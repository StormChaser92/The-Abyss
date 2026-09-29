<?php
/* Edytor koloru nicka — wspólny dla pages/ustawienia.php i własnego profilu.
   Wymaga: $polaczenie, $id_gracza. Gracz bez rangi nic tu nie widzi. */
require_once __DIR__ . '/nick.php';
$nke_kom = $nke_kom ?? nk_zapisz($polaczenie, (int)$id_gracza);
$nke_g = db_wiersz($polaczenie, 'SELECT ' . nk_pola() . ' FROM gracze WHERE id = ?', [(int)$id_gracza]);
if ($nke_g && nk_moze_kolor($nke_g)):
    $nke_k = nk_hex_ok($nke_g['nick_kolor']) ? strtolower($nke_g['nick_kolor']) : '#ffffff';
    $nke_p = max(0, min(3, (int)$nke_g['nick_poswiata']));
    $nke_nazwy = implode(', ', array_map(fn($k) => NK_RANGI[$k]['n'], array_diff(nk_rangi($nke_g), ['vip'])));
?>
<div class="nke-wrap" id="nick-kolor">
<?php if ($nke_kom): ?><div class="nke-kom">✓ <?php echo nk_h($nke_kom); ?></div><?php endif; ?>
<p class="nke-info">Twoje rangi: <b style="color:#fff"><?php echo nk_h($nke_nazwy); ?></b>. Kolor widać w każdym miejscu, gdzie pojawia się Twój nick. Zbyt ciemny kolor gra rozjaśni, żeby nick był czytelny. Po utracie rangi nick wraca do domyślnego koloru.</p>
<form method="POST" class="nke" action="#nick-kolor">
<div>
<div class="nke-pole"><label class="nke-lbl" for="nke-kolor">Kolor</label>
<div class="nke-rzad"><input type="color" id="nke-kolor" value="<?php echo $nke_k; ?>"><input class="nke-hex" id="nke-hex" name="nk_kolor" value="<?php echo $nke_k; ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-label="Kod koloru"></div>
<div class="nke-szybkie" id="nke-szybkie"><?php foreach (NK_SZYBKIE as $c) echo "<button type=\"button\" style=\"background:$c\" data-c=\"$c\" title=\"$c\" aria-label=\"$c\"></button>"; ?></div></div>
<div class="nke-pole"><span class="nke-lbl">Poświata</span>
<div class="nke-posw"><?php for ($i = 0; $i <= 3; $i++) echo "<label><input type=\"radio\" name=\"nk_poswiata\" value=\"$i\"" . ($i === $nke_p ? ' checked' : '') . "><span>$i</span></label>"; ?></div></div>
<p class="nke-uwaga" id="nke-uwaga"></p>
<div class="nke-akcje"><button class="nke-btn" type="submit" name="nk_zapisz" value="1">Zapisz</button><button class="nke-btn ghost" type="submit" name="nk_reset" value="1">Przywróć domyślny</button></div>
</div>
<div class="nke-pg">
<div class="nke-box"><small>// PROFIL</small><div class="nke-duzy" data-nke-pg></div></div>
<div class="nke-box"><small>// LISTA ONLINE</small><div data-nke-pg></div></div>
<div class="nke-box"><small>// CZAT</small><div data-nke-pg></div></div>
</div>
</form>
</div>
<script>
(function(){
 const login=<?php echo json_encode((string)$nke_g['login']); ?>, ikony=<?php echo json_encode(nk_ikony($nke_g)); ?>;
 const K=document.getElementById('nke-kolor'),H=document.getElementById('nke-hex'),U=document.getElementById('nke-uwaga'),F=K.form;
 const lum=h=>{const v=[1,3,5].map(i=>parseInt(h.substr(i,2),16)/255).map(c=>c<=.03928?c/12.92:Math.pow((c+.055)/1.055,2.4));return .2126*v[0]+.7152*v[1]+.0722*v[2]};
 const tlo=lum('#0a0508'),mix=(h,t)=>'#'+[1,3,5].map(i=>{const c=parseInt(h.substr(i,2),16);return Math.round(c+(255-c)*t).toString(16).padStart(2,'0')}).join('');
 const czyt=h=>{let t=0,x=h;while((lum(x)+.05)/(tlo+.05)<4.5&&t<1){t+=.05;x=mix(h,Math.min(1,t))}return x};
 const glow=(c,p)=>['none',`0 0 4px ${c}`,`0 0 4px ${c},0 0 10px ${c}`,`0 0 2px #fff,0 0 7px ${c},0 0 16px ${c},0 0 28px ${c}`][p];
 const esc=s=>s.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
 function rysuj(){
  const h=H.value.trim().toLowerCase(); if(!/^#[0-9a-f]{6}$/.test(h))return;
  const c=czyt(h),p=+(F.querySelector('input[name=nk_poswiata]:checked')||{value:0}).value;
  U.textContent=c!==h?`Ten kolor jest za ciemny na tle gry. Nick będzie wyświetlany jako ${c}.`:'';
  document.querySelectorAll('[data-nke-pg]').forEach(el=>el.innerHTML=`<span class="nk"><span class="nk-n" style="color:${c};text-shadow:${glow(c,p)}">${esc(login)}</span>${ikony}</span>`);
 }
 const ustaw=h=>{if(/^#[0-9a-f]{6}$/i.test(h)){K.value=h.toLowerCase();H.value=h.toLowerCase()}rysuj()};
 K.addEventListener('input',e=>ustaw(e.target.value));
 H.addEventListener('input',rysuj);
 document.getElementById('nke-szybkie').addEventListener('click',e=>{const c=e.target.dataset&&e.target.dataset.c;if(c)ustaw(c)});
 F.addEventListener('change',e=>{if(e.target.name==='nk_poswiata')rysuj()});
 rysuj();
})();
</script>
<?php else: ?>
<p class="nke-info">Własny kolor nicka mają gracze z rangą (Administrator/ka, Mistrz/yni Gry, Junior MG, Gospodarz/yni Klubu, Barman/ka, Proboszcz).<br>
<span style="font-family:'JetBrains Mono',monospace;font-size:.85em;color:var(--txt-mute)">// konto #<?php echo (int)$id_gracza; ?> · rangi: <?php echo $nke_g ? (nk_h(implode(', ', nk_rangi($nke_g))) ?: 'brak') : 'nie znaleziono konta'; ?></span></p>
<?php endif; ?>
