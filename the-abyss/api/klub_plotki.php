<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB PLOTKI (Faza 4)
// 
// GET  ?op=lista              — anonimowa lista 30 ostatnich
// POST op=dodaj tresc=...     — gracz dodaje plotkę (max 500 znaków)
// POST op=reaguj id=X typ=prawda|falsz — gracz reaguje (1x per plotka)
// POST op=usun id=X           — TYLKO MG (moderacja)
// 
// Zwraca: { ok, msg?, plotki?, ... }
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
$gracz = $polaczenie->query("SELECT id, login, is_barman, is_mg FROM gracze WHERE id=$id_gracza")->fetch_assoc();
if (!$gracz) {
    echo json_encode(['ok' => false, 'msg' => 'Brak danych gracza']);
    exit;
}
$jest_mg = (bool)$gracz['is_mg'];

$op = $_REQUEST['op'] ?? 'lista';

// ═══════════════════════════════════════════════════════════════════
// LISTA PLOTEK
// ═══════════════════════════════════════════════════════════════════
if ($op === 'lista') {
    $q = $polaczenie->query("
        SELECT p.id, p.tresc, p.czas, p.licznik_prawda, p.licznik_falsz,
               (SELECT typ FROM klub_plotki_reakcje WHERE plotka_id=p.id AND gracz_id=$id_gracza LIMIT 1) AS moja_reakcja
        FROM klub_plotki p
        WHERE p.aktywna=1
        ORDER BY p.id DESC
        LIMIT 30
    ");
    $plotki = [];
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $ts = strtotime($r['czas']);
            $teraz = time();
            $minuty = (int)(($teraz - $ts) / 60);
            if ($minuty < 60) $kiedy = $minuty . ' min temu';
            elseif ($minuty < 1440) $kiedy = (int)($minuty / 60) . ' godz. temu';
            else $kiedy = date('d.m', $ts);

            $plotki[] = [
                'id' => (int)$r['id'],
                'tresc' => $r['tresc'],
                'kiedy' => $kiedy,
                'czas' => date('H:i', $ts),
                'prawda' => (int)$r['licznik_prawda'],
                'falsz' => (int)$r['licznik_falsz'],
                'moja_reakcja' => $r['moja_reakcja'], // null|prawda|falsz
            ];
        }
    }
    echo json_encode(['ok' => true, 'plotki' => $plotki]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// DODAJ PLOTKĘ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'dodaj') {
    $tresc = trim($_POST['tresc'] ?? '');
    if (mb_strlen($tresc) < 10) {
        echo json_encode(['ok' => false, 'msg' => 'Plotka musi mieć min. 10 znaków']);
        exit;
    }
    if (mb_strlen($tresc) > 50000) {
        echo json_encode(['ok' => false, 'msg' => 'Plotka ekstremalnie długa (>50k znaków)']);
        exit;
    }
    // Anti-spam: max 5 plotek na godzinę
    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_plotki WHERE autor_id=$id_gracza AND czas >= NOW() - INTERVAL 1 HOUR")->fetch_assoc();
    if ((int)$r['c'] >= 5) {
        echo json_encode(['ok' => false, 'msg' => 'Już rzuciłeś 5 plotek w ostatniej godzinie. Powstrzymaj się chwilę.']);
        exit;
    }
    $tresc_e = $polaczenie->real_escape_string($tresc);
    $polaczenie->query("INSERT INTO klub_plotki (autor_id, tresc) VALUES ($id_gracza, '$tresc_e')");
    echo json_encode(['ok' => true, 'msg' => 'Plotka rzucona — ktoś podsłucha.']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// REAGUJ (prawda / falsz)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'reaguj') {
    $pid = (int)($_POST['id'] ?? 0);
    $typ = $_POST['typ'] ?? '';
    if (!in_array($typ, ['prawda','falsz'], true)) {
        echo json_encode(['ok' => false, 'msg' => 'Niepoprawny typ reakcji']);
        exit;
    }
    $plotka = $polaczenie->query("SELECT id FROM klub_plotki WHERE id=$pid AND aktywna=1")->fetch_assoc();
    if (!$plotka) {
        echo json_encode(['ok' => false, 'msg' => 'Plotka nie istnieje']);
        exit;
    }
    // Sprawdź czy gracz już reagował
    $istn = $polaczenie->query("SELECT id, typ FROM klub_plotki_reakcje WHERE plotka_id=$pid AND gracz_id=$id_gracza")->fetch_assoc();
    if ($istn) {
        if ($istn['typ'] === $typ) {
            // Cofnij reakcję (toggle off)
            $polaczenie->query("DELETE FROM klub_plotki_reakcje WHERE id=" . (int)$istn['id']);
        } else {
            // Zmień typ reakcji
            $typ_e = $polaczenie->real_escape_string($typ);
            $polaczenie->query("UPDATE klub_plotki_reakcje SET typ='$typ_e' WHERE id=" . (int)$istn['id']);
        }
    } else {
        // Nowa reakcja
        $typ_e = $polaczenie->real_escape_string($typ);
        $polaczenie->query("INSERT INTO klub_plotki_reakcje (plotka_id, gracz_id, typ) VALUES ($pid, $id_gracza, '$typ_e')");
    }
    // Przelicz cache liczników
    $cnt_p = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_plotki_reakcje WHERE plotka_id=$pid AND typ='prawda'")->fetch_assoc();
    $cnt_f = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_plotki_reakcje WHERE plotka_id=$pid AND typ='falsz'")->fetch_assoc();
    $cp = (int)$cnt_p['c'];
    $cf = (int)$cnt_f['c'];
    $polaczenie->query("UPDATE klub_plotki SET licznik_prawda=$cp, licznik_falsz=$cf WHERE id=$pid");

    // Zwróć aktualny stan
    $moja = $polaczenie->query("SELECT typ FROM klub_plotki_reakcje WHERE plotka_id=$pid AND gracz_id=$id_gracza")->fetch_assoc();
    echo json_encode([
        'ok' => true,
        'prawda' => $cp,
        'falsz' => $cf,
        'moja_reakcja' => $moja ? $moja['typ'] : null,
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// LISTA Z AUTORAMI (TYLKO MG — moderacja)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'lista_moderacja') {
    if (!$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko MG ma dostęp do panelu moderacji']);
        exit;
    }
    $pokazac_usuniete = isset($_GET['usuniete']) && $_GET['usuniete'] === '1';
    $where = $pokazac_usuniete ? '' : 'WHERE p.aktywna=1';

    $q = $polaczenie->query("
        SELECT p.id, p.tresc, p.czas, p.aktywna,
               p.licznik_prawda, p.licznik_falsz,
               p.autor_id, g.login AS autor_login
        FROM klub_plotki p
        LEFT JOIN gracze g ON g.id = p.autor_id
        $where
        ORDER BY p.id DESC
        LIMIT 100
    ");
    $plotki = [];
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $plotki[] = [
                'id' => (int)$r['id'],
                'tresc' => $r['tresc'],
                'czas' => date('d.m.Y H:i', strtotime($r['czas'])),
                'autor_id' => (int)$r['autor_id'],
                'autor_login' => $r['autor_login'] ?? 'usunięty gracz',
                'aktywna' => (bool)$r['aktywna'],
                'prawda' => (int)$r['licznik_prawda'],
                'falsz' => (int)$r['licznik_falsz'],
            ];
        }
    }
    echo json_encode(['ok' => true, 'plotki' => $plotki]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// USUŃ PLOTKĘ (tylko MG)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'usun') {
    if (!$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko MG może usuwać plotki']);
        exit;
    }
    $pid = (int)($_POST['id'] ?? 0);
    $polaczenie->query("UPDATE klub_plotki SET aktywna=0 WHERE id=$pid");
    echo json_encode(['ok' => true, 'msg' => 'Plotka usunięta']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// PRZYWRÓĆ PLOTKĘ (tylko MG)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'przywroc') {
    if (!$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko MG może przywracać']);
        exit;
    }
    $pid = (int)($_POST['id'] ?? 0);
    $polaczenie->query("UPDATE klub_plotki SET aktywna=1 WHERE id=$pid");
    echo json_encode(['ok' => true, 'msg' => 'Plotka przywrócona']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);