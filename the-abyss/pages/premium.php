<?php
require_once "db.php";
require_once "helpers/vip.php";

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

// POBIERANIE DANYCH GRACZA
$wynik = $polaczenie->query("SELECT id, login, gotowka, is_premium, vip_do FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// ════════════════════════════════════════════════════════════════════
// LOGIKA ZAKUPU PAKIETU
// ════════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kup_vip'])) {
    $klucz = isset($_POST['pakiet']) ? trim($_POST['pakiet']) : '';
    $pakiety = vip_pakiety();

    if (!isset($pakiety[$klucz])) {
        $komunikat = "<div class='msg msg-bad'>Nieznany pakiet — odśwież stronę i spróbuj ponownie.</div>";
    } else {
        $pakiet = $pakiety[$klucz];
        $koszt = (int)$pakiet['koszt'];
        $dni   = (int)$pakiet['dni'];

        if ($gracz['gotowka'] < $koszt) {
            $brakuje = $koszt - $gracz['gotowka'];
            $komunikat = "<div class='msg msg-bad'>Brak gotówki! Potrzebujesz jeszcze " . number_format($brakuje, 0, '', ' ') . " $.</div>";
        } else {
            $polaczenie->query("UPDATE gracze SET gotowka = gotowka - $koszt WHERE id = $id_gracza");
            $nowa_data = dodaj_vip($id_gracza, $dni, 'waluta_growa', $koszt);

            if ($nowa_data === false) {
                $polaczenie->query("UPDATE gracze SET gotowka = gotowka + $koszt WHERE id = $id_gracza");
                $komunikat = "<div class='msg msg-bad'>Błąd systemu — pieniądze zwrócone, spróbuj ponownie.</div>";
            } else {
                $komunikat = "<div class='msg msg-good'>★ Status VIP aktywny do <b>" . htmlspecialchars($nowa_data) . "</b>. Witaj w elicie The Abyss.</div>";
                $gracz['gotowka'] -= $koszt;
                $gracz['vip_do']   = $nowa_data;
                $gracz['is_premium'] = 1;
            }
        }
    }
}

$ma_vip = czy_vip($gracz);
$dni_pozostalo = vip_pozostalo_dni($gracz);
$pakiety = vip_pakiety();

