<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB SIŁOWNIA (Faza 7)
// 
// POST op=zajmij   sprzet_id=X — zajmij stanowisko (max 20 min)
// POST op=zwolnij  sprzet_id=X — zwolnij swoje stanowisko
// 
// Stanowisko zajmuje 1 gracza naraz. Auto-cleanup po 20 min.
// Każde zajęcie startuje "sesję sportową" (do odznaki Żelazny).
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
$gracz = $polaczenie->query("SELECT id, login FROM gracze WHERE id=$id_gracza")->fetch_assoc();
if (!$gracz) {
    echo json_encode(['ok' => false, 'msg' => 'Brak danych gracza']);
    exit;
}

$polaczenie->query("UPDATE klub_silownia_sprzet SET zajety_przez_id=NULL, zajety_od=NULL, do_kiedy=NULL WHERE (do_kiedy IS NOT NULL AND do_kiedy <= NOW()) OR (zajety_od IS NOT NULL AND zajety_od <= NOW() - INTERVAL 20 MINUTE)");

$op = $_POST['op'] ?? '';

if ($op === 'zajmij') {
    $sprzet_id = (int)($_POST['sprzet_id'] ?? 0);
    $sprzet = $polaczenie->query("SELECT * FROM klub_silownia_sprzet WHERE id=$sprzet_id")->fetch_assoc();
    if (!$sprzet) {
        echo json_encode(['ok' => false, 'msg' => 'Stanowisko nie istnieje']);
        exit;
    }
    if (!empty($sprzet['zajety_przez_id']) && (int)$sprzet['zajety_przez_id'] !== $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'Stanowisko jest już zajęte']);
        exit;
    }

    // Zwolnij inne stanowiska tego gracza
    $polaczenie->query("UPDATE klub_silownia_sprzet SET zajety_przez_id=NULL, zajety_od=NULL, do_kiedy=NULL WHERE zajety_przez_id=$id_gracza AND id != $sprzet_id");

    $polaczenie->query("UPDATE klub_silownia_sprzet SET zajety_przez_id=$id_gracza, zajety_od=NOW(), do_kiedy=NOW() + INTERVAL 20 MINUTE WHERE id=$sprzet_id");

    klub_start_sesji_sportowej($polaczenie, $id_gracza, 'silownia');

    $sys = "💪 <b>{$gracz['login']}</b> zajmuje stanowisko: <b>" . htmlspecialchars($sprzet['nazwa']) . "</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'silownia', 'system')");

    $nowe = klub_sprawdz_odznaki($polaczenie, $id_gracza);

    echo json_encode([
        'ok' => true,
        'msg' => '✓ Stanowisko zajęte na 20 minut',
        'nowe_odznaki' => $nowe,
    ]);
    exit;
}

if ($op === 'zwolnij') {
    $sprzet_id = (int)($_POST['sprzet_id'] ?? 0);
    $sprzet = $polaczenie->query("SELECT * FROM klub_silownia_sprzet WHERE id=$sprzet_id")->fetch_assoc();
    if (!$sprzet) {
        echo json_encode(['ok' => false, 'msg' => 'Stanowisko nie istnieje']);
        exit;
    }
    if ((int)$sprzet['zajety_przez_id'] !== $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'To nie Twoje stanowisko']);
        exit;
    }
    $polaczenie->query("UPDATE klub_silownia_sprzet SET zajety_przez_id=NULL, zajety_od=NULL, do_kiedy=NULL WHERE id=$sprzet_id");

    klub_koniec_sesji_sportowej($polaczenie, $id_gracza, 'silownia');

    $sys = "💧 <b>{$gracz['login']}</b> kończy ze stanowiskiem: <b>" . htmlspecialchars($sprzet['nazwa']) . "</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'silownia', 'system')");

    echo json_encode(['ok' => true, 'msg' => '✓ Stanowisko zwolnione']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);