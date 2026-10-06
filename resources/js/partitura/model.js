/**
 * Modelo de partitura v5 — percusión multi-instrumento, duraciones reales.
 * v5 suma sobre v4 (se lee cualquiera de los dos): golpe `fantasma`, `vel` por nota,
 * `pan`/`pitch` por instrumento y `sena` (seña de dirección) por compás.
 *
 * Fuente de duraciones: hoja «Equivalencias» del Cuadernillo de Toques
 * (database/data/partituras-v4/revision/EQUIVALENCIAS.md).
 * Unidad: ticks; TPQ = 48 por negra (= 1 tiempo).
 */
import {
    INSTRUMENTOS, INSTRUMENTOS_DEFAULT, instrumentoPorId, golpeDefault, GOLPES,
    resolverStroke, tipoGolpeDe,
} from './instruments.js';

export const VERSION = 5;
export const TPQ = 48;

/**
 * Figuras del cuadernillo (Equivalencias).
 * `tiempos` = negras en 4/4. Barras: 2 corcheas / 4 semis / 8 fusas por tiempo.
 */
export const DURACIONES = [
    { code: 'w', label: 'Redonda', tiempos: 4, ticks: TPQ * 4, tecla: '1' },
    { code: 'h', label: 'Blanca', tiempos: 2, ticks: TPQ * 2, tecla: '2' },
    { code: 'q', label: 'Negra', tiempos: 1, ticks: TPQ, tecla: '3' },
    { code: '8', label: 'Corchea', tiempos: 0.5, ticks: TPQ / 2, tecla: '4' },
    { code: '16', label: 'Semicorchea', tiempos: 0.25, ticks: TPQ / 4, tecla: '5' },
    { code: '32', label: 'Fusa', tiempos: 0.125, ticks: TPQ / 8, tecla: '6' },
];

/**
 * Herramientas de escritura (como en Flat.io / la hoja Figuras y silencios).
 * `tiempos` es el texto corto bajo el botón (en 4/4, negra = 1 tiempo).
 */
export const HERRAMIENTAS_FIGURA = [
    { id: 'w', grupo: 'figuras', kind: 'nota', dur: 'w', dots: 0, label: 'Redonda', tiempos: '4 t', tecla: '1' },
    { id: 'h', grupo: 'figuras', kind: 'nota', dur: 'h', dots: 0, label: 'Blanca', tiempos: '2 t', tecla: '2' },
    { id: 'h.', grupo: 'figuras', kind: 'nota', dur: 'h', dots: 1, label: 'Blanca con puntillo', tiempos: '3 t' },
    { id: 'q', grupo: 'figuras', kind: 'nota', dur: 'q', dots: 0, label: 'Negra', tiempos: '1 t', tecla: '3' },
    { id: 'q.', grupo: 'figuras', kind: 'nota', dur: 'q', dots: 1, label: 'Negra con puntillo', tiempos: '1½ t' },
    { id: '8', grupo: 'figuras', kind: 'nota', dur: '8', dots: 0, label: 'Corchea', tiempos: '½ t', tecla: '4' },
    { id: '8.', grupo: 'figuras', kind: 'nota', dur: '8', dots: 1, label: 'Corchea con puntillo', tiempos: '¾ t' },
    { id: '16', grupo: 'figuras', kind: 'nota', dur: '16', dots: 0, label: 'Semicorchea', tiempos: '¼ t', tecla: '5' },
    { id: '32', grupo: 'figuras', kind: 'nota', dur: '32', dots: 0, label: 'Fusa', tiempos: '⅛ t', tecla: '6' },
    { id: '8x2', grupo: 'grupos', kind: 'grupo', dur: '8', count: 2, label: '2 corcheas', tiempos: '1 t' },
    { id: '16x2', grupo: 'grupos', kind: 'grupo', dur: '16', count: 2, label: '2 semicorcheas', tiempos: '½ t' },
    { id: '16x4', grupo: 'grupos', kind: 'grupo', dur: '16', count: 4, label: '4 semicorcheas', tiempos: '1 t' },
    { id: '32x8', grupo: 'grupos', kind: 'grupo', dur: '32', count: 8, label: '8 fusas', tiempos: '1 t' },
    { id: '3:2-8', grupo: 'grupos', kind: 'tuplet', dur: '8', num: 3, den: 2, label: 'Tresillo de corcheas', tiempos: '1 t' },
    { id: '3:2-16', grupo: 'grupos', kind: 'tuplet', dur: '16', num: 3, den: 2, label: 'Tresillo de semicorcheas', tiempos: '½ t' },
    { id: '6:4-16', grupo: 'grupos', kind: 'tuplet', dur: '16', num: 6, den: 4, label: 'Sextillo', tiempos: '1 t' },
    { id: 'wr', grupo: 'silencios', kind: 'silencio', dur: 'w', label: 'Silencio de redonda', tiempos: '4 t' },
    { id: 'hr', grupo: 'silencios', kind: 'silencio', dur: 'h', label: 'Silencio de blanca', tiempos: '2 t' },
    { id: 'qr', grupo: 'silencios', kind: 'silencio', dur: 'q', label: 'Silencio de negra', tiempos: '1 t' },
    { id: '8r', grupo: 'silencios', kind: 'silencio', dur: '8', label: 'Silencio de corchea', tiempos: '½ t' },
    { id: '16r', grupo: 'silencios', kind: 'silencio', dur: '16', label: 'Silencio de semicorchea', tiempos: '¼ t' },
];

