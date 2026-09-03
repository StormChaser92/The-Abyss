<?php
// ════════════════════════════════════════════════════════════════════
// API — KLUB FEED (pull nowych wiadomości)
// 
// GET ?sala=sala-glowna&od_id=123
// 
// Zwraca JSON: { ok, wiadomosci: [...], rachunek: {...}, obecni: [...] }
// 
// Bezpieczeństwo: tylko zalogowani gracze, sala musi być w whitelistcie,
// nigdy nie modyfikuje stanu (tylko SELECT).
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
$sala = isset($_GET['sala']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['sala'])) : 'sala-glowna';
$od_id = isset($_GET['od_id']) ? (int)$_GET['od_id'] : 0;

// Whitelist sal (tylko aktywne mogą feedować)
$AKTYWNE_SALE = ['lobby','sala-glowna','sauna','bdsm','tyly','sala-balowa','vip','taras','basen','silownia','masaze','garderoba'];
if (!in_array($sala, $AKTYWNE_SALE, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Niewłaściwa sala']);
    exit;
}

// Aktualizuj obecność (kosztowna operacja, ale tylko UPDATE 1 wiersz)
$sala_sql = $polaczenie->real_escape_string($sala);
$polaczenie->query("UPDATE gracze SET klub_sala='$sala_sql', ostatnia_aktywnosc=NOW() WHERE id=$id_gracza");

// ── 0. CLEANUP wygasłych wyproszeń + check czy gracz jest wyproszony ─
$polaczenie->query("UPDATE klub_wypraszania SET aktywne=0 WHERE aktywne=1 AND do_kiedy <= NOW()");

$wyproszony = null;
$wq = $polaczenie->query("
    SELECT w.do_kiedy, w.powod, g.login AS barman_login,
           TIMESTAMPDIFF(SECOND, NOW(), w.do_kiedy) AS sekund_zostalo
    FROM klub_wypraszania w
    LEFT JOIN gracze g ON g.id = w.barman_id
    WHERE w.gracz_id=$id_gracza AND w.sala='$sala_sql' AND w.aktywne=1
    ORDER BY w.id DESC LIMIT 1
");
if ($wq) {
    $w = $wq->fetch_assoc();
    if ($w) {
        $wyproszony = [
            'do_kiedy'      => $w['do_kiedy'],
            'do_kiedy_fmt'  => date('H:i', strtotime($w['do_kiedy'])),
            'powod'         => $w['powod'] ?: '',
            'barman_login'  => $w['barman_login'] ?: 'barman',
            'sekund_zostalo'=> max(0, (int)$w['sekund_zostalo']),
        ];
        // Wyrzuć z sali natychmiast — gracz ma być w lobby
        $polaczenie->query("UPDATE gracze SET klub_sala='lobby' WHERE id=$id_gracza");
    }
}

// ── 1. NOWE WIADOMOŚCI ──────────────────────────────────────────
$wiadomosci = [];
$last_id = $od_id;

if ($sala !== 'lobby') {
    $q = $polaczenie->query("
        SELECT c.id, c.id_gracza, c.login, c.tresc, c.typ, c.summoner_id,
               c.edytowane_o, c.edycji_licznik, c.usunieta,
               DATE_FORMAT(c.data_wyslania,'%H:%i') AS czas,
               UNIX_TIMESTAMP(c.data_wyslania) AS ts,
               g.login AS summoner_login
        FROM czat c
        LEFT JOIN gracze g ON g.id = c.summoner_id
        WHERE c.sala='$sala_sql' AND c.id > $od_id
        ORDER BY c.id ASC
        LIMIT 100
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $wiadomosci[] = [
                'id'    => (int)$r['id'],
                'id_gracza' => (int)$r['id_gracza'],
                'login' => $r['login'],
                'tresc' => $r['tresc'],
                'typ'   => $r['typ'],
                'czas'  => $r['czas'],
                'ts'    => (int)$r['ts'],
                'edytowane'   => !empty($r['edytowane_o']),
                'edytowane_o' => $r['edytowane_o'] ? date('H:i', strtotime($r['edytowane_o'])) : null,
                'edycji_licznik' => (int)$r['edycji_licznik'],
                'usunieta'       => (bool)$r['usunieta'],
                'summoner_id'    => $r['summoner_id'] ? (int)$r['summoner_id'] : null,
                'summoner_login' => $r['summoner_login'],
                'is_mine'        => ((int)$r['id_gracza'] === $id_gracza),
            ];
            $last_id = (int)$r['id'];
        }
    }
}

// ── 2. AKTUALNY RACHUNEK GRACZA (tylko sala-glowna) ─────────────
$rachunek = ['pozycje' => [], 'razem' => 0, 'liczba' => 0];
if ($sala === 'sala-glowna') {
    $r = $polaczenie->query("
        SELECT id, nazwa_drink, cena, DATE_FORMAT(czas,'%H:%i') AS czas
        FROM klub_rachunki
        WHERE gracz_id=$id_gracza AND oplacony=0
        ORDER BY id DESC
    ");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rachunek['pozycje'][] = [
                'id'    => (int)$row['id'],
                'nazwa' => $row['nazwa_drink'],
                'cena'  => (int)$row['cena'],
                'czas'  => $row['czas'],
            ];
            $rachunek['razem'] += (int)$row['cena'];
            $rachunek['liczba']++;
        }
    }
}

