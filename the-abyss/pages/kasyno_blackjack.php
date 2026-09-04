<?php
/* the-abyss/pages/kasyno_blackjack.php
   Blackjack — 6 talii, krupier dobiera na miękkie 17, BJ płaci 3:2.

   Skóra jak ruletka: krwista czerwień, szkło, Oswald w nagłówkach,
   JetBrains Mono w liczbach. Zasady w includes/blackjack_stol.php,
   logika rundy w api/kasyno_blackjack.php. */
require_once "db.php";
require_once "includes/kasyno_core.php";
require_once "includes/blackjack_stol.php";

$id_gracza = (int)$_SESSION['id_gracza'];
kc_zdejmij_wade($id_gracza);

$gracz = $polaczenie->query("SELECT login, gotowka, bank, zetony, kasyno_netto, kasyno_rozdania
                             FROM gracze WHERE id = $id_gracza")->fetch_assoc();
$gotowka = (int)$gracz['gotowka'];
$zetony  = (int)$gracz['zetony'];
$majatek = $gotowka + (int)$gracz['bank'];

$prog = (int)($polaczenie->query("SELECT MIN(prog_majatku) p FROM kasyno_stoly")->fetch_assoc()['p'] ?? 50000);
$wpuszczony = $majatek >= $prog;

$netto = (int)$gracz['kasyno_netto'];
$reputacja = $netto >= 25000 ? 'Rekin stołów' : ($netto <= -25000 ? 'Frajer kasyna' : 'Stały bywalec');
?>
<link rel="stylesheet" href="css/kasyno_blackjack.css">

<div class="bj">

  <div class="bj-head">
    <div class="eyebrow">// Złoty Smok · sala gier</div>
    <h1>Blackjack</h1>
    <div class="lead">Sześć talii w bucie. Krupier dobiera na miękkie 17. Reszta zależy od ciebie.</div>
  </div>

  <div class="bj-nav">
    <a href="game.php?page=kasyno">Hold'em</a>
    <a href="game.php?page=kasyno&gra=blackjack" class="on">Blackjack</a>
    <a href="game.php?page=kasyno&gra=videopoker">Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty">Sloty</a>
    <a href="game.php?page=kasyno&gra=ruletka">Ruletka</a>
  </div>

<?php if (!$wpuszczony): ?>

  <div class="bj-panel bj-zamkniete">
    <h2>Ochrona zatrzymuje cię przy wejściu</h2>
    <p>Sala gier obsługuje wyłącznie majątki od <b><?php echo number_format($prog, 0, '', ' '); ?> $</b>.
       Masz przy sobie <b><?php echo number_format($majatek, 0, '', ' '); ?> $</b> licząc gotówkę i konto w banku.</p>
  </div>

<?php else: ?>

  <div class="bj-panel bj-bar">
    <div class="grp">
      <div class="kw cash">Gotówka<b id="bj-gotowka"><?php echo number_format($gotowka, 0, '', ' '); ?></b></div>
      <div class="kw zet">Żetony<b id="bj-zetony"><?php echo number_format($zetony, 0, '', ' '); ?></b></div>
    </div>
    <div class="rep"><?php echo $reputacja; ?> · <?php echo (int)$gracz['kasyno_rozdania']; ?> rozdań</div>
  </div>

  <div class="bj-uklad">
    <div>
      <div class="bj-stol">
        <div class="bj-filc">
          <div class="bj-luk"></div>
          <div class="bj-zasady">Blackjack płaci 3 do 2<br>Ubezpieczenie płaci 2 do 1<br>Krupier dobiera na miękkie 17</div>

          <div class="bj-rzad">
            <div class="bj-etyk">Krupier <span class="bj-pkt" id="bj-dealer-pkt">—</span></div>
            <div class="bj-karty" id="bj-dealer"></div>
          </div>

          <div class="bj-rzad">
            <div class="bj-rece" id="bj-rece"></div>
            <div class="bj-etyk" id="bj-etyk-gracz"><?php echo htmlspecialchars($gracz['login']); ?></div>
          </div>
        </div>
        <div class="bj-pod" id="bj-podpowiedz"></div>
      </div>

      <div class="bj-panel bj-mowa" id="bj-mowa">
        <span class="znacznik">Krupier</span>
        <span id="bj-mowa-tresc">Zakład proszę. Minimum <?php echo BJ_MIN; ?> żetonów.</span>
      </div>

      <div class="bj-panel bj-ster">
        <div>
          <div class="bj-tyt">Nominał</div>
          <div class="bj-nom" id="bj-nominaly"></div>
        </div>
        <div class="bj-btns" id="bj-btns"></div>
        <div class="bj-stawka-box">Stawka<b id="bj-stawka">0</b></div>
      </div>
    </div>

    <div class="bj-bok">
      <div class="bj-panel box">
        <div class="bj-tyt">But</div>
        <div class="bj-but-pasek"><i id="bj-but-pasek" style="width:100%"></i></div>
        <div class="bj-but-info" id="bj-but-info">—</div>
        <label class="bj-przel"><input type="checkbox" id="bj-licznik"> licznik kart (Hi-Lo)</label>
        <button class="bj-b zloty" id="bj-tasuj" style="width:100%;margin-top:10px">Nowy but</button>
      </div>

      <div class="bj-panel box">
        <div class="bj-tyt">Ostatnie rozdania</div>
        <div class="bj-hist" id="bj-historia"><span class="bj-puste">jeszcze nic</span></div>
      </div>

      <div class="bj-panel box">
        <div class="bj-tyt">Stół</div>
        <div class="bj-zasady-lista">
          <b><?php echo BJ_TALIE; ?> talie</b>, tasowanie po zejściu 75% buta<br>
          Blackjack <b>3:2</b>, ubezpieczenie <b>2:1</b><br>
          Podwojenie na dowolne dwie karty, także po splicie<br>
          Split do <b><?php echo BJ_MAX_RAK; ?> rąk</b>, dzielone asy dostają po jednej karcie<br>
          Limity <b><?php echo number_format(BJ_MIN, 0, '', ' '); ?>–<?php echo number_format(BJ_MAX, 0, '', ' '); ?></b> żetonów
        </div>
      </div>
    </div>
  </div>

  <script src="js/kasyno_blackjack.js"></script>
  <script>KasynoBlackjack.start();</script>

<?php endif; ?>

</div>
