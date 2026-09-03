<?php
require_once "db.php";
require_once "config/pochodzenia.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat        = "";
$log_walki        = [];
$podsumowanie     = null;

// ═══════════════════════════════════════════════════════════════════
//  HELPERY MATEMATYCZNE — MODEL WALKI
// ═══════════════════════════════════════════════════════════════════

// Tabela progów konwersji MAŁYCH krytyków → 0.1 DUŻEGO krytyka.
// „Do X pkt umiejki wymagane Y uników" ⇒ gdy skill ≤ X, próg = Y.
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

// Szansa trafienia (w %), formuła logarytmiczna, clamp 5–95.
function szansa_trafienia(float $nasze_trafienie, float $zrecznosc_wroga): float {
    $s = ((log10($nasze_trafienie + 200) - log10($zrecznosc_wroga + 200)) * 500) + 50;
    return max(5, min(95, $s));
}

// „Nasze Trafienie" — agregat statystyki celności gracza.
function oblicz_trafienie(int $sila, float $wb, int $lvl, int $bonus_atak): float {
    return ((1/4) + ($lvl / 5800)) * $sila + $wb + $lvl + $bonus_atak;
}

// Średni random obrażeń dla poziomu (średnia z przedziału [1, 5×lvl]).
function sredni_random(int $lvl): float {
    return (1 + 5 * $lvl) / 2;
}

// Konwersja małych krytyków → duże. Używa pętli, bo próg może się zmienić
// w trakcie awansu skilla. Zwraca [pozostała_kumulacja, przyrost_skilla].
function konwertuj_male_na_duze(int $male, float $obecny_skill): array {
    $przyrost = 0.0;
    while (true) {
        $prog = prog_dla_skilla($obecny_skill + $przyrost);
        if ($male >= $prog) { $male -= $prog; $przyrost += 0.1; }
        else break;
    }
    return [$male, round($przyrost, 3)];
}

// ═══════════════════════════════════════════════════════════════════
//  POBIERANIE DANYCH
// ═══════════════════════════════════════════════════════════════════
$wynik = $polaczenie->query("SELECT poziom, exp, gotowka, hp_aktualne, hp_max,
    energia_aktualna, sila, wytrzymalosc, zrecznosc, walka_bronia, uniki,
    bonus_atak, bonus_obrona, apteczki, bron_zalozona
    FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// Fallback — jeśli jeszcze nie wykonałaś ALTER TABLE
$kum_row = @$polaczenie->query("SELECT uniki_male_kumulacja, walka_male_kumulacja FROM gracze WHERE id=$id_gracza");
if ($kum_row && $kum_row->num_rows) {
    $k = $kum_row->fetch_assoc();
    $gracz['uniki_male_kumulacja'] = (int)($k['uniki_male_kumulacja'] ?? 0);
    $gracz['walka_male_kumulacja'] = (int)($k['walka_male_kumulacja'] ?? 0);
    $ma_kolumny_kumulacji = true;
} else {
    $gracz['uniki_male_kumulacja'] = 0;
    $gracz['walka_male_kumulacja'] = 0;
    $ma_kolumny_kumulacji = false;
}

$wrogowie_db = $polaczenie->query("SELECT * FROM wrogowie ORDER BY poziom ASC");
$lista_wrogow = [];
if ($wrogowie_db) {
    while ($w = $wrogowie_db->fetch_assoc()) $lista_wrogow[$w['id']] = $w;
}

// ═══════════════════════════════════════════════════════════════════
//  APTECZKA
// ═══════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uzyj_apteczki'])) {
    if ($gracz['apteczki'] > 0 && $gracz['hp_aktualne'] < $gracz['hp_max']) {
        $nowe_hp = min($gracz['hp_max'], $gracz['hp_aktualne'] + 50);
        $polaczenie->query("UPDATE gracze SET hp_aktualne=$nowe_hp, apteczki=apteczki-1 WHERE id=$id_gracza");
        $gracz['hp_aktualne'] = $nowe_hp;
        $gracz['apteczki'] -= 1;
        $komunikat = "<div class='alert-ok'>💉 Adrenalina w żyłach. Odzyskujesz +50 HP.</div>";
    } else {
        $komunikat = "<div class='alert-err'>Jesteś w pełni zdrowy lub nie masz apteczek.</div>";
    }
}

