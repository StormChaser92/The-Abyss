<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — INCLUDES/PANEL_MG.PHP
   Kulisy MG w Pokoju Sesji: testy k100, walka (tracker), NPC, ukryte rzuty.
   Każdy rzut prowadzącego trafia najpierw do podglądu (sesja PHP),
   potem: Opublikuj / Ukryty rzut / Odrzuć.
   W Opowieściach Swobodnych gracze rzucają sami na swoją postać —
   od razu do Opowieści, bez podglądu. Tam też działa „Zgłoś MG”.
   Wpięcie w pages/pokoj_sesji.php: pm_obsluz(), pm_pasek(), pm_render(),
   pm_przycisk_zgloszenia().
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/rp_walka.php';
require_once __DIR__ . '/bezpieczne.php';

const PM_RYZYKO = [10 => 'Niskie +10', 0 => 'Średnie 0', -10 => 'Wysokie −10', -20 => 'Ekstr. −20'];
const PM_KOSCI  = [4, 6, 8, 10, 12, 20, 100];

function pm_prowadzacy(array $s, bool $czy_mg): bool { return $czy_mg && ($s['poziom'] ?? '') !== 'swobodna'; }
function pm_sam(array $s, bool $czy_mg, bool $zaakc): bool { return $zaakc && ($s['poziom'] ?? '') === 'swobodna'; }
function pm_cechy(?string $s): array { return (!$s || $s === 'Brak') ? [] : array_values(array_filter(array_map('trim', explode(',', $s)), 'strlen')); }
/** Powrót po akcji. Na Sali Głównej Klubu czat.php ustawia $GLOBALS['PM_POWROT']. */
function pm_wroc(int $sid, string $tab = ''): void {
    $baza = $GLOBALS['PM_POWROT'] ?? "game.php?page=pokoj_sesji&id=$sid";
    echo "<script>location.href=" . json_encode($baza . ($tab ? "&pm=$tab" : '')) . ";</script>"; exit;
}
function pm_klub(mysqli $db, int $sid): bool { return (db_wiersz($db, "SELECT typ_opowiesci FROM sesje_rpg WHERE id = ?", [$sid])['typ_opowiesci'] ?? '') === 'klub'; }

/** Gracze Opowieści: zaakceptowani (rola Gracz), a w Wydarzeniu w Klubie także goście obecni na Sali Głównej. */
function pm_gracze(mysqli $db, int $sid): array {
    $l = [];
    foreach (db_wiersze($db, "SELECT g.* FROM sesje_uczestnicy u JOIN gracze g ON g.id = u.gracz_id WHERE u.sesja_id = ? AND u.rola = 'Gracz' AND u.status_akceptacji = 'Zaakceptowany' ORDER BY g.login", [$sid]) as $g) $l[(int)$g['id']] = $g;
    if (pm_klub($db, $sid)) {
        $mg = db_wiersze($db, "SELECT gracz_id FROM sesje_uczestnicy WHERE sesja_id = ? AND rola = 'Mistrz Gry'", [$sid]);
        $bez = array_map(fn($r) => (int)$r['gracz_id'], $mg); $bez[] = (int)(db_wiersz($db, "SELECT mg_id FROM sesje_rpg WHERE id = ?", [$sid])['mg_id'] ?? 0);
        foreach (db_wiersze($db, "SELECT * FROM gracze WHERE klub_sala = 'sala-glowna' AND ostatnia_aktywnosc >= NOW() - INTERVAL 5 MINUTE ORDER BY login") as $g)
            if (!in_array((int)$g['id'], $bez, true)) $l[(int)$g['id']] = $g;
    }
    return $l;
}

/** Cele testów: gracze Opowieści + NPC. */
function pm_cele(mysqli $db, int $sid): array {
    $c = [];
    foreach (pm_gracze($db, $sid) as $g)
        $c['g' . $g['id']] = ['nazwa' => $g['login'], 'row' => $g, 'npc' => false, 'zal' => pm_cechy($g['zalety'] ?? ''), 'wad' => pm_cechy($g['wady'] ?? '')];
    foreach (db_wiersze($db, "SELECT * FROM sesje_npc WHERE sesja_id = ? ORDER BY nazwa", [$sid]) as $n)
        $c['n' . $n['id']] = ['nazwa' => $n['nazwa'], 'row' => $n, 'npc' => true, 'zal' => pm_cechy($n['zalety']), 'wad' => pm_cechy($n['wady'])];
    return $c;
}

function pm_publikuj(mysqli $db, int $sid, int $autor, string $html): void {
    db_zmien($db, "INSERT INTO sesje_posty (sesja_id, autor_id, typ_postu, tresc) VALUES (?, ?, 'Rzut_Koscia', ?)", [$sid, $autor, $html]);
    db_zmien($db, "UPDATE sesje_rpg SET ostatnia_aktywnosc = NOW() WHERE id = ?", [$sid]);
    // Wydarzenie w Klubie: ten sam rzut na czacie Sali Głównej (typ 'system' — klub.js wstawia HTML).
    if (pm_klub($db, $sid)) db_zmien($db, "INSERT INTO czat (id_gracza, login, tresc, sala, typ) VALUES (0, 'system', ?, 'sala-glowna', 'system')", [$html]);
}

