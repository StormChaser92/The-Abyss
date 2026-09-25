<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/UNI_LAMIGLOWKI.PHP
   Łamigłówki zajęć. Serwer generuje planszę, trzyma ją w sesji i sprawdza
   odpowiedź — przeglądarka tylko rysuje. Trudność: 1 Licencjat, 2 Magister, 3 Doktor.
   ═══════════════════════════════════════════════════════════════════════ */

const UNI_LAM_TYPY = ['sudoku' => 'Sudoku 6×6', 'obwod' => 'Łączenie obwodu'];

function uni_lam_tasuj(array $a): array {
    for ($i = count($a) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$a[$i], $a[$j]] = [$a[$j], $a[$i]]; }
    return $a;
}

/* ── SUDOKU 6×6, bloki 2×3 ────────────────────────────────────────── */
function uni_sudoku_generuj(int $stopien): array {
    $cyfry = uni_lam_tasuj([1, 2, 3, 4, 5, 6]);
    $wiersze = []; foreach (uni_lam_tasuj([0, 1, 2]) as $b) foreach (uni_lam_tasuj([0, 1]) as $x) $wiersze[] = $b * 2 + $x;
    $kolumny = []; foreach (uni_lam_tasuj([0, 1]) as $s) foreach (uni_lam_tasuj([0, 1, 2]) as $x) $kolumny[] = $s * 3 + $x;
    $roz = [];
    foreach ($wiersze as $r) { $w = []; foreach ($kolumny as $c) $w[] = $cyfry[(3 * ($r % 2) + intdiv($r, 2) + $c) % 6]; $roz[] = $w; }
    $plansza = $roz;
    $dziury = [1 => 14, 2 => 18, 3 => 22][$stopien] ?? 14;
    foreach (array_slice(uni_lam_tasuj(range(0, 35)), 0, $dziury) as $i) $plansza[intdiv($i, 6)][$i % 6] = 0;
    return ['typ' => 'sudoku', 'plansza' => $plansza];
}

function uni_sudoku_sprawdz(array $dane, $odp): bool {
    if (!is_array($odp) || count($odp) !== 6) return false;
    $g = [];
    for ($r = 0; $r < 6; $r++) {
        if (!isset($odp[$r]) || !is_array($odp[$r]) || count($odp[$r]) !== 6) return false;
        for ($c = 0; $c < 6; $c++) {
            $v = (int)$odp[$r][$c];
            if ($v < 1 || $v > 6) return false;
            if ($dane['plansza'][$r][$c] && $dane['plansza'][$r][$c] !== $v) return false;
            $g[$r][$c] = $v;
        }
    }
    for ($i = 0; $i < 6; $i++) {
        $w = $k = [];
        for ($j = 0; $j < 6; $j++) { $w[] = $g[$i][$j]; $k[] = $g[$j][$i]; }
        if (count(array_unique($w)) !== 6 || count(array_unique($k)) !== 6) return false;
    }
    for ($br = 0; $br < 6; $br += 2) for ($bc = 0; $bc < 6; $bc += 3) {
        $b = [];
        for ($r = $br; $r < $br + 2; $r++) for ($c = $bc; $c < $bc + 3; $c++) $b[] = $g[$r][$c];
        if (count(array_unique($b)) !== 6) return false;
    }
    return true;
}

/* ── ŁĄCZENIE OBWODU — maska N=1 E=2 S=4 W=8, drzewo rozpinające od źródła ── */
const UNI_OB_DIR = [[1, -1, 0, 4], [2, 0, 1, 8], [4, 1, 0, 1], [8, 0, -1, 2]];

function uni_ob_obrot(int $m, int $ile): int { for ($i = 0; $i < ($ile % 4 + 4) % 4; $i++) $m = (($m << 1) | ($m >> 3)) & 15; return $m; }

