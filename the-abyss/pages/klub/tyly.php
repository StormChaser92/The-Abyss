<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — TYŁY KLUBU
// Boczne wyjście, sodowa lampa, automat z papierosami, NPC Mirek
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'tyly');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'tyly', 60);
?>

<style>
/* Tyły: sodowa lampa = pomarańczowo-ochrowy zamiast czerwieni */
.klub-wrap.tyly {
    --room-accent: #ff9a3d;
    --sodium: #ffae5c;
}
.klub-wrap.tyly .kol-left,
.klub-wrap.tyly .kol-right { background: rgba(15,10,7,0.55); }
.klub-wrap.tyly .kol-center { background: rgba(8,5,3,0.6); }

.klub-wrap.tyly .room-header h2 {
    font-family: 'Oswald', sans-serif; font-weight: 400;
    font-size: 1.6em; letter-spacing: 2px; line-height: 1;
    text-transform: uppercase;
    text-shadow: 0 0 14px rgba(255,154,61,0.5);
}
.klub-wrap.tyly .room-header .sub {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); line-height: 1.45;
}
.klub-wrap.tyly .chat-head .title {
    text-shadow: 0 0 8px rgba(255,154,61,0.4);
}

/* Aside header — sodowy */
.klub-wrap.tyly .aside-h {
    color: var(--sodium);
    border-left-color: var(--sodium);
    background: linear-gradient(90deg, rgba(255,154,61,0.08), transparent);
}

/* Lampa sodowa — migająca */
.lamp-box {
    padding: 16px; margin-bottom: 14px;
    background: radial-gradient(ellipse at 50% 0%, rgba(255,174,92,0.15), transparent 70%);
    border: 1px solid rgba(255,154,61,0.2);
    border-radius: 2px; text-align: center;
    position: relative; overflow: hidden;
}
.lamp-box::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 60%;
    background: radial-gradient(ellipse at 50% 0%, rgba(255,174,92,0.1), transparent 70%);
    pointer-events: none;
    animation: lampFlicker 6s ease-in-out infinite;
}
@keyframes lampFlicker {
    0%, 19%, 21%, 23%, 25%, 54%, 56%, 100% { opacity: 1; }
    20%, 24%, 55% { opacity: .55; }
}
.lamp-box .ic {
    font-size: 2em; line-height: 1; position: relative;
    filter: drop-shadow(0 0 18px rgba(255,174,92,0.8));
    animation: lampFlicker 6s ease-in-out infinite;
}
.lamp-box .label {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--sodium); letter-spacing: 3px;
    text-transform: uppercase; margin-top: 8px;
    position: relative;
}

/* Pogoda — deszcz */
.weather-box {
    padding: 10px 14px; margin-bottom: 14px;
    background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft);
    border-left: 2px solid #6b8eff;
    border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .92em; color: var(--txt-dim); line-height: 1.5;
}
.weather-box b {
    color: #a0b8ff; font-style: normal;
    font-family: 'Oswald', sans-serif; font-size: .82em;
    letter-spacing: 1.5px; text-transform: uppercase;
    display: block; margin-bottom: 4px;
}

/* Automat z papierosami */
.cig-pack {
    padding: 8px 12px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); margin-bottom: 5px;
    transition: .15s; display: flex; justify-content: space-between;
    align-items: center; gap: 8px; border-radius: 2px;
}
.cig-pack:hover { border-color: var(--sodium); background: rgba(255,154,61,0.05); }
.cig-pack .nm {
    font-family: 'Fraunces', serif; font-size: .92em;
    color: #fff; line-height: 1.1; flex: 1;
}
.cig-pack .nm i { color: var(--sodium); font-style: italic; }
.cig-pack .nm .d {
    display: block; font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .78em; color: var(--txt-dim);
    margin-top: 1px; line-height: 1.2;
}
.cig-pack .pr {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--sodium); letter-spacing: 1px; white-space: nowrap;
}

/* Ochroniarz Mirek - NPC card */
.npc-card-mirek {
    padding: 12px; background: rgba(0,0,0,0.45);
    border: 1px solid rgba(255,154,61,0.3); border-radius: 2px;
    margin-bottom: 10px;
}
.npc-card-mirek .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-card-mirek .av {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #2a1a08, #0a0408);
    border: 1px solid var(--sodium); color: var(--sodium);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .8em; flex-shrink: 0;
}
.npc-card-mirek .info { flex: 1; min-width: 0; font-family: 'Fraunces', serif; color: #fff; }
.npc-card-mirek .info .nm { font-size: 1em; line-height: 1; }
.npc-card-mirek .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1.5px; margin-top: 3px;
    text-transform: uppercase;
}
.npc-card-mirek .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

