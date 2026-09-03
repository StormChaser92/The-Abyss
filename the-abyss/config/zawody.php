<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — KATALOG ZAWODÓW (~72 profesje fabularne)

   Struktura wpisu:
   - opis            → krótki opis zawodu (do karta.php)
   - wymagania       → minimalne poziomy umiejętności (PU)
   - wymagany_tytul  → null lub nazwa tytułu naukowego
   - kategoria       → grupa tematyczna (dla filtrów i przyszłych funkcji)
   - rp              → warstwa fabularna:
       → opis_spoleczny           → jak społeczeństwo postrzega gracza
       → umiejetnosci_bonus_proc  → % bonusów do umiejętności kluczowych
                                    (mnożnik w sesjach RP)
       → reputacja                → bonus do 5 grup (elita/ulica/syndykat/
                                    wladze/spoleczenstwo)

   Reputacja: skala −5 (strach/pogarda) ... 0 (neutralnie) ... +5 (szacunek)

   Nowe tytuły naukowe (po migracji):
   - Lekarz Weterynarii   (Weterynaria)
   - Magister Farmacji    (Farmacja)
   - Magister Historii    (Historia i Archeologia)
   - Pilot Liniowy        (Lotnictwo Cywilne)
   ═══════════════════════════════════════════════════════════════════════ */

