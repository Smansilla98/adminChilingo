/**
 * Instrumentos y golpes de La Chilinga (nomenclatura del Cuadernillo de Toques).
 * Fuentes: equivalencias de figuras, hoja Nomenclatura, Ejercicio Timbal Bahiano #5.
 */

/**
 * @typedef {Object} Instrumento
 * @property {string} id
 * @property {string} label
 * @property {string} short
 * @property {string} pitch      Línea del pentagrama de percusión (VexFlow key)
 * @property {number} stem       1 arriba / -1 abajo
 * @property {number} midi       Nota MIDI base (canal 10 / GM percussion)
 * @property {string} color
 * @property {'membrana'|'metal'|'mano'} familia
 * @property {number} freq       Frecuencia base del sintetizador
 */

/** @type {Instrumento[]} */
export const INSTRUMENTOS = [
    // Voz "Todos" del cuadernillo: unísono estricto, un solo pentagrama.
    { id: 'todos', label: 'Todos', short: 'Tod', pitch: 'b/4', stem: 1, midi: 38, color: '#6d5b45', familia: 'membrana', freq: 120 },
    { id: 'surdo_grave', label: 'Surdo Grave', short: 'S.Gr', pitch: 'e/4', stem: -1, midi: 35, color: '#e86a3c', familia: 'membrana', freq: 62 },
    { id: 'surdo_agudo', label: 'Surdo Agudo', short: 'S.Ag', pitch: 'g/4', stem: -1, midi: 36, color: '#f0a04b', familia: 'membrana', freq: 96 },
    { id: 'surdo_medio', label: 'Surdo Medio', short: 'S.Me', pitch: 'a/4', stem: -1, midi: 41, color: '#d1a054', familia: 'membrana', freq: 78 },
    { id: 'redoblante', label: 'Redoblante', short: 'Redo', pitch: 'c/5', stem: 1, midi: 38, color: '#5b9ef0', familia: 'membrana', freq: 205 },
    { id: 'repique', label: 'Repique', short: 'Repi', pitch: 'd/5', stem: 1, midi: 40, color: '#4a9a86', familia: 'membrana', freq: 300 },
    { id: 'timbal', label: 'Timbal', short: 'Timb', pitch: 'f/5', stem: 1, midi: 48, color: '#9c8ad1', familia: 'membrana', freq: 168 },
    { id: 'agogo', label: 'Agogó', short: 'Ago', pitch: 'a/5', stem: 1, midi: 67, color: '#c1432b', familia: 'metal', freq: 780 },
    { id: 'palmas', label: 'Palmas', short: 'Palm', pitch: 'b/5', stem: 1, midi: 39, color: '#b6a488', familia: 'mano', freq: 1200 },
];

/** Instrumentos que arrancan en una partitura nueva. */
export const INSTRUMENTOS_DEFAULT = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal'];

/**
 * @typedef {Object} Golpe
 * @property {string} id
 * @property {string} label
 * @property {string} short
 * @property {'normal'|'x'|'triangle'|'diamond'|'circled'|'slash'} cabeza
 * @property {string|null} articulacion  Código VexFlow (a>, a-, ao, a^)
 * @property {number} pos        4 = debajo, 3 = arriba
 * @property {number} gain
 * @property {'golpe'|'aro'|'apagado'|'palma'|'metal'} timbre
 * @property {'abierto'|'slap'|'chapa'|'palma'|'acentuado'|'presionado'|null} tipoGolpe
 */

/**
 * Cabezas según nomenclatura La Chilinga:
 * - Abierto / nota: óvalo negro
 * - Slap / tapado (timbal) / chapa: X
 * - Palma: círculo vacío (o)
 * - Presionado: nota + barra
 * - Acentuado: nota + >
 */
