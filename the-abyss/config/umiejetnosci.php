<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — CONFIG/UMIEJETNOSCI.PHP
   Katalog Umiejętności fabularnych i zasady PU. Jedno źródło prawdy dla
   pages/umiejetnosci.php, karty postaci i przyszłego panelu MG.
   ═══════════════════════════════════════════════════════════════════════ */

const UM_MAX_POZIOM      = 5;
const UM_PE              = 80;   // Próg Efektywności — maks. szansa rzutu (%)
const UM_PU_START        = 15;
const UM_PU_AMERYKANIN   = 4;
const UM_PU_WYKSZTALCONY = 3;    // do potwierdzenia
const UM_POCHODZENIE_AMERYKANIN = ['USA'];   // klucz z config/pochodzenia.php

$UM_ATRYBUTY = [
    'S'  => ['nazwa' => 'Siła',         'kolumna' => 'sila'],
    'Z'  => ['nazwa' => 'Zręczność',    'kolumna' => 'zrecznosc'],
    'W'  => ['nazwa' => 'Wytrzymałość', 'kolumna' => 'wytrzymalosc'],
    'I'  => ['nazwa' => 'Inteligencja', 'kolumna' => 'inteligencja'],
    'Zm' => ['nazwa' => 'Zmysły',       'kolumna' => 'zmysly'],
    'Ch' => ['nazwa' => 'Charyzma',     'kolumna' => 'charyzma'],
];

