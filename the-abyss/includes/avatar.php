<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/AVATAR.PHP
   Jedno miejsce, które zamienia wpis z gracze.avatar na działający adres.

   W bazie są ścieżki z różnych wersji ustawień: „avatars/…", „uploads/avatars/…",
   czasem pełne adresy (martwy via.placeholder.com). Funkcja szuka pliku w znanych
   katalogach i zwraca ścieżkę tylko wtedy, gdy plik naprawdę istnieje.
   Pusty wynik = pokaż zastępczy gradient z inicjałami.
   ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('avatar_url')) {

function avatar_url(?string $sciezka): string {
    static $cache = [];
    $p = trim((string)$sciezka);
    if ($p === '') return '';
    if (isset($cache[$p])) return $cache[$p];

    if (preg_match('~^https?://~i', $p)) {
        return $cache[$p] = (stripos($p, 'via.placeholder.com') !== false) ? '' : $p;
    }

    $p2   = ltrim(str_replace('\\', '/', $p), '/');
    $plik = basename($p2);
    $root = dirname(__DIR__);                     // katalog the-abyss/
    foreach ([$p2, 'uploads/' . $p2, 'uploads/avatars/' . $plik, 'avatars/' . $plik] as $kandydat) {
        $pelna = $root . '/' . $kandydat;
        if (is_file($pelna) && filesize($pelna) > 0) return $cache[$p] = $kandydat;
    }
    return $cache[$p] = '';
}

/** Inicjały do zastępczego portretu. */
function avatar_inicjaly(string $login): string {
    $czyste = preg_replace('/\s*\[(BARMAN|MG|NPC)\]/i', '', $login);
    $czesci = preg_split('/[\s_\-]+/u', trim($czyste), -1, PREG_SPLIT_NO_EMPTY);
    if (count($czesci) >= 2) return mb_strtoupper(mb_substr($czesci[0], 0, 1) . mb_substr($czesci[1], 0, 1));
    return mb_strtoupper(mb_substr($czyste, 0, 2));
}

}
