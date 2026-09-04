<?php
/* the-abyss/api/kasyno_holdem.php
   Stół Hold'em — cała logika po stronie serwera.

   Przeglądarka wysyła intencję („podbijam 1200"), serwer decyduje i zwraca
   nowy stan. Karty zakryte przeciwników i talia nigdy nie opuszczają PHP.

   Czas realny bez WebSocketów: `kasyno_stoly.wersja` rośnie przy każdej
   zmianie, klient pyta „co nowego od N", a żądanie wisi do 20 s.
   Timery nie potrzebują crona — każde żądanie najpierw woła kh_tick().

   Akcje (?a=):
     stan          long-poll:   stol_id, od
     kasa_kup      {kwota}      gotówka -> żetony (1:1)
     kasa_sprzedaj {kwota}      żetony  -> gotówka (prowizja 3%)
     siadz         {stol_id, wejscie, miejsce?}
     wstan         {stol_id}
     akcja         {stol_id, akcja, kwota}
     powiedz       {stol_id, tresc, typ}
     ranking | pule
*/
declare(strict_types=1);
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/kasyno_core.php';
require_once __DIR__.'/../includes/holdem_engine.php';
require_once __DIR__.'/../includes/holdem_bot.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$polaczenie->set_charset('utf8mb4');

const KH_CZAS_DECYZJI = 45;   // sekundy dla człowieka
const KH_CZAS_BOTA    = 3;    // bot „myśli" chwilę, żeby stół nie migał
const KH_AFK          = 120;  // brak kontaktu tyle sekund = wstajemy od stołu
const KH_PROWIZJA     = 0.03; // 3% przy zamianie żetonów na gotówkę
const KH_POLL         = 20;   // maksymalny czas wisienia long-polla

