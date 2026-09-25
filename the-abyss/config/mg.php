<?php
/* the-abyss/config/mg.php
   Mistrzowie Gry. Jedna funkcja czy_mg() dla całej gry:
   - czy_mg(int $id)      — po id gracza (tak woła helpers/firmy.php),
   - czy_mg(string $login) — po loginie (boss, melina, turniej).
   MG to konto z gracze.is_mg = 1 albo login z listy poniżej (bez wielkości liter).
   Plik można dołączać wiele razy — funkcja deklaruje się raz. */

$GLOBALS['MISTRZOWIE_GRY'] = [
    'StormChaser92',
];
$MISTRZOWIE_GRY = $GLOBALS['MISTRZOWIE_GRY'];

if (!function_exists('czy_mg')) {
function czy_mg($kto): bool {
    $db = $GLOBALS['polaczenie'] ?? null;
    if ($kto === null || $kto === '') return false;
    if (is_int($kto) || ctype_digit((string)$kto)) {
        if (!$db) return false;
        $r = $db->query("SELECT login, is_mg FROM gracze WHERE id = " . (int)$kto);
        $g = $r ? $r->fetch_assoc() : null;
        if (!$g) return false;
        if (!empty($g['is_mg'])) return true;
        $login = (string)$g['login'];
    } else {
        $login = (string)$kto;
        if ($db && ($st = $db->prepare("SELECT is_mg FROM gracze WHERE login = ?"))) {
            $st->bind_param('s', $login); $st->execute();
            $g = $st->get_result()->fetch_assoc();
            if (!empty($g['is_mg'])) return true;
        }
    }
    foreach ($GLOBALS['MISTRZOWIE_GRY'] as $m) if (strcasecmp($m, $login) === 0) return true;
    return false;
}
}
