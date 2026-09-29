<?php
/* the-abyss/includes/katedra.php — śluby w Katedrze.
   1. Gracz składa oświadczyny innemu graczowi (oboje bez aktywnego małżeństwa i bez innego zgłoszenia w toku).
   2. Druga osoba przyjmuje albo odmawia.
   3. Po zgodzie obojga zgłoszenie czeka na Proboszcza (gracze.is_proboszcz). Proboszcz udziela ślubu albo odmawia z uzasadnieniem.
      Proboszcz nie decyduje o własnym ślubie — robi to inny Proboszcz.
   Każdy krok wysyła powiadomienie (nie pocztę). */
require_once __DIR__ . '/bezpieczne.php';
require_once __DIR__ . '/nick.php';

if (!function_exists('kt_malzenstwo')) {

function kt_malzenstwo(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM malzenstwa WHERE (malzonek_1_id = ? OR malzonek_2_id = ?) AND status = 'aktywne' LIMIT 1", [$gid, $gid]);
}
function kt_w_toku(mysqli $db, int $gid): ?array {
    return db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE (od_id = ? OR do_id = ?) AND status IN ('oswiadczyny','czeka_proboszcz') ORDER BY id DESC LIMIT 1", [$gid, $gid]);
}
function kt_proboszcz(mysqli $db, int $gid): bool {
    return !empty(db_wiersz($db, "SELECT is_proboszcz FROM gracze WHERE id = ?", [$gid])['is_proboszcz']);
}
function kt_login(mysqli $db, int $gid): string {
    return (string)(db_wiersz($db, "SELECT login FROM gracze WHERE id = ?", [$gid])['login'] ?? '?');
}

/** Oświadczyny. Zwraca [ok, komunikat]. */
function kt_oswiadcz(mysqli $db, int $ja, string $login, string $slowa): array {
    $cel = db_wiersz($db, "SELECT id, login FROM gracze WHERE login = ?", [trim($login)]);
    if (!$cel) return [false, 'Nie ma obywatela o takim nicku.'];
    $on = (int)$cel['id'];
    if ($on === $ja) return [false, 'Nie możesz wziąć ślubu sam ze sobą.'];
    if (kt_malzenstwo($db, $ja)) return [false, 'Jesteś już w związku małżeńskim.'];
    if (kt_malzenstwo($db, $on)) return [false, nk_h($cel['login']) . ' jest już w związku małżeńskim.'];
    if (kt_w_toku($db, $ja)) return [false, 'Masz już zgłoszenie w toku.'];
    if (kt_w_toku($db, $on)) return [false, nk_h($cel['login']) . ' ma już inne zgłoszenie w toku.'];
    $slowa = trim(mb_substr(strip_tags($slowa), 0, 400));
    db_q($db, "INSERT INTO katedra_zgloszenia (od_id, do_id, slowa) VALUES (?, ?, ?)", [$ja, $on, $slowa]);
    powiadom($db, $on, "💍 <b>" . nk_h(kt_login($db, $ja)) . "</b> prosi Cię o rękę. Odpowiedz w <a href='game.php?page=katedra'>Katedrze</a>.");
    return [true, 'Oświadczyny złożone. ' . nk_h($cel['login']) . ' dostanie powiadomienie.'];
}

/** Odpowiedź drugiej osoby: tak → czeka na Proboszcza, nie → odmowa. */
function kt_odpowiedz(mysqli $db, int $ja, int $zid, bool $tak): array {
    $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND do_id = ? AND status = 'oswiadczyny'", [$zid, $ja]);
    if (!$z) return [false, 'To zgłoszenie jest już nieaktualne.'];
    if ($tak && (kt_malzenstwo($db, $ja) || kt_malzenstwo($db, (int)$z['od_id']))) return [false, 'Któreś z was jest już w związku małżeńskim.'];
    $nowy = $tak ? 'czeka_proboszcz' : 'odmowa';
    if (db_zmien($db, "UPDATE katedra_zgloszenia SET status = ?, decyzja_o = IF(? = 'odmowa', NOW(), NULL) WHERE id = ? AND status = 'oswiadczyny'", [$nowy, $nowy, $zid]) !== 1) return [false, 'To zgłoszenie jest już nieaktualne.'];
    $moj = nk_h(kt_login($db, $ja));
    if (!$tak) {
        powiadom($db, (int)$z['od_id'], "<b>$moj</b> nie przyjmuje oświadczyn.");
        return [true, 'Odmówiłaś/eś.'];
    }
    powiadom($db, (int)$z['od_id'], "💍 <b>$moj</b> mówi „tak”. Zgłoszenie czeka na Proboszcza w <a href='game.php?page=katedra'>Katedrze</a>.");
    $para = nk_h(kt_login($db, (int)$z['od_id'])) . ' i ' . $moj;
    foreach (db_wiersze($db, "SELECT id FROM gracze WHERE is_proboszcz = 1 AND id NOT IN (?, ?)", [(int)$z['od_id'], $ja]) as $p)
        powiadom($db, (int)$p['id'], "⛪ $para proszą o ślub. <a href='game.php?page=katedra'>Katedra</a>");
    return [true, 'Zgoda złożona. Teraz decyzja należy do Proboszcza.'];
}

function kt_wycofaj(mysqli $db, int $ja, int $zid): array {
    $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND (od_id = ? OR do_id = ?) AND status IN ('oswiadczyny','czeka_proboszcz')", [$zid, $ja, $ja]);
    if (!$z || db_zmien($db, "UPDATE katedra_zgloszenia SET status = 'wycofane', decyzja_o = NOW() WHERE id = ? AND status IN ('oswiadczyny','czeka_proboszcz')", [$zid]) !== 1) return [false, 'To zgłoszenie jest już nieaktualne.'];
    $drugi = (int)$z['od_id'] === $ja ? (int)$z['do_id'] : (int)$z['od_id'];
    powiadom($db, $drugi, "<b>" . nk_h(kt_login($db, $ja)) . "</b> wycofuje zgłoszenie ślubu.");
    return [true, 'Zgłoszenie wycofane.'];
}

/** Decyzja Proboszcza. */
function kt_decyzja(mysqli $db, int $ks, int $zid, bool $tak, string $uwaga): array {
    if (!kt_proboszcz($db, $ks)) return [false, 'Tylko Proboszcz udziela ślubów.'];
    $uwaga = trim(mb_substr(strip_tags($uwaga), 0, 400));
    if (!$tak && $uwaga === '') return [false, 'Podaj powód odmowy.'];
    return db_tx($db, function () use ($db, $ks, $zid, $tak, $uwaga) {
        $z = db_wiersz($db, "SELECT * FROM katedra_zgloszenia WHERE id = ? AND status = 'czeka_proboszcz' FOR UPDATE", [$zid]);
        if (!$z) return [false, 'To zgłoszenie jest już rozpatrzone.'];
        $a = (int)$z['od_id']; $b = (int)$z['do_id'];
        if ($ks === $a || $ks === $b) return [false, 'O własnym ślubie decyduje inny Proboszcz.'];
        $para = nk_h(kt_login($db, $a)) . ' i ' . nk_h(kt_login($db, $b));
        $ksl = nk_h(kt_login($db, $ks));
        if ($tak) {
            if (kt_malzenstwo($db, $a) || kt_malzenstwo($db, $b)) return [false, 'Któreś z nich jest już w związku małżeńskim.'];
            db_q($db, "INSERT INTO malzenstwa (malzonek_1_id, malzonek_2_id, status, data_slubu, proboszcz_id) VALUES (?, ?, 'aktywne', NOW(), ?)", [$a, $b, $ks]);
            db_q($db, "UPDATE katedra_zgloszenia SET status = 'zaakceptowane', proboszcz_id = ?, uwaga = ?, decyzja_o = NOW() WHERE id = ?", [$ks, $uwaga, $zid]);
            $txt = "💍 Proboszcz <b>$ksl</b> udzielił wam ślubu. Od dziś jesteście małżeństwem." . ($uwaga !== '' ? ' „' . nk_h($uwaga) . '”' : '');
            powiadom($db, $a, $txt); powiadom($db, $b, $txt);
            return [true, "Udzielono ślubu: $para."];
        }
        db_q($db, "UPDATE katedra_zgloszenia SET status = 'odrzucone', proboszcz_id = ?, uwaga = ?, decyzja_o = NOW() WHERE id = ?", [$ks, $uwaga, $zid]);
        $txt = "Proboszcz <b>$ksl</b> odmówił udzielenia ślubu. Powód: " . nk_h($uwaga);
        powiadom($db, $a, $txt); powiadom($db, $b, $txt);
        return [true, "Odmówiono: $para."];
    });
}

}
