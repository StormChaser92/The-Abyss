<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// POBIERANIE DANYCH
$wynik = $polaczenie->query("SELECT gotowka, bank, lokata_kwota, lokata_godziny, lokata_start FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// ==========================================
// LOGIKA ZWYKŁEGO KONTA
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Wpłata do banku
    if (isset($_POST['wplac_bank'])) {
        $kwota = (int)$_POST['kwota_przelewu'];
        if ($kwota > 0 && $gracz['gotowka'] >= $kwota) {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $kwota, bank = bank + $kwota WHERE id = $id_gracza");
            $gracz['gotowka'] -= $kwota; $gracz['bank'] += $kwota;
            $komunikat = "<div class='sukces'>Zdeponowano $kwota $ na bezpiecznym koncie.</div>";
        } else { $komunikat = "<div class='blad'>Nieprawidłowa kwota lub brak gotówki!</div>"; }
    }

    // Wypłata z banku
    if (isset($_POST['wyplac_bank'])) {
        $kwota = (int)$_POST['kwota_przelewu'];
        if ($kwota > 0 && $gracz['bank'] >= $kwota) {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $kwota, bank = bank - $kwota WHERE id = $id_gracza");
            $gracz['gotowka'] += $kwota; $gracz['bank'] -= $kwota;
            $komunikat = "<div class='sukces'>Wypłacono $kwota $ w czystej gotówce.</div>";
        } else { $komunikat = "<div class='blad'>Nie masz tylu środków na koncie!</div>"; }
    }

    // ==========================================
    // LOGIKA LOKAT
    // ==========================================
    
    // Zakładanie lokaty
    if (isset($_POST['zaloz_lokate']) && $gracz['lokata_kwota'] == 0) {
        $kwota_lok = (int)$_POST['kwota_lokaty'];
        $czas_lok = (int)$_POST['czas_lokaty'];
        
        $dopuszczalne_czasy = [6, 12, 24, 48];
        if (!in_array($czas_lok, $dopuszczalne_czasy)) {
            $komunikat = "<div class='blad'>Nieznany wariant lokaty!</div>";
        } elseif ($kwota_lok < 1000) {
            $komunikat = "<div class='blad'>Minimalna kwota lokaty to 1 000 $.</div>";
        } elseif ($gracz['gotowka'] < $kwota_lok) {
            $komunikat = "<div class='blad'>Nie masz tyle gotówki przy sobie!</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $kwota_lok, lokata_kwota = $kwota_lok, lokata_godziny = $czas_lok, lokata_start = NOW() WHERE id = $id_gracza");
            $gracz['gotowka'] -= $kwota_lok; $gracz['lokata_kwota'] = $kwota_lok; $gracz['lokata_godziny'] = $czas_lok; $gracz['lokata_start'] = date('Y-m-d H:i:s');
            $komunikat = "<div class='sukces'>Lokata aktywowana. Pieniądze zaczęły na siebie pracować.</div>";
        }
    }

    // Zerwanie lub Odbiór lokaty
    if (isset($_POST['odbierz_lokate']) && $gracz['lokata_kwota'] > 0) {
        $czas_start = strtotime($gracz['lokata_start']);
        $czas_koniec = $czas_start + ($gracz['lokata_godziny'] * 3600);
        $teraz = time();
        
        $kapital = $gracz['lokata_kwota'];
        
        if ($teraz >= $czas_koniec) {
            // Sukces - odsetki
            $oprocentowanie = 5; // 5% za 24h
            $zysk = round($kapital * ($oprocentowanie / 100) * ($gracz['lokata_godziny'] / 24));
            $pula = $kapital + $zysk;
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $pula, lokata_kwota = 0, lokata_godziny = 0, lokata_start = NULL WHERE id = $id_gracza");
            $gracz['gotowka'] += $pula; $gracz['lokata_kwota'] = 0;
            $komunikat = "<div class='sukces'>Lokata zakończona sukcesem! Odbierasz swój kapitał i zysk w wysokości $zysk $.</div>";
        } else {
            // Zerwanie
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $kapital, lokata_kwota = 0, lokata_godziny = 0, lokata_start = NULL WHERE id = $id_gracza");
            $gracz['gotowka'] += $kapital; $gracz['lokata_kwota'] = 0;
            $komunikat = "<div class='blad'>Zerwałeś umowę przed czasem. Kapitał wraca do Ciebie, ale tracisz wszystkie odsetki!</div>";
        }
    }
}

