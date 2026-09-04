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
<style>
.sl{--gold:#ffd700;--neon:#00ff00;--red:#ff3333;--cyan:#00ccff;--dim:#888;--glass:rgba(20,20,20,.6);--edge:rgba(255,255,255,.06);width:100%;max-width:1000px;margin:0 auto}
.sl *{box-sizing:border-box}
.sl .glass{background:var(--glass);border:1px solid var(--edge);border-radius:10px;backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);box-shadow:0 10px 40px rgba(0,0,0,.8)}
.sl-head{background:rgba(17,17,17,.7);border:1px solid rgba(255,215,0,.1);border-radius:8px;padding:34px 20px;text-align:center;box-shadow:0 0 50px rgba(0,0,0,.5);margin-bottom:22px}
.sl-head h1{color:var(--gold);font-family:'Oswald',sans-serif;font-size:3em;margin:0;text-shadow:0 0 30px var(--gold);text-transform:uppercase;letter-spacing:2px}
.sl-head p{color:var(--dim);font-size:1.1em;margin:8px 0 0;font-style:italic}
.sl-nav{display:flex;gap:14px;justify-content:center;margin-bottom:24px;flex-wrap:wrap}
.sl-nav a{text-decoration:none;background:rgba(20,20,20,.8);border:1px solid rgba(255,215,0,.3);color:var(--gold);padding:12px 26px;font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;border-radius:4px;transition:.3s}
.sl-nav a.on{background:rgba(255,215,0,.1);border-color:var(--gold);color:#fff;box-shadow:0 0 30px rgba(255,215,0,.3)}
.sl-nav a.soon{opacity:.35;pointer-events:none}
.sl-nav a:hover:not(.on){color:#fff;transform:translateY(-3px)}
.sl-bar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 20px;margin-bottom:18px;flex-wrap:wrap}
.sl-bar .grp{display:flex;gap:26px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.82em;color:var(--dim)}
.sl-bar .grp b{display:block;font-size:1.5em;letter-spacing:0;color:var(--neon);margin-top:2px}
.sl-bar .grp .zet b{color:var(--gold)}
.sl-bar .rep{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.78em;color:var(--gold);border:1px solid rgba(255,215,0,.35);padding:6px 14px;border-radius:3px}
/* jackpot */
.sl-jp{text-align:center;padding:16px 20px;margin-bottom:18px;border:1px solid rgba(255,215,0,.3);background:linear-gradient(180deg,rgba(255,215,0,.07),rgba(0,0,0,.4))}
.sl-jp .lbl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:4px;color:var(--dim);font-size:.72em}
.sl-jp .val{font-family:'Oswald',sans-serif;font-weight:700;color:var(--gold);font-size:2.6em;line-height:1.1;text-shadow:0 0 34px rgba(255,215,0,.5)}
.sl-jp .sub{font-size:.76em;color:#6a6a6a;margin-top:4px}
.sl-komunikat{max-height:0;overflow:hidden;transition:.3s;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1px;border-radius:4px}
.sl-komunikat.widoczny{max-height:80px;padding:14px;margin-bottom:16px;background:rgba(255,255,255,.04);border:1px solid var(--edge);color:#bbb}
.sl-komunikat.widoczny.dobry{background:rgba(0,255,0,.08);border-color:rgba(0,255,0,.3);color:var(--neon)}
.sl-komunikat.widoczny.jackpot{background:rgba(255,215,0,.12);border-color:var(--gold);color:var(--gold);font-size:1.25em}
.sl-komunikat.widoczny.blad{background:rgba(255,51,51,.1);border-color:var(--red);color:var(--red)}
.sl-main{display:grid;grid-template-columns:1fr 270px;gap:18px}
/* maszyna */
.sl-maszyna{background:radial-gradient(ellipse at 50% 30%,#141008 0%,#0a0a08 55%,#050505 100%);border:2px solid rgba(255,215,0,.28);border-radius:12px;box-shadow:inset 0 0 80px rgba(0,0,0,.9),0 0 40px rgba(255,215,0,.06);padding:20px}
.sl-bebny{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
.sl-beben{display:flex;flex-direction:column;gap:6px;background:rgba(0,0,0,.55);border:1px solid rgba(255,215,0,.12);border-radius:8px;padding:7px}
.sl-cela{height:76px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.025);border-radius:5px;font-size:2.4em;line-height:1;transition:background .25s,box-shadow .25s}
.sl-beben.kreci .sl-cela{opacity:.5;filter:blur(2.5px)}
.sl-cela.win{background:rgba(0,255,0,.14);box-shadow:inset 0 0 22px rgba(0,255,0,.3)}
.sl-cela.scat{background:rgba(0,204,255,.14);box-shadow:inset 0 0 22px rgba(0,204,255,.35)}
/* sterowanie */
.sl-act{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 20px;margin-top:18px;flex-wrap:wrap}
.sl-stawka-box{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.75em;color:var(--dim)}
.sl-stawki{display:flex;gap:6px;margin-top:6px}
.sl-s{font-family:'Oswald',sans-serif;font-size:.92em;padding:8px 13px;border-radius:4px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.5);color:#999;cursor:pointer;transition:.2s}
.sl-s:hover{color:#fff;border-color:rgba(255,215,0,.4)}
.sl-s.on{background:rgba(255,215,0,.12);border-color:var(--gold);color:var(--gold)}
.sl-razem{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.75em;color:var(--dim);text-align:right}
.sl-razem b{display:block;font-size:1.7em;color:#fff;letter-spacing:0;margin-top:2px}
.sl .btn{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:1.15em;padding:16px 40px;border-radius:4px;cursor:pointer;border:none;background:linear-gradient(180deg,var(--gold),#ff8800);color:#000;font-weight:700;box-shadow:0 6px 22px rgba(255,215,0,.3);transition:.2s}
.sl .btn:hover:not(:disabled){background:linear-gradient(180deg,#fff,var(--gold))}
.sl .btn:disabled{opacity:.45;cursor:default}
/* tabela */
.sl-pay{padding:14px 16px}
.sl-pay .ttl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;color:var(--dim);font-size:.72em;margin-bottom:10px}
#sl-tabela{width:100%;border-collapse:collapse;font-size:.8em}
#sl-tabela th{font-family:'Oswald',sans-serif;font-weight:400;color:#666;font-size:.9em;text-align:right;padding:0 0 6px;border-bottom:1px solid var(--edge)}
#sl-tabela th:first-child{text-align:left}
#sl-tabela td{padding:5px 0;color:#999;border-bottom:1px solid rgba(255,255,255,.03);text-align:right;font-family:'Oswald',sans-serif}
#sl-tabela td:first-child{text-align:left;font-size:1.4em}
#sl-tabela tr.trafiony td{color:var(--neon);background:rgba(0,255,0,.05)}
.sl-side{padding:16px;font-size:.85em}
.sl-side .ttl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;color:var(--dim);font-size:.72em;margin-bottom:8px}
.sl-tab{width:100%;border-collapse:collapse;font-size:.85em;margin-bottom:16px}
.sl-tab td{padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);color:#aaa}
.sl-tab td:last-child{text-align:right;font-family:'Oswald',sans-serif;color:var(--gold)}
.sl-zasady{font-size:.78em;color:#5a5a5a;line-height:1.7}
.sl-zasady b{color:#8a8a8a;font-weight:600}
.sl-zamkniete{padding:50px 40px;text-align:center}
.sl-zamkniete h2{font-family:'Oswald',sans-serif;color:var(--red);text-transform:uppercase;letter-spacing:2px;margin:0 0 14px}
.sl-zamkniete p{color:#aaa;line-height:1.8;max-width:520px;margin:0 auto}
.sl-zamkniete b{color:var(--gold)}
@media(max-width:820px){.sl-main{grid-template-columns:1fr}.sl-cela{height:58px;font-size:1.9em}}
</style>

<div class="sl">

  <div class="sl-head">
    <h1>Złoty Smok</h1>
    <p>Pięć bębnów, dziesięć linii, jedna pula dla całego miasta.</p>
  </div>

  <div class="sl-nav">
    <a href="game.php?page=kasyno">🃏 Hold'em</a>
    <a href="game.php?page=kasyno&gra=videopoker">🎴 Video Poker</a>
    <a href="game.php?page=kasyno&gra=sloty" class="on">🎰 Sloty</a>
    <a href="#" class="soon">🎲 Ruletka</a>
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
