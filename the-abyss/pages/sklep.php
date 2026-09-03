<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";

// 1. POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT poziom, gotowka, bron_zalozona, bonus_atak, pancerz_zalozony, bonus_obrona FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// 2. ASORTYMENT SKLEPU (DODANO PARAMETR 'db' CZYLI NAZWĘ KOLUMNY W BAZIE)
$sklep_bronie = [
    1 => ["nazwa" => "Zardzewiały Widelec", "atak" => 1, "poziom" => 1, "koszt" => 50, "ikona" => "🍴", "opis" => "Lepszy niż pięści, ryzyko tężca gratis.", "db" => "eq_widelec"],
    2 => ["nazwa" => "Tępy Nóż Kuchenny", "atak" => 2, "poziom" => 2, "koszt" => 150, "ikona" => "🔪", "opis" => "Pewnie służył do smarowania masła. Tutaj też sobie poradzi.", "db" => "eq_noz"],
    3 => ["nazwa" => "Kij Baseballowy", "atak" => 4, "poziom" => 5, "koszt" => 400, "ikona" => "🏏", "opis" => "Klasyk ulicznych porachunków.", "db" => "eq_kij"],
    4 => ["nazwa" => "Łańcuch Rowerowy", "atak" => 6, "poziom" => 8, "koszt" => 800, "ikona" => "⛓️", "opis" => "Zostawia paskudne pręgi na ciałach wrogów.", "db" => "eq_lancuch"],
    5 => ["nazwa" => "Stary Kastet", "atak" => 8, "poziom" => 12, "koszt" => 1500, "ikona" => "👊", "opis" => "Zardzewiały kawałek mosiądzu. Ulubiona zabawka rzezimieszków.", "db" => "eq_kastet"]
];

$sklep_pancerze = [
    1 => ["nazwa" => "Gruba Bluza z Kapturem", "obrona" => 1, "poziom" => 1, "koszt" => 50, "ikona" => "🧥", "opis" => "Ochroni przed zimnem i lekkim zadrapaniem.", "db" => "eq_bluza"],
    2 => ["nazwa" => "Skórzana Kurtka", "obrona" => 3, "poziom" => 3, "koszt" => 250, "ikona" => "🏍️", "opis" => "Twarda skóra zatrzyma słabsze cięcia nożem.", "db" => "eq_kurtka"],
    3 => ["nazwa" => "Pancerz z Opon", "obrona" => 5, "poziom" => 6, "koszt" => 600, "ikona" => "🛞", "opis" => "Guma doskonale amortyzuje uderzenia kijem.", "db" => "eq_opony"],
    4 => ["nazwa" => "Sprana Kamizelka Kuloodporna", "obrona" => 8, "poziom" => 10, "koszt" => 1400, "ikona" => "🦺", "opis" => "Ktoś już w niej zginął, ale płytka ceramiczna wciąż tam jest.", "db" => "eq_kamizelka"]
];

