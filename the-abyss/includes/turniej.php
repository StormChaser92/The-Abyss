<?php
/* the-abyss/includes/turniej.php
   Turniej gangów — wszyscy przeciw wszystkim, last man standing.

   Zasady ustalone z projektem:
     · kolejność tur po zręczności malejąco, wszyscy z pełnym HP
     · trzy akcje: atak w wybranego żywego, unik, przeczekanie
     · 30 sekund na ruch; brak kliknięcia liczy się jako unik, nie strata tury
     · obrażenia i celność jak w arenie: obrona procentowa K=50,
       random ograniczony bronią, clamp 2–98%
     · unik obniża szansę trafienia w ciebie o 20 p.p. do twojej następnej tury
     · przeczekanie oddaje 6% maksymalnego HP
     · widzowie obstawiają żetonami; pula dzielona proporcjonalnie do stawek
       między tych, którzy trafili, 5% zostaje w kasynie */

require_once __DIR__.'/bossowie.php';
require_once __DIR__.'/kasyno_core.php';

const TUR_SEKUND   = 30;
const TUR_UNIK_PP  = 20;    // ile punktów procentowych zdejmuje postawa obronna
const TUR_REGEN    = 0.06;  // ile HP oddaje przeczekanie
const TUR_PROWIZJA = 0.05;  // działka kasyna od puli zakładów
const TUR_ELIM_HP  = 10;

function tur_kolumny(): string {
    return "id, login, poziom, sila, zrecznosc, wytrzymalosc, walka_bronia, uniki,
            bonus_atak, bonus_obrona, bonus_szybkosc, bonus_unik, hp_max, syndykat_id";
}