function uni_ob_rozwiazany(array $d, array $rot): bool {
    $n = $d['n']; [$sr, $sc] = $d['src'];
    $akt = fn($r, $c) => uni_ob_obrot($d['maska'][$r][$c], (int)($rot[$r][$c] ?? 0));
    $zas = [$sr * $n + $sc => true]; $q = [[$sr, $sc]];
    while ($q) {
        [$r, $c] = array_shift($q); $m = $akt($r, $c);
        foreach (UNI_OB_DIR as [$b, $dr, $dc, $bp]) {
            if (!($m & $b)) continue;
            $R = $r + $dr; $C = $c + $dc;
            if ($R < 0 || $C < 0 || $R >= $n || $C >= $n) return false;          // przewód w ścianę
            if (!($akt($R, $C) & $bp)) return false;                                // urwany przewód
            if (!isset($zas[$R * $n + $C])) { $zas[$R * $n + $C] = true; $q[] = [$R, $C]; }
        }
    }
    return count($zas) === $n * $n;
}

function uni_obwod_generuj(int $stopien): array {
    $n = [1 => 4, 2 => 5, 3 => 6][$stopien] ?? 4;
    $src = [intdiv($n, 2), intdiv($n, 2)];
    do {
        $maska = array_fill(0, $n, array_fill(0, $n, 0));
        $odw = [$src[0] * $n + $src[1] => true]; $stos = [$src];
        while ($stos) {
            [$r, $c] = end($stos); $opcje = [];
            foreach (UNI_OB_DIR as $d) { $R = $r + $d[1]; $C = $c + $d[2]; if ($R >= 0 && $C >= 0 && $R < $n && $C < $n && !isset($odw[$R * $n + $C])) $opcje[] = $d; }
            if (!$opcje) { array_pop($stos); continue; }
            [$b, $dr, $dc, $bp] = $opcje[random_int(0, count($opcje) - 1)];
            $maska[$r][$c] |= $b; $maska[$r + $dr][$c + $dc] |= $bp;
            $odw[($r + $dr) * $n + $c + $dc] = true; $stos[] = [$r + $dr, $c + $dc];
        }
        $rot = [];
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) $rot[$r][$c] = random_int(0, 3);
        $d = ['typ' => 'obwod', 'n' => $n, 'src' => $src, 'maska' => $maska];
    } while (uni_ob_rozwiazany($d, $rot));
    // Przeglądarka dostaje kafle już obrócone — rozwiązanie nie wynika wprost z danych.
    $pokaz = [];
    for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) $pokaz[$r][$c] = uni_ob_obrot($maska[$r][$c], $rot[$r][$c]);
    return ['typ' => 'obwod', 'n' => $n, 'src' => $src, 'maska' => $pokaz];
}

function uni_obwod_sprawdz(array $dane, $odp): bool {
    if (!is_array($odp) || count($odp) !== $dane['n']) return false;
    $rot = [];
    foreach ($odp as $r => $w) { if (!is_array($w) || count($w) !== $dane['n']) return false; foreach ($w as $c => $v) $rot[(int)$r][(int)$c] = (int)$v; }
    return uni_ob_rozwiazany($dane, $rot);
}

/* ── WSPÓLNE ──────────────────────────────────────────────────────── */
function uni_lam_losuj(int $stopien): array {
    $typ = array_rand(UNI_LAM_TYPY);
    return $typ === 'sudoku' ? uni_sudoku_generuj($stopien) : uni_obwod_generuj($stopien);
}

function uni_lam_sprawdz(array $dane, $odp): bool {
    return $dane['typ'] === 'sudoku' ? uni_sudoku_sprawdz($dane, $odp) : uni_obwod_sprawdz($dane, $odp);
}

/** Dane dla przeglądarki. Odpowiedź obwodu to liczba obrotów każdego kafla. */
function uni_lam_publiczne(array $d): array {
    return $d['typ'] === 'sudoku' ? ['typ' => 'sudoku', 'plansza' => $d['plansza']]
                                  : ['typ' => 'obwod', 'n' => $d['n'], 'src' => $d['src'], 'maska' => $d['maska']];
}
