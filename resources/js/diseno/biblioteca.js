/**
 * Panel Biblioteca → ITO Diseño: buscar e insertar recursos publicados.
 */

/**
 * @param {{
 *   apiUrl: string,
 *   onInsert: (item: {id:number, titulo:string, archivo_url:string}) => void,
 *   root?: HTMLElement,
 * }} opts
 */
export function initBibliotecaPanel(opts) {
    const { apiUrl, onInsert } = opts;
    const panel = document.querySelector('[data-drawer="biblioteca"]');
    if (!panel || !apiUrl) return { reload: () => {} };

    let page = 1;
    let lastPage = 1;
    let loading = false;
    let q = '';
    let tag = '';
    let debounce = null;

    panel.innerHTML = `
        <h3 class="diseno-drawer-title">Biblioteca</h3>
        <p class="diseno-hint">Imágenes y videos (miniatura) publicados. Clic o arrastrá al lienzo.</p>
        <div class="diseno-biblio-search">
            <input type="search" id="disenoBiblioQ" placeholder="Buscar fotos, logos, videos…" autocomplete="off">
        </div>
        <div class="diseno-biblio-tags" id="disenoBiblioTags"></div>
        <div class="diseno-biblio-grid" id="disenoBiblioGrid"></div>
        <div class="diseno-biblio-foot">
            <button type="button" class="diseno-btn diseno-btn-ghost diseno-btn-sm" id="disenoBiblioMore" hidden>Cargar más</button>
            <a class="diseno-biblio-link" href="/biblioteca" target="_blank" rel="noopener">Abrir Biblioteca ↗</a>
        </div>
    `;

    const grid = panel.querySelector('#disenoBiblioGrid');
    const tagsEl = panel.querySelector('#disenoBiblioTags');
    const moreBtn = panel.querySelector('#disenoBiblioMore');
    const input = panel.querySelector('#disenoBiblioQ');

    function insertUrl(item) {
        return item.insert_url || item.thumb_url || item.miniatura_url || item.archivo_url || '';
    }

    function cardHtml(item) {
        const thumb = item.thumb_url || item.miniatura_url || item.archivo_url;
        const title = escapeHtml(item.titulo || 'Sin título');
        const url = insertUrl(item);
        const badge = item.tipo === 'video' ? '<span class="diseno-biblio-badge">video</span>' : '';
        return `<button type="button" class="diseno-biblio-card" draggable="true"
            data-id="${item.id}"
            data-url="${escapeAttr(url)}"
            data-titulo="${escapeAttr(item.titulo || 'Biblioteca')}"
            title="${title}">
            <span class="diseno-biblio-thumb" style="background-image:url('${escapeAttr(thumb || '')}')">${badge}</span>
            <span class="diseno-biblio-cap">${title}</span>
        </button>`;
    }

    function bindCards(scope) {
        scope.querySelectorAll('.diseno-biblio-card').forEach((btn) => {
            btn.addEventListener('click', () => {
                const url = btn.dataset.url;
                if (!url) return;
                onInsert({
                    id: Number(btn.dataset.id),
                    titulo: btn.dataset.titulo || 'Biblioteca',
                    archivo_url: url,
                });
            });
            btn.addEventListener('dragstart', (e) => {
                const payload = JSON.stringify({
                    id: Number(btn.dataset.id),
                    titulo: btn.dataset.titulo,
                    archivo_url: btn.dataset.url,
                });
                e.dataTransfer.setData('application/x-chilinga-biblio', payload);
                e.dataTransfer.setData('text/uri-list', btn.dataset.url);
                e.dataTransfer.effectAllowed = 'copy';
            });
        });
    }

    function renderTags(tags) {
        if (!tagsEl) return;
        const all = [{ nombre: 'Todos', slug: '', usos: 0 }, ...(tags || [])];
        tagsEl.innerHTML = all.map((t) =>
            `<button type="button" class="diseno-biblio-tag ${t.slug === tag ? 'on' : ''}" data-tag="${escapeAttr(t.slug)}">${escapeHtml(t.nombre)}</button>`
        ).join('');
        tagsEl.querySelectorAll('[data-tag]').forEach((b) => {
            b.addEventListener('click', () => {
                tag = b.dataset.tag || '';
                page = 1;
                load(true);
            });
        });
    }

    async function load(reset) {
        if (loading) return;
        loading = true;
        if (reset) {
            grid.innerHTML = '<p class="diseno-hint">Cargando…</p>';
        }
        try {
            const params = new URLSearchParams({
                tipo: 'canvas',
                page: String(page),
                per_page: '24',
            });
            if (q) params.set('q', q);
            if (tag) params.set('tag', tag);
            const res = await fetch(`${apiUrl}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            lastPage = json.meta?.last_page || 1;
            if (page === 1) renderTags(json.tags);
            const items = Array.isArray(json.data)
                ? json.data.filter((i) => insertUrl(i))
                : [];
            if (reset) {
                if (!items.length) {
                    grid.innerHTML = '<p class="diseno-hint">No hay imágenes/videos publicados. Subí en Biblioteca o usá Marca / Subidos.</p>';
                } else {
                    grid.innerHTML = items.map(cardHtml).join('');
                }
            } else {
                grid.insertAdjacentHTML('beforeend', items.map(cardHtml).join(''));
            }
            bindCards(grid);
            if (moreBtn) moreBtn.hidden = page >= lastPage;
        } catch (err) {
            console.error('ITO Diseño: biblioteca', err);
            if (reset) grid.innerHTML = '<p class="diseno-hint">No se pudo cargar la Biblioteca.</p>';
        } finally {
            loading = false;
        }
    }

    input?.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            q = input.value.trim();
            page = 1;
            load(true);
        }, 280);
    });

    moreBtn?.addEventListener('click', () => {
        if (page >= lastPage) return;
        page += 1;
        load(false);
    });

    load(true);

    return {
        reload: () => { page = 1; load(true); },
    };
}

/**
 * Drop de ítem Biblioteca sobre el stage.
 */
export function bindBibliotecaDrop(stageEl, onInsert) {
    if (!stageEl) return;
    stageEl.addEventListener('dragover', (e) => {
        if ([...e.dataTransfer.types].includes('application/x-chilinga-biblio')) {
            e.preventDefault();
            stageEl.classList.add('diseno-drop-biblio');
        }
    });
    stageEl.addEventListener('dragleave', () => stageEl.classList.remove('diseno-drop-biblio'));
    stageEl.addEventListener('drop', (e) => {
        stageEl.classList.remove('diseno-drop-biblio');
        const raw = e.dataTransfer.getData('application/x-chilinga-biblio');
        if (!raw) return;
        e.preventDefault();
        try {
            const item = JSON.parse(raw);
            if (item?.archivo_url) onInsert(item);
        } catch { /* ignore */ }
    });
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(s) {
    return escapeHtml(s).replace(/'/g, '&#39;');
}
