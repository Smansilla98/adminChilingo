/**
 * Banco de samples reales: AudioBuffer por (instrumento, articulación).
 *
 * El catálogo MAPA_SAMPLES es 1:1 con los WAV. Los golpes de paleta que no
 * tienen archivo (acento de surdo, flam, tapado de redoblante, etc.) se
 * resuelven a un disparo audible: misma membrana con más velocity, flam de
 * adorno, o choke. Ningún golpe seleccionable termina en silencio.
 */
import { MAPA_SAMPLES, nombreArchivoSample, GOLPES_POR_INSTRUMENTO } from './instruments.js';

const EXT = ['wav', 'mp3', 'ogg'];

/** Origen de los WAV. Preferí ruta same-origin (meta / data-attr) para no ir a APP_URL equivocado. */
export function samplesBase() {
    if (typeof document !== 'undefined') {
        const el = document.querySelector('[data-samples-base]');
        const fromData = el?.getAttribute('data-samples-base');
        if (fromData) return fromData.replace(/\/$/, '');
        const meta = document.querySelector('meta[name="chilinga-samples"]');
        if (meta?.content) return meta.content.replace(/\/$/, '');
    }
    return '/sounds/perc';
}

export const SAMPLES_BASE = '/sounds/perc';

/** Instrumentos sin WAV propio → un sample cercano de la escuela. */
const ALIAS_INST = {};

function strokeBase(instId) {
    return instId === 'timbal' ? 'abierto' : 'nota';
}

/**
 * Resuelve (instrumento, golpe) a un par que existe en MAPA_SAMPLES.
 * @returns {{ instId: string, strokeId: string, velMul: number, flam: boolean, choke: boolean }}
 */
export function resolverGolpe(instId, strokeId) {
    const strokes = MAPA_SAMPLES[instId];
    if (strokes?.includes(strokeId)) {
        return { instId, strokeId, velMul: 1, flam: false, choke: false };
    }

    // PDF: triángulo = golpe agudo. En redoblante no hay WAV (Oxosi).
    // No sustituir por redoblante_normal.
    if (strokeId === 'agudo') {
        return { instId, strokeId: 'agudo', velMul: 1, flam: false, choke: false };
    }

    if (strokeId === 'acentuado') {
        const base = strokeBase(instId);
        if (strokes?.includes(base)) {
            return { instId, strokeId: base, velMul: 1.28, flam: false, choke: false };
        }
    }

    if (strokeId === 'flam') {
        const base = strokes?.includes(strokeBase(instId)) ? strokeBase(instId) : strokes?.[0];
        if (base) return { instId, strokeId: base, velMul: 1, flam: true, choke: false };
    }

    if (strokeId === 'tapado' && strokes?.includes('nota')) {
        return { instId, strokeId: 'nota', velMul: 0.72, flam: false, choke: true };
    }

    if (strokeId === 'nota' && strokes?.includes('abierto')) {
        return { instId, strokeId: 'abierto', velMul: 1, flam: false, choke: false };
    }
    if (strokeId === 'abierto' && strokes?.includes('nota')) {
        return { instId, strokeId: 'nota', velMul: 1, flam: false, choke: false };
    }
    if (strokeId === 'chapa' && strokes?.includes('slap')) {
        return { instId, strokeId: 'slap', velMul: 1, flam: false, choke: false };
    }

    if (ALIAS_INST[instId]) {
        const a = ALIAS_INST[instId];
        const inner = resolverGolpe(a.instId, strokeId);
        if (MAPA_SAMPLES[inner.instId]?.includes(inner.strokeId)) return inner;
        return resolverGolpe(a.instId, a.strokeId);
    }

    if (strokes?.length) {
        return { instId, strokeId: strokes[0], velMul: 0.9, flam: false, choke: false };
    }

    return { instId: 'surdo_grave', strokeId: 'nota', velMul: 0.8, flam: false, choke: false };
}