// 3. LOGIKA KUPOWANIA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kup_bron'])) {
    $id_broni = (int)$_POST['id_broni'];
    if (isset($sklep_bronie[$id_broni])) {
        $b = $sklep_bronie[$id_broni];
        if ($gracz['poziom'] < $b['poziom']) { $komunikat = "<div class='blad'>Za niski poziom. Wymagany: {$b['poziom']}</div>"; }
        elseif ($gracz['gotowka'] < $b['koszt']) { $komunikat = "<div class='blad'>Brak kasy! Potrzebujesz {$b['koszt']} $</div>"; }
        else {
            $kolumna = $b['db'];
            $nowa_nazwa = $polaczenie->real_escape_string($b['nazwa']);
            // Dodajemy przedmiot do plecaka i od razu go zakładamy
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - {$b['koszt']}, bonus_atak = {$b['atak']}, bron_zalozona = '$nowa_nazwa', bron_ulepszona = 0, $kolumna = $kolumna + 1 WHERE id = $id_gracza");
            $komunikat = "<div class='sukces'>Kupujesz i wyposażasz: <b>{$b['nazwa']}</b>. Przedmiot dodano do ekwipunku!</div>";
            $gracz['gotowka'] -= $b['koszt']; $gracz['bonus_atak'] = $b['atak']; $gracz['bron_zalozona'] = $b['nazwa'];
            $cena_zakupu = round($cena_zakupu * pochodzenie_bonus($gracz_r, 'sklep_npc_koszt_mult', 1.0));
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kup_pancerz'])) {
    $id_panc = (int)$_POST['id_pancerza'];
    if (isset($sklep_pancerze[$id_panc])) {
        $p = $sklep_pancerze[$id_panc];
        if ($gracz['poziom'] < $p['poziom']) { $komunikat = "<div class='blad'>Za niski poziom. Wymagany: {$p['poziom']}</div>"; }
        elseif ($gracz['gotowka'] < $p['koszt']) { $komunikat = "<div class='blad'>Brak kasy! Potrzebujesz {$p['koszt']} $</div>"; }
        else {
            $kolumna = $p['db'];
            $nowa_nazwa = $polaczenie->real_escape_string($p['nazwa']);
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - {$p['koszt']}, bonus_obrona = {$p['obrona']}, pancerz_zalozony = '$nowa_nazwa', pancerz_ulepszony = 0, $kolumna = $kolumna + 1 WHERE id = $id_gracza");
            $komunikat = "<div class='sukces'>Kupujesz i zakładasz: <b>{$p['nazwa']}</b>. Sprzęt dodano do plecaka!</div>";
            $gracz['gotowka'] -= $p['koszt']; $gracz['bonus_obrona'] = $p['obrona']; $gracz['pancerz_zalozony'] = $p['nazwa'];
        }
    }
}
?>

<style>
    .sklep-header { background: linear-gradient(to bottom, rgba(0,0,0,0.5), #0a0a0a), url('https://via.placeholder.com/900x200/332211/111111?text=Lombard') center/cover; padding: 40px; border: 1px solid #444; border-radius: 5px; margin-bottom: 20px; text-align: center; }
    .sklep-header h1 { font-family: 'Oswald'; color: #ffaa00; font-size: 3em; margin: 0; text-transform: uppercase; text-shadow: 0 0 10px #000; }
    .panel-postaci { background: #111; border: 1px solid #333; padding: 20px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .panel-postaci div { font-family: 'Oswald'; font-size: 1.1em; color: #aaa; }
    .panel-postaci b { color: #fff; }
    .kategorie-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .kategoria-tytul { font-family: 'Oswald'; color: #fff; text-transform: uppercase; border-bottom: 2px solid #444; padding-bottom: 10px; margin-bottom: 15px; }
    .przedmiot-karta { background: #050505; border: 1px solid #222; padding: 15px; border-radius: 4px; display: flex; flex-direction: column; margin-bottom: 10px; transition: 0.3s; }
    .przedmiot-karta:hover { border-color: #555; background: #0a0a0a; }
    .przedmiot-info h3 { margin: 0 0 5px 0; color: #fff; font-family: 'Oswald'; font-size: 1.2em; }
    .przedmiot-info p { margin: 0 0 10px 0; color: #888; font-size: 0.85em; font-style: italic; min-height: 40px; }
    .staty-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; margin-right: 5px; }
    .badge-atak { background: #1a0a0a; border: 1px solid #ff3333; color: #ff3333; }
    .badge-obrona { background: #0a1a2a; border: 1px solid #00aaff; color: #00aaff; }
    .lvl-badge { display: inline-block; background: #0a1a0a; border: 1px solid #00ff00; color: #00ff00; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; }
    .przedmiot-akcja { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; border-top: 1px dashed #333; padding-top: 10px; }
    .cena { font-family: 'Oswald'; font-size: 1.3em; color: #ffaa00; }
    .btn-kup { background: transparent; color: #ffaa00; border: 1px solid #ffaa00; padding: 8px 15px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; }
    .btn-kup:hover { background: #ffaa00; color: #000; }
    .btn-disabled { background: #222; color: #555; border-color: #333; cursor: not-allowed; }
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; }
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; }
</style>

<div class="sklep-header">
    <h1>Lombard "Rdza i Krew"</h1>
</div>
<?php echo $komunikat; ?>

<div class="panel-postaci">
    <div style="flex: 1;">Kasa: <b style="color: #00ff00;"><?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $</b><br>Lvl: <b style="color: #00ccff;"><?php echo $gracz['poziom']; ?></b></div>
    <div style="flex: 2; text-align: right; border-left: 1px solid #333; padding-left: 15px;">
        Broń: <b style="color: #ff3333;"><?php echo $gracz['bron_zalozona']; ?></b> <span style="font-size: 0.8em;">(+<?php echo $gracz['bonus_atak']; ?> Atk)</span><br>
        Strój: <b style="color: #00aaff;"><?php echo $gracz['pancerz_zalozony']; ?></b> <span style="font-size: 0.8em;">(+<?php echo $gracz['bonus_obrona']; ?> Obr)</span>
    </div>
</div>

<div class="kategorie-grid">
    <div>
        <h2 class="kategoria-tytul">🔪 Broń Podręczna</h2>
        <?php foreach($sklep_bronie as $id => $bron): 
            $czy_stac = ($gracz['gotowka'] >= $bron['koszt']); $czy_lvl = ($gracz['poziom'] >= $bron['poziom']); $czy_zalozone = ($gracz['bron_zalozona'] == $bron['nazwa']);
        ?>
            <div class="przedmiot-karta" <?php if($czy_zalozone) echo "style='border-color: #ffaa00;'"; ?>>
                <div class="przedmiot-info">
                    <h3><?php echo $bron['ikona']." ".$bron['nazwa']; ?> <?php if($czy_zalozone) echo "<span style='font-size:0.6em; color:#ffaa00;'>(W DŁONI)</span>"; ?></h3>
                    <p><?php echo $bron['opis']; ?></p><span class="staty-badge badge-atak">Atak +<?php echo $bron['atak']; ?></span><span class="lvl-badge">Lvl <?php echo $bron['poziom']; ?></span>
                </div>
                <div class="przedmiot-akcja"><span class="cena"><?php echo $bron['koszt']; ?> $</span>
                    <?php if($czy_zalozone): ?> <button type="button" class="btn-kup btn-disabled" disabled>MASZ</button>
                    <?php elseif(!$czy_lvl): ?> <button type="button" class="btn-kup btn-disabled" disabled>ZBYT SŁABY</button>
                    <?php elseif(!$czy_stac): ?> <button type="button" class="btn-kup btn-disabled" disabled>BRAK $</button>
                    <?php else: ?> <form method="POST" style="margin: 0;"><input type="hidden" name="id_broni" value="<?php echo $id; ?>"><button type="submit" name="kup_bron" class="btn-kup">KUP</button></form><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div>
        <h2 class="kategoria-tytul">🛡️ Ochrona Ciała</h2>
        <?php foreach($sklep_pancerze as $id => $panc): 
            $czy_stac = ($gracz['gotowka'] >= $panc['koszt']); $czy_lvl = ($gracz['poziom'] >= $panc['poziom']); $czy_zalozone = ($gracz['pancerz_zalozony'] == $panc['nazwa']);
        ?>
            <div class="przedmiot-karta" <?php if($czy_zalozone) echo "style='border-color: #ffaa00;'"; ?>>
                <div class="przedmiot-info">
                    <h3><?php echo $panc['ikona']." ".$panc['nazwa']; ?> <?php if($czy_zalozone) echo "<span style='font-size:0.6em; color:#ffaa00;'>(NA SOBIE)</span>"; ?></h3>
                    <p><?php echo $panc['opis']; ?></p><span class="staty-badge badge-obrona">Obrona +<?php echo $panc['obrona']; ?></span><span class="lvl-badge">Lvl <?php echo $panc['poziom']; ?></span>
                </div>
                <div class="przedmiot-akcja"><span class="cena"><?php echo $panc['koszt']; ?> $</span>
                    <?php if($czy_zalozone): ?> <button type="button" class="btn-kup btn-disabled" disabled>MASZ</button>
                    <?php elseif(!$czy_lvl): ?> <button type="button" class="btn-kup btn-disabled" disabled>ZBYT SŁABY</button>
                    <?php elseif(!$czy_stac): ?> <button type="button" class="btn-kup btn-disabled" disabled>BRAK $</button>
                    <?php else: ?> <form method="POST" style="margin: 0;"><input type="hidden" name="id_pancerza" value="<?php echo $id; ?>"><button type="submit" name="kup_pancerz" class="btn-kup">KUP</button></form><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>