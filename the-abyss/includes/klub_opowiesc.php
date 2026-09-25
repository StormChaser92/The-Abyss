<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/KLUB_OPOWIESC.PHP
   Wydarzenia w Klubie (Opowieści typu „klub”) na Sali Głównej:
   pasek „Na żywo” dla wszystkich, Kulisy MG dla prowadzącego.
   Rzuty publikowane są jednocześnie w Opowieści i na czacie Sali Głównej.
   Uczestnikami rzutów i walki są zapisani gracze + goście obecni na sali.
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/panel_mg.php';

const KO_SALA = 'sala-glowna';

/** Trwające Wydarzenia w Klubie (najświeższe pierwsze). */
function ko_lista(mysqli $db): array {
    return db_wiersze($db, "SELECT s.*, g.login AS prow FROM sesje_rpg s JOIN gracze g ON g.id = s.mg_id
        WHERE s.typ_opowiesci = 'klub' AND s.status <> 'Zakończona' AND s.akceptacja = 'ok' ORDER BY s.ostatnia_aktywnosc DESC LIMIT 5");
}

function ko_prowadzi(mysqli $db, array $s, int $gid): bool {
    if ((int)$s['mg_id'] === $gid) return true;
    return (bool)db_wiersz($db, "SELECT 1 FROM sesje_uczestnicy WHERE sesja_id = ? AND gracz_id = ? AND rola = 'Mistrz Gry' AND status_akceptacji = 'Zaakceptowany'", [(int)$s['id'], $gid]);
}

/** Wydarzenie wybrane na Sali: ?op=ID (jeśli trwa), inaczej najświeższe — prowadzący widzi najpierw swoje. */
function ko_wybierz(mysqli $db, int $gid): ?array {
    $l = ko_lista($db);
    if (!$l) return null;
    $op = (int)($_GET['op'] ?? 0);
    foreach ($l as $s) if ((int)$s['id'] === $op) return $s;
    foreach ($l as $s) if (ko_prowadzi($db, $s, $gid)) return $s;
    return $l[0];
}

/** Goście obecni na Sali Głównej (ostatnie 5 minut) — do celów testów i walki. */
function ko_obecni(mysqli $db): array {
    return db_wiersze($db, "SELECT * FROM gracze WHERE klub_sala = ? AND ostatnia_aktywnosc >= NOW() - INTERVAL 5 MINUTE ORDER BY login", [KO_SALA]);
}

/** Pasek „Na żywo” nad czatem — widoczny dla wszystkich na Sali Głównej. */
function ko_pasek(mysqli $db, ?array $s, int $gid): void {
    pm_css();
    if (!$s) return;
    $h = 'rw_h'; $L = RP_POZIOMY[$s['poziom']] ?? RP_POZIOMY['niski']; $inne = array_filter(ko_lista($db), fn($x) => (int)$x['id'] !== (int)$s['id']);
    echo "<div class='ko-live' style='--lc:{$L['kolor']}'><span class='ko-dot'></span><div class='ko-t'><span class='ko-lbl'>Wydarzenie na żywo · " . $h($L['n']) . "</span><b>" . $h($s['tytul']) . "</b><small>Prowadzi " . $h($s['prow']) . "</small></div>"
       . "<a class='ko-a' href='game.php?page=pokoj_sesji&id=" . (int)$s['id'] . "'>Opowieść ›</a>";
    foreach ($inne as $x) echo "<a class='ko-a ghost' href='game.php?page=czat&sala=" . KO_SALA . "&op=" . (int)$x['id'] . "'>" . $h($x['tytul']) . "</a>";
    echo "</div>";
    pm_pasek($db, $s);
}
