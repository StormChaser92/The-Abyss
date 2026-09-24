<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/BEZPIECZNE.PHP
   Zapytania z parametrami i atomowe ruchy zasobów.

   Zasada dla pieniędzy i przedmiotów: nigdy „sprawdź w PHP, potem odejmij".
   Warunek idzie do WHERE (`gotowka >= ?`), a o sukcesie decyduje
   affected_rows. Dwa kliknięcia naraz nie przejdą oba.
   ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('db_q')) {

/** Wykonuje zapytanie z parametrami. Typy zgadywane z wartości, chyba że podasz $typy. */
function db_q(mysqli $db, string $sql, array $par = [], string $typy = ''): mysqli_stmt {
    $st = $db->prepare($sql);
    if (!$st) throw new RuntimeException('SQL: ' . $db->error);
    if ($par) {
        if ($typy === '') foreach ($par as $p) $typy .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        $st->bind_param($typy, ...$par);
    }
    $st->execute();
    return $st;
}

/** Jeden wiersz albo null. */
function db_wiersz(mysqli $db, string $sql, array $par = [], string $typy = ''): ?array {
    $r = db_q($db, $sql, $par, $typy)->get_result();
    $w = $r ? $r->fetch_assoc() : null;
    return $w ?: null;
}

/** Wszystkie wiersze. */
function db_wiersze(mysqli $db, string $sql, array $par = [], string $typy = ''): array {
    $r = db_q($db, $sql, $par, $typy)->get_result();
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

/** UPDATE/DELETE/INSERT — zwraca liczbę zmienionych wierszy. */
function db_zmien(mysqli $db, string $sql, array $par = [], string $typy = ''): int {
    return db_q($db, $sql, $par, $typy)->affected_rows;
}

/** Transakcja: commit po sukcesie, rollback po wyjątku. */
function db_tx(mysqli $db, callable $fn) {
    $db->begin_transaction();
    try { $w = $fn(); $db->commit(); return $w; }
    catch (Throwable $e) { $db->rollback(); throw $e; }
}

/* ── zasoby gracza ─────────────────────────────────────────────────── */

/** Kolumny gracza, które wolno przesuwać tymi funkcjami. Nazwa kolumny nigdy nie przychodzi z formularza. */
function zasob_dozwolony(string $kol): bool {
    static $ok = ['gotowka','bank','zetony','apteczki','materialy','zlom_stalowy','czesci_mechaniczne',
                  'syntetyki','elektronika','energia_aktualna'];
    return in_array($kol, $ok, true);
}

/** Odejmuje tylko wtedy, gdy gracz ma tyle. true = pobrano. */
function zasob_pobierz(mysqli $db, int $gid, int $ile, string $kol = 'gotowka'): bool {
    if ($ile <= 0) return $ile === 0;
    if (!zasob_dozwolony($kol)) throw new InvalidArgumentException("Kolumna $kol");
    return db_zmien($db, "UPDATE gracze SET `$kol` = `$kol` - ? WHERE id = ? AND `$kol` >= ?", [$ile, $gid, $ile]) === 1;
}

function zasob_dodaj(mysqli $db, int $gid, int $ile, string $kol = 'gotowka'): void {
    if ($ile <= 0) return;
    if (!zasob_dozwolony($kol)) throw new InvalidArgumentException("Kolumna $kol");
    db_q($db, "UPDATE gracze SET `$kol` = `$kol` + ? WHERE id = ?", [$ile, $gid]);
}

function kasa_pobierz(mysqli $db, int $gid, int $kwota): bool { return zasob_pobierz($db, $gid, $kwota, 'gotowka'); }
function kasa_dodaj(mysqli $db, int $gid, int $kwota): void { zasob_dodaj($db, $gid, $kwota, 'gotowka'); }

/** Powiadomienie bez sklejania SQL — login z apostrofem już nic nie zepsuje. */
function powiadom(mysqli $db, int $gid, string $tresc): void {
    db_q($db, "INSERT INTO powiadomienia (gracz_id, tresc) VALUES (?, ?)", [$gid, $tresc]);
}

/** Login w HTML powiadomień. */
function bz_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

}