// [nazwa, atrybut główny, atrybut dodatkowy|null, opis]
$UM_KATEGORIE = [
    'krzepa' => ['nazwa' => 'Przemoc i Przetrwanie', 'kolor' => '#ff1744', 'um' => [
        ['Walka Bronią Palną', 'Z', 'Zm', 'Biegłość w posługiwaniu się pistoletami, pistoletami maszynowymi i strzelbami.'],
        ['Celność Snajperska', 'Zm', 'Z', 'Oddawanie precyzyjnych strzałów z dużej odległości. Praca z optyką, poprawki na wiatr i grawitację.'],
        ['Walka na Noże', 'Z', 'S', 'Zabójcza precyzja w używaniu noży bojowych i sprężynowych.'],
        ['Broń Biała (Miecze, Katany)', 'Z', 'S', 'Władanie bronią sieczną: szabla, katana, rapier, miecz dwuręczny. Stary styl zabijania — cichy i bezlitosny.'],
        ['Łucznictwo i Broń Cicha', 'Zm', 'Z', 'Obsługa łuków, kusz, proc i innej bezgłośnej broni miotającej. Idealne do cichych eliminacji.'],
        ['Boks Uliczny', 'S', 'W', 'Klasyczna walka na pięści z naciskiem na siłę i łamanie nosów.'],
        ['Krav Maga', 'S', 'Z', 'Brutalny system walki skupiony na błyskawicznym obezwładnianiu i łamaniu kości.'],
        ['Sztuki Walki Wschodnie', 'Z', 'S', 'Karate, taekwondo, judo, aikido, jujitsu. Precyzyjne chwyty, rzuty, kontrole stawów i filozofia walki.'],
        ['Obsługa Materiałów Wybuchowych', 'I', 'Z', 'Konstruowanie i rozbrajanie improwizowanych ładunków wybuchowych.'],
        ['Taktyka Wojskowa', 'I', 'Ch', 'Dowodzenie drużyną w walce, znajomość doktryn, planowanie operacji militarnych i kontrwywiadu.'],
        ['Kondycja i Wytrzymałość', 'W', 'S', 'Zdolność organizmu do długotrwałego wysiłku, biegania i znoszenia bólu.'],
        ['Pływanie i Nurkowanie', 'W', 'S', 'Wytrzymałe pływanie w trudnych warunkach, nurkowanie z butlą, operacje podwodne.'],
        ['Wspinaczka Wysokogórska', 'S', 'Z', 'Wchodzenie po skałach, dachach, wieżowcach z użyciem lin i uprzęży. Chłodna głowa na krawędzi.'],
    ]],
    'ulica' => ['nazwa' => 'Ulica i Złodziejstwo', 'kolor' => '#ff7a3d', 'um' => [
        ['Włamywanie Elektroniczne', 'I', 'Z', 'Klonowanie kart dostępu i łamanie zamków magnetycznych w strzeżonych wieżowcach.'],
        ['Otwieranie Zamków (Wytrychy)', 'Z', 'Zm', 'Klasyczna sztuka cichego operowania wytrychami.'],
        ['Kieszonkostwo', 'Z', 'Ch', 'Zwinne palce, pozwalające niepostrzeżenie okradać ofiary w tłumie.'],
        ['Skradanie', 'Z', 'Zm', 'Sztuka bezszelestnego poruszania się w cieniach i omijania kamer.'],
        ['Parkour i Freerunning', 'Z', 'W', 'Akrobatyczne poruszanie się po miejskich dachach, ogrodzeniach i zaułkach. Skoki, wspinaczka, płynny ruch.'],
        ['Prowadzenie Pojazdów', 'Z', 'Zm', 'Brawurowa jazda i gubienie pościgów w wąskich uliczkach.'],
        ['Pilotaż', 'Z', 'I', 'Sterowanie awionetkami, śmigłowcami, dronami bojowymi i myśliwcami. Odczyt przyrządów i nawigacja w chmurach.'],
        ['Topografia i Nawigacja', 'I', 'Zm', 'Doskonała znajomość planu miasta, ślepych zaułków i bezpiecznych tras transportowych.'],
        ['Szpiegostwo i Inwigilacja', 'Zm', 'I', 'Stawianie ogona na celu, obsługa mikrofonów kierunkowych, zakładanie ukrytych kamer, prowadzenie nadzoru.'],
        ['Demolka i Siłowe Wejścia', 'S', 'W', 'Brutalne rozbijanie drzwi, ścian, sejfów. Wyważanie barykad, łamanie okuć i krat.'],
        ['Zacieranie Śladów', 'I', 'Zm', 'Czyszczenie miejsca zbrodni, usuwanie logów serwerowych i mylenie psów tropiących.'],
    ]],
    'intelekt' => ['nazwa' => 'Intelekt i Technika', 'kolor' => '#4ad6ff', 'um' => [
        ['Hakowanie Terminali', 'I', null, 'Włamywanie się do baz danych korporacji, policji i systemów bankowych.'],
        ['Programowanie', 'I', null, 'Pisanie własnego kodu: aplikacji, narzędzi, exploitów, wirusów. Biegłość w językach programowania.'],
        ['Elektronika', 'I', 'Z', 'Lutowanie, projektowanie obwodów, mikrokontrolery, budowa gadżetów szpiegowskich, bugowanie urządzeń.'],
        ['Cybernetyka i Implanty', 'I', 'Z', 'Projektowanie, instalacja i hakowanie cybernetycznych wszczepów. Interfejsy neuronowe, protezy bojowe, rozszerzenia zmysłów.'],
        ['Robotyka i Mechatronika', 'I', 'Z', 'Budowa, programowanie i naprawa robotów autonomicznych oraz dronów. Od drona-zabawki po mecha bojowego.'],
        ['Inżynieria Złomu', 'I', 'Z', 'Tworzenie sprzętu i prowizorycznej broni z części znalezionych na śmietniku.'],
        ['Mechanika i Naprawa', 'I', 'S', 'Naprawa pojazdów, rur, zamków i ciężkiego sprzętu maszynowego.'],
        ['Architektura i Konstrukcje', 'I', null, 'Projektowanie budynków, znajomość materiałów budowlanych i fizyki budowli.'],
        ['Biologia i Botanika', 'I', 'Zm', 'Hodowla roślin, inżynieria flory i zaawansowana wiedza o ekosystemach.'],
        ['Medycyna Uliczna', 'I', 'Z', 'Nielegalne łatanie ran postrzałowych w brudnych piwnicach.'],
        ['Medycyna Akademicka', 'I', null, 'Oficjalna, uniwersytecka wiedza o anatomii i chorobach. Prawo do wykonywania zawodu.'],
        ['Psychiatria', 'I', 'Ch', 'Diagnostyka zaburzeń psychicznych, psychoterapia, przepisywanie leków psychotropowych. Głębokie rozumienie umysłu.'],
        ['Chemia i Farmakologia', 'I', null, 'Produkcja narkotyków, leków i synteza własnych dopalaczy.'],
        ['Prawo i Administracja', 'I', 'Ch', 'Znajomość kruczków prawnych, kodeksów karnych i biurokracji miejskiej.'],
        ['Wiedza Ogólna i Pedagogika', 'I', 'Ch', 'Szeroka wiedza teoretyczna oraz umiejętność przekazywania jej innym.'],
        ['Historia i Antykwariat', 'I', 'Zm', 'Wiedza o epokach, artefaktach, kulturach. Rozpoznawanie autentyków od falsyfikatów. Wycena antyków.'],
        ['Filozofia i Religioznawstwo', 'I', 'Ch', 'Systemy myśli, etyka, światowe religie i kulty. Rozumienie światopoglądów ludzi z różnych kultur.'],
        ['Języki Obce', 'I', 'Ch', 'Biegłość w językach: angielskim, chińskim, rosyjskim, japońskim, hiszpańskim i innych — zależnie od osobistej specjalizacji.'],
        ['Analiza Danych i Dedukcja', 'I', 'Zm', 'Łączenie faktów, praca z dokumentami i rozwiązywanie zagadek logicznych.'],
        ['Matematyka i Rachunkowość', 'I', null, 'Skrupulatne obliczenia, księgowość, pranie brudnych pieniędzy i inwestycje.'],
        ['Kryptografia i Szyfry', 'I', null, 'Tworzenie i łamanie szyfrów, steganografia, zabezpieczanie komunikacji. Ukrywanie tajemnic przed każdym okiem.'],
        ['Balistyka i Kryminalistyka', 'I', 'Zm', 'Analiza śladów postrzałowych, odcisków palców, śladów DNA. Rekonstrukcja przebiegu zbrodni na podstawie miejsca zdarzenia.'],
    ]],
    'spoleczne' => ['nazwa' => 'Relacje i Manipulacja', 'kolor' => '#ffd700', 'um' => [
        ['Zarządzanie i Przywództwo', 'Ch', 'I', 'Kierowanie zespołem ludzi, motywowanie ich do pracy i organizacja firm lub gangów.'],
        ['Kadry i Rekrutacja', 'Ch', 'I', 'Przesłuchiwanie kandydatów, ocena kompetencji, budowanie zespołów, tworzenie umów. Wiedza o prawie pracy.'],
        ['Zastraszanie', 'Ch', 'S', 'Operowanie mową ciała i groźbami, by złamać rozmówcę.'],
        ['Perswazja i Negocjacje', 'Ch', 'I', 'Złotousty gaduła. Przekonywanie innych i negocjowanie cen.'],
        ['Wystąpienia Publiczne', 'Ch', null, 'Przemówienia polityczne, kazania, wykłady. Porywanie tłumu, panowanie nad sceną, kontrola emocji publiczności.'],
        ['Marketing i Reklama', 'Ch', 'I', 'Projektowanie kampanii, budowanie marki, analiza grupy docelowej. Sprzedawanie wszystkiego — od pasty do mordercy jako bohatera.'],
        ['Mediacja i Rozwiązywanie Konfliktów', 'Ch', 'I', 'Łagodzenie sporów między stronami, negocjacje pokojowe, rozstrzyganie konfliktów sąsiedzkich lub korporacyjnych.'],
        ['Hipnoza i Sugestia', 'Ch', 'I', 'Wprowadzanie w trans, terapia hipnotyczna, pozyskiwanie informacji drogą sugestii. Techniki perswazji głębokiej.'],
        ['Handel i Wycena', 'Ch', 'I', 'Błyskawiczne ocenianie wartości towarów i profesjonalna obsługa klienta.'],
        ['Obsługa Klienta', 'Ch', 'W', 'Cierpliwość w obsłudze trudnych klientów, rozwiązywanie reklamacji, utrzymywanie kontaktu. Twarz instytucji.'],
        ['Sztuka Uwodzenia', 'Ch', null, 'Wabienie, flirt, uwodzenie i wykorzystywanie wdzięków do osiągania celów.'],
        ['Znajomość Półświatka', 'Ch', 'Zm', 'Znasz odpowiednich ludzi. Wiesz, kogo przekupić i jak działają gangi.'],
        ['Fałszerstwo Dokumentów', 'Z', 'I', 'Produkcja lewych dowodów, paszportów i przepustek.'],
        ['Psychologia i Empatia', 'Zm', 'Ch', 'Czytanie emocji innych, manipulacja uczuciami i doradztwo.'],
        ['Śledzenie Finansowe', 'I', null, 'Wykrywanie prania pieniędzy, audyt, analiza przepływów finansowych i identyfikacja fałszywych transakcji.'],
        ['Etykieta i Dobre Manery', 'Ch', 'I', 'Zasady zachowania na elitarnych salonach. Wiedza jak rozmawiać z bogaczami.'],
    ]],
    'scena' => ['nazwa' => 'Scena, Sztuka i Rozrywka', 'kolor' => '#ff3d5e', 'um' => [
        ['Sztuka Kulinarna i Gastronomia', 'Z', 'Zm', 'Mistrzowskie gotowanie, pieczenie i serwowanie wykwintnych dań oraz drinków.'],
        ['Literatura i Twórcze Pisanie', 'I', 'Ch', 'Lekkie pióro. Pisanie porywających powieści, scenariuszy i artykułów prasowych.'],
        ['Aktorstwo i Charakteryzacja', 'Ch', 'Z', 'Odgrywanie ról, wcielanie się w inne postacie i mistrzowski kamuflaż.'],
        ['Reżyseria i Produkcja Filmowa', 'Ch', 'I', 'Prowadzenie ekipy filmowej, inscenizacja ujęć, montaż, praca z aktorami. Od reklamy po kino.'],
        ['Wokal i Śpiew', 'Ch', 'W', 'Twój głos hipnotyzuje. Śpiewasz czysto i z emocją.'],
        ['DJing i Instrumenty', 'Z', 'Zm', 'Gra na instrumentach oraz profesjonalne miksowanie muzyki elektronicznej.'],
        ['Realizacja Dźwięku', 'Zm', 'I', 'Nagrania studyjne, miksowanie, mastering, akustyka koncertowa. Budowanie brzmienia na koncertach i w filmach.'],
        ['Akrobatyka i Taniec', 'Z', 'W', 'Elastyczność, rozciągnięcie, skoki oraz profesjonalne poczucie rytmu na parkiecie.'],
        ['Sztuki Plastyczne i Rzemiosło', 'Z', 'Zm', 'Talent manualny. Malowanie, rzeźbienie i tworzenie sztuki wizualnej.'],
        ['Fotografia', 'Zm', 'Z', 'Kompozycja kadru, obsługa profesjonalnego sprzętu, praca ze światłem. Paparazzi, fotoreportaż, fotografia artystyczna.'],
        ['Krawiectwo i Stylizacja', 'Z', null, 'Szycie ubrań na miarę, naprawa pancerzy oraz tworzenie modowych trendów.'],
        ['Stand-up i Cięta Riposta', 'Ch', 'I', 'Zjednywanie publiki żartem i gaszenie oponentów słowem.'],
        ['Sztuka Iluzji (Kuglarstwo)', 'Z', 'Ch', 'Sztuczki magiczne, manipulacja kartami i odwracanie uwagi tłumu.'],
        ['Moda i Wizerunek', 'Ch', 'Zm', 'Dobieranie kreacji, dbanie o wygląd zewnętrzny i budowanie własnej marki osobistej.'],
        ['Streaming i Influencing', 'Ch', 'I', 'Budowanie publiczności w sieci, transmisje na żywo, social media, monetyzacja wizerunku online.'],
    ]],
    'outdoor' => ['nazwa' => 'Outdoor i Przyroda', 'kolor' => '#5aff9a', 'um' => [
        ['Myślistwo i Polowanie', 'Zm', 'Z', 'Śledzenie zwierzyny, zakładanie pułapek, strzelanie do ruchomego celu w terenie. Skórowanie i obróbka zdobyczy.'],
        ['Tropienie', 'Zm', 'I', 'Czytanie śladów na ziemi, ustalanie kierunku poruszania się zwierzęcia lub człowieka, lokalizowanie obozowisk.'],
        ['Przetrwanie w Dziczy', 'W', 'I', 'Rozpalanie ognia bez zapałek, rozpoznawanie jadalnych i trujących roślin, budowa szałasów, picie wody z dziczy.'],
        ['Jazda Konna', 'Z', 'W', 'Jazda wierzchem, opieka nad koniem, siodłanie, jazda w trudnym terenie.'],
        ['Rybołówstwo i Żegluga', 'I', 'W', 'Prowadzenie jednostek pływających od kutra po jacht, nawigacja morska, łowienie ryb, praca na otwartej wodzie.'],
        ['Spadochroniarstwo', 'Z', 'W', 'Skoki z wysokości, sterowanie spadochronem, lądowanie w terenie, skoki BASE z wież miasta.'],
    ]],
    'opieka' => ['nazwa' => 'Opieka Medyczna', 'kolor' => '#4ad6ff', 'um' => [
        ['Pielęgniarstwo', 'I', 'Z', 'Podawanie leków, zakładanie wkłuć, opatrunki, opieka nad pacjentami, pierwsza pomoc. Codzienna praktyka oddziałowa.'],
        ['Chirurgia', 'Z', 'I', 'Zaawansowane operacje: cięcie, szycie, transplantacje. Wymaga opanowania, twardej ręki i wiedzy anatomicznej.'],
        ['Fizjoterapia i Rehabilitacja', 'S', 'I', 'Rehabilitacja po urazach, masaż leczniczy, ćwiczenia przywracające sprawność, trening cyber-protez.'],
        ['Weterynaria', 'I', 'Z', 'Leczenie zwierząt: psów bojowych, koni, cyber-wszczepianych zwierząt syndykatu. Diagnostyka weterynaryjna i zabiegi chirurgiczne.'],
        ['Opieka nad Dziećmi i Starszymi', 'Ch', 'W', 'Opieka nad niemowlętami, wychowanie przedszkolne, opieka geriatryczna. Cierpliwość, empatia i spryt przy codziennych wyzwaniach.'],
    ]],
    'rzemioslo' => ['nazwa' => 'Rzemiosło Tradycyjne', 'kolor' => '#ff7a3d', 'um' => [
        ['Kowalstwo i Obróbka Metali', 'S', 'Z', 'Kucie, hartowanie, spawanie. Tworzenie mieczy, zbroi, krat. Tradycyjne rzemiosło w epoce drukarek 3D wciąż ma swoją cenę.'],
        ['Stolarstwo i Obróbka Drewna', 'Z', 'S', 'Praca piłą, dłutem, heblem. Meble, trumny, ściany, podłogi. Stolarz-cieśla wciąż potrzebny w każdej epoce.'],
        ['Jubilerstwo i Zegarmistrzostwo', 'Z', 'Zm', 'Tworzenie biżuterii, osadzanie kamieni szlachetnych, naprawa zegarków mechanicznych. Precyzja i oko do detalu.'],
        ['Introligatorstwo i Oprawa', 'Z', null, 'Oprawa książek, konserwacja starych tomów, tworzenie luksusowych wydań kolekcjonerskich. Ginący zawód.'],
    ]],
];

