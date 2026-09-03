<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";
$log_akcji = [];
$event_html = "";
$minigra = null; // Jeśli odpali się mini-gra

// ═══════════════════════════════════════════════════════════════
// KATALOG DZIELNIC
// ═══════════════════════════════════════════════════════════════
$dzielnice = [
    "Staten Island" => [
        "ikona"     => "🏭",
        "nazwa_en"  => "Staten Island Landfill",
        "opis"      => "Największe wysypisko miasta. Pełno śmieci, ale podstawowe surowce w obfitości.",
        "kolor"     => "#8b8b8b",
        "kolor_rgb" => "139,139,139",
        "wymagany_skill" => 0,
        "mnoznik_stali"  => 1.3, "mnoznik_czesci" => 1.0, "mnoznik_syn" => 0.8, "mnoznik_elek" => 0.7,
        "event_szansa"   => 15,
        "flavor"         => "Zapach zgniłych owoców i morskiej bryzy. Mewy krzyczą."
    ],
    "Brooklyn Navy Yard" => [
        "ikona"     => "⚓",
        "nazwa_en"  => "Brooklyn Navy Yard",
        "opis"      => "Opuszczona stocznia wojskowa. Zardzewiałe okręty pełne części mechanicznych.",
        "kolor"     => "#4a6b8b",
        "kolor_rgb" => "74,107,139",
        "wymagany_skill" => 10,
        "mnoznik_stali"  => 1.5, "mnoznik_czesci" => 1.6, "mnoznik_syn" => 1.0, "mnoznik_elek" => 0.9,
        "event_szansa"   => 20,
        "flavor"         => "Woda bije o zardzewiałe kadłuby. Czasem coś pod powierzchnią się rusza."
    ],
    "Bronx Garages" => [
        "ikona"     => "🚗",
        "nazwa_en"  => "Bronx Chop-Shop District",
        "opis"      => "Dziesiątki garaży rozbieranych aut. Tutaj znajdziesz solidne części mechaniczne.",
        "kolor"     => "#a0522d",
        "kolor_rgb" => "160,82,45",
        "wymagany_skill" => 20,
        "mnoznik_stali"  => 1.1, "mnoznik_czesci" => 2.0, "mnoznik_syn" => 1.2, "mnoznik_elek" => 1.1,
        "event_szansa"   => 25,
        "flavor"         => "Zapach oleju silnikowego. W oddali rewyje silnika i salsa z radia."
    ],
    "Queens Hospital" => [
        "ikona"     => "🏥",
        "nazwa_en"  => "Queens Medical Ruins",
        "opis"      => "Ruiny szpitala. Leki, implanty, syntetyki medyczne. Ale coś tu jeszcze żyje.",
        "kolor"     => "#4aa884",
        "kolor_rgb" => "74,168,132",
        "wymagany_skill" => 35,
        "mnoznik_stali"  => 0.7, "mnoznik_czesci" => 1.0, "mnoznik_syn" => 2.5, "mnoznik_elek" => 1.5,
        "event_szansa"   => 30,
        "flavor"         => "Formaldehyd i krew. Na ścianach stare karty pacjentów."
    ],
    "Chinatown" => [
        "ikona"     => "🏮",
        "nazwa_en"  => "Chinatown Underground",
        "opis"      => "Ukryte magazyny triad. Kontrabanda, elektronika z Tajwanu, pirackie układy.",
        "kolor"     => "#c73e3e",
        "kolor_rgb" => "199,62,62",
        "wymagany_skill" => 50,
        "mnoznik_stali"  => 0.8, "mnoznik_czesci" => 1.3, "mnoznik_syn" => 1.5, "mnoznik_elek" => 2.2,
        "event_szansa"   => 35,
        "flavor"         => "Neonowe szyldy po mandaryńsku. Dym z woka i coś mocniejszego."
    ],
    "Wall Street Ruins" => [
        "ikona"     => "💼",
        "nazwa_en"  => "Financial District Ruins",
        "opis"      => "Co zostało po Krachu. Serwerownie banków, superkomputery, drogie układy.",
        "kolor"     => "#4a6b6b",
        "kolor_rgb" => "74,107,107",
        "wymagany_skill" => 70,
        "mnoznik_stali"  => 1.0, "mnoznik_czesci" => 1.5, "mnoznik_syn" => 1.2, "mnoznik_elek" => 3.0,
        "event_szansa"   => 40,
        "flavor"         => "Porzucone teczki i skórzane fotele. Drony ochrony nadal patrolują."
    ],
    "Manhattan Metro" => [
        "ikona"     => "🚇",
        "nazwa_en"  => "Manhattan Metro Tunnels",
        "opis"      => "Zawalone tunele metra. Niebezpiecznie, ale artefakty są legendarne.",
        "kolor"     => "#6b3e8b",
        "kolor_rgb" => "107,62,139",
        "wymagany_skill" => 90,
        "mnoznik_stali"  => 1.8, "mnoznik_czesci" => 1.8, "mnoznik_syn" => 1.8, "mnoznik_elek" => 2.5,
        "event_szansa"   => 50,
        "flavor"         => "Ciemność absolutna. Tylko światło lampy czołowej i odgłos kapiącej wody."
    ]
];

// ═══════════════════════════════════════════════════════════════
// KATALOG HOTSPOTÓW (typy punktów na mapie)
// ═══════════════════════════════════════════════════════════════
$hotspoty = [
    "kontener"  => ["nazwa" => "Stary Kontener",    "ikona" => "📦", "focus" => "stal",    "mnoznik" => 1.2],
    "wrak"      => ["nazwa" => "Wrak Pojazdu",      "ikona" => "🚙", "focus" => "czesci",  "mnoznik" => 1.4],
    "sejf"      => ["nazwa" => "Sejf",              "ikona" => "🔐", "focus" => "elek",    "mnoznik" => 1.8, "minigra" => true],
    "rura"      => ["nazwa" => "Rury Kanalizacji",  "ikona" => "🕳️", "focus" => "syn",     "mnoznik" => 1.3],
    "stos"      => ["nazwa" => "Stos Złomu",        "ikona" => "🗑️", "focus" => "mixed",   "mnoznik" => 1.0]
];

