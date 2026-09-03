<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// POBIERANIE DANYCH (z 4 nowymi kolumnami kierunków)
$wynik = $polaczenie->query("SELECT gotowka, energia_aktualna,
    uni_medycyna, uni_inzynieria, uni_ekonomia, uni_prawo,
    uni_sztuka, uni_cybernetyka, uni_humanistyka, uni_kryminologia,
    uni_weterynaria, uni_farmacja, uni_historia, uni_lotnictwo,
    uni_ostatni_wyklad, tytul_naukowy
    FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// KIERUNKI STUDIÓW I ICH KONFIGURACJA (12 kierunków)
$kierunki = [
    'medycyna' => [
        'db' => 'uni_medycyna', 'nazwa' => 'Medycyna Akademicka', 'ikona' => 'img/uni_medycyna.png', 'kolor' => '#ff3333',
        'wymagane_zajecia' => 14, 'koszt_kasy' => 500, 'koszt_en' => 5,
        'opis' => 'Pozwala w przyszłości pracować jako legalny Lekarz Rodzinny, Chirurg, Psychiatra, Pielęgniarka lub Fizjoterapeuta. Tytuł: Lekarz Medycyny.',
        'tytul_koncowy' => 'Lekarz Medycyny',
        'pytania' => [
            "Pacjent ma odrzut na nowy, tani cyber-wszczep wątroby. Toksyny zalewają organizm. Co podasz mu w pierwszej kolejności, zanim przejdziesz do operacji?",
            "Podczas strzelaniny w Dokach kula kalibru 9mm uszkodziła tętnicę udową pacjenta. Zostały Ci 2 minuty. Jak zatamujesz krwotok używając sprzętu z ulicy?"
        ]
    ],
    'inzynieria' => [
        'db' => 'uni_inzynieria', 'nazwa' => 'Inżynieria Zbrojeniowa', 'ikona' => 'img/uni_inzynieria.png', 'kolor' => '#00aaff',
        'wymagane_zajecia' => 20, 'koszt_kasy' => 600, 'koszt_en' => 5,
        'opis' => 'Opanowanie zaawansowanej technologii. Tytuł Głównego Inżyniera otwiera drogę do zawodów Architekta i Inżyniera Elektroniki.',
        'tytul_koncowy' => 'Główny Inżynier',
        'pytania' => [
            "Tworzymy karabin pulsacyjny. Cewki magnetyczne przegrzewają się po trzecim strzale, a chłodzenie cieczą odpada ze względu na wagę. Jak ustabilizujesz temperaturę lufy?",
            "Twój dron bojowy traci połączenie w tunelach metra z powodu silnych zakłóceń elektromagnetycznych. Jak zmodyfikujesz jego antenę odbiorczą, by utrzymać sygnał?"
        ]
    ],
    'ekonomia' => [
        'db' => 'uni_ekonomia', 'nazwa' => 'Ekonomia i Logistyka', 'ikona' => 'img/uni_ekonomia.png', 'kolor' => '#ffd700',
        'wymagane_zajecia' => 10, 'koszt_kasy' => 1000, 'koszt_en' => 3,
        'opis' => 'Klucz do kariery jako Księgowy, Przedsiębiorca, Spedytor lub Kadrowa. Daje tytuł Magistra Ekonomii.',
        'tytul_koncowy' => 'Magister Ekonomii',
        'pytania' => [
            "Lokalny syndykat chce wyprać 5 milionów brudnych dolarów w ciągu miesiąca przez sieć Twoich fałszywych kasyn. Jak podzielisz transakcje, by nie zaalarmować urzędu skarbowego?",
            "Cena syntetycznego tlenu drastycznie wzrosła. Masz monopol na dystrybucję w sektorze 4. Jak zoptymalizujesz logistykę, by zmaksymalizować zysk przy niezadowolonych mieszkańcach?"
        ]
    ],
    'prawo' => [
        'db' => 'uni_prawo', 'nazwa' => 'Prawo i Administracja', 'ikona' => 'img/uni_prawo.png', 'kolor' => '#dd88ff',
        'wymagane_zajecia' => 18, 'koszt_kasy' => 800, 'koszt_en' => 4,
        'opis' => 'Niezbędne do pracy jako Adwokat, Prokurator, Sędzia lub Polityk. Daje tytuł Magistra Prawa.',
        'tytul_koncowy' => 'Magister Prawa',
        'pytania' => [
            "Twój klient, szef gangu, został złapany z nielegalną bronią. Znalazłeś jednak lukę: nakaz przeszukania magazynu zawierał zły numer budynku. Jak sformułujesz wniosek o oddalenie dowodów?",
            "Korporacja oskarża małą firmę inżynieryjną o kradzież patentu. Reprezentujesz korporację. Jak udowodnisz przed sędzią, że inżynieria odwrotna w tym przypadku złamała prawa autorskie?"
        ]
    ],
    'sztuka' => [
        'db' => 'uni_sztuka', 'nazwa' => 'Akademia Sztuk Pięknych', 'ikona' => 'img/uni_sztuka.png', 'kolor' => '#ff66b3',
        'wymagane_zajecia' => 12, 'koszt_kasy' => 400, 'koszt_en' => 4,
        'opis' => 'Wymagane dla prestiżowych Reżyserów i elitarnych Projektantów Mody. Daje tytuł Magistra Sztuki.',
        'tytul_koncowy' => 'Magister Sztuki',
        'pytania' => [
            "Tworzysz instalację artystyczną, która ma być ukrytym przekazem podprogowym dla rebeliantów w mieście. Jakich bodźców wizualnych użyjesz, by ominąć algorytmy cenzury korporacji?",
            "Zaprojektuj linię ubrań haute couture, która jednocześnie ukrywa sygnaturę cieplną przed dronami zwiadowczymi. Jakie materiały wybierzesz?"
        ]
    ],
    'cybernetyka' => [
        'db' => 'uni_cybernetyka', 'nazwa' => 'Cybernetyka i Informatyka', 'ikona' => 'img/uni_cybernetyka.png', 'kolor' => '#00ffcc',
        'wymagane_zajecia' => 16, 'koszt_kasy' => 900, 'koszt_en' => 4,
        'opis' => 'Sztuka łamania korporacyjnych zabezpieczeń. Wymagane dla Hakerów i Programistów. Tytuł: Inżynier Cybernetyki.',
        'tytul_koncowy' => 'Inżynier Cybernetyki',
        'pytania' => [
            "Napostkałeś Czarny Lód (Black ICE) na serwerze bankowym, który w ułamek sekundy pali zwoje nerwowe hakera. Opisz sekwencję skryptów, których użyjesz do izolacji tego protokołu.",
            "Chcesz przejąć kontrolę nad flotą dronów dostawczych. Mają zmienne szyfrowanie kwantowe. Jak wykorzystasz opóźnienie w ich komunikacji z centralą, by wstrzyknąć złośliwy kod?"
        ]
    ],
    'humanistyka' => [
        'db' => 'uni_humanistyka', 'nazwa' => 'Pedagogika i Dziennikarstwo', 'ikona' => 'img/uni_humanistyka.png', 'kolor' => '#ffaa00',
        'wymagane_zajecia' => 10, 'koszt_kasy' => 300, 'koszt_en' => 3,
        'opis' => 'Manipulacja słowem. Wymagane dla Nauczycieli, Wykładowców, Dziennikarzy, Bibliotekarzy i Fotoreporterów. Tytuł: Magister Edukacji.',
        'tytul_koncowy' => 'Magister Edukacji',
        'pytania' => [
            "Korporacja zrzuciła toksyczne odpady do rzeki, a w mieście wybucha panika. Napisz krótki, manipulacyjny artykuł, który odwróci uwagę opinii publicznej od korporacji i zrzuci winę na mutanty z kanałów.",
            "Tłum protestujących zbiera się pod fabryką Twojego pracodawcy. Masz przemówić przez megafon. Co powiesz, by zasiać niezgodę w tłumie i doprowadzić do ich pokojowego rozejścia się?"
        ]
    ],
    'kryminologia' => [
        'db' => 'uni_kryminologia', 'nazwa' => 'Kryminologia i Bezpieczeństwo', 'ikona' => 'img/uni_kryminologia.png', 'kolor' => '#888888',
        'wymagane_zajecia' => 14, 'koszt_kasy' => 650, 'koszt_en' => 4,
        'opis' => 'Poznaj umysł przestępcy. Niezbędne dla Detektywów Policyjnych i Klawiszy Więziennych. Tytuł: Licencjat Kryminologii.',
        'tytul_koncowy' => 'Licencjat Kryminologii',
        'pytania' => [
            "Jesteś na miejscu zbrodni. Ofiara ma usunięte wszystkie cyber-wszczepy, ale brak jest śladów krwi w zaułku. Co to mówi o miejscu morderstwa i profilu sprawcy?",
            "Masz przed sobą podejrzanego o morderstwo korporacyjnego VIP-a. Jego tętno i oddech są sztucznie regulowane przez implanty, więc wariograf nie działa. Jakie techniki psychologiczne zastosujesz, by wymusić zeznanie?"
        ]
    ],

    /* ═══════════════════════ NOWE KIERUNKI ═══════════════════════ */

    'weterynaria' => [
        'db' => 'uni_weterynaria', 'nazwa' => 'Weterynaria', 'ikona' => 'img/uni_weterynaria.png', 'kolor' => '#88ff88',
        'wymagane_zajecia' => 14, 'koszt_kasy' => 500, 'koszt_en' => 5,
        'opis' => 'Leczenie zwierząt — od bezpańskich psów po modyfikowane bestie syndykatu. Otwiera zawód Weterynarza. Tytuł: Lekarz Weterynarii.',
        'tytul_koncowy' => 'Lekarz Weterynarii',
        'pytania' => [
            "Do Twojej kliniki trafia pies bojowy psyche-modified syndykatu — zaszczepiona agresja, tętno 180/min, krwawe ślady na pysku. Właściciel oferuje 10k za uspokojenie zwierzęcia i wypisanie bez pytań. Jak ustabilizujesz psa i jaki środek neutralizujący ich kokteil hormonów wściekłości zastosujesz?",
            "Bogacz przyprowadza rzadką jaszczurkę, przemycaną z zakazanych stref Ameryki Południowej. Odmawia ujawnienia pochodzenia zwierzęcia. Gad choruje na coś, czego nie ma w żadnym podręczniku. Jak poprowadzisz diagnostykę, nie łamiąc tajemnicy klienta i nie trafiając na listę CITES?"
        ]
    ],
    'farmacja' => [
        'db' => 'uni_farmacja', 'nazwa' => 'Farmacja', 'ikona' => 'img/uni_farmacja.png', 'kolor' => '#ff88cc',
        'wymagane_zajecia' => 12, 'koszt_kasy' => 600, 'koszt_en' => 4,
        'opis' => 'Chemia leków, recept i licencji. Otwiera zawód Farmaceuty i wzmacnia każdy związany z medycyną. Tytuł: Magister Farmacji.',
        'tytul_koncowy' => 'Magister Farmacji',
        'pytania' => [
            "Klient bez recepty prosi Cię o silny opioid — twierdzi, że znajomy lekarz obiecał. Za ladą obserwuje inspekcja farmaceutyczna. Jakie legalne zamienniki zaproponujesz, by jednocześnie pomóc klientowi, nie pójść siedzieć i nie stracić licencji?",
            "Opracowujesz syntetyczny lek na uporczywy kaszel dla dzielnicy przemysłowej. Surowce z legalnego rynku są za drogie, ale te z szarej strefy są zanieczyszczone. Jak zmodyfikujesz formułę, by używać tańszych prekursorów bez ryzyka wywołania efektu psychotropowego?"
        ]
    ],
    'historia' => [
        'db' => 'uni_historia', 'nazwa' => 'Historia i Archeologia', 'ikona' => 'img/uni_historia.png', 'kolor' => '#bb9966',
        'wymagane_zajecia' => 10, 'koszt_kasy' => 400, 'koszt_en' => 3,
        'opis' => 'Wiedza o przeszłości, artefaktach i autentyczności. Otwiera zawody Historyka/Archeologa i Bibliotekarza. Tytuł: Magister Historii.',
        'tytul_koncowy' => 'Magister Historii',
        'pytania' => [
            "W ruinach podmetrowych znalazłeś fresk sprzed 2000 lat, który podważa oficjalną narrację o założeniu miasta. Muzeum oferuje 50 tysięcy za ciszę, prywatny kolekcjoner — 500 tysięcy za wywóz za granicę. Jak uzasadnisz przed komisją UNESCO, że fresk musi zostać w mieście publicznie?",
            "Korporacja wydobywa rzekomy kosmiczny artefakt z pustyni Lower Manhattan. Jesteś powołanym ekspertem — Twoja analiza pokazuje, że to replika z polimeru XXII wieku. Jak sformułujesz oficjalny raport, by naukowo zdyskredytować fałszerstwo i nie zniknąć w bagażniku następnego dnia?"
        ]
    ],
    'lotnictwo' => [
        'db' => 'uni_lotnictwo', 'nazwa' => 'Lotnictwo Cywilne', 'ikona' => 'img/uni_lotnictwo.png', 'kolor' => '#00aaee',
        'wymagane_zajecia' => 16, 'koszt_kasy' => 1200, 'koszt_en' => 5,
        'opis' => 'Licencja pilota liniowego. Otwiera karierę Pilota Liniowego i wzmacnia każdy zawód związany z transportem. Tytuł: Pilot Liniowy.',
        'tytul_koncowy' => 'Pilot Liniowy',
        'pytania' => [
            "Lecisz rejsem transatlantyckim z VIP-ami syndykatu na pokładzie. Radar wskazuje eskortę trzech korporacyjnych dronów przechwytujących na kursie kolizyjnym. Masz 90 sekund do strefy kontroli ruchu. Jaką taktykę ewazyjną zastosujesz, by chronić pasażerów, nie łamiąc procedur bezpieczeństwa ICAO?",
            "Awaria silnika numer 2 nad zatoką Lower Manhattan Bay. Procedura AW-733 nakazuje powrót na lotnisko, ale masz uzasadnione podejrzenie, że to sabotaż i lądowanie grozi śmiercią załogi. Jak podejmiesz decyzję o wodowaniu awaryjnym i którego kanału radiowego użyjesz, by ominąć skompromitowaną wieżę kontroli lotów?"
        ]
    ],
];

// OBLICZANIE CZASU ODNOWIENIA
$teraz = time();
$ostatni_wyklad = strtotime($gracz['uni_ostatni_wyklad']);
$czas_odnowienia = 24 * 3600;
$sekundy_od_wykladu = $teraz - $ostatni_wyklad;

$mozna_studiowac = ($sekundy_od_wykladu >= $czas_odnowienia);
$pozostalo_sekund = max(0, $czas_odnowienia - $sekundy_od_wykladu);

// STATUS EGZAMINU
$widok_egzaminu = false;
$aktualny_kierunek_egzamin = "";
$pytanie_egzaminacyjne = "";

// ========================================================
// LOGIKA 1: ROZPOCZĘCIE EGZAMINU
// ========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rozpocznij_egzamin'])) {
    $wybrany_id = $_POST['id_kierunku'];
    if (array_key_exists($wybrany_id, $kierunki)) {
        $widok_egzaminu = true;
        $aktualny_kierunek_egzamin = $wybrany_id;

        $pule_pytan = $kierunki[$wybrany_id]['pytania'] ?? ["Opisz najważniejszy aspekt Twojej wiedzy zdobytej na tym wydziale."];
        $pytanie_egzaminacyjne = $pule_pytan[array_rand($pule_pytan)];
        $_SESSION['aktywne_pytanie'] = $pytanie_egzaminacyjne;
    }
}

