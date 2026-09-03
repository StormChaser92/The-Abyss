<?php
require_once "db.php";
require_once "helpers/vip.php";
require_once "helpers/firmy.php";

$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";
$dzial = isset($_GET['dzial']) ? $_GET['dzial'] : 'rejestr_firm';

// POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT id, login, is_premium, vip_do, gotowka, id_firmy, stanowisko_w_firmie, id_domu FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// Pobierz firmę gracza (jeśli ma)
$moja_firma = null;
if (!empty($gracz['id_firmy'])) {
    $moja_firma = pobierz_firme($gracz['id_firmy']);
}

// --- DEFINICJA RYNKU NIERUCHOMOŚCI ---
$lista_nieruchomosci = [
    1 => ["nazwa" => "Pokój w Motelu 'Pod Zdechłym Szczurem'", "koszt" => 1000, "opis" => "Pchły w materacu gratis. Brak garażu. Brak miejsca na Pokoje Specjalne. Podstawa do przetrwania.", "bonus_en" => 10],
    2 => ["nazwa" => "Kawalera w Śródmieściu", "koszt" => 15000, "opis" => "Miejsce na 1 pojazd w zaułku. Możliwość wstawienia małego biurka (1 mały Pokój Specjalny).", "bonus_en" => 15],
    3 => ["nazwa" => "Opuszczony Magazyn w Dokach", "koszt" => 75000, "opis" => "Duży garaż na 3 pojazdy. Ogromna przestrzeń na warsztat, laboratorium lub serwerownię (2 Pokoje).", "bonus_en" => 20],
    4 => ["nazwa" => "Luksusowy Apartament w Wieży", "koszt" => 250000, "opis" => "Prywatna ochrona. Podziemny parking na 5 aut. Mnóstwo miejsca na zaawansowaną działalność (3 Pokoje).", "bonus_en" => 35],
    5 => ["nazwa" => "Willa na Wzgórzach", "koszt" => 1000000, "opis" => "Twierdza bossa. Miejsce na helikopter, flotę aut i całe zaplecze kryminalno-biznesowe (5 Pokoi).", "bonus_en" => 50]
];

