<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — GARDEROBA (Faza 6)
// 
// Klimat: lustra, jedwab, perfumy, dryfujące pióra.
// Specjalne widgety:
//   - Karta strojów po kategoriach (klikalne → /mood)
//   - Lustra (ile osób się przegląda — mood "lustro")
//   - Krawcowa NPC: Madame Iris
//   - Plakat aktywnego eventu
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'garderoba');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'garderoba', 60);
$last_id = klub_last_id($polaczenie, 'garderoba');
$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'garderoba', $id_gracza);

// Pobierz stroje pogrupowane po kategoriach
$stroje_groups = [
    'wieczorowe' => ['nm' => 'Wieczorowe', 'ic' => '🎩', 'lista' => []],
    'plywackie'  => ['nm' => 'Pływackie',  'ic' => '🩱', 'lista' => []],
    'sportowe'   => ['nm' => 'Sportowe',   'ic' => '🏃', 'lista' => []],
    'spa'        => ['nm' => 'Spa',        'ic' => '🧖', 'lista' => []],
    'fetysz'     => ['nm' => 'Fetyszowe',  'ic' => '🖤', 'lista' => []],
    'sceniczne'  => ['nm' => 'Sceniczne',  'ic' => '🎭', 'lista' => []],
    'codzienne'  => ['nm' => 'Codzienne',  'ic' => '👕', 'lista' => []],
];
$sq = $polaczenie->query("SELECT * FROM klub_garderoba_stroje WHERE aktywny=1 ORDER BY sygnatura DESC, nazwa ASC");
if ($sq) while ($s = $sq->fetch_assoc()) {
    $cat = $s['kategoria'];
    if (isset($stroje_groups[$cat])) {
        $stroje_groups[$cat]['lista'][] = $s;
    }
}

// Filtruj puste kategorie
$stroje_groups = array_filter($stroje_groups, fn($g) => count($g['lista']) > 0);

// Licznik luster (mood "lustro" / "przeglądam się" / "stroję")
$przed_lustrami = 0;
foreach ($obecni as $o) {
    $m = mb_strtolower($o['klub_mood'] ?? '');
    if ($m && (strpos($m, 'lustr') !== false || strpos($m, 'przegląd') !== false
            || strpos($m, 'przeglad') !== false || strpos($m, 'stroj') !== false
            || strpos($m, 'przebier') !== false || strpos($m, 'sukni') !== false
            || strpos($m, 'smoking') !== false || strpos($m, 'maluj') !== false)) {
        $przed_lustrami++;
    }
}
?>

<style>
.klub-wrap.garderoba {
    --room-accent: #ff8fbc;
    --garderoba-pastel: #ffd5e8;
    --garderoba-deep: #2a0f1f;
}
.klub-wrap.garderoba .kol-left,
.klub-wrap.garderoba .kol-right { background: rgba(20,8,16,0.6); }
.klub-wrap.garderoba .kol-center {
    background: rgba(15,5,12,0.7);
    background-image:
        radial-gradient(ellipse at 0% 0%, rgba(255,143,188,0.06), transparent 50%),
        radial-gradient(ellipse at 100% 100%, rgba(255,143,188,0.05), transparent 50%);
}

.klub-wrap.garderoba .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.6em; letter-spacing: 1.5px; line-height: 1.1;
    text-transform: none; font-style: italic;
    background: linear-gradient(90deg, #fff, var(--garderoba-pastel), #fff);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 0 12px rgba(255,143,188,0.4));
}

.klub-wrap.garderoba .aside-h {
    color: var(--garderoba-pastel);
    border-left-color: var(--garderoba-pastel);
    background: linear-gradient(90deg, rgba(255,143,188,0.06), transparent);
}