/** Płaska lista w stałej kolejności — indeks służy jako klucz w formularzu. */
function um_lista(): array {
    global $UM_KATEGORIE;
    $l = [];
    foreach ($UM_KATEGORIE as $kid => $k) foreach ($k['um'] as $u) $l[] = ['kat' => $kid, 'n' => $u[0], 'g' => $u[1], 'd' => $u[2], 'o' => $u[3]];
    return $l;
}

/** Koszt pojedynczego poziomu: 1–4 → 1 PU, 5 → 2 PU. */
function um_koszt(int $poziom): int { return $poziom >= 5 ? 2 : ($poziom >= 1 ? 1 : 0); }

/** Łączny koszt dojścia od 0 do danego poziomu. */
function um_koszt_do(int $poziom): int { $s = 0; for ($i = 1; $i <= $poziom; $i++) $s += um_koszt($i); return $s; }

/** PU za Profesję na danym etapie: 1 + 2 + 3 + 4. */
function um_pu_profesji(int $etap): int { $etap = max(0, min(4, $etap)); return intdiv($etap * ($etap + 1), 2); }

function um_ma_zalete(array $g, string $nazwa): bool {
    $z = ($g['zalety'] ?? '') === 'Brak' ? [] : array_map('trim', explode(',', (string)($g['zalety'] ?? '')));
    return in_array($nazwa, $z, true);
}

