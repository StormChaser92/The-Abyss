<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// Pobieramy gotówkę
$gracz = $polaczenie->query("SELECT gotowka FROM gracze WHERE id = $id_gracza")->fetch_assoc();
$gotowka = (int)$gracz['gotowka'];

$komunikat = "";
$gra = isset($_GET['gra']) ? $_GET['gra'] : 'sloty'; 

// ==========================================
// LOGIKA 1: CYBER-SLOTY
// ==========================================
$animuj_slota = false;
$wynik_bębnow = ['❓', '❓', '❓'];
$kwota_wygranej_slot = 0;
$stan_gry_slot = "oczekiwanie";

$symbole_slot = [
    '🍒' => ['mnoznik' => 2, 'szansa' => 40],
    '🍋' => ['mnoznik' => 3, 'szansa' => 30],
    '🔔' => ['mnoznik' => 5, 'szansa' => 15],
    '💎' => ['mnoznik' => 10, 'szansa' => 10],
    '7️⃣' => ['mnoznik' => 50, 'szansa' => 5]
];

function losujSymbol($symbole) {
    $los = rand(1, 100); $suma = 0;
    foreach ($symbole as $znak => $dane) { $suma += $dane['szansa']; if ($los <= $suma) return $znak; }
    return '🍒';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['akcja']) && $_POST['akcja'] == 'zakrec_slot') {
    $stawka = isset($_POST['stawka']) ? (int)$_POST['stawka'] : 0;
    if ($stawka < 10) { $komunikat = "<div class='blad'>Minimalna stawka to 10 $.</div>"; } 
    elseif ($stawka > $gotowka) { $komunikat = "<div class='blad'>Nie masz tylu pieniędzy!</div>"; } 
    else {
        $wynik_bębnow = [losujSymbol($symbole_slot), losujSymbol($symbole_slot), losujSymbol($symbole_slot)];
        if ($wynik_bębnow[0] === $wynik_bębnow[1] && $wynik_bębnow[1] === $wynik_bębnow[2]) {
            $kwota_wygranej_slot = $stawka * $symbole_slot[$wynik_bębnow[0]]['mnoznik'];
            $stan_gry_slot = "wygrana";
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $stawka + $kwota_wygranej_slot WHERE id = $id_gracza");
            $gotowka = $gotowka - $stawka + $kwota_wygranej_slot; 
        } else {
            $stan_gry_slot = "przegrana";
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $stawka WHERE id = $id_gracza");
            $gotowka = $gotowka - $stawka;
        }
        $animuj_slota = true;
    }
}

// ==========================================
// LOGIKA 2: HOLO-RULETKA
// ==========================================
$animuj_ruletke = false;
$wylosowana_liczba = -1;
$wylosowany_kolor = "";
$kwota_wygranej_ruletka = 0;
$stan_gry_ruletka = "oczekiwanie";

$numery_czerwone = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['akcja']) && $_POST['akcja'] == 'zakrec_ruletka') {
    $stawka = isset($_POST['stawka']) ? (int)$_POST['stawka'] : 0;
    $typ_zakladu = isset($_POST['typ_zakladu']) ? $_POST['typ_zakladu'] : '';

    if ($stawka < 10) { $komunikat = "<div class='blad'>Minimalna stawka to 10 $.</div>"; } 
    elseif ($stawka > $gotowka) { $komunikat = "<div class='blad'>Brak gotówki na pokrycie zakładu.</div>"; } 
    elseif (empty($typ_zakladu)) { $komunikat = "<div class='blad'>Wybierz pole na stole!</div>"; }
    else {
        $wylosowana_liczba = rand(0, 36);
        $wylosowany_kolor = ($wylosowana_liczba == 0) ? 'green' : (in_array($wylosowana_liczba, $numery_czerwone) ? 'red' : 'black');
        $czy_parzysta = ($wylosowana_liczba != 0 && $wylosowana_liczba % 2 == 0);
        
        $wygrana = false; $mnoznik = 0;

        if ($typ_zakladu == 'red' && $wylosowany_kolor == 'red') { $wygrana = true; $mnoznik = 2; }
        elseif ($typ_zakladu == 'black' && $wylosowany_kolor == 'black') { $wygrana = true; $mnoznik = 2; }
        elseif ($typ_zakladu == 'green' && $wylosowana_liczba == 0) { $wygrana = true; $mnoznik = 36; }
        elseif ($typ_zakladu == 'even' && $czy_parzysta) { $wygrana = true; $mnoznik = 2; }
        elseif ($typ_zakladu == 'odd' && !$czy_parzysta && $wylosowana_liczba != 0) { $wygrana = true; $mnoznik = 2; }
        elseif ($typ_zakladu == 'low' && $wylosowana_liczba >= 1 && $wylosowana_liczba <= 18) { $wygrana = true; $mnoznik = 2; }
        elseif ($typ_zakladu == 'high' && $wylosowana_liczba >= 19 && $wylosowana_liczba <= 36) { $wygrana = true; $mnoznik = 2; }

        if ($wygrana) {
            $kwota_wygranej_ruletka = $stawka * $mnoznik;
            $stan_gry_ruletka = "wygrana";
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $stawka + $kwota_wygranej_ruletka WHERE id = $id_gracza");
            $gotowka = $gotowka - $stawka + $kwota_wygranej_ruletka;
        } else {
            $stan_gry_ruletka = "przegrana";
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $stawka WHERE id = $id_gracza");
            $gotowka = $gotowka - $stawka;
        }
        $animuj_ruletke = true;
    }
}

