<?php
require_once "db.php";
require_once "helpers/vip.php";

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT id, login, gotowka, is_premium, vip_do FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// ════════════════════════════════════════════════════════════════════
// LOGIKA ZAKUPU PAKIETU
// ════════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kup_vip'])) {
    $klucz = isset($_POST['pakiet']) ? trim($_POST['pakiet']) : '';
    $pakiety = vip_pakiety();

    if (!isset($pakiety[$klucz])) {
        $komunikat = "<div class='blad'>Nieznany pakiet — odśwież stronę i spróbuj ponownie.</div>";
    } else {
        $pakiet = $pakiety[$klucz];
        $koszt = (int)$pakiet['koszt'];
        $dni   = (int)$pakiet['dni'];

        if ($gracz['gotowka'] < $koszt) {
            $brakuje = $koszt - $gracz['gotowka'];
            $komunikat = "<div class='blad'>Brak gotówki! Potrzebujesz jeszcze " . number_format($brakuje, 0, '', ' ') . " $.</div>";
        } else {
            // Pobranie kasy i przyznanie VIP-a
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $koszt WHERE id = $id_gracza");
            $nowa_data = dodaj_vip($id_gracza, $dni, 'waluta_growa', $koszt);

            if ($nowa_data === false) {
                // Awaryjny zwrot kasy
                $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $koszt WHERE id = $id_gracza");
                $komunikat = "<div class='blad'>Błąd systemu — pieniądze zwrócone, spróbuj ponownie.</div>";
            } else {
                $komunikat = "<div class='sukces'>★ Status VIP aktywny do <b>" . htmlspecialchars($nowa_data) . "</b>. Witaj w elicie The Abyss.</div>";
                // Odśwież lokalne dane gracza
                $gracz['gotowka'] -= $koszt;
                $gracz['vip_do']   = $nowa_data;
                $gracz['is_premium'] = 1;
            }
        }
    }
}

// Aktualne info o statusie
$ma_vip = czy_vip($gracz);
$dni_pozostalo = vip_pozostalo_dni($gracz);
$pakiety = vip_pakiety();

