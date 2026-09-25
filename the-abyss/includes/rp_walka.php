<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/RP_WALKA.PHP
   System walki w Opowieściach (na podstawie „Tymczasowego systemu walki”).
   Atrybuty: Krzepa → Siła, Zwinność → Zręczność, Intelekt → Inteligencja,
   Ogłada → Charyzma. Bez magii. Wszystkie rzuty losuje serwer.

   Trafienie:  wręcz/miotana — Zręczność, dystans — Zmysły (+ mod broni)
   Unik:       Zręczność + mod pancerza − 2×OoP sceny (maks. −50)
   Obrażenia:  wręcz/miotana — Siła, dystans — Zręczność (+ mod broni)
               sukces = OoP × 5 (Wysoki/Ekstremalny: × 10), minus % pancerza
   Szansa = Atrybut + mody, limit PE (80). Krytyki: ≤ 5 / ≥ 95.
   Funkcje liczą wynik i zwracają listę zmian (ops) — zapis dopiero po
   decyzji prowadzącego (Opublikuj / Ukryty), patrz rw_zastosuj().
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config/umiejetnosci.php';
require_once __DIR__ . '/../config/rangi.php';

const RW_BRON = [
    'wrecz'    => ['n' => 'Walka wręcz',                   't' => 'zw', 'hm' => 0,   'dm' => 0],
    'noz'      => ['n' => 'Nóż / sztylet (mała)',          't' => 'zw', 'hm' => 5,   'dm' => 10],
    'palka'    => ['n' => 'Pałka / maczeta (tradycyjna)',  't' => 'zw', 'hm' => 0,   'dm' => 20],
    'miecz'    => ['n' => 'Miecz (tradycyjna, dawna)',     't' => 'zw', 'hm' => 0,   'dm' => 20],
    'topor'    => ['n' => 'Młot / topór strażacki (ciężka)', 't' => 'zw', 'hm' => -10, 'dm' => 35],
    'pistolet' => ['n' => 'Pistolet',                      't' => 'dy', 'hm' => 0,   'dm' => 10],
    'dwa'      => ['n' => 'Dwa pistolety',                 't' => 'dy', 'hm' => -5,  'dm' => 20],
    'strzelba' => ['n' => 'Strzelba',                      't' => 'dy', 'hm' => -5,  'dm' => 25],
    'karabin'  => ['n' => 'Karabin',                       't' => 'dy', 'hm' => -10, 'dm' => 35],
    'snajper'  => ['n' => 'Karabin snajperski',            't' => 'dy', 'hm' => -20, 'dm' => 45],
    'luk'      => ['n' => 'Łuk (dawna)',                   't' => 'dy', 'hm' => 0,   'dm' => 15],
    'kusza'    => ['n' => 'Kusza (dawna)',                 't' => 'dy', 'hm' => -10, 'dm' => 35],
    'mil'      => ['n' => 'Broń miotana lekka',            't' => 'mi', 'hm' => 0,   'dm' => 10],
    'mic'      => ['n' => 'Broń miotana ciężka',           't' => 'mi', 'hm' => -5,  'dm' => 20],
];
const RW_TYP     = ['zw' => ['Z', 'S'], 'dy' => ['Zm', 'Z'], 'mi' => ['Z', 'S']];   // [Trafienie, Obrażenia]
const RW_PANC    = ['brak' => ['n' => 'Brak', 'r' => 0, 'u' => 0], 'kam' => ['n' => 'Kamizelka kuloodporna', 'r' => 10, 'u' => -10], 'takt' => ['n' => 'Pancerz taktyczny', 'r' => 20, 'u' => -20]];
const RW_TARCZA  = ['r' => 20, 'u' => -10];
const RW_URAZ    = ['—', 'lekki', 'średni', 'ciężki', 'krytyczny'];
const RW_EF_TRAF = ['Negacja pancerza', 'Ogłuszenie', 'Powalenie', 'Rozbrojenie', 'Krwawienie', 'Zachwianie'];
const RW_WYS     = ['2' => ['2–3 m', -20], '4' => ['4–5 m', 0], '6' => ['6–8 m', 20], '9' => ['9–12 m', 30], '12' => ['12–15 m', 40], '16' => ['16+ m', 50]];
const RW_ATR_KL  = ['S', 'Z', 'W', 'I', 'Zm', 'Ch'];

