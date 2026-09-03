<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — SALA BALOWA (Faza 5)
// 
// Klimat: gala, parkiet, orkiestra. Aktywne wydarzenia.
// Specjalne widgety:
//   - Plakat aktywnego eventu na ścianie + lista uczestników
//   - Licznik tańczących (gracze z mood zawierającym "taniec/parkiet")
//   - Orkiestra (random kapela na nocy)
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'sala-balowa');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'sala-balowa', 60);
$last_id = klub_last_id($polaczenie, 'sala-balowa');

// FAZA 5: Aktywne wydarzenie w sali
$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'sala-balowa', $id_gracza);
$uczestnicy = $aktywny_event ? klub_uczestnicy_eventu($polaczenie, (int)$aktywny_event['id']) : [];

// Licznik tańczących (mood zawiera "taniec", "parkiet", "tańczę")
$tanczacych = 0;
foreach ($obecni as $o) {
    $m = mb_strtolower($o['klub_mood'] ?? '');
    if ($m && (strpos($m, 'taniec') !== false || strpos($m, 'parkiet') !== false
            || strpos($m, 'tańcz') !== false || strpos($m, 'tancz') !== false)) {
        $tanczacych++;
    }
}

// Random kapela nocy (deterministyczna od daty)
$kapele = [
    ['nazwa' => 'Kameralny kwartet "Skarb i Stół"', 'styl' => 'Kwartet smyczkowy', 'utwor' => 'Vivaldi · Cztery pory roku — zima'],
    ['nazwa' => 'Big Band Marcellino', 'styl' => 'Swing lat 30.', 'utwor' => 'Sing, Sing, Sing'],
    ['nazwa' => 'Trio Saint Germain', 'styl' => 'Jazz manouche', 'utwor' => 'Minor Swing'],
    ['nazwa' => 'Orkiestra "Czerwone Spódnice"', 'styl' => 'Tango argentyńskie', 'utwor' => 'Por Una Cabeza'],
    ['nazwa' => 'Sekstet "Złote Klamry"', 'styl' => 'Cabaret', 'utwor' => 'Mein Herr'],
    ['nazwa' => 'Duet fortepian + saksofon', 'styl' => 'Smooth jazz', 'utwor' => 'Misty'],
    ['nazwa' => 'Kapela "Krwawe Walce"', 'styl' => 'Walc wiedeński', 'utwor' => 'Nad pięknym modrym Dunajem'],
];
$dzien_dlugi = (int)date('z') + (int)date('Y') * 1000; // unikalna liczba per dzień+rok
$kapela = $kapele[$dzien_dlugi % count($kapele)];
?>

<style>
.klub-wrap.sala-balowa {
    --room-accent: var(--neon-gold);
    --bal-deep: #5a4000;
}
.klub-wrap.sala-balowa .kol-left,
.klub-wrap.sala-balowa .kol-right { background: rgba(15,10,3,0.5); }
.klub-wrap.sala-balowa .kol-center { background: rgba(8,5,2,0.6); }

.klub-wrap.sala-balowa .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.7em; letter-spacing: 1px;
    line-height: 1.1; text-transform: none;
    text-shadow: 0 0 14px rgba(255,215,0,0.5);
}
.klub-wrap.sala-balowa .chat-head .title {
    font-family: 'Fraunces', serif; font-weight: 400;
    text-transform: none; letter-spacing: 1px; font-size: 1.1em;
}

