<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// 1. POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT gotowka, hp_aktualne, hp_max, energia_aktualna, energia_max, apteczki FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

$komunikat = "";

// 2. LOGIKA LECZENIA I ZAKUPÓW
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['lecz'])) {
    $typ_leczenia = $_POST['lecz'];
    $brakuje_hp = $gracz['hp_max'] - $gracz['hp_aktualne'];
    
    if ($typ_leczenia == 'plastry') {
        $cena = 50;
        $ile_hp = 30;
        $nazwa = "Szybkie łatanie";
        
        if ($gracz['gotowka'] < $cena) {
            $komunikat = "<div class='blad'>Doktor parska śmiechem: \"Wróć jak będziesz mieć kasę, nędzarzu!\"</div>";
        } elseif ($gracz['hp_aktualne'] >= $gracz['hp_max']) {
            $komunikat = "<div class='info'>Jesteś w pełni sił. Nie marnuj mojego czasu.</div>";
        } else {
            $nowe_hp = min($gracz['hp_max'], $gracz['hp_aktualne'] + $ile_hp);
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena, hp_aktualne = $nowe_hp WHERE id = $id_gracza");
            $komunikat = "<div class='sukces'>Zastosowano: $nazwa. Czujesz się trochę lepiej!</div>";
        }
        
    } else if ($typ_leczenia == 'pelne') {
        $cena = $brakuje_hp * 2;
        $nazwa = "Pełna operacja";
        
        if ($brakuje_hp <= 0) {
            $komunikat = "<div class='info'>Nie potrzebujesz operacji, jesteś zdrów jak ryba.</div>";
        } elseif ($gracz['gotowka'] < $cena) {
            $komunikat = "<div class='blad'>Nie stać Cię na operację! Brakuje Ci gotówki (Koszt: $cena $).</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena, hp_aktualne = hp_max WHERE id = $id_gracza");
            $komunikat = "<div class='sukces'>$nazwa zakończona. Jesteś jak nowo narodzony (Koszt: $cena $).</div>";
        }
        
    } else if ($typ_leczenia == 'apteczka') {
        $cena = 150;
        if ($gracz['gotowka'] < $cena) {
            $komunikat = "<div class='blad'>Materiały medyczne kosztują! Przyjdź z gotówką.</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena, apteczki = apteczki + 1 WHERE id = $id_gracza");
            $komunikat = "<div class='sukces'>Kupiłeś Wojskową Apteczkę. Możesz jej użyć w Dokach podczas walki.</div>";
        }
        
    } else if ($typ_leczenia == 'adrenalina') {
        $cena = 1000;
        if ($gracz['energia_aktualna'] >= $gracz['energia_max']) {
            $komunikat = "<div class='info'>Jesteś w pełni pobudzony, serce Ci nie wytrzyma kolejnej dawki!</div>";
        } elseif ($gracz['gotowka'] < $cena) {
            $komunikat = "<div class='blad'>Ten towar to luksus. Potrzebujesz 1 000 $.</div>";
        } else {
            $nowa_en = min($gracz['energia_max'], $gracz['energia_aktualna'] + 5);
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena, energia_aktualna = $nowa_en WHERE id = $id_gracza");
            $komunikat = "<div class='sukces' style='color:#ffaa00; border-color:#ffaa00;'>Przyjąłeś zastrzyk. Odzyskujesz +5 Energii!</div>";
        }
    }

    $wynik = $polaczenie->query("SELECT gotowka, hp_aktualne, hp_max, energia_aktualna, energia_max, apteczki FROM gracze WHERE id=$id_gracza");
    $gracz = $wynik->fetch_assoc();
}

$cena_pelnego = ($gracz['hp_max'] - $gracz['hp_aktualne']) * 2;
?>

