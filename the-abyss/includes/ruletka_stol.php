<?php
/* the-abyss/includes/ruletka_stol.php
   Definicje stołu ruletki europejskiej — jedno źródło prawdy dla serwera
   i klienta. API wystawia to przez ?a=tabela, więc przeglądarka nie ma
   własnej kopii zasad.

   Układ liczb na stole: 12 kolumn × 3 rzędy.
     rząd 0 (góra):  3, 6, 9 ... 36
     rząd 1 (środek): 2, 5, 8 ... 35
     rząd 2 (dół):    1, 4, 7 ... 34
   czyli numer(kolumna, rząd) = 3·kolumna + (3 − rząd)

   Każdy zakład ma dokładnie taką liczbę numerów, jaka odpowiada wypłacie,
   więc RTP wynosi 36/37 = 97,297% niezależnie od tego, co gracz obstawi.
   Geometria (splity, narożniki, szóstki) została sprawdzona liczbowo. */
declare(strict_types=1);

const RL_CZERWONE = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];

/* Kolejność liczb na prawdziwym kole europejskim — od zera zgodnie z ruchem
   wskazówek zegara. Klient rysuje po tym kole, więc animacja zatrzymuje się
   w istniejącej kieszeni, a nie w przypadkowym miejscu. */
const RL_KOLO = [0,32,15,19,4,21,2,25,17,34,6,27,13,36,11,30,8,23,10,
                 5,24,16,33,1,20,14,31,9,22,18,29,7,28,12,35,3,26];

/* Limity stołu. Zróżnicowane, jak w prawdziwym kasynie: na pojedynczy numer
   można postawić mniej niż na czerwone, bo wypłata jest 35 razy większa. */
const RL_MIN_ZAKLAD   = 25;
const RL_MAX_NUMER    = 2500;    // wypłaty 35:1, 17:1, 11:1, 8:1
const RL_MAX_ZEWN     = 25000;   // wypłaty 5:1, 2:1, 1:1
const RL_MAX_SUMA     = 100000;  // łącznie na jeden spin
const RL_NOMINALY     = [25, 100, 500, 2500, 10000];

function rl_numer(int $kolumna, int $rzad): int { return 3 * $kolumna + (3 - $rzad); }

/** Wszystkie dopuszczalne zakłady: klucz => ['numery'=>[...], 'wyplata'=>n]. */
function rl_zaklady(): array {
  static $z = null;
  if ($z !== null) return $z;
  $z = [];
  $dodaj = function(string $k, array $numery, int $wyplata) use (&$z) {
    sort($numery);
    $z[$k] = ['numery'=>$numery, 'wyplata'=>$wyplata];
  };

  // pojedyncze numery
  $dodaj('n0', [0], 35);
  for ($c = 0; $c < 12; $c++) for ($r = 0; $r < 3; $r++) {
    $n = rl_numer($c, $r);
    $dodaj('n'.$n, [$n], 35);
  }

  // splity — sąsiedzi w poziomie i w pionie
  for ($c = 0; $c < 11; $c++) for ($r = 0; $r < 3; $r++) {
    $a = rl_numer($c, $r); $b = rl_numer($c + 1, $r);
    $dodaj('s'.min($a,$b).'-'.max($a,$b), [$a,$b], 17);
  }
  for ($c = 0; $c < 12; $c++) for ($r = 0; $r < 2; $r++) {
    $a = rl_numer($c, $r); $b = rl_numer($c, $r + 1);
    $dodaj('s'.min($a,$b).'-'.max($a,$b), [$a,$b], 17);
  }
  foreach ([1,2,3] as $x) $dodaj('s0-'.$x, [0,$x], 17);

  // ulice i trio z zerem
  for ($c = 0; $c < 12; $c++) $dodaj('t'.(3*$c+1), [3*$c+1, 3*$c+2, 3*$c+3], 11);
  $dodaj('tr0-1-2', [0,1,2], 11);
  $dodaj('tr0-2-3', [0,2,3], 11);

  // narożniki
  for ($c = 0; $c < 11; $c++) for ($r = 0; $r < 2; $r++) {
    $ns = [rl_numer($c,$r), rl_numer($c+1,$r), rl_numer($c,$r+1), rl_numer($c+1,$r+1)];
    $dodaj('c'.min($ns), $ns, 8);
  }

  // szóstki
  for ($c = 0; $c < 11; $c++)
    $dodaj('l'.(3*$c+1), [3*$c+1,3*$c+2,3*$c+3,3*$c+4,3*$c+5,3*$c+6], 5);

  // tuziny i kolumny
  for ($d = 0; $d < 3; $d++)
    $dodaj('d'.($d+1), range($d*12+1, $d*12+12), 2);
  for ($k = 0; $k < 3; $k++) {
    $ns = [];
    for ($i = 0; $i < 12; $i++) $ns[] = 3*$i + $k + 1;
    $dodaj('col'.($k+1), $ns, 2);
  }

  // proste szanse
  $wszystkie = range(1, 36);
  $dodaj('red',   RL_CZERWONE, 1);
  $dodaj('black', array_values(array_diff($wszystkie, RL_CZERWONE)), 1);
  $dodaj('odd',   array_values(array_filter($wszystkie, fn($x) => $x % 2 === 1)), 1);
  $dodaj('even',  array_values(array_filter($wszystkie, fn($x) => $x % 2 === 0)), 1);
  $dodaj('low',   range(1, 18), 1);
  $dodaj('high',  range(19, 36), 1);

  return $z;
}

/** Maksimum na jedno pole — zależne od klasy wypłaty. */
function rl_limit(int $wyplata): int { return $wyplata >= 8 ? RL_MAX_NUMER : RL_MAX_ZEWN; }

function rl_kolor(int $n): string {
  if ($n === 0) return 'zero';
  return in_array($n, RL_CZERWONE, true) ? 'red' : 'black';
}

/** Etykieta zakładu do logu i czatu — „17", „splity 17/18", „czerwone". */
function rl_etykieta(string $klucz): string {
  $z = rl_zaklady()[$klucz] ?? null;
  if (!$z) return $klucz;
  $n = $z['numery'];
  return match (true) {
    $klucz === 'red'   => 'czerwone',
    $klucz === 'black' => 'czarne',
    $klucz === 'odd'   => 'nieparzyste',
    $klucz === 'even'  => 'parzyste',
    $klucz === 'low'   => '1–18',
    $klucz === 'high'  => '19–36',
    str_starts_with($klucz, 'd')   => $n[0].'–'.end($n).' (tuzin)',
    str_starts_with($klucz, 'col') => 'kolumna '.substr($klucz, 3),
    count($n) === 1 => (string)$n[0],
    default => implode('/', $n),
  };
}
