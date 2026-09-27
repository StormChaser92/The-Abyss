<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/UNI_LAMIGLOWKI.PHP
   Łamigłówki zajęć. Serwer generuje planszę, trzyma ją w sesji i sprawdza
   odpowiedź — przeglądarka tylko rysuje. Trudność: 1 Licencjat, 2 Magister, 3 Doktor.
   ═══════════════════════════════════════════════════════════════════════ */

const UNI_LAM_TYPY = ['sudoku' => 'Sudoku 6×6', 'obwod' => 'Łączenie obwodu', 'szyfr' => 'Kryptogram', 'memory' => 'Memory: pary pojęć',
    'pietnastka' => 'Układanka „15”', 'nonogram' => 'Nonogram', 'klamca' => 'Kto kłamie?', 'warcaby' => 'Warcaby: wieloskok'];

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

/* ── KRYPTOGRAM — szyfr podstawieniowy, część liter odsłonięta ───────── */
const UNI_SZ_ZDANIA = [
    1 => ['WIEDZA TO WALUTA TEGO MIASTA', 'KAZDY SEKRET MA SWOJA CENE', 'NOC NA BROADWAYU NIGDY NIE SPI', 'PROBOWKA NIE KLAMIE', 'ZLOTO LEZY W DOKACH'],
    2 => ['KTO PYTA TEN NIE BLADZI ALE KTO SZUKA TEN ZNAJDUJE', 'DZIEKAN CZYTA KAZDA ODPOWIEDZ DWA RAZY', 'POD CANARSIE DZIALA DRUGA STACJA', 'KAZDY ZAMEK MA SWOJ KLUCZ I SWOJA CENE'],
    3 => ['W TYM MIESCIE PRAWDA KOSZTUJE WIECEJ NIZ KLAMSTWO A MILCZENIE NAJWIECEJ', 'NAJLEPSZY SZYFR TO TAKI KTOREGO NIKT NIE SZUKA', 'LABORATORIUM PAMIETA KAZDY BLAD KTORY W NIM POPELNIONO']];
function uni_szyfr_generuj(int $stopien): array {
    $pula = UNI_SZ_ZDANIA[$stopien] ?? UNI_SZ_ZDANIA[1];
    $txt = $pula[random_int(0, count($pula) - 1)];
    $abc = range('A', 'Z'); $mapa = [];
    do { $sh = uni_lam_tasuj($abc); $ok = true; foreach ($abc as $i => $l) if ($sh[$i] === $l) { $ok = false; break; } } while (!$ok);
    foreach ($abc as $i => $l) $mapa[$l] = $sh[$i];
    $lit = array_values(array_unique(array_filter(str_split($txt), fn($c) => ctype_alpha($c))));
    $ile = (int)round(count($lit) * ([1 => .45, 2 => .3, 3 => .15][$stopien] ?? .45));
    $odsl = []; foreach (array_slice(uni_lam_tasuj($lit), 0, $ile) as $l) $odsl[$mapa[$l]] = $l;
    $szyfr = implode('', array_map(fn($c) => ctype_alpha($c) ? $mapa[$c] : $c, str_split($txt)));
    return ['typ' => 'szyfr', 'szyfr' => $szyfr, 'odsl' => $odsl, 'tekst' => $txt];
}
function uni_szyfr_sprawdz(array $d, $odp): bool { return is_string($odp) && preg_replace('/\s+/', ' ', strtoupper(trim($odp))) === $d['tekst']; }

/* ── MEMORY — karty odkrywa serwer (api/uni_memory.php), przeglądarka nie zna treści ── */
const UNI_MEM_PARY = [['H₂O', 'Woda'], ['NaCl', 'Sól kuchenna'], ['Habeas corpus', 'Ochrona przed bezprawnym aresztem'], ['Hipokrates', 'Ojciec medycyny'],
    ['Brooklyn Bridge', '1883'], ['Wall Street', 'Giełda'], ['Pi', '3,14'], ['Adrenalina', 'Hormon stresu'], ['Algorytm', 'Przepis krok po kroku'],
    ['Alibi', 'Dowód nieobecności'], ['Katalizator', 'Przyspiesza reakcję'], ['Aorta', 'Największa tętnica'], ['Ellis Island', 'Brama imigrantów'],
    ['Inflacja', 'Spadek wartości pieniądza'], ['Placebo', 'Pozorny lek'], ['Tlen', 'O'], ['Cezar', 'Rubikon'], ['Newton', 'Grawitacja'],
    ['Kofeina', 'Pobudza'], ['Szekspir', 'Hamlet'], ['Bach', 'Barok'], ['Freud', 'Psychoanaliza'], ['Darwin', 'Ewolucja'], ['Sonet', '14 wersów']];
