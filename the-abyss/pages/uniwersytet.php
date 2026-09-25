<?php
require_once "db.php";
require_once __DIR__ . '/../config/zawody.php';
require_once __DIR__ . '/../config/uniwersytet.php';
require_once __DIR__ . '/../includes/uni_lamiglowki.php';
$id_gracza = (int)$_SESSION['id_gracza'];

/* ═══════════════════════════════════════════════════════════════════════
   UNIWERSYTET v2 — Akademia Nauk
   Łamigłówka otwiera zajęcia, zajęcia trwają 8 h (probówka), jedne na dobę.
   Ostatnie zajęcia stopnia = egzamin pisemny (AI). Stopnie per kierunek.
   ═══════════════════════════════════════════════════════════════════════ */

// Klucz OpenAI — bez niego egzamin dostaje losową ocenę 5–10 (jak dotąd).
$klucz_api_google = "AIzaSyDHBrN8G9ajQEg4MEWV3a3KnDF_2BhLwMU";

$UNI_IKONY = [
    'medycyna' => '<path d="M12 3v18"/><path d="M8 6c0-2 8-2 8 0s-8 3-8 5 8 3 8 5-8 2-8 4"/><path d="M5 5c2 0 3 1 4 2M19 5c-2 0-3 1-4 2"/>',
    'inzynieria' => '<path d="M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2h12.4a1.5 1.5 0 0 0 1.3-2L14 9V3"/><path d="M7 15h10"/>',
    'ekonomia' => '<path d="M3 16l2 4h14l2-4z"/><path d="M6 16V11h12v5"/><path d="M9 11V7h6v4"/><path d="M11 7V4h2"/>',
    'prawo' => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7l-3 6a3 3 0 0 0 6 0zM19 7l-3 6a3 3 0 0 0 6 0z"/>',
    'sztuka' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
    'cybernetyka' => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/>',
    'humanistyka' => '<path d="M2 5c3-1 7-1 10 1 3-2 7-2 10-1v14c-3-1-7-1-10 1-3-2-7-2-10-1z"/><path d="M12 6v14"/>',
    'kryminologia' => '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/><path d="M13 8l-3 4h4l-3 4"/>',
    'weterynaria' => '<path d="M12 3c3 4 5 7 5 10a5 5 0 0 1-10 0c0-3 2-6 5-10z"/><path d="M3 19c3 2 6 2 9 0s6-2 9 0"/>',
    'farmacja' => '<path d="M6 4h12l-1 6a5 5 0 0 1-10 0z"/><path d="M12 15v5M8 20h8"/><path d="M8 7h8"/>',
    'historia' => '<path d="M3 9l9-5 9 5z"/><path d="M5 9v9M9.5 9v9M14.5 9v9M19 9v9M3 21h18M4 18h16"/>',
    'lotnictwo' => '<path d="M2 13l20-8-6 16-4-6-6-2z"/><path d="M12 15l4-6"/>',
    'teologia' => '<path d="M2 6c3-1 7-1 10 1 3-2 7-2 10-1v13c-3-1-7-1-10 1-3-2-7-2-10-1z"/><path d="M12 7v13"/><path d="M6 10h3M7.5 8.5v4"/>',
    'zdrowie' => '<path d="M12 20s-8-5-8-11a4 4 0 0 1 8-1 4 4 0 0 1 8 1c0 6-8 11-8 11z"/><path d="M5 12h4l1.5-3 2 5 1.5-2H19"/>',
    'biologia' => '<path d="M5 19C5 10 11 5 20 4c-1 9-6 15-15 15z"/><path d="M5 19l8-8"/>',
    'architektura' => '<path d="M12 3l-7 18M12 3l7 18"/><circle cx="12" cy="4" r="1.5"/><path d="M7 15h10"/>',
    'awf' => '<path d="M3 10v4M6 8v8M18 8v8M21 10v4M6 12h12"/>',
    'teatr' => '<path d="M9 18V6l11-2v12"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
    'media' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/>',
    'psychologia' => '<path d="M9 21v-4c-3-1-5-4-5-7a8 8 0 0 1 16 0c0 1-1 2 0 3l1 2h-2v3h-3v3"/><path d="M10 8a2 2 0 1 1 3 2"/>',
];

