/**
 * Partitura de percusión — Cuadernillo de Toques (La Chilinga)
 * VexFlow 4 · formato v3 (secciones + golpes por instrumento)
 */
import { Factory, GhostNote, Articulation, BarlineType } from 'vexflow';

/** @typedef {{ beat: number, stroke: string }} Hit */

export const CHILINGA_DRUMS = [
    { id: 'surdo_grave', label: 'Surdo Grave', pitch: 'f/3', stem: -1 },
    { id: 'surdo_agudo', label: 'Surdo Agudo', pitch: 'a/3', stem: -1 },
    { id: 'surdo_medio', label: 'Surdo Medio', pitch: 'c/4', stem: -1 },
    { id: 'redoblante', label: 'Redoblante', pitch: 'f/4' },
    { id: 'timbal', label: 'Timbal', pitch: 'g/5' },
    { id: 'repique', label: 'Repique', pitch: 'c/5' },
];

export const OPTIONAL_DRUMS = [
    { id: 'agogo', label: 'Agogó', pitch: 'e/5' },
    { id: 'palmas', label: 'Palmas', pitch: 'b/4' },
];

/** @type {Record<string, string[]>} */
export const STROKES_BY_DRUM = {
    surdo_grave: ['nota', 'chapa', 'tapado'],
    surdo_agudo: ['nota', 'chapa', 'tapado'],
    surdo_medio: ['nota', 'chapa', 'tapado'],
    redoblante: ['nota', 'acentuado', 'chapa'],
    timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo'],
    repique: ['nota', 'acentuado', 'chapa', 'agudo'],
    agogo: ['nota'],
    palmas: ['nota'],
};

/** @type {Record<string, string>} */
export const STROKE_LABELS = {
    nota: 'Nota',
    chapa: 'Chapa',
    tapado: 'Tapado',
    acentuado: 'Acento',
    abierto: 'Abierto',
    slap: 'Slap',
    palma: 'Palma',
    presionado: 'Pres.',
    dedo: 'Dedo',
    agudo: 'Agudo',
};

/** @type {Record<string, string>} */
const STROKE_SHORT = {
    nota: '•',
    chapa: '×',
    tapado: '—',
    acentuado: '>',
    abierto: '○',
    slap: '⊗',
    palma: '◇',
    presionado: '=',
    dedo: '×',
    agudo: '△',
};

const BEATS = 16;
const ID_ALIASES = {
    medio: 'surdo_medio',
    fondo_grave: 'surdo_grave',
    fondo_agudo: 'surdo_agudo',
};

function migrateDrumId(id) {
    return ID_ALIASES[id] || id;
}

function drumById(id) {
    const mid = migrateDrumId(id);
    return CHILINGA_DRUMS.find((d) => d.id === mid) || OPTIONAL_DRUMS.find((d) => d.id === mid);
}

function emptyHits(measureCount, drumIds) {
    /** @type {Record<string, Hit[][]>} */
    const hits = {};
    drumIds.forEach((id) => {
        hits[id] = [];
        for (let m = 0; m < measureCount; m++) {
            hits[id][m] = [];
        }
    });
    return hits;
}

function emptySection(name = '', measureCount = 1) {
    const drumIds = CHILINGA_DRUMS.map((d) => d.id);
    return {
        name,
        measureCount,
        repeatX: 1,
        repeats: Array.from({ length: measureCount }, () => ({ begin: false, end: false })),
        hits: emptyHits(measureCount, drumIds),
    };
}

function defaultState() {
    return {
        version: 3,
        timeSignature: '4/4',
        beats: BEATS,
        optionalInstruments: [],
        sections: [emptySection('Toque', 1)],
    };
}

/**
 * @param {unknown} raw
 */
