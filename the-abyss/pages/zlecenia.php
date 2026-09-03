<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// Która zakładka
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'fabularne';
if (!in_array($tab, ['fabularne','kontrakty'])) $tab = 'fabularne';

// POBRANIE DANYCH GRACZA
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$komunikat = "";
$dzisiaj = date('Y-m-d');
$limit_dzienny = 5;

// RESET LIMITU ZLECEŃ CODZIENNIE
if ($gracz['zlecenia_data'] != $dzisiaj) {
    $polaczenie->query("UPDATE gracze SET zlecenia_wykonane_dzis=0, zlecenia_data='$dzisiaj' WHERE id=$id_gracza");
    $polaczenie->query("DELETE FROM zlecenia WHERE gracz_id=$id_gracza AND status='dostepne'");
    $gracz['zlecenia_wykonane_dzis'] = 0;
}

// ═══════════════════════════════════════════════════════════════
// GENERATOR ZLECEŃ FABULARNYCH (Twoja matryca 34 zawodów — NIE TYKAM)
// ═══════════════════════════════════════════════════════════════
function losujZlecenie($zawod) {
    $matryce = [
        'Ogólne' => [
            'kto' => ['Podejrzany typ', 'Zdesperowany korpos', 'Lokalny barman', 'Haker z darknetu', 'Drobny złodziejaszek'],
            'co' => ['zleca odzyskanie', 'szuka frajera do przeniesienia', 'wymaga eskorty dla', 'płaci za zniszczenie'],
            'cel' => ['paczki z nieznaną zawartością', 'dysku z danymi', 'torby pełnej brudnej gotówki', 'kradzionego drona'],
            'gdzie' => ['na stacji metra', 'w opuszczonej fabryce', 'w dokach', 'w ciemnym zaułku'],
            'haczyk' => ['ale teren jest obserwowany.', 'tylko zrób to po cichu.', 'ale spodziewaj się kłopotów.', 'i płaci ekstra za milczenie.']
        ],
        'Sprzedawca' => [
            'kto' => ['Stary przemytnik', 'Bogaty klient', 'Członek gangu', 'Skorumpowany glina'],
            'co' => ['chce kupić spod lady', 'każe ci wycenić', 'przynosi do lombardu', 'żąda zniżki na'],
            'cel' => ['kradziony sprzęt wojskowy', 'pudełko starych zegarków', 'złotą biżuterię z krwią', 'nielegalne leki'],
            'gdzie' => ['w twoim sklepie', 'na zapleczu', 'pod osłoną nocy', 'w podziemnym garażu'],
            'haczyk' => ['ale towar jest gorący.', 'i grozi, że spali ci budę.', 'tylko nie zadawaj pytań.', 'a ty musisz to upłynnić do rana.']
        ],
        'Barman' => [
            'kto' => ['Pijany korpos', 'Nerwowy Fixer', 'Płatny zabójca', 'Szef gangu'],
            'co' => ['wypytuje o', 'zostawia ci', 'płaci za informacje o', 'żąda drinka i opowiada o'],
            'cel' => ['tajne hasła do sejfu', 'paczkę dla łącznika', 'pewnej rudej dziewczynie', 'transporcie broni'],
            'gdzie' => ['przy barze', 'w toalecie', 'w loży VIP', 'na zapleczu klubu'],
            'haczyk' => ['ale lokal jest na podsłuchu.', 'podczas gdy ktoś celuje do niego z lasera.', 'i musisz odwrócić uwagę ochrony.', 'a potem wybucha strzelanina.']
        ],
        'Krawiec' => [
            'kto' => ['Boss Syndykatu', 'Ranny najemnik', 'Gwiazda porno', 'Skrytobójca'],
            'co' => ['zamawia uszycie', 'prosi o załatanie', 'zleca przeróbkę', 'żąda wyczyszczenia'],
            'cel' => ['garnituru z ukrytym kevlarem', 'zakrwawionego płaszcza', 'sukni z ukrytą kaburą', 'kamizelki taktycznej'],
            'gdzie' => ['w twojej pracowni', 'w pokoju hotelowym', 'na zapleczu kasyna', 'w piwnicy'],
            'haczyk' => ['ale materiał jest kradziony.', 'zanim przyjadą gliny.', 'i musisz użyć nielegalnych włókien.', 'ale klient nie ma zamiaru zapłacić.']
        ],
        'Hydraulik / Konserwator' => [
            'kto' => ['Właściciel klubu', 'Szef bimbrowni', 'Gangster', 'Zarządca bloku'],
            'co' => ['zleca naprawę', 'każe zamontować', 'żąda przepchania', 'płaci za sabotaż'],
            'cel' => ['rury z toksycznym odpadem', 'ukrytego przejścia w ścianie', 'odpływu pełnego łusek', 'głównego zaworu wody'],
            'gdzie' => ['w kanałach', 'w luksusowym apartamencie', 'w piwnicy rzeźnika', 'w stacji pomp'],
            'haczyk' => ['ale w wodzie są zmutowane szczury.', 'zanim teren zaleje kwas.', 'i musisz to zrobić w całkowitej ciemności.', 'ale instalacja grozi wybuchem.']
        ],
        'Tatuażysta' => [
            'kto' => ['Uciekinier z sekty', 'Członek Yakuzzy', 'Psychopata', 'Agent pod przykrywką'],
            'co' => ['chce usunąć', 'zamawia', 'płaci za wydziaranie', 'zleca zakrycie'],
            'cel' => ['tatuaż wrogiego gangu', 'mapę kanałów na plecach', 'kodu dostępu na przedramieniu', 'blizn po oparzeniach'],
            'gdzie' => ['w brudnym studiu', 'na zapleczu meliny', 'w luksusowym apartamencie', 'w policyjnym areszcie'],
            'haczyk' => ['ale sprzęt jest niesterylny.', 'i klient jest agresywny po dragach.', 'tylko musisz użyć atramentu UV.', 'a on nie pozwala użyć znieczulenia.']
        ],
        'Kierowca Tira' => [
            'kto' => ['Mroczna Korporacja', 'Syndykat', 'Handlarz bronią', 'Szmugler'],
            'co' => ['zleca przewóz', 'żąda przetransportowania', 'płaci za ewakuację', 'kieruje konwój'],
            'cel' => ['nielegalnych chemikaliów', 'skrzyń z bronią maszynową', 'zbiegłych więźniów', 'kradzionych serwerów'],
            'gdzie' => ['przez pustkowia Sektora 9', 'autostradą śmierci', 'przez blokady policyjne', 'do opuszczonej rafinerii'],
            'haczyk' => ['ale droga jest patrolowana przez drony.', 'a ładunek jest wysoce wybuchowy.', 'i na ogonie masz łowców nagród.', 'przy niemal pustym baku.']
        ],
        'Spedytor' => [
            'kto' => ['Boss logistyki', 'Właściciel fabryki', 'Przemytnik', 'Zarządca Doków'],
            'co' => ['żąda zmiany trasy', 'zleca fałszowanie listów przewozowych dla', 'płaci za przepuszczenie', 'wymaga zorganizowania transportu'],
            'cel' => ['konwoju z lekami', 'ciężarówek z kokainą', 'transportu nielegalnych imigrantów', 'części do mechów'],
            'gdzie' => ['w głównym hubie przeładunkowym', 'w bazie komputerowej portu', 'na granicy sektorów', 'w centrum dowodzenia'],
            'haczyk' => ['bo konkurencja planuje zasadzkę.', 'zanim inspekcja sprawdzi papiery.', 'ale celnicy węsza w systemie.', 'wymagając ominięcia cła.']
        ],
        'Malarz' => [
            'kto' => ['Ekscentryczny bogacz', 'Szef sekty', 'Wdowa po mafiosie', 'Kolekcjoner sztuki'],
            'co' => ['zamawia portret', 'zleca renowację', 'płaci za namalowanie', 'żąda stworzenia kopii'],
            'cel' => ['zmarłej żony', 'ukrytego sejfu w ścianie', 'muralu propagandowego', 'kradzionego arcydzieła'],
            'gdzie' => ['w luksusowym penthousie', 'na zniszczonym murze w slumsach', 'w podziemnej galerii', 'w prywatnej krypcie'],
            'haczyk' => ['z użyciem farby z domieszką krwi.', 'ale musisz ukryć w obrazie szyfr.', 'i goni cię termin otwarcia wystawy.', 'podczas trwającej imprezy.']
        ],
        'Pisarz' => [
            'kto' => ['Płatny zabójca', 'Polityk', 'Wydawca', 'Szantażysta'],
            'co' => ['zleca napisanie', 'płaci za zredagowanie', 'żąda stworzenia', 'wymaga poprawienia'],
            'cel' => ['fałszywego listu samobójczego', 'manifestu terrorystycznego', 'paszkwilu na konkurenta', 'zakodowanej wiadomości'],
            'gdzie' => ['w taniej kawalerce', 'w bibliotece miejskiej', 'w kawiarence internetowej', 'w opuszczonej redakcji'],
            'haczyk' => ['i ma to być gotowe na rano.', 'żeby nikt nie poznał twojego stylu.', 'ale tekst musi zawierać tajne hasła.', 'a zleceniodawca trzyma cię na muszce.']
        ],
        'Dziennikarz Śledczy' => [
            'kto' => ['Tajemniczy informator', 'Były policjant', 'Zwolniony naukowiec', 'Haker'],
            'co' => ['daje cynk o', 'przekazuje dokumenty o', 'zleca śledztwo w sprawie', 'żąda opublikowania artykułu o'],
            'cel' => ['korupcji w ratuszu', 'tajnym laboratorium na przedmieściach', 'morderstwie burmistrza', 'brudnych interesach korporacji'],
            'gdzie' => ['w podziemnym parkingu', 'w archiwach miejskich', 'na opuszczonym wysypisku', 'w strefie kwarantanny'],
            'haczyk' => ['ale ryzykujesz życiem swoim i bliskich.', 'a korporacja wysłała już czyścicieli.', 'więc musisz sfalszować swoje nazwisko.', 'ale redaktor naczelny próbuje cię uciszyć.']
        ],
        'Aktor' => [
            'kto' => ['Reżyser filmów B', 'Gangster', 'Zdesperowany mąż', 'Służby specjalne'],
            'co' => ['każe ci zagrać', 'wynajmuje cię jako', 'płaci za udawanie', 'żąda byś wcielił się w'],
            'cel' => ['rolę skorumpowanego policjanta', 'sobowtóra szefa mafii', 'zaginionego dziedzica fortuny', 'ofiarę porwania'],
            'gdzie' => ['na brutalnym planie filmowym', 'podczas wymiany ognia w banku', 'na eleganckim bankiecie', 'w opuszczonym magazynie'],
            'haczyk' => ['żeby zmylić snajpera.', 'ale na planie używana jest ostra amunicja.', 'i nie możesz wyjść z roli nawet na moment.', 'aby wyciągnąć informacje od podejrzanych.']
        ],
        'Muzyk' => [
            'kto' => ['Właściciel klubu', 'Boss narkotykowy', 'Korporacyjny menedżer', 'Producent muzyczny'],
            'co' => ['zamawia koncert', 'zleca zagranie w', 'płaci za stworzenie bitu do', 'żąda występu dla'],
            'cel' => ['loży pełnej VIP-ów', 'podziemnym kasynie', 'tajnej wiadomości podprogowej', 'zgromadzenia fanatyków'],
            'gdzie' => ['w zadymionym pubie', 'na dachu luksusowego hotelu', 'w tajnym studiu nagrań', 'na prywatnym jachcie'],
            'haczyk' => ['podczas którego dojdzie do morderstwa.', 'ale muzyka ma zagłuszyć krzyki torturowanych.', 'i musisz zadowolić bardzo wybredną publikę.', 'grając na kradzionym sprzęcie.']
        ],
        'Tancerz / Tancerka' => [
            'kto' => ['Bogaty biznesmen', 'Właściciel striptiz-klubu', 'Ochroniarz VIP', 'Złodziej'],
            'co' => ['zamawia prywatny taniec', 'zleca występ w', 'płaci za zabawianie', 'żąda odwrócenia uwagi'],
            'cel' => ['ochrony kasyna', 'polityka z walizką pieniędzy', 'szefa ochrony', 'grupy niebezpiecznych najemników'],
            'gdzie' => ['w klubie nocnym', 'na prywatnym jachcie', 'w luksusowej rezydencji', 'na tyłach baru'],
            'haczyk' => ['żeby ktoś mógł ukraść portfel ofiary.', 'ale klient jest bardzo agresywny.', 'podczas gdy w tle trwa włamanie do sejfu.', 'musisz użyć w tańcu niebezpiecznych akrobacji.']
        ],
        'Reżyser' => [
            'kto' => ['Producent z półświatka', 'Lider propagandy', 'Szef gangu', 'Korporacja'],
            'co' => ['zleca nakręcenie', 'żąda zmontowania', 'płaci za wyreżyserowanie', 'wymaga stworzenia'],
            'cel' => ['filmu propagandowego', 'nagrania z fałszywą egzekucją', 'reklamy nielegalnych substancji', 'kompromitującego wideo na burmistrza'],
            'gdzie' => ['w zrujnowanej dzielnicy', 'w profesjonalnym studiu', 'na żywo podczas strzelaniny', 'w zamkniętej strefie wojskowej'],
            'haczyk' => ['z prawdziwą amunicją na planie.', 'a aktorzy to zmuszeni do tego zakładnicy.', 'i masz zakaz używania efektów specjalnych.', 'ale policja właśnie wpadła na plan.']
        ],
        'Projektant Mody' => [
            'kto' => ['Żona bossa mafii', 'Zabójca', 'Celebryta', 'Szpieg przemysłowy'],
            'co' => ['żąda zaprojektowania', 'zleca uszycie', 'płaci za stworzenie', 'wymaga przygotowania'],
            'cel' => ['sukni balowej na wielką galę', 'garnituru kuloodpornego', 'kreacji szokującej opinię publiczną', 'płaszcza z ukrytymi kamerami'],
            'gdzie' => ['w elitarnym atelier', 'w podziemnej szwalni', 'na wybiegu podczas pokazu', 'w pokoju hotelowym VIP'],
            'haczyk' => ['z ukrytą kaburą na pistolet.', 'i materiał kosztuje więcej niż twoje życie.', 'ale kreacja musi ukryć pistolet maszynowy.', 'zanim klient ucieknie z miasta.']
        ],
        'Nauczyciel' => [
            'kto' => ['Zdesperowany ojciec', 'Gangster', 'Dyrektor sierocińca', 'Zbiegły dzieciak'],
            'co' => ['płaci za korepetycje', 'zleca edukację', 'żąda przygotowania do testu', 'wymaga nauczenia czytania'],
            'cel' => ['dla agresywnego syna z gangu', 'niepiśmiennego mordercy', 'grupy kieszonkowców', 'dziedzica zbankrutowanej korporacji'],
            'gdzie' => ['w starej szkole', 'w dziupli gangu', 'w miejskiej bibliotece', 'na zapleczu lombardu'],
            'haczyk' => ['a uczeń położył na stole naładowany pistolet.', 'żeby mógł przeczytać zaszyfrowane dokumenty.', 'ale za błędy grozi pobicie.', 'ukrywając przy tym waszą lokalizację.']
        ],
        'Wykładowca Akademicki' => [
            'kto' => ['Dziekan uczelni', 'Korporacyjny lobbysta', 'Zdesperowany student', 'Służby wywiadowcze'],
            'co' => ['każe ci oblać na egzaminie', 'płaci za wygłoszenie wykładu o', 'zleca kradzież testów z', 'żąda sfałszowania oceny dla'],
            'cel' => ['studenta z biednej rodziny', 'nowej zbrojeniowej technologii', 'anatomii zmutowanych zwierząt', 'syna ważnego polityka'],
            'gdzie' => ['w auli głównej', 'w gabinecie profesorskim', 'w tajnym skrzydle uniwersytetu', 'na konferencji naukowej'],
            'haczyk' => ['bo zapłaciła za to korporacja.', 'ale studenci grożą ci śmiercią.', 'i ryzykujesz utratą posady oraz wolności.', 'a w sali jest wtyczka z policji.']
        ],
        'Księgowy' => [
            'kto' => ['Syndykat Zbrodni', 'Skorumpowany urzędnik', 'Pralnia pieniędzy', 'Bogaty biznesmen'],
            'co' => ['zleca wypranie', 'każe sfałszować księgi dla', 'płaci za ukrycie', 'żąda audytu w'],
            'cel' => ['miliona dolarów z haraczy', 'firmy-krzaka', 'podatków przed urzędem skarbowym', 'kasynie pełnym brudnej forsy'],
            'gdzie' => ['przez fikcyjne spółki', 'w zaszyfrowanej bazie danych', 'w archiwach miejskich', 'na zabezpieczonym laptopie'],
            'haczyk' => ['pod okiem skarbówki i policji.', 'i musisz zdążyć przed porannym nalotem.', 'ale w księgach brakuje pół miliona.', 'ryzykując zemstę oszukanych gangsterów.']
        ],
        'Przedsiębiorca' => [
            'kto' => ['Inwestor z zagranicy', 'Lokalny watażka', 'Konkurent rynkowy', 'Związkowiec'],
            'co' => ['proponuje fuzję', 'żąda haraczu za', 'płaci za sabotaż w', 'zleca przejęcie'],
            'cel' => ['z firmą-krzakiem', 'ochronę twoich magazynów', 'fabryce konkurencji', 'nielegalnego biznesu'],
            'gdzie' => ['w szklanym biurowcu', 'na spotkaniu w podziemnym parkingu', 'w strefie przemysłowej', 'podczas oficjalnego bankietu'],
            'haczyk' => ['żeby zmonopolizować rynek.', 'ale to ewidentna pułapka.', 'i musisz to ukryć przed radą nadzorczą.', 'wymagając bezwzględnych negocjacji.']
        ],
        'Prokurator' => [
            'kto' => ['Skorumpowany sędzia', 'Burmistrz', 'Szef policji', 'Mafijny don'],
            'co' => ['naciska na oskarżenie', 'płaci za uciszenie sprawy o', 'zleca ukrycie dowodów przeciwko', 'żąda surowego wyroku dla'],
            'cel' => ['niewinnego kozła ofiarnego', 'brutalnym mordercy z gangu', 'skorumpowanym oficerze', 'dziennikarza śledczego'],
            'gdzie' => ['w sądzie najwyższym', 'w tajnych aktach prokuratury', 'podczas rozprawy sądowej', 'w sali przesłuchań'],
            'haczyk' => ['by chronić prawdziwego sprawcę.', 'zanim prasa dowie się prawdy.', 'ale oskarżony ma potężnych znajomych.', 'naginając przy tym prawo do granic.']
        ],
        'Adwokat' => [
            'kto' => ['Szef Syndykatu', 'Bogaty biznesmen', 'Skorumpowany radny', 'Rodzina seryjnego mordercy'],
            'co' => ['zleca obronę', 'płaci za wyciągnięcie z aresztu', 'potrzebuje alibi dla', 'żąda wyczyszczenia akt dla'],
            'cel' => ['członka gangu', 'brutalnego mordercy', 'przemytnika broni', 'księgowego mafii'],
            'gdzie' => ['przed sądem najwyższym', 'w tajnych aktach prokuratury', 'podczas przesłuchania policyjnego', 'w areszcie miejskim'],
            'haczyk' => ['ale dowody są twarde jak stal.', 'i masz na to tylko kilka godzin.', 'ale prokurator jest nieprzekupny.', 'kosztem zniszczenia reputacji sędziego.']
        ],
        'Klawisz (Strażnik Więzienny)' => [
            'kto' => ['Niebezpieczny więzień', 'Koledzy z bloku C', 'Prawnik mafii', 'Przemytnik'],
            'co' => ['daje łapówkę za', 'żąda przymknięcia oka na', 'płaci za zaaranżowanie', 'zleca pobicie'],
            'cel' => ['przemycenie telefonu', 'bójkę pod prysznicami', 'widzenia bez świadków', 'nowego więźnia z gangu rywali'],
            'gdzie' => ['w izolatce', 'na spacerniaku', 'w stołówce więziennej', 'w bloku zaostrzonym'],
            'haczyk' => ['ale naczelnik ma dziś inspekcję.', 'i nie możesz zostawić śladów na kamerach.', 'ryzykując buntem osadzonych.', 'a ofiara jest pod ochroną klawiszy.']
        ],
        'Ochroniarz' => [
            'kto' => ['Menedżer klubu', 'Gwiazda pop', 'Szef mafii', 'Właściciel kasyna'],
            'co' => ['każe wyrzucić', 'płaci za eskortę przez', 'zleca pilnowanie', 'żąda zatrzymania'],
            'cel' => ['agresywnych ćpunów', 'niebezpieczną dzielnicę', 'walizki z gotówką', 'nieproszonych gości'],
            'gdzie' => ['przed klubem nocnym', 'w korytarzach VIP', 'na podziemnym parkingu', 'w głównej sali kasyna'],
            'haczyk' => ['którzy wyciągnęli noże.', 'i musisz przyjąć na siebie ewentualną kulę.', 'bez wywoływania paniki w tłumie.', 'ale nie masz pozwolenia na broń.']
        ],
        'Prostytutka' => [
            'kto' => ['Stary klient', 'Bogaty polityk', 'Detektyw', 'Młody gangster'],
            'co' => ['wynajmuje cię by zdobyć alibi', 'płaci za milczenie o', 'zleca wyciągnięcie informacji od', 'żąda towarzystwa na'],
            'cel' => ['pewnego dyrektora banku', 'szefa ochrony', 'śliskiego handlarza bronią', 'zabójcy na zlecenie'],
            'gdzie' => ['w tanim motelu', 'w luksusowym apartamencie', 'w tylnej loży klubu', 'na zamkniętej imprezie'],
            'haczyk' => ['podczas gdy jego ludzie obrabiają bank.', 'a klient ma przy sobie broń.', 'i musisz wykorzystać cały swój urok.', 'zanim zorientuje się, kim jesteś.']
        ],
        'Przemytnik' => [
            'kto' => ['Handlarz bronią', 'Szef kartelu', 'Zbieg', 'Lekarz z czarnego rynku'],
            'co' => ['zleca przerzut', 'płaci za dostarczenie', 'żąda wywiezienia', 'wymaga eskortowania'],
            'cel' => ['skrzyń z amunicją', 'partii syntetycznych narkotyków', 'poszukiwanego mordercy', 'kradzionych organów do przeszczepu'],
            'gdzie' => ['przez granicę sektora', 'w bagażniku pełnym warzyw', 'podziemnymi tunelami', 'przez blokadę drogową'],
            'haczyk' => ['a policja właśnie dostała cynk.', 'przy użyciu sfałszowanych przepustek.', 'ale ładunek tyka.', 'i nie możesz przekroczyć 50 km/h.']
        ],
        'Detektyw Uliczny' => [
            'kto' => ['Zdradzona żona', 'Biznesmen', 'Szef policji', 'Anonimowy klient'],
            'co' => ['wynajmuje cię do śledzenia', 'płaci za znalezienie', 'zleca zrobienie zdjęć', 'żąda zdobycia haków na'],
            'cel' => ['męża-korposa', 'zaginionej córki', 'skorumpowanego sędziego', 'szantażysty'],
            'gdzie' => ['w podejrzanym motelu', 'na starym magazynie', 'w ekskluzywnym klubie', 'na opuszczonej stacji benzynowej'],
            'haczyk' => ['a sprawa łączy się z morderstwem.', 'i musisz uważać na wrogie kule.', 'ale twoje życie wisi na włosku.', 'zanim zdążą wykasować nagrania z monitoringu.']
        ],
        'Medyk Uliczny' => [
            'kto' => ['Gangster', 'Zdesperowana matka', 'Płatny zabójca', 'Uciekinier z więzienia'],
            'co' => ['płaci za zszycie', 'błaga o wyleczenie', 'zleca wyciągnięcie kuli z', 'żąda amputacji u'],
            'cel' => ['rany postrzałowej', 'paskudnej infekcji', 'poparzonego członka gangu', 'cieżko rannego najemnika'],
            'gdzie' => ['w ciemnej piwnicy', 'na tylnym siedzeniu auta', 'w melinie narkomanów', 'w opuszczonej klinice'],
            'haczyk' => ['bez znieczulenia i po cichu.', 'ale nie masz sterylnych narzędzi.', 'a krew zalewa całą podłogę.', 'podczas gdy ktoś dobija się do drzwi.']
        ],
        'Lekarz Rodzinny' => [
            'kto' => ['Polityk', 'Korpos', 'Bogata dziedziczka', 'Aktor'],
            'co' => ['prosi o wypisanie', 'płaci za sfałszowanie', 'żąda dyskretnego leczenia', 'zleca przeprowadzenie terapii dla'],
            'cel' => ['fałszywego zwolnienia lekarskiego', 'recepty na silne opiaty', 'wyników badań krwi', 'rany po nożu'],
            'gdzie' => ['w jasnej, sterylnej klinice', 'w prywatnym gabinecie', 'w luksusowej rezydencji', 'na szpitalnym oddziale VIP'],
            'haczyk' => ['i nikt nie może się o tym dowiedzieć.', 'ryzykując utratę licencji medycznej.', 'ale pacjent zaczyna majaczyć.', 'a ty wiesz, że przyczyna była kryminalna.']
        ],
        'Haker' => [
            'kto' => ['Fixer', 'Grupa rebeliantów', 'Korporacja zbrojeniowa', 'Szantażysta'],
            'co' => ['płaci za zhakowanie', 'zleca złamanie zabezpieczeń', 'żąda pobrania bazy danych z', 'wymaga wykasowania nagrań z'],
            'cel' => ['prywatnego konta bankowego', 'serwerów policji miejskiej', 'systemu monitoringu w kasynie', 'mainframe-u rywalizującej firmy'],
            'gdzie' => ['w podziemnym bunkerze', 'siedząc w furgonetce z anteną', 'podpięty pod lokalny terminal', 'z opuszczonej serwerowni'],
            'haczyk' => ['ale lód (ICE) jest bardzo agresywny.', 'zanim odpalą się protokoły alarmowe.', 'i musisz zostawić wirusa na odchodne.', 'a namierzają twój sygnał!']
        ],
        'Płatny Zabójca' => [
            'kto' => ['Anonimowy klient', 'Zdradzony mąż', 'Boss mafii', 'Zarząd korporacji'],
            'co' => ['zleca szybką eliminację', 'płaci za otrucie', 'żąda zlikwidowania za pomocą snajperki', 'wymaga "zniknięcia"'],
            'cel' => ['świadka koronnego', 'niewygodnego dziennikarza', 'lidera gangu rywali', 'złodzieja technologii'],
            'gdzie' => ['w bezpiecznym domu policji', 'w restauracji pełnej ludzi', 'przez okno z sąsiedniego budynku', 'w ciemnej alejce'],
            'haczyk' => ['pozorując nieszczęśliwy wypadek.', 'ale ofiara ma osobistą ochronę.', 'musisz zostawić fałszywe ślady.', 'strzelając tylko i wyłącznie w głowę.']
        ],
        'Taksówkarz' => [
            'kto' => ['Przerażony korpos', 'Ranny najemnik', 'Kobieta z walizką', 'Agent pod przykrywką'],
            'co' => ['chce byś szybko zawiózł', 'płaci za śledzenie', 'żąda zgubienia pościgu z', 'zleca przewiezienie ukrytego'],
            'cel' => ['czarnego sedana', 'tajemniczej uciekinierki', 'podejrzanego ładunku', 'ważnego VIP-a'],
            'gdzie' => ['przez wąskie uliczki slumsów', 'do podziemnej kliniki', 'na lotnisko', 'na drugą stronę rzeki'],
            'haczyk' => ['a na ogonie macie gliny.', 'tylko nie zadawaj pytań.', 'ale klient krwawi na tapicerkę.', 'jadąc pod prąd na autostradzie.']
        ],
        'Kelner' => [
            'kto' => ['Tajniak', 'Właściciel kasyna', 'Zdradzona kochanka', 'Haker'],
            'co' => ['płaci za dosypanie', 'zleca podsłuchanie rozmowy', 'żąda rozlania wina na', 'prosi o podsunięcie pluskwy do'],
            'cel' => ['proszku nasennego do drinka VIP-a', 'spotkania mafijnych donów', 'eleganckiego polityka', 'stolika korporacyjnych dyrektorów'],
            'gdzie' => ['w luksusowej restauracji', 'na zamkniętej imprezie', 'w zadymionym klubie jazzowym', 'na prywatnym jachcie'],
            'haczyk' => ['ale ochrona patrzy ci na ręce.', 'z zachowaniem pełnego uśmiechu.', 'zanim ofiara zorientuje się w spisku.', 'a klient jest bardzo agresywny.']
        ],
        'Budowlaniec' => [
            'kto' => ['Kierownik budowy', 'Członek syndykatu', 'Inżynier miejski', 'Skorumpowany radny'],
            'co' => ['każe ci zamurować', 'płaci za wyburzenie', 'zleca wylanie betonu na', 'żąda zmontowania rusztowania do'],
            'cel' => ['ciało w fundamencie', 'ścianę do skarbca', 'nielegalnego magazynu', 'szybu wentylacyjnego'],
            'gdzie' => ['na budowie wieżowca', 'w opuszczonej fabryce', 'w tunelach pod rzeką', 'na dachu kasyna'],
            'haczyk' => ['zanim przyjedzie inspekcja budowlana.', 'pod osłoną nocy.', 'ryzykując zawaleniem się stropu.', 'z użyciem fatalnej jakości materiałów.']
        ],
        'Szef Kuchni' => [
            'kto' => ['Menedżer kasyna', 'Boss włoskiej mafii', 'Zblazowany polityk', 'Koneser ekstrawagancji'],
            'co' => ['żąda przygotowania', 'zleca ugotowanie', 'płaci za organizację bankietu z', 'wymaga stworzenia potrawy z'],
            'cel' => ['nielegalnej koniny', 'zatrutych, rzadkich ryb', 'skradzionych, syntetycznych trufli', 'najdroższego mięsa w The Abyss'],
            'gdzie' => ['na spotkaniu na szczycie', 'w podziemnej restauracji', 'na jachcie', 'w prywatnej rezydencji VIP-a'],
            'haczyk' => ['ale jeden błąd w smaku i pożegnasz się z życiem.', 'i musisz przemycić w jedzeniu wiadomość.', 'ale składniki są silnie toksyczne.', 'tylko nie pytaj, skąd to mięso.']
        ],
        'Cukiernik' => [
            'kto' => ['Rodzina mafijna', 'Przemytnik', 'Właściciel klubu', 'Zamachowiec'],
            'co' => ['zamawia upieczenie', 'płaci za przygotowanie', 'żąda stworzenia', 'zleca dostarczenie'],
            'cel' => ['piętrowego tortu z ukrytą bronią', 'zatrutych babeczek', 'pudełka pączków wypełnionych diamentami', 'deseru naszpikowanego narkotykami'],
            'gdzie' => ['na wesele córki bossa', 'do celi więziennej', 'na zamknięte przyjęcie', 'do apartamentu polityka'],
            'haczyk' => ['ale krem musi smakować idealnie.', 'zanim polewa zdąży zastygnąć.', 'i nikt nie może poznać zawartości.', 'ale cukier spłonął w piecu.']
        ],
        'Miejski Botanik (Ogrodnik)' => [
            'kto' => ['Zabójca', 'Diler narkotykowy', 'Ekscentryczny milioner', 'Szef kuchni'],
            'co' => ['zamawia wyhodowanie', 'płaci za skrzyżowanie', 'zleca pielęgnację', 'żąda dostarczenia'],
            'cel' => ['rzadkiej trującej rośliny', 'psychotropowych grzybów z piwnicy', 'mięsożernego bluszczu', 'halucynogennych orchidei'],
            'gdzie' => ['w zaparowanej szklarni', 'w opuszczonym parku', 'na balkonie drapacza chmur', 'w domowym laboratorium botanicznym'],
            'haczyk' => ['której opary są śmiertelnie niebezpieczne.', 'i musisz uważać na oparzenia chemiczne.', 'zanim rośliny zwiędną od smogu.', 'ale gleba jest skażona kwasem.']
        ],
        'Architekt' => [
            'kto' => ['Syndykat', 'Zarząd Korporacji', 'Skorumpowany urzędnik', 'Szef gangu'],
            'co' => ['żąda zaprojektowania', 'płaci za poprawki w', 'zleca stworzenie planów', 'wymaga wytyczenia'],
            'cel' => ['tajnego skarbca', 'luksusowego penthousu', 'podziemnego, zbrojonego bunkra', 'ukrytych szybów ewakuacyjnych'],
            'gdzie' => ['pod nowym kasynem', 'w dzielnicy rządowej', 'na fundamentach starej fabryki', 'w wieżowcu bankowym'],
            'haczyk' => ['i usunięcia oryginalnych planów z bazy miasta.', 'ale konstrukcja nie spełnia norm bezpieczeństwa.', 'musisz zabezpieczyć obiekt przed bombami.', 'zanim inwestor dowie się o błędach.']
        ]
        // Skróciłam dla czytelności — w Twoim pliku ZOSTAW pełną matrycę wszystkich 34 zawodów!
    ];
    $matryca = $matryce[$zawod] ?? $matryce['Ogólne'];
    $kto    = $matryca['kto'][array_rand($matryca['kto'])];
    $co     = $matryca['co'][array_rand($matryca['co'])];
    $cel    = $matryca['cel'][array_rand($matryca['cel'])];
    $gdzie  = $matryca['gdzie'][array_rand($matryca['gdzie'])];
    $haczyk = $matryca['haczyk'][array_rand($matryca['haczyk'])];
    $tresc  = "<b>$kto</b> $co <b>$cel</b> $gdzie, <span style='color:#ffaa00'>$haczyk</span>";
    $czas   = rand(15,60);
    return [
        'tresc'=>$tresc, 'kasa'=>$czas*rand(20,35), 'exp'=>$czas*2,
        'czas'=>$czas, 'energia'=>ceil($czas/10)
    ];
}

