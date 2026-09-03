<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — LOŻA VIP (Faza 5)
// 
// Klimat: złoto, lustra, dyskrecja. Wstęp 500$/noc.
// Specjalne widgety:
//   - Licznik czasu do wygaśnięcia VIP (jak długo zostało)
//   - Premium drinki (kategoria 'vip' z bazy)
//   - Kelner NPC: Maximilian
//   - Plakat eventu w VIP (jeśli aktywny)
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'vip');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'vip', 60);
$last_id = klub_last_id($polaczenie, 'vip');

// Aktywne wydarzenie w VIP
$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'vip', $id_gracza);

// Czas do wygaśnięcia VIP
$moja_zaplata = null;
$czas_zostalo = null;
if (!$ma_uprawnienia) {
    $z = $polaczenie->query("
        SELECT id, waznosc_do, kwota, czas_zaplaty
        FROM klub_vip_zaplaty
        WHERE gracz_id=$id_gracza AND aktywny=1 AND waznosc_do > NOW()
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    if ($z) {
        $moja_zaplata = $z;
        $sek = strtotime($z['waznosc_do']) - time();
        $czas_zostalo = max(0, $sek);
    }
}

// Premium drinki
$drinki_vip = [];
$dq = $polaczenie->query("
    SELECT id, nazwa, opis, cena, sygnatura, ikona_emoji
    FROM klub_drinki
    WHERE aktywny=1 AND kategoria='vip'
    ORDER BY sygnatura DESC, cena DESC
");
if ($dq) while ($d = $dq->fetch_assoc()) $drinki_vip[] = $d;
?>

<style>
.klub-wrap.vip {
    --room-accent: var(--neon-gold);
    --vip-deep: #2a1f00;
}
.klub-wrap.vip .kol-left,
.klub-wrap.vip .kol-right { background: rgba(15,12,3,0.55); }
.klub-wrap.vip .kol-center {
    background: rgba(8,5,2,0.6);
    background-image:
        radial-gradient(ellipse at top, rgba(255,215,0,0.06), transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(255,215,0,0.04), transparent 60%);
}

.klub-wrap.vip .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 400;
    font-size: 1.7em; letter-spacing: 1px; line-height: 1.1;
    text-transform: none;
    background: linear-gradient(90deg, #fff, var(--neon-gold), #fff);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: none;
    filter: drop-shadow(0 0 12px rgba(255,215,0,0.4));
}
.klub-wrap.vip .room-header h2 i {
    color: var(--neon-gold); font-style: italic;
    -webkit-text-fill-color: var(--neon-gold);
}

/* Licznik VIP */
.vip-licznik {
    padding: 14px; margin-bottom: 14px;
    background: linear-gradient(135deg, rgba(255,215,0,0.12), rgba(0,0,0,0.5));
    border: 1px solid var(--neon-gold);
    border-radius: 2px; text-align: center;
    box-shadow: inset 0 0 20px rgba(255,215,0,0.08);
}
.vip-licznik.uprawniony { background: linear-gradient(135deg, rgba(255,215,0,0.18), rgba(0,0,0,0.5)); }
.vip-licznik .label {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-gold); letter-spacing: 3px;
    text-transform: uppercase; margin-bottom: 6px;
}
.vip-licznik .czas {
    font-family: 'Fraunces', serif; font-size: 1.4em;
    color: #fff; line-height: 1; font-weight: 600;
    text-shadow: 0 0 12px rgba(255,215,0,0.4);
}
.vip-licznik .pod {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .85em; color: var(--txt-dim); margin-top: 4px;
}

/* Premium drinki VIP */
.drink-vip {
    padding: 10px 12px; background: linear-gradient(90deg, rgba(255,215,0,0.06), rgba(0,0,0,0.4));
    border: 1px solid rgba(255,215,0,0.3); margin-bottom: 6px;
    transition: .15s; display: flex; justify-content: space-between;
    align-items: center; gap: 8px; border-radius: 2px;
    cursor: pointer;
}
.drink-vip:hover {
    border-color: var(--neon-gold);
    background: linear-gradient(90deg, rgba(255,215,0,0.15), rgba(0,0,0,0.4));
    box-shadow: 0 0 12px rgba(255,215,0,0.25);
}
.drink-vip .ikona { font-size: 1.3em; flex-shrink: 0; }
.drink-vip .info { flex: 1; min-width: 0; }
.drink-vip .nm {
    font-family: 'Fraunces', serif; font-size: .98em;
    color: #fff; line-height: 1.1;
}
.drink-vip .desc {
    display: block; font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .78em; color: var(--txt-dim);
    margin-top: 2px;
}
.drink-vip .pr {
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    color: var(--neon-gold); letter-spacing: 1px; white-space: nowrap;
    text-shadow: 0 0 4px rgba(255,215,0,0.3); font-weight: 500;
}

/* Kelner Maximilian */
.npc-card-max {
    padding: 12px; background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(0,0,0,0.5));
    border: 1px solid var(--neon-gold); border-radius: 2px;
    margin-bottom: 10px;
}
.npc-card-max .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-card-max .av {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #5a4500, #1a1000);
    border: 1px solid var(--neon-gold); color: var(--neon-gold);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .82em; flex-shrink: 0;
}
.npc-card-max .info { flex: 1; min-width: 0; font-family: 'Fraunces', serif; color: #fff; }
.npc-card-max .info .nm { font-size: 1.05em; line-height: 1; }
.npc-card-max .info .nm i { color: var(--neon-gold); font-style: italic; }
.npc-card-max .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px; margin-top: 3px;
    text-transform: uppercase;
}
.npc-card-max .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

