/**
 * Editor de ritmos de La Chilinga: partitura + grilla + audio sobre un único modelo.
 *
 * Layout: toolbar de transporte + paletas laterales + lienzo central (VexFlow) y/o grilla
 * + mixer/inspector. Trabaja siempre sobre el modelo v5 (resources/js/partitura/model.js);
 * la grilla, la partitura y el audio leen el mismo score (docs/EDITOR_RITMOS.md).
 */
import {
    DURACIONES, ops, normalizarPartitura, clonar, resumen, notaDe, vozDe,
    ticksDeCompas, ticksDeVoz, crearPartitura, HERRAMIENTAS_FIGURA, herramientaPorId,
    expandirTimeline,
} from './model.js';
import { INSTRUMENTOS, instrumentoPorId, golpesDe, GOLPES, DINAMICAS, MARCAS_TEXTO, DIGITACIONES, SENAS } from './instruments.js';
import { renderScore } from './renderer.js';
import { MotorAudio } from './audio.js';
import { exportarPNG, exportarPDF, exportarMusicXML, exportarMIDI, descargarBlob } from './exporters.js';
import { importarMusicXML, importarScoreJson, tipoArchivoImport } from './importers.js';
import { TourPartitura } from './tour.js';
import { RegistroAtajos, teclaLegible } from './atajos.js';
import { VistaGrilla } from './grilla-vista.js';
import {
    RESOLUCIONES, ticksDeResolucion, pasosPorCompas, ponerGolpe, quitarGolpe, celdasDeVoz, tickDeNota, notaDePaso, pasoDeTick,
} from './grilla.js';
import { bpmDeToques, registrarToque, cuantizar } from './practica.js';
import { scoreDesdeMIDI, escucharMIDI, instrumentoDeMidi } from './midi.js';
import { mostrarAyuda, mostrarVersiones, menuGolpe, feedbackTecla } from './paneles.js';

const TEMPO_MIN = 40;
const TEMPO_MAX = 180;
const TECLAS_INSTRUMENTO = ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K'];
const TECLAS_GOLPE = ['Q', 'W', 'E', 'T', 'Y', 'U'];
const AUTOGUARDADO_MS = 3000;

const MAX_UNDO = 60;

export class EditorPartitura {
    /**
     * @param {HTMLElement} root
     * @param {{ score?: object, saveUrl?: string|null, backUrl?: string|null, parteUrl?: string|null, readonly?: boolean, editorNombre?: string, uploadRefUrl?: string|null, refUrl?: string|null, refEsPdf?: boolean, refNombre?: string }} [opts]
     */
    constructor(root, opts = {}) {
        this.root = root;
        this.saveUrl = opts.saveUrl || null;
        this.backUrl = opts.backUrl || null;
        this.parteUrl = opts.parteUrl || null;
        this.readonly = !!opts.readonly;
        this.editorNombre = String(opts.editorNombre || '').trim();
        this.uploadRefUrl = opts.uploadRefUrl || null;
        this.refUrl = opts.refUrl || null;
        this.refTipo = opts.refTipo || (opts.refEsPdf ? 'pdf' : 'imagen');
        this.refNombre = opts.refNombre || '';
        this.slug = opts.slug || 'toque';
        this.borradorUrl = opts.borradorUrl || null;
        this.versionesUrl = opts.versionesUrl || null;
        this.borradorPendiente = opts.borrador || null;

        this.score = normalizarPartitura(opts.score || crearPartitura());
        // Ritmo: vistas, foco, grilla, modos y práctica.
        // En pantallas chicas la grilla es la vista principal (tocar una celda = golpe).
        this.vista = leerPref('pt-vista', typeof window !== 'undefined' && window.innerWidth < 700 ? 'grilla' : 'mixta');
        this.resolucion = leerPref('pt-resolucion', '16');
        this.modoPantalla = 'edicion';
        this.foco = this.vista === 'grilla' ? 'grilla' : 'partitura';
        this.gsel = null;            // {sectionIdx, measureIdx, instId, paso}
        this.rango = null;           // {sectionIdx, desde, hasta} compases seleccionados
        this.clip = null;            // portapapeles musical
        this.entrada = true;         // avance automático al escribir
        this.tocando = false;        // modo tocar (teclado = instrumento)
        this.grabando = false;
        this.grabados = [];
        this.toques = [];
        this.loopModo = 'todo';
        this.countInCompases = 1;
        this.seguirReproduccion = true;
        this.estadoGuardado = 'guardado'; // guardado | sucio | guardando | borrador | error
        this.borradorAt = null;
        this.sel = null;              // {sectionIdx, measureIdx, instId, noteIdx}
        this.durActiva = 'q';
        this.herramientaId = 'q';
        this.dotsActivos = 0;
        this.modoSilencio = false;
        this.zoom = 1;
        this.undoStack = [];
        this.redoStack = [];
        this.hits = [];
        this.measureBoxes = [];
        this.dirty = false;
        this.guardando = false;
        this.loop = false;
        this.countIn = true;
        this.audio = new MotorAudio();
        this.audio.onClock = (pos) => this.marcarPlayhead(pos);
        this.audio.onStop = () => this.finTransporte();
        this.audio.onLoad = (msg) => this.aviso(msg);
        this.audio.onReady = (st) => {
            if (!st.listos) {
                this.aviso('Los samples no cargaron. Reproduciendo un golpe de respaldo.');
                return;
            }
            const extra = st.faltan ? ` · ${st.faltan} samples faltan` : '';
            this.aviso(`Listo para reproducir${extra}`);
        };

        this.atajos = new RegistroAtajos();
        this.registrarAtajos();
        try { this.atajos.importar(JSON.parse(localStorage.getItem('pt-atajos') || '{}')); } catch { /* sin preferencias */ }

        this.construir();
        this.bindTeclado();
        this.root.addEventListener('pointerdown', () => {
            this.audio.asegurarContexto().then(() => this.audio.precargarSamples(this.score)).catch(() => {});
        }, { once: true });
        this.tour = new TourPartitura(this.root);
        this.render();
        this.seleccionInicial();
        this.ofrecerBorrador();
        this._reloj = setInterval(() => { if (this.estadoGuardado === 'borrador') this.pintarStatus(); }, 15000);
        this.tour.maybeStart();
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
                <div class="pt-tb-group pt-transporte" data-tour="transporte">
                    <button class="pt-btn pt-btn-play" data-a="play" title="Reproducir / pausa (Espacio)" aria-label="Reproducir o pausar">▶</button>
                    <button class="pt-btn" data-a="stop" title="Detener (Shift+Espacio)" aria-label="Detener">■</button>
                    <label class="pt-field" title="Repetir (L)"><span>Loop</span>
                        <select class="pt-input pt-select" data-f="loop" aria-label="Loop">
                            <option value="off">No</option>
                            <option value="todo">Todo</option>
                            <option value="compas">Compás actual</option>
                            <option value="seleccion">Selección</option>
                            <option value="2">2 compases</option>
                            <option value="4">4 compases</option>
                            <option value="8">8 compases</option>
                        </select>
                    </label>
                    <label class="pt-field" title="Cuenta antes de entrar"><span>Cuenta</span>
                        <select class="pt-input pt-select" data-f="countin" aria-label="Cuenta previa">
                            <option value="0">No</option><option value="1" selected>1 compás</option><option value="2">2 compases</option>
                        </select>
                    </label>
                    <div class="pt-dropdown pt-metro">
                        <button class="pt-btn pt-toggle" data-a="metro" title="Metrónomo (M)" aria-pressed="false">Metrónomo</button>
                        <button class="pt-btn pt-btn-ghost" data-a="metro-menu" title="Opciones del metrónomo" aria-label="Opciones del metrónomo">▾</button>
                        <div class="pt-dropdown-menu pt-metro-menu">
                            <label>Subdivisión
                                <select class="pt-input pt-select" data-f="metro-sub"><option value="1">Negras</option><option value="2">Corcheas</option><option value="4">Semicorcheas</option></select>
                            </label>
                            <label>Volumen <input type="range" min="0" max="1" step="0.05" value="0.5" data-f="metro-vol"></label>
                            <label class="pt-check"><input type="checkbox" data-f="metro-acento" checked> Acento en el 1</label>
                        </div>
                    </div>
                    <label class="pt-field pt-field-tempo" title="Tempo (+ / −)">
                        <span>♩ =</span>
                        <button class="pt-mini" data-a="tempo-menos" aria-label="Bajar tempo">−</button>
                        <input class="pt-tempo-slider" type="range" min="${TEMPO_MIN}" max="${TEMPO_MAX}" data-f="tempo-slider" value="${this.score.tempo}" aria-label="Tempo">
                        <input class="pt-input pt-input-num" type="number" min="${TEMPO_MIN}" max="${TEMPO_MAX}" data-f="tempo" value="${this.score.tempo}" aria-label="BPM">
                        <button class="pt-mini" data-a="tempo-mas" aria-label="Subir tempo">+</button>
                        <button class="pt-btn" data-a="tap" title="Tocá varias veces al pulso">TAP</button>
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
                <div class="pt-tb-group pt-vistas" role="group" aria-label="Vista">
                    <div class="pt-seg">
                        <button class="pt-btn" data-a="vista" data-v="grilla" title="Grilla (Alt+2)">Grilla</button>
                        <button class="pt-btn" data-a="vista" data-v="partitura" title="Partitura (Alt+1)">Partitura</button>
                        <button class="pt-btn" data-a="vista" data-v="mixta" title="Grilla y partitura (Alt+3)">Mixta</button>
                    </div>
                    <label class="pt-field" title="Resolución de la grilla"><span>Grilla</span>
                        <select class="pt-input pt-select" data-f="resolucion">
                            ${RESOLUCIONES.map((r) => `<option value="${r.id}" ${r.id === this.resolucion ? 'selected' : ''}>${r.label}</option>`).join('')}
                        </select>
                    </label>
                    <label class="pt-field"><span>Modo</span>
                        <select class="pt-input pt-select" data-f="modo">
                            <option value="edicion">Edición</option>
                            <option value="ensayo">Ensayo</option>
                            <option value="clase">Clase</option>
                            <option value="presentacion">Presentación</option>
                            <option value="aprendizaje">Aprendizaje</option>
                        </select>
                    </label>
                    ${this.readonly ? '' : `
                    <button class="pt-btn pt-toggle" data-a="tocar" title="Modo tocar: el teclado es un instrumento (P)" aria-pressed="false">Tocar</button>
                    <button class="pt-btn pt-toggle pt-btn-rec" data-a="grabar" title="Grabar lo que tocás (se ajusta a la grilla)" aria-pressed="false">● Grabar</button>
                    <button class="pt-btn pt-toggle" data-a="midi-in" title="Tocar con un teclado o pad MIDI" aria-pressed="false">MIDI</button>`}
                </div>
                <div class="pt-tb-group">
                    <button class="pt-btn" data-a="undo" title="Deshacer (Ctrl+Z)">⟲</button>
                    <button class="pt-btn" data-a="redo" title="Rehacer (Ctrl+Y)">⟳</button>
                    <button class="pt-btn" data-a="zoom-out" title="Zoom -">−</button>
                    <span class="pt-zoom-label">100%</span>
                    <button class="pt-btn" data-a="zoom-in" title="Zoom +">+</button>
                </div>
                <div class="pt-tb-group pt-tb-right" data-tour="archivo">
                    <button class="pt-btn" data-a="tour" title="Guía paso a paso">Guía</button>
                    <button class="pt-btn" data-a="atajos" title="Atajos de teclado (?)">⌨ Atajos</button>
                    ${this.versionesUrl ? '<button class="pt-btn" data-a="versiones" title="Versiones publicadas">Versiones</button>' : ''}
                    <button class="pt-btn" data-a="pantalla-completa" title="Pantalla completa" aria-label="Pantalla completa">⛶</button>
                    ${this.readonly ? '' : `
                    <div class="pt-dropdown">
                        <button class="pt-btn" data-a="import-menu">Importar ▾</button>
                        <div class="pt-dropdown-menu">
                            <button data-a="import-ref">PDF / imagen (original)</button>
                            <button data-a="import-xml">MusicXML (MuseScore)</button>
                            <button data-a="import-json">JSON del editor</button>
                            <button data-a="import-midi">MIDI (.mid)</button>
                        </div>
                    </div>`}
                    <button class="pt-btn pt-toggle ${this.refUrl ? 'on' : ''}" data-a="toggle-ref" title="Ver original" ${this.refUrl ? '' : 'hidden'}>Original</button>
                    <div class="pt-dropdown">
                        <button class="pt-btn" data-a="export-menu">Exportar ▾</button>
                        <div class="pt-dropdown-menu">
                            <button data-a="export-pdf">PDF</button>
                            <button data-a="export-png">PNG</button>
                            <button data-a="export-xml">MusicXML</button>
                            <button data-a="export-midi">MIDI</button>
                            <button data-a="export-wav">WAV (audio)</button>
                        </div>
                    </div>
                    ${this.readonly ? '' : '<button class="pt-btn pt-btn-primary" data-a="save" title="Publicar una versión (Ctrl+S)">Publicar</button>'}
                </div>
            </header>
            <div class="pt-notation" data-tour="figuras">
                <div class="pt-not-group" data-tour="compases">
                    <span class="pt-not-label">Compases</span>
                    <button class="pt-btn" data-a="measure-before" title="Insertar un compás antes">+ Antes</button>
                    <button class="pt-btn" data-a="measure-add" title="Agregar un compás después del seleccionado">+ Después</button>
                    <button class="pt-btn" data-a="measure-dup" title="Duplicar el compás o la selección (R)">Duplicar</button>
                    <label class="pt-field" title="Repetir la selección varias veces"><span>Repetir</span>
                        <select class="pt-input pt-select" data-f="repetir"><option value="">×…</option><option value="2">×2</option><option value="4">×4</option><option value="8">×8</option></select>
                    </label>
                    <button class="pt-btn" data-a="measure-del" title="Borrar el compás seleccionado">− Compás</button>
                    <button class="pt-btn" data-a="measure-clear" title="Silencios en esta línea de este compás">Limpiar voz</button>
                    <button class="pt-btn" data-a="measure-copy" title="Copiar esta línea a otros instrumentos">Copiar voz</button>
                    ${this.readonly ? '' : '<button class="pt-btn pt-btn-warn" data-a="score-clear" title="Borrar todo y dejar un compás vacío">Partitura en blanco</button>'}
                </div>
                <div class="pt-not-group">
                    <span class="pt-not-label">Figuras</span>
                    ${botonesFigura('figuras')}
                </div>
                <div class="pt-not-group">
                    <span class="pt-not-label">Grupos y tresillos</span>
                    ${botonesFigura('grupos')}
                </div>
                <div class="pt-not-group">
                    <span class="pt-not-label">Silencios</span>
                    ${botonesFigura('silencios')}
                </div>
            </div>
            <input type="file" hidden data-import="ref" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf">
            <input type="file" hidden data-import="xml" accept=".musicxml,.xml,application/xml,text/xml">
            <input type="file" hidden data-import="json" accept=".json,application/json">
            <input type="file" hidden data-import="midi" accept=".mid,.midi,audio/midi">

            <div class="pt-body">
                <aside class="pt-palette" data-zone="paleta"></aside>
                <aside class="pt-ref" data-zone="ref" hidden>
                    <div class="pt-ref-head">
                        <strong>Original</strong>
                        <span class="pt-ref-name" data-ref-name></span>
                        <label class="pt-ref-op">Opacidad
                            <input type="range" min="20" max="100" value="100" data-f="ref-op">
                        </label>
                        <button class="pt-btn" data-a="toggle-ref" title="Ocultar">✕</button>
                    </div>
                    <div class="pt-ref-body" data-ref-body></div>
                </aside>
                <main class="pt-canvas-wrap" tabindex="0" data-tour="lienzo">
                    <div class="pt-sena-overlay" data-zone="sena" aria-live="polite" hidden></div>
                    <div class="pt-aprender" data-zone="aprender" hidden>
                        <p><strong>Escuchá y tocá.</strong> La partitura está oculta: reproducí, tocá con el grupo y después comparala.</p>
                        <button class="pt-btn" data-a="aprender-mostrar">Mostrar la partitura</button>
                    </div>
                    <section class="pt-grid" data-zone="grilla" aria-label="Grilla rítmica"></section>
                    <div class="pt-page" data-zone="page">
                        <div class="pt-canvas" data-zone="canvas"></div>
                    </div>
                </main>
                <aside class="pt-side">
                    <div data-tour="inspector">
                    <div class="pt-side-block" data-zone="inspector"></div>
                    <div class="pt-side-block" data-zone="mixer"></div>
                    </div>
                    <div class="pt-side-block" data-zone="estructura" data-tour="partes"></div>
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
            ref: this.root.querySelector('[data-zone="ref"]'),
            refBody: this.root.querySelector('[data-ref-body]'),
            refName: this.root.querySelector('[data-ref-name]'),
            inputRef: this.root.querySelector('[data-import="ref"]'),
            inputXml: this.root.querySelector('[data-import="xml"]'),
            inputJson: this.root.querySelector('[data-import="json"]'),
            inputMidi: this.root.querySelector('[data-import="midi"]'),
            grilla: this.root.querySelector('[data-zone="grilla"]'),
            sena: this.root.querySelector('[data-zone="sena"]'),
            aprender: this.root.querySelector('[data-zone="aprender"]'),
        };
        this.grilla = new VistaGrilla(this, this.el.grilla);

