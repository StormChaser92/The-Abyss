<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// ZAPIS KLASY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wybierz_klase'])) {
    $klasa = $_POST['klasa_wyb'];
    if (in_array($klasa, ['Egzekutor','Szabrownik','Inżynier'])) {
        $k = $polaczenie->real_escape_string($klasa);
        $polaczenie->query("UPDATE gracze SET klasa='$k' WHERE id=$id_gracza");
        echo "<script>location.href='game.php?page=witaj';</script>"; exit;
    }
}

// DANE GRACZA
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$klasa = $gracz['klasa'];

// OSTATNIE AKTYWNOŚCI (ostatnie 5 powiadomień)
$alerty = $polaczenie->query(
    "SELECT tresc, data_dodania FROM powiadomienia
     WHERE gracz_id=$id_gracza ORDER BY data_dodania DESC LIMIT 5"
);
?>
<style>
/* ══════════════════════════════════════════════════════════════════
   WITAJ.PHP — CYBERPUNK NYC
   Klasy: czerwony (Egzekutor), ember (Szabrownik), cyan (Inżynier)
══════════════════════════════════════════════════════════════════ */

/* ── WSPÓLNE ─────────────────────────────────────────────────── */
.w-tytul{
    font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:3px;
    margin:0 0 6px;font-size:2.6em;color:#fff;line-height:1;
    text-shadow:0 0 20px rgba(255,23,68,0.3);
}
.w-sub{
    color:var(--neon-red);font-size:.75em;font-family:'JetBrains Mono',monospace;
    letter-spacing:4px;text-transform:uppercase;margin-bottom:32px;
    text-shadow:0 0 6px rgba(255,23,68,0.5);
}

/* ══ KREATOR KLASY ═══════════════════════════════════════════════ */
.kreator-wrap{
    min-height:70vh;display:flex;flex-direction:column;
    align-items:center;justify-content:center;padding:20px 0;
}
.kreator-alert{
    background:rgba(255,23,68,0.08);
    border:1px solid var(--border-mid);
    color:var(--neon-red-hot);padding:14px 24px;border-radius:2px;
    text-align:center;font-family:'Oswald',sans-serif;font-size:1em;
    letter-spacing:1.5px;text-transform:uppercase;margin-bottom:36px;
    max-width:700px;width:100%;
    box-shadow:0 0 25px rgba(255,23,68,0.15);
}
.kreator-alert::before{content:'⚠ ';font-weight:700}

.klasy-grid{
    display:grid;grid-template-columns:repeat(3,1fr);gap:20px;
    width:100%;max-width:980px;
}

/* KARTA KLASY */
.kk{
    background:rgba(10,6,12,0.65);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;padding:30px 24px;
    display:flex;flex-direction:column;align-items:center;
    text-align:center;gap:14px;
    transition:transform .3s,box-shadow .3s,border-color .3s;
    position:relative;overflow:hidden;cursor:pointer;
}
.kk::before{
    content:'';position:absolute;top:0;left:0;right:0;height:2px;
    opacity:0;transition:opacity .4s;
}
.kk::after{
    content:'';position:absolute;inset:0;border-radius:2px;
    opacity:0;transition:opacity .4s;pointer-events:none;
}
.kk:hover{transform:translateY(-6px)}
.kk:hover::before,.kk:hover::after{opacity:1}

/* EGZEKUTOR - czerwony */
.kk-egz::before{background:linear-gradient(90deg,transparent,var(--neon-red),transparent);box-shadow:0 0 10px var(--neon-red)}
.kk-egz::after{background:radial-gradient(ellipse at 50% 0%,rgba(255,23,68,0.2),transparent 70%)}
.kk-egz:hover{border-color:var(--border-hot);box-shadow:0 0 40px rgba(255,23,68,0.25),0 12px 30px rgba(0,0,0,0.6)}

/* SZABROWNIK - ember */
.kk-szb::before{background:linear-gradient(90deg,transparent,var(--neon-ember),transparent);box-shadow:0 0 10px var(--neon-ember)}
.kk-szb::after{background:radial-gradient(ellipse at 50% 0%,rgba(255,122,61,0.2),transparent 70%)}
.kk-szb:hover{border-color:rgba(255,122,61,0.55);box-shadow:0 0 40px rgba(255,122,61,0.25),0 12px 30px rgba(0,0,0,0.6)}