export function herramientaPorId(id) {
    return HERRAMIENTAS_FIGURA.find((h) => h.id === id) || null;
}

const DUR_TICKS = DURACIONES.reduce((acc, d) => ({ ...acc, [d.code]: d.ticks }), {});

let uid = 0;
export function nextId(prefix = 'n') {
    uid += 1;
    return `${prefix}${Date.now().toString(36)}${uid.toString(36)}`;
}

/** Ticks de una nota, incluyendo puntillos y tresillos. */
export function ticksDeNota(nota) {
    const base = DUR_TICKS[nota.dur] ?? TPQ;
    let t = base;
    const dots = Math.min(2, Math.max(0, nota.dots || 0));
    if (dots === 1) t = base * 1.5;
    if (dots === 2) t = base * 1.75;
    if (nota.tuplet && nota.tuplet.num > 0 && nota.tuplet.den > 0) {
        t = (t * nota.tuplet.den) / nota.tuplet.num;
    }
    return Math.round(t);
}

/** Capacidad de un compás en ticks. */
export function ticksDeCompas(timeSignature) {
    const num = timeSignature?.num || 4;
    const den = timeSignature?.den || 4;
    return Math.round((num * TPQ * 4) / den);
}

export function ticksDeVoz(voz) {
    return (voz || []).reduce((sum, n) => sum + ticksDeNota(n), 0);
}

export function crearNota({
    dur = 'q', dots = 0, rest = false, stroke = 'nota', dyn = null, tuplet = null, digitacion = null, vel = null,
} = {}) {
    return {
        id: nextId(),
        dur,
        dots,
        rest,
        stroke,
        dyn,
        tuplet,
        digitacion: digitacion === 'D' || digitacion === 'I' ? digitacion : null,
        vel: rest || vel === null || vel === undefined ? null : clampVel(vel),
    };
}

function clampVel(v) {
    const n = parseInt(v, 10);
    return Number.isFinite(n) ? Math.min(127, Math.max(1, n)) : null;
}

/** Descompone una cantidad de ticks en silencios "limpios". */
export function silenciosPara(ticks) {
    const out = [];
    let resto = Math.round(ticks);
    const escala = [
        { code: 'w', dots: 0, t: TPQ * 4 },
        { code: 'h', dots: 1, t: TPQ * 3 },
        { code: 'h', dots: 0, t: TPQ * 2 },
        { code: 'q', dots: 1, t: Math.round(TPQ * 1.5) },
        { code: 'q', dots: 0, t: TPQ },
        { code: '8', dots: 1, t: Math.round(TPQ * 0.75) },
        { code: '8', dots: 0, t: TPQ / 2 },
        { code: '16', dots: 0, t: TPQ / 4 },
        { code: '32', dots: 0, t: TPQ / 8 },
    ];
    let guard = 0;
    while (resto > 0 && guard < 64) {
        guard += 1;
        const paso = escala.find((e) => e.t <= resto);
        if (!paso) break;
        out.push(crearNota({ dur: paso.code, dots: paso.dots, rest: true }));
        resto -= paso.t;
    }
    return out;
}

/**
 * Ajusta una voz a la capacidad del compás: recorta lo que sobra y rellena con silencios.
 * Nunca corta grupos de tresillo por la mitad.
 */
export function ajustarVoz(voz, capacidad) {
    const out = [];
    let acum = 0;
    (voz || []).forEach((nota) => {
        const t = ticksDeNota(nota);
        if (acum + t > capacidad) return;
        out.push(nota);
        acum += t;
    });
    if (acum < capacidad) out.push(...silenciosPara(capacidad - acum));
    return out;
}

export function crearCompas(instrumentos, timeSignature) {
    const capacidad = ticksDeCompas(timeSignature);
    const voces = {};
    instrumentos.forEach((id) => {
        voces[id] = silenciosPara(capacidad);
    });
    return {
        id: nextId('m'),
        repeatBegin: false,
        repeatEnd: false,
        ending: null,
        texto: null,
        sena: null,
        voces,
    };
}

