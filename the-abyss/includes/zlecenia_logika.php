<?php
/* the-abyss/includes/zlecenia_logika.php — Tablica Zleceń: test, obława, areszt, reputacja, skoki, nagrody za głowę.
   Stałe i treść: config/zleceniodawcy.php + config/zlecenia_*.php. Tabele: db/migracja_zlecenia.sql.
   Każda funkcja zmieniająca stan zwraca [ok, komunikat]. */
require_once __DIR__ . '/bezpieczne.php';
require_once __DIR__ . '/bronie_katalog.php';
require_once __DIR__ . '/warsztat_logika.php';     // eq_dodaj()
require_once __DIR__ . '/../config/zleceniodawcy.php';

function zl_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function zl_fmt(int $n): string { return number_format($n, 0, '', ' '); }

/* ── gracz, obława, blokada ─────────────────────────────────────────── */

/** Wiersz gracza z przeliczoną obławą (spada co ZL_SPADEK_H godzin) i zdjętą wygasłą blokadą. */
function zl_gracz(mysqli $db, int $gid): array {
    $g = db_wiersz($db, "SELECT id, login, poziom, exp, gotowka, klasa, umiejetnosci, obecne_miasto, is_premium, syndykat_id,
                                bonus_obrona, bron_zalozona, zl_oblawa, zl_oblawa_od, zl_blokada_do, zl_blokada_typ
                         FROM gracze WHERE id = ?", [$gid]) ?? [];
    if (!$g) return [];
    $ob = (int)$g['zl_oblawa'];
    if ($ob > 0 && $g['zl_oblawa_od']) {
        $spadek = intdiv(max(0, time() - strtotime($g['zl_oblawa_od'])), ZL_SPADEK_H * 3600);
        if ($spadek > 0) {
            $ob = max(0, $ob - $spadek);
            db_q($db, "UPDATE gracze SET zl_oblawa = ?, zl_oblawa_od = NOW() WHERE id = ?", [$ob, $gid]);
        }
    }
    $g['zl_oblawa'] = $ob;
    if ($g['zl_blokada_do'] && strtotime($g['zl_blokada_do']) <= time()) {
        db_q($db, "UPDATE gracze SET zl_blokada_do = NULL, zl_blokada_typ = NULL WHERE id = ?", [$gid]);
        $g['zl_blokada_do'] = $g['zl_blokada_typ'] = null;
    }
    return $g;
}

function zl_oblawa_ustaw(mysqli $db, int $gid, int $ob): int {
    $ob = max(0, min(5, $ob));
    db_q($db, "UPDATE gracze SET zl_oblawa = ?, zl_oblawa_od = NOW() WHERE id = ?", [$ob, $gid]);
    return $ob;
}

/** Komunikat blokady (areszt / przyczajenie) albo ''. Używają też pages/doki.php i walka_pvp.php. */
function zl_blokada(mysqli $db, int $gid): string {
    $b = db_wiersz($db, "SELECT zl_blokada_do d, zl_blokada_typ t FROM gracze WHERE id = ?", [$gid]);
    if (!$b || !$b['d'] || strtotime($b['d']) <= time()) return '';
    $do = date('H:i', strtotime($b['d']));
    return $b['t'] === 'areszt' ? "Siedzisz w areszcie do $do. Zlecenia i walka poczekają." : "Przyczaiłeś się do $do. Żadnych zleceń i żadnej walki.";
}

/** [wykorzystane dziś, limit]. Skok liczy się jako jedno zlecenie. */
function zl_limit(mysqli $db, array $g): array {
    $n = (int)(db_wiersz($db, "SELECT COUNT(*) c FROM zl_dziennik WHERE gracz_id = ? AND kiedy >= CURDATE()", [(int)$g['id']])['c'] ?? 0);
    return [$n, !empty($g['is_premium']) ? ZL_LIMIT_PREMIUM : ZL_LIMIT];
}

function zl_wiesc(mysqli $db, string $region, string $tresc): void {
    db_q($db, "INSERT INTO zl_wiesci (region, tresc) VALUES (?, ?)", [$region, mb_substr($tresc, 0, 400)]);
}

/* ── reputacja ──────────────────────────────────────────────────────── */

function zl_rep(mysqli $db, int $gid): array {
    $o = [];
    foreach (db_wiersze($db, "SELECT npc, punkty FROM zl_reputacja WHERE gracz_id = ?", [$gid]) as $r) $o[$r['npc']] = (int)$r['punkty'];
    return $o;
}
function zl_tier(int $pkt): int { $t = 0; foreach (ZL_PROGI as $i => $p) if ($pkt >= $p) $t = $i; return $t; }

/** Zmienia reputację; przy plusie rywal NPC traci 1. Na progu Rodzina — jednorazowy dar (broń). */
function zl_rep_dodaj(mysqli $db, array $g, string $region, string $npc, int $d): string {
    $gid = (int)$g['id']; $dane = zl_dane($region); $info = '';
    db_q($db, "INSERT INTO zl_reputacja (gracz_id, npc, punkty) VALUES (?, ?, GREATEST(0, ?)) ON DUPLICATE KEY UPDATE punkty = GREATEST(0, punkty + ?)", [$gid, $npc, $d, $d]);
    if ($d > 0 && ($w = $dane['npc'][$npc]['wrog'] ?? null)) {
        db_q($db, "UPDATE zl_reputacja SET punkty = GREATEST(0, punkty - 1) WHERE gracz_id = ? AND npc = ?", [$gid, $w]);
        $info .= ' ' . zl_h($dane['npc'][$w]['n'] ?? $w) . ' się o tym dowie (−1 reputacji).';
    }
    $r = db_wiersz($db, "SELECT punkty, dar_odebrany FROM zl_reputacja WHERE gracz_id = ? AND npc = ?", [$gid, $npc]);
    if ($r && (int)$r['punkty'] >= ZL_PROGI[4] && !(int)$r['dar_odebrany']
        && db_zmien($db, "UPDATE zl_reputacja SET dar_odebrany = 1 WHERE gracz_id = ? AND npc = ? AND dar_odebrany = 0", [$gid, $npc]) === 1) {
        $cios = $dane['npc'][$npc]['dar'] ?? 'palna'; $best = null; $bk = null;
        foreach (bronie_katalog() as $k => $b) if ($b['cios'] === $cios && $b['poziom'] <= (int)$g['poziom'] + 5 && (!$best || $b['poziom'] > $best['poziom'])) { $best = $b; $bk = $k; }
        if ($bk) {
            eq_dodaj($db, $gid, $bk, 1);
            $info .= ' Jesteś teraz Rodziną. ' . zl_h($dane['npc'][$npc]['n']) . ' daje ci prezent: <b>' . zl_h($best['nazwa']) . '</b>.';
            powiadom($db, $gid, 'Prezent od ' . zl_h($dane['npc'][$npc]['n']) . ': <b>' . zl_h($best['nazwa']) . '</b>. Leży w ekwipunku.');
        }
    }
    return $info;
}

/* ── test ───────────────────────────────────────────────────────────── */

function zl_um(array $g, string $nazwa): int {
    $um = !empty($g['umiejetnosci']) ? (json_decode($g['umiejetnosci'], true) ?: []) : [];
    return min(5, (int)($um[$nazwa] ?? 0));
}

function zl_sprzet_ok(array $g, ?array $s): bool {
    if (!$s) return false;
    if (isset($s['pancerz'])) return (int)$g['bonus_obrona'] >= (int)$s['pancerz'];
    if (isset($s['cios'])) foreach (bronie_katalog() as $b) if ($b['nazwa'] === ($g['bron_zalozona'] ?? '')) return $b['cios'] === $s['cios'];
    return false;
}
function zl_sprzet_opis(?array $s): string {
    if (!$s) return '';
    if (isset($s['pancerz'])) return 'pancerz, obrona ' . (int)$s['pancerz'] . '+';
    return ['ostrze' => 'ostrze w ręku', 'tepe' => 'tępe narzędzie w ręku', 'palna' => 'broń palna w ręku'][$s['cios']] ?? '';
}

/** Rozpisana szansa: ['s' => %, 'czesci' => [[etykieta, wartość], …]]. */
function zl_szansa(array $g, array $d, int $ryz, int $premia = 0): array {
    $cz = [['Trudność', (int)$d['tr']]];
    $kl = $d['kl'] ?? null;
    $cz[] = ['Klasa: ' . ($kl ?? 'każda'), (!$kl || $kl === $g['klasa']) ? 15 : 0];
    $p = zl_um($g, $d['um']);
    $cz[] = [$d['um'] . " (poz. $p)", min(20, $p * 4)];
    if (!empty($d['sprz'])) $cz[] = ['Sprzęt: ' . zl_sprzet_opis($d['sprz']), zl_sprzet_ok($g, $d['sprz']) ? 10 : 0];
    if ($premia) $cz[] = ['Z poprzedniego etapu', $premia];
    $cz[] = ['Ryzyko: ' . ZL_RYZYKO[$ryz]['n'], ZL_RYZYKO[$ryz]['s']];
    $ob = (int)$g['zl_oblawa'];
    if ($ob) $cz[] = ["Obława $ob★", -5 * $ob];
    return ['s' => max(5, min(95, array_sum(array_column($cz, 1)))), 'czesci' => $cz];
}

function zl_wynik(int $rzut, int $s): string { return $rzut <= $s ? 'sukces' : ($rzut <= $s + 10 ? 'polowiczny' : 'porazka'); }
function zl_mn(array $g): float { return 0.25 + (int)$g['poziom'] * 0.05; }
function zl_kasa(array $g, int $baza, float $m = 1.0): int { return (int)(round($baza * zl_mn($g) * $m / 10) * 10); }
function zl_xp(array $g, int $proc, float $m = 1.0): int { return max(1, (int)round((int)$g['poziom'] * 100 * $proc / 100 * $m)); }

/** Areszt: 10% gotówki (min. 500), 2–6 h bez zleceń i walki, obława spada do 2. */
function zl_areszt(mysqli $db, array $g, string $region, int $ob): string {
    $gid = (int)$g['id'];
    $kara = min((int)$g['gotowka'], max(500, (int)round((int)$g['gotowka'] * 0.1)));
    $h = $ob >= 5 ? 6 : ($ob >= 4 ? 4 : 2);
    db_q($db, "UPDATE gracze SET gotowka = GREATEST(0, gotowka - ?), zl_blokada_do = NOW() + INTERVAL ? HOUR, zl_blokada_typ = 'areszt', zl_oblawa = 2, zl_oblawa_od = NOW() WHERE id = ?", [$kara, $h, $gid]);
    zl_wiesc($db, $region, 'Policja zatrzymała <b>' . zl_h($g['login']) . "</b>. Wyjdzie za $h godz.");
    powiadom($db, $gid, "Areszt: $h godz. bez zleceń i walki. Kara: " . zl_fmt($kara) . ' $.');
    return " <b>Areszt</b>: $h godz. bez zleceń i walki, kara " . zl_fmt($kara) . ' $.';
}

/** Czy porażka kończy się aresztem (obława po zmianie; przy ucieczce ze skoku także niższa obława). */
function zl_czy_areszt(int $ob, bool $ucieczka = false): bool {
    if ($ob >= 5) return true;
    if ($ob >= 4) return rand(1, 100) <= 50;
    return $ucieczka && rand(1, 100) <= 35;
}

/* ── kontrakty ──────────────────────────────────────────────────────── */

/** Dlaczego kontrakt jest niedostępny ('' = dostępny). */
function zl_kontrakt_blok(mysqli $db, array $g, array $d, array $rep, string $kod): string {
    if ((int)$g['poziom'] < (int)$d['lvl']) return 'Od poziomu ' . (int)$d['lvl'];
    if (zl_tier($rep[$d['npc']] ?? 0) < (int)$d['rep']) return 'Reputacja: ' . ZL_RANGI[(int)$d['rep']];
    $ob = (int)$g['zl_oblawa'];
    if ($ob >= 5) return 'Obława: tylko przyczajenie';
    if ($ob >= 4 && !empty($d['gl'])) return 'List gończy: tylko ciche';
    $ost = db_wiersz($db, "SELECT kiedy FROM zl_dziennik WHERE gracz_id = ? AND kod = ? ORDER BY id DESC LIMIT 1", [(int)$g['id'], $kod]);
    if ($ost && strtotime($ost['kiedy']) > time() - ZL_ODNOWA_H * 3600) return 'Znów za ' . max(1, (int)ceil((strtotime($ost['kiedy']) + ZL_ODNOWA_H * 3600 - time()) / 3600)) . ' h';
    return '';
}

function zl_kontrakt_przyjmij(mysqli $db, array $g, string $region, string $kod, int $ryz): array {
    $dane = zl_dane($region); $d = $dane['kontrakty'][$kod] ?? null;
    if (!$d) return [false, 'Nie ma takiego zlecenia.'];
    if ($b = zl_blokada($db, (int)$g['id'])) return [false, $b];
    $ryz = max(0, min(2, $ryz));
    [$uz, $lim] = zl_limit($db, $g);
    if ($uz >= $lim) return [false, "Na dziś wystarczy: $uz / $lim zleceń."];
    if ($w = zl_kontrakt_blok($db, $g, $d, zl_rep($db, (int)$g['id']), $kod)) return [false, $w];
    if (db_wiersz($db, "SELECT id FROM zl_dziennik WHERE gracz_id = ? AND rodzaj = 'kontrakt' AND rozliczone = 0", [(int)$g['id']])) return [false, 'Najpierw dokończ zlecenie, które już masz w toku.'];
    $s = zl_szansa($g, $d, $ryz)['s']; $rzut = rand(1, 100); $cz = (int)$d['cz'];
    db_q($db, "INSERT INTO zl_dziennik (gracz_id, rodzaj, kod, npc, ryzyko, szansa, rzut, gotowe_o) VALUES (?, 'kontrakt', ?, ?, ?, ?, ?, NOW() + INTERVAL ? MINUTE)",
         [(int)$g['id'], $kod, $d['npc'], $ryz, $s, $rzut, $cz]);
    $id = (int)$db->insert_id;
    zl_oblawa_ustaw($db, (int)$g['id'], (int)$g['zl_oblawa']);      // nowe zlecenie zatrzymuje spadek obławy
    if ($cz <= 0) return zl_kontrakt_rozlicz($db, zl_gracz($db, (int)$g['id']), $region, $id);
    return [true, '<b>' . zl_h($d['t']) . "</b>: robota trwa $cz min. Wróć po wynik."];
}

function zl_kontrakt_rozlicz(mysqli $db, array $g, string $region, int $id): array {
    $gid = (int)$g['id'];
    $z = db_wiersz($db, "SELECT * FROM zl_dziennik WHERE id = ? AND gracz_id = ? AND rodzaj = 'kontrakt' AND rozliczone = 0", [$id, $gid]);
    if (!$z) return [false, 'Nie ma takiego zlecenia w toku.'];
    if (strtotime($z['gotowe_o']) > time()) return [false, 'Jeszcze nie skończyłeś.'];
    if (db_zmien($db, "UPDATE zl_dziennik SET rozliczone = 1 WHERE id = ? AND rozliczone = 0", [$id]) !== 1) return [false, 'To zlecenie jest już rozliczone.'];
    $dane = zl_dane($region); $d = $dane['kontrakty'][$z['kod']] ?? null;
    if (!$d) return [false, 'Zlecenie wypadło z tablicy.'];
    $ryz = (int)$z['ryzyko']; $m = ZL_RYZYKO[$ryz]['m']; $npc = $dane['npc'][$d['npc']]['n'];
    $wynik = zl_wynik((int)$z['rzut'], (int)$z['szansa']);
    $tier = zl_tier(zl_rep($db, $gid)[$d['npc']] ?? 0);
    $mR = $m * ($tier >= 4 ? 1.25 : 1);
    $kasa = $wynik === 'sukces' ? zl_kasa($g, (int)$d['kasa'], $mR) : ($wynik === 'polowiczny' ? zl_kasa($g, (int)$d['kasa'], $mR / 2) : 0);
    $xp   = $wynik === 'sukces' ? zl_xp($g, (int)$d['xp'], $m) : ($wynik === 'polowiczny' ? zl_xp($g, (int)$d['xp'], $m / 2) : zl_xp($g, (int)$d['xp'], 0.15));
    $ob = (int)$g['zl_oblawa'];
    $dOb = $wynik === 'sukces' ? ($ryz === 2 ? 1 : 0) : ($wynik === 'polowiczny' ? 1 : ($ryz === 2 ? 2 : 1));
    $ob = zl_oblawa_ustaw($db, $gid, $ob + $dOb);
    db_q($db, "UPDATE gracze SET gotowka = gotowka + ?, exp = exp + ? WHERE id = ?", [$kasa, $xp, $gid]);
    if ($wynik === 'sukces' && !empty($d['mat'])) foreach ($d['mat'] as $kol => $ile)
        if (in_array($kol, ['zlom_stalowy', 'czesci_mechaniczne', 'syntetyki', 'elektronika'], true)) db_q($db, "UPDATE gracze SET `$kol` = `$kol` + ? WHERE id = ?", [(int)$ile, $gid]);
    db_q($db, "UPDATE zl_dziennik SET wynik = ?, nagroda = ?, exp = ? WHERE id = ?", [$wynik, $kasa, $xp, $id]);

    $txt = ['sukces' => 'Czysta robota.', 'polowiczny' => 'Udało się tylko w połowie.', 'porazka' => 'Nie wyszło.'][$wynik]
         . ' <b>' . zl_h($d['t']) . '</b>: ' . ($kasa ? '+' . zl_fmt($kasa) . ' $, ' : '') . "+$xp XP" . ($dOb ? ", obława +$dOb" : '') . '.';
    if ($wynik === 'sukces') $txt .= zl_rep_dodaj($db, $g, $region, $d['npc'], $ryz === 2 ? 2 : 1);
    if ($wynik === 'porazka') { zl_rep_dodaj($db, $g, $region, $d['npc'], -1); $txt .= ' ' . zl_h($npc) . ' zapamięta (−1 reputacji).'; }
    if ($wynik === 'porazka' && zl_czy_areszt($ob)) $txt .= zl_areszt($db, zl_gracz($db, $gid), $region, $ob);
    if (!empty($d['gl']) && $wynik !== 'porazka') zl_wiesc($db, $region, 'Ktoś odrobił „' . zl_h($d['t']) . '” dla ' . zl_h($npc) . '. Na ulicy mówi się o tym od rana.');
    return [$wynik !== 'porazka', $txt];
}

/* ── obniżanie obławy ───────────────────────────────────────────────── */

function zl_lapowka_koszt(array $g): int { return max(500, (int)round(1500 * (int)$g['zl_oblawa'] * (int)$g['poziom'] / 10)); }

function zl_lapowka(mysqli $db, array $g): array {
    if ((int)$g['zl_oblawa'] < 1) return [false, 'Nikt cię nie szuka.'];
    $k = zl_lapowka_koszt($g);
    if (!kasa_pobierz($db, (int)$g['id'], $k)) return [false, 'Łapówka kosztuje ' . zl_fmt($k) . ' $.'];
    zl_oblawa_ustaw($db, (int)$g['id'], (int)$g['zl_oblawa'] - 1);
    return [true, 'Ktoś na komisariacie zgubił twoje zdjęcie. Obława −1 (' . zl_fmt($k) . ' $).'];
}

function zl_adwokat(mysqli $db, array $g, string $region, string $npc): array {
    $n = zl_dane($region)['npc'][$npc] ?? null;
    if (!$n || ($n['usluga'] ?? '') !== 'adwokat') return [false, 'Ten ktoś nie jest adwokatem.'];
    if (zl_tier(zl_rep($db, (int)$g['id'])[$npc] ?? 0) < 3) return [false, zl_h($n['n']) . ' pomaga tylko swojej prawej ręce.'];
    if ((int)$g['zl_oblawa'] < 1) return [false, 'Nikt cię nie szuka.'];
    if (db_zmien($db, "UPDATE zl_reputacja SET usluga_kiedy = NOW() WHERE gracz_id = ? AND npc = ? AND (usluga_kiedy IS NULL OR usluga_kiedy < NOW() - INTERVAL 24 HOUR)", [(int)$g['id'], $npc]) !== 1)
        return [false, zl_h($n['n']) . ' pomaga raz na dobę.'];
    zl_oblawa_ustaw($db, (int)$g['id'], (int)$g['zl_oblawa'] - 2);
    return [true, zl_h($n['n']) . ' wykonał kilka telefonów. Obława −2.'];
}

function zl_przyczaj(mysqli $db, array $g): array {
    if ((int)$g['zl_oblawa'] < 1) return [false, 'Nikt cię nie szuka.'];
    if (zl_blokada($db, (int)$g['id'])) return [false, 'Już siedzisz cicho.'];
    db_q($db, "UPDATE gracze SET zl_blokada_do = NOW() + INTERVAL 8 HOUR, zl_blokada_typ = 'przyczajenie', zl_oblawa = GREATEST(0, zl_oblawa - 2), zl_oblawa_od = NOW() WHERE id = ?", [(int)$g['id']]);
    return [true, 'Znikasz na 8 godzin. Obława −2. Przez ten czas żadnych zleceń i walki.'];
}

require_once __DIR__ . '/zlecenia_skoki.php';
