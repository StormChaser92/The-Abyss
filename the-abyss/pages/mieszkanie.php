<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// ═══════════════════════════════════════════════════════════════
// POBIERANIE DANYCH GRACZA
// ═══════════════════════════════════════════════════════════════
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$posiadane_um = !empty($gracz['umiejetnosci']) ? json_decode($gracz['umiejetnosci'], true) : [];
$posiadane_pokoje = !empty($gracz['pokoje_specjalne']) ? json_decode($gracz['pokoje_specjalne'], true) : [];

// ═══════════════════════════════════════════════════════════════
// SPRAWDŹ CZY GRACZ JEST LOKATOREM U KOGOŚ
// ═══════════════════════════════════════════════════════════════
$jestem_lokatorem = $polaczenie->query("SELECT w.*, g.login AS login_wlasciciela, g.id_domu AS dom_wlasciciela, g.avatar AS avatar_wlasciciela
    FROM wspollokatorzy w
    JOIN gracze g ON w.wlasciciel_id = g.id
    WHERE w.lokator_id = $id_gracza")->fetch_assoc();

$jestem_malzonkiem = ($jestem_lokatorem && $jestem_lokatorem['typ'] == 'malzonek');

// Sprawdź moje małżeństwo
$moje_malzenstwo = $polaczenie->query("SELECT m.*,
    g1.login AS m1_login, g1.avatar AS m1_avatar, g1.ostatni_sen AS m1_ostatni_sen,
    g2.login AS m2_login, g2.avatar AS m2_avatar, g2.ostatni_sen AS m2_ostatni_sen
    FROM malzenstwa m
    JOIN gracze g1 ON m.malzonek_1_id = g1.id
    JOIN gracze g2 ON m.malzonek_2_id = g2.id
    WHERE (m.malzonek_1_id=$id_gracza OR m.malzonek_2_id=$id_gracza) AND m.status='aktywne'")->fetch_assoc();

// Który dom gracz "widzi" — swój czy właściciela?
$patrzy_na_id = $jestem_lokatorem ? $jestem_lokatorem['wlasciciel_id'] : $id_gracza;
$patrzy_na_dom = $polaczenie->query("SELECT * FROM gracze WHERE id=$patrzy_na_id")->fetch_assoc();
$patrzy_na_posiadane_pokoje = !empty($patrzy_na_dom['pokoje_specjalne']) ? json_decode($patrzy_na_dom['pokoje_specjalne'], true) : [];
$jestem_wlascicielem = ($patrzy_na_id == $id_gracza);

// ═══════════════════════════════════════════════════════════════
// BAZA NIERUCHOMOŚCI
// ═══════════════════════════════════════════════════════════════
$baza_domow = [
    0 => ["nazwa"=>"Ulica (Bezdomny)",                   "opis"=>"Śpisz na kartonach w mrocznym zaułku Bronxu.",     "bonus_en"=>0,  "pokoi"=>0, "garaz"=>0, "lokatorzy"=>0, "img"=>"🗑️","kolor"=>"#666","kolor_rgb"=>"102,102,102","bg"=>"staten_island"],
    1 => ["nazwa"=>"Motel 'Pod Zdechłym Szczurem'",      "opis"=>"Pchły w materacu gratis. Ściany cienkie jak papier.","bonus_en"=>10, "pokoi"=>0, "garaz"=>0, "lokatorzy"=>0, "img"=>"🛏️","kolor"=>"#a0522d","kolor_rgb"=>"160,82,45","bg"=>"bronx"],
    2 => ["nazwa"=>"Kawalerka w Śródmieściu",            "opis"=>"Ciasne, ale własne. Woda w kranie ma rdzawy kolor.", "bonus_en"=>15, "pokoi"=>1, "garaz"=>1, "lokatorzy"=>1, "img"=>"🚪","kolor"=>"#00ccff","kolor_rgb"=>"0,204,255","bg"=>"brooklyn"],
    3 => ["nazwa"=>"Opuszczony Magazyn w Dokach",        "opis"=>"Ogromna przestrzeń, zapach smaru i echo kroków.",    "bonus_en"=>20, "pokoi"=>2, "garaz"=>3, "lokatorzy"=>2, "img"=>"🏭","kolor"=>"#00ff88","kolor_rgb"=>"0,255,136","bg"=>"brooklyn"],
    4 => ["nazwa"=>"Luksusowy Apartament w Wieży",       "opis"=>"Prywatna winda, ochrona w lobby, piękny widok.",      "bonus_en"=>35, "pokoi"=>3, "garaz"=>5, "lokatorzy"=>4, "img"=>"🏢","kolor"=>"#aa00ff","kolor_rgb"=>"170,0,255","bg"=>"manhattan"],
    5 => ["nazwa"=>"Willa na Wzgórzach",                 "opis"=>"Twierdza bossa. Oddzielona grubym murem i strażą.",   "bonus_en"=>50, "pokoi"=>5, "garaz"=>10,"lokatorzy"=>8, "img"=>"🏰","kolor"=>"#ffd700","kolor_rgb"=>"255,215,0","bg"=>"wall_street"]
];

$katalog_pokoi = [
    "Laboratorium Chemiczne"  => ["klasa"=>"Inżynier",   "koszt"=>25000, "ikona"=>"⚗️", "opis"=>"Produkcja leków, apteczek, stymulantów.",        "wymagania"=>["Chemia i Farmakologia"=>5], "link"=>"laboratorium"],
    "Warsztat Inżynieryjny"   => ["klasa"=>"Inżynier",   "koszt"=>20000, "ikona"=>"🔧", "opis"=>"Stół montażowy, tokarka. Produkcja broni.",      "wymagania"=>["Mechanika i Naprawa"=>5], "link"=>"warsztat"],
    "Serwerownia"             => ["klasa"=>"Inżynier",   "koszt"=>35000, "ikona"=>"🖥️", "opis"=>"Koparka krypto i zdalne włamania do korpo.",     "wymagania"=>["Hakowanie Terminali"=>5], "link"=>null],
    "Prywatna Siłownia"       => ["klasa"=>"Egzekutor",  "koszt"=>15000, "ikona"=>"🏋️", "opis"=>"Worek, ciężary. Codzienny bonus do statystyk.",  "wymagania"=>["Kondycja i Wytrzymałość"=>3], "link"=>"silownia"],
    "Dźwiękoszczelna Piwnica" => ["klasa"=>"Egzekutor",  "koszt"=>40000, "ikona"=>"⛓️", "opis"=>"Miejsce przesłuchań. Haracz za wypuszczenie.",   "wymagania"=>["Zastraszanie"=>5], "link"=>null],
    "Zbrojownia"              => ["klasa"=>"Egzekutor",  "koszt"=>30000, "ikona"=>"🔫", "opis"=>"Stojaki na broń, stoły serwisowe.",              "wymagania"=>["Walka Bronią Palną"=>5], "link"=>null],
    "Magazyn Kontrabandy"     => ["klasa"=>"Szabrownik", "koszt"=>20000, "ikona"=>"📦", "opis"=>"Bezpieczna skrytka na setki części.",            "wymagania"=>["Kieszonkostwo"=>3], "link"=>null],
    "Stacja Nasłuchowa"       => ["klasa"=>"Szabrownik", "koszt"=>25000, "ikona"=>"📻", "opis"=>"Skaner radiowy policji. Omijaj patrole.",        "wymagania"=>["Analiza Danych i Dedukcja"=>4], "link"=>null]
];

$id_mojego = $patrzy_na_dom['id_domu'];
$moj_dom = $baza_domow[$id_mojego] ?? $baza_domow[0];

$zajete_sloty_pokoje = count($patrzy_na_posiadane_pokoje);
$wolne_sloty_pokoje = max(0, $moj_dom['pokoi'] - $zajete_sloty_pokoje);

// ═══════════════════════════════════════════════════════════════
// POBIERZ LOKATORÓW (tylko właściciel może ich zobaczyć)
// ═══════════════════════════════════════════════════════════════
$lokatorzy = [];
if ($jestem_wlascicielem) {
    $q = $polaczenie->query("SELECT w.*, g.login, g.avatar, g.klasa, g.poziom
        FROM wspollokatorzy w
        JOIN gracze g ON w.lokator_id = g.id
        WHERE w.wlasciciel_id = $id_gracza");
    if ($q) while($r = $q->fetch_assoc()) $lokatorzy[] = $r;
}
$ile_lokatorow = count($lokatorzy);
$wolne_sloty_lokatorzy = max(0, $moj_dom['lokatorzy'] - $ile_lokatorow);

// ═══════════════════════════════════════════════════════════════
// OCZEKUJĄCE ZAPROSZENIA DLA MNIE
// ═══════════════════════════════════════════════════════════════
$moje_zaproszenia = [];
$q = $polaczenie->query("SELECT z.*, g.login AS login_od, g.avatar AS avatar_od, g.id_domu
    FROM zaproszenia_mieszkanie z
    JOIN gracze g ON z.od_id = g.id
    WHERE z.do_id = $id_gracza AND z.status = 'oczekuje'
    ORDER BY z.data_wyslania DESC");
if ($q) while($r = $q->fetch_assoc()) $moje_zaproszenia[] = $r;

// ═══════════════════════════════════════════════════════════════
// SYSTEM BEZPIECZNEJ STREFY
// ═══════════════════════════════════════════════════════════════
$w_mieszkaniu = (bool)$gracz['w_mieszkaniu'];
$cooldown_sek = 15 * 60;
$ostatni_atak_ts = $gracz['ostatni_atak_pvp'] ? strtotime($gracz['ostatni_atak_pvp']) : 0;
$cooldown_do = $ostatni_atak_ts + $cooldown_sek;
$cooldown_pozostal = max(0, $cooldown_do - time());
// Gracz może się schronić jeśli ma własne mieszkanie LUB jest lokatorem
$ma_gdzie_sie_ukryc = ($id_mojego > 0 || $jestem_lokatorem);
$moze_sie_schronic = ($cooldown_pozostal <= 0 && $ma_gdzie_sie_ukryc);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ukryj_sie'])) {
    if (!$ma_gdzie_sie_ukryc) {
        $komunikat = "<div class='blad'>Jesteś bezdomny. Nie masz gdzie się ukryć.</div>";
    } elseif ($cooldown_pozostal > 0) {
        $min = ceil($cooldown_pozostal / 60);
        $komunikat = "<div class='blad'>🚔 Właśnie kogoś zaatakowałeś! Musisz odczekać jeszcze <b>$min min</b>.</div>";
    } else {
        $polaczenie->query("UPDATE gracze SET w_mieszkaniu=1, czas_wejscia_mieszkanie=NOW() WHERE id=$id_gracza");
        $w_mieszkaniu = true;
        $komunikat = "<div class='sukces'>🛡️ Zamknąłeś za sobą drzwi. Jesteś bezpieczny.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wyjdz'])) {
    $polaczenie->query("UPDATE gracze SET w_mieszkaniu=0 WHERE id=$id_gracza");
    $w_mieszkaniu = false;
    $komunikat = "<div class='sukces'>🚪 Wychodzisz na ulicę.</div>";
}

// ═══════════════════════════════════════════════════════════════
// SEN
// ═══════════════════════════════════════════════════════════════
$dzisiaj = date('Y-m-d');
$data_snu = date('Y-m-d', strtotime($gracz['ostatni_sen']));
$moze_spac = ($dzisiaj > $data_snu);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['idz_spac'])) {
    if ($id_mojego > 0 && $moze_spac) {
        $bazowy = $moj_dom['bonus_en'] * $gracz['poziom'];

        // ══ BONUS WSPÓLNEGO SPANIA Z MAŁŻONKIEM (+30%) ══
        $bonus_malz = 0;
        $malz_info = '';
        if ($moje_malzenstwo) {
            // Sprawdź czy oboje mieszkają razem (właściciel + małżonek-lokator)
            $razem = false;
            $partner_id = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['malzonek_2_id'] : $moje_malzenstwo['malzonek_1_id'];

            // Sprawdź wspollokatorstwo małżeńskie w obu kierunkach
            $spr_razem = $polaczenie->query("SELECT id FROM wspollokatorzy WHERE typ='malzonek' AND (
                (wlasciciel_id=$id_gracza AND lokator_id=$partner_id) OR
                (wlasciciel_id=$partner_id AND lokator_id=$id_gracza))")->fetch_assoc();
            if ($spr_razem) $razem = true;

            if ($razem) {
                // Sprawdź czy partner też dziś śpi (ostatni_sen = dziś)
                $partner = $polaczenie->query("SELECT ostatni_sen FROM gracze WHERE id=$partner_id")->fetch_assoc();
                $dzis = date('Y-m-d');
                $partner_spi_dzis = ($partner && date('Y-m-d', strtotime($partner['ostatni_sen'])) == $dzis);

                if ($partner_spi_dzis) {
                    $bonus_malz = ceil($bazowy * 0.3);
                    $malz_info = " 💕 Wspólny sen z małżonkiem: +$bonus_malz EN";
                }
            }
        }

        $przyrost = $bazowy + $bonus_malz;
        $nowa_en  = min($gracz['energia_max'], $gracz['energia_aktualna'] + $przyrost);
        $polaczenie->query("UPDATE gracze SET energia_aktualna=$nowa_en, ostatni_sen=NOW() WHERE id=$id_gracza");
        $komunikat = "<div class='sukces'>💤 Budzisz się wypoczęty! <b>+$przyrost Energii</b>.$malz_info</div>";
        $moze_spac = false;
        $gracz['energia_aktualna'] = $nowa_en;
    }
}

// ═══════════════════════════════════════════════════════════════
// ZAPROSZENIE LOKATORA
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zapros_lokatora'])) {
    $cel_id = (int)$_POST['cel_id'];
    $wiadomosc = $polaczenie->real_escape_string(trim($_POST['wiadomosc'] ?? ''));

    if (!$jestem_wlascicielem) {
        $komunikat = "<div class='blad'>Tylko właściciel może zapraszać lokatorów!</div>";
    } elseif ($wolne_sloty_lokatorzy <= 0) {
        $komunikat = "<div class='blad'>Nie masz wolnego miejsca dla nowego lokatora!</div>";
    } elseif ($cel_id == $id_gracza) {
        $komunikat = "<div class='blad'>Nie możesz zaprosić samego siebie.</div>";
    } else {
        // Sprawdź czy gracz istnieje
        $cel = $polaczenie->query("SELECT id, login FROM gracze WHERE id=$cel_id")->fetch_assoc();
        if (!$cel) {
            $komunikat = "<div class='blad'>Nie ma gracza o ID $cel_id.</div>";
        } else {
            // Sprawdź czy już jest czyimś lokatorem
            $sprawdz = $polaczenie->query("SELECT id FROM wspollokatorzy WHERE lokator_id=$cel_id")->fetch_assoc();
            if ($sprawdz) {
                $komunikat = "<div class='blad'>Ten gracz jest już czyimś lokatorem.</div>";
            } else {
                // Sprawdź czy już wysyłałeś zaproszenie
                $spr2 = $polaczenie->query("SELECT id FROM zaproszenia_mieszkanie WHERE od_id=$id_gracza AND do_id=$cel_id AND status='oczekuje'")->fetch_assoc();
                if ($spr2) {
                    $komunikat = "<div class='blad'>Już wysłałeś mu zaproszenie. Czeka na odpowiedź.</div>";
                } else {
                    $polaczenie->query("INSERT INTO zaproszenia_mieszkanie (od_id, do_id, typ, wiadomosc, status)
                        VALUES ($id_gracza, $cel_id, 'wspollokator', '$wiadomosc', 'oczekuje')");

                    // Powiadomienie dla zaproszonego
                    $pow = "🏠 <b>{$gracz['login']}</b> zaprosił cię do zamieszkania w swoim mieszkaniu! Sprawdź zakładkę Mieszkanie.";
                    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '$pow')");

                    $komunikat = "<div class='sukces'>📨 Zaproszenie wysłane do <b>".htmlspecialchars($cel['login'])."</b>!</div>";
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// AKCEPTACJA / ODRZUCENIE ZAPROSZENIA
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['akceptuj_zaproszenie'])) {
    $zap_id = (int)$_POST['zaproszenie_id'];
    $zap = $polaczenie->query("SELECT * FROM zaproszenia_mieszkanie WHERE id=$zap_id AND do_id=$id_gracza AND status='oczekuje'")->fetch_assoc();
    if ($zap) {
        // Sprawdź czy nie jesteś już lokatorem u kogoś
        $spr = $polaczenie->query("SELECT id FROM wspollokatorzy WHERE lokator_id=$id_gracza")->fetch_assoc();
        if ($spr) {
            $komunikat = "<div class='blad'>Jesteś już czyimś lokatorem. Wyprowadź się najpierw.</div>";
        } else {
            $polaczenie->query("INSERT INTO wspollokatorzy (wlasciciel_id, lokator_id, typ) VALUES ({$zap['od_id']}, $id_gracza, '{$zap['typ']}')");
            $polaczenie->query("UPDATE zaproszenia_mieszkanie SET status='zaakceptowane' WHERE id=$zap_id");
            $polaczenie->query("UPDATE zaproszenia_mieszkanie SET status='odrzucone' WHERE do_id=$id_gracza AND status='oczekuje' AND id != $zap_id");

            // Powiadomienie dla właściciela
            $pow = "🎉 <b>{$gracz['login']}</b> wprowadził się do Twojego mieszkania!";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zap['od_id']}, '$pow')");

            echo "<script>location.href='game.php?page=mieszkanie';</script>"; exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['odrzuc_zaproszenie'])) {
    $zap_id = (int)$_POST['zaproszenie_id'];
    $zap = $polaczenie->query("SELECT * FROM zaproszenia_mieszkanie WHERE id=$zap_id AND do_id=$id_gracza AND status='oczekuje'")->fetch_assoc();
    if ($zap) {
        $polaczenie->query("UPDATE zaproszenia_mieszkanie SET status='odrzucone' WHERE id=$zap_id");
        $pow = "❌ <b>{$gracz['login']}</b> odrzucił Twoje zaproszenie do zamieszkania.";
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zap['od_id']}, '$pow')");
        $komunikat = "<div class='sukces'>Zaproszenie odrzucone.</div>";
    }
}

// ═══════════════════════════════════════════════════════════════
// WYRZUCENIE LOKATORA (właściciel)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wyrzuc_lokatora'])) {
    $lok_id = (int)$_POST['lokator_id'];
    $polaczenie->query("DELETE FROM wspollokatorzy WHERE wlasciciel_id=$id_gracza AND lokator_id=$lok_id");
    // Wyłącz mu tryb "w_mieszkaniu" jakby był aktywny
    $polaczenie->query("UPDATE gracze SET w_mieszkaniu=0 WHERE id=$lok_id");
    $pow = "🚪 <b>{$gracz['login']}</b> wyrzucił cię ze swojego mieszkania.";
    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($lok_id, '$pow')");
    $komunikat = "<div class='sukces'>Lokator wyrzucony na ulicę.</div>";
    echo "<script>location.href='game.php?page=mieszkanie';</script>"; exit;
}

