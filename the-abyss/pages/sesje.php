<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

// ── KATALOG GATUNKÓW ───────────────────────────────────────────
$GATUNKI = [
    'Obyczajowa'   => ['ikona' => '💬', 'opis' => 'Życie codzienne, relacje, rozmowy. Mało walki, dużo postaci.'],
    'Kryminalna'   => ['ikona' => '🔪', 'opis' => 'Porwania, napady, mafijne rozgrywki. Twarde decyzje.'],
    'Śledztwo'     => ['ikona' => '🔍', 'opis' => 'Poszukiwanie śladów, przesłuchania, łamanie tropów.'],
    'Akcja'        => ['ikona' => '⚔️', 'opis' => 'Walka, pościg, strzelanina. Tu PT rośnie szybko.'],
    'Horror'       => ['ikona' => '👻', 'opis' => 'Niepokój, strach, rzeczy które nie powinny istnieć.'],
    'Romans'       => ['ikona' => '💍', 'opis' => 'Romanse, śluby, trójkąty, dramaty sercowe.'],
    'Więzienie'    => ['ikona' => '🔒', 'opis' => 'Sesje w zamknięciu — izolacja, układy, przetrwanie.'],
    'Polityczna'   => ['ikona' => '🏛️', 'opis' => 'Intrygi, korporacje, układy władzy. Dyplomacja na noże.'],
    'Inna'         => ['ikona' => '❓', 'opis' => 'Mieszanka gatunków albo coś eksperymentalnego.'],
];

// ── KATALOG KATEGORII ──────────────────────────────────────────
$KATEGORIE = [
    'Główna Fabuła' => [
        'ikona' => '📖',
        'kolor' => 'var(--neon-gold)',
        'opis' => 'Oficjalna kampania świata. Prowadzą wyłącznie Mistrzowie Gry. Sesje ciągną się tygodniami.',
        'tylko_mg' => true,
    ],
    'Publiczna' => [
        'ikona' => '🎭',
        'kolor' => 'var(--neon-cyan)',
        'opis' => 'Otwarta dla wszystkich. MG może nadzorować, ostrzegać i interweniować przy super-powerach.',
        'tylko_mg' => false,
    ],
    'Prywatna' => [
        'ikona' => '🔐',
        'kolor' => 'var(--neon-ember)',
        'opis' => 'Tylko dla zaproszonych. Brak nadzoru MG — gracze sami dbają o fair play.',
        'tylko_mg' => false,
    ],
    'Rekrutacyjna' => [
        'ikona' => '🏴',
        'kolor' => 'var(--neon-red)',
        'opis' => 'Rekrutacja do syndykatu, firmy lub frakcji. Efekt wpływa na przynależność postaci.',
        'tylko_mg' => false,
    ],
];

// Czy aktualny gracz jest Mistrzem Gry (globalna flaga)?
$mg_check = $polaczenie->query("SELECT jest_mistrzem_gry FROM gracze WHERE id=$id_gracza")->fetch_assoc();
$czy_globalny_mg = !empty($mg_check['jest_mistrzem_gry']);

$komunikat = "";
$kat = isset($_GET['kat']) ? $_GET['kat'] : 'publiczna';

