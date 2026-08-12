/**
 * Instrumentos y golpes de La Chilinga (nomenclatura del Cuadernillo de Toques).
 * Fuente: Toques_chilinga.pdf — pág. 1 (equivalencias) y pág. 2 (nomenclatura).
 */

/**
 * @typedef {Object} Instrumento
 * @property {string} id
 * @property {string} label
 * @property {string} short
 * @property {string} pitch      Línea del pentagrama de percusión (VexFlow key)
 * @property {number} stem       1 arriba / -1 abajo
 * @property {number} midi       Nota MIDI (canal 10)
 * @property {string} color
 * @property {'membrana'|'metal'|'mano'} familia
 * @property {number} freq       Frecuencia base del sintetizador
 */

/** @type {Instrumento[]} */
export const INSTRUMENTOS = [
    { id: 'surdo_grave', label: 'Surdo Grave', short: 'S.Gr', pitch: 'e/4', stem: -1, midi: 35, color: '#e86a3c', familia: 'membrana', freq: 62 },
    { id: 'surdo_agudo', label: 'Surdo Agudo', short: 'S.Ag', pitch: 'g/4', stem: -1, midi: 36, color: '#f0a04b', familia: 'membrana', freq: 96 },
    { id: 'surdo_medio', label: 'Surdo Medio', short: 'S.Me', pitch: 'a/4', stem: -1, midi: 41, color: '#d1a054', familia: 'membrana', freq: 78 },
    { id: 'redoblante', label: 'Redoblante', short: 'Redo', pitch: 'c/5', stem: 1, midi: 38, color: '#5b9ef0', familia: 'membrana', freq: 205 },
    { id: 'repique', label: 'Repique', short: 'Repi', pitch: 'd/5', stem: 1, midi: 40, color: '#4a9a86', familia: 'membrana', freq: 300 },
    { id: 'timbal', label: 'Timbal', short: 'Timb', pitch: 'f/5', stem: 1, midi: 47, color: '#9c8ad1', familia: 'membrana', freq: 168 },
    { id: 'agogo', label: 'Agogó', short: 'Ago', pitch: 'a/5', stem: 1, midi: 67, color: '#c1432b', familia: 'metal', freq: 780 },
    { id: 'palmas', label: 'Palmas', short: 'Palm', pitch: 'b/5', stem: 1, midi: 39, color: '#b6a488', familia: 'mano', freq: 1200 },
];

/** Instrumentos que arrancan en una partitura nueva. */
export const INSTRUMENTOS_DEFAULT = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal'];

/**
 * @typedef {Object} Golpe
 * @property {string} id
 * @property {string} label
 * @property {string} short      Símbolo corto para paletas
 * @property {'normal'|'x'|'triangle'|'diamond'|'circled'|'slash'} cabeza
 * @property {string|null} articulacion  Código VexFlow (a>, a-, ao, a^)
 * @property {number} pos        4 = debajo, 3 = arriba
 * @property {number} gain
 * @property {'golpe'|'aro'|'apagado'|'metal'} timbre
 */

/** @type {Record<string, Golpe>} */
export const GOLPES = {
    nota: { id: 'nota', label: 'Golpe pleno', short: '●', cabeza: 'normal', articulacion: null, pos: 4, gain: 1, timbre: 'golpe' },
    acentuado: { id: 'acentuado', label: 'Acentuado', short: '>', cabeza: 'normal', articulacion: 'a>', pos: 4, gain: 1.35, timbre: 'golpe' },
    chapa: { id: 'chapa', label: 'Chapa / aro', short: '✕', cabeza: 'x', articulacion: null, pos: 4, gain: 0.85, timbre: 'aro' },
    tapado: { id: 'tapado', label: 'Tapado', short: '—', cabeza: 'normal', articulacion: 'a-', pos: 3, gain: 0.6, timbre: 'apagado' },
    presionado: { id: 'presionado', label: 'Presionado', short: '=', cabeza: 'normal', articulacion: 'a-', pos: 4, gain: 0.55, timbre: 'apagado' },
    abierto: { id: 'abierto', label: 'Abierto', short: '○', cabeza: 'circled', articulacion: 'ao', pos: 3, gain: 1.1, timbre: 'golpe' },
    slap: { id: 'slap', label: 'Slap', short: '⊗', cabeza: 'x', articulacion: 'a^', pos: 3, gain: 1.2, timbre: 'aro' },
    palma: { id: 'palma', label: 'Palma', short: '◆', cabeza: 'diamond', articulacion: null, pos: 4, gain: 0.9, timbre: 'apagado' },
    dedo: { id: 'dedo', label: 'Dedos', short: '△', cabeza: 'triangle', articulacion: null, pos: 4, gain: 0.5, timbre: 'golpe' },
    agudo: { id: 'agudo', label: 'Agudo (borde)', short: '▲', cabeza: 'triangle', articulacion: null, pos: 4, gain: 1, timbre: 'aro' },
    flam: { id: 'flam', label: 'Mordente / flam', short: '𝆔', cabeza: 'normal', articulacion: null, pos: 4, gain: 1, timbre: 'golpe' },
};

/** Golpes disponibles por instrumento (el primero es el default). */
export const GOLPES_POR_INSTRUMENTO = {
    surdo_grave: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    surdo_agudo: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    surdo_medio: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    redoblante: ['nota', 'acentuado', 'chapa', 'tapado', 'flam'],
    repique: ['nota', 'acentuado', 'chapa', 'agudo', 'flam'],
    timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo', 'acentuado'],
    agogo: ['nota', 'acentuado', 'tapado'],
    palmas: ['nota', 'acentuado'],
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
