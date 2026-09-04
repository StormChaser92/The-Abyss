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
<style>
.kh{--gold:#ffd700;--neon:#00ff00;--red:#ff3333;--cyan:#00ccff;--dim:#888;--glass:rgba(20,20,20,.6);--edge:rgba(255,255,255,.06);width:100%;max-width:1000px;margin:0 auto}
.kh *{box-sizing:border-box}
.kh .glass{background:var(--glass);border:1px solid var(--edge);border-radius:10px;backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);box-shadow:0 10px 40px rgba(0,0,0,.8)}
.kh-head{background:rgba(17,17,17,.7);border:1px solid rgba(255,215,0,.1);border-radius:8px;padding:34px 20px;text-align:center;box-shadow:0 0 50px rgba(0,0,0,.5);margin-bottom:22px}
.kh-head h1{color:var(--gold);font-family:'Oswald',sans-serif;font-size:3em;margin:0;text-shadow:0 0 30px var(--gold);text-transform:uppercase;letter-spacing:2px}
.kh-head p{color:var(--dim);font-size:1.1em;margin:8px 0 0;font-style:italic}
.kh-nav{display:flex;gap:14px;justify-content:center;margin-bottom:24px;flex-wrap:wrap}
.kh-nav a{text-decoration:none;background:rgba(20,20,20,.8);border:1px solid rgba(255,215,0,.3);color:var(--gold);padding:12px 26px;font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;border-radius:4px;transition:.3s}
.kh-nav a.on{background:rgba(255,215,0,.1);border-color:var(--gold);color:#fff;box-shadow:0 0 30px rgba(255,215,0,.3)}
.kh-nav a.soon{opacity:.35;pointer-events:none}
.kh-nav a:hover:not(.on){color:#fff;transform:translateY(-3px)}
/* pasek portfela */
.kh-bar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 20px;margin-bottom:18px;flex-wrap:wrap}
.kh-bar .grp{display:flex;gap:26px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.82em;color:var(--dim)}
.kh-bar .grp b{display:block;font-size:1.5em;letter-spacing:0;color:var(--neon);margin-top:2px}
.kh-bar .grp .zet b{color:var(--gold)}
.kh-bar .rep{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.78em;color:var(--gold);border:1px solid rgba(255,215,0,.35);padding:6px 14px;border-radius:3px}
/* filc */
.kh-tbl{position:relative;height:500px;margin:0 30px;background:radial-gradient(ellipse at 50% 45%,#0e1512 0%,#070a09 60%,#040505 100%);border:2px solid rgba(255,215,0,.22);border-radius:180px/120px;box-shadow:inset 0 0 90px rgba(0,0,0,.95),0 0 50px rgba(255,215,0,.04)}
.kh-mid{position:absolute;left:50%;top:40%;transform:translate(-50%,-50%);display:flex;flex-direction:column;align-items:center;gap:14px;width:100%}
.kh-pot .lbl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:3px;color:var(--dim);font-size:.7em;text-align:center}
.kh-pot .val{font-family:'Oswald',sans-serif;font-weight:700;color:var(--gold);font-size:2.1em;line-height:1;text-shadow:0 0 28px rgba(255,215,0,.4);text-align:center}
.kh-pot .rake{font-size:.7em;color:#5f5f5f;text-align:center;margin-top:3px}
.kh-faza{font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;color:#5f5f5f;font-size:.7em}
.kh .cards{display:flex;gap:8px;justify-content:center}
.kh .cards.mini{gap:4px;margin-top:6px}
.kh .cards.mini .card{width:30px;height:44px;font-size:.78em;padding:4px}
.kh .card{width:54px;height:78px;background:rgba(22,22,22,.96);border:1px solid rgba(255,255,255,.07);border-radius:7px;display:flex;flex-direction:column;justify-content:space-between;padding:6px 7px;font-family:'Times New Roman',serif;font-weight:700;font-size:1.15em;box-shadow:0 6px 18px rgba(0,0,0,.75)}
.kh .card .f{line-height:1}.kh .card .s{text-align:right;font-size:1.5em;line-height:1}
.kh .card.r{color:var(--red);text-shadow:0 0 12px rgba(255,51,51,.45)}
.kh .card.b{color:#eee;text-shadow:0 0 12px rgba(255,255,255,.25)}
.kh .card.big{width:74px;height:106px;font-size:1.55em}
.kh .card.back{background:radial-gradient(circle at center,#1a1206 0%,#070707 100%);border:2px solid rgba(255,215,0,.35)}
.kh-hand{position:absolute;left:50%;bottom:74px;transform:translateX(-50%)}
/* miejsca */
.kh-s{position:absolute;z-index:4}
.kh-s1{left:50%;bottom:-16px;transform:translateX(-50%)}
.kh-s2{left:1%;bottom:78px}
.kh-s3{left:1%;top:78px}
.kh-s4{left:50%;top:-16px;transform:translateX(-50%)}
.kh-s5{right:1%;top:78px}
.kh-s6{right:1%;bottom:78px}
.kh .seat{background:rgba(12,12,12,.86);border:1px solid var(--edge);border-radius:8px;padding:9px 12px;min-width:158px;backdrop-filter:blur(8px);transition:.25s}
.kh .seat .nm{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;color:#fff;font-size:.88em;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.kh .seat .st{font-family:'Oswald',sans-serif;color:var(--neon);font-size:1.05em;margin-top:2px}
.kh .seat .act{font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.68em;letter-spacing:1px;color:var(--dim);margin-top:3px;min-height:1em}
.kh .seat.turn{border-color:var(--gold);box-shadow:0 0 28px rgba(255,215,0,.28)}
.kh .seat.out{opacity:.4}
.kh .seat.free{border-style:dashed;border-color:rgba(255,255,255,.12);color:var(--dim);text-align:center;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.76em;padding:14px 12px;cursor:pointer}
.kh .seat.free:hover{border-color:rgba(255,215,0,.5);color:var(--gold)}
.kh .tag{font-family:'Oswald',sans-serif;font-size:.6em;letter-spacing:1.5px;padding:2px 6px;border-radius:3px;border:1px solid rgba(0,204,255,.45);color:var(--cyan);text-transform:uppercase}
.kh .tag.d{border-color:rgba(255,215,0,.5);color:var(--gold)}
.kh .tag.me{border-color:rgba(0,255,0,.4);color:var(--neon)}
.kh .bet{font-family:'Oswald',sans-serif;color:var(--gold)}
/* akcje */
.kh-act{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 20px;margin-top:18px;flex-wrap:wrap}
.kh .btn{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:1em;padding:12px 22px;border-radius:4px;cursor:pointer;border:1px solid rgba(255,255,255,.14);background:rgba(25,25,25,.85);color:#ddd;transition:.2s}
.kh .btn:hover{color:#fff;border-color:rgba(255,255,255,.3)}
.kh .btn.gold{background:linear-gradient(180deg,var(--gold),#ff8800);color:#000;border:none;font-weight:700;box-shadow:0 6px 22px rgba(255,215,0,.3)}
.kh .btn.gold:hover{background:linear-gradient(180deg,#fff,var(--gold))}
.kh .btn.ghost{background:rgba(0,0,0,.4)}
.kh .kwota{background:rgba(0,0,0,.55);border:1px solid #444;color:var(--neon);font-family:'Oswald',sans-serif;font-size:1.2em;padding:10px 12px;width:120px;text-align:center;border-radius:4px}
.kh-timer{font-family:'Oswald',sans-serif;color:var(--gold);font-size:.85em;letter-spacing:1px;white-space:nowrap}
.kh-timer .bar{display:block;width:120px;height:3px;background:rgba(255,255,255,.1);border-radius:2px;margin-top:5px;overflow:hidden}
.kh-timer .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--gold),#ff8800);transition:width 1s linear}
/* dół */
.kh-low{display:grid;grid-template-columns:1fr 300px;gap:18px;margin-top:18px}
.kh-chat{display:flex;flex-direction:column;height:280px}
.kh-chat .ttl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;color:var(--dim);font-size:.72em;padding:12px 14px 8px;border-bottom:1px solid var(--edge)}
.kh-feed{padding:10px 14px;display:flex;flex-direction:column;gap:8px;overflow-y:auto;flex:1}
.kh .msg{font-size:.87em;line-height:1.5}
.kh .msg .who{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;color:var(--gold);font-size:.85em;margin-right:6px}
.kh .msg.emote{color:#b39ddb;font-style:italic}
.kh .msg.sys{color:#5f6f5f;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.72em}
.kh .msg.mg{color:var(--cyan);border-left:2px solid rgba(0,204,255,.4);padding-left:9px}
.kh-say{display:flex;gap:8px;padding:10px 14px;border-top:1px solid var(--edge)}
.kh-say input{flex:1;background:rgba(0,0,0,.55);border:1px solid rgba(255,255,255,.1);color:var(--neon);font-family:'Open Sans',sans-serif;font-size:.88em;padding:9px 11px;border-radius:4px}
.kh-say input::placeholder{color:#555}
.kh-say button{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px;font-size:.8em;padding:0 15px;border-radius:4px;border:1px solid rgba(255,215,0,.35);background:rgba(255,215,0,.08);color:var(--gold);cursor:pointer}
.kh-side{padding:16px;font-size:.85em}
.kh-side .ttl{font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;color:var(--dim);font-size:.72em;margin-bottom:8px}
.kh-side .ttl+*{margin-bottom:16px}
#kh-widzowie{display:flex;flex-wrap:wrap;gap:6px}
#kh-widzowie span{background:rgba(0,0,0,.5);border:1px solid var(--edge);border-radius:20px;padding:3px 10px;font-size:.85em;color:#aaa}
.kh-tab{width:100%;border-collapse:collapse;font-size:.85em}
.kh-tab td{padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);color:#aaa}
.kh-tab td:last-child{text-align:right;font-family:'Oswald',sans-serif;color:var(--gold)}
.kh-tab .plus{color:var(--neon)}.kh-tab .minus{color:var(--red)}
.kh-komunikat{max-height:0;overflow:hidden;transition:.3s;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1px;border-radius:4px;margin-bottom:0}
.kh-komunikat.widoczny{max-height:60px;padding:13px;margin-bottom:16px;background:rgba(0,255,0,.08);border:1px solid rgba(0,255,0,.3);color:var(--neon)}
.kh-komunikat.widoczny.blad{background:rgba(255,51,51,.1);border-color:var(--red);color:var(--red)}
.kh-zamkniete{padding:50px 40px;text-align:center}
.kh-zamkniete h2{font-family:'Oswald',sans-serif;color:var(--red);text-transform:uppercase;letter-spacing:2px;margin:0 0 14px}
.kh-zamkniete p{color:#aaa;line-height:1.8;max-width:520px;margin:0 auto}
.kh-zamkniete b{color:var(--gold)}
</style>

<div class="kh">

  <div class="kh-head">
    <h1 id="kh-nazwa">Kasyno Golden Dragon</h1>
    <p>Zaryzykuj wszystko. Zdobądź miasto.</p>
  </div>

  <div class="kh-nav">
    <a href="game.php?page=kasyno" class="on">🃏 Hold'em</a>
    <a href="game.php?page=kasyno&gra=videopoker">🎴 Video Poker</a>
    <a href="#" class="soon">🎰 Sloty</a>
    <a href="#" class="soon">🎲 Ruletka</a>
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
