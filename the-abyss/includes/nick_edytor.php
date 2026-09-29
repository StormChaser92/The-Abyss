<?php
/* Edytor koloru nicka — Ustawienia i zakładka „Kolor nicka” na własnym profilu.
   Wymaga: $polaczenie, $id_gracza. Style: css/nick.css. */
require_once __DIR__ . '/nick.php';
$nke_kom = $nke_kom ?? nk_zapisz($polaczenie, (int)$id_gracza);
$nke_g = db_wiersz($polaczenie, 'SELECT ' . nk_pola() . ', avatar FROM gracze WHERE id = ?', [(int)$id_gracza]);
if ($nke_g && nk_moze_kolor($nke_g)):
    $nke_k = nk_hex_ok($nke_g['nick_kolor']) ? strtolower($nke_g['nick_kolor']) : '#ffffff';
    $nke_p = max(0, min(3, (int)$nke_g['nick_poswiata']));
    $nke_e = isset(NK_EFEKTY[$nke_g['nick_efekt'] ?? '']) ? $nke_g['nick_efekt'] : 'staly';
    $nke_nazwy = implode(', ', array_map(fn($k) => NK_RANGI[$k]['n'], array_diff(nk_rangi($nke_g), ['vip'])));
    $nke_av = function_exists('avatar_url') ? (string)avatar_url($nke_g['avatar'] ?? '') : '';
    $nke_posw = [0 => ['Brak', 'Czysty kolor'], 1 => ['Słaba', 'Lekki blask'], 2 => ['Neon', 'Wyraźna rurka'], 3 => ['Pełna', 'Oślepia']];
?>
<div class="nke-wrap" id="nick-kolor">
<?php if ($nke_kom): ?><div class="nke-kom" style="margin-bottom:16px">✓ <?php echo nk_h($nke_kom); ?></div><?php endif; ?>
<form method="POST" class="nke" action="#nick-kolor" id="nke-form">
<div class="nke-kol">
  <p class="nke-info">Twoje rangi: <b><?php echo nk_h($nke_nazwy); ?></b>. Kolor i efekt widać wszędzie, gdzie pojawia się Twój nick. Po utracie rangi nick wraca do domyślnego koloru.</p>

  <div class="nke-sek">
    <div class="nke-lbl">Kolor <em id="nke-nazwa"></em></div>
    <div class="nke-picker">
      <div class="nke-sv" id="nke-sv" tabindex="0" role="slider" aria-label="Nasycenie i jasność"><i id="nke-sv-k"></i></div>
      <div class="nke-hue" id="nke-hue" tabindex="0" role="slider" aria-label="Odcień" aria-valuemin="0" aria-valuemax="360"><i id="nke-hue-k"></i></div>
    </div>
    <div class="nke-hex-rzad">
      <span class="nke-probka" id="nke-probka"></span>
      <input class="nke-hex" id="nke-hex" name="nk_kolor" value="<?php echo $nke_k; ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-label="Kod koloru" spellcheck="false" autocomplete="off">
      <span class="nke-kontrast ok" id="nke-kontrast"></span>
    </div>
  </div>

  <div class="nke-sek">
    <div class="nke-lbl">Paleta The Abyss</div>
    <div class="nke-paleta" id="nke-paleta"><?php foreach (NK_PALETA as $c => $n) echo "<button type=\"button\" style=\"--c:$c\" data-c=\"$c\" title=\"" . nk_h($n) . "\" aria-label=\"" . nk_h($n) . "\"></button>"; ?></div>
  </div>

  <div class="nke-sek">
    <div class="nke-lbl">Ostatnio używane</div>
    <div class="nke-ostatnie" id="nke-ostatnie"></div>
  </div>

  <div class="nke-sek">
    <div class="nke-lbl">Poświata</div>
    <div class="nke-seg"><?php foreach ($nke_posw as $i => [$n, $o]) echo "<label><input type=\"radio\" name=\"nk_poswiata\" value=\"$i\"" . ($i === $nke_p ? ' checked' : '') . "><span>$n<small>$o</small></span></label>"; ?></div>
  </div>

  <div class="nke-sek">
    <div class="nke-lbl">Efekt</div>
    <div class="nke-seg"><?php foreach (NK_EFEKTY as $k => $e) echo "<label><input type=\"radio\" name=\"nk_efekt\" value=\"$k\"" . ($k === $nke_e ? ' checked' : '') . "><span>" . nk_h($e['n']) . "<small>" . nk_h($e['o']) . "</small></span></label>"; ?></div>
  </div>

  <p class="nke-uwaga" id="nke-uwaga"></p>
  <div class="nke-akcje">
    <button class="nke-btn" type="submit" name="nk_zapisz" value="1">Zapisz wygląd nicka</button>
    <button class="nke-btn ghost" type="submit" name="nk_reset" value="1" formnovalidate>Przywróć domyślny</button>
  </div>
