<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — SALA GŁÓWNA (Faza 2)
// Drinki z bazy (klikalne), rachunek live, napiwki, AJAX
// ══════════════════════════════════════════════════════════════════

$obecni = klub_obecni_w_sali($polaczenie, 'sala-glowna');
$liczba_obecnych = count($obecni);
$wiadomosci = klub_wiadomosci($polaczenie, 'sala-glowna', 60);
$last_id = klub_last_id($polaczenie, 'sala-glowna');

// Czy jest aktywny barman-gracz?
$live_barman = false;
$barman_login = null;
foreach ($obecni as $o) if ($o['is_barman']) { $live_barman = true; $barman_login = $o['login']; break; }

// Pobierz drinki z bazy (Faza 2 — zamiast hardcode)
$drinki = [];
$dq = $polaczenie->query("SELECT id, nazwa, opis, cena, sygnatura, ikona_emoji FROM klub_drinki WHERE aktywny=1 ORDER BY sygnatura DESC, kategoria, cena ASC LIMIT 20");
if ($dq) while ($d = $dq->fetch_assoc()) $drinki[] = $d;

// Aktualny rachunek gracza
$moj_rachunek = $polaczenie->query("
    SELECT id, nazwa_drink, cena, DATE_FORMAT(czas,'%H:%i') AS czas
    FROM klub_rachunki 
    WHERE gracz_id=$id_gracza AND oplacony=0 
    ORDER BY id DESC
");
$pozycje = [];
$razem = 0;
if ($moj_rachunek) {
    while ($r = $moj_rachunek->fetch_assoc()) {
        $pozycje[] = $r;
        $razem += (int)$r['cena'];
    }
}

// Statystyki barmana (jeśli to barman)
$staty_barmana = null;
if ($jest_barmanem) {
    $staty_barmana = $polaczenie->query("SELECT klub_napiwki_zarobione, klub_drinki_podane FROM gracze WHERE id=$id_gracza")->fetch_assoc();
}

// FAZA 3: Ostatnie sygnały flirtu otrzymane
$sygnaly = [];
$sq = $polaczenie->query("
    SELECT f.id, f.czas, f.sala, g.login AS od_login
    FROM klub_flirty f
    LEFT JOIN gracze g ON g.id = f.od_gracza_id
    WHERE f.do_gracza_id=$id_gracza
      AND f.czas >= NOW() - INTERVAL 24 HOUR
    ORDER BY f.id DESC LIMIT 5
");
if ($sq) while ($s = $sq->fetch_assoc()) {
    $sygnaly[] = [
        'od_login' => $s['od_login'] ?: '???',
        'sala' => $s['sala'],
        'czas' => date('H:i', strtotime($s['czas'])),
    ];
}

// FAZA 4: DJ aktualny + kolejka (pełniejsza niż w lobby — z ID dla DJ-a)
$dj_gra = null;
$dj_kolejka = [];
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
$kq = $polaczenie->query("
    SELECT k.id, k.tytul, k.artysta, k.notka, k.gracz_id,
           g.login AS zamawiajacy
    FROM klub_dj_kolejka k
    LEFT JOIN gracze g ON g.id = k.gracz_id
    WHERE k.status='w_kolejce'
    ORDER BY k.id ASC LIMIT 8
");
if ($kq) while ($r = $kq->fetch_assoc()) {
    $dj_kolejka[] = [
        'id' => (int)$r['id'],
        'tytul' => $r['tytul'],
        'artysta' => $r['artysta'],
        'notka' => $r['notka'],
        'zamawiajacy' => $r['zamawiajacy'] ?: '???',
        'czy_moje' => ((int)$r['gracz_id'] === $id_gracza),
    ];
}
?>

<style>
.klub-wrap.sala-glowna { --room-accent: var(--neon-red); }

/* ── DRINK MENU (klikalne) ─────────────────────────────────── */
.drink-menu-item {
    padding: 8px 10px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); margin-bottom: 5px;
    transition: .15s; display: flex; justify-content: space-between;
    align-items: center; gap: 8px; border-radius: 2px;
    cursor: pointer; user-select: none;
}
.drink-menu-item:hover { border-color: var(--room-accent); background: rgba(255,23,68,0.08); transform: translateX(2px); }
.drink-menu-item:active { transform: translateX(0); }
.drink-menu-item .ikona { font-size: 1.2em; flex-shrink: 0; }
.drink-menu-item .info { flex: 1; min-width: 0; }
.drink-menu-item .nm {
    font-family: 'Fraunces', serif; font-size: .94em;
    color: #fff; line-height: 1.1;
}
.drink-menu-item .desc {
    display: block; font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .78em; color: var(--txt-dim);
    margin-top: 2px; line-height: 1.25;
}
.drink-menu-item .pr {
    font-family: 'JetBrains Mono', monospace; font-size: .76em;
    color: var(--neon-ember); letter-spacing: 1px; white-space: nowrap;
    font-weight: 500;
}
.drink-menu-item.sig {
    border-color: var(--border-mid);
    background: linear-gradient(90deg, rgba(255,23,68,0.06), rgba(0,0,0,0.4));
}
.drink-menu-item.sig .pr { color: var(--neon-red-hot); }
.drink-menu-item.sig::after {
    content: '◆'; position: absolute; color: var(--neon-red-hot);
    font-size: .7em; opacity: .7;
}

/* ── KOMENDY HINTY ────────────────────────────────────────── */
.cmd-info {
    padding: 6px 10px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); border-radius: 2px;
    margin-bottom: 4px; font-family: 'JetBrains Mono', monospace;
    color: var(--neon-green); font-size: .72em;
    display: flex; justify-content: space-between; align-items: center;
}
.cmd-info small {
    color: var(--txt-mute); font-family: 'Open Sans', sans-serif;
    font-size: .88em; font-style: italic;
}

/* ── STATY BARMANA ─────────────────────────────────────────── */
.staty-barmana {
    background: rgba(255,215,0,0.06);
    border: 1px solid rgba(255,215,0,0.3);
    border-radius: 2px; padding: 10px 12px; margin-bottom: 12px;
}
.staty-barmana .hd {
    font-family: 'Oswald', sans-serif; color: var(--neon-gold);
    font-size: .72em; letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 6px;
}
.staty-barmana .grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    font-family: 'JetBrains Mono', monospace; font-size: .78em;
}
.staty-barmana .grid div { color: var(--txt-dim); }
.staty-barmana .grid b { color: var(--neon-gold); display: block; font-size: 1.3em; line-height: 1; margin-top: 2px; }

