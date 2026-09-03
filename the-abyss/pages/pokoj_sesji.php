<?php
require_once "db.php";
require_once __DIR__ . '/../config/pochodzenia.php';
require_once __DIR__ . '/../config/zawody.php';
require_once __DIR__ . '/../config/rp_helpers.php';

$id_gracza = $_SESSION['id_gracza'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>window.location.href='game.php?page=sesje';</script>"; exit;
}
$sesja_id = (int)$_GET['id'];

// ── KATALOGI (spójne z sesje.php) ─────────────────────────────
$GATUNKI_IKONY = [
    'Obyczajowa'=>'💬','Kryminalna'=>'🔪','Śledztwo'=>'🔍','Akcja'=>'⚔️',
    'Horror'=>'👻','Romans'=>'💍','Więzienie'=>'🔒','Polityczna'=>'🏛️','Inna'=>'❓'
];
$KAT_KOLORY = [
    'Główna Fabuła'=>'var(--neon-gold)',
    'Publiczna'=>'var(--neon-cyan)',
    'Prywatna'=>'var(--neon-ember)',
    'Rekrutacyjna'=>'var(--neon-red)',
];

// Mapowanie poziomu ryzyka rzutu → próg trudności (ST)
$RYZYKO_PT = [
    'Niskie'      => ['pt' => 10, 'kolor' => 'var(--neon-green)', 'opis' => 'Sytuacja banalna. Bez wpływu na cel sesji.'],
    'Średnie'     => ['pt' => 15, 'kolor' => 'var(--neon-gold)',  'opis' => 'Akcja wpływa na fabułę. Grozi lekkim obrażeniem.'],
    'Wysokie'     => ['pt' => 20, 'kolor' => 'var(--neon-ember)', 'opis' => 'Akcja kluczowa. Poważne ryzyko dla postaci.'],
    'Ekstremalne' => ['pt' => 25, 'kolor' => 'var(--neon-red-hot)', 'opis' => 'Desperackie. Możliwa śmierć lub trwałe okaleczenie.'],
];

// ── PAGINACJA ─────────────────────────────────────────────────
$sf = isset($_GET['sf']) ? max(1, (int)$_GET['sf']) : 1;
$limit_fabula = 10;
$offset_fabula = ($sf - 1) * $limit_fabula;

// ── DANE SESJI ────────────────────────────────────────────────
$sql_sesja = "SELECT s.*, g.login AS nazwa_wlasciciela FROM sesje_rpg s JOIN gracze g ON s.mg_id=g.id WHERE s.id=$sesja_id";
$wynik_sesja = $polaczenie->query($sql_sesja);
if ($wynik_sesja->num_rows == 0) {
    echo "<div style='padding:30px;text-align:center;color:var(--neon-red-hot);background:rgba(0,0,0,0.5);border:1px solid var(--border-mid);border-radius:2px'>⚠ Taka sesja nie istnieje.</div>"; exit;
}
$sesja = $wynik_sesja->fetch_assoc();
$wlasciciel_id = $sesja['mg_id'];
$czy_wlasciciel = ($id_gracza == $wlasciciel_id);
$czy_zakonczona = ($sesja['status'] == 'Zakończona');

// ── DANE GRACZA (pełne, dla bonusów RP) ───────────────────────
$gracz = $polaczenie->query("SELECT * FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$moj_login = $gracz['login'];
$umiejetnosci_gracza = !empty($gracz['umiejetnosci']) ? json_decode($gracz['umiejetnosci'], true) : [];
$zalety_gracza = ($gracz['zalety']=='Brak' || empty($gracz['zalety'])) ? [] : array_map('trim', explode(", ", $gracz['zalety']));
$wady_gracza   = ($gracz['wady']  =='Brak' || empty($gracz['wady']))   ? [] : array_map('trim', explode(", ", $gracz['wady']));

// ── STATUS UCZESTNIKA ─────────────────────────────────────────
$sql_uczestnik = "SELECT rola, status_akceptacji FROM sesje_uczestnicy WHERE sesja_id=$sesja_id AND gracz_id=$id_gracza";
$wynik_uczestnik = $polaczenie->query($sql_uczestnik);
$uczestnik = $wynik_uczestnik->fetch_assoc();
$czy_bierze_udzial = ($wynik_uczestnik->num_rows > 0);
$czy_zaakceptowany = ($czy_bierze_udzial && $uczestnik['status_akceptacji'] == 'Zaakceptowany');
$czy_mg = ($czy_zaakceptowany && ($uczestnik['rola'] == 'Mistrz Gry' || $czy_wlasciciel));

// ── LICZNIK POSTÓW FABUŁY (dla paginacji) ─────────────────────
$row_cnt = $polaczenie->query("SELECT COUNT(*) c FROM sesje_posty WHERE sesja_id=$sesja_id AND typ_postu != 'OffTop'")->fetch_assoc();
$total_fabula = (int)$row_cnt['c'];
$stron_fabula = max(1, (int)ceil($total_fabula / $limit_fabula));

// ═══════════════════════════════════════════════════════════════
// AKCJE POST
// ═══════════════════════════════════════════════════════════════

// DOŁĄCZANIE DO SESJI
if (isset($_POST['dolacz_do_sesji']) && !$czy_bierze_udzial && !$czy_zakonczona) {
    // Publiczna = od razu zaakceptowany; Główna/Prywatna/Rekrutacyjna = oczekuje
    $status_pocz = ($sesja['kategoria'] == 'Publiczna') ? 'Zaakceptowany' : 'Oczekuje';
    $polaczenie->query("INSERT INTO sesje_uczestnicy (sesja_id, gracz_id, rola, status_akceptacji) VALUES ($sesja_id, $id_gracza, 'Gracz', '$status_pocz')");

    $tytul_safe = htmlspecialchars($sesja['tytul']);
    $login_safe = htmlspecialchars($moj_login);
    $tresc_pow = ($status_pocz == 'Oczekuje')
        ? "Gracz <b style='color:var(--neon-green)'>$login_safe</b> prosi o dołączenie do sesji <i>$tytul_safe</i>. Wejdź w Ustawienia MG."
        : "Gracz <b style='color:var(--neon-green)'>$login_safe</b> dołączył do Twojej sesji <i>$tytul_safe</i>.";
    $tresc_pow_safe = $polaczenie->real_escape_string($tresc_pow);
    $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($wlasciciel_id, '$tresc_pow_safe')");
    echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$sesja_id';</script>"; exit;
}

// USTAWIENIA MG + AKCJE NA GRACZACH
if ($czy_mg) {
    if (isset($_POST['zapisz_ustawienia_sesji'])) {
        $nowy_status = $polaczenie->real_escape_string($_POST['status_sesji']);
        $nowa_trudnosc = $polaczenie->real_escape_string($_POST['trudnosc']);
        $nowe_tagi = $polaczenie->real_escape_string(trim($_POST['tagi_sesji']));
        $nowe_ostrz = $polaczenie->real_escape_string(trim($_POST['ostrzezenia']));
        $nowy_opis = $polaczenie->real_escape_string(trim($_POST['opis_sesji']));
        $polaczenie->query("UPDATE sesje_rpg SET status='$nowy_status', poziom_trudnosci='$nowa_trudnosc', tagi='$nowe_tagi', ostrzezenia='$nowe_ostrz', opis='$nowy_opis' WHERE id=$sesja_id");
        echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=ustawienia';</script>"; exit;
    }
    if (isset($_POST['akcja_gracz'])) {
        $cel_id = (int)$_POST['cel_id'];
        $akcja = $_POST['typ_akcji'] ?? $_POST['akcja_gracz'];
        $tytul_safe = htmlspecialchars($sesja['tytul']);
        if ($cel_id != $wlasciciel_id) {
            if ($akcja == 'akceptuj') {
                $polaczenie->query("UPDATE sesje_uczestnicy SET status_akceptacji='Zaakceptowany' WHERE sesja_id=$sesja_id AND gracz_id=$cel_id");
                $tresc = "Zostałeś <b style='color:var(--neon-green)'>zaakceptowany</b> w sesji <i>$tytul_safe</i>.";
                $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '".$polaczenie->real_escape_string($tresc)."')");
            } elseif ($akcja == 'wyrzuc') {
                $polaczenie->query("DELETE FROM sesje_uczestnicy WHERE sesja_id=$sesja_id AND gracz_id=$cel_id");
                $tresc = "Zostałeś <b style='color:var(--neon-red-hot)'>usunięty</b> z sesji <i>$tytul_safe</i>.";
                $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cel_id, '".$polaczenie->real_escape_string($tresc)."')");
            } elseif ($akcja == 'awans_mg') {
                $polaczenie->query("UPDATE sesje_uczestnicy SET rola='Mistrz Gry' WHERE sesja_id=$sesja_id AND gracz_id=$cel_id");
            } elseif ($akcja == 'degradacja_gracz') {
                $polaczenie->query("UPDATE sesje_uczestnicy SET rola='Gracz' WHERE sesja_id=$sesja_id AND gracz_id=$cel_id");
            }
        }
        echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=ustawienia';</script>"; exit;
    }

    // ── PODSUMOWANIE SESJI (zakończenie + reputacja + konsekwencje) ──
    if (isset($_POST['zakoncz_sesje'])) {
        $podsumowanie_tresc = $polaczenie->real_escape_string(trim($_POST['podsumowanie_mg']));

        // Tablica wad — do walidacji konsekwencji
        $wady_dozwolone = [
            // ─ Fizyczne: urazy, okaleczenia, upośledzenia zmysłów
            "Brak Kończyny","Jednooki","Głuchy","Całkowita Ślepota","Oszpecony","Utykający","Hemofiliak",
            "Astma","Krótkowidz","Daltonizm","Niedosłuch","Wolne Gojenie","Jąkanie","Migreny","Bezsenność",
            // ─ Psychiczne: traumy, fobie, zaburzenia pourazowe
            "Trauma Pourazowa","Depresja","Lęki Napadowe","Klaustrofobia","Lęk Wysokości","Lęk Tłumu",
            "Paranoik","Tchórz","Furiat","Odludek","Naiwny","Mizantrop","Brak Empatii",
            // ─ Nałogi i zaburzenia charakteru po przeżyciu
            "Nałogowiec","Hazardzista","Kleptomania",
            // ─ Społeczne
            "Zła Reputacja","Gadatliwy",
            // ─ Umysł (po urazach głowy, traumach)
            "Ociężały Umysł",
            // ─ Specyficzne
            "Pechowiec","Leniwy","Słabeusz"
        ];

        $reps = $_POST['rep']     ?? []; // [gracz_id][grupa] => int
        $notes = $_POST['notatka'] ?? []; // [gracz_id] => string
        $kons_w = $_POST['kons_wada'] ?? []; // [gracz_id] => nazwa wady lub ''
        $kons_o = $_POST['kons_opis'] ?? []; // [gracz_id] => string

        // Iteruj wszystkich zaakceptowanych uczestników (poza MG)
        $uczestnicy_final = $polaczenie->query("
            SELECT u.gracz_id, u.rola FROM sesje_uczestnicy u
            WHERE u.sesja_id=$sesja_id AND u.status_akceptacji='Zaakceptowany'
        ");

        while ($u = $uczestnicy_final->fetch_assoc()) {
            $gid = (int)$u['gracz_id'];

            // MG-owie też mogą dostać podsumowanie, ale normalnie to tylko gracze
            if ($u['rola'] == 'Mistrz Gry') continue;

            $r_elita = (int)max(-3, min(3, $reps[$gid]['elita']         ?? 0));
            $r_ulica = (int)max(-3, min(3, $reps[$gid]['ulica']         ?? 0));
            $r_synd  = (int)max(-3, min(3, $reps[$gid]['syndykat']      ?? 0));
            $r_wladz = (int)max(-3, min(3, $reps[$gid]['wladze']        ?? 0));
            $r_spol  = (int)max(-3, min(3, $reps[$gid]['spoleczenstwo'] ?? 0));

            $notatka = $polaczenie->real_escape_string(trim($notes[$gid] ?? ''));
            $wada_nz = trim($kons_w[$gid] ?? '');
            $wada = (in_array($wada_nz, $wady_dozwolone)) ? $polaczenie->real_escape_string($wada_nz) : '';
            $opis_kons = $polaczenie->real_escape_string(trim($kons_o[$gid] ?? ''));

            // UPSERT — zabezpieczenie przed ponownym zakończeniem
            $polaczenie->query("INSERT INTO sesje_podsumowanie
                (sesja_id, gracz_id, mg_id, reputacja_elita, reputacja_ulica, reputacja_syndykat, reputacja_wladze, reputacja_spoleczenstwo, notatka_mg, konsekwencja_wada, konsekwencja_opis)
                VALUES ($sesja_id, $gid, $id_gracza, $r_elita, $r_ulica, $r_synd, $r_wladz, $r_spol, '$notatka',
                    " . ($wada !== '' ? "'$wada'" : "NULL") . ",
                    " . ($opis_kons !== '' ? "'$opis_kons'" : "NULL") . ")
                ON DUPLICATE KEY UPDATE
                    reputacja_elita=$r_elita, reputacja_ulica=$r_ulica, reputacja_syndykat=$r_synd,
                    reputacja_wladze=$r_wladz, reputacja_spoleczenstwo=$r_spol,
                    notatka_mg='$notatka',
                    konsekwencja_wada=" . ($wada !== '' ? "'$wada'" : "NULL") . ",
                    konsekwencja_opis=" . ($opis_kons !== '' ? "'$opis_kons'" : "NULL")
            );

            // AGREGACJA reputacja_sesyjna w tabeli gracze
            $row_g = $polaczenie->query("SELECT reputacja_sesyjna FROM gracze WHERE id=$gid")->fetch_assoc();
            $rep_old = $row_g['reputacja_sesyjna'] ? json_decode($row_g['reputacja_sesyjna'], true) : ['elita'=>0,'ulica'=>0,'syndykat'=>0,'wladze'=>0,'spoleczenstwo'=>0];
            $rep_new = [
                'elita'         => ($rep_old['elita']         ?? 0) + $r_elita,
                'ulica'         => ($rep_old['ulica']         ?? 0) + $r_ulica,
                'syndykat'      => ($rep_old['syndykat']      ?? 0) + $r_synd,
                'wladze'        => ($rep_old['wladze']        ?? 0) + $r_wladz,
                'spoleczenstwo' => ($rep_old['spoleczenstwo'] ?? 0) + $r_spol,
            ];
            $rep_json_safe = $polaczenie->real_escape_string(json_encode($rep_new, JSON_UNESCAPED_UNICODE));
            $polaczenie->query("UPDATE gracze SET reputacja_sesyjna='$rep_json_safe' WHERE id=$gid");

            // KONSEKWENCJA: dopisanie wady do postaci (jeśli nie ma jej jeszcze)
            if ($wada !== '') {
                $wady_row = $polaczenie->query("SELECT wady FROM gracze WHERE id=$gid")->fetch_assoc();
                $wady_obecne = ($wady_row['wady']=='Brak'||empty($wady_row['wady'])) ? [] : array_map('trim', explode(", ", $wady_row['wady']));
                if (!in_array($wada, $wady_obecne)) {
                    $wady_obecne[] = $wada;
                    $wady_nowe = $polaczenie->real_escape_string(implode(", ", $wady_obecne));
                    $polaczenie->query("UPDATE gracze SET wady='$wady_nowe' WHERE id=$gid");
                }
            }

            // POWIADOMIENIE DLA GRACZA
            $tytul_safe = htmlspecialchars($sesja['tytul']);
            $tresc_pow = "Sesja <i>$tytul_safe</i> została zakończona. MG przygotował podsumowanie Twojej postaci. <a href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=podsumowanie' style='color:var(--neon-cyan)'>[ Przejdź ]</a>";
            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($gid, '".$polaczenie->real_escape_string($tresc_pow)."')");
        }

        // ZAMKNIĘCIE SESJI
        $polaczenie->query("UPDATE sesje_rpg SET status='Zakończona', data_zakonczenia=NOW(), podsumowanie_mg='$podsumowanie_tresc' WHERE id=$sesja_id");
        echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=podsumowanie';</script>"; exit;
    }
}

