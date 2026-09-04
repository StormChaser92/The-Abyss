<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — KATALOG MIAST (globalny)
   Jedno źródło prawdy dla: game.php (pogoda, shard, topbar)
                           pages/lotnisko.php (podróże, koszty)
   ───────────────────────────────────────────────────────────────────────
   Format klucza: WIELKIE LITERY, bez polskich znaków (ASCII).
   Klucz = wartość zapisywana do `gracze.obecne_miasto`.
   ═══════════════════════════════════════════════════════════════════════ */

$MIASTA_DANE = [

    /* ── 🇺🇸 USA ──────────────────────────────────────────────── */
    'NEW YORK' => [
        'lat' => 40.7128, 'lng' => -74.0060, 'shard' => 'NYC-01',
        'kraj' => 'USA', 'flaga' => '🇺🇸', 'strefa' => 'America/New_York',
        'opis'  => 'Stolica neonu i grzechu. The Abyss ma tu swoje korzenie.',
    ],
    'LOS ANGELES' => [
        'lat' => 34.0522, 'lng' => -118.2437, 'shard' => 'LAX-01',
        'kraj' => 'USA', 'flaga' => '🇺🇸', 'strefa' => 'America/Los_Angeles',
        'opis'  => 'Miasto aniołów, smogu i syntetycznych marzeń.',
    ],

    /* ── 🇬🇧 UK ───────────────────────────────────────────────── */
    'LONDON' => [
        'lat' => 51.5074, 'lng' => -0.1278, 'shard' => 'LDN-01',
        'kraj' => 'UK', 'flaga' => '🇬🇧', 'strefa' => 'Europe/London',
        'opis'  => 'Deszczowa korona Europy — mgła, kamera, podsłuch.',
    ],
    'MANCHESTER' => [
        'lat' => 53.4808, 'lng' => -2.2426, 'shard' => 'MCR-01',
        'kraj' => 'UK', 'flaga' => '🇬🇧', 'strefa' => 'Europe/London',
        'opis'  => 'Ceglane wnętrzności przemysłu. Surowa, uliczna, prawdziwa.',
    ],

    /* ── 🇫🇷 FRANCJA ──────────────────────────────────────────── */
    'PARIS' => [
        'lat' => 48.8566, 'lng' => 2.3522, 'shard' => 'PAR-01',
        'kraj' => 'FRANCJA', 'flaga' => '🇫🇷', 'strefa' => 'Europe/Paris',
        'opis'  => 'Szarmancki neon nad Sekwaną. Haute couture i haute crime.',
    ],
    'MARSEILLE' => [
        'lat' => 43.2965, 'lng' => 5.3698, 'shard' => 'MRS-01',
        'kraj' => 'FRANCJA', 'flaga' => '🇫🇷', 'strefa' => 'Europe/Paris',
        'opis'  => 'Portowe miasto — mafia, morze i tony kontrabandy.',
    ],

    /* ── 🇩🇪 NIEMCY ──────────────────────────────────────────── */
    'BERLIN' => [
        'lat' => 52.5200, 'lng' => 13.4050, 'shard' => 'BER-01',
        'kraj' => 'NIEMCY', 'flaga' => '🇩🇪', 'strefa' => 'Europe/Berlin',
        'opis'  => 'Elektroniczne podziemie. Techno w bunkrach, granice w chmurze.',
    ],
    'MUNICH' => [
        'lat' => 48.1351, 'lng' => 11.5820, 'shard' => 'MUC-01',
        'kraj' => 'NIEMCY', 'flaga' => '🇩🇪', 'strefa' => 'Europe/Berlin',
        'opis'  => 'Korporacyjna stolica Bawarii — piwo, chipy, precyzja.',
    ],

    /* ── 🇯🇵 JAPONIA ──────────────────────────────────────────── */
    'TOKYO' => [
        'lat' => 35.6762, 'lng' => 139.6503, 'shard' => 'TKY-01',
        'kraj' => 'JAPONIA', 'flaga' => '🇯🇵', 'strefa' => 'Asia/Tokyo',
        'opis'  => 'Najgęstsza plątanina kabli i świateł na planecie.',
    ],
    'OSAKA' => [
        'lat' => 34.6937, 'lng' => 135.5023, 'shard' => 'OSA-01',
        'kraj' => 'JAPONIA', 'flaga' => '🇯🇵', 'strefa' => 'Asia/Tokyo',
        'opis'  => 'Kuchnia uliczna Japonii — ramen, Yakuza, blask reklam.',
    ],

    /* ── 🇵🇱 POLSKA ─────────────────────────────────────────── */
    'WARSZAWA' => [
        'lat' => 52.2297, 'lng' => 21.0122, 'shard' => 'WAW-01',
        'kraj' => 'POLSKA', 'flaga' => '🇵🇱', 'strefa' => 'Europe/Warsaw',
        'opis'  => 'Miasto trzech warstw: ruiny, szkło i serwerownie.',
    ],
    'KRAKOW' => [
        'lat' => 50.0647, 'lng' => 19.9450, 'shard' => 'KRK-01',
        'kraj' => 'POLSKA', 'flaga' => '🇵🇱', 'strefa' => 'Europe/Warsaw',
        'opis'  => 'Średniowiecze z implantami. Smok pod Wawelem wciąż żyje.',
    ],

    /* ── 🇮🇹 WŁOCHY ──────────────────────────────────────────── */
    'ROME' => [
        'lat' => 41.9028, 'lng' => 12.4964, 'shard' => 'ROM-01',
        'kraj' => 'WLOCHY', 'flaga' => '🇮🇹', 'strefa' => 'Europe/Rome',
        'opis'  => 'Imperium, które nigdy nie upadło — bazyliki i syndykaty.',
    ],
    'MILAN' => [
        'lat' => 45.4642, 'lng' => 9.1900, 'shard' => 'MIL-01',
        'kraj' => 'WLOCHY', 'flaga' => '🇮🇹', 'strefa' => 'Europe/Rome',
        'opis'  => 'Moda, finanse i bardzo drogie kontrakty.',
    ],

    /* ── 🇪🇸 HISZPANIA ───────────────────────────────────────── */
    'MADRID' => [
        'lat' => 40.4168, 'lng' => -3.7038, 'shard' => 'MAD-01',
        'kraj' => 'HISZPANIA', 'flaga' => '🇪🇸', 'strefa' => 'Europe/Madrid',
        'opis'  => 'Serce Iberii bije czerwienią. Korrida w stylu cyber-punk.',
    ],
    'BARCELONA' => [
        'lat' => 41.3851, 'lng' => 2.1734, 'shard' => 'BCN-01',
        'kraj' => 'HISZPANIA', 'flaga' => '🇪🇸', 'strefa' => 'Europe/Madrid',
        'opis'  => 'Gaudí spotyka gang war. Każda iglica to syndykat.',
    ],

    /* ── 🇷🇺 ROSJA ───────────────────────────────────────────── */
    'MOSCOW' => [
        'lat' => 55.7558, 'lng' => 37.6173, 'shard' => 'MOW-01',
        'kraj' => 'ROSJA', 'flaga' => '🇷🇺', 'strefa' => 'Europe/Moscow',
        'opis'  => 'Lód i stal. Żaden Kreml, żadna ulica tu nie wybacza.',
    ],
    'SAINT PETERSBURG' => [
        'lat' => 59.9311, 'lng' => 30.3609, 'shard' => 'LED-01',
        'kraj' => 'ROSJA', 'flaga' => '🇷🇺', 'strefa' => 'Europe/Moscow',
        'opis'  => 'Wenecja Północy z Kałasznikowem w kieszeni.',
    ],

    /* ── 🇨🇳 CHINY ────────────────────────────────────────────── */
    'SHANGHAI' => [
        'lat' => 31.2304, 'lng' => 121.4737, 'shard' => 'SHA-01',
        'kraj' => 'CHINY', 'flaga' => '🇨🇳', 'strefa' => 'Asia/Shanghai',
        'opis'  => 'Pudong drapie niebo, Bund drapie nerwy.',
    ],
    'BEIJING' => [
        'lat' => 39.9042, 'lng' => 116.4074, 'shard' => 'PEK-01',
        'kraj' => 'CHINY', 'flaga' => '🇨🇳', 'strefa' => 'Asia/Shanghai',
        'opis'  => 'Zakazane Miasto w erze zakazanych implantów.',
    ],

    /* ── 🇧🇷 BRAZYLIA ─────────────────────────────────────────── */
    'SAO PAULO' => [
        'lat' => -23.5505, 'lng' => -46.6333, 'shard' => 'SAO-01',
        'kraj' => 'BRAZYLIA', 'flaga' => '🇧🇷', 'strefa' => 'America/Sao_Paulo',
        'opis'  => 'Największa dżungla-beton Południa. Nikt tu nie śpi.',
    ],
    'RIO DE JANEIRO' => [
        'lat' => -22.9068, 'lng' => -43.1729, 'shard' => 'RIO-01',
        'kraj' => 'BRAZYLIA', 'flaga' => '🇧🇷', 'strefa' => 'America/Sao_Paulo',
        'opis'  => 'Fawele z dronami, plaże z kulami.',
    ],

    /* ── 🇲🇽 MEKSYK ──────────────────────────────────────────── */
    'MEXICO CITY' => [
        'lat' => 19.4326, 'lng' => -99.1332, 'shard' => 'MEX-01',
        'kraj' => 'MEKSYK', 'flaga' => '🇲🇽', 'strefa' => 'America/Mexico_City',
        'opis'  => 'Metropolis nad szlakami kartelu — 10 000 metrów kultury.',
    ],
    'CANCUN' => [
        'lat' => 21.1619, 'lng' => -86.8515, 'shard' => 'CUN-01',
        'kraj' => 'MEKSYK', 'flaga' => '🇲🇽', 'strefa' => 'America/Cancun',
        'opis'  => 'Niebieska zatoka dla turystów, czarne dna dla przemytu.',
    ],

    /* ── 🇦🇪 ZEA ─────────────────────────────────────────────── */
    'DUBAI' => [
        'lat' => 25.2048, 'lng' => 55.2708, 'shard' => 'DXB-01',
        'kraj' => 'ZEA', 'flaga' => '🇦🇪', 'strefa' => 'Asia/Dubai',
        'opis'  => 'Pustynia złota. Tu się pierze, kupuje i znika.',
    ],
    'ABU DHABI' => [
        'lat' => 24.4539, 'lng' => 54.3773, 'shard' => 'AUH-01',
        'kraj' => 'ZEA', 'flaga' => '🇦🇪', 'strefa' => 'Asia/Dubai',
        'opis'  => 'Brat Dubaju z większym portfelem i większą bronią.',
    ],

    /* ── 🇮🇳 INDIE ───────────────────────────────────────────── */
    'MUMBAI' => [
        'lat' => 19.0760, 'lng' => 72.8777, 'shard' => 'BOM-01',
        'kraj' => 'INDIE', 'flaga' => '🇮🇳', 'strefa' => 'Asia/Kolkata',
        'opis'  => 'Bollywood spotyka Dharavi. Każdy gra swoją rolę.',
    ],
    'NEW DELHI' => [
        'lat' => 28.6139, 'lng' => 77.2090, 'shard' => 'DEL-01',
        'kraj' => 'INDIE', 'flaga' => '🇮🇳', 'strefa' => 'Asia/Kolkata',
        'opis'  => 'Polityka i dym. Siedem stolic pod jednym dachem.',
    ],

    /* ── 🇨🇦 KANADA ─────────────────────────────────────────── */
    'TORONTO' => [
        'lat' => 43.6532, 'lng' => -79.3832, 'shard' => 'YYZ-01',
        'kraj' => 'KANADA', 'flaga' => '🇨🇦', 'strefa' => 'America/Toronto',
        'opis'  => 'Wieża CN świeci neonem. Uprzejmość to tylko fasada.',
    ],
    'VANCOUVER' => [
        'lat' => 49.2827, 'lng' => -123.1207, 'shard' => 'YVR-01',
        'kraj' => 'KANADA', 'flaga' => '🇨🇦', 'strefa' => 'America/Vancouver',
        'opis'  => 'Gdzie ocean spotyka górę, a góra — hakera.',
    ],

    /* ── 🇦🇺 AUSTRALIA ──────────────────────────────────────── */
    'SYDNEY' => [
        'lat' => -33.8688, 'lng' => 151.2093, 'shard' => 'SYD-01',
        'kraj' => 'AUSTRALIA', 'flaga' => '🇦🇺', 'strefa' => 'Australia/Sydney',
        'opis'  => 'Harbour Bridge nocą — najładniejszy cel świata.',
    ],
    'MELBOURNE' => [
        'lat' => -37.8136, 'lng' => 144.9631, 'shard' => 'MEL-01',
        'kraj' => 'AUSTRALIA', 'flaga' => '🇦🇺', 'strefa' => 'Australia/Melbourne',
        'opis'  => 'Kawiarnie, sztuka uliczna, syndykaty z brytyjskim akcentem.',
    ],

    /* ── 🇸🇪 SZWECJA ──────────────────────────────────────────── */
    'STOCKHOLM' => [
        'lat' => 59.3293, 'lng' => 18.0686, 'shard' => 'STO-01',
        'kraj' => 'SZWECJA', 'flaga' => '🇸🇪', 'strefa' => 'Europe/Stockholm',
        'opis'  => 'Czternaście wysp, czternaście układów. Porządek jest tu towarem.',
    ],
    'GOTHENBURG' => [
        'lat' => 57.7089, 'lng' => 11.9746, 'shard' => 'GOT-01',
        'kraj' => 'SZWECJA', 'flaga' => '🇸🇪', 'strefa' => 'Europe/Stockholm',
        'opis'  => 'Stoczniowy zachód — dźwigi, kontenery i nic, co nie ma ceny.',
    ],

    /* ── 🇳🇴 NORWEGIA ─────────────────────────────────────────── */
    'OSLO' => [
        'lat' => 59.9139, 'lng' => 10.7522, 'shard' => 'OSL-01',
        'kraj' => 'NORWEGIA', 'flaga' => '🇳🇴', 'strefa' => 'Europe/Oslo',
        'opis'  => 'Najczystsze miasto Północy. Brud zszedł piętro niżej.',
    ],
    'BERGEN' => [
        'lat' => 60.3913, 'lng' => 5.3221, 'shard' => 'BGO-01',
        'kraj' => 'NORWEGIA', 'flaga' => '🇳🇴', 'strefa' => 'Europe/Oslo',
        'opis'  => 'Deszcz dwieście dni w roku i pamięć dłuższa niż akta.',
    ],

    /* ── 🇩🇰 DANIA ────────────────────────────────────────────── */
    'COPENHAGEN' => [
        'lat' => 55.6761, 'lng' => 12.5683, 'shard' => 'CPH-01',
        'kraj' => 'DANIA', 'flaga' => '🇩🇰', 'strefa' => 'Europe/Copenhagen',
        'opis'  => 'Rowery, design i najcichsze interesy w tej części mapy.',
    ],
    'AARHUS' => [
        'lat' => 56.1629, 'lng' => 10.2039, 'shard' => 'AAR-01',
        'kraj' => 'DANIA', 'flaga' => '🇩🇰', 'strefa' => 'Europe/Copenhagen',
        'opis'  => 'Port, uniwersytet i dwa światy, które nigdy się nie mieszają.',
    ],

    /* ── 🇨🇿 CZECHY ───────────────────────────────────────────── */
    'PRAGUE' => [
        'lat' => 50.0755, 'lng' => 14.4378, 'shard' => 'PRG-01',
        'kraj' => 'CZECHY', 'flaga' => '🇨🇿', 'strefa' => 'Europe/Prague',
        'opis'  => 'Sto wież i tyle samo piwnic, o których się nie mówi.',
    ],
    'BRNO' => [
        'lat' => 49.1951, 'lng' => 16.6068, 'shard' => 'BRQ-01',
        'kraj' => 'CZECHY', 'flaga' => '🇨🇿', 'strefa' => 'Europe/Prague',
        'opis'  => 'Techniczne serce Moraw — laboratoria z obiema licencjami.',
    ],

    /* ── 🇧🇪 BELGIA ───────────────────────────────────────────── */
    'BRUSSELS' => [
        'lat' => 50.8503, 'lng' => 4.3517, 'shard' => 'BRU-01',
        'kraj' => 'BELGIA', 'flaga' => '🇧🇪', 'strefa' => 'Europe/Brussels',
        'opis'  => 'Urzędy, tłumacze i korytarze, w których zapadają decyzje.',
    ],
    'ANTWERP' => [
        'lat' => 51.2194, 'lng' => 4.4025, 'shard' => 'ANR-01',
        'kraj' => 'BELGIA', 'flaga' => '🇧🇪', 'strefa' => 'Europe/Brussels',
        'opis'  => 'Diamenty na górze, kontenery na dole. Przez port idzie wszystko.',
    ],
];

