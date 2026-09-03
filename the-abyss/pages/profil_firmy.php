<?php
require_once "db.php";
require_once "helpers/firmy.php";

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

$id_firmy = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_firmy <= 0) {
    echo "<div class='msg msg-bad' style='padding:14px;background:rgba(255,23,68,0.06);border:1px solid var(--border-mid);color:var(--neon-red-hot);'>// brak ID firmy //</div>";
    return;
}

// Pobierz dane gracza i firmy
$wynik_g = $polaczenie->query("SELECT id, login, is_premium, is_mg, gotowka, id_firmy, stanowisko_w_firmie FROM gracze WHERE id=$id_gracza");
$gracz = $wynik_g->fetch_assoc();

$firma = pobierz_firme($id_firmy);
if (!$firma) {
    echo "<div class='msg msg-bad' style='padding:14px;background:rgba(255,23,68,0.06);border:1px solid var(--border-mid);color:var(--neon-red-hot);'>// firma o ID $id_firmy nie istnieje //</div>";
    return;
}

$jest_wlascicielem = czy_wlasciciel($id_gracza, $id_firmy);
$jest_mg = !empty($gracz['is_mg']);

// ════════════════════════════════════════════════════════════════════
// LOGIKA: EDYCJA FIRMY (tylko właściciel)
// ════════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edytuj_firme']) && $jest_wlascicielem) {
    $nowy_slogan = trim($_POST['slogan'] ?? '');
    $nowy_adres = trim($_POST['adres_lokalu'] ?? '');
    $nowy_opis = trim($_POST['opis_rp'] ?? '');
    $nowa_historia = trim($_POST['historia_rp'] ?? '');

    if (mb_strlen($nowy_opis) > FIRMA_LIMIT_OPIS_RP) {
        $komunikat = "<div class='msg msg-bad'>Opis fabularny może mieć maksymalnie " . FIRMA_LIMIT_OPIS_RP . " znaków.</div>";
    } elseif (mb_strlen($nowa_historia) > FIRMA_LIMIT_HISTORIA) {
        $komunikat = "<div class='msg msg-bad'>Historia firmy może mieć maksymalnie " . FIRMA_LIMIT_HISTORIA . " znaków.</div>";
    } else {
        $slogan_e = $polaczenie->real_escape_string($nowy_slogan);
        $adres_e  = $polaczenie->real_escape_string($nowy_adres);
        $opis_e   = $polaczenie->real_escape_string($nowy_opis);
        $hist_e   = $polaczenie->real_escape_string($nowa_historia);

        $polaczenie->query("
            UPDATE firmy
            SET slogan = '$slogan_e',
                adres_lokalu = '$adres_e',
                opis_rp = '$opis_e',
                historia_rp = '$hist_e'
            WHERE id = $id_firmy
        ");

        // Upload nowego logo (jeśli przesłano)
        $upload_logo = upload_obrazka_firmy('plik_logo', 'logo_url_text', 'logo', $id_firmy);
        if ($upload_logo['ok'] && $upload_logo['url'] !== '') {
            $url_e = $polaczenie->real_escape_string($upload_logo['url']);
            $polaczenie->query("UPDATE firmy SET logo_url = '$url_e' WHERE id = $id_firmy");
        } elseif (!$upload_logo['ok']) {
            $komunikat = "<div class='msg msg-bad'>" . htmlspecialchars($upload_logo['blad']) . "</div>";
        }

        // Upload nowego bannera (jeśli przesłano)
        $upload_banner = upload_obrazka_firmy('plik_banner', 'banner_url_text', 'banner', $id_firmy);
        if ($upload_banner['ok'] && $upload_banner['url'] !== '') {
            $url_e = $polaczenie->real_escape_string($upload_banner['url']);
            $polaczenie->query("UPDATE firmy SET banner_url = '$url_e' WHERE id = $id_firmy");
        } elseif (!$upload_banner['ok']) {
            $komunikat = "<div class='msg msg-bad'>" . htmlspecialchars($upload_banner['blad']) . "</div>";
        }

        if ($komunikat === '') {
            $komunikat = "<div class='msg msg-good'>Profil firmy został zaktualizowany.</div>";
        }
        $firma = pobierz_firme($id_firmy);
    }
}