// EDYCJA POSTA
if (isset($_POST['zapisz_edycje']) && $czy_zaakceptowany && !$czy_zakonczona) {
    $post_id = (int)$_POST['post_id'];
    $nowa_tresc = trim($_POST['nowa_tresc']);
    $zakladka_powrot = $polaczenie->real_escape_string($_POST['zakladka_powrot']);
    $sf_powrot = isset($_POST['sf']) ? (int)$_POST['sf'] : 1;
    if (strlen($nowa_tresc) > 0) {
        $nowa_tresc_safe = $polaczenie->real_escape_string($nowa_tresc);
        $sprawdz = $polaczenie->query("SELECT autor_id, typ_postu FROM sesje_posty WHERE id=$post_id")->fetch_assoc();
        if ($sprawdz && $sprawdz['typ_postu'] != 'Rzut_Koscia' && ($sprawdz['autor_id'] == $id_gracza || $czy_mg)) {
            $polaczenie->query("UPDATE sesje_posty SET tresc='$nowa_tresc_safe' WHERE id=$post_id");
        }
        echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=$zakladka_powrot&sf=$sf_powrot';</script>"; exit;
    }
}

// DODAWANIE POSTA + RZUT KOŚCIĄ
if ($czy_zaakceptowany && !$czy_zakonczona) {
    if (isset($_POST['dodaj_post'])) {
        $tresc = trim($_POST['tresc_postu']);
        $typ_postu = $polaczenie->real_escape_string($_POST['typ_postu']);
        if ($typ_postu != 'Fabuła' && $typ_postu != 'OffTop') $typ_postu = 'Fabuła';
        if (strlen($tresc) > 0) {
            $tresc_safe = $polaczenie->real_escape_string($tresc);
            $polaczenie->query("INSERT INTO sesje_posty (sesja_id, autor_id, typ_postu, tresc) VALUES ($sesja_id, $id_gracza, '$typ_postu', '$tresc_safe')");
            $polaczenie->query("UPDATE sesje_rpg SET ostatnia_aktywnosc=NOW() WHERE id=$sesja_id");

            // Wzmianki @Nick
            preg_match_all('/@([a-zA-Z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+)/u', $tresc, $matches);
            if (!empty($matches[1])) {
                $wspomniani = array_unique($matches[1]);
                $tytul_safe = htmlspecialchars($sesja['tytul']);
                $typ_nazwa = ($typ_postu == 'OffTop') ? 'OffTopie' : 'wpisie fabularnym';
                $url_z = urlencode($typ_postu);
                foreach ($wspomniani as $nick) {
                    $nick_safe = $polaczenie->real_escape_string($nick);
                    if (strtolower($nick_safe) != strtolower($moj_login)) {
                        $wu = $polaczenie->query("SELECT id FROM gracze WHERE login='$nick_safe'");
                        if ($wu && $wu->num_rows > 0) {
                            $cid = $wu->fetch_assoc()['id'];
                            $tp = "Gracz <b style='color:var(--neon-green)'>$moj_login</b> wspomniał o Tobie w $typ_nazwa (<i>$tytul_safe</i>). <a href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=$url_z' style='color:var(--neon-cyan)'>[ Przejdź ]</a>";
                            $polaczenie->query("INSERT INTO powiadomienia (gracz_id, tresc) VALUES ($cid, '".$polaczenie->real_escape_string($tp)."')");
                        }
                    }
                }
            }

            $ostatnia_strona = max(1, (int)ceil(($total_fabula + 1) / $limit_fabula));
            $url = "game.php?page=pokoj_sesji&id=$sesja_id&zakladka=" . ($typ_postu=='OffTop' ? 'offtop' : 'wpisy');
            if ($typ_postu != 'OffTop') $url .= "&sf=$ostatnia_strona";
            echo "<script>window.location.href='$url';</script>"; exit;
        }
    }

    // ═══ RZUT KOŚCIĄ d20 — PEŁNA FORMUŁA RP ═══
    if (isset($_POST['wykonaj_rzut'])) {
        $wybrane_um    = isset($_POST['um_rzut'])    ? $_POST['um_rzut']    : [];
        $wybrane_cechy = isset($_POST['cechy_rzut']) ? $_POST['cechy_rzut'] : [];
        $ryzyko        = isset($_POST['ryzyko'])     ? $_POST['ryzyko']     : 'Niskie';
        $akcja_opis    = trim($_POST['akcja_opis'] ?? '');

        if (!isset($RYZYKO_PT[$ryzyko])) $ryzyko = 'Niskie';
        $pt = $RYZYKO_PT[$ryzyko]['pt'];
        $ryzyko_kolor = $RYZYKO_PT[$ryzyko]['kolor'];

        // 1) Umiejętności — dla każdej: bonus_rp_umiejetnosci()
        //    To daje (PU + flat pochodzenia) × mnożnik zawodu, czyli full RP formuła.
        $suma_um = 0;
        $um_parts = [];
        foreach ($wybrane_um as $um_nazwa) {
            if (!isset($umiejetnosci_gracza[$um_nazwa])) continue;
            $w = bonus_rp_umiejetnosci($gracz, $um_nazwa);
            $suma_um += $w['wartosc_koncowa'];
            $skladowe = $w['baza_pu'];
            if ($w['pochodzenie'] > 0) $skladowe .= "+{$w['pochodzenie']}";
            elseif ($w['pochodzenie'] < 0) $skladowe .= "{$w['pochodzenie']}";
            if ($w['zawod_proc'] > 0) $skladowe .= "×" . number_format($w['mnoznik_zawodu'], 2);
            $um_parts[] = htmlspecialchars($um_nazwa) . " [" . $skladowe . " = <b>" . $w['wartosc_koncowa'] . "</b>]";
        }

        // 2) Cechy — ±3 za każdą
        $suma_cech = 0;
        $cechy_parts = [];
        foreach ($wybrane_cechy as $c) {
            $c = trim($c);
            if (in_array($c, $zalety_gracza)) {
                $suma_cech += 3;
                $cechy_parts[] = "<span style='color:var(--neon-green)'>" . htmlspecialchars($c) . " +3</span>";
            } elseif (in_array($c, $wady_gracza)) {
                $suma_cech -= 3;
                $cechy_parts[] = "<span style='color:var(--neon-red-hot)'>" . htmlspecialchars($c) . " −3</span>";
            }
        }

        // 3) d20
        $d20 = rand(1, 20);
        $krytyk = "";
        if ($d20 == 20) $krytyk = " <span style='color:var(--neon-gold);font-weight:700;letter-spacing:1px'>★ KRYTYK ★</span>";
        if ($d20 == 1)  $krytyk = " <span style='color:var(--neon-red-hot);font-weight:700;letter-spacing:1px'>☠ FUMBLE ☠</span>";

        // 4) Suma i werdykt — floor na bonusach, bo d20 jest liczbą całkowitą
        $bonus_int = (int)floor($suma_um) + $suma_cech;
        $suma = $d20 + $bonus_int;
        $sukces = ($suma >= $pt);
        $kolor_w = $sukces ? 'var(--neon-green)' : 'var(--neon-red-hot)';
        $tekst_w = $sukces ? 'SUKCES' : 'PORAŻKA';

        // 5) Formatowanie postu
        $html  = "<div class='rzut-box'>";
        $html .= "<div class='rzut-head'><span class='rzut-icon'>🎲</span> <b>Test kostką d20</b> — <span style='color:$ryzyko_kolor'>Ryzyko $ryzyko (PT $pt)</span></div>";
        if ($akcja_opis !== '') {
            $html .= "<div class='rzut-akcja'>&bdquo;" . htmlspecialchars($akcja_opis) . "&rdquo;</div>";
        }
        $html .= "<div class='rzut-row'><span class='rzut-lbl'>Rzut d20:</span> <span class='rzut-val'>$d20</span>$krytyk</div>";
        if (!empty($um_parts)) {
            $html .= "<div class='rzut-row'><span class='rzut-lbl'>Umiejętności:</span> " . implode(' · ', $um_parts) . " → <b style='color:var(--neon-cyan)'>+" . number_format($suma_um, 1) . "</b></div>";
        }
        if (!empty($cechy_parts)) {
            $html .= "<div class='rzut-row'><span class='rzut-lbl'>Cechy:</span> " . implode(' · ', $cechy_parts) . " → <b>" . ($suma_cech >= 0 ? "+$suma_cech" : $suma_cech) . "</b></div>";
        }
        $html .= "<div class='rzut-wynik'>Suma: <b>$suma</b> / $pt &nbsp;·&nbsp; <b style='color:$kolor_w;letter-spacing:2px'>[$tekst_w]</b></div>";
        $html .= "</div>";

        $html_safe = $polaczenie->real_escape_string($html);
        $polaczenie->query("INSERT INTO sesje_posty (sesja_id, autor_id, typ_postu, tresc) VALUES ($sesja_id, $id_gracza, 'Rzut_Koscia', '$html_safe')");
        $polaczenie->query("UPDATE sesje_rpg SET ostatnia_aktywnosc=NOW() WHERE id=$sesja_id");

        $ostatnia_strona = max(1, (int)ceil(($total_fabula + 1) / $limit_fabula));
        echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$sesja_id&zakladka=wpisy&sf=$ostatnia_strona';</script>"; exit;
    }
}

