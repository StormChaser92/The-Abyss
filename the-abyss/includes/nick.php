<?php
/* the-abyss/includes/nick.php — nick gracza z ikonami rang i własnym kolorem.
   Rangi: is_admin, ranga_rp (mg / jmg / gospodarz), is_mg lub lista MG, is_barman, is_proboszcz, is_premium.
   Kolor (nick_kolor + nick_poswiata 0–3) widać tylko, gdy gracz ma rangę inną niż VIP.
   Po utracie rangi kolor zostaje w bazie, ale się nie wyświetla.
   Użycie w SQL:  "SELECT " . nk_pola('g') . " FROM gracze g"  → nk_html($wiersz)
   Z prefiksem:   nk_pola('g', 'w_')  → nk_html(nk_wiersz($wiersz, 'w_'))            */

if (!function_exists('nk_html')) {
require_once __DIR__ . '/../config/mg.php';   // lista $MISTRZOWIE_GRY (MG z urzędu)

define('NK_RANGI', [
    'admin'     => ['n' => 'Administrator/ka',    'k' => '#4ad6ff', 'i' => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>'],
    'mg'        => ['n' => 'Mistrz/yni Gry',      'k' => '#c896ff', 'i' => '<path d="M12 2.5l8.5 5v9L12 21.5l-8.5-5v-9z"/><path d="M12 2.5L7 11h10z"/><path d="M7 11l5 10.5L17 11"/><path d="M3.5 7.5L7 11M20.5 7.5L17 11"/>'],
    'jmg'       => ['n' => 'Junior MG',           'k' => '#9fa8ff', 'i' => '<rect x="4.5" y="4.5" width="15" height="15" rx="2.5"/><circle cx="9" cy="9" r=".9"/><circle cx="15" cy="15" r=".9"/><circle cx="12" cy="12" r=".9"/>'],
    'gospodarz' => ['n' => 'Gospodarz/yni Klubu', 'k' => '#ffb86b', 'i' => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M18 12v3M15 12v2"/>'],
    'barman'    => ['n' => 'Barman/ka',           'k' => '#ff7a3d', 'i' => '<path d="M4 4h16l-8 9z"/><path d="M12 13v7M8 20h8"/>'],
    'proboszcz' => ['n' => 'Proboszcz',           'k' => '#f2dc8c', 'i' => '<path d="M12 3v18M7 8h10"/>'],
    'vip'       => ['n' => 'VIP',                 'k' => '#ffd700', 'i' => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>'],
]);
define('NK_KOLUMNY', ['id', 'login', 'is_premium', 'is_mg', 'is_barman', 'is_proboszcz', 'is_admin', 'ranga_rp', 'nick_kolor', 'nick_poswiata']);
define('NK_SZYBKIE', ['#ff3d5e', '#ff7a3d', '#ffd700', '#f2dc8c', '#5aff9a', '#4ad6ff', '#9fa8ff', '#c896ff', '#ffffff']);

/** Kolumny do SELECT. $alias = alias tabeli gracze, $prefiks = przedrostek kluczy w wyniku. */
function nk_pola(string $alias = '', string $prefiks = ''): string {
    $a = $alias !== '' ? "$alias." : '';
    return implode(', ', array_map(fn($k) => $a . $k . ($prefiks !== '' ? " AS {$prefiks}{$k}" : ''), NK_KOLUMNY));
}
function nk_wiersz(array $r, string $prefiks): array {
    $o = [];
    foreach (NK_KOLUMNY as $k) $o[$k] = $r[$prefiks . $k] ?? null;
    return $o;
}

function nk_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Klucze rang gracza w stałej kolejności. */
function nk_rangi(array $g): array {
    $r = [];
    if (!empty($g['is_admin'])) $r[] = 'admin';
    $rp = (string)($g['ranga_rp'] ?? 'gracz');
    $mg = $rp === 'mg' || !empty($g['is_mg']);
    if (!$mg) foreach (($GLOBALS['MISTRZOWIE_GRY'] ?? []) as $l) if (strcasecmp((string)$l, (string)($g['login'] ?? '')) === 0) { $mg = true; break; }
    if ($mg) $r[] = 'mg';
    elseif ($rp === 'jmg') $r[] = 'jmg';
    if ($rp === 'gospodarz') $r[] = 'gospodarz';
    if (!empty($g['is_barman'])) $r[] = 'barman';
    if (!empty($g['is_proboszcz'])) $r[] = 'proboszcz';
    if (!empty($g['is_premium'])) $r[] = 'vip';
    return $r;
}

/** Własny kolor przysługuje każdej randze poza samym VIP-em. */
function nk_moze_kolor(array $g): bool { return (bool)array_diff(nk_rangi($g), ['vip']); }

function nk_hex_ok($h): bool { return is_string($h) && preg_match('/^#[0-9a-f]{6}$/i', $h) === 1; }

/** Rozjaśnia kolor, aż kontrast z tłem gry (#0a0508) wyniesie co najmniej 4.5:1. */
function nk_czytelny(string $hex): string {
    $lum = function (string $h): float {
        $v = array_map(function ($i) use ($h) { $c = hexdec(substr($h, $i, 2)) / 255; return $c <= .03928 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4; }, [1, 3, 5]);
        return .2126 * $v[0] + .7152 * $v[1] + .0722 * $v[2];
    };
    $tlo = $lum('#0a0508');
    $mix = fn(string $h, float $t): string => '#' . implode('', array_map(fn($i) => str_pad(dechex((int)round(hexdec(substr($h, $i, 2)) + (255 - hexdec(substr($h, $i, 2))) * $t)), 2, '0', STR_PAD_LEFT), [1, 3, 5]));
    $x = strtolower($hex); $t = 0.0;
    while ((($lum($x) + .05) / ($tlo + .05)) < 4.5 && $t < 1) { $t += .05; $x = $mix($hex, min(1, $t)); }
    return $x;
}

function nk_poswiata(string $c, int $p): string {
    return [
        0 => 'none',
        1 => "0 0 4px $c",
        2 => "0 0 4px $c,0 0 10px $c",
        3 => "0 0 2px #fff,0 0 7px $c,0 0 16px $c,0 0 28px $c",
    ][max(0, min(3, $p))];
}

/** Styl inline nicka ('' = domyślny kolor miejsca, w którym stoi). VIP bez rangi: złoty jak dotąd. */
function nk_styl(array $g): string {
    if (nk_moze_kolor($g) && nk_hex_ok($g['nick_kolor'] ?? null)) {
        $c = nk_czytelny($g['nick_kolor']);
        return "color:$c;text-shadow:" . nk_poswiata($c, (int)($g['nick_poswiata'] ?? 0));
    }
    if (!empty($g['is_premium']) && !nk_moze_kolor($g)) return 'color:#ffd700;text-shadow:0 0 6px rgba(255,215,0,.45)';
    return '';
}

function nk_ikona(string $k, string $klasa = 'nk-r'): string {
    $r = NK_RANGI[$k];
    return "<span class=\"$klasa\" style=\"color:{$r['k']}\" title=\"" . nk_h($r['n']) . "\" aria-label=\"" . nk_h($r['n']) . "\"><svg viewBox=\"0 0 24 24\" aria-hidden=\"true\">{$r['i']}</svg></span>";
}
function nk_ikony(array $g): string { return implode('', array_map('nk_ikona', nk_rangi($g))); }

/** Odznaki z nazwą rangi (nagłówek profilu). */
function nk_odznaki(array $g): string {
    return implode('', array_map(function ($k) {
        $r = NK_RANGI[$k];
        return "<span class=\"nk-odz\" style=\"color:{$r['k']}\"><svg viewBox=\"0 0 24 24\" aria-hidden=\"true\">{$r['i']}</svg>" . nk_h($r['n']) . "</span>";
    }, nk_rangi($g)));
}

/** Nick z ikonami. $o: link (bool, domyślnie true), klasa (dodatkowa klasa wrappera), tag (dla link=false). */
function nk_html(array $g, array $o = []): string {
    $link = $o['link'] ?? true;
    $styl = nk_styl($g);
    $attr = 'class="nk-n"' . ($styl !== '' ? " style=\"$styl\"" : '');
    $nick = $link && !empty($g['id'])
        ? '<a href="game.php?page=profil&amp;id=' . (int)$g['id'] . "\" $attr>" . nk_h($g['login'] ?? '?') . '</a>'
        : '<' . ($o['tag'] ?? 'span') . " $attr>" . nk_h($g['login'] ?? '?') . '</' . ($o['tag'] ?? 'span') . '>';
    return '<span class="nk' . (!empty($o['klasa']) ? ' ' . nk_h($o['klasa']) : '') . '">' . $nick . nk_ikony($g) . '</span>';
}

/** Dane do JSON (czat Klubu): styl nicka i gotowy HTML ikon. */
function nk_json(array $g): array { return ['nk_styl' => nk_styl($g), 'nk_ikony' => nk_ikony($g)]; }

/** Zapis koloru z formularza (Ustawienia / Profil). Zwraca komunikat albo ''. */
function nk_zapisz(mysqli $db, int $gid): string {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || (!isset($_POST['nk_zapisz']) && !isset($_POST['nk_reset']))) return '';
    $g = db_wiersz($db, 'SELECT ' . nk_pola() . ' FROM gracze WHERE id = ?', [$gid]);
    if (!$g || !nk_moze_kolor($g)) return 'Własny kolor nicka mają tylko gracze z rangą.';
    if (!empty($_POST['nk_reset'])) { db_q($db, 'UPDATE gracze SET nick_kolor = NULL, nick_poswiata = 0 WHERE id = ?', [$gid]); return 'Przywrócono domyślny kolor nicka.'; }
    $k = strtolower(trim((string)($_POST['nk_kolor'] ?? '')));
    $p = max(0, min(3, (int)($_POST['nk_poswiata'] ?? 0)));
    if (!nk_hex_ok($k)) return 'Niepoprawny kolor.';
    db_q($db, 'UPDATE gracze SET nick_kolor = ?, nick_poswiata = ? WHERE id = ?', [$k, $p, $gid]);
    return 'Zapisano kolor nicka.';
}

}
