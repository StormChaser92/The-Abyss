<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// ═══════════════════════════════════════════════════════════════════
// DEFINICJE RÓL I UPRAWNIEŃ
// ═══════════════════════════════════════════════════════════════════
$ROLE = [
    'Don' => [
        'ranga' => 7, 'ikona' => '👑', 'kolor' => 'var(--neon-gold)',
        'opis' => 'Głowa rodziny. Jego słowo jest prawem.',
        'limit' => 1,
    ],
    'Consigliere' => [
        'ranga' => 6, 'ikona' => '🎩', 'kolor' => 'var(--neon-red-hot)',
        'opis' => 'Prawa ręka Dona. Doradca, powiernik, cień.',
        'limit' => 1,
    ],
    'Capo' => [
        'ranga' => 5, 'ikona' => '⭐', 'kolor' => 'var(--neon-ember)',
        'opis' => 'Kapitan ekipy bojowej. Dowodzi żołnierzami na ulicy.',
        'limit' => 3,
    ],
    'Kwatermistrz' => [
        'ranga' => 4, 'ikona' => '🔑', 'kolor' => 'var(--neon-cyan)',
        'opis' => 'Strażnik skarbca. Wydaje broń, pancerze i kasę.',
        'limit' => 2,
    ],
    'Rekruter' => [
        'ranga' => 3, 'ikona' => '📋', 'kolor' => 'var(--neon-green)',
        'opis' => 'Werbuje nowych. Pierwszy ocenia czy kandydat jest wart zachodu.',
        'limit' => 3,
    ],
    'Żołnierz' => [
        'ranga' => 2, 'ikona' => '🔫', 'kolor' => '#d8d8d8',
        'opis' => 'Pełnoprawny członek. Zarobił sobie miejsce przy stole.',
        'limit' => 999,
    ],
    'Picciotto' => [
        'ranga' => 1, 'ikona' => '👤', 'kolor' => 'var(--txt-mute)',
        'opis' => 'Nowicjusz na próbie. Musi jeszcze zasłużyć na zaufanie.',
        'limit' => 999,
    ],
];

// Sprawdzenie uprawnień
function syn_moze($rola, $akcja) {
    $macierz = [
        'Don'          => ['rozwiaz','kasa_wyplata','arsenal','rekrutacja','dowodztwo','awansy','wyrzucanie','edycja_info','pozyczki'],
        'Consigliere'  => ['kasa_wyplata','arsenal','rekrutacja','dowodztwo','awansy','wyrzucanie','pozyczki'],
        'Capo'         => ['arsenal','rekrutacja','dowodztwo','wyrzucanie','pozyczki'],
        'Kwatermistrz' => ['kasa_wyplata','arsenal','pozyczki'],
        'Rekruter'     => ['rekrutacja'],
        'Żołnierz'     => ['pozyczki'],
        'Picciotto'    => ['pozyczki_ograniczone'],
    ];
    return in_array($akcja, $macierz[$rola] ?? []);
}

function syn_ranga($rola) {
    global $ROLE;
    return $ROLE[$rola]['ranga'] ?? 0;
}

