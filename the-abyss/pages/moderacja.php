<?php
require_once "db.php";
require_once __DIR__ . '/../config/moderacja.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* MODERACJA — MG i Adminka: ostrzeżenia, zawieszenia 3/7/30 dni, wyrzucenie z Klubu, log.
   Odwołania rozpatruje Adminka. Każdy gracz widzi tu swoją historię i może się odwołać. */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$nadzor = mod_moze($polaczenie, $id_gracza);
$admin  = mod_admin($polaczenie, $id_gracza);
$tabs = ['moje' => 'Moja historia'];
if ($nadzor) $tabs = ['gracze' => 'Gracze', 'kary' => 'Aktywne kary', 'odwolania' => 'Odwołania', 'log' => 'Log'] + $tabs;
$tab = (string)($_GET['tab'] ?? '');
if (!isset($tabs[$tab])) $tab = array_key_first($tabs);
$gid_w = (int)($_GET['gid'] ?? 0);
$blad = '';

if (isset($_POST['md_akcja'])) {
    $a = (string)$_POST['md_akcja']; $cel = (int)($_POST['gid'] ?? 0); $pow = (string)($_POST['powod'] ?? '');
    switch ($a) {
        case 'ostrzez': $blad = mod_kara($polaczenie, $id_gracza, $cel, 'ostrzezenie', $pow); break;
        case 'zawies':  $blad = mod_kara($polaczenie, $id_gracza, $cel, 'zawieszenie', $pow, (int)($_POST['dni'] ?? 0)); break;
        case 'wyrzuc':  $blad = mod_kara($polaczenie, $id_gracza, $cel, 'wyrzucenie', $pow); break;
        case 'cofnij':  $blad = mod_cofnij($polaczenie, $id_gracza, (int)($_POST['kid'] ?? 0), $pow); break;
        case 'odwolaj': $blad = mod_odwolaj($polaczenie, $id_gracza, (int)($_POST['kid'] ?? 0), (string)($_POST['tresc'] ?? '')); break;
        case 'uznaj':
        case 'odrzuc':  $blad = mod_rozpatrz($polaczenie, $id_gracza, (int)($_POST['oid'] ?? 0), $a === 'uznaj', $pow); break;
        default:        $blad = 'Nieznana akcja.';
    }
    if ($blad === '') { header('Location: game.php?page=moderacja&tab=' . urlencode($tab) . ($gid_w ? "&gid=$gid_w" : '') . '&ok=1'); exit; }
}

$KARY_SQL = "SELECT k.*, m.login AS mod_login, c.login AS cof_login, o.status AS odw_status, o.odpowiedz AS odw_odp
             FROM mod_kary k JOIN gracze m ON m.id = k.moderator_id LEFT JOIN gracze c ON c.id = k.cofnal_id
             LEFT JOIN mod_odwolania o ON o.kara_id = k.id";
