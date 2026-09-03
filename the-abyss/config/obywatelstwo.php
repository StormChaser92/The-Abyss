<?php
// ============================================================
//  THE ABYSS - SYSTEM OBYWATELSTWA, PASZPORTOW I WIZ
//  Etap 2 - jedno zrodlo prawdy o prawie wjazdu
//  Plik: config/obywatelstwo.php
// ============================================================
//
//  ZALOZENIA (sprawdz przy wklejaniu):
//  1. Klucze krajow sa identyczne z kluczami $POCHODZENIA_DANE
//     (POLSKA, USA, WLOCHY, ZEA ...). Obywatelstwo startowe = pochodzenie.
//  2. Klucze miast to klucze z $MIASTA_DANE (UPPERCASE, ASCII,
//     podkreslnik zamiast spacji: NEW_YORK, ABU_DHABI).
//     Jesli u Ciebie sa inne - popraw tylko $WYMAGANIA_MIAST nizej.
//  3. Warstwa DB idzie przez PDO ($pdo z db.php). Wariant mysqli
//     jest gotowy na dole pliku, wystarczy przelaczyc.
// ============================================================

require_once __DIR__ . '/pochodzenia.php';
require_once __DIR__ . '/rp_helpers.php';

// ------------------------------------------------------------
//  1. STREFY BEZWIZOWE
// ------------------------------------------------------------
// Lot wewnatrz wlasnej strefy nie wymaga wizy - tylko waznego paszportu.

$STREFY_BEZWIZOWE = [
    'KONTYNENT' => [
        'nazwa'  => 'Strefa Kontynentalna',
        'skrot'  => 'SK',
        'kolor'  => 'var(--neon-cyan)',
        'opis'   => 'Stary kontynent zszyty wspolnym rejestrem biometrycznym. Granice istnieja tylko w papierach.',
        'kraje'  => ['POLSKA','NIEMCY','FRANCJA','WLOCHY','HISZPANIA','SZWECJA','NORWEGIA','DANIA','CZECHY','BELGIA'],
    ],
    'ANGLOSFERA' => [
        'nazwa'  => 'Pakt Anglosaski',
        'skrot'  => 'PA',
        'kolor'  => 'var(--neon-gold)',
        'opis'   => 'Wspolny wywiad, wspolne bazy danych, wspolna lista osob niepozadanych.',
        'kraje'  => ['USA','UK','KANADA','AUSTRALIA'],
    ],
    'WSCHOD' => [
        'nazwa'  => 'Porozumienie Wschodnie',
        'skrot'  => 'PW',
        'kolor'  => 'var(--neon-ember)',
        'opis'   => 'Umowa handlowa, ktora przy okazji otworzyla korytarze osobowe.',
        'kraje'  => ['JAPONIA','CHINY','INDIE'],
    ],
    'POLUDNIE' => [
        'nazwa'  => 'Korytarz Poludniowy',
        'skrot'  => 'KP',
        'kolor'  => 'var(--neon-green)',
        'opis'   => 'Ruch bezwizowy wymuszony przez skale migracji zarobkowej.',
        'kraje'  => ['BRAZYLIA','MEKSYK'],
    ],
];

// ROSJA i ZEA celowo poza strefami - kazdy wjazd wymaga wizy.

// ------------------------------------------------------------
//  2. TYPY WIZ
// ------------------------------------------------------------

