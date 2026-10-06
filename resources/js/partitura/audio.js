/**
 * Sampler de percusión + scheduler sobre AudioContext.currentTime.
 * No usa OscillatorNode para simular tambores. El metrónomo sí es un click sintético.
 */
import { UNISONO, vocesDeUnisono, MAPA_SAMPLES } from './instruments.js';

/** Ganancia de timbre por instrumento (el agogó pincha; el surdo no debe tapar). */
const GANANCIA_TIMBRE = {
    surdo_grave: 0.95,
    surdo_medio: 0.78,
    surdo_agudo: 0.72,
    redoblante: 1.0,
    repique: 0.86,
    timbal: 0.88,
    agogo: 0.38,
    palmas: 0.65,
    todos: 0.85,
};
import {
    TPQ, ticksDeCompas, expandirTimeline, eventosMusicales, segundosDeTicks, esAudible,
    ticksDeNota as ticksDeNotaLocal, velocidadDeNota as velocidadNota,
} from './model.js';
import { bancoSamples } from './samples.js';

/** Dispersión micro determinística en el unísono (ms → s). Evita suma en fase. */
const UNISON_OFFSET = {
    surdo_grave: 0,
    surdo_medio: 0.004,
    surdo_agudo: 0.007,
    redoblante: 0.003,
    repique: 0.006,
    timbal: 0.008,
    agogo: 0.005,
    palmas: 0.002,
};

export class MotorAudio {
    constructor() {
        /** @type {AudioContext|null} */
        this.ctx = null;
        this.master = null;
        this.gains = {};
        this.metronomo = false;
        this.metroGain = 0.5;
        /** Clicks por pulso: 1 negras, 2 corcheas, 4 semicorcheas. */
        this.metroSub = 1;
        /** Acentuar el primer tiempo del compás. */
        this.metroAcento = true;
        this.panners = {};
        this.rates = {};
        this.playing = false;
        this.paused = false;
        this.stopFlag = false;
        this.onClock = null;
        this.onStop = null;
        this.onLoad = null;
        this.onReady = null;
        this._sources = [];
        this._raf = 0;
        this._t0 = 0;
        this._offset = 0;
        this._duration = 0;
        this._measureStarts = [];
        this._countInSec = 0;
        this.estadoSamples = { listos: 0, faltan: 0, total: 0, missing: [] };
    }

    async asegurarContexto() {
        if (!this.ctx) {
            const AC = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AC();
            this.master = this.ctx.createGain();
            this.master.gain.value = 0.7;
            this.limiter = this.ctx.createDynamicsCompressor();
            this.limiter.threshold.value = -8;
            this.limiter.knee.value = 8;
            this.limiter.ratio.value = 3.5;
            this.limiter.attack.value = 0.004;
            this.limiter.release.value = 0.12;
            this.master.connect(this.limiter);
            this.limiter.connect(this.ctx.destination);
        }
        if (this.ctx.state === 'suspended') await this.ctx.resume();
        return this.ctx;
    }

    async precargarSamples(score) {
        await this.asegurarContexto();
        if (this.onLoad) this.onLoad('Cargando sonidos...');
        const ids = (score?.instruments || []).map((i) => i.id).filter((id) => id !== UNISONO && MAPA_SAMPLES[id]);
        const extra = Object.keys(MAPA_SAMPLES);
        const set = [...new Set(ids.length ? ids : extra)];
        this.estadoSamples = await bancoSamples.precargar(this.ctx, set);
        if (this.onReady) this.onReady(this.estadoSamples);
        return this.estadoSamples;
    }

    canalDe(instId) {
        if (!this.gains[instId]) {
            const g = this.ctx.createGain();
            g.gain.value = GANANCIA_TIMBRE[instId] ?? 0.9;
            // Paneo por instrumento (si el navegador lo soporta).
            if (typeof this.ctx.createStereoPanner === 'function') {
                const pan = this.ctx.createStereoPanner();
                g.connect(pan).connect(this.master);
                this.panners[instId] = pan;
            } else {
                g.connect(this.master);
            }
            this.gains[instId] = g;
        }
        return this.gains[instId];
    }

