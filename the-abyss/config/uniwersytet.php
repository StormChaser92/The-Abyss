<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — CONFIG/UNIWERSYTET.PHP
   Kierunki, stopnie i zasady studiów. Jedno źródło dla pages/uniwersytet.php,
   karty postaci (Profesje) i puli PU (config/umiejetnosci.php).

   Zasady:
   - Stopnie: Licencjat (etap 1 Profesji) → Magister (etapy 2–3) → Doktor (etap 4).
   - Zajęcia na stopień: Licencjat ×1, Magister ×1,5, Doktor ×2. Czesne ×1 / ×2 / ×3.
   - Ostatnie zajęcia stopnia to egzamin pisemny oceniany przez AI.
   - Jedne zajęcia dziennie (licząc od startu), trwają 8 h, czas płynie offline.
   - PU: 2 za stopień, liczone z maks. 2 kierunków (najwyższe stopnie) — do 12 PU.
   ═══════════════════════════════════════════════════════════════════════ */

const UNI_CZAS_ZAJEC_S     = 8 * 3600;
const UNI_ODSTEP_S         = 24 * 3600;
const UNI_PU_ZA_STOPIEN    = 2;
const UNI_PU_MAKS_KIERUNKOW = 2;
const UNI_EGZAMIN_PROG     = 7;    // ocena AI 1–10
const UNI_EGZAMIN_KARA_EN  = 5;

$UNI_STOPNIE = [
    1 => ['nazwa' => 'Licencjat', 'skrot' => 'Licz.', 'zajecia' => 1.0, 'czesne' => 1, 'etapy' => 'Etap 1'],
    2 => ['nazwa' => 'Magister',  'skrot' => 'Mag.',  'zajecia' => 1.5, 'czesne' => 2, 'etapy' => 'Etapy 2–3'],
    3 => ['nazwa' => 'Doktor',    'skrot' => 'Dok.',  'zajecia' => 2.0, 'czesne' => 3, 'etapy' => 'Etap 4'],
];

