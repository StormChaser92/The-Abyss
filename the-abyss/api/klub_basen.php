<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB BASEN (Faza 7)
// 
// POST op=zajmij   tor_id=X   — zajmij tor (max 30 min)
// POST op=zwolnij  tor_id=X   — zwolnij swój tor
// 
// Tor zajmuje 1 gracza naraz. Auto-cleanup po 30 min bez aktywności.
// Każde zajęcie startuje "sesję sportową" (do odznaki Pływak).
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

// Cleanup wygasłych torów
$polaczenie->query("UPDATE klub_basen_tory SET zajety_przez_id=NULL, zajety_od=NULL, do_kiedy=NULL WHERE (do_kiedy IS NOT NULL AND do_kiedy <= NOW()) OR (zajety_od IS NOT NULL AND zajety_od <= NOW() - INTERVAL 30 MINUTE)");

$op = $_POST['op'] ?? '';

// ═══════════════════════════════════════════════════════════════════
// ZAJMIJ TOR
// ═══════════════════════════════════════════════════════════════════
if ($op === 'zajmij') {
    $tor_id = (int)($_POST['tor_id'] ?? 0);
    $tor = $polaczenie->query("SELECT * FROM klub_basen_tory WHERE id=$tor_id")->fetch_assoc();
    if (!$tor) {
        echo json_encode(['ok' => false, 'msg' => 'Tor nie istnieje']);
        exit;
    }
    if (!empty($tor['zajety_przez_id']) && (int)$tor['zajety_przez_id'] !== $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'Tor jest już zajęty']);
        exit;
    }

    // Zwolnij inne tory tego gracza
    $polaczenie->query("UPDATE klub_basen_tory SET zajety_przez_id=NULL, zajety_od=NULL, do_kiedy=NULL WHERE zajety_przez_id=$id_gracza AND id != $tor_id");

    // Zajmij ten
    $polaczenie->query("UPDATE klub_basen_tory SET zajety_przez_id=$id_gracza, zajety_od=NOW(), do_kiedy=NOW() + INTERVAL 30 MINUTE WHERE id=$tor_id");

    // Sesja sportowa — start (do odznaki Pływak)
    klub_start_sesji_sportowej($polaczenie, $id_gracza, 'basen');

    // System message
    $sys = "🏊 <b>{$gracz['login']}</b> zajmuje <b>" . htmlspecialchars($tor['nazwa']) . "</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'basen', 'system')");

    // Lazy check odznak
    $nowe = klub_sprawdz_odznaki($polaczenie, $id_gracza);

    echo json_encode([
        'ok' => true,
        'msg' => '✓ Tor zajęty na 30 minut',
        'nowe_odznaki' => $nowe,
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ZWOLNIJ TOR
// ═══════════════════════════════════════════════════════════════════
if ($op === 'zwolnij') {
    $tor_id = (int)($_POST['tor_id'] ?? 0);
    $tor = $polaczenie->query("SELECT * FROM klub_basen_tory WHERE id=$tor_id")->fetch_assoc();
    if (!$tor) {
        echo json_encode(['ok' => false, 'msg' => 'Tor nie istnieje']);
        exit;
    }
    if ((int)$tor['zajety_przez_id'] !== $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'To nie Twój tor']);
        exit;
    }
    $polaczenie->query("UPDATE klub_basen_tory SET zajety_przez_id=NULL, zajety_od=NULL, do_kiedy=NULL WHERE id=$tor_id");

    // Zakończ sesję
    klub_koniec_sesji_sportowej($polaczenie, $id_gracza, 'basen');

    $sys = "🚿 <b>{$gracz['login']}</b> zwalnia <b>" . htmlspecialchars($tor['nazwa']) . "</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'basen', 'system')");

    echo json_encode(['ok' => true, 'msg' => '✓ Tor zwolniony']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);