function rw_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rw_sgn(int $v): string { return $v > 0 ? "+$v" : ($v < 0 ? '−' . abs($v) : ''); }
function rw_k100(): int { return random_int(1, 100); }
function rw_szansa(int $base, int $mod): int { return max(0, min(UM_PE, $base + max(UM_MOD_MIN, min(UM_MOD_MAX, $mod)))); }

function rw_parametry(array $s): array {
    $p = RP_POZIOMY[$s['poziom'] ?? 'niski'] ?? RP_POZIOMY['niski'];
    return ['oop' => max(1, (int)($s['oop'] ?? 2)), 'mult' => $p['mult'], 'dbl' => $p['dbl'], 'kryt' => $p['kryt']];
}

/* ── UCZESTNICY ───────────────────────────────────────────────────── */
function rw_postac(mysqli $db, int $sid, string $k): ?array {
    $id = (int)substr($k, 1);
    return $k[0] === 'g' ? db_wiersz($db, "SELECT * FROM gracze WHERE id = ?", [$id])
                         : db_wiersz($db, "SELECT * FROM sesje_npc WHERE id = ? AND sesja_id = ?", [$id, $sid]);
}
function rw_nazwa(array $row, string $k): string { return $k[0] === 'g' ? (string)$row['login'] : (string)$row['nazwa']; }

/** Uczestnicy walki w kolejności Inicjatywy: klucz => [k, npc, nazwa, g (wiersz postaci), w (wiersz trackera), fx, ua] */
function rw_uczestnicy(mysqli $db, int $sid): array {
    $out = [];
    foreach (db_wiersze($db, "SELECT * FROM sesje_walka WHERE sesja_id = ? ORDER BY ini DESC, klucz", [$sid]) as $w) {
        $g = rw_postac($db, $sid, $w['klucz']);
        if (!$g) continue;
        $out[$w['klucz']] = ['k' => $w['klucz'], 'npc' => $w['klucz'][0] === 'n', 'nazwa' => rw_nazwa($g, $w['klucz']), 'g' => $g, 'w' => $w,
            'fx' => json_decode($w['efekty'] ?: '[]', true) ?: [], 'ua' => $w['urazy_atr'] === '' ? [] : explode(',', $w['urazy_atr'])];
    }
    return $out;
}

/** Atrybut po karach z Urazów (−20% za każdy Uraz, który trafił w ten Atrybut). */
function rw_atr(array $u, string $k): int { return (int)round(um_atrybut($u['g'], $k) * (0.8 ** count(array_keys($u['ua'], $k, true)))); }
function rw_fx(array $u, string $pole): int {
    $s = 0;
    foreach ($u['fx'] as $f) { $s += (int)($f[$pole] ?? 0); if ($f['n'] === 'Powalenie' && ($pole === 'hit' || $pole === 'unik')) $s -= 40; }
    return $s;
}
function rw_ma(array $u, string $n): bool { foreach ($u['fx'] as $f) if ($f['n'] === $n) return true; return false; }
function rw_eff_max(array $w): int { return (int)round((int)$w['hp_max'] * (1 - 0.2 * min(4, (int)$w['uraz']))); }

/* ── HTML ─────────────────────────────────────────────────────────── */
function rw_wiersz(string $tytul, int $rzut, int $szansa, string $opis, array $w): string {
    $kl = $w['poziom'] === 2 ? 'cs' : ($w['poziom'] === -2 ? 'cf' : '');
    return "<div class='pmr-row'><div class='pmr-d $kl'><b>$rzut</b><small>/ $szansa%</small></div><div class='pmr-o'><strong>" . rw_h($tytul) . "</strong><span>" . rw_h($opis) . "</span></div><span class='pmr-s s{$w['poziom']}'>" . rw_h($w['nazwa']) . "</span></div>";
}
function rw_karta(string $tytul, string $etykieta, string $wnetrze): string {
    return "<div class='pmr'><div class='pmr-h'><b>" . rw_h($tytul) . "</b><span>" . rw_h($etykieta) . "</span></div>$wnetrze</div>";
}

