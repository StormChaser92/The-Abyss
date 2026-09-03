<?php
// ════════════════════════════════════════════════════════════════════
// API — POGODA (Faza 5)
// 
// Pobiera aktualną pogodę dla miasta klubu z open-meteo.com
// (darmowe, bez klucza API). Cache 1 godz w klub_pogoda_cache.
// 
// Używane przez Taras + lobby (opcjonalnie).
// 
// GET — zwraca aktualny stan pogody
//   { ok, miasto, temperatura, opis, ikona, wiatr, jest_noc, pobrano_o }
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

// Lokalizacja klubu
$lok = $polaczenie->query("SELECT * FROM klub_konfiguracja_lokalizacja WHERE id=1")->fetch_assoc();
if (!$lok) {
    echo json_encode(['ok' => false, 'msg' => 'Brak konfiguracji lokalizacji']);
    exit;
}

// Cache
$cache = $polaczenie->query("SELECT * FROM klub_pogoda_cache WHERE id=1")->fetch_assoc();
if (!$cache) {
    $polaczenie->query("INSERT INTO klub_pogoda_cache (id, miasto) VALUES (1, '" . $polaczenie->real_escape_string($lok['miasto']) . "')");
    $cache = $polaczenie->query("SELECT * FROM klub_pogoda_cache WHERE id=1")->fetch_assoc();
}

$trzeba_pobrac = (
    !$cache['pobrano_o']
    || !$cache['nastepne_pobranie']
    || strtotime($cache['nastepne_pobranie']) <= time()
    || $cache['miasto'] !== $lok['miasto']  // zmiana miasta
);

if ($trzeba_pobrac) {
    // Pobierz z open-meteo.com (darmowe, bez klucza)
    $lat = (float)$lok['szerokosc_geo'];
    $lon = (float)$lok['dlugosc_geo'];
    $tz = $lok['strefa_czasowa'];

    $url = "https://api.open-meteo.com/v1/forecast"
         . "?latitude={$lat}"
         . "&longitude={$lon}"
         . "&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,is_day"
         . "&timezone=" . urlencode($tz);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 6,
            'header' => "User-Agent: TheAbyss/1.0\r\n",
            'method' => 'GET',
            'ignore_errors' => true,
        ]
    ]);

    $response = @file_get_contents($url, false, $ctx);

    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && isset($data['current'])) {
            $c = $data['current'];

            $kod = (int)($c['weather_code'] ?? 0);
            $temp = isset($c['temperature_2m']) ? (float)$c['temperature_2m'] : null;
            $wiatr = isset($c['wind_speed_10m']) ? (float)$c['wind_speed_10m'] : null;
            $wilg = isset($c['relative_humidity_2m']) ? (int)$c['relative_humidity_2m'] : null;
            $is_day = isset($c['is_day']) ? (int)$c['is_day'] : 1;
            $jest_noc = ($is_day === 0) ? 1 : 0;

            $opis = pogoda_opis($kod, $jest_noc);

            $miasto_e = $polaczenie->real_escape_string($lok['miasto']);
            $opis_e = $polaczenie->real_escape_string($opis);
            $temp_sql = $temp !== null ? $temp : 'NULL';
            $wiatr_sql = $wiatr !== null ? $wiatr : 'NULL';
            $wilg_sql = $wilg !== null ? $wilg : 'NULL';

            $polaczenie->query("
                UPDATE klub_pogoda_cache SET
                    miasto='$miasto_e',
                    temperatura_c=$temp_sql,
                    kod_pogody=$kod,
                    opis='$opis_e',
                    wiatr_kmh=$wiatr_sql,
                    wilgotnosc=$wilg_sql,
                    jest_noc=$jest_noc,
                    pobrano_o=NOW(),
                    nastepne_pobranie=DATE_ADD(NOW(), INTERVAL 1 HOUR)
                WHERE id=1
            ");

            $cache = $polaczenie->query("SELECT * FROM klub_pogoda_cache WHERE id=1")->fetch_assoc();
        }
    }
    // jeśli się nie udało — używamy starego cache
}

