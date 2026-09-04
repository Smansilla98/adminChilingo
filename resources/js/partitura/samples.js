/**
 * Banco de samples reales: AudioBuffer por (instrumento, articulación).
 * Sin fallback a otra articulación ni pitch MIDI: la correspondencia es 1:1.
 */
import { MAPA_SAMPLES, nombreArchivoSample } from './instruments.js';

const EXT = ['wav', 'mp3', 'ogg'];
export const SAMPLES_BASE = '/sounds/perc';

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
        instIds.forEach((instId) => {
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
     * Dispara el sample exacto. Sin sample → false (no sustituye otra articulación).
     * @returns {AudioBufferSourceNode|null}
     */
    disparar(ctx, out, instId, strokeId, t, vel = 1) {
        const buf = this.obtener(instId, strokeId);
        if (!buf) return null;
        const src = ctx.createBufferSource();
        src.buffer = buf;
        const g = ctx.createGain();
        const amp = Math.min(1.2, Math.max(0.05, 0.95 * vel));
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(amp, t + 0.004);
        g.gain.exponentialRampToValueAtTime(0.0008, t + Math.max(0.08, buf.duration));
        src.connect(g).connect(out);
        src.start(t);
        return src;
    }
}

export const bancoSamples = new BancoSamples();
