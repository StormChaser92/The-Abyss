<?php
/* the-abyss/pages/melina.php
   Melina syndykatu — skarbiec, warka substancji, terytoria.
   Wejście: game.php?page=melina. Widzą ją tylko członkowie gangu.

   Logika w includes/melina.php, odczynniki kupuje się na rynku. */
require_once "db.php";
require_once "config/miasta.php";
require_once "config/mg.php";
require_once "includes/melina.php";

$id_gracza = (int)$_SESSION['id_gracza'];
$komunikat = "";

$gracz = $polaczenie->query("SELECT id, login, klasa, gotowka, inteligencja, obecne_miasto,
                                    syndykat_id, syndykat_rola, syndykat_dostep_skarbiec
                             FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$syn_id = (int)$gracz['syndykat_id'];
$miasto = $gracz['obecne_miasto'] ?? 'NEW YORK';
$kraj   = $MIASTA_DANE[$miasto]['kraj'] ?? 'USA';
$era    = boss_era($polaczenie);
$era_id = $era ? (int)$era['id'] : 0;
$jestem_mg = czy_mg($gracz['login']);

if ($syn_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['wplac'])) {
        $kwota = max(1, (int)$_POST['kwota']);
        if ((int)$gracz['gotowka'] < $kwota) {
            $komunikat = "<div class='ml-alert err'>Nie masz przy sobie tyle gotówki.</div>";
        } else {
            [$ok, $blad] = melina_kasa($polaczenie, $syn_id, $id_gracza, $kwota, 'Wpłata: '.$gracz['login']);
            if ($ok) {
                $polaczenie->query("UPDATE gracze SET gotowka=gotowka-$kwota WHERE id=$id_gracza");
                $komunikat = "<div class='ml-alert ok'>Do skarbca wpadło ".number_format($kwota, 0, '', ' ')." $.</div>";
            } else {
                $komunikat = "<div class='ml-alert err'>".htmlspecialchars($blad)."</div>";
            }
        }
    } elseif (isset($_POST['wyplac'])) {
        if (!(int)$gracz['syndykat_dostep_skarbiec']) {
            $komunikat = "<div class='ml-alert err'>Klucz do kasy ma lider. Nie ty.</div>";
        } else {
            $kwota = max(1, (int)$_POST['kwota']);
            [$ok, $blad] = melina_kasa($polaczenie, $syn_id, $id_gracza, -$kwota, 'Wypłata: '.$gracz['login']);
            if ($ok) {
                $polaczenie->query("UPDATE gracze SET gotowka=gotowka+$kwota WHERE id=$id_gracza");
                $komunikat = "<div class='ml-alert ok'>Wyjęte ze skarbca: ".number_format($kwota, 0, '', ' ')." $.</div>";
            } else {
                $komunikat = "<div class='ml-alert err'>".htmlspecialchars($blad)."</div>";
            }
        }
    } elseif (isset($_POST['start_warki'])) {
        if (!$era) {
            $komunikat = "<div class='ml-alert err'>Żadna era nie jest otwarta.</div>";
        } else {
            $platnosc = ($_POST['platnosc'] ?? 'prywatnie') === 'skarbiec' ? 'skarbiec' : 'prywatnie';
            if ($platnosc === 'skarbiec' && !(int)$gracz['syndykat_dostep_skarbiec']) $platnosc = 'prywatnie';
            [$ok, $tekst] = melina_start_warki($polaczenie, $gracz, $kraj, $miasto, $era_id, $platnosc);
            $komunikat = "<div class='ml-alert ".($ok ? 'ok' : 'err')."'>".htmlspecialchars($tekst)."</div>";
        }
    } elseif (isset($_POST['odbierz_warke'])) {
        $w = melina_warka_biezaca($polaczenie, $syn_id);
        if (!$w) {
            $komunikat = "<div class='ml-alert err'>Nie ma czego odbierać.</div>";
        } else {
            [$ok, $tekst, $udana] = melina_odbierz_warke($polaczenie, $gracz, $w);
            $komunikat = "<div class='ml-alert ".($udana ? 'ok' : ($ok ? 'uwaga' : 'err'))."'>".htmlspecialchars($tekst)."</div>";
        }
    } elseif ($jestem_mg && isset($_POST['mg_teren'])) {
        $cel = (int)$_POST['syndykat'];
        $kr  = $polaczenie->real_escape_string($_POST['kraj_teren'] ?? $kraj);
        $nt  = $polaczenie->real_escape_string(trim($_POST['notatka'] ?? ''));
        $mg  = $polaczenie->real_escape_string($gracz['login']);
        if ($cel > 0) {
            $polaczenie->query("UPDATE syndykat_terytoria SET `do`=NOW() WHERE kraj='$kr' AND `do` IS NULL");
            $polaczenie->query("INSERT INTO syndykat_terytoria (syndykat_id, kraj, nadal_mg, notatka)
                                VALUES ($cel, '$kr', '$mg', ".($nt ? "'$nt'" : "NULL").")");
            $komunikat = "<div class='ml-alert ok'>Terytorium przydzielone.</div>";
        }
    }
    $gracz = $polaczenie->query("SELECT id, login, klasa, gotowka, inteligencja, obecne_miasto,
                                        syndykat_id, syndykat_rola, syndykat_dostep_skarbiec
                                 FROM gracze WHERE id=$id_gracza")->fetch_assoc();
}

$syndykat = $syn_id > 0
    ? $polaczenie->query("SELECT * FROM syndykaty WHERE id=$syn_id")->fetch_assoc()
    : null;
?>
<link rel="stylesheet" href="css/kasyno_melina.css">

<div class="ml">

  <div class="ml-head">
    <div class="eyebrow">// <?php echo htmlspecialchars($MIASTA_DANE[$miasto]['flaga'] ?? ''); ?> <?php echo htmlspecialchars($miasto); ?> · lokal bez szyldu</div>
    <h1>Melina</h1>
    <div class="lead">Kasa gangu, kadź na zapleczu i mapa tego, co wasze. Wejście tylko dla swoich.</div>
  </div>

  <?php echo $komunikat; ?>

<?php if (!$syndykat): ?>

  <div class="ml-panel ml-pusto">
    <h2>Drzwi się nie otwierają</h2>
    <p>Melina należy do syndykatu, a ty do żadnego nie należysz. Wstąp do gangu albo załóż własny,
       a potem wróć — ktoś wpuści cię bocznym wejściem.</p>
  </div>

<?php else: ?>

  <div class="ml-pasek ml-panel">
    <div class="grp">
      <div class="kw">Gang<b><?php echo htmlspecialchars($syndykat['nazwa']); ?></b></div>
      <div class="kw">Skarbiec<b class="kasa"><?php echo number_format((int)$syndykat['skarbiec'], 0, '', ' '); ?> $</b></div>
      <div class="kw">Twoja gotówka<b class="mine"><?php echo number_format((int)$gracz['gotowka'], 0, '', ' '); ?> $</b></div>
    </div>
    <div class="rola"><?php echo htmlspecialchars($gracz['syndykat_rola']); ?></div>
  </div>

  <div class="ml-uklad">
    <div class="ml-kol">

      <?php
        $warka   = melina_warka_biezaca($polaczenie, $syn_id);
        $gotowa  = $warka && strtotime($warka['gotowa_o']) <= time();
        $braki   = melina_braki($polaczenie, $id_gracza, $syn_id);
        $inz     = ($gracz['klasa'] ?? '') === 'Inżynier';
        $trzyma  = melina_kto_trzyma($polaczenie, $kraj);
        $boss    = $era ? boss_kraju($polaczenie, $kraj, $era_id) : null;
      ?>

      <div class="ml-panel ml-warka">
        <div class="ml-tyt">Kadź · <?php echo htmlspecialchars($kraj); ?></div>

        <?php if ($warka): ?>
          <div class="ml-kadz <?php echo $gotowa ? 'gotowa' : 'warzy'; ?>">
            <div class="stan"><?php echo $gotowa ? 'Gotowa do odbioru' : 'Warzy się'; ?></div>
            <div class="dane">
              <div><span>Kraj</span><b><?php echo htmlspecialchars($warka['kraj']); ?></b></div>
              <div><span>Szansa</span><b><?php echo (int)$warka['szansa']; ?>%</b></div>
              <div><span>Gotowa</span><b><?php echo date('j.m, H:i', strtotime($warka['gotowa_o'])); ?></b></div>
              <div><span>Postawił</span><b><?php
                $i = $polaczenie->query("SELECT login FROM gracze WHERE id=".(int)$warka['inzynier_id']);
                echo htmlspecialchars($i && $i->num_rows ? $i->fetch_assoc()['login'] : '—');
              ?></b></div>
            </div>
            <?php if ($gotowa): ?>
              <form method="POST"><button type="submit" name="odbierz_warke" class="ml-b duzy">Odbierz warkę</button></form>
              <p class="nota">Rzut przy odbiorze. Nieudana warka zabiera składniki i pieczęć.</p>
            <?php else: ?>
              <p class="nota">Nikt przy niej nie stoi — substancja dojdzie sama. Wróćcie o wyznaczonej godzinie.</p>
            <?php endif; ?>
          </div>

        <?php else: ?>
          <p class="ml-opis">
            Pierwsza warka substancji w kraju wywabia miejscową legendę na arenę — i to wasz gang dostaje
            wyłączność na tę walkę. Kadź stawia Inżynier, składniki kupuje się na rynku, a jedna
            <b>Czarna Pieczęć</b> z walki z wrogim gangiem otwiera cały proces.
          </p>

          <div class="ml-recept">
            <?php foreach (warka_receptura() as $nazwa => $ile):
              $ma = melina_ma_przedmiot($polaczenie, $id_gracza, $nazwa); ?>
              <div class="<?php echo $ma >= $ile ? 'ok' : 'brak'; ?>">
                <span><?php echo htmlspecialchars($nazwa); ?></span>
                <b><?php echo $ma; ?> / <?php echo $ile; ?></b>
              </div>
            <?php endforeach; ?>
            <?php $p = melina_pieczecie_wolne($polaczenie, $syn_id); ?>
            <div class="<?php echo $p >= 1 ? 'ok' : 'brak'; ?>">
              <span><?php echo PIECZEC_NAZWA; ?></span><b><?php echo $p; ?> / 1</b>
            </div>
          </div>

          <?php if (!$inz): ?>
            <div class="ml-blok">Nad kadzią musi stanąć Inżynier. Zawołajcie swojego.</div>
          <?php elseif ($braki): ?>
            <div class="ml-blok">Brakuje składników. Odczynniki kupisz na rynku, pieczęć wygrywa się w PvP z wrogim gangiem.</div>
          <?php elseif ($boss && $boss['stan'] !== 'zamkniety'): ?>
            <div class="ml-blok">Prawo do walki w tym kraju jest już rozdane. Warka nic tu nie zmieni.</div>
          <?php else: ?>
            <form method="POST" class="ml-start">
              <select name="platnosc">
                <option value="prywatnie">opłata z mojej gotówki</option>
                <?php if ((int)$gracz['syndykat_dostep_skarbiec']): ?>
                  <option value="skarbiec">opłata ze skarbca</option>
                <?php endif; ?>
              </select>
              <button type="submit" name="start_warki" class="ml-b duzy">Postaw kadź · 25 000 $</button>
            </form>
            <p class="nota">Warzenie trwa od <?php echo WARKA_MIN_H; ?> do <?php echo WARKA_MAX_H; ?> godzin.
               Twoja szansa powodzenia: <b><?php echo melina_szansa_warki((int)$gracz['inteligencja']); ?>%</b>
               (baza <?php echo WARKA_BAZA; ?>% plus inteligencja <?php echo (int)$gracz['inteligencja']; ?>).</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="ml-panel">
        <div class="ml-tyt">Rejestr kasy</div>
        <div class="ml-rejestr">
          <?php
            $r = $polaczenie->query("SELECT k.*, g.login FROM syndykat_kasa k
                                     JOIN gracze g ON g.id = k.gracz_id
                                     WHERE k.syndykat_id=$syn_id ORDER BY k.id DESC LIMIT 14");
            if (!$r || !$r->num_rows) echo '<div class="puste">Kasa jeszcze nie widziała ruchu.</div>';
            while ($r && ($w = $r->fetch_assoc())):
          ?>
            <div>
              <span class="kto"><?php echo htmlspecialchars($w['login']); ?></span>
              <span class="co"><?php echo htmlspecialchars($w['powod']); ?></span>
              <span class="kwota <?php echo (int)$w['kwota'] > 0 ? 'plus' : 'minus'; ?>">
                <?php echo ((int)$w['kwota'] > 0 ? '+' : '').number_format((int)$w['kwota'], 0, '', ' '); ?>
              </span>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
    </div>

    <div class="ml-bok">
      <div class="ml-panel box">
        <div class="ml-tyt">Skarbiec</div>
        <form method="POST" class="ml-kasa-form">
          <input type="number" name="kwota" min="1" placeholder="kwota w $" required>
          <div class="dwa">
            <button type="submit" name="wplac" class="ml-b">Wpłać</button>
            <button type="submit" name="wyplac" class="ml-b <?php echo (int)$gracz['syndykat_dostep_skarbiec'] ? '' : 'martwy'; ?>"
              <?php echo (int)$gracz['syndykat_dostep_skarbiec'] ? '' : 'disabled'; ?>>Wypłać</button>
          </div>
        </form>
        <p class="nota">Wpłaca każdy. Wyjmuje tylko ten, komu gang dał klucz do kasy.</p>
      </div>

      <div class="ml-panel box">
        <div class="ml-tyt">Pieczęcie</div>
        <?php
          $wolne = melina_pieczecie_wolne($polaczenie, $syn_id);
          $wb    = melina_wb_gangu($polaczenie, $syn_id);
          $sz    = melina_szansa_pieczeci($wb);
        ?>
        <div class="ml-piecz">
          <div><span>Wolne</span><b><?php echo $wolne; ?></b></div>
          <div><span>Suma WB gangu</span><b><?php echo number_format($wb, 0, '', ' '); ?></b></div>
          <div><span>Szansa na walkę</span><b><?php echo number_format($sz, 2); ?>%</b></div>
        </div>
        <p class="nota">Czarna Pieczęć wypada z wygranej walki z członkiem wrogiego syndykatu.
           Szansa rośnie z sumą punktów walki bronią całego gangu, sufit to <?php echo (int)PIECZEC_MAX; ?>%.</p>
      </div>

      <div class="ml-panel box">
        <div class="ml-tyt">Terytoria</div>
        <?php $tery = melina_terytoria($polaczenie, $syn_id); ?>
        <?php if (!$tery): ?>
          <div class="puste">Nie trzymacie jeszcze niczego. Terytorium przydziela Mistrz Gry po sesji.</div>
        <?php else: ?>
          <div class="ml-tery">
            <?php foreach ($tery as $t): ?>
              <div>
                <b><?php echo htmlspecialchars($t['kraj']); ?></b>
                <span>od <?php echo date('j.m.Y', strtotime($t['od'])); ?><?php
                  echo $t['notatka'] ? ' · '.htmlspecialchars($t['notatka']) : ''; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($trzyma && (int)$trzyma['syndykat_id'] !== $syn_id): ?>
          <p class="nota"><?php echo htmlspecialchars($kraj); ?> trzyma
             <b><?php echo htmlspecialchars($trzyma['nazwa']); ?></b>.</p>
        <?php endif; ?>
      </div>

      <?php if ($boss): ?>
      <div class="ml-panel box">
        <div class="ml-tyt">Legenda tego kraju</div>
        <div class="ml-boss">
          <b><?php echo htmlspecialchars($boss['imie']); ?></b>
          <span>„<?php echo htmlspecialchars($boss['ksywa']); ?>” · <?php echo htmlspecialchars($boss['arena']); ?></span>
          <div class="stan <?php echo $boss['stan'] ?: 'zamkniety'; ?>">
            <?php echo match ($boss['stan'] ?: 'zamkniety') {
              'gang'    => (int)$boss['syndykat_id'] === $syn_id ? 'Wyłączność należy do was' : 'Wyłączność ma inny gang',
              'otwarty' => 'Otwarty dla wszystkich',
              'ubity'   => 'Pokonany w tej erze',
              default   => 'Czeka na pierwszą warkę',
            }; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($jestem_mg): ?>
      <div class="ml-panel box ml-mg">
        <div class="ml-tyt">Panel Mistrza Gry</div>
        <form method="POST" class="ml-kasa-form">
          <input type="hidden" name="mg_teren" value="1">
          <select name="syndykat" required>
            <option value="">— syndykat —</option>
            <?php
              $sy = $polaczenie->query("SELECT id, nazwa, tag FROM syndykaty ORDER BY nazwa ASC");
              while ($sy && ($s = $sy->fetch_assoc())):
            ?>
              <option value="<?php echo (int)$s['id']; ?>">[<?php echo htmlspecialchars($s['tag']); ?>] <?php echo htmlspecialchars($s['nazwa']); ?></option>
            <?php endwhile; ?>
          </select>
          <select name="kraj_teren">
            <?php foreach (array_unique(array_column($MIASTA_DANE, 'kraj')) as $k): ?>
              <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $k === $kraj ? 'selected' : ''; ?>><?php echo htmlspecialchars($k); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="notatka" placeholder="Notatka z sesji" maxlength="150">
          <button type="submit" class="ml-b">Przydziel terytorium</button>
        </form>
        <p class="nota">Nowy przydział zamyka poprzedni w tym samym kraju.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

</div>
