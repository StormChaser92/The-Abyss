<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// Pobierz dane gracza
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();

$jest_mg = (bool)($gracz['is_mg'] ?? 0);
$jest_proboszczem = (bool)($gracz['is_proboszcz'] ?? 0);
$ma_uprawnienia = ($jest_mg || $jest_proboszczem);

// Sprawdź czy gracz jest już w związku małżeńskim
$moje_malzenstwo = $polaczenie->query("SELECT m.*,
    g1.login AS m1_login, g1.avatar AS m1_avatar,
    g2.login AS m2_login, g2.avatar AS m2_avatar
    FROM malzenstwa m
    JOIN gracze g1 ON m.malzonek_1_id = g1.id
    JOIN gracze g2 ON m.malzonek_2_id = g2.id
    WHERE (m.malzonek_1_id=$id_gracza OR m.malzonek_2_id=$id_gracza)
      AND m.status='aktywne'")->fetch_assoc();

// Sprawdź czy gracz ma już oczekujące zgłoszenie
$moje_zgloszenie = $polaczenie->query("SELECT z.*, g.login AS partner_login, g.avatar AS partner_avatar
    FROM zgloszenia_slubu z
    JOIN gracze g ON g.id = CASE WHEN z.zglaszajacy_id=$id_gracza THEN z.partner_id ELSE z.zglaszajacy_id END
    WHERE (z.zglaszajacy_id=$id_gracza OR z.partner_id=$id_gracza)
      AND z.status IN ('oczekuje','partner_potwierdzil','zatwierdzone')
    ORDER BY z.data_zgloszenia DESC
    LIMIT 1")->fetch_assoc();

// ═══════════════════════════════════════════════════════════════
// ZŁOŻENIE ZGŁOSZENIA ŚLUBU
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zglos_slub'])) {
    $partner_id = (int)$_POST['partner_id'];
    $narracja = trim($_POST['narracja'] ?? '');

    if ($moje_malzenstwo) {
        $komunikat = "<div class='blad'>Jesteś już w związku małżeńskim! Nie możesz zgłosić kolejnego.</div>";
    } elseif ($moje_zgloszenie) {
        $komunikat = "<div class='blad'>Masz już aktywne zgłoszenie oczekujące na rozpatrzenie!</div>";
    } elseif ($partner_id == $id_gracza) {
        $komunikat = "<div class='blad'>Nie możesz zgłosić ślubu z samym sobą.</div>";
    } elseif (mb_strlen($narracja) < 100) {
        $komunikat = "<div class='blad'>Narracja zaręczyn musi mieć minimum 100 znaków (aktualnie: ".mb_strlen($narracja).").</div>";
    } elseif (mb_strlen($narracja) > 3000) {
        $komunikat = "<div class='blad'>Narracja zaręczyn może mieć maksymalnie 3000 znaków.</div>";
    } else {
        // Sprawdź czy partner istnieje
        $partner = $polaczenie->query("SELECT id, login FROM gracze WHERE id=$partner_id")->fetch_assoc();
        if (!$partner) {
            $komunikat = "<div class='blad'>Nie ma gracza o ID $partner_id.</div>";
        } else {
            // Sprawdź czy partner nie jest już w związku
            $partner_w_zwiazku = $polaczenie->query("SELECT id FROM malzenstwa
                WHERE (malzonek_1_id=$partner_id OR malzonek_2_id=$partner_id) AND status='aktywne'")->fetch_assoc();
            if ($partner_w_zwiazku) {
                $komunikat = "<div class='blad'>Twój wybranek/wybranka jest już w związku małżeńskim!</div>";
            } else {
                $narracja_esc = $polaczenie->real_escape_string($narracja);
                $polaczenie->query("INSERT INTO zgloszenia_slubu
                    (zglaszajacy_id, partner_id, narracja_zaręczyn, status)
                    VALUES ($id_gracza, $partner_id, '$narracja_esc', 'oczekuje')");

                // Powiadomienie dla partnera
                $pow = "💍 <b>{$gracz['login']}</b> zgłosił wolę zawarcia z Tobą związku małżeńskiego! Sprawdź Katedrę.";
                $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($partner_id, '$pow')");

                // Powiadomienie dla wszystkich Proboszczów i MG
                $administracja = $polaczenie->query("SELECT id FROM gracze WHERE is_mg=1 OR is_proboszcz=1");
                if ($administracja) {
                    while ($admin = $administracja->fetch_assoc()) {
                        if ($admin['id'] == $id_gracza) continue;
                        $pow_admin = "⛪ Nowe zgłoszenie małżeńskie w Katedrze: <b>{$gracz['login']}</b> & <b>".htmlspecialchars($partner['login'])."</b>";
                        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$admin['id']}, '$pow_admin')");
                    }
                }

                $komunikat = "<div class='sukces'>💒 Zgłoszenie wysłane! Teraz <b>".htmlspecialchars($partner['login'])."</b> musi je potwierdzić, a potem rozpatrzy je Proboszcz lub Mistrz Gry.</div>";
                echo "<script>setTimeout(()=>location.href='game.php?page=katedra',2000);</script>";
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// POTWIERDZENIE ZGŁOSZENIA PRZEZ PARTNERA
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['potwierdz_zgloszenie'])) {
    $zgl_id = (int)$_POST['zgl_id'];
    $zgl = $polaczenie->query("SELECT * FROM zgloszenia_slubu WHERE id=$zgl_id AND partner_id=$id_gracza AND status='oczekuje'")->fetch_assoc();
    if ($zgl) {
        $polaczenie->query("UPDATE zgloszenia_slubu SET status='partner_potwierdzil' WHERE id=$zgl_id");
        // Powiadomienie dla zgłaszającego
        $pow = "💖 <b>{$gracz['login']}</b> potwierdził/a wolę ślubu. Teraz czekacie na decyzję Proboszcza lub Mistrza Gry.";
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zgl['zglaszajacy_id']}, '$pow')");
        $komunikat = "<div class='sukces'>💖 Potwierdziłeś zgłoszenie! Teraz administracja zdecyduje.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['odrzuc_zgloszenie'])) {
    $zgl_id = (int)$_POST['zgl_id'];
    $zgl = $polaczenie->query("SELECT * FROM zgloszenia_slubu WHERE id=$zgl_id AND partner_id=$id_gracza AND status='oczekuje'")->fetch_assoc();
    if ($zgl) {
        $polaczenie->query("UPDATE zgloszenia_slubu SET status='odrzucone', uzasadnienie_odrzucenia='Odrzucone przez partnera' WHERE id=$zgl_id");
        $pow = "💔 <b>{$gracz['login']}</b> odrzucił/a Twoje zgłoszenie małżeńskie.";
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zgl['zglaszajacy_id']}, '$pow')");
        $komunikat = "<div class='sukces'>Zgłoszenie odrzucone.</div>";
    }
}