/** Todo golpe de paleta tiene que resolver a un par con WAV en el catálogo. */
export function golpeEsAudible(instId, strokeId) {
    const r = resolverGolpe(instId, strokeId);
    return !!(MAPA_SAMPLES[r.instId] && MAPA_SAMPLES[r.instId].includes(r.strokeId));
}

export function golpesPaletaAudibles() {
    const pares = [];
    Object.entries(GOLPES_POR_INSTRUMENTO).forEach(([instId, strokes]) => {
        strokes.forEach((stroke) => pares.push({ instId, stroke, ok: golpeEsAudible(instId, stroke) }));
    });
    return pares;
}

export class BancoSamples {
    constructor() {
        /** @type {Map<string, AudioBuffer>} */
        this.buffers = new Map();
        /** @type {Set<string>} */
        this.missing = new Set();
        /** @type {Map<string, { src: AudioBufferSourceNode, gain: GainNode }>} */
        this.voces = new Map();
        this.ready = false;
        this.loading = false;
    }

    clave(instId, strokeId) {
        return `${instId}__${strokeId}`;
    }

    urlsDe(instId, strokeId) {
        const base = nombreArchivoSample(instId, strokeId);
        const root = samplesBase();
        return EXT.map((ext) => `${root}/${base}.${ext}`);
    }

    catalogoRequerido() {
        const pares = [];
        Object.entries(MAPA_SAMPLES).forEach(([instId, strokes]) => {
            strokes.forEach((stroke) => pares.push({ instId, stroke, archivo: nombreArchivoSample(instId, stroke) }));
        });
        return pares;
    }

    /**
     * @param {AudioContext} ctx
     * @param {string[]} [instIds]
     */
    async precargar(ctx, instIds = Object.keys(MAPA_SAMPLES)) {
        this.loading = true;
        const jobs = [];
        const set = new Set(instIds);
        Object.keys(MAPA_SAMPLES).forEach((id) => set.add(id));
        set.forEach((instId) => {
            const strokes = MAPA_SAMPLES[instId];
            if (!strokes) return;
            strokes.forEach((stroke) => jobs.push(this._cargarUno(ctx, instId, stroke)));
        });
        await Promise.allSettled(jobs);
        this.ready = true;
        this.loading = false;
        return this.estado();
    }

    async _cargarUno(ctx, instId, strokeId) {
        const key = this.clave(instId, strokeId);
        if (this.buffers.has(key) || this.missing.has(key)) return;
        for (const url of this.urlsDe(instId, strokeId)) {
            try {
                const res = await fetch(url, { method: 'GET', cache: 'no-cache', credentials: 'same-origin' });
                if (!res.ok) {
                    if (typeof console !== 'undefined') console.warn(`[partitura] ${res.status} ${url}`);
                    continue;
                }
                const tipo = (res.headers.get('content-type') || '').toLowerCase();
                if (tipo.includes('text/html') || tipo.includes('application/json')) {
                    if (typeof console !== 'undefined') console.warn(`[partitura] respuesta ${tipo} en ${url}`);
                    continue;
                }
                const arr = await res.arrayBuffer();
                if (arr.byteLength < 64) continue;
                const buf = await ctx.decodeAudioData(arr.slice(0));
                if (!buf.length) continue;
                this.buffers.set(key, buf);
                this.missing.delete(key);
                return;
            } catch {
                /* siguiente extensión */
            }
        }
        this.missing.add(key);
        if (typeof console !== 'undefined') {
            console.warn(`[partitura] sample no cargó: ${instId} ${strokeId}`, this.urlsDe(instId, strokeId));
        }
    }

    obtener(instId, strokeId) {
        return this.buffers.get(this.clave(instId, strokeId)) || null;
    }

    tiene(instId, strokeId) {
        return this.buffers.has(this.clave(instId, strokeId));
    }

