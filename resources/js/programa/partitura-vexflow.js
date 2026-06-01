/**
 * Partitura de percusión (La Chilinga) — editor en rejilla + render VexFlow 4.
 */
import { Factory, GhostNote } from 'vexflow';

export const CHILINGA_DRUMS = [
    { id: 'repique', label: 'Repique', vexKey: 'c/5/x2' },
    { id: 'redoblante', label: 'Redoblante', vexKey: 'f/4' },
    { id: 'timbal', label: 'Timbal', vexKey: 'd/4/x2' },
    { id: 'medio', label: 'Medio', vexKey: 'e/5/x2' },
    { id: 'fondo_grave', label: 'Fondo grave', vexKey: 'a/4', stem: -1 },
    { id: 'fondo_agudo', label: 'Fondo agudo', vexKey: 'g/4', stem: -1 },
];

const DEFAULT_STATE = {
    version: 1,
    timeSignature: '4/4',
    beats: 16,
    measureCount: 1,
    hits: {},
};

function emptyHits(measureCount, beats) {
    const hits = {};
    CHILINGA_DRUMS.forEach((d) => {
        hits[d.id] = [];
        for (let m = 0; m < measureCount; m++) {
            hits[d.id][m] = [];
        }
    });
    return hits;
}

export function normalizeScore(raw) {
    if (!raw || typeof raw !== 'object') {
        return { ...DEFAULT_STATE, hits: emptyHits(1, 16) };
    }
    const measureCount = Math.min(4, Math.max(1, parseInt(raw.measureCount, 10) || 1));
    const beats = 16;
    const hits = emptyHits(measureCount, beats);
    const src = raw.hits && typeof raw.hits === 'object' ? raw.hits : {};
    CHILINGA_DRUMS.forEach((d) => {
        const row = src[d.id];
        if (!row) return;
        if (Array.isArray(row) && row.length && !Array.isArray(row[0])) {
            hits[d.id][0] = row.filter((n) => Number.isInteger(n) && n >= 0 && n < beats);
            return;
        }
        if (Array.isArray(row)) {
            for (let m = 0; m < measureCount; m++) {
                const cell = row[m];
                hits[d.id][m] = Array.isArray(cell)
                    ? cell.filter((n) => Number.isInteger(n) && n >= 0 && n < beats)
                    : [];
            }
        }
    });
    return {
        version: 1,
        timeSignature: raw.timeSignature === '2/4' ? '2/4' : '4/4',
        beats,
        measureCount,
        hits,
    };
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
    const height = 200;

    const vf = new Factory({ renderer: { elementId: renderId, width, height } });
    const ctx = vf.getContext();
    let x = 12;

    for (let m = 0; m < measureCount; m++) {
        const stave = vf.Stave({ x, y: 30, width: staveWidth });
        if (m === 0) {
            stave.addClef('percussion').addTimeSignature(data.timeSignature);
        }
        stave.setContext(ctx);

        const tickables = [];
        for (let b = 0; b < beats; b++) {
            const keys = [];
            CHILINGA_DRUMS.forEach((drum) => {
                const arr = data.hits[drum.id]?.[m] || [];
                if (arr.includes(b)) keys.push(drum.vexKey);
            });
            if (keys.length > 0) {
                const note = vf.StaveNote({ keys, duration: '16', auto_stem: true });
                const needsDown = CHILINGA_DRUMS.filter(
                    (d) => data.hits[d.id]?.[m]?.includes(b) && d.stem === -1
                );
                if (needsDown.length && keys.length === 1) {
                    note.setStemDirection(-1);
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
        version: 1,
        timeSignature: state.timeSignature,
        beats: state.beats,
        measureCount: state.measureCount,
        hits: state.hits,
    };
}

/**
 * Editor admin: rejilla clickeable + vista previa.
 */
export function initPartituraEditor(root) {
    if (!root) return;

    const dataEl = root.querySelector('[data-partitura-initial]');
    const hidden = root.querySelector('[data-partitura-input]');
    const gridWrap = root.querySelector('[data-partitura-grid]');
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
                    btn.setAttribute('aria-label', drum.label + ', compás ' + (m + 1) + ', posición ' + (b + 1));
                    btn.dataset.drum = drum.id;
                    btn.dataset.measure = String(m);
                    btn.dataset.beat = String(b);
                    if ((state.hits[drum.id]?.[m] || []).includes(b)) {
                        btn.classList.add('is-on');
                        btn.setAttribute('aria-pressed', 'true');
                    } else {
                        btn.setAttribute('aria-pressed', 'false');
                    }
                    btn.addEventListener('click', () => {
                        const mid = parseInt(btn.dataset.measure, 10);
                        const beat = parseInt(btn.dataset.beat, 10);
                        const list = state.hits[drum.id][mid];
                        const idx = list.indexOf(beat);
                        if (idx >= 0) {
                            list.splice(idx, 1);
                            btn.classList.remove('is-on');
                            btn.setAttribute('aria-pressed', 'false');
                        } else {
                            list.push(beat);
                            list.sort((a, b) => a - b);
                            btn.classList.add('is-on');
                            btn.setAttribute('aria-pressed', 'true');
                        }
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
        const next = emptyHits(mc, state.beats);
        CHILINGA_DRUMS.forEach((d) => {
            for (let m = 0; m < mc; m++) {
                next[d.id][m] = state.hits[d.id]?.[m] ? [...state.hits[d.id][m]] : [];
            }
        });
        state.measureCount = mc;
        state.hits = next;
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
            state.hits = emptyHits(state.measureCount, state.beats);
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
                    repique: [[0, 4, 8, 12]],
                    redoblante: [[2, 6, 10, 14]],
                    fondo_grave: [[0, 8]],
                    fondo_agudo: [[4, 12]],
                },
            });
            if (measureSelect) measureSelect.value = '1';
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