<style>
    /* ==============================================
       GLASSMORPHISM W KLINICE 
    ============================================== */
    .szpital-header { 
        background: rgba(17, 17, 17, 0.6);
        border: 1px solid rgba(0, 204, 255, 0.2); 
        border-radius: 8px; 
        padding: 30px;
        text-align: center; 
        box-shadow: 0 0 30px rgba(0, 204, 255, 0.1);
        margin-bottom: 25px; 
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .szpital-header h1 { 
        color: #00ccff; 
        font-family: 'Oswald', sans-serif; 
        font-size: 2.5em; 
        margin: 0; 
        text-shadow: 0 0 15px rgba(0, 204, 255, 0.6); 
        text-transform: uppercase; 
        letter-spacing: 2px;
    }

    .status-panel { display: flex; gap: 20px; margin-bottom: 25px; }
    .status-box { 
        flex: 1; 
        background: rgba(5, 5, 5, 0.6); 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        padding: 20px; 
        text-align: center; 
        border-radius: 8px; 
        backdrop-filter: blur(8px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.5);
    }
    
    .pasek-bg { background: rgba(0,0,0,0.8); border: 1px solid rgba(255,255,255,0.1); height: 12px; border-radius: 10px; margin-top: 10px; overflow: hidden; box-shadow: inset 0 2px 5px rgba(0,0,0,0.8); }
    .pasek-hp-fill { background: linear-gradient(90deg, #880000, #ff3333); height: 100%; transition: 0.5s; box-shadow: 0 0 10px rgba(255,51,51,0.5); }
    .pasek-en-fill { background: linear-gradient(90deg, #005588, #00ccff); height: 100%; transition: 0.5s; box-shadow: 0 0 10px rgba(0,204,255,0.5); }

    .klinika-kontener { 
        background: rgba(15, 15, 15, 0.6); 
        border: 1px solid rgba(255, 255, 255, 0.05); 
        padding: 30px; 
        border-radius: 10px; 
        display: flex; 
        gap: 30px; 
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        box-shadow: 0 10px 40px rgba(0,0,0,0.8);
    }
    
    .lekarz-img { width: 220px; height: 220px; background: url('https://via.placeholder.com/220x220/050505/00ccff?text=Doktor+Rzeznik') center/cover; border: 1px solid rgba(0, 204, 255, 0.3); border-radius: 6px; box-shadow: 0 0 20px rgba(0, 204, 255, 0.1); }
    
    .opcje-leczenia { flex: 1; }
    
    /* Półprzezroczyste karty usług */
    .opcja-karta { 
        background: rgba(5, 5, 5, 0.6); 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        padding: 15px 20px; 
        margin-bottom: 15px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        border-radius: 6px; 
        transition: 0.3s;
    }
    .opcja-karta:hover { background: rgba(0, 204, 255, 0.05); border-color: rgba(0, 204, 255, 0.4); transform: translateX(5px); }
    
    .opcja-info b { display: block; color: #fff; font-family: 'Oswald', sans-serif; font-size: 1.1em; letter-spacing: 0.5px; margin-bottom: 3px;}
    .opcja-info span { color: #aaa; font-size: 0.9em; font-family: 'Open Sans', sans-serif; }

    /* Eleganckie przyciski */
    .btn-lecz { background: rgba(0,0,0,0.5); color: #00ccff; border: 1px solid #00ccff; padding: 10px 20px; font-family: 'Oswald', sans-serif; cursor: pointer; text-transform: uppercase; border-radius: 4px; font-weight: bold; transition: 0.3s; letter-spacing: 1px; font-size: 0.95em;}
    .btn-lecz:hover { background: #00ccff; color: #000; box-shadow: 0 0 15px rgba(0, 204, 255, 0.4); }
    
    .btn-apteczka { color: #00ff00; border-color: #00ff00; }
    .btn-apteczka:hover { background: #00ff00; color: #000; box-shadow: 0 0 15px rgba(0, 255, 0, 0.4); }
    
    .btn-adrenalina { color: #ffaa00; border-color: #ffaa00; }
    .btn-adrenalina:hover { background: #ffaa00; color: #000; box-shadow: 0 0 15px rgba(255, 170, 0, 0.4); }

    /* Komunikaty szklane */
    .sukces { color: #00ff00; background: rgba(0,255,0,0.1); padding: 15px; border: 1px solid rgba(0, 255, 0, 0.3); border-radius: 4px; margin-bottom: 20px; text-align: center; backdrop-filter: blur(5px);}
    .blad { color: #ff3333; background: rgba(255,51,51,0.1); padding: 15px; border: 1px solid rgba(255, 51, 51, 0.3); border-radius: 4px; margin-bottom: 20px; text-align: center; backdrop-filter: blur(5px);}
    .info { color: #00ccff; background: rgba(0,204,255,0.1); padding: 15px; border: 1px solid rgba(0, 204, 255, 0.3); border-radius: 4px; margin-bottom: 20px; text-align: center; backdrop-filter: blur(5px);}
</style>

<div class="szpital-header">
    <h1>Klinika Uliczna "U Rzeźnika"</h1>
</div>

<?php echo $komunikat; ?>

<div class="status-panel">
    <div class="status-box">
        <span style="color:#aaa; font-size: 0.9em; text-transform: uppercase; font-weight: bold;">Zdrowie: <b style="color: #ff3333; font-size: 1.2em; font-family:'Oswald';"><?php echo $gracz['hp_aktualne']; ?> / <?php echo $gracz['hp_max']; ?></b></span>
        <div class="pasek-bg"><div class="pasek-hp-fill" style="width: <?php echo ($gracz['hp_aktualne'] / $gracz['hp_max']) * 100; ?>%;"></div></div>
    </div>
    <div class="status-box">
        <span style="color:#aaa; font-size: 0.9em; text-transform: uppercase; font-weight: bold;">Energia: <b style="color: #00ccff; font-size: 1.2em; font-family:'Oswald';"><?php echo $gracz['energia_aktualna']; ?> / <?php echo $gracz['energia_max']; ?></b></span>
        <div class="pasek-bg"><div class="pasek-en-fill" style="width: <?php echo ($gracz['energia_aktualna'] / $gracz['energia_max']) * 100; ?>%;"></div></div>
    </div>
    <div class="status-box" style="border-color: rgba(0,255,0,0.3); box-shadow: inset 0 0 15px rgba(0,255,0,0.1);">
        <span style="color:#aaa; display:block; font-size: 0.85em; text-transform: uppercase;">Posiadane Apteczki:</span>
        <b style="color: #00ff00; font-size: 1.8em; font-family: 'Oswald'; text-shadow: 0 0 10px rgba(0,255,0,0.4);"><?php echo $gracz['apteczki']; ?></b>
    </div>
</div>

<div class="klinika-kontener">
    <div class="lekarz-img"></div>
    
    <div class="opcje-leczenia">
        <p style="color: #aaa; margin-top: 0; font-style: italic; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 15px;">— Przeżyłeś, co? Nieźle Cię urządziły te szczury. Wybieraj szybko, mam kolejnych pacjentów na zapleczu.</p>
        
        <div class="opcja-karta">
            <div class="opcja-info">
                <b>Szybkie łatanie (Plastry i Leki)</b>
                <span>Przywraca +30 HP. Idealne na drobne pogryzienia.</span>
            </div>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="lecz" value="plastry">
                <button type="submit" class="btn-lecz">50 $</button>
            </form>
        </div>

        <div class="opcja-karta" style="border-left: 3px solid #ff3333;">
            <div class="opcja-info">
                <b style="color: #ff3333;">Operacja Chirurgiczna (Pełne HP)</b>
                <span>Dynamiczny koszt: 2 $ za każdy brakujący punkt zdrowia.</span>
            </div>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="lecz" value="pelne">
                <button type="submit" class="btn-lecz" style="color:#ff3333; border-color:#ff3333;"><?php echo $cena_pelnego; ?> $</button>
            </form>
        </div>
        
        <h3 style="color: #888; font-family:'Oswald'; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 5px; margin-top: 30px;">Czarny Rynek Medyczny</h3>

        <div class="opcja-karta" style="border-left: 3px solid #00ff00;">
            <div class="opcja-info">
                <b style="color: #00ff00;">Wojskowa Apteczka Polowa</b>
                <span>Kup na wynos. Przywraca +50 HP. Możesz jej użyć w Dokach w trakcie walki!</span>
            </div>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="lecz" value="apteczka">
                <button type="submit" class="btn-lecz btn-apteczka">KUP (150 $)</button>
            </form>
        </div>

        <div class="opcja-karta" style="border-left: 3px solid #ffaa00;">
            <div class="opcja-info">
                <b style="color: #ffaa00;">Zastrzyk Adrenaliny (Żółta Fiolka)</b>
                <span>Pobudza organizm na skraj wytrzymałości. Odnawia +5 punktów Energii.</span>
            </div>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="lecz" value="adrenalina">
                <button type="submit" class="btn-lecz btn-adrenalina">1 000 $</button>
            </form>
        </div>

    </div>
</div>