$TYPY_WIZ = [
    'TURYSTYCZNA' => [
        'nazwa'          => 'Wiza turystyczna',
        'ikona'          => '🛄',
        'koszt_bazowy'   => 2500,
        'dni'            => 7,
        'poziom_min'     => 1,
        'prawo_pracy'    => false,
        'prawo_handlu'   => false,
        'odmowa_bazowa'  => 0,   // procent, modyfikowany przez tier kraju
        'opis'           => 'Tydzien na terenie kraju. Bez prawa do pracy zarobkowej i handlu na rynku lokalnym.',
    ],
    'BIZNESOWA' => [
        'nazwa'          => 'Wiza biznesowa',
        'ikona'          => '💼',
        'koszt_bazowy'   => 9000,
        'dni'            => 30,
        'poziom_min'     => 5,
        'prawo_pracy'    => true,
        'prawo_handlu'   => true,
        'odmowa_bazowa'  => 5,
        'opis'           => 'Miesiac pobytu z pelnym prawem do pracy w zawodzie i handlu na rynku lokalnym.',
    ],
    'REZYDENCJA' => [
        'nazwa'          => 'Karta stalego pobytu',
        'ikona'          => '🏛️',
        'koszt_bazowy'   => 45000,
        'dni'            => null, // bezterminowa
        'poziom_min'     => 12,
        'prawo_pracy'    => true,
        'prawo_handlu'   => true,
        'odmowa_bazowa'  => 10,
        'dni_pobytu_min' => 15,   // laczny pobyt w tym kraju
        'rep_min'        => ['wladze' => 1],
        'opis'           => 'Bezterminowe prawo pobytu. Krok przed naturalizacja - drugim obywatelstwem.',
    ],
];

// ------------------------------------------------------------
//  3. TIERY KRAJOW - mnoznik kosztu i trudnosc wjazdu
// ------------------------------------------------------------

$TIERY_KRAJOW = [
    'ZEA'       => ['mult' => 2.20, 'odmowa' => 15, 'opis' => 'Kontrola majatkowa przy wjezdzie.'],
    'USA'       => ['mult' => 1.70, 'odmowa' => 12, 'opis' => 'Rozmowa w konsulacie, skan biometryczny, weryfikacja krzyzowa.'],
    'JAPONIA'   => ['mult' => 1.50, 'odmowa' => 8,  'opis' => 'Wymagany list polecajacy lub czysta kartoteka.'],
    'CHINY'     => ['mult' => 1.45, 'odmowa' => 10, 'opis' => 'Pelna rejestracja urzadzen elektronicznych.'],
    'ROSJA'     => ['mult' => 1.40, 'odmowa' => 14, 'opis' => 'Zaproszenie od podmiotu krajowego lub bardzo dobre uklady.'],
    'UK'        => ['mult' => 1.35, 'odmowa' => 7,  'opis' => 'Osobny rejestr, osobna kolejka, osobne oplaty.'],
    'AUSTRALIA' => ['mult' => 1.30, 'odmowa' => 6,  'opis' => 'Kwarantanna biologiczna przy ladowaniu.'],
    'KANADA'    => ['mult' => 1.15, 'odmowa' => 3,  'opis' => 'Procedura uproszczona, dlugie terminy.'],
    'INDIE'     => ['mult' => 1.10, 'odmowa' => 5,  'opis' => 'Wiza elektroniczna, przetwarzanie automatyczne.'],
    'BRAZYLIA'  => ['mult' => 1.05, 'odmowa' => 5,  'opis' => 'Formalnosci minimalne, egzekucja wybiorcza.'],
    'MEKSYK'    => ['mult' => 1.00, 'odmowa' => 4,  'opis' => 'Oplata administracyjna i odcisk kciuka.'],
];
// Kraje spoza listy: mult 1.00, odmowa 3.

// ------------------------------------------------------------
//  4. PASZPORT
// ------------------------------------------------------------

$PASZPORT = [
    'waznosc_dni'    => 90,
    'koszt_wydania'  => 1200,
    'koszt_ekspres'  => 3500,  // bez kolejki, wydanie natychmiastowe
    'kolejka_godzin' => 6,     // tryb zwykly - paszport gotowy po X godzinach
];

// ------------------------------------------------------------
//  5. BONUSY POCHODZEN DLA SYSTEMU WIZ
// ------------------------------------------------------------
// Celowo NIE dotykam $POCHODZENIA_DANE - overlay trzyma cala logike
// Etapu 2 w jednym pliku. Klucz = klucz pochodzenia.

