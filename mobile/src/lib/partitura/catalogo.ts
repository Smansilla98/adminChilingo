/** Instrumentos y golpes de La Chilinga. Espejo de instruments.js y PartituraMuestras.php. */

export const INSTRUMENTOS: { id: string; nombre: string; corto: string }[] = [
  { id: 'todos', nombre: 'Todos', corto: 'Tod' },
  { id: 'surdo_grave', nombre: 'Surdo Grave', corto: 'S.Gr' },
  { id: 'surdo_agudo', nombre: 'Surdo Agudo', corto: 'S.Ag' },
  { id: 'surdo_medio', nombre: 'Surdo Medio', corto: 'S.Me' },
  { id: 'redoblante', nombre: 'Redoblante', corto: 'Redo' },
  { id: 'repique', nombre: 'Repique', corto: 'Repi' },
  { id: 'timbal', nombre: 'Timbal', corto: 'Timb' },
  { id: 'agogo', nombre: 'Agogó', corto: 'Ago' },
  { id: 'palmas', nombre: 'Palmas', corto: 'Palm' },
];

export const INSTRUMENTOS_DEFAULT = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal'];

export const GOLPES_POR_INSTRUMENTO: Record<string, string[]> = {
  todos: ['nota', 'acentuado', 'chapa', 'tapado', 'flam', 'fantasma'],
  surdo_grave: ['nota', 'acentuado', 'chapa', 'tapado', 'flam', 'fantasma'],
  surdo_agudo: ['nota', 'acentuado', 'chapa', 'tapado', 'flam', 'fantasma'],
  surdo_medio: ['nota', 'acentuado', 'chapa', 'tapado', 'flam', 'fantasma'],
  redoblante: ['nota', 'acentuado', 'chapa', 'agudo', 'tapado', 'flam', 'fantasma'],
  repique: ['nota', 'acentuado', 'chapa', 'agudo', 'flam', 'fantasma'],
  timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo', 'acentuado', 'fantasma'],
  agogo: ['nota', 'acentuado', 'tapado', 'fantasma'],
  palmas: ['nota', 'acentuado', 'fantasma'],
};

export const SIMBOLOS: Record<string, string> = {
  nota: '●',
  acentuado: '>',
  chapa: '✕',
  tapado: '—',
  presionado: '=',
  abierto: '●',
  slap: '○',
  palma: '●',
  dedo: '✕',
  agudo: '▲',
  flam: 'fl',
  fantasma: '◦',
};

export const ETIQUETA_GOLPE: Record<string, string> = {
  nota: 'Golpe',
  acentuado: 'Acentuado',
  chapa: 'Chapa',
  tapado: 'Tapado',
  presionado: 'Presionado',
  abierto: 'Abierto',
  slap: 'Slap',
  palma: 'Palma',
  dedo: 'Dedos',
  agudo: 'Agudo',
  flam: 'Flam',
  fantasma: 'Fantasma',
};

const GANANCIA: Record<string, number> = {
  nota: 1, acentuado: 1.2, chapa: 0.85, tapado: 0.6, presionado: 0.55,
  abierto: 1.1, slap: 1.15, palma: 0.9, dedo: 0.5, agudo: 1, flam: 1, fantasma: 0.35,
};

export const GANANCIA_TIMBRE: Record<string, number> = {
  surdo_grave: 0.95, surdo_medio: 0.78, surdo_agudo: 0.72,
  redoblante: 1, repique: 0.86, timbal: 0.88, agogo: 0.38, palmas: 0.65, todos: 0.85,
};

/** Desfase del unísono, en segundos. Evita que las seis membranas caigan en fase. */
export const DESFASE_UNISONO: Record<string, number> = {
  surdo_grave: 0, surdo_medio: 0.004, surdo_agudo: 0.007, redoblante: 0.003,
  repique: 0.006, timbal: 0.008, agogo: 0.005, palmas: 0.002,
};

const MAPA_SAMPLES: Record<string, string[]> = {
  surdo_grave: ['nota', 'chapa', 'tapado'],
  surdo_medio: ['nota', 'chapa', 'tapado'],
  surdo_agudo: ['nota', 'chapa', 'tapado'],
  redoblante: ['nota', 'acentuado', 'chapa', 'agudo'],
  timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo'],
  repique: ['nota', 'acentuado', 'chapa', 'agudo'],
  agogo: ['nota', 'acentuado', 'tapado'],
  palmas: ['nota', 'acentuado'],
};

const ARTICULACION: Record<string, string> = {
  nota: 'normal', acentuado: 'acentuado', chapa: 'chapa', tapado: 'tapado',
  abierto: 'abierto', slap: 'slap', palma: 'palma', presionado: 'presionado',
  dedo: 'dedo', agudo: 'agudo',
};

export function nombreDe(id: string): string {
  return INSTRUMENTOS.find((i) => i.id === id)?.nombre ?? id;
}

export function golpesDe(instId: string): string[] {
  return GOLPES_POR_INSTRUMENTO[instId] ?? ['nota'];
}

export function gananciaDe(stroke: string): number {
  return GANANCIA[stroke] ?? 1;
}

export function archivoDe(instId: string, strokeId: string): string {
  const art = ARTICULACION[strokeId] ?? strokeId;
  return `${instId}_${art}.wav`;
}

export interface ResolucionGolpe {
  instId: string;
  strokeId: string;
  vel: number;
  flam: boolean;
}

/** Ningún golpe de la paleta queda mudo: si no hay WAV, cae al sample base. */
export function resolverGolpe(instId: string, strokeId: string): ResolucionGolpe {
  const strokes = MAPA_SAMPLES[instId];
  const base = instId === 'timbal' ? 'abierto' : 'nota';
  if (strokes?.includes(strokeId)) return { instId, strokeId, vel: 1, flam: false };
  if (strokeId === 'acentuado' && strokes?.includes(base)) return { instId, strokeId: base, vel: 1.28, flam: false };
  if (strokeId === 'flam') {
    const s = strokes?.includes(base) ? base : strokes?.[0];
    if (s) return { instId, strokeId: s, vel: 1, flam: true };
  }
  if (strokeId === 'fantasma' && strokes?.length) return { instId, strokeId: strokes.includes(base) ? base : strokes[0], vel: 1, flam: false };
  if (strokeId === 'tapado' && strokes?.includes('nota')) return { instId, strokeId: 'nota', vel: 0.72, flam: false };
  if (strokeId === 'nota' && strokes?.includes('abierto')) return { instId, strokeId: 'abierto', vel: 1, flam: false };
  if (strokeId === 'abierto' && strokes?.includes('nota')) return { instId, strokeId: 'nota', vel: 1, flam: false };
  if (strokes?.length) return { instId, strokeId: strokes[0], vel: 0.9, flam: false };
  return { instId: 'surdo_grave', strokeId: 'nota', vel: 0.8, flam: false };
}

export function vocesDeUnisono(ids: string[]): string[] {
  const reales = ids.filter((id) => id !== 'todos');
  return reales.length ? reales : INSTRUMENTOS_DEFAULT;
}

/** Siguiente golpe al tocar una celda. null = silencio. */
export function cicloGolpe(instId: string, actual: string | null): string | null {
  const lista = golpesDe(instId);
  if (actual === null) return lista[0] ?? 'nota';
  const i = lista.indexOf(actual);
  if (i < 0 || i === lista.length - 1) return null;
  return lista[i + 1];
}