/* ── KATEGORIA STROJÓW (collapsible) ──────────────────────────── */
.kat-strojow { margin-bottom: 8px; }
.kat-header {
    padding: 8px 10px; background: rgba(255,143,188,0.08);
    border: 1px solid rgba(255,143,188,0.25);
    border-radius: 2px; cursor: pointer;
    display: flex; align-items: center; justify-content: space-between;
    font-family: 'Oswald', sans-serif; font-size: .8em;
    color: var(--garderoba-pastel); letter-spacing: 1.5px;
    text-transform: uppercase; transition: .15s;
}
.kat-header:hover { background: rgba(255,143,188,0.15); }
.kat-header .ic { font-size: 1.1em; margin-right: 6px; }
.kat-header .cnt {
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    color: var(--txt-mute); letter-spacing: 1px;
}
.kat-strojow details[open] .kat-header { border-bottom: 1px dashed rgba(255,143,188,0.2); }

.stroj-item {
    padding: 6px 10px; border-bottom: 1px dashed rgba(255,255,255,0.04);
    transition: .15s; cursor: pointer;
    display: flex; align-items: center; gap: 8px;
}
.stroj-item:hover {
    background: rgba(255,143,188,0.06);
    transform: translateX(2px);
}
.stroj-item:last-child { border-bottom: none; }
.stroj-item.sig { background: linear-gradient(90deg, rgba(255,215,0,0.04), transparent); }
.stroj-item .ic { font-size: 1.1em; flex-shrink: 0; }
.stroj-item .info { flex: 1; min-width: 0; }
.stroj-item .nm {
    font-family: 'Fraunces', serif; font-size: .92em;
    color: #fff; line-height: 1.15;
}
.stroj-item .desc {
    display: block; font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .76em;
    color: var(--txt-dim); margin-top: 2px;
}
.stroj-item .plec {
    font-family: 'JetBrains Mono', monospace; font-size: .58em;
    color: var(--garderoba-pastel); letter-spacing: 1px;
    text-transform: uppercase; padding: 1px 5px;
    border: 1px solid rgba(255,143,188,0.3); border-radius: 1px;
    flex-shrink: 0;
}

/* ── LUSTRA ──────────────────────────────────────────────────── */
.lustra-box {
    padding: 12px; margin-bottom: 14px; text-align: center;
    background: linear-gradient(135deg, rgba(255,143,188,0.08), rgba(0,0,0,0.5));
    border: 1px solid rgba(255,143,188,0.3); border-radius: 2px;
    position: relative; overflow: hidden;
}
.lustra-box::before {
    content: '🪞 🪞 🪞 🪞 🪞';
    position: absolute; top: -10px; left: 0; right: 0;
    font-size: 1.3em; opacity: .15;
    letter-spacing: 8px; pointer-events: none;
    animation: lustraDrift 12s linear infinite;
}
@keyframes lustraDrift {
    from { transform: translateX(-30%); }
    to   { transform: translateX(30%); }
}
.lustra-box .label {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--garderoba-pastel); letter-spacing: 2.5px;
    text-transform: uppercase; margin-bottom: 6px;
    position: relative; z-index: 1;
}
.lustra-box .v {
    font-family: 'Fraunces', serif; font-size: 2em;
    color: #fff; line-height: 1; font-weight: 600; font-style: italic;
    text-shadow: 0 0 12px rgba(255,143,188,0.5);
    position: relative; z-index: 1;
}
.lustra-box .pod {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .85em; color: var(--txt-dim); margin-top: 4px;
    position: relative; z-index: 1;
}

/* ── KRAWCOWA NPC ────────────────────────────────────────────── */
.npc-iris {
    padding: 12px; margin-bottom: 12px;
    background: linear-gradient(135deg, rgba(255,143,188,0.08), rgba(0,0,0,0.5));
    border: 1px solid var(--garderoba-pastel); border-radius: 2px;
}
.npc-iris .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-iris .av {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--garderoba-deep), #100408);
    border: 1px solid var(--garderoba-pastel); color: var(--garderoba-pastel);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .82em; flex-shrink: 0;
}
.npc-iris .info .nm {
    font-family: 'Fraunces', serif; font-size: 1.05em;
    color: #fff; font-style: italic; line-height: 1;
}
.npc-iris .info .nm i { color: var(--garderoba-pastel); }
.npc-iris .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px; margin-top: 3px;
    text-transform: uppercase;
}
.npc-iris .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

