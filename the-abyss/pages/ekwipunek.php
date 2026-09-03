<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";

// ---------------------------------------------------------
// BAZA WSZYSTKICH PRZEDMIOTÓW DO ZAKŁADANIA (TERAZ Z TYPAMI!)
// Typ Broni (Kinetyczna, Toksyczna, Przeciwpancerna, EMP)
// Typ Pancerza (Biologiczny, Opancerzony, Cybernetyczny)
// ---------------------------------------------------------
$katalog_broni = [
    // Lombard i Podstawy (Zwykle Kinetyczne)
    'eq_widelec' => ['nazwa' => 'Zardzewiały Widelec', 'atak' => 1, 'ikona' => '🍴', 'typ' => 'Kinetyczna'],
    'eq_noz' => ['nazwa' => 'Tępy Nóż Kuchenny', 'atak' => 2, 'ikona' => '🔪', 'typ' => 'Kinetyczna'],
    'eq_kij' => ['nazwa' => 'Kij Baseballowy', 'atak' => 4, 'ikona' => '🏏', 'typ' => 'Kinetyczna'],
    'eq_lancuch' => ['nazwa' => 'Łańcuch Rowerowy', 'atak' => 6, 'ikona' => '⛓️', 'typ' => 'Kinetyczna'],
    'eq_kastet' => ['nazwa' => 'Stary Kastet', 'atak' => 8, 'ikona' => '👊', 'typ' => 'Kinetyczna'],
    
    // Bronie Palne i Taktyczne
    'noz_kabar' => ['nazwa' => 'Nóż bojowy KA-BAR', 'atak' => 10, 'ikona' => '🔪', 'typ' => 'Kinetyczna'],
    'maczeta_kukri' => ['nazwa' => 'Zatruta Maczeta Kukri', 'atak' => 14, 'ikona' => '🔪', 'typ' => 'Toksyczna'],
    'glock_17' => ['nazwa' => 'Glock 17 (9mm)', 'atak' => 16, 'ikona' => '🔫', 'typ' => 'Kinetyczna'],
    'desert_eagle' => ['nazwa' => 'Desert Eagle .50 (AP)', 'atak' => 26, 'ikona' => '🔫', 'typ' => 'Przeciwpancerna'],
    'pm_uzi' => ['nazwa' => 'Klasyczne Uzi', 'atak' => 22, 'ikona' => '💨', 'typ' => 'Kinetyczna'],
    'pm_p90' => ['nazwa' => 'P90 z Amunicją Jadową', 'atak' => 32, 'ikona' => '💨', 'typ' => 'Toksyczna'],
    'karabin_ak47' => ['nazwa' => 'AK-47', 'atak' => 35, 'ikona' => '🎯', 'typ' => 'Kinetyczna'],
    'karabin_m4a1' => ['nazwa' => 'M4A1 (Amunicja AP)', 'atak' => 36, 'ikona' => '🎯', 'typ' => 'Przeciwpancerna'],
    'strzelba_mossberg' => ['nazwa' => 'Mossberg 500', 'atak' => 40, 'ikona' => '💥', 'typ' => 'Kinetyczna'],
    'snajperka_awp' => ['nazwa' => 'Karabin AWP (EMP)', 'atak' => 60, 'ikona' => '🔭', 'typ' => 'EMP'],
    'lmg_m249' => ['nazwa' => 'M249 SAW (AP)', 'atak' => 70, 'ikona' => '🔥', 'typ' => 'Przeciwpancerna'],
    'wyrzutnia_rpg7' => ['nazwa' => 'Wyrzutnia RPG-7 (EMP)', 'atak' => 100, 'ikona' => '🚀', 'typ' => 'EMP']
];

$katalog_pancerzy = [
    // Podstawowe ubrania (Biologiczny - ciało osłonięte tylko szmatami)
    'eq_bluza' => ['nazwa' => 'Gruba Bluza z Kapturem', 'obrona' => 1, 'ikona' => '🧥', 'typ' => 'Biologiczny'],
    'eq_kurtka' => ['nazwa' => 'Skórzana Kurtka', 'obrona' => 3, 'ikona' => '🏍️', 'typ' => 'Biologiczny'],
    
    // Lekkie pancerze (Opancerzony)
    'eq_opony' => ['nazwa' => 'Pancerz z Opon', 'obrona' => 5, 'ikona' => '🛞', 'typ' => 'Opancerzony'],
    'eq_kamizelka' => ['nazwa' => 'Kamizelka Kuloodporna', 'obrona' => 8, 'ikona' => '🦺', 'typ' => 'Opancerzony'],
    'pancerz_taktyczny' => ['nazwa' => 'Pancerz Taktyczny SWAT', 'obrona' => 15, 'ikona' => '🛡️', 'typ' => 'Opancerzony'],
    
    // Pancerze wszczepiane (Cybernetyczny)
    'pancerz_taktyczny_upg' => ['nazwa' => 'Egzoszkielet Taktyczny', 'obrona' => 24, 'ikona' => '🤖', 'typ' => 'Cybernetyczny']
];