// ═══════════════════════════════════════════════════════════════
// WYPROWADZKA (lokator)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wyprowadz_sie'])) {
    if ($jestem_lokatorem) {
        $wl_id = $jestem_lokatorem['wlasciciel_id'];
        $polaczenie->query("DELETE FROM wspollokatorzy WHERE lokator_id=$id_gracza");
        $polaczenie->query("UPDATE gracze SET w_mieszkaniu=0 WHERE id=$id_gracza");
        $pow = "🚶 <b>{$gracz['login']}</b> wyprowadził się z Twojego mieszkania.";
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($wl_id, '$pow')");
        echo "<script>location.href='game.php?page=mieszkanie';</script>"; exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// WSPÓLNA SKRZYNIA
// ═══════════════════════════════════════════════════════════════

// Utwórz skrzynię jeśli nie istnieje (dla właściciela)
$wl_skrzyni_id = $jestem_wlascicielem ? $id_gracza : $jestem_lokatorem['wlasciciel_id'];

$spr_skrz = $polaczenie->query("SELECT id FROM wspolna_skrzynia WHERE wlasciciel_id=$wl_skrzyni_id")->fetch_assoc();
if (!$spr_skrz && ($jestem_wlascicielem || $jestem_lokatorem) && $id_mojego > 0) {
    $polaczenie->query("INSERT INTO wspolna_skrzynia (wlasciciel_id) VALUES ($wl_skrzyni_id)");
}

// Operacje na skrzyni
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['skrzynia_akcja'])) {
    $akcja = $_POST['skrzynia_akcja']; // 'wrzuc' albo 'wyjmij'
    $typ = $_POST['typ_zasobu']; // 'gotowka', 'zlom_stalowy', 'czesci_mechaniczne', 'syntetyki', 'elektronika'
    $ilosc = max(0, (int)$_POST['ilosc']);

    $dozwolone_zasoby = ['gotowka','zlom_stalowy','czesci_mechaniczne','syntetyki','elektronika'];
    if (in_array($typ, $dozwolone_zasoby) && $ilosc > 0) {
        if ($akcja == 'wrzuc') {
            if ($gracz[$typ] >= $ilosc) {
                $polaczenie->query("UPDATE gracze SET $typ = $typ - $ilosc WHERE id=$id_gracza");
                $polaczenie->query("UPDATE wspolna_skrzynia SET $typ = $typ + $ilosc WHERE wlasciciel_id=$wl_skrzyni_id");
                $polaczenie->query("INSERT INTO skrzynia_log (wlasciciel_id, gracz_id, akcja, typ_zasobu, ilosc) VALUES ($wl_skrzyni_id, $id_gracza, 'wrzuc', '$typ', $ilosc)");
                $komunikat = "<div class='sukces'>📦 Wrzuciłeś <b>$ilosc</b> do skrzyni.</div>";
            } else {
                $komunikat = "<div class='blad'>Nie masz tyle zasobów!</div>";
            }
        } elseif ($akcja == 'wyjmij') {
            $sk = $polaczenie->query("SELECT $typ FROM wspolna_skrzynia WHERE wlasciciel_id=$wl_skrzyni_id")->fetch_assoc();
            if ($sk && $sk[$typ] >= $ilosc) {
                $polaczenie->query("UPDATE wspolna_skrzynia SET $typ = $typ - $ilosc WHERE wlasciciel_id=$wl_skrzyni_id");
                $polaczenie->query("UPDATE gracze SET $typ = $typ + $ilosc WHERE id=$id_gracza");
                $polaczenie->query("INSERT INTO skrzynia_log (wlasciciel_id, gracz_id, akcja, typ_zasobu, ilosc) VALUES ($wl_skrzyni_id, $id_gracza, 'wyjmij', '$typ', $ilosc)");
                $komunikat = "<div class='sukces'>📤 Wyjąłeś <b>$ilosc</b> ze skrzyni.</div>";
            } else {
                $komunikat = "<div class='blad'>Skrzynia nie ma tyle zasobów!</div>";
            }
        }
    }
}

