/**
 * Editor de partituras tipo MuseScore para los toques de La Chilinga.
 *
 * Layout: toolbar de transporte + paletas laterales + lienzo central (VexFlow) + mixer/inspector.
 * Trabaja siempre sobre el modelo v4 (resources/js/partitura/model.js).
 */
import {
    DURACIONES, ops, normalizarPartitura, clonar, resumen, notaDe, vozDe,
    ticksDeCompas, ticksDeVoz, crearPartitura,
} from './model.js';
import { INSTRUMENTOS, instrumentoPorId, golpesDe, GOLPES, DINAMICAS, MARCAS_TEXTO } from './instruments.js';
import { renderScore } from './renderer.js';
import { MotorAudio } from './audio.js';
import { exportarPNG, exportarPDF, exportarMusicXML, exportarMIDI } from './exporters.js';

const MAX_UNDO = 60;

export class EditorPartitura {
    /**
     * @param {HTMLElement} root
     * @param {{ score?: object, saveUrl?: string|null, backUrl?: string|null, parteUrl?: string|null, readonly?: boolean }} [opts]
     */
    constructor(root, opts = {}) {
        this.root = root;
        this.saveUrl = opts.saveUrl || null;
        this.backUrl = opts.backUrl || null;
        this.parteUrl = opts.parteUrl || null;
        this.readonly = !!opts.readonly;

        this.score = normalizarPartitura(opts.score || crearPartitura());
        this.sel = null;              // {sectionIdx, measureIdx, instId, noteIdx}
        this.durActiva = 'q';
        this.dotsActivos = 0;
        this.modoSilencio = false;
        this.zoom = 1;
        this.undoStack = [];
        this.redoStack = [];
        this.hits = [];
        this.measureBoxes = [];
        this.dirty = false;
        this.guardando = false;
        this.audio = new MotorAudio();
        this.audio.onMeasure = (pos) => this.marcarPlayhead(pos);
        this.audio.onStop = () => this.finTransporte();

        this.construir();
        this.bindTeclado();
        this.render();
        this.seleccionInicial();
    }

    /** ------------------------------------------------------------- construcción UI */