/* ── AKCJE (zwracają ['html','skrot','ops']) ──────────────────────── */
function rw_atak(array $U, string $a, string $t, string $bron, int $mod_mg, array $P, bool $kryt): array {
    $A = $U[$a]; $T = $U[$t]; $B = RW_BRON[$bron] ?? RW_BRON['wrecz']; [$ha, $da] = RW_TYP[$B['t']];
    global $UM_ATRYBUTY;
    $pa = RW_PANC[$T['w']['pancerz']] ?? RW_PANC['brak']; $tar = (int)$T['w']['tarcza'];
    $hit   = rw_szansa(rw_atr($A, $ha), $B['hm'] + rw_fx($A, 'hit') + $mod_mg);
    $u_mod = $pa['u'] + ($tar ? RW_TARCZA['u'] : 0) - min(50, 2 * $P['oop']) + rw_fx($T, 'unik');
    $unik  = (rw_ma($T, 'Ogłuszenie') || (int)$T['w']['hp'] <= 0) ? 0 : rw_szansa(rw_atr($T, 'Z'), $u_mod);
    $dmg_s = rw_szansa(rw_atr($A, $da), $B['dm'] + rw_fx($A, 'dmg'));
    $red   = min(60, $pa['r'] + ($tar ? RW_TARCZA['r'] : 0));
    $an = $A['nazwa']; $tn = $T['nazwa']; $d = $P['dbl']; $ops = [];

    $r1 = rw_k100(); $w1 = um_wynik($r1, $hit);
    $h = rw_wiersz('Trafienie · ' . $UM_ATRYBUTY[$ha]['nazwa'], $r1, $hit, $UM_ATRYBUTY[$ha]['nazwa'] . ' ' . rw_atr($A, $ha) . ' ' . rw_sgn($B['hm']) . ' ' . rw_sgn(rw_fx($A, 'hit')) . ' ' . rw_sgn($mod_mg), $w1);
    if (!$w1['sukces']) {
        $sum = 'Atak chybia.';
        if ($w1['poziom'] === -2 && $kryt) {
            if (random_int(1, 2) === 1) { $x = (int)round((int)$A['w']['hp_max'] * 0.1 * $d); $ops[] = ['hp', $a, -$x]; $sum = "Krytyczny pech: $an rani się sam (−$x PŻ)."; }
            else { $ops[] = ['fx', $a, ['n' => 'Zachwianie', 't' => $d, 'hit' => -10, 'unik' => -10]]; $sum = "Krytyczny pech: $an traci równowagę (Zachwianie, $d t.)."; }
        }
    } else {
        $r2 = rw_k100(); $w2 = um_wynik($r2, $unik);
        $unik_ok = $unik > 0 && $w2['sukces'] && ($w1['poziom'] < 2 || $w2['poziom'] === 2);
        $h .= rw_wiersz('Unik · Zręczność', $r2, $unik, 'Zręczność ' . rw_atr($T, 'Z') . ' ' . rw_sgn($u_mod) . ($w1['poziom'] === 2 ? ' · atak krytyczny: unik wymaga krytyka' : ''), $w2);
        if ($kryt && $w2['poziom'] === 2)  $ops[] = ['fx', $t, random_int(1, 2) === 1 ? ['n' => '+5 Trafienie', 't' => 1, 'hit' => 5] : ['n' => '+5 Unik', 't' => 1, 'unik' => 5]];
        if ($kryt && $w2['poziom'] === -2) $ops[] = ['fx', $t, random_int(1, 2) === 1 ? ['n' => '−' . (5 * $d) . ' Trafienie', 't' => 1, 'hit' => -5 * $d] : ['n' => '−' . (5 * $d) . ' Unik', 't' => 1, 'unik' => -5 * $d]];
        if ($unik_ok) $sum = "$tn unika ciosu.";
        else {
            $r3 = rw_k100(); $w3 = um_wynik($r3, $dmg_s);
            $h .= rw_wiersz('Obrażenia · ' . $UM_ATRYBUTY[$da]['nazwa'], $r3, $dmg_s, $UM_ATRYBUTY[$da]['nazwa'] . ' ' . rw_atr($A, $da) . ' ' . rw_sgn($B['dm']) . ' ' . rw_sgn(rw_fx($A, 'dmg')), $w3);
            $ef  = ($kryt && $w1['poziom'] === 2) ? RW_EF_TRAF[random_int(0, 5)] : null;
            $neg = $ef === 'Negacja pancerza';
            $base = $P['oop'] * $P['mult']; $r = $neg ? 0 : $red;
            $dmg = $w3['sukces'] ? (int)round($base * (1 - $r / 100)) : 0;
            if ($kryt && $w3['poziom'] === -2) $ops[] = ['fx', $a, ['n' => '−' . (10 * $d) . ' Obrażenia', 't' => 1, 'dmg' => -10 * $d]];
            if ($dmg) $ops[] = ['hp', $t, -$dmg];
            if ($w3['poziom'] === 2) $ops[] = ['uraz', $t];
            $pada = $dmg > 0 && (int)$T['w']['hp'] - $dmg <= 0;
            if ($pada) $ops[] = ['uraz', $t];
            if ($ef && !$neg) $ops[] = ['fx', $t, $ef === 'Krwawienie' ? ['n' => 'Krwawienie', 't' => 3, 'p' => max(1, (int)round(($dmg ?: $base) * 0.1))]
                                                 : ($ef === 'Zachwianie' ? ['n' => 'Zachwianie', 't' => 1, 'hit' => -10, 'unik' => -10] : ['n' => $ef, 't' => 1])];
            $sum = $w3['sukces'] ? "<em>−$dmg PŻ</em> <small>$base (OoP {$P['oop']} × {$P['mult']})" . ($r ? " − $r% pancerz" : '') . ($neg ? ' · pancerz zignorowany' : '') . "</small>"
                                 : 'Trafiony, ale bez obrażeń <small>(inne efekty trafienia działają)</small>';
            if ($ef) $sum .= " · krytyk: <em>$ef</em>";
            if ($w3['poziom'] === 2) $sum .= ' · <em>Uraz</em>';
            if ($pada) $sum .= ' · ' . rw_h($tn) . ' pada (0 PŻ, Uraz za ostatni cios)';
        }
    }
    $h .= "<div class='pmr-sum'>$sum</div>";
    return ['html' => rw_karta("$an → $tn", $B['n'], $h), 'skrot' => strip_tags($sum), 'ops' => $ops, 'tytul' => "Atak: $an → $tn"];
}

