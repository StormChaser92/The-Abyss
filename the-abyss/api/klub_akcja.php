<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB AKCJA (POST: wiadomość lub komenda)
// 
// POST {sala, tresc}
// 
// Parsuje komendy:
//   /bar zamow [drink]       — dodaje drink na rachunek
//   /napiwek @kogo [kwota]   — przelew gracz → barman
//   /zaplac                  — opłaca własny rachunek
//   /mood [tekst]            — zmienia status fabularny
//   /do [sala]               — sygnał teleportu (zwraca redirect_url)
//   /barman [drink]          — bot NPC reaguje (legacy)
//   /karta /kosc /moneta     — bot krupier (legacy)
//   inne                      — zwykła wiadomość roleplay
// 
// Zwraca: { ok, msg?, redirect?, refresh? }
// ════════════════════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['zalogowany']) || $_SESSION['zalogowany'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Nie zalogowano']);
    exit;
}

require_once __DIR__ . "/../db.php";
header('Content-Type: application/json; charset=utf-8');

$id_gracza   = (int)$_SESSION['id_gracza'];
$login_gracza = $_SESSION['login'] ?? 'Gracz';

$sala  = isset($_POST['sala'])  ? preg_replace('/[^a-z0-9\-]/', '', strtolower($_POST['sala'])) : 'sala-glowna';
$tresc = isset($_POST['tresc']) ? trim($_POST['tresc']) : '';

$AKTYWNE_SALE = ['lobby','sala-glowna','sauna','bdsm','tyly','sala-balowa','vip','taras','basen','silownia','masaze','garderoba'];
if (!in_array($sala, $AKTYWNE_SALE, true) || $sala === 'lobby') {
    echo json_encode(['ok' => false, 'msg' => 'Sala nie obsługuje wiadomości']);
    exit;
}

if (empty($tresc)) {
    echo json_encode(['ok' => false, 'msg' => 'Pusta wiadomość']);
    exit;
}

if (mb_strlen($tresc) > 50000) {
    echo json_encode(['ok' => false, 'msg' => 'Wiadomość ekstremalnie długa (>50k znaków). Podziel ją na kilka.']);
    exit;
}

// ── DANE GRACZA + STATUS KLUBU ─────────────────────────────────
$gracz = $polaczenie->query("SELECT id, login, gotowka, is_barman, is_mg, klub_sala FROM gracze WHERE id=$id_gracza")->fetch_assoc();
if (!$gracz) {
    echo json_encode(['ok' => false, 'msg' => 'Brak danych gracza']);
    exit;
}
$jest_barmanem = (bool)$gracz['is_barman'];
$jest_mg       = (bool)$gracz['is_mg'];
$ma_uprawnienia = ($jest_barmanem || $jest_mg);

// FAZA 7: Log dnia wizyty (do odznaki Stały gość)
if (file_exists(__DIR__ . '/klub_odznaki_helper.php')) {
    require_once __DIR__ . '/klub_odznaki_helper.php';
    klub_log_dzien_wizyty($polaczenie, $id_gracza);
}

$klub = $polaczenie->query("SELECT * FROM klub_konfiguracja WHERE id=1")->fetch_assoc();
if (!$klub['otwarty'] && !$ma_uprawnienia) {
    echo json_encode(['ok' => false, 'msg' => 'Klub jest zamknięty']);
    exit;
}

// Ban check
$ban = $polaczenie->query("SELECT id FROM klub_bany WHERE gracz_id=$id_gracza AND aktywny=1 AND do_kiedy > NOW()")->fetch_assoc();
if ($ban && !$ma_uprawnienia) {
    echo json_encode(['ok' => false, 'msg' => 'Jesteś zbanowany w klubie']);
    exit;
}

$sala_sql = $polaczenie->real_escape_string($sala);

// ═══════════════════════════════════════════════════════════════
// PARSOWANIE KOMEND
// ═══════════════════════════════════════════════════════════════

// ── /do [sala] — teleport ──────────────────────────────────────
if (preg_match('/^\/do\s+([a-z0-9\-]+)/i', $tresc, $m)) {
    $cel = strtolower($m[1]);
    $aliasy = ['sg'=>'sala-glowna','glowna'=>'sala-glowna','bar'=>'sala-glowna','balowa'=>'sala-balowa'];
    if (isset($aliasy[$cel])) $cel = $aliasy[$cel];

    if (in_array($cel, $AKTYWNE_SALE, true) || $cel === 'lobby') {
        echo json_encode(['ok' => true, 'redirect' => "game.php?page=czat&sala=$cel"]);
        exit;
    }
    echo json_encode(['ok' => false, 'msg' => "Sala '$cel' nie istnieje lub niedostępna"]);
    exit;
}