// ════════════════════════════════════════════════════════════════════
// LOGIKA: DODANIE WPISU DO KRONIKI (MG lub właściciel dla typów własnych)
// ════════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dodaj_wpis_kroniki'])) {
    $typ_wpisu = trim($_POST['typ_wpisu'] ?? 'wydarzenie');
    $tytul_wpisu = trim($_POST['tytul_wpisu'] ?? '');
    $opis_wpisu = trim($_POST['opis_wpisu'] ?? '');
    $publiczny_wpis = isset($_POST['publiczny']) ? 1 : 0;

    // MG może dodać wszystkie typy. Właściciel tylko 'wydarzenie' i 'plotka'.
    $dozwolone_dla_mg = ['zasluga','strata','wydarzenie','sesja','plotka'];
    $dozwolone_dla_wlasciciela = ['wydarzenie','plotka'];

    $moze_dodac = false;
    if ($jest_mg && in_array($typ_wpisu, $dozwolone_dla_mg)) $moze_dodac = true;
    elseif ($jest_wlascicielem && in_array($typ_wpisu, $dozwolone_dla_wlasciciela)) $moze_dodac = true;

    if (!$moze_dodac) {
        $komunikat = "<div class='msg msg-bad'>Nie masz uprawnień do dodania tego typu wpisu.</div>";
    } elseif ($tytul_wpisu === '' || $opis_wpisu === '') {
        $komunikat = "<div class='msg msg-bad'>Tytuł i opis wpisu nie mogą być puste.</div>";
    } else {
        $rezultat = dodaj_kronike($id_firmy, $typ_wpisu, $tytul_wpisu, $opis_wpisu, $id_gracza, $publiczny_wpis);
        if ($rezultat) {
            $komunikat = "<div class='msg msg-good'>Wpis został dodany do kroniki firmy.</div>";
        } else {
            $komunikat = "<div class='msg msg-bad'>Błąd zapisu wpisu w kronice.</div>";
        }
    }
}

// Pobierz aktualne dane do widoku
$branze_def = definicja_branz();
$b = $branze_def[$firma['branza_kod']] ?? ['nazwa' => 'Nieznana', 'ikona' => '🏢', 'kolor' => '#888888', 'opis' => ''];

$pracownicy = pracownicy_firmy($id_firmy);
$wpisy_kroniki = kronika_firmy($id_firmy, !$jest_mg && !$jest_wlascicielem);
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   PROFIL FIRMY — banner + logo + sekcje
═══════════════════════════════════════════════════════════════ */