// ── WYSZUKIWANIE ──────────────────────────────────────────────
$wyniki_wysz = null; $blad_wysz = "";
if (isset($_POST['szukaj_postow'])) {
    $fraza = trim($_POST['fraza']);
    if (mb_strlen($fraza) >= 3) {
        $fraza_s = $polaczenie->real_escape_string($fraza);
        $wyniki_wysz = $polaczenie->query("
            SELECT p.*, g.login, g.avatar, u.rola
            FROM sesje_posty p JOIN gracze g ON p.autor_id=g.id
            LEFT JOIN sesje_uczestnicy u ON (u.sesja_id=p.sesja_id AND u.gracz_id=p.autor_id)
            WHERE p.sesja_id=$sesja_id AND p.tresc LIKE '%$fraza_s%'
            ORDER BY p.data_dodania DESC LIMIT 50
        ");
    } else { $blad_wysz = "Minimum 3 znaki."; }
}

// ── UCZESTNICY ────────────────────────────────────────────────
$sql_gracze = "SELECT u.rola, u.status_akceptacji, g.id, g.login, g.avatar, g.pochodzenie, g.profesja_fabularna
               FROM sesje_uczestnicy u JOIN gracze g ON u.gracz_id=g.id
               WHERE u.sesja_id=$sesja_id
               ORDER BY FIELD(u.rola, 'Mistrz Gry', 'Gracz', 'Widz'), g.login ASC";
$uczestnicy_tablica = [];
$res_uczest = $polaczenie->query($sql_gracze);
while ($u = $res_uczest->fetch_assoc()) $uczestnicy_tablica[] = $u;
$lista_nickow_json = json_encode(array_column($uczestnicy_tablica, 'login'));

// ── POSTY FABUŁY ──────────────────────────────────────────────
$posty_fabula = [];
$res_f = $polaczenie->query("
    SELECT p.*, g.login, g.avatar, u.rola
    FROM sesje_posty p JOIN gracze g ON p.autor_id=g.id
    LEFT JOIN sesje_uczestnicy u ON (u.sesja_id=p.sesja_id AND u.gracz_id=p.autor_id)
    WHERE p.sesja_id=$sesja_id AND p.typ_postu != 'OffTop'
    ORDER BY p.data_dodania ASC LIMIT $offset_fabula, $limit_fabula
");
while ($p = $res_f->fetch_assoc()) $posty_fabula[] = $p;

// ── POSTY OFFTOP ──────────────────────────────────────────────
$posty_offtop = [];
$res_o = $polaczenie->query("
    SELECT p.*, g.login, g.avatar, u.rola
    FROM sesje_posty p JOIN gracze g ON p.autor_id=g.id
    LEFT JOIN sesje_uczestnicy u ON (u.sesja_id=p.sesja_id AND u.gracz_id=p.autor_id)
    WHERE p.sesja_id=$sesja_id AND p.typ_postu='OffTop'
    ORDER BY p.data_dodania DESC LIMIT 50
");
while ($p = $res_o->fetch_assoc()) $posty_offtop[] = $p;
$posty_offtop = array_reverse($posty_offtop);

// ── PODSUMOWANIA (jeśli sesja zakończona) ─────────────────────
$podsumowania = [];
if ($czy_zakonczona) {
    $res_p = $polaczenie->query("
        SELECT sp.*, g.login, g.avatar, m.login AS mg_login
        FROM sesje_podsumowanie sp
        JOIN gracze g ON sp.gracz_id=g.id
        JOIN gracze m ON sp.mg_id=m.id
        WHERE sp.sesja_id=$sesja_id
    ");
    while ($p = $res_p->fetch_assoc()) $podsumowania[$p['gracz_id']] = $p;
}

$domyslna_zakladka = isset($_GET['zakladka']) && $_GET['zakladka'] != '' ? htmlspecialchars($_GET['zakladka']) : 'wpisy';
if ($domyslna_zakladka == 'Fabuła') $domyslna_zakladka = 'wpisy';
if (isset($_POST['szukaj_postow'])) $domyslna_zakladka = 'szukaj';
if ($czy_zakonczona && !isset($_GET['zakladka'])) $domyslna_zakladka = 'podsumowanie';

// Kolor akcent kategorii sesji
$akcent_kat = $KAT_KOLORY[$sesja['kategoria']] ?? 'var(--neon-red)';
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   POKOJ_SESJI.PHP — CYBERPUNK NYC
═══════════════════════════════════════════════════════════════ */

/* ── NAGŁÓWEK SESJI ──────────────────────────────────────── */
.pokoj-header{
    background:rgba(10,6,12,0.6);backdrop-filter:blur(8px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:22px 26px;margin-bottom:18px;
    display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;
    position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.5);
}
.pokoj-header::before{
    content:'';position:absolute;top:0;left:0;width:4px;height:100%;
    background:<?php echo $akcent_kat; ?>;box-shadow:0 0 12px <?php echo $akcent_kat; ?>;
}
.pokoj-header h1{
    font-family:'Oswald',sans-serif;font-weight:500;color:#fff;font-size:1.9em;
    text-transform:uppercase;letter-spacing:2.5px;margin:0 0 6px;line-height:1.1;
    text-shadow:0 0 18px rgba(255,23,68,0.3);
}
.pokoj-header .wlasc{
    color:var(--txt-dim);font-size:.85em;font-family:'JetBrains Mono',monospace;letter-spacing:.5px;
}
.pokoj-header .wlasc b{color:#fff}
.pokoj-statusy{display:flex;gap:6px;flex-wrap:wrap;max-width:55%}
.pokoj-statusy .tag-mini{
    display:inline-flex;align-items:center;gap:4px;
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.2);
    color:var(--txt-dim);font-size:.72em;padding:4px 9px;border-radius:1px;
    font-family:'JetBrains Mono',monospace;letter-spacing:1px;text-transform:uppercase;
}
.pokoj-statusy .tag-mini.hot{border-color:<?php echo $akcent_kat; ?>;color:<?php echo $akcent_kat; ?>;text-shadow:0 0 4px <?php echo $akcent_kat; ?>}
.pokoj-statusy .tag-mini.ok{border-color:rgba(90,255,154,0.4);color:var(--neon-green)}
.pokoj-statusy .tag-mini.warn{border-color:rgba(255,122,61,0.4);color:var(--neon-ember)}

.pokoj-ostrzezenie{
    background:rgba(255,23,68,0.08);border:1px solid var(--border-mid);
    padding:10px 16px;margin-bottom:18px;border-radius:2px;
    font-family:'JetBrains Mono',monospace;font-size:.82em;color:var(--neon-red-hot);
    letter-spacing:.5px;
}

/* ── STICKY TOOLBAR ─────────────────────────────────────── */
.panel-gora{
    position:sticky;top:0;z-index:50;
    background:rgba(5,3,7,0.92);backdrop-filter:blur(14px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:14px 20px;margin-bottom:22px;
    box-shadow:0 10px 30px rgba(0,0,0,0.7);
}

.uczestnicy-poziom{
    display:flex;align-items:center;gap:10px;
    padding-bottom:12px;border-bottom:1px dashed rgba(255,23,68,0.15);margin-bottom:12px;flex-wrap:wrap;
}
.uczestnicy-poziom-tytul{
    color:var(--txt-dim);font-family:'Oswald',sans-serif;
    text-transform:uppercase;font-size:.78em;letter-spacing:2px;margin-right:6px;
}
.uczestnik-link{text-decoration:none;display:inline-block;position:relative}
.uczestnik-kolko{
    width:36px;height:36px;border-radius:50%;
    border:2px solid var(--border-soft);
    background-size:cover!important;background-position:center!important;
    cursor:pointer;transition:.25s;
}
.uczestnik-link:hover .uczestnik-kolko{
    transform:scale(1.12);border-color:var(--neon-cyan);
    box-shadow:0 0 14px rgba(74,214,255,0.5);
}
.uczestnik-kolko.mg-circle{border-color:var(--neon-gold);box-shadow:0 0 10px rgba(255,215,0,0.4)}
.uczestnik-kolko.oczekuje{opacity:.5;border-style:dashed}

.tooltip-u{
    position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);
    background:rgba(5,3,7,0.97);backdrop-filter:blur(12px);
    border:1px solid var(--border-mid);border-radius:2px;padding:10px 14px;
    min-width:180px;text-align:center;pointer-events:none;
    opacity:0;visibility:hidden;transition:.2s;z-index:100;
    box-shadow:0 10px 30px rgba(0,0,0,0.9);
}
.uczestnik-link:hover .tooltip-u{opacity:1;visibility:visible}
.tooltip-u b{font-family:'Oswald',sans-serif;letter-spacing:.5px}
.tooltip-u .rola{font-size:.75em;color:var(--txt-mute);display:block;margin-top:3px;font-family:'JetBrains Mono',monospace;letter-spacing:1px}

/* MENU ZAKŁADEK */
.menu-poziome{display:flex;gap:8px;flex-wrap:wrap}
.menu-pl{
    padding:7px 14px;background:rgba(0,0,0,0.5);
    border:1px solid var(--border-soft);border-radius:1px;
    color:var(--txt-dim);font-family:'Oswald',sans-serif;
    text-transform:uppercase;font-size:.85em;letter-spacing:1.5px;
    cursor:pointer;transition:.2s;text-decoration:none;
}
.menu-pl:hover{background:rgba(255,23,68,0.1);color:#fff;border-color:var(--border-mid)}
.menu-pl.aktywny{background:rgba(74,214,255,0.12);border-color:var(--neon-cyan);color:#fff;text-shadow:0 0 6px var(--neon-cyan)}
.menu-pl.mg{background:rgba(255,122,61,0.08);border-color:rgba(255,122,61,0.4);color:var(--neon-ember)}
.menu-pl.mg.aktywny{background:rgba(255,122,61,0.2);border-color:var(--neon-ember);color:#fff;text-shadow:0 0 6px var(--neon-ember)}
.menu-pl.gold{background:rgba(255,215,0,0.08);border-color:rgba(255,215,0,0.4);color:var(--neon-gold)}
.menu-pl.gold.aktywny{background:rgba(255,215,0,0.2);border-color:var(--neon-gold);color:#fff;text-shadow:0 0 6px var(--neon-gold)}
.menu-pl.powrot{background:transparent;color:var(--neon-red-hot);border-color:rgba(255,23,68,0.3)}
.menu-pl.powrot:hover{background:rgba(255,23,68,0.1)}

/* KONTENER ZAKŁADEK */
.zakladka-tresc{display:none}
.zakladka-tresc.aktywna{display:block}

/* ── POST ─────────────────────────────────────────────── */
.post-karta{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);border-radius:2px;
    margin-bottom:16px;overflow:hidden;backdrop-filter:blur(6px);
    box-shadow:0 4px 18px rgba(0,0,0,0.4);
}
.post-karta.mg-post{border-left:3px solid var(--neon-gold);box-shadow:0 4px 18px rgba(0,0,0,0.4),0 0 20px rgba(255,215,0,0.08)}
.post-naglowek{
    background:rgba(0,0,0,0.4);padding:12px 18px;
    border-bottom:1px solid rgba(255,23,68,0.08);
    display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
}
.post-autor-info{display:flex;align-items:center;gap:14px}
.post-avatar{
    width:42px;height:42px;border-radius:50%;
    border:1px solid var(--border-soft);
    background-size:cover!important;background-position:center!important;
}
.post-nick{
    color:var(--neon-cyan);font-family:'Oswald',sans-serif;font-size:1.1em;font-weight:500;
    letter-spacing:1px;text-shadow:0 0 6px rgba(74,214,255,0.3);
}
.post-rola-tag{
    background:rgba(255,255,255,0.06);color:var(--txt-dim);
    font-size:.65em;padding:2px 6px;border-radius:1px;margin-left:8px;
    font-family:'JetBrains Mono',monospace;letter-spacing:1.5px;text-transform:uppercase;
}
.mg-tag{background:rgba(255,215,0,0.15);color:var(--neon-gold);border:1px solid rgba(255,215,0,0.4)}
.post-data{font-size:.78em;color:var(--txt-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.5px}
.post-cialo{
    padding:18px 22px;line-height:1.7;font-size:.98em;color:var(--txt-main);
    font-family:'Open Sans',sans-serif;
}
.edytuj-link{
    color:var(--neon-ember);text-decoration:none;font-size:.8em;
    font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1.2px;
}
.edytuj-link:hover{text-shadow:0 0 6px var(--neon-ember)}
.wspomnienie{
    color:var(--neon-green);font-weight:600;
    background:rgba(90,255,154,0.08);padding:2px 6px;border-radius:1px;
    border:1px solid rgba(90,255,154,0.2);
}

/* ── POST SYSTEMOWY (RZUT KOŚCIĄ) ─────────────────────── */
.post-systemowy{
    background:rgba(74,214,255,0.04);border:1px solid rgba(74,214,255,0.25);
    border-radius:2px;padding:14px 18px;margin-bottom:16px;
    backdrop-filter:blur(6px);box-shadow:inset 0 0 30px rgba(74,214,255,0.04);
}
.post-systemowy .sys-head{
    font-family:'JetBrains Mono',monospace;font-size:.82em;
    color:var(--neon-cyan);letter-spacing:2px;text-transform:uppercase;
    margin-bottom:10px;padding-bottom:8px;border-bottom:1px dashed rgba(74,214,255,0.2);
}
.rzut-box{font-family:'Open Sans',sans-serif;font-size:.92em;color:var(--txt-main);line-height:1.6}
.rzut-head{
    font-family:'Oswald',sans-serif;font-size:1.1em;color:#fff;
    letter-spacing:1.5px;margin-bottom:8px;padding-bottom:6px;
    border-bottom:1px dashed rgba(255,255,255,0.08);
}
.rzut-icon{font-size:1.3em;margin-right:6px}
.rzut-akcja{font-style:italic;color:var(--txt-dim);margin-bottom:10px;padding-left:12px;border-left:2px solid rgba(74,214,255,0.2)}
.rzut-row{padding:4px 0;font-size:.93em}
.rzut-lbl{color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.85em;letter-spacing:1px;text-transform:uppercase;margin-right:8px}
.rzut-val{color:#fff;font-family:'Oswald',sans-serif;font-weight:700;font-size:1.2em}
.rzut-wynik{
    margin-top:10px;padding-top:10px;border-top:1px dashed rgba(255,255,255,0.08);
    font-size:1.1em;color:var(--txt-main);
}

/* PAGINACJA */
.paginacja{display:flex;justify-content:center;gap:6px;margin:22px 0;flex-wrap:wrap}
.pg{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);
    color:var(--txt-dim);padding:7px 13px;border-radius:1px;text-decoration:none;
    font-family:'Oswald',sans-serif;font-size:1em;letter-spacing:1px;transition:.25s;
}
.pg:hover{background:rgba(74,214,255,0.1);color:#fff;border-color:var(--neon-cyan)}
.pg.aktywna{background:rgba(74,214,255,0.2);color:#fff;border-color:var(--neon-cyan);box-shadow:0 0 12px rgba(74,214,255,0.4)}

/* FORMULARZ PISANIA */
.form-pisania{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);
    padding:18px;border-radius:2px;margin-top:20px;backdrop-filter:blur(6px);
    box-shadow:0 4px 18px rgba(0,0,0,0.4);
}
.form-tytul{
    font-family:'Oswald',sans-serif;color:#fff;font-size:1.05em;
    text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;
    padding-bottom:10px;border-bottom:1px dashed var(--border-soft);
    display:flex;align-items:center;gap:8px;
}
.form-tytul.fabula{color:var(--neon-green)}
.form-tytul.rzut{color:var(--neon-cyan)}
.edytor-text{
    width:100%;background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);color:#fff;
    padding:12px 14px;font-family:'Open Sans',sans-serif;font-size:.95em;
    box-sizing:border-box;margin-bottom:12px;resize:vertical;border-radius:2px;transition:.2s;
}
.edytor-text:focus{outline:none;border-color:var(--neon-cyan);box-shadow:0 0 10px rgba(74,214,255,0.2)}

.btn-wyslij{
    background:rgba(90,255,154,0.08);color:var(--neon-green);
    border:1px solid var(--neon-green);padding:11px 24px;
    font-family:'Oswald',sans-serif;font-weight:600;cursor:pointer;
    text-transform:uppercase;font-size:.95em;letter-spacing:2px;border-radius:2px;transition:.25s;
}
.btn-wyslij:hover{background:var(--neon-green);color:#000;box-shadow:0 0 18px rgba(90,255,154,0.5)}
.btn-wyslij.cyan{background:rgba(74,214,255,0.08);color:var(--neon-cyan);border-color:var(--neon-cyan)}
.btn-wyslij.cyan:hover{background:var(--neon-cyan);color:#000;box-shadow:0 0 18px rgba(74,214,255,0.5)}
.btn-wyslij.red{background:rgba(255,23,68,0.08);color:var(--neon-red-hot);border-color:var(--neon-red)}
.btn-wyslij.red:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 18px rgba(255,23,68,0.5)}
.btn-wyslij.ember{background:rgba(255,122,61,0.08);color:var(--neon-ember);border-color:var(--neon-ember)}
.btn-wyslij.ember:hover{background:var(--neon-ember);color:#000;box-shadow:0 0 18px rgba(255,122,61,0.5)}
.btn-wyslij.gold{background:rgba(255,215,0,0.08);color:var(--neon-gold);border-color:var(--neon-gold)}
.btn-wyslij.gold:hover{background:var(--neon-gold);color:#000;box-shadow:0 0 18px rgba(255,215,0,0.5)}
.btn-mini{
    padding:5px 11px;font-size:.75em;
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);color:var(--txt-dim);
    cursor:pointer;border-radius:1px;font-family:'Oswald',sans-serif;
    text-transform:uppercase;letter-spacing:1px;transition:.2s;
}
.btn-mini:hover{background:var(--neon-cyan);color:#000;border-color:var(--neon-cyan)}

/* ── RZUT KOŚCIĄ — GENERATOR ────────────────────────── */
.gen-rzut{margin-top:20px}
.gen-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px}
@media(max-width:720px){.gen-row{grid-template-columns:1fr}}
.gen-kol{
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);
    padding:12px 14px;border-radius:2px;max-height:180px;overflow-y:auto;
}
.gen-kol .gen-lbl{
    font-family:'Oswald',sans-serif;font-size:.78em;color:var(--txt-mute);
    text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;
    padding-bottom:6px;border-bottom:1px dashed var(--border-soft);
}
.gen-cb{
    display:flex;align-items:center;gap:8px;padding:5px 0;
    color:var(--txt-main);font-size:.88em;cursor:pointer;font-family:'Open Sans',sans-serif;
}
.gen-cb:hover{color:#fff}
.gen-cb input{accent-color:var(--neon-cyan);cursor:pointer}
.gen-cb .pu-tag{color:var(--neon-cyan);font-family:'JetBrains Mono',monospace;font-size:.82em;margin-left:auto}
.gen-cb .zal-tag{color:var(--neon-green);font-family:'JetBrains Mono',monospace;font-size:.82em;margin-left:auto}
.gen-cb .wad-tag{color:var(--neon-red-hot);font-family:'JetBrains Mono',monospace;font-size:.82em;margin-left:auto}

.ryzyko-wiersz{
    display:flex;gap:12px;flex-wrap:wrap;
    background:rgba(0,0,0,0.4);border:1px solid var(--border-soft);
    padding:12px 14px;border-radius:2px;margin-bottom:12px;
}
.ryzyko-wiersz .lbl{
    width:100%;font-family:'Oswald',sans-serif;font-size:.78em;
    color:var(--txt-mute);text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;
}
.ryzyko-opt{
    display:inline-flex;align-items:center;gap:6px;cursor:pointer;
    padding:6px 12px;border:1px solid var(--border-soft);border-radius:2px;
    font-family:'Oswald',sans-serif;letter-spacing:1px;text-transform:uppercase;font-size:.8em;
    transition:.2s;background:rgba(0,0,0,0.5);
}
.ryzyko-opt input{display:none}
.ryzyko-opt .rk-dot{width:8px;height:8px;border-radius:50%;background:currentColor;box-shadow:0 0 6px currentColor}
.ryzyko-opt.zaz{background:rgba(255,255,255,0.06);box-shadow:0 0 12px currentColor}

.akcja-input{
    width:100%;padding:10px 14px;
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);color:#fff;
    border-radius:2px;font-family:'Open Sans',sans-serif;font-size:.92em;margin-bottom:12px;
}
.akcja-input:focus{outline:none;border-color:var(--neon-cyan)}

/* ── USTAWIENIA MG ───────────────────────────────────── */
.form-mg{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);
    padding:22px;border-radius:2px;margin-bottom:18px;backdrop-filter:blur(6px);
}
.form-mg h3{
    font-family:'Oswald',sans-serif;color:#fff;font-size:1.15em;
    text-transform:uppercase;letter-spacing:2px;margin:0 0 14px;
    padding-bottom:10px;border-bottom:1px dashed var(--border-soft);
}
.mg-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px}
@media(max-width:720px){.mg-row{grid-template-columns:1fr}}
.mg-label{
    display:block;font-family:'Oswald',sans-serif;font-size:.78em;
    color:var(--txt-mute);text-transform:uppercase;letter-spacing:2px;margin-bottom:5px;
}

.gracz-item{
    display:flex;justify-content:space-between;align-items:center;
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);
    border-radius:2px;padding:12px 16px;margin-bottom:8px;gap:14px;flex-wrap:wrap;
}
.gracz-item b{color:var(--neon-cyan);font-family:'Oswald',sans-serif;letter-spacing:1px}
.gracz-item .meta{color:var(--txt-mute);font-size:.78em;font-family:'JetBrains Mono',monospace;margin-left:10px}
.gracz-akcje{display:flex;gap:5px;flex-wrap:wrap}

/* ── ZAKOŃCZENIE SESJI (podsumowanie MG) ─────────────── */
.zakoncz-panel{
    background:rgba(255,215,0,0.04);border:2px solid var(--neon-gold);
    padding:22px;border-radius:2px;margin-bottom:20px;
    box-shadow:0 0 30px rgba(255,215,0,0.1);
}
.zakoncz-panel h3{
    color:var(--neon-gold);font-family:'Oswald',sans-serif;font-size:1.25em;
    text-transform:uppercase;letter-spacing:2.5px;margin:0 0 8px;
    text-shadow:0 0 8px rgba(255,215,0,0.5);
}
.zakoncz-panel .zp-opis{
    color:var(--txt-dim);font-size:.9em;line-height:1.55;margin-bottom:16px;
    padding-bottom:14px;border-bottom:1px dashed rgba(255,215,0,0.2);
}

.gracz-podsumowanie{
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);border-radius:2px;
    padding:16px 18px;margin-bottom:14px;
}
.gp-naglowek{
    display:flex;align-items:center;gap:12px;margin-bottom:14px;
    padding-bottom:10px;border-bottom:1px dashed var(--border-soft);
}
.gp-av{width:40px;height:40px;border-radius:50%;border:1px solid var(--border-soft);background-size:cover!important;background-position:center!important}
.gp-nick{color:var(--neon-cyan);font-family:'Oswald',sans-serif;font-size:1.1em;letter-spacing:1px}

.rep-siatka{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px;
}
.rep-item{
    background:rgba(0,0,0,0.4);border:1px solid var(--border-soft);
    padding:10px;border-radius:2px;text-align:center;
}
.rep-item .rep-lbl{
    font-family:'Oswald',sans-serif;font-size:.72em;color:var(--txt-mute);
    text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;
}
.rep-item input[type=number]{
    width:60px;padding:5px;background:rgba(0,0,0,0.6);border:1px solid var(--border-mid);
    color:#fff;border-radius:1px;text-align:center;font-family:'Oswald',sans-serif;font-size:1.1em;
}
.rep-item .rep-range{display:block;font-size:.7em;color:var(--txt-mute);margin-top:4px;font-family:'JetBrains Mono',monospace}

.gp-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px}
@media(max-width:720px){.gp-row{grid-template-columns:1fr}}
.gp-sel{
    width:100%;padding:8px 12px;
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);color:#fff;
    border-radius:2px;font-family:'Open Sans',sans-serif;font-size:.9em;
}
.gp-textarea{
    width:100%;padding:10px 12px;min-height:60px;
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);color:#fff;
    border-radius:2px;font-family:'Open Sans',sans-serif;font-size:.9em;resize:vertical;
}

/* ── WIDOK PODSUMOWANIA (po zakończeniu) ────────────── */
.pods-widok{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);
    padding:22px;border-radius:2px;margin-bottom:16px;
}
.pods-naglowek{
    display:flex;align-items:center;gap:14px;margin-bottom:14px;
    padding-bottom:12px;border-bottom:1px dashed var(--border-soft);
}
.pods-tresc{
    color:var(--txt-main);font-family:'Open Sans',sans-serif;font-size:.96em;line-height:1.7;
    padding:14px;background:rgba(0,0,0,0.3);border-radius:2px;border-left:3px solid var(--neon-gold);
    margin-bottom:14px;font-style:italic;
}
.pods-rep{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin:12px 0}
.pods-rep .rep-box-mini{
    background:rgba(0,0,0,0.4);border:1px solid var(--border-soft);padding:10px;
    border-radius:2px;text-align:center;
}
.pods-rep .rlbl{font-family:'Oswald',sans-serif;font-size:.72em;color:var(--txt-mute);letter-spacing:2px;text-transform:uppercase;margin-bottom:4px}
.pods-rep .rval{font-family:'Oswald',sans-serif;font-size:1.5em;font-weight:500}
.pods-rep .rval.plus{color:var(--neon-green);text-shadow:0 0 8px rgba(90,255,154,0.5)}
.pods-rep .rval.minus{color:var(--neon-red-hot);text-shadow:0 0 8px rgba(255,23,68,0.5)}
.pods-rep .rval.zero{color:var(--txt-dim)}

.konsekwencja-box{
    background:rgba(255,23,68,0.08);border:1px solid var(--neon-red);
    padding:12px 16px;border-radius:2px;margin:12px 0;
    color:var(--neon-red-hot);font-family:'JetBrains Mono',monospace;font-size:.9em;letter-spacing:.5px;
}
.konsekwencja-box b{color:#fff;font-family:'Oswald',sans-serif;letter-spacing:1.5px;text-transform:uppercase}

/* OPIS/ZASADY */
.opis-widok{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);border-left:3px solid <?php echo $akcent_kat; ?>;
    padding:22px;border-radius:2px;color:var(--txt-main);line-height:1.7;font-size:1em;
    font-family:'Open Sans',sans-serif;
}

/* SZUKAJ */
.szukaj-wynik{
    background:rgba(0,0,0,0.4);border-left:3px solid var(--neon-cyan);
    margin-bottom:12px;padding:12px 16px;border-radius:0 2px 2px 0;
}
.szukaj-nick{color:var(--neon-cyan);font-family:'Oswald',sans-serif;font-weight:500;font-size:1.05em;letter-spacing:1px}
.szukaj-typ{color:var(--txt-mute);font-size:.72em;font-family:'JetBrains Mono',monospace;margin-left:8px;letter-spacing:1px}
.szukaj-data{color:var(--txt-mute);font-size:.78em;font-family:'JetBrains Mono',monospace;float:right}
.szukaj-tresc{color:var(--txt-main);margin-top:8px;font-family:'Open Sans',sans-serif;font-size:.92em;line-height:1.55}
.fraza-hit{background:rgba(255,215,0,0.25);color:var(--neon-gold);padding:1px 4px;border-radius:1px;border:1px solid rgba(255,215,0,0.4);font-weight:600}

/* MENTION POPUP */
.mention-popup{
    position:absolute;background:rgba(5,3,7,0.97);backdrop-filter:blur(12px);
    border:1px solid var(--neon-cyan);border-radius:2px;max-height:160px;overflow-y:auto;
    display:none;z-index:1000;box-shadow:0 10px 30px rgba(0,0,0,0.9);min-width:160px;
}
.mention-item{
    padding:9px 14px;color:#fff;cursor:pointer;
    font-family:'Open Sans',sans-serif;font-size:.9em;
    border-bottom:1px solid rgba(255,23,68,0.08);transition:.15s;
}
.mention-item:last-child{border-bottom:none}
.mention-item:hover,.mention-item.active{background:rgba(74,214,255,0.2);color:var(--neon-cyan)}

.blad-in{
    color:var(--neon-red-hot);background:rgba(255,23,68,0.1);
    padding:12px 16px;border:1px solid var(--border-mid);border-radius:2px;margin-bottom:14px;
    text-align:center;font-family:'Oswald',sans-serif;letter-spacing:1.5px;
}

.offtop-box{
    background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);
    padding:18px;border-radius:2px;max-height:500px;overflow-y:auto;margin-bottom:14px;
    backdrop-filter:blur(6px);
}
.offtop-wpis{
    padding-bottom:12px;margin-bottom:12px;
    border-bottom:1px dashed rgba(255,23,68,0.1);
}
.offtop-wpis:last-child{border-bottom:none;margin-bottom:0}
.offtop-nick{color:var(--neon-cyan);font-family:'Oswald',sans-serif;font-weight:500;font-size:1.02em;letter-spacing:.5px}
.offtop-data{color:var(--txt-mute);font-size:.75em;margin-left:8px;font-family:'JetBrains Mono',monospace}
.offtop-tresc{color:var(--txt-main);margin-top:6px;font-family:'Open Sans',sans-serif;line-height:1.55;font-size:.92em}
</style>

