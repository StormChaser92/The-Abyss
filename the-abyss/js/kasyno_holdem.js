/* the-abyss/js/kasyno_holdem.js
   Klient stołu Hold'em. Nie liczy wyników i nie zmienia kasy — wysyła
   intencję, dostaje stan z serwera i go rysuje. */
(function () {
'use strict';
const API = 'api/kasyno_holdem.php';
const $ = id => document.getElementById(id);
const set = (id, val) => { const el = $(id); if (el) el.textContent = val; };
const html = (id, val) => { const el = $(id); if (el) el.innerHTML = val; };
const fmt = n => Number(n || 0).toLocaleString('pl-PL').replace(/,/g, ' ');

let STOL = 1, wersja = -1, zyje = true, stan = null, ticker = null;

async function call(akcja, dane) {
  const res = await fetch(API + '?a=' + akcja, {
    method: dane === undefined ? 'GET' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: dane === undefined ? undefined : JSON.stringify(Object.assign({ stol_id: STOL }, dane)),
  });
  const j = await res.json().catch(() => ({ ok: false, blad: 'Serwer nie odpowiada (' + res.status + ')' }));
  if (!j.ok) throw new Error(j.blad || 'Błąd serwera');
  return j;
}

/* ------------------------- rysowanie ------------------------- */
function karta(k, duza) {
  if (!k) return '<div class="card back' + (duza ? ' big' : '') + '"></div>';
  return '<div class="card ' + (k.czerwona ? 'r' : 'b') + (duza ? ' big' : '') + '">' +
         '<span class="f">' + k.f + '</span><span class="s">' + k.s + '</span></div>';
}
function rewersy(n, duza) {
  let h = '';
  for (let i = 0; i < n; i++) h += karta(null, duza);
  return h;
}

function rysujStol(s) {
  stan = s;
  set('kh-nazwa', s.stol.nazwa);
  set('kh-blindy', fmt(s.stol.blind_maly) + ' / ' + fmt(s.stol.blind_duzy));
  set('kh-wejscie', fmt(s.stol.wejscie_min));
  set('kh-gotowka', fmt(s.portfel.gotowka));
  set('kh-zetony', fmt(s.portfel.zetony));
  set('kh-pula', fmt(s.stol.pula));
  set('kh-rake', s.stol.rake_opis);
  set('kh-rozdanie', s.stol.faza === 'oczekiwanie'
    ? 'Stół czeka na graczy'
    : s.stol.faza.toUpperCase() + ' · rozdanie #' + s.stol.rozdanie_nr);

  // board — zawsze pięć pozycji, brakujące jako rewersy
  let b = '';
  for (let i = 0; i < 5; i++) b += i < s.board.length ? karta(s.board[i]) : karta(null);
  html('kh-board', b);

  // moja ręka
  html('kh-moja-reka', s.moje.karty
    ? s.moje.karty.map(k => karta(k, true)).join('')
    : rewersy(2, true));

  // miejsca
  s.miejsca.forEach(m => {
    const el = $('kh-m' + m.miejsce);
    if (!el) return;
    if (!m.kto) {
      el.innerHTML = '<div class="seat free" data-siadz="' + m.miejsce + '">Wolne miejsce</div>';
      return;
    }
    const tury = s.stol.aktywne_miejsce === m.miejsce;
    const tagi = (m.ja ? '<span class="tag me">Ty</span>' : '') +
                 (m.bot ? '<span class="tag">Bot</span>' : '') +
                 (s.stol.przycisk === m.miejsce ? '<span class="tag d">D</span>' : '');
    const spas = m.status === 'spasowal';
    el.innerHTML = '<div class="seat' + (tury ? ' turn' : '') + (spas ? ' out' : '') + '">' +
      '<div class="nm">' + m.kto + ' ' + tagi + '</div>' +
      '<div class="st">' + fmt(m.zetony) + '</div>' +
      '<div class="act">' + (m.akcja || (m.status === 'czeka' ? 'czeka na rozdanie' : '')) +
        (m.wplata > 0 ? ' · <span class="bet">' + fmt(m.wplata) + '</span>' : '') + '</div>' +
      (m.karty && !m.ja ? '<div class="cards mini">' + m.karty.map(k => karta(k)).join('') + '</div>' : '') +
      (m.zakryte ? '<div class="cards mini">' + rewersy(m.zakryte) + '</div>' : '') +
      '</div>';
  });

  rysujAkcje(s);
  rysujCzat(s.czat);
  html('kh-widzowie', s.widzowie.length
    ? s.widzowie.map(w => '<span>' + w.login + '</span>').join('')
    : '<span style="opacity:.5">nikt nie patrzy</span>');
}

function rysujAkcje(s) {
  const box = $('kh-akcje'), info = $('kh-timer');
  if (!box) return;
  const przyStole = s.moje.miejsce !== null;

  if (!przyStole) {
    box.innerHTML = '<button class="btn gold" id="kh-siadz">Dosiądź się (' + fmt(s.stol.wejscie_min) + ' żetonów)</button>' +
      '<button class="btn ghost" id="kh-kasa">Kasa kasyna</button>';
    if (info) info.innerHTML = 'Oglądasz stół';
    $('kh-siadz').onclick = () => akcjaSiadz(s.stol.wejscie_min);
    $('kh-kasa').onclick = pokazKase;
    return;
  }

  if (!s.moje.moja_tura) {
    box.innerHTML = '<button class="btn ghost" id="kh-wstan">Wstań od stołu</button>';
    if (info) info.innerHTML = s.stol.aktywne_miejsce
      ? 'Ruch ma miejsce ' + s.stol.aktywne_miejsce
      : (s.stol.faza === 'oczekiwanie' ? 'Czekamy na drugiego gracza' : 'Rozdanie w toku');
    $('kh-wstan').onclick = akcjaWstan;
    return;
  }

  const doCall = s.moje.do_sprawdzenia;
  const min = Math.min(s.moje.min_podbicie, s.moje.zetony + s.moje.do_sprawdzenia);
  box.innerHTML =
    '<button class="btn ghost" data-akcja="pas">Pas</button>' +
    (doCall > 0
      ? '<button class="btn" data-akcja="sprawdzam">Sprawdź ' + fmt(doCall) + '</button>'
      : '<button class="btn" data-akcja="czekam">Czekam</button>') +
    '<input type="number" id="kh-kwota" class="kwota" value="' + min + '" min="' + min + '" step="' + s.stol.blind_duzy + '">' +
    '<button class="btn gold" data-akcja="podbijam">Podbij</button>' +
    '<button class="btn" data-akcja="allin">All-in ' + fmt(s.moje.zetony) + '</button>';

  box.querySelectorAll('[data-akcja]').forEach(b => {
    b.onclick = () => wyslijAkcje(b.dataset.akcja, parseInt($('kh-kwota') ? $('kh-kwota').value : 0) || 0);
  });

  const zostalo = s.stol.zostalo, pelne = s.stol.czas_decyzji;
  if (info) info.innerHTML = 'Zostało <b>' + zostalo + ' s</b><span class="bar"><i style="width:' +
    Math.max(0, Math.min(100, (zostalo / pelne) * 100)) + '%"></i></span>';
}

function rysujCzat(lista) {
  const feed = $('kh-feed');
  if (!feed) return;
  feed.innerHTML = lista.map(m => {
    const kto = m.login || m.bot_nick || '';
    const h = String(m.tresc).replace(/[<>]/g, '');
    if (m.typ === 'system') return '<div class="msg sys">— ' + h + '</div>';
    if (m.typ === 'akcja')  return '<div class="msg emote">' + kto + ' ' + h + '</div>';
    if (m.typ === 'mg')     return '<div class="msg mg"><span class="who">MG</span>' + h + '</div>';
    return '<div class="msg"><span class="who">' + kto + '</span>' + h + '</div>';
  }).join('');
  feed.scrollTop = feed.scrollHeight;
}

/* --------------------------- akcje --------------------------- */
function komunikat(txt, zle) {
  const el = $('kh-komunikat');
  if (!el) return;
  el.textContent = txt || '';
  el.className = 'kh-komunikat' + (zle ? ' blad' : '') + (txt ? ' widoczny' : '');
  if (txt) setTimeout(() => { if (el.textContent === txt) el.className = 'kh-komunikat'; }, 6000);
}

async function wyslijAkcje(akcja, kwota) {
  try { rysujStol(await call('akcja', { akcja, kwota })); wersja = stan.wersja; }
  catch (e) { komunikat(e.message, true); }
}
async function akcjaSiadz(wejscie) {
  try { rysujStol(await call('siadz', { wejscie })); wersja = stan.wersja; }
  catch (e) { komunikat(e.message, true); }
}
async function akcjaWstan() {
  try { rysujStol(await call('wstan', {})); wersja = stan.wersja; }
  catch (e) { komunikat(e.message, true); }
}
async function pokazKase() {
  const kwota = parseInt(prompt('Ile żetonów kupić? (1 żeton = 1 $)', '10000') || '0');
  if (!kwota) return;
  try {
    const j = await call('kasa_kup', { kwota });
    komunikat('Kupiono ' + fmt(kwota) + ' żetonów.');
    set('kh-gotowka', fmt(j.portfel.gotowka));
    set('kh-zetony', fmt(j.portfel.zetony));
  } catch (e) { komunikat(e.message, true); }
}

/* ------------------------ subskrypcja ------------------------
   Jedno długie żądanie wisi do 20 s i wraca, gdy wersja stołu się
   zmieni. Po błędzie odczekujemy coraz dłużej, żeby nie dobijać serwera. */
async function petla() {
  let przerwa = 1000;
  while (zyje) {
    try {
      const j = await call('stan&stol_id=' + STOL + '&od=' + wersja);
      przerwa = 1000;
      if (!j.bez_zmian) { wersja = j.wersja; rysujStol(j); }
    } catch (e) {
      if (!zyje) return;
      komunikat(e.message, true);
      await new Promise(r => setTimeout(r, przerwa));
      przerwa = Math.min(przerwa * 2, 15000);
    }
  }
}

/* Odliczanie w dół między odpowiedziami serwera — sam wyświetlacz. */
function tykaj() {
  if (!stan || !stan.stol.zostalo) return;
  stan.stol.zostalo = Math.max(0, stan.stol.zostalo - 1);
  if (stan.moje.moja_tura) rysujAkcje(stan);
}

window.KasynoHoldem = {
  start(stolId) {
    STOL = stolId || 1;
    const say = $('kh-say');
    if (say) say.addEventListener('submit', async ev => {
      ev.preventDefault();
      const inp = $('kh-tresc'), tresc = inp.value.trim();
      if (!tresc) return;
      inp.value = '';
      try { await call('powiedz', { tresc }); } catch (e) { komunikat(e.message, true); }
    });
    document.addEventListener('click', ev => {
      const w = ev.target.closest('[data-siadz]');
      if (w && stan && stan.moje.miejsce === null) akcjaSiadz(stan.stol.wejscie_min);
    });
    window.addEventListener('beforeunload', () => { zyje = false; });
    ticker = setInterval(tykaj, 1000);
    petla();
  },
};
})();
