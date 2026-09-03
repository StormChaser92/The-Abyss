<?php
require_once "db.php";
require_once "helpers/firmy.php";

$id_gracza = $_SESSION['id_gracza'];
$filtr_branzy = isset($_GET['branza']) ? trim($_GET['branza']) : '';
$szukana_fraza = isset($_GET['szukaj']) ? trim($_GET['szukaj']) : '';

$branze_def = definicja_branz();
if ($filtr_branzy !== '' && !isset($branze_def[$filtr_branzy])) {
    $filtr_branzy = '';
}

// Budowanie zapytania
$warunki = ["status = 'aktywna'"];
if ($filtr_branzy !== '') {
    $b_e = $polaczenie->real_escape_string($filtr_branzy);
    $warunki[] = "branza_kod = '$b_e'";
}
if ($szukana_fraza !== '') {
    $f_e = $polaczenie->real_escape_string($szukana_fraza);
    $warunki[] = "(nazwa LIKE '%$f_e%' OR slogan LIKE '%$f_e%')";
}
$where_sql = "WHERE " . implode(' AND ', $warunki);

$firmy_q = $polaczenie->query("
    SELECT f.id, f.nazwa, f.branza_kod, f.slogan, f.logo_url, f.banner_url,
           f.data_zalozenia, f.reputacja,
           g.login AS wlasciciel_login,
           (SELECT COUNT(*) FROM gracze gp WHERE gp.id_firmy = f.id) AS pracownikow
    FROM firmy f
    LEFT JOIN gracze g ON g.id = f.wlasciciel_id
    $where_sql
    ORDER BY f.data_zalozenia DESC
");
$firmy_lista = $firmy_q ? $firmy_q->fetch_all(MYSQLI_ASSOC) : [];

// Statystyki dla nagłówka
$total_q = $polaczenie->query("SELECT COUNT(*) c FROM firmy WHERE status = 'aktywna'");
$total_firm = $total_q ? (int)$total_q->fetch_assoc()['c'] : 0;
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   LISTA FIRM — katalog
═══════════════════════════════════════════════════════════════ */

.lista-search-row {
    display: flex; gap: 10px; margin-bottom: 16px; align-items: stretch;
}
.lista-search-input {
    flex: 1; padding: 10px 14px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft); color: var(--txt-main);
    border-radius: 1px; font-family: 'Rajdhani', sans-serif; font-size: .98em;
}
.lista-search-input:focus { outline: none; border-color: var(--neon-gold); background: rgba(255,215,0,0.04); }
.lista-search-btn {
    padding: 10px 24px; background: rgba(255,23,68,0.08);
    border: 1px solid var(--border-mid); color: #fff;
    font-family: 'Oswald', sans-serif; font-weight: 500; letter-spacing: 2px;
    text-transform: uppercase; font-size: .85em;
    cursor: pointer; border-radius: 1px; transition: all .25s;
}
.lista-search-btn:hover { background: var(--neon-red); box-shadow: 0 0 18px rgba(255,23,68,0.6); }

/* Filtry branż */
.filtr-branz {
    display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 22px;
}
.filtr-tab {
    padding: 6px 12px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); color: var(--txt-dim);
    font-family: 'Oswald', sans-serif; font-size: .8em;
    text-transform: uppercase; letter-spacing: 1.5px;
    text-decoration: none; border-radius: 1px; transition: all .2s;
}
.filtr-tab:hover { border-color: var(--border-mid); color: #fff; background: rgba(255,23,68,0.04); }
.filtr-tab.aktywny {
    background: rgba(255,215,0,0.1); border-color: var(--neon-gold); color: var(--neon-gold);
    text-shadow: 0 0 6px rgba(255,215,0,0.4);
}

/* Lista firm — siatka kart */
.firma-karta-link {
    display: block; text-decoration: none; color: inherit;
    background: rgba(18,10,18,0.5); backdrop-filter: blur(4px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    margin-bottom: 18px; transition: all .25s;
    position: relative; overflow: hidden;
}
.firma-karta-link:hover {
    border-color: rgba(255,215,0,0.4);
    box-shadow: 0 0 24px rgba(255,215,0,0.12);
    transform: translateY(-2px);
}
.firma-karta-link::before {
    content: ''; position: absolute; top: 0; left: 0; width: 28px; height: 1px;
    background: var(--neon-gold); box-shadow: 0 0 6px var(--neon-gold); z-index: 3;
}

/* Karta z bannerem jako tłem */
.fk-banner {
    height: 100px; background-size: cover; background-position: center;
    background-color: rgba(0,0,0,0.5); position: relative;
}
.fk-banner.placeholder {
    display: flex; align-items: center; justify-content: center;
    font-size: 3em; color: rgba(255,255,255,0.1);
    background: linear-gradient(135deg, #0a0a14 0%, #1a0a14 100%);
}
.fk-banner::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(180deg, transparent 0%, rgba(18,10,18,0.95) 100%);
    pointer-events: none;
}

.fk-tresc {
    padding: 16px; display: flex; align-items: center; gap: 16px;
    margin-top: -36px; position: relative; z-index: 2;
}
.fk-logo {
    width: 64px; height: 64px; border-radius: 1px;
    background-size: cover; background-position: center;
    background-color: rgba(0,0,0,0.9); border: 2px solid var(--neon-gold);
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8em;
    box-shadow: 0 4px 16px rgba(0,0,0,0.6);
}
.fk-info { flex: 1; min-width: 0; }
.fk-nazwa {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 1.2em;
    text-transform: uppercase; letter-spacing: 1.5px; color: #fff;
    margin-bottom: 2px;
    text-shadow: 0 0 12px rgba(0,0,0,0.8);
}
.fk-branza {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px;
}
.fk-slogan {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); font-size: .92em; line-height: 1.4;
}

.fk-stats {
    display: flex; gap: 16px; padding: 0 16px 14px;
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); letter-spacing: 1px;
}
.fk-stats b { color: var(--txt-main); font-weight: 500; }
.fk-stats .gold { color: var(--neon-gold); }
.fk-stats .green { color: var(--neon-green); }
.fk-stats .cyan { color: var(--neon-cyan); }