<!-- ══ NAGŁÓWEK SESJI ══════════════════════════════════════ -->
<div class="pokoj-header">
    <div>
        <h1><?php echo htmlspecialchars($sesja['tytul']); ?></h1>
        <div class="wlasc">MG / Właściciel: <b><?php echo htmlspecialchars($sesja['nazwa_wlasciciela']); ?></b></div>
    </div>
    <div class="pokoj-statusy">
        <span class="tag-mini hot"><?php echo htmlspecialchars($sesja['kategoria']); ?></span>
        <?php if (!empty($sesja['gatunek'])): ?>
            <span class="tag-mini"><?php echo ($GATUNKI_IKONY[$sesja['gatunek']] ?? '•'); ?> <?php echo htmlspecialchars($sesja['gatunek']); ?></span>
        <?php endif; ?>
        <span class="tag-mini warn">◆ <?php echo htmlspecialchars($sesja['poziom_trudnosci']); ?></span>
        <span class="tag-mini <?php echo $czy_zakonczona ? '' : 'ok'; ?>">◉ <?php echo htmlspecialchars($sesja['status']); ?></span>
    </div>
</div>

<?php if (!empty($sesja['ostrzezenia'])): ?>
<div class="pokoj-ostrzezenie">⚠ OSTRZEŻENIE TREŚCIOWE: <?php echo htmlspecialchars($sesja['ostrzezenia']); ?></div>
<?php endif; ?>

