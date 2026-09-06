<?php
/* the-abyss/includes/arena_walka.php
   Arena (doki.php) — własny model walki, niezależny od PvP i bossów.
   bossowie.php i walka_pvp.php zostają na starych wzorach; ten plik nic im nie nadpisuje,
   dlatego wszystkie funkcje mają prefiks arena_ i nie ma tu function_exists.

   Model (ustalenia):
     trafienie   = siła + broń(bonus_atak) + random(1; 5·lvl)          [rzut nr 1]
     szansa      = ((log10(trafienie+200) − log10(unik_wroga+200))·500)+50, clamp 2–98
     obrażenia   = siła + broń + WB + random(1; 5·lvl)                 [rzut nr 2, osobny]
                   − obrona wroga (płasko), × mnożnik ciosu vs typ
     100 rund, timeout = remis (EXP × udział zadanych obrażeń, bez gotówki)
     sufit 100 małych na walkę, osobno dla uników i dla WB
     auto-apteczka poniżej 30% HP, jeśli gracz włączył ją przed sesją
     gotówka z NPC ×2 wobec starych stawek, brak łupu (komponenty: Szabrownik i bossowie) */

const ARENA_RUNDY         = 100;
const ARENA_SUFIT_MALYCH  = 100;   // na walkę, osobno uniki i WB
const ARENA_TRWALOSC_MAX  = 100;   // walk na sztukę sprzętu
const ARENA_APTECZKA_PROG = 0.30;  // auto-apteczka poniżej 30% HP
const ARENA_KASA_MNOZNIK  = 2.0;

/* ── typy przeciwników i kategorie ciosu ───────────────────────────── */

/** Trzy typy zamiast biologiczny/opancerzony/cybernetyczny. */
function arena_typy(): array {
    return [
        'cywil'      => ['nazwa' => 'Cywil',      'opis' => 'Bez ochrony ciała. Ostrze wchodzi bez oporu.'],
        'bykowaty'   => ['nazwa' => 'Bykowaty',   'opis' => 'Masa i gruba odzież. Tępe narzędzie robi swoje, ostrze się zsuwa.'],
        'ochroniarz' => ['nazwa' => 'Ochroniarz', 'opis' => 'Kamizelka i trening. Tylko broń palna daje przewagę.'],
    ];
}

/** Kategorie ciosu. Goła pięść nie ma kary — po prostu nigdy nie ma bonusu. */
function arena_ciosy(): array {
    return [
        'ostrze' => ['nazwa' => 'Ostrze',         'silny' => 'cywil'],
        'tepe'   => ['nazwa' => 'Tępe narzędzie', 'silny' => 'bykowaty'],
        'palna'  => ['nazwa' => 'Broń palna',     'silny' => 'ochroniarz'],
        'piesc'  => ['nazwa' => 'Goła pięść',     'silny' => null],
    ];
}

/** ×1,5 gdy cios trafia w swój typ, w pozostałych razach ×1,0. */
function arena_mnoznik_ciosu(string $cios, string $typ): float {
    $c = arena_ciosy()[$cios] ?? null;
    return ($c && $c['silny'] === $typ) ? 1.5 : 1.0;
}

/** Który cios opłaca się przeciw danemu typowi — do bestiariusza. */
function arena_rekomendowany_cios(string $typ): string {
    foreach (arena_ciosy() as $k => $c) if ($c['silny'] === $typ) return $k;
    return 'piesc';
}

/** Kraj miasta z config/miasta.php. Klucze $MIASTA_DANE są WIELKIMI literami. */
function arena_miasto_kraj(?string $miasto, string $domyslny = 'USA'): string {
    global $MIASTA_DANE;
    if (!$miasto || !is_array($MIASTA_DANE ?? null)) return $domyslny;
    $k = mb_strtoupper(trim($miasto));
    if (isset($MIASTA_DANE[$k]['kraj'])) return $MIASTA_DANE[$k]['kraj'];
    foreach ($MIASTA_DANE as $nazwa => $d)          // tolerancja dla zapisu z bazy
        if (mb_strtoupper($nazwa) === $k) return $d['kraj'] ?? $domyslny;
    return $domyslny;
}

