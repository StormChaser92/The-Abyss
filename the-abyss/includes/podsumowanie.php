<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/PODSUMOWANIE.PHP
   Zakończenie Opowieści: Wieść, PW (widełki wg poziomu postaci), gotówka,
   przedmiot fabularny, reputacja, Wada, Rekonwalescencja z Urazów, Oś Czasu.
   Działa od razu; Adminka ma 7 dni na przegląd (pages/przeglad.php).
   Swobodna: krótkie zakończenie, tylko Urazy → karta.
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/panel_mg.php';

const PD_WIDELKI = ['niski' => [1, 5], 'umiarkowany' => [3, 8], 'wysoki' => [5, 12], 'ekstremalny' => [8, 20]];
const PD_REP = ['elita' => 'Elita', 'ulica' => 'Ulica', 'syndykat' => 'Syndykat', 'wladze' => 'Władze', 'spoleczenstwo' => 'Społecz.'];
const PD_DNI_PRZEGLADU = 7;
const PD_DNI_REKONW = 30;
const PD_WADY = ["Brak Kończyny","Jednooki","Głuchy","Całkowita Ślepota","Oszpecony","Utykający","Hemofiliak","Astma","Krótkowidz","Daltonizm","Niedosłuch","Wolne Gojenie","Jąkanie","Migreny","Bezsenność",
    "Trauma Pourazowa","Depresja","Lęki Napadowe","Klaustrofobia","Lęk Wysokości","Lęk Tłumu","Paranoik","Tchórz","Furiat","Odludek","Naiwny","Mizantrop","Brak Empatii",
    "Nałogowiec","Hazardzista","Kleptomania","Zła Reputacja","Gadatliwy","Ociężały Umysł","Pechowiec","Leniwy","Słabeusz"];

/** Postacie do Podsumowania: gracze Opowieści + wszyscy, którzy byli w trackerze walki. */
function pd_postacie(mysqli $db, int $sid): array {
    $l = [];
    foreach (db_wiersze($db, "SELECT g.* FROM sesje_uczestnicy u JOIN gracze g ON g.id = u.gracz_id WHERE u.sesja_id = ? AND u.rola = 'Gracz' AND u.status_akceptacji = 'Zaakceptowany'", [$sid]) as $g) $l[(int)$g['id']] = $g;
    foreach (db_wiersze($db, "SELECT g.* FROM sesje_walka w JOIN gracze g ON g.id = CAST(SUBSTRING(w.klucz, 2) AS UNSIGNED) WHERE w.sesja_id = ? AND w.klucz LIKE 'g%'", [$sid]) as $g) $l[(int)$g['id']] = $g;
    $urazy = [];
    foreach (db_wiersze($db, "SELECT klucz, uraz FROM sesje_walka WHERE sesja_id = ? AND klucz LIKE 'g%'", [$sid]) as $w) $urazy[(int)substr($w['klucz'], 1)] = (int)$w['uraz'];
    foreach ($l as $id => &$g) $g['_uraz'] = $urazy[$id] ?? 0;
    return $l;
}

function pd_widelki(string $poziom): array { return PD_WIDELKI[$poziom] ?? PD_WIDELKI['niski']; }

