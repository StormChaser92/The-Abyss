/* the-abyss/js/kasyno_videopoker.js
   Klient Video Pokera. Nie ocenia układów i nie zmienia kasy — wysyła
   „rozdaj / trzymam te / podwajam", dostaje stan z serwera i rysuje. */
(function () {
'use strict';
const API = 'api/kasyno_videopoker.php';
const $ = id => document.getElementById(id);
const fmt = n => Number(n || 0).toLocaleString('pl-PL').replace(/,/g, ' ');

let runda = null, zetony = 1, zajete = false;

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
  const el = $('vp-komunikat');
  el.textContent = txt || '';
  el.className = 'vp-komunikat' + (txt ? ' widoczny' : '') + (rodzaj ? ' ' + rodzaj : '');
}

function karta(k, opcje) {
  const o = opcje || {};
  if (!k) return '<div class="card back' + (o.klasa || '') + '"' + (o.attr || '') + '></div>';
  return '<div class="card ' + (k.czerwona ? 'r' : 'b') + (o.klasa || '') + '"' + (o.attr || '') + '>' +
         '<span class="f">' + k.f + '</span><span class="s">' + k.s + '</span></div>';
}

/* --------------------------- rysowanie --------------------------- */
function rysuj(portfel) {
  if (portfel) {
    $('vp-gotowka').textContent = fmt(portfel.gotowka);
    $('vp-zetony').textContent = fmt(portfel.zetony);
  }
  podswietlTabele();

  const r = runda;
  const stan = r ? r.stan : 'nowa';

  // ręka
  if (!r || !r.karty.length) {
    $('vp-reka').innerHTML = [0,1,2,3,4].map(() => karta(null)).join('');
  } else if (stan === 'double') {
    $('vp-reka').innerHTML = r.karty.map(k => karta(k, { klasa: ' dim' })).join('');
  } else {
    $('vp-reka').innerHTML = r.karty.map((k, i) => {
      const trzym = r.trzymane[i] === 1;
      const klik = stan === 'dobranie' ? ' data-hold="' + i + '"' : '';
      return '<div class="vp-slot' + (trzym ? ' held' : '') + '"' + klik + '>' +
             karta(k) + '<span class="vp-hold">' + (trzym ? 'Trzymam' : '') + '</span></div>';
    }).join('');
  }

  // strefa double-up
  const dbl = $('vp-double');
  if (stan === 'double') {
    dbl.style.display = '';
    const dk = r.double_karty;
    dbl.innerHTML =
      '<div class="vp-dbl-row"><div class="vp-dbl-lbl">Krupier</div>' + karta(dk[0], { klasa: ' big' }) + '</div>' +
      '<div class="vp-dbl-mid">Wybierz kartę wyższą niż krupiera. Równa — próbujesz jeszcze raz.</div>' +
      '<div class="vp-dbl-row">' +
        [1,2,3,4].map(i => karta(dk[i], {
          klasa: ' big pick' + (r.double_wybor === i - 1 ? ' chosen' : ''),
          attr: ' data-pick="' + (i - 1) + '"',
        })).join('') +
      '</div>';
  } else {
    dbl.style.display = 'none';
    dbl.innerHTML = '';
  }

  // wynik układu
  const info = $('vp-uklad');
  if (stan === 'double') {
    info.innerHTML = '<b>' + r.uklad + '</b> · do zebrania <b class="zloto">' + fmt(r.wyplata) + '</b>' +
      (r.double_poziom > 0 ? ' · podwojenia ' + r.double_poziom + '/' + r.double_max : '');
  } else if (r && stan === 'zakonczona') {
    info.innerHTML = r.wyplata > 0
      ? '<b class="zloto">' + r.uklad + '</b> · wygrana ' + fmt(r.wyplata)
      : '<b class="pusto">' + (r.uklad || 'BRAK UKŁADU') + '</b>';
  } else if (stan === 'dobranie') {
    info.innerHTML = 'Zaznacz karty, które zostają, i dobierz.';
  } else {
    info.innerHTML = 'Wybierz stawkę i rozdaj.';
  }

  rysujPrzyciski(stan);
}

function rysujPrzyciski(stan) {
  const box = $('vp-akcje');
  const stawka = zetony * ZETON;

  if (stan === 'dobranie') {
    box.innerHTML = '<button class="btn gold" id="vp-dobierz">Dobierz karty</button>' +
                    '<span class="vp-stawka">Stawka ' + fmt(runda.stawka) + '</span>';
    $('vp-dobierz').onclick = dobierz;
    return;
  }
  if (stan === 'double') {
    const limit = runda.double_poziom >= runda.double_max;
    box.innerHTML = '<button class="btn gold" id="vp-zbierz">Zbierz ' + fmt(runda.wyplata) + '</button>' +
      (limit ? '<span class="vp-stawka">Limit podwojeń wyczerpany</span>'
             : '<span class="vp-stawka">albo wybierz kartę powyżej, żeby podwoić</span>');
    $('vp-zbierz').onclick = zbierz;
    return;
  }

  box.innerHTML =
    '<div class="vp-zetony" id="vp-wybor-zetonow">' +
      [1,2,3,4,5].map(n => '<button class="vp-z' + (n === zetony ? ' on' : '') +
        '" data-zeton="' + n + '">' + n + '</button>').join('') +
    '</div>' +
    '<button class="btn gold" id="vp-rozdaj">Rozdaj za ' + fmt(stawka) + '</button>';

  box.querySelectorAll('[data-zeton]').forEach(b => {
    b.onclick = () => { zetony = parseInt(b.dataset.zeton); rysuj(); };
  });
  $('vp-rozdaj').onclick = rozdaj;
}

/** Podświetla wiersz tabeli wypłat odpowiadający stawce i trafionemu układowi. */
function podswietlTabele() {
  document.querySelectorAll('#vp-tabela tr').forEach(tr => {
    tr.classList.toggle('trafiony', !!(runda && runda.uklad && tr.dataset.uklad === runda.uklad));
    tr.querySelectorAll('td[data-kol]').forEach(td => {
      td.classList.toggle('kol-on', parseInt(td.dataset.kol) === zetony);
    });
  });
}

/* ---------------------------- akcje ---------------------------- */
function klucz() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }

