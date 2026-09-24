<?php
require_once __DIR__ . '/bezpieczne.php';
/* the-abyss/includes/warsztat_logika.php
   Warsztat Inżyniera: wytwarzanie, ulepszanie +1…+10, zlecenia od nie-Inżynierów.
   Wymaga includes/bronie_katalog.php i tabel z db/migracja_warsztat.sql.

   Ustalenia:
     · ulepszać może TYLKO Inżynier; reszta składa zlecenie i płaci z góry
     · porażka przy ulepszaniu ZBIJA broń o jeden stopień, nigdy jej nie niszczy
     · przy nieudanym zleceniu Inżynier zwraca zapłatę
     · cennik ustala sam Inżynier
     · rusznikarstwo (gracze.umiejetnosc_inzynierii) rośnie z każdym wytworzeniem
       i z każdą próbą ulepszenia — na starcie jest trudno i często się nie udaje

   Własność przedmiotów przeniesiona z kolumn `gracze.eq_*` do tabeli
   `ekwipunek_gracza` (gracz_id, kod, ilosc, stopien). 60 broni nie da się
   trzymać jako 60 kolumn. */

require_once __DIR__.'/bronie_katalog.php';

/** Pancerze mają katalog w ekwipunek_katalog.php — naprawa go potrzebuje. */
if (!function_exists('eq_katalog_pancerzy')) require_once __DIR__.'/ekwipunek_katalog.php';

const WARSZTAT_STOPIEN_MAX = 10;
const WARSZTAT_KROK        = 0.05;   // +5% ataku na stopień

/* ── własność przedmiotów ──────────────────────────────────────────── */

function eq_stan(mysqli $db, int $gid): array {
    $r = $db->query("SELECT kod, ilosc, stopien FROM ekwipunek_gracza WHERE gracz_id=$gid");
    $out = [];
    while ($r && $w = $r->fetch_assoc())
        $out[$w['kod']] = ['ilosc'=>(int)$w['ilosc'], 'stopien'=>(int)$w['stopien']];
    return $out;
}

function eq_ma(mysqli $db, int $gid, string $kod): int {
    $k = $db->real_escape_string($kod);
    $r = $db->query("SELECT ilosc FROM ekwipunek_gracza WHERE gracz_id=$gid AND kod='$k'");
    return $r && $r->num_rows ? (int)$r->fetch_assoc()['ilosc'] : 0;
}

function eq_stopien(mysqli $db, int $gid, string $kod): int {
    $k = $db->real_escape_string($kod);
    $r = $db->query("SELECT stopien FROM ekwipunek_gracza WHERE gracz_id=$gid AND kod='$k'");
    return $r && $r->num_rows ? (int)$r->fetch_assoc()['stopien'] : 0;
}

