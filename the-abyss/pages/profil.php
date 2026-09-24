<?php
require_once "db.php";
require_once __DIR__ . "/../includes/profil_sanitizer.php";

if (!isset($_SESSION['id_gracza'])) {
    echo "<div style='padding:50px;color:#ff3333'>Brak sesji gracza.</div>";
    exit;
}

$id_gracza = (int)$_SESSION['id_gracza'];
if (empty($_SESSION['csrf_profil'])) {
    $_SESSION['csrf_profil'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_profil'];

function abyss_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function abyss_redirect_profile(int $id, string $tab = 'kartoteka', string $extra = ''): void {
    $url = "game.php?page=profil&id=" . $id . "&tab=" . urlencode($tab) . $extra;
    header("Location: $url");
    exit;
}

function abyss_get_history(mysqli $polaczenie, int $gracz_id): array {
    if (!abyss_table_exists($polaczenie, 'profile_historie')) {
        return ['exists' => false, 'historia_html' => '', 'widocznosc' => 'publiczna', 'updated_at' => null];
    }
    $stmt = $polaczenie->prepare("SELECT historia_html, widocznosc, updated_at FROM profile_historie WHERE gracz_id=? LIMIT 1");
    $stmt->bind_param('i', $gracz_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $row['exists'] = true;
        return $row;
    }
    return ['exists' => true, 'historia_html' => '', 'widocznosc' => 'publiczna', 'updated_at' => null];
}

function generuj_odznaki($profil, $duze = false) {
    $odznaki = [];
    $size = $duze ? '1em' : '.72em';
    $pad  = $duze ? '4px 12px' : '2px 8px';

    if (!empty($profil['is_premium'])) {
        $odznaki[] = "<span class='odznaka o-vip' style='font-size:$size;padding:$pad'>★ VIP</span>";
    }
    if (!empty($profil['is_mg'])) {
        $odznaki[] = "<span class='odznaka o-mg' style='font-size:$size;padding:$pad'>🎭 MG</span>";
    }
    if (!empty($profil['is_proboszcz'])) {
        $odznaki[] = "<span class='odznaka o-proboszcz' style='font-size:$size;padding:$pad'>⛪ Proboszcz</span>";
    }
    if (!empty($profil['is_barman'])) {
        $odznaki[] = "<span class='odznaka o-barman' style='font-size:$size;padding:$pad'>🍸 Barman</span>";
    }
    return implode(' ', $odznaki);
}

// Gracz oglądający profil — do trybu pacyfisty przy przycisku ataku.
$stmt = $polaczenie->prepare("SELECT tryb_pacyfisty FROM gracze WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id_gracza);
$stmt->execute();
$gracz = $stmt->get_result()->fetch_assoc();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $cel_id = $id_gracza;
} else {
    $cel_id = (int)$_GET['id'];
}

$tab = $_GET['tab'] ?? 'kartoteka';
$dozwolone_taby = ['kartoteka', 'historia', 'dziennik', 'relacje', 'sesje', 'plotki'];
if (!in_array($tab, $dozwolone_taby, true)) $tab = 'kartoteka';
$edit_historia = ($cel_id === $id_gracza && $tab === 'historia' && isset($_GET['edit']));

// Zapis bogatej historii profilu.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zapisz_historie_profilu']) && $cel_id === $id_gracza) {
    if (!hash_equals($_SESSION['csrf_profil'], $_POST['csrf'] ?? '')) {
        echo "<div class='profil-alert blad'>Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.</div>";
        exit;
    }

    if (!abyss_table_exists($polaczenie, 'profile_historie')) {
        echo "<div class='profil-alert blad'>Brakuje tabeli profile_historie. Najpierw uruchom plik SQL z paczki.</div>";
        exit;
    }

    $raw_html = $_POST['historia_html'] ?? '';
    $safe_html = abyss_sanitize_profile_html($raw_html);
    $widocznosc = ($_POST['widocznosc'] ?? 'publiczna') === 'prywatna' ? 'prywatna' : 'publiczna';

    // Wersjonowanie — jeśli tabela istnieje, zapisz kopię po każdym zapisie.
    if (abyss_table_exists($polaczenie, 'profile_historie_wersje')) {
        $stmt_v = $polaczenie->prepare("INSERT INTO profile_historie_wersje (gracz_id, historia_html) VALUES (?, ?)");
        if ($stmt_v) {
            $stmt_v->bind_param('is', $id_gracza, $safe_html);
            $stmt_v->execute();
        }
    }

    $stmt_up = $polaczenie->prepare("INSERT INTO profile_historie (gracz_id, historia_html, widocznosc) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE historia_html=VALUES(historia_html), widocznosc=VALUES(widocznosc), updated_at=NOW()");
    $stmt_up->bind_param('iss', $id_gracza, $safe_html, $widocznosc);
    $stmt_up->execute();

    abyss_redirect_profile($id_gracza, 'historia', '&saved=1');
}

// Interakcje na profilu innych graczy.
if ($_SERVER["REQUEST_METHOD"] === "POST" && $cel_id !== $id_gracza) {
    if (!hash_equals($_SESSION['csrf_profil'], $_POST['csrf'] ?? '')) {
        echo "<script>alert('Nieprawidłowy token bezpieczeństwa.');</script>";
    } else {
        if (isset($_POST['akcja_roza'])) {
            $koszt_rozy = 50;
            $stmt = $polaczenie->prepare("SELECT gotowka, login FROM gracze WHERE id=? LIMIT 1");
            $stmt->bind_param('i', $id_gracza);
            $stmt->execute();
            $ja = $stmt->get_result()->fetch_assoc();

            if ($ja && (int)$ja['gotowka'] >= $koszt_rozy) {
                $tresc_alertu = "Obywatel <b style='color:#ff00ff;'>" . abyss_e($ja['login']) . "</b> wysłał Ci różę! 🌹 To rzadki gest sympatii w The Abyss.";
                $stmt_i = $polaczenie->prepare("INSERT INTO powiadomienia (gracz_id, tresc) VALUES (?, ?)");
                $stmt_i->bind_param('is', $cel_id, $tresc_alertu);
                $stmt_i->execute();
                $stmt_u = $polaczenie->prepare("UPDATE gracze SET gotowka = gotowka - ? WHERE id = ?");
                $stmt_u->bind_param('ii', $koszt_rozy, $id_gracza);
                $stmt_u->execute();
                echo "<script>alert('Wysłano różę za 50 $!'); window.location.href='game.php?page=profil&id=$cel_id';</script>";
                exit;
            } else {
                echo "<script>alert('Nie stać Cię nawet na jedną różę...');</script>";
            }
        }

        if (isset($_POST['akcja_zaczep'])) {
            $stmt = $polaczenie->prepare("SELECT login FROM gracze WHERE id=? LIMIT 1");
            $stmt->bind_param('i', $id_gracza);
            $stmt->execute();
            $ja_login = $stmt->get_result()->fetch_assoc()['login'] ?? 'Ktoś';
            $tresc_alertu = "<b style='color:#00ccff;'>" . abyss_e($ja_login) . "</b> zaczepił Cię na ulicy! Może warto do niego napisać?";
            $stmt_i = $polaczenie->prepare("INSERT INTO powiadomienia (gracz_id, tresc) VALUES (?, ?)");
            $stmt_i->bind_param('is', $cel_id, $tresc_alertu);
            $stmt_i->execute();
            echo "<script>alert('Zaczepiłeś gracza.'); window.location.href='game.php?page=profil&id=$cel_id';</script>";
            exit;
        }
    }
}

if ($cel_id !== $id_gracza) {
    $stmt = $polaczenie->prepare("UPDATE gracze SET wyswietlenia_profilu = wyswietlenia_profilu + 1 WHERE id = ?");
    if ($stmt) { $stmt->bind_param('i', $cel_id); $stmt->execute(); }
}

$sql = "SELECT g.id, g.login, g.avatar, g.poziom, g.klasa, g.profesja_fabularna, g.opis_profilu,
        g.ostatnia_aktywnosc, g.data_rejestracji, g.is_premium, g.is_mg, g.is_proboszcz, g.is_barman,
        g.syndykat_rola, g.bonus_atak, g.bonus_obrona, g.tryb_pacyfisty,
        s.nazwa AS nazwa_syndykatu, s.tag AS tag_syndykatu
        FROM gracze g
        LEFT JOIN syndykaty s ON g.syndykat_id = s.id
        WHERE g.id = ?";
$stmt = $polaczenie->prepare($sql);
$stmt->bind_param('i', $cel_id);
$stmt->execute();
$wynik = $stmt->get_result();

if ($wynik->num_rows === 0) {
    echo "<div style='padding: 50px; text-align: center; color: #ff3333; font-family: Oswald; font-size: 2em; text-shadow: 0 0 10px #ff3333;'>Taki gracz nie istnieje w bazie The Abyss.</div>";
    exit;
}
$profil = $wynik->fetch_assoc();
$historia = abyss_get_history($polaczenie, $cel_id);

$malzenstwo_profilu = null;
if (abyss_table_exists($polaczenie, 'malzenstwa')) {
    $stmt_m = $polaczenie->prepare("SELECT m.*,
        g1.id AS m1_id, g1.login AS m1_login, g1.avatar AS m1_avatar,
        g2.id AS m2_id, g2.login AS m2_login, g2.avatar AS m2_avatar
        FROM malzenstwa m
        JOIN gracze g1 ON m.malzonek_1_id = g1.id
        JOIN gracze g2 ON m.malzonek_2_id = g2.id
        WHERE (m.malzonek_1_id=? OR m.malzonek_2_id=?) AND m.status='aktywne' LIMIT 1");
    if ($stmt_m) {
        $stmt_m->bind_param('ii', $cel_id, $cel_id);
        $stmt_m->execute();
        $malzenstwo_profilu = $stmt_m->get_result()->fetch_assoc();
    }
}

$avatar = !empty($profil['avatar']) ? abyss_e($profil['avatar']) : '';
$opis = !empty($profil['opis_profilu']) ? nl2br(abyss_e($profil['opis_profilu'])) : "<span class='pusto'>Ten gracz woli pozostać w cieniu. Brak wpisu w kartotece.</span>";
$klasa_nicku = !empty($profil['is_premium']) ? ' vip' : '';

$ostatnio = strtotime($profil['ostatnia_aktywnosc'] ?? 'now');
$roznica_minut = round((time() - $ostatnio) / 60);
if ($roznica_minut < 15) {
    $status_online = "<span class='status-on'>● Dostępny/a na ulicach</span>";
} else {
    $status_online = "<span class='status-off'>○ Ostatnio: " . abyss_e(date('d.m.Y H:i', $ostatnio)) . "</span>";
}

$story_public = $historia['widocznosc'] !== 'prywatna' || $cel_id === $id_gracza;
$story_html = $story_public ? ($historia['historia_html'] ?? '') : '';
?>

<style>
/* ═══ PROFIL — skóra gry: szkło, czerwony neon, Oswald / Rajdhani / JetBrains Mono ═══ */
.profil-kontener{display:grid;grid-template-columns:300px minmax(0,1fr);gap:22px;align-items:start;margin-top:4px}
.profil-lewy,.karta-info,.profil-tabs{background:rgba(10,8,14,.55);backdrop-filter:blur(10px) saturate(140%);-webkit-backdrop-filter:blur(10px) saturate(140%);border:1px solid var(--border-soft);border-radius:2px;position:relative}
.profil-lewy::before,.karta-info::before{content:'';position:absolute;top:-1px;left:16px;width:36px;height:1px;background:var(--neon-red);box-shadow:0 0 8px var(--neon-red)}
.profil-lewy{padding:18px;text-align:center;position:sticky;top:0}
.profil-avatar{width:100%;aspect-ratio:500/625;background:linear-gradient(160deg,#2a0a14,#0a0408 70%);background-position:top center!important;background-size:cover!important;border:1px solid var(--border-mid);border-radius:2px;margin-bottom:16px;box-shadow:0 0 22px rgba(255,23,68,.22),inset 0 0 30px rgba(0,0,0,.5);position:relative;overflow:hidden}
.profil-avatar::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 60%,rgba(255,23,68,.12));pointer-events:none}
.profil-nick{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.9em;line-height:1.05;margin:0 0 6px;text-transform:uppercase;letter-spacing:3px;color:#fff;overflow-wrap:anywhere;text-shadow:0 0 4px rgba(255,255,255,.4),0 0 16px var(--neon-red),0 0 32px var(--neon-red-deep)}
.profil-nick.vip{color:var(--neon-gold);text-shadow:0 0 12px rgba(255,215,0,.55)}
.profil-status{font-family:'JetBrains Mono',monospace;font-size:.74em;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:14px}
.status-on{color:var(--neon-green);text-shadow:0 0 6px rgba(90,255,154,.4)}
.status-off{color:var(--txt-mute)}
.odznaki-profil{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin-bottom:12px}
.odznaka{display:inline-flex;align-items:center;gap:4px;border-radius:1px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1.5px;font-weight:500;border:1px solid;white-space:nowrap}
.o-vip,.o-proboszcz{color:var(--neon-gold);border-color:rgba(255,215,0,.45);background:rgba(255,215,0,.07)}
.o-mg{color:#c896ff;border-color:rgba(200,150,255,.45);background:rgba(200,150,255,.08)}
.o-barman{color:var(--neon-ember);border-color:rgba(255,122,61,.45);background:rgba(255,122,61,.08)}
.malz-baner{background:rgba(255,23,68,.05);border:1px solid var(--border-soft);border-left:2px solid var(--neon-red);border-radius:2px;padding:10px 12px;margin:14px 0;display:flex;align-items:center;gap:12px}
.malz-info{flex:1;text-align:left;min-width:0}
.malz-label{color:var(--neon-red);font-family:'JetBrains Mono',monospace;font-size:.68em;text-transform:uppercase;letter-spacing:2px;margin-bottom:3px}
.malz-nick{color:#fff;font-family:'Oswald',sans-serif;letter-spacing:1px;text-decoration:none;display:flex;align-items:center;gap:8px}
.malz-nick:hover{color:var(--neon-red-hot)}
.malz-mini-av{width:28px;height:28px;border-radius:50%;background-size:cover;background-position:top center;border:1px solid var(--border-mid);flex-shrink:0}
.malz-dni{color:var(--txt-dim);font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1px;text-align:right;text-transform:uppercase}
.malz-dni b{display:block;font-family:'Oswald',sans-serif;font-size:1.7em;font-weight:500;color:#fff;letter-spacing:0}
.profil-akcje{display:grid;gap:8px;margin-top:4px}
.profil-akcje form{display:grid;gap:8px;margin:0}
.btn-akcja-profil{display:block;width:100%;padding:10px 14px;background:rgba(255,23,68,.08);border:1px solid var(--border-mid);color:#fff;font-family:'Oswald',sans-serif;font-weight:500;font-size:.9em;letter-spacing:2px;text-transform:uppercase;text-decoration:none;text-align:center;border-radius:1px;cursor:pointer;transition:.25s}
.btn-akcja-profil:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 18px rgba(255,23,68,.7);text-shadow:0 0 6px rgba(255,255,255,.8)}
.btn-akcja-profil.ghost{background:transparent;border-color:var(--border-soft);color:var(--txt-dim)}
.btn-akcja-profil.ghost:hover{background:rgba(255,23,68,.1);border-color:var(--neon-red);color:#fff;box-shadow:none;text-shadow:none}
.btn-akcja-profil.ember{background:rgba(255,122,61,.07);border-color:rgba(255,122,61,.4);color:#ffd9c4}
.btn-akcja-profil.ember:hover{background:var(--neon-ember);border-color:var(--neon-ember);color:#05060c;box-shadow:0 0 18px rgba(255,122,61,.6);text-shadow:none}
.btn-atak{margin-top:10px;padding:13px 14px;font-size:1em;letter-spacing:3px;background:linear-gradient(135deg,rgba(255,23,68,.18),rgba(179,0,27,.32));border:1px solid var(--neon-red);text-shadow:0 0 8px var(--neon-red);box-shadow:0 0 14px rgba(255,23,68,.35),inset 0 0 18px rgba(255,23,68,.12)}
.btn-atak:hover{background:linear-gradient(135deg,rgba(255,23,68,.4),rgba(179,0,27,.55));box-shadow:0 0 30px rgba(255,23,68,.75)}
.btn-pacyfista{margin-top:10px;background:rgba(0,0,0,.4);border-color:rgba(255,255,255,.1);color:var(--txt-mute);cursor:not-allowed}
.btn-pacyfista:hover{background:rgba(0,0,0,.4);color:var(--txt-mute);box-shadow:none;text-shadow:none}
.profil-prawy{display:flex;flex-direction:column;gap:16px;min-width:0}
.profil-tabs{display:flex;flex-wrap:wrap;gap:2px;padding:6px}
.profil-tab{font-family:'Oswald',sans-serif;font-size:.86em;letter-spacing:2px;text-transform:uppercase;text-decoration:none;color:var(--txt-dim);padding:9px 14px;border-bottom:2px solid transparent;transition:.2s}
.profil-tab:hover{color:#fff;background:rgba(255,23,68,.06)}
.profil-tab.active{color:#fff;border-bottom-color:var(--neon-red);background:linear-gradient(180deg,transparent,rgba(255,23,68,.14));text-shadow:0 0 8px rgba(255,23,68,.6)}
.karta-info{padding:20px 22px}
.karta-info h3{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.05em;letter-spacing:2px;text-transform:uppercase;color:#fff;margin:0 0 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.karta-info h3 .tag{font-family:'JetBrains Mono',monospace;font-size:.62em;font-weight:400;color:var(--neon-red);letter-spacing:2px;padding:2px 6px;border:1px solid var(--border-soft)}
.stat-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.stat-box{background:rgba(0,0,0,.38);border:1px solid var(--border-soft);padding:12px 14px 12px 16px;border-radius:2px;position:relative}
.stat-box::before{content:'';position:absolute;left:0;top:12%;width:2px;height:76%;background:var(--neon-red);box-shadow:0 0 6px var(--neon-red)}
.stat-box span{display:block;font-family:'JetBrains Mono',monospace;font-size:.66em;letter-spacing:2px;text-transform:uppercase;color:var(--txt-mute);margin-bottom:5px}
.stat-box b{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.3em;color:#fff;letter-spacing:.5px}
.stat-box b.hot{color:var(--neon-red-hot);text-shadow:0 0 8px rgba(255,23,68,.4)}
.stat-box b.ember{color:var(--neon-ember)}
.stat-box .sep{display:inline;color:var(--txt-mute);margin:0 6px}
.syn-linia{font-size:1.08em;line-height:1.7;color:var(--txt-dim)}
.syn-linia b{color:#fff;font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:1px}
.syn-linia .ranga{color:var(--neon-red-hot);text-transform:uppercase;letter-spacing:2px}
.pusto{color:var(--txt-mute);font-style:italic}
.opis-fabularny{border-left:2px solid var(--neon-ember);padding:4px 0 4px 18px;color:var(--txt-main);font-family:'Cormorant Garamond',serif;font-size:1.18em;line-height:1.65;text-wrap:pretty}
.profil-alert{padding:10px 14px;border-radius:2px;font-family:'JetBrains Mono',monospace;font-size:.82em;letter-spacing:1px}
.profil-alert.ok{color:var(--neon-green);border:1px solid rgba(90,255,154,.4);background:rgba(90,255,154,.06)}
.profil-alert.blad{color:var(--neon-red-hot);border:1px solid var(--border-mid);background:rgba(255,23,68,.07)}
.historia-shell{background:rgba(0,0,0,.3);border:1px solid var(--border-soft);border-radius:2px;padding:20px}
.historia-meta{font-family:'JetBrains Mono',monospace;color:var(--txt-mute);font-size:.72em;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:16px;padding-bottom:10px;border-bottom:1px dashed rgba(255,23,68,.15);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.historia-content{color:var(--txt-main);line-height:1.65;overflow-wrap:anywhere}
.historia-content img{max-width:100%;height:auto;border-radius:2px;border:1px solid var(--border-soft);box-shadow:0 15px 40px rgba(0,0,0,.55)}
.historia-content figure{margin:18px auto;text-align:center}
.historia-content figcaption{color:var(--txt-dim);font-size:.9em;margin-top:7px;font-style:italic}
.historia-empty{border:1px dashed var(--border-soft);padding:32px;text-align:center;color:var(--txt-dim);border-radius:2px;background:rgba(0,0,0,.25);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.1em}
.edytor-wrap{display:grid;gap:14px}
.edytor-toolbar{position:sticky;top:0;z-index:3;background:rgba(8,5,10,.96);border:1px solid var(--border-mid);border-radius:2px;padding:8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.edytor-toolbar button,.edytor-toolbar select,.edytor-toolbar input[type=text],.edytor-toolbar input[type=color]{background:rgba(0,0,0,.55);border:1px solid var(--border-soft);color:var(--txt-main);border-radius:1px;padding:7px 10px;font-family:'Rajdhani',sans-serif}
.edytor-toolbar button{cursor:pointer;text-transform:uppercase;font-family:'Oswald',sans-serif;letter-spacing:1px}
.edytor-toolbar button:hover{border-color:var(--neon-red);color:#fff;background:rgba(255,23,68,.12)}
.edytor-toolbar label{color:var(--txt-dim);font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1px;text-transform:uppercase;display:inline-flex;align-items:center;gap:6px}
.story-editor{min-height:520px;background:rgba(0,0,0,.45);border:1px solid var(--border-soft);border-radius:2px;padding:24px;color:var(--txt-main);line-height:1.65;outline:none;overflow:auto}
.story-editor:focus{border-color:var(--border-hot);box-shadow:0 0 22px rgba(255,23,68,.12)}
.story-editor img{max-width:100%;height:auto}
.story-editor figure{text-align:center;margin:18px auto}
.story-editor figcaption{color:var(--txt-dim);font-style:italic}
.edytor-opcje{display:flex;gap:14px;align-items:center;flex-wrap:wrap;color:var(--txt-dim)}
.edytor-opcje .hint{color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.75em}
.edytor-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.btn-save-story,.btn-cancel-story{display:inline-block;font-family:'Oswald',sans-serif;font-weight:500;text-transform:uppercase;letter-spacing:2px;font-size:.88em;padding:10px 18px;border-radius:1px;text-decoration:none;cursor:pointer;transition:.25s}
.btn-save-story{background:rgba(255,23,68,.1);border:1px solid var(--border-mid);color:#fff}
.btn-save-story:hover{background:var(--neon-red);box-shadow:0 0 18px rgba(255,23,68,.7)}
.btn-cancel-story{background:transparent;border:1px solid var(--border-soft);color:var(--txt-dim)}
.btn-cancel-story:hover{color:#fff;border-color:var(--neon-red)}
.private-pill{display:inline-block;color:var(--neon-ember);border:1px solid rgba(255,122,61,.4);background:rgba(255,122,61,.07);padding:2px 8px;border-radius:1px}
@media(max-width:900px){.profil-kontener{grid-template-columns:1fr}.profil-lewy{position:static}.stat-grid{grid-template-columns:1fr}}
</style>

<div class="profil-kontener">
    <div class="profil-lewy">
        <div class="profil-avatar"<?php if ($avatar): ?> style="background-image:url('<?php echo $avatar; ?>')"<?php endif; ?>></div>
        <?php $odznaki_html = generuj_odznaki($profil); if ($odznaki_html): ?>
            <div class="odznaki-profil"><?php echo $odznaki_html; ?></div>
        <?php endif; ?>

        <h1 class="profil-nick<?php echo $klasa_nicku; ?>"><?php echo abyss_e($profil['login']); ?></h1>
        <div class="profil-status"><?php echo $status_online; ?></div>

        <?php if ($malzenstwo_profilu):
            $dni_razem = floor((time() - strtotime($malzenstwo_profilu['data_slubu'])) / 86400);
            if ((int)$malzenstwo_profilu['m1_id'] === $cel_id) {
                $partner_id_mal = (int)$malzenstwo_profilu['m2_id'];
                $partner_login_mal = $malzenstwo_profilu['m2_login'];
                $partner_avatar_mal = $malzenstwo_profilu['m2_avatar'];
            } else {
                $partner_id_mal = (int)$malzenstwo_profilu['m1_id'];
                $partner_login_mal = $malzenstwo_profilu['m1_login'];
                $partner_avatar_mal = $malzenstwo_profilu['m1_avatar'];
            }
            $av_par = !empty($partner_avatar_mal) ? abyss_e($partner_avatar_mal) : '';
        ?>
        <div class="malz-baner">
            <div class="malz-info">
                <div class="malz-label">// W związku z</div>
                <a href="game.php?page=profil&id=<?php echo $partner_id_mal; ?>" class="malz-nick">
                    <div class="malz-mini-av"<?php if ($av_par): ?> style="background-image:url('<?php echo $av_par; ?>')"<?php endif; ?>></div>
                    <?php echo abyss_e($partner_login_mal); ?>
                </a>
            </div>
            <div class="malz-dni"><b><?php echo $dni_razem; ?></b><br><?php echo $dni_razem==1?'dzień':'dni'; ?> razem</div>
        </div>
        <?php endif; ?>

        <div class="profil-akcje">
        <?php if ($cel_id !== $id_gracza): ?>
            <a href="game.php?page=poczta&zakladka=napisz&do=<?php echo urlencode($profil['login']); ?>" class="btn-akcja-profil">✉ Wyślij wiadomość</a>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?php echo abyss_e($csrf); ?>">
                <button type="submit" name="akcja_roza" class="btn-akcja-profil ember">🌹 Wyślij różę · 50 $</button>
                <button type="submit" name="akcja_zaczep" class="btn-akcja-profil ghost">Zaczep obywatela</button>
            </form>
            <?php if ((int)$profil['tryb_pacyfisty'] === 1 || (int)($gracz['tryb_pacyfisty'] ?? 0) === 1): ?>
                <button class="btn-akcja-profil btn-pacyfista" disabled>🛡 Nietykalność · pacyfista</button>
            <?php else: ?>
                <a href="game.php?page=walka_pvp&cel=<?php echo $cel_id; ?>" class="btn-akcja-profil btn-atak" onclick="return confirm('Czy na pewno chcesz zaatakować tego gracza? Koszt: 3 EN.');">◤ Zaatakuj · 3 EN ◥</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="game.php?page=profil&id=<?php echo $id_gracza; ?>&tab=historia&edit=1" class="btn-akcja-profil">✦ Edytuj historię</a>
            <a href="game.php?page=ustawienia" class="btn-akcja-profil ghost">Zmień portret</a>
        <?php endif; ?>
        </div>
    </div>

    <div class="profil-prawy">
        <?php if (isset($_GET['saved'])): ?><div class="profil-alert ok">Historia profilu została zapisana.</div><?php endif; ?>

        <div class="profil-tabs">
            <?php
            $tabs = ['kartoteka'=>'Kartoteka','historia'=>'Historia','dziennik'=>'Dziennik','relacje'=>'Relacje','sesje'=>'Sesje','plotki'=>'Plotki'];
            foreach ($tabs as $key=>$label):
            ?>
                <a class="profil-tab <?php echo $tab===$key?'active':''; ?>" href="game.php?page=profil&id=<?php echo $cel_id; ?>&tab=<?php echo $key; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'kartoteka'): ?>
            <div class="karta-info">
                <h3>Kartoteka obywatela <span class="tag">ID <?php echo (int)$profil['id']; ?></span></h3>
                <div class="stat-grid">
                    <div class="stat-box"><span>Poziom zagrożenia</span><b class="hot">LVL <?php echo (int)$profil['poziom']; ?></b></div>
                    <div class="stat-box"><span>Klasa postaci</span><b><?php echo abyss_e($profil['klasa']); ?></b></div>
                    <div class="stat-box"><span>Profesja (RP)</span><b class="ember"><?php echo !empty($profil['profesja_fabularna']) ? abyss_e($profil['profesja_fabularna']) : "Brak"; ?></b></div>
                    <div class="stat-box"><span>Siła bojowa</span><b>ATK <?php echo (int)$profil['bonus_atak']; ?></b><span class="sep">/</span><b>OBR <?php echo (int)$profil['bonus_obrona']; ?></b></div>
                </div>
            </div>

            <div class="karta-info">
                <h3>Przynależność <span class="tag">SYNDYKAT</span></h3>
                <?php if (!empty($profil['nazwa_syndykatu'])): ?>
                    <div class="syn-linia">
                        Rodzina: <b>[<?php echo abyss_e($profil['tag_syndykatu']); ?>] <?php echo abyss_e($profil['nazwa_syndykatu']); ?></b><br>
                        Ranga: <b class="ranga"><?php echo abyss_e($profil['syndykat_rola']); ?></b>
                    </div>
                <?php else: ?>
                    <div class="pusto">Wolny strzelec. Nie jest powiązany z żadną grupą przestępczą.</div>
                <?php endif; ?>
            </div>

            <div class="karta-info">
                <h3>Notatki ulicy <span class="tag">OPIS FABULARNY</span></h3>
                <div class="opis-fabularny"><?php echo $opis; ?></div>
            </div>
        <?php elseif ($tab === 'historia'): ?>
            <div class="karta-info">
                <h3>Historia postaci <span class="tag">ARCHIWUM</span></h3>

                <?php if (!$historia['exists']): ?>
                    <div class="profil-alert blad">Brakuje tabeli <b>profile_historie</b>. Uruchom SQL z paczki, żeby aktywować tę zakładkę.</div>
                <?php elseif ($edit_historia): ?>
                    <form method="POST" id="story-form" class="edytor-wrap">
                        <input type="hidden" name="csrf" value="<?php echo abyss_e($csrf); ?>">
                        <input type="hidden" name="zapisz_historie_profilu" value="1">
                        <input type="hidden" name="historia_html" id="historia_html">

                        <div class="edytor-toolbar">
                            <button type="button" data-cmd="bold"><b>B</b></button>
                            <button type="button" data-cmd="italic"><i>I</i></button>
                            <button type="button" data-cmd="underline"><u>U</u></button>
                            <button type="button" data-cmd="strikeThrough"><s>S</s></button>
                            <button type="button" data-cmd="formatBlock" data-val="H2">H2</button>
                            <button type="button" data-cmd="formatBlock" data-val="BLOCKQUOTE">Cytat</button>
                            <button type="button" data-cmd="insertUnorderedList">Lista</button>
                            <button type="button" data-cmd="insertHorizontalRule">Linia</button>

                            <label>Kolor <input type="color" id="story-color" value="#e8e1e8"></label>
                            <label>Tło <input type="color" id="story-bg" value="#111111"></label>

                            <select id="story-font">
                                <option value="Rajdhani">Rajdhani</option>
                                <option value="Oswald">Oswald</option>
                                <option value="Cormorant Garamond">Cormorant Garamond</option>
                                <option value="Fraunces">Fraunces</option>
                                <option value="JetBrains Mono">JetBrains Mono</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Arial">Arial</option>
                                <option value="Verdana">Verdana</option>
                            </select>
                            <input type="text" id="story-font-custom" placeholder="inna czcionka, np. Cinzel" style="width:170px;">
                            <select id="story-size">
                                <option value="2">mały</option><option value="3" selected>normalny</option><option value="4">większy</option><option value="5">duży</option><option value="6">bardzo duży</option>
                            </select>
                            <button type="button" id="insert-image-btn">Wstaw obrazek</button>
                            <input type="file" id="story-image-input" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none;">
                            <button type="button" id="clear-format-btn">Wyczyść format</button>
                        </div>

                        <div id="story-editor" class="story-editor" contenteditable="true"><?php echo $historia['historia_html'] ?? ''; ?></div>

                        <div class="edytor-opcje">
                            <label><input type="radio" name="widocznosc" value="publiczna" <?php echo ($historia['widocznosc'] ?? 'publiczna') !== 'prywatna' ? 'checked' : ''; ?>> Publiczna</label>
                            <label><input type="radio" name="widocznosc" value="prywatna" <?php echo ($historia['widocznosc'] ?? '') === 'prywatna' ? 'checked' : ''; ?>> Prywatna</label>
                            <span class="hint">Obrazki wstawiają się w miejscu kursora.</span>
                        </div>

                        <div class="edytor-actions">
                            <a class="btn-cancel-story" href="game.php?page=profil&id=<?php echo $id_gracza; ?>&tab=historia">Anuluj</a>
                            <button type="submit" class="btn-save-story">Zapisz historię</button>
                        </div>
                    </form>

                    <script>
                    (function(){
                        const editor = document.getElementById('story-editor');
                        const form = document.getElementById('story-form');
                        const hidden = document.getElementById('historia_html');
                        let savedRange = null;

                        function focusEditor(){ editor.focus(); }
                        function saveSelection(){
                            const sel = window.getSelection();
                            if (sel.rangeCount > 0 && editor.contains(sel.anchorNode)) savedRange = sel.getRangeAt(0);
                        }
                        function restoreSelection(){
                            if (!savedRange) return;
                            const sel = window.getSelection();
                            sel.removeAllRanges();
                            sel.addRange(savedRange);
                        }
                        editor.addEventListener('keyup', saveSelection);
                        editor.addEventListener('mouseup', saveSelection);
                        editor.addEventListener('input', saveSelection);

                        document.querySelectorAll('.edytor-toolbar [data-cmd]').forEach(btn => {
                            btn.addEventListener('click', () => {
                                focusEditor(); restoreSelection();
                                document.execCommand(btn.dataset.cmd, false, btn.dataset.val || null);
                                saveSelection();
                            });
                        });
                        document.getElementById('story-color').addEventListener('input', e => { focusEditor(); restoreSelection(); document.execCommand('foreColor', false, e.target.value); saveSelection(); });
                        document.getElementById('story-bg').addEventListener('input', e => { focusEditor(); restoreSelection(); document.execCommand('backColor', false, e.target.value); saveSelection(); });
                        document.getElementById('story-font').addEventListener('change', e => { focusEditor(); restoreSelection(); document.execCommand('fontName', false, e.target.value); saveSelection(); });
                        document.getElementById('story-font-custom').addEventListener('change', e => { const v=e.target.value.trim(); if(v){ focusEditor(); restoreSelection(); document.execCommand('fontName', false, v); saveSelection(); }});
                        document.getElementById('story-size').addEventListener('change', e => { focusEditor(); restoreSelection(); document.execCommand('fontSize', false, e.target.value); saveSelection(); });
                        document.getElementById('clear-format-btn').addEventListener('click', () => { focusEditor(); restoreSelection(); document.execCommand('removeFormat', false, null); saveSelection(); });

                        const imgInput = document.getElementById('story-image-input');
                        document.getElementById('insert-image-btn').addEventListener('click', () => { saveSelection(); imgInput.click(); });
                        imgInput.addEventListener('change', async () => {
                            if (!imgInput.files || !imgInput.files[0]) return;
                            const fd = new FormData();
                            fd.append('obrazek', imgInput.files[0]);
                            try {
                                const res = await fetch('api/profil_upload_image.php', { method:'POST', body:fd, credentials:'same-origin' });
                                const data = await res.json();
                                if (!data.ok) { alert(data.msg || 'Nie udało się wgrać obrazka.'); return; }
                                focusEditor(); restoreSelection();
                                document.execCommand('insertHTML', false, data.html);
                                saveSelection();
                            } catch(e) { alert('Brak połączenia z serwerem uploadu.'); }
                            imgInput.value = '';
                        });

                        form.addEventListener('submit', () => { hidden.value = editor.innerHTML; });
                    })();
                    </script>
                <?php elseif (!$story_public): ?>
                    <div class="historia-empty">Ta historia jest prywatna. Właściciel postaci nie udostępnił jej innym graczom.</div>
                <?php else: ?>
                    <div class="historia-shell">
                        <div class="historia-meta">
                            <span>// PUBLIC STORY ARCHIVE</span>
                            <span><?php echo !empty($historia['updated_at']) ? 'OSTATNIA EDYCJA: ' . abyss_e($historia['updated_at']) : 'BRAK OSTATNIEJ EDYCJI'; ?></span>
                            <?php if (($historia['widocznosc'] ?? '') === 'prywatna'): ?><span class="private-pill">widoczne tylko dla Ciebie</span><?php endif; ?>
                        </div>
                        <?php if (trim($story_html) !== ''): ?>
                            <div class="historia-content"><?php echo $story_html; ?></div>
                        <?php else: ?>
                            <div class="historia-empty">
                                Ta postać nie ma jeszcze publicznej historii.
                                <?php if ($cel_id === $id_gracza): ?><br><br><a class="btn-save-story" href="game.php?page=profil&id=<?php echo $id_gracza; ?>&tab=historia&edit=1">Napisz historię</a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($cel_id === $id_gracza): ?>
                        <div style="margin-top:14px;display:flex;justify-content:flex-end"><a class="btn-save-story" href="game.php?page=profil&id=<?php echo $id_gracza; ?>&tab=historia&edit=1">Edytuj historię</a></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="karta-info">
                <h3>Sekcja w przygotowaniu</h3>
                <div class="historia-empty">
                    Ta zakładka jest gotowa jako miejsce w profilu, ale wymaga następnego modułu.
                    <br><br>
                    <b><?php echo ucfirst($tab); ?></b> możesz później podpiąć pod dziennik, relacje, sesje albo plotki postaci.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
