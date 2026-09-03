<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB ODZNAKI (Faza 7)
// 
// GET  ?op=lista              — wszystkie odznaki z progresem gracza
// GET  ?op=moje               — tylko zdobyte przez gracza
// GET  ?op=gracz id=X         — odznaki danego gracza (publiczne)
// POST op=sprawdz             — manualne sprawdzenie nowych odznak
// ════════════════════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['zalogowany']) || $_SESSION['zalogowany'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Nie zalogowano']);
    exit;
}

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/klub_odznaki_helper.php";
header('Content-Type: application/json; charset=utf-8');

$id_gracza = (int)$_SESSION['id_gracza'];
$op = $_REQUEST['op'] ?? 'lista';

// ═══════════════════════════════════════════════════════════════════
// LISTA — wszystkie odznaki z progresem
// ═══════════════════════════════════════════════════════════════════
if ($op === 'lista') {
    $defs = $polaczenie->query("SELECT * FROM klub_odznaki WHERE aktywna=1 ORDER BY kategoria ASC, warunek_prog ASC");

    // Pobierz przyznane gracza
    $przyznane = [];
    $pq = $polaczenie->query("SELECT odznaka_id, zdobyto_o FROM klub_gracz_odznaki WHERE gracz_id=$id_gracza");
    if ($pq) while ($r = $pq->fetch_assoc()) {
        $przyznane[(int)$r['odznaka_id']] = $r['zdobyto_o'];
    }

    $odznaki = [];
    if ($defs) while ($d = $defs->fetch_assoc()) {
        $oid = (int)$d['id'];
        $progres = klub_oblicz_progres_odznaki($polaczenie, $id_gracza, $d['warunek_typ']);
        $prog = (int)$d['warunek_prog'];
        $zdobyta = isset($przyznane[$oid]);

        $odznaki[] = [
            'id' => $oid,
            'slug' => $d['slug'],
            'nazwa' => $d['nazwa'],
            'opis' => $d['opis'],
            'ikona' => $d['ikona_emoji'],
            'kategoria' => $d['kategoria'],
            'rzadkosc' => $d['rzadkosc'],
            'prog' => $prog,
            'progres' => min($progres, $prog),
            'progres_real' => $progres,
            'procent' => $prog > 0 ? min(100, (int)round(100 * $progres / $prog)) : 0,
            'zdobyta' => $zdobyta,
            'zdobyto_o' => $zdobyta ? date('d.m.Y H:i', strtotime($przyznane[$oid])) : null,
        ];
    }
    echo json_encode(['ok' => true, 'odznaki' => $odznaki]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// MOJE — tylko zdobyte
// ═══════════════════════════════════════════════════════════════════
if ($op === 'moje') {
    $q = $polaczenie->query("
        SELECT o.*, g.zdobyto_o
        FROM klub_gracz_odznaki g
        INNER JOIN klub_odznaki o ON o.id = g.odznaka_id
        WHERE g.gracz_id=$id_gracza AND o.aktywna=1
        ORDER BY g.zdobyto_o DESC
    ");
    $moje = [];
    if ($q) while ($r = $q->fetch_assoc()) {
        $moje[] = [
            'id' => (int)$r['id'],
            'slug' => $r['slug'],
            'nazwa' => $r['nazwa'],
            'opis' => $r['opis'],
            'ikona' => $r['ikona_emoji'],
            'kategoria' => $r['kategoria'],
            'rzadkosc' => $r['rzadkosc'],
            'zdobyto_o' => date('d.m.Y H:i', strtotime($r['zdobyto_o'])),
        ];
    }
    echo json_encode(['ok' => true, 'moje' => $moje]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// GRACZ X — publiczna lista odznak gracza
// ═══════════════════════════════════════════════════════════════════
if ($op === 'gracz') {
    $gid = (int)($_REQUEST['id'] ?? 0);
    if ($gid <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Brak ID gracza']);
        exit;
    }
    $q = $polaczenie->query("
        SELECT o.slug, o.nazwa, o.ikona_emoji, o.rzadkosc, g.zdobyto_o
        FROM klub_gracz_odznaki g
        INNER JOIN klub_odznaki o ON o.id = g.odznaka_id
        WHERE g.gracz_id=$gid AND o.aktywna=1
        ORDER BY g.zdobyto_o DESC
    ");
    $odznaki = [];
    if ($q) while ($r = $q->fetch_assoc()) {
        $odznaki[] = [
            'slug' => $r['slug'],
            'nazwa' => $r['nazwa'],
            'ikona' => $r['ikona_emoji'],
            'rzadkosc' => $r['rzadkosc'],
            'zdobyto_o' => date('d.m.Y', strtotime($r['zdobyto_o'])),
        ];
    }
    echo json_encode(['ok' => true, 'odznaki' => $odznaki]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// SPRAWDZ — przyznaj nowe jeśli warunki spełnione
// ═══════════════════════════════════════════════════════════════════
if ($op === 'sprawdz') {
    $nowe = klub_sprawdz_odznaki($polaczenie, $id_gracza);
    echo json_encode(['ok' => true, 'nowe' => $nowe]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);