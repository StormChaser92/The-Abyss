<?php
require_once "db.php";
require_once __DIR__ . '/../includes/katedra.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* KATEDRA v2 — śluby z ceną ustalaną przez Proboszcza, rozwody, karencja, Księga (oś czasu). Logika: includes/katedra.php */
$kom = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zid = (int)($_POST['kt_id'] ?? 0);
    $cena = (int)preg_replace('/\D/', '', (string)($_POST['kt_cena'] ?? '0'));
    $uw = (string)($_POST['kt_uwaga'] ?? '');
    if (isset($_POST['kt_oswiadcz']))      $kom = kt_oswiadcz($polaczenie, $id_gracza, (string)($_POST['kt_login'] ?? ''), (string)($_POST['kt_slowa'] ?? ''));
    elseif (isset($_POST['kt_rozwod']))    $kom = kt_rozwod_proponuj($polaczenie, $id_gracza);
    elseif (isset($_POST['kt_tak']))       $kom = kt_odpowiedz($polaczenie, $id_gracza, $zid, true);
    elseif (isset($_POST['kt_nie']))       $kom = kt_odpowiedz($polaczenie, $id_gracza, $zid, false);
    elseif (isset($_POST['kt_wycofaj']))   $kom = kt_wycofaj($polaczenie, $id_gracza, $zid);
    elseif (isset($_POST['kt_zaplac']))    $kom = kt_zaplac_zgloszenie($polaczenie, $id_gracza, $zid);
    elseif (isset($_POST['kt_zatwierdz'])) $kom = kt_decyzja($polaczenie, $id_gracza, $zid, true, $cena, $uw);
    elseif (isset($_POST['kt_odmow']))     $kom = kt_decyzja($polaczenie, $id_gracza, $zid, false, 0, $uw);
}

