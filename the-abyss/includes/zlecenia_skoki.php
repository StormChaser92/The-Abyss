<?php
/* the-abyss/includes/zlecenia_skoki.php — skoki wieloetapowe i nagrody za głowę.
   Dołączany przez includes/zlecenia_logika.php (tam test, obława, areszt, reputacja). */

/* ── skoki ──────────────────────────────────────────────────────────── */

function zl_skok_aktywny(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM zl_skoki WHERE gracz_id = ? AND status = 'w_toku' ORDER BY id DESC LIMIT 1", [$gid]);
}

function zl_skok_blok(mysqli $db, array $g, array $d, array $rep, string $kod): string {
    if ((int)$g['poziom'] < (int)$d['lvl']) return 'Od poziomu ' . (int)$d['lvl'];
    if (zl_tier($rep[$d['npc']] ?? 0) < (int)$d['rep']) return 'Reputacja: ' . ZL_RANGI[(int)$d['rep']];
    if ((int)$g['zl_oblawa'] >= 3) return 'Obława 3★: skoki zablokowane';
    $ost = db_wiersz($db, "SELECT kiedy FROM zl_dziennik WHERE gracz_id = ? AND kod = ? ORDER BY id DESC LIMIT 1", [(int)$g['id'], $kod]);
    if ($ost && strtotime($ost['kiedy']) > time() - ZL_ODNOWA_H * 3600) return 'Znów za ' . max(1, (int)ceil((strtotime($ost['kiedy']) + ZL_ODNOWA_H * 3600 - time()) / 3600)) . ' h';
    return '';
}

function zl_skok_start(mysqli $db, array $g, string $region, string $kod): array {
    $d = zl_dane($region)['skoki'][$kod] ?? null;
    if (!$d) return [false, 'Nie ma takiego skoku.'];
    $gid = (int)$g['id'];
    if ($b = zl_blokada($db, $gid)) return [false, $b];
    if (zl_skok_aktywny($db, $gid)) return [false, 'Jeden skok naraz.'];
    [$uz, $lim] = zl_limit($db, $g);
    if ($uz >= $lim) return [false, "Na dziś wystarczy: $uz / $lim zleceń."];
    if ($w = zl_skok_blok($db, $g, $d, zl_rep($db, $gid), $kod)) return [false, $w];
    db_q($db, "INSERT INTO zl_dziennik (gracz_id, rodzaj, kod, npc, rozliczone) VALUES (?, 'skok', ?, ?, 0)", [$gid, $kod, $d['npc']]);
    $did = (int)$db->insert_id;
    db_q($db, "INSERT INTO zl_skoki (gracz_id, dziennik_id, kod, log) VALUES (?, ?, ?, '[]')", [$gid, $did, $kod]);
    zl_oblawa_ustaw($db, $gid, (int)$g['zl_oblawa']);
    return [true, 'Skok <b>' . zl_h($d['t']) . '</b> się zaczyna. Wybierz, jak przejdziesz pierwszy etap.'];
}

function zl_skok_wybierz(mysqli $db, array $g, string $region, int $op): array {
    $gid = (int)$g['id']; $s = zl_skok_aktywny($db, $gid);
    if (!$s) return [false, 'Nie masz skoku w toku.'];
    if ($s['wybor'] !== null) return [false, 'Ten etap już trwa.'];
    $d = zl_dane($region)['skoki'][$s['kod']] ?? null;
    $e = $d['etapy'][(int)$s['etap']] ?? null; $o = $e['op'][$op] ?? null;
    if (!$o) return [false, 'Nie ma takiej drogi.'];
    if ($b = zl_blokada($db, $gid)) return [false, $b];
    if (!empty($o['koszt'])) {
        $k = zl_kasa($g, (int)$o['koszt']);
        if (!kasa_pobierz($db, $gid, $k)) return [false, 'Ta droga kosztuje ' . zl_fmt($k) . ' $.'];
    }
    $sz = zl_szansa($g, $o, 1, (int)$s['premia'])['s'];
    db_q($db, "UPDATE zl_skoki SET wybor = ?, szansa = ?, etap_gotowy = NOW() + INTERVAL ? MINUTE WHERE id = ? AND wybor IS NULL", [$op, $sz, (int)$e['min'], (int)$s['id']]);
    return [true, '<b>' . zl_h($o['t']) . "</b>. Etap potrwa {$e['min']} min."];
}