/** Źródła PU: [etykieta, liczba|null, aktywne]. */
function um_zrodla(array $g): array {
    $amer = in_array(strtoupper((string)($g['pochodzenie'] ?? '')), UM_POCHODZENIE_AMERYKANIN, true);
    $wyk  = um_ma_zalete($g, 'Wykształcony');
    $poz  = (int)($g['poziom'] ?? 1);
    $r = [
        ['Start', UM_PU_START, true],
        ['Pochodzenie: Amerykanin', $amer ? UM_PU_AMERYKANIN : null, $amer],
        ['Poziom postaci ' . $poz . ' (1 PU co 2 poziomy)', intdiv($poz, 2), true],
    ];
    foreach ([['profesja_fabularna', 'profesja_etap'], ['profesja2', 'profesja2_etap']] as $i => [$kn, $ke]) {
        $n = $g[$kn] ?? null; $e = (int)($g[$ke] ?? 0);
        $r[] = $n ? ['Profesja ' . ($i + 1) . ': ' . $n . ' · etap ' . $e . '/4', um_pu_profesji($e), true]
                  : ['Profesja ' . ($i + 1) . ': brak', null, false];
    }
    $r[] = ['Zaleta: Wykształcony', $wyk ? UM_PU_WYKSZTALCONY : null, $wyk];
    // Uniwersytet: 2 PU za stopień z maks. 2 kierunków (config/uniwersytet.php)
    global $polaczenie;
    if (function_exists('uni_pu') && isset($g['id']) && $polaczenie instanceof mysqli) {
        $pu_uni = uni_pu($polaczenie, (int)$g['id']);
        $r[] = ['Uniwersytet (dyplomy)', $pu_uni ?: null, $pu_uni > 0];
    }
    return $r;
}

