<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB EVENTY
//
// Operacje:
//   GET  ?op=lista                 — lista nadchodzących + aktywnych
//   POST op=utworz                 — tylko barman/MG
//   POST op=anuluj id=X            — tylko autor/MG
//   POST op=rezerwuj id=X          — gracz zapisuje się
//   POST op=anuluj_rezerwacje id=X — gracz wypisuje się
//
// Zwraca: { ok, msg?, dane? }
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
$jest_barmanem = (bool)$gracz['is_barman'];
$jest_mg       = (bool)$gracz['is_mg'];
$moze_tworzyc  = ($jest_barmanem || $jest_mg);

$op = $_REQUEST['op'] ?? 'lista';

$DOZWOLONE_KOLORY = ['pink','gold','purple','red','cyan'];
$DOZWOLONE_SALE   = ['sala-glowna','sala-balowa','sauna','bdsm','tyly','vip','taras','basen','silownia','garderoba','masaze','lobby'];

// ── HELPERY ──────────────────────────────────────────────────────
function pobierz_event($polaczenie, $id) {
    $id = (int)$id;
    $q = $polaczenie->query("SELECT * FROM klub_wydarzenia WHERE id=$id");
    return $q ? $q->fetch_assoc() : null;
}
function policz_rezerwacje($polaczenie, $eid) {
    $eid = (int)$eid;
    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_rezerwacje WHERE wydarzenie_id=$eid")->fetch_assoc();
    return (int)$r['c'];
}

// ═══════════════════════════════════════════════════════════════════
// LISTA WYDARZEŃ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'lista') {
    $tylko_nadchodzace = isset($_GET['tylko_nadchodzace']) ? (int)$_GET['tylko_nadchodzace'] : 1;
    $where = "WHERE w.aktywne=1 AND w.anulowane=0";
    if ($tylko_nadchodzace) {
        $where .= " AND (w.data_konca IS NULL AND w.data_startu >= NOW() - INTERVAL 4 HOUR
                     OR w.data_konca IS NOT NULL AND w.data_konca >= NOW())";
    }

    $q = $polaczenie->query("
        SELECT w.*, g.login AS autor_login,
               (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id) AS liczba_rezerwacji,
               (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id AND gracz_id=$id_gracza) AS moja_rezerwacja
        FROM klub_wydarzenia w
        LEFT JOIN gracze g ON g.id = w.autor_id
        $where
        ORDER BY w.data_startu ASC
        LIMIT 50
    ");

    $eventy = [];
    if ($q) {
        while ($e = $q->fetch_assoc()) {
            $eventy[] = [
                'id'              => (int)$e['id'],
                'nazwa'           => $e['nazwa'],
                'opis'            => $e['opis'],
                'sala'            => $e['sala'],
                'data_startu'     => $e['data_startu'],
                'data_konca'      => $e['data_konca'],
                'data_startu_fmt' => date('d.m H:i', strtotime($e['data_startu'])),
                'data_startu_dni' => strftime_pl($e['data_startu']),
                'max_miejsc'      => (int)$e['max_miejsc'],
                'kolor_plakatu'   => $e['kolor_plakatu'],
                'ikona_emoji'     => $e['ikona_emoji'],
                'plakat_url'     => $e['plakat_url'],
                'autor_id'        => (int)$e['autor_id'],
                'autor_login'     => $e['autor_login'],
                'liczba_rezerwacji' => (int)$e['liczba_rezerwacji'],
                'moja_rezerwacja' => ((int)$e['moja_rezerwacja'] > 0),
                'czy_moge_edytowac' => ($id_gracza === (int)$e['autor_id'] || $jest_mg),
                'czy_juz_zaczal'  => (strtotime($e['data_startu']) <= time()),
                'czy_juz_skonczyl' => ($e['data_konca'] && strtotime($e['data_konca']) <= time()),
            ];
        }
    }

    echo json_encode(['ok' => true, 'eventy' => $eventy]);
    exit;
}

// Helper: polski opis daty
function strftime_pl($data) {
    $t = strtotime($data);
    $dni = ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'];
    $dzien = $dni[(int)date('w', $t)];
    return $dzien . ' · ' . date('d.m', $t) . ' · ' . date('H:i', $t);
}

