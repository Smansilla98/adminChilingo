/**
 * Partitura de percusión (La Chilinga) — editor en rejilla + render VexFlow 4.
 */
import { Factory, GhostNote, Annotation, BarlineType } from 'vexflow';

export const CHILINGA_DRUMS = [
    { id: 'repique', label: 'Repique', vexKey: 'c/5/x2' },
    { id: 'redoblante', label: 'Redoblante', vexKey: 'f/4' },
    { id: 'timbal', label: 'Timbal', vexKey: 'd/4/x2' },
    { id: 'medio', label: 'Medio', vexKey: 'e/5/x2' },
    { id: 'fondo_grave', label: 'Fondo grave', vexKey: 'a/4', stem: -1 },
    { id: 'fondo_agudo', label: 'Fondo agudo', vexKey: 'g/4', stem: -1 },
];

const DEFAULT_HAND = 'I';
const DEFAULT_TYPE = 'normal';

const DEFAULT_STATE = {
    version: 2,
    timeSignature: '4/4',
    beats: 16,
    measureCount: 1,
    hits: {},
    repeats: [],
};

function emptyRepeats(measureCount) {
    return Array.from({ length: measureCount }, () => ({ begin: false, end: false }));
}

function emptyHits(measureCount) {
    const hits = {};
    CHILINGA_DRUMS.forEach((d) => {
        hits[d.id] = [];
        for (let m = 0; m < measureCount; m++) {
            hits[d.id][m] = [];
        }
    });
    return hits;
}

function normalizeHit(raw) {
    if (typeof raw === 'number' && Number.isInteger(raw)) {
        return { beat: raw, hand: DEFAULT_HAND, type: DEFAULT_TYPE };
    }
    if (!raw || typeof raw !== 'object') return null;
    const beat = parseInt(raw.beat, 10);
    if (!Number.isInteger(beat)) return null;
    const hand = raw.hand === 'D' ? 'D' : DEFAULT_HAND;
    const type = raw.type === 'accent' ? 'accent' : DEFAULT_TYPE;
    return { beat, hand, type };
}

function normalizeHitList(list, beats) {
    if (!Array.isArray(list)) return [];
    const out = [];
    const seen = new Set();
    list.forEach((item) => {
        const hit = normalizeHit(item);
        if (!hit || hit.beat < 0 || hit.beat >= beats || seen.has(hit.beat)) return;
        seen.add(hit.beat);
        out.push(hit);
    });
    out.sort((a, b) => a.beat - b.beat);
    return out;
}

export function normalizeScore(raw) {
    if (!raw || typeof raw !== 'object') {
        return { ...DEFAULT_STATE, hits: emptyHits(1), repeats: emptyRepeats(1) };
    }
    const measureCount = Math.min(4, Math.max(1, parseInt(raw.measureCount, 10) || 1));
    const beats = 16;
    const hits = emptyHits(measureCount);
    const src = raw.hits && typeof raw.hits === 'object' ? raw.hits : {};

    CHILINGA_DRUMS.forEach((d) => {
        const row = src[d.id];
        if (!row) return;
        if (Array.isArray(row) && row.length && !Array.isArray(row[0])) {
            hits[d.id][0] = normalizeHitList(row, beats);
            return;
        }
        if (Array.isArray(row)) {
            for (let m = 0; m < measureCount; m++) {
                hits[d.id][m] = normalizeHitList(row[m], beats);
            }
        }
    });

    let repeats = emptyRepeats(measureCount);
    if (Array.isArray(raw.repeats)) {
        for (let m = 0; m < measureCount; m++) {
            const r = raw.repeats[m];
            if (r && typeof r === 'object') {
                repeats[m] = { begin: !!r.begin, end: !!r.end };
            }
        }
    }

    return {
        version: 2,
        timeSignature: raw.timeSignature === '2/4' ? '2/4' : '4/4',
        beats,
        measureCount,
        hits,
        repeats,
    };
}

function findHit(data, drumId, measure, beat) {
    return (data.hits[drumId]?.[measure] || []).find((h) => h.beat === beat) || null;
}

function drumVexKey(drum, hit) {
    if (hit.type !== 'accent') return drum.vexKey;
    const parts = drum.vexKey.split('/');
    if (parts.length >= 2) {
        return `${parts[0]}/${parts[1]}/d`;
    }
    return drum.vexKey;
}

