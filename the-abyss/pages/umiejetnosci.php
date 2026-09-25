<?php
require_once "db.php";
require_once __DIR__ . '/../config/umiejetnosci.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* ═══════════════════════════════════════════════════════════════════════
   UMIEJĘTNOŚCI FABULARNE
   Pula PU liczona z postaci (config/umiejetnosci.php), nie przechowywana.
   Poziomy 1–4 po 1 PU, poziom 5 za 2 PU. Jeden darmowy reset.
   ═══════════════════════════════════════════════════════════════════════ */

$KOLUMNY = "umiejetnosci, poziom, pochodzenie, zalety, profesja_fabularna, profesja_etap, profesja2, profesja2_etap,
            um_reset_uzyty, sila, zrecznosc, wytrzymalosc, inteligencja, zmysly, charyzma";
$g = db_wiersz($polaczenie, "SELECT $KOLUMNY FROM gracze WHERE id = ?", [$id_gracza]);
$um_raw = $g['umiejetnosci'];
$um = $um_raw ? (json_decode($um_raw, true) ?: []) : [];
$LISTA = um_lista();

// Poziomy powyżej 5 ze starego systemu przycinamy raz — nadwyżka PU wraca do puli sama.
$przyciete = false;
foreach ($um as $n => $l) if ((int)$l > UM_MAX_POZIOM) { $um[$n] = UM_MAX_POZIOM; $przyciete = true; }
if ($przyciete) {
    $nowy = json_encode($um, JSON_UNESCAPED_UNICODE);
    db_zmien($polaczenie, "UPDATE gracze SET umiejetnosci = ? WHERE id = ? AND umiejetnosci <=> ?", [$nowy, $id_gracza, $um_raw]);
    $um_raw = $nowy;
}

$pula   = um_pula($g);
$wydane = um_wydane($um);
$wolne  = $pula - $wydane;
$blad = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zapisz_umiejetnosci'])) {
    $nowe = $um; $koszt = 0; $zmian = 0;
    foreach ((array)($_POST['um'] ?? []) as $idx => $cel) {
        $idx = (int)$idx; $cel = (int)$cel;
        if (!isset($LISTA[$idx])) continue;
        $n = $LISTA[$idx]['n']; $teraz = (int)($um[$n] ?? 0);
        if ($cel <= $teraz) continue;
        if ($cel > UM_MAX_POZIOM) { $blad = "Maksymalny poziom Umiejętności to " . UM_MAX_POZIOM . "."; break; }
        $koszt += um_koszt_do($cel) - um_koszt_do($teraz);
        $nowe[$n] = $cel; $zmian++;
    }
    if (!$blad && $zmian === 0) $blad = "Nie wybrano żadnych zmian.";
    if (!$blad && $koszt > $wolne) $blad = "Za mało Punktów Umiejętności.";
    if (!$blad) {
        // Warunek na stary stan JSON — dwa zapisy naraz nie wydadzą tych samych PU.
        $ok = db_zmien($polaczenie, "UPDATE gracze SET umiejetnosci = ? WHERE id = ? AND umiejetnosci <=> ?",
                       [json_encode($nowe, JSON_UNESCAPED_UNICODE), $id_gracza, $um_raw]);
        if ($ok) { echo "<script>location.href='game.php?page=umiejetnosci&ok=" . $zmian . "';</script>"; exit; }
        $blad = "Karta zmieniła się w międzyczasie. Odśwież stronę i spróbuj ponownie.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_umiejetnosci'])) {
    $ok = db_zmien($polaczenie, "UPDATE gracze SET umiejetnosci = '{}', um_reset_uzyty = 1 WHERE id = ? AND um_reset_uzyty = 0", [$id_gracza]);
    if ($ok) { echo "<script>location.href='game.php?page=umiejetnosci&reset=1';</script>"; exit; }
    $blad = "Darmowy reset został już wykorzystany.";
}