$WIZY_BONUSY_POCHODZEN = [
    'KANADA' => [   // Maple Diplomacy
        'wiza_koszt_mult'        => 0.80,
        'wiza_odmowa_szansa_abs' => -6,
        'bezwizowy_dodatkowy'    => ['MEKSYK'],
    ],
    'ZEA' => [      // Zlota Klatwa - pieniadz otwiera bramki
        'wiza_koszt_mult'        => 0.70,
        'wiza_czas_mult'         => 1.20,
    ],
    'WLOCHY' => [   // La Famiglia - kontakty zalatwiaja przedluzenie
        'wiza_czas_mult'         => 1.25,
        'wiza_odmowa_szansa_abs' => -4,
    ],
    'UK' => [       // Stiff Upper Lip
        'wiza_koszt_mult'        => 0.90,
        'wiza_czas_mult'         => 1.15,
    ],
    'USA' => [
        'wiza_koszt_mult'        => 0.90,
        'bezwizowy_dodatkowy'    => ['JAPONIA','MEKSYK'],
    ],
    'JAPONIA' => [
        'bezwizowy_dodatkowy'    => ['USA'],
    ],
    'NIEMCY' => [   // Ordnung Muss Sein - papiery zawsze w porzadku
        'paszport_koszt_mult'    => 0.85,
        'wiza_odmowa_szansa_abs' => -5,
    ],
    'CZECHY' => [   // Szwejk - urzednik nie wie, co z nim zrobic
        'wiza_odmowa_szansa_abs' => -10,
        'wiza_koszt_mult'        => 0.95,
    ],
    'INDIE' => [    // Dharma
        'wiza_koszt_mult'        => 0.85,
        'bezwizowy_dodatkowy'    => ['UK','ZEA'],
    ],
    'ROSJA' => [    // Niedzwiedz - wszedzie patrza krzywo
        'wiza_odmowa_szansa_abs' => 8,
        'wiza_koszt_mult'        => 1.10,
    ],
    'CHINY' => [
        'bezwizowy_dodatkowy'    => ['ROSJA'],
    ],
    'MEKSYK' => [
        'bezwizowy_dodatkowy'    => ['USA'],
    ],
];

// ------------------------------------------------------------
//  6. MIASTA O OGRANICZONYM DOSTEPIE
// ------------------------------------------------------------
// Wiza daje wjazd do KRAJU. Te miasta wymagaja dodatkowo pozycji.
// Reputacja liczona przez reputacja_grupowa() z rp_helpers.php.

$WYMAGANIA_MIAST = [
    'DUBAI' => [
        'reputacja' => ['elita' => 3],
        'gotowka'   => 25000,
        'opis'      => 'Wjazd do dzielnic wiezowych wymaga potwierdzenia zdolnosci finansowej i referencji z gornej polki.',
    ],
    'ABU_DHABI' => [
        'reputacja' => ['elita' => 2],
        'opis'      => 'Miasto administracyjne. Kontrola dostepu na poziomie lotniska.',
    ],
    'BEIJING' => [
        'reputacja' => ['wladze' => 3],
        'opis'      => 'Strefa rzadowa. Bez czystej kartoteki i zgody administracji nie zejdziesz z pokladu.',
    ],
    'MOSCOW' => [
        'reputacja' => ['syndykat' => 2],
        'opis'      => 'Miasto podzielone miedzy struktury. Bez poreczenia jednej z nich jestes tylko celem.',
    ],
    'MONACO_PLACEHOLDER' => [], // wzor do kopiowania
];
unset($WYMAGANIA_MIAST['MONACO_PLACEHOLDER']);

// ============================================================
//  HELPERY
// ============================================================

/**
 * Wszystkie obywatelstwa gracza: startowe (pochodzenie) + naturalizacje.
 * @return string[] klucze krajow
 */
function obywatelstwa_gracza(array $gracz): array {
    $lista = [];
    if (!empty($gracz['pochodzenie'])) {
        $lista[] = strtoupper($gracz['pochodzenie']);
    }
    if (!empty($gracz['obywatelstwa'])) {
        foreach (explode(',', $gracz['obywatelstwa']) as $k) {
            $k = strtoupper(trim($k));
            if ($k !== '' && !in_array($k, $lista, true)) $lista[] = $k;
        }
    }
    return $lista;
}

