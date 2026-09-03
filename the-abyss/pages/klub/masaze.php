<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — MASAŻE (Faza 6)
// 
// Klimat: olejki, świece, miękkie światło, muzyka tła.
// Specjalne widgety:
//   - Menu zabiegów z cenami (klikalne)
//   - 4 łóżka wolne/zajęte (auto-cleanup po do_kiedy)
//   - Masażystka NPC: Lila
//   - Plakat aktywnego eventu
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'masaze');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'masaze', 60);
$last_id = klub_last_id($polaczenie, 'masaze');
$aktywny_event = klub_aktywne_wydarzenie($polaczenie, 'masaze', $id_gracza);

// Cleanup wygasłych łóżek (do_kiedy <= NOW())
$polaczenie->query("UPDATE klub_masaze_lozka SET klient_id=NULL, zabieg_id=NULL, do_kiedy=NULL WHERE do_kiedy IS NOT NULL AND do_kiedy <= NOW()");

// Pobierz zabiegi
$zabiegi = [];
$zq = $polaczenie->query("SELECT * FROM klub_masaze_zabiegi WHERE aktywny=1 ORDER BY sygnatura DESC, cena ASC");
if ($zq) while ($z = $zq->fetch_assoc()) $zabiegi[] = $z;

// Pobierz łóżka
$lozka = [];
$lq = $polaczenie->query("
    SELECT l.*, g.login AS klient_login, z.nazwa AS zabieg_nazwa, z.ikona_emoji AS zabieg_ikona
    FROM klub_masaze_lozka l
    LEFT JOIN gracze g ON g.id = l.klient_id
    LEFT JOIN klub_masaze_zabiegi z ON z.id = l.zabieg_id
    ORDER BY l.numer ASC
");
if ($lq) while ($l = $lq->fetch_assoc()) $lozka[] = $l;

// Random muzyka tła per godzinę
$muzyka_lista = [
    'Dźwięki morza i wieloryby',
    'Misy tybetańskie',
    'Lo-fi piano w deszczu',
    'Indyjskie sitar i tabla',
    'Birds of paradise — natural soundscape',
    'Cisza przerywana cichym dzwonkiem wiatru',
    'Klasyczna gitara hiszpańska',
    'Dźwięk łańcucha bambusowego nad strumieniem',
];
$muzyka = $muzyka_lista[(int)date('G') % count($muzyka_lista)];
?>

<style>
.klub-wrap.masaze {
    --room-accent: #d4a574;
    --masaze-cream: #f0e0d0;
    --masaze-deep: #2a1810;
}
.klub-wrap.masaze .kol-left,
.klub-wrap.masaze .kol-right { background: rgba(20,12,5,0.6); }
.klub-wrap.masaze .kol-center {
    background: rgba(15,8,3,0.7);
    background-image:
        radial-gradient(ellipse at 30% 20%, rgba(212,165,116,0.06), transparent 60%),
        radial-gradient(ellipse at 70% 80%, rgba(212,165,116,0.04), transparent 50%);
}

.klub-wrap.masaze .room-header h2 {
    font-family: 'Fraunces', serif; font-weight: 300;
    font-size: 1.6em; letter-spacing: 2px; line-height: 1.1;
    text-transform: none; font-style: italic;
    color: var(--masaze-cream);
    text-shadow: 0 0 14px rgba(212,165,116,0.4);
}

.klub-wrap.masaze .aside-h {
    color: #d4a574;
    border-left-color: #d4a574;
    background: linear-gradient(90deg, rgba(212,165,116,0.06), transparent);
}

/* ── MENU ZABIEGÓW ───────────────────────────────────────────── */
.zabieg-item {
    padding: 8px 10px; background: rgba(20,12,5,0.5);
    border: 1px solid rgba(212,165,116,0.2); margin-bottom: 4px;
    border-radius: 2px; transition: .15s; cursor: pointer;
    display: flex; align-items: center; gap: 8px;
}
.zabieg-item:hover {
    border-color: #d4a574;
    background: rgba(212,165,116,0.08);
    transform: translateX(2px);
}
.zabieg-item.sig {
    border-color: rgba(212,165,116,0.4);
    background: linear-gradient(90deg, rgba(212,165,116,0.08), rgba(20,12,5,0.5));
}
.zabieg-item .ic { font-size: 1.2em; flex-shrink: 0; }
.zabieg-item .info { flex: 1; min-width: 0; }
.zabieg-item .nm {
    font-family: 'Fraunces', serif; font-size: .95em;
    color: var(--masaze-cream); line-height: 1.1; font-style: italic;
}
.zabieg-item .desc {
    display: block; font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .76em;
    color: var(--txt-dim); margin-top: 2px;
}
.zabieg-item .meta {
    display: flex; flex-direction: column; align-items: flex-end;
    gap: 2px; flex-shrink: 0;
}
.zabieg-item .pr {
    font-family: 'JetBrains Mono', monospace; font-size: .76em;
    color: #d4a574; letter-spacing: .5px;
}
.zabieg-item .czas {
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    color: var(--txt-mute); letter-spacing: 1px;
    text-transform: uppercase;
}

/* ── ŁÓŻKA ──────────────────────────────────────────────────── */
.lozka-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 14px;
}
.lozko-item {
    padding: 10px; background: rgba(20,12,5,0.5);
    border: 1px solid rgba(212,165,116,0.2); border-radius: 2px;
}
.lozko-item.zajete {
    background: linear-gradient(135deg, rgba(212,165,116,0.12), rgba(20,12,5,0.5));
    border-color: #d4a574;
}
.lozko-item.moje {
    background: linear-gradient(135deg, rgba(255,215,0,0.18), rgba(20,12,5,0.5));
    border-color: var(--neon-gold);
    box-shadow: 0 0 12px rgba(255,215,0,0.2);
}
.lozko-item .nm {
    font-family: 'Fraunces', serif; font-size: .95em;
    color: var(--masaze-cream); font-style: italic;
    margin-bottom: 4px;
}
.lozko-item .stat {
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--neon-green);
}
.lozko-item.zajete .stat { color: #d4a574; }
.lozko-item .klient {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .8em; color: var(--txt-dim); margin-top: 4px;
    line-height: 1.3;
}

/* ── MUZYKA TŁA ─────────────────────────────────────────────── */
.muzyka-box {
    padding: 10px 12px; background: linear-gradient(135deg, rgba(212,165,116,0.06), rgba(0,0,0,0.4));
    border: 1px solid rgba(212,165,116,0.2); border-left: 2px solid #d4a574;
    border-radius: 2px; margin-bottom: 10px;
}
.muzyka-box .label {
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    color: #d4a574; letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 4px;
    display: flex; align-items: center; gap: 6px;
}
.muzyka-box .label .pulse {
    width: 6px; height: 6px; border-radius: 50%;
    background: #d4a574; animation: muzykaPuls 2s infinite;
}
@keyframes muzykaPuls { 0%, 100% { opacity: .4; } 50% { opacity: 1; } }
.muzyka-box .nm {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .92em; color: var(--masaze-cream); line-height: 1.3;
}

/* ── MASAŻYSTKA NPC ─────────────────────────────────────────── */
.npc-lila {
    padding: 12px; margin-bottom: 12px;
    background: linear-gradient(135deg, rgba(212,165,116,0.08), rgba(0,0,0,0.5));
    border: 1px solid #d4a574; border-radius: 2px;
}
.npc-lila .head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.npc-lila .av {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--masaze-deep), #100805);
    border: 1px solid #d4a574; color: #d4a574;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; font-size: .82em; flex-shrink: 0;
}
.npc-lila .info .nm {
    font-family: 'Fraunces', serif; font-size: 1.05em;
    color: var(--masaze-cream); font-style: italic; line-height: 1;
}
.npc-lila .info .ti {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1.5px; margin-top: 3px;
    text-transform: uppercase;
}
.npc-lila .desc {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim); line-height: 1.45;
}

