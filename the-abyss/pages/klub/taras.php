<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — TARAS DACHOWY (Faza 5)
// 
// 23. piętro Manhattanu. Widok na miasto.
// Specjalne widgety:
//   - Pogoda live z open-meteo dla NYC (cache 1h)
//   - Animacja efektu pogody (deszcz/śnieg/mgła/gwiazdy)
//   - Opis miasta pod stopami
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'taras');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'taras', 60);
$last_id = klub_last_id($polaczenie, 'taras');

$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'taras', $id_gracza);

// Lokalizacja klubu
$lok = $polaczenie->query("SELECT * FROM klub_konfiguracja_lokalizacja WHERE id=1")->fetch_assoc();

// Pogoda — z cache (jeśli stara, JS ją odświeży)
$pogoda_cache = $polaczenie->query("SELECT * FROM klub_pogoda_cache WHERE id=1")->fetch_assoc();

// Helpery (te same co w api/klub_pogoda.php — duplikat na potrzeby render server-side)
function taras_pogoda_opis($kod, $jest_noc = 0) {
    $opisy = [
        0 => $jest_noc ? 'Pogodna noc' : 'Słonecznie',
        1 => 'Lekko pochmurno', 2 => 'Częściowe zachmurzenie', 3 => 'Pochmurno',
        45 => 'Mgła', 48 => 'Gęsta mgła',
        51 => 'Lekka mżawka', 53 => 'Mżawka', 55 => 'Gęsta mżawka',
        56 => 'Marznąca mżawka', 57 => 'Marznąca mżawka',
        61 => 'Lekki deszcz', 63 => 'Deszcz', 65 => 'Ulewa',
        66 => 'Marznący deszcz', 67 => 'Marznący deszcz',
        71 => 'Lekki śnieg', 73 => 'Śnieg', 75 => 'Gęsty śnieg', 77 => 'Ziarna śniegu',
        80 => 'Przelotne opady', 81 => 'Przelotne opady', 82 => 'Ulewne przelotne',
        85 => 'Przelotny śnieg', 86 => 'Gęsty przelotny śnieg',
        95 => 'Burza', 96 => 'Burza z gradem', 99 => 'Silna burza',
    ];
    return $opisy[$kod] ?? 'Pogoda';
}
function taras_pogoda_ikona($kod, $jest_noc = 0) {
    if ($kod === 0) return $jest_noc ? '🌙' : '☀️';
    if ($kod >= 1 && $kod <= 3) return $jest_noc ? '☁️' : '⛅';
    if ($kod === 45 || $kod === 48) return '🌫️';
    if (($kod >= 51 && $kod <= 67) || ($kod >= 80 && $kod <= 82)) return '🌧️';
    if (($kod >= 71 && $kod <= 77) || $kod === 85 || $kod === 86) return '❄️';
    if ($kod >= 95) return '⛈️';
    return '☁️';
}
function taras_efekt($kod) {
    if ($kod === 0) return 'clear';
    if ($kod >= 1 && $kod <= 3) return 'cloudy';
    if ($kod === 45 || $kod === 48) return 'fog';
    if (($kod >= 51 && $kod <= 67) || ($kod >= 80 && $kod <= 82)) return 'rain';
    if (($kod >= 71 && $kod <= 77) || $kod === 85 || $kod === 86) return 'snow';
    if ($kod >= 95) return 'storm';
    return 'cloudy';
}

$kod_pog = (int)($pogoda_cache['kod_pogody'] ?? 0);
$jest_noc = (int)($pogoda_cache['jest_noc'] ?? 0);
$temp = $pogoda_cache['temperatura_c'] !== null ? (float)$pogoda_cache['temperatura_c'] : null;
$wiatr = $pogoda_cache['wiatr_kmh'] !== null ? (float)$pogoda_cache['wiatr_kmh'] : null;
$wilg = $pogoda_cache['wilgotnosc'] !== null ? (int)$pogoda_cache['wilgotnosc'] : null;
$opis_pog = $pogoda_cache['opis'] ?: taras_pogoda_opis($kod_pog, $jest_noc);
$ikona_pog = taras_pogoda_ikona($kod_pog, $jest_noc);
$efekt = taras_efekt($kod_pog);
$pogoda_pobrano = $pogoda_cache['pobrano_o'] ?? null;
$ma_dane = ($temp !== null);

