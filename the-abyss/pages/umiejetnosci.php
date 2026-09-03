<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// ── DANE GRACZA ──────────────────────────────────────────────────
$wynik = $polaczenie->query("SELECT umiejetnosci, punkty_umiejetnosci FROM gracze WHERE id=$id_gracza");
$gracz_dane = $wynik->fetch_assoc();
$dostepne_pu = (int)$gracz_dane['punkty_umiejetnosci'];
$posiadane_um = !empty($gracz_dane['umiejetnosci']) ? json_decode($gracz_dane['umiejetnosci'], true) : [];

// ── ZAPIS UMIEJĘTNOŚCI ───────────────────────────────────────────
$blad = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['zapisz_umiejetnosci'])) {

    $wydane_punkty = 0;
    $nowe_umiejetnosci = $posiadane_um;

    if (isset($_POST['um'])) {
        foreach ($_POST['um'] as $nazwa_um => $nowy_poziom) {
            $nowy_poziom = (int)$nowy_poziom;
            $stary_poziom = isset($posiadane_um[$nazwa_um]) ? (int)$posiadane_um[$nazwa_um] : 0;

            if ($nowy_poziom > $stary_poziom) {
                $roznica = $nowy_poziom - $stary_poziom;
                $wydane_punkty += $roznica;
                $nowe_umiejetnosci[$nazwa_um] = $nowy_poziom;
            }
        }
    }

    if ($wydane_punkty <= $dostepne_pu && $wydane_punkty > 0) {
        $nowe_pu = $dostepne_pu - $wydane_punkty;
        $pakiet_json = $polaczenie->real_escape_string(json_encode($nowe_umiejetnosci, JSON_UNESCAPED_UNICODE));

        $sql = "UPDATE gracze SET umiejetnosci='$pakiet_json', punkty_umiejetnosci=$nowe_pu WHERE id=$id_gracza";
        $polaczenie->query($sql);

        echo "<script>window.location.href='game.php?page=umiejetnosci';</script>";
        exit;
    } else {
        $blad = "Wydano nieprawidłową ilość punktów!";
    }
}

