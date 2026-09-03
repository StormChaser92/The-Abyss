<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — PAGES/WYBOR_POCHODZENIA.PHP
   Ekran jednorazowego wyboru narodowości. game.php przekierowuje tu
   wszystkich graczy, którzy mają pochodzenie IS NULL.
   Wybór jest nieodwracalny.
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config/pochodzenia.php';

$msg_er = '';

// ── Obsługa wyboru ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pochodzenie'])) {
    $wybrane = strtoupper(trim($_POST['pochodzenie']));

    if (!isset($POCHODZENIA_DANE[$wybrane])) {
        $msg_er = 'Nieznane pochodzenie.';
    } elseif (!empty($gracz_r['pochodzenie'])) {
        $msg_er = 'Pochodzenie zostało już wybrane — decyzja jest nieodwracalna.';
    } else {
        $dane        = $POCHODZENIA_DANE[$wybrane];
        $atrybut     = $dane['atrybut_start'];
        $wybrane_esc = $polaczenie->real_escape_string($wybrane);

        // Bonus startowy do atrybutu (jeśli przewidziany)
        $sql_bonus = '';
        if ($atrybut && in_array($atrybut, ['sila','zrecznosc','wytrzymalosc','inteligencja'], true)) {
            $sql_bonus = ", $atrybut = $atrybut + 1";
            // Wytrzymalosc automatycznie daje +10 HP max (spójnie z Twoim systemem awansów)
            if ($atrybut === 'wytrzymalosc') {
                $sql_bonus .= ", hp_max = hp_max + 10, hp_aktualne = hp_aktualne + 10";
            }
        }

        // Specjalne bonusy startowe (ZEA: 3x gotówka)
        if (!empty($dane['start_kasa_mult'])) {
            $mult = (float) $dane['start_kasa_mult'];
            $sql_bonus .= ", gotowka = FLOOR(gotowka * $mult)";
        }

        $polaczenie->query("
            UPDATE gracze SET
                pochodzenie = '$wybrane_esc'
                $sql_bonus
            WHERE id = $id_gracza
        ");

        $_SESSION['powiadomienie_awans'] =
            "Wybrałeś pochodzenie: " . $dane['nazwa_m'] . " " . $dane['flaga'] .
            " · Cecha aktywna: " . $dane['cecha'];
        header("Location: game.php?page=witaj");
        exit;
    }
}
?>

<style>
/* ═══ EKRAN WYBORU POCHODZENIA ═══════════════════════════════════ */
.poch-head{text-align:center;margin-bottom:30px}
.poch-head .hint{
    font-family:'JetBrains Mono',monospace;font-size:.78em;
    color:var(--neon-ember);letter-spacing:3px;margin-bottom:12px;
    text-shadow:0 0 6px rgba(255,122,61,0.4);
}
.poch-head h1{
    font-family:'Oswald',sans-serif;font-weight:500;font-size:2.4em;
    text-transform:uppercase;letter-spacing:4px;color:#fff;
    text-shadow:0 0 20px rgba(255,23,68,0.4);margin-bottom:10px;
}
.poch-head .lead{
    color:var(--txt-dim);font-size:1.05em;max-width:640px;margin:0 auto;line-height:1.5;
}
.poch-head .lead strong{color:var(--neon-red-hot);font-weight:500}

.poch-grid{
    display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
    gap:16px;margin-bottom:26px;
}
.poch-card{
    background:rgba(12,8,14,0.55);
    backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:20px;display:flex;flex-direction:column;
    transition:all .3s;position:relative;
}
.poch-card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:var(--akcent,var(--neon-red));
    box-shadow:0 0 14px var(--akcent,var(--neon-red));
}
.poch-card:hover{
    transform:translateY(-4px);
    border-color:var(--akcent,var(--border-hot));
}

.poch-flaga{
    font-size:3.4em;line-height:1;text-align:center;margin-bottom:10px;
    filter:drop-shadow(0 0 12px var(--akcent,var(--neon-red)));
}
.poch-nazwa{
    font-family:'Oswald',sans-serif;font-weight:600;font-size:1.5em;
    text-transform:uppercase;letter-spacing:3px;color:#fff;
    text-align:center;margin-bottom:4px;
}
.poch-miasto{
    font-family:'JetBrains Mono',monospace;font-size:.72em;
    color:var(--txt-mute);letter-spacing:2px;text-align:center;margin-bottom:14px;
}
.poch-opis{
    font-size:.9em;color:var(--txt-dim);line-height:1.5;
    margin-bottom:14px;text-align:center;font-style:italic;min-height:5em;
}

.poch-cecha{
    background:rgba(0,0,0,0.35);
    border:1px dashed var(--akcent,var(--border-mid));
    padding:10px;border-radius:2px;margin-bottom:10px;
}
.poch-cecha-etyk{
    font-family:'JetBrains Mono',monospace;font-size:.7em;
    color:var(--akcent,var(--neon-red));letter-spacing:2px;
    text-transform:uppercase;margin-bottom:4px;font-weight:500;
}
.poch-cecha-nazwa{
    font-family:'Oswald',sans-serif;font-size:1.1em;
    color:#fff;font-weight:500;letter-spacing:1.5px;margin-bottom:6px;
}
.poch-cecha-opis{
    font-size:.86em;color:var(--txt-dim);line-height:1.45;
}