// ══════════════════════════════════════════════════════════════════
// FAZA 3 — Komendy społeczne
// ══════════════════════════════════════════════════════════════════

// ── /szept @kogo treść — prywatna wiadomość MG → gracz ──────────
if (preg_match('/^\/szept\s+@?(\S+)\s+(.+)/iu', $tresc, $m)) {
    if (!$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko MG może szeptać']);
        exit;
    }
    $cel_login = trim($m[1]);
    $tekst = trim($m[2]);
    if (mb_strlen($tekst) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Szept ekstremalnie długi (>50k znaków)']);
        exit;
    }
    $cel_login_sql = $polaczenie->real_escape_string($cel_login);
    $cel = $polaczenie->query("SELECT id, login FROM gracze WHERE login='$cel_login_sql' LIMIT 1")->fetch_assoc();
    if (!$cel) {
        echo json_encode(['ok' => false, 'msg' => "Nie ma gracza '$cel_login'"]);
        exit;
    }
    $cel_id = (int)$cel['id'];
    $tresc_e = $polaczenie->real_escape_string($tekst);
    $polaczenie->query("INSERT INTO klub_szepty (od_gracza_id, do_gracza_id, sala, tresc) VALUES ($id_gracza, $cel_id, '$sala_sql', '$tresc_e')");
    echo json_encode(['ok' => true, 'msg' => "Szept wysłany do {$cel['login']}"]);
    exit;
}

// ── /flirt @kogo — prywatny sygnał (widoczny tylko adresatowi) ─
if (preg_match('/^\/flirt\s+@?(\S+)/iu', $tresc, $m)) {
    $cel_login = trim($m[1]);
    $cel_login_sql = $polaczenie->real_escape_string($cel_login);
    $cel = $polaczenie->query("SELECT id, login FROM gracze WHERE login='$cel_login_sql' LIMIT 1")->fetch_assoc();
    if (!$cel) {
        echo json_encode(['ok' => false, 'msg' => "Nie ma gracza '$cel_login'"]);
        exit;
    }
    $cel_id = (int)$cel['id'];
    if ($cel_id === $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'Trochę narcyzmu, ale nie aż tyle']);
        exit;
    }
    // Throttling: max 1 flirt do tej samej osoby na 60s
    $ostatni = $polaczenie->query("SELECT id FROM klub_flirty WHERE od_gracza_id=$id_gracza AND do_gracza_id=$cel_id AND czas >= NOW() - INTERVAL 60 SECOND LIMIT 1")->fetch_assoc();
    if ($ostatni) {
        echo json_encode(['ok' => false, 'msg' => 'Spokojnie. Daj jej/mu chwilę.']);
        exit;
    }
    $polaczenie->query("INSERT INTO klub_flirty (od_gracza_id, do_gracza_id, sala) VALUES ($id_gracza, $cel_id, '$sala_sql')");
    echo json_encode(['ok' => true, 'msg' => "Spojrzenie wysłane. Sekretnie."]);
    exit;
}

// ── /wypraszam @kogo [minuty] — barman/MG wyprasza z sali ─────
if (preg_match('/^\/wypraszam\s+@?(\S+)(?:\s+(\d+))?(?:\s+(.+))?/iu', $tresc, $m)) {
    if (!$ma_uprawnienia) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman lub MG może wypraszać']);
        exit;
    }
    $cel_login = trim($m[1]);
    $minuty = isset($m[2]) ? max(10, min(60, (int)$m[2])) : 30;
    $powod = isset($m[3]) ? trim($m[3]) : '';
    $cel_login_sql = $polaczenie->real_escape_string($cel_login);
    $cel = $polaczenie->query("SELECT id, login, is_barman, is_mg FROM gracze WHERE login='$cel_login_sql' LIMIT 1")->fetch_assoc();
    if (!$cel) {
        echo json_encode(['ok' => false, 'msg' => "Nie ma gracza '$cel_login'"]);
        exit;
    }
    if ($cel['is_barman'] || $cel['is_mg']) {
        echo json_encode(['ok' => false, 'msg' => 'Nie wypraszaj barmana ani MG']);
        exit;
    }
    $cel_id = (int)$cel['id'];
    $do_kiedy = date('Y-m-d H:i:s', time() + $minuty * 60);
    $powod_sql = $polaczenie->real_escape_string(mb_substr($powod, 0, 200));
    // Anuluj poprzednie wyproszenia z tej samej sali
    $polaczenie->query("UPDATE klub_wypraszania SET aktywne=0 WHERE gracz_id=$cel_id AND sala='$sala_sql' AND aktywne=1");
    $polaczenie->query("INSERT INTO klub_wypraszania (gracz_id, sala, barman_id, powod, do_kiedy) VALUES ($cel_id, '$sala_sql', $id_gracza, '$powod_sql', '$do_kiedy')");
    // Wyrzuć z sali natychmiast
    $polaczenie->query("UPDATE gracze SET klub_sala='lobby' WHERE id=$cel_id");
    // System message
    $sys = "🚪 <b>{$gracz['login']}</b> wyprosił/a <b>{$cel['login']}</b> z sali na <b>$minuty min</b>" . ($powod ? " — <i>" . htmlspecialchars($powod) . "</i>" : "") . ".";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', '$sala_sql', 'system')");
    // Powiadomienie
    $pow = "🚪 Zostałeś wyproszony z sali <b>$sala</b> przez <b>{$gracz['login']}</b> na $minuty min" . ($powod ? ". Powód: $powod" : "") . ".";
    $pow_sql = $polaczenie->real_escape_string($pow);
    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '$pow_sql')");
    echo json_encode(['ok' => true, 'msg' => "{$cel['login']} wyproszony/a na $minuty min"]);
    exit;
}

