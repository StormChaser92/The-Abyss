<?php
/* the-abyss/api/kasyno_blackjack.php
   Blackjack — 6 talii, krupier dobiera na miękkie 17, BJ płaci 3:2.

   Stan rundy siedzi w tabeli `kasyno_blackjack`, nie w sesji: odświeżenie
   strony w środku rozdania niczego nie gubi, a klient nigdy nie widzi
   zakrytej karty krupiera, dopóki runda się nie skończy.

   But żyje między rundami i tasuje się dopiero po zejściu 75% kart —
   liczenie kart ma sens i jest to celowe.

   Akcje (?a=):
     stan                      bieżąca ręka, portfel, but, historia
     rozdaj    {stawka}        nowe rozdanie
     ubezpiecz {tak}           odpowiedź na propozycję ubezpieczenia
     dobierz | stoj | podwoj | split
     tasuj                     ręczne przetasowanie (tylko między rundami)
*/
declare(strict_types=1);
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/kasyno_core.php';
require_once __DIR__.'/../includes/blackjack_stol.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$polaczenie->set_charset('utf8mb4');

$a   = (string)kc_in('a', 'stan');
$gid = kc_gracz_id();

/* ------------------------------- stan -------------------------------- */

function bj_wiersz(int $gid, bool $blokuj = false): array {
  $r = kc_row('SELECT * FROM kasyno_blackjack WHERE gracz_id=?'.($blokuj ? ' FOR UPDATE' : ''), [$gid], 'i');
  if (!$r) {
    kc_q('INSERT INTO kasyno_blackjack (gracz_id,but,zeszlo,tasowan,stan) VALUES (?,?,0,1,"brak")',
      [$gid, json_encode(bj_nowy_but())], 'is');
    $r = kc_row('SELECT * FROM kasyno_blackjack WHERE gracz_id=?'.($blokuj ? ' FOR UPDATE' : ''), [$gid], 'i');
  }
  return [
    'but'           => json_decode((string)$r['but'], true) ?: [],
    'zeszlo'        => (int)$r['zeszlo'],
    'tasowan'       => (int)$r['tasowan'],
    'stan'          => (string)$r['stan'],
    'stawka'        => (int)$r['stawka'],
    'ubezpieczenie' => (int)$r['ubezpieczenie'],
    'rece'          => json_decode((string)$r['rece'], true) ?: [],
    'dealer'        => json_decode((string)$r['dealer'], true) ?: [],
    'aktywna'       => (int)$r['aktywna'],
    'runda_id'      => $r['runda_id'] !== null ? (int)$r['runda_id'] : null,
  ];
}

function bj_zapisz(int $gid, array $st): void {
  kc_q('UPDATE kasyno_blackjack SET but=?, zeszlo=?, tasowan=?, stan=?, stawka=?, ubezpieczenie=?,
          rece=?, dealer=?, aktywna=?, runda_id=? WHERE gracz_id=?',
    [json_encode($st['but']), $st['zeszlo'], $st['tasowan'], $st['stan'], $st['stawka'],
     $st['ubezpieczenie'], json_encode($st['rece']), json_encode($st['dealer']),
     $st['aktywna'], $st['runda_id'], $gid], 'siisiissiii');
}

function bj_zetony(int $gid): int {
  return (int)(kc_row('SELECT zetony FROM gracze WHERE id=?', [$gid], 'i')['zetony'] ?? 0);
}

