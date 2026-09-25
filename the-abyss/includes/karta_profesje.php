<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/KARTA_PROFESJE.PHP
   Profesje fabularne: 2 miejsca, 4 etapy każda (1 / 2 / 3 / 4 PU).
   Awans: Umiejętności (rosną z etapem), poziom postaci, dyplom z Uniwersytetu.
   Porzucenie: raz na postać, PU z porzuconej Profesji zostają.
   Zależności: config/zawody.php, config/umiejetnosci.php, config/uniwersytet.php
   ═══════════════════════════════════════════════════════════════════════ */

const KP_POZIOM_ETAPU = [1 => 1, 2 => 10, 3 => 25, 4 => 45];

function kp_sloty(array $g): array {
    return [
        1 => ['n' => $g['profesja_fabularna'] ?: null, 'e' => (int)($g['profesja_etap'] ?? 0)],
        2 => ['n' => $g['profesja2'] ?: null,          'e' => (int)($g['profesja2_etap'] ?? 0)],
    ];
}

/** Lista wymagań etapu: [[etykieta, ma, trzeba, ok], ...] */
function kp_wymagania(mysqli $db, array $g, array $zawod, int $etap): array {
    $um = !empty($g['umiejetnosci']) ? (json_decode($g['umiejetnosci'], true) ?: []) : [];
    $r = [];
    $poz = KP_POZIOM_ETAPU[$etap] ?? 1;
    if ($poz > 1) $r[] = ['Poziom postaci', (int)$g['poziom'], $poz, (int)$g['poziom'] >= $poz];
    [$ok, $tyt] = uni_wymog_zawodu($db, (int)$g['id'], $zawod, $etap);
    if ($tyt) $r[] = ['🎓 ' . $tyt, $ok ? '✓' : '—', '', $ok];
    foreach ($zawod['wymagania'] ?? [] as $n => $l) {
        $t = um_wymaganie_etapu((int)$l, $etap);
        if ($t <= 0) continue;
        $m = min(UM_MAX_POZIOM, (int)($um[$n] ?? 0));
        $r[] = [$n, $m, $t, $m >= $t];
    }
    return $r;
}
function kp_spelnia(array $w): bool { foreach ($w as $x) if (!$x[3]) return false; return true; }

