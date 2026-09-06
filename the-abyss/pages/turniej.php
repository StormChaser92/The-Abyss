<?php
/* the-abyss/pages/turniej.php
   Turniej gangów — arena PvP wszystkich przeciw wszystkim, widok na żywo.
   Wejście: game.php?page=turniej (najbliższy turniej) albo &id=N.

   Logika w includes/turniej.php, odświeżanie przez api/kasyno_turniej.php. */
require_once "db.php";
require_once "config/miasta.php";
require_once "config/mg.php";
require_once "includes/turniej.php";

$id_gracza = (int)$_SESSION['id_gracza'];
$komunikat = "";

$gracz = $polaczenie->query("SELECT id, login, syndykat_id FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$jestem_mg = czy_mg($gracz['login']);

/* ── panel Mistrza Gry ─────────────────────────────────────────────── */
if ($jestem_mg && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mg'])) {
    $akcja = $_POST['mg'];

    if ($akcja === 'ogloszenie') {
        $nazwa  = $polaczenie->real_escape_string(trim($_POST['nazwa'] ?? 'Turniej gangów'));
        $miasto = $polaczenie->real_escape_string($_POST['miasto'] ?? 'NEW YORK');
        $kraj   = $polaczenie->real_escape_string($MIASTA_DANE[$_POST['miasto'] ?? '']['kraj'] ?? 'USA');
        $arena  = $polaczenie->real_escape_string(trim($_POST['arena'] ?? 'Arena turniejowa'));
        $kiedy  = $polaczenie->real_escape_string($_POST['poczatek'] ?? date('Y-m-d H:i', time() + 86400));
        $wpis   = max(0, (int)($_POST['wpisowe'] ?? 500000));
        $mg     = $polaczenie->real_escape_string($gracz['login']);
        $era    = boss_era($polaczenie);
        $eid    = $era ? (int)$era['id'] : null;

        $polaczenie->query("INSERT INTO turnieje (era_id, nazwa, kraj, miasto, arena, stan, poczatek, wpisowe, mg_login)
            VALUES (".($eid ?: 'NULL').", '$nazwa', '$kraj', '$miasto', '$arena', 'zapisy', '$kiedy', $wpis, '$mg')");
        $nid = (int)$polaczenie->insert_id;
        $polaczenie->query("INSERT INTO wydarzenia (era_id, rodzaj, tytul, tresc, kiedy) VALUES
            (".($eid ?: 'NULL').", 'walka_gangow', 'Turniej: $nazwa',
             'Wszyscy przeciw wszystkim na $arena. Zapisy otwarte, wpisowe ".number_format($wpis, 0, '', ' ')." \$ ze skarbca gangu.', '$kiedy')");
        $komunikat = "<div class='tn-alert ok'>Turniej ogłoszony i wywieszony w wydarzeniach.</div>";
        $_GET['id'] = $nid;

    } elseif ($akcja === 'start') {
        $t = tur_turniej($polaczenie, (int)$_POST['turniej_id']);
        if ($t) {
            [$ok, $tekst] = tur_start($polaczenie, $t);
            $komunikat = "<div class='tn-alert ".($ok ? 'ok' : 'err')."'>".htmlspecialchars($tekst)."</div>";
        }
    } elseif ($akcja === 'odwolaj') {
        $tid = (int)$_POST['turniej_id'];
        $polaczenie->query("UPDATE turnieje SET stan='odwolany', aktywny_id=NULL, ruch_do=NULL WHERE id=$tid");
        $komunikat = "<div class='tn-alert ok'>Turniej odwołany.</div>";

    } elseif ($akcja === 'tytul') {
        $cel   = (int)($_POST['cel_syndykat'] ?? 0);
        $tytul = $polaczenie->real_escape_string(trim($_POST['tytul'] ?? ''));
        $powod = $polaczenie->real_escape_string(trim($_POST['powod'] ?? 'Turniej gangów'));
        $mg    = $polaczenie->real_escape_string($gracz['login']);
        $era   = boss_era($polaczenie);
        $eid   = $era ? (int)$era['id'] : 0;
        if ($cel > 0 && $tytul !== '') {
            $polaczenie->query("UPDATE syndykaty SET tytul='$tytul' WHERE id=$cel");
            $polaczenie->query("INSERT INTO tytuly (era_id, rodzaj, syndykat_id, tytul, powod, nadal)
                                VALUES ($eid, 'gang', $cel, '$tytul', '$powod', '$mg')");
            $komunikat = "<div class='tn-alert ok'>Tytuł nadany.</div>";
        }
    }
}

$turniej = isset($_GET['id'])
    ? tur_turniej($polaczenie, (int)$_GET['id'])
    : tur_biezacy($polaczenie);

if (!$turniej) {
    $ost = $polaczenie->query("SELECT * FROM turnieje WHERE stan='zakonczony' ORDER BY id DESC LIMIT 1");
    $turniej = $ost && $ost->num_rows ? $ost->fetch_assoc() : null;
}
?>
<link rel="stylesheet" href="css/kasyno_turniej.css">

<div class="tn">

  <div class="tn-head">
    <div class="eyebrow">// Walki gangów · wstęp wolny, wyjście własnym chodem</div>
    <h1><?php echo $turniej ? htmlspecialchars($turniej['nazwa']) : 'Turniej gangów'; ?></h1>
    <div class="lead">
      <?php if ($turniej): ?>
        <?php echo htmlspecialchars($turniej['arena']); ?> ·
        <?php echo htmlspecialchars($turniej['miasto']); ?> ·
        <?php echo date('j.m.Y, H:i', strtotime($turniej['poczatek'])); ?>
      <?php else: ?>
        Nic nie jest ogłoszone. Termin wyznacza Mistrz Gry.
      <?php endif; ?>
    </div>
  </div>

  <?php echo $komunikat; ?>

<?php if (!$turniej): ?>

  <div class="tn-panel tn-pusto">
    <h2>Piasek jest pusty</h2>
    <p>Walki gangów odbywają się o godzinie ogłoszonej wcześniej — na sesji albo w wydarzeniach.
       Kiedy termin będzie znany, arena pojawi się tutaj razem z listą zawodników i kasą bukmachera.</p>
  </div>

<?php else: ?>

  <div class="tn-pasek tn-panel">
    <div class="grp">
      <div class="kw">Stan<b class="tn-stan" id="tn-stan">—</b></div>
      <div class="kw">Runda<b id="tn-tura">—</b></div>
      <div class="kw">Pula wpisowego<b class="kasa" id="tn-pula">—</b></div>
      <div class="kw">Twoje żetony<b class="zet" id="tn-zetony">—</b></div>
    </div>
  </div>

  <div class="tn-uklad">
    <div class="tn-kol">
      <div class="tn-panel">
        <div class="tn-tyt">Na piasku</div>
        <div class="tn-zawodnicy" id="tn-zawodnicy"></div>
        <div class="tn-komunikat" id="tn-komunikat"></div>
        <div class="tn-akcje" id="tn-akcje"></div>
        <p class="nota">Kolejność tur ustala zręczność. Masz <?php echo TUR_SEKUND; ?> sekund na ruch —
           kto nie klika, ten odruchowo się kryje. Unik zdejmuje napastnikowi
           <?php echo TUR_UNIK_PP; ?> punktów procentowych celności, przeczekanie oddaje
           <?php echo (int)round(TUR_REGEN * 100); ?>% maksymalnego HP.</p>
      </div>

      <div class="tn-panel">
        <div class="tn-tyt">Przebieg</div>
        <div class="tn-log" id="tn-log"></div>
      </div>
    </div>

    <div class="tn-bok">
      <div class="tn-panel box">
        <div class="tn-tyt">Bukmacher</div>
        <div id="tn-zaklady"></div>
      </div>

      <div class="tn-panel box">
        <div class="tn-tyt">Trybuny</div>
        <div class="tn-czat" id="tn-czat"></div>
        <div class="tn-czat-form">
          <input type="text" id="tn-czat-input" maxlength="240" placeholder="powiedz coś głośno">
          <button class="tn-b" id="tn-czat-wyslij">Wyślij</button>
        </div>
      </div>

      <?php if ($jestem_mg): ?>
      <div class="tn-panel box tn-mg">
        <div class="tn-tyt">Panel Mistrza Gry</div>

        <form method="POST" class="tn-mg-form">
          <input type="hidden" name="mg" value="ogloszenie">
          <label>Nowy turniej</label>
          <input type="text" name="nazwa" placeholder="Nazwa turnieju" maxlength="90" required>
          <select name="miasto">
            <?php foreach ($MIASTA_DANE as $klucz => $m): ?>
              <option value="<?php echo htmlspecialchars($klucz); ?>"><?php echo htmlspecialchars($m['flaga'].' '.$klucz); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="arena" placeholder="Nazwa areny" maxlength="90" required>
          <input type="datetime-local" name="poczatek" required>
          <input type="number" name="wpisowe" value="500000" step="50000" min="0">
          <button type="submit" class="tn-b">Ogłoś i wywieś</button>
        </form>

        <form method="POST" class="tn-mg-form">
          <input type="hidden" name="mg" value="start">
          <input type="hidden" name="turniej_id" value="<?php echo (int)$turniej['id']; ?>">
          <button type="submit" class="tn-b">Pierwszy dzwonek</button>
        </form>

        <form method="POST" class="tn-mg-form">
          <input type="hidden" name="mg" value="tytul">
          <label>Tytuł dla gangu</label>
          <select name="cel_syndykat" required>
            <option value="">— syndykat —</option>
            <?php
              $sy = $polaczenie->query("SELECT id, nazwa, tag FROM syndykaty ORDER BY nazwa ASC");
              while ($sy && ($s = $sy->fetch_assoc())):
            ?>
              <option value="<?php echo (int)$s['id']; ?>">[<?php echo htmlspecialchars($s['tag']); ?>] <?php echo htmlspecialchars($s['nazwa']); ?></option>
            <?php endwhile; ?>
          </select>
          <input type="text" name="tytul" placeholder="np. Panowie Piasku" maxlength="60" required>
          <input type="text" name="powod" placeholder="Za co" maxlength="150">
          <button type="submit" class="tn-b">Nadaj tytuł</button>
        </form>

        <form method="POST" class="tn-mg-form">
          <input type="hidden" name="mg" value="odwolaj">
          <input type="hidden" name="turniej_id" value="<?php echo (int)$turniej['id']; ?>">
          <button type="submit" class="tn-b ostrzezenie">Odwołaj turniej</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="js/kasyno_turniej.js"></script>
  <script>KasynoTurniej.start(<?php echo (int)$turniej['id']; ?>);</script>

<?php endif; ?>

</div>
