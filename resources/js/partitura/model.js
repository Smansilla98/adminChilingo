/**
 * Modelo de partitura v4 — percusión multi-instrumento, duraciones reales.
 *
 * Unidad de tiempo: ticks, TPQ = 48 por negra (divisible por 3 y 4 → tresillos y semicorcheas).
 */
import { INSTRUMENTOS, INSTRUMENTOS_DEFAULT, instrumentoPorId, golpeDefault, GOLPES } from './instruments.js';

export const VERSION = 4;
export const TPQ = 48;

/** Duraciones soportadas (código VexFlow → ticks). */
export const DURACIONES = [
    { code: 'w', label: 'Redonda', ticks: TPQ * 4, tecla: '1' },
    { code: 'h', label: 'Blanca', ticks: TPQ * 2, tecla: '2' },
    { code: 'q', label: 'Negra', ticks: TPQ, tecla: '3' },
    { code: '8', label: 'Corchea', ticks: TPQ / 2, tecla: '4' },
    { code: '16', label: 'Semicorchea', ticks: TPQ / 4, tecla: '5' },
    { code: '32', label: 'Fusa', ticks: TPQ / 8, tecla: '6' },
];

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

export function crearNota({ dur = 'q', dots = 0, rest = false, stroke = 'nota', dyn = null, tuplet = null } = {}) {
    return { id: nextId(), dur, dots, rest, stroke, dyn, tuplet };
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
    return { id: base.id, volume: 0.9, mute: false, solo: false, visible: true };
}

/** ---------------------------------------------------------------- normalización */

function normNota(raw, instId) {
    if (!raw || typeof raw !== 'object') return null;
    const dur = DUR_TICKS[raw.dur] ? raw.dur : 'q';
    const rest = !!raw.rest;
    let stroke = typeof raw.stroke === 'string' && GOLPES[raw.stroke] ? raw.stroke : golpeDefault(instId);
    if (rest) stroke = 'nota';
    let tuplet = null;
    if (raw.tuplet && typeof raw.tuplet === 'object') {
        const num = parseInt(raw.tuplet.num, 10);
        const den = parseInt(raw.tuplet.den, 10);
        if (num > 1 && den > 0) {
            tuplet = { id: String(raw.tuplet.id || nextId('t')), num, den };
        }
    }
    return {
        id: String(raw.id || nextId()),
        dur,
        dots: Math.min(2, Math.max(0, parseInt(raw.dots, 10) || 0)),
        rest,
        stroke,
        dyn: typeof raw.dyn === 'string' && raw.dyn ? raw.dyn : null,
        tuplet,
    };
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

    return out;
}

function normalizarFuente(raw) {
    if (!raw || typeof raw !== 'object') return null;
    const origen = String(raw.origen || '').trim().slice(0, 80);
    const hash = String(raw.hash || '').replace(/[^a-f0-9]/gi, '').slice(0, 40);
    if (!origen || !hash) return null;
    return { origen, hash };
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
        if (nota.rest) nota.dyn = null;
        else nota.stroke = golpeDefault(sel.instId);
        return true;
    },

    setGolpe(score, sel, stroke) {
        const nota = notaDe(score, sel);
        if (!nota) return false;
        nota.rest = false;
        nota.stroke = stroke;
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
            voz.splice(first, count, crearNota({ dur: base.dur, dots: 0, rest: base.rest, stroke: base.stroke }));
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
};

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
