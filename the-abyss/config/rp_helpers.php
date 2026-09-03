<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — HELPERY DO SESJI RP (Centrum Opowieści)

   Używane przy testach umiejętności w sesjach fabularnych.
   Mistrz Gry wywoła test → system policzy:
     (bazowe_PU + bonus_flat_pochodzenia − kara_flat_pochodzenia) × (1 + bonus_%_zawodu / 100)

   Zależności:
   - config/pochodzenia.php (musi być włączony pierwszy)
   - config/zawody.php       (musi być włączony pierwszy)
   ═══════════════════════════════════════════════════════════════════════ */

/**
 * Liczy pełną wartość umiejętności w sesji RP dla konkretnego gracza.
 *
 * @param array  $gracz         wiersz z tabeli `gracze` (musi zawierać: umiejetnosci, pochodzenie, profesja_fabularna)
 * @param string $umiejetnosc   nazwa umiejętności (np. "Matematyka i Rachunkowość")
 *
 * @return array  [
 *    'baza_pu'        => int,   // surowe punkty umiejętności (z JSON-a)
 *    'pochodzenie'    => int,   // płaski bonus/kara pochodzenia (może być ujemny)
 *    'zawod_proc'     => int,   // procent bonusu z zawodu (0-30%)
 *    'po_pochodzeniu' => int,   // (baza + pochodzenie)
 *    'wartosc_koncowa'=> float, // final z zaokrągleniem do 1 miejsca
 *    'mnoznik_zawodu' => float, // 1.0, 1.15, 1.25 itd.
 * ]
 */
function bonus_rp_umiejetnosci($gracz, $umiejetnosc) {
    global $POCHODZENIA_DANE, $ZAWODY_DANE;

    // 1) Bazowe PU z JSON-a
    $json = $gracz['umiejetnosci'] ?? '';
    $um_gracza = !empty($json) ? json_decode($json, true) : [];
    $baza = (int)($um_gracza[$umiejetnosc] ?? 0);

    // 2) Bonus/kara płaska pochodzenia
    $poch_bonus = 0;
    $poch = $gracz['pochodzenie'] ?? null;
    if ($poch && isset($POCHODZENIA_DANE[$poch]['rp'])) {
        $rp = $POCHODZENIA_DANE[$poch]['rp'];
        $poch_bonus  = (int)($rp['umiejetnosci_bonus_flat'][$umiejetnosc] ?? 0);
        $poch_bonus -= abs((int)($rp['umiejetnosci_kara_flat'][$umiejetnosc]  ?? 0));
    }

    // 3) Procent bonusu z zawodu
    $zaw_proc = 0;
    $zawod = $gracz['profesja_fabularna'] ?? null;
    if ($zawod && isset($ZAWODY_DANE[$zawod]['rp']['umiejetnosci_bonus_proc'][$umiejetnosc])) {
        $zaw_proc = (int)$ZAWODY_DANE[$zawod]['rp']['umiejetnosci_bonus_proc'][$umiejetnosc];
    }

    // 4) Formuła
    $po_pochodzeniu = max(0, $baza + $poch_bonus);
    $mnoznik        = 1.0 + ($zaw_proc / 100.0);
    $final          = round($po_pochodzeniu * $mnoznik, 1);

    return [
        'baza_pu'         => $baza,
        'pochodzenie'     => $poch_bonus,
        'zawod_proc'      => $zaw_proc,
        'po_pochodzeniu'  => $po_pochodzeniu,
        'mnoznik_zawodu'  => $mnoznik,
        'wartosc_koncowa' => $final,
    ];
}

/**
 * Zwraca reputację grupową gracza (suma z pochodzenia i zawodu).
 *
 * @param array $gracz
 * @return array ['elita' => int, 'ulica' => int, 'syndykat' => int, 'wladze' => int, 'spoleczenstwo' => int]
 */
function reputacja_grupowa($gracz) {
    global $POCHODZENIA_DANE, $ZAWODY_DANE;

    $rep = ['elita' => 0, 'ulica' => 0, 'syndykat' => 0, 'wladze' => 0, 'spoleczenstwo' => 0];

    $poch = $gracz['pochodzenie'] ?? null;
    if ($poch && isset($POCHODZENIA_DANE[$poch]['rp']['reputacja'])) {
        foreach ($POCHODZENIA_DANE[$poch]['rp']['reputacja'] as $grupa => $v) {
            if (isset($rep[$grupa])) $rep[$grupa] += (int)$v;
        }
    }

    $zawod = $gracz['profesja_fabularna'] ?? null;
    if ($zawod && isset($ZAWODY_DANE[$zawod]['rp']['reputacja'])) {
        foreach ($ZAWODY_DANE[$zawod]['rp']['reputacja'] as $grupa => $v) {
            if (isset($rep[$grupa])) $rep[$grupa] += (int)$v;
        }
    }

    // Clamp do zakresu [-5, 5] — żeby nie było ekstremów typu +8
    foreach ($rep as $g => $v) {
        $rep[$g] = max(-5, min(5, $v));
    }
    return $rep;
}

/**
 * Ikonka i kolor reputacji — do szybkiego wyświetlania w UI.
 */
function reputacja_opis($wartosc) {
    if ($wartosc >=  4) return ['ikona' => '👑', 'kolor' => '#ffd700', 'opis' => 'Legendarny szacunek'];
    if ($wartosc >=  2) return ['ikona' => '✓',  'kolor' => '#5aff9a', 'opis' => 'Szanowany'];
    if ($wartosc >=  1) return ['ikona' => '↑',  'kolor' => '#b8e8b8', 'opis' => 'Lubiany'];
    if ($wartosc ==  0) return ['ikona' => '○',  'kolor' => '#8a818e', 'opis' => 'Neutralny'];
    if ($wartosc >= -1) return ['ikona' => '↓',  'kolor' => '#ffa8a8', 'opis' => 'Nielubiany'];
    if ($wartosc >= -3) return ['ikona' => '✗',  'kolor' => '#ff5577', 'opis' => 'Pogardzany'];
    return                    ['ikona' => '☠',  'kolor' => '#ff1744', 'opis' => 'Wróg na śmierć'];
}

/**
 * Formatuje bonus RP umiejętności do wyświetlenia (np. w karcie postaci).
 *
 * @param array $wynik  rezultat bonus_rp_umiejetnosci()
 * @return string  np. "5 → +2 poch → ×1.25 → 8.8"
 */
function formatuj_bonus_rp($wynik) {
    $baza = $wynik['baza_pu'];
    $poch = $wynik['pochodzenie'];
    $proc = $wynik['zawod_proc'];
    $final = $wynik['wartosc_koncowa'];

    $s = "$baza";
    if ($poch > 0) $s .= " +{$poch}";
    elseif ($poch < 0) $s .= " {$poch}";  // minus już w liczbie
    if ($proc > 0) $s .= " ×" . number_format($wynik['mnoznik_zawodu'], 2);
    $s .= " = <strong>$final</strong>";
    return $s;
}