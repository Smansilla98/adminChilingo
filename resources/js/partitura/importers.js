/**
 * Importa MusicXML (MuseScore / export propio) o JSON v4 al modelo del editor.
 */
import {
    crearNota, crearCompas, crearPartitura, normalizarPartitura,
    TPQ, ticksDeCompas, ajustarVoz, DURACIONES,
} from './model.js';
import { INSTRUMENTOS, instrumentoPorId, golpeDefault } from './instruments.js';

const TIPO_A_DUR = {
    whole: 'w', half: 'h', quarter: 'q', eighth: '8', '16th': '16', '32nd': '32',
    maxima: 'w', long: 'w', breve: 'w',
};

const CABEZA_A_GOLPE = {
    x: 'chapa',
    'circle-x': 'slap',
    circled: 'slap',
    triangle: 'agudo',
    diamond: 'palma',
    slash: 'chapa',
    normal: 'nota',
};

export function importarScoreJson(texto) {
    const data = JSON.parse(texto);
    const score = normalizarPartitura(data);
    if (!score.sections?.length) {
        throw new Error('El JSON no tiene secciones de partitura.');
    }
    return score;
}

export function importarMusicXML(xml) {
    const doc = new DOMParser().parseFromString(xml, 'application/xml');
    if (doc.querySelector('parsererror')) {
        throw new Error('El MusicXML está dañado o no es XML válido.');
    }
    const root = doc.querySelector('score-partwise') || doc.querySelector('score-timewise');
    if (!root) {
        throw new Error('No es un MusicXML de partitura (score-partwise).');
    }

    const title = text(doc, 'work-title, movement-title, credit-words') || 'Toque importado';
    const autor = text(doc, 'creator, identification > creator') || '';
    const divisions = Number(doc.querySelector('attributes > divisions')?.textContent || TPQ) || TPQ;
    const beats = Number(doc.querySelector('attributes > time > beats')?.textContent || 4) || 4;
    const beatType = Number(doc.querySelector('attributes > time > beat-type')?.textContent || 4) || 4;
    const tempo = Number(
        doc.querySelector('sound[tempo]')?.getAttribute('tempo')
        || doc.querySelector('metronome per-minute')?.textContent
        || 88
    ) || 88;

    const timeSignature = { num: beats, den: beatType };
    const capacidad = ticksDeCompas(timeSignature);

    const partMeta = [];
    doc.querySelectorAll('part-list > score-part').forEach((el) => {
        const xmlId = el.getAttribute('id') || '';
        const nombre = text(el, 'part-name, instrument-name') || xmlId;
        mapearInstrumentosDeParte(nombre).forEach((row) => {
            if (partMeta.some((p) => p.instId === row.instId)) return;
            partMeta.push({ xmlId, ...row, nombre });
        });
    });
    if (!partMeta.length) {
        INSTRUMENTOS.filter((i) => i.id !== 'todos').slice(0, 6).forEach((inst) => {
            partMeta.push({ xmlId: inst.id, instId: inst.id, voice: null, nombre: inst.label });
        });
    }

    const xmlParts = new Map();
    doc.querySelectorAll('part').forEach((el) => xmlParts.set(el.getAttribute('id'), el));

    const maxMeasures = Math.max(
        1,
        ...partMeta.map((p) => xmlParts.get(p.xmlId)?.querySelectorAll('measure').length || 0)
    );

    const crudo = [];
    for (let i = 0; i < Math.min(128, maxMeasures); i++) {
        const voces = {};
        let texto = null;
        let nombreSec = null;
        let repeatX = 1;
        let repeatBegin = false;
        let repeatEnd = false;
        let ending = null;
        partMeta.forEach((p) => {
            const mEl = xmlParts.get(p.xmlId)?.querySelectorAll('measure')[i];
            const parsed = mEl
                ? parsearCompas(mEl, p.instId, divisions, p.voice)
                : { notas: [], texto: null, nombreSec: null, repeatX: 1, repeatBegin: false, repeatEnd: false, ending: null };
            voces[p.instId] = ajustarVoz(parsed.notas, capacidad);
            if (parsed.texto) texto = parsed.texto;
            if (parsed.nombreSec) nombreSec = parsed.nombreSec;
            if (parsed.repeatX > 1) repeatX = parsed.repeatX;
            if (parsed.repeatBegin) repeatBegin = true;
            if (parsed.repeatEnd) repeatEnd = true;
            if (parsed.ending) ending = parsed.ending;
        });
        crudo.push({
            id: `imp-m${i}`,
            repeatBegin,
            repeatEnd,
            ending,
            texto,
            nombreSec,
            repeatX,
            voces,
        });
    }

    const sections = [];
    let actual = null;
    crudo.forEach((m) => {
        if (m.nombreSec || !actual) {
            actual = {
                id: `imp-s${sections.length + 1}`,
                name: m.nombreSec || (sections.length ? `SECCIÓN ${sections.length + 1}` : 'TOQUE'),
                repeatX: m.repeatX > 1 ? m.repeatX : 1,
                measures: [],
            };
            sections.push(actual);
        }
        if (m.repeatX > 1) actual.repeatX = m.repeatX;
        actual.measures.push({
            id: m.id,
            repeatBegin: m.repeatBegin,
            repeatEnd: m.repeatEnd,
            ending: m.ending,
            texto: m.texto,
            voces: m.voces,
        });
    });

    const score = crearPartitura({
        title: title.slice(0, 120),
        autor: autor.slice(0, 120),
        instrumentos: partMeta.map((p) => p.instId),
    });
    score.tempo = Math.min(100, Math.max(60, Math.round(tempo)));
    score.timeSignature = timeSignature;
    score.sections = sections.length ? sections : [{
        id: 'imp-s1', name: 'TOQUE', repeatX: 1, measures: [],
    }];

    return normalizarPartitura(score);
}