// Historia zakupów (top 10)
$historia = $polaczenie->query("
    SELECT pakiet_dni, koszt, metoda, data_zakupu, vip_do_po_zakupie
    FROM vip_historia
    WHERE gracz_id = $id_gracza
    ORDER BY data_zakupu DESC
    LIMIT 10
");
?>

<style>
    /* ═══════════════════════════════════════════════════════════════
       PREMIUM — ekran kupna VIP
       Klimat: luksus, złoto, czerń, neon, palace marmurowy.
    ═══════════════════════════════════════════════════════════════ */

    .premium-header {
        background:
          linear-gradient(135deg, rgba(0,0,0,0.92), rgba(20,15,5,0.85)),
          radial-gradient(ellipse at top right, rgba(255,215,0,0.18), transparent 60%);
        border: 1px solid #ffd700;
        border-radius: 6px;
        padding: 50px 40px;
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
        text-align: center;
    }
    .premium-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: repeating-linear-gradient(
          45deg,
          transparent 0,
          transparent 20px,
          rgba(255,215,0,0.025) 20px,
          rgba(255,215,0,0.025) 22px
        );
        pointer-events: none;
    }
    .premium-header h1 {
        font-family: 'Oswald', sans-serif;
        color: #ffd700;
        font-size: 3.2em;
        margin: 0 0 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-shadow: 0 0 25px rgba(255,215,0,0.4), 0 2px 4px rgba(0,0,0,0.8);
        position: relative;
    }
    .premium-header .podtytul {
        color: #d4c89c;
        font-family: 'Cormorant Garamond', serif;
        font-style: italic;
        font-size: 1.3em;
        margin: 0;
        position: relative;
    }
    .premium-header .gwiazdy {
        color: #ffd700;
        font-size: 1.8em;
        letter-spacing: 0.4em;
        margin-bottom: 8px;
        position: relative;
    }

    /* Status box */
    .vip-status-box {
        background: linear-gradient(to right, #0a0a0a, #1a1505 50%, #0a0a0a);
        border: 1px solid #ffd700;
        border-radius: 4px;
        padding: 25px 30px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .vip-status-box.brak {
        border-color: #444;
        background: linear-gradient(to right, #0a0a0a, #1a1a1a 50%, #0a0a0a);
    }
    .vip-status-aktywny {
        font-family: 'Oswald', sans-serif;
        color: #ffd700;
        font-size: 1.5em;
        text-transform: uppercase;
    }
    .vip-status-brak {
        font-family: 'Oswald', sans-serif;
        color: #888;
        font-size: 1.3em;
        text-transform: uppercase;
    }
    .vip-status-data {
        color: #d4c89c;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.95em;
    }
    .vip-status-gotowka {
        color: #00ff00;
        font-family: 'Oswald', sans-serif;
        font-size: 1.5em;
    }

    /* Lista pakietów */
    .pakiety-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .pakiet-karta {
        background: linear-gradient(180deg, #0a0a0a 0%, #060606 100%);
        border: 1px solid #333;
        border-radius: 6px;
        padding: 25px 22px;
        text-align: center;
        position: relative;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
    }
    .pakiet-karta:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(255,215,0,0.15);
    }
    .pakiet-karta.polecany {
        border-color: #4ad6ff;
        box-shadow: 0 0 20px rgba(74, 214, 255, 0.15) inset;
    }
    .pakiet-karta.najlepsza {
        border-color: #ffd700;
        box-shadow: 0 0 25px rgba(255, 215, 0, 0.2) inset;
    }
    .pakiet-banner {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: #ffd700;
        color: #000;
        font-family: 'Oswald', sans-serif;
        font-weight: bold;
        font-size: 0.75em;
        padding: 4px 14px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border-radius: 2px;
        white-space: nowrap;
    }
    .pakiet-banner.cyan { background: #4ad6ff; color: #000; }
    .pakiet-nazwa {
        font-family: 'Oswald', sans-serif;
        font-size: 1.4em;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
    }
    .pakiet-czas {
        font-family: 'Oswald', sans-serif;
        font-size: 3em;
        line-height: 1;
        margin-bottom: 4px;
    }
    .pakiet-czas-jednostka {
        color: #888;
        font-size: 0.9em;
        text-transform: uppercase;
        margin-bottom: 18px;
    }
    .pakiet-cena {
        font-family: 'Oswald', sans-serif;
        font-size: 1.8em;
        color: #00ff00;
        margin: 12px 0 6px;
    }
    .pakiet-na-dzien {
        color: #666;
        font-size: 0.8em;
        font-family: 'JetBrains Mono', monospace;
        margin-bottom: 16px;
    }
    .pakiet-opis {
        color: #aaa;
        font-size: 0.85em;
        line-height: 1.5;
        margin-bottom: 18px;
        font-family: 'Cormorant Garamond', serif;
        font-style: italic;
        flex-grow: 1;
    }
    .btn-kup-vip {
        background: transparent;
        color: #ffd700;
        border: 1px solid #ffd700;
        padding: 12px 18px;
        font-family: 'Oswald', sans-serif;
        font-size: 1em;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        cursor: pointer;
        border-radius: 3px;
        transition: 0.3s;
        width: 100%;
    }
    .btn-kup-vip:hover {
        background: #ffd700;
        color: #000;
        box-shadow: 0 0 15px rgba(255,215,0,0.4);
    }
    .btn-kup-vip:disabled {
        background: transparent;
        color: #ff3333;
        border-color: #ff3333;
        cursor: not-allowed;
    }

    /* Sekcja perków */
    .perki-panel {
        background: #0a0a0a;
        border: 1px solid #333;
        border-radius: 5px;
        padding: 30px;
        margin-bottom: 25px;
    }
    .perki-panel h2 {
        font-family: 'Oswald', sans-serif;
        color: #ffd700;
        text-transform: uppercase;
        margin: 0 0 20px;
        border-bottom: 1px dashed #333;
        padding-bottom: 12px;
        letter-spacing: 0.05em;
    }
    .perki-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 14px;
    }
    .perk {
        background: rgba(255,215,0,0.04);
        border-left: 3px solid #ffd700;
        padding: 12px 16px;
        color: #ddd;
        font-size: 0.95em;
        line-height: 1.4;
    }
    .perk b {
        color: #ffd700;
        font-family: 'Oswald', sans-serif;
        text-transform: uppercase;
        font-size: 0.92em;
        letter-spacing: 0.04em;
    }
    .perk .soon {
        color: #888;
        font-size: 0.78em;
        font-style: italic;
        margin-left: 6px;
    }

    /* Historia */
    .historia-panel {
        background: #0a0a0a;
        border: 1px solid #222;
        border-radius: 5px;
        padding: 25px;
        margin-bottom: 25px;
    }
    .historia-panel h2 {
        font-family: 'Oswald', sans-serif;
        color: #d4c89c;
        text-transform: uppercase;
        margin: 0 0 18px;
        border-bottom: 1px dashed #2a2418;
        padding-bottom: 10px;
        letter-spacing: 0.04em;
    }
    .historia-tabela {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9em;
    }
    .historia-tabela th {
        color: #888;
        text-transform: uppercase;
        font-size: 0.75em;
        letter-spacing: 0.05em;
        padding: 8px 10px;
        border-bottom: 1px solid #222;
        text-align: left;
    }
    .historia-tabela td {
        color: #bbb;
        padding: 10px;
        border-bottom: 1px dashed #1a1a1a;
        font-family: 'JetBrains Mono', monospace;
    }
    .historia-empty {
        color: #555;
        text-align: center;
        padding: 25px;
        font-style: italic;
    }

    /* Komunikaty */
    .sukces {
        background: rgba(255,215,0,0.08);
        border: 1px solid #ffd700;
        color: #ffd700;
        padding: 15px 20px;
        border-radius: 4px;
        margin-bottom: 20px;
        text-align: center;
    }
    .blad {
        background: rgba(255, 51, 51, 0.1);
        border: 1px solid #ff3333;
        color: #ff5555;
        padding: 15px 20px;
        border-radius: 4px;
        margin-bottom: 20px;
        text-align: center;
    }
</style>

<div class="premium-header">
    <div class="gwiazdy">★ ★ ★</div>
    <h1>Klub Premium</h1>
    <p class="podtytul">"W Manhattanie liczą się tylko ci, którzy na siebie pracują. Reszta tylko płaci za drinki."</p>
</div>

<?php echo $komunikat; ?>

<!-- ═══════════════════════════════════════════════════════════════
     STATUS GRACZA
═══════════════════════════════════════════════════════════════ -->
<div class="vip-status-box <?php if (!$ma_vip) echo 'brak'; ?>">
    <div>
        <?php if ($ma_vip): ?>
            <div class="vip-status-aktywny">★ Status VIP aktywny</div>
            <div class="vip-status-data">
                Wygasa: <b style="color:#ffd700;"><?php echo htmlspecialchars(date('d.m.Y, H:i', strtotime($gracz['vip_do']))); ?></b>
                <?php if ($dni_pozostalo > 1): ?>
                    &nbsp;•&nbsp; pozostało <b style="color:#fff;"><?php echo $dni_pozostalo; ?> dni</b>
                <?php elseif ($dni_pozostalo == 1): ?>
                    &nbsp;•&nbsp; <b style="color:#ff7a3d;">ostatni dzień!</b>
                <?php else: ?>
                    &nbsp;•&nbsp; <b style="color:#ff3333;">kończy się w ciągu godziny!</b>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="vip-status-brak">Brak statusu VIP</div>
            <div class="vip-status-data" style="color:#888; font-style: italic;">
                Wybierz pakiet poniżej, aby dołączyć do elity.
            </div>
        <?php endif; ?>
    </div>
    <div class="vip-status-gotowka">
        <div style="font-size: 0.55em; color:#888; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px;">Twoja gotówka</div>
        <?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     PAKIETY DO KUPNA
═══════════════════════════════════════════════════════════════ -->
<div class="pakiety-grid">
    <?php foreach ($pakiety as $klucz => $p):
        $cena_na_dzien = round($p['koszt'] / $p['dni']);
        $stac_go = ($gracz['gotowka'] >= $p['koszt']);
        $karta_klasa = '';
        if (!empty($p['polecany'])) $karta_klasa = 'polecany';
        if (!empty($p['najlepsza_oferta'])) $karta_klasa = 'najlepsza';
    ?>
        <div class="pakiet-karta <?php echo $karta_klasa; ?>">
            <?php if (!empty($p['najlepsza_oferta'])): ?>
                <div class="pakiet-banner">★ Najlepsza Oferta</div>
            <?php elseif (!empty($p['polecany'])): ?>
                <div class="pakiet-banner cyan">Polecany</div>
            <?php endif; ?>

            <div class="pakiet-nazwa" style="color: <?php echo $p['kolor']; ?>;">
                <?php echo htmlspecialchars($p['nazwa']); ?>
            </div>

            <div class="pakiet-czas" style="color: <?php echo $p['kolor']; ?>;">
                <?php echo $p['dni']; ?>
            </div>
            <div class="pakiet-czas-jednostka">
                <?php echo ($p['dni'] == 1) ? 'dzień' : (($p['dni'] < 5) ? 'dni' : 'dni'); ?>
            </div>

            <div class="pakiet-cena">
                <?php echo number_format($p['koszt'], 0, '', ' '); ?> $
            </div>
            <div class="pakiet-na-dzien">
                ≈ <?php echo number_format($cena_na_dzien, 0, '', ' '); ?> $/dzień
            </div>

            <div class="pakiet-opis">
                <?php echo htmlspecialchars($p['opis']); ?>
            </div>

            <form method="POST" onsubmit="return confirm('Kupić pakiet <?php echo htmlspecialchars($p['nazwa']); ?> za <?php echo number_format($p['koszt'], 0, '', ' '); ?> $?');">
                <input type="hidden" name="pakiet" value="<?php echo htmlspecialchars($klucz); ?>">
                <button type="submit" name="kup_vip" class="btn-kup-vip" <?php if (!$stac_go) echo 'disabled'; ?>>
                    <?php if ($stac_go): ?>
                        Wykup status
                    <?php else: ?>
                        Brak gotówki
                    <?php endif; ?>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     LISTA PERKÓW
═══════════════════════════════════════════════════════════════ -->
<div class="perki-panel">
    <h2>★ Co daje status VIP?</h2>
    <div class="perki-grid">
        <div class="perk">
            <b>Możliwość założenia firmy</b><br>
            Wymagane do otwarcia legalnego biznesu w Urzędzie Miasta.
        </div>
        <div class="perk">
            <b>★ Złota gwiazdka przy nicku</b><br>
            Widoczna w listach graczy, czacie klubu, profilu i rankingach.
        </div>
        <div class="perk">
            <b>+1 zlecenie dziennie</b><br>
            Dzienny limit zleceń wzrasta z 5 do 6.
        </div>
        <div class="perk">
            <b>+20% Punktów Pracowniczych</b><span class="soon">[wkrótce]</span><br>
            Maksimum PP rośnie ze 100 do 120 — szybciej realizujesz zlecenia firmy.
        </div>
        <div class="perk">
            <b>Specjalny kolor wiadomości</b><span class="soon">[wkrótce]</span><br>
            Złoty cień Twoich postów w czacie klubu.
        </div>
        <div class="perk">
            <b>Stroje premium w garderobie</b><span class="soon">[wkrótce]</span><br>
            Dostęp do limitowanej kolekcji w klubie The Abyss.
        </div>
        <div class="perk">
            <b>Priorytet w kolejce DJ-a</b><span class="soon">[wkrótce]</span><br>
            Twoje zamówienia muzyki wskakują wyżej.
        </div>
        <div class="perk">
            <b>Tablica VIP w mieście</b><span class="soon">[wkrótce]</span><br>
            Osobny ranking — kto najdłużej utrzymuje status.
        </div>
    </div>
    <p style="color: #666; font-style: italic; margin-top: 20px; text-align: center; font-family: 'Cormorant Garamond', serif;">
        Status VIP nie kupuje przewagi w walce ani siły postaci — kupuje styl, wygodę i drzwi do biznesu.
    </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     HISTORIA ZAKUPÓW
═══════════════════════════════════════════════════════════════ -->
<div class="historia-panel">
    <h2>Historia zakupów (10 ostatnich)</h2>
    <?php if ($historia && $historia->num_rows > 0): ?>
        <table class="historia-tabela">
            <thead>
                <tr>
                    <th>Data zakupu</th>
                    <th>Pakiet</th>
                    <th>Koszt</th>
                    <th>Metoda</th>
                    <th>Wygasa</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($h = $historia->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($h['data_zakupu']); ?></td>
                        <td style="color: #ffd700;"><?php echo (int)$h['pakiet_dni']; ?> dni</td>
                        <td style="color: #00ff00;"><?php echo number_format($h['koszt'], 0, '', ' '); ?> $</td>
                        <td style="color: #888; font-style: italic;">
                            <?php
                            $m = $h['metoda'];
                            $etykiety = [
                                'waluta_growa' => 'gotówka',
                                'przelewy24'   => 'Przelewy24',
                                'patreon'      => 'Patreon',
                                'admin'        => 'admin',
                                'gift'         => 'prezent',
                                'kompensacja'  => 'rekompensata',
                            ];
                            echo htmlspecialchars($etykiety[$m] ?? $m);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($h['vip_do_po_zakupie']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="historia-empty">Nie kupiłeś jeszcze żadnego pakietu VIP.</div>
    <?php endif; ?>
</div>