// ═══════════════════════════════════════════════════════════════════
// POBRANIE DANYCH GRACZA
// ═══════════════════════════════════════════════════════════════════
$wynik = $polaczenie->query("SELECT login, poziom, gotowka, syndykat_id, syndykat_rola, syndykat_data_dolaczenia FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

$w_syndykacie = ($gracz['syndykat_id'] > 0);
$widok = isset($_GET['widok']) ? $_GET['widok'] : 'lista';
$moja_rola = $gracz['syndykat_rola'] ?? 'Brak';
$moja_ranga = syn_ranga($moja_rola);

// ═══════════════════════════════════════════════════════════════════
// LOGIKA POST
// ═══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // ── ZAKŁADANIE SYNDYKATU ─────────────────────────────────────
    if (isset($_POST['zaloz_syndykat']) && !$w_syndykacie) {
        $nazwa = $polaczenie->real_escape_string(trim($_POST['nazwa_syn']));
        $tag = $polaczenie->real_escape_string(trim(strtoupper($_POST['tag_syn'])));
        $motto = $polaczenie->real_escape_string(trim($_POST['motto_syn'] ?? ''));
        $herb = $polaczenie->real_escape_string(trim($_POST['herb_syn'] ?? '⚜'));
        $kolor = $polaczenie->real_escape_string(trim($_POST['kolor_syn'] ?? '#ff1744'));
        $koszt = 1000000;

        if ($gracz['poziom'] < 50) {
            $komunikat = "<div class='msg-blad'>⚠ Wymagany poziom 50, aby założyć syndykat.</div>";
        } elseif ($gracz['gotowka'] < $koszt) {
            $komunikat = "<div class='msg-blad'>⚠ Brak środków — potrzeba 1 000 000 $ w gotówce.</div>";
        } elseif (strlen($nazwa) < 4 || strlen($nazwa) > 30) {
            $komunikat = "<div class='msg-blad'>⚠ Nazwa syndykatu: 4-30 znaków.</div>";
        } elseif (strlen($tag) < 2 || strlen($tag) > 5) {
            $komunikat = "<div class='msg-blad'>⚠ Tag: 2-5 znaków.</div>";
        } else {
            $check = $polaczenie->query("SELECT id FROM syndykaty WHERE nazwa='$nazwa' OR tag='$tag'");
            if ($check->num_rows > 0) {
                $komunikat = "<div class='msg-blad'>⚠ Ta nazwa lub tag są już zajęte.</div>";
            } else {
                $polaczenie->query("INSERT INTO syndykaty (nazwa, tag, wlasciciel_id, motto, herb_emoji, kolor_akcent) 
                                    VALUES ('$nazwa', '$tag', $id_gracza, '$motto', '$herb', '$kolor')");
                $nowy_id = $polaczenie->insert_id;
                $polaczenie->query("UPDATE gracze SET 
                    gotowka = gotowka - $koszt, 
                    syndykat_id = $nowy_id, 
                    syndykat_rola = 'Don', 
                    syndykat_dostep_skarbiec = 1,
                    syndykat_data_dolaczenia = NOW()
                    WHERE id = $id_gracza");
                $polaczenie->query("DELETE FROM syndykat_podania WHERE gracz_id = $id_gracza");
                echo "<script>window.location.href='game.php?page=syndykaty&widok=wewnatrz';</script>";
                exit;
            }
        }
    }

    // ── APLIKOWANIE ──────────────────────────────────────────────
    if (isset($_POST['aplikuj']) && !$w_syndykacie) {
        $cel_id = (int)$_POST['id_syndykatu'];
        $check = $polaczenie->query("SELECT id FROM syndykat_podania WHERE gracz_id=$id_gracza AND syndykat_id=$cel_id");
        if ($check->num_rows > 0) {
            $komunikat = "<div class='msg-blad'>⚠ Już złożyłeś podanie do tej rodziny.</div>";
        } else {
            $polaczenie->query("INSERT INTO syndykat_podania (syndykat_id, gracz_id) VALUES ($cel_id, $id_gracza)");
            $komunikat = "<div class='msg-ok'>✓ Podanie wysłane. Czekaj na decyzję.</div>";
        }
    }

    // ── OPUSZCZANIE / ROZWIĄZANIE ────────────────────────────────
    if (isset($_POST['opusc_syndykat']) && $w_syndykacie) {
        $syn_id = $gracz['syndykat_id'];
        if ($moja_rola == 'Don') {
            // Rozwiązanie syndykatu
            $polaczenie->query("UPDATE gracze SET syndykat_id = 0, syndykat_rola = 'Brak', syndykat_dostep_skarbiec = 0, syndykat_data_dolaczenia = NULL WHERE syndykat_id = $syn_id");
            $polaczenie->query("DELETE FROM syndykat_podania WHERE syndykat_id = $syn_id");
            $polaczenie->query("DELETE FROM syndykaty WHERE id = $syn_id");
            echo "<script>window.location.href='game.php?page=syndykaty';</script>";
            exit;
        } else {
            $polaczenie->query("UPDATE gracze SET syndykat_id = 0, syndykat_rola = 'Brak', syndykat_dostep_skarbiec = 0, syndykat_data_dolaczenia = NULL WHERE id = $id_gracza");
            echo "<script>window.location.href='game.php?page=syndykaty';</script>";
            exit;
        }
    }

    // ── WPŁATA DO KASY ───────────────────────────────────────────
    if (isset($_POST['wplac_kase']) && $w_syndykacie) {
        $syn_id = $gracz['syndykat_id'];
        $kwota = (int)$_POST['kwota_wplaty'];
        if ($kwota > 0 && $gracz['gotowka'] >= $kwota) {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $kwota WHERE id = $id_gracza");
            $polaczenie->query("UPDATE syndykaty SET skarbiec = skarbiec + $kwota WHERE id = $syn_id");
            $gracz['gotowka'] -= $kwota;
            $komunikat = "<div class='msg-ok'>✓ Wpłacono ".number_format($kwota,0,'',' ')." $ do kasy rodziny.</div>";
        } else {
            $komunikat = "<div class='msg-blad'>⚠ Nieprawidłowa kwota lub brak środków.</div>";
        }
    }

    // ── WYPŁATA Z KASY (tylko uprawnieni) ────────────────────────
    if (isset($_POST['wyplac_kase']) && $w_syndykacie && syn_moze($moja_rola, 'kasa_wyplata')) {
        $syn_id = $gracz['syndykat_id'];
        $kwota = (int)$_POST['kwota_wyplaty'];
        $sprawdz = $polaczenie->query("SELECT skarbiec FROM syndykaty WHERE id=$syn_id")->fetch_assoc();
        if ($kwota > 0 && $sprawdz['skarbiec'] >= $kwota) {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $kwota WHERE id = $id_gracza");
            $polaczenie->query("UPDATE syndykaty SET skarbiec = skarbiec - $kwota WHERE id = $syn_id");
            $komunikat = "<div class='msg-ok'>✓ Wypłacono ".number_format($kwota,0,'',' ')." $ z kasy.</div>";
        } else {
            $komunikat = "<div class='msg-blad'>⚠ Nieprawidłowa kwota lub kasa ma za mało środków.</div>";
        }
    }

    // ── PRZYJĘCIE PODANIA (Don, Consigliere, Capo, Rekruter) ─────
    if (isset($_POST['akceptuj_podanie']) && $w_syndykacie && syn_moze($moja_rola, 'rekrutacja')) {
        $aplikant_id = (int)$_POST['id_aplikanta'];
        $syn_id = $gracz['syndykat_id'];
        // Nowi dostają rangę Picciotto
        $polaczenie->query("UPDATE gracze SET 
            syndykat_id = $syn_id, 
            syndykat_rola = 'Picciotto', 
            syndykat_dostep_skarbiec = 0,
            syndykat_data_dolaczenia = NOW()
            WHERE id = $aplikant_id");
        $polaczenie->query("DELETE FROM syndykat_podania WHERE gracz_id = $aplikant_id");
        $komunikat = "<div class='msg-ok'>✓ Nowy Picciotto dołączył do rodziny.</div>";
    }

    // ── ODRZUCENIE PODANIA ───────────────────────────────────────
    if (isset($_POST['odrzuc_podanie']) && $w_syndykacie && syn_moze($moja_rola, 'rekrutacja')) {
        $aplikant_id = (int)$_POST['id_aplikanta'];
        $syn_id = $gracz['syndykat_id'];
        $polaczenie->query("DELETE FROM syndykat_podania WHERE gracz_id = $aplikant_id AND syndykat_id = $syn_id");
        $komunikat = "<div class='msg-ok'>✓ Podanie odrzucone.</div>";
    }

    // ── ZMIANA ROLI (Don/Consigliere → awansy) ───────────────────
    if (isset($_POST['zmien_role']) && $w_syndykacie && syn_moze($moja_rola, 'awansy')) {
        $cel_id = (int)$_POST['id_czlonka'];
        $nowa_rola = $_POST['nowa_rola'] ?? '';
        $syn_id = $gracz['syndykat_id'];

        if (!isset($ROLE[$nowa_rola])) {
            $komunikat = "<div class='msg-blad'>⚠ Nieznana rola.</div>";
        } elseif ($nowa_rola == 'Don') {
            $komunikat = "<div class='msg-blad'>⚠ Nie można przyznać rangi Don. Don jest jeden.</div>";
        } elseif ($cel_id == $id_gracza) {
            $komunikat = "<div class='msg-blad'>⚠ Nie możesz zmieniać własnej rangi.</div>";
        } else {
            $cel_dane = $polaczenie->query("SELECT syndykat_rola FROM gracze WHERE id=$cel_id AND syndykat_id=$syn_id")->fetch_assoc();
            if (!$cel_dane) {
                $komunikat = "<div class='msg-blad'>⚠ Gracz nie należy do syndykatu.</div>";
            } else {
                $ranga_celu = syn_ranga($cel_dane['syndykat_rola']);
                $ranga_docelowa = syn_ranga($nowa_rola);
                // Consigliere nie może awansować na Consigliere
                if ($moja_rola == 'Consigliere' && $nowa_rola == 'Consigliere') {
                    $komunikat = "<div class='msg-blad'>⚠ Tylko Don może mianować Consigliere.</div>";
                } elseif ($ranga_celu >= $moja_ranga || $ranga_docelowa >= $moja_ranga) {
                    $komunikat = "<div class='msg-blad'>⚠ Nie możesz przyznać rangi równej lub wyższej niż twoja.</div>";
                } else {
                    // Sprawdź limit roli
                    $limit = $ROLE[$nowa_rola]['limit'];
                    $obecnie = $polaczenie->query("SELECT COUNT(*) as c FROM gracze WHERE syndykat_id=$syn_id AND syndykat_rola='$nowa_rola'")->fetch_assoc();
                    if ($obecnie['c'] >= $limit) {
                        $komunikat = "<div class='msg-blad'>⚠ Osiągnięto limit {$limit} dla rangi &bdquo;{$nowa_rola}&rdquo;.</div>";
                    } else {
                        $nowa_rola_esc = $polaczenie->real_escape_string($nowa_rola);
                        // Kwatermistrz = auto dostęp do skarbca
                        $dostep = in_array($nowa_rola, ['Don','Consigliere','Kwatermistrz']) ? 1 : 0;
                        $polaczenie->query("UPDATE gracze SET syndykat_rola='$nowa_rola_esc', syndykat_dostep_skarbiec=$dostep WHERE id=$cel_id AND syndykat_id=$syn_id");
                        $komunikat = "<div class='msg-ok'>✓ Zmieniono rangę gracza na &bdquo;{$nowa_rola}&rdquo;.</div>";
                    }
                }
            }
        }
    }

    // ── WYRZUCENIE CZŁONKA ───────────────────────────────────────
    if (isset($_POST['wyrzuc_czlonka']) && $w_syndykacie && syn_moze($moja_rola, 'wyrzucanie')) {
        $cel_id = (int)$_POST['id_czlonka'];
        $syn_id = $gracz['syndykat_id'];
        if ($cel_id == $id_gracza) {
            $komunikat = "<div class='msg-blad'>⚠ Nie możesz wyrzucić sam siebie.</div>";
        } else {
            $cel_dane = $polaczenie->query("SELECT syndykat_rola FROM gracze WHERE id=$cel_id AND syndykat_id=$syn_id")->fetch_assoc();
            if (!$cel_dane) {
                $komunikat = "<div class='msg-blad'>⚠ Gracz nie należy do syndykatu.</div>";
            } else {
                $ranga_celu = syn_ranga($cel_dane['syndykat_rola']);
                if ($ranga_celu >= $moja_ranga) {
                    $komunikat = "<div class='msg-blad'>⚠ Nie możesz wyrzucić kogoś równego lub wyższego rangą.</div>";
                } else {
                    $polaczenie->query("UPDATE gracze SET syndykat_id=0, syndykat_rola='Brak', syndykat_dostep_skarbiec=0, syndykat_data_dolaczenia=NULL WHERE id=$cel_id");
                    $komunikat = "<div class='msg-ok'>✓ Gracz usunięty z rodziny.</div>";
                }
            }
        }
    }

    // ── EDYCJA INFO SYNDYKATU (Don only) ─────────────────────────
    if (isset($_POST['edytuj_info']) && $w_syndykacie && syn_moze($moja_rola, 'edycja_info')) {
        $syn_id = $gracz['syndykat_id'];
        $motto = $polaczenie->real_escape_string(trim($_POST['motto'] ?? ''));
        $herb = $polaczenie->real_escape_string(trim($_POST['herb'] ?? '⚜'));
        $kolor = $polaczenie->real_escape_string(trim($_POST['kolor'] ?? '#ff1744'));
        $opis = $polaczenie->real_escape_string(trim($_POST['opis'] ?? ''));
        $polaczenie->query("UPDATE syndykaty SET motto='$motto', herb_emoji='$herb', kolor_akcent='$kolor', opis='$opis' WHERE id=$syn_id");
        $komunikat = "<div class='msg-ok'>✓ Wizytówka syndykatu zaktualizowana.</div>";
    }
}

// Ponowne pobranie danych (po ewentualnych zmianach)
$wynik = $polaczenie->query("SELECT login, poziom, gotowka, syndykat_id, syndykat_rola, syndykat_data_dolaczenia FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();
$w_syndykacie = ($gracz['syndykat_id'] > 0);
$moja_rola = $gracz['syndykat_rola'] ?? 'Brak';
$moja_ranga = syn_ranga($moja_rola);

// Dane syndykatu gracza
$syndykat = null;
if ($w_syndykacie) {
    $syn_id = $gracz['syndykat_id'];
    $syndykat = $polaczenie->query("SELECT * FROM syndykaty WHERE id=$syn_id")->fetch_assoc();
}
?>

<style>
/* ═══════════════════════════════════════════════════════════════════
   SYNDYKATY.PHP — layout zsynchronizowany z karta.php
═══════════════════════════════════════════════════════════════════ */

/* ── NAGŁÓWEK ───────────────────────────────────────────────── */
.syn-head { text-align: center; margin-bottom: 30px; }
.syn-head h1 {
    font-family: 'Oswald', sans-serif; color: #fff; font-size: 2.8em; margin: 0;
    text-transform: uppercase; letter-spacing: 4px; font-weight: 500; line-height: 1;
    text-shadow: 0 0 20px rgba(255,23,68,0.3);
}
.syn-head p {
    color: var(--neon-red); font-size: .75em; margin-top: 8px;
    font-family: 'JetBrains Mono', monospace; letter-spacing: 4px; text-transform: uppercase;
    text-shadow: 0 0 6px rgba(255,23,68,0.5);
}

/* ── KOMUNIKATY ─────────────────────────────────────────────── */
.msg-ok, .msg-blad {
    padding: 13px 16px; border-radius: 2px; margin-bottom: 18px;
    font-weight: 500; text-align: center;
    font-family: 'Oswald', sans-serif; letter-spacing: 1.5px;
}
.msg-ok {
    background: rgba(90,255,154,0.08); border: 1px solid rgba(90,255,154,0.4);
    color: var(--neon-green); box-shadow: 0 0 20px rgba(90,255,154,0.1);
}
.msg-blad {
    background: rgba(255,23,68,0.1); border: 1px solid var(--border-mid);
    color: var(--neon-red-hot); box-shadow: 0 0 20px rgba(255,23,68,0.15);
}

/* ── BLOK (identyczny z karta.php) ───────────────────────────── */
.blok {
    background: rgba(10,6,12,0.6); backdrop-filter: blur(8px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 28px; margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    position: relative;
}
.blok::before {
    content: ''; position: absolute; top: 0; left: 0; width: 32px; height: 1px;
    background: var(--akcent, var(--neon-red)); box-shadow: 0 0 6px var(--akcent, var(--neon-red));
}
.blok-tytul {
    font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 2.5px;
    font-size: 1.05em; color: #fff; margin: 0 0 20px; font-weight: 500;
    padding-bottom: 12px; border-bottom: 1px solid var(--border-soft);
    display: flex; align-items: center; gap: 10px;
}
.blok-tytul .note {
    color: var(--txt-mute); font-size: .72em; font-weight: 400;
    text-transform: none; margin-left: auto; letter-spacing: .5px;
}

/* ── NAWIGACJA POWROTNA ─────────────────────────────────────── */
.syn-back {
    display: inline-block; margin-bottom: 18px;
    color: var(--txt-mute); text-decoration: none;
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    letter-spacing: 2px; text-transform: uppercase;
    transition: .2s;
}
.syn-back:hover { color: var(--neon-red); text-shadow: 0 0 6px var(--neon-red); }

/* ── KARTA SYNDYKATU (lista) ─────────────────────────────────── */
.syn-lista-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.syn-karta-mini {
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft);
    border-radius: 2px; padding: 20px;
    transition: .25s; position: relative; overflow: hidden;
}
.syn-karta-mini::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
    background: var(--akcent); box-shadow: 0 0 12px var(--akcent);
}
.syn-karta-mini:hover {
    transform: translateY(-3px); border-color: var(--border-mid);
    box-shadow: 0 8px 25px rgba(0,0,0,0.7), 0 0 15px rgba(255,23,68,0.1);
}
.syn-karta-mini.moj { border-color: var(--neon-green); }
.syn-karta-mini.moj::after {
    content: 'TWOJA RODZINA'; position: absolute; top: 10px; right: 10px;
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    color: var(--neon-green); letter-spacing: 2px; padding: 2px 6px;
    border: 1px solid var(--neon-green); border-radius: 1px;
    text-shadow: 0 0 6px rgba(90,255,154,0.5);
}
.syn-karta-head { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
.syn-herb {
    font-size: 2.4em; line-height: 1;
    filter: drop-shadow(0 0 10px var(--akcent));
    width: 56px; height: 56px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,0.4); border: 1px dashed var(--akcent);
    border-radius: 2px;
}
.syn-identyfikacja { flex: 1; min-width: 0; }
.syn-tag {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--akcent); letter-spacing: 2px;
    text-shadow: 0 0 6px var(--akcent); margin-bottom: 2px;
}
.syn-nazwa {
    font-family: 'Oswald', sans-serif; font-size: 1.25em; color: #fff;
    letter-spacing: 1px; text-transform: uppercase; line-height: 1.1;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.syn-motto {
    font-style: italic; color: var(--txt-dim); font-size: .85em;
    margin: 8px 0; line-height: 1.4;
    border-left: 2px solid var(--akcent); padding-left: 10px;
}
.syn-meta {
    font-family: 'JetBrains Mono', monospace; font-size: .75em;
    color: var(--txt-mute); letter-spacing: 1px; margin: 10px 0;
    display: flex; justify-content: space-between; gap: 10px;
}
.syn-meta-pole strong { color: var(--txt-dim); }

/* ── KARTA HERO (wewnątrz syndykatu) ─────────────────────────── */
.syn-hero {
    background: rgba(10,6,12,0.8); backdrop-filter: blur(12px);
    border: 1px solid var(--akcent); border-radius: 2px;
    padding: 28px; margin-bottom: 24px; position: relative; overflow: hidden;
    box-shadow: 0 0 40px rgba(0,0,0,0.7), 0 0 20px var(--akcent);
}
.syn-hero::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: var(--akcent); box-shadow: 0 0 14px var(--akcent);
}
.syn-hero-main {
    display: flex; align-items: center; gap: 22px; flex-wrap: wrap;
}
.syn-hero-herb {
    font-size: 4.5em; line-height: 1; width: 110px; height: 110px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,0.5); border: 2px dashed var(--akcent);
    border-radius: 2px; filter: drop-shadow(0 0 15px var(--akcent));
}
.syn-hero-info { flex: 1; min-width: 200px; }
.syn-hero-tag {
    font-family: 'JetBrains Mono', monospace; font-size: .9em;
    color: var(--akcent); letter-spacing: 3px; text-transform: uppercase;
    text-shadow: 0 0 8px var(--akcent); margin-bottom: 4px;
}
.syn-hero-nazwa {
    font-family: 'Oswald', sans-serif; font-size: 2.6em; color: #fff;
    letter-spacing: 3px; text-transform: uppercase; line-height: 1;
    text-shadow: 0 0 20px rgba(255,255,255,0.15);
}
.syn-hero-motto {
    font-style: italic; color: var(--txt-dim); font-size: 1em;
    margin-top: 10px; line-height: 1.5;
}
.syn-hero-rola {
    text-align: right; padding-left: 20px;
    border-left: 1px solid var(--border-soft);
}
.syn-hero-rola .lab {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase;
}
.syn-hero-rola .wart {
    font-family: 'Oswald', sans-serif; font-size: 1.6em;
    color: var(--rola-kolor, var(--neon-gold));
    letter-spacing: 1.5px; text-transform: uppercase;
    text-shadow: 0 0 10px var(--rola-kolor, var(--neon-gold));
    margin-top: 4px;
}

/* ── SKRÓTY AKCJI ────────────────────────────────────────────── */
.szybkie-akcje {
    display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;
    padding-top: 20px; border-top: 1px dashed var(--border-soft);
}
.akcja-btn {
    flex: 1; min-width: 140px; padding: 11px 14px;
    background: rgba(0,0,0,0.6); border: 1px solid var(--border-soft);
    color: var(--txt-dim); font-family: 'Oswald', sans-serif;
    letter-spacing: 1.5px; text-transform: uppercase; font-size: .9em;
    cursor: pointer; text-align: center; text-decoration: none;
    transition: .25s; border-radius: 2px;
}
.akcja-btn:hover {
    background: rgba(255,23,68,0.08); border-color: var(--neon-red);
    color: var(--neon-red-hot); text-shadow: 0 0 6px rgba(255,23,68,0.4);
}
.akcja-btn.niebezpieczna:hover {
    background: rgba(255,23,68,0.15); border-color: var(--neon-red);
    color: #fff; box-shadow: 0 0 15px rgba(255,23,68,0.4);
}
.akcja-btn.zarzadzaj:hover {
    background: rgba(255,215,0,0.08); border-color: var(--neon-gold);
    color: var(--neon-gold); text-shadow: 0 0 6px rgba(255,215,0,0.5);
}

/* ── SKARBIEC — 3 SEKCJE ─────────────────────────────────────── */
.skarb-sekcje {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px;
}
.skarb-slot {
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft);
    border-radius: 2px; padding: 20px; position: relative; overflow: hidden;
}
.skarb-slot.aktywny { border-color: rgba(255,215,0,0.3); }
.skarb-slot.aktywny::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px;
    background: var(--neon-gold); box-shadow: 0 0 8px var(--neon-gold);
}
.skarb-slot.placeholder { opacity: .55; border-style: dashed; }
.skarb-slot-tytul {
    font-family: 'Oswald', sans-serif; font-size: .8em; color: var(--txt-mute);
    letter-spacing: 2.5px; text-transform: uppercase; margin-bottom: 12px;
    display: flex; align-items: center; gap: 6px;
}
.skarb-wart {
    font-family: 'Oswald', sans-serif; font-size: 2em; font-weight: 500;
    color: var(--neon-gold); letter-spacing: 1.5px; line-height: 1;
    text-shadow: 0 0 10px rgba(255,215,0,0.5); margin-bottom: 12px;
}
.skarb-placeholder-info {
    font-family: 'JetBrains Mono', monospace; font-size: .78em;
    color: var(--txt-mute); letter-spacing: 1px; line-height: 1.5;
    padding: 12px 0;
}
.skarb-placeholder-info strong {
    color: var(--neon-ember); text-shadow: 0 0 6px rgba(255,122,61,0.4);
}
.skarb-ukryty {
    text-align: center; padding: 40px 20px;
    background: repeating-linear-gradient(
        45deg,
        rgba(0,0,0,0.6),
        rgba(0,0,0,0.6) 10px,
        rgba(255,23,68,0.04) 10px,
        rgba(255,23,68,0.04) 20px
    );
    border: 1px solid var(--border-mid);
    font-family: 'Oswald', sans-serif; text-transform: uppercase;
    color: var(--neon-red-hot); font-size: 1.2em; letter-spacing: 2px;
    text-shadow: 0 0 8px rgba(255,23,68,0.5);
}
.skarb-ukryty .sub {
    display: block; margin-top: 8px;
    font-family: 'Open Sans', sans-serif; font-size: .62em;
    color: var(--txt-mute); text-transform: none; letter-spacing: 0;
    line-height: 1.5; font-style: italic;
}

