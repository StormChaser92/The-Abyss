<?php
/* the-abyss/config/moderacja.php
   Moderacja Centrum Opowieści i Klubu.
   - Moderują MG i Adminka (rp_nadzor). MG/Adminkę może ukarać tylko Adminka. Nikt nie karze siebie.
   - Kary: ostrzeżenie, zawieszenie (3 / 7 / 30 dni), wyrzucenie z Klubu (bezterminowo, do cofnięcia).
   - Powód jest zawsze wymagany. Ostrzeżenia nie wygasają — znika tylko cofnięte.
   - Eskalacja: co 3. aktywne ostrzeżenie daje automatyczne zawieszenie (3. → 3 dni, 6. → 7 dni, 9. i dalej → 30 dni).
   - Zawieszenie: brak dostępu do Wydarzeń w Klubie, brak pisania postów (fabuła, NC, czat Klubu), powiadomienie.
   - Wyrzucenie z Klubu: brak wejścia do Klubu i do Wydarzeń w Klubie.
   - Odwołanie: raz od każdej kary, rozpatruje Adminka; uznane cofa karę.
   - Każda akcja trafia do mod_log. Tabele: db/migracja_moderacja.sql. */
require_once __DIR__ . '/../includes/bezpieczne.php';
require_once __DIR__ . '/rangi.php';

