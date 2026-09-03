<?php
require_once "db.php";
require_once __DIR__ . '/../config/pochodzenia.php';
require_once __DIR__ . '/../config/zawody.php';
require_once __DIR__ . '/../config/rp_helpers.php';

$id_gracza = $_SESSION['id_gracza'];

// ═══════════════════════════════════════════════════════════════
// SŁOWNIKI CECH — struktura: [opis (krótki fabularny), wplyw (mechaniczny/sesyjny)]
// Grupowanie pomaga graczowi szybciej znaleźć to, czego szuka.
// ═══════════════════════════════════════════════════════════════

$wszystkie_zalety_def = [

    // ─── FIZYCZNE / KONDYCYJNE ──────────────────────────────
    "Żelazne Płuca" => [
        "grupa" => "Fizyczne",
        "opis"  => "Masz świetną kondycję i możesz biec bez zadyszki.",
        "wplyw" => "Bonus do testów Sprintu, Ucieczki, Pływania i długotrwałego wysiłku. Odporność na smog, gaz łzawiący i dym. W pościgach wytrzymasz dłużej niż przeciętny przeciwnik."
    ],
    "Krzepki" => [
        "grupa" => "Fizyczne",
        "opis"  => "Jesteś postawny, silny i trudno Cię przewrócić.",
        "wplyw" => "Bonus do Siły Mięśni, Walki Wręcz, podnoszenia ciężarów i testów na utrzymanie równowagi. Mocny cios w zwarciu. Imponująca postura bywa pomocna w zastraszaniu."
    ],
    "Szybkie Nogi" => [
        "grupa" => "Fizyczne",
        "opis"  => "Należysz do najszybszych biegaczy w mieście.",
        "wplyw" => "Bonus do ucieczki i pościgu. Jeśli trzeba zwiać przed gliniarzami, syndykatem albo stadem dobermanów — masz największe szanse ujść z życiem."
    ],
    "Kocia Zwinność" => [
        "grupa" => "Fizyczne",
        "opis"  => "Wspinasz się po murach, lądujesz na nogach, omijasz ciosy.",
        "wplyw" => "Bonus do Atletyki, wspinaczki, akrobatyki, unikania upadków i ataków. Przydaje się w dachowych pościgach, zakradaniu i skokach między piętrami."
    ],
    "Mocny Chwyt" => [
        "grupa" => "Fizyczne",
        "opis"  => "Trudno wyrwać Ci cokolwiek z ręki.",
        "wplyw" => "Bonus przy ściąganiu po linie, utrzymaniu broni w szarpaninie, obezwładnianiu. Zawiśniesz na krawędzi okna dłużej niż inni."
    ],
    "Atletyczne Ciało" => [
        "grupa" => "Fizyczne",
        "opis"  => "Lata treningu — giętkie, wytrenowane, estetyczne ciało.",
        "wplyw" => "Bonus do wszystkich sprawdzianów sportowych (bieg, skok, pływanie). Bywasz brany za profesjonalnego sportowca — otwiera to niektóre drzwi."
    ],
    "Szybka Regeneracja" => [
        "grupa" => "Fizyczne",
        "opis"  => "Twoje rany goją się dwa razy szybciej niż u innych.",
        "wplyw" => "Po sesji z obrażeniami wracasz do formy w krótszym czasie. Siniaki schodzą w dzień, złamania zrastają się szybciej niż lekarze przewidują."
    ],
    "Zdrowie Jak Żelazo" => [
        "grupa" => "Fizyczne",
        "opis"  => "Rzadko chorujesz. Epidemie i wirusy Cię omijają.",
        "wplyw" => "Odporność na pospolite choroby, gorączkę, trucizny łagodne. Pracujesz w slumsach i nie łapiesz tego, na co chorują inni."
    ],
    "Żelazna Wątroba" => [
        "grupa" => "Fizyczne",
        "opis"  => "Alkohol, narkotyki i toksyny działają na Ciebie słabiej.",
        "wplyw" => "W kasynach i barach nie tracisz głowy. Trudniej Cię otruć lub uśpić. Bonus do testów oporu na używki i serum prawdy."
    ],
    "Niewrażliwy na Ból" => [
        "grupa" => "Fizyczne",
        "opis"  => "Znosisz rany, które zwaliłyby z nóg przeciętniaka.",
        "wplyw" => "Kontynuujesz walkę po trafieniu, które innego by unieruchomiło. Bonus do testów wytrzymałości w torturach, interogacjach i kontuzjach."
    ],
    "Drobna Postura" => [
        "grupa" => "Fizyczne",
        "opis"  => "Jesteś drobna/drobny — przeciśniesz się tam, gdzie inni utkną.",
        "wplyw" => "Bonus do ukrywania się, przemykania przez szczeliny, schowania się w małej przestrzeni. Trudniej Cię zauważyć w tłumie."
    ],

    // ─── ZMYSŁOWE ───────────────────────────────────────────
    "Sokoli Wzrok" => [
        "grupa" => "Zmysły",
        "opis"  => "Zauważasz detale, które umykają innym.",
        "wplyw" => "Bonus do Obserwacji, wykrywania pułapek, śledzenia, czytania z ruchu warg z dystansu. Widzisz szczegóły dokumentów, które inni muszą przybliżać."
    ],
    "Nocny Marek" => [
        "grupa" => "Zmysły",
        "opis"  => "Twoje oczy doskonale przystosowały się do mroku.",
        "wplyw" => "Bonus do działań nocnych i w piwnicach. Operujesz w ciemności, gdzie inni potrzebują latarki. Noc to Twój żywioł."
    ],
    "Wyczulony Węch" => [
        "grupa" => "Zmysły",
        "opis"  => "Wyczuwasz dym, krew, trucizny, zapachy ludzi.",
        "wplyw" => "Bonus do tropienia, wykrywania trucizn w jedzeniu, rozpoznawania obecności kogoś. Wyczujesz dym kilka chwil przed alarmem."
    ],
    "Świetna Pamięć Słuchowa" => [
        "grupa" => "Zmysły",
        "opis"  => "Zapamiętujesz głos, melodię, akcent po jednym usłyszeniu.",
        "wplyw" => "Rozpoznasz kogoś po głosie nawet po latach. Bonus do rozmów telefonicznych, podsłuchu, identyfikacji w ciemności. Wyczujesz fałszywy akcent."
    ],
    "Szósty Zmysł" => [
        "grupa" => "Zmysły",
        "opis"  => "Masz przeczucie, gdy ktoś planuje zdradę lub atak.",
        "wplyw" => "MG sygnalizuje Ci subtelne zagrożenie, zanim uderzy. Bonus do inicjatywy w zasadzkach i testów przeczuwania intencji rozmówcy."
    ],

    // ─── UMYSŁOWE ───────────────────────────────────────────
    "Bystrzak" => [
        "grupa" => "Umysł",
        "opis"  => "Szybko kojarzysz fakty i rozwiązujesz zagadki.",
        "wplyw" => "Bonus do Śledztwa, łamania zagadek, kojarzenia faktów. Widzisz związki, których inni nie dostrzegają. W sesjach kryminalnych jesteś nieoceniona."
    ],
    "Genialny Umysł" => [
        "grupa" => "Umysł",
        "opis"  => "Przyswajasz wiedzę w mgnieniu oka.",
        "wplyw" => "Uczysz się języków, systemów, schematów znacznie szybciej. Bonus do wszystkich testów wymagających wiedzy akademickiej i teoretycznej."
    ],
    "Fotograficzna Pamięć" => [
        "grupa" => "Umysł",
        "opis"  => "Zapamiętujesz kody, mapy i twarze po jednym spojrzeniu.",
        "wplyw" => "Nie potrzebujesz notesu. Rozpoznajesz twarze z tłumu nawet po latach. Bonus przy odtwarzaniu scen z pamięci, czytaniu z ruchu warg."
    ],
    "Analityczny Umysł" => [
        "grupa" => "Umysł",
        "opis"  => "Widzisz wzorce w chaosie. Łamiesz dane na kawałki.",
        "wplyw" => "Bonus do analizy danych, dedukcji, łamania szyfrów, profilowania przestępców. Z rozrzuconych faktów układasz spójną narrację."
    ],
    "Poliglota" => [
        "grupa" => "Umysł",
        "opis"  => "Mówisz płynnie wieloma językami.",
        "wplyw" => "Dogadasz się w każdej dzielnicy The Abyss. Bonus do testów rozmowy z obcokrajowcami, tłumaczeń, rozpoznawania akcentów."
    ],
    "Złota Rączka" => [
        "grupa" => "Umysł",
        "opis"  => "Zbudujesz prowizoryczną broń lub narzędzie ze śmieci.",
        "wplyw" => "Improwizujesz w sytuacjach kryzysowych — prowizoryczna broń, naprawa, wytrych. Bonus do Inżynierii i Rzemiosła w terenie."
    ],

    // ─── PSYCHOLOGICZNE / NERWY ─────────────────────────────
    "Zimna Krew" => [
        "grupa" => "Nerwy",
        "opis"  => "W sytuacjach ekstremalnego stresu zachowujesz spokój.",
        "wplyw" => "Bonus do celowania, precyzyjnych akcji i decyzji pod presją. Gdy inni panikują, Ty analizujesz. Lepiej blefujesz u pokerowego stołu i w przesłuchaniach."
    ],
    "Refleks Szachisty" => [
        "grupa" => "Nerwy",
        "opis"  => "Zawsze działasz ułamek sekundy szybciej niż inni.",
        "wplyw" => "Bonus do inicjatywy w walce i wszystkich testów na szybkość reakcji. Pierwszy wykonujesz uniki, pierwszy sięgasz po broń."
    ],
    "Wytrenowane Odruchy" => [
        "grupa" => "Nerwy",
        "opis"  => "Twoje ciało reaguje, zanim zdążysz pomyśleć.",
        "wplyw" => "Bonus do walki wręcz, parowania, kontrataków. W niespodziewanych sytuacjach (np. zasadzka) nie zamierasz — ciało działa za Ciebie."
    ],
    "Wysoka Tolerancja Stresu" => [
        "grupa" => "Nerwy",
        "opis"  => "Pod presją pracujesz tak samo dobrze jak w spokoju.",
        "wplyw" => "Niezagrożona wydajność podczas długich sesji, bombardowań, interogacji. Bonus do testów wymagających skupienia w chaosie (celowanie w pościgu, hackowanie pod ostrzałem)."
    ],
    "Twarda Skóra" => [
        "grupa" => "Nerwy",
        "opis"  => "Obelgi, kpiny i oskarżenia spływają po Tobie.",
        "wplyw" => "Odporność na zastraszanie, prowokacje i manipulacje emocjonalne. W negocjacjach nie dasz się wyprowadzić z równowagi."
    ],
    "Pogodny Sen" => [
        "grupa" => "Nerwy",
        "opis"  => "Śpisz głęboko i szybko się regenerujesz.",
        "wplyw" => "Pełna regeneracja HP i energii w krótszej drzemce. Bonus do odporności na pozbawianie snu jako formę tortur."
    ],
    "Wrodzony Optymizm" => [
        "grupa" => "Nerwy",
        "opis"  => "W każdym upadku widzisz szansę.",
        "wplyw" => "Odporność na depresję i zwątpienie po fabularnych porażkach. Trzymasz grupę na duchu — bonus do testów motywowania innych."
    ],

    // ─── SPOŁECZNE / CHARAKTER ──────────────────────────────
    "Charyzmatyczny" => [
        "grupa" => "Społeczne",
        "opis"  => "Jesteś urodzonym liderem i świetnym negocjatorem.",
        "wplyw" => "Bonus do Perswazji i Negocjacji. Ludzie naturalnie słuchają, gdy mówisz. Przewodzisz ekipie, nawet jeśli formalnie nie jesteś szefem."
    ],
    "Srebrny Język" => [
        "grupa" => "Społeczne",
        "opis"  => "Potrafisz wyłgać się z niemal każdej sytuacji.",
        "wplyw" => "Bonus do Kłamania, Oszustwa i Wymówek. Kupisz sobie czas pustymi słowami, gdy inni nie umieją ułożyć zdania."
    ],
    "Mistrz Blefu" => [
        "grupa" => "Społeczne",
        "opis"  => "Twoja twarz nie zdradza żadnych emocji.",
        "wplyw" => "Poker face dosłownie. Bonus do blefu, zatajenia prawdy, oszustwa przy przesłuchaniach. Graczy w kasynie mrozisz spojrzeniem."
    ],
    "Empatyczny" => [
        "grupa" => "Społeczne",
        "opis"  => "Czytasz ludzi jak otwartą księgę.",
        "wplyw" => "Bonus do Rozczytywania Emocji, wykrywania kłamstw, terapii. Wiesz, kiedy ktoś kryje ból lub nienawiść. Świetny negocjator i przyjaciel."
    ],
    "Uliczny Spryt" => [
        "grupa" => "Społeczne",
        "opis"  => "Znasz niepisane prawo ulicy i wiesz, z kim nie zadzierać.",
        "wplyw" => "Bonus do testów przetrwania w slumsach, targowania się z szumowinami, rozpoznawania niebezpiecznych dzielnic. Nie dasz się oszukać pod sklepem."
    ],
    "Zjawiskowa Uroda" => [
        "grupa" => "Społeczne",
        "opis"  => "Twarz jak z żurnala. Ludzie zatrzymują się na Twój widok.",
        "wplyw" => "Bonus do uwodzenia, pierwszego wrażenia, pozyskiwania uwagi. Minus: rozpoznają Cię łatwiej, gdy chcesz być niewidoczna/niewidoczny."
    ],
    "Bezkompromisowy" => [
        "grupa" => "Społeczne",
        "opis"  => "Nigdy nie łamiesz danego słowa.",
        "wplyw" => "Ludzie wiedzą, że można Ci zaufać. Bonus do pozyskiwania informatorów, pozycji w syndykacie, reputacji u władz. Trudno Cię przekupić."
    ],
    "Znajomości w Półświatku" => [
        "grupa" => "Społeczne",
        "opis"  => "Masz kontakty w mrocznych zaułkach miasta.",
        "wplyw" => "W każdej dzielnicy znajdziesz kogoś, kto załatwi sprawę. Bonus do czarnego rynku, kupowania informacji, dostępu do ukrytych lokali."
    ],
    "Bogate Dziedzictwo" => [
        "grupa" => "Społeczne",
        "opis"  => "Pochodzisz z majętnego rodu.",
        "wplyw" => "Bonus do pierwszego wrażenia w elitarnych kręgach, wejścia do restauracji i klubów bez zaproszenia. Nazwisko otwiera salony. Nie daje to kasy — daje DOSTĘP."
    ],

    // ─── SPECYFICZNE ────────────────────────────────────────
    "Oburęczny" => [
        "grupa" => "Specyficzne",
        "opis"  => "Posługujesz się obiema rękami z równą precyzją.",
        "wplyw" => "Możesz walczyć dwoma broniami naraz, pisać jedną ręką i trzymać broń w drugiej. Żadna ręka nie jest 'słabsza'."
    ],
    "Lekka Ręka" => [
        "grupa" => "Specyficzne",
        "opis"  => "Potrafisz niepostrzeżenie coś wsunąć lub wyciągnąć.",
        "wplyw" => "Bonus do kieszonkowania, ukradkowego wręczenia przedmiotu, oszustw karcianych. W kasynie bywasz problemem."
    ],
];