// ═══════════════════════════════════════════════════════════════════
// KATALOG UMIEJĘTNOŚCI (92 pozycje w 8 kategoriach)
// ═══════════════════════════════════════════════════════════════════
$KATEGORIE = [
    'krzepa' => [
        'ikona' => '💪',
        'nazwa' => 'Przemoc i Przetrwanie',
        'akcent' => 'var(--neon-red)',
        'umiejetnosci' => [
            'Walka Bronią Palną'            => 'Biegłość w posługiwaniu się pistoletami, pistoletami maszynowymi i strzelbami.',
            'Celność Snajperska'            => 'Oddawanie precyzyjnych strzałów z dużej odległości. Praca z optyką, poprawki na wiatr i grawitację.',
            'Walka na Noże'                 => 'Zabójcza precyzja w używaniu noży bojowych i sprężynowych.',
            'Broń Biała (Miecze, Katany)'   => 'Władanie bronią sieczną: szabla, katana, rapier, miecz dwuręczny. Stary styl zabijania — cichy i bezlitosny.',
            'Łucznictwo i Broń Cicha'       => 'Obsługa łuków, kusz, proc i innej bezgłośnej broni miotającej. Idealne do cichych eliminacji.',
            'Boks Uliczny'                  => 'Klasyczna walka na pięści z naciskiem na siłę i łamanie nosów.',
            'Krav Maga'                     => 'Brutalny system walki skupiony na błyskawicznym obezwładnianiu i łamaniu kości.',
            'Sztuki Walki Wschodnie'        => 'Karate, taekwondo, judo, aikido, jujitsu. Precyzyjne chwyty, rzuty, kontrole stawów i filozofia walki.',
            'Obsługa Materiałów Wybuchowych'=> 'Konstruowanie i rozbrajanie improwizowanych ładunków wybuchowych.',
            'Taktyka Wojskowa'              => 'Dowodzenie drużyną w walce, znajomość doktryn, planowanie operacji militarnych i kontrwywiadu.',
            'Kondycja i Wytrzymałość'       => 'Zdolność organizmu do długotrwałego wysiłku, biegania i znoszenia bólu.',
            'Pływanie i Nurkowanie'         => 'Wytrzymałe pływanie w trudnych warunkach, nurkowanie z butlą, operacje podwodne.',
            'Wspinaczka Wysokogórska'       => 'Wchodzenie po skałach, dachach, wieżowcach z użyciem lin i uprzęży. Chłodna głowa na krawędzi.',
        ],
    ],
    'ulica' => [
        'ikona' => '🏙️',
        'nazwa' => 'Ulica i Złodziejstwo',
        'akcent' => 'var(--neon-ember)',
        'umiejetnosci' => [
            'Włamywanie Elektroniczne'      => 'Klonowanie kart dostępu i łamanie zamków magnetycznych w strzeżonych wieżowcach.',
            'Otwieranie Zamków (Wytrychy)'  => 'Klasyczna sztuka cichego operowania wytrychami.',
            'Kieszonkostwo'                 => 'Zwinne palce, pozwalające niepostrzeżenie okradać ofiary w tłumie.',
            'Skradanie'                     => 'Sztuka bezszelestnego poruszania się w cieniach i omijania kamer.',
            'Parkour i Freerunning'         => 'Akrobatyczne poruszanie się po miejskich dachach, ogrodzeniach i zaułkach. Skoki, wspinaczka, płynny ruch.',
            'Prowadzenie Pojazdów'          => 'Brawurowa jazda i gubienie pościgów w wąskich uliczkach.',
            'Pilotaż'                       => 'Sterowanie awionetkami, śmigłowcami, dronami bojowymi i myśliwcami. Odczyt przyrządów i nawigacja w chmurach.',
            'Topografia i Nawigacja'        => 'Doskonała znajomość planu miasta, ślepych zaułków i bezpiecznych tras transportowych.',
            'Szpiegostwo i Inwigilacja'     => 'Stawianie ogona na celu, obsługa mikrofonów kierunkowych, zakładanie ukrytych kamer, prowadzenie nadzoru.',
            'Demolka i Siłowe Wejścia'      => 'Brutalne rozbijanie drzwi, ścian, sejfów. Wyważanie barykad, łamanie okuć i krat.',
            'Zacieranie Śladów'             => 'Czyszczenie miejsca zbrodni, usuwanie logów serwerowych i mylenie psów tropiących.',
        ],
    ],
    'intelekt' => [
        'ikona' => '💻',
        'nazwa' => 'Intelekt i Technika',
        'akcent' => 'var(--neon-cyan)',
        'umiejetnosci' => [
            'Hakowanie Terminali'           => 'Włamywanie się do baz danych korporacji, policji i systemów bankowych.',
            'Programowanie'                 => 'Pisanie własnego kodu: aplikacji, narzędzi, exploitów, wirusów. Biegłość w językach programowania.',
            'Elektronika'                   => 'Lutowanie, projektowanie obwodów, mikrokontrolery, budowa gadżetów szpiegowskich, bugowanie urządzeń.',
            'Cybernetyka i Implanty'        => 'Projektowanie, instalacja i hakowanie cybernetycznych wszczepów. Interfejsy neuronowe, protezy bojowe, rozszerzenia zmysłów.',
            'Robotyka i Mechatronika'       => 'Budowa, programowanie i naprawa robotów autonomicznych oraz dronów. Od drona-zabawki po mecha bojowego.',
            'Inżynieria Złomu'              => 'Tworzenie sprzętu i prowizorycznej broni z części znalezionych na śmietniku.',
            'Mechanika i Naprawa'           => 'Naprawa pojazdów, rur, zamków i ciężkiego sprzętu maszynowego.',
            'Architektura i Konstrukcje'    => 'Projektowanie budynków, znajomość materiałów budowlanych i fizyki budowli.',
            'Biologia i Botanika'           => 'Hodowla roślin, inżynieria flory i zaawansowana wiedza o ekosystemach.',
            'Medycyna Uliczna'              => 'Nielegalne łatanie ran postrzałowych w brudnych piwnicach.',
            'Medycyna Akademicka'           => 'Oficjalna, uniwersytecka wiedza o anatomii i chorobach. Prawo do wykonywania zawodu.',
            'Psychiatria'                   => 'Diagnostyka zaburzeń psychicznych, psychoterapia, przepisywanie leków psychotropowych. Głębokie rozumienie umysłu.',
            'Chemia i Farmakologia'         => 'Produkcja narkotyków, leków i synteza własnych dopalaczy.',
            'Prawo i Administracja'         => 'Znajomość kruczków prawnych, kodeksów karnych i biurokracji miejskiej.',
            'Wiedza Ogólna i Pedagogika'    => 'Szeroka wiedza teoretyczna oraz umiejętność przekazywania jej innym.',
            'Historia i Antykwariat'        => 'Wiedza o epokach, artefaktach, kulturach. Rozpoznawanie autentyków od falsyfikatów. Wycena antyków.',
            'Filozofia i Religioznawstwo'   => 'Systemy myśli, etyka, światowe religie i kulty. Rozumienie światopoglądów ludzi z różnych kultur.',
            'Języki Obce'                   => 'Biegłość w językach: angielskim, chińskim, rosyjskim, japońskim, hiszpańskim i innych — zależnie od osobistej specjalizacji.',
            'Analiza Danych i Dedukcja'     => 'Łączenie faktów, praca z dokumentami i rozwiązywanie zagadek logicznych.',
            'Matematyka i Rachunkowość'     => 'Skrupulatne obliczenia, księgowość, pranie brudnych pieniędzy i inwestycje.',
            'Kryptografia i Szyfry'         => 'Tworzenie i łamanie szyfrów, steganografia, zabezpieczanie komunikacji. Ukrywanie tajemnic przed każdym okiem.',
            'Balistyka i Kryminalistyka'    => 'Analiza śladów postrzałowych, odcisków palców, śladów DNA. Rekonstrukcja przebiegu zbrodni na podstawie miejsca zdarzenia.',
        ],
    ],
    'spoleczne' => [
        'ikona' => '🗣️',
        'nazwa' => 'Relacje i Manipulacja',
        'akcent' => 'var(--neon-gold)',
        'umiejetnosci' => [
            'Zarządzanie i Przywództwo'     => 'Kierowanie zespołem ludzi, motywowanie ich do pracy i organizacja firm lub gangów.',
            'Kadry i Rekrutacja'            => 'Przesłuchiwanie kandydatów, ocena kompetencji, budowanie zespołów, tworzenie umów. Wiedza o prawie pracy.',
            'Zastraszanie'                  => 'Operowanie mową ciała i groźbami, by złamać rozmówcę.',
            'Perswazja i Negocjacje'        => 'Złotousty gaduła. Przekonywanie innych i negocjowanie cen.',
            'Wystąpienia Publiczne'         => 'Przemówienia polityczne, kazania, wykłady. Porywanie tłumu, panowanie nad sceną, kontrola emocji publiczności.',
            'Marketing i Reklama'           => 'Projektowanie kampanii, budowanie marki, analiza grupy docelowej. Sprzedawanie wszystkiego — od pasty do mordercy jako bohatera.',
            'Mediacja i Rozwiązywanie Konfliktów' => 'Łagodzenie sporów między stronami, negocjacje pokojowe, rozstrzyganie konfliktów sąsiedzkich lub korporacyjnych.',
            'Hipnoza i Sugestia'            => 'Wprowadzanie w trans, terapia hipnotyczna, pozyskiwanie informacji drogą sugestii. Techniki perswazji głębokiej.',
            'Handel i Wycena'               => 'Błyskawiczne ocenianie wartości towarów i profesjonalna obsługa klienta.',
            'Obsługa Klienta'               => 'Cierpliwość w obsłudze trudnych klientów, rozwiązywanie reklamacji, utrzymywanie kontaktu. Twarz instytucji.',
            'Sztuka Uwodzenia'              => 'Wabienie, flirt, uwodzenie i wykorzystywanie wdzięków do osiągania celów.',
            'Znajomość Półświatka'          => 'Znasz odpowiednich ludzi. Wiesz, kogo przekupić i jak działają gangi.',
            'Fałszerstwo Dokumentów'        => 'Produkcja lewych dowodów, paszportów i przepustek.',
            'Psychologia i Empatia'         => 'Czytanie emocji innych, manipulacja uczuciami i doradztwo.',
            'Śledzenie Finansowe'           => 'Wykrywanie prania pieniędzy, audyt, analiza przepływów finansowych i identyfikacja fałszywych transakcji.',
            'Etykieta i Dobre Manery'       => 'Zasady zachowania na elitarnych salonach. Wiedza jak rozmawiać z bogaczami.',
        ],
    ],
    'scena' => [
        'ikona' => '🎭',
        'nazwa' => 'Scena, Sztuka i Rozrywka',
        'akcent' => 'var(--neon-red-hot)',
        'umiejetnosci' => [
            'Sztuka Kulinarna i Gastronomia'=> 'Mistrzowskie gotowanie, pieczenie i serwowanie wykwintnych dań oraz drinków.',
            'Literatura i Twórcze Pisanie'  => 'Lekkie pióro. Pisanie porywających powieści, scenariuszy i artykułów prasowych.',
            'Aktorstwo i Charakteryzacja'   => 'Odgrywanie ról, wcielanie się w inne postacie i mistrzowski kamuflaż.',
            'Reżyseria i Produkcja Filmowa' => 'Prowadzenie ekipy filmowej, inscenizacja ujęć, montaż, praca z aktorami. Od reklamy po kino.',
            'Wokal i Śpiew'                 => 'Twój głos hipnotyzuje. Śpiewasz czysto i z emocją.',
            'DJing i Instrumenty'           => 'Gra na instrumentach oraz profesjonalne miksowanie muzyki elektronicznej.',
            'Realizacja Dźwięku'            => 'Nagrania studyjne, miksowanie, mastering, akustyka koncertowa. Budowanie brzmienia na koncertach i w filmach.',
            'Akrobatyka i Taniec'           => 'Elastyczność, rozciągnięcie, skoki oraz profesjonalne poczucie rytmu na parkiecie.',
            'Sztuki Plastyczne i Rzemiosło' => 'Talent manualny. Malowanie, rzeźbienie i tworzenie sztuki wizualnej.',
            'Fotografia'                    => 'Kompozycja kadru, obsługa profesjonalnego sprzętu, praca ze światłem. Paparazzi, fotoreportaż, fotografia artystyczna.',
            'Krawiectwo i Stylizacja'       => 'Szycie ubrań na miarę, naprawa pancerzy oraz tworzenie modowych trendów.',
            'Stand-up i Cięta Riposta'      => 'Zjednywanie publiki żartem i gaszenie oponentów słowem.',
            'Sztuka Iluzji (Kuglarstwo)'    => 'Sztuczki magiczne, manipulacja kartami i odwracanie uwagi tłumu.',
            'Moda i Wizerunek'              => 'Dobieranie kreacji, dbanie o wygląd zewnętrzny i budowanie własnej marki osobistej.',
            'Streaming i Influencing'       => 'Budowanie publiczności w sieci, transmisje na żywo, social media, monetyzacja wizerunku online.',
        ],
    ],
    'outdoor' => [
        'ikona' => '🌲',
        'nazwa' => 'Outdoor i Przyroda',
        'akcent' => 'var(--neon-green)',
        'umiejetnosci' => [
            'Myślistwo i Polowanie'         => 'Śledzenie zwierzyny, zakładanie pułapek, strzelanie do ruchomego celu w terenie. Skórowanie i obróbka zdobyczy.',
            'Tropienie'                     => 'Czytanie śladów na ziemi, ustalanie kierunku poruszania się zwierzęcia lub człowieka, lokalizowanie obozowisk.',
            'Przetrwanie w Dziczy'          => 'Rozpalanie ognia bez zapałek, rozpoznawanie jadalnych i trujących roślin, budowa szałasów, picie wody z dziczy.',
            'Jazda Konna'                   => 'Jazda wierzchem, opieka nad koniem, siodłanie, jazda w trudnym terenie.',
            'Rybołówstwo i Żegluga'         => 'Prowadzenie jednostek pływających od kutra po jacht, nawigacja morska, łowienie ryb, praca na otwartej wodzie.',
            'Spadochroniarstwo'             => 'Skoki z wysokości, sterowanie spadochronem, lądowanie w terenie, skoki BASE z wież miasta.',
        ],
    ],
    'opieka' => [
        'ikona' => '🏥',
        'nazwa' => 'Opieka Medyczna',
        'akcent' => 'var(--neon-cyan)',
        'umiejetnosci' => [
            'Pielęgniarstwo'                => 'Podawanie leków, zakładanie wkłuć, opatrunki, opieka nad pacjentami, pierwsza pomoc. Codzienna praktyka oddziałowa.',
            'Chirurgia'                     => 'Zaawansowane operacje: cięcie, szycie, transplantacje. Wymaga opanowania, twardej ręki i wiedzy anatomicznej.',
            'Fizjoterapia i Rehabilitacja'  => 'Rehabilitacja po urazach, masaż leczniczy, ćwiczenia przywracające sprawność, trening cyber-protez.',
            'Weterynaria'                   => 'Leczenie zwierząt: psów bojowych, koni, cyber-wszczepianych zwierząt syndykatu. Diagnostyka weterynaryjna i zabiegi chirurgiczne.',
            'Opieka nad Dziećmi i Starszymi'=> 'Opieka nad niemowlętami, wychowanie przedszkolne, opieka geriatryczna. Cierpliwość, empatia i spryt przy codziennych wyzwaniach.',
        ],
    ],
    'rzemioslo' => [
        'ikona' => '🔨',
        'nazwa' => 'Rzemiosło Tradycyjne',
        'akcent' => 'var(--neon-ember)',
        'umiejetnosci' => [
            'Kowalstwo i Obróbka Metali'    => 'Kucie, hartowanie, spawanie. Tworzenie mieczy, zbroi, krat. Tradycyjne rzemiosło w epoce drukarek 3D wciąż ma swoją cenę.',
            'Stolarstwo i Obróbka Drewna'   => 'Praca piłą, dłutem, heblem. Meble, trumny, ściany, podłogi. Stolarz-cieśla wciąż potrzebny w każdej epoce.',
            'Jubilerstwo i Zegarmistrzostwo'=> 'Tworzenie biżuterii, osadzanie kamieni szlachetnych, naprawa zegarków mechanicznych. Precyzja i oko do detalu.',
            'Introligatorstwo i Oprawa'     => 'Oprawa książek, konserwacja starych tomów, tworzenie luksusowych wydań kolekcjonerskich. Ginący zawód.',
        ],
    ],
];
?>

