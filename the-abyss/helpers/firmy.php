<?php
// ════════════════════════════════════════════════════════════════════
// THE ABYSS — HELPER FIRMY
// ════════════════════════════════════════════════════════════════════
// Centralny moduł obsługi firm. Definiuje branże, dostarcza funkcje
// pomocnicze do pobierania danych firmy, pracowników, sprawdzania
// uprawnień i logowania operacji.
//
// require_once "helpers/firmy.php";  // w game.php po require_once "db.php"
//
// API (Faza 1A.1):
//   definicja_branz()                  → array — 15 branż z opisami
//   nazwa_branzy($kod)                 → string — czytelna nazwa
//   pobierz_firme($id_firmy)           → array|null
//   pobierz_firme_gracza($id_gracza)   → array|null
//   czy_w_firmie($id_gracza)           → bool
//   czy_wlasciciel($id_gracza, $id_firmy)  → bool
//   czy_manager($id_gracza, $id_firmy)     → bool
//   ma_uprawnienia($gracz, $firma_id, $perm)  → bool
//   policz_pracownikow($id_firmy)      → int
//   pracownicy_firmy($id_firmy)        → array (Faza 1A.2)
//   dodaj_kronike($firma_id, $typ, $tytul, $opis, $autor_id, $publiczna)
// ════════════════════════════════════════════════════════════════════