export function crearSeccion(nombre, instrumentos, timeSignature, compases = 1) {
    return {
        id: nextId('s'),
        name: nombre,
        repeatX: 1,
        measures: Array.from({ length: compases }, () => crearCompas(instrumentos, timeSignature)),
    };
}

export function crearPartitura({ title = 'Toque nuevo', autor = '', instrumentos = INSTRUMENTOS_DEFAULT } = {}) {
    const timeSignature = { num: 4, den: 4 };
    return {
        version: VERSION,
        title,
        autor,
        tempo: 100,
        timeSignature,
        instruments: instrumentos.map((id) => instrumentoConfig(id)),
        sections: [crearSeccion('Llamada', instrumentos, timeSignature, 1), crearSeccion('Toque', instrumentos, timeSignature, 2)],
    };
}

function instrumentoConfig(id) {
    const base = instrumentoPorId(id) || INSTRUMENTOS[0];
    return { id: base.id, volume: 0.9, mute: false, solo: false, visible: true, pan: 0, pitch: 0 };
}

/** ---------------------------------------------------------------- normalización */

function normNota(raw, instId) {
    if (!raw || typeof raw !== 'object') return null;
    // Alias plano del requisito: figura / tipoGolpe / isTresillo
    const durCode = raw.dur || raw.figura;
    const dur = DUR_TICKS[durCode] ? durCode : 'q';
    const rest = !!raw.rest;
    const strokeRaw = raw.stroke || raw.tipoGolpe;
    let stroke = resolverStroke(strokeRaw, instId);
    if (!GOLPES[stroke]) stroke = golpeDefault(instId);
    if (rest) stroke = 'nota';
    let tuplet = null;
    if (raw.tuplet && typeof raw.tuplet === 'object') {
        const num = parseInt(raw.tuplet.num, 10);
        const den = parseInt(raw.tuplet.den, 10);
        if (num > 1 && den > 0) {
            tuplet = { id: String(raw.tuplet.id || nextId('t')), num, den };
        }
    } else if (raw.isTresillo === true) {
        tuplet = { id: String(raw.tupletId || nextId('t')), num: 3, den: 2 };
    }
    const dig = raw.digitacion === 'D' || raw.digitacion === 'I' ? raw.digitacion : null;
    return {
        id: String(raw.id || nextId()),
        dur,
        dots: Math.min(2, Math.max(0, parseInt(raw.dots, 10) || 0)),
        rest,
        stroke,
        dyn: typeof raw.dyn === 'string' && raw.dyn ? raw.dyn : null,
        tuplet,
        digitacion: rest ? null : dig,
        vel: rest || raw.vel === null || raw.vel === undefined ? null : clampVel(raw.vel),
    };
}

/**
 * Vista plana de una nota (API / docs del requisito).
 * El modelo canónico sigue siendo v4 anidado en voces[instrumento].
 * @param {string} instId
 * @param {object} nota
 */
export function notaAGolpePlano(instId, nota) {
    return {
        instrumento: instId,
        figura: nota.dur,
        isTresillo: !!(nota.tuplet && nota.tuplet.num === 3 && nota.tuplet.den === 2),
        tipoGolpe: tipoGolpeDe(nota.stroke) || nota.stroke,
        digitacion: nota.digitacion || null,
        stroke: nota.stroke,
        tuplet: nota.tuplet,
        rest: !!nota.rest,
        dyn: nota.dyn || null,
    };
}

const DYN_VEL = { pp: 0.35, p: 0.55, mp: 0.72, mf: 0.9, f: 1.1, ff: 1.3 };

/** Ticks de una negra según el denominador del compás. */
export function ticksPorBeat(timeSignature) {
    const den = timeSignature?.den || 4;
    return Math.round((TPQ * 4) / den);
}

/**
 * Posición musical dentro del compás (1-based beat).
 * subdivision = ticks restantes dentro del beat (0 = ataque en el tiempo).
 */
export function tickAPosicion(tickLocal, timeSignature) {
    const tpB = ticksPorBeat(timeSignature);
    const t = Math.max(0, Math.round(tickLocal));
    const beat0 = Math.floor(t / tpB);
    return {
        beat: beat0 + 1,
        subdivision: t - beat0 * tpB,
        ticksPorBeat: tpB,
    };
}

/**
 * Intensidad de reproducción (1 ≈ golpe pleno mf). Una sola regla: `vel` (1–127) si la
 * nota lo tiene; si no, golpe × dinámica.
 */