/** @type {Record<string, Golpe>} */
export const GOLPES = {
    nota: {
        id: 'nota', label: 'Golpe pleno', short: '●',
        cabeza: 'normal', articulacion: null, pos: 4, gain: 1, timbre: 'golpe', tipoGolpe: 'abierto',
    },
    acentuado: {
        id: 'acentuado', label: 'Acentuado', short: '>',
        cabeza: 'normal', articulacion: 'a>', pos: 4, gain: 1.2, timbre: 'golpe', tipoGolpe: 'acentuado',
    },
    chapa: {
        id: 'chapa', label: 'Chapa / aro', short: '✕',
        cabeza: 'x', articulacion: null, pos: 4, gain: 0.85, timbre: 'aro', tipoGolpe: 'chapa',
    },
    tapado: {
        id: 'tapado', label: 'Tapado', short: '—',
        // Surdos: óvalo + guión arriba (Nomenclatura).
        cabeza: 'normal', articulacion: 'a-', pos: 3, gain: 0.6, timbre: 'apagado', tipoGolpe: 'slap',
    },
    presionado: {
        id: 'presionado', label: 'Presionado', short: '=',
        cabeza: 'normal', articulacion: 'a-', pos: 4, gain: 0.55, timbre: 'apagado', tipoGolpe: 'presionado',
    },
    abierto: {
        id: 'abierto', label: 'Abierto', short: '●',
        // Timbal: óvalo negro (el círculo vacío es Palma).
        cabeza: 'normal', articulacion: null, pos: 3, gain: 1.1, timbre: 'golpe', tipoGolpe: 'abierto',
    },
    slap: {
        id: 'slap', label: 'Slap / tapado', short: '✕',
        cabeza: 'x', articulacion: null, pos: 3, gain: 1.15, timbre: 'aro', tipoGolpe: 'slap',
    },
    palma: {
        id: 'palma', label: 'Palma', short: '◆',
        // Cuadernillo: cabeza en rombo (Timbal)
        cabeza: 'diamond', articulacion: null, pos: 4, gain: 0.9, timbre: 'palma', tipoGolpe: 'palma',
    },
    dedo: {
        id: 'dedo', label: 'Dedos', short: '✕',
        cabeza: 'x', articulacion: null, pos: 4, gain: 0.5, timbre: 'golpe', tipoGolpe: 'slap',
    },
    agudo: {
        id: 'agudo', label: 'Agudo (borde)', short: '▲',
        cabeza: 'triangle', articulacion: null, pos: 4, gain: 1, timbre: 'aro', tipoGolpe: null,
    },
    flam: {
        id: 'flam', label: 'Mordente / flam', short: 'fl',
        cabeza: 'normal', articulacion: null, pos: 4, gain: 1, timbre: 'golpe', tipoGolpe: 'abierto',
    },
};

/** Alias plano (tipoGolpe del requisito) → id interno. */
export const TIPO_GOLPE_A_STROKE = {
    abierto: 'abierto',
    slap: 'slap',
    tapado: 'slap',
    chapa: 'chapa',
    palma: 'palma',
    acentuado: 'acentuado',
    presionado: 'presionado',
    nota: 'nota',
};

/** Digitación pedagógica (ejercicios tipo Timbal Bahiano). */
export const DIGITACIONES = [
    { id: 'D', label: 'Derecha', short: 'D' },
    { id: 'I', label: 'Izquierda', short: 'I' },
];

/** Golpes disponibles por instrumento (el primero es el default). */
export const GOLPES_POR_INSTRUMENTO = {
    todos: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    surdo_grave: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    surdo_agudo: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    surdo_medio: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    redoblante: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    repique: ['nota', 'acentuado', 'chapa', 'agudo', 'flam'],
    timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo', 'acentuado'],
    agogo: ['nota', 'acentuado', 'tapado'],
    palmas: ['nota', 'acentuado'],
};

/**
 * MIDI por (instrumento, golpe). GM percussion + variaciones Chilinga.
 * Timbal: abierto 48, slap 49, palma 50.
 * Redoblante: normal 38, chapa 39.
 * Surdos: abierta = midi base; chapa = rim.
 */
const MIDI_POR_GOLPE = {
    timbal: { abierto: 48, slap: 49, tapado: 49, palma: 50, dedo: 49, presionado: 48, acentuado: 48, nota: 48 },
    redoblante: { nota: 38, acentuado: 38, chapa: 39, tapado: 37, flam: 38 },
    repique: { nota: 40, acentuado: 40, chapa: 39, agudo: 43, flam: 40 },
    surdo_grave: { nota: 35, acentuado: 35, chapa: 37, tapado: 35, flam: 35 },
    surdo_medio: { nota: 41, acentuado: 41, chapa: 37, tapado: 41, flam: 41 },
    surdo_agudo: { nota: 36, acentuado: 36, chapa: 37, tapado: 36, flam: 36 },
};

export const DINAMICAS = ['pp', 'p', 'mp', 'mf', 'f', 'ff'];