function zl_skok_wynik(mysqli $db, array $g, string $region): array {
    $gid = (int)$g['id']; $s = zl_skok_aktywny($db, $gid);
    if (!$s || $s['wybor'] === null) return [false, 'Nic nie czeka na wynik.'];
    if (strtotime($s['etap_gotowy']) > time()) return [false, 'Jeszcze trwa.'];
    if (db_zmien($db, "UPDATE zl_skoki SET wybor = NULL WHERE id = ? AND wybor = ?", [(int)$s['id'], (int)$s['wybor']]) !== 1) return [false, 'Już rozliczone.'];
    $d = zl_dane($region)['skoki'][$s['kod']];
    $nr = (int)$s['etap']; $e = $d['etapy'][$nr]; $o = $e['op'][(int)$s['wybor']];
    $wynik = zl_wynik(rand(1, 100), (int)$s['szansa']);
    $lup = (int)$s['lup']; $por = (int)$s['porazki']; $ob = (int)$g['zl_oblawa']; $premia = 0;
    if ($wynik === 'sukces')        { $lup += (int)($o['lup'] ?? 0); $premia = (int)($o['premia'] ?? 0); $por = 0; $dOb = empty($o['gl']) ? 0 : 1; }
    elseif ($wynik === 'polowiczny'){ $lup = (int)round(($lup + (int)($o['lup'] ?? 0)) * 0.75); $por = 0; $dOb = 1 + (empty($o['gl']) ? 0 : 1); }
    else                            { $lup = (int)round($lup * 0.5); $por++; $dOb = 2; }
    $lup = max(0, $lup);
    $ob = zl_oblawa_ustaw($db, $gid, $ob + $dOb);
    $txt = $e['n'] . ': ' . ['sukces' => 'udało się.', 'polowiczny' => 'połowicznie. Łup −25%.', 'porazka' => 'nie wyszło. Łup −50%.'][$wynik] . ($dOb ? " Obława +$dOb." : '');
    $log = json_decode($s['log'] ?: '[]', true) ?: [];
    $log[] = ['e' => $e['n'], 'o' => $o['t'], 'w' => $wynik];
    $areszt = '';
    if ($wynik === 'porazka' && (!empty($e['ucieczka']) || $ob >= 4) && zl_czy_areszt($ob, !empty($e['ucieczka']))) $areszt = 'tak';
    $koniec = $areszt || $por >= 2 || $nr + 1 >= count($d['etapy']);
    db_q($db, "UPDATE zl_skoki SET etap = ?, lup = ?, premia = ?, porazki = ?, log = ?, szansa = 0 WHERE id = ?",
         [$nr + 1, $lup, $premia, $por, json_encode($log, JSON_UNESCAPED_UNICODE), (int)$s['id']]);
    if (!$koniec) return [$wynik !== 'porazka', $txt . ' Przed tobą: <b>' . zl_h($d['etapy'][$nr + 1]['n']) . '</b>.'];
    return [$wynik !== 'porazka', $txt . zl_skok_koniec($db, zl_gracz($db, $gid), $region, (int)$s['id'], $areszt !== '', $ob)];
}

/** Rozliczenie skoku: łup, XP, materiały, reputacja, wieść. */
function zl_skok_koniec(mysqli $db, array $g, string $region, int $sid, bool $areszt, int $ob): string {
    $gid = (int)$g['id'];
    $s = db_wiersz($db, "SELECT * FROM zl_skoki WHERE id = ? AND status = 'w_toku'", [$sid]);
    if (!$s || db_zmien($db, "UPDATE zl_skoki SET status = 'zakonczony' WHERE id = ? AND status = 'w_toku'", [$sid]) !== 1) return '';
    $dane = zl_dane($region); $d = $dane['skoki'][$s['kod']];
    $pelny = (int)$s['etap'] >= count($d['etapy']) && !$areszt;
    $lup = (int)$s['lup'];
    $kasa = $areszt ? 0 : zl_kasa($g, (int)$d['lup'], $lup / 100);
    $xp = zl_xp($g, (int)$d['xp'], $pelny ? max(0.3, $lup / 100) : 0.25);
    db_q($db, "UPDATE gracze SET gotowka = gotowka + ?, exp = exp + ? WHERE id = ?", [$kasa, $xp, $gid]);
    $txt = ' <b>Koniec skoku.</b> ' . ($kasa ? '+' . zl_fmt($kasa) . ' $, ' : 'Bez łupu, ') . "+$xp XP.";
    if ($pelny && $lup >= 100 && !empty($d['mat'])) {
        foreach ($d['mat'] as $kol => $ile) if (in_array($kol, ['zlom_stalowy', 'czesci_mechaniczne', 'syntetyki', 'elektronika'], true)) db_q($db, "UPDATE gracze SET `$kol` = `$kol` + ? WHERE id = ?", [(int)$ile, $gid]);
        $txt .= ' W torbie są też części dla Manufaktury.';
    }
    $wynik = $pelny && $lup >= 100 ? 'sukces' : ($kasa > 0 ? 'polowiczny' : 'porazka');
    db_q($db, "UPDATE zl_dziennik SET wynik = ?, nagroda = ?, exp = ?, rozliczone = 1 WHERE id = ?", [$wynik, $kasa, $xp, (int)$s['dziennik_id']]);
    if ($wynik === 'sukces') {
        $txt .= zl_rep_dodaj($db, $g, $region, $d['npc'], 2);
        if (!empty($d['wsp'])) zl_rep_dodaj($db, $g, $region, $d['wsp'], 1);
        zl_wiesc($db, $region, 'Ktoś zrobił <b>' . zl_h($d['t']) . '</b>. Policja nie ma nic poza nagraniem, na którym niczego nie widać.');
    } elseif ($wynik === 'porazka') {
        zl_rep_dodaj($db, $g, $region, $d['npc'], -1);
        zl_wiesc($db, $region, 'Nieudany skok: <b>' . zl_h($d['t']) . '</b>. Ktoś uciekał w pośpiechu.');
    }
    if ($areszt) $txt .= zl_areszt($db, $g, $region, $ob);
    return $txt;
}

