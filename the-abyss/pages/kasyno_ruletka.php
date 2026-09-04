<?php
/* the-abyss/pages/kasyno_ruletka.php
   Ruletka europejska — jedno zero, pełny stół zakładów (156 typów).

   Skóra zgodna z resztą gry: krwista czerwień `--neon-red`, szkło,
   narożne klamry, Oswald w nagłówkach, JetBrains Mono w liczbach.
   Styl w css/kasyno_ruletka.css, logika w api/kasyno_ruletka.php,
   zasady stołu w includes/ruletka_stol.php. */
require_once "db.php";
require_once "includes/kasyno_core.php";
require_once "includes/ruletka_stol.php";

$id_gracza = (int)$_SESSION['id_gracza'];
kc_zdejmij_wade($id_gracza);

$gracz = $polaczenie->query("SELECT login, gotowka, bank, zetony, kasyno_netto, kasyno_rozdania
                             FROM gracze WHERE id = $id_gracza")->fetch_assoc();
$gotowka = (int)$gracz['gotowka'];
$bank    = (int)$gracz['bank'];
$zetony  = (int)$gracz['zetony'];
$majatek = $gotowka + $bank;

$prog = (int)($polaczenie->query("SELECT MIN(prog_majatku) p FROM kasyno_stoly")->fetch_assoc()['p'] ?? 50000);
$wpuszczony = $majatek >= $prog;

$netto = (int)$gracz['kasyno_netto'];
$reputacja = $netto >= 25000 ? 'Rekin stołów' : ($netto <= -25000 ? 'Frajer kasyna' : 'Stały bywalec');
?>
<link rel="stylesheet" href="css/kasyno_ruletka.css">

<div class="rl">

  <div class="rl-head">
    <div class="eyebrow">// Złoty Smok · sala gier</div>
    <h1>Ruletka</h1>
    <div class="lead">Jedno zero. Trzydzieści siedem kieszeni. Żadnych drugich szans.</div>
  </div>

  <div class="rl-nav">
    <a href="game.php?page=kasyno">Hold'em</a>
    <a href="game.php?page=kasyno&gra=blackjack">Blackjack</a>
    <a href="game.php?page=kasyno&gra=videopoker">Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty">Sloty</a>
    <a href="game.php?page=kasyno&gra=ruletka" class="on">Ruletka</a>
  </div>

<?php if (!$wpuszczony): ?>

  <div class="rl-panel rl-zamkniete">
    <h2>Ochrona zatrzymuje cię przy wejściu</h2>
    <p>Sala gier obsługuje wyłącznie majątki od <b><?php echo number_format($prog, 0, '', ' '); ?> $</b>.
       Masz przy sobie <b><?php echo number_format($majatek, 0, '', ' '); ?> $</b> licząc gotówkę i konto w banku.</p>
  </div>

<?php else: ?>

  <div class="rl-panel rl-bar">
    <div class="grp">
      <div class="kw cash">Gotówka<b id="rl-gotowka"><?php echo number_format($gotowka, 0, '', ' '); ?></b></div>
      <div class="kw zet">Żetony<b id="rl-zetony"><?php echo number_format($zetony, 0, '', ' '); ?></b></div>
    </div>
    <div class="rep"><?php echo $reputacja; ?> · <?php echo (int)$gracz['kasyno_rozdania']; ?> rozdań</div>
  </div>

  <div class="rl-gora">
    <div class="rl-panel rl-kolo-box">
      <div class="rl-tarcza-wrap">
        <div class="rl-wskaz"></div>
        <div id="rl-tarcza"><canvas id="rl-canvas"></canvas></div>
        <div id="rl-kulka"></div>
      </div>
      <div class="rl-limity">
        Minimum <b><?php echo RL_MIN_ZAKLAD; ?></b> · numer do <b><?php echo number_format(RL_MAX_NUMER, 0, '', ' '); ?></b> ·
        zewnętrzne do <b><?php echo number_format(RL_MAX_ZEWN, 0, '', ' '); ?></b><br>
        Zwrot dla gracza <b>97,3%</b> — przewaga kasyna to samo zero
      </div>
    </div>

    <div class="rl-prawo">
      <div class="rl-panel rl-wynik" id="rl-wynik">
        <div class="rl-wynik-krag" id="rl-wynik-nr">—</div>
        <div class="rl-dealer" id="rl-dealer">Zakłady proszę.</div>
      </div>

      <div class="rl-panel rl-hist">
        <div class="rl-tyt" style="padding-left:0">Ostatnie liczby</div>
        <div class="rl-hist-row" id="rl-historia"></div>
        <div class="rl-tyt" style="padding-left:0;margin-top:14px">Gorące numery · doba</div>
        <div class="rl-hist-row" id="rl-gorace"></div>
      </div>
    </div>
  </div>

  <div id="rl-stol">
    <div class="rl-filc">
      <div class="rl-plansza">
        <div id="rl-ulice"></div>
        <div id="rl-zero"></div>
        <div class="rl-numery-wrap">
          <div id="rl-numery"></div>
          <div id="rl-splity"></div>
        </div>
        <div id="rl-kolumny"></div>
        <div id="rl-tuziny"></div>
        <div id="rl-proste"></div>
      </div>
      <div id="rl-podpowiedz"></div>
    </div>
  </div>

  <div class="rl-panel rl-ster">
    <div class="rl-nom-box">
      <div class="lbl">Nominał żetonu</div>
      <div id="rl-nominaly"></div>
    </div>
    <div class="rl-btns">
      <button class="rl-b" id="rl-cofnij" title="Klawisz Z">Cofnij</button>
      <button class="rl-b" id="rl-wyczysc">Zgarnij</button>
      <button class="rl-b" id="rl-powtorz">Powtórz</button>
      <button class="rl-b duzy" id="rl-spin">Kręć</button>
    </div>
    <div class="rl-suma-box">Na stole<b id="rl-suma">0</b></div>
  </div>

  <script src="js/kasyno_ruletka_kolo.js"></script>
  <script src="js/kasyno_ruletka.js"></script>
  <script>KasynoRuletka.start();</script>

<?php endif; ?>

</div>