// ═══════════════════════════════════════════════════════════════
// GENERATOR KONTRAKTÓW KLASOWYCH
// ═══════════════════════════════════════════════════════════════
$kontrakty_szablony = [
    'Szabrownik' => [
        [
            'zleceniodawca' => 'Stary Juno',
            'dzielnica'     => 'Staten Island',
            'tytul'         => 'Nadgodziny u Juno',
            'tresc'         => 'Chłopie, mam deadline od bossa a moi ludzie się pochorowali. Przynieś mi {ilosc} Stalowego Złomu w {dni} dni i dobrze zapłacę.',
            'typ_celu'      => 'zbierz_stal',
            'ilosc_min'=>60, 'ilosc_max'=>120, 'dni'=>2,
            'trudnosc'=>'latwy',
            'kasa_min'=>400, 'kasa_max'=>700, 'exp'=>50, 'rep'=>25,
        ],
        [
            'zleceniodawca' => 'Fat Tony',
            'dzielnica'     => 'Brooklyn Navy Yard',
            'tytul'         => 'Zlecenie dla Rodziny',
            'tresc'         => 'Moi ludzie potrzebują {ilosc} Części Mechanicznych. Szybko, w {dni} dni. Rodzina nie lubi czekać.',
            'typ_celu'      => 'zbierz_czesci',
            'ilosc_min'=>25, 'ilosc_max'=>50, 'dni'=>3,
            'trudnosc'=>'normalny',
            'kasa_min'=>1200, 'kasa_max'=>1800, 'exp'=>100, 'rep'=>40,
        ],
        [
            'zleceniodawca' => 'Madame Rosa',
            'dzielnica'     => 'Bronx Garages',
            'tytul'         => 'Zamówienie z garażu',
            'tresc'         => 'Chłopaki rozbierają coś dużego. Potrzebuję {ilosc} Kevlaru w {dni} dni. Cash-in-hand.',
            'typ_celu'      => 'zbierz_syn',
            'ilosc_min'=>15, 'ilosc_max'=>30, 'dni'=>3,
            'trudnosc'=>'normalny',
            'kasa_min'=>1400, 'kasa_max'=>2200, 'exp'=>120, 'rep'=>50,
        ],
        [
            'zleceniodawca' => 'Dr. Feng',
            'dzielnica'     => 'Chinatown',
            'tytul'         => 'Specjalne zamówienie',
            'tresc'         => 'Potrzebuję dokładnie {ilosc} Elektroniki. Nie pytaj po co. Masz {dni} dni.',
            'typ_celu'      => 'zbierz_elek',
            'ilosc_min'=>12, 'ilosc_max'=>25, 'dni'=>4,
            'trudnosc'=>'trudny',
            'kasa_min'=>2500, 'kasa_max'=>4000, 'exp'=>200,
            'narzedzie'=>'Wykrywacz EMP', 'rep'=>70,
        ],
        [
            'zleceniodawca' => 'Shadow Collector',
            'dzielnica'     => 'Manhattan Metro',
            'tytul'         => 'Łowca artefaktów',
            'tresc'         => 'Zbieram artefakty z Metra. Przynieś mi {ilosc} sztuk w {dni} dni. Legalne? Kogo to obchodzi.',
            'typ_celu'      => 'zbierz_artefakt',
            'ilosc_min'=>2, 'ilosc_max'=>4, 'dni'=>5,
            'trudnosc'=>'elitarny',
            'kasa_min'=>6000, 'kasa_max'=>10000, 'exp'=>400,
            'narzedzie'=>'Skaner DNA', 'rep'=>120,
        ],
    ],
    'Inżynier' => [
        [
            'zleceniodawca' => 'Volkov Mechanik',
            'dzielnica'     => 'Brooklyn Navy Yard',
            'tytul'         => 'Dostawa dla gangu',
            'tresc'         => 'Moi chłopcy potrzebują broni. Zbuduj mi {ilosc} Pistoletów Samoróbka w {dni} dni.',
            'typ_celu'      => 'wytworz_pistolet_samorobka',
            'ilosc_min'=>3, 'ilosc_max'=>5, 'dni'=>2,
            'trudnosc'=>'latwy',
            'kasa_min'=>800, 'kasa_max'=>1400, 'exp'=>80, 'rep'=>30,
        ],
        [
            'zleceniodawca' => 'Korpos Nakamura',
            'dzielnica'     => 'Wall Street Ruins',
            'tytul'         => 'Zamówienie zbrojeniowe',
            'tresc'         => 'Moja firma (nieoficjalnie) zamawia {ilosc} sztuk Glock 17. Masz {dni} dni. Pełna dyskrecja.',
            'typ_celu'      => 'wytworz_glock_17',
            'ilosc_min'=>4, 'ilosc_max'=>6, 'dni'=>4,
            'trudnosc'=>'normalny',
            'kasa_min'=>2500, 'kasa_max'=>4000, 'exp'=>180, 'rep'=>60,
        ],
        [
            'zleceniodawca' => 'Black Market Chen',
            'dzielnica'     => 'Chinatown',
            'tytul'         => 'Prezent dla klienta',
            'tresc'         => 'Specjalny klient płaci grubo za Egzoszkielet Taktyczny. Potrzebuję {ilosc} sztuk w {dni} dni.',
            'typ_celu'      => 'wytworz_pancerz_taktyczny_upg',
            'ilosc_min'=>1, 'ilosc_max'=>1, 'dni'=>7,
            'trudnosc'=>'elitarny',
            'kasa_min'=>12000, 'kasa_max'=>18000, 'exp'=>500, 'rep'=>150,
        ],
    ],
    'Egzekutor' => [
        [
            'zleceniodawca' => 'Gladiator Leo',
            'dzielnica'     => 'Brooklyn Navy Yard',
            'tytul'         => 'Piękno brutalności',
            'tresc'         => 'Wygraj {ilosc} walki w Dokach. Pokaż tym pozerom, co to prawdziwy rozlew krwi.',
            'typ_celu'      => 'wygraj_walki',
            'ilosc_min'=>3, 'ilosc_max'=>5, 'dni'=>3,
            'trudnosc'=>'normalny',
            'kasa_min'=>1500, 'kasa_max'=>2500, 'exp'=>150, 'rep'=>40,
        ],
        [
            'zleceniodawca' => 'Arena Lord',
            'dzielnica'     => 'Manhattan Metro',
            'tytul'         => 'Żelazny wojownik',
            'tresc'         => 'Wygraj {ilosc} walk w ciągu {dni} dni. Potrzebuję prawdziwej bestii.',
            'typ_celu'      => 'wygraj_walki',
            'ilosc_min'=>10, 'ilosc_max'=>15, 'dni'=>5,
            'trudnosc'=>'elitarny',
            'kasa_min'=>8000, 'kasa_max'=>12000, 'exp'=>400, 'rep'=>100,
        ],
    ],
];

