<?php
/* the-abyss/api/kasyno_sloty.php
   Sloty „Złoty Smok" — 5 bębnów, 3 rzędy, 10 linii.

   RTP 89,8%, policzony przez pełne wyliczenie 7 962 624 kombinacji
   pozycji bębnów: 85,24% linie + 3,59% scatter + ~1% jackpot.
   Trafienie w 50,1% spinów.

   Bębny mają PASKI (osobne dla każdego bębna), a nie niezależne
   losowanie symbolu na pozycję. To różnica, która decyduje o RTP:
   stare sloty ważyły każdy symbol osobno i wychodziło z tego 24%.

   Serwer losuje pozycję każdego bębna, ocenia linie i wypłaca.
   Klient dostaje gotową siatkę i tylko animuje obrót.

   Akcje (?a=): tabela | spin {linia, klucz} | jackpot | trafienia
*/
declare(strict_types=1);
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/kasyno_core.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$polaczenie->set_charset('utf8mb4');

/* ---------------------------- KONFIGURACJA ----------------------------
   Symbole jako indeksy — paski są tablicami liczb, więc ocena linii jest
   tanim porównaniem int, a nie stringów wielobajtowych.               */
const SL_SYMBOLE = ['🍒','🍋','🔔','💎','7️⃣','🐉','⚡','🌙'];
const SL_WILD = 6, SL_SCAT = 7, SL_SMOK = 5;

/* Paski bębnów — 24 pozycje każdy, celowo różne.
   Smok i scatter raz na pasek, wild raz — to one ustalają rzadkość. */
const SL_PASKI = [
  [0,1,2,0,3,1,6,0,4,2,1,7,0,3,1,2,0,5,1,2,0,3,1,2],
  [1,0,3,2,0,6,1,2,0,4,7,1,3,0,2,1,0,2,5,3,1,0,2,1],
  [2,0,1,3,0,2,6,1,7,0,3,2,4,1,0,2,3,1,0,5,2,1,0,3],
  [0,2,1,0,3,2,6,0,1,4,2,7,3,0,1,2,0,3,1,5,2,0,1,2],
  [1,3,0,2,6,1,0,7,2,1,4,0,3,2,1,0,2,1,0,5,3,2,1,0],
];

/* Wypłaty za 3, 4 i 5 symboli — krotność stawki na linię. */
const SL_WYPLATY = [
  5 => [25, 150, 750],   // 🐉 smok
  4 => [15, 75,  350],   // 7️⃣
  3 => [10, 40,  200],   // 💎
  2 => [7,  25,  110],   // 🔔
  1 => [4,  16,  70],    // 🍋
  0 => [3,  10,  40],    // 🍒
];
const SL_SCATTER = [3 => 2, 4 => 5, 5 => 20];   // × całkowita stawka

/* Dziesięć linii jako wiersze (0 góra, 1 środek, 2 dół) na kolejnych bębnach. */
const SL_LINIE = [
  [1,1,1,1,1], [0,0,0,0,0], [2,2,2,2,2], [0,1,2,1,0], [2,1,0,1,2],
  [0,0,1,2,2], [2,2,1,0,0], [1,0,0,0,1], [1,2,2,2,1], [0,1,1,1,2],
];

const SL_STAWKI = [10, 25, 50, 100, 250];   // stawka na linię
const SL_JACKPOT_SMOKI = 5;                 // ile smoków zabiera pulę

function sl_pula(): array {
  $j = kc_row('SELECT pula, pula_start, trafien FROM kasyno_jackpot WHERE id=1');
  return $j ?: ['pula'=>0, 'pula_start'=>0, 'trafien'=>0];
}

$a = (string)kc_in('a', 'tabela');

if ($a === 'tabela') kc_ok([
  'symbole'  => SL_SYMBOLE,
  'wyplaty'  => SL_WYPLATY,
  'scatter'  => SL_SCATTER,
  'linie'    => SL_LINIE,
  'stawki'   => SL_STAWKI,
  'wild'     => SL_WILD, 'scat' => SL_SCAT, 'smok' => SL_SMOK,
  'jackpot'  => sl_pula(),
  'rtp'      => 89.8,
]);

if ($a === 'jackpot') kc_ok(['jackpot'=>sl_pula()]);

