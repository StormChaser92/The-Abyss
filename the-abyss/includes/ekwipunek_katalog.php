<?php
/* the-abyss/includes/ekwipunek_katalog.php
   Katalog broni i pancerzy wyniesiony z pages/ekwipunek.php.

   Po co: `gracze.bonus_szybkosc` i `gracze.bonus_unik` istnieją od migracji bossów,
   ale nic ich nie wypełniało, więc zdolność uniku liczyła się z trzech składników
   zamiast pięciu (arena_zdolnosc_uniku() w includes/arena_walka.php).
   Teraz każda broń ma `szybkosc`, każdy pancerz ma `unik`, a zakładanie sprzętu
   zapisuje wszystkie cztery bonusy naraz.

   Drugi powód: pole `typ` broni ('Kinetyczna'/'Toksyczna'/'Przeciwpancerna'/'EMP')
   i `typ` pancerza ('Biologiczny'/'Opancerzony'/'Cybernetyczny') odchodzą razem
   z amunicją. Broń ma teraz `cios` — kategorię z arena_ciosy(). Typ przeciwnika
   siedzi po stronie NPC, nie po stronie gracza.

   Wkład w zdolność uniku:  szybkosc · 0,45 + unik      (patrz arena_zdolnosc_uniku)
   Ciężki pancerz ma `unik` UJEMNY — chroni, ale spowalnia. */

/** Broń: katalog przeniesiony do includes/bronie_katalog.php (60 pozycji).
    Ta funkcja zostaje jako alias, żeby stary kod nie musiał się zmieniać naraz. */
require_once __DIR__.'/bronie_katalog.php';
require_once __DIR__.'/warsztat_logika.php';   // eq_ma(), eq_stopien(), bron_atak()

function eq_katalog_broni(): array { return bronie_katalog(); }

/** Pancerz: obrona (bonus_obrona), unik (bonus_unik — ciężki na minusie). */
function eq_katalog_pancerzy(): array {
    return [
        'eq_bluza'               => ['nazwa'=>'Gruba Bluza z Kapturem',  'obrona'=>1,  'unik'=>6,   'ikona'=>'🧥'],
        'eq_kurtka'              => ['nazwa'=>'Skórzana Kurtka',         'obrona'=>3,  'unik'=>4,   'ikona'=>'🏍️'],
        'eq_opony'               => ['nazwa'=>'Pancerz z Opon',          'obrona'=>5,  'unik'=>-2,  'ikona'=>'🛞'],
        'eq_kamizelka'           => ['nazwa'=>'Kamizelka Kuloodporna',   'obrona'=>8,  'unik'=>-4,  'ikona'=>'🦺'],
        'pancerz_taktyczny'      => ['nazwa'=>'Pancerz Taktyczny SWAT',  'obrona'=>15, 'unik'=>-8,  'ikona'=>'🛡️'],
        'pancerz_taktyczny_upg'  => ['nazwa'=>'Ciężki Pancerz Płytowy',  'obrona'=>24, 'unik'=>-14, 'ikona'=>'⛓️'],
    ];
}

/** Nazwa kategorii ciosu do wyświetlenia. */
function eq_nazwa_ciosu(string $cios): string {
    return ['ostrze'=>'Ostrze', 'tepe'=>'Tępe narzędzie', 'palna'=>'Broń palna', 'piesc'=>'Goła pięść'][$cios] ?? 'Goła pięść';
}

/** Opis, kogo dany cios kontruje — do kafelka w ekwipunku. */
function eq_kontra_ciosu(string $cios): string {
    return [
        'ostrze' => '×1,5 przeciw Cywilom',
        'tepe'   => '×1,5 przeciw Bykowatym',
        'palna'  => '×1,5 przeciw Ochroniarzom',
        'piesc'  => 'bez bonusu, ale i bez kary',
    ][$cios] ?? '';
}

/**
 * Zakłada broń. Zapisuje bonus_atak I bonus_szybkosc, zeruje ulepszenie
 * i ustawia arena_cios na kategorię tej broni.
 * Zwraca [ok, komunikat].
 */
function eq_zaloz_bron(mysqli $db, int $gid, string $kod): array {
    $katalog = eq_katalog_broni();
    if (!isset($katalog[$kod])) return [false, 'Nie znam takiej broni.'];

    // Posiadanie czyta z tabeli `ekwipunek_gracza` (migracja_warsztat.sql).
    if (eq_ma($db, $gid, $kod) < 1) return [false, 'Nie masz tego przy sobie.'];

    $b = $katalog[$kod];
    $n = $db->real_escape_string($b['nazwa']);

    // Stopień ulepszenia tej sztuki (+0…+10) dolicza się do ataku bazowego.
    $st   = eq_stopien($db, $gid, $kod);
    $atak = bron_atak($b, $st);

    $db->query("UPDATE gracze SET
        bron_zalozona  = '$n',
        bonus_atak     = $atak,
        bron_stopien   = $st,
        bonus_szybkosc = {$b['szybkosc']},
        arena_cios     = '{$b['cios']}',
        bron_ulepszona = 0,
        bron_trwalosc  = bron_trwalosc_max
        WHERE id = $gid");

    $plus = $st > 0 ? " +$st" : '';
    return [true, "Bierzesz w dłonie: <b>{$b['nazwa']}</b>$plus (+$atak atak, +{$b['szybkosc']} szybkość, "
                 . eq_nazwa_ciosu($b['cios']) . ')'];
}

/**
 * Zakłada pancerz. Zapisuje bonus_obrona I bonus_unik.
 * Zwraca [ok, komunikat].
 */
function eq_zaloz_pancerz(mysqli $db, int $gid, string $kod): array {
    $katalog = eq_katalog_pancerzy();
    if (!isset($katalog[$kod])) return [false, 'Nie znam takiego pancerza.'];

    if (eq_ma($db, $gid, $kod) < 1) return [false, 'Nie masz tego przy sobie.'];

    $p = $katalog[$kod];
    $n = $db->real_escape_string($p['nazwa']);
    $db->query("UPDATE gracze SET
        pancerz_zalozony  = '$n',
        bonus_obrona      = {$p['obrona']},
        bonus_unik        = {$p['unik']},
        pancerz_ulepszony = 0,
        pancerz_trwalosc  = pancerz_trwalosc_max
        WHERE id = $gid");
    $znak = $p['unik'] >= 0 ? '+' : '';
    return [true, "Wciągasz na siebie: <b>{$p['nazwa']}</b> (+{$p['obrona']} obrona, {$znak}{$p['unik']} unik)"];
}

/** Rozkład zdolności uniku na składniki — do panelu w ekwipunku i w dokach. */
function eq_rozklad_uniku(array $g): array {
    $s = [
        'Umiejętność uniki'   => round((float)$g['uniki'], 2),
        'Zręczność × 0,45'    => round((int)$g['zrecznosc'] * 0.45, 2),
        'Szybkość broni × 0,45'=> round((int)($g['bonus_szybkosc'] ?? 0) * 0.45, 2),
        'Pancerz (unik)'      => (int)($g['bonus_unik'] ?? 0),
        'Poziom'              => (int)$g['poziom'],
    ];
    $s['RAZEM'] = round(array_sum($s), 2);
    return $s;
}