$historia = $polaczenie->query("
    SELECT pakiet_dni, koszt, metoda, data_zakupu, vip_do_po_zakupie
    FROM vip_historia
    WHERE gracz_id = $id_gracza
    ORDER BY data_zakupu DESC
    LIMIT 10
");
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   PREMIUM — natywny design system gry, akcent zlota
═══════════════════════════════════════════════════════════════ */

/* Override page-head dla premium — zloty akcent zamiast czerwonego */
.page-head.gold::after { background: var(--neon-gold) !important; box-shadow: 0 0 8px var(--neon-gold) !important; }
.page-head.gold .eyebrow { color: var(--neon-gold) !important; text-shadow: 0 0 6px rgba(255,215,0,0.5) !important; }
.page-head.gold h1 { text-shadow: 0 0 20px rgba(255,215,0,0.3) !important; }

/* Hero status box */
.vip-hero {
    background: rgba(0,0,0,0.38); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 20px 24px; margin-bottom: 22px; position: relative;
    display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;
}
.vip-hero::before {
    content: ''; position: absolute; left: 0; top: 10%; width: 2px; height: 80%;
    background: var(--neon-gold); box-shadow: 0 0 6px var(--neon-gold);
}
.vip-hero.aktywny { border-color: rgba(255,215,0,0.35); }
.vip-hero .lbl {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 4px;
}
.vip-hero .val-status {
    font-family: 'Oswald', sans-serif; font-size: 1.5em;
    color: #fff; font-weight: 500; line-height: 1;
}
.vip-hero .val-status.gold { color: var(--neon-gold); text-shadow: 0 0 12px rgba(255,215,0,0.4); }
.vip-hero .info {
    font-family: 'JetBrains Mono', monospace; font-size: .85em;
    color: var(--txt-dim); margin-top: 6px;
}
.vip-hero .info b.gold { color: var(--neon-gold); }
.vip-hero .info b.warn { color: var(--neon-ember); }
.vip-hero .info b.danger { color: var(--neon-red-hot); }
.vip-hero .gotowka-box { text-align: right; }
.vip-hero .gotowka-box .val {
    font-family: 'Oswald', sans-serif; font-size: 1.5em;
    color: var(--neon-ember); text-shadow: 0 0 6px rgba(255,122,61,0.5); line-height: 1;
}

/* Pakiety */
.grid-pakiety { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
@media(max-width: 1100px) { .grid-pakiety { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 600px)  { .grid-pakiety { grid-template-columns: 1fr; } }

.pakiet {
    background: rgba(18,10,18,0.45); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 22px 18px; position: relative; transition: all .3s;
    text-align: center; display: flex; flex-direction: column;
}
.pakiet::before {
    content: ''; position: absolute; top: 0; left: 0; width: 28px; height: 1px;
    background: var(--neon-gold); box-shadow: 0 0 6px var(--neon-gold);
}
.pakiet:hover {
    border-color: rgba(255,215,0,0.4);
    box-shadow: 0 0 24px rgba(255,215,0,0.12);
}
.pakiet.polecany { border-color: rgba(74,214,255,0.35); }
.pakiet.polecany::before { background: var(--neon-cyan); box-shadow: 0 0 6px var(--neon-cyan); }
.pakiet.najlepsza { border-color: rgba(255,215,0,0.5); box-shadow: 0 0 16px rgba(255,215,0,0.08) inset; }

.pakiet-banner {
    position: absolute; top: -1px; right: 12px;
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-gold); letter-spacing: 2px; text-transform: uppercase;
    padding: 3px 8px; background: var(--bg-void);
    border: 1px solid rgba(255,215,0,0.4); border-radius: 1px;
    text-shadow: 0 0 6px rgba(255,215,0,0.5);
}
.pakiet-banner.cyan { color: var(--neon-cyan); border-color: rgba(74,214,255,0.4); text-shadow: 0 0 6px rgba(74,214,255,0.5); }

.pakiet-nazwa {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 1em;
    text-transform: uppercase; letter-spacing: 2px;
    color: #fff; margin-bottom: 16px;
}
.pakiet-czas {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 3em;
    color: var(--neon-gold); line-height: 1; margin-bottom: 2px;
    text-shadow: 0 0 18px rgba(255,215,0,0.25);
}
.pakiet-czas-jednostka {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 16px;
}
.pakiet-cena {
    font-family: 'Oswald', sans-serif; font-size: 1.4em;
    color: var(--neon-ember); text-shadow: 0 0 6px rgba(255,122,61,0.5);
    margin: 8px 0 4px; line-height: 1;
}
.pakiet-na-dzien {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); margin-bottom: 14px;
}
.pakiet-opis {
    color: var(--txt-dim); font-size: .85em; line-height: 1.5;
    margin-bottom: 16px; flex-grow: 1;
    padding-top: 12px; border-top: 1px dashed rgba(255,215,0,0.12);
}

/* Przycisk kupna w stylu .btn ale gold */
.btn-vip {
    display: block; width: 100%; padding: 10px 16px;
    background: rgba(255,215,0,0.08); border: 1px solid rgba(255,215,0,0.35); color: #fff;
    font-family: 'Oswald', sans-serif; font-weight: 500; letter-spacing: 2px; text-transform: uppercase; font-size: .85em;
    cursor: pointer; border-radius: 1px; transition: all .25s; text-decoration: none; text-align: center;
}
.btn-vip:hover {
    background: var(--neon-gold); color: #000;
    box-shadow: 0 0 18px rgba(255,215,0,0.5); text-shadow: 0 0 6px rgba(255,255,255,0.8);
}
.btn-vip:disabled {
    background: transparent; border-color: var(--border-soft); color: var(--txt-mute);
    cursor: not-allowed; opacity: .5;
}
.btn-vip:disabled:hover { background: transparent; box-shadow: none; }

/* Sekcje tresci */
.section-block {
    background: rgba(18,10,18,0.45); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    border: 1px solid var(--border-soft); border-radius: 2px;
    padding: 20px; margin-bottom: 22px; position: relative;
}
.section-block::before {
    content: ''; position: absolute; top: 0; left: 0; width: 28px; height: 1px;
    background: var(--neon-gold); box-shadow: 0 0 6px var(--neon-gold);
}
.section-block h2 {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: 1.05em;
    text-transform: uppercase; letter-spacing: 2px; color: #fff;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px dashed rgba(255,215,0,0.18);
    display: flex; align-items: center; gap: 10px;
}
.section-block h2 .tag {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--neon-gold); letter-spacing: 2px; font-weight: 400;
    padding: 2px 6px; border: 1px solid rgba(255,215,0,0.3); border-radius: 1px;
}

