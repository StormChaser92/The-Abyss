<?php
/* the-abyss/pages/kasyno_videopoker.php
   Video Poker — Jacks or Better 8/5 z double-up.
   Fragment włączany do game.php; logika siedzi w api/kasyno_videopoker.php. */
require_once "db.php";
require_once "includes/kasyno_core.php";

$id_gracza = (int)$_SESSION['id_gracza'];
kc_zdejmij_wade($id_gracza);   // tydzień bez gry zdejmuje wadę hazardzisty

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

$ZETON = 100;
$TABELA = [
  'POKER KRÓLEWSKI'   => [250, 500, 750, 1000, 4000],
  'POKER'             => [50,  100, 150, 200,  250],
  'KARETA'            => [25,  50,  75,  100,  125],
  'FULL'              => [8,   16,  24,  32,   40],
  'KOLOR'             => [5,   10,  15,  20,   25],
  'STRIT'             => [4,   8,   12,  16,   20],
  'TRÓJKA'            => [3,   6,   9,   12,   15],
  'DWIE PARY'         => [2,   4,   6,   8,    10],
  'WALETY LUB LEPSZE' => [1,   2,   3,   4,    5],
];

$trafienia = $polaczenie->query("SELECT login, wyplata, uklad FROM v_kasyno_solo_trafienia LIMIT 6");
?>
<link rel="stylesheet" href="css/kasyno_skora.css">

<div class="vp">

  <div class="vp-head">
    <div class="eyebrow">// Złoty Smok · sala gier</div>
    <h1>Video Poker</h1>
    <p>Walety lub lepsze. Podwajaj, dopóki masz nerwy.</p>
  </div>

  <div class="vp-nav">
    <a href="game.php?page=kasyno">Hold'em</a>
    <a href="game.php?page=kasyno&gra=blackjack">Blackjack</a>
    <a href="game.php?page=kasyno&gra=videopoker" class="on">Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty">Sloty</a>
    <a href="game.php?page=kasyno&gra=ruletka">Ruletka</a>
  </div>

<?php if (!$wpuszczony): ?>

  <div class="glass vp-zamkniete">
    <h2>Ochrona zatrzymuje cię przy wejściu</h2>
    <p>Sala gier Złotego Smoka obsługuje wyłącznie majątki od <b><?php echo number_format($prog, 0, '', ' '); ?> $</b>.
       Masz przy sobie <b><?php echo number_format($majatek, 0, '', ' '); ?> $</b> licząc gotówkę i konto w banku.</p>
  </div>

<?php else: ?>

  <div class="glass vp-bar">
    <div class="grp">
      <div>Gotówka<b id="vp-gotowka"><?php echo number_format($gotowka, 0, '', ' '); ?></b></div>
      <div class="zet">Żetony<b id="vp-zetony"><?php echo number_format($zetony, 0, '', ' '); ?></b></div>
      <div>Żeton stawki<b style="color:#fff"><?php echo $ZETON; ?></b></div>
    </div>
    <div class="rep"><?php echo $reputacja; ?> · <?php echo (int)$gracz['kasyno_rozdania']; ?> rozdań</div>
  </div>

  <div class="vp-komunikat" id="vp-komunikat"></div>

  <div class="vp-main">
    <div>
      <div class="vp-felt">
        <div class="vp-uklad" id="vp-uklad">Wybierz stawkę i rozdaj.</div>
        <div id="vp-reka"></div>
        <div id="vp-double" style="display:none"></div>
      </div>
      <div class="glass vp-act" id="vp-akcje"></div>
    </div>

    <div>
      <div class="glass vp-pay">
        <div class="ttl">Tabela wypłat — mnożnik za żeton</div>
        <table id="vp-tabela">
          <tr><th>Układ</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr>
          <?php foreach ($TABELA as $nazwa => $m): ?>
          <tr data-uklad="<?php echo $nazwa; ?>">
            <td><?php echo $nazwa; ?></td>
            <?php foreach ($m as $i => $v): ?>
              <td data-kol="<?php echo $i + 1; ?>"><?php echo $v; ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="glass vp-side" style="margin-top:18px">
        <div class="ttl">Największe trafienia</div>
        <table class="vp-tab">
          <?php if ($trafienia && $trafienia->num_rows): while ($t = $trafienia->fetch_assoc()): ?>
            <tr><td><?php echo htmlspecialchars($t['login']); ?><br><span style="font-size:.8em;opacity:.6"><?php echo htmlspecialchars((string)$t['uklad']); ?></span></td>
                <td><?php echo number_format((int)$t['wyplata'], 0, '', ' '); ?></td></tr>
          <?php endwhile; else: ?>
            <tr><td colspan="2" style="opacity:.5">Jeszcze nikt nic nie trafił</td></tr>
          <?php endif; ?>
        </table>

        <div class="ttl">Zasady</div>
        <div class="vp-zasady">
          Wypłata od pary waletów. Po wygranej możesz podwoić: krupier odkrywa
          kartę, ty wybierasz jedną z czterech. Wyżej podwaja, niżej zabiera
          wszystko, równa daje kolejną próbę. Maksymalnie pięć podwojeń z rzędu.
        </div>
      </div>
    </div>
  </div>

  <script src="js/kasyno_videopoker.js"></script>
  <script>KasynoVideoPoker.start(<?php echo $ZETON; ?>);</script>

<?php endif; ?>

</div>