/* ── HOVER ACTIONS GUEST (napiwek) ─────────────────────────── */
.guest-row .acts {
    display: flex; gap: 4px; opacity: 0; transition: .2s;
    flex-shrink: 0;
}
.guest-row:hover .acts { opacity: 1; }
.guest-row .acts button {
    width: 22px; height: 22px; padding: 0;
    background: rgba(0,0,0,0.6); color: var(--neon-gold);
    border: 1px solid rgba(255,215,0,0.3);
    font-size: .8em; cursor: pointer; border-radius: 2px;
    transition: .15s;
}
.guest-row .acts button:hover {
    background: var(--neon-gold); color: #000;
    box-shadow: 0 0 8px rgba(255,215,0,0.5);
}

/* ════════════════════════════════════════════════════════════
   FAZA 4 — DJ WIDGET + EDYCJA WIADOMOŚCI
   ════════════════════════════════════════════════════════════ */

/* DJ Now Playing - kompaktowy panel boczny */
.dj-panel {
    background: linear-gradient(135deg, rgba(74,214,255,0.08), rgba(0,0,0,0.4));
    border: 1px solid rgba(74,214,255,0.3);
    border-radius: 2px; padding: 12px; margin-bottom: 14px;
    position: relative;
}
.dj-panel.empty { background: rgba(0,0,0,0.4); border-color: var(--border-soft); }
.dj-panel-eyebrow {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-cyan); letter-spacing: 2.5px; text-transform: uppercase;
    margin-bottom: 6px; display: flex; align-items: center; gap: 6px;
}
.dj-panel.empty .dj-panel-eyebrow { color: var(--txt-mute); }
.dj-panel-eyebrow .dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--neon-cyan); box-shadow: 0 0 6px var(--neon-cyan);
    animation: djBlinkSm 1.4s infinite;
}
.dj-panel.empty .dj-panel-eyebrow .dot { background: var(--txt-mute); animation: none; box-shadow: none; }
@keyframes djBlinkSm { 50% { opacity: .3; } }

