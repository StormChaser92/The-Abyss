<?php
// ════════════════════════════════════════════════════════════════════
// CRON — POWIADOMIENIA O WYDARZENIACH (Faza 6)
// 
// Skrypt CLI uruchamiany co 5 minut przez Windows Task Scheduler
// (XAMPP Windows) lub cron (Linux). Wysyła powiadomienia
// 30 minut przed startem oraz w momencie startu — DO WSZYSTKICH
// ZAREZERWOWANYCH, niezależnie czy są w klubie czy nie.
// 
// Uruchomienie ręczne (test):
//   D:\xampp\php\php.exe D:\Abyss\htdocs\the-abyss\cron\klub_powiadomienia.php
// 
// Crontab (Linux, co 5 min):
//   */5 * * * * /usr/bin/php /var/www/the-abyss/cron/klub_powiadomienia.php
// 
// Windows Task Scheduler — zobacz PATCH_cron.txt
// ════════════════════════════════════════════════════════════════════

// Tylko z CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Skrypt tylko z CLI\n");
}

// Loaduj DB
$root = dirname(__DIR__);
require_once $root . '/db.php';

if (!$polaczenie || $polaczenie->connect_errno) {
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] ERROR: brak połączenia z bazą\n");
    exit(1);
}

$polaczenie->set_charset('utf8mb4');

$timestamp = date('Y-m-d H:i:s');
$wyslane_30min = 0;
$wyslane_start = 0;

// ────────────────────────────────────────────────────────────────
// 1. POWIADOMIENIA 30 MIN PRZED STARTEM
// ────────────────────────────────────────────────────────────────
// Dla każdego eventu który zaczyna się za 25-35 minut
// (okno 10 min, żeby cron co 5 min trafił dwa razy = bezpieczeństwo
// na wypadek opóźnień)

$q = $polaczenie->query("
    SELECT w.id, w.nazwa, w.sala, w.data_startu,
           DATE_FORMAT(w.data_startu, '%H:%i') AS godzina,
           DATE_FORMAT(w.data_startu, '%d.%m') AS data
    FROM klub_wydarzenia w
    WHERE w.aktywne=1 AND w.anulowane=0
      AND w.data_startu BETWEEN NOW() + INTERVAL 25 MINUTE
                            AND NOW() + INTERVAL 35 MINUTE
");

if ($q) {
    while ($e = $q->fetch_assoc()) {
        $eid = (int)$e['id'];
        // Wszystkie rezerwacje
        $rq = $polaczenie->query("SELECT gracz_id FROM klub_rezerwacje WHERE wydarzenie_id=$eid");
        if (!$rq) continue;

        while ($r = $rq->fetch_assoc()) {
            $gid = (int)$r['gracz_id'];

            // Czy już powiadomiony?
            $ist = $polaczenie->query("SELECT id FROM klub_powiadomienia_eventow WHERE wydarzenie_id=$eid AND gracz_id=$gid AND typ='30min'")->fetch_assoc();
            if ($ist) continue;

            // Wstaw powiadomienie
            $tresc = "🔔 Za <b>30 minut</b> startuje wydarzenie <b>" .
                     htmlspecialchars($e['nazwa']) . "</b> ({$e['data']} o {$e['godzina']}, sala: " .
                     htmlspecialchars($e['sala']) . "). Nie zapomnij!";
            $tresc_sql = $polaczenie->real_escape_string($tresc);
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($gid, '$tresc_sql')");
            $polaczenie->query("INSERT INTO klub_powiadomienia_eventow (wydarzenie_id, gracz_id, typ) VALUES ($eid, $gid, '30min')");
            $wyslane_30min++;
        }
    }
}

// ────────────────────────────────────────────────────────────────
// 2. POWIADOMIENIA NA START
// ────────────────────────────────────────────────────────────────
// Eventy które zaczynają się TERAZ (od -2 min do +2 min)

$q = $polaczenie->query("
    SELECT w.id, w.nazwa, w.sala
    FROM klub_wydarzenia w
    WHERE w.aktywne=1 AND w.anulowane=0
      AND w.data_startu BETWEEN NOW() - INTERVAL 2 MINUTE
                            AND NOW() + INTERVAL 2 MINUTE
");

if ($q) {
    while ($e = $q->fetch_assoc()) {
        $eid = (int)$e['id'];
        $rq = $polaczenie->query("SELECT gracz_id FROM klub_rezerwacje WHERE wydarzenie_id=$eid");
        if (!$rq) continue;

        while ($r = $rq->fetch_assoc()) {
            $gid = (int)$r['gracz_id'];

            $ist = $polaczenie->query("SELECT id FROM klub_powiadomienia_eventow WHERE wydarzenie_id=$eid AND gracz_id=$gid AND typ='start'")->fetch_assoc();
            if ($ist) continue;

            $tresc = "🎬 <b>STARTUJE TERAZ:</b> " . htmlspecialchars($e['nazwa']) .
                     " — sala: <b>" . htmlspecialchars($e['sala']) . "</b>. Wchodź!";
            $tresc_sql = $polaczenie->real_escape_string($tresc);
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($gid, '$tresc_sql')");
            $polaczenie->query("INSERT INTO klub_powiadomienia_eventow (wydarzenie_id, gracz_id, typ) VALUES ($eid, $gid, 'start')");
            $wyslane_start++;
        }
    }
}

// ────────────────────────────────────────────────────────────────
// 3. CLEANUP — wygasłe rzeczy
// ────────────────────────────────────────────────────────────────
$polaczenie->query("UPDATE klub_vip_zaplaty SET aktywny=0 WHERE aktywny=1 AND waznosc_do <= NOW()");
$polaczenie->query("UPDATE klub_wypraszania SET aktywne=0 WHERE aktywne=1 AND do_kiedy <= NOW()");
$polaczenie->query("UPDATE klub_basen_tory SET zajety_przez_id=NULL, zajety_od=NULL WHERE zajety_od IS NOT NULL AND zajety_od <= NOW() - INTERVAL 30 MINUTE");
$polaczenie->query("UPDATE klub_silownia_sprzet SET zajety_przez_id=NULL, zajety_od=NULL WHERE zajety_od IS NOT NULL AND zajety_od <= NOW() - INTERVAL 20 MINUTE");
$polaczenie->query("UPDATE klub_masaze_lozka SET klient_id=NULL, zabieg_id=NULL, do_kiedy=NULL WHERE do_kiedy IS NOT NULL AND do_kiedy <= NOW()");

// ────────────────────────────────────────────────────────────────
// LOG
// ────────────────────────────────────────────────────────────────
echo "[$timestamp] CRON klub_powiadomienia: wysłane 30min={$wyslane_30min}, start={$wyslane_start}\n";

$polaczenie->close();
exit(0);