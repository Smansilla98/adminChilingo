/**
 * Panel «Grabación» (estilo Klangio): escuchar la grabación original del toque con el
 * cursor de la partitura sincronizado, alternar grabación / sonido sintetizado / ambos,
 * cambiar la velocidad sin cambiar la afinación, repetir un tramo A-B por compases,
 * marcar a mano dónde cae el primer tiempo y (si el servicio está configurado)
 * pedir la transcripción automática de la percusión.
 *
 * Lo usan el editor y el visor. No depende de VexFlow: recibe callbacks.
 */
import { clonar, expandirTimeline, segundosDeTicks } from './model.js';
import {
    ajustarInicio, posicionEnGrabacion, pulsoDeTiempo, pulsosDeCompas, tempoDeGrabacion,
    tiempoDeCompas, ticksDePulso,
} from './sincro.js';

const VELOCIDADES = [50, 60, 70, 75, 80, 90, 100, 110, 120, 130, 150];
const POLL_MS = 3000;

export class PanelGrabacion {
    /**
     * @param {HTMLElement} host
     * @param {{
     *   score: () => object,
     *   motor: import('./audio.js').MotorAudio,
     *   audioBase?: string|null,
     *   puedeEditar?: boolean,
     *   subirUrl?: string|null,
     *   editorNombre?: () => string,
     *   onCambioRef?: (ref: object|null) => void,
     *   onPos?: (pos: object) => void,
     *   onFin?: () => void,
     *   aviso?: (msg: string) => void,
     *   transcribir?: { url: string, habilitado: boolean, mensaje?: string }|null,
     *   onTranscripcion?: (t: object) => void,
     * }} opts
     */
    constructor(host, opts) {
        this.host = host;
        this.opts = opts;
        this.audio = new Audio();
        this.audio.preload = 'metadata';
        this.audio.preservesPitch = true;
        this.audio.mozPreservesPitch = true;
        this.audio.webkitPreservesPitch = true;
        this.modo = 'grabacion';
        this.velocidad = 100;
        this.loopAB = null; // { desde, hasta } órdenes de compás (0 = primero)
        this._raf = 0;
        this._sintetico = false;
        this.audio.addEventListener('ended', () => this.parar());
        this.audio.addEventListener('loadedmetadata', () => this.pintarTiempo());
        this.audio.addEventListener('error', () => {
            if (this.urlActual) this.aviso('No se pudo abrir la grabación.');
        });
        this.construir();
        this.cargar();
    }

    get score() {
        return this.opts.score();
    }

    get ref() {
        return this.score.audio_ref || null;
    }

    aviso(msg) {
        if (this.opts.aviso) this.opts.aviso(msg);
    }

    urlDe(ref) {
        if (!ref) return null;
        if (ref.url) return ref.url;
        if (ref.path && this.opts.audioBase) return `${this.opts.audioBase.replace(/\/$/, '')}/${encodeURIComponent(ref.path.split('/').pop())}`;
        return null;
    }