function um_pula(array $g): int { $s = 0; foreach (um_zrodla($g) as $z) $s += (int)$z[1]; return $s; }

/** Wydane PU; poziomy spoza katalogu i powyżej 5 są pomijane / przycinane. */
function um_wydane(array $um): int {
    $s = 0;
    foreach (um_lista() as $u) $s += um_koszt_do(min(UM_MAX_POZIOM, (int)($um[$u['n']] ?? 0)));
    return $s;
}

/**
 * Wymagania Profesji z config/zawody.php są w starej skali (do ~14).
 * Przeliczenie na 1–5: etap 1 = stary / 5 (zaokr., min. 1), każdy kolejny etap +1, maks. 5.
 */
function um_wymaganie_etapu(int $stare, int $etap): int {
    if ($stare <= 0) return 0;
    return min(UM_MAX_POZIOM, max(1, (int)round($stare / 5)) + max(0, $etap - 1));
}

/** Szansa na teście Umiejętności przed modami MG: poziom×20 + ⅓ Atrybutu, limit PE. */
function um_szansa(int $poziom, int $atrybut): int { return min(UM_PE, $poziom * 20 + intdiv($atrybut, 3)); }

/* ── TEST k100 ─────────────────────────────────────────────────────────
   Stare bonusy RP (płaskie z pochodzenia, procentowe z zawodu) działały
   na skali bez limitu. Tu zamieniamy je na modyfikatory szansy:
     pochodzenie: 1 punkt = 5 pkt % (np. +2 → +10)
     zawód:       bonus % / 2, zaokrąglone (np. 25% → +13)
     Zaleta/Wada: ±UM_MOD_CECHA za każdą zaznaczoną
   Premie działają tylko przy poziomie ≥ 1, kary zawsze.
   Suma modów mieści się w −50…+50, wynik nie przekracza PE.            */
