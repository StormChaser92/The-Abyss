<?php
/* the-abyss/includes/melina.php
   Melina syndykatu — skarbiec, warka substancji, terytoria, pieczęcie.
   Bez echa i bez HTML.

   Ustalenia z projektu:
     · skarbiec: wpłaca każdy członek, wypłaca tylko ten, kto ma
       `syndykat_dostep_skarbiec` (syndykaty.php nadaje tę flagę liderowi)
     · warkę stawia wyłącznie Inżynier będący w gangu
     · warka trwa 6–12 h realnego czasu i może się nie udać
     · szansa: 80% bazy plus rzut na inteligencję Inżyniera, maks. 98%
     · składniki: odczynniki z rynku plus jedna Czarna Pieczęć z PvP
     · udana warka odblokowuje bossa tego kraju dla gangu */

require_once __DIR__.'/bossowie.php';

const WARKA_MIN_H   = 6;
const WARKA_MAX_H   = 12;
const WARKA_BAZA    = 80;    // stała szansa powodzenia w procentach
const PIECZEC_NAZWA = 'Czarna Pieczęć';
const PIECZEC_MAX   = 60.0;  // sufit szansy na drop, żeby duży gang nie zbierał ich seryjnie

/** Receptura: nazwa odczynnika => sztuk. Nazwy zgodne z rynek_odczynniki. */
function warka_receptura(): array {
    return [
        'Rozpuszczalnik R-9'   => 4,
        'Prekursor „Sól Vela”' => 3,
        'Katalizator irydowy'  => 2,
    ];
}

/* ── skarbiec ──────────────────────────────────────────────────────── */

function melina_saldo(mysqli $db, int $syn): int {
    $r = $db->query("SELECT skarbiec FROM syndykaty WHERE id=$syn");
    return (int)($r && $r->num_rows ? $r->fetch_assoc()['skarbiec'] : 0);
}

