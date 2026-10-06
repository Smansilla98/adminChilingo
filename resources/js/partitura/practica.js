/**
 * Práctica: tap tempo y cuantización de lo que se graba tocando el teclado.
 * Funciones puras (sin DOM ni audio).
 */
import { TPQ, ticksDeCompas } from './model.js';

/**
 * Tap tempo: BPM a partir de los últimos toques (ms). Ignora pausas largas (> 2 s,
 * empieza de nuevo) y usa la mediana para no saltar con un toque desparejo.
 *
 * @param {number[]} tiempos  marcas en ms, en orden
 * @returns {number|null}
 */
export function bpmDeToques(tiempos, { min = 40, max = 180 } = {}) {
    const recientes = [];
    for (let i = tiempos.length - 1; i > 0 && recientes.length < 8; i--) {
        const dt = tiempos[i] - tiempos[i - 1];
        if (dt > 2000) break;
        if (dt > 0) recientes.push(dt);
    }
    if (recientes.length < 2) return null;
    const orden = recientes.slice().sort((a, b) => a - b);
    const mediana = orden[Math.floor(orden.length / 2)];
    return Math.min(max, Math.max(min, Math.round(60000 / mediana)));
}

/** Toques recientes para el tap tempo (descarta los de hace más de 2 s). */
export function registrarToque(tiempos, ahora) {
    const ultimo = tiempos[tiempos.length - 1];
    const base = ultimo !== undefined && ahora - ultimo > 2000 ? [] : tiempos.slice(-8);
    return [...base, ahora];
}

/**
 * Cuantiza golpes grabados (segundos musicales desde el primer compás) a la grilla.
 *
 * @param {{ seg: number, instId: string, stroke?: string }[]} golpes
 * @param {{ bpm: number, timeSignature: {num:number, den:number}, paso: number, compases: {sectionIdx:number, measureIdx:number}[] }} opts
 *   `compases`: el recorrido reproducido (timeline), para ubicar cada golpe en su compás.
 * @returns {{ sectionIdx: number, measureIdx: number, paso: number, instId: string, stroke?: string }[]}
 */
export function cuantizar(golpes, { bpm, timeSignature, paso, compases }) {
    const cap = ticksDeCompas(timeSignature);
    const ticksPorSeg = (TPQ * bpm) / 60;
    const pasosCompas = Math.floor(cap / paso);
    const vistos = new Set();
    const out = [];
    golpes.forEach((g) => {
        const tick = Math.round((g.seg * ticksPorSeg) / paso) * paso;
        let mi = Math.floor(tick / cap);
        let k = Math.round((tick - mi * cap) / paso);
        if (k >= pasosCompas) { mi += 1; k = 0; }
        const pos = compases[mi];
        if (!pos || tick < 0) return;
        const clave = `${pos.sectionIdx}:${pos.measureIdx}:${k}:${g.instId}`;
        if (vistos.has(clave)) return;
        vistos.add(clave);
        out.push({ sectionIdx: pos.sectionIdx, measureIdx: pos.measureIdx, paso: k, instId: g.instId, stroke: g.stroke });
    });
    return out;
}
