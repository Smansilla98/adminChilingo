/**
 * MIDI: importar archivos .mid (percusión, canal 10) y tocar con un teclado/pad MIDI.
 * Exportar ya existe en exporters.js (generarMIDI); el mapeo de notas es el mismo
 * (midiDeGolpe), así que exportar → importar conserva instrumentos y golpes salvo
 * cuando varios comparten nota (p. ej. la chapa): ahí gana el primero de la partitura.
 */
import { TPQ, ticksDeCompas, crearPartitura, crearSeccion, ops, normalizarPartitura } from './model.js';
import { INSTRUMENTOS, GOLPES_POR_INSTRUMENTO, midiDeGolpe, UNISONO } from './instruments.js';
import { ponerGolpe } from './grilla.js';

/** Lee un SMF (formato 0 o 1). Devuelve las notas (note-on con velocidad > 0). */
export function leerMIDI(bytes) {
    const d = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let p = 0;
    const u32 = () => { const v = (d[p] << 24) | (d[p + 1] << 16) | (d[p + 2] << 8) | d[p + 3]; p += 4; return v >>> 0; };
    const u16 = () => { const v = (d[p] << 8) | d[p + 1]; p += 2; return v; };
    const vlq = () => { let v = 0; let b; do { b = d[p++]; v = (v << 7) | (b & 0x7f); } while (b & 0x80 && p < d.length); return v; };
    const tag = () => String.fromCharCode(d[p], d[p + 1], d[p + 2], d[p + 3]);

    if (tag() !== 'MThd') throw new Error('No es un archivo MIDI.');
    p += 4;
    const largoHeader = u32();
    const inicioHeader = p;
    u16();
    const pistas = u16();
    const division = u16();
    if (division & 0x8000) throw new Error('MIDI con división SMPTE: no soportado.');
    p = inicioHeader + largoHeader;

    let tempoUs = 500000;
    let ts = { num: 4, den: 4 };
    const notas = [];
    for (let t = 0; t < pistas && p < d.length; t++) {
        if (tag() !== 'MTrk') break;
        p += 4;
        const largo = u32();
        const fin = p + largo;
        let tick = 0;
        let estado = 0;
        while (p < fin) {
            tick += vlq();
            let b = d[p];
            if (b & 0x80) { estado = b; p++; } else { b = estado; }
            const tipo = estado & 0xf0;
            if (estado === 0xff) {
                const meta = d[p++];
                const len = vlq();
                if (meta === 0x51 && len === 3) tempoUs = (d[p] << 16) | (d[p + 1] << 8) | d[p + 2];
                if (meta === 0x58 && len >= 2) ts = { num: d[p], den: 2 ** d[p + 1] };
                p += len;
            } else if (estado === 0xf0 || estado === 0xf7) {
                p += vlq();
            } else if (tipo === 0x90 || tipo === 0x80) {
                const note = d[p++];
                const vel = d[p++];
                if (tipo === 0x90 && vel > 0) notas.push({ tick, note, vel, canal: estado & 0x0f });
            } else if (tipo === 0xc0 || tipo === 0xd0) {
                p += 1;
            } else {
                p += 2;
            }
        }
        p = fin;
    }
    return { division, bpm: Math.round(60000000 / tempoUs), timeSignature: ts, notas };
}

/**
 * Instrumento y golpe para una nota MIDI. Prefiere los instrumentos de `preferidos`.
 * @returns {{ instId: string, stroke: string }|null}
 */
export function instrumentoDeMidi(note, preferidos = []) {
    const orden = [...preferidos, ...INSTRUMENTOS.map((i) => i.id)].filter((id, i, a) => id !== UNISONO && a.indexOf(id) === i);
    for (const instId of orden) {
        const golpes = GOLPES_POR_INSTRUMENTO[instId] || [];
        const stroke = golpes.find((g) => midiDeGolpe(instId, g) === note);
        if (stroke) return { instId, stroke };
    }
    return null;
}

/**
 * Partitura a partir de un MIDI de percusión, cuantizada a `paso` ticks (1/16 por defecto).
 * @param {ArrayBuffer|Uint8Array} bytes
 */
export function scoreDesdeMIDI(bytes, { titulo = 'Importado de MIDI', paso = TPQ / 4, preferidos = [] } = {}) {
    const midi = leerMIDI(bytes);
    const ts = [2, 4, 8, 16].includes(midi.timeSignature.den) ? midi.timeSignature : { num: 4, den: 4 };
    const escala = TPQ / midi.division;
    const golpes = midi.notas
        .filter((n) => n.canal === 9 || midi.notas.every((x) => x.canal !== 9))
        .map((n) => ({ ...n, mapa: instrumentoDeMidi(n.note, preferidos), t: Math.round((n.tick * escala) / paso) * paso }))
        .filter((n) => n.mapa);
    if (!golpes.length) throw new Error('El MIDI no tiene golpes de percusión reconocibles.');

    const instrumentos = [...new Set(golpes.map((g) => g.mapa.instId))];
    const cap = ticksDeCompas(ts);
    const compases = Math.min(64, Math.floor(Math.max(...golpes.map((g) => g.t)) / cap) + 1);
    const score = crearPartitura({ title: titulo, instrumentos });
    score.tempo = Math.min(260, Math.max(30, midi.bpm || 100));
    score.timeSignature = { ...ts };
    score.sections = [crearSeccion('Importado', instrumentos, ts, compases)];
    ops.setCompasMetrico(score, ts.num, ts.den);

    golpes.forEach((g) => {
        const mi = Math.floor(g.t / cap);
        if (mi >= compases) return;
        const m = score.sections[0].measures[mi];
        const k = Math.round((g.t - mi * cap) / paso);
        const vel = Math.max(1, Math.min(127, Math.round((g.vel / 96) * 100)));
        const voz = ponerGolpe(m.voces[g.mapa.instId], k, paso, g.mapa.instId, { stroke: g.mapa.stroke, vel: Math.abs(vel - 100) > 12 ? vel : null });
        if (voz) m.voces[g.mapa.instId] = voz;
    });
    return normalizarPartitura(score);
}

/**
 * Escucha teclados/pads MIDI (Web MIDI: Chrome, Edge, Opera). Llama `onNota(note, vel)`.
 * @returns {Promise<{ detener: () => void, dispositivos: string[] }>}
 */
export async function escucharMIDI(onNota) {
    if (typeof navigator === 'undefined' || !navigator.requestMIDIAccess) {
        throw new Error('Este navegador no permite MIDI. Probá con Chrome o Edge.');
    }
    const acceso = await navigator.requestMIDIAccess();
    const entradas = [...acceso.inputs.values()];
    const handler = (msg) => {
        const [st, note, vel] = msg.data;
        if ((st & 0xf0) === 0x90 && vel > 0) onNota(note, vel);
    };
    entradas.forEach((i) => { i.onmidimessage = handler; });
    return {
        dispositivos: entradas.map((i) => i.name || 'MIDI'),
        detener: () => entradas.forEach((i) => { i.onmidimessage = null; }),
    };
}