// ── /npc "Imię" "tekst" — barman/MG mówi za NPC ────────────────
if (stripos($tresc, '/npc') === 0) {
    if (!$ma_uprawnienia) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman lub MG może mówić za NPC']);
        exit;
    }
    // Normalizuj typograficzne cudzysłowy → ASCII
    $norm = $tresc;
    $norm = str_replace(["\xE2\x80\x9E","\xE2\x80\x9C","\xE2\x80\x9D","\xC2\xAB","\xC2\xBB","'","'"], '"', $norm);
    if (!preg_match('/^\/npc\s+"([^"]+)"\s+"(.+)"\s*$/u', $norm, $m)) {
        echo json_encode(['ok' => false, 'msg' => 'Format: /npc "Imię NPC" "tekst wypowiedzi"']);
        exit;
    }
    $imie_npc = trim($m[1]);
    $tekst_npc = trim($m[2]);
    if (mb_strlen($imie_npc) < 2 || mb_strlen($imie_npc) > 30) {
        echo json_encode(['ok' => false, 'msg' => 'Imię NPC: 2–30 znaków']);
        exit;
    }
    if (mb_strlen($tekst_npc) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Tekst NPC ekstremalnie długi (>50k znaków)']);
        exit;
    }
    $login_npc = $imie_npc . ' [NPC]';
    $login_sql = $polaczenie->real_escape_string($login_npc);
    $tekst_sql = $polaczenie->real_escape_string($tekst_npc);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ, summoner_id) VALUES (0, '$login_sql', '$tekst_sql', '$sala_sql', 'npc_speak', $id_gracza)");
    echo json_encode(['ok' => true]);
    exit;
}

// ──────────────────────────────────────────────────────────────────
// /mood — kontynuacja (oryginał, scalony po blokach Faza 3)
// ──────────────────────────────────────────────────────────────────

// ══════════════════════════════════════════════════════════════════
// FAZA 4 — Plotki, życzenia, DJ, edycja wiadomości
// ══════════════════════════════════════════════════════════════════

// ── /plotka [tekst] — anonimowa plotka ─────────────────────────
if (preg_match('/^\/plotka\s+(.+)/iu', $tresc, $m)) {
    $tekst = trim($m[1]);
    if (mb_strlen($tekst) < 10) {
        echo json_encode(['ok' => false, 'msg' => 'Plotka za krótka (min 10 znaków)']);
        exit;
    }
    if (mb_strlen($tekst) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Plotka ekstremalnie długa (>50k znaków)']);
        exit;
    }
    // Anti-spam: max 5 plotek na godzinę
    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_plotki WHERE autor_id=$id_gracza AND czas >= NOW() - INTERVAL 1 HOUR")->fetch_assoc();
    if ((int)$r['c'] >= 5) {
        echo json_encode(['ok' => false, 'msg' => 'Już rzuciłeś 5 plotek w ostatniej godzinie']);
        exit;
    }
    $tekst_sql = $polaczenie->real_escape_string($tekst);
    $polaczenie->query("INSERT INTO klub_plotki (autor_id, tresc) VALUES ($id_gracza, '$tekst_sql')");
    echo json_encode(['ok' => true, 'msg' => 'Plotka pojawiła się na tablicy w lobby. Anonimowo.']);
    exit;
}