/* Banner — szeroki obrazek tła */
.firma-banner {
    position: relative; height: 220px; margin-bottom: 70px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft);
    border-radius: 2px; overflow: visible; background-size: cover; background-position: center;
}
.firma-banner::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.4) 0%, rgba(5,6,12,0.95) 100%);
    pointer-events: none;
}
.firma-banner.placeholder {
    display: flex; align-items: center; justify-content: center;
    font-size: 6em; color: rgba(255,255,255,0.08);
    background: linear-gradient(135deg, #0a0a14 0%, #1a0a14 100%);
}
@media(max-width: 700px) { .firma-banner { height: 160px; margin-bottom: 60px; } }

.firma-logo-box {
    position: absolute; bottom: -50px; left: 24px;
    width: 110px; height: 110px;
    border: 2px solid var(--neon-gold); border-radius: 2px;
    background-size: cover; background-position: center;
    background-color: rgba(0,0,0,0.8);
    box-shadow: 0 0 24px rgba(0,0,0,0.8), 0 0 18px rgba(255,215,0,0.2);
    z-index: 2;
    display: flex; align-items: center; justify-content: center;
    font-size: 3em;
}
@media(max-width: 700px) { .firma-logo-box { width: 90px; height: 90px; bottom: -40px; left: 16px; font-size: 2.5em; } }

.firma-tytul-box {
    position: absolute; bottom: -50px; left: 150px; right: 24px;
    z-index: 1; padding-top: 12px;
}
@media(max-width: 700px) { .firma-tytul-box { left: 116px; right: 16px; } }
.firma-tytul-box h1 {
    font-family: 'Oswald', sans-serif; font-weight: 500;
    font-size: 1.8em; text-transform: uppercase; letter-spacing: 2px;
    color: #fff; line-height: 1; margin-bottom: 4px;
    text-shadow: 0 0 18px rgba(255,215,0,0.3), 0 2px 4px rgba(0,0,0,0.9);
}
@media(max-width: 700px) { .firma-tytul-box h1 { font-size: 1.3em; } }
.firma-tytul-box .firma-branza-tag {
    font-family: 'JetBrains Mono', monospace; font-size: .75em;
    letter-spacing: 3px; text-transform: uppercase;
}

/* Slogan */
.firma-slogan {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); font-size: 1.1em; padding: 0 24px 20px;
    border-bottom: 1px dashed var(--border-soft); margin-bottom: 22px;
    line-height: 1.5;
}

/* Statystyki firmy (mini hero-stats) */
.firma-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
    margin-bottom: 22px;
}
@media(max-width: 700px) { .firma-stats { grid-template-columns: repeat(2, 1fr); } }
.firma-stat {
    background: rgba(0,0,0,0.4); border: 1px solid var(--border-soft);
    padding: 12px; border-radius: 1px; position: relative;
}
.firma-stat::before {
    content: ''; position: absolute; left: 0; top: 10%; width: 2px; height: 80%;
    background: var(--neon-gold); box-shadow: 0 0 4px var(--neon-gold);
}
.firma-stat .lbl {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 3px;
}
.firma-stat .val {
    font-family: 'Oswald', sans-serif; font-size: 1.4em;
    color: #fff; font-weight: 500; line-height: 1;
}

/* Sekcje treści — w stylu .card */
.firma-sekcja {
    background: rgba(18,10,18,0.45); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 20px; margin-bottom: 18px; position: relative;
}
.firma-sekcja::before {
    content: ''; position: absolute; top: 0; left: 0; width: 28px; height: 1px;
    background: var(--neon-gold); box-shadow: 0 0 6px var(--neon-gold);
}
.firma-sekcja h2 {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 1.05em;
    text-transform: uppercase; letter-spacing: 2px; color: #fff;
    margin-bottom: 14px; padding-bottom: 10px;
    border-bottom: 1px dashed rgba(255,215,0,0.18);
    display: flex; align-items: center; gap: 10px; justify-content: space-between;
}
.firma-sekcja h2 .tag {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-gold); letter-spacing: 2px; font-weight: 400;
    padding: 2px 6px; border: 1px solid rgba(255,215,0,0.3); border-radius: 1px;
}
.firma-sekcja .tresc-rp {
    color: var(--txt-main); line-height: 1.7; font-size: 1em;
    white-space: pre-wrap; word-wrap: break-word;
}

/* Lista pracowników */
.prac-lista { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
@media(max-width: 700px) { .prac-lista { grid-template-columns: 1fr; } }
.prac-karta {
    display: flex; align-items: center; gap: 12px;
    padding: 10px; background: rgba(0,0,0,0.3);
    border: 1px solid var(--border-soft); border-radius: 1px;
}
.prac-karta:hover { border-color: var(--border-mid); background: rgba(255,23,68,0.04); }
.prac-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background-size: cover; background-position: center;
    background-color: rgba(0,0,0,0.6); border: 1px solid var(--border-soft);
    flex-shrink: 0;
}
.prac-info { flex: 1; min-width: 0; }
.prac-nazwa {
    font-family: 'Oswald', sans-serif; font-weight: 500; color: #fff;
    text-transform: uppercase; letter-spacing: 1.5px; font-size: .95em;
}
.prac-nazwa.vip { color: var(--neon-gold); }
.prac-stanowisko {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 2px;
}
.prac-stanowisko.wlasciciel { color: var(--neon-gold); }
.prac-stanowisko.manager { color: var(--neon-cyan); }

