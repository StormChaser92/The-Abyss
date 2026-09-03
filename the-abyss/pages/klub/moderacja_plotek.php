<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — PANEL MODERACJI PLOTEK (Faza 6)
// 
// Tylko dla MG (is_mg=1). Lista wszystkich plotek z autorami.
// Akcje: usuń (aktywna=0), przywróć (aktywna=1).
// 
// Dostęp: game.php?page=czat&sala=lobby&widok=moderacja_plotek
// ══════════════════════════════════════════════════════════════════

if (!$jest_mg) {
    echo "<div style='padding:40px;text-align:center;color:var(--neon-red-hot);font-family:Oswald,sans-serif;letter-spacing:2px;text-transform:uppercase;border:1px solid var(--neon-red);background:rgba(255,23,68,0.05);border-radius:2px;margin-top:20px'>
        🚫 Brak dostępu — panel moderacji tylko dla MG
        <br><br>
        <a href='game.php?page=czat&sala=lobby' style='color:var(--neon-red-hot);text-decoration:underline;font-size:.85em'>◂ Wróć do Lobby</a>
    </div>";
    return;
}
?>

<style>
.mod-wrap { margin-top: 20px; }
.mod-header {
    padding-bottom: 14px; margin-bottom: 18px;
    border-bottom: 1px solid var(--border-soft);
    display: flex; justify-content: space-between; align-items: baseline;
    flex-wrap: wrap; gap: 14px;
}
.mod-header h2 {
    font-family: 'Oswald', sans-serif; font-size: 1.3em;
    color: #fff; letter-spacing: 3px; text-transform: uppercase;
    text-shadow: 0 0 10px rgba(200,150,255,0.5);
}
.mod-header h2 .ic { color: #c896ff; margin-right: 6px; }
.mod-filters { display: flex; gap: 6px; }
.mod-filter-btn {
    padding: 6px 14px; background: rgba(0,0,0,0.4);
    border: 1px solid var(--border-soft); color: var(--txt-dim);
    font-family: 'JetBrains Mono', monospace; font-size: .72em;
    letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px; transition: .15s; text-decoration: none;
}
.mod-filter-btn:hover { color: #c896ff; border-color: #c896ff; }
.mod-filter-btn.aktywna {
    background: rgba(200,150,255,0.15); color: #c896ff;
    border-color: #c896ff;
}

.mod-plotka {
    display: grid; grid-template-columns: 1fr auto;
    gap: 16px; padding: 14px 18px;
    background: rgba(10,5,15,0.55); border: 1px solid var(--border-soft);
    border-left: 3px solid #c896ff; border-radius: 2px;
    margin-bottom: 8px; transition: .15s;
}
.mod-plotka:hover { background: rgba(200,150,255,0.04); }
.mod-plotka.usunieta {
    opacity: .55; border-left-color: var(--border-mid);
    background: rgba(40,20,40,0.3);
}
.mod-plotka.usunieta .tresc { text-decoration: line-through; color: var(--txt-mute); }

.mod-plotka .info { min-width: 0; }
.mod-plotka .meta {
    display: flex; gap: 12px; flex-wrap: wrap;
    font-family: 'JetBrains Mono', monospace; font-size: .68em;
    color: var(--txt-mute); letter-spacing: 1px;
    text-transform: uppercase; margin-bottom: 6px;
}
.mod-plotka .meta b { color: #c896ff; }
.mod-plotka .meta .id { color: var(--txt-dim); }
.mod-plotka .meta .reakcje { color: var(--txt-mute); }
.mod-plotka .meta .reakcje .v { color: var(--neon-green); }
.mod-plotka .meta .reakcje .f { color: var(--neon-red-hot); }
.mod-plotka .tresc {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.05em; color: #ddd; line-height: 1.45;
    word-wrap: break-word;
}
.mod-plotka .akcje { display: flex; flex-direction: column; gap: 6px; }
.mod-plotka .akcje button {
    padding: 6px 14px; background: rgba(255,23,68,0.1);
    border: 1px solid var(--neon-red); color: var(--neon-red-hot);
    font-family: 'Oswald', sans-serif; font-size: .72em;
    letter-spacing: 2px; text-transform: uppercase; cursor: pointer;
    border-radius: 2px; transition: .15s; white-space: nowrap;
}
.mod-plotka .akcje button:hover { background: var(--neon-red); color: #fff; }
.mod-plotka .akcje button.przywroc {
    background: rgba(90,255,154,0.1); border-color: var(--neon-green);
    color: var(--neon-green);
}
.mod-plotka .akcje button.przywroc:hover { background: var(--neon-green); color: #000; }

.mod-pusty {
    padding: 60px 20px; text-align: center;
    background: rgba(0,0,0,0.3); border: 1px dashed var(--border-soft);
    border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-mute); font-size: 1.1em; line-height: 1.6;
}

.mod-info {
    padding: 12px 16px; margin-bottom: 14px;
    background: rgba(200,150,255,0.06);
    border: 1px solid rgba(200,150,255,0.3); border-radius: 2px;
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    font-size: .92em; color: var(--txt-dim); line-height: 1.5;
}
.mod-info b { color: #c896ff; font-style: normal;
    font-family: 'Oswald', sans-serif; font-size: .82em;
    letter-spacing: 1.5px;
}
</style>

<div class="mod-wrap">
    <div class="mod-header">
        <h2><span class="ic">🛡</span> Moderacja plotek</h2>
        <div class="mod-filters">
            <a href="game.php?page=czat&sala=lobby&widok=moderacja_plotek" class="mod-filter-btn aktywna" id="filter-aktywne">● Aktywne</a>
            <a href="game.php?page=czat&sala=lobby&widok=moderacja_plotek&pokaz=usuniete" class="mod-filter-btn" id="filter-usuniete">○ Usunięte</a>
            <a href="game.php?page=czat&sala=lobby" class="mod-filter-btn" style="margin-left:14px">◂ Lobby</a>
        </div>
    </div>

    <div class="mod-info">
        <b>📋 Informacja</b>: Widzisz wszystkie plotki z autorami. Gracze widzą plotki anonimowo.
        Usunięcie ustawia <code>aktywna=0</code> (nie kasuje z bazy). Możesz przywrócić w każdej chwili.
    </div>

    <div id="mod-lista">
        <div style="padding:40px;text-align:center;color:var(--txt-mute);font-family:JetBrains Mono,monospace;letter-spacing:1px">⏳ Ładuję plotki...</div>
    </div>
</div>

<script>
(async function ladujPlotki() {
    const params = new URLSearchParams(window.location.search);
    const pokazUsuniete = params.get('pokaz') === 'usuniete';

    if (pokazUsuniete) {
        document.getElementById('filter-aktywne').classList.remove('aktywna');
        document.getElementById('filter-usuniete').classList.add('aktywna');
    }

    const lista = document.getElementById('mod-lista');
    try {
        const url = 'api/klub_plotki.php?op=lista_moderacja' + (pokazUsuniete ? '&usuniete=1' : '');
        const res = await fetch(url, { credentials: 'same-origin' });
        const d = await res.json();

        if (!d.ok) {
            lista.innerHTML = '<div class="mod-pusty">' + (d.msg || 'Błąd') + '</div>';
            return;
        }

        if (!d.plotki || d.plotki.length === 0) {
            lista.innerHTML = '<div class="mod-pusty">' +
                (pokazUsuniete ? 'Brak usuniętych plotek.' : 'Brak aktywnych plotek.') +
            '</div>';
            return;
        }

        let html = '';
        for (const p of d.plotki) {
            const cls = p.aktywna ? '' : 'usunieta';
            const akcja = p.aktywna
                ? `<button onclick="usunPlotke(${p.id})">✕ Usuń</button>`
                : `<button class="przywroc" onclick="przywrocPlotke(${p.id})">↺ Przywróć</button>`;
            html += `
                <div class="mod-plotka ${cls}" data-id="${p.id}">
                    <div class="info">
                        <div class="meta">
                            <span class="id">#${p.id}</span>
                            <span>autor: <b>${escapeHtml(p.autor_login)}</b></span>
                            <span>${escapeHtml(p.czas)}</span>
                            <span class="reakcje"><span class="v">👍 ${p.prawda}</span> · <span class="f">👎 ${p.falsz}</span></span>
                        </div>
                        <div class="tresc">${escapeHtml(p.tresc)}</div>
                    </div>
                    <div class="akcje">${akcja}</div>
                </div>`;
        }
        lista.innerHTML = html;
    } catch (e) {
        lista.innerHTML = '<div class="mod-pusty">⚠ Brak połączenia: ' + escapeHtml(e.message) + '</div>';
    }
})();

window.usunPlotke = async function(id) {
    if (!confirm('Usunąć plotkę #' + id + '?\n(Można później przywrócić)')) return;
    const fd = new FormData();
    fd.append('op','usun');
    fd.append('id', id);
    try {
        const res = await fetch('api/klub_plotki.php', { method:'POST', body: fd, credentials: 'same-origin' });
        const d = await res.json();
        if (d.ok) location.reload();
        else alert(d.msg || 'Błąd');
    } catch(e) { alert('Brak połączenia'); }
};

window.przywrocPlotke = async function(id) {
    const fd = new FormData();
    fd.append('op','przywroc');
    fd.append('id', id);
    try {
        const res = await fetch('api/klub_plotki.php', { method:'POST', body: fd, credentials: 'same-origin' });
        const d = await res.json();
        if (d.ok) location.reload();
        else alert(d.msg || 'Błąd');
    } catch(e) { alert('Brak połączenia'); }
};

function escapeHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>