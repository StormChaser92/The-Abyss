<?php
/* the-abyss/pages/sklep.php
   Lombard „Rdza i Krew" — broń poziomów 1–12 i podstawowa ochrona ciała.
   Katalog czytany z includes/bronie_katalog.php (bronie_sklep()), własność
   zapisywana do tabeli ekwipunek_gracza przez eq_dodaj(). */

require_once "db.php";
require_once "includes/bronie_katalog.php";
require_once "includes/warsztat_logika.php";    // eq_dodaj(), eq_stan(), bron_atak()
require_once "includes/ekwipunek_katalog.php";  // eq_zaloz_bron(), eq_zaloz_pancerz()

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

/* Ceny ochrony ciała zostają tutaj — katalog pancerzy jest w ekwipunek_katalog.php,
   a Lombard sprzedaje tylko cztery najtańsze sztuki. */
$sklep_pancerze = [
    'eq_bluza'     => ['poziom'=>1,  'koszt'=>50,   'opis'=>'Ochroni przed zimnem i lekkim zadrapaniem.'],
    'eq_kurtka'    => ['poziom'=>3,  'koszt'=>250,  'opis'=>'Twarda skóra zatrzyma słabsze cięcia nożem.'],
    'eq_opony'     => ['poziom'=>6,  'koszt'=>600,  'opis'=>'Guma amortyzuje uderzenia kijem. Rusza się w niej gorzej.'],
    'eq_kamizelka' => ['poziom'=>10, 'koszt'=>1400, 'opis'=>'Ktoś już w niej zginął, ale płytka ceramiczna wciąż tam jest.'],
];

$opisy_broni = [
    'zardzewialy_widelec' => 'Lepszy niż pięści, ryzyko tężca gratis.',
    'gruba_rurka_stalowa' => 'Wyrwana z barierki na parkingu. Nie pyta o pozwolenie.',
    'tepy_noz_kuchenny'   => 'Pewnie służył do smarowania masła. Tutaj też sobie poradzi.',
    'kij_baseballowy'     => 'Klasyk ulicznych porachunków.',
    'noz_sprezynowy'      => 'Otwiera się szybciej, niż zdążysz pożałować.',
    'lancuch_rowerowy'    => 'Zostawia paskudne pręgi. Rower już nikomu nie potrzebny.',
    'brzytwa'             => 'Fryzjer z Detroit sprzedał ją bez pytań.',
    'stary_kastet'        => 'Zardzewiały mosiądz. Ulubiona zabawka rzezimieszków.',
    'palka_teleskopowa'   => 'Mieści się w rękawie. Rozkłada się z trzaskiem.',
    'noz_motylkowy'       => 'Połowa ludzi kaleczy się przy otwieraniu. Ty też będziesz.',
    'pistolet_samorobka'  => 'Rura, sprężyna i mnóstwo optymizmu.',
    'klucz_francuski'     => 'Nikt nie zapyta, czemu go nosisz.',
    'noz_bojowy_ka_bar'   => 'Wojskowy nadmiar, sprzedany trzy razy pod stołem.',
];