/* Kronika */
.wpis-kroniki {
    padding: 14px 16px; margin-bottom: 10px;
    background: rgba(0,0,0,0.3); border: 1px solid var(--border-soft);
    border-radius: 1px; border-left: 3px solid var(--neon-gold);
}
.wpis-kroniki.zasluga { border-left-color: var(--neon-green); background: rgba(90,255,154,0.03); }
.wpis-kroniki.strata  { border-left-color: var(--neon-red); background: rgba(255,23,68,0.03); }
.wpis-kroniki.sesja   { border-left-color: var(--neon-ember); background: rgba(255,122,61,0.03); }
.wpis-kroniki.plotka  { border-left-color: var(--neon-cyan); background: rgba(74,214,255,0.03); }
.wpis-kroniki.ukryty  { opacity: .55; border-left-style: dashed; }

.wpis-naglowek {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 8px; flex-wrap: wrap; gap: 8px;
}
.wpis-tytul {
    font-family: 'Oswald', sans-serif; font-weight: 500; color: #fff;
    text-transform: uppercase; letter-spacing: 1.5px; font-size: 1em;
}
.wpis-meta {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); letter-spacing: 1px;
}
.wpis-meta .typ-zasluga { color: var(--neon-green); }
.wpis-meta .typ-strata  { color: var(--neon-red); }
.wpis-meta .typ-sesja   { color: var(--neon-ember); }
.wpis-meta .typ-plotka  { color: var(--neon-cyan); }
.wpis-meta .typ-wydarzenie { color: var(--neon-gold); }
.wpis-meta .mg-tag {
    color: var(--neon-red); border: 1px solid var(--neon-red);
    padding: 1px 5px; border-radius: 1px; font-size: .85em;
    text-shadow: 0 0 4px rgba(255,23,68,0.5);
}
.wpis-opis {
    color: var(--txt-dim); line-height: 1.6; font-size: .94em;
    white-space: pre-wrap; word-wrap: break-word;
}

/* Form-y edycji / dodawania */
.firma-form input, .firma-form select, .firma-form textarea {
    width: 100%; padding: 10px 12px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft); color: var(--txt-main);
    border-radius: 1px; font-family: 'Rajdhani', sans-serif; font-size: .95em;
    margin-bottom: 12px; box-sizing: border-box; transition: all .25s;
}
.firma-form input:focus, .firma-form select:focus, .firma-form textarea:focus {
    outline: none; border-color: var(--neon-gold); background: rgba(255,215,0,0.04);
}
.firma-form textarea { resize: vertical; min-height: 70px; }
.firma-form label {
    display: block; font-family: 'JetBrains Mono', monospace;
    font-size: .72em; color: var(--txt-mute); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 6px;
}
.firma-form .checkbox-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 12px;
    color: var(--txt-dim); font-size: .9em;
}
.firma-form .checkbox-row input { width: auto; margin: 0; }

.firma-btn {
    padding: 10px 20px; background: rgba(255,215,0,0.08);
    border: 1px solid rgba(255,215,0,0.35); color: #fff;
    font-family: 'Oswald', sans-serif; font-weight: 500; letter-spacing: 2px;
    text-transform: uppercase; font-size: .85em;
    cursor: pointer; border-radius: 1px; transition: all .25s;
}
.firma-btn:hover {
    background: var(--neon-gold); color: #000;
    box-shadow: 0 0 18px rgba(255,215,0,0.5);
}
.firma-btn.red {
    background: rgba(255,23,68,0.08); border-color: var(--border-mid);
}
.firma-btn.red:hover {
    background: var(--neon-red); color: #fff;
    box-shadow: 0 0 18px rgba(255,23,68,0.7);
}