.klub-wrap.masaze .msg .txt { color: #ede0d0; }
.klub-wrap.masaze .feed { background: radial-gradient(ellipse at 50% 100%, rgba(212,165,116,0.04), transparent 60%); }
</style>

<div class="klub-wrap masaze">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>💆 <i>Masaże</i></h2>
                <div class="sub">Olejki, świece, jedwab. Tu czas zwalnia.</div>
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
                 style="border-color:#d4a574;background:linear-gradient(135deg,rgba(212,165,116,0.1),rgba(0,0,0,0.5))">
                <div>
                    <span class="live-badge" style="background:#d4a574;color:#000">● TRWA</span>
                    <div class="event-tytul">
                        <span class="ikona" style="color:#d4a574"><?php echo htmlspecialchars($aktywny_event['ikona_emoji']); ?></span>
                        <?php echo htmlspecialchars($aktywny_event['nazwa']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Muzyka tła -->
            <div class="muzyka-box">
                <div class="label"><span class="pulse"></span>♪ Muzyka tła</div>
                <div class="nm"><?php echo htmlspecialchars($muzyka); ?></div>
            </div>

            <!-- Menu zabiegów -->
            <div class="aside-h">💆 Karta zabiegów</div>
            <?php foreach ($zabiegi as $z): ?>
            <div class="zabieg-item <?php echo $z['sygnatura'] ? 'sig' : ''; ?>"
                 onclick="masazeZamowDialog(<?php echo (int)$z['id']; ?>, '<?php echo addslashes($z['nazwa']); ?>', <?php echo (int)$z['cena']; ?>, <?php echo (int)$z['czas_min']; ?>)"
                 title="Zamów zabieg (pobierze <?php echo (int)$z['cena']; ?> $)">
                <span class="ic"><?php echo htmlspecialchars($z['ikona_emoji']); ?></span>
                <div class="info">
                    <div class="nm"><?php echo htmlspecialchars($z['nazwa']); ?></div>
                    <span class="desc"><?php echo htmlspecialchars($z['opis']); ?></span>
                </div>
                <div class="meta">
                    <span class="pr"><?php echo (int)$z['cena']; ?> $</span>
                    <span class="czas"><?php echo (int)$z['czas_min']; ?> min</span>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Łóżka -->
            <div class="aside-h" style="margin-top:14px">🛏 Łóżka</div>
            <div class="lozka-grid">
                <?php foreach ($lozka as $l):
                    $z = !empty($l['klient_id']);
                    $moje = $z && (int)$l['klient_id'] === $id_gracza;
                    $do_kiedy = ($l['do_kiedy'] ?? null) ? date('H:i', strtotime($l['do_kiedy'])) : null;
                ?>
                <div class="lozko-item <?php echo $z ? 'zajete' : ''; ?> <?php echo $moje ? 'moje' : ''; ?>">
                    <div class="nm"><?php echo htmlspecialchars($l['nazwa']); ?></div>
                    <div class="stat"><?php echo $z ? '● zajęte' : '○ wolne'; ?></div>
                    <?php if ($z): ?>
                    <div class="klient">
                        <?php echo htmlspecialchars($l['klient_login']); ?>
                        <?php if ($do_kiedy): ?> · do <?php echo $do_kiedy; ?><?php endif; ?>
                        <br>
                        <?php if ($l['zabieg_nazwa']): ?>
                        <span style="color:#d4a574"><?php echo htmlspecialchars($l['zabieg_ikona']); ?> <?php echo htmlspecialchars($l['zabieg_nazwa']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($moje): ?>
                    <button onclick="masazeAnuluj(<?php echo (int)$l['id']; ?>)" style="margin-top:6px;width:100%;padding:4px;background:transparent;border:1px solid var(--border-soft);color:var(--txt-mute);font-family:'Oswald',sans-serif;font-size:.62em;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border-radius:2px">✕ Przerwij</button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Masażystka NPC -->
            <div class="aside-h">◈ Masażystka</div>
            <div class="npc-lila">
                <div class="head">
                    <div class="av">LL</div>
                    <div class="info">
                        <div class="nm"><i>Lila</i></div>
                        <div class="ti">MASAŻYSTKA · NPC · DOTYK</div>
                    </div>
                </div>
                <div class="desc">
                    Spokojna, biały kostium z lnu, włosy zawsze upięte. Mówi szeptem, ruchy ma odmierzone i pewne. Zna każdy kręgosłup, każde napięcie. Olejek pachnie cytryną i drzewem sandałowym.
                </div>
            </div>

            <!-- Komendy -->
            <div class="aside-h">📎 Komendy</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);line-height:1.8;letter-spacing:.5px">
                /mood zabieg [nazwa] · /flirt @ · /do
            </div>
        </div>

        <!-- ══════ KOL CENTER ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Masaże
                    </div>
                    <div class="title" style="font-family:'Fraunces',serif;font-style:italic;letter-spacing:1px;color:var(--masaze-cream)">💆 Cisza i olejki</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:#d4a574;font-size:.9em;">
                    <?php echo count(array_filter($lozka, fn($l) => empty($l['klient_id']))); ?> wolnych łóżek
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="masaze" data-last-id="<?php echo $last_id; ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Świece migoczą. Pachnie cytryną i sandałem.<br>
                    Lila gdzieś za parawanem ściera olej z dłoni.<br>
                    Zaczynasz od pytania, czy od leżenia?
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc prim" onclick="window.klubInsertCmd('/mood na zabiegu')">💆 /mood zabieg</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood odpoczywam')">🕯 odpoczywam</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Kładziesz się na białym ręczniku. Lila pyta cicho, jaką masz dziś preferencję ucisku.*'
                        required></textarea>
                    <button type="submit" class="btn-send" style="background:linear-gradient(90deg,#7a5a3a,#d4a574);border-color:#d4a574;color:#000;font-weight:500">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 W gabinecie <span style="color:var(--neon-green);font-size:.9em">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
            <?php if (empty($obecni)): ?>
                <div style="color:var(--txt-mute);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.9em;padding:20px 4px;text-align:center;">
                    Cisza.<br>Tylko Lila i świece.
                </div>
            <?php else: ?>
                <?php foreach ($obecni as $o):
                    $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                    $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                    $inic = klub_inicjaly($o['login']);
                    $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : 'na zabiegu';
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
                ['basen','🏊','Basen'],
                ['silownia','💪','Siłownia'],
                ['garderoba','👗','Garderoba'],
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
// Wolne łóżka — z PHP
const WOLNE_LOZKA = <?php
$wolne = array_values(array_filter($lozka, fn($l) => empty($l['klient_id'])));
$out = array_map(fn($l) => ['id' => (int)$l['id'], 'nazwa' => $l['nazwa']], $wolne);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

window.masazeZamowDialog = function(zabieg_id, nazwa, cena, czas_min) {
    if (!WOLNE_LOZKA.length) {
        alert('Brak wolnych łóżek. Poczekaj aż któreś się zwolni.');
        return;
    }

    // Modal
    const m = document.createElement('div');
    m.id = 'masaze-modal';
    m.style.cssText = 'position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:20px';

    let lozka_html = '';
    for (const l of WOLNE_LOZKA) {
        lozka_html += `
            <button onclick="masazeWybierzLozko(${zabieg_id}, ${l.id}, '${cena}')" style="padding:12px 16px;background:linear-gradient(90deg,rgba(212,165,116,0.1),rgba(20,12,5,0.5));border:1px solid #d4a574;color:#fff;font-family:'Fraunces',serif;font-size:1em;font-style:italic;cursor:pointer;border-radius:2px;text-align:left;transition:.15s" onmouseover="this.style.background='rgba(212,165,116,0.25)'" onmouseout="this.style.background='linear-gradient(90deg,rgba(212,165,116,0.1),rgba(20,12,5,0.5))'">${escapeAttr(l.nazwa)}</button>`;
    }

    m.innerHTML = `
        <div style="max-width:460px;width:100%;background:linear-gradient(135deg,rgba(40,24,16,0.97),rgba(15,8,3,0.97));border:1px solid #d4a574;border-radius:2px;padding:24px 28px;box-shadow:0 20px 60px rgba(0,0,0,0.8),0 0 40px rgba(212,165,116,0.15)">
            <div style="font-family:'JetBrains Mono',monospace;font-size:.7em;color:#d4a574;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">Wybierz łóżko dla zabiegu</div>
            <div style="font-family:'Fraunces',serif;font-size:1.4em;font-style:italic;color:#fff;margin-bottom:6px">${escapeAttr(nazwa)}</div>
            <div style="font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.95em;color:var(--txt-dim);margin-bottom:18px">
                <b style="color:#d4a574">${cena} $</b> · ${czas_min} min<br>
                Cena pobrana natychmiast z gotówki. Łóżko zajęte na czas zabiegu.
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">${lozka_html}</div>
            <button onclick="document.getElementById('masaze-modal').remove()" style="margin-top:14px;width:100%;padding:8px;background:transparent;border:1px solid var(--border-soft);color:var(--txt-dim);font-family:'Oswald',sans-serif;font-size:.78em;letter-spacing:2px;text-transform:uppercase;cursor:pointer;border-radius:2px">Anuluj</button>
        </div>`;
    document.body.appendChild(m);
    m.addEventListener('click', (e) => { if (e.target === m) m.remove(); });
};

window.masazeWybierzLozko = async function(zabieg_id, lozko_id, cena) {
    if (!confirm('Pobrać ' + cena + ' $ za zabieg?')) return;
    const fd = new FormData();
    fd.append('op','zamow'); fd.append('zabieg_id', zabieg_id); fd.append('lozko_id', lozko_id);
    try {
        const r = await fetch('api/klub_masaze.php', {method:'POST',body:fd,credentials:'same-origin'});
        const d = await r.json();
        if (d.ok) {
            let msg = d.msg + '\n\nDo kiedy: ' + d.do_kiedy + '\nGotówka: ' + d.nowa_gotowka + ' $';
            if (d.nowe_odznaki && d.nowe_odznaki.length) msg += '\n\n🏆 Nowa odznaka: ' + d.nowe_odznaki.map(o=>o.nazwa).join(', ');
            alert(msg);
            location.reload();
        } else alert(d.msg || 'Błąd');
    } catch(e) { alert('Brak połączenia'); }
};

window.masazeAnuluj = async function(lozko_id) {
    if (!confirm('Przerwać zabieg? Pieniądze nie wracają.')) return;
    const fd = new FormData();
    fd.append('op','anuluj'); fd.append('lozko_id', lozko_id);
    try {
        const r = await fetch('api/klub_masaze.php', {method:'POST',body:fd,credentials:'same-origin'});
        const d = await r.json();
        if (d.ok) location.reload();
        else alert(d.msg || 'Błąd');
    } catch(e) { alert('Brak połączenia'); }
};

function escapeAttr(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>