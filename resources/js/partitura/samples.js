/**
 * Cargador multi-sample por (instrumento, golpe).
 * Busca /sounds/perc/{inst}_{stroke}.wav|mp3; si no hay archivo, el MotorAudio usa síntesis + filtros.
 */
import { GOLPES, midiDeGolpe } from './instruments.js';

const EXT = ['wav', 'mp3', 'ogg'];

export class BancoSamples {
    constructor() {
        /** @type {Map<string, AudioBuffer>} */
        this.buffers = new Map();
        /** @type {Set<string>} */
        this.missing = new Set();
        this.ready = false;
    }

    clave(instId, strokeId) {
        return `${instId}__${strokeId}`;
    }

    /**
     * Precarga samples para los instrumentos del score.
     * @param {AudioContext} ctx
     * @param {string[]} instIds
     */
    async precargar(ctx, instIds = []) {
        const strokes = Object.keys(GOLPES);
        const jobs = [];
        instIds.forEach((instId) => {
            strokes.forEach((stroke) => {
                jobs.push(this._cargarUno(ctx, instId, stroke));
            });
        });
        await Promise.allSettled(jobs);
        this.ready = true;
    }

    async _cargarUno(ctx, instId, strokeId) {
        const key = this.clave(instId, strokeId);
        if (this.buffers.has(key) || this.missing.has(key)) return;
        for (const ext of EXT) {
            const url = `/sounds/perc/${instId}_${strokeId}.${ext}`;
            try {
                const res = await fetch(url, { method: 'GET' });
                if (!res.ok) continue;
                const arr = await res.arrayBuffer();
                const buf = await ctx.decodeAudioData(arr.slice(0));
                this.buffers.set(key, buf);
                return;
            } catch {
                /* probar siguiente extensión */
            }
        }
        this.missing.add(key);
    }

    /**
     * @param {string} instId
     * @param {string} strokeId
     * @returns {AudioBuffer|null}
     */
    obtener(instId, strokeId) {
        const exact = this.buffers.get(this.clave(instId, strokeId));
        if (exact) return exact;
        // Fallback: mismo instrumento, golpe "nota" o "abierto"
        for (const alt of ['nota', 'abierto']) {
            const b = this.buffers.get(this.clave(instId, alt));
            if (b) return b;
        }
        return null;
    }

    /**
     * Dispara un sample si existe.
     * @returns {boolean} true si se reprodujo sample
     */
    disparar(ctx, out, instId, strokeId, t, vel = 1) {
        const buf = this.obtener(instId, strokeId);
        if (!buf) return false;
        const src = ctx.createBufferSource();
        src.buffer = buf;
        // Pitch leve según MIDI relativo (mantiene afinación percibida entre técnicas)
        const midi = midiDeGolpe(instId, strokeId);
        const midiBase = midiDeGolpe(instId, strokeId === 'abierto' ? 'abierto' : 'nota');
        const rate = 2 ** ((midi - midiBase) / 12);
        src.playbackRate.value = Math.min(1.6, Math.max(0.7, rate));
        const g = ctx.createGain();
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(Math.min(1.2, 0.95 * vel), t + 0.004);
        g.gain.exponentialRampToValueAtTime(0.0008, t + Math.max(0.12, buf.duration));
        src.connect(g).connect(out);
        src.start(t);
        return true;
    }
}

export const bancoSamples = new BancoSamples();
