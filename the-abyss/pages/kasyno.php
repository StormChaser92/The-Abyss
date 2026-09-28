<?php
/* the-abyss/pages/kasyno.php
   Kasyno Golden Dragon — stół Hold'em. Fragment włączany do game.php,
   więc bez <html> i bez session_start(); to robi już game.php.

   Cała logika gry siedzi w api/kasyno_holdem.php. Ta strona tylko
   rysuje stół i oddaje sterowanie do js/kasyno_holdem.js. */
require_once "db.php";

/* Router gier kasyna. Dzięki temu każda nowa gra jest podstroną `kasyno`
   i nie wymaga dopisywania niczego do listy `$dozwolone_strony` w game.php. */
$gra = isset($_GET['gra']) ? preg_replace('/[^a-z_]/', '', (string)$_GET['gra']) : '';
if ($gra !== '' && $gra !== 'holdem') {
  $plik = "pages/kasyno_$gra.php";
  if (file_exists($plik)) { include $plik; return; }
  echo "<p style='color:#ff6678;background:rgba(0,0,0,.8);padding:20px;border:1px solid var(--border-mid);border-radius:2px'>Nie ma takiej gry w kasynie.</p>";
  return;
}

require_once "includes/kasyno_core.php";
$id_gracza = (int)$_SESSION['id_gracza'];
kc_zdejmij_wade($id_gracza);   // tydzień bez gry zdejmuje wadę hazardzisty

