<?php
// ═══════════════════════════════════════════════════════════════════
// KLUB THE ABYSS — ROUTER (faza 2)
// game.php?page=czat[&sala=nazwa-sali]
// ═══════════════════════════════════════════════════════════════════
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];
$login_gracza = $_SESSION['login'];

// ── KATALOG SAL ────────────────────────────────────────────────────
$SALE = [
    'lobby'       => ['nazwa'=>'Lobby',        'ikona'=>'◉', 'aktywna'=>true,  'opis'=>'Wejście, mapa klubu, ogłoszenia.'],
    'sala-glowna' => ['nazwa'=>'Sala Główna',  'ikona'=>'🍸', 'aktywna'=>true,  'opis'=>'Bar, parkiet, DJ. Serce klubu.'],
    'sauna'       => ['nazwa'=>'Sauna',        'ikona'=>'♨',  'aktywna'=>true,  'opis'=>'Para, cisza, pot.'],
    'bdsm'        => ['nazwa'=>'Pokój BDSM',   'ikona'=>'⛓',  'aktywna'=>true,  'opis'=>'Consent. Safewords. Pokój prywatny.'],
    'tyly'        => ['nazwa'=>'Tyły klubu',   'ikona'=>'🚬', 'aktywna'=>true,  'opis'=>'Papieros, deszcz, cień.'],
    'sala-balowa' => ['nazwa'=>'Sala Balowa',  'ikona'=>'💃', 'aktywna'=>true,  'opis'=>'Wydarzenia, gala, taniec.'],
    'basen'       => ['nazwa'=>'Basen',        'ikona'=>'🏊', 'aktywna'=>true,  'opis'=>'Woda, neon, echo.'],
    'silownia'    => ['nazwa'=>'Siłownia',     'ikona'=>'💪', 'aktywna'=>true,  'opis'=>'Stal, skóra, wysiłek.'],
    'masaze'      => ['nazwa'=>'Masaże',       'ikona'=>'💆', 'aktywna'=>true,  'opis'=>'Olejki, muzyka, szept.'],
    'vip'         => ['nazwa'=>'Loża VIP',     'ikona'=>'🥂', 'aktywna'=>true,  'opis'=>'Złoto, szept, dyskrecja.'],
    'taras'       => ['nazwa'=>'Taras',        'ikona'=>'🌃', 'aktywna'=>true,  'opis'=>'Dach. Miasto pod stopami.'],
    'garderoba'   => ['nazwa'=>'Garderoba',    'ikona'=>'👗', 'aktywna'=>true,  'opis'=>'Lustra, jedwab, perfumy.'],
];

$sala = isset($_GET['sala']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['sala'])) : 'lobby';
if (!isset($SALE[$sala])) $sala = 'lobby';
$dane_sali = $SALE[$sala];
$komunikat = "";

// ── DANE GRACZA ────────────────────────────────────────────────────
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$jest_barmanem = (bool)($gracz['is_barman'] ?? 0);
$jest_mg       = (bool)($gracz['is_mg'] ?? 0);
$ma_uprawnienia = ($jest_barmanem || $jest_mg);

// ── KONFIGURACJA KLUBU ────────────────────────────────────────────
$klub = $polaczenie->query("SELECT * FROM klub_konfiguracja WHERE id=1")->fetch_assoc();
if (!$klub) {
    $polaczenie->query("INSERT INTO klub_konfiguracja (id, otwarty) VALUES (1, 1)");
    $klub = ['id'=>1,'otwarty'=>1,'zamkniety_przez'=>null,'powod_zamkniecia'=>null,'czas_ponownego_otwarcia'=>null,'ogloszenie'=>null];
}
if (!$klub['otwarty'] && $klub['czas_ponownego_otwarcia'] && strtotime($klub['czas_ponownego_otwarcia']) <= time()) {
    $polaczenie->query("UPDATE klub_konfiguracja SET otwarty=1, zamkniety_przez=NULL, powod_zamkniecia=NULL, czas_zamkniecia=NULL, czas_ponownego_otwarcia=NULL WHERE id=1");
    $klub['otwarty'] = 1;
}

// ── BAN CHECK ──────────────────────────────────────────────────────
$ban = $polaczenie->query("SELECT * FROM klub_bany WHERE gracz_id=$id_gracza AND aktywny=1 AND do_kiedy > NOW()")->fetch_assoc();
if ($ban && !$ma_uprawnienia) {
    $czas_bana = date('d.m H:i', strtotime($ban['do_kiedy']));
    echo "<div class='klub-wstep-blokada'>
        🚫 <b>Nie wejdziesz do klubu.</b><br>
        Ochroniarz Mirek zastąpił Ci drogę. Wracasz, gdzie byłeś.<br><br>
        <small>Powód: ".htmlspecialchars($ban['powod'] ?: 'nie podano')."</small><br>
        <small>Wracasz: <b>$czas_bana</b></small>
    </div>
    <style>.klub-wstep-blokada{background:rgba(255,23,68,.08);border:1px solid var(--neon-red);color:var(--neon-red-hot);padding:40px;border-radius:2px;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:1.1em;line-height:1.6;margin-top:20px;box-shadow:0 0 30px rgba(255,23,68,.15)}.klub-wstep-blokada small{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1px;color:var(--txt-dim);text-transform:uppercase}</style>";
    return;
}
$polaczenie->query("UPDATE klub_bany SET aktywny=0 WHERE aktywny=1 AND do_kiedy <= NOW()");