// ════════════════════════════════════════════════════════════════════
// LOGIKA: REJESTRACJA NOWEJ FIRMY (nowy system z tabelą `firmy`)
// ════════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['zaloz_firme'])) {
    if (!czy_vip($gracz)) {
        $komunikat = "<div class='msg msg-bad'>Tylko obywatele ze statusem VIP mogą rejestrować działalność! <a href='game.php?page=premium' style='color:var(--neon-gold);'>→ Wykup VIP</a></div>";
    } elseif (!empty($gracz['id_firmy'])) {
        $komunikat = "<div class='msg msg-bad'>Już prowadzisz/pracujesz w firmie. Wypowiedz pracę lub zamknij firmę aby założyć nową.</div>";
    } elseif ($gracz['gotowka'] < FIRMA_KOSZT_REJESTRACJI) {
        $brakuje = FIRMA_KOSZT_REJESTRACJI - $gracz['gotowka'];
        $komunikat = "<div class='msg msg-bad'>Brak gotówki! Wymagane " . number_format(FIRMA_KOSZT_REJESTRACJI, 0, '', ' ') . " $ — brakuje " . number_format($brakuje, 0, '', ' ') . " $.</div>";
    } else {
        $nazwa = trim($_POST['nazwa'] ?? '');
        $branza = trim($_POST['branza'] ?? '');
        $slogan = trim($_POST['slogan'] ?? '');
        $adres = trim($_POST['adres_lokalu'] ?? '');
        $opis_rp = trim($_POST['opis_rp'] ?? '');

        $branze_dostepne = array_keys(definicja_branz());

        if (mb_strlen($nazwa) < 3 || mb_strlen($nazwa) > 120) {
            $komunikat = "<div class='msg msg-bad'>Nazwa firmy musi mieć od 3 do 120 znaków.</div>";
        } elseif (!in_array($branza, $branze_dostepne)) {
            $komunikat = "<div class='msg msg-bad'>Nieznana branża.</div>";
        } elseif (mb_strlen($opis_rp) > FIRMA_LIMIT_OPIS_RP) {
            $komunikat = "<div class='msg msg-bad'>Opis fabularny może mieć maksymalnie " . FIRMA_LIMIT_OPIS_RP . " znaków.</div>";
        } else {
            // Sprawdź unikalność nazwy
            $nazwa_e = $polaczenie->real_escape_string($nazwa);
            $check = $polaczenie->query("SELECT id FROM firmy WHERE nazwa = '$nazwa_e' AND status != 'zamknieta'");
            if ($check && $check->num_rows > 0) {
                $komunikat = "<div class='msg msg-bad'>Firma o tej nazwie już istnieje. Wybierz inną.</div>";
            } else {
                // Pobierz kasę i utwórz firmę
                $polaczenie->query("UPDATE gracze SET gotowka = gotowka - " . FIRMA_KOSZT_REJESTRACJI . " WHERE id = $id_gracza");

                $branza_e = $polaczenie->real_escape_string($branza);
                $slogan_e = $polaczenie->real_escape_string($slogan);
                $adres_e  = $polaczenie->real_escape_string($adres);
                $opis_e   = $polaczenie->real_escape_string($opis_rp);

                $polaczenie->query("
                    INSERT INTO firmy
                        (wlasciciel_id, nazwa, branza_kod, slogan, opis_rp, adres_lokalu, status, data_zalozenia)
                    VALUES
                        ($id_gracza, '$nazwa_e', '$branza_e', '$slogan_e', '$opis_e', '$adres_e', 'aktywna', NOW())
                ");
                $nowa_firma_id = (int)$polaczenie->insert_id;

                // Upload logo i bannera
                $upload_logo = upload_obrazka_firmy('plik_logo', 'logo_url_text', 'logo', $nowa_firma_id);
                if ($upload_logo['ok'] && $upload_logo['url'] !== '') {
                    $url_e = $polaczenie->real_escape_string($upload_logo['url']);
                    $polaczenie->query("UPDATE firmy SET logo_url = '$url_e' WHERE id = $nowa_firma_id");
                }
                $upload_banner = upload_obrazka_firmy('plik_banner', 'banner_url_text', 'banner', $nowa_firma_id);
                if ($upload_banner['ok'] && $upload_banner['url'] !== '') {
                    $url_e = $polaczenie->real_escape_string($upload_banner['url']);
                    $polaczenie->query("UPDATE firmy SET banner_url = '$url_e' WHERE id = $nowa_firma_id");
                }

                // Przypisz gracza jako właściciela
                $polaczenie->query("
                    UPDATE gracze
                    SET id_firmy = $nowa_firma_id,
                        stanowisko_w_firmie = 'wlasciciel',
                        data_zatrudnienia = NOW(),
                        nazwa_firmy = '$nazwa_e',
                        branza_firmy = '$branza_e'
                    WHERE id = $id_gracza
                ");

                // Pierwszy wpis w kronice
                dodaj_kronike($nowa_firma_id, 'wydarzenie',
                    'Założenie firmy',
                    'Firma została zarejestrowana w Urzędzie Miasta The Abyss.',
                    $id_gracza, 1);

                $komunikat = "<div class='msg msg-good'>Firma &bdquo;" . htmlspecialchars($nazwa) . "&rdquo; została zarejestrowana. <a href='game.php?page=profil_firmy&id=$nowa_firma_id' style='color:var(--neon-gold);'>→ Zobacz profil</a></div>";

                // Odśwież lokalne dane
                $gracz['gotowka'] -= FIRMA_KOSZT_REJESTRACJI;
                $gracz['id_firmy'] = $nowa_firma_id;
                $gracz['stanowisko_w_firmie'] = 'wlasciciel';
                $moja_firma = pobierz_firme($nowa_firma_id);
            }
        }
    }
}

// --- LOGIKA: KUPNO NIERUCHOMOŚCI ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kup_dom'])) {
    $wybrany_id = (int)$_POST['id_nieruchomosci'];

    if (isset($lista_nieruchomosci[$wybrany_id])) {
        $cena_domu = $lista_nieruchomosci[$wybrany_id]['koszt'];

        if ($gracz['id_domu'] >= $wybrany_id) {
            $komunikat = "<div class='msg msg-bad'>Masz już tę lub lepszą nieruchomość!</div>";
        } elseif ($gracz['gotowka'] < $cena_domu) {
            $komunikat = "<div class='msg msg-bad'>Nie stać Cię na ten akt własności. Brakuje " . number_format($cena_domu - $gracz['gotowka'], 0, '', ' ') . " $!</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $cena_domu, id_domu = $wybrany_id WHERE id = $id_gracza");
            $komunikat = "<div class='msg msg-good'>Gratulacje! Podpisano akt własności: " . htmlspecialchars($lista_nieruchomosci[$wybrany_id]['nazwa']) . ".</div>";
            $gracz['gotowka'] -= $cena_domu;
            $gracz['id_domu'] = $wybrany_id;
        }
    }
}
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   URZĄD MIASTA — natywny design system gry
═══════════════════════════════════════════════════════════════ */