/** Klucz strefy dla kraju albo null. */
function strefa_kraju(string $kraj): ?string {
    global $STREFY_BEZWIZOWE;
    $kraj = strtoupper($kraj);
    foreach ($STREFY_BEZWIZOWE as $klucz => $strefa) {
        if (in_array($kraj, $strefa['kraje'], true)) return $klucz;
    }
    return null;
}

/** Wartosc bonusu wizowego pochodzenia. */
function wiza_bonus(array $gracz, string $klucz, $domyslna = 1.0) {
    global $WIZY_BONUSY_POCHODZEN;
    $p = strtoupper($gracz['pochodzenie'] ?? '');
    return $WIZY_BONUSY_POCHODZEN[$p][$klucz] ?? $domyslna;
}

/**
 * Czy gracz wjezdza do kraju bez wizy?
 * Bezwizowo, gdy: obywatelstwo tego kraju, wspolna strefa,
 * albo kraj na liscie bezwizowy_dodatkowy pochodzenia.
 */
function czy_bezwizowo(array $gracz, string $kraj_docelowy): bool {
    $kraj_docelowy = strtoupper($kraj_docelowy);
    $moje = obywatelstwa_gracza($gracz);

    if (in_array($kraj_docelowy, $moje, true)) return true;

    $strefa_cel = strefa_kraju($kraj_docelowy);
    if ($strefa_cel !== null) {
        foreach ($moje as $kraj_moj) {
            if (strefa_kraju($kraj_moj) === $strefa_cel) return true;
        }
    }

    $dodatkowe = wiza_bonus($gracz, 'bezwizowy_dodatkowy', []);
    if (is_array($dodatkowe) && in_array($kraj_docelowy, $dodatkowe, true)) return true;

    return false;
}

/** Koszt wizy po mnoznikach kraju i pochodzenia. */
function koszt_wizy(array $gracz, string $kraj, string $typ): int {
    global $TYPY_WIZ, $TIERY_KRAJOW;
    if (!isset($TYPY_WIZ[$typ])) return 0;

    $baza = $TYPY_WIZ[$typ]['koszt_bazowy'];
    $mult_kraj = $TIERY_KRAJOW[strtoupper($kraj)]['mult'] ?? 1.00;
    $mult_poch = (float) wiza_bonus($gracz, 'wiza_koszt_mult', 1.0);

    return (int) max(500, round($baza * $mult_kraj * $mult_poch));
}

/** Dlugosc wizy w dniach po bonusach (null = bezterminowa). */
function dni_wizy(array $gracz, string $typ): ?int {
    global $TYPY_WIZ;
    $dni = $TYPY_WIZ[$typ]['dni'] ?? null;
    if ($dni === null) return null;
    return (int) round($dni * (float) wiza_bonus($gracz, 'wiza_czas_mult', 1.0));
}

/** Szansa odmowy w procentach [0..60]. Reputacja wladze obniza o 3 pkt za poziom. */
function szansa_odmowy(array $gracz, string $kraj, string $typ): int {
    global $TYPY_WIZ, $TIERY_KRAJOW;

    $baza  = $TYPY_WIZ[$typ]['odmowa_bazowa'] ?? 0;
    $baza += $TIERY_KRAJOW[strtoupper($kraj)]['odmowa'] ?? 3;
    $baza += (int) wiza_bonus($gracz, 'wiza_odmowa_szansa_abs', 0);

    if (function_exists('reputacja_grupowa')) {
        $rep = reputacja_grupowa($gracz);
        $baza -= 3 * (int) ($rep['wladze'] ?? 0);
    }

    return (int) max(0, min(60, $baza));
}

/** Aktywna wiza gracza do kraju albo null. */
function pobierz_wize(PDO $pdo, int $gracz_id, string $kraj): ?array {
    $sql = "SELECT * FROM wizy_gracza
            WHERE gracz_id = :g AND kraj = :k AND status = 'AKTYWNA'
              AND (wygasa IS NULL OR wygasa > NOW())
            ORDER BY (wygasa IS NULL) DESC, wygasa DESC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':g' => $gracz_id, ':k' => strtoupper($kraj)]);
    $w = $st->fetch(PDO::FETCH_ASSOC);
    return $w ?: null;
}