/* Lustra animacja */
.klub-wrap.vip .feed::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
        linear-gradient(90deg, transparent 0%, rgba(255,215,0,0.04) 25%, transparent 50%, rgba(255,215,0,0.04) 75%, transparent 100%);
    animation: vipShine 8s ease-in-out infinite;
}
@keyframes vipShine {
    0%, 100% { opacity: .3; transform: translateX(-20%); }
    50% { opacity: .8; transform: translateX(20%); }
}

.klub-wrap.vip .msg .txt { color: #f5ecd6; }

/* Aside header VIP gold */
.klub-wrap.vip .aside-h {
    color: var(--neon-gold);
    border-left-color: var(--neon-gold);
    background: linear-gradient(90deg, rgba(255,215,0,0.1), transparent);
}

/* Plakat eventu VIP (mały) */
.event-vip-mini {
    padding: 10px 12px; margin-bottom: 12px;
    background: linear-gradient(135deg, rgba(180,95,255,0.1), rgba(0,0,0,0.5));
    border: 1px solid #c896ff; border-radius: 2px;
    position: relative; overflow: hidden;
}
.event-vip-mini.has-img {
    background-size: cover; background-position: center;
    min-height: 100px;
}
.event-vip-mini.has-img::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.85) 100%);
    pointer-events: none;
}
.event-vip-mini > * { position: relative; z-index: 2; }
.event-vip-mini .lab {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: #c896ff; letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 4px;
}
.event-vip-mini .nm {
    font-family: 'Fraunces', serif; color: #fff;
    font-size: 1.05em; line-height: 1.15;
    text-shadow: 0 1px 4px rgba(0,0,0,0.8);
}
</style>

