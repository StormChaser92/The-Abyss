<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB ŻYCZENIA RP (Faza 4)
// 
// GET  ?op=lista                     — aktywne życzenia
// POST op=dodaj                      — dodaj swoje
// POST op=odpowiedz id=X wiadomosc=  — odpowiedz na czyjeś
// POST op=spelnione id=X             — autor oznacza jako spełnione
// POST op=usun id=X                  — autor usuwa swoje
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

$id_gracza = (int)$_SESSION['id_gracza'];
$gracz = $polaczenie->query("SELECT id, login, is_mg FROM gracze WHERE id=$id_gracza")->fetch_assoc();
if (!$gracz) {
    echo json_encode(['ok' => false, 'msg' => 'Brak danych gracza']);
    exit;
}
$jest_mg = (bool)$gracz['is_mg'];

$op = $_REQUEST['op'] ?? 'lista';

$DOZWOLONE_KLIMATY = ['romans','przyjazn','wspolpraca','konflikt','tajemnica','inne'];
$DOZWOLONE_SALE = ['sala-glowna','sala-balowa','sauna','bdsm','tyly','vip','taras','basen','silownia','garderoba','masaze',null,''];

// ═══════════════════════════════════════════════════════════════════
// LISTA
// ═══════════════════════════════════════════════════════════════════
if ($op === 'lista') {
    $q = $polaczenie->query("
        SELECT z.*, g.login AS autor_login,
               (SELECT COUNT(*) FROM klub_zyczenia_odp WHERE zyczenie_id=z.id) AS liczba_odp
        FROM klub_zyczenia z
        LEFT JOIN gracze g ON g.id = z.autor_id
        WHERE z.aktywne=1 AND z.spelnione=0
        ORDER BY z.id DESC
        LIMIT 30
    ");
    $zyczenia = [];
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $ts = strtotime($r['czas']);
            $teraz = time();
            $minuty = (int)(($teraz - $ts) / 60);
            if ($minuty < 60) $kiedy = $minuty . ' min temu';
            elseif ($minuty < 1440) $kiedy = (int)($minuty / 60) . ' godz. temu';
            else $kiedy = date('d.m', $ts);

            $zyczenia[] = [
                'id' => (int)$r['id'],
                'tytul' => $r['tytul'],
                'opis' => $r['opis'],
                'sala_preferowana' => $r['sala_preferowana'],
                'tag_klimat' => $r['tag_klimat'],
                'autor_login' => $r['autor_login'] ?? 'Anonim',
                'autor_id' => (int)$r['autor_id'],
                'kiedy' => $kiedy,
                'liczba_odp' => (int)$r['liczba_odp'],
                'czy_moje' => ((int)$r['autor_id'] === $id_gracza),
            ];
        }
    }
    echo json_encode(['ok' => true, 'zyczenia' => $zyczenia]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// DODAJ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'dodaj') {
    $tytul = trim($_POST['tytul'] ?? '');
    $opis = trim($_POST['opis'] ?? '');
    $sala = trim($_POST['sala_preferowana'] ?? '');
    $klimat = trim($_POST['tag_klimat'] ?? '');

    if (mb_strlen($tytul) < 5 || mb_strlen($tytul) > 120) {
        echo json_encode(['ok' => false, 'msg' => 'Tytuł: 5–120 znaków']);
        exit;
    }
    if (mb_strlen($opis) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Opis ekstremalnie długi (>50k znaków)']);
        exit;
    }

    if ($sala === '') $sala_sql = 'NULL';
    elseif (in_array($sala, $DOZWOLONE_SALE, true)) $sala_sql = "'" . $polaczenie->real_escape_string($sala) . "'";
    else { echo json_encode(['ok' => false, 'msg' => 'Niepoprawna sala']); exit; }

    if ($klimat === '' || !in_array($klimat, $DOZWOLONE_KLIMATY, true)) $klimat = 'inne';
    $klimat_e = $polaczenie->real_escape_string($klimat);

    // Anti-spam: max 3 aktywne życzenia
    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_zyczenia WHERE autor_id=$id_gracza AND aktywne=1 AND spelnione=0")->fetch_assoc();
    if ((int)$r['c'] >= 3) {
        echo json_encode(['ok' => false, 'msg' => 'Masz już 3 aktywne życzenia — zamknij któreś najpierw']);
        exit;
    }

    $tytul_e = $polaczenie->real_escape_string($tytul);
    $opis_e = $polaczenie->real_escape_string($opis);

    $polaczenie->query("
        INSERT INTO klub_zyczenia (autor_id, tytul, opis, sala_preferowana, tag_klimat)
        VALUES ($id_gracza, '$tytul_e', '$opis_e', $sala_sql, '$klimat_e')
    ");

    echo json_encode(['ok' => true, 'msg' => 'Życzenie ogłoszone na tablicy']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ODPOWIEDZ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'odpowiedz') {
    $zid = (int)($_POST['id'] ?? 0);
    $wiad = trim($_POST['wiadomosc'] ?? '');
    if (mb_strlen($wiad) < 5 || mb_strlen($wiad) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Odpowiedź: 5–50000 znaków']);
        exit;
    }
    $z = $polaczenie->query("SELECT id, autor_id, tytul FROM klub_zyczenia WHERE id=$zid AND aktywne=1 AND spelnione=0")->fetch_assoc();
    if (!$z) {
        echo json_encode(['ok' => false, 'msg' => 'Życzenie nie istnieje']);
        exit;
    }
    if ((int)$z['autor_id'] === $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'Nie odpowiadaj samemu sobie']);
        exit;
    }
    // Throttling: max 1 odpowiedź na to samo życzenie na dzień
    $istn = $polaczenie->query("SELECT id FROM klub_zyczenia_odp WHERE zyczenie_id=$zid AND od_gracza_id=$id_gracza AND czas >= NOW() - INTERVAL 24 HOUR")->fetch_assoc();
    if ($istn) {
        echo json_encode(['ok' => false, 'msg' => 'Już odpowiedziałeś na to życzenie. Poczekaj na reakcję autora.']);
        exit;
    }
    $wiad_e = $polaczenie->real_escape_string($wiad);
    $polaczenie->query("INSERT INTO klub_zyczenia_odp (zyczenie_id, od_gracza_id, wiadomosc) VALUES ($zid, $id_gracza, '$wiad_e')");

    // Powiadom autora życzenia
    $tresc = "💌 <b>{$gracz['login']}</b> odpowiedział/a na Twoje życzenie „" . htmlspecialchars($z['tytul']) . "\". Sprawdź tablicę życzeń.";
    $tresc_sql = $polaczenie->real_escape_string($tresc);
    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES (" . (int)$z['autor_id'] . ", '$tresc_sql')");

    echo json_encode(['ok' => true, 'msg' => 'Odpowiedź wysłana — autor otrzymał powiadomienie']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// LISTA ODPOWIEDZI (tylko autor życzenia)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'odpowiedzi') {
    $zid = (int)($_REQUEST['id'] ?? 0);
    $z = $polaczenie->query("SELECT autor_id FROM klub_zyczenia WHERE id=$zid")->fetch_assoc();
    if (!$z || ((int)$z['autor_id'] !== $id_gracza && !$jest_mg)) {
        echo json_encode(['ok' => false, 'msg' => 'Nie masz dostępu']);
        exit;
    }
    $q = $polaczenie->query("
        SELECT o.*, g.login AS od_login
        FROM klub_zyczenia_odp o
        LEFT JOIN gracze g ON g.id = o.od_gracza_id
        WHERE o.zyczenie_id=$zid
        ORDER BY o.id DESC
    ");
    // Mark as read
    $polaczenie->query("UPDATE klub_zyczenia_odp SET przeczytana=1 WHERE zyczenie_id=$zid AND przeczytana=0");

    $odp = [];
    if ($q) while ($r = $q->fetch_assoc()) {
        $odp[] = [
            'id' => (int)$r['id'],
            'wiadomosc' => $r['wiadomosc'],
            'od_login' => $r['od_login'] ?? 'Anonim',
            'od_id' => (int)$r['od_gracza_id'],
            'czas' => date('d.m H:i', strtotime($r['czas'])),
        ];
    }
    echo json_encode(['ok' => true, 'odpowiedzi' => $odp]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// SPEŁNIONE / USUŃ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'spelnione') {
    $zid = (int)($_POST['id'] ?? 0);
    $z = $polaczenie->query("SELECT autor_id FROM klub_zyczenia WHERE id=$zid")->fetch_assoc();
    if (!$z || (int)$z['autor_id'] !== $id_gracza) {
        echo json_encode(['ok' => false, 'msg' => 'Nie Twoje życzenie']);
        exit;
    }
    $polaczenie->query("UPDATE klub_zyczenia SET spelnione=1 WHERE id=$zid");
    echo json_encode(['ok' => true, 'msg' => '✓ Życzenie oznaczone jako spełnione']);
    exit;
}

if ($op === 'usun') {
    $zid = (int)($_POST['id'] ?? 0);
    $z = $polaczenie->query("SELECT autor_id FROM klub_zyczenia WHERE id=$zid")->fetch_assoc();
    if (!$z || ((int)$z['autor_id'] !== $id_gracza && !$jest_mg)) {
        echo json_encode(['ok' => false, 'msg' => 'Nie masz dostępu']);
        exit;
    }
    $polaczenie->query("UPDATE klub_zyczenia SET aktywne=0 WHERE id=$zid");
    echo json_encode(['ok' => true, 'msg' => 'Życzenie usunięte']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);