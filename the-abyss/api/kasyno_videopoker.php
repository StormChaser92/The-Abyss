<?php
/* the-abyss/api/kasyno_videopoker.php
   Video Poker — Jacks or Better 8/5 z double-up.

   Serwer tasuje, ocenia układ i wypłaca. Przeglądarka wysyła tylko
   „rozdaj", „trzymam te karty", „podwajam". Talia i karty double-up
   nigdy nie opuszczają PHP, więc podgląd źródła nic nie daje.

   Stan rundy siedzi w tabeli, nie w sesji — odświeżenie strony w połowie
   rozdania wraca do tej samej ręki.

   Akcje (?a=):
     stan                          otwarta runda albo nic
     rozdaj    {zetony, klucz}     pobiera stawkę, rozdaje 5 kart
     dobierz   {runda_id, trzymane}
     podwajam  {runda_id}          start double-up
     wybierz   {runda_id, karta}   0-3, odsłania i rozstrzyga
     zbierz    {runda_id}          przelewa wygraną do portfela
     trafienia
*/
declare(strict_types=1);
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/kasyno_core.php';
require_once __DIR__.'/../includes/holdem_engine.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$polaczenie->set_charset('utf8mb4');

const VP_ZETON     = 100;  // wartość jednego żetonu stawki
const VP_MAX_ZETON = 5;    // maksymalna stawka w żetonach
const VP_MAX_DOUBLE = 5;   // ile razy z rzędu można podwajać

/* Jacks or Better 8/5 — RTP ok. 97,3%, czyli tyle samo co ruletka.
   Mnożniki na żeton; poker królewski przy maksymalnej stawce płaci 800. */
const VP_TABELA = [
  'POKER KRÓLEWSKI' => [250, 500, 750, 1000, 4000],  // per liczba żetonów
  'POKER'           => [50,  100, 150, 200,  250],
  'KARETA'          => [25,  50,  75,  100,  125],
  'FULL'            => [8,   16,  24,  32,   40],
  'KOLOR'           => [5,   10,  15,  20,   25],
  'STRIT'           => [4,   8,   12,  16,   20],
  'TRÓJKA'          => [3,   6,   9,   12,   15],
  'DWIE PARY'       => [2,   4,   6,   8,    10],
  'WALETY LUB LEPSZE' => [1,  2,   3,   4,    5],
];

/** Ocena pięciu kart w regułach Jacks or Better. Zwraca [nazwa, mnożnik]. */
function vp_ocen(array $karty, int $zetony): array {
  $o = he_ocena5($karty);
  $kat = $o['kat'];
  $nazwa = null;

  if ($kat === 8) $nazwa = ($o['kickery'][0] ?? 0) === 14 ? 'POKER KRÓLEWSKI' : 'POKER';
  elseif ($kat === 7) $nazwa = 'KARETA';
  elseif ($kat === 6) $nazwa = 'FULL';
  elseif ($kat === 5) $nazwa = 'KOLOR';
  elseif ($kat === 4) $nazwa = 'STRIT';
  elseif ($kat === 3) $nazwa = 'TRÓJKA';
  elseif ($kat === 2) $nazwa = 'DWIE PARY';
  elseif ($kat === 1 && ($o['kickery'][0] ?? 0) >= 11) $nazwa = 'WALETY LUB LEPSZE';

  if ($nazwa === null) return ['BRAK UKŁADU', 0];
  return [$nazwa, VP_TABELA[$nazwa][max(0, min(VP_MAX_ZETON, $zetony) - 1)]];
}

/** Karty do interfejsu; zakryte oddajemy jako null. */
function vp_karty(string $csv, ?array $odslon = null): array {
  if ($csv === '') return [];
  $out = [];
  foreach (explode(',', $csv) as $i => $k) {
    $out[] = ($odslon === null || in_array($i, $odslon, true)) ? he_karta_html($k) : null;
  }
  return $out;
}

