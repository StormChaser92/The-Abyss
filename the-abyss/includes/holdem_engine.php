<?php
/* the-abyss/includes/holdem_engine.php
   Czysta matematyka pokera: talia, ocena układów z siedmiu kart, podział
   puli z pobocznymi, rake. Bez bazy i bez sesji — da się to testować osobno.

   Zapis karty: figura + kolor, np. 'As', 'Kh', 'Td', '9c'.
   Figury: 2 3 4 5 6 7 8 9 T J Q K A      Kolory: s ♠  h ♥  d ♦  c ♣ */
declare(strict_types=1);

const HE_FIGURY = ['2','3','4','5','6','7','8','9','T','J','Q','K','A'];
const HE_KOLORY = ['s','h','d','c'];
const HE_NAZWY = [
  8 => 'Poker',        7 => 'Kareta',   6 => 'Full',      5 => 'Kolor',
  4 => 'Strit',        3 => 'Trójka',   2 => 'Dwie pary',  1 => 'Para',
  0 => 'Wysoka karta',
];

function he_talia(): array {
  $t = [];
  foreach (HE_KOLORY as $k) foreach (HE_FIGURY as $f) $t[] = $f.$k;
  return $t;
}

/** Tasowanie na kryptograficznym źródle — nie rand(), nie mt_rand(). */
function he_potasuj(array $t): array {
  for ($i = count($t) - 1; $i > 0; $i--) {
    $j = random_int(0, $i);
    [$t[$i], $t[$j]] = [$t[$j], $t[$i]];
  }
  return $t;
}

function he_ranga(string $karta): int { return (int)array_search($karta[0], HE_FIGURY, true) + 2; }
function he_kolor(string $karta): string { return $karta[1]; }

/** Ładny zapis do interfejsu: ['f'=>'A','s'=>'♠','czerwona'=>false] */
function he_karta_html(string $karta): array {
  $mapa = ['s'=>'♠','h'=>'♥','d'=>'♦','c'=>'♣'];
  $f = $karta[0] === 'T' ? '10' : $karta[0];
  return ['f'=>$f, 's'=>$mapa[$karta[1]], 'czerwona'=>in_array($karta[1],['h','d'],true)];
}

/* ------------------------------------------------------------------
   Ocena pięciu kart. Zwraca liczbę — im większa, tym lepszy układ.
   Kategoria w najwyższych cyfrach, potem kickery malejąco.
   ------------------------------------------------------------------ */
function he_ocena5(array $karty): array {
  $rangi = array_map('he_ranga', $karty);
  $kolory = array_map('he_kolor', $karty);
  rsort($rangi);

  $ile = array_count_values($rangi);
  // sortuj figury: najpierw po liczbie wystąpień, potem po randze
  $grupy = [];
  foreach ($ile as $r => $n) $grupy[] = [$n, (int)$r];
  usort($grupy, fn($a,$b) => $b[0] <=> $a[0] ?: $b[1] <=> $a[1]);

  $kolor = count(array_unique($kolory)) === 1;
  $unikaty = array_values(array_unique($rangi));   // malejąco
  $strit = false; $topStrita = 0;
  if (count($unikaty) === 5) {
    if ($unikaty[0] - $unikaty[4] === 4) { $strit = true; $topStrita = $unikaty[0]; }
    elseif ($unikaty === [14,5,4,3,2])   { $strit = true; $topStrita = 5; }  // koło A-2-3-4-5
  }

  if ($kolor && $strit)                        return he_pak(8, [$topStrita]);
  if ($grupy[0][0] === 4)                      return he_pak(7, [$grupy[0][1], $grupy[1][1]]);
  if ($grupy[0][0] === 3 && $grupy[1][0] === 2) return he_pak(6, [$grupy[0][1], $grupy[1][1]]);
  if ($kolor)                                  return he_pak(5, $unikaty);
  if ($strit)                                  return he_pak(4, [$topStrita]);
  if ($grupy[0][0] === 3)                      return he_pak(3, [$grupy[0][1], $grupy[1][1], $grupy[2][1]]);
  if ($grupy[0][0] === 2 && $grupy[1][0] === 2) return he_pak(2, [$grupy[0][1], $grupy[1][1], $grupy[2][1]]);
  if ($grupy[0][0] === 2)                      return he_pak(1, [$grupy[0][1], $grupy[1][1], $grupy[2][1], $grupy[3][1]]);
  return he_pak(0, $unikaty);
}