function zl_skok_porzuc(mysqli $db, array $g, string $region): array {
    $s = zl_skok_aktywny($db, (int)$g['id']);
    if (!$s) return [false, 'Nie masz skoku w toku.'];
    if (db_zmien($db, "UPDATE zl_skoki SET status = 'przerwany' WHERE id = ? AND status = 'w_toku'", [(int)$s['id']]) !== 1) return [false, 'Już po skoku.'];
    $d = zl_dane($region)['skoki'][$s['kod']] ?? null;
    db_q($db, "UPDATE zl_dziennik SET wynik = 'przerwany', rozliczone = 1 WHERE id = ?", [(int)$s['dziennik_id']]);
    if ($d) zl_rep_dodaj($db, $g, $region, $d['npc'], -3);
    return [true, 'Zostawiasz ekipę w połowie roboty. ' . zl_h(zl_dane($region)['npc'][$d['npc'] ?? '']['n'] ?? '') . ' tego nie zapomni (−3 reputacji).'];
}

/* ── nagrody za głowę ───────────────────────────────────────────────── */

const ZL_GLOWA_MIN = 5000;
const ZL_GLOWA_OPLATA = 0.10;
const ZL_GLOWA_DNI = 7;
const ZL_GLOWA_LVL = 10;
const ZL_GLOWA_ROZNICA = 10;

/** Wygasłe nagrody wracają do wystawiającego (bez opłaty). */
function zl_glowy_wygas(mysqli $db): void {
    foreach (db_wiersze($db, "SELECT id, wystawil_id, kwota FROM zl_glowy WHERE status = 'aktywna' AND wygasa < NOW() LIMIT 50") as $w)
        if (db_zmien($db, "UPDATE zl_glowy SET status = 'wygasla' WHERE id = ? AND status = 'aktywna'", [(int)$w['id']]) === 1) {
            kasa_dodaj($db, (int)$w['wystawil_id'], (int)$w['kwota']);
            powiadom($db, (int)$w['wystawil_id'], 'Nikt nie zgarnął twojej nagrody za głowę. Wraca do ciebie ' . zl_fmt((int)$w['kwota']) . ' $.');
        }
}

function zl_glowa_wystaw(mysqli $db, array $g, string $region, string $login, int $kwota): array {
    $cel = db_wiersz($db, "SELECT id, login, poziom, syndykat_id FROM gracze WHERE login = ?", [trim($login)]);
    if (!$cel) return [false, 'Nie ma takiego gracza.'];
    if ((int)$cel['id'] === (int)$g['id']) return [false, 'Na siebie nie wystawisz.'];
    if ((int)$g['syndykat_id'] > 0 && (int)$cel['syndykat_id'] === (int)$g['syndykat_id']) return [false, 'Nie na kogoś z twojego syndykatu.'];
    if ((int)$cel['poziom'] < ZL_GLOWA_LVL) return [false, 'Cel musi mieć co najmniej ' . ZL_GLOWA_LVL . ' poziom.'];
    if ($kwota < ZL_GLOWA_MIN) return [false, 'Minimum ' . zl_fmt(ZL_GLOWA_MIN) . ' $.'];
    $opl = (int)round($kwota * ZL_GLOWA_OPLATA);
    if (!kasa_pobierz($db, (int)$g['id'], $kwota + $opl)) return [false, 'Potrzebujesz ' . zl_fmt($kwota + $opl) . ' $ przy sobie.'];
    db_q($db, "INSERT INTO zl_glowy (cel_id, wystawil_id, kwota, oplata, wygasa) VALUES (?, ?, ?, ?, NOW() + INTERVAL ? DAY)", [(int)$cel['id'], (int)$g['id'], $kwota, $opl, ZL_GLOWA_DNI]);
    zl_wiesc($db, $region, 'Na <b>' . zl_h($cel['login']) . '</b> wystawiono nagrodę za głowę: ' . zl_fmt($kwota) . ' $.');
    return [true, 'Nagroda wystawiona na ' . ZL_GLOWA_DNI . ' dni. Opłata ' . zl_fmt($opl) . ' $ przepadła.'];
}