// ========================================================
// LOGIKA 2: WERYFIKACJA EGZAMINU PRZEZ AI (API)
// ========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['zatwierdz_egzamin'])) {
    $wybrany_id = $_POST['id_kierunku'];
    $odpowiedz_gracza = trim($_POST['odpowiedz_gracza']);
    $pytanie = $_SESSION['aktywne_pytanie'];
    $k = $kierunki[$wybrany_id];
    $kolumna_db = $k['db'];

    // --- INTEGRACJA API AI (CURL) ---
    // WAŻNE: TUTAJ WKLEJ SWÓJ KLUCZ API OPENAI
    $klucz_api_openai = "TUTAJ_WKLEJ_SWOJ_KLUCZ";

    $prompt_systemowy = "Jesteś surowym, bezwzględnym dziekanem wydziału '{$k['nazwa']}' na cyberpunkowym uniwersytecie. Egzaminujesz studenta, aby wydać mu dyplom '{$k['tytul_koncowy']}'.";
    $prompt_uzytkownika = "Pytanie: $pytanie\n\nOdpowiedź studenta: $odpowiedz_gracza\n\nOceń tę odpowiedź merytorycznie oraz pod kątem wczucia się w rolę (roleplay) w skali 1 do 10. Zwróć wynik w CZYSTYM formacie JSON: {\"ocena\": 8, \"komentarz\": \"Twój mroczny komentarz zwrotny dla studenta\"}. Nic więcej.";

    $ai_odpowiedz = "";
    $ocena = 0;

    if ($klucz_api_openai == "TUTAJ_WKLEJ_SWOJ_KLUCZ") {
        $ocena = rand(5, 10);
        $ai_odpowiedz = "System AI symuluje odpowiedź: Twój kod zadziałał, ale musisz wkleić swój Klucz API w pliku, by oceniać prawdziwe odpowiedzi!";
    } else {
        $dane_api = [
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => $prompt_systemowy],
                ["role" => "user", "content" => $prompt_uzytkownika]
            ],
            "temperature" => 0.7
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dane_api));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $klucz_api_openai
        ]);

        $wynik_curl = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($wynik_curl, true);

        if (isset($response['choices'][0]['message']['content'])) {
            $ai_json = json_decode($response['choices'][0]['message']['content'], true);
            $ocena = isset($ai_json['ocena']) ? (int)$ai_json['ocena'] : 0;
            $ai_odpowiedz = isset($ai_json['komentarz']) ? $ai_json['komentarz'] : "Błąd przesyłu danych z matrycy Dziekana.";
        } else {
            $ocena = 0;
            $ai_odpowiedz = "Wystąpił błąd w łączności z neural-netem Dziekana. Uznano to za próbę oszustwa na egzaminie!";
        }
    }

    // --- ROZSTRZYGNIĘCIE EGZAMINU ---
    if ($ocena >= 7) {
        $nowy_tytul = $k['tytul_koncowy'];
        $nowy_tytul_safe = $polaczenie->real_escape_string($nowy_tytul);
        $polaczenie->query("UPDATE gracze SET
            $kolumna_db = $kolumna_db + 1,
            tytul_naukowy = '$nowy_tytul_safe',
            uni_ostatni_wyklad = NOW()
            WHERE id = $id_gracza");

        $gracz[$kolumna_db] += 1;
        $gracz['tytul_naukowy'] = $nowy_tytul;

        $komunikat = "<div class='sukces' style='border-color:#ffd700; color:#ffd700; box-shadow: 0 0 20px rgba(255,215,0,0.5);'>
            <h2 style='font-family:Oswald; margin-top:0;'>EGZAMIN ZDANY! (Ocena: $ocena/10)</h2>
            <p style='color:#ccc; font-style:italic;'>\"$ai_odpowiedz\"</p>
            <p>Otrzymujesz tytuł: <b style='color:#fff;'>$nowy_tytul</b>!</p>
        </div>";
        unset($_SESSION['aktywne_pytanie']);
    } else {
        $polaczenie->query("UPDATE gracze SET energia_aktualna = energia_aktualna - 5, uni_ostatni_wyklad = NOW() WHERE id = $id_gracza");
        $gracz['energia_aktualna'] -= 5;

        $komunikat = "<div class='blad' style='border-color:#ff3333; box-shadow: 0 0 20px rgba(255,51,51,0.5);'>
            <h2 style='font-family:Oswald; margin-top:0;'>EGZAMIN OBLANY! (Ocena: $ocena/10)</h2>
            <p style='color:#ccc; font-style:italic;'>\"$ai_odpowiedz\"</p>
            <p>Dziekan wyrzucił Cię z gabinetu. Tracisz 5 Energii z powodu potężnego stresu. Spróbuj ponownie jutro!</p>
        </div>";
        unset($_SESSION['aktywne_pytanie']);
    }
    $mozna_studiowac = false;
}