$wszystkie_wady_def = [

    // ─── FIZYCZNE — MAJOR ───────────────────────────────────
    "Brak Kończyny" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Brakuje Ci ręki lub nogi.",
        "wplyw" => "Ogromna kara do większości testów fizycznych związanych z utratą kończyny. Bez ręki: walka wręcz, prace precyzyjne. Bez nogi: bieg, wspinaczka, walka w pozycji stojącej."
    ],
    "Całkowita Ślepota" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Nie widzisz. Musisz polegać na innych zmysłach.",
        "wplyw" => "Drastyczna kara do testów wymagających wzroku. Bonus do zmysłu słuchu i dotyku. Typowo postaci niewidome rozwijają specyficzne skille zastępcze — laska, pies, echolokacja."
    ],
    "Głuchy" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Żyjesz w ciszy. Łatwo zajść Cię od tyłu.",
        "wplyw" => "Kara do wszystkich testów opartych na słuchu — zasadzka, rozmowy bez kontaktu wzrokowego, podsłuchiwanie. Niedostępność radia, telefonu."
    ],
    "Jednooki" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Brak oka zaburza Twoją percepcję głębi.",
        "wplyw" => "Kara do testów celowania na dystans, parkowania pojazdów, oceny odległości. Ślepa strona — łatwo Cię zaskoczyć z niewidzialnej flanki."
    ],
    "Oszpecony" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Twoja twarz nosi ślady drastycznych przejść.",
        "wplyw" => "Kara do pierwszego wrażenia, uwodzenia, wystąpień publicznych. Bonus do zastraszania. Łatwo Cię rozpoznać, gdy chcesz być niewidoczna/niewidoczny."
    ],
    "Utykający" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Stara kontuzja nogi drastycznie spowalnia Cię.",
        "wplyw" => "Kara do biegu, ucieczki, wspinaczki, wszystkich działań z nogi. Po długim marszu ból się wzmaga."
    ],
    "Hemofiliak" => [
        "grupa" => "Fizyczne poważne",
        "opis"  => "Twoja krew słabo krzepnie. Każda rana to zagrożenie.",
        "wplyw" => "Nawet drobne skaleczenia nie przestają krwawić. W walce tracisz HP dłużej po trafieniu. Konieczny stały dostęp do apteczki."
    ],

    // ─── FIZYCZNE — CHOROBY PRZEWLEKŁE ──────────────────────
    "Astma" => [
        "grupa" => "Choroby",
        "opis"  => "Smog, dym i wysiłek mocno Ci szkodzą.",
        "wplyw" => "Kara do wysiłku długotrwałego, sprintu, walki w zadymionym pomieszczeniu. Bez inhalatora atak astmy wyłącza Cię z akcji."
    ],
    "Choroba Serca" => [
        "grupa" => "Choroby",
        "opis"  => "Twoje serce nie wytrzymuje nadmiernego wysiłku.",
        "wplyw" => "Kara do długich pościgów, walk, ekstremalnego stresu. Za silna emocja może wywołać zawał (MG decyduje). Wymagasz leków."
    ],
    "Cukrzyca" => [
        "grupa" => "Choroby",
        "opis"  => "Potrzebujesz regularnych posiłków i leków.",
        "wplyw" => "Jeśli od dawna nie jadłaś/nie brałaś insuliny — tracisz siły, wzrok się rozmywa. W długich sesjach (więzienie, zakładnik) to realny problem."
    ],
    "Epilepsja" => [
        "grupa" => "Choroby",
        "opis"  => "Ataki padaczki pod wpływem błysków lub silnego stresu.",
        "wplyw" => "Migające światła (neon, stroboskop, wybuchy) mogą wywołać atak — wyłączenie z akcji na kilka tur. Unikasz dyskotek i strzelanin w ciemności."
    ],
    "Migreny" => [
        "grupa" => "Choroby",
        "opis"  => "Silne bóle głowy potrafią Cię wyłączyć na godziny.",
        "wplyw" => "Po silnym stresie lub za mało snu — rzut na migrenę. Sukces: jesteś do niczego, kara do wszystkich testów. Przydaje się ciemny pokój."
    ],
    "Alergie" => [
        "grupa" => "Choroby",
        "opis"  => "Kurz, pyłki, sierść lub niektóre chemikalia wywołują reakcje.",
        "wplyw" => "W określonych środowiskach (opuszczone magazyny, stajnie, laboratoria) dostajesz kary do zmysłów — kichanie, łzawienie, duszności."
    ],
    "Niska Odporność" => [
        "grupa" => "Choroby",
        "opis"  => "Łatwo się przeziębiasz i zarażasz.",
        "wplyw" => "Po kontakcie z chorymi, brudną wodą, szczurami — rzut na chorobę. Wyjazd do slumsów bywa ryzykowny. Stale chodzisz z pakietem leków."
    ],
    "Bezsenność" => [
        "grupa" => "Choroby",
        "opis"  => "Rzadko sypiasz normalnie. Zawsze jesteś zmęczona/zmęczony.",
        "wplyw" => "Spowolniona regeneracja HP i energii. Kara do testów wymagających skupienia rano. Gorączkowe myśli w środku nocy."
    ],
    "Reumatyzm" => [
        "grupa" => "Choroby",
        "opis"  => "Stawy bolą Cię, zwłaszcza w chłodne i wilgotne dni.",
        "wplyw" => "W zimnie i deszczu — kara do wszystkich testów fizycznych. Zimą ruchy są sztywniejsze, walka trudniejsza."
    ],
    "Wolne Gojenie" => [
        "grupa" => "Choroby",
        "opis"  => "Rany leczą się u Ciebie znacznie dłużej niż u innych.",
        "wplyw" => "Po sesji z obrażeniami wracasz do formy dłużej. Blizny zostają na zawsze. Drobne skaleczenie może stać się ropiejącą raną."
    ],

    // ─── FIZYCZNE — ZMYSŁY ──────────────────────────────────
    "Krótkowidz" => [
        "grupa" => "Zmysły osłabione",
        "opis"  => "Bez okularów jesteś praktycznie bezradna/bezradny na dystans.",
        "wplyw" => "Kara do celowania, obserwacji z dystansu, czytania znaków. Zgubione/stłuczone okulary to realny problem fabularny."
    ],
    "Daltonizm" => [
        "grupa" => "Zmysły osłabione",
        "opis"  => "Nie rozróżniasz niektórych kolorów (zwykle zieleń/czerwień).",
        "wplyw" => "Kara do testów wymagających rozróżnienia kolorów — kable, sygnały, dokumenty, mundury. Łatwo przetniesz zły przewód w bombie."
    ],
    "Niedosłuch" => [
        "grupa" => "Zmysły osłabione",
        "opis"  => "Słyszysz tylko częściowo.",
        "wplyw" => "Kara do testów słuchu — cichy szept, odległe odgłosy, rozmowy w tłumie. Aparat słuchowy pomaga, ale bywa awaryjny."
    ],
    "Słabeusz" => [
        "grupa" => "Fizyczne",
        "opis"  => "Brakuje Ci tężyzny fizycznej.",
        "wplyw" => "Kara do Siły, walki wręcz, podnoszenia ciężarów. W zwarciu przegrywasz z przeciętniakiem. Nie wyważysz drzwi ani nie wdrapiesz się po linie."
    ],
    "Jąkanie" => [
        "grupa" => "Nerwowe",
        "opis"  => "W stresie blokują Ci się słowa.",
        "wplyw" => "Kara do perswazji, negocjacji, wystąpień publicznych w momentach napięcia. Bloku telefonicznego — dłuższa rozmowa to kara."
    ],

    // ─── PSYCHICZNE — ZABURZENIA ────────────────────────────
    "Depresja" => [
        "grupa" => "Psychiczne",
        "opis"  => "Okresy silnej apatii i beznadziei.",
        "wplyw" => "Po fabularnych porażkach — rzut na epizod depresyjny. W trakcie: kara do inicjatywy, motywacji, testów społecznych. Łatwiej Cię złamać emocjonalnie."
    ],
    "Lęki Napadowe" => [
        "grupa" => "Psychiczne",
        "opis"  => "Nagłe ataki paniki — szybkie serce, duszność, zawroty.",
        "wplyw" => "W sytuacjach silnego stresu — rzut na atak paniki. Sukces atak: wyłączenie z akcji na kilka tur. Wolisz znane miejsca i rutynę."
    ],
    "Trauma Pourazowa" => [
        "grupa" => "Psychiczne",
        "opis"  => "Powracające wspomnienia dawnej traumy.",
        "wplyw" => "Określone bodźce (konkretny zapach, dźwięk, scena — ustalane z MG) wywołują flashback. Podczas flashbacku jesteś sparaliżowana/sparaliżowany lub reagujesz irracjonalnie."
    ],
    "Natręctwa" => [
        "grupa" => "Psychiczne",
        "opis"  => "Musisz wykonywać rytuały — liczenie, mycie rąk, sprawdzanie drzwi.",
        "wplyw" => "Gdy nie wykonasz rytuału — kara do koncentracji. W pogoni czasem zatrzymujesz się, bo 'musisz coś sprawdzić'. Upośledza planowanie nagłych akcji."
    ],
    "Rozproszenie Uwagi" => [
        "grupa" => "Psychiczne",
        "opis"  => "Trudno Ci utrzymać skupienie na jednym zadaniu.",
        "wplyw" => "Kara do długich testów (analizy, obserwacji z ukrycia). Przerywasz rozmowy, nie kończysz zdań. W środowisku pełnym bodźców gubisz wątek."
    ],
    "Paranoik" => [
        "grupa" => "Psychiczne",
        "opis"  => "Wszędzie widzisz spiski i nie ufasz nikomu.",
        "wplyw" => "Kara do współpracy grupowej, przyjmowania pomocy. Bonus do wykrywania prawdziwych spisków (czasem masz rację!). Trudno Cię przekupić — ale i trudno zaprzyjaźnić."
    ],

    // ─── PSYCHICZNE — CHARAKTER ─────────────────────────────
    "Furiat" => [
        "grupa" => "Charakter",
        "opis"  => "Bardzo szybko wyprowadzają Cię z równowagi.",
        "wplyw" => "Przy prowokacji — rzut na opanowanie lub atak w afekcie. W negocjacjach łatwo Cię wymanipulować. W walce: bonus do obrażeń, kara do taktyki."
    ],
    "Tchórz" => [
        "grupa" => "Charakter",
        "opis"  => "W obliczu silniejszego wroga uciekasz.",
        "wplyw" => "Przy starciu z przeważającym przeciwnikiem — rzut na panikę. Porażka: ucieczka, nawet jeśli grozi to sojusznikom. Kara do testów odwagi."
    ],
    "Naiwny" => [
        "grupa" => "Charakter",
        "opis"  => "Łatwo nabierasz się na kłamstwa i tanie sztuczki.",
        "wplyw" => "Kara do wykrywania kłamstw, oszustw, manipulacji. Sprzedawcy używanych samochodów Cię kochają. Ufasz pierwszej historii, którą usłyszysz."
    ],
    "Odludek" => [
        "grupa" => "Charakter",
        "opis"  => "Nienawidzisz tłumów i pracy w grupie.",
        "wplyw" => "Kara do działań grupowych, zebrań, tłumów. W klubach i koncertach — stres. Lepiej działasz solo; w drużynie gubisz koordynację."
    ],
    "Gadatliwy" => [
        "grupa" => "Charakter",
        "opis"  => "Ogromny problem z utrzymaniem tajemnicy.",
        "wplyw" => "Przy pytaniu o sekret — rzut na dyskrecję. Porażka: wygadujesz więcej, niż powinnaś/powinieneś. Przesłuchanie Cię to raj dla śledczych."
    ],
    "Leniwy" => [
        "grupa" => "Charakter",
        "opis"  => "Zawsze szukasz drogi na skróty.",
        "wplyw" => "Kara do testów wymagających dokładności, wytrwałości, treningu. Pomijasz ważne kroki, a potem masz problem. Bonus do improwizacji (bo musisz)."
    ],
    "Impulsywność" => [
        "grupa" => "Charakter",
        "opis"  => "Działasz zanim pomyślisz.",
        "wplyw" => "W krytycznych momentach — rzut, czy nie zrobisz czegoś głupiego. Strzelisz bez namysłu, rzucisz się do ucieczki zanim ustalisz plan. Bywa, że paradoksalnie ratuje Ci to życie."
    ],
    "Pycha" => [
        "grupa" => "Charakter",
        "opis"  => "Nie potrafisz przyznać się do błędu.",
        "wplyw" => "Kara do przyjmowania rad, uczenia się na błędach. Prowokujesz wrogów niepotrzebnie. Łatwo Cię wyprowadzić z równowagi prawidłowym oskarżeniem."
    ],
    "Skąpiec" => [
        "grupa" => "Charakter",
        "opis"  => "Nad wszystko cenisz pieniądze. Wydajesz niechętnie.",
        "wplyw" => "Przy decyzji o wydatku — rzut na skąpstwo. Porażka: wybierasz tańszą (gorszą) opcję. Łatwo Cię przekupić. Trudno Ci zaufać ludziom, którzy nie chcą pieniędzy."
    ],
    "Mizantrop" => [
        "grupa" => "Charakter",
        "opis"  => "Ludzie obrzydzają Cię swoim istnieniem.",
        "wplyw" => "Kara do interakcji społecznych. Nie pomagasz bez konkretnej motywacji. W drużynie trudno Cię znieść — ale paradoksalnie: nie dajesz się oszukać sentymentom."
    ],
    "Brak Empatii" => [
        "grupa" => "Charakter",
        "opis"  => "Nie rozumiesz emocji innych.",
        "wplyw" => "Kara do rozczytywania uczuć, pocieszania, terapii. Bonus do negocjacji pod presją (obojętność). W relacjach bliskich — ciągłe nieporozumienia."
    ],

    // ─── PSYCHICZNE — FOBIE ─────────────────────────────────
    "Klaustrofobia" => [
        "grupa" => "Fobie",
        "opis"  => "Panika w ciasnych zamkniętych pomieszczeniach.",
        "wplyw" => "W windach, tunelach, piwnicach, bagażnikach — rzut na panikę. Sukces: przyspieszone serce, kary do testów. Krytyczna porażka: paraliż, ucieczka."
    ],
    "Lęk Wysokości" => [
        "grupa" => "Fobie",
        "opis"  => "Wysokie miejsca wywołują u Ciebie zawroty głowy.",
        "wplyw" => "Na wysokich piętrach, dachach, balkonach — kara do testów równowagi i precyzji. Krawędź widoczna z dołu paraliżuje. Unikasz drapaczy chmur."
    ],
    "Lęk Tłumu" => [
        "grupa" => "Fobie",
        "opis"  => "Przerażają Cię otwarte przestrzenie i duże skupiska ludzi.",
        "wplyw" => "W centrach handlowych, stadionach, demonstracjach — kara do wszystkich testów. Wolisz skrytki, wąskie uliczki, znane trasy."
    ],

    // ─── NAŁOGI ─────────────────────────────────────────────
    "Nałogowiec" => [
        "grupa" => "Nałogi",
        "opis"  => "Tracisz skupienie, gdy nie dostaniesz swojej używki.",
        "wplyw" => "Po dłuższym czasie bez substancji — kara do koncentracji, agresji. Może wywołać sesję poszukiwania dostawcy w najgorszym momencie. Łatwo Cię przekupić działką."
    ],
    "Hazardzista" => [
        "grupa" => "Nałogi",
        "opis"  => "Kasyno to Twoje drugie imię. Nie możesz się oprzeć zakładom.",
        "wplyw" => "Przy okazji hazardu — rzut na powściągliwość. Porażka: grasz, nawet gdy to głupie. Znasz kasyna i szulerkę — to czasem bonus, czasem pułapka."
    ],
    "Kleptomania" => [
        "grupa" => "Nałogi",
        "opis"  => "Kradniesz kompulsywnie, nawet bezsensowne rzeczy.",
        "wplyw" => "Przy widoku atrakcyjnego drobiazgu — rzut na opanowanie. Porażka: zabierasz, choć nie potrzebujesz. Ryzyko wpadki w najgorszym momencie."
    ],

    // ─── SPOŁECZNE / INNE ───────────────────────────────────
    "Zła Reputacja" => [
        "grupa" => "Społeczne",
        "opis"  => "Twoja twarz jest znana jako szumowiny.",
        "wplyw" => "Kara do pierwszego wrażenia, testów oficjalnych (banki, urząd), pracy w kręgach elity. W slumsach i syndykacie — czasem bonus."
    ],
    "Pechowiec" => [
        "grupa" => "Specyficzne",
        "opis"  => "Los często krzyżuje Ci plany w najgorszym momencie.",
        "wplyw" => "MG może raz na sesję wymusić rzut z karą 'za pecha' w kluczowym momencie. Ekwipunek bywa zawodny, rzuty krytyczne porażki — częstsze."
    ],
    "Ociężały Umysł" => [
        "grupa" => "Umysłowe",
        "opis"  => "Myślenie analityczne to dla Ciebie katorga.",
        "wplyw" => "Kara do wszystkich testów intelektualnych — zagadki, łamigłówki, dedukcja, akademicka wiedza. Wolniej przetwarzasz informacje."
    ],
    "Dysleksja" => [
        "grupa" => "Umysłowe",
        "opis"  => "Czytanie i pisanie to dla Ciebie wyzwanie.",
        "wplyw" => "Kara do testów wymagających czytania dokumentów pod presją czasu, pisania raportów. W sesjach śledczych to ogromne utrudnienie. Nie przeszkadza w mowie."
    ],
    "Hipochondryk" => [
        "grupa" => "Psychiczne",
        "opis"  => "Każdy ból kojarzy Ci się ze śmiertelną chorobą.",
        "wplyw" => "Po każdym zranieniu — martwisz się, szukasz lekarza. Kara do testów koncentracji po zadanych obrażeniach. Wydajesz fortunę na badania, których nie potrzebujesz."
    ],
];