// ═══════════════════════════════════════════════════════════════
// AKCJE PROBOSZCZA / MG
// ═══════════════════════════════════════════════════════════════

// Zatwierdzenie zgłoszenia (wyznaczenie daty ślubu)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zatwierdz_zgloszenie']) && $ma_uprawnienia) {
    $zgl_id = (int)$_POST['zgl_id'];
    $data_slubu = $_POST['data_slubu'] ?? null;

    $zgl = $polaczenie->query("SELECT * FROM zgloszenia_slubu WHERE id=$zgl_id AND status='partner_potwierdzil'")->fetch_assoc();
    if ($zgl && $data_slubu) {
        $data_esc = $polaczenie->real_escape_string($data_slubu);
        $polaczenie->query("UPDATE zgloszenia_slubu SET
            status='zatwierdzone',
            rozpatrujacy_id=$id_gracza,
            data_rozpatrzenia=NOW(),
            planowana_data_slubu='$data_esc'
            WHERE id=$zgl_id");

        // Powiadomienie dla pary
        $pow = "🎉 Wasze zgłoszenie zostało zatwierdzone przez <b>{$gracz['login']}</b>! Ślub odbędzie się ".date('d.m.Y H:i', strtotime($data_slubu)).". Przygotujcie się na sesję fabularną!";
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zgl['zglaszajacy_id']}, '$pow')");
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zgl['partner_id']}, '$pow')");

        $komunikat = "<div class='sukces'>✅ Zgłoszenie zatwierdzone, data wyznaczona na ".date('d.m.Y H:i', strtotime($data_slubu))."</div>";
    } else {
        $komunikat = "<div class='blad'>Nieprawidłowe zgłoszenie lub data.</div>";
    }
}

// Odrzucenie zgłoszenia przez administrację
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_odrzuc_zgloszenie']) && $ma_uprawnienia) {
    $zgl_id = (int)$_POST['zgl_id'];
    $uzasadnienie = $polaczenie->real_escape_string(trim($_POST['uzasadnienie'] ?? 'Brak uzasadnienia'));

    $zgl = $polaczenie->query("SELECT * FROM zgloszenia_slubu WHERE id=$zgl_id")->fetch_assoc();
    if ($zgl) {
        $polaczenie->query("UPDATE zgloszenia_slubu SET
            status='odrzucone',
            rozpatrujacy_id=$id_gracza,
            data_rozpatrzenia=NOW(),
            uzasadnienie_odrzucenia='$uzasadnienie'
            WHERE id=$zgl_id");

        $pow = "⛪ Wasze zgłoszenie małżeńskie zostało odrzucone przez <b>{$gracz['login']}</b>. Powód: $uzasadnienie";
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zgl['zglaszajacy_id']}, '$pow')");
        $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$zgl['partner_id']}, '$pow')");

        $komunikat = "<div class='sukces'>Zgłoszenie odrzucone.</div>";
    }
}

