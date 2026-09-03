<?php
// ════════════════════════════════════════════════════════════════════
// HELPER — SPRAWDZANIE ODZNAK (Faza 7)
// 
// Funkcja: klub_sprawdz_odznaki($polaczenie, $gracz_id)
// 
// Liczy progres każdej odznaki i przyznaje nowe (jeśli próg
// osiągnięty). Wywoływana lazy z lobby.php (raz na sesję) oraz
// po akcjach które wpływają na progres (rezerwacja, zabieg, etc).
// 
// Zwraca array nowo przyznanych odznak (w jednym wywołaniu) —
// można pokazać toast.
// ════════════════════════════════════════════════════════════════════

if (!function_exists('klub_sprawdz_odznaki')) {

/**
 * Główna funkcja sprawdzania i przyznawania odznak.
 * @param mysqli $polaczenie
 * @param int $gracz_id
 * @return array Lista nowo przyznanych odznak (slug, nazwa, ikona)
 */
function klub_sprawdz_odznaki($polaczenie, $gracz_id) {
    $gracz_id = (int)$gracz_id;
    $nowe_odznaki = [];

    // Pobierz wszystkie aktywne odznaki
    $defs = $polaczenie->query("SELECT * FROM klub_odznaki WHERE aktywna=1");
    if (!$defs) return [];

    // Pobierz już przyznane
    $przyznane = [];
    $pq = $polaczenie->query("SELECT odznaka_id FROM klub_gracz_odznaki WHERE gracz_id=$gracz_id");
    if ($pq) while ($r = $pq->fetch_assoc()) $przyznane[(int)$r['odznaka_id']] = true;

    while ($d = $defs->fetch_assoc()) {
        $oid = (int)$d['id'];
        if (isset($przyznane[$oid])) continue;  // już ma

        $progres = klub_oblicz_progres_odznaki($polaczenie, $gracz_id, $d['warunek_typ']);
        $prog = (int)$d['warunek_prog'];

        if ($progres >= $prog) {
            // Przyznaj
            $polaczenie->query("INSERT IGNORE INTO klub_gracz_odznaki (gracz_id, odznaka_id) VALUES ($gracz_id, $oid)");
            if ($polaczenie->affected_rows > 0) {
                $nowe_odznaki[] = [
                    'slug' => $d['slug'],
                    'nazwa' => $d['nazwa'],
                    'ikona' => $d['ikona_emoji'],
                    'opis' => $d['opis'],
                    'rzadkosc' => $d['rzadkosc'],
                ];
                // System message w klubie
                $login = $polaczenie->query("SELECT login FROM gracze WHERE id=$gracz_id")->fetch_assoc()['login'] ?? '???';
                $login_e = htmlspecialchars($login);
                $sys = "🏆 <b>$login_e</b> zdobył/a odznakę <b>{$d['ikona_emoji']} " . htmlspecialchars($d['nazwa']) . "</b>!";
                $sys_sql = $polaczenie->real_escape_string($sys);
                $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'lobby', 'system')");
            }
        }
    }

    return $nowe_odznaki;
}

/**
 * Oblicza progres dla danego typu warunku.
 * @return int aktualny progres gracza
 */