/* Nawigacja zakładek urzędu (w stylu .grid) */
.urzad-nav {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
    margin-bottom: 22px;
}
@media(max-width: 820px) { .urzad-nav { grid-template-columns: repeat(2, 1fr); } }

.urzad-tab {
    text-align: center; padding: 12px 8px;
    background: rgba(0,0,0,0.38); border: 1px solid var(--border-soft);
    color: var(--txt-dim); text-decoration: none; border-radius: 1px;
    font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 2px;
    font-size: .85em; transition: all .25s; position: relative;
}
.urzad-tab:hover {
    background: rgba(255,23,68,0.08); color: #fff;
    border-color: var(--border-mid);
}
.urzad-tab.aktywny {
    background: linear-gradient(180deg, rgba(255,23,68,0.18), rgba(255,23,68,0.04));
    color: #fff; border-color: var(--neon-red);
    text-shadow: 0 0 6px rgba(255,23,68,0.5);
}
.urzad-tab.aktywny::after {
    content: ''; position: absolute; left: 0; right: 0; bottom: -1px;
    height: 1px; background: var(--neon-red); box-shadow: 0 0 6px var(--neon-red);
}

/* Panel — używa stylu .card */
.urzad-panel {
    background: rgba(18,10,18,0.45); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 20px; margin-bottom: 22px; position: relative;
}
.urzad-panel::before {
    content: ''; position: absolute; top: 0; left: 0; width: 28px; height: 1px;
    background: var(--neon-red); box-shadow: 0 0 6px var(--neon-red);
}
.urzad-panel.gold::before { background: var(--neon-gold); box-shadow: 0 0 6px var(--neon-gold); }
.urzad-panel.gold { border-color: rgba(255,215,0,0.35); }
.urzad-panel h2 {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 1.05em;
    text-transform: uppercase; letter-spacing: 2px; color: #fff;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px dashed rgba(255,23,68,0.18);
    display: flex; align-items: center; gap: 10px;
}
.urzad-panel.gold h2 { border-bottom-color: rgba(255,215,0,0.18); color: var(--neon-gold); }
.urzad-panel h2 .tag {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-red); letter-spacing: 2px; font-weight: 400;
    padding: 2px 6px; border: 1px solid rgba(255,23,68,0.3); border-radius: 1px;
}
.urzad-panel.gold h2 .tag {
    color: var(--neon-gold); border-color: rgba(255,215,0,0.3);
}

/* Pola formularzy */
.urzad-input, .urzad-select, .urzad-textarea {
    width: 100%; padding: 10px 12px;
    background: rgba(0,0,0,0.5); border: 1px solid var(--border-soft); color: var(--txt-main);
    border-radius: 1px; font-family: 'Rajdhani', sans-serif; font-size: .98em;
    margin-bottom: 12px; transition: all .25s;
}
.urzad-input:focus, .urzad-select:focus, .urzad-textarea:focus {
    outline: none; border-color: var(--neon-gold);
    background: rgba(255,215,0,0.04);
    box-shadow: 0 0 12px rgba(255,215,0,0.15);
}
.urzad-textarea { font-family: 'Rajdhani', sans-serif; resize: vertical; min-height: 80px; }
.urzad-label {
    display: block; font-family: 'JetBrains Mono', monospace;
    font-size: .72em; color: var(--txt-mute); letter-spacing: 2px;
    text-transform: uppercase; margin-bottom: 6px;
}