/* Sekcja ukryta domyślnie (toggle dla edytora) */
details.firma-edytor {
    margin-bottom: 22px;
    background: rgba(0,0,0,0.45); border: 1px solid rgba(255,215,0,0.2);
    border-radius: 2px; padding: 0;
}
details.firma-edytor[open] { border-color: rgba(255,215,0,0.5); }
details.firma-edytor summary {
    padding: 14px 18px; cursor: pointer;
    font-family: 'Oswald', sans-serif; font-weight: 500;
    text-transform: uppercase; letter-spacing: 2px; font-size: .95em;
    color: var(--neon-gold); list-style: none;
    display: flex; align-items: center; justify-content: space-between;
}
details.firma-edytor summary::-webkit-details-marker { display: none; }
details.firma-edytor summary::after {
    content: '▼'; font-size: .7em; color: var(--neon-gold);
    transition: transform .2s;
}
details.firma-edytor[open] summary::after { transform: rotate(180deg); }
details.firma-edytor .edytor-tresc { padding: 0 18px 18px; }

/* Komunikaty */
.msg { padding: 12px 16px; border-radius: 1px; margin-bottom: 18px; font-family: 'JetBrains Mono', monospace; font-size: .85em; letter-spacing: 1.2px; line-height: 1.5; }
.msg-good { background: rgba(90,255,154,0.06); border: 1px solid rgba(90,255,154,0.4); color: var(--neon-green); }
.msg-bad  { background: rgba(255,23,68,0.06); border: 1px solid var(--border-mid); color: var(--neon-red-hot); }
</style>

<?php echo $komunikat; ?>

<!-- BANNER + LOGO -->
<div class="firma-banner <?php if (empty($firma['banner_url'])) echo 'placeholder'; ?>"
     <?php if (!empty($firma['banner_url'])): ?>style="background-image: url('<?php echo htmlspecialchars($firma['banner_url']); ?>');"<?php endif; ?>>
    <?php if (empty($firma['banner_url'])): ?>
        <span><?php echo $b['ikona']; ?></span>
    <?php endif; ?>

    <div class="firma-logo-box" <?php if (!empty($firma['logo_url'])): ?>style="background-image: url('<?php echo htmlspecialchars($firma['logo_url']); ?>');"<?php endif; ?>>
        <?php if (empty($firma['logo_url'])): ?><?php echo $b['ikona']; ?><?php endif; ?>
    </div>

    <div class="firma-tytul-box">
        <h1><?php echo htmlspecialchars($firma['nazwa']); ?></h1>
        <div class="firma-branza-tag" style="color: <?php echo $b['kolor']; ?>;">
            <?php echo $b['ikona'] . ' ' . htmlspecialchars($b['nazwa']); ?>
        </div>
    </div>
</div>

<?php if (!empty($firma['slogan'])): ?>
    <div class="firma-slogan">&bdquo;<?php echo htmlspecialchars($firma['slogan']); ?>&rdquo;</div>
<?php endif; ?>

<!-- STATYSTYKI -->
<div class="firma-stats">
    <div class="firma-stat">
        <div class="lbl">Właściciel</div>
        <div class="val" style="color: var(--neon-gold); font-size: 1.05em;">★ <?php echo htmlspecialchars($firma['wlasciciel_login'] ?? '?'); ?></div>
    </div>
    <div class="firma-stat">
        <div class="lbl">Pracownicy</div>
        <div class="val"><?php echo (int)$firma['pracownikow']; ?> / <?php echo FIRMA_MAX_PRACOWNIKOW; ?></div>
    </div>
    <div class="firma-stat">
        <div class="lbl">Reputacja</div>
        <div class="val" style="color: var(--neon-cyan);"><?php echo (int)$firma['reputacja']; ?></div>
    </div>
    <div class="firma-stat">
        <div class="lbl">Założono</div>
        <div class="val" style="font-size: .95em; color: var(--txt-dim); font-family: 'JetBrains Mono', monospace;">
            <?php echo date('d.m.Y', strtotime($firma['data_zalozenia'])); ?>
        </div>
    </div>
