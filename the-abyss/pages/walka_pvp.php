<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

if (!isset($_GET['cel']) || !is_numeric($_GET['cel'])) {
    header("Location: game.php?page=ranking"); exit;
}

$cel_id = (int)$_GET['cel'];
$koszt_energii = 3;

// 1. POBIERANIE DANYCH OBU GRACZY
$sql = "SELECT id, login, gotowka, hp_aktualne, hp_max, energia_aktualna, sila, zrecznosc, wytrzymalosc, inteligencja, walka_bronia, uniki, bonus_atak, bonus_obrona, tryb_pacyfisty, w_mieszkaniu FROM gracze WHERE id IN ($id_gracza, $cel_id)";
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

// Statystyki bojowe
$moja_celnosc = 60 + ($ja['zrecznosc'] * 1.5) + ($ja['walka_bronia'] * 2);
$jego_unik = ($on['zrecznosc'] * 1.5) + ($on['uniki'] * 2);
$moja_szansa_trafienia = max(5, min(95, $moja_celnosc - $jego_unik));

$mój_atak = $ja['sila'] + $ja['bonus_atak'];
$jego_pancerz = $on['wytrzymalosc'] + $on['bonus_obrona'];
$redukcja_jego = 50 / (50 + $jego_pancerz);

$moja_szansa_kryta = 5 + ($ja['inteligencja'] * 0.5);

// Przebieg (uproszczony do 3 wymian dla dynamiki)
$jego_hp = $on['hp_aktualne'];
$moje_hp = $ja['hp_aktualne'];

for($r=1; $r<=5; $r++) {
    // Mój atak
    if (rand(1,100) <= $moja_szansa_trafienia) {
        $dmg = round($mój_atak * $redukcja_jego * (rand(90,110)/100));
        if (rand(1,100) <= $moja_szansa_kryta) { $dmg *= 2; $log[] = "<b style='color:#ffaa00;'>[KRYTYK!]</b> Uderzasz czule! -$dmg HP."; }
        else { $log[] = "Trafiasz przeciwnika: -$dmg HP."; }
        $jego_hp -= $dmg;
    } else { $log[] = "<span style='color:#888;'>Pudłujesz...</span>"; }
    
    if ($jego_hp <= 0) break;
    
    // Jego kontratak (analogicznie)
    $jego_celnosc = 60 + ($on['zrecznosc'] * 1.5) + ($on['walka_bronia'] * 2);
    $moj_unik = ($ja['zrecznosc'] * 1.5) + ($ja['uniki'] * 2);
    if (rand(1,100) <= max(5, min(95, $jego_celnosc - $moj_unik))) {
        $dmg_e = round(($on['sila'] + $on['bonus_atak']) * (50 / (50 + $ja['wytrzymalosc'] + $ja['bonus_obrona'])));
        $moje_hp -= $dmg_e;
        $log[] = "<span style='color:#ff3333;'>Otrzymujesz cios: -$dmg_e HP.</span>";
    }
    if ($moje_hp <= 10) break;

    // Obrażenia bronią białą (Japończyk)
if ($typ_broni === 'melee') {
    $dmg = round($dmg * pochodzenie_bonus($gracz_r, 'egzekutor_dmg_melee_mult', 1.0));
    $szansa_krytyka += pochodzenie_bonus($gracz_r, 'egzekutor_krytyk_melee_abs', 0);
}

// Pierwsza tura (Hiszpan)
if ($tura === 1) {
    $dmg = round($dmg * pochodzenie_bonus($gracz_r, 'egzekutor_dmg_pierwsza_tura_mult', 1.0));
}

// Otrzymywane obrażenia melee (Rosjanin)
if ($typ_ataku === 'melee') {
    $dmg_otrzymane = round($dmg_otrzymane * pochodzenie_bonus($gracz_r, 'dmg_otrzymywanych_melee_mult', 1.0));
}

// Unik (Brazylijczyk)
$unik_szansa += pochodzenie_bonus($gracz_r, 'unik_szansa_abs', 0);
}

// 4. ROZSTRZYGNIĘCIE
if ($jego_hp <= 0 || ($jego_hp < $moje_hp && $moje_hp > 10)) {
    // WYGRANA
    $procent_lupu = rand(5, 15);
    $zrabowano = round($on['gotowka'] * ($procent_lupu / 100));
    
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $zrabowano, energia_aktualna = energia_aktualna - $koszt_energii, ostatni_atak_pvp = NOW() WHERE id = $id_gracza");
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $zrabowano, hp_aktualne = max(1, $jego_hp) WHERE id = $cel_id");
    
    // Alert dla ofiary
    $alert = "Zostałeś napadnięty przez <b>{$ja['login']}</b>! Po krótkiej walce straciłeś <b>$zrabowano $</b>.";
    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '$alert')");
    
    $tytul_walki = "ZWYCIĘSTWO!";
    $podsumowanie = "Pokonałeś przeciwnika i zrabowałeś <b>$zrabowano $</b> (ok. $procent_lupu% jego portfela).";
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