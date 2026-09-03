<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";
$zakladka = isset($_GET['zakladka']) ? $_GET['zakladka'] : 'materialy';

// 1. POBIERANIE WSZYSTKICH DANYCH (SELECT *)
$wynik = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// --- ZAAWANSOWANY SŁOWNIK PRZEDMIOTÓW ---
$slownik_przedmiotow = [
    // Materiały i Medycyna
    'zlom_stalowy' => 'Stalowy Złom 🔩',
    'czesci_mechaniczne' => 'Części Mechaniczne ⚙️',
    'syntetyki' => 'Kevlar / Syntetyki 🧵',
    'elektronika' => 'Elektronika 🔋',
    'apteczki' => 'Apteczka Uliczna 💉',
    
    // Rzemiosło Podstawowe i Mk II
    'pistolet_samorobka' => 'Pistolet Samoróbka 9mm 🔫',
    'pistolet_samorobka_upg' => 'Pistolet Samoróbka Mk II 🔫🌟',
    'karabin_szturmowy' => 'Stary Karabin Szturmowy 🪖',
    'karabin_szturmowy_upg' => 'Karabin Szturmowy Mk II 🪖🌟',
    'pancerz_taktyczny' => 'Pancerz Taktyczny 🛡️',
    'pancerz_taktyczny_upg' => 'Pancerz Taktyczny Mk II 🛡️🌟',
    
    // Nowy, Potężny Arsenał Inżyniera
    'noz_kabar' => 'Nóż bojowy KA-BAR 🔪',
    'maczeta_kukri' => 'Maczeta Kukri 🔪',
    'glock_17' => 'Glock 17 (9mm) 🔫',
    'desert_eagle' => 'Desert Eagle .50 AE 🔫',
    'pm_uzi' => 'Klasyczne Uzi 💨',
    'pm_p90' => 'Nowoczesne P90 💨',
    'karabin_ak47' => 'AK-47 (Wschód) 🎯',
    'karabin_m4a1' => 'M4A1 (Zachód) 🎯',
    'strzelba_mossberg' => 'Mossberg 500 💥',
    'snajperka_awp' => 'Karabin AWP 🔭',
    'lmg_m249' => 'M249 SAW 🔥',
    'wyrzutnia_rpg7' => 'Wyrzutnia RPG-7 🚀'
];

$kategoria_materialy = ['zlom_stalowy', 'czesci_mechaniczne', 'syntetyki', 'elektronika'];

// 2. LOGIKA: WYSTAWIANIE OFERTY
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['wystaw_oferte'])) {
    $co_sprzedaje = $polaczenie->real_escape_string($_POST['przedmiot']);
    $ilosc = (int)$_POST['ilosc'];
    $cena = (int)$_POST['cena'];
    
    $typ = in_array($co_sprzedaje, $kategoria_materialy) ? 'material' : 'przedmiot';

    if ($ilosc <= 0 || $cena <= 0) {
        $komunikat = "<div class='blad'>Ilość i cena muszą być większe od zera!</div>";
    } elseif (!isset($gracz[$co_sprzedaje]) || $gracz[$co_sprzedaje] < $ilosc) {
        $komunikat = "<div class='blad'>Nie masz w ekwipunku takiej ilości tego przedmiotu!</div>";
    } else {
        $polaczenie->query("UPDATE gracze SET $co_sprzedaje = $co_sprzedaje - $ilosc WHERE id = $id_gracza");
        $polaczenie->query("INSERT INTO rynek_oferty (id_sprzedawcy, typ_oferty, kod_przedmiotu, ilosc, cena_za_sztuke) VALUES ($id_gracza, '$typ', '$co_sprzedaje', $ilosc, $cena)");
        $komunikat = "<div class='sukces'>Towar został wystawiony na Rynku!</div>";
        $gracz[$co_sprzedaje] -= $ilosc;
    }
}