// Pobierz stan skrzyni
$skrzynia = null;
if ($wl_skrzyni_id && $id_mojego > 0) {
    $skrzynia = $polaczenie->query("SELECT * FROM wspolna_skrzynia WHERE wlasciciel_id=$wl_skrzyni_id")->fetch_assoc();
}

// Ostatnie operacje (5 najnowszych)
$log_skrzyni = [];
if ($skrzynia) {
    $q = $polaczenie->query("SELECT s.*, g.login FROM skrzynia_log s
        JOIN gracze g ON s.gracz_id = g.id
        WHERE s.wlasciciel_id = $wl_skrzyni_id
        ORDER BY s.data DESC LIMIT 5");
    if ($q) while($r = $q->fetch_assoc()) $log_skrzyni[] = $r;
}

// ═══════════════════════════════════════════════════════════════
// BUDOWA POKOJU (tylko właściciel!)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zbuduj_pokoj'])) {
    if (!$jestem_wlascicielem) {
        $komunikat = "<div class='blad'>Tylko właściciel mieszkania może budować pokoje!</div>";
    } else {
        $nazwa = $_POST['pokoj'];
        if (isset($katalog_pokoi[$nazwa])) {
            $dane = $katalog_pokoi[$nazwa];
            if ($dane['klasa'] !== $gracz['klasa']) {
                $komunikat = "<div class='blad'>Ten pokój nie pasuje do Twojej klasy!</div>";
            } elseif (in_array($nazwa, $posiadane_pokoje)) {
                $komunikat = "<div class='blad'>Masz już ten pokój!</div>";
            } elseif ($wolne_sloty_pokoje <= 0) {
                $komunikat = "<div class='blad'>Brak miejsca!</div>";
            } elseif ($gracz['gotowka'] < $dane['koszt']) {
                $komunikat = "<div class='blad'>Brak gotówki!</div>";
            } else {
                $ok = true;
                foreach ($dane['wymagania'] as $u => $l) {
                    if (!isset($posiadane_um[$u]) || $posiadane_um[$u] < $l) { $ok = false; break; }
                }
                if ($ok) {
                    $posiadane_pokoje[] = $nazwa;
                    $json = $polaczenie->real_escape_string(json_encode($posiadane_pokoje));
                    $polaczenie->query("UPDATE gracze SET gotowka=gotowka-{$dane['koszt']}, pokoje_specjalne='$json' WHERE id=$id_gracza");
                    $komunikat = "<div class='sukces'>✅ Zbudowano: <b>$nazwa</b></div>";
                    echo "<script>location.href='game.php?page=mieszkanie';</script>"; exit;
                } else {
                    $komunikat = "<div class='blad'>Nie spełniasz wymagań!</div>";
                }
            }
        }
    }
}