// Udzielenie ślubu (finalizacja — tworzenie małżeństwa)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['udziel_slubu']) && $ma_uprawnienia) {
    $zgl_id = (int)$_POST['zgl_id'];
    $zgl = $polaczenie->query("SELECT * FROM zgloszenia_slubu WHERE id=$zgl_id AND status='zatwierdzone'")->fetch_assoc();
    if ($zgl) {
        // Upewnij się że ID w kolejności (dla unique key)
        $m1 = min($zgl['zglaszajacy_id'], $zgl['partner_id']);
        $m2 = max($zgl['zglaszajacy_id'], $zgl['partner_id']);

        // Sprawdź czy żadne z nich nie jest już w małżeństwie
        $spr = $polaczenie->query("SELECT id FROM malzenstwa WHERE
            (malzonek_1_id IN ($m1,$m2) OR malzonek_2_id IN ($m1,$m2)) AND status='aktywne'")->fetch_assoc();

        if ($spr) {
            $komunikat = "<div class='blad'>Jedno z narzeczonych jest już w innym związku!</div>";
        } else {
            $narracja_esc = $polaczenie->real_escape_string($zgl['narracja_zaręczyn']);
            $polaczenie->query("INSERT INTO malzenstwa
                (malzonek_1_id, malzonek_2_id, narracja_zaręczyn, udzielajacy_slubu_id, status)
                VALUES ($m1, $m2, '$narracja_esc', $id_gracza, 'aktywne')");

            $polaczenie->query("UPDATE zgloszenia_slubu SET status='slubowali' WHERE id=$zgl_id");

            // ══ AUTOMATYCZNE ZAMIESZKANIE RAZEM ══
            // Sprawdź kto ma lepszy dom i tam wprowadź drugiego
            $g1_info = $polaczenie->query("SELECT id_domu FROM gracze WHERE id=$m1")->fetch_assoc();
            $g2_info = $polaczenie->query("SELECT id_domu FROM gracze WHERE id=$m2")->fetch_assoc();

            $wlasciciel_dom = null;
            $lokator_dom = null;
            if ($g1_info['id_domu'] > $g2_info['id_domu']) {
                $wlasciciel_dom = $m1; $lokator_dom = $m2;
            } elseif ($g2_info['id_domu'] > $g1_info['id_domu']) {
                $wlasciciel_dom = $m2; $lokator_dom = $m1;
            } elseif ($g1_info['id_domu'] > 0) {
                // Oboje mają takie same — bierzemy m1 jako właściciela
                $wlasciciel_dom = $m1; $lokator_dom = $m2;
            }

            if ($wlasciciel_dom && $lokator_dom) {
                // Usuń istniejące wspollokatorstwo lokatora (jeśli gdzieś mieszka)
                $polaczenie->query("DELETE FROM wspollokatorzy WHERE lokator_id=$lokator_dom");
                // Dodaj jako małżonka z pełnym dostępem
                $polaczenie->query("INSERT IGNORE INTO wspollokatorzy (wlasciciel_id, lokator_id, typ, moze_spac, ma_dostep_do_pokoi)
                    VALUES ($wlasciciel_dom, $lokator_dom, 'malzonek', 1, 1)");
            }

            // Upgrade istniejących wspollokatorow na małżonków (jeśli już mieszkali razem)
            $polaczenie->query("UPDATE wspollokatorzy SET typ='malzonek', ma_dostep_do_pokoi=1
                WHERE (wlasciciel_id=$m1 AND lokator_id=$m2) OR (wlasciciel_id=$m2 AND lokator_id=$m1)");

            // Powiadomienia
            $pow = "💍 Gratulacje! Jesteście teraz małżeństwem! Ślubu udzielił/a <b>{$gracz['login']}</b>.";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($m1, '$pow')");
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($m2, '$pow')");

            $komunikat = "<div class='sukces' style='font-size:1.1em'>💒 Udzieliłeś sakramentu małżeństwa! Niech żyją nowożeńcy! 🥂</div>";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// ZGŁOSZENIE ROZWODU
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zglos_rozwod'])) {
    $powod = trim($_POST['powod_rozwodu'] ?? '');

    if (!$moje_malzenstwo) {
        $komunikat = "<div class='blad'>Nie jesteś w związku małżeńskim!</div>";
    } elseif (mb_strlen($powod) < 50) {
        $komunikat = "<div class='blad'>Uzasadnienie rozwodu musi mieć minimum 50 znaków.</div>";
    } else {
        $spr = $polaczenie->query("SELECT id FROM zgloszenia_rozwodu WHERE malzenstwo_id={$moje_malzenstwo['id']} AND status='oczekuje'")->fetch_assoc();
        if ($spr) {
            $komunikat = "<div class='blad'>Zgłoszenie rozwodu już jest w trakcie rozpatrywania.</div>";
        } else {
            $powod_esc = $polaczenie->real_escape_string($powod);
            $polaczenie->query("INSERT INTO zgloszenia_rozwodu (zglaszajacy_id, malzenstwo_id, powod)
                VALUES ($id_gracza, {$moje_malzenstwo['id']}, '$powod_esc')");

            // Powiadomienie partnera
            $partner_id = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['malzonek_2_id'] : $moje_malzenstwo['malzonek_1_id'];
            $pow = "💔 <b>{$gracz['login']}</b> złożył/a wniosek o rozwód. Sprawę rozpatrzy Proboszcz lub Mistrz Gry.";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($partner_id, '$pow')");

            // Powiadomienie administracji
            $admini = $polaczenie->query("SELECT id FROM gracze WHERE is_mg=1 OR is_proboszcz=1");
            if ($admini) {
                while ($a = $admini->fetch_assoc()) {
                    if ($a['id'] == $id_gracza) continue;
                    $pow_a = "⚖️ Nowe zgłoszenie rozwodu w Katedrze — rozpatrz w panelu administracji.";
                    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$a['id']}, '$pow_a')");
                }
            }

            $komunikat = "<div class='sukces'>💔 Wniosek złożony. Proboszcz lub MG rozpatrzy go wkrótce.</div>";
        }
    }
}

// ZATWIERDZENIE ROZWODU PRZEZ MG
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['zatwierdz_rozwod']) && $ma_uprawnienia) {
    $rid = (int)$_POST['rozwod_id'];
    $r = $polaczenie->query("SELECT * FROM zgloszenia_rozwodu WHERE id=$rid AND status='oczekuje'")->fetch_assoc();
    if ($r) {
        $m = $polaczenie->query("SELECT * FROM malzenstwa WHERE id={$r['malzenstwo_id']} AND status='aktywne'")->fetch_assoc();
        if ($m) {
            // Rozwiąż małżeństwo
            $polaczenie->query("UPDATE malzenstwa SET status='rozwiedzione', data_rozwodu=NOW() WHERE id={$m['id']}");
            $polaczenie->query("UPDATE zgloszenia_rozwodu SET status='zatwierdzone', rozpatrujacy_id=$id_gracza, data_rozpatrzenia=NOW() WHERE id=$rid");

            // Usuń lokatorstwo małżonka (obie strony)
            $polaczenie->query("DELETE FROM wspollokatorzy WHERE
                (wlasciciel_id={$m['malzonek_1_id']} AND lokator_id={$m['malzonek_2_id']})
                OR (wlasciciel_id={$m['malzonek_2_id']} AND lokator_id={$m['malzonek_1_id']})");

            // Powiadomienia
            $pow = "⚖️ Wasze małżeństwo zostało rozwiązane przez <b>{$gracz['login']}</b>.";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$m['malzonek_1_id']}, '$pow')");
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$m['malzonek_2_id']}, '$pow')");

            $komunikat = "<div class='sukces'>⚖️ Małżeństwo rozwiązane.</div>";
        }
    }
}

// ODRZUCENIE ROZWODU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['odrzuc_rozwod']) && $ma_uprawnienia) {
    $rid = (int)$_POST['rozwod_id'];
    $uzas = $polaczenie->real_escape_string(trim($_POST['uzasadnienie_roz'] ?? 'Brak uzasadnienia'));
    $r = $polaczenie->query("SELECT * FROM zgloszenia_rozwodu WHERE id=$rid AND status='oczekuje'")->fetch_assoc();
    if ($r) {
        $polaczenie->query("UPDATE zgloszenia_rozwodu SET status='odrzucone', rozpatrujacy_id=$id_gracza, data_rozpatrzenia=NOW(), uzasadnienie='$uzas' WHERE id=$rid");
        $m = $polaczenie->query("SELECT * FROM malzenstwa WHERE id={$r['malzenstwo_id']}")->fetch_assoc();
        if ($m) {
            $pow = "⚖️ Wniosek rozwodowy odrzucony przez <b>{$gracz['login']}</b>. Powód: $uzas";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$m['malzonek_1_id']}, '$pow')");
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ({$m['malzonek_2_id']}, '$pow')");
        }
        $komunikat = "<div class='sukces'>Wniosek odrzucony.</div>";
    }
}

// ═══════════════════════════════════════════════════════════════
// POBIERANIE DANYCH DO WIDOKU
// ═══════════════════════════════════════════════════════════════