if (!defined('FIRMY_HELPER_LOADED')) {
    define('FIRMY_HELPER_LOADED', 1);

// Stałe konfiguracji
define('FIRMA_KOSZT_REJESTRACJI', 500000);
define('FIRMA_MAX_PRACOWNIKOW',   10);
define('FIRMA_LIMIT_OPIS_RP',     5000);
define('FIRMA_LIMIT_HISTORIA',    10000);
define('FIRMA_LIMIT_MOTYWACJA',   1000);
define('FIRMA_LIMIT_AKTYWNYCH_APLIKACJI', 5);
define('FIRMA_COOLDOWN_APLIKACJI_DNI', 7);

// ────────────────────────────────────────────────────────────────────
// definicja_branz() — katalog 15 branż MVP.
// Klucz tablicy = `branza_kod` w bazie.
// ────────────────────────────────────────────────────────────────────
function definicja_branz() {
    return [
        'klub_nocny' => [
            'nazwa' => 'Klub Nocny / Bar',
            'ikona' => '🍸',
            'kolor' => '#ff1744',
            'opis_skrocony' => 'Drinki, muzyka i półświatek po zmroku.',
            'opis' => 'Bar, klub nocny lub speakeasy. Klimat alkoholu, tłumu i nocnego życia. Zatrudnia barmanów, ochroniarzy, DJ-ów. Klienci szukają rozrywki, drinków, prywatnych pokoi VIP.',
        ],
        'agencja_ochrony' => [
            'nazwa' => 'Agencja Ochrony',
            'ikona' => '🛡️',
            'kolor' => '#4ad6ff',
            'opis_skrocony' => 'Eskorty, prywatni detektywi, ochrona VIP-ów.',
            'opis' => 'Profesjonalna ochrona — eskorty, śledztwa prywatne, zabezpieczenie eventów. Zatrudnia byłych żołnierzy, kryminologów, detektywów. Klienci szukają dyskrecji i skuteczności.',
        ],
        'handel' => [
            'nazwa' => 'Handel i Usługi',
            'ikona' => '🏪',
            'kolor' => '#5aff9a',
            'opis_skrocony' => 'Hurtownia, sklep, ogólny biznes handlowy.',
            'opis' => 'Wszystko od zwykłych sklepów po hurtownie z towarem niekoniecznie legalnym. Elastyczna branża — od sieci spożywczej po pasaże handlowe na Manhattanie.',
        ],
        'klinika' => [
            'nazwa' => 'Prywatna Klinika',
            'ikona' => '💉',
            'kolor' => '#ff7a3d',
            'opis_skrocony' => 'Medycyna VIP, dyskretne zabiegi, ratunek.',
            'opis' => 'Prywatna klinika dla tych, którzy nie chcą trafić do publicznego szpitala. Operacje VIP, ekstrakcje kul, dyskretne diagnostyki. Zatrudnia lekarzy, pielęgniarki, anestezjologów.',
        ],
        'kancelaria' => [
            'nazwa' => 'Kancelaria Prawna',
            'ikona' => '⚖️',
            'kolor' => '#ffd700',
            'opis_skrocony' => 'Wybielanie kartoteki, kruczki, obrona w sądzie.',
            'opis' => 'Adwokaci dla bogatych, znających słabości systemu prawnego. Sporządzają kontrakty, bronią w sądzie, czyszczą kartoteki. Klienci to gracze z problemami z policją lub konkurencją.',
        ],
        'studio_arch' => [
            'nazwa' => 'Studio Architektoniczne',
            'ikona' => '🏛️',
            'kolor' => '#4ad6ff',
            'opis_skrocony' => 'Projekty willi, klubów, kryjówek.',
            'opis' => 'Architekci i projektanci wnętrz. Od luksusowych penthouseów po ukryte podziemne kompleksy. Zatrudnia architektów, designerów, inżynierów konstrukcji.',
        ],
        'agencja_reklamowa' => [
            'nazwa' => 'Agencja Reklamowa',
            'ikona' => '📢',
            'kolor' => '#ff1744',
            'opis_skrocony' => 'Kampanie, branding, plotki sponsorowane.',
            'opis' => 'Wymyślają hasła, kupują billboardy, organizują kampanie wirusowe. W mieście, gdzie wizerunek jest walutą, agencja reklamowa może podnieść lub pogrzebać kogokolwiek.',
        ],
        'galeria' => [
            'nazwa' => 'Galeria Sztuki',
            'ikona' => '🖼️',
            'kolor' => '#ffd700',
            'opis_skrocony' => 'Aukcje, wystawy, autentykacje obrazów.',
            'opis' => 'Wystawy, prywatne aukcje, ekspertyzy autentyczności. Gdzie spotykają się kolekcjonerzy, krytycy i ci którzy chcą wyprać pieniądze przez sztukę.',
        ],
        'studio_nagran' => [
            'nazwa' => 'Studio Nagraniowe',
            'ikona' => '🎙️',
            'kolor' => '#ff7a3d',
            'opis_skrocony' => 'Albumy, podcasty, ścieżki dźwiękowe.',
            'opis' => 'Profesjonalna produkcja muzyki i audio. Albumy artystów, podcasty, jingle reklamowe, ścieżki dźwiękowe do filmów. Zatrudnia inżynierów dźwięku, producentów, sesyjnych muzyków.',
        ],
        'restauracja' => [
            'nazwa' => 'Restauracja / Catering',
            'ikona' => '🍽️',
            'kolor' => '#ff1744',
            'opis_skrocony' => 'Premiery, bankiety, eleganckie kolacje.',
            'opis' => 'Od bistro po restauracje z gwiazdkami Michelin. Catering na premiery, prywatne kolacje mafii, bankiety. Zatrudnia kucharzy, kelnerów, sommelierów.',
        ],
        'biuro_makl' => [
            'nazwa' => 'Biuro Maklerskie',
            'ikona' => '📈',
            'kolor' => '#5aff9a',
            'opis_skrocony' => 'Inwestycje, fuzje, doradztwo finansowe.',
            'opis' => 'Doradztwo inwestycyjne, fuzje, restrukturyzacje. W mieście, gdzie pieniądz jest jedynym językiem, makler to spowiednik bogaczy. Zatrudnia ekonomistów, analityków, prawników podatkowych.',
        ],
        'pracownia_mody' => [
            'nazwa' => 'Pracownia Mody',
            'ikona' => '👗',
            'kolor' => '#ff7a3d',
            'opis_skrocony' => 'Sesje, kolekcje, kostiumy do filmów.',
            'opis' => 'Projektanci ubrań, atelier krawieckie, butiki. Od strojów na czerwony dywan po taktyczne uniformy syndykatów. Zatrudnia projektantów, krawców, stylistów.',
        ],
        'fabryka' => [
            'nazwa' => 'Fabryka',
            'ikona' => '🏭',
            'kolor' => '#888888',
            'opis_skrocony' => 'Produkcja masowa, surowce, montaż.',
            'opis' => 'Hala produkcyjna — komponenty, wyposażenie, zaopatrzenie hurtowe. Zatrudnia operatorów maszyn, kierowników zmiany, kontrolerów jakości. Brudne ręce, czysty zysk.',
        ],
        'studio_rezys' => [
            'nazwa' => 'Studio Reżyserskie',
            'ikona' => '🎬',
            'kolor' => '#ffd700',
            'opis_skrocony' => 'Filmy, seriale, reklamy z aktorami.',
            'opis' => 'Produkcja filmowa — od reklam po pełnometrażowe filmy. Zatrudnia reżyserów, operatorów, aktorów, scenarzystów. Tu rodzą się gwiazdy Manhattanu — i tu są niszczone.',
        ],
        'studio_foto' => [
            'nazwa' => 'Studio Fotograficzne',
            'ikona' => '📸',
            'kolor' => '#4ad6ff',
            'opis_skrocony' => 'Sesje, eventy, portrety prasowe.',
            'opis' => 'Profesjonalna fotografia — sesje modowe, korporacyjne portrety, eventy, foto reportaże. Czasem także: paparazzi i zlecenia "dyskretne". Zatrudnia fotografów, retuszerów, asystentów planu.',
        ],
    ];
}

// ────────────────────────────────────────────────────────────────────
// nazwa_branzy($kod) — czytelna nazwa branży albo "Nieznana"
// ────────────────────────────────────────────────────────────────────
function nazwa_branzy($kod) {
    $b = definicja_branz();
    return isset($b[$kod]) ? $b[$kod]['nazwa'] : 'Nieznana branża';
}

// ────────────────────────────────────────────────────────────────────
// dane_branzy($kod) — pełen rekord branży albo null
// ────────────────────────────────────────────────────────────────────
function dane_branzy($kod) {
    $b = definicja_branz();
    return $b[$kod] ?? null;
}

// ────────────────────────────────────────────────────────────────────
// pobierz_firme($id_firmy) — pełny rekord firmy z liczbą pracowników
// ────────────────────────────────────────────────────────────────────
function pobierz_firme($id_firmy) {
    global $polaczenie;
    $id_firmy = (int)$id_firmy;
    if ($id_firmy <= 0) return null;

    $r = $polaczenie->query("
        SELECT f.*, g.login AS wlasciciel_login,
               (SELECT COUNT(*) FROM gracze gp WHERE gp.id_firmy = f.id) AS pracownikow
        FROM firmy f
        LEFT JOIN gracze g ON g.id = f.wlasciciel_id
        WHERE f.id = $id_firmy
        LIMIT 1
    ");
    if (!$r || $r->num_rows === 0) return null;
    return $r->fetch_assoc();
}

// ────────────────────────────────────────────────────────────────────
// pobierz_firme_gracza($id_gracza) — firma w której gracz pracuje
// ────────────────────────────────────────────────────────────────────
function pobierz_firme_gracza($id_gracza) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    if ($id_gracza <= 0) return null;

    $r = $polaczenie->query("SELECT id_firmy FROM gracze WHERE id = $id_gracza");
    if (!$r || $r->num_rows === 0) return null;
    $row = $r->fetch_assoc();
    if (empty($row['id_firmy'])) return null;
    return pobierz_firme((int)$row['id_firmy']);
}

// ────────────────────────────────────────────────────────────────────
// czy_w_firmie($id_gracza) — czy gracz ma jakąkolwiek pracę
// ────────────────────────────────────────────────────────────────────
function czy_w_firmie($id_gracza) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    if ($id_gracza <= 0) return false;
    $r = $polaczenie->query("SELECT id_firmy FROM gracze WHERE id = $id_gracza");
    if (!$r || $r->num_rows === 0) return false;
    $row = $r->fetch_assoc();
    return !empty($row['id_firmy']);
}