// ═══════════════════════════════════════════════════════════════
// KONFLIKTY — cechy wykluczające się wzajemnie
// ═══════════════════════════════════════════════════════════════
$konflikty = [
    // Oddech i kondycja
    ['Żelazne Płuca','Astma'],
    // Wzrok
    ['Sokoli Wzrok','Krótkowidz','Całkowita Ślepota','Jednooki','Daltonizm'],
    // Siła fizyczna / kondycja
    ['Krzepki','Słabeusz'],
    ['Atletyczne Ciało','Słabeusz','Utykający'],
    // Szybkość / nogi
    ['Szybkie Nogi','Utykający','Brak Kończyny'],
    ['Kocia Zwinność','Utykający','Brak Kończyny'],
    // Umysł
    ['Genialny Umysł','Bystrzak','Ociężały Umysł'],
    ['Analityczny Umysł','Ociężały Umysł','Rozproszenie Uwagi'],
    ['Poliglota','Dysleksja'],
    ['Fotograficzna Pamięć','Ociężały Umysł'],
    // Zdrowie ogólne
    ['Zdrowie Jak Żelazo','Niska Odporność','Choroba Serca','Cukrzyca','Hemofiliak'],
    ['Szybka Regeneracja','Wolne Gojenie','Hemofiliak'],
    // Wygląd
    ['Zjawiskowa Uroda','Oszpecony'],
    // Nerwy
    ['Zimna Krew','Furiat','Tchórz','Lęki Napadowe','Trauma Pourazowa'],
    ['Wysoka Tolerancja Stresu','Lęki Napadowe','Trauma Pourazowa','Furiat'],
    ['Mistrz Blefu','Jąkanie'],
    // Sen
    ['Pogodny Sen','Bezsenność'],
    // Nastrój
    ['Wrodzony Optymizm','Depresja'],
    // Empatia i społeczne
    ['Empatyczny','Brak Empatii','Mizantrop'],
    ['Charyzmatyczny','Odludek','Jąkanie','Mizantrop'],
    ['Twarda Skóra','Lęki Napadowe'],
    // Uliczne
    ['Uliczny Spryt','Naiwny'],
    // Ręce i precyzja
    ['Oburęczny','Brak Kończyny'],
    ['Lekka Ręka','Brak Kończyny'],
    // Szybkość reakcji
    ['Refleks Szachisty','Leniwy','Rozproszenie Uwagi'],
    ['Wytrenowane Odruchy','Leniwy'],
    // Charakter społeczny
    ['Bezkompromisowy','Skąpiec'],
    // Słuch
    ['Świetna Pamięć Słuchowa','Głuchy','Niedosłuch'],
];

