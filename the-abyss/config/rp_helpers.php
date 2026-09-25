<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — HELPERY DO SESJI RP (Centrum Opowieści)

   Używane przy testach umiejętności w sesjach fabularnych.
   Od v2 test to k100: szansa = poziom×20 + ⅓ Atrybutu + mody, limit PE.
   Bonus pochodzenia: 1 pkt = 5 % szansy. Bonus zawodu: % / 2.
   Szczegóły i stałe: config/umiejetnosci.php.

   Zależności:
   - config/pochodzenia.php (musi być włączony pierwszy)
   - config/zawody.php       (musi być włączony pierwszy)
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/umiejetnosci.php';   // v2: test k100 — um_test(), um_opis_testu()

/**
 * Test Umiejętności w systemie k100 (config/umiejetnosci.php → um_test()).
 * Zachowuje stare klucze, żeby karta.php i pokoj_sesji.php działały bez zmian:
 * 'wartosc_koncowa' to teraz szansa w %.
 */
function bonus_rp_umiejetnosci($gracz, $umiejetnosc, $atr = 'g', $mod_dod = 0) {
    $t = um_test($gracz, $umiejetnosc, $atr, (int)$mod_dod);
    return $t + [
        'baza_pu'         => $t['poziom'],
        'pochodzenie'     => $t['poch_pkt'],
        'po_pochodzeniu'  => $t['baza'] + $t['mod_pochodzenia'],
        'mnoznik_zawodu'  => 1.0,
        'wartosc_koncowa' => $t['szansa'],
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
 * Formatuje rozbicie szansy do wyświetlenia, np. "3×20 + 18 Siła · +10 poch. = <strong>78%</strong>".
 */
function formatuj_bonus_rp($wynik) {
    $s = htmlspecialchars(um_opis_testu($wynik));
    return preg_replace('/= (\d+%)/', '= <strong>$1</strong>', $s, 1);
}
