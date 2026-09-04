/**
 * Sampler de percusión + scheduler sobre AudioContext.currentTime.
 * No usa OscillatorNode para simular tambores. El metrónomo sí es un click sintético.
 */
import { UNISONO, vocesDeUnisono, MAPA_SAMPLES } from './instruments.js';
import {
    TPQ, ticksDeCompas, expandirTimeline, eventosMusicales, segundosDeTicks,
} from './model.js';
import { bancoSamples } from './samples.js';

export class MotorAudio {
    constructor() {
        /** @type {AudioContext|null} */
        this.ctx = null;
        this.master = null;
        this.gains = {};
        this.metronomo = false;
        this.metroGain = 0.5;
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
        this.estadoSamples = { listos: 0, faltan: 0, total: 0, missing: [] };
    }

    async asegurarContexto() {
        if (!this.ctx) {
            const AC = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AC();
            this.master = this.ctx.createGain();
            this.master.gain.value = 0.9;
            this.master.connect(this.ctx.destination);
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
            g.gain.value = 0.9;
            g.connect(this.master);
            this.gains[instId] = g;
        }
        return this.gains[instId];
    }

    aplicarMixer(score) {
        if (!this.ctx) return;
        const soloActivo = score.instruments.some((i) => i.solo);
        score.instruments.forEach((i) => {
            const g = this.canalDe(i.id);
            const audible = soloActivo ? i.solo && !i.mute : !i.mute;
            g.gain.value = audible ? i.volume : 0;
        });
    }

    async golpe(instId, strokeId, when = 0, velocidad = 1) {
        await this.asegurarContexto();
        if (!bancoSamples.ready) await bancoSamples.precargar(this.ctx, [instId]);
        const t = when || this.ctx.currentTime + 0.01;
        this._dispararGolpe(instId, strokeId, t, velocidad);
    }

    _dispararGolpe(instId, strokeId, t, velocidad = 1) {
        const dest = instId === UNISONO ? null : instId;
        if (!dest) return null;
        const src = bancoSamples.disparar(this.ctx, this.canalDe(dest), dest, strokeId, t, velocidad);
        if (src) this._sources.push(src);
        return src;
    }

    _click(t, fuerte) {
        const ctx = this.ctx;
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = 'square';
        osc.frequency.value = fuerte ? 1600 : 1100;
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(this.metroGain * (fuerte ? 0.25 : 0.15), t + 0.001);
        g.gain.exponentialRampToValueAtTime(0.0004, t + 0.045);
        osc.connect(g).connect(this.master);
        osc.start(t);
        osc.stop(t + 0.06);
        this._sources.push(osc);
    }

    /**
     * @param {object} score
     * @param {{ desde?: {sectionIdx:number, measureIdx:number}, soloSeccion?: number|null, loop?: boolean, offsetSec?: number }} [opts]
     */
    async play(score, opts = {}) {
        await this.asegurarContexto();
        this._cortarFuentes();
        if (this._raf) cancelAnimationFrame(this._raf);
        this._raf = 0;
        this.stopFlag = false;
        this.paused = false;
        await this.precargarSamples(score);
        this.aplicarMixer(score);

        const plan = this._planificar(score, opts);
        if (!plan.eventos.length && !plan.measureStarts.length) {
            this.playing = false;
            return;
        }

        const lookahead = 0.08;
        this._t0 = this.ctx.currentTime + lookahead - (opts.offsetSec || 0);
        this._offset = opts.offsetSec || 0;
        this._duration = plan.duration;
        this._measureStarts = plan.measureStarts;
        this._loop = !!opts.loop;
        this._playOpts = opts;
        this._score = score;
        this.playing = true;

        plan.eventos.forEach((ev) => {
            if (ev.musicalSec + 0.0005 < this._offset) return;
            const t = this._t0 + ev.musicalSec;
            if (t < this.ctx.currentTime - 0.02) return;
            if (ev.tipo === 'nota') {
                const destinos = ev.instrument === UNISONO ? vocesDeUnisono(score) : [ev.instrument];
                destinos.forEach((id) => this._dispararGolpe(id, ev.articulation, t, ev.velocity));
            } else if (ev.tipo === 'click') {
                this._click(t, ev.fuerte);
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

        const eventosRebase = eventosMusicales(score)
            .filter((ev) => {
                if (opts.soloSeccion !== null && opts.soloSeccion !== undefined && ev.sectionIdx !== opts.soloSeccion) return false;
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

        const clicks = [];
        if (this.metronomo) {
            const porPulso = Math.round((TPQ * 4) / (score.timeSignature.den || 4));
            timeline.forEach((pos, mi) => {
                for (let p = 0; p < (score.timeSignature.num || 4); p++) {
                    clicks.push({
                        tipo: 'click',
                        musicalSec: segundosDeTicks(mi * cap + p * porPulso, bpm),
                        fuerte: p === 0,
                    });
                }
            });
        }

        return {
            eventos: [...eventosRebase, ...clicks],
            measureStarts,
            duration: segundosDeTicks(cursorTick, bpm),
        };
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
                    this.play(this._score, { ...this._playOpts, offsetSec: 0 });
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
        if (!starts.length) return { sectionIdx: 0, measureIdx: 0, frac: 0 };
        let cur = starts[0];
        for (const m of starts) {
            if (musicalSec >= m.musicalSec) cur = m;
            else break;
        }
        const frac = cur.duration > 0 ? Math.min(1, Math.max(0, (musicalSec - cur.musicalSec) / cur.duration)) : 0;
        return { sectionIdx: cur.sectionIdx, measureIdx: cur.measureIdx, frac };
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
        if (this.ctx && this.master) {
            this.master.gain.cancelScheduledValues(t);
            this.master.gain.setValueAtTime(this.master.gain.value, t);
            this.master.gain.linearRampToValueAtTime(0.0001, t + 0.02);
            this.master.gain.linearRampToValueAtTime(0.9, t + 0.08);
        }
    }
}