/* ── OBSŁUGA ZAKOŃCZENIA ──────────────────────────────────────────── */
function pd_obsluz(mysqli $db, array $s, int $gid, bool $czy_mg): string {
    if (!isset($_POST['pd_zakoncz'])) return '';
    if (!$czy_mg || $s['status'] === 'Zakończona') return 'Tylko prowadzący może zakończyć tę Opowieść.';
    $sid = (int)$s['id']; $swob = $s['poziom'] === 'swobodna'; $P = pd_postacie($db, $sid);
    $rek = array_map('intval', (array)($_POST['rek'] ?? []));
    $notka = trim(mb_substr((string)($_POST['notatka_ogolna'] ?? ''), 0, 5000));
    $wt = trim(mb_substr((string)($_POST['wiesc_tytul'] ?? ''), 0, 160)); $wx = trim(mb_substr((string)($_POST['wiesc_tresc'] ?? ''), 0, 3000));
    if (!$swob && (mb_strlen($wt) < 5 || mb_strlen($wx) < 20)) return 'Wieść: tytuł min. 5 znaków, streszczenie min. 20.';

    $ok = db_tx($db, function () use ($db, $s, $sid, $gid, $swob, $P, $rek, $notka, $wt, $wx) {
        // Blokada podwójnego zakończenia
        if (db_zmien($db, "UPDATE sesje_rpg SET status = 'Zakończona', data_zakonczenia = NOW(), podsumowanie_mg = ?, walka_aktywna = 0,
                wiesc_tytul = ?, wiesc_tresc = ?, przeglad_status = ?, przeglad_do = ? WHERE id = ? AND status <> 'Zakończona'",
                [$notka, $swob ? null : $wt, $swob ? null : $wx, $swob ? 'brak' : 'otwarty', $swob ? null : date('Y-m-d H:i:s', time() + PD_DNI_PRZEGLADU * 86400), $sid]) !== 1) return false;
        foreach ($P as $id => $g) {
            $r = !empty($g['_uraz']) && $g['_uraz'] >= 3 && in_array($id, $rek, true);
            if ($r) db_q($db, "UPDATE gracze SET rekonwalescencja_do = GREATEST(COALESCE(rekonwalescencja_do, NOW()), NOW() + INTERVAL " . PD_DNI_REKONW . " DAY) WHERE id = ?", [$id]);
            if ($swob) {
                db_q($db, "INSERT INTO sesje_podsumowanie (sesja_id, gracz_id, mg_id, rekonwalescencja) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rekonwalescencja = VALUES(rekonwalescencja)", [$sid, $id, $gid, $r ? 1 : 0]);
                powiadom($db, $id, "Opowieść Swobodna <i>" . bz_h($s['tytul']) . "</i> została zakończona." . ($r ? " Rekonwalescencja: " . PD_DNI_REKONW . " dni." : '') . " <a href='game.php?page=pokoj_sesji&id=$sid' style='color:var(--neon-cyan)'>[ Przejdź ]</a>");
                continue;
            }
            $lv = (string)($_POST['lv'][$id] ?? $s['poziom']); if (!isset(PD_WIDELKI[$lv])) $lv = isset(PD_WIDELKI[$s['poziom']]) ? $s['poziom'] : 'niski';
            [$mn, $mx] = pd_widelki($lv);
            $pw = max($mn, min($mx, (int)($_POST['pw'][$id] ?? $mn)));
            $kasa = max(0, min(10000000, (int)($_POST['kasa'][$id] ?? 0)));
            $prz = trim(mb_substr((string)($_POST['prz'][$id] ?? ''), 0, 200));
            $oc = trim(mb_substr((string)($_POST['ocena'][$id] ?? ''), 0, 300));
            $os = trim(mb_substr((string)($_POST['os'][$id] ?? ''), 0, 1000));
            $wada = in_array($_POST['wada'][$id] ?? '', PD_WADY, true) ? $_POST['wada'][$id] : null;
            $rep = []; foreach (PD_REP as $k => $_) $rep[$k] = max(-3, min(3, (int)($_POST['rep'][$id][$k] ?? 0)));
            db_q($db, "INSERT INTO sesje_podsumowanie (sesja_id, gracz_id, mg_id, reputacja_elita, reputacja_ulica, reputacja_syndykat, reputacja_wladze, reputacja_spoleczenstwo, notatka_mg, konsekwencja_wada, pw, gotowka, przedmiot, poziom_postaci, os_wpis, rekonwalescencja)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE pw = VALUES(pw), gotowka = VALUES(gotowka)",
                [$sid, $id, $gid, $rep['elita'], $rep['ulica'], $rep['syndykat'], $rep['wladze'], $rep['spoleczenstwo'], $oc, $wada, $pw, $kasa, $prz ?: null, $lv, $os, $r ? 1 : 0]);
            // Karta: PW, gotówka, reputacja, Wada
            $row = db_wiersz($db, "SELECT reputacja_sesyjna, wady FROM gracze WHERE id = ?", [$id]);
            $old = $row['reputacja_sesyjna'] ? (json_decode($row['reputacja_sesyjna'], true) ?: []) : [];
            foreach ($rep as $k => $v) $old[$k] = (int)($old[$k] ?? 0) + $v;
            $wady = pm_cechy($row['wady'] ?? ''); if ($wada && !in_array($wada, $wady, true)) $wady[] = $wada;
            db_q($db, "UPDATE gracze SET pw = pw + ?, gotowka = gotowka + ?, reputacja_sesyjna = ?, wady = ? WHERE id = ?", [$pw, $kasa, json_encode($old, JSON_UNESCAPED_UNICODE), $wady ? implode(', ', $wady) : 'Brak', $id]);
            db_q($db, "INSERT INTO os_czasu (gracz_id, zrodlo, tresc, sesja_id) VALUES (?, 'opowiesc', ?, ?)", [$id, $os ?: 'Udział w Opowieści.', $sid]);
            powiadom($db, $id, "Opowieść <i>" . bz_h($s['tytul']) . "</i> zakończona: <b>+$pw PW</b>" . ($kasa ? ", +" . number_format($kasa, 0, ',', ' ') . " $" : '') . ($r ? ", Rekonwalescencja " . PD_DNI_REKONW . " dni" : '') . ". <a href='game.php?page=pokoj_sesji&id=$sid&zakladka=podsumowanie' style='color:var(--neon-cyan)'>[ Podsumowanie ]</a>");
        }
        db_q($db, "DELETE FROM sesje_walka WHERE sesja_id = ?", [$sid]);
        if (!$swob) foreach (rp_nadzorcy($db) as $n) powiadom($db, $n, "Nowe Podsumowanie do przeglądu: <i>" . bz_h($s['tytul']) . "</i> (" . PD_DNI_PRZEGLADU . " dni). <a href='game.php?page=przeglad&id=$sid' style='color:var(--neon-gold)'>[ Przegląd ]</a>");
        return true;
    });
    if (!$ok) return 'Ta Opowieść jest już zakończona.';
    echo "<script>location.href='game.php?page=pokoj_sesji&id=$sid&zakladka=podsumowanie';</script>"; exit;
}

