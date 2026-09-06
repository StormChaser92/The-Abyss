<?php
/* the-abyss/pages/warsztat.php
   Warsztat Inżynieryjny — wytwarzanie, ulepszanie +1…+10, cennik i zlecenia.
   Logika: includes/warsztat_logika.php (nic nie liczymy tutaj).
   Usunięty martwy blok pochodzenie_bonus($gracz_r, …) — bonusy idą w $mod. */

require_once "db.php";
require_once "config/pochodzenia.php";
require_once "includes/bronie_katalog.php";
require_once "includes/warsztat_logika.php";

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$pokoje = !empty($gracz['pokoje_specjalne']) ? json_decode($gracz['pokoje_specjalne'], true) : [];

if ($gracz['klasa'] !== 'Inżynier' || !in_array("Warsztat Inżynieryjny", (array)$pokoje, true)) {
    echo "<div style='padding:50px; text-align:center; color:#ff3333; font-family:Oswald,sans-serif; font-size:1.8em;'>
            Ulepszać broń może tylko Inżynier z własnym warsztatem.<br>
            <span style='font-size:.55em; color:#888;'>Jeśli szukasz ulepszenia, złóż zlecenie u kogoś, kto go ma.</span>
          </div>";
    exit;
}

/* Bonusy pochodzenia przekazywane jawnie — dawniej wisiały jako martwy kod. */
$mod = [
    'craft_energia_mult'         => pochodzenie_bonus($gracz, 'craft_energia_mult', 1.0),
    'inzynier_craft_fail_mult'   => pochodzenie_bonus($gracz, 'inzynier_craft_fail_mult', 1.0),
    'craft_bonus_produkt_szansa' => pochodzenie_bonus($gracz, 'craft_bonus_produkt_szansa', 0),
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['wytworz'])) {
        [$ok, $tekst] = warsztat_wytworz($polaczenie, $gracz, $_POST['kod'] ?? '', $mod);
        $komunikat = "<div class='".($ok ? 'sukces' : 'blad')."'>$tekst</div>";
    }
    if (isset($_POST['ulepsz'])) {
        [$ok, $tekst] = warsztat_ulepsz($polaczenie, $gracz, $_POST['kod'] ?? '', $id_gracza);
        $komunikat = "<div class='".($ok ? 'sukces' : 'blad')."'>$tekst</div>";
    }
    if (isset($_POST['ustaw_cene'])) {
        warsztat_ustaw_cene($polaczenie, $id_gracza, $_POST['kod'] ?? '', $_POST['typ'] ?? 'wytworz', (int)($_POST['cena'] ?? 0));
        $komunikat = "<div class='sukces'>Cennik zaktualizowany.</div>";
    }
    if (isset($_POST['wykonaj_zlecenie'])) {
        [$ok, $tekst] = zlecenie_wykonaj($polaczenie, $gracz, (int)($_POST['zid'] ?? 0));
        $komunikat = "<div class='".($ok ? 'sukces' : 'blad')."'>$tekst</div>";
    }
    $gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
}

$moje    = eq_stan($polaczenie, $id_gracza);
$kat     = bronie_katalog();
$tiery   = bronie_tiery();
$cennik  = warsztat_cennik($polaczenie, $id_gracza);
$zlecenia = zlecenia_otwarte($polaczenie, $id_gracza);

/* Broń, którą Inżynier posiada i która da się jeszcze ulepszyć. */
$do_ulepszenia = [];
foreach ($moje as $kod => $st) {
    if (!isset($kat[$kod]) || (int)$st['ilosc'] <= 0) continue;
    if ((int)$st['stopien'] >= WARSZTAT_STOPIEN_MAX) continue;
    $do_ulepszenia[$kod] = $st;
}
?>