// 1. LOGIKA ZAKŁADANIA I UŻYWANIA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Zmiana Broni
    if (isset($_POST['zaloz_bron'])) {
        $kod = $_POST['kod_przedmiotu'];
        $w = $polaczenie->query("SELECT $kod FROM gracze WHERE id=$id_gracza");
        $posiada = $w->fetch_assoc()[$kod];
        
        if (isset($katalog_broni[$kod]) && $posiada > 0) {
            $b = $katalog_broni[$kod];
            $polaczenie->query("UPDATE gracze SET bron_zalozona = '{$b['nazwa']}', bonus_atak = {$b['atak']}, bron_ulepszona = 0 WHERE id = $id_gracza");
            $komunikat = "<div class='sukces' style='border-color:#ffaa00; color:#ffaa00;'>Bierzesz w dłonie: <b>{$b['nazwa']}</b> (+{$b['atak']} Atak).</div>";
        }
    }
    
    // Zmiana Pancerza
    if (isset($_POST['zaloz_pancerz'])) {
        $kod = $_POST['kod_przedmiotu'];
        $w = $polaczenie->query("SELECT $kod FROM gracze WHERE id=$id_gracza");
        $posiada = $w->fetch_assoc()[$kod];
        
        if (isset($katalog_pancerzy[$kod]) && $posiada > 0) {
            $p = $katalog_pancerzy[$kod];
            $polaczenie->query("UPDATE gracze SET pancerz_zalozony = '{$p['nazwa']}', bonus_obrona = {$p['obrona']}, pancerz_ulepszony = 0 WHERE id = $id_gracza");
            $komunikat = "<div class='sukces' style='border-color:#00aaff; color:#00ccff;'>Wciągasz na siebie: <b>{$p['nazwa']}</b> (+{$p['obrona']} Obrona).</div>";
        }
    }

    // Apteczka
    if (isset($_POST['uzyj_apteczki'])) {
        $w = $polaczenie->query("SELECT apteczki, hp_aktualne, hp_max FROM gracze WHERE id=$id_gracza");
        $g_temp = $w->fetch_assoc();
        if ($g_temp['apteczki'] > 0) {
            if ($g_temp['hp_aktualne'] < $g_temp['hp_max']) {
                $nowe_hp = min($g_temp['hp_max'], $g_temp['hp_aktualne'] + 50);
                $polaczenie->query("UPDATE gracze SET hp_aktualne = $nowe_hp, apteczki = apteczki - 1 WHERE id = $id_gracza");
                $komunikat = "<div class='sukces' style='border-color:#ff3333; color:#ff3333;'>Użyto apteczki. Odzyskujesz zdrowie.</div>";
            } else { $komunikat = "<div class='blad'>Jesteś w pełni zdrowy.</div>"; }
        }
    }
}

// 2. POBIERANIE WSZYSTKICH PRZEDMIOTÓW PODSTAWOWYCH
$wynik = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// 3. POBIERANIE ŁUPÓW Z BAZY DANYCH (NOWOŚĆ Z ARENY)
// Pobieramy przedmioty przypisane do gracza z nowej tabeli
$lupy_wynik = $polaczenie->query("SELECT nazwa, ilosc FROM przedmioty_gracze WHERE gracz_id = $id_gracza ORDER BY nazwa ASC");
$lupy = [];
if ($lupy_wynik && $lupy_wynik->num_rows > 0) {
    while($wiersz = $lupy_wynik->fetch_assoc()) {
        $lupy[] = $wiersz;
    }
}
?>

