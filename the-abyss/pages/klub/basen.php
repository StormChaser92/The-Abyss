<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — BASEN (Faza 6)
// 
// Klimat: chłód wody, neonowe odbicia, echo szmerów.
// Specjalne widgety:
//   - 4 tory pływackie (wolne/zajęte)
//   - Licznik pływających (mood "pływam"/"woda"/"basen")
//   - Temperatura wody (deterministyczna)
//   - Ratownik NPC: Vesna
//   - Plakat aktywnego eventu
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'basen');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'basen', 60);
$last_id = klub_last_id($polaczenie, 'basen');
$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'basen', $id_gracza);

// Cleanup wygasłych torów (>30 min bez aktywności)
$polaczenie->query("UPDATE klub_basen_tory SET zajety_przez_id=NULL, zajety_od=NULL WHERE zajety_od IS NOT NULL AND zajety_od <= NOW() - INTERVAL 30 MINUTE");

// Pobierz tory
$tory = [];
$tq = $polaczenie->query("
    SELECT t.*, g.login AS zajmujacy
    FROM klub_basen_tory t
    LEFT JOIN gracze g ON g.id = t.zajety_przez_id
    ORDER BY t.numer ASC
");
if ($tq) while ($t = $tq->fetch_assoc()) $tory[] = $t;

// Licznik pływających (mood-based)
$plywajacych = 0;
foreach ($obecni as $o) {
    $m = mb_strtolower($o['klub_mood'] ?? '');
    if ($m && (strpos($m, 'pływ') !== false || strpos($m, 'plyw') !== false
            || strpos($m, 'wodzie') !== false || strpos($m, 'basen') !== false
            || strpos($m, 'pluska') !== false || strpos($m, 'mokr') !== false)) {
        $plywajacych++;
    }
}

// Temperatura wody — deterministyczna na dobę (między 26-29°C)
$dzien_id = (int)date('z') + (int)date('Y') * 1000;
$temp_woda = 26 + ($dzien_id % 4);
?>

<style>
.klub-wrap.basen {
    --room-accent: var(--neon-cyan);
    --basen-deep: #003a4a;
}
.klub-wrap.basen .kol-left,
.klub-wrap.basen .kol-right { background: rgba(2,12,18,0.55); }
.klub-wrap.basen .kol-center {
    background: rgba(0,8,14,0.65);
    background-image: radial-gradient(ellipse at 50% 0%, rgba(74,214,255,0.08), transparent 60%);
}

.klub-wrap.basen .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.6em; letter-spacing: 1px; line-height: 1.1;
    text-transform: none;
    text-shadow: 0 0 14px rgba(74,214,255,0.5);
}
.klub-wrap.basen .room-header h2 i { color: var(--neon-cyan); font-style: italic; }

.klub-wrap.basen .aside-h {
    color: var(--neon-cyan);
    border-left-color: var(--neon-cyan);
    background: linear-gradient(90deg, rgba(74,214,255,0.08), transparent);
}

