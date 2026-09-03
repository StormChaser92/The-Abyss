<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";
$zakladka = isset($_GET['zakladka']) ? $_GET['zakladka'] : 'odbiorcza';

// WYSYŁANIE WIADOMOŚCI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['wyslij_wiadomosc'])) {
    $odbiorca_login = $polaczenie->real_escape_string(trim($_POST['odbiorca']));
    $tytul = $polaczenie->real_escape_string(trim($_POST['tytul']));
    $tresc = $polaczenie->real_escape_string(trim($_POST['tresc']));
    
    if (empty($odbiorca_login) || empty($tytul) || empty($tresc)) {
        $komunikat = "<div class='blad'>Wypełnij wszystkie pola!</div>";
    } else {
        // Szukamy ID odbiorcy na podstawie loginu
        $wynik_odb = $polaczenie->query("SELECT id FROM gracze WHERE login = '$odbiorca_login'");
        if ($wynik_odb->num_rows > 0) {
            $odbiorca = $wynik_odb->fetch_assoc();
            $odbiorca_id = $odbiorca['id'];
            
            $polaczenie->query("INSERT INTO wiadomosci (nadawca_id, odbiorca_id, tytul, tresc) VALUES ($id_gracza, $odbiorca_id, '$tytul', '$tresc')");
            $komunikat = "<div class='sukces'>Wiadomość została wysłana do obywatela: $odbiorca_login.</div>";
            $zakladka = 'wyslane'; // Po wysłaniu przerzucamy do wysłanych
        } else {
            $komunikat = "<div class='blad'>Gracz o nicku '$odbiorca_login' nie istnieje!</div>";
        }
    }
}

// USUWANIE WIADOMOŚCI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usun_wiadomosc'])) {
    $id_wiadomosci = (int)$_POST['id_wiadomosci'];
    // Sprawdzamy czy użytkownik jest odbiorcą lub nadawcą, by mógł to usunąć
    $polaczenie->query("DELETE FROM wiadomosci WHERE id = $id_wiadomosci AND (odbiorca_id = $id_gracza OR nadawca_id = $id_gracza)");
    $komunikat = "<div class='sukces'>Wiadomość została spalona. Ślady zatarte.</div>";
}

// Oznaczanie wiadomości jako odczytane w skrzynce odbiorczej
if ($zakladka == 'odbiorcza') {
    $polaczenie->query("UPDATE wiadomosci SET odczytana = 1 WHERE odbiorca_id = $id_gracza AND odczytana = 0");
}

// Oznaczanie alertów jako odczytane
if ($zakladka == 'alerty') {
    $polaczenie->query("UPDATE powiadomienia SET odczytane = 1 WHERE gracz_id = $id_gracza AND odczytane = 0");
}
?>