export function velocidadDeNota(nota) {
    if (nota.vel) return Math.min(1.4, Math.max(0.05, nota.vel / 100));
    const golpe = GOLPES[nota.stroke] || GOLPES.nota;
    const dyn = nota.dyn ? (DYN_VEL[nota.dyn] || 1) : 1;
    return dyn * (golpe.gain || 1);
}

/** ¿Suena el instrumento con el mute/solo actual? (misma regla que el mixer). */
export function esAudible(score, instId) {
    const insts = score.instruments || [];
    const cfg = insts.find((i) => i.id === instId);
    if (!cfg || cfg.mute) return false;
    const soloActivo = insts.some((i) => i.solo);
    if (!soloActivo) return true;
    const todos = insts.find((i) => i.id === 'todos');
    if (todos?.solo && instId !== 'todos') return true;
    return !!cfg.solo;
}

/**
 * Eventos musicales planos derivados del score v4 (misma fuente que el render).
 * Respeta repeatX y barras de repetición vía expandirTimeline.
 *
 * @returns {Array<{
 *   instrument: string, articulation: string, measure: number,
 *   beat: number, subdivision: number, velocity: number,
 *   sectionIdx: number, measureIdx: number, tickLocal: number,
 *   absTick: number, noteId: string, dyn: string|null
 * }>}
 */
export function eventosMusicales(score) {
    const ts = score.timeSignature || { num: 4, den: 4 };
    const cap = ticksDeCompas(ts);
    const timeline = expandirTimeline(score);
    const out = [];
    let absTick = 0;
    let measureOrdinal = 0;
    timeline.forEach((pos) => {
        measureOrdinal += 1;
        const m = score.sections[pos.sectionIdx]?.measures[pos.measureIdx];
        if (!m) return;
        Object.entries(m.voces || {}).forEach(([instId, voz]) => {
            let local = 0;
            (voz || []).forEach((n) => {
                const dur = ticksDeNota(n);
                if (!n.rest) {
                    const p = tickAPosicion(local, ts);
                    out.push({
                        instrument: instId,
                        articulation: n.stroke,
                        measure: measureOrdinal,
                        beat: p.beat,
                        subdivision: p.subdivision,
                        velocity: velocidadDeNota(n),
                        sectionIdx: pos.sectionIdx,
                        measureIdx: pos.measureIdx,
                        tickLocal: local,
                        absTick,
                        noteId: n.id,
                        dyn: n.dyn || null,
                    });
                }
                local += dur;
            });
        });
        absTick += cap;
    });
    return out;
}

/** Segundos de una negra al BPM dado. */
export function duracionNegra(bpm) {
    return 60 / Math.max(1, Number(bpm) || 100);
}

export function segundosDeTicks(ticks, bpm) {
    return ticks * (duracionNegra(bpm) / TPQ);
}

/** @param {unknown} raw */
export function normalizarPartitura(raw) {
    if (!raw || typeof raw !== 'object' || !Array.isArray(raw.sections) || !raw.sections.length) {
        return crearPartitura();
    }

    const num = Math.min(12, Math.max(1, parseInt(raw.timeSignature?.num, 10) || 4));
    const den = [2, 4, 8, 16].includes(parseInt(raw.timeSignature?.den, 10)) ? parseInt(raw.timeSignature.den, 10) : 4;
    const timeSignature = { num, den };
    const capacidad = ticksDeCompas(timeSignature);

    const ids = [];
    const instruments = [];
    (Array.isArray(raw.instruments) ? raw.instruments : []).forEach((i) => {
        const id = typeof i === 'string' ? i : i?.id;
        if (!id || !instrumentoPorId(id) || ids.includes(id)) return;
        ids.push(id);
        instruments.push({
            id,
            volume: clamp(parseFloat(i?.volume ?? 0.9), 0, 1.5),
            mute: !!i?.mute,
            solo: !!i?.solo,
            visible: i?.visible === undefined ? true : !!i.visible,
            pan: clamp(parseFloat(i?.pan ?? 0), -1, 1) || 0,
            pitch: Math.min(12, Math.max(-12, parseInt(i?.pitch ?? 0, 10) || 0)),
        });
    });
    if (!instruments.length) {
        INSTRUMENTOS_DEFAULT.forEach((id) => {
            ids.push(id);
            instruments.push(instrumentoConfig(id));
        });
    }

    const sections = raw.sections.map((sec, si) => {
        const measuresRaw = Array.isArray(sec?.measures) && sec.measures.length ? sec.measures : [null];
        return {
            id: String(sec?.id || nextId('s')),
            name: typeof sec?.name === 'string' && sec.name.trim() ? sec.name.trim() : `Parte ${si + 1}`,
            repeatX: Math.min(16, Math.max(1, parseInt(sec?.repeatX, 10) || 1)),
            agrupar: sec?.agrupar === 'agudos-graves' ? 'agudos-graves' : null,
            measures: measuresRaw.slice(0, 64).map((m) => {
                const voces = {};
                ids.forEach((id) => {
                    const vozRaw = Array.isArray(m?.voces?.[id]) ? m.voces[id] : [];
                    const voz = vozRaw.map((n) => normNota(n, id)).filter(Boolean);
                    voces[id] = ajustarVoz(voz, capacidad);
                });
                const ending = parseInt(m?.ending, 10);
                return {
                    id: String(m?.id || nextId('m')),
                    repeatBegin: !!m?.repeatBegin,
                    repeatEnd: !!m?.repeatEnd,
                    ending: ending >= 1 && ending <= 4 ? ending : null,
                    texto: typeof m?.texto === 'string' && m.texto.trim() ? m.texto.trim().slice(0, 40) : null,
                    sena: normalizarSena(m?.sena, ids),
                    voces,
                };
            }),
        };
    });

    const out = {
        version: VERSION,
        title: String(raw.title || 'Toque').slice(0, 160),
        autor: String(raw.autor || '').slice(0, 200),
        tempo: Math.min(260, Math.max(30, parseInt(raw.tempo, 10) || 100)),
        timeSignature,
        instruments,
        sections,
    };

    // Sello de origen (partituras-v4/NN-slug.json + hash). Se conserva tal cual para
    // que el seeder pueda detectar cuando lo guardado en la base quedó viejo.
    const fuente = normalizarFuente(raw.fuente);
    if (fuente) out.fuente = fuente;
    const source = normalizarSourcePdf(raw.source);
    if (source) out.source = source;

    return out;
}

