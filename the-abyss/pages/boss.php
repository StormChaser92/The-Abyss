<?php
/* the-abyss/pages/boss.php
   Boss kraju — endgame syndykatów. Poziom 120, 48 rund, jedna próba na
   postać na erę, bez apteczek, wymagany sprzęt klasy V i obecność na
   miejscu. Matematyka siedzi w includes/bossowie.php.

   Wejście: game.php?page=boss  (boss czytany z gracze.obecne_miasto) */
require_once "db.php";
require_once "config/miasta.php";
require_once "config/mg.php";
require_once "includes/bossowie.php";

$id_gracza = (int)$_SESSION['id_gracza'];
$komunikat = "";
$walka     = null;

$kolumny = "id, login, poziom, exp, gotowka, hp_aktualne, hp_max, energia_aktualna,
            sila, wytrzymalosc, zrecznosc, inteligencja, walka_bronia, uniki,
            bonus_atak, bonus_obrona, bonus_szybkosc, bonus_unik,
            bron_zalozona, pancerz_zalozony, obecne_miasto, syndykat_id, syndykat_rola,
            uniki_male_kumulacja, walka_male_kumulacja, tytul";
$gracz = $polaczenie->query("SELECT $kolumny FROM gracze WHERE id=$id_gracza")->fetch_assoc();

$miasto  = $gracz['obecne_miasto'] ?? 'NEW YORK';
$m_dane  = $MIASTA_DANE[$miasto] ?? null;
$kraj    = $m_dane['kraj'] ?? 'USA';
$era     = boss_era($polaczenie);
$era_id  = $era ? (int)$era['id'] : 0;
$jestem_mg = czy_mg($gracz['login']);

$boss = $era ? boss_kraju($polaczenie, $kraj, $era_id) : null;
if ($boss && !$boss['stan']) {
    boss_zapewnij_stan($polaczenie, (int)$boss['id'], $era_id);
    $boss = boss_kraju($polaczenie, $kraj, $era_id);
}

/* Samoczynne otwarcie dla serwera: próby wyczerpane albo minęło 7 dni. */
if ($boss && boss_moze_otworzyc($polaczenie, $boss, $era_id)) {
    boss_otworz_dla_serwera($polaczenie, $boss, $era_id);
    $boss = boss_kraju($polaczenie, $kraj, $era_id);
}