</div>

<!-- ADRES -->
<?php if (!empty($firma['adres_lokalu'])): ?>
    <div class="firma-sekcja">
        <h2>Lokalizacja <span class="tag">ADRES</span></h2>
        <div style="color: var(--txt-dim); font-family: 'JetBrains Mono', monospace; font-size: .95em; letter-spacing: 1px;">
            // <?php echo htmlspecialchars($firma['adres_lokalu']); ?>
        </div>
    </div>
<?php endif; ?>

<!-- OPIS RP -->
<?php if (!empty($firma['opis_rp'])): ?>
    <div class="firma-sekcja">
        <h2>O firmie <span class="tag">OPIS</span></h2>
        <div class="tresc-rp"><?php echo nl2br(htmlspecialchars($firma['opis_rp'])); ?></div>
    </div>
<?php else: ?>
    <div class="firma-sekcja">
        <h2>O firmie <span class="tag">OPIS</span></h2>
        <div style="color: var(--txt-mute); font-style: italic; font-family: 'Cormorant Garamond', serif; line-height: 1.6;">
            // właściciel jeszcze nie spisał opisu firmy //
        </div>
    </div>
<?php endif; ?>

<!-- HISTORIA -->
<?php if (!empty($firma['historia_rp'])): ?>
    <div class="firma-sekcja">
        <h2>Historia firmy <span class="tag">CHRONICLE</span></h2>
        <div class="tresc-rp"><?php echo nl2br(htmlspecialchars($firma['historia_rp'])); ?></div>
    </div>
<?php endif; ?>

<!-- PRACOWNICY -->
<div class="firma-sekcja">
    <h2>Załoga <span class="tag">TEAM // <?php echo count($pracownicy); ?></span></h2>
    <?php if (count($pracownicy) > 0): ?>
        <div class="prac-lista">
            <?php foreach ($pracownicy as $p):
                $av = !empty($p['avatar']) ? htmlspecialchars($p['avatar']) : 'https://via.placeholder.com/80/0a0a0a/333?text=?';
                $stan_class = $p['stanowisko_w_firmie'];
                $stan_label = [
                    'wlasciciel' => '★ Właściciel',
                    'manager'    => '☰ Manager',
                    'pracownik'  => 'Pracownik'
                ];
            ?>
                <a href="game.php?page=profil&id=<?php echo (int)$p['id']; ?>" class="prac-karta" style="text-decoration: none;">
                    <div class="prac-avatar" style="background-image: url('<?php echo $av; ?>');"></div>
                    <div class="prac-info">
                        <div class="prac-nazwa <?php if ($p['is_premium']) echo 'vip'; ?>">
                            <?php if ($p['is_premium']) echo '★ '; ?><?php echo htmlspecialchars($p['login']); ?>
                        </div>
                        <div class="prac-stanowisko <?php echo $stan_class; ?>">
                            <?php echo $stan_label[$stan_class] ?? 'Pracownik'; ?>
                            &nbsp;//&nbsp;
                            <?php echo htmlspecialchars($p['profesja_fabularna'] ?? '?'); ?>
                            &nbsp;//&nbsp;
                            LV.<?php echo (int)$p['poziom']; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="color: var(--txt-mute); font-style: italic; padding: 8px 0;">// brak pracowników //</div>
    <?php endif; ?>
</div>

