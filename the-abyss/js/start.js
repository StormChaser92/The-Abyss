/* the-abyss/js/start.js — strona startowa: skalowanie do tła 1672×941, blask szkła, widoki panelu. */
(function () {
  var W0 = 1672, H0 = 941, html = document.documentElement;
  var stage = document.getElementById('stStage'), nav = document.getElementById('stNav'), stopka = document.getElementById('stStopka');

  function dopasuj() {
    var W = innerWidth, H = innerHeight;
    var flow = W < 900 || W / H < 1.22;
    html.classList.toggle('flow', flow);
    if (flow) return;
    var s = H / H0, szer = W / s, menu = nav.querySelector('.st-menu');
    menu.style.left = (szer >= 1560 ? 512 : Math.max(300, (szer - menu.offsetWidth) / 2 - 40)) + 'px';
    var left = (W - W0 * s) / 2;
    stage.style.transform = 'translate(' + left + 'px,' + (H - H0 * s) / 2 + 'px) scale(' + s + ')';
    html.classList.toggle('szeroki', W0 * s < W - 2);
    nav.style.width = szer + 'px';
    nav.style.transform = 'scale(' + s + ')';
    if (stopka) { stopka.style.width = szer + 'px'; stopka.style.transform = 'scale(' + s + ')'; }
  }
  addEventListener('resize', dopasuj);
  dopasuj();

  // Blask szkła: pozycja kursora → --mx / --my
  document.querySelectorAll('.szklo').forEach(function (el) {
    el.addEventListener('pointermove', function (e) {
      var r = el.getBoundingClientRect();
      el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(2) + '%');
      el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(2) + '%');
    });
    el.addEventListener('pointerenter', function () { el.classList.add('lsni'); });
    el.addEventListener('pointerleave', function () { el.classList.remove('lsni'); });
  });

  // Widoki panelu
  var panel = document.getElementById('stPanel'), zamknij = document.getElementById('stZamknij');
  if (!panel || !zamknij) return;   // strona wylogowania: bez widoków
  var MENU = { start: 'swiat', opowiesci: 'opowiesci', spolecznosc: 'spolecznosc', faq: 'faq' };
  function pokaz(v, zapisz) {
    if (!document.getElementById('w-' + v)) v = 'start';
    panel.querySelectorAll('.widok').forEach(function (w) { w.classList.toggle('on', w.id === 'w-' + v); });
    document.querySelectorAll('.st-menu a').forEach(function (a) { a.classList.toggle('on', a.dataset.w === (MENU[v] ? v : '')); });
    zamknij.hidden = v === 'start';
    if (zapisz !== false) history.replaceState(null, '', v === 'start' ? location.pathname + location.search : '#' + v);
    var f = document.querySelector('#w-' + v + ' input:not([type=hidden])');
    if (f && !html.classList.contains('flow')) setTimeout(function () { f.focus(); }, 380);
    if (html.classList.contains('flow')) scrollTo(0, 0);
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-w]');
    if (!a) return;
    e.preventDefault();
    pokaz(a.dataset.w);
  });
  // FAQ: otwarte tylko jedno pytanie (fallback dla przeglądarek bez <details name>)
  document.querySelectorAll('.st-faq details').forEach(function (d) {
    d.addEventListener('toggle', function () { if (d.open) document.querySelectorAll('.st-faq details').forEach(function (o) { if (o !== d) o.open = false; }); });
  });
  addEventListener('keydown', function (e) { if (e.key === 'Escape') pokaz('start'); });
  pokaz((location.hash || '').slice(1) || document.body.dataset.widok || 'start', false);
})();