/** Zmiany po rzucie: walka (rw_zastosuj) i punkty testów złożonych. */
function pm_ops(mysqli $db, int $sid, array $ops): void {
    $walka = [];
    foreach ($ops as $o) {
        if ($o[0] !== 'zl') { $walka[] = $o; continue; }
        db_zmien($db, "UPDATE sesje_test_zlozony SET suma = GREATEST(0, suma + ?) WHERE id = ? AND sesja_id = ? AND status = 'trwa'", [(int)$o[2], (int)$o[1], $sid]);
        db_zmien($db, "UPDATE sesje_test_zlozony SET status = 'sukces' WHERE id = ? AND status = 'trwa' AND suma >= cel", [(int)$o[1]]);
    }
    if ($walka) rw_zastosuj($db, $sid, $walka);
}

/* ── TESTY ────────────────────────────────────────────────────────── */
function pm_test(array $cele, array $p, ?string $lock, ?mysqli $db = null, int $sid = 0): array {
    global $UM_ATRYBUTY;
    $rodz = (string)($p['rodzaj'] ?? 'prosty');
    $ck = $lock ?? (string)($p['cel'] ?? '');
    if (!in_array($rodz, ['prosty', 'atr', 'srednia', 'zdol', 'przec', 'zloz', 'praw', 'kosc'], true)) $rodz = 'prosty';
    $bez_celu = in_array($rodz, ['praw', 'kosc'], true);
    if (!$bez_celu && !isset($cele[$ck])) return ['blad' => 'Wybierz postać albo NPC.'];
    $C = $cele[$ck] ?? null;
    $ryz = (int)($p['ryz'] ?? 0); if (!isset(PM_RYZYKO[$ryz])) $ryz = 0;
    $mod_mg = $lock ? 0 : max(-50, min(50, (int)($p['mod'] ?? 0)));
    $cm = 0; $co = [];
    if ($C) foreach ((array)($p['cechy'][$ck] ?? []) as $n) {
        $n = (string)$n;
        if (in_array($n, $C['zal'], true)) { $cm += UM_MOD_CECHA; $co[] = "$n +" . UM_MOD_CECHA; }
        elseif (in_array($n, $C['wad'], true)) { $cm -= UM_MOD_CECHA; $co[] = "$n −" . UM_MOD_CECHA; }
    }
    $m = $ryz + $cm + $mod_mg;
    $sd = trim(mb_substr((string)($p['sd'] ?? ''), 0, 200));
    $h = ''; $sum = ''; $skrot = ''; $ops = [];

    if (in_array($rodz, ['prosty', 'atr', 'srednia', 'zdol'], true)) {
        if ($rodz === 'prosty') {
            $um = (string)($p['um'] ?? ''); if (!um_definicja($um)) return ['blad' => 'Wybierz Umiejętność.'];
            $t = um_test($C['row'], $um, ($p['ag'] ?? 'g') === 'd' ? 'd' : 'g', $m); $nz = "$um (poz. {$t['poziom']})"; $lb = 'Test Umiejętności'; $opis = um_opis_testu($t);
        } elseif ($rodz === 'atr') {
            $at = (string)($p['at'] ?? 'Z'); if (!isset($UM_ATRYBUTY[$at])) $at = 'Z';
            $t = um_test_atrybutu($C['row'], $at, $m); $nz = $UM_ATRYBUTY[$at]['nazwa']; $lb = 'Test Atrybutu'; $opis = um_opis_testu($t);
        } else {
            // Test średniej Atrybutów (min. 2) / Test Zdolności („Możliwości”): średnia wybranych Atrybutów, limit PE
            $ak = array_values(array_unique(array_filter(array_map('strval', (array)($p['ats'] ?? [])), fn($k) => isset($UM_ATRYBUTY[$k]))));
            if ($rodz === 'srednia' && count($ak) < 2) return ['blad' => 'Test średniej wymaga co najmniej 2 Atrybutów.'];
            if (!$ak) return ['blad' => 'Wybierz Atrybut do testu Zdolności.'];
            $sr = intdiv(array_sum(array_map(fn($k) => um_atrybut($C['row'], $k), $ak)), count($ak));
            $nz = implode(' + ', array_map(fn($k) => $UM_ATRYBUTY[$k]['nazwa'], $ak));
            $opis = (count($ak) > 1 ? "średnia $sr" : "$nz $sr") . ($m ? ' ' . rw_sgn($m) : '');
            $lb = $rodz === 'srednia' ? 'Test średniej Atrybutów' : 'Test Zdolności';
            if ($rodz === 'zdol') {
                $zd = (string)($p['zd'] ?? '');
                if ($zd === '' || !in_array($zd, $C['zal'], true)) {
                    // Bez tej Zdolności rzutu się nie wykonuje — test jest automatycznie nieudany.
                    $h = "<div class='pmr-row'><div class='pmr-d'><b>—</b><small>brak</small></div><div class='pmr-o'><strong>" . rw_h($zd ?: 'Zdolność') . "</strong><span>postać nie ma tej Zdolności</span></div><span class='pmr-s s-1'>Automatyczna porażka</span></div>";
                    return ['html' => rw_karta($C['nazwa'], $lb, ($sd ? "<div class='pmr-sd'>" . rw_h($sd) . "</div>" : '') . $h), 'skrot' => "{$C['nazwa']}: brak Zdolności — automatyczna porażka", 'ops' => [], 'tytul' => "$lb · {$C['nazwa']}"];
                }
                $nz = "$zd · $nz";
            }
            $t = ['szansa' => rw_szansa($sr, $m)];
        }
        $ty = $C['nazwa'];
        if (!empty($p['auto']) && !$lock) {
            // Automatyczny sukces: na prośbę gracza w NC, gdy szansa ≥ 50%. Nie dotyczy walki.
            if ($t['szansa'] < 50) return ['blad' => "Automatyczny sukces wymaga szansy co najmniej 50% (jest {$t['szansa']}%)."];
            $h = "<div class='pmr-row'><div class='pmr-d'><b>—</b><small>/ {$t['szansa']}%</small></div><div class='pmr-o'><strong>" . rw_h($nz) . "</strong><span>" . rw_h($opis) . " · bez rzutu</span></div><span class='pmr-s s1'>Automatyczny sukces</span></div>";
            $skrot = "{$C['nazwa']} · $nz: automatyczny sukces ({$t['szansa']}%)";
        } else {
            $r = rw_k100(); $w = um_wynik($r, $t['szansa']);
            $h = rw_wiersz($nz, $r, $t['szansa'], $opis, $w); $skrot = "{$C['nazwa']} · $nz: $r / {$t['szansa']}% — {$w['nazwa']}";
        }
    } elseif ($rodz === 'przec') {
        $bk = (string)($p['cel_b'] ?? ''); if (!isset($cele[$bk]) || $bk === $ck) return ['blad' => 'Wybierz drugą stronę testu.'];
        $B = $cele[$bk]; $ua = (string)($p['um'] ?? ''); $ub = (string)($p['um_b'] ?? '');
        if (!um_definicja($ua) || !um_definicja($ub)) return ['blad' => 'Wybierz Umiejętności obu stron.'];
        $ta = um_test($C['row'], $ua, 'g', $m); $tb = um_test($B['row'], $ub, 'g', 0);
        $ra = rw_k100(); $rb = rw_k100(); $wa = um_wynik($ra, $ta['szansa']); $wb = um_wynik($rb, $tb['szansa']);
        $za = $ta['szansa'] - $ra; $zb = $tb['szansa'] - $rb;
        $win = $wa['poziom'] !== $wb['poziom'] ? ($wa['poziom'] > $wb['poziom'] ? $C['nazwa'] : $B['nazwa']) : ($za !== $zb ? ($za > $zb ? $C['nazwa'] : $B['nazwa']) : null);
        $h = rw_wiersz("{$C['nazwa']} · $ua", $ra, $ta['szansa'], um_opis_testu($ta), $wa) . rw_wiersz("{$B['nazwa']} · $ub", $rb, $tb['szansa'], um_opis_testu($tb), $wb);
        $sum = $win ? 'Wygrywa <em>' . rw_h($win) . '</em>' : 'Remis'; $ty = "{$C['nazwa']} vs {$B['nazwa']}"; $lb = 'Test przeciwstawny';
        $skrot = "{$C['nazwa']} $ra/{$ta['szansa']}% vs {$B['nazwa']} $rb/{$tb['szansa']}% — " . ($win ?: 'remis');
    } elseif ($rodz === 'zloz') {
        // Test złożony: seria testów rozłożona na tury, wyniki się sumują.
        // Krytyczny sukces 3, sukces 2, minimalny 1, porażka 0, krytyczna porażka −1.
        if (!$db) return ['blad' => 'Brak połączenia z bazą.'];
        $um = (string)($p['um'] ?? ''); if (!um_definicja($um)) return ['blad' => 'Wybierz Umiejętność.'];
        $zid = (int)($p['zl_id'] ?? 0);
        if ($zid) { $Z = db_wiersz($db, "SELECT * FROM sesje_test_zlozony WHERE id = ? AND sesja_id = ? AND status = 'trwa'", [$zid, $sid]); if (!$Z) return ['blad' => 'Ten test złożony już się zakończył.']; }
        else {
            $zn = trim(mb_substr((string)($p['zl_nazwa'] ?? ''), 0, 80)); if ($zn === '') return ['blad' => 'Podaj nazwę nowego testu złożonego.'];
            db_zmien($db, "INSERT INTO sesje_test_zlozony (sesja_id, nazwa, cel, tury) VALUES (?, ?, ?, ?)", [$sid, $zn, max(1, min(50, (int)($p['zl_cel'] ?? 10))), max(1, min(20, (int)($p['zl_tury'] ?? 3)))]);
            $Z = db_wiersz($db, "SELECT * FROM sesje_test_zlozony WHERE id = ?", [(int)$db->insert_id]);
        }
        $t = um_test($C['row'], $um, 'g', $m); $r = rw_k100(); $w = um_wynik($r, $t['szansa']);
        $pkt = [2 => 3, 1 => 2, 0 => 1, -1 => 0, -2 => -1][$w['poziom']];
        $nowa = max(0, (int)$Z['suma'] + $pkt);
        $h = rw_wiersz("$um (poz. {$t['poziom']})", $r, $t['szansa'], um_opis_testu($t), $w);
        $sum = rw_h($Z['nazwa']) . ': ' . ($pkt > 0 ? "+$pkt" : ($pkt < 0 ? '−' . abs($pkt) : '0')) . " → <em>$nowa / {$Z['cel']}</em> <small>tura {$Z['tura']}/{$Z['tury']}</small>" . ($nowa >= (int)$Z['cel'] ? ' · <em>cel osiągnięty</em>' : '');
        $ops[] = ['zl', (int)$Z['id'], $pkt];
        $ty = $C['nazwa']; $lb = 'Test złożony'; $skrot = "{$C['nazwa']} · {$Z['nazwa']}: $r/{$t['szansa']}% ({$w['nazwa']}) → $nowa/{$Z['cel']}";
    } elseif ($rodz === 'praw') {
        $s = max(1, min(99, (int)($p['p'] ?? 50))); $r = rw_k100(); $ok = $r <= $s;
        $h = "<div class='pmr-row'><div class='pmr-d'><b>$r</b><small>/ $s%</small></div><div class='pmr-o'><strong>" . rw_h($sd ?: 'Zdarzenie') . "</strong><span>k100 ≤ $s</span></div><span class='pmr-s " . ($ok ? 's1' : 's-1') . "'>" . ($ok ? 'Tak' : 'Nie') . "</span></div>";
        $ty = 'Prawdopodobieństwo'; $lb = 'Test prawdopodobieństwa'; $skrot = ($sd ?: 'Zdarzenie') . ": $r/$s% — " . ($ok ? 'tak' : 'nie'); $sd = '';
    } else {
        $n = max(1, min(20, (int)($p['kn'] ?? 1))); $k = (int)($p['ks'] ?? 6); if (!in_array($k, PM_KOSCI, true)) $k = 6; $b = max(-50, min(50, (int)($p['kb'] ?? 0)));
        $wy = []; for ($i = 0; $i < $n; $i++) $wy[] = random_int(1, $k); $s = array_sum($wy) + $b;
        $h = "<div class='pmr-row'><div class='pmr-d'><b>$s</b><small>suma</small></div><div class='pmr-o'><strong>{$n}k{$k}" . rw_sgn($b) . "</strong><span>" . implode(' + ', $wy) . ($b ? ' ' . rw_sgn($b) : '') . "</span></div><span></span></div>";
        $ty = "{$n}k{$k}" . rw_sgn($b); $lb = 'Dowolna kość'; $skrot = "$ty: " . implode('+', $wy) . " = $s";
    }
    $pre = $sd ? "<div class='pmr-sd'>" . rw_h($sd) . "</div>" : '';
    $post = (!empty($p['pasywny']) && !$bez_celu && !$lock ? "<div class='pmr-o'><span>Test pasywny — bez deklaracji gracza, nie zużywa akcji postaci</span></div>" : '')
          . ($co && !$bez_celu ? "<div class='pmr-o'><span>Zalety / Wady: " . rw_h(implode(', ', $co)) . "</span></div>" : '')
          . ($ryz && !$bez_celu ? "<div class='pmr-o'><span>Ryzyko " . rw_h(PM_RYZYKO[$ryz]) . "</span></div>" : '')
          . ($sum ? "<div class='pmr-sum'>$sum</div>" : '');
    return ['html' => rw_karta($ty, $lb, $pre . $h . $post), 'skrot' => ($sd ? "$sd — " : '') . $skrot, 'ops' => $ops, 'tytul' => "$lb · $ty"];
}