// ── FAZA 3: CHECK WYPROSZENIA Z KONKRETNEJ SALI (server-side) ──
$polaczenie->query("UPDATE klub_wypraszania SET aktywne=0 WHERE aktywne=1 AND do_kiedy <= NOW()");
if (!$ma_uprawnienia && $sala !== 'lobby') {
    $wyp = $polaczenie->query("
        SELECT w.do_kiedy, w.powod, g.login AS barman_login
        FROM klub_wypraszania w
        LEFT JOIN gracze g ON g.id = w.barman_id
        WHERE w.gracz_id=$id_gracza AND w.sala='" . $polaczenie->real_escape_string($sala) . "' AND w.aktywne=1
        ORDER BY w.id DESC LIMIT 1
    ")->fetch_assoc();
    if ($wyp) {
        $do_kiedy_fmt = date('H:i', strtotime($wyp['do_kiedy']));
        $barman_login = htmlspecialchars($wyp['barman_login'] ?: 'barman');
        $powod = htmlspecialchars($wyp['powod'] ?: '');
        echo "<div class='klub-wstep-blokada' style='margin-top:20px'>
            🚪 <b>Wyproszony/a z tej sali</b><br><br>
            <small style='font-family:Cormorant Garamond,serif;font-size:1em;font-style:italic;color:#ddd;letter-spacing:0;text-transform:none;line-height:1.5;display:block;margin-bottom:12px'>
                <b style='font-family:Oswald,sans-serif;color:var(--neon-red-hot);font-size:.9em;letter-spacing:1.5px'>$barman_login</b> zamknął/a Ci dostęp do tej sali.
                " . ($powod ? "<br>Powód: <i>$powod</i>" : "") . "
            </small>
            <small>Wracasz: <b>$do_kiedy_fmt</b></small><br>
            <a href='game.php?page=czat&sala=lobby' style='display:inline-block;margin-top:14px;padding:10px 22px;background:rgba(255,23,68,0.1);border:1px solid var(--neon-red);color:var(--neon-red-hot);text-decoration:none;letter-spacing:2.5px;font-family:Oswald,sans-serif;text-transform:uppercase;font-size:.85em;border-radius:2px'>◂ Wróć do Lobby</a>
        </div>
        <style>.klub-wstep-blokada{background:rgba(255,23,68,.08);border:1px solid var(--neon-red);color:var(--neon-red-hot);padding:40px;border-radius:2px;text-align:center;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:1.1em;line-height:1.6;box-shadow:0 0 30px rgba(255,23,68,.15)}.klub-wstep-blokada small{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1px;color:var(--txt-dim);text-transform:uppercase;display:block;margin-top:6px}</style>";
        return;
    }
}

// ── FAZA 5: CHECK DOSTĘPU DO LOŻY VIP (server-side) ──────────────
$polaczenie->query("UPDATE klub_vip_zaplaty SET aktywny=0 WHERE aktywny=1 AND waznosc_do <= NOW()");
if ($sala === 'vip' && !$ma_uprawnienia) {
    $vip_z = $polaczenie->query("
        SELECT id, waznosc_do FROM klub_vip_zaplaty
        WHERE gracz_id=$id_gracza AND aktywny=1 AND waznosc_do > NOW()
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    if (!$vip_z) {
        $gotowka = (int)$gracz['gotowka'];
        $cena = 500;
        $btn_zaplac = ($gotowka >= $cena)
            ? "<button onclick='zaplacVIP()' style='display:inline-block;margin-top:14px;padding:12px 28px;background:linear-gradient(90deg,#7a5e0a,#c9a961);border:1px solid var(--neon-gold);color:#000;text-decoration:none;letter-spacing:3px;font-family:Oswald,sans-serif;text-transform:uppercase;font-size:.9em;border-radius:2px;cursor:pointer;box-shadow:0 0 18px rgba(255,215,0,0.4);font-weight:600'>✦ ZAPŁAĆ $cena \$</button>"
            : "<div style='margin-top:14px;color:var(--neon-red-hot);font-family:JetBrains Mono,monospace;font-size:.78em;letter-spacing:1.5px'>BRAK GOTÓWKI — POTRZEBA " . ($cena - $gotowka) . " \$ WIĘCEJ</div>";
        echo "<div class='klub-wstep-blokada' style='margin-top:20px;text-align:center'>
            🥂 <b>Loża VIP</b><br><br>
            <small style='font-family:Cormorant Garamond,serif;font-size:1.1em;font-style:italic;color:#ddd;letter-spacing:0;text-transform:none;line-height:1.5;display:block;margin-bottom:14px'>
                Złote lustra, kelnerzy w smokingach, szampan w wiaderkach.<br>
                Wstęp $cena \$ — ważny do 6:00 rano.
            </small>
            <small>Twoja gotówka: <b>" . number_format($gotowka, 0, '', ' ') . " \$</b></small><br>
            $btn_zaplac
            <br>
            <a href='game.php?page=czat&sala=lobby' style='display:inline-block;margin-top:10px;padding:8px 18px;background:transparent;border:1px solid var(--border-mid);color:var(--txt-dim);text-decoration:none;letter-spacing:2px;font-family:Oswald,sans-serif;text-transform:uppercase;font-size:.78em;border-radius:2px'>◂ Wróć do Lobby</a>
        </div>
        <style>.klub-wstep-blokada{background:linear-gradient(135deg,rgba(255,215,0,0.06),rgba(0,0,0,0.5));border:1px solid var(--neon-gold);color:var(--neon-gold);padding:40px;border-radius:2px;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:1.2em;line-height:1.6;box-shadow:0 0 40px rgba(255,215,0,.15)}.klub-wstep-blokada small{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.5px;color:var(--txt-dim);text-transform:uppercase;display:block;margin-top:6px}.klub-wstep-blokada b{color:#fff;text-shadow:0 0 8px rgba(255,215,0,0.6)}</style>
        <script>
        async function zaplacVIP(){
            if(!confirm('Zapłacić $cena \$ za wstęp do Loży VIP do 6:00 rano?'))return;
            const fd = new FormData();
            fd.append('op','zaplac');
            try {
                const r = await fetch('api/klub_vip.php', {method:'POST',body:fd,credentials:'same-origin'});
                const d = await r.json();
                if(d.ok){alert(d.msg||'OK');location.reload();}
                else alert(d.msg||'Błąd');
            } catch(e){alert('Brak połączenia');}
        }
        </script>";
        return;
    }
}

// ── AKTUALIZACJA OBECNOŚCI ────────────────────────────────────────
$sala_sql = $polaczenie->real_escape_string($sala);
$polaczenie->query("UPDATE gracze SET klub_sala='$sala_sql', klub_ostatnie_wejscie=NOW() WHERE id=$id_gracza");

// ═══════════════════════════════════════════════════════════════════
// POST: AKCJE BARMANA (admin) — zwykłe wiadomości idą przez AJAX
// ═══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['zamknij_klub']) && $ma_uprawnienia) {
        $godz = max(1, min(24, (int)$_POST['godziny_zamkniecia']));
        $powod = $polaczenie->real_escape_string(trim($_POST['powod'] ?? 'Zamknięte do odwołania'));
        $do_kiedy = date('Y-m-d H:i:s', time() + $godz * 3600);
        $polaczenie->query("UPDATE klub_konfiguracja SET otwarty=0, zamkniety_przez=$id_gracza, powod_zamkniecia='$powod', czas_zamkniecia=NOW(), czas_ponownego_otwarcia='$do_kiedy' WHERE id=1");
        $komunikat = "<div class='msg-ok'>🚪 Klub zamknięty na $godz godz.</div>";
    }
    if (isset($_POST['otworz_klub']) && $ma_uprawnienia) {
        $polaczenie->query("UPDATE klub_konfiguracja SET otwarty=1, zamkniety_przez=NULL, powod_zamkniecia=NULL, czas_zamkniecia=NULL, czas_ponownego_otwarcia=NULL WHERE id=1");
        $komunikat = "<div class='msg-ok'>🔓 Klub ponownie otwarty.</div>";
    }
    if (isset($_POST['banuj_gracza']) && $ma_uprawnienia) {
        $cel_id = (int)$_POST['cel_id'];
        $godz_banu = max(1, min(168, (int)$_POST['godziny_banu']));
        $powod = $polaczenie->real_escape_string(trim($_POST['powod_banu'] ?? ''));
        $do_kiedy = date('Y-m-d H:i:s', time() + $godz_banu * 3600);
        $cel = $polaczenie->query("SELECT login, is_barman, is_mg FROM gracze WHERE id=$cel_id")->fetch_assoc();
        if (!$cel) {
            $komunikat = "<div class='msg-blad'>Nie ma gracza o ID $cel_id.</div>";
        } elseif ($cel['is_barman'] || $cel['is_mg']) {
            $komunikat = "<div class='msg-blad'>Nie możesz zbanować Barmana ani MG.</div>";
        } else {
            $polaczenie->query("INSERT INTO klub_bany (gracz_id, barman_id, powod, do_kiedy) VALUES ($cel_id, $id_gracza, '$powod', '$do_kiedy')");
            $pow = "🚫 <b>Barman {$gracz['login']}</b> zbanował Cię w klubie na $godz_banu godz. Powód: $powod";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '$pow')");
            $polaczenie->query("UPDATE gracze SET klub_sala=NULL WHERE id=$cel_id");
            $komunikat = "<div class='msg-ok'>Gracz <b>".htmlspecialchars($cel['login'])."</b> zbanowany na $godz_banu godz.</div>";
        }
    }
    if (isset($_POST['dodaj_ogloszenie']) && $ma_uprawnienia) {
        $tresc = $polaczenie->real_escape_string(trim($_POST['tresc_ogloszenia'] ?? ''));
        if (!empty($tresc)) {
            $polaczenie->query("UPDATE klub_konfiguracja SET ogloszenie='$tresc', ogloszenie_autor_id=$id_gracza, ogloszenie_data=NOW() WHERE id=1");
            $komunikat = "<div class='msg-ok'>📢 Ogłoszenie dodane.</div>";
        }
    }
    if (isset($_POST['usun_ogloszenie']) && $ma_uprawnienia) {
        $polaczenie->query("UPDATE klub_konfiguracja SET ogloszenie=NULL, ogloszenie_autor_id=NULL, ogloszenie_data=NULL WHERE id=1");
        $komunikat = "<div class='msg-ok'>📢 Ogłoszenie usunięte.</div>";
    }
}

// Odśwież dane gracza po POST
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();

