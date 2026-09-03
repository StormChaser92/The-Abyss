<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — SIŁOWNIA (Faza 6)
// 
// Klimat: stal, skóra, pot, intensywność.
// Specjalne widgety:
//   - 8 stanowisk sprzętu (wolne/zajęte)
//   - Licznik trenujących (mood "trening"/"ćwiczę"/"podnoszę")
//   - Trener NPC: Roman
//   - Plakat aktywnego eventu
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'silownia');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'silownia', 60);
$last_id = klub_last_id($polaczenie, 'silownia');
$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'silownia', $id_gracza);

// Cleanup wygasłego sprzętu (>20 min bez aktywności)
$polaczenie->query("UPDATE klub_silownia_sprzet SET zajety_przez_id=NULL, zajety_od=NULL WHERE zajety_od IS NOT NULL AND zajety_od <= NOW() - INTERVAL 20 MINUTE");

// Pobierz sprzęt
$sprzet = [];
$sq = $polaczenie->query("
    SELECT s.*, g.login AS zajmujacy
    FROM klub_silownia_sprzet s
    LEFT JOIN gracze g ON g.id = s.zajety_przez_id
    ORDER BY s.id ASC
");
if ($sq) while ($s = $sq->fetch_assoc()) $sprzet[] = $s;

$wolne = count(array_filter($sprzet, fn($s) => empty($s['zajety_przez_id'])));
$zajete = count($sprzet) - $wolne;

// Licznik trenujących
$trenujacych = 0;
foreach ($obecni as $o) {
    $m = mb_strtolower($o['klub_mood'] ?? '');
    if ($m && (strpos($m, 'trening') !== false || strpos($m, 'ćwicz') !== false
            || strpos($m, 'cwicz') !== false || strpos($m, 'podnos') !== false
            || strpos($m, 'biega') !== false || strpos($m, 'siłow') !== false
            || strpos($m, 'silow') !== false || strpos($m, 'pompk') !== false)) {
        $trenujacych++;
    }
}
?>

<style>
.klub-wrap.silownia {
    --room-accent: #ff3d3d;
    --silownia-stal: #2a0a0a;
}
.klub-wrap.silownia .kol-left,
.klub-wrap.silownia .kol-right { background: rgba(15,3,3,0.6); }
.klub-wrap.silownia .kol-center {
    background: rgba(8,2,2,0.7);
    background-image:
        repeating-linear-gradient(90deg, transparent 0 80px, rgba(255,61,61,0.02) 80px 81px),
        radial-gradient(ellipse at 50% 0%, rgba(255,61,61,0.06), transparent 60%);
}

.klub-wrap.silownia .room-header h2 {
    font-family: 'Oswald', sans-serif; font-weight: 600;
    font-size: 1.6em; letter-spacing: 4px; line-height: 1;
    text-transform: uppercase;
    text-shadow: 0 0 14px rgba(255,61,61,0.5);
}

.klub-wrap.silownia .aside-h {
    color: #ff3d3d;
    border-left-color: #ff3d3d;
    background: linear-gradient(90deg, rgba(255,61,61,0.08), transparent);
}

/* ── SPRZĘT ──────────────────────────────────────────────────── */
.sprzet-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 14px;
}
.sprzet-item {
    padding: 8px 10px; border-radius: 2px;
    background: linear-gradient(135deg, rgba(15,5,5,0.7), rgba(0,0,0,0.5));
    border: 1px solid rgba(255,61,61,0.2); transition: .15s;
    position: relative;
}
.sprzet-item.zajety {
    background: linear-gradient(135deg, rgba(255,61,61,0.15), rgba(15,5,5,0.7));
    border-color: rgba(255,61,61,0.6);
    box-shadow: 0 0 8px rgba(255,61,61,0.2);
}
.sprzet-item .ic {
    font-size: 1.4em; line-height: 1; margin-bottom: 4px; display: block;
}
.sprzet-item .nm {
    font-family: 'Oswald', sans-serif; font-size: .76em;
    color: #fff; letter-spacing: 1px; line-height: 1.15;
    text-transform: uppercase;
}
.sprzet-item .stat {
    font-family: 'JetBrains Mono', monospace; font-size: .58em;
    margin-top: 4px; letter-spacing: 1.5px;
    color: var(--neon-green);
}
.sprzet-item.zajety .stat { color: #ff3d3d; }
.sprzet-item .by {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: #ff3d3d; margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── STATYSTYKI SIŁOWNI ──────────────────────────────────────── */
.silownia-stats {
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-bottom: 14px;
}
.silownia-stat-box {
    padding: 10px 6px; text-align: center;
    background: linear-gradient(135deg, rgba(255,61,61,0.06), rgba(0,0,0,0.5));
    border: 1px solid rgba(255,61,61,0.25); border-radius: 2px;
}
.silownia-stat-box .label {
    font-family: 'JetBrains Mono', monospace; font-size: .58em;
    color: var(--txt-mute); letter-spacing: 1.5px;
    text-transform: uppercase; margin-bottom: 3px;
}
.silownia-stat-box .v {
    font-family: 'Oswald', sans-serif; font-size: 1.4em; font-weight: 700;
    color: #ff3d3d; line-height: 1;
    text-shadow: 0 0 10px rgba(255,61,61,0.5);
}

/* ── TRENER NPC ──────────────────────────────────────────────── */
.npc-roman {
    padding: 12px; margin-bottom: 12px;
    background: linear-gradient(135deg, rgba(255,61,61,0.08), rgba(0,0,0,0.5));
    border: 1px solid #ff3d3d; border-radius: 2px;
}
.npc-roman .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-roman .av {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--silownia-stal), #050000);
    border: 1px solid #ff3d3d; color: #ff3d3d;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .82em; flex-shrink: 0;
}
.npc-roman .info .nm {
    font-family: 'Oswald', sans-serif; font-size: 1em; font-weight: 600;
    color: #fff; letter-spacing: 1.5px; text-transform: uppercase;
}
.npc-roman .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px; margin-top: 3px;
    text-transform: uppercase;
}
.npc-roman .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