function rw_odpoczynek(array $U, string $k, array $P): array {
    $A = $U[$k]; $s = rw_szansa(rw_atr($A, 'Ch'), 0); $r = rw_k100(); $w = um_wynik($r, $s);
    $g = max(0, min($P['oop'] * 5, rw_eff_max($A['w']) - (int)$A['w']['hp']));
    $h = rw_wiersz('Charyzma (Ogłada)', $r, $s, 'Charyzma ' . rw_atr($A, 'Ch'), $w)
       . "<div class='pmr-sum'>" . ($w['sukces'] ? "<em>+$g PŻ</em> <small>OoP {$P['oop']} × 5, do limitu " . rw_eff_max($A['w']) . "</small>" : 'Nie udaje się złapać oddechu.') . "</div>";
    return ['html' => rw_karta($A['nazwa'] . ' · Odpoczynek', 'akcja pełna', $h), 'skrot' => $w['sukces'] ? "+$g PŻ" : 'porażka', 'ops' => $w['sukces'] && $g ? [['hp', $k, $g]] : [], 'tytul' => 'Odpoczynek: ' . $A['nazwa']];
}

function rw_upadek(array $U, string $k, string $wys, int $pod, bool $akr): array {
    $T = $U[$k]; $W = RW_WYS[$wys] ?? RW_WYS['4'];
    $m = $W[1] + $pod + ($T['w']['pancerz'] === 'takt' ? 10 : 0); $r = rw_k100();
    $proc = max(0, min(100, $r + $m)); if ($akr && (int)$wys <= 4) $proc = (int)round($proc / 2);
    $d = (int)round((int)$T['w']['hp_max'] * $proc / 100); $u = intdiv($proc, 30);
    $ops = $d ? [['hp', $k, -$d]] : []; for ($i = 0; $i < $u; $i++) $ops[] = ['uraz', $k];
    $h = "<div class='pmr-row'><div class='pmr-d'><b>$r</b><small>k100</small></div><div class='pmr-o'><strong>$proc% max PŻ</strong><span>k100 $r " . (rw_sgn($m) ?: '+0') . ($akr ? ' · Akrobatyka: połowa' : '') . "</span></div><span class='pmr-s s-1'>−$d PŻ</span></div>"
       . "<div class='pmr-sum'><em>−$d PŻ</em>" . ($u ? " · <em>$u × Uraz</em> <small>(każde 30% max PŻ)</small>" : '') . "</div>";
    return ['html' => rw_karta($T['nazwa'] . ' · Upadek ' . $W[0], 'poza walką', $h), 'skrot' => "−$d PŻ, Urazy: $u", 'ops' => $ops, 'tytul' => 'Upadek: ' . $T['nazwa']];
}

