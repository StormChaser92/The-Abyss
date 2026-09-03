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

$avatar = !empty($profil['avatar']) ? abyss_e($profil['avatar']) : "https://via.placeholder.com/500x625/111/333?text=Brak+Zdjecia";
$opis = !empty($profil['opis_profilu']) ? nl2br(abyss_e($profil['opis_profilu'])) : "<span style='color:#666; font-style:italic;'>Ten gracz woli pozostać w cieniu. Brak wpisu w kartotece.</span>";
$kolor_nicku = (!empty($profil['is_premium'])) ? "#ffd700" : "#00ff00";

$ostatnio = strtotime($profil['ostatnia_aktywnosc'] ?? 'now');
$roznica_minut = round((time() - $ostatnio) / 60);
if ($roznica_minut < 15) {
    $status_online = "<span style='color: #00ff00; font-weight: bold; text-shadow: 0 0 5px rgba(0,255,0,0.5);'>● Dostępny/a na ulicach</span>";
} else {
    $status_online = "<span style='color: #888;'>○ Ostatnio widziany/a: " . abyss_e($profil['ostatnia_aktywnosc']) . "</span>";
}

$story_public = $historia['widocznosc'] !== 'prywatna' || $cel_id === $id_gracza;
$story_html = $story_public ? ($historia['historia_html'] ?? '') : '';
?>

