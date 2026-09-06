<?php
/* the-abyss/pages/ekwipunek.php
   Ekwipunek — broń, pancerze, zasoby, łupy.
   Katalog: includes/bronie_katalog.php + includes/ekwipunek_katalog.php
   Własność: tabela ekwipunek_gracza (eq_stan()), nie kolumny gracze.eq_*
   Nowość: stopień ulepszenia obok nazwy i panel rozkładu zdolności uniku. */

require_once "db.php";
require_once "includes/bronie_katalog.php";
require_once "includes/warsztat_logika.php";
require_once "includes/ekwipunek_katalog.php";

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['zaloz_bron'])) {
        [$ok, $tekst] = eq_zaloz_bron($polaczenie, $id_gracza, $_POST['kod'] ?? '');
        $komunikat = "<div class='".($ok ? 'sukces' : 'blad')."'>$tekst</div>";
    }
    if (isset($_POST['zaloz_pancerz'])) {
        [$ok, $tekst] = eq_zaloz_pancerz($polaczenie, $id_gracza, $_POST['kod'] ?? '');
        $komunikat = "<div class='".($ok ? 'sukces' : 'blad')."'>$tekst</div>";
    }
    if (isset($_POST['uzyj_apteczki'])) {
        $t = $polaczenie->query("SELECT apteczki, hp_aktualne, hp_max FROM gracze WHERE id=$id_gracza")->fetch_assoc();
        if ((int)$t['apteczki'] <= 0) {
            $komunikat = "<div class='blad'>Nie masz apteczek.</div>";
        } elseif ((int)$t['hp_aktualne'] >= (int)$t['hp_max']) {
            $komunikat = "<div class='blad'>Jesteś w pełni zdrowy.</div>";
        } else {
            $nowe = min((int)$t['hp_max'], (int)$t['hp_aktualne'] + 50);
            $polaczenie->query("UPDATE gracze SET hp_aktualne=$nowe, apteczki=apteczki-1 WHERE id=$id_gracza");
            $komunikat = "<div class='sukces'>Apteczka zużyta. Odzyskujesz zdrowie.</div>";
        }
    }
}

$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$moje  = eq_stan($polaczenie, $id_gracza);
$kat_b = bronie_katalog();
$kat_p = eq_katalog_pancerzy();
$rozklad = eq_rozklad_uniku($gracz);

$lupy = [];
$q = $polaczenie->query("SELECT nazwa, ilosc FROM przedmioty_gracze WHERE gracz_id=$id_gracza ORDER BY nazwa ASC");
while ($q && $w = $q->fetch_assoc()) if ((int)$w['ilosc'] > 0) $lupy[] = $w;

/* Podział posiadanej broni na kategorie ciosu — ułatwia wybór pod typ przeciwnika. */
$moja_bron = [];
foreach ($moje as $kod => $st) {
    if (!isset($kat_b[$kod]) || (int)$st['ilosc'] <= 0) continue;
    $moja_bron[$kat_b[$kod]['cios']][$kod] = $st;
}
$kolejnosc_ciosow = ['ostrze', 'tepe', 'palna', 'piesc'];
?>

