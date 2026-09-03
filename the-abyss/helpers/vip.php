<?php
// ════════════════════════════════════════════════════════════════════
// THE ABYSS — HELPER VIP
// ════════════════════════════════════════════════════════════════════
// Centralny moduł obsługi członkostwa VIP. Inne pliki gry wywołują
// funkcje stąd zamiast bezpośrednio sprawdzać `is_premium` w bazie.
//
// require_once "helpers/vip.php";  // w game.php po require_once "db.php"
//
// API:
//   czy_vip($gracz)              → bool — czy ma aktywny VIP
//   vip_pozostalo_dni($gracz)    → int  — ile pełnych dni jeszcze
//   vip_pozostalo_godzin($gracz) → int  — ile godzin (przydatne na końcówce)
//   vip_sync_status($id)         → void — synchronizuje is_premium z vip_do
//   dodaj_vip($id, $dni, ...)    → string|false — nowa data końca lub false
//   vip_pakiety()                → array — definicje pakietów do kupna
// ════════════════════════════════════════════════════════════════════

if (!defined('VIP_HELPER_LOADED')) {
    define('VIP_HELPER_LOADED', 1);

// ────────────────────────────────────────────────────────────────────
// vip_pakiety() — definicja pakietów do kupna
// Centralna definicja, łatwa do modyfikacji.
// Klucze tablicy = identyfikatory używane w formularzu zakupu.
// ────────────────────────────────────────────────────────────────────
function vip_pakiety() {
    return [
        'tygodniowy' => [
            'dni'   => 7,
            'koszt' => 25000,
            'nazwa' => 'Pakiet Tygodniowy',
            'opis'  => 'Krótki test luksusu. Przekonaj się czy elita Ci pasuje.',
            'kolor' => '#888888',
        ],
        'miesieczny' => [
            'dni'   => 30,
            'koszt' => 80000,
            'nazwa' => 'Pakiet Miesięczny',
            'opis'  => 'Najpopularniejszy wybór mieszkańców Manhattanu.',
            'kolor' => '#4ad6ff',
            'polecany' => true,
        ],
        'kwartalny' => [
            'dni'   => 90,
            'koszt' => 200000,
            'nazwa' => 'Pakiet Kwartalny',
            'opis'  => 'Trzy miesiące prestiżu. Oszczędzasz 17% w stosunku do miesięcznego.',
            'kolor' => '#ff7a3d',
        ],
        'roczny' => [
            'dni'   => 365,
            'koszt' => 600000,
            'nazwa' => 'Pakiet Roczny',
            'opis'  => 'Dla prawdziwych obywateli. Oszczędzasz ~38% w stosunku do miesięcznego.',
            'kolor' => '#ffd700',
            'najlepsza_oferta' => true,
        ],
    ];
}

// ────────────────────────────────────────────────────────────────────
// _vip_pobierz_date($gracz) — wewnętrzny helper, nie używaj bezpośrednio.
// Akceptuje: int (id gracza), array (rekord z 'vip_do'), null
// Zwraca: string z datą lub null.
// ────────────────────────────────────────────────────────────────────
function _vip_pobierz_date($gracz) {
    global $polaczenie;

    if (is_array($gracz)) {
        return $gracz['vip_do'] ?? null;
    }
    if (is_numeric($gracz)) {
        $id = (int)$gracz;
        if ($id <= 0) return null;
        $r = $polaczenie->query("SELECT vip_do FROM gracze WHERE id=$id");
        if (!$r || $r->num_rows === 0) return null;
        $row = $r->fetch_assoc();
        return $row['vip_do'] ?? null;
    }
    return null;
}

// ────────────────────────────────────────────────────────────────────
// czy_vip($gracz) — czy gracz ma aktywny VIP?
// $gracz może być: int (id), array (rekord), null
// ────────────────────────────────────────────────────────────────────
function czy_vip($gracz) {
    $vip_do = _vip_pobierz_date($gracz);
    if (empty($vip_do)) return false;
    return strtotime($vip_do) > time();
}

// ────────────────────────────────────────────────────────────────────
// vip_pozostalo_dni($gracz) — pełnych dni do wygaśnięcia
// ────────────────────────────────────────────────────────────────────
function vip_pozostalo_dni($gracz) {
    $vip_do = _vip_pobierz_date($gracz);
    if (empty($vip_do)) return 0;
    $diff = strtotime($vip_do) - time();
    if ($diff <= 0) return 0;
    return (int)ceil($diff / 86400);
}

// ────────────────────────────────────────────────────────────────────
// vip_pozostalo_godzin($gracz) — godziny do wygaśnięcia (czytelny licznik
// na ostatnim dniu)
// ────────────────────────────────────────────────────────────────────
function vip_pozostalo_godzin($gracz) {
    $vip_do = _vip_pobierz_date($gracz);
    if (empty($vip_do)) return 0;
    $diff = strtotime($vip_do) - time();
    if ($diff <= 0) return 0;
    return (int)floor($diff / 3600);
}

// ────────────────────────────────────────────────────────────────────
// vip_sync_status($id_gracza) — lazy-check.
// Synchronizuje flagę is_premium z aktualnym stanem vip_do.
// Wysyła powiadomienie gdy VIP właśnie wygasł.
// Wywoływać przy każdym wejściu na game.php (nagłówek).
// ────────────────────────────────────────────────────────────────────
function vip_sync_status($id_gracza) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    if ($id_gracza <= 0) return;

    $r = $polaczenie->query("SELECT vip_do, is_premium FROM gracze WHERE id=$id_gracza");
    if (!$r || $r->num_rows === 0) return;
    $row = $r->fetch_assoc();

    $aktywny = !empty($row['vip_do']) && strtotime($row['vip_do']) > time();
    $flaga_powinna = $aktywny ? 1 : 0;
    $flaga_obecna  = (int)$row['is_premium'];

    if ($flaga_obecna !== $flaga_powinna) {
        $polaczenie->query("UPDATE gracze SET is_premium=$flaga_powinna WHERE id=$id_gracza");

        // Jeśli VIP właśnie wygasł (z 1 na 0) — powiadom gracza
        if ($flaga_obecna === 1 && $flaga_powinna === 0) {
            $tresc = $polaczenie->real_escape_string(
                "Twoje członkostwo VIP właśnie wygasło. Aby odnowić status i zachować swoje przywileje — odwiedź sekcję Premium."
            );
            $polaczenie->query(
                "INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($id_gracza, '$tresc')"
            );
        }
    }
}