    construir() {
        this.root.classList.add('pt-app');
        this.root.innerHTML = `
            <header class="pt-toolbar">
                <div class="pt-tb-group pt-tb-title">
                    ${this.backUrl ? `<a class="pt-btn pt-btn-ghost" href="${attr(this.backUrl)}" title="Volver">←</a>` : ''}
                    <input class="pt-input pt-input-title" data-f="title" value="${attr(this.score.title)}" placeholder="Título del toque" ${this.readonly ? 'disabled' : ''}>
                    <input class="pt-input pt-input-autor" data-f="autor" value="${attr(this.score.autor || '')}" placeholder="Autor / arreglo" ${this.readonly ? 'disabled' : ''}>
                </div>
                <div class="pt-tb-group">
                    <button class="pt-btn pt-btn-play" data-a="play" title="Reproducir (Espacio)">▶</button>
                    <button class="pt-btn" data-a="stop" title="Detener">■</button>
                    <button class="pt-btn pt-toggle" data-a="loop" title="Loop">↻</button>
                    <button class="pt-btn pt-toggle" data-a="metro" title="Metrónomo">𝅘𝅥</button>
                </div>
                <div class="pt-tb-group">
                    <label class="pt-field"><span>Tempo</span>
                        <input class="pt-input pt-input-num" type="number" min="30" max="260" data-f="tempo" value="${this.score.tempo}">
                    </label>
                    <label class="pt-field"><span>Compás</span>
                        <select class="pt-input pt-select" data-f="ts">
                            ${['4/4', '2/4', '3/4', '6/8', '12/8', '2/2'].map((t) => {
                                const cur = `${this.score.timeSignature.num}/${this.score.timeSignature.den}`;
                                return `<option value="${t}" ${t === cur ? 'selected' : ''}>${t}</option>`;
                            }).join('')}
                        </select>
                    </label>
                </div>
                <div class="pt-tb-group pt-tb-dur" data-zone="duraciones">
                    ${DURACIONES.map((d) => `<button class="pt-btn pt-dur" data-dur="${d.code}" title="${d.label} (${d.tecla})">${figuraSvg(d.code)}<em>${d.tecla}</em></button>`).join('')}
                    <button class="pt-btn pt-dot" data-a="dot" title="Puntillo (.)">.</button>
                    <button class="pt-btn pt-rest" data-a="rest" title="Silencio (R)">𝄽</button>
                </div>
                <div class="pt-tb-group">
                    <button class="pt-btn" data-a="undo" title="Deshacer (Ctrl+Z)">⟲</button>
                    <button class="pt-btn" data-a="redo" title="Rehacer (Ctrl+Y)">⟳</button>
                    <button class="pt-btn" data-a="zoom-out" title="Zoom -">−</button>
                    <span class="pt-zoom-label">100%</span>
                    <button class="pt-btn" data-a="zoom-in" title="Zoom +">+</button>
                </div>
                <div class="pt-tb-group pt-tb-right">
                    <div class="pt-dropdown">
                        <button class="pt-btn" data-a="export-menu">Exportar ▾</button>
                        <div class="pt-dropdown-menu">
                            <button data-a="export-pdf">PDF</button>
                            <button data-a="export-png">PNG</button>
                            <button data-a="export-xml">MusicXML</button>
                            <button data-a="export-midi">MIDI</button>
                        </div>
                    </div>
                    ${this.readonly ? '' : '<button class="pt-btn pt-btn-primary" data-a="save">Guardar</button>'}
                </div>
            </header>

            <div class="pt-body">
                <aside class="pt-palette" data-zone="paleta"></aside>
                <main class="pt-canvas-wrap" tabindex="0">
                    <div class="pt-page" data-zone="page">
                        <div class="pt-canvas" data-zone="canvas"></div>
                    </div>
                </main>
                <aside class="pt-side">
                    <div class="pt-side-block" data-zone="inspector"></div>
                    <div class="pt-side-block" data-zone="mixer"></div>
                    <div class="pt-side-block" data-zone="estructura"></div>
                </aside>
            </div>

            <footer class="pt-status" data-zone="status"></footer>
        `;

        this.el = {
            canvas: this.root.querySelector('[data-zone="canvas"]'),
            page: this.root.querySelector('[data-zone="page"]'),
            wrap: this.root.querySelector('.pt-canvas-wrap'),
            paleta: this.root.querySelector('[data-zone="paleta"]'),
            inspector: this.root.querySelector('[data-zone="inspector"]'),
            mixer: this.root.querySelector('[data-zone="mixer"]'),
            estructura: this.root.querySelector('[data-zone="estructura"]'),
            status: this.root.querySelector('[data-zone="status"]'),
            zoomLabel: this.root.querySelector('.pt-zoom-label'),
        };

        this.pintarPaleta();
        this.root.addEventListener('click', (e) => this.onClick(e));
        this.root.addEventListener('change', (e) => this.onChange(e));
        this.root.addEventListener('input', (e) => this.onInput(e));
        this.el.canvas.addEventListener('click', (e) => this.onCanvasClick(e));
        window.addEventListener('resize', debounce(() => this.render(), 200));
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty && !this.readonly) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    pintarPaleta() {
        this.el.paleta.innerHTML = `
            <div class="pt-pal-block">
                <h3>Golpes</h3>
                <div class="pt-pal-grid" data-zone="golpes"></div>
            </div>
            <div class="pt-pal-block">
                <h3>Dinámicas</h3>
                <div class="pt-pal-grid">
                    ${DINAMICAS.map((d) => `<button class="pt-chip" data-dyn="${d}" title="Dinámica ${d}"><i>${d}</i></button>`).join('')}
                </div>
            </div>
            <div class="pt-pal-block">
                <h3>Grupos</h3>
                <div class="pt-pal-grid">
                    <button class="pt-chip pt-chip-wide" data-a="tuplet-3" title="Tresillo (Ctrl+3)">Tresillo 3</button>
                    <button class="pt-chip pt-chip-wide" data-a="tuplet-6" title="Sextillo (Ctrl+6)">Sextillo 6</button>
                </div>
            </div>
            <div class="pt-pal-block">
                <h3>Repeticiones</h3>
                <div class="pt-pal-grid">
                    <button class="pt-chip" data-a="rep-begin" title="Barra de repetición inicial">𝄆</button>
                    <button class="pt-chip" data-a="rep-end" title="Barra de repetición final">𝄇</button>
                    <button class="pt-chip" data-a="ending-1" title="Casilla 1.">1.</button>
                    <button class="pt-chip" data-a="ending-2" title="Casilla 2.">2.</button>
                    <button class="pt-chip" data-a="ending-off" title="Quitar casilla">✕</button>
                </div>
            </div>
            <div class="pt-pal-block">
                <h3>Marcas</h3>
                <div class="pt-pal-grid">
                    ${MARCAS_TEXTO.map((m) => `<button class="pt-chip pt-chip-wide" data-marca="${m.id}">${m.label}</button>`).join('')}
                    <button class="pt-chip pt-chip-wide" data-a="marca-off">Quitar marca</button>
                </div>
            </div>
            <div class="pt-pal-block">
                <h3>Compases</h3>
                <div class="pt-pal-grid">
                    <button class="pt-chip pt-chip-wide" data-a="measure-add">+ Compás</button>
                    <button class="pt-chip pt-chip-wide" data-a="measure-del">− Compás</button>
                    <button class="pt-chip pt-chip-wide" data-a="measure-clear">Limpiar voz</button>
                    <button class="pt-chip pt-chip-wide" data-a="measure-copy">Copiar voz →</button>
                </div>
            </div>
        `;
    }