<style>
    .eq-header { background: rgba(10,10,10,.6); padding: 30px; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: stretch; gap: 20px; flex-wrap: wrap; backdrop-filter: blur(10px); box-shadow: 0 5px 20px rgba(0,0,0,.5); }
    .eq-header h2 { margin: 0; color: #fff; font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: 1.8em; letter-spacing: 1px; align-self: center; }
    .zalozone-karta { background: rgba(0,0,0,.5); border: 1px solid rgba(255,255,255,.1); padding: 20px; border-radius: 6px; flex: 1 1 240px; box-shadow: inset 0 0 15px rgba(0,0,0,.8); }
    .zalozone-karta span.lbl { color: #888; font-size: .85em; text-transform: uppercase; display: block; margin-bottom: 8px; font-family: 'Oswald', sans-serif; }
    .zalozone-karta b { color: #fff; font-size: 1.25em; font-weight: 700; }
    .stopien { color: #ffd700; font-family: monospace; }

    .unik-panel { background: rgba(10,10,10,.6); border: 1px solid rgba(90,255,154,.25); border-radius: 8px; padding: 22px 24px; margin-bottom: 30px; }
    .unik-panel h3 { margin: 0 0 6px; font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 1px; color: #5aff9a; font-size: 1.2em; }
    .unik-panel .pod { color: #777; font-size: .88em; margin-bottom: 16px; }
    .unik-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .unik-item { background: rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.06); border-radius: 5px; padding: 12px 14px; }
    .unik-item span { display: block; color: #888; font-size: .78em; text-transform: uppercase; font-family: 'Oswald', sans-serif; letter-spacing: 1px; margin-bottom: 4px; }
    .unik-item b { font-size: 1.35em; font-family: monospace; color: #fff; }
    .unik-item.zero b { color: #ff3333; }
    .unik-item.suma { border-color: rgba(90,255,154,.4); background: rgba(90,255,154,.06); }
    .unik-item.suma b { color: #5aff9a; }

    .kategoria-eq { margin-top: 36px; }
    .kategoria-eq h3 { border-bottom: 1px solid rgba(255,255,255,.1); padding-bottom: 10px; color: #fff; font-family: 'Oswald', sans-serif; text-transform: uppercase; margin-bottom: 18px; font-size: 1.4em; letter-spacing: 1px; }
    .cios-grupa { margin-bottom: 26px; }
    .cios-tytul { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 2px; font-size: .95em; color: #aaa; margin-bottom: 10px; display: flex; align-items: baseline; gap: 10px; }
    .cios-tytul small { color: #666; letter-spacing: 0; text-transform: none; font-family: 'Open Sans', sans-serif; }

    .przedmioty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 18px; }
    .przedmiot-box { background: rgba(10,10,10,.5); border: 1px solid rgba(255,255,255,.05); padding: 18px; text-align: center; border-radius: 8px; transition: .3s; display: flex; flex-direction: column; backdrop-filter: blur(5px); box-shadow: 0 5px 15px rgba(0,0,0,.4); }
    .przedmiot-box:hover { background: rgba(20,20,20,.8); transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,.6); }
    .przedmiot-ikona { font-size: 2.8em; margin-bottom: 10px; text-shadow: 0 0 15px rgba(255,255,255,.2); }
    .przedmiot-nazwa { color: #fff; font-weight: 700; margin-bottom: 10px; font-size: 1.02em; min-height: 42px; display: flex; align-items: center; justify-content: center; }
    .badge-typ { font-family: 'Oswald', sans-serif; font-size: .78em; text-transform: uppercase; padding: 2px 8px; border-radius: 4px; margin-bottom: 8px; display: inline-block; }
    .typ-ostrze { background: rgba(74,214,255,.1); color: #4ad6ff; border: 1px solid #4ad6ff; }
    .typ-tepe   { background: rgba(255,122,61,.1); color: #ff7a3d; border: 1px solid #ff7a3d; }
    .typ-palna  { background: rgba(90,255,154,.1); color: #5aff9a; border: 1px solid #5aff9a; }
    .typ-piesc  { background: rgba(255,255,255,.06); color: #aaa; border: 1px solid #666; }
    .staty-linia { background: rgba(0,0,0,.8); color: #fff; padding: 7px; border-radius: 4px; font-family: monospace; font-size: .9em; margin-bottom: 6px; border: 1px solid rgba(255,255,255,.1); }
    .kontra { color: #777; font-size: .84em; margin-bottom: 12px; }
    .btn-akcja { background: rgba(255,170,0,.2); color: #ffaa00; border: 1px solid #ffaa00; padding: 10px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: 700; text-transform: uppercase; border-radius: 4px; width: 100%; transition: .3s; margin-top: auto; letter-spacing: 1px; }
    .btn-akcja:hover:not(:disabled) { background: #ffaa00; color: #000; box-shadow: 0 0 15px rgba(255,170,0,.6); }
    .btn-akcja-obrona { background: rgba(0,204,255,.2); color: #00ccff; border-color: #00ccff; }
    .btn-akcja-obrona:hover:not(:disabled) { background: #00ccff; color: #000; box-shadow: 0 0 15px rgba(0,204,255,.6); }
    .btn-disabled { background: rgba(10,10,10,.8) !important; border-color: rgba(255,255,255,.1) !important; color: #555 !important; cursor: not-allowed; }
    .sukces { background: rgba(0,255,0,.1); border: 1px solid rgba(0,255,0,.3); color: #00ff00; padding: 15px; margin-bottom: 20px; text-align: center; border-radius: 4px; }
    .blad { background: rgba(255,51,51,.1); border: 1px solid rgba(255,51,51,.3); color: #ff3333; padding: 15px; margin-bottom: 20px; text-align: center; border-radius: 4px; }
    .lup-box { background: rgba(20,0,30,.6); border: 1px solid rgba(221,136,255,.3); padding: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
    .lup-nazwa { color: #dd88ff; font-weight: 700; }
    .lup-ilosc { background: rgba(0,0,0,.8); color: #fff; font-family: monospace; font-size: 1.1em; padding: 5px 10px; border-radius: 4px; border: 1px solid rgba(255,255,255,.1); }
    .pusto { color: #888; font-style: italic; }
</style>

<div style="text-align:center; margin-bottom:26px;">
    <h1 style="font-family:'Oswald',sans-serif; color:#fff; font-size:2.6em; margin:0; text-transform:uppercase; letter-spacing:2px; text-shadow:0 0 20px rgba(255,255,255,.2);">Mój Ekwipunek</h1>
</div>

<?php echo $komunikat; ?>

<div class="eq-header">
    <h2>Obecne<br>wyposażenie</h2>
    <div class="zalozone-karta" style="border-color:rgba(255,170,0,.5);">
        <span class="lbl">W dłoniach</span>
        <b><?php echo htmlspecialchars($gracz['bron_zalozona'] ?: 'Goła pięść'); ?></b>
        <?php if ((int)$gracz['bron_stopien'] > 0): ?><span class="stopien">+<?php echo (int)$gracz['bron_stopien']; ?></span><?php endif; ?>
        <div style="margin-top:8px; font-family:monospace; color:#ffaa00;">
            +<?php echo (int)$gracz['bonus_atak']; ?> atak · +<?php echo (int)$gracz['bonus_szybkosc']; ?> szybkość
        </div>
        <div style="color:#777; font-size:.88em;"><?php echo eq_kontra_ciosu($gracz['arena_cios'] ?? 'piesc'); ?></div>
    </div>
    <div class="zalozone-karta" style="border-color:rgba(0,204,255,.5);">
        <span class="lbl">Na ciele</span>
        <b><?php echo htmlspecialchars($gracz['pancerz_zalozony'] ?: 'Nic'); ?></b>
        <div style="margin-top:8px; font-family:monospace; color:#00ccff;">
            +<?php echo (int)$gracz['bonus_obrona']; ?> obrona ·
            <span style="color:<?php echo (int)$gracz['bonus_unik'] < 0 ? '#ff3333' : '#5aff9a'; ?>;">
                <?php echo ((int)$gracz['bonus_unik'] >= 0 ? '+' : '').(int)$gracz['bonus_unik']; ?> unik
            </span>
        </div>
        <div style="color:#777; font-size:.88em;">
            Trwałość: broń <?php echo (int)$gracz['bron_trwalosc']; ?>/<?php echo (int)$gracz['bron_trwalosc_max']; ?> ·
            pancerz <?php echo (int)$gracz['pancerz_trwalosc']; ?>/<?php echo (int)$gracz['pancerz_trwalosc_max']; ?>
        </div>
    </div>
</div>

<div class="unik-panel">
    <h3>Zdolność uniku &mdash; z czego się składa</h3>
    <div class="pod">Ile razy przeciwnik w Dokach ma spudłować. Każde pudło to jeden mały unik; sto małych w walce to sufit 0,4 dużego.</div>
    <div class="unik-grid">
        <?php foreach ($rozklad as $nazwa => $wartosc):
            if ($nazwa === 'RAZEM') continue;
            $zero = ((float)$wartosc == 0.0);
        ?>
            <div class="unik-item<?php echo $zero ? ' zero' : ''; ?>">
                <span><?php echo htmlspecialchars($nazwa); ?></span>
                <b><?php echo $wartosc >= 0 ? '+' : ''; ?><?php echo $wartosc; ?></b>
            </div>
        <?php endforeach; ?>
        <div class="unik-item suma"><span>Razem</span><b><?php echo $rozklad['RAZEM']; ?></b></div>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color:#ffaa00; border-color:rgba(255,170,0,.3);">⚔️ Zbrojownia</h3>
    <?php if (empty($moja_bron)): ?>
        <p class="pusto">Zbrojownia pusta. Kup coś w Lombardzie albo zamów u Inżyniera.</p>
    <?php else: foreach ($kolejnosc_ciosow as $cios):
        if (empty($moja_bron[$cios])) continue; ?>
        <div class="cios-grupa">
            <div class="cios-tytul"><?php echo eq_nazwa_ciosu($cios); ?> <small><?php echo eq_kontra_ciosu($cios); ?></small></div>
            <div class="przedmioty-grid">
                <?php foreach ($moja_bron[$cios] as $kod => $st):
                    $b = $kat_b[$kod];
                    $stopien = (int)$st['stopien'];
                    $atak    = bron_atak($b, $stopien);
                    $noszę   = ($gracz['bron_zalozona'] === $b['nazwa']);
                ?>
                    <div class="przedmiot-box"<?php if ($noszę) echo ' style="border-color:#ffaa00; box-shadow:inset 0 0 15px rgba(255,170,0,.2);"'; ?>>
                        <div class="przedmiot-ikona"><?php echo $b['ikona']; ?></div>
                        <div class="badge-typ typ-<?php echo $b['cios']; ?>"><?php echo bronie_kategoria_nazwa($b['cios']); ?></div>
                        <div class="przedmiot-nazwa">
                            <?php echo htmlspecialchars($b['nazwa']); ?>
                            <?php if ($stopien > 0) echo ' <span class="stopien">+'.$stopien.'</span>'; ?>
                        </div>
                        <div class="staty-linia">+<?php echo $atak; ?> atak · +<?php echo (int)$b['szybkosc']; ?> szyb.</div>
                        <?php if ($stopien > 0): ?>
                            <div class="kontra">bazowo <?php echo (int)$b['atak']; ?> · ulepszenie +<?php echo $stopien * 5; ?>%</div>
                        <?php else: ?>
                            <div class="kontra">sztuk: <?php echo (int)$st['ilosc']; ?> · lvl <?php echo (int)$b['poziom']; ?></div>
                        <?php endif; ?>
                        <?php if ($noszę): ?>
                            <button class="btn-akcja btn-disabled" disabled>W DŁONI</button>
                        <?php else: ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                                <button type="submit" name="zaloz_bron" class="btn-akcja">Weź do ręki</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<div class="kategoria-eq">
    <h3 style="color:#00ccff; border-color:rgba(0,204,255,.3);">🛡️ Pancerze</h3>
    <div class="przedmioty-grid">
        <?php $ma_p = false; foreach ($kat_p as $kod => $p):
            $ilosc = isset($moje[$kod]) ? (int)$moje[$kod]['ilosc'] : 0;
            if ($ilosc <= 0) continue;
            $ma_p = true;
            $noszę = ($gracz['pancerz_zalozony'] === $p['nazwa']);
        ?>
            <div class="przedmiot-box"<?php if ($noszę) echo ' style="border-color:#00ccff; box-shadow:inset 0 0 15px rgba(0,204,255,.2);"'; ?>>
                <div class="przedmiot-ikona"><?php echo $p['ikona']; ?></div>
                <div class="przedmiot-nazwa"><?php echo htmlspecialchars($p['nazwa']); ?></div>
                <div class="staty-linia">
                    +<?php echo (int)$p['obrona']; ?> obrona ·
                    <span style="color:<?php echo (int)$p['unik'] < 0 ? '#ff3333' : '#5aff9a'; ?>;">
                        <?php echo ((int)$p['unik'] >= 0 ? '+' : '').(int)$p['unik']; ?> unik
                    </span>
                </div>
                <div class="kontra"><?php echo (int)$p['unik'] < 0 ? 'chroni, ale spowalnia' : 'lekkie, nie krępuje'; ?> · sztuk: <?php echo $ilosc; ?></div>
                <?php if ($noszę): ?>
                    <button class="btn-akcja btn-akcja-obrona btn-disabled" disabled>NA SOBIE</button>
                <?php else: ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                        <button type="submit" name="zaloz_pancerz" class="btn-akcja btn-akcja-obrona">Ubierz</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; if (!$ma_p) echo '<p class="pusto">Nie masz zapasowych pancerzy.</p>'; ?>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color:#ff3333; border-color:rgba(255,51,51,.3);">💉 Zasoby</h3>
    <div class="przedmioty-grid">
        <div class="przedmiot-box" style="border-color:rgba(255,51,51,.3);">
            <div class="przedmiot-ikona">💊</div>
            <div class="przedmiot-nazwa">Apteczka Uliczna</div>
            <div class="staty-linia" style="color:#ff3333;"><?php echo (int)$gracz['apteczki']; ?> szt.</div>
            <div class="kontra">na arenie odpala się sama poniżej 30% HP</div>
            <?php if ((int)$gracz['apteczki'] > 0): ?>
                <form method="POST" style="margin:0;"><button type="submit" name="uzyj_apteczki" class="btn-akcja" style="background:rgba(255,51,51,.2); color:#ff3333; border-color:#ff3333;">Użyj (+50 HP)</button></form>
            <?php else: ?>
                <button class="btn-akcja btn-disabled" disabled>Brak</button>
            <?php endif; ?>
        </div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">🔩</div><div class="przedmiot-nazwa">Stalowy Złom</div><div class="staty-linia"><?php echo (int)$gracz['zlom_stalowy']; ?> szt.</div></div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">⚙️</div><div class="przedmiot-nazwa">Części Mechaniczne</div><div class="staty-linia"><?php echo (int)$gracz['czesci_mechaniczne']; ?> szt.</div></div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">🧵</div><div class="przedmiot-nazwa">Kevlar i Syntetyki</div><div class="staty-linia"><?php echo (int)$gracz['syntetyki']; ?> szt.</div></div>
        <div class="przedmiot-box"><div class="przedmiot-ikona">🔋</div><div class="przedmiot-nazwa">Elektronika</div><div class="staty-linia"><?php echo (int)$gracz['elektronika']; ?> szt.</div></div>
    </div>
</div>

<div class="kategoria-eq">
    <h3 style="color:#dd88ff; border-color:rgba(221,136,255,.3);">💎 Łupy i komponenty</h3>
    <?php if (empty($lupy)): ?>
        <p class="pusto">Nic tu nie ma. Komponenty kupisz u Szabrownika albo zdejmiesz z bossa &mdash; zwykli przeciwnicy z areny nie zostawiają łupu.</p>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:14px;">
            <?php foreach ($lupy as $l): ?>
                <div class="lup-box">
                    <div class="lup-nazwa"><?php echo htmlspecialchars($l['nazwa']); ?></div>
                    <div class="lup-ilosc">×<?php echo (int)$l['ilosc']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