// ────────────────────────────────────────────────────────────────────
// czy_wlasciciel($id_gracza, $id_firmy)
// ────────────────────────────────────────────────────────────────────
function czy_wlasciciel($id_gracza, $id_firmy) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    $id_firmy  = (int)$id_firmy;
    if ($id_gracza <= 0 || $id_firmy <= 0) return false;
    $r = $polaczenie->query("SELECT 1 FROM firmy WHERE id = $id_firmy AND wlasciciel_id = $id_gracza LIMIT 1");
    return $r && $r->num_rows > 0;
}

// ────────────────────────────────────────────────────────────────────
// czy_manager($id_gracza, $id_firmy) — czy gracz jest managerem TEJ firmy
// (właściciel zwraca true, bo właściciel ma uprawnienia managera)
// ────────────────────────────────────────────────────────────────────
function czy_manager($id_gracza, $id_firmy) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    $id_firmy  = (int)$id_firmy;
    if ($id_gracza <= 0 || $id_firmy <= 0) return false;

    $r = $polaczenie->query("
        SELECT stanowisko_w_firmie FROM gracze
        WHERE id = $id_gracza AND id_firmy = $id_firmy
        LIMIT 1
    ");
    if (!$r || $r->num_rows === 0) return false;
    $row = $r->fetch_assoc();
    return in_array($row['stanowisko_w_firmie'], ['wlasciciel', 'manager']);
}

