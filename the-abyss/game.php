<?php
session_start();

if (!isset($_SESSION['zalogowany']) || $_SESSION['zalogowany'] !== true) {
    header("Location: index.php");
    exit;
}

require_once "db.php";
require_once "config/miasta.php";   // ← katalog miast + helpery pogody/odległości
require_once "config/pochodzenia.php";  // ← katalog narodowości + helpery bonusów
require_once "helpers/vip.php";   // ← helper systemu VIP
require_once "helpers/firmy.php";   // ← helper systemu firm

$id_gracza   = $_SESSION['id_gracza'];
$strona      = isset($_GET['page'])     ? $_GET['page']     : 'witaj';
$zakladka    = isset($_GET['zakladka']) ? $_GET['zakladka'] : '';

$dozwolone_strony = ['witaj','karta','umiejetnosci','czat','rynek','doki','szpital','ekwipunek',
    'ustawienia','firma','miasto','ranking','mieszkanie','laboratorium','silownia','zlomowisko',
    'sklep','warsztat','syndykaty','profil','poczta','bank','walka_pvp','uniwersytet','zlecenia',
    'sesje','pokoj_sesji','kasyno','katedra','lotnisko','wybor_pochodzenia','premium',
    'profil_firmy','lista_firm'];
// 1. NAMIERZANIE
$strona_sql = $polaczenie->real_escape_string($strona);
$polaczenie->query("UPDATE gracze SET ostatnia_aktywnosc=NOW(), obecna_lokacja='$strona_sql' WHERE id=$id_gracza");