<div class="panel-gora">

    <!-- UCZESTNICY -->
    <div class="uczestnicy-poziom">
        <div class="uczestnicy-poziom-tytul">▸ UCZESTNICY</div>
        <?php foreach ($uczestnicy_tablica as $u):
            $img = !empty($u['avatar']) ? $u['avatar'] : 'https://via.placeholder.com/36/0a0a0a/333?text=?';
            $rola_txt = ($u['id'] == $wlasciciel_id) ? 'Właściciel / MG' : ($u['rola'] == 'Mistrz Gry' ? 'Mistrz Gry' : $u['rola']);
            if ($u['status_akceptacji'] == 'Oczekuje') $rola_txt .= ' (Oczekuje)';
            $klasy = [];
            if ($u['id'] == $wlasciciel_id || $u['rola'] == 'Mistrz Gry') $klasy[] = 'mg-circle';
            if ($u['status_akceptacji'] == 'Oczekuje') $klasy[] = 'oczekuje';
        ?>
            <a href="game.php?page=profil&id=<?php echo $u['id']; ?>" class="uczestnik-link" target="_blank">
                <div class="uczestnik-kolko <?php echo implode(' ', $klasy); ?>" style="background-image:url('<?php echo htmlspecialchars($img); ?>')"></div>
                <div class="tooltip-u">
                    <b><?php echo htmlspecialchars($u['login']); ?></b>
                    <span class="rola"><?php echo $rola_txt; ?></span>
                </div>
            </a>
        <?php endforeach; ?>

        <?php if (!$czy_bierze_udzial && !$czy_zakonczona): ?>
            <form method="POST" style="margin-left:auto">
                <button type="submit" name="dolacz_do_sesji" class="btn-mini" style="color:var(--neon-cyan);border-color:var(--neon-cyan)">+ Dołącz</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- MENU ZAKŁADEK -->
    <div class="menu-poziome">
        <a href="game.php?page=sesje" class="menu-pl powrot">← Powrót</a>
        <div class="menu-pl <?php if($domyslna_zakladka=='wpisy') echo 'aktywny'; ?>" onclick="przelaczZakladke('wpisy', this)">📜 Wpisy</div>
        <div class="menu-pl <?php if($domyslna_zakladka=='offtop') echo 'aktywny'; ?>" onclick="przelaczZakladke('offtop', this)">💬 OffTop</div>
        <div class="menu-pl <?php if($domyslna_zakladka=='opis') echo 'aktywny'; ?>" onclick="przelaczZakladke('opis', this)">📖 Opis</div>
        <div class="menu-pl <?php if($domyslna_zakladka=='szukaj') echo 'aktywny'; ?>" onclick="przelaczZakladke('szukaj', this)">🔍 Szukaj</div>
        <?php if ($czy_zakonczona): ?>
            <div class="menu-pl gold <?php if($domyslna_zakladka=='podsumowanie') echo 'aktywny'; ?>" onclick="przelaczZakladke('podsumowanie', this)">🏆 Podsumowanie</div>
        <?php endif; ?>
        <?php if ($czy_mg): ?>
            <div class="menu-pl mg <?php if($domyslna_zakladka=='ustawienia') echo 'aktywny'; ?>" onclick="przelaczZakladke('ustawienia', this)">⚙️ Ustawienia MG</div>
            <?php if (!$czy_zakonczona): ?>
            <div class="menu-pl gold <?php if($domyslna_zakladka=='zakoncz') echo 'aktywny'; ?>" onclick="przelaczZakladke('zakoncz', this)">🏁 Zakończ sesję</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ══ ZAKŁADKA: WPISY (FABUŁA) ══════════════════════════════ -->