// Zwróć obecny stan
$kod = (int)($cache['kod_pogody'] ?? 0);
$jest_noc = (int)($cache['jest_noc'] ?? 0);

echo json_encode([
    'ok' => true,
    'miasto' => $cache['miasto'] ?? $lok['miasto'],
    'kraj' => $lok['kraj'],
    'temperatura' => $cache['temperatura_c'] !== null ? (float)$cache['temperatura_c'] : null,
    'kod_pogody' => $kod,
    'opis' => $cache['opis'] ?: pogoda_opis($kod, $jest_noc),
    'ikona' => pogoda_ikona($kod, $jest_noc),
    'wiatr_kmh' => $cache['wiatr_kmh'] !== null ? (float)$cache['wiatr_kmh'] : null,
    'wilgotnosc' => $cache['wilgotnosc'] !== null ? (int)$cache['wilgotnosc'] : null,
    'jest_noc' => (bool)$jest_noc,
    'pobrano_o' => $cache['pobrano_o'] ? date('H:i', strtotime($cache['pobrano_o'])) : null,
    'efekt' => pogoda_efekt($kod),  // 'rain' | 'snow' | 'fog' | 'clear' | 'storm'
]);


// ════════════════════════════════════════════════════════════════════
// HELPERY
// ════════════════════════════════════════════════════════════════════

/**
 * WMO weather code → polski opis
 * https://open-meteo.com/en/docs (sekcja Weather code)
 */
function pogoda_opis($kod, $jest_noc = 0) {
    $opisy = [
        0 => $jest_noc ? 'Pogodna noc' : 'Słonecznie',
        1 => 'Lekko pochmurno',
        2 => 'Częściowe zachmurzenie',
        3 => 'Pochmurno',
        45 => 'Mgła',
        48 => 'Gęsta mgła',
        51 => 'Lekka mżawka',
        53 => 'Mżawka',
        55 => 'Gęsta mżawka',
        56 => 'Marznąca mżawka',
        57 => 'Marznąca mżawka',
        61 => 'Lekki deszcz',
        63 => 'Deszcz',
        65 => 'Ulewa',
        66 => 'Marznący deszcz',
        67 => 'Marznący deszcz',
        71 => 'Lekki śnieg',
        73 => 'Śnieg',
        75 => 'Gęsty śnieg',
        77 => 'Ziarna śniegu',
        80 => 'Przelotne opady',
        81 => 'Przelotne opady',
        82 => 'Ulewne przelotne opady',
        85 => 'Przelotny śnieg',
        86 => 'Gęsty przelotny śnieg',
        95 => 'Burza',
        96 => 'Burza z gradem',
        99 => 'Silna burza',
    ];
    return $opisy[$kod] ?? 'Pogoda';
}

/**
 * WMO code → emoji ikona
 */
function pogoda_ikona($kod, $jest_noc = 0) {
    if ($kod === 0) return $jest_noc ? '🌙' : '☀️';
    if ($kod >= 1 && $kod <= 3) return $jest_noc ? '☁️' : '⛅';
    if ($kod === 45 || $kod === 48) return '🌫️';
    if (($kod >= 51 && $kod <= 67) || ($kod >= 80 && $kod <= 82)) return '🌧️';
    if (($kod >= 71 && $kod <= 77) || $kod === 85 || $kod === 86) return '❄️';
    if ($kod >= 95) return '⛈️';
    return '☁️';
}

/**
 * WMO code → kategoria efektu wizualnego (CSS animacja na tarasie)
 */
function pogoda_efekt($kod) {
    if ($kod === 0) return 'clear';
    if ($kod >= 1 && $kod <= 3) return 'cloudy';
    if ($kod === 45 || $kod === 48) return 'fog';
    if (($kod >= 51 && $kod <= 67) || ($kod >= 80 && $kod <= 82)) return 'rain';
    if (($kod >= 71 && $kod <= 77) || $kod === 85 || $kod === 86) return 'snow';
    if ($kod >= 95) return 'storm';
    return 'cloudy';
}