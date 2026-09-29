<?php
/* Powiadomienie (nie poczta!) o kontraktach, którym minął czas.
   Woła game.php przy każdym wejściu; ciężka logika zleceń ładuje się tylko, gdy jest co ogłosić.
   Wymaga kolumny zl_dziennik.powiadomiono (db/migracja_zlecenia_powiadomienia.sql). */

/** Zwraca liczbę kontraktów gotowych do odebrania (licznik przy Zleceniach w belce). */
function zl_powiadom_gotowe(mysqli $db, int $gid, string $miasto): int {
    $nowe = db_wiersze($db, "SELECT id, kod FROM zl_dziennik
        WHERE gracz_id = ? AND rodzaj = 'kontrakt' AND rozliczone = 0 AND powiadomiono = 0
          AND gotowe_o IS NOT NULL AND gotowe_o <= NOW()", [$gid]);
    if ($nowe) {
        require_once __DIR__ . '/zlecenia_logika.php';
        $reg = zl_region($miasto) ?? 'NY';
        $kontrakty = zl_dane($reg)['kontrakty'] ?? [];
        foreach ($nowe as $z) {
            if (db_zmien($db, "UPDATE zl_dziennik SET powiadomiono = 1 WHERE id = ? AND powiadomiono = 0", [(int)$z['id']]) !== 1) continue;
            $tytul = isset($kontrakty[$z['kod']]) ? '<b>' . zl_h($kontrakty[$z['kod']]['t']) . '</b>' : 'Twoje zlecenie';
            powiadom($db, $gid, "📜 $tytul dobiegło końca. Wynik czeka na <a href='game.php?page=zlecenia'>Tablicy Zleceń</a>.");
        }
    }
    return (int)(db_wiersz($db, "SELECT COUNT(*) c FROM zl_dziennik
        WHERE gracz_id = ? AND rodzaj = 'kontrakt' AND rozliczone = 0 AND gotowe_o <= NOW()", [$gid])['c'] ?? 0);
}
