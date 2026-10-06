/**
 * Modelo v4 mínimo para escribir y escuchar. Misma unidad que model.js: ticks, TPQ 48.
 * La grilla es de semicorchea (12 ticks). Una negra ocupa cuatro celdas: el golpe y tres ligaduras.
 */
import { golpesDe, INSTRUMENTOS_DEFAULT, vocesDeUnisono, gananciaDe } from './catalogo';

export const TPQ = 48;
export const PASO = 12;

export type Dur = 'w' | 'h' | 'q' | '8' | '16' | '32';

export interface Nota {
  id?: string;
  dur: Dur;
  dots?: number;
  rest?: boolean;
  stroke?: string;
  dyn?: string | null;
  tuplet?: { id?: string; num: number; den: number } | null;
  /** Intensidad fina 1–127 (v5): si está, manda sobre golpe × dinámica. */
  vel?: number | null;
}

export interface Compas {
  id?: string;
  repeatBegin?: boolean;
  repeatEnd?: boolean;
  ending?: number | null;
  texto?: string | null;
  voces: Record<string, Nota[]>;
}

export interface Seccion {
  id?: string;
  name: string;
  repeatX: number;
  measures: Compas[];
}

export interface InstrumentoScore {
  id: string;
  volume?: number;
  mute?: boolean;
  solo?: boolean;
  visible?: boolean;
}

export interface Score {
  version?: number;
  title?: string;
  autor?: string;
  tempo?: number;
  timeSignature?: { num: number; den: number };
  instruments: InstrumentoScore[];
  sections: Seccion[];
  fuente?: unknown;
  source?: unknown;
}

const DURACIONES: Record<Dur, number> = { w: TPQ * 4, h: TPQ * 2, q: TPQ, '8': 24, '16': 12, '32': 6 };
const ESCALA: [Dur, number, number][] = [
  ['w', 0, 192], ['h', 1, 144], ['h', 0, 96], ['q', 1, 72], ['q', 0, 48],
  ['8', 1, 36], ['8', 0, 24], ['16', 0, 12], ['32', 0, 6],
];
const DYN: Record<string, number> = { pp: 0.35, p: 0.55, mp: 0.72, mf: 0.9, f: 1.1, ff: 1.3 };

let seq = 0;
const nid = () => `n${(seq += 1).toString(36)}`;

export function ticksDeCompas(ts?: { num: number; den: number }): number {
  const num = Math.min(12, Math.max(1, ts?.num || 4));
  const den = [2, 4, 8, 16].includes(ts?.den || 4) ? (ts?.den || 4) : 4;
  return Math.round((num * TPQ * 4) / den);
}

export function ticksDeNota(nota: Nota): number {
  const base = DURACIONES[nota.dur] ?? TPQ;
  const dots = Math.max(0, Math.min(2, nota.dots ?? 0));
  let t = base * (dots === 1 ? 1.5 : dots === 2 ? 1.75 : 1);
  const num = nota.tuplet?.num ?? 0;
  const den = nota.tuplet?.den ?? 0;
  if (num > 0 && den > 0) t = (t * den) / num;
  return Math.round(t);
}

export function segundosDeTicks(ticks: number, bpm: number): number {
  return ticks * (60 / Math.max(1, bpm) / TPQ);
}

function notaDe(ticks: number, rest: boolean, stroke: string): Nota[] {
  const out: Nota[] = [];
  let resto = Math.max(0, ticks);
  let guard = 0;
  while (resto > 0 && guard < 64) {
    guard += 1;
    const paso = ESCALA.find((e) => e[2] <= resto);
    if (!paso) break;
    out.push({ id: nid(), dur: paso[0], dots: paso[1], rest, stroke: rest ? 'nota' : stroke });
    resto -= paso[2];
  }
  return out;
}

export function compasVacio(ids: string[], ts?: { num: number; den: number }): Compas {
  const voces: Record<string, Nota[]> = {};
  ids.forEach((id) => { voces[id] = notaDe(ticksDeCompas(ts), true, 'nota'); });
  return { id: nid(), voces };
}

