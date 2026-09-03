<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — KATALOG POCHODZEŃ (21 narodowości)

   DWIE WARSTWY BONUSÓW:
   1. MECHANICZNE (klucz 'bonusy') → używane w walce, craftingu, szabrowaniu
      - helper: pochodzenie_bonus($gracz, $klucz, $default)
   2. FABULARNE/RP (klucz 'rp')     → używane w sesjach w Centrum Opowieści
      - helper: rp_bonus_flat_pochodzenia() w config/rp_helpers.php

   REPUTACJA GRUPOWA (klucz 'rp' → 'reputacja'):
   - elita         : korporacje, bogacze, salony
   - ulica         : gangi, dzielnice niskiego szczebla
   - syndykat      : zorganizowana przestępczość (mafie, triady, kartel)
   - wladze        : policja, sędziowie, urzędnicy
   - spoleczenstwo : przeciętny obywatel, małe przedsiębiorstwa
   Skala: -5 (strach/pogarda) ... 0 (neutralnie) ... +5 (szacunek/miłość)
   ═══════════════════════════════════════════════════════════════════════ */

$POCHODZENIA_DANE = [

    /* ══════════════════ 🇵🇱 POLAK ══════════════════════════════ */
    'POLSKA' => [
        'flaga' => '🇵🇱', 'nazwa_m' => 'Polak', 'nazwa_f' => 'Polka',
        'kraj' => 'POLSKA', 'miasto_startowe' => 'WARSZAWA',
        'kolor_akcent' => '#dc143c',
        'opis' => 'Wychowani wśród ruin imperiów i przemysłowych kominów. Trzy pokolenia wstecz ich dziadkowie łatali Moskwicze gumą do żucia — oni łatają Glocki złomem z Manhattan Metro.',
        'cecha' => 'Kombinator',
        'opis_cechy' => 'Polacy słyną z zaradności i robienia "czegoś z niczego". W mrocznym świecie The Abyss to umiejętność na wagę złota.',
        'synergia' => 'Szabrownik',
        'bonusy_opis' => [
            'Szabrowanie na Złomowisku kosztuje −15% Energii',
            '+5 p.p. bazowej szansy na znalezienie rzadkiego artefaktu',
            '+1 Wytrzymałość na start (+10 HP max)',
        ],
        'bonusy' => [
            'szabrowanie_energia_mult'  => 0.85,
            'artefakt_szansa_bonus_abs' => 5.0,
        ],
        'atrybut_start' => 'wytrzymalosc',
        'rp' => [
            'opis_kulturowy' => 'W półświatku lekceważony za "wschodni" akcent, ale szanowany za zaradność. W sesjach jest mistrzem kombinowania — potrafi skonstruować plan z pustego mieszkania i resztek kawy.',
            'umiejetnosci_bonus_flat' => [
                'Znajomość Półświatka' => 2,
                'Inżynieria Złomu' => 2,
                'Handel i Wycena' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Etykieta i Dobre Manery' => -1,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 0],
        ],
    ],

    /* ══════════════════ 🇺🇸 AMERYKANIN ═════════════════════════ */
    'USA' => [
        'flaga' => '🇺🇸', 'nazwa_m' => 'Amerykanin', 'nazwa_f' => 'Amerykanka',
        'kraj' => 'USA', 'miasto_startowe' => 'NEW YORK',
        'kolor_akcent' => '#3c5c96',
        'opis' => 'Wychowani w kulturze, gdzie broń palna to chleb powszedni. Pierwszą strzelnicę pamiętają zanim zapamiętali elementarz.',
        'cecha' => 'Druga Poprawka',
        'opis_cechy' => 'W krwi mają strzelanie. Każda broń w ich rękach zyskuje dodatkową precyzję, szczególnie przeciwko opancerzonym celom.',
        'synergia' => 'Egzekutor',
        'bonusy_opis' => [
            '+10% obrażeń amunicją AP (przeciwpancerną) vs wrogowie Opancerzeni',
            '+1 Zręczność na start',
        ],
        'bonusy' => [
            'egzekutor_dmg_ap_vs_armor_mult' => 1.10,
        ],
        'atrybut_start' => 'zrecznosc',
        'rp' => [
            'opis_kulturowy' => 'Bezpośredni, pewny siebie, czasem do przesady. W The Abyss widzą go jako "kowboja" — czasem irytującego, czasem niezastąpionego. Ogólnie budzi mieszane uczucia.',
            'umiejetnosci_bonus_flat' => [
                'Walka Bronią Palną' => 2,
                'Zarządzanie i Przywództwo' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Etykieta i Dobre Manery' => -1,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇨🇳 CHIŃCZYK ═══════════════════════════ */
    'CHINY' => [
        'flaga' => '🇨🇳', 'nazwa_m' => 'Chińczyk', 'nazwa_f' => 'Chinka',
        'kraj' => 'CHINY', 'miasto_startowe' => 'SHANGHAI',
        'kolor_akcent' => '#ee1c25',
        'opis' => 'Pochodzą z ojczyzny światowej elektroniki i masowej inżynierii. Pięć tysięcy lat dokładności w genach.',
        'cecha' => 'Kultura Produkcji',
        'opis_cechy' => 'Precyzja w rękach i chłodna głowa. Każdy craft w warsztacie to rytuał, nie loteria.',
        'synergia' => 'Inżynier',
        'bonusy_opis' => [
            '−5 p.p. szansy na zepsucie sprzętu przy craftowaniu zaawansowanej broni/pancerzy',
            '+1 Inteligencja na start',
        ],
        'bonusy' => [
            'inzynier_craft_fail_mult' => 0.95,
        ],
        'atrybut_start' => 'inteligencja',
        'rp' => [
            'opis_kulturowy' => 'Cierpliwość i długa perspektywa. W sesjach nie pchają się na pierwszą linię — wolą obserwować, planować, uderzać wtedy, gdy sytuacja dojrzeje. Triady w Chinatown traktują ich jak swoich.',
            'umiejetnosci_bonus_flat' => [
                'Inżynieria Złomu' => 2,
                'Mechanika i Naprawa' => 2,
                'Matematyka i Rachunkowość' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Stand-up i Cięta Riposta' => -1,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 0, 'syndykat' => 2, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇮🇹 WŁOCH ══════════════════════════════ */
    'WLOCHY' => [
        'flaga' => '🇮🇹', 'nazwa_m' => 'Włoch', 'nazwa_f' => 'Włoszka',
        'kraj' => 'WLOCHY', 'miasto_startowe' => 'ROME',
        'kolor_akcent' => '#009246',
        'opis' => 'Wiedzą, z kim rozmawiać i jak ubijać interesy. "La Famiglia" to nie slogan, to system operacyjny.',
        'cecha' => 'La Famiglia',
        'opis_cechy' => 'Znajomości w każdym porcie i osobisty urok otwierają drzwi. Zawsze znajdzie się kuzyn, który da zniżkę.',
        'synergia' => 'Uniwersalna (ekonomia/transport)',
        'bonusy_opis' => [
            '−10% gotówki za każdy lot międzynarodowy',
            '−5% prowizji przy handlu między graczami',
            'Zwiększony limit Zalet/Wad: 8/8 (zamiast 7/7)',
        ],
        'bonusy' => [
            'lot_kasa_mult' => 0.90,
            'handel_prowizja_mult' => 0.95,
            'limit_cech' => 8,
        ],
        'atrybut_start' => null,
        'rp' => [
            'opis_kulturowy' => 'Urodzony dyplomata w złotych łańcuchach. W każdym syndykacie ma kuzyna, w każdym urzędzie wujka. Sesje z Włochami zawsze mają więcej rozmów niż strzelanin — bo po rozmowie strzelaniny już nie trzeba.',
            'umiejetnosci_bonus_flat' => [
                'Perswazja i Negocjacje' => 2,
                'Znajomość Półświatka' => 2,
                'Etykieta i Dobre Manery' => 1,
                'Sztuka Kulinarna i Gastronomia' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Zacieranie Śladów' => -1,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 3, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇦🇺 AUSTRALIJCZYK ══════════════════════ */
    'AUSTRALIA' => [
        'flaga' => '🇦🇺', 'nazwa_m' => 'Australijczyk', 'nazwa_f' => 'Australijka',
        'kraj' => 'AUSTRALIA', 'miasto_startowe' => 'SYDNEY',
        'kolor_akcent' => '#00247d',
        'opis' => 'Pochodzą z kontynentu, na którym każda roślina, zwierzę i pogoda chce cię zabić. Kto tam przetrwał, przetrwa wszędzie.',
        'cecha' => 'Zahartowany',
        'opis_cechy' => 'Odporność fizjologiczna na toksyny i biologiczne zagrożenia. Jady, kwasy, wirusy — ich organizm widział już wszystko.',
        'synergia' => 'Uniwersalna (przetrwanie/walka)',
        'bonusy_opis' => [
            '−15% obrażeń od potworów typu Biologiczny',
            '−15% obrażeń od ataków toksycznych (Tox)',
            '+1 Siła na start',
        ],
        'bonusy' => [
            'dmg_bio_redukcja_mult' => 0.85,
            'dmg_tox_redukcja_mult' => 0.85,
        ],
        'atrybut_start' => 'sila',
        'rp' => [
            'opis_kulturowy' => 'Luz, kumpelski, "no worries mate". W sesjach świetnie radzą sobie z zagrożeniami środowiskowymi i dzikimi potworami, ale na elitarnych salonach wyglądają jak słoń w salonie porcelany.',
            'umiejetnosci_bonus_flat' => [
                'Kondycja i Wytrzymałość' => 2,
                'Biologia i Botanika' => 1,
                'Medycyna Uliczna' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Etykieta i Dobre Manery' => -2,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 2, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇬🇧 BRYTYJCZYK ══════════════════════════ */
    'UK' => [
        'flaga' => '🇬🇧', 'nazwa_m' => 'Brytyjczyk', 'nazwa_f' => 'Brytyjka',
        'kraj' => 'UK', 'miasto_startowe' => 'LONDON',
        'kolor_akcent' => '#c8102e',
        'opis' => 'Kolonialne imperium nauczyło ich, jak prowadzić interesy w najciemniejszych zakamarkach świata. Zawsze z kieliszkiem whisky w dłoni i trzema asami w rękawie.',
        'cecha' => 'Stiff Upper Lip',
        'opis_cechy' => 'Chłód w spojrzeniu, kalkulacja w głosie. Nawet negocjując z zabójcą, mówią jak przy herbacie.',
        'synergia' => 'Uniwersalna (zlecenia/wywiad)',
        'bonusy_opis' => [
            '+10% nagród gotówkowych za zlecenia fabularne',
            '−5% kary za porażkę zlecenia',
            '+1 Inteligencja na start',
        ],
        'bonusy' => [
            'zlecenia_nagroda_mult' => 1.10,
            'zlecenia_kara_porazka_mult' => 0.95,
        ],
        'atrybut_start' => 'inteligencja',
        'rp' => [
            'opis_kulturowy' => 'Emocjonalna rezerwa i sardoniczny humor. Akceptowany w elitarnych klubach i administracji, lekko wywyższony wobec ulicy. W sesjach fabularnych mistrz zimnej analizy i dwuznacznych komentarzy.',
            'umiejetnosci_bonus_flat' => [
                'Etykieta i Dobre Manery' => 2,
                'Prawo i Administracja' => 1,
                'Analiza Danych i Dedukcja' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Sztuka Uwodzenia' => -1,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => -1, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇫🇷 FRANCUZ ════════════════════════════ */
    'FRANCJA' => [
        'flaga' => '🇫🇷', 'nazwa_m' => 'Francuz', 'nazwa_f' => 'Francuzka',
        'kraj' => 'FRANCJA', 'miasto_startowe' => 'PARIS',
        'kolor_akcent' => '#0055a4',
        'opis' => 'Każda broń musi być dziełem sztuki. Każdy pancerz — rzeźbą. Francuz nie tworzy — on kreuje.',
        'cecha' => 'Haute Couture',
        'opis_cechy' => 'Sztuka rzemieślnicza w genach. Ich produkty wyróżniają się w tłumie nie tylko statystykami, ale i stylem.',
        'synergia' => 'Inżynier (jakość/estetyka)',
        'bonusy_opis' => [
            '+5 p.p. jakości (bonus do statystyk) craftowanej broni i pancerza',
            '+10% ceny sprzedaży przedmiotów własnoręcznie stworzonych',
            '+1 Zręczność na start',
        ],
        'bonusy' => [
            'craft_jakosc_bonus_abs' => 5,
            'sprzedaz_wlasne_mult' => 1.10,
        ],
        'atrybut_start' => 'zrecznosc',
        'rp' => [
            'opis_kulturowy' => 'Eleganci urodzeni. W sesjach błyszczą na balach, w galeriach, w paryskich restauracjach. Brudna robota jest poniżej ich godności — ale flirt, manipulacja i gra spojrzeń? Mistrzostwo.',
            'umiejetnosci_bonus_flat' => [
                'Moda i Wizerunek' => 2,
                'Sztuka Uwodzenia' => 2,
                'Sztuka Kulinarna i Gastronomia' => 1,
                'Krawiectwo i Stylizacja' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Boks Uliczny' => -1,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇩🇪 NIEMIEC ════════════════════════════ */
    'NIEMCY' => [
        'flaga' => '🇩🇪', 'nazwa_m' => 'Niemiec', 'nazwa_f' => 'Niemka',
        'kraj' => 'NIEMCY', 'miasto_startowe' => 'BERLIN',
        'kolor_akcent' => '#ffcc00',
        'opis' => 'Systematyczność, precyzja, niemiecka jakość. Inżynieria to nie hobby — to religia.',
        'cecha' => 'Ordnung Muss Sein',
        'opis_cechy' => 'Porządek musi być. Każda procedura zoptymalizowana, każda operacja — bez strat energetycznych.',
        'synergia' => 'Inżynier (efektywność)',
        'bonusy_opis' => [
            'Craft w warsztacie kosztuje −10% Energii',
            '+10 p.p. szansy na bonusowy produkt przy craftowaniu (2 za cenę 1)',
            '+1 Inteligencja na start',
        ],
        'bonusy' => [
            'craft_energia_mult' => 0.90,
            'craft_bonus_produkt_szansa' => 10,
        ],
        'atrybut_start' => 'inteligencja',
        'rp' => [
            'opis_kulturowy' => 'Dokładność, punktualność, brak tolerancji dla bałaganu. W sesjach analitycznych są bezkonkurencyjni — każdy plan rozbity na kroki, każdy krok z rezerwą. Pokerem nie grają — grają w szachy.',
            'umiejetnosci_bonus_flat' => [
                'Mechanika i Naprawa' => 2,
                'Analiza Danych i Dedukcja' => 1,
                'Prawo i Administracja' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Sztuka Iluzji (Kuglarstwo)' => -1,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 3, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇯🇵 JAPOŃCZYK ══════════════════════════ */
    'JAPONIA' => [
        'flaga' => '🇯🇵', 'nazwa_m' => 'Japończyk', 'nazwa_f' => 'Japonka',
        'kraj' => 'JAPONIA', 'miasto_startowe' => 'TOKYO',
        'kolor_akcent' => '#bc002d',
        'opis' => 'Duch samuraja nigdy nie umarł — przeniósł się w neonowe zaułki Shinjuku. Katana rozcina polimer jak masło.',
        'cecha' => 'Bushidō',
        'opis_cechy' => 'Kodeks wojownika. Każdy cios bronią białą jest aktem rytualnym — i dlatego śmiertelnie precyzyjnym.',
        'synergia' => 'Egzekutor (broń biała)',
        'bonusy_opis' => [
            '+15% obrażeń bronią białą w Dokach',
            '+10 p.p. szansy na krytyk bronią białą',
            '+1 Zręczność na start',
        ],
        'bonusy' => [
            'egzekutor_dmg_melee_mult' => 1.15,
            'egzekutor_krytyk_melee_abs' => 10,
        ],
        'atrybut_start' => 'zrecznosc',
        'rp' => [
            'opis_kulturowy' => 'Honor ponad życie. W Yakuzie mają szacunek graniczący z kultem — ale poza japońskimi kręgami ich sztywność utrudnia relacje. Sesje z nimi są zimne, cięte, precyzyjne.',
            'umiejetnosci_bonus_flat' => [
                'Walka na Noże' => 2,
                'Etykieta i Dobre Manery' => 2,
                'Akrobatyka i Taniec' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Znajomość Półświatka' => -1,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 2, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇪🇸 HISZPAN ════════════════════════════ */
    'HISZPANIA' => [
        'flaga' => '🇪🇸', 'nazwa_m' => 'Hiszpan', 'nazwa_f' => 'Hiszpanka',
        'kraj' => 'HISZPANIA', 'miasto_startowe' => 'MADRID',
        'kolor_akcent' => '#f1bf00',
        'opis' => 'Korrida we krwi. Uderzają pierwsi, mocno, zdecydowanie. Kto się waha, ten już nie żyje.',
        'cecha' => 'La Furia Roja',
        'opis_cechy' => 'Czerwona furia. Pierwszy atak to koncert śmierci — po nim siła maleje, ale rzadko jest potrzebna.',
        'synergia' => 'Egzekutor (first strike)',
        'bonusy_opis' => [
            '+25% obrażeń w pierwszej turze walki',
            '+5 p.p. szansy na odzyskanie energii po zwycięskiej walce',
            '+1 Siła na start',
        ],
        'bonusy' => [
            'egzekutor_dmg_pierwsza_tura_mult' => 1.25,
            'egzekutor_energia_po_wygranej_szansa' => 5,
        ],
        'atrybut_start' => 'sila',
        'rp' => [
            'opis_kulturowy' => 'Dusza artysty i temperament torreadora. Sesje z Hiszpanem są gwałtowne, pełne pasji — i bardzo głośne. Fiesta, flamenco i wendeta potrafią się przeplatać w jeden wieczór.',
            'umiejetnosci_bonus_flat' => [
                'Sztuka Uwodzenia' => 2,
                'Wokal i Śpiew' => 1,
                'Akrobatyka i Taniec' => 1,
                'Boks Uliczny' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Analiza Danych i Dedukcja' => -1,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇷🇺 ROSJANIN ═══════════════════════════ */
    'ROSJA' => [
        'flaga' => '🇷🇺', 'nazwa_m' => 'Rosjanin', 'nazwa_f' => 'Rosjanka',
        'kraj' => 'ROSJA', 'miasto_startowe' => 'MOSCOW',
        'kolor_akcent' => '#d52b1e',
        'opis' => 'Urodzeni w mrozie Sybiru, trenowani w moskiewskim podziemiu. Trzeba dużo, żeby ich zatrzymać — a jeszcze więcej, żeby zabić.',
        'cecha' => 'Медведь (Niedźwiedź)',
        'opis_cechy' => 'Niedźwiedzia skóra, niedźwiedzia odporność. Ból to tylko informacja, nie sygnał do odwrotu.',
        'synergia' => 'Egzekutor (wytrzymałość)',
        'bonusy_opis' => [
            '−10% otrzymywanych obrażeń w walce wręcz',
            '+10% efektywności używek (alkohol, stymulanty) w Klubie',
            '+1 Wytrzymałość na start (+10 HP max)',
        ],
        'bonusy' => [
            'dmg_otrzymywanych_melee_mult' => 0.90,
            'uzywki_bonus_mult' => 1.10,
        ],
        'atrybut_start' => 'wytrzymalosc',
        'rp' => [
            'opis_kulturowy' => 'Gburowaci i niedostępni na pierwszy rzut oka — ale lojalni do końca dla tych, którzy zasłużyli. Rosyjska mafia traktuje ich jak swoich, korporacje boją się, a ulica respektuje.',
            'umiejetnosci_bonus_flat' => [
                'Zastraszanie' => 2,
                'Kondycja i Wytrzymałość' => 2,
                'Znajomość Półświatka' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Sztuka Uwodzenia' => -1,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 3, 'wladze' => -1, 'spoleczenstwo' => 0],
        ],
    ],

    /* ══════════════════ 🇧🇷 BRAZYLIJCZYK ═══════════════════════ */
    'BRAZYLIA' => [
        'flaga' => '🇧🇷', 'nazwa_m' => 'Brazylijczyk', 'nazwa_f' => 'Brazylijka',
        'kraj' => 'BRAZYLIA', 'miasto_startowe' => 'SAO PAULO',
        'kolor_akcent' => '#ffdf00',
        'opis' => 'Capoeira, samba, futbol — Brazylijczyk tańczy nawet w walce. Trudno go trafić, niemożliwe złapać.',
        'cecha' => 'Ginga',
        'opis_cechy' => 'Rytm w ciele od urodzenia. Każdy cios wroga omijają krokiem tanecznym.',
        'synergia' => 'Egzekutor (uniki)',
        'bonusy_opis' => [
            '+10 p.p. szansy na unik w walce',
            '+5 p.p. szansy na kontratak po udanym uniku',
            '+1 Zręczność na start',
        ],
        'bonusy' => [
            'unik_szansa_abs' => 10,
            'kontratak_po_uniku_szansa' => 5,
        ],
        'atrybut_start' => 'zrecznosc',
        'rp' => [
            'opis_kulturowy' => 'Otwartość, radość życia, powerhouse na imprezach. W sesjach wnoszą energię i nieprzewidywalność — ale przez luźność bywają niedoceniani przez poważnych graczy.',
            'umiejetnosci_bonus_flat' => [
                'Akrobatyka i Taniec' => 2,
                'Wokal i Śpiew' => 1,
                'Sztuka Uwodzenia' => 1,
                'Kondycja i Wytrzymałość' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Matematyka i Rachunkowość' => -1,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇲🇽 MEKSYKANIN ═════════════════════════ */
    'MEKSYK' => [
        'flaga' => '🇲🇽', 'nazwa_m' => 'Meksykanin', 'nazwa_f' => 'Meksykanka',
        'kraj' => 'MEKSYK', 'miasto_startowe' => 'MEXICO CITY',
        'kolor_akcent' => '#006847',
        'opis' => 'Siła rodziny, siła wspólnoty. Fiesta wciągnie cię w swój rytm — albo zmiażdży, jeśli staniesz na drodze.',
        'cecha' => 'La Raza',
        'opis_cechy' => 'Krew rodzinna ponad wszystko. Kontrakty od swoich NPCów dają więcej, a żołądek toleruje wszystko.',
        'synergia' => 'Uniwersalna (kontrakty/regeneracja)',
        'bonusy_opis' => [
            '+15% nagród za Kontrakty Klasowe',
            '+20 p.p. szansy na regenerację HP z jedzenia w mieszkaniu',
            '+1 Siła na start',
        ],
        'bonusy' => [
            'kontrakty_nagroda_mult' => 1.15,
            'regeneracja_hp_bonus_abs' => 20,
        ],
        'atrybut_start' => 'sila',
        'rp' => [
            'opis_kulturowy' => 'Rodzina ponad wszystko, potem tequila, potem reszta. Kartel traktuje ich z szacunkiem (albo ze strachem), a społeczność latynoska w każdym mieście to natychmiastowa sieć kontaktów.',
            'umiejetnosci_bonus_flat' => [
                'Sztuka Kulinarna i Gastronomia' => 2,
                'Znajomość Półświatka' => 2,
                'Kondycja i Wytrzymałość' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Etykieta i Dobre Manery' => -1,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 2, 'syndykat' => 2, 'wladze' => -1, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇦🇪 EMIRATCZYK ═════════════════════════ */
    'ZEA' => [
        'flaga' => '🇦🇪', 'nazwa_m' => 'Emiratczyk', 'nazwa_f' => 'Emiratka',
        'kraj' => 'ZEA', 'miasto_startowe' => 'DUBAI',
        'kolor_akcent' => '#00732f',
        'opis' => 'Ropa rządziła — teraz rządzą dane. Rodzina rządząca emiratem ma konta w tuzinach syndykatów, a portfele grubsze niż większość banków.',
        'cecha' => 'Złota Klątwa',
        'opis_cechy' => 'Fortune przy urodzeniu. Startują z kapitałem, a odsetki bankowe rosną im szybciej niż komukolwiek.',
        'synergia' => 'Uniwersalna (bogactwo)',
        'bonusy_opis' => [
            'Start z 3× większą gotówką niż standardowo',
            '+25% odsetek w Banku Centralnym',
            '+1 Inteligencja na start',
        ],
        'bonusy' => [
            'bank_odsetki_mult' => 1.25,
        ],
        'start_kasa_mult' => 3.0,
        'atrybut_start' => 'inteligencja',
        'rp' => [
            'opis_kulturowy' => 'Arystokracja XXI wieku. Otwierają każde drzwi — pod warunkiem, że mają w portfelu odpowiednią sumę. Sesje RP z nimi to zawsze ballroom, helikopter albo prywatny jacht.',
            'umiejetnosci_bonus_flat' => [
                'Handel i Wycena' => 2,
                'Matematyka i Rachunkowość' => 2,
                'Etykieta i Dobre Manery' => 1,
                'Prawo i Administracja' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Walka Bronią Palną' => -1,
            ],
            'reputacja' => ['elita' => 4, 'ulica' => -2, 'syndykat' => 2, 'wladze' => 2, 'spoleczenstwo' => 0],
        ],
    ],

    /* ══════════════════ 🇮🇳 HINDUS ═════════════════════════════ */
    'INDIE' => [
        'flaga' => '🇮🇳', 'nazwa_m' => 'Hindus', 'nazwa_f' => 'Hinduska',
        'kraj' => 'INDIE', 'miasto_startowe' => 'MUMBAI',
        'kolor_akcent' => '#ff9933',
        'opis' => 'Karma, reinkarnacja, jasne oczy pod warstwą smogu. Każda lekcja to krok ku Mokszy — również w krwi Nowego Delhi.',
        'cecha' => 'Dharma',
        'opis_cechy' => 'Duchowa perspektywa. Patrząc długodystansowo, każde doświadczenie jest cenne.',
        'synergia' => 'Uniwersalna (doświadczenie/nauka)',
        'bonusy_opis' => [
            '+10% EXP z każdego źródła',
            '−10% kosztu wykładów na Uniwersytecie',
            '+1 Inteligencja na start',
        ],
        'bonusy' => [
            'exp_mult' => 1.10,
            'uniwersytet_koszt_mult' => 0.90,
        ],
        'atrybut_start' => 'inteligencja',
        'rp' => [
            'opis_kulturowy' => 'Spokojni mędrcy w chaosie ulicy. Dobrzy doradcy, doskonali negocjatorzy — rzadko tracą panowanie nad emocjami. Elity szanują ich intelekt, ulica szanuje ich spokój.',
            'umiejetnosci_bonus_flat' => [
                'Psychologia i Empatia' => 2,
                'Wiedza Ogólna i Pedagogika' => 2,
                'Chemia i Farmakologia' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Zastraszanie' => -1,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇨🇦 KANADYJCZYK ════════════════════════ */
    'KANADA' => [
        'flaga' => '🇨🇦', 'nazwa_m' => 'Kanadyjczyk', 'nazwa_f' => 'Kanadyjka',
        'kraj' => 'KANADA', 'miasto_startowe' => 'TORONTO',
        'kolor_akcent' => '#ff0000',
        'opis' => 'Uprzejmość jako broń. Nikt nie podejrzewa Kanadyjczyka — a on już dawno wynegocjował rabat na twoje życie.',
        'cecha' => 'Maple Diplomacy',
        'opis_cechy' => 'Uśmiech i "sorry" otwiera drzwi, których żaden lewarek nie dotknie.',
        'synergia' => 'Uniwersalna (handel NPC)',
        'bonusy_opis' => [
            '−10% cen u sprzedawców NPC',
            '+15% szybszego wzrostu reputacji w dzielnicach Złomowiska',
            '+1 Zręczność na start',
        ],
        'bonusy' => [
            'sklep_npc_koszt_mult' => 0.90,
            'reputacja_dzielnic_mult' => 1.15,
        ],
        'atrybut_start' => 'zrecznosc',
        'rp' => [
            'opis_kulturowy' => 'Zawsze mili, zawsze uprzejmi — i dlatego zawsze niedoceniani. W sesjach są mistrzami dyskretnego załatwiania spraw bez przemocy. Nikt ich nie podejrzewa, aż jest za późno.',
            'umiejetnosci_bonus_flat' => [
                'Perswazja i Negocjacje' => 2,
                'Handel i Wycena' => 1,
                'Psychologia i Empatia' => 1,
                'Etykieta i Dobre Manery' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Zastraszanie' => -1,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    /* ══════════════════ 🇸🇪 SZWED ══════════════════════════════ */
    'SZWECJA' => [
        'flaga' => '🇸🇪', 'nazwa_m' => 'Szwed', 'nazwa_f' => 'Szwedka',
        'kraj' => 'SZWECJA', 'miasto_startowe' => 'STOCKHOLM',
        'kolor_akcent' => '#fecc00',
        'opis' => 'Umiar i precyzja. Szwecja to kraj, gdzie wszystko działa — łącznie z tobą.',
        'cecha' => 'Lagom',
        'opis_cechy' => 'Nie za mało, nie za dużo — w sam raz. Skandynawska dyscyplina.',
        'synergia' => 'Inżynier (efektywność)',
        'bonusy_opis' => [
            '+3 bonusowej regeneracji Energii na godzinę',
            '+5% trwałości narzędzi Szabrownika',
            '+1 Wytrzymałość na start (+10 HP max)',
        ],
        'bonusy' => [
            'regeneracja_en_bonus_abs' => 3,
            'narzedzia_trwalosc_mult' => 1.05,
        ],
        'atrybut_start' => 'wytrzymalosc',
        'rp' => [
            'opis_kulturowy' => 'Chłodni, sprawiedliwi, technologicznie zaawansowani. Idealni mediatorzy — neutralność skandynawska jest znana na całym świecie. Brak emocji bywa postrzegany jako zimno.',
            'umiejetnosci_bonus_flat' => [
                'Analiza Danych i Dedukcja' => 2,
                'Mechanika i Naprawa' => 1,
                'Wiedza Ogólna i Pedagogika' => 1,
                'Etykieta i Dobre Manery' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Stand-up i Cięta Riposta' => -1,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇳🇴 NORWEG ═════════════════════════════ */
    'NORWEGIA' => [
        'flaga' => '🇳🇴', 'nazwa_m' => 'Norweg', 'nazwa_f' => 'Norweżka',
        'kraj' => 'NORWEGIA', 'miasto_startowe' => 'OSLO',
        'kolor_akcent' => '#002868',
        'opis' => 'Fjordy, wiatr, zimna krew. Norweg nie boi się promieniowania — bał się już gorszych rzeczy.',
        'cecha' => 'Friluftsliv',
        'opis_cechy' => 'Życie na zewnątrz. Ekstremalne warunki to ich naturalne środowisko.',
        'synergia' => 'Szabrownik (ekstremalne warunki)',
        'bonusy_opis' => [
            '+15 p.p. szansy na znalezienie surowców w dzielnicach skażonych',
            'Brak kary za brak Hazmatu w Queens Hospital / Wall Street Ruins',
            '+1 Wytrzymałość na start (+10 HP max)',
        ],
        'bonusy' => [
            'szabrowanie_skazone_szansa_abs' => 15,
            'skazone_brak_hazmat_ok' => 1,
        ],
        'atrybut_start' => 'wytrzymalosc',
        'rp' => [
            'opis_kulturowy' => 'Zamknięci, ale niezłomni. Nie zabłysną w rozmowie, ale za to poprowadzą wyprawę przez skażone strefy, gdzie inni padają z wyczerpania. Trekking w Himalajach? Zwykła niedzielna przechadzka.',
            'umiejetnosci_bonus_flat' => [
                'Topografia i Nawigacja' => 2,
                'Kondycja i Wytrzymałość' => 2,
                'Biologia i Botanika' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Sztuka Uwodzenia' => -1,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════ 🇩🇰 DUŃCZYK ═══════════════════════════ */
    'DANIA' => [
        'flaga' => '🇩🇰', 'nazwa_m' => 'Duńczyk', 'nazwa_f' => 'Dunka',
        'kraj' => 'DANIA', 'miasto_startowe' => 'COPENHAGEN',
        'kolor_akcent' => '#c8102e',
        'opis' => 'Hygge to sztuka znalezienia komfortu w każdej sytuacji. Duńczyk przekształci nawet motel w dom.',
        'cecha' => 'Hygge',
        'opis_cechy' => 'Dom to świątynia regeneracji. Każda godzina w mieszkaniu odnawia więcej energii niż innym.',
        'synergia' => 'Uniwersalna (mieszkanie)',
        'bonusy_opis' => [
            '+50% bonusu energii ze spania w mieszkaniu',
            '+1 dodatkowy slot na lokatora w mieszkaniu',
            '+1 Wytrzymałość na start (+10 HP max)',
        ],
        'bonusy' => [
            'mieszkanie_sen_bonus_mult' => 1.50,
            'mieszkanie_lokator_slot_bonus' => 1,
        ],
        'atrybut_start' => 'wytrzymalosc',
        'rp' => [
            'opis_kulturowy' => 'Najszczęśliwszy naród świata — i to widać. W sesjach rozluźniają atmosferę, budują relacje, tworzą "bezpieczne miejsce" dla drużyny. Przy stole negocjacyjnym sprawiają, że wszyscy się dogadują.',
            'umiejetnosci_bonus_flat' => [
                'Psychologia i Empatia' => 2,
                'Sztuka Kulinarna i Gastronomia' => 1,
                'Literatura i Twórcze Pisanie' => 1,
                'Etykieta i Dobre Manery' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Zastraszanie' => -1,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    /* ══════════════════ 🇨🇿 CZECH ══════════════════════════════ */
    'CZECHY' => [
        'flaga' => '🇨🇿', 'nazwa_m' => 'Czech', 'nazwa_f' => 'Czeszka',
        'kraj' => 'CZECHY', 'miasto_startowe' => 'PRAGUE',
        'kolor_akcent' => '#11457e',
        'opis' => 'Cyniczny humor i nieograniczona zdolność wchłonięcia piwa. Kto nie bierze się za poważnie, zawsze wygrywa długoterminowo.',
        'cecha' => 'Szwejk',
        'opis_cechy' => 'Głupek o orlim umyśle. Pod maską uprzejmego idioty kryje się zimny strateg.',
        'synergia' => 'Szabrownik (ukrywanie)',
        'bonusy_opis' => [
            '−20% szansy na wykrycie przez Patrol NYPD podczas szabrowania',
            '+10% nagród z eventów losowych na Złomowisku',
            '+1 Siła na start',
        ],
        'bonusy' => [
            'event_patrol_szansa_mult' => 0.80,
            'event_nagrody_mult' => 1.10,
        ],
        'atrybut_start' => 'sila',
        'rp' => [
            'opis_kulturowy' => 'Zawsze z kuflem i anegdotą. W sesjach wnoszą humor, który rozładowuje napięcia — ale za cynicznym śmiechem ukrywa się ostra obserwacja. Ludzie ich nie doceniają, a potem nagle odkrywają, że zostali wykiwani.',
            'umiejetnosci_bonus_flat' => [
                'Stand-up i Cięta Riposta' => 2,
                'Skradanie' => 2,
                'Psychologia i Empatia' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Zarządzanie i Przywództwo' => -1,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════ 🇧🇪 BELG ═══════════════════════════════ */
    'BELGIA' => [
        'flaga' => '🇧🇪', 'nazwa_m' => 'Belg', 'nazwa_f' => 'Belgijka',
        'kraj' => 'BELGIA', 'miasto_startowe' => 'BRUSSELS',
        'kolor_akcent' => '#fdda24',
        'opis' => 'Antwerpia handlowała diamentami od wieków. Belg wie, gdzie kupić, gdzie sprzedać, komu się kłaniać.',
        'cecha' => 'Port Czarnych Diamentów',
        'opis_cechy' => 'Handlarz urodzony. Na Czarnym Rynku porusza się jak ryba w wodzie.',
        'synergia' => 'Uniwersalna (Czarny Rynek)',
        'bonusy_opis' => [
            '−10% prowizji na Czarnym Rynku',
            '+5% cen sprzedaży na Czarnym Rynku',
            '+1 Inteligencja na start',
        ],
        'bonusy' => [
            'rynek_prowizja_mult' => 0.90,
            'rynek_cena_sprzedazy_mult' => 1.05,
        ],
        'atrybut_start' => 'inteligencja',
        'rp' => [
            'opis_kulturowy' => 'Dyplomaci ukryci pod maską handlarzy. Rozmawiają w pięciu językach, mają kontakty w każdym porcie, zawsze znają kogoś "kto może pomóc". Dyskretni i skuteczni.',
            'umiejetnosci_bonus_flat' => [
                'Handel i Wycena' => 2,
                'Znajomość Półświatka' => 2,
                'Prawo i Administracja' => 1,
            ],
            'umiejetnosci_kara_flat' => [
                'Zastraszanie' => -1,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 2, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],
];


/* ═══════════════════════════════════════════════════════════════════════
   HELPERY MECHANICZNE (bez zmian — używane w walce, craftingu itd.)
   ═══════════════════════════════════════════════════════════════════════ */

function pochodzenie_bonus($gracz, $klucz, $default = 1.0) {
    global $POCHODZENIA_DANE;
    $poch = $gracz['pochodzenie'] ?? null;
    if (!$poch || !isset($POCHODZENIA_DANE[$poch]['bonusy'][$klucz])) return $default;
    return $POCHODZENIA_DANE[$poch]['bonusy'][$klucz];
}

function ma_pochodzenie($gracz, $pochodzenie) {
    return ($gracz['pochodzenie'] ?? null) === strtoupper($pochodzenie);
}

function pochodzenie_dane($pochodzenie) {
    global $POCHODZENIA_DANE;
    if (!$pochodzenie) return null;
    return $POCHODZENIA_DANE[strtoupper($pochodzenie)] ?? null;
}