// ────────────────────────────────────────────────────────────────────
// dodaj_vip($id_gracza, $dni, $metoda, $koszt) — przyznanie VIP.
//
// $metoda — sposób zakupu, do logu historii. Wartości:
//   'waluta_growa' — kupno za $ growe (faktycznie Twoja gotówka)
//   'przelewy24'   — przelewy24 (przyszłość, real money)
//   'patreon'      — wsparcie Patreon (przyszłość)
//   'admin'        — przyznane ręcznie przez MG/admina
//   'gift'         — prezent od innego gracza
//   'kompensacja'  — rekompensata za błędy
//
// $koszt — w $ growych albo w groszach przy real money. Tylko do logu.
//
// JEŚLI gracz ma aktywny VIP — nowe dni dodają się DO KOŃCA, nie od dziś.
// (czyli nie tracisz pozostałego czasu kupując następny pakiet)
//
// ZWRACA: string z nową datą końca VIP, lub false przy błędzie.
// ────────────────────────────────────────────────────────────────────
function dodaj_vip($id_gracza, $dni, $metoda = 'waluta_growa', $koszt = 0) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    $dni = (int)$dni;
    $koszt = (int)$koszt;

    if ($id_gracza <= 0 || $dni <= 0) return false;

    $r = $polaczenie->query("SELECT vip_do FROM gracze WHERE id=$id_gracza");
    if (!$r || $r->num_rows === 0) return false;
    $row = $r->fetch_assoc();

    // Punkt startowy: koniec aktualnego VIP (jeśli aktywny) lub teraz
    if (!empty($row['vip_do']) && strtotime($row['vip_do']) > time()) {
        $start = $row['vip_do'];
    } else {
        $start = date('Y-m-d H:i:s');
    }

    $nowy_koniec = date('Y-m-d H:i:s', strtotime("$start +$dni days"));
    $nk_esc = $polaczenie->real_escape_string($nowy_koniec);

    $polaczenie->query(
        "UPDATE gracze SET vip_do='$nk_esc', is_premium=1 WHERE id=$id_gracza"
    );

    $metoda_esc = $polaczenie->real_escape_string($metoda);
    $polaczenie->query(
        "INSERT INTO vip_historia (gracz_id, pakiet_dni, koszt, metoda, vip_do_po_zakupie)
         VALUES ($id_gracza, $dni, $koszt, '$metoda_esc', '$nk_esc')"
    );

    return $nowy_koniec;
}

// ────────────────────────────────────────────────────────────────────
// vip_status_text($gracz) — pomocnik do widoku.
// Zwraca string typu "Aktywny do 2026-05-30 14:32 (32 dni)" lub
// "Brak VIP".
// ────────────────────────────────────────────────────────────────────
function vip_status_text($gracz) {
    $vip_do = _vip_pobierz_date($gracz);
    if (empty($vip_do) || strtotime($vip_do) <= time()) {
        return 'Brak VIP';
    }
    $dni = vip_pozostalo_dni($gracz);
    $godz = vip_pozostalo_godzin($gracz);
    $data = date('Y-m-d H:i', strtotime($vip_do));

    if ($dni > 1) {
        return "Aktywny do $data (pozostało $dni dni)";
    } elseif ($godz > 1) {
        return "Aktywny do $data (pozostało $godz godzin)";
    } else {
        return "Aktywny do $data (kończy się w ciągu godziny!)";
    }
}

} // koniec VIP_HELPER_LOADED guard