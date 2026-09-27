<?php
/* ═══════════════════════════════════════════════════════════════════════
   API — UNIWERSYTET: MEMORY
   POST i=indeks karty → serwer odkrywa treść i pilnuje par.
   Przeglądarka nie zna kart z góry, więc nie da się podejrzeć rozwiązania.
   Stan w $_SESSION['uni_lam']['d'] (ustawia pages/uniwersytet.php).
   ═══════════════════════════════════════════════════════════════════════ */
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['zalogowany'])) { http_response_code(401); echo json_encode(['ok' => false, 'msg' => 'Nie zalogowano']); exit; }
require_once __DIR__ . '/../includes/csrf.php';
if (($_SESSION['uni_lam']['d']['typ'] ?? '') !== 'memory') { echo json_encode(['ok' => false, 'msg' => 'Brak aktywnej łamigłówki']); exit; }

$d = &$_SESSION['uni_lam']['d'];
$d['ok_idx'] = $d['ok_idx'] ?? [];
$i = (int)($_POST['i'] ?? -1);
if ($i < 0 || $i >= count($d['karty']) || in_array($i, $d['ok_idx'], true) || $i === $d['otwarta']) { echo json_encode(['ok' => false]); exit; }

$out = ['ok' => true, 'i' => $i, 't' => $d['karty'][$i][1]];
if ($d['otwarta'] === null) {
    $d['otwarta'] = $i;
} else {
    $j = $d['otwarta']; $d['otwarta'] = null; $d['ruchy']++;
    $out['j'] = $j;
    $out['para'] = $d['karty'][$i][0] === $d['karty'][$j][0];
    if ($out['para']) { $d['znalezione'][] = $d['karty'][$i][0]; $d['ok_idx'][] = $i; $d['ok_idx'][] = $j; }
}
$out['ruchy'] = $d['ruchy'];
$out['koniec'] = count($d['znalezione']) * 2 === count($d['karty']);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
