<?php
/* the-abyss/includes/blackjack_stol.php
   Zasady stołu blackjacka. Bez SQL i bez echa — czysta arytmetyka kart,
   żeby dało się to sprawdzić w izolacji.

   Karta = dwa znaki: ranga (A23456789TJQK) + kolor (s h d c).
   But = tablica kart; ostatni element to wierzch (array_pop). */
declare(strict_types=1);

const BJ_TALIE       = 6;
const BJ_MIN         = 100;
const BJ_MAX         = 25000;
const BJ_PENETRACJA  = 0.75;   // po zejściu tylu kart tasujemy przed nową rundą
const BJ_MAX_RAK     = 4;      // split do czterech rąk
const BJ_KART        = BJ_TALIE * 52;

/** Potasowany but. Fisher-Yates na random_int — nie shuffle(). */
function bj_nowy_but(): array {
  $but = [];
  foreach (['s','h','d','c'] as $kolor)
    foreach (['A','2','3','4','5','6','7','8','9','T','J','Q','K'] as $ranga)
      for ($i = 0; $i < BJ_TALIE; $i++) $but[] = $ranga.$kolor;
  for ($i = count($but) - 1; $i > 0; $i--) {
    $j = random_int(0, $i);
    [$but[$i], $but[$j]] = [$but[$j], $but[$i]];
  }
  return $but;
}

/** Punkty ręki. Asy liczone po 11, redukowane do 1 dopóki jest fura. */
function bj_wartosc(array $karty): array {
  $suma = 0; $asy = 0;
  foreach ($karty as $k) {
    $r = $k[0];
    if ($r === 'A')                            { $asy++; $suma += 11; }
    elseif ($r === 'T' || $r === 'J' || $r === 'Q' || $r === 'K') $suma += 10;
    else                                        $suma += (int)$r;
  }
  while ($suma > 21 && $asy > 0) { $suma -= 10; $asy--; }
  return ['suma' => $suma, 'miekka' => $asy > 0];
}

/** Wartość rangi do porównań przy splicie (figury równe sobie). */
function bj_ranga_pkt(string $karta): int {
  $r = $karta[0];
  if ($r === 'A') return 11;
  if ($r === 'T' || $r === 'J' || $r === 'Q' || $r === 'K') return 10;
  return (int)$r;
}

/** Naturalny blackjack: dwie karty, 21, ręka nie pochodzi ze splitu. */
function bj_naturalny(array $reka): bool {
  return count($reka['karty']) === 2 && empty($reka['ze_splitu'])
      && bj_wartosc($reka['karty'])['suma'] === 21;
}

function bj_pusta_reka(int $stawka, bool $zeSplitu = false): array {
  return ['karty'=>[], 'stawka'=>$stawka, 'stan'=>'gra',
          'podwojona'=>false, 'ze_splitu'=>$zeSplitu,
          'wynik'=>null, 'wyplata'=>0];
}

/** Co gracz może zrobić aktywną ręką. Klient tylko rysuje te przyciski —
    API i tak sprawdza wszystko drugi raz. */
function bj_mozliwosci(array $st, int $zetony): array {
  $i = $st['aktywna'];
  if ($st['stan'] !== 'gra' || !isset($st['rece'][$i])) return [];
  $r = $st['rece'][$i];
  if ($r['stan'] !== 'gra') return [];
  $ile   = count($r['karty']);
  $asy   = $r['ze_splitu'] && $r['karty'][0][0] === 'A';
  if ($asy) return [];                              // dzielone asy: jedna karta i koniec
  $m = ['dobierz','stoj'];
  if ($ile === 2 && $zetony >= $r['stawka']) $m[] = 'podwoj';
  if ($ile === 2 && count($st['rece']) < BJ_MAX_RAK && $zetony >= $r['stawka']
      && bj_ranga_pkt($r['karty'][0]) === bj_ranga_pkt($r['karty'][1])) $m[] = 'split';
  return $m;
}

/** Krupier: dobiera do 16, dobiera na miękkie 17, staje na twardym 17. */
function bj_gra_krupier(array $karty, array &$but): array {
  while (true) {
    $w = bj_wartosc($karty);
    if ($w['suma'] > 17) break;
    if ($w['suma'] === 17 && !$w['miekka']) break;
    $karty[] = array_pop($but);
  }
  return $karty;
}

/** Rozliczenie rundy. Zwraca sumę do wypłaty w żetonach i opisy rąk. */
function bj_rozlicz(array &$st): int {
  $dw   = bj_wartosc($st['dealer']);
  $dbj  = count($st['dealer']) === 2 && $dw['suma'] === 21;
  $suma = 0;

  foreach ($st['rece'] as &$r) {
    $w = bj_wartosc($r['karty']);
    if ($r['stan'] === 'fura') { $r['wynik'] = 'fura'; $r['wyplata'] = 0; continue; }

    if (bj_naturalny($r)) {
      if ($dbj) { $r['wynik'] = 'remis';     $r['wyplata'] = $r['stawka']; }
      else      { $r['wynik'] = 'blackjack'; $r['wyplata'] = $r['stawka'] + intdiv($r['stawka'] * 3, 2); }
    } elseif ($dbj) {
      $r['wynik'] = 'przegrana'; $r['wyplata'] = 0;
    } elseif ($dw['suma'] > 21 || $w['suma'] > $dw['suma']) {
      $r['wynik'] = 'wygrana';   $r['wyplata'] = $r['stawka'] * 2;
    } elseif ($w['suma'] === $dw['suma']) {
      $r['wynik'] = 'remis';     $r['wyplata'] = $r['stawka'];
    } else {
      $r['wynik'] = 'przegrana'; $r['wyplata'] = 0;
    }
    $suma += $r['wyplata'];
  }
  unset($r);

  if ($st['ubezpieczenie'] > 0 && $dbj) $suma += $st['ubezpieczenie'] * 3;
  $st['dealer_bj'] = $dbj;
  return $suma;
}

