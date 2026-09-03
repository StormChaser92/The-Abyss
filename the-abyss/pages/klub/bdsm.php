<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — POKÓJ BDSM
// Pokój prywatny — safewords, consent, NPC Madame Vex
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'bdsm');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'bdsm', 60);
?>

<style>
/* BDSM: ciemny burgund + matowe złoto, świece zamiast neonu */
.klub-wrap.bdsm {
    --room-accent: #c8325a;
    --bdsm-gold: #c9a961;
    --bdsm-dark: #2a0510;
}
.klub-wrap.bdsm .kol-left,
.klub-wrap.bdsm .kol-right { background: rgba(15,5,10,0.55); }
.klub-wrap.bdsm .kol-center { background: rgba(10,3,7,0.6); }

.klub-wrap.bdsm .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.7em; letter-spacing: 1px; line-height: 1.1;
    text-shadow: 0 0 14px rgba(200,50,90,0.5);
    text-transform: none;
}
.klub-wrap.bdsm .room-header h2 i { color: var(--room-accent); font-style: italic; }
.klub-wrap.bdsm .room-header .sub {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); line-height: 1.45; font-size: 1em;
}
.klub-wrap.bdsm .chat-head .title {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.05em; text-transform: none; letter-spacing: 1px;
}

/* Badge prywatności */
.private-badge {
    display: inline-block; margin-top: 8px;
    padding: 3px 10px;
    background: rgba(201,169,97,0.1);
    border: 1px solid var(--bdsm-gold);
    color: var(--bdsm-gold);
    font-family: 'JetBrains Mono', monospace;
    font-size: .68em; letter-spacing: 2px;
    text-transform: uppercase; border-radius: 1px;
}

/* Aside header w BDSM — kolor zamiast czerwieni */
.klub-wrap.bdsm .aside-h {
    color: var(--bdsm-gold);
    border-left-color: var(--bdsm-gold);
    background: linear-gradient(90deg, rgba(201,169,97,0.08), transparent);
}

/* Safewords — przyciski */
.safeword-block {
    margin-bottom: 16px; padding: 12px;
    background: linear-gradient(135deg, rgba(60,5,20,0.6), rgba(20,3,10,0.7));
    border: 1px solid rgba(201,169,97,0.3);
    border-radius: 2px;
}
.safeword-block h3 {
    font-family: 'Oswald', sans-serif; font-size: .72em;
    color: var(--bdsm-gold); letter-spacing: 3px;
    text-transform: uppercase; margin-bottom: 10px;
    text-align: center;
}
.sw-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 6px; }
.sw-btn {
    padding: 8px 10px; border: 1px solid; cursor: pointer;
    background: rgba(0,0,0,0.5); display: flex; flex-direction: column;
    align-items: flex-start; transition: .2s;
    font-family: 'Oswald', sans-serif; border-radius: 2px;
    text-align: left;
}
.sw-btn .w {
    font-size: .85em; letter-spacing: 1.5px;
    text-transform: uppercase; line-height: 1;
}
.sw-btn .l {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .78em; color: var(--txt-dim); margin-top: 4px;
    text-transform: none; letter-spacing: 0; line-height: 1.2;
}
.sw-btn.green { border-color: rgba(90,255,154,0.4); color: var(--neon-green); }
.sw-btn.green:hover { background: rgba(90,255,154,0.1); box-shadow: 0 0 12px rgba(90,255,154,0.3); }
.sw-btn.yellow { border-color: rgba(255,215,0,0.4); color: var(--neon-gold); }
.sw-btn.yellow:hover { background: rgba(255,215,0,0.1); box-shadow: 0 0 12px rgba(255,215,0,0.3); }
.sw-btn.red {
    grid-column: span 2;
    border-color: var(--neon-red); color: #fff;
    background: linear-gradient(135deg, rgba(255,23,68,0.15), rgba(60,0,10,0.6));
}
.sw-btn.red:hover {
    background: linear-gradient(135deg, rgba(255,23,68,0.3), rgba(80,0,15,0.8));
    box-shadow: 0 0 18px rgba(255,23,68,0.5);
}

/* NPC Card */
.npc-card {
    padding: 12px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--bdsm-gold); border-radius: 2px;
    margin-bottom: 10px;
}
.npc-card .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-card .av {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #3a0a18, #0a0408);
    border: 1px solid var(--bdsm-gold); color: var(--bdsm-gold);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .8em;
    flex-shrink: 0;
}
.npc-card .info {
    flex: 1; min-width: 0;
    font-family: 'Fraunces', serif; color: #fff;
}
.npc-card .info .nm { font-size: 1em; line-height: 1; }
.npc-card .info .nm i { color: var(--bdsm-gold); font-style: italic; }
.npc-card .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1.5px;
    margin-top: 3px; text-transform: uppercase;
}
.npc-card .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

/* Zasady — krótsza lista */
.rules-mini {
    padding: 12px; background: rgba(0,0,0,0.35);
    border-left: 2px solid var(--bdsm-gold);
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); font-size: .92em; line-height: 1.55;
    margin-top: 10px;
}
.rules-mini b {
    color: var(--bdsm-gold); font-style: normal;
    font-family: 'Oswald', sans-serif; font-size: .8em;
    letter-spacing: 2px; text-transform: uppercase;
    display: block; margin-bottom: 6px;
}