/** Ruch na skarbcu wraz z wpisem do rejestru. Zwraca [ok, komunikat]. */
function melina_kasa(mysqli $db, int $syn, int $gracz, int $kwota, string $powod): array {
    if ($kwota === 0) return [false, 'Zero to nie jest kwota.'];
    $db->query("START TRANSACTION");
    $r = $db->query("SELECT skarbiec FROM syndykaty WHERE id=$syn FOR UPDATE");
    if (!$r || !$r->num_rows) { $db->query("ROLLBACK"); return [false, 'Nie ma takiego syndykatu.']; }
    $saldo = (int)$r->fetch_assoc()['skarbiec'];
    if ($kwota < 0 && $saldo + $kwota < 0) { $db->query("ROLLBACK"); return [false, 'W skarbcu nie ma tyle.']; }
    $nowe = $saldo + $kwota;
    $p = $db->real_escape_string($powod);
    $db->query("UPDATE syndykaty SET skarbiec=$nowe WHERE id=$syn");
    $db->query("INSERT INTO syndykat_kasa (syndykat_id, gracz_id, kwota, saldo_po, powod)
                VALUES ($syn, $gracz, $kwota, $nowe, '$p')");
    $db->query("COMMIT");
    return [true, ''];
}

/* ── pieczęcie ─────────────────────────────────────────────────────── */

/** Suma punktów walki bronią całego gangu — z niej liczy się szansa dropu. */
function melina_wb_gangu(mysqli $db, int $syn): float {
    $r = $db->query("SELECT COALESCE(SUM(walka_bronia),0) s FROM gracze WHERE syndykat_id=$syn");
    return (float)($r ? $r->fetch_assoc()['s'] : 0);
}

function melina_szansa_pieczeci(float $wb_gangu): float {
    return min(PIECZEC_MAX, 0.01 * $wb_gangu);
}

function melina_pieczecie_wolne(mysqli $db, int $syn): int {
    $r = $db->query("SELECT COUNT(*) c FROM syndykat_pieczecie WHERE syndykat_id=$syn AND zuzyta_warka IS NULL");
    return (int)($r ? $r->fetch_assoc()['c'] : 0);
}

/**
 * Rzut na Czarną Pieczęć po wygranej walce PvP.
 * Wywołaj z walka_pvp.php po ustaleniu zwycięzcy:
 *   melina_rzut_na_pieczec($polaczenie, $ja, $on);
 * Liczy się tylko wygrana nad członkiem INNEGO syndykatu.
 * Zwraca true, jeśli pieczęć wypadła.
 */
function melina_rzut_na_pieczec(mysqli $db, array $zwyciezca, array $przegrany): bool {
    $syn = (int)($zwyciezca['syndykat_id'] ?? 0);
    $ich = (int)($przegrany['syndykat_id'] ?? 0);
    if ($syn <= 0 || $ich <= 0 || $syn === $ich) return false;

    $szansa = melina_szansa_pieczeci(melina_wb_gangu($db, $syn));
    if (mt_rand(1, 10000) > $szansa * 100) return false;

    $gid = (int)$zwyciezca['id'];
    $oid = (int)$przegrany['id'];
    $db->query("INSERT INTO syndykat_pieczecie (syndykat_id, gracz_id, ofiara_id, ofiara_gang)
                VALUES ($syn, $gid, $oid, $ich)");
    $n = $db->real_escape_string(PIECZEC_NAZWA);
    $ch = $db->query("SELECT id FROM przedmioty_gracze WHERE gracz_id=$gid AND nazwa='$n'");
    if ($ch && $ch->num_rows)
        $db->query("UPDATE przedmioty_gracze SET ilosc=ilosc+1 WHERE gracz_id=$gid AND nazwa='$n'");
    else
        $db->query("INSERT INTO przedmioty_gracze (gracz_id, nazwa, ilosc) VALUES ($gid, '$n', 1)");
    return true;
}

/* ── warka ─────────────────────────────────────────────────────────── */

function melina_warka_biezaca(mysqli $db, int $syn): ?array {
    $r = $db->query("SELECT * FROM syndykat_warki WHERE syndykat_id=$syn AND stan='warzy' ORDER BY id DESC LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

/** Szansa powodzenia: baza plus rzut na inteligencję Inżyniera. */
function melina_szansa_warki(int $inteligencja): int {
    return (int)min(98, WARKA_BAZA + min(18, (int)floor($inteligencja / 10)));
}

/** Ile sztuk danego przedmiotu ma gracz. */
function melina_ma_przedmiot(mysqli $db, int $gracz, string $nazwa): int {
    $n = $db->real_escape_string($nazwa);
    $r = $db->query("SELECT ilosc FROM przedmioty_gracze WHERE gracz_id=$gracz AND nazwa='$n'");
    return (int)($r && $r->num_rows ? $r->fetch_assoc()['ilosc'] : 0);
}

function melina_zdejmij_przedmiot(mysqli $db, int $gracz, string $nazwa, int $ile): void {
    $n = $db->real_escape_string($nazwa);
    $db->query("UPDATE przedmioty_gracze SET ilosc=ilosc-$ile WHERE gracz_id=$gracz AND nazwa='$n'");
    $db->query("DELETE FROM przedmioty_gracze WHERE gracz_id=$gracz AND nazwa='$n' AND ilosc<=0");
}

/** Czego brakuje graczowi do postawienia warki. Pusta tablica = komplet. */
function melina_braki(mysqli $db, int $gracz, int $syn): array {
    $braki = [];
    foreach (warka_receptura() as $nazwa => $ile) {
        $ma = melina_ma_przedmiot($db, $gracz, $nazwa);
        if ($ma < $ile) $braki[] = "$nazwa — masz $ma z $ile";
    }
    if (melina_pieczecie_wolne($db, $syn) < 1)
        $braki[] = PIECZEC_NAZWA.' — gang nie ma żadnej wolnej';
    return $braki;
}

/**
 * Postawienie warki. Zwraca [ok, komunikat].
 * Warunki: Inżynier, w gangu, w mieście, komplet składników, brak innej warki.
 */
function melina_start_warki(mysqli $db, array $g, string $kraj, string $miasto, int $era_id, string $platnosc): array {
    $syn = (int)$g['syndykat_id'];
    if ($syn <= 0)                       return [false, 'Warkę stawia się dla gangu, a ty w żadnym nie jesteś.'];
    if (($g['klasa'] ?? '') !== 'Inżynier') return [false, 'Nad kadzią musi stanąć Inżynier. Ty nim nie jesteś.'];
    if (melina_warka_biezaca($db, $syn)) return [false, 'Jedna warka naraz. Poczekaj, aż tamta dojdzie.'];

    $braki = melina_braki($db, (int)$g['id'], $syn);
    if ($braki) return [false, 'Brakuje składników: '.implode('; ', $braki).'.'];

    $koszt = 25000;                          // opłata za melinę, prąd i ciszę
    if ($platnosc === 'skarbiec') {
        [$ok, $blad] = melina_kasa($db, $syn, (int)$g['id'], -$koszt, 'Warka substancji');
        if (!$ok) return [false, $blad];
    } else {
        if ((int)$g['gotowka'] < $koszt) return [false, 'Nie masz przy sobie '.number_format($koszt, 0, '', ' ').' $.'];
        $db->query("UPDATE gracze SET gotowka=gotowka-$koszt WHERE id={$g['id']}");
    }

    foreach (warka_receptura() as $nazwa => $ile)
        melina_zdejmij_przedmiot($db, (int)$g['id'], $nazwa, $ile);

    $db->query("UPDATE syndykat_pieczecie SET zuzyta_warka=0
                WHERE syndykat_id=$syn AND zuzyta_warka IS NULL ORDER BY id ASC LIMIT 1");

    $godzin = mt_rand(WARKA_MIN_H, WARKA_MAX_H);
    $int    = (int)$g['inteligencja'];
    $szansa = melina_szansa_warki($int);
    $kr = $db->real_escape_string($kraj);
    $mi = $db->real_escape_string($miasto);
    $pl = $platnosc === 'skarbiec' ? 'skarbiec' : 'prywatnie';

    $db->query("INSERT INTO syndykat_warki
        (syndykat_id, era_id, kraj, miasto, inzynier_id, inteligencja, szansa, gotowa_o, zaplacil, koszt)
        VALUES ($syn, $era_id, '$kr', '$mi', {$g['id']}, $int, $szansa,
                DATE_ADD(NOW(), INTERVAL $godzin HOUR), '$pl', $koszt)");
    $wid = (int)$db->insert_id;
    $db->query("UPDATE syndykat_pieczecie SET zuzyta_warka=$wid WHERE syndykat_id=$syn AND zuzyta_warka=0");

    return [true, "Kadź stoi. Substancja dojdzie za $godzin h, szansa powodzenia $szansa%."];
}

/**
 * Odbiór warki: rzut na powodzenie. Udana odblokowuje bossa kraju.
 * Zwraca [ok, komunikat, udana].
 */
function melina_odbierz_warke(mysqli $db, array $g, array $warka): array {
    $syn = (int)$g['syndykat_id'];
    if ($syn !== (int)$warka['syndykat_id']) return [false, 'To nie jest warka twojego gangu.', false];
    if (strtotime($warka['gotowa_o']) > time()) return [false, 'Jeszcze się warzy.', false];

    $udana = mt_rand(1, 100) <= (int)$warka['szansa'];
    $wid   = (int)$warka['id'];
    $stan  = $udana ? 'udana' : 'zepsuta';
    $db->query("UPDATE syndykat_warki SET stan='$stan', odebral_id={$g['id']}, odebrana=NOW() WHERE id=$wid");
    $db->query("UPDATE syndykaty SET ".($udana ? 'warki_udane=warki_udane+1' : 'warki_zepsute=warki_zepsute+1')." WHERE id=$syn");

    if (!$udana)
        return [true, 'Kadź poszła w gęstą pianę i cała warka nadaje się do kanału. Pieczęć przepadła razem z nią.', false];

    $kraj   = (string)$warka['kraj'];
    $era_id = (int)$warka['era_id'];
    $boss   = boss_kraju($db, $kraj, $era_id);
    if (!$boss)
        return [true, 'Substancja gotowa. W tym kraju nie ma jednak żadnej legendy do wywabienia.', true];

    boss_zapewnij_stan($db, (int)$boss['id'], $era_id);
    $boss = boss_kraju($db, $kraj, $era_id);

    if ($boss['stan'] === 'zamkniety') {
        $s = $db->query("SELECT nazwa FROM syndykaty WHERE id=$syn");
        $nazwa = $db->real_escape_string($s && $s->num_rows ? $s->fetch_assoc()['nazwa'] : 'syndykat');
        $db->query("UPDATE boss_stan SET stan='gang', syndykat_id=$syn, odblokowany=NOW(),
                    powod='Pierwsza warka substancji w kraju'
                    WHERE era_id=$era_id AND boss_id={$boss['id']}");
        $t = $db->real_escape_string($boss['imie'].' „'.$boss['ksywa'].'”');
        $db->query("INSERT INTO wydarzenia (era_id, rodzaj, tytul, tresc) VALUES
            ($era_id, 'boss_otwarty', 'Prawo pierwszeństwa: $nazwa',
             'Pierwsza warka substancji w kraju wypłynęła na ulicę. $t zgodził się przyjąć wyzwanie — wyłącznie od ludzi $nazwa. Każdy egzekutor gangu ma jedną próbę.')");
        return [true, 'Substancja gotowa. Wieść niesie się szybciej niż towar — '.$boss['ksywa'].' czeka na waszego człowieka.', true];
    }

    return [true, 'Substancja gotowa, ale prawo do tej walki jest już rozdane.', true];
}

/* ── terytoria ─────────────────────────────────────────────────────── */

function melina_terytoria(mysqli $db, int $syn): array {
    $out = [];
    $r = $db->query("SELECT * FROM syndykat_terytoria WHERE syndykat_id=$syn AND `do` IS NULL ORDER BY kraj ASC");
    while ($r && ($w = $r->fetch_assoc())) $out[] = $w;
    return $out;
}

function melina_kto_trzyma(mysqli $db, string $kraj): ?array {
    $k = $db->real_escape_string($kraj);
    $r = $db->query("SELECT t.*, s.nazwa, s.tag FROM syndykat_terytoria t
                     JOIN syndykaty s ON s.id = t.syndykat_id
                     WHERE t.kraj='$k' AND t.`do` IS NULL ORDER BY t.od ASC LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}