async function chron(fn) {
  if (zajete) return;
  zajete = true;
  try { await fn(); } catch (e) { komunikat(e.message, 'blad'); }
  finally { zajete = false; }
}

const rozdaj = () => chron(async () => {
  komunikat('');
  const j = await call('rozdaj', { zetony, klucz: klucz() });
  runda = j.runda; rysuj(j.portfel);
});

const dobierz = () => chron(async () => {
  const trzymane = runda.trzymane;
  const j = await call('dobierz', { runda_id: runda.runda_id, trzymane });
  runda = j.runda; rysuj(j.portfel);
  if (runda.stan === 'double') komunikat(runda.uklad + ' — zbierz albo podwój.', 'dobry');
  else komunikat('Brak układu. Następne rozdanie?', '');
});

const podwoj = karta => chron(async () => {
  const j = await call('wybierz', { runda_id: runda.runda_id, karta });
  runda = j.runda;
  const k = j.odkryte;
  if (j.wynik === 'wygrana') {
    komunikat('Twoja ' + k.gracz.f + k.gracz.s + ' bije ' + k.krupier.f + k.krupier.s + '. Wygrana podwojona.', 'dobry');
  } else if (j.wynik === 'remis') {
    komunikat('Remis — ' + k.gracz.f + k.gracz.s + ' przeciw ' + k.krupier.f + k.krupier.s + '. Jeszcze raz.', '');
  } else {
    komunikat('Krupier miał ' + k.krupier.f + k.krupier.s + ', ty ' + k.gracz.f + k.gracz.s + '. Wszystko przepadło.', 'blad');
  }
  rysuj(j.portfel);
});

const zbierz = () => chron(async () => {
  const j = await call('zbierz', { runda_id: runda.runda_id });
  runda = j.runda; rysuj(j.portfel);
  komunikat('Zebrano ' + fmt(j.zebrano) + ' żetonów.', 'dobry');
});

/* -------------------------- start -------------------------- */
let ZETON = 100;

window.KasynoVideoPoker = {
  async start(zeton) {
    ZETON = zeton || 100;

    document.addEventListener('click', ev => {
      const hold = ev.target.closest('[data-hold]');
      if (hold && runda && runda.stan === 'dobranie') {
        const i = parseInt(hold.dataset.hold);
        runda.trzymane[i] = runda.trzymane[i] ? 0 : 1;
        rysuj();
        return;
      }
      const pick = ev.target.closest('[data-pick]');
      if (pick && runda && runda.stan === 'double' && !zajete) podwoj(parseInt(pick.dataset.pick));
    });

    try {
      const j = await call('stan');
      runda = j.runda;
      rysuj(j.portfel);
      if (runda) komunikat('Wracasz do niedokończonej rundy.', '');
    } catch (e) { komunikat(e.message, 'blad'); }
  },
};
})();
