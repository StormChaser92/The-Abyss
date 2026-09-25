<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — CONFIG/RANGI.PHP
   Rangi prowadzących, poziomy trudności i rodzaje Opowieści.
   Ranga siedzi w gracze.ranga_rp (nadaje ją MG lub Adminka: pages/rangi.php).
   Konta z gracze.is_mg = 1 albo z listy $MISTRZOWIE_GRY (config/mg.php) są zawsze MG.
   Uwaga: nie dołączamy config/mg.php — helpers/firmy.php ma własne czy_mg()
   i dwie deklaracje kończą się błędem „Cannot redeclare czy_mg()”.
   ═══════════════════════════════════════════════════════════════════════ */

/** Czy konto jest MG z urzędu (flaga is_mg lub login z listy MG). */
function rp_mg_konto(array $g): bool {
    global $MISTRZOWIE_GRY;
    if (!empty($g['is_mg'])) return true;
    foreach (($MISTRZOWIE_GRY ?? ['StormChaser92']) as $l) if (strcasecmp((string)$l, (string)($g['login'] ?? '')) === 0) return true;
    return false;
}

const RP_RANGI = [
    'gracz'     => ['n' => 'Gracz',             'typy' => ['sesja', 'swobodna'],         'poziomy' => ['swobodna', 'niski', 'umiarkowany']],
    'jmg'       => ['n' => 'Junior MG',         'typy' => ['sesja', 'swobodna'],         'poziomy' => ['swobodna', 'niski', 'umiarkowany', 'wysoki'], 'akceptacja' => true],
    'gospodarz' => ['n' => 'Gospodarz Klubu',   'typy' => ['klub', 'swobodna'],          'poziomy' => ['swobodna', 'niski', 'umiarkowany', 'wysoki']],
    'mg'        => ['n' => 'Mistrz Gry',        'typy' => ['sesja', 'klub', 'swobodna'], 'poziomy' => ['swobodna', 'niski', 'umiarkowany', 'wysoki', 'ekstremalny']],
    'adminka'   => ['n' => 'Adminka Fabularna', 'typy' => ['sesja', 'klub', 'swobodna'], 'poziomy' => ['swobodna', 'niski', 'umiarkowany', 'wysoki', 'ekstremalny']],
];

const RP_TYPY = [
    'sesja'    => ['n' => 'Sesja',               'opis' => 'Opowieść w Centrum, tury i narracje prowadzącego'],
    'klub'     => ['n' => 'Wydarzenie w Klubie', 'opis' => 'Sala Główna Klubu, na żywo z panelem prowadzącego'],
    'swobodna' => ['n' => 'Swobodna',            'opis' => 'Prywatnie z zaproszonymi graczami, bez prowadzącego'],
];

/* mult — mnożnik OoP przy obrażeniach; dbl — mnożnik kar z krytycznego pecha;
   kryt — czy efekty krytyczne trzeba rozpatrywać (na niższych poziomach prowadzący może je włączyć). */
const RP_POZIOMY = [
    'swobodna'    => ['n' => 'Swobodna',    'kolor' => '#b48cff', 'mult' => 5,  'dbl' => 1, 'kryt' => false, 'opis' => 'Prywatna sesja graczy. Rzucają sami, bez PW. Życie i Urazy liczą się.'],
    'niski'       => ['n' => 'Niski',       'kolor' => '#3dff9a', 'mult' => 5,  'dbl' => 1, 'kryt' => false, 'opis' => 'Proste zadania, krytyki opcjonalne.'],
    'umiarkowany' => ['n' => 'Umiarkowany', 'kolor' => '#ffd23d', 'mult' => 5,  'dbl' => 1, 'kryt' => false, 'opis' => 'Realne ryzyko, walka wg Systemu walki.'],
    'wysoki'      => ['n' => 'Wysoki',      'kolor' => '#ff7a3d', 'mult' => 10, 'dbl' => 2, 'kryt' => true,  'opis' => 'Obrażenia OoP × 10, krytyki obowiązkowe, pech ×2.'],
    'ekstremalny' => ['n' => 'Ekstremalny', 'kolor' => '#ff1744', 'mult' => 10, 'dbl' => 2, 'kryt' => true,  'opis' => 'Tylko MG. Możliwa śmierć postaci.'],
];

function rp_ranga(mysqli $db, int $gid): string {
    static $c = [];
    if (isset($c[$gid])) return $c[$gid];
    $g = db_wiersz($db, "SELECT login, ranga_rp, is_mg FROM gracze WHERE id = ?", [$gid]);
    $r = $g['ranga_rp'] ?? 'gracz';
    if (!isset(RP_RANGI[$r])) $r = 'gracz';
    if ($r === 'gracz' && $g && rp_mg_konto($g)) $r = 'mg';
    return $c[$gid] = $r;
}

function rp_moze_zalozyc(string $ranga, string $typ, string $poziom): bool {
    $R = RP_RANGI[$ranga] ?? RP_RANGI['gracz'];
    if (!in_array($typ, $R['typy'], true) || !in_array($poziom, $R['poziomy'], true)) return false;
    return ($typ === 'swobodna') === ($poziom === 'swobodna');   // Swobodna ma zawsze poziom Swobodna
}

/** MG i Adminka: akceptacja Opowieści JMG, zgłoszenia, nadawanie rang. */
function rp_nadzor(string $ranga): bool { return $ranga === 'mg' || $ranga === 'adminka'; }

function rp_wymaga_akceptacji(string $ranga, string $typ): bool { return !empty(RP_RANGI[$ranga]['akceptacja']) && $typ !== 'swobodna'; }

/** Loginy z uprawnieniem nadzoru — do powiadomień o zgłoszeniach i akceptacjach. */
function rp_nadzorcy(mysqli $db): array {
    global $MISTRZOWIE_GRY;
    $ids = [];
    foreach (db_wiersze($db, "SELECT id FROM gracze WHERE ranga_rp IN ('mg','adminka') OR is_mg = 1") as $w) $ids[(int)$w['id']] = true;
    foreach (($MISTRZOWIE_GRY ?? ['StormChaser92']) as $l) { $w = db_wiersz($db, "SELECT id FROM gracze WHERE login = ?", [$l]); if ($w) $ids[(int)$w['id']] = true; }
    return array_keys($ids);
}
