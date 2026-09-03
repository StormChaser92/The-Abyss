<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";

// 1. POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();
$posiadane_pokoje = !empty($gracz['pokoje_specjalne']) ? json_decode($gracz['pokoje_specjalne'], true) : [];

if ($gracz['klasa'] !== 'Inżynier' || !in_array("Warsztat Inżynieryjny", $posiadane_pokoje)) {
    echo "<div style='padding: 50px; text-align: center; color: #ff3333; font-family: Oswald; font-size: 2em;'>Brak uprawnień lub brak Warsztatu!</div>";
    exit;
}

// 2. POBIERANIE ŁUPÓW GRACZA (Do elitarnych broni)
$lupy_q = $polaczenie->query("SELECT nazwa, ilosc FROM przedmioty_gracze WHERE gracz_id = $id_gracza");
$posiadane_lupy = [];
while($r = $lupy_q->fetch_assoc()) { $posiadane_lupy[$r['nazwa']] = $r['ilosc']; }

// ---------------------------------------------------------
// 3. PEŁEN KATALOG SCHEMATÓW INŻYNIERA
// Podział na 3 poziomy trudności i technologii
// ---------------------------------------------------------
$katalog_schematow = [
    "🛠️ TIER I: Sprzęt Podstawowy (Złom i Części)" => [
        "noz_kabar" => ["nazwa" => "Nóż bojowy KA-BAR", "stal" => 3, "czesci" => 0, "syn" => 1, "elek" => 0, "loot" => null, "en" => 3, "trudnosc" => 5],
        "pistolet_samorobka" => ["nazwa" => "Pistolet Samoróbka 9mm", "stal" => 5, "czesci" => 2, "syn" => 0, "elek" => 0, "loot" => null, "en" => 5, "trudnosc" => 10],
        "glock_17" => ["nazwa" => "Glock 17 (9mm)", "stal" => 8, "czesci" => 4, "syn" => 1, "elek" => 0, "loot" => null, "en" => 8, "trudnosc" => 15],
        "pm_uzi" => ["nazwa" => "Klasyczne Uzi", "stal" => 12, "czesci" => 8, "syn" => 2, "elek" => 1, "loot" => null, "en" => 10, "trudnosc" => 20],
        "strzelba_mossberg" => ["nazwa" => "Mossberg 500", "stal" => 14, "czesci" => 5, "syn" => 3, "elek" => 0, "loot" => null, "en" => 12, "trudnosc" => 25],
        "karabin_szturmowy" => ["nazwa" => "Stary Karabin Szturmowy", "stal" => 18, "czesci" => 8, "syn" => 2, "elek" => 2, "loot" => null, "en" => 15, "trudnosc" => 30],
        "karabin_ak47" => ["nazwa" => "AK-47 (Wschód)", "stal" => 20, "czesci" => 6, "syn" => 3, "elek" => 0, "loot" => null, "en" => 15, "trudnosc" => 35]
    ],
    
    "🧪 TIER II: Sprzęt Zaawansowany (Modyfikacje i Pancerze)" => [
        "maczeta_kukri" => ["nazwa" => "Zatruta Maczeta Kukri (Tox)", "stal" => 8, "czesci" => 2, "syn" => 4, "elek" => 0, "loot" => "Kwas Żołądkowy Mutanta", "en" => 15, "trudnosc" => 40],
        "desert_eagle" => ["nazwa" => "Desert Eagle .50 (AP)", "stal" => 15, "czesci" => 8, "syn" => 2, "elek" => 4, "loot" => "Łuski po nabojach", "en" => 18, "trudnosc" => 45],
        "pm_p90" => ["nazwa" => "P90 z Amunicją Jadową (Tox)", "stal" => 15, "czesci" => 12, "syn" => 6, "elek" => 6, "loot" => "Brudna Strzykawka", "en" => 20, "trudnosc" => 50],
        "karabin_m4a1" => ["nazwa" => "M4A1 (Amunicja AP)", "stal" => 22, "czesci" => 15, "syn" => 5, "elek" => 5, "loot" => "Przemycane Części", "en" => 25, "trudnosc" => 55],
        "pancerz_taktyczny" => ["nazwa" => "Pancerz Taktyczny SWAT", "stal" => 15, "czesci" => 5, "syn" => 20, "elek" => 2, "loot" => "Kewlarowy Pancerz", "en" => 20, "trudnosc" => 60]
    ],
    
    "☢️ TIER III: Prototypy Śmierci (Wymagają Unikalnych Artefaktów)" => [
        "lmg_m249" => ["nazwa" => "M249 SAW (AP)", "stal" => 35, "czesci" => 20, "syn" => 5, "elek" => 8, "loot" => "Tytanowa Płyta", "en" => 35, "trudnosc" => 70],
        "snajperka_awp" => ["nazwa" => "Karabin AWP (EMP)", "stal" => 25, "czesci" => 15, "syn" => 8, "elek" => 15, "loot" => "Spalony Mikroczip", "en" => 40, "trudnosc" => 80],
        "wyrzutnia_rpg7" => ["nazwa" => "Wyrzutnia RPG-7 (EMP)", "stal" => 50, "czesci" => 15, "syn" => 5, "elek" => 25, "loot" => "Bateria Termojądrowa", "en" => 50, "trudnosc" => 90],
        "pancerz_taktyczny_upg" => ["nazwa" => "Egzoszkielet Taktyczny", "stal" => 40, "czesci" => 25, "syn" => 30, "elek" => 35, "loot" => "Rdzeń Plazmowy Alpha", "en" => 60, "trudnosc" => 110]
    ]
];