// ═══════════════════════════════════════════════════════════════
// KATALOG NARZĘDZI
// ═══════════════════════════════════════════════════════════════
$narzedzia_def = [
    "Magnes Przemysłowy" => ["ikona"=>"🧲","opis"=>"+50% Stalowego Złomu","kolor"=>"#aaa"],
    "Wykrywacz EMP"      => ["ikona"=>"📡","opis"=>"+60% Elektroniki",    "kolor"=>"#ffd700"],
    "Pas Hazmat"         => ["ikona"=>"☢️","opis"=>"Wstęp do skażonych stref (Queens, Metro)", "kolor"=>"#00ff88"],
    "Laserowy Rozcinak"  => ["ikona"=>"🔦","opis"=>"Ułatwia otwieranie sejfów","kolor"=>"#ff3333"],
    "Skaner DNA"         => ["ikona"=>"🧬","opis"=>"+80% Kevlaru i Syntetyków","kolor"=>"#00ffcc"],
];

// ═══════════════════════════════════════════════════════════════
// POBIERANIE DANYCH GRACZA
// ═══════════════════════════════════════════════════════════════
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$odblokowane = json_decode($gracz['odblokowane_dzielnice'] ?? '["Staten Island"]', true);
$skill = (float)$gracz['umiejetnosc_szabrowania'];

// Automatyczne odblokowywanie dzielnic po osiągnięciu skilla
$nowo_odblokowane = [];
foreach ($dzielnice as $nz => $d) {
    if ($skill >= $d['wymagany_skill'] && !in_array($nz, $odblokowane)) {
        $odblokowane[] = $nz;
        $nowo_odblokowane[] = $nz;
    }
}
if (!empty($nowo_odblokowane)) {
    $json_odbl = $polaczenie->real_escape_string(json_encode($odblokowane));
    $polaczenie->query("UPDATE gracze SET odblokowane_dzielnice='$json_odbl' WHERE id=$id_gracza");
    foreach ($nowo_odblokowane as $nz)
        $komunikat .= "<div class='sukces'>🔓 Odblokowano nową strefę: <b>".$dzielnice[$nz]['nazwa_en']."</b>!</div>";
}

$aktywna = $gracz['aktywna_dzielnica'] ?? 'Staten Island';
if (!in_array($aktywna, $odblokowane)) $aktywna = 'Staten Island';
$D = $dzielnice[$aktywna];

// Pobierz posiadane narzędzia
$narzedzia_posiadane = [];
$q_nar = $polaczenie->query("SELECT * FROM narzedzia_gracza WHERE gracz_id=$id_gracza");
if ($q_nar) while($r = $q_nar->fetch_assoc()) $narzedzia_posiadane[] = $r;