/* ── ZAPIS ────────────────────────────────────────────────────────── */
function rw_zastosuj(mysqli $db, int $sid, array $ops): void {
    foreach ($ops as $op) {
        $k = (string)$op[1];
        $w = db_wiersz($db, "SELECT * FROM sesje_walka WHERE sesja_id = ? AND klucz = ?", [$sid, $k]);
        if (!$w) continue;
        $fx = json_decode($w['efekty'] ?: '[]', true) ?: [];
        $ua = $w['urazy_atr'] === '' ? [] : explode(',', $w['urazy_atr']);
        if ($op[0] === 'hp')   $w['hp'] = (int)$w['hp'] + (int)$op[2];
        if ($op[0] === 'uraz') { $w['uraz'] = min(4, (int)$w['uraz'] + 1); $ua[] = RW_ATR_KL[random_int(0, 5)]; }
        if ($op[0] === 'fx')   $fx[] = $op[2];
        $w['hp'] = max(0, min(rw_eff_max($w), (int)$w['hp']));
        $kt = $w['kryt_tury'];
        if ($k[0] === 'g' && (int)$w['hp'] === 0 && (int)$w['uraz'] >= 4 && $kt === null) $kt = 2;
        db_zmien($db, "UPDATE sesje_walka SET hp = ?, uraz = ?, urazy_atr = ?, efekty = ?, kryt_tury = ? WHERE sesja_id = ? AND klucz = ?",
            [(int)$w['hp'], (int)$w['uraz'], implode(',', $ua), json_encode($fx, JSON_UNESCAPED_UNICODE), $kt, $sid, $k], 'iissiis');
    }
}

function rw_dodaj(mysqli $db, int $sid, string $k): bool {
    $g = rw_postac($db, $sid, $k);
    if (!$g) return false;
    $hp = max(1, (int)($g['hp_max'] ?? 100));
    $ini = rw_k100() + intdiv(um_atrybut($g, 'Z'), 5);
    return db_zmien($db, "INSERT IGNORE INTO sesje_walka (sesja_id, klucz, ini, hp, hp_max, efekty, bron, pancerz, nc_ok) VALUES (?, ?, ?, ?, ?, '[]', ?, ?, ?)",
        [$sid, $k, $ini, $hp, $hp, $k[0] === 'n' ? ($g['bron'] ?? 'wrecz') : 'wrecz', $k[0] === 'n' ? ($g['pancerz'] ?? 'brak') : 'brak', $k[0] === 'n' ? 1 : 0]) === 1;
}