<style>
/* ═══════════════════════════════════════════════════════════════════
   UMIEJETNOSCI.PHP — CYBERPUNK NYC (layout zsynchronizowany z karta.php)
═══════════════════════════════════════════════════════════════════ */

.um-head { text-align: center; margin-bottom: 30px; }
.um-head h1 {
    font-family: 'Oswald', sans-serif; color: #fff; font-size: 2.8em; margin: 0;
    text-transform: uppercase; letter-spacing: 4px; font-weight: 500; line-height: 1;
    text-shadow: 0 0 20px rgba(255,23,68,0.3);
}
.um-head p {
    color: var(--neon-red); font-size: .75em; margin-top: 8px;
    font-family: 'JetBrains Mono', monospace; letter-spacing: 4px; text-transform: uppercase;
    text-shadow: 0 0 6px rgba(255,23,68,0.5);
}

.um-pu-panel {
    background: rgba(255,23,68,0.05);
    border: 1px solid var(--border-mid); border-radius: 2px;
    padding: 22px 26px; margin-bottom: 22px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
    box-shadow: 0 0 30px rgba(255,23,68,0.08);
    position: relative; overflow: hidden;
}
.um-pu-panel::before {
    content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 3px;
    background: var(--neon-red); box-shadow: 0 0 12px var(--neon-red);
}
.um-pu-label {
    font-family: 'Oswald', sans-serif; color: var(--neon-red-hot);
    text-transform: uppercase; letter-spacing: 3px; font-size: 1em;
    text-shadow: 0 0 8px rgba(255,23,68,0.5);
}
.um-pu-value {
    font-family: 'Oswald', sans-serif; font-weight: 700; font-size: 2.5em;
    color: #fff; letter-spacing: 2px; line-height: 1;
    text-shadow: 0 0 12px rgba(255,23,68,0.7);
}
.um-pu-subtext {
    color: var(--txt-mute); font-family: 'JetBrains Mono', monospace;
    font-size: .75em; letter-spacing: 1.5px; text-transform: uppercase;
    margin-top: 4px;
}

