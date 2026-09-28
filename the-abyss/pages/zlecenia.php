<?php
/* the-abyss/pages/zlecenia.php — Tablica Zleceń: kontrakty od stałych zleceniodawców, skoki, nagrody za głowę, wieści.
   Logika: includes/zlecenia_logika.php (+ zlecenia_skoki.php). Treść: config/zlecenia_ny|la|pl.php.
   Zamawianie u Inżyniera przeniesione do Manufaktury (pages/warsztat_zamow.php). */
require_once "db.php";
require_once __DIR__ . '/../includes/zlecenia_logika.php';

$id_gracza = (int)$_SESSION['id_gracza'];
$tab = in_array($_GET['t'] ?? '', ['kontrakty', 'skoki', 'glowy', 'wiesci'], true) ? $_GET['t'] : 'kontrakty';
$npc_f = preg_replace('/[^a-z]/', '', (string)($_GET['npc'] ?? ''));
$g = zl_gracz($polaczenie, $id_gracza);
$region = zl_region($g['obecne_miasto'] ?? null);

/* ── akcje (POST → komunikat w sesji → powrót) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zl'])) {
    $a = (string)$_POST['zl']; $w = [false, 'Nieznana akcja.'];
    if ($region || in_array($a, ['glowa', 'lapowka', 'przyczaj'], true)) {
        $r = $region ?? 'NY';
        switch ($a) {
            case 'kontrakt':    $w = zl_kontrakt_przyjmij($polaczenie, $g, $r, (string)($_POST['kod'] ?? ''), (int)($_POST['ryz'] ?? 1)); break;
            case 'rozlicz':     $w = zl_kontrakt_rozlicz($polaczenie, $g, $r, (int)($_POST['id'] ?? 0)); break;
            case 'skok_start':  $w = zl_skok_start($polaczenie, $g, $r, (string)($_POST['kod'] ?? '')); break;
            case 'skok_wybor':  $w = zl_skok_wybierz($polaczenie, $g, $r, (int)($_POST['op'] ?? -1)); break;
            case 'skok_wynik':  $w = zl_skok_wynik($polaczenie, $g, $r); break;
            case 'skok_porzuc': $w = zl_skok_porzuc($polaczenie, $g, $r); break;
            case 'lapowka':     $w = zl_lapowka($polaczenie, $g); break;
            case 'przyczaj':    $w = zl_przyczaj($polaczenie, $g); break;
            case 'adwokat':     $w = zl_adwokat($polaczenie, $g, $r, (string)($_POST['npc'] ?? '')); break;
            case 'glowa':       $w = zl_glowa_wystaw($polaczenie, $g, $r, (string)($_POST['login'] ?? ''), (int)($_POST['kwota'] ?? 0)); break;
        }
    }
    $_SESSION['zl_msg'] = $w;
    header('Location: game.php?page=zlecenia&t=' . $tab . ($npc_f ? "&npc=$npc_f" : '')); exit;
}
$msg = $_SESSION['zl_msg'] ?? null; unset($_SESSION['zl_msg']);

zl_glowy_wygas($polaczenie);
$dane = $region ? zl_dane($region) : ['npc' => [], 'kontrakty' => [], 'skoki' => []];
$rep = zl_rep($polaczenie, $id_gracza);
[$uz, $lim] = zl_limit($polaczenie, $g);
$ob = (int)$g['zl_oblawa'];
$blokada = zl_blokada($polaczenie, $id_gracza);
$wtoku = db_wiersz($polaczenie, "SELECT * FROM zl_dziennik WHERE gracz_id = ? AND rodzaj = 'kontrakt' AND rozliczone = 0 ORDER BY id DESC LIMIT 1", [$id_gracza]);
$skok = zl_skok_aktywny($polaczenie, $id_gracza);
$adwokaci = array_filter($dane['npc'], fn($n, $id) => ($n['usluga'] ?? '') === 'adwokat' && zl_tier($rep[$id] ?? 0) >= 3, ARRAY_FILTER_USE_BOTH);

$repHtml = function (int $pkt) { $t = zl_tier($pkt); $o = '<div class="zl-rep">'; for ($i = 1; $i <= 4; $i++) $o .= '<i class="' . ($t >= $i ? 'on' : '') . '"></i>'; return $o . '</div>'; };
$ile = fn(int $n) => ($n > 0 ? '+' : '') . $n;
$czas = function (?string $do) { $t = $do ? max(0, strtotime($do) - time()) : 0; return '<span class="zl-czas" data-do="' . ($do ? strtotime($do) : 0) . '">' . sprintf('%02d:%02d', intdiv($t, 60), $t % 60) . '</span>'; };
?>
<link rel="stylesheet" href="css/zlecenia.css">
<div class="zl">

<div class="zl-head">
  <div class="eb">// <?php echo zl_h($region ? ZL_REGIONY[$region] : ($g['obecne_miasto'] ?? '')); ?> · robota bez ogłoszeń</div>
  <h1>Tablica Zleceń</h1>
  <p>Kontrakty od ludzi, którzy pamiętają, jak dla nich pracowałeś. Skoki w kilku etapach. Nagrody za głowę. Im głośniej działasz, tym bliżej jest policja.</p>
</div>

<?php if ($msg): ?><div class="g zl-msg <?php echo $msg[0] ? 'ok' : 'zle'; ?>"><?php echo $msg[1]; ?></div><?php endif; ?>

<div class="g zl-bar">
  <div><span class="ttl">Zlecenia dziś</span><b class="v"><?php echo "$uz / $lim"; ?></b><small><?php echo empty($g['is_premium']) ? 'Premium: ' . ZL_LIMIT_PREMIUM . ' dziennie' : 'Premium'; ?> · skok liczy się jako jedno</small></div>
  <div><span class="ttl">Obława</span>
    <div class="zl-gw"><?php for ($i = 0; $i < 5; $i++) echo '<i class="' . ($i < $ob ? 'on' : '') . '"></i>'; ?></div>
    <small><b style="color:#fff"><?php echo ZL_OBLAWA[$ob]; ?></b>. <?php echo ZL_OBLAWA_SKUTEK[$ob]; ?><?php if ($ob) echo ' Spada o 1 co ' . ZL_SPADEK_H . ' h bez zleceń.'; ?></small>
    <?php if ($ob && !$blokada): ?><div class="zl-ob-akcje">
      <form method="POST"><input type="hidden" name="zl" value="lapowka"><button class="btn maly" type="submit">Łapówka −1 · <?php echo zl_fmt(zl_lapowka_koszt($g)); ?> $</button></form>
      <form method="POST" onsubmit="return confirm('8 godzin bez zleceń i walki. Obława −2.')"><input type="hidden" name="zl" value="przyczaj"><button class="btn maly" type="submit">Przyczaj się −2</button></form>
      <?php foreach ($adwokaci as $aid => $a): ?><form method="POST"><input type="hidden" name="zl" value="adwokat"><input type="hidden" name="npc" value="<?php echo $aid; ?>"><button class="btn maly" type="submit"><?php echo zl_h($a['n']); ?> −2</button></form><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
  <div><span class="ttl">Status</span><b class="v" style="font-size:1.2em"><?php echo $blokada ? ($g['zl_blokada_typ'] === 'areszt' ? 'Areszt' : 'Przyczajony') : 'Na ulicy'; ?></b><small><?php echo $blokada ? zl_h($blokada) : zl_h($g['klasa'] ?: 'Bez klasy') . ' · poziom ' . (int)$g['poziom']; ?></small></div>
  <div><span class="ttl">Gotówka</span><b class="v" style="color:var(--ember)"><?php echo zl_fmt((int)$g['gotowka']); ?> $</b><small><a href="game.php?page=warsztat&widok=zamow">Zamów u Inżyniera →</a></small></div>
</div>

<?php if ($wtoku && $region): $wd = $dane['kontrakty'][$wtoku['kod']] ?? null; $gotowe = strtotime($wtoku['gotowe_o']) <= time(); ?>
<div class="g zl-wtoku">
  <div><span class="ttl">W toku</span><br><b><?php echo zl_h($wd['t'] ?? $wtoku['kod']); ?></b> · <?php echo zl_h($dane['npc'][$wtoku['npc']]['n'] ?? ''); ?></div>
  <?php if ($gotowe): ?><form method="POST" style="margin:0"><input type="hidden" name="zl" value="rozlicz"><input type="hidden" name="id" value="<?php echo (int)$wtoku['id']; ?>"><button class="btn duzy" type="submit">Odbierz wynik</button></form>
  <?php else: ?><div>Gotowe za <?php echo $czas($wtoku['gotowe_o']); ?></div><?php endif; ?>
</div>
<?php endif; ?>

<nav class="zl-tabs">
<?php foreach (['kontrakty' => 'Kontrakty', 'skoki' => 'Skoki' . ($skok ? ' · w toku' : ''), 'glowy' => 'Nagrody za głowę', 'wiesci' => 'Wieści'] as $k => $n) echo "<a href='game.php?page=zlecenia&t=$k' class='" . ($k === $tab ? 'on' : '') . "'>" . zl_h($n) . '</a>'; ?>
</nav>

<?php if (!$region && in_array($tab, ['kontrakty', 'skoki'], true)): ?>
  <div class="g zl-pusto">W tym mieście nikt cię nie zna i nikt ci niczego nie zleci. Zleceniodawcy czekają w Nowym Jorku, Los Angeles i Polsce. <a href="game.php?page=lotnisko">Port lotniczy →</a></div>

<?php elseif ($tab === 'kontrakty'): ?>
  <div class="zl-npcs">
  <?php foreach ($dane['npc'] as $nid => $n): $p = $rep[$nid] ?? 0; ?>
    <a class="g zl-npc <?php echo $npc_f === $nid ? 'on' : ''; ?>" href="game.php?page=zlecenia&t=kontrakty<?php echo $npc_f === $nid ? '' : "&npc=$nid"; ?>">
      <b><?php echo zl_h($n['n']); ?></b><?php echo $repHtml($p); ?><small><?php echo ZL_RANGI[zl_tier($p)] . " · $p pkt"; ?></small><small><?php echo zl_h($n['typ']); ?></small>
    </a>
  <?php endforeach; ?>
  </div>
  <?php if ($npc_f && isset($dane['npc'][$npc_f])): $n = $dane['npc'][$npc_f]; $p = $rep[$npc_f] ?? 0; $t = zl_tier($p); ?>
    <div class="g zl-npc-opis"><b><?php echo zl_h($n['n']); ?></b>: <?php echo zl_h($n['o']); ?>
      <?php if ($t < 4) echo ' Do rangi <b>' . ZL_RANGI[$t + 1] . '</b> brakuje ' . (ZL_PROGI[$t + 1] - $p) . ' pkt.'; ?>
      <?php if (!empty($n['wrog'])) echo ' Nie znosi: <b>' . zl_h($dane['npc'][$n['wrog']]['n'] ?? '') . '</b>.'; ?>
      <?php if (($n['usluga'] ?? '') === 'adwokat') echo ' Od rangi Prawa ręka obniża obławę o 2, raz na dobę.'; ?>
    </div>
  <?php endif; ?>
  <div class="zl-lista">
  <?php foreach ($dane['kontrakty'] as $kod => $d): if ($npc_f && $d['npc'] !== $npc_f) continue;
      $bl = $blokada ?: zl_kontrakt_blok($polaczenie, $g, $d, $rep, $kod);
      if ($bl && strncmp($bl, 'Od poziomu', 10) === 0 && (int)$d['lvl'] > (int)$g['poziom'] + 10) continue;
      $n = $dane['npc'][$d['npc']]; $sN = zl_szansa($g, $d, 1); $tier = zl_tier($rep[$d['npc']] ?? 0); ?>
    <details class="g zl-k <?php echo $bl ? 'blok' : ''; ?>">
      <summary>
        <div class="tt"><h3><?php echo zl_h($d['t']); ?></h3><?php echo !empty($d['gl']) ? '<span class="zl-tag">głośne</span>' : '<span class="zl-tag m">ciche</span>'; ?></div>
        <div class="kasa"><?php echo zl_fmt(zl_kasa($g, (int)$d['kasa'], $tier >= 4 ? 1.25 : 1)); ?> $<small><?php echo $sN['s']; ?>% szans</small></div>
        <div class="od"><?php echo zl_h($n['n']) . ' · ' . ((int)$d['cz'] ? (int)$d['cz'] . ' min' : 'od razu'); ?></div>
        <div class="zl-tagi tagi">
          <span class="zl-tag <?php echo (!$d['kl'] || $d['kl'] === $g['klasa']) ? 'gr' : 'm'; ?>"><?php echo zl_h($d['kl'] ?? 'Każda klasa'); ?></span>
          <span class="zl-tag c"><?php echo zl_h($d['um']); ?></span>
          <?php if (!empty($d['sprz'])) echo '<span class="zl-tag z">' . zl_h(zl_sprzet_opis($d['sprz'])) . '</span>'; ?>
          <?php if (!empty($d['mat'])) echo '<span class="zl-tag z">+ materiały</span>'; ?>
          <?php if ($bl) echo '<span class="zl-tag">' . zl_h($bl) . '</span>'; ?>
        </div>
      </summary>
      <div class="zl-k-body">
        <div style="display:flex;flex-direction:column;gap:12px">
          <p class="zl-brief">„<?php echo zl_h($d['b']); ?>”</p>
          <div class="zl-test"><?php foreach ($sN['czesci'] as [$et, $v]) if (strncmp($et, 'Ryzyko', 6) !== 0) echo '<div><span>' . zl_h($et) . '</span><b class="' . ($v > 0 && $et !== 'Trudność' ? 'plus' : ($v < 0 ? 'minus' : '')) . '">' . ($et === 'Trudność' ? $v : $ile($v)) . '</b></div>'; ?></div>
        </div>
        <form method="POST" class="zl-form-k">
          <input type="hidden" name="zl" value="kontrakt"><input type="hidden" name="kod" value="<?php echo zl_h($kod); ?>">
          <span class="ttl">Jak ryzykujesz</span>
          <div class="zl-ryz">
          <?php foreach (ZL_RYZYKO as $i => $r): $s = zl_szansa($g, $d, $i)['s']; ?>
            <label><input type="radio" name="ryz" value="<?php echo $i; ?>" <?php echo $i === 1 ? 'checked' : ''; ?>><b><?php echo $r['n']; ?></b><span class="s"><?php echo $s; ?>%</span>
              <small><?php echo zl_fmt(zl_kasa($g, (int)$d['kasa'], $r['m'] * ($tier >= 4 ? 1.25 : 1))); ?> $ · <?php echo zl_xp($g, (int)$d['xp'], $r['m']); ?> XP<br>obława <?php echo $i === 2 ? '+1 / +2' : '+0 / +1'; ?></small></label>
          <?php endforeach; ?>
          </div>
          <span class="ttl">Połowiczny wynik: połowa nagrody i obława +1. Porażka: bez nagrody, −1 reputacji<?php echo $ob >= 3 ? ', ryzyko aresztu' : ''; ?>.</span>
          <button class="btn duzy" type="submit" <?php echo ($bl || $wtoku || $uz >= $lim) ? 'disabled' : ''; ?>><?php echo $wtoku ? 'Najpierw dokończ zlecenie w toku' : ($uz >= $lim ? 'Limit na dziś' : 'Przyjmij zlecenie'); ?></button>
        </form>
      </div>
    </details>
  <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'skoki'): ?>
  <?php if ($skok && ($sd = $dane['skoki'][$skok['kod']] ?? null)): $nr = (int)$skok['etap']; $log = json_decode($skok['log'] ?: '[]', true) ?: []; $e = $sd['etapy'][$nr] ?? null; ?>
    <div class="zl-skok">
      <div class="g zl-etapy">
        <div class="ttl">Skok · <?php echo zl_h($dane['npc'][$sd['npc']]['n'] ?? ''); ?></div>
        <h3 style="margin:6px 0 8px"><?php echo zl_h($sd['t']); ?></h3>
        <?php foreach ($sd['etapy'] as $i => $et): $kl = $i < $nr ? ($log[$i]['w'] ?? 'sukces') : ($i === $nr ? 'teraz' : 'przysz'); ?>
          <div class="zl-et <?php echo $kl; ?>"><span class="kr"><?php echo $i < $nr ? ['sukces' => '✓', 'polowiczny' => '½', 'porazka' => '✕'][$kl] : $i + 1; ?></span>
            <div><b><?php echo zl_h($et['n']); ?></b><small><?php echo $i < $nr ? zl_h($log[$i]['o'] ?? '') : $et['min'] . ' min'; ?></small></div></div>
        <?php endforeach; ?>
      </div>
      <div class="g zl-scena">
        <?php if ($e): ?>
          <div class="ttl">Etap <?php echo $nr + 1; ?> z <?php echo count($sd['etapy']); ?> · <?php echo zl_h($e['n']); ?></div>
          <p><?php echo zl_h($e['o']); ?></p>
          <div class="zl-lup"><span>Łup: <b><?php echo zl_fmt(zl_kasa($g, (int)$sd['lup'], (int)$skok['lup'] / 100)); ?> $</b></span><?php if ((int)$skok['premia']) echo '<span>Premia z poprzedniego etapu: <b>+' . (int)$skok['premia'] . '</b></span>'; ?><?php if ((int)$skok['porazki']) echo '<span>Porażka z rzędu: <b>1 / 2</b></span>'; ?></div>
          <?php if ($skok['wybor'] !== null): $o = $e['op'][(int)$skok['wybor']]; $gotowe = strtotime($skok['etap_gotowy']) <= time(); ?>
            <div class="zl-op"><h3><?php echo zl_h($o['t']); ?></h3><p><?php echo zl_h($o['o']); ?></p><div class="sz"><?php echo (int)$skok['szansa']; ?>%<small>szansa</small></div>
              <?php if ($gotowe): ?><form method="POST"><input type="hidden" name="zl" value="skok_wynik"><button class="btn duzy" type="submit">Zobacz, jak poszło</button></form>
              <?php else: ?><div>Trwa jeszcze <?php echo $czas($skok['etap_gotowy']); ?></div><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="zl-opcje">
            <?php foreach ($e['op'] as $i => $o): $s = zl_szansa($g, $o, 1, (int)$skok['premia'])['s']; ?>
              <div class="zl-op">
                <div class="zl-tagi"><span class="zl-tag <?php echo (empty($o['kl']) || $o['kl'] === $g['klasa']) ? 'gr' : 'm'; ?>"><?php echo zl_h($o['kl'] ?? 'Każda klasa'); ?></span><span class="zl-tag c"><?php echo zl_h($o['um']); ?></span>
                  <?php if (!empty($o['sprz'])) echo '<span class="zl-tag z">' . zl_h(zl_sprzet_opis($o['sprz'])) . '</span>'; ?><?php if (!empty($o['gl'])) echo '<span class="zl-tag">głośno</span>'; ?></div>
                <h3><?php echo zl_h($o['t']); ?></h3><p><?php echo zl_h($o['o']); ?></p>
                <div class="sz"><?php echo $s; ?>%<small><?php echo !empty($o['koszt']) ? 'koszt ' . zl_fmt(zl_kasa($g, (int)$o['koszt'])) . ' $' : 'szansa'; ?><?php echo !empty($o['lup']) ? ' · łup ' . $ile((int)$o['lup']) . '%' : ''; ?><?php echo !empty($o['premia']) ? ' · dalej +' . (int)$o['premia'] : ''; ?></small></div>
                <form method="POST"><input type="hidden" name="zl" value="skok_wybor"><input type="hidden" name="op" value="<?php echo $i; ?>"><button class="btn" type="submit" <?php echo $blokada ? 'disabled' : ''; ?>>Idź tą drogą</button></form>
              </div>
            <?php endforeach; ?>
            </div>
            <div class="ttl">Sukces: dalej bez strat. Połowiczny: łup −25%, obława +1. Porażka: łup −50%, obława +2. Dwie porażki z rzędu kończą skok<?php echo !empty($e['ucieczka']) ? '. Porażka w ucieczce grozi aresztem' : ''; ?>.</div>
            <form method="POST" style="margin:0;text-align:right" onsubmit="return confirm('Porzucić skok? −3 reputacji u zleceniodawcy.')"><input type="hidden" name="zl" value="skok_porzuc"><button class="btn maly" type="submit">Porzuć skok (−3 reputacji)</button></form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="zl-skoki-lista">
    <?php foreach ($dane['skoki'] as $kod => $d): $bl = $blokada ?: zl_skok_blok($polaczenie, $g, $d, $rep, $kod); ?>
      <div class="g zl-sk">
        <div class="ttl"><?php echo zl_h($dane['npc'][$d['npc']]['n'] ?? '') . (!empty($d['wsp']) ? ' & ' . zl_h($dane['npc'][$d['wsp']]['n'] ?? '') : ''); ?> · <?php echo count($d['etapy']); ?> etapy</div>
        <h3><?php echo zl_h($d['t']); ?></h3>
        <p><?php echo zl_h($d['b']); ?></p>
        <div class="zl-tagi"><span class="zl-tag m">od poziomu <?php echo (int)$d['lvl']; ?></span><span class="zl-tag m">reputacja: <?php echo ZL_RANGI[(int)$d['rep']]; ?></span><?php if (!empty($d['mat'])) echo '<span class="zl-tag z">+ części dla Manufaktury</span>'; ?><?php if ($bl) echo '<span class="zl-tag">' . zl_h($bl) . '</span>'; ?></div>
        <div class="dol"><span class="kasa">do <?php echo zl_fmt(zl_kasa($g, (int)$d['lup'], 2)); ?> $</span>
          <form method="POST" style="margin:0"><input type="hidden" name="zl" value="skok_start"><input type="hidden" name="kod" value="<?php echo zl_h($kod); ?>"><button class="btn duzy" type="submit" <?php echo ($bl || $uz >= $lim) ? 'disabled' : ''; ?>>Zacznij skok</button></form></div>
      </div>
    <?php endforeach; ?>
    </div>
    <p class="ttl" style="margin:0">Skoki otwierają się od rangi Zaufany u zleceniodawcy. Każdy etap trwa kilka–kilkanaście minut. Obława od 3★ blokuje skoki.</p>
  <?php endif; ?>

<?php elseif ($tab === 'glowy'): $cele = zl_glowy_lista($polaczenie); ?>
  <div class="zl-bh">
    <div class="zl-lista">
    <?php foreach ($cele as $c): $bl = zl_glowa_blok($polaczenie, $g, $c); ?>
      <div class="g zl-cel">
        <div><b><?php echo zl_h($c['login']); ?></b><small>poziom <?php echo (int)$c['poziom']; ?> · <?php echo zl_h($c['syndykat'] ?? 'bez syndykatu'); ?> · <?php echo (int)$c['ile'] > 1 ? (int)$c['ile'] . ' nagrody · ' : ''; ?>wygasa <?php echo date('d.m H:i', strtotime($c['wygasa'])); ?></small></div>
        <div class="n"><?php echo zl_fmt((int)$c['suma']); ?> $<br>
          <?php echo $bl ? '<span class="zl-tag m">' . zl_h($bl) . '</span>' : '<a class="btn maly" href="game.php?page=walka_pvp&cel=' . (int)$c['id'] . '">Poluj</a>'; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$cele) echo '<div class="g zl-pusto">Nikt nie wystawił nagrody za niczyją głowę. Na razie.</div>'; ?>
    </div>
    <form method="POST" class="g zl-bh-form">
      <input type="hidden" name="zl" value="glowa">
      <h3>Wystaw nagrodę</h3>
      <label>Login celu<input type="text" name="login" required maxlength="24" autocomplete="off"></label>
      <label>Nagroda ($)<input type="number" name="kwota" required min="<?php echo ZL_GLOWA_MIN; ?>" step="500" value="<?php echo ZL_GLOWA_MIN; ?>"></label>
      <button class="btn duzy" type="submit">Wystaw na <?php echo ZL_GLOWA_DNI; ?> dni</button>
      <p>Opłata <?php echo (int)(ZL_GLOWA_OPLATA * 100); ?>% przepada. Minimum <?php echo zl_fmt(ZL_GLOWA_MIN); ?> $. Nie na siebie ani na kogoś z własnego syndykatu. Cel od <?php echo ZL_GLOWA_LVL; ?> poziomu, łowca najwyżej <?php echo ZL_GLOWA_ROZNICA; ?> poziomów wyżej. Nagrodę zgarnia ten, kto wygra z celem walkę. Jedna próba na 12 godzin. Po <?php echo ZL_GLOWA_DNI; ?> dniach bez łowcy kwota wraca.</p>
    </form>
  </div>

<?php else: $wr = $region ?? 'NY'; $ws = db_wiersze($polaczenie, "SELECT tresc, kiedy FROM zl_wiesci WHERE region = ? ORDER BY id DESC LIMIT 30", [$wr]); ?>
  <div class="g zl-wiesci">
    <div class="ttl" style="display:block">Wieści · <?php echo ZL_REGIONY[$wr]; ?></div>
    <?php foreach ($ws as $w) echo '<div><span>' . date('d.m H:i', strtotime($w['kiedy'])) . '</span><p style="margin:0">' . html_wiesc($w['tresc']) . '</p></div>'; ?>
    <?php if (!$ws) echo '<p style="margin:0">Na ulicy cisza.</p>'; ?>
  </div>
<?php endif; ?>

</div>
<script>
(function(){var el=document.querySelectorAll('.zl-czas[data-do]');if(!el.length)return;
setInterval(function(){var now=Date.now()/1000,rel=false;el.forEach(function(e){var t=Math.max(0,Math.round(+e.dataset.do-now));e.textContent=String(Math.floor(t/60)).padStart(2,'0')+':'+String(t%60).padStart(2,'0');if(t===0&&+e.dataset.do>0){rel=true;e.dataset.do=0;}});if(rel)setTimeout(function(){location.reload();},600);},1000);})();
</script>
<?php
/** Wieści mają tylko <b> — reszta jako tekst. */
function html_wiesc(string $t): string { return preg_replace('#&lt;(/?)b&gt;#', '<$1b>', htmlspecialchars(strip_tags($t, '<b>'), ENT_QUOTES, 'UTF-8')); }