/** Czy paszport wazny. */
function paszport_wazny(array $gracz): bool {
    if (empty($gracz['paszport_wazny_do'])) return false;
    return strtotime($gracz['paszport_wazny_do']) > time();
}

/**
 * GLOWNA BRAMKA - czy gracz moze polediec do miasta.
 * Zwraca ['ok'=>bool, 'powod'=>string, 'kod'=>string, 'wiza'=>?array]
 * Kody: OK_OBYWATEL, OK_BEZWIZOWO, OK_WIZA, BRAK_PASZPORTU,
 *       BRAK_WIZY, BRAK_REPUTACJI, BRAK_GOTOWKI
 */
function czy_moze_wjechac(PDO $pdo, array $gracz, string $kraj, string $miasto = ''): array {
    global $WYMAGANIA_MIAST, $TIERY_KRAJOW;

    $kraj   = strtoupper($kraj);
    $miasto = strtoupper($miasto);
    $wiza   = null;

    // --- warstwa 1: kraj ---
    if (in_array($kraj, obywatelstwa_gracza($gracz), true)) {
        $kod = 'OK_OBYWATEL';
    } else {
        if (!paszport_wazny($gracz)) {
            return ['ok' => false, 'kod' => 'BRAK_PASZPORTU', 'wiza' => null,
                    'powod' => 'Twoj paszport jest niewazny. Odnow go w Urzedzie Imigracyjnym.'];
        }
        if (czy_bezwizowo($gracz, $kraj)) {
            $kod = 'OK_BEZWIZOWO';
        } else {
            $wiza = pobierz_wize($pdo, (int) $gracz['id'], $kraj);
            if ($wiza === null) {
                $powod = $TIERY_KRAJOW[$kraj]['opis'] ?? 'Wjazd wylacznie na podstawie waznej wizy.';
                return ['ok' => false, 'kod' => 'BRAK_WIZY', 'wiza' => null,
                        'powod' => 'Brak waznej wizy. ' . $powod];
            }
            $kod = 'OK_WIZA';
        }
    }

    // --- warstwa 2: miasto ---
    if ($miasto !== '' && isset($WYMAGANIA_MIAST[$miasto])) {
        $wym = $WYMAGANIA_MIAST[$miasto];

        if (!empty($wym['reputacja']) && function_exists('reputacja_grupowa')) {
            $rep = reputacja_grupowa($gracz);
            foreach ($wym['reputacja'] as $grupa => $prog) {
                if ((int) ($rep[$grupa] ?? 0) < $prog) {
                    return ['ok' => false, 'kod' => 'BRAK_REPUTACJI', 'wiza' => $wiza,
                            'powod' => 'Wymagana reputacja ' . strtoupper($grupa) . ' na poziomie ' . $prog
                                     . '. ' . ($wym['opis'] ?? '')];
                }
            }
        }
        if (!empty($wym['gotowka']) && (int) ($gracz['gotowka'] ?? 0) < $wym['gotowka']) {
            return ['ok' => false, 'kod' => 'BRAK_GOTOWKI', 'wiza' => $wiza,
                    'powod' => 'Wymagane potwierdzenie srodkow: ' . number_format($wym['gotowka'], 0, ',', ' ') . '$.'];
        }
    }

    return ['ok' => true, 'kod' => $kod, 'wiza' => $wiza, 'powod' => ''];
}

/** Czy gracz ma prawo pracy/handlu w kraju, w ktorym stoi. */
function prawo_pracy(PDO $pdo, array $gracz, string $kraj): bool {
    global $TYPY_WIZ;
    $kraj = strtoupper($kraj);
    if (in_array($kraj, obywatelstwa_gracza($gracz), true)) return true;
    if (czy_bezwizowo($gracz, $kraj)) return true;
    $w = pobierz_wize($pdo, (int) $gracz['id'], $kraj);
    if (!$w) return false;
    return (bool) ($TYPY_WIZ[$w['typ']]['prawo_pracy'] ?? false);
}