/* INŻYNIER - cyan */
.kk-inz::before{background:linear-gradient(90deg,transparent,var(--neon-cyan),transparent);box-shadow:0 0 10px var(--neon-cyan)}
.kk-inz::after{background:radial-gradient(ellipse at 50% 0%,rgba(74,214,255,0.2),transparent 70%)}
.kk-inz:hover{border-color:rgba(74,214,255,0.55);box-shadow:0 0 40px rgba(74,214,255,0.25),0 12px 30px rgba(0,0,0,0.6)}

.kk-ikona{
    font-size:3em;line-height:1;position:relative;z-index:1;
    filter:drop-shadow(0 0 12px currentColor);
}
.kk-nazwa{
    font-family:'Oswald',sans-serif;font-size:1.6em;
    text-transform:uppercase;letter-spacing:3px;
    position:relative;z-index:1;
}
.kk-egz .kk-nazwa{color:var(--neon-red-hot);text-shadow:0 0 15px rgba(255,61,94,0.7)}
.kk-szb .kk-nazwa{color:var(--neon-ember);text-shadow:0 0 15px rgba(255,122,61,0.7)}
.kk-inz .kk-nazwa{color:var(--neon-cyan);text-shadow:0 0 15px rgba(74,214,255,0.7)}

.kk-opis{
    color:var(--txt-dim);font-size:.92em;line-height:1.6;
    min-height:90px;position:relative;z-index:1;
}

/* Ministatystyki */
.kk-stats{
    display:grid;grid-template-columns:1fr 1fr;gap:6px;
    width:100%;position:relative;z-index:1;
}
.kk-stat{
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.04);
    border-radius:2px;padding:7px 9px;text-align:left;
}
.kk-stat-name{
    font-size:.66em;color:var(--txt-mute);text-transform:uppercase;
    font-family:'JetBrains Mono',monospace;letter-spacing:1px;margin-bottom:2px;
}
.kk-stat-val{
    font-size:.95em;font-weight:700;font-family:'Oswald',sans-serif;letter-spacing:1px;
}
.kk-egz .kk-stat-val{color:var(--neon-red-hot)}
.kk-szb .kk-stat-val{color:var(--neon-ember)}
.kk-inz .kk-stat-val{color:var(--neon-cyan)}