// ── 3. OBECNI W SALI (do prawej kolumny) ────────────────────────
$obecni = [];
$o = $polaczenie->query("
    SELECT id, login, avatar, is_barman, is_mg, klub_mood
    FROM gracze
    WHERE klub_sala='$sala_sql'
      AND ostatnia_aktywnosc >= NOW() - INTERVAL 5 MINUTE
    ORDER BY is_barman DESC, is_mg DESC, login ASC
");
if ($o) {
    while ($row = $o->fetch_assoc()) {
        $obecni[] = [
            'id'        => (int)$row['id'],
            'login'     => $row['login'],
            'avatar'    => $row['avatar'],
            'is_barman' => (bool)$row['is_barman'],
            'is_mg'     => (bool)$row['is_mg'],
            'klub_mood' => $row['klub_mood'] ?? '',
            'is_me'     => ((int)$row['id'] === $id_gracza),
        ];
    }
}

// ── 4. NIEPRZECZYTANE SZEPTY DLA TEGO GRACZA ────────────────────
$szepty = [];
$sq = $polaczenie->query("
    SELECT s.id, s.tresc, s.czas, g.login AS od_login, g.id AS od_id
    FROM klub_szepty s
    LEFT JOIN gracze g ON g.id = s.od_gracza_id
    WHERE s.do_gracza_id=$id_gracza AND s.przeczytane=0
    ORDER BY s.id ASC LIMIT 10
");
if ($sq) {
    while ($r = $sq->fetch_assoc()) {
        $szepty[] = [
            'id'         => (int)$r['id'],
            'tresc'      => $r['tresc'],
            'od_login'   => $r['od_login'] ?: 'MG',
            'od_id'      => (int)$r['od_id'],
            'czas'       => date('H:i', strtotime($r['czas'])),
        ];
    }
    // Oznacz jako przeczytane (wystarczy że feed je dostarczył)
    if (!empty($szepty)) {
        $ids = implode(',', array_column($szepty, 'id'));
        $polaczenie->query("UPDATE klub_szepty SET przeczytane=1 WHERE id IN ($ids)");
    }
}

// ── 5. NOWE FLIRTY DLA TEGO GRACZA ──────────────────────────────
$flirty = [];
$fq = $polaczenie->query("
    SELECT f.id, f.czas, f.sala, g.login AS od_login, g.id AS od_id
    FROM klub_flirty f
    LEFT JOIN gracze g ON g.id = f.od_gracza_id
    WHERE f.do_gracza_id=$id_gracza AND f.widziany=0
    ORDER BY f.id DESC LIMIT 10
");
if ($fq) {
    while ($r = $fq->fetch_assoc()) {
        $flirty[] = [
            'id'         => (int)$r['id'],
            'od_login'   => $r['od_login'] ?: '???',
            'od_id'      => (int)$r['od_id'],
            'sala'       => $r['sala'],
            'czas'       => date('H:i', strtotime($r['czas'])),
        ];
    }
    if (!empty($flirty)) {
        $ids = implode(',', array_column($flirty, 'id'));
        $polaczenie->query("UPDATE klub_flirty SET widziany=1 WHERE id IN ($ids)");
    }
}

// ── 6. LAZY CHECK POWIADOMIEŃ EVENTÓW ───────────────────────────
// Eventy gracza w ciągu najbliższych 30 min, jeszcze nie powiadomione
$pq = $polaczenie->query("
    SELECT w.id, w.nazwa, w.data_startu, w.sala,
           TIMESTAMPDIFF(MINUTE, NOW(), w.data_startu) AS minuty_do
    FROM klub_wydarzenia w
    INNER JOIN klub_rezerwacje r ON r.wydarzenie_id = w.id
    WHERE r.gracz_id=$id_gracza
      AND w.aktywne=1 AND w.anulowane=0
      AND w.data_startu BETWEEN NOW() AND NOW() + INTERVAL 30 MINUTE
");
if ($pq) {
    while ($e = $pq->fetch_assoc()) {
        $eid = (int)$e['id'];
        // Czy już wysłano powiadomienie '30min'?
        $juz = $polaczenie->query("SELECT id FROM klub_powiadomienia_eventow WHERE wydarzenie_id=$eid AND gracz_id=$id_gracza AND typ='30min'")->fetch_assoc();
        if (!$juz) {
            $polaczenie->query("INSERT INTO klub_powiadomienia_eventow (wydarzenie_id, gracz_id, typ) VALUES ($eid, $id_gracza, '30min')");
            $tresc = "⏰ Twoje wydarzenie <b>" . htmlspecialchars($e['nazwa']) . "</b> zaczyna się za <b>" . max(0, (int)$e['minuty_do']) . " min</b> w sali <b>" . htmlspecialchars($e['sala']) . "</b>.";
            $tresc_sql = $polaczenie->real_escape_string($tresc);
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($id_gracza, '$tresc_sql')");
        }
    }
}
// Eventy które właśnie się rozpoczęły
$sq2 = $polaczenie->query("
    SELECT w.id, w.nazwa, w.sala
    FROM klub_wydarzenia w
    INNER JOIN klub_rezerwacje r ON r.wydarzenie_id = w.id
    WHERE r.gracz_id=$id_gracza
      AND w.aktywne=1 AND w.anulowane=0
      AND w.data_startu BETWEEN NOW() - INTERVAL 5 MINUTE AND NOW()
");
if ($sq2) {
    while ($e = $sq2->fetch_assoc()) {
        $eid = (int)$e['id'];
        $juz = $polaczenie->query("SELECT id FROM klub_powiadomienia_eventow WHERE wydarzenie_id=$eid AND gracz_id=$id_gracza AND typ='start'")->fetch_assoc();
        if (!$juz) {
            $polaczenie->query("INSERT INTO klub_powiadomienia_eventow (wydarzenie_id, gracz_id, typ) VALUES ($eid, $id_gracza, 'start')");
            $tresc = "🎉 Wydarzenie <b>" . htmlspecialchars($e['nazwa']) . "</b> zaczyna się TERAZ w sali <b>" . htmlspecialchars($e['sala']) . "</b>!";
            $tresc_sql = $polaczenie->real_escape_string($tresc);
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($id_gracza, '$tresc_sql')");
        }
    }
}

// ── 7. FAZA 4: AKTUALNY DJ (sala-glowna) ───────────────────────
$dj = ['gra' => null, 'kolejka' => []];
if ($sala === 'sala-glowna') {
    // Aktualny utwór z klub_dj_aktualny (1 wiersz id=1)
    $cur = $polaczenie->query("
        SELECT a.kolejka_id, a.tytul_recznie, a.artysta_recznie, a.dj_login, a.czas_startu,
               k.tytul AS tytul_k, k.artysta AS artysta_k, k.notka,
               gz.login AS zamawiajacy
        FROM klub_dj_aktualny a
        LEFT JOIN klub_dj_kolejka k ON k.id = a.kolejka_id
        LEFT JOIN gracze gz ON gz.id = k.gracz_id
        WHERE a.id=1
    ");
    if ($cur && ($r = $cur->fetch_assoc())) {
        $tytul = $r['tytul_k'] ?: $r['tytul_recznie'];
        $artysta = $r['artysta_k'] ?: $r['artysta_recznie'];
        if ($tytul) {
            $dj['gra'] = [
                'tytul' => $tytul,
                'artysta' => $artysta,
                'notka' => $r['notka'],
                'zamawiajacy' => $r['zamawiajacy'],
                'dj_login' => $r['dj_login'],
                'gra_od' => $r['czas_startu'] ? date('H:i', strtotime($r['czas_startu'])) : null,
            ];
        }
    }
    // Kolejka (max 3 najbliższe)
    $kol = $polaczenie->query("
        SELECT k.id, k.tytul, k.artysta, k.notka, g.login AS od_login
        FROM klub_dj_kolejka k
        LEFT JOIN gracze g ON g.id = k.gracz_id
        WHERE k.status='w_kolejce'
        ORDER BY k.id ASC LIMIT 3
    ");
    if ($kol) {
        while ($r = $kol->fetch_assoc()) {
            $dj['kolejka'][] = [
                'id' => (int)$r['id'],
                'tytul' => $r['tytul'],
                'artysta' => $r['artysta'],
                'notka' => $r['notka'],
                'od_login' => $r['od_login'],
            ];
        }
    }
}

echo json_encode([
    'ok'         => true,
    'sala'       => $sala,
    'last_id'    => $last_id,
    'wiadomosci' => $wiadomosci,
    'rachunek'   => $rachunek,
    'obecni'     => $obecni,
    'szepty'     => $szepty,
    'flirty'     => $flirty,
    'wyproszony' => $wyproszony,
    'dj'         => $dj,
    'time'       => date('H:i:s'),
]);