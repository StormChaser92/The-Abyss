<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — PANEL ODZNAK (Faza 7)
// 
// Wyświetlanie wszystkich odznak gracza, z progresem i datami zdobycia.
// Dostęp: game.php?page=czat&sala=lobby&widok=odznaki
// ══════════════════════════════════════════════════════════════════
?>

<style>
.odznaki-wrap { margin-top: 20px; }
.odznaki-header {
    padding-bottom: 14px; margin-bottom: 18px;
    border-bottom: 1px solid var(--border-soft);
    display: flex; justify-content: space-between; align-items: baseline;
    flex-wrap: wrap; gap: 14px;
}
.odznaki-header h2 {
    font-family: 'Oswald', sans-serif; font-size: 1.3em;
    color: #fff; letter-spacing: 3px; text-transform: uppercase;
    text-shadow: 0 0 10px rgba(255,215,0,0.5);
}
.odznaki-header h2 .ic { color: var(--neon-gold); margin-right: 6px; }
.odznaki-stat {
    font-family: 'JetBrains Mono', monospace; font-size: .8em;
    color: var(--txt-dim); letter-spacing: 1.5px;
}
.odznaki-stat b { color: var(--neon-gold); font-size: 1.2em; }

.odznaki-filtry { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
.odznaki-filtr {
    padding: 6px 12px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); color: var(--txt-dim);
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px; transition: .15s; user-select: none;
}
.odznaki-filtr:hover { color: var(--neon-gold); border-color: var(--neon-gold); }
.odznaki-filtr.aktywna {
    background: rgba(255,215,0,0.15); color: var(--neon-gold);
    border-color: var(--neon-gold);
}

.odznaki-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}
.odznaka-card {
    padding: 14px; border-radius: 2px;
    background: linear-gradient(135deg, rgba(20,15,5,0.6), rgba(0,0,0,0.5));
    border: 1px solid var(--border-soft);
    transition: .2s; position: relative;
}
.odznaka-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}
.odznaka-card.zdobyta { border-color: var(--neon-gold); background: linear-gradient(135deg, rgba(60,40,5,0.6), rgba(0,0,0,0.5)); }
.odznaka-card.zdobyta::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(135deg, rgba(255,215,0,0.05), transparent);
    border-radius: 2px;
}