<style>
    .warsztat-header { background: linear-gradient(to right, rgba(0,30,0,.8), rgba(0,0,0,.9)); padding: 36px; border: 1px solid rgba(0,255,0,.4); border-radius: 8px; margin-bottom: 22px; box-shadow: 0 0 25px rgba(0,255,0,.15); }
    .warsztat-header h1 { font-family: 'Oswald', sans-serif; color: #00ff00; font-size: 2.8em; margin: 0; text-transform: uppercase; text-shadow: 0 0 15px rgba(0,255,0,.6); letter-spacing: 1px; }
    .warsztat-header p { color: #ccc; font-size: 1.05em; margin: 10px 0 0; }

    .panel-zasobow { background: rgba(10,10,10,.6); border: 1px solid rgba(255,255,255,.05); padding: 22px; border-radius: 8px; display: grid; grid-template-columns: repeat(auto-fit, minmax(125px, 1fr)); gap: 14px; margin-bottom: 26px; box-shadow: inset 0 0 15px rgba(0,0,0,.5); }
    .zasob { text-align: center; padding: 14px; border-radius: 6px; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.05); }
    .zasob span { display: block; color: #888; font-size: .82em; text-transform: uppercase; font-family: 'Oswald', sans-serif; letter-spacing: 1px; margin-bottom: 5px; }
    .zasob b { color: #fff; font-size: 1.4em; font-weight: 700; }

    .sekcja { margin-bottom: 34px; }
    .sekcja-tytul { color: #00ccff; font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: 1.4em; border-bottom: 1px dashed rgba(0,204,255,.3); padding-bottom: 10px; margin-bottom: 18px; letter-spacing: 1px; display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .sekcja-tytul small { color: #666; font-size: .58em; letter-spacing: 1px; }

    .zlecenia-lista { display: grid; gap: 12px; margin-bottom: 10px; }
    .zlecenie { background: rgba(30,20,0,.5); border: 1px solid rgba(255,170,0,.35); border-radius: 6px; padding: 16px 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
    .zlecenie .kto { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 1px; color: #fff; }
    .zlecenie .co { color: #aaa; font-size: .92em; }
    .zlecenie .stawka { font-family: 'Oswald', sans-serif; color: #ffd700; font-size: 1.2em; }

    .kategoria-box { margin-bottom: 30px; background: rgba(5,5,5,.5); padding: 18px; border-radius: 8px; border: 1px solid rgba(255,255,255,.02); }
    .schematy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .schemat-karta { background: rgba(15,15,15,.7); border: 1px solid rgba(255,255,255,.05); padding: 18px; border-radius: 8px; display: flex; flex-direction: column; transition: .3s; }
    .schemat-karta:hover { border-color: rgba(0,255,0,.4); transform: translateY(-4px); background: rgba(20,30,20,.8); }
    .schemat-karta h3 { margin: 0 0 12px; color: #fff; font-family: 'Oswald', sans-serif; font-size: 1.15em; border-bottom: 1px solid rgba(255,255,255,.05); padding-bottom: 9px; display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
    .posiada-badge { font-size: .68em; background: rgba(0,0,0,.8); padding: 3px 8px; border-radius: 4px; color: #888; border: 1px solid rgba(255,255,255,.1); white-space: nowrap; }
    .wymagania { font-size: .9em; color: #aaa; flex-grow: 1; margin-bottom: 16px; line-height: 1.6; }
    .req-item { display: inline-block; background: rgba(0,0,0,.6); padding: 2px 6px; border-radius: 3px; margin: 0 4px 5px 0; border: 1px solid rgba(255,255,255,.05); }
    .req-item.brak { border-color: rgba(255,51,51,.5); color: #ff8888; }
    .req-item b { color: #fff; }
    .staty-bron { font-family: monospace; color: #ffd700; margin-bottom: 8px; }
    .cios-mini { font-family: 'Oswald', sans-serif; font-size: .78em; text-transform: uppercase; padding: 2px 7px; border-radius: 3px; border: 1px solid #555; color: #ccc; }

    .szansa-bar-bg { background: rgba(0,0,0,.8); height: 8px; border-radius: 4px; margin-top: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,.1); }
    .szansa-bar-fill { height: 100%; transition: width 1s; }

    .btn-wytworz { background: rgba(0,255,0,.1); color: #00ff00; border: 1px solid rgba(0,255,0,.5); padding: 11px; font-family: 'Oswald', sans-serif; cursor: pointer; text-transform: uppercase; width: 100%; border-radius: 4px; transition: .3s; font-weight: 700; letter-spacing: 1px; }
    .btn-wytworz:hover:not(:disabled) { background: #00ff00; color: #000; box-shadow: 0 0 20px rgba(0,255,0,.6); }
    .btn-ulepsz { background: rgba(255,215,0,.12); color: #ffd700; border: 1px solid rgba(255,215,0,.5); padding: 11px; font-family: 'Oswald', sans-serif; cursor: pointer; text-transform: uppercase; width: 100%; border-radius: 4px; transition: .3s; font-weight: 700; letter-spacing: 1px; }
    .btn-ulepsz:hover:not(:disabled) { background: #ffd700; color: #000; box-shadow: 0 0 20px rgba(255,215,0,.5); }
    .btn-mini { background: transparent; color: #ffaa00; border: 1px solid #ffaa00; padding: 7px 14px; font-family: 'Oswald', sans-serif; cursor: pointer; text-transform: uppercase; border-radius: 3px; }
    .btn-mini:hover { background: #ffaa00; color: #000; }
    .btn-disabled { background: rgba(10,10,10,.8) !important; border-color: rgba(255,255,255,.1) !important; color: #555 !important; cursor: not-allowed; }

    .cennik-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 10px; }
    .cennik-form select, .cennik-form input { background: #0a0a0a; border: 1px solid #333; color: #fff; padding: 8px; border-radius: 3px; font-family: 'Open Sans', sans-serif; }
    .cennik-tabela { width: 100%; border-collapse: collapse; font-size: .95em; }
    .cennik-tabela th { font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: .72em; letter-spacing: 1px; color: #00ccff; text-align: left; padding: 9px; border-bottom: 1px solid rgba(0,204,255,.3); }
    .cennik-tabela td { padding: 8px 9px; border-bottom: 1px solid rgba(255,255,255,.05); }

    .sukces { background: rgba(0,255,0,.1); border: 1px solid rgba(0,255,0,.3); color: #00ff00; padding: 18px; margin-bottom: 22px; text-align: center; border-radius: 6px; }
    .blad { background: rgba(255,51,51,.1); border: 1px solid rgba(255,51,51,.3); color: #ff3333; padding: 18px; margin-bottom: 22px; text-align: center; border-radius: 6px; }
    .pusto { color: #888; font-style: italic; }
</style>

<div class="warsztat-header">
    <h1>Manufaktura</h1>
    <p>Od rurki z barierki do karabinu przeciwpancernego. Kuj, ulepszaj, przyjmuj zlecenia &mdash; i licz się z tym, że ręka zadrży.</p>
</div>

<?php echo $komunikat; ?>

<div class="panel-zasobow">
    <div class="zasob"><span>Rusznikarstwo</span><b style="color:#00ff00; text-shadow:0 0 10px rgba(0,255,0,.4);"><?php echo number_format((float)$gracz['umiejetnosc_inzynierii'], 2); ?></b></div>
    <div class="zasob"><span>Inteligencja</span><b style="color:#ffaa00;"><?php echo (int)$gracz['inteligencja']; ?></b></div>
    <div class="zasob"><span>Energia</span><b style="color:#00ccff;"><?php echo (int)$gracz['energia_aktualna']; ?></b></div>
    <div class="zasob" style="border-left:1px dashed rgba(255,255,255,.1);"><span>Stal 🔩</span><b><?php echo (int)$gracz['zlom_stalowy']; ?></b></div>
    <div class="zasob"><span>Części ⚙️</span><b><?php echo (int)$gracz['czesci_mechaniczne']; ?></b></div>
    <div class="zasob"><span>Kevlar 🧵</span><b><?php echo (int)$gracz['syntetyki']; ?></b></div>
    <div class="zasob"><span>Elektronika 🔋</span><b><?php echo (int)$gracz['elektronika']; ?></b></div>
</div>

<?php /* ── ZLECENIA ──────────────────────────────────────────────── */ ?>
<div class="sekcja">
    <div class="sekcja-tytul">📋 Zlecenia <small>klient zapłacił z góry · przy porażce zwracasz kasę</small></div>
    <?php if (empty($zlecenia)): ?>
        <p class="pusto">Brak otwartych zleceń. Ustaw cennik niżej, żeby ktokolwiek mógł cokolwiek zamówić.</p>
    <?php else: ?>
        <div class="zlecenia-lista">
            <?php foreach ($zlecenia as $z):
                $b = $kat[$z['kod']] ?? null; if (!$b) continue;
                $ulepsz = ($z['typ'] === 'ulepsz');
            ?>
                <div class="zlecenie">
                    <div>
                        <div class="kto"><?php echo htmlspecialchars($z['klient']); ?></div>
                        <div class="co">
                            <?php echo $ulepsz ? 'ulepszenie' : 'wytworzenie'; ?>:
                            <b style="color:#fff;"><?php echo htmlspecialchars($b['nazwa']); ?></b>
                            <?php if ($ulepsz): ?>
                                &middot; stopień klienta: +<?php echo eq_stopien($polaczenie, (int)$z['klient_id'], $z['kod']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:14px;">
                        <span class="stawka"><?php echo number_format((int)$z['cena'], 0, '', ' '); ?> $</span>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="zid" value="<?php echo (int)$z['id']; ?>">
                            <button type="submit" name="wykonaj_zlecenie" class="btn-mini">Wykonaj</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="background:rgba(10,10,10,.55); border:1px solid rgba(255,255,255,.06); border-radius:6px; padding:18px; margin-top:14px;">
        <div style="font-family:'Oswald',sans-serif; text-transform:uppercase; letter-spacing:1px; color:#fff; margin-bottom:6px;">Twój cennik</div>
        <div style="color:#777; font-size:.9em; margin-bottom:10px;">Cenę ustalasz sam. Materiały i energia idą z Twoich zapasów, więc licz je w stawce.</div>
        <form method="POST" class="cennik-form">
            <select name="kod" required>
                <option value="">— wybierz broń —</option>
                <?php foreach ($kat as $kod => $b): ?>
                    <option value="<?php echo htmlspecialchars($kod); ?>"><?php echo htmlspecialchars($b['nazwa']); ?> (lvl <?php echo (int)$b['poziom']; ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="typ">
                <option value="wytworz">wytworzenie</option>
                <option value="ulepsz">ulepszenie o stopień</option>
            </select>
            <input type="number" name="cena" min="0" step="50" placeholder="cena w $" required>
            <button type="submit" name="ustaw_cene" class="btn-mini">Zapisz</button>
        </form>

        <?php if (!empty($cennik)): ?>
            <table class="cennik-tabela" style="margin-top:16px;">
                <thead><tr><th>Broń</th><th>Usługa</th><th>Cena</th></tr></thead>
                <tbody>
                <?php foreach ($cennik as $typ => $poz): foreach ($poz as $kod => $cena):
                    if (!isset($kat[$kod])) continue; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($kat[$kod]['nazwa']); ?></td>
                        <td style="color:#888;"><?php echo $typ === 'ulepsz' ? 'ulepszenie o stopień' : 'wytworzenie'; ?></td>
                        <td style="font-family:monospace; color:#ffd700;"><?php echo number_format((int)$cena, 0, '', ' '); ?> $</td>
                    </tr>
                <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php /* ── ULEPSZANIE ────────────────────────────────────────────── */ ?>
<div class="sekcja">
    <div class="sekcja-tytul">🔧 Ulepszanie <small>+5% ataku na stopień · porażka zbija o jeden, nie niszczy</small></div>
    <?php if (empty($do_ulepszenia)): ?>
        <p class="pusto">Nie masz broni, którą dałoby się jeszcze podnieść.</p>
    <?php else: ?>
        <div class="schematy-grid">
            <?php foreach ($do_ulepszenia as $kod => $st):
                $b  = $kat[$kod];
                $sp = (int)$st['stopien'];
                $sz = ulepszenie_szansa($gracz, $b, $sp);
                $mn = 0.35 * ($sp + 1);
                $r  = ['stal'=>(int)ceil($b['stal']*$mn), 'czesci'=>(int)ceil($b['czesci']*$mn),
                       'syn'=>(int)ceil($b['syn']*$mn),   'elek'=>(int)ceil($b['elek']*$mn)];
                $en = max(2, (int)ceil($b['en'] * 0.5 * ($sp + 1) * 0.6));
                $brak = ((int)$gracz['zlom_stalowy'] < $r['stal'] || (int)$gracz['czesci_mechaniczne'] < $r['czesci']
                      || (int)$gracz['syntetyki'] < $r['syn'] || (int)$gracz['elektronika'] < $r['elek']
                      || (int)$gracz['energia_aktualna'] < $en);
                $kolor = $sz >= 70 ? '#00ff00' : ($sz >= 40 ? '#ffaa00' : '#ff3333');
            ?>
                <div class="schemat-karta">
                    <h3><?php echo $b['ikona'].' '.htmlspecialchars($b['nazwa']); ?>
                        <span class="posiada-badge">+<?php echo $sp; ?> → +<?php echo $sp + 1; ?></span></h3>
                    <div class="wymagania">
                        <div class="staty-bron">atak <?php echo bron_atak($b, $sp); ?> → <b style="color:#fff;"><?php echo bron_atak($b, $sp + 1); ?></b></div>
                        <?php foreach ([['stal','Stal'],['czesci','Części'],['syn','Kevlar'],['elek','Elektronika']] as [$k, $ln]):
                            if ($r[$k] <= 0) continue;
                            $mapa = ['stal'=>'zlom_stalowy','czesci'=>'czesci_mechaniczne','syn'=>'syntetyki','elek'=>'elektronika'];
                            $ma = (int)$gracz[$mapa[$k]] >= $r[$k]; ?>
                            <span class="req-item<?php echo $ma ? '' : ' brak'; ?>"><?php echo $ln; ?>: <b><?php echo $r[$k]; ?></b></span>
                        <?php endforeach; ?>
                        <span class="req-item<?php echo (int)$gracz['energia_aktualna'] >= $en ? '' : ' brak'; ?>">Energia: <b><?php echo $en; ?></b></span>
                        <div style="margin-top:12px; font-family:'Oswald',sans-serif; text-transform:uppercase;">
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:#aaa;">Szansa</span>
                                <b style="color:<?php echo $kolor; ?>;"><?php echo round($sz); ?>%</b>
                            </div>
                            <div class="szansa-bar-bg"><div class="szansa-bar-fill" style="width:<?php echo round($sz); ?>%; background:<?php echo $kolor; ?>;"></div></div>
                        </div>
                        <?php if ($sp > 0): ?>
                            <div style="color:#ff8888; font-size:.86em; margin-top:8px;">Porażka: spadek na +<?php echo $sp - 1; ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" style="margin-top:auto;">
                        <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                        <button type="submit" name="ulepsz" class="btn-ulepsz<?php echo $brak ? ' btn-disabled' : ''; ?>"<?php echo $brak ? ' disabled' : ''; ?>>
                            <?php echo $brak ? 'Braki' : 'Ulepsz (-'.$en.' EN)'; ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php /* ── SCHEMATY ──────────────────────────────────────────────── */ ?>
<div class="sekcja-tytul">🛠️ Schematy <small>60 pozycji · Lombard kończy się na poziomie 12</small></div>
<?php foreach ($tiery as $nazwa_kat => $bronie): if (empty($bronie)) continue; ?>
    <div class="kategoria-box">
        <div class="sekcja-tytul" style="font-size:1.15em; border-bottom-style:solid;"><?php echo htmlspecialchars($nazwa_kat); ?></div>
        <div class="schematy-grid">
            <?php foreach ($bronie as $kod => $s):
                $mam = isset($moje[$kod]) ? (int)$moje[$kod]['ilosc'] : 0;
                $en  = max(1, (int)round($s['en'] * (float)$mod['craft_energia_mult']));
                $brak_mat = ((int)$gracz['zlom_stalowy'] < $s['stal'] || (int)$gracz['czesci_mechaniczne'] < $s['czesci']
                          || (int)$gracz['syntetyki'] < $s['syn'] || (int)$gracz['elektronika'] < $s['elek']);
                $brak_en  = ((int)$gracz['energia_aktualna'] < $en);
                $sz = min(95, rusznikarstwo_szansa($gracz, (int)$s['trudnosc']) / max(0.01, (float)$mod['inzynier_craft_fail_mult']));
                $kolor = $sz >= 70 ? '#00ff00' : ($sz >= 40 ? '#ffaa00' : '#ff3333');
            ?>
                <div class="schemat-karta">
                    <h3><?php echo $s['ikona'].' '.htmlspecialchars($s['nazwa']); ?>
                        <span class="posiada-badge">w szafce: <?php echo $mam; ?></span></h3>
                    <div class="wymagania">
                        <div class="staty-bron">+<?php echo (int)$s['atak']; ?> atak · +<?php echo (int)$s['szybkosc']; ?> szyb.
                            <span class="cios-mini"><?php echo bronie_kategoria_nazwa($s['cios']); ?></span></div>
                        <?php foreach ([['stal','Stal','zlom_stalowy'],['czesci','Części','czesci_mechaniczne'],['syn','Kevlar','syntetyki'],['elek','Elektronika','elektronika']] as [$k, $ln, $kol]):
                            if ((int)$s[$k] <= 0) continue;
                            $ma = (int)$gracz[$kol] >= (int)$s[$k]; ?>
                            <span class="req-item<?php echo $ma ? '' : ' brak'; ?>"><?php echo $ln; ?>: <b><?php echo (int)$s[$k]; ?></b></span>
                        <?php endforeach; ?>
                        <span class="req-item<?php echo $brak_en ? ' brak' : ''; ?>">Energia: <b><?php echo $en; ?></b></span>
                        <div style="margin-top:12px; font-family:'Oswald',sans-serif; text-transform:uppercase;">
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:#aaa;">Szansa kalibracji</span>
                                <b style="color:<?php echo $kolor; ?>;"><?php echo round($sz); ?>%</b>
                            </div>
                            <div class="szansa-bar-bg"><div class="szansa-bar-fill" style="width:<?php echo round($sz); ?>%; background:<?php echo $kolor; ?>;"></div></div>
                        </div>
                        <div style="color:#777; font-size:.85em; margin-top:8px;">Porażka: połowa materiałów i energii przepada.</div>
                    </div>
                    <form method="POST" style="margin-top:auto;">
                        <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                        <button type="submit" name="wytworz" class="btn-wytworz<?php echo ($brak_mat || $brak_en) ? ' btn-disabled' : ''; ?>"<?php echo ($brak_mat || $brak_en) ? ' disabled' : ''; ?>>
                            <?php echo $brak_mat ? 'Braki surowcowe' : ($brak_en ? 'Za mało energii' : 'Kuj (-'.$en.' EN)'); ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
