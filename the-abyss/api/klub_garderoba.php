<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB GARDEROBA (Faza 7)
// 
// POST op=log_stroj  stroj_nazwa=X — loguje użycie stroju
// 
// Wykorzystywane przez kliknięcie na strój w pages/klub/garderoba.php.
// Pierwsze użycie zapisuje (UNIQUE), kolejne ignoruje. Gdy gracz osiąga
// 5 różnych — przyznawana odznaka Modny.
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
$op = $_POST['op'] ?? '';

if ($op === 'log_stroj') {
    $stroj = trim($_POST['stroj_nazwa'] ?? '');
    if ($stroj === '') {
        echo json_encode(['ok' => false, 'msg' => 'Brak nazwy stroju']);
        exit;
    }
    klub_log_stroj($polaczenie, $id_gracza, $stroj);
    $nowe = klub_sprawdz_odznaki($polaczenie, $id_gracza);
    echo json_encode(['ok' => true, 'nowe_odznaki' => $nowe]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);