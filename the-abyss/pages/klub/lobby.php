<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — LOBBY
// Dostępne zmienne: $SALE, $sala, $gracz, $id_gracza, $polaczenie, 
//                   $klub, $licznik_sal, $lacznie_w_klubie
// ══════════════════════════════════════════════════════════════════
?>
<style>
/* ── HERO LOBBY ─────────────────────────────────────────────── */
.lobby-hero {
    position: relative; padding: 28px 32px; margin-bottom: 22px;
    background: linear-gradient(135deg, rgba(255,23,68,0.14), rgba(180,95,255,0.08) 60%, rgba(10,5,10,0.45));
    border: 1px solid var(--border-mid); border-radius: 2px; overflow: hidden;
}
.lobby-hero::before {
    content: ''; position: absolute; inset: 0;
    background: repeating-linear-gradient(90deg, transparent 0 120px, rgba(255,23,68,0.04) 120px 121px);
    pointer-events: none;
}
.lobby-hero::after {
    content: ''; position: absolute; top: -40%; right: -10%; width: 60%; height: 180%;
    background: radial-gradient(ellipse at center, rgba(255,61,94,0.22), transparent 60%);
    pointer-events: none; animation: lobbyFloat 8s ease-in-out infinite;
}
@keyframes lobbyFloat { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-20px,20px)} }
.lobby-hero .eyebrow {
    font-family: 'JetBrains Mono', monospace; font-size: .74em;
    color: var(--neon-red); letter-spacing: 5px; text-transform: uppercase;
    margin-bottom: 10px; position: relative; z-index: 2;
}
.lobby-hero h1 {
    font-family: 'Oswald', sans-serif; font-weight: 300; font-size: 3.4em;
    color: #fff; line-height: .95; text-transform: uppercase;
    letter-spacing: -.5px; text-shadow: 0 0 30px rgba(255,61,94,0.4);
    position: relative; z-index: 2;
}
.lobby-hero h1 b {
    font-weight: 600; color: var(--neon-red-hot); font-style: italic;
    text-shadow: 0 0 20px var(--neon-red);
}
.lobby-hero .tag {
    margin-top: 14px; display: flex; gap: 20px; flex-wrap: wrap;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: 1.1em; color: var(--txt-dim); position: relative; z-index: 2;
}
.lobby-hero .tag b {
    color: #fff; font-style: normal; font-family: 'Oswald', sans-serif;
    font-size: .78em; letter-spacing: 2px;
}
.lobby-hero .live {
    position: absolute; top: 20px; right: 26px; z-index: 3;
    display: flex; align-items: center; gap: 8px;
    font-family: 'JetBrains Mono', monospace; font-size: .78em;
    color: var(--neon-green); letter-spacing: 2px;
}
.lobby-hero .live .dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--neon-green); box-shadow: 0 0 10px var(--neon-green);
    animation: lobbyPulse 1.2s infinite;
}
@keyframes lobbyPulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── SECTION HEADER ─────────────────────────────────────────── */
.sec-head {
    display: flex; align-items: baseline; justify-content: space-between;
    margin: 22px 0 12px; padding-bottom: 8px;
    border-bottom: 1px solid var(--border-soft); gap: 16px;
}
.sec-head h2 {
    font-family: 'Oswald', sans-serif; font-size: 1.1em;
    letter-spacing: 4px; text-transform: uppercase; color: #fff;
}
.sec-head h2 .ic { color: var(--neon-red); margin-right: 6px; }
.sec-head .meta {
    font-family: 'JetBrains Mono', monospace; font-size: .74em;
    color: var(--txt-mute); letter-spacing: 2px;
}
.sec-head .meta b { color: var(--neon-red-hot); }

/* ── MAPA KLUBU ─────────────────────────────────────────────── */
.map-wrap {
    position: relative; background: rgba(5,3,8,0.6);
    border: 1px solid var(--border-soft); padding: 16px;
    margin-bottom: 20px;
}
.map-wrap::before {
    content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .5;
    background: linear-gradient(rgba(255,23,68,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,23,68,0.05) 1px, transparent 1px);
    background-size: 40px 40px;
}
.map-legend {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 12px; padding-bottom: 8px;
    border-bottom: 1px dashed var(--border-soft);
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); letter-spacing: 2px;
    position: relative; z-index: 2; flex-wrap: wrap; gap: 10px;
}
.map-legend .addr { color: var(--neon-red); }
.map-legend .capacity { color: var(--neon-ember); }

.map-grid {
    display: grid; grid-template-columns: 1.3fr 1fr 1fr 1.1fr;
    grid-template-rows: 130px 130px 130px; gap: 9px;
    position: relative; z-index: 2;
}
@media(max-width:900px){
    .map-grid { grid-template-columns: 1fr 1fr; grid-template-rows: auto; }
    .room.bar { grid-column: span 2; grid-row: auto; }
}

.room {
    position: relative; border: 1px solid var(--border-soft);
    background: rgba(10,5,12,0.7); padding: 14px; cursor: pointer;
    transition: .25s; overflow: hidden; text-decoration: none; color: inherit;
    display: flex; flex-direction: column; justify-content: space-between;
    --accent: var(--neon-red);
}
.room::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, var(--accent), transparent 50%);
    opacity: .15; pointer-events: none; transition: .3s;
}
.room:hover {
    transform: translateY(-2px); border-color: var(--accent);
    box-shadow: 0 8px 24px rgba(0,0,0,0.5), 0 0 30px var(--accent);
    z-index: 5;
}
.room:hover::before { opacity: .35; }
.room .r-top {
    display: flex; justify-content: space-between; align-items: flex-start;
    position: relative; z-index: 2;
}
.room .r-code {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--accent); letter-spacing: 2px; opacity: .8;
}
.room .r-live {
    display: flex; align-items: center; gap: 4px;
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--neon-green); letter-spacing: 1px;
}
.room .r-live .dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--neon-green); box-shadow: 0 0 6px var(--neon-green);
}
.room .r-live.empty { color: var(--txt-mute); }
.room .r-live.empty .dot { background: var(--txt-mute); box-shadow: none; }

