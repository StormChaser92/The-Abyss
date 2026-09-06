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

/** Broń: atak (bonus_atak), szybkosc (bonus_szybkosc), cios (kategoria vs typ wroga). */
function eq_katalog_broni(): array {
    return [
        // Lombard i podstawy
        'eq_widelec'         => ['nazwa'=>'Zardzewiały Widelec',      'atak'=>1,   'szybkosc'=>26, 'cios'=>'ostrze', 'ikona'=>'🍴'],
        'eq_noz'             => ['nazwa'=>'Tępy Nóż Kuchenny',        'atak'=>2,   'szybkosc'=>28, 'cios'=>'ostrze', 'ikona'=>'🔪'],
        'eq_kij'             => ['nazwa'=>'Kij Baseballowy',          'atak'=>4,   'szybkosc'=>18, 'cios'=>'tepe',   'ikona'=>'🏏'],
        'eq_lancuch'         => ['nazwa'=>'Łańcuch Rowerowy',         'atak'=>6,   'szybkosc'=>14, 'cios'=>'tepe',   'ikona'=>'⛓️'],
        'eq_kastet'          => ['nazwa'=>'Stary Kastet',             'atak'=>8,   'szybkosc'=>24, 'cios'=>'piesc',  'ikona'=>'👊'],
        // Broń biała
        'noz_kabar'          => ['nazwa'=>'Nóż bojowy KA-BAR',        'atak'=>10,  'szybkosc'=>30, 'cios'=>'ostrze', 'ikona'=>'🔪'],
        'maczeta_kukri'      => ['nazwa'=>'Maczeta Kukri',            'atak'=>14,  'szybkosc'=>22, 'cios'=>'ostrze', 'ikona'=>'🔪'],
        // Broń palna
        'glock_17'           => ['nazwa'=>'Glock 17 (9 mm)',          'atak'=>16,  'szybkosc'=>24, 'cios'=>'palna',  'ikona'=>'🔫'],
        'pm_uzi'             => ['nazwa'=>'Uzi',                      'atak'=>22,  'szybkosc'=>20, 'cios'=>'palna',  'ikona'=>'💨'],
        'desert_eagle'       => ['nazwa'=>'Desert Eagle .50',         'atak'=>26,  'szybkosc'=>14, 'cios'=>'palna',  'ikona'=>'🔫'],
        'pm_p90'             => ['nazwa'=>'FN P90',                   'atak'=>32,  'szybkosc'=>18, 'cios'=>'palna',  'ikona'=>'💨'],
        'karabin_ak47'       => ['nazwa'=>'AK-47',                    'atak'=>35,  'szybkosc'=>12, 'cios'=>'palna',  'ikona'=>'🎯'],
        'karabin_m4a1'       => ['nazwa'=>'M4A1',                     'atak'=>36,  'szybkosc'=>13, 'cios'=>'palna',  'ikona'=>'🎯'],
        'strzelba_mossberg'  => ['nazwa'=>'Mossberg 500',             'atak'=>40,  'szybkosc'=>9,  'cios'=>'palna',  'ikona'=>'💥'],
        'snajperka_awp'      => ['nazwa'=>'Karabin wyborowy AWP',     'atak'=>60,  'szybkosc'=>5,  'cios'=>'palna',  'ikona'=>'🔭'],
        'lmg_m249'           => ['nazwa'=>'M249 SAW',                 'atak'=>70,  'szybkosc'=>3,  'cios'=>'palna',  'ikona'=>'🔥'],
        'wyrzutnia_rpg7'     => ['nazwa'=>'Wyrzutnia RPG-7',          'atak'=>100, 'szybkosc'=>1,  'cios'=>'palna',  'ikona'=>'🚀'],
    ];
}

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

    // Posiadanie: przedmioty żyją jako kolumny w `gracze` (eq_*, glock_17, ...).
    $kol = preg_replace('/[^a-z0-9_]/', '', $kod);
    $r = $db->query("SELECT `$kol` AS ile FROM gracze WHERE id=$gid");
    if (!$r || !$r->num_rows || (int)$r->fetch_assoc()['ile'] <= 0)
        return [false, 'Nie masz tego przy sobie.'];

    $b = $katalog[$kod];
    $n = $db->real_escape_string($b['nazwa']);
    $db->query("UPDATE gracze SET
        bron_zalozona  = '$n',
        bonus_atak     = {$b['atak']},
        bonus_szybkosc = {$b['szybkosc']},
        arena_cios     = '{$b['cios']}',
        bron_ulepszona = 0,
        bron_trwalosc  = bron_trwalosc_max
        WHERE id = $gid");
    return [true, "Bierzesz w dłonie: <b>{$b['nazwa']}</b> (+{$b['atak']} atak, +{$b['szybkosc']} szybkość, "
                 . eq_nazwa_ciosu($b['cios']) . ')'];
}

/**
 * Zakłada pancerz. Zapisuje bonus_obrona I bonus_unik.
 * Zwraca [ok, komunikat].
 */
function eq_zaloz_pancerz(mysqli $db, int $gid, string $kod): array {
    $katalog = eq_katalog_pancerzy();
    if (!isset($katalog[$kod])) return [false, 'Nie znam takiego pancerza.'];

    $kol = preg_replace('/[^a-z0-9_]/', '', $kod);
    $r = $db->query("SELECT `$kol` AS ile FROM gracze WHERE id=$gid");
    if (!$r || !$r->num_rows || (int)$r->fetch_assoc()['ile'] <= 0)
        return [false, 'Nie masz tego przy sobie.'];

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
