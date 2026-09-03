<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — WYDARZENIA (Faza 3)
// Pełny widok wydarzeń: lista nadchodzących + formularz dla barmana
// Włączane przez ?widok=wydarzenia w URL lobby
// ══════════════════════════════════════════════════════════════════

// Pobierz wszystkie aktywne i niedawno zakończone eventy
$wszystkie_eventy = [];
$qe = $polaczenie->query("
    SELECT w.*, g.login AS autor_login,
           (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id) AS liczba_rez,
           (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id AND gracz_id=$id_gracza) AS moja_rez
    FROM klub_wydarzenia w
    LEFT JOIN gracze g ON g.id = w.autor_id
    WHERE w.aktywne=1 AND w.anulowane=0
      AND w.data_startu >= NOW() - INTERVAL 4 HOUR
    ORDER BY w.data_startu ASC LIMIT 30
");
if ($qe) while ($e = $qe->fetch_assoc()) $wszystkie_eventy[] = $e;

$dni_pl_nazwy = ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'];
$miesiace_pl = ['', 'stycznia','lutego','marca','kwietnia','maja','czerwca','lipca','sierpnia','września','października','listopada','grudnia'];
?>

<style>
.wyd-wrap { margin-top: 30px; }

.wyd-tworz {
    background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(0,0,0,0.4));
    border: 1px solid rgba(255,215,0,0.4); border-radius: 2px;
    padding: 20px 22px; margin-bottom: 24px; position: relative;
}
.wyd-tworz::before {
    content: '✦ TWORZENIE WYDARZENIA'; position: absolute; top: -10px; left: 18px;
    background: #0a0a12; color: var(--neon-gold); padding: 2px 12px;
    font-family: 'Oswald', sans-serif; font-size: .75em;
    letter-spacing: 2px; border: 1px solid rgba(255,215,0,0.5);
}
.wyd-tworz form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px; }
.wyd-tworz form .full { grid-column: span 2; }
.wyd-tworz label {
    display: block; font-family: 'JetBrains Mono', monospace;
    font-size: .68em; letter-spacing: 2px; color: var(--neon-gold);
    text-transform: uppercase; margin-bottom: 4px;
}
.wyd-tworz input, .wyd-tworz textarea, .wyd-tworz select {
    width: 100%; padding: 8px 10px;
    background: rgba(0,0,0,0.6); border: 1px solid var(--border-soft);
    color: #ddd; font-family: 'Open Sans', sans-serif; font-size: .9em;
    border-radius: 2px; box-sizing: border-box;
}
.wyd-tworz input:focus, .wyd-tworz textarea:focus, .wyd-tworz select:focus {
    border-color: var(--neon-gold); outline: none;
}
.wyd-tworz textarea { min-height: 80px; resize: vertical; font-family: 'Cormorant Garamond', serif; font-size: 1em; }
.wyd-tworz .btn-utworz {
    grid-column: span 2;
    padding: 12px; background: rgba(255,215,0,0.15);
    color: var(--neon-gold); border: 1px solid rgba(255,215,0,0.4);
    font-family: 'Oswald', sans-serif; font-size: .9em;
    letter-spacing: 2.5px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px; transition: .2s;
}
.wyd-tworz .btn-utworz:hover { background: var(--neon-gold); color: #000; box-shadow: 0 0 18px rgba(255,215,0,0.5); }
.wyd-tworz .pomoc {
    grid-column: span 2;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-mute); padding: 8px 10px;
    background: rgba(0,0,0,0.3); border-left: 2px solid rgba(255,215,0,0.3);
    line-height: 1.5;
}