/** Pakuje kategorię i kickery w jedną liczbę porównywalną przez <=>. */
function he_pak(int $kat, array $kickery): array {
  $wynik = $kat;
  for ($i = 0; $i < 5; $i++) $wynik = $wynik * 16 + (int)($kickery[$i] ?? 0);
  return ['kat' => $kat, 'wynik' => $wynik, 'kickery' => $kickery];
}

/** Najlepszy układ z 5–7 kart: sprawdzamy wszystkie kombinacje po 5. */
function he_najlepszy(array $karty): array {
  $n = count($karty);
  if ($n < 5) return ['kat'=>-1,'wynik'=>0,'kickery'=>[],'karty'=>[],'nazwa'=>'—'];
  $best = null; $bestKarty = [];
  for ($a=0;$a<$n-4;$a++) for ($b=$a+1;$b<$n-3;$b++) for ($c=$b+1;$c<$n-2;$c++)
  for ($d=$c+1;$d<$n-1;$d++) for ($e=$d+1;$e<$n;$e++) {
    $piec = [$karty[$a],$karty[$b],$karty[$c],$karty[$d],$karty[$e]];
    $o = he_ocena5($piec);
    if ($best === null || $o['wynik'] > $best['wynik']) { $best = $o; $bestKarty = $piec; }
  }
  $best['karty'] = $bestKarty;
  $best['nazwa'] = he_nazwa_ukladu($best);
  return $best;
}

function he_nazwa_ukladu(array $o): string {
  $baza = HE_NAZWY[$o['kat']] ?? '—';
  if ($o['kat'] === 8 && ($o['kickery'][0] ?? 0) === 14) return 'Poker królewski';
  if ($o['kat'] === 1 || $o['kat'] === 3 || $o['kat'] === 7) {
    $f = HE_FIGURY[($o['kickery'][0] ?? 2) - 2] ?? '';
    return $baza.' ('.($f === 'T' ? '10' : $f).')';
  }
  return $baza;
}

/* ------------------------------------------------------------------
   Podział puli z pulami pobocznymi.

   $wplaty  = [miejsce => ile łącznie wpłacił w tym rozdaniu]
   $walczy  = [miejsce, ...]  — kto doszedł do showdownu (nie spasował)
   $sily    = [miejsce => wynik z he_najlepszy()]

   Zwraca [miejsce => ile wygrywa]. Obsługuje all-iny o różnej wysokości
   i dzieli remisy — reszta z dzielenia idzie do pierwszego z lewej.
   ------------------------------------------------------------------ */
function he_podziel_pule(array $wplaty, array $walczy, array $sily): array {
  $wyplaty = array_fill_keys(array_keys($wplaty), 0);
  $poziomy = array_values(array_unique(array_filter($wplaty, fn($v) => $v > 0)));
  sort($poziomy);
  $poprzedni = 0;

  foreach ($poziomy as $poziom) {
    $warstwa = $poziom - $poprzedni;
    if ($warstwa <= 0) { $poprzedni = $poziom; continue; }

    // do tej warstwy dokłada każdy, kto wpłacił co najmniej tyle
    $pula = 0;
    foreach ($wplaty as $m => $w) $pula += min($warstwa, max(0, $w - $poprzedni));

    // o warstwę walczą ci, którzy nie spasowali i wpłacili do niej pełną kwotę
    $kandydaci = array_values(array_filter($walczy, fn($m) => ($wplaty[$m] ?? 0) >= $poziom));
    if (!$kandydaci) { $poprzedni = $poziom; continue; }

    $naj = 0;
    foreach ($kandydaci as $m) $naj = max($naj, $sily[$m]['wynik'] ?? 0);
    $zwyciezcy = array_values(array_filter($kandydaci, fn($m) => ($sily[$m]['wynik'] ?? 0) === $naj));

    $udzial = intdiv($pula, count($zwyciezcy));
    $reszta = $pula - $udzial * count($zwyciezcy);
    sort($zwyciezcy);
    foreach ($zwyciezcy as $i => $m) $wyplaty[$m] += $udzial + ($i === 0 ? $reszta : 0);

    $poprzedni = $poziom;
  }
  return $wyplaty;
}

/* ------------------------------------------------------------------
   Rake: 5% puli, maksimum trzy duże blindy. Nie pobieramy z rozdania
   rozstrzygniętego przed flopem bez walki (zasada „no flop, no drop").
   ------------------------------------------------------------------ */
function he_rake(int $pula, int $blindDuzy, bool $bylFlop): int {
  if (!$bylFlop) return 0;
  return (int)min(floor($pula * 0.05), $blindDuzy * 3);
}
