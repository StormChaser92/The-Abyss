<?php
/* the-abyss/api/kasyno_turniej.php
   Turniej gangów — długie odpytywanie stanu areny, akcje na turze, czat.

   Akcje (?a=):
     stan   {turniej}            pełny stan: zawodnicy, log, czat, zegar
     akcja  {turniej,ruch,cel}   atak | unik | czekaj
     czat   {turniej,tresc}
     postaw {turniej,na,stawka}  zakład widza (żetony)
     zapisz {turniej}            zgłoszenie zawodnika, wpisowe ze skarbca
*/
declare(strict_types=1);
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/kasyno_core.php';
require_once __DIR__.'/../includes/turniej.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$polaczenie->set_charset('utf8mb4');

$a   = (string)kc_in('a', 'stan');
$gid = kc_gracz_id();
$tid = kc_int('turniej');

$t = tur_turniej($polaczenie, $tid);
if (!$t) kc_blad('Nie ma takiego turnieju.', 404);

/** Migawka stanu areny wysyłana klientowi. */
function tur_widok(mysqli $db, array $t, int $gid): array {
    tur_sprawdz_zegar($db, $t);
    $t   = tur_turniej($db, (int)$t['id']);
    $tid = (int)$t['id'];
    $zaw = tur_zawodnicy($db, $tid);

    $lista = array_map(fn($z) => [
        'gracz_id' => (int)$z['gracz_id'],
        'login'    => $z['login'],
        'gang'     => $z['gang'],
        'tag'      => $z['tag'],
        'hp'       => (int)$z['hp'],
        'hp_max'   => (int)$z['hp_max'],
        'stan'     => $z['stan'],
        'postawa'  => $z['postawa'],
        'zadane'   => (int)$z['zadane'],
        'miejsce'  => $z['miejsce'] !== null ? (int)$z['miejsce'] : null,
        'aktywny'  => (int)$t['aktywny_id'] === (int)$z['gracz_id'],
        'ja'       => (int)$z['gracz_id'] === $gid,
    ], $zaw);

    $log = kc_all("SELECT tura, akcja, tresc, dmg FROM turniej_log
                   WHERE turniej_id=$tid ORDER BY id DESC LIMIT 40", [], '');
    $czat = kc_all("SELECT c.tresc, c.czas, g.login FROM turniej_czat c
                    JOIN gracze g ON g.id=c.gracz_id
                    WHERE c.turniej_id=$tid ORDER BY c.id DESC LIMIT 30", [], '');

    $kursy = tur_kursy($db, $tid);
    $moj_zaklad = kc_row("SELECT na_kogo, stawka, wyplata, rozliczony FROM turniej_zaklady
                          WHERE turniej_id=$tid AND gracz_id=$gid", [], '');
    $zetony = (int)(kc_row('SELECT zetony FROM gracze WHERE id=?', [$gid], 'i')['zetony'] ?? 0);

    $jestem = false;
    foreach ($zaw as $z) if ((int)$z['gracz_id'] === $gid) $jestem = true;

    return [
        'turniej' => [
            'id'      => $tid,
            'nazwa'   => $t['nazwa'],
            'arena'   => $t['arena'],
            'kraj'    => $t['kraj'],
            'miasto'  => $t['miasto'],
            'stan'    => $t['stan'],
            'tura'    => (int)$t['tura'],
            'wpisowe' => (int)$t['wpisowe'],
            'pula'    => (int)$t['pula'],
            'poczatek'=> $t['poczatek'],
            'zwyciezca_id' => $t['zwyciezca_id'] !== null ? (int)$t['zwyciezca_id'] : null,
        ],
        'zawodnicy'  => $lista,
        'moja_tura'  => $t['stan'] === 'trwa' && (int)$t['aktywny_id'] === $gid,
        'sekundy'    => $t['ruch_do'] ? max(0, strtotime($t['ruch_do']) - time()) : 0,
        'jestem'     => $jestem,
        'log'        => array_reverse($log),
        'czat'       => array_reverse($czat),
        'zaklady'    => ['pula' => $kursy['pula'], 'na' => $kursy['na'], 'moj' => $moj_zaklad],
        'zetony'     => $zetony,
    ];
}

if ($a === 'stan') kc_ok(tur_widok($polaczenie, $t, $gid));

if ($a === 'akcja') {
    $ruch = (string)kc_in('ruch', 'unik');
    $cel  = kc_int('cel');
    if (!in_array($ruch, ['atak','unik','czekaj'], true)) kc_blad('Nieznany ruch.');
    [$ok, $tekst] = tur_akcja($polaczenie, $t, $gid, $ruch, $cel);
    if (!$ok) kc_blad($tekst);
    kc_ok(tur_widok($polaczenie, tur_turniej($polaczenie, $tid), $gid) + ['komunikat' => $tekst]);
}

if ($a === 'czat') {
    $tresc = trim((string)kc_in('tresc', ''));
    if ($tresc === '') kc_blad('Pusto.');
    $tresc = mb_substr($tresc, 0, 240);
    kc_q('INSERT INTO turniej_czat (turniej_id, gracz_id, tresc) VALUES (?,?,?)',
         [$tid, $gid, $tresc], 'iis');
    kc_ok(tur_widok($polaczenie, $t, $gid));
}

if ($a === 'postaw') {
    [$ok, $tekst] = tur_postaw($polaczenie, $t, $gid, kc_int('na'), kc_int('stawka'));
    if (!$ok) kc_blad($tekst);
    kc_ok(tur_widok($polaczenie, $t, $gid) + ['komunikat' => $tekst]);
}

if ($a === 'zapisz') {
    $g = kc_row('SELECT id, login, hp_max, syndykat_id FROM gracze WHERE id=?', [$gid], 'i');
    [$ok, $tekst] = tur_zapisz($polaczenie, $g, $t);
    if (!$ok) kc_blad($tekst);
    kc_ok(tur_widok($polaczenie, $t, $gid) + ['komunikat' => $tekst]);
}

kc_blad('Nieznana akcja.', 404);