// ═══════════════════════════════════════════════════════════════════
// HELPERY DLA SAL
// ═══════════════════════════════════════════════════════════════════

function klub_obecni_w_sali($polaczenie, $sala_key) {
    $s = $polaczenie->real_escape_string($sala_key);
    $q = $polaczenie->query("SELECT id, login, avatar, is_barman, is_mg, klub_mood 
                             FROM gracze 
                             WHERE klub_sala='$s' 
                               AND ostatnia_aktywnosc >= NOW() - INTERVAL 5 MINUTE
                             ORDER BY is_barman DESC, is_mg DESC, login ASC");
    $out = [];
    if ($q) while($r = $q->fetch_assoc()) $out[] = $r;
    return $out;
}

function klub_licznik_sal($polaczenie) {
    $q = $polaczenie->query("SELECT klub_sala, COUNT(*) as c FROM gracze 
                             WHERE klub_sala IS NOT NULL 
                               AND ostatnia_aktywnosc >= NOW() - INTERVAL 5 MINUTE
                             GROUP BY klub_sala");
    $out = [];
    if ($q) while($r = $q->fetch_assoc()) $out[$r['klub_sala']] = (int)$r['c'];
    return $out;
}

function klub_wiadomosci($polaczenie, $sala_key, $limit = 50) {
    $s = $polaczenie->real_escape_string($sala_key);
    $limit = (int)$limit;
    $q = $polaczenie->query("SELECT id, login, tresc, typ, summoner_id, DATE_FORMAT(data_wyslania,'%H:%i') AS czas 
                             FROM czat 
                             WHERE sala='$s' 
                             ORDER BY id DESC 
                             LIMIT $limit");
    $out = [];
    if ($q) while($r = $q->fetch_assoc()) $out[] = $r;
    return array_reverse($out);
}

function klub_inicjaly($login) {
    $login = preg_replace('/\s*\[(BARMAN|MG|NPC)\]/i', '', $login);
    $login = trim($login);
    if (preg_match('/([A-Za-zĄĆĘŁŃÓŚŹŻąćęłńóśźż0-9])[^_\s]*[_\s]*([A-Za-zĄĆĘŁŃÓŚŹŻąćęłńóśźż0-9])?/u', $login, $m)) {
        return mb_strtoupper(($m[1] ?? '') . ($m[2] ?? ''));
    }
    return mb_strtoupper(mb_substr($login, 0, 2));
}

// ── PARSER RICH-TEXT (server-side, używany przy initial render) ──
function klub_parse_rich($tresc) {
    $s = htmlspecialchars($tresc, ENT_QUOTES, 'UTF-8');
    // **bold**
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<span class="emph">$1</span>', $s);
    // *narracja*
    $s = preg_replace('/\*(.+?)\*/u', '<span class="nar">$1</span>', $s);
    // "dialog"  oraz  „dialog"
    $s = preg_replace('/&quot;(.+?)&quot;/u', '<span class="dialog">"$1"</span>', $s);
    $s = preg_replace('/(„)(.+?)(")/u', '<span class="dialog">$1$2$3</span>', $s);
    // — myślnik = dialog
    $s = preg_replace('/(^|\s)(— [^*"„<\n]+?)(?=$|<|\n)/mu', '$1<span class="dialog">$2</span>', $s);
    // @nick
    $s = preg_replace('/(^|\s)@([A-Za-z0-9_\-]{2,32})/u', '$1<span class="mention">@$2</span>', $s);
    return $s;
}

// ── FAZA 5: Aktywne wydarzenie w danej sali ─────────────────────
// Zwraca dane eventu który TERAZ trwa w tej sali, lub null
// "Trwa" = data_startu <= NOW() AND (data_konca IS NULL AND data_startu+4h >= NOW()
//                                    OR data_konca >= NOW())
function klub_aktywne_wydarzenie($polaczenie, $sala_key, $id_gracza) {
    $s = $polaczenie->real_escape_string($sala_key);
    $id_gracza = (int)$id_gracza;
    $q = $polaczenie->query("
        SELECT w.*, g.login AS autor_login,
               (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id) AS liczba_rezerwacji,
               (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id AND gracz_id=$id_gracza) AS moja_rezerwacja
        FROM klub_wydarzenia w
        LEFT JOIN gracze g ON g.id = w.autor_id
        WHERE w.aktywne=1 AND w.anulowane=0
          AND w.sala='$s'
          AND w.data_startu <= NOW()
          AND (
            (w.data_konca IS NULL AND w.data_startu >= NOW() - INTERVAL 4 HOUR)
            OR (w.data_konca IS NOT NULL AND w.data_konca >= NOW())
          )
        ORDER BY w.data_startu DESC LIMIT 1
    ");
    if ($q && ($r = $q->fetch_assoc())) return $r;
    return null;
}

// Lista uczestników eventu (zarezerwowanych)
function klub_uczestnicy_eventu($polaczenie, $event_id) {
    $eid = (int)$event_id;
    $q = $polaczenie->query("
        SELECT g.id, g.login, g.avatar, g.is_barman, g.is_mg, g.klub_sala
        FROM klub_rezerwacje r
        INNER JOIN gracze g ON g.id = r.gracz_id
        WHERE r.wydarzenie_id=$eid
        ORDER BY g.is_barman DESC, g.is_mg DESC, g.login ASC
    ");
    $out = [];
    if ($q) while ($r = $q->fetch_assoc()) $out[] = $r;
    return $out;
}

function klub_render_msg($w) {
    // Wiadomości systemowe (napiwki, zamówienia, ban) — wyśrodkowane
    if (($w['typ'] ?? '') === 'system') {
        $tresc = $w['tresc']; // już ma <b> i <i>, nie escapujemy
        echo "<div class='msg sys' data-id='".(int)$w['id']."'>
            <span class='sys-line'>$tresc <span class='sys-time'>".htmlspecialchars($w['czas'])."</span></span>
        </div>";
        return;
    }

    $login_raw = $w['login'];
    $login = htmlspecialchars($login_raw);

    $klasa = 'msg';
    $role_label = '';
    // FAZA 3: typ npc_speak (barman/MG mówi za NPC)
    if (($w['typ'] ?? '') === 'npc_speak') {
        $klasa .= ' npc speak';
        $role_label = 'NPC';
    }
    elseif (strpos($login_raw, 'Barman [NPC]') !== false)  { $klasa .= ' npc bot'; $role_label = 'Barman NPC'; }
    elseif (strpos($login_raw, 'Krupier [NPC]') !== false) { $klasa .= ' npc'; $role_label = 'Krupier NPC'; }
    elseif (strpos($login_raw, '[BARMAN]') !== false)  { $klasa .= ' barman'; $role_label = 'Barman'; }
    elseif (strpos($login_raw, '[MG]') !== false)      { $klasa .= ' mg'; $role_label = 'MG'; }

    $login_clean = preg_replace('/\s*\[(BARMAN|MG|NPC)\]/i', '', $login_raw);
    $inicjaly = klub_inicjaly($login_raw);
    $tresc_html = klub_parse_rich($w['tresc']);

    // Dodatkowa notka dla npc_speak: kto wyzwał
    $summon_note = '';
    if (($w['typ'] ?? '') === 'npc_speak' && !empty($w['summoner_id'])) {
        global $polaczenie;
        $sum = $polaczenie->query("SELECT login FROM gracze WHERE id=" . (int)$w['summoner_id'])->fetch_assoc();
        if ($sum) {
            $summon_note = "<span class='summon-by'>wyzwał: " . htmlspecialchars($sum['login']) . "</span>";
        }
    }

    echo "<div class='$klasa' data-id='".(int)$w['id']."'>
        <div class='av'>".htmlspecialchars($inicjaly)."</div>
        <div class='body'>
            <div class='who'>
                <span class='nm'>".htmlspecialchars($login_clean)."</span>
                ".($role_label ? "<span class='role'>$role_label</span>" : "")."
                $summon_note
                <span class='when'>".htmlspecialchars($w['czas'])."</span>
            </div>
            <div class='txt'>$tresc_html</div>
        </div>
    </div>";
}

// Pobiera ostatnie ID wiadomości w sali (do AJAX-a żeby nie pobierać duplikatów)
function klub_last_id($polaczenie, $sala_key) {
    $s = $polaczenie->real_escape_string($sala_key);
    $q = $polaczenie->query("SELECT MAX(id) AS m FROM czat WHERE sala='$s'");
    if ($q) {
        $r = $q->fetch_assoc();
        return (int)($r['m'] ?? 0);
    }
    return 0;
}

// ═══════════════════════════════════════════════════════════════════
// SHARED CSS — wspólny layout klubu (Faza 2: rozszerzony)
// ═══════════════════════════════════════════════════════════════════
?>
<style>
/* ── KLUB: layout 3-kolumnowy ─────────────────────────────────── */
.klub-wrap { --room-accent: var(--neon-red); }
.klub-3col {
    display: grid; grid-template-columns: 240px 1fr 240px;
    gap: 0; min-height: 620px;
    background: rgba(6,3,9,0.55); border: 1px solid var(--border-soft);
    border-radius: 2px; overflow: hidden;
    margin-bottom: 20px;
}
@media(max-width:1100px){ .klub-3col{ grid-template-columns: 1fr; } .kol-left,.kol-right{ display:none } }

.kol-left, .kol-right {
    background: rgba(10,5,12,0.4); padding: 14px;
    border-right: 1px solid var(--border-soft);
    max-height: 700px; overflow-y: auto;
}
.kol-right { border-right: none; border-left: 1px solid var(--border-soft); }
.kol-center { min-width: 0; display: flex; flex-direction: column; background: rgba(6,3,9,0.4); position: relative; }

/* ── ROOM HEADER ───────────────────────────────────────────────── */
.room-header { padding-bottom: 12px; margin-bottom: 14px; border-bottom: 1px solid var(--border-soft); }
.room-header .back {
    display: inline-flex; align-items: center; gap: 6px;
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-dim); letter-spacing: 2px; text-decoration: none;
    margin-bottom: 8px; transition: .2s;
}
.room-header .back:hover { color: var(--room-accent); }
.room-header h2 {
    font-family: 'Oswald', sans-serif; font-size: 1.3em; color: #fff;
    letter-spacing: 2px; text-transform: uppercase; line-height: 1;
    text-shadow: 0 0 10px var(--room-accent);
}
.room-header .sub {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); font-size: .95em; margin-top: 4px;
}
.room-header .stats {
    display: flex; justify-content: space-between; margin-top: 10px;
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); letter-spacing: 1.5px;
}
.room-header .stats b { color: var(--room-accent); }

.aside-h {
    font-family: 'Oswald', sans-serif; font-size: .72em;
    letter-spacing: 3px; text-transform: uppercase;
    color: var(--room-accent); margin: 14px 0 8px;
    padding: 3px 6px; border-left: 2px solid var(--room-accent);
    background: linear-gradient(90deg, rgba(255,23,68,0.08), transparent);
    display: flex; justify-content: space-between; align-items: baseline;
}

/* ── CHAT HEAD ─────────────────────────────────────────────────── */
.chat-head {
    padding: 14px 18px; border-bottom: 1px solid var(--border-soft);
    background: linear-gradient(180deg, rgba(15,5,20,0.6), rgba(10,5,12,0.3));
    display: flex; justify-content: space-between; align-items: center;
    gap: 14px; flex-shrink: 0;
}
.chat-head .breadcrumb {
    font-family: 'JetBrains Mono', monospace; font-size: .74em;
    letter-spacing: 2px; color: var(--txt-mute);
}
.chat-head .breadcrumb a { color: var(--room-accent); text-decoration: none; }
.chat-head .title {
    font-family: 'Oswald', sans-serif; color: #fff; font-size: 1em;
    letter-spacing: 2px; text-transform: uppercase;
}
.chat-head .title .ic { color: var(--room-accent); margin-right: 6px; }

/* ── FEED ──────────────────────────────────────────────────────── */
.feed {
    flex: 1; overflow-y: auto; padding: 16px 20px;
    display: flex; flex-direction: column; gap: 12px;
    max-height: 480px;
}
.feed .empty {
    color: var(--txt-mute); text-align: center; padding: 40px 20px;
    font-family: 'Cormorant Garamond', serif; font-style: italic; font-size: 1em;
    line-height: 1.6;
}

.msg { display: flex; gap: 12px; animation: msgIn .25s ease; }
@keyframes msgIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.msg .av {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #2a0a14, #0a0408);
    border: 1px solid var(--border-mid);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; color: var(--room-accent);
    font-size: .82em; flex-shrink: 0;
}
.msg .body { flex: 1; min-width: 0; }
.msg .who {
    display: flex; align-items: baseline; gap: 8px; margin-bottom: 2px;
    flex-wrap: wrap;
}
.msg .who .nm { font-family: 'Oswald', sans-serif; color: #fff; font-size: .95em; letter-spacing: .5px; }
.msg .who .role {
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    padding: 1px 6px; border: 1px solid var(--border-soft);
    color: var(--room-accent); letter-spacing: 1.5px;
    text-transform: uppercase; background: rgba(255,23,68,0.06);
}
.msg .who .when { font-family: 'JetBrains Mono', monospace; font-size: .66em; color: var(--txt-mute); margin-left: auto; letter-spacing: 1px; }
.msg .txt { font-family: 'Cormorant Garamond', serif; font-size: 1.08em; line-height: 1.5; color: #e4dde4; word-wrap: break-word; }

/* RICH-TEXT spans */
.msg .txt .nar { color: var(--txt-dim); font-style: italic; }
.msg .txt .emph {
    color: var(--room-accent); font-weight: 500;
    text-shadow: 0 0 6px var(--room-accent);
}
.msg .txt .dialog {
    font-family: 'Fraunces', serif; color: #fff;
    padding-left: 10px; border-left: 2px solid var(--room-accent);
    margin: 3px 0; display: inline-block;
}
.msg .txt .mention {
    color: var(--neon-ember); font-family: 'Oswald', sans-serif;
    font-size: .85em; letter-spacing: 1px;
    padding: 1px 5px; background: rgba(255,122,61,0.1);
    border: 1px solid rgba(255,122,61,0.3); border-radius: 1px;
}

/* Barman / MG / NPC */
.msg.barman .av { border-color: var(--neon-red); background: linear-gradient(135deg, var(--neon-red-deep), #2a0a14); color: #fff; box-shadow: 0 0 10px rgba(255,23,68,0.5); }
.msg.barman .who .nm { color: var(--neon-red-hot); text-shadow: 0 0 8px rgba(255,61,94,0.5); }
.msg.barman .who .role { color: #fff; background: var(--neon-red); border-color: var(--neon-red); }

.msg.mg .av { border-color: #c896ff; color: #c896ff; }
.msg.mg .who .nm { color: #c896ff; text-shadow: 0 0 8px rgba(200,150,255,0.4); }
.msg.mg .who .role { color: #c896ff; background: rgba(200,150,255,0.1); border-color: rgba(200,150,255,0.3); }

.msg.npc .av { border-color: var(--neon-ember); color: var(--neon-ember); background: linear-gradient(135deg,#2a1505,#0a0408); }
.msg.npc .who .nm { color: var(--neon-ember); }
.msg.npc .who .role { color: var(--neon-ember); border-color: rgba(255,122,61,0.4); background: rgba(255,122,61,0.1); }

/* ── SYSTEM MSG (napiwki, zamówienia, płatności) ──────────────── */
.msg.sys {
    display: block; text-align: center; padding: 4px 0;
}
.msg.sys .sys-line {
    display: inline-block; padding: 6px 14px;
    background: rgba(255,215,0,0.06);
    border: 1px solid rgba(255,215,0,0.2);
    border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .92em; color: #d8c890; letter-spacing: .3px;
}
.msg.sys .sys-line b { color: var(--neon-gold); font-weight: 600; }
.msg.sys .sys-time {
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    color: var(--txt-mute); margin-left: 8px; font-style: normal;
}

/* ── COMPOSER ──────────────────────────────────────────────────── */
.composer {
    flex-shrink: 0; border-top: 1px solid var(--border-soft);
    background: rgba(6,3,9,0.85); padding: 10px 18px 12px;
}
.quick-cmds { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.qc {
    padding: 4px 9px; background: rgba(0,0,0,0.5);
    border: 1px solid var(--border-soft);
    color: var(--txt-dim); font-family: 'JetBrains Mono', monospace;
    font-size: .7em; cursor: pointer; letter-spacing: 1px;
    transition: .15s; border-radius: 2px;
}
.qc:hover { color: #fff; border-color: var(--room-accent); background: rgba(255,23,68,0.08); }
.qc.prim { color: var(--neon-ember); border-color: rgba(255,122,61,0.3); }

.composer-main { display: flex; gap: 10px; align-items: flex-end; }
.chat-input {
    flex: 1; min-height: 48px; max-height: 140px;
    background: rgba(0,0,0,0.55); border: 1px solid var(--border-soft);
    color: #fff; padding: 10px 12px;
    font-family: 'Cormorant Garamond', serif; font-size: 1.05em;
    line-height: 1.4; resize: none; outline: none; transition: .2s;
    border-radius: 2px;
}
.chat-input:focus { border-color: var(--room-accent); box-shadow: inset 0 0 12px rgba(255,23,68,0.08); }
.chat-input::placeholder { color: var(--txt-mute); font-style: italic; }

.btn-send {
    padding: 10px 20px;
    background: linear-gradient(90deg, var(--neon-red-deep), var(--room-accent));
    color: #fff; border: 1px solid var(--room-accent);
    font-family: 'Oswald', sans-serif; font-size: .85em;
    letter-spacing: 2.5px; text-transform: uppercase; cursor: pointer;
    box-shadow: 0 0 14px rgba(255,23,68,0.3); transition: .2s;
    border-radius: 2px; white-space: nowrap;
}
.btn-send:hover { box-shadow: 0 0 24px rgba(255,23,68,0.6); }

/* ── GOŚCIE ──────────────────────────────────────────────────── */
.guest-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px; background: rgba(0,0,0,0.35);
    border: 1px solid var(--border-soft);
    margin-bottom: 5px; transition: .15s; position: relative;
    border-radius: 2px; text-decoration: none;
}
.guest-row:hover { border-color: var(--room-accent); background: rgba(255,23,68,0.05); }
.guest-row .av {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #2a0a14, #0a0408);
    border: 1px solid var(--border-mid);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Oswald', sans-serif; color: var(--room-accent);
    font-size: .78em; flex-shrink: 0;
}
.guest-row .who { flex: 1; min-width: 0; }
.guest-row .who .nm {
    font-family: 'Oswald', sans-serif; color: #fff;
    font-size: .86em; letter-spacing: .5px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.guest-row .who .mood {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .78em; color: var(--txt-dim); display: block;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.guest-row.bar { border-color: var(--border-mid); }
.guest-row.bar::after {
    content: 'BARMAN'; position: absolute; top: -7px; right: 6px;
    background: var(--neon-red); color: #fff;
    font-family: 'Oswald', sans-serif; font-size: .55em;
    padding: 1px 6px; letter-spacing: 1.5px;
}
.guest-row.mg::after {
    content: 'MG'; position: absolute; top: -7px; right: 6px;
    background: #c896ff; color: #000;
    font-family: 'Oswald', sans-serif; font-size: .55em;
    padding: 1px 6px; letter-spacing: 1.5px;
}
.guest-row.me { border-color: var(--neon-cyan); background: rgba(74,214,255,0.04); }
.guest-row.me::after {
    content: 'TY'; position: absolute; top: -7px; right: 6px;
    background: var(--neon-cyan); color: #000;
    font-family: 'Oswald', sans-serif; font-size: .55em;
    padding: 1px 8px; letter-spacing: 1.5px;
}

/* Linki sal */
.sala-link {
    display: flex; justify-content: space-between; align-items: center;
    padding: 4px 8px; font-family: 'JetBrains Mono', monospace;
    font-size: .76em; color: var(--txt-dim); text-decoration: none;
    letter-spacing: .5px; border-left: 2px solid transparent;
    transition: .15s; margin-bottom: 2px;
}
.sala-link:hover {
    color: #fff; border-left-color: var(--room-accent);
    background: rgba(255,23,68,0.05); padding-left: 12px;
}
.sala-link .cnt { font-size: .9em; color: var(--room-accent); text-shadow: 0 0 4px var(--room-accent); }

/* ── KOMUNIKATY ──────────────────────────────────────────────── */
.msg-ok, .msg-blad {
    padding: 10px 14px; border-radius: 2px; margin-bottom: 14px;
    font-family: 'Oswald', sans-serif; letter-spacing: 1.5px;
    text-align: center; font-size: .9em;
}
.msg-ok { background: rgba(90,255,154,0.08); border: 1px solid rgba(90,255,154,0.4); color: var(--neon-green); }
.msg-blad { background: rgba(255,23,68,0.1); border: 1px solid var(--border-mid); color: var(--neon-red-hot); }

/* ── TOPBAR + OGŁOSZENIE + PANEL BARMANA ─────────────────────── */
.klub-topbar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 14px; margin-bottom: 14px; gap: 14px; flex-wrap: wrap;
    background: rgba(0,0,0,0.4); border: 1px solid var(--border-soft);
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace; font-size: .78em;
    letter-spacing: 1.5px; color: var(--txt-dim);
}
.klub-topbar .status-on { color: var(--neon-green); }
.klub-topbar .status-off { color: var(--neon-red-hot); }
.klub-topbar .nav-rooms { display: flex; gap: 8px; flex-wrap: wrap; }
.klub-topbar .nav-rooms a {
    color: var(--txt-dim); text-decoration: none;
    padding: 3px 8px; border: 1px solid var(--border-soft);
    border-radius: 2px; transition: .15s; font-size: .85em;
}
.klub-topbar .nav-rooms a:hover { color: var(--neon-red-hot); border-color: var(--neon-red); background: rgba(255,23,68,0.06); }
.klub-topbar .nav-rooms a.aktywna { color: #fff; border-color: var(--neon-red); background: rgba(255,23,68,0.12); text-shadow: 0 0 6px var(--neon-red); }

.klub-ogloszenie {
    background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(0,0,0,0.3));
    border: 1px solid rgba(255,215,0,0.4); border-radius: 2px;
    padding: 14px 18px; margin-bottom: 16px; position: relative;
}
.klub-ogloszenie::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: var(--neon-gold); box-shadow: 0 0 10px var(--neon-gold);
}
.klub-ogloszenie .ogl-label { color: var(--neon-gold); font-family: 'Oswald', sans-serif; font-size: .8em; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
.klub-ogloszenie .ogl-tresc { color: #eee; font-size: 1em; line-height: 1.5; font-family: 'Cormorant Garamond', serif; }
.klub-ogloszenie .ogl-autor { color: var(--txt-mute); font-size: .72em; margin-top: 6px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1px; }

.panel-barmana {
    background: rgba(0,0,0,0.5); border: 1px solid rgba(255,215,0,0.35);
    border-radius: 2px; margin-bottom: 20px; position: relative;
    padding: 22px 18px 18px;
}
.panel-barmana::before {
    content: '🍸 PANEL BARMANA'; position: absolute; top: -11px; left: 18px;
    background: #0a0a12; color: var(--neon-gold); padding: 2px 12px;
    font-family: 'Oswald', sans-serif; font-size: .78em;
    letter-spacing: 2px; border: 1px solid rgba(255,215,0,0.5);
}
.pb-sekcja { margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px dashed rgba(255,255,255,0.06); }
.pb-sekcja:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.pb-tytul { color: #ccc; font-family: 'Oswald', sans-serif; font-size: .82em; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
.pb-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.pb-form input, .pb-form textarea {
    background: rgba(0,0,0,0.6); border: 1px solid var(--border-soft);
    color: #ddd; padding: 8px 10px; font-family: 'Open Sans', sans-serif;
    font-size: .85em; border-radius: 2px;
}
.pb-form input[type=number] { width: 70px; }
.pb-form textarea { width: 100%; min-height: 45px; resize: vertical; margin-bottom: 8px; }
.pb-form .flex1 { flex: 1; min-width: 150px; }
.btn-pb {
    background: rgba(255,215,0,0.15); color: var(--neon-gold);
    border: 1px solid rgba(255,215,0,0.4); padding: 8px 14px;
    font-family: 'Oswald', sans-serif; font-size: .82em; cursor: pointer;
    text-transform: uppercase; letter-spacing: 1.2px; border-radius: 2px; transition: .2s;
}
.btn-pb:hover { background: var(--neon-gold); color: #000; }
.btn-pb.danger { background: rgba(255,23,68,0.1); color: var(--neon-red-hot); border-color: var(--border-mid); }
.btn-pb.danger:hover { background: var(--neon-red); color: #fff; }

/* ── NEW MESSAGE TOAST ─────────────────────────────────────── */
.klub-new-toast {
    position: absolute; bottom: 110px; left: 50%; transform: translateX(-50%);
    padding: 8px 16px; background: var(--neon-red);
    color: #fff; border: 1px solid var(--neon-red-hot);
    font-family: 'Oswald', sans-serif; font-size: .82em;
    letter-spacing: 1.5px; text-transform: uppercase;
    box-shadow: 0 4px 14px rgba(0,0,0,0.6), 0 0 20px rgba(255,23,68,0.5);
    cursor: pointer; transition: .2s; opacity: 0; pointer-events: none;
    border-radius: 2px; z-index: 10;
}
.klub-new-toast.on { opacity: 1; pointer-events: auto; }
.klub-new-toast:hover { background: var(--neon-red-hot); }

/* ── FLASH MESSAGE GLOBAL ──────────────────────────────────── */
#klub-flash {
    position: fixed; top: 24px; right: 24px; z-index: 1000;
    padding: 12px 18px; border-radius: 2px;
    font-family: 'Oswald', sans-serif; font-size: .9em;
    letter-spacing: 1.5px; max-width: 320px;
    transform: translateX(120%); transition: transform .3s ease;
    box-shadow: 0 8px 24px rgba(0,0,0,0.6);
}
#klub-flash.on { transform: translateX(0); }
#klub-flash.ok { background: rgba(90,255,154,0.95); color: #052010; border: 1px solid var(--neon-green); }
#klub-flash.blad { background: rgba(255,23,68,0.95); color: #fff; border: 1px solid var(--neon-red); }

/* ── RACHUNEK BOX ──────────────────────────────────────────── */
.rachunek-box {
    background: rgba(255,215,0,0.05);
    border: 1px solid rgba(255,215,0,0.25);
    padding: 10px; border-radius: 2px;
    font-family: 'JetBrains Mono', monospace;
}
.rachunek-pusty {
    color: var(--txt-mute); font-size: .82em;
    font-style: italic; padding: 12px 4px; text-align: center;
    font-family: 'Cormorant Garamond', serif;
}
.rachunek-pusty small {
    display: block; margin-top: 6px; font-size: .9em;
    font-family: 'JetBrains Mono', monospace; font-style: normal;
    color: var(--txt-dim); letter-spacing: 0;
}
.rachunek-pusty b { color: var(--neon-ember); }
.rachunek-naglowek {
    font-family: 'Oswald', sans-serif; font-size: .8em;
    color: var(--neon-gold); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 8px;
    padding-bottom: 6px; border-bottom: 1px solid rgba(255,215,0,0.2);
}
.r-poz {
    display: grid; grid-template-columns: 1fr auto auto;
    gap: 8px; padding: 4px 0; font-size: .78em;
    color: var(--txt-dim); align-items: center;
    border-bottom: 1px dashed rgba(255,255,255,0.04);
}
.r-poz .r-nm {
    color: #fff; font-family: 'Fraunces', serif;
    font-size: .96em; line-height: 1.2;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.r-poz .r-cz { color: var(--txt-mute); font-size: .82em; }
.r-poz .r-pr { color: var(--neon-ember); font-weight: 500; }
.rachunek-total {
    margin-top: 8px; padding-top: 8px;
    border-top: 1px dashed rgba(255,215,0,0.3);
    color: var(--neon-gold); font-size: .92em;
    display: flex; justify-content: space-between; align-items: baseline;
    letter-spacing: 1px;
}
.rachunek-total b { color: var(--neon-gold); text-shadow: 0 0 6px rgba(255,215,0,0.4); font-size: 1.15em; }
.btn-zaplac {
    width: 100%; margin-top: 10px; padding: 8px;
    background: rgba(255,215,0,0.1); color: var(--neon-gold);
    border: 1px solid rgba(255,215,0,0.4); border-radius: 2px;
    font-family: 'Oswald', sans-serif; font-size: .82em;
    letter-spacing: 2px; text-transform: uppercase; cursor: pointer;
    transition: .2s;
}
.btn-zaplac:hover { background: var(--neon-gold); color: #000; box-shadow: 0 0 14px rgba(255,215,0,0.5); }

/* ════════════════════════════════════════════════════════════════
   FAZA 3 — SZEPTY, FLIRTY, WYPROSZENIA, NPC_SPEAK
   ════════════════════════════════════════════════════════════════ */

/* ── NPC_SPEAK (barman/MG mówi za NPC) ────────────────────────── */
.msg.speak .av {
    border-color: var(--neon-ember);
    background: linear-gradient(135deg, #2a1505, #0a0408);
    color: var(--neon-ember);
    box-shadow: 0 0 8px rgba(255,122,61,0.4);
}
.msg.speak .who .nm {
    color: var(--neon-ember);
    font-family: 'Fraunces', serif;
    font-style: italic;
    text-shadow: 0 0 6px rgba(255,122,61,0.3);
}
.msg.speak .who .role {
    background: rgba(255,122,61,0.15);
    border-color: var(--neon-ember);
    color: var(--neon-ember);
}
.summon-by {
    font-family: 'JetBrains Mono', monospace;
    font-size: .58em;
    color: var(--txt-mute);
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 1px 6px;
    border: 1px dashed var(--border-soft);
    border-radius: 1px;
    margin-left: 4px;
}

/* ── SZEPT TOAST (MG → gracz) ─────────────────────────────────── */
.klub-szept-toast {
    position: fixed; top: 80px; right: 20px; z-index: 1500;
    width: 340px; max-width: calc(100vw - 40px);
    background: linear-gradient(135deg, rgba(40,15,60,0.97), rgba(15,5,25,0.97));
    border: 1px solid #c896ff;
    border-radius: 2px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.7), 0 0 30px rgba(200,150,255,0.3);
    transform: translateX(120%);
    transition: transform .4s ease;
    overflow: hidden;
}
.klub-szept-toast.on { transform: translateX(0); }
.klub-szept-toast::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: #c896ff;
    box-shadow: 0 0 8px #c896ff;
}
.klub-szept-toast .szept-head {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid rgba(200,150,255,0.2);
    background: rgba(0,0,0,0.3);
}
.klub-szept-toast .szept-ic {
    color: #c896ff; font-size: 1.2em;
    text-shadow: 0 0 8px #c896ff;
}
.klub-szept-toast .szept-od {
    flex: 1;
    font-family: 'Oswald', sans-serif; font-size: .82em;
    color: #c896ff; letter-spacing: 1.5px;
    text-transform: uppercase;
}
.klub-szept-toast .szept-od b { color: #fff; }
.klub-szept-toast .szept-czas {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute);
}
.klub-szept-toast .szept-close {
    background: transparent; border: 1px solid var(--border-soft);
    color: var(--txt-dim); cursor: pointer;
    width: 22px; height: 22px; padding: 0;
    border-radius: 50%; line-height: 1;
    font-size: .7em; transition: .15s;
}
.klub-szept-toast .szept-close:hover {
    background: var(--neon-red); color: #fff; border-color: var(--neon-red);
}
.klub-szept-toast .szept-tresc {
    padding: 14px 18px;
    font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: 1.05em;
    color: #f0e8f5; line-height: 1.5;
    word-break: break-word;
}
.klub-szept-toast .szept-tresc .nar { color: #b09cc8; }
.klub-szept-toast .szept-tresc .emph { color: #fff; text-shadow: 0 0 4px #c896ff; }
.klub-szept-toast .szept-tresc .dialog {
    border-left-color: #c896ff;
}

/* ── FLIRT TOAST (sygnał) ─────────────────────────────────────── */
.klub-flirt-toast {
    position: fixed; bottom: 90px; right: 20px; z-index: 1400;
    padding: 12px 18px;
    background: linear-gradient(135deg, rgba(255,61,94,0.2), rgba(40,5,15,0.95));
    border: 1px solid #ff3d5e;
    border-radius: 2px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.6), 0 0 20px rgba(255,61,94,0.4);
    display: flex; align-items: center; gap: 10px;
    transform: translateX(120%) scale(0.9);
    transition: transform .5s cubic-bezier(0.25, 0.46, 0.45, 1.94);
    max-width: 320px;
    cursor: pointer;
}
.klub-flirt-toast.on { transform: translateX(0) scale(1); }
.klub-flirt-toast .flirt-ic {
    font-size: 1.6em; color: var(--neon-red-hot);
    text-shadow: 0 0 12px var(--neon-red);
    animation: flirtPulse 1.5s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes flirtPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}
.klub-flirt-toast .flirt-text {
    flex: 1;
    font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: 1em;
    color: #fff; line-height: 1.3;
}
.klub-flirt-toast .flirt-text b {
    color: var(--neon-red-hot); font-style: normal;
    font-family: 'Oswald', sans-serif; font-size: .9em;
    letter-spacing: 1px;
}
.klub-flirt-toast .flirt-czas {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute);
    flex-shrink: 0;
}

/* ── WYPROSZENIE OVERLAY (full screen) ────────────────────────── */
.klub-wyproszenie {
    position: fixed; inset: 0; z-index: 2000;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .4s ease;
}
.klub-wyproszenie.on { opacity: 1; }
.klub-wyproszenie .wyp-box {
    max-width: 480px; width: 90%;
    padding: 36px 40px; text-align: center;
    background: linear-gradient(135deg, rgba(40,5,10,0.95), rgba(10,5,12,0.98));
    border: 1px solid var(--neon-red);
    box-shadow: 0 20px 60px rgba(0,0,0,0.8), 0 0 60px rgba(255,23,68,0.4);
    border-radius: 2px;
}
.klub-wyproszenie .wyp-ic {
    font-size: 4em; line-height: 1; margin-bottom: 14px;
    filter: drop-shadow(0 0 20px rgba(255,23,68,0.6));
}
.klub-wyproszenie .wyp-tytul {
    font-family: 'Oswald', sans-serif; font-size: 1.6em;
    color: var(--neon-red-hot); letter-spacing: 4px;
    text-transform: uppercase;
    text-shadow: 0 0 14px var(--neon-red);
    margin-bottom: 14px;
}
.klub-wyproszenie .wyp-tresc {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.15em; color: #eee; line-height: 1.5;
    margin-bottom: 14px;
}
.klub-wyproszenie .wyp-tresc b {
    color: var(--neon-red-hot); font-style: normal;
    font-family: 'Oswald', sans-serif; font-size: .9em;
    letter-spacing: 1.5px;
}
.klub-wyproszenie .wyp-powod {
    padding: 10px 14px; margin-bottom: 14px;
    background: rgba(0,0,0,0.4);
    border-left: 2px solid var(--neon-ember);
    font-family: 'Cormorant Garamond', serif;
    font-style: italic; font-size: .95em;
    color: var(--txt-dim); text-align: left;
}
.klub-wyproszenie .wyp-do-kiedy {
    font-family: 'JetBrains Mono', monospace;
    font-size: .85em; color: var(--txt-dim);
    letter-spacing: 1.5px; margin-bottom: 22px;
    text-transform: uppercase;
}
.klub-wyproszenie .wyp-do-kiedy b {
    color: var(--neon-gold); font-size: 1.3em;
    text-shadow: 0 0 8px rgba(255,215,0,0.4);
}
.klub-wyproszenie .wyp-btn {
    display: inline-block; padding: 12px 28px;
    background: linear-gradient(90deg, var(--neon-red-deep), var(--neon-red));
    color: #fff; text-decoration: none;
    border: 1px solid var(--neon-red);
    font-family: 'Oswald', sans-serif; font-size: .9em;
    letter-spacing: 3px; text-transform: uppercase;
    box-shadow: 0 0 18px rgba(255,23,68,0.4);
    border-radius: 2px; transition: .2s;
}
.klub-wyproszenie .wyp-btn:hover {
    box-shadow: 0 0 28px rgba(255,23,68,0.7);
    transform: translateY(-1px);
}

/* ── SEKRETNE SYGNAŁY (widget w sali głównej) ─────────────────── */
.klub-sygnaly {
    background: linear-gradient(135deg, rgba(255,61,94,0.06), rgba(0,0,0,0.4));
    border: 1px solid rgba(255,61,94,0.25);
    border-radius: 2px; padding: 10px;
    margin-bottom: 14px;
}
.klub-sygnaly.pulse { animation: sygPulse 1.2s ease 2; }
@keyframes sygPulse {
    0%, 100% { box-shadow: 0 0 0 rgba(255,61,94,0); }
    50% { box-shadow: 0 0 20px rgba(255,61,94,0.5); }
}
.sygnaly-pusto {
    color: var(--txt-mute); font-size: .8em;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    padding: 8px 4px; text-align: center;
}
.sygnal-row {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 0;
    border-bottom: 1px dashed rgba(255,255,255,0.05);
    font-size: .85em;
}
.sygnal-row:last-child { border-bottom: none; }
.sygnal-row .ic {
    color: var(--neon-red-hot); font-size: .9em;
    text-shadow: 0 0 6px var(--neon-red);
}
.sygnal-row .nm {
    flex: 1; min-width: 0;
    font-family: 'Fraunces', serif;
    color: #fff; font-size: 1em;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sygnal-row .nm i { color: var(--neon-red-hot); font-style: italic; }
.sygnal-row .czas {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute);
}
</style>

<?php
$licznik_sal = klub_licznik_sal($polaczenie);
$lacznie_w_klubie = array_sum($licznik_sal);
?>

<div class="klub-wrap">

    <div class="klub-topbar">
        <div>
            <?php if ($klub['otwarty']): ?>
                <span class="status-on">● KLUB OTWARTY</span>
            <?php else: ?>
                <span class="status-off">● KLUB ZAMKNIĘTY</span>
                <?php if ($klub['czas_ponownego_otwarcia']): ?>
                    <span style="color:var(--txt-mute); margin-left:8px;">do <?php echo date('d.m H:i', strtotime($klub['czas_ponownego_otwarcia'])); ?></span>
                <?php endif; ?>
            <?php endif; ?>
            <span style="color:var(--txt-mute); margin-left:14px;">· <?php echo $lacznie_w_klubie; ?> osób w środku</span>
        </div>

        <div class="nav-rooms">
            <a href="game.php?page=czat&sala=lobby" class="<?php echo $sala=='lobby'?'aktywna':''; ?>">◉ Lobby</a>
            <a href="game.php?page=czat&sala=sala-glowna" class="<?php echo $sala=='sala-glowna'?'aktywna':''; ?>">🍸 Sala Główna</a>
            <a href="game.php?page=czat&sala=sala-balowa" class="<?php echo $sala=='sala-balowa'?'aktywna':''; ?>">💃 Sala Balowa</a>
            <a href="game.php?page=czat&sala=garderoba" class="<?php echo $sala=='garderoba'?'aktywna':''; ?>">👗 Garderoba</a>
            <a href="game.php?page=czat&sala=basen" class="<?php echo $sala=='basen'?'aktywna':''; ?>">🏊 Basen</a>
            <a href="game.php?page=czat&sala=silownia" class="<?php echo $sala=='silownia'?'aktywna':''; ?>">💪 Siłownia</a>
            <a href="game.php?page=czat&sala=sauna" class="<?php echo $sala=='sauna'?'aktywna':''; ?>">♨ Sauna</a>
            <a href="game.php?page=czat&sala=masaze" class="<?php echo $sala=='masaze'?'aktywna':''; ?>">💆 Masaże</a>
            <a href="game.php?page=czat&sala=bdsm" class="<?php echo $sala=='bdsm'?'aktywna':''; ?>">⛓ BDSM</a>
            <a href="game.php?page=czat&sala=vip" class="<?php echo $sala=='vip'?'aktywna':''; ?>">🥂 VIP</a>
            <a href="game.php?page=czat&sala=taras" class="<?php echo $sala=='taras'?'aktywna':''; ?>">🌃 Taras</a>
            <a href="game.php?page=czat&sala=tyly" class="<?php echo $sala=='tyly'?'aktywna':''; ?>">🚬 Tyły</a>
        </div>
    </div>

    <?php echo $komunikat; ?>

    <?php if (!empty($klub['ogloszenie']) && $sala !== 'lobby'):
        $autor_ogl = null;
        if ($klub['ogloszenie_autor_id']) {
            $autor_ogl = $polaczenie->query("SELECT login FROM gracze WHERE id={$klub['ogloszenie_autor_id']}")->fetch_assoc();
        }
    ?>
    <div class="klub-ogloszenie">
        <div class="ogl-label">📢 Ogłoszenie Barmana</div>
        <div class="ogl-tresc"><?php echo nl2br(htmlspecialchars($klub['ogloszenie'])); ?></div>
        <?php if ($autor_ogl): ?>
        <div class="ogl-autor">— <?php echo htmlspecialchars($autor_ogl['login']); ?>, <?php echo date('d.m H:i', strtotime($klub['ogloszenie_data'])); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    if (!$klub['otwarty'] && !$ma_uprawnienia && $sala !== 'lobby') {
        echo "<div class='klub-wstep-blokada' style='margin:0 0 20px'>
            🔒 KLUB ZAMKNIĘTY<br>
            <small style='font-family:JetBrains Mono,monospace;font-size:.7em;color:var(--txt-dim);text-transform:uppercase;letter-spacing:1px'>
                Ponowne otwarcie: ".($klub['czas_ponownego_otwarcia']?date('d.m.Y H:i',strtotime($klub['czas_ponownego_otwarcia'])):'brak daty')."<br>
                ".($klub['powod_zamkniecia']?htmlspecialchars($klub['powod_zamkniecia']):'')."
            </small>
        </div>";
    } else {
        $sub_file = $dane_sali['aktywna']
            ? "pages/klub/{$sala}.php"
            : "pages/klub/_placeholder.php";
        if (file_exists($sub_file)) {
            include $sub_file;
        } else {
            echo "<div class='msg-blad'>⚠ Plik sali nie istnieje: $sub_file</div>";
        }
    }
    ?>

    <?php if ($ma_uprawnienia): ?>
    <div class="panel-barmana">
        <div class="pb-sekcja">
            <div class="pb-tytul">🚪 Status klubu</div>
            <?php if ($klub['otwarty']): ?>
            <form method="POST" class="pb-form">
                <input type="number" name="godziny_zamkniecia" min="1" max="24" value="2" required>
                <span style="color:#666;font-size:.82em">godz.</span>
                <input type="text" name="powod" placeholder="Powód zamknięcia..." class="flex1">
                <button type="submit" name="zamknij_klub" class="btn-pb danger">🚪 Zamknij klub</button>
            </form>
            <?php else: ?>
            <form method="POST" class="pb-form">
                <button type="submit" name="otworz_klub" class="btn-pb">🔓 Otwórz klub</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="pb-sekcja">
            <div class="pb-tytul">📢 Ogłoszenie klubu</div>
            <form method="POST" class="pb-form">
                <textarea name="tresc_ogloszenia" placeholder="np. 'Dziś happy hour 22:00-24:00 — drinki 50% taniej!'"></textarea>
                <button type="submit" name="dodaj_ogloszenie" class="btn-pb">📢 Dodaj ogłoszenie</button>
                <?php if ($klub['ogloszenie']): ?>
                <button type="submit" name="usun_ogloszenie" class="btn-pb danger">🗑️ Usuń aktualne</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="pb-sekcja">
            <div class="pb-tytul">🚫 Wyrzuć / zbanuj gracza</div>
            <form method="POST" class="pb-form">
                <input type="number" name="cel_id" placeholder="ID gracza" required>
                <input type="number" name="godziny_banu" min="1" max="168" value="2" required>
                <span style="color:#666;font-size:.82em">godz.</span>
                <input type="text" name="powod_banu" placeholder="Powód..." class="flex1">
                <button type="submit" name="banuj_gracza" class="btn-pb danger">🚫 Wyrzuć</button>
            </form>
        </div>

        <div class="pb-sekcja">
            <div class="pb-tytul">✦ Wydarzenia</div>
            <?php
            // Lista swoich aktywnych wydarzeń
            $moje_eventy = $polaczenie->query("
                SELECT id, nazwa, data_startu, kolor_plakatu,
                       (SELECT COUNT(*) FROM klub_rezerwacje WHERE wydarzenie_id=w.id) AS lr
                FROM klub_wydarzenia w
                WHERE autor_id=$id_gracza AND aktywne=1 AND anulowane=0
                  AND data_startu >= NOW() - INTERVAL 4 HOUR
                ORDER BY data_startu ASC LIMIT 5
            ");
            ?>
            <div style="margin-bottom:10px">
                <a href="game.php?page=czat&sala=lobby&widok=wydarzenia" class="btn-pb" style="text-decoration:none;display:inline-block">
                    ✦ Otwórz panel wydarzeń
                </a>
                <span style="margin-left:14px;color:var(--txt-mute);font-size:.85em">tworzenie · zarządzanie · lista wszystkich</span>
            </div>
            <?php if ($moje_eventy && $moje_eventy->num_rows > 0): ?>
            <div style="background:rgba(0,0,0,0.4);padding:10px;border-radius:2px;border-left:2px solid var(--neon-gold)">
                <div style="font-family:'Oswald',sans-serif;font-size:.78em;letter-spacing:2px;color:var(--neon-gold);margin-bottom:6px;text-transform:uppercase">Twoje aktywne wydarzenia</div>
                <?php while ($me = $moje_eventy->fetch_assoc()):
                    $ts = strtotime($me['data_startu']);
                ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed rgba(255,255,255,0.05);font-family:'JetBrains Mono',monospace;font-size:.78em">
                    <span style="color:#fff"><?php echo htmlspecialchars($me['nazwa']); ?></span>
                    <span style="color:var(--txt-dim)">
                        <?php echo date('d.m H:i', $ts); ?>
                        · <span style="color:var(--neon-red-hot)"><?php echo (int)$me['lr']; ?></span> rez.
                    </span>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="pb-sekcja">
            <div class="pb-tytul">📋 Komendy społeczne</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:.78em;color:var(--txt-dim);line-height:1.7">
                <?php if ($jest_mg): ?>
                <code style="color:var(--neon-cyan)">/szept @login [tekst]</code> — prywatna wiadomość MG → gracz<br>
                <?php endif; ?>
                <code style="color:var(--neon-ember)">/npc "Imię" "tekst"</code> — mów za nazwanego NPC<br>
                <code style="color:var(--neon-red-hot)">/wypraszam @login [min] [powód]</code> — wyrzuć z aktualnej sali (10–60 min)<br>
                <span style="color:var(--txt-mute);font-style:italic">Te komendy działają w czacie sali, w której jesteś.</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ══ JS RICH CHAT (auto-refresh, parser, AJAX) ══════════════ -->
<script src="js/klub.js?v=2"></script>