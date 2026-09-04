<?php
/* the-abyss/api/kasyno_ruletka.php
   Ruletka europejska — jedno zero, RTP 97,297%.

   Serwer losuje liczbę przez random_int, rozstrzyga wszystkie zakłady i
   wypłaca. Klient dostaje gotowy numer i animuje koło tak, żeby kulka
   zatrzymała się właśnie tam. Losowanie nigdy nie zależy od animacji.

   Akcje (?a=):
     tabela                     definicje zakładów, limity, koło, nominały
     spin   {zaklady, klucz}    zaklady = {"n17":100,"red":500}
     historia                   ostatnie liczby + gorące numery
*/
declare(strict_types=1);
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/kasyno_core.php';
require_once __DIR__.'/../includes/ruletka_stol.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$polaczenie->set_charset('utf8mb4');

$a = (string)kc_in('a', 'tabela');

if ($a === 'tabela') {
  $z = [];
  foreach (rl_zaklady() as $k => $v) $z[$k] = [$v['numery'], $v['wyplata']];
  kc_ok([
    'zaklady'   => $z,
    'kolo'      => RL_KOLO,
    'czerwone'  => RL_CZERWONE,
    'nominaly'  => RL_NOMINALY,
    'limity'    => ['min'=>RL_MIN_ZAKLAD, 'max_numer'=>RL_MAX_NUMER,
                    'max_zewn'=>RL_MAX_ZEWN, 'max_suma'=>RL_MAX_SUMA],
    'rtp'       => 97.297,
  ]);
}

$gid = kc_gracz_id();

if ($a === 'historia') kc_ok(rl_historia($gid));

/* -------------------------------- SPIN -------------------------------- */
if ($a === 'spin') {
  kc_sprawdz_prog($gid);
  $wejscie = kc_in('zaklady');
  if (!is_array($wejscie) || !$wejscie) kc_blad('Nie postawiłeś żadnego zakładu.');

  $def = rl_zaklady();
  $zaklady = []; $suma = 0;
  foreach ($wejscie as $klucz => $kwota) {
    if (!isset($def[$klucz]))  kc_blad('Nieznany zakład: '.$klucz);
    $kwota = (int)$kwota;
    if ($kwota <= 0)           kc_blad('Zakład musi być dodatni.');
    if ($kwota % RL_MIN_ZAKLAD !== 0) kc_blad('Zakłady tylko w wielokrotnościach '.RL_MIN_ZAKLAD.'.');
    $limit = rl_limit($def[$klucz]['wyplata']);
    if ($kwota > $limit)
      kc_blad('Limit na to pole: '.number_format($limit, 0, '', ' ').' żetonów.');
    $zaklady[$klucz] = $kwota;
    $suma += $kwota;
  }
  if ($suma > RL_MAX_SUMA)
    kc_blad('Maksymalnie '.number_format(RL_MAX_SUMA, 0, '', ' ').' żetonów na jeden spin.');

  $out = kc_tx(function() use ($gid, $zaklady, $suma, $def) {
    kc_kasa($gid, 0, -$suma, 'zaklad_ruletka');

    $numer = random_int(0, 36);
    $kolor = rl_kolor($numer);

    $wyplata = 0; $trafione = [];
    foreach ($zaklady as $klucz => $kwota) {
      if (!in_array($numer, $def[$klucz]['numery'], true)) continue;
      $w = $kwota * ($def[$klucz]['wyplata'] + 1);   // zwrot stawki + wygrana
      $wyplata += $w;
      $trafione[] = ['klucz'=>$klucz, 'etykieta'=>rl_etykieta($klucz),
                     'stawka'=>$kwota, 'wygrana'=>$w];
    }

    $klucz = (string)kc_in('klucz', '');
    kc_q('INSERT INTO kasyno_solo (gracz_id,gra,stawka,zetony_stawka,wyplata,stan,zaklady,wynik_nr,uklad,klucz_zadania)
          VALUES (?,"ruletka",?,1,?,"zakonczona",?,?,?,?)',
      [$gid, $suma, $wyplata, json_encode($zaklady, JSON_UNESCAPED_UNICODE), $numer,
       $numer.' '.$kolor, $klucz !== '' ? substr($klucz, 0, 64) : null], 'iiisiss');
    global $polaczenie;
    $rid = (int)$polaczenie->insert_id;

    if ($wyplata > 0) kc_kasa($gid, 0, $wyplata, 'wyplata_ruletka', 'solo', $rid);

    kc_q('INSERT INTO kasyno_ruletka_historia (gracz_id,numer,kolor,stawka,wyplata)
          VALUES (?,?,?,?,?)', [$gid, $numer, $kolor, $suma, $wyplata], 'iisii');
    kc_q('INSERT INTO kasyno_solo_udzial (gracz_id,gra,stawka,wyplata) VALUES (?,"ruletka",?,?)',
      [$gid, $suma, $wyplata], 'iii');
    kc_zapisz_wynik($gid, $suma, $wyplata, 'ruletka');

    $g = kc_row('SELECT gotowka, zetony FROM gracze WHERE id=?', [$gid], 'i');
    return [
      'runda_id' => $rid,
      'numer'    => $numer,
      'kolor'    => $kolor,
      'stawka'   => $suma,
      'wyplata'  => $wyplata,
      'netto'    => $wyplata - $suma,
      'trafione' => $trafione,
      'portfel'  => ['gotowka'=>(int)$g['gotowka'], 'zetony'=>(int)$g['zetony']],
    ];
  });

  kc_ok($out + rl_historia($gid));
}

kc_blad('Nieznana akcja.', 404);

/** Ostatnie liczby tego gracza i gorące numery z całego serwera (doba). */
function rl_historia(int $gid): array {
  return [
    'ostatnie' => kc_all('SELECT numer, kolor FROM kasyno_ruletka_historia
                          WHERE gracz_id=? ORDER BY id DESC LIMIT 18', [$gid], 'i'),
    'gorace'   => kc_all('SELECT numer, COUNT(*) ile FROM kasyno_ruletka_historia
                          WHERE czas >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                          GROUP BY numer ORDER BY ile DESC, numer LIMIT 5'),
  ];
}