// ── TWORZENIE SESJI ────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['stworz_sesje'])) {
    $tytul       = $polaczenie->real_escape_string(trim($_POST['tytul']));
    $opis        = $polaczenie->real_escape_string(trim($_POST['opis']));
    $kategoria   = $polaczenie->real_escape_string($_POST['kategoria']);
    $gatunek     = $polaczenie->real_escape_string($_POST['gatunek']);
    $tagi        = $polaczenie->real_escape_string(trim($_POST['tagi']));
    $ostrzezenia = $polaczenie->real_escape_string(trim($_POST['ostrzezenia']));
    $trudnosc    = $polaczenie->real_escape_string($_POST['trudnosc']);

    // Blokada: Główna Fabuła tylko dla MG
    if ($kategoria === 'Główna Fabuła' && !$czy_globalny_mg) {
        $komunikat = "<div class='blad'>⚠ Sesje w kategorii 'Główna Fabuła' mogą zakładać tylko Mistrzowie Gry.</div>";
    } elseif (!isset($KATEGORIE[$kategoria])) {
        $komunikat = "<div class='blad'>⚠ Nieprawidłowa kategoria.</div>";
    } elseif (!isset($GATUNKI[$gatunek])) {
        $komunikat = "<div class='blad'>⚠ Nieprawidłowy gatunek.</div>";
    } elseif (mb_strlen($tytul) < 5 || mb_strlen($opis) < 20) {
        $komunikat = "<div class='blad'>⚠ Tytuł min. 5 znaków, wstęp fabularny min. 20.</div>";
    } else {
        $sql = "INSERT INTO sesje_rpg (tytul, opis, mg_id, kategoria, gatunek, tagi, ostrzezenia, poziom_trudnosci)
                VALUES ('$tytul','$opis',$id_gracza,'$kategoria','$gatunek','$tagi','$ostrzezenia','$trudnosc')";
        if ($polaczenie->query($sql)) {
            $nowa_id = $polaczenie->insert_id;
            $polaczenie->query("INSERT INTO sesje_uczestnicy (sesja_id, gracz_id, rola, status_akceptacji)
                                VALUES ($nowa_id, $id_gracza, 'Mistrz Gry', 'Zaakceptowany')");
            echo "<script>window.location.href='game.php?page=pokoj_sesji&id=$nowa_id';</script>"; exit;
        } else {
            $komunikat = "<div class='blad'>⚠ Błąd zapisu: " . htmlspecialchars($polaczenie->error) . "</div>";
        }
    }
}

// ── LOGIKA LIST ────────────────────────────────────────────────
$warunek = "s.kategoria = 'Publiczna' AND s.status != 'Zakończona'";
$tytul_listy = "Publiczne";
$opis_listy = "Otwarte sesje dla wszystkich obywateli. Wejdź w wir wydarzeń.";
$ikona_listy = '🎭';

if ($kat == 'rozgrywane') {
    $warunek = "s.id IN (SELECT sesja_id FROM sesje_uczestnicy WHERE gracz_id=$id_gracza AND rola='Gracz' AND status_akceptacji='Zaakceptowany') AND s.status != 'Zakończona'";
    $tytul_listy = "Rozgrywane przeze mnie";
    $opis_listy  = "Sesje, w których aktualnie bierzesz udział.";
    $ikona_listy = '🎬';
} elseif ($kat == 'prowadzone') {
    $warunek = "(s.mg_id=$id_gracza OR s.id IN (SELECT sesja_id FROM sesje_uczestnicy WHERE gracz_id=$id_gracza AND rola='Mistrz Gry'))";
    $tytul_listy = "Prowadzone";
    $opis_listy  = "Opowieści, w których jesteś Mistrzem Gry.";
    $ikona_listy = '🎲';
} elseif ($kat == 'archiwum') {
    $warunek = "s.status = 'Zakończona' AND (s.mg_id=$id_gracza OR s.id IN (SELECT sesja_id FROM sesje_uczestnicy WHERE gracz_id=$id_gracza))";
    $tytul_listy = "Archiwum";
    $opis_listy  = "Zakończone sesje z Twoim udziałem. Możesz wrócić do podsumowań i notatek.";
    $ikona_listy = '📼';
} elseif ($kat == 'glowna_fabula') {
    $warunek = "s.kategoria = 'Główna Fabuła' AND s.status != 'Zakończona'";
    $tytul_listy = "Główna Fabuła";
    $opis_listy  = "Oficjalna kampania The Abyss. Wydarzenia kanoniczne prowadzone przez Mistrzów Gry.";
    $ikona_listy = '📖';
} elseif ($kat == 'prywatna') {
    $warunek = "s.kategoria = 'Prywatna' AND s.id IN (SELECT sesja_id FROM sesje_uczestnicy WHERE gracz_id=$id_gracza) AND s.status != 'Zakończona'";
    $tytul_listy = "Prywatne";
    $opis_listy  = "Sesje prywatne, do których zostałeś zaproszony.";
    $ikona_listy = '🔐';
} elseif ($kat == 'rekrutacje') {
    $warunek = "s.kategoria = 'Rekrutacyjna' AND s.status != 'Zakończona'";
    $tytul_listy = "Rekrutacje";
    $opis_listy  = "Sesje wejścia do syndykatów, firm i frakcji.";
    $ikona_listy = '🏴';
}

// ── POBRANIE LISTY ──────────────────────────────────────────────
$sql_lista = "
    SELECT s.*, g.login AS nazwa_mg, g.avatar,
           (SELECT COUNT(*) FROM sesje_uczestnicy WHERE sesja_id=s.id AND rola='Gracz' AND status_akceptacji='Zaakceptowany') AS liczba_graczy
    FROM sesje_rpg s
    JOIN gracze g ON s.mg_id=g.id
    WHERE $warunek
    ORDER BY s.ostatnia_aktywnosc DESC
    LIMIT 40