const SENAS_IDS = ['entrada', 'corte', 'llamada', 'cambio', 'otra'];

export function normalizarSena(raw, ids = []) {
    if (!raw || typeof raw !== 'object') return null;
    const texto = String(raw.texto || '').trim().slice(0, 80);
    if (!texto) return null;
    return {
        texto,
        tipo: SENAS_IDS.includes(raw.tipo) ? raw.tipo : 'otra',
        instrumento: ids.includes(raw.instrumento) ? raw.instrumento : null,
    };
}

function normalizarFuente(raw) {
    if (!raw || typeof raw !== 'object') return null;
    const origen = String(raw.origen || '').trim().slice(0, 80);
    const hash = String(raw.hash || '').replace(/[^a-f0-9]/gi, '').slice(0, 40);
    if (!origen || !hash) return null;
    return { origen, hash };
}

/** Fuente primaria: PDF de Toques (páginas de archivo). */
function normalizarSourcePdf(raw) {
    if (!raw || typeof raw !== 'object') return null;
    const type = String(raw.type || '').trim().slice(0, 20);
    const file = String(raw.file || '').trim().slice(0, 120);
    const pages = Array.isArray(raw.pages)
        ? raw.pages.map((n) => parseInt(n, 10)).filter((n) => n >= 1 && n <= 200)
        : [];
    if (type !== 'pdf' || !file) return null;
    return { type, file, pages };
}

function clamp(n, min, max) {
    if (Number.isNaN(n)) return max;
    return Math.min(max, Math.max(min, n));
}

/** ---------------------------------------------------------------- operaciones de edición */