/* Rzadkość — kolor obwódki */
.odznaka-card.r-zwykla.zdobyta { border-color: var(--neon-green); }
.odznaka-card.r-zwykla.zdobyta::before { background: linear-gradient(135deg, rgba(90,255,154,0.05), transparent); }
.odznaka-card.r-rzadka.zdobyta { border-color: var(--neon-cyan); }
.odznaka-card.r-rzadka.zdobyta::before { background: linear-gradient(135deg, rgba(74,214,255,0.05), transparent); }
.odznaka-card.r-epicka.zdobyta { border-color: #c896ff; }
.odznaka-card.r-epicka.zdobyta::before { background: linear-gradient(135deg, rgba(200,150,255,0.07), transparent); }
.odznaka-card.r-legendarna.zdobyta {
    border-color: var(--neon-gold);
    box-shadow: 0 0 18px rgba(255,215,0,0.3);
}

.odznaka-card .ikona {
    font-size: 2.4em; line-height: 1; text-align: center;
    margin-bottom: 8px;
    filter: grayscale(70%) opacity(.4);
}
.odznaka-card.zdobyta .ikona { filter: none; }
.odznaka-card .nazwa {
    font-family: 'Oswald', sans-serif; font-size: .9em;
    color: var(--txt-dim); letter-spacing: 2px; text-transform: uppercase;
    text-align: center; margin-bottom: 6px;
}
.odznaka-card.zdobyta .nazwa { color: #fff; }
.odznaka-card .opis {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .85em; color: var(--txt-mute); line-height: 1.4;
    text-align: center; margin-bottom: 10px; min-height: 36px;
}
.odznaka-card .progres-bar {
    width: 100%; height: 4px; background: rgba(0,0,0,0.6);
    border-radius: 2px; overflow: hidden; margin-bottom: 4px;
}
.odznaka-card .progres-bar .fill {
    height: 100%;
    background: linear-gradient(90deg, var(--neon-gold), #ffeb3b);
    transition: width .3s;
}
.odznaka-card.zdobyta .progres-bar .fill { background: var(--neon-gold); }
.odznaka-card .progres-text {
    font-family: 'JetBrains Mono', monospace; font-size: .65em;
    color: var(--txt-mute); letter-spacing: 1px;
    text-align: center;
}
.odznaka-card .progres-text b { color: var(--neon-gold); }
.odznaka-card .data-zdob {
    font-family: 'JetBrains Mono', monospace; font-size: .62em;
    color: var(--neon-gold); letter-spacing: 1.5px;
    text-align: center; margin-top: 6px;
    text-transform: uppercase; opacity: .7;
}
.odznaka-card .rzadkosc-tag {
    position: absolute; top: 8px; right: 8px;
    font-family: 'JetBrains Mono', monospace; font-size: .55em;
    letter-spacing: 1px; text-transform: uppercase;
    padding: 1px 6px; border-radius: 1px;
    background: rgba(0,0,0,0.6); color: var(--txt-mute);
}
.odznaka-card.r-zwykla .rzadkosc-tag { color: var(--neon-green); border: 1px solid rgba(90,255,154,0.3); }
.odznaka-card.r-rzadka .rzadkosc-tag { color: var(--neon-cyan); border: 1px solid rgba(74,214,255,0.3); }
.odznaka-card.r-epicka .rzadkosc-tag { color: #c896ff; border: 1px solid rgba(200,150,255,0.4); }
.odznaka-card.r-legendarna .rzadkosc-tag { color: var(--neon-gold); border: 1px solid var(--neon-gold); }

.odznaki-pusty {
    padding: 60px 20px; text-align: center;
    background: rgba(0,0,0,0.3); border: 1px dashed var(--border-soft);
    border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-mute); font-size: 1.1em;
}
</style>

<div class="odznaki-wrap">
    <div class="odznaki-header">
        <h2><span class="ic">🏆</span> Moje odznaki</h2>
        <div class="odznaki-stat" id="odznaki-stat">⏳ Ładuję...</div>
    </div>

    <div class="odznaki-filtry" id="odznaki-filtry">
        <span class="odznaki-filtr aktywna" data-kat="wszystkie">Wszystkie</span>
        <span class="odznaki-filtr" data-kat="zdobyte">Zdobyte</span>
        <span class="odznaki-filtr" data-kat="niezdobyte">Niezdobyte</span>
        <span class="odznaki-filtr" data-kat="vip">VIP</span>
        <span class="odznaki-filtr" data-kat="aktywnosc">Aktywność</span>
        <span class="odznaki-filtr" data-kat="spoleczne">Społeczne</span>
        <span class="odznaki-filtr" data-kat="gastronomia">Gastronomia</span>
        <span class="odznaki-filtr" data-kat="sale">Sale</span>
        <span class="odznaki-filtr" data-kat="specjalne">Specjalne</span>
        <span class="odznaki-filtr" data-kat="lojalnosc">Lojalność</span>
        <span class="odznaki-filtr" data-kat="styl">Styl</span>
        <a class="odznaki-filtr" href="game.php?page=czat&sala=lobby" style="margin-left:14px;text-decoration:none">◂ Lobby</a>
    </div>

    <div class="odznaki-grid" id="odznaki-grid">
        <div class="odznaki-pusty">⏳ Ładuję odznaki...</div>
    </div>
</div>

<script>
let WSZYSTKIE_ODZNAKI = [];
let AKTYWNY_FILTR = 'wszystkie';

(async function ladujOdznaki() {
    try {
        const r = await fetch('api/klub_odznaki.php?op=lista', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok) {
            document.getElementById('odznaki-grid').innerHTML = '<div class="odznaki-pusty">Błąd: ' + (d.msg || '???') + '</div>';
            return;
        }
        WSZYSTKIE_ODZNAKI = d.odznaki || [];

        // Statystyki
        const zdobyte = WSZYSTKIE_ODZNAKI.filter(o => o.zdobyta).length;
        const total = WSZYSTKIE_ODZNAKI.length;
        document.getElementById('odznaki-stat').innerHTML = 'Zdobyte: <b>' + zdobyte + '</b> / ' + total;

        rysujOdznaki();
    } catch (e) {
        document.getElementById('odznaki-grid').innerHTML = '<div class="odznaki-pusty">⚠ Brak połączenia</div>';
    }
})();

function rysujOdznaki() {
    let lista = WSZYSTKIE_ODZNAKI;
    if (AKTYWNY_FILTR === 'zdobyte') lista = lista.filter(o => o.zdobyta);
    else if (AKTYWNY_FILTR === 'niezdobyte') lista = lista.filter(o => !o.zdobyta);
    else if (AKTYWNY_FILTR !== 'wszystkie') lista = lista.filter(o => o.kategoria === AKTYWNY_FILTR);

    if (lista.length === 0) {
        document.getElementById('odznaki-grid').innerHTML = '<div class="odznaki-pusty">Brak odznak w tej kategorii.</div>';
        return;
    }

    let html = '';
    for (const o of lista) {
        const cls = (o.zdobyta ? 'zdobyta ' : '') + 'r-' + o.rzadkosc;
        const data = o.zdobyta ? `<div class="data-zdob">★ zdobyto: ${escapeHtml(o.zdobyto_o)}</div>` : '';
        html += `
            <div class="odznaka-card ${cls}">
                <span class="rzadkosc-tag">${escapeHtml(o.rzadkosc)}</span>
                <div class="ikona">${escapeHtml(o.ikona)}</div>
                <div class="nazwa">${escapeHtml(o.nazwa)}</div>
                <div class="opis">${escapeHtml(o.opis)}</div>
                <div class="progres-bar"><div class="fill" style="width:${o.procent}%"></div></div>
                <div class="progres-text"><b>${o.progres}</b> / ${o.prog}</div>
                ${data}
            </div>`;
    }
    document.getElementById('odznaki-grid').innerHTML = html;
}

document.querySelectorAll('.odznaki-filtr[data-kat]').forEach(el => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.odznaki-filtr').forEach(f => f.classList.remove('aktywna'));
        el.classList.add('aktywna');
        AKTYWNY_FILTR = el.dataset.kat;
        rysujOdznaki();
    });
});

function escapeHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>