";
$lista_sesji = $polaczenie->query($sql_lista);
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   SESJE.PHP — CYBERPUNK NYC
═══════════════════════════════════════════════════════════════ */

.rp-head{
    text-align:center;margin-bottom:24px;padding-bottom:18px;
    border-bottom:1px solid var(--border-soft);position:relative;
}
.rp-head::after{
    content:'';position:absolute;bottom:-1px;left:50%;transform:translateX(-50%);
    width:140px;height:1px;background:var(--neon-red);box-shadow:0 0 8px var(--neon-red);
}
.rp-head .eyebrow{
    font-family:'JetBrains Mono',monospace;font-size:.75em;
    color:var(--neon-red);letter-spacing:4px;text-transform:uppercase;
    margin-bottom:6px;text-shadow:0 0 6px rgba(255,23,68,0.5);
}
.rp-head h1{
    font-family:'Oswald',sans-serif;font-weight:500;font-size:2.6em;
    text-transform:uppercase;letter-spacing:3px;color:#fff;line-height:1;
    text-shadow:0 0 20px rgba(255,23,68,0.3);
}
.rp-head .lead{margin-top:10px;color:var(--txt-dim);font-size:1.02em;max-width:640px;line-height:1.5;margin-left:auto;margin-right:auto}

/* LAYOUT */
.rp-wrap{display:flex;gap:22px;align-items:flex-start}
.rp-menu{
    width:240px;min-width:240px;
    background:rgba(10,6,12,0.55);backdrop-filter:blur(8px);
    border:1px solid var(--border-soft);border-radius:2px;padding:18px 14px;
    position:sticky;top:10px;
}
.rp-menu::before{
    content:'';position:absolute;top:0;left:0;width:32px;height:1px;
    background:var(--neon-red);box-shadow:0 0 6px var(--neon-red);
}
.rp-tresc{flex:1;min-width:0}