/** Nazwa kategorii ciosu do wyświetlenia — używana też przez doki.php. */
function arena_nazwa_ciosu(string $cios): string {
    return ['ostrze'=>'Ostrze', 'tepe'=>'Tępe narzędzie', 'palna'=>'Broń palna', 'piesc'=>'Goła pięść'][$cios] ?? 'Goła pięść';
}

/* ── progi umiejętności ────────────────────────────────────────────── */

function arena_prog_dla_skilla(float $skill): int {
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

/** Małe → duże. Sufit 100 małych na walkę stoi PRZED tą funkcją, nie w niej. */
function arena_konwertuj(int $male, float $obecny_skill): array {
    $przyrost = 0.0;
    while (true) {
        $prog = arena_prog_dla_skilla($obecny_skill + $przyrost);
        if ($male >= $prog) { $male -= $prog; $przyrost += 0.1; } else break;
    }
    return [$male, round($przyrost, 3)];
}

/* ── trafienie, unik, obrażenia ────────────────────────────────────── */

/** Rzut nr 1. Bez WB — umiejętność „walka bronią" siedzi w obrażeniach. */
function arena_trafienie(array $g, ?int $rzut = null): float {
    $lvl = (int)$g['poziom'];
    return (int)$g['sila'] + (int)$g['bonus_atak'] + ($rzut ?? mt_rand(1, 5 * max(1, $lvl)));
}

/** Zdolność uniku gracza — pięć składników. */
function arena_zdolnosc_uniku(array $g): float {
    return (float)$g['uniki']
         + (int)$g['zrecznosc']             * 0.45
         + (int)($g['bonus_szybkosc'] ?? 0) * 0.45
         + (int)($g['bonus_unik'] ?? 0)
         + (int)$g['poziom'];
}

function arena_szansa(float $trafienie, float $unik_celu): float {
    $s = ((log10($trafienie + 200) - log10($unik_celu + 200)) * 500) + 50;
    return max(2, min(98, $s));
}

/** Rzut nr 2, osobny od trafienia. WB dodaje się jak siła. */
function arena_obrazenia_surowe(array $g, ?int $rzut = null): float {
    $lvl = (int)$g['poziom'];
    return (int)$g['sila'] + (int)$g['bonus_atak'] + (float)$g['walka_bronia']
         + ($rzut ?? mt_rand(1, 5 * max(1, $lvl)));
}

function arena_po_obronie(float $surowe, int $obrona): int {
    return max(1, (int)round($surowe - $obrona));
}

/* ── trwałość sprzętu ──────────────────────────────────────────────── */

function arena_sprzet_dziala(?int $t): bool { return $t === null || $t > 0; }

/** Bonusy przefiltrowane przez trwałość. Zwraca [bonus_atak, bonus_obrona]. */
function arena_bonusy_sprzetu(array $g): array {
    return [
        arena_sprzet_dziala(isset($g['bron_trwalosc'])    ? (int)$g['bron_trwalosc']    : null) ? (int)$g['bonus_atak']   : 0,
        arena_sprzet_dziala(isset($g['pancerz_trwalosc']) ? (int)$g['pancerz_trwalosc'] : null) ? (int)$g['bonus_obrona'] : 0,
    ];
}

function arena_zuzyj_sprzet(mysqli $db, int $gid, int $walk): array {
    $r = $db->query("SELECT bron_trwalosc, pancerz_trwalosc FROM gracze WHERE id=$gid");
    if (!$r || !$r->num_rows) return [null, null, []];
    $t = $r->fetch_assoc();
    $bron = max(0, (int)$t['bron_trwalosc'] - $walk);
    $panc = max(0, (int)$t['pancerz_trwalosc'] - $walk);
    $db->query("UPDATE gracze SET bron_trwalosc=$bron, pancerz_trwalosc=$panc WHERE id=$gid");
    $zepsute = [];
    if ($bron === 0 && (int)$t['bron_trwalosc'] > 0)    $zepsute[] = 'broń';
    if ($panc === 0 && (int)$t['pancerz_trwalosc'] > 0) $zepsute[] = 'zbroja';
    return [$bron, $panc, $zepsute];
}

/* ── role NPC ──────────────────────────────────────────────────────── */

/* Rola definiuje DOCELOWE PROCENTY, nie mnożniki statów — staty są w skali
   logarytmicznej, więc mnożenie ich wprost daje 2% albo 98% zamiast profilu.
     szansa_ja  — jak często gracz trafia tego wroga
     szansa_on  — jak często wróg trafia gracza
     obrona_ul  — obrona jako udział idealnej siły ataku
     hp_mult    — długość walki: rundy ≈ 66,7 · hp_mult (przy rekomendowanym ciosie)
     dmg_ul     — jaki udział HP gracza zabiera cała walka
     exp_mult   — mnożnik nagrody względem zbalansowanego

   Arytmetyka farmienia (i powód, dla którego remis istnieje):
     małe uniki = rundy × (1 − szansa_on)   → farmi wróg, który atakuje długo i pudłuje
     małe WB    = rundy × szansa_ja         → farmi wróg, którego trafiasz i który żyje
   Sufit 100 małych osiąga się tylko w walce na pełne 100 rund, czyli zwykle w remisie. */
function arena_role(): array {
    return [
        'zbalansowany' => ['nazwa'=>'Zbalansowany',     'szansa_ja'=>35,'szansa_on'=>35,'obrona_ul'=>0.50,'hp_mult'=>1.00,'dmg_ul'=>0.40,'exp_mult'=>1.00,
                           'farmi'=>'wszystko po trochu',
                           'opis'=>'Bez wyraźnej przewagi. Arena startowa i sprawdzenie buildu.'],
        'worek'        => ['nazwa'=>'Worek treningowy', 'szansa_ja'=>60,'szansa_on'=>50,'obrona_ul'=>0.42,'hp_mult'=>1.50,'dmg_ul'=>0.30,'exp_mult'=>1.30,
                           'farmi'=>'walka bronią',
                           'opis'=>'Stoi w miejscu i przyjmuje wszystko. Trafiasz go w 60% na dystansie stu rund, więc daje najwięcej małych WB.'],
        'szczur'       => ['nazwa'=>'Szczur',           'szansa_ja'=>18,'szansa_on'=>12,'obrona_ul'=>0.30,'hp_mult'=>1.50,'dmg_ul'=>0.30,'exp_mult'=>1.30,
                           'farmi'=>'uniki',
                           'opis'=>'Rzuca się bez przerwy i prawie zawsze pudłuje. Trudny do trafienia, więc walka trwa całe sto rund — i tyle samo prób uniku.'],
        'pancerz'      => ['nazwa'=>'Pancerz',          'szansa_ja'=>30,'szansa_on'=>30,'obrona_ul'=>0.65,'hp_mult'=>1.40,'dmg_ul'=>0.40,'exp_mult'=>1.60,
                           'farmi'=>'EXP na energię',
                           'opis'=>'Obrona zjada dwie trzecie ciosu. Bez ulepszonej broni nie zdążysz w sto rund i skończysz remisem.'],
        'zabojca'      => ['nazwa'=>'Zabójca',          'szansa_ja'=>45,'szansa_on'=>50,'obrona_ul'=>0.38,'hp_mult'=>0.50,'dmg_ul'=>0.70,'exp_mult'=>1.80,
                           'farmi'=>'EXP i ryzyko',
                           'opis'=>'Pada w trzydzieści rund, ale zabiera przy tym większość twojego zdrowia. Najwyższy EXP za minutę walki.'],
    ];
}

/* ── generator statystyk NPC ───────────────────────────────────────── */
/* Punkt zaczepienia: „idealna siła ataku" z Twojego wzoru, odwróconego:
     idealna = HP/(rundy·szansa) + obrona   ⇒   HP = rundy · szansa · (idealna − obrona)
   Projektujemy na szansę 35% w obie strony i obronę = 50% idealnej siły.
   idealna(lvl) ≈ 15,5·lvl + 20 (siła 8·lvl+20, broń 5·lvl, WB 0,6·lvl, średni random). */

const ARENA_CEL_RUNDY  = 100;     // na tyle rund projektowane jest HP
const ARENA_CIOS_MULT  = 1.5;     // projekt zakłada rekomendowany cios vs typ

function arena_idealna_sila(int $lvl): float {
    return 15.5 * $lvl + 20.5;    // siła + broń + WB + średni random(1;5·lvl)
}

/** Zdolność uniku referencyjnego gracza poziomu L — potrzebna do celności NPC. */
function arena_unik_referencyjny(int $lvl): float {
    return 0.6 * $lvl + (4 * $lvl + 10) * 0.45 + $lvl;
}

/** Odwrócenie wzoru na szansę — jaki UNIK celu daje zadany procent trafień atakującego. */
function arena_stat_dla_szansy(float $atakujacy, float $procent): float {
    $r = pow(10, (50 - $procent) / 500);          // (cel+200)/(atakujący+200)
    return max(1, round($r * ($atakujacy + 200) - 200));
}

/** Druga strona: jaka CELNOŚĆ atakującego daje zadany procent trafień w dany unik.
    Na niskich poziomach wychodzi ujemna — wówczas clamp na 1 i realny procent jest
    wyższy od docelowego (przy poziomie 1 nie da się zejść poniżej ~43%). */
function arena_celnosc_dla_szansy(float $unik_celu, float $procent): float {
    $r = pow(10, ($procent - 50) / 500);          // (atakujący+200)/(cel+200)
    return max(1, round($r * ($unik_celu + 200) - 200));
}

function arena_generuj_wroga(int $lvl, string $rola, string $typ): array {
    $p = arena_role()[$rola] ?? arena_role()['zbalansowany'];

    $idealna = arena_idealna_sila($lvl);
    $obrona  = round($idealna * $p['obrona_ul']);
    $netto   = max(1, ($idealna - $obrona) * ARENA_CIOS_MULT);       // średni cios rekomendowaną bronią
    $hp      = round(ARENA_CEL_RUNDY * ($p['szansa_ja'] / 100) * $netto * $p['hp_mult'] / ARENA_CIOS_MULT);

    $unik    = arena_stat_dla_szansy($idealna, $p['szansa_ja']);
    $celnosc = arena_celnosc_dla_szansy(arena_unik_referencyjny($lvl), $p['szansa_on']);

    // Obrażenia NPC: cała walka zabiera dmg_ul zdrowia gracza.
    // hp_gracza ≈ 50·lvl+100 — jedyne założenie do potwierdzenia w plikach postaci.
    $rundy    = ARENA_CEL_RUNDY * $p['hp_mult'] / ARENA_CIOS_MULT;
    $trafienia= max(1, $rundy * $p['szansa_on'] / 100);
    $netto_cios = $p['dmg_ul'] * (50 * $lvl + 100) / $trafienia;
    $atak = round((13 * $lvl + 20) + $netto_cios - (1 + 5 * $lvl) / 2);

    $waga = (float)$p['exp_mult'];

    return [
        'poziom'  => $lvl,
        'rola'    => $rola,
        'typ'     => $typ,
        'hp'      => (int)max(10, $hp),
        'atak'    => (int)max(1, $atak),
        'obrona'  => (int)max(0, $obrona),
        'celnosc' => (int)max(1, $celnosc),
        'unik'    => (int)max(1, $unik),
        'exp'     => (int)round(12 * $lvl * $waga),
        'kasa'    => (int)round(35 * ARENA_KASA_MNOZNIK * $lvl * $waga),
    ];
}

/* ── walka ─────────────────────────────────────────────────────────── */

/**
 * Jedna walka. $mod: cios, auto_apteczka, hp_start, apteczki, bonusy pochodzenia.
 * Zwraca wynik ('wygrana'|'remis'|'przegrana'), liczniki i dziennik rund.
 */
function arena_walka(array $g, array $wrog, array $mod = []): array {
    $cios     = $mod['cios'] ?? 'piesc';
    $auto     = !empty($mod['auto_apteczka']);
    $apteczki = (int)($mod['apteczki'] ?? 0);
    $hp_max   = (int)($g['hp_max'] ?? $g['hp_aktualne']);
    $hp       = (int)($mod['hp_start'] ?? $g['hp_aktualne']);

    [$bonus_a, $bonus_o] = arena_bonusy_sprzetu($g);
    $gg = $g; $gg['bonus_atak'] = $bonus_a;
    $lvl = (int)$g['poziom'];
    $mn_cios = arena_mnoznik_ciosu($cios, $wrog['typ'] ?? 'cywil');
    $obrona  = (int)$g['wytrzymalosc'] + $bonus_o;
    $unik_g  = arena_zdolnosc_uniku($g);
    $unik_abs= (float)($mod['bonus_unik_abs'] ?? 0);

    $w_hp = (int)$wrog['hp'];
    $male_un = 0; $male_wb = 0; $zadany = 0; $wziety = 0; $uzyte_apteczki = 0;
    $suma_moj = 0.0; $suma_jego = 0.0; $dziennik = []; $runda = 1;

    while ($runda <= ARENA_RUNDY && $w_hp > 0 && $hp > 10) {
        // ── mój atak: rzut nr 1 na trafienie
        $traf     = arena_trafienie($gg);
        $moj_hit  = arena_szansa($traf, (float)$wrog['unik']);
        $suma_moj += $moj_hit;
        if (mt_rand(1, 10000) <= $moj_hit * 100) {
            // rzut nr 2, osobny — obrażenia z WB
            $dmg = (int)round(arena_po_obronie(arena_obrazenia_surowe($gg), (int)$wrog['obrona']) * $mn_cios);
            if ($cios === 'melee' || $cios === 'ostrze' || $cios === 'tepe' || $cios === 'piesc')
                $dmg = (int)round($dmg * (float)($mod['dmg_melee_mult'] ?? 1.0));
            if ($runda === 1) $dmg = (int)round($dmg * (float)($mod['dmg_pierwsza_mult'] ?? 1.0));
            $dmg = max(1, $dmg);
            $w_hp -= $dmg; $zadany += $dmg;
            if ($male_wb < ARENA_SUFIT_MALYCH) $male_wb++;        // sufit 100 na walkę
            $dziennik[] = ['r'=>$runda,'typ'=>'trafienie','dmg'=>$dmg,'w_hp'=>max(0,$w_hp),'hp'=>$hp];
        } else {
            $dziennik[] = ['r'=>$runda,'typ'=>'pudlo','dmg'=>0,'w_hp'=>max(0,$w_hp),'hp'=>$hp];
        }
        if ($w_hp <= 0) break;

        // ── jego atak
        $jego_hit = max(2, arena_szansa((float)$wrog['celnosc'], $unik_g) - $unik_abs);
        $suma_jego += $jego_hit;
        if (mt_rand(1, 10000) <= $jego_hit * 100) {
            $surowe = (int)$wrog['atak'] + mt_rand(1, 5 * max(1, (int)$wrog['poziom']));
            $dmg_w  = max(1, arena_po_obronie($surowe, $obrona));
            $hp -= $dmg_w; $wziety += $dmg_w;
            $dziennik[] = ['r'=>$runda,'typ'=>'rana','dmg'=>$dmg_w,'w_hp'=>max(0,$w_hp),'hp'=>max(0,$hp)];
        } else {
            if ($male_un < ARENA_SUFIT_MALYCH) $male_un++;        // sufit 100 na walkę
            $dziennik[] = ['r'=>$runda,'typ'=>'unik','dmg'=>0,'w_hp'=>max(0,$w_hp),'hp'=>$hp];
        }

        // ── auto-apteczka poniżej 30% HP
        if ($auto && $apteczki > 0 && $hp > 10 && $hp < $hp_max * ARENA_APTECZKA_PROG) {
            $apteczki--; $uzyte_apteczki++;
            $hp = min($hp_max, $hp + (int)round($hp_max * 0.5));
            $dziennik[] = ['r'=>$runda,'typ'=>'apteczka','dmg'=>0,'w_hp'=>max(0,$w_hp),'hp'=>$hp];
        }
        $runda++;
    }

    $wynik = $w_hp <= 0 ? 'wygrana' : ($hp <= 10 ? 'przegrana' : 'remis');
    $rund_faktycznie = min(ARENA_RUNDY, max(1, $runda - ($wynik === 'remis' ? 1 : 0)));

    return [
        'wynik'=>$wynik, 'rundy'=>$rund_faktycznie,
        'dmg_zadany'=>$zadany, 'dmg_wziety'=>$wziety,
        'hp_koniec'=>max(1, $hp), 'w_hp'=>max(0, $w_hp),
        'male_un'=>$male_un, 'male_wb'=>$male_wb,
        'apteczki_uzyte'=>$uzyte_apteczki, 'apteczki_zostalo'=>$apteczki,
        'moj_hit'=>round($suma_moj / max(1, $rund_faktycznie), 1),
        'jego_hit'=>round($suma_jego / max(1, $rund_faktycznie), 1),
        'unik'=>round($unik_g, 1), 'mnoznik_ciosu'=>$mn_cios,
        'dziennik'=>$dziennik,
    ];
}

/** Nagroda. Remis: EXP × udział obrażeń, bez gotówki. Łupu nie ma wcale. */
function arena_nagroda(array $wrog, array $w): array {
    if ($w['wynik'] === 'przegrana') return ['exp'=>0, 'kasa'=>0, 'powod'=>'porazka'];
    if ($w['wynik'] === 'remis') {
        $udzial = max(0.0, min(1.0, $w['dmg_zadany'] / max(1, (int)$wrog['hp'])));
        return ['exp'=>(int)floor((int)$wrog['exp'] * $udzial), 'kasa'=>0,
                'powod'=>'remis', 'udzial'=>(int)round($udzial * 100)];
    }
    return [
        'exp'  => mt_rand((int)((int)$wrog['exp']  * 0.8), (int)((int)$wrog['exp']  * 1.2)),
        'kasa' => mt_rand((int)((int)$wrog['kasa'] * 0.8), (int)((int)$wrog['kasa'] * 1.2)),
        'powod'=> 'wygrana',
    ];
}

/** Ocena celu dla bestiariusza — wartości oczekiwane, bez rzutów. */
function arena_analiza(array $wrog, array $g, string $cios = 'piesc'): array {
    [$bonus_a, $bonus_o] = arena_bonusy_sprzetu($g);
    $gg = $g; $gg['bonus_atak'] = $bonus_a;
    $lvl = (int)$g['poziom'];
    $sr_rzut = (int)round((1 + 5 * max(1, $lvl)) / 2);

    $traf     = arena_trafienie($gg, $sr_rzut);
    $unik_g   = arena_zdolnosc_uniku($g);
    $moj_hit  = arena_szansa($traf, (float)$wrog['unik']);
    $jego_hit = arena_szansa((float)$wrog['celnosc'], $unik_g);
    $mn       = arena_mnoznik_ciosu($cios, $wrog['typ'] ?? 'cywil');

    $sr_dmg = (int)round(arena_po_obronie(arena_obrazenia_surowe($gg, $sr_rzut), (int)$wrog['obrona']) * $mn);
    $rundy  = $sr_dmg > 0 && $moj_hit > 0 ? (int)$wrog['hp'] / ($sr_dmg * $moj_hit / 100) : 999;
    $rundy_real = min(ARENA_RUNDY, $rundy);

    $jego_dmg = arena_po_obronie((int)$wrog['atak'] + (1 + 5 * max(1, (int)$wrog['poziom'])) / 2,
                                 (int)$g['wytrzymalosc'] + $bonus_o);
    $obiore = (int)round($rundy_real * $jego_hit / 100 * $jego_dmg);

    $uniki_est = min(ARENA_SUFIT_MALYCH, (int)round($rundy_real * (1 - $jego_hit / 100)));
    $wb_est    = min(ARENA_SUFIT_MALYCH, (int)round($rundy_real * $moj_hit / 100));

    if     ($rundy > ARENA_RUNDY)                    $ocena = ['remis',    'REMIS — nie zdążysz zabić'];
    elseif ($obiore >= (int)$g['hp_aktualne'] - 10)  $ocena = ['too_hard', 'Za trudny — padniesz'];
    elseif ($rundy < 8)                              $ocena = ['too_easy', 'Za łatwy'];
    elseif ($rundy < 30)                             $ocena = ['easy',     'Łatwy cel'];
    elseif ($rundy < 70)                             $ocena = ['good',     'Dobra farma'];
    else                                             $ocena = ['optimal',  '★ OPTYMALNA FARMA'];

    return [
        'moj_hit'=>round($moj_hit,1), 'jego_hit'=>round($jego_hit,1),
        'sr_dmg'=>$sr_dmg, 'rundy'=>round($rundy,1), 'obiore'=>$obiore,
        'uniki_est'=>$uniki_est, 'wb_est'=>$wb_est, 'mnoznik_ciosu'=>$mn,
        'idealna_sila'=>(int)round(arena_idealna_sila((int)$wrog['poziom'])),
        'rekomendowany_cios'=>arena_rekomendowany_cios($wrog['typ'] ?? 'cywil'),
        'ocena'=>$ocena,
    ];
}