const UM_MOD_POCHODZENIE_PKT = 5;
const UM_MOD_ZAWOD_DZIELNIK  = 2;
const UM_MOD_CECHA           = 10;
const UM_MOD_MIN             = -50;
const UM_MOD_MAX             = 50;
const UM_KRYT_SUKCES         = 5;    // rzut ≤ 5
const UM_KRYT_PORAZKA        = 95;   // rzut ≥ 95
const UM_MIN_SUKCES_ZAKRES   = 10;   // ostatnie 10 pkt pod progiem = sukces minimalny

function um_definicja(string $nazwa): ?array {
    foreach (um_lista() as $u) if ($u['n'] === $nazwa) return $u;
    return null;
}

/** Wartość Atrybutu postaci po kluczu S/Z/W/I/Zm/Ch. */
function um_atrybut(array $g, string $klucz): int {
    global $UM_ATRYBUTY;
    return isset($UM_ATRYBUTY[$klucz]) ? (int)($g[$UM_ATRYBUTY[$klucz]['kolumna']] ?? 0) : 0;
}

/**
 * Rozbicie szansy na teście Umiejętności.
 * $atr — 'g' (główny), 'd' (dodatkowy) albo klucz Atrybutu. $mod_dod — mody spoza karty (ryzyko, cechy, MG).
 */