    /** ------------------------------------------------------------- render */

    render() {
        const ancho = Math.max(560, Math.floor((this.el.wrap.clientWidth - 48) / this.zoom));
        const r = renderScore(this.el.canvas, this.score, { anchoPagina: ancho });
        this.hits = r.hits;
        this.measureBoxes = r.measureBoxes;
        this.el.page.style.transform = `scale(${this.zoom})`;
        this.el.zoomLabel.textContent = `${Math.round(this.zoom * 100)}%`;
        this.pintarInspector();
        this.pintarMixer();
        this.pintarEstructura();
        this.pintarGolpes();
        this.pintarStatus();
        this.marcarSeleccion();
        this.marcarBotonesDuracion();
    }

    seleccionInicial() {
        if (this.sel) return;
        const inst = this.score.instruments[0];
        if (inst) this.seleccionar({ sectionIdx: 0, measureIdx: 0, instId: inst.id, noteIdx: 0 });
    }

    seleccionar(sel) {
        this.sel = sel;
        this.pintarInspector();
        this.pintarGolpes();
        this.pintarStatus();
        this.marcarSeleccion();
    }

    marcarSeleccion() {
        this.root.querySelectorAll('.pt-sel-box').forEach((n) => n.remove());
        if (!this.sel) return;
        const hit = this.hitDeSeleccion();
        if (!hit) return;
        const box = document.createElement('div');
        box.className = 'pt-sel-box';
        box.style.left = `${hit.x - 4}px`;
        box.style.top = `${hit.y - 4}px`;
        box.style.width = `${hit.w + 8}px`;
        box.style.height = `${hit.h + 8}px`;
        hit.lineEl.appendChild(box);
        if (typeof box.scrollIntoView === 'function') {
            const r = box.getBoundingClientRect();
            const wr = this.el.wrap.getBoundingClientRect();
            if (r.top < wr.top || r.bottom > wr.bottom) box.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    hitDeSeleccion() {
        const s = this.sel;
        return this.hits.find(
            (h) => h.sectionIdx === s.sectionIdx && h.measureIdx === s.measureIdx && h.instId === s.instId && h.noteIdx === s.noteIdx
        );
    }

    marcarPlayhead(pos) {
        this.root.querySelectorAll('.pt-play-box').forEach((n) => n.remove());
        const box = this.measureBoxes.find((b) => b.sectionIdx === pos.sectionIdx && b.measureIdx === pos.measureIdx);
        if (!box) return;
        const el = document.createElement('div');
        el.className = 'pt-play-box';
        el.style.left = `${box.x}px`;
        el.style.top = `${box.y}px`;
        el.style.width = `${box.w}px`;
        el.style.height = `${box.h}px`;
        box.lineEl.appendChild(el);
    }

    pintarGolpes() {
        const zona = this.el.paleta.querySelector('[data-zone="golpes"]');
        if (!zona) return;
        const instId = this.sel?.instId || this.score.instruments[0]?.id;
        const golpes = golpesDe(instId);
        const teclas = ['q', 'w', 'e', 'r', 't', 'y'];
        zona.innerHTML = golpes
            .map((g, i) => `<button class="pt-chip pt-chip-golpe" data-stroke="${g.id}" title="${g.label}${teclas[i] ? ` (${teclas[i].toUpperCase()})` : ''}">
                <span class="pt-chip-sym">${g.short}</span><small>${g.label.split(' ')[0]}</small></button>`)
            .join('');
        this.teclasGolpe = golpes.slice(0, teclas.length).reduce((acc, g, i) => ({ ...acc, [teclas[i]]: g.id }), {});
    }

    pintarInspector() {
        const s = this.sel;
        if (!s) {
            this.el.inspector.innerHTML = '<h3>Inspector</h3><p class="pt-muted">Hacé clic en una nota.</p>';
            return;
        }
        const nota = notaDe(this.score, s);
        const sec = this.score.sections[s.sectionIdx];
        const m = sec?.measures[s.measureIdx];
        if (!nota || !m) {
            this.el.inspector.innerHTML = '<h3>Inspector</h3><p class="pt-muted">Selección inválida.</p>';
            return;
        }
        const inst = instrumentoPorId(s.instId);
        const cap = ticksDeCompas(this.score.timeSignature);
        const usado = ticksDeVoz(vozDe(this.score, s) || []);
        this.el.inspector.innerHTML = `
            <h3>Inspector</h3>
            <dl class="pt-kv">
                <dt>Parte</dt><dd>${esc(sec.name)}</dd>
                <dt>Compás</dt><dd>${s.measureIdx + 1} / ${sec.measures.length}</dd>
                <dt>Instrumento</dt><dd><span class="pt-dot-color" style="background:${inst?.color || '#999'}"></span>${esc(inst?.label || s.instId)}</dd>
                <dt>Nota</dt><dd>${s.noteIdx + 1} de ${(vozDe(this.score, s) || []).length}</dd>
                <dt>Figura</dt><dd>${esc(DURACIONES.find((d) => d.code === nota.dur)?.label || nota.dur)}${'.'.repeat(nota.dots)}</dd>
                <dt>Tipo</dt><dd>${nota.rest ? 'Silencio' : esc(GOLPES[nota.stroke]?.label || nota.stroke)}</dd>
                <dt>Dinámica</dt><dd>${nota.dyn || '—'}</dd>
                <dt>Grupo</dt><dd>${nota.tuplet ? `${nota.tuplet.num}:${nota.tuplet.den}` : '—'}</dd>
            </dl>
            <div class="pt-kv-bar ${usado === cap ? 'ok' : 'warn'}">
                <span>Compás ${usado === cap ? 'completo' : 'incompleto'}</span><b>${usado}/${cap}</b>
            </div>
            <div class="pt-side-actions">
                <label class="pt-field pt-field-wide"><span>Texto del compás</span>
                    <input class="pt-input" data-f="measure-text" value="${attr(m.texto || '')}" placeholder="Ej: D.C. al Fine">
                </label>
            </div>
        `;
    }

    pintarMixer() {
        const soloActivo = this.score.instruments.some((i) => i.solo);
        this.el.mixer.innerHTML = `
            <h3>Mixer e instrumentos</h3>
            <div class="pt-mixer">
                ${this.score.instruments
                    .map((cfg) => {
                        const def = instrumentoPorId(cfg.id);
                        return `<div class="pt-mix-row ${soloActivo && !cfg.solo ? 'dim' : ''}" data-inst="${cfg.id}">
                            <span class="pt-dot-color" style="background:${def?.color || '#999'}"></span>
                            <span class="pt-mix-name">${esc(def?.label || cfg.id)}</span>
                            <input type="range" min="0" max="1" step="0.05" value="${cfg.volume}" data-f="vol" title="Volumen">
                            <button class="pt-mini ${cfg.mute ? 'on' : ''}" data-a="mute" title="Mute">M</button>
                            <button class="pt-mini ${cfg.solo ? 'on' : ''}" data-a="solo" title="Solo">S</button>
                            <button class="pt-mini ${cfg.visible === false ? '' : 'on'}" data-a="visible" title="Ver en partitura">👁</button>
                            <button class="pt-mini" data-a="preview" title="Escuchar">♪</button>
                            ${this.parteUrl ? `<a class="pt-mini" href="${attr(this.parteUrl.replace('__INST__', cfg.id))}" target="_blank" title="Parte separada">⎙</a>` : ''}
                        </div>`;
                    })
                    .join('')}
            </div>
            <details class="pt-details">
                <summary>Agregar / quitar instrumentos</summary>
                <div class="pt-checks">
                    ${INSTRUMENTOS.map(
                        (i) => `<label><input type="checkbox" data-f="inst-on" value="${i.id}" ${
                            this.score.instruments.some((c) => c.id === i.id) ? 'checked' : ''
                        }> ${esc(i.label)}</label>`
                    ).join('')}
                </div>
            </details>
        `;
    }

    pintarEstructura() {
        this.el.estructura.innerHTML = `
            <h3>Partes</h3>
            <div class="pt-sections">
                ${this.score.sections
                    .map(
                        (sec, si) => `<div class="pt-sec-row ${this.sel?.sectionIdx === si ? 'active' : ''}" data-section="${si}">
                        <input class="pt-input pt-input-sm" data-f="sec-name" value="${attr(sec.name)}">
                        <label class="pt-rep">×<input class="pt-input pt-input-num pt-input-sm" type="number" min="1" max="16" data-f="sec-rep" value="${sec.repeatX}"></label>
                        <button class="pt-mini" data-a="sec-play" title="Escuchar parte">▶</button>
                        <button class="pt-mini" data-a="sec-del" title="Borrar parte">✕</button>
                    </div>`
                    )
                    .join('')}
            </div>
            <button class="pt-chip pt-chip-wide" data-a="sec-add">+ Parte</button>
        `;
    }

    pintarStatus() {
        const r = resumen(this.score);
        const s = this.sel;
        this.el.status.innerHTML = `
            <span>${r.partes} partes · ${r.compases} compases · ${r.golpes} golpes · ${r.instrumentos} instrumentos</span>
            <span class="pt-status-sel">${
                s ? `Parte ${s.sectionIdx + 1} · Compás ${s.measureIdx + 1} · ${esc(instrumentoPorId(s.instId)?.short || s.instId)} · nota ${s.noteIdx + 1}` : 'Sin selección'
            }</span>
            <span class="pt-status-dirty">${this.readonly ? 'Solo lectura' : this.dirty ? 'Cambios sin guardar' : 'Guardado'}</span>
        `;
    }

    marcarBotonesDuracion() {
        this.root.querySelectorAll('.pt-dur').forEach((b) => b.classList.toggle('on', b.dataset.dur === this.durActiva));
        this.root.querySelector('.pt-dot')?.classList.toggle('on', this.dotsActivos > 0);
        this.root.querySelector('.pt-rest')?.classList.toggle('on', this.modoSilencio);
    }

    /** ------------------------------------------------------------- eventos */

    onCanvasClick(e) {
        const svg = e.target.closest('svg');
        if (!svg) return;
        const lineEl = svg.parentElement;
        const rect = svg.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.zoom;
        const y = (e.clientY - rect.top) / this.zoom;
        const candidatos = this.hits.filter((h) => h.lineEl === lineEl);
        if (!candidatos.length) return;
        let mejor = null;
        let mejorD = Infinity;
        candidatos.forEach((h) => {
            const cx = h.x + h.w / 2;
            const cy = h.y + h.h / 2;
            const d = (cx - x) ** 2 + ((cy - y) * 1.6) ** 2;
            if (d < mejorD) {
                mejorD = d;
                mejor = h;
            }
        });
        if (!mejor) return;
        this.seleccionar({ sectionIdx: mejor.sectionIdx, measureIdx: mejor.measureIdx, instId: mejor.instId, noteIdx: mejor.noteIdx });
        if (!mejor.rest) {
            const nota = notaDe(this.score, this.sel);
            if (nota) this.audio.golpe(mejor.instId, nota.stroke).catch(() => {});
        }
    }

    onClick(e) {
        const btn = e.target.closest('[data-a],[data-stroke],[data-dyn],[data-dur],[data-marca]');
        if (!btn) return;
        const a = btn.dataset.a;
        const secRow = btn.closest('[data-section]');
        const mixRow = btn.closest('[data-inst]');

        if (btn.dataset.dur) return this.aplicarDuracion(btn.dataset.dur);
        if (btn.dataset.stroke) return this.editar(() => ops.setGolpe(this.score, this.sel, btn.dataset.stroke), btn.dataset.stroke);
        if (btn.dataset.dyn) return this.editar(() => ops.setDinamica(this.score, this.sel, btn.dataset.dyn));
        if (btn.dataset.marca) {
            const marca = MARCAS_TEXTO.find((m) => m.id === btn.dataset.marca);
            return this.editarCompas((m) => { m.texto = marca ? marca.texto : null; });
        }

        switch (a) {
            case 'play': return this.play();
            case 'stop': return this.audio.stop();
            case 'loop': btn.classList.toggle('on'); this.loop = btn.classList.contains('on'); return;
            case 'metro': btn.classList.toggle('on'); this.audio.metronomo = btn.classList.contains('on'); return;
            case 'dot': this.dotsActivos = this.dotsActivos ? 0 : 1; this.marcarBotonesDuracion();
                return this.editar(() => ops.toggleDot(this.score, this.sel, 1));
            case 'rest': this.modoSilencio = !this.modoSilencio; this.marcarBotonesDuracion();
                return this.editar(() => ops.toggleSilencio(this.score, this.sel));
            case 'undo': return this.undo();
            case 'redo': return this.redo();
            case 'zoom-in': return this.setZoom(this.zoom + 0.15);
            case 'zoom-out': return this.setZoom(this.zoom - 0.15);
            case 'save': return this.guardar();
            case 'export-menu': return btn.closest('.pt-dropdown').classList.toggle('open');
            case 'export-pdf': return exportarPDF(this.el.canvas, this.score).catch((err) => this.aviso(`PDF: ${err.message}`));
            case 'export-png': return exportarPNG(this.el.canvas, this.score).catch((err) => this.aviso(`PNG: ${err.message}`));
            case 'export-xml': return exportarMusicXML(this.score);
            case 'export-midi': return exportarMIDI(this.score);
            case 'tuplet-3': return this.editar(() => ops.tuplet(this.score, this.sel, 3, 2));
            case 'tuplet-6': return this.editar(() => ops.tuplet(this.score, this.sel, 6, 4));
            case 'rep-begin': return this.editarCompas((m) => { m.repeatBegin = !m.repeatBegin; });
            case 'rep-end': return this.editarCompas((m) => { m.repeatEnd = !m.repeatEnd; });
            case 'ending-1': return this.editarCompas((m) => { m.ending = m.ending === 1 ? null : 1; });
            case 'ending-2': return this.editarCompas((m) => { m.ending = m.ending === 2 ? null : 2; });
            case 'ending-off': return this.editarCompas((m) => { m.ending = null; });
            case 'marca-off': return this.editarCompas((m) => { m.texto = null; });
            case 'measure-add':
                if (!this.sel) return;
                return this.editar(() => ops.agregarCompas(this.score, this.sel.sectionIdx, this.sel.measureIdx));
            case 'measure-del':
                if (!this.sel) return;
                return this.editar(() => {
                    const ok = ops.borrarCompas(this.score, this.sel.sectionIdx, this.sel.measureIdx);
                    if (ok) this.sel.measureIdx = Math.max(0, this.sel.measureIdx - 1);
                    return ok;
                });
            case 'measure-clear': return this.editar(() => ops.limpiarCompas(this.score, this.sel));
            case 'measure-copy': return this.copiarVozDialogo();
            case 'sec-add': return this.editar(() => ops.agregarSeccion(this.score, `Parte ${this.score.sections.length + 1}`));
            case 'sec-del':
                if (!secRow) return;
                return this.editar(() => {
                    const si = Number(secRow.dataset.section);
                    const ok = ops.borrarSeccion(this.score, si);
                    if (ok) this.sel = null;
                    return ok;
                });
            case 'sec-play':
                if (!secRow) return;
                return this.play({ soloSeccion: Number(secRow.dataset.section) });
            case 'mute':
                return this.mixer(mixRow, (cfg) => { cfg.mute = !cfg.mute; });
            case 'solo':
                return this.mixer(mixRow, (cfg) => { cfg.solo = !cfg.solo; });
            case 'visible':
                return this.mixer(mixRow, (cfg) => { cfg.visible = cfg.visible === false; }, true);
            case 'preview': {
                const id = mixRow?.dataset.inst;
                if (id) this.audio.golpe(id, golpesDe(id)[0]?.id || 'nota').catch(() => {});
                return;
            }
            default:
                return;
        }
    }

    onChange(e) {
        const f = e.target.dataset.f;
        if (!f) return;
        const secRow = e.target.closest('[data-section]');
        const mixRow = e.target.closest('[data-inst]');

        if (f === 'ts') {
            const [num, den] = e.target.value.split('/').map(Number);
            return this.editar(() => ops.setCompasMetrico(this.score, num, den));
        }
        if (f === 'inst-on') {
            const ids = Array.from(this.el.mixer.querySelectorAll('[data-f="inst-on"]:checked')).map((c) => c.value);
            if (!ids.length) return this.aviso('Tiene que quedar al menos un instrumento.');
            return this.editar(() => {
                ops.setInstrumentos(this.score, INSTRUMENTOS.filter((i) => ids.includes(i.id)).map((i) => i.id));
                if (this.sel && !ids.includes(this.sel.instId)) this.sel = null;
                return true;
            });
        }
        if (f === 'sec-rep' && secRow) {
            const si = Number(secRow.dataset.section);
            return this.editar(() => {
                this.score.sections[si].repeatX = Math.min(16, Math.max(1, Number(e.target.value) || 1));
                return true;
            });
        }
        if (f === 'vol' && mixRow) {
            return this.mixer(mixRow, (cfg) => { cfg.volume = Number(e.target.value); });
        }
    }

    onInput(e) {
        const f = e.target.dataset.f;
        if (!f) return;
        if (f === 'title') { this.score.title = e.target.value; return this.tocado(); }
        if (f === 'autor') { this.score.autor = e.target.value; return this.tocado(); }
        if (f === 'tempo') {
            this.score.tempo = Math.min(260, Math.max(30, Number(e.target.value) || 100));
            return this.tocado();
        }
        if (f === 'sec-name') {
            const si = Number(e.target.closest('[data-section]').dataset.section);
            this.score.sections[si].name = e.target.value;
            return this.tocado();
        }
        if (f === 'measure-text') {
            if (!this.sel) return;
            const m = this.score.sections[this.sel.sectionIdx].measures[this.sel.measureIdx];
            m.texto = e.target.value || null;
            this.tocado();
            return this.renderDiferido();
        }
    }

    bindTeclado() {
        document.addEventListener('keydown', (e) => {
            if (e.target.matches('input, textarea, select')) return;
            const mod = e.ctrlKey || e.metaKey;

            if (e.code === 'Space') { e.preventDefault(); return this.audio.playing ? this.audio.stop() : this.play(); }
            if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); return this.undo(); }
            if (mod && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) { e.preventDefault(); return this.redo(); }
            if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); return this.guardar(); }
            if (mod && e.key === '3') { e.preventDefault(); return this.editar(() => ops.tuplet(this.score, this.sel, 3, 2)); }
            if (mod && e.key === '6') { e.preventDefault(); return this.editar(() => ops.tuplet(this.score, this.sel, 6, 4)); }
            if (mod) return;

            const dur = DURACIONES.find((d) => d.tecla === e.key);
            if (dur) { e.preventDefault(); return this.aplicarDuracion(dur.code); }
            if (e.key === '.') { e.preventDefault(); return this.editar(() => ops.toggleDot(this.score, this.sel, 1)); }
            if (e.key.toLowerCase() === 'r') { e.preventDefault(); return this.editar(() => ops.toggleSilencio(this.score, this.sel)); }
            if (e.key === 'Enter') { e.preventDefault(); return this.insertar(); }
            if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); return this.editar(() => ops.borrar(this.score, this.sel)); }
            if (e.key === 'ArrowRight') { e.preventDefault(); return this.mover(1); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); return this.mover(-1); }
            if (e.key === 'ArrowDown') { e.preventDefault(); return this.moverInstrumento(1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); return this.moverInstrumento(-1); }

            const stroke = this.teclasGolpe?.[e.key.toLowerCase()];
            if (stroke) { e.preventDefault(); return this.editar(() => ops.setGolpe(this.score, this.sel, stroke), stroke); }
        });
    }

    /** ------------------------------------------------------------- acciones */

    aplicarDuracion(dur) {
        this.durActiva = dur;
        this.marcarBotonesDuracion();
        if (!this.sel) return;
        this.editar(() => ops.setDuracion(this.score, this.sel, dur));
    }

    insertar() {
        if (!this.sel) return;
        this.editar(() => {
            const ok = ops.insertarDespues(this.score, this.sel, { dur: this.durActiva, rest: this.modoSilencio });
            if (ok) this.sel.noteIdx += 1;
            return ok;
        });
    }

    mover(delta) {
        if (!this.sel) return;
        const voz = vozDe(this.score, this.sel) || [];
        const sec = this.score.sections[this.sel.sectionIdx];
        let { noteIdx, measureIdx, sectionIdx } = this.sel;
        noteIdx += delta;
        if (noteIdx < 0) {
            if (measureIdx > 0) measureIdx -= 1;
            else if (sectionIdx > 0) { sectionIdx -= 1; measureIdx = this.score.sections[sectionIdx].measures.length - 1; }
            else return;
            noteIdx = (this.score.sections[sectionIdx].measures[measureIdx].voces[this.sel.instId] || []).length - 1;
        } else if (noteIdx >= voz.length) {
            if (measureIdx < sec.measures.length - 1) measureIdx += 1;
            else if (sectionIdx < this.score.sections.length - 1) { sectionIdx += 1; measureIdx = 0; }
            else return;
            noteIdx = 0;
        }
        this.seleccionar({ ...this.sel, sectionIdx, measureIdx, noteIdx: Math.max(0, noteIdx) });
    }

    moverInstrumento(delta) {
        if (!this.sel) return;
        const visibles = this.score.instruments.filter((i) => i.visible !== false);
        const i = visibles.findIndex((x) => x.id === this.sel.instId);
        const next = visibles[i + delta];
        if (!next) return;
        const voz = this.score.sections[this.sel.sectionIdx].measures[this.sel.measureIdx].voces[next.id] || [];
        this.seleccionar({ ...this.sel, instId: next.id, noteIdx: Math.min(this.sel.noteIdx, Math.max(0, voz.length - 1)) });
    }

    copiarVozDialogo() {
        if (!this.sel) return;
        const sec = this.score.sections[this.sel.sectionIdx];
        const respuesta = window.prompt(
            `Copiar la voz del compás ${this.sel.measureIdx + 1} a qué compases de "${sec.name}"? (ej: 2,3,4 o 2-6)`,
            `${Math.min(sec.measures.length, this.sel.measureIdx + 2)}-${sec.measures.length}`
        );
        if (!respuesta) return;
        const destinos = parseRangos(respuesta, sec.measures.length).filter((i) => i !== this.sel.measureIdx);
        if (!destinos.length) return;
        this.editar(() => ops.copiarVoz(this.score, this.sel, destinos));
    }

    mixer(row, fn, reRender = false) {
        if (!row) return;
        const cfg = this.score.instruments.find((i) => i.id === row.dataset.inst);
        if (!cfg) return;
        fn(cfg);
        this.audio.aplicarMixer(this.score);
        this.tocado();
        if (reRender) {
            if (this.sel && this.score.instruments.find((i) => i.id === this.sel.instId)?.visible === false) this.sel = null;
            this.render();
        } else {
            this.pintarMixer();
        }
    }

    /** Ejecuta una operación con undo + re-render. */
    editar(fn, previewStroke = null) {
        if (this.readonly) return;
        if (!this.sel && fn.length === 0) { /* algunas ops no necesitan selección */ }
        const antes = clonar(this.score);
        let ok = false;
        try {
            ok = fn() !== false;
        } catch (err) {
            console.error('Partitura: operación fallida', err);
            return this.aviso('No se pudo aplicar el cambio.');
        }
        if (!ok) return;
        this.undoStack.push(antes);
        if (this.undoStack.length > MAX_UNDO) this.undoStack.shift();
        this.redoStack = [];
        this.tocado();
        this.render();
        if (previewStroke && this.sel) this.audio.golpe(this.sel.instId, previewStroke).catch(() => {});
    }

    editarCompas(fn) {
        if (!this.sel) return this.aviso('Elegí un compás primero.');
        this.editar(() => {
            const m = this.score.sections[this.sel.sectionIdx]?.measures[this.sel.measureIdx];
            if (!m) return false;
            fn(m);
            return true;
        });
    }

    undo() {
        const prev = this.undoStack.pop();
        if (!prev) return;
        this.redoStack.push(clonar(this.score));
        this.score = prev;
        this.sel = null;
        this.tocado();
        this.render();
        this.seleccionInicial();
    }

    redo() {
        const next = this.redoStack.pop();
        if (!next) return;
        this.undoStack.push(clonar(this.score));
        this.score = next;
        this.sel = null;
        this.tocado();
        this.render();
        this.seleccionInicial();
    }

    setZoom(z) {
        this.zoom = Math.min(2, Math.max(0.5, Math.round(z * 100) / 100));
        this.render();
    }

    async play(opts = {}) {
        this.root.querySelector('.pt-btn-play')?.classList.add('on');
        try {
            await this.audio.play(this.score, {
                loop: !!this.loop,
                soloSeccion: opts.soloSeccion ?? null,
                desde: opts.soloSeccion === undefined && this.sel ? { sectionIdx: this.sel.sectionIdx, measureIdx: 0 } : null,
            });
        } catch (err) {
            this.aviso(`Audio: ${err.message}`);
            this.finTransporte();
        }
    }

    finTransporte() {
        this.root.querySelector('.pt-btn-play')?.classList.remove('on');
        this.root.querySelectorAll('.pt-play-box').forEach((n) => n.remove());
    }

    tocado() {
        this.dirty = true;
        this.pintarStatus();
        const hidden = document.querySelector('[data-partitura-json]');
        if (hidden) hidden.value = JSON.stringify(this.score);
    }

    async guardar() {
        if (this.readonly || !this.saveUrl || this.guardando) return;
        this.guardando = true;
        this.aviso('Guardando…');
        try {
            const res = await fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ score: this.score }),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json().catch(() => ({}));
            if (data.score) this.score = normalizarPartitura(data.score);
            this.dirty = false;
            this.render();
            this.aviso('Partitura guardada.');
        } catch (err) {
            this.aviso(`No se pudo guardar: ${err.message}`);
        } finally {
            this.guardando = false;
        }
    }

    renderDiferido() {
        clearTimeout(this._rt);
        this._rt = setTimeout(() => this.render(), 400);
    }

    aviso(msg) {
        let t = this.root.querySelector('.pt-toast');
        if (!t) {
            t = document.createElement('div');
            t.className = 'pt-toast';
            this.root.appendChild(t);
        }
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(this._toast);
        this._toast = setTimeout(() => t.classList.remove('show'), 2600);
    }
}

/** ------------------------------------------------------------- helpers */

function parseRangos(txt, max) {
    const out = new Set();
    String(txt)
        .split(',')
        .forEach((parte) => {
            const m = parte.trim().match(/^(\d+)\s*-\s*(\d+)$/);
            if (m) {
                for (let i = Number(m[1]); i <= Number(m[2]); i++) if (i >= 1 && i <= max) out.add(i - 1);
                return;
            }
            const n = parseInt(parte, 10);
            if (n >= 1 && n <= max) out.add(n - 1);
        });
    return Array.from(out);
}

function figuraSvg(code) {
    const glyph = { w: '𝅝', h: '𝅗𝅥', q: '𝅘𝅥', 8: '𝅘𝅥𝅮', 16: '𝅘𝅥𝅯', 32: '𝅘𝅥𝅰' }[code] || '♪';
    return `<span class="pt-fig">${glyph}</span>`;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function attr(s) {
    return esc(s);
}

function debounce(fn, ms) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

export default EditorPartitura;