/* Przyciski w stylu .btn z game.php */
.urzad-btn {
    flex: 1; padding: 10px 16px;
    background: rgba(255,23,68,0.08); border: 1px solid var(--border-mid); color: #fff;
    font-family: 'Oswald', sans-serif; font-weight: 500; letter-spacing: 2px;
    text-transform: uppercase; font-size: .85em;
    cursor: pointer; border-radius: 1px; transition: all .25s;
    text-decoration: none; text-align: center; display: inline-block; box-sizing: border-box;
}
.urzad-btn:hover {
    background: var(--neon-red); color: #fff;
    box-shadow: 0 0 18px rgba(255,23,68,0.7);
    text-shadow: 0 0 6px rgba(255,255,255,0.8);
}
.urzad-btn.gold {
    background: rgba(255,215,0,0.08); border-color: rgba(255,215,0,0.35);
}
.urzad-btn.gold:hover {
    background: var(--neon-gold); color: #000;
    box-shadow: 0 0 18px rgba(255,215,0,0.5);
}
.urzad-btn.green {
    background: rgba(90,255,154,0.08); border-color: rgba(90,255,154,0.35); color: #fff;
}
.urzad-btn.green:hover {
    background: var(--neon-green); color: #000;
    box-shadow: 0 0 18px rgba(90,255,154,0.5);
}
.urzad-btn:disabled, .urzad-btn[disabled] {
    background: transparent !important; border-color: var(--border-soft) !important;
    color: var(--txt-mute) !important; cursor: not-allowed; opacity: .5;
    box-shadow: none !important;
}

/* Komunikaty */
.msg {
    padding: 12px 16px; border-radius: 1px; margin-bottom: 18px;
    font-family: 'JetBrains Mono', monospace; font-size: .85em;
    letter-spacing: 1.2px; line-height: 1.5;
}
.msg-good {
    background: rgba(90,255,154,0.06); border: 1px solid rgba(90,255,154,0.4);
    color: var(--neon-green); text-shadow: 0 0 6px rgba(90,255,154,0.3);
}
.msg-bad {
    background: rgba(255,23,68,0.06); border: 1px solid var(--border-mid);
    color: var(--neon-red-hot); text-shadow: 0 0 6px rgba(255,23,68,0.3);
}

