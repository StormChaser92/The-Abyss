<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB: „Polej graczowi" (drink za wpis)
// POST {wpis_id}  →  { ok, ile, msg? }
// Jeden drink od osoby na wpis. Nie można polać sobie, NPC ani systemowi.
// ════════════════════════════════════════════════════════════════════
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['zalogowany']) || $_SESSION['zalogowany'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Nie zalogowano']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Tylko POST']);
    exit;
}

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../includes/bezpieczne.php";

$id_gracza = (int)$_SESSION['id_gracza'];
$wpis_id   = (int)($_POST['wpis_id'] ?? 0);

$w = db_wiersz($polaczenie, "SELECT id, id_gracza, typ, usunieta, polewki FROM czat WHERE id = ?", [$wpis_id]);
if (!$w || (int)$w['usunieta'] === 1 || $w['typ'] !== 'wiadomosc' || (int)$w['id_gracza'] <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Temu wpisowi nie da się polać']);
    exit;
}
$autor = (int)$w['id_gracza'];
if ($autor === $id_gracza) {
    echo json_encode(['ok' => false, 'msg' => 'Sobie nie polewasz — niech inni docenią']);
    exit;
}

// UNIQUE(wpis_id, od_gracza_id) pilnuje „jeden drink od osoby" nawet przy podwójnym kliknięciu
$nowa = db_zmien($polaczenie,
    "INSERT IGNORE INTO klub_polewki (wpis_id, od_gracza_id, do_gracza_id) VALUES (?, ?, ?)",
    [$wpis_id, $id_gracza, $autor]) === 1;

if ($nowa) {
    db_q($polaczenie, "UPDATE czat SET polewki = polewki + 1 WHERE id = ?", [$wpis_id]);
    db_q($polaczenie, "UPDATE gracze SET klub_polewki_otrzymane = klub_polewki_otrzymane + 1 WHERE id = ?", [$autor]);
}

$ile = (int)(db_wiersz($polaczenie, "SELECT polewki FROM czat WHERE id = ?", [$wpis_id])['polewki'] ?? 0);
echo json_encode(['ok' => true, 'ile' => $ile, 'nowa' => $nowa,
                  'msg' => $nowa ? null : 'Już postawiłeś drinka za ten wpis']);