function normalizeHit(raw, beats) {
    if (typeof raw === 'number' && Number.isInteger(raw)) {
        return { beat: raw, stroke: 'nota' };
    }
    if (!raw || typeof raw !== 'object') return null;

    const beat = parseInt(raw.beat, 10);
    if (!Number.isInteger(beat) || beat < 0 || beat >= beats) return null;

    if (raw.stroke && typeof raw.stroke === 'string') {
        return { beat, stroke: raw.stroke };
    }

    // Migración v2: mano + tipo
    if (raw.type === 'accent') return { beat, stroke: 'acentuado' };
    return { beat, stroke: 'nota' };
}

function normalizeHitList(list, beats) {
    if (!Array.isArray(list)) return [];
    const out = [];
    const seen = new Set();
    list.forEach((item) => {
        const hit = normalizeHit(item, beats);
        if (!hit || seen.has(hit.beat)) return;
        seen.add(hit.beat);
        out.push(hit);
    });
    out.sort((a, b) => a.beat - b.beat);
    return out;
}

function migrateV2ToV3(raw) {
    const measureCount = Math.min(4, Math.max(1, parseInt(raw.measureCount, 10) || 1));
    const section = emptySection('Toque', measureCount);
    const src = raw.hits && typeof raw.hits === 'object' ? raw.hits : {};

    Object.keys(src).forEach((oldId) => {
        const id = migrateDrumId(oldId);
        if (!section.hits[id]) return;
        const row = src[oldId];
        if (!Array.isArray(row)) return;
        const rows = row.length && !Array.isArray(row[0]) ? [row] : row;
        for (let m = 0; m < measureCount; m++) {
            section.hits[id][m] = normalizeHitList(rows[m] ?? [], BEATS);
        }
    });

    if (Array.isArray(raw.repeats)) {
        for (let m = 0; m < measureCount; m++) {
            const r = raw.repeats[m];
            if (r && typeof r === 'object') {
                section.repeats[m] = { begin: !!r.begin, end: !!r.end };
            }
        }
    }

    return {
        version: 3,
        timeSignature: raw.timeSignature === '2/4' ? '2/4' : '4/4',
        beats: BEATS,
        optionalInstruments: [],
        sections: [section],
    };
}

/**
 * @param {unknown} raw
 */
export function normalizeScore(raw) {
    if (!raw || typeof raw !== 'object') {
        return defaultState();
    }

    const version = parseInt(raw.version, 10) || 2;
    if (version < 3 || !Array.isArray(raw.sections)) {
        if (raw.hits) return migrateV2ToV3(raw);
        return defaultState();
    }

    const optional = Array.isArray(raw.optionalInstruments)
        ? raw.optionalInstruments.map(migrateDrumId).filter((id) => OPTIONAL_DRUMS.some((d) => d.id === id))
        : [];

    const drumIds = [...CHILINGA_DRUMS.map((d) => d.id), ...optional];
    const sections = raw.sections.map((sec, si) => {
        const measureCount = Math.min(8, Math.max(1, parseInt(sec.measureCount, 10) || 1));
        const hits = emptyHits(measureCount, drumIds);
        const srcHits = sec.hits && typeof sec.hits === 'object' ? sec.hits : {};

        drumIds.forEach((id) => {
            const row = srcHits[id];
            if (!Array.isArray(row)) return;
            const rows = row.length && !Array.isArray(row[0]) ? [row] : row;
            for (let m = 0; m < measureCount; m++) {
                hits[id][m] = normalizeHitList(rows[m] ?? [], BEATS);
            }
        });

        const repeats = Array.from({ length: measureCount }, (_, m) => {
            const r = sec.repeats?.[m];
            return r && typeof r === 'object'
                ? { begin: !!r.begin, end: !!r.end }
                : { begin: false, end: false };
        });

        return {
            name: typeof sec.name === 'string' ? sec.name : `Sección ${si + 1}`,
            measureCount,
            repeatX: Math.min(8, Math.max(1, parseInt(sec.repeatX, 10) || 1)),
            repeats,
            hits,
        };
    });

    if (!sections.length) sections.push(emptySection('Toque', 1));

    return {
        version: 3,
        timeSignature: raw.timeSignature === '2/4' ? '2/4' : '4/4',
        beats: BEATS,
        optionalInstruments: optional,
        sections,
    };
}