// Oczekujące zgłoszenia dla administracji
$zgloszenia_do_rozpatrzenia = [];
if ($ma_uprawnienia) {
    $q = $polaczenie->query("SELECT z.*,
        g1.login AS login_1, g1.avatar AS avatar_1,
        g2.login AS login_2, g2.avatar AS avatar_2
        FROM zgloszenia_slubu z
        JOIN gracze g1 ON z.zglaszajacy_id = g1.id
        JOIN gracze g2 ON z.partner_id = g2.id
        WHERE z.status IN ('partner_potwierdzil','zatwierdzone')
        ORDER BY z.data_zgloszenia DESC");
    if ($q) while($r = $q->fetch_assoc()) $zgloszenia_do_rozpatrzenia[] = $r;
}

// Zgłoszenia do potwierdzenia przez obecnego gracza (jeśli jest partnerem)
$zgloszenia_do_potwierdzenia = [];
$q = $polaczenie->query("SELECT z.*, g.login AS od_login, g.avatar AS od_avatar
    FROM zgloszenia_slubu z
    JOIN gracze g ON z.zglaszajacy_id = g.id
    WHERE z.partner_id=$id_gracza AND z.status='oczekuje'
    ORDER BY z.data_zgloszenia DESC");
if ($q) while($r = $q->fetch_assoc()) $zgloszenia_do_potwierdzenia[] = $r;

// Zaplanowane śluby publiczne
$zaplanowane_sluby = [];
$q = $polaczenie->query("SELECT z.*, g1.login AS login_1, g2.login AS login_2
    FROM zgloszenia_slubu z
    JOIN gracze g1 ON z.zglaszajacy_id = g1.id
    JOIN gracze g2 ON z.partner_id = g2.id
    WHERE z.status='zatwierdzone' AND z.planowana_data_slubu > NOW()
    ORDER BY z.planowana_data_slubu ASC LIMIT 10");
if ($q) while($r = $q->fetch_assoc()) $zaplanowane_sluby[] = $r;

// Spis małżeństw
$malzenstwa_spis = [];
$q = $polaczenie->query("SELECT m.*,
    g1.login AS m1_login, g1.avatar AS m1_avatar,
    g2.login AS m2_login, g2.avatar AS m2_avatar,
    gu.login AS udzielajacy_login,
    DATEDIFF(NOW(), m.data_slubu) AS dni_razem
    FROM malzenstwa m
    JOIN gracze g1 ON m.malzonek_1_id = g1.id
    JOIN gracze g2 ON m.malzonek_2_id = g2.id
    LEFT JOIN gracze gu ON m.udzielajacy_slubu_id = gu.id
    WHERE m.status='aktywne'
    ORDER BY m.data_slubu DESC LIMIT 50");
if ($q) while($r = $q->fetch_assoc()) $malzenstwa_spis[] = $r;
// Zgłoszenia rozwodowe (dla administracji)
$zgloszenia_rozwodu = [];
if ($ma_uprawnienia) {
    $q = $polaczenie->query("SELECT r.*, m.malzonek_1_id, m.malzonek_2_id, m.data_slubu, m.narracja_zaręczyn AS m_narracja,
        g1.login AS login_1, g1.avatar AS avatar_1,
        g2.login AS login_2, g2.avatar AS avatar_2,
        gz.login AS zglaszajacy_login
        FROM zgloszenia_rozwodu r
        JOIN malzenstwa m ON r.malzenstwo_id = m.id
        JOIN gracze g1 ON m.malzonek_1_id = g1.id
        JOIN gracze g2 ON m.malzonek_2_id = g2.id
        JOIN gracze gz ON r.zglaszajacy_id = gz.id
        WHERE r.status = 'oczekuje'
        ORDER BY r.data_zgloszenia DESC");
    if ($q) while($r = $q->fetch_assoc()) $zgloszenia_rozwodu[] = $r;
}

// Moje zgłoszenie rozwodu (jeśli jest)
$moje_zgl_rozwodu = null;
if ($moje_malzenstwo) {
    $moje_zgl_rozwodu = $polaczenie->query("SELECT * FROM zgloszenia_rozwodu
        WHERE malzenstwo_id={$moje_malzenstwo['id']} AND status='oczekuje'")->fetch_assoc();
}

?>

<style>
/* ══ KATEDRA — NAGŁÓWEK Z OBRAZKIEM ══ */
.kat-header{
    position:relative;
    border-radius:12px;margin-bottom:22px;overflow:hidden;
    border:1px solid rgba(255,215,0,.3);
    box-shadow:0 0 40px rgba(255,215,0,.1);
    min-height:320px;
    background:
        linear-gradient(to bottom, rgba(0,0,0,.3) 0%, rgba(0,0,0,.7) 70%, rgba(0,0,0,.95) 100%),
        url('img/katedra.jpg') center/cover no-repeat;
    /* WRZUĆ OBRAZEK do img/katedra.jpg — zalecam min 1400x500px, ciemny klimat */
    background-color:#1a0a00;
}
.kat-header-content{
    position:absolute;bottom:0;left:0;right:0;
    padding:30px 40px;
    text-align:center;
}
.kat-ikona{font-size:3.5em;line-height:1;margin-bottom:8px;filter:drop-shadow(0 0 20px #ffd700)}
.kat-nazwa{font-family:'Oswald',sans-serif;color:#ffd700;font-size:2.5em;
    margin:0 0 6px;text-transform:uppercase;letter-spacing:3px;
    text-shadow:0 0 25px rgba(255,215,0,.6),0 2px 10px #000}
.kat-motto{color:#ccc;font-style:italic;font-size:1em;max-width:600px;margin:0 auto}

/* ══ SEKCJE ══ */
.sekcja-tytul{color:#ffd700;font-family:'Oswald',sans-serif;text-transform:uppercase;
    letter-spacing:2px;font-size:1em;margin:26px 0 14px;padding-bottom:10px;
    border-bottom:1px solid rgba(255,215,0,.2);display:flex;align-items:center;gap:10px}
.sekcja-tytul .licznik{background:rgba(255,215,0,.15);color:#ffd700;padding:2px 10px;border-radius:12px;font-size:.75em;font-weight:400;letter-spacing:0}

/* ══ ZGŁOŚ ŚLUB — FORMULARZ ══ */
.zglos-box{
    background:linear-gradient(135deg,rgba(255,215,0,.05),rgba(0,0,0,.4));
    border:1px solid rgba(255,215,0,.25);border-radius:12px;padding:24px;margin-bottom:24px;
}
.zglos-tytul{font-family:'Oswald',sans-serif;color:#ffd700;font-size:1.3em;
    text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;text-align:center;
    text-shadow:0 0 10px rgba(255,215,0,.4)}
.zglos-podtytul{color:#888;font-style:italic;text-align:center;margin-bottom:20px;font-size:.9em}
.zglos-form label{display:block;color:#aaa;font-family:'Oswald',sans-serif;
    font-size:.85em;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px}
.zglos-form input,.zglos-form textarea{
    width:100%;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.1);
    color:#ddd;padding:10px 14px;border-radius:6px;font-family:'Open Sans',sans-serif;
    font-size:.95em;box-sizing:border-box;margin-bottom:14px;
}
.zglos-form textarea{resize:vertical;min-height:180px;line-height:1.6}
.zglos-form input:focus,.zglos-form textarea:focus{outline:none;border-color:rgba(255,215,0,.5);box-shadow:0 0 10px rgba(255,215,0,.15)}
.licznik-znakow{font-size:.8em;color:#666;text-align:right;margin-top:-10px;margin-bottom:10px}
.licznik-znakow.ok{color:#00ff88}
.licznik-znakow.za-malo{color:#ff6666}

.btn-zglos{
    width:100%;background:rgba(255,215,0,.15);color:#ffd700;
    border:1px solid rgba(255,215,0,.5);padding:14px;font-family:'Oswald',sans-serif;
    font-size:1.1em;font-weight:700;cursor:pointer;text-transform:uppercase;
    letter-spacing:2px;border-radius:8px;transition:.3s;
}
.btn-zglos:hover{background:#ffd700;color:#000;box-shadow:0 0 25px rgba(255,215,0,.5)}

/* ══ ZGŁOSZENIE OCZEKUJĄCE (dla zaangażowanej pary) ══ */
.zgl-oczekuje{
    background:rgba(221,136,255,.08);border:1px solid rgba(221,136,255,.4);
    border-radius:10px;padding:20px;margin-bottom:20px;text-align:center;
    animation:zgl-glow 3s infinite;
}
@keyframes zgl-glow{0%,100%{box-shadow:0 0 15px rgba(221,136,255,.15)}50%{box-shadow:0 0 30px rgba(221,136,255,.4)}}
.zgl-tytul{font-family:'Oswald',sans-serif;color:#dd88ff;font-size:1.1em;
    text-transform:uppercase;letter-spacing:2px;margin-bottom:10px}
.zgl-status{color:#aaa;font-size:.95em;margin-bottom:10px}
.zgl-status b{color:#dd88ff}

/* ══ ZGŁOSZENIE DO POTWIERDZENIA (dla partnera) ══ */
.zgl-potwierdz{
    background:linear-gradient(135deg,rgba(255,51,102,.1),rgba(0,0,0,.3));
    border:1px solid rgba(255,51,102,.5);border-radius:12px;padding:24px;margin-bottom:24px;
    animation:zgl-pulse 2s infinite;
}
@keyframes zgl-pulse{0%,100%{box-shadow:0 0 20px rgba(255,51,102,.2)}50%{box-shadow:0 0 40px rgba(255,51,102,.5)}}
.zp-head{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.zp-avatar{width:60px;height:60px;border-radius:50%;background-size:cover;
    background-position:top center;border:2px solid #ff3366;flex-shrink:0}
.zp-text{color:#ccc}
.zp-text b{color:#ff3366;font-family:'Oswald',sans-serif;letter-spacing:.5px}
.zp-narracja{background:rgba(0,0,0,.5);border-left:3px solid rgba(255,51,102,.6);
    padding:16px 20px;color:#ccc;font-style:italic;font-size:.95em;line-height:1.7;margin-bottom:14px;
    border-radius:0 6px 6px 0}
.zp-akcje{display:flex;gap:10px}
.btn-tak{flex:1;background:rgba(255,51,102,.15);color:#ff3366;border:1px solid rgba(255,51,102,.5);
    padding:12px;font-family:'Oswald',sans-serif;font-size:1em;cursor:pointer;
    text-transform:uppercase;letter-spacing:1.5px;border-radius:6px;font-weight:700}
.btn-tak:hover{background:#ff3366;color:#fff;box-shadow:0 0 20px rgba(255,51,102,.5)}
.btn-nie{background:transparent;border:1px solid rgba(255,255,255,.15);color:#888;
    padding:12px 22px;font-family:'Oswald',sans-serif;cursor:pointer;text-transform:uppercase;border-radius:6px}
.btn-nie:hover{background:rgba(255,68,68,.1);color:#ff6666;border-color:rgba(255,68,68,.3)}

/* ══ JUŻ ŻONATY / ZAMĘŻNA ══ */
.zwiazek-info{
    background:linear-gradient(135deg,rgba(255,51,102,.08),rgba(221,136,255,.08));
    border:1px solid rgba(255,51,102,.3);border-radius:12px;padding:24px;margin-bottom:24px;
    text-align:center;
}
.z-serca{font-size:3em;margin-bottom:8px;filter:drop-shadow(0 0 20px #ff3366)}
.z-para{font-family:'Oswald',sans-serif;font-size:1.5em;color:#fff;
    margin-bottom:6px;letter-spacing:1px;text-transform:uppercase}
.z-para span{color:#ff3366}
.z-data{color:#aaa;font-size:.95em;margin-bottom:6px}
.z-dni{color:#dd88ff;font-family:'Oswald',sans-serif;font-size:1.1em;text-transform:uppercase;letter-spacing:1.5px;margin-top:8px;
    text-shadow:0 0 10px rgba(221,136,255,.4)}

/* ══ PANEL ADMINISTRACJI ══ */
.admin-panel{
    background:rgba(0,0,0,.5);border:1px solid rgba(0,255,136,.3);
    border-radius:12px;padding:4px;margin-bottom:24px;position:relative;
}
.admin-panel::before{
    content:'⛪ PANEL PROBOSZCZA / MG';
    position:absolute;top:-12px;left:20px;
    background:#0a0a12;color:#00ff88;padding:2px 14px;border-radius:12px;
    font-family:'Oswald',sans-serif;font-size:.8em;letter-spacing:2px;
    border:1px solid rgba(0,255,136,.4);
}
.admin-inner{padding:22px}

.admin-zgloszenie{
    background:rgba(10,10,18,.6);border:1px solid rgba(255,255,255,.08);
    border-radius:10px;padding:20px;margin-bottom:14px;
}
.az-para{display:flex;align-items:center;gap:15px;margin-bottom:14px;justify-content:center}
.az-gracz{display:flex;align-items:center;gap:10px}
.az-avatar{width:44px;height:44px;border-radius:50%;background-size:cover;background-position:top center;
    border:1px solid rgba(255,215,0,.3)}
.az-login{font-family:'Oswald',sans-serif;color:#fff;font-size:1em}
.az-serce{font-size:1.5em;color:#ff3366;filter:drop-shadow(0 0 8px #ff3366)}
.az-status{color:#ffaa00;font-size:.85em;font-family:'Oswald',sans-serif;text-transform:uppercase;text-align:center;margin-bottom:12px;letter-spacing:1px}
.az-narracja{background:rgba(0,0,0,.4);padding:14px 18px;border-left:3px solid rgba(255,215,0,.4);
    color:#bbb;font-style:italic;font-size:.9em;line-height:1.7;border-radius:0 6px 6px 0;margin-bottom:14px;
    max-height:200px;overflow-y:auto}
.az-akcje-admin{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.az-akcje-admin input{background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.1);
    color:#ddd;padding:8px 12px;border-radius:4px;font-family:'Open Sans',sans-serif;font-size:.9em}
.btn-zatwierdz{flex:1;background:rgba(0,255,136,.15);color:#00ff88;border:1px solid rgba(0,255,136,.4);
    padding:10px;font-family:'Oswald',sans-serif;cursor:pointer;border-radius:6px;text-transform:uppercase;letter-spacing:1px}
.btn-zatwierdz:hover{background:#00ff88;color:#000}
.btn-admin-odrzuc{background:transparent;border:1px solid rgba(255,68,68,.3);color:#ff6666;
    padding:10px 16px;font-family:'Oswald',sans-serif;cursor:pointer;border-radius:6px;text-transform:uppercase;font-size:.9em}
.btn-admin-odrzuc:hover{background:rgba(255,68,68,.15)}
.btn-udziel{width:100%;background:rgba(255,215,0,.15);color:#ffd700;border:1px solid rgba(255,215,0,.5);
    padding:12px;font-family:'Oswald',sans-serif;cursor:pointer;border-radius:6px;text-transform:uppercase;
    letter-spacing:1.5px;font-size:1em;margin-top:10px;font-weight:700;animation:udziel-glow 2s infinite}
@keyframes udziel-glow{0%,100%{box-shadow:0 0 10px rgba(255,215,0,.3)}50%{box-shadow:0 0 25px rgba(255,215,0,.6)}}
.btn-udziel:hover{background:#ffd700;color:#000}

/* ══ ZAPLANOWANE ŚLUBY ══ */
.zaplanowany{
    background:rgba(221,136,255,.05);border:1px solid rgba(221,136,255,.25);
    border-radius:8px;padding:14px 18px;margin-bottom:10px;
    display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
}
.zap-para{font-family:'Oswald',sans-serif;color:#fff;font-size:1em;letter-spacing:.5px}
.zap-para b{color:#dd88ff}
.zap-data{color:#ffd700;font-family:'Oswald',sans-serif;font-size:.9em;
    background:rgba(255,215,0,.08);padding:4px 12px;border-radius:14px;border:1px solid rgba(255,215,0,.2)}

/* ══ SPIS MAŁŻEŃSTW ══ */
.malzenstwa-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
.malz-karta{
    background:rgba(0,0,0,.4);border:1px solid rgba(255,51,102,.15);
    border-radius:10px;padding:16px;transition:.25s;
}
.malz-karta:hover{border-color:rgba(255,51,102,.4);background:rgba(255,51,102,.05);transform:translateY(-2px)}
.mk-para{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.mk-avatar{width:38px;height:38px;border-radius:50%;background-size:cover;background-position:top center;
    border:1px solid rgba(255,51,102,.3)}
.mk-serce{color:#ff3366;font-size:1.3em;filter:drop-shadow(0 0 6px #ff3366)}
.mk-login{font-family:'Oswald',sans-serif;color:#fff;font-size:.95em;letter-spacing:.3px}
.mk-info{display:flex;justify-content:space-between;font-size:.82em;color:#666;padding-top:10px;border-top:1px dashed rgba(255,255,255,.05)}
.mk-info b{color:#dd88ff}

.empty-box{padding:30px;text-align:center;color:#444;font-style:italic;
    border:1px dashed rgba(255,255,255,.08);border-radius:10px;font-size:.9em}

.sukces{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.4);color:#00ff88;
    padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
.blad{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.4);color:#ff6666;
    padding:14px 18px;margin-bottom:18px;border-radius:8px;text-align:center}
</style>

<!-- ══ NAGŁÓWEK KATEDRY (z obrazkiem) ══ -->
<div class="kat-header">
    <div class="kat-header-content">
        <div class="kat-ikona">⛪</div>
        <h1 class="kat-nazwa">Katedra Świętego Dymu</h1>
        <p class="kat-motto">❝ Gdzie dusze znajdują pokój, a serca łączą się na wieki ❞</p>
    </div>
</div>

<?php echo $komunikat; ?>

<!-- ══ PANEL ADMINISTRACJI (tylko dla MG/Proboszczów) ══ -->
<?php if ($ma_uprawnienia): ?>
<div class="admin-panel">
    <div class="admin-inner">

    <?php if (empty($zgloszenia_do_rozpatrzenia) && empty($zgloszenia_rozwodu)): ?>
        <div class="empty-box">Brak zgłoszeń oczekujących na rozpatrzenie.</div>
    <?php else:
        foreach ($zgloszenia_do_rozpatrzenia as $z):
            $av1 = !empty($z['avatar_1']) ? htmlspecialchars($z['avatar_1']) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
            $av2 = !empty($z['avatar_2']) ? htmlspecialchars($z['avatar_2']) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
            $czy_zatwierdzone = ($z['status'] == 'zatwierdzone');
    ?>
    <div class="admin-zgloszenie">
        <div class="az-para">
            <div class="az-gracz">
                <div class="az-avatar" style="background-image:url('<?php echo $av1; ?>')"></div>
                <div class="az-login"><?php echo htmlspecialchars($z['login_1']); ?></div>
            </div>
            <span class="az-serce">💕</span>
            <div class="az-gracz">
                <div class="az-avatar" style="background-image:url('<?php echo $av2; ?>')"></div>
                <div class="az-login"><?php echo htmlspecialchars($z['login_2']); ?></div>
            </div>
        </div>
        <div class="az-status">
            <?php if ($czy_zatwierdzone): ?>
                ✅ ZATWIERDZONE — ślub zaplanowany na <?php echo date('d.m.Y H:i', strtotime($z['planowana_data_slubu'])); ?>
            <?php else: ?>
                ⏳ Oboje potwierdzili — czeka na decyzję
            <?php endif; ?>
        </div>
        <div class="az-narracja">❝ <?php echo nl2br(htmlspecialchars($z['narracja_zaręczyn'])); ?> ❞</div>

        <?php if ($czy_zatwierdzone): ?>
            <!-- Udzielenie ślubu -->
            <form method="POST">
                <input type="hidden" name="zgl_id" value="<?php echo $z['id']; ?>">
                <button type="submit" name="udziel_slubu" class="btn-udziel">💒 Udziel sakramentu małżeństwa</button>
            </form>
        <?php else: ?>
            <!-- Zatwierdzenie / odrzucenie -->
            <form method="POST" class="az-akcje-admin" style="margin-bottom:8px">
                <input type="hidden" name="zgl_id" value="<?php echo $z['id']; ?>">
                <label style="color:#888;font-size:.82em;font-family:'Oswald',sans-serif">Data ślubu:</label>
                <input type="datetime-local" name="data_slubu" required>
                <button type="submit" name="zatwierdz_zgloszenie" class="btn-zatwierdz">✓ Zatwierdź</button>
            </form>
            <form method="POST" class="az-akcje-admin">
                <input type="hidden" name="zgl_id" value="<?php echo $z['id']; ?>">
                <input type="text" name="uzasadnienie" placeholder="Powód odrzucenia..." style="flex:1">
                <button type="submit" name="admin_odrzuc_zgloszenie" class="btn-admin-odrzuc">✗ Odrzuć</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- ══ ZGŁOSZENIA ROZWODOWE ══ -->
    <?php if (!empty($zgloszenia_rozwodu)):
        foreach($zgloszenia_rozwodu as $r):
            $av1 = !empty($r['avatar_1']) ? htmlspecialchars($r['avatar_1']) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
            $av2 = !empty($r['avatar_2']) ? htmlspecialchars($r['avatar_2']) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
            $dni_razem = floor((time() - strtotime($r['data_slubu'])) / 86400);
    ?>
    <div class="admin-zgloszenie" style="border-color:rgba(255,68,68,.3);background:rgba(255,68,68,.04)">
        <div style="text-align:center;font-family:'Oswald',sans-serif;color:#ff6666;font-size:.85em;text-transform:uppercase;letter-spacing:2px;margin-bottom:12px">
            ⚖️ Wniosek rozwodowy
        </div>
        <div class="az-para">
            <div class="az-gracz">
                <div class="az-avatar" style="background-image:url('<?php echo $av1; ?>')"></div>
                <div class="az-login"><?php echo htmlspecialchars($r['login_1']); ?></div>
            </div>
            <span class="az-serce" style="color:#ff4444;text-decoration:line-through">💔</span>
            <div class="az-gracz">
                <div class="az-avatar" style="background-image:url('<?php echo $av2; ?>')"></div>
                <div class="az-login"><?php echo htmlspecialchars($r['login_2']); ?></div>
            </div>
        </div>
        <div class="az-status" style="color:#ff6666">
            Razem <?php echo $dni_razem; ?> dni · Wniosek złożył/a: <b><?php echo htmlspecialchars($r['zglaszajacy_login']); ?></b>
        </div>
        <div class="az-narracja" style="border-left-color:rgba(255,68,68,.5)">
            <b style="color:#ff6666;display:block;margin-bottom:6px">Powód rozwodu:</b>
            ❝ <?php echo nl2br(htmlspecialchars($r['powod'])); ?> ❞
        </div>
        <form method="POST" class="az-akcje-admin" style="margin-bottom:8px">
            <input type="hidden" name="rozwod_id" value="<?php echo $r['id']; ?>">
            <button type="submit" name="zatwierdz_rozwod" class="btn-admin-odrzuc" style="flex:1;background:rgba(255,68,68,.15);color:#ff6666;border-color:rgba(255,68,68,.5)"
                onclick="return confirm('Na pewno rozwiązać to małżeństwo?')">
                ⚖️ Zatwierdź rozwód
            </button>
        </form>
        <form method="POST" class="az-akcje-admin">
            <input type="hidden" name="rozwod_id" value="<?php echo $r['id']; ?>">
            <input type="text" name="uzasadnienie_roz" placeholder="Dlaczego odrzucasz wniosek..." style="flex:1">
            <button type="submit" name="odrzuc_rozwod" class="btn-zatwierdz">✗ Odrzuć wniosek</button>
        </form>
    </div>
    <?php endforeach; endif; ?>

    <?php endif; ?>

    </div>
</div>
<?php endif; ?>

<!-- ══ ZGŁOSZENIA DO POTWIERDZENIA (dla partnera) ══ -->
<?php foreach($zgloszenia_do_potwierdzenia as $z):
    $av = !empty($z['od_avatar']) ? htmlspecialchars($z['od_avatar']) : "https://via.placeholder.com/100/0a0a0a/333?text=?";
?>
<div class="zgl-potwierdz">
    <div class="zp-head">
        <div class="zp-avatar" style="background-image:url('<?php echo $av; ?>')"></div>
        <div class="zp-text">
            <b><?php echo htmlspecialchars($z['od_login']); ?></b> zgłosił/a do Katedry wolę zawarcia z Tobą związku małżeńskiego 💍
            <div style="color:#888;font-size:.85em;margin-top:4px">Narracja zaręczyn poniżej:</div>
        </div>
    </div>
    <div class="zp-narracja">❝ <?php echo nl2br(htmlspecialchars($z['narracja_zaręczyn'])); ?> ❞</div>
    <form method="POST" class="zp-akcje">
        <input type="hidden" name="zgl_id" value="<?php echo $z['id']; ?>">
        <button type="submit" name="potwierdz_zgloszenie" class="btn-tak">💖 Potwierdzam wolę</button>
        <button type="submit" name="odrzuc_zgloszenie" class="btn-nie">Odrzuć</button>
    </form>
</div>
<?php endforeach; ?>

<!-- ══ CO JA TU ROBIĘ ══ -->
<?php if ($moje_malzenstwo):
    $partner_login = ($moje_malzenstwo['malzonek_1_id'] == $id_gracza) ? $moje_malzenstwo['m2_login'] : $moje_malzenstwo['m1_login'];
    $dni = floor((time() - strtotime($moje_malzenstwo['data_slubu'])) / 86400);
?>
<div class="zwiazek-info">
    <div class="z-serca">💍</div>
    <div class="z-para"><?php echo htmlspecialchars($moje_malzenstwo['m1_login']); ?> <span>& ❤️</span> <?php echo htmlspecialchars($moje_malzenstwo['m2_login']); ?></div>
    <div class="z-data">Ślub: <?php echo date('d.m.Y', strtotime($moje_malzenstwo['data_slubu'])); ?></div>
    <div class="z-dni">🕊️ Razem od <?php echo $dni; ?> <?php echo $dni==1?'dnia':'dni'; ?></div>
</div>

<!-- ══ ROZWÓD ══ -->
<?php if ($moje_zgl_rozwodu): ?>
<div class="zgl-oczekuje" style="border-color:rgba(255,68,68,.4);background:rgba(255,68,68,.06)">
    <div class="zgl-tytul" style="color:#ff6666">⚖️ Wniosek rozwodowy w toku</div>
    <div class="zgl-status" style="color:#aaa">
        Wniosek złożony <b><?php echo date('d.m.Y H:i', strtotime($moje_zgl_rozwodu['data_zgloszenia'])); ?></b><br>
        Czeka na rozpatrzenie przez Proboszcza lub Mistrza Gry.
    </div>
</div>
<?php else: ?>
<details style="background:rgba(255,68,68,.03);border:1px solid rgba(255,68,68,.15);border-radius:10px;padding:14px 18px;margin-bottom:20px">
    <summary style="cursor:pointer;color:#ff6666;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.5px;font-size:.9em;padding:4px 0">
        💔 Chcesz złożyć wniosek rozwodowy?
    </summary>
    <div style="padding-top:14px">
        <p style="color:#888;font-size:.88em;font-style:italic;margin-bottom:12px">
            Rozwód jest poważną decyzją. Twój wniosek rozpatrzy Proboszcz lub Mistrz Gry po wysłuchaniu obu stron. Opisz powód w minimum 50 znakach.
        </p>
        <form method="POST">
            <textarea name="powod_rozwodu" required minlength="50" maxlength="2000" placeholder="Opisz powód rozwodu. Może być narracyjnie, z opisem sytuacji w postaci. Np. 'Po aferze w klubie z Katarzyną moja postać straciła do niej zaufanie...'"
                style="width:100%;background:rgba(0,0,0,.6);border:1px solid rgba(255,68,68,.3);color:#ddd;padding:12px;border-radius:6px;font-family:'Open Sans',sans-serif;font-size:.92em;min-height:120px;box-sizing:border-box;resize:vertical;margin-bottom:10px"></textarea>
            <button type="submit" name="zglos_rozwod" onclick="return confirm('Na pewno złożyć wniosek rozwodowy?')"
                style="width:100%;background:rgba(255,68,68,.12);color:#ff6666;border:1px solid rgba(255,68,68,.4);padding:12px;font-family:'Oswald',sans-serif;cursor:pointer;text-transform:uppercase;letter-spacing:1.5px;border-radius:6px;font-weight:700">
                ⚖️ Złóż wniosek rozwodowy
            </button>
        </form>
    </div>
</details>
<?php endif; ?>

<?php elseif ($moje_zgloszenie):
    $p_login = $moje_zgloszenie['partner_login'];
    $status_tekst = match($moje_zgloszenie['status']){
        'oczekuje'          => "⏳ Oczekiwanie na potwierdzenie przez <b>".htmlspecialchars($p_login)."</b>",
        'partner_potwierdzil' => "✅ Oboje potwierdziliście! Czekacie na decyzję Proboszcza/MG",
        'zatwierdzone'      => "💒 <b>Zatwierdzone!</b> Ślub zaplanowany na <b>".date('d.m.Y H:i', strtotime($moje_zgloszenie['planowana_data_slubu']))."</b>",
        default             => ''
    };
?>
<div class="zgl-oczekuje">
    <div class="zgl-tytul">💍 Twoje zgłoszenie małżeńskie</div>
    <div class="zgl-status"><?php echo $status_tekst; ?></div>
</div>

<?php else: ?>
<!-- ══ FORMULARZ ZGŁOSZENIA ══ -->
<div class="zglos-box">
    <div class="zglos-tytul">💍 Zgłoś wolę wejścia w związek małżeński</div>
    <div class="zglos-podtytul">Proboszcz lub Mistrz Gry rozpatrzy Twoje zgłoszenie i wyznaczy datę ceremonii w Centrum Opowieści</div>

    <form method="POST" class="zglos-form">
        <label>ID wybranka/wybranki</label>
        <input type="number" name="partner_id" required min="1" placeholder="np. 42">

        <label>Narracja zaręczyn</label>
        <textarea name="narracja" id="narracja" required minlength="100" maxlength="3000"
            placeholder="Opisz w formie narracyjnej jak wyglądały zaręczyny. Może być z dialogami. Przykład:

*Marek klęknął pod starą latarnią przy moście brooklyńskim. Z kieszeni wyjął aksamitne pudełko.*

— Od chwili, gdy cię ujrzałem w tej obskurnej melinie, wiedziałem, że jesteś tą jedyną. Nawet gdy strzelałaś do mnie w pierwszym naszym spotkaniu...

*Anna parsknęła śmiechem, łzy spłynęły jej po policzku.*

— Idioto. Tak, wyjdę za ciebie..."></textarea>
        <div class="licznik-znakow" id="licznik">0 / 3000 znaków (min. 100)</div>

        <button type="submit" name="zglos_slub" class="btn-zglos">⛪ Złóż zgłoszenie do Katedry</button>
    </form>
</div>

<script>
const ta = document.getElementById('narracja');
const licznik = document.getElementById('licznik');
ta.addEventListener('input', () => {
    const len = ta.value.length;
    licznik.innerText = len + ' / 3000 znaków (min. 100)';
    licznik.className = 'licznik-znakow ' + (len >= 100 ? 'ok' : 'za-malo');
});
</script>
<?php endif; ?>

<!-- ══ ZAPLANOWANE ŚLUBY ══ -->
<?php if (!empty($zaplanowane_sluby)): ?>
<div class="sekcja-tytul">
    📅 Zaplanowane ceremonie
    <span class="licznik"><?php echo count($zaplanowane_sluby); ?></span>
</div>
<?php foreach($zaplanowane_sluby as $z): ?>
<div class="zaplanowany">
    <div class="zap-para">💒 <b><?php echo htmlspecialchars($z['login_1']); ?></b> & <b><?php echo htmlspecialchars($z['login_2']); ?></b></div>
    <div class="zap-data">📅 <?php echo date('d.m.Y H:i', strtotime($z['planowana_data_slubu'])); ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ══ SPIS MAŁŻEŃSTW ══ -->
<div class="sekcja-tytul">
    💒 Zawarte małżeństwa
    <span class="licznik"><?php echo count($malzenstwa_spis); ?></span>
</div>

<?php if (empty($malzenstwa_spis)): ?>
    <div class="empty-box">Jeszcze żadna para nie stanęła na ślubnym kobiercu w naszej Katedrze.</div>
<?php else: ?>
<div class="malzenstwa-grid">
<?php foreach($malzenstwa_spis as $m):
    $av1 = !empty($m['m1_avatar']) ? htmlspecialchars($m['m1_avatar']) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
    $av2 = !empty($m['m2_avatar']) ? htmlspecialchars($m['m2_avatar']) : "https://via.placeholder.com/80/0a0a0a/333?text=?";
?>
<div class="malz-karta">
    <div class="mk-para">
        <div class="mk-avatar" style="background-image:url('<?php echo $av1; ?>')"></div>
        <span class="mk-login"><?php echo htmlspecialchars($m['m1_login']); ?></span>
        <span class="mk-serce">💕</span>
        <span class="mk-login"><?php echo htmlspecialchars($m['m2_login']); ?></span>
        <div class="mk-avatar" style="background-image:url('<?php echo $av2; ?>')"></div>
    </div>
    <div class="mk-info">
        <span>Ślub: <?php echo date('d.m.Y', strtotime($m['data_slubu'])); ?></span>
        <span>Razem: <b><?php echo $m['dni_razem']; ?> dni</b></span>
    </div>
    <?php if ($m['udzielajacy_login']): ?>
    <div style="font-size:.75em;color:#444;margin-top:6px;text-align:center;font-style:italic">
        Ślubu udzielił/a: <?php echo htmlspecialchars($m['udzielajacy_login']); ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>