/* Branże w gridzie */
.grid-branze { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
@media(max-width: 1100px) { .grid-branze { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 600px)  { .grid-branze { grid-template-columns: 1fr; } }
.branza-radio { position: relative; }
.branza-radio input { position: absolute; opacity: 0; pointer-events: none; }
.branza-radio label {
    display: block; padding: 12px; cursor: pointer;
    background: rgba(0,0,0,0.4); border: 1px solid var(--border-soft);
    border-radius: 1px; transition: all .2s;
}
.branza-radio label:hover { border-color: var(--border-mid); background: rgba(255,23,68,0.04); }
.branza-radio input:checked + label {
    border-color: var(--neon-gold); background: rgba(255,215,0,0.06);
    box-shadow: 0 0 12px rgba(255,215,0,0.15) inset;
}
.branza-radio .b-ikona { font-size: 1.4em; margin-right: 8px; }
.branza-radio .b-nazwa {
    font-family: 'Oswald', sans-serif; font-weight: 500;
    text-transform: uppercase; letter-spacing: 1.5px; font-size: .9em; color: #fff;
}
.branza-radio .b-opis {
    color: var(--txt-dim); font-size: .8em; margin-top: 4px; line-height: 1.4;
}

/* Tabele */
.urzad-tabela { width: 100%; border-collapse: collapse; font-size: .92em; }
.urzad-tabela th {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase;
    padding: 10px 12px; border-bottom: 1px solid var(--border-soft); text-align: left;
}
.urzad-tabela td {
    color: var(--txt-dim); padding: 10px 12px;
    border-bottom: 1px dashed rgba(255,23,68,0.08);
}
.urzad-tabela tr:hover td { background: rgba(255,23,68,0.04); }

/* Karta nieruchomości */
.dom-karta {
    display: flex; padding: 16px; margin-bottom: 12px;
    background: rgba(0,0,0,0.4); border: 1px solid var(--border-soft); border-radius: 1px;
    align-items: center; transition: all .25s;
}
.dom-karta:hover { border-color: var(--border-mid); }
.dom-karta.posiadany { border-color: rgba(90,255,154,0.4); background: rgba(90,255,154,0.04); }
.dom-karta h3 {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 1.1em;
    color: #fff; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;
}
.dom-karta p { color: var(--txt-dim); font-size: .92em; line-height: 1.5; margin-bottom: 4px; }
.dom-cena {
    font-family: 'Oswald', sans-serif; font-size: 1.5em;
    color: var(--neon-ember); text-shadow: 0 0 6px rgba(255,122,61,0.4);
    line-height: 1;
}

/* Certyfikat (gdy gracz ma firmę) */
.certyfikat {
    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    border: 2px solid rgba(255,215,0,0.5); border-radius: 2px;
    padding: 28px; text-align: center; margin-bottom: 20px;
    box-shadow: 0 0 24px rgba(255,215,0,0.1) inset;
}
.cert-logo {
    width: 100px; height: 100px; margin: 0 auto 14px;
    background: rgba(0,0,0,0.5); border: 1px solid rgba(255,215,0,0.3);
    background-size: cover; background-position: center; border-radius: 1px;
}
.cert-nazwa {
    font-family: 'Oswald', sans-serif; font-weight: 500;
    font-size: 1.8em; color: #fff; text-transform: uppercase; letter-spacing: 2px;
    text-shadow: 0 0 16px rgba(255,215,0,0.3); margin-bottom: 6px;
}
.cert-branza {
    font-family: 'JetBrains Mono', monospace; font-size: .82em;
    color: var(--neon-gold); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 16px;
}
.cert-stanowisko {
    color: var(--txt-dim); font-size: .92em; font-family: 'Cormorant Garamond', serif;
    font-style: italic; margin-bottom: 18px;
}

/* Layout kolumn dla rejestracji */
.dwie-kolumny {
    display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;
}
@media(max-width: 820px) { .dwie-kolumny { grid-template-columns: 1fr; } }
</style>

<header class="page-head">
    <div class="eyebrow">★ THE ABYSS // CITY HALL</div>
    <h1>Urząd Miasta</h1>
    <p class="lead">Wszystkie sprawy formalne — rejestracja firm, akta obywateli, nieruchomości i dyskretne usługi specjalne — jeden adres, cztery kontuary.</p>
</header>

<?php echo $komunikat; ?>

<nav class="urzad-nav">
    <a href="game.php?page=firma&dzial=rejestr_firm" class="urzad-tab <?php if($dzial=='rejestr_firm') echo 'aktywny'; ?>">🏢 Wydział Biznesu</a>
    <a href="game.php?page=firma&dzial=spis_ludnosci" class="urzad-tab <?php if($dzial=='spis_ludnosci') echo 'aktywny'; ?>">👥 Archiwum</a>
    <a href="game.php?page=firma&dzial=nieruchomosci" class="urzad-tab <?php if($dzial=='nieruchomosci') echo 'aktywny'; ?>">🏠 Nieruchomości</a>
    <a href="game.php?page=firma&dzial=tozsamosc" class="urzad-tab <?php if($dzial=='tozsamosc') echo 'aktywny'; ?>">🎭 Tożsamość</a>
</nav>

<?php
// ════════════════════════════════════════════════════════════════════
// WYDZIAŁ BIZNESU
// ════════════════════════════════════════════════════════════════════
if ($dzial == 'rejestr_firm'):
    $branze = definicja_branz();
?>
    <?php if (!czy_vip($gracz)): ?>
        <div class="urzad-panel gold" style="text-align: center;">
            <h2 style="border: none; padding: 0; margin-bottom: 18px;">★ Tylko dla VIP <span class="tag">PREMIUM</span></h2>
            <p style="color: var(--txt-dim); font-size: 1em; line-height: 1.6; margin-bottom: 20px;">
                Tylko obywatele ze statusem VIP mogą otwierać legalne firmy w The Abyss.<br>
                W mieście, gdzie wizerunek decyduje o wszystkim — biznesem zajmują się ludzie z klasą.
            </p>
            <a href="game.php?page=premium" class="urzad-btn gold" style="display: inline-block; max-width: 280px;">★ Wykup status VIP</a>
        </div>

    <?php elseif ($moja_firma): ?>
        <!-- Gracz ma firmę / pracuje w niej -->
        <div class="certyfikat">
            <?php if (!empty($moja_firma['logo_url'])): ?>
                <div class="cert-logo" style="background-image: url('<?php echo htmlspecialchars($moja_firma['logo_url']); ?>');"></div>
            <?php else: ?>
                <div class="cert-logo" style="display:flex; align-items:center; justify-content:center; font-size:2.5em;">
                    <?php echo $branze[$moja_firma['branza_kod']]['ikona'] ?? '🏢'; ?>
                </div>
            <?php endif; ?>
            <div class="cert-nazwa"><?php echo htmlspecialchars($moja_firma['nazwa']); ?></div>
            <div class="cert-branza"><?php echo htmlspecialchars(nazwa_branzy($moja_firma['branza_kod'])); ?></div>
            <div class="cert-stanowisko">
                <?php
                $stan_label = [
                    'wlasciciel' => 'Twoja firma — jesteś właścicielem',
                    'manager' => 'Pracujesz tu jako Manager',
                    'pracownik' => 'Jesteś pracownikiem tej firmy'
                ];
                echo $stan_label[$gracz['stanowisko_w_firmie']] ?? 'Pracownik';
                ?>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="game.php?page=profil_firmy&id=<?php echo (int)$moja_firma['id']; ?>" class="urzad-btn gold" style="max-width: 220px;">
                    Zobacz publiczny profil
                </a>
                <?php if ($gracz['stanowisko_w_firmie'] === 'wlasciciel'): ?>
                    <a href="game.php?page=moja_firma" class="urzad-btn green" style="max-width: 220px;">
                        Panel zarządzania
                    </a>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Formularz rejestracji nowej firmy -->
        <div class="urzad-panel gold">
            <h2>Rejestracja nowej firmy <span class="tag">KOSZT: <?php echo number_format(FIRMA_KOSZT_REJESTRACJI, 0, '', ' '); ?> $</span></h2>

            <p style="color: var(--txt-dim); margin-bottom: 18px; font-family: 'Cormorant Garamond', serif; font-style: italic; line-height: 1.6;">
                Otwarcie legalnego biznesu w The Abyss to nie tylko dokumenty. To deklaracja, że stać Cię na utrzymanie własnej marki w jednym z najbardziej bezlitosnych miast świata.
            </p>

            <form method="POST" enctype="multipart/form-data">
                <div class="dwie-kolumny">
                    <div>
                        <label class="urzad-label">Nazwa firmy (3–120 znaków)</label>
                        <input type="text" name="nazwa" class="urzad-input" required minlength="3" maxlength="120" placeholder="np. Iron Lotus Corp.">
                    </div>
                    <div>
                        <label class="urzad-label">Adres lokalu (opcjonalnie, fabularnie)</label>
                        <input type="text" name="adres_lokalu" class="urzad-input" maxlength="255" placeholder="np. Penthouse, Tribeca, 5. Avenue">
                    </div>
                </div>

                <label class="urzad-label">Slogan / hasło reklamowe (opcjonalnie)</label>
                <input type="text" name="slogan" class="urzad-input" maxlength="255" placeholder="np. &bdquo;Twoje sekrety. Nasza dyskrecja.&rdquo;">

                <label class="urzad-label">Branża <span style="color: var(--neon-red);">*</span></label>
                <div class="grid-branze">
                    <?php foreach ($branze as $kod => $b): ?>
                        <div class="branza-radio">
                            <input type="radio" id="branza_<?php echo $kod; ?>" name="branza" value="<?php echo $kod; ?>" required>
                            <label for="branza_<?php echo $kod; ?>">
                                <div><span class="b-ikona"><?php echo $b['ikona']; ?></span><span class="b-nazwa"><?php echo htmlspecialchars($b['nazwa']); ?></span></div>
                                <div class="b-opis"><?php echo htmlspecialchars($b['opis_skrocony']); ?></div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <label class="urzad-label">Opis fabularny / historia firmy (max <?php echo FIRMA_LIMIT_OPIS_RP; ?> znaków, opcjonalnie)</label>
                <textarea name="opis_rp" class="urzad-textarea" maxlength="<?php echo FIRMA_LIMIT_OPIS_RP; ?>" rows="4" placeholder="Krótka historia, czym firma się zajmuje, jakie ma motto, klienci..."></textarea>

                <div class="dwie-kolumny">
                    <div>
                        <label class="urzad-label">Logo firmy (kwadrat, max 5MB)</label>
                        <input type="file" name="plik_logo" class="urzad-input" accept="image/jpeg,image/png,image/gif,image/webp">
                        <input type="url" name="logo_url_text" class="urzad-input" placeholder="LUB link do obrazka">
                    </div>
                    <div>
                        <label class="urzad-label">Banner firmy (szerokie tło profilu, max 5MB)</label>
                        <input type="file" name="plik_banner" class="urzad-input" accept="image/jpeg,image/png,image/gif,image/webp">
                        <input type="url" name="banner_url_text" class="urzad-input" placeholder="LUB link do obrazka">
                    </div>
                </div>

                <button type="submit" name="zaloz_firme" class="urzad-btn gold" style="margin-top: 12px; padding: 14px;">
                    Opłać <?php echo number_format(FIRMA_KOSZT_REJESTRACJI, 0, '', ' '); ?> $ i zarejestruj
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Spis wszystkich firm -->
    <div class="urzad-panel">
        <h2>Spis przedsiębiorców <span class="tag">REJESTR PUBLICZNY</span></h2>
        <table class="urzad-tabela">
            <thead>
                <tr><th>Firma</th><th>Branża</th><th>Właściciel</th><th>Pracownicy</th><th></th></tr>
            </thead>
            <tbody>
                <?php
                $firmy_sql = $polaczenie->query("
                    SELECT f.id, f.nazwa, f.branza_kod, f.logo_url, g.login AS wlasciciel_login,
                           (SELECT COUNT(*) FROM gracze gp WHERE gp.id_firmy = f.id) AS pracownikow
                    FROM firmy f
                    LEFT JOIN gracze g ON g.id = f.wlasciciel_id
                    WHERE f.status = 'aktywna'
                    ORDER BY f.nazwa ASC
                ");
                $branze_def = definicja_branz();
                if ($firmy_sql && $firmy_sql->num_rows > 0):
                    while($f = $firmy_sql->fetch_assoc()):
                        $b = $branze_def[$f['branza_kod']] ?? ['nazwa' => 'Nieznana', 'ikona' => '🏢', 'kolor' => '#888'];
                ?>
                    <tr>
                        <td><b style="color:#fff;">
                            <span style="margin-right:6px;"><?php echo $b['ikona']; ?></span>
                            <a href="game.php?page=profil_firmy&id=<?php echo (int)$f['id']; ?>" style="color:#fff; text-decoration:none; border-bottom:1px dashed rgba(255,255,255,0.2);">
                                <?php echo htmlspecialchars($f['nazwa']); ?>
                            </a>
                        </b></td>
                        <td><span style="color: <?php echo $b['kolor']; ?>;"><?php echo htmlspecialchars($b['nazwa']); ?></span></td>
                        <td><span style="color: var(--neon-gold);">★ <?php echo htmlspecialchars($f['wlasciciel_login'] ?? '?'); ?></span></td>
                        <td><?php echo (int)$f['pracownikow']; ?> / <?php echo FIRMA_MAX_PRACOWNIKOW; ?></td>
                        <td><a href="game.php?page=profil_firmy&id=<?php echo (int)$f['id']; ?>" style="color: var(--neon-cyan); font-family: 'JetBrains Mono', monospace; font-size: .8em; text-decoration: none;">// PROFIL →</a></td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr><td colspan="5" style="text-align:center; padding: 24px; color: var(--txt-mute); font-style: italic;">// brak zarejestrowanych firm //</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php
// ════════════════════════════════════════════════════════════════════
// ARCHIWUM MIEJSKIE
// ════════════════════════════════════════════════════════════════════
elseif ($dzial == 'spis_ludnosci'):
    $warunek_wyszukiwania = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['szukany_nick'])) {
        $czysty_nick = $polaczenie->real_escape_string(trim($_POST['szukany_nick']));
        $warunek_wyszukiwania = "WHERE login LIKE '%$czysty_nick%'";
    }
?>
    <div class="urzad-panel">
        <h2>Wyszukiwarka obywateli <span class="tag">ARCHIWUM</span></h2>
        <form method="POST" style="display: flex; gap: 8px;">
            <input type="text" name="szukany_nick" class="urzad-input" style="margin: 0; flex: 1;" placeholder="Wpisz fragment nicku...">
            <button type="submit" class="urzad-btn" style="flex: none; max-width: 180px;">Szukaj w aktach</button>
        </form>
    </div>
    <div class="urzad-panel">
        <h2>Akta miejskie <span class="tag">REKORDY</span></h2>
        <table class="urzad-tabela">
            <thead>
                <tr><th>Nick</th><th>Poziom</th><th>Klasa</th><th>Zawód</th></tr>
            </thead>
            <tbody>
                <?php
                $mieszkancy_sql = $polaczenie->query("SELECT login, poziom, klasa, profesja_fabularna, is_premium FROM gracze $warunek_wyszukiwania ORDER BY id ASC LIMIT 50");
                if ($mieszkancy_sql && $mieszkancy_sql->num_rows > 0):
                    while($m = $mieszkancy_sql->fetch_assoc()):
                        $vip = ($m['is_premium']==1) ? "<span style='color:var(--neon-gold);'>★</span> " : "";
                ?>
                    <tr>
                        <td><b style="color:#fff;"><?php echo $vip . htmlspecialchars($m['login']); ?></b></td>
                        <td style="color: var(--neon-green);"><?php echo (int)$m['poziom']; ?></td>
                        <td><?php echo htmlspecialchars($m['klasa']); ?></td>
                        <td><?php echo htmlspecialchars($m['profesja_fabularna']); ?></td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--txt-mute); font-style:italic;">// brak wyników //</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php