function activeDrums(state) {
    const opt = state.optionalInstruments || [];
    return [
        ...CHILINGA_DRUMS,
        ...OPTIONAL_DRUMS.filter((d) => opt.includes(d.id)),
    ];
}

function findHit(section, drumId, measure, beat) {
    return (section.hits[drumId]?.[measure] || []).find((h) => h.beat === beat) || null;
}

function strokeToKey(drum, stroke) {
    const base = drum.pitch;
    switch (stroke) {
        case 'chapa':
        case 'dedo':
            return `${base}/x`;
        case 'agudo':
            return `${base}/tu`;
        case 'slap':
            return `${base}/ci`;
        case 'palma':
            return `${base}/d`;
        case 'abierto':
        case 'nota':
        case 'tapado':
        case 'presionado':
        case 'acentuado':
        default:
            return base;
    }
}

/** Posiciones VexFlow: 3 = arriba, 4 = abajo (según pág. 2 Nomenclatura del cuadernillo). */
function applyStrokeModifiers(note, stroke) {
    if (stroke === 'acentuado') {
        note.addModifier(new Articulation('a>').setPosition(4));
    } else if (stroke === 'tapado') {
        note.addModifier(new Articulation('a-').setPosition(3));
    } else if (stroke === 'presionado') {
        note.addModifier(new Articulation('a-').setPosition(4));
    }
}

function sectionHasHits(section, drums) {
    return drums.some((d) =>
        (section.hits[d.id] || []).some((arr) => arr && arr.length > 0)
    );
}

/**
 * @param {HTMLElement} container
 */
export function renderPartituraVexflow(container, rawData) {
    if (!container) return false;
    const data = normalizeScore(rawData);
    const drums = activeDrums(data);
    const hasAny = data.sections.some((sec) => sectionHasHits(sec, drums));
    if (!hasAny) {
        container.innerHTML = '<p class="text-muted small mb-0">Sin golpes cargados en la rejilla.</p>';
        return false;
    }

    const renderId = 'vf-' + Math.random().toString(36).slice(2, 10);
    container.innerHTML = '';
    container.className = 'programa-partitura-score';

    data.sections.forEach((section, si) => {
        if (!sectionHasHits(section, drums)) return;

        const head = document.createElement('div');
        head.className = 'programa-partitura-sec-head small fw-semibold text-muted mb-1 mt-2';
        head.textContent = section.name + (section.repeatX > 1 ? ` ×${section.repeatX}` : '');
        container.appendChild(head);

        const mountId = `${renderId}-s${si}`;
        const mount = document.createElement('div');
        mount.id = mountId;
        mount.setAttribute('role', 'img');
        mount.setAttribute('aria-label', `Sección ${section.name}`);
        container.appendChild(mount);

        const beats = data.beats;
        const staveWidth = beats * 16 + 56;
        const staveHeight = 58;
        const labelWidth = 108;
        const width = labelWidth + section.measureCount * (staveWidth + 12) + 40;
        const activeRows = drums.filter((drum) => {
            for (let m = 0; m < section.measureCount; m++) {
                if ((section.hits[drum.id]?.[m] || []).length) return true;
            }
            return false;
        });
        const height = Math.max(80, activeRows.length * staveHeight + 24);

        const vf = new Factory({ renderer: { elementId: mountId, width, height } });
        const ctx = vf.getContext();
        let y = 12;

        activeRows.forEach((drum, di) => {
            let x = labelWidth;
            ctx.setFont('Inter', 9, '500');
            ctx.setFillStyle('#84745c');
            ctx.fillText(drum.label, 4, y + staveHeight / 2 + 4);

            for (let m = 0; m < section.measureCount; m++) {
                const stave = vf.Stave({ x, y, width: staveWidth });
                if (di === 0 && m === 0 && si === 0) {
                    stave.addClef('percussion').addTimeSignature(data.timeSignature);
                }
                const rep = section.repeats[m] || { begin: false, end: false };
                if (rep.begin) stave.setBegBarType(BarlineType.REPEAT_BEGIN);
                if (rep.end) stave.setEndBarType(BarlineType.REPEAT_END);
                stave.setContext(ctx);

                const tickables = [];
                for (let b = 0; b < beats; b++) {
                    const hit = findHit(section, drum.id, m, b);
                    if (hit) {
                        const note = vf.StaveNote({
                            keys: [strokeToKey(drum, hit.stroke)],
                            duration: '16',
                            auto_stem: true,
                        });
                        if (drum.stem === -1) note.setStemDirection(-1);
                        applyStrokeModifiers(note, hit.stroke);
                        tickables.push(note);
                    } else {
                        tickables.push(new GhostNote({ duration: '16' }));
                    }
                }

                const voice = vf.Voice({ time: data.timeSignature });
                voice.setStrict(false);
                voice.addTickables(tickables);
                vf.Formatter().joinVoices([voice]).formatToStave([voice], stave);
                stave.draw();
                voice.draw(ctx, stave);
                x += staveWidth + 8;
            }
            y += staveHeight;
        });
    });

    return true;
}

