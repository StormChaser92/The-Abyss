<?php
/* the-abyss/pages/doki.php
   Arena Zaułków — walka z NPC na nowym modelu.
   Cała matematyka: includes/arena_walka.php (100 rund, remis, sufit 100 małych).
   Amunicja i typy pancerza sci-fi odeszły — jest kategoria ciosu vs typ przeciwnika. */

require_once "db.php";
require_once "config/pochodzenia.php";
require_once "config/miasta.php";
require_once "includes/arena_walka.php";
require_once "includes/bronie_katalog.php";

$id_gracza    = $_SESSION['id_gracza'];
$komunikat    = "";
$log_walki    = [];
$podsumowanie = null;

$kolumny = "id, poziom, exp, gotowka, hp_aktualne, hp_max, energia_aktualna,
            sila, wytrzymalosc, zrecznosc, walka_bronia, uniki,
            bonus_atak, bonus_obrona, bonus_szybkosc, bonus_unik,
            bron_trwalosc, bron_trwalosc_max, pancerz_trwalosc, pancerz_trwalosc_max,
            apteczki, bron_zalozona, arena_cios, arena_auto_apteczka, obecne_miasto,
            uniki_male_kumulacja, walka_male_kumulacja";
$gracz = $polaczenie->query("SELECT $kolumny FROM gracze WHERE id=$id_gracza")->fetch_assoc();

/* Wrogowie tylko z miasta, w którym stoi postać. */
$miasto = strtoupper(trim($gracz['obecne_miasto'] ?? ''));
$m_esc  = $polaczenie->real_escape_string($miasto);
$kraj   = arena_miasto_kraj($gracz['obecne_miasto'] ?? null);

$lista_wrogow = [];
$q = $polaczenie->query("SELECT * FROM wrogowie WHERE UPPER(miasto)='$m_esc' ORDER BY poziom ASC");
while ($q && $w = $q->fetch_assoc()) $lista_wrogow[(int)$w['id']] = $w;

