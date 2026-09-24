<?php
/* ═══════════════════════════════════════════════════════════════════════
   THE ABYSS — PAGES/MIASTO.PHP  ·  „Wejdź do Miasta" = Broadway
   Drogowskazy do wszystkich lokacji. Po najechaniu z cienia wyłania się karta miejsca.

   GRAFIKI LOKACJI: wrzuć do  the-abyss/img/miasto/  pod nazwą klucza z listy poniżej,
   np. img/miasto/kasyno.jpg, img/miasto/doki.jpg (jpg / png / webp, najlepiej 16:10,
   ok. 960×600). Brak pliku = zastępczy gradient z ikoną.
   ═══════════════════════════════════════════════════════════════════════ */
$bw_dane = json_decode(<<<'JSON'
[
 {"id":"broadway","ulica":"Broadway","kwartal":"Midtown · Theater District","kolor":"#ff1744","miejsca":[
  {"k":"czat","n":"The Abyss Club","i":"🍸","lead":"Najgłośniejszy klub w mieście.","o":"Idealne miejsce na plotki, szukanie zleceń i wydawanie gotówki. Bar, sala główna, sauna i tyły dla wtajemniczonych."},
  {"k":"kasyno","n":"Kasyno Golden Dragon","i":"🎰","lead":"Zaryzykuj wszystko. Zdobądź miasto.","o":"Ruletka, blackjack, video poker i Złoty Smok z jedną pulą dla całego miasta."},
  {"k":"sesje","n":"Centrum Opowieści","i":"🎭","lead":"Miasto tysiąca historii.","o":"Dołącz do trwających śledztw, rekrutacji do syndykatów lub napisz własną legendę."},
  {"k":"katedra","n":"Katedra","i":"⛪","t":"Śluby","lead":"Stare mury pośród neonów.","o":"Tu można wziąć ślub. Cisza, świece i ksiądz, który nie zadaje pytań."}
 ]},
 {"id":"wall","ulica":"Wall St","kwartal":"Financial District","kolor":"#ffd700","miejsca":[
  {"k":"bank","n":"Bank The Abyss","i":"🏦","lead":"Jedyna instytucja w mieście, która nie ukradnie Twoich pieniędzy w ciemnym zaułku.","o":"Konto, depozyty i przelewy między mieszkańcami."},
  {"k":"firma","n":"Urząd Miasta","i":"🏛️","t":"VIP","lead":"Jeden adres, cztery kontuary.","o":"Rejestracja firm, akta obywateli, nieruchomości i dyskretne usługi specjalne."},
  {"k":"lista_firm","n":"Katalog firm","i":"🏢","lead":"Pełen rejestr legalnych firm w mieście.","o":"Od podziemnych klubów po galerie. Sprawdź, kto zatrudnia i co sprzedaje."},
  {"k":"premium","n":"Klub Premium","i":"★","t":"VIP","lead":"W Manhattanie liczą się tylko ci, którzy na siebie pracują.","o":"Reszta tylko płaci za drinki."}
 ]},
 {"id":"uptown","ulica":"5th Ave","kwartal":"Uptown","kolor":"#4ad6ff","miejsca":[
  {"k":"uniwersytet","n":"Akademia Nauk","i":"🎓","lead":"Wiedza to potęga.","o":"A w tym mieście potęga decyduje o tym, kto przeżyje. Dyplomy i egzaminy przed komisją."},
  {"k":"szpital","n":"Klinika „U Rzeźnika”","i":"🏥","lead":"Dostałeś kulkę w żebra?","o":"Rzeźnik poskłada Cię do kupy, oczywiście jeśli masz czym zapłacić."},
  {"k":"silownia","n":"Prywatna Siłownia","i":"🏋️","lead":"Żelazo nie kłamie.","o":"Trening siły i kondycji przed kolejną nocą w zaułkach."},
  {"k":"ranking","n":"Ranking","i":"🏆","lead":"Kto rządzi tym miastem.","o":"Lista najgroźniejszych i najbogatszych mieszkańców."},
  {"k":"lotnisko","n":"Port Lotniczy","i":"✈️","lead":"Bilet w jedną stronę.","o":"Loty do innych miast. Każde ma własną pogodę, własne areny i własne okazje."}
 ]},
 {"id":"doki","ulica":"Red Hook","kwartal":"Nabrzeże · strefa cienia","kolor":"#ff7a3d","miejsca":[
  {"k":"doki","n":"Arena Zaułków","i":"⚔️","t":"Walka","lead":"Sto rund na walkę.","o":"Niebezpieczne rewiry. Gangi, szczury i przeciwnicy, którzy nie oddają pola."},
  {"k":"rynek","n":"Czarny Rynek","i":"🕶️","lead":"Żadnych pytań.","o":"Jeśli masz gotówkę, tu kupisz każdą nielegalną broń i pancerz z przemytu."},
  {"k":"sklep","n":"Lombard „Rdza i Krew”","i":"🛠️","t":"Dla nowych","lead":"Trzynaście sztuk na wejście.","o":"Wszystko cięższe kuje Inżynier."},
  {"k":"warsztat","n":"Manufaktura","i":"🔧","lead":"Od rurki z barierki do karabinu przeciwpancernego.","o":"Kuj, ulepszaj, przyjmuj zlecenia i licz się z tym, że ręka zadrży."},
  {"k":"zlecenia","n":"Zlecenia u Inżyniera","i":"📜","lead":"Płacisz z góry.","o":"Ulepszać broń umie tylko Inżynier z warsztatem. Jeśli mu nie wyjdzie, pieniądze wracają, ale broń schodzi o stopień."},
  {"k":"zlomowisko","n":"Złomowisko","i":"🔩","lead":"Góry blachy do przekopania.","o":"Części na broń i towar na sprzedaż, dla tych, którzy nie boją się brudnych rąk."},
  {"k":"laboratorium","n":"Laboratorium Chemiczne","i":"🧪","lead":"Piwnica z wentylacją.","o":"Receptury, odczynniki i produkty, o które nikt głośno nie pyta."},
  {"k":"syndykaty","n":"Syndykaty Miasta","i":"🏴","lead":"Rodziny, klany, podziemie.","o":"Samotne wilki giną tu najszybciej. Załóż własne imperium lub dołącz do potężnego gangu."}
 ]}
]
JSON, true);