    estado() {
        const req = this.catalogoRequerido();
        const faltan = req.filter(({ instId, stroke }) => this.missing.has(this.clave(instId, stroke)));
        const listos = req.filter(({ instId, stroke }) => this.buffers.has(this.clave(instId, stroke)));
        return { listos: listos.length, faltan: faltan.length, total: req.length, missing: faltan };
    }

    /**
     * Dispara el sample (o su fallback). Ataque corto anti-click; sin fade
     * de salida salvo choke (tapado sin sample propio).
     * @returns {AudioBufferSourceNode|null}
     */
    disparar(ctx, out, instId, strokeId, t, vel = 1) {
        const plan = resolverGolpe(instId, strokeId);
        const buf = this.obtener(plan.instId, plan.strokeId);
        const amp = Math.min(0.85, Math.max(0.04, 0.52 * vel * plan.velMul));
        const voz = plan.instId;
        const start = Number.isFinite(t) ? t : (ctx.currentTime + 0.01);
        if (plan.flam) {
            if (buf) this._oneshot(ctx, out, buf, start - 0.032, amp * 0.35, false, `${voz}__flam`);
        }
        if (!buf) {
            if (typeof console !== 'undefined') {
                console.warn(`[partitura] sin buffer, thump de respaldo: ${plan.instId} ${plan.strokeId}`);
            }
            return this._thump(ctx, out, start, amp, plan.choke, voz);
        }
        return this._oneshot(ctx, out, buf, start, amp, plan.choke, voz);
    }

    _oneshot(ctx, out, buf, t, amp, choke, voz) {
        const start = Math.max(ctx.currentTime, t);
        // Solo el tapado/choke corta la cola anterior. Cortar en cada golpe
        // al programar el timeline dejaba todas las notas en silencio:
        // cancelScheduledValues + setValueAtTime(0.0008) aplastaba el ataque
        // de las notas que todavía no habían sonado.
        if (choke && voz) this._cortarVoz(ctx, voz, start);
        const src = ctx.createBufferSource();
        src.buffer = buf;
        const g = ctx.createGain();
        g.gain.setValueAtTime(0, start);
        g.gain.linearRampToValueAtTime(amp, start + 0.002);
        if (choke) {
            g.gain.exponentialRampToValueAtTime(0.0008, start + 0.07);
        }
        src.connect(g).connect(out);
        try {
            src.start(start);
        } catch (err) {
            if (typeof console !== 'undefined') console.warn('[partitura] start()', err);
            return null;
        }
        if (voz) this.voces.set(voz, { src, gain: g });
        return src;
    }

    /** Respaldo audible si el WAV no cargó: no dejar un golpe mudo. */
    _thump(ctx, out, t, amp, choke, voz) {
        const start = Math.max(ctx.currentTime, t);
        if (choke && voz) this._cortarVoz(ctx, voz, start);
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(92, start);
        osc.frequency.exponentialRampToValueAtTime(48, start + 0.09);
        g.gain.setValueAtTime(0, start);
        g.gain.linearRampToValueAtTime(Math.min(0.45, amp), start + 0.002);
        g.gain.exponentialRampToValueAtTime(0.0008, start + (choke ? 0.06 : 0.14));
        osc.connect(g).connect(out);
        try {
            osc.start(start);
            osc.stop(start + 0.16);
        } catch {
            return null;
        }
        if (voz) this.voces.set(voz, { src: osc, gain: g });
        return osc;
    }

    _cortarVoz(ctx, voz, t) {
        const prev = this.voces.get(voz);
        if (!prev) return;
        this.voces.delete(voz);
        const cut = Math.max(ctx.currentTime + 0.001, t);
        try {
            prev.src.stop(cut);
        } catch {
            /* ya detenida */
        }
    }

    cortarTodas(ctx) {
        const t = ctx ? ctx.currentTime : 0;
        [...this.voces.keys()].forEach((voz) => this._cortarVoz(ctx || { currentTime: t }, voz, t));
        this.voces.clear();
    }
}

export const bancoSamples = new BancoSamples();