/* ── TORY PŁYWACKIE ──────────────────────────────────────────── */
.tory-lista { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.tor-item {
    padding: 10px 12px; border-radius: 2px;
    background: linear-gradient(90deg, rgba(74,214,255,0.04), rgba(0,15,25,0.5));
    border: 1px solid rgba(74,214,255,0.2);
    border-left: 3px solid var(--neon-cyan);
    transition: .15s; position: relative; overflow: hidden;
    cursor: pointer;
}
.tor-item:hover { background: rgba(74,214,255,0.1); border-color: var(--neon-cyan); }
.tor-item.zajety {
    background: linear-gradient(90deg, rgba(255,23,68,0.1), rgba(0,15,25,0.5));
    border-left-color: var(--neon-red-hot);
}
.tor-item .head {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 3px;
}
.tor-item .nm {
    font-family: 'Fraunces', serif; font-size: 1em;
    color: #fff; line-height: 1.1;
}
.tor-item .nm i { color: var(--neon-cyan); font-style: italic; }
.tor-item .stat {
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--neon-cyan);
}
.tor-item.zajety .stat { color: var(--neon-red-hot); }
.tor-item .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .8em; color: var(--txt-dim); margin-top: 2px;
}
.tor-item .zajmujacy {
    margin-top: 4px;
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--neon-red-hot); letter-spacing: 1px;
}
.tor-item.moje { border-left-color: var(--neon-gold); background: linear-gradient(90deg, rgba(255,215,0,0.08), rgba(0,15,25,0.5)); }
.tor-item.moje .zajmujacy { color: var(--neon-gold); }
.tor-akcje { margin-top: 8px; display: flex; }
.tor-akcje button {
    flex: 1; padding: 6px 10px;
    background: rgba(74,214,255,0.1); border: 1px solid var(--neon-cyan);
    color: var(--neon-cyan); font-family: 'Oswald', sans-serif;
    font-size: .72em; letter-spacing: 1.5px; text-transform: uppercase;
    cursor: pointer; border-radius: 2px; transition: .15s;
}
.tor-akcje button:hover { background: var(--neon-cyan); color: #000; }
.tor-akcje .btn-zwolnij {
    background: rgba(255,215,0,0.1); border-color: var(--neon-gold);
    color: var(--neon-gold);
}
.tor-akcje .btn-zwolnij:hover { background: var(--neon-gold); color: #000; }

/* Animacja fal w toze */
.tor-item.zajety::after {
    content: ''; position: absolute; left: 0; right: 0; top: 0; bottom: 0;
    background: repeating-linear-gradient(90deg,
        transparent 0 6px,
        rgba(74,214,255,0.08) 6px 8px,
        transparent 8px 14px);
    animation: torFala 4s linear infinite;
    pointer-events: none;
}
@keyframes torFala {
    from { background-position: 0 0; }
    to   { background-position: 28px 0; }
}

/* ── LICZNIK PŁYWAJĄCYCH ──────────────────────────────────────── */
.basen-stats {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    margin-bottom: 14px;
}
.basen-stat-box {
    padding: 12px 10px; text-align: center;
    background: linear-gradient(135deg, rgba(74,214,255,0.06), rgba(0,8,14,0.5));
    border: 1px solid rgba(74,214,255,0.25); border-radius: 2px;
}
.basen-stat-box .label {
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    color: var(--txt-mute); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 4px;
}
.basen-stat-box .v {
    font-family: 'Fraunces', serif; font-size: 1.6em;
    color: #fff; line-height: 1; font-weight: 600;
    text-shadow: 0 0 10px rgba(74,214,255,0.4);
}
.basen-stat-box .v .unit {
    font-size: .55em; color: var(--neon-cyan); margin-left: 2px;
}

/* ── RATOWNIK NPC ────────────────────────────────────────────── */
.npc-vesna {
    padding: 12px; margin-bottom: 12px;
    background: linear-gradient(135deg, rgba(74,214,255,0.06), rgba(0,0,0,0.5));
    border: 1px solid var(--neon-cyan); border-radius: 2px;
}
.npc-vesna .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-vesna .av {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--basen-deep), #001218);
    border: 1px solid var(--neon-cyan); color: var(--neon-cyan);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .82em; flex-shrink: 0;
}
.npc-vesna .info { flex: 1; min-width: 0; font-family: 'Fraunces', serif; color: #fff; }
.npc-vesna .info .nm { font-size: 1.05em; line-height: 1; }
.npc-vesna .info .nm i { color: var(--neon-cyan); font-style: italic; }
.npc-vesna .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px; margin-top: 3px;
    text-transform: uppercase;
}
.npc-vesna .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

/* Animacja powierzchni wody w feed */
.klub-wrap.basen .feed {
    position: relative;
}
.klub-wrap.basen .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 0;
    background:
        radial-gradient(ellipse at 25% 80%, rgba(74,214,255,0.08), transparent 40%),
        radial-gradient(ellipse at 75% 30%, rgba(74,214,255,0.06), transparent 40%);
    animation: basenSurface 8s ease-in-out infinite alternate;
}
@keyframes basenSurface {
    from { transform: translateX(0); opacity: .6; }
    to   { transform: translateX(20px); opacity: 1; }
}
.klub-wrap.basen .feed > * { position: relative; z-index: 1; }