/* ═══════════════════════════════════════════════════════════════════
   HELPER: odległość w km między dwoma punktami (formuła Haversine).
   ═══════════════════════════════════════════════════════════════════ */
function km_odleglosc($lat1, $lng1, $lat2, $lng2) {
    $r = 6371.0; // promień Ziemi w km
    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);
    $a = sin($dlat/2)**2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng/2)**2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
}

/* ═══════════════════════════════════════════════════════════════════
   HELPER: koszt (kasa + energia) lotu.
   ═══════════════════════════════════════════════════════════════════ */
function koszt_lotu($km, $ten_sam_kraj = false) {
    $kasa    = 400 + round($km * 0.28);
    if ($ten_sam_kraj) $kasa = round($kasa * 0.65);   // −35% rabatu w kraju
    $kasa    = max(250, $kasa);
    $energia = max(5, (int)round(6 + $km * 0.003));
    $czas    = max(5, (int)round($km / 60));          // minuty przelotu (flavour)
    return ['kasa' => $kasa, 'energia' => $energia, 'czas' => $czas];
}

/* ═══════════════════════════════════════════════════════════════════
   HELPER: grupuje miasta po kraju (do rozwijanych list w lotnisko.php).
   ═══════════════════════════════════════════════════════════════════ */
function grupuj_miasta_po_kraju($miasta) {
    $grupy = [];
    foreach ($miasta as $nazwa => $d) {
        $kr = $d['kraj'];
        if (!isset($grupy[$kr])) {
            $grupy[$kr] = ['flaga' => $d['flaga'], 'miasta' => []];
        }
        $grupy[$kr]['miasta'][$nazwa] = $d;
    }
    return $grupy;
}

