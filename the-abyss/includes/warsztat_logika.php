<?php
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

        $db->query("UPDATE gracze SET
            zlom_stalowy = zlom_stalowy - {$b['stal']},
            czesci_mechaniczne = czesci_mechaniczne - {$b['czesci']},
            syntetyki = syntetyki - {$b['syn']},
            elektronika = elektronika - {$b['elek']},
            energia_aktualna = energia_aktualna - $en,
            exp = exp + $exp,
            umiejetnosc_inzynierii = umiejetnosc_inzynierii + $przyrost
            WHERE id = $gid");
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
        zlom_stalowy = zlom_stalowy - $ss, czesci_mechaniczne = czesci_mechaniczne - $sc,
        syntetyki = syntetyki - $sy, elektronika = elektronika - $se,
        energia_aktualna = energia_aktualna - ".max(1, (int)floor($en / 2)).",
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

    $db->query("UPDATE gracze SET
        zlom_stalowy = zlom_stalowy - $st_stal, czesci_mechaniczne = czesci_mechaniczne - $st_czes,
        syntetyki = syntetyki - $st_syn, elektronika = elektronika - $st_elek,
        energia_aktualna = energia_aktualna - $en,
        umiejetnosc_inzynierii = umiejetnosc_inzynierii + $przyrost
        WHERE id = $iid");

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
    if ((int)$klient['gotowka'] < $cena)
        return [false, "Brakuje gotówki. Cena: $cena \$."];
    if ($typ === 'ulepsz' && eq_ma($db, $kid, $kod) < 1)
        return [false, 'Nie masz tej broni — nie ma czego ulepszać.'];

    $k = $db->real_escape_string($kod);
    $db->query("UPDATE gracze SET gotowka = gotowka - $cena WHERE id=$kid");
    $db->query("INSERT INTO warsztat_zlecenia (klient_id, inzynier_id, kod, typ, cena, status)
                VALUES ($kid, $inz_id, '$k', '$typ', $cena, 'oczekuje')");
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