// ═══════════════════════════════════════════════════════════════════
//  WALKA — MULTI-FIGHT Z INTEGRACJĄ BONUSÓW NARODOWYCH CLAUDE'A
// ═══════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['walcz'])) {
    $id_wroga   = (int)($_POST['id_wroga'] ?? 0);
    $ilosc_walk = max(1, min(10, (int)($_POST['ilosc_walk'] ?? 1)));
    $amunicja   = $_POST['typ_amunicji'] ?? 'Kinetyczna';

    // PROSTE USTALENIE TYPU BRONI (na potrzeby skryptu Claude'a)
    $nazwa_broni_mala = strtolower($gracz['bron_zalozona'] ?? '');
    $typ_broni = (strpos($nazwa_broni_mala, 'nóż') !== false || strpos($nazwa_broni_mala, 'kij') !== false || strpos($nazwa_broni_mala, 'maczeta') !== false) ? 'melee' : 'ranged';

    if (!isset($lista_wrogow[$id_wroga])) {
        $komunikat = "<div class='alert-err'>Ten przeciwnik nie istnieje.</div>";
    } elseif ($gracz['energia_aktualna'] < $ilosc_walk) {
        $komunikat = "<div class='alert-err'>Masz {$gracz['energia_aktualna']} EN, a potrzebujesz {$ilosc_walk}.</div>";
    } elseif ($gracz['hp_aktualne'] <= 10) {
        $komunikat = "<div class='alert-err'>Ledwo stoisz. Użyj apteczki lub odwiedź Klinikę.</div>";
    } else {
        $p_sila    = $gracz['sila'];
        $p_wyt     = $gracz['wytrzymalosc'];
        $p_wb      = (float)$gracz['walka_bronia'];
        $p_un      = (float)$gracz['uniki'];
        $p_bonus_a = $gracz['bonus_atak'];
        $p_bonus_o = $gracz['bonus_obrona'];
        $p_lvl     = $gracz['poziom'];
        $p_hp      = $gracz['hp_aktualne'];
        $p_trafienie = oblicz_trafienie($p_sila, $p_wb, $p_lvl, $p_bonus_a);

        $sesja = [
            'walk_wyk'=>0, 'wygranych'=>0, 'przegranych'=>0,
            'exp_total'=>0, 'kasa_total'=>0,
            'male_un'=>0, 'male_wb'=>0,
            'loot'=>[], 'rundy_total'=>0,
            'dmg_zadanych'=>0, 'dmg_otrzymanych'=>0,
        ];

        for ($walka = 1; $walka <= $ilosc_walk; $walka++) {
            $wrog = $lista_wrogow[$id_wroga];
            $w_nazwa = $wrog['nazwa']; $w_lvl = $wrog['poziom'];
            $w_hp = $wrog['hp']; $w_hp_max = $wrog['hp'];
            $w_atak = $wrog['atak']; $w_obrona = $wrog['obrona'];
            $w_zr = $wrog['uniki']; 
            $w_panc = $wrog['typ_pancerza'];

            $mnoznik = 1.0;
            if     ($amunicja=='Toksyczna'       && $w_panc=='Biologiczny')    $mnoznik = 1.5;
            elseif ($amunicja=='Toksyczna'       && $w_panc=='Cybernetyczny')  $mnoznik = 0.5;
            elseif ($amunicja=='Przeciwpancerna' && $w_panc=='Opancerzony')    $mnoznik = 1.5;
            elseif ($amunicja=='Przeciwpancerna' && $w_panc=='Biologiczny')    $mnoznik = 0.5;
            elseif ($amunicja=='EMP'             && $w_panc=='Cybernetyczny')  $mnoznik = 1.5;
            elseif ($amunicja=='EMP'             && $w_panc=='Opancerzony')    $mnoznik = 0.5;

            // APLIKOWANIE BONUSU UNIKU OD CLAUDE'A (Brazylijczyk)
            $dodatkowy_unik = function_exists('pochodzenie_bonus') ? pochodzenie_bonus($gracz, 'unik_szansa_abs', 0) : 0;
            
            $p_hit = szansa_trafienia($p_trafienie, $w_zr);
            $w_hit = szansa_trafienia($w_zr, $p_un) - $dodatkowy_unik; // Odejmujemy szansę trafienia wroga

            $log_walki[] = "<div class='lg-walka-h'>▶ WALKA #$walka · <b>$w_nazwa</b> (Lvl $w_lvl) · ammo [$amunicja] ×$mnoznik · trafienie: ".round($p_hit,1)."% / wróg: ".round($w_hit,1)."%</div>";

            $runda = 1;
            $male_un_walka = 0;
            $male_wb_walka = 0;

            while ($p_hp > 10 && $w_hp > 0 && $runda <= 24) {
                // --- GRACZ ATAKUJE ---
                if (mt_rand(1, 10000) <= $p_hit * 100) {
                    $r  = mt_rand(1, max(1, 5 * $p_lvl));
                    $raw = $p_sila + $p_bonus_a + $r;
                    $dmg = max(1, (int)round(($raw - $w_obrona) * $mnoznik));
                    
                    // SZANSA NA KRYTYKA BAZOWA
                    $szansa_krytyka = 10;

                    // 1. ZINTEGROWANY KOD CLAUDE'A - JAPOŃCZYK (Obrażenia bronią białą)
                    if ($typ_broni === 'melee' && function_exists('pochodzenie_bonus')) {
                        $dmg = round($dmg * pochodzenie_bonus($gracz, 'egzekutor_dmg_melee_mult', 1.0));
                        $szansa_krytyka += pochodzenie_bonus($gracz, 'egzekutor_krytyk_melee_abs', 0);
                    }

                    // 2. ZINTEGROWANY KOD CLAUDE'A - HISZPAN (Pierwsza tura)
                    if ($runda === 1 && function_exists('pochodzenie_bonus')) {
                        $dmg = round($dmg * pochodzenie_bonus($gracz, 'egzekutor_dmg_pierwsza_tura_mult', 1.0));
                    }

                    // Ostateczny test na krytyka
                    $krytyk = (mt_rand(1,100) <= $szansa_krytyka);
                    if ($krytyk) $dmg = (int)round($dmg * 2);
                    
                    $w_hp -= $dmg;
                    $sesja['dmg_zadanych'] += $dmg;
                    $male_wb_walka++;
                    $oznacz = $krytyk ? "<span class='lg-kryt'>[KRYTYK]</span>" : "<span class='lg-hit'>[TRAFIENIE]</span>";
                    $log_walki[] = "<span class='lg-r'>R{$runda}</span> $oznacz Zadajesz <b>{$dmg}</b> dmg · wróg: ".max(0,$w_hp)."/{$w_hp_max}";
                } else {
                    $log_walki[] = "<span class='lg-r'>R{$runda}</span> <span class='lg-miss'>[PUDŁO]</span> Pocisk trafia w pustkę.";
                }
                if ($w_hp <= 0) break;

                // --- WRÓG ATAKUJE ---
                if (mt_rand(1, 10000) <= $w_hit * 100) {
                    $rw = mt_rand(1, max(1, 5 * $w_lvl));
                    $raw_w = $w_atak + $rw;
                    $obr_gracza = $p_wyt + $p_bonus_o;
                    $dmg_w = max(1, (int)round($raw_w - $obr_gracza));

                    // 3. ZINTEGROWANY KOD CLAUDE'A - ROSJANIN (Otrzymywane obrażenia wręcz)
                    $typ_ataku = 'melee'; 
                    if ($typ_ataku === 'melee' && function_exists('pochodzenie_bonus')) {
                        $dmg_w = round($dmg_w * pochodzenie_bonus($gracz, 'dmg_otrzymywanych_melee_mult', 1.0));
                    }

                    $p_hp -= $dmg_w;
                    $sesja['dmg_otrzymanych'] += $dmg_w;
                    $log_walki[] = "<span class='lg-r'>R{$runda}</span> <span class='lg-rana'>[RANA]</span> Obrywasz <b>{$dmg_w}</b> dmg · HP: {$p_hp}";
                } else {
                    $male_un_walka++;
                    $log_walki[] = "<span class='lg-r'>R{$runda}</span> <span class='lg-unik'>[UNIK]</span> Odskok w ostatniej chwili. (+1 mały unik)";
                }
                $runda++;
            }

            $przebyte = max(1, $runda - 1);
            $sesja['rundy_total'] += $przebyte;
            $sesja['male_un']     += $male_un_walka;
            $sesja['male_wb']     += $male_wb_walka;
            $sesja['walk_wyk']++;

            if ($p_hp <= 10) {
                $log_walki[] = "<div class='lg-klat lg-loss'>💀 PORAŻKA po {$przebyte} rundach. Sesja przerwana.</div>";
                $sesja['przegranych']++;
                if ($p_hp < 1) $p_hp = 1;
                break;
            } else {
                $exp_z  = mt_rand((int)($wrog['exp']*0.8),  (int)($wrog['exp']*1.2));
                $kasa_z = mt_rand((int)($wrog['kasa']*0.8), (int)($wrog['kasa']*1.2));
                $sesja['exp_total']  += $exp_z;
                $sesja['kasa_total'] += $kasa_z;
                $sesja['wygranych']++;
                $log_walki[] = "<div class='lg-klat lg-win'>✓ {$w_nazwa} pokonany (R{$przebyte}) · +{$exp_z} EXP · +{$kasa_z} \$ · małe uniki tej walki: {$male_un_walka}</div>";
                if (!empty($wrog['loot_nazwa']) && $wrog['loot_szansa'] > 0 && mt_rand(1,100) <= $wrog['loot_szansa']) {
                    $sesja['loot'][$wrog['loot_nazwa']] = ($sesja['loot'][$wrog['loot_nazwa']] ?? 0) + 1;
                }
            }
            $log_walki[] = "<div class='lg-sep'>─────────────────────</div>";
        }

        // ═══ KONWERSJA MAŁYCH KRYTYKÓW → DUŻE ════════════════════════
        $kum_un = $gracz['uniki_male_kumulacja'] + $sesja['male_un'];
        [$kum_un, $przyr_un] = konwertuj_male_na_duze($kum_un, $p_un);
        $kum_wb = $gracz['walka_male_kumulacja'] + $sesja['male_wb'];
        [$kum_wb, $przyr_wb] = konwertuj_male_na_duze($kum_wb, $p_wb);

        // ═══ ZAPIS ═══════════════════════════════════════════════════
        $hp_safe = max(1, $p_hp);
        $en_koszt = $sesja['walk_wyk'];
        $new_un = $p_un + $przyr_un;
        $new_wb = $p_wb + $przyr_wb;

        $sql_extra = $ma_kolumny_kumulacji
            ? ", uniki_male_kumulacja=$kum_un, walka_male_kumulacja=$kum_wb"
            : "";

        $polaczenie->query("UPDATE gracze SET
            hp_aktualne=$hp_safe,
            energia_aktualna=GREATEST(0, energia_aktualna-$en_koszt),
            exp=exp+{$sesja['exp_total']},
            gotowka=gotowka+{$sesja['kasa_total']},
            uniki=$new_un,
            walka_bronia=$new_wb
            $sql_extra
            WHERE id=$id_gracza");

        foreach ($sesja['loot'] as $nazwa => $ile) {
            $n = $polaczenie->real_escape_string($nazwa);
            $ch = $polaczenie->query("SELECT id FROM przedmioty_gracze WHERE gracz_id=$id_gracza AND nazwa='$n'");
            if ($ch && $ch->num_rows) $polaczenie->query("UPDATE przedmioty_gracze SET ilosc=ilosc+$ile WHERE gracz_id=$id_gracza AND nazwa='$n'");
            else                      $polaczenie->query("INSERT INTO przedmioty_gracze (gracz_id, nazwa, ilosc) VALUES ($id_gracza, '$n', $ile)");
        }

        // Odśwież $gracz do renderu
        $gracz['hp_aktualne']     = $hp_safe;
        $gracz['energia_aktualna']= max(0, $gracz['energia_aktualna'] - $en_koszt);
        $gracz['exp']            += $sesja['exp_total'];
        $gracz['gotowka']        += $sesja['kasa_total'];
        $gracz['uniki']           = $new_un;
        $gracz['walka_bronia']    = $new_wb;
        $gracz['uniki_male_kumulacja'] = $kum_un;
        $gracz['walka_male_kumulacja'] = $kum_wb;

        $podsumowanie = $sesja + [
            'przyr_un'=>$przyr_un, 'przyr_wb'=>$przyr_wb,
            'kum_un'=>$kum_un,    'kum_wb'=>$kum_wb,
            'p_hp_koniec'=>$hp_safe
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════
//  OBLICZENIA DLA RENDERU (aktualny stan gracza)
// ═══════════════════════════════════════════════════════════════════
$moje_trafienie  = oblicz_trafienie($gracz['sila'], (float)$gracz['walka_bronia'], $gracz['poziom'], $gracz['bonus_atak']);
$moja_obrona     = $gracz['wytrzymalosc'] + $gracz['bonus_obrona'];
$moj_sr_random   = sredni_random($gracz['poziom']);
$prog_un_teraz   = prog_dla_skilla((float)$gracz['uniki']);
$prog_wb_teraz   = prog_dla_skilla((float)$gracz['walka_bronia']);

// Dla każdego wroga wyliczamy dane analizatora taktycznego
function analiza_wroga(array $w, float $moje_traf, float $moje_uniki, int $sila, int $bonus_a, int $lvl, float $sr_rand): array {
    $hit_nasz  = szansa_trafienia($moje_traf, $w['uniki']);
    $hit_wroga = szansa_trafienia($w['uniki'], $moje_uniki);
    $avg_dmg   = max(1, ($sila + $bonus_a + $sr_rand) - $w['obrona']);
    $rundy_est = $avg_dmg > 0 && $hit_nasz > 0 ? $w['hp'] / ($avg_dmg * ($hit_nasz/100)) : 99;

    // Rating farmy — im bliżej 24 rund, tym lepiej do farmy uników
    if     ($rundy_est < 3)   $rating = ['too_easy', 'Za łatwy'];
    elseif ($rundy_est < 10)  $rating = ['easy',     'Łatwy cel'];
    elseif ($rundy_est < 22)  $rating = ['good',     'Dobra farma'];
    elseif ($rundy_est <= 26) $rating = ['optimal',  '★ OPTYMALNA FARMA'];
    elseif ($rundy_est <= 50) $rating = ['hard',     'Trudny — trening'];
    else                      $rating = ['too_hard', 'Za trudny'];

    return compact('hit_nasz','hit_wroga','avg_dmg','rundy_est','rating');
}
$analizy = [];
foreach ($lista_wrogow as $w) {
    $analizy[$w['id']] = analiza_wroga($w, $moje_trafienie, (float)$gracz['uniki'], $gracz['sila'], $gracz['bonus_atak'], $gracz['poziom'], $moj_sr_random);
}
?>

<style>
/* ══════════════════════════════════════════════════════════════════
   DOKI.PHP — ARENA ZAUŁKÓW · CYBERPUNK NYC
══════════════════════════════════════════════════════════════════ */

/* ── NAGŁÓWEK ARENY ───────────────────────────────────────────── */
.doki-head{
    background:
        linear-gradient(180deg, rgba(10,5,10,0.6), rgba(5,2,5,0.85)),
        repeating-linear-gradient(45deg, rgba(255,23,68,0.03) 0 10px, transparent 10px 20px);
    border:1px solid var(--border-mid);border-radius:2px;
    padding:36px 40px;margin-bottom:22px;position:relative;overflow:hidden;
    box-shadow:0 0 40px rgba(255,23,68,0.15);
}
.doki-head::before{
    content:'';position:absolute;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,var(--neon-red),transparent);
    box-shadow:0 0 10px var(--neon-red);
}
.doki-head::after{
    content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
    background:linear-gradient(90deg,transparent,var(--neon-red-hot),transparent);
}
.doki-head .eyebrow{
    color:var(--neon-red);font-family:'JetBrains Mono',monospace;
    font-size:.75em;letter-spacing:4px;text-transform:uppercase;margin-bottom:8px;
    text-shadow:0 0 6px rgba(255,23,68,0.5);
}
.doki-head h1{
    font-family:'Oswald',sans-serif;color:#fff;font-size:3em;font-weight:500;
    text-transform:uppercase;letter-spacing:4px;line-height:1;
    text-shadow:0 0 20px rgba(255,23,68,0.6),0 0 40px rgba(255,23,68,0.3);
}
.doki-head .sub{
    color:var(--txt-dim);margin-top:10px;font-size:.95em;
    font-family:'Rajdhani',sans-serif;letter-spacing:1px;
}

/* ── PANEL TAKTYCZNY (HP/EN + statystyki bojowe + ammo + apteczka) ── */
.taktyczny{
    background:rgba(10,6,12,0.55);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:18px 22px;margin-bottom:20px;
    display:grid;grid-template-columns:1.2fr 1fr auto;gap:22px;align-items:center;
}
.taktyczny-stats{display:flex;flex-direction:column;gap:6px}
.tk-row{display:flex;justify-content:space-between;font-size:.85em;color:var(--txt-dim);font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px}
.tk-row b{color:#fff;font-family:'JetBrains Mono',monospace;letter-spacing:0;font-weight:500}
.tk-row .v-red  {color:var(--neon-red-hot);text-shadow:0 0 5px rgba(255,61,94,0.4)}
.tk-row .v-cyan {color:var(--neon-cyan);text-shadow:0 0 5px rgba(74,214,255,0.4)}
.tk-row .v-ember{color:var(--neon-ember);text-shadow:0 0 5px rgba(255,122,61,0.4)}
.tk-row .v-green{color:var(--neon-green);text-shadow:0 0 5px rgba(90,255,154,0.4)}

.taktyczny-arsenal{
    border-left:1px dashed rgba(255,23,68,0.15);padding-left:22px;
    display:flex;flex-direction:column;gap:10px;
}
.tk-label{
    font-family:'JetBrains Mono',monospace;font-size:.68em;
    color:var(--txt-mute);letter-spacing:2.5px;text-transform:uppercase;margin-bottom:3px;
}
.ammo-select{
    background:rgba(0,0,0,0.7);color:var(--neon-cyan);
    border:1px solid rgba(74,214,255,0.4);padding:10px 12px;
    font-family:'Oswald',sans-serif;font-size:.95em;text-transform:uppercase;
    letter-spacing:1.5px;border-radius:2px;outline:none;cursor:pointer;width:100%;
    text-shadow:0 0 6px rgba(74,214,255,0.3);
    transition:box-shadow .3s,border-color .3s;
}
.ammo-select:focus,.ammo-select:hover{
    box-shadow:0 0 16px rgba(74,214,255,0.4);border-color:var(--neon-cyan);
}

.btn-apteczka{
    background:rgba(255,23,68,0.1);border:1px solid var(--border-mid);
    color:var(--neon-red-hot);padding:14px 20px;cursor:pointer;
    font-family:'Oswald',sans-serif;font-weight:500;
    text-transform:uppercase;letter-spacing:2px;font-size:.92em;
    border-radius:2px;transition:.3s;white-space:nowrap;
}
.btn-apteczka:hover:not(:disabled){
    background:var(--neon-red);color:#fff;
    box-shadow:0 0 22px rgba(255,23,68,0.7);text-shadow:0 0 8px #fff;
}
.btn-apteczka:disabled{border-color:rgba(255,255,255,0.07);color:var(--txt-mute);cursor:not-allowed;background:rgba(0,0,0,0.4)}

/* ── FARMA / KUMULACJA MAŁYCH KRYTYKÓW ───────────────────────── */
.farma{
    background:rgba(10,6,12,0.55);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;padding:20px 24px;
    margin-bottom:20px;position:relative;
}
.farma::before{
    content:'';position:absolute;top:0;left:0;width:32px;height:1px;
    background:var(--neon-red);box-shadow:0 0 6px var(--neon-red);
}
.farma-tytul{
    font-family:'Oswald',sans-serif;font-size:.9em;color:#fff;
    text-transform:uppercase;letter-spacing:2.5px;margin-bottom:14px;
    padding-bottom:10px;border-bottom:1px solid var(--border-soft);
    display:flex;justify-content:space-between;align-items:center;
}
.farma-tytul .hint{
    font-family:'JetBrains Mono',monospace;font-size:.72em;font-weight:400;
    text-transform:none;color:var(--txt-dim);letter-spacing:.5px;
}
.farma-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.farma-stat{
    background:rgba(0,0,0,0.4);border:1px solid rgba(255,23,68,0.08);
    border-radius:2px;padding:12px 14px;
}
.fs-head{
    display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;
}
.fs-head .name{
    font-family:'Oswald',sans-serif;color:#fff;font-size:1em;
    text-transform:uppercase;letter-spacing:1.5px;
}
.fs-head .val{
    font-family:'JetBrains Mono',monospace;font-size:1.15em;
    color:var(--neon-ember);text-shadow:0 0 8px rgba(255,122,61,0.5);font-weight:500;
}
.fs-bar-wrap{background:rgba(0,0,0,0.7);border:1px solid rgba(255,23,68,0.1);height:8px;overflow:hidden;position:relative}
.fs-bar{height:100%;transition:width .6s}
.fs-bar.un{background:linear-gradient(90deg,#003344,var(--neon-cyan));box-shadow:0 0 8px rgba(74,214,255,0.5)}
.fs-bar.wb{background:linear-gradient(90deg,#5a0010,var(--neon-red-hot));box-shadow:0 0 8px rgba(255,23,68,0.5)}
.fs-caption{
    display:flex;justify-content:space-between;margin-top:6px;
    font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-mute);letter-spacing:1px;
}

/* ── ANALIZATOR TAKTYCZNY (portret + liczby) ──────────────────── */
.analiza-wrap{
    display:grid;grid-template-columns:260px 1fr;gap:22px;
    background:rgba(10,6,12,0.55);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;padding:24px;
    margin-bottom:20px;
}
.an-portret{
    width:220px;height:220px;margin:0 auto;
    border:2px solid var(--border-mid);border-radius:2px;
    background:#050305 center/cover no-repeat;
    box-shadow:0 0 28px rgba(255,23,68,0.25),inset 0 0 20px rgba(0,0,0,0.6);
    transition:.3s;
}
.an-nazwa{
    text-align:center;margin-top:12px;
    font-family:'Oswald',sans-serif;color:#fff;font-size:1.5em;
    text-transform:uppercase;letter-spacing:2px;
    text-shadow:0 0 15px rgba(255,23,68,0.5);
}
.an-pancerz{text-align:center;margin-top:8px}
.badge-panc{
    display:inline-block;padding:4px 12px;border-radius:2px;
    font-family:'Oswald',sans-serif;font-size:.78em;font-weight:600;
    letter-spacing:2px;text-transform:uppercase;color:#000;
}
.an-lewa{display:flex;flex-direction:column;align-items:center;gap:6px}

.an-prawa{display:flex;flex-direction:column;gap:12px;min-width:0}
.an-rating{
    padding:8px 14px;border-radius:2px;border:1px solid;
    font-family:'Oswald',sans-serif;font-size:.9em;letter-spacing:2px;
    text-transform:uppercase;font-weight:500;text-align:center;
}
.rt-optimal {color:var(--neon-green);border-color:var(--neon-green);background:rgba(90,255,154,0.08);box-shadow:0 0 15px rgba(90,255,154,0.2);text-shadow:0 0 6px rgba(90,255,154,0.5)}
.rt-good    {color:var(--neon-cyan);border-color:rgba(74,214,255,0.5);background:rgba(74,214,255,0.05)}
.rt-easy    {color:var(--txt-dim);border-color:rgba(255,255,255,0.1);background:rgba(255,255,255,0.02)}
.rt-too_easy{color:var(--txt-mute);border-color:rgba(255,255,255,0.05);background:transparent}
.rt-hard    {color:var(--neon-ember);border-color:rgba(255,122,61,0.4);background:rgba(255,122,61,0.05)}
.rt-too_hard{color:var(--neon-red-hot);border-color:var(--border-mid);background:rgba(255,23,68,0.08)}

.an-liczby{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.an-cell{
    background:rgba(0,0,0,0.4);border:1px solid rgba(255,23,68,0.06);
    border-radius:2px;padding:10px 12px;
}
.an-cell .lbl{
    font-family:'JetBrains Mono',monospace;font-size:.68em;
    color:var(--txt-mute);letter-spacing:2px;text-transform:uppercase;margin-bottom:3px;
}
.an-cell .v{
    font-family:'Oswald',sans-serif;font-size:1.3em;color:#fff;font-weight:500;letter-spacing:1px;
}
.an-placeholder{color:var(--txt-mute);font-style:italic;font-family:'JetBrains Mono',monospace;font-size:.85em;text-align:center;padding:40px 20px}

/* ── TABELA WROGÓW ──────────────────────────────────────────── */
.bestiar{
    background:rgba(10,6,12,0.55);backdrop-filter:blur(6px);
    border:1px solid var(--border-soft);border-radius:2px;padding:0;overflow:hidden;
}
.bestiar-h{
    padding:14px 18px;background:rgba(0,0,0,0.4);
    border-bottom:1px solid var(--border-soft);
    font-family:'Oswald',sans-serif;color:#fff;font-size:1em;
    text-transform:uppercase;letter-spacing:2.5px;
    display:flex;justify-content:space-between;align-items:center;
}
.bestiar-h .note{font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);letter-spacing:1px;text-transform:none}

.t-wr{width:100%;border-collapse:collapse;font-size:.92em}
.t-wr th{
    background:rgba(0,0,0,0.5);padding:12px 14px;text-align:left;
    color:var(--neon-red);font-family:'Oswald',sans-serif;
    text-transform:uppercase;font-size:.75em;letter-spacing:2px;
    border-bottom:1px solid var(--border-mid);font-weight:500;
    text-shadow:0 0 6px rgba(255,23,68,0.4);
}
.t-wr td{
    padding:12px 14px;border-bottom:1px dashed rgba(255,23,68,0.07);
    color:var(--txt-main);vertical-align:middle;transition:.2s;
}
.t-wr tr.w-row{cursor:pointer;transition:background .2s}
.t-wr tr.w-row:hover{background:rgba(255,23,68,0.06)}
.t-wr tr.w-row:hover td{color:#fff}
.t-wr tr.w-row.selected{background:rgba(255,23,68,0.1)}
.t-wr tr.w-row.selected td{color:#fff;border-bottom-color:var(--border-mid)}

.td-nazwa{font-family:'Oswald',sans-serif;font-size:1.05em;letter-spacing:.5px;color:#fff}
.td-lvl{font-family:'JetBrains Mono',monospace;color:var(--neon-ember);font-weight:500}
.td-stats{font-family:'JetBrains Mono',monospace;font-size:.85em;line-height:1.5}
.td-stats .s-hp  {color:var(--neon-red-hot)}
.td-stats .s-atk {color:var(--neon-ember)}
.td-stats .s-zr  {color:var(--neon-cyan)}
.td-loot{font-size:.85em;color:var(--txt-dim)}
.td-loot .name{color:var(--neon-ember);font-family:'Oswald',sans-serif;letter-spacing:.5px}
.td-loot .chance{color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.85em}

.td-akcja{display:flex;gap:6px;align-items:center;justify-content:flex-end}
.ilosc-sel{
    background:rgba(0,0,0,0.7);color:#fff;
    border:1px solid rgba(255,23,68,0.25);padding:6px 8px;
    font-family:'JetBrains Mono',monospace;font-size:.9em;
    border-radius:2px;outline:none;cursor:pointer;width:58px;text-align:center;
}
.ilosc-sel:hover,.ilosc-sel:focus{border-color:var(--neon-red);box-shadow:0 0 8px rgba(255,23,68,0.3)}
.btn-walka{
    background:rgba(0,0,0,0.5);border:1px solid var(--border-mid);
    color:var(--neon-red-hot);padding:8px 16px;
    font-family:'Oswald',sans-serif;text-transform:uppercase;font-weight:500;
    letter-spacing:1.5px;font-size:.85em;cursor:pointer;border-radius:2px;transition:.3s;
}
.btn-walka:hover:not(:disabled){
    background:var(--neon-red);color:#fff;
    box-shadow:0 0 16px rgba(255,23,68,0.6);text-shadow:0 0 6px #fff;
}
.btn-walka:disabled{border-color:rgba(255,255,255,0.07);color:var(--txt-mute);cursor:not-allowed}

.rating-mini{
    display:inline-block;padding:2px 8px;border-radius:2px;
    font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1.5px;
    text-transform:uppercase;white-space:nowrap;
    border:1px solid;margin-top:3px;
}
.rm-optimal {color:var(--neon-green);border-color:var(--neon-green);background:rgba(90,255,154,0.08)}
.rm-good    {color:var(--neon-cyan);border-color:rgba(74,214,255,0.3);background:rgba(74,214,255,0.04)}
.rm-easy    {color:var(--txt-dim);border-color:rgba(255,255,255,0.08)}
.rm-too_easy{color:var(--txt-mute);border-color:rgba(255,255,255,0.04);opacity:.7}
.rm-hard    {color:var(--neon-ember);border-color:rgba(255,122,61,0.3)}
.rm-too_hard{color:var(--neon-red-hot);border-color:var(--border-mid);background:rgba(255,23,68,0.06)}

/* ── LOG WALKI ──────────────────────────────────────────────── */
.log-box{
    background:rgba(5,3,6,0.85);backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);border-radius:2px;padding:20px 22px;
    font-family:'JetBrains Mono',monospace;font-size:.9em;line-height:1.7;
    max-height:400px;overflow-y:auto;margin-bottom:20px;
    box-shadow:inset 0 0 30px rgba(0,0,0,0.7);
}
.log-box .lg-r{color:var(--txt-mute);letter-spacing:1px;margin-right:4px;font-size:.85em}
.log-box .lg-walka-h{
    color:var(--neon-red-hot);margin:12px 0 8px;padding:6px 10px;
    background:rgba(255,23,68,0.08);border-left:2px solid var(--neon-red);
    font-family:'Oswald',sans-serif;letter-spacing:1.5px;font-size:1em;
    text-transform:uppercase;
}
.log-box .lg-hit  {color:var(--neon-green);font-weight:500}
.log-box .lg-kryt {color:var(--neon-ember);font-weight:500;text-shadow:0 0 6px rgba(255,122,61,0.5)}
.log-box .lg-miss {color:var(--txt-mute);font-style:italic}
.log-box .lg-rana {color:var(--neon-red-hot);font-weight:500}
.log-box .lg-unik {color:var(--neon-cyan);font-weight:500;text-shadow:0 0 5px rgba(74,214,255,0.4)}
.log-box .lg-klat {margin:10px 0;padding:8px 12px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:1em}
.log-box .lg-win  {color:var(--neon-green);background:rgba(90,255,154,0.07);border-left:2px solid var(--neon-green)}
.log-box .lg-loss {color:var(--neon-red-hot);background:rgba(255,23,68,0.08);border-left:2px solid var(--neon-red)}
.log-box .lg-sep  {color:var(--txt-mute);margin:4px 0;opacity:.4}

/* ── PODSUMOWANIE SESJI ─────────────────────────────────────── */
.podsum{
    background:rgba(10,6,12,0.7);backdrop-filter:blur(6px);
    border:1px solid var(--border-mid);border-radius:2px;
    padding:22px;margin-bottom:22px;
    box-shadow:0 0 25px rgba(255,23,68,0.15);
}
.podsum-h{
    font-family:'Oswald',sans-serif;color:#fff;font-size:1.15em;
    text-transform:uppercase;letter-spacing:2.5px;margin-bottom:16px;
    padding-bottom:10px;border-bottom:1px solid var(--border-mid);
    text-shadow:0 0 10px rgba(255,23,68,0.5);
}
.podsum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px}
.ps-cell{background:rgba(0,0,0,0.4);border:1px solid rgba(255,23,68,0.08);border-radius:2px;padding:10px 12px}
.ps-cell .l{font-family:'JetBrains Mono',monospace;font-size:.68em;color:var(--txt-mute);letter-spacing:2px;text-transform:uppercase;margin-bottom:2px}
.ps-cell .v{font-family:'Oswald',sans-serif;font-size:1.25em;color:#fff;letter-spacing:1px;font-weight:500}
.ps-cell.accent .v{color:var(--neon-ember);text-shadow:0 0 8px rgba(255,122,61,0.5)}
.ps-cell.green  .v{color:var(--neon-green);text-shadow:0 0 8px rgba(90,255,154,0.5)}
.ps-cell.cyan   .v{color:var(--neon-cyan);text-shadow:0 0 8px rgba(74,214,255,0.5)}
.ps-cell.red    .v{color:var(--neon-red-hot);text-shadow:0 0 8px rgba(255,23,68,0.5)}
.ps-loot{margin-top:12px;padding-top:12px;border-top:1px dashed rgba(255,23,68,0.15);font-family:'Oswald',sans-serif;font-size:.95em;letter-spacing:1px;color:var(--neon-ember);text-shadow:0 0 8px rgba(255,122,61,0.4)}
.ps-loot .item{display:inline-block;margin-right:14px}

/* ── ALERTY ─────────────────────────────────────────────────── */
.alert-ok{
    background:rgba(90,255,154,0.08);border:1px solid var(--neon-green);
    color:var(--neon-green);padding:14px 18px;margin-bottom:18px;border-radius:2px;
    text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1.5px;font-size:1em;
    box-shadow:0 0 18px rgba(90,255,154,0.15);
}
.alert-err{
    background:rgba(255,23,68,0.1);border:1px solid var(--border-mid);
    color:var(--neon-red-hot);padding:14px 18px;margin-bottom:18px;border-radius:2px;
    text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1.5px;font-size:1em;
    box-shadow:0 0 18px rgba(255,23,68,0.15);
}

@media(max-width:900px){
    .taktyczny{grid-template-columns:1fr}
    .analiza-wrap{grid-template-columns:1fr}
    .farma-grid{grid-template-columns:1fr}
}
</style>

<div class="doki-head">
    <div class="eyebrow">// SECTOR 7 · DOKS · ARENA</div>
    <h1>Arena Zaułków</h1>
    <div class="sub">Farma uników i walki wręcz. Odpowiednia amunicja to różnica między życiem a śmiercią. Cel optymalnej farmy: walka trwa 24 rundy.</div>
</div>

<?php echo $komunikat; ?>

<?php if ($podsumowanie): ?>
<div class="podsum">
    <div class="podsum-h">▸ Raport z sesji · <?php echo $podsumowanie['walk_wyk']; ?>/<?php echo $_POST['ilosc_walk'] ?? 1; ?> walk</div>
    <div class="podsum-grid">
        <div class="ps-cell green"><div class="l">Wygrane</div><div class="v"><?php echo $podsumowanie['wygranych']; ?></div></div>
        <div class="ps-cell red"><div class="l">Przegrane</div><div class="v"><?php echo $podsumowanie['przegranych']; ?></div></div>
        <div class="ps-cell accent"><div class="l">EXP</div><div class="v">+<?php echo $podsumowanie['exp_total']; ?></div></div>
        <div class="ps-cell accent"><div class="l">Gotówka</div><div class="v">+<?php echo $podsumowanie['kasa_total']; ?> $</div></div>
        <div class="ps-cell cyan"><div class="l">Małe uniki</div><div class="v"><?php echo $podsumowanie['male_un']; ?></div></div>
        <div class="ps-cell red"><div class="l">Małe WB</div><div class="v"><?php echo $podsumowanie['male_wb']; ?></div></div>
        <div class="ps-cell cyan"><div class="l">Przyrost Uników</div><div class="v">+<?php echo number_format($podsumowanie['przyr_un'],3); ?></div></div>
        <div class="ps-cell red"><div class="l">Przyrost WB</div><div class="v">+<?php echo number_format($podsumowanie['przyr_wb'],3); ?></div></div>
        <div class="ps-cell"><div class="l">Rund łącznie</div><div class="v"><?php echo $podsumowanie['rundy_total']; ?></div></div>
        <div class="ps-cell"><div class="l">DMG zadane</div><div class="v"><?php echo $podsumowanie['dmg_zadanych']; ?></div></div>
        <div class="ps-cell"><div class="l">DMG otrzymane</div><div class="v"><?php echo $podsumowanie['dmg_otrzymanych']; ?></div></div>
        <div class="ps-cell"><div class="l">HP na koniec</div><div class="v"><?php echo $podsumowanie['p_hp_koniec']; ?></div></div>
    </div>
    <?php if (!empty($podsumowanie['loot'])): ?>
        <div class="ps-loot">
            ◈ ŁUPY:
            <?php foreach ($podsumowanie['loot'] as $nazwa => $ile): ?>
                <span class="item"><?php echo htmlspecialchars($nazwa); ?> ×<?php echo $ile; ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="taktyczny">
    <div class="taktyczny-stats">
        <div class="tk-row"><span>Zdrowie</span><b class="v-red"><?php echo $gracz['hp_aktualne']; ?> / <?php echo $gracz['hp_max']; ?></b></div>
        <div class="tk-row"><span>Energia</span><b class="v-cyan"><?php echo $gracz['energia_aktualna']; ?></b></div>
        <div class="tk-row"><span>Trafienie</span><b class="v-ember"><?php echo round($moje_trafienie,1); ?></b></div>
        <div class="tk-row"><span>Obrona</span><b class="v-cyan"><?php echo $moja_obrona; ?></b></div>
        <div class="tk-row"><span>Uniki · WB</span><b><?php echo number_format($gracz['uniki'],2); ?> · <?php echo number_format($gracz['walka_bronia'],2); ?></b></div>
    </div>

    <div class="taktyczny-arsenal">
        <div class="tk-label">Aktywny Magazynek</div>
        <select id="master_ammo" class="ammo-select" onchange="synchronizujAmunicje()">
            <option value="Kinetyczna">Kinetyczna · Standard</option>
            <option value="Toksyczna">Toksyczna · ×1.5 Biologia</option>
            <option value="Przeciwpancerna">Przeciwpancerna · ×1.5 Pancerz</option>
            <option value="EMP">EMP · ×1.5 Cybernetyka</option>
        </select>
        <div class="tk-label" style="margin-top:4px">Średni random obrażeń: <span style="color:var(--neon-ember)"><?php echo round($moj_sr_random,1); ?></span> (poziom <?php echo $gracz['poziom']; ?>)</div>
    </div>

    <form method="POST" style="margin:0">
        <button type="submit" name="uzyj_apteczki" class="btn-apteczka" <?php if($gracz['apteczki']<=0||$gracz['hp_aktualne']==$gracz['hp_max']) echo 'disabled'; ?>>
            💉 Apteczka ×<?php echo $gracz['apteczki']; ?>
        </button>
    </form>
</div>

<div class="farma">
    <div class="farma-tytul">
        ▸ Status Farmy — Małe Krytyki
        <span class="hint">// mały krytyk = 1 udane trafienie/unik w walce</span>
    </div>
    <div class="farma-grid">
        <div class="farma-stat">
            <div class="fs-head">
                <span class="name">👁 Uniki (duże)</span>
                <span class="val"><?php echo number_format($gracz['uniki'],2); ?></span>
            </div>
            <div class="fs-bar-wrap">
                <div class="fs-bar un" style="width:<?php echo min(100,($gracz['uniki_male_kumulacja']/$prog_un_teraz)*100); ?>%"></div>
            </div>
            <div class="fs-caption">
                <span>MAŁE: <?php echo $gracz['uniki_male_kumulacja']; ?> / <?php echo $prog_un_teraz; ?></span>
                <span>+0.1 przy progu</span>
            </div>
        </div>
        <div class="farma-stat">
            <div class="fs-head">
                <span class="name">⚔️ Walka Bronią (duże)</span>
                <span class="val"><?php echo number_format($gracz['walka_bronia'],2); ?></span>
            </div>
            <div class="fs-bar-wrap">
                <div class="fs-bar wb" style="width:<?php echo min(100,($gracz['walka_male_kumulacja']/$prog_wb_teraz)*100); ?>%"></div>
            </div>
            <div class="fs-caption">
                <span>MAŁE: <?php echo $gracz['walka_male_kumulacja']; ?> / <?php echo $prog_wb_teraz; ?></span>
                <span>+0.1 przy progu</span>
            </div>
        </div>
    </div>
</div>

<div class="analiza-wrap">
    <div class="an-lewa">
        <div class="an-portret" id="an-portret" style="background-image:url('https://via.placeholder.com/220/0a0508/2a1a20?text=WYBIERZ')"></div>
        <div class="an-nazwa" id="an-nazwa">Wybierz cel z listy</div>
        <div class="an-pancerz" id="an-pancerz"></div>
    </div>
    <div class="an-prawa">
        <div class="an-rating rt-easy" id="an-rating">// Najedź kursorem na wroga w tabeli</div>
        <div class="an-liczby" id="an-liczby">
            <div class="an-placeholder" style="grid-column:span 2">// Waiting for target lock...</div>
        </div>
    </div>
</div>

<?php if (!empty($log_walki)): ?>
<div class="log-box">
    <?php foreach($log_walki as $linia) echo $linia . "<br>"; ?>
</div>
<?php endif; ?>

<div class="bestiar">
    <div class="bestiar-h">
        ▸ Bestiariusz
        <span class="note">// klikaj na wroga dla analizy · wybierz ilość walk i atakuj</span>
    </div>

    <?php if (empty($lista_wrogow)): ?>
        <p style="color:var(--neon-red-hot);text-align:center;padding:30px">// Brak wrogów w matrycy.</p>
    <?php else: ?>
    <table class="t-wr">
        <thead>
            <tr>
                <th>Cel</th>
                <th>Lvl</th>
                <th>Typ Pancerza</th>
                <th>Staty</th>
                <th>Łup</th>
                <th>Rating</th>
                <th style="text-align:right">Akcja</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista_wrogow as $wrog):
            $id_w = (int)$wrog['id'];
            $a = $analizy[$id_w];

            // Kolor badge pancerza
            $panc_kol = match($wrog['typ_pancerza']) {
                'Biologiczny'   => '#5aff9a',
                'Opancerzony'   => '#ff7a3d',
                'Cybernetyczny' => '#4ad6ff',
                default         => '#8a818e'
            };

            // Ścieżka zdjęcia wroga
            $sciezka = "https://via.placeholder.com/220/0a0508/2a1a20?text=" . urlencode($wrog['nazwa']);
            foreach (['png','jpg','jpeg','gif','webp'] as $ext) {
                if (file_exists("img/wrogowie/{$id_w}.{$ext}")) { $sciezka = "img/wrogowie/{$id_w}.{$ext}"; break; }
            }

            $loot_info = '<span style="color:var(--txt-mute)">—</span>';
            if (!empty($wrog['loot_nazwa'])) {
                $loot_info = '<span class="name">' . htmlspecialchars($wrog['loot_nazwa']) . '</span><br><span class="chance">' . $wrog['loot_szansa'] . '% szans</span>';
            }

            // Dane dla JS analizatora (zakodowane w data-*)
            $json_data = htmlspecialchars(json_encode([
                'nazwa'     => $wrog['nazwa'],
                'lvl'       => (int)$wrog['poziom'],
                'hp'        => (int)$wrog['hp'],
                'atak'      => (int)$wrog['atak'],
                'obrona'    => (int)$wrog['obrona'],
                'zr'        => (int)$wrog['uniki'],
                'pancerz'   => $wrog['typ_pancerza'],
                'panc_kol'  => $panc_kol,
                'portret'   => $sciezka,
                'hit_nasz'  => round($a['hit_nasz'],1),
                'hit_wroga' => round($a['hit_wroga'],1),
                'avg_dmg'   => round($a['avg_dmg'],1),
                'rundy_est' => round($a['rundy_est'],1),
                'rating'    => $a['rating'][0],
                'rating_txt'=> $a['rating'][1],
            ]), ENT_QUOTES, 'UTF-8');

        ?>
            <tr class="w-row" data-wrog='<?php echo $json_data; ?>' onmouseover="pokazAnalize(this)" onclick="pokazAnalize(this)">
                <td class="td-nazwa"><?php echo htmlspecialchars($wrog['nazwa']); ?></td>
                <td class="td-lvl">Lvl <?php echo $wrog['poziom']; ?></td>
                <td><span class="badge-panc" style="background:<?php echo $panc_kol; ?>;box-shadow:0 0 10px <?php echo $panc_kol; ?>"><?php echo $wrog['typ_pancerza']; ?></span></td>
                <td class="td-stats">
                    <span class="s-hp">HP <?php echo $wrog['hp']; ?></span> ·
                    <span class="s-atk">ATK <?php echo $wrog['atak']; ?></span> ·
                    <span class="s-zr">ZR <?php echo $wrog['uniki']; ?></span>
                </td>
                <td class="td-loot"><?php echo $loot_info; ?></td>
                <td>
                    <span class="rating-mini rm-<?php echo $a['rating'][0]; ?>"><?php echo $a['rating'][1]; ?></span>
                    <div style="font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--txt-mute);margin-top:3px">
                        est. <?php echo round($a['rundy_est'],1); ?>R · <?php echo round($a['hit_nasz'],0); ?>% trafienia
                    </div>
                </td>
                <td class="td-akcja">
                    <form method="POST" style="display:flex;gap:6px;margin:0;align-items:center">
                        <input type="hidden" name="id_wroga" value="<?php echo $id_w; ?>">
                        <input type="hidden" name="typ_amunicji" class="ukryta-amunicja" value="Kinetyczna">
                        <select name="ilosc_walk" class="ilosc-sel" title="Ilość walk (1-10)">
                            <option value="1">×1</option>
                            <option value="3">×3</option>
                            <option value="5">×5</option>
                            <option value="10">×10</option>
                        </select>
                        <button type="submit" name="walcz" class="btn-walka">⚔ Walcz</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if (!$ma_kolumny_kumulacji): ?>
<div style="margin-top:20px;padding:14px 18px;background:rgba(255,122,61,0.08);border:1px solid rgba(255,122,61,0.3);border-radius:2px;color:var(--neon-ember);font-family:'JetBrains Mono',monospace;font-size:.8em;letter-spacing:.5px;line-height:1.7">
    ⚠ <b>System Farmy wyłączony:</b> w tabeli <code>gracze</code> brakuje kolumn <code>uniki_male_kumulacja</code> i <code>walka_male_kumulacja</code>. Dodaj je przez ALTER TABLE (patrz instrukcja), aby małe krytyki zaczęły persystować między walkami.
</div>
<?php endif; ?>

<script>
/* ═══ ANALIZATOR TAKTYCZNY ══════════════════════════════════════ */
function pokazAnalize(row) {
    // Odznacz poprzedni
    document.querySelectorAll('.w-row.selected').forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');

    const d = JSON.parse(row.getAttribute('data-wrog'));

    // Portret + nazwa
    document.getElementById('an-portret').style.backgroundImage = "url('" + d.portret + "')";
    document.getElementById('an-nazwa').textContent = d.nazwa + " · Lvl " + d.lvl;
    document.getElementById('an-pancerz').innerHTML =
        "<span class='badge-panc' style='background:" + d.panc_kol + ";box-shadow:0 0 10px " + d.panc_kol + "'>" + d.pancerz + "</span>";

    // Rating
    const rat = document.getElementById('an-rating');
    rat.className = 'an-rating rt-' + d.rating;
    rat.textContent = d.rating_txt + ' · szacunkowo ' + d.rundy_est + ' rund do zabicia';

    // Liczby
    document.getElementById('an-liczby').innerHTML =
        "<div class='an-cell'><div class='lbl'>Trafimy go</div><div class='v'>" + d.hit_nasz + "%</div></div>" +
        "<div class='an-cell'><div class='lbl'>On trafi nas</div><div class='v'>" + d.hit_wroga + "%</div></div>" +
        "<div class='an-cell'><div class='lbl'>Śr. dmg / cios</div><div class='v'>" + d.avg_dmg + "</div></div>" +
        "<div class='an-cell'><div class='lbl'>HP / ATK</div><div class='v'>" + d.hp + " / " + d.atak + "</div></div>";
}

/* ═══ SYNCHRONIZACJA AMUNICJI ═══════════════════════════════════ */
function synchronizujAmunicje() {
    const a = document.getElementById('master_ammo').value;
    document.querySelectorAll('.ukryta-amunicja').forEach(i => i.value = a);
}
synchronizujAmunicje();
</script>