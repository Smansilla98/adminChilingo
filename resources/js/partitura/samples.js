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
        this.ready = false;
        this.loading = false;
    }

    clave(instId, strokeId) {
        return `${instId}__${strokeId}`;
    }

    urlsDe(instId, strokeId) {
        const base = nombreArchivoSample(instId, strokeId);
        return EXT.map((ext) => `${SAMPLES_BASE}/${base}.${ext}`);
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
                const res = await fetch(url, { method: 'GET' });
                if (!res.ok) continue;
                const arr = await res.arrayBuffer();
                const buf = await ctx.decodeAudioData(arr.slice(0));
                this.buffers.set(key, buf);
                this.missing.delete(key);
                return;
            } catch {
                /* siguiente extensión */
            }
        }
        this.missing.add(key);
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
        if (!buf) return null;
        const amp = Math.min(1.25, Math.max(0.05, 0.95 * vel * plan.velMul));
        if (plan.flam) {
            this._oneshot(ctx, out, buf, t - 0.032, amp * 0.4, false);
        }
        return this._oneshot(ctx, out, buf, t, amp, plan.choke);
    }

    _oneshot(ctx, out, buf, t, amp, choke) {
        const src = ctx.createBufferSource();
        src.buffer = buf;
        const g = ctx.createGain();
        const start = Math.max(0, t);
        g.gain.setValueAtTime(0, start);
        g.gain.linearRampToValueAtTime(amp, start + 0.002);
        if (choke) {
            g.gain.exponentialRampToValueAtTime(0.0008, start + 0.08);
        }
        src.connect(g).connect(out);
        src.start(start);
        return src;
    }
}

export const bancoSamples = new BancoSamples();