// ========================================================
// LOGIKA 3: STANDARDOWY WYKŁAD (Zdarzenia Losowe)
// ========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_kierunku']) && !isset($_POST['rozpocznij_egzamin']) && !isset($_POST['zatwierdz_egzamin'])) {
    $wybrany_id = $_POST['id_kierunku'];

    if (array_key_exists($wybrany_id, $kierunki)) {
        $k = $kierunki[$wybrany_id];
        $kolumna_db = $k['db'];
        $postep_gracza = $gracz[$kolumna_db];

        if (!$mozna_studiowac) {
            $komunikat = "<div class='blad'>Byłeś już dzisiaj na zajęciach! Wykładowcy też muszą odpocząć. Wróć jutro.</div>";
        } elseif ($postep_gracza >= $k['wymagane_zajecia']) {
            $komunikat = "<div class='info'>Już ukończyłeś ten kierunek! Masz dyplom w kieszeni.</div>";
        } elseif ($gracz['gotowka'] < $k['koszt_kasy']) {
            $komunikat = "<div class='blad'>Brak środków na opłacenie czesnego ({$k['koszt_kasy']} $).</div>";
        } elseif ($gracz['energia_aktualna'] < $k['koszt_en']) {
            $komunikat = "<div class='blad'>Jesteś zbyt zmęczony na naukę ({$k['koszt_en']} EN). Odpocznij.</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET
                gotowka = gotowka - {$k['koszt_kasy']},
                energia_aktualna = energia_aktualna - {$k['koszt_en']},
                $kolumna_db = $kolumna_db + 1,
                uni_ostatni_wyklad = NOW()
                WHERE id = $id_gracza");

            $gracz['gotowka'] -= $k['koszt_kasy'];
            $gracz['energia_aktualna'] -= $k['koszt_en'];
            $gracz[$kolumna_db] += 1;
            $mozna_studiowac = false;

            $komunikat = "<div class='sukces'>Ukończyłeś kolejne zajęcia z kierunku <b>{$k['nazwa']}</b>! Twoja wiedza rośnie.</div>";
        }
    }
}
?>