// ── /życzenie [tytuł] — szybkie życzenie RP (rozszerzone w UI) ─
if (preg_match('/^\/(?:życzenie|zyczenie|wish)\s+(.+)/iu', $tresc, $m)) {
    $tekst = trim($m[1]);
    if (mb_strlen($tekst) < 5 || mb_strlen($tekst) > 120) {
        echo json_encode(['ok' => false, 'msg' => 'Tytuł życzenia: 5–120 znaków. Pełne życzenie z opisem dodaj na tablicy w lobby.']);
        exit;
    }
    // Limit 3 aktywnych życzeń
    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_zyczenia WHERE autor_id=$id_gracza AND aktywne=1 AND spelnione=0")->fetch_assoc();
    if ((int)$r['c'] >= 3) {
        echo json_encode(['ok' => false, 'msg' => 'Masz już 3 aktywne życzenia — zamknij któreś najpierw']);
        exit;
    }
    $tekst_sql = $polaczenie->real_escape_string($tekst);
    $sala_sql_pref = $polaczenie->real_escape_string($sala);
    $polaczenie->query("INSERT INTO klub_zyczenia (autor_id, tytul, sala_preferowana, tag_klimat) VALUES ($id_gracza, '$tekst_sql', '$sala_sql_pref', 'inne')");
    echo json_encode(['ok' => true, 'msg' => 'Życzenie na tablicy w lobby. Dodaj opis tam, żeby ludzie wiedzieli więcej.']);
    exit;
}

// ── /dj [tytuł] [— artysta] — zamów utwór (każdy gracz) ────────
if (preg_match('/^\/dj\s+(.+)/iu', $tresc, $m)) {
    $params = trim($m[1]);

    // Subkomendy DJ (tylko barman/MG)
    if (preg_match('/^(zakoncz|cisza|stop)\b/iu', $params)) {
        if (!$ma_uprawnienia) {
            echo json_encode(['ok' => false, 'msg' => 'Tylko Barman/MG może kończyć utwór']);
            exit;
        }
        $polaczenie->query("UPDATE klub_dj_kolejka SET status='zagrany', czas_zagrania=NOW() WHERE status='grany'");
        $polaczenie->query("UPDATE klub_dj_aktualny SET kolejka_id=NULL, tytul_recznie=NULL, artysta_recznie=NULL, dj_login=NULL, dj_id=NULL, czas_startu=NULL WHERE id=1");
        $sys = "🎧 DJ <b>{$gracz['login']}</b> kończy ostatni utwór. Cisza.";
        $sys_sql = $polaczenie->real_escape_string($sys);
        $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");
        echo json_encode(['ok' => true, 'msg' => 'Cisza w klubie']);
        exit;
    }

    if (preg_match('/^(nastepny|next)\b/iu', $params)) {
        if (!$ma_uprawnienia) {
            echo json_encode(['ok' => false, 'msg' => 'Tylko Barman/MG może puszczać utwory']);
            exit;
        }
        $u = $polaczenie->query("SELECT k.*, g.login FROM klub_dj_kolejka k LEFT JOIN gracze g ON g.id=k.gracz_id WHERE k.status='w_kolejce' ORDER BY k.id ASC LIMIT 1")->fetch_assoc();
        if (!$u) {
            echo json_encode(['ok' => false, 'msg' => 'Kolejka pusta']);
            exit;
        }
        $polaczenie->query("UPDATE klub_dj_kolejka SET status='zagrany', czas_zagrania=NOW() WHERE status='grany'");
        $polaczenie->query("UPDATE klub_dj_kolejka SET status='grany' WHERE id=" . (int)$u['id']);
        $login_e = $polaczenie->real_escape_string($gracz['login']);
        $polaczenie->query("UPDATE klub_dj_aktualny SET kolejka_id=" . (int)$u['id'] . ", tytul_recznie=NULL, artysta_recznie=NULL, dj_login='$login_e', dj_id=$id_gracza, czas_startu=NOW() WHERE id=1");
        $opis = htmlspecialchars($u['tytul']) . ($u['artysta'] ? " — " . htmlspecialchars($u['artysta']) : '');
        $sys = "🎧 DJ <b>{$gracz['login']}</b> puszcza: <i>$opis</i>";
        if ($u['login']) $sys .= " <small>(zamówione przez {$u['login']})</small>";
        $sys_sql = $polaczenie->real_escape_string($sys);
        $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");
        echo json_encode(['ok' => true, 'msg' => '🎧 Następny utwór puszczony']);
        exit;
    }

    // Zwykłe zamówienie: /dj Tytuł utworu — Artysta
    $tytul = $params;
    $artysta = '';
    if (strpos($params, ' — ') !== false || strpos($params, ' - ') !== false) {
        $parts = preg_split('/\s[—-]\s/u', $params, 2);
        $tytul = trim($parts[0]);
        $artysta = isset($parts[1]) ? trim($parts[1]) : '';
    }
    if (mb_strlen($tytul) < 2 || mb_strlen($tytul) > 150) {
        echo json_encode(['ok' => false, 'msg' => 'Tytuł: 2–150 znaków. Format: /dj Tytuł — Artysta']);
        exit;
    }
    $artysta = mb_substr($artysta, 0, 100);

    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_dj_kolejka WHERE gracz_id=$id_gracza AND status='w_kolejce'")->fetch_assoc();
    if ((int)$r['c'] >= 3) {
        echo json_encode(['ok' => false, 'msg' => 'Masz już 3 utwory w kolejce — poczekaj na DJ-a']);
        exit;
    }

    $tytul_e = $polaczenie->real_escape_string($tytul);
    $artysta_e = $artysta === '' ? 'NULL' : "'" . $polaczenie->real_escape_string($artysta) . "'";
    $polaczenie->query("INSERT INTO klub_dj_kolejka (gracz_id, tytul, artysta) VALUES ($id_gracza, '$tytul_e', $artysta_e)");

    $opis = htmlspecialchars($tytul) . ($artysta ? " — " . htmlspecialchars($artysta) : '');
    $sys = "🎵 <b>{$gracz['login']}</b> zamawia u DJ-a: <i>$opis</i>";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");
    echo json_encode(['ok' => true, 'msg' => '🎵 Utwór w kolejce — DJ zobaczy']);
    exit;
}