/* ── PODGLĄD CZŁONKÓW ─────────────────────────────────────── */
.czl-preview-lista {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
}
.czl-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; background: rgba(0,0,0,0.45);
    border: 1px solid rgba(255,23,68,0.08); border-radius: 2px;
    font-size: .88em;
}
.czl-row .czl-ikona {
    font-size: 1.1em; flex-shrink: 0; filter: drop-shadow(0 0 4px var(--rola-kolor, #fff));
}
.czl-row .czl-login {
    flex: 1; min-width: 0;
    font-family: 'Oswald', sans-serif; letter-spacing: .5px;
    color: var(--txt-main); text-transform: uppercase; font-size: .92em;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.czl-row .czl-rola {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--rola-kolor, var(--txt-mute)); letter-spacing: 1px;
    text-shadow: 0 0 4px var(--rola-kolor, transparent);
}

/* ── INPUT / FORMULARZ ────────────────────────────────────── */
.syn-input {
    width: 100%; padding: 11px 14px; box-sizing: border-box;
    background: rgba(0,0,0,0.55); border: 1px solid var(--border-soft);
    color: #fff; border-radius: 2px; font-size: .95em;
    font-family: 'Oswald', sans-serif; letter-spacing: 1px;
    transition: .2s; margin-bottom: 14px;
}
.syn-input:focus {
    outline: none; border-color: var(--neon-red);
    box-shadow: 0 0 10px rgba(255,23,68,0.3);
}
.syn-label {
    display: block; font-family: 'JetBrains Mono', monospace;
    font-size: .72em; color: var(--txt-mute); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 6px;
}
.btn-akcja {
    background: rgba(255,23,68,0.08); color: var(--neon-red-hot);
    border: 1px solid var(--neon-red); padding: 12px 22px;
    font-family: 'Oswald', sans-serif; font-size: 1em; font-weight: 600;
    cursor: pointer; text-transform: uppercase; border-radius: 2px;
    transition: .3s; letter-spacing: 2.5px; text-align: center;
    text-decoration: none; display: inline-block;
}
.btn-akcja:hover {
    background: var(--neon-red); color: #fff;
    box-shadow: 0 0 20px rgba(255,23,68,0.6); text-shadow: 0 0 8px #fff;
}
.btn-akcja.green {
    background: rgba(90,255,154,0.08); color: var(--neon-green);
    border-color: var(--neon-green);
}
.btn-akcja.green:hover {
    background: var(--neon-green); color: #000;
    box-shadow: 0 0 18px rgba(90,255,154,0.5);
}
.btn-akcja.cyan {
    background: rgba(74,214,255,0.08); color: var(--neon-cyan);
    border-color: var(--neon-cyan);
}
.btn-akcja.cyan:hover {
    background: var(--neon-cyan); color: #000;
    box-shadow: 0 0 18px rgba(74,214,255,0.5);
}
.btn-akcja.gold {
    background: rgba(255,215,0,0.08); color: var(--neon-gold);
    border-color: var(--neon-gold);
}
.btn-akcja.gold:hover {
    background: var(--neon-gold); color: #000;
    box-shadow: 0 0 18px rgba(255,215,0,0.5);
}
.btn-akcja.full { width: 100%; }
.btn-akcja.mini { padding: 6px 12px; font-size: .8em; letter-spacing: 1px; }

/* ── HERB PICKER ─────────────────────────────────────────── */
.herb-picker {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
    gap: 6px; margin-bottom: 14px;
}
.herb-opcja {
    aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
    font-size: 1.6em; cursor: pointer; border-radius: 2px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft);
    transition: .2s;
}
.herb-opcja:hover {
    border-color: var(--neon-red); background: rgba(255,23,68,0.08);
}
.herb-opcja.wybrany {
    border-color: var(--neon-gold);
    background: rgba(255,215,0,0.12);
    box-shadow: 0 0 10px rgba(255,215,0,0.4);
}

/* ── KOLOR PICKER ─────────────────────────────────────────── */
.kolor-picker {
    display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px;
}
.kolor-opcja {
    width: 34px; height: 34px; border-radius: 2px; cursor: pointer;
    border: 2px solid transparent; transition: .2s;
}
.kolor-opcja:hover { transform: scale(1.1); }
.kolor-opcja.wybrany { border-color: #fff; box-shadow: 0 0 10px currentColor; }

/* ── TABELA (zarządzanie) ────────────────────────────────── */
.tabela-syn {
    width: 100%; border-collapse: collapse; margin-top: 12px;
    font-size: .9em;
}
.tabela-syn th {
    background: rgba(0,0,0,0.5);
    color: var(--txt-mute); font-family: 'Oswald', sans-serif;
    text-transform: uppercase; padding: 10px 12px;
    border-bottom: 1px solid var(--border-soft); text-align: left;
    font-size: .85em; letter-spacing: 1.5px; font-weight: 500;
}
.tabela-syn td {
    padding: 10px 12px; border-bottom: 1px dashed rgba(255,23,68,0.06);
    color: var(--txt-main); vertical-align: middle;
}
.tabela-syn tr:hover td { background: rgba(255,23,68,0.03); }
.tabela-syn select {
    background: rgba(0,0,0,0.6); color: #fff;
    border: 1px solid var(--border-soft); padding: 5px 8px;
    font-family: 'Oswald', sans-serif; letter-spacing: 1px;
    font-size: .85em; border-radius: 2px;
}

/* ── LEGENDA RÓL ─────────────────────────────────────────── */
.legenda-rol-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 10px;
}
.legenda-rol {
    padding: 12px 14px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); border-radius: 2px;
    display: flex; align-items: flex-start; gap: 10px;
}
.legenda-rol-head {
    display: flex; align-items: center; gap: 8px; margin-bottom: 4px;
}
.legenda-rol-ikona {
    font-size: 1.4em; filter: drop-shadow(0 0 6px var(--rola-kolor));
}
.legenda-rol-nazwa {
    font-family: 'Oswald', sans-serif; text-transform: uppercase;
    letter-spacing: 1.5px; color: var(--rola-kolor);
    font-size: .95em; text-shadow: 0 0 6px var(--rola-kolor);
}
.legenda-rol-opis {
    font-size: .78em; color: var(--txt-dim); line-height: 1.4;
}