    construir() {
        const t = this.opts.transcribir;
        this.host.classList.add('pt-grab');
        this.host.innerHTML = `
            <div class="pt-grab-head">
                <strong>Grabación</strong>
                <span class="pt-grab-nombre" data-g="nombre">Sin grabación</span>
                <span class="pt-grab-tiempo" data-g="tiempo"></span>
            </div>
            <div class="pt-grab-row">
                <button class="pt-btn" data-g-a="play" title="Escuchar la grabación con la partitura">▶ Escuchar</button>
                <button class="pt-btn" data-g-a="stop" title="Detener">■</button>
                <input class="pt-grab-barra" type="range" min="0" max="1000" value="0" data-g="barra" aria-label="Posición de la grabación">
            </div>
            <div class="pt-grab-row">
                <label class="pt-field"><span>Escuchar</span>
                    <select class="pt-input pt-select" data-g="modo" aria-label="Qué se escucha">
                        <option value="grabacion">Grabación</option>
                        <option value="sintetizado">Sonido sintetizado</option>
                        <option value="ambos">Ambos</option>
                    </select>
                </label>
                <label class="pt-field" title="Más lento o más rápido, sin cambiar la afinación"><span>Velocidad</span>
                    <select class="pt-input pt-select" data-g="velocidad" aria-label="Velocidad">
                        ${VELOCIDADES.map((v) => `<option value="${v}" ${v === 100 ? 'selected' : ''}>${v}%</option>`).join('')}
                    </select>
                </label>
            </div>
            <div class="pt-grab-row">
                <label class="pt-check"><input type="checkbox" data-g="loop"> Repetir desde el compás</label>
                <input class="pt-input pt-input-num" type="number" min="1" value="1" data-g="desde" aria-label="Desde el compás">
                <span>hasta</span>
                <input class="pt-input pt-input-num" type="number" min="1" value="4" data-g="hasta" aria-label="Hasta el compás">
            </div>
            ${this.opts.puedeEditar ? `
            <div class="pt-grab-row">
                <button class="pt-btn" data-g-a="inicio" title="Pausá justo en el primer tiempo y tocá acá">Ajustar inicio aquí</button>
                <button class="pt-btn" data-g-a="subir" title="MP3, WAV, M4A u OGG, hasta 50 MB">Subir grabación</button>
                <input type="file" hidden data-g="archivo" accept=".mp3,.wav,.m4a,.ogg,audio/*">
            </div>` : ''}
            ${t ? `
            <div class="pt-grab-row pt-grab-transcribir">
                <button class="pt-btn pt-btn-primary" data-g-a="transcribir" ${t.habilitado ? '' : 'disabled'}
                    title="Escribe un borrador de la partitura a partir de la grabación">Transcribir desde audio</button>
                <span class="pt-grab-estado" data-g="estado">${t.habilitado ? '' : esc(t.mensaje || 'La transcripción automática no está disponible.')}</span>
                <progress max="100" value="0" data-g="progreso" hidden></progress>
            </div>` : ''}
            <p class="pt-grab-ayuda">El cursor sigue a la grabación. Si arranca corrido, pausá justo en el primer golpe del toque y tocá «Ajustar inicio aquí».</p>
        `;
        this.el = {};
        this.host.querySelectorAll('[data-g]').forEach((n) => { this.el[n.dataset.g] = n; });
        this.host.addEventListener('click', (e) => {
            const b = e.target.closest('[data-g-a]');
            if (!b) return;
            const a = b.dataset.gA;
            if (a === 'play') this.toggle();
            if (a === 'stop') this.parar();
            if (a === 'inicio') this.marcarInicio();
            if (a === 'subir') this.el.archivo?.click();
            if (a === 'transcribir') this.transcribir();
        });
        this.el.modo.addEventListener('change', () => this.setModo(this.el.modo.value));
        this.el.velocidad.addEventListener('change', () => this.setVelocidad(Number(this.el.velocidad.value)));
        const loop = () => this.setLoop();
        this.el.loop.addEventListener('change', loop);
        this.el.desde.addEventListener('change', loop);
        this.el.hasta.addEventListener('change', loop);
        this.el.barra.addEventListener('input', () => {
            if (!this.audio.duration) return;
            this.buscar((Number(this.el.barra.value) / 1000) * this.audio.duration);
        });
        this.el.archivo?.addEventListener('change', (e) => this.subir(e.target.files?.[0]));
    }

    /** Vuelve a leer audio_ref (por ejemplo, después de deshacer o importar). */
    cargar() {
        const ref = this.ref;
        const url = this.urlDe(ref);
        this.el.nombre.textContent = ref ? (ref.nombre || 'Grabación de referencia') : 'Sin grabación: subí un audio del toque';
        this.host.querySelector('[data-g-a="play"]').disabled = !url;
        if (url !== this.urlActual) {
            this.parar();
            this.urlActual = url;
            if (url) this.audio.src = url;
            else this.audio.removeAttribute('src');
        }
        const total = expandirTimeline(this.score).length;
        this.el.desde.max = String(total);
        this.el.hasta.max = String(total);
        this.pintarTiempo();
    }

    setModo(m) {
        this.modo = ['grabacion', 'sintetizado', 'ambos'].includes(m) ? m : 'grabacion';
        if (this.sonando) this.reanclarSintetico();
    }

    setVelocidad(v) {
        this.velocidad = Math.min(150, Math.max(50, v || 100));
        this.audio.playbackRate = this.velocidad / 100;
        if (this.sonando) this.reanclarSintetico();
    }

    setLoop() {
        if (!this.el.loop.checked) {
            this.loopAB = null;
            return;
        }
        const total = expandirTimeline(this.score).length;
        const a = Math.min(total, Math.max(1, parseInt(this.el.desde.value, 10) || 1));
        const b = Math.min(total, Math.max(a, parseInt(this.el.hasta.value, 10) || a));
        this.el.desde.value = String(a);
        this.el.hasta.value = String(b);
        this.loopAB = { desde: a - 1, hasta: b - 1 };
        if (this.sonando) this.buscar(tiempoDeCompas(this.score, this.loopAB.desde));
    }

