/**
 * Grilla rítmica: la misma partitura vista por pasos (1/8, 1/16, 1/32).
 *
 * No hay datos propios de la grilla: lee y edita las voces del modelo. Las ediciones
 * son locales (parten la nota que cubre el paso y rellenan con silencios), así que el
 * resto de la notación queda intacta. Lo que no cae en la grilla (tresillos, fusas en
 * una grilla de semicorcheas) se muestra marcado y solo se edita en la partitura.
 */
import { TPQ, ticksDeNota, ticksDeCompas, crearNota, nextId } from './model.js';
import { golpeDefault, resolverStroke } from './instruments.js';

/** Resoluciones de la grilla: ticks por paso. */
export const RESOLUCIONES = [
    { id: '8', label: 'Corcheas', ticks: TPQ / 2 },
    { id: '16', label: 'Semicorcheas', ticks: TPQ / 4 },
    { id: '32', label: 'Fusas', ticks: TPQ / 8 },
];

export function ticksDeResolucion(id) {
    return (RESOLUCIONES.find((r) => r.id === String(id)) || RESOLUCIONES[1]).ticks;
}

export function pasosPorCompas(timeSignature, paso) {
    return Math.max(1, Math.floor(ticksDeCompas(timeSignature) / paso));
}

/**
 * Etiquetas de conteo por paso: 1 e & a (semicorcheas), 1 & (corcheas), 1 · e · & · a · (fusas).
 * En compases de 8 (6/8, 12/8) cuenta corcheas: 1 2 3 4 5 6.
 */
export function etiquetasDePasos(timeSignature, paso) {
    const n = pasosPorCompas(timeSignature, paso);
    const den = timeSignature?.den || 4;
    const porPulso = Math.round((TPQ * 4) / den);
    const sub = Math.max(1, Math.round(porPulso / paso));
    const nombres = sub === 4 ? ['', 'e', '&', 'a'] : sub === 2 ? ['', '&'] : sub === 8 ? ['', '·', 'e', '·', '&', '·', 'a', '·'] : null;
    return Array.from({ length: n }, (_, k) => {
        const pulso = Math.floor(k / sub) + 1;
        const r = k % sub;
        if (r === 0) return String(pulso);
        return nombres ? nombres[r] : '·';
    });
}

/**
 * Celdas de una voz.
 * - `golpe`: una nota (no silencio) empieza en el paso.
 * - `sostiene`: el paso cae dentro de un golpe que empezó antes.
 * - `vacio`: silencio.
 * - `fuera`: hay ataques entre pasos o el paso cae en un grupo irregular (no editable acá).
 *
 * @returns {{ tipo: 'golpe'|'sostiene'|'vacio'|'fuera', noteIdx: number, nota: object|null }[]}
 */
export function celdasDeVoz(voz, capacidad, paso) {
    const n = Math.max(1, Math.floor(capacidad / paso));
    const celdas = Array.from({ length: n }, () => ({ tipo: 'vacio', noteIdx: -1, nota: null }));
    let t = 0;
    (voz || []).forEach((nota, i) => {
        const d = ticksDeNota(nota);
        const desde = Math.floor(t / paso);
        const alineada = t % paso === 0;
        const irregular = !!nota.tuplet;
        if (!nota.rest) {
            if (alineada && !irregular && desde < n) {
                celdas[desde] = { tipo: 'golpe', noteIdx: i, nota };
            } else if (desde < n) {
                celdas[desde] = { tipo: 'fuera', noteIdx: i, nota };
            }
        }
        // Pasos interiores de la nota.
        for (let k = desde + 1; k * paso < t + d && k < n; k++) {
            if (irregular) celdas[k] = { tipo: 'fuera', noteIdx: i, nota };
            else if (!nota.rest && celdas[k].tipo === 'vacio') celdas[k] = { tipo: 'sostiene', noteIdx: i, nota };
        }
        if (irregular && desde < n && nota.rest && celdas[desde].tipo === 'vacio') celdas[desde] = { tipo: 'fuera', noteIdx: i, nota };
        t += d;
    });
    return celdas;
}

/** Duraciones estándar para partir un tramo (sin tresillos). */
const PIEZAS = [
    { dur: 'w', dots: 0, t: TPQ * 4 },
    { dur: 'h', dots: 1, t: TPQ * 3 },
    { dur: 'h', dots: 0, t: TPQ * 2 },
    { dur: 'q', dots: 1, t: (TPQ * 3) / 2 },
    { dur: 'q', dots: 0, t: TPQ },
    { dur: '8', dots: 1, t: (TPQ * 3) / 4 },
    { dur: '8', dots: 0, t: TPQ / 2 },
    { dur: '16', dots: 0, t: TPQ / 4 },
    { dur: '32', dots: 0, t: TPQ / 8 },
];