/* ── apteczka ──────────────────────────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['uzyj_apteczki'])) {
    if ((int)$gracz['apteczki'] > 0 && (int)$gracz['hp_aktualne'] < (int)$gracz['hp_max']) {
        $nowe = min((int)$gracz['hp_max'], (int)$gracz['hp_aktualne'] + 50);
        $polaczenie->query("UPDATE gracze SET hp_aktualne=$nowe, apteczki=apteczki-1 WHERE id=$id_gracza");
        $gracz['hp_aktualne'] = $nowe; $gracz['apteczki']--;
        $komunikat = "<div class='alert-ok'>Adrenalina w żyłach. +50 HP.</div>";
    } else {
        $komunikat = "<div class='alert-err'>Jesteś w pełni zdrowy albo nie masz apteczek.</div>";
    }
}

/* ── ustawienia sesji ──────────────────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['zapisz_ustawienia'])) {
    $cios = in_array($_POST['cios'] ?? '', ['ostrze','tepe','palna','piesc'], true) ? $_POST['cios'] : 'piesc';
    $auto = isset($_POST['auto_apteczka']) ? 1 : 0;
    $polaczenie->query("UPDATE gracze SET arena_cios='$cios', arena_auto_apteczka=$auto WHERE id=$id_gracza");
    $gracz['arena_cios'] = $cios; $gracz['arena_auto_apteczka'] = $auto;
    $komunikat = "<div class='alert-ok'>Zapisane: ".eq_nazwa_ciosu_lokalne($cios).($auto ? ', auto-apteczka włączona' : ', apteczki ręcznie').".</div>";
}

function eq_nazwa_ciosu_lokalne(string $c): string {
    return ['ostrze'=>'ostrze', 'tepe'=>'tępe narzędzie', 'palna'=>'broń palna', 'piesc'=>'goła pięść'][$c] ?? 'goła pięść';
}

/* ── walka ─────────────────────────────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['walcz'])) {
    $id_wroga   = (int)($_POST['id_wroga'] ?? 0);
    $ilosc_walk = max(1, min(10, (int)($_POST['ilosc_walk'] ?? 1)));

    if (!isset($lista_wrogow[$id_wroga])) {
        $komunikat = "<div class='alert-err'>Tego przeciwnika nie ma w tym mieście.</div>";
    } elseif ((int)$gracz['energia_aktualna'] < $ilosc_walk) {
        $komunikat = "<div class='alert-err'>Masz {$gracz['energia_aktualna']} EN, potrzebujesz {$ilosc_walk}.</div>";
    } elseif ((int)$gracz['hp_aktualne'] <= 10) {
        $komunikat = "<div class='alert-err'>Ledwo stoisz. Apteczka albo Klinika.</div>";
    } else {
        $mod = [
            'cios'                  => $gracz['arena_cios'] ?: 'piesc',
            'auto_apteczka'         => (int)$gracz['arena_auto_apteczka'] === 1,
            'apteczki'              => (int)$gracz['apteczki'],
            'bonus_unik_abs'        => pochodzenie_bonus($gracz, 'unik_szansa_abs', 0),
            'dmg_melee_mult'        => pochodzenie_bonus($gracz, 'egzekutor_dmg_melee_mult', 1.0),
            'dmg_pierwsza_mult'     => pochodzenie_bonus($gracz, 'egzekutor_dmg_pierwsza_tura_mult', 1.0),
        ];

        $sesja = ['walk_wyk'=>0, 'wygranych'=>0, 'remisow'=>0, 'przegranych'=>0,
                  'exp_total'=>0, 'kasa_total'=>0, 'male_un'=>0, 'male_wb'=>0,
                  'rundy_total'=>0, 'dmg_zadanych'=>0, 'dmg_otrzymanych'=>0, 'apteczki_uzyte'=>0];

        $p_hp = (int)$gracz['hp_aktualne'];
        $apteczki_stan = (int)$gracz['apteczki'];

        for ($walka = 1; $walka <= $ilosc_walk; $walka++) {
            $wrog = $lista_wrogow[$id_wroga];
            $mod['hp_start'] = $p_hp;
            $mod['apteczki'] = $apteczki_stan;

            $w = arena_walka($gracz, $wrog, $mod);
            $n = arena_nagroda($wrog, $w);

            $p_hp = $w['hp_koniec'];
            $apteczki_stan = $w['apteczki_zostalo'];
            $sesja['rundy_total']     += $w['rundy'];
            $sesja['male_un']         += $w['male_un'];
            $sesja['male_wb']         += $w['male_wb'];
            $sesja['dmg_zadanych']    += $w['dmg_zadany'];
            $sesja['dmg_otrzymanych'] += $w['dmg_wziety'];
            $sesja['exp_total']       += $n['exp'];
            $sesja['kasa_total']      += $n['kasa'];
            $sesja['apteczki_uzyte']  += $w['apteczki_uzyte'];
            $sesja['walk_wyk']++;

            $naglowek = "<div class='lg-walka-h'>▶ Walka #$walka · <b>".htmlspecialchars($wrog['nazwa'])
                      . "</b> (lvl {$wrog['poziom']}) · ".eq_nazwa_ciosu_lokalne($mod['cios'])
                      . " ×".number_format($w['mnoznik_ciosu'], 1)
                      . " · trafiam {$w['moj_hit']}% / on {$w['jego_hit']}%</div>";
            $log_walki[] = $naglowek;

            if ($w['wynik'] === 'wygrana') {
                $sesja['wygranych']++;
                $log_walki[] = "<div class='lg-klat lg-win'>✓ Pokonany w rundzie {$w['rundy']} · +{$n['exp']} EXP · +{$n['kasa']} \$ · małe uniki: {$w['male_un']} · małe WB: {$w['male_wb']}</div>";
            } elseif ($w['wynik'] === 'remis') {
                $sesja['remisow']++;
                $log_walki[] = "<div class='lg-klat lg-remis'>◷ Sto rund bez rozstrzygnięcia · zdjęte {$n['udzial']}% jego HP · +{$n['exp']} EXP, bez gotówki · małe uniki: {$w['male_un']} · małe WB: {$w['male_wb']}</div>";
            } else {
                $sesja['przegranych']++;
                $log_walki[] = "<div class='lg-klat lg-loss'>💀 Porażka w rundzie {$w['rundy']}. Sesja przerwana. Małe uniki i tak się liczą: {$w['male_un']}</div>";
                $log_walki[] = "<details class='lg-det'><summary>dziennik rund tej walki</summary>".arena_render_dziennik($w['dziennik'])."</details>";
                break;
            }
            $log_walki[] = "<details class='lg-det'><summary>dziennik rund tej walki ({$w['rundy']})</summary>".arena_render_dziennik($w['dziennik'])."</details>";
        }

        /* trwałość sprzętu — 100 walk na sztukę */
        [$b_tr, $p_tr, $zepsute] = arena_zuzyj_sprzet($polaczenie, $id_gracza, $sesja['walk_wyk']);
        if ($zepsute) {
            $komunikat = "<div class='alert-err'>Zużyte do zera: ".implode(' i ', $zepsute)
                       . ". Sprzęt nie daje bonusów, dopóki Inżynier go nie naprawi.</div>";
        }

        /* małe → duże */
        $kum_un = (int)$gracz['uniki_male_kumulacja'] + $sesja['male_un'];
        [$kum_un, $przyr_un] = arena_konwertuj($kum_un, (float)$gracz['uniki']);
        $kum_wb = (int)$gracz['walka_male_kumulacja'] + $sesja['male_wb'];
        [$kum_wb, $przyr_wb] = arena_konwertuj($kum_wb, (float)$gracz['walka_bronia']);

        $hp_safe  = max(1, $p_hp);
        $en_koszt = $sesja['walk_wyk'];
        $new_un   = (float)$gracz['uniki'] + $przyr_un;
        $new_wb   = (float)$gracz['walka_bronia'] + $przyr_wb;

        $polaczenie->query("UPDATE gracze SET
            hp_aktualne=$hp_safe,
            apteczki=$apteczki_stan,
            energia_aktualna=GREATEST(0, energia_aktualna-$en_koszt),
            exp=exp+{$sesja['exp_total']},
            gotowka=gotowka+{$sesja['kasa_total']},
            uniki=$new_un,
            walka_bronia=$new_wb,
            uniki_male_kumulacja=$kum_un,
            walka_male_kumulacja=$kum_wb,
            npc_ubicia=npc_ubicia+{$sesja['wygranych']},
            npc_remisy=npc_remisy+{$sesja['remisow']},
            npc_porazki=npc_porazki+{$sesja['przegranych']}
            WHERE id=$id_gracza");

        $gracz['hp_aktualne']      = $hp_safe;
        $gracz['apteczki']         = $apteczki_stan;
        $gracz['energia_aktualna'] = max(0, (int)$gracz['energia_aktualna'] - $en_koszt);
        $gracz['exp']             += $sesja['exp_total'];
        $gracz['gotowka']         += $sesja['kasa_total'];
        $gracz['uniki']            = $new_un;
        $gracz['walka_bronia']     = $new_wb;
        $gracz['uniki_male_kumulacja'] = $kum_un;
        $gracz['walka_male_kumulacja'] = $kum_wb;
        $gracz['bron_trwalosc']    = $b_tr ?? $gracz['bron_trwalosc'];
        $gracz['pancerz_trwalosc'] = $p_tr ?? $gracz['pancerz_trwalosc'];

        $podsumowanie = $sesja + ['przyr_un'=>$przyr_un, 'przyr_wb'=>$przyr_wb,
                                  'kum_un'=>$kum_un, 'kum_wb'=>$kum_wb, 'p_hp_koniec'=>$hp_safe];
    }
}