/* ── IGRZYSKA PLACEHOLDER ─────────────────────────────────── */
.igrzyska-hero {
    text-align: center; padding: 40px 20px;
    background: radial-gradient(ellipse at center, rgba(255,215,0,0.08), transparent);
    border: 1px solid rgba(255,215,0,0.3); border-radius: 2px;
}
.igrzyska-status {
    font-family: 'JetBrains Mono', monospace; font-size: .9em;
    color: var(--neon-gold); letter-spacing: 4px;
    text-shadow: 0 0 8px rgba(255,215,0,0.6);
}
.igrzyska-tytul {
    font-family: 'Oswald', sans-serif; font-size: 2.5em;
    color: #fff; letter-spacing: 4px; text-transform: uppercase;
    margin: 10px 0; text-shadow: 0 0 20px rgba(255,215,0,0.3);
}
.igrzyska-opis {
    max-width: 640px; margin: 16px auto; line-height: 1.7;
    color: var(--txt-dim); font-size: .95em;
}
.postep-artefaktow {
    max-width: 500px; margin: 24px auto; padding: 16px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft);
    border-radius: 2px;
}
.postep-artefaktow .lab {
    font-family: 'JetBrains Mono', monospace; font-size: .75em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 10px;
}
.postep-bar {
    height: 8px; background: rgba(0,0,0,0.6); border-radius: 1px;
    overflow: hidden; position: relative;
}
.postep-wart {
    height: 100%; background: linear-gradient(to right, var(--neon-ember), var(--neon-gold));
    box-shadow: 0 0 10px var(--neon-gold);
    width: 0%;
}
.postep-liczby {
    margin-top: 8px; font-family: 'JetBrains Mono', monospace;
    font-size: .85em; color: var(--neon-gold);
}
</style>