export const ops = {
    /** Cambia duración de una nota y re-ajusta el compás. */
    setDuracion(score, sel, dur) {
        const voz = vozDe(score, sel);
        if (!voz) return false;
        const nota = voz[sel.noteIdx];
        if (!nota) return false;
        nota.dur = dur;
        reajustar(score, sel);
        return true;
    },

    /** Reemplaza la nota seleccionada (o inserta después) por una figura / grupo / silencio. */
    aplicarHerramienta(score, sel, herramienta, { insertar = false } = {}) {
        const voz = vozDe(score, sel);
        if (!voz || !herramienta) return false;
        const idx = insertar ? sel.noteIdx + 1 : sel.noteIdx;
        if (idx < 0 || idx > voz.length || (!insertar && !voz[idx])) return false;
        const stroke = golpeDefault(sel.instId);
        const notas = notasDeHerramienta(herramienta, stroke);
        if (!notas.length) return false;
        if (insertar) voz.splice(idx, 0, ...notas);
        else voz.splice(idx, 1, ...notas);
        reajustar(score, sel);
        return notas.length;
    },

    /** Deja una sola parte con un compás vacío (se puede deshacer). Conserva título, tempo e instrumentos. */
    vaciarPartitura(score) {
        const ids = (score.instruments || []).map((i) => i.id).filter((id) => instrumentoPorId(id));
        const instrumentos = ids.length ? ids : INSTRUMENTOS_DEFAULT;
        if (!score.instruments?.length) {
            score.instruments = instrumentos.map((id) => instrumentoConfig(id));
        }
        score.sections = [crearSeccion('Llamada', score.instruments.map((i) => i.id), score.timeSignature, 1)];
        return true;
    },

    toggleDot(score, sel, dots = 1) {
        const nota = notaDe(score, sel);
        if (!nota) return false;
        nota.dots = nota.dots === dots ? 0 : dots;
        reajustar(score, sel);
        return true;
    },

    toggleSilencio(score, sel) {
        const nota = notaDe(score, sel);
        if (!nota) return false;
        nota.rest = !nota.rest;
        if (nota.rest) {
            nota.dyn = null;
            nota.digitacion = null;
        } else {
            nota.stroke = golpeDefault(sel.instId);
        }
        return true;
    },

    setGolpe(score, sel, stroke) {
        const nota = notaDe(score, sel);
        if (!nota) return false;
        nota.rest = false;
        nota.stroke = resolverStroke(stroke, sel.instId);
        return true;
    },

    /** Digitación D / I debajo del pentagrama (ejercicios pedagógicos). */
    setDigitacion(score, sel, dig) {
        const nota = notaDe(score, sel);
        if (!nota || nota.rest) return false;
        const next = dig === 'D' || dig === 'I' ? dig : null;
        nota.digitacion = nota.digitacion === next ? null : next;
        return true;
    },

    setDinamica(score, sel, dyn) {
        const nota = notaDe(score, sel);
        if (!nota) return false;
        nota.dyn = nota.dyn === dyn ? null : dyn;
        return true;
    },

    /** Inserta una nota después de la seleccionada, comiendo del silencio siguiente. */
    insertarDespues(score, sel, { dur = '8', rest = false, stroke = null } = {}) {
        const voz = vozDe(score, sel);
        if (!voz) return false;
        const nota = crearNota({ dur, rest, stroke: stroke || golpeDefault(sel.instId) });
        voz.splice(sel.noteIdx + 1, 0, nota);
        reajustar(score, sel);
        return true;
    },

    borrar(score, sel) {
        const voz = vozDe(score, sel);
        if (!voz || voz.length <= 1) return false;
        voz.splice(sel.noteIdx, 1);
        reajustar(score, sel);
        return true;
    },

    /** Convierte la nota seleccionada en un grupo irregular (tresillo/sextillo). */
    tuplet(score, sel, num = 3, den = 2) {
        const voz = vozDe(score, sel);
        if (!voz) return false;
        const nota = voz[sel.noteIdx];
        if (!nota) return false;
        if (nota.tuplet) {
            // Deshacer: quitar todo el grupo y dejar una nota simple
            const gid = nota.tuplet.id;
            const first = voz.findIndex((n) => n.tuplet?.id === gid);
            const count = voz.filter((n) => n.tuplet?.id === gid).length;
            const base = voz[first];
            voz.splice(first, count, crearNota({
                dur: base.dur, dots: 0, rest: base.rest, stroke: base.stroke, digitacion: base.digitacion,
            }));
            reajustar(score, sel);
            return true;
        }
        const gid = nextId('t');
        const nuevas = Array.from({ length: num }, (_, i) =>
            crearNota({
                dur: nota.dur,
                dots: 0,
                rest: i === 0 ? nota.rest : false,
                stroke: nota.stroke,
                digitacion: i === 0 ? nota.digitacion : null,
                tuplet: { id: gid, num, den },
            })
        );
        voz.splice(sel.noteIdx, 1, ...nuevas);
        reajustar(score, sel);
        return true;
    },

    /** Copia la voz de un compás a otros compases de la misma sección. */
    copiarVoz(score, sel, destinos = []) {
        const voz = vozDe(score, sel);
        if (!voz) return false;
        const sec = score.sections[sel.sectionIdx];
        destinos.forEach((mi) => {
            const m = sec.measures[mi];
            if (!m) return;
            m.voces[sel.instId] = JSON.parse(JSON.stringify(voz)).map((n) => ({ ...n, id: nextId() }));
        });
        return true;
    },

    limpiarCompas(score, sel) {
        const m = score.sections[sel.sectionIdx]?.measures[sel.measureIdx];
        if (!m) return false;
        m.voces[sel.instId] = silenciosPara(ticksDeCompas(score.timeSignature));
        return true;
    },

    agregarCompas(score, sectionIdx, despuesDe = null) {
        const sec = score.sections[sectionIdx];
        if (!sec) return false;
        const nuevo = crearCompas(score.instruments.map((i) => i.id), score.timeSignature);
        if (despuesDe === null) sec.measures.push(nuevo);
        else sec.measures.splice(despuesDe + 1, 0, nuevo);
        return true;
    },

    borrarCompas(score, sectionIdx, measureIdx) {
        const sec = score.sections[sectionIdx];
        if (!sec || sec.measures.length <= 1) return false;
        sec.measures.splice(measureIdx, 1);
        return true;
    },

    agregarSeccion(score, nombre = 'Parte nueva') {
        score.sections.push(crearSeccion(nombre, score.instruments.map((i) => i.id), score.timeSignature, 1));
        return true;
    },

    borrarSeccion(score, sectionIdx) {
        if (score.sections.length <= 1) return false;
        score.sections.splice(sectionIdx, 1);
        return true;
    },

    setInstrumentos(score, ids) {
        const capacidad = ticksDeCompas(score.timeSignature);
        const anteriores = score.instruments.slice();
        score.instruments = ids
            .filter((id) => instrumentoPorId(id))
            .map((id) => anteriores.find((i) => i.id === id) || instrumentoConfig(id));
        score.sections.forEach((sec) =>
            sec.measures.forEach((m) => {
                const voces = {};
                score.instruments.forEach((i) => {
                    voces[i.id] = m.voces[i.id] ? ajustarVoz(m.voces[i.id], capacidad) : silenciosPara(capacidad);
                });
                m.voces = voces;
            })
        );
        return true;
    },

    setCompasMetrico(score, num, den) {
        score.timeSignature = { num, den };
        const capacidad = ticksDeCompas(score.timeSignature);
        score.sections.forEach((sec) =>
            sec.measures.forEach((m) => {
                Object.keys(m.voces).forEach((id) => {
                    m.voces[id] = ajustarVoz(m.voces[id], capacidad);
                });
            })
        );
        return true;
    },

    /** Compás vacío antes o después de `measureIdx`. */
    insertarCompas(score, sectionIdx, measureIdx, { antes = false } = {}) {
        const sec = score.sections[sectionIdx];
        if (!sec || sec.measures.length >= 64) return false;
        const nuevo = crearCompas(score.instruments.map((i) => i.id), score.timeSignature);
        sec.measures.splice(antes ? measureIdx : measureIdx + 1, 0, nuevo);
        return true;
    },

    /** Copia profunda de los compases [desde, hasta] (portapapeles musical). */
    copiarCompases(score, sectionIdx, desde, hasta) {
        const sec = score.sections[sectionIdx];
        if (!sec) return null;
        const a = Math.max(0, Math.min(desde, hasta));
        const b = Math.min(sec.measures.length - 1, Math.max(desde, hasta));
        return {
            timeSignature: { ...score.timeSignature },
            measures: JSON.parse(JSON.stringify(sec.measures.slice(a, b + 1))),
        };
    },

    /**
     * Pega compases empezando en `measureIdx` (pisa los existentes y agrega los que falten).
     * Los instrumentos que no están en la partitura se ignoran; los que faltan quedan en silencio.
     */
    pegarCompases(score, sectionIdx, measureIdx, clip) {
        const sec = score.sections[sectionIdx];
        if (!sec || !clip?.measures?.length) return false;
        const capacidad = ticksDeCompas(score.timeSignature);
        const ids = score.instruments.map((i) => i.id);
        clip.measures.forEach((src, k) => {
            const destino = measureIdx + k;
            if (destino >= 64) return;
            const m = clonarCompas(src, ids, capacidad);
            if (destino < sec.measures.length) sec.measures[destino] = m;
            else sec.measures.push(m);
        });
        return true;
    },

    /** Repite los compases [desde, hasta] `veces` veces justo después (R / Repetir ×N). */
    repetirCompases(score, sectionIdx, desde, hasta, veces = 1) {
        const sec = score.sections[sectionIdx];
        if (!sec) return false;
        const clip = ops.copiarCompases(score, sectionIdx, desde, hasta);
        const n = clip.measures.length;
        const capacidad = ticksDeCompas(score.timeSignature);
        const ids = score.instruments.map((i) => i.id);
        const copias = [];
        for (let v = 0; v < Math.max(1, veces); v++) {
            clip.measures.forEach((src) => copias.push(clonarCompas(src, ids, capacidad)));
        }
        const lugar = Math.max(desde, hasta) + 1;
        const libres = 64 - sec.measures.length;
        if (libres <= 0) return false;
        sec.measures.splice(lugar, 0, ...copias.slice(0, Math.floor(libres / n) * n || libres));
        return true;
    },

    /** Intensidad fina (1–127) o null para volver a golpe × dinámica. */
    setVel(score, sel, vel) {
        const nota = notaDe(score, sel);
        if (!nota || nota.rest) return false;
        nota.vel = vel === null || vel === '' ? null : clampVel(vel);
        return true;
    },

    /** Sube o baja un escalón de dinámica (pp … ff). Sin dinámica arranca en mf. */
    pasoDinamica(score, sel, delta) {
        const nota = notaDe(score, sel);
        if (!nota || nota.rest) return false;
        const escala = ['pp', 'p', 'mp', 'mf', 'f', 'ff'];
        const i = escala.indexOf(nota.dyn || 'mf');
        const j = Math.min(escala.length - 1, Math.max(0, i + delta));
        nota.dyn = escala[j] === 'mf' && !nota.dyn ? null : escala[j];
        nota.vel = null;
        return true;
    },

    setSena(score, sectionIdx, measureIdx, sena) {
        const m = score.sections[sectionIdx]?.measures[measureIdx];
        if (!m) return false;
        m.sena = normalizarSena(sena, score.instruments.map((i) => i.id));
        return true;
    },
};