/* ── WIDOK: FORMULARZ ─────────────────────────────────────────────── */
function pd_css(): void { ?>
<style>
.pd{display:flex;flex-direction:column;gap:14px;color:#f1ebf2}
.pd .lbl{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.6px;text-transform:uppercase;color:#cfc6d2}
.pd-box{background:rgba(8,4,10,.6);border:1px solid rgba(255,23,68,.22);padding:14px 16px;display:flex;flex-direction:column;gap:10px}
.pd-box h3{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.1em;letter-spacing:3px;text-transform:uppercase;color:#fff;margin:0}
.pd-box h3 small{font-family:'Rajdhani',sans-serif;font-size:.75em;letter-spacing:.5px;text-transform:none;color:#cfc6d2;margin-left:8px}
.pd input[type=text],.pd input[type=number],.pd select,.pd textarea{background:rgba(0,0,0,.55);border:1px solid rgba(255,23,68,.22);color:#fff;padding:8px 10px;width:100%;font-family:inherit;font-size:1em}
.pd textarea{min-height:80px;resize:vertical}
.pd-f{display:flex;flex-direction:column;gap:5px}
.pd-2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}
.pd-pc{border:1px solid rgba(255,255,255,.1);border-left:3px solid var(--lc);background:rgba(0,0,0,.4);padding:12px 14px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}
.pd-pc-h{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center}
.pd-pc-h b{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.15em;letter-spacing:1px}
.pd-pc-h select{width:auto}
.pd-c{display:flex;flex-direction:column;gap:9px;min-width:0}
.pd-pw{display:grid;grid-template-columns:auto minmax(0,1fr) 60px;gap:10px;align-items:center;font-family:'JetBrains Mono',monospace;color:#cfc6d2}
.pd-pw input[type=range]{accent-color:var(--lc)}
.pd-pw output{font-family:'Oswald',sans-serif;font-size:1.5em;color:#fff;text-align:right}
.pd-rep{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:5px}
.pd-rep label{display:flex;flex-direction:column;gap:3px;font-family:'JetBrains Mono',monospace;font-size:.66em;letter-spacing:1px;text-transform:uppercase;color:#cfc6d2;text-align:center}
.pd-rep input{text-align:center;padding:5px}
.pd-uraz{padding:9px 12px;border:1px solid #ff3d5e;background:rgba(255,23,68,.08);display:flex;gap:10px;align-items:flex-start;line-height:1.4}
.pd-uraz b{color:#ff3d5e}
.pd-hint{color:#cfc6d2;font-size:.92em;line-height:1.45;margin:0}
.pd-btn{padding:11px 18px;border:1px solid #ffd23d;background:rgba(255,210,61,.1);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer;align-self:flex-end}
.pd-btn:hover{background:#ffd23d;color:#000}
.pd-err{padding:10px 12px;border:1px solid #ff3d5e;color:#ff3d5e;font-family:'JetBrains Mono',monospace;font-size:.88em}
.pd-sum{display:flex;flex-direction:column;gap:6px;padding:12px 14px;border:1px solid rgba(255,23,68,.45);background:rgba(0,0,0,.45);margin-bottom:14px}
.pd-sum h4{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:1.6em;color:#fff;margin:0}
.pd-sum p{font-family:'Cormorant Garamond',serif;font-size:1.12em;line-height:1.5;color:#ece4ec;margin:0}
.pd-sum .pd-lista{display:flex;flex-wrap:wrap;gap:6px}
.pd-sum .pd-lista span{padding:3px 9px;border:1px solid rgba(255,255,255,.15);font-size:.92em;color:#cfc6d2}
.pd-sum .pd-lista b{color:#ffd23d}
@media (max-width:800px){.pd-pc,.pd-2{grid-template-columns:1fr}.pd-rep{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
<?php }

function pd_formularz(mysqli $db, array $s, string $tab, string $blad = ''): void {
    $h = 'rw_h'; $sid = (int)$s['id']; $swob = $s['poziom'] === 'swobodna'; $P = pd_postacie($db, $sid);
    pd_css();
    echo "<div id='tab-zakoncz' class='zakladka-tresc" . ($tab === 'zakoncz' ? ' aktywna' : '') . "'><form method='POST' class='pd' onsubmit=\"return confirm('Zakończyć Opowieść? Podsumowanie od razu trafi do graczy.')\">";
    echo "<input type='hidden' name='pd_zakoncz' value='1'>";
    if ($blad) echo "<div class='pd-err'>⚠ " . $h($blad) . "</div>";
    if ($swob) {
        echo "<div class='pd-box'><h3>Zakończenie <small>Opowieść Swobodna — bez PW, Wieści i Osi Czasu</small></h3><div class='pd-f'><span class='lbl'>Jak się skończyło (dla uczestników)</span><textarea name='notatka_ogolna'></textarea></div>";
        foreach ($P as $id => $g) if ($g['_uraz'] >= 3) echo "<label class='pd-uraz'><input type='checkbox' name='rek[]' value='$id' checked><span><b>" . $h($g['login']) . "</b>: Uraz " . RW_URAZ[min(4, $g['_uraz'])] . ". Na karcie: <b>Rekonwalescencja " . PD_DNI_REKONW . " dni</b>.</span></label>";
        echo "<button class='pd-btn' type='submit'>Zakończ Opowieść</button></div></form></div>"; return;
    }
    $L = RP_POZIOMY[$s['poziom']] ?? RP_POZIOMY['niski'];
    echo "<div class='pd-box'><h3>Wieść <small>publiczne streszczenie — Centrum i strona powitalna</small></h3>
      <div class='pd-f'><span class='lbl'>Tytuł</span><input type='text' name='wiesc_tytul' maxlength='160' required minlength='5'></div>
      <div class='pd-f'><span class='lbl'>Streszczenie</span><textarea name='wiesc_tresc' maxlength='3000' required minlength='20' placeholder='Co wydarzyło się w mieście — bez spoilerów dla innych Opowieści.'></textarea></div></div>";
    echo "<div class='pd-box'><h3>Notatka prowadzącego <small>dla uczestników</small></h3><textarea name='notatka_ogolna'></textarea></div>";
    echo "<div class='pd-box'><h3>Postacie <small>widełki PW zależą od poziomu przypisanego postaci</small></h3>";
    if (!$P) echo "<p class='pd-hint'>Brak zaakceptowanych graczy.</p>";
    foreach ($P as $id => $g) {
        $lv = isset(PD_WIDELKI[$s['poziom']]) ? $s['poziom'] : 'niski'; [$mn, $mx] = pd_widelki($lv);
        echo "<div class='pd-pc' style='--lc:" . $L['kolor'] . "' data-pd><div class='pd-pc-h'><b>" . $h($g['login']) . "</b><label style='display:flex;gap:8px;align-items:center'><span class='lbl'>Poziom dla postaci</span><select name='lv[$id]' data-pd-lv>";
        foreach (PD_WIDELKI as $k => [$a, $b]) echo "<option value='$k' data-min='$a' data-max='$b' data-kolor='" . RP_POZIOMY[$k]['kolor'] . "'" . ($k === $lv ? ' selected' : '') . ">" . RP_POZIOMY[$k]['n'] . " ($a–$b PW)</option>";
        echo "</select></label></div><div class='pd-c'>
          <div class='pd-f'><span class='lbl'>Punkty Wydarzeń</span><div class='pd-pw'><span data-pd-min>$mn</span><input type='range' name='pw[$id]' min='$mn' max='$mx' value='" . (int)round(($mn + $mx) / 2) . "' data-pd-pw><output>" . (int)round(($mn + $mx) / 2) . "</output></div></div>
          <div class='pd-f'><span class='lbl'>Ocena udziału</span><input type='text' name='ocena[$id]' maxlength='300' placeholder='Krótko: za co te PW'></div>
          <div class='pd-2'><div class='pd-f'><span class='lbl'>Gotówka $</span><input type='number' name='kasa[$id]' min='0' step='100' value='0'></div><div class='pd-f'><span class='lbl'>Przedmiot fabularny</span><input type='text' name='prz[$id]' maxlength='200' placeholder='opis, bez mechaniki'></div></div>
          <div class='pd-f'><span class='lbl'>Reputacja (−3…+3)</span><div class='pd-rep'>";
        foreach (PD_REP as $k => $n) echo "<label>$n<input type='number' name='rep[$id][$k]' min='-3' max='3' value='0'></label>";
        echo "</div></div></div><div class='pd-c'><div class='pd-f'><span class='lbl'>Konsekwencja: nowa Wada</span><select name='wada[$id]'><option value=''>—</option>";
        foreach (PD_WADY as $w) echo "<option>" . $h($w) . "</option>";
        echo "</select></div>";
        echo $g['_uraz'] >= 3 ? "<label class='pd-uraz'><input type='checkbox' name='rek[]' value='$id' checked><span>Z trackera walki: <b>Uraz " . RW_URAZ[min(4, $g['_uraz'])] . "</b>. Na karcie: <b>Rekonwalescencja " . PD_DNI_REKONW . " dni</b>.</span></label>"
                              : "<p class='pd-hint'>" . ($g['_uraz'] ? 'Uraz ' . RW_URAZ[$g['_uraz']] . ' — nie przechodzi na kartę.' : 'Bez Urazów z walki.') . "</p>";
        echo "<div class='pd-f'><span class='lbl'>Wpis na Oś Czasu</span><textarea name='os[$id]' maxlength='1000' style='min-height:70px' placeholder='Co ta postać zrobiła w tej Opowieści'></textarea></div></div></div>";
    }
    echo "</div><p class='pd-hint'>Podsumowanie działa od razu. Adminka Fabularna ma " . PD_DNI_PRZEGLADU . " dni na przegląd i poprawki.</p><button class='pd-btn' type='submit'>Zakończ i opublikuj</button></form></div>";
    echo "<script>document.querySelectorAll('[data-pd]').forEach(c=>{const s=c.querySelector('[data-pd-lv]'),r=c.querySelector('[data-pd-pw]'),o=r.nextElementSibling,m=c.querySelector('[data-pd-min]');
      s.onchange=()=>{const x=s.selectedOptions[0];r.min=x.dataset.min;r.max=x.dataset.max;r.value=Math.round((+x.dataset.min+ +x.dataset.max)/2);o.textContent=r.value;m.textContent=x.dataset.min;c.style.setProperty('--lc',x.dataset.kolor)};
      r.oninput=()=>o.textContent=r.value;s.onchange()});</script>";
}

/** Nad starym widokiem Podsumowania: Wieść + PW/gotówka postaci. */
function pd_widok(mysqli $db, array $s): void {
    $h = 'rw_h';
    $w = db_wiersze($db, "SELECT p.*, g.login FROM sesje_podsumowanie p JOIN gracze g ON g.id = p.gracz_id WHERE p.sesja_id = ? ORDER BY g.login", [(int)$s['id']]);
    pd_css();
    echo "<div class='pd-sum pd'>";
    if (!empty($s['wiesc_tytul'])) echo "<span class='lbl'>Wieść</span><h4>" . $h($s['wiesc_tytul']) . "</h4><p>" . nl2br($h($s['wiesc_tresc'])) . "</p>";
    echo "<div class='pd-lista'>";
    foreach ($w as $r) echo "<span>" . $h($r['login']) . ($s['poziom'] !== 'swobodna' ? " · " . (RP_POZIOMY[$r['poziom_postaci']]['n'] ?? '') . " · <b>+" . (int)$r['pw'] . " PW</b>" . ((int)$r['gotowka'] ? ' · ' . number_format((int)$r['gotowka'], 0, ',', ' ') . ' $' : '') . ($r['przedmiot'] ? ' · ' . $h($r['przedmiot']) : '') : '') . ((int)$r['rekonwalescencja'] ? ' · Rekonwalescencja' : '') . "</span>";
    echo "</div></div>";
}

/* ── WIEŚCI I OŚ CZASU ────────────────────────────────────────────── */
function pd_wiesci(mysqli $db, int $limit = 30): array {
    return db_wiersze($db, "SELECT s.id, s.wiesc_tytul, s.wiesc_tresc, s.poziom, s.data_zakonczenia, g.login AS prow,
        (SELECT GROUP_CONCAT(gg.login ORDER BY gg.login SEPARATOR ', ') FROM sesje_podsumowanie p JOIN gracze gg ON gg.id = p.gracz_id WHERE p.sesja_id = s.id) AS uczestnicy
        FROM sesje_rpg s JOIN gracze g ON g.id = s.mg_id WHERE s.status = 'Zakończona' AND s.wiesc_tytul IS NOT NULL AND s.typ_opowiesci <> 'swobodna'
        ORDER BY s.data_zakonczenia DESC LIMIT " . max(1, min(100, $limit)));
}

/** Widżet na stronę powitalną: ostatnie 3 Wieści. */
function pd_widget(mysqli $db): void {
    $l = pd_wiesci($db, 3); if (!$l) return; $h = 'rw_h';
    echo "<div style='margin:18px 0;padding:14px 16px;border:1px solid rgba(255,23,68,.45);background:rgba(8,4,10,.7);color:#f1ebf2'><div style=\"font-family:'Oswald',sans-serif;letter-spacing:3px;text-transform:uppercase;margin-bottom:6px\">Wieści z miasta</div>";
    foreach ($l as $w) echo "<a href='game.php?page=centrum&widok=wiesci#w" . (int)$w['id'] . "' style='display:flex;flex-direction:column;gap:2px;padding:8px 0;border-top:1px dotted rgba(255,255,255,.12);text-decoration:none'><b style='color:#fff'>" . $h($w['wiesc_tytul']) . "</b><small style='color:#cfc6d2'>" . date('d.m', strtotime($w['data_zakonczenia'])) . " · " . $h(RP_POZIOMY[$w['poziom']]['n'] ?? '') . "</small></a>";
    echo "<a href='game.php?page=centrum&widok=wiesci' style='color:var(--neon-red-hot);font-size:.9em'>Wszystkie Wieści ›</a></div>";
}

/** Oś Czasu na karcie postaci. */
function pd_os_czasu(mysqli $db, int $gid): void {
    $h = 'rw_h';
    $g = db_wiersz($db, "SELECT pw, rekonwalescencja_do, TIMESTAMPDIFF(DAY, NOW(), rekonwalescencja_do) AS dni FROM gracze WHERE id = ?", [$gid]);
    $l = db_wiersze($db, "SELECT o.*, s.tytul, s.poziom AS s_poziom, p.pw, p.gotowka, p.przedmiot, p.poziom_postaci, p.konsekwencja_wada, p.rekonwalescencja
        FROM os_czasu o LEFT JOIN sesje_rpg s ON s.id = o.sesja_id LEFT JOIN sesje_podsumowanie p ON p.sesja_id = o.sesja_id AND p.gracz_id = o.gracz_id
        WHERE o.gracz_id = ? ORDER BY o.data DESC LIMIT 50", [$gid]);
    echo "<div class='blok'><div class='blok-tytul'>🕰 Oś Czasu <span class='note'>PW łącznie: <b style='color:#fff'>" . (int)$g['pw'] . "</b></span></div>";
    if ($g['rekonwalescencja_do'] && (int)$g['dni'] >= 0) {
        $d = (int)$g['dni']; $pr = max(0, min(100, round((PD_DNI_REKONW - $d) / PD_DNI_REKONW * 100)));
        echo "<div style='display:flex;gap:14px;align-items:center;padding:10px 12px;margin-bottom:12px;border:1px solid #ff3d5e;background:rgba(255,23,68,.08)'><b style=\"font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;color:#ff3d5e\">Rekonwalescencja</b><div style='flex:1;height:6px;background:rgba(255,255,255,.1)'><i style='display:block;height:100%;width:$pr%;background:#ff3d5e'></i></div><span style='color:#cfc6d2;font-size:.9em'>zostało $d z " . PD_DNI_REKONW . " dni · w Opowieściach „Uraz lekki”</span></div>";
    }
    if (!$l) echo "<p style='color:#cfc6d2'>Brak wpisów. Pojawią się po zakończeniu Opowieści i egzaminach na Uniwersytecie.</p>";
    echo "<div style='display:flex;flex-direction:column;gap:10px;padding-left:22px;border-left:2px solid rgba(255,23,68,.5)'>";
    foreach ($l as $o) {
        $kol = $o['zrodlo'] === 'uniwersytet' ? '#4ad6ff' : (RP_POZIOMY[$o['poziom_postaci'] ?: ($o['s_poziom'] ?? '')]['kolor'] ?? '#ff3d5e');
        $meta = $o['zrodlo'] === 'opowiesc' && $o['tytul'] ? $h($o['tytul']) . " · " . $h(RP_POZIOMY[$o['poziom_postaci']]['n'] ?? '') . " · <b style='color:#fff'>+" . (int)$o['pw'] . " PW</b>" . ((int)$o['gotowka'] ? ' · ' . number_format((int)$o['gotowka'], 0, ',', ' ') . ' $' : '') . ($o['przedmiot'] ? ' · ' . $h($o['przedmiot']) : '') . ($o['konsekwencja_wada'] ? ' · Wada: ' . $h($o['konsekwencja_wada']) : '') . ((int)$o['rekonwalescencja'] ? ' · Rekonwalescencja' : '')
              : ($o['zrodlo'] === 'uniwersytet' ? 'Uniwersytet' : $h($o['zrodlo']));
        echo "<div style='position:relative;padding:10px 12px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.08)'><i style='position:absolute;left:-29px;top:13px;width:12px;height:12px;transform:rotate(45deg);background:$kol;box-shadow:0 0 8px $kol'></i>"
           . "<div style='display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap'><span style=\"font-family:'Oswald',sans-serif;letter-spacing:1px;color:#fff\">$meta</span><small style=\"font-family:'JetBrains Mono',monospace;color:#cfc6d2\">" . date('d.m.Y', strtotime($o['data'])) . "</small></div>"
           . "<p style='color:#cfc6d2;margin:4px 0 0;line-height:1.45'>" . $h($o['tresc']) . "</p></div>";
    }
    echo "</div></div>";
}
