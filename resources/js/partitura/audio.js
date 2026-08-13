/**
 * Motor de audio: percusión sintetizada (WebAudio), transporte, metrónomo y mixer.
 * Sin samples externos: cada tambor se modela con membrana (osc con pitch-drop) + ruido filtrado.
 */
import { instrumentoPorId, GOLPES, UNISONO, vocesDeUnisono } from './instruments.js';
import { TPQ, ticksDeNota, expandirTimeline, ticksDeCompas } from './model.js';

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
        this._golpe(instId, strokeId, when ? when : this.ctx.currentTime + 0.01, velocidad);
    }

    _golpe(instId, strokeId, t, velocidad = 1) {
        const inst = instrumentoPorId(instId);
        if (!inst) return;
        const golpe = GOLPES[strokeId] || GOLPES.nota;
        const out = this.canalDe(instId);
        const ctx = this.ctx;
        const vel = velocidad * golpe.gain;

        if (inst.familia === 'metal' || golpe.timbre === 'aro') {
            this._metalico(ctx, out, inst, golpe, t, vel);
            if (inst.familia === 'membrana' && golpe.timbre === 'aro') this._membrana(ctx, out, inst, golpe, t, vel * 0.35);
            return;
        }
        if (inst.familia === 'mano') {
            this._ruido(ctx, out, t, vel, 1600, 0.09);
            return;
        }
        this._membrana(ctx, out, inst, golpe, t, vel);
        if (golpe.timbre === 'golpe') this._ruido(ctx, out, t, vel * 0.25, 2200, 0.03);
        if (strokeId === 'flam') this._membrana(ctx, out, inst, golpe, Math.max(0, t - 0.035), vel * 0.5);
    }

    _membrana(ctx, out, inst, golpe, t, vel) {
        const apagado = golpe.timbre === 'apagado';
        const dur = apagado ? 0.11 : Math.max(0.16, 26 / inst.freq);
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = 'sine';
        const f0 = inst.freq * (golpe.id === 'agudo' ? 1.6 : 1);
        osc.frequency.setValueAtTime(f0 * 1.9, t);
        osc.frequency.exponentialRampToValueAtTime(Math.max(35, f0), t + Math.min(0.09, dur * 0.6));
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(Math.min(1, 0.85 * vel), t + 0.004);
        g.gain.exponentialRampToValueAtTime(0.0008, t + dur);
        osc.connect(g).connect(out);
        osc.start(t);
        osc.stop(t + dur + 0.02);

        // Armónico de cuerpo
        const osc2 = ctx.createOscillator();
        const g2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(f0 * 2.7, t);
        g2.gain.setValueAtTime(0, t);
        g2.gain.linearRampToValueAtTime(0.18 * vel, t + 0.003);
        g2.gain.exponentialRampToValueAtTime(0.0005, t + dur * 0.5);
        osc2.connect(g2).connect(out);
        osc2.start(t);
        osc2.stop(t + dur);
    }

    _metalico(ctx, out, inst, golpe, t, vel) {
        const base = inst.familia === 'metal' ? inst.freq : inst.freq * 4.2;
        [1, 1.51, 2.03].forEach((mult, i) => {
            const osc = ctx.createOscillator();
            const g = ctx.createGain();
            osc.type = 'square';
            osc.frequency.value = base * mult;
            const dur = inst.familia === 'metal' ? 0.35 : 0.09;
            g.gain.setValueAtTime(0, t);
            g.gain.linearRampToValueAtTime((0.22 / (i + 1)) * vel, t + 0.002);
            g.gain.exponentialRampToValueAtTime(0.0004, t + dur);
            osc.connect(g).connect(out);
            osc.start(t);
            osc.stop(t + dur + 0.02);
        });
        this._ruido(ctx, out, t, vel * 0.4, 3800, 0.05);
    }

    _ruido(ctx, out, t, vel, freq, dur) {
        const len = Math.max(1, Math.floor(ctx.sampleRate * dur));
        const buf = ctx.createBuffer(1, len, ctx.sampleRate);
        const data = buf.getChannelData(0);
        for (let i = 0; i < len; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / len);
        const src = ctx.createBufferSource();
        src.buffer = buf;
        const hp = ctx.createBiquadFilter();
        hp.type = 'bandpass';
        hp.frequency.value = freq;
        hp.Q.value = 0.8;
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
     * @param {object} score
     * @param {{ desde?: {sectionIdx:number, measureIdx:number}, soloSeccion?: number|null, loop?: boolean }} [opts]
     */
    async play(score, opts = {}) {
        await this.asegurarContexto();
        this.stop();
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
                // La voz "Todos" es un unísono estricto: suena en todos los instrumentos del toque.
                const destinos = cfg.id === UNISONO ? unisono : [cfg.id];
                // el canal virtual "Todos" no tiene gain propio: respetamos su mute acá
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

        // Scheduler por ventanas de 250 ms
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
            // corte suave para evitar clicks
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