// ═══════════════════════════════════════════════════════════════
// ZMIANA DZIELNICY
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zmien_dzielnice'])) {
    $nowa = $_POST['nowa_dzielnica'];
    if (in_array($nowa, $odblokowane) && isset($dzielnice[$nowa])) {
        $nowa_esc = $polaczenie->real_escape_string($nowa);
        $polaczenie->query("UPDATE gracze SET aktywna_dzielnica='$nowa_esc' WHERE id=$id_gracza");
        echo "<script>location.href='game.php?page=zlomowisko';</script>"; exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// AKTYWACJA NARZĘDZIA
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uzyj_narzedzia'])) {
    $nid = (int)$_POST['narzedzie_id'];
    $n = $polaczenie->query("SELECT * FROM narzedzia_gracza WHERE id=$nid AND gracz_id=$id_gracza")->fetch_assoc();
    if ($n) {
        $nn = $polaczenie->real_escape_string($n['nazwa']);
        $polaczenie->query("UPDATE gracze SET aktywne_narzedzie='$nn', narzedzie_trwalosc={$n['trwalosc_aktualna']} WHERE id=$id_gracza");
        echo "<script>location.href='game.php?page=zlomowisko';</script>"; exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zdjecie_narzedzia'])) {
    $polaczenie->query("UPDATE gracze SET aktywne_narzedzie=NULL, narzedzie_trwalosc=0 WHERE id=$id_gracza");
    echo "<script>location.href='game.php?page=zlomowisko';</script>"; exit;
}

// ═══════════════════════════════════════════════════════════════
// ROZWIĄZANIE EVENTU (wcześniej triggnięty)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_wybor'])) {
    $event_typ = $_POST['event_typ'];
    $wybor = $_POST['event_wybor'];

    switch($event_typ) {
        case 'patrol':
            if ($wybor == 'uciekaj') {
                $polaczenie->query("UPDATE gracze SET energia_aktualna = GREATEST(0, energia_aktualna - 5) WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>🏃 Przeskoczyłeś ogrodzenie i zniknąłeś w tłumie. <span style='color:#aaa'>(-5 EN)</span></div>";
            } elseif ($wybor == 'przekup') {
                if ($gracz['gotowka'] >= 300) {
                    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - 300 WHERE id=$id_gracza");
                    $komunikat = "<div class='sukces'>💵 Funkcjonariusz wziął 300$ i odszedł. Rano nie pamięta twarzy. <span style='color:#aaa'>(-300$)</span></div>";
                } else {
                    $polaczenie->query("UPDATE gracze SET hp_aktualne = GREATEST(1, hp_aktualne - 20), gotowka = GREATEST(0, gotowka - 100) WHERE id=$id_gracza");
                    $komunikat = "<div class='blad'>🚔 Nie masz kasy. Pałka, kajdanki, mandat. <span style='color:#aaa'>(-20 HP, -100$)</span></div>";
                }
            }
            break;
        case 'rywal':
            if ($wybor == 'walcz') {
                $twoja = $gracz['sila'] + rand(1,10);
                $rywala = 15 + rand(1,10);
                if ($twoja >= $rywala) {
                    $lup = rand(20,50);
                    $polaczenie->query("UPDATE gracze SET zlom_stalowy = zlom_stalowy + $lup, hp_aktualne = GREATEST(1, hp_aktualne - 10) WHERE id=$id_gracza");
                    $komunikat = "<div class='sukces'>👊 Powaliłeś go na ziemię. Zabrałeś jego plecak. <span style='color:#aaa'>(+$lup Stali, -10 HP)</span></div>";
                } else {
                    $polaczenie->query("UPDATE gracze SET hp_aktualne = GREATEST(1, hp_aktualne - 30) WHERE id=$id_gracza");
                    $komunikat = "<div class='blad'>💢 Miał nóż. Ledwo się wymknąłeś. <span style='color:#aaa'>(-30 HP)</span></div>";
                }
            } elseif ($wybor == 'wspolpraca') {
                $polaczenie->query("UPDATE gracze SET czesci_mechaniczne = czesci_mechaniczne + 5, syntetyki = syntetyki + 3 WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>🤝 Pokazał ci lepsze miejsce. Wymieniliście się kontaktami. <span style='color:#aaa'>(+5 Części, +3 Kevlaru)</span></div>";
            } elseif ($wybor == 'ignoruj') {
                $komunikat = "<div class='sukces'>👤 Przeszedłeś obok bez słowa. Każdy tu robi swoje.</div>";
            }
            break;
        case 'zwloki':
            if ($wybor == 'przeszukaj') {
                $kasa = rand(50,200);
                $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $kasa WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>💀 W portfelu $kasa$, zdjęcie dziewczyny, list pożegnalny. Zabrałeś kasę. <span style='color:#aaa'>(+$kasa$)</span></div>";
            } elseif ($wybor == 'zostaw') {
                $polaczenie->query("UPDATE gracze SET exp = exp + 10 WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>🕊️ Zostawiłeś go w spokoju. Czasem człowiek musi zostać człowiekiem. <span style='color:#aaa'>(+10 EXP)</span></div>";
            }
            break;
        case 'szczury':
            $polaczenie->query("UPDATE gracze SET hp_aktualne = GREATEST(1, hp_aktualne - 15) WHERE id=$id_gracza");
            if ($wybor == 'walcz') {
                $lup = rand(1,3);
                $polaczenie->query("UPDATE gracze SET syntetyki = syntetyki + $lup WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>🐀 Rozpędziłeś stado. W ich norze znalazłeś kevlar. <span style='color:#aaa'>(-15 HP, +$lup Kevlar)</span></div>";
            } else {
                $komunikat = "<div class='blad'>🐀 Uciekłeś, ale jeden cię ugryzł. <span style='color:#aaa'>(-15 HP)</span></div>";
            }
            break;
        case 'paczka':
            if ($wybor == 'bierz') {
                $elek = rand(3,8);
                $polaczenie->query("UPDATE gracze SET elektronika = elektronika + $elek WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>📻 Paczka pełna układów scalonych. Mafia się kiedyś dopomni. <span style='color:#aaa'>(+$elek Elektroniki)</span></div>";
            } else {
                $komunikat = "<div class='sukces'>🚶 Lepiej nie wchodzić w drogę rodzinie Tomasino.</div>";
            }
            break;
        case 'przechodzien':
            if ($wybor == 'pomoz') {
                $polaczenie->query("UPDATE gracze SET gotowka = gotowka + 150, exp = exp + 15 WHERE id=$id_gracza");
                $komunikat = "<div class='sukces'>🎫 Pomogłeś staruszce znaleźć zgubiony naszyjnik. Dała ci 150$. <span style='color:#aaa'>(+150$, +15 EXP)</span></div>";
            } else {
                $komunikat = "<div class='sukces'>🚶 Nie twoja sprawa.</div>";
            }
            break;
    }
    // Odśwież gracza
    $gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
}

// ═══════════════════════════════════════════════════════════════
// GŁÓWNA AKCJA — SZABROWANIE HOTSPOTU
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['szabruj_hotspot'])) {
    $hs = $_POST['hotspot'];
    $koszt = isset($_POST['energia']) ? max(1, min(20, (int)$_POST['energia'])) : 3;

    if (!isset($hotspoty[$hs])) {
        $komunikat = "<div class='blad'>Nieznany punkt.</div>";
    } elseif ($gracz['energia_aktualna'] < $koszt) {
        $komunikat = "<div class='blad'>Brakuje energii. Potrzeba minimum $koszt EN.</div>";
    } elseif ($gracz['klasa'] !== 'Szabrownik') {
        // Kara dla amatorów
        $stal = floor($koszt * 1.3);
        $polaczenie->query("UPDATE gracze SET energia_aktualna=energia_aktualna-$koszt, zlom_stalowy=zlom_stalowy+$stal WHERE id=$id_gracza");
        $komunikat = "<div class='blad'>Grzebiesz bez sensu. Bez wprawy Szabrownika znajdujesz tylko podstawowy złom. <br><span style='color:#aaa'>+$stal Stali, -$koszt EN</span></div>";
        $gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
    } else {
        // ── SZABROWNIK ZE SKILLEM ────────────────────────────────
        $H = $hotspoty[$hs];
        $mnoznik_skilla = 1 + ($skill / 20);
        $mnoznik_hotspotu = $H['mnoznik'];

        // Bonus narzędzia
        $bonus_stal = $bonus_czesci = $bonus_syn = $bonus_elek = 1.0;
        $aktywne_n = $gracz['aktywne_narzedzie'];
        if ($aktywne_n && $gracz['narzedzie_trwalosc'] > 0) {
            switch($aktywne_n) {
                case 'Magnes Przemysłowy':  $bonus_stal = 1.5; break;
                case 'Wykrywacz EMP':       $bonus_elek = 1.6; break;
                case 'Skaner DNA':          $bonus_syn  = 1.8; break;
            }
        }

        // Losowanie łupów
        $znal_stal = $znal_czesci = $znal_syn = $znal_elek = 0;
        $focus = $H['focus'];

        // Baza stali — zawsze jakaś jest
        $znal_stal = ceil(rand(2,4) * $koszt * $mnoznik_skilla * $D['mnoznik_stali'] * $mnoznik_hotspotu * $bonus_stal);
        if ($focus == 'stal') $znal_stal = ceil($znal_stal * 1.3);

        // Reszta surowców zależnie od hotspotu i dzielnicy
        for ($i = 0; $i < $koszt; $i++) {
            $p_cz = (55 + floor($gracz['inteligencja']/5)) * $D['mnoznik_czesci'];
            $p_sy = (40 + floor($gracz['inteligencja']/5)) * $D['mnoznik_syn'];
            $p_el = (18 + floor($gracz['inteligencja']/5)) * $D['mnoznik_elek'];
            if ($focus == 'czesci') $p_cz *= 1.5;
            if ($focus == 'syn')    $p_sy *= 1.5;
            if ($focus == 'elek')   $p_el *= 1.5;

            if (rand(1,100) <= $p_cz) $znal_czesci += ceil(rand(1,2) * $bonus_czesci);
            if (rand(1,100) <= $p_sy) $znal_syn    += ceil(rand(1,2) * $bonus_syn);
            if (rand(1,100) <= $p_el) $znal_elek   += ceil(1 * $bonus_elek);
        }

        // Skill + EXP
        $wahanie = rand(50,180)/100;
        $kara_poziom = max(0.2, 1 - ($skill / 120));
        $przyrost_skilla = round($koszt * 0.1 * $wahanie * $kara_poziom, 2);
        $exp = rand(3,5) * $koszt;

        // Trwałość narzędzia -5
        $nowa_trw = ($aktywne_n) ? max(0, $gracz['narzedzie_trwalosc'] - 5) : 0;
        $set_nar = $aktywne_n ? ", narzedzie_trwalosc=$nowa_trw" : "";

        // Licznik akcji
        $licznik = $gracz['szabrowanie_licznik_akcji'] + 1;

        $polaczenie->query("UPDATE gracze SET
            energia_aktualna = energia_aktualna - $koszt,
            zlom_stalowy     = zlom_stalowy + $znal_stal,
            czesci_mechaniczne = czesci_mechaniczne + $znal_czesci,
            syntetyki        = syntetyki + $znal_syn,
            elektronika      = elektronika + $znal_elek,
            exp              = exp + $exp,
            umiejetnosc_szabrowania = umiejetnosc_szabrowania + $przyrost_skilla,
            szabrowanie_licznik_akcji = $licznik
            $set_nar
            WHERE id=$id_gracza");

        // Jeśli trwałość spadła do 0 — zniszcz narzędzie
        if ($aktywne_n && $nowa_trw <= 0) {
            $polaczenie->query("DELETE FROM narzedzia_gracza WHERE gracz_id=$id_gracza AND nazwa='".$polaczenie->real_escape_string($aktywne_n)."' LIMIT 1");
            $polaczenie->query("UPDATE gracze SET aktywne_narzedzie=NULL, narzedzie_trwalosc=0 WHERE id=$id_gracza");
            $log_akcji[] = "<span style='color:#ff6666'>💥 Twoje <b>$aktywne_n</b> rozpadło się w rękach!</span>";
        }

        // ══ HOOK: POSTĘP KONTRAKTÓW KLASOWYCH (surowce + dzielnica) ══
        $kontrakty_akt = $polaczenie->query("SELECT id,typ_celu,cel_parametr,cel_ilosc,postep
            FROM kontrakty_klasowe
            WHERE gracz_id=$id_gracza AND status='aktywny' AND deadline > NOW()");
        if ($kontrakty_akt) {
            while ($kak = $kontrakty_akt->fetch_assoc()) {
                $dodaj = 0;
                switch ($kak['typ_celu']) {
                    case 'zbierz_stal':    $dodaj = $znal_stal;   break;
                    case 'zbierz_czesci':  $dodaj = $znal_czesci; break;
                    case 'zbierz_syn':     $dodaj = $znal_syn;    break;
                    case 'zbierz_elek':    $dodaj = $znal_elek;   break;
                    case 'szabruj_w_dzielnicy':
                        if ($kak['cel_parametr'] == $aktywna) $dodaj = 1;
                        break;
                }
                if ($dodaj > 0) {
                    $polaczenie->query("UPDATE kontrakty_klasowe SET postep = LEAST(cel_ilosc, postep+$dodaj) WHERE id={$kak['id']}");
                    $log_akcji[] = "<span style='color:#dd88ff;font-size:.85em'>📋 Postęp kontraktu: +$dodaj</span>";
                }
            }
        }

        // Log znalezisk
        if ($znal_stal>0)   $log_akcji[] = "<b style='color:#aaa'>+$znal_stal Stalowy Złom 🔩</b>";
        if ($znal_czesci>0) $log_akcji[] = "<b style='color:#ccc'>+$znal_czesci Części Mechaniczne ⚙️</b>";
        if ($znal_syn>0)    $log_akcji[] = "<b style='color:#00ffcc'>+$znal_syn Kevlar / Syntetyki 🧵</b>";
        if ($znal_elek>0)   $log_akcji[] = "<b style='color:#ffd700'>+$znal_elek Elektronika 🔋</b>";
        $log_akcji[] = "<span style='color:#888;font-size:.9em'>+$exp EXP, +$przyrost_skilla Skilla</span>";

        // ── SZANSA NA ARTEFAKT (z bonusem od Inteligencji!) ──────
        $sz_art = min(85, (1.5 * $koszt) + ($skill * 0.5) + ($D['event_szansa'] * 0.3) + ($gracz['inteligencja'] * 0.15));
        if (rand(1,100) <= $sz_art) {
            $qa = $polaczenie->query("SELECT loot_nazwa FROM wrogowie WHERE loot_nazwa IS NOT NULL AND loot_nazwa != '' ORDER BY RAND() LIMIT 1");
            if ($qa && $qa->num_rows > 0) {
                $art = $polaczenie->real_escape_string($qa->fetch_assoc()['loot_nazwa']);
                $log_akcji[] = "<div style='margin-top:10px;padding:12px;background:rgba(221,136,255,.1);border:1px solid rgba(221,136,255,.4);border-radius:6px;color:#dd88ff;font-family:Oswald;text-transform:uppercase;text-align:center;font-size:1.1em'>💎 JACKPOT! Znaleziono artefakt: <b>$art</b></div>";
                $spr = $polaczenie->query("SELECT id FROM przedmioty_gracze WHERE gracz_id=$id_gracza AND nazwa='$art'");
                if ($spr && $spr->num_rows > 0)
                    $polaczenie->query("UPDATE przedmioty_gracze SET ilosc=ilosc+1 WHERE gracz_id=$id_gracza AND nazwa='$art'");
                else
                    $polaczenie->query("INSERT INTO przedmioty_gracze (gracz_id, nazwa, ilosc) VALUES ($id_gracza, '$art', 1)");

                // ══ HOOK: POSTĘP KONTRAKTÓW — ZBIERANIE ARTEFAKTÓW ══
                $polaczenie->query("UPDATE kontrakty_klasowe SET postep = LEAST(cel_ilosc, postep+1)
                    WHERE gracz_id=$id_gracza AND status='aktywny' AND typ_celu='zbierz_artefakt' AND deadline > NOW()");
            }
        }

        // ── SZANSA NA EVENT (po 3 akcji warm-up) ─────────────────
        if ($licznik > 3 && rand(1,100) <= $D['event_szansa']) {
            $eventy_pula = ['patrol','rywal','zwloki','szczury','paczka','przechodzien'];
            $ev = $eventy_pula[array_rand($eventy_pula)];
            $event_html = $ev; // renderujemy niżej
        }

        // ── MINI-GRA JEŚLI HOTSPOT = SEJF ─────────────────────────
        if ($hs == 'sejf' && rand(1,100) <= 60) {
            $minigra = 'sejf';
        }

        $gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
        // Szansa na surowce w skażonych dzielnicach (Norweg)
if ($dzielnica_skazona) {
    $szansa_surowce += pochodzenie_bonus($gracz_r, 'szabrowanie_skazone_szansa_abs', 0);
}

// Norweg nie potrzebuje Hazmatu
if (ma_pochodzenie($gracz_r, 'NORWEGIA')) {
    $wymaga_hazmat = false;
}

// Patrol NYPD (Czech)
$szansa_patrol = round($szansa_patrol * pochodzenie_bonus($gracz_r, 'event_patrol_szansa_mult', 1.0));

// Nagrody z eventów (Czech)
$nagroda_event = round($nagroda_event * pochodzenie_bonus($gracz_r, 'event_nagrody_mult', 1.0));
    }
}

// ═══════════════════════════════════════════════════════════════
// ROZWIĄZANIE MINI-GRY SEJFU
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['minigra_sejf'])) {
    $kod_wybor = (int)$_POST['kod_wybor'];
    $kod_poprawny = (int)$_POST['kod_poprawny'];
    if ($kod_wybor === $kod_poprawny) {
        $nagroda_elek = rand(8,20); $nagroda_kasa = rand(200,600);
        $polaczenie->query("UPDATE gracze SET elektronika=elektronika+$nagroda_elek, gotowka=gotowka+$nagroda_kasa WHERE id=$id_gracza");
        $komunikat = "<div class='sukces'>🔓 Sejf otwarty! Trafiłeś kombinację! <span style='color:#aaa'>(+$nagroda_elek Elektroniki, +$nagroda_kasa\$)</span></div>";
    } else {
        $komunikat = "<div class='blad'>💥 Sejf wybuchł! Wpisałeś złą kombinację i system obronny zadziałał. Poprawny kod to był: <b>$kod_poprawny</b></div>";
        $polaczenie->query("UPDATE gracze SET hp_aktualne = GREATEST(1, hp_aktualne - 25) WHERE id=$id_gracza");
    }
    $gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
}
?>

<style>
/* ══════════════════════════════════════════════════════
   ZŁOMOWISKO — STYL
══════════════════════════════════════════════════════ */
.z-header{
    background:
        linear-gradient(135deg,rgba(0,0,0,.5),rgba(<?php echo $D['kolor_rgb']; ?>,.3),rgba(0,0,0,.7)),
        url('img/dzielnice/<?php echo strtolower(str_replace(' ','_',$aktywna)); ?>.jpg') center/cover;
    background-color:rgba(<?php echo $D['kolor_rgb']; ?>,.15);
    padding:28px 32px;border-radius:12px;margin-bottom:22px;
    border:1px solid rgba(<?php echo $D['kolor_rgb']; ?>,.4);
    box-shadow:0 0 30px rgba(<?php echo $D['kolor_rgb']; ?>,.15);
    position:relative;overflow:hidden;
}
.z-header::before{
    content:'';position:absolute;inset:0;
    background:repeating-linear-gradient(
        45deg,transparent,transparent 20px,
        rgba(<?php echo $D['kolor_rgb']; ?>,.03) 20px,rgba(<?php echo $D['kolor_rgb']; ?>,.03) 21px);
    pointer-events:none;
}
.z-header-content{position:relative;z-index:1}
.z-ikona-duza{font-size:3em;margin-bottom:4px;display:block;filter:drop-shadow(0 0 15px <?php echo $D['kolor']; ?>)}
.z-tytul{font-family:'Oswald',sans-serif;font-size:2.4em;margin:0;
    color:<?php echo $D['kolor']; ?>;
    text-transform:uppercase;letter-spacing:2px;
    text-shadow:0 0 20px rgba(<?php echo $D['kolor_rgb']; ?>,.8)}
.z-opis{color:#ccc;font-size:.95em;margin-top:8px;max-width:700px}
.z-flavor{color:#666;font-style:italic;font-size:.85em;margin-top:10px;border-left:2px solid rgba(<?php echo $D['kolor_rgb']; ?>,.5);padding-left:10px}

/* ── PASEK INFO ─────────────────────────────────────── */
.pasek-info{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;
    background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);
    border-radius:8px;padding:14px;margin-bottom:22px;
}
.pi-item{text-align:center}
.pi-label{font-size:.7em;color:#555;text-transform:uppercase;font-family:'Oswald',sans-serif;letter-spacing:1px;margin-bottom:4px}
.pi-val{font-family:'Oswald',sans-serif;font-size:1.3em;font-weight:700}

/* ── SELEKTOR DZIELNICY ─────────────────────────────── */
.dz-selektor-tytul{color:#555;font-family:'Oswald',sans-serif;text-transform:uppercase;
    letter-spacing:2px;font-size:.85em;margin-bottom:10px}
.dz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:24px}
.dz-karta{
    background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);
    border-radius:8px;padding:14px 10px;text-align:center;cursor:pointer;
    transition:.3s;position:relative;overflow:hidden;
}
.dz-karta.zablokowana{opacity:.35;cursor:not-allowed;filter:grayscale(1)}
.dz-karta:not(.zablokowana):hover{transform:translateY(-3px)}
.dz-karta.aktywna{border-width:2px}
.dz-ikona{font-size:1.8em;margin-bottom:6px;filter:drop-shadow(0 0 10px currentColor)}
.dz-nazwa{font-family:'Oswald',sans-serif;font-size:.9em;text-transform:uppercase;letter-spacing:.5px}
.dz-lock{font-size:.7em;color:#666;margin-top:4px}

/* ── MAPA HOTSPOTÓW ─────────────────────────────────── */
.mapa-wrap{
    background:radial-gradient(ellipse at center,rgba(<?php echo $D['kolor_rgb']; ?>,.1),rgba(0,0,0,.6));
    border:1px solid rgba(<?php echo $D['kolor_rgb']; ?>,.3);
    border-radius:10px;padding:22px;margin-bottom:22px;
    position:relative;min-height:280px;
}
.mapa-tytul{font-family:'Oswald',sans-serif;color:<?php echo $D['kolor']; ?>;
    text-transform:uppercase;letter-spacing:2px;font-size:1em;margin-bottom:16px;text-align:center}
.mapa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px}
.hotspot{
    background:rgba(0,0,0,.7);border:2px solid rgba(255,255,255,.08);
    border-radius:10px;padding:18px 12px;text-align:center;cursor:pointer;
    transition:.3s;position:relative;
}
.hotspot:hover{
    transform:scale(1.05);
    border-color:<?php echo $D['kolor']; ?>;
    box-shadow:0 0 20px rgba(<?php echo $D['kolor_rgb']; ?>,.5);
}
.hotspot-ikona{font-size:2.5em;margin-bottom:8px;display:block;
    filter:drop-shadow(0 0 10px rgba(<?php echo $D['kolor_rgb']; ?>,.6));
    animation:hs-pulse 3s infinite;
}
@keyframes hs-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
.hotspot-nazwa{font-family:'Oswald',sans-serif;font-size:.95em;text-transform:uppercase;
    color:#ddd;letter-spacing:1px;margin-bottom:4px}
.hotspot-focus{font-size:.75em;color:#666;font-family:'Oswald',sans-serif;text-transform:uppercase}

/* ── NARZĘDZIA ──────────────────────────────────────── */
.narz-panel{
    background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.06);
    border-radius:10px;padding:16px;margin-bottom:22px;
}
.narz-tytul{color:#888;font-family:'Oswald',sans-serif;text-transform:uppercase;
    font-size:.85em;letter-spacing:1.5px;margin-bottom:12px}
.narz-aktywne{
    background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.3);
    border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;
}
.narz-trwalosc{width:120px;background:rgba(0,0,0,.7);height:8px;border-radius:4px;overflow:hidden}
.narz-trwalosc-fill{height:100%;background:linear-gradient(90deg,#ff3333,#ffaa00,#00ff88);transition:width .5s}
.narz-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
.narz-item{
    background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);
    border-radius:6px;padding:10px;display:flex;align-items:center;gap:10px;
    cursor:pointer;transition:.2s;font-size:.85em;
}
.narz-item:hover{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.15)}
.narz-item-ik{font-size:1.5em}

/* ── SUWAK ENERGII ──────────────────────────────────── */
.en-slider-box{
    background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);
    border-radius:8px;padding:14px 18px;margin-bottom:18px;
}
.en-slider-label{display:flex;justify-content:space-between;color:#888;
    font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.85em;margin-bottom:8px}
.en-val{color:<?php echo $D['kolor']; ?>;font-weight:700;font-size:1.1em;
    text-shadow:0 0 8px rgba(<?php echo $D['kolor_rgb']; ?>,.6)}
input[type=range].en-suwak{-webkit-appearance:none;width:100%;background:transparent;height:20px}
input[type=range].en-suwak::-webkit-slider-thumb{
    -webkit-appearance:none;height:18px;width:18px;border-radius:50%;
    background:<?php echo $D['kolor']; ?>;cursor:pointer;margin-top:-6px;
    box-shadow:0 0 10px <?php echo $D['kolor']; ?>;border:1px solid rgba(255,255,255,.3)}
input[type=range].en-suwak::-webkit-slider-runnable-track{
    width:100%;height:6px;background:rgba(255,255,255,.1);border-radius:4px}

/* ── EVENT MODAL ────────────────────────────────────── */
.event-modal{
    background:rgba(5,5,10,.95);border:2px solid rgba(255,170,0,.5);
    border-radius:12px;padding:26px;margin-bottom:22px;
    box-shadow:0 0 40px rgba(255,170,0,.3);
    animation:ev-enter .4s ease-out;
}
@keyframes ev-enter{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.ev-ikona{font-size:2.8em;text-align:center;margin-bottom:10px;filter:drop-shadow(0 0 15px #ffaa00)}
.ev-tytul{font-family:'Oswald',sans-serif;color:#ffaa00;font-size:1.5em;text-transform:uppercase;
    text-align:center;letter-spacing:2px;margin-bottom:10px;text-shadow:0 0 10px rgba(255,170,0,.5)}
.ev-tresc{color:#ccc;text-align:center;font-size:.95em;line-height:1.6;margin-bottom:18px}
.ev-wybory{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.ev-btn{
    background:rgba(0,0,0,.7);border:1px solid rgba(255,170,0,.4);color:#ffaa00;
    padding:12px;font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.9em;
    cursor:pointer;border-radius:6px;transition:.25s;letter-spacing:1px;
}
.ev-btn:hover{background:#ffaa00;color:#000;box-shadow:0 0 15px rgba(255,170,0,.5)}

/* ── MINI-GRA SEJF ─────────────────────────────────── */
.sejf-modal{
    background:rgba(5,5,10,.95);border:2px solid rgba(255,215,0,.5);
    border-radius:12px;padding:26px;margin-bottom:22px;
    box-shadow:0 0 40px rgba(255,215,0,.3);animation:ev-enter .4s ease-out;
}
.sejf-tytul{font-family:'Oswald',sans-serif;color:#ffd700;font-size:1.5em;text-transform:uppercase;
    text-align:center;letter-spacing:2px;margin-bottom:12px;text-shadow:0 0 10px rgba(255,215,0,.5)}
.sejf-tresc{color:#ccc;text-align:center;font-size:.9em;margin-bottom:18px}
.sejf-kody{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
.sejf-btn{background:rgba(0,0,0,.7);border:1px solid rgba(255,215,0,.3);color:#ffd700;
    padding:16px;font-family:'Oswald',sans-serif;font-size:1.3em;font-weight:700;
    cursor:pointer;border-radius:6px;transition:.2s}
.sejf-btn:hover{background:rgba(255,215,0,.2);box-shadow:0 0 15px rgba(255,215,0,.4)}

/* ── LOG AKCJI ──────────────────────────────────────── */
.log-akcji{
    background:rgba(0,0,0,.7);border:1px dashed rgba(<?php echo $D['kolor_rgb']; ?>,.4);
    border-radius:10px;padding:18px;margin-bottom:20px;
    font-family:monospace;font-size:1em;line-height:2;text-align:center;
    animation:ev-enter .5s ease-out;
}

.sukces{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.3);color:#00ff88;
    padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
.blad{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.3);color:#ff6666;
    padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
</style>

<?php if ($gracz['klasa'] !== 'Szabrownik'): ?>
<div class="blad" style="margin-top:20px">
    ⚠️ Nie jesteś Szabrownikiem. Możesz tu grzebać, ale bez wprawy nie znajdziesz niczego wartościowego.
    <br><span style="font-size:.85em">Tylko klasa Szabrownika korzysta w pełni z systemu dzielnic, narzędzi i eventów.</span>
</div>
<?php endif; ?>

<!-- ══ NAGŁÓWEK DZIELNICY ══ -->
<div class="z-header">
    <div class="z-header-content">
        <span class="z-ikona-duza"><?php echo $D['ikona']; ?></span>
        <h1 class="z-tytul"><?php echo $D['nazwa_en']; ?></h1>
        <p class="z-opis"><?php echo $D['opis']; ?></p>
        <p class="z-flavor">❝ <?php echo $D['flavor']; ?> ❞</p>
    </div>
</div>

<?php echo $komunikat; ?>

<!-- ══ PASEK INFO ══ -->
<div class="pasek-info">
    <div class="pi-item"><div class="pi-label">⚡ Energia</div><div class="pi-val" style="color:#00ccff"><?php echo $gracz['energia_aktualna']; ?>/<?php echo $gracz['energia_max']; ?></div></div>
    <div class="pi-item"><div class="pi-label">❤️ HP</div><div class="pi-val" style="color:#ff5555"><?php echo $gracz['hp_aktualne']; ?>/<?php echo $gracz['hp_max']; ?></div></div>
    <div class="pi-item"><div class="pi-label">🧠 INT</div><div class="pi-val" style="color:#ffaa00"><?php echo $gracz['inteligencja']; ?></div></div>
    <div class="pi-item"><div class="pi-label">🔧 Skill</div><div class="pi-val" style="color:#dd88ff"><?php echo number_format($skill,1); ?></div></div>
    <div class="pi-item"><div class="pi-label">🔩 Stal</div><div class="pi-val"><?php echo $gracz['zlom_stalowy']; ?></div></div>
    <div class="pi-item"><div class="pi-label">⚙️ Części</div><div class="pi-val"><?php echo $gracz['czesci_mechaniczne']; ?></div></div>
    <div class="pi-item"><div class="pi-label">🧵 Kevlar</div><div class="pi-val" style="color:#00ffcc"><?php echo $gracz['syntetyki']; ?></div></div>
    <div class="pi-item"><div class="pi-label">🔋 Elek.</div><div class="pi-val" style="color:#ffd700"><?php echo $gracz['elektronika']; ?></div></div>
</div>

<!-- ══ EVENT MODAL (jeśli wylosowany) ══ -->
<?php if ($event_html): ?>
    <?php
    $eventy_data = [
        'patrol'      => ['ikona'=>'🚔','tytul'=>'Patrol NYPD!','tresc'=>'Z oddali słychać syreny. Latarki świecą przez ogrodzenie. Co robisz?','wybory'=>[['uciekaj','🏃 Uciekaj (-5 EN)'],['przekup','💵 Przekup (-300$)']]],
        'rywal'       => ['ikona'=>'👥','tytul'=>'Inny Szabrownik','tresc'=>'Chudy facet w brudnej kurtce grzebie w tym samym stosie. Widzi cię. Co robisz?','wybory'=>[['walcz','👊 Walcz'],['wspolpraca','🤝 Współpraca'],['ignoruj','👤 Ignoruj']]],
        'zwloki'      => ['ikona'=>'💀','tytul'=>'Ciało','tresc'=>'Spod plandeki wystaje ręka. Zimny trup, ma jeszcze portfel. Co robisz?','wybory'=>[['przeszukaj','💰 Przeszukaj'],['zostaw','🕊️ Zostaw w spokoju']]],
        'szczury'     => ['ikona'=>'🐀','tytul'=>'Atak Szczurów!','tresc'=>'Wielkie szczury wychodzą ze ścieków. Jest ich dziesiątki.','wybory'=>[['walcz','⚔️ Walcz'],['uciekaj','🏃 Uciekaj']]],
        'paczka'      => ['ikona'=>'📻','tytul'=>'Paczka Mafii','tresc'=>'Zawinięta w czarną folię. Znaczek rodziny Tomasino. W środku coś drogiego.','wybory'=>[['bierz','💼 Zabieraj'],['zostaw','🚶 Nie tykaj']]],
        'przechodzien'=> ['ikona'=>'🎫','tytul'=>'Staruszka w potrzebie','tresc'=>'Kobieta szuka w śmieciach naszyjnika. "Mój mąż mi go kupił, jeszcze za życia..."','wybory'=>[['pomoz','💖 Pomóż'],['ignoruj','🚶 Twoje sprawy']]]
    ];
    $E = $eventy_data[$event_html];
    ?>
    <div class="event-modal">
        <div class="ev-ikona"><?php echo $E['ikona']; ?></div>
        <div class="ev-tytul"><?php echo $E['tytul']; ?></div>
        <div class="ev-tresc"><?php echo $E['tresc']; ?></div>
        <form method="POST">
            <input type="hidden" name="event_typ" value="<?php echo $event_html; ?>">
            <div class="ev-wybory">
                <?php foreach($E['wybory'] as [$v,$lab]): ?>
                <button type="submit" name="event_wybor" value="<?php echo $v; ?>" class="ev-btn"><?php echo $lab; ?></button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ══ MINI-GRA SEJF ══ -->
<?php if ($minigra == 'sejf'):
    $kod_poprawny = rand(0,9);
    $opcje_kodow = range(0,9);
    shuffle($opcje_kodow);
    $opcje_kodow = array_slice($opcje_kodow, 0, 5);
    if (!in_array($kod_poprawny, $opcje_kodow)) { $opcje_kodow[0] = $kod_poprawny; shuffle($opcje_kodow); }
?>
    <div class="sejf-modal">
        <div style="font-size:2.8em;text-align:center;filter:drop-shadow(0 0 15px #ffd700)">🔐</div>
        <div class="sejf-tytul">Znaleziony Sejf!</div>
        <div class="sejf-tresc">Ostatnia cyfra kodu jest pod napisem "Glory to Wall St." wyrytym w metalu.<br>
            <b style="color:#ffd700">Wskazówka: to <?php
                $podp = $kod_poprawny % 2 == 0 ? "liczba parzysta" : "liczba nieparzysta";
                if ($kod_poprawny >= 5) $podp .= " większa lub równa 5";
                else $podp .= " mniejsza niż 5";
                echo $podp;
            ?>.</b>
        </div>
        <form method="POST">
            <input type="hidden" name="kod_poprawny" value="<?php echo $kod_poprawny; ?>">
            <div class="sejf-kody">
                <?php foreach($opcje_kodow as $k): ?>
                <button type="submit" name="minigra_sejf" value="1" class="sejf-btn" onclick="document.getElementById('kod_wybor_<?php echo $k; ?>').click()"><?php echo $k; ?></button>
                <input type="radio" id="kod_wybor_<?php echo $k; ?>" name="kod_wybor" value="<?php echo $k; ?>" style="display:none">
                <?php endforeach; ?>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ══ LOG AKCJI ══ -->
<?php if (!empty($log_akcji)): ?>
<div class="log-akcji">
    <?php foreach($log_akcji as $l) echo $l."<br>"; ?>
</div>
<?php endif; ?>

<!-- ══ SELEKTOR DZIELNICY ══ -->
<div class="dz-selektor-tytul">🗺️ Dzielnice NYC</div>
<div class="dz-grid">
<?php foreach($dzielnice as $nazwa => $d):
    $odbl = in_array($nazwa, $odblokowane);
    $akt  = ($nazwa == $aktywna);
?>
    <?php if ($odbl): ?>
    <form method="POST" style="margin:0">
        <input type="hidden" name="nowa_dzielnica" value="<?php echo $nazwa; ?>">
        <button type="submit" name="zmien_dzielnice" class="dz-karta<?php echo $akt?' aktywna':''; ?>"
            style="border-color:<?php echo $akt?$d['kolor']:'rgba(255,255,255,.06)'; ?>;color:<?php echo $akt?$d['kolor']:'#aaa'; ?>;background:<?php echo $akt?'rgba('.$d['kolor_rgb'].',.1)':'rgba(0,0,0,.5)'; ?>">
            <div class="dz-ikona"><?php echo $d['ikona']; ?></div>
            <div class="dz-nazwa"><?php echo $nazwa; ?></div>
            <?php if ($akt): ?><div class="dz-lock" style="color:<?php echo $d['kolor']; ?>">● TY TU JESTEŚ</div><?php endif; ?>
        </button>
    </form>
    <?php else: ?>
    <div class="dz-karta zablokowana">
        <div class="dz-ikona">🔒</div>
        <div class="dz-nazwa"><?php echo $nazwa; ?></div>
        <div class="dz-lock">Wymaga skill <?php echo $d['wymagany_skill']; ?></div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
</div>

<!-- ══ NARZĘDZIA ══ -->
<div class="narz-panel">
    <div class="narz-tytul">🛠️ Narzędzia Szabrownika</div>

    <?php if ($gracz['aktywne_narzedzie'] && $gracz['narzedzie_trwalosc'] > 0):
        $N = $narzedzia_def[$gracz['aktywne_narzedzie']] ?? ['ikona'=>'🔧','opis'=>'','kolor'=>'#888'];
    ?>
    <div class="narz-aktywne">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:2em"><?php echo $N['ikona']; ?></span>
            <div>
                <div style="font-family:'Oswald',sans-serif;font-size:1.1em;color:<?php echo $N['kolor']; ?>"><?php echo $gracz['aktywne_narzedzie']; ?></div>
                <div style="font-size:.82em;color:#888"><?php echo $N['opis']; ?></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <div style="text-align:right">
                <div style="font-size:.72em;color:#555;text-transform:uppercase">Trwałość</div>
                <div class="narz-trwalosc"><div class="narz-trwalosc-fill" style="width:<?php echo $gracz['narzedzie_trwalosc']; ?>%"></div></div>
                <div style="font-size:.75em;color:#aaa;margin-top:3px"><?php echo $gracz['narzedzie_trwalosc']; ?>%</div>
            </div>
            <form method="POST" style="margin:0">
                <button type="submit" name="zdjecie_narzedzia" style="background:transparent;border:1px solid rgba(255,68,68,.4);color:#ff6666;padding:6px 12px;border-radius:4px;cursor:pointer;font-family:'Oswald',sans-serif;font-size:.85em">Zdejmij</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($narzedzia_posiadane)): ?>
    <div style="font-size:.75em;color:#555;margin-bottom:8px;text-transform:uppercase;font-family:'Oswald',sans-serif">Dostępne w ekwipunku:</div>
    <div class="narz-grid">
        <?php foreach($narzedzia_posiadane as $n):
            if ($n['nazwa'] == $gracz['aktywne_narzedzie']) continue;
            $ND = $narzedzia_def[$n['nazwa']] ?? ['ikona'=>'🔧','opis'=>'','kolor'=>'#888'];
        ?>
        <form method="POST" style="margin:0">
            <input type="hidden" name="narzedzie_id" value="<?php echo $n['id']; ?>">
            <button type="submit" name="uzyj_narzedzia" class="narz-item" style="width:100%;text-align:left">
                <span class="narz-item-ik"><?php echo $ND['ikona']; ?></span>
                <div>
                    <div style="color:<?php echo $ND['kolor']; ?>"><?php echo $n['nazwa']; ?></div>
                    <div style="font-size:.75em;color:#666"><?php echo $n['trwalosc_aktualna']; ?>%</div>
                </div>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="color:#444;font-size:.85em;font-style:italic;text-align:center;padding:10px">
        Nie masz narzędzi. Inżynierowie tworzą je w Warsztacie — kup na Czarnym Rynku.
    </div>
    <?php endif; ?>
</div>

<!-- ══ SUWAK ENERGII ══ -->
<div class="en-slider-box">
    <div class="en-slider-label">
        <span>Zaangażowana energia na jedną akcję:</span>
        <span class="en-val" id="enVal">3 EN</span>
    </div>
    <input type="range" id="enSuwak" class="en-suwak" min="1" max="<?php echo min(20,$gracz['energia_aktualna']); ?>" value="3"
        oninput="document.getElementById('enVal').innerText=this.value+' EN'; document.querySelectorAll('.hs-en').forEach(e=>e.value=this.value);">
</div>

<!-- ══ MAPA HOTSPOTÓW ══ -->
<div class="mapa-wrap">
    <div class="mapa-tytul">🎯 Wybierz miejsce do przeszukania</div>
    <div class="mapa-grid">
    <?php foreach($hotspoty as $hs_kod => $hs):
        $focus_label = [
            'stal'=>'Głównie stal','czesci'=>'Głównie części','syn'=>'Głównie kevlar',
            'elek'=>'Głównie elektronika','mixed'=>'Miks wszystkiego'
        ][$hs['focus']] ?? '';
    ?>
        <form method="POST" style="margin:0">
            <input type="hidden" name="hotspot" value="<?php echo $hs_kod; ?>">
            <input type="hidden" name="energia" class="hs-en" value="3">
            <button type="submit" name="szabruj_hotspot" class="hotspot" style="width:100%">
                <span class="hotspot-ikona"><?php echo $hs['ikona']; ?></span>
                <div class="hotspot-nazwa"><?php echo $hs['nazwa']; ?></div>
                <div class="hotspot-focus"><?php echo $focus_label; ?></div>
                <?php if (!empty($hs['minigra'])): ?>
                <div style="font-size:.7em;color:#ffd700;margin-top:4px;font-family:Oswald;text-transform:uppercase">🎮 Mini-gra</div>
                <?php endif; ?>
            </button>
        </form>
    <?php endforeach; ?>
    </div>
</div>