        this.pintarPaleta();
        this.root.addEventListener('click', (e) => this.onClick(e));
        this.root.addEventListener('change', (e) => this.onChange(e));
        this.root.addEventListener('input', (e) => this.onInput(e));
        this.el.canvas.addEventListener('click', (e) => this.onCanvasClick(e));
        this.el.inputRef?.addEventListener('change', (e) => this.onImportFile(e.target.files?.[0]));
        this.el.inputXml?.addEventListener('change', (e) => this.onImportFile(e.target.files?.[0]));
        this.el.inputJson?.addEventListener('change', (e) => this.onImportFile(e.target.files?.[0]));
        this.el.inputMidi?.addEventListener('change', (e) => this.importarMidi(e.target.files?.[0]));
        this.el.canvas.addEventListener('pointerdown', () => this.setFoco('partitura'));
        document.addEventListener('fullscreenchange', () => this.render());
        this.bindDropImport();
        if (this.refUrl) this.mostrarReferencia(this.refUrl, this.refTipo, this.refNombre, true);
        window.addEventListener('resize', debounce(() => this.render(), 200));
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty && !this.readonly && this.estadoGuardado !== 'borrador') {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    pintarPaleta() {
        this.el.paleta.innerHTML = `
            <div class="pt-pal-block" data-tour="golpes">
                <h3>Golpes</h3>
                <div class="pt-pal-grid" data-zone="golpes"></div>
            </div>
            <div data-tour="expresion">
            <div class="pt-pal-block">
                <h3>Dinámicas</h3>
                <div class="pt-pal-grid">
                    ${DINAMICAS.map((d) => `<button class="pt-chip" data-dyn="${d}" title="Dinámica ${d}"><i>${d}</i></button>`).join('')}
                </div>
            </div>
            <div class="pt-pal-block">
                <h3>Manos</h3>
                <div class="pt-pal-grid">
                    ${DIGITACIONES.map((d) => `<button class="pt-chip" data-digitacion="${d.id}" title="${d.label} (${d.id})"><span class="pt-chip-sym">${d.short}</span><small>${d.label}</small></button>`).join('')}
                    <button class="pt-chip" data-digitacion="" title="Quitar digitación">✕</button>
                </div>
                <p class="pt-muted" style="font-size:.72rem;margin:.35rem 0 0">D = derecha · I = izquierda (debajo del pentagrama)</p>
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
        `;
    }

    /** ------------------------------------------------------------- render */

    render() {
        this.aplicarVista();
        if (this.vista !== 'grilla' || this.modoPantalla === 'presentacion') this.renderPartitura();
        else { this.hits = []; this.measureBoxes = []; }
        if (this.vista !== 'partitura') this.grilla.render();
        this.el.zoomLabel.textContent = `${Math.round(this.zoom * 100)}%`;
        this.pintarInspector();
        this.pintarMixer();
        this.pintarEstructura();
        this.pintarGolpes();
        this.pintarStatus();
        this.marcarSeleccion();
        this.marcarBotonesDuracion();
        if (this.tour?.abierto) this.tour.colocar();
    }

    /** Dibuja el pentagrama (VexFlow). Solo cuando la partitura se ve o se exporta. */
    renderPartitura() {
        const anchoRaw = Math.floor(((this.el.wrap?.clientWidth || 900) - 48) / this.zoom);
        const ancho = Math.max(560, Number.isFinite(anchoRaw) ? anchoRaw : 900);
        const tmp = document.createElement('div');
        tmp.className = 'pt-canvas';
        try {
            const r = renderScore(tmp, this.score, { anchoPagina: ancho, todasLasVoces: true });
            this.el.canvas.replaceChildren(...tmp.childNodes);
            this.hits = r.hits;
            this.measureBoxes = r.measureBoxes;
        } catch (err) {
            console.error('Partitura: no se pudo dibujar', err);
            if (!this.el.canvas.childNodes.length) {
                this.el.canvas.innerHTML = '<p class="pt-empty">No se pudo dibujar el pentagrama. Recargá la página.</p>';
            }
            this.aviso('No se pudo dibujar el pentagrama.');
        }
        this.el.page.style.transform = `scale(${this.zoom})`;
    }

    seleccionInicial() {
        if (this.sel) return;
        const inst = this.score.instruments[0];
        if (inst) this.seleccionar({ sectionIdx: 0, measureIdx: 0, instId: inst.id, noteIdx: 0 });
    }

    seleccionar(sel, { desdeGrilla = false } = {}) {
        this.sel = sel;
        // Vista híbrida: la misma posición se marca en la grilla.
        if (!desdeGrilla && sel) {
            const voz = vozDe(this.score, sel) || [];
            const paso = ticksDeResolucion(this.resolucion);
            this.gsel = { sectionIdx: sel.sectionIdx, measureIdx: sel.measureIdx, instId: sel.instId, paso: pasoDeTick(tickDeNota(voz, sel.noteIdx), paso) };
            if (this.vista !== 'partitura') this.grilla.marcarCursor();
        }
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
        if (this.vista !== 'partitura') this.grilla.marcarPlay(pos);
        this.mostrarSena(pos);
        this.root.querySelectorAll('.pt-play-box').forEach((n) => n.remove());
        const box = this.measureBoxes.find((b) => b.sectionIdx === pos.sectionIdx && b.measureIdx === pos.measureIdx);
        if (!box) return;
        const el = document.createElement('div');
        el.className = 'pt-play-box pt-play-cursor';
        const frac = pos.countIn ? 0 : (Number.isFinite(pos.frac) ? pos.frac : 0);
        el.style.left = `${box.x + frac * box.w}px`;
        el.style.top = `${box.y}px`;
        el.style.width = '2px';
        el.style.height = `${box.h}px`;
        if (pos.countIn) el.classList.add('pt-play-countin');
        box.lineEl.appendChild(el);
    }

    pintarGolpes() {
        const zona = this.el.paleta.querySelector('[data-zone="golpes"]');
        if (!zona) return;
        const instId = this.sel?.instId || this.score.instruments[0]?.id;
        const golpes = golpesDe(instId);
        const teclas = TECLAS_GOLPE.map((_, i) => this.atajos.acciones.get(`golpe-${i + 1}`)?.teclas[0]);
        zona.innerHTML = golpes
            .map((g, i) => `<button class="pt-chip pt-chip-golpe" data-stroke="${g.id}" title="${g.label}${teclas[i] ? ` (${teclaLegible(teclas[i])})` : ''}">
                <span class="pt-chip-sym">${g.short}</span><small>${g.label.split(' ')[0]}</small>${teclas[i] ? `<kbd class="pt-chip-kbd">${teclaLegible(teclas[i])}</kbd>` : ''}</button>`)
            .join('');
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
                <dt>Mano</dt><dd>${nota.digitacion === 'D' ? 'Derecha (D)' : nota.digitacion === 'I' ? 'Izquierda (I)' : '—'}</dd>
                <dt>Dinámica</dt><dd>${nota.dyn || '—'}</dd>
                <dt>Grupo</dt><dd>${nota.tuplet ? `${nota.tuplet.num}:${nota.tuplet.den}${nota.tuplet.num === 3 ? ' (tresillo)' : ''}` : '—'}</dd>
            </dl>
            <div class="pt-kv-bar ${usado === cap ? 'ok' : 'warn'}">
                <span>Compás ${usado === cap ? 'completo' : 'incompleto'}</span><b>${usado}/${cap}</b>
            </div>
            ${nota.rest ? '' : `
            <label class="pt-field pt-field-wide"><span>Intensidad ${nota.vel ? `(${nota.vel})` : '(según golpe y dinámica)'}</span>
                <input type="range" min="1" max="127" value="${nota.vel || Math.round(100 * (nota.dyn ? 1 : 1))}" data-f="vel" aria-label="Intensidad del golpe">
                ${nota.vel ? '<button class="pt-mini" data-a="vel-auto" title="Volver a golpe y dinámica">auto</button>' : ''}
            </label>`}
            <div class="pt-side-actions">
                <label class="pt-field pt-field-wide"><span>Texto del compás</span>
                    <input class="pt-input" data-f="measure-text" value="${attr(m.texto || '')}" placeholder="Ej: D.C. al Fine">
                </label>
                <label class="pt-field pt-field-wide"><span>Seña de dirección ✋</span>
                    <input class="pt-input" data-f="sena-texto" value="${attr(m.sena?.texto || '')}" placeholder="Ej: Entrada de repique" maxlength="80">
                </label>
                <label class="pt-field pt-field-wide"><span>Tipo de seña</span>
                    <select class="pt-input pt-select" data-f="sena-tipo">
                        ${SENAS.map((x) => `<option value="${x.id}" ${(m.sena?.tipo || 'entrada') === x.id ? 'selected' : ''}>${x.label}</option>`).join('')}
                    </select>
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
                            <label class="pt-mix-extra" title="Paneo (izquierda / derecha)">⇆<input type="range" min="-1" max="1" step="0.1" value="${cfg.pan || 0}" data-f="pan" aria-label="Paneo de ${esc(def?.label || cfg.id)}"></label>
                            <label class="pt-mix-extra" title="Afinación en semitonos">♭♯<input type="number" min="-12" max="12" step="1" value="${cfg.pitch || 0}" data-f="pitch" class="pt-input pt-input-num pt-input-sm" aria-label="Afinación de ${esc(def?.label || cfg.id)}"></label>
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
            ${this.rango ? `<span class="pt-status-rango">Selección: compases ${Math.min(this.rango.desde, this.rango.hasta) + 1}–${Math.max(this.rango.desde, this.rango.hasta) + 1}</span>` : ''}
            ${this.tocando ? `<span class="pt-status-modo">${this.grabando ? '● Grabando' : 'Modo tocar'} · ${TECLAS_INSTRUMENTO.slice(0, this.score.instruments.length).join(' ')}</span>` : ''}
            <span class="pt-status-dirty ${this.estadoGuardado}" role="status">${this.textoGuardado()}</span>
        `;
    }

    textoGuardado() {
        if (this.readonly) return 'Solo lectura';
        switch (this.estadoGuardado) {
            case 'sucio': return '● Cambios sin guardar';
            case 'guardando': return 'Guardando borrador…';
            case 'borrador': return `✓ Borrador guardado${this.borradorAt ? ` ${haceCuanto(this.borradorAt)}` : ''} · falta publicar`;
            case 'error': return '⚠ No se pudo guardar el borrador (reintenta solo)';
            default: return '✓ Publicada';
        }
    }

    marcarBotonesDuracion() {
        this.root.querySelectorAll('[data-fig]').forEach((b) => b.classList.toggle('on', b.dataset.fig === this.herramientaId));
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
            if (nota) this.audio.golpe(mejor.instId, nota.stroke, 0, 1, this.score).catch((err) => this.aviso(`Audio: ${err.message}`));
        }
    }

    onClick(e) {
        const btn = e.target.closest('[data-a],[data-stroke],[data-dyn],[data-dur],[data-fig],[data-marca],[data-digitacion]');
        if (!btn) return;
        const a = btn.dataset.a;
        const secRow = btn.closest('[data-section]');
        const mixRow = btn.closest('[data-inst]');

        if (btn.dataset.fig) return this.aplicarHerramientaUI(btn.dataset.fig);
        if (btn.dataset.dur) return this.aplicarDuracion(btn.dataset.dur);
        if (btn.dataset.stroke) return this.editar(() => ops.setGolpe(this.score, this.sel, btn.dataset.stroke), btn.dataset.stroke);
        if (btn.dataset.dyn) return this.editar(() => ops.setDinamica(this.score, this.sel, btn.dataset.dyn));
        if (btn.hasAttribute('data-digitacion')) {
            const dig = btn.dataset.digitacion || null;
            return this.editar(() => ops.setDigitacion(this.score, this.sel, dig));
        }
        if (btn.dataset.marca) {
            const marca = MARCAS_TEXTO.find((m) => m.id === btn.dataset.marca);
            return this.editarCompas((m) => { m.texto = marca ? marca.texto : null; });
        }

        switch (a) {
            case 'tour': return this.tour.start(0);
            case 'atajos': return mostrarAyuda(this);
            case 'versiones': return mostrarVersiones(this);
            case 'pantalla-completa': return this.pantallaCompleta();
            case 'play': return this.togglePlay();
            case 'stop': return this.audio.stop();
            case 'metro': return this.toggleMetronomo();
            case 'metro-menu':
                this.root.querySelectorAll('.pt-dropdown').forEach((d) => { if (d !== btn.closest('.pt-dropdown')) d.classList.remove('open'); });
                return btn.closest('.pt-dropdown').classList.toggle('open');
            case 'tap': return this.tapTempo();
            case 'tempo-mas': return this.setTempo(this.score.tempo + 1);
            case 'tempo-menos': return this.setTempo(this.score.tempo - 1);
            case 'vista': return this.setVista(btn.dataset.v);
            case 'tocar': return this.toggleTocar();
            case 'grabar': return this.toggleGrabar();
            case 'midi-in': return this.toggleMidi();
            case 'aprender-mostrar': this.root.classList.add('pt-aprender-visto'); return this.render();
            case 'vel-auto': return this.editar(() => ops.setVel(this.score, this.sel, null), null, { compas: this.sel });
            case 'measure-before':
                if (!this.sel) return;
                return this.editar(() => {
                    const ok = ops.insertarCompas(this.score, this.sel.sectionIdx, this.sel.measureIdx, { antes: true });
                    if (ok) this.sel.measureIdx += 1;
                    return ok;
                });
            case 'measure-dup': return this.repetirSeleccion(1);
            case 'import-midi':
                this.cerrarMenus();
                return this.el.inputMidi?.click();
            case 'export-wav': return this.exportarWav();
            case 'dot': this.dotsActivos = this.dotsActivos ? 0 : 1; this.marcarBotonesDuracion();
                return this.editar(() => ops.toggleDot(this.score, this.sel, 1));
            case 'rest': this.modoSilencio = !this.modoSilencio; this.marcarBotonesDuracion();
                return this.editar(() => ops.toggleSilencio(this.score, this.sel));
            case 'undo': return this.undo();
            case 'redo': return this.redo();
            case 'zoom-in': return this.setZoom(this.zoom + 0.15);
            case 'zoom-out': return this.setZoom(this.zoom - 0.15);
            case 'save': return this.guardar();
            case 'export-menu':
            case 'import-menu':
                this.root.querySelectorAll('.pt-dropdown').forEach((d) => {
                    if (d !== btn.closest('.pt-dropdown')) d.classList.remove('open');
                });
                return btn.closest('.pt-dropdown').classList.toggle('open');
            case 'import-ref':
                this.cerrarMenus();
                return this.el.inputRef?.click();
            case 'import-xml':
                this.cerrarMenus();
                return this.el.inputXml?.click();
            case 'import-json':
                this.cerrarMenus();
                return this.el.inputJson?.click();
            case 'toggle-ref':
                return this.toggleReferencia();
            case 'export-pdf': this.asegurarPartitura(); return exportarPDF(this.el.canvas, this.score).catch((err) => this.aviso(`PDF: ${err.message}`));
            case 'export-png': this.asegurarPartitura(); return exportarPNG(this.el.canvas, this.score).catch((err) => this.aviso(`PNG: ${err.message}`));
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
            case 'score-clear': return this.vaciarPartituraUI();
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
                if (id) this.audio.golpe(id, golpesDe(id)[0]?.id || 'nota', 0, 1, this.score).catch((err) => this.aviso(`Audio: ${err.message}`));
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
        if (f === 'pan' && mixRow) return this.mixer(mixRow, (cfg) => { cfg.pan = Number(e.target.value); });
        if (f === 'pitch' && mixRow) return this.mixer(mixRow, (cfg) => { cfg.pitch = Math.max(-12, Math.min(12, parseInt(e.target.value, 10) || 0)); });
        if (f === 'loop') { this.loopModo = e.target.value; return this.aviso(this.loopModo === 'off' ? 'Sin loop' : `Loop: ${e.target.selectedOptions[0].textContent}`); }
        if (f === 'countin') { this.countInCompases = Number(e.target.value) || 0; return; }
        if (f === 'metro-sub') { this.audio.metroSub = Number(e.target.value) || 1; return; }
        if (f === 'metro-vol') { this.audio.metroGain = Number(e.target.value); return; }
        if (f === 'metro-acento') { this.audio.metroAcento = e.target.checked; return; }
        if (f === 'resolucion') { this.resolucion = e.target.value; guardarPref('pt-resolucion', this.resolucion); return this.render(); }
        if (f === 'modo') return this.setModoPantalla(e.target.value);
        if (f === 'repetir') {
            const veces = Number(e.target.value);
            e.target.value = '';
            if (veces) this.repetirSeleccion(veces - 1);
            return;
        }
        if (f === 'vel' && this.sel) return this.editar(() => ops.setVel(this.score, this.sel, Number(e.target.value)), null, { compas: this.sel });
        if (f === 'sena-tipo' && this.sel) {
            const m = this.score.sections[this.sel.sectionIdx]?.measures[this.sel.measureIdx];
            if (!m?.sena) return;
            return this.editar(() => ops.setSena(this.score, this.sel.sectionIdx, this.sel.measureIdx, { ...m.sena, tipo: e.target.value }));
        }
    }

    onInput(e) {
        const f = e.target.dataset.f;
        if (!f) return;
        if (f === 'title') { this.score.title = e.target.value; return this.tocado(); }
        if (f === 'autor') { this.score.autor = e.target.value; return this.tocado(); }
        if (f === 'tempo' || f === 'tempo-slider') {
            this.setTempo(e.target.value);
            return;
        }
        if (f === 'sec-name') {
            const si = Number(e.target.closest('[data-section]').dataset.section);
            this.score.sections[si].name = e.target.value;
            return this.tocado();
        }
        if (f === 'sena-texto') {
            if (!this.sel) return;
            const { sectionIdx, measureIdx } = this.sel;
            const tipo = this.root.querySelector('[data-f="sena-tipo"]')?.value || 'entrada';
            ops.setSena(this.score, sectionIdx, measureIdx, e.target.value.trim() ? { texto: e.target.value, tipo } : null);
            this.tocado();
            if (this.vista !== 'partitura') this.grilla.renderCompas(sectionIdx, measureIdx);
            return this.renderDiferido();
        }
        if (f === 'measure-text') {
            if (!this.sel) return;
            const m = this.score.sections[this.sel.sectionIdx].measures[this.sel.measureIdx];
            m.texto = e.target.value || null;
            this.tocado();
            return this.renderDiferido();
        }
        if (f === 'ref-op' && this.el.refBody) {
            this.el.refBody.style.opacity = String(Number(e.target.value) / 100);
        }
    }

    bindTeclado() {
        document.addEventListener('keydown', (e) => {
            if (this.tour?.abierto) return;
            if (e.defaultPrevented || e.isComposing) return;
            if (this.root.querySelector('dialog.pt-dialog[open]')) return;
            const enCampo = e.target.matches?.('input, textarea, select, [contenteditable="true"]');
            if (enCampo && e.key !== 'Escape') return;
            if (e.target.closest?.('.pt-menu-golpe')) return;
            const accion = this.atajos.resolver(e, this.contextosActivos());
            if (!accion) return;
            e.preventDefault();
            if (e.repeat && !accion.repetible) return;
            accion.run(e);
        });
    }

    /** El contexto decide qué hacen las letras: tocar > grilla > partitura. */
    contextosActivos() {
        if (this.tocando) return ['tocar', 'global'];
        if (this.foco === 'grilla' && this.vista !== 'partitura') return ['grilla', 'global'];
        return ['partitura', 'global'];
    }

    setFoco(foco) {
        if (this.foco === foco) return;
        this.foco = foco;
        this.root.classList.toggle('pt-foco-grilla', foco === 'grilla');
        if (foco === 'grilla' && !this.gsel) this.gselInicial();
        this.grilla?.marcarCursor();
    }

    /**
     * Todas las acciones con teclado. La ayuda (?) se genera desde acá.
     * Ver la tabla en docs/EDITOR_RITMOS.md §5.
     */
    registrarAtajos() {
        const r = this.atajos;
        const P = ['partitura'];
        const G = ['grilla'];
        // Transporte y general
        r.registrar({ id: 'play', etiqueta: 'Reproducir / pausa', grupo: 'Escuchar', teclas: 'Space', run: () => this.togglePlay() });
        r.registrar({ id: 'stop', etiqueta: 'Detener y volver al inicio', grupo: 'Escuchar', teclas: 'Shift+Space', run: () => this.audio.stop() });
        r.registrar({ id: 'loop', etiqueta: 'Loop (todo ↔ apagado)', grupo: 'Escuchar', teclas: 'L', run: () => this.toggleLoop() });
        r.registrar({ id: 'metro', etiqueta: 'Metrónomo', grupo: 'Escuchar', teclas: 'M', run: () => this.toggleMetronomo() });
        r.registrar({ id: 'bpm-mas', etiqueta: 'Subir tempo', grupo: 'Escuchar', teclas: ['+', '='], run: () => this.setTempo(this.score.tempo + 1) });
        r.registrar({ id: 'bpm-menos', etiqueta: 'Bajar tempo', grupo: 'Escuchar', teclas: '-', run: () => this.setTempo(this.score.tempo - 1) });
        r.registrar({ id: 'tocar', etiqueta: 'Modo tocar (el teclado es un instrumento)', grupo: 'Tocar', teclas: 'P', run: () => this.toggleTocar() });
        r.registrar({ id: 'escape', etiqueta: 'Salir / cancelar selección', grupo: 'General', teclas: 'Escape', run: () => this.escape() });
        r.registrar({ id: 'ayuda', etiqueta: 'Mostrar atajos', grupo: 'General', teclas: '?', run: () => mostrarAyuda(this) });
        r.registrar({ id: 'vista-partitura', etiqueta: 'Ver partitura', grupo: 'Vistas', teclas: 'Alt+1', run: () => this.setVista('partitura') });
        r.registrar({ id: 'vista-grilla', etiqueta: 'Ver grilla', grupo: 'Vistas', teclas: 'Alt+2', run: () => this.setVista('grilla') });
        r.registrar({ id: 'vista-mixta', etiqueta: 'Ver grilla y partitura', grupo: 'Vistas', teclas: 'Alt+3', run: () => this.setVista('mixta') });
        r.registrar({ id: 'deshacer', etiqueta: 'Deshacer', grupo: 'Editar', teclas: 'Ctrl+Z', run: () => this.undo() });
        r.registrar({ id: 'rehacer', etiqueta: 'Rehacer', grupo: 'Editar', teclas: ['Ctrl+Y', 'Ctrl+Shift+Z'], run: () => this.redo() });
        r.registrar({ id: 'guardar', etiqueta: 'Publicar versión', grupo: 'Editar', teclas: 'Ctrl+S', run: () => this.guardar() });
        r.registrar({ id: 'copiar', etiqueta: 'Copiar compás / selección', grupo: 'Editar', teclas: 'Ctrl+C', run: () => this.copiar() });
        r.registrar({ id: 'pegar', etiqueta: 'Pegar en el compás actual', grupo: 'Editar', teclas: 'Ctrl+V', run: () => this.pegar() });
        r.registrar({ id: 'repetir', etiqueta: 'Repetir compás / selección a continuación', grupo: 'Editar', teclas: 'R', run: () => this.repetirSeleccion(1) });
        r.registrar({ id: 'sel-izq', etiqueta: 'Extender selección de compases ←', grupo: 'Editar', teclas: 'Shift+ArrowLeft', run: () => this.extenderRango(-1), repetible: true });
        r.registrar({ id: 'sel-der', etiqueta: 'Extender selección de compases →', grupo: 'Editar', teclas: 'Shift+ArrowRight', run: () => this.extenderRango(1), repetible: true });
        r.registrar({ id: 'dyn-menos', etiqueta: 'Golpe más suave (dinámica)', grupo: 'Editar', teclas: '<', run: () => this.pasoDinamica(-1) });
        r.registrar({ id: 'dyn-mas', etiqueta: 'Golpe más fuerte (dinámica)', grupo: 'Editar', teclas: '>', run: () => this.pasoDinamica(1) });

        // Partitura
        r.registrar({ id: 'p-izq', etiqueta: 'Nota anterior', grupo: 'Partitura', contextos: P, teclas: 'ArrowLeft', run: () => this.mover(-1), repetible: true });
        r.registrar({ id: 'p-der', etiqueta: 'Nota siguiente', grupo: 'Partitura', contextos: P, teclas: 'ArrowRight', run: () => this.mover(1), repetible: true });
        r.registrar({ id: 'p-arriba', etiqueta: 'Instrumento anterior', grupo: 'Partitura', contextos: P, teclas: 'ArrowUp', run: () => this.moverInstrumento(-1), repetible: true });
        r.registrar({ id: 'p-abajo', etiqueta: 'Instrumento siguiente', grupo: 'Partitura', contextos: P, teclas: 'ArrowDown', run: () => this.moverInstrumento(1), repetible: true });
        DURACIONES.forEach((d) => r.registrar({ id: `fig-${d.code}`, etiqueta: d.label, grupo: 'Partitura', contextos: P, teclas: d.tecla, run: () => this.aplicarDuracion(d.code) }));
        r.registrar({ id: 'puntillo', etiqueta: 'Puntillo', grupo: 'Partitura', contextos: P, teclas: '.', run: () => this.aplicarHerramientaUI(this.durActiva === 'h' ? 'h.' : this.durActiva === '8' ? '8.' : 'q.') });
        r.registrar({ id: 'tresillo', etiqueta: 'Tresillo', grupo: 'Partitura', contextos: P, teclas: 'Ctrl+3', run: () => this.aplicarHerramientaUI('3:2-8') });
        r.registrar({ id: 'sextillo', etiqueta: 'Sextillo', grupo: 'Partitura', contextos: P, teclas: 'Ctrl+6', run: () => this.aplicarHerramientaUI('6:4-16') });
        r.registrar({ id: 'silencio', etiqueta: 'Silencio', grupo: 'Partitura', contextos: P, teclas: '0', run: () => {
            const id = `${this.durActiva}r`;
            this.aplicarHerramientaUI(herramientaPorId(id) ? id : 'qr');
        } });
        r.registrar({ id: 'insertar', etiqueta: 'Insertar nota después', grupo: 'Partitura', contextos: P, teclas: 'Enter', run: () => this.insertar() });
        r.registrar({ id: 'borrar', etiqueta: 'Borrar', grupo: 'Editar', contextos: ['partitura', 'grilla'], teclas: ['Delete', 'Backspace'], run: () => this.borrarActual() });
        r.registrar({ id: 'entrada', etiqueta: 'Entrada continua (avanza al escribir)', grupo: 'Editar', contextos: ['partitura', 'grilla'], teclas: 'N', run: () => {
            this.entrada = !this.entrada;
            this.aviso(this.entrada ? 'Entrada continua: avanza al escribir' : 'Entrada continua apagada');
        } });
        r.registrar({ id: 'dig-d', etiqueta: 'Mano derecha', grupo: 'Partitura', contextos: P, teclas: 'D', run: () => this.editar(() => ops.setDigitacion(this.score, this.sel, 'D')) });
        r.registrar({ id: 'dig-i', etiqueta: 'Mano izquierda', grupo: 'Partitura', contextos: P, teclas: 'I', run: () => this.editar(() => ops.setDigitacion(this.score, this.sel, 'I')) });
        TECLAS_GOLPE.forEach((t, i) => r.registrar({
            id: `golpe-${i + 1}`, etiqueta: `Golpe ${i + 1} del instrumento`, grupo: 'Golpes', contextos: ['partitura', 'grilla'], teclas: t, personalizable: true,
            run: (e) => this.golpePorIndice(i, e),
        }));

        // Grilla
        r.registrar({ id: 'g-izq', etiqueta: 'Paso anterior', grupo: 'Grilla', contextos: G, teclas: 'ArrowLeft', run: () => this.moverGrilla(-1, 0), repetible: true });
        r.registrar({ id: 'g-der', etiqueta: 'Paso siguiente', grupo: 'Grilla', contextos: G, teclas: 'ArrowRight', run: () => this.moverGrilla(1, 0), repetible: true });
        r.registrar({ id: 'g-arriba', etiqueta: 'Instrumento anterior', grupo: 'Grilla', contextos: G, teclas: 'ArrowUp', run: () => this.moverGrilla(0, -1), repetible: true });
        r.registrar({ id: 'g-abajo', etiqueta: 'Instrumento siguiente', grupo: 'Grilla', contextos: G, teclas: 'ArrowDown', run: () => this.moverGrilla(0, 1), repetible: true });
        r.registrar({ id: 'g-alternar', etiqueta: 'Poner / sacar golpe', grupo: 'Grilla', contextos: G, teclas: ['X', 'Enter'], run: () => this.gsel && this.grillaClick(this.gsel) });
        r.registrar({ id: 'g-silencio', etiqueta: 'Sacar golpe', grupo: 'Grilla', contextos: G, teclas: '0', run: () => this.gsel && this.grillaQuitar(this.gsel) });
        [['4', '8'], ['5', '16'], ['6', '32']].forEach(([t, res]) => r.registrar({
            id: `res-${res}`, etiqueta: `Grilla de ${RESOLUCIONES.find((x) => x.id === res).label.toLowerCase()}`, grupo: 'Grilla', contextos: G, teclas: t,
            run: () => this.setResolucion(res),
        }));

        // Instrumentos (grilla y modo tocar comparten teclas)
        TECLAS_INSTRUMENTO.forEach((t, i) => r.registrar({
            id: `inst-${i + 1}`, etiqueta: `Instrumento ${i + 1} de la partitura`, grupo: 'Tocar', contextos: ['grilla', 'tocar'], teclas: t, personalizable: true,
            run: () => this.golpeDeInstrumento(i, t),
        }));
    }

    guardarAtajos() {
        try { localStorage.setItem('pt-atajos', JSON.stringify(this.atajos.exportar())); } catch { /* sin almacenamiento */ }
        this.pintarGolpes();
    }

    /** ------------------------------------------------------------- acciones */

    aplicarDuracion(dur) {
        return this.aplicarHerramientaUI(dur);
    }

    aplicarHerramientaUI(id) {
        const h = herramientaPorId(id);
        if (!h) return;
        this.herramientaId = h.id;
        this.durActiva = h.dur;
        this.dotsActivos = h.dots || 0;
        this.modoSilencio = h.kind === 'silencio';
        this.marcarBotonesDuracion();
        if (!this.sel) return;
        this.editar(() => {
            const n = ops.aplicarHerramienta(this.score, this.sel, h);
            return n !== false && n !== 0;
        });
    }

    vaciarPartituraUI() {
        if (this.readonly) return;
        if (!window.confirm('¿Borrar toda la partitura y empezar de cero?\nQueda un compás vacío. Se puede deshacer con Ctrl+Z.')) return;
        this.editar(() => {
            const ok = ops.vaciarPartitura(this.score);
            if (ok) {
                const inst = this.score.instruments[0];
                this.sel = inst
                    ? { sectionIdx: 0, measureIdx: 0, instId: inst.id, noteIdx: 0 }
                    : null;
            }
            return ok;
        });
    }

    insertar() {
        if (!this.sel) return;
        const h = herramientaPorId(this.herramientaId) || herramientaPorId(this.durActiva);
        if (!h) return;
        this.editar(() => {
            const n = ops.aplicarHerramienta(this.score, this.sel, h, { insertar: true });
            if (n) this.sel.noteIdx += 1;
            return n !== false && n !== 0;
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

    /**
     * Ejecuta una operación con undo + re-render. Con `compas`, la grilla redibuja solo ese
     * compás y la partitura se redibuja diferida (escribir rápido no espera a VexFlow).
     */
    editar(fn, previewStroke = null, { compas = null } = {}) {
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
        if (compas && this.vista !== 'partitura') {
            this.grilla.renderCompas(compas.sectionIdx, compas.measureIdx);
            this.pintarStatus();
            this.pintarInspector();
            if (this.vista === 'mixta') this.renderDiferido(250);
        } else {
            this.render();
        }
        if (previewStroke && this.sel) this.audio.golpe(this.sel.instId, previewStroke, 0, 1, this.score).catch((err) => this.aviso(`Audio: ${err.message}`));
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

    setTempo(raw) {
        const bpm = Math.min(TEMPO_MAX, Math.max(TEMPO_MIN, Math.round(Number(raw)) || 88));
        if (bpm === this.score.tempo) return;
        this.score.tempo = bpm;
        const num = this.root.querySelector('[data-f="tempo"]');
        const slider = this.root.querySelector('[data-f="tempo-slider"]');
        if (num && document.activeElement !== num) num.value = String(bpm);
        if (slider) slider.value = String(bpm);
        this.tocado();
        // Cambiar el tempo mientras suena: sigue desde el mismo lugar, sin cortar.
        clearTimeout(this._retempo);
        if (this.audio.playing) this._retempo = setTimeout(() => this.audio.cambiarTempo(this.score).catch(() => {}), 120);
    }

    /** Tramo a repetir según el modo de loop (null = toda la obra). */
    rangoDeLoop() {
        const pos = this.gsel || this.sel;
        if (!pos) return null;
        const sec = this.score.sections[pos.sectionIdx];
        const n = sec?.measures.length || 1;
        switch (this.loopModo) {
            case 'compas': return { sectionIdx: pos.sectionIdx, desde: pos.measureIdx, hasta: pos.measureIdx };
            case 'seleccion': return this.rango || { sectionIdx: pos.sectionIdx, desde: pos.measureIdx, hasta: pos.measureIdx };
            case '2': case '4': case '8': {
                const k = Number(this.loopModo);
                return { sectionIdx: pos.sectionIdx, desde: pos.measureIdx, hasta: Math.min(n - 1, pos.measureIdx + k - 1) };
            }
            default: return null;
        }
    }

    async togglePlay() {
        if (this.audio.playing) return this.audio.pause();
        return this.play();
    }

    async play(opts = {}) {
        const btn = this.root.querySelector('.pt-btn-play');
        btn?.classList.add('on');
        if (btn) btn.textContent = '❚❚';
        const rango = opts.soloSeccion === undefined ? this.rangoDeLoop() : null;
        const base = {
            loop: this.loopModo !== 'off',
            soloSeccion: opts.soloSeccion ?? null,
            rango,
            countInCompases: this.countInCompases,
        };
        try {
            if (this.audio.paused) {
                await this.audio.resume(this.score, base);
                return;
            }
            const pos = this.sel;
            await this.audio.play(this.score, {
                ...base,
                desde: !rango && opts.soloSeccion === undefined && pos ? { sectionIdx: pos.sectionIdx, measureIdx: 0 } : null,
                countIn: this.countInCompases > 0,
            });
            this._inicioPlay = performance.now();
        } catch (err) {
            this.aviso(`Audio: ${err.message}`);
            this.finTransporte();
        }
    }

    finTransporte() {
        const play = this.root.querySelector('.pt-btn-play');
        play?.classList.remove('on');
        if (play) play.textContent = '▶';
        this.root.querySelectorAll('.pt-play-box').forEach((n) => n.remove());
        this.grilla?.limpiarPlay();
        if (this.el?.sena) this.el.sena.hidden = true;
        if (this.grabando) this.terminarGrabacion();
    }

    tocado() {
        this.dirty = true;
        this.estadoGuardado = 'sucio';
        this.pintarStatus();
        const hidden = document.querySelector('[data-partitura-json]');
        if (hidden) hidden.value = JSON.stringify(this.score);
        this.programarAutoguardado();
    }

    /** Autoguardado del borrador con debounce: una sola petición por ráfaga de cambios. */
    programarAutoguardado() {
        if (this.readonly) return;
        clearTimeout(this._autosave);
        this._autosave = setTimeout(() => this.autoguardar(), AUTOGUARDADO_MS);
    }

    async autoguardar() {
        const nombre = (this.editorNombre || this.root.dataset.editorNombre || '').trim();
        // Sin servidor o sin firma: el borrador queda en este navegador.
        if (!this.borradorUrl || nombre.length < 2) {
            try {
                localStorage.setItem(`pt-borrador:${this.slug}`, JSON.stringify({ score: this.score, at: new Date().toISOString() }));
                this.estadoGuardado = 'borrador';
                this.borradorAt = new Date();
            } catch { /* sin almacenamiento */ }
            return this.pintarStatus();
        }
        if (this._guardandoBorrador) return this.programarAutoguardado();
        this._guardandoBorrador = true;
        this.estadoGuardado = 'guardando';
        this.pintarStatus();
        try {
            const res = await fetch(this.borradorUrl, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ score: this.score, editor_nombre: nombre }),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            this.estadoGuardado = this.estadoGuardado === 'sucio' ? 'sucio' : 'borrador';
            this.borradorAt = new Date();
        } catch {
            this.estadoGuardado = 'error';
        } finally {
            this._guardandoBorrador = false;
            this.pintarStatus();
        }
    }

    /** Si hay un borrador más nuevo que lo publicado (servidor o navegador), ofrece recuperarlo. */
    ofrecerBorrador() {
        let b = this.borradorPendiente;
        let origen = 'servidor';
        if (!b) {
            try {
                const local = JSON.parse(localStorage.getItem(`pt-borrador:${this.slug}`) || 'null');
                if (local?.score && JSON.stringify(normalizarPartitura(local.score).sections) !== JSON.stringify(this.score.sections)) {
                    b = local;
                    origen = 'navegador';
                }
            } catch { /* nada */ }
        }
        if (!b?.score || this.readonly) return;
        const cuando = b.at ? new Date(b.at).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : '';
        const barra = document.createElement('div');
        barra.className = 'pt-borrador-barra';
        barra.setAttribute('role', 'status');
        barra.innerHTML = `<span>Hay un borrador sin publicar${b.autor ? ` de <b>${esc(b.autor)}</b>` : ''}${cuando ? ` (${cuando})` : ''} guardado en el ${origen}.</span>
            <button class="pt-btn pt-btn-primary" data-b="recuperar">Recuperar</button>
            <button class="pt-btn" data-b="descartar">Descartar</button>`;
        this.root.prepend(barra);
        barra.addEventListener('click', (e) => {
            const accion = e.target.closest('[data-b]')?.dataset.b;
            if (!accion) return;
            if (accion === 'recuperar') {
                this.aplicarScoreImportado(b.score);
                this.aviso('Borrador recuperado. Publicalo cuando esté listo.');
            } else {
                try { localStorage.removeItem(`pt-borrador:${this.slug}`); } catch { /* nada */ }
                if (origen === 'servidor' && this.borradorUrl) fetch(this.borradorUrl, { method: 'DELETE', headers: jsonHeaders() }).catch(() => {});
            }
            barra.remove();
        });
    }

    async cargarVersion(numero) {
        try {
            const res = await fetch(`${this.versionesUrl}/${numero}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const { data } = await res.json();
            this.aplicarScoreImportado(data.score);
            this.aviso(`Versión ${numero} cargada. Publicala si querés volver a ella.`);
        } catch (err) {
            this.aviso(`No se pudo abrir la versión: ${err.message}`);
        }
    }

    async guardar() {
        if (this.readonly || !this.saveUrl || this.guardando) return;
        const nombre = (this.editorNombre || this.root.dataset.editorNombre || '').trim();
        if (nombre.length < 2) {
            this.aviso('Indicá tu nombre para guardar.');
            window.dispatchEvent(new CustomEvent('partitura:pedir-nombre'));
            return;
        }
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
                body: JSON.stringify({ score: this.score, editor_nombre: nombre }),
            });
            clearTimeout(this._autosave);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || data.error || (data.errors?.editor_nombre?.[0]) || `HTTP ${res.status}`;
                throw new Error(msg);
            }
            if (data.score) this.score = normalizarPartitura(data.score);
            this.dirty = false;
            this.estadoGuardado = 'guardado';
            try { localStorage.removeItem(`pt-borrador:${this.slug}`); } catch { /* nada */ }
            this.render();
            this.aviso(`Publicada${data.version ? ` como versión ${data.version}` : ''}${data.editado_por ? ` · ${data.editado_por}` : ''}.`);
        } catch (err) {
            this.aviso(`No se pudo guardar: ${err.message}`);
        } finally {
            this.guardando = false;
        }
    }

    renderDiferido(ms = 400) {
        clearTimeout(this._rt);
        this._rt = setTimeout(() => {
            if (this.vista === 'grilla') return;
            this.renderPartitura();
            this.marcarSeleccion();
        }, ms);
    }

    cerrarMenus() {
        this.root.querySelectorAll('.pt-dropdown').forEach((d) => d.classList.remove('open'));
    }

    bindDropImport() {
        const wrap = this.el.wrap;
        if (!wrap || this.readonly) return;
        wrap.addEventListener('dragover', (e) => {
            if (![...e.dataTransfer.items].some((i) => i.kind === 'file')) return;
            e.preventDefault();
            wrap.classList.add('pt-drop');
        });
        wrap.addEventListener('dragleave', () => wrap.classList.remove('pt-drop'));
        wrap.addEventListener('drop', (e) => {
            wrap.classList.remove('pt-drop');
            const file = e.dataTransfer?.files?.[0];
            if (!file) return;
            e.preventDefault();
            this.onImportFile(file);
        });
    }

    async onImportFile(file) {
        if (!file || this.readonly) return;
        const tipo = tipoArchivoImport(file);
        if (this.el.inputRef) this.el.inputRef.value = '';
        if (this.el.inputXml) this.el.inputXml.value = '';
        if (this.el.inputJson) this.el.inputJson.value = '';

        if (tipo === 'mxl') {
            return this.aviso('Exportá desde MuseScore como MusicXML descomprimido (.musicxml), no .mxl.');
        }
        if (tipo === 'pdf' || tipo === 'imagen') {
            return this.subirReferencia(file);
        }
        if (tipo === 'xml' || tipo === 'json') {
            try {
                const texto = await file.text();
                const score = tipo === 'json' ? importarScoreJson(texto) : importarMusicXML(texto);
                this.aplicarScoreImportado(score);
                this.aviso(tipo === 'json'
                    ? 'JSON cargado. Revisá y guardá.'
                    : 'MusicXML volcado al editor. Revisá tambores y golpes, y guardá.');
            } catch (err) {
                this.aviso(err.message || 'No se pudo importar el archivo.');
            }
            return;
        }
        this.aviso('Usá PDF/imagen (original), MusicXML de MuseScore o JSON del editor.');
    }

    aplicarScoreImportado(score) {
        this.editar(() => {
            this.score = normalizarPartitura(score);
            this.sel = null;
            const titleInput = this.root.querySelector('[data-f="title"]');
            const autorInput = this.root.querySelector('[data-f="autor"]');
            const tempoInput = this.root.querySelector('[data-f="tempo"]');
            const tsInput = this.root.querySelector('[data-f="ts"]');
            if (titleInput) titleInput.value = this.score.title || '';
            if (autorInput) autorInput.value = this.score.autor || '';
            if (tempoInput) tempoInput.value = String(this.score.tempo || 88);
            const tempoSlider = this.root.querySelector('[data-f="tempo-slider"]');
            if (tempoSlider) tempoSlider.value = String(this.score.tempo || 88);
            if (tsInput) tsInput.value = `${this.score.timeSignature.num}/${this.score.timeSignature.den}`;
            return true;
        });
        this.seleccionInicial();
    }

    async subirReferencia(file) {
        if (!this.uploadRefUrl) {
            return this.aviso('Este toque no permite subir el original desde acá.');
        }
        const nombre = (this.editorNombre || this.root.dataset.editorNombre || '').trim();
        if (nombre.length < 2) {
            this.aviso('Indicá tu nombre para subir el original.');
            window.dispatchEvent(new CustomEvent('partitura:pedir-nombre'));
            return;
        }
        this.aviso('Subiendo original…');
        const fd = new FormData();
        fd.append('partitura_archivo', file);
        fd.append('editor_nombre', nombre);
        try {
            const res = await fetch(this.uploadRefUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || data.error || data.errors?.partitura_archivo?.[0] || `HTTP ${res.status}`);
            }
            this.mostrarReferencia(data.url, data.es_pdf ? 'pdf' : 'imagen', data.nombre || file.name, true);
            this.aviso('Original al lado. Transcribí las notas en el editor y guardá.');
        } catch (err) {
            this.aviso(`No se pudo subir: ${err.message}`);
        }
    }

    mostrarReferencia(url, tipo, nombre, abrir = true) {
        this.refUrl = url;
        this.refTipo = tipo === 'pdf' || tipo === 'video' ? tipo : 'imagen';
        this.refNombre = nombre || '';
        if (this.el.refName) this.el.refName.textContent = this.refNombre;
        if (this.el.refBody) {
            if (this.refTipo === 'pdf') {
                this.el.refBody.innerHTML = `<iframe src="${attr(url)}#view=FitH" title="Original PDF"></iframe>`;
            } else if (this.refTipo === 'video') {
                this.el.refBody.innerHTML = `<video src="${attr(url)}" controls playsinline></video>`;
            } else {
                this.el.refBody.innerHTML = `<img src="${attr(url)}" alt="${attr(this.refNombre || 'Original')}">`;
            }
        }
        const toggle = this.root.querySelector('[data-a="toggle-ref"]');
        if (toggle) toggle.hidden = false;
        if (abrir) this.setReferenciaVisible(true);
    }

    toggleReferencia() {
        this.setReferenciaVisible(!this.root.classList.contains('has-ref'));
    }

    setReferenciaVisible(on) {
        if (!this.el.ref) return;
        this.el.ref.hidden = !on;
        this.root.classList.toggle('has-ref', on);
        this.root.querySelectorAll('[data-a="toggle-ref"]').forEach((b) => b.classList.toggle('on', on));
        this.render();
    }

    /** ------------------------------------------------------------- vistas y modos */

    aplicarVista() {
        const r = this.root;
        ['partitura', 'grilla', 'mixta'].forEach((v) => r.classList.toggle(`pt-vista-${v}`, this.vista === v));
        ['edicion', 'ensayo', 'clase', 'presentacion', 'aprendizaje'].forEach((m) => r.classList.toggle(`pt-modo-${m}`, this.modoPantalla === m));
        r.querySelectorAll('[data-a="vista"]').forEach((b) => {
            b.classList.toggle('on', b.dataset.v === this.vista);
            b.setAttribute('aria-pressed', String(b.dataset.v === this.vista));
        });
        if (this.el.grilla) this.el.grilla.hidden = this.vista === 'partitura';
        if (this.el.page) this.el.page.hidden = this.vista === 'grilla' && this.modoPantalla !== 'presentacion';
        const ocultar = this.modoPantalla === 'aprendizaje' && !r.classList.contains('pt-aprender-visto');
        if (this.el.aprender) this.el.aprender.hidden = !ocultar;
        r.classList.toggle('pt-oculta-notacion', ocultar);
    }

    setVista(v) {
        if (!['partitura', 'grilla', 'mixta'].includes(v)) return;
        this.vista = v;
        guardarPref('pt-vista', v);
        this.setFoco(v === 'grilla' ? 'grilla' : v === 'partitura' ? 'partitura' : this.foco);
        this.render();
        this.aviso(v === 'grilla' ? 'Vista grilla' : v === 'partitura' ? 'Vista partitura' : 'Grilla y partitura');
    }

    setModoPantalla(m) {
        this.modoPantalla = m;
        const sel = this.root.querySelector('[data-f="modo"]');
        if (sel && sel.value !== m) sel.value = m;
        this.root.classList.remove('pt-aprender-visto');
        if (m === 'clase' || m === 'ensayo') this.seguirReproduccion = true;
        this.render();
        const textos = {
            ensayo: 'Modo ensayo: pantalla simple, tempo, loop y metrónomo a mano.',
            clase: 'Modo clase: usá M (mute) y S (solo) de cada instrumento para armar el toque por partes.',
            presentacion: 'Modo presentación: título, tempo y partitura. ⛶ para pantalla completa.',
            aprendizaje: 'Modo aprendizaje: escuchá sin ver la partitura y después comparala.',
            edicion: 'Modo edición',
        };
        this.aviso(textos[m] || '');
    }

    pantallaCompleta() {
        if (document.fullscreenElement) return document.exitFullscreen?.();
        return this.root.requestFullscreen?.().catch(() => this.aviso('El navegador no permitió la pantalla completa.'));
    }

    toggleMetronomo() {
        this.audio.metronomo = !this.audio.metronomo;
        const b = this.root.querySelector('[data-a="metro"]');
        b?.classList.toggle('on', this.audio.metronomo);
        b?.setAttribute('aria-pressed', String(this.audio.metronomo));
        if (this.audio.playing) this.audio.cambiarTempo(this.score).catch(() => {});
        this.aviso(this.audio.metronomo ? 'Metrónomo encendido' : 'Metrónomo apagado');
    }

    toggleLoop() {
        this.loopModo = this.loopModo === 'off' ? 'todo' : 'off';
        const sel = this.root.querySelector('[data-f="loop"]');
        if (sel) sel.value = this.loopModo;
        this.aviso(this.loopModo === 'off' ? 'Sin loop' : 'Loop: todo');
    }

    tapTempo() {
        this.toques = registrarToque(this.toques, performance.now());
        const bpm = bpmDeToques(this.toques, { min: TEMPO_MIN, max: TEMPO_MAX });
        if (bpm) {
            this.setTempo(bpm);
            this.aviso(`♩ = ${bpm}`);
        } else {
            this.aviso('Seguí tocando TAP al pulso…');
        }
    }

    escape() {
        this.cerrarMenus();
        this.root.querySelectorAll('.pt-menu-golpe').forEach((m) => m.remove());
        if (this.tocando) return this.toggleTocar();
        if (this.rango) {
            this.rango = null;
            this.marcarRango();
            return this.pintarStatus();
        }
        return undefined;
    }

    /** ------------------------------------------------------------- grilla */

    instrumentosVisibles() {
        return this.score.instruments.filter((i) => i.visible !== false);
    }

    gselInicial() {
        const s = this.sel;
        const inst = s?.instId || this.instrumentosVisibles()[0]?.id;
        if (!inst) return;
        const voz = s ? vozDe(this.score, s) || [] : [];
        this.gsel = {
            sectionIdx: s?.sectionIdx || 0,
            measureIdx: s?.measureIdx || 0,
            instId: inst,
            paso: s ? pasoDeTick(tickDeNota(voz, s.noteIdx), ticksDeResolucion(this.resolucion)) : 0,
        };
    }

    setResolucion(res) {
        this.resolucion = res;
        guardarPref('pt-resolucion', res);
        const sel = this.root.querySelector('[data-f="resolucion"]');
        if (sel) sel.value = res;
        if (this.gsel) this.gsel.paso = 0;
        this.render();
        this.aviso(`Grilla de ${RESOLUCIONES.find((r) => r.id === res)?.label.toLowerCase()}`);
    }

    moverGrilla(dx, dy) {
        if (!this.gsel) this.gselInicial();
        const g = this.gsel;
        if (!g) return;
        if (dy) {
            const insts = this.instrumentosVisibles();
            const i = insts.findIndex((x) => x.id === g.instId);
            const next = insts[i + dy];
            if (next) g.instId = next.id;
        }
        if (dx) {
            const n = pasosPorCompas(this.score.timeSignature, ticksDeResolucion(this.resolucion));
            let { paso, measureIdx, sectionIdx } = g;
            paso += dx;
            if (paso < 0) {
                if (measureIdx > 0) measureIdx -= 1;
                else if (sectionIdx > 0) { sectionIdx -= 1; measureIdx = this.score.sections[sectionIdx].measures.length - 1; }
                else paso = 0;
                if (paso < 0) paso = n - 1;
            } else if (paso >= n) {
                const sec = this.score.sections[sectionIdx];
                if (measureIdx < sec.measures.length - 1) { measureIdx += 1; paso = 0; }
                else if (sectionIdx < this.score.sections.length - 1) { sectionIdx += 1; measureIdx = 0; paso = 0; }
                else paso = n - 1;
            }
            Object.assign(g, { paso, measureIdx, sectionIdx });
        }
        this.sincronizarDesdeGrilla();
    }

    /** La celda de la grilla elige la nota correspondiente en la partitura (vista híbrida). */
    sincronizarDesdeGrilla() {
        const g = this.gsel;
        if (!g) return;
        const voz = this.score.sections[g.sectionIdx]?.measures[g.measureIdx]?.voces[g.instId] || [];
        this.seleccionar({ sectionIdx: g.sectionIdx, measureIdx: g.measureIdx, instId: g.instId, noteIdx: notaDePaso(voz, g.paso, ticksDeResolucion(this.resolucion)) }, { desdeGrilla: true });
        this.grilla.marcarCursor();
    }

    celdaDe(c) {
        const voz = this.score.sections[c.sectionIdx]?.measures[c.measureIdx]?.voces[c.instId] || [];
        return celdasDeVoz(voz, ticksDeCompas(this.score.timeSignature), ticksDeResolucion(this.resolucion))[c.paso];
    }

    /** Clic / Enter en una celda: si hay golpe lo saca; si no, pone el último golpe usado. */
    grillaClick(c) {
        this.gsel = { ...c };
        this.setFoco('grilla');
        const actual = this.celdaDe(c);
        if (actual?.tipo === 'golpe') this.grillaQuitar(c);
        else this.grillaPoner(c, {});
        this.sincronizarDesdeGrilla();
    }

    grillaPoner(c, { stroke = null, dyn } = {}) {
        if (this.readonly) return;
        const actual = this.celdaDe(c);
        const golpe = stroke || (actual?.tipo === 'golpe' ? actual.nota.stroke : (this.ultimoGolpe?.[c.instId] || null));
        let sonar = golpe;
        this.editar(() => {
            const m = this.score.sections[c.sectionIdx]?.measures[c.measureIdx];
            if (!m) return false;
            const voz = ponerGolpe(m.voces[c.instId], c.paso, ticksDeResolucion(this.resolucion), c.instId, { stroke: golpe || undefined, ...(dyn !== undefined ? { dyn } : {}) });
            if (!voz) {
                this.aviso('Ese paso cae en un grupo irregular: editalo en la partitura.');
                return false;
            }
            m.voces[c.instId] = voz;
            sonar = this.celdaDe(c)?.nota?.stroke || golpe;
            return true;
        }, null, { compas: c });
        if (stroke) this.ultimoGolpe = { ...(this.ultimoGolpe || {}), [c.instId]: stroke };
        const nota = this.celdaDe(c)?.nota;
        if (nota) this.audio.golpe(c.instId, sonar || nota.stroke, 0, velocidadPreview(nota), this.score).catch(() => {});
    }

    grillaQuitar(c) {
        if (this.readonly) return;
        this.editar(() => {
            const m = this.score.sections[c.sectionIdx]?.measures[c.measureIdx];
            const voz = m && quitarGolpe(m.voces[c.instId], c.paso, ticksDeResolucion(this.resolucion));
            if (!voz) return false;
            m.voces[c.instId] = voz;
            return true;
        }, null, { compas: c });
    }

    grillaMenu(c, x, y) {
        this.gsel = { ...c };
        this.setFoco('grilla');
        this.grilla.marcarCursor();
        menuGolpe(this, c, x, y);
    }

    /** Tecla de instrumento (A S D F…): en la grilla escribe y avanza; en modo tocar suena (y graba). */
    golpeDeInstrumento(i, tecla) {
        const cfg = this.instrumentosVisibles()[i];
        if (!cfg) return;
        const def = instrumentoPorId(cfg.id);
        const stroke = this.ultimoGolpe?.[cfg.id] || golpesDe(cfg.id)[0]?.id || 'nota';
        feedbackTecla(this, this.atajos.acciones.get(`inst-${i + 1}`)?.teclas[0] || tecla, def?.label || cfg.id);
        if (this.tocando) return this.tocarGolpe(cfg.id, stroke, 1);
        if (!this.gsel) this.gselInicial();
        const c = { ...this.gsel, instId: cfg.id };
        this.gsel = c;
        this.grillaPoner(c, { stroke });
        if (this.entrada) this.moverGrilla(1, 0);
        else this.sincronizarDesdeGrilla();
    }

    /** Golpe N del instrumento (Q W E T Y U): cambia el golpe de la nota o celda actual. */
    golpePorIndice(i) {
        if (this.foco === 'grilla' && this.vista !== 'partitura') {
            if (!this.gsel) this.gselInicial();
            const g = golpesDe(this.gsel.instId)[i];
            if (!g) return;
            this.grillaPoner(this.gsel, { stroke: g.id });
            if (this.entrada) this.moverGrilla(1, 0);
            return;
        }
        if (!this.sel) return;
        const g = golpesDe(this.sel.instId)[i];
        if (!g) return;
        this.editar(() => ops.setGolpe(this.score, this.sel, g.id), g.id);
        if (this.entrada) this.mover(1);
    }

    borrarActual() {
        if (this.foco === 'grilla' && this.vista !== 'partitura') return this.gsel && this.grillaQuitar(this.gsel);
        return this.editar(() => ops.borrar(this.score, this.sel));
    }

    pasoDinamica(delta) {
        if (this.foco === 'grilla' && this.vista !== 'partitura' && this.gsel) {
            const c = this.celdaDe(this.gsel);
            if (c?.tipo !== 'golpe') return;
            this.sel = { sectionIdx: this.gsel.sectionIdx, measureIdx: this.gsel.measureIdx, instId: this.gsel.instId, noteIdx: c.noteIdx };
        }
        if (!this.sel) return;
        const compas = { sectionIdx: this.sel.sectionIdx, measureIdx: this.sel.measureIdx };
        this.editar(() => ops.pasoDinamica(this.score, this.sel, delta), null, { compas });
        const nota = notaDe(this.score, this.sel);
        if (nota && !nota.rest) {
            this.audio.golpe(this.sel.instId, nota.stroke, 0, velocidadPreview(nota), this.score).catch(() => {});
            this.aviso(`Dinámica: ${nota.dyn || 'mf'}`);
        }
    }

    /** ------------------------------------------------------------- compases: rango, copiar, repetir */

    posicionActual() {
        return (this.foco === 'grilla' && this.gsel) ? this.gsel : this.sel;
    }

    extenderRango(delta) {
        const pos = this.posicionActual();
        if (!pos) return;
        const n = this.score.sections[pos.sectionIdx].measures.length;
        if (!this.rango || this.rango.sectionIdx !== pos.sectionIdx) {
            this.rango = { sectionIdx: pos.sectionIdx, desde: pos.measureIdx, hasta: pos.measureIdx };
        }
        this.rango.hasta = Math.max(0, Math.min(n - 1, this.rango.hasta + delta));
        this.marcarRango();
        this.pintarStatus();
    }

    rangoActual() {
        if (this.rango) {
            return { sectionIdx: this.rango.sectionIdx, desde: Math.min(this.rango.desde, this.rango.hasta), hasta: Math.max(this.rango.desde, this.rango.hasta) };
        }
        const pos = this.posicionActual();
        return pos ? { sectionIdx: pos.sectionIdx, desde: pos.measureIdx, hasta: pos.measureIdx } : null;
    }

    marcarRango() {
        this.grilla?.marcarRango();
        this.root.querySelectorAll('.pt-rango-box').forEach((b) => b.remove());
        const r = this.rango;
        if (!r) return;
        this.measureBoxes.filter((b) => b.sectionIdx === r.sectionIdx && b.measureIdx >= Math.min(r.desde, r.hasta) && b.measureIdx <= Math.max(r.desde, r.hasta))
            .forEach((b) => {
                const el = document.createElement('div');
                el.className = 'pt-rango-box';
                Object.assign(el.style, { left: `${b.x}px`, top: `${b.y}px`, width: `${b.w}px`, height: `${b.h}px` });
                b.lineEl.appendChild(el);
            });
    }

    copiar() {
        const r = this.rangoActual();
        if (!r) return;
        this.clip = ops.copiarCompases(this.score, r.sectionIdx, r.desde, r.hasta);
        try { localStorage.setItem('pt-clip', JSON.stringify(this.clip)); } catch { /* sin almacenamiento */ }
        const n = this.clip.measures.length;
        this.aviso(n === 1 ? 'Compás copiado' : `${n} compases copiados`);
    }

    pegar() {
        let clip = this.clip;
        if (!clip) {
            try { clip = JSON.parse(localStorage.getItem('pt-clip') || 'null'); } catch { clip = null; }
        }
        const pos = this.posicionActual();
        if (!clip || !pos) return this.aviso('No hay nada copiado.');
        this.editar(() => ops.pegarCompases(this.score, pos.sectionIdx, pos.measureIdx, clip));
        this.aviso(`Pegado desde el compás ${pos.measureIdx + 1}`);
    }

    /** Repite el compás (o la selección) `veces` veces a continuación. */
    repetirSeleccion(veces = 1) {
        const r = this.rangoActual();
        if (!r || veces < 1) return;
        this.editar(() => ops.repetirCompases(this.score, r.sectionIdx, r.desde, r.hasta, veces));
        const n = (r.hasta - r.desde + 1) * veces;
        this.aviso(`Se agregaron ${n} compás${n === 1 ? '' : 'es'}`);
    }

    /** ------------------------------------------------------------- tocar, grabar, MIDI */

    toggleTocar() {
        this.tocando = !this.tocando;
        const b = this.root.querySelector('[data-a="tocar"]');
        b?.classList.toggle('on', this.tocando);
        b?.setAttribute('aria-pressed', String(this.tocando));
        this.root.classList.toggle('pt-tocando', this.tocando);
        if (this.tocando) {
            this.audio.asegurarContexto().then(() => this.audio.precargarSamples(this.score)).catch(() => {});
            const teclas = this.instrumentosVisibles().slice(0, 8).map((cfg, i) => `${teclaLegible(this.atajos.acciones.get(`inst-${i + 1}`)?.teclas[0] || '')} ${instrumentoPorId(cfg.id)?.short || cfg.id}`).join(' · ');
            this.aviso(`Modo tocar: ${teclas}. P o Esc para salir.`);
        } else if (this.grabando) {
            this.audio.stop();
        }
        this.pintarStatus();
    }

    tocarGolpe(instId, stroke, vel) {
        this.audio.golpe(instId, stroke, 0, vel, this.score).catch(() => {});
        if (this.grabando && this.audio.playing) {
            const seg = this.audio.musicalAhora() - (this.audio._countInSec || 0);
            if (seg >= -0.15) this.grabados.push({ seg: Math.max(0, seg), instId, stroke });
        }
    }

    toggleGrabar() {
        if (this.grabando) {
            this.audio.stop();
            return;
        }
        if (!this.tocando) this.toggleTocar();
        this.grabando = true;
        this.grabados = [];
        const b = this.root.querySelector('[data-a="grabar"]');
        b?.classList.add('on');
        b?.setAttribute('aria-pressed', 'true');
        if (!this.countInCompases) this.countInCompases = 1;
        this.play();
        this.aviso('Grabando: tocá con las teclas de cada instrumento. Stop para terminar.');
        this.pintarStatus();
    }

    /** Al parar: cuantiza lo grabado a la grilla y lo escribe (un solo paso de deshacer). */
    terminarGrabacion() {
        this.grabando = false;
        const b = this.root.querySelector('[data-a="grabar"]');
        b?.classList.remove('on');
        b?.setAttribute('aria-pressed', 'false');
        const golpes = this.grabados;
        this.grabados = [];
        if (!golpes.length) return this.pintarStatus();
        const paso = ticksDeResolucion(this.resolucion);
        const posiciones = cuantizar(golpes, {
            bpm: this.score.tempo,
            timeSignature: this.score.timeSignature,
            paso,
            compases: (this.audio._measureStarts || []).map((m) => ({ sectionIdx: m.sectionIdx, measureIdx: m.measureIdx })),
        });
        this.editar(() => {
            let n = 0;
            posiciones.forEach((p) => {
                const m = this.score.sections[p.sectionIdx]?.measures[p.measureIdx];
                const voz = m && ponerGolpe(m.voces[p.instId], p.paso, paso, p.instId, { stroke: p.stroke });
                if (voz) { m.voces[p.instId] = voz; n += 1; }
            });
            this._grabadosEscritos = n;
            return n > 0;
        });
        this.aviso(`Se escribieron ${this._grabadosEscritos || 0} golpes (ajustados a ${RESOLUCIONES.find((r) => r.id === this.resolucion)?.label.toLowerCase()}). Ctrl+Z deshace.`);
    }

    async toggleMidi() {
        const b = this.root.querySelector('[data-a="midi-in"]');
        if (this._midi) {
            this._midi.detener();
            this._midi = null;
            b?.classList.remove('on');
            return this.aviso('MIDI desconectado');
        }
        try {
            this._midi = await escucharMIDI((note, vel) => {
                const ids = this.score.instruments.map((i) => i.id);
                const m = instrumentoDeMidi(note, ids);
                if (!m || !ids.includes(m.instId)) return;
                const velocidad = Math.max(0.1, vel / 100);
                if (this.tocando) return this.tocarGolpe(m.instId, m.stroke, velocidad);
                if (!this.gsel) this.gselInicial();
                const c = { ...this.gsel, instId: m.instId };
                this.gsel = c;
                this.grillaPoner(c, { stroke: m.stroke });
                if (this.entrada) this.moverGrilla(1, 0);
            });
            b?.classList.add('on');
            this.aviso(this._midi.dispositivos.length ? `MIDI: ${this._midi.dispositivos.join(', ')}` : 'MIDI activo (conectá un dispositivo)');
        } catch (err) {
            this.aviso(err.message);
        }
    }

    async importarMidi(file) {
        if (!file || this.readonly) return;
        if (this.el.inputMidi) this.el.inputMidi.value = '';
        try {
            const bytes = new Uint8Array(await file.arrayBuffer());
            const score = scoreDesdeMIDI(bytes, {
                titulo: this.score.title,
                preferidos: this.score.instruments.map((i) => i.id),
                paso: ticksDeResolucion(this.resolucion),
            });
            this.aplicarScoreImportado(score);
            this.aviso('MIDI importado y ajustado a la grilla. Revisá y publicá.');
        } catch (err) {
            this.aviso(err.message || 'No se pudo leer el MIDI.');
        }
    }

    async exportarWav() {
        this.cerrarMenus();
        this.aviso('Generando el audio…');
        try {
            const blob = await this.audio.renderizarWav(this.score);
            descargarBlob(blob, `${this.slug || 'toque'}.wav`);
            this.aviso('Audio listo.');
        } catch (err) {
            this.aviso(`WAV: ${err.message}`);
        }
    }

    /** Antes de exportar PDF/PNG: la partitura tiene que estar dibujada aunque se vea la grilla. */
    asegurarPartitura() {
        this.cerrarMenus();
        if (this.vista === 'grilla') this.renderPartitura();
    }

    /** Seña del compás que suena, grande (útil en ensayo y clase). */
    mostrarSena(pos) {
        const el = this.el.sena;
        if (!el) return;
        const m = pos && !pos.countIn ? this.score.sections[pos.sectionIdx]?.measures[pos.measureIdx] : null;
        const clave = m ? `${pos.sectionIdx}:${pos.measureIdx}` : null;
        if (clave === this._senaClave) return;
        this._senaClave = clave;
        if (!m?.sena) { el.hidden = true; return; }
        const inst = m.sena.instrumento ? instrumentoPorId(m.sena.instrumento)?.label : null;
        el.innerHTML = `<span class="pt-sena-mano" aria-hidden="true">✋</span><strong>${esc(m.sena.texto)}</strong>${inst ? `<small>${esc(inst)}</small>` : ''}`;
        el.dataset.tipo = m.sena.tipo;
        el.hidden = false;
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

function botonesFigura(grupo) {
    return HERRAMIENTAS_FIGURA.filter((h) => h.grupo === grupo)
        .map((h) => `<button type="button" class="pt-fig-btn" data-fig="${h.id}" title="${attr(h.label)} = ${h.tiempos}${h.tecla ? ` (${h.tecla})` : ''}">
            ${iconoHerramienta(h)}<small>${h.tiempos}</small>
        </button>`)
        .join('');
}

function iconoHerramienta(h) {
    if (h.kind === 'silencio') return silencioSvg(h.dur);
    if (h.kind === 'grupo') return grupoSvg(h.dur, h.count);
    if (h.kind === 'tuplet') return grupoSvg(h.dur, Math.min(3, h.num), { tuplet: `${h.num}` });
    return figuraSvg(h.dur, h.dots || 0);
}

function figuraSvg(code, dots = 0) {
    const fill = code !== 'w' && code !== 'h';
    const oval = fill
        ? '<ellipse cx="9.2" cy="8.4" rx="5.1" ry="3.35" transform="rotate(-22 9.2 8.4)" fill="currentColor"/>'
        : '<ellipse cx="9.2" cy="8.4" rx="5.1" ry="3.35" transform="rotate(-22 9.2 8.4)" fill="none" stroke="currentColor" stroke-width="1.35"/>';
    const stem = code === 'w' ? '' : '<path d="M4.35 9.1 V24.2" stroke="currentColor" stroke-width="1.25" fill="none"/>';
    const flags = {
        8: '<path d="M4.35 24.2 C8.8 22.2 11.2 19.6 10.4 16.4" fill="none" stroke="currentColor" stroke-width="1.2"/>',
        16: '<path d="M4.35 24.2 C8.8 22.2 11.2 19.6 10.4 16.4M4.35 21.4 C8.4 19.6 10.6 17.4 10 14.8" fill="none" stroke="currentColor" stroke-width="1.2"/>',
        32: '<path d="M4.35 24.2 C8.8 22.2 11.2 19.6 10.4 16.4M4.35 21.4 C8.4 19.6 10.6 17.4 10 14.8M4.35 18.6 C8 17 9.9 15.2 9.5 13.2" fill="none" stroke="currentColor" stroke-width="1.15"/>',
    };
    const dot = dots ? '<circle cx="16.6" cy="8.4" r="1.35" fill="currentColor"/>' : '';
    const inner = oval + stem + (flags[code] || '') + dot;
    const w = dots ? 20 : 16;
    return `<svg class="pt-fig" viewBox="0 0 ${w} 28" width="${dots ? 17 : 14}" height="22" aria-hidden="true">${inner}</svg>`;
}

function silencioSvg(dur) {
    const inner = {
        w: '<rect x="3.5" y="7.2" width="10" height="3.6" fill="currentColor"/>',
        h: '<rect x="3.5" y="11.6" width="10" height="3.6" fill="currentColor"/>',
        q: '<path d="M8.2 5.2 C11.8 8.4 6.4 11.2 9.8 14.6 C6.2 13.2 5.4 16.8 8.8 19.6 C5.2 18 6.6 22.8 10.4 24.4" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M10.4 24.4 C8.2 26.2 6.4 25.2 6.8 23.6" fill="currentColor"/>',
        8: '<path d="M11.2 6.4 C7.2 9.6 7.6 13.2 11 14.2 L6.4 24.4" fill="none" stroke="currentColor" stroke-width="1.3"/><circle cx="10.4" cy="14.2" r="1.7" fill="currentColor"/>',
        16: '<path d="M11.4 5.2 C7.4 8.2 7.8 11.4 11.2 12.4 M11.2 10.6 C7.2 13.6 7.6 16.6 11 17.6 L6.2 24.6" fill="none" stroke="currentColor" stroke-width="1.25"/><circle cx="10.6" cy="12.4" r="1.5" fill="currentColor"/><circle cx="10.4" cy="17.6" r="1.5" fill="currentColor"/>',
    }[dur] || '<rect x="5" y="12" width="6" height="3" fill="currentColor"/>';
    return `<svg class="pt-fig" viewBox="0 0 16 28" width="14" height="22" aria-hidden="true">${inner}</svg>`;
}

function grupoSvg(dur, count, opts = {}) {
    const n = Math.min(4, Math.max(2, count || 2));
    const gap = 11;
    const w = 8 + gap * (n - 1) + 10;
    const beams = dur === '32' ? 3 : dur === '16' ? 2 : 1;
    let notes = '';
    for (let i = 0; i < n; i++) {
        const x = 8 + i * gap;
        notes += `<ellipse cx="${x + 4.8}" cy="8.4" rx="4.4" ry="2.9" transform="rotate(-22 ${x + 4.8} 8.4)" fill="currentColor"/>`;
        notes += `<path d="M${x} 9.1 V22.4" stroke="currentColor" stroke-width="1.2" fill="none"/>`;
    }
    const x1 = 8;
    const x2 = 8 + (n - 1) * gap;
    let beam = '';
    for (let b = 0; b < beams; b++) {
        const y = 21.4 - b * 2.4;
        beam += `<path d="M${x1} ${y} H${x2}" stroke="currentColor" stroke-width="1.7" stroke-linecap="square"/>`;
    }
    const num = opts.tuplet
        ? `<text x="${(x1 + x2) / 2}" y="27.2" text-anchor="middle" font-size="7.5" font-weight="700" fill="currentColor">${opts.tuplet}</text>`
        : '';
    return `<svg class="pt-fig pt-fig-g" viewBox="0 0 ${w} 28" width="${Math.round(w * 0.9)}" height="22" aria-hidden="true">${notes}${beam}${num}</svg>`;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function attr(s) {
    return esc(s);
}

function leerPref(clave, porDefecto) {
    try { return localStorage.getItem(clave) || porDefecto; } catch { return porDefecto; }
}

function guardarPref(clave, valor) {
    try { localStorage.setItem(clave, valor); } catch { /* sin almacenamiento */ }
}

function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    };
}

function haceCuanto(fecha) {
    const s = Math.round((Date.now() - fecha.getTime()) / 1000);
    if (s < 10) return 'recién';
    if (s < 60) return `hace ${s} s`;
    return `hace ${Math.round(s / 60)} min`;
}

function velocidadPreview(nota) {
    if (nota.vel) return nota.vel / 100;
    const g = GOLPES[nota.stroke] || GOLPES.nota;
    return g.gain || 1;
}

function debounce(fn, ms) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

export default EditorPartitura;