/* ── PLAKAT EVENTU NA ŚCIANIE ───────────────────────────────── */
.event-poster-wall {
    position: relative; padding: 16px 18px; margin-bottom: 14px;
    border: 1px solid var(--neon-gold); border-radius: 2px;
    background: linear-gradient(135deg, rgba(255,215,0,0.1), rgba(15,10,3,0.85));
    overflow: hidden;
}
.event-poster-wall.has-img {
    background-size: cover; background-position: center;
    min-height: 200px;
    display: flex; flex-direction: column; justify-content: flex-end;
}
.event-poster-wall.has-img::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(180deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.2) 30%, rgba(0,0,0,0.9) 100%);
    z-index: 1;
}
.event-poster-wall > * { position: relative; z-index: 2; }
.event-poster-wall .live-badge {
    display: inline-block; padding: 2px 10px;
    background: var(--neon-red); color: #fff;
    font-family: 'Oswald', sans-serif; font-size: .68em;
    letter-spacing: 2.5px; text-transform: uppercase;
    border-radius: 2px; box-shadow: 0 0 10px rgba(255,23,68,0.5);
    animation: liveBlink 1.6s infinite;
    margin-bottom: 8px;
}
@keyframes liveBlink { 50% { opacity: .6; } }
.event-poster-wall .event-tytul {
    font-family: 'Fraunces', serif; font-size: 1.3em;
    color: #fff; line-height: 1.15;
    text-shadow: 0 0 10px rgba(255,215,0,0.4), 0 2px 4px rgba(0,0,0,0.8);
}
.event-poster-wall .event-tytul .ikona {
    color: var(--neon-gold); margin-right: 4px;
}
.event-poster-wall .event-meta {
    margin-top: 6px;
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: #fff; letter-spacing: 1.5px; line-height: 1.5;
    text-shadow: 0 1px 3px rgba(0,0,0,0.8);
    display: flex; flex-wrap: wrap; gap: 10px;
}
.event-poster-wall .event-meta .lab { color: var(--neon-gold); }
.event-poster-wall .event-opis {
    margin-top: 8px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: #f0e8d8; font-size: .95em; line-height: 1.45;
    text-shadow: 0 1px 3px rgba(0,0,0,0.7);
}

.uczestnicy-event {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(60px,1fr));
    gap: 4px; margin-top: 8px;
    padding-top: 8px; border-top: 1px dashed rgba(255,215,0,0.3);
}
.uczestnik-mini {
    text-align: center; font-family: 'JetBrains Mono', monospace;
    font-size: .65em; color: var(--txt-dim);
    text-decoration: none;
}
.uczestnik-mini .av {
    width: 32px; height: 32px; border-radius: 50%; margin: 0 auto 2px;
    background: linear-gradient(135deg, #2a1f00, #050402);
    border: 1px solid var(--neon-gold); color: var(--neon-gold);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .9em;
}
.uczestnik-mini .nm {
    color: #fff; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; max-width: 60px; display: block;
}
.uczestnik-mini.is-here .av {
    box-shadow: 0 0 10px rgba(90,255,154,0.5);
    border-color: var(--neon-green);
}
.uczestnik-mini.is-me .av {
    border-color: var(--neon-cyan); color: var(--neon-cyan);
    box-shadow: 0 0 10px rgba(74,214,255,0.5);
}

/* ── PARKIET — licznik tańczących ─────────────────────────────── */
.parkiet-info {
    padding: 12px 14px; margin-bottom: 14px;
    background: linear-gradient(135deg, rgba(255,215,0,0.05), rgba(0,0,0,0.4));
    border: 1px solid rgba(255,215,0,0.25); border-radius: 2px;
    text-align: center;
}
.parkiet-info .label {
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 4px;
}
.parkiet-info .liczba {
    font-family: 'Oswald', sans-serif; font-size: 2em;
    color: var(--neon-gold); line-height: 1;
    text-shadow: 0 0 12px rgba(255,215,0,0.6);
}
.parkiet-info .szept {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .85em; color: var(--txt-dim); margin-top: 4px;
}

/* ── ORKIESTRA ─────────────────────────────────────────────── */
.orkiestra-box {
    padding: 10px 12px; margin-bottom: 10px;
    background: rgba(0,0,0,0.45);
    border-left: 2px solid var(--neon-gold);
    border-radius: 2px;
}
.orkiestra-box .ic-row {
    display: flex; align-items: center; gap: 6px;
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-gold); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 6px;
}
.orkiestra-box .nm {
    font-family: 'Fraunces', serif; font-size: 1em;
    color: #fff; line-height: 1.15;
}
.orkiestra-box .nm i { color: var(--neon-gold); font-style: italic; }
.orkiestra-box .styl {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .82em; color: var(--txt-dim); margin-top: 3px;
}
.orkiestra-box .utwor {
    margin-top: 6px; padding-top: 6px;
    border-top: 1px dashed rgba(255,215,0,0.2);
    font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .85em;
    color: var(--neon-gold);
}