$status = function (array $k): array {
    if ($k['cofnieta']) return ['cofnięta', 'off'];
    if ($k['rodzaj'] === 'zawieszenie' && strtotime($k['do_kiedy']) <= time()) return ['minęło', 'off'];
    return ['aktywna', 'on'];
};
$kara = function (array $k, bool $akcje, bool $odwolanie = false) use ($h, $status, $polaczenie, $id_gracza) {
    [$st, $cl] = $status($k);
    echo "<div class='md-kara $cl'><div class='md-kara-gl'><b>" . $h(MOD_NAZWY[$k['rodzaj']]) . "</b>";
    if ($k['dni']) echo "<span class='md-tag'>{$k['dni']} dni · do " . mod_data($k['do_kiedy']) . "</span>";
    if ($k['auto']) echo "<span class='md-tag'>automatyczne</span>";
    echo "<span class='md-tag $cl'>$st</span>";
    if ($k['odw_status']) echo "<span class='md-tag'>odwołanie: " . $h($k['odw_status']) . "</span>";
    echo "<span class='md-meta'>" . mod_data($k['kiedy']) . " · " . $h($k['mod_login']) . ($k['sesja_id'] ? " · <a href='game.php?page=pokoj_sesji&id=" . (int)$k['sesja_id'] . "&zakladka=offtop'>sesja #" . (int)$k['sesja_id'] . "</a>" : '') . "</span></div>";
    echo "<div class='md-pow'>" . nl2br($h($k['powod'])) . "</div>";
    if ($k['cofnieta']) echo "<div class='md-meta'>Cofnął " . $h($k['cof_login']) . ": " . $h($k['cofniecie_powod']) . "</div>";
    if ($k['odw_odp'] && $k['odw_status'] !== 'nowe') echo "<div class='md-meta'>Decyzja Adminki: " . $h($k['odw_odp']) . "</div>";
    if ($akcje && !$k['cofnieta'] && $cl === 'on' && mod_moze_cel($polaczenie, $id_gracza, (int)$k['gracz_id']))
        echo "<details><summary>Cofnij karę</summary><form method='POST' class='md-f'><input type='hidden' name='md_akcja' value='cofnij'><input type='hidden' name='kid' value='" . (int)$k['id'] . "'><input type='text' name='powod' required maxlength='500' placeholder='Powód cofnięcia (wymagany)'><button type='submit'>Cofnij</button></form></details>";
    if ($odwolanie && !$k['cofnieta'] && !$k['odw_status'])
        echo "<details><summary>Odwołaj się do Adminki</summary><form method='POST' class='md-f col'><input type='hidden' name='md_akcja' value='odwolaj'><input type='hidden' name='kid' value='" . (int)$k['id'] . "'><textarea name='tresc' required minlength='10' maxlength='2000' placeholder='Dlaczego ta kara jest niesłuszna? Jedno odwołanie od każdej kary.'></textarea><button type='submit'>Wyślij odwołanie</button></form></details>";
    echo "</div>";
};
?>
<style>
.md{display:flex;flex-direction:column;gap:16px;color:#f1ebf2;max-width:880px}
.md h1{font-family:'Oswald',sans-serif;font-weight:500;font-size:2em;letter-spacing:4px;text-transform:uppercase;margin:0;color:#fff}
.md h2{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.1em;letter-spacing:2px;text-transform:uppercase;margin:0;color:#fff}
.md p{color:#cfc6d2;line-height:1.45;margin:0}
.md a{color:var(--neon-cyan)}
.md-tabs{display:flex;gap:6px;flex-wrap:wrap}
.md-tabs a{padding:7px 14px;border:1px solid rgba(255,23,68,.25);color:var(--txt-dim);text-decoration:none;font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase;font-size:.85em}
.md-tabs a.on{border-color:var(--neon-cyan);color:#fff;background:rgba(74,214,255,.1)}
.md-tabs a b{color:var(--neon-red-hot);margin-left:4px}
.md input,.md select,.md textarea{background:rgba(0,0,0,.55);border:1px solid rgba(255,23,68,.3);color:#fff;padding:8px 10px;font-family:inherit;font-size:1em;min-width:0}
.md textarea{min-height:70px;resize:vertical}
.md button{padding:8px 14px;border:1px solid var(--neon-red,#ff1744);background:rgba(255,23,68,.14);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer}
.md button.ok{border-color:var(--neon-green);background:rgba(90,255,154,.1)}
.md-f{display:flex;gap:8px;flex-wrap:wrap;margin:0}
.md-f input[type=text],.md-f input[type=search]{flex:1}
.md-f.col{flex-direction:column}
.md-box{display:flex;flex-direction:column;gap:10px;padding:14px 16px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.08)}
.md-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px}
.md-grid form{display:flex;flex-direction:column;gap:8px;padding:12px;background:rgba(0,0,0,.35);border:1px solid rgba(255,23,68,.18)}
.md-grid .lbl{font-family:'Oswald',sans-serif;font-size:.8em;letter-spacing:2px;text-transform:uppercase;color:var(--txt-dim)}
.md-l{display:flex;flex-direction:column;gap:8px}
.md-r{display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:8px 12px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.08);text-decoration:none;color:inherit}
.md-r b{color:#fff}
.md-kara{display:flex;flex-direction:column;gap:6px;padding:10px 14px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.08)}
.md-kara.on{border-color:rgba(255,23,68,.4)}
.md-kara.off{opacity:.65}
.md-kara-gl{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.md-kara-gl b{color:#fff;font-family:'Oswald',sans-serif;letter-spacing:1px}
.md-tag{font-family:'JetBrains Mono',monospace;font-size:.72em;padding:2px 7px;border:1px solid rgba(255,255,255,.14);color:var(--txt-dim);text-transform:uppercase;letter-spacing:1px}
.md-tag.on{color:var(--neon-red-hot);border-color:var(--neon-red)}
.md-meta{color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.78em;margin-left:auto}
.md-kara>.md-meta{margin-left:0}
.md-pow{color:var(--txt-main);line-height:1.5}
.md details summary{cursor:pointer;color:var(--neon-cyan);font-family:'Oswald',sans-serif;font-size:.8em;letter-spacing:1.5px;text-transform:uppercase}
.md details form{margin-top:8px}
.md-ok{color:var(--neon-green,#3dff9a);font-family:'JetBrains Mono',monospace}
.md-err{color:var(--neon-red-hot);font-family:'Oswald',sans-serif;letter-spacing:1px}
.md-log{display:grid;grid-template-columns:130px 110px 110px 150px minmax(0,1fr);gap:4px 12px;font-size:.88em;align-items:start}
.md-log span{overflow-wrap:anywhere}
.md-log .hd{font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase;color:var(--txt-dim);font-size:.85em}
@media(max-width:760px){.md-log{grid-template-columns:1fr 1fr}}
</style>
<div class="md">
  <div><h1>Moderacja</h1><p>Ostrzeżenia nie wygasają; co trzecie aktywne daje automatyczne zawieszenie (3, potem 7, potem 30 dni). Zawieszenie zamyka Wydarzenia w Klubie i blokuje pisanie postów. Wyrzucenie zamyka Klub do czasu cofnięcia. Od każdej kary można raz odwołać się do Adminki.</p></div>
  <?php
  $n_odw = $nadzor ? (int)(db_wiersz($polaczenie, "SELECT COUNT(*) c FROM mod_odwolania WHERE status = 'nowe'")['c'] ?? 0) : 0;
  echo "<nav class='md-tabs'>";
  foreach ($tabs as $k => $n) echo "<a href='game.php?page=moderacja&tab=$k' class='" . ($k === $tab ? 'on' : '') . "'>" . $h($n) . ($k === 'odwolania' && $n_odw ? "<b>$n_odw</b>" : '') . "</a>";
  echo "</nav>";
  if ($blad !== '') echo "<div class='md-err'>⚠ " . $h($blad) . "</div>";
  elseif (isset($_GET['ok'])) echo "<div class='md-ok'>✓ Zapisano.</div>";

  if ($tab === 'gracze'):
      $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 40));
      $lista = $q !== '' ? db_wiersze($polaczenie, "SELECT id, login FROM gracze WHERE login LIKE ? ORDER BY login LIMIT 30", ['%' . $q . '%']) : [];
  ?>
    <form class="md-f" method="GET"><input type="hidden" name="page" value="moderacja"><input type="hidden" name="tab" value="gracze"><input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="Szukaj gracza po loginie…"><button type="submit">Szukaj</button></form>
    <?php if ($lista): ?><div class="md-l"><?php foreach ($lista as $g): ?><a class="md-r" href="game.php?page=moderacja&tab=gracze&gid=<?php echo (int)$g['id']; ?>"><b><?php echo $h($g['login']); ?></b><span class="md-meta">ostrzeżeń: <?php echo mod_ostrzezen($polaczenie, (int)$g['id']); ?></span></a><?php endforeach; ?></div>
    <?php elseif ($q !== ''): ?><p>Nikogo nie znaleziono.</p><?php endif; ?>
    <?php if ($gid_w && ($cel = db_wiersz($polaczenie, "SELECT id, login, ranga_rp FROM gracze WHERE id = ?", [$gid_w]))):
        $zaw = mod_zawieszenie_aktywne($polaczenie, $gid_w); $wyr = mod_wyrzucony($polaczenie, $gid_w); $n = mod_ostrzezen($polaczenie, $gid_w);
        $moze = mod_moze_cel($polaczenie, $id_gracza, $gid_w);
    ?>
      <div class="md-box">
        <h2><?php echo $h($cel['login']); ?> · <?php echo $h(RP_RANGI[$cel['ranga_rp']]['n'] ?? 'Gracz'); ?></h2>
        <p>Aktywne ostrzeżenia: <b><?php echo $n; ?></b> (następne automatyczne zawieszenie przy <?php echo (intdiv($n, 3) + 1) * 3; ?>.)<?php if ($zaw) echo ' · <b style="color:var(--neon-red-hot)">zawieszony do ' . mod_data($zaw['do_kiedy']) . '</b>'; ?><?php if ($wyr) echo ' · <b style="color:var(--neon-red-hot)">wyrzucony z Klubu</b>'; ?></p>
        <?php if ($moze): ?>
        <div class="md-grid">
          <form method="POST"><span class="lbl">Ostrzeżenie</span><input type="hidden" name="md_akcja" value="ostrzez"><input type="hidden" name="gid" value="<?php echo $gid_w; ?>"><textarea name="powod" required maxlength="1000" placeholder="Powód (wymagany)"></textarea><button type="submit">Ostrzeż</button></form>
          <form method="POST"><span class="lbl">Zawieszenie</span><input type="hidden" name="md_akcja" value="zawies"><input type="hidden" name="gid" value="<?php echo $gid_w; ?>"><select name="dni"><?php foreach (MOD_DNI as $d) echo "<option value='$d'>$d dni</option>"; ?></select><textarea name="powod" required maxlength="1000" placeholder="Powód (wymagany)"></textarea><button type="submit">Zawieś</button></form>
          <?php if (!$wyr): ?><form method="POST" onsubmit="return confirm('Wyrzucić gracza z Klubu The Abyss?')"><span class="lbl">Wyrzucenie z Klubu</span><input type="hidden" name="md_akcja" value="wyrzuc"><input type="hidden" name="gid" value="<?php echo $gid_w; ?>"><textarea name="powod" required maxlength="1000" placeholder="Powód (wymagany)"></textarea><button type="submit">Wyrzuć z Klubu</button></form><?php endif; ?>
        </div>
        <?php else: ?><p>Nie możesz moderować tego konta<?php echo $gid_w === $id_gracza ? ' (to Ty)' : ' — MG i Adminkę karze tylko Adminka'; ?>.</p><?php endif; ?>
        <h2>Historia</h2>
        <div class="md-l"><?php $hist = db_wiersze($polaczenie, "$KARY_SQL WHERE k.gracz_id = ? ORDER BY k.kiedy DESC", [$gid_w]); foreach ($hist as $k) $kara($k, true); if (!$hist) echo '<p>Czysta kartoteka.</p>'; ?></div>
      </div>
    <?php endif; ?>

  <?php elseif ($tab === 'kary'):
      $akt = db_wiersze($polaczenie, "$KARY_SQL WHERE k.cofnieta = 0 AND (k.rodzaj = 'wyrzucenie' OR (k.rodzaj = 'zawieszenie' AND k.do_kiedy > NOW())) ORDER BY k.kiedy DESC");
      $loginy = []; foreach ($akt as $k) $loginy[(int)$k['gracz_id']] = true;
      $nazwy = []; foreach (array_keys($loginy) as $g) $nazwy[$g] = db_wiersz($polaczenie, "SELECT login FROM gracze WHERE id = ?", [$g])['login'] ?? '?';
  ?>
    <div class="md-l"><?php foreach ($akt as $k): ?><div><a href="game.php?page=moderacja&tab=gracze&gid=<?php echo (int)$k['gracz_id']; ?>"><b><?php echo $h($nazwy[(int)$k['gracz_id']]); ?></b></a><?php $kara($k, true); ?></div><?php endforeach; if (!$akt) echo '<p>Brak aktywnych zawieszeń i wyrzuceń.</p>'; ?></div>

  <?php elseif ($tab === 'odwolania'):
      $odw = db_wiersze($polaczenie, "SELECT o.*, g.login, a.login AS adm, k.rodzaj, k.dni, k.do_kiedy, k.powod, k.kiedy AS k_kiedy, m.login AS mod_login
            FROM mod_odwolania o JOIN mod_kary k ON k.id = o.kara_id JOIN gracze g ON g.id = o.gracz_id JOIN gracze m ON m.id = k.moderator_id LEFT JOIN gracze a ON a.id = o.admin_id
            ORDER BY o.status = 'nowe' DESC, o.kiedy DESC LIMIT 60");
  ?>
    <?php if (!$admin): ?><p>Odwołania rozpatruje Adminka Fabularna — tutaj tylko podgląd.</p><?php endif; ?>
    <div class="md-l"><?php foreach ($odw as $o): ?>
      <div class="md-kara <?php echo $o['status'] === 'nowe' ? 'on' : 'off'; ?>">
        <div class="md-kara-gl"><b><?php echo $h($o['login']); ?></b><span class="md-tag"><?php echo $h(MOD_NAZWY[$o['rodzaj']]) . ($o['dni'] ? " · {$o['dni']} dni" : ''); ?></span><span class="md-tag <?php echo $o['status'] === 'nowe' ? 'on' : ''; ?>"><?php echo $h($o['status']); ?></span><span class="md-meta">kara <?php echo mod_data($o['k_kiedy']); ?> · <?php echo $h($o['mod_login']); ?></span></div>
        <div class="md-meta" style="margin:0">Powód kary: <?php echo $h($o['powod']); ?></div>
        <div class="md-pow"><?php echo nl2br($h($o['tresc'])); ?></div>
        <?php if ($o['status'] !== 'nowe'): ?><div class="md-meta" style="margin:0"><?php echo $h($o['adm']); ?>, <?php echo mod_data($o['data_decyzji']); ?>: <?php echo $h($o['odpowiedz']); ?></div>
        <?php elseif ($admin && (int)$o['gracz_id'] !== $id_gracza): ?>
          <form method="POST" class="md-f col"><input type="hidden" name="oid" value="<?php echo (int)$o['id']; ?>"><textarea name="powod" required maxlength="1000" placeholder="Uzasadnienie decyzji (wymagane, trafi do gracza)"></textarea><div class="md-f"><button type="submit" name="md_akcja" value="uznaj" class="ok">Uznaj — cofnij karę</button><button type="submit" name="md_akcja" value="odrzuc">Odrzuć</button></div></form>
        <?php endif; ?>
      </div>
    <?php endforeach; if (!$odw) echo '<p>Brak odwołań.</p>'; ?></div>

  <?php elseif ($tab === 'log'):
      $log = db_wiersze($polaczenie, "SELECT l.*, m.login AS kto, g.login AS kogo FROM mod_log l JOIN gracze m ON m.id = l.moderator_id JOIN gracze g ON g.id = l.gracz_id ORDER BY l.kiedy DESC LIMIT 150");
  ?>
    <div class="md-box md-log"><span class="hd">Kiedy</span><span class="hd">Kto</span><span class="hd">Komu</span><span class="hd">Akcja</span><span class="hd">Opis</span>
      <?php foreach ($log as $l) echo "<span class='md-meta' style='margin:0'>" . mod_data($l['kiedy']) . "</span><span>" . $h($l['kto']) . "</span><span>" . $h($l['kogo']) . "</span><span class='md-tag' style='justify-self:start'>" . $h($l['akcja']) . "</span><span>" . $h($l['opis']) . "</span>"; ?>
    </div>
    <?php if (!$log) echo '<p>Log jest pusty.</p>'; ?>

  <?php else:
      $moje = db_wiersze($polaczenie, "$KARY_SQL WHERE k.gracz_id = ? ORDER BY k.kiedy DESC", [$id_gracza]);
      $zaw = mod_zawieszenie_aktywne($polaczenie, $id_gracza); $wyr = mod_wyrzucony($polaczenie, $id_gracza);
  ?>
    <p>Aktywne ostrzeżenia: <b><?php echo mod_ostrzezen($polaczenie, $id_gracza); ?></b><?php if ($zaw) echo ' · <b style="color:var(--neon-red-hot)">zawieszenie do ' . mod_data($zaw['do_kiedy']) . '</b>'; ?><?php if ($wyr) echo ' · <b style="color:var(--neon-red-hot)">wyrzucenie z Klubu</b>'; ?></p>
    <div class="md-l"><?php foreach ($moje as $k) $kara($k, false, true); if (!$moje) echo '<p>Nie masz żadnych kar.</p>'; ?></div>
  <?php endif; ?>
</div>