function bj_odpowiedz(int $gid, array $st, array $extra = []): never {
  $zetony = bj_zetony($gid);
  $g = kc_row('SELECT gotowka FROM gracze WHERE id=?', [$gid], 'i');
  kc_ok(bj_widok($st, $zetony) + [
    'portfel' => ['gotowka'=>(int)$g['gotowka'], 'zetony'=>$zetony],
    'but'     => ['zostalo'=>count($st['but']), 'wszystkich'=>BJ_KART,
                  'zeszlo'=>$st['zeszlo'], 'tasowan'=>$st['tasowan']],
    'limity'  => ['min'=>BJ_MIN, 'max'=>BJ_MAX],
    'historia'=> kc_all('SELECT stawka, wyplata, gracz_pkt, dealer_pkt, wynik
                         FROM kasyno_bj_historia WHERE gracz_id=? ORDER BY id DESC LIMIT 12', [$gid], 'i'),
  ] + $extra);
}

/** Zdjęcie karty z buta. But nigdy nie pustoszeje w środku rozdania —
    dokłada się awaryjnie świeży, bo przerwana ręka byłaby gorsza. */
function bj_karta(array &$st): string {
  if (!$st['but']) { $st['but'] = bj_nowy_but(); $st['zeszlo'] = 0; $st['tasowan']++; }
  $st['zeszlo']++;
  return array_pop($st['but']);
}

/* Domknięcie rundy: krupier dogrywa, rozliczenie, wypłata, statystyki. */
function bj_zakoncz(int $gid, array &$st): void {
  $zywa = false;
  foreach ($st['rece'] as $r) if ($r['stan'] !== 'fura') $zywa = true;

  if ($zywa && !(count($st['dealer']) === 2 && bj_wartosc($st['dealer'])['suma'] === 21)) {
    while (true) {
      $w = bj_wartosc($st['dealer']);
      if ($w['suma'] > 17 || ($w['suma'] === 17 && !$w['miekka'])) break;
      $st['dealer'][] = bj_karta($st);
    }
  }

  $wyplata = bj_rozlicz($st);
  $st['stan'] = 'koniec';

  $stawka = $st['ubezpieczenie'];
  foreach ($st['rece'] as $r) $stawka += $r['stawka'];

  $pkt  = 0;
  foreach ($st['rece'] as $r) $pkt = max($pkt, bj_wartosc($r['karty'])['suma']);
  $dpkt = bj_wartosc($st['dealer'])['suma'];

  $wyniki = array_column($st['rece'], 'wynik');
  $glowny = in_array('blackjack', $wyniki, true) ? 'blackjack'
          : (in_array('wygrana', $wyniki, true) ? 'wygrana'
          : (in_array('remis', $wyniki, true) ? 'remis'
          : (in_array('fura', $wyniki, true) ? 'fura' : 'przegrana')));

  $uklad = $glowny.' '.$pkt.' vs '.$dpkt;
  kc_q('INSERT INTO kasyno_solo (gracz_id,gra,stawka,zetony_stawka,wyplata,stan,uklad)
        VALUES (?,"blackjack",?,1,?,"zakonczona",?)', [$gid, $stawka, $wyplata, $uklad], 'iiis');
  global $polaczenie;
  $st['runda_id'] = (int)$polaczenie->insert_id;

  if ($wyplata > 0) kc_kasa($gid, 0, $wyplata, 'wyplata_blackjack', 'solo', $st['runda_id']);

  kc_q('INSERT INTO kasyno_bj_historia (gracz_id,stawka,wyplata,gracz_pkt,dealer_pkt,wynik)
        VALUES (?,?,?,?,?,?)', [$gid, $stawka, $wyplata, min($pkt, 255), min($dpkt, 255), $glowny], 'iiiiis');
  kc_q('INSERT INTO kasyno_solo_udzial (gracz_id,gra,stawka,wyplata) VALUES (?,"blackjack",?,?)',
    [$gid, $stawka, $wyplata], 'iii');
  kc_zapisz_wynik($gid, $stawka, $wyplata, 'blackjack');
}

/** Przejście do kolejnej ręki; jeśli nie ma — domknięcie rundy. */
function bj_dalej(int $gid, array &$st): void {
  for ($i = $st['aktywna']; $i < count($st['rece']); $i++) {
    if ($st['rece'][$i]['stan'] === 'gra') { $st['aktywna'] = $i; return; }
  }
  bj_zakoncz($gid, $st);
}

/* ------------------------------ akcje -------------------------------- */

if ($a === 'stan') bj_odpowiedz($gid, bj_wiersz($gid));

if ($a === 'tasuj') {
  $st = kc_tx(function() use ($gid) {
    $st = bj_wiersz($gid, true);
    if ($st['stan'] === 'gra' || $st['stan'] === 'ubezpieczenie')
      kc_blad('Nie tasujemy w środku rozdania.');
    $st['but'] = bj_nowy_but(); $st['zeszlo'] = 0; $st['tasowan']++;
    bj_zapisz($gid, $st);
    return $st;
  });
  bj_odpowiedz($gid, $st, ['komunikat'=>'Krupier wprowadza świeży but.']);
}

if ($a === 'rozdaj') {
  kc_sprawdz_prog($gid);
  $stawka = kc_int('stawka');
  if ($stawka < BJ_MIN || $stawka > BJ_MAX)
    kc_blad('Stawka od '.number_format(BJ_MIN, 0, '', ' ').' do '.number_format(BJ_MAX, 0, '', ' ').' żetonów.');
  if ($stawka % BJ_MIN !== 0) kc_blad('Stawka w wielokrotnościach '.BJ_MIN.'.');

  $st = kc_tx(function() use ($gid, $stawka) {
    $st = bj_wiersz($gid, true);
    if ($st['stan'] === 'gra' || $st['stan'] === 'ubezpieczenie')
      kc_blad('Najpierw dokończ rozdanie.');

    if ($st['zeszlo'] >= (int)(BJ_KART * BJ_PENETRACJA) || count($st['but']) < 20) {
      $st['but'] = bj_nowy_but(); $st['zeszlo'] = 0; $st['tasowan']++;
    }

    kc_kasa($gid, 0, -$stawka, 'zaklad_blackjack');

    $st['stawka']        = $stawka;
    $st['ubezpieczenie'] = 0;
    $st['aktywna']       = 0;
    $st['runda_id']      = null;
    $st['rece']          = [bj_pusta_reka($stawka)];
    $st['dealer']        = [];
    $st['stan']          = 'gra';

    $st['rece'][0]['karty'][] = bj_karta($st);
    $st['dealer'][]           = bj_karta($st);
    $st['rece'][0]['karty'][] = bj_karta($st);
    $st['dealer'][]           = bj_karta($st);

    $up   = bj_ranga_pkt($st['dealer'][0]);
    $dbj  = bj_wartosc($st['dealer'])['suma'] === 21;
    $gbj  = bj_wartosc($st['rece'][0]['karty'])['suma'] === 21;

    if ($up === 11 && bj_zetony($gid) >= intdiv($stawka, 2) && !$gbj) {
      $st['stan'] = 'ubezpieczenie';          // pytamy, zanim krupier zajrzy
      bj_zapisz($gid, $st);
      return $st;
    }
    // krupier zagląda pod asa i dziesiątkę; naturalny po dowolnej stronie kończy rundę
    if ($dbj || $gbj) bj_zakoncz($gid, $st);

    bj_zapisz($gid, $st);
    return $st;
  });
  bj_odpowiedz($gid, $st);
}

if ($a === 'ubezpiecz') {
  $tak = filter_var(kc_in('tak', false), FILTER_VALIDATE_BOOL);
  $st = kc_tx(function() use ($gid, $tak) {
    $st = bj_wiersz($gid, true);
    if ($st['stan'] !== 'ubezpieczenie') kc_blad('Nie ma czego ubezpieczać.');
    if ($tak) {
      $kwota = intdiv($st['stawka'], 2);
      kc_kasa($gid, 0, -$kwota, 'ubezpieczenie_blackjack');
      $st['ubezpieczenie'] = $kwota;
    }
    $st['stan'] = 'gra';
    if (bj_wartosc($st['dealer'])['suma'] === 21) bj_zakoncz($gid, $st);
    bj_zapisz($gid, $st);
    return $st;
  });
  bj_odpowiedz($gid, $st);
}

if (in_array($a, ['dobierz','stoj','podwoj','split'], true)) {
  $st = kc_tx(function() use ($gid, $a) {
    $st = bj_wiersz($gid, true);
    if ($st['stan'] !== 'gra') kc_blad('Rozdanie nie jest w toku.');
    $i = $st['aktywna'];
    if (!isset($st['rece'][$i]) || $st['rece'][$i]['stan'] !== 'gra') kc_blad('Ta ręka jest już zamknięta.');
    $moz = bj_mozliwosci($st, bj_zetony($gid));
    if (!in_array($a, $moz, true)) kc_blad('Nie możesz teraz tego zrobić.');

    if ($a === 'dobierz') {
      $st['rece'][$i]['karty'][] = bj_karta($st);
      $w = bj_wartosc($st['rece'][$i]['karty']);
      if ($w['suma'] > 21)       $st['rece'][$i]['stan'] = 'fura';
      elseif ($w['suma'] === 21) $st['rece'][$i]['stan'] = 'stoi';
      if ($st['rece'][$i]['stan'] !== 'gra') bj_dalej($gid, $st);

    } elseif ($a === 'stoj') {
      $st['rece'][$i]['stan'] = 'stoi';
      $st['aktywna'] = $i + 1;
      bj_dalej($gid, $st);

    } elseif ($a === 'podwoj') {
      $dop = $st['rece'][$i]['stawka'];
      kc_kasa($gid, 0, -$dop, 'podwojenie_blackjack');
      $st['rece'][$i]['stawka']   += $dop;
      $st['rece'][$i]['podwojona'] = true;
      $st['rece'][$i]['karty'][]   = bj_karta($st);
      $w = bj_wartosc($st['rece'][$i]['karty']);
      $st['rece'][$i]['stan'] = $w['suma'] > 21 ? 'fura' : 'stoi';
      $st['aktywna'] = $i + 1;
      bj_dalej($gid, $st);

    } else { // split
      $stawka = $st['rece'][$i]['stawka'];
      kc_kasa($gid, 0, -$stawka, 'split_blackjack');
      $karty = $st['rece'][$i]['karty'];
      $nowa  = bj_pusta_reka($stawka, true);
      $nowa['karty'] = [$karty[1]];
      $st['rece'][$i]['karty']     = [$karty[0]];
      $st['rece'][$i]['ze_splitu'] = true;
      array_splice($st['rece'], $i + 1, 0, [$nowa]);

      $asy = $karty[0][0] === 'A';
      $st['rece'][$i]['karty'][]     = bj_karta($st);
      $st['rece'][$i + 1]['karty'][] = bj_karta($st);
      if ($asy) {                       // dzielone asy: po jednej karcie i koniec
        $st['rece'][$i]['stan']     = 'stoi';
        $st['rece'][$i + 1]['stan'] = 'stoi';
        bj_dalej($gid, $st);
      } else {
        foreach ([$i, $i + 1] as $nr)
          if (bj_wartosc($st['rece'][$nr]['karty'])['suma'] === 21) $st['rece'][$nr]['stan'] = 'stoi';
        bj_dalej($gid, $st);
      }
    }

    bj_zapisz($gid, $st);
    return $st;
  });
  bj_odpowiedz($gid, $st);
}

kc_blad('Nieznana akcja.', 404);