/* ── Podpowiedź strategii podstawowej (6 talii, H17, DAS, bez poddania) ──
   Litery: H dobierz, S stój, D podwój (inaczej dobierz), Ds podwój
   (inaczej stój), P dziel. Tablice ułożone po karcie krupiera 2..A. */
function bj_podpowiedz(array $reka, string $upcard, array $mozliwosci): string {
  $d = bj_ranga_pkt($upcard); $d = $d === 11 ? 11 : $d;
  $i = $d - 2;                                       // 0..9, gdzie 9 = as
  $k = $reka['karty'];
  $w = bj_wartosc($k);
  $pary = [
    11 => 'PPPPPPPPPP', 10 => 'SSSSSSSSSS', 9 => 'PPPPPSPPSS', 8 => 'PPPPPPPPPP',
    7  => 'PPPPPPHHHH', 6  => 'PPPPPPHHHH', 5 => 'DDDDDDDDHH', 4 => 'HHHPPHHHHH',
    3  => 'PPPPPPPHHH', 2  => 'PPPPPPPHHH',
  ];
  $miekkie = [
    20 => 'SSSSSSSSSS', 17 => 'HDDDDHHHHH',
    16 => 'HHDDDHHHHH', 15 => 'HHDDDHHHHH', 14 => 'HHHDDHHHHH', 13 => 'HHHDDHHHHH',
  ];
  $twarde = [
    11 => 'DDDDDDDDDD', 10 => 'DDDDDDDDHH', 9 => 'HDDDDHHHHH',
    8  => 'HHHHHHHHHH', 12 => 'HHSSSHHHHH',
  ];

  $lit = null;
  if (count($k) === 2 && bj_ranga_pkt($k[0]) === bj_ranga_pkt($k[1]) && in_array('split', $mozliwosci, true))
    $lit = $pary[bj_ranga_pkt($k[0])][$i] ?? null;

  if ($lit === null) {
    if ($w['miekka'] && $w['suma'] <= 20) {
      $s = $miekkie[$w['suma']] ?? null;
      // A,8 z „Ds" ma dwa znaki — obsłuż osobno
      // A,8 i A,7 grają „podwój, a jak nie wolno to stój"
      if ($w['suma'] === 19)     $lit = $i === 4 ? 'Ds' : 'S';
      elseif ($w['suma'] === 18) $lit = $i <= 4 ? 'Ds' : ($i <= 6 ? 'S' : 'H');
      elseif ($s !== null)       $lit = $s[$i];
      else                       $lit = 'S';
    } elseif ($w['suma'] >= 17) $lit = 'S';
    elseif ($w['suma'] >= 13)   $lit = $i <= 4 ? 'S' : 'H';
    elseif ($w['suma'] === 12)  $lit = $twarde[12][$i];
    else                        $lit = $twarde[$w['suma']][$i] ?? 'H';
  }

  $mozeD = in_array('podwoj', $mozliwosci, true);
  if ($lit === 'D')  $lit = $mozeD ? 'D' : 'H';
  if ($lit === 'Ds') $lit = $mozeD ? 'D' : 'S';

  return match ($lit) {
    'P' => 'dziel',
    'D' => 'podwój',
    'S' => 'stój',
    default => 'dobierz',
  };
}

/** Widok stanu dla klienta. Zakryta karta krupiera nie wychodzi na zewnątrz. */
function bj_widok(array $st, int $zetony): array {
  $koniec = $st['stan'] === 'koniec';
  $dealer = $st['dealer'] ?? [];
  $karty  = $koniec ? $dealer : array_slice($dealer, 0, 1);

  $rece = [];
  foreach (($st['rece'] ?? []) as $nr => $r) {
    $w = bj_wartosc($r['karty']);
    $rece[] = [
      'karty'   => $r['karty'],
      'suma'    => $w['suma'],
      'miekka'  => $w['miekka'],
      'stawka'  => $r['stawka'],
      'stan'    => $r['stan'],
      'wynik'   => $r['wynik'],
      'wyplata' => $r['wyplata'],
      'bj'      => bj_naturalny($r),
      'aktywna' => $st['stan'] === 'gra' && $nr === $st['aktywna'],
    ];
  }

  $moz = bj_mozliwosci($st, $zetony);
  $pod = null;
  if ($moz && isset($dealer[0]))
    $pod = bj_podpowiedz($st['rece'][$st['aktywna']], $dealer[0], $moz);

  return [
    'stan'          => $st['stan'],
    'stawka'        => $st['stawka'],
    'ubezpieczenie' => $st['ubezpieczenie'],
    'rece'          => $rece,
    'dealer'        => ['karty'=>$karty, 'zakryta'=>!$koniec && count($dealer) > 1,
                        'suma'=>bj_wartosc($karty)['suma'],
                        'pelna'=>$koniec ? bj_wartosc($dealer)['suma'] : null],
    'mozliwosci'    => $moz,
    'podpowiedz'    => $pod,
  ];
}