/* Lista perkow */
.perki { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
@media(max-width: 820px) { .perki { grid-template-columns: 1fr; } }
.perk {
    padding: 10px 14px; border-left: 2px solid var(--neon-gold);
    background: rgba(255,215,0,0.04); color: var(--txt-dim); font-size: .92em; line-height: 1.45;
}
.perk b {
    font-family: 'Oswald', sans-serif; font-weight: 500; font-size: .95em;
    color: var(--neon-gold); text-transform: uppercase; letter-spacing: 1.5px;
    display: block; margin-bottom: 4px;
}
.perk .soon {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); margin-left: 6px; letter-spacing: 1px;
}
.perki-stopka {
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    color: var(--txt-mute); text-align: center; padding-top: 16px;
    margin-top: 14px; border-top: 1px dashed rgba(255,215,0,0.12);
    letter-spacing: 1.5px; line-height: 1.6;
}

/* Tabela historii */
.tabela-historia { width: 100%; border-collapse: collapse; font-size: .9em; }
.tabela-historia th {
    font-family: 'JetBrains Mono', monospace; font-size: .7em;
    color: var(--txt-mute); letter-spacing: 2px; text-transform: uppercase;
    padding: 8px 10px; border-bottom: 1px solid var(--border-soft); text-align: left;
}
.tabela-historia td {
    color: var(--txt-dim); padding: 9px 10px;
    border-bottom: 1px dashed rgba(255,23,68,0.08);
    font-family: 'JetBrains Mono', monospace; font-size: .88em;
}
.tabela-historia td.gold { color: var(--neon-gold); }
.tabela-historia td.green { color: var(--neon-green); }
.pusta {
    text-align: center; padding: 24px; color: var(--txt-mute);
    font-style: italic; font-family: 'Rajdhani', sans-serif;
}

