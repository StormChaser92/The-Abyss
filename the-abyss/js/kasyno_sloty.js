/* the-abyss/js/kasyno_sloty.js
   Klient slotów. Serwer przysyła gotową siatkę; ten plik animuje obrót
   bębnów i podświetla trafione linie. Nie losuje niczego. */
(function () {
'use strict';
const API = 'api/kasyno_sloty.php';
const $ = id => document.getElementById(id);
const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
const fmt = n => Number(n || 0).toLocaleString('pl-PL').replace(/,/g, ' ');

let CFG = null, linia = 10, kreci = false, ostatnia = null;

async function call(akcja, dane) {
  const res = await fetch(API + '?a=' + akcja, {
    method: dane === undefined ? 'GET' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: dane === undefined ? undefined : JSON.stringify(dane),
  });
  const j = await res.json().catch(() => ({ ok: false, blad: 'Serwer nie odpowiada (' + res.status + ')' }));
  if (!j.ok) throw new Error(j.blad || 'Błąd serwera');
  return j;
}

function komunikat(txt, rodzaj) {
  const el = $('sl-komunikat');
  if (!el) return;
  el.innerHTML = txt || '';
  el.className = 'sl-komunikat' + (txt ? ' widoczny' : '') + (rodzaj ? ' ' + rodzaj : '');
}

/* --------------------------- animacja bębnów ---------------------------
   Każdy bęben przewija losowe symbole, zatrzymuje się kolejno od lewej
   i dopiero na końcu pokazuje to, co przysłał serwer. */
function losowySymbol() {
  return CFG.symbole[Math.floor(Math.random() * 6)];   // bez wilda i scattera w rozmyciu
}

function rysujBeben(i, symbole, rozmyty) {
  const el = $('sl-b' + i);
  if (!el) return;
  el.className = 'sl-beben' + (rozmyty ? ' kreci' : '');
  el.innerHTML = symbole.map(s =>
    '<div class="sl-cela"><span>' + s + '</span></div>').join('');
}

function pokazSiatke(siatka) {
  for (let i = 0; i < 5; i++) {
    rysujBeben(i, siatka[i].map(idx => CFG.symbole[idx]), false);
  }
}

async function animujSpin(wynik) {
  const czasy = [420, 560, 700, 840, 1000];   // bębny stają kolejno
  // faza rozmycia
  for (let i = 0; i < 5; i++) rysujBeben(i, [losowySymbol(), losowySymbol(), losowySymbol()], true);
  const migacz = setInterval(() => {
    for (let i = 0; i < 5; i++) {
      const el = $('sl-b' + i);
      if (el && el.classList.contains('kreci'))
        rysujBeben(i, [losowySymbol(), losowySymbol(), losowySymbol()], true);
    }
  }, 90);

  await Promise.all(czasy.map((t, i) => new Promise(r => setTimeout(() => {
    rysujBeben(i, wynik.siatka[i].map(idx => CFG.symbole[idx]), false);
    r();
  }, t))));
  clearInterval(migacz);
}

/** Podświetla cele wchodzące w trafione linie. */
function podswietl(wynik) {
  document.querySelectorAll('.sl-cela').forEach(c => c.classList.remove('win', 'scat'));
  (wynik.trafione || []).forEach(t => {
    const wiersze = CFG.linie[t.linia];
    for (let r = 0; r < t.ile; r++) {
      const el = document.querySelector('#sl-b' + r + ' .sl-cela:nth-child(' + (wiersze[r] + 1) + ')');
      if (el) el.classList.add('win');
    }
  });
  if (wynik.scat >= 3) {
    for (let r = 0; r < 5; r++) {
      wynik.siatka[r].forEach((idx, k) => {
        if (idx === CFG.scat) {
          const el = document.querySelector('#sl-b' + r + ' .sl-cela:nth-child(' + (k + 1) + ')');
          if (el) el.classList.add('scat');
        }
      });
    }
  }
}

function opisWyniku(w) {
  if (w.jackpot > 0)
    return ['<b>PIĘĆ SMOKÓW.</b> Jackpot ' + fmt(w.jackpot) + ' żetonów.', 'jackpot'];
  const czesci = [];
  if (w.trafione && w.trafione.length) {
    const naj = w.trafione.reduce((a, b) => b.wygrana > a.wygrana ? b : a);
    czesci.push(CFG.symbole[naj.symbol] + ' ×' + naj.ile +
      (w.trafione.length > 1 ? ' i ' + (w.trafione.length - 1) + ' więcej' : ''));
  }
  if (w.scat >= 3) czesci.push('🌙 ×' + w.scat);
  if (!czesci.length) return ['', ''];
  return [czesci.join(' · ') + ' — <b>' + fmt(w.wyplata) + '</b> żetonów', 'dobry'];
}

/* ------------------------------ tabela ------------------------------ */
function podswietlTabele(symbol) {
  document.querySelectorAll('#sl-tabela tr').forEach(tr => {
    tr.classList.toggle('trafiony', symbol !== undefined && parseInt(tr.dataset.sym) === symbol);
  });
}

function rysujStawki() {
  const box = $('sl-stawki');
  if (!box) return;
  box.innerHTML = CFG.stawki.map(s =>
    '<button class="sl-s' + (s === linia ? ' on' : '') + '" data-stawka="' + s + '">' + fmt(s) + '</button>').join('');
  box.querySelectorAll('[data-stawka]').forEach(b => {
    b.onclick = () => { linia = parseInt(b.dataset.stawka); rysujStawki(); odswiezPrzycisk(); };
  });
}
function odswiezPrzycisk() {
  set('sl-calosc', fmt(linia * CFG.linie.length));
  const b = $('sl-spin');
  if (b) b.disabled = kreci;
}

/* ------------------------------- spin ------------------------------- */
async function spin() {
  if (kreci) return;
  kreci = true; odswiezPrzycisk(); komunikat('');
  podswietlTabele();
  document.querySelectorAll('.sl-cela').forEach(c => c.classList.remove('win', 'scat'));

  try {
    const klucz = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    const [j] = await Promise.all([
      call('spin', { linia, klucz }),
      new Promise(r => setTimeout(r, 250)),      // minimalny czas, żeby animacja miała sens
    ]);
    ostatnia = j;
    await animujSpin(j);
    podswietl(j);
    set('sl-gotowka', fmt(j.portfel.gotowka));
    set('sl-zetony', fmt(j.portfel.zetony));
    set('sl-jackpot', fmt(j.jackpot_pula));
    const [txt, rodzaj] = opisWyniku(j);
    komunikat(txt, rodzaj);
    if (j.trafione && j.trafione.length) {
      const naj = j.trafione.reduce((a, b) => b.wygrana > a.wygrana ? b : a);
      podswietlTabele(naj.symbol);
    }
  } catch (e) {
    komunikat(e.message, 'blad');
    if (ostatnia) pokazSiatke(ostatnia.siatka);
  } finally {
    kreci = false; odswiezPrzycisk();
  }
}

window.KasynoSloty = {
  async start() {
    try {
      CFG = await call('tabela');
      linia = CFG.stawki[0];
      rysujStawki();
      odswiezPrzycisk();
      set('sl-jackpot', fmt(CFG.jackpot.pula));
      // siatka startowa — spokojne symbole, bez sugerowania wygranej
      pokazSiatke([[0,1,2],[1,2,0],[2,0,1],[0,2,1],[1,0,2]]);
      const b = $('sl-spin');
      if (b) b.onclick = spin;
      document.addEventListener('keydown', ev => {
        if (ev.code === 'Space' && ev.target.tagName !== 'INPUT') { ev.preventDefault(); spin(); }
      });
      // pula rośnie od spinów innych graczy
      setInterval(async () => {
        if (kreci) return;
        try { const j = await call('jackpot'); set('sl-jackpot', fmt(j.jackpot.pula)); } catch (e) {}
      }, 20000);
    } catch (e) { komunikat(e.message, 'blad'); }
  },
};
})();