if (!function_exists('mod_moze')) {

const MOD_DNI = [3, 7, 30];
const MOD_NAZWY = ['ostrzezenie' => 'Ostrzeżenie', 'zawieszenie' => 'Zawieszenie', 'wyrzucenie' => 'Wyrzucenie z Klubu'];
const NC_CZAS = ['1h' => ['1 godzina', 3600], '24h' => ['24 godziny', 86400], 'sesja' => ['do końca sesji', 0]];

function mod_moze(mysqli $db, int $gid): bool { return rp_nadzor(rp_ranga($db, $gid)); }
function mod_admin(mysqli $db, int $gid): bool { return rp_ranga($db, $gid) === 'adminka'; }

function mod_moze_cel(mysqli $db, int $kto, int $cel): bool {
    if ($kto === $cel || $cel <= 0 || !mod_moze($db, $kto)) return false;
    return !mod_moze($db, $cel) || mod_admin($db, $kto);
}

function mod_log(mysqli $db, int $kto, int $cel, string $akcja, string $opis): void {
    db_zmien($db, "INSERT INTO mod_log (moderator_id, gracz_id, akcja, opis) VALUES (?, ?, ?, ?)", [$kto, $cel, $akcja, mb_substr($opis, 0, 1000)]);
}

function mod_zawieszenie_aktywne(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM mod_kary WHERE gracz_id = ? AND rodzaj = 'zawieszenie' AND cofnieta = 0 AND do_kiedy > NOW() ORDER BY do_kiedy DESC LIMIT 1", [$gid]);
}
function mod_wyrzucony(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM mod_kary WHERE gracz_id = ? AND rodzaj = 'wyrzucenie' AND cofnieta = 0 ORDER BY id DESC LIMIT 1", [$gid]);
}
function mod_ostrzezen(mysqli $db, int $gid): int {
    return (int)(db_wiersz($db, "SELECT COUNT(*) c FROM mod_kary WHERE gracz_id = ? AND rodzaj = 'ostrzezenie' AND cofnieta = 0", [$gid])['c'] ?? 0);
}
function mod_data(?string $d): string { return $d ? date('d.m.Y H:i', strtotime($d)) : ''; }

/** Komunikat dla api/klub_akcja.php; '' = może pisać. */
function mod_blokada_klubu(mysqli $db, int $gid): string {
    if (mod_wyrzucony($db, $gid)) return 'Zostałeś wyrzucony z Klubu The Abyss.';
    if ($z = mod_zawieszenie_aktywne($db, $gid)) return 'Zawieszenie do ' . mod_data($z['do_kiedy']) . ' — nie możesz pisać.';
    return '';
}

/** Nakłada karę. Zwraca '' albo komunikat błędu. */
function mod_kara(mysqli $db, int $kto, int $cel, string $rodzaj, string $powod, int $dni = 0, int $sid = 0, int $pid = 0, bool $auto = false): string {
    $powod = trim(mb_substr($powod, 0, 1000));
    if (!isset(MOD_NAZWY[$rodzaj])) return 'Nieznana kara.';
    if ($powod === '') return 'Podaj powód.';
    if (!$auto && !mod_moze_cel($db, $kto, $cel)) return 'Brak uprawnień wobec tego gracza.';
    if (!db_wiersz($db, "SELECT id FROM gracze WHERE id = ?", [$cel])) return 'Nie ma takiego gracza.';
    $do = null;
    if ($rodzaj === 'zawieszenie') {
        if (!in_array($dni, MOD_DNI, true)) return 'Zawieszenie: 3, 7 albo 30 dni.';
        $akt = mod_zawieszenie_aktywne($db, $cel);           // kolejne zawieszenie dolicza się do trwającego
        $od  = $akt ? max(time(), strtotime($akt['do_kiedy'])) : time();
        $do  = date('Y-m-d H:i:s', $od + $dni * 86400);
    } else $dni = 0;
    if ($rodzaj === 'wyrzucenie' && mod_wyrzucony($db, $cel)) return 'Gracz jest już wyrzucony z Klubu.';

    db_zmien($db, "INSERT INTO mod_kary (gracz_id, moderator_id, rodzaj, dni, do_kiedy, sesja_id, post_id, powod, auto) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?)",
             [$cel, $kto, $rodzaj, $dni, $do, $sid, $pid, $powod, $auto ? 1 : 0]);
    $opis = MOD_NAZWY[$rodzaj] . ($dni ? " ($dni dni, do " . mod_data($do) . ")" : '');
    mod_log($db, $kto, $cel, $auto ? 'auto_' . $rodzaj : $rodzaj, "$opis: $powod");
    $skutek = [
        'ostrzezenie' => 'Aktywne ostrzeżenia: ' . mod_ostrzezen($db, $cel) . '. Co trzecie oznacza automatyczne zawieszenie.',
        'zawieszenie' => 'Do końca zawieszenia nie wejdziesz na Wydarzenia w Klubie i nie napiszesz postów w sesjach, NC ani na czacie Klubu.',
        'wyrzucenie'  => 'Nie masz wstępu do Klubu The Abyss ani na Wydarzenia w Klubie.',
    ][$rodzaj];
    powiadom($db, $cel, "<b style='color:var(--neon-red-hot)'>" . bz_h($opis) . "</b>. Powód: " . bz_h($powod) . ". $skutek <a href='game.php?page=moderacja' style='color:var(--neon-cyan)'>[ Historia i odwołanie ]</a>");

    if ($rodzaj === 'ostrzezenie' && !$auto) {
        $n = mod_ostrzezen($db, $cel);
        if ($n > 0 && $n % 3 === 0) mod_kara($db, $kto, $cel, 'zawieszenie', "Automatycznie: $n. aktywne ostrzeżenie.", $n >= 9 ? 30 : ($n >= 6 ? 7 : 3), $sid, 0, true);
    }
    return '';
}

function mod_cofnij(mysqli $db, int $kto, int $kid, string $powod, bool $z_odwolania = false): string {
    $k = db_wiersz($db, "SELECT * FROM mod_kary WHERE id = ? AND cofnieta = 0", [$kid]);
    if (!$k) return 'Nie ma takiej aktywnej kary.';
    if (!$z_odwolania && !mod_moze_cel($db, $kto, (int)$k['gracz_id'])) return 'Brak uprawnień.';
    $powod = trim(mb_substr($powod, 0, 500));
    if ($powod === '') return 'Podaj powód cofnięcia.';
    db_zmien($db, "UPDATE mod_kary SET cofnieta = 1, cofnal_id = ?, cofniecie_powod = ? WHERE id = ? AND cofnieta = 0", [$kto, $powod, $kid]);
    mod_log($db, $kto, (int)$k['gracz_id'], 'cofniecie', MOD_NAZWY[$k['rodzaj']] . " #$kid: $powod");
    powiadom($db, (int)$k['gracz_id'], "Cofnięto karę: <b style='color:var(--neon-green)'>" . bz_h(MOD_NAZWY[$k['rodzaj']]) . "</b> z " . mod_data($k['kiedy']) . ". " . bz_h($powod));
    return '';
}

function mod_odwolaj(mysqli $db, int $gid, int $kid, string $tresc): string {
    if (!db_wiersz($db, "SELECT id FROM mod_kary WHERE id = ? AND gracz_id = ? AND cofnieta = 0", [$kid, $gid])) return 'Nie ma takiej aktywnej kary.';
    if (db_wiersz($db, "SELECT id FROM mod_odwolania WHERE kara_id = ?", [$kid])) return 'Od tej kary już się odwołałeś.';
    $tresc = trim(mb_substr($tresc, 0, 2000));
    if (mb_strlen($tresc) < 10) return 'Opisz odwołanie (min. 10 znaków).';
    db_zmien($db, "INSERT INTO mod_odwolania (kara_id, gracz_id, tresc) VALUES (?, ?, ?)", [$kid, $gid, $tresc]);
    mod_log($db, $gid, $gid, 'odwolanie', "Kara #$kid");
    foreach (db_wiersze($db, "SELECT id FROM gracze WHERE ranga_rp = 'adminka'") as $a)
        powiadom($db, (int)$a['id'], "Nowe odwołanie od kary. <a href='game.php?page=moderacja&tab=odwolania' style='color:var(--neon-cyan)'>[ Moderacja ]</a>");
    return '';
}

function mod_rozpatrz(mysqli $db, int $adm, int $oid, bool $uznaj, string $odp): string {
    if (!mod_admin($db, $adm)) return 'Odwołania rozpatruje Adminka Fabularna.';
    $o = db_wiersz($db, "SELECT o.*, k.rodzaj FROM mod_odwolania o JOIN mod_kary k ON k.id = o.kara_id WHERE o.id = ? AND o.status = 'nowe'", [$oid]);
    if (!$o) return 'Nie ma takiego odwołania.';
    if ((int)$o['gracz_id'] === $adm) return 'Nie rozpatrujesz własnego odwołania.';
    $odp = trim(mb_substr($odp, 0, 1000));
    if ($odp === '') return 'Napisz uzasadnienie decyzji.';
    db_zmien($db, "UPDATE mod_odwolania SET status = ?, admin_id = ?, odpowiedz = ?, data_decyzji = NOW() WHERE id = ?", [$uznaj ? 'uznane' : 'odrzucone', $adm, $odp, $oid]);
    if ($uznaj) mod_cofnij($db, $adm, (int)$o['kara_id'], "Uznane odwołanie: $odp", true);
    else powiadom($db, (int)$o['gracz_id'], "Odwołanie od kary (" . bz_h(MOD_NAZWY[$o['rodzaj']]) . ") <b style='color:var(--neon-red-hot)'>odrzucone</b>: " . bz_h($odp));
    mod_log($db, $adm, (int)$o['gracz_id'], $uznaj ? 'odwolanie_uznane' : 'odwolanie_odrzucone', "Kara #{$o['kara_id']}: $odp");
    return '';
}

/** Wyciszenie w NC danej sesji; null = może pisać. do_kiedy NULL = do końca sesji. */
function nc_wyciszenie(mysqli $db, int $sid, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM nc_wyciszenia WHERE sesja_id = ? AND gracz_id = ? AND (do_kiedy IS NULL OR do_kiedy > NOW())", [$sid, $gid]);
}

}