// ── /edytuj ID nowy_tekst — edycja własnej wiadomości (10 min) ─
if (preg_match('/^\/edytuj\s+(\d+)\s+(.+)/iu', $tresc, $m)) {
    $msg_id = (int)$m[1];
    $nowy = trim($m[2]);
    $msg = $polaczenie->query("SELECT id, id_gracza, typ, data_wyslania, tresc, edycji_licznik, usunieta, tresc_oryginalna FROM czat WHERE id=$msg_id")->fetch_assoc();
    if (!$msg) {
        echo json_encode(['ok' => false, 'msg' => 'Wiadomość nie istnieje']);
        exit;
    }
    if ((int)$msg['id_gracza'] !== $id_gracza && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Możesz edytować tylko swoje wiadomości']);
        exit;
    }
    if ((int)$msg['usunieta'] === 1) {
        echo json_encode(['ok' => false, 'msg' => 'Wiadomość jest usunięta']);
        exit;
    }
    if (in_array($msg['typ'], ['system','bot','npc_speak'])) {
        echo json_encode(['ok' => false, 'msg' => 'Tej wiadomości nie można edytować']);
        exit;
    }
    $wiek_sek = time() - strtotime($msg['data_wyslania']);
    if ($wiek_sek > 600 && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Wiadomość za stara (max 10 min od wysłania)']);
        exit;
    }
    if (mb_strlen($nowy) < 1 || mb_strlen($nowy) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Nowa treść: 1–50000 znaków']);
        exit;
    }
    if ((int)$msg['edycji_licznik'] >= 5 && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Limit 5 edycji wiadomości osiągnięty']);
        exit;
    }
    // Zapisz oryginał przy pierwszej edycji
    $orig_set = '';
    if (empty($msg['tresc_oryginalna'])) {
        $orig_e = $polaczenie->real_escape_string($msg['tresc']);
        $orig_set = ", tresc_oryginalna='$orig_e'";
    }
    $nowy_e = $polaczenie->real_escape_string($nowy);
    $polaczenie->query("UPDATE czat SET tresc='$nowy_e', edytowane_o=NOW(), edycji_licznik=edycji_licznik+1 $orig_set WHERE id=$msg_id");
    echo json_encode(['ok' => true, 'msg' => 'Wiadomość zaktualizowana', 'refresh' => true]);
    exit;
}

// ── /usun ID — usunięcie własnej wiadomości (10 min) ─────────────
if (preg_match('/^\/usun\s+(\d+)/iu', $tresc, $m)) {
    $msg_id = (int)$m[1];
    $msg = $polaczenie->query("SELECT id, id_gracza, typ, data_wyslania FROM czat WHERE id=$msg_id")->fetch_assoc();
    if (!$msg) {
        echo json_encode(['ok' => false, 'msg' => 'Wiadomość nie istnieje']);
        exit;
    }
    if ((int)$msg['id_gracza'] !== $id_gracza && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Możesz usuwać tylko swoje wiadomości']);
        exit;
    }
    if (in_array($msg['typ'], ['system','bot','npc_speak']) && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Tej wiadomości nie można usunąć']);
        exit;
    }
    $wiek_sek = time() - strtotime($msg['data_wyslania']);
    if ($wiek_sek > 600 && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Wiadomość za stara (max 10 min od wysłania)']);
        exit;
    }
    $polaczenie->query("UPDATE czat SET usunieta=1, tresc='[wiadomość usunięta]' WHERE id=$msg_id");
    echo json_encode(['ok' => true, 'msg' => 'Wiadomość usunięta', 'refresh' => true]);
    exit;
}