/** Parte `ticks` en figuras estándar, de mayor a menor. */
export function piezas(ticks) {
    const out = [];
    let resto = Math.round(ticks);
    let guard = 0;
    while (resto > 0 && guard++ < 64) {
        const p = PIEZAS.find((x) => x.t <= resto);
        if (!p) break;
        out.push(p);
        resto -= p.t;
    }
    return out;
}

/** Golpe de `ticks` de largo: la primera pieza suena, el resto es silencio. */
function golpeDeLargo(ticks, base) {
    return piezas(ticks).map((p, i) => (i === 0
        ? { ...base, id: base.id || nextId(), dur: p.dur, dots: p.dots, rest: false, tuplet: null }
        : crearNota({ dur: p.dur, dots: p.dots, rest: true })));
}

function silencioDeLargo(ticks) {
    return piezas(ticks).map((p) => crearNota({ dur: p.dur, dots: p.dots, rest: true }));
}

/** Índice y tick de inicio de la nota que cubre `tick`. */
function notaEn(voz, tick) {
    let t = 0;
    for (let i = 0; i < voz.length; i++) {
        const d = ticksDeNota(voz[i]);
        if (tick >= t && tick < t + d) return { i, inicio: t, dur: d };
        t += d;
    }
    return null;
}

/**
 * Pone un golpe en el paso `k` (o cambia su golpe si ya hay uno). Devuelve la voz nueva
 * o null si el paso no es editable en la grilla.
 *
 * @param {object[]} voz
 * @param {number} k        paso
 * @param {number} paso     ticks por paso
 * @param {string} instId
 * @param {{ stroke?: string, dyn?: string|null, vel?: number|null }} [golpe]
 */
export function ponerGolpe(voz, k, paso, instId, golpe = {}) {
    const tick = k * paso;
    const hit = notaEn(voz, tick);
    if (!hit) return null;
    const nota = voz[hit.i];
    if (nota.tuplet) return null;
    const stroke = resolverStroke(golpe.stroke || golpeDefault(instId), instId);
    const out = voz.slice();

    // Ya empieza un golpe acá: solo cambia su golpe.
    if (hit.inicio === tick && !nota.rest) {
        out[hit.i] = { ...nota, stroke, ...(golpe.dyn !== undefined ? { dyn: golpe.dyn } : {}), ...(golpe.vel !== undefined ? { vel: golpe.vel } : {}) };
        return out;
    }

    const antes = tick - hit.inicio;
    const despues = hit.inicio + hit.dur - tick;
    const largoNuevo = Math.min(paso, despues);
    const reemplazo = [];
    if (antes > 0) {
        reemplazo.push(...(nota.rest ? silencioDeLargo(antes) : golpeDeLargo(antes, nota)));
    }
    reemplazo.push(...golpeDeLargo(largoNuevo, crearNota({
        stroke, dyn: golpe.dyn ?? null, vel: golpe.vel ?? null,
    })));
    if (despues > largoNuevo) reemplazo.push(...silencioDeLargo(despues - largoNuevo));
    out.splice(hit.i, 1, ...reemplazo);
    return out;
}

/** Saca el golpe que empieza en el paso `k` (queda silencio del mismo largo). */
export function quitarGolpe(voz, k, paso) {
    const tick = k * paso;
    const hit = notaEn(voz, tick);
    if (!hit || hit.inicio !== tick || voz[hit.i].rest || voz[hit.i].tuplet) return null;
    const out = voz.slice();
    const n = voz[hit.i];
    out[hit.i] = { ...n, rest: true, stroke: 'nota', dyn: null, vel: null, digitacion: null };
    return out;
}

/** Alterna el golpe del paso: si hay, lo saca; si no, lo pone. */
export function alternarGolpe(voz, k, paso, instId, golpe = {}) {
    const c = celdasDeVoz(voz, ticksTotales(voz), paso)[k];
    if (c?.tipo === 'golpe') return quitarGolpe(voz, k, paso);
    return ponerGolpe(voz, k, paso, instId, golpe);
}

function ticksTotales(voz) {
    return (voz || []).reduce((s, n) => s + ticksDeNota(n), 0);
}

/** Paso de la grilla que corresponde a un tick (para sincronizar con la partitura). */
export function pasoDeTick(tick, paso) {
    return Math.floor(tick / paso);
}

/** Tick de inicio de la nota `noteIdx` dentro de la voz. */
export function tickDeNota(voz, noteIdx) {
    let t = 0;
    for (let i = 0; i < noteIdx && i < voz.length; i++) t += ticksDeNota(voz[i]);
    return t;
}

/** Índice de la nota que cubre el paso `k` (para seleccionar en la partitura). */
export function notaDePaso(voz, k, paso) {
    return notaEn(voz || [], k * paso)?.i ?? 0;
}
