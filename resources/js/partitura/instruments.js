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
    // Cuadernillo (Equivalencias, Nomenclatura y los 26 toques): cabeza en el 2º espacio,
    // plica arriba, barras arriba; acentos y guiones debajo del pentagrama.
    { id: 'todos', label: 'Todos', short: 'Tod', pitch: 'a/4', stem: 1, midi: 38, color: '#6d5b45', familia: 'membrana', freq: 120 },
    { id: 'surdo_grave', label: 'Surdo Grave', short: 'S.Gr', pitch: 'a/4', stem: 1, midi: 87, color: '#e86a3c', familia: 'membrana', freq: 62 },
    { id: 'surdo_agudo', label: 'Surdo Agudo', short: 'S.Ag', pitch: 'a/4', stem: 1, midi: 43, color: '#f0a04b', familia: 'membrana', freq: 96 },
    { id: 'surdo_medio', label: 'Surdo Medio', short: 'S.Me', pitch: 'a/4', stem: 1, midi: 41, color: '#d1a054', familia: 'membrana', freq: 78 },
    { id: 'redoblante', label: 'Redoblante', short: 'Redo', pitch: 'a/4', stem: 1, midi: 38, color: '#5b9ef0', familia: 'membrana', freq: 205 },
    { id: 'repique', label: 'Repique', short: 'Repi', pitch: 'a/4', stem: 1, midi: 40, color: '#4a9a86', familia: 'membrana', freq: 300 },
    { id: 'timbal', label: 'Timbal', short: 'Timb', pitch: 'a/4', stem: 1, midi: 66, color: '#9c8ad1', familia: 'membrana', freq: 168 },
    { id: 'agogo', label: 'Agogó', short: 'Ago', pitch: 'a/4', stem: 1, midi: 67, color: '#c1432b', familia: 'metal', freq: 780 },
    { id: 'palmas', label: 'Palmas', short: 'Palm', pitch: 'a/4', stem: 1, midi: 39, color: '#b6a488', familia: 'mano', freq: 1200 },
];

/** Instrumentos que arrancan en una partitura nueva. */
export const INSTRUMENTOS_DEFAULT = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal'];

/**
 * @typedef {Object} Golpe
 * @property {string} id
 * @property {string} label
 * @property {string} short
 * @property {'normal'|'x'|'triangle'|'diamond'|'hollow'|'slash'} cabeza
 * @property {string|null} articulacion  Código VexFlow (a>, a-, ao, a^)
 * @property {number} pos        4 = debajo, 3 = arriba
 * @property {number} gain
 * @property {'golpe'|'aro'|'apagado'|'palma'|'metal'} timbre
 * @property {'abierto'|'slap'|'chapa'|'palma'|'acentuado'|'presionado'|null} tipoGolpe
 */

/**
 * Cabezas según la hoja Nomenclatura del cuadernillo (PDF pág. 5):
 * - Nota / abierto: óvalo negro
 * - Chapa (surdos, redoblante, repique) / dedo (timbal): X
 * - Slap (timbal): cabeza hueca, con plica y barras (en los toques va en semicorcheas)
 * - Palma (timbal): rombo negro
 * - Presionado (timbal) y tapado (surdos): nota + guión DEBAJO del pentagrama
 * - Acentuado: nota + > debajo
 * - Agudo (repique): triángulo negro
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
        // Surdos: óvalo + guión debajo (Nomenclatura; Candombe, Rumba, Mongo kutá).
        cabeza: 'normal', articulacion: 'a-', pos: 4, gain: 0.6, timbre: 'apagado', tipoGolpe: 'tapado',
    },
    presionado: {
        id: 'presionado', label: 'Presionado', short: '=',
        cabeza: 'normal', articulacion: 'a-', pos: 4, gain: 0.55, timbre: 'apagado', tipoGolpe: 'presionado',
    },
    abierto: {
        id: 'abierto', label: 'Abierto', short: '●',
        // Timbal: óvalo negro (la cabeza hueca es slap).
        cabeza: 'normal', articulacion: null, pos: 4, gain: 1.1, timbre: 'golpe', tipoGolpe: 'abierto',
    },
    slap: {
        id: 'slap', label: 'Slap', short: '○',
        // Hoja Nomenclatura: cabeza hueca. No es una blanca: dura lo que diga la figura.
        cabeza: 'hollow', articulacion: null, pos: 4, gain: 1.15, timbre: 'aro', tipoGolpe: 'slap',
    },
    palma: {
        id: 'palma', label: 'Palma', short: '◆',
        // Hoja Nomenclatura: rombo negro (La Meta Toque 2, Toque de Marcha, Iyesá III).
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
    fantasma: {
        // Ghost note: golpe muy suave. Se dibuja entre paréntesis.
        id: 'fantasma', label: 'Fantasma (suave)', short: '◦',
        cabeza: 'normal', articulacion: null, pos: 4, gain: 0.35, timbre: 'golpe', tipoGolpe: null, parentesis: true,
    },
};

/** Alias plano (tipoGolpe del requisito) → id interno. */
export const TIPO_GOLPE_A_STROKE = {
    abierto: 'abierto',
    slap: 'slap',
    tapado: 'tapado',
    chapa: 'chapa',
    palma: 'palma',
    acentuado: 'acentuado',
    presionado: 'presionado',
    nota: 'nota',
    normal: 'nota',
};

/**
 * Articulación de archivo de sample (1:1 con el golpe).
 * `nota` (golpe pleno) se publica como `normal` en el nombre de archivo.
 */