function uv_stan(mysqli $db, int $gid): array {
    $g = db_wiersz($db, "SELECT gotowka, energia_aktualna, energia_max, zalety,
        UNIX_TIMESTAMP(uni_ostatnie_zajecia) AS ost, UNIX_TIMESTAMP() AS teraz FROM gracze WHERE id = ?", [$gid]);
    $p = [];
    foreach (db_wiersze($db, "SELECT kierunek, stopien, zajecia FROM uni_postep WHERE gracz_id = ?", [$gid]) as $w)
        $p[$w['kierunek']] = ['s' => (int)$w['stopien'], 'z' => (int)$w['zajecia']];
    $akt = db_wiersz($db, "SELECT kierunek, UNIX_TIMESTAMP(start) AS s, UNIX_TIMESTAMP(koniec) AS k FROM uni_zajecia WHERE gracz_id = ?", [$gid]);
    $do_nast = $g['ost'] ? max(0, (int)$g['ost'] + UNI_ODSTEP_S - (int)$g['teraz']) : 0;
    return ['g' => $g, 'p' => $p, 'akt' => $akt, 'do_nast' => $do_nast, 'wolne' => !$akt && $do_nast === 0, 'teraz' => (int)$g['teraz']];
}
function uv_p(array $st, string $kid): array { return $st['p'][$kid] ?? ['s' => 0, 'z' => 0]; }
/** Stan kierunku: null gdy wszystko ukończone, inaczej [stopień w toku 1–3, zaliczone, wymagane, czy_egzamin]. */
function uv_kier(array $st, string $kid): ?array {
    $p = uv_p($st, $kid);
    if ($p['s'] >= 3) return null;
    $nr = $p['s'] + 1; $w = uni_wymagane_zajecia($kid, $nr);
    return [$nr, $p['z'], $w, $p['z'] >= $w - 1];
}

$st = uv_stan($polaczenie, $id_gracza);
$komunikat = ''; $klasa_kom = ''; $lam_js = null; $egz = null;
$akcja = $_POST['akcja'] ?? '';
$kid   = (string)($_POST['kierunek'] ?? '');

// ── 1. START ŁAMIGŁÓWKI ─────────────────────────────────────────────
if ($akcja === 'lam_start' && isset($UNI_KIERUNKI[$kid])) {
    $k = uv_kier($st, $kid);
    if (!$st['wolne'])                       { $komunikat = 'Dzisiejsze zajęcia są już za Tobą albo trwają.'; $klasa_kom = 'err'; }
    elseif (!$k || $k[3])                    { $komunikat = 'Na tym kierunku czeka egzamin albo wszystko jest ukończone.'; $klasa_kom = 'err'; }
    elseif ($st['g']['gotowka'] < uni_czesne($kid, $k[0]) || $st['g']['energia_aktualna'] < $UNI_KIERUNKI[$kid]['energia']) { $komunikat = 'Brak środków na czesne albo za mało energii.'; $klasa_kom = 'err'; }
    else {
        $d = uni_lam_losuj($k[0]);
        $_SESSION['uni_lam'] = ['k' => $kid, 'nr' => $k[0], 'z' => $k[1], 'd' => $d];
        $lam_js = uni_lam_publiczne($d) + ['kolor' => $UNI_KIERUNKI[$kid]['kolor']];
    }
}

// ── 2. ROZWIĄZANIE → START ZAJĘĆ ────────────────────────────────────
if ($akcja === 'lam_rozwiaz') {
    $s = $_SESSION['uni_lam'] ?? null;
    unset($_SESSION['uni_lam']);
    $odp = json_decode((string)($_POST['odp'] ?? ''), true);
    $k = $s ? uv_kier($st, $s['k']) : null;
    if (!$s || !$k || $k[0] !== $s['nr'] || $k[1] !== $s['z'] || $k[3]) { $komunikat = 'Łamigłówka wygasła. Zacznij jeszcze raz.'; $klasa_kom = 'err'; }
    elseif (!uni_lam_sprawdz($s['d'], $odp))  { $komunikat = 'Rozwiązanie się nie zgadza.'; $klasa_kom = 'err'; }
    else {
        $kasa = uni_czesne($s['k'], $s['nr']); $en = (int)$UNI_KIERUNKI[$s['k']]['energia'];
        $polaczenie->begin_transaction();
        try {
            // Warunek „raz na dobę” i opłata w jednym UPDATE — dwa kliknięcia nie przejdą oba.
            $ok = db_zmien($polaczenie, "UPDATE gracze SET gotowka = gotowka - ?, energia_aktualna = energia_aktualna - ?, uni_ostatnie_zajecia = NOW()
                WHERE id = ? AND gotowka >= ? AND energia_aktualna >= ? AND (uni_ostatnie_zajecia IS NULL OR uni_ostatnie_zajecia <= NOW() - INTERVAL ? SECOND)",
                [$kasa, $en, $id_gracza, $kasa, $en, UNI_ODSTEP_S]) === 1;
            if ($ok) db_zmien($polaczenie, "INSERT INTO uni_zajecia (gracz_id, kierunek, start, koniec) VALUES (?, ?, NOW(), NOW() + INTERVAL ? SECOND)", [$id_gracza, $s['k'], UNI_CZAS_ZAJEC_S]);
            $ok ? $polaczenie->commit() : $polaczenie->rollback();
        } catch (Throwable $e) { $polaczenie->rollback(); $ok = false; }
        if ($ok) { echo "<script>location.href='game.php?page=uniwersytet&start=1';</script>"; exit; }
        $komunikat = 'Nie udało się rozpocząć zajęć — odśwież stronę.'; $klasa_kom = 'err';
    }
}

// ── 3. ZALICZENIE PEŁNEJ PROBÓWKI ───────────────────────────────────
if ($akcja === 'zalicz' && $st['akt'] && $st['teraz'] >= (int)$st['akt']['k']) {
    $kz = $st['akt']['kierunek'];
    if (db_zmien($polaczenie, "DELETE FROM uni_zajecia WHERE gracz_id = ? AND koniec <= NOW()", [$id_gracza]) === 1) {
        db_zmien($polaczenie, "INSERT INTO uni_postep (gracz_id, kierunek, stopien, zajecia) VALUES (?, ?, 0, 1)
            ON DUPLICATE KEY UPDATE zajecia = zajecia + 1", [$id_gracza, $kz]);
    }
    echo "<script>location.href='game.php?page=uniwersytet&zal=" . urlencode($kz) . "';</script>"; exit;
}

// ── 4. EGZAMIN — pytanie ────────────────────────────────────────────
if ($akcja === 'egz_start' && isset($UNI_KIERUNKI[$kid])) {
    $k = uv_kier($st, $kid);
    if ($st['wolne'] && $k && $k[3]) {
        $pula = $UNI_KIERUNKI[$kid]['pytania'];
        $_SESSION['uni_egz'] = ['k' => $kid, 'nr' => $k[0], 'q' => $pula[array_rand($pula)]];
        $egz = $_SESSION['uni_egz'];
    } else { $komunikat = 'Egzamin nie jest teraz dostępny.'; $klasa_kom = 'err'; }
}

// ── 5. EGZAMIN — odpowiedź i ocena AI ───────────────────────────────
if ($akcja === 'egz_wyslij') {
    $e = $_SESSION['uni_egz'] ?? null;
    unset($_SESSION['uni_egz']);
    $odp = trim((string)($_POST['odpowiedz'] ?? ''));
    $k = $e ? uv_kier($st, $e['k']) : null;
    // Egzamin to dzisiejsze zajęcia: zajmujemy dobę zanim zapytamy AI.
    $zajete = $e && $k && $k[3] && $k[0] === $e['nr'] && $odp !== '' && db_zmien($polaczenie,
        "UPDATE gracze SET uni_ostatnie_zajecia = NOW() WHERE id = ? AND (uni_ostatnie_zajecia IS NULL OR uni_ostatnie_zajecia <= NOW() - INTERVAL ? SECOND)
         AND NOT EXISTS (SELECT 1 FROM uni_zajecia WHERE gracz_id = ?)", [$id_gracza, UNI_ODSTEP_S, $id_gracza]) === 1;
    if (!$zajete) { $komunikat = 'Ten egzamin już się odbył albo nie jest dostępny.'; $klasa_kom = 'err'; }
    else {
        $kd = $UNI_KIERUNKI[$e['k']]; $tytul = uni_tytul($e['k'], $e['nr']);
        $ocena = 0; $komentarz = '';
        if ($klucz_api_openai === "TUTAJ_WKLEJ_SWOJ_KLUCZ") {
            $ocena = random_int(5, 10);
            $komentarz = 'Brak klucza API — ocena losowa. Wklej klucz w pages/uniwersytet.php.';
        } else {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $klucz_api_openai],
                CURLOPT_POSTFIELDS => json_encode(['model' => 'gpt-3.5-turbo', 'temperature' => 0.7, 'messages' => [
                    ['role' => 'system', 'content' => "Jesteś surowym dziekanem wydziału '{$kd['nazwa']}' na cyberpunkowym uniwersytecie The Abyss. Egzaminujesz studenta na stopień '$tytul'. Wymagania rosną ze stopniem (Licencjat, Magister, Doktor)."],
                    ['role' => 'user', 'content' => "Pytanie: {$e['q']}\n\nOdpowiedź studenta: $odp\n\nOceń merytorycznie i pod kątem wczucia się w rolę w skali 1–10. Zwróć CZYSTY JSON: {\"ocena\": 8, \"komentarz\": \"krótki mroczny komentarz\"}."],
                ]])]);
            $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
            $j = json_decode($r['choices'][0]['message']['content'] ?? '', true);
            $ocena = (int)($j['ocena'] ?? 0);
            $komentarz = (string)($j['komentarz'] ?? 'Błąd łączności z matrycą Dziekana — uznano za próbę oszustwa.');
        }
        if ($ocena >= UNI_EGZAMIN_PROG) {
            db_zmien($polaczenie, "INSERT INTO uni_postep (gracz_id, kierunek, stopien, zajecia) VALUES (?, ?, 1, 0)
                ON DUPLICATE KEY UPDATE stopien = stopien + 1, zajecia = 0", [$id_gracza, $e['k']]);
            db_zmien($polaczenie, "INSERT INTO os_czasu (gracz_id, zrodlo, tresc) VALUES (?, 'uniwersytet', ?)",
                [$id_gracza, "Uzyskał(a) tytuł: $tytul (Akademia Nauk, ocena $ocena/10)."]);
            $komunikat = "EGZAMIN ZDANY ($ocena/10). Tytuł: $tytul · +" . UNI_PU_ZA_STOPIEN . " PU · wpis na Oś Czasu. „" . $komentarz . "”"; $klasa_kom = 'gold';
        } else {
            db_zmien($polaczenie, "UPDATE gracze SET energia_aktualna = GREATEST(0, energia_aktualna - ?) WHERE id = ?", [UNI_EGZAMIN_KARA_EN, $id_gracza]);
            $komunikat = "EGZAMIN OBLANY ($ocena/10). −" . UNI_EGZAMIN_KARA_EN . " EN, kolejna próba jutro. „" . $komentarz . "”"; $klasa_kom = 'err';
        }
    }
}

if (isset($_GET['start'])) { $komunikat = 'Zajęcia rozpoczęte — probówka napełni się za ' . (UNI_CZAS_ZAJEC_S / 3600) . ' h.'; }
if (isset($_GET['zal']) && isset($UNI_KIERUNKI[$_GET['zal']])) { $komunikat = 'Zaliczone zajęcia: ' . $UNI_KIERUNKI[$_GET['zal']]['nazwa'] . '.'; }

$st = uv_stan($polaczenie, $id_gracza);   // po zapisach
$pu_uni = uni_pu($polaczenie, $id_gracza);
$ma_lic = uni_ma_licencjat($polaczenie, $id_gracza);
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$zl = fn($v) => number_format((int)$v, 0, ',', ' ');
$TARCZA = '<svg viewBox="0 0 18 22"><path d="M9 1l7 3v7c0 5-3 8-7 10-4-2-7-5-7-10V4z"/></svg>';
$USD = '<svg class="usd" viewBox="0 0 24 24"><path d="M12 2v20M17 6c-1-2-9-2-9 2s9 2 9 6-8 4-9 2"/></svg>';
$BOLT = '<svg class="bolt" viewBox="0 0 24 24"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg>';
$WAVE = '<svg class="wave%s" viewBox="0 0 200 12" preserveAspectRatio="none"><path d="M0 6 Q12.5 0 25 6 T50 6 T75 6 T100 6 T125 6 T150 6 T175 6 T200 6 V12 H0Z"/></svg>';

// Dwa kierunki, z których liczą się PU
$top = $st['p']; uasort($top, fn($a, $b) => $b['s'] <=> $a['s']);
$top_pu = array_keys(array_filter(array_slice($top, 0, UNI_PU_MAKS_KIERUNKOW, true), fn($x) => $x['s'] > 0));
?>
<link rel="stylesheet" href="css/uniwersytet.css">
<div class="uv">
<header class="u-head">
  <div><div class="lbl">5th Ave · Uptown</div><h1>Akademia Nauk</h1><p>Rozwiąż łamigłówkę, żeby zacząć zajęcia. Potem zostaje tylko czekać, aż probówka się wypełni. Jedne zajęcia dziennie, na jednym kierunku naraz.</p></div>
  <div class="u-stats">
    <div class="st"><span class="lbl">Gotówka</span><b class="g"><?php echo $zl($st['g']['gotowka']); ?> $</b></div>
    <div class="st"><span class="lbl">Energia</span><b><?php echo (int)$st['g']['energia_aktualna']; ?> / <?php echo (int)$st['g']['energia_max']; ?></b></div>
    <div class="st"><span class="lbl">PU z Uniwersytetu</span><b><?php echo $pu_uni; ?> / <?php echo UNI_PU_ZA_STOPIEN * 3 * UNI_PU_MAKS_KIERUNKOW; ?></b></div>
    <div class="st"><span class="lbl">Zaleta „Wykształcony”</span><b class="<?php echo $ma_lic ? 'ok' : 'no'; ?>"><?php echo $ma_lic ? 'dostępna' : 'wymaga Licencjatu'; ?></b></div>
  </div>
</header>

<?php if ($komunikat): ?><div class="msg <?php echo $klasa_kom; ?>"><?php echo $h($komunikat); ?></div><?php endif; ?>

<section class="rack">
  <div class="rack-h"><h2>Stojak — zajęcia w toku</h2><span class="lbl"><?php
    echo $st['akt'] ? 'Trwają zajęcia — kolejne po ich zakończeniu i upływie doby od startu' : ($st['do_nast'] > 0 ? 'Dzisiejsze zajęcia za Tobą' : 'Możesz dziś rozpocząć jedne zajęcia'); ?></span></div>
  <div class="tubes">
  <?php if ($st['akt']):
      $a = $st['akt']; $kd = $UNI_KIERUNKI[$a['kierunek']] ?? null; $ks = uv_kier($st, $a['kierunek']);
      $pr = min(1, ($st['teraz'] - $a['s']) / max(1, $a['k'] - $a['s'])); $done = $pr >= 1; ?>
    <div class="tb<?php echo $done ? ' done' : ''; ?>" style="--k:<?php echo $kd['kolor'] ?? '#ff3d5e'; ?>" data-start="<?php echo (int)$a['s']; ?>" data-koniec="<?php echo (int)$a['k']; ?>">
      <div class="tb-cork"></div>
      <div class="tb-glass"><div class="liq" style="height:<?php echo max(4, round($pr * 100)); ?>%"><?php printf($WAVE, ''); printf($WAVE, ' w2'); for ($i = 0; $i < 4; $i++) echo '<span class="bub" style="left:' . (12 + $i * 9) . 'px;animation-delay:' . ($i * .8) . 's"></span>'; ?></div></div>
      <div class="tb-info"><b><?php echo $h($kd['nazwa'] ?? $a['kierunek']); ?></b>
        <?php if ($ks): ?><small><?php echo $UNI_STOPNIE[$ks[0]]['nazwa']; ?> · zajęcia <?php echo $ks[1] + 1; ?>/<?php echo $ks[2]; ?></small><?php endif; ?>
        <?php if ($done): ?>
          <form method="POST" action="game.php?page=uniwersytet"><input type="hidden" name="akcja" value="zalicz"><button type="submit" class="tb-btn">Zalicz zajęcia</button></form>
        <?php else: ?>
          <span class="tb-t">--:--:--</span><small class="tb-p"><?php echo floor($pr * 100); ?>%</small>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($st['do_nast'] > 0): ?>
    <div class="tb empty"><div class="tb-cork" style="opacity:.35"></div><div class="tb-glass"></div><div class="tb-info"><b>Sale zamknięte</b><small>Kolejne zajęcia za</small><span class="tb-t" style="--k:var(--brass-hi)" data-cd="<?php echo $st['teraz'] + $st['do_nast']; ?>">--:--:--</span></div></div>
  <?php else: ?>
    <div class="tb empty"><div class="tb-cork" style="opacity:.35"></div><div class="tb-glass"></div><div class="tb-info"><b>Wolne miejsce</b><small>Wybierz kierunek poniżej i rozwiąż łamigłówkę</small></div></div>
  <?php endif; ?>
  </div>
</section>

<section style="display:flex;flex-direction:column;gap:12px">
  <div class="sec-h"><h2>Dyplomy</h2><span class="lbl">PU liczone z <?php echo UNI_PU_MAKS_KIERUNKOW; ?> kierunków z najwyższymi stopniami (złote)</span></div>
  <div class="dipl">
  <?php $jest = false; foreach ($UNI_KIERUNKI as $id => $kd) { $s = uv_p($st, $id)['s']; for ($i = 1; $i <= $s; $i++) { $jest = true; $pu = in_array($id, $top_pu, true); ?>
    <div class="dp<?php echo $pu ? ' pu' : ''; ?>" style="--k:<?php echo $kd['kolor']; ?>"><b><?php echo $h(uni_tytul($id, $i)); ?></b><small><?php echo $pu ? '+' . UNI_PU_ZA_STOPIEN . ' PU · ' : ''; ?>odblokowuje: <?php echo $UNI_STOPNIE[$i]['etapy']; ?></small></div>
  <?php } } if (!$jest) echo '<span class="dp-empty">// Brak dyplomów</span>'; ?>
  </div>
</section>

<section style="display:flex;flex-direction:column;gap:12px">
  <div class="sec-h"><div class="sec-t"><svg viewBox="0 0 24 24"><path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/><path d="M22 9v6"/></svg>Kierunki studiów <i>///</i></div><span class="lbl">Licencjat → Magister (×1,5 zajęć, ×2 czesne) → Doktor (×2 zajęć, ×3 czesne)</span></div>
  <div class="grid">
  <?php foreach ($UNI_KIERUNKI as $id => $kd):
      $p = uv_p($st, $id); $k = uv_kier($st, $id);
      $akt_tu = $st['akt'] && $st['akt']['kierunek'] === $id;
      $prof = [];
      foreach ($ZAWODY_DANE ?? [] as $zn => $zd) if (($zd['wymagany_kierunek'] ?? null) === $id) $prof[] = $zn . (($zd['kierunek_od_etapu'] ?? 1) > 1 ? ' (od etapu ' . (int)$zd['kierunek_od_etapu'] . ')' : '');
  ?>
    <article class="kc" style="--k:<?php echo $kd['kolor']; ?>">
      <div class="kc-h"><svg class="ic" viewBox="0 0 24 24"><?php echo $UNI_IKONY[$id] ?? $UNI_IKONY['humanistyka']; ?></svg>
        <div><h3><?php echo $h($kd['nazwa']); ?></h3><p>Teraz: <?php echo $k ? '<span>' . $h(uni_tytul($id, $k[0])) . '</span>' : 'wszystkie stopnie ukończone'; ?></p></div></div>
      <div class="kc-mid">
        <?php if ($k): ?><div class="prog"><div class="prog-row"><span>Zajęcia</span><b><?php echo $k[1]; ?> / <?php echo $k[2]; ?></b></div><div class="bar"><i style="width:<?php echo round($k[1] / $k[2] * 100, 1); ?>%"></i></div></div>
        <?php else: ?><div class="prog"></div><?php endif; ?>
        <div class="flasks"><?php foreach ($UNI_STOPNIE as $nr => $sd): ?><div class="fl<?php echo $nr <= $p['s'] ? ' on' : ($k && $nr === $k[0] ? ' cur' : ''); ?>"><?php echo $TARCZA; ?><small><?php echo $sd['skrot']; ?></small></div><?php endforeach; ?></div>
      </div>
      <?php if ($prof): ?><div class="prof">Profesje: <b><?php echo $h(implode(', ', $prof)); ?></b></div><?php endif; ?>
      <?php if ($k): ?><div class="cost"><span>Czesne <?php echo $USD; ?><b><?php echo $zl(uni_czesne($id, $k[0])); ?> $</b></span><span>Energia <?php echo $BOLT; ?><b class="en"><?php echo (int)$kd['energia']; ?></b></span><span><?php echo UNI_CZAS_ZAJEC_S / 3600; ?> h</span></div><?php endif; ?>
      <form method="POST" action="game.php?page=uniwersytet" style="margin:0">
        <input type="hidden" name="kierunek" value="<?php echo $id; ?>">
        <?php if (!$k): ?><button type="button" class="kstat" disabled>Doktorat ukończony ✦</button>
        <?php elseif ($akt_tu): ?><button type="button" class="kstat" disabled>Zajęcia w toku ◢</button>
        <?php elseif ($st['akt']): ?><button type="button" class="kstat" disabled>Trwają inne zajęcia</button>
        <?php elseif ($st['do_nast'] > 0): ?><button type="button" class="kstat" disabled>Kolejne zajęcia za <span data-cd="<?php echo $st['teraz'] + $st['do_nast']; ?>" data-krotko="1">--:--</span></button>
        <?php elseif ($k[3]): ?><button type="submit" name="akcja" value="egz_start" class="kstat exam">Egzamin: <?php echo $UNI_STOPNIE[$k[0]]['nazwa']; ?> ▸</button>
        <?php elseif ($st['g']['gotowka'] < uni_czesne($id, $k[0]) || $st['g']['energia_aktualna'] < $kd['energia']): ?><button type="button" class="kstat" disabled>Brak gotówki lub energii</button>
        <?php else: ?><button type="submit" name="akcja" value="lam_start" class="kstat go">Rozpocznij zajęcia ▸</button><?php endif; ?>
      </form>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<section class="rules">
  <div><b>Stopnie a Profesje.</b> Licencjat odblokowuje etap 1 Profesji, Magister etapy 2–3, Doktor etap 4.</div>
  <div><b>PU.</b> Każdy stopień daje <?php echo UNI_PU_ZA_STOPIEN; ?> PU, liczone z maks. <?php echo UNI_PU_MAKS_KIERUNKOW; ?> kierunków — razem do <?php echo UNI_PU_ZA_STOPIEN * 3 * UNI_PU_MAKS_KIERUNKOW; ?> PU.</div>
  <div><b>Zaleta „Wykształcony”.</b> Można ją wybrać, gdy postać ma co najmniej jeden Licencjat.</div>
  <div><b>Egzamin.</b> Ostatnie zajęcia każdego stopnia to egzamin pisemny, który ocenia Dziekan (AI). Egzamin liczy się jako zajęcia na dany dzień.</div>
</section>

<div class="mod" id="uvMod"><div class="mod-box" style="--k:<?php echo $lam_js['kolor'] ?? ($egz ? '#ffd700' : 'var(--brass)'); ?>">
<?php if ($lam_js): $kd = $UNI_KIERUNKI[$_SESSION['uni_lam']['k']]; $nr = $_SESSION['uni_lam']['nr']; ?>
  <div class="mod-h"><div><div class="lbl"><?php echo $h($kd['nazwa']); ?> · <?php echo $UNI_STOPNIE[$nr]['nazwa']; ?> · zajęcia <?php echo $_SESSION['uni_lam']['z'] + 1; ?>/<?php echo uni_wymagane_zajecia($_SESSION['uni_lam']['k'], $nr); ?></div><h3><?php echo UNI_LAM_TYPY[$lam_js['typ']]; ?></h3></div><button type="button" class="x" data-zamknij aria-label="Zamknij">×</button></div>
  <div id="uvLam"></div>
  <form method="POST" action="game.php?page=uniwersytet" id="uvLamForm"><input type="hidden" name="akcja" value="lam_rozwiaz"><input type="hidden" name="odp" value=""></form>
  <div class="mod-foot"><span>Trudność: <?php echo $UNI_STOPNIE[$nr]['nazwa']; ?></span><span>Po rozwiązaniu: −<?php echo $zl(uni_czesne($_SESSION['uni_lam']['k'], $nr)); ?> $, −<?php echo (int)$kd['energia']; ?> EN, <?php echo UNI_CZAS_ZAJEC_S / 3600; ?> h zajęć</span></div>
<?php elseif ($egz): $kd = $UNI_KIERUNKI[$egz['k']]; ?>
  <div class="mod-h"><div><div class="lbl">Terminal Dziekanatu · egzamin dyplomowy</div><h3><?php echo $h(uni_tytul($egz['k'], $egz['nr'])); ?></h3></div><button type="button" class="x" data-zamknij aria-label="Zamknij">×</button></div>
  <div class="q"><?php echo $h($egz['q']); ?></div>
  <form method="POST" action="game.php?page=uniwersytet" style="display:flex;flex-direction:column;gap:12px">
    <input type="hidden" name="akcja" value="egz_wyslij">
    <textarea name="odpowiedz" required placeholder="Odpowiedź w roli postaci…"></textarea>
    <div class="mod-foot"><span>Dziekan (AI) ocenia 1–10, zdany od <?php echo UNI_EGZAMIN_PROG; ?>. Oblany: −<?php echo UNI_EGZAMIN_KARA_EN; ?> EN, kolejna próba jutro.</span><button type="submit" class="kbtn" style="--k:#ffd700">Wyślij do Dziekana</button></div>
  </form>
<?php endif; ?>
</div></div>
</div>
<script>window.UNI_V2=<?php echo json_encode(['teraz' => $st['teraz'], 'lam' => $lam_js, 'egz' => (bool)$egz], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;</script>
<script src="js/uniwersytet.js"></script>