$UNI_KIERUNKI = [
    'medycyna' => [
        'nazwa' => 'Medycyna Akademicka', 'dziedzina' => 'Medycyny', 'kolor' => '#ff3b4e',
        'zajecia' => 14, 'czesne' => 500, 'energia' => 5,
        'stary_tytul' => 'Lekarz Medycyny',
        'pytania' => [
            'Pacjent ma odrzut na nowy, tani cyber-wszczep wątroby. Toksyny zalewają organizm. Co podasz mu w pierwszej kolejności, zanim przejdziesz do operacji?',
            'Podczas strzelaniny w Dokach kula kalibru 9mm uszkodziła tętnicę udową pacjenta. Zostały Ci 2 minuty. Jak zatamujesz krwotok używając sprzętu z ulicy?',
        ],
    ],
    'inzynieria' => [
        'nazwa' => 'Inżynieria Zbrojeniowa', 'dziedzina' => 'Inżynierii', 'kolor' => '#2ea8ff',
        'zajecia' => 20, 'czesne' => 600, 'energia' => 5,
        'stary_tytul' => 'Główny Inżynier',
        'pytania' => [
            'Tworzymy karabin pulsacyjny. Cewki magnetyczne przegrzewają się po trzecim strzale, a chłodzenie cieczą odpada ze względu na wagę. Jak ustabilizujesz temperaturę lufy?',
            'Twój dron bojowy traci połączenie w tunelach metra z powodu silnych zakłóceń elektromagnetycznych. Jak zmodyfikujesz jego antenę odbiorczą, by utrzymać sygnał?',
        ],
    ],
    'ekonomia' => [
        'nazwa' => 'Ekonomia i Logistyka', 'dziedzina' => 'Ekonomii', 'kolor' => '#ffcf33',
        'zajecia' => 10, 'czesne' => 1000, 'energia' => 3,
        'stary_tytul' => 'Magister Ekonomii',
        'pytania' => [
            'Lokalny syndykat chce wyprać 5 milionów brudnych dolarów w ciągu miesiąca przez sieć Twoich fałszywych kasyn. Jak podzielisz transakcje, by nie zaalarmować urzędu skarbowego?',
            'Cena syntetycznego tlenu drastycznie wzrosła. Masz monopol na dystrybucję w sektorze 4. Jak zoptymalizujesz logistykę, by zmaksymalizować zysk przy niezadowolonych mieszkańcach?',
        ],
    ],
    'prawo' => [
        'nazwa' => 'Prawo i Administracja', 'dziedzina' => 'Prawa', 'kolor' => '#d98cff',
        'zajecia' => 18, 'czesne' => 800, 'energia' => 4,
        'stary_tytul' => 'Magister Prawa',
        'pytania' => [
            'Twój klient, szef gangu, został złapany z nielegalną bronią. Znalazłeś jednak lukę: nakaz przeszukania magazynu zawierał zły numer budynku. Jak sformułujesz wniosek o oddalenie dowodów?',
            'Korporacja oskarża małą firmę inżynieryjną o kradzież patentu. Reprezentujesz korporację. Jak udowodnisz przed sędzią, że inżynieria odwrotna w tym przypadku złamała prawa autorskie?',
        ],
    ],
    'sztuka' => [
        'nazwa' => 'Akademia Sztuk Pięknych', 'dziedzina' => 'Sztuki', 'kolor' => '#ff6fb8',
        'zajecia' => 12, 'czesne' => 400, 'energia' => 4,
        'stary_tytul' => 'Magister Sztuki',
        'pytania' => [
            'Tworzysz instalację artystyczną, która ma być ukrytym przekazem podprogowym dla rebeliantów w mieście. Jakich bodźców wizualnych użyjesz, by ominąć algorytmy cenzury korporacji?',
            'Zaprojektuj linię ubrań haute couture, która jednocześnie ukrywa sygnaturę cieplną przed dronami zwiadowczymi. Jakie materiały wybierzesz?',
        ],
    ],
    'cybernetyka' => [
        'nazwa' => 'Cybernetyka i Informatyka', 'dziedzina' => 'Cybernetyki', 'kolor' => '#20f0c8',
        'zajecia' => 16, 'czesne' => 900, 'energia' => 4,
        'stary_tytul' => 'Inżynier Cybernetyki',
        'pytania' => [
            'Napostkałeś Czarny Lód (Black ICE) na serwerze bankowym, który w ułamek sekundy pali zwoje nerwowe hakera. Opisz sekwencję skryptów, których użyjesz do izolacji tego protokołu.',
            'Chcesz przejąć kontrolę nad flotą dronów dostawczych. Mają zmienne szyfrowanie kwantowe. Jak wykorzystasz opóźnienie w ich komunikacji z centralą, by wstrzyknąć złośliwy kod?',
        ],
    ],
    'humanistyka' => [
        'nazwa' => 'Pedagogika i Dziennikarstwo', 'dziedzina' => 'Edukacji', 'kolor' => '#ffab2e',
        'zajecia' => 10, 'czesne' => 300, 'energia' => 3,
        'stary_tytul' => 'Magister Edukacji',
        'pytania' => [
            'Korporacja zrzuciła toksyczne odpady do rzeki, a w mieście wybucha panika. Napisz krótki, manipulacyjny artykuł, który odwróci uwagę opinii publicznej od korporacji i zrzuci winę na mutanty z kanałów.',
            'Tłum protestujących zbiera się pod fabryką Twojego pracodawcy. Masz przemówić przez megafon. Co powiesz, by zasiać niezgodę w tłumie i doprowadzić do ich pokojowego rozejścia się?',
        ],
    ],
    'kryminologia' => [
        'nazwa' => 'Kryminologia i Bezpieczeństwo', 'dziedzina' => 'Kryminologii', 'kolor' => '#b7b7c9',
        'zajecia' => 14, 'czesne' => 650, 'energia' => 4,
        'stary_tytul' => 'Licencjat Kryminologii',
        'pytania' => [
            'Jesteś na miejscu zbrodni. Ofiara ma usunięte wszystkie cyber-wszczepy, ale brak jest śladów krwi w zaułku. Co to mówi o miejscu morderstwa i profilu sprawcy?',
            'Masz przed sobą podejrzanego o morderstwo korporacyjnego VIP-a. Jego tętno i oddech są sztucznie regulowane przez implanty, więc wariograf nie działa. Jakie techniki psychologiczne zastosujesz, by wymusić zeznanie?',
        ],
    ],
    'weterynaria' => [
        'nazwa' => 'Weterynaria', 'dziedzina' => 'Weterynarii', 'kolor' => '#7dff8a',
        'zajecia' => 14, 'czesne' => 500, 'energia' => 5,
        'stary_tytul' => 'Lekarz Weterynarii',
        'pytania' => [
            'Do Twojej kliniki trafia pies bojowy psyche-modified syndykatu — zaszczepiona agresja, tętno 180/min, krwawe ślady na pysku. Właściciel oferuje 10k za uspokojenie zwierzęcia i wypisanie bez pytań. Jak ustabilizujesz psa i jaki środek neutralizujący ich kokteil hormonów wściekłości zastosujesz?',
            'Bogacz przyprowadza rzadką jaszczurkę, przemycaną z zakazanych stref Ameryki Południowej. Odmawia ujawnienia pochodzenia zwierzęcia. Gad choruje na coś, czego nie ma w żadnym podręczniku. Jak poprowadzisz diagnostykę, nie łamiąc tajemnicy klienta i nie trafiając na listę CITES?',
        ],
    ],
    'farmacja' => [
        'nazwa' => 'Farmacja', 'dziedzina' => 'Farmacji', 'kolor' => '#ff8fd0',
        'zajecia' => 12, 'czesne' => 600, 'energia' => 4,
        'stary_tytul' => 'Magister Farmacji',
        'pytania' => [
            'Klient bez recepty prosi Cię o silny opioid — twierdzi, że znajomy lekarz obiecał. Za ladą obserwuje inspekcja farmaceutyczna. Jakie legalne zamienniki zaproponujesz, by jednocześnie pomóc klientowi, nie pójść siedzieć i nie stracić licencji?',
            'Opracowujesz syntetyczny lek na uporczywy kaszel dla dzielnicy przemysłowej. Surowce z legalnego rynku są za drogie, ale te z szarej strefy są zanieczyszczone. Jak zmodyfikujesz formułę, by używać tańszych prekursorów bez ryzyka wywołania efektu psychotropowego?',
        ],
    ],
    'historia' => [
        'nazwa' => 'Historia i Archeologia', 'dziedzina' => 'Historii', 'kolor' => '#d4a86a',
        'zajecia' => 10, 'czesne' => 400, 'energia' => 3,
        'stary_tytul' => 'Magister Historii',
        'pytania' => [
            'W ruinach podmetrowych znalazłeś fresk sprzed 2000 lat, który podważa oficjalną narrację o założeniu miasta. Muzeum oferuje 50 tysięcy za ciszę, prywatny kolekcjoner — 500 tysięcy za wywóz za granicę. Jak uzasadnisz przed komisją UNESCO, że fresk musi zostać w mieście publicznie?',
            'Korporacja wydobywa rzekomy kosmiczny artefakt z pustyni Lower Manhattan. Jesteś powołanym ekspertem — Twoja analiza pokazuje, że to replika z polimeru XXII wieku. Jak sformułujesz oficjalny raport, by naukowo zdyskredytować fałszerstwo i nie zniknąć w bagażniku następnego dnia?',
        ],
    ],
    'teologia' => [
        'nazwa' => 'Teologia', 'dziedzina' => 'Teologii', 'kolor' => '#f1e2bd',
        'zajecia' => 12, 'czesne' => 400, 'energia' => 3,
        'pytania' => [
            'Parafianin wyznaje Ci na spowiedzi, że jutro syndykat podpali kamienicę pełną rodzin. Tajemnica spowiedzi jest święta. Jak postąpisz, by uratować ludzi i nie złamać sakramentu?',
            'Młoda para prosi o ślub w Katedrze, ale pan młody jest egzekutorem gangu z krwią na rękach. Biskup każe odmówić, rodzina panny młodej grozi. Jak uzasadnisz swoją decyzję teologicznie?',
        ],
    ],
    'zdrowie' => [
        'nazwa' => 'Nauki o Zdrowiu', 'dziedzina' => 'Nauk o Zdrowiu', 'kolor' => '#ff7d86',
        'zajecia' => 12, 'czesne' => 600, 'energia' => 4,
        'pytania' => [
            'Karambol na moście Brooklyn: siedmiu rannych, dwoje ratowników, jedna karetka. Jak przeprowadzisz triaż i kogo zabierasz pierwszego?',
            'Pacjent po amputacji nie przyjmuje cyber-protezy — organizm ją odrzuca, a psychika się łamie. Jak zaplanujesz rehabilitację na najbliższe sześć tygodni?',
        ],
    ],
    'biologia' => [
        'nazwa' => 'Biologia i Nauki Przyrodnicze', 'dziedzina' => 'Biologii', 'kolor' => '#9be35a',
        'zajecia' => 12, 'czesne' => 500, 'energia' => 4,
        'pytania' => [
            'Z kanałów pod Queens wyrasta grzyb, który rozkłada beton w tempie metra na dobę. Jak zidentyfikujesz organizm i zatrzymasz go, nie trując całej dzielnicy?',
            'Korporacja chce opatentować zmodyfikowane drzewo oczyszczające smog, ale zasiewa nim park publiczny bez zgody mieszkańców. Jakie ryzyka ekologiczne przedstawisz komisji?',
        ],
    ],
    'architektura' => [
        'nazwa' => 'Architektura i Budownictwo', 'dziedzina' => 'Architektury', 'kolor' => '#e8a45e',
        'zajecia' => 18, 'czesne' => 700, 'energia' => 5,
        'pytania' => [
            'Syndykat zamawia wieżowiec z ukrytym piętrem, którego nie ma w żadnych planach. Jak rozplanujesz konstrukcję, instalacje i ewakuację, by piętro przetrwało inspekcję budowlaną?',
            'Po trzęsieniu ziemi dzielnica Red Hook osiada w wodzie. Masz budżet na jedno rozwiązanie dla dwóch tysięcy mieszkańców. Co zaprojektujesz i dlaczego?',
        ],
    ],
    'awf' => [
        'nazwa' => 'Wychowanie Fizyczne (AWF)', 'dziedzina' => 'Wychowania Fizycznego', 'kolor' => '#ff8f3d',
        'zajecia' => 10, 'czesne' => 350, 'energia' => 6,
        'pytania' => [
            'Twój zawodnik ma walkę za tydzień, a lekarz syndykatu proponuje stymulanty nie do wykrycia. Jak rozpiszesz ostatni tydzień przygotowań, żeby wygrać czysto?',
            'Grupa dzieciaków z ulicy trafia do Twojej sekcji zamiast do gangu. Jak ułożysz trening, który utrzyma ich z dala od zaułków?',
        ],
    ],
    'teatr' => [
        'nazwa' => 'Akademia Teatralna i Muzyczna', 'dziedzina' => 'Sztuk Scenicznych', 'kolor' => '#b98cff',
        'zajecia' => 12, 'czesne' => 400, 'energia' => 4,
        'pytania' => [
            'Premiera na Broadwayu za godzinę, a odtwórca głównej roli leży pobity w garderobie. Masz dublera bez prób. Jak przygotujesz go i scenę do spektaklu?',
            'Mecenas teatru — boss mafii — żąda, by sztuka go gloryfikowała. Jak zmienisz scenariusz, by zadowolić jego i nie zdradzić widzów?',
        ],
    ],
    'media' => [
        'nazwa' => 'Dziennikarstwo i Media', 'dziedzina' => 'Dziennikarstwa', 'kolor' => '#f5d76e',
        'zajecia' => 10, 'czesne' => 350, 'energia' => 3,
        'pytania' => [
            'Masz nagranie, na którym komisarz policji bierze łapówkę od kartelu. Jedyne źródło prosi o anonimowość i boi się o życie. Jak zweryfikujesz materiał i opublikujesz go, chroniąc informatora?',
            'Twoja redakcja należy do korporacji, którą właśnie przyłapałeś na zatruwaniu rzeki. Szef każe temat zamknąć. Co robisz?',
        ],
    ],
    'psychologia' => [
        'nazwa' => 'Psychologia', 'dziedzina' => 'Psychologii', 'kolor' => '#86adff',
        'zajecia' => 14, 'czesne' => 600, 'energia' => 4,
        'pytania' => [
            'Pacjentka twierdzi, że implant słuchowy szepcze jej rozkazy. Inżynier mówi, że urządzenie działa poprawnie. Jak przeprowadzisz diagnozę różnicową?',
            'Negocjujesz z mężczyzną, który przetrzymuje zakładników w banku na 5th Ave. Masz dziesięć minut. Jak zbudujesz kontakt i doprowadzisz do kapitulacji?',
        ],
    ],
    'lotnictwo' => [
        'nazwa' => 'Lotnictwo Cywilne', 'dziedzina' => 'Lotnictwa', 'kolor' => '#35c4ff',
        'zajecia' => 16, 'czesne' => 1200, 'energia' => 5,
        'stary_tytul' => 'Pilot Liniowy',
        'pytania' => [
            'Lecisz rejsem transatlantyckim z VIP-ami syndykatu na pokładzie. Radar wskazuje eskortę trzech korporacyjnych dronów przechwytujących na kursie kolizyjnym. Masz 90 sekund do strefy kontroli ruchu. Jaką taktykę ewazyjną zastosujesz, by chronić pasażerów, nie łamiąc procedur bezpieczeństwa ICAO?',
            'Awaria silnika numer 2 nad zatoką Lower Manhattan Bay. Procedura AW-733 nakazuje powrót na lotnisko, ale masz uzasadnione podejrzenie, że to sabotaż i lądowanie grozi śmiercią załogi. Jak podejmiesz decyzję o wodowaniu awaryjnym i którego kanału radiowego użyjesz, by ominąć skompromitowaną wieżę kontroli lotów?',
        ],
    ],
];

