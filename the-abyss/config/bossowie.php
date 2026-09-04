<?php
/* the-abyss/includes/bossowie.php
   Bossowie krajowi — matematyka i stan. Bez echa i bez HTML.

   Zasady ustalone z projektem:
     · poziom 120, 48 rund, jedna próba na postać na erę, bez apteczek
     · obrona procentowa dmg × 50/(50 + obrona) w obie strony (jak PvP)
     · random obrażeń ograniczony bronią: rand(1, min(5·lvl, siła+broń))
     · clamp szansy trafienia 2–98%
     · wymagany sprzęt klasy V, obecność w kraju bossa
     · przegrana kosztuje szansę, nie postać — szpital jak przy arenie

   Funkcje wspólne z doki.php (prog_dla_skilla, konwertuj_male_na_duze)
   są tu powtórzone pod osłoną function_exists, żeby plik dał się
   włączyć niezależnie od tego, co jeszcze siedzi w żądaniu. */

const BOSS_RUNDY   = 48;
const BOSS_K       = 50;   // stała obrony procentowej, jak w walka_pvp.php
const BOSS_DNI     = 7;    // po tylu dniach boss otwiera się dla serwera
const BOSS_KLASA   = 5;    // wymagana klasa broni i zbroi
const BOSS_POZIOM_MIN = 110;

/* ── progi konwersji małych krytyków (tabela z projektu) ───────────── */
if (!function_exists('prog_dla_skilla')) {
    function prog_dla_skilla(float $skill): int {
        if ($skill <= 10)    return 25;
        if ($skill <= 20)    return 26;
        if ($skill <= 50)    return 27;
        if ($skill <= 100)   return 28;
        if ($skill <= 200)   return 29;
        if ($skill <= 500)   return 30;
        if ($skill <= 1000)  return 32;
        if ($skill <= 2000)  return 34;
        if ($skill <= 5000)  return 36;
        if ($skill <= 10000) return 38;
        if ($skill <= 20000) return 40;
        if ($skill <= 40000) return 45;
        if ($skill <= 50000) return 50;
        if ($skill <= 60000) return 53;
        return 56;
    }
}

if (!function_exists('konwertuj_male_na_duze')) {
    function konwertuj_male_na_duze(int $male, float $obecny_skill): array {
        $przyrost = 0.0;
        while (true) {
            $prog = prog_dla_skilla($obecny_skill + $przyrost);
            if ($male >= $prog) { $male -= $prog; $przyrost += 0.1; }
            else break;
        }
        return [$male, round($przyrost, 3)];
    }
}

if (!function_exists('oblicz_trafienie')) {
    function oblicz_trafienie(int $sila, float $wb, int $lvl, int $bonus_atak): float {
        return ((1/4) + ($lvl / 5800)) * $sila + $wb + $lvl + $bonus_atak;
    }
}

/** Szansa trafienia z clampem 2–98 — walka z bossem gra pełnym zakresem. */
function boss_szansa(float $trafienie, float $unik_celu): float {
    $s = ((log10($trafienie + 200) - log10($unik_celu + 200)) * 500) + 50;
    return max(2, min(98, $s));
}

/** Pełny wzór na zdolność uniku, z zręcznością, bronią i poziomem. */
function zdolnosc_uniku(array $g): float {
    return (float)$g['uniki']
         + (int)$g['zrecznosc']       * 0.45
         + (int)($g['bonus_szybkosc'] ?? 0) * 0.45
         + (int)($g['bonus_unik'] ?? 0)
         + (int)$g['poziom'];
}

/** Klasa sprzętu I–V z jego jedynego parametru (atak albo obrona). */
function klasa_sprzetu(int $wartosc): int {
    if ($wartosc >= 400) return 5;
    if ($wartosc >= 260) return 4;
    if ($wartosc >= 150) return 3;
    if ($wartosc >=  60) return 2;
    return 1;
}

function klasa_rzymska(int $k): string {
    return ['I','II','III','IV','V'][max(1, min(5, $k)) - 1];
}

/** Obrażenia po obronie procentowej. Zawsze co najmniej 1. */
function boss_po_obronie(float $surowe, int $obrona): int {
    return max(1, (int)round($surowe * BOSS_K / (BOSS_K + $obrona)));
}

/* ── stan ───────────────────────────────────────────────────────────── */