    aplicarMixer(score) {
        if (!this.ctx) return;
        const todos = score.instruments.find((i) => i.id === UNISONO);
        const soloActivo = score.instruments.some((i) => i.solo);
        const soloTodos = !!(todos && todos.solo);
        score.instruments.forEach((i) => {
            const g = this.canalDe(i.id);
            let audible;
            if (!soloActivo) audible = !i.mute;
            else if (i.id === UNISONO) audible = i.solo && !i.mute;
            else if (soloTodos) audible = !i.mute;
            else audible = i.solo && !i.mute;
            const vol = i.id === UNISONO ? (todos?.volume ?? 0.9) : i.volume;
            const timbre = GANANCIA_TIMBRE[i.id] ?? 1;
            g.gain.value = audible ? vol * timbre : 0;
            if (this.panners[i.id]) this.panners[i.id].pan.value = Math.max(-1, Math.min(1, Number(i.pan) || 0));
            this.rates[i.id] = 2 ** ((Number(i.pitch) || 0) / 12);
        });
    }

    async golpe(instId, strokeId, when = 0, velocidad = 1, score = null) {
        if (score) this._score = score;
        await this.asegurarContexto();
        if (!bancoSamples.ready) await bancoSamples.precargar(this.ctx, instId === UNISONO ? undefined : [instId]);
        if (this.ctx.state === 'suspended') await this.ctx.resume();
        const t = when || this.ctx.currentTime + 0.02;
        this._dispararGolpe(instId, strokeId, t, velocidad);
    }

    _dispararGolpe(instId, strokeId, t, velocidad = 1) {
        const destinos = instId === UNISONO ? vocesDeUnisono(this._score) : [instId];
        if (!destinos.length) return null;
        const n = destinos.length;
        const comp = n > 1 ? 1 / Math.sqrt(n) : 1;
        let last = null;
        destinos.forEach((id) => {
            const dt = n > 1 ? (UNISON_OFFSET[id] || 0) : 0;
            const src = bancoSamples.disparar(
                this.ctx, this.canalDe(id), id, strokeId, t + dt, velocidad * comp, this.rates[id] ?? 1,
            );
            if (src) this._sources.push(src);
            last = src || last;
        });
        return last;
    }

    _click(t, fuerte, sub = false, ctx = this.ctx, destino = this.master) {
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = 'square';
        osc.frequency.value = fuerte ? 1600 : sub ? 900 : 1100;
        const nivel = fuerte ? 0.25 : sub ? 0.08 : 0.15;
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(Math.max(0.0005, this.metroGain * nivel), t + 0.001);
        g.gain.exponentialRampToValueAtTime(0.0004, t + 0.045);
        osc.connect(g).connect(destino);
        osc.start(t);
        osc.stop(t + 0.06);
        this._sources.push(osc);
    }

    /**
     * @param {object} score
     * @param {{ desde?: {sectionIdx:number, measureIdx:number}, soloSeccion?: number|null, loop?: boolean, offsetSec?: number, countIn?: boolean }} [opts]
     */
    async play(score, opts = {}) {
        this._score = score;
        await this.asegurarContexto();
        this._cortarFuentes();
        if (this._raf) cancelAnimationFrame(this._raf);
        this._raf = 0;
        this.stopFlag = false;
        this.paused = false;
        await this.precargarSamples(score);
        if (this.ctx.state === 'suspended') await this.ctx.resume();
        this.aplicarMixer(score);

        const plan = this._planificar(score, opts);
        if (!plan.eventos.length && !plan.measureStarts.length) {
            this.playing = false;
            return;
        }

        const lookahead = 0.12;
        this._t0 = this.ctx.currentTime + lookahead - (opts.offsetSec || 0);
        this._offset = opts.offsetSec || 0;
        this._duration = plan.duration;
        this._measureStarts = plan.measureStarts;
        this._countInSec = plan.countInSec || 0;
        this._bpm = score.tempo;
        this._loop = !!opts.loop;
        this._playOpts = opts;
        this.playing = true;

        plan.eventos.forEach((ev) => {
            if (ev.musicalSec + 0.0005 < this._offset) return;
            const t = this._t0 + ev.musicalSec;
            if (t < this.ctx.currentTime - 0.02) return;
            if (ev.tipo === 'nota') {
                this._dispararGolpe(ev.instrument, ev.articulation, t, ev.velocity);
            } else if (ev.tipo === 'click') {
                this._click(t, ev.fuerte, ev.sub);
            }
        });

        this._tickClock();
    }