export const MARCAS_TEXTO = [
    { id: 'dc', label: 'D.C.', texto: 'D.C.' },
    { id: 'dc_al_fine', label: 'D.C. al Fine', texto: 'D.C. al Fine' },
    { id: 'ds', label: 'D.S.', texto: 'D.S.' },
    { id: 'fine', label: 'Fine', texto: 'Fine' },
    { id: 'coda', label: 'Coda', texto: 'Coda' },
    { id: 'segno', label: 'Segno', texto: 'Segno' },
    { id: 'corte', label: 'Corte', texto: 'Corte' },
    { id: 'llamada', label: 'Llamada', texto: 'Llamada' },
];

/** Id del pentagrama de unísono estricto ("Todos" en el cuadernillo). */
export const UNISONO = 'todos';

/** @param {string} id */
export function esUnisono(id) {
    return id === UNISONO;
}

/**
 * Instrumentos reales que suenan cuando toca la voz "Todos".
 * @param {object} score
 * @returns {string[]}
 */
export function vocesDeUnisono(score) {
    const reales = (score?.instruments || []).map((i) => i.id).filter((id) => id !== UNISONO);
    return reales.length ? reales : INSTRUMENTOS_DEFAULT;
}

/** @param {string} id */
export function instrumentoPorId(id) {
    return INSTRUMENTOS.find((i) => i.id === id) || null;
}

/** @param {string} instId */
export function golpesDe(instId) {
    return (GOLPES_POR_INSTRUMENTO[instId] || ['nota']).map((g) => GOLPES[g]).filter(Boolean);
}

/** @param {string} instId */
export function golpeDefault(instId) {
    return (GOLPES_POR_INSTRUMENTO[instId] || ['nota'])[0];
}

/**
 * Resuelve un stroke desde id interno o alias plano (tipoGolpe).
 * @param {string} raw
 * @param {string} [instId]
 */
export function resolverStroke(raw, instId) {
    if (typeof raw !== 'string' || !raw) return golpeDefault(instId || 'timbal');
    if (GOLPES[raw]) return raw;
    const alias = TIPO_GOLPE_A_STROKE[raw.toLowerCase()];
    if (alias && GOLPES[alias]) return alias;
    return golpeDefault(instId || 'timbal');
}

/** @param {string} strokeId */
export function tipoGolpeDe(strokeId) {
    return GOLPES[strokeId]?.tipoGolpe || strokeId || null;
}

/**
 * Nota MIDI para (instrumento, golpe).
 * @param {string} instId
 * @param {string} strokeId
 */
export function midiDeGolpe(instId, strokeId) {
    const mapa = MIDI_POR_GOLPE[instId];
    if (mapa && mapa[strokeId] != null) return mapa[strokeId];
    const inst = instrumentoPorId(instId);
    const base = inst?.midi ?? 38;
    const golpe = GOLPES[strokeId] || GOLPES.nota;
    if (golpe.timbre === 'aro' || strokeId === 'chapa' || strokeId === 'slap') return Math.min(81, base + 1);
    return base;
}

/** Frecuencia aproximada desde MIDI (para síntesis / fallback). */
export function freqDeMidi(midi) {
    return 440 * (2 ** ((midi - 69) / 12));
}

/**
 * Filtro sugerido cuando no hay sample: slap/chapa → highpass, graves/abierto → lowpass.
 * @param {string} strokeId
 * @returns {{ type: BiquadFilterType, freq: number, Q: number }|null}
 */
export function filtroDeGolpe(strokeId) {
    const golpe = GOLPES[strokeId] || GOLPES.nota;
    if (golpe.timbre === 'aro' || strokeId === 'slap' || strokeId === 'chapa' || strokeId === 'dedo') {
        return { type: 'highpass', freq: 1800, Q: 0.7 };
    }
    if (golpe.timbre === 'palma') {
        return { type: 'bandpass', freq: 900, Q: 1.1 };
    }
    if (golpe.timbre === 'apagado' || strokeId === 'presionado' || strokeId === 'tapado') {
        return { type: 'lowpass', freq: 420, Q: 0.8 };
    }
    return { type: 'lowpass', freq: 2800, Q: 0.5 };
}

/** Notehead VexFlow según el golpe. */
export function cabezaVexflow(pitch, golpeId) {
    const g = GOLPES[golpeId] || GOLPES.nota;
    switch (g.cabeza) {
        case 'x': return `${pitch}/x2`;
        case 'circled': return `${pitch}/x3`;
        case 'triangle': return `${pitch}/tu`;
        case 'diamond': return `${pitch}/d`;
        case 'slash': return `${pitch}/s`;
        default: return pitch;
    }
}
