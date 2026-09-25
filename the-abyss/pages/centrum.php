<?php
require_once "db.php";
require_once __DIR__ . '/../includes/bezpieczne.php';
require_once __DIR__ . '/../config/rangi.php';
require_once __DIR__ . '/../includes/podsumowanie.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* ═══════════════════════════════════════════════════════════════════════
   CENTRUM OPOWIEŚCI v2 — kolumny Nabór / W toku / Podsumowanie,
   zakładanie z uprawnieniami rang, akceptacja Opowieści JMG,
   zgłoszenia z Opowieści Swobodnych (MG i Adminka).
   Stara lista (pages/sesje.php) działa dalej — w menu podmień link na page=centrum.
   ═══════════════════════════════════════════════════════════════════════ */

$GATUNKI = ['Obyczajowa', 'Kryminalna', 'Śledztwo', 'Akcja', 'Horror', 'Romans', 'Więzienie', 'Polityczna', 'Inna'];
$ranga  = rp_ranga($polaczenie, $id_gracza);
$nadzor = rp_nadzor($ranga);
$ja     = db_wiersz($polaczenie, "SELECT login FROM gracze WHERE id = ?", [$id_gracza]);
$h      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$wroc   = function (string $q = '') { echo "<script>location.href='game.php?page=centrum$q';</script>"; exit; };
$blad   = '';

// ── ZAKŁADANIE ────────────────────────────────────────────────────
if (($_POST['co_akcja'] ?? '') === 'zaloz') {
    $typ = (string)($_POST['typ'] ?? ''); $poz = (string)($_POST['poziom'] ?? '');
    if ($typ === 'swobodna') $poz = 'swobodna';
    $tyt = trim(mb_substr((string)($_POST['tytul'] ?? ''), 0, 120));
    $opis = trim(mb_substr((string)($_POST['opis'] ?? ''), 0, 5000));
    $gat = in_array($_POST['gatunek'] ?? '', $GATUNKI, true) ? $_POST['gatunek'] : 'Inna';
    $oop = max(1, min(5, (int)($_POST['oop'] ?? 2)));
    $msc = (int)($_POST['miejsca'] ?? 0); $msc = $msc > 0 ? min(20, $msc) : null;
    if (!isset(RP_TYPY[$typ]) || !isset(RP_POZIOMY[$poz]) || !rp_moze_zalozyc($ranga, $typ, $poz)) $blad = 'Twoja ranga nie pozwala założyć takiej Opowieści.';
    elseif (mb_strlen($tyt) < 5 || mb_strlen($opis) < 20) $blad = 'Tytuł min. 5 znaków, zapowiedź min. 20.';
    else {
        $akc = rp_wymaga_akceptacji($ranga, $typ) ? 'czeka' : 'ok';
        $kat = $typ === 'swobodna' ? 'Prywatna' : 'Publiczna';
        $sid = db_tx($polaczenie, function () use ($polaczenie, $tyt, $opis, $id_gracza, $kat, $gat, $poz, $typ, $oop, $akc, $msc) {
            db_q($polaczenie, "INSERT INTO sesje_rpg (tytul, opis, mg_id, kategoria, gatunek, tagi, ostrzezenia, poziom_trudnosci, typ_opowiesci, poziom, oop, akceptacja, miejsca) VALUES (?, ?, ?, ?, ?, '', '', ?, ?, ?, ?, ?, ?)",
                [$tyt, $opis, $id_gracza, $kat, $gat, RP_POZIOMY[$poz]['n'], $typ, $poz, $oop, $akc, $msc]);
            $sid = (int)$polaczenie->insert_id;
            // W Swobodnej założyciel jest zwykłym uczestnikiem — nie dostaje Kulis MG.
            db_q($polaczenie, "INSERT INTO sesje_uczestnicy (sesja_id, gracz_id, rola, status_akceptacji) VALUES (?, ?, ?, 'Zaakceptowany')", [$sid, $id_gracza, $typ === 'swobodna' ? 'Gracz' : 'Mistrz Gry']);
            return $sid;
        });
        if ($typ === 'swobodna') {
            foreach (array_slice(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['zaproszeni'] ?? ''))))), 0, 10) as $l) {
                $g = db_wiersz($polaczenie, "SELECT id FROM gracze WHERE login = ?", [$l]);
                if (!$g || (int)$g['id'] === $id_gracza) continue;
                if (db_wiersz($polaczenie, "SELECT 1 FROM sesje_uczestnicy WHERE sesja_id = ? AND gracz_id = ?", [$sid, (int)$g['id']])) continue;
                db_zmien($polaczenie, "INSERT INTO sesje_uczestnicy (sesja_id, gracz_id, rola, status_akceptacji) VALUES (?, ?, 'Gracz', 'Zaakceptowany')", [$sid, (int)$g['id']]);
                powiadom($polaczenie, (int)$g['id'], "<b>" . bz_h($ja['login']) . "</b> zaprasza Cię do Opowieści Swobodnej <i>" . bz_h($tyt) . "</i>. <a href='game.php?page=pokoj_sesji&id=$sid' style='color:var(--neon-cyan)'>[ Wejdź ]</a>");
            }
        }
        if ($akc === 'czeka') foreach (rp_nadzorcy($polaczenie) as $n) powiadom($polaczenie, $n, "Junior MG <b>" . bz_h($ja['login']) . "</b> czeka na akceptację Opowieści <i>" . bz_h($tyt) . "</i>. <a href='game.php?page=centrum' style='color:var(--neon-gold)'>[ Centrum ]</a>");
        echo "<script>location.href='game.php?page=pokoj_sesji&id=$sid';</script>"; exit;
    }
}

