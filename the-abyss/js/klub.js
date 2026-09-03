/* ════════════════════════════════════════════════════════════════════
   KLUB THE ABYSS — RICH CHAT (Faza 2)
   
   - Auto-refresh co 5s (pull nowych wiadomości)
   - Wstrzymuje refresh gdy gracz pisze
   - "Nowe wiadomości" toast podczas pisania
   - Rich-text parsing: *narracja*, "dialog", **bold**, @mention
   - Wysyłka AJAX bez przeładowania strony
   - Rachunek live update
═══════════════════════════════════════════════════════════════════════ */

(function() {
    'use strict';

    // ── KONFIG ───────────────────────────────────────────────────
    const REFRESH_MS = 5000;
    const TYPING_GRACE_MS = 8000;        // jak długo "wstrzymujesz" odświeżanie po ostatnim klawiszu
    const SCROLL_BOTTOM_THRESHOLD = 50;  // px od dołu = "user czyta na dole"

    // ── ELEMENTY ─────────────────────────────────────────────────
    const feed = document.getElementById('klub-feed');
    if (!feed) return; // nie jesteśmy w sali z czatem

    const sala = feed.dataset.sala || 'sala-glowna';
    const input = document.getElementById('klub-input');
    const form = document.getElementById('klub-form');
    const sendBtn = form ? form.querySelector('button[type=submit]') : null;
    const rachunekBox = document.getElementById('klub-rachunek');
    const obecniBox = document.getElementById('klub-obecni');

    // ── STAN ─────────────────────────────────────────────────────
    let lastId = parseInt(feed.dataset.lastId || '0', 10);
    let lastTypingMs = 0;
    let pendingNew = 0;          // ile nowych wiadomości czeka gdy user pisze
    let isFetching = false;
    let refreshTimer = null;

    // ── PARSER RICH-TEXT ─────────────────────────────────────────
    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseRich(raw) {
        let s = escapeHtml(raw);
        // **bold**  →  <span class="emph">…</span>
        s = s.replace(/\*\*(.+?)\*\*/g, '<span class="emph">$1</span>');
        // *akcja*  →  <span class="nar">…</span>
        s = s.replace(/\*(.+?)\*/g, '<span class="nar">$1</span>');
        // "dialog"  →  <span class="dialog">"…"</span>
        // (Polish quotes too: „dialog")
        s = s.replace(/&quot;(.+?)&quot;/g, '<span class="dialog">"$1"</span>');
        s = s.replace(/(„)(.+?)(")/g, '<span class="dialog">$1$2$3</span>');
        // — myślnik na początku linii lub po spacji  →  dialog
        s = s.replace(/(^|\s)(— [^*"„<\n]+?)(?=$|<|\n)/gm, '$1<span class="dialog">$2</span>');
        // @nick  →  mention (tylko a-zA-Z0-9_-)
        s = s.replace(/(^|\s)@([A-Za-z0-9_\-]{2,32})/g, '$1<span class="mention">@$2</span>');
        return s;
    }

    function inicjaly(login) {
        const clean = login.replace(/\s*\[(BARMAN|MG|NPC)\]/gi, '').trim();
        const parts = clean.split(/[_\s]+/);
        if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
        return clean.substring(0, 2).toUpperCase();
    }

    // ── RENDER WIADOMOŚCI ────────────────────────────────────────
    function renderMsg(m) {
        let cls = 'msg';
        let role = '';

        if (m.typ === 'system') {
            const div = document.createElement('div');
            div.className = 'msg sys';
            div.dataset.id = m.id;
            div.innerHTML = '<span class="sys-line">' + m.tresc + ' <span class="sys-time">' + escapeHtml(m.czas) + '</span></span>';
            return div;
        }

        if (m.login.indexOf('Barman [NPC]') !== -1) { cls += ' npc bot'; role = 'Barman NPC'; }
        else if (m.login.indexOf('Krupier [NPC]') !== -1) { cls += ' npc'; role = 'Krupier NPC'; }
        else if (m.login.indexOf('[BARMAN]') !== -1) { cls += ' barman'; role = 'Barman'; }
        else if (m.login.indexOf('[MG]') !== -1) { cls += ' mg'; role = 'MG'; }

        // FAZA 4: flagi
        if (m.is_mine) cls += ' is-mine';
        if (m.usunieta) cls += ' usunieta';

        const cleanLogin = m.login.replace(/\s*\[(BARMAN|MG|NPC)\]/gi, '');
        const ini = inicjaly(m.login);
        const tresc = parseRich(m.tresc);

        // Edycja możliwa tylko dla zwykłych wiadomości w ciągu 10 min
        const canEdit = m.is_mine && !m.usunieta && m.typ === 'wiadomosc' && m.ts && (Date.now()/1000 - m.ts < 600);
        const editedNote = m.edytowane
            ? `<span class="edited">edytowane ${escapeHtml(m.edytowane_o || '')}</span>`
            : '';
        const actions = canEdit
            ? `<span class="actions">
                  <button class="edit" onclick="window.klubEdytujMsg(${m.id})" title="Edytuj">✎ edytuj</button>
                  <button class="del" onclick="window.klubUsunMsg(${m.id})" title="Usuń">✕ usuń</button>
               </span>`
            : '';

        const div = document.createElement('div');
        div.className = cls;
        div.dataset.id = m.id;
        div.dataset.tresc = m.tresc; // do edycji in-place
        div.innerHTML = `
            <div class="av">${escapeHtml(ini)}</div>
            <div class="body">
                <div class="who">
                    <span class="nm">${escapeHtml(cleanLogin)}</span>
                    ${role ? '<span class="role">' + role + '</span>' : ''}
                    ${editedNote}
                    <span class="when">${escapeHtml(m.czas)}</span>
                    ${actions}
                </div>
                <div class="txt">${tresc}</div>
            </div>
        `;
        return div;
    }

    // ── SCROLL HELPERS ───────────────────────────────────────────
    function isAtBottom() {
        return (feed.scrollHeight - feed.scrollTop - feed.clientHeight) < SCROLL_BOTTOM_THRESHOLD;
    }
    function scrollToBottom() {
        feed.scrollTop = feed.scrollHeight;
    }

    // ── FETCH NOWYCH WIADOMOŚCI ──────────────────────────────────
    async function fetchNew() {
        if (isFetching) return;
        isFetching = true;
        try {
            const res = await fetch(`api/klub_feed.php?sala=${encodeURIComponent(sala)}&od_id=${lastId}`, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (!data.ok) return;

            // Dopisz nowe wiadomości
            if (data.wiadomosci && data.wiadomosci.length) {
                const wasAtBottom = isAtBottom();
                const empty = feed.querySelector('.empty');
                if (empty) empty.remove();

                for (const m of data.wiadomosci) {
                    if (m.id <= lastId) continue;
                    feed.appendChild(renderMsg(m));
                    lastId = m.id;
                }
                feed.dataset.lastId = lastId;

                if (wasAtBottom || !isUserTyping()) {
                    scrollToBottom();
                    pendingNew = 0;
                    hideNewToast();
                } else {
                    pendingNew += data.wiadomosci.length;
                    showNewToast();
                }
            }

            // Rachunek update (tylko sala-glowna)
            if (rachunekBox && data.rachunek) {
                renderRachunek(data.rachunek);
            }

            // Obecni update
            if (obecniBox && data.obecni) {
                renderObecni(data.obecni);
            }

            // ── FAZA 3: SZEPTY ────────────────────────────────────
            if (data.szepty && data.szepty.length) {
                for (const s of data.szepty) {
                    pokazSzept(s);
                }
            }

            // ── FAZA 3: FLIRTY ────────────────────────────────────
            if (data.flirty && data.flirty.length) {
                for (const f of data.flirty) {
                    pokazFlirt(f);
                }
                // Aktualizuj widget "sekretne sygnały" jeśli istnieje
                const sygWidget = document.getElementById('klub-sygnaly');
                if (sygWidget) odswiezSygnaly();
            }

            // ── FAZA 3: WYPROSZONY Z SALI ─────────────────────────
            if (data.wyproszony) {
                pokazWyproszenie(data.wyproszony);
                if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
                return; // przerwij — i tak za chwilę przekierowujemy
            }
        } catch (e) {
            // Cisza — nie spamuj konsoli, ale zachowaj loguje wystarczająco
            // console.warn('klub feed fail:', e.message);
        } finally {
            isFetching = false;
        }
    }

    // ── RACHUNEK ─────────────────────────────────────────────────
    function renderRachunek(r) {
        if (!rachunekBox) return;
        if (!r.liczba) {
            rachunekBox.innerHTML = '<div class="rachunek-pusty">Rachunek pusty.<br><small>Zamów drinka komendą <b>/bar zamów [nazwa]</b> lub klikając pozycję z karty.</small></div>';
            return;
        }
        let html = '<div class="rachunek-naglowek">◈ Twój rachunek</div><div class="rachunek-pozycje">';
        for (const p of r.pozycje) {
            html += `<div class="r-poz"><span class="r-nm">${escapeHtml(p.nazwa)}</span><span class="r-cz">${escapeHtml(p.czas)}</span><span class="r-pr">${p.cena} $</span></div>`;
        }
        html += `</div><div class="rachunek-total"><span>RAZEM</span><b>${r.razem} $</b></div>`;
        html += '<button type="button" class="btn-zaplac" onclick="window.klubZaplac()">▸ Zapłać rachunek</button>';
        rachunekBox.innerHTML = html;
    }

    // ── OBECNI ───────────────────────────────────────────────────
    function renderObecni(lista) {
        if (!obecniBox) return;
        if (!lista.length) {
            obecniBox.innerHTML = '<div class="obecni-pusto">Tylko Ty.</div>';
            return;
        }
        let html = '';
        for (const o of lista) {
            const cls = o.is_me ? 'me' : (o.is_barman ? 'bar' : (o.is_mg ? 'mg' : ''));
            const ini = inicjaly(o.login);
            const ava = o.avatar
                ? `<div class="av" style="background-image:url('${escapeHtml(o.avatar)}');background-size:cover;background-position:center;color:transparent">${escapeHtml(ini)}</div>`
                : `<div class="av">${escapeHtml(ini)}</div>`;
            const mood = o.klub_mood || (o.is_barman ? 'za barem' : 'w sali');
            html += `<a href="game.php?page=profil&id=${o.id}" class="guest-row ${cls}">${ava}<div class="who"><span class="nm">${escapeHtml(o.login)}</span><span class="mood">${escapeHtml(mood)}</span></div></a>`;
        }
        obecniBox.innerHTML = html;
    }

    // ── TOAST "NOWE WIADOMOŚCI" ──────────────────────────────────
    let newToast = null;
    function showNewToast() {
        if (!newToast) {
            newToast = document.createElement('div');
            newToast.className = 'klub-new-toast';
            newToast.onclick = () => { scrollToBottom(); pendingNew = 0; hideNewToast(); };
            feed.parentElement.appendChild(newToast);
        }
        newToast.textContent = `▼ ${pendingNew} now${pendingNew===1?'a wiadomość':(pendingNew<5?'e wiadomości':'ych wiadomości')} — kliknij żeby zobaczyć`;
        newToast.classList.add('on');
    }
    function hideNewToast() {
        if (newToast) newToast.classList.remove('on');
    }

    // ── TYPING DETECTION ─────────────────────────────────────────
    function isUserTyping() {
        if (!input) return false;
        if (input.value.trim() === '') return false;
        return (Date.now() - lastTypingMs) < TYPING_GRACE_MS;
    }
    if (input) {
        input.addEventListener('input', () => { lastTypingMs = Date.now(); });
    }

    // ── WYSYŁKA WIADOMOŚCI (AJAX) ────────────────────────────────
    async function wyslij(tresc) {
        if (!tresc.trim()) return;
        try {
            const fd = new FormData();
            fd.append('sala', sala);
            fd.append('tresc', tresc);

            const res = await fetch('api/klub_akcja.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            if (!data.ok) {
                pokazFlash(data.msg || 'Nieznany błąd', 'blad');
                return;
            }

            if (data.msg) {
                pokazFlash(data.msg, 'ok');
            }

            // Po wysłaniu — od razu pull nowych
            input.value = '';
            input.style.height = 'auto';
            lastTypingMs = 0;
            await fetchNew();
            scrollToBottom();
        } catch (e) {
            pokazFlash('Brak połączenia z serwerem', 'blad');
        }
    }

    // ── FLASH MESSAGES ───────────────────────────────────────────
    function pokazFlash(text, klasa) {
        let flash = document.getElementById('klub-flash');
        if (!flash) {
            flash = document.createElement('div');
            flash.id = 'klub-flash';
            document.body.appendChild(flash);
        }
        flash.className = 'klub-flash ' + (klasa || 'ok');
        flash.textContent = text;
        flash.classList.add('on');
        clearTimeout(flash._t);
        flash._t = setTimeout(() => flash.classList.remove('on'), 4000);
    }

    // ── FORM SUBMIT (intercept) ──────────────────────────────────
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            wyslij(input.value);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                wyslij(input.value);
            }
        });
    }

    // ── EXPORTS dla onclick handlers ─────────────────────────────
    window.klubZaplac = function() {
        if (!confirm('Zapłacić cały rachunek?')) return;
        wyslij('/zaplac');
    };
    window.klubZamow = function(nazwa) {
        wyslij('/bar zamów ' + nazwa);
    };
    window.klubInsertCmd = function(cmd) {
        if (!input) return;
        input.value = cmd + ' ';
        input.focus();
    };

    // ══════════════════════════════════════════════════════════════
    // FAZA 4 — EDYCJA WIADOMOŚCI + DJ
    // ══════════════════════════════════════════════════════════════

    // ── EDYCJA WIADOMOŚCI IN-PLACE ───────────────────────────────
    window.klubEdytujMsg = function(id) {
        const msgEl = feed.querySelector(`.msg[data-id="${id}"]`);
        if (!msgEl) return;
        const txt = msgEl.querySelector('.txt');
        if (!txt) return;
        if (msgEl.classList.contains('editing')) return;
        msgEl.classList.add('editing');

        const oryginalna = msgEl.dataset.tresc || txt.textContent;
        const oryginalnyHtml = txt.innerHTML;

        txt.innerHTML = `
            <textarea class="edit-mode-input">${escapeHtml(oryginalna)}</textarea>
            <div class="edit-mode-actions">
                <button class="save">✓ Zapisz</button>
                <button class="cancel">✕ Anuluj</button>
            </div>
        `;
        const ta = txt.querySelector('textarea');
        ta.focus();
        ta.setSelectionRange(ta.value.length, ta.value.length);

        txt.querySelector('.save').addEventListener('click', async () => {
            const nowy = ta.value.trim();
            if (!nowy) { pokazFlash('Wiadomość nie może być pusta', 'blad'); return; }
            if (nowy === oryginalna) {
                msgEl.classList.remove('editing');
                txt.innerHTML = oryginalnyHtml;
                return;
            }
            await wyslij('/edytuj ' + id + ' ' + nowy);
        });
        txt.querySelector('.cancel').addEventListener('click', () => {
            msgEl.classList.remove('editing');
            txt.innerHTML = oryginalnyHtml;
        });
        ta.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                msgEl.classList.remove('editing');
                txt.innerHTML = oryginalnyHtml;
            }
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                txt.querySelector('.save').click();
            }
        });
    };

    window.klubUsunMsg = function(id) {
        if (!confirm('Usunąć tę wiadomość?')) return;
        wyslij('/usun ' + id);
    };

    // ── DJ AKCJE ────────────────────────────────────────────────
    async function djCall(op, params) {
        const fd = new FormData();
        fd.append('op', op);
        for (const k in params) if (params[k] !== undefined) fd.append(k, params[k]);
        try {
            const res = await fetch('api/klub_dj.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const d = await res.json();
            if (d.ok) {
                if (d.msg) pokazFlash(d.msg, 'ok');
                // Odśwież sala-glowna żeby zobaczyć nowy stan
                setTimeout(() => location.reload(), 500);
            } else {
                pokazFlash(d.msg || 'Błąd', 'blad');
            }
        } catch (e) {
            pokazFlash('Brak połączenia', 'blad');
        }
    }

    window.djZamow = function() {
        const tytul = (document.getElementById('dj-tytul') || {}).value || '';
        const artysta = (document.getElementById('dj-artysta') || {}).value || '';
        const notka = (document.getElementById('dj-notka') || {}).value || '';
        if (tytul.trim().length < 2) { pokazFlash('Podaj tytuł utworu (min 2 znaki)', 'blad'); return; }
        djCall('zamow', { tytul: tytul.trim(), artysta: artysta.trim(), notka: notka.trim() });
    };
    window.djPusc = function(id) { djCall('ustaw_grany', { id: id }); };
    window.djOdrzuc = function(id) {
        if (!confirm('Odrzucić ten utwór z kolejki?')) return;
        djCall('odrzuc', { id: id });
    };
    window.djNastepny = function() {
        // Bierze pierwszy z kolejki i ustawia jako grany
        const firstRow = document.querySelector('.dj-kolejka-list .dj-row .play-btn');
        if (!firstRow) { pokazFlash('Kolejka pusta', 'blad'); return; }
        firstRow.click();
    };
    window.djZakoncz = function() {
        if (!confirm('Zakończyć aktualny utwór? Zapadnie cisza.')) return;
        djCall('zakoncz', {});
    };
    window.djOgloszenie = function() {
        const tytul = (document.getElementById('dj-recznie-tytul') || {}).value || '';
        const artysta = (document.getElementById('dj-recznie-artysta') || {}).value || '';
        if (tytul.trim().length < 2) { pokazFlash('Podaj tytuł', 'blad'); return; }
        djCall('ogloszenie_recznie', { tytul: tytul.trim(), artysta: artysta.trim() });
    };

    // ══════════════════════════════════════════════════════════════
    // FAZA 3 — SZEPTY, FLIRTY, WYPROSZENIA
    // ══════════════════════════════════════════════════════════════

    // ── TOAST: SZEPT MG ──────────────────────────────────────────
    function pokazSzept(s) {
        const box = document.createElement('div');
        box.className = 'klub-szept-toast';
        box.innerHTML = `
            <div class="szept-head">
                <span class="szept-ic">🜂</span>
                <span class="szept-od">Szept od <b>${escapeHtml(s.od_login)}</b></span>
                <span class="szept-czas">${escapeHtml(s.czas)}</span>
                <button type="button" class="szept-close">✕</button>
            </div>
            <div class="szept-tresc">${parseRich(s.tresc)}</div>
        `;
        document.body.appendChild(box);
        setTimeout(() => box.classList.add('on'), 10);
        const close = () => { box.classList.remove('on'); setTimeout(() => box.remove(), 400); };
        box.querySelector('.szept-close').addEventListener('click', close);
        // Auto-zamknij po 30s
        setTimeout(close, 30000);
    }

    // ── TOAST: FLIRT (sygnał) ────────────────────────────────────
    function pokazFlirt(f) {
        const box = document.createElement('div');
        box.className = 'klub-flirt-toast';
        box.innerHTML = `
            <span class="flirt-ic">✦</span>
            <span class="flirt-text"><b>${escapeHtml(f.od_login)}</b> dyskretnie Ci się przygląda</span>
            <span class="flirt-czas">${escapeHtml(f.czas)}</span>
        `;
        document.body.appendChild(box);
        setTimeout(() => box.classList.add('on'), 10);
        setTimeout(() => {
            box.classList.remove('on');
            setTimeout(() => box.remove(), 400);
        }, 6000);
    }

    // ── PEŁNOEKRANOWY KOMUNIKAT: WYPROSZONY ──────────────────────
    function pokazWyproszenie(w) {
        // Zapobiec duplikatom
        if (document.getElementById('klub-wyproszenie-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'klub-wyproszenie-overlay';
        overlay.className = 'klub-wyproszenie';
        overlay.innerHTML = `
            <div class="wyp-box">
                <div class="wyp-ic">🚪</div>
                <div class="wyp-tytul">Wyproszony/a z sali</div>
                <div class="wyp-tresc">
                    <b>${escapeHtml(w.barman_login)}</b> wyprosił/a Cię z tej sali.<br>
                    Wracasz do Lobby.
                </div>
                ${w.powod ? `<div class="wyp-powod">Powód: <i>${escapeHtml(w.powod)}</i></div>` : ''}
                <div class="wyp-do-kiedy">Wracasz do tej sali po: <b>${escapeHtml(w.do_kiedy_fmt)}</b></div>
                <a href="game.php?page=czat&sala=lobby" class="wyp-btn">◂ Wróć do Lobby</a>
            </div>
        `;
        document.body.appendChild(overlay);
        setTimeout(() => overlay.classList.add('on'), 10);
        // Auto-redirect po 5s
        setTimeout(() => {
            window.location.href = 'game.php?page=czat&sala=lobby';
        }, 5000);
    }

    // ── ODŚWIEŻ WIDGET "SEKRETNE SYGNAŁY" ────────────────────────
    async function odswiezSygnaly() {
        const widget = document.getElementById('klub-sygnaly-lista');
        if (!widget) return;
        // Nic nie pobieramy — odświeżamy reload bo flirty są w state widgetu
        // (server-side render przy ładowaniu strony)
        // Jeśli chcemy live update, można pobrać GET na osobny endpoint.
        // Na razie: dorzucamy klasę .nowy do widgetu — animacja
        widget.classList.add('pulse');
        setTimeout(() => widget.classList.remove('pulse'), 2000);
    }

    // ── RENDERMSG OVERRIDE: npc_speak (Faza 3) ───────────────────
    // Edytuję istniejący renderMsg żeby obsłużył typ npc_speak
    // (poprzednia wersja patrzyła tylko na string [NPC] w loginie)
    const oryginalnyRenderMsg = renderMsg;
    renderMsg = function(m) {
        if (m.typ === 'npc_speak') {
            const cleanLogin = (m.login || '').replace(/\s*\[(BARMAN|MG|NPC)\]/gi, '');
            const ini = inicjaly(m.login || '');
            const tresc = parseRich(m.tresc);
            const summonNote = m.summoner_login
                ? `<span class="summon-by">wyzwał: ${escapeHtml(m.summoner_login)}</span>`
                : '';
            const div = document.createElement('div');
            div.className = 'msg npc speak';
            div.dataset.id = m.id;
            div.innerHTML = `
                <div class="av">${escapeHtml(ini)}</div>
                <div class="body">
                    <div class="who">
                        <span class="nm">${escapeHtml(cleanLogin)}</span>
                        <span class="role">NPC</span>
                        ${summonNote}
                        <span class="when">${escapeHtml(m.czas)}</span>
                    </div>
                    <div class="txt">${tresc}</div>
                </div>
            `;
            return div;
        }
        return oryginalnyRenderMsg(m);
    };

    // ── AUTO-RESIZE TEXTAREA ─────────────────────────────────────
    if (input) {
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        });
    }

    // ── DETEKCJA SCROLLA — gdy user wraca na dół, kasuj toast ───
    feed.addEventListener('scroll', () => {
        if (isAtBottom() && pendingNew > 0) {
            pendingNew = 0;
            hideNewToast();
        }
    });

    // ── PIERWSZY RENDER + AUTOMATYKA ─────────────────────────────
    scrollToBottom();
    refreshTimer = setInterval(fetchNew, REFRESH_MS);

    // Pause when tab hidden, resume when visible
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
        } else {
            fetchNew();
            if (!refreshTimer) refreshTimer = setInterval(fetchNew, REFRESH_MS);
        }
    });

})();