if ($a === 'trafienia') kc_ok(['rows'=>kc_all(
  'SELECT g.login, l.kwota, l.czas FROM kasyno_jackpot_log l
   JOIN gracze g ON g.id = l.gracz_id ORDER BY l.id DESC LIMIT 5')]);

$gid = kc_gracz_id();

/* -------------------------------- SPIN -------------------------------- */
if ($a === 'spin') {
  kc_sprawdz_prog($gid);
  $linia = kc_int('linia', SL_STAWKI[0]);
  if (!in_array($linia, SL_STAWKI, true)) kc_blad('Nieprawidłowa stawka na linię.');
  $stawka = $linia * count(SL_LINIE);

  $klucz = (string)kc_in('klucz', '');
  $klucz = $klucz !== '' ? substr($klucz, 0, 64) : null;
  if ($klucz !== null) {
    $stara = kc_row('SELECT * FROM kasyno_solo WHERE gracz_id=? AND klucz_zadania=?', [$gid, $klucz], 'is');
    if ($stara) kc_ok(sl_odpowiedz($stara, $gid, true));
  }

  $out = kc_tx(function() use ($gid, $linia, $stawka, $klucz) {
    kc_kasa($gid, 0, -$stawka, 'zaklad_sloty');

    // pozycja każdego bębna — random_int, nie mt_rand
    $poz = [];
    $siatka = [];
    foreach (SL_PASKI as $i => $pasek) {
      $n = count($pasek);
      $p = random_int(0, $n - 1);
      $poz[$i] = $p;
      $siatka[$i] = [$pasek[$p], $pasek[($p + 1) % $n], $pasek[($p + 2) % $n]];
    }

    // linie — liczone od lewej, wild zastępuje każdy symbol poza scatterem
    $wyplata = 0; $trafione = [];
    foreach (SL_LINIE as $li => $wiersze) {
      $sy = [];
      foreach ($wiersze as $r => $wiersz) $sy[$r] = $siatka[$r][$wiersz];

      $baza = -1;
      foreach ($sy as $s) { if ($s !== SL_WILD && $s !== SL_SCAT) { $baza = $s; break; } }
      if ($baza < 0) continue;

      $ile = 0;
      for ($r = 0; $r < 5; $r++) { if ($sy[$r] === $baza || $sy[$r] === SL_WILD) $ile++; else break; }
      if ($ile < 3 || !isset(SL_WYPLATY[$baza])) continue;

      $m = SL_WYPLATY[$baza][$ile - 3];
      if ($m <= 0) continue;
      $w = $linia * $m;
      $wyplata += $w;
      $trafione[] = ['linia'=>$li, 'symbol'=>$baza, 'ile'=>$ile, 'wygrana'=>$w];
    }

    // scatter — liczy się gdziekolwiek na bębnach, płaci od całej stawki
    $scat = 0; $smoki = 0;
    foreach ($siatka as $beben) foreach ($beben as $s) {
      if ($s === SL_SCAT) $scat++;
      if ($s === SL_SMOK) $smoki++;
    }
    $scatWyplata = $scat >= 3 ? $stawka * SL_SCATTER[min($scat, 5)] : 0;
    $wyplata += $scatWyplata;

    // jackpot: pięć smoków zabiera całą pulę
    $jackpot = 0;
    $j = kc_row('SELECT pula, pula_start, wklad_proc FROM kasyno_jackpot WHERE id=1 FOR UPDATE');
    if ($smoki >= SL_JACKPOT_SMOKI) {
      $jackpot = (int)$j['pula'];
      $wyplata += $jackpot;
      kc_q('UPDATE kasyno_jackpot SET pula=pula_start, zdobyl_id=?, zdobyl_kwote=?, zdobyl_czas=NOW(),
              trafien=trafien+1 WHERE id=1', [$gid, $jackpot], 'ii');
      kc_q('INSERT INTO kasyno_jackpot_log (gracz_id,kwota,stawka) VALUES (?,?,?)',
        [$gid, $jackpot, $stawka], 'iii');
    } else {
      kc_q('UPDATE kasyno_jackpot SET pula = pula + ? WHERE id=1',
        [(int)floor($stawka * (float)$j['wklad_proc'])], 'i');
    }

    $plaskaSiatka = [];
    foreach ($siatka as $beben) foreach ($beben as $s) $plaskaSiatka[] = $s;

    kc_q('INSERT INTO kasyno_solo (gracz_id,gra,stawka,zetony_stawka,wyplata,stan,siatka,linie,uklad,klucz_zadania)
          VALUES (?,"sloty",?,?,?,"zakonczona",?,?,?,?)',
      [$gid, $stawka, $linia, $wyplata, implode(',', $plaskaSiatka),
       json_encode(['trafione'=>$trafione, 'scat'=>$scat, 'scat_wyplata'=>$scatWyplata,
                    'jackpot'=>$jackpot, 'smoki'=>$smoki], JSON_UNESCAPED_UNICODE),
       $jackpot > 0 ? 'JACKPOT' : ($scat >= 3 ? 'SCATTER ×'.$scat : ($trafione ? 'LINIE' : null)),
       $klucz], 'iiiissss');

    global $polaczenie;
    $rid = (int)$polaczenie->insert_id;
    if ($wyplata > 0) kc_kasa($gid, 0, $wyplata, 'wyplata_sloty', 'solo', $rid);

    kc_q('INSERT INTO kasyno_solo_udzial (gracz_id,gra,stawka,wyplata) VALUES (?,"sloty",?,?)',
      [$gid, $stawka, $wyplata], 'iii');
    kc_zapisz_wynik($gid, $stawka, $wyplata, 'sloty');

    return kc_row('SELECT * FROM kasyno_solo WHERE id=?', [$rid], 'i');
  });

  kc_ok(sl_odpowiedz($out, $gid, false));
}

kc_blad('Nieznana akcja.', 404);

function sl_odpowiedz(array $r, int $gid, bool $powtorka): array {
  $meta = json_decode((string)$r['linie'], true) ?: [];
  $plaska = $r['siatka'] === '' ? [] : array_map('intval', explode(',', $r['siatka']));
  $siatka = [];
  for ($i = 0; $i < 5; $i++) $siatka[$i] = array_slice($plaska, $i * 3, 3);

  $g = kc_row('SELECT gotowka, zetony FROM gracze WHERE id=?', [$gid], 'i') ?: [];
  return [
    'runda_id'     => (int)$r['id'],
    'stawka'       => (int)$r['stawka'],
    'linia'        => (int)$r['zetony_stawka'],
    'wyplata'      => (int)$r['wyplata'],
    'siatka'       => $siatka,
    'trafione'     => $meta['trafione'] ?? [],
    'scat'         => $meta['scat'] ?? 0,
    'scat_wyplata' => $meta['scat_wyplata'] ?? 0,
    'jackpot'      => $meta['jackpot'] ?? 0,
    'smoki'        => $meta['smoki'] ?? 0,
    'jackpot_pula' => (int)sl_pula()['pula'],
    'portfel'      => ['gotowka'=>(int)($g['gotowka'] ?? 0), 'zetony'=>(int)($g['zetony'] ?? 0)],
    'powtorka'     => $powtorka,
  ];
}