// Pobierz dane gracza (podstawowe — do walidacji POST)
$row = $polaczenie->query("SELECT zalety,wady,umiejetnosc_szabrowania,umiejetnosc_inzynierii,pochodzenie FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$aktualne_zalety = ($row['zalety']=='Brak'||empty($row['zalety'])) ? [] : explode(", ",$row['zalety']);
$aktualne_wady   = ($row['wady']  =='Brak'||empty($row['wady']))   ? [] : explode(", ",$row['wady']);
$skill_szab      = $row['umiejetnosc_szabrowania'] ?? 0.0;
$skill_inz       = $row['umiejetnosc_inzynierii'] ?? 0.0;
$blad_cech = ""; $blad_zawodu = "";

// Limit cech zależny od pochodzenia (Włoch: 8, inni: 7)
$poch_wstepne = pochodzenie_dane($row['pochodzenie'] ?? null);
$limit_cech = ($poch_wstepne && isset($poch_wstepne['bonusy']['limit_cech']))
    ? (int)$poch_wstepne['bonusy']['limit_cech']
    : 7;

// ── ZAPIS CECH ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['zapisz_cechy'])) {
    $nz = $_POST['zalety'] ?? [];
    $nw = $_POST['wady']   ?? [];
    $az = array_unique(array_merge($aktualne_zalety,$nz));
    $aw = array_unique(array_merge($aktualne_wady,$nw));
    $all = array_merge($az,$aw);
    if (count($az)>$limit_cech || count($aw)>$limit_cech) {
        $blad_cech = "Osiągnięto maksymalny limit $limit_cech zalet i $limit_cech wad!";
    } else {
        $ok = true;
        foreach ($konflikty as $g) {
            $f = array_intersect($g,$all);
            if (count($f)>1) { $ok=false; $blad_cech="Wykluczające się cechy: ".implode(" oraz ",$f)."!"; break; }
        }
        if ($ok) {
            $zt = empty($az)?"Brak":$polaczenie->real_escape_string(implode(", ",$az));
            $wt = empty($aw)?"Brak":$polaczenie->real_escape_string(implode(", ",$aw));
            $polaczenie->query("UPDATE gracze SET zalety='$zt',wady='$wt' WHERE id=$id_gracza");
            echo "<script>location.href='game.php?page=karta';</script>"; exit;
        }
    }
}

// ── ZAPIS AP ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['zapisz_ap'])) {
    $ap_row  = $polaczenie->query("SELECT punkty_atrybutow FROM gracze WHERE id=$id_gracza")->fetch_assoc();
    $avail   = $ap_row['punkty_atrybutow'];
    $ds = (int)$_POST['dodana_sila']; $dz = (int)$_POST['dodana_zrecznosc'];
    $dw = (int)$_POST['dodana_wytrzymalosc']; $di = (int)$_POST['dodana_inteligencja'];
    $sum = $ds+$dz+$dw+$di;
    if ($sum>0 && $sum<=$avail) {
        $polaczenie->query("UPDATE gracze SET sila=sila+$ds,zrecznosc=zrecznosc+$dz,
            wytrzymalosc=wytrzymalosc+$dw,inteligencja=inteligencja+$di,
            punkty_atrybutow=punkty_atrybutow-$sum WHERE id=$id_gracza");
        echo "<script>location.href='game.php?page=karta';</script>"; exit;
    }
}

// ── ZAWODY — źródło prawdy: config/zawody.php ─────────────────
$zawody = $ZAWODY_DANE;

// WYBÓR ZAWODU
if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['wybierz_zawod'])) {
    $gd  = $polaczenie->query("SELECT umiejetnosci,tytul_naukowy FROM gracze WHERE id=$id_gracza")->fetch_assoc();
    $pu  = !empty($gd['umiejetnosci']) ? json_decode($gd['umiejetnosci'],true) : [];
    $wz  = $_POST['zawod'];
    if (isset($zawody[$wz])) {
        $req = $zawody[$wz]['wymagania']; $rt = $zawody[$wz]['wymagany_tytul'] ?? null;
        $ok = (!$rt || $gd['tytul_naukowy']==$rt);
        foreach ($req as $n=>$l) if (($pu[$n]??0)<$l) { $ok=false; break; }
        if ($ok) {
            $wz_esc = $polaczenie->real_escape_string($wz);
            $polaczenie->query("UPDATE gracze SET profesja_fabularna='$wz_esc' WHERE id=$id_gracza");
            echo "<script>location.href='game.php?page=karta';</script>"; exit;
        } else $blad_zawodu = "Nie spełniasz wymagań, by podjąć ten zawód!";
    }
}

// ── PEŁNE DANE GRACZA (po wszystkich zapisach POST) ───────────
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$posiadane_um = !empty($gracz['umiejetnosci']) ? json_decode($gracz['umiejetnosci'],true) : [];

// Pochodzenie do wyświetlenia (z aktualnymi danymi)
$poch = pochodzenie_dane($gracz['pochodzenie'] ?? null);