<div id="tab-wpisy" class="zakladka-tresc <?php if($domyslna_zakladka=='wpisy') echo 'aktywna'; ?>">

    <?php if ($stron_fabula > 1): ?>
    <div class="paginacja">
        <?php for ($i=1; $i<=$stron_fabula; $i++): ?>
        <a href="game.php?page=pokoj_sesji&id=<?php echo $sesja_id; ?>&zakladka=wpisy&sf=<?php echo $i; ?>" class="pg <?php if($i==$sf) echo 'aktywna'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php if (count($posty_fabula) > 0): ?>
        <?php foreach ($posty_fabula as $post):
            $p_img = !empty($post['avatar']) ? $post['avatar'] : 'https://via.placeholder.com/42/0a0a0a/333?text=?';
            $czy_to_mg = ($post['autor_id'] == $wlasciciel_id || $post['rola'] == 'Mistrz Gry');
            $moze_edytowac = ($post['autor_id'] == $id_gracza || $czy_mg);

            if ($post['typ_postu'] == 'Rzut_Koscia'): ?>
                <div class="post-systemowy">
                    <div class="sys-head">// SYSTEM · <?php echo htmlspecialchars($post['login']); ?> · <?php echo $post['data_dodania']; ?></div>
                    <?php echo $post['tresc']; ?>
                </div>
            <?php else:
                $tresc_html = htmlspecialchars($post['tresc']);
                $tresc_html = preg_replace('/@([a-zA-Z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+)/u', '<span class="wspomnienie">@$1</span>', $tresc_html);
                $tresc_html = preg_replace('/\*(.*?)\*/s', '<span style="color:var(--txt-mute);font-style:italic">*$1*</span>', $tresc_html);
                $tresc_html = nl2br($tresc_html);
            ?>
                <div class="post-karta <?php if($czy_to_mg) echo 'mg-post'; ?>">
                    <div class="post-naglowek">
                        <div class="post-autor-info">
                            <div class="post-avatar" style="background-image:url('<?php echo htmlspecialchars($p_img); ?>')"></div>
                            <div>
                                <div class="post-nick">
                                    <?php echo htmlspecialchars($post['login']); ?>
                                    <span class="post-rola-tag <?php if($czy_to_mg) echo 'mg-tag'; ?>"><?php echo $czy_to_mg ? 'Mistrz Gry' : 'Gracz'; ?></span>
                                </div>
                                <div class="post-data">📅 <?php echo $post['data_dodania']; ?></div>
                            </div>
                        </div>
                        <?php if ($moze_edytowac && !$czy_zakonczona): ?>
                            <a href="javascript:void(0)" class="edytuj-link" onclick="pokazEdycje(<?php echo $post['id']; ?>)">✏ Edytuj</a>
                        <?php endif; ?>
                    </div>
                    <div class="post-cialo">
                        <div id="post-tresc-<?php echo $post['id']; ?>"><?php echo $tresc_html; ?></div>
                        <div id="post-edycja-<?php echo $post['id']; ?>" style="display:none;margin-top:10px">
                            <form method="POST">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="zakladka_powrot" value="wpisy">
                                <input type="hidden" name="sf" value="<?php echo $sf; ?>">
                                <textarea name="nowa_tresc" class="edytor-text tag-input" style="min-height:140px"><?php echo htmlspecialchars($post['tresc']); ?></textarea>
                                <div style="text-align:right">
                                    <button type="button" class="btn-mini" onclick="ukryjEdycje(<?php echo $post['id']; ?>)">Anuluj</button>
                                    <button type="submit" name="zapisz_edycje" class="btn-wyslij ember" style="padding:7px 16px;font-size:.85em">Zapisz</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align:center;padding:40px;color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.9em;font-style:italic">// Brak wpisów fabularnych na tej stronie</div>
    <?php endif; ?>

    <?php if ($stron_fabula > 1): ?>
    <div class="paginacja">
        <?php for ($i=1; $i<=$stron_fabula; $i++): ?>
        <a href="game.php?page=pokoj_sesji&id=<?php echo $sesja_id; ?>&zakladka=wpisy&sf=<?php echo $i; ?>" class="pg <?php if($i==$sf) echo 'aktywna'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php if ($czy_zaakceptowany && !$czy_zakonczona): ?>

        <!-- ══ GENERATOR RZUTU d20 ═════════════════════════ -->
        <div class="form-pisania">
            <div class="form-tytul rzut">🎲 Generator Testu d20</div>
            <div style="color:var(--txt-dim);font-size:.85em;margin-bottom:14px;line-height:1.5">
                Opisz akcję, wybierz ryzyko, zaznacz umiejętności i cechy które mają zastosowanie. System doda pełny bonus RP (pochodzenie × zawód) do rzutu.
            </div>
            <form method="POST" class="gen-rzut">

                <input type="text" name="akcja_opis" class="akcja-input" placeholder="Co próbuje zrobić Twoja postać? (np. 'przekonać strażnika o niewinności')" maxlength="200">

                <div class="ryzyko-wiersz">
                    <div class="lbl">◆ Poziom ryzyka akcji:</div>
                    <?php foreach ($RYZYKO_PT as $rn => $rd):
                        $akt = ($rn == 'Niskie') ? 'zaz' : ''; ?>
                        <label class="ryzyko-opt <?php echo $akt; ?>" style="color:<?php echo $rd['kolor']; ?>" title="<?php echo htmlspecialchars($rd['opis']); ?>">
                            <input type="radio" name="ryzyko" value="<?php echo $rn; ?>" <?php if($rn=='Niskie') echo 'checked'; ?>>
                            <span class="rk-dot"></span>
                            <span><?php echo $rn; ?> (PT <?php echo $rd['pt']; ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="gen-row">
                    <div class="gen-kol">
                        <div class="gen-lbl">◆ Umiejętności (pełny bonus RP)</div>
                        <?php if (empty($umiejetnosci_gracza)): ?>
                            <div style="color:var(--txt-mute);font-size:.85em;font-style:italic">// Brak rozwiniętych umiejętności</div>
                        <?php else:
                            // Pokazujemy tylko umiejętności z PU > 0, sortując malejąco
                            $um_sort = $umiejetnosci_gracza;
                            arsort($um_sort);
                            foreach ($um_sort as $nazwa => $lvl):
                                if ($lvl <= 0) continue;
                                $w = bonus_rp_umiejetnosci($gracz, $nazwa);
                                $koncowa = $w['wartosc_koncowa'];
                        ?>
                            <label class="gen-cb">
                                <input type="checkbox" name="um_rzut[]" value="<?php echo htmlspecialchars($nazwa); ?>">
                                <span><?php echo htmlspecialchars($nazwa); ?></span>
                                <span class="pu-tag">+<?php echo $koncowa; ?></span>
                            </label>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="gen-kol">
                        <div class="gen-lbl">◆ Zalety / Wady (±3)</div>
                        <?php
                        $brak = true;
                        foreach ($zalety_gracza as $z) { if (empty($z)) continue; $brak=false; ?>
                            <label class="gen-cb">
                                <input type="checkbox" name="cechy_rzut[]" value="<?php echo htmlspecialchars($z); ?>">
                                <span><?php echo htmlspecialchars($z); ?></span>
                                <span class="zal-tag">+3</span>
                            </label>
                        <?php }
                        foreach ($wady_gracza as $w) { if (empty($w)) continue; $brak=false; ?>
                            <label class="gen-cb">
                                <input type="checkbox" name="cechy_rzut[]" value="<?php echo htmlspecialchars($w); ?>">
                                <span><?php echo htmlspecialchars($w); ?></span>
                                <span class="wad-tag">−3</span>
                            </label>
                        <?php } if ($brak): ?>
                            <div style="color:var(--txt-mute);font-size:.85em;font-style:italic">// Brak cech charakteru</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="text-align:right;margin-top:10px">
                    <button type="submit" name="wykonaj_rzut" class="btn-wyslij cyan">🎲 Rzuć d20</button>
                </div>
            </form>
        </div>

        <!-- ══ FORMULARZ WPISU FABULARNEGO ═════════════════ -->
        <div class="form-pisania">
            <div class="form-tytul fabula">✍ Dodaj Wpis Fabularny</div>
            <form method="POST">
                <input type="hidden" name="typ_postu" value="Fabuła">
                <textarea name="tresc_postu" class="edytor-text tag-input" style="min-height:180px" placeholder="*Opis narracyjny w gwiazdkach*&#10;&#10;Dialog białym tekstem... (użyj @Nick, aby kogoś wspomnieć)" required></textarea>
                <div style="text-align:right">
                    <button type="submit" name="dodaj_post" class="btn-wyslij">Wyślij Wpis →</button>
                </div>
            </form>
        </div>

    <?php elseif (!$czy_zaakceptowany && !$czy_zakonczona): ?>
        <div style="padding:30px;text-align:center;color:var(--txt-mute);background:rgba(0,0,0,0.3);border:1px dashed var(--border-soft);border-radius:2px;margin-top:20px">
            // Musisz być zaakceptowany, żeby pisać wpisy w tej sesji.
        </div>
    <?php endif; ?>
</div>

<!-- ══ ZAKŁADKA: OFFTOP ══════════════════════════════════════ -->
<div id="tab-offtop" class="zakladka-tresc <?php if($domyslna_zakladka=='offtop') echo 'aktywna'; ?>">
    <div class="offtop-box">
        <?php foreach ($posty_offtop as $post):
            $mo = ($post['autor_id'] == $id_gracza || $czy_mg);
            $th = htmlspecialchars($post['tresc']);
            $th = preg_replace('/@([a-zA-Z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+)/u', '<span class="wspomnienie">@$1</span>', $th);
            $th = nl2br($th);
        ?>
        <div class="offtop-wpis">
            <span class="offtop-nick"><?php echo htmlspecialchars($post['login']); ?></span>
            <span class="offtop-data"><?php echo $post['data_dodania']; ?></span>
            <?php if ($mo && !$czy_zakonczona): ?>
                <a href="javascript:void(0)" class="edytuj-link" style="margin-left:10px;font-size:.75em" onclick="pokazEdycje(<?php echo $post['id']; ?>)">✏</a>
            <?php endif; ?>
            <div id="post-tresc-<?php echo $post['id']; ?>" class="offtop-tresc"><?php echo $th; ?></div>
            <div id="post-edycja-<?php echo $post['id']; ?>" style="display:none;margin-top:10px">
                <form method="POST">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <input type="hidden" name="zakladka_powrot" value="offtop">
                    <textarea name="nowa_tresc" class="edytor-text tag-input" style="min-height:70px"><?php echo htmlspecialchars($post['tresc']); ?></textarea>
                    <div style="text-align:right">
                        <button type="button" class="btn-mini" onclick="ukryjEdycje(<?php echo $post['id']; ?>)">Anuluj</button>
                        <button type="submit" name="zapisz_edycje" class="btn-wyslij ember" style="padding:6px 14px;font-size:.8em">Zapisz</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($posty_offtop) == 0): ?>
            <div style="text-align:center;color:var(--txt-mute);font-style:italic;font-family:'JetBrains Mono',monospace;font-size:.9em">// Brak dyskusji</div>
        <?php endif; ?>
    </div>

    <?php if ($czy_zaakceptowany && !$czy_zakonczona): ?>
    <form method="POST" style="display:flex;gap:10px">
        <input type="hidden" name="typ_postu" value="OffTop">
        <input type="text" name="tresc_postu" class="edytor-text tag-input" style="margin:0" placeholder="Napisz wiadomość OffTop... (użyj @Nick)" required>
        <button type="submit" name="dodaj_post" class="btn-wyslij cyan">Wyślij</button>
    </form>
    <?php endif; ?>
