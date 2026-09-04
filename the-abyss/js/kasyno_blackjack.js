/* the-abyss/js/kasyno_blackjack.js
   Klient stołu blackjacka. Cała logika kart jest po stronie serwera —
   tutaj tylko rysowanie stanu, zakład i skróty klawiszowe.

   Licznik Hi-Lo liczy karty, które gracz FAKTYCZNIE zobaczył: zakryta
   karta krupiera wchodzi do liczydła dopiero po odkryciu. */
window.KasynoBlackjack = (function () {
  'use strict';

  const API = 'api/kasyno_blackjack.php';
  const NOMINALY = [100, 500, 2500, 10000];
  const ZNAKI = { s: '♠', h: '♥', d: '♦', c: '♣' };
  const RANGI = { A: 'A', T: '10', J: 'J', Q: 'D', K: 'K' };

  let stawka = 0, nominal = 100, ostatnia = 0;
  let stan = null, zajety = false;
  let widziane = new Set(), liczba = 0, tasowan = -1;

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => Number(n).toLocaleString('pl-PL').replace(/\u00a0/g, ' ');

  async function woalj(a, dane) {
    if (zajety) return null;
    zajety = true;
    try {
      const r = await fetch(API + '?a=' + a, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dane || {}),
      });
      const j = await r.json();
      if (!j.ok) { mowa(j.blad || 'Coś poszło nie tak.', 'uwaga'); return null; }
      stan = j;
      rysuj();
      return j;
    } catch (e) {
      mowa('Stół nie odpowiada. Spróbuj jeszcze raz.', 'uwaga');
      return null;
    } finally { zajety = false; }
  }

  function mowa(tekst, klasa) {
    $('bj-mowa-tresc').innerHTML = tekst;
    $('bj-mowa').className = 'bj-panel bj-mowa' + (klasa ? ' ' + klasa : '');
  }

  /* ---------------------------- karty ---------------------------- */

  function karta(kod, nr, zakryta) {
    const d = document.createElement('div');
    if (zakryta) { d.className = 'bj-k rewers'; return d; }
    const r = kod[0], k = kod[1];
    d.className = 'bj-k' + (k === 'h' || k === 'd' ? ' czerwona' : '');
    d.innerHTML = '<div class="r">' + (RANGI[r] || r) + '</div><div class="s">' + ZNAKI[k] + '</div>';
    d.dataset.k = nr;
    return d;
  }

  function wartoscHiLo(kod) {
    const r = kod[0];
    if (r === 'A' || r === 'T' || r === 'J' || r === 'Q' || r === 'K') return -1;
    if (r === '7' || r === '8' || r === '9') return 0;
    return 1;
  }

  /* Liczydło: każda karta liczona raz, klucz = kod + pozycja w rozdaniu. */
  function policz(karty, prefiks) {
    karty.forEach((kod, i) => {
      const klucz = prefiks + i + kod;
      if (widziane.has(klucz)) return;
      widziane.add(klucz);
      liczba += wartoscHiLo(kod);
    });
  }

  /* ---------------------------- render --------------------------- */

  function rysuj() {
    if (!stan) return;
    const s = stan;

    if (s.portfel) {
      $('bj-gotowka').textContent = fmt(s.portfel.gotowka);
      $('bj-zetony').textContent = fmt(s.portfel.zetony);
    }

    if (s.but.tasowan !== tasowan) { tasowan = s.but.tasowan; widziane.clear(); liczba = 0; }

    // krupier
    const dealer = $('bj-dealer');
    dealer.innerHTML = '';
    (s.dealer.karty || []).forEach((k, i) => dealer.appendChild(karta(k, i, false)));
    if (s.dealer.zakryta) dealer.appendChild(karta('', 99, true));
    policz(s.dealer.karty || [], 'd');

    const dpkt = $('bj-dealer-pkt');
    if (!s.dealer.karty || !s.dealer.karty.length) { dpkt.textContent = '—'; dpkt.className = 'bj-pkt'; }
    else {
      const p = s.dealer.pelna !== null && s.dealer.pelna !== undefined ? s.dealer.pelna : s.dealer.suma;
      dpkt.textContent = s.dealer.zakryta ? p + ' +' : p;
      dpkt.className = 'bj-pkt' + (p > 21 ? ' bust' : (p === 21 && s.stan === 'koniec' && s.dealer.karty.length === 2 ? ' bj' : ''));
    }

    // ręce gracza
    const box = $('bj-rece');
    box.innerHTML = '';
    (s.rece || []).forEach((r, nr) => {
      policz(r.karty, 'g' + nr);
      const d = document.createElement('div');
      let kl = 'bj-reka';
      if (r.aktywna) kl += ' aktywna';
      if (r.wynik === 'wygrana' || r.wynik === 'blackjack') kl += ' wygrana';
      if (r.wynik === 'przegrana' || r.wynik === 'fura') kl += ' przegrana';
      d.className = kl;

      const karty = document.createElement('div');
      karty.className = 'bj-karty';
      r.karty.forEach((k, i) => karty.appendChild(karta(k, i, false)));
      d.appendChild(karty);

      const pkt = document.createElement('div');
      pkt.className = 'bj-pkt' + (r.suma > 21 ? ' bust' : (r.bj ? ' bj' : ''));
      pkt.textContent = r.bj ? 'BLACKJACK' : (r.suma > 21 ? r.suma + ' fura' : (r.miekka ? r.suma + ' miękkie' : r.suma));
      d.appendChild(pkt);

      const st = document.createElement('div');
      st.className = 'stawka';
      st.textContent = fmt(r.stawka) + (s.ubezpieczenie && nr === 0 ? ' + ' + fmt(s.ubezpieczenie) + ' ubezp.' : '');
      d.appendChild(st);

      const wy = document.createElement('div');
      const netto = r.wyplata - r.stawka;
      wy.className = 'wynik' + (r.wynik === 'blackjack' ? ' bj' : (netto > 0 ? ' plus' : (netto < 0 ? ' minus' : '')));
      wy.textContent = r.wynik ? opis(r.wynik, netto) : '';
      d.appendChild(wy);

      box.appendChild(d);
    });

    if (!s.rece || !s.rece.length) {
      box.innerHTML = '<div class="bj-reka"><div class="bj-karty"></div>' +
        '<div class="bj-pkt">—</div><div class="stawka">' + fmt(stawka) + '</div><div class="wynik"></div></div>';
    }

    rysujBut();
    rysujHistorie();
    rysujPrzyciski();
    rysujPodpowiedz();
    $('bj-stawka').textContent = fmt(s.stan === 'brak' || s.stan === 'koniec' ? stawka : s.stawka);
  }

  function opis(wynik, netto) {
    if (wynik === 'blackjack') return 'Blackjack +' + fmt(netto);
    if (wynik === 'wygrana')   return 'Wygrana +' + fmt(netto);
    if (wynik === 'remis')     return 'Remis';
    if (wynik === 'fura')      return 'Fura −' + fmt(-netto);
    return 'Przegrana −' + fmt(-netto);
  }

  function rysujBut() {
    const b = stan.but;
    const proc = Math.round(b.zostalo / b.wszystkich * 100);
    $('bj-but-pasek').style.width = proc + '%';
    let txt = '<b>' + b.zostalo + '</b> z ' + b.wszystkich + ' kart · tasowań: <b>' + b.tasowan + '</b>';
    if ($('bj-licznik').checked) {
      const talie = Math.max(0.5, b.zostalo / 52);
      const prawdziwa = liczba / talie;
      txt += '<br>Hi-Lo: <b>' + (liczba > 0 ? '+' : '') + liczba + '</b> · na talię <b>' +
             (prawdziwa > 0 ? '+' : '') + prawdziwa.toFixed(1) + '</b>';
    }
    $('bj-but-info').innerHTML = txt;
  }

  function rysujHistorie() {
    const box = $('bj-historia');
    const h = stan.historia || [];
    if (!h.length) { box.innerHTML = '<span class="bj-puste">jeszcze nic</span>'; return; }
    box.innerHTML = h.map((r) => {
      const n = r.wyplata - r.stawka;
      const kl = n > 0 ? 'plus' : (n === 0 ? 'rem' : 'minus');
      return '<div><span>' + r.gracz_pkt + ' vs ' + r.dealer_pkt + '</span><span class="' + kl + '">' +
             (n > 0 ? '+' : '') + fmt(n) + '</span></div>';
    }).join('');
  }

  function przycisk(txt, fn, klasa, tytul) {
    const b = document.createElement('button');
    b.className = 'bj-b' + (klasa ? ' ' + klasa : '');
    b.textContent = txt;
    if (tytul) b.title = tytul;
    b.onclick = fn;
    return b;
  }

  function rysujPrzyciski() {
    const box = $('bj-btns');
    box.innerHTML = '';
    const s = stan;

    if (s.stan === 'ubezpieczenie') {
      box.appendChild(przycisk('Ubezpiecz ' + fmt(Math.floor(s.stawka / 2)), () => woalj('ubezpiecz', { tak: true }), 'zloty'));
      box.appendChild(przycisk('Bez ubezpieczenia', () => woalj('ubezpiecz', { tak: false }), 'duzy'));
      mowa('As na wierzchu. Ubezpieczenie płaci <b>2:1</b>, ale na dłuższą metę to zakład dla kasyna.', 'uwaga');
      return;
    }

    if (s.stan === 'gra') {
      const m = s.mozliwosci || [];
      if (m.includes('podwoj')) box.appendChild(przycisk('Podwój', () => woalj('podwoj'), '', 'Klawisz P'));
      if (m.includes('split'))  box.appendChild(przycisk('Dziel', () => woalj('split'), '', 'Klawisz X'));
      if (m.includes('stoj'))   box.appendChild(przycisk('Stój', () => woalj('stoj'), '', 'Klawisz S'));
      if (m.includes('dobierz'))box.appendChild(przycisk('Dobierz', () => woalj('dobierz'), 'duzy', 'Klawisz D'));
      if (!m.length) mowa('Krupier dobiera…');
      else if (s.rece.length > 1) mowa('Ręka <b>' + (s.rece.findIndex((r) => r.aktywna) + 1) + '</b> z ' + s.rece.length + '. Twój ruch.');
      else mowa('Twój ruch.');
      return;
    }

    // brak / koniec — obstawianie
    box.appendChild(przycisk('Wyczyść', () => { stawka = 0; rysuj(); }));
    if (ostatnia > 0) box.appendChild(przycisk('Powtórz ' + fmt(ostatnia), () => { stawka = ostatnia; rysuj(); }));
    const graj = przycisk(s.stan === 'koniec' ? 'Następne rozdanie' : 'Rozdaj', rozdaj, 'duzy', 'Spacja');
    graj.disabled = stawka <= 0;
    box.appendChild(graj);

    if (s.stan === 'koniec') podsumuj();
    else if (stawka <= 0) mowa('Zakład proszę. Minimum ' + fmt(stan.limity.min) + ' żetonów.');
    else mowa('Zakład <b>' + fmt(stawka) + '</b>. Rozdaję?');

    document.querySelectorAll('.bj-chip').forEach((c) => {
      c.disabled = stawka + Number(c.dataset.v) > Math.min(stan.limity.max, stan.portfel.zetony);
    });
  }

  function podsumuj() {
    const s = stan;
    let stakeAll = s.ubezpieczenie, wyplata = 0;
    s.rece.forEach((r) => { stakeAll += r.stawka; wyplata += r.wyplata; });
    if (s.ubezpieczenie && s.dealer.karty.length === 2 && s.dealer.pelna === 21) wyplata += s.ubezpieczenie * 3;
    const netto = wyplata - stakeAll;
    const dpkt = s.dealer.pelna;
    let t = 'Krupier ma <b>' + dpkt + '</b>' + (dpkt > 21 ? ' — fura' : '') + '. ';
    if (netto > 0)      { t += 'Wypłata <b>' + fmt(wyplata) + '</b> żetonów, na czysto <b>+' + fmt(netto) + '</b>.'; mowa(t, 'wygrana'); }
    else if (netto === 0) { t += 'Zakład wraca do ciebie.'; mowa(t); }
    else                { t += 'Tracisz <b>' + fmt(-netto) + '</b> żetonów.'; mowa(t, 'przegrana'); }
  }

  function rysujPodpowiedz() {
    const p = $('bj-podpowiedz');
    p.innerHTML = stan.podpowiedz
      ? 'Strategia podstawowa mówi: <b>' + stan.podpowiedz + '</b>'
      : '';
  }

  /* --------------------------- akcje ----------------------------- */

  async function rozdaj() {
    if (stawka <= 0) return;
    ostatnia = stawka;
    const suma = stawka;
    stawka = 0;                       // zerowanie PRZED renderem, inaczej DOM i moduł się rozjeżdżają
    const j = await woalj('rozdaj', { stawka: suma });
    if (!j) { stawka = suma; rysuj(); }
  }

  function nominaly() {
    const box = $('bj-nominaly');
    box.innerHTML = '';
    NOMINALY.forEach((v, i) => {
      const b = document.createElement('button');
      b.className = 'bj-chip n' + i;
      b.dataset.v = v;
      b.textContent = v >= 1000 ? String(v / 1000).replace('.', ',') + 'k' : v;
      b.onclick = () => {
        if (stan && (stan.stan === 'gra' || stan.stan === 'ubezpieczenie')) return;
        nominal = v;
        stawka = Math.min(stawka + v, stan ? Math.min(stan.limity.max, stan.portfel.zetony) : v);
        rysuj();
      };
      box.appendChild(b);
    });
  }

  function klawisze(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (!stan) return;
    const m = stan.mozliwosci || [];
    const k = e.key.toLowerCase();
    if (stan.stan === 'gra') {
      if (k === 'd' && m.includes('dobierz')) { e.preventDefault(); woalj('dobierz'); }
      if (k === 's' && m.includes('stoj'))    { e.preventDefault(); woalj('stoj'); }
      if (k === 'p' && m.includes('podwoj'))  { e.preventDefault(); woalj('podwoj'); }
      if (k === 'x' && m.includes('split'))   { e.preventDefault(); woalj('split'); }
    } else if (e.code === 'Space' && stawka > 0) { e.preventDefault(); rozdaj(); }
  }

  function start() {
    nominaly();
    $('bj-licznik').onchange = () => { if (stan) rysujBut(); };
    $('bj-tasuj').onclick = () => woalj('tasuj');
    document.addEventListener('keydown', klawisze);
    woalj('stan').then(() => {
      if (stan && stan.stan === 'koniec') { /* poprzednia ręka wciąż na stole */ }
    });
  }

  return { start: start };
})();