/** Dziennik rund w zwiniętej formie — sto rund × dziesięć walk to za dużo na płasko. */
function arena_render_dziennik(array $d): string {
    $out = '';
    foreach ($d as $r) {
        $k = ['trafienie'=>['lg-hit','TRAFIENIE'], 'pudlo'=>['lg-miss','PUDŁO'],
              'rana'=>['lg-rana','RANA'], 'unik'=>['lg-unik','UNIK'],
              'apteczka'=>['lg-apt','APTECZKA']][$r['typ']] ?? ['lg-miss', strtoupper($r['typ'])];
        $dmg = $r['dmg'] > 0 ? " {$r['dmg']} dmg" : '';
        $out .= "<div><span class='lg-r'>R{$r['r']}</span> <span class='{$k[0]}'>[{$k[1]}]</span>$dmg"
              . " <span class='lg-hp'>· ty {$r['hp']} · on {$r['w_hp']}</span></div>";
    }
    return $out;
}

/* ── dane do renderu ───────────────────────────────────────────────── */
$prog_un   = arena_prog_dla_skilla((float)$gracz['uniki']);
$prog_wb   = arena_prog_dla_skilla((float)$gracz['walka_bronia']);
$unik_moj  = arena_zdolnosc_uniku($gracz);
$cios_moj  = $gracz['arena_cios'] ?: 'piesc';
$analizy   = [];
foreach ($lista_wrogow as $w) $analizy[(int)$w['id']] = arena_analiza($w, $gracz, $cios_moj);

$TYPY = ['cywil'=>['Cywil','#4ad6ff'], 'bykowaty'=>['Bykowaty','#ff7a3d'], 'ochroniarz'=>['Ochroniarz','#5aff9a']];
$ROLE = arena_role();
?>