// ──────────────────────────────────────────────────────────────────
// /mood — kontynuacja (oryginał, scalony po blokach Faza 3)
// ──────────────────────────────────────────────────────────────────
if (preg_match('/^\/mood\s+(.+)/i', $tresc, $m)) {
    $mood = trim($m[1]);
    $mood = mb_substr($mood, 0, 100);
    $mood_sql = $polaczenie->real_escape_string($mood);
    $polaczenie->query("UPDATE gracze SET klub_mood='$mood_sql' WHERE id=$id_gracza");
    echo json_encode(['ok' => true, 'msg' => 'Status zmieniony', 'refresh' => true]);
    exit;
}

if (preg_match('/^\/mood$/i', $tresc)) {
    $polaczenie->query("UPDATE gracze SET klub_mood='' WHERE id=$id_gracza");
    echo json_encode(['ok' => true, 'msg' => 'Status wyczyszczony', 'refresh' => true]);
    exit;
}

// ── /napiwek @kogo kwota ───────────────────────────────────────
if (preg_match('/^\/napiwek\s+@?(\S+)\s+(\d+)/i', $tresc, $m)) {
    $cel_login = trim($m[1]);
    $kwota = (int)$m[2];

    if ($kwota < 1) {
        echo json_encode(['ok' => false, 'msg' => 'Kwota musi być większa od 0']);
        exit;
    }
    if ($kwota > $gracz['gotowka']) {
        echo json_encode(['ok' => false, 'msg' => 'Brak gotówki — masz tylko ' . number_format($gracz['gotowka'], 0, '', ' ') . ' $']);
        exit;
    }

    $cel_login_sql = $polaczenie->real_escape_string($cel_login);
    $cel = $polaczenie->query("SELECT id, login FROM gracze WHERE login='$cel_login_sql' LIMIT 1")->fetch_assoc();
    if (!$cel) {
        echo json_encode(['ok' => false, 'msg' => "Nie ma gracza '$cel_login'"]);
        exit;
    }
    if ((int)$cel['id'] === $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'Nie możesz dać napiwku samemu sobie']);
        exit;
    }

    $cel_id = (int)$cel['id'];
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $kwota WHERE id=$id_gracza");
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $kwota, klub_napiwki_zarobione = klub_napiwki_zarobione + $kwota WHERE id=$cel_id");
    $polaczenie->query("INSERT INTO klub_napiwki (od_gracza_id, do_gracza_id, kwota) VALUES ($id_gracza, $cel_id, $kwota)");

    // System message w czacie
    $sys = "✦ {$gracz['login']} zostawia napiwek <b>{$kwota} \$</b> dla <b>{$cel['login']}</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', '$sala_sql', 'system')");

    echo json_encode(['ok' => true, 'msg' => "Napiwek $kwota \$ dla {$cel['login']}"]);
    exit;
}

// ── /zaplac — opłacenie rachunku ────────────────────────────────
if (preg_match('/^\/zaplac/i', $tresc)) {
    $rachunki = $polaczenie->query("SELECT id, nazwa_drink, cena, barman_id FROM klub_rachunki WHERE gracz_id=$id_gracza AND oplacony=0");
    if (!$rachunki || $rachunki->num_rows === 0) {
        echo json_encode(['ok' => false, 'msg' => 'Twój rachunek jest pusty']);
        exit;
    }

    $razem = 0;
    $ids = [];
    $do_barmanow = []; // barman_id => kwota
    while ($r = $rachunki->fetch_assoc()) {
        $razem += (int)$r['cena'];
        $ids[] = (int)$r['id'];
        if (!empty($r['barman_id'])) {
            $bid = (int)$r['barman_id'];
            $do_barmanow[$bid] = ($do_barmanow[$bid] ?? 0) + (int)$r['cena'];
        }
    }

    if ($razem > $gracz['gotowka']) {
        echo json_encode(['ok' => false, 'msg' => "Brak gotówki na rachunek $razem \$ — masz " . number_format($gracz['gotowka'], 0, '', ' ') . ' $']);
        exit;
    }

    $ids_str = implode(',', $ids);
    $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $razem WHERE id=$id_gracza");
    $polaczenie->query("UPDATE klub_rachunki SET oplacony=1 WHERE id IN ($ids_str)");

    // 80% trafia do barmanów którzy podali drinki
    foreach ($do_barmanow as $bid => $kwota_drink) {
        $do_barmana = (int)floor($kwota_drink * 0.80);
        if ($do_barmana > 0) {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $do_barmana WHERE id=$bid");
        }
    }

    $sys = "💰 {$gracz['login']} płaci rachunek <b>$razem \$</b>.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', '$sala_sql', 'system')");

    echo json_encode(['ok' => true, 'msg' => "Zapłacono rachunek $razem \$"]);
    exit;
}

// ── /bar zamów [drink] albo /barman [drink] ────────────────────
$is_zamow = false;
$nazwa_szukana = '';

