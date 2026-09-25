/* THE ABYSS — Uniwersytet v2: probówka, łamigłówki (widok). Plansze i weryfikacja są po stronie serwera. */
(function(){
const D=window.UNI_V2||{},$=id=>document.getElementById(id);
const przes=D.teraz*1000-Date.now();            // zegar serwera
const teraz=()=>(Date.now()+przes)/1000;
const fmt=s=>{s=Math.max(0,Math.floor(s));const h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60;return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(x).padStart(2,'0')};

/* ── Probówka i odliczanie ── */
function tick(){
  const tb=document.querySelector('.uv .tb[data-start]');
  if(tb){const s=+tb.dataset.start,k=+tb.dataset.koniec,pr=Math.min(1,(teraz()-s)/(k-s));
    tb.querySelector('.liq').style.height=Math.max(4,pr*100)+'%';
    const t=tb.querySelector('.tb-t'),p=tb.querySelector('.tb-p');
    if(pr>=1&&!tb.classList.contains('done')){location.reload();return}
    if(t)t.textContent=fmt(k-teraz());if(p)p.textContent=Math.floor(pr*100)+'%'}
  document.querySelectorAll('.uv [data-cd]').forEach(el=>{const r=+el.dataset.cd-teraz();if(r<=0){location.reload();return}el.textContent=el.dataset.krotko?fmt(r).slice(0,5):fmt(r)});
}
setInterval(tick,1000);tick();

/* ── Modal ── */
const M=$('uvMod');
if(M){M.addEventListener('click',e=>{if(e.target===M||e.target.closest('[data-zamknij]'))M.classList.remove('on')});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')M.classList.remove('on')})}

/* ── Sudoku 6×6 ── */
function sudoku(el,plansza,kolor,gotowe){
  const stan=plansza.map(r=>r.slice()),stale=plansza.map(r=>r.map(v=>v>0));let sel=null,koniec=false;
  el.innerHTML=`<div class="sd" style="--k:${kolor}"><div class="sd-grid"></div><div class="sd-pad">${[1,2,3,4,5,6].map(d=>`<button type="button" data-d="${d}">${d}</button>`).join('')}<button type="button" data-d="0">⌫</button></div><p class="sd-hint">Każda cyfra 1–6 raz w wierszu, kolumnie i bloku 2×3. Kliknij pole, potem cyfrę — albo użyj klawiatury.</p></div>`;
  const grid=el.querySelector('.sd-grid');
  const konf=(r,c)=>{const v=stan[r][c];if(!v)return false;for(let i=0;i<6;i++){if(i!==c&&stan[r][i]===v)return true;if(i!==r&&stan[i][c]===v)return true}
    const br=r-r%2,bc=c-c%3;for(let i=br;i<br+2;i++)for(let j=bc;j<bc+3;j++)if((i!==r||j!==c)&&stan[i][j]===v)return true;return false};
  function rysuj(){let h='';for(let r=0;r<6;r++)for(let c=0;c<6;c++){const k=['sd-c'];if(stale[r][c])k.push('fix');
    if(sel&&sel[0]===r&&sel[1]===c)k.push('sel');else if(sel&&(sel[0]===r||sel[1]===c||(Math.floor(sel[0]/2)===Math.floor(r/2)&&Math.floor(sel[1]/3)===Math.floor(c/3))))k.push('rel');
    if(konf(r,c))k.push('bad');if(c===2)k.push('br');if(r%2===1&&r<5)k.push('bb');
    h+=`<button type="button" class="${k.join(' ')}" data-r="${r}" data-c="${c}">${stan[r][c]||''}</button>`}grid.innerHTML=h;
    if(!koniec&&stan.every(w=>w.every(v=>v))&&stan.every((w,r)=>w.every((v,c)=>!konf(r,c)))){koniec=true;el.firstChild.classList.add('done');setTimeout(()=>gotowe(stan),700)}}
  const wpisz=d=>{if(!sel||koniec)return;const[r,c]=sel;if(!stale[r][c]){stan[r][c]=d;rysuj()}};
  grid.addEventListener('click',e=>{const b=e.target.closest('.sd-c');if(b&&!koniec){sel=[+b.dataset.r,+b.dataset.c];rysuj()}});
  el.querySelector('.sd-pad').addEventListener('click',e=>{const b=e.target.closest('button');if(b)wpisz(+b.dataset.d)});
  document.addEventListener('keydown',e=>{if(!M.classList.contains('on'))return;
    if(e.key>='1'&&e.key<='6')wpisz(+e.key);else if(['Backspace','Delete','0'].includes(e.key))wpisz(0);
    else if(sel&&e.key.startsWith('Arrow')){const d={ArrowUp:[-1,0],ArrowDown:[1,0],ArrowLeft:[0,-1],ArrowRight:[0,1]}[e.key];sel=[(sel[0]+d[0]+6)%6,(sel[1]+d[1]+6)%6];rysuj();e.preventDefault()}});
  rysuj();
}

/* ── Łączenie obwodu ── */
const DIR=[[1,-1,0,4],[2,0,1,8],[4,1,0,1],[8,0,-1,2]],obr=m=>((m<<1)|(m>>3))&15;
function obwod(el,d,kolor,gotowe){
  const n=d.n,[sr,sc]=d.src,rot=d.maska.map(r=>r.map(()=>0));let koniec=false;
  const akt=(r,c)=>{let m=d.maska[r][c];for(let i=0;i<rot[r][c]%4;i++)m=obr(m);return m};
  function zasilone(){const z=d.maska.map(r=>r.map(()=>false)),q=[[sr,sc]];z[sr][sc]=true;
    while(q.length){const[r,c]=q.shift(),m=akt(r,c);for(const[b,dr,dc,bp]of DIR){if(!(m&b))continue;const R=r+dr,C=c+dc;if(R<0||C<0||R>=n||C>=n||z[R][C])continue;if(akt(R,C)&bp){z[R][C]=true;q.push([R,C])}}}return z}
  function ok(){const z=zasilone();for(let r=0;r<n;r++)for(let c=0;c<n;c++){if(!z[r][c])return false;const m=akt(r,c);
    for(const[b,dr,dc,bp]of DIR){if(!(m&b))continue;const R=r+dr,C=c+dc;if(R<0||C<0||R>=n||C>=n||!(akt(R,C)&bp))return false}}return true}
  el.innerHTML=`<div class="ob" style="--k:${kolor};--n:${n}"><div class="ob-grid"></div><p class="sd-hint">Obracaj kafle kliknięciem, aż prąd ze źródła dotrze do każdej lampy i żaden przewód nie zostanie urwany.</p></div>`;
  const grid=el.querySelector('.ob-grid'),kafle=[];
  for(let r=0;r<n;r++)for(let c=0;c<n;c++){const m=d.maska[r][c],st=[1,2,4,8].filter(b=>m&b).length;
    const lin=DIR.filter(x=>m&x[0]).map(([b])=>({1:'M50 50V0',2:'M50 50H100',4:'M50 50V100',8:'M50 50H0'})[b]).join(' ');
    const zr=r===sr&&c===sc,lampa=st===1&&!zr,b=document.createElement('button');b.type='button';b.className='ob-t';b.dataset.r=r;b.dataset.c=c;
    b.innerHTML=`<svg viewBox="0 0 100 100"><g class="ob-rot"><path class="ob-l" d="${lin}"/>${zr?'<circle class="ob-src" cx="50" cy="50" r="20"/>':lampa?'<circle class="ob-lamp" cx="50" cy="50" r="15"/>':'<circle class="ob-node" cx="50" cy="50" r="8"/>'}</g></svg>`;
    grid.appendChild(b);kafle.push(b)}
  function rysuj(){const z=zasilone();kafle.forEach(b=>{const r=+b.dataset.r,c=+b.dataset.c;b.classList.toggle('on',z[r][c]);b.querySelector('.ob-rot').style.transform=`rotate(${rot[r][c]*90}deg)`});
    if(!koniec&&ok()){koniec=true;el.firstChild.classList.add('done');setTimeout(()=>gotowe(rot.map(w=>w.map(v=>v%4))),900)}}
  grid.addEventListener('click',e=>{const b=e.target.closest('.ob-t');if(!b||koniec)return;rot[+b.dataset.r][+b.dataset.c]++;rysuj()});
  rysuj();
}

/* ── Start łamigłówki podanej przez serwer ── */
if(D.lam){const el=$('uvLam'),f=$('uvLamForm');
  const wyslij=odp=>{f.querySelector('[name=odp]').value=JSON.stringify(odp);f.submit()};
  if(D.lam.typ==='sudoku')sudoku(el,D.lam.plansza,D.lam.kolor,wyslij);else obwod(el,D.lam,D.lam.kolor,wyslij);
  M.classList.add('on')}
if(D.egz)M.classList.add('on');
})();