<style>
    .uni-header {
        text-align: center; padding: 25px 0 30px 0; border-bottom: 1px solid #333;
        margin-bottom: 30px; position: relative;
    }
    .uni-header h1 {
        font-family: 'Oswald', sans-serif; font-size: 2.8em; color: #fff;
        text-transform: uppercase; letter-spacing: 6px;
        text-shadow: 0 0 20px rgba(255,215,0,0.4), 0 0 40px rgba(255,51,51,0.15);
        margin: 0; font-weight: 500;
    }
    .uni-header p {
        color: #aaa; font-style: italic; font-size: 1.1em;
        font-family: 'Open Sans', sans-serif; margin-top: 8px;
    }
    .uni-header::after {
        content: ''; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%);
        width: 180px; height: 1px; background: #ffd700; box-shadow: 0 0 10px #ffd700;
    }
    .cooldown-box {
        background: rgba(10,10,20,0.8); backdrop-filter: blur(10px);
        padding: 25px; border: 1px solid #444; border-left: 4px solid #00aaff;
        border-radius: 6px; margin-bottom: 30px; text-align: center;
        box-shadow: 0 5px 25px rgba(0,0,0,0.6);
    }
    .cooldown-box h2 {
        font-family: 'Oswald', sans-serif; font-size: 1.6em;
        margin: 0 0 10px 0; letter-spacing: 2px;
    }
    .karty-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
        gap: 20px; margin-top: 20px;
    }
    .karta-kierunku {
        display: flex; align-items: stretch; background: rgba(20,20,30,0.8);
        backdrop-filter: blur(8px); border: 1px solid #333; border-radius: 6px;
        overflow: hidden; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.7);
        position: relative;
    }
    .karta-kierunku:hover { transform: translateY(-3px); border-color: #555; }
    .kierunek-ikona {
        width: 130px; padding: 20px; display: flex; align-items: center; justify-content: center;
        background: rgba(0,0,0,0.4); border-right: 1px solid rgba(255,255,255,0.05);
        position: relative; flex-shrink: 0;
    }
    .kierunek-ikona img { width: 100px; height: 100px; object-fit: contain; z-index: 2; }
    .kierunek-glow {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 100px; height: 100px; border-radius: 50%;
        filter: blur(40px); opacity: 0.4; z-index: 1;
    }
    .kierunek-info { flex: 1; padding: 18px 22px; display: flex; flex-direction: column; justify-content: center; }
    .kierunek-info h3 {
        font-family: 'Oswald', sans-serif; font-size: 1.5em; margin: 0 0 8px 0;
        letter-spacing: 1.5px; text-transform: uppercase;
    }
    .kierunek-info p { color: #aaa; font-size: 0.95em; font-family: 'Open Sans', sans-serif; margin-bottom: 12px; line-height: 1.45; }
    .postep-bg {
        background: rgba(0,0,0,0.5); height: 20px; border-radius: 3px;
        border: 1px solid #333; overflow: hidden; position: relative;
    }
    .postep-fill { height: 100%; border-radius: 2px; transition: 0.5s; box-shadow: 0 0 10px currentColor; }
    .postep-text { text-align: right; color: #aaa; font-family: 'JetBrains Mono', monospace; font-size: 0.85em; margin-top: 4px; letter-spacing: 1px; }

    .kierunek-akcja {
        padding: 18px; display: flex; flex-direction: column; justify-content: center;
        align-items: center; border-left: 1px solid rgba(255,255,255,0.05);
        min-width: 170px; background: rgba(0,0,0,0.2);
    }
    .kierunek-koszt { color: #aaa; font-size: 0.85em; margin-bottom: 12px; text-align: center; font-family: 'JetBrains Mono', monospace; }
    .btn-studiuj {
        background: rgba(0,0,0,0.6); color: #fff; border: 1px solid #555;
        padding: 10px 18px; font-family: 'Oswald', sans-serif; font-size: 0.95em;
        text-transform: uppercase; letter-spacing: 1.5px; cursor: pointer;
        border-radius: 3px; transition: 0.25s; width: 100%;
    }
    .btn-studiuj:hover { background: rgba(255,255,255,0.08); box-shadow: 0 0 15px rgba(255,255,255,0.15); }
    .btn-zablokowany { background: #111; color: #555; border-color: #333; cursor: not-allowed; }

    .sukces, .blad, .info {
        padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid;
        font-family: 'Open Sans', sans-serif;
    }
    .sukces { background: rgba(0,255,100,0.07); border-color: #0f0; color: #9fff9f; }
    .blad { background: rgba(255,0,0,0.08); border-color: #ff3333; color: #ff9999; }
    .info { background: rgba(0,170,255,0.07); border-color: #00aaff; color: #99ccff; }

    /* Egzamin Dziekanatu */
    .egzamin-modal {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
        z-index: 999; display: flex; justify-content: center; align-items: center;
    }
    .egzamin-pudlo {
        background: linear-gradient(135deg, rgba(20,0,0,0.95), rgba(40,10,10,0.95));
        border: 2px solid #ff3333; padding: 40px; border-radius: 8px;
        max-width: 700px; width: 90%; box-shadow: 0 0 60px rgba(255,51,51,0.5);
    }
    .egzamin-tytul {
        color: #ff3333; font-family: 'Oswald', sans-serif; font-size: 1.8em; letter-spacing: 3px;
        text-transform: uppercase; margin-top: 0; text-shadow: 0 0 15px #ff3333;
        border-bottom: 1px dashed rgba(255,51,51,0.5); padding-bottom: 15px; margin-bottom: 30px;
    }
    .egzamin-prompt { color: #fff; font-size: 1.2em; line-height: 1.6; margin-bottom: 30px; border-left: 4px solid #ff3333; padding-left: 20px; background: rgba(255,0,0,0.1); padding: 15px;}
    .egzamin-textarea {
        width: 100%; height: 200px; background: rgba(0,0,0,0.8); color: #00ff00;
        border: 1px solid #ff3333; padding: 20px; font-family: monospace; font-size: 1.1em;
        border-radius: 6px; box-sizing: border-box; resize: vertical; margin-bottom: 20px;
    }
    .egzamin-textarea:focus { outline: none; box-shadow: 0 0 15px rgba(255,51,51,0.5); }

    .btn-egzamin-akcja {
        background: rgba(255, 0, 0, 0.2); color: #fff; border: 2px solid #ff3333;
        padding: 15px 40px; font-family: 'Oswald', sans-serif; font-size: 1.3em;
        text-transform: uppercase; cursor: pointer; border-radius: 6px; font-weight: 700;
        text-shadow: 0 0 10px #ff3333; box-shadow: 0 0 20px rgba(255,51,51,0.4);
        transition: 0.3s;
    }
    .btn-egzamin-akcja:hover { background: #ff3333; color: #000; box-shadow: 0 0 40px rgba(255,51,51,0.8); }

    /* Pulsujący guzik przed egzaminem */
    .btn-egzamin { background: rgba(150, 0, 0, 0.4); color: #ff3333; border: 2px solid #ff3333; text-shadow: 0 0 10px #ff3333; box-shadow: inset 0 0 10px rgba(255,51,51,0.2), 0 0 15px rgba(255,51,51,0.1); animation: puls-egzamin 2s infinite; }
    .btn-egzamin:hover { background: rgba(255, 51, 51, 0.2); color: #fff; box-shadow: inset 0 0 20px rgba(255,51,51,0.5), 0 0 20px rgba(255,51,51,0.6); text-shadow: 0 0 10px #fff; }
    @keyframes puls-egzamin { 0% { border-color: #aa0000; box-shadow: 0 0 5px rgba(170,0,0,0.2); } 50% { border-color: #ff3333; box-shadow: 0 0 20px rgba(255,51,51,0.5); } 100% { border-color: #aa0000; box-shadow: 0 0 5px rgba(170,0,0,0.2); } }
</style>

<?php if ($widok_egzaminu): ?>
<div class="egzamin-modal">
    <div class="egzamin-pudlo">
        <h2 class="egzamin-tytul">TERMINAL DZIEKANATU // EGZAMIN DYPLOMOWY</h2>
        <div class="egzamin-prompt">
            <span style="color:#ff3333; font-family: Oswald; text-transform:uppercase;">Pytanie systemu:</span><br>
            <?php echo $pytanie_egzaminacyjne; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="id_kierunku" value="<?php echo $aktualny_kierunek_egzamin; ?>">
            <textarea name="odpowiedz_gracza" class="egzamin-textarea" placeholder="Wprowadź odpowiedź... System AI przeanalizuje Twoją wiedzę." required></textarea>

            <div style="display: flex; justify-content: space-between;">
                <a href="game.php?page=uniwersytet" style="color: #888; text-decoration: none; padding: 15px; font-family: Oswald;">[ Anuluj i ucieknij ]</a>
                <button type="submit" name="zatwierdz_egzamin" class="btn-egzamin-akcja">ZATWIERDŹ ODPOWIEDŹ</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="uni-header">
    <h1>Akademia Nauk</h1>
    <p>Wiedza to potęga. A w tym mieście potęga decyduje o tym, kto przeżyje.</p>
</div>

<?php echo $komunikat; ?>

<div class="cooldown-box">
    <?php if (!empty($gracz['tytul_naukowy'])): ?>
        <div style="color: #ffd700; font-family: 'Oswald', sans-serif; font-size: 1.3em; margin-bottom: 15px; text-shadow: 0 0 10px rgba(255,215,0,0.4);">
            🎓 Twój aktualny tytuł: <b style="text-transform: uppercase; letter-spacing: 1px;"><?php echo $gracz['tytul_naukowy']; ?></b>
        </div>
    <?php endif; ?>

    <?php if ($mozna_studiowac): ?>
        <h2 style="color: #00ff00; text-shadow: 0 0 10px rgba(0,255,0,0.3);">✓ Sale wykładowe są otwarte</h2>
        <p style="color: #aaa; font-family: 'Open Sans', sans-serif;">Możesz wziąć udział w jednych zajęciach dziennie. Wybierz mądrze.</p>
    <?php else: ?>
        <h2 style="color: #ffaa00; text-shadow: 0 0 10px rgba(255,170,0,0.3);">⏳ Trwa regeneracja umysłu...</h2>
        <p style="color: #aaa; font-family: 'Open Sans', sans-serif;">Wykłady są bardzo wyczerpujące. Następne zajęcia będą dostępne za:</p>
        <div style="font-family: 'Oswald', sans-serif; font-size: 2.5em; font-weight: 700; color: #fff; letter-spacing: 2px; text-shadow: 0 2px 5px rgba(0,0,0,0.8);">
            <?php echo gmdate("H:i:s", $pozostalo_sekund); ?>
        </div>
    <?php endif; ?>
</div>

<div class="karty-grid">
<?php foreach ($kierunki as $id_k => $dane):
    $postep = $gracz[$dane['db']];
    $max = $dane['wymagane_zajecia'];
    $procent = min(100, ($postep / $max) * 100);
    $ukonczone = ($postep >= $max);

    $sciezka_do_pliku = $dane['ikona'];
    if (file_exists($sciezka_do_pliku)) {
        $ikona_html = "<img src='{$sciezka_do_pliku}' alt='{$dane['nazwa']}'>";
    } else {
        $ikona_html = "<div style='width: 100px; height: 100px; background-color: {$dane['kolor']}; border-radius: 10px; opacity: 0.5;'></div>";
    }
?>
    <div class="karta-kierunku" style="<?php if($ukonczone) echo 'border-color: '.$dane['kolor'].'; box-shadow: 0 0 20px rgba(0,0,0,0.8), inset 0 0 15px '.$dane['kolor'].'33;'; ?>">

        <div class="kierunek-ikona">
            <div class="kierunek-glow" style="background-color: <?php echo $dane['kolor']; ?>;"></div>
            <?php echo $ikona_html; ?>
        </div>

        <div class="kierunek-info">
            <h3 style="color: <?php echo $dane['kolor']; ?>; text-shadow: 0 0 10px <?php echo $dane['kolor']; ?>88;"><?php echo $dane['nazwa']; ?></h3>
            <p><?php echo $dane['opis']; ?></p>

            <div class="postep-bg"><div class="postep-fill" style="background: <?php echo $dane['kolor']; ?>; color: <?php echo $dane['kolor']; ?>; width: <?php echo $procent; ?>%;"></div></div>
            <div class="postep-text">Zaliczono: <b style="color:#fff;"><?php echo $postep; ?> / <?php echo $max; ?></b></div>
        </div>

        <div class="kierunek-akcja">
            <div class="kierunek-koszt">
                Koszt: <b style="color: #00ff00;"><?php echo $dane['koszt_kasy']; ?> $</b> | <b style="color: #00ccff;"><?php echo $dane['koszt_en']; ?> EN</b>
            </div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="id_kierunku" value="<?php echo $id_k; ?>">

                <?php if ($ukonczone): ?>
                    <button type="button" class="btn-studiuj btn-zablokowany" disabled>🎓 UKOŃCZONO</button>

                <?php elseif ($postep == ($max - 1) && $mozna_studiowac): ?>
                    <button type="submit" name="rozpocznij_egzamin" value="tak" class="btn-studiuj btn-egzamin">PODEJDŹ DO EGZAMINU!</button>

                <?php elseif (!$mozna_studiowac): ?>
                    <button type="button" class="btn-studiuj btn-zablokowany" disabled>CZEKAJ NA JUTRO</button>

                <?php else: ?>
                    <button type="submit" class="btn-studiuj" style="border-color: <?php echo $dane['kolor']; ?>; color: <?php echo $dane['kolor']; ?>;">WEŹ UDZIAŁ</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>