function parsearCompas(mEl, instId, divisions, voiceFilter) {
    const notas = [];
    let texto = null;
    let nombreSec = null;
    let repeatX = 1;
    mEl.querySelectorAll('direction-type rehearsal').forEach((w) => {
        const t = (w.textContent || '').trim();
        if (esNombreSeccion(t)) nombreSec = t;
    });
    mEl.querySelectorAll('direction-type words').forEach((w) => {
        const t = (w.textContent || '').trim();
        const veces = t.match(/×\s*(\d+)/);
        if (veces) repeatX = Math.min(16, Math.max(1, Number(veces[1])));
        else if (esNombreSeccion(t)) nombreSec = t;
        else if (t && t.length < 60) texto = t;
    });
    const left = mEl.querySelector('barline[location="left"]');
    const right = mEl.querySelector('barline[location="right"]');
    const repeatBegin = !!left?.querySelector('repeat[direction="forward"]');
    const repeatEnd = !!right?.querySelector('repeat[direction="backward"]');
    const times = Number(right?.querySelector('repeat')?.getAttribute('times') || 0);
    if (times > 1) repeatX = Math.min(16, times);
    const endingRaw = Number(left?.querySelector('ending')?.getAttribute('number') || 0);
    const ending = [1, 2, 3].includes(endingRaw) ? endingRaw : null;

    mEl.querySelectorAll(':scope > note').forEach((note) => {
        if (note.querySelector('chord, grace, cue')) return;
        const v = Number(note.querySelector('voice')?.textContent || 1);
        if (voiceFilter && v !== voiceFilter) return;
        const rest = !!note.querySelector('rest');
        const dots = note.querySelectorAll('dot').length;
        const dur = duracionDe(note, divisions);
        let stroke = golpeDefault(instId);
        if (!rest) {
            const cabeza = (note.querySelector('notehead')?.textContent || 'normal').trim().toLowerCase();
            stroke = golpeDesdeCabeza(instId, cabeza);
            if (note.querySelector('accent, strong-accent')) stroke = instId === 'timbal' ? 'abierto' : 'acentuado';
            else if (note.querySelector('tenuto')) stroke = instId === 'timbal' ? 'presionado' : 'tapado';
        }
        let tuplet = null;
        const tm = note.querySelector('time-modification');
        if (tm) {
            const num = Number(tm.querySelector('actual-notes')?.textContent || 0);
            const den = Number(tm.querySelector('normal-notes')?.textContent || 0);
            if (num > 1 && den > 0) tuplet = { num, den };
        }
        notas.push(crearNota({ dur, dots: Math.min(2, dots), rest, stroke, tuplet }));
    });

    if (!notas.length) {
        const c = crearCompas([instId], { num: 4, den: 4 });
        return { notas: c.voces[instId], texto, nombreSec, repeatX, repeatBegin, repeatEnd, ending };
    }

    return { notas, texto, nombreSec, repeatX, repeatBegin, repeatEnd, ending };
}