/* Sala Balowa: złoty akcent na wiadomości */
.klub-wrap.sala-balowa .msg .txt { color: #f5ecd6; }

/* Płatki na podłodze - subtelna animacja tła */
.klub-wrap.sala-balowa .feed {
    position: relative;
    background: radial-gradient(ellipse at 50% 100%, rgba(255,215,0,0.04), transparent 70%);
}

/* CTA Idź na event */
.event-cta {
    margin-top: 8px;
    display: flex; gap: 6px;
}
.event-cta button, .event-cta a {
    flex: 1; padding: 6px 10px;
    background: rgba(255,215,0,0.1); border: 1px solid var(--neon-gold);
    color: var(--neon-gold); font-family: 'Oswald', sans-serif;
    font-size: .72em; letter-spacing: 1.5px; text-transform: uppercase;
    cursor: pointer; border-radius: 2px; transition: .15s;
    text-decoration: none; text-align: center;
}
.event-cta button:hover, .event-cta a:hover { background: var(--neon-gold); color: #000; }
</style>

<div class="klub-wrap sala-balowa">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>💃 Sala <i>Balowa</i></h2>
                <div class="sub">Złoto, kandelabry, parkiet z dębowych tafli. Tu odbywają się wydarzenia.</div>
                <div class="stats">
                    <span>GOŚCI: <b><?php echo $liczba_obecnych; ?>/60</b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <!-- ── Plakat aktywnego eventu ── -->
            <?php if ($aktywny_event):
                $kolor = htmlspecialchars($aktywny_event['kolor_plakatu']);
                $ikona = htmlspecialchars($aktywny_event['ikona_emoji'] ?: '✦');
                $plakat = !empty($aktywny_event['plakat_url']) ? htmlspecialchars($aktywny_event['plakat_url']) : '';
                $autor = htmlspecialchars($aktywny_event['autor_login'] ?: 'Barman');
                $rez = (int)$aktywny_event['liczba_rezerwacji'];
                $max = (int)$aktywny_event['max_miejsc'];
            ?>
            <div class="event-poster-wall <?php echo $plakat ? 'has-img' : ''; ?> <?php echo $kolor; ?>"
                 <?php if ($plakat): ?>style="background-image:url('<?php echo $plakat; ?>')"<?php endif; ?>>
                <div>
                    <span class="live-badge">● TRWA TERAZ</span>
                    <div class="event-tytul">
                        <span class="ikona"><?php echo $ikona; ?></span>
                        <?php echo htmlspecialchars($aktywny_event['nazwa']); ?>
                    </div>
                    <div class="event-meta">
                        <span><span class="lab">Prowadzi:</span> <?php echo $autor; ?></span>
                        <span><span class="lab">Goście:</span> <?php echo $rez; ?>/<?php echo $max; ?></span>
                    </div>
                    <?php if (!empty($aktywny_event['opis'])): ?>
                    <div class="event-opis">
                        <?php
                        $opis_short = mb_substr($aktywny_event['opis'], 0, 200);
                        if (mb_strlen($aktywny_event['opis']) > 200) $opis_short .= '...';
                        echo nl2br(htmlspecialchars($opis_short));
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista uczestników eventu -->
            <?php if (!empty($uczestnicy)): ?>
            <div class="aside-h">✦ Uczestnicy (<?php echo count($uczestnicy); ?>)</div>
            <div class="uczestnicy-event">
                <?php foreach ($uczestnicy as $u):
                    $here = ($u['klub_sala'] === 'sala-balowa');
                    $is_me = ((int)$u['id'] === $id_gracza);
                    $klasa = '';
                    if ($here) $klasa .= 'is-here ';
                    if ($is_me) $klasa .= 'is-me';
                    $inic = klub_inicjaly($u['login']);
                ?>
                <a href="game.php?page=profil&id=<?php echo (int)$u['id']; ?>" class="uczestnik-mini <?php echo $klasa; ?>" title="<?php echo htmlspecialchars($u['login']); ?><?php echo $here ? ' · jest na sali' : ''; ?>">
                    <div class="av"><?php echo htmlspecialchars($inic); ?></div>
                    <span class="nm"><?php echo htmlspecialchars($u['login']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="aside-h">◈ Wydarzenia</div>
            <div style="padding:14px 12px;background:rgba(0,0,0,0.35);border:1px dashed var(--border-soft);border-radius:2px;text-align:center;color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.92em;line-height:1.5">
                Sala czeka.<br>
                <small style="font-size:.85em;display:block;margin-top:6px;font-family:'JetBrains Mono',monospace;font-style:normal;letter-spacing:1px;text-transform:uppercase">brak aktywnego wydarzenia</small>
                <a href="game.php?page=czat&sala=lobby&widok=wydarzenia" style="display:inline-block;margin-top:10px;padding:6px 14px;background:rgba(255,215,0,0.1);border:1px solid rgba(255,215,0,0.4);color:var(--neon-gold);text-decoration:none;font-family:'Oswald',sans-serif;font-size:.72em;letter-spacing:1.5px;text-transform:uppercase;border-radius:2px">📋 Lista wydarzeń</a>
            </div>
            <?php endif; ?>

            <!-- Parkiet — licznik tańczących -->
            <div class="aside-h" style="margin-top:14px">💃 Parkiet</div>
            <div class="parkiet-info">
                <div class="label">tańczących</div>
                <div class="liczba"><?php echo $tanczacych; ?></div>
                <div class="szept">
                    <?php if ($tanczacych === 0): ?>
                        Parkiet pusty. Czeka na pierwszą parę.
                    <?php elseif ($tanczacych === 1): ?>
                        Ktoś tańczy w samotności.
                    <?php elseif ($tanczacych < 5): ?>
                        Kilka par na parkiecie.
                    <?php else: ?>
                        Parkiet wibruje.
                    <?php endif; ?>
                </div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:.62em;color:var(--txt-mute);margin-top:8px;letter-spacing:1px">
                    /mood taneczny / na parkiecie
                </div>
            </div>

            <!-- Orkiestra -->
            <div class="aside-h">🎻 Orkiestra dziś</div>
            <div class="orkiestra-box">
                <div class="ic-row">🎻 NA SCENIE</div>
                <div class="nm"><?php echo htmlspecialchars($kapela['nazwa']); ?></div>
                <div class="styl"><?php echo htmlspecialchars($kapela['styl']); ?></div>
                <div class="utwor">♪ <?php echo htmlspecialchars($kapela['utwor']); ?></div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood [tekst] · /flirt @nick · /do [sala] · /edytuj /usun
            </div>
        </div>

        <!-- ══════ KOL CENTER — CHAT ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Sala Balowa
                    </div>
                    <div class="title">💃 Złota sala</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--neon-gold);font-size:.9em;">
                    <?php if ($aktywny_event): ?>
                        ● Wydarzenie trwa
                    <?php else: ?>
                        Cisza między balami
                    <?php endif; ?>
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="sala-balowa" data-last-id="<?php echo $last_id; ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Kandelabry rzucają miękkie światło na pusty parkiet.<br>
                    Posadzka czeka. Kto pierwszy zatańczy?
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood na parkiecie')">💃 /mood parkiet</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Wchodzisz po marmurowych schodach. Orkiestra przygrywa walc.* — "Pozwoli Pani prowadzić?"'
                        required></textarea>
                    <button type="submit" class="btn-send">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 W sali <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Sala pusta.<br>Echo posadzki.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'na sali';
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
                ['vip','🥂','VIP'],
                ['taras','🌃','Taras'],
                ['bdsm','⛓','BDSM'],
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