// ────────────────────────────────────────────────────────────────────
// czy_mg($id_gracza) — czy gracz to Mistrz Gry (admin uprawnień)
// ────────────────────────────────────────────────────────────────────
function czy_mg($id_gracza) {
    global $polaczenie;
    $id_gracza = (int)$id_gracza;
    if ($id_gracza <= 0) return false;
    $r = $polaczenie->query("SELECT is_mg FROM gracze WHERE id = $id_gracza");
    if (!$r || $r->num_rows === 0) return false;
    $row = $r->fetch_assoc();
    return !empty($row['is_mg']);
}

// ────────────────────────────────────────────────────────────────────
// policz_pracownikow($id_firmy) — liczba pracowników (wliczając właściciela)
// ────────────────────────────────────────────────────────────────────
function policz_pracownikow($id_firmy) {
    global $polaczenie;
    $id_firmy = (int)$id_firmy;
    if ($id_firmy <= 0) return 0;
    $r = $polaczenie->query("SELECT COUNT(*) c FROM gracze WHERE id_firmy = $id_firmy");
    if (!$r) return 0;
    return (int)$r->fetch_assoc()['c'];
}

// ────────────────────────────────────────────────────────────────────
// pracownicy_firmy($id_firmy) — lista pracowników (Faza 1A.2 będzie używać)
// ────────────────────────────────────────────────────────────────────
function pracownicy_firmy($id_firmy) {
    global $polaczenie;
    $id_firmy = (int)$id_firmy;
    if ($id_firmy <= 0) return [];

    $r = $polaczenie->query("
        SELECT id, login, avatar, klasa, profesja_fabularna, poziom,
               stanowisko_w_firmie, data_zatrudnienia, is_premium
        FROM gracze
        WHERE id_firmy = $id_firmy
        ORDER BY
          CASE stanowisko_w_firmie
            WHEN 'wlasciciel' THEN 1
            WHEN 'manager'    THEN 2
            ELSE 3
          END,
          data_zatrudnienia ASC
    ");
    if (!$r) return [];
    return $r->fetch_all(MYSQLI_ASSOC);
}

// ────────────────────────────────────────────────────────────────────
// dodaj_kronike() — wpis MG (lub właściciela dla typów własnych)
// ────────────────────────────────────────────────────────────────────
function dodaj_kronike($firma_id, $typ, $tytul, $opis, $autor_id, $publiczna = 1) {
    global $polaczenie;
    $firma_id  = (int)$firma_id;
    $autor_id  = (int)$autor_id;
    $publiczna = $publiczna ? 1 : 0;

    $dozwolone = ['zasluga','strata','wydarzenie','sesja','plotka'];
    if (!in_array($typ, $dozwolone)) return false;
    if ($firma_id <= 0 || $autor_id <= 0) return false;

    $tytul = trim($tytul);
    $opis  = trim($opis);
    if ($tytul === '' || $opis === '') return false;
    if (mb_strlen($tytul) > 160) $tytul = mb_substr($tytul, 0, 160);
    if (mb_strlen($opis)  > 5000) $opis  = mb_substr($opis,  0, 5000);

    $tytul_e = $polaczenie->real_escape_string($tytul);
    $opis_e  = $polaczenie->real_escape_string($opis);
    $typ_e   = $polaczenie->real_escape_string($typ);

    $polaczenie->query("
        INSERT INTO firmy_kronika
          (firma_id, typ, tytul, opis, dodal_id, widoczny_publicznie)
        VALUES
          ($firma_id, '$typ_e', '$tytul_e', '$opis_e', $autor_id, $publiczna)
    ");
    return $polaczenie->insert_id;
}

// ────────────────────────────────────────────────────────────────────
// kronika_firmy($firma_id, $publiczne_only) — wpisy z kroniki
// ────────────────────────────────────────────────────────────────────
function kronika_firmy($firma_id, $publiczne_only = true) {
    global $polaczenie;
    $firma_id = (int)$firma_id;
    if ($firma_id <= 0) return [];

    $warunek = $publiczne_only ? "AND k.widoczny_publicznie = 1" : "";
    $r = $polaczenie->query("
        SELECT k.*, g.login AS autor_login, g.is_mg AS autor_mg
        FROM firmy_kronika k
        LEFT JOIN gracze g ON g.id = k.dodal_id
        WHERE k.firma_id = $firma_id $warunek
        ORDER BY k.data_wpisu DESC
        LIMIT 50
    ");
    if (!$r) return [];
    return $r->fetch_all(MYSQLI_ASSOC);
}

// ────────────────────────────────────────────────────────────────────
// upload_obrazka_firmy() — wspólna logika uploadu logo i bannera.
//
// $field — nazwa pola w $_FILES (np. 'plik_logo' lub 'plik_banner')
// $url_field — nazwa pola URL (np. 'logo_url' lub 'banner_url')
// $prefix — prefiks pliku (np. 'logo' lub 'banner')
// $id_firmy — ID firmy (do nazwy pliku)
//
// Zwraca: ['ok' => bool, 'url' => string, 'blad' => string]
// ────────────────────────────────────────────────────────────────────
function upload_obrazka_firmy($field, $url_field, $prefix, $id_firmy) {
    $wynik = ['ok' => true, 'url' => '', 'blad' => ''];

    // Najpierw URL z pola tekstowego (priorytet niższy niż upload pliku)
    if (!empty($_POST[$url_field])) {
        $wynik['url'] = trim($_POST[$url_field]);
    }

    // Plik na dysk wygrywa z URL-em jeśli oba podane
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
        $dozwolone = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $nazwa_pliku = $_FILES[$field]['name'];
        $rozszerzenie = strtolower(pathinfo($nazwa_pliku, PATHINFO_EXTENSION));

        if (!in_array($rozszerzenie, $dozwolone)) {
            $wynik['ok'] = false;
            $wynik['blad'] = 'Niedozwolony format obrazka. Akceptowane: JPG, PNG, GIF, WEBP.';
            return $wynik;
        }

        if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
            $wynik['ok'] = false;
            $wynik['blad'] = 'Plik zbyt duży (max 5 MB).';
            return $wynik;
        }

        if (!is_dir('logos')) { @mkdir('logos', 0777, true); }
        $nowa_nazwa = 'logos/' . $prefix . '_firma_' . (int)$id_firmy . '_' . time() . '.' . $rozszerzenie;

        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $nowa_nazwa)) {
            $wynik['ok'] = false;
            $wynik['blad'] = 'Błąd zapisu pliku na serwerze.';
            return $wynik;
        }
        $wynik['url'] = $nowa_nazwa;
    }

    return $wynik;
}

// ────────────────────────────────────────────────────────────────────
// link_do_firmy($id_firmy, $nazwa) — gotowy HTML do wstawiania w listach
// ────────────────────────────────────────────────────────────────────
function link_do_firmy($id_firmy, $nazwa) {
    $id = (int)$id_firmy;
    $n  = htmlspecialchars($nazwa);
    return "<a href='game.php?page=profil_firmy&id=$id' style='color:var(--neon-gold);text-decoration:none;'>$n</a>";
}

} // koniec FIRMY_HELPER_LOADED guard