// ════════════════════════════════════════════════════════════════════
// NIERUCHOMOŚCI
// ════════════════════════════════════════════════════════════════════
elseif ($dzial == 'nieruchomosci'):
?>
    <div class="urzad-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; margin-bottom: 16px; border-bottom: 1px dashed rgba(255,23,68,0.18);">
            <h2 style="margin: 0; padding: 0; border: none;">Księgi wieczyste <span class="tag">NIERUCHOMOŚCI</span></h2>
            <div style="font-family: 'JetBrains Mono', monospace; font-size: .85em; color: var(--txt-dim);">
                Gotówka: <span style="color: var(--neon-ember); font-family: 'Oswald'; font-size: 1.2em;"><?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $</span>
            </div>
        </div>

        <p style="color: var(--txt-dim); margin-bottom: 18px; line-height: 1.6;">
            Własny dach nad głową pozwala na spokojny sen — dodatkową dzienną porcję energii. Większe posesje umożliwiają w przyszłości tworzenie pokoi specjalnych i potężnych garaży.
        </p>

        <?php foreach($lista_nieruchomosci as $id_dom => $dane):
            $czy_posiada = ($gracz['id_domu'] == $id_dom);
            $stac_go = ($gracz['gotowka'] >= $dane['koszt']);
        ?>
            <div class="dom-karta <?php if($czy_posiada) echo 'posiadany'; ?>">
                <div style="flex: 1; padding-right: 16px;">
                    <h3>
                        <?php echo htmlspecialchars($dane['nazwa']); ?>
                        <?php if($czy_posiada): ?>
                            <span style="font-size:.6em; color: var(--neon-green); margin-left: 8px;">// TWÓJ DOM</span>
                        <?php endif; ?>
                    </h3>
                    <p><?php echo htmlspecialchars($dane['opis']); ?></p>
                    <div style="margin-top: 6px; color: var(--neon-cyan); font-size: .85em; font-family: 'JetBrains Mono', monospace;">
                        // Bonus snu: +<?php echo $dane['bonus_en']; ?> energii × poziom
                    </div>
                </div>
                <div style="text-align: right; min-width: 180px;">
                    <div class="dom-cena"><?php echo number_format($dane['koszt'], 0, '', ' '); ?> $</div>
                    <?php if($gracz['id_domu'] >= $id_dom): ?>
                        <button type="button" class="urzad-btn" disabled style="margin-top: 8px;">Posiadasz</button>
                    <?php elseif(!$stac_go): ?>
                        <button type="button" class="urzad-btn" disabled style="margin-top: 8px;">Brak gotówki</button>
                    <?php else: ?>
                        <form method="POST" style="margin-top: 8px;">
                            <input type="hidden" name="id_nieruchomosci" value="<?php echo $id_dom; ?>">
                            <button type="submit" name="kup_dom" class="urzad-btn green">Kup akt własności</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php