<style>
.doki-head{background:linear-gradient(180deg,rgba(10,5,10,.6),rgba(5,2,5,.85)),repeating-linear-gradient(45deg,rgba(255,23,68,.03) 0 10px,transparent 10px 20px);border:1px solid var(--border-mid);border-radius:2px;padding:32px 36px;margin-bottom:20px;position:relative;overflow:hidden;box-shadow:0 0 40px rgba(255,23,68,.15)}
.doki-head::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--neon-red),transparent);box-shadow:0 0 10px var(--neon-red)}
.doki-head .eyebrow{color:var(--neon-red);font-family:'JetBrains Mono',monospace;font-size:.75em;letter-spacing:4px;text-transform:uppercase;margin-bottom:8px}
.doki-head h1{font-family:'Oswald',sans-serif;color:#fff;font-size:2.8em;font-weight:500;text-transform:uppercase;letter-spacing:4px;line-height:1;text-shadow:0 0 20px rgba(255,23,68,.6)}
.doki-head .sub{color:var(--txt-dim);margin-top:10px;font-size:.95em;letter-spacing:1px}
.taktyczny{background:rgba(10,6,12,.55);border:1px solid var(--border-soft);border-radius:2px;padding:18px 22px;margin-bottom:18px;display:grid;grid-template-columns:1.1fr 1.3fr auto;gap:22px;align-items:start}
.tk-row{display:flex;justify-content:space-between;gap:14px;font-size:.85em;color:var(--txt-dim);font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:5px}
.tk-row b{color:#fff;font-family:'JetBrains Mono',monospace;letter-spacing:0;font-weight:500}
.tk-row .v-red{color:var(--neon-red-hot)}.tk-row .v-cyan{color:var(--neon-cyan)}.tk-row .v-ember{color:var(--neon-ember)}.tk-row .v-green{color:var(--neon-green)}
.tk-arsenal{border-left:1px dashed rgba(255,23,68,.15);padding-left:22px}
.tk-label{font-family:'JetBrains Mono',monospace;font-size:.68em;color:var(--txt-mute);letter-spacing:2.5px;text-transform:uppercase;margin-bottom:6px}
.cios-sel{background:rgba(0,0,0,.7);color:var(--neon-cyan);border:1px solid rgba(74,214,255,.4);padding:9px 12px;font-family:'Oswald',sans-serif;font-size:.92em;text-transform:uppercase;letter-spacing:1.5px;border-radius:2px;outline:none;cursor:pointer;width:100%}
.cios-sel:hover,.cios-sel:focus{box-shadow:0 0 16px rgba(74,214,255,.4);border-color:var(--neon-cyan)}
.chk-row{display:flex;align-items:center;gap:8px;margin-top:10px;color:var(--txt-dim);font-size:.88em}
.btn-zapisz{background:rgba(74,214,255,.1);border:1px solid rgba(74,214,255,.4);color:var(--neon-cyan);padding:8px 14px;cursor:pointer;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:.82em;border-radius:2px;margin-top:10px}
.btn-zapisz:hover{background:var(--neon-cyan);color:#000}
.btn-apteczka{background:rgba(255,23,68,.1);border:1px solid var(--border-mid);color:var(--neon-red-hot);padding:13px 18px;cursor:pointer;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:.9em;border-radius:2px;white-space:nowrap}
.btn-apteczka:hover:not(:disabled){background:var(--neon-red);color:#fff;box-shadow:0 0 22px rgba(255,23,68,.7)}
.btn-apteczka:disabled{border-color:rgba(255,255,255,.07);color:var(--txt-mute);cursor:not-allowed;background:rgba(0,0,0,.4)}
.farma{background:rgba(10,6,12,.55);border:1px solid var(--border-soft);border-radius:2px;padding:20px 24px;margin-bottom:18px;position:relative}
.farma::before{content:'';position:absolute;top:0;left:0;width:32px;height:1px;background:var(--neon-red);box-shadow:0 0 6px var(--neon-red)}
.farma-tytul{font-family:'Oswald',sans-serif;font-size:.9em;color:#fff;text-transform:uppercase;letter-spacing:2.5px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border-soft);display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:baseline}
.farma-tytul .hint{font-family:'JetBrains Mono',monospace;font-size:.72em;text-transform:none;color:var(--txt-dim);letter-spacing:.5px}
.farma-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.farma-stat{background:rgba(0,0,0,.4);border:1px solid rgba(255,23,68,.08);border-radius:2px;padding:12px 14px}
.fs-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
.fs-head .name{font-family:'Oswald',sans-serif;color:#fff;text-transform:uppercase;letter-spacing:1.5px}
.fs-head .val{font-family:'JetBrains Mono',monospace;font-size:1.15em;color:var(--neon-ember)}
.fs-bar-wrap{background:rgba(0,0,0,.7);border:1px solid rgba(255,23,68,.1);height:8px;overflow:hidden}
.fs-bar{height:100%;transition:width .6s}
.fs-bar.un{background:linear-gradient(90deg,#003344,var(--neon-cyan))}
.fs-bar.wb{background:linear-gradient(90deg,#5a0010,var(--neon-red-hot))}
.fs-caption{display:flex;justify-content:space-between;margin-top:6px;font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-mute);letter-spacing:1px}
.unik-rozklad{margin-top:14px;padding-top:12px;border-top:1px dashed rgba(255,23,68,.15);display:flex;gap:16px;flex-wrap:wrap;font-family:'JetBrains Mono',monospace;font-size:.75em;color:var(--txt-mute);letter-spacing:1px}
.unik-rozklad b{color:var(--neon-cyan)}
.unik-rozklad .zero b{color:var(--neon-red-hot)}
.bestiar{background:rgba(10,6,12,.55);border:1px solid var(--border-soft);border-radius:2px;overflow:hidden}
.bestiar-h{padding:14px 18px;background:rgba(0,0,0,.4);border-bottom:1px solid var(--border-soft);font-family:'Oswald',sans-serif;color:#fff;text-transform:uppercase;letter-spacing:2.5px;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:baseline}
.bestiar-h .note{font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-dim);letter-spacing:1px;text-transform:none}
.t-scroll{overflow-x:visible}
.t-wr{width:100%;border-collapse:collapse;font-size:.92em}
.t-wr th{background:rgba(0,0,0,.5);padding:11px 12px;text-align:left;color:var(--neon-red);font-family:'Oswald',sans-serif;text-transform:uppercase;font-size:.72em;letter-spacing:1.6px;border-bottom:1px solid var(--border-mid);font-weight:500;line-height:1.35}
.t-wr td{padding:11px 12px;border-bottom:1px dashed rgba(255,23,68,.07);color:var(--txt-main);vertical-align:middle}
.t-wr tr.w-row:hover td{background:rgba(255,23,68,.06);color:#fff}
.td-nazwa{font-family:'Oswald',sans-serif;font-size:1.02em;color:#fff;min-width:180px}
.td-nazwa .opis{display:block;font-family:'Rajdhani',sans-serif;font-size:.84em;color:var(--txt-dim);letter-spacing:0;line-height:1.4;margin-top:2px;max-width:38ch}
.td-nazwa .mini{display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;color:var(--txt-mute);letter-spacing:1px;margin-top:4px}
.td-nazwa .mini .s-hp{color:rgba(255,61,94,.75)}
.td-nazwa .mini .s-atk{color:rgba(255,122,61,.75)}
.td-nazwa .mini .s-un{color:rgba(74,214,255,.75)}
.td-hit{font-family:'JetBrains Mono',monospace;white-space:nowrap}
.td-hit .duzy{font-size:1.32em;color:var(--neon-green)}
.td-hit .duzy.sredni{color:var(--neon-ember)}
.td-hit .duzy.slaby{color:var(--neon-red-hot)}
.td-hit .pod{display:block;font-size:.7em;color:var(--txt-mute);letter-spacing:1px;margin-top:2px}
.td-hit .farm{display:block;font-size:.7em;letter-spacing:1px;margin-top:3px;color:var(--txt-mute)}
.td-hit .farm b{color:var(--neon-cyan);font-weight:500}
.td-lvl{font-family:'JetBrains Mono',monospace;color:var(--neon-ember)}
.td-stats{font-family:'JetBrains Mono',monospace;font-size:.84em;line-height:1.5;white-space:nowrap}
.td-stats .s-hp{color:var(--neon-red-hot)}.td-stats .s-atk{color:var(--neon-ember)}.td-stats .s-un{color:var(--neon-cyan)}
.badge-typ{display:inline-block;padding:3px 10px;border-radius:2px;font-family:'Oswald',sans-serif;font-size:.74em;letter-spacing:1.5px;text-transform:uppercase;color:#000;font-weight:600;white-space:nowrap}
.badge-rola{display:inline-block;padding:2px 8px;border:1px solid rgba(255,255,255,.12);border-radius:2px;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:1.2px;text-transform:uppercase;color:var(--txt-dim);white-space:nowrap}
.farmi{display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;color:var(--txt-mute);letter-spacing:1px;margin-top:3px}
.rating-mini{display:inline-block;padding:2px 8px;border-radius:2px;font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1.4px;text-transform:uppercase;white-space:nowrap;border:1px solid}
.rm-optimal{color:var(--neon-green);border-color:var(--neon-green);background:rgba(90,255,154,.08)}
.rm-good{color:var(--neon-cyan);border-color:rgba(74,214,255,.3);background:rgba(74,214,255,.04)}
.rm-easy{color:var(--txt-dim);border-color:rgba(255,255,255,.08)}
.rm-too_easy{color:var(--txt-mute);border-color:rgba(255,255,255,.04);opacity:.7}
.rm-remis{color:var(--neon-gold,#ffd700);border-color:rgba(255,215,0,.4);background:rgba(255,215,0,.06)}
.rm-too_hard{color:var(--neon-red-hot);border-color:var(--border-mid);background:rgba(255,23,68,.06)}
.est{font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--txt-mute);margin-top:3px;white-space:nowrap}
.td-akcja{text-align:right}
.ilosc-sel{background:rgba(0,0,0,.7);color:#fff;border:1px solid rgba(255,23,68,.25);padding:6px 8px;font-family:'JetBrains Mono',monospace;font-size:.9em;border-radius:2px;cursor:pointer;width:58px;text-align:center}
.btn-walka{background:rgba(0,0,0,.5);border:1px solid var(--border-mid);color:var(--neon-red-hot);padding:8px 14px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:.84em;cursor:pointer;border-radius:2px}
.btn-walka:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 16px rgba(255,23,68,.6)}
.log-box{background:rgba(5,3,6,.85);border:1px solid var(--border-soft);border-radius:2px;padding:18px 20px;font-family:'JetBrains Mono',monospace;font-size:.88em;line-height:1.7;max-height:460px;overflow-y:auto;margin-bottom:18px}
.log-box .lg-r{color:var(--txt-mute);letter-spacing:1px;margin-right:4px;font-size:.85em}
.log-box .lg-hp{color:var(--txt-mute);font-size:.85em}
.log-box .lg-walka-h{color:var(--neon-red-hot);margin:12px 0 6px;padding:6px 10px;background:rgba(255,23,68,.08);border-left:2px solid var(--neon-red);font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase}
.log-box .lg-hit{color:var(--neon-green)}.log-box .lg-miss{color:var(--txt-mute);font-style:italic}
.log-box .lg-rana{color:var(--neon-red-hot)}.log-box .lg-unik{color:var(--neon-cyan)}
.log-box .lg-apt{color:#ffd700}
.log-box .lg-klat{margin:8px 0;padding:8px 12px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px}
.log-box .lg-win{color:var(--neon-green);background:rgba(90,255,154,.07);border-left:2px solid var(--neon-green)}
.log-box .lg-loss{color:var(--neon-red-hot);background:rgba(255,23,68,.08);border-left:2px solid var(--neon-red)}
.log-box .lg-remis{color:#ffd700;background:rgba(255,215,0,.06);border-left:2px solid #ffd700}
.log-box .lg-det{margin:4px 0 10px}
.log-box .lg-det summary{cursor:pointer;color:var(--txt-mute);font-size:.85em;letter-spacing:1px;padding:3px 0}
.log-box .lg-det summary:hover{color:var(--neon-cyan)}
.log-box .lg-det>div{padding-left:14px;border-left:1px dashed rgba(255,255,255,.08)}
.podsum{background:rgba(10,6,12,.7);border:1px solid var(--border-mid);border-radius:2px;padding:22px;margin-bottom:20px;box-shadow:0 0 25px rgba(255,23,68,.15)}
.podsum-h{font-family:'Oswald',sans-serif;color:#fff;font-size:1.12em;text-transform:uppercase;letter-spacing:2.5px;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border-mid)}
.podsum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(128px,1fr));gap:10px}
.ps-cell{background:rgba(0,0,0,.4);border:1px solid rgba(255,23,68,.08);border-radius:2px;padding:10px 12px}
.ps-cell .l{font-family:'JetBrains Mono',monospace;font-size:.66em;color:var(--txt-mute);letter-spacing:2px;text-transform:uppercase;margin-bottom:2px}
.ps-cell .v{font-family:'Oswald',sans-serif;font-size:1.22em;color:#fff;letter-spacing:1px}
.ps-cell.accent .v{color:var(--neon-ember)}.ps-cell.green .v{color:var(--neon-green)}
.ps-cell.cyan .v{color:var(--neon-cyan)}.ps-cell.red .v{color:var(--neon-red-hot)}
.ps-cell.gold .v{color:#ffd700}
.alert-ok{background:rgba(90,255,154,.08);border:1px solid var(--neon-green);color:var(--neon-green);padding:13px 18px;margin-bottom:18px;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1.5px;border-radius:2px}
.alert-err{background:rgba(255,23,68,.1);border:1px solid var(--border-mid);color:var(--neon-red-hot);padding:13px 18px;margin-bottom:18px;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1.5px;border-radius:2px}
.pusto{color:var(--txt-dim);text-align:center;padding:34px 20px;font-style:italic}
.trwalosc{font-family:'JetBrains Mono',monospace;font-size:.72em;color:var(--txt-mute);letter-spacing:1px;margin-top:8px}
.trwalosc .low{color:var(--neon-red-hot)}
@media(max-width:900px){.taktyczny{grid-template-columns:1fr}.tk-arsenal{border-left:0;padding-left:0;border-top:1px dashed rgba(255,23,68,.15);padding-top:14px}.farma-grid{grid-template-columns:1fr}}
</style>

<div class="doki-head">
    <div class="eyebrow">// DOKI · ARENA · <?php echo htmlspecialchars($miasto ?: 'BRAK MIASTA'); ?> · <?php echo htmlspecialchars($kraj); ?></div>
    <h1>Arena Zaułków</h1>
    <div class="sub">Sto rund na walkę. Przeciwnik, którego nie zdążysz zabić, kończy remisem: dostajesz EXP proporcjonalny do zadanych obrażeń, bez gotówki — ale umiejętności farmią się tak samo.</div>
</div>

<?php echo $komunikat; ?>

<?php if ($podsumowanie): ?>
<div class="podsum">
    <div class="podsum-h">▸ Raport z sesji · <?php echo (int)$podsumowanie['walk_wyk']; ?>/<?php echo (int)($_POST['ilosc_walk'] ?? 1); ?> walk</div>
    <div class="podsum-grid">
        <div class="ps-cell green"><div class="l">Wygrane</div><div class="v"><?php echo (int)$podsumowanie['wygranych']; ?></div></div>
        <div class="ps-cell gold"><div class="l">Remisy</div><div class="v"><?php echo (int)$podsumowanie['remisow']; ?></div></div>
        <div class="ps-cell red"><div class="l">Porażki</div><div class="v"><?php echo (int)$podsumowanie['przegranych']; ?></div></div>
        <div class="ps-cell accent"><div class="l">EXP</div><div class="v">+<?php echo (int)$podsumowanie['exp_total']; ?></div></div>
        <div class="ps-cell accent"><div class="l">Gotówka</div><div class="v">+<?php echo number_format((int)$podsumowanie['kasa_total'], 0, '', ' '); ?> $</div></div>
        <div class="ps-cell cyan"><div class="l">Małe uniki</div><div class="v"><?php echo (int)$podsumowanie['male_un']; ?></div></div>
        <div class="ps-cell red"><div class="l">Małe WB</div><div class="v"><?php echo (int)$podsumowanie['male_wb']; ?></div></div>
        <div class="ps-cell cyan"><div class="l">Przyrost uników</div><div class="v">+<?php echo number_format($podsumowanie['przyr_un'], 3); ?></div></div>
        <div class="ps-cell red"><div class="l">Przyrost WB</div><div class="v">+<?php echo number_format($podsumowanie['przyr_wb'], 3); ?></div></div>
        <div class="ps-cell"><div class="l">Rund łącznie</div><div class="v"><?php echo (int)$podsumowanie['rundy_total']; ?></div></div>
        <div class="ps-cell"><div class="l">DMG zadane</div><div class="v"><?php echo number_format((int)$podsumowanie['dmg_zadanych'], 0, '', ' '); ?></div></div>
        <div class="ps-cell"><div class="l">DMG obrane</div><div class="v"><?php echo number_format((int)$podsumowanie['dmg_otrzymanych'], 0, '', ' '); ?></div></div>
        <div class="ps-cell"><div class="l">Apteczki</div><div class="v"><?php echo (int)$podsumowanie['apteczki_uzyte']; ?></div></div>
        <div class="ps-cell"><div class="l">HP na koniec</div><div class="v"><?php echo (int)$podsumowanie['p_hp_koniec']; ?></div></div>
    </div>
</div>
<?php endif; ?>

<div class="taktyczny">
    <div>
        <div class="tk-row"><span>Zdrowie</span><b class="v-red"><?php echo (int)$gracz['hp_aktualne']; ?> / <?php echo (int)$gracz['hp_max']; ?></b></div>
        <div class="tk-row"><span>Energia</span><b class="v-cyan"><?php echo (int)$gracz['energia_aktualna']; ?></b></div>
        <div class="tk-row"><span>Siła + broń</span><b class="v-ember"><?php echo (int)$gracz['sila']; ?> + <?php echo (int)$gracz['bonus_atak']; ?></b></div>
        <div class="tk-row"><span>Obrona</span><b class="v-cyan"><?php echo (int)$gracz['wytrzymalosc'] + (int)$gracz['bonus_obrona']; ?></b></div>
        <div class="tk-row"><span>Zdolność uniku</span><b class="v-green"><?php echo number_format($unik_moj, 2); ?></b></div>
        <div class="tk-row"><span>Uniki · WB</span><b><?php echo number_format((float)$gracz['uniki'], 2); ?> · <?php echo number_format((float)$gracz['walka_bronia'], 2); ?></b></div>
        <div class="trwalosc">
            Trwałość: broń
            <span class="<?php echo (int)$gracz['bron_trwalosc'] < 20 ? 'low' : ''; ?>"><?php echo (int)$gracz['bron_trwalosc']; ?>/<?php echo (int)$gracz['bron_trwalosc_max']; ?></span>
            · pancerz
            <span class="<?php echo (int)$gracz['pancerz_trwalosc'] < 20 ? 'low' : ''; ?>"><?php echo (int)$gracz['pancerz_trwalosc']; ?>/<?php echo (int)$gracz['pancerz_trwalosc_max']; ?></span>
        </div>
    </div>

    <div class="tk-arsenal">
        <form method="POST" style="margin:0">
            <div class="tk-label">Czym bijesz</div>
            <select name="cios" class="cios-sel">
                <?php foreach (['ostrze'=>'Ostrze · ×1,5 na Cywilach',
                                'tepe'=>'Tępe narzędzie · ×1,5 na Bykowatych',
                                'palna'=>'Broń palna · ×1,5 na Ochroniarzach',
                                'piesc'=>'Goła pięść · bez bonusu i bez kary'] as $k => $opis): ?>
                    <option value="<?php echo $k; ?>"<?php if ($cios_moj === $k) echo ' selected'; ?>><?php echo $opis; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="tk-label" style="margin-top:8px">
                W dłoni: <span style="color:var(--neon-ember)"><?php echo htmlspecialchars($gracz['bron_zalozona'] ?: 'nic'); ?></span>
            </div>
            <label class="chk-row">
                <input type="checkbox" name="auto_apteczka" value="1"<?php if ((int)$gracz['arena_auto_apteczka'] === 1) echo ' checked'; ?>>
                Auto-apteczka poniżej 30% HP (masz <?php echo (int)$gracz['apteczki']; ?>)
            </label>
            <button type="submit" name="zapisz_ustawienia" class="btn-zapisz">Zapisz</button>
        </form>
    </div>

    <form method="POST" style="margin:0">
        <button type="submit" name="uzyj_apteczki" class="btn-apteczka"<?php if ((int)$gracz['apteczki'] <= 0 || (int)$gracz['hp_aktualne'] >= (int)$gracz['hp_max']) echo ' disabled'; ?>>
            💉 Apteczka ×<?php echo (int)$gracz['apteczki']; ?>
        </button>
    </form>
</div>

<div class="farma">
    <div class="farma-tytul">
        ▸ Status farmy — małe krytyki
        <span class="hint">// sufit: 100 małych na walkę, osobno uniki i WB</span>
    </div>
    <div class="farma-grid">
        <div class="farma-stat">
            <div class="fs-head"><span class="name">👁 Uniki</span><span class="val"><?php echo number_format((float)$gracz['uniki'], 2); ?></span></div>
            <div class="fs-bar-wrap"><div class="fs-bar un" style="width:<?php echo min(100, ((int)$gracz['uniki_male_kumulacja'] / max(1, $prog_un)) * 100); ?>%"></div></div>
            <div class="fs-caption"><span>MAŁE: <?php echo (int)$gracz['uniki_male_kumulacja']; ?> / <?php echo $prog_un; ?></span><span>+0,1 przy progu</span></div>
        </div>
        <div class="farma-stat">
            <div class="fs-head"><span class="name">⚔️ Walka bronią</span><span class="val"><?php echo number_format((float)$gracz['walka_bronia'], 2); ?></span></div>
            <div class="fs-bar-wrap"><div class="fs-bar wb" style="width:<?php echo min(100, ((int)$gracz['walka_male_kumulacja'] / max(1, $prog_wb)) * 100); ?>%"></div></div>
            <div class="fs-caption"><span>MAŁE: <?php echo (int)$gracz['walka_male_kumulacja']; ?> / <?php echo $prog_wb; ?></span><span>+0,1 przy progu</span></div>
        </div>
    </div>
    <div class="unik-rozklad">
        <span>Zdolność uniku:</span>
        <span>uniki <b><?php echo number_format((float)$gracz['uniki'], 2); ?></b></span>
        <span>zręczność×0,45 <b><?php echo number_format((int)$gracz['zrecznosc'] * 0.45, 2); ?></b></span>
        <span class="<?php echo (int)$gracz['bonus_szybkosc'] == 0 ? 'zero' : ''; ?>">szybkość broni×0,45 <b><?php echo number_format((int)$gracz['bonus_szybkosc'] * 0.45, 2); ?></b></span>
        <span class="<?php echo (int)$gracz['bonus_unik'] == 0 ? 'zero' : ''; ?>">pancerz <b><?php echo (int)$gracz['bonus_unik']; ?></b></span>
        <span>poziom <b><?php echo (int)$gracz['poziom']; ?></b></span>
    </div>
</div>

<?php if (!empty($log_walki)): ?>
<div class="log-box">
    <?php foreach ($log_walki as $l) echo $l; ?>
</div>
<?php endif; ?>

<div class="bestiar">
    <div class="bestiar-h">
        ▸ Bestiariusz — <?php echo htmlspecialchars($miasto ?: '—'); ?>
        <span class="note">// wybierz ilość walk i atakuj · 1 EN za walkę</span>
    </div>

    <?php if (empty($lista_wrogow)): ?>
        <p class="pusto">
            W tym mieście nie ma jeszcze nikogo, kto szuka zwady.<br>
            Przeciwnicy są na razie tylko w Nowym Jorku — kolejne miasta dochodzą.
        </p>
    <?php else: ?>
    <div class="t-scroll">
    <table class="t-wr">
        <thead><tr>
            <th>Cel</th><th>Lvl</th><th>Typ</th><th>Rola</th>
            <th>Trafienia<br>ja / on</th><th>Nagroda</th><th>Ocena</th><th style="text-align:right">Akcja</th>
        </tr></thead>
        <tbody>
        <?php foreach ($lista_wrogow as $id_w => $wrog):
            $a   = $analizy[$id_w];
            $typ = $TYPY[$wrog['typ']] ?? ['?', '#8a818e'];
            $rol = $ROLE[$wrog['rola']] ?? null;
            $mn  = $a['mnoznik_ciosu'];
        ?>
            <tr class="w-row">
                <td class="td-nazwa">
                    <?php echo htmlspecialchars($wrog['nazwa']); ?>
                    <?php if (!empty($wrog['opis'])): ?><span class="opis"><?php echo htmlspecialchars($wrog['opis']); ?></span><?php endif; ?>
                    <span class="mini">
                        <span class="s-hp">HP <?php echo number_format((int)$wrog['hp'], 0, '', ' '); ?></span> ·
                        <span class="s-atk">ATK <?php echo (int)$wrog['atak']; ?></span> ·
                        OBR <?php echo (int)$wrog['obrona']; ?> ·
                        <span class="s-un">UNIK <?php echo (int)$wrog['unik']; ?></span> ·
                        CEL <?php echo (int)$wrog['celnosc']; ?>
                    </span>
                </td>
                <td class="td-lvl"><?php echo (int)$wrog['poziom']; ?></td>
                <td>
                    <span class="badge-typ" style="background:<?php echo $typ[1]; ?>"><?php echo $typ[0]; ?></span>
                    <?php if ($mn > 1): ?><span class="farmi" style="color:var(--neon-green)">twój cios ×<?php echo number_format($mn, 1); ?></span><?php endif; ?>
                </td>
                <td>
                    <span class="badge-rola"><?php echo htmlspecialchars($rol['nazwa'] ?? $wrog['rola']); ?></span>
                    <?php if ($rol): ?><span class="farmi">farmi: <?php echo htmlspecialchars($rol['farmi']); ?></span><?php endif; ?>
                </td>
                <td class="td-hit">
                    <?php $kl = $a['moj_hit'] >= 60 ? '' : ($a['moj_hit'] >= 25 ? ' sredni' : ' slaby'); ?>
                    <span class="duzy<?php echo $kl; ?>"><?php echo $a['moj_hit']; ?>%</span>
                    <span class="pod">on trafia <?php echo $a['jego_hit']; ?>%<?php echo $a['rundy'] > ARENA_RUNDY ? ' · remis' : ' · '.round($a['rundy']).' rund'; ?></span>
                    <span class="farm">farma: <b><?php echo $a['uniki_est']; ?></b> uników · <b><?php echo $a['wb_est']; ?></b> WB</span>
                </td>
                <td class="td-stats"><?php echo (int)$wrog['exp']; ?> PD<br><span style="color:var(--neon-ember)"><?php echo number_format((int)$wrog['kasa'], 0, '', ' '); ?> $</span></td>
                <td>
                    <span class="rating-mini rm-<?php echo $a['ocena'][0]; ?>"><?php echo $a['ocena'][1]; ?></span>
                    <div class="est">obierzesz ~<?php echo (int)$a['obiore']; ?> HP</div>
                </td>
                <td class="td-akcja">
                    <form method="POST" style="display:flex;gap:6px;margin:0;align-items:center;justify-content:flex-end">
                        <input type="hidden" name="id_wroga" value="<?php echo $id_w; ?>">
                        <select name="ilosc_walk" class="ilosc-sel" title="Ilość walk">
                            <option value="1">×1</option><option value="3">×3</option>
                            <option value="5">×5</option><option value="10">×10</option>
                        </select>
                        <button type="submit" name="walcz" class="btn-walka">⚔ Walcz</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