/* ── panel Mistrza Gry ─────────────────────────────────────────────── */
if ($jestem_mg && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mg_akcja']) && $boss && $era) {
    $bid = (int)$boss['id'];
    if ($_POST['mg_akcja'] === 'przydziel') {
        $syn = (int)($_POST['syndykat'] ?? 0);
        $powod = $polaczenie->real_escape_string(trim($_POST['powod'] ?? 'Kontrola terytorium'));
        if ($syn > 0) {
            $polaczenie->query("UPDATE boss_stan SET stan='gang', syndykat_id=$syn,
                                odblokowany=NOW(), powod='$powod'
                                WHERE era_id=$era_id AND boss_id=$bid");
            $s = $polaczenie->query("SELECT nazwa FROM syndykaty WHERE id=$syn")->fetch_assoc();
            $nazwa = $polaczenie->real_escape_string($s['nazwa'] ?? 'syndykat');
            $t = $polaczenie->real_escape_string($boss['imie'].' „'.$boss['ksywa'].'”');
            $polaczenie->query("INSERT INTO wydarzenia (era_id, rodzaj, tytul, tresc) VALUES
                ($era_id, 'boss_otwarty', 'Prawo pierwszeństwa: $nazwa',
                 'Syndykat $nazwa zdobył prawo do walki z $t. Powód: $powod. Każdy egzekutor gangu ma jedną próbę.')");
            $komunikat = "<div class='bs-alert ok'>Prawo pierwszeństwa przydzielone.</div>";
        }
    } elseif ($_POST['mg_akcja'] === 'otworz') {
        boss_otworz_dla_serwera($polaczenie, $boss, $era_id);
        $komunikat = "<div class='bs-alert ok'>Arena otwarta dla całego serwera.</div>";
    } elseif ($_POST['mg_akcja'] === 'zamknij_ere') {
        $polaczenie->query("UPDATE ery SET zamknieta=NOW() WHERE id=$era_id");
        $nazwa = $polaczenie->real_escape_string(trim($_POST['nowa_era'] ?? 'Nowa era'));
        $mg = $polaczenie->real_escape_string($gracz['login']);
        $polaczenie->query("INSERT INTO ery (nazwa, mg_login) VALUES ('$nazwa', '$mg')");
        $komunikat = "<div class='bs-alert ok'>Era zamknięta. Bossowie wracają do stanu zamkniętego.</div>";
    }
    $era    = boss_era($polaczenie);
    $era_id = $era ? (int)$era['id'] : 0;
    $boss   = $era ? boss_kraju($polaczenie, $kraj, $era_id) : null;
    if ($boss && !$boss['stan']) {
        boss_zapewnij_stan($polaczenie, (int)$boss['id'], $era_id);
        $boss = boss_kraju($polaczenie, $kraj, $era_id);
    }
}

/* ── walka ─────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stan_do_walki']) && $boss && $era) {
    [$wolno, $powod] = boss_wpuszcza($polaczenie, $gracz, $boss, $era_id);
    if (!$wolno) {
        $komunikat = "<div class='bs-alert err'>".htmlspecialchars($powod)."</div>";
    } else {
        $walka = boss_walka($gracz, $boss);
        $bid = (int)$boss['id'];
        $syn = (int)$gracz['syndykat_id'];

        [$kum_un, $przyr_un] = konwertuj_male_na_duze(
            (int)$gracz['uniki_male_kumulacja'] + $walka['male_un'], (float)$gracz['uniki']);
        [$kum_wb, $przyr_wb] = konwertuj_male_na_duze(
            (int)$gracz['walka_male_kumulacja'] + $walka['male_wb'], (float)$gracz['walka_bronia']);

        $exp  = 0;
        $kasa = 0;
        if ($walka['wynik'] === 'wygrana') {
            $exp  = (int)$boss['nagroda_exp'];
            $kasa = (int)$boss['nagroda_kasa'];
        } else {
            // Przegrana i remis: EXP proporcjonalny do zadanych obrażeń.
            $exp = (int)round($boss['nagroda_exp'] * 0.15 * min(1, $walka['dmg_zadany'] / max(1, (int)$boss['hp'])));
        }

        $nowe_un = (float)$gracz['uniki'] + $przyr_un;
        $nowe_wb = (float)$gracz['walka_bronia'] + $przyr_wb;
        $hp = $walka['hp_koniec'];

        $polaczenie->query("UPDATE gracze SET
            hp_aktualne=$hp,
            energia_aktualna=GREATEST(0, energia_aktualna-10),
            exp=exp+$exp, gotowka=gotowka+$kasa,
            uniki=$nowe_un, walka_bronia=$nowe_wb,
            uniki_male_kumulacja=$kum_un, walka_male_kumulacja=$kum_wb
            WHERE id=$id_gracza");

        $dz = $polaczenie->real_escape_string(json_encode($walka['dziennik']));
        $polaczenie->query("INSERT INTO boss_proby
            (era_id, boss_id, gracz_id, syndykat_id, wynik, rundy, dmg_zadany, dmg_wziety,
             hp_koniec, uniki_male, wb_male, dziennik) VALUES
            ($era_id, $bid, $id_gracza, ".($syn > 0 ? $syn : 'NULL').", '{$walka['wynik']}',
             {$walka['rundy']}, {$walka['dmg_zadany']}, {$walka['dmg_wziety']},
             $hp, {$walka['male_un']}, {$walka['male_wb']}, '$dz')");

        if ($walka['wynik'] === 'wygrana') {
            $polaczenie->query("UPDATE boss_stan SET stan='ubity', zwyciezca_id=$id_gracza,
                                zwyciezca_gang=".($syn > 0 ? $syn : 'NULL').", ubity=NOW()
                                WHERE era_id=$era_id AND boss_id=$bid AND stan <> 'ubity'");

            $komp = $polaczenie->real_escape_string($boss['komponent']);
            $ch = $polaczenie->query("SELECT id FROM przedmioty_gracze WHERE gracz_id=$id_gracza AND nazwa='$komp'");
            if ($ch && $ch->num_rows)
                $polaczenie->query("UPDATE przedmioty_gracze SET ilosc=ilosc+1 WHERE gracz_id=$id_gracza AND nazwa='$komp'");
            else
                $polaczenie->query("INSERT INTO przedmioty_gracze (gracz_id, nazwa, ilosc) VALUES ($id_gracza, '$komp', 1)");
            $polaczenie->query("INSERT INTO boss_lupy (gracz_id, boss_id, komponent) VALUES ($id_gracza, $bid, '$komp')");

            $ksywa = $polaczenie->real_escape_string($boss['imie'].' „'.$boss['ksywa'].'”');
            $polaczenie->query("UPDATE gracze SET tytul='Niezniszczalny', bossy_ubite=bossy_ubite+1 WHERE id=$id_gracza");
            $polaczenie->query("INSERT INTO tytuly (era_id, rodzaj, gracz_id, tytul, powod) VALUES
                ($era_id, 'gracz', $id_gracza, 'Niezniszczalny', 'Pokonanie $ksywa')");

            if ($syn > 0) {
                $tg = $polaczenie->real_escape_string($boss['tytul_gangu']);
                $polaczenie->query("UPDATE syndykaty SET tytul='$tg', bossy_ubite=bossy_ubite+1 WHERE id=$syn");
                $polaczenie->query("INSERT INTO tytuly (era_id, rodzaj, syndykat_id, tytul, powod) VALUES
                    ($era_id, 'gang', $syn, '$tg', 'Pokonanie $ksywa')");
            }

            $login = $polaczenie->real_escape_string($gracz['login']);
            $arena = $polaczenie->real_escape_string($boss['arena']);
            $polaczenie->query("INSERT INTO wydarzenia (era_id, rodzaj, tytul, tresc) VALUES
                ($era_id, 'boss_ubity', '$ksywa pokonany',
                 '$login położył $ksywa na $arena po {$walka['rundy']} rundach. Arena zamknięta do końca ery.')");
        }

        // Odśwież stan do renderu
        $gracz = $polaczenie->query("SELECT $kolumny FROM gracze WHERE id=$id_gracza")->fetch_assoc();
        $boss  = boss_kraju($polaczenie, $kraj, $era_id);
        $walka += ['przyr_un'=>$przyr_un, 'przyr_wb'=>$przyr_wb, 'exp'=>$exp, 'kasa'=>$kasa];
    }
}

$prognoza = ($boss && $gracz) ? boss_prognoza($gracz, $boss) : null;
[$wolno, $powod] = ($boss && $era) ? boss_wpuszcza($polaczenie, $gracz, $boss, $era_id) : [false, ''];
$kl_bron  = klasa_sprzetu((int)$gracz['bonus_atak']);
$kl_zbroj = klasa_sprzetu((int)$gracz['bonus_obrona']);
$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $boss['imie'] ?? ''));
?>
<link rel="stylesheet" href="css/kasyno_boss.css">

<div class="bs">

  <div class="bs-head">
    <div class="eyebrow">// <?php echo htmlspecialchars($m_dane['flaga'] ?? ''); ?> <?php echo htmlspecialchars($kraj); ?> · <?php echo htmlspecialchars($miasto); ?> · arena elitarna</div>
    <h1><?php echo $boss ? htmlspecialchars($boss['arena']) : 'Arena elitarna'; ?></h1>
    <div class="lead">
      <?php if ($era): ?>
        <?php echo htmlspecialchars($era['nazwa']); ?> · jedna próba na postać · 48 rund · bez apteczek
      <?php else: ?>
        Żadna era nie jest otwarta. Mistrz Gry musi ją rozpocząć.
      <?php endif; ?>
    </div>
  </div>

  <?php echo $komunikat; ?>

<?php if (!$boss): ?>

  <div class="bs-panel bs-pusto">
    <h2>Tutaj nie ma legendy</h2>
    <p>W tym kraju nikt nie utrzymał tytułu wystarczająco długo, żeby ktokolwiek nazwał go niepokonanym.
       Bossowie stoją w dwudziestu jeden krajach — sprawdź lotnisko.</p>
  </div>

<?php else: ?>

  <div class="bs-uklad">
    <div>
      <div class="bs-panel bs-karta">
        <div class="bs-portret" data-slot="img/wrogowie/<?php echo strtolower($kraj); ?>/<?php echo $slug; ?>.png">
          <?php
            $sciezka = null;
            foreach (['png','jpg','jpeg','webp'] as $ext) {
                $p = "img/wrogowie/".strtolower($kraj)."/$slug.$ext";
                if (file_exists($p)) { $sciezka = $p; break; }
            }
            if ($sciezka): ?>
              <img src="<?php echo htmlspecialchars($sciezka); ?>" alt="<?php echo htmlspecialchars($boss['imie']); ?>">
            <?php else: ?>
              <div class="bs-portret-pusty">
                <span>portret</span>
                <code>img/wrogowie/<?php echo strtolower($kraj); ?>/<?php echo $slug; ?>.png</code>
              </div>
          <?php endif; ?>
        </div>

        <div class="bs-karta-tresc">
          <div class="bs-imie"><?php echo htmlspecialchars($boss['imie']); ?></div>
          <div class="bs-ksywa">„<?php echo htmlspecialchars($boss['ksywa']); ?>”</div>
          <div class="bs-poziom">Poziom <?php echo (int)$boss['poziom']; ?></div>
          <p class="bs-opis"><?php echo htmlspecialchars($boss['opis']); ?></p>
          <div class="bs-staty">
            <div><span>HP</span><b><?php echo number_format((int)$boss['hp'], 0, '', ' '); ?></b></div>
            <div><span>Celność</span><b><?php echo number_format((int)$boss['celnosc'], 0, '', ' '); ?></b></div>
            <div><span>Unik</span><b><?php echo number_format((int)$boss['unik'], 0, '', ' '); ?></b></div>
            <div><span>Obrona</span><b><?php echo number_format((int)$boss['obrona'], 0, '', ' '); ?></b></div>
            <div><span>Atak</span><b><?php echo number_format((int)$boss['atak'], 0, '', ' '); ?></b></div>
          </div>
        </div>
      </div>

<?php if ($walka): ?>
      <div class="bs-panel bs-wynik <?php echo $walka['wynik']; ?>">
        <div class="bs-tyt">Wynik</div>
        <h2>
          <?php if ($walka['wynik'] === 'wygrana'): ?>
            <?php echo htmlspecialchars($boss['ksywa']); ?> leży na piasku
          <?php elseif ($walka['wynik'] === 'przegrana'): ?>
            Wynoszą cię z areny
          <?php else: ?>
            Sędzia przerywa po 48 rundach
          <?php endif; ?>
        </h2>
        <div class="bs-wynik-siatka">
          <div><span>Rundy</span><b><?php echo $walka['rundy']; ?></b></div>
          <div><span>Zadane</span><b><?php echo number_format($walka['dmg_zadany'], 0, '', ' '); ?></b></div>
          <div><span>Wzięte</span><b><?php echo number_format($walka['dmg_wziety'], 0, '', ' '); ?></b></div>
          <div><span>HP bossa</span><b><?php echo number_format($walka['hp_boss'], 0, '', ' '); ?></b></div>
          <div><span>Twoje HP</span><b><?php echo $walka['hp_koniec']; ?></b></div>
          <div><span>Małe uniki</span><b><?php echo $walka['male_un']; ?></b></div>
          <div><span>Małe WB</span><b><?php echo $walka['male_wb']; ?></b></div>
          <div><span>Uniki</span><b>+<?php echo number_format($walka['przyr_un'], 3); ?></b></div>
          <div><span>Walka bronią</span><b>+<?php echo number_format($walka['przyr_wb'], 3); ?></b></div>
          <div><span>EXP</span><b>+<?php echo number_format($walka['exp'], 0, '', ' '); ?></b></div>
          <div><span>Gotówka</span><b>+<?php echo number_format($walka['kasa'], 0, '', ' '); ?></b></div>
        </div>
        <?php if ($walka['wynik'] === 'wygrana'): ?>
          <div class="bs-lup">
            Tytuł <b>Niezniszczalny</b> · łup <b><?php echo htmlspecialchars($boss['komponent']); ?></b>
            <?php if ((int)$gracz['syndykat_id'] > 0): ?> · gang bierze tytuł <b><?php echo htmlspecialchars($boss['tytul_gangu']); ?></b><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="bs-panel bs-dziennik">
        <div class="bs-tyt">Przebieg walki</div>
        <div class="bs-log">
          <?php foreach ($walka['dziennik'] as $w): ?>
            <div class="lw <?php echo $w['typ']; ?>">
              <span class="r">R<?php echo $w['r']; ?></span>
              <span class="t"><?php
                echo match ($w['typ']) {
                    'trafienie' => 'trafienie',
                    'pudlo'     => 'pudło',
                    'rana'      => 'rana',
                    default     => 'unik',
                };
              ?></span>
              <span class="d"><?php echo $w['dmg'] > 0 ? $w['dmg'].' dmg' : '—'; ?></span>
              <span class="hp">boss <?php echo $w['hp_boss']; ?> · ty <?php echo $w['hp']; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>

      <div class="bs-panel bs-prognoza">
        <div class="bs-tyt">Prognoza — te same wzory, bez rzutu kością</div>
        <div class="bs-progn-siatka">
          <div><span>Trafisz go</span><b><?php echo $prognoza['moja_szansa']; ?>%</b></div>
          <div><span>On trafi ciebie</span><b><?php echo $prognoza['jego_szansa']; ?>%</b></div>
          <div><span>Twój cios</span><b><?php echo $prognoza['moj_cios']; ?></b></div>
          <div><span>Jego cios</span><b><?php echo $prognoza['jego_cios']; ?></b></div>
          <div class="<?php echo $prognoza['wystarczy'] ? 'dobrze' : 'zle'; ?>">
            <span>Zadasz w 48 rundach</span><b><?php echo number_format($prognoza['zadam'], 0, '', ' '); ?></b>
          </div>
          <div class="<?php echo $prognoza['przezyje'] ? 'dobrze' : 'zle'; ?>">
            <span>Oberwiesz</span><b><?php echo number_format($prognoza['obiore'], 0, '', ' '); ?></b>
          </div>
        </div>
        <p class="bs-progn-slowo">
          <?php if ($prognoza['wystarczy'] && $prognoza['przezyje']): ?>
            Bilans wychodzi na twoją korzyść po obu stronach. Rozstrzygnie wariancja.
          <?php elseif ($prognoza['wystarczy']): ?>
            Zdążysz go położyć, ale statystycznie padniesz pierwsza. Więcej wytrzymałości albo lepsza zbroja.
          <?php elseif ($prognoza['przezyje']): ?>
            Przeżyjesz 48 rund i nie zdążysz go zabić — to remis. Potrzebujesz mocniejszej broni.
          <?php else: ?>
            Nie zdążysz go zabić i nie przeżyjesz próby. Wróć z lepszym sprzętem.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="bs-bok">
      <div class="bs-panel box">
        <div class="bs-tyt">Stan areny</div>
        <?php
          $etykiety = [
            'zamkniety' => ['Zamknięta', 'Nikt nie zdobył jeszcze prawa do tej walki.'],
            'gang'      => ['Prawo pierwszeństwa', ''],
            'otwarty'   => ['Otwarta dla wszystkich', 'Każdy egzekutor ma jedną próbę w tej erze.'],
            'ubity'     => ['Boss pokonany', ''],
          ];
          $e = $etykiety[$boss['stan']] ?? $etykiety['zamkniety'];
        ?>
        <div class="bs-stan <?php echo $boss['stan']; ?>"><?php echo $e[0]; ?></div>
        <p class="bs-stan-opis">
          <?php if ($boss['stan'] === 'gang'):
            $s = $polaczenie->query("SELECT nazwa, tag FROM syndykaty WHERE id=".(int)$boss['syndykat_id']);
            $s = $s && $s->num_rows ? $s->fetch_assoc() : null;
            $zostalo = boss_proby_zostaly($polaczenie, (int)$boss['id'], $era_id, (int)$boss['syndykat_id']);
            $dni = max(0, BOSS_DNI - (int)floor((time() - strtotime($boss['odblokowany'])) / 86400));
          ?>
            Wyłączność ma <b><?php echo htmlspecialchars($s['nazwa'] ?? '—'); ?></b>.
            Zostało prób: <b><?php echo $zostalo; ?></b>, dni do otwarcia: <b><?php echo $dni; ?></b>.
            <?php if ($boss['powod']): ?><br>Powód: <?php echo htmlspecialchars($boss['powod']); ?><?php endif; ?>
          <?php elseif ($boss['stan'] === 'ubity'):
            $z = $polaczenie->query("SELECT login FROM gracze WHERE id=".(int)$boss['zwyciezca_id']);
            $z = $z && $z->num_rows ? $z->fetch_assoc()['login'] : '—';
          ?>
            Położył go <b><?php echo htmlspecialchars($z); ?></b>
            <?php echo $boss['ubity'] ? date('j.m.Y', strtotime($boss['ubity'])) : ''; ?>.
            Arena zamknięta do końca ery.
          <?php else: ?>
            <?php echo $e[1]; ?>
          <?php endif; ?>
        </p>
      </div>

      <div class="bs-panel box">
        <div class="bs-tyt">Próg wejścia</div>
        <div class="bs-prog">
          <div class="<?php echo $kl_bron >= 5 ? 'ok' : 'brak'; ?>">
            Broń klasy <b><?php echo klasa_rzymska(BOSS_KLASA); ?></b>
            <span>masz <?php echo klasa_rzymska($kl_bron); ?> · +<?php echo (int)$gracz['bonus_atak']; ?> atk</span>
          </div>
          <div class="<?php echo $kl_zbroj >= 5 ? 'ok' : 'brak'; ?>">
            Zbroja klasy <b><?php echo klasa_rzymska(BOSS_KLASA); ?></b>
            <span>masz <?php echo klasa_rzymska($kl_zbroj); ?> · +<?php echo (int)$gracz['bonus_obrona']; ?> obr</span>
          </div>
          <div class="<?php echo (int)$gracz['poziom'] >= BOSS_POZIOM_MIN ? 'ok' : 'brak'; ?>">
            Poziom <b><?php echo BOSS_POZIOM_MIN; ?>+</b>
            <span>masz <?php echo (int)$gracz['poziom']; ?></span>
          </div>
          <div class="<?php echo (int)$gracz['hp_aktualne'] >= (int)$gracz['hp_max'] ? 'ok' : 'brak'; ?>">
            Pełne HP
            <span><?php echo (int)$gracz['hp_aktualne']; ?> / <?php echo (int)$gracz['hp_max']; ?></span>
          </div>
        </div>
      </div>

      <div class="bs-panel box">
        <div class="bs-tyt">Twoje liczby</div>
        <div class="bs-moje">
          <div><span>Trafienie</span><b><?php echo $prognoza['trafienie']; ?></b></div>
          <div><span>Zdolność uniku</span><b><?php echo $prognoza['unik']; ?></b></div>
          <div><span>Uniki</span><b><?php echo number_format((float)$gracz['uniki'], 2); ?></b></div>
          <div><span>Walka bronią</span><b><?php echo number_format((float)$gracz['walka_bronia'], 2); ?></b></div>
          <div><span>Obrona</span><b><?php echo (int)$gracz['wytrzymalosc'] + (int)$gracz['bonus_obrona']; ?></b></div>
        </div>
      </div>

      <div class="bs-panel box">
        <div class="bs-tyt">Łup i tytuły</div>
        <div class="bs-nagrody">
          <div><span>Komponent</span><b><?php echo htmlspecialchars($boss['komponent']); ?></b></div>
          <div><span>Tytuł gracza</span><b>Niezniszczalny</b></div>
          <div><span>Tytuł gangu</span><b><?php echo htmlspecialchars($boss['tytul_gangu']); ?></b></div>
          <div><span>Gotówka</span><b><?php echo number_format((int)$boss['nagroda_kasa'], 0, '', ' '); ?> $</b></div>
          <div><span>EXP</span><b><?php echo number_format((int)$boss['nagroda_exp'], 0, '', ' '); ?></b></div>
        </div>
        <p class="bs-nagrody-nota">Komponent jest składnikiem mocniejszej substancji gangu. Tytuły są prestiżowe — bez bonusów mechanicznych.</p>
      </div>

      <?php if ($jestem_mg): ?>
      <div class="bs-panel box bs-mg">
        <div class="bs-tyt">Panel Mistrza Gry</div>
        <form method="POST" class="bs-mg-form">
          <input type="hidden" name="mg_akcja" value="przydziel">
          <label>Prawo pierwszeństwa</label>
          <select name="syndykat" required>
            <option value="">— wybierz syndykat —</option>
            <?php
              $sy = $polaczenie->query("SELECT id, nazwa, tag FROM syndykaty ORDER BY nazwa ASC");
              while ($sy && ($s = $sy->fetch_assoc())):
            ?>
              <option value="<?php echo (int)$s['id']; ?>">[<?php echo htmlspecialchars($s['tag']); ?>] <?php echo htmlspecialchars($s['nazwa']); ?></option>
            <?php endwhile; ?>
          </select>
          <input type="text" name="powod" placeholder="Powód, np. kontrola terytorium" maxlength="90">
          <button type="submit" class="bs-b">Przydziel</button>
        </form>
        <form method="POST" class="bs-mg-form">
          <input type="hidden" name="mg_akcja" value="otworz">
          <button type="submit" class="bs-b">Otwórz dla wszystkich</button>
        </form>
        <form method="POST" class="bs-mg-form">
          <input type="hidden" name="mg_akcja" value="zamknij_ere">
          <input type="text" name="nowa_era" placeholder="Nazwa nowej ery" maxlength="60" required>
          <button type="submit" class="bs-b ostrzezenie">Zamknij erę</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="bs-panel bs-akcja">
    <?php if ($wolno): ?>
      <form method="POST">
        <button type="submit" name="stan_do_walki" class="bs-b duzy">Stań do walki</button>
      </form>
      <p>Jedna próba na całą erę. Kosztuje 10 energii i nie da się jej powtórzyć.</p>
    <?php else: ?>
      <div class="bs-blokada"><?php echo htmlspecialchars($powod); ?></div>
    <?php endif; ?>
  </div>

<?php endif; ?>

</div>