<style>
.odznaka{display:inline-flex;align-items:center;gap:4px;border-radius:12px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-weight:700;border:1px solid;margin:0 2px;white-space:nowrap}
.o-vip{color:#ffd700;border-color:rgba(255,215,0,.5);background:rgba(255,215,0,.1);text-shadow:0 0 8px rgba(255,215,0,.5)}
.o-mg{color:#dd88ff;border-color:rgba(221,136,255,.5);background:rgba(221,136,255,.1);text-shadow:0 0 8px rgba(221,136,255,.5)}
.o-proboszcz{color:#ffd700;border-color:rgba(255,215,0,.4);background:rgba(255,215,0,.08);text-shadow:0 0 6px rgba(255,215,0,.4)}
.o-barman{color:#ffaa00;border-color:rgba(255,170,0,.5);background:rgba(255,170,0,.1);text-shadow:0 0 8px rgba(255,170,0,.5)}
.odznaki-profil{display:flex;flex-wrap:wrap;justify-content:center;gap:4px;margin-bottom:14px}
.profil-kontener{display:flex;gap:30px;margin-top:10px;align-items:flex-start}.profil-lewy{width:320px;min-width:320px;background:rgba(10,10,10,.6);border:1px solid rgba(255,255,255,.08);padding:25px;border-radius:8px;text-align:center;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);box-shadow:0 10px 40px rgba(0,0,0,.6)}
.profil-avatar{width:100%;height:auto;aspect-ratio:500/625;background-position:top center!important;background-size:cover!important;border:1px solid rgba(255,255,255,.15);border-radius:6px;margin-bottom:20px;box-shadow:0 5px 20px rgba(0,0,0,.8);background-color:#050505}.profil-nick{font-family:'Oswald',sans-serif;font-size:2.2em;margin:0 0 5px 0;text-transform:uppercase;word-wrap:break-word;text-shadow:0 0 10px rgba(0,255,0,.3);letter-spacing:1px}
.malz-baner{background:linear-gradient(135deg,rgba(255,51,102,.08),rgba(221,136,255,.08));border:1px solid rgba(255,51,102,.3);border-radius:10px;padding:12px 16px;margin:14px 0;display:flex;align-items:center;gap:12px}.malz-info{flex:1;text-align:left}.malz-label{color:#ff3366;font-family:'Oswald',sans-serif;font-size:.72em;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:2px}.malz-nick{color:#fff;font-family:'Oswald',sans-serif;font-size:1em;letter-spacing:.5px;text-decoration:none;display:flex;align-items:center;gap:6px}.malz-nick:hover{text-decoration:underline}.malz-mini-av{width:28px;height:28px;border-radius:50%;background-size:cover;background-position:top center;border:1px solid rgba(255,51,102,.4)}.malz-dni{color:#dd88ff;font-family:'Oswald',sans-serif;font-size:.8em;letter-spacing:1px;text-align:right}.malz-dni b{font-size:1.3em;color:#dd88ff}
.btn-akcja-profil{display:block;width:100%;background:rgba(0,0,0,.5);color:#fff;font-family:'Oswald',sans-serif;font-size:1.05em;text-transform:uppercase;letter-spacing:1.5px;padding:12px 15px;margin-bottom:10px;border-radius:3px;cursor:pointer;transition:all .3s ease;text-decoration:none;box-sizing:border-box;font-weight:600;position:relative;overflow:hidden}.btn-akcja-profil::before{content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(to right,transparent,rgba(255,255,255,.1),transparent);transform:skewX(-20deg);transition:.5s}.btn-akcja-profil:hover::before{left:150%}.btn-wiadomosc{border:1px solid rgba(0,204,255,.3);color:#00ccff;text-shadow:0 0 5px rgba(0,204,255,.5)}.btn-wiadomosc:hover{background:rgba(0,204,255,.15);border-color:#00ccff;box-shadow:0 0 20px rgba(0,204,255,.6),inset 0 0 10px rgba(0,204,255,.3);color:#fff;text-shadow:0 0 10px #00ccff}.btn-roza{border:1px solid rgba(255,0,255,.3);color:#ff00ff;text-shadow:0 0 5px rgba(255,0,255,.5)}.btn-roza:hover{background:rgba(255,0,255,.15);border-color:#ff00ff;box-shadow:0 0 20px rgba(255,0,255,.6),inset 0 0 10px rgba(255,0,255,.3);color:#fff;text-shadow:0 0 10px #ff00ff}.btn-zaczep{border:1px solid rgba(255,204,0,.3);color:#ffcc00;text-shadow:0 0 5px rgba(255,204,0,.5)}.btn-zaczep:hover{background:rgba(255,204,0,.15);border-color:#ffcc00;box-shadow:0 0 20px rgba(255,204,0,.6),inset 0 0 10px rgba(255,204,0,.3);color:#fff;text-shadow:0 0 10px #ffcc00}.btn-atak{background:rgba(50,0,0,.6);border:2px solid #ff3333;color:#ff3333;font-size:1.2em;font-weight:700;text-shadow:0 0 10px #ff3333;box-shadow:inset 0 0 15px rgba(255,51,51,.2),0 0 15px rgba(255,51,51,.1);margin-top:25px}.btn-atak:hover{background:rgba(255,0,0,.2);color:#fff;border-color:#ff0000;box-shadow:0 0 30px rgba(255,0,0,.8),inset 0 0 20px rgba(255,0,0,.5);text-shadow:0 0 15px #ff0000;transform:scale(1.02)}.btn-pacyfista{background:rgba(20,20,20,.8);border:1px solid rgba(100,100,100,.5);color:#666;cursor:not-allowed;margin-top:25px}
.profil-prawy{flex-grow:1;display:flex;flex-direction:column;gap:18px;min-width:0}.karta-info{background:rgba(10,10,10,.6);border:1px solid rgba(255,255,255,.08);padding:28px;border-radius:8px;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);box-shadow:0 5px 20px rgba(0,0,0,.5)}.karta-info h3{font-family:'Oswald',sans-serif;color:#fff;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:10px;margin-top:0;margin-bottom:22px;font-size:1.35em;letter-spacing:1px}.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.stat-box{background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.05);padding:15px 20px;border-radius:6px}.stat-box span{color:#888;font-size:.85em;text-transform:uppercase;display:block;margin-bottom:5px;font-family:'Oswald',sans-serif;letter-spacing:.5px}.stat-box b{color:#fff;font-size:1.2em;font-family:'Open Sans',sans-serif}.opis-fabularny{background:rgba(0,0,0,.4);border-left:4px solid #00ccff;padding:20px 25px;color:#ddd;line-height:1.7;font-family:'Open Sans',sans-serif;font-size:1.05em;border-radius:0 6px 6px 0}
.profil-tabs{display:flex;gap:8px;flex-wrap:wrap;background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.08);padding:10px;border-radius:8px}.profil-tab{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;text-decoration:none;color:#8a818e;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.35);padding:10px 13px;border-radius:4px;transition:.2s}.profil-tab:hover{color:#fff;border-color:rgba(74,214,255,.4)}.profil-tab.active{color:#4ad6ff;border-color:rgba(74,214,255,.6);background:rgba(74,214,255,.08);box-shadow:0 0 16px rgba(74,214,255,.16)}
.profil-alert{padding:12px 16px;border-radius:4px;margin-bottom:14px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px}.profil-alert.ok{color:#5aff9a;border:1px solid rgba(90,255,154,.45);background:rgba(90,255,154,.08)}.profil-alert.blad{color:#ff6678;border:1px solid rgba(255,102,120,.45);background:rgba(255,102,120,.08)}
.historia-shell{background:radial-gradient(ellipse at top,rgba(74,214,255,.06),transparent 45%),rgba(0,0,0,.34);border:1px solid rgba(74,214,255,.18);border-radius:8px;padding:22px;overflow:hidden}.historia-meta{font-family:'JetBrains Mono',monospace;color:#666;font-size:.82em;text-transform:uppercase;margin-bottom:16px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.historia-content{color:#e8e1e8;line-height:1.65;overflow-wrap:anywhere}.historia-content img{max-width:100%;height:auto;border-radius:6px;border:1px solid rgba(255,255,255,.12);box-shadow:0 15px 40px rgba(0,0,0,.55)}.historia-content figure{margin:18px auto;text-align:center}.historia-content figcaption{color:#8a818e;font-size:.9em;margin-top:7px;font-style:italic}.historia-empty{border:1px dashed rgba(255,255,255,.14);padding:35px;text-align:center;color:#777;border-radius:8px;background:rgba(0,0,0,.25)}
.edytor-wrap{display:grid;grid-template-columns:1fr;gap:14px}.edytor-toolbar{position:sticky;top:0;z-index:3;background:rgba(8,5,10,.96);border:1px solid rgba(255,23,68,.25);border-radius:8px;padding:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}.edytor-toolbar button,.edytor-toolbar select,.edytor-toolbar input[type=text],.edytor-toolbar input[type=color]{background:rgba(0,0,0,.55);border:1px solid rgba(255,255,255,.14);color:#eee;border-radius:4px;padding:8px 10px;font-family:'Rajdhani',sans-serif}.edytor-toolbar button{cursor:pointer;text-transform:uppercase;font-family:'Oswald',sans-serif;letter-spacing:.8px}.edytor-toolbar button:hover{border-color:#4ad6ff;color:#4ad6ff}.story-editor{min-height:520px;background:rgba(0,0,0,.45);border:1px solid rgba(74,214,255,.25);border-radius:8px;padding:24px;color:#eee;line-height:1.65;outline:none;overflow:auto}.story-editor:focus{box-shadow:0 0 0 1px rgba(74,214,255,.45),0 0 25px rgba(74,214,255,.12)}.story-editor img{max-width:100%;height:auto;border-radius:6px}.story-editor figure{text-align:center;margin:18px auto}.story-editor figcaption{color:#888;font-style:italic}.edytor-actions{display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap}.btn-save-story,.btn-cancel-story{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;padding:12px 18px;border-radius:4px;text-decoration:none;cursor:pointer}.btn-save-story{background:rgba(90,255,154,.12);border:1px solid rgba(90,255,154,.6);color:#5aff9a}.btn-save-story:hover{background:#5aff9a;color:#05060c}.btn-cancel-story{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.18);color:#aaa}.btn-cancel-story:hover{color:#fff;border-color:#fff}.private-pill{display:inline-block;color:#ffaa00;border:1px solid rgba(255,170,0,.4);background:rgba(255,170,0,.08);padding:4px 9px;border-radius:20px;font-size:.8em;text-transform:uppercase;font-family:'Oswald',sans-serif;letter-spacing:.8px}
@media(max-width:900px){.profil-kontener{flex-direction:column}.profil-lewy{width:100%;min-width:0}.stat-grid{grid-template-columns:1fr}}
</style>

<div class="profil-kontener">
    <div class="profil-lewy">
        <div class="profil-avatar" style="background:url('<?php echo $avatar; ?>');"></div>
        <?php $odznaki_html = generuj_odznaki($profil); if ($odznaki_html): ?>
            <div class="odznaki-profil"><?php echo $odznaki_html; ?></div>
        <?php endif; ?>

        <h1 class="profil-nick" style="color: <?php echo $kolor_nicku; ?>;"><?php echo abyss_e($profil['login']); ?></h1>
        <div style="margin-bottom: 15px; font-size: 0.95em; font-family: 'Open Sans', sans-serif;"><?php echo $status_online; ?></div>

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
            $av_par = !empty($partner_avatar_mal) ? abyss_e($partner_avatar_mal) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
        ?>
        <div class="malz-baner">
            <div class="malz-info">
                <div class="malz-label">💍 Żonaty/Zamężna z</div>
                <a href="game.php?page=profil&id=<?php echo $partner_id_mal; ?>" class="malz-nick">
                    <div class="malz-mini-av" style="background-image:url('<?php echo $av_par; ?>')"></div>
                    <?php echo abyss_e($partner_login_mal); ?>
                </a>
            </div>
            <div class="malz-dni"><b><?php echo $dni_razem; ?></b><br><?php echo $dni_razem==1?'dzień':'dni'; ?> razem</div>
        </div>
        <?php endif; ?>

        <?php if ($cel_id !== $id_gracza): ?>
            <a href="game.php?page=poczta&zakladka=napisz&do=<?php echo abyss_e($profil['login']); ?>" class="btn-akcja-profil btn-wiadomosc">✉️ Wyślij Wiadomość</a>
            <form method="POST" style="margin: 0; padding: 0;">
                <input type="hidden" name="csrf" value="<?php echo abyss_e($csrf); ?>">
                <button type="submit" name="akcja_roza" class="btn-akcja-profil btn-roza">🌹 Wyślij Różę (50 $)</button>
                <button type="submit" name="akcja_zaczep" class="btn-akcja-profil btn-zaczep">👋 Zaczep Obywatela</button>
            </form>
            <?php if ((int)$profil['tryb_pacyfisty'] === 1 || (int)($gracz['tryb_pacyfisty'] ?? 0) === 1): ?>
                <button class="btn-akcja-profil btn-pacyfista" disabled>🛡️ Nietykalność (Pacyfista)</button>
            <?php else: ?>
                <a href="game.php?page=walka_pvp&cel=<?php echo $cel_id; ?>" class="btn-akcja-profil btn-atak" onclick="return confirm('Czy na pewno chcesz zaatakować tego gracza? Koszt: 3 EN.');">⚔️ ZAATAKUJ (3 EN)</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="game.php?page=ustawienia" class="btn-akcja-profil btn-wiadomosc" style="border-color:#888;color:#aaa;">⚙️ Edytuj portret</a>
            <a href="game.php?page=profil&id=<?php echo $id_gracza; ?>&tab=historia&edit=1" class="btn-akcja-profil btn-roza">✦ Edytuj historię</a>
        <?php endif; ?>
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
                <h3 style="color:#00ccff;text-shadow:0 0 10px rgba(0,204,255,0.4);border-color:rgba(0,204,255,0.2);">🗂️ Kartoteka Obywatela <span style="color:#666;font-size:.7em;">(ID: <?php echo (int)$profil['id']; ?>)</span></h3>
                <div class="stat-grid">
                    <div class="stat-box"><span>Poziom Zagrożenia</span><b style="color:#00ccff;font-family:'Oswald',sans-serif;font-size:1.4em;">Lvl <?php echo (int)$profil['poziom']; ?></b></div>
                    <div class="stat-box"><span>Klasa Postaci</span><b><?php echo abyss_e($profil['klasa']); ?></b></div>
                    <div class="stat-box"><span>Profesja (RP)</span><b style="color:#ffaa00;"><?php echo !empty($profil['profesja_fabularna']) ? abyss_e($profil['profesja_fabularna']) : "Brak"; ?></b></div>
                    <div class="stat-box"><span>Siła Bojowa</span><b style="color:#ff3333;">Atk: <?php echo (int)$profil['bonus_atak']; ?></b> <span style="display:inline;color:#555;">|</span> <b style="color:#00aaff;">Obr: <?php echo (int)$profil['bonus_obrona']; ?></b></div>
                </div>
            </div>

            <div class="karta-info">
                <h3 style="color:#dd88ff;text-shadow:0 0 10px rgba(221,136,255,0.4);border-color:rgba(221,136,255,0.2);">🏴 Przynależność do Syndykatu</h3>
                <?php if (!empty($profil['nazwa_syndykatu'])): ?>
                    <div style="font-size:1.15em;font-family:'Open Sans',sans-serif;line-height:1.6;">
                        <span style="color:#aaa;">Gracz należy do:</span> <b style="color:#fff;">[<?php echo abyss_e($profil['tag_syndykatu']); ?>] <?php echo abyss_e($profil['nazwa_syndykatu']); ?></b><br>
                        <span style="color:#aaa;">Ranga w strukturze:</span> <b style="color:#dd88ff;font-family:'Oswald',sans-serif;font-size:1.1em;letter-spacing:1px;text-transform:uppercase;"><?php echo abyss_e($profil['syndykat_rola']); ?></b>
                    </div>
                <?php else: ?>
                    <div style="color:#888;font-style:italic;font-family:'Open Sans',sans-serif;">Ten obywatel to wolny strzelec. Nie jest powiązany z żadną grupą przestępczą.</div>
                <?php endif; ?>
            </div>

            <div class="karta-info">
                <h3 style="color:#fff;border-color:rgba(255,255,255,0.2);">📖 Notatki Ulicy (Opis Fabularny)</h3>
                <div class="opis-fabularny"><?php echo $opis; ?></div>
            </div>
        <?php elseif ($tab === 'historia'): ?>
            <div class="karta-info">
                <h3 style="color:#4ad6ff;text-shadow:0 0 10px rgba(74,214,255,.3);border-color:rgba(74,214,255,.2);">✦ Historia Postaci</h3>

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

                            <label style="color:#888;font-family:'Oswald';text-transform:uppercase;">Kolor <input type="color" id="story-color" value="#e8e1e8"></label>
                            <label style="color:#888;font-family:'Oswald';text-transform:uppercase;">Tło <input type="color" id="story-bg" value="#111111"></label>

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

                        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;color:#aaa;">
                            <label><input type="radio" name="widocznosc" value="publiczna" <?php echo ($historia['widocznosc'] ?? 'publiczna') !== 'prywatna' ? 'checked' : ''; ?>> Publiczna</label>
                            <label><input type="radio" name="widocznosc" value="prywatna" <?php echo ($historia['widocznosc'] ?? '') === 'prywatna' ? 'checked' : ''; ?>> Prywatna</label>
                            <span style="color:#666;">Obrazki wstawiają się w miejscu kursora.</span>
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
                        <div style="margin-top:16px;text-align:right;"><a class="btn-save-story" href="game.php?page=profil&id=<?php echo $id_gracza; ?>&tab=historia&edit=1">Edytuj historię</a></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="karta-info">
                <h3 style="color:#ffaa00;border-color:rgba(255,170,0,.25);">Sekcja w przygotowaniu</h3>
                <div class="historia-empty">
                    Ta zakładka jest gotowa jako miejsce w profilu, ale wymaga następnego modułu.
                    <br><br>
                    <b><?php echo ucfirst($tab); ?></b> możesz później podpiąć pod dziennik, relacje, sesje albo plotki postaci.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