function boss_era(mysqli $db): ?array {
    $r = $db->query("SELECT * FROM ery WHERE zamknieta IS NULL ORDER BY id DESC LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

/** Boss kraju wraz ze stanem w bieżącej erze. */
function boss_kraju(mysqli $db, string $kraj, int $era_id): ?array {
    $kraj = $db->real_escape_string($kraj);
    $r = $db->query("SELECT b.*, s.id AS stan_id, s.stan, s.syndykat_id, s.odblokowany,
                            s.otwarty_od, s.zwyciezca_id, s.zwyciezca_gang, s.ubity, s.powod
                     FROM bossowie b
                     LEFT JOIN boss_stan s ON s.boss_id = b.id AND s.era_id = $era_id
                     WHERE b.kraj = '$kraj' LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

/** Wiersz stanu zakładany leniwie — nowy boss w nowej erze. */
function boss_zapewnij_stan(mysqli $db, int $boss_id, int $era_id): void {
    $r = $db->query("SELECT id FROM boss_stan WHERE era_id=$era_id AND boss_id=$boss_id");
    if (!$r || !$r->num_rows)
        $db->query("INSERT INTO boss_stan (era_id, boss_id, stan) VALUES ($era_id, $boss_id, 'zamkniety')");
}

/** Ilu egzekutorów gangu jeszcze nie zużyło próby na tego bossa. */
function boss_proby_zostaly(mysqli $db, int $boss_id, int $era_id, int $syndykat_id): int {
    $r = $db->query("SELECT COUNT(*) c FROM gracze g
                     WHERE g.syndykat_id = $syndykat_id
                       AND g.id NOT IN (SELECT gracz_id FROM boss_proby
                                        WHERE era_id=$era_id AND boss_id=$boss_id)");
    return (int)($r ? $r->fetch_assoc()['c'] : 0);
}

/** Przejście gang → otwarty: próby wyczerpane albo minęło 7 dni. */
function boss_moze_otworzyc(mysqli $db, array $boss, int $era_id): bool {
    if ($boss['stan'] !== 'gang') return false;
    if ($boss['odblokowany'] && (time() - strtotime($boss['odblokowany'])) >= BOSS_DNI * 86400)
        return true;
    return boss_proby_zostaly($db, (int)$boss['id'], $era_id, (int)$boss['syndykat_id']) === 0;
}

function boss_otworz_dla_serwera(mysqli $db, array $boss, int $era_id): void {
    $db->query("UPDATE boss_stan SET stan='otwarty', otwarty_od=NOW()
                WHERE era_id=$era_id AND boss_id={$boss['id']}");
    $t = $db->real_escape_string($boss['imie'].' „'.$boss['ksywa'].'”');
    $a = $db->real_escape_string($boss['arena']);
    $db->query("INSERT INTO wydarzenia (era_id, rodzaj, tytul, tresc) VALUES
        ($era_id, 'boss_otwarty', 'Arena otwarta: $t',
         'Gang, który odblokował $t, nie zdołał go położyć. $a przyjmuje teraz każdego egzekutora — jedna próba na postać.')");
}

/**
 * Czy gracz może stanąć do bossa. Zwraca [bool, powód].
 * Sprawdza po kolei: erę, stan bossa, obecność w kraju, próbę, sprzęt, HP i energię.
 */
function boss_wpuszcza(mysqli $db, array $g, array $boss, int $era_id): array {
    if ($boss['stan'] === 'ubity')
        return [false, 'Ten boss już padł w tej erze. Legenda ma tylko jednego pogromcę.'];
    if ($boss['stan'] === 'zamkniety' || !$boss['stan'])
        return [false, 'Nikt jeszcze nie zdobył prawa do tej walki.'];
    if ($boss['stan'] === 'gang' && (int)$g['syndykat_id'] !== (int)$boss['syndykat_id'])
        return [false, 'Prawo pierwszeństwa ma inny syndykat. Poczekaj, aż wyczerpie próby albo minie tydzień.'];
    if (($g['obecne_miasto'] ?? '') !== $boss['miasto'])
        return [false, 'Walka odbywa się na miejscu. Musisz przylecieć do właściwego miasta.'];

    $r = $db->query("SELECT id FROM boss_proby WHERE era_id=$era_id AND boss_id={$boss['id']} AND gracz_id={$g['id']}");
    if ($r && $r->num_rows)
        return [false, 'Zużyłaś już swoją jedyną próbę w tej erze. Gang może wystawić następnego.'];

    if ((int)$g['poziom'] < BOSS_POZIOM_MIN)
        return [false, 'Za nisko. Ochrona wpuszcza od poziomu '.BOSS_POZIOM_MIN.'.'];
    if (klasa_sprzetu((int)$g['bonus_atak']) < BOSS_KLASA)
        return [false, 'Twoja broń jest klasy '.klasa_rzymska(klasa_sprzetu((int)$g['bonus_atak'])).'. Potrzebujesz klasy V.'];
    if (klasa_sprzetu((int)$g['bonus_obrona']) < BOSS_KLASA)
        return [false, 'Twoja zbroja jest klasy '.klasa_rzymska(klasa_sprzetu((int)$g['bonus_obrona'])).'. Potrzebujesz klasy V.'];
    if ((int)$g['hp_aktualne'] < (int)$g['hp_max'])
        return [false, 'Na bossa wchodzi się w pełni sił. Wylecz się do maksimum.'];
    if ((int)$g['energia_aktualna'] < 10)
        return [false, 'Potrzebujesz 10 punktów energii.'];

    return [true, ''];
}

/**
 * Pełna walka: 48 rund, gracz atakuje pierwszy, bez leczenia.
 * Zwraca tablicę z wynikiem, dziennikiem rund i licznikami małych krytyków.
 */
function boss_walka(array $g, array $boss): array {
    $sila   = (int)$g['sila'];
    $atakB  = (int)$g['bonus_atak'];
    $lvl    = (int)$g['poziom'];
    $obrona = (int)$g['wytrzymalosc'] + (int)$g['bonus_obrona'];
    $hp     = (int)$g['hp_aktualne'];
    $hp0    = $hp;

    $trafienie = oblicz_trafienie($sila, (float)$g['walka_bronia'], $lvl, $atakB);
    $unik      = zdolnosc_uniku($g);

    $moja_szansa = boss_szansa($trafienie, (float)$boss['unik']);
    $jego_szansa = boss_szansa((float)$boss['celnosc'], $unik);

    $hp_boss   = (int)$boss['hp'];
    $cap       = max(1, min(5 * $lvl, $sila + $atakB));   // random ograniczony bronią
    $male_un   = 0;
    $male_wb   = 0;
    $zadany    = 0;
    $wziety    = 0;
    $dziennik  = [];
    $runda     = 1;

    while ($runda <= BOSS_RUNDY && $hp_boss > 0 && $hp > 10) {
        if (mt_rand(1, 10000) <= $moja_szansa * 100) {
            $surowe = $sila + $atakB + mt_rand(1, $cap);
            $dmg    = boss_po_obronie($surowe, (int)$boss['obrona']);
            $hp_boss -= $dmg;
            $zadany  += $dmg;
            $male_wb++;
            $dziennik[] = ['r'=>$runda, 'typ'=>'trafienie', 'dmg'=>$dmg, 'hp_boss'=>max(0,$hp_boss), 'hp'=>$hp];
        } else {
            $dziennik[] = ['r'=>$runda, 'typ'=>'pudlo', 'dmg'=>0, 'hp_boss'=>max(0,$hp_boss), 'hp'=>$hp];
        }
        if ($hp_boss <= 0) break;

        if (mt_rand(1, 10000) <= $jego_szansa * 100) {
            $surowe = (int)$boss['atak'] + mt_rand(1, 5 * (int)$boss['poziom']);
            $dmg    = boss_po_obronie($surowe, $obrona);
            $hp    -= $dmg;
            $wziety += $dmg;
            $dziennik[] = ['r'=>$runda, 'typ'=>'rana', 'dmg'=>$dmg, 'hp_boss'=>max(0,$hp_boss), 'hp'=>max(0,$hp)];
        } else {
            $male_un++;
            $dziennik[] = ['r'=>$runda, 'typ'=>'unik', 'dmg'=>0, 'hp_boss'=>max(0,$hp_boss), 'hp'=>$hp];
        }
        $runda++;
    }

    $wynik = $hp_boss <= 0 ? 'wygrana' : ($hp <= 10 ? 'przegrana' : 'remis');

    return [
        'wynik'      => $wynik,
        'rundy'      => min(BOSS_RUNDY, $runda),
        'dmg_zadany' => $zadany,
        'dmg_wziety' => $wziety,
        'hp_koniec'  => max(1, $hp),
        'hp_start'   => $hp0,
        'hp_boss'    => max(0, $hp_boss),
        'male_un'    => $male_un,
        'male_wb'    => $male_wb,
        'moja_szansa'=> round($moja_szansa, 1),
        'jego_szansa'=> round($jego_szansa, 1),
        'trafienie'  => round($trafienie, 1),
        'unik'       => round($unik, 1),
        'dziennik'   => $dziennik,
    ];
}

/** Prognoza pokazywana przed walką — te same wzory, bez rzutów kością. */
function boss_prognoza(array $g, array $boss): array {
    $sila  = (int)$g['sila'];
    $atakB = (int)$g['bonus_atak'];
    $lvl   = (int)$g['poziom'];
    $cap   = max(1, min(5 * $lvl, $sila + $atakB));

    $trafienie = oblicz_trafienie($sila, (float)$g['walka_bronia'], $lvl, $atakB);
    $unik      = zdolnosc_uniku($g);
    $moja      = boss_szansa($trafienie, (float)$boss['unik']);
    $jego      = boss_szansa((float)$boss['celnosc'], $unik);

    $moj_cios  = boss_po_obronie($sila + $atakB + ($cap + 1) / 2, (int)$boss['obrona']);
    $jego_cios = boss_po_obronie((int)$boss['atak'] + (1 + 5 * (int)$boss['poziom']) / 2,
                                 (int)$g['wytrzymalosc'] + (int)$g['bonus_obrona']);

    $zadam  = (int)round(BOSS_RUNDY * $moja / 100 * $moj_cios);
    $obiore = (int)round(BOSS_RUNDY * $jego / 100 * $jego_cios);

    return [
        'moja_szansa' => round($moja, 1),
        'jego_szansa' => round($jego, 1),
        'moj_cios'    => $moj_cios,
        'jego_cios'   => $jego_cios,
        'zadam'       => $zadam,
        'obiore'      => $obiore,
        'trafienie'   => round($trafienie, 1),
        'unik'        => round($unik, 1),
        'wystarczy'   => $zadam >= (int)$boss['hp'],
        'przezyje'    => $obiore < (int)$g['hp_max'],
    ];
}