.room .r-name {
    position: relative; z-index: 2;
    font-family: 'Oswald', sans-serif; font-size: 1em;
    color: #fff; letter-spacing: 2px; text-transform: uppercase;
    line-height: 1.1; margin: auto 0;
}
.room .r-name .sub {
    display: block; font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .75em; letter-spacing: .5px;
    color: var(--txt-dim); text-transform: none; margin-top: 4px;
    font-weight: 400;
}
.room .r-bottom {
    display: flex; justify-content: space-between; align-items: baseline;
    position: relative; z-index: 2; font-family: 'JetBrains Mono', monospace;
    font-size: .7em; color: var(--txt-mute); letter-spacing: 1px;
}
.room .r-bottom .count { color: #fff; }
.room .r-bottom .count b {
    color: var(--accent); text-shadow: 0 0 6px var(--accent);
}
.room.placeholder { opacity: .4; cursor: not-allowed; border-style: dashed; }
.room.placeholder:hover { transform: none; box-shadow: none; }

/* Room accents */
.room.bar { --accent: var(--neon-red); grid-column: span 2; grid-row: span 2; }
.room.bar .r-name { font-size: 1.5em; }
.room.sala-balowa { --accent: var(--neon-gold); grid-column: span 2; }
.room.sauna { --accent: #a8d8ff; }
.room.basen { --accent: var(--neon-cyan); }
.room.silownia { --accent: #ff8a3d; }
.room.masaz { --accent: #c8a8ff; }
.room.bdsm { --accent: #c8325a; background: linear-gradient(135deg, rgba(60,5,15,0.8), rgba(10,5,12,0.8)); }
.room.vip { --accent: var(--neon-gold); }
.room.tyly { --accent: #ff9a3d; background: linear-gradient(135deg, rgba(20,15,8,0.8), rgba(10,5,12,0.8)); }
.room.garderoba { --accent: var(--neon-ember); }
.room.taras { --accent: #b45fff; }

.r-lock {
    position: absolute; top: 10px; right: 10px;
    width: 22px; height: 22px; border-radius: 50%;
    background: rgba(0,0,0,0.7);
    display: flex; align-items: center; justify-content: center;
    font-size: .7em; z-index: 3;
}

/* ── PLAKATY WYDARZEŃ ───────────────────────────────────────── */
.events {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 12px; margin-bottom: 20px;
}
@media(max-width:900px){ .events { grid-template-columns: 1fr 1fr; } }
.poster {
    position: relative; aspect-ratio: 3/4; padding: 18px 18px;
    background: linear-gradient(165deg, rgba(30,5,20,0.8), rgba(10,5,12,0.95));
    border: 1px solid var(--border-soft); overflow: hidden;
    cursor: pointer; transition: .25s; text-decoration: none; color: inherit;
    display: flex; flex-direction: column; justify-content: space-between;
}
.poster.pink   { background: linear-gradient(165deg, rgba(255,61,94,0.3), rgba(20,5,15,0.95) 60%); }
.poster.gold   { background: linear-gradient(165deg, rgba(255,215,0,0.2), rgba(15,10,5,0.95) 60%); }
.poster.purple { background: linear-gradient(165deg, rgba(180,95,255,0.25), rgba(15,5,25,0.95) 60%); }
.poster.red    { background: linear-gradient(165deg, rgba(255,23,68,0.4), rgba(30,0,5,0.95) 60%); }
.poster.cyan   { background: linear-gradient(165deg, rgba(74,214,255,0.25), rgba(5,15,25,0.95) 60%); }
.poster.red .p-date { color: var(--neon-red-hot); text-shadow: 0 0 6px var(--neon-red); }
.poster.cyan .p-date { color: var(--neon-cyan); text-shadow: 0 0 6px var(--neon-cyan); }
.poster.red .p-title i { color: var(--neon-red-hot); }
.poster.cyan .p-title i { color: var(--neon-cyan); }
.poster.red .p-tag { background: var(--neon-red-hot); color: #fff; }
.poster.cyan .p-tag { background: var(--neon-cyan); color: #000; }

/* FAZA 4: plakat tła */
.poster.has-img {
    background-size: cover !important;
    background-position: center center !important;
    background-repeat: no-repeat !important;
}
.poster.has-img::before {
    /* dim+gradient overlay dla czytelności tekstu */
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
        linear-gradient(180deg, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.25) 35%, rgba(0,0,0,0.85) 100%);
    z-index: 1;
}
.poster.has-img > * { position: relative; z-index: 2; }
.poster.has-img.pink   { box-shadow: inset 0 -3px 0 #ff3d5e; }
.poster.has-img.gold   { box-shadow: inset 0 -3px 0 var(--neon-gold); }
.poster.has-img.purple { box-shadow: inset 0 -3px 0 #c896ff; }
.poster.has-img.red    { box-shadow: inset 0 -3px 0 var(--neon-red-hot); }
.poster.has-img.cyan   { box-shadow: inset 0 -3px 0 var(--neon-cyan); }
.poster.has-img .p-date { text-shadow: 0 1px 4px rgba(0,0,0,0.9); }
.poster.has-img .p-title { text-shadow: 0 2px 8px rgba(0,0,0,0.95); }
.poster.has-img .p-meta { text-shadow: 0 1px 4px rgba(0,0,0,0.9); }
/* FAZA 4: plakat z obrazkiem — tło zastępowane, overlay na wierzchu */
.poster.has-img {
    background-size: cover !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
}
.poster.has-img::before {
    content: '';
    background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.3) 40%, rgba(0,0,0,0.85) 100%) !important;
    inset: 0; position: absolute; pointer-events: none;
    z-index: 1;
}
.poster.has-img > * { position: relative; z-index: 2; }
.poster.has-img .p-title {
    text-shadow: 0 2px 16px rgba(0,0,0,0.95), 0 0 8px rgba(0,0,0,0.8);
}
.poster.has-img .p-meta {
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(2px);
    border-top-color: rgba(255,255,255,0.15);
    padding: 8px 10px; margin: 0 -18px -18px -18px;
}
.poster::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: repeating-linear-gradient(45deg, transparent 0 6px, rgba(255,255,255,0.02) 6px 7px);
}
.poster:hover {
    transform: translateY(-3px); border-color: var(--neon-red-hot);
    box-shadow: 0 16px 40px rgba(0,0,0,0.6), 0 0 30px rgba(255,23,68,0.3);
}
.poster .p-date {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    letter-spacing: 3px; color: var(--neon-red-hot);
    text-shadow: 0 0 6px var(--neon-red);
    z-index: 2; position: relative; text-transform: uppercase;
}
.poster.gold .p-date { color: var(--neon-gold); text-shadow: 0 0 6px var(--neon-gold); }
.poster.purple .p-date { color: #c896ff; text-shadow: 0 0 6px #c896ff; }
.poster .p-title {
    font-family: 'Fraunces', serif; font-weight: 400; font-size: 1.8em;
    color: #fff; line-height: 1; text-shadow: 0 2px 12px rgba(0,0,0,0.8);
    z-index: 2; position: relative; letter-spacing: -.5px;
}
.poster .p-title i { font-style: italic; color: var(--neon-red-hot); display: block; }
.poster.gold .p-title i { color: var(--neon-gold); }
.poster.purple .p-title i { color: #c896ff; }
.poster .p-meta {
    font-family: 'Oswald', sans-serif; font-size: .75em; letter-spacing: 2px;
    color: var(--txt-dim); text-transform: uppercase; z-index: 2; position: relative;
    padding-top: 10px; border-top: 1px dashed var(--border-soft);
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
}
.poster .p-meta b { color: #fff; font-weight: 500; }
.poster .p-tag {
    position: absolute; top: 12px; right: -30px; transform: rotate(30deg);
    background: var(--neon-red); color: #fff; padding: 3px 40px;
    font-family: 'Oswald', sans-serif; font-size: .66em; letter-spacing: 2.5px;
    z-index: 3; text-transform: uppercase; box-shadow: 0 2px 10px rgba(0,0,0,0.5);
}
.poster.gold .p-tag { background: var(--neon-gold); color: #1a0f00; }
.poster.purple .p-tag { background: #c896ff; }

/* ── DRINK TEJ NOCY ─────────────────────────────────────────── */
.drink-night {
    display: grid; grid-template-columns: 200px 1fr; gap: 20px;
    padding: 20px 24px; margin-bottom: 20px;
    background: linear-gradient(100deg, rgba(255,23,68,0.1), rgba(10,5,12,0.7) 60%);
    border: 1px solid var(--border-mid);
    position: relative; overflow: hidden;
}
.drink-night::before {
    content: ''; position: absolute; top: -30%; right: -10%;
    width: 50%; height: 160%;
    background: radial-gradient(ellipse, rgba(255,23,68,0.2), transparent 60%);
    pointer-events: none;
}
@media(max-width:700px){ .drink-night { grid-template-columns: 1fr; text-align: center; } }
.drink-glass {
    width: 200px; height: 200px; position: relative; z-index: 2;
    margin: 0 auto;
}
.drink-glass svg {
    width: 100%; height: 100%;
    filter: drop-shadow(0 0 20px rgba(255,23,68,0.5));
}
.drink-info {
    position: relative; z-index: 2;
    display: flex; flex-direction: column; justify-content: center;
}
.drink-info .eyebrow {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--neon-red); letter-spacing: 4px; text-transform: uppercase;
    margin-bottom: 6px;
}
.drink-info h3 {
    font-family: 'Fraunces', serif; font-weight: 400; font-size: 2.2em;
    color: #fff; line-height: 1; letter-spacing: -.5px; margin-bottom: 10px;
}
.drink-info h3 i { color: var(--neon-red-hot); text-shadow: 0 0 14px rgba(255,61,94,0.6); }
.drink-info .flavor {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: 1.05em; color: var(--txt-dim); line-height: 1.5;
    margin-bottom: 10px;
}
.drink-info .ingr {
    font-family: 'JetBrains Mono', monospace; font-size: .78em;
    color: var(--neon-ember); letter-spacing: 1px;
    padding-top: 8px; border-top: 1px dashed var(--border-soft);
}
.drink-info .ingr b { color: #fff; }

/* ── REGULAMIN ─────────────────────────────────────────────── */
.klub-rules {
    margin-top: 10px; padding: 16px 20px;
    background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft);
    border-left: 3px solid var(--neon-red);
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: 1em; color: var(--txt-dim); line-height: 1.6;
}
.klub-rules b {
    color: var(--neon-red-hot); font-style: normal;
    font-family: 'Oswald', sans-serif; letter-spacing: 2px;
    font-size: .85em; text-transform: uppercase;
    display: block; margin-bottom: 6px;
}

/* Placeholder info dla FAZY 2/3 */
.soon-note {
    padding: 10px 14px; margin-bottom: 14px;
    background: rgba(255,215,0,0.05);
    border: 1px dashed rgba(255,215,0,0.3);
    font-family: 'JetBrains Mono', monospace; font-size: .76em;
    color: var(--neon-gold); letter-spacing: 1.5px;
    text-align: center; border-radius: 2px;
}
</style>

<!-- ══ HERO ═══════════════════════════════════════════════════════ -->
<div class="lobby-hero">
    <div class="live">
        <span class="dot"></span> <?php echo $lacznie_w_klubie; ?> GOŚCI · LIVE
    </div>
    <div class="eyebrow">◆ 23. PIĘTRO · MANHATTAN · DZIELNICA RED</div>
    <h1>Klub <b>The Abyss</b></h1>
    <div class="tag">
        <span><b>Godz. pracy:</b> 20:00 — do świtu</span>
        <span><b>Dress code:</b> cokolwiek, co błyszczy w ciemności</span>
        <span><b>Wiek:</b> 18+</span>
    </div>
</div>

<!-- ══ MAPA KLUBU ═════════════════════════════════════════════════ -->
<div class="sec-head">
    <h2><span class="ic">▣</span> Plan klubu</h2>
    <div class="meta">KLIKNIJ POKÓJ · <b><?php echo $lacznie_w_klubie; ?>/120</b> osób</div>
</div>

<div class="map-wrap">
    <div class="map-legend">
        <span class="addr">W. 42ND ST · 23RD FL · GATE A</span>
        <span>ŚWIATŁA: <b style="color:var(--neon-red-hot)">CZERWONE · LIVE</b></span>
        <span class="capacity">POJEMNOŚĆ: 120</span>
    </div>

    <div class="map-grid">

        <!-- Sala Główna (2x2) -->
        <?php $c = $licznik_sal['sala-glowna'] ?? 0; ?>
        <a href="game.php?page=czat&sala=sala-glowna" class="room bar">
            <div class="r-top">
                <div class="r-code">// B-01 · BAR &amp; FLOOR</div>
                <div class="r-live <?php echo $c==0?'empty':''; ?>"><span class="dot"></span> <?php echo $c; ?></div>
            </div>
            <div class="r-name">Sala Główna<span class="sub">bar, parkiet, DJ · serce klubu</span></div>
            <div class="r-bottom">
                <span>Serce klubu</span>
                <span class="count"><b><?php echo $c; ?></b>/40</span>
            </div>
        </a>

        <!-- Sala Balowa (2x1) — PLACEHOLDER -->
        <a href="game.php?page=czat&sala=sala-balowa" class="room sala-balowa placeholder">
            <div class="r-lock" title="Wkrótce">🔒</div>
            <div class="r-top">
                <div class="r-code">// B-02 · GRAND HALL</div>
                <div class="r-live empty"><span class="dot"></span> 0</div>
            </div>
            <div class="r-name">Sala Balowa<span class="sub">wkrótce — wydarzenia, gala</span></div>
            <div class="r-bottom">
                <span>Wkrótce otwarte</span>
                <span class="count">—</span>
            </div>
        </a>

        <!-- Sauna -->
        <?php $c = $licznik_sal['sauna'] ?? 0; ?>
        <a href="game.php?page=czat&sala=sauna" class="room sauna">
            <div class="r-top">
                <div class="r-code">// S-01</div>
                <div class="r-live <?php echo $c==0?'empty':''; ?>"><span class="dot"></span> <?php echo $c; ?></div>
            </div>
            <div class="r-name">Sauna<span class="sub">para, cisza, pot</span></div>
            <div class="r-bottom"><span>Otwarte</span><span class="count"><b><?php echo $c; ?></b>/8</span></div>
        </a>

        <!-- Basen — placeholder -->
        <a href="game.php?page=czat&sala=basen" class="room basen placeholder">
            <div class="r-lock" title="Wkrótce">🔒</div>
            <div class="r-top"><div class="r-code">// S-02</div><div class="r-live empty"><span class="dot"></span> 0</div></div>
            <div class="r-name">Basen<span class="sub">wkrótce</span></div>
            <div class="r-bottom"><span>Wkrótce</span><span class="count">—</span></div>
        </a>

        <!-- Siłownia — placeholder -->
        <a href="game.php?page=czat&sala=silownia" class="room silownia placeholder">
            <div class="r-lock" title="Wkrótce">🔒</div>
            <div class="r-top"><div class="r-code">// S-03</div><div class="r-live empty"><span class="dot"></span> 0</div></div>
            <div class="r-name">Siłownia<span class="sub">wkrótce</span></div>
            <div class="r-bottom"><span>Wkrótce</span><span class="count">—</span></div>
        </a>

        <!-- Masaże — placeholder -->
        <a href="game.php?page=czat&sala=masaze" class="room masaz placeholder">
            <div class="r-lock" title="Wkrótce">🔒</div>
            <div class="r-top"><div class="r-code">// P-01</div><div class="r-live empty"><span class="dot"></span> 0</div></div>
            <div class="r-name">Masaże<span class="sub">wkrótce</span></div>
            <div class="r-bottom"><span>Wkrótce</span><span class="count">—</span></div>
        </a>

        <!-- BDSM -->
        <?php $c = $licznik_sal['bdsm'] ?? 0; ?>
        <a href="game.php?page=czat&sala=bdsm" class="room bdsm">
            <div class="r-lock" title="Pokój prywatny">🔐</div>
            <div class="r-top">
                <div class="r-code">// P-02 · PRIVATE</div>
                <div class="r-live <?php echo $c==0?'empty':''; ?>"><span class="dot"></span> <?php echo $c; ?></div>
            </div>
            <div class="r-name">Pokój BDSM<span class="sub">consent · safewords · zasady</span></div>
            <div class="r-bottom"><span>Prywatny</span><span class="count"><b><?php echo $c; ?></b>/6</span></div>
        </a>

        <!-- VIP — placeholder -->
        <a href="game.php?page=czat&sala=vip" class="room vip placeholder">
            <div class="r-lock" title="Na zaproszenie">🔑</div>
            <div class="r-top"><div class="r-code">// V-01 · VIP</div><div class="r-live empty"><span class="dot"></span> 0</div></div>
            <div class="r-name">Loża VIP<span class="sub">wkrótce — na zaproszenie</span></div>
            <div class="r-bottom"><span>Wkrótce</span><span class="count">—</span></div>
        </a>

        <!-- Garderoba — placeholder -->
        <a href="game.php?page=czat&sala=garderoba" class="room garderoba placeholder">
            <div class="r-lock" title="Wkrótce">🔒</div>
            <div class="r-top"><div class="r-code">// G-01</div><div class="r-live empty"><span class="dot"></span> 0</div></div>
            <div class="r-name">Garderoba<span class="sub">wkrótce</span></div>
            <div class="r-bottom"><span>Wkrótce</span><span class="count">—</span></div>
        </a>

        <!-- Tyły -->
        <?php $c = $licznik_sal['tyly'] ?? 0; ?>
        <a href="game.php?page=czat&sala=tyly" class="room tyly">
            <div class="r-top">
                <div class="r-code">// X-01 · OUTSIDE</div>
                <div class="r-live <?php echo $c==0?'empty':''; ?>"><span class="dot"></span> <?php echo $c; ?></div>
            </div>
            <div class="r-name">Tyły klubu<span class="sub">papieros, deszcz, cień</span></div>
            <div class="r-bottom"><span>Otwarte</span><span class="count"><b><?php echo $c; ?></b>/∞</span></div>
        </a>

        <!-- Taras — placeholder -->
        <a href="game.php?page=czat&sala=taras" class="room taras placeholder">
            <div class="r-lock" title="Wkrótce">🔒</div>
            <div class="r-top"><div class="r-code">// X-02 · ROOFTOP</div><div class="r-live empty"><span class="dot"></span> 0</div></div>
            <div class="r-name">Taras<span class="sub">wkrótce — miasto pod stopami</span></div>
            <div class="r-bottom"><span>Wkrótce</span><span class="count">—</span></div>
        </a>

    </div>
</div>

<!-- ══ WYDARZENIA — DYNAMICZNE (Faza 3) ════════════════════════════ -->
<?php
// Pobierz nadchodzące wydarzenia
$eventy = [];
$qe = $polaczenie->query("
    SELECT w.*, g.login AS autor_login,
           (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id) AS liczba_rez,
           (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id AND gracz_id=$id_gracza) AS moja_rez
    FROM klub_wydarzenia w
    LEFT JOIN gracze g ON g.id = w.autor_id
    WHERE w.aktywne=1 AND w.anulowane=0
      AND w.data_startu >= NOW() - INTERVAL 4 HOUR
    ORDER BY w.data_startu ASC LIMIT 6
");
if ($qe) while ($e = $qe->fetch_assoc()) $eventy[] = $e;

$dni_pl = ['Niedz','Pon','Wt','Śr','Czw','Pt','Sob'];
?>

<div class="sec-head">
    <h2><span class="ic">◆</span> Wydarzenia najbliższych nocy</h2>
    <div class="meta">
        <?php if ($ma_uprawnienia): ?>
            <a href="game.php?page=czat&sala=lobby&widok=wydarzenia" style="color:var(--neon-gold);text-decoration:none;border-bottom:1px dashed">+ Utwórz wydarzenie</a>
        <?php else: ?>
            <a href="game.php?page=czat&sala=lobby&widok=wydarzenia" style="color:var(--txt-dim);text-decoration:none;border-bottom:1px dashed">📋 Wszystkie wydarzenia</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($eventy)): ?>
<div class="soon-note">
    ⚡ Brak zaplanowanych wydarzeń. <?php echo $ma_uprawnienia ? 'Utwórz pierwsze — kliknij "+ Utwórz wydarzenie" powyżej.' : 'Wracaj — Barman wkrótce coś ogłosi.'; ?>
</div>
<?php else: ?>
<div class="events">
    <?php foreach ($eventy as $e):
        $ts = strtotime($e['data_startu']);
        $dni_pl_idx = (int)date('w', $ts);
        $dni_pl_nazwy = ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'];
        $dni_pl_skr   = ['NIEDZ','PON','WT','ŚR','CZW','PT','SOB'];
        $miesiace_pl = ['','SYCZNIA','LUTEGO','MARCA','KWIETNIA','MAJA','CZERWCA','LIPCA','SIERPNIA','WRZEŚNIA','PAŹDZIERNIKA','LISTOPADA','GRUDNIA'];
        $miesiace_pl[1] = 'STYCZNIA';
        $data_label = $dni_pl_skr[$dni_pl_idx] . ' · ' . (int)date('j', $ts) . ' ' . $miesiace_pl[(int)date('n', $ts)] . ' · ' . date('H:i', $ts);
        $kolor = htmlspecialchars($e['kolor_plakatu'] ?: 'pink');
        $juz_zaczal = ($ts <= time());
        $tag = $juz_zaczal ? 'live now' : (((int)$e['moja_rez']>0) ? '✓ zapisany/a' : 'rezerwacje');
        $pelne = ((int)$e['liczba_rez'] >= (int)$e['max_miejsc']);
        $sale_nazwy = $SALE[$e['sala']]['nazwa'] ?? $e['sala'];
    ?>
    <a href="game.php?page=czat&sala=lobby&widok=wydarzenia&event=<?php echo (int)$e['id']; ?>" class="poster <?php echo $kolor; ?><?php echo !empty($e['plakat_url']) ? ' has-img' : ''; ?>" 
       style="text-decoration:none<?php echo !empty($e['plakat_url']) ? ';background-image:url(\'' . htmlspecialchars($e['plakat_url']) . '\')' : ''; ?>">
        <div class="p-tag"><?php echo htmlspecialchars($tag); ?></div>
        <div><div class="p-date"><?php echo htmlspecialchars($data_label); ?></div></div>
        <div><div class="p-title"><?php echo htmlspecialchars($e['ikona_emoji'] ?: '✦'); ?> <?php echo htmlspecialchars($e['nazwa']); ?></div></div>
        <div class="p-meta">
            <span><?php echo htmlspecialchars($sale_nazwy); ?></span>
            <span><b><?php echo (int)$e['liczba_rez']; ?>/<?php echo (int)$e['max_miejsc']; ?></b> <?php echo $pelne ? 'pełne' : 'miejsc'; ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
// Jeśli widok=wydarzenia, włącz pełny widok zamiast lobby
if (isset($_GET['widok']) && $_GET['widok'] === 'wydarzenia') {
    include __DIR__ . '/wydarzenia.php';
    return;
}
// Faza 6: panel moderacji plotek (tylko MG)
if (isset($_GET['widok']) && $_GET['widok'] === 'moderacja_plotek') {
    include __DIR__ . '/moderacja_plotek.php';
    return;
}
// Faza 7: panel odznak gracza
if (isset($_GET['widok']) && $_GET['widok'] === 'odznaki') {
    include __DIR__ . '/odznaki.php';
    return;
}

// Faza 7: lazy-check odznak + log dnia wizyty (tanie, raz na sesję)
if (file_exists(__DIR__ . '/../../api/klub_odznaki_helper.php')) {
    require_once __DIR__ . '/../../api/klub_odznaki_helper.php';
    klub_log_dzien_wizyty($polaczenie, $id_gracza);
    $_FAZA7_NOWE_ODZNAKI = klub_sprawdz_odznaki($polaczenie, $id_gracza);
} else {
    $_FAZA7_NOWE_ODZNAKI = [];
}
?>

<!-- ══ DRINK TEJ NOCY (statyczne Faza 2) ══════════════════════ -->
<?php
// FAZA 4: Pobierz plotki (anonim, ostatnie 12)
$plotki = [];
$pq = $polaczenie->query("
    SELECT p.id, p.tresc, p.licznik_prawda, p.licznik_falsz,
           DATE_FORMAT(p.czas,'%d.%m %H:%i') AS czas,
           (SELECT typ FROM klub_plotki_reakcje WHERE plotka_id=p.id AND gracz_id=$id_gracza LIMIT 1) AS moja_reakcja
    FROM klub_plotki p
    WHERE p.aktywna=1
    ORDER BY p.id DESC LIMIT 12
");
if ($pq) while ($p = $pq->fetch_assoc()) $plotki[] = $p;

// FAZA 4: DJ aktualny + kolejka
$dj_gra = null;
$dj_kolejka_total = 0;
$dj_q = $polaczenie->query("
    SELECT a.kolejka_id, a.tytul_recznie, a.artysta_recznie, a.dj_login, a.czas_startu,
           k.tytul AS k_tytul, k.artysta AS k_artysta, k.notka,
           gz.login AS zamawiajacy
    FROM klub_dj_aktualny a
    LEFT JOIN klub_dj_kolejka k ON k.id = a.kolejka_id
    LEFT JOIN gracze gz ON gz.id = k.gracz_id
    WHERE a.id=1
");
if ($dj_q && ($r = $dj_q->fetch_assoc())) {
    $tytul = $r['k_tytul'] ?: $r['tytul_recznie'];
    $artysta = $r['k_artysta'] ?: $r['artysta_recznie'];
    if ($tytul) {
        $dj_gra = [
            'tytul' => $tytul,
            'artysta' => $artysta,
            'notka' => $r['notka'],
            'zamawiajacy' => $r['zamawiajacy'],
            'dj_login' => $r['dj_login'],
            'gra_od' => $r['czas_startu'] ? date('H:i', strtotime($r['czas_startu'])) : null,
        ];
    }
}
$dj_cnt_q = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_dj_kolejka WHERE status='w_kolejce'");
if ($dj_cnt_q && ($r = $dj_cnt_q->fetch_assoc())) $dj_kolejka_total = (int)$r['c'];

// FAZA 4: Pobierz życzenia RP (top 10 aktywnych)
$zyczenia = [];
$zq = $polaczenie->query("
    SELECT z.id, z.tytul, z.opis, z.sala_preferowana, z.tag_klimat, z.autor_id,
           g.login AS autor_login,
           DATE_FORMAT(z.czas,'%H:%i') AS czas,
           DATE_FORMAT(z.czas,'%d.%m') AS data_short,
           (SELECT COUNT(*) FROM klub_zyczenia_odp WHERE zyczenie_id=z.id) AS liczba_odp
    FROM klub_zyczenia z
    LEFT JOIN gracze g ON g.id = z.autor_id
    WHERE z.aktywne=1 AND z.spelnione=0
    ORDER BY z.id DESC LIMIT 10
");
if ($zq) while ($z = $zq->fetch_assoc()) $zyczenia[] = $z;
?>

<style>
/* ── TABLICA PLOTEK ─────────────────────────────────────────── */
.plotki-wrap, .zyczenia-wrap {
    background: rgba(10,5,12,0.55);
    border: 1px solid var(--border-soft);
    border-radius: 2px; padding: 18px 20px;
    margin-bottom: 20px;
}
.plotki-wrap { border-left: 3px solid var(--neon-ember); }
.zyczenia-wrap { border-left: 3px solid #c896ff; }

.tablica-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
    margin-top: 10px;
}
@media(max-width:700px){ .tablica-grid { grid-template-columns: 1fr; } }

.plotka-card {
    padding: 10px 14px; background: rgba(0,0,0,0.4);
    border: 1px dashed rgba(255,122,61,0.25);
    border-radius: 2px; position: relative;
    transition: .2s;
}
.plotka-card::before {
    content: '"'; position: absolute; left: 4px; top: -8px;
    font-family: 'Fraunces', serif; font-size: 2.4em;
    color: var(--neon-ember); opacity: .4; line-height: 1;
}
.plotka-card .tresc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: 1.02em; color: #e6d8c8; line-height: 1.4;
    padding-left: 18px;
}
.plotka-card .meta {
    margin-top: 8px;
    display: flex; justify-content: space-between; align-items: center;
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1.5px;
    text-transform: uppercase;
}
.plotka-card .meta .anon { color: var(--neon-ember); }
.plotka-card .ukryj-btn {
    background: transparent; border: 1px solid var(--border-soft);
    color: var(--txt-mute); cursor: pointer;
    padding: 1px 7px; font-size: .85em;
    border-radius: 1px;
}
.plotka-card .ukryj-btn:hover { color: var(--neon-red-hot); border-color: var(--neon-red); }

.plotki-pusto, .zyczenia-pusto {
    padding: 30px 20px; text-align: center;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-mute); font-size: 1em; line-height: 1.5;
}

/* ── TABLICA ŻYCZEŃ ─────────────────────────────────────────── */
.zyczenie-card {
    padding: 12px 14px; background: rgba(0,0,0,0.4);
    border: 1px solid rgba(200,150,255,0.25);
    border-radius: 2px;
}
.zyczenie-card .head {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 6px; padding-bottom: 6px;
    border-bottom: 1px dashed rgba(200,150,255,0.15);
}
.zyczenie-card .head .av {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #2a1545, #0a0408);
    border: 1px solid #c896ff; color: #c896ff;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .75em;
    flex-shrink: 0;
}
.zyczenie-card .head .login {
    flex: 1; min-width: 0;
    font-family: 'Oswald', sans-serif; color: #fff;
    font-size: .9em; letter-spacing: 1px;
    text-decoration: none;
}
.zyczenie-card .head .login:hover { color: #c896ff; }
.zyczenie-card .head .czas {
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1.5px;
}
.zyczenie-card .tresc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: 1.05em; color: #f0e0f5; line-height: 1.45;
    margin-bottom: 8px;
}
.zyczenie-card .tresc::before { content: '"'; opacity: .5; }
.zyczenie-card .tresc::after { content: '"'; opacity: .5; }
.zyczenie-card .meta {
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; flex-wrap: wrap;
}
.zyczenie-card .meta .sala-pref {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-dim); letter-spacing: 1px;
}
.zyczenie-card .meta .sala-pref b { color: var(--neon-cyan); }
.zyczenie-card .btn-zainter {
    padding: 5px 12px;
    background: rgba(200,150,255,0.1);
    border: 1px solid #c896ff;
    color: #c896ff;
    font-family: 'Oswald', sans-serif; font-size: .72em;
    letter-spacing: 1.5px; text-transform: uppercase;
    cursor: pointer; border-radius: 2px; transition: .2s;
}
.zyczenie-card .btn-zainter:hover { background: #c896ff; color: #000; }
.zyczenie-card .btn-zainter.juz {
    background: rgba(90,255,154,0.1);
    border-color: var(--neon-green); color: var(--neon-green);
}
.zyczenie-card .btn-zainter.moje {
    background: rgba(0,0,0,0.5); border-color: var(--border-soft);
    color: var(--txt-mute); cursor: default;
}
.zyczenie-card .zainter-cnt {
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    color: var(--neon-red-hot);
}
.zyczenie-card .zainter-cnt b {
    color: #fff; font-size: 1.15em;
    text-shadow: 0 0 4px var(--neon-red);
}

/* Composer szybki dla plotki/życzenia */
.tablica-comp {
    display: flex; gap: 8px; margin-top: 14px;
    padding-top: 14px; border-top: 1px dashed var(--border-soft);
}
.tablica-comp input {
    flex: 1; padding: 8px 12px;
    background: rgba(0,0,0,0.6);
    border: 1px solid var(--border-soft);
    color: #ddd; font-family: 'Cormorant Garamond', serif;
    font-size: .98em; font-style: italic;
    border-radius: 2px;
}
.tablica-comp input:focus { border-color: var(--neon-ember); outline: none; }
.tablica-comp button {
    padding: 8px 16px;
    background: rgba(255,122,61,0.15);
    border: 1px solid var(--neon-ember);
    color: var(--neon-ember);
    font-family: 'Oswald', sans-serif; font-size: .8em;
    letter-spacing: 2px; text-transform: uppercase;
    cursor: pointer; border-radius: 2px; transition: .2s;
    white-space: nowrap;
}
.tablica-comp button:hover { background: var(--neon-ember); color: #000; }
.tablica-comp.zyczenie input:focus { border-color: #c896ff; }
.tablica-comp.zyczenie button {
    background: rgba(200,150,255,0.15);
    border-color: #c896ff; color: #c896ff;
}
.tablica-comp.zyczenie button:hover { background: #c896ff; color: #000; }

/* FAZA 4: Reakcje plotki */
.reakcja-btn {
    background: rgba(0,0,0,0.4); border: 1px solid var(--border-soft);
    color: var(--txt-dim); padding: 3px 8px;
    font-family: 'JetBrains Mono', monospace; font-size: .76em;
    cursor: pointer; border-radius: 2px; transition: .15s;
}
.reakcja-btn b { color: #fff; margin-left: 3px; }
.reakcja-btn:hover { border-color: var(--neon-ember); color: #fff; }
.reakcja-btn.prawda.aktywne { border-color: var(--neon-green); color: var(--neon-green); background: rgba(90,255,154,0.1); }
.reakcja-btn.prawda.aktywne b { color: var(--neon-green); }
.reakcja-btn.falsz.aktywne { border-color: var(--neon-red-hot); color: var(--neon-red-hot); background: rgba(255,23,68,0.1); }
.reakcja-btn.falsz.aktywne b { color: var(--neon-red-hot); }

/* FAZA 4: tytuł życzenia */
.zyczenie-card .tytul {
    font-family: 'Fraunces', serif; font-size: 1.1em;
    color: #fff; margin: 6px 0 4px; line-height: 1.2;
}

/* FAZA 4: klimat tag */
.klimat-tag {
    background: rgba(200,150,255,0.1); color: #c896ff;
    border: 1px solid rgba(200,150,255,0.3);
    padding: 2px 8px; border-radius: 2px;
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    letter-spacing: 1px; text-transform: uppercase;
}

/* FAZA 4: spelnione */
.btn-zainter.spelnione {
    background: rgba(90,255,154,0.1); border-color: var(--neon-green);
    color: var(--neon-green); padding: 4px 8px;
}
.btn-zainter.spelnione:hover { background: var(--neon-green); color: #000; }

/* FAZA 4: form toggle on */
.tablica-comp.zyczenie#zyczenie-form { display: none; }
.tablica-comp.zyczenie#zyczenie-form.on { display: flex !important; }

/* FAZA 4: Modal odpowiedzi */
.zyczenie-odp-modal {
    position: fixed; inset: 0; z-index: 1900;
    background: rgba(0,0,0,0.85); backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center;
}
.zyczenie-odp-modal .modal-box {
    width: 90%; max-width: 540px; max-height: 80vh; overflow-y: auto;
    background: linear-gradient(135deg, rgba(20,5,35,0.97), rgba(10,5,15,0.97));
    border: 1px solid #c896ff; border-radius: 2px;
    padding: 24px 28px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.8), 0 0 40px rgba(200,150,255,0.3);
}
.zyczenie-odp-modal .modal-h {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 14px; padding-bottom: 10px;
    border-bottom: 1px solid rgba(200,150,255,0.3);
}
.zyczenie-odp-modal h3 {
    font-family: 'Fraunces', serif; color: #fff; font-size: 1.3em;
    line-height: 1.1; margin: 0;
}
.zyczenie-odp-modal .x-btn {
    background: transparent; border: 1px solid var(--border-soft);
    color: var(--txt-dim); width: 28px; height: 28px; border-radius: 50%;
    font-size: .8em; cursor: pointer; line-height: 1;
}
.zyczenie-odp-modal .odp-row {
    padding: 12px; margin-bottom: 8px;
    background: rgba(0,0,0,0.4); border-left: 2px solid #c896ff;
    border-radius: 2px;
}
.zyczenie-odp-modal .odp-row .h {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 6px;
}
.zyczenie-odp-modal .odp-row .h .login {
    font-family: 'Oswald', sans-serif; color: #c896ff;
    font-size: .9em; letter-spacing: 1px;
}
.zyczenie-odp-modal .odp-row .h .czas {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute);
}
.zyczenie-odp-modal .odp-row .tresc {
    font-family: 'Cormorant Garamond', serif;
    color: #f0e8f5; font-style: italic; font-size: 1em;
    line-height: 1.5;
}
.zyczenie-odp-modal .pusto {
    color: var(--txt-mute); text-align: center;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    padding: 20px;
}

/* ════════════════════════════════════════════════════════════
   DJ NOW PLAYING (Faza 4)
   ════════════════════════════════════════════════════════════ */
.dj-now {
    display: grid; grid-template-columns: auto 1fr auto;
    gap: 16px; align-items: center;
    padding: 16px 20px; margin-bottom: 20px;
    background: linear-gradient(100deg, rgba(74,214,255,0.08), rgba(10,5,12,0.6) 50%);
    border: 1px solid rgba(74,214,255,0.3);
    border-left: 3px solid var(--neon-cyan);
    border-radius: 2px; position: relative; overflow: hidden;
}
.dj-now.empty { background: rgba(0,0,0,0.4); border-left-color: var(--border-mid); }
.dj-now::before {
    content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 40%;
    background: radial-gradient(ellipse at right, rgba(74,214,255,0.1), transparent 70%);
    pointer-events: none;
}
.dj-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, #1b4055, #050d14);
    border: 2px solid var(--neon-cyan); position: relative;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 18px rgba(74,214,255,0.4);
    flex-shrink: 0;
}
.dj-icon::after {
    content: ''; width: 14px; height: 14px; border-radius: 50%;
    background: var(--neon-cyan); box-shadow: 0 0 6px var(--neon-cyan);
    animation: djSpin 4s linear infinite;
}
.dj-now.empty .dj-icon { border-color: var(--border-mid); box-shadow: none; }
.dj-now.empty .dj-icon::after { background: var(--txt-mute); animation: none; box-shadow: none; }
@keyframes djSpin { from { transform: scale(1); } to { transform: scale(1); } }
.dj-now.live .dj-icon::before {
    content: ''; position: absolute; inset: -4px;
    border: 2px dashed var(--neon-cyan); border-radius: 50%;
    animation: djRing 6s linear infinite;
}
@keyframes djRing { to { transform: rotate(360deg); } }

.dj-info { min-width: 0; }
.dj-eyebrow {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--neon-cyan); letter-spacing: 3px;
    text-transform: uppercase; margin-bottom: 4px;
}
.dj-eyebrow .dot { color: var(--neon-cyan); animation: djBlink 1.6s infinite; }
@keyframes djBlink { 50% { opacity: .3; } }
.dj-now.empty .dj-eyebrow { color: var(--txt-mute); }
.dj-now.empty .dj-eyebrow .dot { animation: none; }

.dj-tytul {
    font-family: 'Fraunces', serif; font-size: 1.4em; line-height: 1.1;
    color: #fff; text-shadow: 0 0 10px rgba(74,214,255,0.3);
    margin-bottom: 4px;
}
.dj-tytul i { color: var(--neon-cyan); font-style: italic; }
.dj-now.empty .dj-tytul { color: var(--txt-dim); text-shadow: none; }

.dj-meta {
    display: flex; gap: 12px; flex-wrap: wrap;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .92em; color: var(--txt-dim);
}
.dj-meta b { color: #fff; font-style: normal;
    font-family: 'Oswald', sans-serif; font-size: .82em; letter-spacing: 1px;
}
.dj-meta .ic { color: var(--neon-cyan); }

.dj-akcje { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
.dj-zamow-btn {
    padding: 8px 16px;
    background: rgba(74,214,255,0.1);
    border: 1px solid var(--neon-cyan);
    color: var(--neon-cyan);
    font-family: 'Oswald', sans-serif; font-size: .8em;
    letter-spacing: 1.5px; text-transform: uppercase;
    cursor: pointer; border-radius: 2px; transition: .2s;
    text-decoration: none; white-space: nowrap;
}
.dj-zamow-btn:hover { background: var(--neon-cyan); color: #000; box-shadow: 0 0 12px rgba(74,214,255,0.5); }
.dj-cnt {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); letter-spacing: 1px;
    text-transform: uppercase; text-align: right;
}
.dj-cnt b { color: var(--neon-cyan); font-size: 1.1em; }

@media(max-width:700px){ .dj-now { grid-template-columns: 1fr; text-align: center; } .dj-akcje { align-items: center; } .dj-icon { margin: 0 auto; } }
</style>

<!-- ══ TABLICA PLOTEK ══════════════════════════════════════════ -->
<div class="sec-head">
    <h2><span class="ic">◈</span> Plotki klubowe</h2>
    <div class="meta">
        anonimowe · <b><?php echo count($plotki); ?></b> aktualnych
        <?php if ($jest_mg): ?>
            · <a href="game.php?page=czat&sala=lobby&widok=moderacja_plotek" style="color:#c896ff;text-decoration:none;border-bottom:1px dashed">🛡 Moderacja</a>
        <?php endif; ?>
    </div>
</div>

<div class="plotki-wrap">
    <?php if (empty($plotki)): ?>
        <div class="plotki-pusto">
            Tablica plotek pusta. Bądź pierwszy.<br>
            <small style="font-size:.85em">Wpisz <b>/plotka [tekst]</b> w czacie sali, lub poniżej.</small>
        </div>
    <?php else: ?>
    <div class="tablica-grid">
        <?php foreach ($plotki as $p):
            $moja_p = $p['moja_reakcja']; // null|prawda|falsz
        ?>
        <div class="plotka-card" data-id="<?php echo (int)$p['id']; ?>">
            <div class="tresc"><?php echo htmlspecialchars($p['tresc']); ?></div>
            <div class="meta">
                <span class="anon">— anonim</span>
                <span><?php echo htmlspecialchars($p['czas']); ?></span>
                <button class="reakcja-btn prawda <?php echo $moja_p === 'prawda' ? 'aktywne' : ''; ?>"
                        onclick="reagujPlotka(<?php echo (int)$p['id']; ?>, 'prawda')"
                        title="Prawda">
                    👍 <b><?php echo (int)$p['licznik_prawda']; ?></b>
                </button>
                <button class="reakcja-btn falsz <?php echo $moja_p === 'falsz' ? 'aktywne' : ''; ?>"
                        onclick="reagujPlotka(<?php echo (int)$p['id']; ?>, 'falsz')"
                        title="Fałsz">
                    👎 <b><?php echo (int)$p['licznik_falsz']; ?></b>
                </button>
                <?php if ($jest_mg): ?>
                <button class="ukryj-btn" onclick="usunPlotke(<?php echo (int)$p['id']; ?>)" title="Usuń (MG)">✕</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="tablica-comp">
        <input type="text" id="plotka-input" placeholder="Wrzuć plotkę anonimowo... (min 10 znaków, max 5/godz.)" maxlength="500">
        <button onclick="dodajPlotke()">📌 Wrzuć</button>
    </div>
</div>

<!-- ══ TABLICA ŻYCZEŃ RP ═══════════════════════════════════════ -->
<div class="sec-head">
    <h2><span class="ic">◆</span> Szukam partnera RP</h2>
    <div class="meta">aktywne · <b><?php echo count($zyczenia); ?></b> życzeń</div>
</div>

<div class="zyczenia-wrap">
    <?php if (empty($zyczenia)): ?>
        <div class="zyczenia-pusto">
            Nikt teraz nie szuka.<br>
            <small style="font-size:.85em">Kliknij „✦ Ogłoś" poniżej, żeby ogłosić kogo szukasz.</small>
        </div>
    <?php else: ?>
    <div class="tablica-grid">
        <?php foreach ($zyczenia as $z):
            $moje = ((int)$z['autor_id'] === $id_gracza);
            $autor = $z['autor_login'] ?: 'gracz';
            $inic = function_exists('klub_inicjaly') ? klub_inicjaly($autor) : strtoupper(mb_substr($autor, 0, 2));
            $sala_pref_nazwa = $z['sala_preferowana'] && isset($SALE[$z['sala_preferowana']]) ? $SALE[$z['sala_preferowana']]['nazwa'] : null;
            $klimat = $z['tag_klimat'] ?: 'inne';
            $klimat_emoji = ['romans'=>'❤️','przyjazn'=>'🤝','wspolpraca'=>'⚙️','konflikt'=>'⚔️','tajemnica'=>'🜂','inne'=>'✦'];
            $klimat_label = ['romans'=>'romans','przyjazn'=>'przyjaźń','wspolpraca'=>'współpraca','konflikt'=>'konflikt','tajemnica'=>'tajemnica','inne'=>'inne'];
        ?>
        <div class="zyczenie-card" data-id="<?php echo (int)$z['id']; ?>">
            <div class="head">
                <div class="av"><?php echo htmlspecialchars($inic); ?></div>
                <a href="game.php?page=profil&id=<?php echo (int)$z['autor_id']; ?>" class="login"><?php echo htmlspecialchars($autor); ?></a>
                <span class="czas"><?php echo htmlspecialchars($z['czas']); ?></span>
            </div>
            <div class="tytul"><?php echo htmlspecialchars($z['tytul']); ?></div>
            <?php if (!empty($z['opis'])): ?>
            <div class="tresc"><?php echo nl2br(htmlspecialchars($z['opis'])); ?></div>
            <?php endif; ?>
            <div class="meta">
                <span class="klimat-tag"><?php echo $klimat_emoji[$klimat] ?? '✦'; ?> <?php echo htmlspecialchars($klimat_label[$klimat] ?? $klimat); ?></span>
                <?php if ($sala_pref_nazwa): ?>
                <span class="sala-pref">📍 <b><?php echo htmlspecialchars($sala_pref_nazwa); ?></b></span>
                <?php endif; ?>
                <span class="zainter-cnt">💌 <b><?php echo (int)$z['liczba_odp']; ?></b></span>
                <?php if ($moje): ?>
                    <button class="btn-zainter moje" onclick="pokazOdpowiedzi(<?php echo (int)$z['id']; ?>)">📨 Pokaż odp.</button>
                    <button class="btn-zainter spelnione" onclick="oznaczSpelnione(<?php echo (int)$z['id']; ?>)" title="Oznacz jako spełnione">✓</button>
                    <button class="ukryj-btn" onclick="usunZyczenie(<?php echo (int)$z['id']; ?>)" title="Usuń">✕</button>
                <?php else: ?>
                    <button class="btn-zainter" onclick="odpowiedzZyczenie(<?php echo (int)$z['id']; ?>, '<?php echo htmlspecialchars(addslashes($z['tytul'])); ?>')">✦ Odpowiedz</button>
                <?php endif; ?>
                <?php if ($jest_mg && !$moje): ?>
                    <button class="ukryj-btn" onclick="usunZyczenie(<?php echo (int)$z['id']; ?>)" title="Usuń (MG)">✕</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <button class="tablica-comp-toggle" onclick="document.getElementById('zyczenie-form').classList.toggle('on')" style="width:100%;padding:10px;background:rgba(200,150,255,0.08);border:1px dashed rgba(200,150,255,0.4);color:#c896ff;font-family:'Oswald',sans-serif;font-size:.85em;letter-spacing:2px;text-transform:uppercase;cursor:pointer;border-radius:2px;margin-top:10px">
        ✦ Ogłoś nowe życzenie
    </button>
    <div class="tablica-comp zyczenie" id="zyczenie-form" style="display:none;flex-direction:column;gap:8px;align-items:stretch;margin-top:6px">
        <input type="text" id="zyczenie-tytul" placeholder="Tytuł: kogo szukasz? (5–120 znaków)" maxlength="120">
        <textarea id="zyczenie-opis" placeholder="Opis (opcjonalny): jaki klimat, kogo, dlaczego, gdzie..." maxlength="2000" rows="3" style="width:100%;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;padding:8px 10px;font-family:'Cormorant Garamond',serif;font-size:1em;resize:vertical;border-radius:2px;box-sizing:border-box"></textarea>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <select id="zyczenie-klimat" style="flex:1;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;padding:8px;font-family:'Open Sans',sans-serif;font-size:.85em;border-radius:2px">
                <option value="inne">✦ Klimat: dowolny</option>
                <option value="romans">❤️ Romans</option>
                <option value="przyjazn">🤝 Przyjaźń</option>
                <option value="wspolpraca">⚙️ Współpraca</option>
                <option value="konflikt">⚔️ Konflikt</option>
                <option value="tajemnica">🜂 Tajemnica</option>
            </select>
            <select id="zyczenie-sala" style="flex:1;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;padding:8px;font-family:'Open Sans',sans-serif;font-size:.85em;border-radius:2px">
                <option value="">📍 Sala: dowolna</option>
                <option value="sala-glowna">🍸 Sala Główna</option>
                <option value="sala-balowa">💃 Sala Balowa</option>
                <option value="sauna">♨ Sauna</option>
                <option value="bdsm">⛓ BDSM</option>
                <option value="tyly">🚬 Tyły</option>
                <option value="vip">🥂 VIP</option>
                <option value="taras">🌃 Taras</option>
            </select>
        </div>
        <button onclick="dodajZyczenie()" style="padding:10px;background:rgba(200,150,255,0.15);border:1px solid #c896ff;color:#c896ff;font-family:'Oswald',sans-serif;font-size:.85em;letter-spacing:2px;text-transform:uppercase;cursor:pointer;border-radius:2px">✦ Ogłoś życzenie</button>
    </div>
</div>

<script>
// FAZA 4: Plotki
window.reagujPlotka = async (id, typ) => {
    const fd = new FormData();
    fd.append('op', 'reaguj'); fd.append('id', id); fd.append('typ', typ);
    try {
        const r = await fetch('api/klub_plotki.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        location.reload();
    } catch (e) { alert('Brak połączenia'); }
};
window.usunPlotke = async (id) => {
    if (!confirm('Usunąć plotkę?')) return;
    const fd = new FormData();
    fd.append('op', 'usun'); fd.append('id', id);
    try {
        const r = await fetch('api/klub_plotki.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        location.reload();
    } catch (e) { alert('Brak połączenia'); }
};
window.dodajPlotke = async () => {
    const inp = document.getElementById('plotka-input');
    const tx = inp.value.trim();
    if (tx.length < 10) { alert('Plotka za krótka (min 10 znaków)'); return; }
    const fd = new FormData();
    fd.append('op', 'dodaj'); fd.append('tresc', tx);
    try {
        const r = await fetch('api/klub_plotki.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        inp.value = ''; location.reload();
    } catch (e) { alert('Brak połączenia'); }
};

// FAZA 4: Życzenia
window.dodajZyczenie = async () => {
    const tytul = document.getElementById('zyczenie-tytul').value.trim();
    const opis = document.getElementById('zyczenie-opis').value.trim();
    const klimat = document.getElementById('zyczenie-klimat').value;
    const sala = document.getElementById('zyczenie-sala').value;
    if (tytul.length < 5 || tytul.length > 120) { alert('Tytuł: 5–120 znaków'); return; }
    const fd = new FormData();
    fd.append('op', 'dodaj');
    fd.append('tytul', tytul);
    fd.append('opis', opis);
    fd.append('tag_klimat', klimat);
    fd.append('sala_preferowana', sala);
    try {
        const r = await fetch('api/klub_zyczenia.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        location.reload();
    } catch (e) { alert('Brak połączenia'); }
};
window.odpowiedzZyczenie = async (id, tytul) => {
    const wiad = prompt('Odpowiedz na życzenie „' + tytul + '"\nTwoja wiadomość (5–500 znaków):');
    if (!wiad || wiad.trim().length < 5) return;
    const fd = new FormData();
    fd.append('op', 'odpowiedz'); fd.append('id', id); fd.append('wiadomosc', wiad.trim());
    try {
        const r = await fetch('api/klub_zyczenia.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        alert(d.msg || '✓ Wysłane');
    } catch (e) { alert('Brak połączenia'); }
};
window.pokazOdpowiedzi = async (id) => {
    try {
        const r = await fetch('api/klub_zyczenia.php?op=odpowiedzi&id=' + id, { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }

        const modal = document.createElement('div');
        modal.className = 'zyczenie-odp-modal';
        let html = `<div class="modal-box">
            <div class="modal-h">
                <h3>📨 Odpowiedzi (${d.odpowiedzi.length})</h3>
                <button class="x-btn" onclick="this.closest('.zyczenie-odp-modal').remove()">✕</button>
            </div>`;
        if (d.odpowiedzi.length === 0) {
            html += '<div class="pusto">Nikt jeszcze nie odpowiedział.</div>';
        } else {
            for (const o of d.odpowiedzi) {
                html += `<div class="odp-row">
                    <div class="h">
                        <a href="game.php?page=profil&id=${o.od_id}" class="login">${escapeHtmlLobby(o.od_login)}</a>
                        <span class="czas">${escapeHtmlLobby(o.czas)}</span>
                    </div>
                    <div class="tresc">${escapeHtmlLobby(o.wiadomosc)}</div>
                </div>`;
            }
        }
        html += '</div>';
        modal.innerHTML = html;
        modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
        document.body.appendChild(modal);
    } catch (e) { alert('Brak połączenia'); }
};
window.oznaczSpelnione = async (id) => {
    if (!confirm('Oznaczyć życzenie jako spełnione? Zniknie z tablicy.')) return;
    const fd = new FormData();
    fd.append('op', 'spelnione'); fd.append('id', id);
    try {
        const r = await fetch('api/klub_zyczenia.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        location.reload();
    } catch (e) { alert('Brak połączenia'); }
};
window.usunZyczenie = async (id) => {
    if (!confirm('Usunąć życzenie?')) return;
    const fd = new FormData();
    fd.append('op', 'usun'); fd.append('id', id);
    try {
        const r = await fetch('api/klub_zyczenia.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) { alert(d.msg || 'Błąd'); return; }
        location.reload();
    } catch (e) { alert('Brak połączenia'); }
};

function escapeHtmlLobby(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>

<!-- ══ DJ NOW PLAYING (Faza 4) ═══════════════════════════════════ -->
<div class="sec-head">
    <h2><span class="ic">◉</span> DJ — co teraz gra</h2>
    <div class="meta">
        <a href="game.php?page=czat&sala=sala-glowna" style="color:var(--neon-cyan);text-decoration:none;border-bottom:1px dashed">🎵 zamów utwór w sali głównej</a>
    </div>
</div>

<div class="dj-now <?php echo $dj_gra ? 'live' : 'empty'; ?>">
    <div class="dj-icon"></div>
    <div class="dj-info">
        <?php if ($dj_gra): ?>
            <div class="dj-eyebrow"><span class="dot">●</span> NA WIZJI · gra od <?php echo htmlspecialchars($dj_gra['gra_od'] ?: ''); ?></div>
            <div class="dj-tytul">
                <?php echo htmlspecialchars($dj_gra['tytul']); ?>
                <?php if ($dj_gra['artysta']): ?> — <i><?php echo htmlspecialchars($dj_gra['artysta']); ?></i><?php endif; ?>
            </div>
            <div class="dj-meta">
                <?php if ($dj_gra['dj_login']): ?><span><span class="ic">🎧</span> DJ <b><?php echo htmlspecialchars($dj_gra['dj_login']); ?></b></span><?php endif; ?>
                <?php if ($dj_gra['zamawiajacy']): ?><span><span class="ic">🎵</span> dla <b><?php echo htmlspecialchars($dj_gra['zamawiajacy']); ?></b></span><?php endif; ?>
                <?php if ($dj_gra['notka']): ?><span>"<?php echo htmlspecialchars($dj_gra['notka']); ?>"</span><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="dj-eyebrow"><span class="dot">○</span> CISZA · DJ jeszcze nic nie puścił</div>
            <div class="dj-tytul" style="font-size:1.1em">Nikt teraz nie puszcza muzyki</div>
            <div class="dj-meta">
                <span>Zamów utwór komendą <b>/dj Tytuł — Artysta</b> w czacie sali głównej</span>
            </div>
        <?php endif; ?>
    </div>
    <div class="dj-akcje">
        <a href="game.php?page=czat&sala=sala-glowna" class="dj-zamow-btn">🎵 Zamów</a>
        <div class="dj-cnt">w kolejce: <b><?php echo $dj_kolejka_total; ?></b></div>
    </div>
</div>

<!-- ══ DRINK TEJ NOCY (statyczne Faza 2) ══════════════════════ -->
<div class="sec-head">
    <h2><span class="ic">◈</span> Drink tej nocy</h2>
    <div class="meta">barman rotuje co 24h · <b>faza 2</b></div>
</div>

<div class="drink-night">
    <div class="drink-glass">
        <svg viewBox="0 0 200 220">
            <defs>
                <linearGradient id="dn-liquid" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#ff3d5e" stop-opacity=".85"/>
                    <stop offset="100%" stop-color="#5a0010" stop-opacity=".9"/>
                </linearGradient>
                <linearGradient id="dn-glass" x1="0" x2="1" y1="0" y2="0">
                    <stop offset="0%" stop-color="#fff" stop-opacity=".1"/>
                    <stop offset="50%" stop-color="#fff" stop-opacity=".35"/>
                    <stop offset="100%" stop-color="#fff" stop-opacity=".1"/>
                </linearGradient>
            </defs>
            <path d="M 45 30 L 155 30 L 105 100 L 95 100 Z" fill="url(#dn-liquid)"/>
            <path d="M 30 20 L 170 20 L 100 110 L 100 170 M 70 190 L 130 190 M 100 170 L 100 190"
                  stroke="url(#dn-glass)" stroke-width="2.5" fill="none"/>
            <ellipse cx="100" cy="20" rx="70" ry="4" stroke="#fff" stroke-width="1.5" fill="none" opacity=".6"/>
            <circle cx="85" cy="45" r="4" fill="#fff" opacity=".6"/>
            <circle cx="100" cy="95" r="8" fill="#8b0020"/>
            <circle cx="97" cy="92" r="2" fill="#ff3d5e" opacity=".7"/>
        </svg>
    </div>
    <div class="drink-info">
        <div class="eyebrow">◆ POCAŁUNEK Z OTCHŁANI · dziś</div>
        <h3>Pocałunek <i>z Otchłani</i></h3>
        <div class="flavor">
            Różowy gin, granat, kropla dymu z palonego cukru. Szklanka schłodzona do granicy łez. Podawany z wisienką, której nie powinieneś jeść, jeśli chcesz wyjść stąd tym samym człowiekiem.
        </div>
        <div class="ingr">
            <b>Skład:</b> gin / likier granatowy / cytryna / prosecco / dym / amarena
        </div>
    </div>
</div>

<!-- ══ ODZNAKI (Faza 7) ═════════════════════════════════════════ -->
<?php
$moje_odznaki_lobby = [];
$liczba_odznak_gracza = 0;
$liczba_odznak_total = 0;
if (function_exists('klub_oblicz_progres_odznaki')) {
    $oq = $polaczenie->query("
        SELECT o.nazwa, o.ikona_emoji, o.rzadkosc, g.zdobyto_o
        FROM klub_gracz_odznaki g
        INNER JOIN klub_odznaki o ON o.id = g.odznaka_id
        WHERE g.gracz_id=$id_gracza AND o.aktywna=1
        ORDER BY g.zdobyto_o DESC LIMIT 6
    ");
    if ($oq) while ($r = $oq->fetch_assoc()) $moje_odznaki_lobby[] = $r;
    $cnt1 = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_gracz_odznaki WHERE gracz_id=$id_gracza")->fetch_assoc();
    $cnt2 = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_odznaki WHERE aktywna=1")->fetch_assoc();
    $liczba_odznak_gracza = $cnt1 ? (int)$cnt1['c'] : 0;
    $liczba_odznak_total = $cnt2 ? (int)$cnt2['c'] : 0;
}
?>
<div class="sec-head">
    <h2><span class="ic">🏆</span> Moje odznaki</h2>
    <div class="meta">
        <b><?php echo $liczba_odznak_gracza; ?></b> / <?php echo $liczba_odznak_total; ?>
        · <a href="game.php?page=czat&sala=lobby&widok=odznaki" style="color:var(--neon-gold);text-decoration:none;border-bottom:1px dashed">📋 Wszystkie</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-bottom:18px">
    <?php if (empty($moje_odznaki_lobby)): ?>
    <div style="grid-column:1/-1;padding:20px 14px;background:rgba(0,0,0,0.3);border:1px dashed var(--border-soft);border-radius:2px;text-align:center;color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic">
        Brak odznak. Bywaj w klubie, zamawiaj drinki, pływaj, tańcz — odznaki same przyjdą.
        <br><a href="game.php?page=czat&sala=lobby&widok=odznaki" style="color:var(--neon-gold);text-decoration:none;font-size:.85em;border-bottom:1px dashed">Zobacz dostępne odznaki →</a>
    </div>
    <?php else:
        foreach ($moje_odznaki_lobby as $o):
            $rzd_kolor = match($o['rzadkosc']) {
                'rzadka' => 'var(--neon-cyan)',
                'epicka' => '#c896ff',
                'legendarna' => 'var(--neon-gold)',
                default => 'var(--neon-green)',
            };
    ?>
    <div style="padding:10px 8px;background:linear-gradient(135deg,rgba(20,15,5,0.6),rgba(0,0,0,0.5));border:1px solid <?php echo $rzd_kolor; ?>;border-radius:2px;text-align:center" title="<?php echo htmlspecialchars($o['nazwa']); ?> · zdobyto <?php echo date('d.m.Y', strtotime($o['zdobyto_o'])); ?>">
        <div style="font-size:1.6em;line-height:1;margin-bottom:4px"><?php echo htmlspecialchars($o['ikona_emoji']); ?></div>
        <div style="font-family:'Oswald',sans-serif;font-size:.7em;letter-spacing:1px;color:#fff;text-transform:uppercase;line-height:1.1"><?php echo htmlspecialchars($o['nazwa']); ?></div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Toast dla nowych odznak (Faza 7) -->
<?php if (!empty($_FAZA7_NOWE_ODZNAKI)): ?>
<div id="odznaki-toast" style="position:fixed;top:20px;right:20px;z-index:10000;max-width:340px">
    <?php foreach ($_FAZA7_NOWE_ODZNAKI as $no): ?>
    <div style="margin-bottom:8px;padding:14px 16px;background:linear-gradient(135deg,rgba(60,40,5,0.95),rgba(0,0,0,0.95));border:1px solid var(--neon-gold);border-radius:2px;box-shadow:0 8px 32px rgba(0,0,0,0.6),0 0 24px rgba(255,215,0,0.3);animation:toastSlide .5s ease-out">
        <div style="font-family:'JetBrains Mono',monospace;font-size:.6em;color:var(--neon-gold);letter-spacing:2.5px;text-transform:uppercase;margin-bottom:4px">★ Nowa odznaka!</div>
        <div style="display:flex;align-items:center;gap:10px">
            <div style="font-size:2em"><?php echo htmlspecialchars($no['ikona']); ?></div>
            <div>
                <div style="font-family:'Fraunces',serif;font-size:1.05em;color:#fff;line-height:1.1"><?php echo htmlspecialchars($no['nazwa']); ?></div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.82em;color:var(--txt-dim);margin-top:3px"><?php echo htmlspecialchars($no['opis']); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<style>@keyframes toastSlide{from{transform:translateX(120%)}to{transform:translateX(0)}}</style>
<script>setTimeout(()=>{const t=document.getElementById('odznaki-toast');if(t)t.style.transition='opacity 1s';if(t)t.style.opacity='0';setTimeout(()=>{if(t)t.remove();},1000);},8000);</script>
<?php endif; ?>

<!-- ══ REGULAMIN ═══════════════════════════════════════════════ -->
<div class="klub-rules">
    <b>◆ Zasady The Abyss</b>
    Wszystko, co dzieje się w klubie, jest fabułą postaci — nie gracza. Consent wprost, safewords są święte, pokoje prywatne są prywatne. Barman-gracz ma prawo wyprosić. Ochroniarz Mirek widzi wszystko. <i>Jak wchodzisz w drzwi The Abyss, zostawiasz swoje imię przy progu i zabierasz je dopiero rano.</i>
</div>