/** Start walki: wszyscy zaakceptowani gracze + NPC Opowieści. Zwraca HTML Inicjatywy. */
function rw_start(mysqli $db, int $sid): string {
    db_zmien($db, "DELETE FROM sesje_walka WHERE sesja_id = ?", [$sid]);
    foreach (db_wiersze($db, "SELECT gracz_id FROM sesje_uczestnicy WHERE sesja_id = ? AND rola = 'Gracz' AND status_akceptacji = 'Zaakceptowany'", [$sid]) as $u) rw_dodaj($db, $sid, 'g' . $u['gracz_id']);
    foreach (db_wiersze($db, "SELECT id FROM sesje_npc WHERE sesja_id = ?", [$sid]) as $n) rw_dodaj($db, $sid, 'n' . $n['id']);
    db_zmien($db, "UPDATE sesje_rpg SET walka_aktywna = 1, walka_runda = 1, walka_tura = 0 WHERE id = ?", [$sid]);
    return rw_inicjatywa_html($db, $sid, 'Walka! Inicjatywa');
}

function rw_inicjatywa(mysqli $db, int $sid): string {
    foreach (rw_uczestnicy($db, $sid) as $k => $u)
        db_zmien($db, "UPDATE sesje_walka SET ini = ? WHERE sesja_id = ? AND klucz = ?", [rw_k100() + intdiv(rw_atr($u, 'Z'), 5), $sid, $k]);
    db_zmien($db, "UPDATE sesje_rpg SET walka_tura = 0 WHERE id = ?", [$sid]);
    return rw_inicjatywa_html($db, $sid, 'Inicjatywa (ponowny rzut)');
}
function rw_inicjatywa_html(mysqli $db, int $sid, string $tytul): string {
    $l = [];
    foreach (rw_uczestnicy($db, $sid) as $u) $l[] = rw_h($u['nazwa']) . ': <b>' . (int)$u['w']['ini'] . '</b>';
    return rw_karta($tytul, 'k100 + Zręczność / 5 · wiążąca do końca potyczki', "<div class='pmr-o'><span>" . implode('<br>', $l) . "</span></div>");
}

/** Koniec rundy: Krwawienie, upływ efektów, odliczanie Urazu krytycznego. Zwraca HTML albo ''. */
function rw_koniec_rundy(mysqli $db, int $sid): string {
    $log = [];
    foreach (rw_uczestnicy($db, $sid) as $k => $u) {
        $w = $u['w']; $hp = (int)$w['hp']; $nowe = [];
        foreach ($u['fx'] as $f) {
            if ($f['n'] === 'Krwawienie') { $hp -= (int)$f['p']; $log[] = rw_h($u['nazwa']) . " traci {$f['p']} PŻ (Krwawienie)"; }
            if (isset($f['t'])) $f['t'] = (int)$f['t'] - 1;
            if (!isset($f['t']) || $f['t'] > 0) $nowe[] = $f;
        }
        $hp = max(0, $hp); $kt = $w['kryt_tury'];
        if ($kt !== null && (int)$kt > 0) { $kt = (int)$kt - 1; $log[] = rw_h($u['nazwa']) . ($kt ? ": do śmierci $kt t. bez pomocy medycznej" : ': czas na pomoc medyczną minął — decyzja MG'); }
        if ($k[0] === 'g' && $hp === 0 && (int)$w['uraz'] >= 4 && $kt === null) $kt = 2;
        db_zmien($db, "UPDATE sesje_walka SET hp = ?, efekty = ?, kryt_tury = ? WHERE sesja_id = ? AND klucz = ?", [$hp, json_encode($nowe, JSON_UNESCAPED_UNICODE), $kt, $sid, $k], 'isiis');
    }
    db_zmien($db, "UPDATE sesje_rpg SET walka_runda = walka_runda + 1, walka_tura = 0 WHERE id = ?", [$sid]);
    return $log ? rw_karta('Koniec rundy', 'efekty odroczone', "<div class='pmr-o'><span>" . implode('<br>', $log) . "</span></div>") : '';
}