function tur_turniej(mysqli $db, int $id): ?array {
    $r = $db->query("SELECT * FROM turnieje WHERE id=$id");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

/** Najbliższy turniej, który jeszcze żyje. */
function tur_biezacy(mysqli $db): ?array {
    $r = $db->query("SELECT * FROM turnieje WHERE stan IN ('ogloszony','zapisy','trwa')
                     ORDER BY poczatek ASC LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

function tur_zawodnicy(mysqli $db, int $tid): array {
    $out = [];
    $r = $db->query("SELECT z.*, g.login, g.poziom, g.zrecznosc, s.nazwa AS gang, s.tag
                     FROM turniej_zawodnicy z
                     JOIN gracze g ON g.id = z.gracz_id
                     LEFT JOIN syndykaty s ON s.id = z.syndykat_id
                     WHERE z.turniej_id=$tid ORDER BY z.kolejnosc ASC");
    while ($r && ($w = $r->fetch_assoc())) $out[] = $w;
    return $out;
}

function tur_zywi(array $zawodnicy): array {
    return array_values(array_filter($zawodnicy, fn($z) => $z['stan'] === 'zywy'));
}

function tur_log(mysqli $db, int $tid, int $tura, string $akcja, string $tresc,
                 ?int $gracz = null, ?int $cel = null, int $dmg = 0): void {
    $t = $db->real_escape_string($tresc);
    $a = $db->real_escape_string($akcja);
    $db->query("INSERT INTO turniej_log (turniej_id, tura, gracz_id, cel_id, akcja, dmg, tresc)
                VALUES ($tid, $tura, ".($gracz ?: 'NULL').", ".($cel ?: 'NULL').", '$a', $dmg, '$t')");
}

/* ── zapisy ────────────────────────────────────────────────────────── */

/** Zgłoszenie zawodnika. Wpisowe idzie ze skarbca gangu. */
function tur_zapisz(mysqli $db, array $g, array $t): array {
    if ($t['stan'] !== 'zapisy')  return [false, 'Zapisy są zamknięte.'];
    $syn = (int)$g['syndykat_id'];
    if ($syn <= 0)                return [false, 'Na turniej wychodzi się z gangiem za plecami.'];
    $tid = (int)$t['id'];
    $r = $db->query("SELECT id FROM turniej_zawodnicy WHERE turniej_id=$tid AND gracz_id={$g['id']}");
    if ($r && $r->num_rows)       return [false, 'Jesteś już na liście.'];

    require_once __DIR__.'/melina.php';
    [$ok, $blad] = melina_kasa($db, $syn, (int)$g['id'], -(int)$t['wpisowe'], 'Wpisowe: '.$t['nazwa']);
    if (!$ok) return [false, 'Skarbiec nie udźwignął wpisowego: '.$blad];

    $hp = max(1, (int)$g['hp_max']);
    $db->query("INSERT INTO turniej_zawodnicy (turniej_id, gracz_id, syndykat_id, hp, hp_max)
                VALUES ($tid, {$g['id']}, $syn, $hp, $hp)");
    $db->query("UPDATE turnieje SET pula = pula + ".(int)$t['wpisowe']." WHERE id=$tid");
    tur_log($db, $tid, 0, 'system', $g['login'].' staje na liście zawodników.', (int)$g['id']);
    return [true, 'Jesteś na liście. Wpisowe poszło ze skarbca.'];
}

/* ── start ─────────────────────────────────────────────────────────── */

/** Ustawia kolejność po zręczności i oddaje pierwszy ruch. */
function tur_start(mysqli $db, array $t): array {
    $tid = (int)$t['id'];
    $zaw = tur_zawodnicy($db, $tid);
    if (count($zaw) < 2) return [false, 'Do walki trzeba dwóch. Turniej odwołany.'];

    usort($zaw, fn($a, $b) => (int)$b['zrecznosc'] <=> (int)$a['zrecznosc']);
    foreach ($zaw as $i => $z)
        $db->query("UPDATE turniej_zawodnicy SET kolejnosc=$i, hp=hp_max, postawa='brak', stan='zywy'
                    WHERE id={$z['id']}");

    $pierwszy = (int)$zaw[0]['gracz_id'];
    $db->query("UPDATE turnieje SET stan='trwa', tura=1, aktywny_id=$pierwszy,
                ruch_do=DATE_ADD(NOW(), INTERVAL ".TUR_SEKUND." SECOND) WHERE id=$tid");
    tur_log($db, $tid, 1, 'system', 'Brama zamknięta. Na piasku staje '.count($zaw).' zawodników.');
    return [true, 'Turniej ruszył.'];
}

/* ── walka ─────────────────────────────────────────────────────────── */

/** Statystyki bojowe zawodnika, ściągane z gracze na czas akcji. */
function tur_staty(mysqli $db, int $gracz_id): ?array {
    $r = $db->query("SELECT ".tur_kolumny()." FROM gracze WHERE id=$gracz_id");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

/** Kto ma ruch po obecnym. Wraca na początek listy i podbija numer tury. */
function tur_nastepny(mysqli $db, array $t): void {
    $tid  = (int)$t['id'];
    $zaw  = tur_zawodnicy($db, $tid);
    $zywi = tur_zywi($zaw);

    if (count($zywi) <= 1) { tur_koniec($db, $t, $zywi[0] ?? null); return; }

    $obecny = (int)$t['aktywny_id'];
    $poz = -1;
    foreach ($zaw as $i => $z) if ((int)$z['gracz_id'] === $obecny) { $poz = $i; break; }

    $n = count($zaw);
    for ($k = 1; $k <= $n; $k++) {
        $kand = $zaw[($poz + $k) % $n];
        if ($kand['stan'] !== 'zywy') continue;
        $nowa_tura = (int)$t['tura'] + ((($poz + $k) >= $n) ? 1 : 0);
        $db->query("UPDATE turnieje SET aktywny_id={$kand['gracz_id']}, tura=$nowa_tura,
                    ruch_do=DATE_ADD(NOW(), INTERVAL ".TUR_SEKUND." SECOND) WHERE id=$tid");
        return;
    }
    tur_koniec($db, $t, $zywi[0] ?? null);
}

/**
 * Wykonanie akcji. $akcja: atak|unik|czekaj|pas.
 * Zwraca [ok, komunikat]. Pas wywołuje się sam po upływie 30 sekund.
 */
function tur_akcja(mysqli $db, array $t, int $gracz_id, string $akcja, int $cel_id = 0): array {
    $tid = (int)$t['id'];
    if ($t['stan'] !== 'trwa')                 return [false, 'Turniej nie trwa.'];
    if ((int)$t['aktywny_id'] !== $gracz_id)   return [false, 'To nie twoja tura.'];

    $zaw = tur_zawodnicy($db, $tid);
    $ja = null;
    foreach ($zaw as $z) if ((int)$z['gracz_id'] === $gracz_id) $ja = $z;
    if (!$ja || $ja['stan'] !== 'zywy')        return [false, 'Już nie walczysz.'];

    // Postawa obronna obowiązuje tylko do własnej następnej tury.
    $db->query("UPDATE turniej_zawodnicy SET postawa='brak' WHERE id={$ja['id']}");

    if ($akcja === 'unik' || $akcja === 'pas') {
        $db->query("UPDATE turniej_zawodnicy SET postawa='unik', pasy=pasy+".($akcja === 'pas' ? 1 : 0)."
                    WHERE id={$ja['id']}");
        tur_log($db, $tid, (int)$t['tura'], $akcja,
            $akcja === 'pas'
              ? $ja['login'].' zwleka — kryje się odruchowo.'
              : $ja['login'].' schodzi z linii i czeka na okazję.',
            $gracz_id);
        tur_nastepny($db, tur_turniej($db, $tid));
        return [true, 'Kryjesz się.'];
    }

    if ($akcja === 'czekaj') {
        $regen = max(1, (int)round($ja['hp_max'] * TUR_REGEN));
        $nowe  = min((int)$ja['hp_max'], (int)$ja['hp'] + $regen);
        $db->query("UPDATE turniej_zawodnicy SET hp=$nowe, postawa='czeka' WHERE id={$ja['id']}");
        tur_log($db, $tid, (int)$t['tura'], 'czekaj',
            $ja['login'].' łapie oddech i odzyskuje '.$regen.' HP.', $gracz_id, null, 0);
        tur_nastepny($db, tur_turniej($db, $tid));
        return [true, 'Łapiesz oddech.'];
    }

    if ($akcja !== 'atak') return [false, 'Nieznana akcja.'];

    $cel = null;
    foreach ($zaw as $z) if ((int)$z['gracz_id'] === $cel_id) $cel = $z;
    if (!$cel || $cel['stan'] !== 'zywy' || $cel_id === $gracz_id)
        return [false, 'Wybierz kogoś, kto jeszcze stoi.'];

    $sJa  = tur_staty($db, $gracz_id);
    $sCel = tur_staty($db, $cel_id);
    if (!$sJa || !$sCel) return [false, 'Brak danych zawodnika.'];

    $trafienie = oblicz_trafienie((int)$sJa['sila'], (float)$sJa['walka_bronia'],
                                  (int)$sJa['poziom'], (int)$sJa['bonus_atak']);
    $unik = zdolnosc_uniku($sCel);
    $szansa = boss_szansa($trafienie, $unik);
    if ($cel['postawa'] === 'unik') $szansa = max(2, $szansa - TUR_UNIK_PP);

    if (mt_rand(1, 10000) > $szansa * 100) {
        tur_log($db, $tid, (int)$t['tura'], 'atak',
            $sJa['login'].' uderza w powietrze — '.$sCel['login'].' zdążył się usunąć.',
            $gracz_id, $cel_id, 0);
        tur_nastepny($db, tur_turniej($db, $tid));
        return [true, 'Pudło.'];
    }

    $cap    = max(1, min(5 * (int)$sJa['poziom'], (int)$sJa['sila'] + (int)$sJa['bonus_atak']));
    $surowe = (int)$sJa['sila'] + (int)$sJa['bonus_atak'] + mt_rand(1, $cap);
    $dmg    = boss_po_obronie($surowe, (int)$sCel['wytrzymalosc'] + (int)$sCel['bonus_obrona']);
    $hp     = (int)$cel['hp'] - $dmg;

    $db->query("UPDATE turniej_zawodnicy SET hp=".max(0, $hp).", wziete=wziete+$dmg WHERE id={$cel['id']}");
    $db->query("UPDATE turniej_zawodnicy SET zadane=zadane+$dmg WHERE id={$ja['id']}");
    tur_log($db, $tid, (int)$t['tura'], 'atak',
        $sJa['login'].' trafia '.$sCel['login'].' za '.$dmg.' obrażeń.', $gracz_id, $cel_id, $dmg);

    if ($hp <= TUR_ELIM_HP) {
        $zywi_teraz = count(tur_zywi(tur_zawodnicy($db, $tid)));
        $db->query("UPDATE turniej_zawodnicy SET stan='wyeliminowany', hp=0, miejsce=$zywi_teraz
                    WHERE id={$cel['id']}");
        tur_log($db, $tid, (int)$t['tura'], 'system',
            $sCel['login'].' osuwa się na piasek. Zostaje '.($zywi_teraz - 1).'.', $cel_id);
    }

    tur_nastepny($db, tur_turniej($db, $tid));
    return [true, 'Trafienie za '.$dmg.'.'];
}

/** Wymuszenie pasu, gdy minęło 30 sekund. Wywoływane przy każdym odpytaniu. */
function tur_sprawdz_zegar(mysqli $db, array $t): void {
    if ($t['stan'] !== 'trwa' || !$t['ruch_do']) return;
    if (strtotime($t['ruch_do']) > time()) return;
    tur_akcja($db, $t, (int)$t['aktywny_id'], 'pas');
}

/* ── zakończenie ───────────────────────────────────────────────────── */

function tur_koniec(mysqli $db, array $t, ?array $zwyciezca): void {
    $tid = (int)$t['id'];
    $wid = $zwyciezca ? (int)$zwyciezca['gracz_id'] : 0;
    $syn = $zwyciezca ? (int)$zwyciezca['syndykat_id'] : 0;

    $db->query("UPDATE turnieje SET stan='zakonczony', aktywny_id=NULL, ruch_do=NULL,
                zwyciezca_id=".($wid ?: 'NULL').", zwyciezca_gang=".($syn ?: 'NULL')."
                WHERE id=$tid AND stan='trwa'");
    if ($zwyciezca) $db->query("UPDATE turniej_zawodnicy SET miejsce=1 WHERE id={$zwyciezca['id']}");

    // Pula wpisowego dla zwycięzcy.
    if ($wid) {
        $pula = (int)$t['pula'];
        if ($pula > 0) $db->query("UPDATE gracze SET gotowka=gotowka+$pula WHERE id=$wid");
        $z = $db->query("SELECT login FROM gracze WHERE id=$wid")->fetch_assoc();
        $login = $db->real_escape_string($z['login'] ?? '—');
        tur_log($db, $tid, (int)$t['tura'], 'system',
            $login.' zostaje sam na piasku i zabiera całą pulę.', $wid);

        $nazwa = $db->real_escape_string($t['nazwa']);
        $db->query("INSERT INTO wydarzenia (era_id, rodzaj, tytul, tresc) VALUES
            (".($t['era_id'] ? (int)$t['era_id'] : 'NULL').", 'walka_gangow',
             'Turniej rozstrzygnięty: $nazwa',
             '$login wychodzi z areny jako jedyny stojący. Pula wpisowego przechodzi na niego.')");
    }

    // Terytorium kraju przechodzi na gang zwycięzcy.
    if ($syn > 0) {
        $kr = $db->real_escape_string($t['kraj']);
        $mg = $db->real_escape_string($t['mg_login']);
        $db->query("UPDATE syndykat_terytoria SET `do`=NOW() WHERE kraj='$kr' AND `do` IS NULL");
        $db->query("INSERT INTO syndykat_terytoria (syndykat_id, kraj, nadal_mg, notatka)
                    VALUES ($syn, '$kr', '$mg', 'Zdobyte w turnieju gangów')");
    }

    tur_rozlicz_zaklady($db, $tid, $wid);
}

/** Pula zakładów dzielona proporcjonalnie do stawek, 5% zostaje w kasynie. */
function tur_rozlicz_zaklady(mysqli $db, int $tid, int $zwyciezca_id): void {
    $r = $db->query("SELECT COALESCE(SUM(stawka),0) s FROM turniej_zaklady WHERE turniej_id=$tid");
    $pula = (int)($r ? $r->fetch_assoc()['s'] : 0);
    if ($pula <= 0) return;

    $r = $db->query("SELECT COALESCE(SUM(stawka),0) s FROM turniej_zaklady
                     WHERE turniej_id=$tid AND na_kogo=$zwyciezca_id");
    $trafione = (int)($r ? $r->fetch_assoc()['s'] : 0);

    if ($trafione <= 0) {                    // nikt nie trafił — pula zostaje w kasynie
        $db->query("UPDATE turniej_zaklady SET rozliczony=1 WHERE turniej_id=$tid");
        return;
    }

    $do_podzialu = (int)floor($pula * (1 - TUR_PROWIZJA));
    $q = $db->query("SELECT * FROM turniej_zaklady WHERE turniej_id=$tid AND na_kogo=$zwyciezca_id");
    while ($q && ($z = $q->fetch_assoc())) {
        $wyplata = (int)floor($do_podzialu * ((int)$z['stawka'] / $trafione));
        $db->query("UPDATE turniej_zaklady SET wyplata=$wyplata, rozliczony=1 WHERE id={$z['id']}");
        if ($wyplata > 0)
            kc_kasa((int)$z['gracz_id'], 0, $wyplata, 'wyplata_turniej', 'turniej', $tid);
    }
    $db->query("UPDATE turniej_zaklady SET rozliczony=1 WHERE turniej_id=$tid AND rozliczony=0");
}

/* ── zakłady widzów ────────────────────────────────────────────────── */

function tur_postaw(mysqli $db, array $t, int $gracz_id, int $na_kogo, int $stawka): array {
    if (!in_array($t['stan'], ['zapisy','ogloszony'], true))
        return [false, 'Zakłady przyjmujemy tylko przed pierwszym dzwonkiem.'];
    if ($stawka < 100) return [false, 'Minimalny zakład to 100 żetonów.'];

    $tid = (int)$t['id'];
    $r = $db->query("SELECT id FROM turniej_zawodnicy WHERE turniej_id=$tid AND gracz_id=$na_kogo");
    if (!$r || !$r->num_rows) return [false, 'Ten zawodnik nie stanął na liście.'];
    $r = $db->query("SELECT id FROM turniej_zawodnicy WHERE turniej_id=$tid AND gracz_id=$gracz_id");
    if ($r && $r->num_rows)   return [false, 'Zawodnik nie obstawia własnej walki.'];
    $r = $db->query("SELECT id FROM turniej_zaklady WHERE turniej_id=$tid AND gracz_id=$gracz_id");
    if ($r && $r->num_rows)   return [false, 'Jeden zakład na widza.'];

    kc_kasa($gracz_id, 0, -$stawka, 'zaklad_turniej', 'turniej', $tid);
    $db->query("INSERT INTO turniej_zaklady (turniej_id, gracz_id, na_kogo, stawka)
                VALUES ($tid, $gracz_id, $na_kogo, $stawka)");
    return [true, 'Zakład przyjęty.'];
}

/** Pula zakładów i podział stawek — do tablicy kursów przy liście zawodników. */
function tur_kursy(mysqli $db, int $tid): array {
    $pula = 0; $na = [];
    $r = $db->query("SELECT na_kogo, SUM(stawka) s FROM turniej_zaklady WHERE turniej_id=$tid GROUP BY na_kogo");
    while ($r && ($w = $r->fetch_assoc())) { $na[(int)$w['na_kogo']] = (int)$w['s']; $pula += (int)$w['s']; }
    return ['pula' => $pula, 'na' => $na];
}