.um-info {
    color: var(--txt-main); font-size: .92em; line-height: 1.55;
    padding: 14px 18px; margin-bottom: 22px;
    background: rgba(74,214,255,0.05);
    border: 1px solid rgba(74,214,255,0.2); border-radius: 2px;
    position: relative;
}
.um-info::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px;
    background: var(--neon-cyan); box-shadow: 0 0 8px var(--neon-cyan);
}

.blok {
    background: rgba(10,6,12,0.6); backdrop-filter: blur(8px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 24px; margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    position: relative;
}
.blok::before {
    content: ''; position: absolute; top: 0; left: 0; width: 32px; height: 1px;
    background: var(--akcent, var(--neon-red));
    box-shadow: 0 0 6px var(--akcent, var(--neon-red));
}
.blok-tytul {
    font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 2.5px;
    font-size: 1.05em; color: #fff; margin: 0 0 18px; font-weight: 500;
    padding-bottom: 12px; border-bottom: 1px solid var(--border-soft);
    display: flex; align-items: center; gap: 10px;
}
.blok-tytul .licznik-um {
    color: var(--txt-mute); font-size: .72em; font-weight: 400;
    text-transform: none; margin-left: auto; letter-spacing: .5px;
    font-family: 'JetBrains Mono', monospace;
}

.um-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 10px;
}
.um-karta {
    background: rgba(0,0,0,0.5); border: 1px solid rgba(255,23,68,0.08);
    border-radius: 2px; padding: 12px 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    transition: .2s;
}
.um-karta:hover {
    background: rgba(255,23,68,0.04);
    border-color: var(--border-mid);
}
.um-nazwa-box {
    flex: 1; min-width: 0;
    font-family: 'Oswald', sans-serif; letter-spacing: .5px;
    color: var(--txt-main); font-size: .95em;
    cursor: help; position: relative;
    border-bottom: 1px dotted rgba(255,23,68,0.2);
    padding-bottom: 1px;
}
.um-nazwa-box:hover { color: #fff; border-bottom-color: var(--neon-cyan); }
.um-nazwa-box:hover::after {
    content: attr(data-opis);
    position: absolute; z-index: 100;
    bottom: calc(100% + 8px); left: 0;
    width: 280px; padding: 12px 14px;
    background: rgba(5,3,7,0.97); backdrop-filter: blur(12px);
    border: 1px solid var(--neon-red); border-radius: 2px;
    color: var(--txt-main); font-family: 'Open Sans', sans-serif;
    font-size: .82em; line-height: 1.5; letter-spacing: 0;
    text-transform: none; font-weight: normal;
    box-shadow: 0 10px 40px rgba(0,0,0,0.95), 0 0 20px rgba(255,23,68,0.25);
    pointer-events: none; text-align: left; white-space: normal;
}

.um-ctrl { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.btn-um {
    background: rgba(0,0,0,0.8); color: var(--txt-main);
    border: 1px solid var(--border-soft);
    width: 26px; height: 26px; border-radius: 2px; font-weight: 700; cursor: pointer;
    transition: .2s; font-size: 1em;
    display: inline-flex; align-items: center; justify-content: center;
    font-family: 'JetBrains Mono', monospace;
}
.btn-um:hover {
    background: var(--neon-cyan); color: #000; border-color: var(--neon-cyan);
    box-shadow: 0 0 10px rgba(74,214,255,0.6);
}
.btn-um-minus:hover {
    background: var(--neon-red-hot); color: #fff; border-color: var(--neon-red);
    box-shadow: 0 0 10px rgba(255,23,68,0.6);
}
.um-poziom {
    color: var(--neon-cyan); min-width: 28px; text-align: center;
    font-weight: 700; font-family: 'JetBrains Mono', monospace;
    font-size: 1.05em; text-shadow: 0 0 6px rgba(74,214,255,0.5);
}
.um-poziom.zero { color: var(--txt-mute); text-shadow: none; }
.um-poziom.wysoki { color: var(--neon-green); text-shadow: 0 0 6px rgba(90,255,154,0.5); }

.blad {
    background: rgba(255,23,68,0.1); border: 1px solid var(--border-mid);
    color: var(--neon-red-hot); padding: 13px 16px; border-radius: 2px;
    margin-bottom: 18px; font-weight: 500; text-align: center;
    font-family: 'Oswald', sans-serif; letter-spacing: 1.5px;
    box-shadow: 0 0 20px rgba(255,23,68,0.15);
}

.um-sticky-zapisz {
    position: sticky; bottom: 20px; margin-top: 20px;
    background: rgba(5,3,7,0.95); backdrop-filter: blur(12px);
    border: 1px solid var(--border-mid); border-radius: 2px;
    padding: 14px 18px;
    display: none; align-items: center; justify-content: space-between; gap: 14px;
    box-shadow: 0 -8px 30px rgba(0,0,0,0.8), 0 0 20px rgba(255,23,68,0.2);
    z-index: 50;
}
.um-sticky-zapisz.aktywny { display: flex; }
.um-sticky-info {
    color: var(--txt-dim); font-family: 'Oswald', sans-serif;
    letter-spacing: 1.5px; text-transform: uppercase; font-size: .88em;
}
.um-sticky-info strong { color: var(--neon-red-hot); margin: 0 4px; font-size: 1.3em; }

@media (max-width: 600px) {
    .um-head h1 { font-size: 2em; letter-spacing: 2px; }
    .um-pu-panel { flex-direction: column; text-align: center; }
    .um-pu-value { font-size: 2em; }
    .um-nazwa-box:hover::after { width: 220px; left: auto; right: 0; }
}
</style>

<!-- ══ NAGŁÓWEK ══════════════════════════════════════════════════ -->
<div class="um-head">
    <h1>Umiejętności Fabularne</h1>
    <p>// Trening i Rozwój Postaci</p>
</div>

<?php if (!empty($blad)): ?>
<div class="blad">⚠ <?php echo htmlspecialchars($blad); ?></div>
<?php endif; ?>

<!-- ══ PASEK PUNKTÓW UMIEJĘTNOŚCI ═══════════════════════════════ -->
<div class="um-pu-panel">
    <div>
        <div class="um-pu-label">◆ Wolne Punkty Umiejętności</div>
        <div class="um-pu-subtext">Rozdysponuj w wybranych umiejętnościach</div>
    </div>
    <div class="um-pu-value" id="licznik"><?php echo $dostepne_pu; ?></div>
</div>

<!-- ══ INFO ═════════════════════════════════════════════════════ -->
<div class="um-info">
    💡 Każdy poziom kosztuje 1 PU. Zawody fabularne wymagają konkretnych poziomów
    umiejętności, a im wyższy poziom — tym większy bonus w sesjach Centrum Opowieści.
    Każdy awans mechaniczny daje kolejne PU do rozdysponowania.
</div>

<form method="POST" action="game.php?page=umiejetnosci" id="form-um">

<?php
// ─── RENDERUJ KATEGORIE JAKO BLOKI ───────────────────────────────
foreach ($KATEGORIE as $id_kat => $dane_kat):
    $liczba_um = count($dane_kat['umiejetnosci']);
    $liczba_nabytych = 0;
    foreach (array_keys($dane_kat['umiejetnosci']) as $nazwa_um) {
        if (!empty($posiadane_um[$nazwa_um])) $liczba_nabytych++;
    }
?>

<div class="blok" style="--akcent: <?php echo $dane_kat['akcent']; ?>">
    <div class="blok-tytul">
        <span><?php echo $dane_kat['ikona']; ?></span>
        <span><?php echo htmlspecialchars($dane_kat['nazwa']); ?></span>
        <span class="licznik-um"><?php echo $liczba_nabytych; ?> / <?php echo $liczba_um; ?> opanowane</span>
    </div>

    <div class="um-grid">
    <?php foreach ($dane_kat['umiejetnosci'] as $nazwa => $opis):
        $poziom = isset($posiadane_um[$nazwa]) ? (int)$posiadane_um[$nazwa] : 0;
        $klucz = htmlspecialchars($nazwa);
        $opis_safe = htmlspecialchars($opis, ENT_QUOTES);
        $klasa_poziom = $poziom === 0 ? 'zero' : ($poziom >= 8 ? 'wysoki' : '');
    ?>
        <div class="um-karta">
            <span class="um-nazwa-box" data-opis="<?php echo $opis_safe; ?>"><?php echo htmlspecialchars($nazwa); ?></span>
            <div class="um-ctrl">
                <button type="button" class="btn-um btn-um-minus" onclick="zmienPunkt('<?php echo $klucz; ?>', -1, <?php echo $poziom; ?>)">−</button>
                <span class="um-poziom <?php echo $klasa_poziom; ?>" id="wyswietl_<?php echo $klucz; ?>"><?php echo $poziom; ?></span>
                <input type="number" name="um[<?php echo $klucz; ?>]" id="input_<?php echo $klucz; ?>" value="<?php echo $poziom; ?>" style="display:none">
                <button type="button" class="btn-um" onclick="zmienPunkt('<?php echo $klucz; ?>', 1, <?php echo $poziom; ?>)">+</button>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<?php endforeach; ?>

<!-- ══ STICKY PASEK NA DOLE — widoczny gdy są zmiany ═══════════ -->
<div class="um-sticky-zapisz" id="sticky-zapisz">
    <div class="um-sticky-info">
        ◤ Wydajesz <strong id="sticky-wydane">0</strong> PU z <strong><?php echo $dostepne_pu; ?></strong>
    </div>
    <button type="submit" name="zapisz_umiejetnosci" style="
        background:var(--neon-red); color:#fff; border:none; padding:11px 28px;
        font-family:'Oswald',sans-serif; font-size:1em; font-weight:600;
        cursor:pointer; text-transform:uppercase; letter-spacing:2.5px; border-radius:2px;
        box-shadow:0 0 15px rgba(255,23,68,0.5);">
        ▸ Zatwierdź Wybór
    </button>
</div>

</form>

<script>
const maxDostepne = <?php echo $dostepne_pu; ?>;
let aktualnieWydane = 0;

function zmienPunkt(klucz, zmiana, poziomBazy) {
    const input = document.getElementById('input_' + klucz);
    const display = document.getElementById('wyswietl_' + klucz);
    const obecnyPoziom = parseInt(input.value);
    const nowyPoziom = obecnyPoziom + zmiana;

    if (nowyPoziom < poziomBazy) {
        return;
    }
    if (zmiana > 0) {
        if (aktualnieWydane >= maxDostepne) return;
        aktualnieWydane++;
    } else if (zmiana < 0) {
        aktualnieWydane--;
    }

    input.value = nowyPoziom;
    display.innerText = nowyPoziom;

    display.classList.remove('zero', 'wysoki');
    if (nowyPoziom === 0) display.classList.add('zero');
    else if (nowyPoziom >= 8) display.classList.add('wysoki');

    document.getElementById('licznik').innerText = (maxDostepne - aktualnieWydane);
    document.getElementById('sticky-wydane').innerText = aktualnieWydane;

    const sticky = document.getElementById('sticky-zapisz');
    if (aktualnieWydane > 0) sticky.classList.add('aktywny');
    else sticky.classList.remove('aktywny');
}
</script>