$gracz = $polaczenie->query("SELECT id, poziom, gotowka, bron_zalozona, bonus_atak, bonus_szybkosc,
                                    pancerz_zalozony, bonus_obrona, bonus_unik, bron_stopien
                             FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$moje = eq_stan($polaczenie, $id_gracza);

/* ── kupno ─────────────────────────────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['kup_bron'])) {
    $kod = $_POST['kod'] ?? '';
    $kat = bronie_sklep();
    if (isset($kat[$kod])) {
        $b = $kat[$kod];
        if ((int)$gracz['poziom'] < (int)$b['poziom'])
            $komunikat = "<div class='blad'>Za niski poziom. Wymagany: {$b['poziom']}</div>";
        elseif ((int)$gracz['gotowka'] < (int)$b['cena'])
            $komunikat = "<div class='blad'>Brak kasy. Potrzebujesz {$b['cena']} \$</div>";
        else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - {$b['cena']} WHERE id=$id_gracza");
            eq_dodaj($polaczenie, $id_gracza, $kod, 1);
            [$ok, $tekst] = eq_zaloz_bron($polaczenie, $id_gracza, $kod);
            $komunikat = "<div class='sukces'>Kupujesz <b>{$b['nazwa']}</b>. $tekst</div>";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['kup_pancerz'])) {
    $kod = $_POST['kod'] ?? '';
    $kat = eq_katalog_pancerzy();
    if (isset($sklep_pancerze[$kod]) && isset($kat[$kod])) {
        $s = $sklep_pancerze[$kod];
        $p = $kat[$kod];
        if ((int)$gracz['poziom'] < (int)$s['poziom'])
            $komunikat = "<div class='blad'>Za niski poziom. Wymagany: {$s['poziom']}</div>";
        elseif ((int)$gracz['gotowka'] < (int)$s['koszt'])
            $komunikat = "<div class='blad'>Brak kasy. Potrzebujesz {$s['koszt']} \$</div>";
        else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - {$s['koszt']} WHERE id=$id_gracza");
            eq_dodaj($polaczenie, $id_gracza, $kod, 1);
            [$ok, $tekst] = eq_zaloz_pancerz($polaczenie, $id_gracza, $kod);
            $komunikat = "<div class='sukces'>Kupujesz <b>{$p['nazwa']}</b>. $tekst</div>";
        }
    }
}

if ($komunikat) {
    $gracz = $polaczenie->query("SELECT id, poziom, gotowka, bron_zalozona, bonus_atak, bonus_szybkosc,
                                        pancerz_zalozony, bonus_obrona, bonus_unik, bron_stopien
                                 FROM gracze WHERE id=$id_gracza")->fetch_assoc();
    $moje = eq_stan($polaczenie, $id_gracza);
}
?>

<style>
    .sklep-header { background: linear-gradient(to bottom, rgba(0,0,0,0.5), #0a0a0a); padding: 40px; border: 1px solid #444; border-radius: 5px; margin-bottom: 20px; text-align: center; }
    .sklep-header h1 { font-family: 'Oswald'; color: #ffaa00; font-size: 3em; margin: 0; text-transform: uppercase; text-shadow: 0 0 10px #000; }
    .sklep-header p { color: #888; margin: 10px 0 0; font-style: italic; }
    .panel-postaci { background: #111; border: 1px solid #333; padding: 20px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
    .panel-postaci div { font-family: 'Oswald'; font-size: 1.1em; color: #aaa; }
    .panel-postaci b { color: #fff; }
    .kategorie-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; }
    .kategoria-tytul { font-family: 'Oswald'; color: #fff; text-transform: uppercase; border-bottom: 2px solid #444; padding-bottom: 10px; margin-bottom: 15px; }
    .przedmiot-karta { background: #050505; border: 1px solid #222; padding: 15px; border-radius: 4px; display: flex; flex-direction: column; margin-bottom: 10px; transition: 0.3s; }
    .przedmiot-karta:hover { border-color: #555; background: #0a0a0a; }
    .przedmiot-info h3 { margin: 0 0 5px 0; color: #fff; font-family: 'Oswald'; font-size: 1.2em; }
    .przedmiot-info p { margin: 0 0 10px 0; color: #888; font-size: 0.85em; font-style: italic; min-height: 40px; }
    .staty-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; margin-right: 5px; margin-bottom: 4px; }
    .badge-atak { background: #1a0a0a; border: 1px solid #ff3333; color: #ff3333; }
    .badge-obrona { background: #0a1a2a; border: 1px solid #00aaff; color: #00aaff; }
    .badge-szyb { background: #1a1500; border: 1px solid #ffaa00; color: #ffaa00; }
    .badge-unik { background: #0a1a0a; border: 1px solid #5aff9a; color: #5aff9a; }
    .badge-unik.minus { background: #1a0a0a; border-color: #ff3333; color: #ff3333; }
    .badge-cios { background: #111; border: 1px solid #555; color: #ccc; }
    .lvl-badge { display: inline-block; background: #0a1a0a; border: 1px solid #00ff00; color: #00ff00; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; }
    .przedmiot-akcja { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; border-top: 1px dashed #333; padding-top: 10px; }
    .cena { font-family: 'Oswald'; font-size: 1.3em; color: #ffaa00; }
    .btn-kup { background: transparent; color: #ffaa00; border: 1px solid #ffaa00; padding: 8px 15px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; }
    .btn-kup:hover:not(:disabled) { background: #ffaa00; color: #000; }
    .btn-disabled { background: #222; color: #555; border-color: #333; cursor: not-allowed; }
    .sukces { background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; }
    .blad { background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; }
    .info-warsztat { background: rgba(0,204,255,.06); border: 1px solid rgba(0,204,255,.3); color: #9fd8ff; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: .95em; }
</style>

<div class="sklep-header">
    <h1>Lombard "Rdza i Krew"</h1>
    <p>Trzynaście sztuk na wejście. Wszystko cięższe kuje Inżynier.</p>
</div>
<?php echo $komunikat; ?>

<div class="info-warsztat">
    Lombard trzyma broń do poziomu 12. Powyżej &mdash; tylko warsztat: Inżynier wytworzy albo ulepszy na zlecenie.
</div>

<div class="panel-postaci">
    <div>Kasa: <b style="color:#00ff00;"><?php echo number_format((int)$gracz['gotowka'], 0, '', ' '); ?> $</b><br>Poziom: <b style="color:#00ccff;"><?php echo (int)$gracz['poziom']; ?></b></div>
    <div style="text-align:right; border-left:1px solid #333; padding-left:15px;">
        Broń: <b style="color:#ff3333;"><?php echo htmlspecialchars($gracz['bron_zalozona'] ?: '—'); ?></b>
        <?php if ((int)$gracz['bron_stopien'] > 0) echo '<span style="color:#ffd700;">+'.(int)$gracz['bron_stopien'].'</span>'; ?>
        <span style="font-size:0.8em;">(+<?php echo (int)$gracz['bonus_atak']; ?> atk, +<?php echo (int)$gracz['bonus_szybkosc']; ?> szyb.)</span><br>
        Strój: <b style="color:#00aaff;"><?php echo htmlspecialchars($gracz['pancerz_zalozony'] ?: '—'); ?></b>
        <span style="font-size:0.8em;">(+<?php echo (int)$gracz['bonus_obrona']; ?> obr, <?php echo ((int)$gracz['bonus_unik'] >= 0 ? '+' : '').(int)$gracz['bonus_unik']; ?> unik)</span>
    </div>
</div>

<div class="kategorie-grid">
    <div>
        <h2 class="kategoria-tytul">🔪 Broń podręczna</h2>
        <?php foreach (bronie_sklep() as $kod => $b):
            $stac  = (int)$gracz['gotowka'] >= (int)$b['cena'];
            $lvl   = (int)$gracz['poziom'] >= (int)$b['poziom'];
            $mam   = isset($moje[$kod]) ? (int)$moje[$kod]['ilosc'] : 0;
            $noszę = ($gracz['bron_zalozona'] === $b['nazwa']);
        ?>
            <div class="przedmiot-karta"<?php if ($noszę) echo ' style="border-color:#ffaa00;"'; ?>>
                <div class="przedmiot-info">
                    <h3><?php echo $b['ikona'].' '.htmlspecialchars($b['nazwa']); ?>
                        <?php if ($noszę) echo '<span style="font-size:0.6em; color:#ffaa00;">(W DŁONI)</span>'; ?>
                        <?php if ($mam > 0) echo '<span style="font-size:0.6em; color:#666;">masz '.$mam.'</span>'; ?>
                    </h3>
                    <p><?php echo htmlspecialchars($opisy_broni[$kod] ?? ''); ?></p>
                    <span class="staty-badge badge-atak">Atak +<?php echo (int)$b['atak']; ?></span>
                    <span class="staty-badge badge-szyb">Szybkość +<?php echo (int)$b['szybkosc']; ?></span>
                    <span class="staty-badge badge-cios"><?php echo bronie_kategoria_nazwa($b['cios']); ?></span>
                    <span class="lvl-badge">Lvl <?php echo (int)$b['poziom']; ?></span>
                </div>
                <div class="przedmiot-akcja">
                    <span class="cena"><?php echo number_format((int)$b['cena'], 0, '', ' '); ?> $</span>
                    <?php if (!$lvl): ?><button class="btn-kup btn-disabled" disabled>ZBYT SŁABY</button>
                    <?php elseif (!$stac): ?><button class="btn-kup btn-disabled" disabled>BRAK $</button>
                    <?php else: ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                            <button type="submit" name="kup_bron" class="btn-kup"><?php echo $mam > 0 ? 'KUP JESZCZE' : 'KUP'; ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div>
        <h2 class="kategoria-tytul">🛡️ Ochrona ciała</h2>
        <?php $kat_p = eq_katalog_pancerzy(); foreach ($sklep_pancerze as $kod => $s):
            if (!isset($kat_p[$kod])) continue;
            $p     = $kat_p[$kod];
            $stac  = (int)$gracz['gotowka'] >= (int)$s['koszt'];
            $lvl   = (int)$gracz['poziom'] >= (int)$s['poziom'];
            $mam   = isset($moje[$kod]) ? (int)$moje[$kod]['ilosc'] : 0;
            $noszę = ($gracz['pancerz_zalozony'] === $p['nazwa']);
        ?>
            <div class="przedmiot-karta"<?php if ($noszę) echo ' style="border-color:#00aaff;"'; ?>>
                <div class="przedmiot-info">
                    <h3><?php echo $p['ikona'].' '.htmlspecialchars($p['nazwa']); ?>
                        <?php if ($noszę) echo '<span style="font-size:0.6em; color:#00aaff;">(NA SOBIE)</span>'; ?>
                        <?php if ($mam > 0) echo '<span style="font-size:0.6em; color:#666;">masz '.$mam.'</span>'; ?>
                    </h3>
                    <p><?php echo htmlspecialchars($s['opis']); ?></p>
                    <span class="staty-badge badge-obrona">Obrona +<?php echo (int)$p['obrona']; ?></span>
                    <span class="staty-badge badge-unik<?php echo (int)$p['unik'] < 0 ? ' minus' : ''; ?>">
                        Unik <?php echo ((int)$p['unik'] >= 0 ? '+' : '').(int)$p['unik']; ?>
                    </span>
                    <span class="lvl-badge">Lvl <?php echo (int)$s['poziom']; ?></span>
                </div>
                <div class="przedmiot-akcja">
                    <span class="cena"><?php echo number_format((int)$s['koszt'], 0, '', ' '); ?> $</span>
                    <?php if (!$lvl): ?><button class="btn-kup btn-disabled" disabled>ZBYT SŁABY</button>
                    <?php elseif (!$stac): ?><button class="btn-kup btn-disabled" disabled>BRAK $</button>
                    <?php else: ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                            <button type="submit" name="kup_pancerz" class="btn-kup"><?php echo $mam > 0 ? 'KUP JESZCZE' : 'KUP'; ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <p style="color:#666; font-size:.9em; font-style:italic; margin-top:15px;">
            Cięższy pancerz chroni, ale ujemny unik zjada farmę uników na arenie. Rozkład masz w ekwipunku.
        </p>
    </div>
</div>
