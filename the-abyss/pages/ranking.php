<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// Definiowanie dostępnych kategorii rankingu
$kolumny = [
    'poziom' => ['db' => 'poziom', 'nazwa' => '🏆 Najwyższy Poziom', 'format' => 'Lvl'],
    'sila' => ['db' => 'sila', 'nazwa' => '💪 Najwięcej Siły', 'format' => 'pkt'],
    'zrecznosc' => ['db' => 'zrecznosc', 'nazwa' => '🤸 Zręczność', 'format' => 'pkt'],
    'wytrzymalosc' => ['db' => 'wytrzymalosc', 'nazwa' => '🛡️ Wytrzymałość', 'format' => 'pkt'],
    'inteligencja' => ['db' => 'inteligencja', 'nazwa' => '🧠 Inteligencja', 'format' => 'pkt'],
    'walka_bronia' => ['db' => 'walka_bronia', 'nazwa' => '⚔️ Walka Bronią', 'format' => 'pkt'],
    'uniki' => ['db' => 'uniki', 'nazwa' => '💨 Zdolność Uniku', 'format' => 'pkt'],
    'gotowka' => ['db' => 'gotowka', 'nazwa' => '💵 Bogacze (Gotówka)', 'format' => '$'],
    'bank' => ['db' => 'bank', 'nazwa' => '🏦 Oszczędności (Bank)', 'format' => '$'],
    'wyswietlenia' => ['db' => 'wyswietlenia_profilu', 'nazwa' => '⭐ Sława (Wyświetlenia)', 'format' => 'widzów']
];

$kategoria = isset($_GET['kat']) && array_key_exists($_GET['kat'], $kolumny) ? $_GET['kat'] : 'poziom';
$aktywna_kategoria = $kolumny[$kategoria];
$kolumna_sql = $aktywna_kategoria['db'];

// Pobieranie top 50 graczy dla wybranej kategorii
$sql = "SELECT id, login, klasa, is_premium, $kolumna_sql AS wynik FROM gracze ORDER BY $kolumna_sql DESC, id ASC LIMIT 50";
$wynik_rankingu = $polaczenie->query($sql);
?>