// 2. SILNIK (Regeneracja + Awans)
$wynik_r = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza");
if ($wynik_r) {
    $gracz_r = $wynik_r->fetch_assoc();

    $ostatni_tick = strtotime(date('Y-m-d H:00:00', strtotime($gracz_r['ostatnia_regeneracja'])));
    $obecny_tick  = strtotime(date('Y-m-d H:00:00'));
    if ($obecny_tick > $ostatni_tick) {
        $godz         = round(($obecny_tick - $ostatni_tick) / 3600);
        $dodana_en    = $godz * (9 + $gracz_r['poziom']);
        $nowa_en      = min($gracz_r['energia_max'], $gracz_r['energia_aktualna'] + $dodana_en);
        $polaczenie->query("UPDATE gracze SET energia_aktualna=$nowa_en, hp_aktualne=hp_max, ostatnia_regeneracja=NOW() WHERE id=$id_gracza");
        $gracz_r['energia_aktualna'] = $nowa_en;
        $gracz_r['hp_aktualne']      = $gracz_r['hp_max'];
    }

    $lvl = $gracz_r['poziom']; $exp = $gracz_r['exp']; $awans = false;
    while ($exp >= $lvl * 100) { $exp -= $lvl * 100; $lvl++; $awans = true; }
    if ($awans) {
        $diff = $lvl - $gracz_r['poziom'];
        $polaczenie->query("UPDATE gracze SET poziom=$lvl, exp=$exp,
            punkty_atrybutow=punkty_atrybutow+".($diff*5).",
            punkty_umiejetnosci=punkty_umiejetnosci+".($diff*5).",
            hp_max=hp_max+".($diff*10).", hp_aktualne=hp_max+".($diff*10).",
            energia_max=energia_max+".($diff*5).", energia_aktualna=energia_max+".($diff*5)."
            WHERE id=$id_gracza");
        $_SESSION['powiadomienie_awans'] = "Awans! Osiągnąłeś poziom $lvl! Zyskujesz +5 AP i +5 PU.";
        $gracz_r['poziom']=$lvl; $gracz_r['exp']=$exp;
    }
    $avatar_url = !empty($gracz_r['avatar']) ? htmlspecialchars($gracz_r['avatar']) : "https://via.placeholder.com/500x625/0a0a0a/333?text=PORTRET";
}

// VIP — synchronizacja flagi is_premium z vip_do + powiadomienie o wygaśnięciu
vip_sync_status($id_gracza);
if (isset($gracz_r)) {
    // Odśwież lokalne dane gracza po synchronizacji
    $r_vip = $polaczenie->query("SELECT vip_do, is_premium FROM gracze WHERE id=$id_gracza");
    if ($r_vip && $row_vip = $r_vip->fetch_assoc()) {
        $gracz_r['vip_do'] = $row_vip['vip_do'];
        $gracz_r['is_premium'] = $row_vip['is_premium'];
    }
}

// 2.5. WYMUSZENIE WYBORU POCHODZENIA — gracz bez pochodzenia trafia na ekran wyboru
// (dotyczy starych kont sprzed migracji oraz wszystkich nowych postaci)
if (empty($gracz_r['pochodzenie']) && $strona !== 'wybor_pochodzenia') {
    header("Location: game.php?page=wybor_pochodzenia");
    exit;
}

// 3. ONLINE
$wynik_online = $polaczenie->query("
    SELECT g.id,g.login,g.avatar,g.klasa,g.profesja_fabularna,g.nazwa_firmy,g.branza_firmy,g.is_premium,
           DATEDIFF(NOW(),g.data_rejestracji) AS dni, s.nazwa AS s_nazwa, s.tag AS s_tag
    FROM gracze g LEFT JOIN syndykaty s ON g.syndykat_id=s.id
    WHERE g.ostatnia_aktywnosc >= NOW() - INTERVAL 15 MINUTE ORDER BY g.login ASC");
$lista_online = $wynik_online ? $wynik_online->fetch_all(MYSQLI_ASSOC) : [];
$ilosc_online = count($lista_online);

// 4. LICZNIKI
$n_poczta = $polaczenie->query("SELECT COUNT(*) c FROM wiadomosci WHERE odbiorca_id=$id_gracza AND odczytana=0")->fetch_assoc()['c'];
$n_alerty  = $polaczenie->query("SELECT COUNT(*) c FROM powiadomienia WHERE gracz_id=$id_gracza AND odczytane=0")->fetch_assoc()['c'];
$aktualna  = $strona;

// 5. ── DANE DLA TOPBARA I SYSTEM STATUS (teraz oparte o katalog miast) ──
$obecne_miasto = strtoupper($gracz_r['obecne_miasto'] ?? 'NEW YORK');
$miasto_dane   = $MIASTA_DANE[$obecne_miasto] ?? $MIASTA_DANE['NEW YORK'];
$kod_shardu    = $miasto_dane['shard'];
$flaga_miasta  = $miasto_dane['flaga'];

// Wersja gry — zmieniasz tu ręcznie przy każdym patchu
$wersja_gry = 'v1.1.0';

// Nazwa podstrony -> etykieta w topbarze
$mapa_loc = [
    'witaj'       => 'KWATERA',      'karta'       => 'KARTA POSTACI',
    'umiejetnosci'=> 'UMIEJĘTNOŚCI', 'ekwipunek'   => 'EKWIPUNEK',
    'mieszkanie'  => 'MIESZKANIE',   'poczta'      => 'TERMINAL POCZTY',
    'miasto'      => 'ULICA',        'uniwersytet' => 'UNIWERSYTET',
    'bank'        => 'BANK',         'szpital'     => 'KLINIKA',
    'sesje'       => 'CENTRUM OPOWIEŚCI', 'pokoj_sesji'=> 'POKÓJ SESJI',
    'katedra'     => 'KATEDRA',      'zlecenia'    => 'TABLICA ZLECEŃ',
    'doki'        => 'DOKI',         'zlomowisko'  => 'ZŁOMOWISKO',
    'rynek'       => 'CZARNY RYNEK', 'kasyno'      => 'KASYNO',
    'syndykaty'   => 'SYNDYKATY',    'ustawienia'  => 'USTAWIENIA',
    'profil'      => 'PROFIL',       'ranking'     => 'RANKING',
    'warsztat'    => 'WARSZTAT',     'sklep'       => 'SKLEP',
    'walka_pvp'   => 'PvP',          'firma'       => 'FIRMA',
    'laboratorium'=> 'LABORATORIUM', 'silownia'    => 'SIŁOWNIA',
    'czat'        => 'KLUB THE ABYSS', 'lotnisko'    => 'PORT LOTNICZY',
    'wybor_pochodzenia' => 'POCHODZENIE',
];
$etykieta_lokacji = $mapa_loc[$strona] ?? strtoupper($strona);

// ── REALNA POGODA z Open-Meteo (cache 30 min w cache/) ───────────────
$pogoda_raw = fetch_pogoda($miasto_dane['lat'], $miasto_dane['lng']);
$pogoda     = formatuj_pogode($pogoda_raw);
// $pogoda = [etykieta, kolor_hex]

// ── SYS.LOG: dynamiczne części zależne od stanu gracza ───────────────
$sys_log_parts = [];
$sys_log_parts[] = "<span class='ok'>PANEL OK</span>";
$sys_log_parts[] = "NEON STABLE";
if ($gracz_r['hp_aktualne'] < $gracz_r['hp_max'] * 0.25) {
    $sys_log_parts[] = "<span class='warn'>VITALS LOW</span>";
}
if ($gracz_r['energia_aktualna'] < $gracz_r['energia_max'] * 0.15) {
    $sys_log_parts[] = "<span class='warn'>ENERGY CRITICAL</span>";
}
if (!empty($gracz_r['w_mieszkaniu'])) {
    $sys_log_parts[] = "<span class='ok'>SAFEHOUSE</span>";
}
$sys_log_parts[] = strtoupper($obecne_miasto) . " UPLINK STABLE";
$sys_log_parts[] = "NO INTRUSION DETECTED";
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>The Abyss — <?php echo $etykieta_lokacji; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@300;400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════════
   THE ABYSS — CYBERPUNK NYC · MAIN LAYOUT
   Paleta: deep-navy, crimson neon, ember orange, bone white
═══════════════════════════════════════════════════════════════════ */
:root{
    --neon-red:       #ff1744;
    --neon-red-hot:   #ff3d5e;
    --neon-red-deep:  #b3001b;
    --neon-ember:     #ff7a3d;
    --neon-gold:      #ffd700;
    --neon-cyan:      #4ad6ff;
    --neon-green:     #5aff9a;
    --bg-void:        #05060c;
    --bg-panel:       rgba(10, 8, 14, 0.62);
    --bg-card:        rgba(18, 10, 18, 0.75);
    --txt-main:       #e8e1e8;
    --txt-dim:        #8a818e;
    --txt-mute:       #5a525a;
    --border-soft:    rgba(255, 23, 68, 0.18);
    --border-mid:     rgba(255, 23, 68, 0.38);
    --border-hot:     rgba(255, 61, 94, 0.75);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;font-family:'Rajdhani',sans-serif;font-size:14px;color:var(--txt-main)}

/* ── TŁO: NOCNY NEW YORK ────────────────────────────────────────── */
body{
    position:relative;
    background:
        linear-gradient(180deg, rgba(5,6,14,0.35) 0%, rgba(10,5,10,0.25) 40%, rgba(20,8,12,0.45) 100%),
        url('img/nyc_bg.jpg') center center / cover no-repeat fixed;
    background-color:#05060c;
    overflow-x:hidden;
}
body::before{
    content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
    background:
        radial-gradient(ellipse 90% 50% at 50% 100%, rgba(255,23,68,0.10) 0%, transparent 60%),
        radial-gradient(ellipse 70% 40% at 50% 0%, rgba(0,0,0,0.35) 0%, transparent 60%);
}
body::after{
    content:'';position:fixed;inset:0;z-index:1;pointer-events:none;
    background:radial-gradient(ellipse 120% 90% at 50% 50%, transparent 55%, rgba(5,6,12,0.55) 100%);
}

/* ── GŁÓWNA APLIKACJA — SZKŁO + NEON ───────────────────────────── */
.app{
    position:relative;z-index:5;
    display:flex;width:calc(100% - 48px);max-width:1520px;min-height:calc(100vh - 48px);
    margin:24px auto;
    background:rgba(10,8,14,0.32);
    backdrop-filter:blur(10px) saturate(140%) brightness(0.9);
    -webkit-backdrop-filter:blur(10px) saturate(140%) brightness(0.9);
    border-radius:4px;
    border:1.5px solid var(--neon-red);
    box-shadow:
        0 0 0 1px rgba(255,23,68,0.25),
        0 0 18px rgba(255,23,68,0.55),
        0 0 48px rgba(255,23,68,0.35),
        0 0 120px rgba(255,23,68,0.18),
        inset 0 0 0 1px rgba(255,80,110,0.35),
        inset 0 0 40px rgba(255,23,68,0.08),
        0 20px 80px rgba(0,0,0,0.8);
    animation:neon-breathe 4s ease-in-out infinite;
}
@keyframes neon-breathe{
    0%,100%{box-shadow:0 0 0 1px rgba(255,23,68,0.25),0 0 18px rgba(255,23,68,0.55),0 0 48px rgba(255,23,68,0.35),0 0 120px rgba(255,23,68,0.18),inset 0 0 0 1px rgba(255,80,110,0.35),inset 0 0 40px rgba(255,23,68,0.08),0 20px 80px rgba(0,0,0,0.8)}
    50%{box-shadow:0 0 0 1px rgba(255,23,68,0.35),0 0 28px rgba(255,23,68,0.75),0 0 70px rgba(255,23,68,0.45),0 0 160px rgba(255,23,68,0.25),inset 0 0 0 1px rgba(255,100,130,0.45),inset 0 0 60px rgba(255,23,68,0.12),0 20px 80px rgba(0,0,0,0.8)}
}

/* Corner brackets */
.app::before,.app::after{content:'';position:absolute;width:22px;height:22px;pointer-events:none;border:2px solid var(--neon-red-hot);filter:drop-shadow(0 0 6px var(--neon-red))}
.app::before{top:-6px;left:-6px;border-right:0;border-bottom:0}
.app::after{bottom:-6px;right:-6px;border-left:0;border-top:0}
.corners{position:absolute;inset:0;pointer-events:none;z-index:6}
.corners::before,.corners::after{content:'';position:absolute;width:22px;height:22px;border:2px solid var(--neon-red-hot);filter:drop-shadow(0 0 6px var(--neon-red))}
.corners::before{top:-6px;right:-6px;border-left:0;border-bottom:0}
.corners::after{bottom:-6px;left:-6px;border-right:0;border-top:0}

/* ── SIDEBARY ─────────────────────────────────────────────────── */
.sidebar,.sidebar-right{
    width:240px;min-width:240px;
    background:linear-gradient(180deg, rgba(8,5,10,0.55) 0%, rgba(14,6,12,0.50) 100%);
    backdrop-filter:blur(8px) saturate(130%);
    -webkit-backdrop-filter:blur(8px) saturate(130%);
    padding:18px 14px;
    position:sticky;top:0;height:calc(100vh - 48px);overflow-y:auto;
}
.sidebar{border-right:1px solid var(--border-soft)}
.sidebar-right{border-left:1px solid var(--border-soft)}

/* ── LOGO ─────────────────────────────────────────────────────── */
.logo{
    font-family:'Oswald',sans-serif;font-weight:600;
    text-align:center;padding:4px 0 18px;
    border-bottom:1px solid var(--border-soft);margin-bottom:18px;
}
.logo .t{font-size:1.05em;letter-spacing:6px;color:var(--txt-mute);text-transform:uppercase;display:block;margin-bottom:2px}
.logo .h{
    font-size:2.1em;letter-spacing:3px;color:#fff;text-transform:uppercase;display:block;
    text-shadow:0 0 4px #fff, 0 0 12px var(--neon-red-hot), 0 0 24px var(--neon-red), 0 0 42px var(--neon-red-deep);
    animation:logo-flicker 6s infinite;
}
@keyframes logo-flicker{0%,19%,21%,23%,25%,54%,56%,100%{opacity:1}20%,24%,55%{opacity:.75}}
.logo .sub{font-family:'JetBrains Mono',monospace;font-size:.65em;letter-spacing:2px;color:var(--neon-red);display:block;margin-top:4px;opacity:.8}

/* ── PROFIL BOCZNY ────────────────────────────────────────────── */
.quick-profile{
    background:rgba(18,10,18,0.45);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);border-radius:2px;padding:12px;margin-bottom:16px;position:relative;
}
.quick-profile::before{content:'';position:absolute;top:-1px;left:12px;right:12px;height:1px;background:linear-gradient(90deg,transparent,var(--neon-red),transparent)}
.quick-name{
    font-family:'Oswald',sans-serif;font-weight:500;font-size:.95em;
    text-align:center;margin-bottom:10px;padding-bottom:8px;
    border-bottom:1px dashed rgba(255,23,68,0.15);letter-spacing:1px;text-transform:uppercase;
}
.quick-name .vip{color:var(--neon-gold);text-shadow:0 0 8px var(--neon-gold)}
.quick-name .nick{color:#fff}
.quick-name .id{color:var(--txt-mute);font-size:.75em;font-family:'JetBrains Mono',monospace}

.quick-avatar{
    width:100%;aspect-ratio:500/625;
    background-position:top center!important;background-size:cover!important;
    border-radius:2px;margin-bottom:12px;
    border:1px solid var(--border-mid);
    box-shadow:0 0 18px rgba(255,23,68,0.25),inset 0 0 30px rgba(0,0,0,0.5);
    position:relative;overflow:hidden;transition:all .4s;
}
.quick-avatar::after{
    content:'';position:absolute;inset:0;pointer-events:none;
    background:linear-gradient(180deg, transparent 0%, transparent 60%, rgba(255,23,68,0.12) 100%);
}
.quick-avatar:hover{border-color:var(--border-hot);box-shadow:0 0 32px rgba(255,23,68,0.45),inset 0 0 30px rgba(0,0,0,0.4)}

.qs{display:flex;justify-content:space-between;margin-bottom:5px;color:var(--txt-dim);font-size:.9em}
.qs b{color:var(--txt-main);font-weight:600}
.qs .money{color:var(--neon-ember);text-shadow:0 0 6px rgba(255,122,61,0.5);font-family:'JetBrains Mono',monospace}

/* Paski */
.bar-label{font-size:.7em;color:var(--txt-dim);margin:9px 0 3px;text-transform:uppercase;font-family:'Oswald',sans-serif;letter-spacing:1.2px}
.bar-wrap{background:rgba(0,0,0,0.7);border:1px solid rgba(255,23,68,0.12);height:13px;border-radius:1px;position:relative;overflow:hidden}
.bar-wrap::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:repeating-linear-gradient(90deg, transparent 0 10%, rgba(255,255,255,0.04) 10% calc(10% + 1px));z-index:2}
.bar-fill{height:100%;transition:width .6s ease;position:relative}
.bar-fill::after{content:'';position:absolute;top:0;right:0;width:2px;height:100%;background:#fff;box-shadow:0 0 8px currentColor}
.bar-txt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.7em;color:#fff;font-weight:700;text-shadow:1px 1px 2px #000;font-family:'JetBrains Mono',monospace;z-index:3}
.fill-hp {background:linear-gradient(90deg,#5a0010,var(--neon-red),var(--neon-red-hot));box-shadow:0 0 10px rgba(255,23,68,0.6)}
.fill-en {background:linear-gradient(90deg,#003344,var(--neon-cyan));box-shadow:0 0 10px rgba(74,214,255,0.5)}
.fill-exp{background:linear-gradient(90deg,#5a2200,var(--neon-ember));box-shadow:0 0 10px rgba(255,122,61,0.5)}

/* ── PRZYCISK MIASTO ──────────────────────────────────────────── */
.btn-miasto{
    display:flex;align-items:center;justify-content:center;gap:10px;
    background:linear-gradient(135deg, rgba(255,23,68,0.15), rgba(179,0,27,0.25));
    height:52px;border-radius:2px;margin:14px 0;
    border:1px solid var(--neon-red);text-decoration:none;
    font-family:'Oswald',sans-serif;font-weight:600;letter-spacing:4px;text-transform:uppercase;font-size:.95em;
    color:#fff;text-shadow:0 0 8px var(--neon-red);
    box-shadow:0 0 14px rgba(255,23,68,0.4),inset 0 0 20px rgba(255,23,68,0.15);
    transition:all .3s ease;position:relative;overflow:hidden;
}
.btn-miasto::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15),transparent);transform:translateX(-100%);transition:transform .6s}
.btn-miasto:hover::before{transform:translateX(100%)}
.btn-miasto:hover{background:linear-gradient(135deg, rgba(255,23,68,0.35), rgba(179,0,27,0.5));box-shadow:0 0 30px rgba(255,23,68,0.7),inset 0 0 30px rgba(255,23,68,0.25);transform:scale(1.015)}

/* ── NAWIGACJA ───────────────────────────────────────────────── */
.nav-title{
    color:var(--neon-red);font-size:.68em;text-transform:uppercase;
    font-family:'Oswald',sans-serif;letter-spacing:3px;font-weight:500;
    margin:20px 0 8px;padding:4px 8px;
    border-left:2px solid var(--neon-red);
    background:linear-gradient(90deg, rgba(255,23,68,0.12), transparent);
    text-shadow:0 0 8px rgba(255,23,68,0.5);
}
.menu-link{
    display:flex;align-items:center;gap:10px;
    color:var(--txt-dim);text-decoration:none;
    padding:7px 10px;font-size:.95em;font-weight:500;
    border-radius:1px;margin-bottom:1px;
    border-left:2px solid transparent;transition:all .2s;
    white-space:nowrap;overflow:hidden;font-family:'Rajdhani',sans-serif;letter-spacing:.5px;position:relative;
}
.menu-link:hover{color:#fff;background:rgba(255,23,68,0.08);border-left-color:var(--neon-red-hot);padding-left:14px}
.menu-link.aktywny{color:#fff;background:linear-gradient(90deg, rgba(255,23,68,0.22), rgba(255,23,68,0.04));border-left-color:var(--neon-red);text-shadow:0 0 8px rgba(255,23,68,0.7)}
.menu-link.aktywny::after{content:'▸';position:absolute;right:10px;color:var(--neon-red);text-shadow:0 0 6px var(--neon-red)}
.menu-link.aktywny-ember{color:#fff;background:linear-gradient(90deg, rgba(255,122,61,0.22), rgba(255,122,61,0.04));border-left-color:var(--neon-ember);text-shadow:0 0 8px rgba(255,122,61,0.7)}
.menu-link.aktywny-ember::after{content:'▸';position:absolute;right:10px;color:var(--neon-ember);text-shadow:0 0 6px var(--neon-ember)}
.menu-link.aktywny-gold{color:#fff;background:linear-gradient(90deg, rgba(255,215,0,0.22), rgba(255,215,0,0.04));border-left-color:var(--neon-gold);text-shadow:0 0 8px rgba(255,215,0,0.7)}
.menu-link.aktywny-gold::after{content:'▸';position:absolute;right:10px;color:var(--neon-gold);text-shadow:0 0 6px var(--neon-gold)}
.menu-link.danger{color:#ff6678}
.menu-link.danger:hover{color:#ff3d5e;background:rgba(255,61,94,0.08)}

.badge{background:var(--neon-red);color:#fff;font-size:.7em;padding:1px 7px;border-radius:1px;margin-left:auto;font-weight:600;font-family:'JetBrains Mono',monospace;box-shadow:0 0 10px rgba(255,23,68,0.7);animation:badge-pulse 1.5s infinite}
@keyframes badge-pulse{0%,100%{box-shadow:0 0 8px rgba(255,23,68,0.6)}50%{box-shadow:0 0 16px rgba(255,23,68,1)}}
.badge-ember{background:var(--neon-ember);box-shadow:0 0 10px rgba(255,122,61,0.7)}

/* ── TREŚĆ ŚRODKOWA ─────────────────────────────────────────── */
.content{
    flex:1;padding:28px 40px;
    display:flex;flex-direction:column;align-items:center;
    overflow-y:auto;max-height:calc(100vh - 48px);position:relative;
}
.content::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:repeating-linear-gradient(0deg, transparent 0 3px, rgba(255,23,68,0.015) 3px 4px);z-index:1}
.page-wrap{width:100%;max-width:1000px;position:relative;z-index:2}

/* TOPBAR */
.topbar{
    display:flex;justify-content:space-between;align-items:center;gap:14px;
    padding:10px 16px;margin-bottom:22px;
    background:rgba(0,0,0,0.35);
    backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);border-radius:2px;
    font-family:'JetBrains Mono',monospace;font-size:.78em;flex-wrap:wrap;
}
.topbar .loc{color:var(--neon-red);text-shadow:0 0 6px rgba(255,23,68,0.6);letter-spacing:2px}
.topbar .loc .flag{margin-right:4px}
.topbar .time{color:var(--txt-dim)}
.topbar .time b{color:#fff;font-weight:500}
.topbar .signal{color:var(--neon-ember);letter-spacing:1px}

/* ═══════════════════════════════════════════════════════════════════
   REUŻYWALNE KLASY GLOBALNE (dla każdej podstrony) — z prototypu.
═══════════════════════════════════════════════════════════════════ */

/* PAGE HEAD — eyebrow + h1 + lead (np. witaj.php, lotnisko.php) */
.page-head{
    margin-bottom:26px;padding-bottom:18px;
    border-bottom:1px solid var(--border-soft);position:relative;
}
.page-head::after{
    content:'';position:absolute;bottom:-1px;left:0;width:120px;height:1px;
    background:var(--neon-red);box-shadow:0 0 8px var(--neon-red);
}
.page-head .eyebrow{
    font-family:'JetBrains Mono',monospace;font-size:.75em;
    color:var(--neon-red);letter-spacing:4px;text-transform:uppercase;
    margin-bottom:6px;text-shadow:0 0 6px rgba(255,23,68,0.5);
}
.page-head h1{
    font-family:'Oswald',sans-serif;font-weight:500;
    font-size:2.4em;text-transform:uppercase;letter-spacing:3px;
    color:#fff;line-height:1;text-shadow:0 0 20px rgba(255,23,68,0.3);
}
.page-head .lead{margin-top:10px;color:var(--txt-dim);font-size:1.05em;max-width:720px;line-height:1.5}

/* HERO STATS — grid 4-statyk */
.hero-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px}
.stat{
    background:rgba(0,0,0,0.38);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);padding:14px;border-radius:2px;position:relative;
}
.stat::before{content:'';position:absolute;left:0;top:10%;width:2px;height:80%;background:var(--neon-red);box-shadow:0 0 6px var(--neon-red)}
.stat .lbl{font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--txt-mute);letter-spacing:2px;text-transform:uppercase;margin-bottom:4px}
.stat .val{font-family:'Oswald',sans-serif;font-size:1.7em;color:#fff;font-weight:500;line-height:1}
.stat .delta{font-family:'JetBrains Mono',monospace;font-size:.75em;margin-top:4px;color:var(--neon-ember)}
@media(max-width:820px){.hero-stats{grid-template-columns:repeat(2,1fr)}}

/* GRID KART — karty treści z h3 + .tag, opis, .stats, .action-row */
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:22px}
.card{
    background:rgba(18,10,18,0.45);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
    border:1px solid var(--border-soft);border-radius:2px;padding:18px;
    position:relative;transition:all .3s;
}
.card::before{content:'';position:absolute;top:0;left:0;width:28px;height:1px;background:var(--neon-red);box-shadow:0 0 6px var(--neon-red)}
.card:hover{border-color:var(--border-mid);box-shadow:0 0 24px rgba(255,23,68,0.12)}
.card h3{
    font-family:'Oswald',sans-serif;font-weight:500;font-size:1.05em;
    text-transform:uppercase;letter-spacing:2px;color:#fff;
    margin-bottom:12px;display:flex;align-items:center;gap:10px;
}
.card h3 .tag{
    font-family:'JetBrains Mono',monospace;font-size:.65em;color:var(--neon-red);letter-spacing:2px;font-weight:400;
    padding:2px 6px;border:1px solid var(--border-soft);border-radius:1px;
}
.card p{color:var(--txt-dim);font-size:.98em;line-height:1.55;margin-bottom:8px}
.card .stats{
    display:flex;justify-content:space-between;padding-top:12px;margin-top:12px;
    border-top:1px dashed rgba(255,23,68,0.12);
    font-family:'JetBrains Mono',monospace;font-size:.82em;
}
.card .stats span{color:var(--txt-mute)}
.card .stats b{color:var(--txt-main);font-weight:500}
.card .stats .hot{color:var(--neon-red-hot);text-shadow:0 0 6px rgba(255,23,68,0.5)}
@media(max-width:820px){.grid{grid-template-columns:1fr}}

/* PRZYCISKI STANDARDOWE */
.action-row{display:flex;gap:10px;margin-top:14px}
.btn{
    flex:1;padding:10px 16px;
    background:rgba(255,23,68,0.08);border:1px solid var(--border-mid);color:#fff;
    font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:2px;text-transform:uppercase;font-size:.85em;
    cursor:pointer;border-radius:1px;transition:all .25s;text-decoration:none;text-align:center;
}
.btn:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 18px rgba(255,23,68,0.7);text-shadow:0 0 6px rgba(255,255,255,0.8)}
.btn.ghost{background:transparent;border-color:var(--border-soft);color:var(--txt-dim)}
.btn.ghost:hover{background:rgba(255,23,68,0.1);color:#fff;border-color:var(--neon-red)}

/* SYS.LOG — stopka z dynamicznymi statusami */
.sys-log{
    font-family:'JetBrains Mono',monospace;font-size:.72em;
    color:var(--txt-mute);text-align:center;padding:14px 10px;
    border-top:1px solid var(--border-soft);
    letter-spacing:1.8px;margin-top:26px;line-height:1.6;
}
.sys-log .ok  {color:var(--neon-green);text-shadow:0 0 6px rgba(90,255,154,0.4)}
.sys-log .warn{color:var(--neon-ember);text-shadow:0 0 6px rgba(255,122,61,0.4)}

/* ── PRAWY PANEL ONLINE ─────────────────────────────────────── */
.sidebar-right h3{
    color:#fff;font-family:'Oswald',sans-serif;font-size:.95em;font-weight:500;
    text-transform:uppercase;letter-spacing:3px;
    padding-bottom:10px;margin-bottom:14px;
    border-bottom:1px solid var(--border-soft);
    display:flex;justify-content:space-between;align-items:center;
}
.online-count{color:var(--neon-red);font-family:'JetBrains Mono',monospace;font-size:.85em;text-shadow:0 0 6px rgba(255,23,68,0.5)}

.gracz-online{
    display:flex;align-items:center;gap:10px;
    padding:7px 10px;border-radius:1px;margin-bottom:1px;
    border-left:2px solid transparent;transition:all .2s;
    position:relative;cursor:default;font-size:.95em;font-weight:500;
}
.gracz-online:hover{background:rgba(255,23,68,0.06);border-left-color:var(--neon-red);padding-left:14px}
.dot{width:6px;height:6px;background:var(--neon-red);border-radius:50%;flex-shrink:0;box-shadow:0 0 8px var(--neon-red);animation:dot-pulse 2s infinite}
@keyframes dot-pulse{0%,100%{opacity:1}50%{opacity:.4}}
.gracz-online .nick-link{color:#fff;text-decoration:none;flex:1}
.gracz-online .nick-link.vip{color:var(--neon-gold)}

/* Tooltip online */
.tt{
    position:absolute;right:calc(100% + 12px);top:0;width:240px;
    background:rgba(6,4,8,0.97);backdrop-filter:blur(12px);
    border:1px solid var(--border-mid);border-radius:2px;padding:16px;
    opacity:0;visibility:hidden;pointer-events:none;
    transition:opacity .25s,visibility .25s;
    box-shadow:0 10px 40px rgba(0,0,0,0.9), 0 0 20px rgba(255,23,68,0.15);
    z-index:200;
}
.gracz-online:hover .tt{opacity:1;visibility:visible}
.tt-av{width:100%;aspect-ratio:500/625;background-position:top center!important;background-size:cover!important;border-radius:2px;margin-bottom:12px;border:1px solid var(--border-soft)}
.tt-name{font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;text-align:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px dashed rgba(255,23,68,0.15);letter-spacing:1px}
.tt-row{display:flex;justify-content:space-between;font-size:.82em;margin-bottom:6px;color:var(--txt-dim);font-family:'JetBrains Mono',monospace}
.tt-row b{color:var(--txt-main);text-align:right;font-weight:500}

/* ── SYSTEM STATUS (prawy dół) ───────────────────────────────── */
.sys-status{
    margin-top:20px;padding-top:16px;
    border-top:1px solid var(--border-soft);
    font-family:'JetBrains Mono',monospace;font-size:.75em;
    color:var(--txt-mute);line-height:1.9;
}
.sys-status .row{display:flex;justify-content:space-between;align-items:center;gap:10px}
.sys-status .row b{color:var(--neon-red);font-weight:400;text-shadow:0 0 4px rgba(255,23,68,0.4)}
.sys-status .row .ok{color:var(--neon-green);text-shadow:0 0 6px rgba(90,255,154,0.5)}

/* ── AWANS ALERT ─────────────────────────────────────────────── */
.alert-awans{
    background:rgba(255,23,68,0.08);border:1px solid var(--neon-red);
    color:var(--neon-red-hot);padding:14px;margin-bottom:22px;
    border-radius:2px;text-align:center;
    font-family:'Oswald',sans-serif;font-size:1.2em;text-transform:uppercase;letter-spacing:1px;
    box-shadow:0 0 25px rgba(255,23,68,0.3);width:100%;
    animation:awans-glow 2s ease-in-out infinite;
}
@keyframes awans-glow{0%,100%{box-shadow:0 0 20px rgba(255,23,68,0.3)}50%{box-shadow:0 0 40px rgba(255,23,68,0.6)}}
.alert-awans a{color:#fff;font-size:.7em;text-decoration:none;font-family:'Rajdhani',sans-serif;display:inline-block;margin-top:6px;letter-spacing:2px;opacity:.8}
.alert-awans a:hover{opacity:1;text-shadow:0 0 6px #fff}

@keyframes pulse-red{0%,100%{filter:drop-shadow(0 0 0 var(--neon-red))}50%{filter:drop-shadow(0 0 8px var(--neon-red))}}
.pulsuj{animation:pulse-red 1.5s infinite;display:inline-block}

/* Scrollbar */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:rgba(0,0,0,0.3)}
::-webkit-scrollbar-thumb{background:var(--neon-red);box-shadow:0 0 6px var(--neon-red)}
*{scrollbar-width:thin;scrollbar-color:var(--neon-red) rgba(0,0,0,0.3)}

input,select,textarea,button{font-family:'Rajdhani',sans-serif}

/* Reduce motion */
@media(prefers-reduced-motion:reduce){
    .app,.logo .h,.badge,.dot,.alert-awans,.pulsuj{animation:none!important}
}
</style>
</head>
<body>
<div class="app">
<div class="corners"></div>

<!-- ══ LEWY SIDEBAR ══════════════════════════════════════════════ -->
<div class="sidebar">
    <div class="logo">
        <span class="t">&lt; <?php echo htmlspecialchars(ucfirst(strtolower($obecne_miasto))); ?> &gt;</span>
        <span class="h">The Abyss</span>
        <span class="sub">// SHARD <?php echo $kod_shardu; ?></span>
    </div>

    <div class="quick-profile">
        <div class="quick-name">
            <?php if($gracz_r['is_premium']) echo "<span class='vip'>★</span> "; ?>
            <?php if($poch = pochodzenie_dane($gracz_r['pochodzenie'])): ?>
                <span title="<?php echo htmlspecialchars($poch['nazwa_m']); ?>" style="filter:drop-shadow(0 0 6px <?php echo $poch['kolor_akcent']; ?>)"><?php echo $poch['flaga']; ?></span>
            <?php endif; ?>
            <span class="nick"><?php echo htmlspecialchars($gracz_r['login']); ?></span>
            <span class="id">#<?php echo $id_gracza; ?></span>
        </div>

        <div class="quick-avatar" style="background-image:url('<?php echo $avatar_url; ?>')"></div>

        <div class="qs"><span>Poziom</span><b><?php echo $gracz_r['poziom']; ?></b></div>
        <div class="qs"><span>Gotówka</span><b class="money"><?php echo number_format($gracz_r['gotowka'],0,'','&nbsp;'); ?> $</b></div>

        <div class="bar-label">Zdrowie</div>
        <div class="bar-wrap">
            <?php $php = min(100,($gracz_r['hp_aktualne']/$gracz_r['hp_max'])*100); ?>
            <div class="bar-fill fill-hp" style="width:<?php echo $php; ?>%"></div>
            <div class="bar-txt"><?php echo $gracz_r['hp_aktualne'].'/'.$gracz_r['hp_max']; ?></div>
        </div>
        <div class="bar-label">Energia</div>
        <div class="bar-wrap">
            <?php $pen = min(100,($gracz_r['energia_aktualna']/$gracz_r['energia_max'])*100); ?>
            <div class="bar-fill fill-en" style="width:<?php echo $pen; ?>%"></div>
            <div class="bar-txt"><?php echo $gracz_r['energia_aktualna'].'/'.$gracz_r['energia_max']; ?></div>
        </div>
        <?php $pex = min(100,($gracz_r['exp']/($gracz_r['poziom']*100))*100); ?>
        <div class="bar-label">Doświadczenie</div>
        <div class="bar-wrap">
            <div class="bar-fill fill-exp" style="width:<?php echo $pex; ?>%"></div>
            <div class="bar-txt"><?php echo $gracz_r['exp'].'/'.($gracz_r['poziom']*100); ?></div>
        </div>
    </div>

    <a href="game.php?page=miasto" class="btn-miasto">◤ Wejdź do Miasta ◥</a>

    <?php
    function nav($url,$label,$akt,$cur,$cls=''){
        $a = ($akt==$cur) ? " aktywny $cls" : ($cls?" $cls":"");
        echo "<a href='$url' class='menu-link$a'>$label</a>";
    }
    ?>

    <div class="nav-title">Moja Postać</div>
    <?php nav("game.php?page=witaj","🏠 Kwatera Główna",$aktualna,'witaj'); ?>
    <?php nav("game.php?page=mieszkanie","🛋️ Mieszkanie",$aktualna,'mieszkanie'); ?>
    <?php nav("game.php?page=karta","📋 Karta Postaci",$aktualna,'karta'); ?>
    <?php nav("game.php?page=umiejetnosci","🧠 Umiejętności",$aktualna,'umiejetnosci'); ?>
    <?php nav("game.php?page=ekwipunek","🎒 Ekwipunek",$aktualna,'ekwipunek'); ?>

    <a href="game.php?page=poczta" class="menu-link<?php echo ($aktualna=='poczta'&&$zakladka!='alerty')?' aktywny':''; ?>">
        <span class="<?php echo $n_poczta>0?'pulsuj':''; ?>">✉️</span> Poczta
        <?php if($n_poczta>0) echo "<span class='badge'>$n_poczta</span>"; ?>
    </a>
    <a href="game.php?page=poczta&zakladka=alerty" class="menu-link<?php echo ($aktualna=='poczta'&&$zakladka=='alerty')?' aktywny':''; ?>">
        <span class="<?php echo $n_alerty>0?'pulsuj':''; ?>">🔔</span> Powiadomienia
        <?php if($n_alerty>0) echo "<span class='badge badge-ember'>$n_alerty</span>"; ?>
    </a>

    <div class="nav-title">Miasto</div>
    <?php nav("game.php?page=uniwersytet","🎓 Uniwersytet",$aktualna,'uniwersytet'); ?>
    <a href="game.php?page=premium" class="menu-link<?php echo $aktualna=='premium'?' aktywny-gold':''; ?>">★ Premium</a>
    <?php nav("game.php?page=bank","🏦 Bank Centralny",$aktualna,'bank'); ?>
    <?php nav("game.php?page=szpital","🏥 Klinika Rzeźnika",$aktualna,'szpital'); ?>
    <a href="game.php?page=sesje" class="menu-link<?php echo ($aktualna=='sesje'||$aktualna=='pokoj_sesji')?' aktywny-ember':''; ?>">🎭 Centrum Opowieści</a>
      <a href="game.php?page=czat" class="menu-link<?php echo $aktualna=='czat'?' aktywny':''; ?>">🍸 Klub The Abyss</a>
      <?php nav("game.php?page=katedra","⛪ Katedra",$aktualna,'katedra'); ?>
 
    <?php nav("game.php?page=lotnisko","✈️ Port Lotniczy",$aktualna,'lotnisko'); ?>

    <div class="nav-title">Eksploracja</div>
    <?php nav("game.php?page=zlecenia","📜 Tablica Zleceń",$aktualna,'zlecenia'); ?>
    <a href="game.php?page=lista_firm" class="menu-link<?php echo ($aktualna=='lista_firm'||$aktualna=='profil_firmy')?' aktywny':''; ?>">🏢 Katalog firm</a>
    <?php nav("game.php?page=doki","⚔️ Walka (Doki)",$aktualna,'doki'); ?>
    <?php nav("game.php?page=zlomowisko","🔩 Złomowisko",$aktualna,'zlomowisko'); ?>
    <?php nav("game.php?page=rynek","🕶️ Czarny Rynek",$aktualna,'rynek'); ?>
    <a href="game.php?page=kasyno" class="menu-link<?php echo $aktualna=='kasyno'?' aktywny-gold':''; ?>">🎰 Kasyno</a>
    <?php nav("game.php?page=syndykaty","🏴 Syndykaty",$aktualna,'syndykaty'); ?>

    <div class="nav-title">Konto</div>
    <?php nav("game.php?page=ustawienia","⚙️ Ustawienia",$aktualna,'ustawienia'); ?>
    <a href="logout.php" class="menu-link danger">🚪 Wyloguj się</a>
</div>

<!-- ══ TREŚĆ ══════════════════════════════════════════════════════ -->
<div class="content">
    <div class="page-wrap">

        <!-- TOPBAR: lokacja (z flagą) · czas (JS) · sygnał -->
        <div class="topbar">
            <span class="loc">▸ LOC: <span class="flag"><?php echo $flaga_miasta; ?></span><?php echo htmlspecialchars(strtoupper($obecne_miasto)); ?> // <?php echo $etykieta_lokacji; ?></span>
            <span class="time">NET.TIME <b id="net-time"><?php echo date('H:i:s'); ?></b> · <span id="net-date"><?php echo date('d.m.Y'); ?></span></span>
            <span class="signal">SIGNAL ▮▮▮▮▯ 4/5</span>
        </div>

        <?php
        if (isset($_SESSION['powiadomienie_awans'])) {
            echo "<div class='alert-awans'>⚡ ".$_SESSION['powiadomienie_awans']." ⚡<br>
                  <a href='game.php?page=karta'>[ Przejdź do Karty Postaci ]</a></div>";
            unset($_SESSION['powiadomienie_awans']);
        }
        if (in_array($strona,$dozwolone_strony)) {
            $sc = "pages/$strona.php";
            if (file_exists($sc)) include $sc;
            else echo "<p style='color:#ff6678;background:rgba(0,0,0,0.8);padding:20px;border-radius:2px;border:1px solid var(--border-mid)'>BŁĄD: Plik nie istnieje.</p>";
        } else echo "<p style='color:#ff6678;background:rgba(0,0,0,0.8);padding:20px;border-radius:2px;border:1px solid var(--border-mid)'>Nieznana lokalizacja.</p>";
        ?>

        <!-- ── DYNAMICZNA STOPKA SYS.LOG — reaguje na stan gracza ── -->
        <div class="sys-log">
            // SYS.LOG — <span id="sys-clock"><?php echo date('H:i:s'); ?></span> — <?php echo implode(' — ', $sys_log_parts); ?>
        </div>

    </div>
</div>

<!-- ══ PRAWY SIDEBAR — ONLINE + SYSTEM STATUS ══════════════════ -->
<div class="sidebar-right">
    <h3>Online <span class="online-count">[ <?php echo $ilosc_online; ?> ]</span></h3>
    <?php foreach($lista_online as $o):
        $img  = !empty($o['avatar']) ? htmlspecialchars($o['avatar']) : "https://via.placeholder.com/500x625/0a0a0a/333?text=PORTRET";
        $kol  = $o['is_premium'] ? 'vip' : '';
        $synd = !empty($o['s_nazwa']) ? "[".htmlspecialchars($o['s_tag'])."] ".htmlspecialchars($o['s_nazwa']) : "Brak";
    ?>
    <div class="gracz-online">
        <div class="dot"></div>
        <a href="game.php?page=profil&id=<?php echo $o['id']; ?>" class="nick-link <?php echo $kol; ?>">
            <?php echo ($o['is_premium']?"★ ":"").htmlspecialchars($o['login']); ?>
        </a>
        <div class="tt">
            <div class="tt-name" style="color:<?php echo $o['is_premium']?'var(--neon-gold)':'#fff'; ?>"><?php echo ($o['is_premium']?"★ ":"").htmlspecialchars($o['login']); ?></div>
            <div class="tt-av" style="background-image:url('<?php echo $img; ?>')"></div>
            <div class="tt-row"><span>Dni w mieście</span><b><?php echo max(0,$o['dni']); ?></b></div>
            <div class="tt-row"><span>Klasa</span><b><?php echo htmlspecialchars($o['klasa']); ?></b></div>
            <div class="tt-row"><span>Profesja</span><b style="color:var(--neon-ember)"><?php echo $o['profesja_fabularna']?:'-'; ?></b></div>
            <div class="tt-row"><span>Syndykat</span><b style="color:var(--neon-red-hot)"><?php echo $synd; ?></b></div>
            <?php if(!empty($o['nazwa_firmy'])): ?>
            <div style="margin-top:10px;padding-top:10px;border-top:1px dashed rgba(255,23,68,0.15);text-align:center">
                <div style="color:var(--neon-gold);font-family:'Oswald',sans-serif;font-size:.82em;margin-bottom:6px;letter-spacing:1.5px">💼 WŁAŚCICIEL FIRMY</div>
                <div class="tt-row"><span>Firma</span><b><?php echo htmlspecialchars($o['nazwa_firmy']); ?></b></div>
                <div class="tt-row"><span>Branża</span><b><?php echo htmlspecialchars($o['branza_firmy']); ?></b></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- SYSTEM STATUS — teraz z realną pogodą! -->
    <div class="sys-status">
        <div class="row"><span>UPLINK</span><b class="ok">● ONLINE</b></div>
        <div class="row"><span>PING</span><b id="sys-ping">— ms</b></div>
        <div class="row"><span>PATCH</span><b><?php echo $wersja_gry; ?></b></div>
        <div class="row"><span>SHARD</span><b><?php echo $kod_shardu; ?></b></div>
        <div class="row"><span>WEATHER</span><b style="color:<?php echo $pogoda[1]; ?>;text-shadow:0 0 6px <?php echo $pogoda[1]; ?>"><?php echo htmlspecialchars($pogoda[0]); ?></b></div>
    </div>
</div>

</div><!-- .app -->

<script>
/* ── TOPBAR + SYS.LOG: zegar real-time ──────────────── */
(function(){
    const timeEl = document.getElementById('net-time');
    const dateEl = document.getElementById('net-date');
    const sysEl  = document.getElementById('sys-clock');
    function tick(){
        const d = new Date();
        const pad = n => String(n).padStart(2,'0');
        const s = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        timeEl.textContent = s;
        if(sysEl) sysEl.textContent = s;
        dateEl.textContent = `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${d.getFullYear()}`;
    }
    tick(); setInterval(tick, 1000);
})();

/* ── SYSTEM STATUS: realny ping do serwera ────────────
   Mierzymy czas fetch na sam game.php. Powtarza się co 15 s. */
(function(){
    const el = document.getElementById('sys-ping');
    async function mierz(){
        try{
            const t0 = performance.now();
            await fetch(window.location.pathname + '?t=' + Date.now(), {method:'HEAD', cache:'no-store'});
            const ms = Math.round(performance.now() - t0);
            el.textContent = ms + ' ms';
            el.style.color = ms < 80 ? 'var(--neon-green)' : (ms < 200 ? 'var(--neon-ember)' : 'var(--neon-red)');
        }catch(e){ el.textContent = 'OFFLINE'; el.style.color = 'var(--neon-red)'; }
    }
    mierz(); setInterval(mierz, 15000);
})();
</script>
</body>
</html>