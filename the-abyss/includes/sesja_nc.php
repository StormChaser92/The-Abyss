<?php
/* the-abyss/includes/sesja_nc.php
   Zakładka NC (Non Clima) w pokoju sesji — rozmowy poza fabułą we wszystkich rodzajach Opowieści.
   Piszą uczestnicy i obserwatorzy (w sesjach prywatnych tylko uczestnicy). Nic nie jest obowiązkowe.
   Prowadzący: wpis-upomnienie, przypinanie, usuwanie, wyciszenie (1 h / 24 h / do końca sesji).
   MG i Adminka: dodatkowo ostrzeżenie z poziomu wpisu (config/moderacja.php).
   W bazie posty NC to sesje_posty.typ_postu = 'OffTop'. */
require_once __DIR__ . '/../config/moderacja.php';
require_once __DIR__ . '/formatuj.php';

function nc_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function nc_wzmianki(mysqli $db, array $s, int $gid, string $t, string $gdzie = 'NC', string $zak = 'offtop'): void {
    if (!preg_match_all('/@([a-zA-Z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+)/u', $t, $m)) return;
    $ja = (string)(db_wiersz($db, "SELECT login FROM gracze WHERE id = ?", [$gid])['login'] ?? '');
    foreach (array_unique($m[1]) as $nick) {
        if (mb_strtolower($nick) === mb_strtolower($ja)) continue;
        $w = db_wiersz($db, "SELECT id FROM gracze WHERE login = ?", [$nick]);
        if ($w) powiadom($db, (int)$w['id'], "Gracz <b style='color:var(--neon-green)'>" . bz_h($ja) . "</b> wspomniał o Tobie w $gdzie (<i>" . bz_h($s['tytul']) . "</i>). <a href='game.php?page=pokoj_sesji&id=" . (int)$s['id'] . "&zakladka=$zak' style='color:var(--neon-cyan)'>[ Przejdź ]</a>");
    }
}

/** Obsługa formularzy NC. $prow = prowadzący tej sesji. Zwraca komunikat błędu albo przekierowuje. */
function nc_obsluz(mysqli $db, array $s, int $gid, bool $prow, bool $moze): string {
    $a = (string)($_POST['nc_akcja'] ?? '');
    if ($a === '') return '';
    $sid  = (int)$s['id'];
    $wroc = function () use ($sid) { header("Location: game.php?page=pokoj_sesji&id=$sid&zakladka=offtop"); exit; };

    if ($a === 'pisz') {
        if (!$moze) return 'Nie możesz teraz pisać w NC.';
        $t = trim(mb_substr((string)($_POST['tresc'] ?? ''), 0, 5000));
        if ($t === '') return 'Pusta wiadomość.';
        db_zmien($db, "INSERT INTO sesje_posty (sesja_id, autor_id, typ_postu, tresc, nc_upomnienie) VALUES (?, ?, 'OffTop', ?, ?)", [$sid, $gid, $t, $prow && !empty($_POST['upomnienie']) ? 1 : 0]);
        nc_wzmianki($db, $s, $gid, $t);
        $wroc();
    }

    $pid  = (int)($_POST['post_id'] ?? 0);
    $post = $pid ? db_wiersz($db, "SELECT id, autor_id, tresc FROM sesje_posty WHERE id = ? AND sesja_id = ? AND typ_postu = 'OffTop'", [$pid, $sid]) : null;

    if ($a === 'ostrzez') {
        if (!$post) return 'Nie ma takiego wpisu.';
        $e = mod_kara($db, $gid, (int)$post['autor_id'], 'ostrzezenie', (string)($_POST['powod'] ?? ''), 0, $sid, $pid);
        if ($e !== '') return $e;
        $wroc();
    }
    if (!$prow) return 'Tylko prowadzący.';

    if ($a === 'przypnij' && $post) {
        db_zmien($db, "UPDATE sesje_posty SET nc_przypiety = 1 - nc_przypiety WHERE id = ?", [$pid]);
        $wroc();
    }
    if ($a === 'usun' && $post) {
        db_zmien($db, "DELETE FROM sesje_posty WHERE id = ?", [$pid]);
        mod_log($db, $gid, (int)$post['autor_id'], 'nc_usuniecie', "Sesja #$sid: " . mb_substr($post['tresc'], 0, 300));
        $wroc();
    }
    if ($a === 'wycisz' || $a === 'odcisz') {
        $cel = (int)($_POST['gracz_id'] ?? 0);
        $u   = db_wiersz($db, "SELECT rola FROM sesje_uczestnicy WHERE sesja_id = ? AND gracz_id = ?", [$sid, $cel]);
        if ($cel <= 0 || $cel === $gid || $cel === (int)$s['mg_id'] || ($u['rola'] ?? '') === 'Mistrz Gry') return 'Nie można wyciszyć tego gracza.';
        if ($a === 'odcisz') {
            db_zmien($db, "DELETE FROM nc_wyciszenia WHERE sesja_id = ? AND gracz_id = ?", [$sid, $cel]);
            mod_log($db, $gid, $cel, 'nc_odciszenie', "Sesja #$sid");
            $wroc();
        }
        $c = (string)($_POST['czas'] ?? '');
        if (!isset(NC_CZAS[$c])) return 'Wybierz czas wyciszenia.';
        $do = NC_CZAS[$c][1] ? date('Y-m-d H:i:s', time() + NC_CZAS[$c][1]) : null;
        db_zmien($db, "REPLACE INTO nc_wyciszenia (sesja_id, gracz_id, moderator_id, do_kiedy) VALUES (?, ?, ?, ?)", [$sid, $cel, $gid, $do]);
        mod_log($db, $gid, $cel, 'nc_wyciszenie', "Sesja #$sid, " . NC_CZAS[$c][0]);
        powiadom($db, $cel, "Prowadzący wyciszył Cię w NC sesji <i>" . bz_h($s['tytul']) . "</i> (" . NC_CZAS[$c][0] . ").");
        $wroc();
    }
    return 'Nieznana akcja.';
}