if (preg_match('/^\/bar\s+zam[oó]w\s+(.+)/iu', $tresc, $m)) {
    $is_zamow = true;
    $nazwa_szukana = trim($m[1]);
} elseif (preg_match('/^\/zamow\s+(.+)/iu', $tresc, $m)) {
    $is_zamow = true;
    $nazwa_szukana = trim($m[1]);
}

if ($is_zamow && $sala === 'sala-glowna') {
    // Wyczyść białe znaki, cudzysłowy proste i typograficzne, niskopoziomowe ASCII
    $nazwa_szukana = trim($nazwa_szukana);
    $nazwa_szukana = trim($nazwa_szukana, "\"'\xE2\x80\x9C\xE2\x80\x9D\xE2\x80\x9E");
    $nazwa_szukana_sql = $polaczenie->real_escape_string($nazwa_szukana);

    // Szukaj drinka w katalogu (LIKE - elastyczne)
    $drink = $polaczenie->query("
        SELECT id, nazwa, opis, cena, sygnatura, ikona_emoji 
        FROM klub_drinki 
        WHERE aktywny=1 AND nazwa LIKE '%$nazwa_szukana_sql%' 
        ORDER BY (nazwa = '$nazwa_szukana_sql') DESC, sygnatura DESC, LENGTH(nazwa) ASC 
        LIMIT 1
    ")->fetch_assoc();

    if (!$drink) {
        echo json_encode(['ok' => false, 'msg' => "Nie ma w karcie drinka pasującego do '$nazwa_szukana'"]);
        exit;
    }

    // Sprawdź czy gracz nie ma za wielkiego rachunku (limit ostrożnościowy)
    $stary_r = $polaczenie->query("SELECT IFNULL(SUM(cena),0) AS razem FROM klub_rachunki WHERE gracz_id=$id_gracza AND oplacony=0")->fetch_assoc();
    if ((int)$stary_r['razem'] >= 500) {
        echo json_encode(['ok' => false, 'msg' => 'Masz już 500 $ na rachunku — opłać go (/zaplac) zanim zamówisz więcej']);
        exit;
    }

    // Czy w sali jest live barman-gracz?
    $live = $polaczenie->query("
        SELECT id, login FROM gracze 
        WHERE is_barman=1 AND klub_sala='sala-glowna' 
          AND ostatnia_aktywnosc >= NOW() - INTERVAL 10 MINUTE 
          AND id != $id_gracza
        LIMIT 1
    ")->fetch_assoc();
    $barman_id = $live ? (int)$live['id'] : null;
    $barman_id_sql = $barman_id ?? 'NULL';

    // Dodaj na rachunek
    $drink_id = (int)$drink['id'];
    $cena = (int)$drink['cena'];
    $nazwa_sql = $polaczenie->real_escape_string($drink['nazwa']);
    $polaczenie->query("INSERT INTO klub_rachunki (gracz_id, drink_id, nazwa_drink, cena, barman_id) VALUES ($id_gracza, $drink_id, '$nazwa_sql', $cena, $barman_id_sql)");
    $polaczenie->query("UPDATE gracze SET klub_drinki_zamowione = klub_drinki_zamowione + 1 WHERE id=$id_gracza");
    if ($barman_id) {
        $polaczenie->query("UPDATE gracze SET klub_drinki_podane = klub_drinki_podane + 1 WHERE id=$barman_id");
    }

    // FAZA 7: Log drinka + sprawdzenie odznak (Smakosz)
    if (file_exists(__DIR__ . '/klub_odznaki_helper.php')) {
        require_once __DIR__ . '/klub_odznaki_helper.php';
        klub_log_drink($polaczenie, $id_gracza, $drink_id);
        klub_sprawdz_odznaki($polaczenie, $id_gracza);
    }

    // Wiadomość gracza w czacie
    $login_sql = $polaczenie->real_escape_string($gracz['login']);
    $tresc_gracza = $polaczenie->real_escape_string($tresc);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES ($id_gracza, '$login_sql', '$tresc_gracza', '$sala_sql', 'wiadomosc')");

    // Reakcja barmana
    if ($barman_id) {
        // Live barman — system message zamiast bot
        $sys = "🍸 {$live['login']} przygotowuje <b>{$drink['ikona_emoji']} {$drink['nazwa']}</b> dla <b>{$gracz['login']}</b> (<i>$cena \$ na rachunek</i>).";
        $sys_sql = $polaczenie->real_escape_string($sys);
        $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', '$sala_sql', 'system')");
    } else {
        // Bot NPC
        $reakcje = [
            "*Ociera szklankę brudną szmatą i sięga pod bar po {z}.* Pij. I nie zrób chlewu.",
            "*Bez słowa stawia {z} przed Tobą.* Płacisz potem. Pamiętam każdy cent.",
            "*Mierzy Cię chłodnym wzrokiem, podaje {z}.* Tylko nie wylej na podłogę.",
            "*Zręcznie miksuje {z} i podsuwa bez patrzenia.*",
            "*Skinienie głowy, brzęk szkła.* {z}. Bez pytań.",
            "*Gwiżdże pod nosem, przygotowuje {z}.* Smacznego.",
            "*Stawia {z} powoli, jakby oceniał czy jesteś tego wart.*",
            "*Milczenie. Podaje {z} z miną człowieka, który widział zbyt wiele.*",
            "— Już leci. *Stawia {z} bez ceregieli.*",
            "*Wyciera ręce w fartuch, miksuje {z} z aptekarską precyzją.*",
        ];
        $opis_drink = $drink['ikona_emoji'] . ' ' . $drink['nazwa'];
        $odp = str_replace('{z}', $opis_drink, $reakcje[array_rand($reakcje)]);
        $odp .= " <i>($cena \$ — na rachunek)</i>";
        $odp_sql = $polaczenie->real_escape_string($odp);
        $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'Barman [NPC]', '$odp_sql', '$sala_sql', 'bot')");
    }

    echo json_encode(['ok' => true, 'msg' => "{$drink['nazwa']} dodany na rachunek (+$cena \$)"]);
    exit;
}

// Stara komenda /barman X (kompatybilność) — działa jak /bar zamow X
if (preg_match('/^\/barman\s+(.+)/i', $tresc, $m) && $sala === 'sala-glowna') {
    // Przekieruj logikę przez /bar zamow
    $_POST['tresc'] = '/bar zamow ' . trim($m[1]);
    include __FILE__; // re-run z nowym tresc
    exit;
}

// ── /karta /kosc /moneta — krupier NPC ──────────────────────────
if (in_array($sala, ['sala-glowna']) && stripos($tresc, '/karta') === 0) {
    $kolory = ['♠️ Pik','♣️ Trefl','♥️ Kier','♦️ Karo'];
    $figury = ['2','3','4','5','6','7','8','9','10','Walet','Dama','Król','As'];
    $karta = $figury[array_rand($figury)] . ' ' . $kolory[array_rand($kolory)];
    $login_sql = $polaczenie->real_escape_string($gracz['login']);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES ($id_gracza, '$login_sql', '" . $polaczenie->real_escape_string($tresc) . "', '$sala_sql', 'wiadomosc')");
    $odp = "*Rozkłada talię na stole i przesuwa jedną kartę w stronę {$gracz['login']}. To:* **$karta**.";
    $odp_sql = $polaczenie->real_escape_string($odp);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'Krupier [NPC]', '$odp_sql', '$sala_sql', 'bot')");
    echo json_encode(['ok' => true]);
    exit;
}
if (in_array($sala, ['sala-glowna']) && stripos($tresc, '/kosc') === 0) {
    $w = rand(1,6);
    $login_sql = $polaczenie->real_escape_string($gracz['login']);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES ($id_gracza, '$login_sql', '" . $polaczenie->real_escape_string($tresc) . "', '$sala_sql', 'wiadomosc')");
    $odp = "*Rzuca sześciościenną kością po zielonym suknie na polecenie {$gracz['login']}. Wypada:* **$w**.";
    $odp_sql = $polaczenie->real_escape_string($odp);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'Krupier [NPC]', '$odp_sql', '$sala_sql', 'bot')");
    echo json_encode(['ok' => true]);
    exit;
}
if (in_array($sala, ['sala-glowna']) && stripos($tresc, '/moneta') === 0) {
    $w = (rand(0,1) === 0) ? 'Orzeł' : 'Reszka';
    $login_sql = $polaczenie->real_escape_string($gracz['login']);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES ($id_gracza, '$login_sql', '" . $polaczenie->real_escape_string($tresc) . "', '$sala_sql', 'wiadomosc')");
    $odp = "*Podrzuca z brzękiem mosiężną monetę. Wynik:* **$w**.";
    $odp_sql = $polaczenie->real_escape_string($odp);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'Krupier [NPC]', '$odp_sql', '$sala_sql', 'bot')");
    echo json_encode(['ok' => true]);
    exit;
}

// ── ZWYKŁA WIADOMOŚĆ ROLEPLAY ───────────────────────────────────
$tresc_sql = $polaczenie->real_escape_string($tresc);
$login_display = $gracz['login'];
if ($jest_barmanem) $login_display .= ' [BARMAN]';
elseif ($jest_mg)   $login_display .= ' [MG]';
$login_sql = $polaczenie->real_escape_string($login_display);

$polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES ($id_gracza, '$login_sql', '$tresc_sql', '$sala_sql', 'wiadomosc')");

echo json_encode(['ok' => true]);