<style>
    /* ========================================================
       GLASSMORPHISM W EKWIPUNKU
       ======================================================== */
    .eq-header { 
        background: rgba(10, 10, 10, 0.6); 
        padding: 30px; 
        border: 1px solid rgba(255,255,255,0.08); 
        border-radius: 8px; 
        margin-bottom: 30px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        backdrop-filter: blur(10px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.5);
    }
    .eq-header h2 { margin: 0; color: #fff; font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: 2em; letter-spacing: 1px;}
    
    .zalozone-karta { 
        background: rgba(0, 0, 0, 0.5); 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        padding: 20px; 
        border-radius: 6px; 
        width: 45%; 
        box-shadow: inset 0 0 15px rgba(0,0,0,0.8);
    }
    .zalozone-karta span { color: #888; font-size: 0.85em; text-transform: uppercase; display: block; margin-bottom: 8px; font-family: 'Oswald', sans-serif;}
    .zalozone-karta b { color: #fff; font-size: 1.3em; font-family: 'Open Sans', sans-serif; font-weight: 700;}
    
    .kategoria-eq { margin-top: 40px; }
    .kategoria-eq h3 { 
        border-bottom: 1px solid rgba(255,255,255,0.1); 
        padding-bottom: 10px; 
        color: #fff; 
        font-family: 'Oswald', sans-serif; 
        text-transform: uppercase; 
        margin-bottom: 20px; 
        font-size: 1.5em;
        letter-spacing: 1px;
    }
    
    .przedmioty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
    
    .przedmiot-box { 
        background: rgba(10, 10, 10, 0.5); 
        border: 1px solid rgba(255,255,255,0.05); 
        padding: 20px; 
        text-align: center; 
        border-radius: 8px; 
        transition: 0.3s; 
        display: flex; 
        flex-direction: column; 
        backdrop-filter: blur(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.4);
    }
    .przedmiot-box:hover { background: rgba(20, 20, 20, 0.8); transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.6); }
    
    .przedmiot-ikona { font-size: 3.5em; margin-bottom: 15px; text-shadow: 0 0 15px rgba(255,255,255,0.2);}
    .przedmiot-nazwa { color: #fff; font-family: 'Open Sans', sans-serif; font-weight: 700; margin-bottom: 15px; font-size: 1.1em; min-height: 45px; display: flex; align-items: center; justify-content: center;}
    
    /* Typy ekwipunku - Nowe odznaki */
    .badge-typ { font-family: 'Oswald', sans-serif; font-size: 0.8em; text-transform: uppercase; padding: 2px 8px; border-radius: 4px; margin-bottom: 10px; display: inline-block;}
    .typ-kinetyczna { background: rgba(255,255,255,0.1); color: #ccc; border: 1px solid #666; }
    .typ-toksyczna { background: rgba(0,255,0,0.1); color: #00ff00; border: 1px solid #00ff00; }
    .typ-ap { background: rgba(255,170,0,0.1); color: #ffaa00; border: 1px solid #ffaa00; }
    .typ-emp { background: rgba(0,204,255,0.1); color: #00ccff; border: 1px solid #00ccff; }
    .typ-biologia { background: rgba(0,255,0,0.1); color: #00ff00; border: 1px solid #00ff00; }
    .typ-opancerzony { background: rgba(255,170,0,0.1); color: #ffaa00; border: 1px solid #ffaa00; }
    .typ-cyber { background: rgba(0,204,255,0.1); color: #00ccff; border: 1px solid #00ccff; }

    .przedmiot-ilosc { background: rgba(0,0,0,0.8); color: #fff; padding: 8px; border-radius: 4px; font-family: 'Oswald', sans-serif; font-size: 1.1em; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); }
    
    .btn-akcja { background: rgba(255,170,0,0.2); color: #ffaa00; border: 1px solid #ffaa00; padding: 10px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: 700; text-transform: uppercase; border-radius: 4px; width: 100%; transition: 0.3s; margin-top: auto; font-size: 1em; letter-spacing: 1px;}
    .btn-akcja:hover:not(:disabled) { background: #ffaa00; color: #000; box-shadow: 0 0 15px rgba(255,170,0,0.6);}
    
    .btn-akcja-obrona { background: rgba(0,204,255,0.2); color: #00ccff; border-color: #00ccff; } 
    .btn-akcja-obrona:hover:not(:disabled) { background: #00ccff; color: #000; box-shadow: 0 0 15px rgba(0,204,255,0.6);}
    
    .btn-disabled { background: rgba(10,10,10,0.8) !important; border-color: rgba(255,255,255,0.1) !important; color: #555 !important; cursor: not-allowed; }
    
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid rgba(0,255,0,0.3); color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.1em; border-radius: 4px;}
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid rgba(255,51,51,0.3); color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.1em; border-radius: 4px;}
    
    /* Kafelki dla Łupów */
    .lup-box { background: rgba(20, 0, 30, 0.6); border: 1px solid rgba(221,136,255,0.3); padding: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
    .lup-nazwa { color: #dd88ff; font-family: 'Open Sans', sans-serif; font-weight: 700; text-shadow: 0 0 5px rgba(221,136,255,0.4); }
    .lup-ilosc { background: rgba(0,0,0,0.8); color: #fff; font-family: monospace; font-size: 1.2em; padding: 5px 10px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1);}
</style>

<div style="text-align: center; margin-bottom: 30px;">
    <h1 style="font-family: 'Oswald', sans-serif; color: #fff; font-size: 3em; margin:0; text-transform: uppercase; letter-spacing: 2px; text-shadow: 0 0 20px rgba(255,255,255,0.2);">Mój Ekwipunek</h1>
</div>

<?php echo $komunikat; ?>

<div class="eq-header">
    <h2>Obecne Wyposażenie</h2>
    <div style="display: flex; gap: 20px; flex-grow: 1; justify-content: flex-end;">
        <div class="zalozone-karta" style="border-color: rgba(255,170,0,0.5);">
            <span>W dłoniach (Broń):</span>
            <b><?php echo $gracz['bron_zalozona']; ?></b> <span style="display:inline; color:#ffaa00; font-weight:bold; font-size: 1.1em; text-shadow: 0 0 10px rgba(255,170,0,0.4);">+<?php echo $gracz['bonus_atak']; ?> ATK</span>
        </div>
        <div class="zalozone-karta" style="border-color: rgba(0,204,255,0.5);">
            <span>Na ciele (Pancerz):</span>
            <b><?php echo $gracz['pancerz_zalozony']; ?></b> <span style="display:inline; color:#00ccff; font-weight:bold; font-size: 1.1em; text-shadow: 0 0 10px rgba(0,204,255,0.4);">+<?php echo $gracz['bonus_obrona']; ?> OBR</span>
        </div>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color: #ffaa00; border-color: rgba(255,170,0,0.3); text-shadow: 0 0 10px rgba(255,170,0,0.4);">⚔️ Zbrojownia</h3>
    <div class="przedmioty-grid">
        <?php 
        $ma_bron = false;
        foreach($katalog_broni as $kod => $b) {
            if (isset($gracz[$kod]) && $gracz[$kod] > 0) {
                $ma_bron = true;
                $zalozona = ($gracz['bron_zalozona'] == $b['nazwa'] || strpos($gracz['bron_zalozona'], $b['nazwa']) !== false);
                
                $klasa_typu = 'typ-kinetyczna';
                if ($b['typ'] == 'Toksyczna') $klasa_typu = 'typ-toksyczna';
                if ($b['typ'] == 'Przeciwpancerna') $klasa_typu = 'typ-ap';
                if ($b['typ'] == 'EMP') $klasa_typu = 'typ-emp';
                
                echo '<div class="przedmiot-box" style="'.($zalozona ? 'border-color: #ffaa00; box-shadow: inset 0 0 15px rgba(255,170,0,0.2);' : '').'">';
                echo '<div class="przedmiot-ikona">'.$b['ikona'].'</div>';
                echo '<div class="badge-typ '.$klasa_typu.'">Amunicja: '.$b['typ'].'</div>';
                echo '<div class="przedmiot-nazwa">'.$b['nazwa'].'</div>';
                echo '<div class="przedmiot-ilosc">Ilość: '.$gracz[$kod].' szt.</div>';
                
                if ($zalozona) {
                    echo '<button class="btn-akcja btn-disabled" disabled>W DŁONI</button>';
                } else {
                    echo '<form method="POST" style="margin: 0;"><input type="hidden" name="kod_przedmiotu" value="'.$kod.'"><button type="submit" name="zaloz_bron" class="btn-akcja">ZAŁÓŻ (+'.$b['atak'].' ATK)</button></form>';
                }
                echo '</div>';
            }
        }
        if (!$ma_bron) echo "<p style='color: #888; font-style: italic;'>Zbrojownia jest pusta. Odwiedź Sklep (Lombard) lub Warsztat.</p>";
        ?>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color: #00ccff; border-color: rgba(0,204,255,0.3); text-shadow: 0 0 10px rgba(0,204,255,0.4);">🛡️ Pancerze</h3>
    <div class="przedmioty-grid">
        <?php 
        $ma_pancerz = false;
        foreach($katalog_pancerzy as $kod => $p) {
            if (isset($gracz[$kod]) && $gracz[$kod] > 0) {
                $ma_pancerz = true;
                $zalozona = ($gracz['pancerz_zalozony'] == $p['nazwa'] || strpos($gracz['pancerz_zalozony'], $p['nazwa']) !== false);
                
                $klasa_typu = 'typ-biologia';
                if ($p['typ'] == 'Opancerzony') $klasa_typu = 'typ-opancerzony';
                if ($p['typ'] == 'Cybernetyczny') $klasa_typu = 'typ-cyber';
                
                echo '<div class="przedmiot-box" style="'.($zalozona ? 'border-color: #00ccff; box-shadow: inset 0 0 15px rgba(0,204,255,0.2);' : '').'">';
                echo '<div class="przedmiot-ikona">'.$p['ikona'].'</div>';
                echo '<div class="badge-typ '.$klasa_typu.'">Typ Ciała: '.$p['typ'].'</div>';
                echo '<div class="przedmiot-nazwa">'.$p['nazwa'].'</div>';
                echo '<div class="przedmiot-ilosc">Ilość: '.$gracz[$kod].' szt.</div>';
                
                if ($zalozona) {
                    echo '<button class="btn-akcja btn-akcja-obrona btn-disabled" disabled>UBRANE</button>';
                } else {
                    echo '<form method="POST" style="margin: 0;"><input type="hidden" name="kod_przedmiotu" value="'.$kod.'"><button type="submit" name="zaloz_pancerz" class="btn-akcja btn-akcja-obrona">ZAŁÓŻ (+'.$p['obrona'].' OBR)</button></form>';
                }
                echo '</div>';
            }
        }
        if (!$ma_pancerz) echo "<p style='color: #888; font-style: italic;'>Nie posiadasz zapasowych pancerzy.</p>";
        ?>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color: #ff3333; border-color: rgba(255,51,51,0.3); text-shadow: 0 0 10px rgba(255,51,51,0.4);">💉 Podstawowe Zasoby</h3>
    <div class="przedmioty-grid">
        <div class="przedmiot-box" style="border-color: rgba(255,51,51,0.3);">
            <div class="przedmiot-ikona" style="text-shadow: 0 0 15px rgba(255,51,51,0.4);">💊</div>
            <div class="przedmiot-nazwa">Apteczka Uliczna</div>
            <div class="przedmiot-ilosc" style="color: #ff3333;"><?php echo $gracz['apteczki']; ?> szt.</div>
            <?php if($gracz['apteczki'] > 0): ?>
                <form method="POST" style="margin: 0;"><button type="submit" name="uzyj_apteczki" class="btn-akcja" style="background: rgba(255,51,51,0.2); color: #ff3333; border-color: #ff3333;">UŻYJ (+50 HP)</button></form>
            <?php else: ?>
                <button class="btn-akcja btn-disabled" disabled>BRAK</button>
            <?php endif; ?>
        </div>
        
        <div class="przedmiot-box"><div class="przedmiot-ikona">🔩</div><div class="przedmiot-nazwa">Stalowy Złom</div><div class="przedmiot-ilosc"><?php echo $gracz['zlom_stalowy']; ?> szt.</div></div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">⚙️</div><div class="przedmiot-nazwa">Części Mechaniczne</div><div class="przedmiot-ilosc"><?php echo $gracz['czesci_mechaniczne']; ?> szt.</div></div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">🧵</div><div class="przedmiot-nazwa">Kevlar i Syntetyki</div><div class="przedmiot-ilosc"><?php echo $gracz['syntetyki']; ?> szt.</div></div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">🔋</div><div class="przedmiot-nazwa">Elektronika</div><div class="przedmiot-ilosc"><?php echo $gracz['elektronika']; ?> szt.</div></div>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color: #dd88ff; border-color: rgba(221,136,255,0.3); text-shadow: 0 0 10px rgba(221,136,255,0.4);">💎 Łupy z Areny (Artefakty)</h3>
    <?php if (empty($lupy)): ?>
        <p style="color: #888; font-style: italic;">Nie zszabrowałeś jeszcze żadnych unikalnych artefaktów. Udaj się do Doków, by zdobyć unikalny Łup!</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
            <?php foreach($lupy as $lup): ?>
                <div class="lup-box">
                    <div class="lup-nazwa">🎁 <?php echo htmlspecialchars($lup['nazwa']); ?></div>
                    <div class="lup-ilosc">x<?php echo $lup['ilosc']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>