$miasto = $pogoda_cache['miasto'] ?? $lok['miasto'];
?>

<style>
.klub-wrap.taras {
    --room-accent: #b45fff;
    --taras-night: #1a1530;
}
.klub-wrap.taras .kol-left,
.klub-wrap.taras .kol-right { background: rgba(8,5,18,0.55); }
.klub-wrap.taras .kol-center { background: rgba(5,3,12,0.65); }

.klub-wrap.taras .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.6em; letter-spacing: 1px; line-height: 1.1;
    text-transform: none;
    text-shadow: 0 0 14px rgba(180,95,255,0.5);
}
.klub-wrap.taras .chat-head .title {
    font-family: 'Fraunces', serif; font-weight: 400;
    text-transform: none; letter-spacing: 1px;
}

/* ── WIDGET POGODY ─────────────────────────────────────────── */
.pogoda-box {
    position: relative; padding: 16px;
    background: linear-gradient(135deg, rgba(180,95,255,0.1), rgba(0,0,0,0.5));
    border: 1px solid rgba(180,95,255,0.3); border-radius: 2px;
    overflow: hidden; margin-bottom: 14px;
}
.pogoda-box.noc {
    background: linear-gradient(135deg, rgba(15,5,40,0.9), rgba(0,0,0,0.7));
    border-color: rgba(180,95,255,0.4);
}
.pogoda-box .miasto-row {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 8px; padding-bottom: 6px;
    border-bottom: 1px dashed rgba(180,95,255,0.3);
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1.5px; text-transform: uppercase;
}
.pogoda-box .miasto {
    color: #c896ff;
}
.pogoda-box .pobrano {
    font-size: .85em;
}
.pogoda-box .glowny-row {
    display: flex; align-items: center; gap: 14px;
}
.pogoda-box .ikona-big {
    font-size: 3.5em; line-height: 1;
    filter: drop-shadow(0 0 12px rgba(180,95,255,0.5));
}
.pogoda-box .info {
    flex: 1; min-width: 0;
}
.pogoda-box .temp {
    font-family: 'Fraunces', serif; font-size: 2.4em;
    color: #fff; line-height: 1; font-weight: 600;
    text-shadow: 0 0 10px rgba(180,95,255,0.4);
}
.pogoda-box .temp .stopnie {
    font-size: .55em; color: #c896ff; margin-left: 2px;
}
.pogoda-box .opis {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: 1em; color: #f0e0f5; margin-top: 2px;
}
.pogoda-box .detale {
    margin-top: 10px; padding-top: 8px;
    border-top: 1px dashed rgba(180,95,255,0.2);
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-dim); letter-spacing: 1px;
}
.pogoda-box .detale .v { color: #c896ff; font-weight: 500; }
.pogoda-box .brak {
    color: var(--txt-mute); font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .9em; padding: 8px 0;
    text-align: center;
}

/* Animacja w feed — efekty pogody */
.klub-wrap.taras .feed {
    position: relative; overflow: hidden;
    background: linear-gradient(180deg, rgba(15,10,40,0.4) 0%, transparent 30%, rgba(15,10,40,0.5) 100%);
}
/* DESZCZ */
.klub-wrap.taras.efekt-rain .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .25;
    background: repeating-linear-gradient(80deg, transparent 0 8px, rgba(180,200,255,0.6) 8px 9px, transparent 9px 18px);
    animation: tarasRain 1.1s linear infinite;
}
@keyframes tarasRain {
    from { background-position: 0 0; }
    to   { background-position: -50px 80px; }
}
/* ŚNIEG */
.klub-wrap.taras.efekt-snow .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(circle at 10% 10%, white 0.5px, transparent 1px) 0 0/80px 80px,
        radial-gradient(circle at 30% 50%, white 0.5px, transparent 1px) 0 0/100px 100px,
        radial-gradient(circle at 60% 30%, white 0.5px, transparent 1px) 0 0/120px 120px;
    opacity: .7;
    animation: tarasSnow 8s linear infinite;
}
@keyframes tarasSnow {
    from { background-position: 0 0, 0 0, 0 0; }
    to   { background-position: 20px 200px, -30px 220px, 40px 240px; }
}
/* MGŁA */
.klub-wrap.taras.efekt-fog .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse at 30% 60%, rgba(255,255,255,0.12), transparent 40%),
                radial-gradient(ellipse at 70% 40%, rgba(255,255,255,0.1), transparent 40%);
    animation: tarasFog 12s ease-in-out infinite alternate;
}
@keyframes tarasFog {
    from { opacity: .4; transform: translateX(0); }
    to   { opacity: .9; transform: translateX(15px); }
}
/* BURZA */
.klub-wrap.taras.efekt-storm .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .35;
    background: repeating-linear-gradient(80deg, transparent 0 6px, rgba(150,150,255,0.7) 6px 7px, transparent 7px 14px);
    animation: tarasRain .8s linear infinite;
}
.klub-wrap.taras.efekt-storm .feed::after {
    content: ''; position: absolute; inset: 0; pointer-events: none; opacity: 0;
    background: white; animation: tarasFlash 6s infinite;
}
@keyframes tarasFlash {
    0%, 92%, 100% { opacity: 0; }
    93%, 95% { opacity: .15; }
}
/* GWIAZDY (clear noc) */
.klub-wrap.taras.efekt-clear.noc .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(2px 2px at 25% 15%, white, transparent),
        radial-gradient(1px 1px at 60% 25%, white, transparent),
        radial-gradient(1.5px 1.5px at 80% 50%, white, transparent),
        radial-gradient(1px 1px at 15% 60%, white, transparent),
        radial-gradient(2px 2px at 70% 75%, rgba(180,95,255,0.9), transparent),
        radial-gradient(1px 1px at 40% 85%, white, transparent),
        radial-gradient(1.5px 1.5px at 90% 30%, white, transparent);
    background-size: 100% 100%;
    animation: tarasStars 4s ease-in-out infinite alternate;
}
@keyframes tarasStars {
    from { opacity: .5; }
    to   { opacity: 1; }
}