// ==========================================
// LOGIKA 3: CYBER-POKER (Video Poker)
// ==========================================
$stan_gry_poker = isset($_SESSION['poker_stan']) ? $_SESSION['poker_stan'] : 'start';
$karty_poker = isset($_SESSION['poker_karty']) ? $_SESSION['poker_karty'] : [];
$kwota_wygranej_poker = 0;
$laczna_wyplata_poker = 0; 
$nazwa_ukladu_poker = "";

// Funkcja tworząca talię
function generujTalie() {
    $talia = [];
    $figury = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $kolory = ['♠','♥','♦','♣'];
    foreach($kolory as $k) {
        foreach($figury as $f) {
            $talia[] = ['figura' => $f, 'kolor' => $k];
        }
    }
    shuffle($talia);
    return $talia;
}

// Potężna funkcja oceniająca układ pokerowy (5 kart)
function ocenUkladPokerowy($karty) {
    $wartosci_figur = ['2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,'10'=>10,'J'=>11,'Q'=>12,'K'=>13,'A'=>14];
    $wartosci = []; $ilosci_figur = []; $ilosci_kolorow = [];

    foreach ($karty as $k) {
        $wartosc = $wartosci_figur[$k['figura']];
        $wartosci[] = $wartosc;
        $ilosci_figur[$k['figura']] = isset($ilosci_figur[$k['figura']]) ? $ilosci_figur[$k['figura']] + 1 : 1;
        $ilosci_kolorow[$k['kolor']] = isset($ilosci_kolorow[$k['kolor']]) ? $ilosci_kolorow[$k['kolor']] + 1 : 1;
    }

    rsort($wartosci);
    $czy_kolor = (count($ilosci_kolorow) == 1);
    $czy_strit = false;

    if (count(array_unique($wartosci)) == 5 && ($wartosci[0] - $wartosci[4] == 4)) $czy_strit = true;
    if ($wartosci == [14, 5, 4, 3, 2]) $czy_strit = true; // Wyjątek A-2-3-4-5

    $liczebnosci = array_values($ilosci_figur);
    rsort($liczebnosci);

    if ($czy_kolor && $czy_strit && $wartosci[0] == 14 && $wartosci[4] == 10) return ['nazwa' => 'POKER KRÓLEWSKI', 'mnoznik' => 800];
    if ($czy_kolor && $czy_strit) return ['nazwa' => 'POKER', 'mnoznik' => 50];
    if ($liczebnosci[0] == 4) return ['nazwa' => 'KARETA', 'mnoznik' => 25];
    if ($liczebnosci[0] == 3 && $liczebnosci[1] == 2) return ['nazwa' => 'FULL', 'mnoznik' => 9];
    if ($czy_kolor) return ['nazwa' => 'KOLOR', 'mnoznik' => 6];
    if ($czy_strit) return ['nazwa' => 'STRIT', 'mnoznik' => 4];
    if ($liczebnosci[0] == 3) return ['nazwa' => 'TRÓJKA', 'mnoznik' => 3];
    if ($liczebnosci[0] == 2 && $liczebnosci[1] == 2) return ['nazwa' => 'DWIE PARY', 'mnoznik' => 2];
    
    // Para z Waletami lub wyżej (Jacks or Better)
    if ($liczebnosci[0] == 2) {
        foreach ($ilosci_figur as $figura => $ilosc) {
            if ($ilosc == 2 && $wartosci_figur[$figura] >= 11) return ['nazwa' => 'WYSOKA PARA (Walety+)', 'mnoznik' => 1];
        }
    }
    return ['nazwa' => 'BRAK UKŁADU', 'mnoznik' => 0];
}

// FAZA 1: ROZDANIE KART
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['akcja']) && $_POST['akcja'] == 'poker_rozdaj') {
    $stawka = isset($_POST['stawka']) ? (int)$_POST['stawka'] : 0;
    if ($stawka < 10) { $komunikat = "<div class='blad'>Min. stawka to 10 $.</div>"; } 
    elseif ($stawka > $gotowka) { $komunikat = "<div class='blad'>Brak gotówki.</div>"; } 
    else {
        // Zabieramy stawkę
        $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $stawka WHERE id = $id_gracza");
        $gotowka -= $stawka;

        $talia = generujTalie();
        $reka = [];
        for ($i=0; $i<5; $i++) { $reka[] = array_pop($talia); }

        $_SESSION['poker_stan'] = 'wymiana';
        $_SESSION['poker_stawka'] = $stawka;
        $_SESSION['poker_karty'] = $reka;
        $_SESSION['poker_talia'] = $talia;
        
        $stan_gry_poker = 'wymiana';
        $karty_poker = $reka;
    }
}