/* Color picker */
.kolor-picker { display: flex; gap: 6px; flex-wrap: wrap; }
.kolor-opt {
    width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
    border: 2px solid transparent; transition: .15s;
    display: flex; align-items: center; justify-content: center;
    font-size: .9em; color: #fff;
}
.kolor-opt input { display: none; }
.kolor-opt.pink   { background: linear-gradient(135deg, #ff3d5e, #5a0010); }
.kolor-opt.gold   { background: linear-gradient(135deg, #ffd700, #5a4000); color: #000; }
.kolor-opt.purple { background: linear-gradient(135deg, #c896ff, #3a1060); }
.kolor-opt.red    { background: linear-gradient(135deg, #ff1744, #4a0010); }
.kolor-opt.cyan   { background: linear-gradient(135deg, #4ad6ff, #00505a); color: #000; }
.kolor-opt.checked, .kolor-opt:hover { border-color: #fff; box-shadow: 0 0 10px rgba(255,255,255,0.4); }

/* ── PLAKAT UPLOAD ────────────────────────────────────────── */
.plakat-upload {
    border: 1px dashed rgba(255,215,0,0.3);
    background: rgba(0,0,0,0.4);
    padding: 14px; border-radius: 2px;
    text-align: center;
}
.plakat-upload input[type=file] {
    width: 100%; padding: 8px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft);
    color: #ddd; font-family: 'JetBrains Mono', monospace; font-size: .82em;
    cursor: pointer; border-radius: 2px;
}
.plakat-upload input[type=file]::file-selector-button {
    background: rgba(255,215,0,0.15);
    color: var(--neon-gold);
    border: 1px solid rgba(255,215,0,0.4);
    padding: 5px 12px; cursor: pointer; border-radius: 2px;
    font-family: 'Oswald', sans-serif; font-size: .82em;
    letter-spacing: 1.5px; text-transform: uppercase;
    margin-right: 10px;
}
.plakat-upload input[type=file]::file-selector-button:hover {
    background: var(--neon-gold); color: #000;
}
.plakat-info {
    margin-top: 8px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .9em; color: var(--txt-dim);
}
.plakat-preview {
    margin-top: 14px; position: relative;
    display: inline-block;
}
.plakat-preview img {
    max-width: 100%; max-height: 320px;
    border: 1px solid var(--neon-gold);
    box-shadow: 0 4px 20px rgba(0,0,0,0.6), 0 0 16px rgba(255,215,0,0.2);
    border-radius: 2px;
}
.plakat-usun {
    position: absolute; top: 8px; right: 8px;
    background: rgba(255,23,68,0.9); border: 1px solid var(--neon-red);
    color: #fff; padding: 5px 12px;
    font-family: 'Oswald', sans-serif; font-size: .72em;
    letter-spacing: 1.5px; cursor: pointer; border-radius: 2px;
    text-transform: uppercase;
}
.plakat-usun:hover { background: var(--neon-red); }

/* Lista eventów - karty */
.wyd-lista { display: flex; flex-direction: column; gap: 12px; }
.wyd-card {
    display: grid; grid-template-columns: 100px 1fr auto;
    gap: 16px; padding: 16px 18px;
    background: rgba(10,5,12,0.55); border: 1px solid var(--border-soft);
    border-left: 4px solid var(--neon-red); border-radius: 2px;
    transition: .2s; align-items: center;
}
.wyd-card:hover { border-left-color: var(--neon-red-hot); background: rgba(255,23,68,0.04); }
.wyd-card.pink   { border-left-color: #ff3d5e; }
.wyd-card.gold   { border-left-color: var(--neon-gold); }
.wyd-card.purple { border-left-color: #c896ff; }
.wyd-card.red    { border-left-color: var(--neon-red-hot); }
.wyd-card.cyan   { border-left-color: var(--neon-cyan); }

/* FAZA 4: karta z plakatem (mini-thumb) */
.wyd-card.z-plakatem .wyd-data {
    position: relative; overflow: hidden; padding: 0;
    min-height: 120px; background: rgba(0,0,0,0.6);
}
.wyd-mini-plakat {
    position: absolute; inset: 0;
    background-size: cover; background-position: center;
    opacity: .55; pointer-events: none;
}
.wyd-card.z-plakatem .wyd-data > div:not(.wyd-mini-plakat) {
    position: relative; z-index: 2;
}
.wyd-card.z-plakatem .wyd-data .dzien {
    text-shadow: 0 2px 8px rgba(0,0,0,0.95);
    margin-top: 14px;
}
.wyd-card.z-plakatem .wyd-data .miesiac,
.wyd-card.z-plakatem .wyd-data .czas,
.wyd-card.z-plakatem .wyd-data .dzien-tyg {
    text-shadow: 0 1px 4px rgba(0,0,0,0.9);
}

.wyd-card.live {
    background: linear-gradient(90deg, rgba(255,23,68,0.08), rgba(10,5,12,0.55));
    border-color: var(--neon-red);
    box-shadow: 0 0 20px rgba(255,23,68,0.2);
}

.wyd-data {
    text-align: center; padding: 8px;
    background: rgba(0,0,0,0.4); border-radius: 2px;
    border: 1px solid var(--border-soft);
}
.wyd-data .dzien {
    font-family: 'Oswald', sans-serif; font-size: 2em;
    color: #fff; line-height: 1; font-weight: 600;
}
.wyd-data .miesiac {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-red-hot); letter-spacing: 2px;
    text-transform: uppercase; margin-top: 2px;
}
.wyd-data .czas {
    font-family: 'JetBrains Mono', monospace; font-size: .8em;
    color: var(--neon-gold); margin-top: 4px;
}
.wyd-data .dzien-tyg {
    font-family: 'JetBrains Mono', monospace; font-size: .58em;
    color: var(--txt-mute); letter-spacing: 1.5px;
    text-transform: uppercase; margin-top: 2px;
}

.wyd-info {
    min-width: 0;
}
.wyd-info .nazwa {
    font-family: 'Fraunces', serif; font-size: 1.45em;
    color: #fff; line-height: 1.1; margin-bottom: 6px;
    text-shadow: 0 0 8px rgba(255,23,68,0.2);
}
.wyd-info .nazwa .ikona { color: var(--neon-red); margin-right: 6px; }
.wyd-info .meta-row {
    display: flex; gap: 14px; flex-wrap: wrap;
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-dim); letter-spacing: 1px; margin-bottom: 6px;
    text-transform: uppercase;
}
.wyd-info .meta-row b { color: var(--neon-red-hot); }
.wyd-info .opis {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .98em; color: var(--txt-dim); line-height: 1.5;
    max-height: 60px; overflow: hidden;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}

.wyd-akcje { display: flex; flex-direction: column; gap: 6px; min-width: 140px; }
.wyd-rez-info {
    text-align: center; padding: 6px 10px;
    background: rgba(0,0,0,0.4); border: 1px solid var(--border-soft);
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    color: var(--txt-dim);
}
.wyd-rez-info b { color: var(--neon-red-hot); display: block; font-size: 1.2em; line-height: 1; margin-bottom: 2px; }
.wyd-btn {
    padding: 8px 14px; border: 1px solid var(--neon-red);
    background: rgba(255,23,68,0.1); color: var(--neon-red-hot);
    font-family: 'Oswald', sans-serif; font-size: .82em;
    letter-spacing: 2px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px; transition: .2s; text-align: center;
    text-decoration: none; display: block;
}
.wyd-btn:hover { background: var(--neon-red); color: #fff; }
.wyd-btn.zapisany { background: rgba(90,255,154,0.1); border-color: var(--neon-green); color: var(--neon-green); }
.wyd-btn.zapisany:hover { background: var(--neon-green); color: #000; }
.wyd-btn.pelne { background: rgba(0,0,0,0.4); border-color: var(--border-soft); color: var(--txt-mute); cursor: not-allowed; }
.wyd-btn.live-now { background: var(--neon-red); color: #fff; animation: liveBlinkBtn 1.5s infinite; }
@keyframes liveBlinkBtn { 0%,100%{box-shadow:0 0 16px rgba(255,23,68,0.6)} 50%{box-shadow:0 0 24px rgba(255,23,68,0.95)} }
.wyd-btn-cancel {
    padding: 4px 10px; font-size: .7em;
    background: rgba(0,0,0,0.5); color: var(--txt-mute);
    border: 1px solid var(--border-soft);
}
.wyd-btn-cancel:hover { color: var(--neon-red-hot); border-color: var(--neon-red); background: rgba(255,23,68,0.1); }

.wyd-pusty {
    padding: 60px 20px; text-align: center;
    background: rgba(0,0,0,0.3); border: 1px dashed var(--border-soft);
    border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-mute); font-size: 1.1em; line-height: 1.6;
}
.wyd-pusty b { color: var(--neon-gold); font-style: normal; font-family: 'Oswald', sans-serif; letter-spacing: 2px; }
</style>

<div class="wyd-wrap">

    <!-- ── FORMULARZ TWORZENIA (tylko barman/MG) ── -->
    <?php if ($ma_uprawnienia): ?>
    <div class="wyd-tworz">
        <form id="form-tworz-event">
            <div class="full">
                <label>Nazwa wydarzenia</label>
                <input type="text" name="nazwa" required maxlength="120" placeholder="np. „Walentynki w Otchłani"">
            </div>

            <div>
                <label>Data i godzina startu</label>
                <input type="datetime-local" name="data_startu" required>
            </div>
            <div>
                <label>Koniec (opcjonalnie)</label>
                <input type="datetime-local" name="data_konca">
            </div>

            <div>
                <label>Sala</label>
                <select name="sala">
                    <option value="sala-balowa">💃 Sala Balowa (klasyk eventów)</option>
                    <option value="sala-glowna">🍸 Sala Główna</option>
                    <option value="vip">🥂 Loża VIP</option>
                    <option value="taras">🌃 Taras</option>
                    <option value="basen">🏊 Basen</option>
                    <option value="bdsm">⛓ Pokój BDSM</option>
                    <option value="sauna">♨ Sauna</option>
                    <option value="silownia">💪 Siłownia</option>
                    <option value="masaze">💆 Masaże</option>
                    <option value="garderoba">👗 Garderoba</option>
                    <option value="tyly">🚬 Tyły</option>
                </select>
            </div>
            <div>
                <label>Maksymalna liczba miejsc</label>
                <input type="number" name="max_miejsc" value="30" min="1" max="200">
            </div>

            <div>
                <label>Ikona (1 emoji)</label>
                <input type="text" name="ikona_emoji" value="✦" maxlength="4">
            </div>
            <div>
                <label>Kolor plakatu</label>
                <div class="kolor-picker">
                    <label class="kolor-opt pink checked" title="Różowy"><input type="radio" name="kolor_plakatu" value="pink" checked></label>
                    <label class="kolor-opt gold" title="Złoty"><input type="radio" name="kolor_plakatu" value="gold"></label>
                    <label class="kolor-opt purple" title="Fioletowy"><input type="radio" name="kolor_plakatu" value="purple"></label>
                    <label class="kolor-opt red" title="Czerwony"><input type="radio" name="kolor_plakatu" value="red"></label>
                    <label class="kolor-opt cyan" title="Cyjan"><input type="radio" name="kolor_plakatu" value="cyan"></label>
                </div>
            </div>

            <div class="full">
                <label>Opis wydarzenia</label>
                <textarea name="opis" maxlength="2000" placeholder="*Krótki opis fabularny.* Co się będzie działo? Co przygotujesz? Może zalecić dress code? Kogo szukasz?"></textarea>
            </div>

            <div class="full">
                <label>Plakat (opcjonalnie) <small style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--txt-mute);text-transform:none;letter-spacing:0">— JPG/PNG/WebP, max 5 MB · zalecane proporcje 3:4 (np. 600×800)</small></label>
                <div class="plakat-upload">
                    <input type="file" name="plakat" id="plakat-input" accept="image/jpeg,image/png,image/webp">
                    <div class="plakat-info">Brak pliku — użyte będzie tło z koloru i ikony.</div>
                    <div class="plakat-preview" id="plakat-preview" style="display:none">
                        <img id="plakat-preview-img" src="" alt="Podgląd">
                        <button type="button" class="plakat-usun" onclick="document.getElementById('plakat-input').value='';document.getElementById('plakat-preview').style.display='none';document.querySelector('.plakat-info').textContent='Brak pliku — użyte będzie tło z koloru i ikony.'">✕ Usuń</button>
                    </div>
                </div>
            </div>

            <div class="pomoc">
                💡 <b>Wskazówka:</b> wydarzenie pojawi się natychmiast w Lobby jako plakat. Gracze będą mogli kliknąć „Biorę udział". 30 min przed startem dostaną powiadomienie. Możesz później anulować (z podaniem powodu). Jeśli wgrasz plakat — kolor i ikona zostaną widoczne jako overlay na wierzchu obrazka.
            </div>

            <button type="submit" class="btn-utworz">✦ Utwórz wydarzenie</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── LISTA WYDARZEŃ ── -->
    <div class="sec-head">
        <h2><span class="ic">◈</span> Wszystkie nadchodzące wydarzenia</h2>
        <div class="meta">
            <a href="game.php?page=czat&sala=lobby" style="color:var(--txt-dim);text-decoration:none;border-bottom:1px dashed">◂ Wróć do Lobby</a>
        </div>
    </div>

    <?php if (empty($wszystkie_eventy)): ?>
    <div class="wyd-pusty">
        <b>Pusty kalendarz.</b><br><br>
        Żaden barman nie ogłosił jeszcze wydarzenia.<br>
        <i>Wracaj — coś się będzie działo.</i>
    </div>
    <?php else: ?>
    <div class="wyd-lista">
        <?php foreach ($wszystkie_eventy as $e):
            $ts = strtotime($e['data_startu']);
            $dzien_tyg = $dni_pl_nazwy[(int)date('w', $ts)];
            $miesiac_n = $miesiace_pl[(int)date('n', $ts)];
            $dzien = (int)date('j', $ts);
            $czas = date('H:i', $ts);
            $sale_nazwy = $SALE[$e['sala']]['nazwa'] ?? $e['sala'];

            $juz_zaczal = ($ts <= time());
            $juz_skonczyl = ($e['data_konca'] && strtotime($e['data_konca']) <= time());
            $pelne = ((int)$e['liczba_rez'] >= (int)$e['max_miejsc']);
            $moja_rez = ((int)$e['moja_rez'] > 0);
            $moj_event = ((int)$e['autor_id'] === $id_gracza);
        ?>
        <div class="wyd-card <?php echo htmlspecialchars($e['kolor_plakatu']); ?> <?php echo $juz_zaczal && !$juz_skonczyl ? 'live' : ''; ?><?php echo !empty($e['plakat_url']) ? ' z-plakatem' : ''; ?>" data-id="<?php echo (int)$e['id']; ?>">
            <div class="wyd-data">
                <?php if (!empty($e['plakat_url'])): ?>
                <div class="wyd-mini-plakat" style="background-image:url('<?php echo htmlspecialchars($e['plakat_url']); ?>')"></div>
                <?php endif; ?>
                <div class="dzien"><?php echo $dzien; ?></div>
                <div class="miesiac"><?php echo htmlspecialchars($miesiac_n); ?></div>
                <div class="czas"><?php echo htmlspecialchars($czas); ?></div>
                <div class="dzien-tyg"><?php echo htmlspecialchars($dzien_tyg); ?></div>
            </div>

            <div class="wyd-info">
                <div class="nazwa">
                    <span class="ikona"><?php echo htmlspecialchars($e['ikona_emoji'] ?: '✦'); ?></span>
                    <?php echo htmlspecialchars($e['nazwa']); ?>
                </div>
                <div class="meta-row">
                    <span>📍 <b><?php echo htmlspecialchars($sale_nazwy); ?></b></span>
                    <span>👥 <b><?php echo (int)$e['liczba_rez']; ?>/<?php echo (int)$e['max_miejsc']; ?></b> miejsc</span>
                    <span>✦ <?php echo htmlspecialchars($e['autor_login'] ?? 'Barman'); ?></span>
                    <?php if ($juz_zaczal && !$juz_skonczyl): ?>
                    <span style="color:var(--neon-red-hot)">● TRWA</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($e['opis'])): ?>
                <div class="opis"><?php echo nl2br(htmlspecialchars($e['opis'])); ?></div>
                <?php endif; ?>
            </div>

            <div class="wyd-akcje">
                <div class="wyd-rez-info">
                    <b><?php echo (int)$e['liczba_rez']; ?></b>
                    <?php echo $pelne ? 'pełne' : 'osób zapisanych'; ?>
                </div>

                <?php if ($juz_skonczyl): ?>
                    <div class="wyd-btn pelne">Zakończone</div>
                <?php elseif ($juz_zaczal): ?>
                    <a href="game.php?page=czat&sala=<?php echo htmlspecialchars($e['sala']); ?>" class="wyd-btn live-now">▶ Idź na event</a>
                <?php elseif ($moja_rez): ?>
                    <button type="button" class="wyd-btn zapisany" onclick="anulujRezerwacje(<?php echo (int)$e['id']; ?>)">✓ Idziesz</button>
                <?php elseif ($pelne): ?>
                    <div class="wyd-btn pelne">Pełne</div>
                <?php else: ?>
                    <button type="button" class="wyd-btn" onclick="zapiszSie(<?php echo (int)$e['id']; ?>)">Biorę udział</button>
                <?php endif; ?>

                <?php if (($moj_event || $jest_mg) && !$juz_skonczyl): ?>
                    <button type="button" class="wyd-btn-cancel wyd-btn" style="border-color:rgba(74,214,255,0.5);color:var(--neon-cyan)"
                        data-id="<?php echo (int)$e['id']; ?>"
                        data-nazwa="<?php echo htmlspecialchars($e['nazwa']); ?>"
                        data-opis="<?php echo htmlspecialchars($e['opis']); ?>"
                        data-sala="<?php echo htmlspecialchars($e['sala']); ?>"
                        data-data-startu="<?php echo date('Y-m-d\TH:i', strtotime($e['data_startu'])); ?>"
                        data-data-konca="<?php echo $e['data_konca'] ? date('Y-m-d\TH:i', strtotime($e['data_konca'])) : ''; ?>"
                        data-max-miejsc="<?php echo (int)$e['max_miejsc']; ?>"
                        data-kolor="<?php echo htmlspecialchars($e['kolor_plakatu']); ?>"
                        data-ikona="<?php echo htmlspecialchars($e['ikona_emoji']); ?>"
                        data-plakat="<?php echo htmlspecialchars($e['plakat_url'] ?: ''); ?>"
                        onclick="otworzEdycje(this)">
                        ✏ Edytuj
                    </button>
                    <button type="button" class="wyd-btn-cancel wyd-btn" onclick="anulujEvent(<?php echo (int)$e['id']; ?>, '<?php echo htmlspecialchars(addslashes($e['nazwa'])); ?>')">✕ Anuluj wydarzenie</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
// Color picker
document.querySelectorAll('.kolor-picker .kolor-opt').forEach(o => {
    o.addEventListener('click', () => {
        document.querySelectorAll('.kolor-opt').forEach(x => x.classList.remove('checked'));
        o.classList.add('checked');
        o.querySelector('input').checked = true;
    });
});

// FAZA 4: Plakat preview
const plakatInput = document.getElementById('plakat-input');
if (plakatInput) {
    plakatInput.addEventListener('change', (e) => {
        const f = e.target.files[0];
        const info = document.querySelector('.plakat-info');
        const preview = document.getElementById('plakat-preview');
        const previewImg = document.getElementById('plakat-preview-img');
        if (!f) {
            preview.style.display = 'none';
            info.textContent = 'Brak pliku — użyte będzie tło z koloru i ikony.';
            return;
        }
        if (f.size > 5 * 1024 * 1024) {
            alert('Plik za duży (max 5 MB)');
            plakatInput.value = '';
            return;
        }
        if (!['image/jpeg','image/png','image/webp'].includes(f.type)) {
            alert('Nieobsługiwany format. Użyj JPG, PNG lub WebP.');
            plakatInput.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            previewImg.src = ev.target.result;
            preview.style.display = 'inline-block';
            info.textContent = `Wybrano: ${f.name} (${(f.size/1024).toFixed(0)} KB)`;
        };
        reader.readAsDataURL(f);
    });
}

// Tworzenie eventu
const formTworz = document.getElementById('form-tworz-event');
if (formTworz) {
    formTworz.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(formTworz);
        fd.append('op', 'utworz');
        try {
            const res = await fetch('api/klub_eventy.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const d = await res.json();
            if (d.ok) {
                alert('✓ ' + (d.msg || 'Utworzono'));
                window.location.reload();
            } else {
                alert('Błąd: ' + (d.msg || 'nieznany'));
            }
        } catch (err) {
            alert('Brak połączenia z serwerem');
        }
    });
}

// Rezerwacja
window.zapiszSie = async function(id) {
    const fd = new FormData();
    fd.append('op', 'rezerwuj');
    fd.append('id', id);
    try {
        const res = await fetch('api/klub_eventy.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await res.json();
        if (d.ok) { window.location.reload(); }
        else alert('Błąd: ' + (d.msg || ''));
    } catch (err) { alert('Brak połączenia'); }
};

window.anulujRezerwacje = async function(id) {
    if (!confirm('Anulować swoją rezerwację?')) return;
    const fd = new FormData();
    fd.append('op', 'anuluj_rezerwacje');
    fd.append('id', id);
    try {
        const res = await fetch('api/klub_eventy.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await res.json();
        if (d.ok) { window.location.reload(); }
        else alert('Błąd: ' + (d.msg || ''));
    } catch (err) { alert('Brak połączenia'); }
};

window.anulujEvent = async function(id, nazwa) {
    const powod = prompt('Anulować wydarzenie „' + nazwa + '"?\nPodaj powód (opcjonalnie):');
    if (powod === null) return;
    const fd = new FormData();
    fd.append('op', 'anuluj');
    fd.append('id', id);
    fd.append('powod', powod);
    try {
        const res = await fetch('api/klub_eventy.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await res.json();
        if (d.ok) { alert('✓ ' + d.msg); window.location.reload(); }
        else alert('Błąd: ' + (d.msg || ''));
    } catch (err) { alert('Brak połączenia'); }
};

// ── EDYCJA WYDARZENIA (Faza 6) ───────────────────────────────
window.otworzEdycje = function(btn) {
    const d = btn.dataset;
    const modal = document.getElementById('edycja-modal');
    if (modal) modal.remove(); // usuń poprzedni jeśli był

    const m = document.createElement('div');
    m.id = 'edycja-modal';
    m.style.cssText = 'position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:20px;overflow-y:auto';

    const isoStartu = d.dataStartu || '';
    const isoKonca = d.dataKonca || '';

    m.innerHTML = `
    <div style="max-width:600px;width:100%;max-height:90vh;overflow-y:auto;background:linear-gradient(135deg,rgba(40,15,5,0.97),rgba(15,5,5,0.97));border:1px solid var(--neon-cyan);border-radius:2px;padding:24px 28px;box-shadow:0 20px 60px rgba(0,0,0,0.8),0 0 40px rgba(74,214,255,0.2)">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid rgba(74,214,255,0.2)">
            <h3 style="font-family:'Oswald',sans-serif;color:var(--neon-cyan);letter-spacing:3px;text-transform:uppercase;font-size:1.05em;margin:0">✏ Edycja wydarzenia</h3>
            <button onclick="document.getElementById('edycja-modal').remove()" style="background:transparent;border:1px solid var(--border-soft);color:var(--txt-mute);width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:.85em">✕</button>
        </div>
        <form id="form-edycja-event" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <input type="hidden" name="op" value="edytuj">
            <input type="hidden" name="id" value="${d.id}">

            <div style="grid-column:span 2">
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Nazwa</label>
                <input type="text" name="nazwa" value="${escapeAttr(d.nazwa)}" required maxlength="120" style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
            </div>

            <div>
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Data startu</label>
                <input type="datetime-local" name="data_startu" value="${isoStartu}" required style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
            </div>
            <div>
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Koniec (opcj.)</label>
                <input type="datetime-local" name="data_konca" value="${isoKonca}" style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
            </div>

            <div>
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Sala</label>
                <select name="sala" style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
                    ${['sala-glowna','sala-balowa','vip','taras','basen','silownia','sauna','masaze','bdsm','tyly','garderoba','lobby'].map(s =>
                        `<option value="${s}" ${s===d.sala?'selected':''}>${s}</option>`
                    ).join('')}
                </select>
            </div>
            <div>
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Max miejsc</label>
                <input type="number" name="max_miejsc" value="${d.maxMiejsc}" min="1" max="200" style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
            </div>

            <div>
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Ikona</label>
                <input type="text" name="ikona_emoji" value="${escapeAttr(d.ikona)}" maxlength="4" style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
            </div>
            <div>
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Kolor</label>
                <select name="kolor_plakatu" style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Open Sans',sans-serif;font-size:.9em;border-radius:2px;box-sizing:border-box">
                    ${['pink','gold','purple','red','cyan'].map(k =>
                        `<option value="${k}" ${k===d.kolor?'selected':''}>${k}</option>`
                    ).join('')}
                </select>
            </div>

            <div style="grid-column:span 2">
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Opis</label>
                <textarea name="opis" style="width:100%;min-height:100px;padding:8px 10px;background:rgba(0,0,0,0.6);border:1px solid var(--border-soft);color:#ddd;font-family:'Cormorant Garamond',serif;font-size:1em;border-radius:2px;box-sizing:border-box;resize:vertical">${escapeAttr(d.opis)}</textarea>
            </div>

            <div style="grid-column:span 2">
                <label style="display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;color:var(--neon-cyan);text-transform:uppercase;margin-bottom:4px">Plakat (.jpg/.png/.webp)</label>
                ${d.plakat ? `
                    <div style="margin-bottom:8px;padding:6px;border:1px solid var(--border-soft);border-radius:2px;display:flex;align-items:center;gap:8px">
                        <img src="${escapeAttr(d.plakat)}" alt="Aktualny plakat" style="width:60px;height:80px;object-fit:cover;border-radius:1px">
                        <span style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);flex:1">Aktualny plakat</span>
                        <label style="font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--neon-red-hot);cursor:pointer;letter-spacing:1px;text-transform:uppercase">
                            <input type="checkbox" name="usun_plakat" value="1" style="margin-right:4px"> Usuń
                        </label>
                    </div>
                ` : ''}
                <input type="file" name="plakat" accept="image/jpeg,image/png,image/webp" style="width:100%;padding:6px;background:rgba(0,0,0,0.4);border:1px dashed var(--border-soft);color:#ddd;font-size:.85em;border-radius:2px">
                <div style="font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.82em;color:var(--txt-mute);margin-top:4px">Pozostaw puste, aby zachować obecny plakat. Max 5 MB.</div>
            </div>

            <div style="grid-column:span 2;display:flex;gap:10px;margin-top:8px">
                <button type="submit" style="flex:1;padding:10px;background:rgba(74,214,255,0.15);color:var(--neon-cyan);border:1px solid var(--neon-cyan);font-family:'Oswald',sans-serif;font-size:.85em;letter-spacing:2.5px;text-transform:uppercase;cursor:pointer;border-radius:2px">✓ Zapisz zmiany</button>
                <button type="button" onclick="document.getElementById('edycja-modal').remove()" style="padding:10px 20px;background:transparent;border:1px solid var(--border-soft);color:var(--txt-dim);font-family:'Oswald',sans-serif;font-size:.85em;letter-spacing:2.5px;text-transform:uppercase;cursor:pointer;border-radius:2px">Anuluj</button>
            </div>
        </form>
    </div>`;
    document.body.appendChild(m);

    document.getElementById('form-edycja-event').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            const res = await fetch('api/klub_eventy.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const d = await res.json();
            if (d.ok) { alert('✓ ' + d.msg); window.location.reload(); }
            else alert('Błąd: ' + (d.msg || ''));
        } catch (err) { alert('Brak połączenia'); }
    });

    m.addEventListener('click', (e) => { if (e.target === m) m.remove(); });
};

function escapeAttr(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>