/** Lista aktywnych celów (suma nagród na cel). */
function zl_glowy_lista(mysqli $db): array {
    return db_wiersze($db, "SELECT g.id, g.login, g.poziom, g.syndykat_id, s.nazwa AS syndykat, SUM(z.kwota) AS suma, MIN(z.wygasa) AS wygasa, COUNT(*) AS ile
                            FROM zl_glowy z JOIN gracze g ON g.id = z.cel_id LEFT JOIN syndykaty s ON s.id = g.syndykat_id
                            WHERE z.status = 'aktywna' GROUP BY g.id ORDER BY suma DESC LIMIT 40");
}

/** Czy $ja może zgarnąć nagrodę z $cel ('' = tak). */
function zl_glowa_blok(mysqli $db, array $ja, array $cel): string {
    if ((int)$ja['id'] === (int)$cel['id']) return 'To ty';
    if ((int)$ja['syndykat_id'] > 0 && (int)$ja['syndykat_id'] === (int)$cel['syndykat_id']) return 'Twój syndykat';
    if ((int)$ja['poziom'] > (int)$cel['poziom'] + ZL_GLOWA_ROZNICA) return 'Za niski poziom celu';
    $p = db_wiersz($db, "SELECT ostatnio FROM zl_polowania WHERE lowca_id = ? AND cel_id = ?", [(int)$ja['id'], (int)$cel['id']]);
    if ($p && strtotime($p['ostatnio']) > time() - 12 * 3600) return 'Znów za ' . max(1, (int)ceil((strtotime($p['ostatnio']) + 12 * 3600 - time()) / 3600)) . ' h';
    return '';
}

/** Wywoływane z pages/walka_pvp.php po walce. Zwraca tekst do podsumowania. */
function zl_glowa_po_walce(mysqli $db, array $ja, array $on, bool $wygrana): string {
    $ja = db_wiersz($db, "SELECT id, login, poziom, syndykat_id, obecne_miasto FROM gracze WHERE id = ?", [(int)$ja['id']]);
    $on = db_wiersz($db, "SELECT id, login, poziom, syndykat_id FROM gracze WHERE id = ?", [(int)$on['id']]);
    if (!$ja || !$on || !db_wiersz($db, "SELECT id FROM zl_glowy WHERE cel_id = ? AND status = 'aktywna' AND wygasa > NOW() LIMIT 1", [(int)$on['id']])) return '';
    if (zl_glowa_blok($db, $ja, $on) !== '') return '';
    db_q($db, "REPLACE INTO zl_polowania (lowca_id, cel_id, ostatnio) VALUES (?, ?, NOW())", [(int)$ja['id'], (int)$on['id']]);
    if (!$wygrana) return "<br><span style='color:#ff7a3d'>Nagroda za głowę " . zl_h($on['login']) . " wciąż czeka. Następna próba za 12 godzin.</span>";
    $suma = 0;
    foreach (db_wiersze($db, "SELECT id, kwota, wystawil_id FROM zl_glowy WHERE cel_id = ? AND status = 'aktywna' AND wygasa > NOW()", [(int)$on['id']]) as $z)
        if (db_zmien($db, "UPDATE zl_glowy SET status = 'zgarnieta', lowca_id = ? WHERE id = ? AND status = 'aktywna'", [(int)$ja['id'], (int)$z['id']]) === 1) {
            $suma += (int)$z['kwota'];
            powiadom($db, (int)$z['wystawil_id'], zl_h($ja['login']) . ' zgarnął twoją nagrodę za głowę ' . zl_h($on['login']) . '.');
        }
    if ($suma <= 0) return '';
    kasa_dodaj($db, (int)$ja['id'], $suma);
    zl_wiesc($db, zl_region($ja['obecne_miasto']) ?? 'NY', '<b>' . zl_h($ja['login']) . '</b> zgarnął nagrodę za głowę <b>' . zl_h($on['login']) . '</b>: ' . zl_fmt($suma) . ' $.');
    return "<br><span style='color:#ffd700'>Nagroda za głowę: <b>+" . zl_fmt($suma) . " $</b>.</span>";
}
