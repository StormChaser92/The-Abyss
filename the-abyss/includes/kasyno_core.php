<?php
/* the-abyss/includes/kasyno_core.php
   Wspólna warstwa kasyna: zapytania, pieniądze, transakcje.

   Powstało po Hold'em, żeby druga gra nie kopiowała kodu ruszającego
   gotówkę. `kc_kasa()` jest JEDYNĄ drogą do zmiany gotówki i żetonów —
   blokuje wiersz gracza, sprawdza wypłacalność, pisze do ledgera. */
declare(strict_types=1);

function kc_ok(array $d = []): never { echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function kc_blad(string $m, int $kod = 400): never {
  http_response_code($kod);
  echo json_encode(['ok'=>false,'blad'=>$m], JSON_UNESCAPED_UNICODE); exit;
}

function kc_wejscie(): array {
  static $b = null;
  if ($b !== null) return $b;
  $j = json_decode(file_get_contents('php://input') ?: '', true);
  return $b = is_array($j) ? $j : $_POST;
}
function kc_in(string $k, $def = null) { $w = kc_wejscie(); return $w[$k] ?? $_GET[$k] ?? $def; }
function kc_int(string $k, int $def = 0): int { $v = kc_in($k, $def); return is_numeric($v) ? (int)$v : $def; }

function kc_q(string $sql, array $p = [], string $typy = ''): mysqli_stmt {
  global $polaczenie;
  $st = $polaczenie->prepare($sql);
  if ($p) $st->bind_param($typy ?: str_repeat('s', count($p)), ...$p);
  $st->execute();
  return $st;
}
function kc_row(string $sql, array $p = [], string $typy = ''): ?array {
  $r = kc_q($sql, $p, $typy)->get_result()->fetch_assoc();
  return $r ?: null;
}
function kc_all(string $sql, array $p = [], string $typy = ''): array {
  return kc_q($sql, $p, $typy)->get_result()->fetch_all(MYSQLI_ASSOC);
}

function kc_tx(callable $fn) {
  global $polaczenie;
  $polaczenie->begin_transaction();
  try { $r = $fn(); $polaczenie->commit(); return $r; }
  catch (Throwable $e) { $polaczenie->rollback(); throw $e; }
}

function kc_gracz_id(): int {
  if (empty($_SESSION['id_gracza'])) kc_blad('Nie jesteś zalogowany.', 401);
  return (int)$_SESSION['id_gracza'];
}

/** Zmiana kasy. Wołać wyłącznie w kc_tx(). */
function kc_kasa(int $gid, int $dGotowka, int $dZetony, string $powod, ?string $refTyp = null, ?int $refId = null): array {
  $g = kc_row('SELECT gotowka, zetony FROM gracze WHERE id=? FOR UPDATE', [$gid], 'i');
  if (!$g) kc_blad('Nie ma takiego gracza.', 404);
  $gotowka = (int)$g['gotowka'] + $dGotowka;
  $zetony  = (int)$g['zetony']  + $dZetony;
  if ($gotowka < 0) kc_blad('Za mało gotówki.', 409);
  if ($zetony  < 0) kc_blad('Za mało żetonów.', 409);
  kc_q('UPDATE gracze SET gotowka=?, zetony=? WHERE id=?', [$gotowka, $zetony, $gid], 'iii');
  kc_q('INSERT INTO kasyno_ledger (gracz_id,delta_gotowka,delta_zetony,gotowka_po,zetony_po,powod,ref_typ,ref_id)
        VALUES (?,?,?,?,?,?,?,?)',
    [$gid,$dGotowka,$dZetony,$gotowka,$zetony,$powod,$refTyp,$refId], 'iiiiissi');
  return ['gotowka'=>$gotowka, 'zetony'=>$zetony];
}

/** Statystyki gier solo + reputacja + licznik do wady hazardzisty. */
function kc_zapisz_wynik(int $gid, int $stawka, int $wyplata, string $gra): void {
  $netto = $wyplata - $stawka;
  kc_q('UPDATE gracze SET kasyno_netto = kasyno_netto + ?, kasyno_rozdania = kasyno_rozdania + 1,
          kasyno_wygrana_max = GREATEST(kasyno_wygrana_max, ?), kasyno_ostatnia_gra = NOW()
        WHERE id=?', [$netto, $wyplata, $gid], 'iii');
  kc_q('INSERT INTO kasyno_solo_stat (gracz_id,gra,rozdania,obrot,wygrane,najwieksza)
        VALUES (?,?,1,?,?,?)
        ON DUPLICATE KEY UPDATE rozdania=rozdania+1, obrot=obrot+VALUES(obrot),
          wygrane=wygrane+VALUES(wygrane), najwieksza=GREATEST(najwieksza,VALUES(najwieksza))',
    [$gid,$gra,$stawka,$wyplata,$wyplata], 'isiii');
  kc_sprawdz_wade($gid);
}

/** Wada „Hazardzista": 200 rozdań w 7 dni. Mija po tygodniu bez gry. */
function kc_sprawdz_wade(int $gid): void {
  $g = kc_row('SELECT wady, kasyno_wada_od FROM gracze WHERE id=?', [$gid], 'i');
  if (!$g) return;

  // ma już wadę — zdejmowanie obsługuje kc_zdejmij_wade() przy wejściu na stronę
  if ($g['kasyno_wada_od'] !== null) return;
  $ileHoldem = (int)(kc_row('SELECT COUNT(*) n FROM kasyno_udzial WHERE gracz_id=? AND czas >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [$gid], 'i')['n'] ?? 0);
  $ileSolo   = (int)(kc_row('SELECT COUNT(*) n FROM kasyno_solo_udzial WHERE gracz_id=? AND czas >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [$gid], 'i')['n'] ?? 0);
  if ($ileHoldem + $ileSolo < 200) return;

  $wady = trim((string)$g['wady']);
  if (stripos($wady, 'Hazardzista') === false) $wady = $wady === '' ? 'Hazardzista' : $wady.', Hazardzista';
  kc_q('UPDATE gracze SET wady=?, kasyno_wada_od=NOW() WHERE id=?', [$wady, $gid], 'si');
}

/** Tydzień bez gry — wada schodzi. Wołane przy wejściu na stronę kasyna. */
function kc_zdejmij_wade(int $gid): void {
  $g = kc_row('SELECT wady, kasyno_wada_od, kasyno_ostatnia_gra FROM gracze WHERE id=?', [$gid], 'i');
  if (!$g || $g['kasyno_wada_od'] === null) return;
  if ($g['kasyno_ostatnia_gra'] !== null && strtotime($g['kasyno_ostatnia_gra']) > time() - 7 * 86400) return;

  $wady = preg_replace('/\s*,?\s*Hazardzista/iu', '', (string)$g['wady']);
  $wady = trim(trim((string)$wady), ',');
  kc_q('UPDATE gracze SET wady=?, kasyno_wada_od=NULL WHERE id=?', [$wady, $gid], 'si');
}

/** Próg majątkowy wejścia do kasyna — wspólny dla wszystkich gier. */
function kc_sprawdz_prog(int $gid): void {
  $g = kc_row('SELECT gotowka, bank FROM gracze WHERE id=?', [$gid], 'i');
  $prog = (int)(kc_row('SELECT MIN(prog_majatku) p FROM kasyno_stoly')['p'] ?? 50000);
  if ((int)$g['gotowka'] + (int)$g['bank'] < $prog)
    kc_blad('Ochrona nie wpuszcza cię do sali gier. Wymagany majątek: '.number_format($prog, 0, '', ' ').' $.', 403);
}