function handLabelAtBeat(data, measure, beat) {
    const hands = new Set();
    CHILINGA_DRUMS.forEach((drum) => {
        const hit = findHit(data, drum.id, measure, beat);
        if (hit) hands.add(hit.hand);
    });
    if (!hands.size) return '';
    if (hands.size === 1) return [...hands][0];
    return 'I/D';
}

/**
 * @param {HTMLElement} container — se vacía y se crea un div hijo para VexFlow
 */
export function renderPartituraVexflow(container, rawData) {
    if (!container) return false;
    const data = normalizeScore(rawData);
    const hasAny = CHILINGA_DRUMS.some((d) =>
        (data.hits[d.id] || []).some((arr) => arr && arr.length > 0)
    );
    if (!hasAny) {
        container.innerHTML = '<p class="text-muted small mb-0">Sin golpes cargados en la rejilla.</p>';
        return false;
    }

    const renderId = 'vf-' + Math.random().toString(36).slice(2, 10);
    container.innerHTML = '';
    const mount = document.createElement('div');
    mount.id = renderId;
    mount.setAttribute('role', 'img');
    mount.setAttribute('aria-label', 'Partitura de percusión');
    container.appendChild(mount);

    const measureCount = data.measureCount;
    const beats = data.beats;
    const staveWidth = beats * 18 + 60;
    const width = 20 + measureCount * (staveWidth + 16);
    const height = 220;

    const vf = new Factory({ renderer: { elementId: renderId, width, height } });
    const ctx = vf.getContext();
    let x = 12;

    for (let m = 0; m < measureCount; m++) {
        const stave = vf.Stave({ x, y: 30, width: staveWidth });
        if (m === 0) {
            stave.addClef('percussion').addTimeSignature(data.timeSignature);
        }
        const rep = data.repeats[m] || { begin: false, end: false };
        if (rep.begin) stave.setBegBarType(BarlineType.REPEAT_BEGIN);
        if (rep.end) stave.setEndBarType(BarlineType.REPEAT_END);
        stave.setContext(ctx);

        const tickables = [];
        for (let b = 0; b < beats; b++) {
            const keys = [];
            let needsDown = false;
            CHILINGA_DRUMS.forEach((drum) => {
                const hit = findHit(data, drum.id, m, b);
                if (hit) {
                    keys.push(drumVexKey(drum, hit));
                    if (drum.stem === -1) needsDown = true;
                }
            });
            if (keys.length > 0) {
                const note = vf.StaveNote({ keys, duration: '16', auto_stem: true });
                if (needsDown && keys.length === 1) {
                    note.setStemDirection(-1);
                }
                const label = handLabelAtBeat(data, m, b);
                if (label) {
                    const ann = new Annotation(label);
                    ann.setVerticalJustification(Annotation.VerticalJustify.BOTTOM);
                    note.addModifier(ann);
                }
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
        x += staveWidth + 16;
    }

    return true;
}

function exportState(state) {
    return {
        version: 2,
        timeSignature: state.timeSignature,
        beats: state.beats,
        measureCount: state.measureCount,
        hits: state.hits,
        repeats: state.repeats,
    };
}

const CELL_CYCLE = [
    null,
    { hand: 'I', type: 'normal' },
    { hand: 'D', type: 'normal' },
    { hand: 'I', type: 'accent' },
    { hand: 'D', type: 'accent' },
];

function cellState(hit) {
    if (!hit) return 0;
    const idx = CELL_CYCLE.findIndex(
        (s) => s && s.hand === hit.hand && s.type === hit.type
    );
    return idx >= 0 ? idx : 1;
}

function applyCellVisual(btn, hit) {
    btn.classList.remove('is-on', 'is-accent', 'hand-d');
    btn.textContent = '';
    if (!hit) {
        btn.setAttribute('aria-pressed', 'false');
        btn.setAttribute('aria-label', btn.dataset.baseLabel);
        return;
    }
    btn.classList.add('is-on');
    if (hit.type === 'accent') btn.classList.add('is-accent');
    if (hit.hand === 'D') btn.classList.add('hand-d');
    btn.textContent = hit.hand;
    btn.setAttribute('aria-pressed', 'true');
    const tipo = hit.type === 'accent' ? 'acento abierto' : 'normal';
    btn.setAttribute('aria-label', `${btn.dataset.baseLabel}, mano ${hit.hand}, golpe ${tipo}`);
}

/**
 * Editor admin: rejilla clickeable + vista previa.
 */
export function initPartituraEditor(root) {
    if (!root) return;

    const dataEl = root.querySelector('[data-partitura-initial]');
    const hidden = root.querySelector('[data-partitura-input]');
    const gridWrap = root.querySelector('[data-partitura-grid]');
    const repeatWrap = root.querySelector('[data-partitura-repeats]');
    const preview = root.querySelector('[data-partitura-preview]');
    const measureSelect = root.querySelector('[data-partitura-measures]');
    const clearBtn = root.querySelector('[data-partitura-clear]');
    const demoBtn = root.querySelector('[data-partitura-demo]');
    const removeCheck = root.querySelector('[data-partitura-remove]');

    let state = normalizeScore(null);
    if (dataEl && dataEl.textContent.trim()) {
        try {
            state = normalizeScore(JSON.parse(dataEl.textContent));
        } catch (e) {
            /* ignore */
        }
    }

    function syncHidden() {
        if (hidden) {
            hidden.value = removeCheck?.checked ? '' : JSON.stringify(exportState(state));
        }
    }

    function buildRepeats() {
        if (!repeatWrap) return;
        repeatWrap.innerHTML = '';
        const mc = state.measureCount;
        const row = document.createElement('div');
        row.className = 'programa-partitura-repeats d-flex flex-wrap gap-2 mb-2';
        for (let m = 0; m < mc; m++) {
            const rep = state.repeats[m] || { begin: false, end: false };
            const wrap = document.createElement('label');
            wrap.className = 'small d-flex align-items-center gap-1';
            const sel = document.createElement('select');
            sel.className = 'form-select form-select-sm w-auto';
            sel.setAttribute('aria-label', 'Repetición compás ' + (m + 1));
            sel.dataset.measure = String(m);
            [
                ['', 'Sin repetición'],
                ['begin', 'Inicio ⟲'],
                ['end', 'Fin ⟲'],
                ['both', 'Inicio y fin ⟲'],
            ].forEach(([val, label]) => {
                const opt = document.createElement('option');
                opt.value = val;
                opt.textContent = 'C' + (m + 1) + ': ' + label;
                sel.appendChild(opt);
            });
            if (rep.begin && rep.end) sel.value = 'both';
            else if (rep.begin) sel.value = 'begin';
            else if (rep.end) sel.value = 'end';
            else sel.value = '';
            sel.addEventListener('change', () => {
                const mid = parseInt(sel.dataset.measure, 10);
                const v = sel.value;
                state.repeats[mid] = {
                    begin: v === 'begin' || v === 'both',
                    end: v === 'end' || v === 'both',
                };
                syncHidden();
                renderPartituraVexflow(preview, state);
            });
            wrap.appendChild(sel);
            row.appendChild(wrap);
        }
        repeatWrap.appendChild(row);
    }

    function buildGrid() {
        if (!gridWrap) return;
        gridWrap.innerHTML = '';
        const beats = state.beats;
        const mc = state.measureCount;

        const table = document.createElement('table');
        table.className = 'table table-sm table-bordered programa-partitura-grid mb-0';

        const thead = document.createElement('thead');
        const hr = document.createElement('tr');
        const th0 = document.createElement('th');
        th0.textContent = 'Tambor';
        th0.scope = 'col';
        hr.appendChild(th0);
        for (let m = 0; m < mc; m++) {
            for (let b = 0; b < beats; b++) {
                const th = document.createElement('th');
                th.className = 'text-center programa-partitura-grid-beat';
                th.scope = 'col';
                th.title = 'Compás ' + (m + 1) + ', pulso ' + (b + 1);
                if (b % 4 === 0) th.classList.add('programa-partitura-grid-downbeat');
                th.textContent = b % 4 === 0 ? String(m + 1) + '.' + (Math.floor(b / 4) + 1) : '';
                hr.appendChild(th);
            }
        }
        thead.appendChild(hr);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        CHILINGA_DRUMS.forEach((drum) => {
            const tr = document.createElement('tr');
            const label = document.createElement('th');
            label.scope = 'row';
            label.textContent = drum.label;
            tr.appendChild(label);

            for (let m = 0; m < mc; m++) {
                for (let b = 0; b < beats; b++) {
                    const td = document.createElement('td');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'programa-partitura-cell';
                    const baseLabel = drum.label + ', compás ' + (m + 1) + ', posición ' + (b + 1);
                    btn.dataset.baseLabel = baseLabel;
                    btn.dataset.drum = drum.id;
                    btn.dataset.measure = String(m);
                    btn.dataset.beat = String(b);
                    const hit = findHit(state, drum.id, m, b);
                    applyCellVisual(btn, hit);
                    btn.addEventListener('click', () => {
                        const mid = parseInt(btn.dataset.measure, 10);
                        const beat = parseInt(btn.dataset.beat, 10);
                        const list = state.hits[drum.id][mid];
                        const idx = list.findIndex((h) => h.beat === beat);
                        let next;
                        if (idx < 0) {
                            next = CELL_CYCLE[1];
                        } else {
                            const cur = cellState(list[idx]);
                            const nxt = (cur + 1) % CELL_CYCLE.length;
                            next = CELL_CYCLE[nxt];
                        }
                        if (idx >= 0) list.splice(idx, 1);
                        if (next) {
                            list.push({ beat, hand: next.hand, type: next.type });
                            list.sort((a, b) => a.beat - b.beat);
                        }
                        applyCellVisual(btn, next ? list.find((h) => h.beat === beat) : null);
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

    function resizeMeasures(count) {
        const mc = Math.min(4, Math.max(1, count));
        const next = emptyHits(mc);
        CHILINGA_DRUMS.forEach((d) => {
            for (let m = 0; m < mc; m++) {
                next[d.id][m] = state.hits[d.id]?.[m]
                    ? state.hits[d.id][m].map((h) => ({ ...h }))
                    : [];
            }
        });
        const nextRepeats = emptyRepeats(mc);
        for (let m = 0; m < mc; m++) {
            nextRepeats[m] = state.repeats[m]
                ? { ...state.repeats[m] }
                : { begin: false, end: false };
        }
        state.measureCount = mc;
        state.hits = next;
        state.repeats = nextRepeats;
        buildRepeats();
        buildGrid();
        syncHidden();
        renderPartituraVexflow(preview, state);
    }

    if (measureSelect) {
        measureSelect.value = String(state.measureCount);
        measureSelect.addEventListener('change', () => {
            resizeMeasures(parseInt(measureSelect.value, 10));
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            state.hits = emptyHits(state.measureCount);
            state.repeats = emptyRepeats(state.measureCount);
            buildRepeats();
            buildGrid();
            syncHidden();
            renderPartituraVexflow(preview, state);
        });
    }

    if (demoBtn) {
        demoBtn.addEventListener('click', () => {
            state = normalizeScore({
                measureCount: 1,
                hits: {
                    repique: [[
                        { beat: 0, hand: 'I', type: 'normal' },
                        { beat: 4, hand: 'D', type: 'normal' },
                        { beat: 8, hand: 'I', type: 'accent' },
                        { beat: 12, hand: 'D', type: 'normal' },
                    ]],
                    redoblante: [[
                        { beat: 2, hand: 'D', type: 'normal' },
                        { beat: 6, hand: 'I', type: 'normal' },
                        { beat: 10, hand: 'D', type: 'accent' },
                        { beat: 14, hand: 'I', type: 'normal' },
                    ]],
                    fondo_grave: [[{ beat: 0, hand: 'I', type: 'normal' }, { beat: 8, hand: 'D', type: 'normal' }]],
                    fondo_agudo: [[{ beat: 4, hand: 'I', type: 'normal' }, { beat: 12, hand: 'D', type: 'accent' }]],
                },
                repeats: [{ begin: true, end: true }],
            });
            if (measureSelect) measureSelect.value = '1';
            buildRepeats();
            buildGrid();
            syncHidden();
            renderPartituraVexflow(preview, state);
        });
    }

    if (removeCheck) {
        removeCheck.addEventListener('change', syncHidden);
    }

    const form = root.closest('form');
    if (form) {
        form.addEventListener('submit', syncHidden);
    }

    buildRepeats();
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