/* ═══════════════════════════════════════════════════════════════════
   POGODA: Open-Meteo API (darmowe, bez klucza). Cache plikowy 30 min.
   ═══════════════════════════════════════════════════════════════════ */
function fetch_pogoda($lat, $lng) {
    $cache_dir = __DIR__ . '/../cache';
    if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);

    $cache_key  = md5("$lat,$lng");
    $cache_file = "$cache_dir/pogoda_$cache_key.json";

    // Cache 30 min — żeby nie hamerować API
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 1800) {
        $json = @file_get_contents($cache_file);
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['current'])) return $data;
    }

    $url = "https://api.open-meteo.com/v1/forecast"
         . "?latitude=$lat&longitude=$lng"
         . "&current=temperature_2m,weather_code"
         . "&timezone=auto";

    $json = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,   // XAMPP-friendly
            CURLOPT_USERAGENT      => 'TheAbyss/1.0',
        ]);
        $json = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents($url, false, $ctx);
    }

    if ($json) {
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['current'])) {
            @file_put_contents($cache_file, $json);
            return $data;
        }
    }
    return null;
}

/* ═══════════════════════════════════════════════════════════════════
   POGODA: mapowanie kodów WMO → cyberpunkowe etykiety.
   Zwraca [etykieta, kolor_hex].
   ═══════════════════════════════════════════════════════════════════ */