// ═══════════════════════════════════════════════════════════════════
// UTWÓRZ EVENT (barman/MG)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'utworz') {
    if (!$moze_tworzyc) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman lub MG mogą tworzyć wydarzenia']);
        exit;
    }

    $nazwa = trim($_POST['nazwa'] ?? '');
    $opis  = trim($_POST['opis'] ?? '');
    $sala  = strtolower(trim($_POST['sala'] ?? 'sala-balowa'));
    $data_startu = trim($_POST['data_startu'] ?? '');
    $data_konca  = trim($_POST['data_konca'] ?? '');
    $max_miejsc = max(1, min(200, (int)($_POST['max_miejsc'] ?? 30)));
    $kolor = strtolower(trim($_POST['kolor_plakatu'] ?? 'pink'));
    $ikona = trim($_POST['ikona_emoji'] ?? '✦');

    // Walidacje
    if (mb_strlen($nazwa) < 3 || mb_strlen($nazwa) > 120) {
        echo json_encode(['ok' => false, 'msg' => 'Nazwa: 3–120 znaków']);
        exit;
    }
    if (mb_strlen($opis) > 2000) {
        echo json_encode(['ok' => false, 'msg' => 'Opis: max 2000 znaków']);
        exit;
    }
    if (!in_array($sala, $DOZWOLONE_SALE, true)) {
        echo json_encode(['ok' => false, 'msg' => 'Niewłaściwa sala']);
        exit;
    }
    if (!in_array($kolor, $DOZWOLONE_KOLORY, true)) $kolor = 'pink';
    $ikona = mb_substr($ikona, 0, 4); // emoji max 1-2 znaki UTF-8

    $ts_startu = strtotime($data_startu);
    if (!$ts_startu) {
        echo json_encode(['ok' => false, 'msg' => 'Niepoprawna data startu']);
        exit;
    }
    if ($ts_startu < time() - 600) { // -10 min tolerancja
        echo json_encode(['ok' => false, 'msg' => 'Data startu nie może być w przeszłości']);
        exit;
    }
    if ($ts_startu > time() + 60 * 86400) {
        echo json_encode(['ok' => false, 'msg' => 'Data startu nie może być dalej niż 60 dni']);
        exit;
    }

    $data_startu_sql = date('Y-m-d H:i:s', $ts_startu);
    $data_konca_sql = 'NULL';
    if ($data_konca) {
        $ts_konca = strtotime($data_konca);
        if (!$ts_konca || $ts_konca <= $ts_startu) {
            echo json_encode(['ok' => false, 'msg' => 'Data końca musi być po dacie startu']);
            exit;
        }
        $data_konca_sql = "'" . date('Y-m-d H:i:s', $ts_konca) . "'";
    }

    // Escape
    $nazwa_e = $polaczenie->real_escape_string($nazwa);
    $opis_e  = $polaczenie->real_escape_string($opis);
    $sala_e  = $polaczenie->real_escape_string($sala);
    $kolor_e = $polaczenie->real_escape_string($kolor);
    $ikona_e = $polaczenie->real_escape_string($ikona);

    // ── INSERT eventu (bez obrazka — dodamy w drugim kroku) ──
    $polaczenie->query("
        INSERT INTO klub_wydarzenia (nazwa, opis, sala, data_startu, data_konca, max_miejsc, kolor_plakatu, ikona_emoji, autor_id)
        VALUES ('$nazwa_e', '$opis_e', '$sala_e', '$data_startu_sql', $data_konca_sql, $max_miejsc, '$kolor_e', '$ikona_e', $id_gracza)
    ");
    $eid = $polaczenie->insert_id;

    // ── FAZA 4: UPLOAD PLAKATU (jeśli podano) ─────────────────
    $plakat_url = null;
    if (!empty($_FILES['plakat']) && $_FILES['plakat']['error'] === UPLOAD_ERR_OK) {
        $upl = klub_upload_plakat($_FILES['plakat'], $eid);
        if ($upl['ok']) {
            $plakat_url = $upl['url'];
            $plakat_url_sql = $polaczenie->real_escape_string($plakat_url);
            $polaczenie->query("UPDATE klub_wydarzenia SET plakat_url='$plakat_url_sql' WHERE id=$eid");
        } else {
            // Plakat się nie udał — event istnieje, ale dodajemy info do response
            // (event NIE jest cofany, bo upload to opcja, nie wymóg)
            $err_plakatu = $upl['msg'];
        }
    }

    // Wyślij ogłoszenie do czatu sali głównej
    $sys = "✦ <b>{$gracz['login']}</b> ogłasza nowe wydarzenie: <b>" . htmlspecialchars($nazwa) . "</b> — " . date('d.m H:i', $ts_startu);
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");

    $resp = ['ok' => true, 'msg' => 'Wydarzenie utworzone', 'id' => $eid];
    if (!empty($err_plakatu)) {
        $resp['msg'] .= ' (uwaga z plakatem: ' . $err_plakatu . ')';
    }
    if ($plakat_url) {
        $resp['plakat_url'] = $plakat_url;
    }
    echo json_encode($resp);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// HELPER: UPLOAD PLAKATU (wywoływany z op=utworz)
// ═══════════════════════════════════════════════════════════════════
function klub_upload_plakat($file, $event_id) {
    $UPLOAD_DIR_REL = 'uploads/eventy/';
    $UPLOAD_DIR_ABS = __DIR__ . '/../' . $UPLOAD_DIR_REL;

    // Utwórz folder jeśli nie ma
    if (!is_dir($UPLOAD_DIR_ABS)) {
        @mkdir($UPLOAD_DIR_ABS, 0755, true);
    }
    if (!is_dir($UPLOAD_DIR_ABS) || !is_writable($UPLOAD_DIR_ABS)) {
        return ['ok' => false, 'msg' => 'Folder uploadu nie jest zapisywalny: ' . $UPLOAD_DIR_REL];
    }

    // Sprawdź rozmiar (max 5 MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'Plik za duży (max 5 MB)'];
    }
    if ($file['size'] < 1024) {
        return ['ok' => false, 'msg' => 'Plik za mały'];
    }

    // Sprawdź MIME (magic bytes, nie rozszerzenie)
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'Niepoprawny upload'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'msg' => 'Format nieobsługiwany (akceptujemy JPG, PNG, WebP)'];
    }
    $ext_src = $allowed[$mime];

    // Resize i konwersja do JPG (oszczędza miejsce)
    if (!extension_loaded('gd')) {
        // Brak GD — zapisz oryginał
        $hash = substr(md5(uniqid('', true)), 0, 8);
        $target = $UPLOAD_DIR_ABS . $event_id . '_' . $hash . '.' . $ext_src;
        if (!move_uploaded_file($tmp, $target)) {
            return ['ok' => false, 'msg' => 'Nie udało się zapisać pliku'];
        }
        return ['ok' => true, 'url' => $UPLOAD_DIR_REL . basename($target)];
    }

    // Wczytaj obraz przez GD
    $img = null;
    if ($mime === 'image/jpeg') $img = @imagecreatefromjpeg($tmp);
    elseif ($mime === 'image/png') $img = @imagecreatefrompng($tmp);
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($tmp);
    if (!$img) {
        return ['ok' => false, 'msg' => 'Nie udało się odczytać obrazu'];
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $MAX_W = 800;
    $MAX_H = 1200;
    $ratio = min($MAX_W / $w, $MAX_H / $h, 1.0);

    if ($ratio < 1.0) {
        $new_w = (int)round($w * $ratio);
        $new_h = (int)round($h * $ratio);
        $resized = imagecreatetruecolor($new_w, $new_h);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $hash = substr(md5(uniqid('', true)), 0, 8);
    $target = $UPLOAD_DIR_ABS . $event_id . '_' . $hash . '.jpg';
    $ok = imagejpeg($img, $target, 82);
    imagedestroy($img);

    if (!$ok) {
        return ['ok' => false, 'msg' => 'Nie udało się zapisać przeskalowanego pliku'];
    }
    return ['ok' => true, 'url' => $UPLOAD_DIR_REL . basename($target)];
}

// ═══════════════════════════════════════════════════════════════════
// EDYTUJ EVENT (Faza 6)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'edytuj') {
    $eid = (int)($_POST['id'] ?? 0);
    $event = pobierz_event($polaczenie, $eid);
    if (!$event) {
        echo json_encode(['ok' => false, 'msg' => 'Nie ma takiego wydarzenia']);
        exit;
    }
    if ((int)$event['autor_id'] !== $id_gracza && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Możesz edytować tylko swoje wydarzenia']);
        exit;
    }
    if ((int)$event['anulowane'] === 1) {
        echo json_encode(['ok' => false, 'msg' => 'Wydarzenie jest anulowane — nie można edytować']);
        exit;
    }

    // Możliwe pola do zmiany
    $updates = [];

    if (isset($_POST['nazwa']) && trim($_POST['nazwa']) !== '') {
        $nazwa = trim($_POST['nazwa']);
        if (mb_strlen($nazwa) < 3 || mb_strlen($nazwa) > 120) {
            echo json_encode(['ok' => false, 'msg' => 'Nazwa: 3–120 znaków']);
            exit;
        }
        $updates[] = "nazwa='" . $polaczenie->real_escape_string($nazwa) . "'";
    }

    if (isset($_POST['opis'])) {
        $opis = trim($_POST['opis']);
        if (mb_strlen($opis) > 50000) {
            echo json_encode(['ok' => false, 'msg' => 'Opis: max 50000 znaków']);
            exit;
        }
        $updates[] = "opis='" . $polaczenie->real_escape_string($opis) . "'";
    }

    if (isset($_POST['data_startu']) && trim($_POST['data_startu']) !== '') {
        $ts_startu = strtotime(trim($_POST['data_startu']));
        if (!$ts_startu) {
            echo json_encode(['ok' => false, 'msg' => 'Niepoprawna data startu']);
            exit;
        }
        // Pozwalamy na przeszłość (np. żeby wcześniejsza godzina była "trwa")
        // ale nie więcej niż 60 dni do tyłu (sanity)
        if ($ts_startu < time() - 60 * 86400) {
            echo json_encode(['ok' => false, 'msg' => 'Data startu nie może być starsza niż 60 dni']);
            exit;
        }
        if ($ts_startu > time() + 60 * 86400) {
            echo json_encode(['ok' => false, 'msg' => 'Data startu nie dalej niż 60 dni w przyszłość']);
            exit;
        }
        $updates[] = "data_startu='" . date('Y-m-d H:i:s', $ts_startu) . "'";
        // Reset powiadomień gdy zmienia się start (żeby gracze dostali ponownie)
        $polaczenie->query("DELETE FROM klub_powiadomienia_eventow WHERE wydarzenie_id=$eid AND typ='30min'");
    }

    if (isset($_POST['data_konca'])) {
        $dk = trim($_POST['data_konca']);
        if ($dk === '' || $dk === 'null') {
            $updates[] = "data_konca=NULL";
        } else {
            $ts_konca = strtotime($dk);
            if (!$ts_konca) {
                echo json_encode(['ok' => false, 'msg' => 'Niepoprawna data końca']);
                exit;
            }
            $updates[] = "data_konca='" . date('Y-m-d H:i:s', $ts_konca) . "'";
        }
    }

    if (isset($_POST['sala'])) {
        $sala_e = strtolower(trim($_POST['sala']));
        if (!in_array($sala_e, $DOZWOLONE_SALE, true)) {
            echo json_encode(['ok' => false, 'msg' => 'Niewłaściwa sala']);
            exit;
        }
        $updates[] = "sala='" . $polaczenie->real_escape_string($sala_e) . "'";
    }

    if (isset($_POST['max_miejsc'])) {
        $mm = max(1, min(200, (int)$_POST['max_miejsc']));
        $updates[] = "max_miejsc=$mm";
    }

    if (isset($_POST['kolor_plakatu'])) {
        $k = strtolower(trim($_POST['kolor_plakatu']));
        if (in_array($k, $DOZWOLONE_KOLORY, true)) {
            $updates[] = "kolor_plakatu='" . $polaczenie->real_escape_string($k) . "'";
        }
    }

    if (isset($_POST['ikona_emoji']) && trim($_POST['ikona_emoji']) !== '') {
        $ikona = mb_substr(trim($_POST['ikona_emoji']), 0, 4);
        $updates[] = "ikona_emoji='" . $polaczenie->real_escape_string($ikona) . "'";
    }

    // Plakat — upload nowego (zastępuje stary)
    if (!empty($_FILES['plakat']) && !empty($_FILES['plakat']['tmp_name'])) {
        $upl = klub_upload_plakat($_FILES['plakat'], $eid);
        if ($upl['ok']) {
            // Usuń stary plik jeśli istniał
            if (!empty($event['plakat_url'])) {
                $stary = __DIR__ . '/../' . $event['plakat_url'];
                if (file_exists($stary)) @unlink($stary);
            }
            $updates[] = "plakat_url='" . $polaczenie->real_escape_string($upl['url']) . "'";
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Plakat: ' . $upl['msg']]);
            exit;
        }
    }

    // Usuń plakat (osobna flaga)
    if (isset($_POST['usun_plakat']) && (int)$_POST['usun_plakat'] === 1) {
        if (!empty($event['plakat_url'])) {
            $stary = __DIR__ . '/../' . $event['plakat_url'];
            if (file_exists($stary)) @unlink($stary);
        }
        $updates[] = "plakat_url=NULL";
    }

    if (empty($updates)) {
        echo json_encode(['ok' => false, 'msg' => 'Nic do zmiany']);
        exit;
    }

    $sql = "UPDATE klub_wydarzenia SET " . implode(', ', $updates) . " WHERE id=$eid";
    if ($polaczenie->query($sql)) {
        // Powiadom zarezerwowanych o zmianie
        $rez = $polaczenie->query("SELECT gracz_id FROM klub_rezerwacje WHERE wydarzenie_id=$eid");
        if ($rez) {
            $tresc_pow = "✏️ Wydarzenie <b>" . htmlspecialchars($event['nazwa']) . "</b> zostało zaktualizowane przez <b>{$gracz['login']}</b>. Sprawdź szczegóły.";
            $tresc_pow_sql = $polaczenie->real_escape_string($tresc_pow);
            while ($r = $rez->fetch_assoc()) {
                $gid = (int)$r['gracz_id'];
                if ($gid !== $id_gracza) {
                    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($gid, '$tresc_pow_sql')");
                }
            }
        }
        echo json_encode(['ok' => true, 'msg' => '✓ Wydarzenie zaktualizowane']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Błąd zapisu: ' . $polaczenie->error]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ANULUJ EVENT
// ═══════════════════════════════════════════════════════════════════
if ($op === 'anuluj') {
    $eid = (int)($_POST['id'] ?? 0);
    $event = pobierz_event($polaczenie, $eid);
    if (!$event) {
        echo json_encode(['ok' => false, 'msg' => 'Nie ma takiego wydarzenia']);
        exit;
    }
    if ((int)$event['autor_id'] !== $id_gracza && !$jest_mg) {
        echo json_encode(['ok' => false, 'msg' => 'Możesz anulować tylko swoje wydarzenia']);
        exit;
    }

    $powod = $polaczenie->real_escape_string(mb_substr(trim($_POST['powod'] ?? ''), 0, 200));
    $polaczenie->query("UPDATE klub_wydarzenia SET anulowane=1, aktywne=0, powod_anulowania='$powod' WHERE id=$eid");

    // Powiadom wszystkich zarezerwowanych
    $rez = $polaczenie->query("SELECT gracz_id FROM klub_rezerwacje WHERE wydarzenie_id=$eid");
    if ($rez) {
        $tresc_pow = "❌ Wydarzenie <b>" . htmlspecialchars($event['nazwa']) . "</b> zostało anulowane.";
        if ($powod) $tresc_pow .= " <i>Powód: $powod</i>";
        $tresc_pow_sql = $polaczenie->real_escape_string($tresc_pow);
        while ($r = $rez->fetch_assoc()) {
            $gid = (int)$r['gracz_id'];
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($gid, '$tresc_pow_sql')");
        }
    }

    // Sys msg w czacie
    $sys = "❌ Wydarzenie <b>" . htmlspecialchars($event['nazwa']) . "</b> zostało anulowane przez {$gracz['login']}.";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");

    echo json_encode(['ok' => true, 'msg' => 'Wydarzenie anulowane']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// REZERWUJ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'rezerwuj') {
    $eid = (int)($_POST['id'] ?? 0);
    $event = pobierz_event($polaczenie, $eid);
    if (!$event || $event['anulowane'] || !$event['aktywne']) {
        echo json_encode(['ok' => false, 'msg' => 'Wydarzenie nie istnieje lub anulowane']);
        exit;
    }
    if (strtotime($event['data_startu']) <= time() - 1800) {
        echo json_encode(['ok' => false, 'msg' => 'Wydarzenie już zaczęło się ponad 30 min temu']);
        exit;
    }

    $istn = $polaczenie->query("SELECT id FROM klub_rezerwacje WHERE wydarzenie_id=$eid AND gracz_id=$id_gracza")->fetch_assoc();
    if ($istn) {
        echo json_encode(['ok' => false, 'msg' => 'Już masz rezerwację']);
        exit;
    }

    $aktualne = policz_rezerwacje($polaczenie, $eid);
    if ($aktualne >= (int)$event['max_miejsc']) {
        echo json_encode(['ok' => false, 'msg' => 'Brak wolnych miejsc']);
        exit;
    }

    $polaczenie->query("INSERT INTO klub_rezerwacje (wydarzenie_id, gracz_id) VALUES ($eid, $id_gracza)");
    echo json_encode(['ok' => true, 'msg' => 'Rezerwacja zapisana — zobaczymy się!']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ANULUJ REZERWACJĘ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'anuluj_rezerwacje') {
    $eid = (int)($_POST['id'] ?? 0);
    $polaczenie->query("DELETE FROM klub_rezerwacje WHERE wydarzenie_id=$eid AND gracz_id=$id_gracza");
    echo json_encode(['ok' => true, 'msg' => 'Rezerwacja anulowana']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);