function vp_widok(array $r): array {
  $stan = $r['stan'];
  $double = $stan === 'double';
  // w trakcie double-up widać kartę krupiera; wybory zostają zakryte
  $odslon = null;
  if ($double) $odslon = $r['double_wybor'] !== null
    ? [0, (int)$r['double_wybor'] + 1]
    : [0];

  return [
    'runda_id'      => (int)$r['id'],
    'stan'          => $stan,
    'stawka'        => (int)$r['stawka'],
    'zetony_stawka' => (int)$r['zetony_stawka'],
    'karty'         => vp_karty($r['karty']),
    'trzymane'      => $r['trzymane'] === '' ? [0,0,0,0,0] : array_map('intval', explode(',', $r['trzymane'])),
    'uklad'         => $r['uklad'],
    'mnoznik'       => (int)$r['mnoznik'],
    'wyplata'       => (int)$r['wyplata'],
    'double_poziom' => (int)$r['double_poziom'],
    'double_max'    => VP_MAX_DOUBLE,
    'double_karty'  => $double ? vp_karty($r['double_karty'], $odslon) : [],
    'double_wybor'  => $r['double_wybor'] !== null ? (int)$r['double_wybor'] : null,
  ];
}

function vp_portfel(int $gid): array {
  $g = kc_row('SELECT gotowka, zetony, bank FROM gracze WHERE id=?', [$gid], 'i') ?: [];
  return ['gotowka'=>(int)($g['gotowka'] ?? 0), 'zetony'=>(int)($g['zetony'] ?? 0), 'bank'=>(int)($g['bank'] ?? 0)];
}