.lista-pusta {
    padding: 40px 20px; text-align: center;
    color: var(--txt-mute); font-style: italic;
    background: rgba(0,0,0,0.4); border: 1px dashed var(--border-soft); border-radius: 1px;
}
</style>

<header class="page-head">
    <div class="eyebrow">★ THE ABYSS // BUSINESS DIRECTORY</div>
    <h1>Katalog firm</h1>
    <p class="lead">Pełen rejestr legalnych firm w mieście. Łącznie <b style="color: var(--neon-gold);"><?php echo $total_firm; ?></b> aktywnych przedsiębiorstw — od podziemnych klubów po galerie sztuki.</p>
</header>

<!-- WYSZUKIWARKA -->
<form method="GET" class="lista-search-row">
    <input type="hidden" name="page" value="lista_firm">
    <?php if ($filtr_branzy !== ''): ?>
        <input type="hidden" name="branza" value="<?php echo htmlspecialchars($filtr_branzy); ?>">
    <?php endif; ?>
    <input type="text" name="szukaj" class="lista-search-input" placeholder="Szukaj nazwy lub sloganu..." value="<?php echo htmlspecialchars($szukana_fraza); ?>">
    <button type="submit" class="lista-search-btn">Szukaj</button>
    <?php if ($szukana_fraza !== '' || $filtr_branzy !== ''): ?>
        <a href="game.php?page=lista_firm" class="lista-search-btn" style="background: transparent; text-decoration: none; display: inline-flex; align-items: center;">Wyczyść</a>
    <?php endif; ?>
</form>

<!-- FILTRY BRANŻ -->
<div class="filtr-branz">
    <a href="game.php?page=lista_firm<?php if ($szukana_fraza !== '') echo '&szukaj=' . urlencode($szukana_fraza); ?>"
       class="filtr-tab <?php if ($filtr_branzy === '') echo 'aktywny'; ?>">
        ⌖ Wszystkie
    </a>
    <?php foreach ($branze_def as $kod => $b): ?>
        <a href="game.php?page=lista_firm&branza=<?php echo urlencode($kod); ?><?php if ($szukana_fraza !== '') echo '&szukaj=' . urlencode($szukana_fraza); ?>"
           class="filtr-tab <?php if ($filtr_branzy === $kod) echo 'aktywny'; ?>">
            <?php echo $b['ikona'] . ' ' . htmlspecialchars($b['nazwa']); ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- LISTA FIRM -->
<?php if (count($firmy_lista) > 0): ?>
    <?php foreach ($firmy_lista as $f):
        $b = $branze_def[$f['branza_kod']] ?? ['nazwa' => 'Nieznana', 'ikona' => '🏢', 'kolor' => '#888888'];
    ?>
        <a href="game.php?page=profil_firmy&id=<?php echo (int)$f['id']; ?>" class="firma-karta-link">
            <div class="fk-banner <?php if (empty($f['banner_url'])) echo 'placeholder'; ?>"
                 <?php if (!empty($f['banner_url'])): ?>style="background-image: url('<?php echo htmlspecialchars($f['banner_url']); ?>');"<?php endif; ?>>
                <?php if (empty($f['banner_url'])): ?><span><?php echo $b['ikona']; ?></span><?php endif; ?>
            </div>

            <div class="fk-tresc">
                <div class="fk-logo" <?php if (!empty($f['logo_url'])): ?>style="background-image: url('<?php echo htmlspecialchars($f['logo_url']); ?>');"<?php endif; ?>>
                    <?php if (empty($f['logo_url'])): ?><?php echo $b['ikona']; ?><?php endif; ?>
                </div>
                <div class="fk-info">
                    <div class="fk-nazwa"><?php echo htmlspecialchars($f['nazwa']); ?></div>
                    <div class="fk-branza" style="color: <?php echo $b['kolor']; ?>;"><?php echo htmlspecialchars($b['nazwa']); ?></div>
                    <?php if (!empty($f['slogan'])): ?>
                        <div class="fk-slogan">&bdquo;<?php echo htmlspecialchars($f['slogan']); ?>&rdquo;</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="fk-stats">
                <span>// Właściciel: <b class="gold">★ <?php echo htmlspecialchars($f['wlasciciel_login'] ?? '?'); ?></b></span>
                <span>// Załoga: <b class="green"><?php echo (int)$f['pracownikow']; ?></b> / <?php echo FIRMA_MAX_PRACOWNIKOW; ?></span>
                <span>// Reputacja: <b class="cyan"><?php echo (int)$f['reputacja']; ?></b></span>
                <span>// Założono: <b><?php echo date('d.m.Y', strtotime($f['data_zalozenia'])); ?></b></span>
            </div>
        </a>
    <?php endforeach; ?>
<?php else: ?>
    <div class="lista-pusta">
        <?php if ($filtr_branzy !== '' || $szukana_fraza !== ''): ?>
            // brak firm spełniających kryteria — spróbuj innej branży lub wyczyść filtry //
        <?php else: ?>
            // brak zarejestrowanych firm w mieście //<br>
            <a href="game.php?page=firma" style="color: var(--neon-gold); font-family: 'JetBrains Mono', monospace; font-size: .85em; letter-spacing: 2px; margin-top: 12px; display: inline-block;">→ Zarejestruj pierwszą</a>
        <?php endif; ?>
    </div>
<?php endif; ?>