    _planificar(score, opts) {
        const bpm = score.tempo || 100;
        const cap = ticksDeCompas(score.timeSignature);
        let timeline = expandirTimeline(score);
        if (opts.soloSeccion !== null && opts.soloSeccion !== undefined) {
            timeline = timeline.filter((s) => s.sectionIdx === opts.soloSeccion);
        }
        // Loop de un tramo: compases [desde, hasta] de una parte (una pasada, sin repeticiones).
        if (opts.rango) {
            const { sectionIdx, desde, hasta } = opts.rango;
            const a = Math.min(desde, hasta);
            const b = Math.max(desde, hasta);
            timeline = [];
            for (let mi = a; mi <= b; mi++) {
                if (score.sections[sectionIdx]?.measures[mi]) timeline.push({ sectionIdx, measureIdx: mi });
            }
        }
        if (opts.desde) {
            const i = timeline.findIndex((s) => s.sectionIdx === opts.desde.sectionIdx && s.measureIdx === opts.desde.measureIdx);
            if (i > 0) timeline = timeline.slice(i);
        }

        const measureStarts = [];
        let cursorTick = 0;
        timeline.forEach((pos) => {
            measureStarts.push({
                sectionIdx: pos.sectionIdx,
                measureIdx: pos.measureIdx,
                musicalSec: segundosDeTicks(cursorTick, bpm),
                duration: segundosDeTicks(cap, bpm),
                startTick: cursorTick,
            });
            cursorTick += cap;
        });

        const full = expandirTimeline(score);
        const firstAbs = timeline.length
            ? Math.max(0, full.findIndex((s) => s.sectionIdx === timeline[0].sectionIdx && s.measureIdx === timeline[0].measureIdx)) * cap
            : 0;

        const eventosBase = opts.rango
            ? eventosDeTramo(score, timeline, cap, firstAbs)
            : eventosMusicales(score);
        const eventosRebase = eventosBase
            .filter((ev) => {
                if (opts.soloSeccion !== null && opts.soloSeccion !== undefined && ev.sectionIdx !== opts.soloSeccion) return false;
                // Lo silenciado (mute/solo) no se programa.
                if (score.instruments?.length && !esAudible(score, ev.instrument)) return false;
                return ev.absTick >= firstAbs;
            })
            .map((ev) => ({
                tipo: 'nota',
                musicalSec: segundosDeTicks(ev.absTick + ev.tickLocal - firstAbs, bpm),
                instrument: ev.instrument,
                articulation: ev.articulation,
                velocity: ev.velocity,
                sectionIdx: ev.sectionIdx,
                measureIdx: ev.measureIdx,
            }));

        const porPulso = Math.round((TPQ * 4) / (score.timeSignature.den || 4));
        const pulsos = score.timeSignature.num || 4;
        const compasesPrevios = opts.countIn === false || opts.offsetSec > 0 ? 0 : Math.min(2, Math.max(0, opts.countInCompases ?? 1));
        const countInSec = compasesPrevios ? segundosDeTicks(cap * compasesPrevios, bpm) : 0;

        const clicks = [];
        for (let c = 0; c < compasesPrevios; c++) {
            for (let p = 0; p < pulsos; p++) {
                clicks.push({
                    tipo: 'click',
                    musicalSec: segundosDeTicks(c * cap + p * porPulso, bpm),
                    fuerte: p === 0,
                    countIn: true,
                });
            }
        }
        if (this.metronomo) {
            const sub = [1, 2, 4].includes(this.metroSub) ? this.metroSub : 1;
            const paso = porPulso / sub;
            timeline.forEach((pos, mi) => {
                for (let p = 0; p < pulsos * sub; p++) {
                    clicks.push({
                        tipo: 'click',
                        musicalSec: countInSec + segundosDeTicks(mi * cap + p * paso, bpm),
                        fuerte: p === 0 && this.metroAcento,
                        sub: p % sub !== 0,
                    });
                }
            });
        }

        if (countInSec) {
            eventosRebase.forEach((ev) => { ev.musicalSec += countInSec; });
            measureStarts.forEach((m) => { m.musicalSec += countInSec; });
        }

        return {
            eventos: [...eventosRebase, ...clicks],
            measureStarts,
            countInSec,
            duration: countInSec + segundosDeTicks(cursorTick, bpm),
        };
    }

