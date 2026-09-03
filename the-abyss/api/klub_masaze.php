<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB MASAŻE (Faza 7)
// 
// POST op=zamow    zabieg_id=X lozko_id=Y — pobiera kasę, zajmuje łóżko
// POST op=anuluj   lozko_id=Y              — anuluj zabieg (bez zwrotu)
// 
// Cena pobierana natychmiast z gotówki gracza (nie idzie na rachunek).
// Łóżko zajęte na czas zabiegu (z klub_masaze_zabiegi.czas_min).
// Każdy zabieg zapisany w klub_zabiegi_historia (do odznaki).
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
$gracz = $polaczenie->query("SELECT id, login, gotowka FROM gracze WHERE id=$id_gracza")->fetch_assoc();
if (!$gracz) {
    echo json_encode(['ok' => false, 'msg' => 'Brak danych gracza']);
    exit;
}

// Cleanup zakończonych zabiegów
$polaczenie->query("UPDATE klub_masaze_lozka SET klient_id=NULL, zabieg_id=NULL, do_kiedy=NULL WHERE do_kiedy IS NOT NULL AND do_kiedy <= NOW()");

$op = $_POST['op'] ?? '';

// ═══════════════════════════════════════════════════════════════════
// ZAMÓW ZABIEG
// ═══════════════════════════════════════════════════════════════════
if ($op === 'zamow') {
    $zabieg_id = (int)($_POST['zabieg_id'] ?? 0);
    $lozko_id = (int)($_POST['lozko_id'] ?? 0);

    $zabieg = $polaczenie->query("SELECT * FROM klub_masaze_zabiegi WHERE id=$zabieg_id AND aktywny=1")->fetch_assoc();
    if (!$zabieg) {
        echo json_encode(['ok' => false, 'msg' => 'Zabieg nie istnieje lub nieaktywny']);
        exit;
    }
    $lozko = $polaczenie->query("SELECT * FROM klub_masaze_lozka WHERE id=$lozko_id")->fetch_assoc();
    if (!$lozko) {
        echo json_encode(['ok' => false, 'msg' => 'Łóżko nie istnieje']);
        exit;
    }
    if (!empty($lozko['klient_id'])) {
        echo json_encode(['ok' => false, 'msg' => 'Łóżko jest zajęte']);
        exit;
    }

    $cena = (int)$zabieg['cena'];
    $czas_min = (int)$zabieg['czas_min'];

    if ((int)$gracz['gotowka'] < $cena) {
        echo json_encode([
            'ok' => false,
            'msg' => "Brak gotówki — masz " . number_format((int)$gracz['gotowka'], 0, '', ' ') . " \$, potrzeba $cena \$"
        ]);
        exit;
    }

    // Sprawdź czy gracz nie ma już rozpoczętego zabiegu
    $istn = $polaczenie->query("SELECT id FROM klub_masaze_lozka WHERE klient_id=$id_gracza")->fetch_assoc();
    if ($istn) {
        echo json_encode(['ok' => false, 'msg' => 'Masz już rozpoczęty zabieg na innym łóżku']);
        exit;
    }

    // Pobierz kasę
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena WHERE id=$id_gracza");
    // Zajmij łóżko
    $polaczenie->query("UPDATE klub_masaze_lozka SET klient_id=$id_gracza, zabieg_id=$zabieg_id, do_kiedy=NOW() + INTERVAL $czas_min MINUTE WHERE id=$lozko_id");
    // Historia
    $z_nazwa_e = $polaczenie->real_escape_string($zabieg['nazwa']);
    $polaczenie->query("INSERT INTO klub_zabiegi_historia (gracz_id, zabieg_id, zabieg_nazwa, cena_zaplacona) VALUES ($id_gracza, $zabieg_id, '$z_nazwa_e', $cena)");

    // System message
    $sys = "💆 <b>{$gracz['login']}</b> rozpoczyna zabieg <b>{$zabieg['ikona_emoji']} " . htmlspecialchars($zabieg['nazwa']) . "</b> na <b>" . htmlspecialchars($lozko['nazwa']) . "</b> — $cena \$ ($czas_min min).";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'masaze', 'system')");

    // Sprawdź odznaki
    $nowe = klub_sprawdz_odznaki($polaczenie, $id_gracza);

    echo json_encode([
        'ok' => true,
        'msg' => "✓ Zabieg rozpoczęty ($czas_min min, $cena \$)",
        'do_kiedy' => date('H:i', strtotime("+$czas_min minutes")),
        'nowa_gotowka' => (int)$gracz['gotowka'] - $cena,
        'nowe_odznaki' => $nowe,
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ANULUJ ZABIEG
// ═══════════════════════════════════════════════════════════════════
if ($op === 'anuluj') {
    $lozko_id = (int)($_POST['lozko_id'] ?? 0);
    $lozko = $polaczenie->query("SELECT * FROM klub_masaze_lozka WHERE id=$lozko_id")->fetch_assoc();
    if (!$lozko) {
        echo json_encode(['ok' => false, 'msg' => 'Łóżko nie istnieje']);
        exit;
    }
    if ((int)$lozko['klient_id'] !== $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'To nie Twoje łóżko']);
        exit;
    }
    $polaczenie->query("UPDATE klub_masaze_lozka SET klient_id=NULL, zabieg_id=NULL, do_kiedy=NULL WHERE id=$lozko_id");

    $sys = "🚪 <b>{$gracz['login']}</b> przerywa zabieg na <b>" . htmlspecialchars($lozko['nazwa']) . "</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'masaze', 'system')");

    echo json_encode(['ok' => true, 'msg' => 'Zabieg przerwany — kasa nie wraca']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);