/* PRZYCISK WYBORU */
.btn-klasa{
    width:100%;padding:13px;
    font-family:'Oswald',sans-serif;font-size:1em;font-weight:600;
    text-transform:uppercase;letter-spacing:2px;
    border-radius:2px;cursor:pointer;border:1px solid;
    transition:.3s;position:relative;z-index:1;background:transparent;
}
.kk-egz .btn-klasa{color:var(--neon-red-hot);border-color:var(--border-mid)}
.kk-egz .btn-klasa:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 22px rgba(255,23,68,0.6);text-shadow:0 0 8px #fff}
.kk-szb .btn-klasa{color:var(--neon-ember);border-color:rgba(255,122,61,0.4)}
.kk-szb .btn-klasa:hover{background:var(--neon-ember);color:#000;box-shadow:0 0 22px rgba(255,122,61,0.6)}
.kk-inz .btn-klasa{color:var(--neon-cyan);border-color:rgba(74,214,255,0.4)}
.kk-inz .btn-klasa:hover{background:var(--neon-cyan);color:#000;box-shadow:0 0 22px rgba(74,214,255,0.6)}

/* ══ DASHBOARD ═══════════════════════════════════════════════════ */
.dash-header{
    display:flex;align-items:center;gap:20px;flex-wrap:wrap;
    background:rgba(10,6,12,0.55);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:20px 24px;margin-bottom:22px;position:relative;
}
.dash-header::before{
    content:'';position:absolute;top:0;left:24px;width:120px;height:1px;
    background:var(--neon-red);box-shadow:0 0 8px var(--neon-red);
}
.dash-avatar{
    width:80px;height:100px;flex-shrink:0;
    background-position:top center!important;background-size:cover!important;
    border-radius:2px;border:1px solid var(--border-mid);
    box-shadow:0 0 15px rgba(255,23,68,0.25),inset 0 0 10px rgba(0,0,0,0.6);
}
.dash-info{flex:1;min-width:0}
.dash-witaj{
    color:var(--neon-red);font-family:'JetBrains Mono',monospace;
    text-transform:uppercase;font-size:.7em;letter-spacing:3px;margin-bottom:4px;
    text-shadow:0 0 6px rgba(255,23,68,0.5);
}
.dash-nick{
    color:#fff;font-family:'Oswald',sans-serif;font-size:2em;font-weight:500;
    letter-spacing:2px;line-height:1;margin-bottom:10px;text-transform:uppercase;
}
.dash-nick .lvl{
    font-size:.55em;color:var(--txt-mute);margin-left:10px;
    font-family:'JetBrains Mono',monospace;letter-spacing:2px;vertical-align:middle;
}

/* Odznaka klasy */
.klasa-badge{
    display:inline-flex;align-items:center;gap:8px;
    padding:5px 14px;border-radius:2px;
    font-family:'Oswald',sans-serif;font-size:.85em;
    text-transform:uppercase;letter-spacing:2px;font-weight:600;
    border:1px solid;
}
.badge-Egzekutor {color:var(--neon-red-hot);border-color:var(--border-mid);background:rgba(255,23,68,0.1);text-shadow:0 0 6px rgba(255,23,68,0.5)}
.badge-Szabrownik{color:var(--neon-ember);border-color:rgba(255,122,61,0.3);background:rgba(255,122,61,0.1);text-shadow:0 0 6px rgba(255,122,61,0.5)}
.badge-Inżynier  {color:var(--neon-cyan);border-color:rgba(74,214,255,0.3);background:rgba(74,214,255,0.08);text-shadow:0 0 6px rgba(74,214,255,0.5)}

.dash-money{
    text-align:right;flex-shrink:0;
    padding-left:20px;margin-left:auto;
    border-left:1px dashed rgba(255,23,68,0.2);
}
.dash-money .lbl{
    color:var(--txt-mute);font-family:'JetBrains Mono',monospace;
    font-size:.7em;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;
}
.dash-money .val{
    color:var(--neon-ember);font-family:'Oswald',sans-serif;
    font-size:1.8em;font-weight:500;letter-spacing:1px;
    text-shadow:0 0 12px rgba(255,122,61,0.5);
}

/* Siatka kafelków */
.dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:22px}

.dash-tile{
    background:rgba(10,6,12,0.55);backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);border-radius:2px;padding:20px;
    transition:border-color .25s,box-shadow .25s;position:relative;
}
.dash-tile::before{
    content:'';position:absolute;top:0;left:0;width:28px;height:1px;
    background:var(--neon-red);box-shadow:0 0 6px var(--neon-red);
}
.dash-tile:hover{border-color:var(--border-mid);box-shadow:0 4px 24px rgba(255,23,68,0.12)}
.dt-label{
    font-family:'Oswald',sans-serif;text-transform:uppercase;
    font-size:.78em;color:#fff;letter-spacing:2.5px;margin-bottom:14px;
    padding-bottom:8px;border-bottom:1px solid var(--border-soft);
}

/* Szybkie akcje */
.akcje-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
.akcja{
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.05);
    border-radius:2px;padding:14px 10px;text-align:center;
    text-decoration:none;transition:.25s;
    display:flex;flex-direction:column;align-items:center;gap:6px;
}
.akcja:hover{transform:translateY(-2px)}
.akcja-ikona{font-size:1.8em;line-height:1}
.akcja-nazwa{
    font-family:'Oswald',sans-serif;font-size:.78em;
    text-transform:uppercase;letter-spacing:1.5px;
}

/* Kolorowanie akcji wg klasy */
<?php
$kolor = match($klasa){
    'Egzekutor' => ['var(--neon-red-hot)','255,23,68'],
    'Szabrownik'=> ['var(--neon-ember)','255,122,61'],
    'Inżynier'  => ['var(--neon-cyan)','74,214,255'],
    default     => ['var(--neon-red)','255,23,68'],
};
echo ".akcja:hover{border-color:rgba({$kolor[1]},.4);box-shadow:0 0 18px rgba({$kolor[1]},.15);}
      .akcja-nazwa{color:{$kolor[0]}}";