function exportState(state) {
    return JSON.parse(JSON.stringify(state));
}

/**
 * Transcripción simplificada de «Toque de Chilinga» (Cuadernillo pág. 3).
 * Referencia: Toques_chilinga_compressed.pdf — Nomenclatura pág. 2, Equivalencias pág. 1.
 */
function demoToqueChilinga() {
    const llamada = emptySection('Llamada inicial y final', 2);
    llamada.repeatX = 1;
    // Patrón sincopado de llamada (corcheas y semicorcheas — compás 1)
    llamada.hits.repique[0] = [
        { beat: 0, stroke: 'nota' }, { beat: 2, stroke: 'nota' }, { beat: 3, stroke: 'chapa' },
        { beat: 6, stroke: 'nota' }, { beat: 8, stroke: 'chapa' }, { beat: 10, stroke: 'nota' },
        { beat: 12, stroke: 'nota' }, { beat: 14, stroke: 'chapa' },
    ];
    llamada.hits.redoblante[0] = [
        { beat: 1, stroke: 'acentuado' }, { beat: 4, stroke: 'nota' }, { beat: 7, stroke: 'nota' },
        { beat: 9, stroke: 'acentuado' }, { beat: 13, stroke: 'nota' },
    ];
    llamada.hits.surdo_grave[0] = [{ beat: 0, stroke: 'nota' }, { beat: 8, stroke: 'nota' }];
    llamada.hits.surdo_agudo[0] = [{ beat: 4, stroke: 'nota' }, { beat: 12, stroke: 'nota' }];

    const toque = emptySection('Toque', 1);
    toque.repeatX = 1;
    // Surdo Grave: negras en 1 y 3
    toque.hits.surdo_grave[0] = [{ beat: 0, stroke: 'nota' }, { beat: 8, stroke: 'nota' }];
    // Surdo Agudo: negras en 2 y 4
    toque.hits.surdo_agudo[0] = [{ beat: 4, stroke: 'nota' }, { beat: 12, stroke: 'nota' }];
    // Surdo Medio: dos semicorcheas + corchea en 1 y 3
    toque.hits.surdo_medio[0] = [
        { beat: 0, stroke: 'nota' }, { beat: 1, stroke: 'nota' }, { beat: 2, stroke: 'nota' },
        { beat: 8, stroke: 'nota' }, { beat: 9, stroke: 'nota' }, { beat: 10, stroke: 'nota' },
    ];
    // Redoblante y Repique: corcheas, acento en tiempos fuertes
    const corcheasAcento = [0, 4, 8, 12];
    const corcheas = [0, 2, 4, 6, 8, 10, 12, 14];
    toque.hits.redoblante[0] = corcheas.map((b) => ({
        beat: b,
        stroke: corcheasAcento.includes(b) ? 'acentuado' : 'nota',
    }));
    toque.hits.repique[0] = corcheas.map((b) => ({
        beat: b,
        stroke: corcheasAcento.includes(b) ? 'acentuado' : 'nota',
    }));
    // Timbal: dos semicorcheas + silencio de corchea (×4 por compás)
    toque.hits.timbal[0] = [
        { beat: 0, stroke: 'abierto' }, { beat: 1, stroke: 'abierto' },
        { beat: 4, stroke: 'abierto' }, { beat: 5, stroke: 'abierto' },
        { beat: 8, stroke: 'abierto' }, { beat: 9, stroke: 'abierto' },
        { beat: 12, stroke: 'abierto' }, { beat: 13, stroke: 'abierto' },
    ];

    const intermedia = emptySection('Llamada intermedia', 1);
    intermedia.repeatX = 4;
    // Semicorcheas + corchea tras silencio de corchea
    intermedia.hits.repique[0] = [2, 3, 4, 6, 7, 8, 10, 11, 12, 14, 15].map((b) => ({ beat: b, stroke: 'nota' }));
    intermedia.hits.redoblante[0] = [2, 3, 4, 6, 7, 8, 10, 11, 12, 14, 15].map((b) => ({ beat: b, stroke: 'nota' }));
    // Surdos: silencio de negra + dos corcheas
    intermedia.hits.surdo_grave[0] = [{ beat: 4, stroke: 'nota' }, { beat: 6, stroke: 'nota' }];
    intermedia.hits.surdo_agudo[0] = [{ beat: 4, stroke: 'nota' }, { beat: 6, stroke: 'nota' }];
    intermedia.hits.surdo_medio[0] = [{ beat: 4, stroke: 'nota' }, { beat: 6, stroke: 'nota' }];

    return {
        version: 3,
        timeSignature: '4/4',
        beats: BEATS,
        optionalInstruments: [],
        sections: [llamada, toque, intermedia],
    };
}