export function partituraVacia(titulo: string, autor = ''): Score {
  const ids = [...INSTRUMENTOS_DEFAULT];
  const ts = { num: 4, den: 4 };
  return {
    version: 4,
    title: titulo,
    autor,
    tempo: 88,
    timeSignature: ts,
    instruments: ids.map((id) => ({ id, volume: 0.9, mute: false, solo: false, visible: true })),
    sections: [
      { id: nid(), name: 'Llamada', repeatX: 1, measures: [compasVacio(ids, ts)] },
      { id: nid(), name: 'Toque', repeatX: 1, measures: [compasVacio(ids, ts), compasVacio(ids, ts)] },
    ],
  };
}

export interface Posicion { sectionIdx: number; measureIdx: number }

export function expandirTimeline(score: Score): Posicion[] {
  const out: Posicion[] = [];
  score.sections.forEach((sec, si) => {
    const pasada: Posicion[] = [];
    let inicio = 0;
    sec.measures.forEach((m, mi) => {
      if (m.repeatBegin) inicio = mi;
      pasada.push({ sectionIdx: si, measureIdx: mi });
      if (m.repeatEnd) {
        for (let k = inicio; k <= mi; k += 1) pasada.push({ sectionIdx: si, measureIdx: k });
        inicio = mi + 1;
      }
    });
    const veces = Math.max(1, sec.repeatX || 1);
    for (let r = 0; r < veces; r += 1) out.push(...pasada);
  });
  return out;
}

export interface EventoNota {
  instrument: string;
  articulation: string;
  velocity: number;
  sectionIdx: number;
  measureIdx: number;
  tickLocal: number;
  absTick: number;
}

export function eventosMusicales(score: Score): EventoNota[] {
  const cap = ticksDeCompas(score.timeSignature);
  const out: EventoNota[] = [];
  let absTick = 0;
  expandirTimeline(score).forEach((pos) => {
    const m = score.sections[pos.sectionIdx]?.measures[pos.measureIdx];
    if (m) {
      Object.entries(m.voces || {}).forEach(([instId, voz]) => {
        let local = 0;
        (voz || []).forEach((n) => {
          const dur = ticksDeNota(n);
          if (!n.rest) {
            const dyn = n.dyn ? (DYN[n.dyn] ?? 1) : 1;
            out.push({
              instrument: instId,
              articulation: n.stroke || (instId === 'timbal' ? 'abierto' : 'nota'),
              velocity: n.vel ? Math.min(1.4, Math.max(0.05, n.vel / 100)) : dyn * gananciaDe(n.stroke || 'nota'),
              sectionIdx: pos.sectionIdx,
              measureIdx: pos.measureIdx,
              tickLocal: local,
              absTick,
            });
          }
          local += dur;
        });
      });
    }
    absTick += cap;
  });
  return out;
}

export interface EventoPlan {
  tipo: 'nota' | 'click';
  t: number;
  inst?: string;
  stroke?: string;
  vel?: number;
  fuerte?: boolean;
  sectionIdx?: number;
  measureIdx?: number;
}

export interface PlanAudio {
  eventos: EventoPlan[];
  compases: { sectionIdx: number; measureIdx: number; t: number; dur: number }[];
  duracion: number;
  conteo: number;
}

