<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — SAUNA
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'sauna');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'sauna', 60);
?>

<style>
/* Sauna używa cieplejszego, spokojniejszego akcentu */
.klub-wrap.sauna { --room-accent: #f2b87a; }
.klub-wrap.sauna .kol-left,
.klub-wrap.sauna .kol-right,
.klub-wrap.sauna .kol-center { background: rgba(20,14,8,0.45); }
.klub-wrap.sauna .room-header h2 {
    font-family: 'Cormorant Garamond', serif; font-weight: 500;
    font-size: 1.6em; letter-spacing: 1px;
    text-shadow: 0 0 14px rgba(242,184,122,0.4);
    text-transform: none;
}
.klub-wrap.sauna .chat-head .title {
    font-family: 'Cormorant Garamond', serif; font-weight: 500;
    text-transform: none; letter-spacing: 1px; font-size: 1.1em;
}
.klub-wrap.sauna .msg .txt { color: #f0e4d4; }
.klub-wrap.sauna .guest-row { background: rgba(0,0,0,0.3); }

/* Para — animacja tła feedu */
.klub-wrap.sauna .feed {
    position: relative;
    background: radial-gradient(ellipse at 50% 80%, rgba(242,184,122,0.04), transparent 70%);
}
.klub-wrap.sauna .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse at 30% 70%, rgba(255,220,180,0.08), transparent 50%),
                radial-gradient(ellipse at 70% 30%, rgba(255,220,180,0.05), transparent 50%);
    animation: saunaSteam 10s ease-in-out infinite alternate;
}
@keyframes saunaSteam {
    from { opacity: .5; transform: translateY(0); }
    to   { opacity: 1; transform: translateY(-10px); }
}

/* Termometr */
.temp-gauge {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    margin-bottom: 14px;
}
.temp-box {
    padding: 10px 12px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); border-radius: 2px;
    text-align: center;
}
.temp-box.hot { border-color: rgba(242,184,122,0.4); }
.temp-box .l {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px; text-transform: uppercase;
}
.temp-box .v {
    font-family: 'Fraunces', serif; font-size: 1.9em;
    color: #fff; line-height: 1; margin-top: 3px;
}
.temp-box.hot .v { color: #f2b87a; text-shadow: 0 0 10px rgba(242,184,122,0.6); }
.temp-box .u {
    font-size: .45em; color: var(--txt-dim); margin-left: 2px;
    font-family: 'JetBrains Mono', monospace;
}

/* Rytuały (info fabularne) */
.ritual {
    padding: 10px 12px; background: rgba(242,184,122,0.05);
    border-left: 2px solid rgba(242,184,122,0.4);
    margin-bottom: 6px;
}
.ritual .rt-nm {
    font-family: 'Fraunces', serif; color: #fff;
    font-size: .95em; line-height: 1.1;
}
.ritual .rt-nm i { color: #f2b87a; font-style: italic; }
.ritual .rt-d {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .82em; color: var(--txt-dim);
    margin-top: 3px; line-height: 1.4;
}
</style>

<div class="klub-wrap sauna">
    <div class="klub-3col">

        <!-- LEFT -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>♨ Sauna</h2>
                <div class="sub">Para, cisza, pot. Drzwi zamykają się ciężko — tu zostawia się rozmowy na zewnątrz.</div>
                <div class="stats">
                    <span>GOŚCI: <b><?php echo $liczba_obecnych; ?>/8</b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <div class="temp-gauge">
                <div class="temp-box hot">
                    <div class="l">Temperatura</div>
                    <div class="v">82<span class="u">°C</span></div>
                </div>
                <div class="temp-box">
                    <div class="l">Wilgotność</div>
                    <div class="v">94<span class="u">%</span></div>
                </div>
            </div>

            <div class="aside-h">◈ Rytuały</div>
            <div class="ritual">
                <div class="rt-nm">Okłady <i>z brzozowych liści</i></div>
                <div class="rt-d">Ciepło pachnie żywicą. Każde uderzenie liściem zostawia czerwony ślad na skórze.</div>
            </div>
            <div class="ritual">
                <div class="rt-nm">Kubek <i>herbaty iwan-czaj</i></div>
                <div class="rt-d">Gorzka, ziołowa, z miodem. Podawana po wyjściu z kąpieli, tuż przy basenie schłodzenia.</div>
            </div>
            <div class="ritual">
                <div class="rt-nm">Cisza <i>przed północą</i></div>
                <div class="rt-d">Od 23:45 do 00:15 nikt nie mówi. Tak chce Heiko.</div>
            </div>

            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood [tekst] · /do lobby · /do sala-glowna
            </div>
        </div>

        <!-- CENTER -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Sauna
                    </div>
                    <div class="title"><span class="ic">♨</span> Para i cisza</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--txt-dim);font-size:.9em;">
                    NPC: Heiko, saunamistrz
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="sauna" data-last-id="<?php echo klub_last_id($polaczenie, 'sauna'); ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Para zasłania widok. Drewno skrzypi pod ciężarem wilgoci.<br>
                    Nikogo tu nie ma — jeszcze.
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
                        placeholder="*Siadasz bliżej pieca. Gorąco przenika przez skórę.*"
                        required></textarea>
                    <button type="submit" class="btn-send">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="kol-right">
            <div class="aside-h">👥 W saunie <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Tylko para. I cisza.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'w parze';
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
                ['bdsm','⛓','BDSM'],
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