// Obliczanie czasu dla aktywnej lokaty
$lokata_aktywna = ($gracz['lokata_kwota'] > 0);
$czas_koniec = 0;
$pozostalo = 0;
$zysk_przewidywany = 0;

if ($lokata_aktywna) {
    $czas_start = strtotime($gracz['lokata_start']);
    $czas_koniec = $czas_start + ($gracz['lokata_godziny'] * 3600);
    $teraz = time();
    $pozostalo = max(0, $czas_koniec - $teraz);
    
    $zysk_przewidywany = round($gracz['lokata_kwota'] * (5 / 100) * ($gracz['lokata_godziny'] / 24));
    $odsetki = round($odsetki * pochodzenie_bonus($gracz_r, 'bank_odsetki_mult', 1.0));
}
?>

<style>
    .bank-header { background: linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.2)), url('https://via.placeholder.com/900x200/0a1a0a/003300?text=Bank+Centralny') center/cover; padding: 40px; border: 1px solid #00ff00; border-radius: 4px; margin-bottom: 20px; text-align: left; }
    .bank-header h1 { font-family: 'Oswald'; color: #00ff00; font-size: 3em; margin: 0; text-transform: uppercase; text-shadow: 2px 2px 5px #000; }
    
    .panel-bank { background: #0a0a0a; border: 1px solid #222; border-radius: 4px; padding: 25px; margin-bottom: 20px; }
    .panel-bank h2 { font-family: 'Oswald'; color: #fff; text-transform: uppercase; border-bottom: 1px dashed #333; padding-bottom: 15px; margin-top: 0; }
    
    .stan-konta { display: flex; justify-content: space-around; background: #050505; border: 1px solid #333; padding: 20px; border-radius: 4px; margin-bottom: 25px; }
    .stan-box { text-align: center; }
    .stan-box span { color: #888; font-size: 0.9em; text-transform: uppercase; display: block; margin-bottom: 5px; }
    .stan-box b { font-family: 'Oswald'; font-size: 2em; color: #fff; }
    
    .input-text, .input-select { width: 100%; padding: 15px; background: #000; border: 1px solid #444; color: #00ff00; border-radius: 3px; font-size: 1.1em; margin-bottom: 15px; box-sizing: border-box; font-family: 'Oswald'; }
    .input-text:focus { outline: none; border-color: #00ff00; }
    
    .btn-akcja { background: #008800; color: #fff; border: 1px solid #00ff00; padding: 15px 20px; font-family: 'Oswald'; font-size: 1.1em; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; width: 100%; }
    .btn-akcja:hover { background: #00ff00; color: #000; }
    .btn-wyplata { background: transparent; color: #00ccff; border-color: #00ccff; }
    .btn-wyplata:hover { background: #00ccff; color: #000; }
    .btn-zerwij { background: transparent; color: #ff3333; border-color: #ff3333; }
    .btn-zerwij:hover { background: #ff3333; color: #000; }
    
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.1em; }
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.1em; }
    
    .lokata-info { background: #111; border-left: 4px solid #00ff00; padding: 20px; margin-bottom: 20px; }
</style>

<div class="bank-header">
    <h1>Bank The Abyss</h1>
    <p style="color: #ccc; font-size: 1.1em;">Jedyna instytucja w mieście, która nie ukradnie Twoich pieniędzy w ciemnym zaułku.</p>
</div>

<?php echo $komunikat; ?>

<div class="stan-konta">
    <div class="stan-box">
        <span>Gotówka w portfelu</span>
        <b style="color:#888;"><?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $</b>
    </div>
    <div class="stan-box">
        <span>Środki na koncie bankowym</span>
        <b style="color:#00ff00;"><?php echo number_format($gracz['bank'], 0, '', ' '); ?> $</b>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    
    <div class="panel-bank">
        <h2>Skrytka Depozytowa</h2>
        <p style="color: #aaa; margin-bottom: 20px;">Pieniądze wpłacone do sejfu są w pełni bezpieczne podczas ataku innych obywateli.</p>
        
        <form method="POST">
            <label style="color:#888; text-transform:uppercase; font-size:0.8em; font-weight:bold;">Kwota Operacji:</label>
            <input type="number" name="kwota_przelewu" class="input-text" min="1" placeholder="Wpisz kwotę..." required>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="wplac_bank" class="btn-akcja">⬇ WPŁAĆ NA KONTO</button>
                <button type="submit" name="wyplac_bank" class="btn-akcja btn-wyplata">⬆ WYPŁAĆ GOTÓWKĘ</button>
            </div>
        </form>
    </div>

    <div class="panel-bank" style="border-color: #ffd700;">
        <h2 style="color: #ffd700;">Inwestycje i Lokaty</h2>
        
        <?php if (!$lokata_aktywna): ?>
            <p style="color: #aaa; margin-bottom: 20px;">Zamroź swoją gotówkę, aby generować zysk. Bazowe oprocentowanie wynosi 5% w skali 24h.</p>
            
            <form method="POST">
                <label style="color:#888; text-transform:uppercase; font-size:0.8em; font-weight:bold;">Kwota Lokaty (z portfela):</label>
                <input type="number" name="kwota_lokaty" class="input-text" min="1000" max="<?php echo $gracz['gotowka']; ?>" placeholder="Min. 1000 $" required>
                
                <label style="color:#888; text-transform:uppercase; font-size:0.8em; font-weight:bold;">Okres zamrożenia:</label>
                <select name="czas_lokaty" class="input-select">
                    <option value="6">Szybka Lokata (6 Godzin) - Zysk 1.25%</option>
                    <option value="12">Standardowa Lokata (12 Godzin) - Zysk 2.50%</option>
                    <option value="24" selected>Dobowa Lokata (24 Godziny) - Zysk 5.00%</option>
                    <option value="48">Długoterminowa Lokata (48 Godzin) - Zysk 10.00%</option>
                </select>
                
                <button type="submit" name="zaloz_lokate" class="btn-akcja" style="background: #aa8800; border-color: #ffd700;">PODPISZ WEKSEL</button>
            </form>
            
        <?php else: ?>
            <div class="lokata-info">
                <div style="color: #888; text-transform: uppercase; font-size: 0.9em;">Aktywna Lokata:</div>
                <div style="font-family: 'Oswald'; font-size: 2em; color: #fff; margin-bottom: 10px;">
                    <?php echo number_format($gracz['lokata_kwota'], 0, '', ' '); ?> $
                </div>
                
                <div style="display: flex; justify-content: space-between; color: #aaa; margin-bottom: 15px;">
                    <span>Zysk po zakończeniu: <b style="color: #00ff00;">+<?php echo number_format($zysk_przewidywany, 0, '', ' '); ?> $</b></span>
                    <span>Czas: <b><?php echo $gracz['lokata_godziny']; ?>H</b></span>
                </div>
                
                <?php if ($pozostalo > 0): ?>
                    <div style="background: #000; padding: 10px; text-align: center; color: #ffaa00; font-family: monospace; font-size: 1.2em; border-radius: 3px; border: 1px solid #333; margin-bottom: 20px;">
                        Czas do końca: <?php echo gmdate("H:i:s", $pozostalo); ?>
                    </div>
                    <form method="POST">
                        <button type="submit" name="odbierz_lokate" class="btn-akcja btn-zerwij" onclick="return confirm('Chcesz zerwać lokatę? Odzyskasz pieniądze, ale STRACISZ wszystkie wypracowane odsetki!');">ZERWIJ LOKATĘ (0% ZYSKU)</button>
                    </form>
                <?php else: ?>
                    <div style="background: rgba(0,255,0,0.1); padding: 10px; text-align: center; color: #00ff00; font-family: 'Oswald'; font-size: 1.2em; border-radius: 3px; border: 1px solid #00ff00; margin-bottom: 20px;">
                        ŚRODKI GOTOWE DO WYPŁATY!
                    </div>
                    <form method="POST">
                        <button type="submit" name="odbierz_lokate" class="btn-akcja" style="background: #00aa00; border-color: #00ff00;">💰 ODBIERZ KAPITAŁ I ZYSK</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>