function golpeDesdeCabeza(instId, cabeza) {
    if (cabeza === 'triangle') return instId === 'repique' ? 'agudo' : 'dedo';
    if (cabeza === 'circle-x' || cabeza === 'circled') return instId === 'timbal' ? 'slap' : 'chapa';
    if (cabeza === 'x') return instId === 'timbal' ? 'dedo' : 'chapa';
    if (cabeza === 'diamond') return 'palma';
    if (instId === 'timbal') return 'abierto';
    return CABEZA_A_GOLPE[cabeza] || 'nota';
}

function esNombreSeccion(t) {
    if (!t || t.length < 3 || t.length > 48) return false;
    if (/^×\s*\d+$/.test(t) || /^[\d.]+$/.test(t)) return false;
    const letters = t.replace(/[^A-Za-zÁÉÍÓÚÑáéíóúü]/g, '');
    return letters.length >= 3 && t === t.toUpperCase();
}

function mapearInstrumentosDeParte(nombre) {
    const n = String(nombre || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    if (/redobl/.test(n) && /repique|repi/.test(n)) {
        return [
            { instId: 'redoblante', voice: 1 },
            { instId: 'repique', voice: 2 },
        ];
    }
    const id = mapearInstrumento(nombre);
    return id ? [{ instId: id, voice: null }] : [];
}

function duracionDe(note, divisions) {
    const tipo = (note.querySelector('type')?.textContent || '').trim();
    if (TIPO_A_DUR[tipo]) return TIPO_A_DUR[tipo];
    const dur = Number(note.querySelector('duration')?.textContent || 0);
    const ticks = Math.round((dur / (divisions || TPQ)) * TPQ);
    const match = [...DURACIONES].sort((a, b) => Math.abs(a.ticks - ticks) - Math.abs(b.ticks - ticks))[0];
    return match?.code || 'q';
}

function mapearInstrumento(nombre) {
    const n = String(nombre || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    const reglas = [
        [/todos|unisono|unison/, 'todos'],
        [/palma/, 'palmas'],
        [/agogo|bell|cencerro/, 'agogo'],
        [/timbal/, 'timbal'],
        [/repique|repiqi/, 'repique'],
        [/redobl|caja|snare/, 'redoblante'],
        [/grave|surdo\s*1|surdo1|primero/, 'surdo_grave'],
        [/agud|surdo\s*3|surdo3|tercero/, 'surdo_agudo'],
        [/medio|surdo\s*2|surdo2|segundo/, 'surdo_medio'],
        [/surdo/, 'surdo_grave'],
    ];
    for (const [re, id] of reglas) {
        if (re.test(n) && instrumentoPorId(id)) return id;
    }
    return null;
}

function text(root, sel) {
    const el = root.querySelector(sel);
    return el ? String(el.textContent || '').trim() : '';
}

export function tipoArchivoImport(file) {
    const name = (file?.name || '').toLowerCase();
    const type = (file?.type || '').toLowerCase();
    if (name.endsWith('.mxl')) return 'mxl';
    if (name.endsWith('.musicxml') || name.endsWith('.xml') || type.includes('xml')) return 'xml';
    if (name.endsWith('.json') || type.includes('json')) return 'json';
    if (name.endsWith('.pdf') || type === 'application/pdf') return 'pdf';
    if (/\.(jpe?g|png|webp)$/.test(name) || type.startsWith('image/')) return 'imagen';
    return 'otro';
}
