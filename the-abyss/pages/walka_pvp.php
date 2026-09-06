<?php
require_once "db.php";
require_once "config/pochodzenia.php";  // pochodzenie_bonus()
require_once "includes/melina.php";     // rzut na Czarną Pieczęć po wygranej
$id_gracza = $_SESSION['id_gracza'];

if (!isset($_GET['cel']) || !is_numeric($_GET['cel'])) {
    header("Location: game.php?page=ranking"); exit;
}

$cel_id = (int)$_GET['cel'];
$koszt_energii = 3;

// 1. POBIERANIE DANYCH OBU GRACZY
$sql = "SELECT id, login, gotowka, hp_aktualne, hp_max, energia_aktualna, sila, zrecznosc, wytrzymalosc, inteligencja, walka_bronia, uniki, bonus_atak, bonus_obrona, tryb_pacyfisty, w_mieszkaniu, syndykat_id, pochodzenie, bron_zalozona FROM gracze WHERE id IN ($id_gracza, $cel_id)";
$wynik = $polaczenie->query($sql);

$gracze = [];
while($r = $wynik->fetch_assoc()) { $gracze[$r['id']] = $r; }

$ja = $gracze[$id_gracza];
$on = $gracze[$cel_id];

// 2. WALIDACJA - CZY WALKA MOŻE SIĘ ODBYĆ?
if ($ja['id'] == $on['id']) { die("Nie możesz zaatakować samego siebie."); }
if ($ja['energia_aktualna'] < $koszt_energii) { die("Masz za mało energii ($koszt_energii EN)."); }
if ($ja['tryb_pacyfisty'] == 1) { die("Jesteś w trybie nietykalności. Wyłącz go, by atakować."); }
if ($on['tryb_pacyfisty'] == 1) { die("Ten obywatel jest nietykalny."); }
// ══ BEZPIECZNA STREFA — ofiara w mieszkaniu ══
if ($on['w_mieszkaniu'] == 1) { die("Ten gracz ukrył się w swoim mieszkaniu. Nie możesz go tam dosięgnąć."); }

// ══ BEZPIECZNA STREFA — atakujący w mieszkaniu ══  
if ($ja['w_mieszkaniu'] == 1) { die("Nie możesz atakować będąc w swoim mieszkaniu. Najpierw wyjdź na ulicę."); }
if ($on['hp_aktualne'] < 20) { die("Przeciwnik jest zbyt ranny, by go atakować."); }

// 3. SYMULACJA WALKI (MATEMATYKA)
$log = [];

// Typ broni — decyduje o bonusach do walki wręcz (Japończyk, Rosjanin).
function pvp_typ_broni(?string $nazwa): string {
    $n = mb_strtolower($nazwa ?? '');
    foreach (['nóż','noz','kij','maczeta','katana','pałka','palka','kastet'] as $melee)
        if (mb_strpos($n, $melee) !== false) return 'melee';
    return 'ranged';
}
$moj_typ_broni  = pvp_typ_broni($ja['bron_zalozona'] ?? '');
$jego_typ_broni = pvp_typ_broni($on['bron_zalozona'] ?? '');

// Statystyki bojowe
$moja_celnosc = 60 + ($ja['zrecznosc'] * 1.5) + ($ja['walka_bronia'] * 2);
$jego_unik = ($on['zrecznosc'] * 1.5) + ($on['uniki'] * 2);
$moja_szansa_trafienia = max(5, min(95, $moja_celnosc - $jego_unik));

$mój_atak = $ja['sila'] + $ja['bonus_atak'];
$jego_pancerz = $on['wytrzymalosc'] + $on['bonus_obrona'];
$redukcja_jego = 50 / (50 + $jego_pancerz);

$moja_szansa_kryta = 5 + ($ja['inteligencja'] * 0.5);

// Bonusy pochodzenia — liczone raz, przed pętlą.
// Brazylijczyk (unik_szansa_abs) obniża szansę trafienia przeciwnika.
$moj_bonus_uniku  = pochodzenie_bonus($ja, 'unik_szansa_abs', 0);
$jego_bonus_uniku = pochodzenie_bonus($on, 'unik_szansa_abs', 0);
$moja_szansa_trafienia = max(5, min(95, $moja_szansa_trafienia - $jego_bonus_uniku));

// Japończyk: obrażenia i krytyk bronią białą.
$mnoznik_melee = $moj_typ_broni === 'melee' ? pochodzenie_bonus($ja, 'egzekutor_dmg_melee_mult', 1.0) : 1.0;
if ($moj_typ_broni === 'melee') $moja_szansa_kryta += pochodzenie_bonus($ja, 'egzekutor_krytyk_melee_abs', 0);

// Hiszpan: pierwsza tura. Rosjanin: mniej obrywa w zwarciu.
$mnoznik_pierwszej   = pochodzenie_bonus($ja, 'egzekutor_dmg_pierwsza_tura_mult', 1.0);
$moja_redukcja_melee = pochodzenie_bonus($ja, 'dmg_otrzymywanych_melee_mult', 1.0);
$jego_redukcja_melee = pochodzenie_bonus($on, 'dmg_otrzymywanych_melee_mult', 1.0);

// Przebieg (uproszczony do 3 wymian dla dynamiki)
$jego_hp = $on['hp_aktualne'];
$moje_hp = $ja['hp_aktualne'];