$gracz = $polaczenie->query("SELECT login, gotowka, bank, zetony, kasyno_netto, kasyno_rozdania
                             FROM gracze WHERE id = $id_gracza")->fetch_assoc();
$gotowka = (int)$gracz['gotowka'];
$bank    = (int)$gracz['bank'];
$zetony  = (int)$gracz['zetony'];
$majatek = $gotowka + $bank;

$stol = $polaczenie->query("SELECT * FROM kasyno_stoly WHERE gra='holdem' ORDER BY id LIMIT 1")->fetch_assoc();
$prog = (int)($stol['prog_majatku'] ?? 50000);
$wpuszczony = $majatek >= $prog;

$netto = (int)$gracz['kasyno_netto'];
$reputacja = $netto >= 25000 ? 'Rekin stołów' : ($netto <= -25000 ? 'Frajer kasyna' : 'Stały bywalec');

$ranking = $polaczenie->query("SELECT login, netto, rozdania, reputacja FROM v_kasyno_ranking ORDER BY netto DESC LIMIT 8");
$pule    = $polaczenie->query("SELECT nr, pula, board FROM v_kasyno_pule_dnia LIMIT 5");
?>
<link rel="stylesheet" href="css/kasyno_skora.css">

<div class="kh">

  <div class="kh-head">
    <div class="eyebrow">// Złoty Smok · sala gier</div>
    <h1 id="kh-nazwa">Kasyno Golden Dragon</h1>
    <p>Zaryzykuj wszystko. Zdobądź miasto.</p>
  </div>

  <div class="kh-nav">
    <a href="game.php?page=kasyno" class="on">Hold'em</a>
    <a href="game.php?page=kasyno&gra=blackjack">Blackjack</a>
    <a href="game.php?page=kasyno&gra=videopoker">Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty">Sloty</a>
    <a href="game.php?page=kasyno&gra=ruletka">Ruletka</a>
  </div>

<?php if (!$wpuszczony): ?>

  <div class="glass kh-zamkniete">
    <h2>Ochrona zatrzymuje cię przy wejściu</h2>
    <p>Sala gier Złotego Smoka obsługuje wyłącznie majątki od <b><?php echo number_format($prog, 0, '', ' '); ?> $</b>.
       Masz przy sobie <b><?php echo number_format($majatek, 0, '', ' '); ?> $</b> licząc gotówkę i konto w banku.
       Wróć, kiedy będzie cię na to stać.</p>
  </div>

<?php else: ?>

  <div class="glass kh-bar">
    <div class="grp">
      <div>Gotówka<b id="kh-gotowka"><?php echo number_format($gotowka, 0, '', ' '); ?></b></div>
      <div class="zet">Żetony<b id="kh-zetony"><?php echo number_format($zetony, 0, '', ' '); ?></b></div>
      <div>Blindy<b style="color:#fff" id="kh-blindy">—</b></div>
      <div>Wejście<b style="color:#fff" id="kh-wejscie">—</b></div>
    </div>
    <div class="rep"><?php echo $reputacja; ?> · <?php echo (int)$gracz['kasyno_rozdania']; ?> rozdań</div>
  </div>

  <div class="kh-komunikat" id="kh-komunikat"></div>

  <div class="kh-tbl">
    <div class="kh-mid">
      <div class="kh-pot">
        <div class="lbl">Pula</div>
        <div class="val" id="kh-pula">0</div>
        <div class="rake" id="kh-rake">rake 5%</div>
      </div>
      <div class="cards" id="kh-board"></div>
      <div class="kh-faza" id="kh-rozdanie">Ładowanie stołu…</div>
    </div>

    <div class="cards kh-hand" id="kh-moja-reka"></div>

    <div class="kh-s kh-s1" id="kh-m1"></div>
    <div class="kh-s kh-s2" id="kh-m2"></div>
    <div class="kh-s kh-s3" id="kh-m3"></div>
    <div class="kh-s kh-s4" id="kh-m4"></div>
    <div class="kh-s kh-s5" id="kh-m5"></div>
    <div class="kh-s kh-s6" id="kh-m6"></div>
  </div>

  <div class="glass kh-act">
    <div class="kh-timer" id="kh-timer">Łączenie ze stołem…</div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap" id="kh-akcje"></div>
  </div>

  <div class="kh-low">
    <div class="glass kh-chat">
      <div class="ttl">Stół — czat fabularny · <span style="text-transform:none;letter-spacing:0">wpisz <b>/me</b> żeby opisać akcję</span></div>
      <div class="kh-feed" id="kh-feed"></div>
      <form class="kh-say" id="kh-say">
        <input id="kh-tresc" placeholder="Powiedz coś albo /me opisz akcję…" autocomplete="off" maxlength="600">
        <button type="submit">Wyślij</button>
      </form>
    </div>

    <div class="glass kh-side">
      <div class="ttl">Widzowie</div>
      <div id="kh-widzowie"><span style="opacity:.5">—</span></div>

      <div class="ttl">Największe pule dnia</div>
      <table class="kh-tab">
        <?php if ($pule && $pule->num_rows): while ($p = $pule->fetch_assoc()): ?>
          <tr><td>#<?php echo (int)$p['nr']; ?></td><td><?php echo number_format((int)$p['pula'], 0, '', ' '); ?></td></tr>
        <?php endwhile; else: ?>
          <tr><td colspan="2" style="opacity:.5">Dziś jeszcze nikt nie grał</td></tr>
        <?php endif; ?>
      </table>

      <div class="ttl">Ranking kasyna</div>
      <table class="kh-tab">
        <?php if ($ranking && $ranking->num_rows): while ($r = $ranking->fetch_assoc()):
          $n = (int)$r['netto']; ?>
          <tr><td><?php echo htmlspecialchars($r['login']); ?></td>
              <td class="<?php echo $n >= 0 ? 'plus' : 'minus'; ?>"><?php echo ($n > 0 ? '+' : '').number_format($n, 0, '', ' '); ?></td></tr>
        <?php endwhile; else: ?>
          <tr><td colspan="2" style="opacity:.5">Ranking jest jeszcze pusty</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <script src="js/kasyno_holdem.js"></script>
  <script>KasynoHoldem.start(<?php echo (int)$stol['id']; ?>);</script>

<?php endif; ?>

</div>