// ── AKCEPTACJA OPOWIEŚCI JMG ──────────────────────────────────────
if ($nadzor && in_array($_POST['co_akcja'] ?? '', ['akceptuj', 'odrzuc'], true)) {
    $sid = (int)($_POST['sid'] ?? 0); $ok = $_POST['co_akcja'] === 'akceptuj';
    $s = db_wiersz($polaczenie, "SELECT id, tytul, mg_id FROM sesje_rpg WHERE id = ? AND akceptacja = 'czeka'", [$sid]);
    if ($s && db_zmien($polaczenie, "UPDATE sesje_rpg SET akceptacja = ? WHERE id = ? AND akceptacja = 'czeka'", [$ok ? 'ok' : 'odrzucona', $sid]) === 1)
        powiadom($polaczenie, (int)$s['mg_id'], "Opowieść <i>" . bz_h($s['tytul']) . "</i> została " . ($ok ? "<b style='color:var(--neon-green)'>zaakceptowana</b> — nabór ruszył." : "<b style='color:var(--neon-red-hot)'>odrzucona</b> przez MG."));
    $wroc();
}

// ── ZGŁOSZENIA ────────────────────────────────────────────────────
if ($nadzor && ($_POST['co_akcja'] ?? '') === 'zgl') {
    $z = db_wiersz($polaczenie, "SELECT z.*, s.tytul, s.mg_id FROM sesje_zgloszenia z JOIN sesje_rpg s ON s.id = z.sesja_id WHERE z.id = ? AND z.status = 'nowe'", [(int)($_POST['zid'] ?? 0)]);
    $co = (string)($_POST['decyzja'] ?? '');
    if ($z && in_array($co, ['dolaczyl', 'cofniety', 'przejeta', 'oddalone'], true)) {
        $sid = (int)$z['sesja_id'];
        db_tx($polaczenie, function () use ($polaczenie, $z, $co, $sid, $id_gracza) {
            if ($co !== 'oddalone') {
                if (db_wiersz($polaczenie, "SELECT 1 FROM sesje_uczestnicy WHERE sesja_id = ? AND gracz_id = ?", [$sid, $id_gracza]))
                    db_q($polaczenie, "UPDATE sesje_uczestnicy SET rola = 'Mistrz Gry', status_akceptacji = 'Zaakceptowany' WHERE sesja_id = ? AND gracz_id = ?", [$sid, $id_gracza]);
                else db_q($polaczenie, "INSERT INTO sesje_uczestnicy (sesja_id, gracz_id, rola, status_akceptacji) VALUES (?, ?, 'Mistrz Gry', 'Zaakceptowany')", [$sid, $id_gracza]);
            }
            if ($co === 'cofniety')
                db_q($polaczenie, "UPDATE sesje_posty SET tresc = ? WHERE id = ? AND sesja_id = ?", ['*[Post cofnięty przez Mistrza Gry po zgłoszeniu.]*', (int)$z['post_id'], $sid]);
            if ($co === 'przejeta') {
                // MG przejmuje prowadzenie: Opowieść staje się Sesją o poziomie Niskim, dawny właściciel zostaje graczem.
                db_q($polaczenie, "UPDATE sesje_uczestnicy SET rola = 'Gracz' WHERE sesja_id = ? AND gracz_id = ?", [$sid, (int)$z['mg_id']]);
                db_q($polaczenie, "UPDATE sesje_rpg SET mg_id = ?, typ_opowiesci = 'sesja', poziom = 'niski', poziom_trudnosci = 'Niski', kategoria = 'Publiczna' WHERE id = ?", [$id_gracza, $sid]);
            }
            db_q($polaczenie, "UPDATE sesje_zgloszenia SET status = ?, rozpatrzyl_id = ? WHERE id = ?", [$co, $id_gracza, (int)$z['id']]);
        });
        $opis = ['dolaczyl' => 'MG dołączył do Opowieści', 'cofniety' => 'MG cofnął zgłoszony post', 'przejeta' => 'MG przejął prowadzenie Opowieści', 'oddalone' => 'MG oddalił zgłoszenie'][$co];
        foreach (array_unique([(int)$z['zglaszajacy_id'], (int)$z['autor_id']]) as $g)
            powiadom($polaczenie, $g, "Zgłoszenie w <i>" . bz_h($z['tytul']) . "</i>: $opis. <a href='game.php?page=pokoj_sesji&id=$sid' style='color:var(--neon-cyan)'>[ Przejdź ]</a>");
    }
    $wroc('&zgl=1');
}

