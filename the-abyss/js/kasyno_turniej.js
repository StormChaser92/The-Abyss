/* the-abyss/js/kasyno_turniej.js
   Widok turnieju na żywo: odpytywanie stanu, akcje na turze, czat, zakłady.
   Cała logika walki jest po stronie serwera — tu tylko rysowanie. */
window.KasynoTurniej = (function () {
  'use strict';

  const API = 'api/kasyno_turniej.php';
  let TID = 0, stan = null, wybrany = 0, zajety = false, tykanie = null;

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => Number(n).toLocaleString('pl-PL').replace(/\u00a0/g, ' ');
  const esc = (s) => String(s).replace(/[<>&"]/g, (c) => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));

  async function woalj(a, dane, cicho) {
    if (zajety) return null;
    zajety = true;
    try {
      const r = await fetch(API + '?a=' + a, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ turniej: TID }, dane || {})),
      });
      const j = await r.json();
      if (!j.ok) { if (!cicho) mowa(j.blad || 'Nie wyszło.', 'err'); return null; }
      stan = j;
      rysuj();
      return j;
    } catch (e) {
      if (!cicho) mowa('Arena nie odpowiada.', 'err');
      return null;
    } finally { zajety = false; }
  }

  function mowa(tekst, klasa) {
    const b = $('tn-komunikat');
    if (!b) return;
    b.textContent = tekst;
    b.className = 'tn-komunikat' + (klasa ? ' ' + klasa : '');
    clearTimeout(b._t);
    b._t = setTimeout(() => { b.textContent = ''; b.className = 'tn-komunikat'; }, 6000);
  }

  /* ---------------------------- render --------------------------- */

  function rysuj() {
    if (!stan) return;
    const t = stan.turniej;

    $('tn-stan').textContent = ({
      ogloszony: 'Ogłoszony', zapisy: 'Zapisy otwarte', trwa: 'Walka trwa',
      zakonczony: 'Po wszystkim', odwolany: 'Odwołany',
    })[t.stan] || t.stan;
    $('tn-stan').className = 'tn-stan ' + t.stan;
    $('tn-tura').textContent = t.stan === 'trwa' ? 'Tura ' + t.tura : '—';
    $('tn-pula').textContent = fmt(t.pula) + ' $';
    $('tn-zetony').textContent = fmt(stan.zetony);

    rysujZawodnikow();
    rysujAkcje();
    rysujLog();
    rysujCzat();
    rysujZaklady();
  }

  function rysujZawodnikow() {
    const box = $('tn-zawodnicy');
    box.innerHTML = '';
    stan.zawodnicy.forEach((z) => {
      const d = document.createElement('div');
      let kl = 'tn-zaw';
      if (z.stan !== 'zywy') kl += ' padl';
      if (z.aktywny) kl += ' aktywny';
      if (z.ja) kl += ' ja';
      if (z.gracz_id === wybrany) kl += ' cel';
      d.className = kl;
      const proc = z.hp_max > 0 ? Math.max(0, Math.round(z.hp / z.hp_max * 100)) : 0;
      d.innerHTML =
        '<div class="gora"><b>' + esc(z.login) + '</b>' +
        (z.tag ? '<span class="tag">[' + esc(z.tag) + ']</span>' : '') +
        (z.postawa === 'unik' && z.stan === 'zywy' ? '<span class="postawa">kryje się</span>' : '') +
        '</div>' +
        '<div class="pasek"><i style="width:' + proc + '%"></i></div>' +
        '<div class="dol"><span>' + z.hp + ' / ' + z.hp_max + '</span><span>zadane ' + fmt(z.zadane) + '</span></div>' +
        (z.miejsce ? '<div class="miejsce">' + (z.miejsce === 1 ? 'zwycięzca' : 'miejsce ' + z.miejsce) + '</div>' : '');
      if (z.stan === 'zywy' && !z.ja) d.onclick = () => { wybrany = z.gracz_id; rysuj(); };
      box.appendChild(d);
    });
  }

  function rysujAkcje() {
    const box = $('tn-akcje');
    box.innerHTML = '';
    const t = stan.turniej;

    if (t.stan === 'zapisy' && !stan.jestem) {
      box.appendChild(przycisk('Zapisz się · wpisowe ' + fmt(t.wpisowe) + ' $',
        () => woalj('zapisz'), 'duzy'));
      return;
    }
    if (t.stan !== 'trwa') {
      box.innerHTML = '<span class="tn-czekaj">' +
        (t.stan === 'zakonczony' ? 'Arena posprzątana.' : 'Czekamy na pierwszy dzwonek.') + '</span>';
      return;
    }
    if (!stan.moja_tura) {
      const kto = stan.zawodnicy.find((z) => z.aktywny);
      box.innerHTML = '<span class="tn-czekaj">Ruch: <b>' + esc(kto ? kto.login : '—') + '</b>' +
        ' · ' + stan.sekundy + ' s</span>';
      return;
    }

    const atak = przycisk(wybrany ? 'Atakuj' : 'Wybierz cel', () => {
      if (!wybrany) { mowa('Kliknij zawodnika, którego chcesz uderzyć.', 'err'); return; }
      woalj('akcja', { ruch: 'atak', cel: wybrany });
    }, 'duzy');
    atak.disabled = !wybrany;
    box.appendChild(atak);
    box.appendChild(przycisk('Unik', () => woalj('akcja', { ruch: 'unik' })));
    box.appendChild(przycisk('Przeczekaj', () => woalj('akcja', { ruch: 'czekaj' })));
    const zegar = document.createElement('span');
    zegar.className = 'tn-zegar' + (stan.sekundy <= 10 ? ' pilne' : '');
    zegar.textContent = stan.sekundy + ' s';
    box.appendChild(zegar);
  }

  function rysujLog() {
    const box = $('tn-log');
    if (!box) return;
    box.innerHTML = stan.log.map((l) =>
      '<div class="lw ' + l.akcja + '"><span class="t">T' + l.tura + '</span>' +
      '<span class="c">' + esc(l.tresc) + '</span>' +
      (l.dmg > 0 ? '<span class="d">−' + l.dmg + '</span>' : '<span class="d"></span>') + '</div>'
    ).join('');
    box.scrollTop = box.scrollHeight;
  }

  function rysujCzat() {
    const box = $('tn-czat');
    if (!box) return;
    box.innerHTML = stan.czat.map((c) =>
      '<div><b>' + esc(c.login) + '</b> ' + esc(c.tresc) + '</div>'
    ).join('');
    box.scrollTop = box.scrollHeight;
  }

  function rysujZaklady() {
    const box = $('tn-zaklady');
    if (!box) return;
    const z = stan.zaklady;
    const t = stan.turniej;

    if (z.moj) {
      const na = stan.zawodnicy.find((x) => x.gracz_id === z.moj.na_kogo);
      let txt = 'Postawione <b>' + fmt(z.moj.stawka) + '</b> żetonów na <b>' +
                esc(na ? na.login : '—') + '</b>.';
      if (Number(z.moj.rozliczony)) {
        txt += Number(z.moj.wyplata) > 0
          ? ' Wypłata: <b class="plus">' + fmt(z.moj.wyplata) + '</b>.'
          : ' Zakład przepadł.';
      }
      box.innerHTML = '<p>' + txt + '</p><p class="nota">Pula zakładów: ' + fmt(z.pula) + ' żetonów.</p>';
      return;
    }

    if (t.stan !== 'zapisy' && t.stan !== 'ogloszony') {
      box.innerHTML = '<p class="nota">Zakłady zamknięte. Pula: ' + fmt(z.pula) + ' żetonów.</p>';
      return;
    }
    if (stan.jestem) {
      box.innerHTML = '<p class="nota">Zawodnik nie obstawia własnej walki.</p>';
      return;
    }

    const opcje = stan.zawodnicy.map((x) =>
      '<option value="' + x.gracz_id + '">' + esc(x.login) +
      (z.na[x.gracz_id] ? ' · ' + fmt(z.na[x.gracz_id]) + ' żet.' : '') + '</option>').join('');
    box.innerHTML =
      '<div class="tn-zaklad-form">' +
      '<select id="tn-na">' + (opcje || '<option value="">brak zawodników</option>') + '</select>' +
      '<input type="number" id="tn-stawka" min="100" step="100" value="1000">' +
      '<button class="tn-b" id="tn-postaw">Postaw</button>' +
      '</div><p class="nota">Pula: ' + fmt(z.pula) + ' żetonów · kasyno bierze 5%, reszta idzie do trafnych.</p>';
    $('tn-postaw').onclick = () => {
      const na = parseInt($('tn-na').value || '0', 10);
      const st = parseInt($('tn-stawka').value || '0', 10);
      if (!na) return;
      woalj('postaw', { na: na, stawka: st });
    };
  }

  function przycisk(txt, fn, klasa) {
    const b = document.createElement('button');
    b.className = 'tn-b' + (klasa ? ' ' + klasa : '');
    b.textContent = txt;
    b.onclick = fn;
    return b;
  }

  /* ---------------------------- pętla ---------------------------- */

  function tyknij() {
    if (!stan) return;
    if (stan.sekundy > 0) { stan.sekundy--; rysujAkcje(); }
  }

  function start(id) {
    TID = id;
    const wyslij = () => {
      const t = ($('tn-czat-input').value || '').trim();
      if (!t) return;
      $('tn-czat-input').value = '';
      woalj('czat', { tresc: t });
    };
    $('tn-czat-wyslij').onclick = wyslij;
    $('tn-czat-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') wyslij(); });

    woalj('stan');
    setInterval(() => woalj('stan', {}, true), 3000);
    tykanie = setInterval(tyknij, 1000);
  }

  return { start: start };
})();
