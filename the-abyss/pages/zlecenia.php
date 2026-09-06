<?php
/* the-abyss/pages/zlecenia.php
   Strona dla klientów: lista Inżynierów z cennikiem i składanie zleceń.
   Ulepszać broń może tylko Inżynier — tu reszta gry zamawia i płaci z góry. */

require_once "db.php";
require_once "includes/bronie_katalog.php";
require_once "includes/warsztat_logika.php";

$id_gracza = $_SESSION['id_gracza'];
$komunikat = "";

$gracz = $polaczenie->query("SELECT id, login, poziom, gotowka, obecne_miasto FROM gracze WHERE id=$id_gracza")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['zloz'])) {
    [$ok, $tekst] = zlecenie_zloz($polaczenie, $gracz, (int)($_POST['inz'] ?? 0), $_POST['kod'] ?? '', $_POST['typ'] ?? 'wytworz');
    $komunikat = "<div class='".($ok ? 'sukces' : 'blad')."'>$tekst</div>";
    $gracz = $polaczenie->query("SELECT id, login, poziom, gotowka, obecne_miasto FROM gracze WHERE id=$id_gracza")->fetch_assoc();
}

$kat  = bronie_katalog();
$moje = eq_stan($polaczenie, $id_gracza);

/* Inżynierowie, którzy mają cokolwiek w cenniku. */
$inzynierowie = [];
$q = $polaczenie->query("SELECT g.id, g.login, g.poziom, g.obecne_miasto, g.umiejetnosc_inzynierii,
                                COUNT(c.id) AS pozycje
                         FROM gracze g JOIN warsztat_cennik c ON c.inzynier_id = g.id
                         WHERE g.klasa = 'Inżynier'
                         GROUP BY g.id
                         ORDER BY g.umiejetnosc_inzynierii DESC");
while ($q && $w = $q->fetch_assoc()) $inzynierowie[] = $w;

/* Moje zlecenia w toku i rozliczone. */
$moje_zlecenia = [];
$q = $polaczenie->query("SELECT z.*, g.login AS inzynier
                         FROM warsztat_zlecenia z JOIN gracze g ON g.id = z.inzynier_id
                         WHERE z.klient_id = $id_gracza
                         ORDER BY z.kiedy DESC LIMIT 20");
while ($q && $w = $q->fetch_assoc()) $moje_zlecenia[] = $w;
?>

<style>
    .zl-header { background: linear-gradient(to right, rgba(30,20,0,.85), rgba(0,0,0,.9)); padding: 34px; border: 1px solid rgba(255,170,0,.35); border-radius: 8px; margin-bottom: 22px; }
    .zl-header h1 { font-family: 'Oswald', sans-serif; color: #ffaa00; font-size: 2.6em; margin: 0; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 0 15px rgba(255,170,0,.45); }
    .zl-header p { color: #ccc; margin: 10px 0 0; }
    .kasa-pas { background: #111; border: 1px solid #333; border-radius: 4px; padding: 16px 20px; margin-bottom: 24px; font-family: 'Oswald', sans-serif; color: #aaa; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .kasa-pas b { color: #fff; }
    .sekcja-tytul { color: #ffaa00; font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: 1.35em; border-bottom: 1px dashed rgba(255,170,0,.3); padding-bottom: 10px; margin: 30px 0 16px; letter-spacing: 1px; display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .sekcja-tytul small { color: #666; font-size: .6em; letter-spacing: 1px; }
    .inz-karta { background: rgba(12,12,12,.65); border: 1px solid rgba(255,255,255,.06); border-radius: 8px; padding: 20px 22px; margin-bottom: 16px; }
    .inz-gora { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; flex-wrap: wrap; border-bottom: 1px solid rgba(255,255,255,.06); padding-bottom: 12px; margin-bottom: 14px; }
    .inz-nazwa { font-family: 'Oswald', sans-serif; font-size: 1.25em; color: #fff; text-transform: uppercase; letter-spacing: 1px; }
    .inz-meta { color: #888; font-size: .9em; }
    .inz-meta b { color: #00ff00; }
    .cennik-tabela { width: 100%; border-collapse: collapse; font-size: .95em; }
    .cennik-tabela th { font-family: 'Oswald', sans-serif; text-transform: uppercase; font-size: .7em; letter-spacing: 1px; color: #ffaa00; text-align: left; padding: 8px; border-bottom: 1px solid rgba(255,170,0,.25); }
    .cennik-tabela td { padding: 9px 8px; border-bottom: 1px solid rgba(255,255,255,.05); vertical-align: middle; }
    .cennik-tabela tr:hover td { background: rgba(255,170,0,.04); }
    .cena { font-family: monospace; color: #ffd700; white-space: nowrap; }
    .usluga { color: #888; font-size: .9em; white-space: nowrap; }
    .btn-zamow { background: transparent; color: #ffaa00; border: 1px solid #ffaa00; padding: 7px 14px; font-family: 'Oswald', sans-serif; cursor: pointer; text-transform: uppercase; border-radius: 3px; white-space: nowrap; }
    .btn-zamow:hover:not(:disabled) { background: #ffaa00; color: #000; }
    .btn-disabled { background: #1a1a1a !important; color: #555 !important; border-color: #333 !important; cursor: not-allowed; }
    .st-oczekuje { color: #ffd700; }
    .st-sukces   { color: #5aff9a; }
    .st-porazka  { color: #ff5566; }
    .sukces { background: rgba(0,255,0,.1); border: 1px solid rgba(0,255,0,.3); color: #00ff00; padding: 16px; margin-bottom: 20px; text-align: center; border-radius: 4px; }
    .blad { background: rgba(255,51,51,.1); border: 1px solid rgba(255,51,51,.3); color: #ff3333; padding: 16px; margin-bottom: 20px; text-align: center; border-radius: 4px; }
    .pusto { color: #888; font-style: italic; }
</style>

<div class="zl-header">
    <h1>Zlecenia u Inżyniera</h1>
    <p>Ulepszać broń umie tylko Inżynier z warsztatem. Płacisz z góry &mdash; jeśli mu nie wyjdzie, pieniądze wracają, ale broń schodzi o stopień.</p>
</div>

<?php echo $komunikat; ?>

<div class="kasa-pas">
    <div>Kasa: <b style="color:#00ff00;"><?php echo number_format((int)$gracz['gotowka'], 0, '', ' '); ?> $</b></div>
    <div>Poziom: <b><?php echo (int)$gracz['poziom']; ?></b></div>
    <div>Miasto: <b><?php echo htmlspecialchars($gracz['obecne_miasto'] ?? '—'); ?></b></div>
</div>

<div class="sekcja-tytul">🔧 Warsztaty <small>sortowane po rusznikarstwie &mdash; wyższe znaczy mniejsze ryzyko porażki</small></div>

<?php if (empty($inzynierowie)): ?>
    <p class="pusto">Żaden Inżynier nie wystawił jeszcze cennika. Bez cennika nie da się nic zamówić.</p>
<?php else: foreach ($inzynierowie as $inz):
    $cennik = warsztat_cennik($polaczenie, (int)$inz['id']);
    if (empty($cennik)) continue;
?>
    <div class="inz-karta">
        <div class="inz-gora">
            <div class="inz-nazwa"><?php echo htmlspecialchars($inz['login']); ?></div>
            <div class="inz-meta">
                Rusznikarstwo: <b><?php echo number_format((float)$inz['umiejetnosc_inzynierii'], 2); ?></b>
                &middot; poziom <?php echo (int)$inz['poziom']; ?>
                &middot; <?php echo htmlspecialchars($inz['obecne_miasto'] ?? '—'); ?>
            </div>
        </div>
        <table class="cennik-tabela">
            <thead><tr><th>Broń</th><th>Usługa</th><th>Cena</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cennik as $typ => $poz): foreach ($poz as $kod => $cena):
                if (!isset($kat[$kod])) continue;
                $b = $kat[$kod];
                $ulepsz = ($typ === 'ulepsz');
                $mam    = isset($moje[$kod]) ? (int)$moje[$kod]['ilosc'] : 0;
                $sp     = isset($moje[$kod]) ? (int)$moje[$kod]['stopien'] : 0;
                $stac   = (int)$gracz['gotowka'] >= (int)$cena;
                $blok   = null;
                if (!$stac)                                  $blok = 'Brak $';
                elseif ($ulepsz && $mam < 1)                 $blok = 'Nie masz jej';
                elseif ($ulepsz && $sp >= WARSZTAT_STOPIEN_MAX) $blok = 'Już +'.WARSZTAT_STOPIEN_MAX;
                elseif ((int)$gracz['poziom'] < (int)$b['poziom']) $blok = 'Za niski lvl';
            ?>
                <tr>
                    <td>
                        <?php echo $b['ikona'].' '.htmlspecialchars($b['nazwa']); ?>
                        <?php if ($ulepsz && $mam > 0): ?>
                            <span style="color:#666; font-size:.9em;">(masz +<?php echo $sp; ?> → <?php echo bron_atak($b, $sp); ?> atk)</span>
                        <?php else: ?>
                            <span style="color:#666; font-size:.9em;">lvl <?php echo (int)$b['poziom']; ?> · +<?php echo (int)$b['atak']; ?> atk</span>
                        <?php endif; ?>
                    </td>
                    <td class="usluga"><?php echo $ulepsz ? 'ulepszenie o stopień' : 'wytworzenie'; ?></td>
                    <td class="cena"><?php echo number_format((int)$cena, 0, '', ' '); ?> $</td>
                    <td style="text-align:right;">
                        <?php if ($blok): ?>
                            <button class="btn-zamow btn-disabled" disabled><?php echo htmlspecialchars($blok); ?></button>
                        <?php else: ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="inz" value="<?php echo (int)$inz['id']; ?>">
                                <input type="hidden" name="kod" value="<?php echo htmlspecialchars($kod); ?>">
                                <input type="hidden" name="typ" value="<?php echo htmlspecialchars($typ); ?>">
                                <button type="submit" name="zloz" class="btn-zamow">Zamów</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; endif; ?>

<div class="sekcja-tytul">📜 Moje zlecenia</div>
<?php if (empty($moje_zlecenia)): ?>
    <p class="pusto">Nic jeszcze nie zamawiałeś.</p>
<?php else: ?>
    <table class="cennik-tabela">
        <thead><tr><th>Inżynier</th><th>Broń</th><th>Usługa</th><th>Cena</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($moje_zlecenia as $z):
            $b = $kat[$z['kod']] ?? null;
            $status = $z['status'] === 'oczekuje' ? '<span class="st-oczekuje">w kolejce</span>'
                    : ($z['wynik'] === 'sukces' ? '<span class="st-sukces">gotowe</span>'
                    : '<span class="st-porazka">nie wyszło · kasa zwrócona</span>');
        ?>
            <tr>
                <td><?php echo htmlspecialchars($z['inzynier']); ?></td>
                <td><?php echo $b ? htmlspecialchars($b['nazwa']) : htmlspecialchars($z['kod']); ?></td>
                <td class="usluga"><?php echo $z['typ'] === 'ulepsz' ? 'ulepszenie' : 'wytworzenie'; ?></td>
                <td class="cena"><?php echo number_format((int)$z['cena'], 0, '', ' '); ?> $</td>
                <td><?php echo $status; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