export function planificar(score: Score, opts: { bpm?: number; soloSeccion?: number | null; conteo?: boolean; metronomo?: boolean } = {}): PlanAudio {
  const bpm = opts.bpm || score.tempo || 88;
  const cap = ticksDeCompas(score.timeSignature);
  let timeline = expandirTimeline(score);
  if (opts.soloSeccion !== null && opts.soloSeccion !== undefined) {
    timeline = timeline.filter((s) => s.sectionIdx === opts.soloSeccion);
  }
  const compases = timeline.map((pos, i) => ({
    sectionIdx: pos.sectionIdx,
    measureIdx: pos.measureIdx,
    t: segundosDeTicks(i * cap, bpm),
    dur: segundosDeTicks(cap, bpm),
  }));
  const full = expandirTimeline(score);
  const firstAbs = timeline.length
    ? Math.max(0, full.findIndex((s) => s.sectionIdx === timeline[0].sectionIdx && s.measureIdx === timeline[0].measureIdx)) * cap
    : 0;
  const notas: EventoPlan[] = eventosMusicales(score)
    .filter((ev) => (opts.soloSeccion == null || ev.sectionIdx === opts.soloSeccion) && ev.absTick >= firstAbs)
    .map((ev) => ({
      tipo: 'nota',
      t: segundosDeTicks(ev.absTick + ev.tickLocal - firstAbs, bpm),
      inst: ev.instrument,
      stroke: ev.articulation,
      vel: ev.velocity,
      sectionIdx: ev.sectionIdx,
      measureIdx: ev.measureIdx,
    }));
  const pulsos = score.timeSignature?.num || 4;
  const porPulso = Math.round((TPQ * 4) / (score.timeSignature?.den || 4));
  const conteo = opts.conteo !== false ? segundosDeTicks(cap, bpm) : 0;
  const clicks: EventoPlan[] = [];
  if (conteo) {
    for (let p = 0; p < pulsos; p += 1) clicks.push({ tipo: 'click', t: segundosDeTicks(p * porPulso, bpm), fuerte: p === 0 });
  }
  if (opts.metronomo) {
    timeline.forEach((_, mi) => {
      for (let p = 0; p < pulsos; p += 1) {
        clicks.push({ tipo: 'click', t: conteo + segundosDeTicks(mi * cap + p * porPulso, bpm), fuerte: p === 0 });
      }
    });
  }
  if (conteo) {
    notas.forEach((ev) => { ev.t += conteo; });
    compases.forEach((c) => { c.t += conteo; });
  }
  const duracion = (compases.at(-1) ? compases.at(-1)!.t + compases.at(-1)!.dur : conteo);
  return { eventos: [...clicks, ...notas].sort((a, b) => a.t - b.t), compases, duracion, conteo };
}

export function idsDe(score: Score): string[] {
  return score.instruments.map((i) => i.id);
}

export type Celda = { tipo: 'golpe' | 'liga' | 'silencio'; stroke?: string };

export function grillaDeVoz(voz: Nota[] | undefined, capacidad: number, paso = PASO): Celda[] | null {
  const n = Math.round(capacidad / paso);
  const celdas: Celda[] = Array.from({ length: n }, () => ({ tipo: 'silencio' }));
  let t = 0;
  for (const nota of voz ?? []) {
    const dur = ticksDeNota(nota);
    if (t % paso !== 0) return null;
    const desde = t / paso;
    const hasta = Math.round((t + dur) / paso);
    if (!nota.rest) {
      if (desde < n) celdas[desde] = { tipo: 'golpe', stroke: nota.stroke || 'nota' };
      for (let i = desde + 1; i < hasta && i < n; i += 1) celdas[i] = { tipo: 'liga', stroke: nota.stroke || 'nota' };
    }
    t += dur;
  }
  return celdas;
}

export function vozDesdeGrilla(celdas: Celda[], paso = PASO): Nota[] {
  const out: Nota[] = [];
  let i = 0;
  while (i < celdas.length) {
    const c = celdas[i];
    if (c.tipo === 'silencio') {
      let j = i;
      while (j < celdas.length && celdas[j].tipo === 'silencio') j += 1;
      out.push(...notaDe((j - i) * paso, true, 'nota'));
      i = j;
      continue;
    }
    const stroke = c.stroke || 'nota';
    let j = i + 1;
    while (j < celdas.length && celdas[j].tipo === 'liga') j += 1;
    out.push(...notaDe((j - i) * paso, false, stroke));
    i = j;
  }
  return out;
}

function clonar(score: Score): Score {
  return JSON.parse(JSON.stringify(score)) as Score;
}