.klub-wrap.silownia .msg .txt { color: #f5dada; }
</style>

<div class="klub-wrap silownia">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>💪 SIŁOWNIA</h2>
                <div class="sub">Stal, skóra, pot. Lustra na każdej ścianie. Tutaj boli, ale boli słusznie.</div>
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
                 style="border-color:#ff3d3d;background:linear-gradient(135deg,rgba(255,61,61,0.1),rgba(0,0,0,0.5))">
                <div>
                    <span class="live-badge">● TRWA</span>
                    <div class="event-tytul">
                        <span class="ikona" style="color:#ff3d3d"><?php echo htmlspecialchars($aktywny_event['ikona_emoji']); ?></span>
                        <?php echo htmlspecialchars($aktywny_event['nazwa']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statystyki siłowni -->
            <div class="aside-h">📊 Stan</div>
            <div class="silownia-stats">
                <div class="silownia-stat-box">
                    <div class="label">Trenuje</div>
                    <div class="v"><?php echo $trenujacych; ?></div>
                </div>
                <div class="silownia-stat-box">
                    <div class="label">Wolne</div>
                    <div class="v" style="color:var(--neon-green);text-shadow:0 0 10px rgba(90,255,154,0.3)"><?php echo $wolne; ?></div>
                </div>
                <div class="silownia-stat-box">
                    <div class="label">Zajęte</div>
                    <div class="v"><?php echo $zajete; ?></div>
                </div>
            </div>

            <!-- Sprzęt -->
            <div class="aside-h">🏋️ Sprzęt</div>
            <div class="sprzet-grid">
                <?php foreach ($sprzet as $s):
                    $z = !empty($s['zajety_przez_id']);
                ?>
                <div class="sprzet-item <?php echo $z ? 'zajety' : ''; ?>" title="<?php echo htmlspecialchars($s['opis']); ?>">
                    <span class="ic"><?php echo htmlspecialchars($s['ikona_emoji']); ?></span>
                    <div class="nm"><?php echo htmlspecialchars($s['nazwa']); ?></div>
                    <div class="stat"><?php echo $z ? '● zajęty' : '○ wolny'; ?></div>
                    <?php if ($z): ?>
                    <div class="by">→ <?php echo htmlspecialchars($s['zajmujacy']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Trener NPC -->
            <div class="aside-h">◈ Trener</div>
            <div class="npc-roman">
                <div class="head">
                    <div class="av">RM</div>
                    <div class="info">
                        <div class="nm">Roman</div>
                        <div class="ti">TRENER · NPC · BEZ TARYFY ULGOWEJ</div>
                    </div>
                </div>
                <div class="desc">
                    Były bokser zawodowy, blizna nad lewą brwią, koszulka zawsze za ciasna. Mówi mało, ale gdy mówi — to się słucha. Pamięta każdą serię, każde oszustwo na powtórzeniach.
                </div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood trening · /flirt @ · /do [sala]
            </div>
        </div>

        <!-- ══════ KOL CENTER ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Siłownia
                    </div>
                    <div class="title" style="font-family:'Oswald',sans-serif;letter-spacing:3px">💪 STAL I POT</div>
                </div>
                <div style="font-family:'JetBrains Mono',monospace;font-style:normal;color:#ff3d3d;font-size:.78em;letter-spacing:1.5px;text-transform:uppercase;">
                    <?php echo $trenujacych; ?> trenuje · <?php echo $zajete; ?>/<?php echo count($sprzet); ?> zajęte
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="silownia" data-last-id="<?php echo $last_id; ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Sztangi czekają na podłodze. Echo własnych kroków.<br>
                    Roman gdzieś w narożniku, przy worku, ale Cię obserwuje.<br>
                    Pierwszy ruch?
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc prim" onclick="window.klubInsertCmd('/mood trenuję')">💪 /mood trenuję</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood podnoszę ciężary')">🏋️ ciężary</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood biegam')">🏃 biegam</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Zapinasz pas, sięgasz po sztangę.* — "Dwa razy ośmiu po sto. I to ma być czyste."'
                        required></textarea>
                    <button type="submit" class="btn-send" style="background:linear-gradient(90deg,#7a0010,#ff3d3d);border-color:#ff3d3d;color:#fff;font-weight:600;letter-spacing:3px">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 Na siłowni <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Pusto.<br>Tylko stal.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'na siłce';
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
                ['basen','🏊','Basen'],
                ['sauna','♨','Sauna'],
                ['masaze','💆','Masaże'],
                ['tyly','🚬','Tyły'],
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