function uni_memory_generuj(int $stopien): array {
    $n = [1 => 6, 2 => 8, 3 => 10][$stopien] ?? 6;
    $karty = [];
    foreach (array_slice(uni_lam_tasuj(UNI_MEM_PARY), 0, $n) as $i => [$a, $b]) { $karty[] = [$i, $a]; $karty[] = [$i, $b]; }
    return ['typ' => 'memory', 'karty' => uni_lam_tasuj($karty), 'znalezione' => [], 'otwarta' => null, 'ruchy' => 0];
}
function uni_memory_sprawdz(array $d, $odp): bool { return count($d['znalezione']) * 2 === count($d['karty']); }

/* ── UKŁADANKA „15” — tasowanie losowymi ruchami od stanu ułożonego (zawsze rozwiązywalna) ── */
function uni_15_ruch(array $p, int $n, int $kafel): ?array {
    $z = array_search(0, $p, true); $k = array_search($kafel, $p, true);
    if ($k === false || $kafel === 0) return null;
    if (abs(intdiv($z, $n) - intdiv($k, $n)) + abs($z % $n - $k % $n) !== 1) return null;
    $p[$z] = $kafel; $p[$k] = 0; return $p;
}
function uni_pietnastka_generuj(int $stopien): array {
    [$n, $ile] = [1 => [3, 40], 2 => [4, 80], 3 => [4, 160]][$stopien] ?? [3, 40];
    $p = array_merge(range(1, $n * $n - 1), [0]); $ost = -1;
    for ($i = 0; $i < $ile; $i++) {
        $z = array_search(0, $p, true); $opc = [];
        foreach ([-$n, $n, -1, 1] as $dd) { $t = $z + $dd; if ($t < 0 || $t >= $n * $n || (abs($dd) === 1 && intdiv($t, $n) !== intdiv($z, $n)) || $p[$t] === $ost) continue; $opc[] = $t; }
        $t = $opc[random_int(0, count($opc) - 1)]; $ost = $p[$t]; $p = uni_15_ruch($p, $n, $p[$t]);
    }
    if ($p === array_merge(range(1, $n * $n - 1), [0])) return uni_pietnastka_generuj($stopien);
    return ['typ' => 'pietnastka', 'n' => $n, 'plansza' => $p];
}
function uni_pietnastka_sprawdz(array $d, $odp): bool {
    if (!is_array($odp) || count($odp) > 5000) return false;
    $p = $d['plansza'];
    foreach ($odp as $k) { $p = uni_15_ruch($p, $d['n'], (int)$k); if ($p === null) return false; }
    return $p === array_merge(range(1, $d['n'] ** 2 - 1), [0]);
}

/* ── NONOGRAM — losowy obraz, podpowiedzi wierszy i kolumn; liczy się każde rozwiązanie zgodne z podpowiedziami ── */
function uni_nono_podp(array $linia): array { $o = []; $c = 0; foreach ($linia as $v) { if ($v) $c++; elseif ($c) { $o[] = $c; $c = 0; } } if ($c) $o[] = $c; return $o ?: [0]; }
function uni_nono_podpowiedzi(array $g): array {
    $n = count($g); $w = []; $k = [];
    for ($i = 0; $i < $n; $i++) { $w[] = uni_nono_podp($g[$i]); $k[] = uni_nono_podp(array_column($g, $i)); }
    return [$w, $k];
}
function uni_nonogram_generuj(int $stopien): array {
    $n = [1 => 5, 2 => 6, 3 => 8][$stopien] ?? 5;
    do { $g = []; $s = 0; for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) { $g[$r][$c] = random_int(1, 100) <= 58 ? 1 : 0; $s += $g[$r][$c]; } } while ($s < $n * $n * .4);
    [$w, $k] = uni_nono_podpowiedzi($g);
    return ['typ' => 'nonogram', 'n' => $n, 'w' => $w, 'k' => $k];
}
function uni_nonogram_sprawdz(array $d, $odp): bool {
    if (!is_array($odp) || count($odp) !== $d['n']) return false;
    $g = []; foreach ($odp as $r => $w) { if (!is_array($w) || count($w) !== $d['n']) return false; foreach ($w as $v) $g[(int)$r][] = $v ? 1 : 0; }
    return uni_nono_podpowiedzi($g) === [$d['w'], $d['k']];
}

/* ── KTO KŁAMIE — dokładnie jeden kłamca; zagadka generowana aż rozwiązanie jest jednoznaczne ── */
const UNI_KL_IMIONA = ['Asystent Vance', 'Doktorantka Moreau', 'Laborant Okafor', 'Stażystka Lin', 'Portier Kowalczyk', 'Profesor Hale'];
function uni_kl_prawda(array $st, int $klamca): bool {
    foreach ($st as $i => [$o, $czy_mowi_prawde]) { $zdanie = ($o !== $klamca) === $czy_mowi_prawde; if ($zdanie !== ($i !== $klamca)) return false; }
    return true;
}
function uni_klamca_generuj(int $stopien): array {
    $n = [1 => 3, 2 => 4, 3 => 5][$stopien] ?? 3;
    do {
        $L = random_int(0, $n - 1); $st = [];
        for ($i = 0; $i < $n; $i++) { do { $o = random_int(0, $n - 1); } while ($o === $i); $prawda_o = $o !== $L; $st[$i] = [$o, $i === $L ? !$prawda_o : $prawda_o]; }
        $roz = 0; for ($x = 0; $x < $n; $x++) if (uni_kl_prawda($st, $x)) $roz++;
    } while ($roz !== 1);
    $im = array_slice(uni_lam_tasuj(UNI_KL_IMIONA), 0, $n);
    $zd = []; foreach ($st as $i => [$o, $p]) $zd[] = [$im[$i], $im[$o] . ($p ? ' mówi prawdę.' : ' kłamie.')];
    return ['typ' => 'klamca', 'zdania' => $zd, 'klamca' => $L, 'n' => $n];
}
function uni_klamca_sprawdz(array $d, $odp): bool { return is_int($odp) || ctype_digit((string)$odp) ? (int)$odp === $d['klamca'] : false; }

