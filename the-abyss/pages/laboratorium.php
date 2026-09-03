<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";

// 1. POBIERANIE DANYCH
$wynik = $polaczenie->query("SELECT login, klasa, materialy, apteczki, energia_aktualna FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// 2. LOGIKA PRODUKCJI (Tylko dla Inżyniera!)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['produkuj_apteczke'])) {
    if ($gracz['klasa'] !== 'Inżynier') {
        $komunikat = "<div class='blad'>Jesteś Egzekutorem. Jedyne co potrafisz zrobić w laboratorium, to je wysadzić. Brak kwalifikacji.</div>";
    } else {
        $koszt_mat = 10;
        $koszt_en = 20;

        if ($gracz['materialy'] < $koszt_mat) {
            $komunikat = "<div class='blad'>Brak materiałów! Potrzebujesz $koszt_mat sztuk surowca.</div>";
        } else if ($gracz['energia_aktualna'] < $koszt_en) {
            $komunikat = "<div class='blad'>Jesteś zbyt zmęczony na precyzyjną pracę laboratoryjną.</div>";
        } else {
            // Sukces!
            $polaczenie->query("UPDATE gracze SET 
                materialy = materialy - $koszt_mat, 
                energia_aktualna = energia_aktualna - $koszt_en,
                apteczki = apteczki + 1 
                WHERE id = $id_gracza");
            
            $komunikat = "<div class='sukces'>Udało się! Wyprodukowałeś profesjonalną Apteczkę Uliczną.</div>";
            
            // Odśwież dane do widoku
            $gracz['materialy'] -= $koszt_mat;
            $gracz['apteczki'] += 1;
            $gracz['energia_aktualna'] -= $koszt_en;
        }
    }
}
?>

<style>
    .lab-box { background: #0a1a1a; border: 2px solid #00ccff; padding: 30px; border-radius: 5px; text-align: center; }
    .lab-ikona { font-size: 4em; color: #00ccff; margin-bottom: 20px; }
    .zasoby-info { display: flex; justify-content: center; gap: 30px; margin-bottom: 30px; background: #111; padding: 15px; border-radius: 4px; }
    .zasob { text-align: center; }
    .zasob span { display: block; color: #888; font-size: 0.8em; text-transform: uppercase; }
    .zasob b { font-size: 1.5em; color: #fff; font-family: 'Oswald'; }
    
    .btn-lab { background: #00ccff; color: #000; border: none; padding: 15px 30px; font-family: 'Oswald'; font-size: 1.2em; cursor: pointer; text-transform: uppercase; border-radius: 4px; }
    .btn-lab:hover { background: #fff; }
    .lock-msg { background: rgba(255,0,0,0.1); border: 1px solid #ff3333; color: #ff3333; padding: 20px; border-radius: 4px; font-size: 1.1em; }
    
    .sukces { background: rgba(0, 204, 255, 0.1); border: 1px solid #00ccff; color: #00ccff; padding: 15px; margin-bottom: 15px; }
    .blad { background: rgba(255,51,51,0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 15px; }
</style>

<h1 class="witaj">Laboratorium Chemiczne</h1>

<?php echo $komunikat; ?>

<div class="lab-box">
    <div class="lab-ikona">⚗️</div>
    
    <div class="zasoby-info">
        <div class="zasob"><span>Materiały</span><b><?php echo $gracz['materialy']; ?></b></div>
        <div class="zasob"><span>Twoje Apteczki</span><b><?php echo $gracz['apteczki']; ?></b></div>
    </div>

    <?php if ($gracz['klasa'] === 'Inżynier'): ?>
        <p style="color: #aaa; margin-bottom: 20px;">Jako Inżynier potrafisz syntezować leki z odzyskanego złomu i chemikaliów.</p>
        <form method="POST">
            <button type="submit" name="produkuj_apteczke" class="btn-lab">Wytwórz Apteczkę (10 Mat. | 20 EN)</button>
        </form>
    <?php else: ?>
        <div class="lock-msg">
            <b>DOSTĘP ZABLOKOWANY</b><br>
            Twoja klasa (<?php echo $gracz['klasa']; ?>) nie posiada wiedzy potrzebnej do obsługi aparatury medycznej. 
            Możesz jedynie przebywać w tym pomieszczeniu, ale nic tu nie wyprodukujesz.
        </div>
    <?php endif; ?>
</div>

<br>
<a href="game.php?page=mieszkanie" style="color: #888; text-decoration: none;">← Wróć do salonu</a>