function wygenerujKontrakt($szablon) {
    $ilosc = rand($szablon['ilosc_min'], $szablon['ilosc_max']);
    $kasa  = rand($szablon['kasa_min'], $szablon['kasa_max']);
    $tresc = str_replace(['{ilosc}','{dni}'], [$ilosc,$szablon['dni']], $szablon['tresc']);
    return [
        'zleceniodawca'=>$szablon['zleceniodawca'],
        'dzielnica'=>$szablon['dzielnica'],
        'tytul'=>$szablon['tytul'],
        'tresc'=>$tresc,
        'typ_celu'=>$szablon['typ_celu'],
        'cel_ilosc'=>$ilosc,
        'trudnosc'=>$szablon['trudnosc'],
        'kasa'=>$kasa,
        'exp'=>$szablon['exp'],
        'narzedzie'=>$szablon['narzedzie'] ?? null,
        'rep'=>$szablon['rep'],
        'dni'=>$szablon['dni']
    ];
}

// ═══════════════════════════════════════════════════════════════
// FABULARNE — logika bez zmian (Twoja stara logika)
// ═══════════════════════════════════════════════════════════════
if (isset($_POST['odswiez_zlecenia'])) {
    if ($gracz['gotowka'] >= 100) {
        $polaczenie->query("UPDATE gracze SET gotowka=gotowka-100 WHERE id=$id_gracza");
        $polaczenie->query("DELETE FROM zlecenia WHERE gracz_id=$id_gracza AND status='dostepne'");
        $gracz['gotowka'] -= 100;
    } else $komunikat = "<div class='blad'>Brakuje ci 100$ na Fixera!</div>";
}