</div>

<div class="nke-pg" aria-label="Podgląd">
  <div class="nke-box"><small>// PROFIL</small><div class="nke-pg-prof"><span class="nke-pg-av"<?php if ($nke_av) echo ' style="background-image:url(\'' . nk_h($nke_av) . '\')"'; ?>></span><div data-nke-pg></div></div></div>
  <div class="nke-box"><small>// LISTA ONLINE</small><div class="nke-pg-on"><span class="dot"></span><div data-nke-pg style="min-width:0;flex:1"></div></div></div>
  <div class="nke-box"><small>// CZAT KLUBU</small><div class="nke-pg-cz"><div><span data-nke-pg></span><time>23:47</time></div><p>Ostatnia kolejka przed zamknięciem.</p></div></div>
  <div class="nke-box"><small>// ARCHIWUM · RANKING</small><div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><div data-nke-pg style="min-width:0"></div><span style="font-family:'JetBrains Mono',monospace;color:var(--neon-green);font-size:.9em">LVL <?php echo (int)(db_wiersz($polaczenie, 'SELECT poziom FROM gracze WHERE id = ?', [(int)$id_gracza])['poziom'] ?? 1); ?></span></div></div>
</div>
</form>
</div>
<script>
(function(){
 const login=<?php echo json_encode((string)$nke_g['login']); ?>, ikony=<?php echo json_encode(nk_ikony($nke_g)); ?>;
 const PALETA=<?php echo json_encode(NK_PALETA, JSON_UNESCAPED_UNICODE); ?>;
 const $=id=>document.getElementById(id), F=$('nke-form'), H=$('nke-hex'), SV=$('nke-sv'), HU=$('nke-hue');
 const KLUCZ='abyss_nick_ostatnie';
 let hsv={h:0,s:0,v:1};

 const hex2rgb=h=>[1,3,5].map(i=>parseInt(h.substr(i,2),16));
 const rgb2hex=a=>'#'+a.map(x=>Math.round(x).toString(16).padStart(2,'0')).join('');
 function rgb2hsv([r,g,b]){r/=255;g/=255;b/=255;const M=Math.max(r,g,b),m=Math.min(r,g,b),d=M-m;let h=0;if(d){h=M===r?((g-b)/d)%6:M===g?(b-r)/d+2:(r-g)/d+4;h*=60;if(h<0)h+=360}return{h,s:M?d/M:0,v:M}}
 function hsv2rgb({h,s,v}){const c=v*s,x=c*(1-Math.abs((h/60)%2-1)),m=v-c;const[r,g,b]=h<60?[c,x,0]:h<120?[x,c,0]:h<180?[0,c,x]:h<240?[0,x,c]:h<300?[x,0,c]:[c,0,x];return[(r+m)*255,(g+m)*255,(b+m)*255]}
 const lum=h=>{const v=hex2rgb(h).map(c=>c/255).map(c=>c<=.03928?c/12.92:Math.pow((c+.055)/1.055,2.4));return .2126*v[0]+.7152*v[1]+.0722*v[2]};
 const TLO=lum('#0a0508'), kontrast=h=>(lum(h)+.05)/(TLO+.05);
 const mix=(h,t)=>rgb2hex(hex2rgb(h).map(c=>c+(255-c)*t));
 const czyt=h=>{let t=0,x=h;while(kontrast(x)<4.5&&t<1){t+=.05;x=mix(h,Math.min(1,t))}return x};
 const glow=(c,p)=>['none',`0 0 4px ${c}`,`0 0 4px ${c},0 0 10px ${c}`,`0 0 2px #fff,0 0 7px ${c},0 0 16px ${c},0 0 28px ${c}`][p];
 const esc=s=>s.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
 const wart=n=>(F.querySelector(`input[name=${n}]:checked`)||{}).value;

 function rysuj(){
  const h=H.value.trim().toLowerCase(); if(!/^#[0-9a-f]{6}$/.test(h))return;
  const c=czyt(h), p=+wart('nk_poswiata')||0, e=wart('nk_efekt')||'staly', k=kontrast(h);
  SV.style.setProperty('--nke-h',hsv.h);
  $('nke-sv-k').style.left=(hsv.s*100)+'%'; $('nke-sv-k').style.top=((1-hsv.v)*100)+'%';
  $('nke-hue-k').style.top=(hsv.h/360*100)+'%'; HU.setAttribute('aria-valuenow',Math.round(hsv.h));
  $('nke-probka').style.background=h; $('nke-probka').style.setProperty('--nke-c',c);
  const kt=$('nke-kontrast'); kt.textContent='KONTRAST '+k.toFixed(1)+':1'; kt.className='nke-kontrast '+(k>=4.5?'ok':'slabo');
  $('nke-uwaga').textContent=c!==h?`Ten kolor jest za ciemny na tle gry. Nick będzie wyświetlany jako ${c}.`:'';
  $('nke-nazwa').textContent=PALETA[h]||'';
  document.querySelectorAll('#nke-paleta button').forEach(b=>b.classList.toggle('on',b.dataset.c===h));
  const kl='nk-n'+(e!=='staly'?' nk-e-'+e:'');
  const html=`<span class="nk"><span class="${kl}" style="--nk-c:${c};color:${c};text-shadow:${glow(c,p)}">${esc(login)}</span>${ikony}</span>`;
  document.querySelectorAll('[data-nke-pg]').forEach(el=>el.innerHTML=html);
 }
 function zHex(h,zHsv){h=h.toLowerCase(); if(!/^#[0-9a-f]{6}$/.test(h))return; H.value=h; if(zHsv!==false)hsv=rgb2hsv(hex2rgb(h)); rysuj()}
 const zHsvU=()=>{H.value=rgb2hex(hsv2rgb(hsv)); rysuj()};

 function ciagnij(el,fn){
  const ruch=ev=>{const r=el.getBoundingClientRect();fn(Math.min(1,Math.max(0,(ev.clientX-r.left)/r.width)),Math.min(1,Math.max(0,(ev.clientY-r.top)/r.height)))};
  el.addEventListener('pointerdown',ev=>{el.setPointerCapture(ev.pointerId);ruch(ev);el.addEventListener('pointermove',ruch)});
  el.addEventListener('pointerup',()=>el.removeEventListener('pointermove',ruch));
  el.addEventListener('pointercancel',()=>el.removeEventListener('pointermove',ruch));
 }
 ciagnij(SV,(x,y)=>{hsv.s=x;hsv.v=1-y;zHsvU()});
 ciagnij(HU,(x,y)=>{hsv.h=Math.min(359.9,y*360);zHsvU()});
 SV.addEventListener('keydown',e=>{const d=e.shiftKey?.1:.02,m={ArrowLeft:[-d,0],ArrowRight:[d,0],ArrowUp:[0,d],ArrowDown:[0,-d]}[e.key];if(!m)return;e.preventDefault();hsv.s=Math.min(1,Math.max(0,hsv.s+m[0]));hsv.v=Math.min(1,Math.max(0,hsv.v+m[1]));zHsvU()});
 HU.addEventListener('keydown',e=>{const d=(e.shiftKey?15:3)*({ArrowUp:-1,ArrowDown:1}[e.key]||0);if(!d)return;e.preventDefault();hsv.h=(hsv.h+d+360)%360;zHsvU()});
 H.addEventListener('input',()=>zHex(H.value.trim()));
 $('nke-paleta').addEventListener('click',e=>{const c=e.target.dataset&&e.target.dataset.c;if(c)zHex(c)});
 F.addEventListener('change',e=>{if(e.target.name==='nk_poswiata'||e.target.name==='nk_efekt')rysuj()});

 function ostatnie(){let l=[];try{l=JSON.parse(localStorage.getItem(KLUCZ)||'[]')}catch(e){}return l.filter(c=>/^#[0-9a-f]{6}$/.test(c)).slice(0,8)}
 $('nke-ostatnie').innerHTML=ostatnie().map(c=>`<button type="button" style="--c:${c}" data-c="${c}" title="${c}" aria-label="${c}"></button>`).join('');
 $('nke-ostatnie').addEventListener('click',e=>{const c=e.target.dataset&&e.target.dataset.c;if(c)zHex(c)});
 F.addEventListener('submit',e=>{if(e.submitter&&e.submitter.name==='nk_zapisz'){const h=H.value.trim().toLowerCase();try{localStorage.setItem(KLUCZ,JSON.stringify([h,...ostatnie().filter(c=>c!==h)].slice(0,8)))}catch(err){}}});

 zHex(H.value.trim());
})();
</script>
<?php else: ?>
<p class="nke-info">Własny kolor nicka mają gracze z rangą (Administrator/ka, Mistrz/yni Gry, Junior MG, Gospodarz/yni Klubu, Barman/ka, Proboszcz).<br>
<span style="font-family:'JetBrains Mono',monospace;font-size:.85em;color:var(--txt-mute)">// konto #<?php echo (int)$id_gracza; ?> · rangi: <?php echo $nke_g ? (nk_h(implode(', ', nk_rangi($nke_g))) ?: 'brak') : 'nie znaleziono konta'; ?></span></p>
<?php endif; ?>