// Bonusy RP aktualnego zawodu
$zawod_aktywny = $gracz['profesja_fabularna'] ?? null;
$zawod_rp = null;
if ($zawod_aktywny && isset($ZAWODY_DANE[$zawod_aktywny]['rp'])) {
    $zawod_rp = $ZAWODY_DANE[$zawod_aktywny]['rp'];
}

// Reputacja grupowa gracza
$reputacja = reputacja_grupowa($gracz);
?>
<style>
/* ══════════════════════════════════════════════════════════════════
   KARTA.PHP — CYBERPUNK NYC
══════════════════════════════════════════════════════════════════ */

/* ── NAGŁÓWEK ──────────────────────────────────────────────── */
.k-head{text-align:center;margin-bottom:30px}
.k-head h1{
    font-family:'Oswald',sans-serif;color:#fff;font-size:2.8em;margin:0;
    text-transform:uppercase;letter-spacing:4px;font-weight:500;line-height:1;
    text-shadow:0 0 20px rgba(255,23,68,0.3);
}
.k-head p{
    color:var(--neon-red);font-size:.75em;margin-top:8px;
    font-family:'JetBrains Mono',monospace;letter-spacing:4px;text-transform:uppercase;
    text-shadow:0 0 6px rgba(255,23,68,0.5);
}

/* ── KARTA POCHODZENIA ─────────────────────────────────────── */
.karta-pochodzenie{
    display:flex;align-items:stretch;gap:16px;
    background:rgba(12,8,14,0.5);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:16px;margin-bottom:22px;
    position:relative;overflow:hidden;
}
.karta-pochodzenie::before{
    content:'';position:absolute;top:0;left:0;bottom:0;width:3px;
    background:var(--akcent);box-shadow:0 0 12px var(--akcent);
}
.karta-pochodzenie .flaga-box{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    min-width:90px;padding:10px;
    background:rgba(0,0,0,0.35);border-radius:2px;
    border:1px dashed var(--akcent);
}
.karta-pochodzenie .flaga-box .flag{font-size:2.8em;line-height:1;filter:drop-shadow(0 0 10px var(--akcent))}
.karta-pochodzenie .flaga-box .kraj{
    font-family:'JetBrains Mono',monospace;font-size:.68em;
    color:var(--akcent);letter-spacing:2px;margin-top:6px;
    text-shadow:0 0 6px var(--akcent);
}
.karta-pochodzenie .info{flex:1}
.karta-pochodzenie .etykieta{
    font-family:'JetBrains Mono',monospace;font-size:.7em;
    color:var(--txt-mute);letter-spacing:3px;text-transform:uppercase;
    margin-bottom:2px;
}
.karta-pochodzenie h3{
    font-family:'Oswald',sans-serif;font-weight:500;font-size:1.3em;
    text-transform:uppercase;letter-spacing:2px;color:#fff;
    margin-bottom:4px;
}
.karta-pochodzenie .cecha-info{
    font-family:'JetBrains Mono',monospace;font-size:.78em;
    color:var(--akcent);letter-spacing:1.5px;margin-bottom:10px;
    text-shadow:0 0 6px var(--akcent);
}
.karta-pochodzenie ul{list-style:none;padding:0;margin:0}
.karta-pochodzenie li{
    font-size:.88em;color:var(--txt-main);line-height:1.5;
    padding:3px 0 3px 16px;position:relative;
}
.karta-pochodzenie li::before{
    content:'▸';position:absolute;left:0;
    color:var(--akcent);text-shadow:0 0 4px var(--akcent);
}

/* ── BLOK ──────────────────────────────────────────────────── */
.blok{
    background:rgba(10,6,12,0.6);backdrop-filter:blur(8px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:28px;margin-bottom:24px;
    box-shadow:0 8px 32px rgba(0,0,0,0.6);
    position:relative;
}
.blok::before{
    content:'';position:absolute;top:0;left:0;width:32px;height:1px;
    background:var(--neon-red);box-shadow:0 0 6px var(--neon-red);
}
.blok-tytul{
    font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2.5px;
    font-size:1.05em;color:#fff;margin:0 0 20px;font-weight:500;
    padding-bottom:12px;border-bottom:1px solid var(--border-soft);
    display:flex;align-items:center;gap:10px;
}
.blok-tytul .note{
    color:var(--txt-mute);font-size:.72em;font-weight:400;
    text-transform:none;margin-left:auto;letter-spacing:.5px;
}

/* ── STAT GRID ────────────────────────────────────────────── */
.sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.sb{
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.1);
    border-radius:2px;padding:18px;position:relative;
}
.sb::before{
    content:'';position:absolute;left:0;top:12%;width:2px;height:76%;
    background:var(--neon-red);box-shadow:0 0 6px var(--neon-red);
}
.sb h4{
    color:var(--txt-dim);margin:0 0 14px;font-size:.75em;
    text-transform:uppercase;letter-spacing:2.5px;
    font-family:'Oswald',sans-serif;font-weight:500;
}
.sr{
    display:flex;justify-content:space-between;align-items:center;
    padding:8px 0;border-bottom:1px dashed rgba(255,23,68,0.08);
    color:var(--txt-dim);font-size:.92em;
}
.sr:last-child{border-bottom:none}
.sv{font-weight:700;font-family:'Oswald',sans-serif;font-size:1.1em;letter-spacing:1px}
.c-red   {color:var(--neon-red-hot);text-shadow:0 0 6px rgba(255,61,94,0.4)}
.c-blue  {color:var(--neon-cyan);text-shadow:0 0 6px rgba(74,214,255,0.4)}
.c-gold  {color:var(--neon-gold);text-shadow:0 0 6px rgba(255,215,0,0.4)}
.c-ember {color:var(--neon-ember);text-shadow:0 0 6px rgba(255,122,61,0.4)}
.c-green {color:var(--neon-green);text-shadow:0 0 6px rgba(90,255,154,0.4)}