.klub-wrap.basen .msg .txt { color: #d8eef5; }
</style>

<div class="klub-wrap basen">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>🏊 <i>Basen</i></h2>
                <div class="sub">Woda chłodna jak metal. Neonowe pasy odbijają się na suficie.</div>
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
                 style="border-color:var(--neon-cyan);background:linear-gradient(135deg,rgba(74,214,255,0.1),rgba(0,0,0,0.5))">
                <div>
                    <span class="live-badge" style="background:var(--neon-cyan);color:#000">● TRWA</span>
                    <div class="event-tytul" style="text-shadow:0 0 10px rgba(74,214,255,0.4),0 2px 4px rgba(0,0,0,0.8)">
                        <span class="ikona" style="color:var(--neon-cyan)"><?php echo htmlspecialchars($aktywny_event['ikona_emoji']); ?></span>
                        <?php echo htmlspecialchars($aktywny_event['nazwa']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statystyki basenu -->
            <div class="aside-h">📊 Stan basenu</div>
            <div class="basen-stats">
                <div class="basen-stat-box">
                    <div class="label">Pływających</div>
                    <div class="v"><?php echo $plywajacych; ?></div>
                </div>
                <div class="basen-stat-box">
                    <div class="label">Temp. wody</div>
                    <div class="v"><?php echo $temp_woda; ?><span class="unit">°C</span></div>
                </div>
            </div>

            <!-- Tory -->
            <div class="aside-h">🏊 Tory</div>
            <div class="tory-lista">
                <?php foreach ($tory as $t):
                    $zajety = !empty($t['zajety_przez_id']);
                    $moje = $zajety && (int)$t['zajety_przez_id'] === $id_gracza;
                    $do_kiedy = ($t['do_kiedy'] ?? null) ? date('H:i', strtotime($t['do_kiedy'])) : null;
                ?>
                <div class="tor-item <?php echo $zajety ? 'zajety' : ''; ?> <?php echo $moje ? 'moje' : ''; ?>">
                    <div class="head">
                        <span class="nm"><?php echo htmlspecialchars($t['nazwa']); ?></span>
                        <span class="stat"><?php echo $zajety ? '● zajęty' : '○ wolny'; ?></span>
                    </div>
                    <div class="desc"><?php echo htmlspecialchars($t['opis']); ?></div>
                    <?php if ($zajety): ?>
                    <div class="zajmujacy">
                        → <?php echo htmlspecialchars($t['zajmujacy']); ?> pływa<?php if ($do_kiedy): ?> · do <?php echo $do_kiedy; ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="tor-akcje">
                        <?php if (!$zajety): ?>
                            <button onclick="basenZajmij(<?php echo (int)$t['id']; ?>)" class="btn-zajmij">Zajmij na 30 min</button>
                        <?php elseif ($moje): ?>
                            <button onclick="basenZwolnij(<?php echo (int)$t['id']; ?>)" class="btn-zwolnij">✕ Zwolnij</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Ratownik NPC -->
            <div class="aside-h">◈ Ratownik</div>
            <div class="npc-vesna">
                <div class="head">
                    <div class="av">VS</div>
                    <div class="info">
                        <div class="nm"><i>Vesna</i></div>
                        <div class="ti">RATOWNIK · NPC · OBOWIĄZKI</div>
                    </div>
                </div>
                <div class="desc">
                    Czerwone szorty, gwizdek na sznurku, opalona od chloru. Patrzy przez okulary korekcyjne, ale nigdy nie spuszcza wzroku z toru. Pamięta wszystkich graczy z imienia.
                </div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood pływam · /flirt @ · /do [sala]
            </div>
        </div>

        <!-- ══════ KOL CENTER — CHAT ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Basen
                    </div>
                    <div class="title">🏊 Woda i neony</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--neon-cyan);font-size:.9em;">
                    <?php echo $temp_woda; ?>°C · <?php echo $plywajacych; ?> w wodzie
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="basen" data-last-id="<?php echo $last_id; ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Powierzchnia wody jest gładka jak lustro.<br>
                    Echo cofa Twoje kroki na kafelkach.<br>
                    Nikogo. Tylko neon nad torem czwartym mruga.
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc prim" onclick="window.klubInsertCmd('/mood pływam w torze ')">🏊 /mood pływam</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood odpoczywam przy basenie')">🏖 odpoczywam</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Stoisz na krawędzi. Woda ciągnie chłodem. Skok? Schody?*'
                        required></textarea>
                    <button type="submit" class="btn-send" style="background:linear-gradient(90deg,#00505a,var(--neon-cyan));border-color:var(--neon-cyan);color:#000;font-weight:600">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 Przy basenie <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Pusto.<br>Tylko Vesna i woda.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'przy basenie';
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
                ['silownia','💪','Siłownia'],
                ['masaze','💆','Masaże'],
                ['sauna','♨','Sauna'],
                ['taras','🌃','Taras'],
                ['garderoba','👗','Garderoba'],
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
async function basenZajmij(tor_id) {
    const fd = new FormData();
    fd.append("op","zajmij"); fd.append("tor_id", tor_id);
    try {
        const r = await fetch("api/klub_basen.php", {method:"POST",body:fd,credentials:"same-origin"});
        const d = await r.json();
        if (d.ok) {
            if (d.nowe_odznaki && d.nowe_odznaki.length) alert(d.msg + "

🏆 Nowa odznaka: " + d.nowe_odznaki.map(o=>o.nazwa).join(", "));
            else alert(d.msg);
            location.reload();
        } else alert(d.msg || "Błąd");
    } catch(e) { alert("Brak połączenia"); }
}
async function basenZwolnij(tor_id) {
    if (!confirm("Zwolnić tor?")) return;
    const fd = new FormData();
    fd.append("op","zwolnij"); fd.append("tor_id", tor_id);
    try {
        const r = await fetch("api/klub_basen.php", {method:"POST",body:fd,credentials:"same-origin"});
        const d = await r.json();
        if (d.ok) location.reload();
        else alert(d.msg || "Błąd");
    } catch(e) { alert("Brak połączenia"); }
}
</script>