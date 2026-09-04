/**
 * Motor de audio: samples multi-técnica (si existen) + síntesis WebAudio con filtros.
 * El timing de tresillos usa ticksDeNota (TPQ divisible por 3) — equivalente a Tone.js Transport.
 */
import {
    instrumentoPorId, GOLPES, UNISONO, vocesDeUnisono,
    midiDeGolpe, freqDeMidi, filtroDeGolpe,
} from './instruments.js';
import { TPQ, ticksDeNota, expandirTimeline, ticksDeCompas } from './model.js';
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
        this.stopFlag = false;
        this._timer = null;
        this.onMeasure = null;
        this.onStop = null;
        this._samplesReady = false;
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

    /**
     * Precarga samples para los instrumentos del score (no bloquea si faltan archivos).
     * @param {object} score
     */
    async precargarSamples(score) {
        await this.asegurarContexto();
        const ids = (score?.instruments || []).map((i) => i.id).filter((id) => id !== UNISONO);
        await bancoSamples.precargar(this.ctx, ids.length ? ids : ['timbal', 'redoblante', 'surdo_grave']);
        this._samplesReady = true;
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

    /** Golpe suelto (preview al editar). */
    async golpe(instId, strokeId, when = 0, velocidad = 1) {
        await this.asegurarContexto();
        if (!this._samplesReady) {
            // Precarga lazy del instrumento tocado
            bancoSamples.precargar(this.ctx, [instId]).catch(() => {});
        }
        this._golpe(instId, strokeId, when ? when : this.ctx.currentTime + 0.01, velocidad);
    }

    _golpe(instId, strokeId, t, velocidad = 1) {
        const inst = instrumentoPorId(instId);
        if (!inst) return;
        const golpe = GOLPES[strokeId] || GOLPES.nota;
        const out = this.canalDe(instId);
        const ctx = this.ctx;
        const vel = velocidad * golpe.gain;

        // 1) Sample real si está cargado
        if (bancoSamples.disparar(ctx, out, instId, strokeId, t, vel)) return;

        // 2) Síntesis diferenciada por técnica + filtro
        if (inst.familia === 'metal' || golpe.timbre === 'aro') {
            this._metalico(ctx, out, inst, golpe, strokeId, t, vel);
            if (inst.familia === 'membrana' && golpe.timbre === 'aro') {
                this._membrana(ctx, out, inst, golpe, strokeId, t, vel * 0.28);
            }
            return;
        }
        if (inst.familia === 'mano' || golpe.timbre === 'palma') {
            this._ruido(ctx, out, t, vel, golpe.timbre === 'palma' ? 900 : 1600, 0.1, filtroDeGolpe(strokeId));
            return;
        }
        this._membrana(ctx, out, inst, golpe, strokeId, t, vel);
        if (golpe.timbre === 'golpe') this._ruido(ctx, out, t, vel * 0.22, 2200, 0.03, filtroDeGolpe(strokeId));
        if (strokeId === 'flam') this._membrana(ctx, out, inst, golpe, strokeId, Math.max(0, t - 0.035), vel * 0.5);
    }

    _membrana(ctx, out, inst, golpe, strokeId, t, vel) {
        const apagado = golpe.timbre === 'apagado';
        const midi = midiDeGolpe(inst.id, strokeId);
        const f0 = freqDeMidi(midi) * (inst.familia === 'membrana' ? 0.55 : 1);
        // Preferir freq del instrumento como ancla grave, modulada por MIDI
        const base = (inst.freq + f0) / 2;
        const dur = apagado ? 0.11 : Math.max(0.16, 26 / Math.max(40, base));
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        const filtro = ctx.createBiquadFilter();
        const cfg = filtroDeGolpe(strokeId);
        if (cfg) {
            filtro.type = cfg.type;
            filtro.frequency.value = cfg.freq;
            filtro.Q.value = cfg.Q;
        } else {
            filtro.type = 'lowpass';
            filtro.frequency.value = 3200;
        }

        osc.type = 'sine';
        const fStart = base * (golpe.id === 'agudo' ? 2.1 : 1.9);
        osc.frequency.setValueAtTime(fStart, t);
        osc.frequency.exponentialRampToValueAtTime(Math.max(35, base), t + Math.min(0.09, dur * 0.6));
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(Math.min(1, 0.85 * vel), t + 0.004);
        g.gain.exponentialRampToValueAtTime(0.0008, t + dur);
        osc.connect(filtro).connect(g).connect(out);
        osc.start(t);
        osc.stop(t + dur + 0.02);

        const osc2 = ctx.createOscillator();
        const g2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(base * 2.7, t);
        g2.gain.setValueAtTime(0, t);
        g2.gain.linearRampToValueAtTime(0.18 * vel, t + 0.003);
        g2.gain.exponentialRampToValueAtTime(0.0005, t + dur * 0.5);
        osc2.connect(g2).connect(out);
        osc2.start(t);
        osc2.stop(t + dur);
    }

    _metalico(ctx, out, inst, golpe, strokeId, t, vel) {
        const midi = midiDeGolpe(inst.id, strokeId);
        const base = inst.familia === 'metal' ? inst.freq : freqDeMidi(midi) * 1.8;
        const filtro = ctx.createBiquadFilter();
        const cfg = filtroDeGolpe(strokeId) || { type: 'highpass', freq: 1600, Q: 0.7 };
        filtro.type = cfg.type;
        filtro.frequency.value = cfg.freq;
        filtro.Q.value = cfg.Q;
        const bus = ctx.createGain();
        bus.gain.value = 1;
        bus.connect(filtro).connect(out);

        [1, 1.51, 2.03].forEach((mult, i) => {
            const osc = ctx.createOscillator();
            const g = ctx.createGain();
            osc.type = 'square';
            osc.frequency.value = base * mult;
            const dur = inst.familia === 'metal' ? 0.35 : 0.09;
            g.gain.setValueAtTime(0, t);
            g.gain.linearRampToValueAtTime((0.22 / (i + 1)) * vel, t + 0.002);
            g.gain.exponentialRampToValueAtTime(0.0004, t + dur);
            osc.connect(g).connect(bus);
            osc.start(t);
            osc.stop(t + dur + 0.02);
        });
        this._ruido(ctx, out, t, vel * 0.4, 3800, 0.05, cfg);
    }

    _ruido(ctx, out, t, vel, freq, dur, filtroCfg = null) {
        const len = Math.max(1, Math.floor(ctx.sampleRate * dur));
        const buf = ctx.createBuffer(1, len, ctx.sampleRate);
        const data = buf.getChannelData(0);
        for (let i = 0; i < len; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / len);
        const src = ctx.createBufferSource();
        src.buffer = buf;
        const hp = ctx.createBiquadFilter();
        if (filtroCfg) {
            hp.type = filtroCfg.type;
            hp.frequency.value = filtroCfg.freq;
            hp.Q.value = filtroCfg.Q;
        } else {
            hp.type = 'bandpass';
            hp.frequency.value = freq;
            hp.Q.value = 0.8;
        }
        const g = ctx.createGain();
        g.gain.value = 0.5 * vel;
        src.connect(hp).connect(g).connect(out);
        src.start(t);
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
    }

    /**
     * Reproduce la partitura completa (o desde una parte / compás).
     * Tresillos: cada nota aporta ticksDeNota (base * den/num) → no se desfasa del pulso.
     * @param {object} score
     * @param {{ desde?: {sectionIdx:number, measureIdx:number}, soloSeccion?: number|null, loop?: boolean }} [opts]
     */
    async play(score, opts = {}) {
        await this.asegurarContexto();
        this.stop();
        await this.precargarSamples(score);
        this.aplicarMixer(score);
        this.playing = true;
        this.stopFlag = false;

        let timeline = expandirTimeline(score);
        if (opts.soloSeccion !== null && opts.soloSeccion !== undefined) {
            timeline = timeline.filter((s) => s.sectionIdx === opts.soloSeccion);
        }
        if (opts.desde) {
            const i = timeline.findIndex((s) => s.sectionIdx === opts.desde.sectionIdx && s.measureIdx === opts.desde.measureIdx);
            if (i > 0) timeline = timeline.slice(i);
        }
        if (!timeline.length) {
            this.playing = false;
            return;
        }

        const segNegra = 60 / (score.tempo || 100);
        const segTick = segNegra / TPQ;
        const capacidad = ticksDeCompas(score.timeSignature);
        const unisono = vocesDeUnisono(score);
        const t0 = this.ctx.currentTime + 0.12;
        let cursor = 0;
        const eventos = [];

        timeline.forEach((pos) => {
            const m = score.sections[pos.sectionIdx].measures[pos.measureIdx];
            eventos.push({ tipo: 'compas', t: t0 + cursor * segTick, pos });
            if (this.metronomo) {
                const porPulso = Math.round((TPQ * 4) / score.timeSignature.den);
                for (let p = 0; p < score.timeSignature.num; p++) {
                    eventos.push({ tipo: 'click', t: t0 + (cursor + p * porPulso) * segTick, fuerte: p === 0 });
                }
            }
            score.instruments.forEach((cfg) => {
                const destinos = cfg.id === UNISONO ? unisono : [cfg.id];
                if (cfg.id === UNISONO && (cfg.mute || (score.instruments.some((i) => i.solo) && !cfg.solo))) return;
                let local = 0;
                (m.voces[cfg.id] || []).forEach((n) => {
                    if (!n.rest) {
                        destinos.forEach((instId) => {
                            eventos.push({
                                tipo: 'nota',
                                t: t0 + (cursor + local) * segTick,
                                instId,
                                stroke: n.stroke,
                                vel: (n.dyn ? dinamicaVel(n.dyn) : 1) * (destinos.length > 1 ? 0.85 : 1),
                            });
                        });
                    }
                    local += ticksDeNota(n);
                });
            });
            cursor += capacidad;
        });

        const finT = t0 + cursor * segTick;
        eventos.sort((a, b) => a.t - b.t);

        let i = 0;
        const tick = () => {
            if (this.stopFlag) return;
            const horizonte = this.ctx.currentTime + 0.35;
            while (i < eventos.length && eventos[i].t <= horizonte) {
                const ev = eventos[i];
                if (ev.tipo === 'nota') this._golpe(ev.instId, ev.stroke, ev.t, ev.vel);
                else if (ev.tipo === 'click') this._click(ev.t, ev.fuerte);
                else if (ev.tipo === 'compas' && this.onMeasure) {
                    const delay = Math.max(0, (ev.t - this.ctx.currentTime) * 1000);
                    setTimeout(() => {
                        if (!this.stopFlag) this.onMeasure(ev.pos);
                    }, delay);
                }
                i += 1;
            }
            if (i >= eventos.length && this.ctx.currentTime > finT) {
                this.playing = false;
                if (opts.loop && !this.stopFlag) {
                    this.play(score, opts);
                    return;
                }
                if (this.onStop) this.onStop();
                return;
            }
            this._timer = setTimeout(tick, 90);
        };
        tick();
    }

    stop() {
        this.stopFlag = true;
        this.playing = false;
        if (this._timer) clearTimeout(this._timer);
        this._timer = null;
        if (this.ctx && this.master) {
            const t = this.ctx.currentTime;
            this.master.gain.cancelScheduledValues(t);
            this.master.gain.setValueAtTime(this.master.gain.value, t);
            this.master.gain.linearRampToValueAtTime(0, t + 0.02);
            this.master.gain.linearRampToValueAtTime(0.9, t + 0.09);
        }
        if (this.onStop) this.onStop();
    }
}

function dinamicaVel(dyn) {
    return { pp: 0.35, p: 0.55, mp: 0.72, mf: 0.9, f: 1.1, ff: 1.3 }[dyn] || 1;
}