<div class="klub-wrap vip">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>🥂 Loża <i>VIP</i></h2>
                <div class="sub">Złote lustra. Smokingi. Szampan w wiaderkach z lodu.</div>
                <div class="stats">
                    <span>GOŚCI: <b><?php echo $liczba_obecnych; ?>/16</b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <!-- Licznik VIP -->
            <?php if ($ma_uprawnienia): ?>
            <div class="vip-licznik uprawniony">
                <div class="label">★ Stały dostęp</div>
                <div class="czas">Bezterminowo</div>
                <div class="pod"><?php echo $jest_mg ? 'MG ma wstęp wszędzie' : 'Barman zna wszystkie drzwi'; ?></div>
            </div>
            <?php elseif ($moja_zaplata):
                $h = floor($czas_zostalo / 3600);
                $m = floor(($czas_zostalo % 3600) / 60);
                $do_kiedy = date('H:i', strtotime($moja_zaplata['waznosc_do']));
            ?>
            <div class="vip-licznik">
                <div class="label">★ Twój dostęp</div>
                <div class="czas"><?php echo $h; ?>h <?php echo str_pad((int)$m, 2, '0', STR_PAD_LEFT); ?>min</div>
                <div class="pod">Do godziny <b style="color:var(--neon-gold)"><?php echo $do_kiedy; ?></b></div>
            </div>
            <?php endif; ?>

            <!-- Aktywne wydarzenie -->
            <?php if ($aktywny_event):
                $plakat = !empty($aktywny_event['plakat_url']) ? htmlspecialchars($aktywny_event['plakat_url']) : '';
            ?>
            <div class="event-vip-mini <?php echo $plakat ? 'has-img' : ''; ?>"
                 <?php if ($plakat): ?>style="background-image:url('<?php echo $plakat; ?>')"<?php endif; ?>>
                <div class="lab">● Wydarzenie trwa</div>
                <div class="nm"><?php echo htmlspecialchars($aktywny_event['ikona_emoji']); ?> <?php echo htmlspecialchars($aktywny_event['nazwa']); ?></div>
            </div>
            <?php endif; ?>

            <!-- Premium drinki -->
            <div class="aside-h">🥂 Karta premium</div>
            <?php foreach ($drinki_vip as $d): ?>
            <div class="drink-vip" onclick="window.klubZamow('<?php echo addslashes($d['nazwa']); ?>')" title="Zamów u kelnera">
                <div class="ikona"><?php echo htmlspecialchars($d['ikona_emoji']); ?></div>
                <div class="info">
                    <div class="nm"><?php echo htmlspecialchars($d['nazwa']); ?>
                        <span class="desc"><?php echo htmlspecialchars($d['opis']); ?></span>
                    </div>
                </div>
                <div class="pr"><?php echo (int)$d['cena']; ?> $</div>
            </div>
            <?php endforeach; ?>

            <!-- Kelner NPC -->
            <div class="aside-h">◈ Kelner Loży</div>
            <div class="npc-card-max">
                <div class="head">
                    <div class="av">MX</div>
                    <div class="info">
                        <div class="nm"><i>Maximilian</i></div>
                        <div class="ti">SOMMELIER · NPC · DYSKRECJA</div>
                    </div>
                </div>
                <div class="desc">
                    Smoking idealnie skrojony, biała koszula, czarne jedwabne spinki. Pamięta każdy drink, każde imię, żadnej rozmowy. Nigdy nie patrzy w oczy podczas serwowania.
                </div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /bar zamów [drink] · /napiwek · /flirt @ · /mood · /do
            </div>
        </div>

        <!-- ══════ KOL CENTER — CHAT ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Loża VIP
                    </div>
                    <div class="title" style="background:linear-gradient(90deg,#fff,var(--neon-gold),#fff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">🥂 Złoto i dyskrecja</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--neon-gold);font-size:.9em;">
                    Maximilian czeka na zamówienia
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="vip" data-last-id="<?php echo $last_id; ?>" style="position:relative;overflow:hidden">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Cisza. Tylko cykanie zegara w narożniku i pęknięcie lodu w wiaderku.<br>
                    Kelner czeka, pochylony lekko nad tacą.
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc prim" onclick="window.klubInsertCmd('/bar zamów Krew Bogini')">🥂 Krew Bogini</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/bar zamów ')">/bar zamów</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/napiwek @')">/napiwek</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Siadasz na obitej skórą kanapie. Maximilian podsuwa Ci kartę bez słowa.*'
                        required></textarea>
                    <button type="submit" class="btn-send" style="background:linear-gradient(90deg,#7a5e0a,#c9a961);color:#000;border-color:var(--neon-gold);box-shadow:0 0 14px rgba(255,215,0,0.3)">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 W loży <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Pusto.<br>Tylko Maximilian.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'w loży';
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