.klub-wrap.garderoba .msg .txt { color: #f5dde9; }

/* Subtelne pióra dryfujące w feed */
.klub-wrap.garderoba .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(2px 2px at 20% 30%, rgba(255,143,188,0.4), transparent),
        radial-gradient(1.5px 1.5px at 70% 60%, rgba(255,143,188,0.3), transparent),
        radial-gradient(1px 1px at 40% 80%, rgba(255,255,255,0.4), transparent);
    background-size: 100% 100%;
    animation: garderobaPiora 14s ease-in-out infinite alternate;
    opacity: .5;
}
@keyframes garderobaPiora {
    from { transform: translateY(0); }
    to   { transform: translateY(-15px); }
}
</style>

<div class="klub-wrap garderoba">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>👗 <i>Garderoba</i></h2>
                <div class="sub">Lustra na każdej ścianie. Jedwab zsuwa się z wieszaków.</div>
                <div class="stats">
                    <span>GOŚCI: <b><?php echo $liczba_obecnych; ?></b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <!-- Aktywne wydarzenie -->
            <?php if ($aktywny_event):
                $plakat = !empty($aktywny_event['plakat_url']) ? htmlspecialchars($aktywny_event['plakat_url']) : '';
            ?>
            <div class="event-poster-wall <?php echo $plakat ? 'has-img' : ''; ?>"
                 <?php if ($plakat): ?>style="background-image:url('<?php echo $plakat; ?>')"<?php endif; ?>
                 style="border-color:var(--garderoba-pastel);background:linear-gradient(135deg,rgba(255,143,188,0.1),rgba(0,0,0,0.5))">
                <div>
                    <span class="live-badge" style="background:var(--garderoba-pastel);color:#000">● TRWA</span>
                    <div class="event-tytul">
                        <span class="ikona" style="color:var(--garderoba-pastel)"><?php echo htmlspecialchars($aktywny_event['ikona_emoji']); ?></span>
                        <?php echo htmlspecialchars($aktywny_event['nazwa']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lustra -->
            <div class="lustra-box">
                <div class="label">🪞 Przed lustrami</div>
                <div class="v"><?php echo $przed_lustrami; ?></div>
                <div class="pod">
                    <?php if ($przed_lustrami === 0): ?>
                        Lustra czekają.
                    <?php elseif ($przed_lustrami === 1): ?>
                        Ktoś sam ze sobą.
                    <?php elseif ($przed_lustrami < 4): ?>
                        Kilka odbić, kilka pewności.
                    <?php else: ?>
                        Garderoba pełna własnych obrazów.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Karta strojów -->
            <div class="aside-h">👗 Karta strojów</div>
            <?php foreach ($stroje_groups as $kat_id => $kat): ?>
            <div class="kat-strojow">
                <details>
                    <summary class="kat-header">
                        <span><span class="ic"><?php echo $kat['ic']; ?></span><?php echo $kat['nm']; ?></span>
                        <span class="cnt"><?php echo count($kat['lista']); ?> · ⌄</span>
                    </summary>
                    <?php foreach ($kat['lista'] as $s): ?>
                    <div class="stroj-item <?php echo $s['sygnatura'] ? 'sig' : ''; ?>"
                         onclick="garderobaWybierzStroj('<?php echo addslashes($s['nazwa']); ?>')"
                         title="Wybierz strój">
                        <span class="ic"><?php echo htmlspecialchars($s['ikona_emoji']); ?></span>
                        <div class="info">
                            <div class="nm"><?php echo htmlspecialchars($s['nazwa']); ?></div>
                            <span class="desc"><?php echo htmlspecialchars($s['opis']); ?></span>
                        </div>
                        <span class="plec"><?php echo strtoupper(substr($s['plec'], 0, 3)); ?></span>
                    </div>
                    <?php endforeach; ?>
                </details>
            </div>
            <?php endforeach; ?>

            <!-- Krawcowa NPC -->
            <div class="aside-h" style="margin-top:14px">◈ Krawcowa</div>
            <div class="npc-iris">
                <div class="head">
                    <div class="av">IR</div>
                    <div class="info">
                        <div class="nm">Madame <i>Iris</i></div>
                        <div class="ti">KRAWCOWA · NPC · OKO IGŁY</div>
                    </div>
                </div>
                <div class="desc">
                    Czarne włosy upięte w kok, czerwona pomadka, taśma miernicza zawsze w kieszeni. Mówi z lekkim francuskim akcentem. Każdy strój dopasuje do figury w pięć minut, każdą wadę zatuszuje w trzy.
                </div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood w stroju: [nazwa] · /mood przed lustrem · /flirt @
            </div>
        </div>

        <!-- ══════ KOL CENTER ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Garderoba
                    </div>
                    <div class="title" style="font-family:'Fraunces',serif;font-style:italic;letter-spacing:1px;background:linear-gradient(90deg,#fff,var(--garderoba-pastel),#fff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">👗 Pióra i lustra</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--garderoba-pastel);font-size:.9em;">
                    Iris z taśmą w pogotowiu
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="garderoba" data-last-id="<?php echo $last_id; ?>" style="position:relative;overflow:hidden">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty" style="position:relative;z-index:1">
                    Wieszaki kołyszą się od ledwie wyczuwalnego przeciągu.<br>
                    Lustra mnożą Cię wokół. Jaką wersję siebie wybierzesz dziś?
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc prim" onclick="window.klubInsertCmd('/mood przed lustrem')">🪞 /mood lustro</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood w stroju: ')">👗 /mood stroju</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood maluję się')">💄 /mood maluję</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Stajesz przed lustrem. Iris przykłada miarkę do biodra. — "Ten dół chce krótszego niż myślisz."*'
                        required></textarea>
                    <button type="submit" class="btn-send" style="background:linear-gradient(90deg,#7a4060,var(--garderoba-pastel));border-color:var(--garderoba-pastel);color:#000;font-weight:500">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 W garderobie <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Tylko Iris i wiatr w piórach.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'przebiera się';
                ?>
                <a href="game.php?page=profil&id=<?php echo (int)$o['id']; ?>" class="guest-row <?php echo $klasa; ?>" style="text-decoration:none;">
                    <div class="av"<?php echo $ava?" style=\"background-image:url('$ava');background-size:cover;background-position:center;color:transparent\"":''; ?>>
                        <?php echo !$ava ? htmlspecialchars($inic) : ''; ?>
                    </div>
                    <div class="who">
                        <span class="nm"><?php echo htmlspecialchars($o['login']); ?></span>
                        <span class="mood"><?php echo htmlspecialchars($mood); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <div class="aside-h" style="margin-top:16px">🚪 W innych salach</div>
            <?php
            $linki = [
                ['sala-glowna','🍸','Sala Główna'],
                ['sala-balowa','💃','Sala Balowa'],
                ['masaze','💆','Masaże'],
                ['sauna','♨','Sauna'],
                ['basen','🏊','Basen'],
                ['vip','🥂','VIP'],
            ];
            foreach ($linki as $l):
                $cnt = $licznik_sal[$l[0]] ?? 0;
            ?>
            <a href="game.php?page=czat&sala=<?php echo $l[0]; ?>" class="sala-link">
                <span><?php echo $l[1]; ?> <?php echo $l[2]; ?></span>
                <span class="cnt"><?php echo $cnt; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script>
window.garderobaWybierzStroj = async function(nazwa) {
    // Najpierw zaloguj strój (do odznaki Modny)
    try {
        const fd = new FormData();
        fd.append('op','log_stroj');
        fd.append('stroj_nazwa', nazwa);
        const r = await fetch('api/klub_garderoba.php', {method:'POST',body:fd,credentials:'same-origin'});
        const d = await r.json();
        if (d && d.nowe_odznaki && d.nowe_odznaki.length) {
            alert('🏆 Nowa odznaka: ' + d.nowe_odznaki.map(o=>o.nazwa).join(', '));
        }
    } catch(e) { /* cisza */ }

    // Potem wpisz /mood do czatu
    if (window.klubInsertCmd) {
        window.klubInsertCmd('/mood w stroju: ' + nazwa);
    }
};
</script>