$ZAWODY_DANE = [

    /* ══════════════════════ HANDEL I USŁUGI ════════════════════════ */

    'Sprzedawca' => [
        'opis' => 'Uśmiechasz się do klientów, wciskając im drogi towar.',
        'wymagania' => ['Handel i Wycena' => 5, 'Perswazja i Negocjacje' => 4, 'Matematyka i Rachunkowość' => 2, 'Obsługa Klienta' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'handel',
        'rp' => [
            'opis_spoleczny' => 'Przeciętny obywatel widzi Cię codziennie — znają Cię za ladą i ufają. Bywalcy mogą liczyć na rabaty pod stołem.',
            'umiejetnosci_bonus_proc' => [
                'Handel i Wycena' => 25,
                'Perswazja i Negocjacje' => 15,
                'Matematyka i Rachunkowość' => 10,
                'Obsługa Klienta' => 15,
                'Moda i Wizerunek' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    'Barman' => [
        'opis' => 'Nikt nie zadaje pytań, każdy chce się napić.',
        'wymagania' => ['Perswazja i Negocjacje' => 5, 'Znajomość Półświatka' => 4, 'Psychologia i Empatia' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'handel',
        'rp' => [
            'opis_spoleczny' => 'Wszyscy Cię znają — od prostytutek do bossów mafii. Słyszysz wszystko i pamiętasz wszystko. Neutralny teren, ale z potężną siecią kontaktów.',
            'umiejetnosci_bonus_proc' => [
                'Perswazja i Negocjacje' => 20,
                'Znajomość Półświatka' => 15,
                'Psychologia i Empatia' => 15,
                'Sztuka Kulinarna i Gastronomia' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 2, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Kelner' => [
        'opis' => 'Roznosisz drinki, idealne stanowisko do podsłuchiwania.',
        'wymagania' => ['Sztuka Kulinarna i Gastronomia' => 6, 'Etykieta i Dobre Manery' => 5, 'Perswazja i Negocjacje' => 4, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'handel',
        'rp' => [
            'opis_spoleczny' => 'Niewidzialny dla gości — co czyni Cię idealnym obserwatorem. Elita Cię ignoruje, a Ty zapamiętujesz każde słowo przy ich stoliku.',
            'umiejetnosci_bonus_proc' => [
                'Sztuka Kulinarna i Gastronomia' => 15,
                'Etykieta i Dobre Manery' => 15,
                'Perswazja i Negocjacje' => 10,
                'Obsługa Klienta' => 10,
                'Akrobatyka i Taniec' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Szef Kuchni' => [
        'opis' => 'Karmisz elitę The Abyss. Twoje noże są zawsze ostre.',
        'wymagania' => ['Sztuka Kulinarna i Gastronomia' => 10, 'Walka na Noże' => 5, 'Zarządzanie i Przywództwo' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'handel',
        'rp' => [
            'opis_spoleczny' => 'Szanowany w kręgach elitarnych — lista rezerwacji to lista VIP-ów. Twoje kuchnie kryją tajemnice, Twoje noże opowiadają historie.',
            'umiejetnosci_bonus_proc' => [
                'Sztuka Kulinarna i Gastronomia' => 30,
                'Walka na Noże' => 10,
                'Zarządzanie i Przywództwo' => 10,
                'Handel i Wycena' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    'Krawiec' => [
        'opis' => 'Szyjesz garnitury dla bossów mafii i łatasz pancerze.',
        'wymagania' => ['Krawiectwo i Stylizacja' => 7, 'Moda i Wizerunek' => 5, 'Handel i Wycena' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Robisz garnitury dla bossów mafii i kieratusy dla egzekutorów. Syndykaty Cię cenią — dobry krawiec zna mierzyny każdego kluczowego gracza.',
            'umiejetnosci_bonus_proc' => [
                'Krawiectwo i Stylizacja' => 30,
                'Moda i Wizerunek' => 20,
                'Handel i Wycena' => 10,
                'Sztuki Plastyczne i Rzemiosło' => 5,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 3, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Hydraulik' => [
        'opis' => 'Bez Ciebie to miasto utonęłoby we własnych ściekach.',
        'wymagania' => ['Mechanika i Naprawa' => 8, 'Inżynieria Złomu' => 5, 'Kondycja i Wytrzymałość' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Niezbędny dla wszystkich, ignorowany przez wszystkich. Masz dostęp do kanałów, rur i piwnic, o których elita nie ma pojęcia.',
            'umiejetnosci_bonus_proc' => [
                'Mechanika i Naprawa' => 25,
                'Inżynieria Złomu' => 15,
                'Kondycja i Wytrzymałość' => 10,
                'Architektura i Konstrukcje' => 5,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Tatuażysta' => [
        'opis' => 'Dziarasz symbole gangów.',
        'wymagania' => ['Sztuki Plastyczne i Rzemiosło' => 8, 'Medycyna Uliczna' => 4, 'Znajomość Półświatka' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Każdy członek gangu ma Twój symbol na skórze. Wiesz, kto do którego syndykatu należy — i czasem to niebezpieczna wiedza.',
            'umiejetnosci_bonus_proc' => [
                'Sztuki Plastyczne i Rzemiosło' => 25,
                'Medycyna Uliczna' => 15,
                'Znajomość Półświatka' => 10,
                'Moda i Wizerunek' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 3, 'syndykat' => 2, 'wladze' => -1, 'spoleczenstwo' => 0],
        ],
    ],

    'Mechanik Samochodowy' => [
        'opis' => 'Reperujesz bryki — od taksówek do wyścigówek syndykatu.',
        'wymagania' => ['Mechanika i Naprawa' => 10, 'Prowadzenie Pojazdów' => 5, 'Inżynieria Złomu' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Warsztat jest Twoim królestwem. Klienci zostawiają Ci wozy i tajemnice — dyskretna naprawa uszkodzeń po strzelaninie kosztuje dodatkowo.',
            'umiejetnosci_bonus_proc' => [
                'Mechanika i Naprawa' => 30,
                'Prowadzenie Pojazdów' => 15,
                'Inżynieria Złomu' => 10,
                'Elektronika' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 2, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Ślusarz' => [
        'opis' => 'Otwierasz zatrzaśnięte drzwi i dorabiasz klucze. Legalnie.',
        'wymagania' => ['Otwieranie Zamków (Wytrychy)' => 8, 'Mechanika i Naprawa' => 6, 'Sztuki Plastyczne i Rzemiosło' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Legalny fach z dziwną reputacją — ludzie wiedzą, że mógłbyś otworzyć każde drzwi. Policja czasem Cię wzywa, syndykaty czasem oferują lepszą stawkę.',
            'umiejetnosci_bonus_proc' => [
                'Otwieranie Zamków (Wytrychy)' => 30,
                'Mechanika i Naprawa' => 15,
                'Sztuki Plastyczne i Rzemiosło' => 10,
                'Elektronika' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    'Stylista / Fryzjer' => [
        'opis' => 'Tworzysz wizerunki celebrytek i polityków.',
        'wymagania' => ['Moda i Wizerunek' => 8, 'Sztuki Plastyczne i Rzemiosło' => 5, 'Psychologia i Empatia' => 4, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Klientki opowiadają Ci sekrety jak kapłanowi. Wiesz, kto śpi z kim, kto się rozwodzi i kto ma problemy finansowe — wszystko przy myciu głowy.',
            'umiejetnosci_bonus_proc' => [
                'Moda i Wizerunek' => 25,
                'Sztuki Plastyczne i Rzemiosło' => 15,
                'Psychologia i Empatia' => 15,
                'Obsługa Klienta' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Kosmetyczka' => [
        'opis' => 'Manicure, pedicure, zabiegi — gabinet pełen plotek.',
        'wymagania' => ['Moda i Wizerunek' => 6, 'Psychologia i Empatia' => 5, 'Obsługa Klienta' => 4, 'Sztuki Plastyczne i Rzemiosło' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'W Twoim gabinecie klientki zdejmują maski — dosłownie i w przenośni. Zaufana doradczyni spraw sercowych i źródło plotek o całej dzielnicy.',
            'umiejetnosci_bonus_proc' => [
                'Moda i Wizerunek' => 20,
                'Psychologia i Empatia' => 15,
                'Obsługa Klienta' => 15,
                'Sztuki Plastyczne i Rzemiosło' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ TRANSPORT ══════════════════════════════ */

    'Taksówkarz' => [
        'opis' => 'Nocny kierowca. Znasz każdą dziurę w mieście.',
        'wymagania' => ['Prowadzenie Pojazdów' => 8, 'Topografia i Nawigacja' => 6, 'Etykieta i Dobre Manery' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'transport',
        'rp' => [
            'opis_spoleczny' => 'Pasażerowie mówią przy Tobie wszystko — jesteś tłem. Znasz najszybsze trasy, ale też sekrety miasta, których nie znajdziesz na mapie.',
            'umiejetnosci_bonus_proc' => [
                'Prowadzenie Pojazdów' => 20,
                'Topografia i Nawigacja' => 20,
                'Znajomość Półświatka' => 10,
                'Etykieta i Dobre Manery' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Kierowca Tira' => [
        'opis' => 'Przemierzasz pustkowia dostarczając ciężki towar.',
        'wymagania' => ['Prowadzenie Pojazdów' => 10, 'Mechanika i Naprawa' => 6, 'Kondycja i Wytrzymałość' => 8],
        'wymagany_tytul' => null,
        'kategoria' => 'transport',
        'rp' => [
            'opis_spoleczny' => 'Samotnik na drodze. Dalekie trasy, zmęczone oczy, radio zamiast towarzystwa. Przewozisz wszystko — również rzeczy, o które lepiej nie pytać.',
            'umiejetnosci_bonus_proc' => [
                'Prowadzenie Pojazdów' => 30,
                'Mechanika i Naprawa' => 15,
                'Kondycja i Wytrzymałość' => 15,
                'Topografia i Nawigacja' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Spedytor' => [
        'opis' => 'Zarządzasz flotą i logistyką w całym mieście.',
        'wymagania' => ['Zarządzanie i Przywództwo' => 8, 'Handel i Wycena' => 8, 'Analiza Danych i Dedukcja' => 6, 'Prowadzenie Pojazdów' => 5],
        'wymagany_tytul' => 'Magister Ekonomii',
        'kategoria' => 'transport',
        'rp' => [
            'opis_spoleczny' => 'Koordynujesz dostawy dla korporacji i czasem dla syndykatów. Masz biuro, asystentkę i armię kierowców — ale i potężne zobowiązania.',
            'umiejetnosci_bonus_proc' => [
                'Zarządzanie i Przywództwo' => 20,
                'Handel i Wycena' => 20,
                'Analiza Danych i Dedukcja' => 15,
                'Prowadzenie Pojazdów' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 2, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],

    'Pilot Liniowy' => [
        'opis' => 'Latasz rejsami z VIP-ami nad chmurami The Abyss.',
        'wymagania' => ['Pilotaż' => 12, 'Mechanika i Naprawa' => 6, 'Topografia i Nawigacja' => 8, 'Zimna Krew podczas kryzysu' => 0, 'Elektronika' => 4],
        'wymagany_tytul' => 'Pilot Liniowy',
        'kategoria' => 'transport',
        'rp' => [
            'opis_spoleczny' => 'Srebrny mundur, złote skrzydła na piersi. Pasażerowie widzą w Tobie autorytet, kapitanowie lotnisk — równego sobie. Niejedna tajemnica korporacji lata z Tobą na pokładzie.',
            'umiejetnosci_bonus_proc' => [
                'Pilotaż' => 35,
                'Topografia i Nawigacja' => 20,
                'Mechanika i Naprawa' => 10,
                'Elektronika' => 10,
                'Zimna Krew' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Kurier Motocyklowy' => [
        'opis' => 'Przemykasz przez korki z paczką przypiętą do pleców.',
        'wymagania' => ['Prowadzenie Pojazdów' => 8, 'Topografia i Nawigacja' => 6, 'Kondycja i Wytrzymałość' => 5, 'Parkour i Freerunning' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'transport',
        'rp' => [
            'opis_spoleczny' => 'Kropka na mapie, która przemieszcza się szybciej niż każdy inny wóz w mieście. Klientela od pizzy do dokumentów sądowych — wszystko na czas.',
            'umiejetnosci_bonus_proc' => [
                'Prowadzenie Pojazdów' => 25,
                'Topografia i Nawigacja' => 20,
                'Kondycja i Wytrzymałość' => 10,
                'Parkour i Freerunning' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ SZTUKA I MEDIA ═════════════════════════ */

    'Malarz' => [
        'opis' => 'Przelewasz mrok The Abyss na płótna.',
        'wymagania' => ['Sztuki Plastyczne i Rzemiosło' => 10, 'Moda i Wizerunek' => 5, 'Wiedza Ogólna i Pedagogika' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Twoje obrazy wiszą w galeriach i penthausach. Elita ceni, krytycy analizują, ulica zazdrości — a Ty wiesz, że prawdziwy ból jest w kolorach, nie w słowach.',
            'umiejetnosci_bonus_proc' => [
                'Sztuki Plastyczne i Rzemiosło' => 30,
                'Moda i Wizerunek' => 15,
                'Wiedza Ogólna i Pedagogika' => 5,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Pisarz' => [
        'opis' => 'Tworzysz bestsellery w wynajętej kawalerce.',
        'wymagania' => ['Literatura i Twórcze Pisanie' => 10, 'Analiza Danych i Dedukcja' => 5, 'Psychologia i Empatia' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Twoje książki czyta całe miasto. Obserwujesz ludzi, wyciągasz z nich historie, które sami przed sobą ukrywali. Inteligentna i niebezpieczna umiejętność.',
            'umiejetnosci_bonus_proc' => [
                'Literatura i Twórcze Pisanie' => 30,
                'Analiza Danych i Dedukcja' => 10,
                'Psychologia i Empatia' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Dziennikarz Śledczy' => [
        'opis' => 'Szukasz prawdy tam, gdzie inni boją się wejść.',
        'wymagania' => ['Literatura i Twórcze Pisanie' => 8, 'Analiza Danych i Dedukcja' => 8, 'Perswazja i Negocjacje' => 6, 'Zacieranie Śladów' => 4],
        'wymagany_tytul' => 'Magister Edukacji',
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Dla władz jesteś bohaterem — albo zagrożeniem. Dla syndykatów jesteś celem. Twoje artykuły potrafią obalić polityków i zdemaskować mafie, ale pierwszy nóż zawsze idzie w Twoje plecy.',
            'umiejetnosci_bonus_proc' => [
                'Literatura i Twórcze Pisanie' => 20,
                'Analiza Danych i Dedukcja' => 20,
                'Perswazja i Negocjacje' => 15,
                'Zacieranie Śladów' => 10,
                'Skradanie' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 1, 'syndykat' => -3, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Fotograf' => [
        'opis' => 'Łapasz chwile, które inni chcieliby zapomnieć.',
        'wymagania' => ['Fotografia' => 10, 'Sztuki Plastyczne i Rzemiosło' => 6, 'Moda i Wizerunek' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Zdjęcia z Twojego aparatu trafiają na okładki magazynów i do teczek szantażystów. Jedno kliknięcie migawki w niewłaściwym momencie — i życie celebrytki się wali.',
            'umiejetnosci_bonus_proc' => [
                'Fotografia' => 30,
                'Sztuki Plastyczne i Rzemiosło' => 15,
                'Moda i Wizerunek' => 10,
                'Skradanie' => 5,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Fotoreporter' => [
        'opis' => 'Kadr z pola walki ulicznej warty więcej niż tysiąc słów.',
        'wymagania' => ['Fotografia' => 10, 'Literatura i Twórcze Pisanie' => 6, 'Analiza Danych i Dedukcja' => 5, 'Skradanie' => 4, 'Kondycja i Wytrzymałość' => 4],
        'wymagany_tytul' => 'Magister Edukacji',
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Jesteś tam, gdzie dzieje się historia — strzelaniny, zamieszki, tajne spotkania. Twoje zdjęcia pokazują prawdę, której politycy by woleli nie widzieć.',
            'umiejetnosci_bonus_proc' => [
                'Fotografia' => 25,
                'Literatura i Twórcze Pisanie' => 15,
                'Analiza Danych i Dedukcja' => 15,
                'Skradanie' => 10,
                'Kondycja i Wytrzymałość' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => -2, 'wladze' => 1, 'spoleczenstwo' => 3],
        ],
    ],

    'Aktor' => [
        'opis' => 'Błyszczysz w świetle reflektorów lokalnego teatru.',
        'wymagania' => ['Aktorstwo i Charakteryzacja' => 10, 'Moda i Wizerunek' => 6, 'Perswazja i Negocjacje' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Fani Cię uwielbiają, bulwarowe gazety piszą kłamstwa, elita zaprasza Cię do salonów. Twoja twarz jest rozpoznawalna — co w świecie The Abyss bywa błogosławieństwem i przekleństwem.',
            'umiejetnosci_bonus_proc' => [
                'Aktorstwo i Charakteryzacja' => 30,
                'Moda i Wizerunek' => 15,
                'Perswazja i Negocjacje' => 10,
                'Wokal i Śpiew' => 5,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 3],
        ],
    ],

    'Muzyk' => [
        'opis' => 'Grasz w zadymionych pubach lub studiach nagraniowych.',
        'wymagania' => ['Wokal i Śpiew' => 8, 'DJing i Instrumenty' => 8, 'Moda i Wizerunek' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Twoja muzyka jest w każdej słuchawce miasta. Kluby chcą Cię na scenie, fani chcą autografów, paparazzi chcą skandalu. Sława kosztuje, ale też otwiera każde drzwi.',
            'umiejetnosci_bonus_proc' => [
                'Wokal i Śpiew' => 20,
                'DJing i Instrumenty' => 20,
                'Moda i Wizerunek' => 10,
                'Stand-up i Cięta Riposta' => 5,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 3],
        ],
    ],

    'Tancerz / Tancerka' => [
        'opis' => 'Zarabiasz ciałem i rytmem na parkietach miasta.',
        'wymagania' => ['Akrobatyka i Taniec' => 9, 'Kondycja i Wytrzymałość' => 6, 'Moda i Wizerunek' => 5, 'Sztuka Uwodzenia' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Twoje ciało to instrument. Wystąpienia na Broadway, w klubach, w prywatnych salonach elity. Znajomi z każdej sfery — ale nikt nie zna Cię naprawdę.',
            'umiejetnosci_bonus_proc' => [
                'Akrobatyka i Taniec' => 25,
                'Kondycja i Wytrzymałość' => 15,
                'Moda i Wizerunek' => 10,
                'Sztuka Uwodzenia' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Projektant Mody' => [
        'opis' => 'Dyktujesz trendy w The Abyss.',
        'wymagania' => ['Krawiectwo i Stylizacja' => 10, 'Moda i Wizerunek' => 10, 'Sztuki Plastyczne i Rzemiosło' => 8, 'Perswazja i Negocjacje' => 5],
        'wymagany_tytul' => 'Magister Sztuki',
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Milanowie mody nazywają Cię po imieniu. Twoje kolekcje trafiają na okładki Vogue, a projekty noszą celebrytki. Elita płaci krocie, by wyglądać jak Twoja wizja.',
            'umiejetnosci_bonus_proc' => [
                'Krawiectwo i Stylizacja' => 25,
                'Moda i Wizerunek' => 25,
                'Sztuki Plastyczne i Rzemiosło' => 15,
                'Perswazja i Negocjacje' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ EDUKACJA I NAUKA ═══════════════════════ */

    'Nauczyciel' => [
        'opis' => 'Próbujesz wbić wiedzę do głów trudnej młodzieży.',
        'wymagania' => ['Wiedza Ogólna i Pedagogika' => 9, 'Psychologia i Empatia' => 6, 'Perswazja i Negocjacje' => 5],
        'wymagany_tytul' => 'Magister Edukacji',
        'kategoria' => 'nauka',
        'rp' => [
            'opis_spoleczny' => 'Uczniowie Cię szanują, rodzice też. Syndykaty Cię omijają — kto atakuje nauczycieli, traci lojalność ulicy. Jeden z niewielu bezpiecznych zawodów w The Abyss.',
            'umiejetnosci_bonus_proc' => [
                'Wiedza Ogólna i Pedagogika' => 25,
                'Psychologia i Empatia' => 15,
                'Perswazja i Negocjacje' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Wykładowca' => [
        'opis' => 'Ekspert z uniwersytetu.',
        'wymagania' => ['Wiedza Ogólna i Pedagogika' => 12, 'Analiza Danych i Dedukcja' => 7, 'Wystąpienia Publiczne' => 6, 'Aktorstwo i Charakteryzacja' => 4],
        'wymagany_tytul' => 'Magister Edukacji',
        'kategoria' => 'nauka',
        'rp' => [
            'opis_spoleczny' => 'Tytuł profesora otwiera elitarne drzwi. Piszesz artykuły do prasy, konsultujesz dla rządu, doradzasz korporacjom. Cichy wpływ na społeczeństwo — większy niż połowa polityków.',
            'umiejetnosci_bonus_proc' => [
                'Wiedza Ogólna i Pedagogika' => 30,
                'Analiza Danych i Dedukcja' => 15,
                'Wystąpienia Publiczne' => 15,
                'Aktorstwo i Charakteryzacja' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Bibliotekarz / Archiwista' => [
        'opis' => 'Zarządzasz księgozbiorami i wiesz, gdzie leżą sekrety.',
        'wymagania' => ['Wiedza Ogólna i Pedagogika' => 8, 'Historia i Antykwariat' => 6, 'Analiza Danych i Dedukcja' => 6, 'Etykieta i Dobre Manery' => 4],
        'wymagany_tytul' => 'Magister Edukacji',
        'kategoria' => 'nauka',
        'rp' => [
            'opis_spoleczny' => 'Dostęp do archiwów, dokumentów i książek wyklętych. Dziennikarze, prawnicy, naukowcy przychodzą do Ciebie po informacje — Ty wiesz, gdzie co leży.',
            'umiejetnosci_bonus_proc' => [
                'Wiedza Ogólna i Pedagogika' => 25,
                'Historia i Antykwariat' => 20,
                'Analiza Danych i Dedukcja' => 15,
                'Etykieta i Dobre Manery' => 5,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    'Historyk / Archeolog' => [
        'opis' => 'Odkrywasz prawdę zakopaną w ruinach miasta.',
        'wymagania' => ['Historia i Antykwariat' => 12, 'Analiza Danych i Dedukcja' => 8, 'Wiedza Ogólna i Pedagogika' => 6, 'Przetrwanie w Dziczy' => 4],
        'wymagany_tytul' => 'Magister Historii',
        'kategoria' => 'nauka',
        'rp' => [
            'opis_spoleczny' => 'Muzea zapraszają Cię na konsultacje, uniwersytety proszą o wykłady, a czarny rynek oferuje fortuny za Twoją ekspertyzę. Autorytet dla elity, skarb dla paserów.',
            'umiejetnosci_bonus_proc' => [
                'Historia i Antykwariat' => 35,
                'Analiza Danych i Dedukcja' => 15,
                'Wiedza Ogólna i Pedagogika' => 10,
                'Przetrwanie w Dziczy' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 2],
        ],
    ],

    'Antykwariusz' => [
        'opis' => 'Znasz się na starociach. Twoje biurko kryje fortunę.',
        'wymagania' => ['Historia i Antykwariat' => 10, 'Handel i Wycena' => 8, 'Etykieta i Dobre Manery' => 5, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'handel',
        'rp' => [
            'opis_spoleczny' => 'Twój sklep to skarbnica. Kolekcjonerzy i elita przychodzą po unikaty. Czasami ktoś próbuje Ci sprzedać coś, o czego pochodzenie lepiej nie pytać.',
            'umiejetnosci_bonus_proc' => [
                'Historia i Antykwariat' => 25,
                'Handel i Wycena' => 25,
                'Etykieta i Dobre Manery' => 15,
                'Obsługa Klienta' => 10,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════════ BIZNES, PRAWO I POLITYKA ═══════════════ */

    'Księgowy' => [
        'opis' => 'Słupki muszą się zgadzać.',
        'wymagania' => ['Matematyka i Rachunkowość' => 12, 'Analiza Danych i Dedukcja' => 8, 'Prawo i Administracja' => 6, 'Śledzenie Finansowe' => 5],
        'wymagany_tytul' => 'Magister Ekonomii',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Korporacje Cię potrzebują, syndykaty Cię potrzebują bardziej — pranie pieniędzy wymaga specjalistów. Szacunek elity + ciche zlecenia z podziemia = bardzo dochodowe życie.',
            'umiejetnosci_bonus_proc' => [
                'Matematyka i Rachunkowość' => 30,
                'Analiza Danych i Dedukcja' => 20,
                'Prawo i Administracja' => 10,
                'Śledzenie Finansowe' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => -1, 'syndykat' => 2, 'wladze' => 2, 'spoleczenstwo' => 1],
        ],
    ],

    'Przedsiębiorca' => [
        'opis' => 'Właściciel biznesu. Zarządzasz ludźmi i kapitałem.',
        'wymagania' => ['Zarządzanie i Przywództwo' => 12, 'Handel i Wycena' => 10, 'Matematyka i Rachunkowość' => 8, 'Perswazja i Negocjacje' => 8],
        'wymagany_tytul' => 'Magister Ekonomii',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Elita wita Cię na prywatnych obiadach. Korpo-władza uznaje Cię za gracza. Ulica może nienawidzić — ale pieniądze otwierają każde drzwi w The Abyss.',
            'umiejetnosci_bonus_proc' => [
                'Zarządzanie i Przywództwo' => 25,
                'Handel i Wycena' => 20,
                'Matematyka i Rachunkowość' => 15,
                'Perswazja i Negocjacje' => 15,
            ],
            'reputacja' => ['elita' => 4, 'ulica' => -1, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 2],
        ],
    ],

    'Kadrowa / HR Manager' => [
        'opis' => 'Rekrutujesz, zwalniasz, negocjujesz kontrakty.',
        'wymagania' => ['Kadry i Rekrutacja' => 10, 'Psychologia i Empatia' => 7, 'Prawo i Administracja' => 5, 'Etykieta i Dobre Manery' => 5, 'Perswazja i Negocjacje' => 5],
        'wymagany_tytul' => 'Magister Ekonomii',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Władasz losem setek pracowników. Jedna decyzja i rodzina traci dom, inna — awans życia. Pracownicy Cię boją i szukają Twojego faworyzowania. Korporacja Cię ceni jako tarczę.',
            'umiejetnosci_bonus_proc' => [
                'Kadry i Rekrutacja' => 30,
                'Psychologia i Empatia' => 20,
                'Prawo i Administracja' => 15,
                'Etykieta i Dobre Manery' => 10,
                'Perswazja i Negocjacje' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => -1, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 1],
        ],
    ],

    'Sekretarka / Asystentka' => [
        'opis' => 'Prawa ręka szefa. Wiesz o firmie więcej niż on sam.',
        'wymagania' => ['Obsługa Klienta' => 7, 'Matematyka i Rachunkowość' => 5, 'Etykieta i Dobre Manery' => 6, 'Psychologia i Empatia' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Pierwsza linia obrony — i pierwsza uszy szefa. Pracownicy sądzą, że Cię nie widzą, ale Ty zapamiętujesz każde przewinienie i każdy romans biurowy.',
            'umiejetnosci_bonus_proc' => [
                'Obsługa Klienta' => 25,
                'Etykieta i Dobre Manery' => 15,
                'Psychologia i Empatia' => 15,
                'Matematyka i Rachunkowość' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],

    'Urzędnik Państwowy' => [
        'opis' => 'Żelazny tron za biurkiem. Stempel albo nie stempel.',
        'wymagania' => ['Prawo i Administracja' => 8, 'Matematyka i Rachunkowość' => 5, 'Etykieta i Dobre Manery' => 4, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Petenci stoją w kolejce, by zyskać Twoją przychylność. Jedna drobna łapówka, jedno pominięte pole w formularzu — i sprawa idzie inaczej. Władza bez fajerwerków.',
            'umiejetnosci_bonus_proc' => [
                'Prawo i Administracja' => 25,
                'Obsługa Klienta' => 15,
                'Matematyka i Rachunkowość' => 10,
                'Etykieta i Dobre Manery' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => -1, 'syndykat' => 0, 'wladze' => 3, 'spoleczenstwo' => 0],
        ],
    ],

    'Prokurator' => [
        'opis' => 'Oskarżasz kryminalistów z uśmiechem na ustach.',
        'wymagania' => ['Prawo i Administracja' => 12, 'Analiza Danych i Dedukcja' => 9, 'Perswazja i Negocjacje' => 8, 'Zastraszanie' => 6, 'Wystąpienia Publiczne' => 6],
        'wymagany_tytul' => 'Magister Prawa',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Przed Tobą drżą. Za Tobą śmierć idzie. Prokurator w The Abyss żyje w jednej z dwóch opcji: ochrona państwa lub grób przy ulicy.',
            'umiejetnosci_bonus_proc' => [
                'Prawo i Administracja' => 30,
                'Analiza Danych i Dedukcja' => 20,
                'Perswazja i Negocjacje' => 15,
                'Zastraszanie' => 10,
                'Wystąpienia Publiczne' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => -2, 'syndykat' => -3, 'wladze' => 4, 'spoleczenstwo' => 2],
        ],
    ],

    'Adwokat' => [
        'opis' => 'Wyciągasz z aresztu szumowiny za odpowiednią stawkę.',
        'wymagania' => ['Prawo i Administracja' => 12, 'Perswazja i Negocjacje' => 10, 'Psychologia i Empatia' => 7, 'Etykieta i Dobre Manery' => 5, 'Wystąpienia Publiczne' => 5],
        'wymagany_tytul' => 'Magister Prawa',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Ochraniasz swoich klientów, nawet tych najgorszych — to zawodowa etyka. Syndykaty Cię kochają, policja nienawidzi, ale system i tak Cię chroni.',
            'umiejetnosci_bonus_proc' => [
                'Prawo i Administracja' => 30,
                'Perswazja i Negocjacje' => 20,
                'Psychologia i Empatia' => 15,
                'Etykieta i Dobre Manery' => 10,
                'Wystąpienia Publiczne' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 2, 'syndykat' => 3, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Sędzia' => [
        'opis' => 'Sprawiedliwość jest taka, jaką ją ogłosisz.',
        'wymagania' => ['Prawo i Administracja' => 14, 'Analiza Danych i Dedukcja' => 10, 'Psychologia i Empatia' => 8, 'Etykieta i Dobre Manery' => 7, 'Wystąpienia Publiczne' => 5],
        'wymagany_tytul' => 'Magister Prawa',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Jedna dłoń ze stuknięciem młotka zmienia życie. Syndykaty próbują się przebić przez bramę ochrony, korporacje szukają przychylnych wyroków, prasa wytyka każdy błąd.',
            'umiejetnosci_bonus_proc' => [
                'Prawo i Administracja' => 35,
                'Analiza Danych i Dedukcja' => 20,
                'Psychologia i Empatia' => 15,
                'Etykieta i Dobre Manery' => 10,
                'Wystąpienia Publiczne' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => -1, 'syndykat' => -2, 'wladze' => 5, 'spoleczenstwo' => 3],
        ],
    ],

    'Polityk' => [
        'opis' => 'Obiecujesz wszystko każdemu. Wygrywasz wybory.',
        'wymagania' => ['Wystąpienia Publiczne' => 12, 'Perswazja i Negocjacje' => 10, 'Prawo i Administracja' => 8, 'Etykieta i Dobre Manery' => 8, 'Psychologia i Empatia' => 6],
        'wymagany_tytul' => 'Magister Prawa',
        'kategoria' => 'biznes',
        'rp' => [
            'opis_spoleczny' => 'Kolorowe plakaty, głośne przemówienia, ciche spotkania za zamkniętymi drzwiami. Elita finansuje, media obserwują, syndykaty czekają z propozycjami nie do odrzucenia.',
            'umiejetnosci_bonus_proc' => [
                'Wystąpienia Publiczne' => 30,
                'Perswazja i Negocjacje' => 20,
                'Prawo i Administracja' => 15,
                'Etykieta i Dobre Manery' => 10,
                'Psychologia i Empatia' => 10,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => -1, 'syndykat' => 1, 'wladze' => 4, 'spoleczenstwo' => 2],
        ],
    ],

    'Ksiądz / Pastor' => [
        'opis' => 'Duszpasterz zgubionych dusz miasta.',
        'wymagania' => ['Psychologia i Empatia' => 8, 'Wystąpienia Publiczne' => 8, 'Wiedza Ogólna i Pedagogika' => 6, 'Perswazja i Negocjacje' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'nauka',
        'rp' => [
            'opis_spoleczny' => 'Parafianie zwierzają Ci się z grzechów, których nie wyznają nikomu innemu. Władze Cię szanują, syndykaty Cię omijają, ulica się Ciebie obawia i kocha jednocześnie.',
            'umiejetnosci_bonus_proc' => [
                'Psychologia i Empatia' => 25,
                'Wystąpienia Publiczne' => 20,
                'Wiedza Ogólna i Pedagogika' => 15,
                'Perswazja i Negocjacje' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 4],
        ],
    ],

    /* ══════════════════════ SŁUŻBY PAŃSTWOWE ═══════════════════════ */

    'Policjant' => [
        'opis' => 'Pilnujesz porządku na ulicach, które ciebie nienawidzą.',
        'wymagania' => ['Walka Bronią Palną' => 8, 'Prawo i Administracja' => 5, 'Zastraszanie' => 5, 'Kondycja i Wytrzymałość' => 6, 'Prowadzenie Pojazdów' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'sluzby',
        'rp' => [
            'opis_spoleczny' => 'Mundur budzi respekt w dobrych dzielnicach, pogardę w złych. Koledzy po fachu są rodziną, syndykaty wiedzą o każdym Twoim kroku. Granica między bohaterem a łajdakiem jest cienka.',
            'umiejetnosci_bonus_proc' => [
                'Walka Bronią Palną' => 20,
                'Zastraszanie' => 15,
                'Prawo i Administracja' => 15,
                'Kondycja i Wytrzymałość' => 10,
                'Prowadzenie Pojazdów' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => -2, 'syndykat' => -1, 'wladze' => 3, 'spoleczenstwo' => 2],
        ],
    ],

    'Detektyw Policyjny' => [
        'opis' => 'Masz odznakę, licencję i uzasadnione podejrzenia.',
        'wymagania' => ['Analiza Danych i Dedukcja' => 10, 'Walka Bronią Palną' => 6, 'Skradanie' => 6, 'Psychologia i Empatia' => 5, 'Prawo i Administracja' => 5],
        'wymagany_tytul' => 'Licencjat Kryminologii',
        'kategoria' => 'sluzby',
        'rp' => [
            'opis_spoleczny' => 'Prowadzisz śledztwa tam, gdzie zwykły policjant by się zagubił. Masz dostęp do akt, świadków i baz danych. Skorumpowana część systemu patrzy na Cię z niepokojem.',
            'umiejetnosci_bonus_proc' => [
                'Analiza Danych i Dedukcja' => 30,
                'Skradanie' => 15,
                'Psychologia i Empatia' => 15,
                'Prawo i Administracja' => 10,
                'Walka Bronią Palną' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => -1, 'syndykat' => -2, 'wladze' => 4, 'spoleczenstwo' => 3],
        ],
    ],

    'Strażak' => [
        'opis' => 'Wchodzisz w ogień, kiedy inni uciekają.',
        'wymagania' => ['Kondycja i Wytrzymałość' => 10, 'Mechanika i Naprawa' => 6, 'Demolka i Siłowe Wejścia' => 5, 'Medycyna Uliczna' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'sluzby',
        'rp' => [
            'opis_spoleczny' => 'Jeden z nielicznych zawodów, który wszyscy kochają — od slumsów po penthousy. Nikt nie atakuje strażaka. Nawet najgorszy syndykat ma zasady.',
            'umiejetnosci_bonus_proc' => [
                'Kondycja i Wytrzymałość' => 25,
                'Demolka i Siłowe Wejścia' => 20,
                'Mechanika i Naprawa' => 15,
                'Medycyna Uliczna' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 3, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 4],
        ],
    ],

    'Ratownik Medyczny' => [
        'opis' => 'Karetka pod drzwiami, minuty do życia.',
        'wymagania' => ['Medycyna Uliczna' => 8, 'Kondycja i Wytrzymałość' => 6, 'Prowadzenie Pojazdów' => 5, 'Psychologia i Empatia' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'sluzby',
        'rp' => [
            'opis_spoleczny' => 'Widzisz miasto od najgorszej strony — strzelaniny, przedawkowania, wypadki. Ratujesz wszystkich bez pytania o przynależność. Zaufanie na ulicy i w szpitalach.',
            'umiejetnosci_bonus_proc' => [
                'Medycyna Uliczna' => 30,
                'Kondycja i Wytrzymałość' => 15,
                'Prowadzenie Pojazdów' => 15,
                'Psychologia i Empatia' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 3, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 4],
        ],
    ],

    'Żołnierz / Najemnik' => [
        'opis' => 'Operacje poza prawem — dla rządu lub za pieniądze.',
        'wymagania' => ['Walka Bronią Palną' => 10, 'Taktyka Wojskowa' => 8, 'Kondycja i Wytrzymałość' => 8, 'Celność Snajperska' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'walka',
        'rp' => [
            'opis_spoleczny' => 'Mundur lub pseudonim — nieważne. Twoja reputacja to skuteczność. Kontrakty od rządów i korporacji. Ulica Cię szanuje, wrogowie Cię boją, a weteranów pamiętasz imieniem.',
            'umiejetnosci_bonus_proc' => [
                'Walka Bronią Palną' => 25,
                'Taktyka Wojskowa' => 25,
                'Kondycja i Wytrzymałość' => 15,
                'Celność Snajperska' => 15,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 1],
        ],
    ],

    /* ══════════════════════ MEDYCYNA I OPIEKA ══════════════════════ */

    'Medyk Uliczny' => [
        'opis' => 'Zaszywasz rany w piwnicach.',
        'wymagania' => ['Medycyna Uliczna' => 10, 'Chemia i Farmakologia' => 6, 'Zacieranie Śladów' => 5, 'Znajomość Półświatka' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Ulica Cię kocha — ratujesz życie gangsterom, których szpital by nie przyjął. Dyskrecja jest Twoim drugim imieniem, a dług wdzięczności masz u każdego syndykatu.',
            'umiejetnosci_bonus_proc' => [
                'Medycyna Uliczna' => 30,
                'Chemia i Farmakologia' => 15,
                'Zacieranie Śladów' => 10,
                'Znajomość Półświatka' => 5,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 3, 'syndykat' => 2, 'wladze' => -1, 'spoleczenstwo' => 1],
        ],
    ],

    'Lekarz Rodzinny' => [
        'opis' => 'Przyjmujesz elitarnych pacjentów z dyplomem na ścianie.',
        'wymagania' => ['Medycyna Akademicka' => 12, 'Chemia i Farmakologia' => 8, 'Psychologia i Empatia' => 7, 'Etykieta i Dobre Manery' => 5],
        'wymagany_tytul' => 'Lekarz Medycyny',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Gabinet w centrum, prywatni pacjenci, elita na pierwsze miejsce w kolejce. Znasz ich tajemnice — i sekrety ich małżonków. Lekarz to spowiednik XXI wieku.',
            'umiejetnosci_bonus_proc' => [
                'Medycyna Akademicka' => 30,
                'Chemia i Farmakologia' => 20,
                'Psychologia i Empatia' => 15,
                'Etykieta i Dobre Manery' => 10,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Pielęgniarka / Pielęgniarz' => [
        'opis' => 'Sól szpitala. Znasz każdego pacjenta po imieniu.',
        'wymagania' => ['Pielęgniarstwo' => 10, 'Medycyna Akademicka' => 6, 'Psychologia i Empatia' => 5, 'Kondycja i Wytrzymałość' => 4],
        'wymagany_tytul' => 'Lekarz Medycyny',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Lekarze przychodzą i odchodzą — Ty zostajesz. Pacjenci wpadają w rozpacz i zdrowieją dzięki Tobie. Ulica Cię uwielbia, elita szanuje, każda rodzina coś Ci zawdzięcza.',
            'umiejetnosci_bonus_proc' => [
                'Pielęgniarstwo' => 30,
                'Medycyna Akademicka' => 15,
                'Psychologia i Empatia' => 15,
                'Kondycja i Wytrzymałość' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 4],
        ],
    ],

    'Chirurg' => [
        'opis' => 'Cięcie, zszycie, nowe życie. Albo i nie.',
        'wymagania' => ['Chirurgia' => 12, 'Medycyna Akademicka' => 10, 'Chemia i Farmakologia' => 8, 'Psychologia i Empatia' => 5, 'Pielęgniarstwo' => 4],
        'wymagany_tytul' => 'Lekarz Medycyny',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Na stole operacyjnym Twoja ręka decyduje o życiu. Elita polecana do najlepszych klinik. Syndykaty marzą o swoim dyskretnym chirurgu. Zarobki szaleństwo, odpowiedzialność — tragedia.',
            'umiejetnosci_bonus_proc' => [
                'Chirurgia' => 35,
                'Medycyna Akademicka' => 20,
                'Chemia i Farmakologia' => 15,
                'Pielęgniarstwo' => 10,
                'Psychologia i Empatia' => 5,
            ],
            'reputacja' => ['elita' => 4, 'ulica' => 1, 'syndykat' => 2, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Psychiatra' => [
        'opis' => 'Diagnozujesz zaburzenia, leczysz umysły pełne blizn.',
        'wymagania' => ['Psychiatria' => 10, 'Medycyna Akademicka' => 8, 'Psychologia i Empatia' => 10, 'Chemia i Farmakologia' => 6],
        'wymagany_tytul' => 'Lekarz Medycyny',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Pacjenci opowiadają Ci rzeczy, których nie powiedzieliby nawet na spowiedzi. Znasz sekrety, traumy i grzechy elity. To wiedza bardzo niebezpieczna — i bardzo cenna.',
            'umiejetnosci_bonus_proc' => [
                'Psychiatria' => 35,
                'Psychologia i Empatia' => 25,
                'Medycyna Akademicka' => 15,
                'Chemia i Farmakologia' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 3],
        ],
    ],

    'Fizjoterapeuta' => [
        'opis' => 'Reanimujesz ciała po wypadkach i operacjach.',
        'wymagania' => ['Fizjoterapia i Rehabilitacja' => 10, 'Medycyna Akademicka' => 5, 'Kondycja i Wytrzymałość' => 6, 'Psychologia i Empatia' => 4],
        'wymagany_tytul' => 'Lekarz Medycyny',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Praca mozolna i codzienna. Sportowcy, weterani, ofiary strzelanin — wszyscy trafiają na Twój stół. Powolna praca przy odbudowie życia.',
            'umiejetnosci_bonus_proc' => [
                'Fizjoterapia i Rehabilitacja' => 30,
                'Pielęgniarstwo' => 15,
                'Medycyna Akademicka' => 10,
                'Psychologia i Empatia' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 3],
        ],
    ],

    'Farmaceuta' => [
        'opis' => 'Apteka to Twój zamek. Każda pigułka przeliczona.',
        'wymagania' => ['Chemia i Farmakologia' => 12, 'Medycyna Akademicka' => 6, 'Matematyka i Rachunkowość' => 5, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => 'Magister Farmacji',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Znasz recepty całej dzielnicy. Wiesz kto bierze antydepresanty, kto hormony, kto morfinę po operacji. Legalny dostęp do leków to potężna pozycja.',
            'umiejetnosci_bonus_proc' => [
                'Chemia i Farmakologia' => 35,
                'Medycyna Akademicka' => 15,
                'Matematyka i Rachunkowość' => 10,
                'Obsługa Klienta' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 2, 'spoleczenstwo' => 3],
        ],
    ],

    'Weterynarz' => [
        'opis' => 'Leczysz zwierzęta — i te nietypowe eksperymenty.',
        'wymagania' => ['Weterynaria' => 10, 'Biologia i Botanika' => 6, 'Chemia i Farmakologia' => 5, 'Psychologia i Empatia' => 4],
        'wymagany_tytul' => 'Lekarz Weterynarii',
        'kategoria' => 'medycyna',
        'rp' => [
            'opis_spoleczny' => 'Właściciele zwierząt ufają Ci bezgranicznie. Syndykaty przyprowadzają psy bojowe. Kolekcjonerzy — egzotyczne zwierzęta z czarnego rynku. Czasem wiesz o pochodzeniu więcej, niż powinieneś.',
            'umiejetnosci_bonus_proc' => [
                'Weterynaria' => 35,
                'Biologia i Botanika' => 20,
                'Chemia i Farmakologia' => 15,
                'Chirurgia' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 2, 'syndykat' => 2, 'wladze' => 1, 'spoleczenstwo' => 3],
        ],
    ],

    'Masażysta' => [
        'opis' => 'Twoje dłonie to terapia i usługa jednocześnie.',
        'wymagania' => ['Fizjoterapia i Rehabilitacja' => 7, 'Kondycja i Wytrzymałość' => 5, 'Psychologia i Empatia' => 4, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Salon masażu — miejsce, gdzie klienci opuszczają maski. Elita przychodzi się odstresować, sportowcy odbudować. Jeśli pracujesz w eleganckich klubach, jesteś świadkiem wielu biznesowych rozmów.',
            'umiejetnosci_bonus_proc' => [
                'Fizjoterapia i Rehabilitacja' => 25,
                'Psychologia i Empatia' => 15,
                'Obsługa Klienta' => 10,
                'Kondycja i Wytrzymałość' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ TECHNIKA I NAUKA ═══════════════════════ */

    'Haker' => [
        'opis' => 'Omijanie zapór korporacyjnych to Twoja codzienność.',
        'wymagania' => ['Hakowanie Terminali' => 12, 'Włamywanie Elektroniczne' => 10, 'Programowanie' => 8, 'Analiza Danych i Dedukcja' => 8, 'Zacieranie Śladów' => 6],
        'wymagany_tytul' => 'Inżynier Cybernetyki',
        'kategoria' => 'technika',
        'rp' => [
            'opis_spoleczny' => 'Niewidzialny w sieci, potężny w darknecie. Korporacje oferują Ci kontrakty i nagrody za głowę jednocześnie. Społeczność hakerska — Twoja rodzina — chroni Cię przed światem.',
            'umiejetnosci_bonus_proc' => [
                'Hakowanie Terminali' => 30,
                'Włamywanie Elektroniczne' => 25,
                'Programowanie' => 20,
                'Analiza Danych i Dedukcja' => 15,
                'Zacieranie Śladów' => 10,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 2, 'syndykat' => 2, 'wladze' => -2, 'spoleczenstwo' => 0],
        ],
    ],

    'Programista' => [
        'opis' => 'Piszesz kod, który porusza świat.',
        'wymagania' => ['Programowanie' => 12, 'Analiza Danych i Dedukcja' => 8, 'Matematyka i Rachunkowość' => 6, 'Elektronika' => 5],
        'wymagany_tytul' => 'Inżynier Cybernetyki',
        'kategoria' => 'technika',
        'rp' => [
            'opis_spoleczny' => 'Biurko z trzema monitorami i klawiatura mechaniczna. Korporacja Cię wynajmuje, startup Cię kusi opcjami, freelance daje wolność. Wybierz swoją klatkę.',
            'umiejetnosci_bonus_proc' => [
                'Programowanie' => 35,
                'Analiza Danych i Dedukcja' => 20,
                'Matematyka i Rachunkowość' => 15,
                'Elektronika' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    'Inżynier Elektroniki' => [
        'opis' => 'Lutownica to Twój skalpel. Obwody to Twoja sztuka.',
        'wymagania' => ['Elektronika' => 12, 'Inżynieria Złomu' => 6, 'Mechanika i Naprawa' => 6, 'Matematyka i Rachunkowość' => 5],
        'wymagany_tytul' => 'Główny Inżynier',
        'kategoria' => 'technika',
        'rp' => [
            'opis_spoleczny' => 'Potrafisz zrobić podsłuch z radia i bombę z telefonu. Korporacje budują wokół Ciebie działy R&D, syndykaty marzą o cichym specjaliście od gadżetów.',
            'umiejetnosci_bonus_proc' => [
                'Elektronika' => 35,
                'Inżynieria Złomu' => 15,
                'Mechanika i Naprawa' => 15,
                'Matematyka i Rachunkowość' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 2, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],

    'Botanik' => [
        'opis' => 'Hodujesz florę dla bogaczy, a czasem trujące pnącza.',
        'wymagania' => ['Biologia i Botanika' => 10, 'Medycyna Uliczna' => 4, 'Kondycja i Wytrzymałość' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'technika',
        'rp' => [
            'opis_spoleczny' => 'Dla elity — projekty ogrodów vertical na dachach. Dla ulicy — zioła lecznicze i mniej legalne odmiany. Dla syndykatu — uprawy, o których lepiej nie mówić głośno.',
            'umiejetnosci_bonus_proc' => [
                'Biologia i Botanika' => 30,
                'Medycyna Uliczna' => 15,
                'Chemia i Farmakologia' => 10,
                'Kondycja i Wytrzymałość' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 1],
        ],
    ],

    'Architekt' => [
        'opis' => 'Projektujesz luksusowe penthousy i zbrojone bunkry.',
        'wymagania' => ['Architektura i Konstrukcje' => 10, 'Matematyka i Rachunkowość' => 8, 'Sztuki Plastyczne i Rzemiosło' => 6],
        'wymagany_tytul' => 'Główny Inżynier',
        'kategoria' => 'technika',
        'rp' => [
            'opis_spoleczny' => 'Twoje projekty wyznaczają nowy skyline miasta. Miliarderzy czekają miesiące na Twoje konsultacje — a syndykaty potrzebują kogoś, kto zaprojektuje im bunkier pod kasynem.',
            'umiejetnosci_bonus_proc' => [
                'Architektura i Konstrukcje' => 30,
                'Matematyka i Rachunkowość' => 20,
                'Sztuki Plastyczne i Rzemiosło' => 15,
                'Mechanika i Naprawa' => 5,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 2, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ OCHRONA I WALKA ════════════════════════ */

    'Ochroniarz' => [
        'opis' => 'Stoisz na bramce i łamiesz nosy pijanym bywalcom.',
        'wymagania' => ['Boks Uliczny' => 8, 'Zastraszanie' => 6, 'Kondycja i Wytrzymałość' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'walka',
        'rp' => [
            'opis_spoleczny' => 'Szerokie barki otwierają drzwi i zamykają problemy. Kluby, imprezy, prywatna ochrona — masz dostęp do miejsc, gdzie inni tylko marzą o wejściu.',
            'umiejetnosci_bonus_proc' => [
                'Boks Uliczny' => 20,
                'Zastraszanie' => 15,
                'Kondycja i Wytrzymałość' => 10,
                'Walka Bronią Palną' => 5,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],

    'Klawisz' => [
        'opis' => 'Trzymasz w ryzach więźniów, często będąc gorszym od nich.',
        'wymagania' => ['Zastraszanie' => 8, 'Boks Uliczny' => 6, 'Kondycja i Wytrzymałość' => 6, 'Prawo i Administracja' => 4],
        'wymagany_tytul' => 'Licencjat Kryminologii',
        'kategoria' => 'walka',
        'rp' => [
            'opis_spoleczny' => 'Cienkie granice między strażnikiem a przestępcą. Masz dostęp do kryminalistów, którzy nie zobaczą światła dziennego — i kontakty niemożliwe dla innych.',
            'umiejetnosci_bonus_proc' => [
                'Zastraszanie' => 20,
                'Boks Uliczny' => 15,
                'Kondycja i Wytrzymałość' => 10,
                'Prawo i Administracja' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => -1, 'syndykat' => 0, 'wladze' => 2, 'spoleczenstwo' => 0],
        ],
    ],

    'Detektyw Uliczny' => [
        'opis' => 'Zbierasz haki na mężów, żony i bossów.',
        'wymagania' => ['Analiza Danych i Dedukcja' => 10, 'Skradanie' => 8, 'Znajomość Półświatka' => 6, 'Psychologia i Empatia' => 6, 'Szpiegostwo i Inwigilacja' => 6],
        'wymagany_tytul' => 'Licencjat Kryminologii',
        'kategoria' => 'walka',
        'rp' => [
            'opis_spoleczny' => 'Obserwujesz, słuchasz, dokumentujesz. Klienci płacą za niewygodne prawdy. Żyjesz na pograniczu prawa — jesteś potrzebny, ale nikt nie chce Cię widzieć publicznie.',
            'umiejetnosci_bonus_proc' => [
                'Analiza Danych i Dedukcja' => 25,
                'Skradanie' => 20,
                'Szpiegostwo i Inwigilacja' => 15,
                'Znajomość Półświatka' => 10,
                'Psychologia i Empatia' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 1, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 1],
        ],
    ],

    'Płatny Zabójca' => [
        'opis' => 'Twoja wizytówka to śmierć. Jesteś duchem.',
        'wymagania' => ['Walka Bronią Palną' => 12, 'Celność Snajperska' => 10, 'Skradanie' => 10, 'Walka na Noże' => 8, 'Zacieranie Śladów' => 6, 'Kondycja i Wytrzymałość' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'walka',
        'rp' => [
            'opis_spoleczny' => 'Legenda ulicy, postrach elity. Nikt Cię nie zna z twarzy — Twoje nazwisko to szept. Syndykaty płacą kroci, władze ścigają, społeczeństwo drży na samą myśl.',
            'umiejetnosci_bonus_proc' => [
                'Walka Bronią Palną' => 25,
                'Celność Snajperska' => 20,
                'Skradanie' => 20,
                'Walka na Noże' => 15,
                'Zacieranie Śladów' => 15,
                'Kondycja i Wytrzymałość' => 10,
            ],
            'reputacja' => ['elita' => -3, 'ulica' => 3, 'syndykat' => 2, 'wladze' => -5, 'spoleczenstwo' => -3],
        ],
    ],

    /* ══════════════════════ SPORT I KONDYCJA ═══════════════════════ */

    'Sportowiec Zawodowy' => [
        'opis' => 'Światła stadionu, okładki gazet, kontrakty.',
        'wymagania' => ['Kondycja i Wytrzymałość' => 12, 'Akrobatyka i Taniec' => 6, 'Moda i Wizerunek' => 5, 'Boks Uliczny' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'sztuka',
        'rp' => [
            'opis_spoleczny' => 'Fani Cię kochają, sponsorzy płacą fortuny, syndykaty próbują kupić mecz. Kontrakt to klatka — ale klatka ze złota.',
            'umiejetnosci_bonus_proc' => [
                'Kondycja i Wytrzymałość' => 30,
                'Akrobatyka i Taniec' => 15,
                'Boks Uliczny' => 15,
                'Moda i Wizerunek' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 3, 'syndykat' => 1, 'wladze' => 1, 'spoleczenstwo' => 4],
        ],
    ],

    'Trener Fitness' => [
        'opis' => 'Kształtujesz ciała i motywujesz leniwych.',
        'wymagania' => ['Kondycja i Wytrzymałość' => 8, 'Fizjoterapia i Rehabilitacja' => 5, 'Psychologia i Empatia' => 5, 'Perswazja i Negocjacje' => 4, 'Obsługa Klienta' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'rzemioslo',
        'rp' => [
            'opis_spoleczny' => 'Klienci marzą o takim ciele jak Twoje. Siłownia to Twoja domena — znasz każdego bywalca, ich kompleksy, aspiracje, tajemnice.',
            'umiejetnosci_bonus_proc' => [
                'Kondycja i Wytrzymałość' => 25,
                'Fizjoterapia i Rehabilitacja' => 15,
                'Psychologia i Empatia' => 15,
                'Perswazja i Negocjacje' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ OUTDOOR / PRZYRODA ═════════════════════ */

    'Leśniczy' => [
        'opis' => 'Strażnik dziczy i nadzorca ocalałych lasów.',
        'wymagania' => ['Przetrwanie w Dziczy' => 8, 'Myślistwo i Polowanie' => 6, 'Topografia i Nawigacja' => 5, 'Biologia i Botanika' => 4, 'Tropienie' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'przyroda',
        'rp' => [
            'opis_spoleczny' => 'W mieście Cię nie znają — ale w dziczy, na obrzeżach, jesteś królem. Chronisz zwierzynę przed kłusownikami, a czasem przed samym sobą.',
            'umiejetnosci_bonus_proc' => [
                'Przetrwanie w Dziczy' => 25,
                'Tropienie' => 20,
                'Myślistwo i Polowanie' => 15,
                'Topografia i Nawigacja' => 10,
                'Biologia i Botanika' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 0, 'syndykat' => -1, 'wladze' => 2, 'spoleczenstwo' => 2],
        ],
    ],

    'Rybak' => [
        'opis' => 'Morze karmi, morze zabiera. Znasz jego humory.',
        'wymagania' => ['Rybołówstwo i Żegluga' => 10, 'Przetrwanie w Dziczy' => 5, 'Kondycja i Wytrzymałość' => 6, 'Pływanie i Nurkowanie' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'przyroda',
        'rp' => [
            'opis_spoleczny' => 'Dzielnice portowe Cię znają. Dostarczasz świeże ryby do kuchni elity i mniej świeże do slumsów. Łódź to Twoje mobilne biuro — i czasem idealne ukrycie.',
            'umiejetnosci_bonus_proc' => [
                'Rybołówstwo i Żegluga' => 30,
                'Kondycja i Wytrzymałość' => 15,
                'Przetrwanie w Dziczy' => 10,
                'Pływanie i Nurkowanie' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 1, 'wladze' => 0, 'spoleczenstwo' => 2],
        ],
    ],

    'Instruktor Jazdy Konnej' => [
        'opis' => 'Stajnia, konie, uczniowie z bogatych domów.',
        'wymagania' => ['Jazda Konna' => 10, 'Weterynaria' => 5, 'Psychologia i Empatia' => 4, 'Obsługa Klienta' => 4, 'Etykieta i Dobre Manery' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'przyroda',
        'rp' => [
            'opis_spoleczny' => 'Klubiki jeździeckie elity. Zakłady, sponsoring, córki milionerów na Twoich zajęciach. Znasz posiadłości, o których plebs nawet nie słyszał.',
            'umiejetnosci_bonus_proc' => [
                'Jazda Konna' => 30,
                'Weterynaria' => 15,
                'Etykieta i Dobre Manery' => 15,
                'Psychologia i Empatia' => 10,
                'Obsługa Klienta' => 10,
            ],
            'reputacja' => ['elita' => 3, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 1, 'spoleczenstwo' => 2],
        ],
    ],

    /* ══════════════════════ MARGINES I NIELEGALNE ══════════════════ */

    'Prostytutka' => [
        'opis' => 'Ulica to Twój dom, a ciało to Twój biznes.',
        'wymagania' => ['Sztuka Uwodzenia' => 8, 'Psychologia i Empatia' => 6, 'Znajomość Półświatka' => 5, 'Kondycja i Wytrzymałość' => 3],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Stygmatyzowana przez porządne społeczeństwo, ale z nieoczekiwanymi sojusznikami. Klientami są wszyscy: od biznesmenów po gangsterów — i każdy mówi Ci więcej, niż powinien.',
            'umiejetnosci_bonus_proc' => [
                'Sztuka Uwodzenia' => 25,
                'Psychologia i Empatia' => 15,
                'Znajomość Półświatka' => 10,
                'Moda i Wizerunek' => 10,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 1, 'syndykat' => 1, 'wladze' => -1, 'spoleczenstwo' => -2],
        ],
    ],

    'Przemytnik' => [
        'opis' => 'Załatwisz wszystko, byle bez płacenia cła.',
        'wymagania' => ['Prowadzenie Pojazdów' => 10, 'Znajomość Półświatka' => 8, 'Fałszerstwo Dokumentów' => 6, 'Handel i Wycena' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Szlaki granic są Twoim domem. Syndykaty Cię cenią, władze chciałyby mieć Twoją głowę — ale zawsze zostawiasz trop na innej granicy.',
            'umiejetnosci_bonus_proc' => [
                'Prowadzenie Pojazdów' => 20,
                'Znajomość Półświatka' => 20,
                'Fałszerstwo Dokumentów' => 15,
                'Handel i Wycena' => 10,
                'Zacieranie Śladów' => 10,
            ],
            'reputacja' => ['elita' => 0, 'ulica' => 2, 'syndykat' => 3, 'wladze' => -3, 'spoleczenstwo' => -1],
        ],
    ],

    'Handlarz Bronią' => [
        'opis' => 'Dostarczasz żelazo. Od pistoletu po wyrzutnie rakiet.',
        'wymagania' => ['Handel i Wycena' => 10, 'Znajomość Półświatka' => 10, 'Walka Bronią Palną' => 6, 'Fałszerstwo Dokumentów' => 5, 'Perswazja i Negocjacje' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Każdy gang zna Twoje nazwisko. Każdy najemnik ma Twój numer. Władze nie mogą Cię dorwać bez wojny — bo Twoje sieci sięgają wyżej, niż myślą.',
            'umiejetnosci_bonus_proc' => [
                'Handel i Wycena' => 25,
                'Znajomość Półświatka' => 25,
                'Walka Bronią Palną' => 15,
                'Fałszerstwo Dokumentów' => 15,
                'Perswazja i Negocjacje' => 10,
            ],
            'reputacja' => ['elita' => 1, 'ulica' => 3, 'syndykat' => 4, 'wladze' => -4, 'spoleczenstwo' => -3],
        ],
    ],

    'Diler Narkotyków' => [
        'opis' => 'Zaopatrujesz klubów i slumsów w towar.',
        'wymagania' => ['Chemia i Farmakologia' => 8, 'Handel i Wycena' => 6, 'Znajomość Półświatka' => 10, 'Zacieranie Śladów' => 5, 'Perswazja i Negocjacje' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Klientela od nastolatków po prawników. Każda dawka to życie w Twoich rękach. Ulica Cię szanuje, policja obserwuje, syndykaty chcą swojej działki.',
            'umiejetnosci_bonus_proc' => [
                'Chemia i Farmakologia' => 25,
                'Handel i Wycena' => 20,
                'Znajomość Półświatka' => 20,
                'Zacieranie Śladów' => 15,
                'Perswazja i Negocjacje' => 10,
            ],
            'reputacja' => ['elita' => -2, 'ulica' => 2, 'syndykat' => 3, 'wladze' => -4, 'spoleczenstwo' => -4],
        ],
    ],

    'Kłusownik' => [
        'opis' => 'Polujesz tam, gdzie nie wolno. Sprzedajesz drogo.',
        'wymagania' => ['Myślistwo i Polowanie' => 10, 'Tropienie' => 8, 'Przetrwanie w Dziczy' => 8, 'Zacieranie Śladów' => 6, 'Walka Bronią Palną' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Rzadkie skóry, egzotyczne mięso, trofea dla kolekcjonerów. Rozkaz o Twoją głowę wisi w każdej leśniczówce. Czarny rynek Cię uwielbia, ekolodzy nienawidzą.',
            'umiejetnosci_bonus_proc' => [
                'Myślistwo i Polowanie' => 30,
                'Tropienie' => 25,
                'Przetrwanie w Dziczy' => 20,
                'Zacieranie Śladów' => 15,
                'Walka Bronią Palną' => 10,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 1, 'syndykat' => 2, 'wladze' => -3, 'spoleczenstwo' => -2],
        ],
    ],

    'Handlarz Antykami (Czarny Rynek)' => [
        'opis' => 'Skarby wyniesione z muzeów trafiają przez Twoje biurko.',
        'wymagania' => ['Historia i Antykwariat' => 10, 'Handel i Wycena' => 8, 'Znajomość Półświatka' => 8, 'Fałszerstwo Dokumentów' => 6, 'Etykieta i Dobre Manery' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Kolekcjonerzy z całego świata Cię znają. Każdy artefakt ma historię — a Ty znasz jej wszystkie wersje. Muzea Cię ścigają, elita potajemnie zaprasza.',
            'umiejetnosci_bonus_proc' => [
                'Historia i Antykwariat' => 30,
                'Handel i Wycena' => 20,
                'Znajomość Półświatka' => 15,
                'Fałszerstwo Dokumentów' => 15,
                'Etykieta i Dobre Manery' => 10,
            ],
            'reputacja' => ['elita' => 2, 'ulica' => 0, 'syndykat' => 2, 'wladze' => -3, 'spoleczenstwo' => -1],
        ],
    ],

    'Złodziej Samochodów' => [
        'opis' => 'Każda maszyna na czterech kołach słyszy Twój głos.',
        'wymagania' => ['Włamywanie Elektroniczne' => 8, 'Prowadzenie Pojazdów' => 10, 'Mechanika i Naprawa' => 6, 'Zacieranie Śladów' => 5, 'Skradanie' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Legenda lotnych brygad. Fleet Manager każdej korporacji Cię przeklina. Paserzy czekają w kolejce na Twój towar — świeży, nietypowany, bezzwłocznie na eksport.',
            'umiejetnosci_bonus_proc' => [
                'Włamywanie Elektroniczne' => 25,
                'Prowadzenie Pojazdów' => 25,
                'Mechanika i Naprawa' => 15,
                'Zacieranie Śladów' => 15,
                'Skradanie' => 10,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 3, 'syndykat' => 2, 'wladze' => -3, 'spoleczenstwo' => -2],
        ],
    ],

    'Włamywacz Domowy' => [
        'opis' => 'Ciche buty, wytrych, pusta willa w święta.',
        'wymagania' => ['Otwieranie Zamków (Wytrychy)' => 10, 'Skradanie' => 10, 'Parkour i Freerunning' => 6, 'Zacieranie Śladów' => 5, 'Szpiegostwo i Inwigilacja' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Elita Cię nienawidzi, ulica szanuje profesjonalizm. Pracujesz w cichym rytmie: tydzień obserwacji, noc akcji, miesiąc przestoju. Złote zasady — nikt nie ginie, nikt Cię nie widzi.',
            'umiejetnosci_bonus_proc' => [
                'Otwieranie Zamków (Wytrychy)' => 30,
                'Skradanie' => 25,
                'Parkour i Freerunning' => 15,
                'Zacieranie Śladów' => 15,
                'Szpiegostwo i Inwigilacja' => 10,
            ],
            'reputacja' => ['elita' => -3, 'ulica' => 2, 'syndykat' => 2, 'wladze' => -3, 'spoleczenstwo' => -2],
        ],
    ],

    'Szuler Kasynowy' => [
        'opis' => 'Karty, kości, ruletka — wszystko tańczy dla Ciebie.',
        'wymagania' => ['Sztuka Iluzji (Kuglarstwo)' => 10, 'Matematyka i Rachunkowość' => 8, 'Psychologia i Empatia' => 6, 'Kieszonkostwo' => 5, 'Mistrz Blefu' => 0],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Kasyna Cię nie chcą, ale Twoi klienci wynagrodzą Cię lepiej. Naiwniacy tracą fortuny, szczęściarze odchodzą wygrani — oba scenariusze dają Ci procent.',
            'umiejetnosci_bonus_proc' => [
                'Sztuka Iluzji (Kuglarstwo)' => 30,
                'Matematyka i Rachunkowość' => 20,
                'Psychologia i Empatia' => 15,
                'Kieszonkostwo' => 15,
                'Sztuka Uwodzenia' => 5,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 2, 'syndykat' => 1, 'wladze' => -2, 'spoleczenstwo' => -1],
        ],
    ],

    'Paser' => [
        'opis' => 'Kupisz wszystko bez pytania o dowód. I sprzedasz drożej.',
        'wymagania' => ['Handel i Wycena' => 8, 'Znajomość Półświatka' => 10, 'Zacieranie Śladów' => 6, 'Analiza Danych i Dedukcja' => 4, 'Historia i Antykwariat' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Każdy złodziej w mieście zna Twój adres. Każdy detektyw też — ale brak im dowodów. Ty widzisz tylko towar, nie pochodzenie. To Twoje zawodowe motto.',
            'umiejetnosci_bonus_proc' => [
                'Handel i Wycena' => 25,
                'Znajomość Półświatka' => 25,
                'Zacieranie Śladów' => 20,
                'Historia i Antykwariat' => 10,
                'Analiza Danych i Dedukcja' => 10,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 3, 'syndykat' => 3, 'wladze' => -3, 'spoleczenstwo' => -2],
        ],
    ],

    'Oszust Matrymonialny' => [
        'opis' => 'Kolekcjonujesz serca i majątki. Znikasz przed rozwodem.',
        'wymagania' => ['Sztuka Uwodzenia' => 12, 'Psychologia i Empatia' => 8, 'Etykieta i Dobre Manery' => 6, 'Fałszerstwo Dokumentów' => 5, 'Aktorstwo i Charakteryzacja' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Ofiarami są samotni bogaci i bogate. Twoja twarz pojawia się w galerii FBI z różnymi imionami. Zmieniasz miasta, tożsamości, akcenty — ale zawsze zostawiasz za sobą złamane serca i puste konta.',
            'umiejetnosci_bonus_proc' => [
                'Sztuka Uwodzenia' => 30,
                'Psychologia i Empatia' => 20,
                'Aktorstwo i Charakteryzacja' => 15,
                'Etykieta i Dobre Manery' => 15,
                'Fałszerstwo Dokumentów' => 10,
            ],
            'reputacja' => ['elita' => -3, 'ulica' => 1, 'syndykat' => 1, 'wladze' => -2, 'spoleczenstwo' => -3],
        ],
    ],

    'Alfons / Madame' => [
        'opis' => 'Zarządzasz stajnią. Pilnujesz zysków i bezpieczeństwa.',
        'wymagania' => ['Perswazja i Negocjacje' => 8, 'Zastraszanie' => 8, 'Znajomość Półświatka' => 10, 'Psychologia i Empatia' => 6, 'Zarządzanie i Przywództwo' => 6],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Twój dom schadzek zna każdy wpływowy człowiek w mieście. Sekrety elity są Twoim kapitałem. Syndykaty chcą procent, władze chcą zeznań, ulica chce ochrony.',
            'umiejetnosci_bonus_proc' => [
                'Zarządzanie i Przywództwo' => 25,
                'Znajomość Półświatka' => 20,
                'Zastraszanie' => 15,
                'Perswazja i Negocjacje' => 15,
                'Psychologia i Empatia' => 10,
            ],
            'reputacja' => ['elita' => -2, 'ulica' => 2, 'syndykat' => 3, 'wladze' => -3, 'spoleczenstwo' => -4],
        ],
    ],

    'Porywacz' => [
        'opis' => 'Ofiara, okup, czysta forsa. Robota profesjonalisty.',
        'wymagania' => ['Walka Bronią Palną' => 8, 'Zastraszanie' => 10, 'Zacieranie Śladów' => 8, 'Prowadzenie Pojazdów' => 6, 'Szpiegostwo i Inwigilacja' => 6, 'Taktyka Wojskowa' => 5],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'FBI ma dedykowaną jednostkę do ścigania takich jak Ty. Bogaci zatrudniają ochronę, syndykaty oferują kontrakty. Jedno zadanie to fortuna — lub dożywocie.',
            'umiejetnosci_bonus_proc' => [
                'Zastraszanie' => 25,
                'Walka Bronią Palną' => 20,
                'Zacieranie Śladów' => 20,
                'Szpiegostwo i Inwigilacja' => 15,
                'Taktyka Wojskowa' => 10,
                'Prowadzenie Pojazdów' => 10,
            ],
            'reputacja' => ['elita' => -4, 'ulica' => 1, 'syndykat' => 2, 'wladze' => -5, 'spoleczenstwo' => -5],
        ],
    ],

    'Fałszerz' => [
        'opis' => 'Paszporty, banknoty, obrazy — wszystko prawdziwsze od prawdziwego.',
        'wymagania' => ['Fałszerstwo Dokumentów' => 12, 'Sztuki Plastyczne i Rzemiosło' => 8, 'Analiza Danych i Dedukcja' => 5, 'Historia i Antykwariat' => 5, 'Elektronika' => 4],
        'wymagany_tytul' => null,
        'kategoria' => 'margines',
        'rp' => [
            'opis_spoleczny' => 'Twoje dzieła krążą w obiegu — banknoty w portfelach, paszporty w kieszeniach, obrazy na ścianach galerii. Mistrz nieuchwytny. Nawet Interpol się Cię obawia.',
            'umiejetnosci_bonus_proc' => [
                'Fałszerstwo Dokumentów' => 35,
                'Sztuki Plastyczne i Rzemiosło' => 20,
                'Historia i Antykwariat' => 15,
                'Analiza Danych i Dedukcja' => 10,
                'Elektronika' => 10,
            ],
            'reputacja' => ['elita' => -1, 'ulica' => 2, 'syndykat' => 4, 'wladze' => -4, 'spoleczenstwo' => -2],
        ],
    ],

];


/* ═══════════════════════════════════════════════════════════════════════
   HELPER: zwraca dane zawodu (lub null).
   ═══════════════════════════════════════════════════════════════════════ */
function zawod_dane($nazwa_zawodu) {
    global $ZAWODY_DANE;
    if (!$nazwa_zawodu) return null;
    return $ZAWODY_DANE[$nazwa_zawodu] ?? null;
}