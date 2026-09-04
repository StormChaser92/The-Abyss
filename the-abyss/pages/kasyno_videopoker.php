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
<style>
.vp{--gold:#ffd700;--neon:#00ff00;--red:#ff3333;--cyan:#00ccff;--dim:#888;--glass:rgba(20,20,20,.6);--edge:rgba(255,255,255,.06);width:100%;max-width:1000px;margin:0 auto}
.vp *{box-sizing:border-box}
.vp .glass{background:var(--glass);border:1px solid var(--edge);border-radius:10px;backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);box-shadow:0 10px 40px rgba(0,0,0,.8)}
.vp-head{background:rgba(17,17,17,.7);border:1px solid rgba(255,215,0,.1);border-radius:8px;padding:34px 20px;text-align:center;box-shadow:0 0 50px rgba(0,0,0,.5);margin-bottom:22px}
.vp-head h1{color:var(--gold);font-family:'Oswald',sans-serif;font-size:3em;margin:0;text-shadow:0 0 30px var(--gold);text-transform:uppercase;letter-spacing:2px}
.vp-head p{color:var(--dim);font-size:1.1em;margin:8px 0 0;font-style:italic}
.vp-nav{display:flex;gap:14px;justify-content:center;margin-bottom:24px;flex-wrap:wrap}
.vp-nav a{text-decoration:none;background:rgba(20,20,20,.8);border:1px solid rgba(255,215,0,.3);color:var(--gold);padding:12px 26px;font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;border-radius:4px;transition:.3s}
.vp-nav a.on{background:rgba(255,215,0,.1);border-color:var(--gold);color:#fff;box-shadow:0 0 30px rgba(255,215,0,.3)}
.vp-nav a.soon{opacity:.35;pointer-events:none}
.vp-nav a:hover:not(.on){color:#fff;transform:translateY(-3px)}
.vp-bar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 20px;margin-bottom:18px;flex-wrap:wrap}
.vp-bar .grp{display:flex;gap:26px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.82em;color:var(--dim)}
.vp-bar .grp b{display:block;font-size:1.5em;letter-spacing:0;color:var(--neon);margin-top:2px}
.vp-bar .grp .zet b{color:var(--gold)}
.vp-bar .rep{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.78em;color:var(--gold);border:1px solid rgba(255,215,0,.35);padding:6px 14px;border-radius:3px}
.vp-komunikat{max-height:0;overflow:hidden;transition:.3s;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1px;border-radius:4px}
.vp-komunikat.widoczny{max-height:70px;padding:13px;margin-bottom:16px;background:rgba(255,255,255,.04);border:1px solid var(--edge);color:#bbb}
.vp-komunikat.widoczny.dobry{background:rgba(0,255,0,.08);border-color:rgba(0,255,0,.3);color:var(--neon)}
.vp-komunikat.widoczny.blad{background:rgba(255,51,51,.1);border-color:var(--red);color:var(--red)}
.vp-main{display:grid;grid-template-columns:1fr 290px;gap:18px}
/* tabela wypłat */
.vp-pay{padding:14px 16px}
.vp-pay .ttl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;color:var(--dim);font-size:.72em;margin-bottom:10px}
#vp-tabela{width:100%;border-collapse:collapse;font-size:.78em}
#vp-tabela th{font-family:'Oswald',sans-serif;font-weight:400;color:#666;font-size:.9em;text-align:right;padding:0 0 6px;border-bottom:1px solid var(--edge)}
#vp-tabela th:first-child{text-align:left}
#vp-tabela td{padding:4px 0;color:#999;border-bottom:1px solid rgba(255,255,255,.03);text-align:right;font-family:'Oswald',sans-serif}
#vp-tabela td:first-child{text-align:left;color:#bbb;font-size:.95em;letter-spacing:.5px}
#vp-tabela td.kol-on{color:var(--gold)}
#vp-tabela tr.trafiony td{color:var(--neon);background:rgba(0,255,0,.05)}
#vp-tabela tr.trafiony td:first-child{color:var(--neon)}
/* stół */
.vp-felt{background:radial-gradient(ellipse at 50% 40%,#0e1512 0%,#070a09 60%,#040505 100%);border:2px solid rgba(255,215,0,.22);border-radius:14px;box-shadow:inset 0 0 90px rgba(0,0,0,.95);padding:26px 20px;display:flex;flex-direction:column;align-items:center;gap:20px;min-height:330px;justify-content:center}
.vp-uklad{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.9em;color:var(--dim);text-align:center;min-height:1.4em}
.vp-uklad b{color:#fff}.vp-uklad .zloto{color:var(--gold)}.vp-uklad .pusto{color:#666}
#vp-reka{display:flex;gap:12px;justify-content:center}
.vp-slot{display:flex;flex-direction:column;align-items:center;gap:7px;cursor:pointer}
.vp-slot .vp-hold{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:.62em;color:var(--gold);min-height:1em}
.vp-slot.held .card{border-color:var(--gold);box-shadow:0 0 26px rgba(255,215,0,.35);transform:translateY(-8px)}
.vp .card{width:74px;height:106px;background:rgba(22,22,22,.96);border:1px solid rgba(255,255,255,.07);border-radius:8px;display:flex;flex-direction:column;justify-content:space-between;padding:8px 9px;font-family:'Times New Roman',serif;font-weight:700;font-size:1.55em;box-shadow:0 6px 18px rgba(0,0,0,.75);transition:.2s}
.vp .card .f{line-height:1}.vp .card .s{text-align:right;font-size:1.4em;line-height:1}
.vp .card.r{color:var(--red);text-shadow:0 0 12px rgba(255,51,51,.45)}
.vp .card.b{color:#eee;text-shadow:0 0 12px rgba(255,255,255,.25)}
.vp .card.back{background:radial-gradient(circle at center,#1a1206 0%,#070707 100%);border:2px solid rgba(255,215,0,.35)}
.vp .card.dim{opacity:.35;transform:scale(.82)}
.vp .card.big{width:80px;height:114px}
.vp .card.pick{cursor:pointer}
.vp .card.pick:hover{border-color:var(--gold);box-shadow:0 0 30px rgba(255,215,0,.45);transform:translateY(-6px)}
.vp .card.chosen{border-color:var(--neon);box-shadow:0 0 30px rgba(0,255,0,.4)}
/* double-up */
#vp-double{display:flex;flex-direction:column;align-items:center;gap:14px;width:100%;padding-top:6px;border-top:1px solid rgba(255,215,0,.12)}
.vp-dbl-row{display:flex;gap:12px;align-items:center;justify-content:center}
.vp-dbl-lbl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.7em;color:var(--dim);margin-right:6px}
.vp-dbl-mid{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.7em;color:var(--gold);text-align:center}
/* akcje */
.vp-act{display:flex;align-items:center;justify-content:center;gap:16px;padding:16px 20px;margin-top:18px;flex-wrap:wrap}
.vp .btn{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:1em;padding:13px 26px;border-radius:4px;cursor:pointer;border:1px solid rgba(255,255,255,.14);background:rgba(25,25,25,.85);color:#ddd;transition:.2s}
.vp .btn:hover{color:#fff;border-color:rgba(255,255,255,.3)}
.vp .btn.gold{background:linear-gradient(180deg,var(--gold),#ff8800);color:#000;border:none;font-weight:700;box-shadow:0 6px 22px rgba(255,215,0,.3)}
.vp .btn.gold:hover{background:linear-gradient(180deg,#fff,var(--gold))}
.vp-zetony{display:flex;gap:6px}
.vp-z{font-family:'Oswald',sans-serif;font-size:1em;width:40px;height:44px;border-radius:4px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.5);color:#999;cursor:pointer;transition:.2s}
.vp-z:hover{color:#fff;border-color:rgba(255,215,0,.4)}
.vp-z.on{background:rgba(255,215,0,.12);border-color:var(--gold);color:var(--gold)}
.vp-stawka{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.78em;color:var(--dim)}
.vp-side{padding:16px;font-size:.85em}
.vp-side .ttl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;color:var(--dim);font-size:.72em;margin-bottom:8px}
.vp-tab{width:100%;border-collapse:collapse;font-size:.85em;margin-bottom:16px}
.vp-tab td{padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);color:#aaa}
.vp-tab td:last-child{text-align:right;font-family:'Oswald',sans-serif;color:var(--gold)}
.vp-zasady{font-size:.78em;color:#5a5a5a;line-height:1.7}
.vp-zamkniete{padding:50px 40px;text-align:center}
.vp-zamkniete h2{font-family:'Oswald',sans-serif;color:var(--red);text-transform:uppercase;letter-spacing:2px;margin:0 0 14px}
.vp-zamkniete p{color:#aaa;line-height:1.8;max-width:520px;margin:0 auto}
.vp-zamkniete b{color:var(--gold)}
@media(max-width:820px){.vp-main{grid-template-columns:1fr}}
</style>

<div class="vp">

  <div class="vp-head">
    <h1>Video Poker</h1>
    <p>Walety lub lepsze. Podwajaj, dopóki masz nerwy.</p>
  </div>

  <div class="vp-nav">
    <a href="game.php?page=kasyno">🃏 Hold'em</a>
    <a href="game.php?page=kasyno&gra=blackjack">🂡 Blackjack</a>
    <a href="game.php?page=kasyno&gra=videopoker" class="on">🎴 Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty">🎰 Sloty</a>
    <a href="game.php?page=kasyno&gra=ruletka">🎲 Ruletka</a>
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