// FAZA 2: WYMIANA I WYNIK
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['akcja']) && $_POST['akcja'] == 'poker_wymien' && $stan_gry_poker == 'wymiana') {
    $zatrzymane = isset($_POST['hold']) ? $_POST['hold'] : [];
    $talia = $_SESSION['poker_talia'];
    $reka = $_SESSION['poker_karty'];
    $stawka = $_SESSION['poker_stawka'];

    // Podmiana niezatrzymanych kart
    for ($i=0; $i<5; $i++) {
        if (!isset($zatrzymane[$i])) {
            $reka[$i] = array_pop($talia); // Dajemy nową kartę z talii
        }
    }
    
    // Ocena układu
    $wynik_oceny = ocenUkladPokerowy($reka);
    $kwota_wygranej_poker = $stawka * $wynik_oceny['mnoznik'];
    $nazwa_ukladu_poker = $wynik_oceny['nazwa'];

    // Jeśli wygrana - dodajemy kasę (Zabezpieczone obliczenia!)
    if ($kwota_wygranej_poker > 0) {
        $laczna_wyplata_poker = $stawka + $kwota_wygranej_poker; 
        $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $laczna_wyplata_poker WHERE id = $id_gracza");
        $gotowka += $laczna_wyplata_poker;
    }

    $stan_gry_poker = 'wynik';
    $karty_poker = $reka;
    
    // Bezpieczne czyszczenie sesji
    unset($_SESSION['poker_stan']);
    unset($_SESSION['poker_stawka']);
    unset($_SESSION['poker_karty']);
    unset($_SESSION['poker_talia']);
}

// Jeśli gracz chce odrzucić grę w trakcie
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['akcja']) && $_POST['akcja'] == 'poker_anuluj') {
    unset($_SESSION['poker_stan']);
    $stan_gry_poker = 'start';
    $karty_poker = [];
}

?>