$kolor_klasy = match($gracz['klasa']){
    'Egzekutor'  => ['#ff5555','255,85,85'],
    'Szabrownik' => ['#ffaa00','255,170,0'],
    'Inżynier'   => ['#00ccff','0,204,255'],
    default      => ['#00ff88','0,255,136'],
};
?>

<style>
/* ══ STYLE Z ETAPU A (podstawa) ══ */
.m-header{
    background:
        linear-gradient(135deg,rgba(0,0,0,.65),rgba(<?php echo $moj_dom['kolor_rgb']; ?>,.25),rgba(0,0,0,.75)),
        url('img/dzielnice/<?php echo $moj_dom['bg']; ?>.jpg') center/cover;
    background-color:rgba(<?php echo $moj_dom['kolor_rgb']; ?>,.1);
    padding:32px 36px;border-radius:12px;margin-bottom:22px;
    border:1px solid rgba(<?php echo $moj_dom['kolor_rgb']; ?>,.4);
    box-shadow:0 0 30px rgba(<?php echo $moj_dom['kolor_rgb']; ?>,.15);
    text-align:center;position:relative;overflow:hidden;
}
.m-ikona{font-size:4em;line-height:1;margin-bottom:10px;filter:drop-shadow(0 0 20px <?php echo $moj_dom['kolor']; ?>)}
.m-nazwa{font-family:'Oswald',sans-serif;color:<?php echo $moj_dom['kolor']; ?>;font-size:2.4em;
    margin:0 0 8px;text-transform:uppercase;letter-spacing:2px;
    text-shadow:0 0 20px rgba(<?php echo $moj_dom['kolor_rgb']; ?>,.7)}