$ile_dostepnych = $polaczenie->query("SELECT COUNT(*) c FROM zlecenia WHERE gracz_id=$id_gracza AND status='dostepne'")->fetch_assoc()['c'];
$aktywne_zlecenie = $polaczenie->query("SELECT * FROM zlecenia WHERE gracz_id=$id_gracza AND status='w_trakcie'")->fetch_assoc();

if ($ile_dostepnych == 0 && !$aktywne_zlecenie) {
    for ($i = 0; $i < 3; $i++) {
        $typ = ($i == 0) ? 'Ogólne' : (!empty($gracz['profesja_fabularna']) ? $gracz['profesja_fabularna'] : 'Ogólne');
        $z = losujZlecenie($typ);
        $t = $polaczenie->real_escape_string($z['tresc']);
        $polaczenie->query("INSERT INTO zlecenia (gracz_id,typ_zlecenia,tresc,nagroda_kasa,nagroda_exp,koszt_en,czas_trwania_minuty,status)
            VALUES ($id_gracza,'$typ','$t',{$z['kasa']},{$z['exp']},{$z['energia']},{$z['czas']},'dostepne')");
    }
}

if (isset($_POST['rozpocznij']) && !$aktywne_zlecenie) {
    $id_z = (int)$_POST['id_zlecenia'];
    $s = $polaczenie->query("SELECT koszt_en,czas_trwania_minuty FROM zlecenia WHERE id=$id_z AND gracz_id=$id_gracza AND status='dostepne'")->fetch_assoc();
    if ($gracz['zlecenia_wykonane_dzis'] >= $limit_dzienny) {
        $komunikat = "<div class='blad'>Wykorzystałeś limit $limit_dzienny zleceń na dziś.</div>";
    } elseif ($s && $gracz['energia_aktualna'] >= $s['koszt_en']) {
        $zak = date('Y-m-d H:i:s', time() + ($s['czas_trwania_minuty']*60));
        $polaczenie->query("UPDATE zlecenia SET status='w_trakcie',czas_rozpoczecia=NOW(),czas_zakonczenia='$zak' WHERE id=$id_z");
        $polaczenie->query("UPDATE gracze SET energia_aktualna=energia_aktualna-{$s['koszt_en']} WHERE id=$id_gracza");
        $polaczenie->query("DELETE FROM zlecenia WHERE gracz_id=$id_gracza AND status='dostepne'");
        echo "<script>location.href='game.php?page=zlecenia&tab=fabularne';</script>"; exit;
    } else $komunikat = "<div class='blad'>Brakuje energii lub zlecenia!</div>";
}

if (isset($_POST['odbierz']) && $aktywne_zlecenie) {
    if (time() >= strtotime($aktywne_zlecenie['czas_zakonczenia'])) {
        $polaczenie->query("UPDATE zlecenia SET status='zakonczone' WHERE id={$aktywne_zlecenie['id']}");
        $polaczenie->query("UPDATE gracze SET gotowka=gotowka+{$aktywne_zlecenie['nagroda_kasa']},exp=exp+{$aktywne_zlecenie['nagroda_exp']},zlecenia_wykonane_dzis=zlecenia_wykonane_dzis+1 WHERE id=$id_gracza");
        $komunikat = "<div class='sukces'>✅ Zlecenie wykonane! +{$aktywne_zlecenie['nagroda_kasa']}\$, +{$aktywne_zlecenie['nagroda_exp']} EXP</div>";
        $aktywne_zlecenie = null;
    } else $komunikat = "<div class='blad'>Zlecenie nie zostało jeszcze ukończone!</div>";
}

$aktywne_zlecenie = $polaczenie->query("SELECT * FROM zlecenia WHERE gracz_id=$id_gracza AND status='w_trakcie'")->fetch_assoc();
$oferty = $polaczenie->query("SELECT * FROM zlecenia WHERE gracz_id=$id_gracza AND status='dostepne'");

// ═══════════════════════════════════════════════════════════════
// KONTRAKTY KLASOWE — LOGIKA
// ═══════════════════════════════════════════════════════════════

// Automatyczne oznaczanie niepowodzeń po deadline
$polaczenie->query("UPDATE kontrakty_klasowe SET status='niepowodzenie'
    WHERE gracz_id=$id_gracza AND status='aktywny' AND deadline < NOW()");

// Automatyczne oznaczanie ukończonych
$polaczenie->query("UPDATE kontrakty_klasowe SET status='ukonczony'
    WHERE gracz_id=$id_gracza AND status='aktywny' AND postep >= cel_ilosc AND deadline > NOW()");

// Generowanie 3 nowych propozycji jeśli brak dostępnych
$ile_dostepnych_kontraktow = $polaczenie->query("SELECT COUNT(*) c FROM kontrakty_klasowe
    WHERE gracz_id=$id_gracza AND status='dostepny'")->fetch_assoc()['c'];

if ($ile_dostepnych_kontraktow == 0 && isset($kontrakty_szablony[$gracz['klasa']])) {
    $szablony = $kontrakty_szablony[$gracz['klasa']];
    $ile = min(3, count($szablony));
    $wylosowane = array_rand($szablony, $ile);
    if (!is_array($wylosowane)) $wylosowane = [$wylosowane];

    foreach ($wylosowane as $idx) {
        $k = wygenerujKontrakt($szablony[$idx]);
        $deadline = date('Y-m-d H:i:s', time() + ($k['dni']*86400));
        $t_esc = $polaczenie->real_escape_string($k['tresc']);
        $ty_esc = $polaczenie->real_escape_string($k['tytul']);
        $zl_esc = $polaczenie->real_escape_string($k['zleceniodawca']);
        $dz_esc = $polaczenie->real_escape_string($k['dzielnica']);
        $tc_esc = $polaczenie->real_escape_string($k['typ_celu']);
        $nar_esc = $k['narzedzie'] ? "'".$polaczenie->real_escape_string($k['narzedzie'])."'" : "NULL";

        $polaczenie->query("INSERT INTO kontrakty_klasowe
            (gracz_id, klasa_wymagana, zleceniodawca, dzielnica, tytul, tresc, typ_celu, cel_ilosc, trudnosc,
             nagroda_kasa, nagroda_exp, nagroda_narzedzie, nagroda_reputacja, deadline, status)
            VALUES ($id_gracza, '{$gracz['klasa']}', '$zl_esc', '$dz_esc', '$ty_esc', '$t_esc', '$tc_esc',
             {$k['cel_ilosc']}, '{$k['trudnosc']}', {$k['kasa']}, {$k['exp']}, $nar_esc, {$k['rep']}, '$deadline', 'dostepny')");
    }
}

// Przyjmij kontrakt
if (isset($_POST['przyjmij_kontrakt'])) {
    $kid = (int)$_POST['kontrakt_id'];
    $aktywne_ile = $polaczenie->query("SELECT COUNT(*) c FROM kontrakty_klasowe WHERE gracz_id=$id_gracza AND status='aktywny'")->fetch_assoc()['c'];
    if ($aktywne_ile >= 3) {
        $komunikat = "<div class='blad'>Możesz mieć max 3 aktywne kontrakty jednocześnie!</div>";
    } else {
        $polaczenie->query("UPDATE kontrakty_klasowe SET status='aktywny',data_rozpoczecia=NOW() WHERE id=$kid AND gracz_id=$id_gracza AND status='dostepny'");
        // Usuń pozostałe dostępne propozycje
        $polaczenie->query("DELETE FROM kontrakty_klasowe WHERE gracz_id=$id_gracza AND status='dostepny'");
        echo "<script>location.href='game.php?page=zlecenia&tab=kontrakty';</script>"; exit;
    }
}

// Odrzuć propozycję
if (isset($_POST['odrzuc_kontrakt'])) {
    $kid = (int)$_POST['kontrakt_id'];
    $polaczenie->query("DELETE FROM kontrakty_klasowe WHERE id=$kid AND gracz_id=$id_gracza AND status='dostepny'");
    echo "<script>location.href='game.php?page=zlecenia&tab=kontrakty';</script>"; exit;
}

// Odbierz nagrodę
if (isset($_POST['odbierz_kontrakt'])) {
    $kid = (int)$_POST['kontrakt_id'];
    $k = $polaczenie->query("SELECT * FROM kontrakty_klasowe WHERE id=$kid AND gracz_id=$id_gracza AND status='ukonczony'")->fetch_assoc();
    if ($k) {
        // Wypłata
        $polaczenie->query("UPDATE gracze SET gotowka=gotowka+{$k['nagroda_kasa']},exp=exp+{$k['nagroda_exp']} WHERE id=$id_gracza");

        // Narzędzie
        if ($k['nagroda_narzedzie']) {
            $nn = $polaczenie->real_escape_string($k['nagroda_narzedzie']);
            $polaczenie->query("INSERT INTO narzedzia_gracza (gracz_id,nazwa,trwalosc_max,trwalosc_aktualna) VALUES ($id_gracza,'$nn',100,100)");
        }

        // Reputacja
        if ($k['nagroda_reputacja'] > 0 && $k['dzielnica']) {
            $dz = $polaczenie->real_escape_string($k['dzielnica']);
            $polaczenie->query("INSERT INTO reputacja_dzielnic (gracz_id,dzielnica,reputacja) VALUES ($id_gracza,'$dz',{$k['nagroda_reputacja']})
                ON DUPLICATE KEY UPDATE reputacja=reputacja+{$k['nagroda_reputacja']}, ostatnia_aktualizacja=NOW()");
        }

        $polaczenie->query("UPDATE kontrakty_klasowe SET status='odebrany' WHERE id=$kid");
        $komunikat = "<div class='sukces'>✅ Kontrakt wykonany! +{$k['nagroda_kasa']}\$, +{$k['nagroda_exp']} EXP"
            . ($k['nagroda_narzedzie'] ? ", +{$k['nagroda_narzedzie']}" : "")
            . ($k['nagroda_reputacja'] ? ", +{$k['nagroda_reputacja']} Reputacji w {$k['dzielnica']}" : "")
            . "</div>";
    }
}

// Pobierz kontrakty do widoku
$kontrakty_dostepne = $polaczenie->query("SELECT * FROM kontrakty_klasowe WHERE gracz_id=$id_gracza AND status='dostepny' ORDER BY id DESC");
$kontrakty_aktywne  = $polaczenie->query("SELECT * FROM kontrakty_klasowe WHERE gracz_id=$id_gracza AND status IN ('aktywny','ukonczony') ORDER BY status DESC, deadline ASC");
$kontrakty_archiwum = $polaczenie->query("SELECT * FROM kontrakty_klasowe WHERE gracz_id=$id_gracza AND status IN ('odebrany','niepowodzenie') ORDER BY id DESC LIMIT 5");
?>

<style>
/* ══ NAGŁÓWEK ══ */
.zl-header{
    background:linear-gradient(135deg,rgba(0,0,0,.7),rgba(30,20,0,.5)),
        url('https://via.placeholder.com/900x200/1a1000/000?text=') center/cover;
    padding:32px 36px;border:1px solid rgba(255,204,0,.3);border-radius:12px;margin-bottom:22px;
    box-shadow:0 0 30px rgba(255,204,0,.08);
}
.zl-header h1{font-family:'Oswald',sans-serif;color:#ffcc00;font-size:2.6em;margin:0;
    text-transform:uppercase;letter-spacing:2px;text-shadow:0 0 15px rgba(255,204,0,.5)}
.zl-header p{color:#aaa;margin-top:8px}

/* ══ ZAKŁADKI ══ */
.tabs{display:flex;gap:4px;margin-bottom:22px;border-bottom:1px solid rgba(255,255,255,.08)}
.tab{
    background:transparent;border:none;padding:14px 24px;
    font-family:'Oswald',sans-serif;font-size:1em;text-transform:uppercase;letter-spacing:1.5px;
    color:#666;cursor:pointer;border-bottom:2px solid transparent;
    transition:.25s;text-decoration:none;display:inline-block;
}
.tab:hover{color:#aaa}
.tab.active{color:#ffcc00;border-bottom-color:#ffcc00;text-shadow:0 0 10px rgba(255,204,0,.4)}

/* ══ KARTY ══ */
.panel-limit{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);
    padding:14px;text-align:center;border-radius:8px;margin-bottom:20px;color:#aaa}

.karta-z{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.06);
    padding:20px;border-radius:10px;margin-bottom:15px;transition:.3s}
.karta-z:hover{border-color:rgba(255,204,0,.3);background:rgba(10,10,10,.6)}
.kz-typ{font-family:'Oswald',sans-serif;color:#00ccff;text-transform:uppercase;
    font-size:.9em;letter-spacing:1px;border-bottom:1px dashed rgba(255,255,255,.05);padding-bottom:8px;margin-bottom:12px}
.kz-tresc{font-size:1.05em;line-height:1.65;color:#ddd;margin-bottom:15px}
.kz-nagrody{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:12px;
    background:rgba(0,0,0,.5);padding:14px;border-radius:6px;border:1px solid rgba(255,255,255,.05)}
.kz-ngr{text-align:center}
.kz-ngr span{display:block;font-size:.72em;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.kz-ngr b{font-family:'Oswald',sans-serif;font-size:1.2em}

.btn-akcja{background:rgba(0,204,255,.1);color:#00ccff;border:1px solid rgba(0,204,255,.4);
    padding:13px;font-family:'Oswald',sans-serif;font-size:1.05em;cursor:pointer;
    text-transform:uppercase;border-radius:6px;transition:.3s;width:100%;letter-spacing:1px;margin-top:15px}
.btn-akcja:hover{background:#00ccff;color:#000;box-shadow:0 0 20px rgba(0,204,255,.4)}
.btn-odswiez{background:transparent;border:1px solid rgba(255,255,255,.15);color:#888;
    padding:9px 16px;width:auto;margin-top:0;font-size:.9em}
.btn-odswiez:hover{background:rgba(255,255,255,.05);color:#fff;box-shadow:none}

.aktywne-panel{background:rgba(0,204,255,.05);border:2px solid rgba(0,204,255,.5);
    padding:28px;text-align:center;border-radius:10px;box-shadow:0 0 30px rgba(0,204,255,.1)}
.timer{font-family:monospace;font-size:3em;color:#fff;margin:16px 0;font-weight:bold;
    text-shadow:0 0 15px #00ccff}

.sukces{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.4);color:#00ff88;
    padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
.blad{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.4);color:#ff6666;
    padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}

/* ══ KONTRAKTY KLASOWE ══ */
.kontrakt{
    background:rgba(8,8,14,.7);border:1px solid rgba(255,255,255,.07);
    border-radius:12px;padding:22px;margin-bottom:16px;position:relative;overflow:hidden;
    transition:.3s;
}
.kontrakt::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
}
.kontrakt-latwy::before   {background:linear-gradient(90deg,#00ff88,#00ccff)}
.kontrakt-normalny::before{background:linear-gradient(90deg,#00ccff,#dd88ff)}
.kontrakt-trudny::before  {background:linear-gradient(90deg,#ffaa00,#ff6666)}
.kontrakt-elitarny::before{background:linear-gradient(90deg,#ff3388,#ffd700,#ff3388);animation:elit-glow 3s infinite}
@keyframes elit-glow{0%,100%{opacity:1}50%{opacity:.4}}

.k-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;gap:12px;flex-wrap:wrap}
.k-zleceniodawca{display:flex;align-items:center;gap:12px}
.k-avatar{
    width:52px;height:52px;border-radius:50%;
    background:linear-gradient(135deg,#333,#111);
    display:flex;align-items:center;justify-content:center;
    font-size:1.8em;border:2px solid rgba(255,255,255,.1);flex-shrink:0;
}
.k-npc-info{display:flex;flex-direction:column}
.k-npc-name{font-family:'Oswald',sans-serif;font-size:1.1em;color:#fff;text-transform:uppercase;letter-spacing:.5px}
.k-npc-dzielnica{font-size:.82em;color:#666;font-family:'Oswald',sans-serif;margin-top:2px}

.k-trudnosc{
    padding:4px 12px;border-radius:14px;font-family:'Oswald',sans-serif;
    font-size:.72em;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;
    border:1px solid;
}
.t-latwy   {color:#00ff88;border-color:rgba(0,255,136,.4);background:rgba(0,255,136,.08)}
.t-normalny{color:#00ccff;border-color:rgba(0,204,255,.4);background:rgba(0,204,255,.08)}
.t-trudny  {color:#ffaa00;border-color:rgba(255,170,0,.4);background:rgba(255,170,0,.08)}
.t-elitarny{color:#ff3388;border-color:rgba(255,51,136,.5);background:rgba(255,51,136,.1);box-shadow:0 0 10px rgba(255,51,136,.2)}

.k-tytul{font-family:'Oswald',sans-serif;color:#fff;font-size:1.3em;margin:10px 0 8px;letter-spacing:.5px}
.k-tresc{color:#bbb;font-size:.95em;line-height:1.6;font-style:italic;padding-left:14px;
    border-left:2px solid rgba(255,255,255,.1);margin-bottom:14px}

.k-nagrody{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.k-ngr-chip{
    background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.08);
    padding:6px 12px;border-radius:20px;font-size:.85em;display:flex;align-items:center;gap:6px;
}
.k-ngr-chip.kasa{border-color:rgba(0,255,136,.3);color:#00ff88}
.k-ngr-chip.exp {border-color:rgba(255,170,0,.3);color:#ffaa00}
.k-ngr-chip.nar {border-color:rgba(221,136,255,.3);color:#dd88ff}
.k-ngr-chip.rep {border-color:rgba(0,204,255,.3);color:#00ccff}

/* Progres */
.k-progress{background:rgba(0,0,0,.6);border-radius:6px;height:22px;overflow:hidden;position:relative;
    border:1px solid rgba(255,255,255,.07);margin-bottom:10px}
.k-progress-fill{height:100%;background:linear-gradient(90deg,#006633,#00ff88);
    transition:width .8s;box-shadow:0 0 10px rgba(0,255,136,.3)}
.k-progress-fill.done{background:linear-gradient(90deg,#ffd700,#ffaa00);box-shadow:0 0 15px rgba(255,215,0,.5)}
.k-progress-txt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    font-size:.82em;color:#fff;font-weight:700;text-shadow:1px 1px 0 #000;font-family:'Oswald',sans-serif}

.k-deadline{display:flex;justify-content:space-between;font-size:.82em;color:#666;margin-bottom:12px}
.k-deadline b{color:#aaa}
.k-deadline.urgent b{color:#ff6666;text-shadow:0 0 8px rgba(255,102,102,.5)}

.k-akcje{display:flex;gap:10px}
.btn-k-przyjmij{flex:1;background:rgba(0,255,136,.1);color:#00ff88;border:1px solid rgba(0,255,136,.4);
    padding:11px;font-family:'Oswald',sans-serif;cursor:pointer;text-transform:uppercase;border-radius:6px;
    font-size:.95em;letter-spacing:1px;transition:.25s}
.btn-k-przyjmij:hover{background:#00ff88;color:#000;box-shadow:0 0 15px rgba(0,255,136,.4)}
.btn-k-odrzuc{background:transparent;border:1px solid rgba(255,68,68,.3);color:#ff6666;
    padding:11px 18px;font-family:'Oswald',sans-serif;cursor:pointer;text-transform:uppercase;border-radius:6px;font-size:.9em}
.btn-k-odrzuc:hover{background:rgba(255,68,68,.15)}

.btn-k-odbierz{width:100%;background:rgba(255,215,0,.15);color:#ffd700;border:1px solid rgba(255,215,0,.5);
    padding:13px;font-family:'Oswald',sans-serif;font-size:1.1em;cursor:pointer;text-transform:uppercase;
    border-radius:6px;letter-spacing:1px;animation:odbierz-pulse 2s infinite}
@keyframes odbierz-pulse{0%,100%{box-shadow:0 0 10px rgba(255,215,0,.3)}50%{box-shadow:0 0 25px rgba(255,215,0,.6)}}
.btn-k-odbierz:hover{background:#ffd700;color:#000}

.k-empty{padding:40px;text-align:center;color:#555;font-style:italic;
    border:1px dashed rgba(255,255,255,.08);border-radius:10px;font-size:.9em}

.sekcja-tytul{color:#666;font-family:'Oswald',sans-serif;text-transform:uppercase;
    letter-spacing:2px;font-size:.85em;margin:22px 0 12px;padding-bottom:8px;
    border-bottom:1px solid rgba(255,255,255,.05)}

.archiwum-row{display:flex;justify-content:space-between;padding:10px 14px;font-size:.85em;
    border-bottom:1px dashed rgba(255,255,255,.05);color:#777}
.archiwum-row:last-child{border-bottom:none}
.ar-ukonczony{color:#00ff88}
.ar-niepowodzenie{color:#ff6666}
</style>

<!-- ══════════════════════════════════════════════════════
     NAGŁÓWEK
══════════════════════════════════════════════════════ -->
<div class="zl-header">
    <h1>📜 Tablica Fixerów</h1>
    <p>Ulica zawsze potrzebuje rąk do pracy. Brudnej pracy.</p>
</div>

<?php echo $komunikat; ?>

<!-- ══ ZAKŁADKI ══ -->
<div class="tabs">
    <a href="game.php?page=zlecenia&tab=fabularne" class="tab <?php echo $tab=='fabularne'?'active':''; ?>">
        🎭 Zlecenia Fabularne
    </a>
    <a href="game.php?page=zlecenia&tab=kontrakty" class="tab <?php echo $tab=='kontrakty'?'active':''; ?>">
        🎯 Kontrakty Klasowe
    </a>
</div>

<?php if ($tab == 'fabularne'): ?>
<!-- ══════════════════════════════════════════════════════
     ZAKŁADKA 1 — FABULARNE (Twoja logika, nietknięta)
══════════════════════════════════════════════════════ -->
<div class="panel-limit">
    Dzisiejszy limit zleceń: <b style="color:#fff"><?php echo $gracz['zlecenia_wykonane_dzis']; ?> / <?php echo $limit_dzienny; ?></b>
</div>

<?php if ($aktywne_zlecenie):
    $pozostalo = max(0, strtotime($aktywne_zlecenie['czas_zakonczenia']) - time());
?>
    <div class="aktywne-panel">
        <h2 style="font-family:'Oswald',sans-serif;color:#00ccff;margin-top:0;text-transform:uppercase;letter-spacing:2px">🎭 Zlecenie w toku</h2>
        <div class="kz-tresc" style="margin-bottom:20px;text-align:left;background:rgba(0,0,0,.6);padding:18px;border-radius:6px;border:1px dashed rgba(255,255,255,.1)">
            <?php echo $aktywne_zlecenie['tresc']; ?>
        </div>

        <?php if ($pozostalo > 0): ?>
            <div style="color:#aaa;text-transform:uppercase;font-family:'Oswald',sans-serif">Czas do zakończenia:</div>
            <div class="timer" id="odliczanie"></div>
            <script>
                var czas = <?php echo $pozostalo; ?>;
                setInterval(function(){
                    if(czas<=0){location.reload()}else{
                        czas--;
                        var m=Math.floor(czas/60),s=czas%60;
                        document.getElementById("odliczanie").innerText=(m<10?"0":"")+m+":"+(s<10?"0":"")+s;
                    }
                },1000);
            </script>
        <?php else: ?>
            <div class="timer" style="color:#00ff88;text-shadow:0 0 15px #00ff88">✓ GOTOWE!</div>
            <form method="POST">
                <button type="submit" name="odbierz" class="btn-akcja" style="background:rgba(0,255,136,.15);border-color:#00ff88;color:#00ff88">Odbierz Zapłatę</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($gracz['zlecenia_wykonane_dzis'] < $limit_dzienny): ?>
    <form method="POST" style="text-align:right;margin-bottom:15px">
        <button type="submit" name="odswiez_zlecenia" class="btn-akcja btn-odswiez">🔄 Nowe oferty (-100$)</button>
    </form>

    <?php while ($o = $oferty->fetch_assoc()): ?>
        <div class="karta-z">
            <div class="kz-typ">Typ misji: <?php echo $o['typ_zlecenia']; ?></div>
            <div class="kz-tresc"><?php echo $o['tresc']; ?></div>
            <div class="kz-nagrody">
                <div class="kz-ngr"><span>Zapłata</span><b style="color:#00ff88"><?php echo $o['nagroda_kasa']; ?>$</b></div>
                <div class="kz-ngr"><span>EXP</span><b style="color:#ffaa00">+<?php echo $o['nagroda_exp']; ?></b></div>
                <div class="kz-ngr"><span>Czas</span><b style="color:#fff"><?php echo $o['czas_trwania_minuty']; ?>min</b></div>
                <div class="kz-ngr"><span>Energia</span><b style="color:#00ccff">-<?php echo $o['koszt_en']; ?></b></div>
            </div>
            <form method="POST" style="margin:0">
                <input type="hidden" name="id_zlecenia" value="<?php echo $o['id']; ?>">
                <button type="submit" name="rozpocznij" class="btn-akcja">Przyjmij Zlecenie</button>
            </form>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="k-empty">Jesteś wyczerpany. Żadni Fixerzy nie dadzą ci dziś pracy. Wróć jutro.</div>
<?php endif; ?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════
     ZAKŁADKA 2 — KONTRAKTY KLASOWE
══════════════════════════════════════════════════════ -->

<?php if (!isset($kontrakty_szablony[$gracz['klasa']])): ?>
    <div class="blad">
        ⚠️ Twoja klasa (<?php echo $gracz['klasa']; ?>) nie ma jeszcze dostępnych kontraktów klasowych.
        <br><span style="font-size:.85em">Są one przeznaczone dla Szabrowników, Inżynierów i Egzekutorów.</span>
    </div>
<?php else: ?>

<!-- ── AKTYWNE KONTRAKTY ── -->
<div class="sekcja-tytul">📊 Aktywne kontrakty (<?php echo $kontrakty_aktywne->num_rows; ?> / 3)</div>

<?php if ($kontrakty_aktywne->num_rows == 0): ?>
    <div class="k-empty">Nie masz żadnych aktywnych kontraktów. Przyjmij propozycję poniżej.</div>
<?php else:
    while ($k = $kontrakty_aktywne->fetch_assoc()):
        $proc = min(100, ($k['postep'] / $k['cel_ilosc']) * 100);
        $deadline_ts = strtotime($k['deadline']);
        $pozostalo_s = $deadline_ts - time();
        $urgent = ($pozostalo_s < 86400);
        $dni_p = floor($pozostalo_s / 86400);
        $godz_p = floor(($pozostalo_s % 86400) / 3600);
        $ukonczony = ($k['status'] == 'ukonczony');
?>
    <div class="kontrakt kontrakt-<?php echo $k['trudnosc']; ?>">
        <div class="k-head">
            <div class="k-zleceniodawca">
                <div class="k-avatar">👤</div>
                <div class="k-npc-info">
                    <div class="k-npc-name"><?php echo htmlspecialchars($k['zleceniodawca']); ?></div>
                    <div class="k-npc-dzielnica"><?php echo htmlspecialchars($k['dzielnica']); ?></div>
                </div>
            </div>
            <span class="k-trudnosc t-<?php echo $k['trudnosc']; ?>"><?php echo $k['trudnosc']; ?></span>
        </div>

        <div class="k-tytul"><?php echo htmlspecialchars($k['tytul']); ?></div>
        <div class="k-tresc">❝ <?php echo htmlspecialchars($k['tresc']); ?> ❞</div>

        <div class="k-progress">
            <div class="k-progress-fill <?php echo $ukonczony?'done':''; ?>" style="width:<?php echo $proc; ?>%"></div>
            <div class="k-progress-txt"><?php echo $k['postep']; ?> / <?php echo $k['cel_ilosc']; ?> (<?php echo round($proc); ?>%)</div>
        </div>

        <div class="k-deadline <?php echo $urgent && !$ukonczony?'urgent':''; ?>">
            <span>⏰ Deadline:</span>
            <b><?php
                if ($ukonczony) echo "GOTOWE DO ODEBRANIA ✓";
                elseif ($pozostalo_s <= 0) echo "PRZEGAPIONY";
                else echo ($dni_p > 0 ? "$dni_p dni " : "") . "$godz_p godz.";
            ?></b>
        </div>

        <div class="k-nagrody">
            <span class="k-ngr-chip kasa">💵 <?php echo number_format($k['nagroda_kasa'],0,'','&nbsp;'); ?>$</span>
            <span class="k-ngr-chip exp">⭐ +<?php echo $k['nagroda_exp']; ?> EXP</span>
            <?php if ($k['nagroda_narzedzie']): ?><span class="k-ngr-chip nar">🔧 <?php echo htmlspecialchars($k['nagroda_narzedzie']); ?></span><?php endif; ?>
            <?php if ($k['nagroda_reputacja']>0): ?><span class="k-ngr-chip rep">⚔️ +<?php echo $k['nagroda_reputacja']; ?> Rep</span><?php endif; ?>
        </div>

        <?php if ($ukonczony): ?>
        <form method="POST" style="margin:0">
            <input type="hidden" name="kontrakt_id" value="<?php echo $k['id']; ?>">
            <button type="submit" name="odbierz_kontrakt" class="btn-k-odbierz">✨ Odbierz Nagrodę</button>
        </form>
        <?php endif; ?>
    </div>
<?php endwhile; endif; ?>


<!-- ── DOSTĘPNE PROPOZYCJE ── -->
<div class="sekcja-tytul">📬 Propozycje</div>

<?php if ($kontrakty_dostepne->num_rows == 0): ?>
    <div class="k-empty">Brak nowych propozycji. Dokończ aktywne kontrakty, a fixerzy przyślą nowe oferty.</div>
<?php else:
    while ($k = $kontrakty_dostepne->fetch_assoc()):
        $dni = ceil((strtotime($k['deadline']) - time()) / 86400);
?>
    <div class="kontrakt kontrakt-<?php echo $k['trudnosc']; ?>">
        <div class="k-head">
            <div class="k-zleceniodawca">
                <div class="k-avatar">👤</div>
                <div class="k-npc-info">
                    <div class="k-npc-name"><?php echo htmlspecialchars($k['zleceniodawca']); ?></div>
                    <div class="k-npc-dzielnica"><?php echo htmlspecialchars($k['dzielnica']); ?></div>
                </div>
            </div>
            <span class="k-trudnosc t-<?php echo $k['trudnosc']; ?>"><?php echo $k['trudnosc']; ?></span>
        </div>

        <div class="k-tytul"><?php echo htmlspecialchars($k['tytul']); ?></div>
        <div class="k-tresc">❝ <?php echo htmlspecialchars($k['tresc']); ?> ❞</div>

        <div style="margin-bottom:12px;font-size:.9em;color:#888">
            <b style="color:#aaa">Cel:</b> <?php echo $k['cel_ilosc']; ?> × <?php echo str_replace(['zbierz_','wytworz_','_'],[' ','Wytwórz ',' '],$k['typ_celu']); ?>
            &nbsp;·&nbsp;
            <b style="color:#aaa">Termin:</b> <?php echo $dni; ?> dni
        </div>

        <div class="k-nagrody">
            <span class="k-ngr-chip kasa">💵 <?php echo number_format($k['nagroda_kasa'],0,'','&nbsp;'); ?>$</span>
            <span class="k-ngr-chip exp">⭐ +<?php echo $k['nagroda_exp']; ?> EXP</span>
            <?php if ($k['nagroda_narzedzie']): ?><span class="k-ngr-chip nar">🔧 <?php echo htmlspecialchars($k['nagroda_narzedzie']); ?></span><?php endif; ?>
            <?php if ($k['nagroda_reputacja']>0): ?><span class="k-ngr-chip rep">⚔️ +<?php echo $k['nagroda_reputacja']; ?> Rep</span><?php endif; ?>
        </div>

        <form method="POST" class="k-akcje">
            <input type="hidden" name="kontrakt_id" value="<?php echo $k['id']; ?>">
            <button type="submit" name="przyjmij_kontrakt" class="btn-k-przyjmij">✓ Przyjmij</button>
            <button type="submit" name="odrzuc_kontrakt" class="btn-k-odrzuc">✗ Odrzuć</button>
        </form>
    </div>
<?php endwhile; endif; ?>


<!-- ── ARCHIWUM ── -->
<?php if ($kontrakty_archiwum->num_rows > 0): ?>
<div class="sekcja-tytul">📁 Historia (ostatnie 5)</div>
<div style="background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.05);border-radius:8px;padding:10px 14px">
<?php while ($k = $kontrakty_archiwum->fetch_assoc()):
    $cls = $k['status']=='odebrany'?'ar-ukonczony':'ar-niepowodzenie';
    $ico = $k['status']=='odebrany'?'✓':'✗';
?>
    <div class="archiwum-row">
        <span><?php echo $ico; ?> <b><?php echo htmlspecialchars($k['tytul']); ?></b> — <?php echo htmlspecialchars($k['zleceniodawca']); ?></span>
        <span class="<?php echo $cls; ?>"><?php echo $k['status']=='odebrany'?'Ukończony':'Niepowodzenie'; ?></span>
    </div>
<?php endwhile; ?>
</div>
<?php endif; ?>

<?php endif; // koniec if !isset($kontrakty_szablony) ?>

<?php endif; // koniec if tab == kontrakty ?>