    /**
     * Cambia el tempo sin cortar: retoma desde la misma posición musical al nuevo BPM.
     */
    async cambiarTempo(score) {
        if (!this.playing || !this._score) return;
        const bpmAnterior = this._bpm || score.tempo;
        const musical = Math.max(0, this.musicalAhora() - (this._countInSec || 0));
        const ticks = (musical * TPQ * bpmAnterior) / 60;
        const offsetSec = segundosDeTicks(ticks, score.tempo);
        await this.play(score, { ...this._playOpts, offsetSec, countIn: false });
    }

    /**
     * Renderiza el ritmo a WAV (estéreo 44,1 kHz) con OfflineAudioContext, sin backend.
     * @returns {Promise<Blob>}
     */
    async renderizarWav(score, opts = {}) {
        await this.asegurarContexto();
        await this.precargarSamples(score);
        const plan = this._planificar(score, { ...opts, countIn: false });
        const sr = 44100;
        const dur = Math.max(0.5, plan.duration + 1.2);
        const OAC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        const off = new OAC(2, Math.ceil(sr * dur), sr);
        const master = off.createGain();
        master.gain.value = 0.7;
        const lim = off.createDynamicsCompressor();
        lim.threshold.value = -8;
        lim.ratio.value = 3.5;
        master.connect(lim).connect(off.destination);
        const canales = {};
        const canal = (id) => {
            if (canales[id]) return canales[id];
            const cfg = score.instruments.find((i) => i.id === id) || {};
            const g = off.createGain();
            g.gain.value = (cfg.volume ?? 0.9) * (GANANCIA_TIMBRE[id] ?? 1);
            if (typeof off.createStereoPanner === 'function') {
                const pan = off.createStereoPanner();
                pan.pan.value = Math.max(-1, Math.min(1, Number(cfg.pan) || 0));
                g.connect(pan).connect(master);
            } else {
                g.connect(master);
            }
            canales[id] = g;
            return g;
        };
        plan.eventos.forEach((ev) => {
            if (ev.tipo === 'click') {
                if (opts.conMetronomo) this._click(ev.musicalSec + 0.05, ev.fuerte, ev.sub, off, master);
                return;
            }
            const destinos = ev.instrument === UNISONO ? vocesDeUnisono(score) : [ev.instrument];
            destinos.forEach((id) => {
                const cfg = score.instruments.find((i) => i.id === id) || {};
                bancoSamples.disparar(off, canal(id), id, ev.articulation, ev.musicalSec + 0.05, ev.velocity / Math.sqrt(destinos.length), 2 ** ((Number(cfg.pitch) || 0) / 12));
            });
        });
        const buffer = await off.startRendering();
        return wavDeBuffer(buffer);
    }

    _tickClock() {
        if (this._raf) cancelAnimationFrame(this._raf);
        const loop = () => {
            if (this.stopFlag || this.paused || !this.playing) return;
            const musicalSec = Math.max(0, this.ctx.currentTime - this._t0);
            const pos = this.posicionDe(musicalSec);
            if (this.onClock) this.onClock({ musicalSec, ...pos });
            if (musicalSec >= this._duration) {
                if (this._loop && this._score) {
                    this.play(this._score, { ...this._playOpts, offsetSec: 0, countIn: false });
                    return;
                }
                this.playing = false;
                if (this.onStop) this.onStop();
                return;
            }
            this._raf = requestAnimationFrame(loop);
        };
        this._raf = requestAnimationFrame(loop);
    }