/* Miasto pod stopami widget */
.miasto-box {
    padding: 12px; margin-bottom: 12px;
    background: linear-gradient(180deg, rgba(180,95,255,0.06), rgba(0,0,0,0.4));
    border: 1px solid rgba(180,95,255,0.2);
    border-radius: 2px;
}
.miasto-box .label {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: #c896ff; letter-spacing: 2.5px; text-transform: uppercase;
    margin-bottom: 6px;
}
.miasto-box .nazwa {
    font-family: 'Fraunces', serif; font-size: 1.2em;
    color: #fff; line-height: 1.1; margin-bottom: 4px;
}
.miasto-box .nazwa i { color: #c896ff; font-style: italic; }
.miasto-box .opis {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.4;
}
.miasto-box .meta {
    margin-top: 8px; padding-top: 6px;
    border-top: 1px dashed rgba(180,95,255,0.2);
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px;
    text-transform: uppercase;
    display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;
}
.miasto-box .meta b { color: #c896ff; }

/* Skyline SVG dolny pas */
.skyline {
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 60px; pointer-events: none; opacity: .35;
    background: linear-gradient(180deg, transparent, rgba(0,0,0,0.5));
    z-index: 1;
}
.skyline svg {
    position: absolute; bottom: 0; left: 0; width: 100%; height: 100%;
}

.klub-wrap.taras .msg .txt { color: #ede0f5; }
.klub-wrap.taras .aside-h {
    color: #c896ff;
    border-left-color: #c896ff;
    background: linear-gradient(90deg, rgba(180,95,255,0.08), transparent);
}
</style>

<div class="klub-wrap taras efekt-<?php echo htmlspecialchars($efekt); ?> <?php echo $jest_noc ? 'noc' : ''; ?>">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>🌃 <i>Taras</i></h2>
                <div class="sub"><?php echo (int)$lok['pietro']; ?>. piętro. Wiatr w uszach. Miasto pod stopami.</div>
                <div class="stats">
                    <span>NA TARASIE: <b><?php echo $liczba_obecnych; ?></b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <!-- Aktywne wydarzenie -->
            <?php if ($aktywny_event):
                $plakat = !empty($aktywny_event['plakat_url']) ? htmlspecialchars($aktywny_event['plakat_url']) : '';
            ?>
            <div class="event-poster-wall <?php echo $plakat ? 'has-img' : ''; ?>"
                 <?php if ($plakat): ?>style="background-image:url('<?php echo $plakat; ?>')"<?php endif; ?>
                 style="border-color:#c896ff;background:linear-gradient(135deg,rgba(180,95,255,0.1),rgba(0,0,0,0.5))">
                <div>
                    <span class="live-badge">● TRWA</span>
                    <div class="event-tytul">
                        <span class="ikona" style="color:#c896ff"><?php echo htmlspecialchars($aktywny_event['ikona_emoji']); ?></span>
                        <?php echo htmlspecialchars($aktywny_event['nazwa']); ?>
                    </div>
                </div>
            </div>
            <style>.klub-wrap.taras .event-poster-wall .live-badge{background:#c896ff;color:#000}.klub-wrap.taras .event-poster-wall .event-tytul{text-shadow:0 0 10px rgba(180,95,255,0.4),0 2px 4px rgba(0,0,0,0.8)}</style>
            <?php endif; ?>

            <!-- POGODA WIDGET -->
            <div class="aside-h">🌤 Pogoda <span style="color:var(--txt-mute);font-size:.8em;font-weight:normal;letter-spacing:0">live · <?php echo htmlspecialchars($miasto); ?></span></div>
            <div class="pogoda-box <?php echo $jest_noc ? 'noc' : ''; ?>" id="taras-pogoda-box">
                <div class="miasto-row">
                    <span class="miasto"><?php echo htmlspecialchars($miasto); ?></span>
                    <span class="pobrano"><?php echo $pogoda_pobrano ? 'aktualizacja: ' . date('H:i', strtotime($pogoda_pobrano)) : 'pobieranie...'; ?></span>
                </div>
                <?php if ($ma_dane): ?>
                <div class="glowny-row">
                    <div class="ikona-big" id="taras-ikona"><?php echo $ikona_pog; ?></div>
                    <div class="info">
                        <div class="temp" id="taras-temp"><?php echo (int)round($temp); ?><span class="stopnie">°C</span></div>
                        <div class="opis" id="taras-opis"><?php echo htmlspecialchars($opis_pog); ?></div>
                    </div>
                </div>
                <div class="detale">
                    <div>WIATR: <span class="v" id="taras-wiatr"><?php echo $wiatr !== null ? round($wiatr) . ' km/h' : '—'; ?></span></div>
                    <div>WILG: <span class="v" id="taras-wilg"><?php echo $wilg !== null ? $wilg . '%' : '—'; ?></span></div>
                </div>
                <?php else: ?>
                <div class="brak">⏳ Pobieranie pogody z <?php echo htmlspecialchars($miasto); ?>...</div>
                <?php endif; ?>
            </div>

            <!-- Miasto pod stopami -->
            <div class="aside-h">◈ Pod tobą</div>
            <div class="miasto-box">
                <div class="label">📍 Lokalizacja</div>
                <div class="nazwa"><?php echo htmlspecialchars($lok['miasto']); ?>, <i><?php echo htmlspecialchars($lok['kraj']); ?></i></div>
                <div class="opis">
                    <?php
                    // Opis miasta zależny od pogody
                    if ($efekt === 'rain') {
                        echo 'Asfalt lśni. Latarnie odbijają się w kałużach. Ulice są mokrym lustrem.';
                    } elseif ($efekt === 'snow') {
                        echo 'Świat zamilkł pod białą warstwą. Każdy krok skrzypi.';
                    } elseif ($efekt === 'fog') {
                        echo 'Mgła zatarła kontury miasta. Widać tylko najbliższe światła.';
                    } elseif ($efekt === 'storm') {
                        echo 'Niebo pęka co kilka minut. Pioruny rzucają miasto w stop-klatki.';
                    } elseif ($jest_noc) {
                        echo 'Miasto śpi w neonach. Kilka okien jeszcze się świeci.';
                    } else {
                        echo 'Miasto pulsuje. Klaksony, kroki, syreny. Życie nie zatrzymuje się tu nigdy.';
                    }
                    ?>
                </div>
                <div class="meta">
                    <span><b><?php echo (int)$lok['pietro']; ?>.</b> piętro</span>
                    <span><?php echo htmlspecialchars($lok['dzielnica']); ?></span>
                </div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood [tekst] · /flirt @ · /do [sala]
            </div>
        </div>

        <!-- ══════ KOL CENTER — CHAT ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Taras
                    </div>
                    <div class="title">🌃 <?php echo $ikona_pog; ?> <?php echo htmlspecialchars($opis_pog); ?></div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:#c896ff;font-size:.9em;">
                    <?php echo htmlspecialchars($miasto); ?> · <?php echo $temp !== null ? (int)round($temp) . '°C' : '—'; ?>
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="taras" data-last-id="<?php echo $last_id; ?>" style="position:relative">
                <!-- Skyline SVG -->
                <div class="skyline">
                    <svg viewBox="0 0 800 60" preserveAspectRatio="none">
                        <path d="M0,60 L0,40 L40,40 L40,30 L80,30 L80,38 L120,38 L120,20 L150,20 L150,15 L180,15 L180,28 L220,28 L220,35 L260,35 L260,18 L290,18 L290,42 L330,42 L330,32 L370,32 L370,22 L410,22 L410,38 L450,38 L450,28 L490,28 L490,15 L520,15 L520,8 L545,8 L545,18 L580,18 L580,30 L620,30 L620,40 L660,40 L660,25 L700,25 L700,35 L740,35 L740,20 L780,20 L780,38 L800,38 L800,60 Z"
                              fill="rgba(180,95,255,0.4)"/>
                    </svg>
                </div>

                <?php if (empty($wiadomosci)): ?>
                <div class="empty" style="position:relative;z-index:2">
                    <?php if ($jest_noc): ?>
                        Pusty taras. Wiatr i pojedyncze sygnały samochodów osiemnaście pięter niżej.
                    <?php else: ?>
                        Słońce na betonie. Nikogo. Tylko miasto huczy w dole.
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood na krawędzi')">🌃 /mood krawędź</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Opierasz się o barierkę. Miasto huczy 23 piętra niżej.*'
                        required></textarea>
                    <button type="submit" class="btn-send" style="background:linear-gradient(90deg,#5a2080,#b45fff);border-color:#c896ff">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 Na tarasie <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Tylko wiatr.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'na krawędzi';
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
                ['vip','🥂','VIP'],
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

<script>
// FAZA 5: Pobierz świeżą pogodę przy otwieraniu tarasu
// (cache w bazie ma 1h, ale jeśli ta sesja jest pierwsza po godzinie,
//  ten fetch wymusi odświeżenie)
(async function odswiezPogode() {
    try {
        const r = await fetch('api/klub_pogoda.php', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) return;

        const ikonaEl = document.getElementById('taras-ikona');
        const tempEl = document.getElementById('taras-temp');
        const opisEl = document.getElementById('taras-opis');
        const wiatrEl = document.getElementById('taras-wiatr');
        const wilgEl = document.getElementById('taras-wilg');

        if (ikonaEl && d.ikona) ikonaEl.textContent = d.ikona;
        if (tempEl && d.temperatura !== null) tempEl.innerHTML = Math.round(d.temperatura) + '<span class="stopnie">°C</span>';
        if (opisEl && d.opis) opisEl.textContent = d.opis;
        if (wiatrEl) wiatrEl.textContent = d.wiatr_kmh !== null ? Math.round(d.wiatr_kmh) + ' km/h' : '—';
        if (wilgEl) wilgEl.textContent = d.wilgotnosc !== null ? d.wilgotnosc + '%' : '—';

        // Aktualizuj klasę efektu na .klub-wrap
        const wrap = document.querySelector('.klub-wrap.taras');
        if (wrap && d.efekt) {
            wrap.className = wrap.className.replace(/efekt-\w+/g, '');
            wrap.classList.add('efekt-' + d.efekt);
            if (d.jest_noc) wrap.classList.add('noc');
            else wrap.classList.remove('noc');
        }
    } catch (e) {
        // cisza
    }
})();
</script>