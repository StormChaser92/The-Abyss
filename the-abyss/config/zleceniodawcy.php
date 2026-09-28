<?php
/* the-abyss/config/zleceniodawcy.php — stałe Tablicy Zleceń i wczytywanie treści per region.
   Treść: config/zlecenia_ny.php, zlecenia_la.php, zlecenia_pl.php. Każdy plik zwraca:
     'npc'       => [id => ['n' nazwa, 'typ', 'kl' ulubiona klasa|null, 'o' opis, 'wrog' id rywala?, 'usluga' 'adwokat'?, 'dar' cios broni-prezentu]]
     'kontrakty' => [kod => ['npc','t' tytuł,'b' brief,'kl' klasa|null,'um' umiejętność,'sprz' ['cios'=>..]|['pancerz'=>N]?,
                             'tr' trudność,'kasa' baza $,'xp' % poziomu,'cz' minuty (0 = od razu),'gl' głośne,'rep' próg 0–4,'lvl' min. poziom,'mat' materiały?]]
     'skoki'     => [kod => ['npc','wsp','t','b','rep','lvl','lup' baza $,'xp' % poziomu,'mat',
                             'etapy' => [['n','min','o','ucieczka'?, 'op' => [['t','o','kl'?,'um','tr','sprz'?,'koszt'?,'premia'?,'lup'?,'gl'?], ×3]], …]]]
   Szansa = trudność + klasa 15 + umiejętność 4/poziom (max 20) + sprzęt 10 + ryzyko − obława·5, obcięta do 5–95. */

const ZL_LIMIT         = 5;
const ZL_LIMIT_PREMIUM = 6;
const ZL_PROGI         = [0, 5, 15, 30, 50];
const ZL_RANGI         = ['Obcy', 'Znajomy', 'Zaufany', 'Prawa ręka', 'Rodzina'];
const ZL_RYZYKO        = [['n' => 'Ostrożnie', 's' => 15, 'm' => 0.7], ['n' => 'Normalnie', 's' => 0, 'm' => 1.0], ['n' => 'Na bezczelnego', 's' => -15, 'm' => 1.6]];
const ZL_OBLAWA        = ['Czysto', 'Ktoś pytał', 'Patrole zaglądają', 'Twarz na odprawie', 'List gończy', 'Obława'];
const ZL_OBLAWA_SKUTEK = ['Wszystko dostępne.', 'Na razie bez skutków.', 'Szansa −10.', 'Skoki zablokowane, szansa −15.', 'Tylko ciche kontrakty. Każda porażka grozi aresztem.', 'Tylko przyczajenie się. Każda porażka to areszt.'];
const ZL_SPADEK_H      = 6;      // −1 gwiazdka co tyle godzin bez zleceń
const ZL_ODNOWA_H      = 20;     // ten sam kontrakt / skok ponownie po tylu godzinach
const ZL_REGIONY       = ['NY' => 'New York', 'LA' => 'Los Angeles', 'PL' => 'Polska'];

function zl_dane(string $region): array {
    static $c = [];
    if (!isset($c[$region])) {
        $plik = __DIR__ . '/zlecenia_' . strtolower($region) . '.php';
        $c[$region] = is_file($plik) ? require $plik : ['npc' => [], 'kontrakty' => [], 'skoki' => []];
    }
    return $c[$region];
}

/** Region zleceń dla miasta gracza: NY, LA, PL albo null (tu nikt nie zleca). */
function zl_region(?string $miasto): ?string {
    $m = strtoupper(trim((string)$miasto));
    if ($m === '' || $m === 'NEW YORK') return 'NY';
    if ($m === 'LOS ANGELES') return 'LA';
    if (in_array($m, ['WARSZAWA', 'KRAKOW', 'GDANSK', 'WROCLAW', 'POZNAN', 'LODZ'], true)) return 'PL';
    return null;
}