    posicionDe(musicalSec) {
        const starts = this._measureStarts;
        if (!starts.length) return { sectionIdx: 0, measureIdx: 0, frac: 0, countIn: false };
        const countInSec = this._countInSec || 0;
        if (countInSec && musicalSec < countInSec) {
            return {
                sectionIdx: starts[0].sectionIdx,
                measureIdx: starts[0].measureIdx,
                frac: 0,
                countIn: true,
            };
        }
        let cur = starts[0];
        for (const m of starts) {
            if (musicalSec >= m.musicalSec) cur = m;
            else break;
        }
        const frac = cur.duration > 0 ? Math.min(1, Math.max(0, (musicalSec - cur.musicalSec) / cur.duration)) : 0;
        return { sectionIdx: cur.sectionIdx, measureIdx: cur.measureIdx, frac, countIn: false };
    }

    musicalAhora() {
        if (!this.ctx || !this.playing) return this._offset;
        if (this.paused) return this._offset;
        return Math.max(0, this.ctx.currentTime - this._t0);
    }

    pause() {
        if (!this.playing || this.paused) return;
        this._offset = this.musicalAhora();
        this.paused = true;
        this.playing = false;
        this._cortarFuentes();
        if (this._raf) cancelAnimationFrame(this._raf);
        this._raf = 0;
    }

    async resume(score, opts = {}) {
        if (!this.paused) return this.play(score, opts);
        return this.play(score, { ...opts, offsetSec: this._offset });
    }

    stop() {
        this.stopFlag = true;
        this.playing = false;
        this.paused = false;
        this._offset = 0;
        this._cortarFuentes();
        if (this._raf) cancelAnimationFrame(this._raf);
        this._raf = 0;
        if (this.onStop) this.onStop();
    }

    _cortarFuentes() {
        const t = this.ctx ? this.ctx.currentTime : 0;
        this._sources.forEach((src) => {
            try { src.stop(t); } catch { /* ya detenida */ }
        });
        this._sources = [];
        bancoSamples.cortarTodas(this.ctx);
    }
}

/** Eventos de un tramo de compases (para el loop de una selección). */
function eventosDeTramo(score, timeline, cap, firstAbs) {
    const out = [];
    timeline.forEach((pos, i) => {
        const m = score.sections[pos.sectionIdx]?.measures[pos.measureIdx];
        if (!m) return;
        const absTick = firstAbs + i * cap;
        Object.entries(m.voces || {}).forEach(([instId, voz]) => {
            let local = 0;
            (voz || []).forEach((n) => {
                const d = ticksDeNotaLocal(n);
                if (!n.rest) {
                    out.push({
                        instrument: instId, articulation: n.stroke, velocity: velocidadNota(n),
                        sectionIdx: pos.sectionIdx, measureIdx: pos.measureIdx, tickLocal: local, absTick,
                    });
                }
                local += d;
            });
        });
    });
    return out;
}

/** WAV PCM 16 bits a partir de un AudioBuffer. */
export function wavDeBuffer(buffer) {
    const canales = buffer.numberOfChannels;
    const largo = buffer.length;
    const bytes = 44 + largo * canales * 2;
    const view = new DataView(new ArrayBuffer(bytes));
    const texto = (o, t) => { for (let i = 0; i < t.length; i++) view.setUint8(o + i, t.charCodeAt(i)); };
    texto(0, 'RIFF');
    view.setUint32(4, bytes - 8, true);
    texto(8, 'WAVE');
    texto(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, canales, true);
    view.setUint32(24, buffer.sampleRate, true);
    view.setUint32(28, buffer.sampleRate * canales * 2, true);
    view.setUint16(32, canales * 2, true);
    view.setUint16(34, 16, true);
    texto(36, 'data');
    view.setUint32(40, largo * canales * 2, true);
    const datos = Array.from({ length: canales }, (_, c) => buffer.getChannelData(c));
    let o = 44;
    for (let i = 0; i < largo; i++) {
        for (let c = 0; c < canales; c++) {
            const v = Math.max(-1, Math.min(1, datos[c][i]));
            view.setInt16(o, v < 0 ? v * 0x8000 : v * 0x7fff, true);
            o += 2;
        }
    }
    return new Blob([view], { type: 'audio/wav' });
}