/* ── AP PANEL ─────────────────────────────────────────────── */
.ap-panel{
    background:rgba(255,23,68,0.05);
    border:1px solid var(--border-mid);border-radius:2px;
    padding:20px;margin-bottom:22px;
    box-shadow:0 0 30px rgba(255,23,68,0.08);
}
.ap-header{
    display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;
    padding-bottom:12px;border-bottom:1px dashed rgba(255,23,68,0.15);
}
.ap-title{
    font-family:'Oswald',sans-serif;font-size:1.15em;color:var(--neon-red-hot);
    text-shadow:0 0 10px rgba(255,23,68,0.5);
    text-transform:uppercase;letter-spacing:2px;
}
.ap-pool{color:var(--txt-dim);font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase;font-size:.9em}
.ap-pool strong{color:#fff;font-size:1.4em;margin:0 6px;text-shadow:0 0 10px rgba(255,23,68,0.5)}
.ap-row{
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.08);
    border-radius:2px;padding:10px 16px;
    display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;
}
.ap-name{color:var(--txt-main);font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.95em;letter-spacing:1px}
.ap-name span{color:var(--txt-mute);margin-left:8px;font-size:.9em}
.btn-ap{
    background:rgba(0,0,0,0.8);color:var(--txt-main);
    border:1px solid var(--border-soft);
    width:28px;height:28px;border-radius:2px;font-weight:700;cursor:pointer;
    transition:.2s;font-size:1em;display:inline-flex;align-items:center;justify-content:center;
    font-family:'JetBrains Mono',monospace;
}
.btn-ap:hover{background:var(--neon-red);color:#fff;border-color:var(--neon-red);box-shadow:0 0 10px rgba(255,23,68,0.6)}
.ap-val{color:var(--neon-red-hot);width:40px;text-align:center;font-weight:700;font-family:'JetBrains Mono',monospace;font-size:1.05em;text-shadow:0 0 6px rgba(255,23,68,0.5)}
.btn-save{
    width:100%;background:rgba(255,23,68,0.1);color:var(--neon-red-hot);
    border:1px solid var(--neon-red);padding:13px;
    font-family:'Oswald',sans-serif;font-size:1.1em;font-weight:600;
    cursor:pointer;text-transform:uppercase;border-radius:2px;transition:.3s;letter-spacing:2.5px;
    margin-top:14px;display:none;
}
.btn-save:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 20px rgba(255,23,68,0.7);text-shadow:0 0 8px #fff}

/* ── CECHY ────────────────────────────────────────────────── */
.cechy-flex{display:flex;flex-wrap:wrap;gap:20px;margin-top:6px}
.cechy-kol{
    flex:1;min-width:280px;
    background:rgba(0,0,0,0.4);border:1px solid rgba(255,23,68,0.08);
    border-radius:2px;padding:20px;
}
.cechy-kol-tytul{
    font-family:'Oswald',sans-serif;font-size:1.2em;margin-bottom:12px;letter-spacing:2px;text-transform:uppercase;
}
.cechy-kol-tytul .cnt{color:var(--txt-mute);font-size:.7em;margin-left:6px;letter-spacing:1px}
.zalety-tytul{color:var(--neon-green);text-shadow:0 0 8px rgba(90,255,154,0.5)}
.wady-tytul{color:var(--neon-red-hot);text-shadow:0 0 8px rgba(255,23,68,0.5)}

.cecha-tag{
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(0,0,0,0.6);border-radius:2px;padding:5px 12px;
    font-size:.85em;margin:3px;border:1px solid;
    font-family:'Oswald',sans-serif;letter-spacing:.5px;
}
.cecha-tag.z{border-color:rgba(90,255,154,0.3);color:var(--neon-green)}
.cecha-tag.w{border-color:var(--border-mid);color:var(--neon-red-hot)}
.brak-cech{color:var(--txt-mute);font-style:italic;font-family:'JetBrains Mono',monospace;font-size:.8em}

.btn-toggle{
    background:rgba(0,0,0,0.6);border:1px solid rgba(255,23,68,0.1);
    color:var(--txt-dim);padding:10px 16px;width:100%;
    font-family:'Oswald',sans-serif;font-size:1em;text-transform:uppercase;letter-spacing:1.5px;
    cursor:pointer;border-radius:2px;margin:14px 0 10px;transition:.3s;
    display:flex;justify-content:space-between;align-items:center;
}
.btn-toggle.z:hover{border-color:var(--neon-green);color:var(--neon-green);background:rgba(90,255,154,0.06)}
.btn-toggle.w:hover{border-color:var(--neon-red);color:var(--neon-red-hot);background:rgba(255,23,68,0.06)}
.btn-toggle .free{font-size:.8em;color:var(--txt-mute);letter-spacing:1px}

.lista-uk{display:none;padding:4px 0}
.cb-item{
    display:flex;align-items:flex-start;gap:10px;
    padding:9px 10px;border-radius:2px;cursor:pointer;
    transition:background .2s;color:var(--txt-dim);font-size:.9em;
    border:1px solid transparent;margin-bottom:4px;
    position:relative; /* potrzebne do pozycjonowania tooltipa */
}
.cb-item:hover{background:rgba(255,255,255,0.03);color:var(--txt-main)}
.cb-item input{margin-top:2px;flex-shrink:0;width:14px;height:14px;cursor:pointer;accent-color:var(--neon-red)}
.cb-item .cb-tresc{flex:1;min-width:0}
.cb-item .cb-nazwa{font-weight:500}
.cb-item .cb-opis{font-size:.8em;color:var(--txt-mute);margin-top:2px;line-height:1.4}
.cb-item.nabyta{opacity:.5;cursor:not-allowed}

/* ── GRUPY CECH (nagłówki kategorii) ───────────────────── */
.grupa-naglowek{
    font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:3px;
    text-transform:uppercase;padding:8px 10px 4px;margin-top:10px;
    border-bottom:1px dashed rgba(255,255,255,0.08);
}
.grupa-naglowek:first-child{margin-top:0}
.grupa-naglowek.zal{color:var(--neon-green);text-shadow:0 0 6px rgba(90,255,154,0.4)}
.grupa-naglowek.wad{color:var(--neon-red-hot);text-shadow:0 0 6px rgba(255,23,68,0.5)}
.grupa-naglowek::before{content:'◈ ';opacity:.6}

/* ── TOOLTIP SZCZEGÓŁOWY PO HOVER ─────────────────────── */
.cecha-tooltip{
    position:absolute;top:-4px;
    width:320px;padding:14px 16px;
    background:rgba(5,3,7,0.97);backdrop-filter:blur(14px);
    border:1px solid var(--neon-red);border-radius:2px;
    opacity:0;visibility:hidden;pointer-events:none;
    z-index:1000;
    box-shadow:0 10px 40px rgba(0,0,0,0.95),0 0 20px rgba(255,23,68,0.25);
    transition:opacity .25s ease .2s, visibility .25s ease .2s;
}
.cecha-tooltip::before{
    content:'';position:absolute;top:14px;width:0;height:0;
    border-style:solid;border-width:6px;
}
/* Tooltip po prawej stronie (dla zalet) */
.cecha-tooltip.tt-prawo{left:calc(100% + 10px)}
.cecha-tooltip.tt-prawo::before{
    right:100%;border-color:transparent var(--neon-red) transparent transparent;
}
/* Tooltip po lewej stronie (dla wad) */
.cecha-tooltip.tt-lewo{right:calc(100% + 10px)}
.cecha-tooltip.tt-lewo::before{
    left:100%;border-color:transparent transparent transparent var(--neon-red);
}
.cb-item:hover .cecha-tooltip{opacity:1;visibility:visible;pointer-events:auto}

.cecha-tooltip .tt-header{
    display:flex;justify-content:space-between;align-items:flex-start;gap:10px;
    padding-bottom:10px;margin-bottom:10px;border-bottom:1px dashed rgba(255,255,255,0.1);
}
.cecha-tooltip .tt-nazwa{
    font-family:'Oswald',sans-serif;font-size:1.1em;color:#fff;font-weight:500;
    letter-spacing:1px;text-transform:uppercase;line-height:1.2;
    text-shadow:0 0 8px rgba(255,23,68,0.4);
}
.cecha-tooltip .tt-grupa{
    font-family:'JetBrains Mono',monospace;font-size:.65em;letter-spacing:2px;
    text-transform:uppercase;padding:3px 7px;border-radius:1px;
    background:rgba(0,0,0,0.5);white-space:nowrap;flex-shrink:0;
}
.cecha-tooltip .tt-grupa.zal{color:var(--neon-green);border:1px solid rgba(90,255,154,0.4)}
.cecha-tooltip .tt-grupa.wad{color:var(--neon-red-hot);border:1px solid rgba(255,23,68,0.4)}

.cecha-tooltip .tt-sekcja{margin-bottom:10px}
.cecha-tooltip .tt-sekcja:last-child{margin-bottom:0}
.cecha-tooltip .tt-label{
    font-family:'Oswald',sans-serif;font-size:.72em;letter-spacing:2px;
    color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px;
    text-shadow:0 0 6px rgba(74,214,255,0.3);
}
.cecha-tooltip .tt-tresc{
    font-family:'Open Sans',sans-serif;font-size:.82em;color:var(--txt-main);
    line-height:1.55;
}

/* Mobilne: tooltip w dole zamiast boku — dla zbyt wąskich ekranów */
@media(max-width:900px){
    .cecha-tooltip.tt-prawo,.cecha-tooltip.tt-lewo{
        left:0;right:0;width:auto;top:100%;margin-top:5px;
    }
    .cecha-tooltip.tt-prawo::before,.cecha-tooltip.tt-lewo::before{display:none}
}

/* ── ZAWODY ───────────────────────────────────────────────── */
.kariera-header{
    display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
    gap:12px;margin-bottom:22px;padding:14px 18px;
    background:rgba(0,0,0,0.4);border:1px solid rgba(255,23,68,0.08);border-radius:2px;
}
.kariera-label{
    font-family:'Oswald',sans-serif;color:var(--txt-dim);text-transform:uppercase;
    font-size:.85em;letter-spacing:2px;
}
.kariera-label strong{
    color:var(--neon-cyan);font-size:1.35em;margin-left:10px;
    text-shadow:0 0 10px rgba(74,214,255,0.4);letter-spacing:1.5px;
}
.kariera-tytul{
    color:var(--neon-gold);font-family:'Oswald',sans-serif;font-size:1.05em;
    letter-spacing:1.5px;text-transform:uppercase;
    text-shadow:0 0 8px rgba(255,215,0,0.4);
}

.zawody-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px}
.zawod-karta{
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.1);
    border-radius:2px;padding:18px;
    display:flex;flex-direction:column;gap:12px;
    transition:all .25s;position:relative;
}
.zawod-karta:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,0.6)}
.zawod-karta.aktywny{border-color:var(--neon-green);box-shadow:0 0 20px rgba(90,255,154,0.15)}
.zawod-karta.aktywny::before{
    content:'AKTYWNY';position:absolute;top:10px;right:10px;
    font-family:'JetBrains Mono',monospace;font-size:.65em;
    color:var(--neon-green);letter-spacing:2px;padding:2px 6px;
    border:1px solid var(--neon-green);border-radius:1px;
    text-shadow:0 0 6px rgba(90,255,154,0.5);
}
.zawod-karta.dostepny{border-color:rgba(74,214,255,0.25)}
.zawod-karta.niedostepny{opacity:.45}
.zawod-nazwa{
    font-family:'Oswald',sans-serif;font-size:1.2em;color:#fff;
    letter-spacing:1.5px;text-transform:uppercase;
}
.zawod-opis{color:var(--txt-dim);font-size:.86em;line-height:1.5}
.zawod-req{
    background:rgba(5,3,7,0.8);border:1px solid rgba(255,23,68,0.06);
    border-radius:2px;padding:10px 12px;
    font-size:.82em;line-height:1.8;font-family:'JetBrains Mono',monospace;
}
.req-ok {color:var(--neon-green)}
.req-brak{color:var(--neon-red-hot);text-decoration:line-through;opacity:.7}
.req-tytul-ok  {color:var(--neon-gold)}
.req-tytul-brak{color:var(--neon-red-hot);text-decoration:line-through;opacity:.7}

.btn-zawod{
    width:100%;padding:11px;font-family:'Oswald',sans-serif;font-weight:600;
    font-size:.95em;text-transform:uppercase;letter-spacing:2px;border-radius:2px;
    cursor:pointer;border:1px solid;transition:.3s;margin-top:auto;
}
.btn-z-aktywny{background:rgba(90,255,154,0.12);border-color:var(--neon-green);color:var(--neon-green);cursor:default}
.btn-z-wybierz{background:rgba(74,214,255,0.08);border-color:var(--neon-cyan);color:var(--neon-cyan)}
.btn-z-wybierz:hover{background:var(--neon-cyan);color:#000;box-shadow:0 0 18px rgba(74,214,255,0.5)}
.btn-z-brak   {background:transparent;border-color:rgba(255,255,255,0.06);color:var(--txt-mute);cursor:not-allowed}

/* ── BONUSY RP ────────────────────────────────────────────── */
.rp-opis{
    color:var(--txt-main);font-size:.95em;line-height:1.6;
    padding:14px 18px;margin-bottom:18px;
    background:rgba(255,122,61,0.05);border:1px solid rgba(255,122,61,0.2);border-radius:2px;
    font-style:italic;position:relative;
}
.rp-opis::before{
    content:'';position:absolute;left:0;top:0;bottom:0;width:2px;
    background:var(--neon-ember);box-shadow:0 0 8px var(--neon-ember);
}
.rp-sub{
    font-family:'Oswald',sans-serif;font-size:.95em;color:#fff;
    text-transform:uppercase;letter-spacing:2px;margin:18px 0 12px;
    padding-bottom:6px;border-bottom:1px dashed rgba(255,23,68,0.15);
    display:flex;align-items:center;gap:8px;font-weight:500;
}
.rp-um-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:8px}
.rp-um-row{
    display:flex;justify-content:space-between;align-items:center;gap:10px;
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.08);
    border-radius:2px;padding:9px 14px;font-size:.88em;
}
.rp-um-row .um-nazwa{color:var(--txt-main);flex:1;font-family:'Oswald',sans-serif;letter-spacing:.5px}
.rp-um-row .um-bonus{
    color:var(--neon-ember);font-family:'JetBrains Mono',monospace;font-weight:700;
    text-shadow:0 0 6px rgba(255,122,61,0.5);white-space:nowrap;
}
.rp-um-row .um-wynik{
    color:var(--txt-dim);font-family:'JetBrains Mono',monospace;font-size:.82em;
    white-space:nowrap;text-align:right;
}
.rp-um-row .um-wynik strong{color:var(--neon-green);text-shadow:0 0 6px rgba(90,255,154,0.5)}