$pola_a = nk_pola('a', 'a_'); $pola_b = nk_pola('b', 'b_');
$gn = fn(int $id) => db_wiersz($polaczenie, 'SELECT ' . nk_pola() . ' FROM gracze WHERE id = ?', [$id]);
$ja = $gn($id_gracza);
$gotowka = (int)(db_wiersz($polaczenie, 'SELECT gotowka FROM gracze WHERE id = ?', [$id_gracza])['gotowka'] ?? 0);
$proboszcz = kt_proboszcz($polaczenie, $id_gracza);
$mal = kt_malzenstwo($polaczenie, $id_gracza);
$zg  = kt_w_toku($polaczenie, $id_gracza);
$karencja = !$mal ? kt_karencja($polaczenie, $id_gracza) : null;
if ($mal) {
    $partner = $gn((int)$mal['malzonek_1_id'] === $id_gracza ? (int)$mal['malzonek_2_id'] : (int)$mal['malzonek_1_id']);
    $udzielil = !empty($mal['proboszcz_id']) ? $gn((int)$mal['proboszcz_id']) : null;
}
if ($zg) {
    $drugi = $gn((int)$zg['od_id'] === $id_gracza ? (int)$zg['do_id'] : (int)$zg['od_id']);
    $wycenil = !empty($zg['proboszcz_id']) ? $gn((int)$zg['proboszcz_id']) : null;
    $moje_od = (int)$zg['od_id'] === $id_gracza;
}
if ($karencja) $ostatni = db_wiersz($polaczenie, "SELECT m.data_rozwodu, IF(m.malzonek_1_id = ?, m.malzonek_2_id, m.malzonek_1_id) ex FROM malzenstwa m
    WHERE (m.malzonek_1_id = ? OR m.malzonek_2_id = ?) AND m.status = 'rozwiazane' ORDER BY m.data_rozwodu DESC LIMIT 1", [$id_gracza, $id_gracza, $id_gracza]);

$do_decyzji = $proboszcz ? db_wiersze($polaczenie, "SELECT z.*, $pola_a, $pola_b, m.data_slubu, m.cena AS cena_slubu FROM katedra_zgloszenia z
    JOIN gracze a ON a.id = z.od_id JOIN gracze b ON b.id = z.do_id LEFT JOIN malzenstwa m ON m.id = z.malzenstwo_id
    WHERE z.status = 'czeka_proboszcz' ORDER BY z.kiedy ASC") : [];

$KS_NA_STRONE = 20;
$ks_str = max(0, (int)($_GET['ks'] ?? 0));
$ksiega = db_wiersze($polaczenie, "SELECT * FROM (
      SELECT m.data_slubu AS kiedy, 'slub' AS typ, m.data_slubu, m.proboszcz_id, $pola_a, $pola_b FROM malzenstwa m JOIN gracze a ON a.id = m.malzonek_1_id JOIN gracze b ON b.id = m.malzonek_2_id
      UNION ALL
      SELECT m.data_rozwodu AS kiedy, 'rozwod' AS typ, m.data_slubu, m.proboszcz_id, $pola_a, $pola_b FROM malzenstwa m JOIN gracze a ON a.id = m.malzonek_1_id JOIN gracze b ON b.id = m.malzonek_2_id WHERE m.status = 'rozwiazane' AND m.data_rozwodu IS NOT NULL
    ) os ORDER BY kiedy DESC LIMIT " . ($KS_NA_STRONE + 1) . " OFFSET " . ($ks_str * $KS_NA_STRONE));
$ks_dalej = count($ksiega) > $KS_NA_STRONE;
$ksiega = array_slice($ksiega, 0, $KS_NA_STRONE);
$ks_proboszcz = [];
foreach ($ksiega as $k) if (!empty($k['proboszcz_id'])) $ks_proboszcz[(int)$k['proboszcz_id']] = null;
foreach (array_keys($ks_proboszcz) as $pid) $ks_proboszcz[$pid] = kt_login($polaczenie, $pid);

$proboszczowie = db_wiersze($polaczenie, 'SELECT ' . nk_pola() . ' FROM gracze WHERE is_proboszcz = 1 ORDER BY login');
$data = fn($d) => date('d.m.Y', strtotime($d));
$kroki = function (array $nazwy, int $teraz) {
    $h = '<div class="kt-kroki">';
    foreach ($nazwy as $i => $n) $h .= '<div class="' . ($i < $teraz ? 'ok' : ($i === $teraz ? 'teraz' : '')) . '">' . $n . '</div>';
    return $h . '</div>';
};
$K_SLUB = ['Oświadczyny', 'Proboszcz', 'Opłata', 'Ślub'];
$K_ROZW = ['Zgoda', 'Wycena', 'Opłata', 'Rozwód'];
?>
<style>
.kt{display:grid;gap:18px;--zl:#f2dc8c}
.kt-2{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:18px}
@media(max-width:900px){.kt-2{grid-template-columns:1fr}}
.kt-panel{background:rgba(18,10,18,.5);border:1px solid var(--border-soft);border-radius:2px;padding:18px;position:relative;min-width:0}
.kt-panel::before{content:'';position:absolute;top:0;left:0;width:28px;height:1px;background:var(--zl);box-shadow:0 0 6px var(--zl)}
.kt-panel h2{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.05em;letter-spacing:2px;text-transform:uppercase;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.kt-panel h2 .tag{font-family:'JetBrains Mono',monospace;font-size:.65em;color:var(--zl);letter-spacing:2px;font-weight:400;padding:2px 6px;border:1px solid rgba(242,220,140,.3)}
.kt-panel h2 .tag.r{color:var(--neon-red-hot);border-color:var(--border-mid)}
.kt p{color:var(--txt-dim);line-height:1.55}
.kt-lbl{display:block;font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:2px;color:var(--txt-mute);text-transform:uppercase;margin:0 0 6px}
.kt input[type=text],.kt input[type=number],.kt textarea{width:100%;background:rgba(0,0,0,.5);border:1px solid var(--border-soft);color:#fff;padding:10px 12px;font-family:'Rajdhani',sans-serif;font-size:1em;border-radius:1px}
.kt input[type=number]{font-family:'JetBrains Mono',monospace;padding-right:30px}
.kt textarea{min-height:74px;resize:vertical}
.kt input:focus,.kt textarea:focus{outline:none;border-color:var(--zl)!important;box-shadow:0 0 10px rgba(242,220,140,.2)}
.kt-pole{margin-bottom:14px;position:relative}
.kt-cena::after{content:'$';position:absolute;right:12px;bottom:11px;font-family:'JetBrains Mono',monospace;color:var(--txt-mute)}
.kt-pola{display:grid;grid-template-columns:minmax(0,200px) minmax(0,1fr);gap:12px;margin-top:12px}
@media(max-width:600px){.kt-pola{grid-template-columns:1fr}}
.kt-akcje{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.kt-btn{padding:10px 18px;background:rgba(242,220,140,.08);border:1px solid rgba(242,220,140,.45);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:.85em;cursor:pointer;border-radius:1px;transition:all .25s}
.kt-btn:hover{background:var(--zl);color:#140a08;box-shadow:0 0 18px rgba(242,220,140,.55)}
.kt-btn.ghost{background:transparent;border-color:var(--border-soft);color:var(--txt-dim)}
.kt-btn.ghost:hover{color:#fff;border-color:var(--neon-red);background:rgba(255,23,68,.1);box-shadow:none}
.kt-btn.red{background:rgba(255,23,68,.08);border-color:var(--border-mid)}
.kt-btn.red:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 18px rgba(255,23,68,.7)}
.kt-btn:disabled{opacity:.4;cursor:not-allowed;box-shadow:none}
.kt-kom{padding:12px 14px;border:1px solid;font-family:'JetBrains Mono',monospace;font-size:.85em}
.kt-kom.ok{color:var(--neon-green);border-color:rgba(90,255,154,.35);background:rgba(90,255,154,.05)}
.kt-kom.blad{color:var(--neon-red-hot);border-color:var(--border-mid);background:rgba(255,23,68,.06)}
.kt-para{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:1.1em;min-width:0}
.kt-para .i{color:var(--zl);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.3em}
.kt-slowa{margin:12px 0;padding:12px 14px;border-left:2px solid rgba(242,220,140,.5);background:rgba(242,220,140,.04);color:var(--txt-main);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.15em;line-height:1.45}
.kt-stan{font-family:'JetBrains Mono',monospace;font-size:.75em;letter-spacing:2px;color:var(--zl);text-transform:uppercase;margin-bottom:10px}
.kt-stan.r{color:var(--neon-red-hot)}
.kt-meta{font-family:'JetBrains Mono',monospace;font-size:.75em;color:var(--txt-mute);margin-top:8px;line-height:1.6}
.kt-kroki{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px;margin-bottom:16px}
.kt-kroki div{padding:8px 4px;text-align:center;font-family:'JetBrains Mono',monospace;font-size:.65em;letter-spacing:1px;text-transform:uppercase;border-top:2px solid var(--border-soft);color:var(--txt-mute);overflow:hidden;text-overflow:ellipsis}
.kt-kroki div.ok{border-top-color:var(--neon-green);color:var(--neon-green)}
.kt-kroki div.teraz{border-top-color:var(--zl);color:var(--zl)}
.kt-kwota{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px;border:1px solid rgba(242,220,140,.3);background:rgba(242,220,140,.04);margin:12px 0;flex-wrap:wrap}
.kt-kwota b{font-family:'JetBrains Mono',monospace;font-size:1.5em;color:var(--zl);font-weight:500;text-shadow:0 0 10px rgba(242,220,140,.4)}
.kt-kwota .kt-lbl{margin-bottom:4px}
.kt-lista{display:grid;gap:10px}
.kt-wpis{padding:14px;border:1px solid var(--border-soft);background:rgba(0,0,0,.3)}
.kt-info{padding:10px 12px;border:1px solid rgba(255,122,61,.35);background:rgba(255,122,61,.05);color:var(--neon-ember);line-height:1.45}
.kt-roz summary{list-style:none;cursor:pointer;display:inline-block;margin-top:18px;font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:2px;color:var(--neon-red-hot);text-transform:uppercase}
.kt-roz summary::-webkit-details-marker{display:none}
.kt-roz summary::before{content:'▸ '}.kt-roz[open] summary::before{content:'▾ '}
.kt-roz p{margin:10px 0 12px}
.kt-pusto{color:var(--txt-mute);font-style:italic}
.kt-os{position:relative;padding-left:26px}
.kt-os::before{content:'';position:absolute;left:8px;top:6px;bottom:6px;width:1px;background:linear-gradient(to bottom,var(--zl),rgba(242,220,140,.1))}
.kt-os-rok{font-family:'Oswald',sans-serif;letter-spacing:3px;color:#fff;margin:6px 0 2px -26px;font-size:.9em}
.kt-os-w{position:relative;padding:10px 0 14px;display:grid;gap:4px}
.kt-os-w::before{content:'';position:absolute;left:-22px;top:15px;width:9px;height:9px;border-radius:50%;background:#0a0508;border:1.5px solid var(--zl);box-shadow:0 0 8px rgba(242,220,140,.6)}
.kt-os-w.roz::before{border-color:var(--neon-red-hot);box-shadow:0 0 8px rgba(255,23,68,.6)}
.kt-os-w time{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1px;color:var(--txt-mute)}
.kt-os-w .typ{font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:.75em;color:var(--zl)}
.kt-os-w.roz .typ{color:var(--neon-red-hot)}
.kt-os-w.roz .kt-para{opacity:.75}
.kt-os-w .kt-meta{margin-top:0}
.kt-strony{display:flex;gap:10px;margin-top:6px}
.kt-strony a{text-decoration:none}
</style>

<div class="kt">
  <div class="page-head">
    <div class="eyebrow">// KATEDRA</div>
    <h1>Katedra</h1>
    <p class="lead">Stare mury pośród neonów. Tu dwoje obywateli może zgłosić chęć zawarcia małżeństwa. Gdy obie strony powiedzą „tak”, Proboszcz wycenia ceremonię, a po opłacie udziela ślubu.</p>
  </div>

  <?php if ($kom): ?><div class="kt-kom <?php echo $kom[0] ? 'ok' : 'blad'; ?>"><?php echo $kom[0] ? '✓ ' : '⚠ '; echo $kom[1]; ?></div><?php endif; ?>

  <?php if ($proboszcz): ?>
  <section class="kt-panel">
    <h2>Prośby do Proboszcza <span class="tag">PROBOSZCZ · <?php echo count($do_decyzji); ?></span></h2>
    <?php if (!$do_decyzji): ?><p class="kt-pusto">Nikt nie czeka na Twoją decyzję.</p><?php else: ?>
    <div class="kt-lista">
      <?php foreach ($do_decyzji as $z): $wlasny = (int)$z['od_id'] === $id_gracza || (int)$z['do_id'] === $id_gracza; $slub = $z['typ'] === 'slub'; $zid = (int)$z['id']; ?>
      <form class="kt-wpis" method="POST">
        <input type="hidden" name="kt_id" value="<?php echo $zid; ?>">
        <div class="kt-stan<?php echo $slub ? '' : ' r'; ?>"><?php echo $slub ? 'Ślub · obie strony powiedziały „tak”' : 'Rozwód · oboje się zgodzili'; ?></div>
        <div class="kt-para"><?php echo nk_html(nk_wiersz($z, 'a_')); ?><span class="i">&amp;</span><?php echo nk_html(nk_wiersz($z, 'b_')); ?></div>
        <?php if ($slub && $z['slowa'] !== ''): ?><div class="kt-slowa"><?php echo nk_h($z['slowa']); ?></div><?php endif; ?>
        <div class="kt-meta"><?php echo $slub ? 'Zgłoszenie z ' . $data($z['kiedy']) : 'Małżeństwo od ' . $data($z['data_slubu']) . ' · ślub kosztował ' . kt_fmt((int)$z['cena_slubu']); ?></div>
        <?php if ($wlasny): ?><p class="kt-meta">Dotyczy Ciebie. Decyzję podejmie inny Proboszcz.</p><?php else: ?>
        <div class="kt-pola">
          <div class="kt-pole kt-cena"><label class="kt-lbl" for="kt-c-<?php echo $zid; ?>"><?php echo $slub ? 'Cena ceremonii' : 'Opłata za rozwód'; ?></label><input type="number" id="kt-c-<?php echo $zid; ?>" name="kt_cena" min="0" step="100" value="0" required></div>
          <?php if ($slub): ?><div class="kt-pole"><label class="kt-lbl" for="kt-uw-<?php echo $zid; ?>">Słowo od Proboszcza (przy odmowie wymagane)</label><input type="text" id="kt-uw-<?php echo $zid; ?>" name="kt_uwaga" maxlength="400"></div><?php endif; ?>
        </div>
        <div class="kt-akcje">
          <?php if ($slub): ?><button class="kt-btn" type="submit" name="kt_zatwierdz" value="1">Zatwierdź i wyceń</button><button class="kt-btn ghost" type="submit" name="kt_odmow" value="1" formnovalidate>Odmów</button>
          <?php else: ?><button class="kt-btn red" type="submit" name="kt_zatwierdz" value="1">Wyceń rozwód</button><?php endif; ?>
        </div>
        <div class="kt-meta"><?php echo $slub ? 'Po zatwierdzeniu ktoś z pary płaci podaną kwotę i dopiero wtedy zostaje zawarte małżeństwo. Pieniądze trafiają do Katedry. Cena 0 $ = ślub od razu.'
                                                 : 'Rozwodu nie można odmówić. Ustalasz tylko opłatę, którą para dzieli pół na pół. 0 $ = rozwód od razu.'; ?></div>
        <?php endif; ?>
      </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <div class="kt-2">
    <section class="kt-panel">
    <?php if ($zg && $zg['typ'] === 'rozwod'):
        $st = $zg['status']; ?>
      <?php if ($st === 'oswiadczyny' && !$moje_od): ?>
        <h2>Propozycja rozwodu <span class="tag r">DLA CIEBIE</span></h2>
        <?php echo $kroki($K_ROZW, 0); ?>
        <div class="kt-para"><?php echo nk_html($drugi); ?> <span style="color:var(--txt-dim)">proponuje rozwód.</span></div>
        <form method="POST" class="kt-akcje" style="margin-top:14px"><input type="hidden" name="kt_id" value="<?php echo (int)$zg['id']; ?>"><button class="kt-btn red" type="submit" name="kt_tak" value="1">Zgadzam się</button><button class="kt-btn ghost" type="submit" name="kt_nie" value="1">Nie zgadzam się</button></form>
      <?php elseif ($st === 'do_zaplaty'):
          $moja_kol = $moje_od ? 'zaplacil_od' : 'zaplacil_do'; $jego_kol = $moje_od ? 'zaplacil_do' : 'zaplacil_od'; $moja = kt_polowa($zg, $moje_od); ?>
        <h2>Rozwód wyceniony <span class="tag r">DO ZAPŁATY</span></h2>
        <?php echo $kroki($K_ROZW, 2); ?>
        <div class="kt-kwota"><div><span class="kt-lbl">Twoja połowa z <?php echo kt_fmt((int)$zg['cena']); ?></span><span style="color:var(--txt-dim)"><?php echo nk_h($drugi['login']); ?>: <?php echo (int)$zg[$jego_kol] ? '<span style="color:var(--neon-green)">✓ zapłacone</span>' : 'jeszcze nie zapłacił/a'; ?></span></div><b><?php echo kt_fmt($moja); ?></b></div>
        <?php if ((int)$zg[$moja_kol]): ?><p>Swoją połowę już zapłaciłaś/eś. Rozwód zostanie orzeczony, gdy <?php echo nk_h($drugi['login']); ?> zapłaci swoją.</p>
        <?php else: ?><form method="POST" class="kt-akcje"><input type="hidden" name="kt_id" value="<?php echo (int)$zg['id']; ?>"><button class="kt-btn red" type="submit" name="kt_zaplac" value="1"<?php echo $gotowka < $moja ? ' disabled' : ''; ?>>Zapłać swoją połowę</button><?php if (!(int)$zg[$jego_kol]): ?><button class="kt-btn ghost" type="submit" name="kt_wycofaj" value="1">Wycofaj</button><?php endif; ?></form>
        <div class="kt-meta">Masz przy sobie <?php echo kt_fmt($gotowka); ?>. Wycenił <?php echo $wycenil ? nk_html($wycenil) : '?'; ?>.</div><?php endif; ?>
      <?php else: ?>
        <h2>Wniosek o rozwód <span class="tag r">W TOKU</span></h2>
        <?php echo $kroki($K_ROZW, $st === 'oswiadczyny' ? 0 : 1); ?>
        <div class="kt-stan r"><?php echo $st === 'oswiadczyny' ? 'Czeka na zgodę drugiej osoby' : 'Czeka na wycenę Proboszcza'; ?></div>
        <div class="kt-para"><?php echo nk_html($drugi); ?></div>
        <form method="POST" style="margin-top:14px"><input type="hidden" name="kt_id" value="<?php echo (int)$zg['id']; ?>"><button class="kt-btn ghost" type="submit" name="kt_wycofaj" value="1">Wycofaj wniosek</button></form>
      <?php endif; ?>

    <?php elseif ($mal): ?>
      <h2>Twoje małżeństwo <span class="tag">ZAWARTE</span></h2>
      <div class="kt-para"><?php echo nk_html($ja); ?><span class="i">&amp;</span><?php echo $partner ? nk_html($partner) : '?'; ?></div>
      <div class="kt-meta">Ślub <?php echo $data($mal['data_slubu']); ?><?php if (!empty($udzielil)): ?> · udzielił <?php echo nk_html($udzielil); ?><?php endif; ?><?php if ((int)($mal['cena'] ?? 0) > 0) echo ' · ' . kt_fmt((int)$mal['cena']); ?></div>
      <details class="kt-roz"><summary>Rozwód</summary>
        <p>Rozwód wymaga zgody obojga. Proboszcz ustala opłatę, którą dzielicie pół na pół. Przez <?php echo KT_KARENCJA_DNI; ?> dni po rozwodzie nie można wziąć nowego ślubu. Rozwód trafi do Księgi i do Plotek na obu profilach.</p>
        <form method="POST" onsubmit="return confirm('Zaproponować rozwód?')"><button class="kt-btn red" type="submit" name="kt_rozwod" value="1">Zaproponuj rozwód</button></form>
      </details>

    <?php elseif ($zg && (int)$zg['do_id'] === $id_gracza && $zg['status'] === 'oswiadczyny'): ?>
      <h2>Oświadczyny <span class="tag">DLA CIEBIE</span></h2>
      <?php echo $kroki($K_SLUB, 0); ?>
      <div class="kt-stan">Prosi Cię o rękę</div>
      <div class="kt-para"><?php echo nk_html($drugi); ?></div>
      <?php if ($zg['slowa'] !== ''): ?><div class="kt-slowa"><?php echo nk_h($zg['slowa']); ?></div><?php endif; ?>
      <form method="POST" class="kt-akcje" style="margin-top:12px"><input type="hidden" name="kt_id" value="<?php echo (int)$zg['id']; ?>"><button class="kt-btn" type="submit" name="kt_tak" value="1">Tak</button><button class="kt-btn ghost" type="submit" name="kt_nie" value="1">Nie</button></form>

    <?php elseif ($zg && $zg['status'] === 'do_zaplaty'): ?>
      <h2>Ceremonia zatwierdzona <span class="tag">DO ZAPŁATY</span></h2>
      <?php echo $kroki($K_SLUB, 2); ?>
      <div class="kt-para"><?php echo nk_html($drugi); ?></div>
      <div class="kt-kwota"><div><span class="kt-lbl">Cenę ustalił/a</span><?php echo $wycenil ? nk_html($wycenil) : '?'; ?></div><b><?php echo kt_fmt((int)$zg['cena']); ?></b></div>
      <?php if ($zg['uwaga'] !== ''): ?><div class="kt-slowa" style="margin-top:0"><?php echo nk_h($zg['uwaga']); ?></div><?php endif; ?>
      <form method="POST" class="kt-akcje"><input type="hidden" name="kt_id" value="<?php echo (int)$zg['id']; ?>"><button class="kt-btn" type="submit" name="kt_zaplac" value="1"<?php echo $gotowka < (int)$zg['cena'] ? ' disabled' : ''; ?>>Zapłać i weź ślub</button><button class="kt-btn ghost" type="submit" name="kt_wycofaj" value="1">Wycofaj</button></form>
      <div class="kt-meta">Zapłacić może każde z was. Gdy jedno zapłaci, ślub zostaje zawarty od razu. Masz przy sobie <?php echo kt_fmt($gotowka); ?>.</div>

    <?php elseif ($zg): ?>
      <h2>Zgłoszenie ślubu <span class="tag">W TOKU</span></h2>
      <?php echo $kroki($K_SLUB, $zg['status'] === 'oswiadczyny' ? 0 : 1); ?>
      <div class="kt-stan"><?php echo $zg['status'] === 'oswiadczyny' ? 'Czeka na odpowiedź' : 'Czeka na decyzję i wycenę Proboszcza'; ?></div>
      <div class="kt-para"><?php echo nk_html($drugi); ?></div>
      <?php if ($zg['slowa'] !== ''): ?><div class="kt-slowa"><?php echo nk_h($zg['slowa']); ?></div><?php endif; ?>
      <div class="kt-meta">Zgłoszono <?php echo $data($zg['kiedy']); ?></div>
      <form method="POST" style="margin-top:14px" onsubmit="return confirm('Wycofać zgłoszenie?')"><input type="hidden" name="kt_id" value="<?php echo (int)$zg['id']; ?>"><button class="kt-btn ghost" type="submit" name="kt_wycofaj" value="1">Wycofaj zgłoszenie</button></form>

    <?php elseif ($karencja): ?>
      <h2>Po rozwodzie <span class="tag r">KARENCJA</span></h2>
      <?php $zost = $karencja - time(); ?>
      <div class="kt-info">Nowy ślub możliwy od <b><?php echo date('d.m.Y, H:i', $karencja); ?></b>. Zostało <?php echo floor($zost / 86400); ?> dni <?php echo floor(($zost % 86400) / 3600); ?> godz.</div>
      <?php if (!empty($ostatni)): ?><div class="kt-meta">Rozwód z <?php echo nk_h(kt_login($polaczenie, (int)$ostatni['ex'])); ?> · <?php echo $data($ostatni['data_rozwodu']); ?></div><?php endif; ?>

    <?php else: ?>
      <h2>Zgłoś chęć ślubu <span class="tag">OŚWIADCZYNY</span></h2>
      <form method="POST">
        <div class="kt-pole"><label class="kt-lbl" for="kt-login">Nick przyszłej żony lub męża</label><input type="text" id="kt-login" name="kt_login" maxlength="40" required autocomplete="off"></div>
        <div class="kt-pole"><label class="kt-lbl" for="kt-slowa">Słowa oświadczyn (opcjonalnie)</label><textarea id="kt-slowa" name="kt_slowa" maxlength="400"></textarea></div>
        <button class="kt-btn" type="submit" name="kt_oswiadcz" value="1">Złóż oświadczyny</button>
      </form>
      <p class="kt-meta" style="margin-top:12px">Druga osoba dostanie powiadomienie. Po jej zgodzie Proboszcz wyceni ceremonię.</p>
    <?php endif; ?>
    </section>

    <section class="kt-panel">
      <h2>Proboszczowie <span class="tag">KATEDRA</span></h2>
      <?php if ($proboszczowie): ?><div class="kt-lista" style="gap:6px"><?php foreach ($proboszczowie as $p) echo '<div>' . nk_html($p) . '</div>'; ?></div>
      <?php else: ?><p class="kt-pusto">Katedra nie ma teraz Proboszcza. Zgłoszenia poczekają, aż Administrator go wyznaczy.</p><?php endif; ?>
    </section>
  </div>

  <section class="kt-panel">
    <h2>Księga ślubów <span class="tag">OŚ CZASU</span></h2>
    <?php if (!$ksiega): ?><p class="kt-pusto">Księga jest jeszcze pusta.</p><?php else: ?>
    <div class="kt-os">
      <?php $rok = null; foreach ($ksiega as $k): $r = date('Y', strtotime($k['kiedy'])); $roz = $k['typ'] === 'rozwod';
        if ($r !== $rok) { $rok = $r; echo "<div class=\"kt-os-rok\">$r</div>"; } ?>
      <div class="kt-os-w<?php echo $roz ? ' roz' : ''; ?>">
        <time><?php echo $data($k['kiedy']); ?></time>
        <span class="typ"><?php echo $roz ? 'Rozwód' : 'Ślub'; ?></span>
        <div class="kt-para"><?php echo nk_html(nk_wiersz($k, 'a_')); ?><span class="i">&amp;</span><?php echo nk_html(nk_wiersz($k, 'b_')); ?></div>
        <div class="kt-meta"><?php
          if ($roz) { $d = max(1, (int)floor((strtotime($k['kiedy']) - strtotime($k['data_slubu'])) / 86400)); echo "Po $d " . ($d === 1 ? 'dniu' : 'dniach') . ' małżeństwa'; }
          elseif (!empty($k['proboszcz_id'])) echo 'Udzielił ' . nk_h($ks_proboszcz[(int)$k['proboszcz_id']] ?? '?');
        ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($ks_str > 0 || $ks_dalej): ?><div class="kt-strony">
      <?php if ($ks_str > 0): ?><a class="kt-btn ghost" href="game.php?page=katedra&amp;ks=<?php echo $ks_str - 1; ?>">← Nowsze</a><?php endif; ?>
      <?php if ($ks_dalej): ?><a class="kt-btn ghost" href="game.php?page=katedra&amp;ks=<?php echo $ks_str + 1; ?>">Starsze wpisy →</a><?php endif; ?>
    </div><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