export function escribirCelda(score: Score, seccion: number, compas: number, instId: string, celda: number, stroke: string | null): Score | null {
  const next = clonar(score);
  const m = next.sections[seccion]?.measures[compas];
  if (!m) return null;
  const cap = ticksDeCompas(next.timeSignature);
  const grilla = grillaDeVoz(m.voces[instId], cap);
  if (!grilla || celda < 0 || celda >= grilla.length) return null;
  const eraGolpe = grilla[celda].tipo === 'golpe';
  const eraLiga = grilla[celda].tipo === 'liga';
  if (stroke === null) {
    grilla[celda] = { tipo: 'silencio' };
    if (eraGolpe) {
      for (let k = celda + 1; k < grilla.length && grilla[k].tipo === 'liga'; k += 1) grilla[k] = { tipo: 'silencio' };
    }
  } else {
    grilla[celda] = { tipo: 'golpe', stroke };
    if (eraLiga) {
      for (let k = celda + 1; k < grilla.length && grilla[k].tipo === 'liga'; k += 1) grilla[k] = { tipo: 'silencio' };
    }
  }
  m.voces[instId] = vozDesdeGrilla(grilla);
  return next;
}

/** Redondea ataques a la semicorchea. Sirve para un compás con tresillos que la grilla no puede mostrar. */
export function cuantizarVoz(score: Score, seccion: number, compas: number, instId: string): Score {
  const next = clonar(score);
  const m = next.sections[seccion]?.measures[compas];
  if (!m) return score;
  const cap = ticksDeCompas(next.timeSignature);
  const n = Math.round(cap / PASO);
  const grilla: Celda[] = Array.from({ length: n }, () => ({ tipo: 'silencio' }));
  let t = 0;
  for (const nota of m.voces[instId] ?? []) {
    const dur = ticksDeNota(nota);
    if (!nota.rest) {
      const idx = Math.round(t / PASO);
      if (idx >= 0 && idx < n) grilla[idx] = { tipo: 'golpe', stroke: nota.stroke || golpesDe(instId)[0] || 'nota' };
    }
    t += dur;
  }
  m.voces[instId] = vozDesdeGrilla(grilla);
  return next;
}

export function agregarCompas(score: Score, seccion: number): Score {
  const next = clonar(score);
  const sec = next.sections[seccion];
  if (!sec) return score;
  sec.measures.push(compasVacio(idsDe(next), next.timeSignature));
  return next;
}

export function agregarSeccion(score: Score): Score {
  const next = clonar(score);
  next.sections.push({
    id: nid(),
    name: 'Nueva',
    repeatX: 1,
    measures: [compasVacio(idsDe(next), next.timeSignature)],
  });
  return next;
}

export function repeticion(score: Score, seccion: number, veces: number): Score {
  const next = clonar(score);
  const sec = next.sections[seccion];
  if (sec) sec.repeatX = Math.max(1, Math.min(16, veces));
  return next;
}

export function renombrarSeccion(score: Score, seccion: number, nombre: string): Score {
  const next = clonar(score);
  const sec = next.sections[seccion];
  if (sec) sec.name = nombre.slice(0, 40);
  return next;
}

export function tempoDe(score: Score, bpm: number): Score {
  const next = clonar(score);
  next.tempo = Math.max(60, Math.min(100, Math.round(bpm)));
  return next;
}

export function conInstrumento(score: Score, instId: string, poner: boolean): Score {
  const next = clonar(score);
  const esta = next.instruments.some((i) => i.id === instId);
  if (poner && !esta) {
    next.instruments.push({ id: instId, volume: 0.9, mute: false, solo: false, visible: true });
    next.sections.forEach((sec) => sec.measures.forEach((m) => {
      m.voces[instId] = notaDe(ticksDeCompas(next.timeSignature), true, 'nota');
    }));
  }
  if (!poner && esta && next.instruments.length > 1) {
    next.instruments = next.instruments.filter((i) => i.id !== instId);
    next.sections.forEach((sec) => sec.measures.forEach((m) => { delete m.voces[instId]; }));
  }
  return next;
}

export function paraGuardar(score: Score): Score {
  const next = clonar(score);
  next.instruments = next.instruments.map((i) => ({ ...i, mute: false, solo: false }));
  return next;
}

export function realesDe(score: Score): string[] {
  return vocesDeUnisono(idsDe(score));
}