/* Komunikaty */
.msg {
    padding: 12px 16px; border-radius: 1px; margin-bottom: 18px;
    font-family: 'JetBrains Mono', monospace; font-size: .85em;
    letter-spacing: 1.2px; line-height: 1.5;
}
.msg-good {
    background: rgba(255,215,0,0.06); border: 1px solid rgba(255,215,0,0.4);
    color: var(--neon-gold); text-shadow: 0 0 6px rgba(255,215,0,0.4);
}
.msg-good b { color: #fff; }
.msg-bad {
    background: rgba(255,23,68,0.06); border: 1px solid var(--border-mid);
    color: var(--neon-red-hot); text-shadow: 0 0 6px rgba(255,23,68,0.3);
}
</style>

<!-- ═══════════════════════════════════════════════════════════════
     NAGLOWEK STRONY (zgodny z konwencja gry)
═══════════════════════════════════════════════════════════════ -->
<header class="page-head gold">
    <div class="eyebrow">★ THE ABYSS // PREMIUM CLUB</div>
    <h1>Klub Premium</h1>
    <p class="lead">W Manhattanie liczą się tylko ci, którzy na siebie pracują. Reszta tylko płaci za drinki.</p>
</header>

<?php echo $komunikat; ?>

<!-- ═══════════════════════════════════════════════════════════════
     STATUS GRACZA
═══════════════════════════════════════════════════════════════ -->
<div class="vip-hero <?php if ($ma_vip) echo 'aktywny'; ?>">
    <div>
        <div class="lbl">Status członkostwa</div>
        <?php if ($ma_vip): ?>
            <div class="val-status gold">★ VIP AKTYWNY</div>
            <div class="info">
                Wygasa: <b class="gold"><?php echo htmlspecialchars(date('d.m.Y, H:i', strtotime($gracz['vip_do']))); ?></b>
                <?php if ($dni_pozostalo > 1): ?>
                    &nbsp;//&nbsp; pozostało <b class="gold"><?php echo $dni_pozostalo; ?> dni</b>
                <?php elseif ($dni_pozostalo == 1): ?>
                    &nbsp;//&nbsp; <b class="warn">ostatni dzień!</b>
                <?php else: ?>
                    &nbsp;//&nbsp; <b class="danger">kończy się w ciągu godziny!</b>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="val-status">BRAK STATUSU VIP</div>
            <div class="info">// wybierz pakiet poniżej, aby dołączyć do elity</div>
        <?php endif; ?>
    </div>
    <div class="gotowka-box">
        <div class="lbl">Twoja gotówka</div>
        <div class="val"><?php echo number_format($gracz['gotowka'], 0, '', ' '); ?> $</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     PAKIETY DO KUPNA
═══════════════════════════════════════════════════════════════ -->
<div class="grid-pakiety">
    <?php foreach ($pakiety as $klucz => $p):
        $cena_na_dzien = round($p['koszt'] / $p['dni']);
        $stac_go = ($gracz['gotowka'] >= $p['koszt']);
        $karta_klasa = '';
        if (!empty($p['polecany'])) $karta_klasa = 'polecany';
        if (!empty($p['najlepsza_oferta'])) $karta_klasa = 'najlepsza';
    ?>
        <div class="pakiet <?php echo $karta_klasa; ?>">
            <?php if (!empty($p['najlepsza_oferta'])): ?>
                <div class="pakiet-banner">★ Najlepsza</div>
            <?php elseif (!empty($p['polecany'])): ?>
                <div class="pakiet-banner cyan">Polecany</div>
            <?php endif; ?>

            <div class="pakiet-nazwa"><?php echo htmlspecialchars($p['nazwa']); ?></div>
            <div class="pakiet-czas"><?php echo $p['dni']; ?></div>
            <div class="pakiet-czas-jednostka">DNI</div>
            <div class="pakiet-cena"><?php echo number_format($p['koszt'], 0, '', ' '); ?> $</div>
            <div class="pakiet-na-dzien">≈ <?php echo number_format($cena_na_dzien, 0, '', ' '); ?> $/dzień</div>
            <div class="pakiet-opis"><?php echo htmlspecialchars($p['opis']); ?></div>

            <form method="POST" onsubmit="return confirm('Kupić pakiet <?php echo htmlspecialchars($p['nazwa']); ?> za <?php echo number_format($p['koszt'], 0, '', ' '); ?> $?');">
                <input type="hidden" name="pakiet" value="<?php echo htmlspecialchars($klucz); ?>">
                <button type="submit" name="kup_vip" class="btn-vip" <?php if (!$stac_go) echo 'disabled'; ?>>
                    <?php echo $stac_go ? 'Wykup status' : 'Brak gotówki'; ?>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     LISTA PERKOW
═══════════════════════════════════════════════════════════════ -->
<div class="section-block">
    <h2>★ Przywileje VIP <span class="tag">PERKS</span></h2>
    <div class="perki">
        <div class="perk">
            <b>Możliwość założenia firmy</b>
            Wymagane do otwarcia legalnego biznesu w Urzędzie Miasta.
        </div>
        <div class="perk">
            <b>★ Złota gwiazdka przy nicku</b>
            Widoczna w listach graczy, czacie klubu, profilu i rankingach.
        </div>
        <div class="perk">
            <b>+1 zlecenie dziennie</b>
            Dzienny limit zleceń wzrasta z 5 do 6.
        </div>
        <div class="perk">
            <b>+20% Punktów Pracowniczych <span class="soon">[wkrótce]</span></b>
            Maksimum PP rośnie ze 100 do 120 — szybciej realizujesz zlecenia firmy.
        </div>
        <div class="perk">
            <b>Specjalny kolor wiadomości <span class="soon">[wkrótce]</span></b>
            Złoty cień Twoich postów w czacie klubu.
        </div>
        <div class="perk">
            <b>Stroje premium w garderobie <span class="soon">[wkrótce]</span></b>
            Dostęp do limitowanej kolekcji w klubie The Abyss.
        </div>
        <div class="perk">
            <b>Priorytet w kolejce DJ-a <span class="soon">[wkrótce]</span></b>
            Twoje zamówienia muzyki wskakują wyżej.
        </div>
        <div class="perk">
            <b>Tablica VIP w mieście <span class="soon">[wkrótce]</span></b>
            Osobny ranking — kto najdłużej utrzymuje status.
        </div>
    </div>
    <div class="perki-stopka">
        // VIP NIE KUPUJE PRZEWAGI W WALCE — TYLKO STYL, WYGODĘ I DRZWI DO BIZNESU //
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     HISTORIA ZAKUPOW
═══════════════════════════════════════════════════════════════ -->
<div class="section-block">
    <h2>Historia zakupów <span class="tag">LOG // OSTATNIE 10</span></h2>
    <?php if ($historia && $historia->num_rows > 0): ?>
        <table class="tabela-historia">
            <thead>
                <tr>
                    <th>Data zakupu</th>
                    <th>Pakiet</th>
                    <th>Koszt</th>
                    <th>Metoda</th>
                    <th>Wygasa</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($h = $historia->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($h['data_zakupu']); ?></td>
                        <td class="gold"><?php echo (int)$h['pakiet_dni']; ?> dni</td>
                        <td class="green"><?php echo number_format($h['koszt'], 0, '', ' '); ?> $</td>
                        <td>
                            <?php
                            $etykiety = [
                                'waluta_growa' => 'gotówka',
                                'przelewy24'   => 'Przelewy24',
                                'patreon'      => 'Patreon',
                                'admin'        => 'admin',
                                'gift'         => 'prezent',
                                'kompensacja'  => 'rekompensata',
                            ];
                            echo htmlspecialchars($etykiety[$h['metoda']] ?? $h['metoda']);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($h['vip_do_po_zakupie']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="pusta">// brak zakupów //</div>
    <?php endif; ?>
</div>