?>

/* Pasek ostatnich aktywności */
.aktywnosc-row{
    display:flex;align-items:flex-start;gap:10px;
    padding:10px 0;border-bottom:1px dashed rgba(255,23,68,0.08);
    font-size:.9em;color:var(--txt-dim);
}
.aktywnosc-row:last-child{border-bottom:none}
.akt-dot{
    width:6px;height:6px;background:var(--neon-red);border-radius:50%;
    flex-shrink:0;margin-top:6px;box-shadow:0 0 6px var(--neon-red);
}
.akt-czas{
    color:var(--txt-mute);font-size:.82em;white-space:nowrap;flex-shrink:0;
    font-family:'JetBrains Mono',monospace;letter-spacing:1px;
}

/* Mini-paski HP/EN/EXP w dashboardzie */
.mini-bar-wrap{background:rgba(0,0,0,0.7);border:1px solid rgba(255,23,68,0.1);height:8px;overflow:hidden;margin-top:4px;position:relative}
.mini-bar{height:100%;transition:width .6s ease}
.mini-stat-row{margin-bottom:14px}
.mini-stat-row:last-child{margin-bottom:0}
.mini-label{
    display:flex;justify-content:space-between;font-size:.82em;
    color:var(--txt-dim);margin-bottom:4px;
    font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;
}
.mini-label b{color:#fff;font-family:'JetBrains Mono',monospace;letter-spacing:0;font-weight:500}

@media(max-width:700px){.klasy-grid{grid-template-columns:1fr}}
</style>

<?php if ($klasa == 'Brak'): ?>
<!-- ══════════════════════════════════════════════════════
     EKRAN WYBORU KLASY — PIERWSZE LOGOWANIE
════════════════════════════════════════════════════════ -->
<div class="kreator-wrap">
    <div class="w-tytul">Wybierz Specjalizację</div>
    <div class="w-sub">// The Abyss · Krok 1 z 1</div>

    <div class="kreator-alert">
        Ten wybór jest ostateczny i nie można go cofnąć. Zastanów się dobrze.
    </div>

    <div class="klasy-grid">

        <!-- EGZEKUTOR -->
        <div class="kk kk-egz">
            <div class="kk-ikona">⚔️</div>
            <div class="kk-nazwa">Egzekutor</div>
            <div class="kk-opis">Zabijaka, mięśniak, ochroniarz. Walczy na arenie w Dokach, zdobywając doświadczenie rozlewem krwi. Specjalizuje się w broni i bezpośredniej konfrontacji.</div>
            <div class="kk-stats">
                <div class="kk-stat"><div class="kk-stat-name">Siła ataku</div><div class="kk-stat-val">+20%</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Arena PvP</div><div class="kk-stat-val">✓ Pełny</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Warsztat</div><div class="kk-stat-val">✗ Brak</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Złomowisko</div><div class="kk-stat-val">✗ Brak</div></div>
            </div>
            <form method="POST" style="width:100%">
                <input type="hidden" name="klasa_wyb" value="Egzekutor">
                <button type="submit" name="wybierz_klase" class="btn-klasa">Zostaję Egzekutorem</button>
            </form>
        </div>

        <!-- SZABROWNIK -->
        <div class="kk kk-szb">
            <div class="kk-ikona">🔩</div>
            <div class="kk-nazwa">Szabrownik</div>
            <div class="kk-opis">Zwinny, sprytny, cichy. Przeszukuje złomowiska w poszukiwaniu rzadkich komponentów. Zaopatruje Inżynierów w surowce niezbędne do produkcji.</div>
            <div class="kk-stats">
                <div class="kk-stat"><div class="kk-stat-name">Szabrowanie</div><div class="kk-stat-val">+30%</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Złomowisko</div><div class="kk-stat-val">✓ Pełny</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Arena PvP</div><div class="kk-stat-val">✗ Brak</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Warsztat</div><div class="kk-stat-val">✗ Brak</div></div>
            </div>
            <form method="POST" style="width:100%">
                <input type="hidden" name="klasa_wyb" value="Szabrownik">
                <button type="submit" name="wybierz_klase" class="btn-klasa">Zostaję Szabrownikiem</button>
            </form>
        </div>

        <!-- INŻYNIER -->
        <div class="kk kk-inz">
            <div class="kk-ikona">🔧</div>
            <div class="kk-nazwa">Inżynier</div>
            <div class="kk-opis">Umysł ponad siłę. Jedyna klasa zdolna tworzyć broń i pancerze w Warsztacie. Przetwarza komponenty od Szabrowników w gotowy sprzęt bojowy.</div>
            <div class="kk-stats">
                <div class="kk-stat"><div class="kk-stat-name">Produkcja</div><div class="kk-stat-val">+35%</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Warsztat</div><div class="kk-stat-val">✓ Pełny</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Arena PvP</div><div class="kk-stat-val">✗ Brak</div></div>
                <div class="kk-stat"><div class="kk-stat-name">Złomowisko</div><div class="kk-stat-val">✗ Brak</div></div>
            </div>
            <form method="POST" style="width:100%">
                <input type="hidden" name="klasa_wyb" value="Inżynier">
                <button type="submit" name="wybierz_klase" class="btn-klasa">Zostaję Inżynierem</button>
            </form>
        </div>

    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════
     DASHBOARD — POWRACAJĄCY GRACZ
════════════════════════════════════════════════════════ -->
<?php
$avatar = !empty($gracz['avatar']) ? htmlspecialchars($gracz['avatar']) : "https://via.placeholder.com/500x625/0a0a0a/333?text=PORTRET";
$proc_hp  = min(100, ($gracz['hp_aktualne'] / $gracz['hp_max']) * 100);
$proc_en  = min(100, ($gracz['energia_aktualna'] / $gracz['energia_max']) * 100);
$proc_exp = min(100, ($gracz['exp'] / ($gracz['poziom'] * 100)) * 100);

$akcje = match($klasa) {
    'Egzekutor'  => [
        ['game.php?page=doki',       '⚔️',  'Arena (Doki)'],
        ['game.php?page=sklep',      '🛒',  'Sklep'],
        ['game.php?page=ekwipunek',  '🎒',  'Ekwipunek'],
        ['game.php?page=zlecenia',   '📜',  'Zlecenia'],
        ['game.php?page=ranking',    '🏆',  'Ranking'],
        ['game.php?page=szpital',    '🏥',  'Klinika'],
    ],
    'Szabrownik' => [
        ['game.php?page=zlomowisko', '🔩',  'Złomowisko'],
        ['game.php?page=rynek',      '🕶️', 'Czarny Rynek'],
        ['game.php?page=ekwipunek',  '🎒',  'Ekwipunek'],
        ['game.php?page=zlecenia',   '📜',  'Zlecenia'],
        ['game.php?page=sklep',      '🛒',  'Sklep'],
        ['game.php?page=szpital',    '🏥',  'Klinika'],
    ],
    'Inżynier'   => [
        ['game.php?page=warsztat',   '🔧',  'Warsztat'],
        ['game.php?page=rynek',      '🕶️', 'Czarny Rynek'],
        ['game.php?page=ekwipunek',  '🎒',  'Ekwipunek'],
        ['game.php?page=zlecenia',   '📜',  'Zlecenia'],
        ['game.php?page=sklep',      '🛒',  'Sklep'],
        ['game.php?page=szpital',    '🏥',  'Klinika'],
    ],
    default => []
};
?>

<!-- Nagłówek z avatarem -->
<div class="dash-header">
    <div class="dash-avatar" style="background-image:url('<?php echo $avatar; ?>')"></div>
    <div class="dash-info">
        <div class="dash-witaj">// Witaj z powrotem, operative</div>
        <div class="dash-nick">
            <?php if($gracz['is_premium']) echo "<span style='color:var(--neon-gold);text-shadow:0 0 8px var(--neon-gold)'>★</span> "; ?>
            <?php echo htmlspecialchars($gracz['login']); ?>
            <span class="lvl">LVL <?php echo $gracz['poziom']; ?></span>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="klasa-badge badge-<?php echo $klasa; ?>">
                <?php echo match($klasa){'Egzekutor'=>'⚔️','Szabrownik'=>'🔩','Inżynier'=>'🔧',default=>''}; ?>
                <?php echo $klasa; ?>
            </span>
            <?php if(!empty($gracz['profesja_fabularna']) && $gracz['profesja_fabularna'] != 'Brak'): ?>
            <span style="color:var(--txt-dim);font-family:'Oswald',sans-serif;font-size:.85em;letter-spacing:1px;text-transform:uppercase">
                💼 <?php echo htmlspecialchars($gracz['profesja_fabularna']); ?>
            </span>
            <?php endif; ?>
            <?php if(!empty($gracz['tytul_naukowy'])): ?>
            <span style="color:var(--neon-gold);font-family:'Oswald',sans-serif;font-size:.82em;letter-spacing:1px;text-transform:uppercase;text-shadow:0 0 6px rgba(255,215,0,0.4)">
                🎓 <?php echo htmlspecialchars($gracz['tytul_naukowy']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="dash-money">
        <div class="lbl">Stan konta</div>
        <div class="val"><?php echo number_format($gracz['gotowka'],0,'','&nbsp;'); ?> $</div>
    </div>
</div>

<!-- Siatka kafelków -->
<div class="dash-grid">

    <!-- Stan fizyczny -->
    <div class="dash-tile">
        <div class="dt-label">⚡ Stan fizyczny</div>
        <div class="mini-stat-row">
            <div class="mini-label"><span>Zdrowie</span><b><?php echo $gracz['hp_aktualne'].'/'.$gracz['hp_max']; ?></b></div>
            <div class="mini-bar-wrap"><div class="mini-bar" style="width:<?php echo $proc_hp; ?>%;background:linear-gradient(90deg,#5a0010,var(--neon-red),var(--neon-red-hot));box-shadow:0 0 8px rgba(255,23,68,0.5)"></div></div>
        </div>
        <div class="mini-stat-row">
            <div class="mini-label"><span>Energia</span><b><?php echo $gracz['energia_aktualna'].'/'.$gracz['energia_max']; ?></b></div>
            <div class="mini-bar-wrap"><div class="mini-bar" style="width:<?php echo $proc_en; ?>%;background:linear-gradient(90deg,#003344,var(--neon-cyan));box-shadow:0 0 8px rgba(74,214,255,0.4)"></div></div>
        </div>
        <div class="mini-stat-row">
            <div class="mini-label"><span>Doświadczenie</span><b><?php echo $gracz['exp'].'/'.($gracz['poziom']*100); ?></b></div>
            <div class="mini-bar-wrap"><div class="mini-bar" style="width:<?php echo $proc_exp; ?>%;background:linear-gradient(90deg,#5a2200,var(--neon-ember));box-shadow:0 0 8px rgba(255,122,61,0.4)"></div></div>
        </div>
    </div>

    <!-- Szybkie akcje -->
    <div class="dash-tile" style="grid-column:span 2">
        <div class="dt-label">🎯 Szybkie akcje — <?php echo $klasa; ?></div>
        <div class="akcje-grid">
            <?php foreach($akcje as [$url,$ico,$nazwa]): ?>
            <a href="<?php echo $url; ?>" class="akcja">
                <span class="akcja-ikona"><?php echo $ico; ?></span>
                <span class="akcja-nazwa"><?php echo $nazwa; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Ostatnie aktywności -->
<div class="dash-tile" style="margin-bottom:0">
    <div class="dt-label">🔔 Ostatnie aktywności</div>
    <?php
    $ma_alerty = false;
    if ($alerty && $alerty->num_rows > 0):
        $ma_alerty = true;
        while($a = $alerty->fetch_assoc()):
            $czas = date('d.m · H:i', strtotime($a['data_dodania']));
    ?>
        <div class="aktywnosc-row">
            <div class="akt-dot"></div>
            <div style="flex:1"><?php echo htmlspecialchars($a['tresc']); ?></div>
            <div class="akt-czas"><?php echo $czas; ?></div>
        </div>
    <?php endwhile; endif; ?>
    <?php if(!$ma_alerty): ?>
        <div style="color:var(--txt-mute);font-style:italic;font-size:.9em;padding:10px 0;text-align:center">
            // Brak ostatnich aktywności. Czas to zmienić.
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>