/* Wiadomości w Tyłach: lekko przyciemnione */
.klub-wrap.tyly .msg .txt { color: #e6dac8; }
.klub-wrap.tyly .msg .who .role {
    color: var(--sodium); border-color: rgba(255,154,61,0.4);
    background: rgba(255,154,61,0.08);
}

/* Deszcz — overlay na całym feed */
.klub-wrap.tyly .feed {
    position: relative;
    background: 
        linear-gradient(180deg, rgba(8,5,3,0.4), transparent 30%, rgba(8,5,3,0.4)),
        radial-gradient(ellipse at 50% 100%, rgba(255,154,61,0.05), transparent 70%);
}
.klub-wrap.tyly .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .15;
    background: 
        repeating-linear-gradient(80deg, transparent 0 6px, rgba(160,184,255,0.4) 6px 7px, transparent 7px 14px);
    animation: rainSlide 1.2s linear infinite;
}
@keyframes rainSlide {
    from { background-position: 0 0; }
    to   { background-position: -40px 60px; }
}

/* Citrofon ochroniarza Mirka */
.intercom {
    margin-top: 10px; padding: 10px 14px;
    background: rgba(0,0,0,0.5);
    border: 1px solid var(--border-soft);
    border-left: 2px dashed var(--sodium);
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace;
    font-size: .82em; color: var(--txt-dim);
    letter-spacing: .5px; line-height: 1.5;
}
.intercom b { color: var(--sodium); }
</style>

<div class="klub-wrap tyly">
    <div class="klub-3col">

        <!-- LEFT — opis, lampa, automat, NPC -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>🚬 Tyły klubu</h2>
                <div class="sub">
                    Boczne wyjście, cegła, śmietnik, kałuża pod lampą.<br>
                    <i>Tu przychodzą rozmowy, których nie da się zacząć przy barze.</i>
                </div>
                <div class="stats">
                    <span>NA ZEWNĄTRZ: <b><?php echo $liczba_obecnych; ?></b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <!-- Lampa sodowa -->
            <div class="lamp-box">
                <div class="ic">💡</div>
                <div class="label">Sodowa lampa · miga</div>
            </div>

            <!-- Pogoda -->
            <div class="weather-box">
                <b>◆ Na zewnątrz</b>
                Pada — drobny, listopadowy deszcz. Asfalt lśni pod lampą sodową, dym papierosa wisi w powietrzu jak zatrzymana kropla.
            </div>

            <!-- Automat z papierosami -->
            <div class="aside-h">🚬 Z automatu</div>
            <div class="cig-pack">
                <div class="nm">Czarny <i>Zefir</i><span class="d">mocny, gorzki, stary</span></div>
                <div class="pr">5 $</div>
            </div>
            <div class="cig-pack">
                <div class="nm">Biała <i>Syrena</i><span class="d">mentol, lekki</span></div>
                <div class="pr">4 $</div>
            </div>
            <div class="cig-pack">
                <div class="nm">Goździk <i>Kretek</i><span class="d">słodkie, dymią grubo</span></div>
                <div class="pr">7 $</div>
            </div>
            <div class="cig-pack">
                <div class="nm">Cygaro <i>Don Vidal</i><span class="d">kubańskie, na dłuższą rozmowę</span></div>
                <div class="pr">25 $</div>
            </div>
            <div style="font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.78em;color:var(--txt-mute);padding:6px 4px;line-height:1.4">
                Automat zżera monety bez wydawania reszty. RP only — fizyczne transakcje w Fazie 2.
            </div>

            <!-- Ochrona -->
            <div class="aside-h">◈ Ochrona</div>
            <div class="npc-card-mirek">
                <div class="head">
                    <div class="av">MR</div>
                    <div class="info">
                        <div class="nm">Ochroniarz <i>Mirek</i></div>
                        <div class="ti">BRAMKARZ · NPC · widzi wszystko</div>
                    </div>
                </div>
                <div class="desc">
                    Stoi przy drzwiach, palce zaciśnięte na styropianowym kubku z kawą. Twarz jak betonowa płyta. Nie zaczyna rozmów. Nie kończy ich, jeśli sam nie chce. Ma listę i pamięć.
                </div>
            </div>

            <!-- Citrofon -->
            <div class="intercom">
                <b>// CITROFON · LIVE</b><br>
                Jeśli ktoś robi się głośny — Mirek otwiera drzwi i mówi tylko: „<b>Wracaj do środka albo idź dalej.</b>"
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
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Tyły
                    </div>
                    <div class="title">🚬 Asfalt i deszcz</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--sodium);font-size:.9em;">
                    Mirek pilnuje drzwi
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="tyly" data-last-id="<?php echo klub_last_id($polaczenie, 'tyly'); ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Cisza. Tylko deszcz i pojedyncza syrena gdzieś dwie ulice dalej.<br>
                    Ktoś musi zapalić pierwszego papierosa.
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
                        placeholder="*Wyciągasz papierosa. Pochylasz się przy ścianie, żeby osłonić zapałkę przed wiatrem.*"
                        required></textarea>
                    <button type="submit" class="btn-send">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- RIGHT — goście -->
        <div class="kol-right">
            <div class="aside-h">👥 W tyłach <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Tylko Mirek i deszcz.<br>I twój papieros.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'pali w cieniu';
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
                ['bdsm','⛓','BDSM'],
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