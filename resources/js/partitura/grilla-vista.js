/**
 * Vista de grilla del editor: una fila por instrumento, una columna por paso.
 * Lee el mismo score que la partitura; al editar un golpe se vuelve a dibujar solo ese
 * compás (no toda la grilla).
 */
import { ticksDeCompas } from './model.js';
import { GOLPES, instrumentoPorId, golpesDe } from './instruments.js';
import { celdasDeVoz, etiquetasDePasos, pasosPorCompas, ticksDeResolucion } from './grilla.js';

const FUERTES = new Set(['acentuado', 'slap', 'abierto']);

export class VistaGrilla {
    /**
     * @param {import('./editor.js').EditorPartitura} editor
     * @param {HTMLElement} host
     */
    constructor(editor, host) {
        this.ed = editor;
        this.host = host;
        this.medidas = new Map();
        this._presion = null;

        host.addEventListener('click', (e) => {
            const cel = e.target.closest('.pt-cel');
            if (!cel || cel.disabled) return;
            if (this._largo) { this._largo = false; return; }
            this.ed.grillaClick(datos(cel));
        });
        host.addEventListener('contextmenu', (e) => {
            const cel = e.target.closest('.pt-cel');
            if (!cel || cel.disabled) return;
            e.preventDefault();
            this.ed.grillaMenu(datos(cel), e.clientX, e.clientY);
        });
        // Pulsación larga (touch): menú de golpes.
        host.addEventListener('pointerdown', (e) => {
            const cel = e.target.closest('.pt-cel');
            if (!cel || cel.disabled || e.pointerType === 'mouse') return;
            clearTimeout(this._presion);
            this._presion = setTimeout(() => {
                this._largo = true;
                this.ed.grillaMenu(datos(cel), e.clientX, e.clientY);
            }, 480);
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach((ev) => host.addEventListener(ev, () => clearTimeout(this._presion)));
        host.addEventListener('focusin', () => this.ed.setFoco('grilla'));
    }

    get paso() {
        return ticksDeResolucion(this.ed.resolucion);
    }

    instrumentos() {
        return this.ed.score.instruments.filter((i) => i.visible !== false);
    }

    render() {
        const score = this.ed.score;
        const paso = this.paso;
        const etiquetas = etiquetasDePasos(score.timeSignature, paso);
        const insts = this.instrumentos();
        const soloActivo = score.instruments.some((i) => i.solo);
        this.medidas.clear();
        this.host.style.setProperty('--pasos', String(pasosPorCompas(score.timeSignature, paso)));
        this.host.innerHTML = score.sections.map((sec, si) => `
            <section class="pt-gsec" data-s="${si}" aria-label="${esc(sec.name)}">
                <header class="pt-gsec-head"><strong>${esc(sec.name)}</strong>${sec.repeatX > 1 ? ` <span class="pt-muted">×${sec.repeatX}</span>` : ''}</header>
                <div class="pt-gtrack">
                    <div class="pt-gnames" aria-hidden="false">
                        <div class="pt-gname pt-gname-head"></div>
                        ${insts.map((cfg) => {
                            const def = instrumentoPorId(cfg.id);
                            const dim = cfg.mute || (soloActivo && !cfg.solo);
                            return `<div class="pt-gname ${dim ? 'dim' : ''}" data-inst="${cfg.id}">
                                <span class="pt-dot-color" style="background:${def?.color || '#999'}"></span>
                                <span class="pt-gname-txt">${esc(def?.short || cfg.id)}</span>
                                <button class="pt-mini" data-a="preview" title="Escuchar ${esc(def?.label || cfg.id)}" aria-label="Escuchar ${esc(def?.label || cfg.id)}">♪</button>
                                <button class="pt-mini ${cfg.mute ? 'on' : ''}" data-a="mute" title="Silenciar" aria-pressed="${cfg.mute}">M</button>
                                <button class="pt-mini ${cfg.solo ? 'on' : ''}" data-a="solo" title="Solo" aria-pressed="${cfg.solo}">S</button>
                            </div>`;
                        }).join('')}
                    </div>
                    <div class="pt-gmeasures">
                        ${sec.measures.map((m, mi) => this.htmlCompas(si, mi, etiquetas, insts)).join('')}
                    </div>
                </div>
            </section>`).join('');
        this.host.querySelectorAll('.pt-gm').forEach((el) => this.medidas.set(`${el.dataset.s}:${el.dataset.m}`, el));
        this.marcarCursor();
        this.marcarRango();
    }

    htmlCompas(si, mi, etiquetas, insts) {
        const score = this.ed.score;
        const m = score.sections[si].measures[mi];
        const cap = ticksDeCompas(score.timeSignature);
        const paso = this.paso;
        const proximo = etiquetas.findIndex((e, k) => k > 0 && /^\d+$/.test(e));
        const sub = proximo > 0 ? proximo : etiquetas.length;
        return `<div class="pt-gm" data-s="${si}" data-m="${mi}" role="grid" aria-label="Compás ${mi + 1}">
            <div class="pt-gm-head" role="row">
                <span class="pt-gm-num" title="Compás ${mi + 1}">c${mi + 1}</span>
                ${etiquetas.map((e, k) => `<span class="pt-gm-lbl ${k % sub === 0 ? 'beat' : ''}">${e}</span>`).join('')}
            </div>
            ${m.sena?.texto ? `<div class="pt-gm-sena" title="Seña">✋ ${esc(m.sena.texto)}</div>` : ''}
            ${insts.map((cfg) => {
                const def = instrumentoPorId(cfg.id);
                const celdas = celdasDeVoz(m.voces[cfg.id], cap, paso);
                return `<div class="pt-grow" role="row" data-inst="${cfg.id}">${celdas.map((c, k) => this.htmlCelda(c, k, si, mi, cfg.id, def, sub)).join('')}</div>`;
            }).join('')}
        </div>`;
    }

    htmlCelda(c, k, si, mi, instId, def, sub) {
        const beat = k % sub === 0 ? ' beat' : '';
        const base = `data-s="${si}" data-m="${mi}" data-i="${instId}" data-k="${k}"`;
        const nombre = def?.label || instId;
        if (c.tipo === 'fuera') {
            return `<button class="pt-cel fuera${beat}" ${base} disabled title="Figura fuera de la grilla: editala en la partitura" aria-label="${esc(nombre)} paso ${k + 1}: fuera de grilla">⋯</button>`;
        }
        if (c.tipo === 'golpe') {
            const g = GOLPES[c.nota.stroke] || GOLPES.nota;
            const intensidad = intensidadDe(c.nota);
            return `<button class="pt-cel golpe ${intensidad}${beat}" ${base} style="--c:${def?.color || '#999'}" aria-pressed="true" aria-label="${esc(nombre)} paso ${k + 1}: ${esc(g.label)}${c.nota.dyn ? ` ${c.nota.dyn}` : ''}">${simbolo(c.nota)}</button>`;
        }
        if (c.tipo === 'sostiene') {
            return `<button class="pt-cel sostiene${beat}" ${base} style="--c:${def?.color || '#999'}" aria-pressed="false" aria-label="${esc(nombre)} paso ${k + 1}: sigue sonando">·</button>`;
        }
        return `<button class="pt-cel${beat}" ${base} aria-pressed="false" aria-label="${esc(nombre)} paso ${k + 1}: vacío"></button>`;
    }

    renderCompas(si, mi) {
        const viejo = this.medidas.get(`${si}:${mi}`);
        if (!viejo) return this.render();
        const etiquetas = etiquetasDePasos(this.ed.score.timeSignature, this.paso);
        const tmp = document.createElement('div');
        tmp.innerHTML = this.htmlCompas(si, mi, etiquetas, this.instrumentos());
        const nuevo = tmp.firstElementChild;
        viejo.replaceWith(nuevo);
        this.medidas.set(`${si}:${mi}`, nuevo);
        this.marcarCursor();
        this.marcarRango();
    }

    celda(g) {
        const el = this.medidas.get(`${g.sectionIdx}:${g.measureIdx}`);
        return el?.querySelector(`.pt-grow[data-inst="${g.instId}"] .pt-cel[data-k="${g.paso}"]`) || null;
    }

    marcarCursor() {
        this.host.querySelectorAll('.pt-cel.is-cursor').forEach((c) => c.classList.remove('is-cursor'));
        const g = this.ed.gsel;
        if (!g) return;
        const cel = this.celda(g);
        if (!cel) return;
        cel.classList.add('is-cursor');
        if (this.ed.foco === 'grilla' && document.activeElement !== cel && this.host.contains(document.activeElement)) cel.focus({ preventScroll: true });
        const r = cel.getBoundingClientRect();
        const h = this.host.getBoundingClientRect();
        if (r.right > h.right || r.left < h.left || r.bottom > h.bottom || r.top < h.top) {
            cel.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }

    marcarRango() {
        this.host.querySelectorAll('.pt-gm.is-rango').forEach((m) => m.classList.remove('is-rango'));
        const r = this.ed.rango;
        if (!r) return;
        for (let mi = Math.min(r.desde, r.hasta); mi <= Math.max(r.desde, r.hasta); mi++) {
            this.medidas.get(`${r.sectionIdx}:${mi}`)?.classList.add('is-rango');
        }
    }

    marcarPlay(pos) {
        this.limpiarPlay();
        if (!pos || pos.countIn) return;
        const el = this.medidas.get(`${pos.sectionIdx}:${pos.measureIdx}`);
        if (!el) return;
        const n = pasosPorCompas(this.ed.score.timeSignature, this.paso);
        const k = Math.min(n - 1, Math.floor((pos.frac || 0) * n));
        el.classList.add('is-play');
        el.querySelectorAll(`.pt-cel[data-k="${k}"], .pt-gm-lbl:nth-of-type(${k + 2})`).forEach((c) => c.classList.add('is-now'));
        if (this.ed.seguirReproduccion) {
            const r = el.getBoundingClientRect();
            const h = this.host.getBoundingClientRect();
            if (r.left < h.left || r.right > h.right || r.top < h.top || r.bottom > h.bottom) el.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'auto' });
        }
    }

    limpiarPlay() {
        this.host.querySelectorAll('.is-now').forEach((c) => c.classList.remove('is-now'));
        this.host.querySelectorAll('.pt-gm.is-play').forEach((c) => c.classList.remove('is-play'));
    }
}

function datos(cel) {
    return { sectionIdx: Number(cel.dataset.s), measureIdx: Number(cel.dataset.m), instId: cel.dataset.i, paso: Number(cel.dataset.k) };
}

/** Intensidad visible por tamaño (no solo color): fuerte, normal o suave. */
export function intensidadDe(nota) {
    if (nota.stroke === 'fantasma' || (nota.vel && nota.vel < 50) || nota.dyn === 'pp' || nota.dyn === 'p') return 'suave';
    if (FUERTES.has(nota.stroke) || (nota.vel && nota.vel > 110) || nota.dyn === 'f' || nota.dyn === 'ff') return 'fuerte';
    return 'normal';
}

function simbolo(nota) {
    if (nota.stroke === 'acentuado') return '◆';
    if (nota.stroke === 'fantasma') return '◦';
    if (nota.stroke === 'nota' || nota.stroke === 'abierto' || nota.stroke === 'palma') return '●';
    return (GOLPES[nota.stroke] || GOLPES.nota).short;
}

/** Golpes del instrumento para el menú contextual. */
export function opcionesDeGolpe(instId) {
    return golpesDe(instId);
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