.m-opis{color:#bbb;font-style:italic;font-size:1em;margin-bottom:0}
.m-jestem-lokatorem{background:rgba(221,136,255,.15);border:1px solid rgba(221,136,255,.5);color:#dd88ff;
    padding:6px 14px;border-radius:20px;display:inline-block;margin-top:10px;font-family:'Oswald',sans-serif;
    font-size:.85em;text-transform:uppercase;letter-spacing:1.5px}

/* Pasek bezpieczeństwa */
.safe-panel{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.4);border-radius:10px;padding:18px 22px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;animation:safe-glow 3s ease-in-out infinite}
@keyframes safe-glow{0%,100%{box-shadow:0 0 15px rgba(0,255,136,.15)}50%{box-shadow:0 0 30px rgba(0,255,136,.4)}}
.safe-info{display:flex;align-items:center;gap:14px}
.safe-icon{font-size:2.2em;filter:drop-shadow(0 0 10px #00ff88)}
.safe-text strong{color:#00ff88;font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;letter-spacing:1.5px;display:block;text-shadow:0 0 10px rgba(0,255,136,.5)}
.safe-text span{color:#888;font-size:.85em}
.unsafe-panel{background:rgba(255,68,68,.06);border:1px solid rgba(255,68,68,.3);border-radius:10px;padding:18px 22px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px}
.unsafe-panel .safe-icon{filter:drop-shadow(0 0 10px #ff6666)}
.unsafe-panel strong{color:#ff6666;font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;letter-spacing:1.5px;display:block}
.btn-safe{background:rgba(0,255,136,.15);color:#00ff88;border:1px solid rgba(0,255,136,.5);padding:11px 22px;font-family:'Oswald',sans-serif;font-size:1em;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:1px;border-radius:6px;transition:.3s}
.btn-safe:hover{background:#00ff88;color:#000;box-shadow:0 0 20px rgba(0,255,136,.5)}
.btn-unsafe{background:rgba(255,170,0,.1);color:#ffaa00;border:1px solid rgba(255,170,0,.5);padding:11px 22px;font-family:'Oswald',sans-serif;font-size:1em;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:1px;border-radius:6px;transition:.3s}
.btn-unsafe:hover{background:#ffaa00;color:#000;box-shadow:0 0 20px rgba(255,170,0,.5)}
.btn-disabled{background:rgba(0,0,0,.5);color:#555;border:1px solid rgba(255,255,255,.1);cursor:not-allowed}

/* Sen */
.sen-box{background:rgba(0,204,255,.05);border:1px solid rgba(0,204,255,.25);border-radius:10px;padding:20px;text-align:center;margin-bottom:22px}
.sen-ikona{font-size:2.5em;margin-bottom:8px;filter:drop-shadow(0 0 15px #00ccff);animation:<?php echo $moze_spac ? 'sen-pulse 2s infinite' : 'none'; ?>}
@keyframes sen-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.05);opacity:.8}}
.sen-info{color:#00ccff;font-family:'Oswald',sans-serif;font-size:1.05em;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;text-shadow:0 0 10px rgba(0,204,255,.4)}
.btn-spij{background:rgba(0,204,255,.15);color:#00ccff;border:1px solid rgba(0,204,255,.5);padding:13px 36px;font-family:'Oswald',sans-serif;font-size:1.1em;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:1.5px;border-radius:6px;transition:.3s}
.btn-spij:hover{background:#00ccff;color:#000;box-shadow:0 0 25px rgba(0,204,255,.5)}

/* Infrastruktura */
.infra-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:26px}
.infra-karta{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:20px}
.infra-tytul{font-family:'Oswald',sans-serif;color:#fff;font-size:1.1em;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;justify-content:space-between;align-items:center}
.infra-tytul b{color:#00ff88}

.slot-zbudowany{background:rgba(<?php echo $kolor_klasy[1]; ?>,.08);border:1px solid rgba(<?php echo $kolor_klasy[1]; ?>,.35);border-radius:8px;padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;transition:.25s}
.slot-zbudowany:hover{background:rgba(<?php echo $kolor_klasy[1]; ?>,.15);transform:translateX(3px)}
.slot-nazwa{display:flex;align-items:center;gap:10px;color:#fff;font-family:'Oswald',sans-serif;font-size:1em;letter-spacing:.5px}
.slot-ikona{font-size:1.4em}
.slot-wejdz{background:rgba(<?php echo $kolor_klasy[1]; ?>,.15);color:<?php echo $kolor_klasy[0]; ?>;border:1px solid rgba(<?php echo $kolor_klasy[1]; ?>,.4);padding:6px 14px;border-radius:4px;text-decoration:none;font-family:'Oswald',sans-serif;font-size:.82em;text-transform:uppercase;letter-spacing:1px;transition:.2s}
.slot-wejdz:hover{background:<?php echo $kolor_klasy[0]; ?>;color:#000}
.slot-pusty{background:rgba(0,0,0,.4);border:1px dashed rgba(255,255,255,.1);color:#555;padding:14px;text-align:center;border-radius:8px;margin-bottom:10px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.88em}
.slot-niedostepny{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.04);color:#333;padding:14px;text-align:center;border-radius:8px;font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.85em}

/* ══ NOWE: LOKATORZY ══ */
.lokator-box{
    background:rgba(221,136,255,.05);border:1px solid rgba(221,136,255,.2);
    border-radius:8px;padding:12px;margin-bottom:10px;
    display:flex;justify-content:space-between;align-items:center;gap:12px;
}
.lok-left{display:flex;align-items:center;gap:10px;flex:1}
.lok-avatar{
    width:42px;height:42px;border-radius:50%;background-size:cover;background-position:top center;
    border:1px solid rgba(221,136,255,.4);flex-shrink:0;
}
.lok-info{display:flex;flex-direction:column}
.lok-login{font-family:'Oswald',sans-serif;color:#dd88ff;font-size:1em;letter-spacing:.5px}
.lok-meta{font-size:.78em;color:#666}
.btn-wyrzuc{
    background:transparent;border:1px solid rgba(255,68,68,.3);color:#ff6666;
    padding:6px 12px;border-radius:4px;cursor:pointer;font-family:'Oswald',sans-serif;
    font-size:.78em;text-transform:uppercase;letter-spacing:.5px;transition:.2s;
}
.btn-wyrzuc:hover{background:rgba(255,68,68,.2)}

/* ══ ZAPROSZENIA ══ */
.zaproszenie-box{
    background:linear-gradient(135deg,rgba(221,136,255,.1),rgba(0,0,0,.3));
    border:1px solid rgba(221,136,255,.4);border-radius:10px;padding:18px;margin-bottom:14px;
    animation:zap-glow 3s ease-in-out infinite;
}
@keyframes zap-glow{0%,100%{box-shadow:0 0 15px rgba(221,136,255,.15)}50%{box-shadow:0 0 30px rgba(221,136,255,.4)}}
.zap-head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.zap-avatar{width:50px;height:50px;border-radius:50%;background-size:cover;background-position:top center;border:2px solid rgba(221,136,255,.5)}
.zap-text{color:#ccc;font-size:.95em;line-height:1.5}
.zap-text b{color:#dd88ff}
.zap-wiadomosc{
    background:rgba(0,0,0,.5);border-left:3px solid rgba(221,136,255,.5);
    padding:10px 14px;margin:10px 0;color:#aaa;font-style:italic;font-size:.88em;
}
.zap-akcje{display:flex;gap:10px;margin-top:12px}
.btn-akceptuj{flex:1;background:rgba(0,255,136,.15);color:#00ff88;border:1px solid rgba(0,255,136,.5);padding:10px;font-family:'Oswald',sans-serif;cursor:pointer;border-radius:6px;text-transform:uppercase;letter-spacing:1px}
.btn-akceptuj:hover{background:#00ff88;color:#000}
.btn-odrzuc{background:transparent;border:1px solid rgba(255,68,68,.3);color:#ff6666;padding:10px 18px;font-family:'Oswald',sans-serif;cursor:pointer;border-radius:6px;text-transform:uppercase;letter-spacing:1px}
.btn-odrzuc:hover{background:rgba(255,68,68,.15)}

/* ══ FORMULARZ ZAPROSZENIA ══ */
.form-zapros{background:rgba(0,0,0,.4);border:1px dashed rgba(221,136,255,.3);border-radius:8px;padding:14px;margin-top:12px}
.form-zapros input,.form-zapros textarea{
    width:100%;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.1);
    color:#ddd;padding:8px 12px;border-radius:4px;font-family:'Open Sans',sans-serif;font-size:.9em;
    box-sizing:border-box;margin-bottom:8px;
}
.form-zapros textarea{resize:vertical;min-height:60px}
.form-zapros input:focus,.form-zapros textarea:focus{outline:none;border-color:rgba(221,136,255,.5)}
.btn-zapros{width:100%;background:rgba(221,136,255,.15);color:#dd88ff;border:1px solid rgba(221,136,255,.5);padding:10px;font-family:'Oswald',sans-serif;cursor:pointer;text-transform:uppercase;letter-spacing:1px;border-radius:6px}
.btn-zapros:hover{background:#dd88ff;color:#000}

/* ══ WSPÓLNA SKRZYNIA ══ */
.skrzynia-wrap{background:rgba(0,0,0,.5);border:1px solid rgba(255,215,0,.2);border-radius:10px;padding:22px;margin-bottom:22px}
.skrzynia-tytul{font-family:'Oswald',sans-serif;color:#ffd700;font-size:1.1em;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid rgba(255,215,0,.15);display:flex;align-items:center;gap:10px}
.skrzynia-stan{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:16px}
.skrzynia-zasob{
    background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.06);
    border-radius:8px;padding:12px;text-align:center;
}
.sz-label{font-size:.72em;color:#666;text-transform:uppercase;font-family:'Oswald',sans-serif;letter-spacing:1px;margin-bottom:4px}
.sz-val{font-family:'Oswald',sans-serif;font-size:1.3em;font-weight:700;color:#fff;margin-bottom:8px}
.sz-akcje{display:flex;gap:4px}
.sz-akcje input{width:50px;padding:4px;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:3px;font-size:.85em;text-align:center}
.sz-btn{flex:1;background:rgba(255,215,0,.1);color:#ffd700;border:1px solid rgba(255,215,0,.3);padding:4px 8px;font-family:'Oswald',sans-serif;font-size:.75em;cursor:pointer;border-radius:3px;text-transform:uppercase}
.sz-btn:hover{background:#ffd700;color:#000}
.sz-btn.wyjmij{background:rgba(0,204,255,.1);color:#00ccff;border-color:rgba(0,204,255,.3)}
.sz-btn.wyjmij:hover{background:#00ccff;color:#000}

.skrzynia-log{margin-top:12px;background:rgba(0,0,0,.4);border-radius:6px;padding:10px 14px;font-size:.82em;color:#888}
.log-row{padding:4px 0;border-bottom:1px dashed rgba(255,255,255,.04)}
.log-row:last-child{border-bottom:none}
.log-row b{color:#ccc}

/* Katalog budowy — jak w ETAP A */
.sekcja-tytul{color:#666;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.85em;margin:30px 0 14px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.05)}
.status-box{background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.06);padding:14px 18px;border-radius:8px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.status-box span{color:#888;font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.85em}
.status-box b{color:#00ff88;font-size:1.15em}
.pokoje-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.pokoj-k{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:18px;display:flex;flex-direction:column;gap:12px;transition:.3s}
.pokoj-k:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.5)}
.pokoj-k.zbudowany{border-color:rgba(<?php echo $kolor_klasy[1]; ?>,.4);background:rgba(<?php echo $kolor_klasy[1]; ?>,.05)}
.pk-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.pk-tytul{font-family:'Oswald',sans-serif;color:#fff;font-size:1.1em;letter-spacing:.5px}
.pk-koszt{color:#ffd700;font-family:'Oswald',sans-serif;font-weight:700;white-space:nowrap}
.pk-opis{color:#888;font-size:.88em;line-height:1.5}
.pk-wymagania{background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.05);border-radius:6px;padding:10px 12px;font-size:.82em;line-height:1.8}
.wym-ok{color:#00ff88}
.wym-brak{color:#ff5555;text-decoration:line-through}
.btn-buduj{background:rgba(255,215,0,.1);color:#ffd700;border:1px solid rgba(255,215,0,.4);padding:11px;font-family:'Oswald',sans-serif;font-size:.95em;cursor:pointer;text-transform:uppercase;letter-spacing:1px;border-radius:6px;transition:.3s;font-weight:700;margin-top:auto}
.btn-buduj:hover{background:#ffd700;color:#000;box-shadow:0 0 15px rgba(255,215,0,.4)}
.btn-zbudowany{background:rgba(<?php echo $kolor_klasy[1]; ?>,.15);color:<?php echo $kolor_klasy[0]; ?>;border:1px solid rgba(<?php echo $kolor_klasy[1]; ?>,.4);padding:11px;font-family:'Oswald',sans-serif;border-radius:6px;text-align:center;text-transform:uppercase;letter-spacing:1px;margin-top:auto}
.btn-brak{background:transparent;border:1px solid rgba(255,255,255,.08);color:#444;padding:11px;font-family:'Oswald',sans-serif;text-transform:uppercase;border-radius:6px;letter-spacing:1px;cursor:not-allowed;text-align:center;margin-top:auto}

.sukces{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.4);color:#00ff88;padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
.blad{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.4);color:#ff6666;padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
</style>

<?php echo $komunikat; ?>

<!-- ══ SEKCJA MAŁŻONKA (jeśli jest) ══ -->
<?php if ($moje_malzenstwo):
    $dni_razem = floor((time() - strtotime($moje_malzenstwo['data_slubu'])) / 86400);
    $partner_login = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['m2_login'] : $moje_malzenstwo['m1_login'];
    $partner_avatar = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['m2_avatar'] : $moje_malzenstwo['m1_avatar'];
    $partner_id_m = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['malzonek_2_id'] : $moje_malzenstwo['malzonek_1_id'];
    $av_partner = !empty($partner_avatar) ? htmlspecialchars($partner_avatar) : "https://via.placeholder.com/100/0a0a0a/333?text=?";

    // Sprawdź czy partner spał dziś
    $partner_sen_info = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['m2_ostatni_sen'] : $moje_malzenstwo['m1_ostatni_sen'];
    $dzis = date('Y-m-d');
    $partner_spal_dzis = ($partner_sen_info && date('Y-m-d', strtotime($partner_sen_info)) == $dzis);
?>
<div style="background:linear-gradient(135deg,rgba(255,51,102,.08),rgba(221,136,255,.08));
    border:1px solid rgba(255,51,102,.3);border-radius:12px;padding:18px 22px;margin-bottom:18px;
    display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <a href="game.php?page=profil&id=<?php echo $partner_id_m; ?>" style="text-decoration:none">
        <div style="width:54px;height:54px;border-radius:50%;background-size:cover;background-position:top center;
            background-image:url('<?php echo $av_partner; ?>');border:2px solid #ff3366;
            box-shadow:0 0 15px rgba(255,51,102,.3)"></div>
    </a>
    <div style="flex:1">
        <div style="font-family:'Oswald',sans-serif;color:#ff3366;text-transform:uppercase;font-size:.8em;letter-spacing:1.5px;margin-bottom:3px">💍 Moja druga połówka</div>
        <a href="game.php?page=profil&id=<?php echo $partner_id_m; ?>" style="text-decoration:none;color:#fff;font-family:'Oswald',sans-serif;font-size:1.2em;letter-spacing:.5px">
            <?php echo htmlspecialchars($partner_login); ?>
        </a>
        <div style="color:#888;font-size:.85em;margin-top:3px">
            🕊️ Razem <b style="color:#dd88ff"><?php echo $dni_razem; ?></b> <?php echo $dni_razem==1?'dzień':'dni'; ?>
            <?php if ($partner_spal_dzis): ?>
                · 😴 <span style="color:#00ff88">Śpi dziś</span>
            <?php else: ?>
                · <span style="color:#666">Jeszcze nie spał/a dziś</span>
            <?php endif; ?>
        </div>
    </div>
    <a href="game.php?page=katedra" style="background:rgba(255,215,0,.1);color:#ffd700;border:1px solid rgba(255,215,0,.3);
        padding:6px 14px;border-radius:4px;text-decoration:none;font-family:'Oswald',sans-serif;font-size:.82em;
        text-transform:uppercase;letter-spacing:1px;transition:.25s">⛪ Katedra</a>
</div>
<?php endif; ?>

<!-- ══ OCZEKUJĄCE ZAPROSZENIA ══ -->
<?php if (!empty($moje_zaproszenia)): ?>
<div class="sekcja-tytul">📬 Oczekujące zaproszenia</div>
<?php foreach($moje_zaproszenia as $zap):
    $av = !empty($zap['avatar_od']) ? htmlspecialchars($zap['avatar_od']) : "https://via.placeholder.com/100/0a0a0a/333?text=?";
    $dom_zap = $baza_domow[$zap['id_domu']] ?? $baza_domow[0];
?>
<div class="zaproszenie-box">
    <div class="zap-head">
        <div class="zap-avatar" style="background-image:url('<?php echo $av; ?>')"></div>
        <div class="zap-text">
            <b><?php echo htmlspecialchars($zap['login_od']); ?></b> zaprasza Cię do zamieszkania w:
            <br><b style="color:<?php echo $dom_zap['kolor']; ?>"><?php echo $dom_zap['img']; ?> <?php echo $dom_zap['nazwa']; ?></b>
        </div>
    </div>
    <?php if (!empty($zap['wiadomosc'])): ?>
    <div class="zap-wiadomosc">❝ <?php echo nl2br(htmlspecialchars($zap['wiadomosc'])); ?> ❞</div>
    <?php endif; ?>
    <form method="POST" class="zap-akcje">
        <input type="hidden" name="zaproszenie_id" value="<?php echo $zap['id']; ?>">
        <button type="submit" name="akceptuj_zaproszenie" class="btn-akceptuj">✓ Akceptuję — wprowadzam się</button>
        <button type="submit" name="odrzuc_zaproszenie" class="btn-odrzuc">✗ Odrzuć</button>
    </form>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ══ PASEK BEZPIECZEŃSTWA ══ -->
<?php if ($w_mieszkaniu): ?>
<div class="safe-panel">
    <div class="safe-info">
        <div class="safe-icon">🛡️</div>
        <div class="safe-text">
            <strong>Bezpieczna strefa aktywna</strong>
            <span>Żaden gracz nie może cię teraz zaatakować.</span>
        </div>
    </div>
    <form method="POST" style="margin:0"><button type="submit" name="wyjdz" class="btn-unsafe">🚪 Wyjdź na ulicę</button></form>
</div>
<?php else: ?>
<div class="unsafe-panel">
    <div class="safe-info">
        <div class="safe-icon">⚠️</div>
        <div class="safe-text">
            <strong>Jesteś w terenie</strong>
            <?php if (!$ma_gdzie_sie_ukryc): ?>
                <span>Jesteś bezdomny. Kup nieruchomość lub znajdź lokatora który Cię przyjmie.</span>
            <?php elseif ($cooldown_pozostal > 0):
                $min = ceil($cooldown_pozostal / 60); ?>
                <span>Cooldown po ataku PvP: <b style="color:#ff6666"><?php echo $min; ?> min</b>.</span>
            <?php else: ?>
                <span>Możesz zostać zaatakowany. Ukryj się w mieszkaniu.</span>
            <?php endif; ?>
        </div>
    </div>
    <form method="POST" style="margin:0">
        <?php if ($moze_sie_schronic): ?>
            <button type="submit" name="ukryj_sie" class="btn-safe">🛡️ Ukryj się w mieszkaniu</button>
        <?php else: ?>
            <button type="button" class="btn-safe btn-disabled" disabled>🔒 Niedostępne</button>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<!-- ══ NAGŁÓWEK ══ -->
<div class="m-header">
    <div class="m-ikona"><?php echo $moj_dom['img']; ?></div>
    <h1 class="m-nazwa"><?php echo $moj_dom['nazwa']; ?></h1>
    <p class="m-opis">❝ <?php echo $moj_dom['opis']; ?> ❞</p>
    <?php if ($jestem_lokatorem): ?>
    <div class="m-jestem-lokatorem">🏠 Mieszkasz u <?php echo htmlspecialchars($jestem_lokatorem['login_wlasciciela']); ?></div>
    <?php endif; ?>
</div>

<!-- ══ SEN ══ -->
<?php if ($id_mojego > 0): ?>
<div class="sen-box">
    <div class="sen-ikona">💤</div>
    <div class="sen-info">Bonus z tego łóżka: <b>+<?php echo ($moj_dom['bonus_en'] * $gracz['poziom']); ?> Energii</b></div>
    <form method="POST" style="margin:0">
        <?php if ($moze_spac): ?>
            <button type="submit" name="idz_spac" class="btn-spij">😴 Prześpij się</button>
        <?php else: ?>
            <button type="button" class="btn-spij btn-disabled" disabled>✓ Spałeś już dzisiaj</button>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<!-- ══ WSPÓLNA SKRZYNIA ══ -->
<?php if ($skrzynia): ?>
<div class="skrzynia-wrap">
    <div class="skrzynia-tytul">🗃️ Wspólna skrzynia <span style="color:#555;font-size:.75em;font-weight:400;letter-spacing:0">— dostępna dla właściciela i lokatorów</span></div>
    <div class="skrzynia-stan">
        <?php
        $zasoby = [
            'gotowka'            => ['label'=>'💵 Gotówka','kolor'=>'#00ff88'],
            'zlom_stalowy'       => ['label'=>'🔩 Stal',   'kolor'=>'#aaa'],
            'czesci_mechaniczne' => ['label'=>'⚙️ Części', 'kolor'=>'#ccc'],
            'syntetyki'          => ['label'=>'🧵 Kevlar', 'kolor'=>'#00ffcc'],
            'elektronika'        => ['label'=>'🔋 Elek.',  'kolor'=>'#ffd700']
        ];
        foreach($zasoby as $k => $z):
        ?>
        <div class="skrzynia-zasob">
            <div class="sz-label"><?php echo $z['label']; ?></div>
            <div class="sz-val" style="color:<?php echo $z['kolor']; ?>"><?php echo number_format($skrzynia[$k],0,'','&nbsp;'); ?></div>
            <form method="POST" class="sz-akcje" style="margin-bottom:4px">
                <input type="hidden" name="typ_zasobu" value="<?php echo $k; ?>">
                <input type="number" name="ilosc" min="1" placeholder="0" required>
                <button type="submit" name="skrzynia_akcja" value="wrzuc" class="sz-btn">➕ W</button>
            </form>
            <form method="POST" class="sz-akcje">
                <input type="hidden" name="typ_zasobu" value="<?php echo $k; ?>">
                <input type="number" name="ilosc" min="1" placeholder="0" required>
                <button type="submit" name="skrzynia_akcja" value="wyjmij" class="sz-btn wyjmij">➖ WY</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($log_skrzyni)): ?>
    <div class="skrzynia-log">
        <b style="color:#888;text-transform:uppercase;font-size:.78em;font-family:'Oswald',sans-serif;letter-spacing:1px">Ostatnie operacje</b>
        <?php foreach($log_skrzyni as $l):
            $ikona = $l['akcja'] == 'wrzuc' ? '➕' : '➖';
            $kolor_a = $l['akcja'] == 'wrzuc' ? '#00ff88' : '#00ccff';
            $data_skr = date('d.m H:i', strtotime($l['data']));
        ?>
        <div class="log-row">
            <?php echo $ikona; ?> <b><?php echo htmlspecialchars($l['login']); ?></b>
            <span style="color:<?php echo $kolor_a; ?>"><?php echo $l['akcja'] == 'wrzuc' ? 'wrzucił' : 'wyjął'; ?></span>
            <b><?php echo $l['ilosc']; ?></b> <?php echo $zasoby[$l['typ_zasobu']]['label'] ?? $l['typ_zasobu']; ?>
            <span style="float:right;color:#444"><?php echo $data_skr; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ INFRASTRUKTURA ══ -->
<?php if ($id_mojego > 0): ?>
<div class="infra-grid">
    <!-- Garaż -->
    <div class="infra-karta">
        <div class="infra-tytul">🚗 Garaż <b><?php echo $moj_dom['garaz']; ?> miejsc</b></div>
        <?php if ($moj_dom['garaz'] > 0): ?>
            <?php for($i=0;$i<$moj_dom['garaz'];$i++): ?><div class="slot-pusty">Puste miejsce parkingowe</div><?php endfor; ?>
        <?php else: ?>
            <div class="slot-niedostepny">Brak garażu</div>
        <?php endif; ?>
    </div>

    <!-- Pokoje specjalne -->
    <div class="infra-karta">
        <div class="infra-tytul">🔬 Pokoje specjalne <b><?php echo $zajete_sloty_pokoje; ?>/<?php echo $moj_dom['pokoi']; ?></b></div>
        <?php if ($moj_dom['pokoi'] > 0): ?>
            <?php foreach($patrzy_na_posiadane_pokoje as $p):
                $dane_p = $katalog_pokoi[$p] ?? null;
                $ikona = $dane_p['ikona'] ?? '🔸';
                $link = $dane_p['link'] ?? null;
                // Lokator ma dostęp do pokoi tylko jeśli ma uprawnienia (na razie zawsze TAK)
                // W ETAP E dodamy logikę małżonka/zwykłego lokatora
            ?>
            <div class="slot-zbudowany">
                <div class="slot-nazwa"><span class="slot-ikona"><?php echo $ikona; ?></span><?php echo htmlspecialchars($p); ?></div>
                <?php if ($link && ($jestem_wlascicielem || $jestem_malzonkiem)): ?>
                    <a href="game.php?page=<?php echo $link; ?>" class="slot-wejdz">Wejdź →</a>
                <?php elseif($link && $jestem_lokatorem): ?>
                    <span style="color:#555;font-size:.7em;font-family:'Oswald',sans-serif">Tylko właściciel / małżonek</span>
                <?php else: ?>
                    <span style="color:#555;font-size:.75em;font-family:'Oswald',sans-serif">Wkrótce</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php for($i=0;$i<$wolne_sloty_pokoje;$i++): ?><div class="slot-pusty">Puste pomieszczenie</div><?php endfor; ?>
        <?php else: ?>
            <div class="slot-niedostepny">Brak miejsca na pokoje</div>
        <?php endif; ?>
    </div>

    <!-- Lokatorzy -->
    <div class="infra-karta">
        <div class="infra-tytul">
            👥 <?php echo $jestem_wlascicielem ? 'Moi lokatorzy' : 'Współlokatorzy'; ?>
            <b><?php echo $jestem_wlascicielem ? $ile_lokatorow : '?'; ?>/<?php echo $moj_dom['lokatorzy']; ?></b>
        </div>

        <?php if ($moj_dom['lokatorzy'] == 0): ?>
            <div class="slot-niedostepny">Zbyt mała nieruchomość na lokatorów</div>

        <?php elseif ($jestem_wlascicielem): ?>
            <?php foreach($lokatorzy as $l):
                $av = !empty($l['avatar']) ? htmlspecialchars($l['avatar']) : "https://via.placeholder.com/100/0a0a0a/333?text=?";
            ?>
            <div class="lokator-box">
                <div class="lok-left">
                    <div class="lok-avatar" style="background-image:url('<?php echo $av; ?>')"></div>
                    <div class="lok-info">
                        <div class="lok-login"><?php echo htmlspecialchars($l['login']); ?></div>
                        <div class="lok-meta"><?php echo htmlspecialchars($l['klasa']); ?> · Poziom <?php echo $l['poziom']; ?></div>
                    </div>
                </div>
                <form method="POST" style="margin:0" onsubmit="return confirm('Wyrzucić <?php echo htmlspecialchars($l['login']); ?> na ulicę?')">
                    <input type="hidden" name="lokator_id" value="<?php echo $l['lokator_id']; ?>">
                    <button type="submit" name="wyrzuc_lokatora" class="btn-wyrzuc">Wyrzuć</button>
                </form>
            </div>
            <?php endforeach; ?>

            <?php if ($wolne_sloty_lokatorzy > 0): ?>
            <form method="POST" class="form-zapros">
                <div style="color:#dd88ff;font-family:'Oswald',sans-serif;font-size:.9em;text-transform:uppercase;margin-bottom:8px;letter-spacing:1px">📨 Zaproś nowego lokatora</div>
                <input type="number" name="cel_id" placeholder="ID gracza" required>
                <textarea name="wiadomosc" placeholder="Wiadomość (opcjonalnie): np. 'Witaj, mam wolny pokój'..." maxlength="500"></textarea>
                <button type="submit" name="zapros_lokatora" class="btn-zapros">Wyślij zaproszenie</button>
            </form>
            <?php else: ?>
            <div class="slot-pusty" style="margin-top:10px">Wszystkie miejsca zajęte</div>
            <?php endif; ?>

        <?php else: // jestem lokatorem ?>
            <div class="slot-zbudowany" style="border-color:rgba(221,136,255,.5);background:rgba(221,136,255,.08)">
                <div class="slot-nazwa">
                    <span class="slot-ikona">🏠</span>
                    <div>
                        <div style="color:#dd88ff;font-size:1em">Mieszkasz u <?php echo htmlspecialchars($jestem_lokatorem['login_wlasciciela']); ?></div>
                        <div style="font-size:.78em;color:#666;margin-top:2px">Od <?php echo date('d.m.Y', strtotime($jestem_lokatorem['data_wprowadzenia'])); ?></div>
                    </div>
                </div>
                <form method="POST" style="margin:0" onsubmit="return confirm('Na pewno chcesz się wyprowadzić?')">
                    <button type="submit" name="wyprowadz_sie" class="btn-wyrzuc">Wyprowadź się</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ KATALOG BUDOWY (tylko właściciel) ══ -->
<?php if ($jestem_wlascicielem): ?>
<div class="sekcja-tytul">🔨 Rozbudowa domu — katalog dla: <?php echo $gracz['klasa']; ?></div>
<div class="status-box">
    <span>Pojemność domu: <b><?php echo $moj_dom['pokoi']; ?> pokoi</b></span>
    <span>Wolne sloty: <b style="color:<?php echo $wolne_sloty_pokoje>0?'#00ff88':'#ff5555'; ?>"><?php echo $wolne_sloty_pokoje; ?></b></span>
</div>

<div class="pokoje-grid">
<?php foreach($katalog_pokoi as $nazwa => $dane):
    if ($dane['klasa'] !== $gracz['klasa']) continue;
    $czy_ma = in_array($nazwa, $posiadane_pokoje);
    $wym_html = ""; $braki = false;
    foreach ($dane['wymagania'] as $u => $l) {
        $moj = $posiadane_um[$u] ?? 0;
        if ($moj < $l) { $braki = true; $wym_html .= "<div class='wym-brak'>$u ($moj/$l)</div>"; }
        else           $wym_html .= "<div class='wym-ok'>$u ($moj/$l) ✓</div>";
    }
    $stac = ($gracz['gotowka'] >= $dane['koszt']);
    $moze = (!$braki && $stac && $wolne_sloty_pokoje > 0 && !$czy_ma);
?>
    <div class="pokoj-k <?php echo $czy_ma?'zbudowany':''; ?>">
        <div class="pk-head">
            <div class="pk-tytul"><?php echo $dane['ikona']; ?> <?php echo htmlspecialchars($nazwa); ?></div>
            <?php if (!$czy_ma): ?><div class="pk-koszt"><?php echo number_format($dane['koszt'],0,'','&nbsp;'); ?>$</div><?php endif; ?>
        </div>
        <div class="pk-opis"><?php echo $dane['opis']; ?></div>
        <?php if (!$czy_ma): ?><div class="pk-wymagania"><?php echo $wym_html; ?></div><?php endif; ?>

        <?php if ($czy_ma): ?>
            <div class="btn-zbudowany">✓ Zbudowane</div>
        <?php elseif ($moze): ?>
            <form method="POST" style="margin:0">
                <input type="hidden" name="pokoj" value="<?php echo htmlspecialchars($nazwa); ?>">
                <button type="submit" name="zbuduj_pokoj" class="btn-buduj">🔨 Rozpocznij budowę</button>
            </form>
        <?php elseif (!$stac): ?><div class="btn-brak">Brak gotówki</div>
        <?php elseif ($wolne_sloty_pokoje <= 0): ?><div class="btn-brak">Brak miejsca</div>
        <?php else: ?><div class="btn-brak">Za mały skill</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php endif; // koniec katalog (tylko właściciel) ?>

<?php else: // bezdomny ?>
<div class="blad" style="padding:30px;font-size:1.05em">
    😔 Jesteś bezdomny. Kup nieruchomość w <a href="game.php?page=miasto" style="color:#ff9999">Urzędzie Miasta</a> lub poczekaj na zaproszenie od innego gracza.
</div>
// Bonus spania
$bonus_snu = round($bonus_snu * pochodzenie_bonus($gracz_r, 'mieszkanie_sen_bonus_mult', 1.0));

// Dodatkowy slot lokatora
$max_lokatorow = $nieruchomosc['sloty_lokatorow'] + pochodzenie_bonus($gracz_r, 'mieszkanie_lokator_slot_bonus', 0);
<?php endif; ?>