/* ── WARCABY: WIELOSKOK — biały pionek musi zbić wszystkie czarne w jednym ruchu ── */
function uni_warcaby_generuj(int $stopien): array {
    $sk = [1 => 2, 2 => 3, 3 => 4][$stopien] ?? 2;
    for ($prob = 0; $prob < 500; $prob++) {
        $r = random_int(0, 7); $c = random_int(0, 7); if (($r + $c) % 2 === 0) continue;
        $start = [$r, $c]; $zajete = ["$r,$c" => 1]; $czarne = []; $ok = true;
        for ($i = 0; $i < $sk && $ok; $i++) {
            $opc = [];
            foreach ([[-1, -1], [-1, 1], [1, -1], [1, 1]] as [$dr, $dc]) {
                $mr = $r + $dr; $mc = $c + $dc; $lr = $r + 2 * $dr; $lc = $c + 2 * $dc;
                if ($lr < 0 || $lc < 0 || $lr > 7 || $lc > 7 || isset($zajete["$mr,$mc"]) || isset($zajete["$lr,$lc"])) continue;
                $opc[] = [$mr, $mc, $lr, $lc];
            }
            if (!$opc) { $ok = false; break; }
            [$mr, $mc, $r, $c] = $opc[random_int(0, count($opc) - 1)];
            $czarne[] = [$mr, $mc]; $zajete["$mr,$mc"] = 1; $zajete["$r,$c"] = 1;
        }
        if ($ok) return ['typ' => 'warcaby', 'bialy' => $start, 'czarne' => $czarne];
    }
    return uni_warcaby_generuj(1);
}
function uni_warcaby_sprawdz(array $d, $odp): bool {
    if (!is_array($odp) || count($odp) !== count($d['czarne'])) return false;
    [$r, $c] = $d['bialy']; $cz = []; foreach ($d['czarne'] as [$a, $b]) $cz["$a,$b"] = 1;
    foreach ($odp as $pole) {
        if (!is_array($pole) || count($pole) !== 2) return false;
        [$lr, $lc] = [(int)$pole[0], (int)$pole[1]];
        if ($lr < 0 || $lc < 0 || $lr > 7 || $lc > 7 || abs($lr - $r) !== 2 || abs($lc - $c) !== 2) return false;
        $m = (($r + $lr) / 2) . ',' . (($c + $lc) / 2);
        if (!isset($cz[$m]) || isset($cz["$lr,$lc"])) return false;
        unset($cz[$m]); $r = $lr; $c = $lc;
    }
    return !$cz;
}

/* ── WSPÓLNE ──────────────────────────────────────────────────────── */
function uni_lam_losuj(int $stopien): array {
    $typ = array_rand(UNI_LAM_TYPY);
    return ('uni_' . $typ . '_generuj')($stopien);
}

function uni_lam_sprawdz(array $dane, $odp): bool {
    $f = 'uni_' . $dane['typ'] . '_sprawdz';
    return function_exists($f) && $f($dane, $odp);
}

/** Dane dla przeglądarki — bez rozwiązań (kryptogram bez tekstu, memory bez treści kart, kto kłamie bez kłamcy). */
function uni_lam_publiczne(array $d): array {
    switch ($d['typ']) {
        case 'sudoku':     return ['typ' => 'sudoku', 'plansza' => $d['plansza']];
        case 'obwod':      return ['typ' => 'obwod', 'n' => $d['n'], 'src' => $d['src'], 'maska' => $d['maska']];
        case 'szyfr':      return ['typ' => 'szyfr', 'szyfr' => $d['szyfr'], 'odsl' => $d['odsl']];
        case 'memory':     return ['typ' => 'memory', 'ile' => count($d['karty'])];
        case 'pietnastka': return ['typ' => 'pietnastka', 'n' => $d['n'], 'plansza' => $d['plansza']];
        case 'nonogram':   return ['typ' => 'nonogram', 'n' => $d['n'], 'w' => $d['w'], 'k' => $d['k']];
        case 'klamca':     return ['typ' => 'klamca', 'zdania' => $d['zdania']];
        case 'warcaby':    return ['typ' => 'warcaby', 'bialy' => $d['bialy'], 'czarne' => $d['czarne']];
    }
    return ['typ' => $d['typ']];
}