$bw_root = dirname(__DIR__);
foreach ($bw_dane as &$d) foreach ($d['miejsca'] as &$m) {
    $m['img'] = '';
    foreach (['jpg','jpeg','png','webp'] as $ext) {
        if (is_file("$bw_root/img/miasto/{$m['k']}.$ext")) { $m['img'] = "img/miasto/{$m['k']}.$ext"; break; }
    }
}
unset($d, $m);
?>
<style>
.bw{--bw-bulb:#ffd27a}
.bw-marq{position:relative;padding:30px 28px 26px;margin-bottom:26px;border:1px solid var(--border-mid);background:linear-gradient(135deg,rgba(255,255,255,.13),rgba(255,255,255,.03) 38%,rgba(255,23,68,.07) 100%);backdrop-filter:blur(16px) saturate(150%);-webkit-backdrop-filter:blur(16px) saturate(150%);border-color:rgba(255,255,255,.18);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),inset 0 -1px 0 rgba(255,23,68,.35),0 10px 40px rgba(0,0,0,.45),0 0 30px rgba(255,23,68,.14);text-align:center;overflow:hidden}
.bw-marq::before{content:'';position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle,var(--bw-bulb) 0 2px,rgba(255,210,122,.35) 3px,transparent 5px) 0 4px/20px 12px repeat-x,radial-gradient(circle,var(--bw-bulb) 0 2px,rgba(255,210,122,.35) 3px,transparent 5px) 10px calc(100% - 4px)/20px 12px repeat-x;animation:bw-chase 1.6s steps(2) infinite;opacity:.85}
@keyframes bw-chase{to{background-position:20px 4px,30px calc(100% - 4px)}}
.bw-marq .eyebrow{font-family:'JetBrains Mono',monospace;font-size:.75em;letter-spacing:4px;text-transform:uppercase;color:var(--neon-red);text-shadow:0 0 6px rgba(255,23,68,.5);margin-bottom:8px}
.bw-marq h1{font-family:'Oswald',sans-serif;font-weight:600;font-size:3em;letter-spacing:10px;text-transform:uppercase;color:#fff;line-height:1;text-shadow:0 0 18px rgba(255,23,68,.7),0 0 2px #fff}
.bw-marq p{margin-top:10px;color:var(--txt-dim);font-size:1.05em;text-wrap:pretty}
.bw{container-type:inline-size}.bw-uklad{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:24px;align-items:start}
.bw-aleja{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:22px 16px}
.bw-slup{--c:#ff1744;position:relative;padding:0 0 18px;display:flex;flex-direction:column;align-items:center}
.bw-slup::before{content:'';position:absolute;top:8px;bottom:0;left:50%;width:6px;margin-left:-3px;background:linear-gradient(90deg,#16121a,#3a3440 45%,#0c0a0e);border-radius:2px;box-shadow:0 0 10px rgba(0,0,0,.8)}
.bw-slup::after{content:'';position:absolute;bottom:0;left:50%;width:34px;height:8px;margin-left:-17px;background:#1c1820;border-radius:2px 2px 0 0;box-shadow:0 0 16px color-mix(in srgb,var(--c) 45%,transparent)}
.bw-tabl{position:relative;z-index:1;width:100%;padding:9px 10px 8px;background:linear-gradient(160deg,rgba(255,255,255,.16),rgba(255,255,255,.04) 45%,color-mix(in srgb,var(--c) 10%,transparent));backdrop-filter:blur(12px) saturate(150%);-webkit-backdrop-filter:blur(12px) saturate(150%);border:1px solid rgba(255,255,255,.2);border-bottom-color:var(--c);box-shadow:inset 0 1px 0 rgba(255,255,255,.4),0 0 16px color-mix(in srgb,var(--c) 28%,transparent);text-align:center;margin-bottom:14px}
.bw-tabl b{display:block;font-family:'Oswald',sans-serif;font-weight:600;font-size:1.35em;letter-spacing:3px;text-transform:uppercase;color:#fff;text-shadow:0 0 10px var(--c)}
.bw-tabl span{display:block;font-family:'JetBrains Mono',monospace;font-size:.68em;letter-spacing:2px;text-transform:uppercase;color:var(--c);margin-top:2px}
.bw-znaki{position:relative;z-index:1;width:100%;display:flex;flex-direction:column;gap:9px}
.bw-znak{--ar:14px;position:relative;display:flex;align-items:center;gap:8px;width:92%;min-height:42px;padding:6px 14px;text-decoration:none;color:var(--txt-main);background:linear-gradient(180deg,rgba(255,255,255,.17),rgba(255,255,255,.05) 48%,rgba(255,255,255,.02) 52%,color-mix(in srgb,var(--c) 8%,transparent));backdrop-filter:blur(10px) saturate(160%);-webkit-backdrop-filter:blur(10px) saturate(160%);font-family:'Oswald',sans-serif;font-weight:500;font-size:.9em;letter-spacing:1px;text-transform:uppercase;line-height:1.1;transition:background .35s,color .35s,filter .35s,transform .35s;filter:drop-shadow(0 3px 6px rgba(0,0,0,.55));overflow:hidden}
.bw-znak.p{align-self:flex-end;padding-right:calc(var(--ar) + 10px);clip-path:polygon(0 0,calc(100% - var(--ar)) 0,100% 50%,calc(100% - var(--ar)) 100%,0 100%)}
.bw-znak.l{align-self:flex-start;flex-direction:row-reverse;text-align:right;padding-left:calc(var(--ar) + 10px);clip-path:polygon(var(--ar) 0,100% 0,100% 100%,var(--ar) 100%,0 50%)}
.bw-znak::before{content:'';position:absolute;inset:0;pointer-events:none;border-top:1px solid rgba(255,255,255,.38);border-bottom:1px solid color-mix(in srgb,var(--c) 55%,transparent)}.bw-znak::after{content:'';position:absolute;top:0;bottom:0;left:-60%;width:45%;pointer-events:none;background:linear-gradient(100deg,transparent,rgba(255,255,255,.32),transparent);transform:skewX(-18deg);transition:left .8s ease}.bw-znak:hover::after,.bw-znak.akt::after{left:120%}
.bw-znak .ik{flex:none;width:22px;text-align:center;font-size:1em;filter:grayscale(.6);transition:filter .35s}
.bw-znak .nz{flex:1;min-width:0;overflow-wrap:break-word;hyphens:manual}
.bw-znak .tg{flex:none;font-family:'JetBrains Mono',monospace;font-size:.62em;letter-spacing:1px;padding:1px 5px;border:1px solid var(--c);color:var(--c)}
.bw-znak:hover,.bw-znak:focus-visible,.bw-znak.akt{color:#fff;background:linear-gradient(180deg,rgba(255,255,255,.24),color-mix(in srgb,var(--c) 22%,transparent) 50%,color-mix(in srgb,var(--c) 34%,transparent));filter:drop-shadow(0 0 10px color-mix(in srgb,var(--c) 65%,transparent));outline:none}
.bw-znak.p:hover,.bw-znak.p.akt{transform:translateX(4px)}
.bw-znak.l:hover,.bw-znak.l.akt{transform:translateX(-4px)}
.bw-znak:hover .ik,.bw-znak.akt .ik{filter:none}
.bw-info{--c:#ff1744;position:sticky;top:16px;background:linear-gradient(150deg,rgba(255,255,255,.14),rgba(255,255,255,.04) 35%,rgba(255,255,255,.02) 70%,color-mix(in srgb,var(--c) 8%,transparent));backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);border:1px solid rgba(255,255,255,.18);padding:14px;box-shadow:inset 0 1px 0 rgba(255,255,255,.4),inset 1px 0 0 rgba(255,255,255,.12),0 14px 40px rgba(0,0,0,.5);transition:border-color .8s,box-shadow .8s;overflow:hidden}
.bw-info.on{border-color:rgba(255,255,255,.26);box-shadow:inset 0 1px 0 rgba(255,255,255,.5),inset 0 -1px 0 color-mix(in srgb,var(--c) 60%,transparent),0 14px 40px rgba(0,0,0,.5),0 0 34px color-mix(in srgb,var(--c) 22%,transparent)}.bw-info::after{content:'';position:absolute;top:-40%;left:-80%;width:60%;height:180%;pointer-events:none;background:linear-gradient(100deg,transparent,rgba(255,255,255,.14),transparent);transform:rotate(12deg);transition:left 1.6s ease}.bw-info.on::after{left:140%}
.bw-info::before{content:'';position:absolute;top:-1px;left:14px;width:40px;height:1px;background:var(--c);box-shadow:0 0 8px var(--c)}
.bw-foto{position:relative;aspect-ratio:16/10;background:radial-gradient(ellipse at 50% 110%,color-mix(in srgb,var(--c) 40%,transparent),transparent 65%),linear-gradient(160deg,#1e0a12,#050306 75%);background-size:cover;background-position:center;border:1px solid rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;overflow:hidden;opacity:0;filter:brightness(0) blur(8px);transform:scale(1.04);transition:opacity 1.2s ease,filter 1.4s ease,transform 1.6s ease}
.bw-foto .gl{font-size:3.4em;opacity:.55;filter:drop-shadow(0 0 18px var(--c))}
.bw-foto.ma-foto .gl{display:none}
.bw-foto::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 130%,rgba(255,23,68,.6),transparent 60%),linear-gradient(0deg,rgba(5,3,6,.85),transparent 45%);transition:opacity 1.4s ease .2s}
.bw-info.on .bw-foto{opacity:1;filter:none;transform:none}
.bw-info.on .bw-foto::after{opacity:.55}
.bw-txt>*{opacity:0;transform:translateY(8px);transition:opacity .9s ease,transform .9s ease}
.bw-info.on .bw-txt>*{opacity:1;transform:none}
.bw-info.on .bw-txt>:nth-child(2){transition-delay:.35s}
.bw-info.on .bw-txt>:nth-child(3){transition-delay:.5s}
.bw-info.on .bw-txt>:nth-child(4){transition-delay:.65s}
.bw-info.on .bw-txt>:nth-child(5){transition-delay:.8s}
.bw-kw{margin-top:14px;font-family:'JetBrains Mono',monospace;font-size:.7em;letter-spacing:2px;text-transform:uppercase;color:var(--c)}
.bw-info h2{font-family:'Oswald',sans-serif;font-weight:500;font-size:1.7em;letter-spacing:2px;text-transform:uppercase;color:#fff;line-height:1.1;margin:4px 0 8px;text-shadow:0 0 14px color-mix(in srgb,var(--c) 50%,transparent)}
.bw-lead{color:var(--txt-main);font-size:1.08em;font-weight:600;line-height:1.35;text-wrap:pretty}
.bw-opis{color:var(--txt-dim);font-size:1em;line-height:1.5;margin-top:6px;text-wrap:pretty}
.bw-idz{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:16px;min-height:44px;border:1px solid rgba(255,255,255,.22);border-bottom-color:var(--c);color:#fff;text-decoration:none;font-family:'Oswald',sans-serif;font-weight:500;letter-spacing:3px;text-transform:uppercase;font-size:.9em;background:linear-gradient(180deg,rgba(255,255,255,.16),color-mix(in srgb,var(--c) 14%,transparent));box-shadow:inset 0 1px 0 rgba(255,255,255,.35);transition:background .25s,box-shadow .25s}
.bw-idz:hover{background:color-mix(in srgb,var(--c) 28%,transparent);box-shadow:0 0 16px color-mix(in srgb,var(--c) 45%,transparent)}
.bw-pusto{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:20px;color:var(--txt-mute);font-family:'JetBrains Mono',monospace;font-size:.78em;letter-spacing:2px;text-transform:uppercase;transition:opacity .5s}
.bw-pusto b{font-family:'Oswald',sans-serif;font-weight:400;font-size:2.2em;color:rgba(255,23,68,.35)}
.bw-info.on .bw-pusto{opacity:0;pointer-events:none}
@container (max-width:700px){.bw-uklad{grid-template-columns:minmax(0,1fr)}.bw-info{position:sticky;bottom:10px;top:auto}.bw-info:not(.gotowy){display:none}.bw-foto{aspect-ratio:auto;height:170px}}
@media(max-width:600px){.bw-marq h1{font-size:2.1em;letter-spacing:5px}}
@media(prefers-reduced-motion:reduce){.bw-marq::before{animation:none}.bw-znak::after,.bw-info::after{display:none}.bw-foto,.bw-txt>*{transition:opacity .2s}}
</style>

<div class="bw">
<header class="bw-marq">
<div class="eyebrow">// Manhattan · nocna aleja</div>
<h1>Broadway</h1>
<p>Wybierz kierunek. Najedź na drogowskaz, żeby zobaczyć, dokąd prowadzi.</p>
</header>
<div class="bw-uklad">
<div class="bw-aleja">
<?php foreach ($bw_dane as $d): ?>
<section class="bw-slup" style="--c:<?php echo $d['kolor']; ?>">
<div class="bw-tabl"><b><?php echo htmlspecialchars($d['ulica']); ?></b><span><?php echo htmlspecialchars($d['kwartal']); ?></span></div>
<div class="bw-znaki">
<?php foreach ($d['miejsca'] as $i => $m): ?>
<a href="game.php?page=<?php echo $m['k']; ?>" class="bw-znak <?php echo $i % 2 ? 'l' : 'p'; ?>" data-k="<?php echo $m['k']; ?>"><span class="ik"><?php echo $m['i']; ?></span><span class="nz"><?php echo htmlspecialchars($m['n']); ?></span><?php if (!empty($m['t'])): ?><span class="tg"><?php echo htmlspecialchars($m['t']); ?></span><?php endif; ?></a>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
</div>
<aside class="bw-info" id="bw-info" aria-live="polite">
<div class="bw-foto" id="bw-foto"><span class="gl" id="bw-gl"></span></div>
<div class="bw-txt"><div class="bw-kw" id="bw-kw"></div><h2 id="bw-n"></h2><p class="bw-lead" id="bw-lead"></p><p class="bw-opis" id="bw-o"></p><a class="bw-idz" id="bw-idz" href="#">Idź tam <span>→</span></a></div>
<div class="bw-pusto"><b>◤ ◥</b>Najedź na drogowskaz</div>
</aside>
</div>
</div>

<script>
(function(){
const DANE = <?php echo json_encode($bw_dane, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const M = {};
DANE.forEach(d => d.miejsca.forEach(m => M[m.k] = Object.assign({d}, m)));
const box = document.getElementById('bw-info'), foto = document.getElementById('bw-foto');
const $ = id => document.getElementById(id);
let teraz = null, t1 = null, t2 = null;
function pokaz(k){
  if (k === teraz) return;
  clearTimeout(t1); clearTimeout(t2);
  t1 = setTimeout(() => {
    teraz = k; const m = M[k];
    document.querySelectorAll('.bw-znak.akt').forEach(e => e.classList.remove('akt'));
    const z = document.querySelector('.bw-znak[data-k="' + k + '"]'); if (z) z.classList.add('akt');
    box.classList.add('gotowy'); box.classList.remove('on');
    t2 = setTimeout(() => {
      box.style.setProperty('--c', m.d.kolor);
      foto.style.backgroundImage = m.img ? "url('" + m.img + "')" : '';
      foto.classList.toggle('ma-foto', !!m.img);
      $('bw-gl').textContent = m.i;
      $('bw-kw').textContent = m.d.ulica + ' · ' + m.d.kwartal;
      $('bw-n').textContent = m.n;
      $('bw-lead').textContent = m.lead;
      $('bw-o').textContent = m.o;
      $('bw-idz').href = 'game.php?page=' + m.k;
      void box.offsetWidth; box.classList.add('on');
    }, box.classList.contains('on') ? 260 : 0);
  }, 110);
}
document.querySelectorAll('.bw-znak').forEach(z => {
  z.addEventListener('mouseenter', () => pokaz(z.dataset.k));
  z.addEventListener('focus', () => pokaz(z.dataset.k));
  z.addEventListener('click', e => {
    if (matchMedia('(hover: none)').matches && teraz !== z.dataset.k) { e.preventDefault(); pokaz(z.dataset.k); }
  });
});
})();
</script>