for($r=1; $r<=5; $r++) {
    // Mój atak
    if (rand(1,100) <= $moja_szansa_trafienia) {
        $dmg = round($mój_atak * $redukcja_jego * (rand(90,110)/100));
        $dmg = round($dmg * $mnoznik_melee);
        if ($r === 1) $dmg = round($dmg * $mnoznik_pierwszej);
        if ($moj_typ_broni === 'melee') $dmg = round($dmg * $jego_redukcja_melee);
        $dmg = max(1, (int)$dmg);
        if (rand(1,100) <= $moja_szansa_kryta) { $dmg *= 2; $log[] = "<b style='color:#ffaa00;'>[KRYTYK!]</b> Uderzasz czule! -$dmg HP."; }
        else { $log[] = "Trafiasz przeciwnika: -$dmg HP."; }
        $jego_hp -= $dmg;
    } else { $log[] = "<span style='color:#888;'>Pudłujesz...</span>"; }
    
    if ($jego_hp <= 0) break;
    
    // Jego kontratak (analogicznie)
    $jego_celnosc = 60 + ($on['zrecznosc'] * 1.5) + ($on['walka_bronia'] * 2);
    $moj_unik = ($ja['zrecznosc'] * 1.5) + ($ja['uniki'] * 2);
    if (rand(1,100) <= max(5, min(95, $jego_celnosc - $moj_unik - $moj_bonus_uniku))) {
        $dmg_e = round(($on['sila'] + $on['bonus_atak']) * (50 / (50 + $ja['wytrzymalosc'] + $ja['bonus_obrona'])));
        if ($jego_typ_broni === 'melee') $dmg_e = round($dmg_e * $moja_redukcja_melee);
        $dmg_e = max(1, (int)$dmg_e);
        $moje_hp -= $dmg_e;
        $log[] = "<span style='color:#ff3333;'>Otrzymujesz cios: -$dmg_e HP.</span>";
    }
    if ($moje_hp <= 10) break;
}

// 4. ROZSTRZYGNIĘCIE
if ($jego_hp <= 0 || ($jego_hp < $moje_hp && $moje_hp > 10)) {
    // WYGRANA
    $procent_lupu = rand(5, 15);
    $zrabowano = round($on['gotowka'] * ($procent_lupu / 100));
    
    // Hiszpan: szansa, że walka nic nie kosztuje energetycznie.
    $koszt_po_wygranej = $koszt_energii;
    if (rand(1,100) <= pochodzenie_bonus($ja, 'egzekutor_energia_po_wygranej_szansa', 0)) $koszt_po_wygranej = 0;

    $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $zrabowano, energia_aktualna = energia_aktualna - $koszt_po_wygranej, ostatni_atak_pvp = NOW() WHERE id = $id_gracza");
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $zrabowano, hp_aktualne = max(1, $jego_hp) WHERE id = $cel_id");
    
    // Alert dla ofiary
    $alert = "Zostałeś napadnięty przez <b>{$ja['login']}</b>! Po krótkiej walce straciłeś <b>$zrabowano $</b>.";
    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '$alert')");
    
    // Czarna Pieczęć — tylko za wygraną z członkiem WROGIEGO syndykatu.
    // Szansa: 0,01% x suma walki bronią całego gangu, sufit 60%.
    $pieczec = melina_rzut_na_pieczec($polaczenie, $ja, $on);

    $tytul_walki = "ZWYCIĘSTWO!";
    $podsumowanie = "Pokonałeś przeciwnika i zrabowałeś <b>$zrabowano $</b> (ok. $procent_lupu% jego portfela).";
    if ($pieczec) $podsumowanie .= "<br><span style='color:#ffd700;'>Z kieszeni pokonanego wypada <b>Czarna Pieczęć</b>. Gang może z niej postawić warkę w melinie.</span>";
    $kolor = "#00ff00";
} else {
    // PRZEGRANA
    $polaczenie->query("UPDATE gracze SET hp_aktualne = max(1, $moje_hp), energia_aktualna = energia_aktualna - $koszt_energii, ostatni_atak_pvp = NOW() WHERE id = $id_gracza");
    $tytul_walki = "PORAŻKA!";
    $podsumowanie = "Przeciwnik okazał się silniejszy lub zdołał uciec. Wracasz z niczym i nowymi sińcami.";
    $kolor = "#ff3333";
}
?>

<div style="background: #0a0a0a; border: 1px solid <?php echo $kolor; ?>; padding: 30px; border-radius: 4px; text-align: center;">
    <h1 style="font-family: 'Oswald'; color: <?php echo $kolor; ?>; font-size: 3em; margin: 0;"><?php echo $tytul_walki; ?></h1>
    <p style="font-size: 1.2em; color: #fff; margin: 20px 0;"><?php echo $podsumowanie; ?></p>
    
    <div style="background: #000; padding: 20px; border: 1px solid #222; text-align: left; font-family: monospace; max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
        <?php foreach($log as $linia) echo $linia . "<br>"; ?>
    </div>
    
    <a href="game.php?page=profil&id=<?php echo $cel_id; ?>" class="btn-walka" style="background: #333; color:#fff; text-decoration:none; padding: 10px 20px; display:inline-block; border-radius:3px;">WRÓĆ DO PROFILU</a>
</div>