.dj-tytul-sm {
    font-family: 'Fraunces', serif; font-size: 1.05em;
    color: #fff; line-height: 1.15; margin-bottom: 4px;
}
.dj-tytul-sm i { color: var(--neon-cyan); font-style: italic; font-size: .92em; }
.dj-panel.empty .dj-tytul-sm { color: var(--txt-dim); font-style: italic; font-size: .92em; }

.dj-meta-sm {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .8em; color: var(--txt-dim); line-height: 1.4;
    margin-bottom: 8px;
}
.dj-meta-sm b { color: #fff; font-style: normal; font-family: 'Oswald', sans-serif; font-size: .85em; }

.dj-akcje-sm { display: flex; gap: 6px; margin-top: 6px; }
.dj-akcje-sm button {
    flex: 1; padding: 5px 8px;
    background: rgba(74,214,255,0.1);
    border: 1px solid rgba(74,214,255,0.4);
    color: var(--neon-cyan);
    font-family: 'Oswald', sans-serif; font-size: .68em;
    letter-spacing: 1px; text-transform: uppercase;
    cursor: pointer; border-radius: 2px; transition: .15s;
}
.dj-akcje-sm button:hover { background: var(--neon-cyan); color: #000; }
.dj-akcje-sm button.stop {
    background: rgba(255,23,68,0.08); border-color: rgba(255,23,68,0.4);
    color: var(--neon-red-hot);
}
.dj-akcje-sm button.stop:hover { background: var(--neon-red); color: #fff; }

.dj-kolejka-list { margin-top: 10px; max-height: 200px; overflow-y: auto; }
.dj-row {
    display: grid; grid-template-columns: 1fr auto;
    gap: 6px; padding: 6px 8px;
    background: rgba(0,0,0,0.35); border-left: 2px solid rgba(74,214,255,0.4);
    margin-bottom: 4px; border-radius: 1px;
    font-size: .82em;
}
.dj-row .info { min-width: 0; overflow: hidden; }
.dj-row .nm {
    font-family: 'Fraunces', serif; color: #fff;
    font-size: .98em; line-height: 1.1;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dj-row .nm i { color: var(--neon-cyan); font-style: italic; font-size: .9em; }
.dj-row .od {
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1px;
    text-transform: uppercase; margin-top: 2px;
}
.dj-row .play-btn {
    background: rgba(74,214,255,0.15); border: 1px solid var(--neon-cyan);
    color: var(--neon-cyan); padding: 0 8px; cursor: pointer;
    font-size: .9em; border-radius: 2px;
}
.dj-row .play-btn:hover { background: var(--neon-cyan); color: #000; }
.dj-row .reject-btn {
    background: transparent; border: 1px solid var(--border-soft);
    color: var(--txt-mute); padding: 0 6px; cursor: pointer;
    font-size: .85em; border-radius: 2px;
}
.dj-row .reject-btn:hover { color: var(--neon-red-hot); border-color: var(--neon-red); }
.dj-row.moje { border-left-color: var(--neon-gold); }

.dj-zamow-form { display: flex; flex-direction: column; gap: 4px; margin-top: 8px; }
.dj-zamow-form input {
    background: rgba(0,0,0,0.55); border: 1px solid var(--border-soft);
    color: #ddd; padding: 6px 8px; border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-size: .92em; font-style: italic;
}
.dj-zamow-form input:focus { border-color: var(--neon-cyan); outline: none; }
.dj-zamow-form button {
    padding: 6px; background: rgba(74,214,255,0.1);
    border: 1px solid var(--neon-cyan); color: var(--neon-cyan);
    font-family: 'Oswald', sans-serif; font-size: .72em;
    letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px;
}
.dj-zamow-form button:hover { background: var(--neon-cyan); color: #000; }

.dj-pusto {
    color: var(--txt-mute); font-size: .82em;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    padding: 8px 4px; text-align: center;
}

/* ── EDYCJA WIADOMOŚCI ───────────────────────────────────── */
.msg .actions {
    display: none; gap: 4px; margin-left: 6px;
    align-items: center;
}
.msg.is-mine:hover .actions, .msg.can-edit:hover .actions { display: inline-flex; }
.msg .actions button {
    background: transparent; border: 1px solid var(--border-soft);
    color: var(--txt-mute); cursor: pointer;
    padding: 1px 6px; border-radius: 1px;
    font-family: 'JetBrains Mono', monospace;
    font-size: .62em; letter-spacing: 1px; text-transform: uppercase;
    transition: .15s;
}
.msg .actions button:hover {
    color: var(--neon-cyan); border-color: var(--neon-cyan);
    background: rgba(74,214,255,0.08);
}
.msg .actions button.del:hover {
    color: var(--neon-red-hot); border-color: var(--neon-red);
    background: rgba(255,23,68,0.08);
}
.msg .who .edited {
    font-family: 'JetBrains Mono', monospace; font-size: .58em;
    color: var(--txt-mute); letter-spacing: 1px;
    text-transform: uppercase; opacity: .7;
}
.msg.usunieta .txt { color: var(--txt-mute); font-style: italic; }
.msg.usunieta .actions { display: none !important; }

.edit-mode-input {
    width: 100%; min-height: 40px; max-height: 120px; resize: vertical;
    background: rgba(0,0,0,0.6); border: 1px solid var(--neon-cyan);
    color: #fff; padding: 6px 8px; border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-size: 1em;
    box-shadow: inset 0 0 8px rgba(74,214,255,0.1);
}
.edit-mode-actions { margin-top: 4px; display: flex; gap: 6px; }
.edit-mode-actions button {
    padding: 3px 10px; background: rgba(74,214,255,0.15);
    border: 1px solid var(--neon-cyan); color: var(--neon-cyan);
    font-family: 'Oswald', sans-serif; font-size: .7em;
    letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px;
}
.edit-mode-actions button:hover { background: var(--neon-cyan); color: #000; }
.edit-mode-actions button.cancel {
    background: transparent; border-color: var(--border-soft); color: var(--txt-dim);
}
</style>

<div class="klub-wrap sala-glowna">
    <div class="klub-3col">

        <!-- ══════ KOL LEFT — INFO + DRINKI + RACHUNEK ══════ -->
        <div class="kol-left">
            <div class="room-header">
                <a href="game.php?page=czat&sala=lobby" class="back">◂ LOBBY</a>
                <h2>🍸 Sala Główna</h2>
                <div class="sub">Bar, parkiet, DJ. Serce klubu.</div>
                <div class="stats">
                    <span>GOŚCI: <b><?php echo $liczba_obecnych; ?>/40</b></span>
                    <span>CZAS: <b><?php echo date('H:i'); ?></b></span>
                </div>
            </div>

            <?php if ($staty_barmana): ?>
            <div class="staty-barmana">
                <div class="hd">🍸 Twoje statystyki</div>
                <div class="grid">
                    <div>Napiwki<br><b><?php echo number_format((int)$staty_barmana['klub_napiwki_zarobione'], 0, '', ' '); ?> $</b></div>
                    <div>Drinki podane<br><b><?php echo (int)$staty_barmana['klub_drinki_podane']; ?></b></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── FAZA 3: SEKRETNE SYGNAŁY (flirty otrzymane) ── -->
            <div class="aside-h">✦ Sekretne sygnały <span style="color:var(--txt-mute);font-size:.8em;font-weight:normal;letter-spacing:0">ostatnie 24h</span></div>
            <div class="klub-sygnaly" id="klub-sygnaly">
                <div id="klub-sygnaly-lista">
                    <?php if (empty($sygnaly)): ?>
                        <div class="sygnaly-pusto">Nikt jeszcze nie spojrzał...<br><small style="font-size:.85em">Wpisz <code>/flirt @kogo</code> w czacie żeby wysłać sygnał.</small></div>
                    <?php else: ?>
                        <?php foreach ($sygnaly as $s): ?>
                        <div class="sygnal-row">
                            <span class="ic">✦</span>
                            <span class="nm"><i><?php echo htmlspecialchars($s['od_login']); ?></i></span>
                            <span class="czas"><?php echo htmlspecialchars($s['czas']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── KARTA DRINKÓW (klikalne) ── -->
            <div class="aside-h">🍸 Karta drinków</div>
            <?php foreach ($drinki as $d): ?>
            <div class="drink-menu-item <?php echo $d['sygnatura'] ? 'sig' : ''; ?>" 
                 onclick="window.klubZamow('<?php echo addslashes($d['nazwa']); ?>')"
                 title="Kliknij, żeby zamówić">
                <div class="ikona"><?php echo htmlspecialchars($d['ikona_emoji']); ?></div>
                <div class="info">
                    <div class="nm"><?php echo htmlspecialchars($d['nazwa']); ?>
                        <span class="desc"><?php echo htmlspecialchars($d['opis']); ?></span>
                    </div>
                </div>
                <div class="pr"><?php echo (int)$d['cena']; ?> $</div>
            </div>
            <?php endforeach; ?>

            <!-- ── TWÓJ RACHUNEK ── -->
            <div class="aside-h" style="margin-top:18px">◈ Twój rachunek</div>
            <div class="rachunek-box" id="klub-rachunek">
                <?php if (empty($pozycje)): ?>
                    <div class="rachunek-pusty">Rachunek pusty.<br><small>Zamów drinka komendą <b>/bar zamów [nazwa]</b> lub klikając pozycję z karty.</small></div>
                <?php else: ?>
                    <div class="rachunek-naglowek">◈ Twój rachunek</div>
                    <div class="rachunek-pozycje">
                    <?php foreach ($pozycje as $p): ?>
                        <div class="r-poz">
                            <span class="r-nm"><?php echo htmlspecialchars($p['nazwa_drink']); ?></span>
                            <span class="r-cz"><?php echo htmlspecialchars($p['czas']); ?></span>
                            <span class="r-pr"><?php echo (int)$p['cena']; ?> $</span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="rachunek-total"><span>RAZEM</span><b><?php echo $razem; ?> $</b></div>
                    <button type="button" class="btn-zaplac" onclick="window.klubZaplac()">▸ Zapłać rachunek</button>
                <?php endif; ?>
            </div>

            <!-- ── DJ PANEL (Faza 4) ── -->
            <div class="aside-h" style="margin-top:18px">🎧 DJ <span style="color:var(--txt-mute);font-size:.8em;font-weight:normal;letter-spacing:0">w sali głównej</span></div>
            <div class="dj-panel <?php echo $dj_gra ? '' : 'empty'; ?>" id="klub-dj">
                <?php if ($dj_gra): ?>
                <div class="dj-panel-eyebrow"><span class="dot"></span> Teraz gra · <?php echo htmlspecialchars($dj_gra['gra_od'] ?: ''); ?></div>
                <div class="dj-tytul-sm">
                    <?php echo htmlspecialchars($dj_gra['tytul']); ?>
                    <?php if ($dj_gra['artysta']): ?> — <i><?php echo htmlspecialchars($dj_gra['artysta']); ?></i><?php endif; ?>
                </div>
                <div class="dj-meta-sm">
                    <?php if ($dj_gra['dj_login']): ?>🎧 DJ <b><?php echo htmlspecialchars($dj_gra['dj_login']); ?></b><?php endif; ?>
                    <?php if ($dj_gra['zamawiajacy']): ?> · dla <b><?php echo htmlspecialchars($dj_gra['zamawiajacy']); ?></b><?php endif; ?>
                    <?php if ($dj_gra['notka']): ?><br>"<?php echo htmlspecialchars($dj_gra['notka']); ?>"<?php endif; ?>
                </div>
                <?php if ($ma_uprawnienia): ?>
                <div class="dj-akcje-sm">
                    <button onclick="window.djNastepny()">▶ Następny</button>
                    <button class="stop" onclick="window.djZakoncz()">■ Cisza</button>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="dj-panel-eyebrow"><span class="dot"></span> Cisza</div>
                <div class="dj-tytul-sm">DJ jeszcze nic nie puścił</div>
                <?php endif; ?>

                <!-- Kolejka -->
                <?php if (!empty($dj_kolejka)): ?>
                <div class="aside-h" style="font-size:.65em;letter-spacing:2px;margin:10px 0 6px;background:transparent;border-left-color:rgba(74,214,255,0.4);color:var(--neon-cyan)">
                    Kolejka (<?php echo count($dj_kolejka); ?>)
                </div>
                <div class="dj-kolejka-list">
                    <?php foreach ($dj_kolejka as $k): ?>
                    <div class="dj-row <?php echo $k['czy_moje'] ? 'moje' : ''; ?>">
                        <div class="info">
                            <div class="nm"><?php echo htmlspecialchars($k['tytul']); ?><?php if ($k['artysta']): ?> — <i><?php echo htmlspecialchars($k['artysta']); ?></i><?php endif; ?></div>
                            <div class="od">od <?php echo htmlspecialchars($k['zamawiajacy']); ?><?php if ($k['notka']): ?> · "<?php echo htmlspecialchars($k['notka']); ?>"<?php endif; ?></div>
                        </div>
                        <?php if ($ma_uprawnienia): ?>
                        <div style="display:flex;gap:3px">
                            <button class="play-btn" onclick="window.djPusc(<?php echo (int)$k['id']; ?>)" title="Puść teraz">▶</button>
                            <button class="reject-btn" onclick="window.djOdrzuc(<?php echo (int)$k['id']; ?>)" title="Odrzuć">✕</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Forma zamawiania (każdy gracz) -->
                <div class="dj-zamow-form">
                    <input type="text" id="dj-tytul" placeholder="Tytuł utworu" maxlength="150">
                    <input type="text" id="dj-artysta" placeholder="Artysta (opcjonalnie)" maxlength="100">
                    <input type="text" id="dj-notka" placeholder='Notka, np. „dla Marka, na 22:30" (opcjonalnie)' maxlength="200">
                    <button onclick="window.djZamow()">🎵 Zamów u DJ-a</button>
                </div>

                <?php if ($ma_uprawnienia): ?>
                <details style="margin-top:8px">
                    <summary style="font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--neon-cyan);cursor:pointer;letter-spacing:1.5px;text-transform:uppercase">Ogłosić ręcznie (DJ)</summary>
                    <div class="dj-zamow-form" style="margin-top:6px">
                        <input type="text" id="dj-recznie-tytul" placeholder="Tytuł" maxlength="150">
                        <input type="text" id="dj-recznie-artysta" placeholder="Artysta" maxlength="100">
                        <button onclick="window.djOgloszenie()">🎧 Puść teraz</button>
                    </div>
                </details>
                <?php endif; ?>
            </div>

            <!-- ── KOMENDY ── -->
            <div class="aside-h" style="margin-top:18px">📎 Komendy</div>
            <div class="cmd-info">/bar zamów [drink] <small>z karty</small></div>
            <div class="cmd-info">/zaplac <small>opłać rachunek</small></div>
            <div class="cmd-info">/napiwek @nick [kwota] <small>napiwek</small></div>
            <div class="cmd-info">/karta /kosc /moneta <small>krupier</small></div>
            <div class="cmd-info">/mood [tekst] <small>nastrój</small></div>
            <div class="cmd-info" style="color:var(--neon-red-hot)">/flirt @nick <small>sygnał prywatny</small></div>
            <div class="cmd-info" style="color:var(--neon-cyan)">/dj Tytuł — Artysta <small>zamów utwór</small></div>
            <div class="cmd-info" style="color:var(--neon-ember)">/plotka [tekst] <small>anonim · w lobby</small></div>
            <div class="cmd-info" style="color:#c896ff">/życzenie [tytuł] <small>RP partner · w lobby</small></div>
            <div class="cmd-info">/edytuj ID [tekst] <small>do 10 min</small></div>
            <div class="cmd-info">/usun ID <small>do 10 min</small></div>
            <div class="cmd-info">/do [sala] <small>przejdź</small></div>
            <?php if ($ma_uprawnienia): ?>
            <div class="cmd-info" style="color:var(--neon-ember)">/npc "Imię" "tekst" <small>NPC</small></div>
            <div class="cmd-info" style="color:var(--neon-red-hot)">/wypraszam @nick <small>10–60 min</small></div>
            <?php endif; ?>
            <?php if ($jest_mg): ?>
            <div class="cmd-info" style="color:#c896ff">/szept @nick [tekst] <small>MG only</small></div>
            <?php endif; ?>
        </div>

        <!-- ══════ KOL CENTER — CHAT ══════ -->
        <div class="kol-center chat">
            <div class="chat-head">
                <div>
                    <div class="breadcrumb">
                        <a href="game.php?page=czat&sala=lobby">Klub</a> / Sala Główna
                    </div>
                    <div class="title"><span class="ic">🍸</span> Bar i Parkiet · LIVE</div>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--txt-dim);font-size:.9em;">
                    <?php if ($live_barman): ?>
                        Barman-gracz: <span style="color:var(--neon-red-hot)"><?php echo htmlspecialchars($barman_login); ?></span>
                    <?php else: ?>
                        Dziś za barem: <span style="color:var(--neon-ember)">stary NPC</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="feed" id="klub-feed" data-sala="sala-glowna" data-last-id="<?php echo $last_id; ?>">
                <?php if (empty($wiadomosci)): ?>
                <div class="empty">
                    Klub milczy. Słychać tylko cichy jazz z szafy grającej przy schodach.<br>
                    Napisz cokolwiek — opis akcji w <b>*gwiazdkach*</b> albo dialog w <b>"cudzysłowach"</b>.
                </div>
                <?php else: ?>
                    <?php foreach ($wiadomosci as $w) klub_render_msg($w); ?>
                <?php endif; ?>
            </div>

            <!-- Composer -->
            <div class="composer">
                <div class="quick-cmds">
                    <button type="button" class="qc prim" onclick="window.klubInsertCmd('/bar zamów')">/bar zamów</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/napiwek @')">/napiwek</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/zaplac')">/zaplac</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/flirt @')" style="color:var(--neon-red-hot);border-color:rgba(255,61,94,0.4)">✦ /flirt</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/mood')">/mood</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/karta')">/karta</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/kosc')">/kosc</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/do')">/do [sala]</button>
                    <?php if ($ma_uprawnienia): ?>
                    <button type="button" class="qc" onclick='window.klubInsertCmd(`/npc &quot;Imię&quot; &quot;tekst&quot;`)' style="color:var(--neon-ember);border-color:rgba(255,122,61,0.4)">/npc</button>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/wypraszam @')" style="color:var(--neon-red-hot)">/wypraszam</button>
                    <?php endif; ?>
                    <?php if ($jest_mg): ?>
                    <button type="button" class="qc" onclick="window.klubInsertCmd('/szept @')" style="color:#c896ff;border-color:rgba(200,150,255,0.4)">🜂 /szept</button>
                    <?php endif; ?>
                </div>

                <form id="klub-form" class="composer-main" autocomplete="off">
                    <textarea name="tresc" class="chat-input" id="klub-input"
                        placeholder='*Siadasz przy barze, rozpinasz marynarkę.* — "Whisky. Podwójną. I niech będzie dziś cicho."'
                        required></textarea>
                    <button type="submit" class="btn-send">Wyślij ◤</button>
                </form>
            </div>
        </div>

        <!-- ══════ KOL RIGHT — GOŚCIE W SALI ══════ -->
        <div class="kol-right">
            <div class="aside-h">👥 W sali <span style="color:var(--neon-green);font-size:.9em;">● <?php echo $liczba_obecnych; ?></span></div>

            <div id="klub-obecni">
                <?php if (empty($obecni)): ?>
                    <div class="obecni-pusto" style="color:var(--txt-mute); font-family:'Cormorant Garamond',serif; font-style:italic; font-size:.9em; padding:20px 4px; text-align:center;">
                        Jesteś pierwszy. Kiedyś ktoś wejdzie.
                    </div>
                <?php else: ?>
                    <?php foreach ($obecni as $o):
                        $klasa = ($o['id'] == $id_gracza) ? 'me' : ($o['is_barman'] ? 'bar' : ($o['is_mg'] ? 'mg' : ''));
                        $ava = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : '';
                        $inic = klub_inicjaly($o['login']);
                        $mood = !empty($o['klub_mood']) ? $o['klub_mood'] : ($o['is_barman'] ? 'za barem' : 'w sali');
                    ?>
                    <a href="game.php?page=profil&id=<?php echo (int)$o['id']; ?>" class="guest-row <?php echo $klasa; ?>">
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
            $linki = [['bdsm','⛓','BDSM'],['sauna','♨','Sauna'],['tyly','🚬','Tyły']];
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