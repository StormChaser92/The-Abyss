<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — PAGES/LOTNISKO.PHP
   Podróże międzynarodowe. Wkleja się jak każda inna pages/*.php —
   ładowana automatycznie przez game.php.
   ═══════════════════════════════════════════════════════════════════════ */

// ── Dane miast + pochodzenia (globalne katalogi) ─────────────────────
require_once __DIR__ . '/../config/miasta.php';
require_once __DIR__ . '/../config/pochodzenia.php';

// $gracz_r, $id_gracza, $polaczenie są już dostępne z game.php
$obecne      = strtoupper($gracz_r['obecne_miasto'] ?? 'NEW YORK');
$obecne_dane = $MIASTA_DANE[$obecne] ?? $MIASTA_DANE['NEW YORK'];

// Bonus pochodzenia (np. Włoch −10% kasy za lot)
$lot_rabat = pochodzenie_bonus($gracz_r, 'lot_kasa_mult', 1.0);

// ── OBSŁUGA BOOKINGU LOTU ────────────────────────────────────────────
$msg_ok = '';
$msg_er = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cel'])) {
    $cel = strtoupper(trim($_POST['cel']));

    if (!isset($MIASTA_DANE[$cel])) {
        $msg_er = "Nieznana destynacja.";
    } elseif ($cel === $obecne) {
        $msg_er = "Jesteś już w tym mieście, operatorze.";
    } else {
        $cel_dane      = $MIASTA_DANE[$cel];
        $ten_sam_kraj  = ($cel_dane['kraj'] === $obecne_dane['kraj']);
        $km            = round(km_odleglosc(
            $obecne_dane['lat'], $obecne_dane['lng'],
            $cel_dane['lat'],    $cel_dane['lng']
        ));
        $koszt = koszt_lotu($km, $ten_sam_kraj);
        $koszt['kasa'] = max(250, (int)round($koszt['kasa'] * $lot_rabat));

        if ($gracz_r['gotowka'] < $koszt['kasa']) {
            $msg_er = "Nie masz wystarczająco gotówki. Potrzeba: "
                    . number_format($koszt['kasa'], 0, '', ' ') . " \$.";
        } elseif ($gracz_r['energia_aktualna'] < $koszt['energia']) {
            $msg_er = "Za mało energii. Potrzeba: {$koszt['energia']} EN.";
        } else {
            // ── WSZYSTKO OK — WYKONAJ LOT ──
            $cel_esc = $polaczenie->real_escape_string($cel);
            $polaczenie->query("
                UPDATE gracze SET
                    obecne_miasto    = '$cel_esc',
                    gotowka          = gotowka - {$koszt['kasa']},
                    energia_aktualna = energia_aktualna - {$koszt['energia']},
                    ostatni_lot      = NOW()
                WHERE id = $id_gracza
            ");

            // Log lotu (jeśli tabela istnieje — jest bezpieczny fallback)
            $z_esc = $polaczenie->real_escape_string($obecne);
            @$polaczenie->query("
                INSERT INTO loty_historia (gracz_id, z_miasta, do_miasta, dystans_km, koszt, energia)
                VALUES ($id_gracza, '$z_esc', '$cel_esc', $km, {$koszt['kasa']}, {$koszt['energia']})
            ");

            // Odświeżamy obecną lokalizację — przekieruj, żeby topbar/pogoda się przeładowały
            $_SESSION['powiadomienie_lot'] =
                "✈ Wylądowałeś w mieście {$cel}. "
                . "Dystans: " . number_format($km, 0, '', ' ') . " km. "
                . "Koszt: " . number_format($koszt['kasa'], 0, '', ' ') . " \$, "
                . "−{$koszt['energia']} EN.";
            header("Location: game.php?page=lotnisko");
            exit;
        }
    }
}

// ── Pogrupowane destynacje ──────────────────────────────────────────
$grupy = grupuj_miasta_po_kraju($MIASTA_DANE);

// Sortowanie: najpierw kraj gracza (łatwe krajowe loty u góry), reszta alfabetycznie
uksort($grupy, function($a, $b) use ($obecne_dane) {
    if ($a === $obecne_dane['kraj']) return -1;
    if ($b === $obecne_dane['kraj']) return  1;
    return strcmp($a, $b);
});
?>

<!-- ══════════════════════════════════════════════════════════════════
     STYLE — lokalne dla lotniska (dokleja się do game.php, ale nazwy
     klas są zawężone prefixem `.lot-` żeby nic nie ruszyć globalnie)
════════════════════════════════════════════════════════════════════ -->
<style>
.lot-hero{
    background:rgba(0,0,0,0.42);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:26px 30px;margin-bottom:22px;position:relative;overflow:hidden;
}
.lot-hero::before{
    content:'';position:absolute;top:0;left:0;width:160px;height:1px;
    background:var(--neon-red);box-shadow:0 0 12px var(--neon-red);
}
.lot-hero::after{
    content:'// FLIGHT BOARD · AUTH ' attr(data-shard);
    position:absolute;top:12px;right:24px;
    font-family:'JetBrains Mono',monospace;font-size:.72em;
    color:var(--neon-red);letter-spacing:3px;opacity:.8;
    text-shadow:0 0 6px rgba(255,23,68,0.4);
}
.lot-hero h2{
    font-family:'Oswald',sans-serif;font-weight:500;
    font-size:2em;text-transform:uppercase;letter-spacing:4px;
    color:#fff;margin-bottom:4px;
    text-shadow:0 0 18px rgba(255,23,68,0.4);
}
.lot-hero .sub{
    font-family:'JetBrains Mono',monospace;font-size:.8em;
    color:var(--txt-dim);letter-spacing:2px;margin-bottom:12px;
}
.lot-hero .current{
    display:flex;align-items:center;gap:14px;flex-wrap:wrap;
    padding:14px;margin-top:10px;
    background:rgba(255,23,68,0.06);border:1px dashed var(--border-mid);
    border-radius:2px;
}
.lot-hero .current .flag{font-size:2.2em;line-height:1;filter:drop-shadow(0 0 8px rgba(255,23,68,0.3))}
.lot-hero .current .info{flex:1}
.lot-hero .current .loc{
    font-family:'Oswald',sans-serif;font-size:1.2em;color:#fff;
    letter-spacing:2px;text-transform:uppercase;
}
.lot-hero .current .desc{color:var(--txt-dim);font-size:.92em;margin-top:3px}
.lot-hero .current .meta{
    font-family:'JetBrains Mono',monospace;font-size:.78em;
    color:var(--neon-ember);letter-spacing:1.5px;
}

/* Alerty */
.lot-alert{
    padding:12px 16px;margin-bottom:18px;border-radius:2px;
    font-family:'JetBrains Mono',monospace;font-size:.88em;letter-spacing:1px;
    border:1px solid;backdrop-filter:blur(4px);
}
.lot-alert.ok{border-color:#5aff9a;background:rgba(90,255,154,0.08);color:#5aff9a}
.lot-alert.er{border-color:#ff3d5e;background:rgba(255,23,68,0.08);color:#ff3d5e}

/* Grupa krajów */
.lot-country{
    margin-bottom:20px;
    background:rgba(12,8,14,0.5);
    border:1px solid var(--border-soft);border-radius:2px;
    overflow:hidden;
}
.lot-country-head{
    display:flex;align-items:center;gap:14px;
    padding:12px 16px;
    background:linear-gradient(90deg, rgba(255,23,68,0.12), rgba(255,23,68,0.02));
    border-bottom:1px solid var(--border-soft);
    font-family:'Oswald',sans-serif;font-weight:500;font-size:1.1em;
    letter-spacing:3px;text-transform:uppercase;color:#fff;
}
.lot-country-head .flag{font-size:1.5em;line-height:1}
.lot-country-head .name{flex:1}
.lot-country-head .hint{
    font-family:'JetBrains Mono',monospace;font-size:.7em;
    color:var(--neon-ember);letter-spacing:2px;font-weight:400;
}

.lot-flights{display:grid;grid-template-columns:1fr 1fr;gap:0}
.lot-flight{
    padding:16px;
    border-right:1px solid var(--border-soft);
    border-top:1px solid var(--border-soft);
    background:rgba(0,0,0,0.25);transition:all .25s;position:relative;
}
.lot-flights .lot-flight:nth-child(2n){border-right:0}
.lot-flight:hover{background:rgba(255,23,68,0.05)}

.lot-flight.here{
    background:linear-gradient(135deg, rgba(255,215,0,0.08), rgba(255,122,61,0.04));
}
.lot-flight.here::before{
    content:'◆ AKTUALNA LOKACJA';
    position:absolute;top:8px;right:12px;
    font-family:'JetBrains Mono',monospace;font-size:.7em;
    color:var(--neon-gold);letter-spacing:2px;
    text-shadow:0 0 6px rgba(255,215,0,0.6);
}

.lot-flight .city{
    font-family:'Oswald',sans-serif;font-size:1.25em;font-weight:500;
    color:#fff;letter-spacing:2px;text-transform:uppercase;margin-bottom:4px;
    display:flex;align-items:center;gap:8px;
}
.lot-flight .city .shard{
    font-family:'JetBrains Mono',monospace;font-size:.55em;
    color:var(--txt-mute);letter-spacing:2px;font-weight:400;
}
.lot-flight .desc{
    color:var(--txt-dim);font-size:.95em;line-height:1.45;
    margin-bottom:12px;min-height:2.9em;
}

.lot-costs{
    display:grid;grid-template-columns:repeat(3,1fr);gap:8px;
    padding:10px 0;margin-bottom:12px;
    border-top:1px dashed rgba(255,23,68,0.15);
    border-bottom:1px dashed rgba(255,23,68,0.15);
    font-family:'JetBrains Mono',monospace;font-size:.8em;
}
.lot-costs .cell{text-align:center}
.lot-costs .cell .lbl{color:var(--txt-mute);font-size:.85em;display:block;letter-spacing:1px}
.lot-costs .cell .v{color:#fff;font-weight:500;display:block;margin-top:2px}
.lot-costs .cell .v.money{color:var(--neon-ember);text-shadow:0 0 4px rgba(255,122,61,0.4)}
.lot-costs .cell .v.en{color:var(--neon-cyan);text-shadow:0 0 4px rgba(74,214,255,0.4)}
.lot-costs .cell.domestic .v.money{color:#5aff9a;text-shadow:0 0 4px rgba(90,255,154,0.4)}

.lot-book{
    display:block;width:100%;
    padding:10px;background:rgba(255,23,68,0.1);
    border:1px solid var(--border-mid);color:#fff;
    font-family:'Oswald',sans-serif;font-weight:500;font-size:.9em;
    letter-spacing:3px;text-transform:uppercase;cursor:pointer;
    border-radius:1px;transition:all .25s;text-align:center;
}
.lot-book:hover:not(:disabled){
    background:var(--neon-red);
    box-shadow:0 0 18px rgba(255,23,68,0.7);
    text-shadow:0 0 8px rgba(255,255,255,0.8);
}
.lot-book:disabled{
    background:rgba(0,0,0,0.3);border-color:rgba(255,255,255,0.08);
    color:var(--txt-mute);cursor:not-allowed;
}
.lot-book.cant-afford{
    border-color:rgba(255,61,94,0.3);color:#ff6678;
}

@media (max-width:720px){
    .lot-flights{grid-template-columns:1fr}
    .lot-flights .lot-flight:nth-child(2n){border-right:0}
    .lot-flight{border-right:0!important}
}
</style>

<?php
// Komunikat po wylądowaniu (z sesji po redirect)
if (isset($_SESSION['powiadomienie_lot'])) {
    echo "<div class='lot-alert ok'>" . htmlspecialchars($_SESSION['powiadomienie_lot']) . "</div>";
    unset($_SESSION['powiadomienie_lot']);
}
if ($msg_er) echo "<div class='lot-alert er'>" . htmlspecialchars($msg_er) . "</div>";
?>

<!-- HERO: aktualna lokalizacja + branding -->
<div class="lot-hero" data-shard="<?php echo htmlspecialchars($obecne_dane['shard']); ?>">
    <h2>✈ Port Lotniczy</h2>
    <div class="sub">// GLOBAL TRANSIT NETWORK · 32 DESTINATIONS · 16 COUNTRIES</div>

    <div class="current">
        <span class="flag"><?php echo $obecne_dane['flaga']; ?></span>
        <div class="info">
            <div class="loc"><?php echo htmlspecialchars($obecne); ?> · <?php echo htmlspecialchars($obecne_dane['kraj']); ?></div>
            <div class="desc"><?php echo htmlspecialchars($obecne_dane['opis']); ?></div>
        </div>
        <div class="meta">
            SHARD <?php echo htmlspecialchars($obecne_dane['shard']); ?><br>
            <?php echo $obecne_dane['lat']; ?>°, <?php echo $obecne_dane['lng']; ?>°
        </div>
    </div>
</div>

<!-- LISTA DESTYNACJI POGRUPOWANA PO KRAJU -->
<?php foreach ($grupy as $nazwa_kraj => $gr):
    $miasta_kraju    = $gr['miasta'];
    $to_twoj_kraj    = ($nazwa_kraj === $obecne_dane['kraj']);
?>

<div class="lot-country">
    <div class="lot-country-head">
        <span class="flag"><?php echo $gr['flaga']; ?></span>
        <span class="name"><?php echo htmlspecialchars($nazwa_kraj); ?></span>
        <?php if ($to_twoj_kraj): ?>
            <span class="hint">// ROUTES -35% (DOMESTIC)</span>
        <?php endif; ?>
    </div>

    <div class="lot-flights">
    <?php foreach ($miasta_kraju as $nazwa_m => $m):
        $to_obecne = ($nazwa_m === $obecne);
        $km        = round(km_odleglosc(
            $obecne_dane['lat'], $obecne_dane['lng'],
            $m['lat'], $m['lng']
        ));
        $koszt     = koszt_lotu($km, $to_twoj_kraj);
        $koszt['kasa'] = max(250, (int)round($koszt['kasa'] * $lot_rabat));
        $stac_na_kase    = ($gracz_r['gotowka'] >= $koszt['kasa']);
        $stac_na_energie = ($gracz_r['energia_aktualna'] >= $koszt['energia']);
        $stac            = $stac_na_kase && $stac_na_energie && !$to_obecne;
    ?>
        <div class="lot-flight <?php echo $to_obecne ? 'here' : ''; ?>">
            <div class="city">
                <span><?php echo htmlspecialchars($nazwa_m); ?></span>
                <span class="shard"><?php echo htmlspecialchars($m['shard']); ?></span>
            </div>
            <div class="desc"><?php echo htmlspecialchars($m['opis']); ?></div>

            <?php if ($to_obecne): ?>
                <div class="lot-costs">
                    <div class="cell"><span class="lbl">Status</span><span class="v" style="color:var(--neon-gold)">TU JESTEŚ</span></div>
                    <div class="cell"><span class="lbl">Dystans</span><span class="v">0 km</span></div>
                    <div class="cell"><span class="lbl">Shard</span><span class="v"><?php echo htmlspecialchars($m['shard']); ?></span></div>
                </div>
                <button class="lot-book" disabled>— AKTUALNA LOKACJA —</button>
            <?php else: ?>
                <div class="lot-costs">
                    <div class="cell <?php echo $to_twoj_kraj ? 'domestic' : ''; ?>">
                        <span class="lbl">Koszt</span>
                        <span class="v money"><?php echo number_format($koszt['kasa'], 0, '', ' '); ?> $</span>
                    </div>
                    <div class="cell">
                        <span class="lbl">Energia</span>
                        <span class="v en"><?php echo $koszt['energia']; ?> EN</span>
                    </div>
                    <div class="cell">
                        <span class="lbl">Dystans</span>
                        <span class="v"><?php echo number_format($km, 0, '', ' '); ?> km</span>
                    </div>
                </div>

                <form method="post" style="margin:0">
                    <input type="hidden" name="cel" value="<?php echo htmlspecialchars($nazwa_m); ?>">
                    <?php if ($stac): ?>
                        <button type="submit" class="lot-book"
                                onclick="return confirm('Lecisz do <?php echo htmlspecialchars($nazwa_m); ?>? Kosztuje <?php echo number_format($koszt['kasa'], 0, '', ' '); ?>$ i <?php echo $koszt['energia']; ?> EN.');">
                            ◤ Zarezerwuj Lot ◥
                        </button>
                    <?php elseif (!$stac_na_kase): ?>
                        <button type="button" class="lot-book cant-afford" disabled>
                            ✕ BRAK KASY (<?php echo number_format($koszt['kasa'] - $gracz_r['gotowka'], 0, '', ' '); ?> $ brakuje)
                        </button>
                    <?php else: ?>
                        <button type="button" class="lot-book cant-afford" disabled>
                            ✕ BRAK ENERGII (<?php echo $koszt['energia'] - $gracz_r['energia_aktualna']; ?> EN brakuje)
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<?php endforeach; ?>

<!-- STOPKA Z INFORMACJAMI -->
<div style="
    font-family:'JetBrains Mono',monospace;font-size:.8em;color:var(--txt-mute);
    padding:20px;margin-top:14px;text-align:center;letter-spacing:1.5px;
    background:rgba(0,0,0,0.25);border:1px dashed var(--border-soft);border-radius:2px;">
    // ZMIANA MIASTA AKTUALIZUJE: POGODA · SHARD · KLIMAT · LOKALNE RYNKI<br>
    // LOTY W OBRĘBIE TEGO SAMEGO KRAJU: −35% RABATU<br>
    // WSZYSTKIE KOSZTY POBIERANE W GOTÓWCE + ENERGII · BRAK ZWROTU
</div>