/* ── OBSŁUGA POST ─────────────────────────────────────────────────── */
function pm_obsluz(mysqli $db, array $sesja, int $gid, bool $czy_mg, bool $zaakc): string {
    $a = (string)($_POST['pm_akcja'] ?? '');
    if ($a === '') return '';
    $sid = (int)$sesja['id']; $prow = pm_prowadzacy($sesja, $czy_mg); $sam = pm_sam($sesja, $czy_mg, $zaakc);
    if ($sesja['status'] === 'Zakończona') return 'Opowieść jest zakończona.';

    // Zgłoszenie posta (Swobodne)
    if ($a === 'zglos') {
        $pid = (int)($_POST['post_id'] ?? 0); $powod = trim(mb_substr((string)($_POST['powod'] ?? ''), 0, 1000));
        $post = db_wiersz($db, "SELECT autor_id, tresc FROM sesje_posty WHERE id = ? AND sesja_id = ?", [$pid, $sid]);
        if ($sesja['poziom'] !== 'swobodna' || !$zaakc || !$post || (int)$post['autor_id'] === $gid || $powod === '') return 'Nie można zgłosić tego posta.';
        db_zmien($db, "INSERT IGNORE INTO sesje_zgloszenia (sesja_id, post_id, zglaszajacy_id, autor_id, powod, kopia_tresci) VALUES (?, ?, ?, ?, ?, ?)", [$sid, $pid, $gid, (int)$post['autor_id'], $powod, $post['tresc']]);
        foreach (rp_nadzorcy($db) as $n) powiadom($db, $n, "Nowe zgłoszenie z Opowieści Swobodnej <i>" . bz_h($sesja['tytul']) . "</i>. <a href='game.php?page=centrum&zgl=1' style='color:var(--neon-gold)'>[ Zgłoszenia ]</a>");
        pm_wroc($sid);
    }

    // Test gracza na siebie (Swobodne) — od razu do Opowieści
    if ($a === 'test' && $sam && !$prow) {
        $cele = pm_cele($db, $sid);
        $r = pm_test($cele, $_POST, 'g' . $gid, $db, $sid);
        if (isset($r['blad'])) return $r['blad'];
        pm_publikuj($db, $sid, $gid, $r['html']); if ($r['ops']) pm_ops($db, $sid, $r['ops']); pm_wroc($sid);
    }

    if (!$prow) return 'Tylko prowadzący może używać Kulis MG.';
    $P = rw_parametry($sesja);

    switch ($a) {
        case 'test':
            $r = pm_test(pm_cele($db, $sid), $_POST, null, $db, $sid);
            if (isset($r['blad'])) return $r['blad'];
            $_SESSION['pm_pv'][$sid] = $r + ['tab' => 'test']; pm_wroc($sid, 'test');
        case 'atak': case 'odp': case 'upadek':
            $U = rw_uczestnicy($db, $sid);
            $k1 = (string)($_POST['kto'] ?? '');
            if (!isset($U[$k1])) return 'Wybierz uczestnika walki.';
            if ($a === 'atak') {
                $k2 = (string)($_POST['cel'] ?? ''); if (!isset($U[$k2]) || $k2 === $k1) return 'Wybierz cel ataku.';
                if ((int)$U[$k1]['w']['hp'] <= 0 || rw_ma($U[$k1], 'Ogłuszenie')) return 'Atakujący nie może wykonać akcji (nieprzytomny lub ogłuszony).';
                $br = (string)($_POST['bron'] ?? $U[$k1]['w']['bron']); if (!isset(RW_BRON[$br])) $br = 'wrecz';
                // Zalety/Wady z karty: atakujący → Trafienie, cel → Unik (±UM_MOD_CECHA za każdą)
                $cm = function (string $k, string $pole) use ($U): array {
                    $zal = pm_cechy($U[$k]['g']['zalety'] ?? ''); $wad = pm_cechy($U[$k]['g']['wady'] ?? ''); $m = 0; $o = [];
                    foreach ((array)($_POST[$pole][$k] ?? []) as $n) { $n = (string)$n;
                        if (in_array($n, $zal, true)) { $m += UM_MOD_CECHA; $o[] = "$n +" . UM_MOD_CECHA; } elseif (in_array($n, $wad, true)) { $m -= UM_MOD_CECHA; $o[] = "$n −" . UM_MOD_CECHA; } }
                    return [$m, $o];
                };
                [$ma, $oa] = $cm($k1, 'wc_a'); [$mt, $ot] = $cm($k2, 'wc_t');
                $opis = implode(' · ', array_filter([$oa ? 'Trafienie: ' . implode(', ', $oa) : '', $ot ? 'Unik: ' . implode(', ', $ot) : '']));
                $r = rw_atak($U, $k1, $k2, $br, max(-50, min(50, (int)($_POST['mod'] ?? 0))) + $ma, $P, $P['kryt'] || !empty($_POST['kryt']), $mt, $opis);
            } elseif ($a === 'odp') {
                if (!empty($_POST['atakowany'])) return 'Odpoczynek nie może się udać, gdy postać jest atakowana w tej samej turze.';
                $r = rw_odpoczynek($U, $k1, $P);
            } else {
                $r = rw_upadek($U, $k1, (string)($_POST['wys'] ?? '4'), max(-10, min(10, (int)($_POST['pod'] ?? 0))), !empty($_POST['akr']));
            }
            $_SESSION['pm_pv'][$sid] = $r + ['tab' => 'walka']; pm_wroc($sid, 'walka');
        case 'pub': case 'ukr': case 'odrz':
            $pv = $_SESSION['pm_pv'][$sid] ?? null; unset($_SESSION['pm_pv'][$sid]);
            if (!$pv) pm_wroc($sid);
            if ($a !== 'odrz') {
                if ($pv['ops']) pm_ops($db, $sid, $pv['ops']);
                if ($a === 'pub') pm_publikuj($db, $sid, $gid, $pv['html']);
                else db_zmien($db, "INSERT INTO sesje_rzuty_ukryte (sesja_id, mg_id, tytul, tresc) VALUES (?, ?, ?, ?)", [$sid, $gid, mb_substr($pv['tytul'], 0, 160), $pv['html']]);
            }
            pm_wroc($sid, $pv['tab'] ?? '');
        case 'ujawnij':
            $u = db_wiersz($db, "SELECT * FROM sesje_rzuty_ukryte WHERE id = ? AND sesja_id = ? AND ujawniony = 0", [(int)($_POST['uid'] ?? 0), $sid]);
            if ($u && db_zmien($db, "UPDATE sesje_rzuty_ukryte SET ujawniony = 1 WHERE id = ? AND ujawniony = 0", [(int)$u['id']]) === 1)
                pm_publikuj($db, $sid, $gid, str_replace("<div class='pmr'>", "<div class='pmr'><div class='pmr-uj'>Ujawniony rzut z " . rw_h($u['data']) . "</div>", $u['tresc']));
            pm_wroc($sid, 'ukr');
        case 'walka_start':
            pm_publikuj($db, $sid, $gid, rw_start($db, $sid)); pm_wroc($sid, 'walka');
        case 'walka_ini':
            pm_publikuj($db, $sid, $gid, rw_inicjatywa($db, $sid)); pm_wroc($sid, 'walka');
        case 'walka_next':
            $n = max(1, count(rw_uczestnicy($db, $sid)));
            db_zmien($db, "UPDATE sesje_rpg SET walka_tura = (walka_tura + 1) % ? WHERE id = ?", [$n, $sid]); pm_wroc($sid, 'walka');
        case 'walka_runda':
            $h = rw_koniec_rundy($db, $sid); if ($h) pm_publikuj($db, $sid, $gid, $h); pm_wroc($sid, 'walka');
        case 'walka_koniec':
            db_zmien($db, "UPDATE sesje_rpg SET walka_aktywna = 0 WHERE id = ?", [$sid]);
            db_zmien($db, "DELETE FROM sesje_walka WHERE sesja_id = ?", [$sid]);
            unset($_SESSION['pm_pv'][$sid]); pm_wroc($sid, 'walka');
        case 'uczestnik_dodaj':
            $k = (string)($_POST['klucz'] ?? '');
            if (preg_match('/^[gn]\d+$/', $k)) rw_dodaj($db, $sid, $k);
            pm_wroc($sid, 'walka');
        case 'uczestnik_zapisz':
            $k = (string)($_POST['klucz'] ?? '');
            $w = db_wiersz($db, "SELECT * FROM sesje_walka WHERE sesja_id = ? AND klucz = ?", [$sid, $k]);
            if (!$w) pm_wroc($sid, 'walka');
            if (!empty($_POST['usun'])) { db_zmien($db, "DELETE FROM sesje_walka WHERE sesja_id = ? AND klucz = ?", [$sid, $k]); pm_wroc($sid, 'walka'); }
            $br = (string)($_POST['bron'] ?? 'wrecz'); if (!isset(RW_BRON[$br])) $br = 'wrecz';
            $pa = (string)($_POST['pancerz'] ?? 'brak'); if (!isset(RW_PANC[$pa])) $pa = 'brak';
            $hp = max(0, min(rw_eff_max($w), (int)($_POST['hp'] ?? $w['hp'])));
            $ur = max(0, min(4, (int)($_POST['uraz'] ?? $w['uraz'])));
            $ua = $w['urazy_atr'] === '' ? [] : explode(',', $w['urazy_atr']); $ua = array_slice($ua, 0, $ur);
            $kt = !empty($_POST['stabilizuj']) ? null : $w['kryt_tury'];
            if (!empty($_POST['stabilizuj']) && $ur >= 4) $ur = 3;   // pomoc medyczna: krytyczny → ciężki
            db_zmien($db, "UPDATE sesje_walka SET bron = ?, pancerz = ?, tarcza = ?, nc_ok = ?, hp = ?, uraz = ?, urazy_atr = ?, efekty = ?, kryt_tury = ? WHERE sesja_id = ? AND klucz = ?",
                [$br, $pa, empty($_POST['tarcza']) ? 0 : 1, empty($_POST['nc_ok']) ? 0 : 1, $hp, $ur, implode(',', $ua), !empty($_POST['czysc']) ? '[]' : ($w['efekty'] ?: '[]'), $kt, $sid, $k], 'ssiiiissiis');
            pm_wroc($sid, 'walka');
        case 'npc_dodaj':
            $nz = trim(mb_substr((string)($_POST['nazwa'] ?? ''), 0, 80)); if ($nz === '') return 'Podaj nazwę NPC.';
            $at = []; foreach (['sila', 'zrecznosc', 'wytrzymalosc', 'inteligencja', 'zmysly', 'charyzma'] as $c) $at[] = max(0, min(100, (int)($_POST[$c] ?? 40)));
            $um = []; foreach ((array)($_POST['um_n'] ?? []) as $i => $n) { $n = (string)$n; $l = max(0, min(5, (int)($_POST['um_l'][$i] ?? 0))); if ($l && um_definicja($n)) $um[$n] = $l; }
            $br = (string)($_POST['bron'] ?? 'wrecz'); if (!isset(RW_BRON[$br])) $br = 'wrecz';
            $pa = (string)($_POST['pancerz'] ?? 'brak'); if (!isset(RW_PANC[$pa])) $pa = 'brak';
            $cz = fn($s) => implode(', ', pm_cechy(mb_substr((string)$s, 0, 500))) ?: 'Brak';
            db_zmien($db, "INSERT INTO sesje_npc (sesja_id, nazwa, sila, zrecznosc, wytrzymalosc, inteligencja, zmysly, charyzma, umiejetnosci, zalety, wady, hp_max, bron, pancerz) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                array_merge([$sid, $nz], $at, [json_encode($um, JSON_UNESCAPED_UNICODE), $cz($_POST['zalety'] ?? ''), $cz($_POST['wady'] ?? ''), max(1, min(9999, (int)($_POST['hp_max'] ?? 100))), $br, $pa]));
            pm_wroc($sid, 'npc');
        case 'zl_tura': case 'zl_zamknij':
            $Z = db_wiersz($db, "SELECT * FROM sesje_test_zlozony WHERE id = ? AND sesja_id = ? AND status = 'trwa'", [(int)($_POST['zid'] ?? 0), $sid]);
            if ($Z) {
                if ($a === 'zl_zamknij' || (int)$Z['tura'] >= (int)$Z['tury']) {
                    $ok = (int)$Z['suma'] >= (int)$Z['cel'];
                    db_zmien($db, "UPDATE sesje_test_zlozony SET status = ? WHERE id = ?", [$ok ? 'sukces' : 'porazka', (int)$Z['id']]);
                    pm_publikuj($db, $sid, $gid, rw_karta('Test złożony: ' . $Z['nazwa'], 'wynik', "<div class='pmr-sum'>" . ($ok ? '<em>Sukces</em>' : 'Porażka') . " <small>{$Z['suma']} / {$Z['cel']} sukcesów w {$Z['tura']} z {$Z['tury']} tur</small></div>"));
                } else db_zmien($db, "UPDATE sesje_test_zlozony SET tura = tura + 1 WHERE id = ?", [(int)$Z['id']]);
            }
            pm_wroc($sid, 'test');
        case 'npc_usun':
            $nid = (int)($_POST['nid'] ?? 0);
            db_zmien($db, "DELETE FROM sesje_npc WHERE id = ? AND sesja_id = ?", [$nid, $sid]);
            db_zmien($db, "DELETE FROM sesje_walka WHERE sesja_id = ? AND klucz = ?", [$sid, 'n' . $nid]);
            pm_wroc($sid, 'npc');
    }
    return '';
}

/* ── WIDOK ────────────────────────────────────────────────────────── */
function pm_css(): void { ?>
<style>
.pmr{border:1px solid rgba(255,23,68,.45);background:linear-gradient(135deg,rgba(255,23,68,.08),rgba(10,4,8,.7));padding:14px 16px;display:flex;flex-direction:column;gap:9px;color:#f1ebf2}
.pmr-h{display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap}
.pmr-h b{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;color:#fff}
.pmr-h span,.pmr-uj{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.4px;text-transform:uppercase;color:#cfc6d2}
.pmr-uj{color:#b48cff}
.pmr-sd{font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.12em;color:#ece4ec}
.pmr-row{display:grid;grid-template-columns:66px minmax(0,1fr) auto;gap:12px;align-items:center;padding:6px 0;border-top:1px dotted rgba(255,255,255,.1)}
.pmr-d{width:66px;height:54px;border:2px solid #ff1744;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(0,0,0,.6);transform:rotate(-3deg)}
.pmr-d b{font-family:'Oswald',sans-serif;font-size:1.55em;line-height:1;color:#fff}
.pmr-d small{font-family:'JetBrains Mono',monospace;font-size:.62em;color:#cfc6d2}
.pmr-d.cs{border-color:#ffd23d}.pmr-d.cs b{color:#ffd23d}.pmr-d.cf{border-color:#b48cff}.pmr-d.cf b{color:#b48cff}
.pmr-o{display:flex;flex-direction:column;gap:2px;min-width:0}
.pmr-o strong{color:#fff;font-weight:700}
.pmr-o span{font-family:'JetBrains Mono',monospace;font-size:.78em;color:#cfc6d2;line-height:1.5}
.pmr-o span b{color:#fff;font-weight:500}
.pmr-s{font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:.8em;padding:4px 9px;border:1.5px solid currentColor;white-space:nowrap}
.pmr-s.s2{color:#ffd23d}.pmr-s.s1{color:#3dff9a}.pmr-s.s0{color:#9ff5c8}.pmr-s.s-1{color:#ff3d5e}.pmr-s.s-2{color:#b48cff}
.pmr-sum{padding-top:8px;border-top:1px solid rgba(255,23,68,.45);font-family:'Oswald',sans-serif;letter-spacing:1px;color:#fff}
.pmr-sum em{font-style:normal;color:#ffd23d}.pmr-sum small{font-family:'Rajdhani',sans-serif;color:#cfc6d2;letter-spacing:0}
.pm-ini{display:flex;gap:8px;overflow-x:auto;padding:12px;margin-bottom:18px;background:rgba(0,0,0,.5);border:1px solid rgba(255,23,68,.22)}
.pm-ini-h{display:flex;flex-direction:column;justify-content:center;padding-right:10px;border-right:1px solid rgba(255,23,68,.22);min-width:80px;font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.5px;color:#cfc6d2;text-transform:uppercase}
.pm-ini-h b{font-family:'Oswald',sans-serif;font-size:1.9em;color:#fff;font-weight:500}
.pm-ini-c{min-width:128px;padding:8px 10px;border:1px solid rgba(255,255,255,.1);background:rgba(0,0,0,.35);display:flex;flex-direction:column;gap:5px;position:relative;color:#f1ebf2}
.pm-ini-c.now{border-color:#ff7a3d;box-shadow:0 0 14px rgba(255,122,61,.35)}
.pm-ini-c.now::before{content:'TERAZ';position:absolute;top:-8px;left:8px;background:#ff7a3d;color:#000;font-family:'Oswald',sans-serif;font-size:.62em;letter-spacing:2px;padding:0 6px}
.pm-ini-c.npc{border-style:dashed}.pm-ini-c.down{opacity:.45}
.pm-ini-n{display:flex;justify-content:space-between;gap:6px;font-weight:700;color:#fff}
.pm-ini-n span{font-family:'JetBrains Mono',monospace;font-weight:400;color:#ff7a3d;font-size:.85em}
.pm-hp{height:5px;background:rgba(255,255,255,.1);position:relative}
.pm-hp i{position:absolute;left:0;top:0;bottom:0;background:#3dff9a}.pm-hp i.mid{background:#ffd23d}.pm-hp i.low{background:#ff3d5e}
.pm-hp u{position:absolute;right:0;top:0;bottom:0;background:repeating-linear-gradient(45deg,rgba(255,23,68,.5) 0 3px,transparent 3px 6px)}
.pm-fx{display:flex;flex-wrap:wrap;gap:3px}
.pm-fx s{text-decoration:none;font-family:'JetBrains Mono',monospace;font-size:.66em;padding:0 5px;border:1px solid #ffd23d;color:#ffd23d}
.pm-fx s.u{border-color:#ff3d5e;color:#ff3d5e}
.msg.sys .sys-line:has(.pmr){display:block;background:none;border:0;padding:0;text-align:left;font-size:1em;color:inherit}
.msg.sys .sys-line .pmr{margin:4px 0}
.ko-live{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:center;padding:10px 14px;margin-bottom:10px;background:rgba(0,0,0,.55);border:1px solid var(--lc);box-shadow:0 0 14px rgba(0,0,0,.4);color:#f1ebf2}
.ko-dot{width:10px;height:10px;border-radius:50%;background:var(--lc);box-shadow:0 0 8px var(--lc);animation:koP 1.2s infinite}
@keyframes koP{50%{opacity:.3}}
.ko-t{display:flex;flex-direction:column;gap:1px;flex:1;min-width:180px}
.ko-t b{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:1px;color:#fff;font-size:1.05em}
.ko-t small{color:#cfc6d2}
.ko-lbl{font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:1.6px;text-transform:uppercase;color:var(--lc)}
.ko-a{padding:6px 12px;border:1px solid var(--lc);color:#fff !important;text-decoration:none;font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase;font-size:.8em}
.ko-a.ghost{border-color:rgba(255,255,255,.2);color:#cfc6d2 !important;text-transform:none;letter-spacing:.5px}
.pm-zgl{margin-top:8px}
.pm-zgl summary{cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:.74em;color:#ffd23d;letter-spacing:1px;list-style:none}
.pm-zgl form{display:flex;gap:6px;margin-top:6px}
.pm-zgl input{flex:1;background:rgba(0,0,0,.55);border:1px solid rgba(255,210,61,.4);color:#fff;padding:6px 8px;font-family:inherit}
.pm-zgl button{background:transparent;border:1px solid #ffd23d;color:#ffd23d;padding:6px 10px;font-family:'Oswald',sans-serif;letter-spacing:1.5px;cursor:pointer}
</style>
<?php }

function pm_hp_bar(array $w): string {
    $e = rw_eff_max($w); $mx = max(1, (int)$w['hp_max']); $hp = (int)$w['hp'];
    $cl = $hp / max(1, $e) < .3 ? 'low' : ($hp / max(1, $e) < .6 ? 'mid' : '');
    return "<div class='pm-hp'><i class='$cl' style='width:" . round($hp / $mx * 100, 1) . "%'></i>" . ((int)$w['uraz'] ? "<u style='width:" . round(($mx - $e) / $mx * 100, 1) . "%'></u>" : '') . "</div>";
}
function pm_fx_html(array $u): string {
    $w = $u['w']; $h = (int)$w['uraz'] ? "<s class='u'>Uraz " . RW_URAZ[min(4, (int)$w['uraz'])] . "</s>" : '';
    if ((int)$w['hp'] <= 0) $h .= "<s class='u'>Nieprzytomny</s>";
    if ($w['kryt_tury'] !== null) $h .= "<s class='u'>Śmierć za " . (int)$w['kryt_tury'] . " t.</s>";
    foreach ($u['fx'] as $f) $h .= '<s>' . rw_h($f['n']) . (isset($f['t']) ? ' ' . (int)$f['t'] . 't' : '') . '</s>';
    return "<div class='pm-fx'>$h</div>";
}

/** Pasek Inicjatywy nad postami — widoczny dla wszystkich w trakcie walki. */
function pm_pasek(mysqli $db, array $s): void {
    if (empty($s['walka_aktywna'])) return;
    $U = array_values(rw_uczestnicy($db, (int)$s['id'])); $t = (int)$s['walka_tura'];
    echo "<div class='pm-ini'><div class='pm-ini-h'>Runda<b>" . (int)$s['walka_runda'] . "</b></div>";
    foreach ($U as $i => $u) echo "<div class='pm-ini-c" . ($i === $t ? ' now' : '') . ($u['npc'] ? ' npc' : '') . ((int)$u['w']['hp'] <= 0 ? ' down' : '') . "'><div class='pm-ini-n'>" . rw_h($u['nazwa']) . "<span>" . (int)$u['w']['ini'] . "</span></div>" . pm_hp_bar($u['w']) . pm_fx_html($u) . "</div>";
    echo "</div>";
}

/** „Zgłoś MG” pod postem w Opowieści Swobodnej. */
function pm_przycisk_zgloszenia(array $s, array $post, int $gid, bool $zaakc): void {
    if (($s['poziom'] ?? '') !== 'swobodna' || !$zaakc || (int)$post['autor_id'] === $gid || $post['typ_postu'] === 'Rzut_Koscia' || $s['status'] === 'Zakończona') return;
    echo "<details class='pm-zgl'><summary>⚑ Zgłoś MG</summary><form method='POST'><input type='hidden' name='pm_akcja' value='zglos'><input type='hidden' name='post_id' value='" . (int)$post['id'] . "'><input name='powod' maxlength='1000' required placeholder='Co jest nie tak? np. zabił moją postać bez mojej zgody i bez rzutu'><button type='submit'>Wyślij</button></form></details>";
}

function pm_opcje(array $m, $sel): string { $h = ''; foreach ($m as $k => $n) $h .= "<option value='" . rw_h($k) . "'" . ((string)$k === (string)$sel ? ' selected' : '') . ">" . rw_h($n) . "</option>"; return $h; }

/** Podgląd rzutu z przyciskami Opublikuj / Ukryty / Odrzuć. */
function pm_pv_html(array $pv): void { ?>
  <div class="pmk-pv"><span class="lbl"><b>Podgląd · tylko Ty</b> · <?php echo rw_h($pv['tytul']); ?></span><?php echo $pv['html']; ?>
    <div class="acts"><form method="POST"><input type="hidden" name="pm_akcja" value="pub"><button class="pmk-btn gold" type="submit">Opublikuj<?php echo $pv['ops'] ? ' i zastosuj' : ''; ?></button></form>
    <form method="POST"><input type="hidden" name="pm_akcja" value="ukr"><button class="pmk-btn vio" type="submit">Ukryty rzut</button></form>
    <form method="POST"><input type="hidden" name="pm_akcja" value="odrz"><button class="pmk-btn ghost" type="submit">Odrzuć</button></form></div></div>
<?php }

function pm_render(mysqli $db, array $s, int $gid, bool $czy_mg, bool $zaakc, string $blad = ''): void {
    pm_css();
    $prow = pm_prowadzacy($s, $czy_mg); $sam = pm_sam($s, $czy_mg, $zaakc) && !$prow;
    if ((!$prow && !$sam) || $s['status'] === 'Zakończona') return;
    require __DIR__ . '/panel_mg_widok.php';
}
