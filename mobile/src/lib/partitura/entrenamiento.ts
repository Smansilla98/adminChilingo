/**
 * Entrenamiento de velocidad (espejo de resources/js/partitura/entrenamiento.js): el loop arranca a
 * un % del tempo del toque y sube un escalón cada N vueltas hasta un tope. Tres presets que cada
 * usuario ajusta; se guardan en el teléfono.
 */
export const TEMPO_MIN = 30;
export const TEMPO_MAX = 260;

export type NombrePreset = 'leve' | 'medio' | 'fuerte';
export const PRESETS: NombrePreset[] = ['leve', 'medio', 'fuerte'];

export interface Preset {
  inicio: number;
  subida: number;
  cada: number;
  tope: number;
}

export type Presets = Record<NombrePreset, Preset>;

export const PRESETS_DEFAULT: Presets = {
  leve: { inicio: 80, subida: 2, cada: 2, tope: 100 },
  medio: { inicio: 70, subida: 5, cada: 2, tope: 100 },
  fuerte: { inicio: 60, subida: 10, cada: 1, tope: 100 },
};

export const LIMITES: Record<keyof Preset, [number, number]> = {
  inicio: [30, 100],
  subida: [1, 20],
  cada: [1, 16],
  tope: [50, 150],
};

const acotar = (n: number, [min, max]: [number, number]) => Math.min(max, Math.max(min, n));

export function normalizarPreset(raw: Partial<Preset> | undefined | null, nombre: NombrePreset = 'medio'): Preset {
  const base = PRESETS_DEFAULT[nombre];
  const dato = (campo: keyof Preset): number => {
    const n = Math.round(Number(raw?.[campo]));
    return Number.isFinite(n) ? acotar(n, LIMITES[campo]) : base[campo];
  };
  return { inicio: dato('inicio'), subida: dato('subida'), cada: dato('cada'), tope: dato('tope') };
}

/** Tempo de la vuelta `vuelta` (0 = la primera). */
export function tempoDeVuelta(base: number, vuelta: number, preset: Partial<Preset>): number {
  const cfg = normalizarPreset(preset);
  const b = Math.max(TEMPO_MIN, Number(base) || 100);
  const tope = Math.min(TEMPO_MAX, Math.round((b * cfg.tope) / 100));
  const inicio = Math.round((b * cfg.inicio) / 100);
  const escalones = Math.floor(Math.max(0, vuelta) / cfg.cada);
  const tempo = inicio + escalones * ((b * cfg.subida) / 100);
  return Math.max(TEMPO_MIN, Math.min(tope, Math.round(tempo)));
}

/** Presets guardados (texto JSON), con lo que falte de fábrica. */
export function parsearPresets(texto: string | null): Presets {
  let guardados: Partial<Record<NombrePreset, Partial<Preset>>> = {};
  try {
    guardados = JSON.parse(texto || '{}') || {};
  } catch {
    guardados = {};
  }
  return { leve: normalizarPreset(guardados.leve, 'leve'), medio: normalizarPreset(guardados.medio, 'medio'), fuerte: normalizarPreset(guardados.fuerte, 'fuerte') };
}

export const CLAVE_PRESETS = 'ito.partitura.entrenamiento.v1';
