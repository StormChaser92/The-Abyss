<?php
require_once "db.php";
require_once __DIR__ . '/../includes/bezpieczne.php';
require_once __DIR__ . '/../config/rangi.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* RANGI PROWADZĄCYCH — nadaje Mistrz Gry albo Adminka Fabularna. */
$moja = rp_ranga($polaczenie, $id_gracza);
if (!rp_nadzor($moja)) { echo "<div style='padding:30px;text-align:center;color:var(--neon-red-hot)'>⚠ Tylko Mistrz Gry lub Adminka Fabularna.</div>"; return; }
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kom = '';

if (isset($_POST['rg_gid'], $_POST['rg_ranga'])) {
    $gid = (int)$_POST['rg_gid']; $r = (string)$_POST['rg_ranga'];
    // Adminka i MG mogą nadawać wszystkie rangi, ale nie mogą odebrać rangi samym sobie.
    if (isset(RP_RANGI[$r]) && $gid !== $id_gracza && db_zmien($polaczenie, "UPDATE gracze SET ranga_rp = ? WHERE id = ?", [$r, $gid]) === 1) {
        powiadom($polaczenie, $gid, "Twoja ranga w Centrum Opowieści: <b>" . bz_h(RP_RANGI[$r]['n']) . "</b>.");
        $kom = 'Zapisano.';
    }
}
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 40));
$lista = $q !== ''
    ? db_wiersze($polaczenie, "SELECT id, login, ranga_rp FROM gracze WHERE login LIKE ? ORDER BY login LIMIT 50", ['%' . $q . '%'])
    : db_wiersze($polaczenie, "SELECT id, login, ranga_rp FROM gracze WHERE ranga_rp <> 'gracz' ORDER BY FIELD(ranga_rp,'adminka','mg','jmg','gospodarz'), login");
?>
<style>
.rg{display:flex;flex-direction:column;gap:16px;color:#f1ebf2;max-width:760px}
.rg h1{font-family:'Oswald',sans-serif;font-weight:500;font-size:2em;letter-spacing:4px;text-transform:uppercase;margin:0;color:#fff}
.rg p{color:#cfc6d2;line-height:1.45;margin:0}
.rg form.sz{display:flex;gap:8px}
.rg input,.rg select{background:rgba(0,0,0,.55);border:1px solid rgba(255,23,68,.3);color:#fff;padding:8px 10px;font-family:inherit;font-size:1em}
.rg input{flex:1}
.rg button{padding:8px 14px;border:1px solid var(--neon-red,#ff1744);background:rgba(255,23,68,.14);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer}
.rg-l{display:flex;flex-direction:column;gap:6px}
.rg-r{display:grid;grid-template-columns:minmax(0,1fr) 220px auto;gap:10px;align-items:center;padding:8px 12px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.08)}
.rg-r b{color:#fff}
.rg-ok{color:var(--neon-green,#3dff9a);font-family:'JetBrains Mono',monospace}
</style>
<div class="rg">
  <div><h1>Rangi prowadzących</h1><p>Gracz: Swobodna, Niski, Umiarkowany. Junior MG: do Wysokiego, po akceptacji MG. Gospodarz Klubu: Wydarzenia w Klubie do Wysokiego. Mistrz Gry: wszystko. Adminka Fabularna: nadzór, akceptacje i rangi.</p></div>
  <?php if ($kom) echo "<div class='rg-ok'>✓ $kom</div>"; ?>
  <form class="sz" method="GET"><input type="hidden" name="page" value="rangi"><input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="Szukaj gracza po loginie…"><button type="submit">Szukaj</button></form>
  <div class="rg-l">
    <?php foreach ($lista as $g): ?>
      <form class="rg-r" method="POST"><b><?php echo $h($g['login']); ?></b><input type="hidden" name="rg_gid" value="<?php echo (int)$g['id']; ?>">
        <select name="rg_ranga"<?php echo (int)$g['id'] === $id_gracza ? ' disabled' : ''; ?>><?php foreach (RP_RANGI as $k => $r) echo "<option value='$k'" . ($g['ranga_rp'] === $k ? ' selected' : '') . ">" . $h($r['n']) . "</option>"; ?></select>
        <button type="submit"<?php echo (int)$g['id'] === $id_gracza ? ' disabled' : ''; ?>>Zapisz</button></form>
    <?php endforeach; if (!$lista) echo '<p>Nikogo nie znaleziono.</p>'; ?>
  </div>
</div>