/** Otwarta runda gracza albo null. */
function vp_otwarta(int $gid): ?array {
  return kc_row('SELECT * FROM kasyno_solo WHERE gracz_id=? AND gra="videopoker" AND stan<>"zakonczona"
                 ORDER BY id DESC LIMIT 1', [$gid], 'i');
}
function vp_zamknij_runde(array $r, int $gid): void {
  kc_q('INSERT INTO kasyno_solo_udzial (gracz_id,gra,stawka,wyplata) VALUES (?,"videopoker",?,?)',
    [$gid, (int)$r['stawka'], (int)$r['wyplata']], 'iii');
  kc_zapisz_wynik($gid, (int)$r['stawka'], (int)$r['wyplata'], 'videopoker');
}

/* ============================ ROUTING ============================ */
$a = (string)kc_in('a', 'stan');
$gid = kc_gracz_id();

if ($a === 'tabela') kc_ok(['tabela'=>VP_TABELA, 'zeton'=>VP_ZETON, 'max_zeton'=>VP_MAX_ZETON]);

if ($a === 'trafienia')
  kc_ok(['rows'=>kc_all('SELECT login, gra, stawka, wyplata, uklad FROM v_kasyno_solo_trafienia LIMIT 8')]);

if ($a === 'stan') {
  $r = vp_otwarta($gid);
  kc_ok(['runda'=>$r ? vp_widok($r) : null, 'portfel'=>vp_portfel($gid)]);
}

/* --------------------------- ROZDANIE --------------------------- */
if ($a === 'rozdaj') {
  kc_sprawdz_prog($gid);
  $zetony = max(1, min(VP_MAX_ZETON, kc_int('zetony', 1)));
  $stawka = $zetony * VP_ZETON;
  $klucz = (string)kc_in('klucz', '');
  $klucz = $klucz !== '' ? substr($klucz, 0, 64) : null;

  // idempotencja — powtórka żądania zwraca tę samą rundę, nie pobiera drugiej stawki
  if ($klucz !== null) {
    $stara = kc_row('SELECT * FROM kasyno_solo WHERE gracz_id=? AND klucz_zadania=?', [$gid, $klucz], 'is');
    if ($stara) kc_ok(['runda'=>vp_widok($stara), 'portfel'=>vp_portfel($gid), 'powtorka'=>true]);
  }
  if (vp_otwarta($gid)) kc_blad('Masz niedokończoną rundę.', 409);

  $out = kc_tx(function() use ($gid, $zetony, $stawka, $klucz) {
    kc_kasa($gid, 0, -$stawka, 'zaklad_videopoker');
    $talia = he_potasuj(he_talia());
    $reka = array_splice($talia, 0, 5);
    kc_q('INSERT INTO kasyno_solo (gracz_id,gra,stawka,zetony_stawka,stan,karty,talia,klucz_zadania)
          VALUES (?,"videopoker",?,?,"dobranie",?,?,?)',
      [$gid, $stawka, $zetony, implode(',', $reka), implode(',', $talia), $klucz], 'iiisss');
    global $polaczenie;
    return kc_row('SELECT * FROM kasyno_solo WHERE id=?', [(int)$polaczenie->insert_id], 'i');
  });
  kc_ok(['runda'=>vp_widok($out), 'portfel'=>vp_portfel($gid)]);
}

/* --------------------------- DOBIERANIE -------------------------- */
if ($a === 'dobierz') {
  $trzymane = kc_in('trzymane');
  if (!is_array($trzymane)) kc_blad('Brak listy trzymanych kart.');

  $out = kc_tx(function() use ($gid, $trzymane) {
    $r = kc_row('SELECT * FROM kasyno_solo WHERE id=? AND gracz_id=? AND stan="dobranie" FOR UPDATE',
      [kc_int('runda_id'), $gid], 'ii');
    if (!$r) kc_blad('Runda nie istnieje albo już rozliczona.', 409);

    $reka = explode(',', $r['karty']);
    $talia = $r['talia'] === '' ? [] : explode(',', $r['talia']);
    $flagi = [];
    for ($i = 0; $i < 5; $i++) {
      $flagi[$i] = !empty($trzymane[$i]) ? 1 : 0;
      if (!$flagi[$i]) $reka[$i] = array_shift($talia);
    }

    [$nazwa, $mnoznik] = vp_ocen($reka, (int)$r['zetony_stawka']);
    $wyplata = $mnoznik * VP_ZETON;
    // wygrana zostaje w rundzie: gracz może ją zebrać albo podwoić
    $stan = $wyplata > 0 ? 'double' : 'zakonczona';

    kc_q('UPDATE kasyno_solo SET karty=?, talia=?, trzymane=?, uklad=?, mnoznik=?, wyplata=?, stan=?
          WHERE id=?',
      [implode(',', $reka), implode(',', $talia), implode(',', $flagi),
       $nazwa, $mnoznik, $wyplata, $stan, (int)$r['id']], 'ssssiisi');

    $r2 = kc_row('SELECT * FROM kasyno_solo WHERE id=?', [(int)$r['id']], 'i');
    if ($stan === 'zakonczona') vp_zamknij_runde($r2, $gid);
    else {
      // przygotuj karty do ewentualnego double-up: krupier + 4 zakryte
      $karty = array_splice($talia, 0, 5);
      kc_q('UPDATE kasyno_solo SET double_karty=?, talia=? WHERE id=?',
        [implode(',', $karty), implode(',', $talia), (int)$r['id']], 'ssi');
      $r2 = kc_row('SELECT * FROM kasyno_solo WHERE id=?', [(int)$r['id']], 'i');
    }
    return $r2;
  });
  kc_ok(['runda'=>vp_widok($out), 'portfel'=>vp_portfel($gid)]);
}

/* ---------------------------- DOUBLE-UP --------------------------
   Krupier odkrywa jedną kartę. Gracz wybiera jedną z czterech zakrytych.
   Wyżej = podwojenie, niżej = przepada wszystko, równo = jeszcze raz.  */
if ($a === 'wybierz') {
  $wybor = kc_int('karta', -1);
  if ($wybor < 0 || $wybor > 3) kc_blad('Wybierz jedną z czterech kart.');

  $out = kc_tx(function() use ($gid, $wybor) {
    $r = kc_row('SELECT * FROM kasyno_solo WHERE id=? AND gracz_id=? AND stan="double" FOR UPDATE',
      [kc_int('runda_id'), $gid], 'ii');
    if (!$r) kc_blad('Nie ma czego podwajać.', 409);
    if ((int)$r['double_poziom'] >= VP_MAX_DOUBLE) kc_blad('Limit podwojeń wyczerpany — zbierz wygraną.', 409);

    $karty = explode(',', $r['double_karty']);
    $krupier = he_ranga($karty[0]);
    $moja = he_ranga($karty[$wybor + 1]);
    $wyplata = (int)$r['wyplata'];
    $poziom = (int)$r['double_poziom'];

    if ($moja > $krupier) {
      $wyplata *= 2; $poziom++;
      $wynik = 'wygrana';
    } elseif ($moja < $krupier) {
      $wyplata = 0;
      $wynik = 'przegrana';
    } else {
      $wynik = 'remis';
    }

    kc_q('UPDATE kasyno_solo SET wyplata=?, double_poziom=?, double_wybor=? WHERE id=?',
      [$wyplata, $poziom, $wybor, (int)$r['id']], 'iiii');

    if ($wynik === 'przegrana') {
      kc_q('UPDATE kasyno_solo SET stan="zakonczona" WHERE id=?', [(int)$r['id']], 'i');
      $r2 = kc_row('SELECT * FROM kasyno_solo WHERE id=?', [(int)$r['id']], 'i');
      vp_zamknij_runde($r2, $gid);
      return [$r2, $wynik, $karty[0], $karty[$wybor + 1]];
    }

    // remis i wygrana: świeże karty na kolejną próbę
    $talia = $r['talia'] === '' ? he_potasuj(he_talia()) : explode(',', $r['talia']);
    if (count($talia) < 5) $talia = he_potasuj(he_talia());
    $nowe = array_splice($talia, 0, 5);
    kc_q('UPDATE kasyno_solo SET double_karty=?, talia=?, double_wybor=NULL WHERE id=?',
      [implode(',', $nowe), implode(',', $talia), (int)$r['id']], 'ssi');

    $r2 = kc_row('SELECT * FROM kasyno_solo WHERE id=?', [(int)$r['id']], 'i');
    return [$r2, $wynik, $karty[0], $karty[$wybor + 1]];
  });

  [$r2, $wynik, $kartaKrupiera, $kartaGracza] = $out;
  kc_ok([
    'runda'   => vp_widok($r2),
    'portfel' => vp_portfel($gid),
    'wynik'   => $wynik,
    'odkryte' => ['krupier'=>he_karta_html($kartaKrupiera), 'gracz'=>he_karta_html($kartaGracza)],
  ]);
}

/* ----------------------------- ZBIERZ ---------------------------- */
if ($a === 'zbierz') {
  $out = kc_tx(function() use ($gid) {
    $r = kc_row('SELECT * FROM kasyno_solo WHERE id=? AND gracz_id=? AND stan="double" FOR UPDATE',
      [kc_int('runda_id'), $gid], 'ii');
    if (!$r) kc_blad('Nie ma czego zbierać.', 409);
    $wyplata = (int)$r['wyplata'];
    if ($wyplata > 0) kc_kasa($gid, 0, $wyplata, 'wyplata_videopoker', 'solo', (int)$r['id']);
    kc_q('UPDATE kasyno_solo SET stan="zakonczona" WHERE id=?', [(int)$r['id']], 'i');
    $r2 = kc_row('SELECT * FROM kasyno_solo WHERE id=?', [(int)$r['id']], 'i');
    vp_zamknij_runde($r2, $gid);
    return $r2;
  });
  kc_ok(['runda'=>vp_widok($out), 'portfel'=>vp_portfel($gid), 'zebrano'=>(int)$out['wyplata']]);
}

kc_blad('Nieznana akcja.', 404);