/** Clona un compás con ids nuevos, ajustado a los instrumentos y la capacidad actuales. */
function clonarCompas(src, ids, capacidad) {
    const voces = {};
    ids.forEach((id) => {
        const voz = Array.isArray(src?.voces?.[id]) ? src.voces[id] : [];
        voces[id] = ajustarVoz(voz.map((n) => ({ ...n, id: nextId(), tuplet: n.tuplet ? { ...n.tuplet } : null })), capacidad);
    });
    return {
        id: nextId('m'),
        repeatBegin: !!src?.repeatBegin,
        repeatEnd: !!src?.repeatEnd,
        ending: src?.ending ?? null,
        texto: src?.texto ?? null,
        sena: src?.sena ? { ...src.sena } : null,
        voces,
    };
}

export function notaDe(score, sel) {
    const voz = vozDe(score, sel);
    return voz ? voz[sel.noteIdx] || null : null;
}

export function vozDe(score, sel) {
    if (!sel) return null;
    const m = score.sections[sel.sectionIdx]?.measures[sel.measureIdx];
    if (!m) return null;
    return m.voces[sel.instId] || null;
}

function notasDeHerramienta(h, stroke) {
    if (h.kind === 'nota') {
        return [crearNota({ dur: h.dur, dots: h.dots || 0, rest: false, stroke })];
    }
    if (h.kind === 'silencio') {
        return [crearNota({ dur: h.dur, dots: h.dots || 0, rest: true })];
    }
    if (h.kind === 'grupo') {
        const n = Math.max(2, h.count || 2);
        return Array.from({ length: n }, () => crearNota({ dur: h.dur, rest: false, stroke }));
    }
    if (h.kind === 'tuplet') {
        const num = Math.max(2, h.num || 3);
        const den = Math.max(1, h.den || 2);
        const gid = nextId('t');
        return Array.from({ length: num }, () => crearNota({
            dur: h.dur, rest: false, stroke, tuplet: { id: gid, num, den },
        }));
    }
    return [];
}

