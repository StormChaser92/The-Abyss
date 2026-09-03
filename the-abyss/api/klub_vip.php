<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB VIP (Faza 5)
// 
// Opłata 500$ → dostęp do Loży VIP do 6:00 rano (lub 8h, co dłuższe)
// 
// GET  ?op=stan                — czy gracz ma aktywny dostęp
// POST op=zaplac               — kup dostęp (z gotówki gracza)
// POST op=anuluj               — anuluj swój dostęp (bez zwrotu)
// 
// Zwraca: { ok, msg?, ma_dostep, waznosc_do, waznosc_fmt? }
// ════════════════════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['zalogowany']) || $_SESSION['zalogowany'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Nie zalogowano']);
    exit;
}

require_once __DIR__ . "/../db.php";
header('Content-Type: application/json; charset=utf-8');

$id_gracza = (int)$_SESSION['id_gracza'];
$gracz = $polaczenie->query("SELECT id, login, gotowka, is_barman, is_mg FROM gracze WHERE id=$id_gracza")->fetch_assoc();
if (!$gracz) {
    echo json_encode(['ok' => false, 'msg' => 'Brak danych gracza']);
    exit;
}
$jest_uprawniony = ($gracz['is_barman'] || $gracz['is_mg']);

$op = $_REQUEST['op'] ?? 'stan';

// Cleanup wygasłych
$polaczenie->query("UPDATE klub_vip_zaplaty SET aktywny=0 WHERE aktywny=1 AND waznosc_do <= NOW()");

// Helper: czy gracz ma aktywny dostęp
function vip_aktywny_dostep($polaczenie, $id_gracza, $jest_uprawniony) {
    if ($jest_uprawniony) {
        // Barman + MG zawsze mają dostęp
        return ['ma_dostep' => true, 'powod' => 'rola', 'waznosc_do' => null];
    }
    $z = $polaczenie->query("
        SELECT id, waznosc_do FROM klub_vip_zaplaty
        WHERE gracz_id=$id_gracza AND aktywny=1 AND waznosc_do > NOW()
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    if ($z) {
        return [
            'ma_dostep' => true,
            'powod' => 'oplata',
            'waznosc_do' => $z['waznosc_do'],
            'zaplata_id' => (int)$z['id'],
        ];
    }
    return ['ma_dostep' => false];
}

// ═══════════════════════════════════════════════════════════════════
// STAN
// ═══════════════════════════════════════════════════════════════════
if ($op === 'stan') {
    $st = vip_aktywny_dostep($polaczenie, $id_gracza, $jest_uprawniony);
    $resp = [
        'ok' => true,
        'ma_dostep' => $st['ma_dostep'],
        'powod' => $st['powod'] ?? null,
        'waznosc_do' => $st['waznosc_do'] ?? null,
        'waznosc_fmt' => isset($st['waznosc_do']) && $st['waznosc_do']
            ? date('d.m H:i', strtotime($st['waznosc_do']))
            : null,
        'gotowka' => (int)$gracz['gotowka'],
        'cena' => 500,
    ];
    echo json_encode($resp);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ZAPŁAĆ ZA DOSTĘP
// ═══════════════════════════════════════════════════════════════════
if ($op === 'zaplac') {
    $cena = 500;

    // Czy już ma aktywny dostęp?
    $st = vip_aktywny_dostep($polaczenie, $id_gracza, $jest_uprawniony);
    if ($st['ma_dostep'] && ($st['powod'] === 'oplata')) {
        echo json_encode([
            'ok' => false,
            'msg' => 'Masz już aktywny dostęp do VIP do ' . date('d.m H:i', strtotime($st['waznosc_do']))
        ]);
        exit;
    }
    if ($st['ma_dostep'] && ($st['powod'] === 'rola')) {
        echo json_encode([
            'ok' => false,
            'msg' => 'Masz już dostęp do VIP z tytułu roli (Barman/MG)'
        ]);
        exit;
    }

    if ((int)$gracz['gotowka'] < $cena) {
        echo json_encode([
            'ok' => false,
            'msg' => "Brak gotówki — masz " . number_format((int)$gracz['gotowka'], 0, '', ' ') . " \$, potrzeba $cena \$"
        ]);
        exit;
    }

    // Ważność do 6:00 rano (lub +8h jeśli to już po 6:00)
    $teraz = time();
    $jutro_6 = strtotime(date('Y-m-d', $teraz) . ' 06:00:00');
    if ($jutro_6 <= $teraz) {
        $jutro_6 = strtotime(date('Y-m-d', $teraz + 86400) . ' 06:00:00');
    }
    // Jeśli do 6:00 jest mniej niż 4 godz, dorzuć resztę nocy (>= 4 godz)
    if (($jutro_6 - $teraz) < 4 * 3600) {
        $jutro_6 = $teraz + 8 * 3600;
    }
    $waznosc_do = date('Y-m-d H:i:s', $jutro_6);

    // Pobierz pieniądze
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena WHERE id=$id_gracza");
    $polaczenie->query("INSERT INTO klub_vip_zaplaty (gracz_id, kwota, waznosc_do) VALUES ($id_gracza, $cena, '$waznosc_do')");

    // System message
    $sys = "🥂 <b>{$gracz['login']}</b> opłacił/a wstęp do <b>Loży VIP</b> ($cena \$, ważne do " . date('H:i', strtotime($waznosc_do)) . ").";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");

    echo json_encode([
        'ok' => true,
        'msg' => "✓ Wstęp do VIP opłacony — ważny do " . date('d.m H:i', strtotime($waznosc_do)),
        'waznosc_do' => $waznosc_do,
        'waznosc_fmt' => date('d.m H:i', strtotime($waznosc_do)),
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ANULUJ DOSTĘP
// ═══════════════════════════════════════════════════════════════════
if ($op === 'anuluj') {
    $polaczenie->query("UPDATE klub_vip_zaplaty SET aktywny=0 WHERE gracz_id=$id_gracza AND aktywny=1");
    echo json_encode(['ok' => true, 'msg' => 'Dostęp anulowany']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);