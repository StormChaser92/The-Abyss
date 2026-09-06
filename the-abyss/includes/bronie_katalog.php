<?php
/* the-abyss/includes/bronie_katalog.php
   Katalog 60 broni — białe i palne, jeden wpis na model.
   Zastępuje trzy rozjechane listy: $katalog_broni w pages/ekwipunek.php,
   $sklep_bronie w pages/sklep.php i $katalog_schematow w pages/warsztat.php.

   Pola:
     poziom     — wymagany poziom postaci
     atak       — bonus_atak; krzywa 5·poziom × mnożnik kategorii
                  (ostrze 0,95 · tępe 1,00 · palna 1,10 · pięść 0,85)
     szybkosc   — bonus_szybkosc; wkład w unik to szybkosc × 0,45
     cios       — kategoria vs typ przeciwnika (arena_mnoznik_ciosu)
     cena       — cena w Lombardzie; 'sklep'=>false znaczy tylko od Inżyniera
     stal/czesci/syn/elek/trudnosc/en — receptura warsztatu

   Krzywa ataku celowo dobiega 5·poziom, bo tyle zakłada build referencyjny
   w arena_idealna_sila() (includes/arena_walka.php). Stary katalog kończył się
   na 100 ataku, czyli sześć razy poniżej kalibracji areny na poziomie 120. */

