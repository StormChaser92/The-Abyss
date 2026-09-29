<?php
require_once "db.php";
require_once __DIR__ . '/../includes/bezpieczne.php';
require_once __DIR__ . '/../config/rangi.php';
require_once __DIR__ . '/../includes/nick.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* RANGI — Mistrz Gry nadaje rangi prowadzących. Administrator nadaje wszystko:
   rangi prowadzących oraz Administratora, Barmana/kę i Proboszcza. Nikt nie zmienia rang sobie. */
$moja  = rp_ranga($polaczenie, $id_gracza);
$admin = rp_admin($polaczenie, $id_gracza);
if (!rp_nadzor($moja) && !$admin) { echo "<div style='padding:30px;text-align:center;color:var(--neon-red-hot)'>⚠ Tylko Mistrz Gry lub Administrator.</div>"; return; }
$h = 'nk_h';
$kom = '';
const RG_FLAGI = ['is_admin' => 'admin', 'is_barman' => 'barman', 'is_proboszcz' => 'proboszcz'];

if (isset($_POST['rg_gid'])) {
    $gid = (int)$_POST['rg_gid'];
    $cel = $gid !== $id_gracza ? db_wiersz($polaczenie, 'SELECT ' . nk_pola() . ' FROM gracze WHERE id = ?', [$gid]) : null;
    if ($cel) {
        $zmiany = [];
        $r = (string)($_POST['rg_ranga'] ?? $cel['ranga_rp']);
        if (isset(RP_RANGI[$r]) && $r !== $cel['ranga_rp']) {
            db_q($polaczenie, "UPDATE gracze SET ranga_rp = ? WHERE id = ?", [$r, $gid]);
            $zmiany[] = 'ranga prowadzącego: ' . RP_RANGI[$r]['n'];
        }
        if ($admin) foreach (RG_FLAGI as $kol => $k) {
            $nowa = !empty($_POST['rg_' . $k]) ? 1 : 0;
            if ($nowa !== (int)$cel[$kol]) {
                db_q($polaczenie, "UPDATE gracze SET `$kol` = ? WHERE id = ?", [$nowa, $gid]);
                $zmiany[] = ($nowa ? '+ ' : '− ') . NK_RANGI[$k]['n'];
            }
        }
        if ($zmiany) {
            $opis = implode(', ', $zmiany);
            db_q($polaczenie, "INSERT INTO rangi_log (kto_id, gracz_id, zmiana) VALUES (?, ?, ?)", [$id_gracza, $gid, mb_substr($opis, 0, 120)]);
            powiadom($polaczenie, $gid, "Zmiana Twoich rang: <b>" . bz_h($opis) . "</b>.");
            $kom = bz_h($cel['login']) . ': ' . bz_h($opis) . '.';
        }
    }
}
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 40));
$pola = nk_pola();
$lista = $q !== ''
    ? db_wiersze($polaczenie, "SELECT $pola FROM gracze WHERE login LIKE ? ORDER BY login LIMIT 50", ['%' . $q . '%'])
    : db_wiersze($polaczenie, "SELECT $pola FROM gracze WHERE ranga_rp <> 'gracz' OR is_admin = 1 OR is_barman = 1 OR is_proboszcz = 1 OR is_mg = 1
                               ORDER BY is_admin DESC, FIELD(ranga_rp,'mg','jmg','gospodarz','gracz'), login");
?>
<style>
.rg{display:flex;flex-direction:column;gap:16px;color:#f1ebf2;max-width:880px}
.rg h1{font-family:'Oswald',sans-serif;font-weight:500;font-size:2em;letter-spacing:4px;text-transform:uppercase;margin:0;color:#fff}
.rg p{color:#cfc6d2;line-height:1.45;margin:0}
.rg form.sz{display:flex;gap:8px}
.rg input[type=search],.rg select{background:rgba(0,0,0,.55);border:1px solid rgba(255,23,68,.3);color:#fff;padding:8px 10px;font-family:inherit;font-size:1em}
.rg input[type=search]{flex:1}
.rg button{padding:8px 14px;border:1px solid var(--neon-red,#ff1744);background:rgba(255,23,68,.14);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer}
.rg button:disabled,.rg select:disabled{opacity:.4;cursor:not-allowed}
.rg-l{display:flex;flex-direction:column;gap:6px}
.rg-r{display:grid;grid-template-columns:minmax(0,1fr) 200px auto;gap:10px;align-items:center;padding:8px 12px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.08)}
.rg-r.adm{grid-template-columns:minmax(0,1fr) 190px auto auto}
.rg-r .nk-n{color:#fff}
.rg-fl{display:flex;gap:6px;flex-wrap:wrap}
.rg-fl label{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border:1px solid rgba(255,255,255,.1);cursor:pointer;font-size:.9em;color:var(--txt-dim)}
.rg-fl input{accent-color:var(--neon-red)}
.rg-fl label:has(input:checked){color:#fff;border-color:currentColor}
.rg-fl .nk-r svg{width:13px;height:13px}
.rg-ok{color:var(--neon-green,#3dff9a);font-family:'JetBrains Mono',monospace}
@media(max-width:820px){.rg-r,.rg-r.adm{grid-template-columns:1fr}}
</style>
<div class="rg">
  <div><h1>Rangi</h1><p>Gracz: Swobodna, Niski, Umiarkowany. Junior MG: do Wysokiego, po akceptacji MG. Gospodarz Klubu: Wydarzenia w Klubie do Wysokiego. Mistrz Gry: wszystko, akceptacje, przegląd i rangi prowadzących.<?php if ($admin): ?> Jako Administrator nadajesz też Administratora, Barmana/kę i Proboszcza.<?php endif; ?></p></div>
  <?php if ($kom) echo "<div class='rg-ok'>✓ $kom</div>"; ?>
  <form class="sz" method="GET"><input type="hidden" name="page" value="rangi"><input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="Szukaj gracza po loginie…"><button type="submit">Szukaj</button></form>
  <div class="rg-l">
    <?php foreach ($lista as $g): $ja = (int)$g['id'] === $id_gracza; $d = $ja ? ' disabled' : ''; ?>
      <form class="rg-r<?php echo $admin ? ' adm' : ''; ?>" method="POST"><?php echo nk_html($g); ?><input type="hidden" name="rg_gid" value="<?php echo (int)$g['id']; ?>">
        <select name="rg_ranga" aria-label="Ranga prowadzącego"<?php echo $d; ?>><?php foreach (RP_RANGI as $k => $r) echo "<option value='$k'" . ($g['ranga_rp'] === $k ? ' selected' : '') . ">" . $h($r['n']) . "</option>"; ?></select>
        <?php if ($admin): ?><div class="rg-fl"><?php foreach (RG_FLAGI as $kol => $k) echo "<label style=\"color:" . NK_RANGI[$k]['k'] . "\"><input type=\"checkbox\" name=\"rg_$k\" value=\"1\"" . (!empty($g[$kol]) ? ' checked' : '') . "$d>" . nk_ikona($k) . $h(NK_RANGI[$k]['n']) . "</label>"; ?></div><?php endif; ?>
        <button type="submit"<?php echo $d; ?>>Zapisz</button></form>
    <?php endforeach; if (!$lista) echo '<p>Nikogo nie znaleziono.</p>'; ?>
  </div>
</div>
