/* the-abyss/js/kasyno_ruletka_kolo.js
   Koło ruletki: rysowanie na canvasie i animacja rzutu.

   Koło jest rysowane raz, w rozdzielczości ekranu, a potem tylko obracane.
   Kulka krąży w przeciwnym kierunku i opada z rantu do kieszeni. Numer
   przychodzi z serwera PRZED animacją — obliczamy kąt tak, żeby kulka
   zatrzymała się dokładnie w tej kieszeni. Animacja niczego nie losuje. */
window.RuletkaKolo = (function () {
'use strict';

const TAU = Math.PI * 2;

function kolorKieszeni(n, czerwone) {
  if (n === 0) return { tlo: '#0b6b3a', tekst: '#eafff2' };
  return czerwone.includes(n)
    ? { tlo: '#8e0f22', tekst: '#ffe9ec' }
    : { tlo: '#12101a', tekst: '#e8e1e8' };
}

function rysuj(canvas, kolo, czerwone, wrap) {
  const dpr = window.devicePixelRatio || 1;
  // Mierzymy NIEOBRACAJĄCY się kontener. Rect obróconego elementu to
  // prostokąt otaczający, więc canvas po starcie koła „rośnie" o 40%.
  const box = (wrap || canvas).getBoundingClientRect();
  const w = Math.max(240, box.width || 340);
  canvas.width = w * dpr;
  canvas.height = w * dpr;
  const ctx = canvas.getContext('2d');
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.clearRect(0, 0, w, w);

  const cx = w / 2, cy = w / 2, R = w / 2;
  const N = kolo.length;
  const kat = TAU / N;

  // zewnętrzny rant — ciemny metal z czerwoną poświatą
  const rant = ctx.createRadialGradient(cx, cy, R * 0.86, cx, cy, R);
  rant.addColorStop(0, '#2a1418');
  rant.addColorStop(0.55, '#16090e');
  rant.addColorStop(1, '#050308');
  ctx.fillStyle = rant;
  ctx.beginPath(); ctx.arc(cx, cy, R, 0, TAU); ctx.fill();

  ctx.strokeStyle = 'rgba(255,23,68,0.55)';
  ctx.lineWidth = 1.5;
  ctx.beginPath(); ctx.arc(cx, cy, R - 1, 0, TAU); ctx.stroke();

  // pierścień kieszeni
  const rZew = R * 0.855, rWew = R * 0.60;
  for (let i = 0; i < N; i++) {
    const n = kolo[i];
    const a0 = -Math.PI / 2 + i * kat - kat / 2;
    const a1 = a0 + kat;
    const k = kolorKieszeni(n, czerwone);

    ctx.beginPath();
    ctx.arc(cx, cy, rZew, a0, a1);
    ctx.arc(cx, cy, rWew, a1, a0, true);
    ctx.closePath();
    ctx.fillStyle = k.tlo;
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,0.10)';
    ctx.lineWidth = 1;
    ctx.stroke();

    // numer, obrócony na zewnątrz
    const aS = a0 + kat / 2;
    ctx.save();
    ctx.translate(cx + Math.cos(aS) * (rZew - (rZew - rWew) * 0.32),
                  cy + Math.sin(aS) * (rZew - (rZew - rWew) * 0.32));
    ctx.rotate(aS + Math.PI / 2);
    ctx.fillStyle = k.tekst;
    ctx.font = '600 ' + Math.round(R * 0.088) + 'px Rajdhani, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(String(n), 0, 0);
    ctx.restore();
  }

  // separatory kieszeni
  for (let i = 0; i < N; i++) {
    const a = -Math.PI / 2 + i * kat - kat / 2;
    ctx.strokeStyle = 'rgba(210,190,150,0.28)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(cx + Math.cos(a) * rWew, cy + Math.sin(a) * rWew);
    ctx.lineTo(cx + Math.cos(a) * rZew, cy + Math.sin(a) * rZew);
    ctx.stroke();
  }

  // wieżyczka
  const hub = ctx.createRadialGradient(cx - R * 0.1, cy - R * 0.12, R * 0.02, cx, cy, rWew);
  hub.addColorStop(0, '#3a2026');
  hub.addColorStop(0.6, '#1a0d12');
  hub.addColorStop(1, '#0a0509');
  ctx.fillStyle = hub;
  ctx.beginPath(); ctx.arc(cx, cy, rWew, 0, TAU); ctx.fill();
  ctx.strokeStyle = 'rgba(255,61,94,0.35)';
  ctx.lineWidth = 1.5;
  ctx.beginPath(); ctx.arc(cx, cy, rWew - 1, 0, TAU); ctx.stroke();

  // krzyż wieżyczki
  ctx.save();
  ctx.translate(cx, cy);
  ctx.strokeStyle = 'rgba(220,200,160,0.30)';
  ctx.lineWidth = Math.max(2, R * 0.018);
  for (let i = 0; i < 4; i++) {
    ctx.rotate(Math.PI / 2);
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(0, -rWew * 0.82);
    ctx.stroke();
  }
  ctx.fillStyle = '#241318';
  ctx.beginPath(); ctx.arc(0, 0, rWew * 0.22, 0, TAU); ctx.fill();
  ctx.strokeStyle = 'rgba(255,215,0,0.35)';
  ctx.lineWidth = 1;
  ctx.beginPath(); ctx.arc(0, 0, rWew * 0.22, 0, TAU); ctx.stroke();
  ctx.restore();

  return { rZew, rWew, R };
}

const easeOutQuart = t => 1 - Math.pow(1 - t, 4);
const easeInOutSine = t => -(Math.cos(Math.PI * t) - 1) / 2;

return {
  /**
   * @param opts.canvas   element canvas z kołem
   * @param opts.ball     element kulki (pozycjonowany absolutnie)
   * @param opts.tarcza   element obracany (owija canvas)
   * @param opts.kolo     kolejność liczb na kole
   * @param opts.czerwone lista czerwonych
   */
  stworz(opts) {
    const { canvas, ball, tarcza, kolo, czerwone } = opts;
    // kontener, który się NIE obraca — jedyne wiarygodne źródło geometrii
    const wrap = opts.wrap || ball.offsetParent || tarcza.parentElement;
    let geo = rysuj(canvas, kolo, czerwone, wrap);
    let obrotTarczy = 0;

    // przerysowanie po zmianie rozmiaru okna
    let t = null;
    window.addEventListener('resize', () => {
      clearTimeout(t);
      t = setTimeout(() => { geo = rysuj(canvas, kolo, czerwone, wrap); }, 200);
    });

    const promienBazowy = () => wrap.getBoundingClientRect().width / 2;

    function ustawKulke(katDeg, promien) {
      const R = promienBazowy();
      const a = (katDeg - 90) * Math.PI / 180;
      ball.style.left = (R + Math.cos(a) * promien) + 'px';
      ball.style.top = (R + Math.sin(a) * promien) + 'px';
    }

    return {
      /** Rzut kulki. Zwraca Promise spełniony, gdy kulka usiądzie. */
      rzut(numer, czasMs) {
        const czas = czasMs || 5200;
        const idx = kolo.indexOf(numer);
        if (idx < 0) return Promise.resolve();

        const R = promienBazowy();
        const rRant = R * 0.925;
        const rKieszen = R * 0.735;

        const katKieszeni = idx * (360 / kolo.length);
        // tarcza kręci się tak, by kieszeń trafiła na godzinę 12
        const obrotyTarczy = 4;
        const docelowyObrot = obrotTarczy + obrotyTarczy * 360 +
          (((-katKieszeni - (obrotTarczy % 360)) % 360) + 360) % 360;
        const startObrot = obrotTarczy;

        const obrotyKulki = 9;   // kulka krąży szybciej i w drugą stronę
        ball.style.opacity = '1';

        return new Promise(resolve => {
          const t0 = performance.now();
          function krok(now) {
            const p = Math.min(1, (now - t0) / czas);
            const e = easeOutQuart(p);

            obrotTarczy = startObrot + (docelowyObrot - startObrot) * e;
            tarcza.style.transform = 'rotate(' + obrotTarczy + 'deg)';

            // kulka: pełne obroty w przeciwnym kierunku, koniec na godzinie 12
            const katKulki = -obrotyKulki * 360 * (1 - Math.pow(1 - p, 3)) % 360;
            // opadanie z rantu do kieszeni w ostatniej trzeciej części
            const opad = p < 0.62 ? 0 : easeInOutSine((p - 0.62) / 0.38);
            const promien = rRant - (rRant - rKieszen) * opad;
            // lekkie odbicia na krawędzi kieszeni
            const drgania = p > 0.82 && p < 0.97
              ? Math.sin((p - 0.82) * 42) * (1 - (p - 0.82) / 0.15) * R * 0.012
              : 0;
            ustawKulke(katKulki * (1 - e) + 0 * e, promien + drgania);

            if (p < 1) requestAnimationFrame(krok);
            else { ustawKulke(0, rKieszen); resolve(); }
          }
          requestAnimationFrame(krok);
        });
      },
      schowajKulke() { ball.style.opacity = '0'; },
      przerysuj() { geo = rysuj(canvas, kolo, czerwone, wrap); },
    };
  },
};
})();
