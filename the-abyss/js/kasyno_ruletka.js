/* the-abyss/js/kasyno_ruletka.js
   Stół ruletki: układ pól, żetony, rozstrzygnięcie.

   Definicje zakładów (156 typów) przychodzą z serwera — ten plik nie zna
   zasad, tylko rysuje pola i wysyła, co gracz postawił. Numer losuje
   serwer; koło jest animowane pod znany wynik. */
(function () {
'use strict';
const API = 'api/kasyno_ruletka.php';
const $ = id => document.getElementById(id);
const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
const fmt = n => Number(n || 0).toLocaleString('pl-PL').replace(/,/g, ' ');
const num = (c, r) => 3 * c + (3 - r);

let CFG = null, kolo = null, nominal = 25, kreci = false;
let zaklady = {}, stos = [], poprzednie = null;
const pola = {};          // klucz zakładu -> element
let historia = { ostatnie: [], gorace: [] };

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

function dealer(txt, rodzaj) {
  const el = $('rl-dealer');
  if (!el) return;
  el.innerHTML = txt || '';
  el.className = 'rl-dealer' + (rodzaj ? ' ' + rodzaj : '');
}

/* ============================ BUDOWA STOŁU ============================ */
function pole(rodzic, klucz, klasa, tekst, styl) {
  const el = document.createElement('div');
  el.className = klasa;
  el.dataset.klucz = klucz;
  if (tekst !== undefined && tekst !== null) el.innerHTML = '<span class="rl-etykieta">' + tekst + '</span>';
  if (styl) Object.assign(el.style, styl);
  rodzic.appendChild(el);
  pola[klucz] = el;
  return el;
}

function budujStol() {
  const czerwone = CFG.czerwone;

  // zero
  const zero = $('rl-zero');
  zero.innerHTML = '';
  pole(zero, 'n0', 'rl-num rl-zielone rl-n0', '0');

  // 36 liczb, układ 12 kolumn × 3 rzędy
  const siatka = $('rl-numery');
  siatka.innerHTML = '';
  for (let r = 0; r < 3; r++) for (let c = 0; c < 12; c++) {
    const n = num(c, r);
    pole(siatka, 'n' + n, 'rl-num ' + (czerwone.includes(n) ? 'rl-czerwone' : 'rl-czarne'), n);
  }

  // ulice i szóstki — pasek nad liczbami
  const gora = $('rl-ulice');
  gora.innerHTML = '';
  for (let c = 0; c < 12; c++) {
    pole(gora, 't' + (3 * c + 1), 'rl-hot rl-ulica', null,
      { left: ((c + 0.5) / 12 * 100) + '%' });
  }
  for (let c = 0; c < 11; c++) {
    pole(gora, 'l' + (3 * c + 1), 'rl-hot rl-szostka', null,
      { left: ((c + 1) / 12 * 100) + '%' });
  }

  // splity i narożniki — nakładka na obszar liczb
  const nak = $('rl-splity');
  nak.innerHTML = '';
  for (let c = 0; c < 11; c++) for (let r = 0; r < 3; r++) {
    const a = num(c, r), b = num(c + 1, r);
    pole(nak, 's' + Math.min(a, b) + '-' + Math.max(a, b), 'rl-hot rl-split-h', null,
      { left: ((c + 1) / 12 * 100) + '%', top: ((r + 0.5) / 3 * 100) + '%' });
  }
  for (let c = 0; c < 12; c++) for (let r = 0; r < 2; r++) {
    const a = num(c, r), b = num(c, r + 1);
    pole(nak, 's' + Math.min(a, b) + '-' + Math.max(a, b), 'rl-hot rl-split-v', null,
      { left: ((c + 0.5) / 12 * 100) + '%', top: ((r + 1) / 3 * 100) + '%' });
  }
  for (let c = 0; c < 11; c++) for (let r = 0; r < 2; r++) {
    const ns = [num(c, r), num(c + 1, r), num(c, r + 1), num(c + 1, r + 1)];
    pole(nak, 'c' + Math.min.apply(null, ns), 'rl-hot rl-naroznik', null,
      { left: ((c + 1) / 12 * 100) + '%', top: ((r + 1) / 3 * 100) + '%' });
  }
  // splity i trio z zerem — lewa krawędź obszaru liczb
  [[0, 3], [1, 2], [2, 1]].forEach(([r, n]) => {
    pole(nak, 's0-' + n, 'rl-hot rl-split-h', null,
      { left: '0%', top: ((r + 0.5) / 3 * 100) + '%' });
  });
  pole(nak, 'tr0-2-3', 'rl-hot rl-naroznik', null, { left: '0%', top: (1 / 3 * 100) + '%' });
  pole(nak, 'tr0-1-2', 'rl-hot rl-naroznik', null, { left: '0%', top: (2 / 3 * 100) + '%' });

  // kolumny 2:1
  const kol = $('rl-kolumny');
  kol.innerHTML = '';
  for (let k = 3; k >= 1; k--) pole(kol, 'col' + k, 'rl-zewn rl-kol', '2:1');

  // tuziny
  const tuz = $('rl-tuziny');
  tuz.innerHTML = '';
  ['1–12', '13–24', '25–36'].forEach((t, i) => pole(tuz, 'd' + (i + 1), 'rl-zewn', t));

  // proste szanse
  const pr = $('rl-proste');
  pr.innerHTML = '';
  pole(pr, 'low', 'rl-zewn', '1–18');
  pole(pr, 'even', 'rl-zewn', 'PARZYSTE');
  pole(pr, 'red', 'rl-zewn rl-p-red', '<i class="rl-romb"></i>');
  pole(pr, 'black', 'rl-zewn rl-p-black', '<i class="rl-romb"></i>');
  pole(pr, 'odd', 'rl-zewn', 'NIEPARZYSTE');
  pole(pr, 'high', 'rl-zewn', '19–36');

  // jedno nasłuchiwanie na cały stół
  $('rl-stol').addEventListener('click', ev => {
    const el = ev.target.closest('[data-klucz]');
    if (el) postaw(el.dataset.klucz);
  });
  $('rl-stol').addEventListener('contextmenu', ev => {
    const el = ev.target.closest('[data-klucz]');
    if (!el) return;
    ev.preventDefault();
    zdejmij(el.dataset.klucz);
  });

  // podpowiedzi: co obejmuje pole pod kursorem
  $('rl-stol').addEventListener('mouseover', ev => {
    const el = ev.target.closest('[data-klucz]');
    if (!el) return;
    const def = CFG.zaklady[el.dataset.klucz];
    if (!def) return;
    podswietlNumery(def[0]);
    set('rl-podpowiedz', def[0].length === 1
      ? 'Numer ' + def[0][0] + ' — wypłata ' + def[1] + ':1'
      : def[0].length + ' numerów — wypłata ' + def[1] + ':1');
  });
  $('rl-stol').addEventListener('mouseout', () => {
    podswietlNumery([]);
    set('rl-podpowiedz', '');
  });
}

function podswietlNumery(lista) {
  document.querySelectorAll('.rl-num.hover').forEach(e => e.classList.remove('hover'));
  lista.forEach(n => { const e = pola['n' + n]; if (e) e.classList.add('hover'); });
}

/* ============================== ŻETONY ============================== */
function klasaNominalu(v) {
  const i = CFG.nominaly.indexOf(v);
  return 'rl-chip n' + (i >= 0 ? i : 0);
}

function postaw(klucz) {
  if (kreci) { dealer('Koniec zakładów.', 'uwaga'); return; }
  const def = CFG.zaklady[klucz];
  if (!def) return;
  const limit = def[1] >= 8 ? CFG.limity.max_numer : CFG.limity.max_zewn;
  const teraz = zaklady[klucz] || 0;
  if (teraz + nominal > limit) {
    dealer('Limit na to pole: ' + fmt(limit) + ' żetonów.', 'uwaga');
    return;
  }
  if (suma() + nominal > CFG.limity.max_suma) {
    dealer('Maksymalnie ' + fmt(CFG.limity.max_suma) + ' żetonów na spin.', 'uwaga');
    return;
  }
  zaklady[klucz] = teraz + nominal;
  stos.push({ klucz, kwota: nominal });
  rysujZetony();
}

function zdejmij(klucz) {
  if (kreci || !zaklady[klucz]) return;
  delete zaklady[klucz];
  stos = stos.filter(s => s.klucz !== klucz);
  rysujZetony();
}

function cofnij() {
  if (kreci || !stos.length) return;
  const ostatni = stos.pop();
  zaklady[ostatni.klucz] -= ostatni.kwota;
  if (zaklady[ostatni.klucz] <= 0) delete zaklady[ostatni.klucz];
  rysujZetony();
}

function wyczysc() {
  if (kreci) return;
  zaklady = {}; stos = [];
  rysujZetony();
}

function powtorz() {
  if (kreci || !poprzednie) return;
  zaklady = Object.assign({}, poprzednie);
  stos = Object.entries(zaklady).map(([klucz, kwota]) => ({ klucz, kwota }));
  rysujZetony();
  dealer('Poprzednie zakłady wróciły na stół.');
}

const suma = () => Object.values(zaklady).reduce((a, b) => a + b, 0);

function rysujZetony() {
  Object.entries(pola).forEach(([klucz, el]) => {
    const stary = el.querySelector('.rl-chip');
    if (stary) stary.remove();
    const kwota = zaklady[klucz];
    if (!kwota) { el.classList.remove('obstawione'); return; }
    el.classList.add('obstawione');
    const chip = document.createElement('span');
    // kolor po największym nominale, który się mieści
    const pasuje = CFG.nominaly.filter(v => v <= kwota);
    chip.className = klasaNominalu(pasuje.length ? pasuje[pasuje.length - 1] : CFG.nominaly[0]);
    chip.textContent = kwota >= 1000 ? Math.round(kwota / 1000) + 'k' : kwota;
    el.appendChild(chip);
  });
  const s = suma();
  set('rl-suma', fmt(s));
  const b = $('rl-spin');
  if (b) b.disabled = kreci || s === 0;
  const c = $('rl-cofnij'); if (c) c.disabled = kreci || !stos.length;
  const w = $('rl-wyczysc'); if (w) w.disabled = kreci || !s;
  const p = $('rl-powtorz'); if (p) p.disabled = kreci || !poprzednie;
}

function rysujNominaly() {
  const box = $('rl-nominaly');
  box.innerHTML = CFG.nominaly.map((v, i) =>
    '<button class="rl-chip-btn n' + i + (v === nominal ? ' on' : '') + '" data-n="' + v + '">' +
    (v >= 1000 ? (v / 1000) + 'k' : v) + '</button>').join('');
  box.querySelectorAll('[data-n]').forEach(b => {
    b.onclick = () => { nominal = parseInt(b.dataset.n); rysujNominaly(); };
  });
}

/* ============================== HISTORIA ============================== */
function rysujHistorie() {
  const box = $('rl-historia');
  if (box) box.innerHTML = historia.ostatnie.length
    ? historia.ostatnie.map(h =>
        '<span class="rl-h ' + h.kolor + '">' + h.numer + '</span>').join('')
    : '<span class="rl-puste">brak spinów</span>';

  const g = $('rl-gorace');
  if (g) g.innerHTML = historia.gorace.length
    ? historia.gorace.map(h => {
        const kolor = h.numer == 0 ? 'zero' : (CFG.czerwone.includes(+h.numer) ? 'red' : 'black');
        return '<span class="rl-h ' + kolor + '">' + h.numer + '<i>' + h.ile + '</i></span>';
      }).join('')
    : '<span class="rl-puste">—</span>';
}

/* ================================ SPIN ================================ */
async function spin() {
  if (kreci || !suma()) return;
  kreci = true;
  rysujZetony();
  document.querySelectorAll('.rl-num.wygral, [data-klucz].wygral').forEach(e => e.classList.remove('wygral'));
  set('rl-wynik-nr', '—');
  $('rl-wynik').className = 'rl-wynik';
  dealer('Koniec zakładów. Kulka w ruchu.', 'praca');

  const doWyslania = Object.assign({}, zaklady);
  try {
    const klucz = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    const j = await call('spin', { zaklady: doWyslania, klucz });
    poprzednie = doWyslania;

    await kolo.rzut(j.numer);

    set('rl-wynik-nr', j.numer);
    $('rl-wynik').className = 'rl-wynik ' + j.kolor;
    const cel = pola['n' + j.numer];
    if (cel) cel.classList.add('wygral');
    (j.trafione || []).forEach(t => { const e = pola[t.klucz]; if (e) e.classList.add('wygral'); });

    set('rl-gotowka', fmt(j.portfel.gotowka));
    set('rl-zetony', fmt(j.portfel.zetony));
    historia = { ostatnie: j.ostatnie || [], gorace: j.gorace || [] };
    rysujHistorie();

    if (j.wyplata > 0) {
      const co = j.trafione.map(t => t.etykieta).join(', ');
      dealer('<b>' + j.numer + '</b> — wypłata <b>' + fmt(j.wyplata) + '</b> żetonów za ' + co +
        (j.netto > 0 ? ' <span class="rl-plus">+' + fmt(j.netto) + '</span>' : ''), 'wygrana');
    } else {
      dealer('<b>' + j.numer + '</b>. Kasyno zabiera ' + fmt(j.stawka) + ' żetonów.', 'przegrana');
    }

    // przegrane żetony schodzą ze stołu, trafione zostają na chwilę
    zaklady = {}; stos = [];
    setTimeout(() => {
      if (!kreci) rysujZetony();
    }, 1400);
  } catch (e) {
    dealer(e.message, 'uwaga');
  } finally {
    kreci = false;
    setTimeout(rysujZetony, 1400);
  }
}

/* ================================ START ================================ */
window.KasynoRuletka = {
  async start() {
    try {
      CFG = await call('tabela');
      budujStol();
      rysujNominaly();
      rysujZetony();

      kolo = RuletkaKolo.stworz({
        canvas: $('rl-canvas'),
        ball: $('rl-kulka'),
        tarcza: $('rl-tarcza'),
        kolo: CFG.kolo,
        czerwone: CFG.czerwone,
      });

      const h = await call('historia');
      historia = { ostatnie: h.ostatnie || [], gorace: h.gorace || [] };
      rysujHistorie();

      $('rl-spin').onclick = spin;
      $('rl-cofnij').onclick = cofnij;
      $('rl-wyczysc').onclick = wyczysc;
      $('rl-powtorz').onclick = powtorz;
      document.addEventListener('keydown', ev => {
        if (ev.target.tagName === 'INPUT') return;
        if (ev.code === 'Space') { ev.preventDefault(); spin(); }
        if (ev.key === 'z' || ev.key === 'Z') cofnij();
      });

      dealer('Zakłady proszę. Kliknij pole lewym, zdejmij prawym.');
    } catch (e) {
      dealer(e.message, 'uwaga');
    }
  },
};
})();