/**
 * Editor admin: secciones + rejilla + vista previa.
 */
export function initPartituraEditor(root) {
    if (!root) return;

    const dataEl = root.querySelector('[data-partitura-initial]');
    const hidden = root.querySelector('[data-partitura-input]');
    const gridWrap = root.querySelector('[data-partitura-grid]');
    const sectionsWrap = root.querySelector('[data-partitura-sections]');
    const preview = root.querySelector('[data-partitura-preview]');
    const optionalWrap = root.querySelector('[data-partitura-optional]');
    const clearBtn = root.querySelector('[data-partitura-clear]');
    const demoBtn = root.querySelector('[data-partitura-demo]');
    const addSectionBtn = root.querySelector('[data-partitura-add-section]');
    const removeCheck = root.querySelector('[data-partitura-remove]');

    let state = normalizeScore(null);
    if (dataEl && dataEl.textContent.trim()) {
        try {
            state = normalizeScore(JSON.parse(dataEl.textContent));
        } catch (e) { /* ignore */ }
    }

    let activeSection = 0;

    function syncHidden() {
        if (hidden) {
            hidden.value = removeCheck?.checked ? '' : JSON.stringify(exportState(state));
        }
    }

    function cycleStroke(drumId, current) {
        const options = [null, ...(STROKES_BY_DRUM[drumId] || ['nota'])];
        const idx = current
            ? options.findIndex((s) => s === current.stroke)
            : 0;
        const next = options[(idx + 1) % options.length];
        return next ? { stroke: next } : null;
    }

    function applyCellVisual(btn, hit, drumId) {
        btn.classList.remove('is-on', 'stroke-chapa', 'stroke-accent', 'stroke-open');
        btn.textContent = '';
        if (!hit) {
            btn.setAttribute('aria-pressed', 'false');
            btn.setAttribute('aria-label', btn.dataset.baseLabel);
            return;
        }
        btn.classList.add('is-on');
        if (['chapa', 'dedo'].includes(hit.stroke)) btn.classList.add('stroke-chapa');
        if (hit.stroke === 'acentuado' || hit.stroke === 'agudo') btn.classList.add('stroke-accent');
        if (['abierto', 'slap'].includes(hit.stroke)) btn.classList.add('stroke-open');
        if (hit.stroke === 'palma') btn.classList.add('stroke-palma');
        btn.textContent = STROKE_SHORT[hit.stroke] || '•';
        btn.setAttribute('aria-pressed', 'true');
        btn.setAttribute('aria-label', `${btn.dataset.baseLabel}, ${STROKE_LABELS[hit.stroke] || hit.stroke}`);
    }

    function buildOptionalToggles() {
        if (!optionalWrap) return;
        optionalWrap.innerHTML = '';
        OPTIONAL_DRUMS.forEach((d) => {
            const lbl = document.createElement('label');
            lbl.className = 'form-check form-check-inline small';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'form-check-input';
            cb.checked = state.optionalInstruments.includes(d.id);
            cb.addEventListener('change', () => {
                if (cb.checked) {
                    if (!state.optionalInstruments.includes(d.id)) {
                        state.optionalInstruments.push(d.id);
                    }
                } else {
                    state.optionalInstruments = state.optionalInstruments.filter((x) => x !== d.id);
                }
                state.sections.forEach((sec) => {
                    if (!sec.hits[d.id]) {
                        sec.hits[d.id] = Array.from({ length: sec.measureCount }, () => []);
                    }
                });
                buildGrid();
                syncHidden();
                renderPartituraVexflow(preview, state);
            });
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(' ' + d.label));
            optionalWrap.appendChild(lbl);
        });
    }

    function buildSectionsPanel() {
        if (!sectionsWrap) return;
        sectionsWrap.innerHTML = '';

        const tabs = document.createElement('div');
        tabs.className = 'programa-partitura-section-tabs d-flex flex-wrap gap-1 mb-2';
        state.sections.forEach((sec, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm ' + (i === activeSection ? 'btn-warning' : 'btn-outline-secondary');
            btn.textContent = sec.name || `Sección ${i + 1}`;
            btn.addEventListener('click', () => {
                activeSection = i;
                buildSectionsPanel();
                buildGrid();
            });
            tabs.appendChild(btn);
        });
        sectionsWrap.appendChild(tabs);

        const sec = state.sections[activeSection];
        if (!sec) return;

        const controls = document.createElement('div');
        controls.className = 'row g-2 align-items-end mb-2';

        const nameCol = document.createElement('div');
        nameCol.className = 'col-md-4';
        nameCol.innerHTML = '<label class="form-label small mb-0">Nombre</label>';
        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'form-control form-control-sm';
        nameInput.value = sec.name;
        nameInput.placeholder = 'Ej. Llamada inicial, Toque, Variación…';
        nameInput.addEventListener('input', () => {
            sec.name = nameInput.value;
            syncHidden();
            buildSectionsPanel();
        });
        nameCol.appendChild(nameInput);

        const measCol = document.createElement('div');
        measCol.className = 'col-auto';
        measCol.innerHTML = '<label class="form-label small mb-0">Compases</label>';
        const measSel = document.createElement('select');
        measSel.className = 'form-select form-select-sm';
        [1, 2, 3, 4].forEach((n) => {
            const o = document.createElement('option');
            o.value = String(n);
            o.textContent = String(n);
            if (n === sec.measureCount) o.selected = true;
            measSel.appendChild(o);
        });
        measSel.addEventListener('change', () => {
            const mc = parseInt(measSel.value, 10);
            const drums = activeDrums(state);
            const next = emptyHits(mc, drums.map((d) => d.id));
            drums.forEach((d) => {
                for (let m = 0; m < mc; m++) {
                    next[d.id][m] = sec.hits[d.id]?.[m]?.map((h) => ({ ...h })) || [];
                }
            });
            sec.measureCount = mc;
            sec.hits = next;
            sec.repeats = Array.from({ length: mc }, (_, m) => sec.repeats[m] || { begin: false, end: false });
            buildGrid();
            syncHidden();
            renderPartituraVexflow(preview, state);
        });
        measCol.appendChild(measSel);

        const repCol = document.createElement('div');
        repCol.className = 'col-auto';
        repCol.innerHTML = '<label class="form-label small mb-0">Repetir</label>';
        const repSel = document.createElement('select');
        repSel.className = 'form-select form-select-sm';
        [1, 2, 3, 4, 8].forEach((n) => {
            const o = document.createElement('option');
            o.value = String(n);
            o.textContent = n === 1 ? '×1' : `×${n}`;
            if (n === sec.repeatX) o.selected = true;
            repSel.appendChild(o);
        });
        repSel.addEventListener('change', () => {
            sec.repeatX = parseInt(repSel.value, 10);
            syncHidden();
        });
        repCol.appendChild(repSel);

        const delCol = document.createElement('div');
        delCol.className = 'col-auto';
        if (state.sections.length > 1) {
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-sm btn-outline-danger';
            delBtn.textContent = 'Quitar sección';
            delBtn.addEventListener('click', () => {
                state.sections.splice(activeSection, 1);
                activeSection = Math.max(0, activeSection - 1);
                buildSectionsPanel();
                buildGrid();
                syncHidden();
                renderPartituraVexflow(preview, state);
            });
            delCol.appendChild(delBtn);
        }

        controls.append(nameCol, measCol, repCol, delCol);
        sectionsWrap.appendChild(controls);

        const repRow = document.createElement('div');
        repRow.className = 'd-flex flex-wrap gap-2 mb-2';
        for (let m = 0; m < sec.measureCount; m++) {
            const sel = document.createElement('select');
            sel.className = 'form-select form-select-sm w-auto';
            sel.setAttribute('aria-label', `Repetición compás ${m + 1}`);
            [['', 'Sin ⟲'], ['begin', 'Inicio ⟲'], ['end', 'Fin ⟲'], ['both', 'Inicio y fin ⟲']].forEach(([v, l]) => {
                const o = document.createElement('option');
                o.value = v;
                o.textContent = `C${m + 1}: ${l}`;
                sel.appendChild(o);
            });
            const r = sec.repeats[m] || { begin: false, end: false };
            if (r.begin && r.end) sel.value = 'both';
            else if (r.begin) sel.value = 'begin';
            else if (r.end) sel.value = 'end';
            sel.addEventListener('change', () => {
                const v = sel.value;
                sec.repeats[m] = { begin: v === 'begin' || v === 'both', end: v === 'end' || v === 'both' };
                syncHidden();
                renderPartituraVexflow(preview, state);
            });
            repRow.appendChild(sel);
        }
        sectionsWrap.appendChild(repRow);
    }

    function buildGrid() {
        if (!gridWrap) return;
        gridWrap.innerHTML = '';
        const sec = state.sections[activeSection];
        if (!sec) return;

        const beats = state.beats;
        const drums = activeDrums(state);
        const table = document.createElement('table');
        table.className = 'table table-sm table-bordered programa-partitura-grid mb-0';

        const thead = document.createElement('thead');
        const hr = document.createElement('tr');
        const th0 = document.createElement('th');
        th0.textContent = 'Instrumento';
        th0.scope = 'col';
        hr.appendChild(th0);
        for (let m = 0; m < sec.measureCount; m++) {
            for (let b = 0; b < beats; b++) {
                const th = document.createElement('th');
                th.className = 'text-center programa-partitura-grid-beat';
                th.scope = 'col';
                if (b % 4 === 0) {
                    th.classList.add('programa-partitura-grid-downbeat');
                    th.textContent = `${m + 1}.${Math.floor(b / 4) + 1}`;
                }
                hr.appendChild(th);
            }
        }
        thead.appendChild(hr);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        drums.forEach((drum) => {
            const tr = document.createElement('tr');
            const label = document.createElement('th');
            label.scope = 'row';
            label.textContent = drum.label;
            label.className = 'programa-partitura-drum-label';
            tr.appendChild(label);

            for (let m = 0; m < sec.measureCount; m++) {
                for (let b = 0; b < beats; b++) {
                    const td = document.createElement('td');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'programa-partitura-cell';
                    const baseLabel = `${drum.label}, compás ${m + 1}, pulso ${b + 1}`;
                    btn.dataset.baseLabel = baseLabel;
                    btn.dataset.drum = drum.id;
                    btn.dataset.measure = String(m);
                    btn.dataset.beat = String(b);
                    const hit = findHit(sec, drum.id, m, b);
                    applyCellVisual(btn, hit, drum.id);
                    btn.addEventListener('click', () => {
                        const mid = parseInt(btn.dataset.measure, 10);
                        const beat = parseInt(btn.dataset.beat, 10);
                        const list = sec.hits[drum.id][mid];
                        const idx = list.findIndex((h) => h.beat === beat);
                        const cur = idx >= 0 ? list[idx] : null;
                        const next = cycleStroke(drum.id, cur);
                        if (idx >= 0) list.splice(idx, 1);
                        if (next) {
                            list.push({ beat, stroke: next.stroke });
                            list.sort((a, b) => a.beat - b.beat);
                        }
                        applyCellVisual(btn, next ? list.find((h) => h.beat === beat) : null, drum.id);
                        syncHidden();
                        renderPartituraVexflow(preview, state);
                    });
                    td.appendChild(btn);
                    tr.appendChild(td);
                }
            }
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        gridWrap.appendChild(table);
    }

    if (addSectionBtn) {
        addSectionBtn.addEventListener('click', () => {
            state.sections.push(emptySection('', 1));
            activeSection = state.sections.length - 1;
            buildSectionsPanel();
            buildGrid();
            syncHidden();
            renderPartituraVexflow(preview, state);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            const sec = state.sections[activeSection];
            if (!sec) return;
            const drums = activeDrums(state);
            sec.hits = emptyHits(sec.measureCount, drums.map((d) => d.id));
            sec.repeats = Array.from({ length: sec.measureCount }, () => ({ begin: false, end: false }));
            buildGrid();
            syncHidden();
            renderPartituraVexflow(preview, state);
        });
    }

    if (demoBtn) {
        demoBtn.addEventListener('click', () => {
            state = demoToqueChilinga();
            activeSection = 0;
            buildOptionalToggles();
            buildSectionsPanel();
            buildGrid();
            syncHidden();
            renderPartituraVexflow(preview, state);
        });
    }

    if (removeCheck) removeCheck.addEventListener('change', syncHidden);
    const form = root.closest('form');
    if (form) form.addEventListener('submit', syncHidden);

    buildOptionalToggles();
    buildSectionsPanel();
    buildGrid();
    syncHidden();
    renderPartituraVexflow(preview, state);
}

export function initPartituraViewers() {
    document.querySelectorAll('[data-partitura-viewer]').forEach((el) => {
        const raw = el.getAttribute('data-partitura-json');
        if (!raw) return;
        try {
            renderPartituraVexflow(el, JSON.parse(raw));
        } catch (e) {
            el.innerHTML = '<p class="text-muted small">No se pudo mostrar la partitura digital.</p>';
        }
    });
}