<style>
    /* Wspólne style kasyna (Glassmorphism & Centering) */
    
    /* GŁÓWNY KONTENER KASYNA */
    .gra-kontener {
        /* WYŚRODKOWANIE CAŁEJ ZAWARTOŚCI NA TLE */
        display: flex;
        flex-direction: column;
        align-items: center; /* Wyśrodkowanie w poziomie */
        width: 100%;
        box-sizing: border-box;
    }
    
    /* ZAPEWNIA, ŻE TREŚĆ NIE ROZCIĄGA SIĘ ZA SZEROKO */
    .page-container {
        max-width: 1000px;
        width: 100%;
    }

    .kasyno-header { 
        background: rgba(17, 17, 17, 0.7); /* Szklane, mroczne tło */
        border: 1px solid rgba(255, 215, 0, 0.1); 
        border-radius: 8px; 
        padding: 50px 20px;
        text-align: center; 
        box-shadow: 0 0 50px rgba(0,0,0,0.5);
        margin-bottom: 30px; 
        position: relative; 
        overflow: hidden; 
    }
    .kasyno-header h1 { 
        color: #ffd700; 
        font-family: 'Oswald'; 
        font-size: 4em; 
        margin: 0; 
        text-shadow: 0 0 30px #ffd700; 
        text-transform: uppercase; 
        letter-spacing: 2px;
    }
    .kasyno-header p {
        color: #888;
        font-size: 1.2em;
        margin-top: 10px;
        font-style: italic;
    }
    
    .kasyno-nawigacja { 
        display: flex; 
        gap: 20px; 
        justify-content: center; 
        margin-bottom: 40px; 
    }
    .gra-zakladka { 
        text-decoration: none; 
        background: rgba(20, 20, 20, 0.8); 
        border: 1px solid rgba(255, 215, 0, 0.3); 
        color: #ffd700; 
        padding: 15px 35px; 
        font-family: 'Oswald'; 
        font-size: 1.3em; 
        text-transform: uppercase; 
        border-radius: 4px; 
        cursor: pointer; 
        transition: 0.3s; 
    }
    .gra-zakladka.aktywna { 
        background: rgba(255, 215, 0, 0.1);
        border-color: #ffd700; 
        color: #fff; 
        box-shadow: 0 0 30px rgba(255, 215, 0, 0.3); 
    }
    .gra-zakladka:hover:not(.aktywna) { 
        background: rgba(255, 215, 0, 0.05); 
        color: #fff; 
        border-color: #ffd700;
        transform: translateY(-3px);
    }
    
    /* KLUCZOWE: SZKLANE PANELE Sterowania (Glassmorphism) */
    .panel-sterowania { 
        background: rgba(20, 20, 20, 0.6); /* Przezroczyste tło */
        border: 1px solid rgba(255, 255, 255, 0.05); /* Bardzo cienka, jasna ramka */
        padding: 30px; 
        border-radius: 10px; 
        text-align: center; 
        position: relative; 
        z-index: 10; 
        margin-top: 30px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.8);
        
        /* Magia szkła: Rozmycie tła POD panelem */
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
    }
    
    .input-stawka { background: rgba(0,0,0,0.5); color: #00ff00; border: 1px solid #444; font-family: 'Oswald'; font-size: 2em; padding: 10px 20px; text-align: center; width: 180px; border-radius: 4px; margin-bottom: 20px; box-shadow: inset 0 0 10px #000;}
    .btn-spin { 
        background: linear-gradient(180deg, #ffd700, #ff8800);
        color: #000; 
        font-family: 'Oswald'; 
        font-size: 2.2em; 
        padding: 18px 50px; 
        border: none;
        border-radius: 4px; 
        cursor: pointer; 
        text-transform: uppercase; 
        transition: 0.3s; 
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3); 
        letter-spacing: 2px;
        font-weight: bold;
        margin: 5px;
    }
    .btn-spin:hover { 
        background: linear-gradient(180deg, #fff, #ffd700);
        box-shadow: 0 10px 40px rgba(255, 215, 0, 0.5);
        transform: scale(1.05);
    }
    .btn-mala { font-size: 0.9em; padding: 8px 20px; background: #333; border: 1px solid #555; }

    /* STYLE POSZCZEGÓLNYCH GIER (Teraz jako Szklane Panele) */
    
    /* CYBER-SLOTY */
    .slot-maszyna { 
        background: rgba(10, 10, 10, 0.7); 
        border: 1px solid rgba(255, 215, 0, 0.2); 
        border-radius: 12px; 
        padding: 40px; 
        max-width: 700px; 
        margin: 0 auto; /* Wyśrodkowanie w poziomie */
        box-shadow: inset 0 0 60px rgba(0,0,0,0.9), 0 0 40px rgba(255,215,0,0.05);
        position: relative; 
    }
    .tabela-wyplat { 
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        background: rgba(5,5,5,0.8); 
        border: 1px solid rgba(255, 215, 0, 0.1); 
        padding: 15px; 
        margin-bottom: 30px; 
        text-align: center;
        border-radius: 4px;
        color: #888;
        font-size: 0.9em;
    }
    .tabela-wyplat div b { color: #fff; }
    .bebny-kontener { display: flex; justify-content: center; gap: 20px; background: rgba(0,0,0,0.8); padding: 30px; border: 1px solid rgba(255, 215, 0, 0.1); border-radius: 8px; margin-bottom: 30px;}
    .beben { width: 120px; height: 140px; background: #000; border: 2px solid #ffd700; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 5em; box-shadow: 0 0 30px rgba(255, 215, 0, 0.2);}
    .kręcenie { animation: spin 0.1s linear infinite; filter: blur(3px); }
    @keyframes spin { 0% { transform: translateY(-70px); } 100% { transform: translateY(70px); } }

    /* HOLO-RULETKA */
    .ruletka-maszyna { max-width: 800px; margin: 0 auto; text-align: center; }
    .holo-kolo { 
        width: 250px; 
        height: 250px; 
        border-radius: 50%; 
        border: 8px solid rgba(255,255,255,0.05); 
        margin: 0 auto 40px auto; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 7em; 
        font-family: 'Oswald'; 
        color: #fff; 
        background: rgba(5,5,5,0.8); 
        box-shadow: inset 0 0 50px #000, 0 0 30px rgba(0,0,0,0.5); 
        transition: 0.2s; 
    }
    .holo-kolo.red { border-color: #ff3333; color: #ff3333; box-shadow: inset 0 0 50px #ff3333, 0 0 50px rgba(255, 51, 51, 0.3); text-shadow: 0 0 20px #ff3333; }
    .holo-kolo.black { border-color: #444; color: #aaa; box-shadow: inset 0 0 50px #000, 0 0 30px rgba(255,255,255,0.1); text-shadow: none; }
    .holo-kolo.green { border-color: #00ff00; color: #00ff00; box-shadow: inset 0 0 50px #00ff00, 0 0 50px rgba(0, 255, 0, 0.3); text-shadow: 0 0 20px #00ff00; }
    
    .stol-zakladow { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); 
        gap: 15px; 
        background: rgba(10, 10, 10, 0.7); 
        padding: 30px; 
        border: 2px solid rgba(255, 255, 255, 0.05); 
        border-radius: 12px; 
    }
    .pole-zakladu { 
        padding: 20px 10px; 
        text-align: center; 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        border-radius: 6px; 
        font-family: 'Oswald'; 
        font-size: 1.4em; 
        text-transform: uppercase; 
        cursor: pointer; 
        transition: 0.2s; 
        user-select: none; 
        color: #fff;
    }
    .pole-zakladu.red { background: rgba(102, 0, 0, 0.7); border-color: #ff3333; }
    .pole-zakladu.black { background: rgba(20, 20, 20, 0.7); border-color: #555; }
    .pole-zakladu.green { background: rgba(0, 68, 0, 0.7); border-color: #00ff00; grid-column: span 4; font-size: 2em; color: #00ff00;}
    .pole-zakladu.inne { background: rgba(15, 15, 15, 0.7); color: #ccc; }
    
    .pole-zakladu:hover { filter: brightness(1.5); transform: scale(1.05) translateY(-3px); }
    .pole-zakladu.zaznaczone { transform: scale(1.08) translateY(-5px); box-shadow: 0 0 40px #ffd700; border-color: #ffd700; color: #ffd700; z-index: 5; position: relative; }

    /* CYBER-POKER */
    .poker-maszyna { 
        max-width: 900px; 
        margin: 0 auto; 
        background: rgba(10, 10, 10, 0.7); 
        border: 1px solid rgba(0, 204, 255, 0.2); 
        border-radius: 12px; 
        padding: 30px; 
        box-shadow: inset 0 0 60px rgba(0,0,0,0.9), 0 0 40px rgba(0, 204, 255, 0.05); 
    }
    .poker-tabela { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 10px; 
        font-size: 0.95em; 
        background: rgba(5, 5, 5, 0.8); 
        border: 1px solid rgba(0, 204, 255, 0.1); 
        padding: 20px; 
        border-radius: 6px; 
        margin-bottom: 30px; 
        color: #aaa;
    }
    .poker-tabela div { display: flex; justify-content: space-between; padding: 3px 10px; border-bottom: 1px solid #1a1a1a;}
    .poker-tabela b { color: #00ccff; text-shadow: 0 0 10px #00ccff; }
    
    .poker-stolik { display: flex; justify-content: center; gap: 20px; margin: 40px 0; perspective: 1500px; min-height: 250px; }
    .karta-poker { 
        width: 140px; 
        height: 210px; 
        background: rgba(20, 20, 20, 0.95); 
        border-radius: 12px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.8); 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between; 
        padding: 15px; 
        font-size: 2.5em; 
        font-family: 'Times New Roman', serif; 
        font-weight: bold; 
        position: relative; 
        transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        cursor: pointer; 
        user-select: none; 
        box-sizing: border-box; 
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .karta-poker.czerwona { color: #ff3333; text-shadow: 0 0 15px rgba(255, 51, 51, 0.5); }
    .karta-poker.czarna { color: #eee; text-shadow: 0 0 15px rgba(255, 255, 255, 0.3); }
    
    .karta-rewers { 
        background: radial-gradient(circle at center, #220033 0%, #050505 100%); 
        border: 3px solid #dd88ff; 
        color: transparent !important; 
        box-shadow: 0 0 20px rgba(221, 136, 255, 0.2);
    }
    
    .karta-poker.zatrzymana { transform: translateY(-25px); border: 2px solid #00ff00; box-shadow: 0 15px 40px rgba(0,255,0,0.2); }
    .karta-poker.zatrzymana::after { 
        content: 'HOLD'; 
        position: absolute; 
        top: 50%; 
        left: 50%; 
        transform: translate(-50%, -50%); 
        background: #00ff00; 
        color: #000; 
        font-family: 'Oswald'; 
        font-size: 0.6em; 
        padding: 5px 15px; 
        border-radius: 4px; 
        letter-spacing: 2px; 
        font-weight: bold;
        box-shadow: 0 0 20px #00ff00;
    }
    
    .znak-gora { text-align: left; line-height: 0.8; }
    .znak-srodek { text-align: center; font-size: 2.5em; line-height: 0.2; }
    .znak-dol { text-align: right; transform: rotate(180deg); line-height: 0.8; }

    /* WYNIK OVERLAY - SZKLANY I NEONOWY */
    .wynik-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center; z-index: 1000; visibility: hidden; opacity: 0; transition: 0.5s; flex-direction: column; backdrop-filter: blur(10px); }
    .wynik-box { background: rgba(15, 15, 15, 0.8); border: 2px solid #ffd700; padding: 60px 80px; border-radius: 12px; text-align: center; transform: scale(0.6); transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 0 100px rgba(255, 215, 0, 0.3); }
    .wynik-overlay.show { visibility: visible; opacity: 1; }
    .wynik-overlay.show .wynik-box { transform: scale(1); }
    
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; font-weight: bold; border-radius: 4px; backdrop-filter: blur(5px);}
</style>

<div class="gra-kontener">
    <div class="page-container">

    <div class="kasyno-header">
        <h1>Kasyno Golden Dragon</h1>
        <p>Zaryzykuj wszystko. Zdobądź miasto.</p>
    </div>

    <?php echo $komunikat; ?>

    <div class="kasyno-nawigacja">
        <a href="game.php?page=kasyno&gra=sloty" class="gra-zakladka <?php if($gra=='sloty') echo 'aktywna'; ?>">🎰 Cyber-Sloty</a>
        <a href="game.php?page=kasyno&gra=ruletka" class="gra-zakladka <?php if($gra=='ruletka') echo 'aktywna'; ?>">🎲 Holo-Ruletka</a>
        <a href="game.php?page=kasyno&gra=poker" class="gra-zakladka <?php if($gra=='poker') echo 'aktywna'; ?>">🃏 Cyber-Poker</a>
    </div>


    <?php if ($gra == 'sloty'): ?>
    <div class="slot-maszyna">
        <div class="tabela-wyplat">
            <div><span>🍒</span> 3x = <b>x2</b></div>
            <div><span>🍋</span> 3x = <b>x3</b></div>
            <div><span>🔔</span> 3x = <b>x5</b></div>
            <div><span>💎</span> 3x = <b>x10</b></div>
            <div><span>7️⃣</span> 3x = <b style="color:#00ff00;">x50 JACKPOT</b></div>
        </div>
        <div class="bebny-kontener">
            <div class="beben" id="beben-1">❓</div>
            <div class="beben" id="beben-2">❓</div>
            <div class="beben" id="beben-3">❓</div>
        </div>
        <div class="panel-sterowania">
            <div style="color: #aaa; margin-bottom: 10px; font-size: 1.1em;">Portfel: <b style="color: #00ff00; font-family: 'Oswald'; font-size: 1.3em;"><?php echo number_format($gotowka, 0, '', ' '); ?> $</b></div>
            <form method="POST" novalidate>
                <input type="hidden" name="akcja" value="zakrec_slot">
                <input type="number" name="stawka" class="input-stawka" value="100">
                <br><button type="submit" class="btn-spin">ZAKRĘĆ</button>
            </form>
        </div>
    </div>

    <script>
        const czyAnimowacSlot = <?php echo $animuj_slota ? 'true' : 'false'; ?>;
        const wynikSlota = <?php echo json_encode($wynik_bębnow); ?>;
        
        if (czyAnimowacSlot) {
            const b1 = document.getElementById('beben-1'); const b2 = document.getElementById('beben-2'); const b3 = document.getElementById('beben-3');
            b1.classList.add('kręcenie'); b2.classList.add('kręcenie'); b3.classList.add('kręcenie');
            b1.innerText = '🎰'; b2.innerText = '🎰'; b3.innerText = '🎰';

            setTimeout(() => { b1.classList.remove('kręcenie'); b1.innerText = wynikSlota[0]; }, 1000); 
            setTimeout(() => { b2.classList.remove('kręcenie'); b2.innerText = wynikSlota[1]; }, 2000); 
            setTimeout(() => { 
                b3.classList.remove('kręcenie'); b3.innerText = wynikSlota[2];
                setTimeout(() => { document.getElementById('ekran-wyniku-slot').classList.add('show'); }, 500);
            }, 3000); 
        }
    </script>

    <div class="wynik-overlay" id="ekran-wyniku-slot">
        <div class="wynik-box">
            <?php if ($stan_gry_slot == "wygrana"): ?>
                <h2 style="color: #ffd700; font-family: 'Oswald'; font-size: 4em; margin: 0; text-shadow: 0 0 20px #ffd700;">WYGRANA!</h2>
                <p style="color: #fff; font-size: 1.5em;">Wygrywasz: <b style="color: #00ff00;">+<?php echo number_format($kwota_wygranej_slot, 0, '', ' '); ?> $</b></p>
            <?php elseif ($stan_gry_slot == "przegrana"): ?>
                <h2 style="color: #ff3333; font-family: 'Oswald'; font-size: 3em; margin: 0; text-shadow: 0 0 20px #ff3333;">PRZEGRANA</h2>
                <p style="color: #fff; font-size: 1.5em;">Kasyno zawsze wygrywa.</p>
            <?php endif; ?>
            <button class="gra-zakladka" style="margin-top: 20px; background: #333;" onclick="document.getElementById('ekran-wyniku-slot').classList.remove('show');">Graj Dalej</button>
        </div>
    </div>

    <?php elseif ($gra == 'ruletka'): ?>
    <div class="ruletka-maszyna">
        
        <div class="holo-kolo" id="holo-wynik">00</div>

        <div class="stol-zakladow">
            <div class="pole-zakladu green" onclick="wybierzZaklad('green', this)">Złote Zero (x36)</div>
            <div class="pole-zakladu red" onclick="wybierzZaklad('red', this)">Czerwone (x2)</div>
            <div class="pole-zakladu black" onclick="wybierzZaklad('black', this)">Czarne (x2)</div>
            <div class="pole-zakladu inne" onclick="wybierzZaklad('even', this)">Parzyste (x2)</div>
            <div class="pole-zakladu inne" onclick="wybierzZaklad('odd', this)">Nieparzyste (x2)</div>
            <div class="pole-zakladu inne" style="grid-column: span 2;" onclick="wybierzZaklad('low', this)">Niskie 1-18 (x2)</div>
            <div class="pole-zakladu inne" style="grid-column: span 2;" onclick="wybierzZaklad('high', this)">Wysokie 19-36 (x2)</div>
        </div>

        <div class="panel-sterowania">
            <div style="color: #aaa; margin-bottom: 10px; font-size: 1.1em;">Portfel: <b style="color: #00ff00; font-family: 'Oswald'; font-size: 1.3em;"><?php echo number_format($gotowka, 0, '', ' '); ?> $</b></div>
            
            <form method="POST" novalidate id="form-ruletka">
                <input type="hidden" name="akcja" value="zakrec_ruletka">
                <input type="hidden" name="typ_zakladu" id="ukryty-zaklad" value="">
                
                <input type="number" name="stawka" class="input-stawka" value="100">
                <br>
                <button type="submit" class="btn-spin" id="btn-zakrec-ruletka">ZAKRĘĆ</button>
            </form>
        </div>
    </div>

    <script>
        function wybierzZaklad(typ, element) {
            document.querySelectorAll('.pole-zakladu').forEach(el => el.classList.remove('zaznaczone'));
            element.classList.add('zaznaczone');
            document.getElementById('ukryty-zaklad').value = typ;
        }

        const czyAnimowacRul = <?php echo $animuj_ruletke ? 'true' : 'false'; ?>;
        const wylosowanaLiczba = <?php echo $wylosowana_liczba; ?>;
        const wylosowanyKolor = "<?php echo $wylosowany_kolor; ?>";
        const holoKolo = document.getElementById('holo-wynik');

        if (czyAnimowacRul) {
            let czasAnimacji = 0;
            const kolory = ['red', 'black', 'green'];
            
            let interval = setInterval(() => {
                holoKolo.innerText = Math.floor(Math.random() * 37);
                holoKolo.className = 'holo-kolo ' + kolory[Math.floor(Math.random() * kolory.length)];
                czasAnimacji += 50;

                if (czasAnimacji >= 2500) {
                    clearInterval(interval);
                    holoKolo.innerText = wylosowanaLiczba;
                    holoKolo.className = 'holo-kolo ' + wylosowanyKolor;
                    
                    setTimeout(() => { document.getElementById('ekran-wyniku-ruletka').classList.add('show'); }, 500);
                }
            }, 50);
        }
    </script>

    <div class="wynik-overlay" id="ekran-wyniku-ruletka">
        <div class="wynik-box">
            <?php if ($stan_gry_ruletka == "wygrana"): ?>
                <h2 style="color: #ffd700; font-family: 'Oswald'; font-size: 4em; margin: 0; text-shadow: 0 0 20px #ffd700;">TRAFIONY!</h2>
                <p style="color: #fff; font-size: 1.5em;">Wygrywasz: <b style="color: #00ff00;">+<?php echo number_format($kwota_wygranej_ruletka, 0, '', ' '); ?> $</b></p>
            <?php elseif ($stan_gry_ruletka == "przegrana"): ?>
                <h2 style="color: #ff3333; font-family: 'Oswald'; font-size: 3em; margin: 0; text-shadow: 0 0 20px #ff3333;">PUDŁO</h2>
                <p style="color: #fff; font-size: 1.5em;">Kasyno zgarnia Twoje pieniądze.</p>
            <?php endif; ?>
            <button class="gra-zakladka" style="margin-top: 20px; background: #333;" onclick="document.getElementById('ekran-wyniku-ruletka').classList.remove('show');">Graj Dalej</button>
        </div>
    </div>

    <?php elseif ($gra == 'poker'): ?>
    <div class="poker-maszyna">
        <div class="poker-tabela">
            <div><span>Poker Królewski</span> <b>x800</b></div>
            <div><span>Poker</span> <b>x50</b></div>
            <div><span>Kareta</span> <b>x25</b></div>
            <div><span>Full</span> <b>x9</b></div>
            <div><span>Kolor</span> <b>x6</b></div>
            <div><span>Strit</span> <b>x4</b></div>
            <div><span>Trójka</span> <b>x3</b></div>
            <div><span>Dwie Pary</span> <b>x2</b></div>
            <div><span>Para (Walety+)</span> <b>x1</b></div>
            <div style="grid-column: span 2; text-align: center; justify-content: center; margin-top: 5px; color: #555;">(Pary poniżej Waletów nie wygrywają)</div>
        </div>

        <form method="POST" id="form-poker">
            <div class="poker-stolik">
                <?php 
                for ($i = 0; $i < 5; $i++): 
                    if ($stan_gry_poker == 'start' || empty($karty_poker)) {
                        // Rewersy kart przed rozpoczęciem gry
                        echo '<div class="karta-poker karta-rewers"></div>';
                    } else {
                        $karta = $karty_poker[$i];
                        $kolor_klasa = ($karta['kolor'] == '♥' || $karta['kolor'] == '♦') ? 'czerwona' : 'czarna';
                        
                        if ($stan_gry_poker == 'wymiana') {
                            // Karta interaktywna (kliknij by zatrzymać)
                            echo '<div class="karta-poker '.$kolor_klasa.'" onclick="zaznaczKarte('.$i.', this)">';
                            echo '<div class="znak-gora">'.$karta['figura'].'</div>';
                            echo '<div class="znak-srodek">'.$karta['kolor'].'</div>';
                            echo '<div class="znak-dol">'.$karta['figura'].'</div>';
                            echo '<input type="checkbox" name="hold['.$i.']" value="1" id="karta-check-'.$i.'" style="display:none;">';
                            echo '</div>';
                        } else {
                            // Wynik końcowy (tylko do odczytu)
                            echo '<div class="karta-poker '.$kolor_klasa.'">';
                            echo '<div class="znak-gora">'.$karta['figura'].'</div>';
                            echo '<div class="znak-srodek">'.$karta['kolor'].'</div>';
                            echo '<div class="znak-dol">'.$karta['figura'].'</div>';
                            echo '</div>';
                        }
                    }
                endfor; 
                ?>
            </div>

            <div class="panel-sterowania">
                <div style="color: #aaa; margin-bottom: 10px; font-size: 1.1em;">Portfel: <b style="color: #00ff00; font-family: 'Oswald'; font-size: 1.3em;"><?php echo number_format($gotowka, 0, '', ' '); ?> $</b></div>
                
                <?php if ($stan_gry_poker == 'start'): ?>
                    <input type="hidden" name="akcja" value="poker_rozdaj">
                    <input type="number" name="stawka" class="input-stawka" value="100">
                    <br><button type="submit" class="btn-spin" style="background: linear-gradient(to bottom, #0055ff, #002288); border-color: #00ccff;">ROZDAJ KARTY</button>
                
                <?php elseif ($stan_gry_poker == 'wymiana'): ?>
                    <div style="color: #ffd700; font-family: 'Oswald'; font-size: 1.5em; margin-bottom: 15px;">Wybierz karty do zatrzymania i kliknij WYMIEŃ</div>
                    <input type="hidden" name="akcja" value="poker_wymien">
                    <button type="submit" class="btn-spin" style="background: linear-gradient(to bottom, #00aa00, #004400); border-color: #00ff00;">WYMIEŃ KARTY</button>
                    <button type="submit" name="akcja" value="poker_anuluj" class="btn-spin btn-mala">Poddaj się (Strata Stawki)</button>
                
                <?php elseif ($stan_gry_poker == 'wynik'): ?>
                    <div style="margin-bottom: 20px;">
                        <span style="color: #888; text-transform: uppercase; font-size: 0.9em;">Twój układ:</span><br>
                        <b style="color: #00ccff; font-family: 'Oswald'; font-size: 2.5em;"><?php echo $nazwa_ukladu_poker; ?></b>
                    </div>
                    
                    <?php if ($kwota_wygranej_poker > 0): ?>
                        <h2 style="color: #00ff00; font-family: 'Oswald'; font-size: 3em; margin: 0; text-shadow: 0 0 20px #00ff00;">WYGRANA!</h2>
                        <p style="color: #fff; font-size: 1.5em;">Wygrywasz łącznie: <b style="color: #00ff00;">+<?php echo number_format($laczna_wyplata_poker, 0, '', ' '); ?> $</b></p>
                    <?php else: ?>
                        <h2 style="color: #ff3333; font-family: 'Oswald'; font-size: 2em; margin: 0; text-shadow: 0 0 20px #ff3333;">NIC NIE WYGRYWASZ</h2>
                    <?php endif; ?>
                    
                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="akcja" value="poker_anuluj">
                        <button type="submit" class="gra-zakladka" style="background: #333;">Graj Od Nowa</button>
                    </form>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        function zaznaczKarte(id, kartaHTML) {
            let checkbox = document.getElementById('karta-check-' + id);
            if (checkbox.checked) {
                checkbox.checked = false;
                kartaHTML.classList.remove('zatrzymana');
            } else {
                checkbox.checked = true;
                kartaHTML.classList.add('zatrzymana');
            }
        }
    </script>
    <?php endif; ?>

    </div> </div> ```