.rep-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
.rep-box{
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.08);
    border-radius:2px;padding:14px;text-align:center;position:relative;
}
.rep-box .rep-nazwa{
    font-family:'Oswald',sans-serif;font-size:.78em;color:var(--txt-mute);
    text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;
}
.rep-box .rep-wart{
    font-family:'Oswald',sans-serif;font-size:1.6em;font-weight:500;
    line-height:1;margin-bottom:6px;letter-spacing:1px;
}
.rep-box .rep-opis{
    font-family:'JetBrains Mono',monospace;font-size:.7em;
    color:var(--txt-dim);letter-spacing:1px;text-transform:uppercase;
}

/* ── PRZYCISK ZAPIS CECH ─────────────────────────────────── */
.btn-zapisz-cechy{
    margin-top:20px;width:100%;
    background:rgba(74,214,255,0.08);color:var(--neon-cyan);
    border:1px solid rgba(74,214,255,0.4);padding:13px;
    font-family:'Oswald',sans-serif;font-size:1.05em;font-weight:600;
    cursor:pointer;text-transform:uppercase;border-radius:2px;
    transition:.3s;letter-spacing:2.5px;
}
.btn-zapisz-cechy:hover{background:var(--neon-cyan);color:#000;box-shadow:0 0 20px rgba(74,214,255,0.5)}

/* ── BLĄD ─────────────────────────────────────────────────── */
.blad{
    background:rgba(255,23,68,0.1);border:1px solid var(--border-mid);
    color:var(--neon-red-hot);padding:13px 16px;border-radius:2px;margin-bottom:18px;
    font-weight:500;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1.5px;
    box-shadow:0 0 20px rgba(255,23,68,0.15);
}
</style>

<!-- ══ NAGŁÓWEK ══════════════════════════════════════════════════ -->
<div class="k-head">
    <h1>Karta Postaci</h1>
    <p>// <?php echo htmlspecialchars($gracz['login']); ?> · LVL <?php echo $gracz['poziom']; ?></p>
</div>

<?php if($blad_cech) echo "<div class='blad'>⚠ $blad_cech</div>"; ?>

<!-- ══ KARTA POCHODZENIA ═══════════════════════════════════════ -->
<?php if ($poch): ?>
<div class="karta-pochodzenie" style="--akcent: <?php echo $poch['kolor_akcent']; ?>">
    <div class="flaga-box">
        <span class="flag"><?php echo $poch['flaga']; ?></span>
        <span class="kraj"><?php echo htmlspecialchars($poch['kraj']); ?></span>
    </div>
    <div class="info">
        <div class="etykieta">◆ POCHODZENIE</div>
        <h3><?php echo htmlspecialchars($poch['nazwa_m']); ?></h3>
        <div class="cecha-info">
            Cecha: <?php echo htmlspecialchars($poch['cecha']); ?> ·
            Synergia: <?php echo htmlspecialchars($poch['synergia']); ?>
        </div>
        <ul>
        <?php foreach ($poch['bonusy_opis'] as $b): ?>
            <li><?php echo htmlspecialchars($b); ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- ══ STATYSTYKI BOJOWE ═══════════════════════════════════════════ -->
<div class="blok">
    <div class="blok-tytul">⚔️ Statystyki Bojowe</div>

    <?php if($gracz['punkty_atrybutow']>0): ?>
    <div class="ap-panel">
        <div class="ap-header">
            <span class="ap-title">⚡ Rozdaj Atrybuty</span>
            <span class="ap-pool">Dostępne: <strong id="ap_pool"><?php echo $gracz['punkty_atrybutow']; ?></strong>AP</span>
        </div>
        <form method="POST" action="game.php?page=karta">
        <?php
        $attrs = ['sila'=>['Siła','💪'],'zrecznosc'=>['Zręczność','🎯'],'wytrzymalosc'=>['Wytrzymałość','🛡️'],'inteligencja'=>['Inteligencja','🧠']];
        foreach($attrs as $k=>[$label,$ico]): ?>
            <div class="ap-row">
                <span class="ap-name"><?php echo $ico; ?> <?php echo $label; ?> <span>(obecnie: <?php echo $gracz[$k]; ?>)</span></span>
                <div style="display:flex;align-items:center;gap:6px">
                    <button type="button" class="btn-ap" onclick="apChange('<?php echo $k;?>',-1)">−</button>
                    <span class="ap-val" id="disp_<?php echo $k; ?>">0</span>
                    <input type="hidden" name="dodana_<?php echo $k; ?>" id="inp_<?php echo $k; ?>" value="0">
                    <button type="button" class="btn-ap" onclick="apChange('<?php echo $k;?>',1)">+</button>
                </div>
            </div>
        <?php endforeach; ?>
            <button type="submit" name="zapisz_ap" id="btn_ap" class="btn-save">◤ Zatwierdź Rozwój ◥</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="sg">
        <div class="sb">
            <h4>🧠 Atrybuty</h4>
            <div class="sr"><span>Siła</span>           <span class="sv c-red"><?php echo $gracz['sila']; ?></span></div>
            <div class="sr"><span>Zręczność</span>      <span class="sv c-red"><?php echo $gracz['zrecznosc']; ?></span></div>
            <div class="sr"><span>Wytrzymałość</span>   <span class="sv c-red"><?php echo $gracz['wytrzymalosc']; ?></span></div>
            <div class="sr"><span>Inteligencja</span>   <span class="sv c-red"><?php echo $gracz['inteligencja']; ?></span></div>
        </div>
        <div class="sb">
            <h4>🛠️ Skille</h4>
            <div class="sr"><span>Walka Bronią</span>   <span class="sv c-ember"><?php echo number_format($gracz['walka_bronia'],2); ?></span></div>
            <div class="sr"><span>Zdolność Uniku</span> <span class="sv c-ember"><?php echo number_format($gracz['uniki'],2); ?></span></div>
            <div class="sr"><span>Szabrowanie</span>    <span class="sv c-ember"><?php echo number_format($skill_szab,2); ?></span></div>
            <div class="sr"><span>Inżynieria</span>     <span class="sv c-ember"><?php echo number_format($skill_inz, 2); ?></span></div>
        </div>
        <div class="sb">
            <h4>⚡ Stan Fizyczny</h4>
            <div class="sr"><span>Punkty Życia</span>   <span class="sv c-red"><?php echo $gracz['hp_aktualne'].'/'.$gracz['hp_max']; ?></span></div>
            <div class="sr"><span>Energia</span>        <span class="sv c-blue"><?php echo $gracz['energia_aktualna'].'/'.$gracz['energia_max']; ?></span></div>
            <div class="sr"><span>Siła Ataku</span>     <span class="sv c-ember"><?php echo $gracz['sila']+$gracz['bonus_atak']; ?></span></div>
            <div class="sr"><span>Obrona</span>         <span class="sv c-blue"><?php echo $gracz['wytrzymalosc']+$gracz['bonus_obrona']; ?></span></div>
        </div>
    </div>
</div>

<!-- ══ CHARAKTER ══════════════════════════════════════════════════ -->
<div class="blok">
    <div class="blok-tytul">🎭 Charakter <span class="note">cechy fabularne</span></div>
    <p style="color:var(--txt-dim);margin-bottom:18px;font-size:.88em;line-height:1.5">
        Limit <?php echo $limit_cech; ?> zalet i <?php echo $limit_cech; ?> wad. <strong style="color:var(--neon-red-hot)">Nabytych wad nie można cofnąć.</strong>
    </p>

    <form method="POST" action="game.php?page=karta">
    <div class="cechy-flex">

        <!-- ZALETY -->
        <div class="cechy-kol">
            <div class="cechy-kol-tytul zalety-tytul">
                Zalety <span class="cnt">(<?php echo count($aktualne_zalety); ?>/<?php echo $limit_cech; ?>)</span>
            </div>
            <div>
                <?php foreach($aktualne_zalety as $z) echo "<span class='cecha-tag z'>✓ ".htmlspecialchars($z)."</span>"; ?>
                <?php if(empty($aktualne_zalety)) echo "<span class='brak-cech'>// Brak nabytej przewagi</span>"; ?>
            </div>
            <button type="button" class="btn-toggle z" onclick="tog('lista-z')">
                <span>▸ Dobierz zalety</span>
                <span class="free"><?php echo $limit_cech-count($aktualne_zalety); ?> wolnych</span>
            </button>
            <div id="lista-z" class="lista-uk">
                <?php
                // Grupowanie zalet według pola 'grupa'
                $grupy_zal = [];
                foreach ($wszystkie_zalety_def as $n => $d) {
                    $g = $d['grupa'] ?? 'Inne';
                    if (!isset($grupy_zal[$g])) $grupy_zal[$g] = [];
                    $grupy_zal[$g][$n] = $d;
                }
                foreach ($grupy_zal as $gn => $cechy): ?>
                    <div class="grupa-naglowek zal"><?php echo htmlspecialchars($gn); ?></div>
                    <?php foreach ($cechy as $n => $d):
                        $nabyta = in_array($n, $aktualne_zalety); ?>
                    <label class="cb-item<?php echo $nabyta?' nabyta':''; ?>">
                        <input type="checkbox" name="zalety[]" value="<?php echo htmlspecialchars($n); ?>" class="cecha-cb"
                            <?php echo $nabyta?'checked onclick="return false;"':''; ?>>
                        <div class="cb-tresc">
                            <div class="cb-nazwa"><?php echo htmlspecialchars($n); ?><?php if($nabyta) echo " <span style='color:var(--neon-green);font-size:.8em'>(Nabyte)</span>"; ?></div>
                            <div class="cb-opis"><?php echo htmlspecialchars($d['opis']); ?></div>
                        </div>
                        <div class="cecha-tooltip tt-prawo">
                            <div class="tt-header">
                                <div class="tt-nazwa"><?php echo htmlspecialchars($n); ?></div>
                                <div class="tt-grupa zal"><?php echo htmlspecialchars($gn); ?></div>
                            </div>
                            <div class="tt-sekcja">
                                <div class="tt-label">▸ Opis fabularny</div>
                                <div class="tt-tresc"><?php echo htmlspecialchars($d['opis']); ?></div>
                            </div>
                            <div class="tt-sekcja">
                                <div class="tt-label">▸ Wpływ na grę</div>
                                <div class="tt-tresc"><?php echo htmlspecialchars($d['wplyw']); ?></div>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- WADY -->
        <div class="cechy-kol">
            <div class="cechy-kol-tytul wady-tytul">
                Wady <span class="cnt">(<?php echo count($aktualne_wady); ?>/<?php echo $limit_cech; ?>)</span>
            </div>
            <div>
                <?php foreach($aktualne_wady as $w) echo "<span class='cecha-tag w'>✗ ".htmlspecialchars($w)."</span>"; ?>
                <?php if(empty($aktualne_wady)) echo "<span class='brak-cech'>// Brak widocznych słabości</span>"; ?>
            </div>
            <button type="button" class="btn-toggle w" onclick="tog('lista-w')">
                <span>▸ Dobierz wady</span>
                <span class="free"><?php echo $limit_cech-count($aktualne_wady); ?> wolnych</span>
            </button>
            <div id="lista-w" class="lista-uk">
                <?php
                // Grupowanie wad według pola 'grupa'
                $grupy_wad = [];
                foreach ($wszystkie_wady_def as $n => $d) {
                    $g = $d['grupa'] ?? 'Inne';
                    if (!isset($grupy_wad[$g])) $grupy_wad[$g] = [];
                    $grupy_wad[$g][$n] = $d;
                }
                foreach ($grupy_wad as $gn => $cechy): ?>
                    <div class="grupa-naglowek wad"><?php echo htmlspecialchars($gn); ?></div>
                    <?php foreach ($cechy as $n => $d):
                        $nabyta = in_array($n, $aktualne_wady); ?>
                    <label class="cb-item<?php echo $nabyta?' nabyta':''; ?>">
                        <input type="checkbox" name="wady[]" value="<?php echo htmlspecialchars($n); ?>" class="cecha-cb"
                            <?php echo $nabyta?'checked onclick="return false;"':''; ?>>
                        <div class="cb-tresc">
                            <div class="cb-nazwa"><?php echo htmlspecialchars($n); ?><?php if($nabyta) echo " <span style='color:var(--neon-red-hot);font-size:.8em'>(Nabyte)</span>"; ?></div>
                            <div class="cb-opis"><?php echo htmlspecialchars($d['opis']); ?></div>
                        </div>
                        <div class="cecha-tooltip tt-lewo">
                            <div class="tt-header">
                                <div class="tt-nazwa"><?php echo htmlspecialchars($n); ?></div>
                                <div class="tt-grupa wad"><?php echo htmlspecialchars($gn); ?></div>
                            </div>
                            <div class="tt-sekcja">
                                <div class="tt-label">▸ Opis fabularny</div>
                                <div class="tt-tresc"><?php echo htmlspecialchars($d['opis']); ?></div>
                            </div>
                            <div class="tt-sekcja">
                                <div class="tt-label">▸ Wpływ na grę</div>
                                <div class="tt-tresc"><?php echo htmlspecialchars($d['wplyw']); ?></div>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <button type="submit" name="zapisz_cechy" class="btn-zapisz-cechy">Zaktualizuj Historię Postaci →</button>
    </form>
</div>

<!-- ══ KARIERA ════════════════════════════════════════════════════ -->
<div class="blok">
    <div class="blok-tytul">💼 Kariera Fabularna</div>

    <div class="kariera-header">
        <span class="kariera-label">
            Obecny zawód:
            <strong><?php echo htmlspecialchars($gracz['profesja_fabularna'] ?: '—'); ?></strong>
        </span>
        <?php if(!empty($gracz['tytul_naukowy'])): ?>
        <span class="kariera-tytul">🎓 <?php echo htmlspecialchars($gracz['tytul_naukowy']); ?></span>
        <?php endif; ?>
    </div>

    <?php if($blad_zawodu) echo "<div class='blad'>⚠ $blad_zawodu</div>"; ?>

    <div class="zawody-grid">
    <?php foreach($zawody as $nazwa=>$dane):
        $req_t   = $dane['wymagany_tytul'] ?? null;
        $braki   = false;
        $html_r  = "";
        if ($req_t) {
            if ($gracz['tytul_naukowy']==$req_t)
                $html_r .= "<div class='req-tytul-ok'>🎓 $req_t ✓</div>";
            else { $braki=true; $html_r .= "<div class='req-tytul-brak'>🎓 $req_t</div>"; }
        }
        foreach ($dane['wymagania'] as $n=>$l) {
            $p = $posiadane_um[$n] ?? 0;
            if ($p < $l) { $braki=true; $html_r .= "<div class='req-brak'>$n ($p/$l)</div>"; }
            else           $html_r .= "<div class='req-ok'>$n ($p/$l) ✓</div>";
        }
        $moze   = !$braki;
        $obecny = ($gracz['profesja_fabularna']==$nazwa);
        $cls    = $obecny ? 'aktywny' : ($moze ? 'dostepny' : 'niedostepny');
    ?>
        <div class="zawod-karta <?php echo $cls; ?>">
            <div>
                <div class="zawod-nazwa"><?php echo htmlspecialchars($nazwa); ?></div>
                <div class="zawod-opis"><?php echo htmlspecialchars($dane['opis']); ?></div>
            </div>
            <div class="zawod-req"><?php echo $html_r; ?></div>
            <form method="POST">
                <input type="hidden" name="zawod" value="<?php echo htmlspecialchars($nazwa); ?>">
                <?php if($obecny): ?>
                    <button type="button" class="btn-zawod btn-z-aktywny">✓ Wykonujesz ten zawód</button>
                <?php elseif($moze): ?>
                    <button type="submit" name="wybierz_zawod" class="btn-zawod btn-z-wybierz">Zdobądź ten zawód</button>
                <?php else: ?>
                    <button type="button" class="btn-zawod btn-z-brak" disabled>Nie spełniasz wymagań</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- ══ BONUSY RP — aktywne w sesjach Centrum Opowieści ══════════ -->
<?php if ($zawod_rp || !empty($reputacja)): ?>
<div class="blok">
    <div class="blok-tytul">🎭 Bonusy Fabularne (RP) <span class="note">aktywne w sesjach Centrum Opowieści</span></div>

    <?php if ($zawod_rp && !empty($zawod_rp['opis_spoleczny'])): ?>
    <div class="rp-opis">
        <?php echo htmlspecialchars($zawod_rp['opis_spoleczny']); ?>
    </div>
    <?php endif; ?>

    <?php if ($zawod_rp && !empty($zawod_rp['umiejetnosci_bonus_proc'])): ?>
    <div class="rp-sub">⚡ Bonusy zawodu do umiejętności</div>
    <div class="rp-um-grid">
        <?php foreach ($zawod_rp['umiejetnosci_bonus_proc'] as $um => $proc):
            $wynik = bonus_rp_umiejetnosci($gracz, $um);
        ?>
        <div class="rp-um-row">
            <span class="um-nazwa"><?php echo htmlspecialchars($um); ?></span>
            <span class="um-bonus">+<?php echo (int)$proc; ?>%</span>
            <span class="um-wynik"><?php echo formatuj_bonus_rp($wynik); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="rp-sub">👥 Reputacja grupowa</div>
    <div class="rep-grid">
        <?php
        $grupy_label = [
            'elita'         => 'Elita',
            'ulica'         => 'Ulica',
            'syndykat'      => 'Syndykat',
            'wladze'        => 'Władze',
            'spoleczenstwo' => 'Społeczeństwo',
        ];
        foreach ($grupy_label as $g => $label):
            $wart = $reputacja[$g] ?? 0;
            $opis = reputacja_opis($wart);
            $sign = $wart > 0 ? '+' : '';
        ?>
        <div class="rep-box">
            <div class="rep-nazwa"><?php echo $label; ?></div>
            <div class="rep-wart" style="color:<?php echo $opis['kolor']; ?>;text-shadow:0 0 8px <?php echo $opis['kolor']; ?>">
                <?php echo $opis['ikona']; ?> <?php echo $sign . $wart; ?>
            </div>
            <div class="rep-opis"><?php echo htmlspecialchars($opis['opis']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
// ── AP SYSTEM ─────────────────────────────────────────────
let apPool = <?php echo $gracz['punkty_atrybutow']; ?>, apSpent = 0;
const apVals = {sila:0, zrecznosc:0, wytrzymalosc:0, inteligencja:0};
function apChange(attr, d) {
    if (d>0 && apSpent>=apPool) return;
    if (d<0 && apVals[attr]<=0) return;
    apVals[attr] += d; apSpent += d;
    document.getElementById('disp_'+attr).textContent = (apVals[attr]>0?'+':'')+apVals[attr];
    document.getElementById('inp_'+attr).value = apVals[attr];
    document.getElementById('ap_pool').textContent = apPool - apSpent;
    document.getElementById('btn_ap').style.display = apSpent>0 ? 'block' : 'none';
}

// ── TOGGLE LISTY CECH ─────────────────────────────────────
function tog(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display==='block' ? 'none' : 'block';
}

// ── WALIDACJA CECH ────────────────────────────────────────
const konflikty = <?php echo json_encode($konflikty); ?>;
const LIMIT = <?php echo (int)$limit_cech; ?>;
document.querySelectorAll('.cecha-cb').forEach(cb => cb.addEventListener('change', validate));
function validate() {
    const sel = [...document.querySelectorAll('.cecha-cb:checked')].map(c=>c.value);
    const cz  = document.querySelectorAll('input[name="zalety[]"]:checked').length;
    const cw  = document.querySelectorAll('input[name="wady[]"]:checked').length;
    document.querySelectorAll('.cecha-cb').forEach(cb => {
        if (cb.classList.contains('nabyta')) return;
        let blok = false;
        if (!cb.checked) {
            if (cb.name==='zalety[]' && cz>=LIMIT) blok=true;
            if (cb.name==='wady[]'   && cw>=LIMIT) blok=true;
        }
        konflikty.forEach(g => {
            if (g.includes(cb.value)) {
                const other = g.filter(x => sel.includes(x) && x!==cb.value);
                if (other.length) { blok=true; cb.checked=false; }
            }
        });
        cb.disabled = blok;
        cb.closest('.cb-item').style.opacity = blok ? '.3' : '1';
        cb.closest('.cb-item').style.cursor  = blok ? 'not-allowed' : 'pointer';
    });
}
validate();
</script>