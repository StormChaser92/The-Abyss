<?php
/* the-abyss/includes/holdem_bot.php
   Decyzje bota. Trzy osobowości, jawnie oznaczone przy stole:

     ostrozny      — wchodzi rzadko, ale gdy podbija, zwykle ma rękę
     szarpiacy     — naciska przy każdej okazji, blefuje regularnie
     nieobliczalny — duża losowość, żadnego czytelnego wzorca

   Bot nie widzi kart przeciwnika. Ocenia własną rękę, wysokość zakładu
   względem puli i ulicę — tak samo jak człowiek. */
declare(strict_types=1);
require_once __DIR__.'/holdem_engine.php';

/** Siła ręki przed flopem, 0..1. Prosty model: para, wysokość, kolor, łączność. */
function bot_sila_preflop(array $reka): float {
  $r1 = he_ranga($reka[0]); $r2 = he_ranga($reka[1]);
  $wysoka = max($r1, $r2); $niska = min($r1, $r2);
  $para = $r1 === $r2;
  $doKoloru = he_kolor($reka[0]) === he_kolor($reka[1]);
  $luka = $wysoka - $niska;

  if ($para) return min(1.0, 0.50 + ($wysoka - 2) * 0.038);   // 22 ≈ 0.50, AA ≈ 0.96
  $s = ($wysoka - 2) / 24 + ($niska - 2) / 40;                 // wysokie karty ważą więcej
  if ($doKoloru) $s += 0.09;
  if ($luka === 1) $s += 0.07;
  elseif ($luka === 2) $s += 0.03;
  elseif ($luka > 4) $s -= 0.06;
  return max(0.05, min(0.92, $s));
}

/** Siła po flopie, 0..1 — z kategorii najlepszego układu i wysokości kickera. */
function bot_sila_postflop(array $reka, array $board): float {
  $o = he_najlepszy(array_merge($reka, $board));
  $baza = [0=>0.14, 1=>0.34, 2=>0.55, 3=>0.70, 4=>0.80, 5=>0.86, 6=>0.93, 7=>0.97, 8=>1.0];
  $s = $baza[$o['kat']] ?? 0.2;

  // para na boardzie, której bot nie trzyma w ręce, jest warta mniej
  if ($o['kat'] === 1) {
    $rangiReki = array_map('he_ranga', $reka);
    if (!in_array($o['kickery'][0], $rangiReki, true)) $s -= 0.12;
    elseif ($o['kickery'][0] >= 11) $s += 0.06;
  }
  return max(0.05, min(1.0, $s));
}

/**
 * Decyzja bota.
 *
 * @param array $stan  reka, board, pula, do_sprawdzenia, zetony, blind_duzy,
 *                     ulica, moja_wplata_ulica
 * @return array ['akcja'=>'pas|czekam|sprawdzam|podbijam|allin', 'kwota'=>int, 'mowa'=>?string]
 */