<!-- KRONIKA -->
<div class="firma-sekcja">
    <h2>Kronika firmy <span class="tag">CHRONICLE // <?php echo count($wpisy_kroniki); ?></span></h2>
    <?php if (count($wpisy_kroniki) > 0): ?>
        <?php foreach ($wpisy_kroniki as $w):
            $typ_class = $w['typ'];
            $typ_label = [
                'zasluga'    => 'ZASŁUGA',
                'strata'     => 'STRATA',
                'wydarzenie' => 'WYDARZENIE',
                'sesja'      => 'SESJA',
                'plotka'     => 'PLOTKA',
            ];
            $ukryty_class = $w['widoczny_publicznie'] ? '' : 'ukryty';
        ?>
            <div class="wpis-kroniki <?php echo $typ_class . ' ' . $ukryty_class; ?>">
                <div class="wpis-naglowek">
                    <div class="wpis-tytul"><?php echo htmlspecialchars($w['tytul']); ?></div>
                    <div class="wpis-meta">
                        <span class="typ-<?php echo $typ_class; ?>">[<?php echo $typ_label[$typ_class] ?? '?'; ?>]</span>
                        &nbsp;//&nbsp;
                        <?php echo date('d.m.Y H:i', strtotime($w['data_wpisu'])); ?>
                        &nbsp;//&nbsp;
                        <?php if ($w['autor_mg']): ?><span class="mg-tag">MG</span> <?php endif; ?>
                        <?php echo htmlspecialchars($w['autor_login'] ?? '?'); ?>
                        <?php if (!$w['widoczny_publicznie']): ?> &nbsp;//&nbsp; <span style="color: var(--txt-mute);">UKRYTY</span><?php endif; ?>
                    </div>
                </div>
                <div class="wpis-opis"><?php echo nl2br(htmlspecialchars($w['opis'])); ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="color: var(--txt-mute); font-style: italic; padding: 8px 0;">// kronika pusta — żadne wydarzenia jeszcze się nie zapisały //</div>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════════════
     PANEL EDYCJI (dla właściciela)
     ════════════════════════════════════════════════════════════════ -->
<?php if ($jest_wlascicielem): ?>
    <details class="firma-edytor">
        <summary>★ Edycja profilu firmy <span class="tag" style="font-family:'JetBrains Mono',monospace; font-size:.65em; padding:2px 6px; border:1px solid rgba(255,215,0,0.3); border-radius:1px; letter-spacing:2px;">WŁAŚCICIEL</span></summary>
        <div class="edytor-tresc">
            <form method="POST" enctype="multipart/form-data" class="firma-form">
                <label>Slogan / hasło reklamowe</label>
                <input type="text" name="slogan" maxlength="255" value="<?php echo htmlspecialchars($firma['slogan'] ?? ''); ?>" placeholder="np. &bdquo;Twoje sekrety. Nasza dyskrecja.&rdquo;">

                <label>Adres lokalu (fabularnie)</label>
                <input type="text" name="adres_lokalu" maxlength="255" value="<?php echo htmlspecialchars($firma['adres_lokalu'] ?? ''); ?>" placeholder="np. Penthouse, Tribeca, 5. Avenue">

                <label>Opis firmy (max <?php echo FIRMA_LIMIT_OPIS_RP; ?> znaków)</label>
                <textarea name="opis_rp" maxlength="<?php echo FIRMA_LIMIT_OPIS_RP; ?>" rows="5"><?php echo htmlspecialchars($firma['opis_rp'] ?? ''); ?></textarea>

                <label>Historia firmy / kronika własna (max <?php echo FIRMA_LIMIT_HISTORIA; ?> znaków)</label>
                <textarea name="historia_rp" maxlength="<?php echo FIRMA_LIMIT_HISTORIA; ?>" rows="6" placeholder="Tu możesz spisać historię firmy — jej początki, ważne momenty, wizję..."><?php echo htmlspecialchars($firma['historia_rp'] ?? ''); ?></textarea>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label>Nowe logo (kwadrat, max 5MB)</label>
                        <input type="file" name="plik_logo" accept="image/jpeg,image/png,image/gif,image/webp">
                        <input type="url" name="logo_url_text" placeholder="LUB link do obrazka">
                    </div>
                    <div>
                        <label>Nowy banner (szerokie tło, max 5MB)</label>
                        <input type="file" name="plik_banner" accept="image/jpeg,image/png,image/gif,image/webp">
                        <input type="url" name="banner_url_text" placeholder="LUB link do obrazka">
                    </div>
                </div>

                <button type="submit" name="edytuj_firme" class="firma-btn">Zapisz zmiany</button>
            </form>
        </div>
    </details>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════════
     PANEL DODAWANIA WPISU DO KRONIKI
     - MG: pełen panel z wszystkimi typami
     - Właściciel: ograniczony (tylko 'wydarzenie' / 'plotka')
     ════════════════════════════════════════════════════════════════ -->