function formatuj_pogode($data) {
    if (!$data || !isset($data['current']['weather_code'])) {
        return ['NO SIGNAL', '#8a818e'];
    }
    $temp = round($data['current']['temperature_2m']);
    $code = (int)$data['current']['weather_code'];

    // WMO Weather Codes
    $mapa = [
        0  => ['CLEAR',           '#ffd700'],
        1  => ['MOSTLY CLEAR',    '#e8e1e8'],
        2  => ['PARTLY CLOUDY',   '#b8b0b8'],
        3  => ['OVERCAST',        '#8a818e'],
        45 => ['NEON FOG',        '#8a818e'],
        48 => ['RIME FOG',        '#b8b0b8'],
        51 => ['LIGHT DRIZZLE',   '#4ad6ff'],
        53 => ['DRIZZLE',         '#4ad6ff'],
        55 => ['HEAVY DRIZZLE',   '#4ad6ff'],
        56 => ['ICE DRIZZLE',     '#4ad6ff'],
        57 => ['ICE DRIZZLE',     '#4ad6ff'],
        61 => ['LIGHT RAIN',      '#4ad6ff'],
        63 => ['RAIN',            '#4ad6ff'],
        65 => ['ACID RAIN',       '#ff3d5e'],
        66 => ['FREEZING RAIN',   '#4ad6ff'],
        67 => ['FREEZING RAIN',   '#4ad6ff'],
        71 => ['LIGHT SNOW',      '#ffffff'],
        73 => ['SNOW',            '#ffffff'],
        75 => ['HEAVY SNOW',      '#ffffff'],
        77 => ['SNOW GRAINS',     '#ffffff'],
        80 => ['RAIN SHOWERS',    '#4ad6ff'],
        81 => ['RAIN SHOWERS',    '#4ad6ff'],
        82 => ['VIOLENT SHOWER',  '#ff3d5e'],
        85 => ['SNOW SHOWER',     '#ffffff'],
        86 => ['HEAVY SNOW-FALL', '#ffffff'],
        95 => ['THUNDERSTORM',    '#ff3d5e'],
        96 => ['THUNDER + HAIL',  '#ff3d5e'],
        99 => ['THUNDER + HAIL',  '#ff3d5e'],
    ];
    [$label, $color] = $mapa[$code] ?? ['ANOMALY', '#ff7a3d'];
    return ["$label · {$temp}°C", $color];
}