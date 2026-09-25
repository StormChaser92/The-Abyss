<?php
/* THE ABYSS — INCLUDES/KARTA_ZALETY.PHP
   Sekcja „Zalety i Wady” na karcie postaci (Punkty Zdolności).
   Zasady i dane: config/zalety_wady.php. */

function zw_render(array $g, array $ZDEF, array $WDEF, array $pary, int $pula, string $blad = ''): void {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $lista = fn($s) => (!$s || $s === 'Brak') ? [] : array_values(array_filter(array_map('trim', explode(',', $s)), 'strlen'));
    $mz = $lista($g['zalety'] ?? ''); $mw = $lista($g['wady'] ?? '');
    $uzyta = !empty($g['zw_zmiana_uzyta']);
    $AN = ['S' => 'Siła', 'Z' => 'Zręczność', 'W' => 'Wytrzymałość', 'I' => 'Inteligencja', 'Zm' => 'Zmysły', 'Ch' => 'Charyzma'];
    $karty = function (array $DEF, array $KAT, array $KMAP, array $moje, string $pole, bool $zal) use ($h, $AN, $uzyta) {
        $grupy = [];
        foreach ($DEF as $n => $d) $grupy[$KMAP[$n] ?? array_key_first($KAT)][] = [$n, $d];
        foreach ($KAT as $k => $nazwa) {
            if (empty($grupy[$k])) continue;
            echo "<section class='zw-sec' data-kat='$k'><h4>" . $h($nazwa) . "</h4><div class='zw-grid'>";
            foreach ($grupy[$k] as [$n, $d]) {
                $on = in_array($n, $moje, true); $t = $zal ? (ZW_TEST[$n] ?? '') : '';
                echo "<label class='zw-c" . ($on ? ' on' : '') . "' data-n='" . $h($n) . "'><input type='checkbox' name='{$pole}[]' value='" . $h($n) . "'" . ($on ? ' checked' : '') . ($uzyta ? ' disabled' : '') . ">"
                   . "<b>" . $h($n) . "</b><p>" . $h($d['opis'] ?? '') . "</p><span class='zw-m'><i class='zw-x'></i><s class='pz'>" . ($zal ? '−1 PZ' : '+1 PZ') . "</s>"
                   . ($t ? "<s class='a'>Test: " . $h(implode(' + ', array_map(fn($a) => $AN[$a] ?? $a, explode('+', $t)))) . "</s>" : '') . "</span>"
                   . (!empty($d['wplyw']) ? "<em>" . $h($d['wplyw']) . "</em>" : '') . "</label>";
            }
            echo "</div></section>";
        }
    };
    ?>
<style>
.zw{--g:#3dff9a;--r:#ff3d5e;--dim:#cfc6d2;display:flex;flex-direction:column;gap:14px;color:#f1ebf2}
.zw .lbl{font-family:'JetBrains Mono',monospace;font-size:.72em;letter-spacing:1.6px;text-transform:uppercase;color:var(--dim)}
.zw-pz{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.zw-pz>div{border:1px solid rgba(255,23,68,.22);background:rgba(0,0,0,.5);padding:11px 13px;display:flex;flex-direction:column;gap:4px}
.zw-pz b{font-family:'Oswald',sans-serif;font-size:1.9em;font-weight:500;line-height:1}
.zw-pz small{color:var(--dim);line-height:1.35}
.zw-pz .bad b{color:var(--r)}.zw-pz .ok b{color:var(--g)}
.zw-met{height:8px;background:rgba(255,255,255,.08);display:flex}.zw-met i{display:block;height:100%}
.zw-info{padding:10px 12px;border:1px solid rgba(74,214,255,.4);background:rgba(74,214,255,.06);color:#e3f7ff;line-height:1.45}
.zw-err{padding:10px 12px;border:1px solid var(--r);color:var(--r);font-family:'JetBrains Mono',monospace;font-size:.88em}
.zw-tabs{display:flex;gap:2px;border-bottom:1px solid rgba(255,23,68,.22)}
.zw-tabs button{padding:9px 16px;background:rgba(0,0,0,.35);border:1px solid transparent;border-bottom:0;color:var(--dim);font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer}
.zw-tabs button.on{color:#fff;background:rgba(255,23,68,.16);border-color:rgba(255,23,68,.45)}
.zw-tabs i{font-style:normal;font-family:'JetBrains Mono',monospace;margin-left:6px;color:#ffd23d}
.zw-bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.zw-bar button{padding:5px 10px;border:1px solid rgba(255,255,255,.16);background:transparent;color:var(--dim);font-family:'Oswald',sans-serif;letter-spacing:1.2px;text-transform:uppercase;font-size:.78em;cursor:pointer}
.zw-bar button.on{color:#fff;border-color:#ff1744;box-shadow:inset 0 0 0 1px #ff1744}
.zw-bar input[type=search]{flex:1 1 200px;background:rgba(0,0,0,.55);border:1px solid rgba(255,23,68,.22);color:#fff;padding:7px 11px;font-family:inherit}
.zw-bar label{display:flex;gap:6px;align-items:center;color:var(--dim);cursor:pointer}
.zw-pane{display:none;flex-direction:column;gap:14px}.zw-pane.on{display:flex}
.zw-sec h4{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:3px;text-transform:uppercase;border-bottom:1px solid rgba(255,23,68,.22);padding-bottom:6px;margin-bottom:8px;color:#fff}
.zw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px}
.zw-c{position:relative;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.5);padding:10px 12px 10px 38px;display:flex;flex-direction:column;gap:5px;cursor:pointer;transition:opacity .45s,filter .45s,transform .45s,border-color .3s}
.zw-c input{position:absolute;opacity:0;pointer-events:none}
.zw-c::before{content:'';position:absolute;left:12px;top:13px;width:16px;height:16px;border:1.5px solid rgba(255,255,255,.3)}
.zw-c.on{border-color:var(--k);box-shadow:inset 0 0 0 1px var(--k)}
.zw-c.on::before{background:var(--k);border-color:var(--k);box-shadow:0 0 8px var(--k)}
.zw-c.off{opacity:.38;cursor:not-allowed}
.zw-c.blk{opacity:.22;filter:grayscale(1);transform:scale(.98);cursor:not-allowed}
.zw-c.blk b{text-decoration:line-through;text-decoration-color:var(--r)}
.zw-c.flash{animation:zwZ .6s ease}
@keyframes zwZ{0%{opacity:1;filter:none;transform:none;box-shadow:0 0 0 2px var(--r)}}
.zw-c b{font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:.8px;color:#fff}
.zw-c p{color:var(--dim);line-height:1.38;font-size:.93em;margin:0}
.zw-c em{display:none;font-style:normal;color:#e3dce6;font-size:.88em;line-height:1.4;border-top:1px dotted rgba(255,255,255,.12);padding-top:5px}
.zw-c:hover em{display:block}
.zw-m{display:flex;gap:5px;flex-wrap:wrap}
.zw-m s,.zw-m i{text-decoration:none;font-style:normal;font-family:'JetBrains Mono',monospace;font-size:.66em;letter-spacing:1.1px;text-transform:uppercase;padding:1px 6px;border:1px solid currentColor}
.zw-m .pz{color:var(--k)}.zw-m .a{color:#4ad6ff}
.zw-m .zw-x{display:none;color:var(--r);border-style:dashed;text-transform:none;letter-spacing:.3px}
.zw-c.blk .zw-x{display:inline}
.zw-foot{position:sticky;bottom:0;z-index:5;display:flex;flex-wrap:wrap;gap:8px 20px;align-items:center;padding:10px 14px;background:rgba(10,5,10,.95);border:1px solid #ff1744}
.zw-foot span{font-family:'JetBrains Mono',monospace;font-size:.82em;color:var(--dim)}
.zw-foot span b{font-family:'Oswald',sans-serif;font-size:1.3em;color:#fff;font-weight:500;margin-left:5px}
.zw-foot .msg{color:var(--r);font-family:'Rajdhani',sans-serif;font-size:.95em}
.zw-foot button{margin-left:auto;padding:10px 18px;border:1px solid #ffd23d;background:rgba(255,210,61,.1);color:#fff;font-family:'Oswald',sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer}
.zw-foot button:disabled{opacity:.35;cursor:not-allowed}
@media (max-width:800px){.zw-pz{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="blok"><div class="blok-tytul">🎭 Zalety i Wady <span class="note">Punkty Zdolności</span></div>
<form method="POST" action="game.php?page=karta" class="zw" id="zw" onsubmit="return confirm('Zapisać Zalety i Wady? To Twoja jedyna darmowa zmiana — później zmienia je tylko MG.')">
  <input type="hidden" name="zapisz_cechy" value="1">
  <p style="color:var(--dim);line-height:1.5;margin:0">Każda Zaleta kosztuje 1 PZ, każda Wada oddaje 1 PZ. Razem najwyżej <?php echo ZW_MAKS; ?>. Zalety są też Zdolnościami: bez danej Zalety MG nie wykona testu Zdolności — jest automatycznie nieudany.</p>
  <?php if ($blad): ?><div class="zw-err">⚠ <?php echo $h($blad); ?></div><?php endif; ?>
  <div class="zw-info"><?php echo $uzyta ? '<b>Darmowa zmiana wykorzystana.</b> Kolejne zmiany Zalet i Wad wprowadza Mistrz Gry w Podsumowaniu Opowieści.' : '<b>Masz jedną darmową zmianę.</b> Po zapisaniu Zalety i Wady zmienia już tylko Mistrz Gry.'; ?></div>
  <div class="zw-pz">
    <div><span class="lbl">Pula PZ</span><b><?php echo $pula; ?></b><small><?php echo ZW_PULA_START; ?> + 1 co <?php echo ZW_CO_POZIOMOW; ?> poziomów (poziom <?php echo (int)$g['poziom']; ?>)</small></div>
    <div id="zwWolB" class="ok"><span class="lbl">Wolne PZ</span><b id="zwWol">0</b><small>pula + Wady − Zalety</small></div>
    <div><span class="lbl">Rozdysponowane</span><b id="zwR">0 / <?php echo ZW_MAKS; ?></b><div class="zw-met"><i id="zwMZ" style="background:var(--g)"></i><i id="zwMW" style="background:var(--r)"></i></div></div>
    <div><span class="lbl">Wybrane</span><b id="zwW">0 · 0</b><small>Zalety · Wady</small></div>
  </div>
  <div class="zw-tabs"><button type="button" class="on" data-t="z">Zalety<i id="zwTZ">0</i></button><button type="button" data-t="w">Wady<i id="zwTW">0</i></button></div>
  <div class="zw-bar"><input type="search" id="zwQ" placeholder="Szukaj…"><label><input type="checkbox" id="zwTylko"> Tylko wybrane</label></div>
  <div class="zw-pane on" id="zw-z" style="--k:var(--g)"><?php $karty($ZDEF, ZW_KAT_Z, ZW_KATEGORIA_Z, $mz, 'zalety', true); ?></div>
  <div class="zw-pane" id="zw-w" style="--k:var(--r)"><?php $karty($WDEF, ZW_KAT_W, ZW_KATEGORIA_W, $mw, 'wady', false); ?></div>
  <div class="zw-foot"><span>Zalety<b id="zwFZ">0</b></span><span>Wady<b id="zwFW">0</b></span><span>Wolne PZ<b id="zwFWol">0</b></span><span class="msg" id="zwMsg"></span>
    <?php if (!$uzyta): ?><button type="submit" id="zwSave">Zapisz (darmowa zmiana)</button><?php endif; ?></div>
</form></div>
<script>
(function(){const F=document.getElementById('zw');if(!F)return;const PULA=<?php echo (int)$pula; ?>,MAX=<?php echo ZW_MAKS; ?>,ZABL=<?php echo $uzyta ? 'true' : 'false'; ?>,P=<?php echo json_encode(array_values($pary), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
const K={};P.forEach(([a,b])=>{(K[a]=K[a]||new Set()).add(b);(K[b]=K[b]||new Set()).add(a)});
const $=s=>F.querySelector(s),C=[...F.querySelectorAll('.zw-c')];let prev=new Set(),q='',tyl=false;
function rys(){const sel=new Set(C.filter(c=>c.querySelector('input').checked).map(c=>c.dataset.n));
 const z=F.querySelectorAll('input[name="zalety[]"]:checked').length,w=F.querySelectorAll('input[name="wady[]"]:checked').length,wol=PULA+w-z,r=z+w;
 const now=new Set();
 C.forEach(c=>{const i=c.querySelector('input'),n=c.dataset.n,on=i.checked,kol=on?[]:[...(K[n]||[])].filter(x=>sel.has(x)),blk=kol.length>0;
  const zal=i.name==='zalety[]',off=!on&&!blk&&(ZABL||r>=MAX||(zal&&wol<=0));
  c.classList.toggle('on',on);c.classList.toggle('blk',blk);c.classList.toggle('off',off);
  if(blk){now.add(n);c.querySelector('.zw-x').textContent='Wyklucza: '+kol.join(', ');if(!prev.has(n)){c.classList.remove('flash');void c.offsetWidth;c.classList.add('flash')}}
  const ok=(!q||(n+' '+c.textContent).toLowerCase().includes(q))&&(!tyl||on);c.style.display=ok?'':'none'});
 F.querySelectorAll('.zw-sec').forEach(s=>s.style.display=[...s.querySelectorAll('.zw-c')].some(c=>c.style.display!=='none')?'':'none');
 prev=now;
 $('#zwWol').textContent=wol;$('#zwWolB').className=wol<0?'bad':'ok';$('#zwR').textContent=r+' / '+MAX;$('#zwW').textContent=z+' · '+w;
 $('#zwMZ').style.width=z/MAX*100+'%';$('#zwMW').style.width=w/MAX*100+'%';$('#zwTZ').textContent=z;$('#zwTW').textContent=w;$('#zwFZ').textContent=z;$('#zwFW').textContent=w;$('#zwFWol').textContent=wol;
 if(!ZABL)$('#zwMsg').textContent=wol<0?'Za mało PZ — dodaj Wadę albo zrezygnuj z Zalety.':r>MAX?'Najwyżej '+MAX+' PZ łącznie.':r>=MAX?'Rozdysponowano maksymalnie '+MAX+' PZ.':'';
 const s=$('#zwSave');if(s)s.disabled=wol<0||r>MAX}
F.addEventListener('click',e=>{const c=e.target.closest('.zw-c');if(!c||ZABL)return;const i=c.querySelector('input');
 if(c.classList.contains('blk')||c.classList.contains('off')){e.preventDefault();if(c.classList.contains('blk'))$('#zwMsg').textContent='„'+c.dataset.n+'” '+c.querySelector('.zw-x').textContent.toLowerCase()+'.';return}
 if(i.checked&&i.name==='wady[]'){const z=F.querySelectorAll('input[name="zalety[]"]:checked').length,w=F.querySelectorAll('input[name="wady[]"]:checked').length;if(PULA+w-1-z<0){e.preventDefault();$('#zwMsg').textContent='Najpierw zrezygnuj z Zalety — bez tej Wady zabraknie PZ.';return}}});
F.addEventListener('change',rys);
F.querySelectorAll('.zw-tabs button').forEach(b=>b.onclick=()=>{F.querySelectorAll('.zw-tabs button').forEach(x=>x.classList.toggle('on',x===b));F.querySelectorAll('.zw-pane').forEach(p=>p.classList.toggle('on',p.id==='zw-'+b.dataset.t))});
$('#zwQ').oninput=e=>{q=e.target.value.trim().toLowerCase();rys()};$('#zwTylko').onchange=e=>{tyl=e.target.checked;rys()};
C.forEach(c=>{if(c.querySelector('input').checked)return});rys();prev=new Set(C.filter(c=>c.classList.contains('blk')).map(c=>c.dataset.n));
})();
</script>
<?php
}