$atr = [];
foreach ($UM_ATRYBUTY as $k => $a) $atr[$k] = (int)($g[$a['kolumna']] ?? 0);
$dane_js = [
    'pe' => UM_PE, 'max' => UM_MAX_POZIOM, 'pula' => $pula, 'wydane' => $wydane,
    'reset' => (bool)$g['um_reset_uzyty'],
    'atrNazwy' => array_map(fn($a) => $a['nazwa'], $UM_ATRYBUTY), 'atr' => $atr,
    'kat' => array_map(fn($k) => ['nazwa' => $k['nazwa'], 'kolor' => $k['kolor']], $UM_KATEGORIE),
    'um' => array_map(fn($u) => [$u['kat'], $u['n'], $u['g'], $u['d'], $u['o'], min(UM_MAX_POZIOM, (int)($um[$u['n']] ?? 0))], $LISTA),
];
$zrodla = um_zrodla($g);
$komunikat = isset($_GET['ok']) ? "Zapisano rozwój — zmian: " . (int)$_GET['ok'] . "." : (isset($_GET['reset']) ? "Reset wykonany. PU wróciły do puli." : "");
?>
<style>
.ku{display:flex;flex-direction:column;gap:18px;padding-bottom:10px}
.ku .mono{font-family:'JetBrains Mono',monospace}
.ku .lbl{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.6px;text-transform:uppercase;color:var(--txt-dim)}
.ku-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;border-bottom:1px solid var(--border-soft);padding-bottom:14px}
.ku-head h1{font-family:'Oswald',sans-serif;font-weight:600;font-size:2.4em;letter-spacing:4px;text-transform:uppercase;color:#fff;text-shadow:0 0 14px rgba(255,23,68,.55)}
.ku-head p{color:var(--txt-dim);max-width:560px;line-height:1.45;text-wrap:pretty}
.ku-msg{padding:12px 16px;border:1px solid var(--neon-green);color:var(--neon-green);font-family:'JetBrains Mono',monospace;font-size:.85em}
.ku-msg.err{border-color:var(--border-hot);color:var(--neon-red-hot)}
.ku-top{display:grid;grid-template-columns:minmax(0,340px) minmax(0,1fr);gap:14px}
.ku-panel{background:var(--bg-panel);border:1px solid var(--border-soft);border-radius:2px;padding:16px 18px;backdrop-filter:blur(6px)}
.pu-big{display:flex;align-items:baseline;gap:10px;margin:6px 0 12px}
.pu-big b{font-family:'Oswald',sans-serif;font-size:3.2em;line-height:1;color:#fff;text-shadow:0 0 14px rgba(255,23,68,.7)}
.pu-big span{color:var(--txt-dim);font-family:'JetBrains Mono',monospace;font-size:.8em}
.pu-bar{height:6px;background:rgba(255,255,255,.06);position:relative;margin-bottom:14px;overflow:hidden}
.pu-bar i{position:absolute;top:0;bottom:0;left:0;background:var(--neon-red);box-shadow:0 0 10px var(--neon-red)}
.pu-bar em{position:absolute;top:0;bottom:0;background:repeating-linear-gradient(45deg,var(--neon-gold) 0 4px,transparent 4px 7px)}
.pu-src{display:flex;flex-direction:column;gap:5px}
.pu-src div{display:flex;justify-content:space-between;gap:10px;font-size:.95em}
.pu-src div span:last-child{font-family:'JetBrains Mono',monospace;color:var(--txt-main)}
.pu-src div.off,.pu-src div.off span:last-child{color:var(--txt-mute)}
.pu-src .sum{border-top:1px solid var(--border-soft);padding-top:6px;margin-top:3px;font-weight:700}
.attr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:8px;margin-top:10px}
.attr{border:1px solid rgba(255,255,255,.07);background:rgba(0,0,0,.25);padding:10px 12px;display:flex;flex-direction:column;gap:6px}
.attr .n{display:flex;justify-content:space-between;align-items:baseline}
.attr .n b{font-family:'Oswald',sans-serif;font-size:1.7em;color:#fff}
.attr .t{height:4px;background:rgba(255,255,255,.07);position:relative}
.attr .t i{position:absolute;left:0;top:0;bottom:0;background:var(--neon-cyan)}
.attr .t u{position:absolute;top:-3px;bottom:-3px;width:2px;background:var(--neon-gold)}
.attr small{color:var(--txt-dim);font-family:'JetBrains Mono',monospace;font-size:.7em}
.attr-note{margin-top:12px;color:var(--txt-dim);font-size:.92em;line-height:1.45}
.attr-note b{color:var(--txt-main)}
.pe-key{display:inline-block;width:10px;height:2px;background:var(--neon-gold);vertical-align:middle;margin-right:4px}
details.ku-basic{background:var(--bg-panel);border:1px solid var(--border-soft)}
details.ku-basic summary{list-style:none;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;color:var(--txt-main)}
details.ku-basic summary::-webkit-details-marker{display:none}
details.ku-basic summary::after{content:'+';font-family:'JetBrains Mono',monospace;color:var(--neon-red-hot)}
details.ku-basic[open] summary::after{content:'−'}
.ku-basic-body{padding:0 18px 16px;color:var(--txt-dim);line-height:1.5;display:flex;flex-direction:column;gap:10px}
.ku-basic-body ul{padding-left:18px;display:flex;flex-direction:column;gap:4px}
.ku-basic-body li{color:var(--txt-main)}
.ku-tools{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.ku-tools input[type=search]{flex:1 1 220px;background:rgba(0,0,0,.4);border:1px solid var(--border-mid);color:var(--txt-main);padding:9px 12px;font-family:inherit;font-size:1em;outline:none}
.ku-tools input[type=search]:focus{border-color:var(--border-hot)}
.ku-chips{display:flex;flex-wrap:wrap;gap:6px}
.ku-chip{background:transparent;border:1px solid rgba(255,255,255,.12);color:var(--txt-dim);padding:6px 10px;font-size:.88em;font-weight:600;letter-spacing:.5px;font-family:inherit;cursor:pointer}
.ku-chip:hover{color:#fff;border-color:var(--border-mid)}
.ku-chip.on{color:#fff;border-color:var(--c,var(--neon-red));box-shadow:inset 0 0 0 1px var(--c,var(--neon-red))}
.ku-tgl{display:flex;align-items:center;gap:6px;color:var(--txt-dim);font-weight:600;cursor:pointer;user-select:none}
.ku-tgl input{accent-color:var(--neon-red)}
.ku-kat{display:flex;flex-direction:column;gap:6px;margin-bottom:8px}
.ku-kat h2{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:3px;text-transform:uppercase;font-size:1.05em;color:var(--c);display:flex;align-items:center;gap:10px;margin:8px 0 2px}
.ku-kat h2::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,var(--c),transparent);opacity:.4}
.ku-kat h2 span{font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--txt-mute);letter-spacing:1px}
.ku-row{display:grid;grid-template-columns:minmax(0,1fr) 150px 150px 130px 76px;gap:14px;align-items:center;padding:10px 14px;background:var(--bg-card);border:1px solid rgba(255,255,255,.05);border-left:2px solid transparent}
.ku-row:hover{border-color:rgba(255,255,255,.1);border-left-color:var(--c)}
.ku-row.has{border-left-color:var(--c)}
.ku-row .nm b{font-weight:700;font-size:1.05em;color:#fff}
.ku-row .nm p{color:var(--txt-dim);font-size:.9em;line-height:1.35;margin-top:2px;text-wrap:pretty}
.ku-atrs{display:flex;gap:5px;flex-wrap:wrap}
.ku-at{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1px;padding:3px 6px;text-transform:uppercase;white-space:nowrap}
.ku-at.g{background:rgba(74,214,255,.14);color:var(--neon-cyan);border:1px solid rgba(74,214,255,.4)}
.ku-at.d{color:var(--txt-dim);border:1px dashed rgba(255,255,255,.2)}
.ku-pips{display:flex;gap:4px;align-items:center}
.ku-pip{height:12px;flex:1;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08)}
.ku-pip.w2{flex:2}
.ku-pip.s{background:var(--c);border-color:var(--c);box-shadow:0 0 6px var(--c)}
.ku-pip.p{background:repeating-linear-gradient(45deg,var(--neon-gold) 0 3px,rgba(255,215,0,.25) 3px 6px);border-color:var(--neon-gold)}
.ku-lvl{font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);margin-top:5px;display:flex;justify-content:space-between}
.ku-ch{display:flex;flex-direction:column;gap:2px}
.ku-ch b{font-family:'Oswald',sans-serif;font-size:1.45em;color:#fff;line-height:1}
.ku-ch b.cap{color:var(--neon-gold)}
.ku-ch small{font-family:'JetBrains Mono',monospace;font-size:.68em;color:var(--txt-dim);line-height:1.35}
.ku-ch.none b{color:var(--txt-mute);font-size:1.1em}
.ku-btns{display:flex;gap:4px;justify-content:flex-end}
.ku-btns button{width:34px;height:34px;background:rgba(0,0,0,.4);border:1px solid var(--border-mid);color:#fff;font-family:'JetBrains Mono',monospace;font-size:1.1em;cursor:pointer}
.ku-btns button:hover:not(:disabled){border-color:var(--border-hot);background:rgba(255,23,68,.15)}
.ku-btns button:disabled{opacity:.25;cursor:not-allowed}
.ku-empty{padding:30px;text-align:center;color:var(--txt-mute);font-family:'JetBrains Mono',monospace}
.ku-bar{position:sticky;bottom:0;z-index:20;background:rgba(5,6,12,.94);border:1px solid var(--border-mid);backdrop-filter:blur(8px);padding:12px 18px;display:flex;flex-wrap:wrap;align-items:center;gap:14px}
.ku-bar-info{flex:1 1 260px;display:flex;flex-wrap:wrap;gap:18px;align-items:baseline}
.ku-bar-info b{font-family:'Oswald',sans-serif;font-size:1.5em;color:var(--neon-gold)}
.ku-btn{padding:10px 18px;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:.95em;border:1px solid var(--border-mid);background:transparent;color:var(--txt-main);cursor:pointer}
.ku-btn:hover:not(:disabled){border-color:var(--border-hot);color:#fff}
.ku-btn.pri{background:var(--neon-red);border-color:var(--neon-red);color:#fff;box-shadow:0 0 14px rgba(255,23,68,.45)}
.ku-btn.pri:hover:not(:disabled){background:var(--neon-red-hot)}
.ku-btn:disabled{opacity:.3;cursor:not-allowed}
.ku-btn.ghost{border-color:transparent;color:var(--txt-dim);padding:10px 6px;letter-spacing:1px;font-size:.85em}
.ku-btn.ghost:hover:not(:disabled){color:var(--neon-red-hot)}
.ku-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;padding:20px}
.ku-modal.on{display:flex}
.ku-modal-box{max-width:460px;width:100%;background:#0c0910;border:1px solid var(--border-hot);padding:22px;display:flex;flex-direction:column;gap:14px;box-shadow:0 0 40px rgba(255,23,68,.25)}
.ku-modal-box h3{font-family:'Oswald',sans-serif;letter-spacing:3px;text-transform:uppercase;color:#fff}
.ku-modal-box p{color:var(--txt-dim);line-height:1.5}
.ku-modal-box ul{list-style:none;display:flex;flex-direction:column;gap:4px;max-height:200px;overflow:auto}
.ku-modal-box li{display:flex;justify-content:space-between;gap:10px;font-family:'JetBrains Mono',monospace;font-size:.85em}
.ku-modal-act{display:flex;justify-content:flex-end;gap:8px}
@media (max-width:860px){.ku-top{grid-template-columns:minmax(0,1fr)}.ku-row{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"nm btns" "atrs atrs" "pips ch"}.ku-row .nm{grid-area:nm}.ku-row .ku-atrs{grid-area:atrs}.ku-row .pw{grid-area:pips}.ku-row .ku-ch{grid-area:ch;text-align:right}.ku-row .ku-btns{grid-area:btns}}
</style>

<div class="ku">
<header class="ku-head">
<div><div class="lbl">Karta postaci · Fabuła</div><h1>Umiejętności</h1></div>
<p>Poziom Umiejętności decyduje o szansie powodzenia działań postaci w Opowieściach. Mistrz Gry rzuca k100 na Umiejętność razem z przypisanym do niej Atrybutem.</p>
</header>

<?php if ($blad): ?><div class="ku-msg err">⚠ <?php echo htmlspecialchars($blad); ?></div><?php endif; ?>
<?php if ($komunikat && !$blad): ?><div class="ku-msg"><?php echo htmlspecialchars($komunikat); ?></div><?php endif; ?>

<section class="ku-top">
<div class="ku-panel">
<div class="lbl">Wolne Punkty Umiejętności</div>
<div class="pu-big"><b id="puFree"><?php echo $wolne; ?></b><span>/ <?php echo $pula; ?> PU · maks. ok. 60</span></div>
<div class="pu-bar"><i id="puSpent" style="width:<?php echo $pula ? round($wydane / $pula * 100, 2) : 0; ?>%"></i><em id="puPend"></em></div>
<div class="pu-src">
<?php foreach ($zrodla as [$et, $pkt, $akt]): ?>
<div class="<?php echo $akt ? '' : 'off'; ?>"><span><?php echo htmlspecialchars($et); ?></span><span><?php echo $pkt === null ? '—' : '+' . (int)$pkt; ?></span></div>
<?php endforeach; ?>
<div class="sum"><span>Razem</span><span><?php echo $pula; ?> PU</span></div>
<div><span>Wydane</span><span>−<?php echo $wydane; ?></span></div>
</div>
</div>
<div class="ku-panel">
<div class="lbl">Atrybuty · skala 0–100</div>
<div class="attr-grid">
<?php foreach ($UM_ATRYBUTY as $k => $a): $v = $atr[$k]; ?>
<div class="attr"><div class="n"><span class="lbl"><?php echo $a['nazwa']; ?></span><b><?php echo $v; ?></b></div><div class="t"><i style="width:<?php echo min(100, $v); ?>%"></i><u style="left:<?php echo UM_PE; ?>%"></u></div><small>⅓ do testu: +<?php echo intdiv($v, 3); ?></small></div>
<?php endforeach; ?>
</div>
<p class="attr-note">Szansa na teście Umiejętności = <b>poziom × 20</b> + <b>⅓ Atrybutu</b> (w dół). Mistrz Gry dolicza modyfikator od −50 do +50. Wynik nie przekroczy <span class="pe-key"></span><b>Progu Efektywności (<?php echo UM_PE; ?>%)</b>. Atrybut dodatkowy (ramka przerywana) wchodzi do testu, gdy MG uzna, że sytuacja to uzasadnia.</p>
</div>
</section>

<details class="ku-basic">
<summary>Umiejętności podstawowe — ma je każda postać</summary>
<div class="ku-basic-body">
<p>Umiejętności podstawowe opisują podstawowe kompetencje każdego mieszkańca The Abyss. Posiadają je wszystkie istoty rozumne, chyba że dana postać ma również Wadę, która wprowadza jakieś ograniczenie.</p>
<ul>
<li>czytania i pisania we wszystkich znanych przez siebie językach,</li>
<li>rachowania — potrafi liczyć na poziomie umożliwiającym zakupy, handel czy kontrolę wydatków,</li>
<li>teologii i historii kraju skąd pochodzi (w wersji przekazywanej przez rodzime państwo bądź społeczność),</li>
<li>przygotowywania prostych posiłków, przetworów i napojów,</li>
<li>tańca i śpiewu — potrafi dostatecznie odtworzyć podstawowe kroki przynajmniej jednego tańca popularnego w środowisku, z którego się wywodzi, zna też kilka rodzimych piosenek i ich teksty,</li>
<li>pływania i utrzymywania się na wodzie w dogodnych warunkach atmosferycznych,</li>
<li>jazdy autem lub konno — potrafi jeździć wierzchem, stępem oraz kłusem po równej, bezpiecznej powierzchni w celu przemieszczania się [ta zasada dotyczy tylko jazdy końmi, nie innych wierzchowców],</li>
<li>używania popularnych produktów medycznych — rozpoznaje i umie zaaplikować najpowszechniejsze środki zbijające gorączkę, uśmierzające ból, a także zaopatrywać rany w sytuacjach niezagrażających istotnie zdrowiu i życiu,</li>
<li>prostych napraw oręża, wyposażenia i ubrań (w tym proste czynności krawieckie) oraz konserwacji broni, którą się posługuje.</li>
</ul>
<p>Akcje odpowiadające wszystkim tu wymienionym w typowych sytuacjach uważa się za automatycznie udane. Chcąc zapewnić swojej postaci wyższe kompetencje w zakresie któregokolwiek z powyższych aspektów, należy wybrać odpowiednią Umiejętność.</p>
</div>
</details>

<div class="ku-tools">
<input type="search" id="kuQ" placeholder="Szukaj umiejętności…">
<div class="ku-chips" id="kuChips"></div>
<label class="ku-tgl"><input type="checkbox" id="kuOnly"> Tylko rozwinięte</label>
</div>

<form method="POST" action="game.php?page=umiejetnosci" id="kuForm">
<input type="hidden" name="zapisz_umiejetnosci" value="1">
<div id="kuList"></div>
<div id="kuInputs"></div>
</form>
<form method="POST" action="game.php?page=umiejetnosci" id="kuResetForm"><input type="hidden" name="reset_umiejetnosci" value="1"></form>

<div class="ku-bar">
<div class="ku-bar-info">
<span class="lbl">Do wydania <b id="bPend">0</b> PU</span>
<span class="lbl">Po zapisie zostanie <b id="bLeft" style="color:#fff"><?php echo $wolne; ?></b> PU</span>
</div>
<button type="button" class="ku-btn ghost" id="bReset" <?php echo ($g['um_reset_uzyty'] || $wydane === 0) ? 'disabled' : ''; ?>>Reset umiejętności <?php echo $g['um_reset_uzyty'] ? '(wykorzystany)' : '(1× za darmo)'; ?></button>
<button type="button" class="ku-btn" id="bUndo" disabled>Cofnij</button>
<button type="button" class="ku-btn pri" id="bSave" disabled>Zatwierdź</button>
</div>
</div>

<div class="ku-modal" id="kuModal"><div class="ku-modal-box">
<h3 id="mT"></h3><p id="mP"></p><ul id="mL"></ul>
<div class="ku-modal-act"><button type="button" class="ku-btn" id="mNo">Anuluj</button><button type="button" class="ku-btn pri" id="mYes">Potwierdź</button></div>
</div></div>

<script>
(function(){
const D=<?php echo json_encode($dane_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const A=D.atrNazwy,PE=D.pe,MAX=D.max,$=id=>document.getElementById(id);
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
let pend={},kat="all";
const koszt=l=>l>=5?2:(l>=1?1:0);
const kosztDo=l=>{let s=0;for(let i=1;i<=l;i++)s+=koszt(i);return s};
const lv=i=>D.um[i][5],lvP=i=>lv(i)+(pend[i]||0);
const oczek=()=>Object.keys(pend).reduce((s,i)=>s+kosztDo(lvP(i))-kosztDo(lv(i)),0);
const wolne=()=>D.pula-D.wydane-oczek();
const szansa=(l,a)=>l*20+Math.floor(D.atr[a]/3);
function renderTop(){const p=oczek();$('puFree').textContent=D.pula-D.wydane-p;
 const e=$('puPend');e.style.left=(D.pula?D.wydane/D.pula*100:0)+"%";e.style.width=(D.pula?p/D.pula*100:0)+"%";
 $('bPend').textContent=p;$('bLeft').textContent=D.pula-D.wydane-p;$('bSave').disabled=p===0;$('bUndo').disabled=p===0;
 $('kuInputs').innerHTML=Object.keys(pend).map(i=>`<input type="hidden" name="um[${i}]" value="${lvP(i)}">`).join("")}
function renderChips(){const c=[["all",{nazwa:"Wszystkie",kolor:"#ff1744"}]].concat(Object.entries(D.kat));
 $('kuChips').innerHTML=c.map(([id,k])=>`<button type="button" class="ku-chip${kat===id?' on':''}" style="--c:${k.kolor}" data-k="${id}">${esc(k.nazwa)}</button>`).join("")}
function row(i,col){const[,n,g,d,o]=D.um[i],s=lv(i),p=lvP(i),nc=koszt(p+1);
 let pips="";for(let k=1;k<=MAX;k++)pips+=`<span class="ku-pip${k===5?' w2':''}${k<=s?' s':(k<=p?' p':'')}"></span>`;
 let ch;if(p===0)ch=`<div class="ku-ch none"><b>—</b><small>bez Umiejętności:<br>test Atrybutu ${Math.min(D.atr[g],PE)}%</small></div>`;
 else{const raw=szansa(p,g);ch=`<div class="ku-ch"><b class="${raw>=PE?'cap':''}">${Math.min(raw,PE)}%</b><small>${p}×20 + ${Math.floor(D.atr[g]/3)} ${A[g].slice(0,3).toUpperCase()}${raw>PE?' · limit PE':''}${d?`<br>z ${A[d]}: ${Math.min(szansa(p,d),PE)}%`:''}</small></div>`}
 return `<div class="ku-row${p>0?' has':''}" style="--c:${col}"><div class="nm"><b>${esc(n)}</b><p>${esc(o)}</p></div>
 <div class="ku-atrs"><span class="ku-at g" title="Atrybut główny">${A[g]}</span>${d?`<span class="ku-at d" title="Atrybut dodatkowy">+ ${A[d]}</span>`:''}</div>
 <div class="pw"><div class="ku-pips">${pips}</div><div class="ku-lvl"><span>poz. ${p}/${MAX}</span><span>${p<MAX?'następny: '+nc+' PU':'maks.'}</span></div></div>
 ${ch}<div class="ku-btns"><button type="button" data-m="${i}" ${p<=s?'disabled':''} aria-label="Obniż">−</button><button type="button" data-p="${i}" ${p>=MAX||nc>wolne()?'disabled':''} aria-label="Podnieś">+</button></div></div>`}
function renderList(){const q=$('kuQ').value.trim().toLowerCase(),only=$('kuOnly').checked;let h="";
 Object.entries(D.kat).forEach(([kid,k])=>{if(kat!=="all"&&kat!==kid)return;
  const all=D.um.map((u,i)=>i).filter(i=>D.um[i][0]===kid);
  const vis=all.filter(i=>(!q||D.um[i][1].toLowerCase().includes(q)||D.um[i][4].toLowerCase().includes(q))&&(!only||lvP(i)>0));
  if(!vis.length)return;h+=`<section class="ku-kat" style="--c:${k.kolor}"><h2>${esc(k.nazwa)} <span>${all.filter(i=>lvP(i)>0).length}/${all.length}</span></h2>${vis.map(i=>row(i,k.kolor)).join("")}</section>`});
 $('kuList').innerHTML=h||'<div class="ku-empty">// Brak umiejętności dla tego filtra</div>'}
function all(){renderTop();renderList()}
$('kuList').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;
 if(b.dataset.p!==undefined){const i=b.dataset.p;pend[i]=(pend[i]||0)+1}else if(b.dataset.m!==undefined){const i=b.dataset.m;pend[i]--;if(!pend[i])delete pend[i]}all()});
$('kuChips').addEventListener('click',e=>{const b=e.target.closest('.ku-chip');if(!b)return;kat=b.dataset.k;renderChips();renderList()});
$('kuQ').addEventListener('input',renderList);$('kuOnly').addEventListener('change',renderList);
$('bUndo').onclick=()=>{pend={};all()};
const M=$('kuModal');let onYes=null;
function modal(t,p,items,fn){$('mT').textContent=t;$('mP').textContent=p;$('mL').innerHTML=items.map(x=>`<li><span>${esc(x[0])}</span><span>${esc(x[1])}</span></li>`).join("");onYes=fn;M.classList.add('on')}
$('mNo').onclick=()=>M.classList.remove('on');M.addEventListener('click',e=>{if(e.target===M)M.classList.remove('on')});
$('mYes').onclick=()=>{M.classList.remove('on');onYes&&onYes()};
$('bSave').onclick=()=>{const it=Object.keys(pend).map(i=>[D.um[i][1]+" "+lv(i)+" → "+lvP(i),"−"+(kosztDo(lvP(i))-kosztDo(lv(i)))+" PU"]);
 modal("Zatwierdzić rozwój?","Wydanych PU nie da się odzyskać. Jedyny wyjątek to jednorazowy darmowy reset.",it,()=>$('kuForm').submit())};
$('bReset').onclick=()=>modal("Reset umiejętności","Wszystkie Umiejętności wrócą do poziomu 0, a PU wrócą do puli. Reset jest darmowy, ale możesz go użyć tylko raz.",[["Zwrot",D.wydane+" PU"]],()=>$('kuResetForm').submit());
renderChips();all();
})();
</script>
