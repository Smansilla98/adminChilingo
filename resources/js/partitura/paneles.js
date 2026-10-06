/**
 * Paneles del editor de ritmos: ayuda de atajos (generada desde el registro),
 * preferencias de atajos, versiones y menú de golpe de la grilla.
 */
import { normalizarTecla, teclaLegible } from './atajos.js';
import { GOLPES, DINAMICAS, instrumentoPorId } from './instruments.js';
import { opcionesDeGolpe } from './grilla-vista.js';

/** Diálogo modal nativo (<dialog>): foco atrapado, Esc cierra. */
export function abrirDialogo(root, { titulo, cuerpo, clase = '' }) {
    root.querySelectorAll('dialog.pt-dialog').forEach((d) => d.remove());
    const d = document.createElement('dialog');
    d.className = `pt-dialog ${clase}`;
    d.setAttribute('aria-label', titulo);
    d.innerHTML = `
        <header class="pt-dialog-head"><h2>${esc(titulo)}</h2><button type="button" class="pt-btn pt-btn-ghost" data-cerrar aria-label="Cerrar">✕</button></header>
        <div class="pt-dialog-body">${cuerpo}</div>`;
    root.appendChild(d);
    d.querySelector('[data-cerrar]').addEventListener('click', () => d.close());
    d.addEventListener('close', () => d.remove());
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
    return d;
}

const CONTEXTOS = { global: 'Siempre', partitura: 'Partitura', grilla: 'Grilla', tocar: 'Modo tocar' };

export function mostrarAyuda(ed) {
    const grupos = ed.atajos.ayuda();
    const d = abrirDialogo(ed.root, {
        titulo: 'Atajos de teclado',
        clase: 'pt-dialog-ancho',
        cuerpo: `
            <p class="pt-muted">El foco decide qué hacen las letras: en la <b>partitura</b> eligen golpe y figura; en la <b>grilla</b> y en <b>modo tocar</b> cada letra es un instrumento.</p>
            <div class="pt-ayuda">
                ${grupos.map((g) => `
                    <section><h3>${esc(g.grupo)}</h3>
                        <dl>${g.items.map((i) => `<div><dt>${i.teclas.map((t) => `<kbd>${esc(t)}</kbd>`).join(' ')}</dt><dd>${esc(i.etiqueta)}${i.contextos.includes('global') ? '' : ` <small class="pt-muted">· ${i.contextos.map((c) => CONTEXTOS[c] || c).join(', ')}</small>`}</dd></div>`).join('')}</dl>
                    </section>`).join('')}
            </div>
            <p><button type="button" class="pt-btn" data-personalizar>Personalizar atajos…</button></p>`,
    });
    d.querySelector('[data-personalizar]').addEventListener('click', () => { d.close(); mostrarPreferencias(ed); });
}

export function mostrarPreferencias(ed) {
    const editables = [...ed.atajos.acciones.values()].filter((a) => a.personalizable);
    const d = abrirDialogo(ed.root, {
        titulo: 'Personalizar atajos',
        cuerpo: `
            <p class="pt-muted">Hacé clic en una tecla y presioná la nueva. Si ya está en uso, te avisamos y no se cambia.</p>
            <div class="pt-prefs">
                ${editables.map((a) => `<label class="pt-pref"><span>${esc(a.etiqueta)}</span>
                    <button type="button" class="pt-kbd-btn" data-accion="${a.id}" aria-label="Tecla para ${esc(a.etiqueta)}: ${esc(teclaLegible(a.teclas[0] || ''))}">${esc(teclaLegible(a.teclas[0] || '—'))}</button></label>`).join('')}
            </div>
            <p class="pt-pref-aviso" role="alert" data-aviso hidden></p>
            <p><button type="button" class="pt-btn" data-restaurar>Volver a los atajos originales</button></p>`,
    });
    const aviso = d.querySelector('[data-aviso]');
    d.querySelectorAll('[data-accion]').forEach((btn) => {
        btn.addEventListener('click', () => {
            btn.textContent = 'Presioná una tecla…';
            btn.classList.add('esperando');
            const capturar = (e) => {
                e.preventDefault();
                e.stopPropagation();
                document.removeEventListener('keydown', capturar, true);
                btn.classList.remove('esperando');
                if (e.key === 'Escape') { btn.textContent = teclaLegible(ed.atajos.acciones.get(btn.dataset.accion).teclas[0]); return; }
                const r = ed.atajos.asignar(btn.dataset.accion, normalizarTecla(e));
                aviso.hidden = r.ok;
                if (!r.ok) aviso.textContent = `⚠ ${r.conflicto}`;
                btn.textContent = teclaLegible(ed.atajos.acciones.get(btn.dataset.accion).teclas[0]);
                ed.guardarAtajos();
            };
            document.addEventListener('keydown', capturar, true);
        });
    });
    d.querySelector('[data-restaurar]').addEventListener('click', () => {
        ed.atajos.restaurar();
        ed.guardarAtajos();
        d.close();
        mostrarPreferencias(ed);
    });
}