/** Liczba zajęć na danym stopniu (1–3), razem z egzaminem. */
function uni_wymagane_zajecia(string $kid, int $stopien): int {
    global $UNI_KIERUNKI, $UNI_STOPNIE;
    return (int)round($UNI_KIERUNKI[$kid]['zajecia'] * $UNI_STOPNIE[$stopien]['zajecia']);
}
function uni_czesne(string $kid, int $stopien): int {
    global $UNI_KIERUNKI, $UNI_STOPNIE;
    return (int)$UNI_KIERUNKI[$kid]['czesne'] * $UNI_STOPNIE[$stopien]['czesne'];
}
function uni_tytul(string $kid, int $stopien): string {
    global $UNI_KIERUNKI, $UNI_STOPNIE;
    return $UNI_STOPNIE[$stopien]['nazwa'] . ' ' . $UNI_KIERUNKI[$kid]['dziedzina'];
}

/** [kierunek => ukończone stopnie 0–3, ...] dla gracza. */
function uni_stopnie(mysqli $db, int $gid): array {
    static $cache = [];
    if (isset($cache[$gid])) return $cache[$gid];
    $r = [];
    foreach (db_wiersze($db, "SELECT kierunek, stopien FROM uni_postep WHERE gracz_id = ?", [$gid]) as $w) $r[$w['kierunek']] = (int)$w['stopien'];
    return $cache[$gid] = $r;
}