/* ============================ NARZĘDZIA ============================ */
function kh_ok(array $d = []): never { echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function kh_blad(string $m, int $kod = 400): never {
  http_response_code($kod);
  echo json_encode(['ok'=>false,'blad'=>$m], JSON_UNESCAPED_UNICODE); exit;
}
function kh_wejscie(): array {
  static $b = null;
  if ($b !== null) return $b;
  $j = json_decode(file_get_contents('php://input') ?: '', true);
  return $b = is_array($j) ? $j : $_POST;
}
function kh_in(string $k, $def = null) { $w = kh_wejscie(); return $w[$k] ?? $_GET[$k] ?? $def; }
function kh_int(string $k, int $def = 0): int { $v = kh_in($k, $def); return is_numeric($v) ? (int)$v : $def; }

function kh_q(string $sql, array $p = [], string $typy = ''): mysqli_stmt {
  global $polaczenie;
  $st = $polaczenie->prepare($sql);
  if ($p) $st->bind_param($typy ?: str_repeat('s', count($p)), ...$p);
  $st->execute();
  return $st;
}
function kh_row(string $sql, array $p = [], string $typy = ''): ?array {
  $r = kh_q($sql, $p, $typy)->get_result()->fetch_assoc();
  return $r ?: null;
}
function kh_all(string $sql, array $p = [], string $typy = ''): array {
  return kh_q($sql, $p, $typy)->get_result()->fetch_all(MYSQLI_ASSOC);
}

function kh_gracz_id(): int {
  if (empty($_SESSION['id_gracza'])) kh_blad('Nie jesteś zalogowany.', 401);
  return (int)$_SESSION['id_gracza'];
}

/* --------------------------- PIENIĄDZE ---------------------------
   Deleguje do kc_kasa() z includes/kasyno_core.php — jedna droga do
   zmiany kasy dla wszystkich gier kasyna. Wołać wyłącznie w transakcji. */
function kh_kasa(int $gid, int $dGotowka, int $dZetony, string $powod, ?string $refTyp = null, ?int $refId = null): array {
  return kc_kasa($gid, $dGotowka, $dZetony, $powod, $refTyp, $refId);
}

function kh_tx(callable $fn) {
  global $polaczenie;
  $polaczenie->begin_transaction();
  try { $r = $fn(); $polaczenie->commit(); return $r; }
  catch (Throwable $e) { $polaczenie->rollback(); throw $e; }
}

function kh_bump(int $stolId): void { kh_q('UPDATE kasyno_stoly SET wersja=wersja+1 WHERE id=?', [$stolId], 'i'); }
function kh_stol(int $id): ?array { return kh_row('SELECT * FROM kasyno_stoly WHERE id=?', [$id], 'i'); }
function kh_miejsca(int $stolId): array {
  return kh_all('SELECT m.*, g.login, g.avatar, b.nick AS bot_nick, b.osobowosc
                 FROM kasyno_miejsca m
                 LEFT JOIN gracze g ON g.id = m.gracz_id
                 LEFT JOIN kasyno_boty b ON b.id = m.bot_id
                 WHERE m.stol_id=? ORDER BY m.miejsce', [$stolId], 'i');
}
function kh_sys(int $stolId, string $tresc): void {
  kh_q('INSERT INTO kasyno_wiadomosci (kanal,typ,tresc) VALUES (?,"system",?)', ['stol:'.$stolId, $tresc], 'ss');
}

/* ===================== MASZYNA STANU STOŁU ===================== */

/** Kto gra dalej (nie spasował, nie odszedł). */
function kh_grajacy(array $miejsca): array {
  return array_values(array_filter($miejsca, fn($m) => in_array($m['status'], ['gra','allin'], true)));
}
function kh_moga_decydowac(array $miejsca): array {
  return array_values(array_filter($miejsca, fn($m) => $m['status'] === 'gra' && (int)$m['zetony'] > 0));
}
function kh_obsadzone(array $miejsca): array {
  return array_values(array_filter($miejsca, fn($m) => $m['gracz_id'] !== null || $m['bot_id'] !== null));
}

/** Następne miejsce zgodnie z ruchem wskazówek, które może jeszcze działać. */
function kh_nastepne(array $miejsca, int $od, int $ilesMiejsc): ?int {
  for ($i = 1; $i <= $ilesMiejsc; $i++) {
    $nr = (($od - 1 + $i) % $ilesMiejsc) + 1;
    foreach ($miejsca as $m) {
      if ((int)$m['miejsce'] === $nr && $m['status'] === 'gra' && (int)$m['zetony'] > 0) return $nr;
    }
  }
  return null;
}

/** Główny zegar: wymusza deadline'y, rusza boty, rozdaje, kończy ulice. */
function kh_tick(int $stolId): void {
  $stol = kh_stol($stolId);
  if (!$stol) return;

  // 1. sprzątanie po rozłączonych ludziach
  $afk = kh_all('SELECT gracz_id FROM kasyno_miejsca
                 WHERE stol_id=? AND gracz_id IS NOT NULL
                   AND (widziano IS NULL OR widziano < DATE_SUB(NOW(), INTERVAL ? SECOND))',
                [$stolId, KH_AFK], 'ii');
  foreach ($afk as $a) kh_wstan($stolId, (int)$a['gracz_id'], true);

  $miejsca = kh_miejsca($stolId);
  $ludzie = array_values(array_filter($miejsca, fn($m) => $m['gracz_id'] !== null));
  $boty   = array_values(array_filter($miejsca, fn($m) => $m['bot_id'] !== null));

  // 2. bot dosiada się tylko wtedy, gdy przy stole jest dokładnie jeden człowiek
  if (count($ludzie) === 1 && !$boty) kh_dosadz_bota($stol, $miejsca);
  elseif (count($ludzie) !== 1 && $boty && $stol['faza'] === 'oczekiwanie') {
    foreach ($boty as $b) kh_zwolnij_miejsce($stolId, (int)$b['miejsce'], 'Bot wstaje od stołu.');
  }

  $stol = kh_stol($stolId);
  $miejsca = kh_miejsca($stolId);
  $obsadzone = kh_obsadzone($miejsca);

  // 3. start rozdania
  if ($stol['faza'] === 'oczekiwanie') {
    if (count($obsadzone) >= 2) kh_nowe_rozdanie($stol);
    return;
  }
  if ($stol['faza'] === 'sprzatanie') {
    if ($stol['faza_do'] && strtotime($stol['faza_do']) <= time()) {
      kh_q('UPDATE kasyno_stoly SET faza="oczekiwanie", faza_do=NULL, wersja=wersja+1 WHERE id=?', [$stolId], 'i');
    }
    return;
  }

  // 4. czyj ruch
  $akt = $stol['aktywne_miejsce'] !== null ? (int)$stol['aktywne_miejsce'] : null;
  if ($akt === null) { kh_zakoncz_ulice($stol); return; }

  $m = null;
  foreach ($miejsca as $x) if ((int)$x['miejsce'] === $akt) { $m = $x; break; }
  if (!$m || $m['status'] !== 'gra') { kh_przekaz_ruch($stol, $akt); return; }

  $minal = $stol['faza_do'] && strtotime($stol['faza_do']) <= time();

  // 5. bot decyduje
  if ($m['bot_id'] !== null && $minal) { kh_ruch_bota($stol, $m); return; }

  // 6. człowiek przekroczył 45 s — pas (albo czekam, gdy darmowe)
  if ($m['gracz_id'] !== null && $minal) {
    $doCall = max(0, (int)$stol['zaklad_biezacy'] - (int)$m['wplata_ulica']);
    kh_wykonaj_akcje($stol, $m, $doCall > 0 ? 'pas' : 'czekam', 0, true);
  }
}

/** Wybiera losową osobowość i sadza bota z wejściem minimalnym. */
function kh_dosadz_bota(array $stol, array $miejsca): void {
  $wolne = null;
  foreach ($miejsca as $m) if ($m['gracz_id'] === null && $m['bot_id'] === null) { $wolne = (int)$m['miejsce']; break; }
  if ($wolne === null) return;
  $bot = kh_row('SELECT * FROM kasyno_boty ORDER BY RAND() LIMIT 1');
  if (!$bot) return;
  kh_q('UPDATE kasyno_miejsca SET bot_id=?, zetony=?, status="czeka", widziano=NOW()
        WHERE stol_id=? AND miejsce=?',
    [(int)$bot['id'], (int)$stol['wejscie_min'], (int)$stol['id'], $wolne], 'iiii');
  kh_sys((int)$stol['id'], $bot['nick'].' dosiada się do stołu.');
  kh_bump((int)$stol['id']);
}

/* --------------------------- ROZDANIE --------------------------- */
function kh_nowe_rozdanie(array $stol): void {
  $stolId = (int)$stol['id'];
  kh_tx(function() use ($stol, $stolId) {
    $miejsca = kh_obsadzone(kh_miejsca($stolId));

    // bot bez żetonów dokupuje — sejf kasyna jest nieskończony
    foreach ($miejsca as $m) {
      if ($m['bot_id'] !== null && (int)$m['zetony'] < (int)$stol['blind_duzy'] * 5) {
        kh_q('UPDATE kasyno_miejsca SET zetony=? WHERE stol_id=? AND miejsce=?',
          [(int)$stol['wejscie_min'], $stolId, (int)$m['miejsce']], 'iii');
      }
    }
    // gracz bez żetonów wstaje
    foreach ($miejsca as $m) {
      if ($m['gracz_id'] !== null && (int)$m['zetony'] < (int)$stol['blind_duzy']) {
        kh_wstan($stolId, (int)$m['gracz_id'], false, 'Skończyły ci się żetony przy stole.');
      }
    }

    $miejsca = kh_obsadzone(kh_miejsca($stolId));
    if (count($miejsca) < 2) {
      kh_q('UPDATE kasyno_stoly SET faza="oczekiwanie", faza_do=NULL, aktywne_miejsce=NULL, wersja=wersja+1 WHERE id=?', [$stolId], 'i');
      return;
    }

    $numery = array_map(fn($m) => (int)$m['miejsce'], $miejsca);
    sort($numery);
    $ilesMiejsc = (int)$stol['miejsca'];

    // żeton krupiera przesuwa się o jedno obsadzone miejsce
    $przycisk = $stol['przycisk'] !== null ? (int)$stol['przycisk'] : $numery[count($numery) - 1];
    $idx = array_search($przycisk, $numery, true);
    $przycisk = $numery[($idx === false ? -1 : (int)$idx + 1) % count($numery)];

    $talia = he_potasuj(he_talia());
    $nr = (int)$stol['rozdanie_nr'] + 1;

    kh_q('INSERT INTO kasyno_rozdania (stol_id,nr) VALUES (?,?)', [$stolId, $nr], 'ii');
    global $polaczenie;
    $rozdanieId = (int)$polaczenie->insert_id;

    // reset miejsc + karty zakryte
    foreach ($miejsca as $m) {
      $karty = array_pop($talia).','.array_pop($talia);
      kh_q('UPDATE kasyno_miejsca SET karty=?, status="gra", wplata_ulica=0, wplata_rozdanie=0,
              ostatnia_akcja=NULL, dzialal_w_ulicy=0 WHERE stol_id=? AND miejsce=?',
        [$karty, $stolId, (int)$m['miejsce']], 'sii');
    }

    // blindy — przy dwóch graczach żeton krupiera stawia małą ciemną
    $poz = array_search($przycisk, $numery, true);
    $ilu = count($numery);
    if ($ilu === 2) { $maly = $przycisk; $duzy = $numery[((int)$poz + 1) % 2]; }
    else {
      $maly = $numery[((int)$poz + 1) % $ilu];
      $duzy = $numery[((int)$poz + 2) % $ilu];
    }
    $pula = 0;
    $pula += kh_postaw_blind($stolId, $maly, (int)$stol['blind_maly'], $rozdanieId, 'blind maly');
    $pula += kh_postaw_blind($stolId, $duzy, (int)$stol['blind_duzy'], $rozdanieId, 'blind duzy');

    // pierwszy decyduje: heads-up żeton krupiera, inaczej za dużą ciemną
    $miejscaPo = kh_miejsca($stolId);
    $start = $ilu === 2 ? $przycisk : kh_nastepne($miejscaPo, $duzy, $ilesMiejsc);
    if ($start !== null) {
      $mStart = null;
      foreach ($miejscaPo as $x) if ((int)$x['miejsce'] === $start) { $mStart = $x; break; }
      if ($mStart && $mStart['status'] !== 'gra') $start = kh_nastepne($miejscaPo, $start, $ilesMiejsc);
    }

    kh_q('UPDATE kasyno_stoly SET faza="preflop", board="", talia=?, pula=?, zaklad_biezacy=?,
            ostatni_podbil=?, przycisk=?, aktywne_miejsce=?, rozdanie_id=?, rozdanie_nr=?,
            faza_do=DATE_ADD(NOW(), INTERVAL ? SECOND), wersja=wersja+1 WHERE id=?',
      [implode(',', $talia), $pula, (int)$stol['blind_duzy'], $duzy, $przycisk, $start,
       $rozdanieId, $nr, kh_czas_dla($stolId, $start), $stolId], 'siiiiiiiii');

    kh_sys($stolId, 'Rozdanie #'.$nr.' — karty w powietrzu.');
  });
}

/** Ile sekund dostaje miejsce: bot 3, człowiek 45. */
function kh_czas_dla(int $stolId, ?int $miejsce): int {
  if ($miejsce === null) return KH_CZAS_DECYZJI;
  $m = kh_row('SELECT bot_id FROM kasyno_miejsca WHERE stol_id=? AND miejsce=?', [$stolId, $miejsce], 'ii');
  return $m && $m['bot_id'] !== null ? KH_CZAS_BOTA : KH_CZAS_DECYZJI;
}

function kh_postaw_blind(int $stolId, int $miejsce, int $kwota, int $rozdanieId, string $opis): int {
  $m = kh_row('SELECT * FROM kasyno_miejsca WHERE stol_id=? AND miejsce=? FOR UPDATE', [$stolId, $miejsce], 'ii');
  if (!$m) return 0;
  $realna = min($kwota, (int)$m['zetony']);
  $status = $realna >= (int)$m['zetony'] ? 'allin' : 'gra';
  kh_q('UPDATE kasyno_miejsca SET zetony=zetony-?, wplata_ulica=?, wplata_rozdanie=?, status=?, ostatnia_akcja=?
        WHERE stol_id=? AND miejsce=?',
    [$realna, $realna, $realna, $status, $opis, $stolId, $miejsce], 'iiissii');
  kh_q('INSERT INTO kasyno_akcje (rozdanie_id,gracz_id,bot_id,miejsce,ulica,akcja,kwota)
        VALUES (?,?,?,?,"preflop","blind",?)',
    [$rozdanieId, $m['gracz_id'], $m['bot_id'], $miejsce, $realna], 'iiiii');
  return $realna;
}

/* ---------------------------- AKCJE ---------------------------- */
function kh_wykonaj_akcje(array $stol, array $m, string $akcja, int $kwota, bool $zTimeoutu = false): void {
  $stolId = (int)$stol['id'];
  $miejsce = (int)$m['miejsce'];
  $doCall = max(0, (int)$stol['zaklad_biezacy'] - (int)$m['wplata_ulica']);
  $stos = (int)$m['zetony'];
  $opis = '';

  kh_tx(function() use ($stol, $stolId, $m, $miejsce, $akcja, $kwota, $doCall, $stos, &$opis, $zTimeoutu) {
    switch ($akcja) {
      case 'pas':
        kh_q('UPDATE kasyno_miejsca SET status="spasowal", ostatnia_akcja=?, dzialal_w_ulicy=1
              WHERE stol_id=? AND miejsce=?', [$zTimeoutu ? 'pas (czas)' : 'pas', $stolId, $miejsce], 'sii');
        if ($zTimeoutu) kh_q('UPDATE kasyno_miejsca SET timeouty=timeouty+1 WHERE stol_id=? AND miejsce=?', [$stolId, $miejsce], 'ii');
        $opis = 'pasuje';
        break;

      case 'czekam':
        if ($doCall > 0) kh_blad('Nie możesz czekać — trzeba dorównać '.$doCall.'.', 409);
        kh_q('UPDATE kasyno_miejsca SET ostatnia_akcja="czekam", dzialal_w_ulicy=1
              WHERE stol_id=? AND miejsce=?', [$stolId, $miejsce], 'ii');
        $opis = 'czeka';
        break;

      case 'sprawdzam':
        if ($doCall <= 0) kh_blad('Nie ma czego sprawdzać.', 409);
        $realna = min($doCall, $stos);
        $status = $realna >= $stos ? 'allin' : 'gra';
        kh_q('UPDATE kasyno_miejsca SET zetony=zetony-?, wplata_ulica=wplata_ulica+?, wplata_rozdanie=wplata_rozdanie+?,
                status=?, ostatnia_akcja="sprawdzam", dzialal_w_ulicy=1 WHERE stol_id=? AND miejsce=?',
          [$realna, $realna, $realna, $status, $stolId, $miejsce], 'iiisii');
        kh_q('UPDATE kasyno_stoly SET pula=pula+? WHERE id=?', [$realna, $stolId], 'ii');
        $opis = 'sprawdza '.$realna;
        break;

      case 'podbijam':
      case 'allin':
        $cel = $akcja === 'allin' ? (int)$m['wplata_ulica'] + $stos : $kwota;
        $minimum = (int)$stol['zaklad_biezacy'] + (int)$stol['blind_duzy'];
        if ($akcja === 'podbijam') {
          if ($cel < $minimum && $cel < (int)$m['wplata_ulica'] + $stos)
            kh_blad('Minimalne podbicie to '.$minimum.'.', 409);
          if ($cel > (int)$m['wplata_ulica'] + $stos) kh_blad('Nie masz tylu żetonów.', 409);
        }
        $doplata = min($cel - (int)$m['wplata_ulica'], $stos);
        if ($doplata <= 0) kh_blad('Nieprawidłowa kwota.', 409);
        $nowaWplata = (int)$m['wplata_ulica'] + $doplata;
        $status = $doplata >= $stos ? 'allin' : 'gra';
        kh_q('UPDATE kasyno_miejsca SET zetony=zetony-?, wplata_ulica=?, wplata_rozdanie=wplata_rozdanie+?,
                status=?, ostatnia_akcja=?, dzialal_w_ulicy=1 WHERE stol_id=? AND miejsce=?',
          [$doplata, $nowaWplata, $doplata, $status, $status === 'allin' ? 'all-in' : 'podbija', $stolId, $miejsce], 'iiissii');
        kh_q('UPDATE kasyno_stoly SET pula=pula+?, zaklad_biezacy=?, ostatni_podbil=? WHERE id=?',
          [$doplata, max($nowaWplata, (int)$stol['zaklad_biezacy']), $miejsce, $stolId], 'iiii');
        // po podbiciu wszyscy pozostali muszą odpowiedzieć
        kh_q('UPDATE kasyno_miejsca SET dzialal_w_ulicy=0
              WHERE stol_id=? AND miejsce<>? AND status="gra"', [$stolId, $miejsce], 'ii');
        $opis = ($status === 'allin' ? 'all-in ' : 'podbija do ').$nowaWplata;
        break;

      default:
        kh_blad('Nieznana akcja.', 400);
    }

    kh_q('INSERT INTO kasyno_akcje (rozdanie_id,gracz_id,bot_id,miejsce,ulica,akcja,kwota)
          VALUES (?,?,?,?,?,?,?)',
      [(int)$stol['rozdanie_id'], $m['gracz_id'], $m['bot_id'], $miejsce,
       $stol['faza'], $akcja, $kwota], 'iiiissi');

    $kto = $m['login'] ?? $m['bot_nick'] ?? ('Miejsce '.$miejsce);
    kh_sys($stolId, $kto.' '.$opis.'.');
    kh_bump($stolId);
  });

  kh_przekaz_ruch(kh_stol($stolId), $miejsce);
}

/** Oddaje ruch dalej albo zamyka ulicę, gdy wszyscy odpowiedzieli. */
function kh_przekaz_ruch(array $stol, int $od): void {
  $stolId = (int)$stol['id'];
  $miejsca = kh_miejsca($stolId);
  $grajacy = kh_grajacy($miejsca);

  // został jeden — bierze pulę bez pokazywania kart
  if (count($grajacy) <= 1) { kh_zakoncz_rozdanie($stol, false); return; }

  $moga = kh_moga_decydowac($miejsca);
  $wszyscyOdpowiedzieli = true;
  foreach ($moga as $m) {
    if (!(int)$m['dzialal_w_ulicy'] || (int)$m['wplata_ulica'] < (int)$stol['zaklad_biezacy']) {
      $wszyscyOdpowiedzieli = false; break;
    }
  }
  if ($wszyscyOdpowiedzieli || !$moga) { kh_zakoncz_ulice($stol); return; }

  $nast = kh_nastepne($miejsca, $od, (int)$stol['miejsca']);
  if ($nast === null) { kh_zakoncz_ulice($stol); return; }
  kh_q('UPDATE kasyno_stoly SET aktywne_miejsce=?, faza_do=DATE_ADD(NOW(), INTERVAL ? SECOND), wersja=wersja+1
        WHERE id=?', [$nast, kh_czas_dla($stolId, $nast), $stolId], 'iii');
}

/** Flop, turn, river albo showdown. */
function kh_zakoncz_ulice(array $stol): void {
  $stolId = (int)$stol['id'];
  $kolejna = ['preflop'=>'flop', 'flop'=>'turn', 'turn'=>'river', 'river'=>'showdown'];
  $nowa = $kolejna[$stol['faza']] ?? 'showdown';

  if ($nowa === 'showdown') { kh_zakoncz_rozdanie($stol, true); return; }

  kh_tx(function() use ($stol, $stolId, $nowa) {
    $talia = $stol['talia'] === '' ? [] : explode(',', $stol['talia']);
    $board = $stol['board'] === '' ? [] : explode(',', $stol['board']);
    array_shift($talia);                                   // karta spalona
    $ile = $nowa === 'flop' ? 3 : 1;
    for ($i = 0; $i < $ile; $i++) { $k = array_shift($talia); if ($k) $board[] = $k; }

    kh_q('UPDATE kasyno_miejsca SET wplata_ulica=0, dzialal_w_ulicy=0, ostatnia_akcja=NULL
          WHERE stol_id=? AND status="gra"', [$stolId], 'i');

    $miejsca = kh_miejsca($stolId);
    // po flopie pierwszy decyduje gracz z lewej strony żetonu krupiera
    $start = kh_nastepne($miejsca, (int)$stol['przycisk'], (int)$stol['miejsca']);

    kh_q('UPDATE kasyno_stoly SET faza=?, board=?, talia=?, zaklad_biezacy=0, ostatni_podbil=NULL,
            aktywne_miejsce=?, faza_do=DATE_ADD(NOW(), INTERVAL ? SECOND), wersja=wersja+1 WHERE id=?',
      [$nowa, implode(',', $board), implode(',', $talia), $start,
       kh_czas_dla($stolId, $start), $stolId], 'sssiii');
    kh_sys($stolId, strtoupper($nowa).': '.implode(' ', array_slice($board, $nowa === 'flop' ? 0 : -1)));
  });

  // gdy wszyscy pozostali są all-in, nie ma już decyzji — lecimy dalej
  $stolPo = kh_stol($stolId);
  if (!kh_moga_decydowac(kh_miejsca($stolId))) kh_zakoncz_ulice($stolPo);
}

/** Rozliczenie: rake, podział puli, statystyki, reputacja, wada. */
function kh_zakoncz_rozdanie(array $stol, bool $showdown): void {
  $stolId = (int)$stol['id'];
  kh_tx(function() use ($stol, $stolId, $showdown) {
    $miejsca = kh_miejsca($stolId);
    $grajacy = kh_grajacy($miejsca);
    $board = $stol['board'] === '' ? [] : explode(',', $stol['board']);
    $bylFlop = count($board) >= 3;

    $wplaty = []; $sily = []; $walczy = [];
    foreach ($miejsca as $m) {
      $nr = (int)$m['miejsce'];
      $wplaty[$nr] = (int)$m['wplata_rozdanie'];
      if (in_array($m['status'], ['gra','allin'], true)) {
        $walczy[] = $nr;
        $sily[$nr] = $showdown && $m['karty'] !== ''
          ? he_najlepszy(array_merge(explode(',', $m['karty']), $board))
          : ['wynik'=>1, 'nazwa'=>'—'];
      }
    }

    $pula = (int)$stol['pula'];
    $rake = he_rake($pula, (int)$stol['blind_duzy'], $bylFlop);
    $doPodzialu = $pula - $rake;

    if (count($grajacy) === 1) {                    // wszyscy inni spasowali
      $zwyciezca = (int)$grajacy[0]['miejsce'];
      $wyplaty = array_fill_keys(array_keys($wplaty), 0);
      $wyplaty[$zwyciezca] = $doPodzialu;
    } else {
      $skala = $pula > 0 ? $doPodzialu / $pula : 1;
      $wyplaty = he_podziel_pule($wplaty, $walczy, $sily);
      foreach ($wyplaty as $nr => $v) $wyplaty[$nr] = (int)floor($v * $skala);
    }

    $wynik = [];
    foreach ($miejsca as $m) {
      $nr = (int)$m['miejsce'];
      $wygral = $wyplaty[$nr] ?? 0;
      if ($wygral > 0) kh_q('UPDATE kasyno_miejsca SET zetony=zetony+? WHERE stol_id=? AND miejsce=?', [$wygral, $stolId, $nr], 'iii');

      if ($m['gracz_id'] !== null && (int)$m['wplata_rozdanie'] > 0) {
        $gid = (int)$m['gracz_id'];
        $netto = $wygral - (int)$m['wplata_rozdanie'];
        kh_q('UPDATE gracze SET kasyno_netto = kasyno_netto + ?, kasyno_rozdania = kasyno_rozdania + 1,
                kasyno_wygrana_max = GREATEST(kasyno_wygrana_max, ?), kasyno_ostatnia_gra = NOW()
              WHERE id=?', [$netto, $wygral, $gid], 'iii');
        kh_q('INSERT INTO kasyno_udzial (rozdanie_id,gracz_id,wplacil,wzial,uklad) VALUES (?,?,?,?,?)',
          [(int)$stol['rozdanie_id'], $gid, (int)$m['wplata_rozdanie'], $wygral,
           $sily[$nr]['nazwa'] ?? null], 'iiiis');
        kc_sprawdz_wade($gid);
      }
      if (in_array($m['status'], ['gra','allin'], true)) {
        $wynik[] = ['miejsce'=>$nr,
                    'kto'=>$m['login'] ?? $m['bot_nick'],
                    'karty'=>$showdown ? $m['karty'] : null,
                    'uklad'=>$sily[$nr]['nazwa'] ?? null,
                    'wzial'=>$wygral];
      }
    }

    kh_q('UPDATE kasyno_rozdania SET board=?, pula=?, rake=?, wynik=?, koniec=NOW() WHERE id=?',
      [$stol['board'], $pula, $rake, json_encode($wynik, JSON_UNESCAPED_UNICODE), (int)$stol['rozdanie_id']], 'siisi');

    $opis = [];
    foreach ($wynik as $w) if ($w['wzial'] > 0) $opis[] = $w['kto'].' bierze '.number_format($w['wzial'], 0, '', ' ').($w['uklad'] && $showdown ? ' ('.$w['uklad'].')' : '');
    kh_sys($stolId, ($opis ? implode(', ', $opis) : 'Pula rozdana').($rake > 0 ? ' · rake '.$rake : '').'.');

    kh_q('UPDATE kasyno_stoly SET faza="sprzatanie", faza_do=DATE_ADD(NOW(), INTERVAL 6 SECOND),
            aktywne_miejsce=NULL, pula=0, zaklad_biezacy=0, ostatni_podbil=NULL, wersja=wersja+1 WHERE id=?', [$stolId], 'i');
  });
}

/* ------------------------------ BOT ------------------------------ */
function kh_ruch_bota(array $stol, array $m): void {
  $bot = kh_row('SELECT * FROM kasyno_boty WHERE id=?', [(int)$m['bot_id']], 'i');
  if (!$bot) return;
  $board = $stol['board'] === '' ? [] : explode(',', $stol['board']);
  $d = bot_decyzja($bot['osobowosc'], [
    'reka'            => $m['karty'] === '' ? [] : explode(',', $m['karty']),
    'board'           => $board,
    'pula'            => (int)$stol['pula'],
    'do_sprawdzenia'  => max(0, (int)$stol['zaklad_biezacy'] - (int)$m['wplata_ulica']),
    'zetony'          => (int)$m['zetony'],
    'blind_duzy'      => (int)$stol['blind_duzy'],
  ]);
  if (!empty($d['mowa'])) {
    kh_q('INSERT INTO kasyno_wiadomosci (kanal,bot_id,typ,tresc) VALUES (?,?,"mowa",?)',
      ['stol:'.(int)$stol['id'], (int)$bot['id'], $d['mowa']], 'sis');
  }
  kh_wykonaj_akcje($stol, $m, $d['akcja'], (int)$d['kwota']);
}

/* --------------------------- SIADANIE --------------------------- */
function kh_wstan(int $stolId, int $gid, bool $timeout = false, ?string $powod = null): void {
  kh_tx(function() use ($stolId, $gid, $timeout, $powod) {
    $m = kh_row('SELECT * FROM kasyno_miejsca WHERE stol_id=? AND gracz_id=? FOR UPDATE', [$stolId, $gid], 'ii');
    if (!$m) return;
    $zwrot = (int)$m['zetony'];
    if ($zwrot > 0) kh_kasa($gid, 0, $zwrot, 'wyjscie_ze_stolu', 'stol', $stolId);
    $login = kh_row('SELECT login FROM gracze WHERE id=?', [$gid], 'i')['login'] ?? 'Gracz';
    kh_q('UPDATE kasyno_miejsca SET gracz_id=NULL, zetony=0, status="wolne", karty="", wplata_ulica=0,
            wplata_rozdanie=0, ostatnia_akcja=NULL, dzialal_w_ulicy=0, widziano=NULL, timeouty=0
          WHERE stol_id=? AND miejsce=?', [$stolId, (int)$m['miejsce']], 'ii');
    kh_sys($stolId, $login.($timeout ? ' odchodzi od stołu (brak reakcji).' : ' wstaje od stołu.').($powod ? ' '.$powod : ''));
    kh_bump($stolId);
  });
}

function kh_zwolnij_miejsce(int $stolId, int $miejsce, string $komunikat): void {
  kh_q('UPDATE kasyno_miejsca SET gracz_id=NULL, bot_id=NULL, zetony=0, status="wolne", karty="",
          wplata_ulica=0, wplata_rozdanie=0, ostatnia_akcja=NULL, dzialal_w_ulicy=0, widziano=NULL
        WHERE stol_id=? AND miejsce=?', [$stolId, $miejsce], 'ii');
  kh_sys($stolId, $komunikat);
  kh_bump($stolId);
}

/* ---------------------------- WIDOK ----------------------------
   To, co klient ma prawo zobaczyć. Karty zakryte innych graczy i talia
   nigdy tu nie trafiają.                                            */
function kh_widok(int $stolId, int $gid): array {
  $stol = kh_stol($stolId);
  $miejsca = kh_miejsca($stolId);
  $board = $stol['board'] === '' ? [] : explode(',', $stol['board']);
  $showdown = in_array($stol['faza'], ['sprzatanie'], true);

  $mojeMiejsce = null; $mojeKarty = null; $mojaWplata = 0; $mojeZetony = 0;
  $lista = [];
  foreach ($miejsca as $m) {
    $nr = (int)$m['miejsce'];
    $ja = $m['gracz_id'] !== null && (int)$m['gracz_id'] === $gid;
    if ($ja) {
      $mojeMiejsce = $nr;
      $mojeKarty = $m['karty'] === '' ? null : array_map('he_karta_html', explode(',', $m['karty']));
      $mojaWplata = (int)$m['wplata_ulica'];
      $mojeZetony = (int)$m['zetony'];
    }
    $lista[] = [
      'miejsce'   => $nr,
      'kto'       => $m['login'] ?? $m['bot_nick'],
      'bot'       => $m['bot_id'] !== null,
      'osobowosc' => $m['osobowosc'],
      'avatar'    => $m['avatar'] ?? null,
      'ja'        => $ja,
      'zetony'    => (int)$m['zetony'],
      'status'    => $m['status'],
      'wplata'    => (int)$m['wplata_ulica'],
      'akcja'     => $m['ostatnia_akcja'],
      'karty'     => ($ja || $showdown) && $m['karty'] !== '' ? array_map('he_karta_html', explode(',', $m['karty'])) : null,
      'zakryte'   => $m['karty'] !== '' && !$ja && !$showdown ? 2 : 0,
    ];
  }

  $doCall = max(0, (int)$stol['zaklad_biezacy'] - $mojaWplata);
  $g = kh_row('SELECT gotowka, zetony, bank FROM gracze WHERE id=?', [$gid], 'i') ?: ['gotowka'=>0,'zetony'=>0,'bank'=>0];

  return [
    'wersja' => (int)$stol['wersja'],
    'stol' => [
      'id'=>$stolId, 'nazwa'=>$stol['nazwa'],
      'blind_maly'=>(int)$stol['blind_maly'], 'blind_duzy'=>(int)$stol['blind_duzy'],
      'wejscie_min'=>(int)$stol['wejscie_min'], 'prog_majatku'=>(int)$stol['prog_majatku'],
      'faza'=>$stol['faza'], 'rozdanie_nr'=>(int)$stol['rozdanie_nr'],
      'pula'=>(int)$stol['pula'], 'zaklad_biezacy'=>(int)$stol['zaklad_biezacy'],
      'aktywne_miejsce'=>$stol['aktywne_miejsce'] !== null ? (int)$stol['aktywne_miejsce'] : null,
      'przycisk'=>$stol['przycisk'] !== null ? (int)$stol['przycisk'] : null,
      'zostalo'=>$stol['faza_do'] ? max(0, strtotime($stol['faza_do']) - time()) : null,
      'czas_decyzji'=>KH_CZAS_DECYZJI,
      'rake_opis'=>'5% puli, maks. '.((int)$stol['blind_duzy'] * 3),
    ],
    'board'    => array_map('he_karta_html', $board),
    'miejsca'  => $lista,
    'moje'     => ['miejsce'=>$mojeMiejsce, 'karty'=>$mojeKarty, 'zetony'=>$mojeZetony,
                   'do_sprawdzenia'=>$doCall, 'moja_tura'=>$mojeMiejsce !== null && (int)$stol['aktywne_miejsce'] === $mojeMiejsce,
                   'min_podbicie'=>(int)$stol['zaklad_biezacy'] + (int)$stol['blind_duzy']],
    'portfel'  => ['gotowka'=>(int)$g['gotowka'], 'zetony'=>(int)$g['zetony'], 'bank'=>(int)$g['bank']],
    'widzowie' => kh_all('SELECT g.login FROM kasyno_widzowie w JOIN gracze g ON g.id=w.gracz_id
                          WHERE w.stol_id=? AND w.widziano > DATE_SUB(NOW(), INTERVAL 90 SECOND)
                            AND w.gracz_id NOT IN (SELECT COALESCE(gracz_id,0) FROM kasyno_miejsca WHERE stol_id=?)',
                         [$stolId, $stolId], 'ii'),
    'czat'     => array_reverse(kh_all('SELECT w.id, w.typ, w.tresc, w.czas, g.login, b.nick AS bot_nick
                    FROM kasyno_wiadomosci w
                    LEFT JOIN gracze g ON g.id=w.gracz_id
                    LEFT JOIN kasyno_boty b ON b.id=w.bot_id
                    WHERE w.kanal=? ORDER BY w.id DESC LIMIT 40', ['stol:'.$stolId], 's')),
  ];
}

/* ============================ ROUTING ============================ */
$a = (string)kh_in('a', 'stan');
$gid = kh_gracz_id();

if ($a === 'kasa_kup' || $a === 'kasa_sprzedaj') {
  $kwota = kh_int('kwota');
  if ($kwota <= 0) kh_blad('Kwota musi być dodatnia.');
  if (kh_row('SELECT 1 x FROM kasyno_miejsca WHERE gracz_id=?', [$gid], 'i')) kh_blad('Najpierw wstań od stołu.', 409);

  $w = kh_tx(function() use ($a, $gid, $kwota) {
    if ($a === 'kasa_kup') {
      $g = kh_row('SELECT gotowka, bank FROM gracze WHERE id=?', [$gid], 'i');
      $prog = (int)(kh_row('SELECT MIN(prog_majatku) p FROM kasyno_stoly')['p'] ?? 50000);
      if ((int)$g['gotowka'] + (int)$g['bank'] < $prog)
        kh_blad('Kasa kasyna obsługuje wyłącznie majątki od '.number_format($prog, 0, '', ' ').' $.', 403);
      return kh_kasa($gid, -$kwota, $kwota, 'kup_zetony');
    }
    $prowizja = (int)floor($kwota * KH_PROWIZJA);
    $w = kh_kasa($gid, $kwota - $prowizja, -$kwota, 'sprzedaj_zetony');
    if ($prowizja > 0) kh_q('INSERT INTO kasyno_ledger (gracz_id,delta_gotowka,delta_zetony,gotowka_po,zetony_po,powod)
                             VALUES (?,?,0,?,?,"prowizja")',
      [$gid, -$prowizja, $w['gotowka'], $w['zetony']], 'iiii');
    return $w + ['prowizja'=>$prowizja];
  });
  kh_ok(['portfel'=>$w]);
}

$stolId = kh_int('stol_id', 1);
if (!kh_stol($stolId)) kh_blad('Nie ma takiego stołu.', 404);

if ($a === 'siadz') {
  $wejscie = kh_int('wejscie');
  $stol = kh_stol($stolId);
  $g = kh_row('SELECT gotowka, bank, zetony FROM gracze WHERE id=?', [$gid], 'i');
  if ((int)$g['gotowka'] + (int)$g['bank'] < (int)$stol['prog_majatku'])
    kh_blad('Ochrona nie wpuszcza cię do sali gier. Wymagany majątek: '.number_format((int)$stol['prog_majatku'], 0, '', ' ').' $.', 403);
  if ($wejscie < (int)$stol['wejscie_min'])
    kh_blad('Minimalne wejście do tego stołu: '.number_format((int)$stol['wejscie_min'], 0, '', ' ').' żetonów.');

  kh_tx(function() use ($stolId, $gid, $wejscie, $stol) {
    if (kh_row('SELECT 1 x FROM kasyno_miejsca WHERE gracz_id=?', [$gid], 'i')) kh_blad('Już siedzisz przy stole.', 409);
    $chce = kh_int('miejsce');
    $m = $chce
      ? kh_row('SELECT * FROM kasyno_miejsca WHERE stol_id=? AND miejsce=? AND gracz_id IS NULL AND bot_id IS NULL FOR UPDATE', [$stolId, $chce], 'ii')
      : kh_row('SELECT * FROM kasyno_miejsca WHERE stol_id=? AND gracz_id IS NULL AND bot_id IS NULL ORDER BY miejsce LIMIT 1 FOR UPDATE', [$stolId], 'i');
    if (!$m) kh_blad('To miejsce jest zajęte.', 409);
    kh_kasa($gid, 0, -$wejscie, 'wejscie_do_stolu', 'stol', $stolId);
    kh_q('UPDATE kasyno_miejsca SET gracz_id=?, zetony=?, status="czeka", widziano=NOW()
          WHERE stol_id=? AND miejsce=?', [$gid, $wejscie, $stolId, (int)$m['miejsce']], 'iiii');
    kh_q('DELETE FROM kasyno_widzowie WHERE stol_id=? AND gracz_id=?', [$stolId, $gid], 'ii');
    $login = kh_row('SELECT login FROM gracze WHERE id=?', [$gid], 'i')['login'] ?? 'Gracz';
    kh_sys($stolId, $login.' dosiada się do stołu z '.number_format($wejscie, 0, '', ' ').' żetonami.');
    kh_bump($stolId);
  });
  kh_tick($stolId);
  kh_ok(kh_widok($stolId, $gid));
}

if ($a === 'wstan') { kh_wstan($stolId, $gid); kh_tick($stolId); kh_ok(kh_widok($stolId, $gid)); }

if ($a === 'akcja') {
  kh_tick($stolId);
  $stol = kh_stol($stolId);
  $m = kh_row('SELECT m.*, g.login, b.nick AS bot_nick FROM kasyno_miejsca m
               LEFT JOIN gracze g ON g.id=m.gracz_id LEFT JOIN kasyno_boty b ON b.id=m.bot_id
               WHERE m.stol_id=? AND m.gracz_id=?', [$stolId, $gid], 'ii');
  if (!$m) kh_blad('Nie siedzisz przy tym stole.', 403);
  if ((int)$stol['aktywne_miejsce'] !== (int)$m['miejsce']) kh_blad('Teraz nie twoja tura.', 409);
  if ($m['status'] !== 'gra') kh_blad('Nie bierzesz udziału w tym rozdaniu.', 409);
  kh_wykonaj_akcje($stol, $m, (string)kh_in('akcja', ''), kh_int('kwota'));
  kh_ok(kh_widok($stolId, $gid));
}

if ($a === 'powiedz') {
  $tresc = trim((string)kh_in('tresc', ''));
  if ($tresc === '') kh_blad('Puste.');
  if (mb_strlen($tresc) > 600) kh_blad('Za długa wiadomość.');
  $typ = 'mowa';
  if (str_starts_with($tresc, '/me ')) { $typ = 'akcja'; $tresc = trim(substr($tresc, 4)); }
  $mg = (int)(kh_row('SELECT jest_mistrzem_gry FROM gracze WHERE id=?', [$gid], 'i')['jest_mistrzem_gry'] ?? 0);
  if ($mg && str_starts_with($tresc, '/mg ')) { $typ = 'mg'; $tresc = trim(substr($tresc, 4)); }
  kh_q('INSERT INTO kasyno_wiadomosci (kanal,gracz_id,typ,tresc) VALUES (?,?,?,?)',
    ['stol:'.$stolId, $gid, $typ, $tresc], 'siss');
  kh_bump($stolId);
  kh_ok();
}

if ($a === 'ranking') kh_ok(['rows'=>kh_all('SELECT * FROM v_kasyno_ranking ORDER BY netto DESC LIMIT 50')]);
if ($a === 'pule')    kh_ok(['rows'=>kh_all('SELECT * FROM v_kasyno_pule_dnia LIMIT 10')]);

/* ------------------------ STAN (long-poll) ------------------------ */
if ($a === 'stan') {
  $od = kh_int('od', -1);
  // obecność: gracz przy stole albo widz
  if (kh_row('SELECT 1 x FROM kasyno_miejsca WHERE stol_id=? AND gracz_id=?', [$stolId, $gid], 'ii')) {
    kh_q('UPDATE kasyno_miejsca SET widziano=NOW() WHERE stol_id=? AND gracz_id=?', [$stolId, $gid], 'ii');
  } else {
    kh_q('INSERT INTO kasyno_widzowie (stol_id,gracz_id) VALUES (?,?)
          ON DUPLICATE KEY UPDATE widziano=NOW()', [$stolId, $gid], 'ii');
  }
  $koniec = microtime(true) + KH_POLL;
  do {
    kh_tick($stolId);
    $w = (int)kh_row('SELECT wersja FROM kasyno_stoly WHERE id=?', [$stolId], 'i')['wersja'];
    if ($w !== $od) kh_ok(kh_widok($stolId, $gid));
    usleep(500000);
  } while (microtime(true) < $koniec);
  kh_ok(['bez_zmian'=>true, 'wersja'=>$od]);
}

kh_blad('Nieznana akcja.', 404);
