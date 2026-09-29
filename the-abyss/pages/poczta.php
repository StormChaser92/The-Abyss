<?php
require_once __DIR__ . '/../includes/formatuj.php';
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
        $komunikat = "<div class='blad'>Wypełnij wszystkie pola.</div>";
    } else {
        // Szukamy ID odbiorcy na podstawie loginu
        $wynik_odb = $polaczenie->query("SELECT id FROM gracze WHERE login = '$odbiorca_login'");
        if ($wynik_odb->num_rows > 0) {
            $odbiorca = $wynik_odb->fetch_assoc();
            $odbiorca_id = $odbiorca['id'];
            
            $polaczenie->query("INSERT INTO wiadomosci (nadawca_id, odbiorca_id, tytul, tresc) VALUES ($id_gracza, $odbiorca_id, '$tytul', '$tresc')");
            $komunikat = "<div class='sukces'>Wiadomość została wysłana do obywatela: " . htmlspecialchars(trim($_POST['odbiorca'])) . ".</div>";
            $zakladka = 'wyslane'; // Po wysłaniu przerzucamy do wysłanych
        } else {
            $komunikat = "<div class='blad'>Gracz o nicku „" . htmlspecialchars(trim($_POST['odbiorca'])) . "” nie istnieje.</div>";
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

// List oznacza się jako przeczytany dopiero po otwarciu (game.php robi to przed licznikiem w belce).
$otwarty_id = ($zakladka == 'odbiorcza' && isset($_GET['list'])) ? (int)$_GET['list'] : 0;

$wyslany_id = ($zakladka == 'wyslane' && isset($_GET['list'])) ? (int)$_GET['list'] : 0;
$id_gracza = (int)$id_gracza;
$n_nowych = (int)(db_wiersz($polaczenie, "SELECT COUNT(*) c FROM wiadomosci WHERE odbiorca_id = ? AND odczytana = 0", [$id_gracza])['c'] ?? 0);
$n_alertow = (int)(db_wiersz($polaczenie, "SELECT COUNT(*) c FROM powiadomienia WHERE gracz_id = ? AND odczytane = 0", [$id_gracza])['c'] ?? 0);
$fmt = fn($d) => date('d.m.Y · H:i', strtotime((string)$d));
?>
<style>
.pc{display:grid;gap:18px}
.pc-tabs{display:flex;flex-wrap:wrap;gap:8px}
.pc-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:1px solid var(--border-soft);background:rgba(0,0,0,.35);color:var(--txt-dim);text-decoration:none;font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:2px;text-transform:uppercase;font-size:.88em;border-radius:1px;transition:all .25s}
.pc-tab:hover{color:#fff;border-color:var(--border-mid);background:rgba(255,23,68,.08)}
.pc-tab.aktywny{color:#fff;border-color:var(--neon-red-hot);background:rgba(255,23,68,.14);box-shadow:0 0 14px rgba(255,23,68,.35),inset 0 0 12px rgba(255,23,68,.12);text-shadow:0 0 6px rgba(255,23,68,.7)}
.pc-tab b{min-width:18px;height:18px;padding:0 5px;display:inline-flex;align-items:center;justify-content:center;border-radius:9px;background:var(--neon-red-deep);border:1px solid var(--neon-red-hot);font-family:'JetBrains Mono',monospace;font-size:.75em;font-weight:500;letter-spacing:0}
.pc-tab b.ember{background:#8a2a00;border-color:var(--neon-ember)}
.pc-panel{background:rgba(18,10,18,.45);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);border:1px solid var(--border-soft);border-radius:2px;padding:18px;position:relative}
.pc-panel::before{content:'';position:absolute;top:0;left:0;width:28px;height:1px;background:var(--neon-red);box-shadow:0 0 6px var(--neon-red)}
.pc-panel h2{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.05em;text-transform:uppercase;letter-spacing:2px;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.pc-panel h2 .tag{font-family:'JetBrains Mono',monospace;font-size:.65em;color:var(--neon-red);letter-spacing:2px;font-weight:400;padding:2px 6px;border:1px solid var(--border-soft)}
.pc-opis{color:var(--txt-dim);margin:-6px 0 14px;line-height:1.5}
.pc-pusto{color:var(--txt-mute);text-align:center;padding:26px 10px;font-family:'JetBrains Mono',monospace;font-size:.85em;letter-spacing:1px}
.pc-lista{display:grid;gap:6px}
.pc-w{display:grid;grid-template-columns:10px minmax(0,1fr) auto;gap:14px;align-items:center;padding:12px 14px;background:rgba(0,0,0,.35);border:1px solid rgba(255,23,68,.1);border-left:2px solid transparent;border-radius:1px;transition:background .2s,border-color .2s}
.pc-w:hover{background:rgba(255,23,68,.05);border-color:var(--border-soft)}
.pc-w.nowy{border-left-color:var(--neon-red-hot);background:rgba(255,23,68,.07)}
.pc-kropka{width:7px;height:7px;border-radius:50%}
.pc-w.nowy .pc-kropka{background:var(--neon-red-hot);box-shadow:0 0 8px var(--neon-red);animation:dot-pulse 2s infinite}
.pc-info{min-width:0;display:grid;gap:3px}
.pc-tytul{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.08em;letter-spacing:.5px;color:var(--txt-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pc-w.nowy .pc-tytul{color:#fff}
.pc-od{font-size:.92em;color:var(--txt-mute);display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.pc-od .nk-n{color:var(--txt-main)}
.pc-status{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1px;text-transform:uppercase}
.pc-status.ok{color:var(--txt-mute)}.pc-status.czeka{color:var(--neon-ember)}
.pc-akcje{display:flex;gap:8px;align-items:center}
.pc-akcje form{margin:0}
.pc-btn{display:inline-block;padding:7px 14px;background:rgba(255,23,68,.08);border:1px solid var(--border-mid);color:#fff;font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:2px;text-transform:uppercase;font-size:.8em;cursor:pointer;border-radius:1px;text-decoration:none;transition:all .25s}
.pc-btn:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 16px rgba(255,23,68,.7)}
.pc-btn.ghost{background:transparent;border-color:var(--border-soft);color:var(--txt-dim)}
.pc-btn.ghost:hover{color:#fff;border-color:var(--neon-red);background:rgba(255,23,68,.1);box-shadow:none}
.pc-btn.duzy{padding:12px 20px;font-size:.9em;width:100%}
.pc-powrot{display:inline-block;color:var(--txt-mute);text-decoration:none;font-family:'JetBrains Mono',monospace;font-size:.75em;letter-spacing:2px;margin-bottom:14px}
.pc-powrot:hover{color:#fff}
.pc-list-glowa{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:12px;margin-bottom:14px;border-bottom:1px dashed rgba(255,23,68,.15);color:var(--txt-mute);font-size:.92em}
.pc-list-glowa time{font-family:'JetBrains Mono',monospace;font-size:.85em}
.pc-list-tytul{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.6em;letter-spacing:1.5px;text-transform:uppercase;color:#fff;margin-bottom:14px;line-height:1.15;overflow-wrap:anywhere}
.pc-list-tresc{color:var(--txt-main);line-height:1.65;white-space:pre-wrap;font-size:1.02em;overflow-wrap:anywhere}
.pc-list-akcje{display:flex;justify-content:space-between;gap:10px;margin-top:18px;padding-top:14px;border-top:1px dashed rgba(255,23,68,.15);flex-wrap:wrap}
.pc-pole{display:grid;gap:6px;margin-bottom:14px}
.pc-pole label{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:2px;color:var(--txt-mute);text-transform:uppercase}
.pc-pole input,.pc-pole textarea{width:100%;background:rgba(0,0,0,.5);border:1px solid var(--border-soft);color:#fff;padding:10px 12px;font-family:'Rajdhani',sans-serif;font-size:1.02em;border-radius:1px}
.pc-pole textarea{min-height:170px;resize:vertical;line-height:1.5}
.pc-pole input:focus,.pc-pole textarea:focus{outline:none;border-color:var(--neon-red-hot);box-shadow:0 0 10px rgba(255,23,68,.25)}
.pc-alert{display:grid;gap:4px;padding:12px 14px;background:rgba(0,0,0,.35);border:1px solid rgba(255,23,68,.1);border-left:2px solid transparent}
.pc-alert.nowy{border-left-color:var(--neon-ember);background:rgba(255,122,61,.06)}
.pc-alert time{font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-mute);letter-spacing:1px}
.pc-alert div{color:var(--txt-main);line-height:1.5}
.pc .sukces,.pc .blad{padding:12px 14px;border:1px solid;font-family:'JetBrains Mono',monospace;font-size:.85em}
.pc .sukces{color:var(--neon-green);border-color:rgba(90,255,154,.35);background:rgba(90,255,154,.05)}
.pc .blad{color:var(--neon-red-hot);border-color:var(--border-mid);background:rgba(255,23,68,.06)}
@media(max-width:600px){.pc-w{grid-template-columns:10px minmax(0,1fr)}.pc-akcje{grid-column:2}}
</style>

<div class="pc">
<div class="page-head">
    <div class="eyebrow">// TERMINAL KOMUNIKACYJNY</div>
    <h1>Poczta</h1>
    <p class="lead">Listy od innych obywateli. Powiadomienia systemowe są w osobnej zakładce.</p>
</div>

<nav class="pc-tabs">
    <a href="game.php?page=poczta&zakladka=odbiorcza" class="pc-tab<?php if ($zakladka == 'odbiorcza') echo ' aktywny'; ?>">Odebrane<?php if ($n_nowych) echo "<b>$n_nowych</b>"; ?></a>
    <a href="game.php?page=poczta&zakladka=wyslane" class="pc-tab<?php if ($zakladka == 'wyslane') echo ' aktywny'; ?>">Wysłane</a>
    <a href="game.php?page=poczta&zakladka=napisz" class="pc-tab<?php if ($zakladka == 'napisz') echo ' aktywny'; ?>">Napisz</a>
    <a href="game.php?page=poczta&zakladka=alerty" class="pc-tab<?php if ($zakladka == 'alerty') echo ' aktywny'; ?>">Powiadomienia<?php if ($n_alertow) echo "<b class='ember'>$n_alertow</b>"; ?></a>
</nav>

<?php echo $komunikat; ?>

<?php if (($zakladka == 'odbiorcza' && $otwarty_id) || ($zakladka == 'wyslane' && $wyslany_id)):
    $odebrany = $zakladka == 'odbiorcza';
    $w = $odebrany
        ? db_wiersz($polaczenie, "SELECT w.*, " . nk_pola('g', 'n_') . " FROM wiadomosci w JOIN gracze g ON w.nadawca_id = g.id WHERE w.id = ? AND w.odbiorca_id = ?", [$otwarty_id, $id_gracza])
        : db_wiersz($polaczenie, "SELECT w.*, " . nk_pola('g', 'n_') . " FROM wiadomosci w JOIN gracze g ON w.odbiorca_id = g.id WHERE w.id = ? AND w.nadawca_id = ?", [$wyslany_id, $id_gracza]);
?>
    <section class="pc-panel">
        <a href="game.php?page=poczta&zakladka=<?php echo $zakladka; ?>" class="pc-powrot">← <?php echo $odebrany ? 'ODEBRANE' : 'WYSŁANE'; ?></a>
        <?php if (!$w): ?>
            <p class="pc-pusto">// Tego listu już nie ma.</p>
        <?php else: ?>
            <div class="pc-list-glowa">
                <span class="pc-od"><?php echo $odebrany ? 'Od:' : 'Do:'; ?> <?php echo nk_html(nk_wiersz($w, 'n_')); ?><?php if (!$odebrany) echo (int)$w['odczytana'] === 1 ? " <span class='pc-status ok'>· przeczytany</span>" : " <span class='pc-status czeka'>· nieprzeczytany</span>"; ?></span>
                <time><?php echo $fmt($w['data_wyslania']); ?></time>
            </div>
            <h2 class="pc-list-tytul"><?php echo htmlspecialchars($w['tytul']); ?></h2>
            <div class="pc-list-tresc"><?php echo htmlspecialchars($w['tresc']); ?></div>
            <div class="pc-list-akcje">
                <?php if ($odebrany): ?><a href="game.php?page=poczta&zakladka=napisz&do=<?php echo urlencode($w['n_login']); ?>&re=<?php echo urlencode('RE: ' . $w['tytul']); ?>" class="pc-btn">↩ Odpowiedz</a><?php else: ?><span></span><?php endif; ?>
                <form method="POST" action="game.php?page=poczta&zakladka=<?php echo $zakladka; ?>" onsubmit="return confirm('Usunąć ten list?')" style="margin:0"><input type="hidden" name="id_wiadomosci" value="<?php echo (int)$w['id']; ?>"><button type="submit" name="usun_wiadomosc" class="pc-btn ghost"><?php echo $odebrany ? 'Usuń' : 'Usuń z historii'; ?></button></form>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($zakladka == 'odbiorcza' || $zakladka == 'wyslane'):
    $odebrany = $zakladka == 'odbiorcza';
    $listy = $odebrany
        ? db_wiersze($polaczenie, "SELECT w.id, w.tytul, w.odczytana, " . nk_pola('g', 'n_') . " FROM wiadomosci w JOIN gracze g ON w.nadawca_id = g.id WHERE w.odbiorca_id = ? ORDER BY w.data_wyslania DESC", [$id_gracza])
        : db_wiersze($polaczenie, "SELECT w.id, w.tytul, w.odczytana, " . nk_pola('g', 'n_') . " FROM wiadomosci w JOIN gracze g ON w.odbiorca_id = g.id WHERE w.nadawca_id = ? ORDER BY w.data_wyslania DESC", [$id_gracza]);
?>
    <section class="pc-panel">
        <h2><?php echo $odebrany ? 'Odebrane listy' : 'Wysłane listy'; ?> <span class="tag"><?php echo count($listy); ?></span></h2>
        <?php if (!$listy): ?>
            <p class="pc-pusto">// <?php echo $odebrany ? 'Skrzynka jest pusta.' : 'Nie wysłano jeszcze żadnego listu.'; ?></p>
        <?php else: ?>
            <div class="pc-lista">
            <?php foreach ($listy as $w): $nowy = $odebrany && (int)$w['odczytana'] === 0; ?>
                <div class="pc-w<?php echo $nowy ? ' nowy' : ''; ?>">
                    <span class="pc-kropka"<?php echo $nowy ? ' title="Nieprzeczytany"' : ''; ?>></span>
                    <div class="pc-info">
                        <span class="pc-tytul"><?php echo htmlspecialchars($w['tytul']); ?></span>
                        <span class="pc-od"><?php echo $odebrany ? 'Od:' : 'Do:'; ?> <?php echo nk_html(nk_wiersz($w, 'n_')); ?><?php if (!$odebrany) echo (int)$w['odczytana'] === 1 ? " <span class='pc-status ok'>· przeczytany</span>" : " <span class='pc-status czeka'>· nieprzeczytany</span>"; ?></span>
                    </div>
                    <div class="pc-akcje">
                        <a href="game.php?page=poczta&zakladka=<?php echo $zakladka; ?>&list=<?php echo (int)$w['id']; ?>" class="pc-btn">Otwórz</a>
                        <form method="POST" onsubmit="return confirm('Usunąć ten list?')"><input type="hidden" name="id_wiadomosci" value="<?php echo (int)$w['id']; ?>"><button type="submit" name="usun_wiadomosc" class="pc-btn ghost">Usuń</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($zakladka == 'napisz'):
    $domyslny_odbiorca = isset($_GET['do']) ? htmlspecialchars($_GET['do']) : "";
    $domyslny_tytul = isset($_GET['re']) ? htmlspecialchars($_GET['re']) : "";
?>
    <section class="pc-panel">
        <h2>Nowy list <span class="tag">NAPISZ</span></h2>
        <form method="POST">
            <div class="pc-pole"><label for="pc-do">Odbiorca (dokładny nick)</label><input type="text" id="pc-do" name="odbiorca" value="<?php echo $domyslny_odbiorca; ?>" required autocomplete="off"></div>
            <div class="pc-pole"><label for="pc-tytul">Tytuł</label><input type="text" id="pc-tytul" name="tytul" value="<?php echo $domyslny_tytul; ?>" required maxlength="100"></div>
            <div class="pc-pole"><label for="pc-tresc">Treść</label><textarea id="pc-tresc" name="tresc" required></textarea></div>
            <button type="submit" name="wyslij_wiadomosc" class="pc-btn duzy">Wyślij list</button>
        </form>
    </section>

<?php elseif ($zakladka == 'alerty'):
    $alerty = db_wiersze($polaczenie, "SELECT * FROM powiadomienia WHERE gracz_id = ? ORDER BY data_utworzenia DESC LIMIT 50", [$id_gracza]);
    db_q($polaczenie, "UPDATE powiadomienia SET odczytane = 1 WHERE gracz_id = ? AND odczytane = 0", [$id_gracza]);   // po odczycie listy, żeby nowe były jeszcze wyróżnione
?>
    <section class="pc-panel">
        <h2>Powiadomienia <span class="tag">SYSTEM</span></h2>
        <p class="pc-opis">Akcje innych graczy wobec Ciebie, wyniki zleceń, sesje i komunikaty The Abyss.</p>
        <?php if (!$alerty): ?>
            <p class="pc-pusto">// Cisza na łączach.</p>
        <?php else: ?>
            <div class="pc-lista">
            <?php foreach ($alerty as $a): ?>
                <div class="pc-alert<?php echo (int)$a['odczytane'] === 0 ? ' nowy' : ''; ?>"><time><?php echo $fmt($a['data_utworzenia']); ?></time><div><?php echo html_bezpieczny((string)$a['tresc']); ?></div></div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
</div>