/** PU z Uniwersytetu: 2 za stopień z maks. 2 kierunków o najwyższych stopniach. */
function uni_pu(mysqli $db, int $gid): int {
    $s = array_values(uni_stopnie($db, $gid));
    rsort($s);
    return array_sum(array_slice($s, 0, UNI_PU_MAKS_KIERUNKOW)) * UNI_PU_ZA_STOPIEN;
}

function uni_ma_licencjat(mysqli $db, int $gid): bool { return max([0] + uni_stopnie($db, $gid)) >= 1; }

/** Stopień wymagany na danym etapie Profesji: 1 → Licencjat, 2–3 → Magister, 4 → Doktor. */
function uni_stopien_dla_etapu(int $etap): int { return $etap >= 4 ? 3 : ($etap >= 2 ? 2 : 1); }

/**
 * Czy gracz spełnia wymóg studiów Profesji na danym etapie.
 * Zwraca [ok, opis] — opis np. "Magister Prawa" albo null, gdy Profesja nie wymaga studiów na tym etapie.
 */
function uni_wymog_zawodu(mysqli $db, int $gid, array $zawod, int $etap = 1): array {
    global $UNI_KIERUNKI;
    $kid = $zawod['wymagany_kierunek'] ?? null;
    if (!$kid || !isset($UNI_KIERUNKI[$kid])) return [true, null];
    if ($etap < (int)($zawod['kierunek_od_etapu'] ?? 1)) return [true, null];
    $potrzebny = uni_stopien_dla_etapu($etap);
    $ma = uni_stopnie($db, $gid)[$kid] ?? 0;
    return [$ma >= $potrzebny, uni_tytul($kid, $potrzebny)];
}

/** Najwyższy tytuł gracza do wyświetlenia (np. na karcie i w profilu). */
function uni_tytul_glowny(mysqli $db, int $gid): ?string {
    $s = uni_stopnie($db, $gid);
    if (!$s) return null;
    arsort($s);
    $kid = array_key_first($s);
    return $s[$kid] > 0 ? uni_tytul($kid, $s[$kid]) : null;
}
