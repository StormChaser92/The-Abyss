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

/* ── Kryptogram ── */
function szyfr(el,d,kolor,gotowe){
  const lit=[...new Set(d.szyfr.replace(/[^A-Z]/g,''))].sort(),map=Object.assign({},d.odsl);
  el.innerHTML='<div class="sz" style="--k:'+kolor+'"><div class="sz-txt"></div><div class="sz-klucz"></div><button type="button" class="kbtn" style="--k:'+kolor+'">Sprawdź odczyt</button><p class="sd-hint">Każda litera szyfru zastępuje jedną literę tekstu. Część jest już odsłonięta — wpisz resztę. Błędny odczyt kończy próbę.</p></div>';
  const txt=el.querySelector('.sz-txt'),kl=el.querySelector('.sz-klucz');
  kl.innerHTML=lit.map(l=>'<label><b>'+l+'</b><input maxlength="1" data-l="'+l+'" value="'+(map[l]||'')+'"'+(d.odsl[l]?' readonly class="fix"':'')+'></label>').join('');
  const rys=()=>{txt.innerHTML=d.szyfr.split(' ').map(w=>'<span class="sz-w">'+[...w].map(c=>/[A-Z]/.test(c)?'<span class="sz-c'+(d.odsl[c]?' fix':'')+'"><i>'+(map[c]||'·')+'</i><small>'+c+'</small></span>':'<span class="sz-c"><i>'+c+'</i><small></small></span>').join('')+'</span>').join('')};
  kl.addEventListener('input',e=>{const i=e.target;i.value=i.value.toUpperCase().replace(/[^A-Z]/g,'');map[i.dataset.l]=i.value;rys();if(i.value){const n=[...kl.querySelectorAll('input:not([readonly])')].find(x=>!x.value);if(n)n.focus()}});
  el.querySelector('button').onclick=()=>{const o=[...d.szyfr].map(c=>/[A-Z]/.test(c)?(map[c]||'?'):c).join('');if(o.includes('?')){alert('Uzupełnij wszystkie litery.');return}el.firstChild.classList.add('done');setTimeout(()=>gotowe(o),500)};
  rys();
}