function bronie_katalog(): array {
    return [
        'zardzewialy_widelec' => ['nazwa'=>'Zardzewiały Widelec', 'poziom'=>1, 'atak'=>5, 'szybkosc'=>27, 'cios'=>'ostrze', 'ikona'=>'🍴', 'cena'=>50, 'sklep'=>true, 'stal'=>3, 'czesci'=>1, 'syn'=>0, 'elek'=>0, 'trudnosc'=>5, 'en'=>2],
        'gruba_rurka_stalowa' => ['nazwa'=>'Gruba Rurka Stalowa', 'poziom'=>1, 'atak'=>5, 'szybkosc'=>19, 'cios'=>'tepe', 'ikona'=>'🔧', 'cena'=>50, 'sklep'=>true, 'stal'=>3, 'czesci'=>1, 'syn'=>0, 'elek'=>0, 'trudnosc'=>5, 'en'=>2],
        'tepy_noz_kuchenny' => ['nazwa'=>'Tępy Nóż Kuchenny', 'poziom'=>2, 'atak'=>10, 'szybkosc'=>27, 'cios'=>'ostrze', 'ikona'=>'🔪', 'cena'=>168, 'sklep'=>true, 'stal'=>4, 'czesci'=>1, 'syn'=>1, 'elek'=>0, 'trudnosc'=>6, 'en'=>3],
        'kij_baseballowy' => ['nazwa'=>'Kij Baseballowy', 'poziom'=>3, 'atak'=>15, 'szybkosc'=>19, 'cios'=>'tepe', 'ikona'=>'🏏', 'cena'=>342, 'sklep'=>true, 'stal'=>5, 'czesci'=>2, 'syn'=>1, 'elek'=>0, 'trudnosc'=>7, 'en'=>3],
        'noz_sprezynowy' => ['nazwa'=>'Nóż Sprężynowy', 'poziom'=>4, 'atak'=>19, 'szybkosc'=>27, 'cios'=>'ostrze', 'ikona'=>'🔪', 'cena'=>566, 'sklep'=>true, 'stal'=>6, 'czesci'=>2, 'syn'=>1, 'elek'=>0, 'trudnosc'=>8, 'en'=>4],
        'lancuch_rowerowy' => ['nazwa'=>'Łańcuch Rowerowy', 'poziom'=>5, 'atak'=>25, 'szybkosc'=>18, 'cios'=>'tepe', 'ikona'=>'⛓️', 'cena'=>836, 'sklep'=>true, 'stal'=>7, 'czesci'=>3, 'syn'=>2, 'elek'=>0, 'trudnosc'=>9, 'en'=>4],
        'brzytwa' => ['nazwa'=>'Brzytwa', 'poziom'=>6, 'atak'=>29, 'szybkosc'=>26, 'cios'=>'ostrze', 'ikona'=>'🪒', 'cena'=>1150, 'sklep'=>true, 'stal'=>7, 'czesci'=>3, 'syn'=>2, 'elek'=>0, 'trudnosc'=>10, 'en'=>5],
        'stary_kastet' => ['nazwa'=>'Stary Kastet', 'poziom'=>7, 'atak'=>30, 'szybkosc'=>30, 'cios'=>'piesc', 'ikona'=>'👊', 'cena'=>1506, 'sklep'=>true, 'stal'=>8, 'czesci'=>4, 'syn'=>2, 'elek'=>0, 'trudnosc'=>11, 'en'=>5],
        'palka_teleskopowa' => ['nazwa'=>'Pałka Teleskopowa', 'poziom'=>8, 'atak'=>40, 'szybkosc'=>18, 'cios'=>'tepe', 'ikona'=>'🏒', 'cena'=>1903, 'sklep'=>true, 'stal'=>9, 'czesci'=>4, 'syn'=>3, 'elek'=>0, 'trudnosc'=>12, 'en'=>5],
        'noz_motylkowy' => ['nazwa'=>'Nóż Motylkowy', 'poziom'=>9, 'atak'=>43, 'szybkosc'=>26, 'cios'=>'ostrze', 'ikona'=>'🔪', 'cena'=>2338, 'sklep'=>true, 'stal'=>10, 'czesci'=>5, 'syn'=>3, 'elek'=>1, 'trudnosc'=>13, 'en'=>6],
        'pistolet_samorobka' => ['nazwa'=>'Pistolet Samoróbka', 'poziom'=>10, 'atak'=>55, 'szybkosc'=>19, 'cios'=>'palna', 'ikona'=>'🔫', 'cena'=>2812, 'sklep'=>true, 'stal'=>11, 'czesci'=>6, 'syn'=>2, 'elek'=>3, 'trudnosc'=>14, 'en'=>6],
        'klucz_francuski' => ['nazwa'=>'Klucz Francuski', 'poziom'=>11, 'atak'=>55, 'szybkosc'=>18, 'cios'=>'tepe', 'ikona'=>'🔧', 'cena'=>3322, 'sklep'=>true, 'stal'=>12, 'czesci'=>6, 'syn'=>4, 'elek'=>1, 'trudnosc'=>14, 'en'=>7],
        'noz_bojowy_ka_bar' => ['nazwa'=>'Nóż Bojowy KA-BAR', 'poziom'=>12, 'atak'=>57, 'szybkosc'=>26, 'cios'=>'ostrze', 'ikona'=>'🔪', 'cena'=>3868, 'sklep'=>true, 'stal'=>13, 'czesci'=>7, 'syn'=>4, 'elek'=>1, 'trudnosc'=>15, 'en'=>7],
        'kastet_cwiekowany' => ['nazwa'=>'Kastet Ćwiekowany', 'poziom'=>14, 'atak'=>60, 'szybkosc'=>30, 'cios'=>'piesc', 'ikona'=>'👊', 'cena'=>5066, 'sklep'=>false, 'stal'=>15, 'czesci'=>8, 'syn'=>4, 'elek'=>1, 'trudnosc'=>17, 'en'=>8],
        'obrzyn_dwururka' => ['nazwa'=>'Obrzyn Dwururka', 'poziom'=>15, 'atak'=>83, 'szybkosc'=>19, 'cios'=>'palna', 'ikona'=>'💥', 'cena'=>5716, 'sklep'=>false, 'stal'=>16, 'czesci'=>8, 'syn'=>3, 'elek'=>5, 'trudnosc'=>18, 'en'=>8],
        'glock_17' => ['nazwa'=>'Glock 17', 'poziom'=>16, 'atak'=>88, 'szybkosc'=>18, 'cios'=>'palna', 'ikona'=>'🔫', 'cena'=>6400, 'sklep'=>false, 'stal'=>16, 'czesci'=>9, 'syn'=>3, 'elek'=>5, 'trudnosc'=>19, 'en'=>9],
        'bagnet' => ['nazwa'=>'Bagnet', 'poziom'=>17, 'atak'=>81, 'szybkosc'=>25, 'cios'=>'ostrze', 'ikona'=>'🗡️', 'cena'=>7116, 'sklep'=>false, 'stal'=>17, 'czesci'=>9, 'syn'=>5, 'elek'=>1, 'trudnosc'=>20, 'en'=>9],
        'mlotek_ciesielski' => ['nazwa'=>'Młotek Ciesielski', 'poziom'=>19, 'atak'=>95, 'szybkosc'=>17, 'cios'=>'tepe', 'ikona'=>'🔨', 'cena'=>8645, 'sklep'=>false, 'stal'=>19, 'czesci'=>10, 'syn'=>6, 'elek'=>1, 'trudnosc'=>22, 'en'=>10],
        'owijki_bokserskie' => ['nazwa'=>'Owijki Bokserskie', 'poziom'=>20, 'atak'=>85, 'szybkosc'=>30, 'cios'=>'piesc', 'ikona'=>'🥊', 'cena'=>9457, 'sklep'=>false, 'stal'=>20, 'czesci'=>11, 'syn'=>6, 'elek'=>1, 'trudnosc'=>23, 'en'=>10],
        'maczeta_kukri' => ['nazwa'=>'Maczeta Kukri', 'poziom'=>21, 'atak'=>100, 'szybkosc'=>24, 'cios'=>'ostrze', 'ikona'=>'🔪', 'cena'=>10300, 'sklep'=>false, 'stal'=>21, 'czesci'=>12, 'syn'=>7, 'elek'=>1, 'trudnosc'=>24, 'en'=>11],
        'beretta_92' => ['nazwa'=>'Beretta 92', 'poziom'=>22, 'atak'=>121, 'szybkosc'=>17, 'cios'=>'palna', 'ikona'=>'🔫', 'cena'=>11174, 'sklep'=>false, 'stal'=>22, 'czesci'=>12, 'syn'=>4, 'elek'=>7, 'trudnosc'=>25, 'en'=>11],
        'kij_bilardowy' => ['nazwa'=>'Kij Bilardowy', 'poziom'=>24, 'atak'=>120, 'szybkosc'=>16, 'cios'=>'tepe', 'ikona'=>'🎱', 'cena'=>13012, 'sklep'=>false, 'stal'=>24, 'czesci'=>13, 'syn'=>8, 'elek'=>1, 'trudnosc'=>27, 'en'=>12],
        'uzi' => ['nazwa'=>'Uzi', 'poziom'=>25, 'atak'=>138, 'szybkosc'=>17, 'cios'=>'palna', 'ikona'=>'💨', 'cena'=>13975, 'sklep'=>false, 'stal'=>25, 'czesci'=>14, 'syn'=>5, 'elek'=>8, 'trudnosc'=>28, 'en'=>13],
        'tanto' => ['nazwa'=>'Tanto', 'poziom'=>27, 'atak'=>128, 'szybkosc'=>24, 'cios'=>'ostrze', 'ikona'=>'🗡️', 'cena'=>15990, 'sklep'=>false, 'stal'=>26, 'czesci'=>15, 'syn'=>9, 'elek'=>2, 'trudnosc'=>30, 'en'=>13],
        'rekawice_z_olowiem' => ['nazwa'=>'Rękawice z Ołowiem', 'poziom'=>28, 'atak'=>119, 'szybkosc'=>30, 'cios'=>'piesc', 'ikona'=>'🥊', 'cena'=>17041, 'sklep'=>false, 'stal'=>27, 'czesci'=>15, 'syn'=>9, 'elek'=>2, 'trudnosc'=>31, 'en'=>14],
        'lopata' => ['nazwa'=>'Łopata', 'poziom'=>30, 'atak'=>150, 'szybkosc'=>16, 'cios'=>'tepe', 'ikona'=>'🛠️', 'cena'=>19228, 'sklep'=>false, 'stal'=>29, 'czesci'=>17, 'syn'=>10, 'elek'=>2, 'trudnosc'=>33, 'en'=>15],
        'skorpion_vz_61' => ['nazwa'=>'Skorpion vz. 61', 'poziom'=>31, 'atak'=>171, 'szybkosc'=>16, 'cios'=>'palna', 'ikona'=>'💨', 'cena'=>20364, 'sklep'=>false, 'stal'=>30, 'czesci'=>17, 'syn'=>6, 'elek'=>9, 'trudnosc'=>33, 'en'=>15],
        'mp5' => ['nazwa'=>'MP5', 'poziom'=>33, 'atak'=>182, 'szybkosc'=>16, 'cios'=>'palna', 'ikona'=>'💨', 'cena'=>22718, 'sklep'=>false, 'stal'=>32, 'czesci'=>18, 'syn'=>6, 'elek'=>10, 'trudnosc'=>35, 'en'=>16],
        'siekiera_strazacka' => ['nazwa'=>'Siekiera Strażacka', 'poziom'=>34, 'atak'=>162, 'szybkosc'=>23, 'cios'=>'ostrze', 'ikona'=>'🪓', 'cena'=>23936, 'sklep'=>false, 'stal'=>33, 'czesci'=>19, 'syn'=>11, 'elek'=>2, 'trudnosc'=>36, 'en'=>16],
        'colt_1911' => ['nazwa'=>'Colt 1911', 'poziom'=>36, 'atak'=>198, 'szybkosc'=>15, 'cios'=>'palna', 'ikona'=>'🔫', 'cena'=>26454, 'sklep'=>false, 'stal'=>34, 'czesci'=>20, 'syn'=>6, 'elek'=>11, 'trudnosc'=>38, 'en'=>17],
        'nunczako' => ['nazwa'=>'Nunczako', 'poziom'=>37, 'atak'=>185, 'szybkosc'=>15, 'cios'=>'tepe', 'ikona'=>'🥢', 'cena'=>27754, 'sklep'=>false, 'stal'=>35, 'czesci'=>20, 'syn'=>12, 'elek'=>2, 'trudnosc'=>39, 'en'=>18],
        'mossberg_500' => ['nazwa'=>'Mossberg 500', 'poziom'=>39, 'atak'=>215, 'szybkosc'=>15, 'cios'=>'palna', 'ikona'=>'💥', 'cena'=>30432, 'sklep'=>false, 'stal'=>37, 'czesci'=>21, 'syn'=>7, 'elek'=>12, 'trudnosc'=>41, 'en'=>18],
        'katana' => ['nazwa'=>'Katana', 'poziom'=>42, 'atak'=>200, 'szybkosc'=>22, 'cios'=>'ostrze', 'ikona'=>'⚔️', 'cena'=>34646, 'sklep'=>false, 'stal'=>40, 'czesci'=>23, 'syn'=>13, 'elek'=>3, 'trudnosc'=>44, 'en'=>20],
        'desert_eagle_50' => ['nazwa'=>'Desert Eagle .50', 'poziom'=>44, 'atak'=>242, 'szybkosc'=>14, 'cios'=>'palna', 'ikona'=>'🔫', 'cena'=>37585, 'sklep'=>false, 'stal'=>42, 'czesci'=>24, 'syn'=>8, 'elek'=>13, 'trudnosc'=>46, 'en'=>20],
        'kastet_tytanowy' => ['nazwa'=>'Kastet Tytanowy', 'poziom'=>45, 'atak'=>191, 'szybkosc'=>30, 'cios'=>'piesc', 'ikona'=>'👊', 'cena'=>39092, 'sklep'=>false, 'stal'=>43, 'czesci'=>25, 'syn'=>14, 'elek'=>3, 'trudnosc'=>47, 'en'=>21],
        'mlot_dwureczny' => ['nazwa'=>'Młot Dwuręczny', 'poziom'=>46, 'atak'=>230, 'szybkosc'=>14, 'cios'=>'tepe', 'ikona'=>'🔨', 'cena'=>40625, 'sklep'=>false, 'stal'=>43, 'czesci'=>25, 'syn'=>15, 'elek'=>3, 'trudnosc'=>48, 'en'=>21],
        'ak_47' => ['nazwa'=>'AK-47', 'poziom'=>48, 'atak'=>264, 'szybkosc'=>13, 'cios'=>'palna', 'ikona'=>'🎯', 'cena'=>43767, 'sklep'=>false, 'stal'=>45, 'czesci'=>26, 'syn'=>9, 'elek'=>14, 'trudnosc'=>50, 'en'=>22],
        'fn_p90' => ['nazwa'=>'FN P90', 'poziom'=>50, 'atak'=>275, 'szybkosc'=>13, 'cios'=>'palna', 'ikona'=>'💨', 'cena'=>47008, 'sklep'=>false, 'stal'=>47, 'czesci'=>28, 'syn'=>9, 'elek'=>15, 'trudnosc'=>52, 'en'=>23],
        'maczeta_bolo' => ['nazwa'=>'Maczeta Bolo', 'poziom'=>52, 'atak'=>247, 'szybkosc'=>21, 'cios'=>'ostrze', 'ikona'=>'🔪', 'cena'=>50347, 'sklep'=>false, 'stal'=>49, 'czesci'=>29, 'syn'=>17, 'elek'=>3, 'trudnosc'=>53, 'en'=>24],
        'remington_870' => ['nazwa'=>'Remington 870', 'poziom'=>54, 'atak'=>297, 'szybkosc'=>12, 'cios'=>'palna', 'ikona'=>'💥', 'cena'=>53785, 'sklep'=>false, 'stal'=>51, 'czesci'=>30, 'syn'=>10, 'elek'=>16, 'trudnosc'=>55, 'en'=>25],
        'kilof' => ['nazwa'=>'Kilof', 'poziom'=>57, 'atak'=>285, 'szybkosc'=>13, 'cios'=>'tepe', 'ikona'=>'⛏️', 'cena'=>59122, 'sklep'=>false, 'stal'=>53, 'czesci'=>31, 'syn'=>18, 'elek'=>3, 'trudnosc'=>58, 'en'=>26],
        'm4a1' => ['nazwa'=>'M4A1', 'poziom'=>58, 'atak'=>319, 'szybkosc'=>11, 'cios'=>'palna', 'ikona'=>'🎯', 'cena'=>60949, 'sklep'=>false, 'stal'=>54, 'czesci'=>32, 'syn'=>10, 'elek'=>17, 'trudnosc'=>59, 'en'=>26],
        'thompson_m1928' => ['nazwa'=>'Thompson M1928', 'poziom'=>60, 'atak'=>330, 'szybkosc'=>11, 'cios'=>'palna', 'ikona'=>'💨', 'cena'=>64675, 'sklep'=>false, 'stal'=>56, 'czesci'=>33, 'syn'=>11, 'elek'=>18, 'trudnosc'=>61, 'en'=>27],
        'szabla_kawaleryjska' => ['nazwa'=>'Szabla Kawaleryjska', 'poziom'=>64, 'atak'=>304, 'szybkosc'=>19, 'cios'=>'ostrze', 'ikona'=>'⚔️', 'cena'=>72408, 'sklep'=>false, 'stal'=>60, 'czesci'=>35, 'syn'=>20, 'elek'=>4, 'trudnosc'=>65, 'en'=>29],
        'spas_12' => ['nazwa'=>'SPAS-12', 'poziom'=>66, 'atak'=>363, 'szybkosc'=>10, 'cios'=>'palna', 'ikona'=>'💥', 'cena'=>76414, 'sklep'=>false, 'stal'=>61, 'czesci'=>36, 'syn'=>12, 'elek'=>20, 'trudnosc'=>67, 'en'=>30],
        'lom' => ['nazwa'=>'Łom', 'poziom'=>70, 'atak'=>350, 'szybkosc'=>11, 'cios'=>'tepe', 'ikona'=>'🪝', 'cena'=>84702, 'sklep'=>false, 'stal'=>65, 'czesci'=>39, 'syn'=>22, 'elek'=>4, 'trudnosc'=>71, 'en'=>31],
        'fn_scar_h' => ['nazwa'=>'FN SCAR-H', 'poziom'=>72, 'atak'=>396, 'szybkosc'=>9, 'cios'=>'palna', 'ikona'=>'🎯', 'cena'=>88982, 'sklep'=>false, 'stal'=>67, 'czesci'=>40, 'syn'=>13, 'elek'=>22, 'trudnosc'=>72, 'en'=>32],
        'mosin_nagant' => ['nazwa'=>'Mosin Nagant', 'poziom'=>74, 'atak'=>407, 'szybkosc'=>9, 'cios'=>'palna', 'ikona'=>'🔭', 'cena'=>93352, 'sklep'=>false, 'stal'=>69, 'czesci'=>41, 'syn'=>13, 'elek'=>22, 'trudnosc'=>74, 'en'=>33],
        'topor_bojowy' => ['nazwa'=>'Topór Bojowy', 'poziom'=>78, 'atak'=>371, 'szybkosc'=>17, 'cios'=>'ostrze', 'ikona'=>'🪓', 'cena'=>102361, 'sklep'=>false, 'stal'=>72, 'czesci'=>43, 'syn'=>25, 'elek'=>5, 'trudnosc'=>78, 'en'=>35],
        'karabin_wyborowy_awp' => ['nazwa'=>'Karabin Wyborowy AWP', 'poziom'=>80, 'atak'=>440, 'szybkosc'=>8, 'cios'=>'palna', 'ikona'=>'🔭', 'cena'=>106998, 'sklep'=>false, 'stal'=>74, 'czesci'=>44, 'syn'=>14, 'elek'=>24, 'trudnosc'=>80, 'en'=>36],
        'piesc_zelazna' => ['nazwa'=>'Pięść Żelazna', 'poziom'=>85, 'atak'=>361, 'szybkosc'=>30, 'cios'=>'piesc', 'ikona'=>'👊', 'cena'=>118974, 'sklep'=>false, 'stal'=>79, 'czesci'=>47, 'syn'=>27, 'elek'=>5, 'trudnosc'=>85, 'en'=>38],
        'steyr_aug' => ['nazwa'=>'Steyr AUG', 'poziom'=>86, 'atak'=>473, 'szybkosc'=>7, 'cios'=>'palna', 'ikona'=>'🎯', 'cena'=>121435, 'sklep'=>false, 'stal'=>79, 'czesci'=>47, 'syn'=>15, 'elek'=>26, 'trudnosc'=>86, 'en'=>38],
        'barrett_m82' => ['nazwa'=>'Barrett M82', 'poziom'=>90, 'atak'=>495, 'szybkosc'=>6, 'cios'=>'palna', 'ikona'=>'🔭', 'cena'=>131491, 'sklep'=>false, 'stal'=>83, 'czesci'=>50, 'syn'=>16, 'elek'=>27, 'trudnosc'=>90, 'en'=>40],
        'mg42' => ['nazwa'=>'MG42', 'poziom'=>94, 'atak'=>517, 'szybkosc'=>5, 'cios'=>'palna', 'ikona'=>'🔥', 'cena'=>141887, 'sklep'=>false, 'stal'=>87, 'czesci'=>52, 'syn'=>17, 'elek'=>28, 'trudnosc'=>93, 'en'=>41],
        'halabarda' => ['nazwa'=>'Halabarda', 'poziom'=>97, 'atak'=>461, 'szybkosc'=>15, 'cios'=>'ostrze', 'ikona'=>'🗡️', 'cena'=>149907, 'sklep'=>false, 'stal'=>89, 'czesci'=>53, 'syn'=>31, 'elek'=>6, 'trudnosc'=>96, 'en'=>43],
        'm249_saw' => ['nazwa'=>'M249 SAW', 'poziom'=>100, 'atak'=>550, 'szybkosc'=>4, 'cios'=>'palna', 'ikona'=>'🔥', 'cena'=>158114, 'sklep'=>false, 'stal'=>92, 'czesci'=>55, 'syn'=>18, 'elek'=>30, 'trudnosc'=>99, 'en'=>44],
        'minigun_m134' => ['nazwa'=>'Minigun M134', 'poziom'=>106, 'atak'=>583, 'szybkosc'=>3, 'cios'=>'palna', 'ikona'=>'🔥', 'cena'=>175088, 'sklep'=>false, 'stal'=>97, 'czesci'=>58, 'syn'=>19, 'elek'=>32, 'trudnosc'=>105, 'en'=>47],
        'granatnik_m32' => ['nazwa'=>'Granatnik M32', 'poziom'=>112, 'atak'=>616, 'szybkosc'=>2, 'cios'=>'palna', 'ikona'=>'💥', 'cena'=>192798, 'sklep'=>false, 'stal'=>103, 'czesci'=>62, 'syn'=>20, 'elek'=>34, 'trudnosc'=>110, 'en'=>49],
        'wyrzutnia_rpg_7' => ['nazwa'=>'Wyrzutnia RPG-7', 'poziom'=>118, 'atak'=>649, 'szybkosc'=>1, 'cios'=>'palna', 'ikona'=>'🚀', 'cena'=>211234, 'sklep'=>false, 'stal'=>108, 'czesci'=>65, 'syn'=>21, 'elek'=>35, 'trudnosc'=>116, 'en'=>52],
        'karabin_przeciwpancerny_ntw_20' => ['nazwa'=>'Karabin Przeciwpancerny NTW-20', 'poziom'=>120, 'atak'=>660, 'szybkosc'=>1, 'cios'=>'palna', 'ikona'=>'🔭', 'cena'=>217539, 'sklep'=>false, 'stal'=>110, 'czesci'=>66, 'syn'=>22, 'elek'=>36, 'trudnosc'=>118, 'en'=>52],
    ];
}

/** Broń dostępna w Lombardzie (poziomy 1–12). Reszta tylko z warsztatu. */
function bronie_sklep(): array {
    return array_filter(bronie_katalog(), fn($b) => $b['sklep']);
}

/** Schematy warsztatu pogrupowane w cztery tiery po poziomie. */
function bronie_tiery(): array {
    $t = ['I · Zaułek (1–12)'=>[], 'II · Ulica (13–40)'=>[], 'III · Zawodowo (41–80)'=>[], 'IV · Wojna (81–120)'=>[]];
    foreach (bronie_katalog() as $kod => $b) {
        $k = $b['poziom'] <= 12 ? 'I · Zaułek (1–12)'
           : ($b['poziom'] <= 40 ? 'II · Ulica (13–40)'
           : ($b['poziom'] <= 80 ? 'III · Zawodowo (41–80)' : 'IV · Wojna (81–120)'));
        $t[$k][$kod] = $b;
    }
    return $t;
}

function bronie_kategoria_nazwa(string $c): string {
    return ['ostrze'=>'Ostrze', 'tepe'=>'Tępe narzędzie', 'palna'=>'Broń palna', 'piesc'=>'Goła pięść'][$c] ?? 'Goła pięść';
}