/* Wiadomości w BDSM mają lekko inny vibe */
.klub-wrap.bdsm .msg .txt { color: #f0e0e8; }
.klub-wrap.bdsm .msg .who .role {
    color: var(--bdsm-gold); border-color: rgba(201,169,97,0.4);
    background: rgba(201,169,97,0.08);
}

/* Świece — animowany glow */
.candle-glow {
    position: relative; padding: 14px;
    background: radial-gradient(ellipse at 50% 100%, rgba(201,169,97,0.12), transparent 70%);
    text-align: center; margin-bottom: 14px;
    border: 1px dashed rgba(201,169,97,0.2);
    border-radius: 2px;
}
.candle-glow .flame {
    font-size: 1.6em; line-height: 1;
    filter: drop-shadow(0 0 12px rgba(255,180,80,0.7));
    animation: flicker 3s ease-in-out infinite;
}
@keyframes flicker {
    0%,100% { opacity: 1; transform: scale(1); }
    25% { opacity: .85; transform: scale(0.97); }
    50% { opacity: 1; transform: scale(1.02); }
    75% { opacity: .9; transform: scale(0.98); }
}
.candle-glow .label {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .85em; color: var(--bdsm-gold); margin-top: 4px;
    letter-spacing: 1px;
}
</style>

<div class="klub-wrap bdsm">
    <div class="klub-3col">

        <!-- LEFT — opis pokoju, NPC, zasady -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>Pokój <i>BDSM</i></h2>
                <div class="sub">
                    Ciężka kotara, szept skóry, świece w lichtarzach.<br>
                    To nie jest miejsce na żart — ani miejsce bez żartu. To miejsce na <i>consent</i>.
                </div>
                <span class="private-badge">🔐 POKÓJ PRYWATNY</span>
                <div class="stats">
                    <span>GOŚCI: <b><?php echo $liczba_obecnych; ?>/6</b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <!-- Świece -->
            <div class="candle-glow">
                <div class="flame">🕯</div>
                <div class="label">Lichtarze są zapalone</div>
            </div>

            <!-- Safewords -->
            <div class="safeword-block">
                <h3>⚑ Safewords</h3>
                <div class="sw-row">
                    <button type="button" class="sw-btn green" onclick="document.getElementById('klub-input').value='*Mówię cicho:* — Zielony.';document.getElementById('klub-input').focus()">
                        <span class="w">Zielony</span>
                        <span class="l">dalej · tak · jeszcze</span>
                    </button>
                    <button type="button" class="sw-btn yellow" onclick="document.getElementById('klub-input').value='*Mówię ostrzej:* — Żółty.';document.getElementById('klub-input').focus()">
                        <span class="w">Żółty</span>
                        <span class="l">wolniej · pauza</span>
                    </button>
                </div>
                <button type="button" class="sw-btn red" onclick="document.getElementById('klub-input').value='*Mówię głośno i wyraźnie:* — CZERWONY.';document.getElementById('klub-input').focus()">
                    <span class="w">Czerwony</span>
                    <span class="l">stop · natychmiast · scena kończy się teraz</span>
                </button>
            </div>

            <!-- Gospodyni pokoju -->
            <div class="aside-h">◈ Gospodyni pokoju</div>
            <div class="npc-card">
                <div class="head">
                    <div class="av">MV</div>
                    <div class="info">
                        <div class="nm">Madame <i>Vex</i></div>
                        <div class="ti">DOMINA · GOSPODYNI · NPC</div>
                    </div>
                </div>
                <div class="desc">
                    Rzemieślniczka czerwonego sznura. Słucha. Patrzy. Zapamiętuje, kto powiedział „czerwony" — i nie zapomina, kto się tego nie wstydził.
                </div>
            </div>

            <!-- Zasady -->
            <div class="aside-h">◈ Zasady</div>
            <div class="rules-mini">
                <b>Trzy kropki</b>
                <strong style="color:#fff;font-style:normal">Consent wprost.</strong> Wszystko jest fabułą postaci — pytasz, druga osoba odpowiada. Domysł nie jest zgodą.<br><br>
                <strong style="color:#fff;font-style:normal">Safewords święte.</strong> Czerwony przerywa scenę. Bez tłumaczenia, bez wyjaśniania.<br><br>
                <strong style="color:#fff;font-style:normal">Ten pokój jest prywatny.</strong> Co dzieje się tu, nie wynosi się do baru. Chyba że obie strony chcą.
            </div>

            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood [tekst] · /do lobby · /do sala-glowna
            </div>
        </div>

        <!-- CENTER — chat -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Pokój BDSM
                    </div>
                    <div class="title">Świece i sznur</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--bdsm-gold);font-size:.9em;">
                    🔐 prywatne · poza zasięgiem
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="bdsm" data-last-id="<?php echo klub_last_id($polaczenie, 'bdsm'); ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Świece migają. Ktoś musi zacząć — opisem albo dialogiem.<br>
                    Pamiętaj o zgodzie. Pamiętaj o safewordach.
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder="*Robisz krok w stronę kotary. Zatrzymujesz się w progu.*"
                        required></textarea>
                    <button type="submit" class="btn-send">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- RIGHT — goście -->
        <div class="kol-right">
            <div class="aside-h">👥 W pokoju <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Pokój jest pusty.<br>Tylko świece.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'w pokoju';
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
                ['sauna','♨','Sauna'],
                ['tyly','🚬','Tyły'],
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