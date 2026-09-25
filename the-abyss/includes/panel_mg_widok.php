<?php
/* Widok Kulis MG — dołączany z pm_render() (includes/panel_mg.php).
   W zasięgu: $db, $s, $gid, $prow, $sam, $blad. */
global $UM_ATRYBUTY;
$sid  = (int)$s['id'];
$P    = rw_parametry($s);
$cele = pm_cele($db, $sid);
if ($sam) $cele = array_intersect_key($cele, ['g' . $gid => 1]);
$walka = $prow && !empty($s['walka_aktywna']);
$U    = $walka ? rw_uczestnicy($db, $sid) : [];
$Ul   = array_values($U);
$pv   = $prow ? ($_SESSION['pm_pv'][$sid] ?? null) : null;
$tab  = $sam ? 'test' : (string)($_GET['pm'] ?? ($pv['tab'] ?? ($walka ? 'walka' : 'test')));
if (!in_array($tab, ['walka', 'test', 'npc', 'ukr'], true)) $tab = 'test';
$otwarty = isset($_GET['pm']) || $blad || $pv;
$ukryte = $prow ? db_wiersze($db, "SELECT * FROM sesje_rzuty_ukryte WHERE sesja_id = ? AND ujawniony = 0 ORDER BY id DESC LIMIT 30", [$sid]) : [];
$npc  = $prow ? db_wiersze($db, "SELECT * FROM sesje_npc WHERE sesja_id = ? ORDER BY nazwa", [$sid]) : [];
$um_nazwy = array_map(fn($u) => $u['n'], um_lista());
$um_opt = pm_opcje(array_combine($um_nazwy, $um_nazwy), '');
$atr_opt = pm_opcje(array_map(fn($a) => $a['nazwa'], $UM_ATRYBUTY), 'Z');
$cel_opt = pm_opcje(array_map(fn($c) => $c['nazwa'] . ($c['npc'] ? ' · NPC' : ''), $cele), '');
$bron_opt = fn($sel) => pm_opcje(array_map(fn($b) => $b['n'], RW_BRON), $sel);
$panc_opt = fn($sel) => pm_opcje(array_map(fn($b) => $b['n'], RW_PANC), $sel);
$js = [];
foreach ($cele as $k => $c) {
    $um = !empty($c['row']['umiejetnosci']) ? (json_decode($c['row']['umiejetnosci'], true) ?: []) : [];
    $at = []; foreach ($UM_ATRYBUTY as $ak => $ad) $at[$ak] = um_atrybut($c['row'], $ak);
    $js[$k] = ['n' => $c['nazwa'], 'um' => $um, 'at' => $at, 'zal' => $c['zal']];
}
$zlozone = db_wiersze($db, "SELECT * FROM sesje_test_zlozony WHERE sesja_id = ? AND status = 'trwa' ORDER BY id", [$sid]);
$um_def = []; foreach (um_lista() as $u) $um_def[$u['n']] = [$u['g'] ?? 'I', $u['d'] ?? null];
$teraz = $Ul[(int)$s['walka_tura']]['k'] ?? ($Ul[0]['k'] ?? '');
$h = 'rw_h';
?>
<style>
.pmk-tgl{position:fixed;top:50%;right:0;transform:translateY(-50%);z-index:9001;writing-mode:vertical-rl;padding:16px 8px;background:var(--neon-red,#ff1744);color:#fff;border:0;font-family:'Oswald',sans-serif;letter-spacing:4px;text-transform:uppercase;box-shadow:0 0 18px rgba(255,23,68,.6);cursor:pointer;transition:right .3s}
body.pmk-on .pmk-tgl{right:min(460px,100vw)}
.pmk{position:fixed;top:0;right:0;bottom:0;width:460px;max-width:100vw;z-index:9000;background:rgba(10,5,10,.97);border-left:1.5px solid var(--neon-red,#ff1744);box-shadow:-10px 0 40px rgba(0,0,0,.6);transform:translateX(100%);transition:transform .3s;display:flex;flex-direction:column;color:#f1ebf2;font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:500}
body.pmk-on .pmk{transform:none}
.pmk *{box-sizing:border-box}
.pmk button,.pmk select,.pmk input,.pmk textarea{font-family:inherit;font-size:1em;font-weight:600}
.pmk-h{padding:16px 18px 0;display:flex;flex-direction:column;gap:12px;border-bottom:1px solid rgba(255,23,68,.22)}
.pmk-h h2{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.35em;letter-spacing:3px;text-transform:uppercase;color:#fff;margin:0}
.pmk-h h2 small{font-family:'JetBrains Mono',monospace;font-size:.5em;color:#cfc6d2;letter-spacing:1.5px;margin-left:6px}
.pmk-tabs{display:flex;gap:2px}
.pmk-tabs button{flex:1;padding:9px 6px;background:rgba(0,0,0,.35);border:1px solid transparent;border-bottom:0;color:#cfc6d2;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:.85em;font-weight:500;cursor:pointer}
.pmk-tabs button.on{color:#fff;background:rgba(255,23,68,.16);border-color:rgba(255,23,68,.45)}
.pmk-tabs i{font-style:normal;color:#ffd23d;margin-left:4px}
.pmk-b{flex:1;overflow-y:auto;padding:16px 18px 30px}
.pmk-pane{display:none;flex-direction:column;gap:12px}.pmk-pane.on{display:flex}
.pmk form{display:flex;flex-direction:column;gap:10px;margin:0}
.pmk .lbl{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.6px;text-transform:uppercase;color:#cfc6d2}
.pmk .f{display:flex;flex-direction:column;gap:5px}
.pmk .f2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}
.pmk select,.pmk input[type=number],.pmk input[type=text],.pmk textarea{background:rgba(0,0,0,.55);border:1px solid rgba(255,23,68,.22);color:#fff;padding:8px 10px;width:100%}
.pmk select:focus,.pmk input:focus{outline:none;border-color:#ff1744}
.pmk .seg{display:flex;flex-wrap:wrap;gap:4px}
.pmk .seg label,.pmk .seg button{flex:1 1 auto;padding:7px 8px;background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.14);color:#cfc6d2;font-size:.9em;text-align:center;cursor:pointer}
.pmk .seg input{display:none}
.pmk .seg label:has(input:checked),.pmk .seg button.on{color:#fff;border-color:var(--c,#ff1744);box-shadow:inset 0 0 0 1px var(--c,#ff1744)}
.pmk .cech{display:flex;flex-wrap:wrap;gap:5px}
.pmk .cech label{display:flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.4);color:#cfc6d2;cursor:pointer;font-size:.92em}
.pmk .cech label b{font-family:'JetBrains Mono',monospace;font-weight:400;font-size:.85em;color:var(--c)}
.pmk .cech label:has(input:checked){color:#fff;border-color:var(--c);box-shadow:inset 0 0 0 1px var(--c)}
.pmk .none{color:#a79eab;font-style:italic}
.pmk-btn{padding:10px 14px;border:1px solid #ff1744;background:rgba(255,23,68,.15);color:#fff;font-family:'Oswald',sans-serif !important;letter-spacing:2px;text-transform:uppercase;font-weight:500 !important;font-size:.9em !important;cursor:pointer}
.pmk-btn:hover{background:#ff1744}
.pmk-btn.gold{border-color:#ffd23d;background:rgba(255,210,61,.1)}.pmk-btn.gold:hover{background:#ffd23d;color:#000}
.pmk-btn.vio{border-color:#b48cff;background:rgba(180,140,255,.1)}.pmk-btn.vio:hover{background:#b48cff;color:#000}
.pmk-btn.ghost{border-color:rgba(255,255,255,.2);background:transparent}.pmk-btn.ghost:hover{background:rgba(255,255,255,.08)}
.pmk-btn.sm{padding:5px 9px;font-size:.78em !important;letter-spacing:1.2px}
.pmk .acts{display:flex;gap:6px;flex-wrap:wrap}.pmk .acts>*{flex:1 1 auto}
.pmk .acts form{flex:1 1 auto}.pmk .acts form button{width:100%}
.pmk .hint{font-size:.9em;color:#cfc6d2;line-height:1.45;margin:0}
.pmk .info{padding:9px 12px;border:1px solid rgba(74,214,255,.4);background:rgba(74,214,255,.06);color:#e3f7ff;font-size:.92em;line-height:1.45}
.pmk .info b{color:#4ad6ff}
.pmk .alert{padding:9px 12px;border:1px solid #ff3d5e;background:rgba(255,23,68,.1);color:#fff;font-size:.92em;line-height:1.4}
.pmk .hr{height:1px;background:rgba(255,23,68,.22)}
.pmk-pv{display:flex;flex-direction:column;gap:8px}
.pmk-pv .lbl b{color:#ffd23d}
.pmk-trk{display:flex;flex-direction:column;gap:6px}
.pmk-trk details{border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.35)}
.pmk-trk details.now{border-color:#ff7a3d}
.pmk-trk summary{display:grid;grid-template-columns:30px minmax(0,1fr) auto;gap:10px;align-items:center;padding:8px 10px;cursor:pointer;list-style:none}
.pmk-trk summary .o{font-family:'JetBrains Mono',monospace;color:#ff7a3d;text-align:center}
.pmk-trk summary .n{display:flex;flex-direction:column;gap:4px;min-width:0}
.pmk-trk summary .n b{color:#fff}
.pmk-trk summary .n small{font-size:.82em;color:#cfc6d2}
.pmk-trk summary .n small .no{color:#ffd23d}.pmk-trk summary .n small .ok{color:#3dff9a}
.pmk-trk summary .hpv{font-family:'JetBrains Mono',monospace;font-size:.8em;color:#fff;text-align:right;white-space:nowrap}
.pmk-trk details form{padding:0 10px 10px}
.pmk-trk .chk{display:flex;flex-wrap:wrap;gap:4px 12px;font-size:.9em;color:#cfc6d2}
.pmk-trk .chk label{display:flex;gap:5px;align-items:center}
.pmk .npc{border:1px dashed rgba(255,255,255,.18);padding:10px 12px;display:flex;flex-direction:column;gap:6px;background:rgba(0,0,0,.3)}
.pmk .npc b{color:#fff;font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:1px}
.pmk .atr{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:4px;font-family:'JetBrains Mono',monospace;font-size:.76em;text-align:center}
.pmk .atr span{padding:3px 0;border:1px solid rgba(255,255,255,.1);color:#cfc6d2}
.pmk .atr span b{display:block;color:#fff;font-family:'Oswald',sans-serif;font-size:1.25em;font-weight:500}
.pmk .atr input{padding:4px;text-align:center}
.pmk .hid{font-family:'JetBrains Mono',monospace;font-size:.8em;color:#cfc6d2;padding:8px 10px;border-left:2px solid #b48cff;background:rgba(180,140,255,.06);display:flex;flex-direction:column;gap:6px}
.pmk .hid b{color:#fff;font-weight:500}
@media (min-width:1300px){body.pmk-on{padding-right:460px}}
</style>

<button class="pmk-tgl" id="pmkTgl" type="button"><?php echo $prow ? 'Kulisy MG' : 'Rzuty'; ?></button>
<aside class="pmk" id="pmk" aria-label="Kulisy MG">
  <div class="pmk-h">
    <h2><?php echo $prow ? 'Kulisy MG <small>widoczne tylko dla prowadzącego</small>' : 'Rzuty <small>Opowieść Swobodna · na własną postać</small>'; ?></h2>
    <div class="pmk-tabs" id="pmkTabs">
      <?php if ($prow): ?><button data-t="walka" type="button">Walka</button><?php endif; ?>
      <button data-t="test" type="button">Testy</button>
      <?php if ($prow): ?><button data-t="npc" type="button">NPC</button><button data-t="ukr" type="button">Ukryte<?php if ($ukryte) echo '<i>' . count($ukryte) . '</i>'; ?></button><?php endif; ?>
    </div>
  </div>
  <div class="pmk-b">
    <?php if ($blad): ?><div class="alert" style="margin-bottom:12px">⚠ <?php echo $h($blad); ?></div><?php endif; ?>

    <?php if ($prow): ?>
    <!-- ═══ WALKA ═══ -->
    <section class="pmk-pane" id="pmk-walka">
      <?php if (!$walka): ?>
        <p class="hint">Walka nie trwa. Start doda do trackera wszystkich zaakceptowanych graczy i <?php echo count($npc); ?> NPC tej Opowieści, a potem automatycznie rzuci Inicjatywę (k100 + Zręczność / 5). PŻ startują od maksimum z karty i liczą się tylko w tej Opowieści.</p>
        <p class="hint">Poziom <b style="color:#fff"><?php echo RP_POZIOMY[$s['poziom']]['n'] ?? ''; ?></b> · OoP <b style="color:#fff"><?php echo $P['oop']; ?></b> · obrażenia za trafienie <b style="color:#fff"><?php echo $P['oop'] * $P['mult']; ?></b><?php echo $P['kryt'] ? ' · krytyki obowiązkowe' : ''; ?></p>
        <form method="POST"><input type="hidden" name="pm_akcja" value="walka_start"><button class="pmk-btn" type="submit">Rozpocznij walkę</button></form>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:baseline"><span class="lbl">Runda <?php echo (int)$s['walka_runda']; ?> · kolejność Inicjatywy</span><span class="lbl">OoP <?php echo $P['oop']; ?> · obr. <?php echo $P['oop'] * $P['mult']; ?></span></div>
        <div class="pmk-trk">
        <?php foreach ($Ul as $i => $u): $w = $u['w']; ?>
          <details class="<?php echo $u['k'] === $teraz ? 'now' : ''; ?>">
            <summary><span class="o"><?php echo (int)$w['ini']; ?></span><span class="n"><b><?php echo $h($u['nazwa']); ?><?php echo $u['npc'] ? ' <small>NPC</small>' : ''; ?></b><?php echo pm_hp_bar($w); ?>
              <small><?php echo $h(RW_BRON[$w['bron']]['n'] ?? $w['bron']); ?> · <?php echo $h(RW_PANC[$w['pancerz']]['n'] ?? ''); ?><?php echo (int)$w['tarcza'] ? ' + tarcza' : ''; ?> <?php echo $u['npc'] ? '' : ((int)$w['nc_ok'] ? '<span class="ok">✓ NC</span>' : '<span class="no">NC niepotwierdzone</span>'); ?></small><?php echo pm_fx_html($u); ?></span>
              <span class="hpv"><?php echo (int)$w['hp']; ?> / <?php echo rw_eff_max($w); ?></span></summary>
            <form method="POST">
              <input type="hidden" name="pm_akcja" value="uczestnik_zapisz"><input type="hidden" name="klucz" value="<?php echo $h($u['k']); ?>">
              <div class="f2"><div class="f"><span class="lbl">Broń (z NC)</span><select name="bron"><?php echo $bron_opt($w['bron']); ?></select></div><div class="f"><span class="lbl">Pancerz</span><select name="pancerz"><?php echo $panc_opt($w['pancerz']); ?></select></div></div>
              <div class="f2"><div class="f"><span class="lbl">PŻ</span><input type="number" name="hp" value="<?php echo (int)$w['hp']; ?>" min="0" max="<?php echo rw_eff_max($w); ?>"></div><div class="f"><span class="lbl">Uraz</span><select name="uraz"><?php echo pm_opcje(RW_URAZ, (int)$w['uraz']); ?></select></div></div>
              <div class="chk"><label><input type="checkbox" name="tarcza" value="1"<?php echo (int)$w['tarcza'] ? ' checked' : ''; ?>> Tarcza</label><?php if (!$u['npc']): ?><label><input type="checkbox" name="nc_ok" value="1"<?php echo (int)$w['nc_ok'] ? ' checked' : ''; ?>> NC potwierdzone</label><?php endif; ?><label><input type="checkbox" name="czysc" value="1"> Usuń efekty</label><?php if ($w['kryt_tury'] !== null): ?><label><input type="checkbox" name="stabilizuj" value="1"> Pomoc medyczna udana</label><?php endif; ?><label><input type="checkbox" name="usun" value="1"> Usuń z walki</label></div>
              <button class="pmk-btn sm ghost" type="submit">Zapisz</button>
            </form>
          </details>
        <?php endforeach; ?>
        </div>
        <?php foreach ($Ul as $u) if ($u['w']['kryt_tury'] !== null): ?>
          <div class="alert"><b><?php echo $h($u['nazwa']); ?></b>: Uraz krytyczny i 0 PŻ. <?php echo (int)$u['w']['kryt_tury'] > 0 ? 'Pomoc medyczna w ciągu <b>' . (int)$u['w']['kryt_tury'] . '</b> tur, inaczej postać umiera.' : 'Czas minął — decyzja o śmierci należy do MG.'; ?></div>
        <?php endif; ?>
        <div class="acts">
          <form method="POST"><input type="hidden" name="pm_akcja" value="walka_next"><button class="pmk-btn sm ghost" type="submit">Następny ›</button></form>
          <form method="POST"><input type="hidden" name="pm_akcja" value="walka_runda"><button class="pmk-btn sm ghost" type="submit">Koniec rundy</button></form>
          <form method="POST"><input type="hidden" name="pm_akcja" value="walka_ini"><button class="pmk-btn sm ghost" type="submit">Nowa Inicjatywa</button></form>
          <form method="POST" onsubmit="return confirm('Zakończyć walkę? Tracker zostanie wyczyszczony.')"><input type="hidden" name="pm_akcja" value="walka_koniec"><button class="pmk-btn sm ghost" type="submit">Zakończ walkę</button></form>
        </div>
        <?php $poza = array_diff_key($cele, $U); if ($poza): ?>
        <form method="POST" style="flex-direction:row"><input type="hidden" name="pm_akcja" value="uczestnik_dodaj"><select name="klucz"><?php echo pm_opcje(array_map(fn($c) => $c['nazwa'], $poza), ''); ?></select><button class="pmk-btn sm ghost" type="submit">+ Do walki</button></form>
        <?php endif; ?>
        <div class="hr"></div>
        <form method="POST" id="pmkWalka">
          <span class="lbl">Akcja</span>
          <div class="seg" id="pmkAkcja"><label><input type="radio" name="pm_akcja" value="atak" checked>Atak</label><label><input type="radio" name="pm_akcja" value="odp">Odpoczynek</label><label><input type="radio" name="pm_akcja" value="upadek">Upadek</label></div>
          <div class="f2"><div class="f"><span class="lbl" data-a-lbl>Kto</span><select name="kto" id="pmkKto"><?php foreach ($Ul as $u) echo "<option value='" . $h($u['k']) . "' data-bron='" . $h($u['w']['bron']) . "'" . ($u['k'] === $teraz ? ' selected' : '') . ">" . $h($u['nazwa']) . "</option>"; ?></select></div>
            <div class="f" data-a="atak"><span class="lbl">Cel</span><select name="cel"><?php foreach ($Ul as $u) if ($u['k'] !== $teraz) echo "<option value='" . $h($u['k']) . "'>" . $h($u['nazwa']) . "</option>"; ?></select></div></div>
          <div class="f2" data-a="atak"><div class="f"><span class="lbl">Broń</span><select name="bron" id="pmkBron"><?php echo $bron_opt($U[$teraz]['w']['bron'] ?? 'wrecz'); ?></select></div><div class="f"><span class="lbl">Mod MG (Trafienie)</span><input type="number" name="mod" value="0" step="5"></div></div>
          <?php foreach (['wc_a' => ['Zalety i Wady atakującego (±' . UM_MOD_CECHA . ' do Trafienia)', 'data-wa'], 'wc_t' => ['Zalety i Wady celu (±' . UM_MOD_CECHA . ' do Uniku)', 'data-wt']] as $pole => [$tyt, $attr]): ?>
          <div class="f" data-a="atak"><span class="lbl"><?php echo $tyt; ?></span>
            <?php foreach ($Ul as $u): $zl = pm_cechy($u['g']['zalety'] ?? ''); $wd = pm_cechy($u['g']['wady'] ?? ''); ?><div class="cech" <?php echo $attr; ?>="<?php echo $h($u['k']); ?>">
              <?php foreach ($zl as $n): ?><label style="--c:#3dff9a"><input type="checkbox" name="<?php echo $pole; ?>[<?php echo $h($u['k']); ?>][]" value="<?php echo $h($n); ?>"><?php echo $h($n); ?><b>+<?php echo UM_MOD_CECHA; ?></b></label><?php endforeach; ?>
              <?php foreach ($wd as $n): ?><label style="--c:#ff3d5e"><input type="checkbox" name="<?php echo $pole; ?>[<?php echo $h($u['k']); ?>][]" value="<?php echo $h($n); ?>"><?php echo $h($n); ?><b>−<?php echo UM_MOD_CECHA; ?></b></label><?php endforeach; ?>
              <?php if (!$zl && !$wd): ?><span class="none">Brak Zalet i Wad na karcie.</span><?php endif; ?>
            </div><?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <?php if (!$P['kryt']): ?><label class="hint" data-a="atak" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="kryt" value="1"> Rozpatruj efekty krytyczne (opcjonalne na tym poziomie)</label><?php endif; ?>
          <label class="hint" data-a="odp" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="atakowany" value="1"> Postać była atakowana w tej turze</label>
          <div class="f2" data-a="upadek"><div class="f"><span class="lbl">Wysokość</span><select name="wys"><?php echo pm_opcje(array_map(fn($x) => $x[0] . ' (' . ($x[1] > 0 ? '+' : '') . $x[1] . ')', RW_WYS), '4'); ?></select></div><div class="f"><span class="lbl">Podłoże</span><select name="pod"><option value="-10">Miękkie −10</option><option value="0" selected>Zwykłe 0</option><option value="10">Twarde +10</option></select></div></div>
          <label class="hint" data-a="upadek" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="akr" value="1"> Udany test Akrobatyki (do 4 m: obrażenia o połowę)</label>
          <button class="pmk-btn" type="submit">Rzuć — podgląd</button>
        </form>
      <?php endif; ?>
      <?php if ($pv && ($pv['tab'] ?? '') === 'walka') pm_pv_html($pv); ?>
    </section>
    <?php endif; ?>

    <!-- ═══ TESTY ═══ -->
    <section class="pmk-pane" id="pmk-test">
      <?php if ($prow && $zlozone): ?><div class="f"><span class="lbl">Trwające testy złożone</span>
        <?php foreach ($zlozone as $z): $pr = min(100, round((int)$z['suma'] / max(1, (int)$z['cel']) * 100)); ?>
        <div class="npc"><div style="display:flex;justify-content:space-between;gap:8px"><b><?php echo $h($z['nazwa']); ?></b><span class="lbl">tura <?php echo (int)$z['tura']; ?> / <?php echo (int)$z['tury']; ?></span></div>
          <div class="pm-hp"><i style="width:<?php echo $pr; ?>%"></i></div><span class="hint"><?php echo (int)$z['suma']; ?> / <?php echo (int)$z['cel']; ?> sukcesów</span>
          <div class="acts"><form method="POST"><input type="hidden" name="pm_akcja" value="zl_tura"><input type="hidden" name="zid" value="<?php echo (int)$z['id']; ?>"><button class="pmk-btn sm ghost" type="submit"><?php echo (int)$z['tura'] >= (int)$z['tury'] ? 'Rozstrzygnij' : 'Następna tura'; ?></button></form>
          <form method="POST"><input type="hidden" name="pm_akcja" value="zl_zamknij"><input type="hidden" name="zid" value="<?php echo (int)$z['id']; ?>"><button class="pmk-btn sm ghost" type="submit">Zakończ teraz</button></form></div></div>
        <?php endforeach; ?></div><?php endif; ?>
      <form method="POST" id="pmkTest">
        <input type="hidden" name="pm_akcja" value="test">
        <div class="f"><span class="lbl">Sytuacja</span><div class="seg" id="pmkSyt"><button type="button" data-s="wiedza">Wiedza</button><button type="button" data-s="obs">Obserwacja</button><button type="button" data-s="obrona">Obrona przed wpływem</button><button type="button" data-s="zamek">Zamek</button><button type="button" data-s="kon">Galop</button><button type="button" data-s="bal">Taniec na balu</button><button type="button" data-s="wsp">Wspinaczka</button><button type="button" data-s="auto">Pościg autem</button></div></div>
        <div class="f"><span class="lbl">Co się dzieje (trafi do posta)</span><input type="text" name="sd" id="pmkSd" maxlength="200" placeholder="np. Iris próbuje otworzyć zamek szafki w szatni"></div>
        <div class="f2"><div class="f"><span class="lbl">Rodzaj testu</span><select name="rodzaj" id="pmkRodz"><option value="prosty">Test Umiejętności</option><option value="atr">Test Atrybutu</option><option value="srednia">Test średniej Atrybutów</option><option value="zdol">Test Zdolności</option><option value="przec">Test przeciwstawny</option><option value="zloz">Test złożony (na kilka tur)</option><option value="praw">Test prawdopodobieństwa</option><option value="kosc">Dowolna kość</option></select></div>
          <div class="f" data-r="prosty atr srednia zdol przec zloz"><span class="lbl" data-r-lbl>Postać / NPC</span><?php if ($sam): ?><input type="text" value="<?php echo $h($cele['g' . $gid]['nazwa'] ?? ''); ?>" disabled><input type="hidden" name="cel" id="pmkCel" value="g<?php echo $gid; ?>"><?php else: ?><select name="cel" id="pmkCel"><?php echo $cel_opt; ?></select><?php endif; ?></div></div>
        <div class="f" data-r="prosty przec zloz"><span class="lbl">Umiejętność</span><select name="um" id="pmkUm"><?php echo $um_opt; ?></select></div>
        <div class="f" data-r="prosty"><span class="lbl">Atrybut</span><select name="ag"><option value="g">Główny</option><option value="d">Dodatkowy (gdy uzasadnia to sytuacja)</option></select></div>
        <div class="f" data-r="atr"><span class="lbl">Atrybut</span><select name="at"><?php echo $atr_opt; ?></select></div>
        <div class="f" data-r="srednia zdol"><span class="lbl">Atrybuty (test średniej: co najmniej 2)</span><div class="cech" style="display:flex"><?php foreach ($UM_ATRYBUTY as $ak => $ad): ?><label style="--c:#4ad6ff"><input type="checkbox" name="ats[]" value="<?php echo $ak; ?>"><?php echo $h($ad['nazwa']); ?></label><?php endforeach; ?></div></div>
        <div class="f" data-r="zdol"><span class="lbl">Zdolność („Możliwość”) z karty</span><select name="zd" id="pmkZd"></select><p class="hint">Bez tej Zdolności rzutu się nie wykonuje — test jest automatycznie nieudany. Działa jak test Atrybutu albo średniej wybranych Atrybutów.</p></div>
        <?php if (!$sam): ?><div class="f2" data-r="przec"><div class="f"><span class="lbl">Strona B</span><select name="cel_b"><?php echo $cel_opt; ?></select></div><div class="f"><span class="lbl">Umiejętność B</span><select name="um_b"><?php echo $um_opt; ?></select></div></div><?php endif; ?>
        <div class="f" data-r="zloz"><span class="lbl">Test złożony</span><select name="zl_id" id="pmkZl"><option value="0">+ Nowy test złożony</option><?php foreach ($zlozone as $z) echo "<option value='" . (int)$z['id'] . "'>" . $h($z['nazwa']) . " · " . (int)$z['suma'] . "/" . (int)$z['cel'] . " · tura " . (int)$z['tura'] . "/" . (int)$z['tury'] . "</option>"; ?></select></div>
        <div class="f" data-r="zloz" data-zl-nowy><span class="lbl">Nazwa, wymagane sukcesy i liczba tur</span><div class="f2"><input type="text" name="zl_nazwa" maxlength="80" placeholder="np. Budowa barykady"><div class="f2"><input type="number" name="zl_cel" value="10" min="1" max="50" title="Wymagane sukcesy"><input type="number" name="zl_tury" value="3" min="1" max="20" title="Liczba tur"></div></div></div>
        <p class="hint" data-r="zloz">Seria rzutów rozłożona na tury — każda postać może rzucać co turę, wyniki się sumują. Krytyczny sukces 3, sukces 2, minimalny 1, porażka 0, krytyczna porażka −1.</p>
        <p class="hint" data-r="przec">Mody działają na pierwszą stronę. Wygrywa wyższy poziom powodzenia, przy remisie większy zapas pod progiem.</p>
        <div class="f2" data-r="praw"><div class="f"><span class="lbl">Szansa %</span><input type="number" name="p" value="50" min="1" max="99"></div><div class="f"><span class="lbl">Czego dotyczy</span><span class="hint">opis z pola „Co się dzieje”</span></div></div>
        <div class="f2" data-r="kosc"><div class="f"><span class="lbl">Ile kości</span><input type="number" name="kn" value="2" min="1" max="20"></div><div class="f"><span class="lbl">Ścianki</span><select name="ks"><?php echo pm_opcje(array_combine(PM_KOSCI, array_map(fn($k) => "k$k", PM_KOSCI)), 6); ?></select></div></div>
        <div class="f" data-r="kosc"><span class="lbl">Premia do sumy</span><input type="number" name="kb" value="0"></div>
        <div class="f" data-r="prosty atr srednia zdol przec zloz"><span class="lbl">Ryzyko akcji</span><div class="seg" id="pmkRyz"><?php foreach (PM_RYZYKO as $v => $n): ?><label style="--c:<?php echo [10 => '#3dff9a', 0 => '#ffd23d', -10 => '#ff7a3d', -20 => '#ff1744'][$v]; ?>"><input type="radio" name="ryz" value="<?php echo $v; ?>"<?php echo $v === 0 ? ' checked' : ''; ?>><?php echo $n; ?></label><?php endforeach; ?></div></div>
        <div class="f" data-r="prosty atr srednia zdol przec zloz"><span class="lbl">Zalety i Wady z karty (±<?php echo UM_MOD_CECHA; ?>)</span>
          <?php foreach ($cele as $k => $c): ?><div class="cech" data-cel="<?php echo $h($k); ?>">
            <?php foreach ($c['zal'] as $n): ?><label style="--c:#3dff9a"><input type="checkbox" name="cechy[<?php echo $h($k); ?>][]" value="<?php echo $h($n); ?>"><?php echo $h($n); ?><b>+<?php echo UM_MOD_CECHA; ?></b></label><?php endforeach; ?>
            <?php foreach ($c['wad'] as $n): ?><label style="--c:#ff3d5e"><input type="checkbox" name="cechy[<?php echo $h($k); ?>][]" value="<?php echo $h($n); ?>"><?php echo $h($n); ?><b>−<?php echo UM_MOD_CECHA; ?></b></label><?php endforeach; ?>
            <?php if (!$c['zal'] && !$c['wad']): ?><span class="none">Brak Zalet i Wad na karcie.</span><?php endif; ?>
          </div><?php endforeach; ?>
        </div>
        <?php if (!$sam): ?><div class="f" data-r="prosty atr srednia zdol przec zloz"><span class="lbl">Mod MG</span><input type="number" name="mod" value="0" step="5" min="-50" max="50"></div><?php endif; ?>
        <?php if (!$sam): ?><label class="hint" data-r="prosty atr srednia zdol przec" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="pasywny" value="1" id="pmkPas"> Test pasywny (wiedza, obserwacja, obrona — bez deklaracji gracza, nie zużywa akcji)</label><?php endif; ?>
        <div class="info" id="pmkInfo" style="display:none"></div>
        <div class="acts"><button class="pmk-btn" type="submit"><?php echo $sam ? 'Rzuć — od razu do Opowieści' : 'Rzuć — podgląd'; ?></button><?php if (!$sam): ?><button class="pmk-btn ghost" type="submit" name="auto" value="1" data-r="prosty atr srednia zdol">Automatyczny sukces</button><?php endif; ?></div>
        <?php if (!$sam): ?><p class="hint" data-r="prosty atr srednia zdol">Automatyczny sukces bez rzutu: gdy gracz poprosi o niego w NC, a szansa wynosi co najmniej 50%. Nie dotyczy walki.</p><?php endif; ?>
        <?php if ($sam): ?><p class="hint">W Opowieści Swobodnej rzucasz sam na swoją postać, a wynik od razu trafia do Opowieści.</p><?php endif; ?>
      </form>
      <?php if ($pv && ($pv['tab'] ?? '') === 'test') pm_pv_html($pv); ?>
    </section>

    <?php if ($prow): ?>
    <!-- ═══ NPC ═══ -->
    <section class="pmk-pane" id="pmk-npc">
      <p class="hint">NPC zapisani w tej Opowieści. Pojawiają się na listach celów w Walce i Testach.</p>
      <?php foreach ($npc as $n): $um = json_decode($n['umiejetnosci'] ?: '[]', true) ?: []; ?>
        <div class="npc"><div style="display:flex;justify-content:space-between;gap:8px"><b><?php echo $h($n['nazwa']); ?></b><span class="lbl"><?php echo (int)$n['hp_max']; ?> PŻ</span></div>
          <div class="atr"><?php foreach ($UM_ATRYBUTY as $ak => $ad) echo "<span>$ak<b>" . (int)$n[$ad['kolumna']] . "</b></span>"; ?></div>
          <span class="hint"><?php echo $h(RW_BRON[$n['bron']]['n'] ?? ''); ?> · <?php echo $h(RW_PANC[$n['pancerz']]['n'] ?? ''); ?> · <?php echo $um ? $h(implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($um), $um))) : 'bez Umiejętności'; ?></span>
          <span class="hint">Zalety: <?php echo $h($n['zalety']); ?> · Wady: <?php echo $h($n['wady']); ?></span>
          <form method="POST" onsubmit="return confirm('Usunąć NPC z Opowieści?')"><input type="hidden" name="pm_akcja" value="npc_usun"><input type="hidden" name="nid" value="<?php echo (int)$n['id']; ?>"><button class="pmk-btn sm ghost" type="submit">Usuń</button></form>
        </div>
      <?php endforeach; ?>
      <div class="hr"></div>
      <form method="POST">
        <input type="hidden" name="pm_akcja" value="npc_dodaj">
        <span class="lbl">Nowy NPC</span>
        <div class="f2"><div class="f"><span class="lbl">Nazwa</span><input type="text" name="nazwa" maxlength="80" required placeholder="np. Snajper na dachu"></div><div class="f"><span class="lbl">PŻ max</span><input type="number" name="hp_max" value="100" min="1"></div></div>
        <div class="atr"><?php foreach ($UM_ATRYBUTY as $ak => $ad) echo "<label><span class='lbl'>$ak</span><input type='number' name='{$ad['kolumna']}' value='40' min='0' max='100'></label>"; ?></div>
        <div class="f2"><div class="f"><span class="lbl">Broń</span><select name="bron"><?php echo $bron_opt('wrecz'); ?></select></div><div class="f"><span class="lbl">Pancerz</span><select name="pancerz"><?php echo $panc_opt('brak'); ?></select></div></div>
        <?php for ($i = 0; $i < 4; $i++): ?><div class="f2"><div class="f"><?php if (!$i) echo '<span class="lbl">Umiejętność</span>'; ?><select name="um_n[]"><option value="">—</option><?php echo $um_opt; ?></select></div><div class="f"><?php if (!$i) echo '<span class="lbl">Poziom</span>'; ?><input type="number" name="um_l[]" value="0" min="0" max="5"></div></div><?php endfor; ?>
        <div class="f"><span class="lbl">Zalety (po przecinku)</span><input type="text" name="zalety" maxlength="500"></div>
        <div class="f"><span class="lbl">Wady (po przecinku)</span><input type="text" name="wady" maxlength="500"></div>
        <button class="pmk-btn ghost" type="submit">+ Dodaj NPC do Opowieści</button>
      </form>
    </section>
    <!-- ═══ UKRYTE ═══ -->
    <section class="pmk-pane" id="pmk-ukr">
      <p class="hint">Rzuty, których gracze nie widzą. Możesz je później ujawnić w Opowieści.</p>
      <?php foreach ($ukryte as $u): ?>
        <div class="hid"><b><?php echo $h($u['tytul']); ?></b><span><?php echo $h($u['data']); ?></span>
          <form method="POST"><input type="hidden" name="pm_akcja" value="ujawnij"><input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>"><button class="pmk-btn sm vio" type="submit">Ujawnij w Opowieści</button></form></div>
      <?php endforeach; if (!$ukryte) echo '<p class="hint">Brak ukrytych rzutów.</p>'; ?>
    </section>
    <?php endif; ?>
  </div>
</aside>
<script>
(function(){
const $=s=>document.querySelector(s),B=document.body,T=<?php echo json_encode($tab); ?>,C=<?php echo json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,UD=<?php echo json_encode($um_def, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,AN=<?php echo json_encode(array_map(fn($a) => $a['nazwa'], $UM_ATRYBUTY), JSON_UNESCAPED_UNICODE); ?>;
if(<?php echo $otwarty ? 'true' : 'false'; ?>||localStorage.getItem('pmk_open')==='1')B.classList.add('pmk-on');
$('#pmkTgl').onclick=()=>{B.classList.toggle('pmk-on');localStorage.setItem('pmk_open',B.classList.contains('pmk-on')?'1':'0')};
function tab(t){document.querySelectorAll('#pmkTabs button').forEach(b=>b.classList.toggle('on',b.dataset.t===t));document.querySelectorAll('.pmk-pane').forEach(p=>p.classList.toggle('on',p.id==='pmk-'+t))}
$('#pmkTabs').onclick=e=>{const b=e.target.closest('button');if(b)tab(b.dataset.t)};tab(T);
/* testy */
const R=$('#pmkRodz'),cel=$('#pmkCel'),um=$('#pmkUm'),inf=$('#pmkInfo');
function testy(){const r=R.value;document.querySelectorAll('#pmkTest [data-r]').forEach(el=>el.style.display=el.dataset.r.split(' ').includes(r)?'':'none');
 const k=cel.value;document.querySelectorAll('#pmkTest .cech[data-cel]').forEach(g=>g.style.display=g.dataset.cel===k?'':'none');
 const zd=$('#pmkZd');if(zd&&zd.dataset.k!==k){zd.dataset.k=k;const z=(C[k]||{}).zal||[],e=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));zd.innerHTML=z.length?z.map(n=>'<option value="'+e(n)+'">'+e(n)+'</option>').join(''):'<option value="">— brak Zdolności na karcie —</option>'}
 const zl=$('#pmkZl');if(r==='zloz')document.querySelectorAll('#pmkTest [data-zl-nowy]').forEach(e=>e.style.display=zl&&zl.value==='0'?'':'none');
 const c=C[k],n=um.value;if(r==='prosty'&&c&&!(c.um[n]>0)){const d=UD[n]||['I'];const ag=document.querySelector('#pmkTest [name=ag]').value;const a=ag==='d'&&d[1]?d[1]:d[0];
  inf.innerHTML='<b>'+c.n+'</b> nie ma Umiejętności „'+n+'” — rzut idzie na sam Atrybut ('+AN[a]+' '+c.at[a]+'). Podstawowe kompetencje (np. prosta jazda konno, podstawowe kroki tańca) w typowych warunkach udają się automatycznie; rzucaj, gdy sytuacja wykracza poza typową.';inf.style.display=''}
 else inf.style.display='none'}
$('#pmkTest').addEventListener('change',testy);testy();
const SYT={wiedza:[/histor|wiedz|nauk|antykw|teolog/i,0,'— test wiedzy: czy rozpoznaje, co ma przed sobą',/wykszta|erudyt|oczytan/i,1],
 obs:[/spostrz|obserw|percep|czujn/i,0,'— test obserwacji: czy zauważa istotny szczegół',/sokol|czujn|krótkow|ślep|nocny/i,1],
 obrona:[/psycholog|empat|opanow|woli|perswaz/i,0,'— test obronny przed wywieraniem wpływu',/uparty|naiwn|odporn|żelazn/i,1],
 zamek:[/zamk|włam|wytrych/i,0,'otwiera zamek',/zręczn|złota rączka|niezdarn/i],
 kon:[/jeźdz|jazd|konn/i,-10,'utrzymuje się w siodle w galopie',/nog|proteza|jeźdź|jeździec/i],
 bal:[/taniec|tańc/i,0,'tańczy na balu — czy się nie potknie i nikomu nie nadepnie na stopę',/niezdarn|nog|proteza|gracj|refleks/i],
 wsp:[/wspin/i,-10,'wspina się po ścianie',/wysok|nog|proteza|refleks/i],
 auto:[/pojazd|prowadz|kierow/i,-10,'ucieka autem przez Manhattan',/refleks|nocny|nałóg/i]};
$('#pmkSyt').onclick=e=>{const b=e.target.closest('button');if(!b)return;const S=SYT[b.dataset.s];document.querySelectorAll('#pmkSyt button').forEach(x=>x.classList.toggle('on',x===b));
 R.value='prosty';const o=[...um.options].find(o=>S[0].test(o.value));if(o)um.value=o.value;
 document.querySelectorAll('#pmkRyz input').forEach(i=>i.checked=+i.value===S[1]);const pas=$('#pmkPas');if(pas)pas.checked=!!S[4];
 const g=document.querySelector('#pmkTest .cech[data-cel="'+cel.value+'"]');if(g)g.querySelectorAll('input').forEach(i=>i.checked=S[3].test(i.value));
 $('#pmkSd').value=((C[cel.value]||{}).n||'')+' '+S[2];testy()};
/* walka */
const W=$('#pmkWalka');if(W){const wc=()=>{const a=W.querySelector('[name=kto]').value,t=W.querySelector('[name=cel]').value;W.querySelectorAll('[data-wa]').forEach(g=>g.style.display=g.dataset.wa===a?'':'none');W.querySelectorAll('[data-wt]').forEach(g=>g.style.display=g.dataset.wt===t?'':'none')};
 const akc=()=>{wc();const a=W.querySelector('[name=pm_akcja]:checked').value;W.querySelectorAll('[data-a]').forEach(el=>el.style.display=el.dataset.a===a?'':'none');W.querySelector('[data-a-lbl]').textContent=a==='atak'?'Atakujący':a==='odp'?'Kto odpoczywa':'Kto spada'};
 W.addEventListener('change',e=>{if(e.target.name==='pm_akcja')akc();if(e.target.id==='pmkKto'){const o=e.target.selectedOptions[0];$('#pmkBron').value=o.dataset.bron;const c=W.querySelector('[name=cel]');[...c.options].forEach(x=>x.hidden=x.value===o.value);if(c.value===o.value){const f=[...c.options].find(x=>!x.hidden);if(f)c.value=f.value}}wc()});akc()}
})();
</script>