/* ── Memory (karty odkrywa serwer) ── */
function memory(el,d,kolor,gotowe){
  el.innerHTML='<div class="mm" style="--k:'+kolor+'"><div class="mm-grid">'+Array.from({length:d.ile},(_,i)=>'<button type="button" class="mm-k" data-i="'+i+'"><span></span></button>').join('')+'</div><p class="sd-hint">Odkrywaj po dwie karty i łącz pojęcie z jego znaczeniem. <b class="mm-r">Ruchy: 0</b></p></div>';
  const g=el.querySelector('.mm-grid');let blok=false,koniec=false;
  g.addEventListener('click',async e=>{const b=e.target.closest('.mm-k');if(!b||blok||koniec||b.classList.contains('on')||b.classList.contains('ok'))return;blok=true;
    const fd=new FormData();fd.append('i',b.dataset.i);
    try{const r=await(await fetch('api/uni_memory.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      if(!r.ok){blok=false;return}b.querySelector('span').textContent=r.t;b.classList.add('on');el.querySelector('.mm-r').textContent='Ruchy: '+r.ruchy;
      if(r.j===undefined){blok=false;return}
      const b2=g.querySelector('[data-i="'+r.j+'"]');
      if(r.para){b.classList.add('ok');b2.classList.add('ok');blok=false;if(r.koniec){koniec=true;el.firstChild.classList.add('done');setTimeout(()=>gotowe('ok'),800)}}
      else setTimeout(()=>{[b,b2].forEach(x=>{x.classList.remove('on');x.querySelector('span').textContent=''});blok=false},1000);
    }catch(x){blok=false}});
}

/* ── Układanka „15” ── */
function pietnastka(el,d,kolor,gotowe){
  const n=d.n,p=d.plansza.slice(),ruchy=[];let koniec=false;
  el.innerHTML='<div class="p15" style="--k:'+kolor+';--n:'+n+'"><div class="p15-grid"></div><p class="sd-hint">Kliknij kafel obok pustego pola, żeby go przesunąć. Ułóż liczby po kolei, puste pole na końcu. <b class="p15-r">Ruchy: 0</b></p></div>';
  const g=el.querySelector('.p15-grid');
  const rys=()=>{g.innerHTML=p.map((v,i)=>'<button type="button" class="p15-t'+(v?'':' pusty')+(v===i+1?' ok':'')+'" data-v="'+v+'">'+(v||'')+'</button>').join('');el.querySelector('.p15-r').textContent='Ruchy: '+ruchy.length;
    if(!koniec&&p.every((v,i)=>v===(i===n*n-1?0:i+1))){koniec=true;el.firstChild.classList.add('done');setTimeout(()=>gotowe(ruchy),700)}};
  g.addEventListener('click',e=>{const b=e.target.closest('.p15-t');if(!b||koniec)return;const v=+b.dataset.v,k=p.indexOf(v),z=p.indexOf(0);
    if(!v||Math.abs(Math.floor(k/n)-Math.floor(z/n))+Math.abs(k%n-z%n)!==1)return;p[z]=v;p[k]=0;ruchy.push(v);rys()});
  rys();
}

/* ── Nonogram ── */
function nonogram(el,d,kolor,gotowe){
  const n=d.n,g=Array.from({length:n},()=>Array(n).fill(0));let koniec=false;
  const podp=l=>{const o=[];let c=0;l.forEach(v=>{if(v===1)c++;else if(c){o.push(c);c=0}});if(c)o.push(c);return o.length?o:[0]};
  el.innerHTML='<div class="nn" style="--k:'+kolor+';--n:'+n+'"><div class="nn-grid"></div><p class="sd-hint">Liczby mówią, ile zamalowanych pól stoi kolejno w wierszu i kolumnie. Klik — zamaluj, prawy klik — oznacz jako puste.</p></div>';
  const G=el.querySelector('.nn-grid');
  const rys=()=>{let h='<div></div>'+d.k.map((k,c)=>'<div class="nn-k'+(JSON.stringify(podp(g.map(r=>r[c])))===JSON.stringify(k)?' ok':'')+'">'+k.join('<br>')+'</div>').join('');
    g.forEach((r,ri)=>{h+='<div class="nn-w'+(JSON.stringify(podp(r))===JSON.stringify(d.w[ri])?' ok':'')+'">'+d.w[ri].join(' ')+'</div>'+r.map((v,ci)=>'<button type="button" class="nn-c'+(v===1?' f':v===2?' x':'')+'" data-r="'+ri+'" data-c="'+ci+'"></button>').join('')});
    G.innerHTML=h;
    if(!koniec&&g.every((r,i)=>JSON.stringify(podp(r))===JSON.stringify(d.w[i]))&&d.k.every((k,c)=>JSON.stringify(podp(g.map(r=>r[c])))===JSON.stringify(k))){koniec=true;el.firstChild.classList.add('done');setTimeout(()=>gotowe(g.map(r=>r.map(v=>v===1?1:0))),700)}};
  const kl=(e,x)=>{const b=e.target.closest('.nn-c');if(!b||koniec)return;e.preventDefault();const r=+b.dataset.r,c=+b.dataset.c;g[r][c]=g[r][c]===x?0:x;rys()};
  G.addEventListener('click',e=>kl(e,1));G.addEventListener('contextmenu',e=>kl(e,2));
  rys();
}

/* ── Kto kłamie? ── */
function klamca(el,d,kolor,gotowe){
  el.innerHTML='<div class="kl" style="--k:'+kolor+'"><p class="sd-hint" style="max-width:520px">W laboratorium zginęła próbka. Dokładnie jedna osoba kłamie, pozostałe mówią prawdę. Kto kłamie?</p><div class="kl-l">'+d.zdania.map((z,i)=>'<div class="kl-z"><b>'+z[0]+'</b><q>„'+z[1]+'”</q><button type="button" class="kbtn" data-i="'+i+'" style="--k:'+kolor+'">To kłamca</button></div>').join('')+'</div><p class="sd-hint">Wskazanie jest ostateczne — błąd kończy próbę.</p></div>';
  el.querySelector('.kl-l').addEventListener('click',e=>{const b=e.target.closest('[data-i]');if(!b)return;if(!confirm('Wskazać: '+d.zdania[+b.dataset.i][0]+'?'))return;el.firstChild.classList.add('done');setTimeout(()=>gotowe(+b.dataset.i),500)});
}

/* ── Warcaby: wieloskok ── */
function warcaby(el,d,kolor,gotowe){
  let pos=d.bialy.slice(),cz=d.czarne.map(x=>x.join(',')),sciezka=[],koniec=false;
  el.innerHTML='<div class="wc" style="--k:'+kolor+'"><div class="wc-b"></div><div class="acts" style="display:flex;gap:8px"><button type="button" class="kbtn" data-reset style="--k:'+kolor+'">Od nowa</button></div><p class="sd-hint">Białym pionkiem zbij wszystkie czarne w jednym ruchu: klikaj kolejne pola lądowania za przeskakiwanym pionkiem. Bić można w każdą stronę.</p></div>';
  const B=el.querySelector('.wc-b');
  const rys=()=>{let h='';for(let r=0;r<8;r++)for(let c=0;c<8;c++){const ciemne=(r+c)%2===1,k=r+','+c;let x='';
      if(pos[0]===r&&pos[1]===c)x='<i class="wc-p w"></i>';else if(cz.includes(k))x='<i class="wc-p b"></i>';
      const moz=ciemne&&!koniec&&Math.abs(r-pos[0])===2&&Math.abs(c-pos[1])===2&&cz.includes(((r+pos[0])/2)+','+((c+pos[1])/2))&&!cz.includes(k);
      h+='<button type="button" class="wc-f'+(ciemne?' d':'')+(moz?' moz':'')+(sciezka.some(s=>s[0]===r&&s[1]===c)?' sl':'')+'" data-r="'+r+'" data-c="'+c+'">'+x+'</button>'}B.innerHTML=h;
    if(!koniec&&!cz.length){koniec=true;el.firstChild.classList.add('done');setTimeout(()=>gotowe(sciezka),700)}};
  B.addEventListener('click',e=>{const b=e.target.closest('.wc-f.moz');if(!b)return;const r=+b.dataset.r,c=+b.dataset.c;cz=cz.filter(k=>k!==((r+pos[0])/2)+','+((c+pos[1])/2));pos=[r,c];sciezka.push([r,c]);rys()});
  el.querySelector('[data-reset]').onclick=()=>{pos=d.bialy.slice();cz=d.czarne.map(x=>x.join(','));sciezka=[];rys()};
  rys();
}

/* ── Start łamigłówki podanej przez serwer ── */
if(D.lam){const el=$('uvLam'),f=$('uvLamForm');
  const wyslij=odp=>{f.querySelector('[name=odp]').value=JSON.stringify(odp);f.submit()};
  const T={sudoku:()=>sudoku(el,D.lam.plansza,D.lam.kolor,wyslij),obwod:()=>obwod(el,D.lam,D.lam.kolor,wyslij),szyfr:()=>szyfr(el,D.lam,D.lam.kolor,wyslij),memory:()=>memory(el,D.lam,D.lam.kolor,wyslij),
    pietnastka:()=>pietnastka(el,D.lam,D.lam.kolor,wyslij),nonogram:()=>nonogram(el,D.lam,D.lam.kolor,wyslij),klamca:()=>klamca(el,D.lam,D.lam.kolor,wyslij),warcaby:()=>warcaby(el,D.lam,D.lam.kolor,wyslij)};
  (T[D.lam.typ]||T.sudoku)();
  M.classList.add('on')}
if(D.egz)M.classList.add('on');
})();