/** Obsługa POST. Zwraca komunikat błędu albo kończy się przekierowaniem. */
function kp_obsluz_post(mysqli $db, int $gid, array $ZAWODY): string {
    $a = $_POST['kp'] ?? '';
    if (!$a) return '';
    $g = db_wiersz($db, "SELECT * FROM gracze WHERE id = ?", [$gid]);
    $sloty = kp_sloty($g);
    $wroc = function (string $q = '') { echo "<script>location.href='game.php?page=karta$q#kariera';</script>"; exit; };

    if ($a === 'wez') {
        $zn = (string)($_POST['zawod'] ?? '');
        if (!isset($ZAWODY[$zn])) return 'Nieznana Profesja.';
        if ($sloty[1]['n'] === $zn || $sloty[2]['n'] === $zn) return 'Już wykonujesz tę Profesję.';
        if (!kp_spelnia(kp_wymagania($db, $g, $ZAWODY[$zn], 1))) return 'Nie spełniasz wymagań etapu 1.';
        $ok = !$sloty[1]['n']
            ? db_zmien($db, "UPDATE gracze SET profesja_fabularna = ?, profesja_etap = 1 WHERE id = ? AND (profesja_fabularna IS NULL OR profesja_fabularna = '')", [$zn, $gid])
            : (!$sloty[2]['n'] ? db_zmien($db, "UPDATE gracze SET profesja2 = ?, profesja2_etap = 1 WHERE id = ? AND (profesja2 IS NULL OR profesja2 = '') AND profesja_fabularna <> ?", [$zn, $gid, $zn]) : 0);
        if (!$ok) return 'Oba miejsca na Profesje są zajęte.';
        $wroc('&kp=wzieta');
    }
    if ($a === 'awans') {
        $s = (int)($_POST['slot'] ?? 0);
        if (!isset($sloty[$s]) || !$sloty[$s]['n'] || !isset($ZAWODY[$sloty[$s]['n']])) return 'Brak Profesji w tym miejscu.';
        $e = $sloty[$s]['e'];
        if ($e >= 4) return 'To już najwyższy etap.';
        if (!kp_spelnia(kp_wymagania($db, $g, $ZAWODY[$sloty[$s]['n']], $e + 1))) return 'Nie spełniasz wymagań kolejnego etapu.';
        [$kn, $ke] = $s === 1 ? ['profesja_fabularna', 'profesja_etap'] : ['profesja2', 'profesja2_etap'];
        if (!db_zmien($db, "UPDATE gracze SET $ke = $ke + 1 WHERE id = ? AND $kn = ? AND $ke = ?", [$gid, $sloty[$s]['n'], $e])) return 'Karta zmieniła się w międzyczasie — odśwież.';
        $wroc('&kp=awans');
    }
    if ($a === 'porzuc') {
        $s = (int)($_POST['slot'] ?? 0);
        if (!isset($sloty[$s]) || !$sloty[$s]['n']) return 'Brak Profesji w tym miejscu.';
        if ((int)$g['profesja_porzucona']) return 'Profesję można porzucić tylko raz.';
        $pu = um_pu_profesji($sloty[$s]['e']);
        $ok = $s === 1
            ? db_zmien($db, "UPDATE gracze SET profesja_pu_zachowane = profesja_pu_zachowane + ?, profesja_porzucona = 1,
                  profesja_fabularna = profesja2, profesja_etap = profesja2_etap, profesja2 = NULL, profesja2_etap = 0
                  WHERE id = ? AND profesja_porzucona = 0 AND profesja_fabularna = ?", [$pu, $gid, $sloty[1]['n']])
            : db_zmien($db, "UPDATE gracze SET profesja_pu_zachowane = profesja_pu_zachowane + ?, profesja_porzucona = 1,
                  profesja2 = NULL, profesja2_etap = 0 WHERE id = ? AND profesja_porzucona = 0 AND profesja2 = ?", [$pu, $gid, $sloty[2]['n']]);
        if (!$ok) return 'Nie udało się porzucić Profesji — odśwież.';
        $wroc('&kp=porzucona');
    }
    return '';
}

function kp_renderuj(mysqli $db, array $g, array $ZAWODY, string $blad = ''): void {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $sloty = kp_sloty($g);
    $wolny = !$sloty[1]['n'] ? 1 : (!$sloty[2]['n'] ? 2 : 0);
    $msg = ['wzieta' => 'Nowa Profesja — etap 1. +1 PU.', 'awans' => 'Awans na kolejny etap Profesji. Nowe PU czekają w Umiejętnościach.', 'porzucona' => 'Profesja porzucona. Zdobyte z niej PU zostają.'][$_GET['kp'] ?? ''] ?? '';
    $kat_nazwy = ['handel' => 'Handel i usługi', 'rzemioslo' => 'Rzemiosło', 'transport' => 'Transport', 'sztuka' => 'Sztuka i media', 'nauka' => 'Edukacja i nauka',
                  'biznes' => 'Biznes, prawo, polityka', 'sluzby' => 'Służby', 'walka' => 'Walka', 'medycyna' => 'Medycyna i opieka'];
    $kat = [];
    foreach ($ZAWODY as $zd) { $k = $zd['kategoria'] ?? 'inne'; $kat[$k] = $kat_nazwy[$k] ?? ucfirst($k); }
    ?>
<style>
.kp{display:flex;flex-direction:column;gap:18px}
.kp-msg{padding:11px 14px;border:1px solid var(--neon-green);color:var(--neon-green);font-family:'JetBrains Mono',monospace;font-size:.85em}
.kp-msg.err{border-color:var(--border-hot);color:var(--neon-red-hot)}
.kp-sloty{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}
.kp-slot{border:1px solid var(--border-mid);background:rgba(0,0,0,.45);padding:16px 18px;display:flex;flex-direction:column;gap:12px;position:relative}
.kp-slot.pusty{border-style:dashed;border-color:rgba(255,255,255,.14);align-items:flex-start;justify-content:center;min-height:170px}
.kp-slot .lbl{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1.6px;text-transform:uppercase;color:#c4bac8}
.kp-slot h3{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.35em;letter-spacing:1.5px;text-transform:uppercase;color:#fff}
.kp-etapy{display:flex;gap:8px;align-items:center}
.kp-e{display:flex;flex-direction:column;align-items:center;gap:3px;font-family:'JetBrains Mono',monospace;font-size:.66em;color:#c4bac8}
.kp-e i{width:16px;height:16px;transform:rotate(45deg);border:1.5px solid rgba(74,214,255,.45)}
.kp-e.on i{background:var(--neon-cyan);border-color:var(--neon-cyan);box-shadow:0 0 8px var(--neon-cyan)}
.kp-e.on{color:#fff}
.kp-pu{margin-left:auto;font-family:'Oswald',sans-serif;color:var(--neon-gold);font-size:1.05em;letter-spacing:1px}
.kp-req{display:flex;flex-direction:column;gap:4px;font-size:.92em}
.kp-req div{display:flex;justify-content:space-between;gap:10px;color:#e3dce6}
.kp-req div span:last-child{font-family:'JetBrains Mono',monospace}
.kp-req .ok span:last-child{color:var(--neon-green)}
.kp-req .no span:last-child{color:var(--neon-red-hot)}
.kp-akcje{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.kp-btn{padding:9px 14px;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:.85em;border:1px solid var(--neon-cyan);background:rgba(74,214,255,.08);color:var(--neon-cyan);cursor:pointer}
.kp-btn:hover:not(:disabled){background:var(--neon-cyan);color:#000}
.kp-btn:disabled{opacity:.35;cursor:not-allowed}
.kp-btn.gold{border-color:var(--neon-gold);color:var(--neon-gold);background:rgba(255,215,0,.07)}
.kp-btn.gold:hover:not(:disabled){background:var(--neon-gold);color:#000}
.kp-btn.ghost{border-color:transparent;background:transparent;color:#c4bac8;letter-spacing:1px}
.kp-btn.ghost:hover:not(:disabled){color:var(--neon-red-hot);background:transparent}
.kp-tools{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.kp-tools input[type=search]{flex:1 1 220px;background:rgba(0,0,0,.4);border:1px solid var(--border-mid);color:var(--txt-main);padding:8px 12px;font-family:inherit;font-size:1em}
.kp-chip{background:transparent;border:1px solid rgba(255,255,255,.14);color:#c4bac8;padding:6px 10px;font-family:inherit;font-size:.88em;font-weight:600;cursor:pointer}
.kp-chip.on{color:#fff;border-color:var(--neon-cyan);box-shadow:inset 0 0 0 1px var(--neon-cyan)}
.kp-tgl{display:flex;gap:6px;align-items:center;color:#c4bac8;font-weight:600;cursor:pointer}
.kp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.kp-z{border:1px solid rgba(255,255,255,.07);background:rgba(0,0,0,.45);padding:14px 16px;display:flex;flex-direction:column;gap:10px}
.kp-z.dost{border-color:rgba(74,214,255,.35)}
.kp-z.moja{border-color:var(--neon-green)}
.kp-z h4{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.08em;letter-spacing:1px;text-transform:uppercase;color:#fff}
.kp-z p{color:#c4bac8;font-size:.9em;line-height:1.4;text-wrap:pretty}
.kp-z .kp-req{font-size:.86em}
.kp-z form{margin-top:auto}
.kp-z.ukryj{display:none}
</style>
<div class="kp" id="kariera">
  <?php if ($blad): ?><div class="kp-msg err">⚠ <?php echo $h($blad); ?></div><?php elseif ($msg): ?><div class="kp-msg"><?php echo $h($msg); ?></div><?php endif; ?>
  <div class="kp-sloty">
  <?php foreach ($sloty as $nr => $s):
      if (!$s['n'] || !isset($ZAWODY[$s['n']])): ?>
    <div class="kp-slot pusty"><span class="lbl">Profesja <?php echo $nr; ?> · wolne miejsce</span><p style="color:#c4bac8;line-height:1.45">Wybierz Profesję z listy poniżej. Etap 1 daje 1 PU, każdy kolejny o 1 więcej (razem 10 PU z jednej Profesji).</p></div>
  <?php continue; endif;
      $zd = $ZAWODY[$s['n']]; $nast = $s['e'] < 4 ? kp_wymagania($db, $g, $zd, $s['e'] + 1) : []; ?>
    <div class="kp-slot">
      <div><span class="lbl">Profesja <?php echo $nr; ?><?php echo $nr === 1 ? ' · główna' : ''; ?></span><h3><?php echo $h($s['n']); ?></h3></div>
      <div class="kp-etapy"><?php for ($e = 1; $e <= 4; $e++): ?><div class="kp-e<?php echo $e <= $s['e'] ? ' on' : ''; ?>"><i></i><span>Etap <?php echo $e; ?></span></div><?php endfor; ?>
        <span class="kp-pu">+<?php echo um_pu_profesji($s['e']); ?> PU</span></div>
      <?php if ($nast): ?>
        <div class="kp-req"><span class="lbl">Wymagania etapu <?php echo $s['e'] + 1; ?> (+<?php echo $s['e'] + 1; ?> PU)</span>
        <?php foreach ($nast as [$et, $ma, $tr, $ok]): ?><div class="<?php echo $ok ? 'ok' : 'no'; ?>"><span><?php echo $h($et); ?></span><span><?php echo $tr === '' ? ($ok ? '✓' : '✗') : $ma . ' / ' . $tr; ?></span></div><?php endforeach; ?>
        </div>
      <?php else: ?><div class="kp-req"><span class="lbl">Najwyższy etap osiągnięty</span></div><?php endif; ?>
      <form method="POST" action="game.php?page=karta" class="kp-akcje">
        <input type="hidden" name="slot" value="<?php echo $nr; ?>">
        <?php if ($nast): ?><button type="submit" name="kp" value="awans" class="kp-btn gold" <?php echo kp_spelnia($nast) ? '' : 'disabled'; ?>>Awansuj na etap <?php echo $s['e'] + 1; ?></button><?php endif; ?>
        <?php if (!(int)$g['profesja_porzucona']): ?><button type="submit" name="kp" value="porzuc" class="kp-btn ghost" onclick="return confirm('Porzucić Profesję „<?php echo $h(addslashes($s['n'])); ?>”? Zdobyte z niej PU zostaną, ale porzucić można tylko raz.')">Porzuć (1× na postać)</button><?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="kp-tools">
    <input type="search" id="kpQ" placeholder="Szukaj Profesji…">
    <button type="button" class="kp-chip on" data-k="">Wszystkie</button>
    <?php foreach ($kat as $k => $n): ?><button type="button" class="kp-chip" data-k="<?php echo $h($k); ?>"><?php echo $h($n); ?></button><?php endforeach; ?>
    <label class="kp-tgl"><input type="checkbox" id="kpDost"> Tylko dostępne</label>
  </div>
  <div class="kp-grid" id="kpGrid">
  <?php foreach ($ZAWODY as $zn => $zd):
      $moja = $sloty[1]['n'] === $zn || $sloty[2]['n'] === $zn;
      $w = kp_wymagania($db, $g, $zd, 1); $ok = kp_spelnia($w); ?>
    <div class="kp-z<?php echo $moja ? ' moja' : ($ok ? ' dost' : ''); ?>" data-k="<?php echo $h($zd['kategoria'] ?? 'inne'); ?>" data-ok="<?php echo $ok && !$moja ? 1 : 0; ?>" data-n="<?php echo $h(mb_strtolower($zn)); ?>">
      <h4><?php echo $h($zn); ?></h4>
      <p><?php echo $h($zd['opis'] ?? ''); ?></p>
      <div class="kp-req"><?php foreach ($w as [$et, $ma, $tr, $o]): ?><div class="<?php echo $o ? 'ok' : 'no'; ?>"><span><?php echo $h($et); ?></span><span><?php echo $tr === '' ? ($o ? '✓' : '✗') : $ma . ' / ' . $tr; ?></span></div><?php endforeach; ?></div>
      <form method="POST" action="game.php?page=karta"><input type="hidden" name="zawod" value="<?php echo $h($zn); ?>">
        <?php if ($moja): ?><button type="button" class="kp-btn" disabled>✓ Wykonujesz</button>
        <?php elseif (!$wolny): ?><button type="button" class="kp-btn" disabled>Oba miejsca zajęte</button>
        <?php else: ?><button type="submit" name="kp" value="wez" class="kp-btn" <?php echo $ok ? '' : 'disabled'; ?>><?php echo $ok ? 'Weź jako Profesję ' . $wolny : 'Nie spełniasz wymagań'; ?></button><?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<script>
(function(){let kat='';const q=document.getElementById('kpQ'),d=document.getElementById('kpDost'),z=document.querySelectorAll('#kpGrid .kp-z');
function f(){const t=q.value.trim().toLowerCase();z.forEach(e=>e.classList.toggle('ukryj',(kat&&e.dataset.k!==kat)||(t&&!e.dataset.n.includes(t))||(d.checked&&e.dataset.ok!=='1')))}
document.querySelectorAll('.kp-chip').forEach(b=>b.onclick=()=>{kat=b.dataset.k;document.querySelectorAll('.kp-chip').forEach(x=>x.classList.toggle('on',x===b));f()});
q.oninput=f;d.onchange=f;})();
</script>
<?php
}
