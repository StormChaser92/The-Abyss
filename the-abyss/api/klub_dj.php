<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB DJ (Faza 4 — fabularny system)
// 
// GET  ?op=stan                    — aktualny utwór + kolejka
// POST op=zamow tytul=... [artysta=] [notka=]   — gracz zamawia
// POST op=ustaw_grany id=X         — DJ (barman/MG) ustawia ten utwór jako grany
// POST op=ogloszenie_recznie       — DJ ogłasza utwór bez kolejki
// POST op=zakoncz                  — kończy aktualny utwór (przesuwa kolejkę)
// POST op=odrzuc id=X              — DJ odrzuca zamówienie z kolejki
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
$jest_djem = ($gracz['is_barman'] || $gracz['is_mg']);

$op = $_REQUEST['op'] ?? 'stan';

// ═══════════════════════════════════════════════════════════════════
// STAN — aktualny utwór + kolejka
// ═══════════════════════════════════════════════════════════════════
if ($op === 'stan') {
    // Aktualny utwór
    $aktualny = null;
    $a = $polaczenie->query("
        SELECT a.*, k.tytul AS tytul_k, k.artysta AS artysta_k, k.notka,
               gz.login AS zamawiajacy
        FROM klub_dj_aktualny a
        LEFT JOIN klub_dj_kolejka k ON k.id = a.kolejka_id
        LEFT JOIN gracze gz ON gz.id = k.gracz_id
        WHERE a.id=1
    ")->fetch_assoc();
    if ($a) {
        $tytul = $a['tytul_k'] ?: $a['tytul_recznie'];
        $artysta = $a['artysta_k'] ?: $a['artysta_recznie'];
        if ($tytul) {
            $aktualny = [
                'tytul' => $tytul,
                'artysta' => $artysta,
                'notka' => $a['notka'],
                'zamawiajacy' => $a['zamawiajacy'],
                'dj_login' => $a['dj_login'],
                'czas_startu' => $a['czas_startu'],
                'gra_od' => $a['czas_startu'] ? date('H:i', strtotime($a['czas_startu'])) : null,
            ];
        }
    }

    // Kolejka
    $kolejka = [];
    $q = $polaczenie->query("
        SELECT k.*, g.login AS zamawiajacy_login
        FROM klub_dj_kolejka k
        LEFT JOIN gracze g ON g.id = k.gracz_id
        WHERE k.status='w_kolejce'
        ORDER BY k.id ASC
        LIMIT 20
    ");
    if ($q) while ($r = $q->fetch_assoc()) {
        $kolejka[] = [
            'id' => (int)$r['id'],
            'tytul' => $r['tytul'],
            'artysta' => $r['artysta'],
            'notka' => $r['notka'],
            'zamawiajacy' => $r['zamawiajacy_login'] ?? '???',
            'gracz_id' => (int)$r['gracz_id'],
            'czy_moje' => ((int)$r['gracz_id'] === $id_gracza),
            'czas' => date('H:i', strtotime($r['czas_zamowienia'])),
        ];
    }

    echo json_encode([
        'ok' => true,
        'aktualny' => $aktualny,
        'kolejka' => $kolejka,
        'jest_djem' => $jest_djem,
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ZAMÓW UTWÓR
// ═══════════════════════════════════════════════════════════════════
if ($op === 'zamow') {
    $tytul = trim($_POST['tytul'] ?? '');
    $artysta = trim($_POST['artysta'] ?? '');
    $notka = trim($_POST['notka'] ?? '');

    if (mb_strlen($tytul) < 2 || mb_strlen($tytul) > 150) {
        echo json_encode(['ok' => false, 'msg' => 'Tytuł: 2–150 znaków']);
        exit;
    }
    if (mb_strlen($artysta) > 100) {
        echo json_encode(['ok' => false, 'msg' => 'Artysta: max 100 znaków']);
        exit;
    }
    if (mb_strlen($notka) > 200) {
        echo json_encode(['ok' => false, 'msg' => 'Notka: max 200 znaków']);
        exit;
    }
    // Anti-spam: max 3 utwory w kolejce na gracza
    $r = $polaczenie->query("SELECT COUNT(*) AS c FROM klub_dj_kolejka WHERE gracz_id=$id_gracza AND status='w_kolejce'")->fetch_assoc();
    if ((int)$r['c'] >= 3) {
        echo json_encode(['ok' => false, 'msg' => 'Masz już 3 utwory w kolejce — poczekaj']);
        exit;
    }

    $tytul_e = $polaczenie->real_escape_string($tytul);
    $artysta_e = $artysta === '' ? 'NULL' : "'" . $polaczenie->real_escape_string($artysta) . "'";
    $notka_e = $notka === '' ? 'NULL' : "'" . $polaczenie->real_escape_string($notka) . "'";

    $polaczenie->query("INSERT INTO klub_dj_kolejka (gracz_id, tytul, artysta, notka) VALUES ($id_gracza, '$tytul_e', $artysta_e, $notka_e)");

    // System message
    $opis = htmlspecialchars($tytul) . ($artysta ? " — " . htmlspecialchars($artysta) : '');
    $sys = "🎵 <b>{$gracz['login']}</b> zamawia u DJ-a: <i>$opis</i>" . ($notka ? " · <small>" . htmlspecialchars($notka) . "</small>" : "");
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");

    echo json_encode(['ok' => true, 'msg' => 'Utwór zamówiony — DJ zobaczy']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// USTAW JAKO GRANY (DJ)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'ustaw_grany') {
    if (!$jest_djem) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman/MG może puszczać utwory']);
        exit;
    }
    $kid = (int)($_POST['id'] ?? 0);
    $u = $polaczenie->query("SELECT k.*, g.login FROM klub_dj_kolejka k LEFT JOIN gracze g ON g.id=k.gracz_id WHERE k.id=$kid AND k.status='w_kolejce'")->fetch_assoc();
    if (!$u) {
        echo json_encode(['ok' => false, 'msg' => 'Utwór nie istnieje lub już zagrany']);
        exit;
    }
    // Zakończ aktualny (jeśli jest)
    $polaczenie->query("UPDATE klub_dj_kolejka SET status='zagrany', czas_zagrania=NOW() WHERE status='grany'");
    // Ustaw nowy
    $polaczenie->query("UPDATE klub_dj_kolejka SET status='grany' WHERE id=$kid");
    $login_e = $polaczenie->real_escape_string($gracz['login']);
    $polaczenie->query("UPDATE klub_dj_aktualny SET kolejka_id=$kid, tytul_recznie=NULL, artysta_recznie=NULL, dj_login='$login_e', dj_id=$id_gracza, czas_startu=NOW() WHERE id=1");

    // System message
    $opis = htmlspecialchars($u['tytul']) . ($u['artysta'] ? " — " . htmlspecialchars($u['artysta']) : '');
    $sys = "🎧 DJ <b>{$gracz['login']}</b> puszcza: <i>$opis</i>";
    if ($u['login']) $sys .= " <small>(zamówione przez {$u['login']})</small>";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");

    echo json_encode(['ok' => true, 'msg' => '🎧 Puściłeś ten utwór']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// OGŁOSZENIE RĘCZNE (bez kolejki)
// ═══════════════════════════════════════════════════════════════════
if ($op === 'ogloszenie_recznie') {
    if (!$jest_djem) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman/MG']);
        exit;
    }
    $tytul = trim($_POST['tytul'] ?? '');
    $artysta = trim($_POST['artysta'] ?? '');
    if (mb_strlen($tytul) < 2 || mb_strlen($tytul) > 150) {
        echo json_encode(['ok' => false, 'msg' => 'Tytuł: 2–150 znaków']);
        exit;
    }
    $tytul_e = $polaczenie->real_escape_string($tytul);
    $artysta_e = $artysta === '' ? 'NULL' : "'" . $polaczenie->real_escape_string(mb_substr($artysta, 0, 100)) . "'";
    // Zakończ poprzedni
    $polaczenie->query("UPDATE klub_dj_kolejka SET status='zagrany', czas_zagrania=NOW() WHERE status='grany'");
    $login_e = $polaczenie->real_escape_string($gracz['login']);
    $polaczenie->query("UPDATE klub_dj_aktualny SET kolejka_id=NULL, tytul_recznie='$tytul_e', artysta_recznie=$artysta_e, dj_login='$login_e', dj_id=$id_gracza, czas_startu=NOW() WHERE id=1");

    $opis = htmlspecialchars($tytul) . ($artysta ? " — " . htmlspecialchars($artysta) : '');
    $sys = "🎧 DJ <b>{$gracz['login']}</b> puszcza: <i>$opis</i>";
    $sys_sql = $polaczenie->real_escape_string($sys);
    $polaczenie->query("INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', '$sys_sql', 'sala-glowna', 'system')");

    echo json_encode(['ok' => true, 'msg' => '🎧 Ogłoszone']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ZAKOŃCZ AKTUALNY
// ═══════════════════════════════════════════════════════════════════
if ($op === 'zakoncz') {
    if (!$jest_djem) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman/MG']);
        exit;
    }
    $polaczenie->query("UPDATE klub_dj_kolejka SET status='zagrany', czas_zagrania=NOW() WHERE status='grany'");
    $polaczenie->query("UPDATE klub_dj_aktualny SET kolejka_id=NULL, tytul_recznie=NULL, artysta_recznie=NULL, dj_login=NULL, dj_id=NULL, czas_startu=NULL WHERE id=1");
    echo json_encode(['ok' => true, 'msg' => 'Cisza w klubie']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// ODRZUĆ
// ═══════════════════════════════════════════════════════════════════
if ($op === 'odrzuc') {
    if (!$jest_djem) {
        echo json_encode(['ok' => false, 'msg' => 'Tylko Barman/MG']);
        exit;
    }
    $kid = (int)($_POST['id'] ?? 0);
    $polaczenie->query("UPDATE klub_dj_kolejka SET status='odrzucony' WHERE id=$kid AND status='w_kolejce'");
    echo json_encode(['ok' => true, 'msg' => 'Odrzucone']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Nieznana operacja']);