// 3. LOGIKA: KUPOWANIE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kup_oferte'])) {
    $id_oferty = (int)$_POST['id_oferty'];
    
    $wynik_oferty = $polaczenie->query("SELECT * FROM rynek_oferty WHERE id = $id_oferty");
    if ($wynik_oferty->num_rows > 0) {
        $oferta = $wynik_oferty->fetch_assoc();
        $calkowity_koszt = $oferta['ilosc'] * $oferta['cena_za_sztuke'];
        $sprzedawca_id = $oferta['id_sprzedawcy'];
        $przedmiot = $oferta['kod_przedmiotu'];
        $ilosc = $oferta['ilosc'];
        
        if ($sprzedawca_id == $id_gracza) {
            $komunikat = "<div class='blad'>Nie możesz kupić własnej oferty! (Przejdź do 'Moje Oferty', by ją zdjąć).</div>";
        } elseif ($gracz['gotowka'] < $calkowity_koszt) {
            $komunikat = "<div class='blad'>Brak gotówki! Potrzebujesz $calkowity_koszt $.</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $calkowity_koszt, $przedmiot = $przedmiot + $ilosc WHERE id = $id_gracza");
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $calkowity_koszt WHERE id = $sprzedawca_id");
            $polaczenie->query("DELETE FROM rynek_oferty WHERE id = $id_oferty");
            $prowizja = round($prowizja * pochodzenie_bonus($gracz_r, 'rynek_prowizja_mult', 1.0));
$cena_sprzedazy = round($cena_sprzedazy * pochodzenie_bonus($gracz_r, 'rynek_cena_sprzedazy_mult', 1.0));
            
            $nazwa_wys = isset($slownik_przedmiotow[$przedmiot]) ? $slownik_przedmiotow[$przedmiot] : $przedmiot;
            $komunikat = "<div class='sukces'>Transakcja udana! Kupiłeś $ilosc x $nazwa_wys za $calkowity_koszt $.</div>";
            $gracz['gotowka'] -= $calkowity_koszt;
            $gracz[$przedmiot] += $ilosc;
        }
    } else {
        $komunikat = "<div class='blad'>Ta oferta została już kupiona lub wycofana.</div>";
    }
}


// 4. LOGIKA: WYCOFANIE OFERTY
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['anuluj_oferte'])) {
    $id_oferty = (int)$_POST['id_oferty'];
    $wynik_oferty = $polaczenie->query("SELECT * FROM rynek_oferty WHERE id = $id_oferty AND id_sprzedawcy = $id_gracza");
    
    if ($wynik_oferty->num_rows > 0) {
        $oferta = $wynik_oferty->fetch_assoc();
        $przedmiot = $oferta['kod_przedmiotu'];
        $ilosc = $oferta['ilosc'];
        
        $polaczenie->query("UPDATE gracze SET $przedmiot = $przedmiot + $ilosc WHERE id = $id_gracza");
        $polaczenie->query("DELETE FROM rynek_oferty WHERE id = $id_oferty");
        
        $komunikat = "<div class='sukces'>Wycofano ofertę. Przedmioty wróciły do Ekwipunku.</div>";
        $gracz[$przedmiot] += $ilosc;
    }
}
?>