// ── LISTA ─────────────────────────────────────────────────────────
$f_typ = isset(RP_TYPY[$_GET['typ'] ?? '']) ? $_GET['typ'] : '';
$f_poz = isset(RP_POZIOMY[$_GET['poz'] ?? '']) ? $_GET['poz'] : '';
$f_q   = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
$wiersze = db_wiersze($polaczenie, "
    SELECT s.id, s.tytul, s.opis, s.mg_id, s.status, s.typ_opowiesci, s.poziom, s.oop, s.akceptacja, s.miejsca, s.walka_aktywna, g.login AS prow,
      (SELECT COUNT(*) FROM sesje_posty p WHERE p.sesja_id = s.id AND p.typ_postu IN ('Fabuła','Rzut_Koscia')) AS posty,
      (SELECT COUNT(*) FROM sesje_uczestnicy u WHERE u.sesja_id = s.id AND u.rola = 'Gracz' AND u.status_akceptacji = 'Zaakceptowany') AS graczy,
      (SELECT COUNT(*) FROM sesje_zgloszenia z WHERE z.sesja_id = s.id AND z.status = 'nowe') AS zgl,
      EXISTS(SELECT 1 FROM sesje_uczestnicy u2 WHERE u2.sesja_id = s.id AND u2.gracz_id = ?) AS jestem
    FROM sesje_rpg s JOIN gracze g ON g.id = s.mg_id
    WHERE (s.status <> 'Zakończona' OR s.data_zakonczenia > NOW() - INTERVAL 30 DAY) AND s.akceptacja <> 'odrzucona'
    ORDER BY s.ostatnia_aktywnosc DESC LIMIT 200", [$id_gracza]);
$kol = ['nabor' => [], 'toku' => [], 'pods' => []];
foreach ($wiersze as $o) {
    $moje = (int)$o['mg_id'] === $id_gracza;
    if ($o['typ_opowiesci'] === 'swobodna' && !$o['jestem'] && !$nadzor) continue;
    if ($o['akceptacja'] === 'czeka' && !$nadzor && !$moje) continue;
    if ($f_typ && $o['typ_opowiesci'] !== $f_typ) continue;
    if ($f_poz && $o['poziom'] !== $f_poz) continue;
    if ($f_q !== '' && mb_stripos($o['tytul'] . ' ' . $o['prow'], $f_q) === false) continue;
    $k = $o['status'] === 'Zakończona' ? 'pods' : (($o['akceptacja'] === 'czeka' || (int)$o['posty'] === 0) ? 'nabor' : 'toku');
    $kol[$k][] = $o;
}
$zgloszenia = $nadzor ? db_wiersze($polaczenie, "SELECT z.*, s.tytul, a.login AS kto, b.login AS na FROM sesje_zgloszenia z JOIN sesje_rpg s ON s.id = z.sesja_id JOIN gracze a ON a.id = z.zglaszajacy_id JOIN gracze b ON b.id = z.autor_id WHERE z.status = 'nowe' ORDER BY z.data DESC") : [];
$R = RP_RANGI[$ranga];
$link = fn(array $z) => 'game.php?page=centrum&' . http_build_query(array_filter($z + ['typ' => $f_typ, 'poz' => $f_poz, 'q' => $f_q], fn($v) => $v !== '' && $v !== null));
?>
<style>
.co{--g:#3dff9a;--y:#ffd23d;--o:#ff7a3d;--v:#b48cff;--dim:#cfc6d2;--mute:#a79eab;--bs:rgba(255,23,68,.22);display:flex;flex-direction:column;gap:18px;color:#f1ebf2;font-weight:500}
.co a{color:var(--neon-red-hot,#ff3d5e)}.co a:hover{color:#fff}
.co .lbl{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.8px;text-transform:uppercase;color:var(--dim)}
.co-head{display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;align-items:flex-end;padding-bottom:16px;border-bottom:1px solid var(--bs)}
.co-head h1{font-family:'Oswald',sans-serif;font-weight:500;font-size:2.4em;letter-spacing:5px;text-transform:uppercase;line-height:1;color:#fff;text-shadow:0 0 6px #fff,0 0 22px var(--neon-red,#ff1744);margin:0}
.co-head p{color:var(--dim);margin-top:8px;max-width:620px;line-height:1.45}
.co-btn{padding:10px 18px;border:1px solid var(--neon-red,#ff1744);background:rgba(255,23,68,.14);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2.5px;text-transform:uppercase;font-size:.9em;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.co-btn:hover{background:var(--neon-red,#ff1744);color:#fff}
.co-btn.ghost{border-color:rgba(255,255,255,.2);background:rgba(0,0,0,.35)}
.co-btn.sm{padding:6px 10px;font-size:.78em;letter-spacing:1.5px}
.co-btn.gold{border-color:var(--y);background:transparent}.co-btn.gold:hover{background:var(--y);color:#000}
.co-btn b{min-width:20px;height:20px;border-radius:10px;background:var(--y);color:#000;font-family:'JetBrains Mono',monospace;font-size:.8em;display:inline-flex;align-items:center;justify-content:center;padding:0 5px}
.co-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:12px 14px;background:rgba(0,0,0,.45);border:1px solid var(--bs)}
.co-chip{padding:6px 12px;border:1px solid rgba(255,255,255,.16);color:var(--dim) !important;font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase;font-size:.82em;display:flex;align-items:center;gap:6px;text-decoration:none}
.co-chip i{width:8px;height:8px;border-radius:50%;background:var(--c,#fff)}
.co-chip.on{color:#fff !important;border-color:var(--c,var(--neon-red,#ff1744));box-shadow:inset 0 0 0 1px var(--c,var(--neon-red,#ff1744))}
.co-bar form{flex:1 1 200px;display:flex}
.co-bar input{flex:1;background:rgba(0,0,0,.5);border:1px solid var(--bs);color:#fff;padding:7px 12px;font-family:inherit;font-size:1em}
.co-kan{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:start}
.co-col{background:rgba(8,4,10,.55);border:1px solid var(--bs)}
.co-col-h{display:flex;justify-content:space-between;align-items:baseline;padding:14px 16px;border-bottom:1px solid var(--bs);position:relative}
.co-col-h::after{content:'';position:absolute;left:0;bottom:-1px;width:70px;height:1px;background:var(--cc);box-shadow:0 0 8px var(--cc)}
.co-col-h h2{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.2em;letter-spacing:3px;text-transform:uppercase;margin:0;color:#fff}
.co-col-h span{font-family:'JetBrains Mono',monospace;color:var(--cc)}
.co-col-b{display:flex;flex-direction:column;gap:10px;padding:12px}
.co-empty{color:var(--mute);font-style:italic;padding:8px 4px}
.co-card{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.08);border-left:3px solid var(--lc);padding:13px 14px;display:flex;flex-direction:column;gap:8px}
.co-card.czeka{border-style:dashed;border-left-style:solid}
.co-tags{display:flex;gap:6px;flex-wrap:wrap}
.co-tag{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1.4px;text-transform:uppercase;padding:2px 7px;border:1px solid currentColor;color:var(--dim)}
.co-tag.lv{color:var(--lc)}.co-tag.y{color:var(--y)}.co-tag.o{color:var(--o)}
.co-card h3{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.1em;letter-spacing:1px;margin:0}
.co-card h3 a{color:#fff;text-decoration:none}
.co-card p{color:var(--dim);line-height:1.4;font-size:.95em;margin:0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.co-meta{display:flex;flex-wrap:wrap;gap:4px 14px;font-family:'JetBrains Mono',monospace;font-size:.76em;color:var(--dim)}
.co-meta b{color:#fff;font-weight:500}
.co-act{display:flex;gap:6px;flex-wrap:wrap}.co-act form{margin:0}
.co-zgl{display:flex;flex-direction:column;gap:10px;padding:16px;background:rgba(8,4,10,.6);border:1px solid rgba(255,210,61,.35)}
.co-zgl h2{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:3px;text-transform:uppercase;margin:0;color:#fff;font-size:1.15em}
.co-zi{border:1px solid rgba(255,210,61,.3);background:rgba(255,210,61,.05);padding:10px 12px;display:flex;flex-direction:column;gap:6px}
.co-zi q{font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.1em;quotes:none;color:#f1ebf2}
.co-zi details{color:var(--dim);font-size:.9em}.co-zi details div{margin-top:6px;padding:8px;background:rgba(0,0,0,.4);white-space:pre-wrap}
.co-new{background:rgba(8,4,10,.6);border:1px solid var(--neon-red,#ff1744);padding:18px 20px}
.co-new summary{cursor:pointer;list-style:none}
.co-new form{display:flex;flex-direction:column;gap:14px;margin-top:16px}
.co-f{display:flex;flex-direction:column;gap:6px}
.co-f input,.co-f textarea,.co-f select{background:rgba(0,0,0,.55);border:1px solid var(--bs);color:#fff;padding:9px 12px;font-family:inherit;font-size:1em;font-weight:500}
.co-f textarea{min-height:90px;resize:vertical}
.co-opts{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}
.co-opt{border:1px solid rgba(255,255,255,.15);background:rgba(0,0,0,.4);padding:10px;color:var(--dim);display:flex;flex-direction:column;gap:3px;cursor:pointer}
.co-opt input{display:none}
.co-opt b{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;color:#fff;font-size:.95em}
.co-opt small{font-size:.85em;line-height:1.3}
.co-opt:has(input:checked){border-color:var(--lc,var(--neon-red,#ff1744));box-shadow:inset 0 0 0 1px var(--lc,var(--neon-red,#ff1744))}
.co-opt:has(input:checked) b{color:var(--lc,#fff)}
.co-opt:has(input:disabled){opacity:.3;cursor:not-allowed}
.co-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.co-note{padding:10px 12px;border:1px solid var(--bs);background:rgba(255,23,68,.05);color:var(--dim);line-height:1.45}
.co-note b{color:#fff}
.co-err{padding:11px 14px;border:1px solid var(--neon-red-hot,#ff3d5e);color:var(--neon-red-hot,#ff3d5e);font-family:'JetBrains Mono',monospace;font-size:.88em}
@media (max-width:1000px){.co-kan{grid-template-columns:1fr}}
</style>
<div class="co">
  <header class="co-head">
    <div><div class="lbl">Ranga: <?php echo $h($R['n']); ?></div><h1>Centrum Opowieści</h1><p>Sesje prowadzone przez Mistrzów Gry, Wydarzenia w Klubie i Opowieści Swobodne, które gracze zakładają sami.</p></div>
    <div class="co-act">
      <?php if ($nadzor): ?><a class="co-btn ghost" href="game.php?page=centrum&zgl=1#zgl">Zgłoszenia <?php if ($zgloszenia) echo '<b>' . count($zgloszenia) . '</b>'; ?></a><a class="co-btn ghost" href="game.php?page=przeglad">Przegląd</a><a class="co-btn ghost" href="game.php?page=rangi">Rangi</a><?php endif; ?>
      <a class="co-btn" href="#nowa" onclick="document.getElementById('nowa').open=true">+ Załóż Opowieść</a>
    </div>
  </header>
  <?php if ($blad): ?><div class="co-err">⚠ <?php echo $h($blad); ?></div><?php endif; ?>

  <?php if ($nadzor && ($zgloszenia || isset($_GET['zgl']))): ?>
  <section class="co-zgl" id="zgl">
    <h2>Zgłoszenia z Opowieści Swobodnych</h2>
    <?php foreach ($zgloszenia as $z): ?>
      <div class="co-zi">
        <div class="co-meta"><span><b><a href="game.php?page=pokoj_sesji&id=<?php echo (int)$z['sesja_id']; ?>"><?php echo $h($z['tytul']); ?></a></b></span><span><?php echo $h($z['kto']); ?> zgłasza post: <b><?php echo $h($z['na']); ?></b></span><span><?php echo $h($z['data']); ?></span></div>
        <q>„<?php echo $h($z['powod']); ?>”</q>
        <details><summary>Treść zgłoszonego posta</summary><div><?php echo $h($z['kopia_tresci']); ?></div></details>
        <form method="POST" class="co-act"><input type="hidden" name="co_akcja" value="zgl"><input type="hidden" name="zid" value="<?php echo (int)$z['id']; ?>">
          <button class="co-btn sm gold" name="decyzja" value="dolaczyl" type="submit">Dołącz jako MG</button>
          <button class="co-btn sm gold" name="decyzja" value="cofniety" type="submit" onclick="return confirm('Cofnąć post? Treść zostanie zastąpiona informacją MG.')">Cofnij post</button>
          <button class="co-btn sm gold" name="decyzja" value="przejeta" type="submit" onclick="return confirm('Przejąć prowadzenie? Opowieść stanie się Sesją o poziomie Niskim.')">Przejmij sesję</button>
          <button class="co-btn sm gold" name="decyzja" value="oddalone" type="submit">Oddal</button>
        </form>
      </div>
    <?php endforeach; if (!$zgloszenia) echo '<div class="co-empty">Brak nowych zgłoszeń.</div>'; ?>
  </section>
  <?php endif; ?>

  <?php $widok = ($_GET['widok'] ?? '') === 'wiesci' ? 'wiesci' : 'lista'; ?>
  <div class="co-bar" style="padding:0;background:none;border:0;border-bottom:1px solid var(--bs);gap:2px">
    <a class="co-chip<?php echo $widok === 'lista' ? ' on' : ''; ?>" href="game.php?page=centrum">Opowieści</a>
    <a class="co-chip<?php echo $widok === 'wiesci' ? ' on' : ''; ?>" href="game.php?page=centrum&widok=wiesci">Wieści</a>
  </div>
  <?php if ($widok === 'wiesci'): ?>
  <section style="display:flex;flex-direction:column;gap:12px">
    <?php $ws = pd_wiesci($polaczenie); foreach ($ws as $w): $L = RP_POZIOMY[$w['poziom']] ?? RP_POZIOMY['niski']; ?>
      <article class="co-card" id="w<?php echo (int)$w['id']; ?>" style="--lc:<?php echo $L['kolor']; ?>;padding:16px 18px">
        <div class="co-tags"><span class="co-tag lv"><?php echo $h($L['n']); ?></span><span class="co-tag"><?php echo date('d.m.Y', strtotime($w['data_zakonczenia'])); ?></span></div>
        <h3 style="font-family:'Cormorant Garamond',serif;font-weight:600;font-size:1.7em;letter-spacing:0"><a href="game.php?page=pokoj_sesji&id=<?php echo (int)$w['id']; ?>&zakladka=podsumowanie"><?php echo $h($w['wiesc_tytul']); ?></a></h3>
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.15em;line-height:1.55;color:#ece4ec;-webkit-line-clamp:unset;display:block"><?php echo nl2br($h($w['wiesc_tresc'])); ?></p>
        <div class="co-meta"><span>Prowadził(a) <b><?php echo $h($w['prow']); ?></b></span><?php if ($w['uczestnicy']) echo '<span>' . $h($w['uczestnicy']) . '</span>'; ?></div>
      </article>
    <?php endforeach; if (!$ws) echo '<div class="co-empty">Jeszcze nie ma Wieści.</div>'; ?>
  </section>
  <?php else: ?>
  <div class="co-bar">
    <a class="co-chip<?php echo !$f_typ ? ' on' : ''; ?>" href="<?php echo $h($link(['typ' => ''])); ?>">Wszystkie</a>
    <?php foreach (RP_TYPY as $k => $t): ?><a class="co-chip<?php echo $f_typ === $k ? ' on' : ''; ?>" href="<?php echo $h($link(['typ' => $k])); ?>"><?php echo $h($t['n']); ?></a><?php endforeach; ?>
    <?php foreach (RP_POZIOMY as $k => $p): ?><a class="co-chip<?php echo $f_poz === $k ? ' on' : ''; ?>" style="--c:<?php echo $p['kolor']; ?>" href="<?php echo $h($link(['poz' => $f_poz === $k ? '' : $k])); ?>"><i></i><?php echo $h($p['n']); ?></a><?php endforeach; ?>
    <form method="GET"><input type="hidden" name="page" value="centrum"><?php if ($f_typ) echo '<input type="hidden" name="typ" value="' . $h($f_typ) . '">'; if ($f_poz) echo '<input type="hidden" name="poz" value="' . $h($f_poz) . '">'; ?><input type="search" name="q" value="<?php echo $h($f_q); ?>" placeholder="Szukaj tytułu lub prowadzącego…"></form>
  </div>

  <section class="co-kan">
  <?php foreach (['nabor' => ['Nabór', 'var(--g)'], 'toku' => ['W toku', 'var(--o)'], 'pods' => ['Podsumowanie', 'var(--v)']] as $k => [$nz, $cc]): ?>
    <div class="co-col" style="--cc:<?php echo $cc; ?>"><div class="co-col-h"><h2><?php echo $nz; ?></h2><span><?php echo count($kol[$k]); ?></span></div><div class="co-col-b">
    <?php foreach ($kol[$k] as $o): $L = RP_POZIOMY[$o['poziom']] ?? RP_POZIOMY['niski']; $url = 'game.php?page=pokoj_sesji&id=' . (int)$o['id']; ?>
      <article class="co-card<?php echo $o['akceptacja'] === 'czeka' ? ' czeka' : ''; ?>" style="--lc:<?php echo $L['kolor']; ?>">
        <div class="co-tags"><span class="co-tag lv"><?php echo $h($L['n']); ?></span><span class="co-tag"><?php echo $h(RP_TYPY[$o['typ_opowiesci']]['n'] ?? ''); ?></span>
          <?php if ($o['akceptacja'] === 'czeka') echo '<span class="co-tag y">Czeka na akceptację MG</span>'; ?>
          <?php if ((int)$o['walka_aktywna']) echo '<span class="co-tag o">Walka</span>'; ?>
          <?php if ($nadzor && (int)$o['zgl']) echo '<span class="co-tag y">Zgłoszenie</span>'; ?></div>
        <h3><a href="<?php echo $url; ?>"><?php echo $h($o['tytul']); ?></a></h3>
        <p><?php echo $h($o['opis']); ?></p>
        <div class="co-meta"><span>Prowadzi <b><?php echo $h($o['prow']); ?></b></span><span><?php echo (int)$o['graczy']; ?><?php echo $o['miejsca'] ? ' / ' . (int)$o['miejsca'] : ''; ?> graczy</span><?php if ($o['typ_opowiesci'] !== 'swobodna') echo '<span>OoP <b>' . (int)$o['oop'] . '</b></span>'; ?></div>
        <div class="co-act">
          <?php if ($o['akceptacja'] === 'czeka' && $nadzor): ?>
            <form method="POST"><input type="hidden" name="sid" value="<?php echo (int)$o['id']; ?>"><button class="co-btn sm" name="co_akcja" value="akceptuj" type="submit">Akceptuj</button> <button class="co-btn sm ghost" name="co_akcja" value="odrzuc" type="submit">Odrzuć</button></form>
          <?php endif; ?>
          <a class="co-btn sm ghost" href="<?php echo $url; ?>"><?php echo $k === 'pods' ? 'Czytaj' : ($o['jestem'] ? 'Wejdź' : ($o['miejsca'] && (int)$o['graczy'] >= (int)$o['miejsca'] ? 'Komplet' : 'Zobacz i dołącz')); ?></a>
        </div>
      </article>
    <?php endforeach; if (!$kol[$k]) echo '<div class="co-empty">Nic tu nie ma.</div>'; ?>
    </div></div>
  <?php endforeach; ?>
  </section>

  <?php endif; ?>

  <details class="co-new" id="nowa"<?php echo $blad ? ' open' : ''; ?>>
    <summary><span class="lbl">Zakładasz jako: <?php echo $h($R['n']); ?></span><div style="font-family:'Oswald',sans-serif;font-size:1.5em;letter-spacing:3px;text-transform:uppercase;color:#fff">Nowa Opowieść</div></summary>
    <form method="POST" id="coForm">
      <input type="hidden" name="co_akcja" value="zaloz">
      <div class="co-f"><span class="lbl">Rodzaj</span><div class="co-opts">
        <?php foreach (RP_TYPY as $k => $t): $ok = in_array($k, $R['typy'], true); ?><label class="co-opt"><input type="radio" name="typ" value="<?php echo $k; ?>"<?php echo $ok ? '' : ' disabled'; ?><?php echo $k === $R['typy'][0] ? ' checked' : ''; ?>><b><?php echo $h($t['n']); ?></b><small><?php echo $h($t['opis']); ?></small></label><?php endforeach; ?>
      </div></div>
      <div class="co-f"><span class="lbl">Poziom trudności</span><div class="co-opts">
        <?php foreach (RP_POZIOMY as $k => $p): ?><label class="co-opt" style="--lc:<?php echo $p['kolor']; ?>"><input type="radio" name="poziom" value="<?php echo $k; ?>" data-ok="<?php echo in_array($k, $R['poziomy'], true) ? 1 : 0; ?>"><b><?php echo $h($p['n']); ?></b><small><?php echo $h($p['opis']); ?></small></label><?php endforeach; ?>
      </div></div>
      <div class="co-f"><span class="lbl">Tytuł</span><input name="tytul" maxlength="120" required minlength="5" placeholder="np. Krew na Pier 17"></div>
      <div class="co-f"><span class="lbl">Zapowiedź</span><textarea name="opis" required minlength="20" placeholder="Kilka zdań dla graczy — gdzie, kiedy, co grozi."></textarea></div>
      <div class="co-row">
        <div class="co-f"><span class="lbl">Gatunek</span><select name="gatunek"><?php foreach ($GATUNKI as $g) echo '<option>' . $h($g) . '</option>'; ?></select></div>
        <div class="co-f"><span class="lbl">Miejsca</span><select name="miejsca"><option value="0">bez limitu</option><?php for ($i = 2; $i <= 8; $i++) echo "<option" . ($i === 4 ? ' selected' : '') . ">$i</option>"; ?></select></div>
        <div class="co-f" data-nie-swob><span class="lbl">OoP sceny</span><select name="oop"><?php for ($i = 1; $i <= 5; $i++) echo "<option" . ($i === 2 ? ' selected' : '') . ">$i</option>"; ?></select></div>
      </div>
      <div class="co-f" data-swob><span class="lbl">Zaproś graczy</span><input name="zaproszeni" placeholder="Loginy oddzielone przecinkiem — tylko oni zobaczą Opowieść (maks. 10)"></div>
      <div class="co-note" id="coNote"></div>
      <div class="co-act" style="justify-content:flex-end"><button class="co-btn" type="submit" id="coOk">Otwórz nabór</button></div>
    </form>
  </details>
</div>
<script>
(function(){const f=document.getElementById('coForm');if(!f)return;const P=<?php echo json_encode(array_map(fn($p) => $p['n'] . ': ' . $p['opis'], RP_POZIOMY), JSON_UNESCAPED_UNICODE); ?>,akc=<?php echo !empty($R['akceptacja']) ? 'true' : 'false'; ?>;
function up(){const t=f.querySelector('[name=typ]:checked').value,sw=t==='swobodna';
 f.querySelectorAll('[name=poziom]').forEach(i=>{i.disabled=i.dataset.ok!=='1'||(sw?i.value!=='swobodna':i.value==='swobodna')});
 let c=f.querySelector('[name=poziom]:checked');if(!c||c.disabled){c=[...f.querySelectorAll('[name=poziom]')].find(i=>!i.disabled);if(c)c.checked=true}
 f.querySelectorAll('[data-swob]').forEach(e=>e.style.display=sw?'':'none');f.querySelectorAll('[data-nie-swob]').forEach(e=>e.style.visibility=sw?'hidden':'');
 const n=document.getElementById('coNote'),b=document.getElementById('coOk');
 if(sw){n.innerHTML='<b>Swobodna:</b> każdy rzuca sam na swoją postać. Życie i Urazy liczą się, PW nie ma. Nie trafia do Wieści ani na Oś Czasu. Przy postach jest przycisk „Zgłoś MG”.';b.textContent='Wyślij zaproszenia'}
 else if(akc){n.innerHTML='<b>Junior MG:</b> Opowieść trafi do MG. Nabór ruszy dopiero po akceptacji.';b.textContent='Wyślij do akceptacji MG'}
 else if(t==='klub'){n.innerHTML='<b>Wydarzenie w Klubie</b> toczy się w czasie rzeczywistym. Kulisy MG otworzą się obok Opowieści.';b.textContent='Otwórz nabór'}
 else{n.innerHTML='<b>'+(c?P[c.value]:'')+'</b> Rzuca prowadzący; gracze rzucają sami tylko w Swobodnych.';b.textContent='Otwórz nabór'}}
f.addEventListener('change',up);up();})();
</script>