function eq_dodaj(mysqli $db, int $gid, string $kod, int $ile = 1): void {
    $k = $db->real_escape_string($kod);
    $db->query("INSERT INTO ekwipunek_gracza (gracz_id, kod, ilosc, stopien) VALUES ($gid, '$k', $ile, 0)
                ON DUPLICATE KEY UPDATE ilosc = ilosc + $ile");
}

/** Atak broni po ulepszeniach: każdy stopień +5% ataku bazowego. */
function bron_atak(array $b, int $stopien): int {
    return (int)round($b['atak'] * (1 + WARSZTAT_KROK * max(0, min(WARSZTAT_STOPIEN_MAX, $stopien))));
}

/* ── rusznikarstwo ─────────────────────────────────────────────────── */

/**
 * Szansa udanego wytworzenia w %.
 * Na starcie (skill 0) nawet zaułkowe schematy wychodzą co czwarty raz —
 * dopiero praktyka to odwraca.
 */
function rusznikarstwo_szansa(array $g, int $trudnosc): float {
    $s = 25
       + (float)$g['umiejetnosc_inzynierii'] * 2.5
       + (int)$g['inteligencja'] / 3
       - $trudnosc * 0.8;
    return max(5, min(95, $s));
}

/** Szansa udanego ulepszenia. Każdy kolejny stopień jest wyraźnie trudniejszy.
    Kara kwadratowa dobiega 100 na ostatnim kroku, więc mistrz ma tam ~25% —
    +10 jest osiągalne, ale kosztuje serię prób (porażka zbija o stopień). */
function ulepszenie_szansa(array $g, array $b, int $ze_stopnia): float {
    $s = 70
       + (float)$g['umiejetnosc_inzynierii'] * 1.8
       + (int)$g['inteligencja'] / 4
       - $b['poziom'] * 0.25
       - pow($ze_stopnia + 1, 2);
    return max(5, min(95, $s));
}

/** Przyrost rusznikarstwa. Sukces uczy proporcjonalnie do trudności, porażka ledwie. */
function rusznikarstwo_przyrost(int $trudnosc, bool $sukces): float {
    return $sukces
        ? round(($trudnosc / 15) * (mt_rand(80, 120) / 100), 2)
        : mt_rand(1, 5) / 100;
}

/* ── wytwarzanie ───────────────────────────────────────────────────── */

/**
 * Wytworzenie broni. Zwraca [ok, komunikat, dane].
 * $mod: bonusy pochodzenia (craft_energia_mult, inzynier_craft_fail_mult,
 * craft_bonus_produkt_szansa) — przekazywane z pages/warsztat.php.
 */
function warsztat_wytworz(mysqli $db, array $g, string $kod, array $mod = []): array {
    $kat = bronie_katalog();
    if (!isset($kat[$kod])) return [false, 'Nie znam takiego schematu.', []];
    $b   = $kat[$kod];
    $gid = (int)$g['id'];

    $en = max(1, (int)round($b['en'] * (float)($mod['craft_energia_mult'] ?? 1.0)));
    if ((int)$g['energia_aktualna'] < $en)
        return [false, "Za mało siły w rękach. Potrzebujesz $en EN.", []];
    if ((int)$g['zlom_stalowy'] < $b['stal'] || (int)$g['czesci_mechaniczne'] < $b['czesci']
        || (int)$g['syntetyki'] < $b['syn'] || (int)$g['elektronika'] < $b['elek'])
        return [false, 'Brakuje materiałów. Dokup u Szabrownika.', []];

    $szansa = rusznikarstwo_szansa($g, (int)$b['trudnosc']);
    $szansa = min(95, $szansa / max(0.01, (float)($mod['inzynier_craft_fail_mult'] ?? 1.0)));
    $los    = mt_rand(1, 10000) / 100;

    if ($los <= $szansa) {
        $ile = 1;
        if (mt_rand(1, 100) <= (int)($mod['craft_bonus_produkt_szansa'] ?? 0)) $ile = 2;
        $przyrost = rusznikarstwo_przyrost((int)$b['trudnosc'], true);
        $exp      = (int)$b['trudnosc'] * 5;

        $pobrano = db_zmien($db, "UPDATE gracze SET
            zlom_stalowy = zlom_stalowy - ?,
            czesci_mechaniczne = czesci_mechaniczne - ?,
            syntetyki = syntetyki - ?,
            elektronika = elektronika - ?,
            energia_aktualna = energia_aktualna - ?,
            exp = exp + ?,
            umiejetnosc_inzynierii = umiejetnosc_inzynierii + ?
            WHERE id = ? AND zlom_stalowy >= ? AND czesci_mechaniczne >= ? AND syntetyki >= ?
              AND elektronika >= ? AND energia_aktualna >= ?",
            [(int)$b['stal'], (int)$b['czesci'], (int)$b['syn'], (int)$b['elek'], $en, $exp, (float)$przyrost, $gid,
             (int)$b['stal'], (int)$b['czesci'], (int)$b['syn'], (int)$b['elek'], $en], 'iiiiiidiiiiii') === 1;
        if (!$pobrano) return [false, 'Brakuje materiałów albo energii.', []];
        eq_dodaj($db, $gid, $kod, $ile);

        $typ = 'wytworz_'.preg_replace('/[^a-z0-9_]/', '', $kod);
        $db->query("UPDATE kontrakty_klasowe SET postep = LEAST(cel_ilosc, postep+1)
                    WHERE gracz_id=$gid AND status='aktywny' AND typ_celu='$typ' AND deadline > NOW()");

        $sztuk = $ile > 1 ? " ×$ile" : '';
        return [true, "Gotowe: <b>{$b['nazwa']}</b>$sztuk. +$exp EXP, +$przyrost rusznikarstwa.",
                ['ile'=>$ile, 'szansa'=>round($szansa, 1)]];
    }

    // Porażka: połowa materiałów i połowa energii w piach.
    $przyrost = rusznikarstwo_przyrost((int)$b['trudnosc'], false);
    $ss = (int)floor($b['stal'] / 2); $sc = (int)floor($b['czesci'] / 2);
    $sy = (int)floor($b['syn'] / 2);  $se = (int)floor($b['elek'] / 2);
    $db->query("UPDATE gracze SET
        zlom_stalowy = GREATEST(0, zlom_stalowy - $ss), czesci_mechaniczne = GREATEST(0, czesci_mechaniczne - $sc),
        syntetyki = GREATEST(0, syntetyki - $sy), elektronika = GREATEST(0, elektronika - $se),
        energia_aktualna = GREATEST(0, energia_aktualna - ".max(1, (int)floor($en / 2))."),
        umiejetnosc_inzynierii = umiejetnosc_inzynierii + $przyrost
        WHERE id = $gid");

    return [false, "Nie wyszło. Materiał w połowie do kosza. (rzut ".round($los, 1)."% przy szansie "
                 . round($szansa, 1)."%) Nauka z błędu: +$przyrost rusznikarstwa.",
            ['szansa'=>round($szansa, 1)]];
}

/* ── ulepszanie ────────────────────────────────────────────────────── */

/**
 * Ulepszenie o jeden stopień. Tylko Inżynier — sprawdzenie klasy po stronie strony.
 * $wlasciciel_id: czyja broń (przy zleceniu to klient, nie Inżynier).
 * Porażka zbija stopień o jeden, nigdy nie niszczy.
 */
function warsztat_ulepsz(mysqli $db, array $inz, string $kod, int $wlasciciel_id): array {
    $kat = bronie_katalog();
    if (!isset($kat[$kod])) return [false, 'Nie znam takiej broni.', []];
    $b = $kat[$kod];

    if (eq_ma($db, $wlasciciel_id, $kod) < 1)
        return [false, 'Właściciel nie ma tej broni przy sobie.', []];

    $st = eq_stopien($db, $wlasciciel_id, $kod);
    if ($st >= WARSZTAT_STOPIEN_MAX)
        return [false, "Ta sztuka jest już na +".WARSZTAT_STOPIEN_MAX.". Dalej się nie da.", []];

    // Koszt materiałowy rośnie z każdym stopniem.
    $mnoznik = 0.35 * ($st + 1);
    $st_stal = (int)ceil($b['stal'] * $mnoznik);
    $st_czes = (int)ceil($b['czesci'] * $mnoznik);
    $st_syn  = (int)ceil($b['syn'] * $mnoznik);
    $st_elek = (int)ceil($b['elek'] * $mnoznik);
    $en      = max(2, (int)ceil($b['en'] * 0.5 * ($st + 1) * 0.6));
    $iid     = (int)$inz['id'];

    if ((int)$inz['energia_aktualna'] < $en)
        return [false, "Za mało energii. Potrzebujesz $en EN.", []];
    if ((int)$inz['zlom_stalowy'] < $st_stal || (int)$inz['czesci_mechaniczne'] < $st_czes
        || (int)$inz['syntetyki'] < $st_syn || (int)$inz['elektronika'] < $st_elek)
        return [false, 'Brakuje materiałów na ten stopień.', []];

    $szansa = ulepszenie_szansa($inz, $b, $st);
    $los    = mt_rand(1, 10000) / 100;
    $przyrost = rusznikarstwo_przyrost((int)$b['trudnosc'], $los <= $szansa);

    $pobrano = db_zmien($db, "UPDATE gracze SET
        zlom_stalowy = zlom_stalowy - ?, czesci_mechaniczne = czesci_mechaniczne - ?,
        syntetyki = syntetyki - ?, elektronika = elektronika - ?,
        energia_aktualna = energia_aktualna - ?,
        umiejetnosc_inzynierii = umiejetnosc_inzynierii + ?
        WHERE id = ? AND zlom_stalowy >= ? AND czesci_mechaniczne >= ? AND syntetyki >= ?
          AND elektronika >= ? AND energia_aktualna >= ?",
        [$st_stal, $st_czes, $st_syn, $st_elek, $en, (float)$przyrost, $iid,
         $st_stal, $st_czes, $st_syn, $st_elek, $en], 'iiiiidiiiiii') === 1;
    if (!$pobrano) return [false, 'Brakuje materiałów albo energii na ten stopień.', []];

    $k = $db->real_escape_string($kod);
    if ($los <= $szansa) {
        $nowy = $st + 1;
        $db->query("UPDATE ekwipunek_gracza SET stopien=$nowy WHERE gracz_id=$wlasciciel_id AND kod='$k'");
        warsztat_odswiez_zalozona($db, $wlasciciel_id, $kod, $nowy);
        return [true, "Ulepszono do <b>+$nowy</b> · atak ".bron_atak($b, $st)." → <b>".bron_atak($b, $nowy)."</b>",
                ['stopien'=>$nowy, 'szansa'=>round($szansa, 1), 'sukces'=>true]];
    }

    $nowy = max(0, $st - 1);
    $db->query("UPDATE ekwipunek_gracza SET stopien=$nowy WHERE gracz_id=$wlasciciel_id AND kod='$k'");
    warsztat_odswiez_zalozona($db, $wlasciciel_id, $kod, $nowy);
    $spadl = $nowy < $st ? "Broń spadła z +$st na <b>+$nowy</b>." : 'Broń była na zero, niżej nie spadnie.';
    return [false, "Ręka zadrżała. $spadl (rzut ".round($los, 1)."% przy szansie ".round($szansa, 1).'%)',
            ['stopien'=>$nowy, 'szansa'=>round($szansa, 1), 'sukces'=>false]];
}

/** Jeśli ulepszana broń jest właśnie założona, przelicza bonus_atak właściciela. */
function warsztat_odswiez_zalozona(mysqli $db, int $gid, string $kod, int $stopien): void {
    $kat = bronie_katalog();
    if (!isset($kat[$kod])) return;
    $b = $kat[$kod];
    $n = $db->real_escape_string($b['nazwa']);
    $a = bron_atak($b, $stopien);
    $db->query("UPDATE gracze SET bonus_atak=$a, bron_stopien=$stopien
                WHERE id=$gid AND bron_zalozona='$n'");
}

/* ── naprawa sprzętu ──────────────────────────────────────────────── */

/**
 * Koszt naprawy. Zwraca [gotowka, stal, czesci, ile_punktow].
 * Naprawa jest zawsze do pełna — częściowa tylko zaciemniałaby rachunek.
 * Stawka rośnie z poziomem sprzętu: cięższa broń kosztuje więcej za punkt.
 */
function naprawa_koszt(array $b, int $trwalosc, int $trwalosc_max): array {
    $brak = max(0, $trwalosc_max - $trwalosc);
    if ($brak === 0) return [0, 0, 0, 0];
    $poziom  = max(1, (int)($b['poziom'] ?? 1));
    $gotowka = (int)ceil($brak * (3 + $poziom * 0.9));
    $stal    = (int)ceil($brak * max(1, (int)($b['stal'] ?? 2)) / 100);
    $czesci  = (int)ceil($brak * max(1, (int)($b['czesci'] ?? 1)) / 100);
    return [$gotowka, $stal, $czesci, $brak];
}

/**
 * Naprawa u Inżyniera. $sprzet: 'bron'|'pancerz'.
 * Materiały i gotówka idą z zapasów WŁAŚCICIELA — Inżynier bierze osobno
 * za robociznę (zlecenia). Naprawa nie może się nie udać: żadnej loterii
 * przy sprzęcie, który gracz już opłacił.
 * Zwraca [ok, komunikat].
 */
function naprawa_wykonaj(mysqli $db, array $g, string $sprzet): array {
    $gid = (int)$g['id'];
    if (!in_array($sprzet, ['bron', 'pancerz'], true)) return [false, 'Nie wiem, co miałbym naprawić.'];

    if ($sprzet === 'bron') {
        $nazwa = $g['bron_zalozona'] ?? '';
        if ($nazwa === '') return [false, 'Nie masz broni w dłoni.'];
        $kol_tr = 'bron_trwalosc'; $kol_max = 'bron_trwalosc_max';
        $dane = null;
        foreach (bronie_katalog() as $b) if ($b['nazwa'] === $nazwa) { $dane = $b; break; }
        if (!$dane) return [false, 'Tej broni nie ma w katalogu.'];
    } else {
        $nazwa = $g['pancerz_zalozony'] ?? '';
        if ($nazwa === '') return [false, 'Nie masz na sobie pancerza.'];
        $kol_tr = 'pancerz_trwalosc'; $kol_max = 'pancerz_trwalosc_max';
        $dane = null;
        foreach (eq_katalog_pancerzy() as $p) if ($p['nazwa'] === $nazwa) {
            // pancerze nie mają receptury — koszt szacujemy z obrony
            $dane = $p + ['poziom' => max(1, (int)round($p['obrona'] / 2)), 'stal' => 3, 'czesci' => 2];
            break;
        }
        if (!$dane) return [false, 'Tego pancerza nie ma w katalogu.'];
    }

    $tr  = (int)$g[$kol_tr];
    $max = (int)$g[$kol_max];
    if ($tr >= $max) return [false, 'Ten sprzęt jest w pełni sprawny.'];

    [$kasa, $stal, $czesci, $brak] = naprawa_koszt($dane, $tr, $max);
    if ((int)$g['gotowka'] < $kasa)
        return [false, "Naprawa kosztuje $kasa \$, masz {$g['gotowka']}."];
    if ((int)$g['zlom_stalowy'] < $stal || (int)$g['czesci_mechaniczne'] < $czesci)
        return [false, "Brakuje materiału: stal $stal, części $czesci."];

    // Warunek na stan trwałości, który wycenił koszt — dwa kliknięcia nie zapłacą dwa razy.
    $zrobione = db_zmien($db, "UPDATE gracze SET
        `$kol_tr` = `$kol_max`,
        gotowka = gotowka - ?,
        zlom_stalowy = zlom_stalowy - ?,
        czesci_mechaniczne = czesci_mechaniczne - ?
        WHERE id = ? AND `$kol_tr` = ? AND gotowka >= ? AND zlom_stalowy >= ? AND czesci_mechaniczne >= ?",
        [(int)$kasa, (int)$stal, (int)$czesci, $gid, $tr, (int)$kasa, (int)$stal, (int)$czesci]) === 1;
    if (!$zrobione) return [false, 'Stan się zmienił albo zabrakło środków — odśwież stronę.'];

    $s = $db->real_escape_string($sprzet);
    $n = $db->real_escape_string("stal $stal, części $czesci");
    $db->query("INSERT INTO naprawy_log (gracz_id, sprzet, z, na, koszt, komponent)
                VALUES ($gid, '$s', $tr, $max, $kasa, '$n')");

    $co = $sprzet === 'bron' ? 'Broń' : 'Pancerz';
    return [true, "$co jak nowy: $tr → $max. Poszło $kasa \$, $stal stali i $czesci części."];
}

/** Podgląd kosztu naprawy dla obu sztuk — do panelu w warsztacie i dokach. */
function naprawa_podglad(array $g): array {
    $out = [];
    foreach (bronie_katalog() as $b) if ($b['nazwa'] === ($g['bron_zalozona'] ?? '')) {
        [$k, $s, $c, $brak] = naprawa_koszt($b, (int)$g['bron_trwalosc'], (int)$g['bron_trwalosc_max']);
        $out['bron'] = ['nazwa'=>$b['nazwa'], 'tr'=>(int)$g['bron_trwalosc'], 'max'=>(int)$g['bron_trwalosc_max'],
                        'kasa'=>$k, 'stal'=>$s, 'czesci'=>$c, 'brak'=>$brak];
        break;
    }
    foreach (eq_katalog_pancerzy() as $p) if ($p['nazwa'] === ($g['pancerz_zalozony'] ?? '')) {
        $d = $p + ['poziom' => max(1, (int)round($p['obrona'] / 2)), 'stal' => 3, 'czesci' => 2];
        [$k, $s, $c, $brak] = naprawa_koszt($d, (int)$g['pancerz_trwalosc'], (int)$g['pancerz_trwalosc_max']);
        $out['pancerz'] = ['nazwa'=>$p['nazwa'], 'tr'=>(int)$g['pancerz_trwalosc'], 'max'=>(int)$g['pancerz_trwalosc_max'],
                           'kasa'=>$k, 'stal'=>$s, 'czesci'=>$c, 'brak'=>$brak];
        break;
    }
    return $out;
}

/* ── zlecenia ──────────────────────────────────────────────────────── */

/** Cennik Inżyniera: ile bierze za wytworzenie i za jeden stopień ulepszenia. */
function warsztat_cennik(mysqli $db, int $inz_id): array {
    $r = $db->query("SELECT kod, typ, cena FROM warsztat_cennik WHERE inzynier_id=$inz_id");
    $out = [];
    while ($r && $w = $r->fetch_assoc()) $out[$w['typ']][$w['kod']] = (int)$w['cena'];
    return $out;
}

function warsztat_ustaw_cene(mysqli $db, int $inz_id, string $kod, string $typ, int $cena): void {
    $k = $db->real_escape_string($kod);
    $t = in_array($typ, ['wytworz', 'ulepsz'], true) ? $typ : 'wytworz';
    $c = max(0, $cena);
    $db->query("INSERT INTO warsztat_cennik (inzynier_id, kod, typ, cena) VALUES ($inz_id, '$k', '$t', $c)
                ON DUPLICATE KEY UPDATE cena=$c");
}

/** Klient składa zlecenie i płaci z góry. Zwraca [ok, komunikat]. */
function zlecenie_zloz(mysqli $db, array $klient, int $inz_id, string $kod, string $typ): array {
    $kat = bronie_katalog();
    if (!isset($kat[$kod])) return [false, 'Nie ma takiej broni w katalogu.'];
    $cennik = warsztat_cennik($db, $inz_id);
    if (!isset($cennik[$typ][$kod]))
        return [false, 'Ten Inżynier nie ma tego w cenniku.'];

    $cena = (int)$cennik[$typ][$kod];
    $kid  = (int)$klient['id'];
    if ($typ === 'ulepsz' && eq_ma($db, $kid, $kod) < 1)
        return [false, 'Nie masz tej broni — nie ma czego ulepszać.'];
    if (!kasa_pobierz($db, $kid, $cena))
        return [false, "Brakuje gotówki. Cena: $cena \$."];

    db_q($db, "INSERT INTO warsztat_zlecenia (klient_id, inzynier_id, kod, typ, cena, status)
               VALUES (?, ?, ?, ?, ?, 'oczekuje')", [$kid, $inz_id, $kod, $typ, $cena]);
    return [true, "Zlecenie złożone. Zapłacono $cena \$ z góry."];
}

/**
 * Inżynier realizuje zlecenie. Materiały i energia idą z jego zapasów.
 * Porażka: zapłata wraca do klienta (Twoja decyzja), broń klienta spada o stopień.
 */
function zlecenie_wykonaj(mysqli $db, array $inz, int $zid): array {
    $iid = (int)$inz['id'];
    $r = $db->query("SELECT * FROM warsztat_zlecenia WHERE id=$zid AND inzynier_id=$iid AND status='oczekuje'");
    if (!$r || !$r->num_rows) return [false, 'Nie ma takiego otwartego zlecenia.'];
    $z = $r->fetch_assoc();
    $kid = (int)$z['klient_id'];

    // Zajmij zlecenie, zanim cokolwiek się wydarzy: drugie równoczesne kliknięcie dostanie 0 wierszy
    // i nie wypłaci drugi raz ani nie zwróci klientowi podwójnie.
    if (db_zmien($db, "UPDATE warsztat_zlecenia SET status='w_toku' WHERE id = ? AND inzynier_id = ? AND status='oczekuje'", [$zid, $iid]) !== 1)
        return [false, 'To zlecenie jest już realizowane.'];

    if ($z['typ'] === 'ulepsz') {
        [$ok, $tekst, $d] = warsztat_ulepsz($db, $inz, $z['kod'], $kid);
    } else {
        [$ok, $tekst, $d] = warsztat_wytworz($db, $inz, $z['kod']);
        if ($ok) {           // wytworzone trafia do klienta, także bonusowa druga sztuka
            $ile = max(1, (int)($d['ile'] ?? 1));
            $db->query("UPDATE ekwipunek_gracza SET ilosc = ilosc - $ile
                        WHERE gracz_id=$iid AND kod='".$db->real_escape_string($z['kod'])."'");
            eq_dodaj($db, $kid, $z['kod'], $ile);
        }
    }

    // Porażka bez rzutu (brak materiałów, energii, broni) zwraca pusty trzeci element — nic się nie
    // wydarzyło, więc zlecenie wraca do kolejki zamiast zwracać klientowi pieniądze.
    if (!$ok && empty($d)) {
        db_q($db, "UPDATE warsztat_zlecenia SET status='oczekuje' WHERE id = ?", [$zid]);
        return [false, $tekst];
    }

    if ($ok) {
        $db->query("UPDATE gracze SET gotowka = gotowka + {$z['cena']} WHERE id=$iid");
        $db->query("UPDATE warsztat_zlecenia SET status='wykonane', wynik='sukces', kiedy_zamkniete=NOW() WHERE id=$zid");
        return [true, "$tekst<br>Zlecenie rozliczone: +{$z['cena']} \$."];
    }

    // Zwrot zapłaty przy porażce.
    $db->query("UPDATE gracze SET gotowka = gotowka + {$z['cena']} WHERE id=$kid");
    $db->query("UPDATE warsztat_zlecenia SET status='wykonane', wynik='porazka', kiedy_zamkniete=NOW() WHERE id=$zid");
    return [false, "$tekst<br>Zapłata {$z['cena']} \$ wróciła do klienta."];
}

/** Otwarte zlecenia u danego Inżyniera — do panelu warsztatu. */
function zlecenia_otwarte(mysqli $db, int $inz_id): array {
    $r = $db->query("SELECT z.*, g.login AS klient
                     FROM warsztat_zlecenia z JOIN gracze g ON g.id = z.klient_id
                     WHERE z.inzynier_id=$inz_id AND z.status='oczekuje'
                     ORDER BY z.kiedy ASC");
    $out = [];
    while ($r && $w = $r->fetch_assoc()) $out[] = $w;
    return $out;
}