</div>

<!-- ══ ZAKŁADKA: OPIS ══════════════════════════════════════ -->
<div id="tab-opis" class="zakladka-tresc <?php if($domyslna_zakladka=='opis') echo 'aktywna'; ?>">
    <div class="opis-widok"><?php echo nl2br(htmlspecialchars($sesja['opis'])); ?></div>
    <?php if (!empty($sesja['tagi'])): ?>
    <p style="color:var(--txt-mute);margin-top:14px;font-family:'JetBrains Mono',monospace;font-size:.82em;letter-spacing:1px">
        TAGI: <span style="color:var(--neon-cyan)"><?php echo htmlspecialchars($sesja['tagi']); ?></span>
    </p>
    <?php endif; ?>
</div>

<!-- ══ ZAKŁADKA: SZUKAJ ══════════════════════════════════════ -->
<div id="tab-szukaj" class="zakladka-tresc <?php if($domyslna_zakladka=='szukaj') echo 'aktywna'; ?>">
    <form method="POST" style="display:flex;gap:10px;margin-bottom:18px">
        <input type="text" name="fraza" class="edytor-text" style="margin:0" placeholder="Wpisz szukane słowo (min. 3 znaki)..." value="<?php echo htmlspecialchars($_POST['fraza'] ?? ''); ?>" required>
        <button type="submit" name="szukaj_postow" class="btn-wyslij cyan">Szukaj</button>
    </form>
    <?php if ($blad_wysz): ?><div class="blad-in">⚠ <?php echo $blad_wysz; ?></div><?php endif; ?>
    <?php if ($wyniki_wysz !== null): ?>
        <div style="background:rgba(10,6,12,0.55);border:1px solid var(--border-soft);padding:20px;border-radius:2px">
            <h3 style="color:#fff;font-family:'Oswald',sans-serif;margin:0 0 14px;padding-bottom:10px;border-bottom:1px dashed var(--border-soft);letter-spacing:1.5px;text-transform:uppercase;font-size:1.1em">Wyniki dla: <span style="color:var(--neon-cyan)"><?php echo htmlspecialchars($_POST['fraza']); ?></span></h3>
            <?php if ($wyniki_wysz->num_rows > 0): ?>
                <?php while ($post = $wyniki_wysz->fetch_assoc()):
                    $th = htmlspecialchars($post['tresc']);
                    $fr = preg_quote(htmlspecialchars($_POST['fraza']), '/');
                    $th = preg_replace('/(' . $fr . ')/iu', '<span class="fraza-hit">$1</span>', $th);
                    $th = nl2br($th);
                ?>
                <div class="szukaj-wynik">
                    <span class="szukaj-nick"><?php echo htmlspecialchars($post['login']); ?></span>
                    <span class="szukaj-typ">[<?php echo $post['typ_postu']; ?>]</span>
                    <span class="szukaj-data"><?php echo $post['data_dodania']; ?></span>
                    <div class="szukaj-tresc"><?php echo $th; ?></div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="color:var(--txt-mute);font-style:italic">Brak wyników. Spróbuj innej frazy.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ══ ZAKŁADKA: USTAWIENIA MG ══════════════════════════════════ -->
<?php if ($czy_mg): ?>
<div id="tab-ustawienia" class="zakladka-tresc <?php if($domyslna_zakladka=='ustawienia') echo 'aktywna'; ?>">

    <form method="POST" class="form-mg">
        <h3>⚙️ Dane Sesji</h3>
        <div class="mg-row">
            <div>
                <label class="mg-label">Status Sesji</label>
                <select name="status_sesji" class="gp-sel">
                    <option value="Rekrutacja" <?php if($sesja['status']=='Rekrutacja') echo 'selected'; ?>>Rekrutacja (Otwarta)</option>
                    <option value="Trwa" <?php if($sesja['status']=='Trwa') echo 'selected'; ?>>Trwa (W toku)</option>
                </select>
            </div>
            <div>
                <label class="mg-label">Poziom Trudności</label>
                <select name="trudnosc" class="gp-sel">
                    <?php foreach (['Łatwa','Normalna','Wysoka','Koszmar'] as $t): ?>
                    <option value="<?php echo $t; ?>" <?php if($sesja['poziom_trudnosci']==$t) echo 'selected'; ?>><?php echo $t; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label class="mg-label">Tagi (po przecinku)</label>
        <input type="text" name="tagi_sesji" class="akcja-input" value="<?php echo htmlspecialchars($sesja['tagi']); ?>">
        <label class="mg-label">Ostrzeżenia treściowe</label>
        <input type="text" name="ostrzezenia" class="akcja-input" value="<?php echo htmlspecialchars($sesja['ostrzezenia'] ?? ''); ?>">
        <label class="mg-label">Opis / Wstęp Fabularny</label>
        <textarea name="opis_sesji" class="gp-textarea" style="min-height:140px"><?php echo htmlspecialchars($sesja['opis']); ?></textarea>
        <div style="text-align:right;margin-top:14px">
            <button type="submit" name="zapisz_ustawienia_sesji" class="btn-wyslij ember">💾 Aktualizuj</button>
        </div>
    </form>

    <div class="form-mg">
        <h3>👥 Zarządzanie Uczestnikami</h3>
        <?php foreach ($uczestnicy_tablica as $u): ?>
        <div class="gracz-item">
            <div>
                <b><?php echo htmlspecialchars($u['login']); ?></b>
                <span class="meta">· <?php echo $u['rola']; ?> · <?php echo $u['status_akceptacji']; ?></span>
            </div>
            <?php if ($u['id'] != $wlasciciel_id): ?>
                <form method="POST" class="gracz-akcje">
                    <input type="hidden" name="cel_id" value="<?php echo $u['id']; ?>">
                    <?php if ($u['status_akceptacji'] == 'Oczekuje'): ?>
                        <button type="submit" name="akcja_gracz" value="akceptuj" class="btn-mini" style="color:var(--neon-green);border-color:var(--neon-green)">✓ Akceptuj</button>
                    <?php endif; ?>
                    <?php if ($u['rola'] == 'Gracz'): ?>
                        <button type="submit" name="akcja_gracz" value="awans_mg" class="btn-mini" style="color:var(--neon-gold);border-color:var(--neon-gold)">↑ MG</button>
                    <?php elseif ($u['rola'] == 'Mistrz Gry'): ?>
                        <button type="submit" name="akcja_gracz" value="degradacja_gracz" class="btn-mini" style="color:var(--neon-ember);border-color:var(--neon-ember)">↓ Gracz</button>
                    <?php endif; ?>
                    <button type="submit" name="akcja_gracz" value="wyrzuc" class="btn-mini" style="color:var(--neon-red-hot);border-color:var(--neon-red)">✗ Wyrzuć</button>
                </form>
            <?php else: ?>
                <span style="color:var(--neon-gold);font-size:.78em;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase">◈ Właściciel</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══ ZAKŁADKA: ZAKOŃCZ SESJĘ (PODSUMOWANIE MG) ══════════════════ -->
<?php if ($czy_mg && !$czy_zakonczona):
    $WADY_LISTA = [
        // ─ Fizyczne: urazy, okaleczenia, upośledzenia zmysłów
        "Brak Kończyny","Jednooki","Głuchy","Całkowita Ślepota","Oszpecony","Utykający","Hemofiliak",
        "Astma","Krótkowidz","Daltonizm","Niedosłuch","Wolne Gojenie","Jąkanie","Migreny","Bezsenność",
        // ─ Psychiczne: traumy, fobie, zaburzenia pourazowe
        "Trauma Pourazowa","Depresja","Lęki Napadowe","Klaustrofobia","Lęk Wysokości","Lęk Tłumu",
        "Paranoik","Tchórz","Furiat","Odludek","Naiwny","Mizantrop","Brak Empatii",
        // ─ Nałogi i zaburzenia charakteru po przeżyciu
        "Nałogowiec","Hazardzista","Kleptomania",
        // ─ Społeczne
        "Zła Reputacja","Gadatliwy",
        // ─ Umysł (po urazach głowy, traumach)
        "Ociężały Umysł",
        // ─ Specyficzne
        "Pechowiec","Leniwy","Słabeusz"
    ];
    $GRUPY_REP = [
        'elita' => 'Elita',
        'ulica' => 'Ulica',
        'syndykat' => 'Syndykat',
        'wladze' => 'Władze',
        'spoleczenstwo' => 'Społecz.',
    ];