function nc_css(): void { ?>
<style>
.nc{display:flex;flex-direction:column;gap:12px}
.nc-lead{color:var(--txt-dim);font-size:.9em;line-height:1.5}
.nc-lead b{color:#fff;font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:1.5px;text-transform:uppercase}
.nc-blad{background:rgba(255,23,68,.1);border:1px solid var(--border-mid);color:var(--neon-red-hot);padding:10px 14px;font-family:'Oswald',sans-serif;letter-spacing:1px}
.nc-lbl{font-family:'Oswald',sans-serif;font-size:.75em;color:var(--txt-mute);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px}
.nc-przyp{background:rgba(74,214,255,.05);border:1px solid rgba(74,214,255,.3);padding:12px 16px}
.nc-box{background:rgba(10,6,12,.55);border:1px solid var(--border-soft);padding:14px 18px;max-height:540px;overflow-y:auto;backdrop-filter:blur(6px)}
.nc-wpis{padding:10px 0;border-bottom:1px dashed rgba(255,23,68,.1)}
.nc-wpis:last-child{border-bottom:none}
.nc-wpis.upo{background:rgba(255,215,0,.06);border:1px solid rgba(255,215,0,.4);padding:10px 12px;margin:6px 0}
.nc-gl{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.nc-nick{color:var(--neon-cyan);font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:.5px}
.nc-rola{font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--txt-mute);text-transform:uppercase;letter-spacing:1px;border:1px solid rgba(255,255,255,.1);padding:1px 6px}
.nc-rola.mg{color:var(--neon-gold);border-color:rgba(255,215,0,.35)}
.nc-data{color:var(--txt-mute);font-size:.75em;font-family:'JetBrains Mono',monospace}
.nc-tag{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1px;text-transform:uppercase;padding:1px 6px}
.nc-tag.upo{color:#000;background:var(--neon-gold)}
.nc-tag.pin{color:var(--neon-cyan);border:1px solid rgba(74,214,255,.4)}
.nc-t{color:var(--txt-main);margin-top:6px;font-family:'Open Sans',sans-serif;line-height:1.55;font-size:.92em;overflow-wrap:anywhere}
.nc-narz{margin-left:auto;position:relative}
.nc-narz summary{list-style:none;cursor:pointer;color:var(--txt-mute);font-family:'JetBrains Mono',monospace;padding:0 6px}
.nc-narz summary::-webkit-details-marker{display:none}
.nc-narz[open] summary{color:#fff}
.nc-menu{position:absolute;right:0;top:22px;z-index:20;min-width:260px;display:flex;flex-direction:column;gap:8px;background:rgba(8,6,12,.97);border:1px solid var(--border-mid);padding:12px;box-shadow:0 10px 30px rgba(0,0,0,.6)}
.nc-menu form{display:flex;gap:6px;flex-wrap:wrap;margin:0}
.nc-menu select,.nc-menu textarea{flex:1;min-width:0;background:rgba(0,0,0,.6);border:1px solid var(--border-soft);color:#fff;padding:6px 8px;font-family:'Open Sans',sans-serif;font-size:.85em}
.nc-menu textarea{width:100%;min-height:60px;flex-basis:100%}
.nc-menu .nc-sek{font-family:'Oswald',sans-serif;font-size:.7em;color:var(--txt-mute);text-transform:uppercase;letter-spacing:2px}
.nc-wyc{display:flex;flex-direction:column;gap:6px;background:rgba(0,0,0,.35);border:1px solid var(--border-soft);padding:10px 14px}
.nc-wyc form{display:flex;align-items:center;gap:10px;margin:0;font-size:.88em;color:var(--txt-dim)}
.nc-wyc b{color:#fff}
.nc-form{display:flex;flex-direction:column;gap:8px}
.nc-form textarea{min-height:70px;margin:0}
.nc-form-d{display:flex;align-items:center;gap:12px;justify-content:flex-end;flex-wrap:wrap}
.nc-form-d label{margin-right:auto;display:flex;align-items:center;gap:6px;color:var(--neon-gold);font-family:'Oswald',sans-serif;font-size:.8em;letter-spacing:1px;text-transform:uppercase;cursor:pointer}
.nc-form-d input{accent-color:var(--neon-gold)}
.nc-stop{padding:16px;text-align:center;color:var(--txt-mute);background:rgba(0,0,0,.3);border:1px dashed var(--border-soft);font-family:'JetBrains Mono',monospace;font-size:.85em}
.nc-stop a{color:var(--neon-cyan)}
.nc-pusto{text-align:center;color:var(--txt-mute);font-style:italic;font-family:'JetBrains Mono',monospace;font-size:.9em;padding:20px 0}
</style>
<?php }

function nc_wpis(mysqli $db, array $p, array $c, bool $kopia = false): void {
    $pid = (int)$p['id']; $aut = (int)$p['autor_id'];
    $mg  = $aut === $c['wlasc'] || $p['rola'] === 'Mistrz Gry';
    $rola = $mg ? 'MG' : (($p['rola'] ?? '') === 'Gracz' ? 'Gracz' : 'Obserwator');
    $t = rp_format((string)$p['tresc']);
    $edytuj = !$kopia && !$c['zak'] && (($aut === $c['gid'] && $c['zaakc']) || $c['prow']);
    echo "<div class='nc-wpis" . ($p['nc_upomnienie'] ? ' upo' : '') . "'><div class='nc-gl'>";
    echo "<b class='nc-nick'>" . nc_h($p['login']) . "</b><span class='nc-rola" . ($mg ? ' mg' : '') . "'>$rola</span><span class='nc-data'>" . nc_h($p['data_dodania']) . "</span>";
    if ($p['nc_upomnienie']) echo "<span class='nc-tag upo'>Upomnienie</span>";
    if ($p['nc_przypiety'] && !$kopia) echo "<span class='nc-tag pin'>📌</span>";
    if ($edytuj) echo "<a href='javascript:void(0)' class='edytuj-link' style='font-size:.75em' onclick='pokazEdycje($pid)'>✏</a>";
    if (!$kopia && ($c['prow'] || $c['nadzor'])) {
        $inny  = $aut !== $c['gid'];
        $wycisz = $c['prow'] && $inny && !$mg;
        $ostrz  = $c['nadzor'] && $inny && mod_moze_cel($db, $c['gid'], $aut);
        echo "<details class='nc-narz'><summary title='Narzędzia prowadzącego'>⋯</summary><div class='nc-menu'>";
        if ($c['prow']) {
            echo "<form method='POST'><input type='hidden' name='nc_akcja' value='przypnij'><input type='hidden' name='post_id' value='$pid'><button type='submit' class='btn-mini'>" . ($p['nc_przypiety'] ? 'Odepnij' : '📌 Przypnij') . "</button></form>";
            echo "<form method='POST' onsubmit=\"return confirm('Usunąć ten wpis z NC?')\"><input type='hidden' name='nc_akcja' value='usun'><input type='hidden' name='post_id' value='$pid'><button type='submit' class='btn-mini'>✕ Usuń wpis</button></form>";
        }
        if ($wycisz) {
            echo "<div class='nc-sek'>Wycisz w NC</div><form method='POST'><input type='hidden' name='nc_akcja' value='wycisz'><input type='hidden' name='gracz_id' value='$aut'><select name='czas'>";
            foreach (NC_CZAS as $k => $v) echo "<option value='$k'>{$v[0]}</option>";
            echo "</select><button type='submit' class='btn-mini'>Wycisz</button></form>";
        }
        if ($ostrz) echo "<div class='nc-sek'>Ostrzeżenie (moderacja)</div><form method='POST'><input type='hidden' name='nc_akcja' value='ostrzez'><input type='hidden' name='post_id' value='$pid'><textarea name='powod' required maxlength='1000' placeholder='Powód (wymagany)'></textarea><button type='submit' class='btn-mini' style='color:var(--neon-red-hot);border-color:var(--neon-red)'>Daj ostrzeżenie</button></form>";
        echo "</div></details>";
    }
    echo "</div><div class='nc-t'" . ($kopia ? '' : " id='post-tresc-$pid'") . ">$t</div>";
    if ($edytuj) echo "<div id='post-edycja-$pid' style='display:none;margin-top:10px'><form method='POST'><input type='hidden' name='post_id' value='$pid'><input type='hidden' name='zakladka_powrot' value='offtop'><textarea name='nowa_tresc' class='edytor-text tag-input' style='min-height:70px'>" . nc_h($p['tresc']) . "</textarea><div style='text-align:right'><button type='button' class='btn-mini' onclick='ukryjEdycje($pid)'>Anuluj</button> <button type='submit' name='zapisz_edycje' class='btn-wyslij ember' style='padding:7px 16px;font-size:.85em'>Zapisz</button></div></form></div>";
    echo "</div>";
}

function nc_render(mysqli $db, array $s, int $gid, bool $prow, bool $moze, bool $zaakc, ?array $zaw, ?array $wyc, string $blad = ''): void {
    $sid = (int)$s['id'];
    $c = ['gid' => $gid, 'wlasc' => (int)$s['mg_id'], 'prow' => $prow, 'zaakc' => $zaakc, 'nadzor' => mod_moze($db, $gid), 'zak' => ($s['status'] ?? '') === 'Zakończona'];
    $sql = "SELECT p.*, g.login, u.rola FROM sesje_posty p JOIN gracze g ON g.id = p.autor_id
            LEFT JOIN sesje_uczestnicy u ON u.sesja_id = p.sesja_id AND u.gracz_id = p.autor_id
            WHERE p.sesja_id = ? AND p.typ_postu = 'OffTop'";
    $przyp = db_wiersze($db, "$sql AND p.nc_przypiety = 1 ORDER BY p.data_dodania", [$sid]);
    $posty = array_reverse(db_wiersze($db, "$sql ORDER BY p.data_dodania DESC LIMIT 80", [$sid]));
    $wycisz = $prow ? db_wiersze($db, "SELECT w.gracz_id, w.do_kiedy, g.login FROM nc_wyciszenia w JOIN gracze g ON g.id = w.gracz_id WHERE w.sesja_id = ? AND (w.do_kiedy IS NULL OR w.do_kiedy > NOW()) ORDER BY g.login", [$sid]) : [];
    nc_css();
    echo "<div class='nc'><div class='nc-lead'><b>NC · Non Clima</b> — rozmowy poza fabułą: ustalenia, pytania do prowadzącego, spostrzeżenia graczy. Piszą uczestnicy i obserwatorzy.</div>";
    if ($blad !== '') echo "<div class='nc-blad'>⚠ " . nc_h($blad) . "</div>";
    if ($przyp) { echo "<div class='nc-przyp'><div class='nc-lbl'>📌 Przypięte</div>"; foreach ($przyp as $p) nc_wpis($db, $p, $c, true); echo "</div>"; }
    echo "<div class='nc-box' id='ncBox'>";
    foreach ($posty as $p) nc_wpis($db, $p, $c);
    if (!$posty) echo "<div class='nc-pusto'>// Brak rozmów</div>";
    echo "</div>";
    if ($wycisz) {
        echo "<div class='nc-wyc'><div class='nc-lbl'>Wyciszeni w NC</div>";
        foreach ($wycisz as $w) echo "<form method='POST'><input type='hidden' name='nc_akcja' value='odcisz'><input type='hidden' name='gracz_id' value='" . (int)$w['gracz_id'] . "'><span><b>" . nc_h($w['login']) . "</b> · " . ($w['do_kiedy'] ? 'do ' . mod_data($w['do_kiedy']) : 'do końca sesji') . "</span><button type='submit' class='btn-mini'>Odcisz</button></form>";
        echo "</div>";
    }
    if ($moze) {
        echo "<form method='POST' class='nc-form'><input type='hidden' name='nc_akcja' value='pisz'><textarea name='tresc' class='edytor-text tag-input' required maxlength='5000' placeholder='Napisz w NC… **pogrubienie**, _kursywa_, @Nick'></textarea><div class='nc-form-d'>";
        if ($prow) echo "<label><input type='checkbox' name='upomnienie' value='1'> Wyróżnij jako upomnienie</label>";
        echo "<button type='submit' class='btn-wyslij cyan'>Wyślij</button></div></form>";
    } else {
        $pow = $c['zak'] ? 'Sesja zakończona — NC tylko do odczytu.'
             : ($zaw ? 'Zawieszenie do ' . mod_data($zaw['do_kiedy']) . ' — nie możesz pisać. <a href="game.php?page=moderacja">Historia i odwołanie →</a>'
             : ($wyc ? 'Prowadzący wyciszył Cię w NC ' . ($wyc['do_kiedy'] ? 'do ' . mod_data($wyc['do_kiedy']) : 'do końca sesji') . '.'
             : 'Sesja prywatna — w NC piszą tylko jej uczestnicy.'));
        echo "<div class='nc-stop'>// $pow</div>";
    }
    echo "</div><script>(function(){var b=document.getElementById('ncBox');if(b)b.scrollTop=b.scrollHeight;})();</script>";
}