/** Czytelny status wizy do UI: ikona, kolor, tekst. */
function status_wizy_opis(?array $wiza): array {
    if ($wiza === null) {
        return ['ikona' => '⛔', 'kolor' => 'var(--neon-red)', 'tekst' => 'BRAK WIZY'];
    }
    if ($wiza['wygasa'] === null) {
        return ['ikona' => '🏛️', 'kolor' => 'var(--neon-gold)', 'tekst' => 'STALY POBYT'];
    }
    $dni = (int) ceil((strtotime($wiza['wygasa']) - time()) / 86400);
    if ($dni <= 1) {
        return ['ikona' => '⚠️', 'kolor' => 'var(--neon-red)', 'tekst' => 'WYGASA DZIS'];
    }
    if ($dni <= 3) {
        return ['ikona' => '⏳', 'kolor' => 'var(--neon-ember)', 'tekst' => 'ZOSTALO ' . $dni . ' DNI'];
    }
    return ['ikona' => '✅', 'kolor' => 'var(--neon-green)', 'tekst' => 'WAZNA ' . $dni . ' DNI'];
}

/** Rejestruje pobyt w kraju - baza pod wymog dni do rezydencji. */
function zarejestruj_pobyt(PDO $pdo, int $gracz_id, string $kraj): void {
    $sql = "INSERT INTO pobyt_kraje (gracz_id, kraj, dni_pobytu, ostatni_wjazd)
            VALUES (:g, :k, 1, NOW())
            ON DUPLICATE KEY UPDATE
                dni_pobytu    = dni_pobytu + 1,
                ostatni_wjazd = NOW()";
    $pdo->prepare($sql)->execute([':g' => $gracz_id, ':k' => strtoupper($kraj)]);
}

/** Laczny pobyt gracza w danym kraju (dni). */
function dni_pobytu(PDO $pdo, int $gracz_id, string $kraj): int {
    $st = $pdo->prepare("SELECT dni_pobytu FROM pobyt_kraje WHERE gracz_id = :g AND kraj = :k");
    $st->execute([':g' => $gracz_id, ':k' => strtoupper($kraj)]);
    return (int) $st->fetchColumn();
}

/**
 * Sprawdza, czy gracz spelnia warunki formalne do zlozenia wniosku.
 * Zwraca ['ok'=>bool, 'braki'=>string[]]
 */
function warunki_wniosku(PDO $pdo, array $gracz, string $kraj, string $typ): array {
    global $TYPY_WIZ;
    $braki = [];
    $def = $TYPY_WIZ[$typ] ?? null;
    if (!$def) return ['ok' => false, 'braki' => ['Nieznany typ wizy.']];

    if ((int) ($gracz['poziom'] ?? 1) < $def['poziom_min']) {
        $braki[] = 'Wymagany poziom ' . $def['poziom_min'] . '.';
    }
    if (!paszport_wazny($gracz)) {
        $braki[] = 'Wymagany wazny paszport.';
    }
    if ((int) ($gracz['gotowka'] ?? 0) < koszt_wizy($gracz, $kraj, $typ)) {
        $braki[] = 'Brak srodkow na oplate konsularna.';
    }
    if (!empty($def['dni_pobytu_min'])) {
        $ile = dni_pobytu($pdo, (int) $gracz['id'], $kraj);
        if ($ile < $def['dni_pobytu_min']) {
            $braki[] = 'Wymagany laczny pobyt ' . $def['dni_pobytu_min'] . ' dni (masz ' . $ile . ').';
        }
    }
    if (!empty($def['rep_min']) && function_exists('reputacja_grupowa')) {
        $rep = reputacja_grupowa($gracz);
        foreach ($def['rep_min'] as $grupa => $prog) {
            if ((int) ($rep[$grupa] ?? 0) < $prog) {
                $braki[] = 'Wymagana reputacja ' . strtoupper($grupa) . ' >= ' . $prog . '.';
            }
        }
    }

    return ['ok' => empty($braki), 'braki' => $braki];
}