// 4. LOGIKA WYTWARZANIA (RNG, Skille, Utrata Surowców)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['wytworz'])) {
    $kod = $_POST['schemat_kod'];
    $s = null;
    foreach($katalog_schematow as $kat => $bronie) { if(isset($bronie[$kod])) { $s = $bronie[$kod]; break; } }
    
    if ($s) {
        $ma_loot = ($s['loot'] == null || (isset($posiadane_lupy[$s['loot']]) && $posiadane_lupy[$s['loot']] >= 1));
        
        if ($gracz['energia_aktualna'] < $s['en']) {
            $komunikat = "<div class='blad'>Jesteś zbyt zmęczony by utrzymać spawarkę! Potrzebujesz {$s['en']} EN.</div>";
        } elseif ($gracz['zlom_stalowy'] < $s['stal'] || $gracz['czesci_mechaniczne'] < $s['czesci'] || $gracz['syntetyki'] < $s['syn'] || $gracz['elektronika'] < $s['elek'] || !$ma_loot) {
            $komunikat = "<div class='blad'>Brakuje materiałów lub wymaganego artefaktu: <b>".($s['loot'] ?? 'Brak')."</b>! Udaj się do Doków lub kup u Szabrownika.</div>";
        } else {
            
            // --- MATEMATYKA SUKCESU (Z VALLHERU) ---
            // Baza to 50%. Każdy punkt skilla inżynierii daje +2%. Inteligencja też lekko pomaga.
            // Od tego odejmujemy trudność schematu.
            $szansa = (50 + ($gracz['umiejetnosc_inzynierii'] * 2.5) + ($gracz['inteligencja'] / 2)) - ($s['trudnosc'] / 1.5);
            $szansa = max(10, min(95, $szansa)); // Nigdy mniej niż 10%, nigdy więcej niż 95%
            
            $los = rand(1, 100);
            
            if ($los <= $szansa) {
                // *** SUKCES ***
                // Przyrost skilla: Im trudniejszy przedmiot, tym więcej się uczysz (od 0.10 do 0.80)
                $przyrost_skilla = round(($s['trudnosc'] / 15) * (rand(80, 120)/100), 2);
                $exp = $s['trudnosc'] * 5;
                
                $polaczenie->query("UPDATE gracze SET 
                    zlom_stalowy = zlom_stalowy - {$s['stal']}, czesci_mechaniczne = czesci_mechaniczne - {$s['czesci']}, 
                    syntetyki = syntetyki - {$s['syn']}, elektronika = elektronika - {$s['elek']},
                    energia_aktualna = energia_aktualna - {$s['en']}, exp = exp + $exp, 
                    umiejetnosc_inzynierii = umiejetnosc_inzynierii + $przyrost_skilla, $kod = $kod + 1 
                    WHERE id = $id_gracza");
                
                // ══ HOOK: POSTĘP KONTRAKTÓW KLASOWYCH — WARSZTAT ══
                $typ_celu_wytw = "wytworz_" . $kod;
                $polaczenie->query("UPDATE kontrakty_klasowe SET postep = LEAST(cel_ilosc, postep+1)
                    WHERE gracz_id=$id_gracza AND status='aktywny' AND typ_celu='$typ_celu_wytw' AND deadline > NOW()");
                
                if ($s['loot']) { 
                    $polaczenie->query("UPDATE przedmioty_gracze SET ilosc = ilosc - 1 WHERE gracz_id = $id_gracza AND nazwa = '{$s['loot']}'"); 
                }
                
                $nowy_skill = $gracz['umiejetnosc_inzynierii'] + $przyrost_skilla;
                $komunikat = "<div class='sukces'>PRODUKCJA UDANA! Wytworzono: <b style='color:#fff;'>{$s['nazwa']}</b>. <br><span style='color:#ccc; font-size:0.9em;'>(+$exp EXP, +$przyrost_skilla Inżynierii. Twój poziom: $nowy_skill)</span></div>";
            } else {
                // *** PORAŻKA ***
                // W Vallheru tracisz surkę i zyskujesz tylko 0.01 do 0.05 skilla.
                $przyrost_fail = rand(1, 5) / 100;
                
                // Traci połowę włożonych surowców bazowych, ale NIE traci artefaktu (byłoby to zbyt bolesne)
                $strata_stali = floor($s['stal']/2);
                $strata_czesci = floor($s['czesci']/2);
                
                $polaczenie->query("UPDATE gracze SET 
                    zlom_stalowy = zlom_stalowy - $strata_stali, czesci_mechaniczne = czesci_mechaniczne - $strata_czesci, 
                    energia_aktualna = energia_aktualna - floor({$s['en']}/2),
                    umiejetnosc_inzynierii = umiejetnosc_inzynierii + $przyrost_fail
                    WHERE id = $id_gracza");
                
                $komunikat = "<div class='blad'>PRODUKCJA NIEUDANA! Przegrzałeś obwody i zepsułeś kalibrację! (Los: $los%, Szansa: $szansa%)<br>Straciłeś część złomu, części i połowę energii włożonej w pracę. <br><span style='color:#aaa; font-size:0.9em;'>(Nauczyłeś się na błędzie: +$przyrost_fail Inżynierii)</span></div>";
            }
            
            // Odświeżenie danych na żywo, żeby gracz nie widział starych surowców po kliknięciu
            $wynik = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza");
            $gracz = $wynik->fetch_assoc();
            $lupy_q = $polaczenie->query("SELECT nazwa, ilosc FROM przedmioty_gracze WHERE gracz_id = $id_gracza");
            $posiadane_lupy = [];
            while($r = $lupy_q->fetch_assoc()) { $posiadane_lupy[$r['nazwa']] = $r['ilosc']; }
             // Koszt energii
$koszt_en = round($koszt_en * pochodzenie_bonus($gracz_r, 'craft_energia_mult', 1.0));

// Szansa na porażkę
$szansa_porazki = round($szansa_porazki * pochodzenie_bonus($gracz_r, 'inzynier_craft_fail_mult', 1.0));

// Bonus jakości (Francuz)
$jakosc_bonus = pochodzenie_bonus($gracz_r, 'craft_jakosc_bonus_abs', 0);

// Szansa na 2 produkty za raz (Niemiec)
if (rand(1,100) <= pochodzenie_bonus($gracz_r, 'craft_bonus_produkt_szansa', 0)) {
    $ilosc_produktow = 2;
}
        }
    }
}
?>

<style>
    /* ========================================================
       GLASSMORPHISM W WARSZTACIE INŻYNIERA
       ======================================================== */
    .warsztat-header { background: linear-gradient(to right, rgba(0,30,0,0.8), rgba(0,0,0,0.9)), url('https://via.placeholder.com/900x250/001100/000000?text=Manufaktura+Inzyniera') center/cover; padding: 40px; border: 1px solid rgba(0, 255, 0, 0.4); border-radius: 8px; margin-bottom: 25px; box-shadow: 0 0 25px rgba(0, 255, 0, 0.15); backdrop-filter: blur(5px);}
    .warsztat-header h1 { font-family: 'Oswald', sans-serif; color: #00ff00; font-size: 3.2em; margin: 0; text-transform: uppercase; text-shadow: 0 0 15px rgba(0,255,0,0.6); letter-spacing: 1px;}
    
    .panel-zasobow { background: rgba(10,10,10,0.6); border: 1px solid rgba(255,255,255,0.05); padding: 25px; border-radius: 8px; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; margin-bottom: 30px; backdrop-filter: blur(10px); box-shadow: inset 0 0 15px rgba(0,0,0,0.5);}
    .zasob { text-align: center; padding: 15px; border-radius: 6px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); }
    .zasob span { display: block; color: #888; font-size: 0.85em; text-transform: uppercase; font-family: 'Oswald', sans-serif; letter-spacing: 1px; margin-bottom: 5px;}
    .zasob b { color: #fff; font-size: 1.5em; font-family: 'Open Sans', sans-serif; font-weight: 700;}

    .kategoria-box { margin-bottom: 40px; background: rgba(5,5,5,0.5); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.02);}
    .kategoria-tytul { color: #00ccff; font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: 1.5em; border-bottom: 1px dashed rgba(0,204,255,0.3); padding-bottom: 10px; margin-bottom: 25px; letter-spacing: 1px; text-shadow: 0 0 10px rgba(0,204,255,0.3);}
    
    .schematy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
    
    .schemat-karta { background: rgba(15,15,15,0.7); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; display: flex; flex-direction: column; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.3);}
    .schemat-karta:hover { border-color: rgba(0,255,0,0.4); transform: translateY(-5px); background: rgba(20,30,20,0.8); box-shadow: 0 10px 25px rgba(0,0,0,0.5), inset 0 0 15px rgba(0,255,0,0.05); }
    
    .schemat-karta h3 { margin: 0 0 15px 0; color: #fff; font-family: 'Oswald', sans-serif; font-size: 1.3em; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;}
    .posiada-badge { font-size: 0.7em; background: rgba(0,0,0,0.8); padding: 3px 8px; border-radius: 4px; color: #888; border: 1px solid rgba(255,255,255,0.1); font-family: 'Open Sans', sans-serif;}
    
    .wymagania { font-size: 0.9em; color: #aaa; flex-grow: 1; margin-bottom: 20px; line-height: 1.6; font-family: 'Open Sans', sans-serif;}
    .req-item { display: inline-block; background: rgba(0,0,0,0.6); padding: 2px 6px; border-radius: 3px; margin-right: 5px; margin-bottom: 5px; border: 1px solid rgba(255,255,255,0.05); }
    .req-item b { color: #fff; }
    
    .loot-req { color: #dd88ff; font-weight: bold; border: 1px solid rgba(221,136,255,0.3); background: rgba(221,136,255,0.05); padding: 6px 10px; border-radius: 4px; display: block; margin-top: 10px; text-align: center; text-transform: uppercase; font-family: 'Oswald', sans-serif;}
    
    .szansa-bar-bg { background: rgba(0,0,0,0.8); height: 8px; border-radius: 4px; margin-top: 15px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);}
    .szansa-bar-fill { height: 100%; background: linear-gradient(90deg, #ff3333, #ffaa00, #00ff00); transition: width 1s; }

    .btn-wytworz { background: rgba(0,255,0,0.1); color: #00ff00; border: 1px solid rgba(0,255,0,0.5); padding: 12px; font-family: 'Oswald', sans-serif; cursor: pointer; text-transform: uppercase; width: 100%; border-radius: 4px; transition: 0.3s; font-weight: bold; letter-spacing: 1px; font-size: 1.05em;}
    .btn-wytworz:hover:not(:disabled) { background: #00ff00; color: #000; box-shadow: 0 0 20px rgba(0,255,0,0.6); }
    .btn-disabled { background: rgba(10,10,10,0.8); border-color: rgba(255,255,255,0.1); color: #555; cursor: not-allowed; }

    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid rgba(0,255,0,0.3); color: #00ff00; padding: 20px; margin-bottom: 25px; text-align: center; border-radius: 6px; font-size: 1.1em;}
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid rgba(255,51,51,0.3); color: #ff3333; padding: 20px; margin-bottom: 25px; text-align: center; border-radius: 6px; font-size: 1.1em;}
</style>

<div class="warsztat-header">
    <h1>Manufaktura Śmierci</h1>
    <p style="color: #ccc; font-size: 1.1em; font-family: 'Open Sans', sans-serif;">Od kawałka pordzewiałej rurki do śmiercionośnej wyrzutni EMP. Twórz, ulepszaj i ryzykuj.</p>
</div>

<?php echo $komunikat; ?>

<div class="panel-zasobow">
    <div class="zasob"><span>Inżynieria (Skill)</span><b style="color:#00ff00; text-shadow: 0 0 10px rgba(0,255,0,0.4);"><?php echo number_format($gracz['umiejetnosc_inzynierii'], 2); ?></b></div>
    <div class="zasob"><span>Inteligencja</span><b style="color:#ffaa00;"><?php echo $gracz['inteligencja']; ?></b></div>
    <div class="zasob"><span>Energia</span><b style="color:#00ccff;"><?php echo $gracz['energia_aktualna']; ?></b></div>
    
    <div class="zasob" style="border-left: 1px dashed rgba(255,255,255,0.1);"><span>Stal 🔩</span><b><?php echo $gracz['zlom_stalowy']; ?></b></div>
    <div class="zasob"><span>Części ⚙️</span><b><?php echo $gracz['czesci_mechaniczne']; ?></b></div>
    <div class="zasob"><span>Kevlar 🧵</span><b><?php echo $gracz['syntetyki']; ?></b></div>
    <div class="zasob"><span>Elektronika 🔋</span><b><?php echo $gracz['elektronika']; ?></b></div>
</div>

<?php foreach($katalog_schematow as $nazwa_kat => $bronie): ?>
    <div class="kategoria-box">
        <div class="kategoria-tytul"><?php echo $nazwa_kat; ?></div>
        <div class="schematy-grid">
            <?php foreach($bronie as $kod => $s): 
                $brak_mat = ($gracz['zlom_stalowy'] < $s['stal'] || $gracz['czesci_mechaniczne'] < $s['czesci'] || $gracz['syntetyki'] < $s['syn'] || $gracz['elektronika'] < $s['elek']);
                $ilosc_lootu = isset($posiadane_lupy[$s['loot']]) ? $posiadane_lupy[$s['loot']] : 0;
                $brak_loot = ($s['loot'] && $ilosc_lootu < 1);
                
                // --- OBLICZANIE SZANSY WIDOCZNEJ DLA GRACZA ---
                $szansa_widok = (50 + ($gracz['umiejetnosc_inzynierii'] * 2.5) + ($gracz['inteligencja'] / 2)) - ($s['trudnosc'] / 1.5);
                $szansa_widok = max(10, min(95, $szansa_widok));
                
                $kolor_szansy = "#00ff00";
                if ($szansa_widok < 70) $kolor_szansy = "#ffaa00";
                if ($szansa_widok < 40) $kolor_szansy = "#ff3333";
            ?>
                <div class="schemat-karta">
                    <h3>
                        <?php echo $s['nazwa']; ?> 
                        <span class="posiada-badge">W szafce: <?php echo $gracz[$kod]; ?></span>
                    </h3>
                    
                    <div class="wymagania">
                        <?php if($s['stal'] > 0) echo "<span class='req-item'>Stal: <b>{$s['stal']}</b></span>"; ?>
                        <?php if($s['czesci'] > 0) echo "<span class='req-item'>Części: <b>{$s['czesci']}</b></span>"; ?>
                        <?php if($s['syn'] > 0) echo "<span class='req-item'>Kevlar: <b>{$s['syn']}</b></span>"; ?>
                        <?php if($s['elek'] > 0) echo "<span class='req-item'>Elektronika: <b>{$s['elek']}</b></span>"; ?>
                        
                        <?php if($s['loot']): ?>
                            <div class="loot-req">
                                💎 Wymaga: <?php echo $s['loot']; ?> <br>
                                <span style="color: <?php echo $brak_loot ? '#ff3333' : '#00ff00'; ?>; font-size: 0.8em;">(Posiadasz: <?php echo $ilosc_lootu; ?>)</span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 15px; font-family: 'Oswald', sans-serif; text-transform: uppercase;">
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:#aaa;">Szansa udanej kalibracji:</span>
                                <b style="color:<?php echo $kolor_szansy; ?>;"><?php echo round($szansa_widok); ?>%</b>
                            </div>
                            <div class="szansa-bar-bg">
                                <div class="szansa-bar-fill" style="width: <?php echo round($szansa_widok); ?>%; background: <?php echo $kolor_szansy; ?>;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" style="margin-top:auto;">
                        <input type="hidden" name="schemat_kod" value="<?php echo $kod; ?>">
                        <button type="submit" name="wytworz" class="btn-wytworz <?php echo ($brak_mat || $brak_loot) ? 'btn-disabled' : ''; ?>" <?php echo ($brak_mat || $brak_loot) ? 'disabled' : ''; ?>>
                            <?php echo ($brak_mat || $brak_loot) ? 'Braki Surowcowe' : 'Buduj (-'.$s['en'].' EN)'; ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>