export const ARTICULACION_SAMPLE = {
    nota: 'normal',
    acentuado: 'acentuado',
    chapa: 'chapa',
    tapado: 'tapado',
    abierto: 'abierto',
    slap: 'slap',
    palma: 'palma',
    presionado: 'presionado',
    dedo: 'dedo',
    agudo: 'agudo',
    fantasma: 'normal',
};

/** Samples requeridos por instrumento (sin fallback a otra articulación). */
export const MAPA_SAMPLES = {
    surdo_grave: ['nota', 'chapa', 'tapado'],
    surdo_medio: ['nota', 'chapa', 'tapado'],
    surdo_agudo: ['nota', 'chapa', 'tapado'],
    redoblante: ['nota', 'acentuado', 'chapa', 'agudo'],
    timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo'],
    repique: ['nota', 'acentuado', 'chapa', 'agudo'],
    agogo: ['nota', 'acentuado', 'tapado'],
    palmas: ['nota', 'acentuado'],
};

export function nombreArchivoSample(instId, strokeId) {
    const art = ARTICULACION_SAMPLE[strokeId] || strokeId;
    return `${instId}_${art}`;
}

/**
 * Agrupa instrumentos en sistemas de pentagrama.
 * Redoblante y Repique comparten un único pentagrama de 5 líneas, salvo
 * `opts.separarAgudos` (cuando en esa parte no tocan lo mismo).
 */
export function sistemasVisuales(insts, agrupar = null, opts = {}) {
    const byId = {};
    insts.forEach((x) => { byId[x.def.id] = x; });
    const seen = new Set();
    const out = [];
    const push = (ids, label) => {
        const members = ids.map((id) => byId[id]).filter(Boolean);
        if (!members.length) return;
        members.forEach((m) => seen.add(m.def.id));
        out.push({
            id: ids.join('+'),
            label: label || members.map((m) => m.def.label).join(' / '),
            members,
            compartido: members.length > 1,
        });
    };
    if (agrupar === 'agudos-graves') {
        push(['redoblante', 'repique'], 'Agudos (llaman)');
        push(['surdo_grave'], 'Graves (responden)');
        ['surdo_agudo', 'surdo_medio'].forEach((id) => { if (byId[id]) seen.add(id); });
        push(['timbal']);
        push(['agogo']);
        push(['palmas']);
        insts.forEach((x) => {
            if (!seen.has(x.def.id)) push([x.def.id]);
        });
        return out;
    }
    push(['todos']);
    push(['surdo_grave']);
    push(['surdo_agudo']);
    push(['surdo_medio']);
    // El cuadernillo junta «Redoblante y Repique» sólo cuando tocan lo mismo.
    if (opts.separarAgudos) {
        push(['redoblante']);
        push(['repique']);
    } else {
        push(['redoblante', 'repique'], 'Redoblante y Repique');
    }
    push(['timbal']);
    push(['agogo']);
    push(['palmas']);
    insts.forEach((x) => {
        if (!seen.has(x.def.id)) push([x.def.id]);
    });
    return out;
}

/** Digitación pedagógica (ejercicios tipo Timbal Bahiano). */
export const DIGITACIONES = [
    { id: 'D', label: 'Derecha', short: 'D' },
    { id: 'I', label: 'Izquierda', short: 'I' },
];

/** Golpes disponibles por instrumento (el primero es el default). */
export const GOLPES_POR_INSTRUMENTO = {
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

/**
 * MIDI por (instrumento, golpe). Canal 10 / GM + GM2.
 * Surdo grave: 87 open / 86 mute. Caixa 38. Repique 40. Timbal 66/65. Palma 39.
 */
const MIDI_POR_GOLPE = {
    timbal: { abierto: 66, slap: 65, tapado: 64, palma: 39, dedo: 62, presionado: 64, acentuado: 66, nota: 66 },
    redoblante: { nota: 38, acentuado: 40, chapa: 37, tapado: 37, flam: 38, agudo: 43 },
    repique: { nota: 40, acentuado: 40, chapa: 37, agudo: 43, flam: 40 },
    surdo_grave: { nota: 87, acentuado: 87, chapa: 37, tapado: 86, flam: 87 },
    surdo_medio: { nota: 41, acentuado: 41, chapa: 37, tapado: 86, flam: 41 },
    surdo_agudo: { nota: 43, acentuado: 43, chapa: 37, tapado: 86, flam: 43 },
    agogo: { nota: 67, acentuado: 67, tapado: 68, chapa: 68, flam: 67 },
    palmas: { nota: 39, acentuado: 39, flam: 39 },
};

export const DINAMICAS = ['pp', 'p', 'mp', 'mf', 'f', 'ff'];

/** Señas de dirección por compás (germen de RhythmCue). Espejo de PartituraScore::SENAS. */
export const SENAS = [
    { id: 'entrada', label: 'Entrada' },
    { id: 'corte', label: 'Corte' },
    { id: 'llamada', label: 'Llamada' },
    { id: 'cambio', label: 'Cambio' },
    { id: 'otra', label: 'Otra' },
];

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
        // Negros siempre: en el cuadernillo el triángulo y el rombo no cambian con la figura.
        case 'triangle': return `${pitch}/t2`;
        case 'diamond': return `${pitch}/d2`;
        case 'slash': return `${pitch}/s`;
        default: return pitch;
    }
}

/**
 * Glifo que pisa al de la figura. El slap es cabeza hueca aunque sea semicorchea,
 * y VexFlow no tiene un código de cabeza para eso.
 * @param {string} golpeId
 * @returns {string|null}
 */
export function glifoDeCabeza(golpeId) {
    return (GOLPES[golpeId] || GOLPES.nota).cabeza === 'hollow' ? 'noteheadHalf' : null;
}
