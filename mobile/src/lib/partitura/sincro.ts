/**
 * Sincronía entre la grabación de referencia y la partitura (espejo de resources/js/partitura/sincro.js).
 * La grabación se mide en segundos; la partitura en pulsos. Con `beats` se interpola entre pulsos
 * (sigue al grupo aunque acelere); si no, se usan `offset_seg` y el tempo de origen.
 */
import { TPQ, ticksDeCompas, type AudioRef, type Score } from './modelo';

/** Ticks de un pulso: negra en x/4; negra con puntillo en 6/8, 9/8 y 12/8. */
export function ticksDePulso(ts?: { num: number; den: number }): number {
  const den = ts?.den || 4;
  const num = ts?.num || 4;
  if (den === 8 && num % 3 === 0 && num > 3) return 72;
  return Math.round((TPQ * 4) / den);
}

export function pulsosDeCompas(ts?: { num: number; den: number }): number {
  return Math.max(1, Math.round(ticksDeCompas(ts) / ticksDePulso(ts)));
}

function pulsosPorSegundo(score: Score): number {
  const ref = score.audio_ref;
  const bpm = ref?.tempo_origen || score.tempo || 100;
  return (bpm / 60) * (TPQ / ticksDePulso(score.timeSignature));
}

/** Pulso fraccionario (0 = primer tiempo del primer compás) en el segundo `t` de la grabación. */
export function pulsoDeTiempo(score: Score, t: number): number {
  const ref: AudioRef | null | undefined = score.audio_ref;
  const beats = ref?.beats ?? [];
  if (beats.length >= 2) {
    const n = beats.length;
    if (t <= beats[0]) return (t - beats[0]) / (beats[1] - beats[0]);
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
  return (t - (ref?.offset_seg ?? 0)) * pulsosPorSegundo(score);
}

/** Segundo de la grabación donde cae el pulso `p`. */
export function tiempoDePulso(score: Score, p: number): number {
  const ref = score.audio_ref;
  const beats = ref?.beats ?? [];
  if (beats.length >= 2) {
    const n = beats.length;
    if (p <= 0) return beats[0] + p * (beats[1] - beats[0]);
    if (p >= n - 1) return beats[n - 1] + (p - (n - 1)) * (beats[n - 1] - beats[n - 2]);
    const i = Math.floor(p);
    return beats[i] + (p - i) * (beats[i + 1] - beats[i]);
  }
  return (ref?.offset_seg ?? 0) + p / pulsosPorSegundo(score);
}

/** Segundo de la grabación donde empieza el compás `orden` (0 = primero, contando repeticiones). */
export function tiempoDeCompas(score: Score, orden: number): number {
  return tiempoDePulso(score, orden * pulsosDeCompas(score.timeSignature));
}

/** Compás que suena en el segundo `t`: orden global, fracción dentro del compás y si está fuera. */
export function posicionEnGrabacion(score: Score, t: number, cantidadCompases: number): { orden: number; frac: number; antes: boolean; despues: boolean } {
  const pulso = pulsoDeTiempo(score, t);
  const porCompas = pulsosDeCompas(score.timeSignature);
  const orden = Math.floor(pulso / porCompas);
  return {
    orden,
    frac: Math.min(1, Math.max(0, pulso / porCompas - orden)),
    antes: orden < 0,
    despues: orden >= cantidadCompases,
  };
}

/** Tempo medio de la grabación según los pulsos (o el de origen si no hay pulsos). */
export function tempoDeGrabacion(score: Score): number | null {
  const beats = score.audio_ref?.beats ?? [];
  if (beats.length < 2) return score.audio_ref?.tempo_origen ?? null;
  const seg = (beats[beats.length - 1] - beats[0]) / (beats.length - 1);
  if (seg <= 0) return null;
  return Math.round((60 / seg) * (ticksDePulso(score.timeSignature) / TPQ));
}