?>
<div id="tab-zakoncz" class="zakladka-tresc <?php if($domyslna_zakladka=='zakoncz') echo 'aktywna'; ?>">
    <div class="zakoncz-panel">
        <h3>🏁 Zakończenie Sesji — Podsumowanie MG</h3>
        <div class="zp-opis">
            Przyznaj każdemu graczowi punkty reputacji w 5 grupach (od −3 do +3). Dodaj notatkę podsumowującą jego udział. Jeśli w sesji doszło do trwałej konsekwencji (np. utrata kończyny) — wybierz odpowiednią wadę z listy, zostanie automatycznie dopisana do karty postaci.
            <br><br>
            <span style="color:var(--neon-red-hot);font-family:'JetBrains Mono',monospace;font-size:.88em">⚠ Operacja jest NIEODWRACALNA. Sesja zostanie zamknięta, a reputacja zapisana trwale.</span>
        </div>

        <form method="POST" onsubmit="return confirm('Czy na pewno chcesz zakończyć sesję? Tej operacji nie można cofnąć.')">

            <label class="mg-label" style="color:var(--neon-gold)">Podsumowanie fabularne sesji (dla wszystkich graczy)</label>
            <textarea name="podsumowanie_mg" class="gp-textarea" style="min-height:100px;margin-bottom:20px" placeholder="Jak skończyła się opowieść? Co udało się osiągnąć? Jakie konsekwencje czekają ocalałych?"></textarea>

            <?php
            // Tylko gracze zaakceptowani, nie MG
            $gracze_do_pods = array_filter($uczestnicy_tablica, function($u) use ($wlasciciel_id) {
                return $u['status_akceptacji']=='Zaakceptowany' && $u['rola']!='Mistrz Gry' && $u['id']!=$wlasciciel_id;
            });
            if (empty($gracze_do_pods)): ?>
                <div style="padding:20px;text-align:center;color:var(--txt-mute);font-style:italic">// Brak zaakceptowanych graczy do podsumowania</div>
            <?php else:
                foreach ($gracze_do_pods as $u):
                    $av = !empty($u['avatar']) ? $u['avatar'] : 'https://via.placeholder.com/40/0a0a0a/333?text=?';
            ?>
            <div class="gracz-podsumowanie">
                <div class="gp-naglowek">
                    <div class="gp-av" style="background-image:url('<?php echo htmlspecialchars($av); ?>')"></div>
                    <div class="gp-nick"><?php echo htmlspecialchars($u['login']); ?></div>
                </div>

                <label class="mg-label">Zmiana reputacji (−3 ... +3)</label>
                <div class="rep-siatka">
                    <?php foreach ($GRUPY_REP as $klucz => $nazwa): ?>
                    <div class="rep-item">
                        <div class="rep-lbl"><?php echo $nazwa; ?></div>
                        <input type="number" name="rep[<?php echo $u['id']; ?>][<?php echo $klucz; ?>]" value="0" min="-3" max="3" step="1">
                        <span class="rep-range">−3 ... +3</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="gp-row">
                    <div>
                        <label class="mg-label">Trwała konsekwencja (wada)</label>
                        <select name="kons_wada[<?php echo $u['id']; ?>]" class="gp-sel">
                            <option value="">— brak —</option>
                            <?php foreach ($WADY_LISTA as $w): ?>
                            <option value="<?php echo $w; ?>"><?php echo $w; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mg-label">Opis konsekwencji (skąd się wzięła)</label>
                        <input type="text" name="kons_opis[<?php echo $u['id']; ?>]" class="akcja-input" placeholder="np. odgryziona przez psa w zaułku" style="margin-bottom:0">
                    </div>
                </div>

                <label class="mg-label" style="margin-top:10px">Notatka MG (widzi ją gracz)</label>
                <textarea name="notatka[<?php echo $u['id']; ?>]" class="gp-textarea" placeholder="Jak postać sprawowała się w sesji?"></textarea>
            </div>
            <?php endforeach; endif; ?>

            <div style="text-align:right;padding-top:14px;border-top:1px dashed rgba(255,215,0,0.2)">
                <button type="submit" name="zakoncz_sesje" class="btn-wyslij gold">🏁 Zakończ sesję i zapisz podsumowanie</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ ZAKŁADKA: PODSUMOWANIE (PO ZAKOŃCZENIU) ══════════════════ -->
<?php if ($czy_zakonczona): ?>
<div id="tab-podsumowanie" class="zakladka-tresc <?php if($domyslna_zakladka=='podsumowanie') echo 'aktywna'; ?>">
    <div class="pods-widok">
        <div class="pods-naglowek" style="padding-bottom:12px">
            <div style="font-family:'Oswald',sans-serif;color:var(--neon-gold);font-size:1.3em;text-transform:uppercase;letter-spacing:2px;text-shadow:0 0 10px rgba(255,215,0,0.5)">🏆 Podsumowanie Sesji</div>
            <?php if ($sesja['data_zakonczenia']): ?>
                <span style="margin-left:auto;color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.85em">Zakończono: <?php echo $sesja['data_zakonczenia']; ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($sesja['podsumowanie_mg'])): ?>
        <div class="pods-tresc"><?php echo nl2br(htmlspecialchars($sesja['podsumowanie_mg'])); ?></div>
        <?php endif; ?>

        <!-- Podsumowania poszczególnych graczy -->
        <?php
        $GRUPY_REP = ['elita'=>'Elita','ulica'=>'Ulica','syndykat'=>'Syndykat','wladze'=>'Władze','spoleczenstwo'=>'Społecz.'];
        foreach ($podsumowania as $pods):
            $u_row = $polaczenie->query("SELECT login, avatar FROM gracze WHERE id=".(int)$pods['gracz_id'])->fetch_assoc();
            $av = !empty($u_row['avatar']) ? $u_row['avatar'] : 'https://via.placeholder.com/40/0a0a0a/333?text=?';
        ?>
        <div class="gracz-podsumowanie" style="margin-top:16px">
            <div class="gp-naglowek">
                <div class="gp-av" style="background-image:url('<?php echo htmlspecialchars($av); ?>')"></div>
                <div class="gp-nick"><?php echo htmlspecialchars($u_row['login']); ?></div>
            </div>

            <div class="pods-rep">
                <?php foreach ($GRUPY_REP as $klucz => $nazwa):
                    $v = (int)$pods['reputacja_' . ($klucz === 'spoleczenstwo' ? 'spoleczenstwo' : $klucz)];
                    $cls = $v > 0 ? 'plus' : ($v < 0 ? 'minus' : 'zero');
                    $sign = $v > 0 ? '+' : '';
                ?>
                <div class="rep-box-mini">
                    <div class="rlbl"><?php echo $nazwa; ?></div>
                    <div class="rval <?php echo $cls; ?>"><?php echo $sign . $v; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($pods['konsekwencja_wada'])): ?>
            <div class="konsekwencja-box">
                <b>☠ TRWAŁA KONSEKWENCJA:</b> <?php echo htmlspecialchars($pods['konsekwencja_wada']); ?>
                <?php if (!empty($pods['konsekwencja_opis'])): ?>
                    <br><span style="color:var(--txt-dim);font-style:italic">„<?php echo htmlspecialchars($pods['konsekwencja_opis']); ?>"</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($pods['notatka_mg'])): ?>
            <div class="pods-tresc" style="margin-top:12px;margin-bottom:0;border-left-color:var(--neon-cyan);font-style:normal">
                <div style="font-family:'Oswald',sans-serif;font-size:.78em;color:var(--txt-mute);text-transform:uppercase;letter-spacing:2px;margin-bottom:8px">Notatka MG</div>
                <?php echo nl2br(htmlspecialchars($pods['notatka_mg'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($podsumowania)): ?>
        <div style="padding:30px;text-align:center;color:var(--txt-mute);font-style:italic">// MG nie utworzył podsumowań dla graczy.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="mention-popup" class="mention-popup"></div>

<script>
function przelaczZakladke(id, el) {
    document.querySelectorAll('.zakladka-tresc').forEach(t => t.classList.remove('aktywna'));
    document.querySelectorAll('.menu-pl').forEach(b => b.classList.remove('aktywny'));
    const tab = document.getElementById('tab-' + id);
    if (tab) tab.classList.add('aktywna');
    el.classList.add('aktywny');
}

function pokazEdycje(id) {
    document.getElementById('post-tresc-' + id).style.display = 'none';
    document.getElementById('post-edycja-' + id).style.display = 'block';
}
function ukryjEdycje(id) {
    document.getElementById('post-tresc-' + id).style.display = 'block';
    document.getElementById('post-edycja-' + id).style.display = 'none';
}

// Ryzyko - wizualny stan zaznaczenia
document.querySelectorAll('.ryzyko-opt input').forEach(r => {
    r.addEventListener('change', function(){
        document.querySelectorAll('.ryzyko-opt').forEach(l => l.classList.remove('zaz'));
        if (this.checked) this.closest('.ryzyko-opt').classList.add('zaz');
    });
});

// ── MENTIONS (@Nick) ──────────────────────────────────
const uczestnicy = <?php echo $lista_nickow_json; ?>;
const tagInputs = document.querySelectorAll('.tag-input');
const popup = document.getElementById('mention-popup');
let currentInput = null, mentionStart = -1;

tagInputs.forEach(input => {
    input.addEventListener('input', function() {
        const val = this.value;
        const cur = this.selectionStart;
        const before = val.substring(0, cur);
        const at = before.lastIndexOf('@');
        if (at !== -1) {
            if (at === 0 || before[at-1] === ' ' || before[at-1] === '\n') {
                const srch = before.substring(at+1).toLowerCase();
                if (!srch.includes(' ')) {
                    const m = uczestnicy.filter(n => n.toLowerCase().startsWith(srch));
                    if (m.length > 0) {
                        currentInput = this; mentionStart = at;
                        showPop(this, m); return;
                    }
                }
            }
        }
        hidePop();
    });
});
function showPop(input, matches) {
    popup.innerHTML = '';
    matches.forEach(n => {
        const d = document.createElement('div');
        d.className = 'mention-item'; d.innerText = '@' + n;
        d.onclick = () => insertMention(n);
        popup.appendChild(d);
    });
    const r = input.getBoundingClientRect();
    popup.style.top = (window.scrollY + r.bottom + 4) + 'px';
    popup.style.left = (window.scrollX + r.left) + 'px';
    popup.style.width = Math.min(r.width, 250) + 'px';
    popup.style.display = 'block';
}
function hidePop() { popup.style.display = 'none'; currentInput = null; mentionStart = -1; }
function insertMention(nick) {
    if (!currentInput) return;
    const v = currentInput.value;
    const before = v.substring(0, mentionStart);
    const after = v.substring(currentInput.selectionStart);
    currentInput.value = before + '@' + nick + ' ' + after;
    currentInput.focus(); hidePop();
}
document.addEventListener('click', e => {
    if (e.target !== popup && !popup.contains(e.target) && !e.target.classList.contains('tag-input')) hidePop();
});
</script>