<style>
    .rynek-nav { display: flex; background: #111; border: 1px solid #333; margin-bottom: 20px; border-radius: 4px; overflow: hidden; }
    .rynek-tab { flex: 1; text-align: center; padding: 15px; color: #aaa; text-decoration: none; font-family: 'Oswald'; text-transform: uppercase; border-right: 1px solid #222; transition: 0.3s; }
    .rynek-tab:last-child { border-right: none; }
    .rynek-tab:hover { background: #1a1a1a; color: #fff; }
    .rynek-tab.aktywny { background: #ffaa00; color: #000; font-weight: bold; }
    .rynek-panel { background: #0a0a0a; border: 1px solid #333; padding: 25px; border-radius: 4px; margin-bottom: 20px; }
    .rynek-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 15px; margin-bottom: 20px; }
    .rynek-header h2 { font-family: 'Oswald'; color: #fff; margin: 0; text-transform: uppercase; }
    .btn-wystaw { background: #aa3300; color: #fff; border: 1px solid #ff5500; padding: 10px 20px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; text-decoration: none; display: inline-block; transition: 0.3s; }
    .btn-wystaw:hover { background: #ff5500; }
    .rynek-tabela { width: 100%; border-collapse: collapse; }
    .rynek-tabela th { background: #111; color: #888; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #333; text-align: left; font-size: 0.9em; }
    .rynek-tabela td { padding: 15px 12px; border-bottom: 1px dashed #222; color: #ccc; }
    .rynek-tabela tr:hover td { background: rgba(255, 170, 0, 0.05); }
    .cena { color: #00ff00; font-family: 'Oswald'; font-size: 1.1em; }
    .btn-kup { background: transparent; color: #00ff00; border: 1px solid #00ff00; padding: 6px 15px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; }
    .btn-kup:hover { background: #00ff00; color: #000; }
    .btn-anuluj { background: transparent; color: #ff3333; border: 1px solid #ff3333; padding: 6px 15px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; }
    .btn-anuluj:hover { background: #ff3333; color: #000; }
    .input-text, .input-select { width: 100%; padding: 12px; background: #050505; border: 1px solid #444; color: #fff; border-radius: 3px; font-size: 1em; margin-bottom: 15px; box-sizing: border-box; }
    .input-text:focus, .input-select:focus { outline: none; border-color: #ffaa00; }
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.1em; }
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.1em; }
</style>

<h1 class="witaj">Czarny Rynek</h1>

<div class="rynek-nav">
    <a href="game.php?page=rynek&zakladka=materialy" class="rynek-tab <?php if($zakladka=='materialy') echo 'aktywny'; ?>">📦 Rynek Materiałów</a>
    <a href="game.php?page=rynek&zakladka=przedmioty" class="rynek-tab <?php if($zakladka=='przedmioty') echo 'aktywny'; ?>">🔫 Rynek Przedmiotów</a>
    <a href="game.php?page=rynek&zakladka=moje_oferty" class="rynek-tab <?php if($zakladka=='moje_oferty' || $zakladka=='wystaw') echo 'aktywny'; ?>">Moje Oferty</a>
</div>

<?php echo $komunikat; ?>

<?php if ($zakladka == 'materialy' || $zakladka == 'przedmioty'): 
    $typ_szukany = ($zakladka == 'materialy') ? 'material' : 'przedmiot';
?>
    <div class="rynek-panel">
        <div class="rynek-header">
            <h2><?php echo ($zakladka == 'materialy') ? 'Giełda Komponentów' : 'Handel Ekwipunkiem'; ?></h2>
            <div>
                <span style="color: #888; margin-right: 15px;">Twoja gotówka: <b style="color: #00ff00;"><?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $</b></span>
                <a href="game.php?page=rynek&zakladka=wystaw" class="btn-wystaw">WYSTAW OFERTĘ</a>
            </div>
        </div>
        
        <table class="rynek-tabela">
            <tr>
                <th>Przedmiot</th>
                <th>Sprzedawca</th>
                <th>Ilość</th>
                <th>Cena za szt.</th>
                <th>Koszt Całkowity</th>
                <th>Akcja</th>
            </tr>
            <?php
            $sql_oferty = "SELECT r.*, g.login FROM rynek_oferty r JOIN gracze g ON r.id_sprzedawcy = g.id WHERE r.typ_oferty = '$typ_szukany' ORDER BY r.data_wystawienia DESC";
            $wynik_ofert = $polaczenie->query($sql_oferty);
            
            if ($wynik_ofert->num_rows > 0) {
                while($of = $wynik_ofert->fetch_assoc()) {
                    $nazwa_wyswietlana = isset($slownik_przedmiotow[$of['kod_przedmiotu']]) ? $slownik_przedmiotow[$of['kod_przedmiotu']] : $of['kod_przedmiotu'];
                    $calkowita_cena = $of['ilosc'] * $of['cena_za_sztuke'];
                    
                    echo "<tr>";
                    echo "<td><b style='color:#fff;'>$nazwa_wyswietlana</b></td>";
                    echo "<td>{$of['login']}</td>";
                    echo "<td><b>{$of['ilosc']} szt.</b></td>";
                    echo "<td><span class='cena'>{$of['cena_za_sztuke']} $</span></td>";
                    echo "<td><span class='cena'>$calkowita_cena $</span></td>";
                    echo "<td>";
                    if ($of['id_sprzedawcy'] != $id_gracza) {
                        echo "<form method='POST' style='margin:0;'><input type='hidden' name='id_oferty' value='{$of['id']}'><button type='submit' name='kup_oferte' class='btn-kup'>KUP</button></form>";
                    } else {
                        echo "<span style='color:#888; font-size: 0.8em;'>Twoja oferta</span>";
                    }
                    echo "</td></tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align: center; padding: 30px;'>Brak ofert na rynku w tej kategorii.</td></tr>";
            }
            ?>
        </table>
    </div>

<?php elseif ($zakladka == 'moje_oferty'): ?>
    <div class="rynek-panel">
        <div class="rynek-header"><h2>Twoje Aktywne Oferty</h2><a href="game.php?page=rynek&zakladka=wystaw" class="btn-wystaw">DODAJ NOWĄ</a></div>
        <table class="rynek-tabela">
            <tr><th>Przedmiot</th><th>Ilość</th><th>Cena za szt.</th><th>Wystawiono</th><th>Akcja</th></tr>
            <?php
            $sql_moje = "SELECT * FROM rynek_oferty WHERE id_sprzedawcy = $id_gracza ORDER BY data_wystawienia DESC";
            $wynik_moje = $polaczenie->query($sql_moje);
            if ($wynik_moje->num_rows > 0) {
                while($of = $wynik_moje->fetch_assoc()) {
                    $nazwa_wyswietlana = isset($slownik_przedmiotow[$of['kod_przedmiotu']]) ? $slownik_przedmiotow[$of['kod_przedmiotu']] : $of['kod_przedmiotu'];
                    echo "<tr><td><b style='color:#fff;'>$nazwa_wyswietlana</b></td><td>{$of['ilosc']} szt.</td><td><span class='cena'>{$of['cena_za_sztuke']} $</span></td><td>{$of['data_wystawienia']}</td>";
                    echo "<td><form method='POST' style='margin:0;'><input type='hidden' name='id_oferty' value='{$of['id']}'><button type='submit' name='anuluj_oferte' class='btn-anuluj'>Wycofaj</button></form></td></tr>";
                }
            } else { echo "<tr><td colspan='5' style='text-align: center; padding: 30px;'>Nie masz obecnie żadnych ofert na rynku.</td></tr>"; }
            ?>
        </table>
    </div>

<?php elseif ($zakladka == 'wystaw'): ?>
    <div class="rynek-panel">
        <h2>Wystaw nowy towar na sprzedaż</h2>
        <form method="POST" style="margin-top: 20px;">
            <label style="color: #888; font-weight: bold; display: block; margin-bottom: 5px;">Co chcesz sprzedać?</label>
            <select name="przedmiot" class="input-select" required>
                <?php 
                $cos_posiada = false;
                foreach($slownik_przedmiotow as $kod => $nazwa) {
                    if (isset($gracz[$kod]) && $gracz[$kod] > 0) {
                        echo "<option value='$kod'>$nazwa (Masz: {$gracz[$kod]})</option>";
                        $cos_posiada = true;
                    }
                }
                if (!$cos_posiada) {
                    echo "<option value=''>Twój ekwipunek jest pusty...</option>";
                }
                ?>
            </select>
            
            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <label style="color: #888; font-weight: bold; display: block; margin-bottom: 5px;">Ilość do wystawienia:</label>
                    <input type="number" name="ilosc" class="input-text" min="1" value="1" required <?php if(!$cos_posiada) echo 'disabled'; ?>>
                </div>
                <div style="flex: 1;">
                    <label style="color: #888; font-weight: bold; display: block; margin-bottom: 5px;">Cena za 1 sztukę ($):</label>
                    <input type="number" name="cena" class="input-text" min="1" value="100" required <?php if(!$cos_posiada) echo 'disabled'; ?>>
                </div>
            </div>
            <button type="submit" name="wystaw_oferte" class="btn-wystaw" style="width: 100%; padding: 15px; font-size: 1.2em; margin-top: 10px;" <?php if(!$cos_posiada) echo 'disabled'; ?>>ZATWIERDŹ OFERTĘ</button>
        </form>
    </div>
<?php endif; ?>