    get sonando() {
        return !!this._sonando;
    }

    async toggle() {
        if (this.sonando) return this.pausar();
        return this.tocar();
    }

    async tocar() {
        if (!this.urlActual) return this.aviso('Primero subí una grabación.');
        if (this.loopAB) {
            const t0 = tiempoDeCompas(this.score, this.loopAB.desde);
            const t1 = tiempoDeCompas(this.score, this.loopAB.hasta + 1);
            if (this.audio.currentTime < t0 - 0.05 || this.audio.currentTime >= t1) this.audio.currentTime = Math.max(0, t0);
        }
        this.audio.playbackRate = this.velocidad / 100;
        this.audio.muted = this.modo === 'sintetizado';
        try {
            await this.audio.play();
        } catch {
            return this.aviso('El navegador no dejó reproducir la grabación.');
        }
        this._sonando = true;
        this.host.querySelector('[data-g-a="play"]').classList.add('on');
        await this.reanclarSintetico();
        this.reloj();
    }

    pausar() {
        this.audio.pause();
        this._sonando = false;
        this.pararSintetico();
        cancelAnimationFrame(this._raf);
        this.host.querySelector('[data-g-a="play"]')?.classList.remove('on');
    }

    parar() {
        this.pausar();
        try { this.audio.currentTime = 0; } catch { /* sin audio cargado */ }
        if (this.opts.onFin) this.opts.onFin();
        this.pintarTiempo();
    }

    buscar(t) {
        this.audio.currentTime = Math.max(0, t);
        this.pintarTiempo();
        if (this.sonando) this.reanclarSintetico();
    }

    /**
     * El sonido sintetizado sigue a la grabación: arranca en el mismo pulso, al tempo
     * de la grabación por la velocidad elegida. Se re-ancla al buscar o cambiar velocidad.
     */
    async reanclarSintetico() {
        this.pararSintetico();
        this.audio.muted = this.modo === 'sintetizado';
        if (this.modo === 'grabacion' || !this.sonando) return;
        const score = clonar(this.score);
        const bpmBase = tempoDeGrabacion(this.score) || this.score.tempo || 100;
        score.tempo = Math.min(260, Math.max(30, Math.round(bpmBase * (this.velocidad / 100))));
        const pulso = Math.max(0, pulsoDeTiempo(this.score, this.audio.currentTime));
        const offsetSec = segundosDeTicks(pulso * ticksDePulso(score.timeSignature), score.tempo);
        try {
            this._sintetico = true;
            const onStop = this.opts.motor.onStop;
            const onClock = this.opts.motor.onClock;
            this.opts.motor.onStop = null;
            this.opts.motor.onClock = null;
            this._restaurar = () => {
                this.opts.motor.onStop = onStop;
                this.opts.motor.onClock = onClock;
            };
            await this.opts.motor.play(score, { offsetSec, countIn: false });
        } catch {
            this.aviso('No se pudo cargar el sonido sintetizado.');
        }
    }

    pararSintetico() {
        if (!this._sintetico) return;
        this._sintetico = false;
        this.opts.motor.stop();
        if (this._restaurar) this._restaurar();
        this._restaurar = null;
    }

    reloj() {
        cancelAnimationFrame(this._raf);
        const timeline = expandirTimeline(this.score);
        let ultimoAncla = performance.now();
        const paso = () => {
            if (!this.sonando) return;
            const t = this.audio.currentTime;
            if (this.loopAB) {
                const fin = tiempoDeCompas(this.score, this.loopAB.hasta + 1);
                if (t >= fin) {
                    this.buscar(tiempoDeCompas(this.score, this.loopAB.desde));
                    this._raf = requestAnimationFrame(paso);
                    return;
                }
            }
            const pos = posicionEnGrabacion(this.score, t, timeline);
            if (this.opts.onPos && pos.sectionIdx !== null) {
                this.opts.onPos({ sectionIdx: pos.sectionIdx, measureIdx: pos.measureIdx, frac: pos.frac, countIn: false });
            }
            // El sintetizado y el audio se desfasan con los tempos variables: re-ancla cada 8 s.
            if (this.modo !== 'grabacion' && performance.now() - ultimoAncla > 8000) {
                ultimoAncla = performance.now();
                this.reanclarSintetico();
            }
            this.pintarTiempo(pos);
            this._raf = requestAnimationFrame(paso);
        };
        this._raf = requestAnimationFrame(paso);
    }