<?php if ($jest_mg || $jest_wlascicielem): ?>
    <details class="firma-edytor" <?php if ($jest_mg) echo 'style="border-color:rgba(255,23,68,0.4);"'; ?>>
        <summary <?php if ($jest_mg) echo 'style="color:var(--neon-red-hot);"'; ?>>
            <?php if ($jest_mg): ?>
                ⚠ Panel Mistrza Gry — dodaj wpis do kroniki
                <span class="tag" style="font-family:'JetBrains Mono',monospace; font-size:.65em; padding:2px 6px; border:1px solid rgba(255,23,68,0.4); border-radius:1px; letter-spacing:2px; color:var(--neon-red-hot);">MG ONLY</span>
            <?php else: ?>
                ✎ Dodaj wpis do kroniki firmy
                <span class="tag" style="font-family:'JetBrains Mono',monospace; font-size:.65em; padding:2px 6px; border:1px solid rgba(255,215,0,0.3); border-radius:1px; letter-spacing:2px;">WŁAŚCICIEL</span>
            <?php endif; ?>
        </summary>
        <div class="edytor-tresc">
            <form method="POST" class="firma-form">
                <label>Typ wpisu</label>
                <select name="typ_wpisu" required>
                    <option value="wydarzenie">Wydarzenie (zwykły wpis kronikalny)</option>
                    <option value="plotka">Plotka (krąży po mieście)</option>
                    <?php if ($jest_mg): ?>
                        <option value="zasluga">★ ZASŁUGA (MG only — pozytywny wpis)</option>
                        <option value="strata">⚠ STRATA (MG only — negatywny wpis)</option>
                        <option value="sesja">🎭 SESJA (MG only — wzmianka z sesji fabularnej)</option>
                    <?php endif; ?>
                </select>

                <label>Tytuł wpisu (max 160 znaków)</label>
                <input type="text" name="tytul_wpisu" maxlength="160" required placeholder="np. Pomoc dzielnicy Red w czasie kryzysu">

                <label>Opis wpisu</label>
                <textarea name="opis_wpisu" rows="4" required placeholder="Co się wydarzyło? Jak firma się zasłużyła lub straciła? Co się o niej mówi?"></textarea>

                <div class="checkbox-row">
                    <input type="checkbox" id="publiczny" name="publiczny" value="1" checked>
                    <label for="publiczny" style="display: inline; font-family: 'Rajdhani', sans-serif; font-size: .92em; text-transform: none; letter-spacing: 0; color: var(--txt-dim); margin: 0;">
                        Wpis publiczny (widoczny dla wszystkich graczy)
                    </label>
                </div>

                <button type="submit" name="dodaj_wpis_kroniki" class="firma-btn <?php if ($jest_mg) echo 'red'; ?>">
                    <?php echo $jest_mg ? 'Zapisz wpis MG' : 'Dodaj wpis'; ?>
                </button>
            </form>
        </div>
    </details>
<?php endif; ?>

<!-- POWRÓT -->
<div style="margin-top: 24px; text-align: center;">
    <a href="game.php?page=lista_firm" style="color: var(--neon-cyan); font-family: 'JetBrains Mono', monospace; font-size: .85em; letter-spacing: 2px; text-decoration: none;">
        ← WRÓĆ DO KATALOGU FIRM
    </a>
</div>