<?php
/* the-abyss/includes/katedra.php — śluby i rozwody w Katedrze.
   ŚLUB:   oświadczyny → „tak” drugiej osoby → Proboszcz zatwierdza i ustala cenę (albo odmawia)
           → ktoś z pary płaci całość → małżeństwo + ogłoszenie w Klubie. Pieniądze znikają (Katedra).
   ROZWÓD: propozycja jednego małżonka → zgoda drugiego → Proboszcz ustala opłatę (nie może odmówić)
           → każde płaci połowę → rozwód, wpis w Plotkach obojga, 7 dni karencji.
   Proboszcz (gracze.is_proboszcz) nie wycenia własnego ślubu ani rozwodu. Cena 0 = od razu bez płacenia.
   Każdy krok wysyła powiadomienie (nie pocztę). */
require_once __DIR__ . '/bezpieczne.php';
require_once __DIR__ . '/nick.php';

if (!function_exists('kt_malzenstwo')) {

define('KT_KARENCJA_DNI', 7);
define('KT_W_TOKU', "'oswiadczyny','czeka_proboszcz','do_zaplaty'");

function kt_malzenstwo(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM malzenstwa WHERE (malzonek_1_id = ? OR malzonek_2_id = ?) AND status = 'aktywne' LIMIT 1", [$gid, $gid]);
}
function kt_w_toku(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE (od_id = ? OR do_id = ?) AND status IN (" . KT_W_TOKU . ") ORDER BY id DESC LIMIT 1", [$gid, $gid]);
}
function kt_proboszcz(mysqli $db, int $gid): bool {
    return !empty(db_wiersz($db, "SELECT is_proboszcz FROM gracze WHERE id = ?", [$gid])['is_proboszcz']);
}
function kt_login(mysqli $db, int $gid): string {
    return (string)(db_wiersz($db, "SELECT login FROM gracze WHERE id = ?", [$gid])['login'] ?? '?');
}
/** Koniec karencji po ostatnim rozwodzie (timestamp) albo null. */
function kt_karencja(mysqli $db, int $gid): ?int {
    $r = db_wiersz($db, "SELECT MAX(data_rozwodu) d FROM malzenstwa WHERE (malzonek_1_id = ? OR malzonek_2_id = ?) AND status = 'rozwiazane'", [$gid, $gid]);
    if (empty($r['d'])) return null;
    $koniec = strtotime($r['d']) + KT_KARENCJA_DNI * 86400;
    return $koniec > time() ? $koniec : null;
}
function kt_fmt(int $k): string { return number_format($k, 0, ',', ' ') . ' $'; }
function kt_plotka(mysqli $db, int $gid, string $html): void { db_q($db, "INSERT INTO plotki (gracz_id, tresc) VALUES (?, ?)", [$gid, $html]); }
function kt_klub(mysqli $db, string $html): void { db_q($db, "INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', ?, 'sala-glowna', 'system')", [$html]); }
function kt_zaplac(mysqli $db, int $gid, int $kwota): bool {
    return $kwota <= 0 || db_zmien($db, "UPDATE gracze SET gotowka = gotowka - ? WHERE id = ? AND gotowka >= ?", [$kwota, $gid, $kwota]) === 1;
}
/** Połowa rozwodu: wnioskujący płaci ewentualną nieparzystą złotówkę. */
function kt_polowa(array $z, bool $od): int { $p = intdiv((int)$z['cena'], 2); return $od ? (int)$z['cena'] - $p : $p; }
function kt_powiadom_proboszczow(mysqli $db, array $pomin, string $html): void {
    foreach (db_wiersze($db, "SELECT id FROM gracze WHERE is_proboszcz = 1") as $p)
        if (!in_array((int)$p['id'], $pomin, true)) powiadom($db, (int)$p['id'], $html);
}

/** Oświadczyny. Zwraca [ok, komunikat]. */
function kt_oswiadcz(mysqli $db, int $ja, string $login, string $slowa): array {
    $cel = db_wiersz($db, "SELECT id, login FROM gracze WHERE login = ?", [trim($login)]);
    if (!$cel) return [false, 'Nie ma obywatela o takim nicku.'];
    $on = (int)$cel['id']; $onl = nk_h($cel['login']);
    if ($on === $ja) return [false, 'Nie możesz wziąć ślubu sam ze sobą.'];
    if (kt_malzenstwo($db, $ja)) return [false, 'Jesteś już w związku małżeńskim.'];
    if (kt_malzenstwo($db, $on)) return [false, "$onl jest już w związku małżeńskim."];
    if ($k = kt_karencja($db, $ja)) return [false, 'Po rozwodzie nowy ślub możliwy od ' . date('d.m.Y, H:i', $k) . '.'];
    if (kt_karencja($db, $on)) return [false, "$onl jest jeszcze w okresie karencji po rozwodzie."];
    if (kt_w_toku($db, $ja)) return [false, 'Masz już zgłoszenie w toku.'];
    if (kt_w_toku($db, $on)) return [false, "$onl ma już inne zgłoszenie w toku."];
    $slowa = trim(mb_substr(strip_tags($slowa), 0, 400));
    db_q($db, "INSERT INTO katedra_zgloszenia (typ, od_id, do_id, slowa) VALUES ('slub', ?, ?, ?)", [$ja, $on, $slowa]);
    powiadom($db, $on, "💍 <b>" . nk_h(kt_login($db, $ja)) . "</b> prosi Cię o rękę. Odpowiedz w <a href='game.php?page=katedra'>Katedrze</a>.");
    return [true, "Oświadczyny złożone. $onl dostanie powiadomienie."];
}

/** Propozycja rozwodu. */
function kt_rozwod_proponuj(mysqli $db, int $ja): array {
    $m = kt_malzenstwo($db, $ja);
    if (!$m) return [false, 'Nie jesteś w związku małżeńskim.'];
    if (kt_w_toku($db, $ja)) return [false, 'Masz już zgłoszenie w toku.'];
    $on = (int)$m['malzonek_1_id'] === $ja ? (int)$m['malzonek_2_id'] : (int)$m['malzonek_1_id'];
    db_q($db, "INSERT INTO katedra_zgloszenia (typ, malzenstwo_id, od_id, do_id) VALUES ('rozwod', ?, ?, ?)", [(int)$m['id'], $ja, $on]);
    powiadom($db, $on, "💔 <b>" . nk_h(kt_login($db, $ja)) . "</b> proponuje rozwód. Odpowiedz w <a href='game.php?page=katedra'>Katedrze</a>.");
    return [true, 'Propozycja rozwodu wysłana.'];
}

/** Odpowiedź drugiej osoby (ślub albo rozwód): tak → czeka na Proboszcza, nie → odmowa. */
function kt_odpowiedz(mysqli $db, int $ja, int $zid, bool $tak): array {
    $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND do_id = ? AND status = 'oswiadczyny'", [$zid, $ja]);
    if (!$z) return [false, 'To zgłoszenie jest już nieaktualne.'];
    $slub = $z['typ'] === 'slub';
    if ($tak && $slub && (kt_malzenstwo($db, $ja) || kt_malzenstwo($db, (int)$z['od_id']))) return [false, 'Któreś z was jest już w związku małżeńskim.'];
    $nowy = $tak ? 'czeka_proboszcz' : 'odmowa';
    if (db_zmien($db, "UPDATE katedra_zgloszenia SET status = ?, decyzja_o = IF(? = 'odmowa', NOW(), NULL) WHERE id = ? AND status = 'oswiadczyny'", [$nowy, $nowy, $zid]) !== 1) return [false, 'To zgłoszenie jest już nieaktualne.'];
    $moj = nk_h(kt_login($db, $ja)); $od = (int)$z['od_id'];
    if (!$tak) {
        powiadom($db, $od, $slub ? "<b>$moj</b> nie przyjmuje oświadczyn." : "<b>$moj</b> nie zgadza się na rozwód.");
        return [true, $slub ? 'Odmówiłaś/eś.' : 'Nie zgodziłaś/eś się na rozwód.'];
    }
    $para = nk_h(kt_login($db, $od)) . ' i ' . $moj;
    powiadom($db, $od, $slub ? "💍 <b>$moj</b> mówi „tak”. Proboszcz wyceni ceremonię w <a href='game.php?page=katedra'>Katedrze</a>."
                             : "<b>$moj</b> zgadza się na rozwód. Proboszcz ustali opłatę.");
    kt_powiadom_proboszczow($db, [$od, $ja], $slub ? "⛪ $para proszą o ślub. <a href='game.php?page=katedra'>Katedra</a>"
                                                   : "⛪ $para proszą o rozwód. <a href='game.php?page=katedra'>Katedra</a>");
    return [true, $slub ? 'Zgoda złożona. Teraz Proboszcz wyceni ceremonię.' : 'Zgoda złożona. Proboszcz ustali opłatę.'];
}

function kt_wycofaj(mysqli $db, int $ja, int $zid): array {
    $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND (od_id = ? OR do_id = ?) AND status IN (" . KT_W_TOKU . ")", [$zid, $ja, $ja]);
    if (!$z) return [false, 'To zgłoszenie jest już nieaktualne.'];
    if ((int)$z['zaplacil_od'] || (int)$z['zaplacil_do']) return [false, 'Opłata jest już częściowo wniesiona — zgłoszenia nie można wycofać.'];
    if (db_zmien($db, "UPDATE katedra_zgloszenia SET status = 'wycofane', decyzja_o = NOW() WHERE id = ? AND status IN (" . KT_W_TOKU . ")", [$zid]) !== 1) return [false, 'To zgłoszenie jest już nieaktualne.'];
    $drugi = (int)$z['od_id'] === $ja ? (int)$z['do_id'] : (int)$z['od_id'];
    powiadom($db, $drugi, "<b>" . nk_h(kt_login($db, $ja)) . "</b> wycofuje " . ($z['typ'] === 'slub' ? 'zgłoszenie ślubu.' : 'wniosek o rozwód.'));
    return [true, 'Zgłoszenie wycofane.'];
}

/** Decyzja Proboszcza: ślub → zatwierdź z ceną albo odmów; rozwód → tylko wycena. */
function kt_decyzja(mysqli $db, int $ks, int $zid, bool $tak, int $cena, string $uwaga): array {
    if (!kt_proboszcz($db, $ks)) return [false, 'Tylko Proboszcz decyduje w Katedrze.'];
    $uwaga = trim(mb_substr(strip_tags($uwaga), 0, 400));
    $cena = max(0, $cena);
    return db_tx($db, function () use ($db, $ks, $zid, $tak, $cena, $uwaga) {
        $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND status = 'czeka_proboszcz' FOR UPDATE", [$zid]);
        if (!$z) return [false, 'To zgłoszenie jest już rozpatrzone.'];
        $a = (int)$z['od_id']; $b = (int)$z['do_id']; $slub = $z['typ'] === 'slub';
        if ($ks === $a || $ks === $b) return [false, 'O własnym ' . ($slub ? 'ślubie' : 'rozwodzie') . ' decyduje inny Proboszcz.'];
        $ksl = nk_h(kt_login($db, $ks));
        if ($slub && !$tak) {
            if ($uwaga === '') return [false, 'Podaj powód odmowy.'];
            db_q($db, "UPDATE katedra_zgloszenia SET status = 'odrzucone', proboszcz_id = ?, uwaga = ?, decyzja_o = NOW() WHERE id = ?", [$ks, $uwaga, $zid]);
            $txt = "Proboszcz <b>$ksl</b> odmówił udzielenia ślubu. Powód: " . nk_h($uwaga);
            powiadom($db, $a, $txt); powiadom($db, $b, $txt);
            return [true, 'Odmówiono.'];
        }
        db_q($db, "UPDATE katedra_zgloszenia SET status = 'do_zaplaty', proboszcz_id = ?, cena = ?, uwaga = ?, decyzja_o = NOW() WHERE id = ?", [$ks, $cena, $uwaga, $zid]);
        if ($cena === 0) return kt_finalizuj($db, $zid);
        $txt = $slub ? "💍 Proboszcz <b>$ksl</b> zatwierdził ślub. Cena ceremonii: <b>" . kt_fmt($cena) . "</b>. Zapłać w <a href='game.php?page=katedra'>Katedrze</a>."
                     : "Proboszcz <b>$ksl</b> ustalił opłatę za rozwód: <b>" . kt_fmt($cena) . "</b> (każde płaci połowę). <a href='game.php?page=katedra'>Katedra</a>";
        powiadom($db, $a, $txt); powiadom($db, $b, $txt);
        return [true, $slub ? 'Ślub zatwierdzony i wyceniony na ' . kt_fmt($cena) . '.' : 'Rozwód wyceniony na ' . kt_fmt($cena) . '.'];
    });
}

/** Opłata. Ślub: jedna osoba płaci całość. Rozwód: każde swoją połowę. */
function kt_zaplac_zgloszenie(mysqli $db, int $ja, int $zid): array {
    return db_tx($db, function () use ($db, $ja, $zid) {
        $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND (od_id = ? OR do_id = ?) AND status = 'do_zaplaty' FOR UPDATE", [$zid, $ja, $ja]);
        if (!$z) return [false, 'Nie ma nic do zapłaty.'];
        $od = (int)$z['od_id'] === $ja;
        if ($z['typ'] === 'slub') {
            if (!kt_zaplac($db, $ja, (int)$z['cena'])) return [false, 'Masz za mało gotówki. Ceremonia kosztuje ' . kt_fmt((int)$z['cena']) . '.'];
            db_q($db, "UPDATE katedra_zgloszenia SET zaplacil_od = ?, zaplacil_do = ? WHERE id = ?", [$od ? 1 : 0, $od ? 0 : 1, $zid]);
            return kt_finalizuj($db, $zid);
        }
        $kol = $od ? 'zaplacil_od' : 'zaplacil_do';
        if ((int)$z[$kol]) return [false, 'Swoją połowę już zapłaciłaś/eś.'];
        $kw = kt_polowa($z, $od);
        if (!kt_zaplac($db, $ja, $kw)) return [false, 'Masz za mało gotówki. Twoja połowa to ' . kt_fmt($kw) . '.'];
        db_q($db, "UPDATE katedra_zgloszenia SET `$kol` = 1 WHERE id = ?", [$zid]);
        $z[$kol] = 1;
        if ((int)$z['zaplacil_od'] && (int)$z['zaplacil_do']) return kt_finalizuj($db, $zid);
        powiadom($db, $od ? (int)$z['do_id'] : (int)$z['od_id'], "<b>" . nk_h(kt_login($db, $ja)) . "</b> zapłacił/a swoją połowę opłaty za rozwód. <a href='game.php?page=katedra'>Katedra</a>");
        return [true, 'Zapłacono ' . kt_fmt($kw) . '. Rozwód zostanie orzeczony, gdy druga osoba zapłaci swoją połowę.'];
    });
}

/** Zawarcie ślubu albo orzeczenie rozwodu (po opłacie lub przy cenie 0). Wołane wewnątrz transakcji. */
function kt_finalizuj(mysqli $db, int $zid): array {
    $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ?", [$zid]);
    $a = (int)$z['od_id']; $b = (int)$z['do_id']; $ks = (int)$z['proboszcz_id'];
    $la = nk_h(kt_login($db, $a)); $lb = nk_h(kt_login($db, $b)); $ksl = nk_h(kt_login($db, $ks));
    if ($z['typ'] === 'slub') {
        if (kt_malzenstwo($db, $a) || kt_malzenstwo($db, $b)) return [false, 'Któreś z was jest już w związku małżeńskim.'];
        db_q($db, "INSERT INTO malzenstwa (malzonek_1_id, malzonek_2_id, status, data_slubu, proboszcz_id, cena) VALUES (?, ?, 'aktywne', NOW(), ?, ?)", [$a, $b, $ks, (int)$z['cena']]);
        db_q($db, "UPDATE katedra_zgloszenia SET status = 'zaakceptowane' WHERE id = ?", [$zid]);
        $txt = "💍 Proboszcz <b>$ksl</b> udzielił wam ślubu. Od dziś jesteście małżeństwem." . ($z['uwaga'] !== '' ? ' „' . nk_h($z['uwaga']) . '”' : '');
        powiadom($db, $a, $txt); powiadom($db, $b, $txt);
        kt_klub($db, "⛪ Dzwony Katedry: <b>$la</b> i <b>$lb</b> wzięli ślub. Udzielił go <b>$ksl</b>.");
        return [true, "Ślub zawarty. $la i $lb są małżeństwem."];
    }
    $m = db_wiersz($db, "SELECT * FROM malzenstwa WHERE id = ? AND status = 'aktywne'", [(int)$z['malzenstwo_id']]);
    if (!$m) return [false, 'To małżeństwo już nie istnieje.'];
    db_q($db, "UPDATE malzenstwa SET status = 'rozwiazane', data_rozwodu = NOW(), oplata_rozwodu = ? WHERE id = ?", [(int)$z['cena'], (int)$m['id']]);
    db_q($db, "UPDATE katedra_zgloszenia SET status = 'zaakceptowane' WHERE id = ?", [$zid]);
    $dni = max(1, (int)floor((time() - strtotime($m['data_slubu'])) / 86400));
    $plotka = "Na mieście mówią, że <b>$la</b> i <b>$lb</b> rozwiedli się po $dni " . ($dni === 1 ? 'dniu' : 'dniach') . " małżeństwa.";
    kt_plotka($db, $a, $plotka); kt_plotka($db, $b, $plotka);
    $txt = "💔 Rozwód orzeczony. Nowy ślub możliwy za " . KT_KARENCJA_DNI . " dni.";
    powiadom($db, $a, $txt); powiadom($db, $b, $txt);
    return [true, 'Rozwód orzeczony.'];
}

}