function um_test(array $g, string $nazwa, string $atr = 'g', int $mod_dod = 0): array {
    global $POCHODZENIA_DANE, $ZAWODY_DANE, $UM_ATRYBUTY;
    $def = um_definicja($nazwa);
    $um  = !empty($g['umiejetnosci']) ? (json_decode($g['umiejetnosci'], true) ?: []) : [];
    $poz = min(UM_MAX_POZIOM, (int)($um[$nazwa] ?? 0));

    $ak = $atr === 'g' ? ($def['g'] ?? 'I') : ($atr === 'd' ? ($def['d'] ?? $def['g'] ?? 'I') : $atr);
    if (!isset($UM_ATRYBUTY[$ak])) $ak = $def['g'] ?? 'I';
    $wart_atr = um_atrybut($g, $ak);

    $poch_pkt = 0; $poch_kara = 0;
    $p = $g['pochodzenie'] ?? null;
    if ($p && isset($POCHODZENIA_DANE[$p]['rp'])) {
        $poch_pkt  = (int)($POCHODZENIA_DANE[$p]['rp']['umiejetnosci_bonus_flat'][$nazwa] ?? 0);
        $poch_kara = abs((int)($POCHODZENIA_DANE[$p]['rp']['umiejetnosci_kara_flat'][$nazwa] ?? 0));
    }
    $zaw_proc = 0;
    $z = $g['profesja_fabularna'] ?? null;
    if ($z && isset($ZAWODY_DANE[$z]['rp']['umiejetnosci_bonus_proc'][$nazwa])) $zaw_proc = (int)$ZAWODY_DANE[$z]['rp']['umiejetnosci_bonus_proc'][$nazwa];

    $mod_poch = ($poz > 0 ? $poch_pkt : 0) * UM_MOD_POCHODZENIE_PKT - $poch_kara * UM_MOD_POCHODZENIE_PKT;
    $mod_zaw  = $poz > 0 ? (int)round($zaw_proc / UM_MOD_ZAWOD_DZIELNIK) : 0;
    $mod      = max(UM_MOD_MIN, min(UM_MOD_MAX, $mod_poch + $mod_zaw + $mod_dod));

    $baza   = $poz > 0 ? $poz * 20 + intdiv($wart_atr, 3) : $wart_atr;   // poziom 0 = test samego Atrybutu
    $szansa = max(0, min(UM_PE, $baza + $mod));

    return [
        'nazwa' => $nazwa, 'poziom' => $poz, 'atrybut' => $ak, 'atrybut_nazwa' => $UM_ATRYBUTY[$ak]['nazwa'],
        'atrybut_wart' => $wart_atr, 'baza' => $baza,
        'poch_pkt' => $poch_pkt - $poch_kara, 'mod_pochodzenia' => $mod_poch,
        'zawod_proc' => $zaw_proc, 'mod_zawodu' => $mod_zaw, 'mod_dodatkowy' => $mod_dod,
        'mod' => $mod, 'szansa' => $szansa, 'limit_pe' => ($baza + $mod) > UM_PE,
    ];
}