function reajustar(score, sel) {
    const m = score.sections[sel.sectionIdx]?.measures[sel.measureIdx];
    if (!m) return;
    m.voces[sel.instId] = ajustarVoz(m.voces[sel.instId], ticksDeCompas(score.timeSignature));
}

/**
 * Timeline plano para reproducción: resuelve barras de repetición y repeatX.
 * @returns {{sectionIdx:number, measureIdx:number}[]}
 */
export function expandirTimeline(score) {
    const out = [];
    score.sections.forEach((sec, si) => {
        const pasada = [];
        let inicio = 0;
        sec.measures.forEach((m, mi) => {
            if (m.repeatBegin) inicio = mi;
            pasada.push({ sectionIdx: si, measureIdx: mi });
            if (m.repeatEnd) {
                for (let k = inicio; k <= mi; k++) pasada.push({ sectionIdx: si, measureIdx: k });
                inicio = mi + 1;
            }
        });
        for (let r = 0; r < sec.repeatX; r++) out.push(...pasada);
    });
    return out;
}

export function clonar(score) {
    return JSON.parse(JSON.stringify(score));
}

export function resumen(score) {
    const compases = score.sections.reduce((n, s) => n + s.measures.length, 0);
    const golpes = score.sections.reduce(
        (n, s) => n + s.measures.reduce((k, m) => k + Object.values(m.voces).reduce((j, v) => j + v.filter((x) => !x.rest).length, 0), 0),
        0
    );
    return { partes: score.sections.length, compases, golpes, instrumentos: score.instruments.length };
}
