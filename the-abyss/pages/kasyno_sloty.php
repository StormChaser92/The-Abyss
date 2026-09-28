<?php
/* the-abyss/pages/kasyno_sloty.php
   Sloty „Złoty Smok" — 5 bębnów, 3 rzędy, 10 linii, jackpot progresywny.
   Włączane przez pages/kasyno.php (game.php?page=kasyno&gra=sloty).
   Logika w api/kasyno_sloty.php. */
require_once "db.php";
require_once "includes/kasyno_core.php";

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

$jp = $polaczenie->query("SELECT pula FROM kasyno_jackpot WHERE id=1")->fetch_assoc();
$pula = (int)($jp['pula'] ?? 0);

$SYM = ['🍒','🍋','🔔','💎','7️⃣','🐉'];
$WYP = [
  5 => [25, 150, 750], 4 => [15, 75, 350], 3 => [10, 40, 200],
  2 => [7, 25, 110],   1 => [4, 16, 70],   0 => [3, 10, 40],
];
$trafienia = $polaczenie->query("SELECT g.login, l.kwota FROM kasyno_jackpot_log l
                                 JOIN gracze g ON g.id = l.gracz_id ORDER BY l.id DESC LIMIT 4");
?>
<link rel="stylesheet" href="css/kasyno_skora.css">

<div class="sl">

  <div class="sl-head">
    <div class="eyebrow">// Złoty Smok · automaty</div>
    <h1>Złoty Smok</h1>
    <p>Pięć bębnów, dziesięć linii, jedna pula dla całego miasta.</p>
  </div>

  <div class="sl-nav">
    <a href="game.php?page=kasyno">Hold'em</a>
    <a href="game.php?page=kasyno&gra=blackjack">Blackjack</a>
    <a href="game.php?page=kasyno&gra=videopoker">Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty" class="on">Sloty</a>
    <a href="game.php?page=kasyno&gra=ruletka">Ruletka</a>
  </div>

<?php if (!$wpuszczony): ?>

  <div class="glass sl-zamkniete">
    <h2>Ochrona zatrzymuje cię przy wejściu</h2>
    <p>Sala gier Złotego Smoka obsługuje wyłącznie majątki od <b><?php echo number_format($prog, 0, '', ' '); ?> $</b>.
       Masz przy sobie <b><?php echo number_format($majatek, 0, '', ' '); ?> $</b> licząc gotówkę i konto w banku.</p>
  </div>

<?php else: ?>

  <div class="glass sl-bar">
    <div class="grp">
      <div>Gotówka<b id="sl-gotowka"><?php echo number_format($gotowka, 0, '', ' '); ?></b></div>
      <div class="zet">Żetony<b id="sl-zetony"><?php echo number_format($zetony, 0, '', ' '); ?></b></div>
    </div>
    <div class="rep"><?php echo $reputacja; ?> · <?php echo (int)$gracz['kasyno_rozdania']; ?> rozdań</div>
  </div>

  <div class="glass sl-jp">
    <div class="lbl">Jackpot</div>
    <div class="val" id="sl-jackpot"><?php echo number_format($pula, 0, '', ' '); ?></div>
    <div class="sub">Pięć smoków 🐉 na bębnach zabiera całą pulę. Rośnie o 1% każdej stawki.</div>
  </div>

  <div class="sl-komunikat" id="sl-komunikat"></div>

  <div class="sl-main">
    <div>
      <div class="sl-maszyna">
        <div class="sl-bebny">
          <div class="sl-beben" id="sl-b0"></div>
          <div class="sl-beben" id="sl-b1"></div>
          <div class="sl-beben" id="sl-b2"></div>
          <div class="sl-beben" id="sl-b3"></div>
          <div class="sl-beben" id="sl-b4"></div>
        </div>
      </div>

      <div class="glass sl-act">
        <div class="sl-stawka-box">
          Stawka na linię
          <div class="sl-stawki" id="sl-stawki"></div>
        </div>
        <button class="btn" id="sl-spin">Kręć</button>
        <div class="sl-razem">Razem za spin<b id="sl-calosc">—</b></div>
      </div>
    </div>

    <div>
      <div class="glass sl-pay">
        <div class="ttl">Wypłaty — krotność stawki na linię</div>
        <table id="sl-tabela">
          <tr><th>Symbol</th><th>3</th><th>4</th><th>5</th></tr>
          <?php foreach (array_reverse($WYP, true) as $sym => $m): ?>
          <tr data-sym="<?php echo $sym; ?>">
            <td><?php echo $SYM[$sym]; ?></td>
            <td><?php echo $m[0]; ?></td><td><?php echo $m[1]; ?></td><td><?php echo $m[2]; ?></td>
          </tr>
          <?php endforeach; ?>
          <tr><td>⚡</td><td colspan="3" style="text-align:right;color:#777">zastępuje każdy symbol</td></tr>
          <tr><td>🌙</td><td colspan="3" style="text-align:right;color:#777">3/4/5 = 2× / 5× / 20× stawki</td></tr>
        </table>
      </div>

      <div class="glass sl-side" style="margin-top:18px">
        <div class="ttl">Ostatnie jackpoty</div>
        <table class="sl-tab">
          <?php if ($trafienia && $trafienia->num_rows): while ($t = $trafienia->fetch_assoc()): ?>
            <tr><td><?php echo htmlspecialchars($t['login']); ?></td>
                <td><?php echo number_format((int)$t['kwota'], 0, '', ' '); ?></td></tr>
          <?php endwhile; else: ?>
            <tr><td colspan="2" style="opacity:.5">Puli jeszcze nikt nie ruszył</td></tr>
          <?php endif; ?>
        </table>

        <div class="ttl">Zasady</div>
        <div class="sl-zasady">
          Linie liczone od lewego bębna. Wygrywa <b>trzy lub więcej</b> tych
          samych symboli pod rząd. Księżyc płaci gdziekolwiek na bębnach.
          <br><br>
          Zwrot dla gracza: <b>89,8%</b> — policzony przez wyliczenie
          wszystkich 7 962 624 kombinacji bębnów. Coś wypada w co drugim
          spinie.
        </div>
      </div>
    </div>
  </div>

  <script src="js/kasyno_sloty.js"></script>
  <script>KasynoSloty.start();</script>

<?php endif; ?>

</div>