<style>
    .poczta-nav { display: flex; background: #111; border: 1px solid #333; margin-bottom: 20px; border-radius: 4px; overflow: hidden; }
    .poczta-tab { flex: 1; text-align: center; padding: 15px; color: #aaa; text-decoration: none; font-family: 'Oswald'; text-transform: uppercase; border-right: 1px solid #222; transition: 0.3s; }
    .poczta-tab:last-child { border-right: none; }
    .poczta-tab:hover { background: #1a1a1a; color: #fff; }
    .poczta-tab.aktywny { background: #0066cc; color: #fff; font-weight: bold; }
    
    .panel { background: #0a0a0a; border: 1px solid #333; padding: 25px; border-radius: 4px; margin-bottom: 20px; }
    .panel h2 { font-family: 'Oswald'; color: #fff; text-transform: uppercase; border-bottom: 1px dashed #333; padding-bottom: 10px; margin-top: 0; }
    
    .wiadomosc-box { background: #111; border: 1px solid #222; border-left: 3px solid #0066cc; padding: 15px; margin-bottom: 15px; border-radius: 3px; position: relative; }
    .wiadomosc-nieodczytana { border-left-color: #00ff00; background: #0a1a0a; }
    .wiadomosc-header { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9em; color: #888; border-bottom: 1px dashed #333; padding-bottom: 5px; }
    .wiadomosc-tytul { font-family: 'Oswald'; font-size: 1.3em; color: #fff; margin: 0 0 10px 0; }
    .wiadomosc-tresc { color: #ccc; line-height: 1.5; white-space: pre-wrap; font-size: 0.95em; }
    
    .btn-akcja { background: #0066cc; color: #fff; border: 1px solid #00aaff; padding: 12px 20px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; }
    .btn-akcja:hover { background: #00aaff; color: #000; }
    .btn-usun { background: transparent; border: 1px solid #ff3333; color: #ff3333; padding: 5px 10px; font-size: 0.8em; margin-top: 10px; }
    .btn-usun:hover { background: #ff3333; color: #000; }
    
    .input-text, .input-textarea { width: 100%; padding: 12px; background: #050505; border: 1px solid #444; color: #fff; border-radius: 3px; font-size: 1em; margin-bottom: 15px; box-sizing: border-box; font-family: 'Open Sans', sans-serif; }
    .input-textarea { resize: vertical; min-height: 150px; }
    .input-text:focus, .input-textarea:focus { outline: none; border-color: #00aaff; }
    
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; }
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; }
</style>

<h1 class="witaj">Terminal Komunikacyjny</h1>

<div class="poczta-nav">
    <a href="game.php?page=poczta&zakladka=odbiorcza" class="poczta-tab <?php if($zakladka=='odbiorcza') echo 'aktywny'; ?>">📥 Odbiorcza</a>
    <a href="game.php?page=poczta&zakladka=wyslane" class="poczta-tab <?php if($zakladka=='wyslane') echo 'aktywny'; ?>">📤 Wysłane</a>
    <a href="game.php?page=poczta&zakladka=napisz" class="poczta-tab <?php if($zakladka=='napisz') echo 'aktywny'; ?>">✍️ Napisz</a>
    <a href="game.php?page=poczta&zakladka=alerty" class="poczta-tab <?php if($zakladka=='alerty') echo 'aktywny'; ?>" style="background: #1a051a; border-left: 1px solid #441166;">🔔 Alerty</a>
</div>

<?php echo $komunikat; ?>

<?php if ($zakladka == 'odbiorcza'): ?>
    <div class="panel">
        <h2>Otrzymane Wiadomości</h2>
        <?php
        $sql = "SELECT w.*, g.login AS nadawca_login FROM wiadomosci w JOIN gracze g ON w.nadawca_id = g.id WHERE w.odbiorca_id = $id_gracza ORDER BY w.data_wyslania DESC";
        $wynik_wiad = $polaczenie->query($sql);
        
        if ($wynik_wiad->num_rows > 0) {
            while ($w = $wynik_wiad->fetch_assoc()) {
                $klasa_nieodczytana = ($w['odczytana'] == 0) ? "wiadomosc-nieodczytana" : "";
                echo "<div class='wiadomosc-box $klasa_nieodczytana'>";
                echo "<div class='wiadomosc-header'>";
                echo "<span>Od: <a href='game.php?page=profil&id={$w['nadawca_id']}' style='color:#00ccff; font-weight:bold; text-decoration:none;'>{$w['nadawca_login']}</a></span>";
                echo "<span>Data: {$w['data_wyslania']}</span>";
                echo "</div>";
                echo "<h3 class='wiadomosc-tytul'>" . htmlspecialchars($w['tytul']) . "</h3>";
                echo "<div class='wiadomosc-tresc'>" . htmlspecialchars($w['tresc']) . "</div>";
                
                echo "<div style='display:flex; justify-content:space-between; align-items:center;'>";
                echo "<a href='game.php?page=poczta&zakladka=napisz&do={$w['nadawca_login']}&re=" . urlencode("RE: " . $w['tytul']) . "' style='color:#00ff00; text-decoration:none; font-size:0.9em; margin-top:10px; display:inline-block;'>↩ Odpowiedz</a>";
                echo "<form method='POST' style='margin:0;'><input type='hidden' name='id_wiadomosci' value='{$w['id']}'><button type='submit' name='usun_wiadomosc' class='btn-usun'>Usuń</button></form>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p style='color: #888; text-align: center; padding: 20px;'>Twoja skrzynka odbiorcza jest pusta.</p>";
        }
        ?>
    </div>

<?php elseif ($zakladka == 'wyslane'): ?>
    <div class="panel">
        <h2>Wysłane Wiadomości</h2>
        <?php
        $sql = "SELECT w.*, g.login AS odbiorca_login FROM wiadomosci w JOIN gracze g ON w.odbiorca_id = g.id WHERE w.nadawca_id = $id_gracza ORDER BY w.data_wyslania DESC";
        $wynik_wiad = $polaczenie->query($sql);
        
        if ($wynik_wiad->num_rows > 0) {
            while ($w = $wynik_wiad->fetch_assoc()) {
                $status = ($w['odczytana'] == 1) ? "<span style='color:#888;'>Przeczytano</span>" : "<span style='color:#ffaa00;'>Dostarczono</span>";
                
                echo "<div class='wiadomosc-box'>";
                echo "<div class='wiadomosc-header'>";
                echo "<span>Do: <a href='game.php?page=profil&id={$w['odbiorca_id']}' style='color:#dd88ff; font-weight:bold; text-decoration:none;'>{$w['odbiorca_login']}</a></span>";
                echo "<span>$status | Data: {$w['data_wyslania']}</span>";
                echo "</div>";
                echo "<h3 class='wiadomosc-tytul'>" . htmlspecialchars($w['tytul']) . "</h3>";
                echo "<div class='wiadomosc-tresc'>" . htmlspecialchars($w['tresc']) . "</div>";
                echo "<div style='text-align:right;'><form method='POST' style='margin:0;'><input type='hidden' name='id_wiadomosci' value='{$w['id']}'><button type='submit' name='usun_wiadomosc' class='btn-usun'>Usuń z historii</button></form></div>";
                echo "</div>";
            }
        } else {
            echo "<p style='color: #888; text-align: center; padding: 20px;'>Nie wysłałeś jeszcze żadnych wiadomości.</p>";
        }
        ?>
    </div>

<?php elseif ($zakladka == 'napisz'): 
    $domyslny_odbiorca = isset($_GET['do']) ? htmlspecialchars($_GET['do']) : "";
    $domyslny_tytul = isset($_GET['re']) ? htmlspecialchars($_GET['re']) : "";
?>
    <div class="panel">
        <h2>Nowy Komunikat</h2>
        <form method="POST">
            <label style="color:#888; display:block; margin-bottom:5px;">Odbiorca (dokładny login):</label>
            <input type="text" name="odbiorca" class="input-text" value="<?php echo $domyslny_odbiorca; ?>" required>
            
            <label style="color:#888; display:block; margin-bottom:5px;">Tytuł wiadomości:</label>
            <input type="text" name="tytul" class="input-text" value="<?php echo $domyslny_tytul; ?>" required maxlength="100">
            
            <label style="color:#888; display:block; margin-bottom:5px;">Treść komunikatu:</label>
            <textarea name="tresc" class="input-textarea" required placeholder="Napisz tutaj swój list..."></textarea>
            
            <button type="submit" name="wyslij_wiadomosc" class="btn-akcja" style="width: 100%;">WYŚLIJ WIADOMOŚĆ</button>
        </form>
    </div>

<?php elseif ($zakladka == 'alerty'): ?>
    <div class="panel" style="border-color: #aa00ff;">
        <h2 style="color: #dd88ff;">Powiadomienia Systemowe i Społecznościowe</h2>
        <p style="color: #888; margin-bottom: 20px;">Tu trafiają informacje o akcjach innych graczy wobec Ciebie oraz ważne komunikaty z The Abyss.</p>
        
        <?php
        $alerty = $polaczenie->query("SELECT * FROM powiadomienia WHERE gracz_id = $id_gracza ORDER BY data_utworzenia DESC LIMIT 50");
        if ($alerty->num_rows > 0) {
            while ($row = $alerty->fetch_assoc()) {
                // Jeśli alert był nieodczytany, dajemy mu zielony akcent, w przeciwnym razie jest ciemny
                $style = ($row['odczytane'] == 0) ? "border-left: 3px solid #00ff00; background: #0a1a0a;" : "border-left: 3px solid #333;";
                
                echo "<div class='wiadomosc-box' style='$style padding: 15px; margin-bottom: 10px; border-radius: 4px;'>";
                echo "<div style='font-size: 0.8em; color: #666; margin-bottom: 5px;'>{$row['data_utworzenia']}</div>";
                echo "<div style='color: #ccc; font-size: 1.05em;'>{$row['tresc']}</div>";
                echo "</div>";
            }
        } else {
            echo "<p style='text-align: center; color: #555; padding: 30px;'>Cisza na łączach. Nikt Cię nie niepokoi.</p>";
        }
        ?>
    </div>
<?php endif; ?>