function bot_decyzja(string $osobowosc, array $stan): array {
  $reka   = $stan['reka'];
  $board  = $stan['board'];
  $pula   = max(1, (int)$stan['pula']);
  $doCall = max(0, (int)$stan['do_sprawdzenia']);
  $stos   = (int)$stan['zetony'];
  $bb     = (int)$stan['blind_duzy'];

  $sila = $board ? bot_sila_postflop($reka, $board) : bot_sila_preflop($reka);

  // profil osobowości: próg wejścia, skłonność do podbicia, częstość blefu
  $p = match ($osobowosc) {
    'ostrozny'      => ['prog'=>0.62, 'podbij'=>0.78, 'blef'=>0.04, 'szum'=>0.05],
    'szarpiacy'     => ['prog'=>0.34, 'podbij'=>0.52, 'blef'=>0.30, 'szum'=>0.12],
    'nieobliczalny' => ['prog'=>0.45, 'podbij'=>0.60, 'blef'=>0.22, 'szum'=>0.30],
    default         => ['prog'=>0.50, 'podbij'=>0.65, 'blef'=>0.10, 'szum'=>0.10],
  };
  $sila = max(0.0, min(1.0, $sila + (random_int(-100,100) / 100) * $p['szum']));

  // ile kosztuje sprawdzenie względem puli — im drożej, tym wyższy wymagany próg
  $cena = $doCall / ($pula + $doCall);
  $prog = $p['prog'] + $cena * 0.35;
  $blefuje = random_int(1, 100) <= (int)round($p['blef'] * 100);

  // --- nikt nie podbił: czekamy albo otwieramy ---
  if ($doCall === 0) {
    if ($sila >= $p['podbij'] || $blefuje) {
      $kwota = bot_kwota_podbicia($pula, $bb, $stos, $sila, $osobowosc);
      if ($kwota >= $stos) return ['akcja'=>'allin','kwota'=>$stos,'mowa'=>bot_mowa($osobowosc,'allin')];
      if ($kwota > 0)      return ['akcja'=>'podbijam','kwota'=>$kwota,'mowa'=>bot_mowa($osobowosc,'podbicie')];
    }
    return ['akcja'=>'czekam','kwota'=>0,'mowa'=>null];
  }

  // --- ktoś podbił ---
  if ($doCall >= $stos) {                       // decyzja o własnym stosie
    $wymog = $osobowosc === 'nieobliczalny' ? 0.66 : 0.78;
    return $sila >= $wymog
      ? ['akcja'=>'allin','kwota'=>$stos,'mowa'=>bot_mowa($osobowosc,'allin')]
      : ['akcja'=>'pas','kwota'=>0,'mowa'=>bot_mowa($osobowosc,'pas')];
  }
  if ($sila < $prog && !$blefuje) return ['akcja'=>'pas','kwota'=>0,'mowa'=>bot_mowa($osobowosc,'pas')];

  if ($sila >= $p['podbij'] + 0.08 || ($blefuje && random_int(0,1))) {
    $kwota = bot_kwota_podbicia($pula, $bb, $stos, $sila, $osobowosc);
    if ($kwota > $doCall) {
      if ($kwota >= $stos) return ['akcja'=>'allin','kwota'=>$stos,'mowa'=>bot_mowa($osobowosc,'allin')];
      return ['akcja'=>'podbijam','kwota'=>$kwota,'mowa'=>bot_mowa($osobowosc,'podbicie')];
    }
  }
  return ['akcja'=>'sprawdzam','kwota'=>$doCall,'mowa'=>null];
}

/** Wysokość podbicia — ułamek puli, zaokrąglony do dużego blinda. */
function bot_kwota_podbicia(int $pula, int $bb, int $stos, float $sila, string $osobowosc): int {
  $ulamek = match ($osobowosc) {
    'ostrozny'      => 0.55 + $sila * 0.25,
    'szarpiacy'     => 0.70 + $sila * 0.55,
    'nieobliczalny' => (random_int(35, 140)) / 100,
    default         => 0.65,
  };
  $kwota = (int)round(($pula * $ulamek) / $bb) * $bb;
  $kwota = max($bb * 2, $kwota);
  return min($kwota, $stos);
}

/** Krótka kwestia do czatu — bot ma być obecny fabularnie, nie tylko liczbowo. */
function bot_mowa(string $osobowosc, string $co): ?string {
  if (random_int(1, 100) > 35) return null;      // milczy większość czasu
  $kwestie = [
    'ostrozny' => [
      'podbicie' => ['Podnoszę. Nie po to tu siedzę, żeby oglądać karty.', 'Skoro tak, to sprawdźmy, ile jesteś wart.'],
      'allin'    => ['Wszystko. Decyduj.'],
      'pas'      => ['Nie tym razem.', 'Zostawiam to tobie.'],
    ],
    'szarpiacy' => [
      'podbicie' => ['Za mało. Podbijam.', 'Robi się ciekawie, prawda?', 'Płać albo idź do domu.'],
      'allin'    => ['Cały stos. Zobaczymy, kto pierwszy mrugnie.'],
      'pas'      => ['Bierz. Następna jest moja.'],
    ],
    'nieobliczalny' => [
      'podbicie' => ['Czemu nie.', 'Coś mi mówi, że to dobry moment.', 'Podbijam. Bez powodu.'],
      'allin'    => ['A niech tam. Wszystko.'],
      'pas'      => ['Nuda.', 'Nie chcę.'],
    ],
  ];
  $lista = $kwestie[$osobowosc][$co] ?? null;
  return $lista ? $lista[random_int(0, count($lista) - 1)] : null;
}
