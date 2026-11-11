/**
 * Sincronía entre la grabación de referencia (audio_ref) y la partitura.
 *
 * La grabación se mide en segundos; la partitura en pulsos. Si la grabación trae
 * `beats` (segundo de cada pulso, marcados a mano o por la transcripción) se
 * interpola entre ellos: así sigue al grupo aunque acelere. Si no, se usa
 * `offset_seg` (dónde cae el primer tiempo) y `tempo_origen` (o el tempo del toque).
 *
 * Funciones puras (sin DOM): las usa el visor, el editor y los tests.
 * Espejo en la app: mobile/src/lib/partitura/sincro.ts.
 */
import { TPQ, ticksDeCompas, expandirTimeline } from './model.js';

/** Ticks de un pulso: negra en x/4, negra con puntillo en 6/8, 9/8 y 12/8. */
export function ticksDePulso(ts) {
    const den = ts?.den || 4;
    const num = ts?.num || 4;
    if (den === 8 && num % 3 === 0 && num > 3) return 72;
    return Math.round((TPQ * 4) / den);
}

export function pulsosDeCompas(ts) {
    return Math.max(1, Math.round(ticksDeCompas(ts) / ticksDePulso(ts)));
}

/** Pulsos por segundo de la grabación cuando no hay beats marcados. */
function pulsosPorSegundo(score) {
    const ref = score.audio_ref || {};
    const bpm = ref.tempo_origen || score.tempo || 100;
    return (bpm / 60) * (TPQ / ticksDePulso(score.timeSignature));
}

/**
 * Pulso (fraccionario, 0 = primer tiempo del primer compás) en el segundo `t` de la grabación.
 * @param {object} score
 * @param {number} t
 */
export function pulsoDeTiempo(score, t) {
    const ref = score.audio_ref || {};
    const beats = Array.isArray(ref.beats) ? ref.beats : [];
    if (beats.length >= 2) {
        if (t <= beats[0]) return (t - beats[0]) / (beats[1] - beats[0]);
        const n = beats.length;
        if (t >= beats[n - 1]) return n - 1 + (t - beats[n - 1]) / (beats[n - 1] - beats[n - 2]);
        let lo = 0;
        let hi = n - 1;
        while (hi - lo > 1) {
            const mid = (lo + hi) >> 1;
            if (beats[mid] <= t) lo = mid;
            else hi = mid;
        }
        return lo + (t - beats[lo]) / (beats[hi] - beats[lo]);
    }
    return (t - (ref.offset_seg || 0)) * pulsosPorSegundo(score);
}

/** Inversa de pulsoDeTiempo: segundo de la grabación donde cae el pulso `p`. */
export function tiempoDePulso(score, p) {
    const ref = score.audio_ref || {};
    const beats = Array.isArray(ref.beats) ? ref.beats : [];
    if (beats.length >= 2) {
        const n = beats.length;
        if (p <= 0) return beats[0] + p * (beats[1] - beats[0]);
        if (p >= n - 1) return beats[n - 1] + (p - (n - 1)) * (beats[n - 1] - beats[n - 2]);
        const i = Math.floor(p);
        return beats[i] + (p - i) * (beats[i + 1] - beats[i]);
    }
    return (ref.offset_seg || 0) + p / pulsosPorSegundo(score);
}

/**
 * Compás que suena en el segundo `t` de la grabación.
 * @returns {{ orden: number, sectionIdx: number|null, measureIdx: number|null, frac: number, antes: boolean, despues: boolean, pulso: number }}
 */
export function posicionEnGrabacion(score, t, timeline = expandirTimeline(score)) {
    const pulso = pulsoDeTiempo(score, t);
    const porCompas = pulsosDeCompas(score.timeSignature);
    const orden = Math.floor(pulso / porCompas);
    const frac = pulso / porCompas - orden;
    const pos = timeline[orden];
    return {
        orden,
        sectionIdx: pos ? pos.sectionIdx : null,
        measureIdx: pos ? pos.measureIdx : null,
        frac: Math.min(1, Math.max(0, frac)),
        antes: orden < 0,
        despues: orden >= timeline.length,
        pulso,
    };
}

/** Segundo de la grabación donde empieza el compás número `orden` (0 = primero, contando repeticiones). */
export function tiempoDeCompas(score, orden) {
    return tiempoDePulso(score, orden * pulsosDeCompas(score.timeSignature));
}

/** Tempo medio de la grabación (BPM de negra) según los beats, o null. */
export function tempoDeGrabacion(score) {
    const beats = score.audio_ref?.beats || [];
    if (beats.length < 2) return score.audio_ref?.tempo_origen || null;
    const seg = (beats[beats.length - 1] - beats[0]) / (beats.length - 1);
    if (seg <= 0) return null;
    return Math.round((60 / seg) * (ticksDePulso(score.timeSignature) / TPQ));
}

/**
 * «Ajustar inicio»: el primer tiempo del primer compás cae en el segundo `t`.
 * Con beats, se descartan los pulsos anteriores (una anacrusa, el conteo) y el más
 * cercano a `t` pasa a ser el primero. Devuelve un audio_ref nuevo.
 */
export function ajustarInicio(ref, t) {
    const out = { ...ref, beats: Array.isArray(ref.beats) ? [...ref.beats] : [] };
    const tt = Math.max(0, Math.round(t * 1000) / 1000);
    if (out.beats.length >= 2) {
        let mejor = 0;
        out.beats.forEach((b, i) => {
            if (Math.abs(b - tt) < Math.abs(out.beats[mejor] - tt)) mejor = i;
        });
        out.beats = out.beats.slice(mejor);
        out.beats[0] = tt;
        if (out.beats.length > 1 && out.beats[1] <= tt) out.beats = [tt];
    }
    out.offset_seg = tt;
    return out;
}