export async function mostrarVersiones(ed) {
    const d = abrirDialogo(ed.root, { titulo: 'Versiones publicadas', cuerpo: '<p class="pt-muted">Cargando…</p>' });
    const cuerpo = d.querySelector('.pt-dialog-body');
    try {
        const res = await fetch(ed.versionesUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const { data } = await res.json();
        if (!data.length) {
            cuerpo.innerHTML = '<p class="pt-muted">Todavía no hay versiones. Se crea una cada vez que publicás.</p>';
            return;
        }
        cuerpo.innerHTML = `
            <p class="pt-muted">Abrir una versión la carga en el editor sin publicarla. Si te sirve, publicala.</p>
            <ol class="pt-versiones">${data.map((v) => `
                <li><div><strong>v${v.numero}</strong> · ${esc(v.autor || 'sin firma')} · ${v.fecha ? new Date(v.fecha).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : ''}
                    ${v.nota ? `<br><small>${esc(v.nota)}</small>` : ''}<br><small class="pt-muted">${v.resumen?.compases ?? '?'} compases · ${v.resumen?.golpes ?? '?'} golpes</small></div>
                    <button type="button" class="pt-btn" data-version="${v.numero}">Abrir</button></li>`).join('')}</ol>`;
        cuerpo.querySelectorAll('[data-version]').forEach((b) => b.addEventListener('click', async () => {
            b.disabled = true;
            await ed.cargarVersion(Number(b.dataset.version));
            d.close();
        }));
    } catch (err) {
        cuerpo.innerHTML = `<p class="pt-muted">No se pudieron cargar las versiones (${esc(err.message)}).</p>`;
    }
}

/** Menú de golpe de una celda de la grilla (clic derecho o pulsación larga). */
export function menuGolpe(ed, celda, x, y) {
    ed.root.querySelectorAll('.pt-menu-golpe').forEach((m) => m.remove());
    const def = instrumentoPorId(celda.instId);
    const menu = document.createElement('div');
    menu.className = 'pt-menu-golpe';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', `Golpe de ${def?.label || celda.instId}`);
    menu.innerHTML = `
        <strong>${esc(def?.label || celda.instId)} · paso ${celda.paso + 1}</strong>
        <div class="pt-menu-grid">${opcionesDeGolpe(celda.instId).map((g) => `<button type="button" role="menuitem" data-golpe="${g.id}"><span>${esc(g.short)}</span>${esc(g.label)}</button>`).join('')}</div>
        <div class="pt-menu-grid pt-menu-dyn">${DINAMICAS.map((dy) => `<button type="button" role="menuitem" data-dyn="${dy}"><i>${dy}</i></button>`).join('')}</div>
        <button type="button" role="menuitem" data-quitar>Quitar golpe</button>`;
    ed.root.appendChild(menu);
    const r = ed.root.getBoundingClientRect();
    menu.style.left = `${Math.min(x - r.left, r.width - 240)}px`;
    menu.style.top = `${Math.min(y - r.top, r.height - 260) + ed.root.scrollTop}px`;
    menu.querySelector('button')?.focus();
    const cerrar = () => { menu.remove(); document.removeEventListener('pointerdown', fuera, true); };
    const fuera = (e) => { if (!menu.contains(e.target)) cerrar(); };
    setTimeout(() => document.addEventListener('pointerdown', fuera, true), 0);
    menu.addEventListener('keydown', (e) => { if (e.key === 'Escape') { cerrar(); ed.grilla?.marcarCursor(); } });
    menu.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.golpe) ed.grillaPoner(celda, { stroke: b.dataset.golpe });
        else if (b.dataset.dyn) ed.grillaPoner(celda, { dyn: b.dataset.dyn });
        else if (b.hasAttribute('data-quitar')) ed.grillaQuitar(celda);
        cerrar();
    });
}

/** Cartel del teclado: muestra qué tecla sonó y qué instrumento (descubrible). */
export function feedbackTecla(ed, tecla, texto) {
    let el = ed.root.querySelector('.pt-tecla-fb');
    if (!el) {
        el = document.createElement('div');
        el.className = 'pt-tecla-fb';
        el.setAttribute('aria-hidden', 'true');
        ed.root.appendChild(el);
    }
    el.innerHTML = `<kbd>${esc(tecla)}</kbd> ${esc(texto)}`;
    el.classList.remove('show');
    void el.offsetWidth;
    el.classList.add('show');
    clearTimeout(ed._fb);
    ed._fb = setTimeout(() => el.classList.remove('show'), 900);
}

export function golpeLabel(id) {
    return GOLPES[id]?.label || id;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