.poch-synergia{
    font-family:'JetBrains Mono',monospace;font-size:.72em;
    color:var(--neon-gold);letter-spacing:2px;font-weight:500;
    padding:7px 10px;margin-bottom:12px;
    background:rgba(255,215,0,0.06);
    border-left:2px solid var(--neon-gold);
    text-transform:uppercase;text-align:center;
}

.poch-bonusy{list-style:none;padding:0;margin-bottom:14px;flex:1}
.poch-bonusy li{
    font-size:.88em;color:var(--txt-main);line-height:1.5;
    padding:5px 0 5px 18px;position:relative;
    border-bottom:1px dashed rgba(255,255,255,0.05);
}
.poch-bonusy li::before{
    content:'▸';position:absolute;left:0;
    color:var(--akcent,var(--neon-red));
    text-shadow:0 0 6px var(--akcent,var(--neon-red));
}

.poch-submit{
    display:block;width:100%;padding:12px;
    background:linear-gradient(135deg, color-mix(in srgb, var(--akcent,#ff1744) 20%, transparent), color-mix(in srgb, var(--akcent,#ff1744) 35%, transparent));
    border:1px solid var(--akcent,var(--neon-red));color:#fff;
    font-family:'Oswald',sans-serif;font-weight:600;font-size:.95em;
    letter-spacing:3px;text-transform:uppercase;cursor:pointer;
    border-radius:1px;transition:all .25s;text-align:center;
    text-shadow:0 0 8px color-mix(in srgb, var(--akcent,#ff1744) 80%, transparent);
    box-shadow:0 0 14px color-mix(in srgb, var(--akcent,#ff1744) 30%, transparent);
}
.poch-submit:hover{
    background:var(--akcent,var(--neon-red));
    box-shadow:0 0 30px color-mix(in srgb, var(--akcent,#ff1744) 70%, transparent);
    transform:scale(1.02);
}

.poch-alert{
    padding:12px 16px;margin-bottom:18px;border-radius:2px;
    font-family:'JetBrains Mono',monospace;font-size:.88em;letter-spacing:1px;
    border:1px solid #ff3d5e;background:rgba(255,23,68,0.08);color:#ff3d5e;
    backdrop-filter:blur(4px);
}
</style>

<?php if ($msg_er): ?>
<div class="poch-alert">⚠ <?php echo htmlspecialchars($msg_er); ?></div>
<?php endif; ?>

<div class="poch-head">
    <div class="hint">// STEP 2/2 — CHOOSE YOUR BLOODLINE</div>
    <h1>Pochodzenie</h1>
    <p class="lead">
        Twoja krew pamięta ulice, na których się urodziłeś. Skąd przyszedłeś, wpływa na to,
        jak poruszasz się po Otchłani — jakie umiejętności są w Twoim DNA, w jakich miastach
        jesteś u siebie. <strong>Wybór jest jednorazowy i nieodwracalny.</strong>
    </p>
</div>

<div class="poch-grid">
<?php foreach ($POCHODZENIA_DANE as $klucz => $p): ?>
    <div class="poch-card" style="--akcent: <?php echo $p['kolor_akcent']; ?>">
        <div class="poch-flaga"><?php echo $p['flaga']; ?></div>
        <div class="poch-nazwa"><?php echo htmlspecialchars($p['nazwa_m']); ?></div>
        <div class="poch-miasto">// MIASTO RODZINNE: <?php echo htmlspecialchars($p['miasto_startowe']); ?></div>

        <div class="poch-opis">"<?php echo htmlspecialchars($p['opis']); ?>"</div>

        <div class="poch-cecha">
            <div class="poch-cecha-etyk">◆ CECHA UNIKATOWA</div>
            <div class="poch-cecha-nazwa"><?php echo htmlspecialchars($p['cecha']); ?></div>
            <div class="poch-cecha-opis"><?php echo htmlspecialchars($p['opis_cechy']); ?></div>
        </div>

        <div class="poch-synergia">◤ SYNERGIA: <?php echo htmlspecialchars($p['synergia']); ?> ◥</div>

        <ul class="poch-bonusy">
        <?php foreach ($p['bonusy_opis'] as $b): ?>
            <li><?php echo htmlspecialchars($b); ?></li>
        <?php endforeach; ?>
        </ul>

        <form method="post" style="margin:0">
            <input type="hidden" name="pochodzenie" value="<?php echo htmlspecialchars($klucz); ?>">
            <button type="submit" class="poch-submit"
                    onclick="return confirm('Wybierasz pochodzenie: <?php echo htmlspecialchars($p['nazwa_m']); ?>. Decyzja jest NIEODWRACALNA. Kontynuować?');">
                Wybierz pochodzenie
            </button>
        </form>
    </div>
<?php endforeach; ?>
</div>

<div style="font-family:'JetBrains Mono',monospace;font-size:.78em;color:var(--txt-mute);
    text-align:center;padding:16px;border-top:1px solid var(--border-soft);letter-spacing:1.5px">
    // BLOODLINE CANNOT BE CHANGED AFTER SELECTION · CHOOSE WISELY
</div>