<!-- ══ NAGŁÓWEK ══════════════════════════════════════════════════ -->
<div class="syn-head">
    <h1>Syndykaty Miasta</h1>
    <p>// Rodziny, Klany, Podziemie</p>
</div>

<?php echo $komunikat; ?>

<?php
// ═══════════════════════════════════════════════════════════════════
// WIDOK LISTA (domyślny / poza syndykatem)
// ═══════════════════════════════════════════════════════════════════
if ($widok == 'lista'):
?>

<div class="blok" style="--akcent: var(--neon-red)">
    <div class="blok-tytul">
        <span>🏛️</span> Wpływowe Rodziny
        <span class="note">Posortowane wg zgromadzonego majątku</span>
    </div>

    <?php
    $lista = $polaczenie->query("
        SELECT s.*, g.login, 
               (SELECT COUNT(*) FROM gracze WHERE syndykat_id = s.id) as liczba_czlonkow
        FROM syndykaty s 
        JOIN gracze g ON s.wlasciciel_id = g.id 
        ORDER BY s.skarbiec DESC
    ");

    if ($lista && $lista->num_rows > 0): ?>
    <div class="syn-lista-grid">
        <?php while($row = $lista->fetch_assoc()):
            $moj_syn = ($gracz['syndykat_id'] == $row['id']);
            $akcent = $row['kolor_akcent'] ?? '#ff1744';
        ?>
        <div class="syn-karta-mini <?php echo $moj_syn ? 'moj' : ''; ?>" style="--akcent: <?php echo htmlspecialchars($akcent); ?>">
            <div class="syn-karta-head">
                <div class="syn-herb"><?php echo $row['herb_emoji'] ?? '⚜'; ?></div>
                <div class="syn-identyfikacja">
                    <div class="syn-tag">[<?php echo htmlspecialchars($row['tag']); ?>]</div>
                    <div class="syn-nazwa"><?php echo htmlspecialchars($row['nazwa']); ?></div>
                </div>
            </div>

            <?php if (!empty($row['motto'])): ?>
            <div class="syn-motto">„<?php echo htmlspecialchars($row['motto']); ?>”</div>
            <?php endif; ?>

            <div class="syn-meta">
                <div class="syn-meta-pole"><strong>Don:</strong> <?php echo htmlspecialchars($row['login']); ?></div>
                <div class="syn-meta-pole"><strong>Ludzi:</strong> <?php echo (int)$row['liczba_czlonkow']; ?></div>
            </div>

            <?php if ($moj_syn): ?>
                <a href="game.php?page=syndykaty&widok=wewnatrz" class="btn-akcja green full">
                    ▸ Wejdź do kwatery
                </a>
            <?php elseif (!$w_syndykacie): ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="id_syndykatu" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="aplikuj" class="btn-akcja cyan full">
                        ✎ Złóż podanie
                    </button>
                </form>
            <?php else: ?>
                <div style="text-align:center; color:var(--txt-mute); font-family:'JetBrains Mono',monospace; font-size:.8em; padding:8px; letter-spacing:2px; text-transform:uppercase;">
                    Masz już rodzinę
                </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:40px; color:var(--txt-mute); font-style:italic;">
        Miasto jest jeszcze dziewicze. Kto pierwszy założy rodzinę, ten zbierze śmietankę.
    </div>
    <?php endif; ?>
</div>

<?php if (!$w_syndykacie): ?>
<!-- ── ZAŁÓŻ WŁASNY SYNDYKAT ──────────────────────────────── -->
<div class="blok" style="--akcent: var(--neon-gold)">
    <div class="blok-tytul">
        <span>✦</span> Załóż Własną Rodzinę
        <span class="note">Wymagania: poz. 50 · 1 000 000 $</span>
    </div>

    <div style="padding:14px 18px; margin-bottom:20px; background:rgba(255,215,0,0.05); border:1px solid rgba(255,215,0,0.2); border-radius:2px; position:relative;">
        <div style="position:absolute; left:0; top:0; bottom:0; width:2px; background:var(--neon-gold); box-shadow:0 0 8px var(--neon-gold);"></div>
        <p style="color:var(--txt-main); line-height:1.6; margin:0; font-size:.92em;">
            Założenie rodziny to nie żart. Potrzebujesz pieniędzy, by przekonać władze, <strong style="color:var(--neon-gold)">znajomości</strong>, by wynająć pierwsze mieszkanie na operacje i <strong style="color:var(--neon-gold)">szacunku</strong>, by inni za tobą poszli. Jesteś Donem — <em>głową rodziny</em>. Zbuduj imperium.
        </p>
    </div>

    <?php if ($gracz['poziom'] < 50): ?>
    <div class="msg-blad">⚠ Potrzebujesz poziomu <strong>50</strong>, aby założyć syndykat. Masz: <?php echo $gracz['poziom']; ?>.</div>
    <?php elseif ($gracz['gotowka'] < 1000000): ?>
    <div class="msg-blad">⚠ Potrzebujesz <strong>1 000 000 $</strong>. Masz: <?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $.</div>
    <?php else: ?>

    <form method="POST">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div>
                <label class="syn-label">Nazwa Rodziny (4-30 znaków)</label>
                <input type="text" name="nazwa_syn" class="syn-input" required minlength="4" maxlength="30" placeholder="np. Rodzina Marconi">
            </div>
            <div>
                <label class="syn-label">Tag (skrót, 2-5 znaków)</label>
                <input type="text" name="tag_syn" class="syn-input" required minlength="2" maxlength="5" style="text-transform:uppercase;" placeholder="np. MARC">
            </div>
        </div>

        <label class="syn-label">Motto (opcjonalne)</label>
        <input type="text" name="motto_syn" class="syn-input" maxlength="200" placeholder="np. Honor. Krew. Milczenie.">

        <label class="syn-label">Herb Rodziny</label>
        <div class="herb-picker" id="herb-picker-zaloz">
            <?php foreach (['⚜','💀','🗡','🩸','👁','♠','♣','⚔','🕊','🌹','🐺','🦅','🐍','🦂','⚓','🎭','🃏','🖤'] as $emoji): ?>
            <div class="herb-opcja" data-emoji="<?php echo $emoji; ?>" onclick="wybierzHerb(this, 'zaloz')"><?php echo $emoji; ?></div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="herb_syn" id="herb-input-zaloz" value="⚜">

        <label class="syn-label">Kolor Akcentu</label>
        <div class="kolor-picker" id="kolor-picker-zaloz">
            <?php foreach (['#ff1744','#ffd700','#4ad6ff','#ff7a3d','#5aff9a','#c896ff','#ff3d5e','#6b8eff','#ff8c00','#ec4899'] as $kolor): ?>
            <div class="kolor-opcja" data-kolor="<?php echo $kolor; ?>" style="background:<?php echo $kolor; ?>; box-shadow:0 0 8px <?php echo $kolor; ?>;" onclick="wybierzKolor(this, 'zaloz')"></div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="kolor_syn" id="kolor-input-zaloz" value="#ff1744">

        <button type="submit" name="zaloz_syndykat" class="btn-akcja gold full" 
                onclick="return confirm('To kosztuje 1 000 000 $ i odejmie je od twojej gotówki. Kontynuować?');">
            ◆ Podpisz akt założenia (−1 000 000 $) ◆
        </button>
    </form>

    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// ═══════════════════════════════════════════════════════════════════
// WIDOK WEWNĄTRZ SYNDYKATU
// ═══════════════════════════════════════════════════════════════════
elseif ($widok == 'wewnatrz' && $w_syndykacie):
    $akcent = $syndykat['kolor_akcent'] ?? '#ff1744';
    $herb = $syndykat['herb_emoji'] ?? '⚜';
    $rola_info = $ROLE[$moja_rola] ?? ['ikona'=>'?', 'kolor'=>'var(--txt-mute)', 'opis'=>'Nieznana rola'];
?>

<a href="game.php?page=syndykaty&widok=lista" class="syn-back">◂ Z powrotem na ulicę</a>

<!-- ══ HERO KARTA RODZINY ═══════════════════════════════════════ -->
<div class="syn-hero" style="--akcent: <?php echo htmlspecialchars($akcent); ?>; --rola-kolor: <?php echo $rola_info['kolor']; ?>">
    <div class="syn-hero-main">
        <div class="syn-hero-herb"><?php echo $herb; ?></div>
        <div class="syn-hero-info">
            <div class="syn-hero-tag">[<?php echo htmlspecialchars($syndykat['tag']); ?>]</div>
            <div class="syn-hero-nazwa"><?php echo htmlspecialchars($syndykat['nazwa']); ?></div>
            <?php if (!empty($syndykat['motto'])): ?>
            <div class="syn-hero-motto">„<?php echo htmlspecialchars($syndykat['motto']); ?>”</div>
            <?php endif; ?>
        </div>
        <div class="syn-hero-rola">
            <div class="lab">Twoja rola</div>
            <div class="wart"><?php echo $rola_info['ikona']; ?> <?php echo $moja_rola; ?></div>
            <div style="font-family:'JetBrains Mono',monospace; font-size:.7em; color:var(--txt-mute); letter-spacing:1px; margin-top:4px;">
                Ranga <?php echo $moja_ranga; ?>
            </div>
        </div>
    </div>

    <div class="szybkie-akcje">
        <?php if ($moja_rola == 'Don' || syn_moze($moja_rola, 'rekrutacja') || syn_moze($moja_rola, 'awansy') || syn_moze($moja_rola, 'wyrzucanie')): ?>
            <a href="game.php?page=syndykaty&widok=zarzadzanie" class="akcja-btn zarzadzaj">
                ⚙ Zarządzanie
            </a>
        <?php endif; ?>
        <a href="game.php?page=syndykaty&widok=igrzyska" class="akcja-btn">
            🏆 Igrzyska
        </a>
        <form method="POST" style="flex:1; min-width:140px; margin:0;">
            <?php if ($moja_rola == 'Don'): ?>
            <button type="submit" name="opusc_syndykat" class="akcja-btn niebezpieczna" style="width:100%;" 
                onclick="return confirm('UWAGA: Rozwiążesz całą rodzinę. Wszyscy członkowie stracą przynależność. Kontynuować?');">
                ✕ Rozwiąż rodzinę
            </button>
            <?php else: ?>
            <button type="submit" name="opusc_syndykat" class="akcja-btn niebezpieczna" style="width:100%;"
                onclick="return confirm('Opuścisz rodzinę. Jesteś pewien?');">
                ✕ Opuść rodzinę
            </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ══ BLOK: CZŁONKOWIE RODZINY ══════════════════════════════════ -->
<div class="blok" style="--akcent: <?php echo htmlspecialchars($akcent); ?>">
    <div class="blok-tytul">
        <span>👥</span> Członkowie Rodziny
        <?php
        $liczba = $polaczenie->query("SELECT COUNT(*) as c FROM gracze WHERE syndykat_id = {$syndykat['id']}")->fetch_assoc();
        ?>
        <span class="note"><?php echo (int)$liczba['c']; ?> osób w rodzinie</span>
    </div>

    <?php
    $czlonkowie = $polaczenie->query("
        SELECT id, login, syndykat_rola 
        FROM gracze 
        WHERE syndykat_id = {$syndykat['id']} 
        ORDER BY FIELD(syndykat_rola, 'Don','Consigliere','Capo','Kwatermistrz','Rekruter','Żołnierz','Picciotto'), login
    ");
    ?>

    <div class="czl-preview-lista">
        <?php while($cz = $czlonkowie->fetch_assoc()):
            $r_info = $ROLE[$cz['syndykat_rola']] ?? ['ikona'=>'?', 'kolor'=>'var(--txt-mute)'];
        ?>
        <div class="czl-row" style="--rola-kolor: <?php echo $r_info['kolor']; ?>">
            <span class="czl-ikona"><?php echo $r_info['ikona']; ?></span>
            <span class="czl-login" title="<?php echo htmlspecialchars($cz['login']); ?>"><?php echo htmlspecialchars($cz['login']); ?></span>
            <span class="czl-rola"><?php echo htmlspecialchars($cz['syndykat_rola']); ?></span>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- ══ BLOK: SKARBIEC ═══════════════════════════════════════════ -->
<div class="blok" style="--akcent: var(--neon-gold)">
    <div class="blok-tytul">
        <span>💎</span> Skarbiec Rodziny
        <span class="note">
            <?php if ($moja_rola == 'Picciotto'): ?>
                Twój status: brak wglądu
            <?php elseif (syn_moze($moja_rola, 'kasa_wyplata')): ?>
                Twój status: pełny dostęp
            <?php elseif (syn_moze($moja_rola, 'arsenal')): ?>
                Twój status: widzisz arsenał
            <?php else: ?>
                Twój status: tylko wpłaty
            <?php endif; ?>
        </span>
    </div>

    <?php 
    // Picciotto nie widzi w ogóle zawartości
    $widzi_skarbiec = ($moja_rola != 'Picciotto');
    ?>

    <?php if (!$widzi_skarbiec): ?>
        <div class="skarb-ukryty">
            🔒 Brak dostępu
            <span class="sub">
                Jako Picciotto jesteś jeszcze na próbie. Szefowie ci nie ufają —
                nikt nie pokaże ci swojej kasy komuś, kto może być kretem z innej rodziny.
                Zarób na zaufanie.
            </span>
        </div>
    <?php else: ?>

        <div class="skarb-sekcje">
            <!-- ── KASA ─────────────────────────────────────── -->
            <div class="skarb-slot aktywny">
                <div class="skarb-slot-tytul">
                    <span>💰</span> Kasa Rodziny
                </div>
                <div class="skarb-wart"><?php echo number_format($syndykat['skarbiec'], 0, '', ' '); ?> $</div>
                <div style="font-family:'JetBrains Mono',monospace; font-size:.72em; color:var(--txt-mute); letter-spacing:1px;">
                    Wolne środki gotówkowe
                </div>
            </div>

            <!-- ── ARSENAŁ BRONI (placeholder) ────────────────── -->
            <div class="skarb-slot placeholder">
                <div class="skarb-slot-tytul">
                    <span>🔫</span> Arsenał Broni
                </div>
                <div style="font-family:'Oswald',sans-serif; font-size:1.3em; color:var(--txt-mute); letter-spacing:2px; text-transform:uppercase; margin-bottom:10px;">
                    W budowie
                </div>
                <div class="skarb-placeholder-info">
                    Po aktualizacji systemu ekwipunku <strong>Kwatermistrz</strong> będzie mógł deponować broń, a Żołnierze pożyczać ją na arenę.
                </div>
            </div>

            <!-- ── ZBROJOWNIA PANCERZY (placeholder) ──────────── -->
            <div class="skarb-slot placeholder">
                <div class="skarb-slot-tytul">
                    <span>🛡️</span> Zbrojownia Pancerzy
                </div>
                <div style="font-family:'Oswald',sans-serif; font-size:1.3em; color:var(--txt-mute); letter-spacing:2px; text-transform:uppercase; margin-bottom:10px;">
                    W budowie
                </div>
                <div class="skarb-placeholder-info">
                    Wypożyczalnia pancerzy dla farmienia statystyk na arenie. <strong>Dostępna po aktualizacji ekwipunku.</strong>
                </div>
            </div>
        </div>

        <!-- ── FORMULARZE KASY ────────────────────────────── -->
        <div style="display:grid; grid-template-columns:<?php echo syn_moze($moja_rola, 'kasa_wyplata') ? '1fr 1fr' : '1fr'; ?>; gap:14px; margin-top:18px;">
            <form method="POST" style="margin:0;">
                <label class="syn-label">Wpłata do kasy</label>
                <input type="number" name="kwota_wplaty" class="syn-input" min="1" max="<?php echo (int)$gracz['gotowka']; ?>" placeholder="Ile $" required>
                <button type="submit" name="wplac_kase" class="btn-akcja green full">
                    ▸ Wpłać do kasy
                </button>
            </form>

            <?php if (syn_moze($moja_rola, 'kasa_wyplata')): ?>
            <form method="POST" style="margin:0;">
                <label class="syn-label">Wypłata z kasy</label>
                <input type="number" name="kwota_wyplaty" class="syn-input" min="1" max="<?php echo (int)$syndykat['skarbiec']; ?>" placeholder="Ile $" required>
                <button type="submit" name="wyplac_kase" class="btn-akcja full"
                    onclick="return confirm('Pobrać środki z kasy rodziny?');">
                    ◂ Pobierz z kasy
                </button>
            </form>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php
// ═══════════════════════════════════════════════════════════════════
// WIDOK IGRZYSKA (placeholder na Fazę 2)
// ═══════════════════════════════════════════════════════════════════
elseif ($widok == 'igrzyska' && $w_syndykacie):
?>

<a href="game.php?page=syndykaty&widok=wewnatrz" class="syn-back">◂ Powrót do kwatery</a>

<div class="blok" style="--akcent: var(--neon-gold)">
    <div class="blok-tytul">
        <span>🏆</span> Igrzyska Rodzin
        <span class="note">Turniej końca ery</span>
    </div>

    <div class="igrzyska-hero">
        <div class="igrzyska-status">⧗ ERA I · TRWA</div>
        <div class="igrzyska-tytul">Igrzyska Jeszcze Nie Rozpoczęły</div>
        <div class="igrzyska-opis">
            Era mechaniczna trwa. Aby rozpocząć igrzyska, jeden z <strong style="color:var(--neon-cyan)">Inżynierów</strong> musi wykuć <em>Legendarną Broń</em> — a do tego potrzebuje <strong style="color:var(--neon-gold)">42 artefaktów ze wszystkich krajów świata</strong>. <br><br>
            <strong style="color:var(--neon-ember)">Szabrownicy</strong> szukają ich na złomowiskach, <strong style="color:var(--neon-red-hot)">Bojowi</strong> wydzierają je wrogom w dokach.
        </div>

        <div class="postep-artefaktow">
            <div class="lab">◆ Artefakty zebrane przez rodzinę</div>
            <div class="postep-bar">
                <div class="postep-wart" style="width: 0%;"></div>
            </div>
            <div class="postep-liczby">0 / 42</div>
        </div>

        <div style="margin-top:28px; padding:16px; background:rgba(0,0,0,0.5); border:1px solid var(--border-soft); border-radius:2px; font-family:'JetBrains Mono',monospace; font-size:.78em; color:var(--txt-mute); letter-spacing:1px; line-height:1.7; text-align:left; max-width:700px; margin-left:auto; margin-right:auto;">
            <strong style="color:var(--neon-gold);">// FAZA 2 — JAK TO BĘDZIE DZIAŁAĆ</strong><br>
            <span style="color:var(--txt-dim);">01.</span> Era mechaniczna trwa miesiącami<br>
            <span style="color:var(--txt-dim);">02.</span> Syndykat zbiera artefakty ze wszystkich krajów<br>
            <span style="color:var(--txt-dim);">03.</span> Inżynier kuje Legendarną Broń gdy skompletuje zestaw<br>
            <span style="color:var(--txt-dim);">04.</span> Powiadomienie leci do MG i właściciela gry<br>
            <span style="color:var(--txt-dim);">05.</span> Rozpoczynają się Igrzyska — walki syndykat vs syndykat<br>
            <span style="color:var(--txt-dim);">06.</span> Zwycięzca ery otrzymuje tytuł i permanentne bonusy
        </div>
    </div>
</div>

<?php
// ═══════════════════════════════════════════════════════════════════
// WIDOK ZARZĄDZANIE
// ═══════════════════════════════════════════════════════════════════
elseif ($widok == 'zarzadzanie' && $w_syndykacie && ($moja_rola == 'Don' || syn_moze($moja_rola, 'rekrutacja') || syn_moze($moja_rola, 'awansy') || syn_moze($moja_rola, 'wyrzucanie'))):
    $syn_id = $syndykat['id'];
    $akcent = $syndykat['kolor_akcent'] ?? '#ff1744';
?>

<a href="game.php?page=syndykaty&widok=wewnatrz" class="syn-back">◂ Powrót do kwatery</a>

<!-- ── PODANIA ──────────────────────────────────────────────── -->
<?php if (syn_moze($moja_rola, 'rekrutacja')): ?>
<div class="blok" style="--akcent: var(--neon-green)">
    <div class="blok-tytul">
        <span>📋</span> Oczekujące Podania
        <?php
        $liczba_podan = $polaczenie->query("SELECT COUNT(*) as c FROM syndykat_podania WHERE syndykat_id = $syn_id")->fetch_assoc();
        ?>
        <span class="note"><?php echo (int)$liczba_podan['c']; ?> kandydatów w kolejce</span>
    </div>

    <?php
    $podania = $polaczenie->query("
        SELECT p.gracz_id, g.login, g.poziom, g.klasa 
        FROM syndykat_podania p 
        JOIN gracze g ON p.gracz_id = g.id 
        WHERE p.syndykat_id = $syn_id
    ");

    if ($podania && $podania->num_rows > 0): ?>
    <table class="tabela-syn">
        <tr><th>Kandydat</th><th>Poziom</th><th>Klasa</th><th style="text-align:right;">Decyzja</th></tr>
        <?php while($p = $podania->fetch_assoc()): ?>
        <tr>
            <td><a href="game.php?page=profil&id=<?php echo $p['gracz_id']; ?>" style="color:var(--neon-cyan); font-weight:600; text-decoration:none;"><?php echo htmlspecialchars($p['login']); ?></a></td>
            <td>Lvl <?php echo (int)$p['poziom']; ?></td>
            <td><?php echo htmlspecialchars($p['klasa']); ?></td>
            <td style="text-align:right;">
                <form method="POST" style="display:inline; margin:0;">
                    <input type="hidden" name="id_aplikanta" value="<?php echo (int)$p['gracz_id']; ?>">
                    <button type="submit" name="akceptuj_podanie" class="btn-akcja green mini">✓ Przyjmij</button>
                    <button type="submit" name="odrzuc_podanie" class="btn-akcja mini">✗ Odrzuć</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
    <div style="text-align:center; padding:24px; color:var(--txt-mute); font-style:italic;">
        Brak nowych kandydatów. Ludzie się nie zgłaszają sami — trzeba wyjść na ulicę.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── ZARZĄDZANIE RANGAMI ─────────────────────────────────── -->
<?php if (syn_moze($moja_rola, 'awansy') || syn_moze($moja_rola, 'wyrzucanie')): ?>
<div class="blok" style="--akcent: var(--neon-red-hot)">
    <div class="blok-tytul">
        <span>⚖️</span> Zarządzanie Personelem
        <span class="note">Awanse, degradacje, wyrzucanie</span>
    </div>

    <table class="tabela-syn">
        <tr>
            <th>Gracz</th>
            <th>Ranga</th>
            <?php if (syn_moze($moja_rola, 'awansy')): ?><th>Nowa ranga</th><?php endif; ?>
            <?php if (syn_moze($moja_rola, 'wyrzucanie')): ?><th style="text-align:right;">Akcja</th><?php endif; ?>
        </tr>
        <?php
        $czlonkowie = $polaczenie->query("
            SELECT id, login, syndykat_rola 
            FROM gracze 
            WHERE syndykat_id = $syn_id 
            ORDER BY FIELD(syndykat_rola, 'Don','Consigliere','Capo','Kwatermistrz','Rekruter','Żołnierz','Picciotto'), login
        ");
        while($cz = $czlonkowie->fetch_assoc()):
            $r_info = $ROLE[$cz['syndykat_rola']] ?? ['ikona'=>'?', 'kolor'=>'var(--txt-mute)'];
            $ranga_celu = syn_ranga($cz['syndykat_rola']);
            $moze_edytowac = ($cz['id'] != $id_gracza && $ranga_celu < $moja_ranga);
        ?>
        <tr>
            <td>
                <span style="color: <?php echo $r_info['kolor']; ?>; font-size:1.1em;"><?php echo $r_info['ikona']; ?></span>
                <a href="game.php?page=profil&id=<?php echo $cz['id']; ?>" style="color:var(--neon-cyan); font-weight:600; text-decoration:none; margin-left:6px;"><?php echo htmlspecialchars($cz['login']); ?></a>
                <?php if ($cz['id'] == $id_gracza): ?>
                    <span style="color:var(--txt-mute); font-size:.75em; margin-left:6px;">(ty)</span>
                <?php endif; ?>
            </td>
            <td style="color: <?php echo $r_info['kolor']; ?>; text-shadow:0 0 4px <?php echo $r_info['kolor']; ?>; font-family:'Oswald',sans-serif; letter-spacing:1px; text-transform:uppercase; font-size:.92em;">
                <?php echo htmlspecialchars($cz['syndykat_rola']); ?>
            </td>

            <?php if (syn_moze($moja_rola, 'awansy')): ?>
            <td>
                <?php if ($moze_edytowac): ?>
                <form method="POST" style="display:flex; gap:6px; margin:0;">
                    <input type="hidden" name="id_czlonka" value="<?php echo (int)$cz['id']; ?>">
                    <select name="nowa_rola" class="syn-input" style="margin:0; flex:1;">
                        <?php foreach ($ROLE as $nazwa_r => $dane_r):
                            // Don niedostępny, rola musi być niższa niż moja, i niż aktualna (degradacja też dopuszczona niżej)
                            if ($nazwa_r == 'Don') continue;
                            if ($dane_r['ranga'] >= $moja_ranga) continue;
                            // Consigliere tylko Don może przyznać
                            if ($nazwa_r == 'Consigliere' && $moja_rola != 'Don') continue;
                            $zaznacz = ($nazwa_r == $cz['syndykat_rola']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $nazwa_r; ?>" <?php echo $zaznacz; ?>>
                            <?php echo $dane_r['ikona']; ?> <?php echo $nazwa_r; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="zmien_role" class="btn-akcja mini cyan">Zmień</button>
                </form>
                <?php else: ?>
                <span style="color:var(--txt-mute); font-size:.8em;">—</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>

            <?php if (syn_moze($moja_rola, 'wyrzucanie')): ?>
            <td style="text-align:right;">
                <?php if ($moze_edytowac): ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="id_czlonka" value="<?php echo (int)$cz['id']; ?>">
                    <button type="submit" name="wyrzuc_czlonka" class="btn-akcja mini"
                        onclick="return confirm('Wyrzucić <?php echo htmlspecialchars($cz['login'], ENT_QUOTES); ?> z rodziny?');">
                        ✕ Wyrzuć
                    </button>
                </form>
                <?php endif; ?>
            </td>
            <?php endif; ?>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php endif; ?>

<!-- ── LEGENDA RÓL ─────────────────────────────────────────── -->
<div class="blok" style="--akcent: var(--neon-cyan)">
    <div class="blok-tytul">
        <span>ℹ️</span> Legenda Rang
        <span class="note">Kto za co odpowiada</span>
    </div>

    <div class="legenda-rol-grid">
        <?php foreach ($ROLE as $nazwa_r => $dane_r): ?>
        <div class="legenda-rol" style="--rola-kolor: <?php echo $dane_r['kolor']; ?>">
            <div class="legenda-rol-ikona"><?php echo $dane_r['ikona']; ?></div>
            <div>
                <div class="legenda-rol-head">
                    <div class="legenda-rol-nazwa"><?php echo $nazwa_r; ?></div>
                    <?php if ($dane_r['limit'] < 999): ?>
                    <span style="font-family:'JetBrains Mono',monospace; font-size:.65em; color:var(--txt-mute); letter-spacing:1px;">
                        [max <?php echo $dane_r['limit']; ?>]
                    </span>
                    <?php endif; ?>
                </div>
                <div class="legenda-rol-opis"><?php echo $dane_r['opis']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── EDYCJA INFO SYNDYKATU (tylko Don) ────────────────────── -->
<?php if (syn_moze($moja_rola, 'edycja_info')): ?>
<div class="blok" style="--akcent: var(--neon-gold)">
    <div class="blok-tytul">
        <span>✎</span> Wizytówka Rodziny
        <span class="note">Tylko Don może edytować</span>
    </div>

    <form method="POST">
        <label class="syn-label">Motto</label>
        <input type="text" name="motto" class="syn-input" maxlength="200" value="<?php echo htmlspecialchars($syndykat['motto'] ?? ''); ?>" placeholder="np. Honor. Krew. Milczenie.">

        <label class="syn-label">Opis Rodziny</label>
        <textarea name="opis" class="syn-input" maxlength="1000" rows="4" placeholder="Kim jesteście? Jak się poznaliście? Co was łączy?"><?php echo htmlspecialchars($syndykat['opis'] ?? ''); ?></textarea>

        <label class="syn-label">Herb</label>
        <div class="herb-picker" id="herb-picker-edytuj">
            <?php 
            $obecny_herb = $syndykat['herb_emoji'] ?? '⚜';
            foreach (['⚜','💀','🗡','🩸','👁','♠','♣','⚔','🕊','🌹','🐺','🦅','🐍','🦂','⚓','🎭','🃏','🖤'] as $emoji): 
                $klasa = ($emoji == $obecny_herb) ? 'wybrany' : '';
            ?>
            <div class="herb-opcja <?php echo $klasa; ?>" data-emoji="<?php echo $emoji; ?>" onclick="wybierzHerb(this, 'edytuj')"><?php echo $emoji; ?></div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="herb" id="herb-input-edytuj" value="<?php echo htmlspecialchars($obecny_herb); ?>">

        <label class="syn-label">Kolor Akcentu</label>
        <div class="kolor-picker" id="kolor-picker-edytuj">
            <?php 
            $obecny_kolor = $syndykat['kolor_akcent'] ?? '#ff1744';
            foreach (['#ff1744','#ffd700','#4ad6ff','#ff7a3d','#5aff9a','#c896ff','#ff3d5e','#6b8eff','#ff8c00','#ec4899'] as $kolor):
                $klasa = ($kolor == $obecny_kolor) ? 'wybrany' : '';
            ?>
            <div class="kolor-opcja <?php echo $klasa; ?>" data-kolor="<?php echo $kolor; ?>" style="background:<?php echo $kolor; ?>; box-shadow:0 0 8px <?php echo $kolor; ?>;" onclick="wybierzKolor(this, 'edytuj')"></div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="kolor" id="kolor-input-edytuj" value="<?php echo htmlspecialchars($obecny_kolor); ?>">

        <button type="submit" name="edytuj_info" class="btn-akcja gold full">
            ▸ Zapisz zmiany
        </button>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
// ── HERB PICKER ──────────────────────────────────────────
function wybierzHerb(element, kontekst) {
    const picker = document.getElementById('herb-picker-' + kontekst);
    picker.querySelectorAll('.herb-opcja').forEach(o => o.classList.remove('wybrany'));
    element.classList.add('wybrany');
    document.getElementById('herb-input-' + kontekst).value = element.dataset.emoji;
}

// ── KOLOR PICKER ─────────────────────────────────────────
function wybierzKolor(element, kontekst) {
    const picker = document.getElementById('kolor-picker-' + kontekst);
    picker.querySelectorAll('.kolor-opcja').forEach(o => o.classList.remove('wybrany'));
    element.classList.add('wybrany');
    document.getElementById('kolor-input-' + kontekst).value = element.dataset.kolor;
}

// ── Ustaw zaznaczenie domyślne ───────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Zakładanie
    const herbZaloz = document.getElementById('herb-picker-zaloz');
    if (herbZaloz) {
        const pierwszy = herbZaloz.querySelector('.herb-opcja');
        if (pierwszy) pierwszy.classList.add('wybrany');
    }
    const kolorZaloz = document.getElementById('kolor-picker-zaloz');
    if (kolorZaloz) {
        const pierwszy = kolorZaloz.querySelector('.kolor-opcja');
        if (pierwszy) pierwszy.classList.add('wybrany');
    }
});
</script>