<style>
    .ranking-header { background: linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.2)), url('https://via.placeholder.com/900x200/111/000?text=Hala+Slaw') center/cover; padding: 40px; border: 1px solid #cca100; border-radius: 4px; margin-bottom: 20px; text-align: left; }
    .ranking-header h1 { font-family: 'Oswald'; color: #ffd700; font-size: 3em; margin: 0; text-transform: uppercase; text-shadow: 2px 2px 5px #000; }
    
    .ranking-kontener { display: grid; grid-template-columns: 250px 1fr; gap: 20px; align-items: start; }
    
    .panel-kategorii { background: #0a0a0a; border: 1px solid #333; border-radius: 4px; padding: 15px; }
    .kat-link { display: block; padding: 12px 15px; color: #aaa; text-decoration: none; font-family: 'Oswald'; text-transform: uppercase; border-bottom: 1px dashed #222; transition: 0.3s; }
    .kat-link:hover { background: #111; color: #fff; padding-left: 20px; }
    .kat-link.aktywna { background: #cca100; color: #000; font-weight: bold; border-color: #cca100; }
    
    .panel-tabeli { background: #0a0a0a; border: 1px solid #333; border-radius: 4px; padding: 25px; }
    .panel-tabeli h2 { font-family: 'Oswald'; color: #fff; text-transform: uppercase; border-bottom: 1px solid #333; padding-bottom: 15px; margin-top: 0; }
    
    .tabela-rank { width: 100%; border-collapse: collapse; }
    .tabela-rank th { background: #111; color: #888; text-transform: uppercase; padding: 15px; border-bottom: 2px solid #333; text-align: left; }
    .tabela-rank td { padding: 12px 15px; border-bottom: 1px dashed #222; color: #ccc; font-size: 1.1em; }
    .tabela-rank tr:hover td { background: rgba(255, 215, 0, 0.05); }
    
    .pozycja-1 { color: #ffd700 !important; font-weight: bold; font-size: 1.3em !important; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
    .pozycja-2 { color: #c0c0c0 !important; font-weight: bold; font-size: 1.2em !important; }
    .pozycja-3 { color: #cd7f32 !important; font-weight: bold; font-size: 1.1em !important; }
    .wynik-liczba { color: #00ff00; font-family: 'Oswald'; }
    
    .nick-link { color: #00ccff; text-decoration: none; font-weight: bold; transition: 0.2s; }
    .nick-link:hover { color: #fff; text-decoration: underline; }
    .moj-wiersz { background: rgba(0, 204, 255, 0.1); border-left: 3px solid #00ccff; }
</style>

<div class="ranking-header">
    <h1>Hala Sław The Abyss</h1>
    <p style="color: #ccc; font-size: 1.1em;">Tylko najlepsi przejdą do historii tego zepsutego miasta.</p>
</div>

<div class="ranking-kontener">
    
    <div class="panel-kategorii">
        <h3 style="color: #888; font-family: 'Oswald'; text-transform: uppercase; margin-top: 0; text-align: center;">Kategorie</h3>
        <?php foreach ($kolumny as $klucz => $dane): ?>
            <a href="game.php?page=ranking&kat=<?php echo $klucz; ?>" class="kat-link <?php if($kategoria == $klucz) echo 'aktywna'; ?>">
                <?php echo $dane['nazwa']; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="panel-tabeli">
        <h2><?php echo $aktywna_kategoria['nazwa']; ?> (Top 50)</h2>
        
        <table class="tabela-rank">
            <tr>
                <th style="width: 10%;"># Miejsce</th>
                <th style="width: 40%;">Obywatel</th>
                <th style="width: 20%;">Klasa</th>
                <th style="width: 30%; text-align: right;">Wynik</th>
            </tr>
            
            <?php
            if ($wynik_rankingu->num_rows > 0) {
                $miejsce = 1;
                while ($w = $wynik_rankingu->fetch_assoc()) {
                    
                    // Style dla miejsc 1-3
                    $klasa_miejsca = "";
                    if ($miejsce == 1) $klasa_miejsca = "pozycja-1";
                    elseif ($miejsce == 2) $klasa_miejsca = "pozycja-2";
                    elseif ($miejsce == 3) $klasa_miejsca = "pozycja-3";
                    
                    // Formatowanie wyświetlania zależnie od kategorii
                    $format_wyniku = number_format($w['wynik'], ($kategoria=='walka_bronia'||$kategoria=='uniki')?2:0, '.', ' ') . ' ' . $aktywna_kategoria['format'];
                    
                    // Oznaczenie wiersza, jeśli to Ty
                    $moj_wiersz = ($w['id'] == $id_gracza) ? "moj-wiersz" : "";
                    
                    echo "<tr class='$moj_wiersz'>";
                    echo "<td class='$klasa_miejsca'>" . $miejsce . "</td>";
                    
                    // Nick jako link do profilu
                    echo "<td>";
                    if ($w['is_premium']) echo "<span style='color:#ffd700;'>★</span> ";
                    echo "<a href='game.php?page=profil&id={$w['id']}' class='nick-link'>{$w['login']}</a>";
                    if ($w['id'] == $id_gracza) echo " <span style='font-size: 0.7em; color: #00ccff; text-transform: uppercase;'>(Ty)</span>";
                    echo "</td>";
                    
                    echo "<td><span style='color:#888;'>" . $w['klasa'] . "</span></td>";
                    echo "<td style='text-align: right;'><span class='wynik-liczba'>" . $format_wyniku . "</span></td>";
                    echo "</tr>";
                    
                    $miejsce++;
                }
            } else {
                echo "<tr><td colspan='4' style='text-align: center; padding: 30px;'>Brak danych w tej kategorii.</td></tr>";
            }
            ?>
        </table>
    </div>
</div>