function klub_oblicz_progres_odznaki($polaczenie, $gracz_id, $warunek_typ) {
    $g = (int)$gracz_id;
    switch ($warunek_typ) {
        case 'vip_zaplaty':
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_vip_zaplaty WHERE gracz_id=$g")->fetch_assoc();
            return (int)$r['c'];

        case 'wiadomosci_w_klubie':
            // Wszystkie wiadomości gracza w salach klubu
            $sale = "'lobby','sala-glowna','sala-balowa','sauna','bdsm','tyly','vip','taras','basen','silownia','masaze','garderoba'";
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM czat WHERE id_gracza=$g AND sala IN ($sale) AND typ NOT IN ('system','bot','npc_speak')")->fetch_assoc();
            return (int)$r['c'];

        case 'plotek_z_reakcjami':
            // Plotki gracza, które mają >= 10 reakcji (pozytywne+negatywne razem)
            $r = $polaczenie->query("
                SELECT COUNT(*) AS c FROM klub_plotki
                WHERE autor_id=$g AND aktywna=1
                  AND (licznik_prawda + licznik_falsz) >= 10
            ")->fetch_assoc();
            return (int)$r['c'];

        case 'roznych_drinkow':
            $r = $polaczenie->query("SELECT COUNT(DISTINCT drink_id) AS c FROM klub_uzycia_drinkow WHERE gracz_id=$g")->fetch_assoc();
            return (int)$r['c'];

        case 'rezerwacji_eventow':
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_rezerwacje WHERE gracz_id=$g")->fetch_assoc();
            return (int)$r['c'];

        case 'sesji_basen':
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_sesje_sportowe WHERE gracz_id=$g AND sala='basen'")->fetch_assoc();
            return (int)$r['c'];

        case 'sesji_silownia':
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_sesje_sportowe WHERE gracz_id=$g AND sala='silownia'")->fetch_assoc();
            return (int)$r['c'];

        case 'sygnalow_flirtu':
            // Wymaga tabeli klub_flirty (z Fazy 3)
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_flirty WHERE od_gracza_id=$g")->fetch_assoc();
            return (int)$r['c'];

        case 'zabiegow_masazu':
            // Liczymy z osobnej tabeli historii zabiegów
            $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_zabiegi_historia WHERE gracz_id=$g")->fetch_assoc();
            return $r ? (int)$r['c'] : 0;

        case 'puszczonych_utworow':
            // Liczymy ile razy gracz puścił utwór jako DJ
            // (po system messages w sali głównej)
            $login_q = $polaczenie->query("SELECT login FROM gracze WHERE id=$g")->fetch_assoc();
            if (!$login_q) return 0;
            $login_e = $polaczenie->real_escape_string($login_q['login']);
            $r = $polaczenie->query("
                SELECT COUNT(*) AS c FROM czat
                WHERE typ='system' AND sala='sala-glowna'
                  AND tresc LIKE '%DJ <b>$login_e</b> puszcza%'
            ")->fetch_assoc();
            return $r ? (int)$r['c'] : 0;

        case 'dni_w_klubie':
            $r = $polaczenie->query("SELECT COUNT(DISTINCT dzien) AS c FROM klub_dni_wizyt WHERE gracz_id=$g")->fetch_assoc();
            return (int)$r['c'];

        case 'roznych_strojow':
            $r = $polaczenie->query("SELECT COUNT(DISTINCT stroj_nazwa) AS c FROM klub_uzycia_strojow WHERE gracz_id=$g")->fetch_assoc();
            return (int)$r['c'];

        default:
            return 0;
    }
}

/**
 * Loguje użycie stroju (do odznaki Modny).
 * Wywoływane przy /mood w stroju: X
 */
function klub_log_stroj($polaczenie, $gracz_id, $stroj_nazwa) {
    $g = (int)$gracz_id;
    $s = $polaczenie->real_escape_string(mb_substr($stroj_nazwa, 0, 80));
    $polaczenie->query("INSERT IGNORE INTO klub_uzycia_strojow (gracz_id, stroj_nazwa) VALUES ($g, '$s')");
}

/**
 * Loguje użycie drinka (do odznaki Smakosz).
 * Wywoływane przy /bar zamów [drink]
 */
function klub_log_drink($polaczenie, $gracz_id, $drink_id) {
    $g = (int)$gracz_id;
    $d = (int)$drink_id;
    if ($d > 0) {
        $polaczenie->query("INSERT IGNORE INTO klub_uzycia_drinkow (gracz_id, drink_id) VALUES ($g, $d)");
    }
}

/**
 * Loguje dzień wizyty w klubie (do odznaki Stały gość).
 * Wywoływane raz przy każdym wejściu do dowolnej sali.
 */
function klub_log_dzien_wizyty($polaczenie, $gracz_id) {
    $g = (int)$gracz_id;
    $polaczenie->query("INSERT IGNORE INTO klub_dni_wizyt (gracz_id, dzien) VALUES ($g, CURDATE())");
}

/**
 * Rozpoczyna sesję sportową (basen/siłownia).
 * Sprawdza czy istnieje aktywna sesja (czas_konca IS NULL) — jeśli
 * tak, nie tworzy duplikatu.
 */
function klub_start_sesji_sportowej($polaczenie, $gracz_id, $sala) {
    if (!in_array($sala, ['basen','silownia'], true)) return;
    $g = (int)$gracz_id;
    $s = $polaczenie->real_escape_string($sala);
    // Aktywne sesja w ostatniej godzinie?
    $istn = $polaczenie->query("
        SELECT id FROM klub_sesje_sportowe
        WHERE gracz_id=$g AND sala='$s' AND czas_konca IS NULL
          AND czas_startu >= NOW() - INTERVAL 2 HOUR
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    if (!$istn) {
        $polaczenie->query("INSERT INTO klub_sesje_sportowe (gracz_id, sala) VALUES ($g, '$s')");
    }
}

/**
 * Kończy aktywną sesję (gdy gracz wychodzi z sali).
 */
function klub_koniec_sesji_sportowej($polaczenie, $gracz_id, $sala) {
    if (!in_array($sala, ['basen','silownia'], true)) return;
    $g = (int)$gracz_id;
    $s = $polaczenie->real_escape_string($sala);
    $polaczenie->query("
        UPDATE klub_sesje_sportowe
        SET czas_konca = NOW()
        WHERE gracz_id=$g AND sala='$s' AND czas_konca IS NULL
    ");
}

}  // !function_exists