    pintarTiempo(pos = null) {
        const d = this.audio.duration;
        const t = this.audio.currentTime || 0;
        if (Number.isFinite(d) && d > 0 && document.activeElement !== this.el.barra) {
            this.el.barra.value = String(Math.round((t / d) * 1000));
        }
        const p = pos || (this.ref ? posicionEnGrabacion(this.score, t) : null);
        const compas = p && !p.antes && !p.despues ? ` · compás ${p.orden + 1}` : '';
        this.el.tiempo.textContent = Number.isFinite(d) && d > 0 ? `${mmss(t)} / ${mmss(d)}${compas}` : '';
    }

    marcarInicio() {
        const ref = this.ref;
        if (!ref) return this.aviso('Primero subí una grabación.');
        const nuevo = ajustarInicio(ref, this.audio.currentTime || 0);
        if (this.opts.onCambioRef) this.opts.onCambioRef(nuevo);
        this.aviso(`El primer tiempo queda en ${mmss(nuevo.offset_seg, true)}.`);
        this.pintarTiempo();
    }

    async subir(file) {
        if (!file || !this.opts.subirUrl) return;
        if (file.size > 50 * 1024 * 1024) return this.aviso('La grabación pasa de 50 MB. Cortala o pasala a MP3.');
        const nombre = (this.opts.editorNombre?.() || '').trim();
        if (nombre.length < 2) return this.aviso('Indicá tu nombre antes de subir.');
        const fd = new FormData();
        fd.append('audio', file);
        fd.append('editor_nombre', nombre);
        this.aviso('Subiendo grabación…');
        try {
            const res = await fetch(this.opts.subirUrl, { method: 'POST', body: fd, headers: cabeceras() });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) throw new Error(data.message || data.error || `HTTP ${res.status}`);
            if (this.opts.onCambioRef) this.opts.onCambioRef(data.audio_ref);
            this.cargar();
            this.aviso('Grabación lista. Escuchala y ajustá el inicio si hace falta.');
        } catch (err) {
            this.aviso(`No se pudo subir: ${err.message}`);
        } finally {
            if (this.el.archivo) this.el.archivo.value = '';
        }
    }

    async transcribir() {
        const t = this.opts.transcribir;
        if (!t?.habilitado) return;
        if (!this.ref?.path) return this.aviso('Subí primero la grabación (archivo, no enlace).');
        const nombre = (this.opts.editorNombre?.() || '').trim();
        this.estado('Enviando…', 0);
        try {
            const res = await fetch(t.url, {
                method: 'POST',
                headers: { ...cabeceras(), 'Content-Type': 'application/json' },
                body: JSON.stringify({ path: this.ref.path, editor_nombre: nombre }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || data.error || `HTTP ${res.status}`);
            this.seguir(data.data);
        } catch (err) {
            this.estado(`No se pudo: ${err.message}`, null);
        }
    }

    seguir(tr) {
        clearTimeout(this._poll);
        if (!tr) return;
        const textos = { pendiente: 'En espera…', procesando: 'Escuchando la grabación…', listo: 'Listo: quedó un borrador para revisar.', error: `No se pudo transcribir. ${tr.error || ''}` };
        this.estado(textos[tr.estado] || tr.estado, tr.estado === 'listo' || tr.estado === 'error' ? null : tr.progreso);
        if (tr.estado === 'listo' || tr.estado === 'error') {
            if (this.opts.onTranscripcion) this.opts.onTranscripcion(tr);
            return;
        }
        this._poll = setTimeout(async () => {
            try {
                const res = await fetch(tr.url, { headers: cabeceras() });
                const data = await res.json();
                this.seguir(data.data);
            } catch {
                this.seguir(tr);
            }
        }, POLL_MS);
    }

    estado(texto, progreso) {
        if (this.el.estado) this.el.estado.textContent = texto;
        if (this.el.progreso) {
            this.el.progreso.hidden = progreso === null || progreso === undefined;
            this.el.progreso.value = Number(progreso) || 0;
        }
    }

    destruir() {
        this.parar();
        clearTimeout(this._poll);
    }
}

/** Compases totales de la grabación según el modelo (para el formulario del loop). */
export function compasesDeGrabacion(score, duracion) {
    if (!score.audio_ref || !duracion) return 0;
    return Math.max(0, Math.floor(pulsoDeTiempo(score, duracion) / pulsosDeCompas(score.timeSignature)));
}

function cabeceras() {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    };
}

function mmss(s, decimas = false) {
    const t = Math.max(0, s || 0);
    const m = Math.floor(t / 60);
    const r = t - m * 60;
    return `${m}:${decimas ? r.toFixed(1).padStart(4, '0') : String(Math.floor(r)).padStart(2, '0')}`;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