// ════════════════════════════════════════════════════════════════════
// WYDZIAŁ ZMIANY TOŻSAMOŚCI
// ════════════════════════════════════════════════════════════════════
elseif ($dzial == 'tozsamosc'):
?>
    <div class="urzad-panel" style="border-color: rgba(255,23,68,0.4); text-align: center;">
        <h2 style="border: none; padding: 0; margin-bottom: 16px; color: var(--neon-red-hot); justify-content: center;">
            Pokój nr 404 — Zamknięte <span class="tag">RESTRICTED</span>
        </h2>
        <p style="color: var(--txt-dim); font-size: 1em; line-height: 1.6; margin-bottom: 14px;">
            Pukasz do nieoznakowanych drzwi. Przez szparę wygląda podejrzany urzędnik opłacany przez mafię.
        </p>
        <p style="color: var(--neon-red-hot); font-style: italic; font-family: 'Cormorant Garamond', serif; font-size: 1.15em; line-height: 1.6;">
            &bdquo;Jeśli chcesz wymazać swoją kartotekę, zmienić twarz i nazwisko… przygotuj <b style="color: var(--neon-green);">1 000 000 $</b> w gotówce. I nie wracaj, póki tyle nie masz.&rdquo;
        </p>
    </div>
<?php endif; ?>