.menu-naglowek{
    font-family:'Oswald',sans-serif;color:#fff;font-size:.95em;font-weight:500;
    text-align:center;padding-bottom:12px;margin-bottom:12px;
    border-bottom:1px solid var(--border-soft);
    text-transform:uppercase;letter-spacing:2.5px;
}
.menu-sekcja{
    color:var(--neon-red);font-size:.68em;text-transform:uppercase;
    font-family:'Oswald',sans-serif;letter-spacing:3px;font-weight:500;
    margin:16px 0 6px;padding:4px 8px;
    border-left:2px solid var(--neon-red);
    background:linear-gradient(90deg, rgba(255,23,68,0.12), transparent);
    text-shadow:0 0 8px rgba(255,23,68,0.5);
}
.menu-sekcja:first-child{margin-top:0}
.menu-link{
    display:flex;align-items:center;gap:8px;
    color:var(--txt-dim);text-decoration:none;
    padding:7px 10px;font-size:.92em;font-weight:500;
    border-radius:1px;margin-bottom:1px;
    border-left:2px solid transparent;transition:all .2s;
    font-family:'Rajdhani',sans-serif;letter-spacing:.5px;position:relative;
}
.menu-link:hover{color:#fff;background:rgba(255,23,68,0.08);border-left-color:var(--neon-red-hot);padding-left:14px}
.menu-link.aktywny{color:#fff;background:linear-gradient(90deg, rgba(255,23,68,0.22), rgba(255,23,68,0.04));border-left-color:var(--neon-red);text-shadow:0 0 8px rgba(255,23,68,0.7)}
.menu-link.aktywny::after{content:'▸';position:absolute;right:10px;color:var(--neon-red)}

.menu-link.mg-only{color:var(--neon-gold);opacity:.45;cursor:help}
.menu-link.mg-only:hover{background:transparent;border-left-color:transparent;padding-left:10px;opacity:.6}
.menu-link .lock{font-size:.75em;margin-left:auto;opacity:.7}

.menu-link-specjalny{
    display:flex;align-items:center;justify-content:center;gap:8px;
    margin-top:14px;padding:10px;
    background:linear-gradient(135deg, rgba(255,23,68,0.15), rgba(179,0,27,0.25));
    border:1px solid var(--neon-red);border-radius:2px;
    color:#fff;text-decoration:none;
    font-family:'Oswald',sans-serif;font-weight:600;letter-spacing:2px;text-transform:uppercase;font-size:.9em;
    text-shadow:0 0 8px var(--neon-red);
    box-shadow:0 0 14px rgba(255,23,68,0.35),inset 0 0 20px rgba(255,23,68,0.1);
    transition:all .3s;
}
.menu-link-specjalny:hover{box-shadow:0 0 28px rgba(255,23,68,0.6),inset 0 0 28px rgba(255,23,68,0.2)}
.menu-link-specjalny.aktywny{background:rgba(255,23,68,0.4);box-shadow:0 0 30px rgba(255,23,68,0.7)}

/* PANEL TREŚCI */
.rp-panel{
    background:rgba(10,6,12,0.6);backdrop-filter:blur(8px);
    border:1px solid var(--border-soft);border-radius:2px;
    padding:24px;position:relative;
    box-shadow:0 8px 32px rgba(0,0,0,0.5);
}
.rp-panel::before{
    content:'';position:absolute;top:0;left:0;width:32px;height:1px;
    background:var(--neon-red);box-shadow:0 0 6px var(--neon-red);
}
.panel-tytul{
    font-family:'Oswald',sans-serif;color:#fff;font-size:1.5em;font-weight:500;
    text-transform:uppercase;letter-spacing:2.5px;margin:0 0 6px;
    display:flex;align-items:center;gap:12px;
}
.panel-tytul .ikona{font-size:1.2em}
.panel-opis{
    color:var(--txt-dim);font-size:.92em;line-height:1.5;
    padding-bottom:14px;margin-bottom:18px;border-bottom:1px dashed var(--border-soft);
}

/* KARTA SESJI */
.sesja-karta{
    background:rgba(18,10,18,0.5);border:1px solid var(--border-soft);border-radius:2px;
    padding:18px;margin-bottom:14px;
    display:flex;gap:16px;transition:all .25s;position:relative;overflow:hidden;
}
.sesja-karta::before{
    content:'';position:absolute;top:0;left:0;width:3px;height:100%;
    background:var(--akcent,var(--neon-red));box-shadow:0 0 8px var(--akcent,var(--neon-red));
}
.sesja-karta:hover{transform:translateX(3px);border-color:var(--border-mid);box-shadow:0 6px 24px rgba(0,0,0,0.5)}
.sesja-avatar{
    width:70px;height:70px;flex-shrink:0;
    background-size:cover!important;background-position:center!important;
    border:1px solid var(--border-soft);border-radius:2px;
    box-shadow:0 0 12px rgba(255,23,68,0.15);
}
.sesja-info{flex:1;min-width:0}
.sesja-naglowek{
    display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:10px;flex-wrap:wrap;
}
.sesja-tytul{
    font-family:'Oswald',sans-serif;color:#fff;font-size:1.25em;font-weight:500;
    text-transform:uppercase;letter-spacing:1.5px;margin:0;
}
.sesja-tagi{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
.tag-mini{
    display:inline-flex;align-items:center;gap:4px;
    background:rgba(0,0,0,0.5);border:1px solid rgba(255,23,68,0.15);
    color:var(--txt-dim);font-size:.72em;padding:3px 8px;border-radius:1px;
    font-family:'JetBrains Mono',monospace;letter-spacing:1px;text-transform:uppercase;
}
.tag-mini.kat{border-color:var(--akcent,var(--neon-red));color:var(--akcent,var(--neon-red));text-shadow:0 0 4px var(--akcent,var(--neon-red))}
.tag-mini.gat{border-color:rgba(74,214,255,0.3);color:var(--neon-cyan);text-shadow:0 0 4px rgba(74,214,255,0.4)}
.tag-mini.trudnosc{border-color:rgba(255,215,0,0.3);color:var(--neon-gold)}
.tag-mini.status-trwa{border-color:rgba(90,255,154,0.3);color:var(--neon-green)}
.tag-mini.status-rekrut{border-color:rgba(255,122,61,0.3);color:var(--neon-ember)}
.tag-mini.status-zakonczona{border-color:rgba(138,129,142,0.3);color:var(--txt-mute)}
.tag-mini.graczy{color:var(--neon-ember)}
.sesja-mg{
    color:var(--txt-mute);font-size:.8em;font-family:'JetBrains Mono',monospace;
    margin-bottom:8px;letter-spacing:.5px;
}
.sesja-mg b{color:#fff;font-weight:500}
.sesja-wstep{
    color:var(--txt-dim);font-size:.9em;line-height:1.5;font-style:italic;
    padding-left:10px;border-left:2px solid rgba(255,23,68,0.15);
}
.sesja-ostrzezenie{
    margin-top:10px;padding:6px 10px;
    background:rgba(255,23,68,0.08);border-left:2px solid var(--neon-red);
    font-size:.8em;color:var(--neon-red-hot);font-family:'JetBrains Mono',monospace;letter-spacing:.5px;
}
.btn-wejdz{
    background:rgba(74,214,255,0.08);border:1px solid var(--neon-cyan);color:var(--neon-cyan);
    padding:7px 14px;font-family:'Oswald',sans-serif;font-weight:500;
    font-size:.82em;text-transform:uppercase;letter-spacing:1.5px;border-radius:1px;
    cursor:pointer;text-decoration:none;transition:.3s;display:inline-block;white-space:nowrap;
}
.btn-wejdz:hover{background:var(--neon-cyan);color:#000;box-shadow:0 0 18px rgba(74,214,255,0.5)}

/* KREATOR */
.kreator-form{margin-top:4px}
.form-wiersz{margin-bottom:18px}
.form-wiersz label{
    display:block;font-family:'Oswald',sans-serif;color:var(--txt-main);
    font-size:.82em;text-transform:uppercase;letter-spacing:2px;
    margin-bottom:8px;font-weight:500;
}
.form-wiersz label .req{color:var(--neon-red);margin-left:3px}
.form-wiersz .hint{
    color:var(--txt-mute);font-size:.78em;font-weight:400;
    text-transform:none;letter-spacing:0;margin-top:3px;
    font-family:'Open Sans',sans-serif;font-style:italic;
}
.form-input,.form-select,.form-textarea{
    width:100%;padding:11px 14px;
    background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);color:#fff;
    border-radius:2px;box-sizing:border-box;
    font-family:'Open Sans',sans-serif;font-size:.95em;transition:.2s;
}
.form-input:focus,.form-select:focus,.form-textarea:focus{
    outline:none;border-color:var(--neon-red);box-shadow:0 0 12px rgba(255,23,68,0.25);
}
.form-textarea{min-height:130px;resize:vertical;line-height:1.5}
.form-dwa{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:700px){.form-dwa{grid-template-columns:1fr}}

.gatunek-grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:4px;
}
.gatunek-opt{
    display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:12px 8px;background:rgba(0,0,0,0.5);border:1px solid var(--border-soft);
    border-radius:2px;cursor:pointer;transition:.2s;text-align:center;
}
.gatunek-opt:hover{border-color:var(--neon-cyan);background:rgba(74,214,255,0.06)}
.gatunek-opt input{display:none}
.gatunek-opt .ikona{font-size:1.6em;line-height:1}
.gatunek-opt .nazwa{
    font-family:'Oswald',sans-serif;font-size:.78em;letter-spacing:1.2px;
    text-transform:uppercase;color:var(--txt-dim);
}
.gatunek-opt.zaznaczony{
    border-color:var(--neon-cyan);background:rgba(74,214,255,0.12);
    box-shadow:0 0 14px rgba(74,214,255,0.25);
}
.gatunek-opt.zaznaczony .nazwa{color:#fff;text-shadow:0 0 6px var(--neon-cyan)}

.kat-info{
    background:rgba(0,0,0,0.45);border:1px solid var(--border-soft);border-radius:2px;
    padding:12px 16px;margin-top:10px;
    font-family:'Open Sans',sans-serif;font-size:.88em;color:var(--txt-dim);line-height:1.55;
    border-left:3px solid var(--akcent-kat,var(--neon-cyan));
}
.kat-info strong{color:#fff;font-family:'Oswald',sans-serif;letter-spacing:1px;text-transform:uppercase}

.btn-akcja{
    background:rgba(255,23,68,0.1);color:var(--neon-red-hot);
    border:1px solid var(--neon-red);padding:13px 30px;
    font-family:'Oswald',sans-serif;font-size:1em;font-weight:600;
    cursor:pointer;text-transform:uppercase;border-radius:2px;
    transition:.3s;letter-spacing:2.5px;
}
.btn-akcja:hover{background:var(--neon-red);color:#fff;box-shadow:0 0 20px rgba(255,23,68,0.7);text-shadow:0 0 8px #fff}

.sukces{
    background:rgba(90,255,154,0.08);border:1px solid var(--neon-green);
    color:var(--neon-green);padding:13px 16px;margin-bottom:18px;border-radius:2px;
    font-family:'Oswald',sans-serif;letter-spacing:1.5px;
}
.blad{
    background:rgba(255,23,68,0.1);border:1px solid var(--border-mid);
    color:var(--neon-red-hot);padding:13px 16px;margin-bottom:18px;border-radius:2px;
    font-family:'Oswald',sans-serif;letter-spacing:1.5px;
}
.brak-sesji{
    padding:60px 20px;text-align:center;
    color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.9em;
    border:1px dashed var(--border-soft);border-radius:2px;background:rgba(0,0,0,0.3);
}
.brak-sesji a{color:var(--neon-cyan);text-decoration:none;font-weight:600;margin-top:10px;display:inline-block}
.brak-sesji a:hover{text-shadow:0 0 8px var(--neon-cyan)}
</style>

<div class="rp-head">
    <div class="eyebrow">◆ CENTRUM OPOWIEŚCI</div>
    <h1>Sesje Fabularne</h1>
    <p class="lead">Miasto tysiąca historii. Dołącz do trwających śledztw, rekrutacji do syndykatów lub napisz własną legendę.</p>
</div>

<?php echo $komunikat; ?>

<div class="rp-wrap">

    <!-- ══ LEWE MENU ══════════════════════════════════════════ -->
    <div class="rp-menu">
        <div class="menu-naglowek">Nawigacja</div>

        <div class="menu-sekcja">Moje</div>
        <a href="game.php?page=sesje&kat=rozgrywane" class="menu-link <?php if($kat=='rozgrywane') echo 'aktywny'; ?>">🎬 Rozgrywane</a>
        <a href="game.php?page=sesje&kat=prowadzone" class="menu-link <?php if($kat=='prowadzone') echo 'aktywny'; ?>">🎲 Prowadzone</a>
        <a href="game.php?page=sesje&kat=archiwum" class="menu-link <?php if($kat=='archiwum') echo 'aktywny'; ?>">📼 Archiwum</a>

        <div class="menu-sekcja">Centrum</div>
        <a href="game.php?page=sesje&kat=glowna_fabula" class="menu-link <?php if($kat=='glowna_fabula') echo 'aktywny'; ?>" style="color:<?php echo $kat=='glowna_fabula'?'':'var(--neon-gold)'; ?>">📖 Główna Fabuła</a>
        <a href="game.php?page=sesje&kat=publiczna" class="menu-link <?php if($kat=='publiczna') echo 'aktywny'; ?>">🎭 Publiczne</a>
        <a href="game.php?page=sesje&kat=prywatna" class="menu-link <?php if($kat=='prywatna') echo 'aktywny'; ?>">🔐 Prywatne</a>
        <a href="game.php?page=sesje&kat=rekrutacje" class="menu-link <?php if($kat=='rekrutacje') echo 'aktywny'; ?>">🏴 Rekrutacje</a>

        <a href="game.php?page=sesje&kat=kreator" class="menu-link-specjalny <?php if($kat=='kreator') echo 'aktywny'; ?>">+ Stwórz Sesję</a>

        <?php if ($czy_globalny_mg): ?>
        <div style="margin-top:16px;padding:8px 10px;background:rgba(255,215,0,0.08);border:1px solid rgba(255,215,0,0.3);border-radius:2px;text-align:center;font-family:'JetBrains Mono',monospace;font-size:.7em;color:var(--neon-gold);letter-spacing:2px;text-shadow:0 0 6px rgba(255,215,0,0.4)">
            ◈ STATUS: MISTRZ GRY
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ TREŚĆ GŁÓWNA ═══════════════════════════════════════ -->
    <div class="rp-tresc">

        <?php if ($kat == 'kreator'): ?>
            <!-- ══ KREATOR SESJI ═════════════════════════════ -->
            <div class="rp-panel">
                <div class="panel-tytul"><span class="ikona">🎲</span> Kreator Sesji</div>
                <div class="panel-opis">Zdefiniuj ramy swojej opowieści. Kategoria określa zasady dostępu, gatunek — klimat, a trudność — ryzyko dla postaci.</div>

                <form method="POST" class="kreator-form">

                    <div class="form-wiersz">
                        <label>Tytuł Sesji <span class="req">*</span></label>
                        <input type="text" name="tytul" class="form-input" placeholder="np. Morderstwo w kasynie Golden Dragon" required minlength="5" maxlength="120">
                    </div>

                    <div class="form-dwa">
                        <div class="form-wiersz">
                            <label>Kategoria <span class="req">*</span></label>
                            <select name="kategoria" class="form-select" id="kat-select" onchange="updateKatInfo()">
                                <?php foreach ($KATEGORIE as $kat_nazwa => $kat_dane):
                                    $disabled = ($kat_dane['tylko_mg'] && !$czy_globalny_mg);
                                ?>
                                    <option value="<?php echo htmlspecialchars($kat_nazwa); ?>"
                                            data-opis="<?php echo htmlspecialchars($kat_dane['opis']); ?>"
                                            data-kolor="<?php echo $kat_dane['kolor']; ?>"
                                            <?php if($disabled) echo 'disabled'; ?>>
                                        <?php echo $kat_dane['ikona'] . ' ' . $kat_nazwa . ($disabled ? ' (tylko MG)' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="kat-info" id="kat-info"></div>
                        </div>
                        <div class="form-wiersz">
                            <label>Poziom Trudności <span class="req">*</span></label>
                            <select name="trudnosc" class="form-select">
                                <option value="Łatwa">🟢 Łatwa — niskie ryzyko (ST 10)</option>
                                <option value="Normalna" selected>🟡 Normalna — standardowe wyzwania (ST 15)</option>
                                <option value="Wysoka">🟠 Wysoka — wymagani specjaliści (ST 20)</option>
                                <option value="Koszmar">🔴 Koszmar — ryzyko śmierci postaci! (ST 25)</option>
                            </select>
                            <div class="hint">Trudność ogólna — informuje graczy „na co się piszą". Konkretny próg PT ustala się przy każdym rzucie.</div>
                        </div>
                    </div>

                    <div class="form-wiersz">
                        <label>Gatunek <span class="req">*</span></label>
                        <div class="gatunek-grid">
                            <?php $pierwszy = true; foreach ($GATUNKI as $gat_nazwa => $gat_dane): ?>
                            <label class="gatunek-opt <?php if($pierwszy) echo 'zaznaczony'; ?>" title="<?php echo htmlspecialchars($gat_dane['opis']); ?>">
                                <input type="radio" name="gatunek" value="<?php echo htmlspecialchars($gat_nazwa); ?>" <?php if($pierwszy) echo 'checked'; ?>>
                                <span class="ikona"><?php echo $gat_dane['ikona']; ?></span>
                                <span class="nazwa"><?php echo htmlspecialchars($gat_nazwa); ?></span>
                            </label>
                            <?php $pierwszy = false; endforeach; ?>
                        </div>
                    </div>

                    <div class="form-wiersz">
                        <label>Tagi <span style="color:var(--txt-mute);font-weight:400;letter-spacing:.5px">(po przecinku)</span></label>
                        <input type="text" name="tagi" class="form-input" placeholder="np. cyberpunk, noir, 18+, szpiegowska">
                    </div>

                    <div class="form-wiersz">
                        <label>Ostrzeżenia treściowe <span style="color:var(--txt-mute);font-weight:400;letter-spacing:.5px">(opcjonalne)</span></label>
                        <input type="text" name="ostrzezenia" class="form-input" placeholder="np. przemoc graficzna, tematy dojrzałe, możliwa śmierć postaci">
                        <div class="hint">Uczciwe ostrzeżenie pozwala graczom świadomie zdecydować o udziale.</div>
                    </div>

                    <div class="form-wiersz">
                        <label>Wstęp fabularny <span class="req">*</span></label>
                        <textarea name="opis" class="form-textarea" placeholder="Opisz miejsce, sytuację wyjściową, atmosferę. To pierwsze, co zobaczą gracze — przyciągnij ich." required minlength="20"></textarea>
                    </div>

                    <div style="text-align:right;padding-top:8px;border-top:1px dashed var(--border-soft)">
                        <button type="submit" name="stworz_sesje" class="btn-akcja">◤ Załóż Sesję ◥</button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- ══ LISTA SESJI ═══════════════════════════════ -->
            <div class="rp-panel">
                <div class="panel-tytul"><span class="ikona"><?php echo $ikona_listy; ?></span> <?php echo htmlspecialchars($tytul_listy); ?></div>
                <div class="panel-opis"><?php echo htmlspecialchars($opis_listy); ?></div>

                <?php if ($lista_sesji && $lista_sesji->num_rows > 0): ?>
                    <?php while ($s = $lista_sesji->fetch_assoc()):
                        $avatar = !empty($s['avatar']) ? $s['avatar'] : 'https://via.placeholder.com/70/0a0a0a/333?text=MG';
                        $kat_dane = $KATEGORIE[$s['kategoria']] ?? ['ikona'=>'?', 'kolor'=>'var(--neon-red)'];
                        $gat_dane = $GATUNKI[$s['gatunek']] ?? ['ikona'=>'?'];
                        $status_class = 'status-trwa';
                        if ($s['status']=='Rekrutacja') $status_class = 'status-rekrut';
                        elseif ($s['status']=='Zakończona') $status_class = 'status-zakonczona';
                        $krotki = mb_substr(strip_tags($s['opis']), 0, 220);
                        if (mb_strlen(strip_tags($s['opis'])) > 220) $krotki .= '...';
                    ?>
                    <div class="sesja-karta" style="--akcent: <?php echo $kat_dane['kolor']; ?>">
                        <div class="sesja-avatar" style="background-image:url('<?php echo htmlspecialchars($avatar); ?>')"></div>
                        <div class="sesja-info">
                            <div class="sesja-naglowek">
                                <h3 class="sesja-tytul"><?php echo htmlspecialchars($s['tytul']); ?></h3>
                                <a href="game.php?page=pokoj_sesji&id=<?php echo $s['id']; ?>" class="btn-wejdz">Wejdź →</a>
                            </div>
                            <div class="sesja-tagi">
                                <span class="tag-mini kat"><?php echo $kat_dane['ikona']; ?> <?php echo htmlspecialchars($s['kategoria']); ?></span>
                                <span class="tag-mini gat"><?php echo $gat_dane['ikona']; ?> <?php echo htmlspecialchars($s['gatunek']); ?></span>
                                <span class="tag-mini trudnosc">◆ <?php echo htmlspecialchars($s['poziom_trudnosci']); ?></span>
                                <span class="tag-mini <?php echo $status_class; ?>">◉ <?php echo htmlspecialchars($s['status']); ?></span>
                                <span class="tag-mini graczy">👥 <?php echo (int)$s['liczba_graczy']; ?></span>
                                <?php
                                $tagi_arr = array_filter(array_map('trim', explode(',', $s['tagi'] ?? '')));
                                foreach (array_slice($tagi_arr, 0, 3) as $t): ?>
                                    <span class="tag-mini"><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="sesja-mg">MG: <b><?php echo htmlspecialchars($s['nazwa_mg']); ?></b></div>
                            <div class="sesja-wstep"><?php echo htmlspecialchars($krotki); ?></div>
                            <?php if (!empty($s['ostrzezenia'])): ?>
                            <div class="sesja-ostrzezenie">⚠ <?php echo htmlspecialchars($s['ostrzezenia']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="brak-sesji">
                        // Brak sesji w tej kategorii<br>
                        <a href="game.php?page=sesje&kat=kreator">+ Załóż pierwszą</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($kat == 'kreator'): ?>
<script>
// ── OPIS KATEGORII w kreatorze ──
const katData = <?php echo json_encode(array_map(function($k){
    return ['opis' => $k['opis'], 'kolor' => $k['kolor']];
}, $KATEGORIE)); ?>;

function updateKatInfo() {
    const sel = document.getElementById('kat-select');
    const info = document.getElementById('kat-info');
    const opt = sel.options[sel.selectedIndex];
    const opis = opt.dataset.opis || '';
    const kolor = opt.dataset.kolor || 'var(--neon-cyan)';
    info.innerHTML = '<strong>' + opt.text.trim() + '</strong> — ' + opis;
    info.style.setProperty('--akcent-kat', kolor);
    info.style.borderLeftColor = kolor;
}
updateKatInfo();

// ── GATUNEK: wizualne zaznaczenie radio ──
document.querySelectorAll('.gatunek-opt input').forEach(r => {
    r.addEventListener('change', function() {
        document.querySelectorAll('.gatunek-opt').forEach(l => l.classList.remove('zaznaczony'));
        if (this.checked) this.closest('.gatunek-opt').classList.add('zaznaczony');
    });
});
</script>
<?php endif; ?>