/** Test samego Atrybutu (bez Umiejętności). */
function um_test_atrybutu(array $g, string $klucz, int $mod_dod = 0): array {
    global $UM_ATRYBUTY;
    $w = um_atrybut($g, $klucz);
    $mod = max(UM_MOD_MIN, min(UM_MOD_MAX, $mod_dod));
    return ['nazwa' => null, 'poziom' => 0, 'atrybut' => $klucz, 'atrybut_nazwa' => $UM_ATRYBUTY[$klucz]['nazwa'] ?? $klucz,
            'atrybut_wart' => $w, 'baza' => $w, 'poch_pkt' => 0, 'mod_pochodzenia' => 0, 'zawod_proc' => 0, 'mod_zawodu' => 0,
            'mod_dodatkowy' => $mod_dod, 'mod' => $mod, 'szansa' => max(0, min(UM_PE, $w + $mod)), 'limit_pe' => ($w + $mod) > UM_PE];
}

/** Poziom powodzenia: 2 krytyczny sukces, 1 sukces, 0 minimalny sukces, -1 porażka, -2 krytyczna porażka. */
function um_wynik(int $rzut, int $szansa): array {
    if ($rzut <= UM_KRYT_SUKCES)  return ['poziom' => 2,  'nazwa' => 'Krytyczny sukces',  'sukces' => true];
    if ($rzut >= UM_KRYT_PORAZKA) return ['poziom' => -2, 'nazwa' => 'Krytyczna porażka', 'sukces' => false];
    if ($rzut > $szansa)          return ['poziom' => -1, 'nazwa' => 'Porażka',           'sukces' => false];
    if ($rzut > $szansa - UM_MIN_SUKCES_ZAKRES) return ['poziom' => 0, 'nazwa' => 'Minimalny sukces', 'sukces' => true];
    return ['poziom' => 1, 'nazwa' => 'Sukces', 'sukces' => true];
}

/** Krótki opis rozbicia szansy, np. "3×20 + 18 Siła · +10 poch. · +13 zawód = 80%". */
function um_opis_testu(array $t): string {
    $s = $t['poziom'] > 0 ? $t['poziom'] . '×20 + ' . intdiv($t['atrybut_wart'], 3) . ' ' . $t['atrybut_nazwa'] : $t['atrybut_nazwa'] . ' ' . $t['atrybut_wart'];
    foreach ([['mod_pochodzenia', 'poch.'], ['mod_zawodu', 'zawód'], ['mod_dodatkowy', 'mody']] as [$k, $l])
        if ($t[$k]) $s .= ' · ' . ($t[$k] > 0 ? '+' : '') . $t[$k] . ' ' . $l;
    return $s . ' = ' . $t['szansa'] . '%' . ($t['limit_pe'] ? ' (limit PE)' : '');
}
