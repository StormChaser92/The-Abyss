<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";

// 1. POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT poziom, klasa, energia_aktualna, walka_bronia, uniki, treningi_silownia, ostatni_trening_odnowienie, pokoje_specjalne FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

$posiadane_pokoje = !empty($gracz['pokoje_specjalne']) ? json_decode($gracz['pokoje_specjalne'], true) : [];

// 2. WERYFIKACJA DOSTĘPU I ODNOWIENIE TRENINGÓW
if (!in_array("Prywatna Siłownia", $posiadane_pokoje)) {
    echo "<div style='padding: 50px; text-align: center; color: #ff3333; font-family: Oswald; font-size: 2em;'>Nie posiadasz Prywatnej Siłowni!</div>";
    exit;
}

$dzisiaj_data = date('Y-m-d');
$ostatnie_odnowienie_data = date('Y-m-d', strtotime($gracz['ostatni_trening_odnowienie']));

if ($dzisiaj_data > $ostatnie_odnowienie_data) {
    $dni_roznicy = floor((strtotime($dzisiaj_data) - strtotime($ostatnie_odnowienie_data)) / (60 * 60 * 24));
    $nowe_treningi = min(5, $gracz['treningi_silownia'] + $dni_roznicy);
    $polaczenie->query("UPDATE gracze SET treningi_silownia = $nowe_treningi, ostatni_trening_odnowienie = NOW() WHERE id = $id_gracza");
    $gracz['treningi_silownia'] = $nowe_treningi;
}

// 3. LOGIKA TRENINGU (ZMNIEJSZONY KOSZT ENERGII)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['trenuj'])) {
    $co = $_POST['co_trenowac']; 
    $koszt_en = 5; // ZMIENIONO NA 5 EN

    if ($gracz['treningi_silownia'] < 1) {
        $komunikat = "<div class='blad'>Jesteś przetrenowany! Dzisiejszy limit biletów wyczerpany.</div>";
    } else if ($gracz['energia_aktualna'] < $koszt_en) {
        $komunikat = "<div class='blad'>Nawet na 5 EN nie masz siły? Odpocznij chwilę.</div>";
    } else {
        $L = $gracz['poziom'];
        $R = mt_rand(80, 120) / 100; 
        $B = 0.05; 
        $P_max = 0.50; 
        
        $zdobyte_punkty = ($B + ($L / 100)) * $R;
        if ($zdobyte_punkty > $P_max) $zdobyte_punkty = $P_max;
        $zdobyte_punkty = round($zdobyte_punkty, 2);

        if ($co == 'wb') {
            $polaczenie->query("UPDATE gracze SET walka_bronia = walka_bronia + $zdobyte_punkty, energia_aktualna = energia_aktualna - $koszt_en, treningi_silownia = treningi_silownia - 1 WHERE id = $id_gracza");
            $gracz['walka_bronia'] += $zdobyte_punkty;
            $nazwa_skilla = "Walki Bronią";
        } else {
            $polaczenie->query("UPDATE gracze SET uniki = uniki + $zdobyte_punkty, energia_aktualna = energia_aktualna - $koszt_en, treningi_silownia = treningi_silownia - 1 WHERE id = $id_gracza");
            $gracz['uniki'] += $zdobyte_punkty;
            $nazwa_skilla = "Uników";
        }
        
        $gracz['treningi_silownia'] -= 1;
        $gracz['energia_aktualna'] -= $koszt_en;
        $komunikat = "<div class='sukces'>Trening zaliczony! Zdobywasz <b>+$zdobyte_punkty</b> do $nazwa_skilla. Koszt: $koszt_en EN.</div>";
    }
}
?>

<style>
    .silownia-box { background: #0a0a0a; border: 2px solid #555; padding: 30px; border-radius: 5px; text-align: center; }
    .silownia-ikona { font-size: 4em; margin-bottom: 20px; }
    .status-treningu { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; }
    .stat-karta { background: #111; padding: 15px 25px; border: 1px solid #333; border-radius: 4px; text-align: center; }
    .stat-karta span { display: block; color: #888; font-size: 0.85em; text-transform: uppercase; margin-bottom: 5px; }
    .stat-karta b { color: #fff; font-size: 1.8em; font-family: 'Oswald'; }
    .trening-kontener { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .trening-opcja { background: #151515; border: 1px solid #222; padding: 20px; border-radius: 4px; transition: 0.3s; }
    .trening-opcja h3 { color: #ff3333; font-family: 'Oswald'; margin-top: 0; text-transform: uppercase; }
    .btn-trenuj { background: #ff3333; color: #000; border: none; padding: 15px; font-family: 'Oswald'; font-size: 1.1em; cursor: pointer; text-transform: uppercase; width: 100%; border-radius: 3px; transition: 0.3s; margin-top: 15px; }
    .btn-trenuj:hover { background: #fff; }
    .btn-disabled { background: #222; color: #555; cursor: not-allowed; }
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 20px; }
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; }
</style>

<h1 class="witaj">Prywatna Siłownia</h1>
<?php echo $komunikat; ?>

<div class="silownia-box">
    <div class="silownia-ikona">🏋️</div>
    <div class="status-treningu">
        <div class="stat-karta"><span>Dostępne Treningi</span><b style="color:#ffaa00;"><?php echo $gracz['treningi_silownia']; ?> / 5</b></div>
        <div class="stat-karta"><span>Twoja Energia</span><b style="color: #00ccff;"><?php echo $gracz['energia_aktualna']; ?></b></div>
    </div>

    <div class="trening-kontener">
        <div class="trening-opcja">
            <h3>Worek i Tarcze</h3>
            <div style="font-family: 'Oswald'; color: #fff;">Poziom: <span style="color: #00ff00;"><?php echo number_format($gracz['walka_bronia'], 2); ?></span></div>
            <form method="POST">
                <input type="hidden" name="co_trenowac" value="wb">
                <button type="submit" name="trenuj" class="btn-trenuj <?php echo ($gracz['treningi_silownia']<1)?'btn-disabled':''; ?>" <?php echo ($gracz['treningi_silownia']<1)?'disabled':''; ?>>Trenuj (-5 EN)</button>
            </form>
        </div>
        <div class="trening-opcja">
            <h3>Skakanka i Refleks</h3>
            <div style="font-family: 'Oswald'; color: #fff;">Poziom: <span style="color: #00ff00;"><?php echo number_format($gracz['uniki'], 2); ?></span></div>
            <form method="POST">
                <input type="hidden" name="co_trenowac" value="uniki">
                <button type="submit" name="trenuj" class="btn-trenuj <?php echo ($gracz['treningi_silownia']<1)?'btn-disabled':''; ?>" <?php echo ($gracz['treningi_silownia']<1)?'disabled':''; ?>>Trenuj (-5 EN)</button>
            </form>
        </div>
    </div>
</div>
<br><a href="game.php?page=mieszkanie" style="color: #888; text-decoration: none;">← Wróć do Mieszkania</a>