<?php
/* the-abyss/includes/rynek_odczynniki.php
   Stragan z odczynnikami — nowa kategoria towaru na rynku. Handlarz jest
   NPC, bo warka musi mieć źródło składników; kupione odczynniki lądują
   w przedmioty_gracze i można je potem odsprzedać graczom normalną ofertą.

   Zapas odnawia się co dobę. Płacić można z prywatnej gotówki albo ze
   skarbca gangu — o ile kupujący ma do niego dostęp.

   Wpięcie w pages/rynek.php: dodaj zakładkę i w jej gałęzi wywołaj
     require_once "includes/rynek_odczynniki.php";
     echo odczynniki_obsluz($polaczenie, $id_gracza);   // przed renderem
     odczynniki_render($polaczenie, $id_gracza);        // w miejscu listy
*/

/** Odnowienie dobowego zapasu przy pierwszym wejściu danego dnia. */
function odczynniki_odswiez(mysqli $db): void {
    $db->query("UPDATE rynek_odczynniki SET sprzedane=0, dzien=CURDATE() WHERE dzien <> CURDATE()");
}

function odczynniki_lista(mysqli $db): array {
    odczynniki_odswiez($db);
    $out = [];
    $r = $db->query("SELECT * FROM rynek_odczynniki ORDER BY cena ASC");
    while ($r && ($w = $r->fetch_assoc())) $out[] = $w;
    return $out;
}

/** Obsługa zakupu. Zwraca gotowy komunikat HTML albo pusty łańcuch. */
function odczynniki_obsluz(mysqli $db, int $gracz_id): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['kup_odczynnik'])) return '';

    $id    = (int)($_POST['odczynnik_id'] ?? 0);
    $ile   = max(1, min(20, (int)($_POST['ilosc'] ?? 1)));
    $skad  = ($_POST['zrodlo'] ?? 'prywatnie') === 'skarbiec' ? 'skarbiec' : 'prywatnie';

    odczynniki_odswiez($db);
    $r = $db->query("SELECT * FROM rynek_odczynniki WHERE id=$id");
    if (!$r || !$r->num_rows) return "<div class='blad'>Handlarz nie ma takiego towaru.</div>";
    $o = $r->fetch_assoc();

    $zostalo = (int)$o['zapas'] - (int)$o['sprzedane'];
    if ($zostalo < $ile)
        return "<div class='blad'>Handlarz ma dziś tylko $zostalo sztuk. Wróć jutro.</div>";

    $koszt = (int)$o['cena'] * $ile;
    $g = $db->query("SELECT gotowka, syndykat_id, syndykat_dostep_skarbiec FROM gracze WHERE id=$gracz_id")->fetch_assoc();

    if ($skad === 'skarbiec') {
        $syn = (int)$g['syndykat_id'];
        if ($syn <= 0)  return "<div class='blad'>Nie masz gangu, więc i skarbca.</div>";
        if (!(int)$g['syndykat_dostep_skarbiec'])
            return "<div class='blad'>Do skarbca sięga tylko lider.</div>";
        require_once __DIR__.'/melina.php';
        [$ok, $blad] = melina_kasa($db, $syn, $gracz_id, -$koszt, 'Odczynniki: '.$o['nazwa']." ×$ile");
        if (!$ok) return "<div class='blad'>".htmlspecialchars($blad)."</div>";
    } else {
        if ((int)$g['gotowka'] < $koszt)
            return "<div class='blad'>Za mało gotówki. Potrzebujesz ".number_format($koszt, 0, '', ' ')." $.</div>";
        $db->query("UPDATE gracze SET gotowka=gotowka-$koszt WHERE id=$gracz_id");
    }

    $db->query("UPDATE rynek_odczynniki SET sprzedane=sprzedane+$ile WHERE id=$id");
    $n = $db->real_escape_string($o['nazwa']);
    $ch = $db->query("SELECT id FROM przedmioty_gracze WHERE gracz_id=$gracz_id AND nazwa='$n'");
    if ($ch && $ch->num_rows)
        $db->query("UPDATE przedmioty_gracze SET ilosc=ilosc+$ile WHERE gracz_id=$gracz_id AND nazwa='$n'");
    else
        $db->query("INSERT INTO przedmioty_gracze (gracz_id, nazwa, ilosc) VALUES ($gracz_id, '$n', $ile)");

    return "<div class='sukces'>Kupione: ".htmlspecialchars($o['nazwa'])." ×$ile za "
         . number_format($koszt, 0, '', ' ')." $. Handlarz nie wystawia paragonu.</div>";
}

/** Render straganu. Styl dziedziczony po rynku — bez własnych kolorów. */
function odczynniki_render(mysqli $db, int $gracz_id): void {
    $lista = odczynniki_lista($db);
    $g = $db->query("SELECT syndykat_id, syndykat_dostep_skarbiec FROM gracze WHERE id=$gracz_id")->fetch_assoc();
    $ma_skarbiec = (int)$g['syndykat_id'] > 0 && (int)$g['syndykat_dostep_skarbiec'];
    ?>
    <p style="color:#888;margin-bottom:16px">
      Chemia pod warkę substancji. Handlarz przywozi ograniczony zapas na dobę i nie pyta, do czego to idzie.
      Kupione odczynniki lądują w twoich łupach — możesz je też odsprzedać graczom normalną ofertą.
    </p>
    <table class="rynek-tabela">
      <tr><th>Odczynnik</th><th>Cena</th><th>Zapas dziś</th><th style="text-align:right">Kup</th></tr>
      <?php foreach ($lista as $o):
        $zostalo = max(0, (int)$o['zapas'] - (int)$o['sprzedane']); ?>
        <tr>
          <td>
            <b><?php echo htmlspecialchars($o['nazwa']); ?></b><br>
            <span style="color:#777;font-size:.88em"><?php echo htmlspecialchars($o['opis']); ?></span>
          </td>
          <td><?php echo number_format((int)$o['cena'], 0, '', ' '); ?> $</td>
          <td><?php echo $zostalo; ?> szt.</td>
          <td style="text-align:right">
            <?php if ($zostalo > 0): ?>
            <form method="POST" style="display:flex;gap:6px;justify-content:flex-end;align-items:center;margin:0">
              <input type="hidden" name="odczynnik_id" value="<?php echo (int)$o['id']; ?>">
              <input type="number" name="ilosc" value="1" min="1" max="<?php echo min(20, $zostalo); ?>" class="input-text" style="width:70px;margin:0">
              <select name="zrodlo" class="input-select" style="width:auto;margin:0">
                <option value="prywatnie">z gotówki</option>
                <?php if ($ma_skarbiec): ?><option value="skarbiec">ze skarbca</option><?php endif; ?>
              </select>
              <button type="submit" name="kup_odczynnik" class="btn-kup">Kup</button>
            </form>
            <?php else: ?>
              <span style="color:#777">wyprzedane</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php
}
