/**
 * Session View tipo Ableton para partituras Chilinga.
 * Tracks = instrumentos · Scenes = secciones · Clips = golpes del instrumento en esa sección.
 */
import {
    CHILINGA_DRUMS,
    OPTIONAL_DRUMS,
    STROKES_BY_DRUM,
    STROKE_LABELS,
    normalizeScore,
    renderPartituraVexflow,
} from './partitura-vexflow.js';

const BEATS = 16;

const TRACK_COLORS = {
    surdo_grave: '#e86a3c',
    surdo_agudo: '#f0a04b',
    surdo_medio: '#d1a054',
    redoblante: '#5b9ef0',
    timbal: '#9c8ad1',
    repique: '#4a9a86',
    agogo: '#c1432b',
    palmas: '#b6a488',
};

/** Biblioteca de patrones / ejercicios (clips arrastrables). */
const CLIP_LIBRARY = [
    {
        id: 'surdo_pulse',
        cat: 'ejercicios',
        label: 'Surdo 1–3',
        drum: 'surdo_grave',
        color: TRACK_COLORS.surdo_grave,
        hits: [{ beat: 0, stroke: 'nota' }, { beat: 8, stroke: 'nota' }],
    },
    {
        id: 'surdo_offbeat',
        cat: 'ejercicios',
        label: 'Surdo 2–4',
        drum: 'surdo_agudo',
        color: TRACK_COLORS.surdo_agudo,
        hits: [{ beat: 4, stroke: 'nota' }, { beat: 12, stroke: 'nota' }],
    },
    {
        id: 'medio_fill',
        cat: 'ejercicios',
        label: 'Medio corrido',
        drum: 'surdo_medio',
        color: TRACK_COLORS.surdo_medio,
        hits: [
            { beat: 0, stroke: 'nota' }, { beat: 1, stroke: 'nota' }, { beat: 2, stroke: 'nota' },
            { beat: 8, stroke: 'nota' }, { beat: 9, stroke: 'nota' }, { beat: 10, stroke: 'nota' },
        ],
    },
    {
        id: 'redob_8',
        cat: 'tabs',
        label: 'Redoblante pares',
        drum: 'redoblante',
        color: TRACK_COLORS.redoblante,
        hits: [0, 2, 4, 6, 8, 10, 12, 14].map((b) => ({
            beat: b,
            stroke: [0, 4, 8, 12].includes(b) ? 'acentuado' : 'nota',
        })),
    },
    {
        id: 'repique_8',
        cat: 'tabs',
        label: 'Repique pares',
        drum: 'repique',
        color: TRACK_COLORS.repique,
        hits: [0, 2, 4, 6, 8, 10, 12, 14].map((b) => ({
            beat: b,
            stroke: [0, 4, 8, 12].includes(b) ? 'acentuado' : 'nota',
        })),
    },
    {
        id: 'timbal_pairs',
        cat: 'ejercicios',
        label: 'Timbal pares',
        drum: 'timbal',
        color: TRACK_COLORS.timbal,
        hits: [
            { beat: 0, stroke: 'abierto' }, { beat: 1, stroke: 'abierto' },
            { beat: 4, stroke: 'abierto' }, { beat: 5, stroke: 'abierto' },
            { beat: 8, stroke: 'abierto' }, { beat: 9, stroke: 'abierto' },
            { beat: 12, stroke: 'abierto' }, { beat: 13, stroke: 'abierto' },
        ],
    },
    {
        id: 'chapa_call',
        cat: 'ejercicios',
        label: 'Chapa llamada',
        drum: 'repique',
        color: TRACK_COLORS.repique,
        hits: [
            { beat: 0, stroke: 'chapa' }, { beat: 4, stroke: 'nota' },
            { beat: 8, stroke: 'chapa' }, { beat: 12, stroke: 'nota' },
        ],
    },
    {
        id: 'tapado_medio',
        cat: 'ejercicios',
        label: 'Medio tapado',
        drum: 'surdo_medio',
        color: TRACK_COLORS.surdo_medio,
        hits: [
            { beat: 2, stroke: 'tapado' }, { beat: 6, stroke: 'tapado' },
            { beat: 10, stroke: 'tapado' }, { beat: 14, stroke: 'tapado' },
        ],
    },
];

const SCENE_PRESETS = [
    { id: 'blank', label: 'Escena vacía', name: '' },
    { id: 'llamada', label: 'Llamada', name: 'Llamada inicial' },
    { id: 'toque', label: 'Toque / Base', name: 'Toque' },
    { id: 'variacion', label: 'Variación', name: 'Variación' },
    { id: 'corte', label: 'Corte', name: 'Corte' },
    { id: 'intermedia', label: 'Llamada intermedia', name: 'Llamada intermedia' },
    { id: 'final', label: 'Llamada final', name: 'Llamada final' },
];

const STROKE_SHORT = {
    nota: '•', chapa: '×', tapado: '—', acentuado: '>', abierto: '○',
    slap: '⊗', palma: '◇', presionado: '=', dedo: '×', agudo: '△',
};

function activeDrums(state) {
    const opt = state.optionalInstruments || [];
    return [
        ...CHILINGA_DRUMS,
        ...OPTIONAL_DRUMS.filter((d) => opt.includes(d.id)),
    ];
}

function emptyHits(measureCount, drumIds) {
    const hits = {};
    drumIds.forEach((id) => {
        hits[id] = [];
        for (let m = 0; m < measureCount; m++) hits[id][m] = [];
    });
    return hits;
}

function emptySection(name = '', measureCount = 1) {
    return {
        name,
        measureCount,
        repeatX: 1,
        repeats: Array.from({ length: measureCount }, () => ({ begin: false, end: false })),
        hits: emptyHits(measureCount, CHILINGA_DRUMS.map((d) => d.id)),
    };
}

function clipHasContent(section, drumId) {
    return (section.hits[drumId] || []).some((arr) => arr && arr.length > 0);
}

/**
 * Mapa didáctico: filas = tambores, casilleros = golpes en el tiempo.
 * Sin pentagrama / teoría musical.
 */
export function renderDidacticMap(container, rawState, options = {}) {
    const state = normalizeScore(rawState);
    const drums = activeDrums(state);
    container.innerHTML = '';
    container.classList.add('daw-didactic-map');

    if (!state.sections.length) {
        container.innerHTML = `<p class="daw-clip-hint">${options.emptyHint || 'Sin partes en este toque.'}</p>`;
        return;
    }

    state.sections.forEach((sec, si) => {
        const block = document.createElement('div');
        block.className = 'daw-map-scene';
        const title = document.createElement('div');
        title.className = 'daw-map-scene-title';
        title.textContent = `${sec.name || 'Parte ' + (si + 1)}${sec.repeatX > 1 ? ` (×${sec.repeatX})` : ''}`;
        block.appendChild(title);

        drums.forEach((drum) => {
            if (!clipHasContent(sec, drum.id)) return;
            const row = document.createElement('div');
            row.className = 'daw-map-row';
            row.style.setProperty('--clip-color', TRACK_COLORS[drum.id] || '#888');
            const lab = document.createElement('div');
            lab.className = 'daw-map-drum';
            lab.textContent = drum.label;
            row.appendChild(lab);
            const steps = document.createElement('div');
            steps.className = 'daw-map-steps';
            for (let m = 0; m < sec.measureCount; m++) {
                for (let b = 0; b < BEATS; b++) {
                    const hit = (sec.hits[drum.id]?.[m] || []).find((h) => h.beat === b);
                    const cell = document.createElement('span');
                    cell.className = 'daw-map-step' + (b % 4 === 0 ? ' is-downbeat' : '');
                    if (hit) {
                        cell.classList.add('is-on', `stroke-${hit.stroke}`);
                        cell.title = `${drum.label}: ${STROKE_LABELS[hit.stroke] || hit.stroke}`;
                    }
                    steps.appendChild(cell);
                }
            }
            row.appendChild(steps);
            block.appendChild(row);
        });

        if (!block.querySelector('.daw-map-row')) {
            const empty = document.createElement('p');
            empty.className = 'daw-clip-hint';
            empty.textContent = 'Sin golpes en esta parte todavía.';
            block.appendChild(empty);
        }
        container.appendChild(block);
    });
}

export function initDidacticViewers() {
    document.querySelectorAll('[data-didactic-viewer]').forEach((el) => {
        const raw = el.getAttribute('data-partitura-json');
        if (!raw) return;
        try {
            renderDidacticMap(el, JSON.parse(raw), {
                emptyHint: 'Este toque todavía no tiene partes cargadas.',
            });
        } catch (e) {
            el.innerHTML = '<p class="text-muted small mb-0">No se pudo mostrar el mapa del toque.</p>';
        }
    });
}

function clipHitCount(section, drumId) {
    return (section.hits[drumId] || []).reduce((n, arr) => n + (arr?.length || 0), 0);
}

function applyPatternToClip(section, drumId, patternHits) {
    if (!section.hits[drumId]) {
        section.hits[drumId] = Array.from({ length: section.measureCount }, () => []);
    }
    // Aplicar al primer compás; si hay más, repetir
    for (let m = 0; m < section.measureCount; m++) {
        section.hits[drumId][m] = patternHits.map((h) => ({ ...h }));
    }
}

function clearClip(section, drumId) {
    if (!section.hits[drumId]) return;
    for (let m = 0; m < section.measureCount; m++) {
        section.hits[drumId][m] = [];
    }
}

function exportState(state) {
    return JSON.parse(JSON.stringify(state));
}

function demoToqueChilinga() {
    const llamada = emptySection('Llamada inicial y final', 2);
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
    toque.hits.surdo_grave[0] = [{ beat: 0, stroke: 'nota' }, { beat: 8, stroke: 'nota' }];
    toque.hits.surdo_agudo[0] = [{ beat: 4, stroke: 'nota' }, { beat: 12, stroke: 'nota' }];
    toque.hits.surdo_medio[0] = [
        { beat: 0, stroke: 'nota' }, { beat: 1, stroke: 'nota' }, { beat: 2, stroke: 'nota' },
        { beat: 8, stroke: 'nota' }, { beat: 9, stroke: 'nota' }, { beat: 10, stroke: 'nota' },
    ];
    const corcheas = [0, 2, 4, 6, 8, 10, 12, 14];
    const accent = [0, 4, 8, 12];
    toque.hits.redoblante[0] = corcheas.map((b) => ({ beat: b, stroke: accent.includes(b) ? 'acentuado' : 'nota' }));
    toque.hits.repique[0] = corcheas.map((b) => ({ beat: b, stroke: accent.includes(b) ? 'acentuado' : 'nota' }));
    toque.hits.timbal[0] = [
        { beat: 0, stroke: 'abierto' }, { beat: 1, stroke: 'abierto' },
        { beat: 4, stroke: 'abierto' }, { beat: 5, stroke: 'abierto' },
        { beat: 8, stroke: 'abierto' }, { beat: 9, stroke: 'abierto' },
        { beat: 12, stroke: 'abierto' }, { beat: 13, stroke: 'abierto' },
    ];

    const intermedia = emptySection('Llamada intermedia', 1);
    intermedia.repeatX = 4;
    intermedia.hits.repique[0] = [2, 3, 4, 6, 7, 8, 10, 11, 12, 14, 15].map((b) => ({ beat: b, stroke: 'nota' }));
    intermedia.hits.redoblante[0] = [2, 3, 4, 6, 7, 8, 10, 11, 12, 14, 15].map((b) => ({ beat: b, stroke: 'nota' }));
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
 * @param {HTMLElement} root
 */
export function initDawEditor(root) {
    if (!root) return;

    const dataEl = root.querySelector('[data-partitura-initial]');
    const hidden = root.querySelector('[data-partitura-input]');
    const removeCheck = root.querySelector('[data-partitura-remove]');
    const browserEl = root.querySelector('[data-daw-browser]');
    const sessionEl = root.querySelector('[data-daw-session]');
    const clipEl = root.querySelector('[data-daw-clip]');
    const preview = root.querySelector('[data-partitura-preview]');
    const demoBtn = root.querySelector('[data-partitura-demo]');
    const addSceneBtn = root.querySelector('[data-daw-add-scene]');

    let state = normalizeScore(null);
    if (dataEl?.textContent.trim()) {
        try { state = normalizeScore(JSON.parse(dataEl.textContent)); } catch (e) { /* */ }
    }

    /** @type {{ scene: number, track: string } | null} */
    let focus = state.sections.length
        ? { scene: 0, track: CHILINGA_DRUMS[0].id }
        : null;

    /** @type {string} golpe seleccionado para pintar (modo didáctico) */
    let paintStroke = 'nota';

    let browserFilter = 'all';
    let showMusicStaff = false;

    const mapEl = root.querySelector('[data-daw-map]');
    const staffWrap = root.querySelector('[data-daw-staff-wrap]');
    const staffToggle = root.querySelector('[data-daw-toggle-staff]');

    function syncHidden() {
        if (hidden) {
            hidden.value = removeCheck?.checked ? '' : JSON.stringify(exportState(state));
        }
    }

    function refresh() {
        buildBrowser();
        buildSession();
        buildClipEditor();
        buildDidacticMap();
        syncHidden();
        if (preview && showMusicStaff) {
            renderPartituraVexflow(preview, state);
        }
        if (staffWrap) {
            staffWrap.hidden = !showMusicStaff;
        }
        if (staffToggle) {
            staffToggle.setAttribute('aria-pressed', showMusicStaff ? 'true' : 'false');
            staffToggle.textContent = showMusicStaff
                ? 'Ocultar pentagrama (avanzado)'
                : 'Ver pentagrama musical (avanzado)';
        }
    }

    function cycleStroke(drumId, current) {
        const options = [null, ...(STROKES_BY_DRUM[drumId] || ['nota'])];
        const idx = current ? options.findIndex((s) => s === current.stroke) : 0;
        const next = options[(idx + 1) % options.length];
        return next ? { stroke: next } : null;
    }

    function buildBrowser() {
        if (!browserEl) return;
        browserEl.innerHTML = '';

        const tabs = document.createElement('div');
        tabs.className = 'daw-browser-tabs';
        [
            ['all', 'Todo'],
            ['ejercicios', 'Ejercicios'],
            ['tabs', 'Tabs'],
            ['escenas', 'Escenas'],
        ].forEach(([id, label]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'daw-browser-tab' + (browserFilter === id ? ' is-active' : '');
            b.textContent = label;
            b.addEventListener('click', () => {
                browserFilter = id;
                buildBrowser();
            });
            tabs.appendChild(b);
        });
        browserEl.appendChild(tabs);

        const list = document.createElement('div');
        list.className = 'daw-browser-list';

        if (browserFilter === 'all' || browserFilter === 'escenas') {
            const h = document.createElement('div');
            h.className = 'daw-browser-heading';
            h.textContent = 'Escenas';
            list.appendChild(h);
            SCENE_PRESETS.forEach((p) => {
                const item = document.createElement('div');
                item.className = 'daw-browser-item daw-browser-item--scene';
                item.draggable = true;
                item.textContent = p.label;
                item.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('application/x-daw-scene', JSON.stringify(p));
                    e.dataTransfer.effectAllowed = 'copy';
                });
                item.addEventListener('dblclick', () => {
                    state.sections.push(emptySection(p.name || 'Escena', 1));
                    focus = { scene: state.sections.length - 1, track: focus?.track || CHILINGA_DRUMS[0].id };
                    refresh();
                });
                list.appendChild(item);
            });
        }

        if (browserFilter === 'all' || browserFilter === 'ejercicios' || browserFilter === 'tabs') {
            const cats = browserFilter === 'all'
                ? ['ejercicios', 'tabs']
                : [browserFilter];
            cats.forEach((cat) => {
                const h = document.createElement('div');
                h.className = 'daw-browser-heading';
                h.textContent = cat === 'tabs' ? 'Tabs / grooves' : 'Ejercicios';
                list.appendChild(h);
                CLIP_LIBRARY.filter((c) => c.cat === cat).forEach((clip) => {
                    const item = document.createElement('div');
                    item.className = 'daw-browser-item daw-browser-item--clip';
                    item.draggable = true;
                    item.style.setProperty('--clip-color', clip.color);
                    item.innerHTML = `<span class="daw-clip-swatch"></span><span>${clip.label}</span>`;
                    item.title = `Arrastrá a un clip de ${clip.drum.replace('_', ' ')}`;
                    item.addEventListener('dragstart', (e) => {
                        e.dataTransfer.setData('application/x-daw-clip', JSON.stringify(clip));
                        e.dataTransfer.effectAllowed = 'copy';
                    });
                    item.addEventListener('dblclick', () => {
                        if (!focus) return;
                        const sec = state.sections[focus.scene];
                        if (!sec) return;
                        // Prefer clip's drum track
                        applyPatternToClip(sec, clip.drum, clip.hits);
                        focus.track = clip.drum;
                        if (!state.optionalInstruments.includes(clip.drum)
                            && OPTIONAL_DRUMS.some((d) => d.id === clip.drum)) {
                            state.optionalInstruments.push(clip.drum);
                        }
                        refresh();
                    });
                    list.appendChild(item);
                });
            });
        }

        browserEl.appendChild(list);
    }

    function buildSession() {
        if (!sessionEl) return;
        sessionEl.innerHTML = '';
        const drums = activeDrums(state);

        const wrap = document.createElement('div');
        wrap.className = 'daw-session-scroll';

        const table = document.createElement('div');
        table.className = 'daw-session-grid';
        table.style.setProperty('--scene-count', String(Math.max(1, state.sections.length)));

        // Header row: scene names
        const corner = document.createElement('div');
        corner.className = 'daw-session-corner';
        corner.textContent = 'Tambores';
        table.appendChild(corner);

        state.sections.forEach((sec, si) => {
            const head = document.createElement('div');
            head.className = 'daw-scene-head' + (focus?.scene === si ? ' is-active' : '');
            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.className = 'daw-scene-name';
            nameInput.value = sec.name || `Escena ${si + 1}`;
            nameInput.addEventListener('change', () => {
                sec.name = nameInput.value;
                syncHidden();
            });
            nameInput.addEventListener('click', () => {
                focus = { scene: si, track: focus?.track || drums[0].id };
                refresh();
            });
            const meta = document.createElement('div');
            meta.className = 'daw-scene-meta';
            meta.innerHTML = `
                <select data-scene-bars="${si}" aria-label="Compases">
                    ${[1, 2, 3, 4].map((n) => `<option value="${n}" ${n === sec.measureCount ? 'selected' : ''}>${n}c</option>`).join('')}
                </select>
                <select data-scene-rep="${si}" aria-label="Repeticiones">
                    ${[1, 2, 3, 4, 8].map((n) => `<option value="${n}" ${n === sec.repeatX ? 'selected' : ''}>×${n}</option>`).join('')}
                </select>
            `;
            meta.querySelector('[data-scene-bars]')?.addEventListener('change', (e) => {
                const mc = parseInt(e.target.value, 10);
                const next = emptyHits(mc, drums.map((d) => d.id));
                drums.forEach((d) => {
                    for (let m = 0; m < mc; m++) {
                        next[d.id][m] = sec.hits[d.id]?.[m]?.map((h) => ({ ...h })) || [];
                    }
                });
                sec.measureCount = mc;
                sec.hits = next;
                sec.repeats = Array.from({ length: mc }, (_, m) => sec.repeats[m] || { begin: false, end: false });
                refresh();
            });
            meta.querySelector('[data-scene-rep]')?.addEventListener('change', (e) => {
                sec.repeatX = parseInt(e.target.value, 10);
                syncHidden();
            });
            if (state.sections.length > 1) {
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'daw-scene-del';
                del.title = 'Quitar escena';
                del.textContent = '×';
                del.addEventListener('click', () => {
                    state.sections.splice(si, 1);
                    focus = { scene: Math.min(si, state.sections.length - 1), track: focus?.track || drums[0].id };
                    refresh();
                });
                meta.appendChild(del);
            }
            head.appendChild(nameInput);
            head.appendChild(meta);
            head.addEventListener('dragover', (e) => {
                if (e.dataTransfer.types.includes('application/x-daw-scene')) {
                    e.preventDefault();
                    head.classList.add('is-drop');
                }
            });
            head.addEventListener('dragleave', () => head.classList.remove('is-drop'));
            head.addEventListener('drop', (e) => {
                e.preventDefault();
                head.classList.remove('is-drop');
                try {
                    const p = JSON.parse(e.dataTransfer.getData('application/x-daw-scene'));
                    if (p?.name !== undefined) sec.name = p.name || sec.name;
                    refresh();
                } catch (err) { /* */ }
            });
            table.appendChild(head);
        });

        drums.forEach((drum) => {
            const trackHead = document.createElement('div');
            trackHead.className = 'daw-track-head' + (focus?.track === drum.id ? ' is-active' : '');
            trackHead.style.setProperty('--track-color', TRACK_COLORS[drum.id] || '#888');
            trackHead.innerHTML = `<span class="daw-track-dot"></span><span>${drum.label}</span>`;
            trackHead.addEventListener('click', () => {
                focus = { scene: focus?.scene ?? 0, track: drum.id };
                refresh();
            });
            table.appendChild(trackHead);

            state.sections.forEach((sec, si) => {
                const slot = document.createElement('button');
                slot.type = 'button';
                const filled = clipHasContent(sec, drum.id);
                slot.className = 'daw-clip-slot'
                    + (filled ? ' is-filled' : '')
                    + (focus?.scene === si && focus?.track === drum.id ? ' is-selected' : '');
                slot.style.setProperty('--clip-color', TRACK_COLORS[drum.id] || '#888');
                if (filled) {
                    const n = clipHitCount(sec, drum.id);
                    slot.innerHTML = `<span class="daw-clip-label">${n} golpes</span>`;
                } else {
                    slot.innerHTML = '<span class="daw-clip-empty">+</span>';
                }
                slot.addEventListener('click', () => {
                    focus = { scene: si, track: drum.id };
                    refresh();
                });
                slot.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    clearClip(sec, drum.id);
                    focus = { scene: si, track: drum.id };
                    refresh();
                });
                slot.addEventListener('dragover', (e) => {
                    if (e.dataTransfer.types.includes('application/x-daw-clip')) {
                        e.preventDefault();
                        slot.classList.add('is-drop');
                    }
                });
                slot.addEventListener('dragleave', () => slot.classList.remove('is-drop'));
                slot.addEventListener('drop', (e) => {
                    e.preventDefault();
                    slot.classList.remove('is-drop');
                    try {
                        const clip = JSON.parse(e.dataTransfer.getData('application/x-daw-clip'));
                        if (!clip?.hits) return;
                        // Drop on slot: apply to this track (or clip's preferred drum if empty match)
                        const targetDrum = drum.id;
                        applyPatternToClip(sec, targetDrum, clip.hits);
                        focus = { scene: si, track: targetDrum };
                        refresh();
                    } catch (err) { /* */ }
                });
                table.appendChild(slot);
            });
        });

        wrap.appendChild(table);
        sessionEl.appendChild(wrap);

        // Optional instruments strip
        const opts = document.createElement('div');
        opts.className = 'daw-optional-strip';
        opts.innerHTML = '<span class="daw-optional-label">Tambores opcionales</span>';
        OPTIONAL_DRUMS.forEach((d) => {
            const lbl = document.createElement('label');
            lbl.className = 'daw-optional-check';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = state.optionalInstruments.includes(d.id);
            cb.addEventListener('change', () => {
                if (cb.checked) {
                    if (!state.optionalInstruments.includes(d.id)) state.optionalInstruments.push(d.id);
                    state.sections.forEach((sec) => {
                        if (!sec.hits[d.id]) {
                            sec.hits[d.id] = Array.from({ length: sec.measureCount }, () => []);
                        }
                    });
                } else {
                    state.optionalInstruments = state.optionalInstruments.filter((x) => x !== d.id);
                }
                refresh();
            });
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(d.label));
            opts.appendChild(lbl);
        });
        sessionEl.appendChild(opts);
    }

    function buildClipEditor() {
        if (!clipEl) return;
        clipEl.innerHTML = '';
        if (!focus || !state.sections[focus.scene]) {
            clipEl.innerHTML = '<p class="daw-clip-hint">Tocá un cuadrito de la grilla de arriba para editar ese tambor en esa parte del toque.</p>';
            return;
        }

        const sec = state.sections[focus.scene];
        const drum = activeDrums(state).find((d) => d.id === focus.track)
            || CHILINGA_DRUMS.find((d) => d.id === focus.track);
        if (!drum) return;

        if (!sec.hits[drum.id]) {
            sec.hits[drum.id] = Array.from({ length: sec.measureCount }, () => []);
        }

        const strokes = STROKES_BY_DRUM[drum.id] || ['nota'];
        if (!strokes.includes(paintStroke)) {
            paintStroke = strokes[0];
        }

        const head = document.createElement('div');
        head.className = 'daw-clip-editor-head';
        head.innerHTML = `
            <div>
                <div class="daw-clip-editor-title" style="--clip-color:${TRACK_COLORS[drum.id] || '#888'}">
                    <span class="daw-track-dot"></span>
                    ${drum.label} · ${sec.name || 'Parte ' + (focus.scene + 1)}
                </div>
                <div class="daw-clip-editor-sub">1) Elegí el tipo de golpe · 2) Tocá los casilleros del tiempo · Tocá de nuevo para borrar</div>
            </div>
            <div class="daw-clip-editor-actions">
                <button type="button" class="daw-btn" data-clear-clip>Borrar todo</button>
            </div>
        `;
        head.querySelector('[data-clear-clip]')?.addEventListener('click', () => {
            clearClip(sec, drum.id);
            refresh();
        });
        clipEl.appendChild(head);

        // Paleta didáctica (nombres en español, sin teoría musical)
        const palette = document.createElement('div');
        palette.className = 'daw-paint-palette';
        const paletteLabel = document.createElement('div');
        paletteLabel.className = 'daw-paint-label';
        paletteLabel.textContent = 'Tipo de golpe';
        palette.appendChild(paletteLabel);
        const paletteRow = document.createElement('div');
        paletteRow.className = 'daw-paint-row';
        strokes.forEach((s) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'daw-paint-btn' + (paintStroke === s ? ' is-active' : '') + ` stroke-${s}`;
            b.textContent = STROKE_LABELS[s] || s;
            b.addEventListener('click', () => {
                paintStroke = s;
                refresh();
            });
            paletteRow.appendChild(b);
        });
        const erase = document.createElement('button');
        erase.type = 'button';
        erase.className = 'daw-paint-btn daw-paint-btn--erase' + (paintStroke === '__erase' ? ' is-active' : '');
        erase.textContent = 'Borrar casillero';
        erase.addEventListener('click', () => {
            paintStroke = '__erase';
            refresh();
        });
        paletteRow.appendChild(erase);
        palette.appendChild(paletteRow);
        clipEl.appendChild(palette);

        const guide = document.createElement('div');
        guide.className = 'daw-time-guide';
        guide.innerHTML = `
            <span class="daw-time-guide-spacer"></span>
            <div class="daw-time-guide-beats">
                ${[1, 2, 3, 4].map((t) => `<span>Tiempo ${t}</span>`).join('')}
            </div>
        `;
        clipEl.appendChild(guide);

        const grid = document.createElement('div');
        grid.className = 'daw-step-grid';
        for (let m = 0; m < sec.measureCount; m++) {
            const bar = document.createElement('div');
            bar.className = 'daw-step-bar';
            const label = document.createElement('div');
            label.className = 'daw-step-bar-label';
            label.textContent = sec.measureCount > 1 ? `Vuelta ${m + 1}` : 'Pasos';
            bar.appendChild(label);
            const cells = document.createElement('div');
            cells.className = 'daw-step-cells';
            for (let b = 0; b < BEATS; b++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                const beatInBar = (b % 4) + 1;
                const isDown = b % 4 === 0;
                btn.className = 'daw-step-cell'
                    + (isDown ? ' is-downbeat' : '')
                    + ` beat-${beatInBar}`;
                const list = sec.hits[drum.id][m];
                const hit = list.find((h) => h.beat === b) || null;
                if (hit) {
                    btn.classList.add('is-on', `stroke-${hit.stroke}`);
                    btn.innerHTML = `<span class="daw-step-name">${STROKE_LABELS[hit.stroke] || hit.stroke}</span>`;
                }
                btn.title = isDown
                    ? `Tiempo ${Math.floor(b / 4) + 1}`
                    : `Entre tiempo ${Math.floor(b / 4) + 1}`;
                btn.addEventListener('click', () => {
                    const idx = list.findIndex((h) => h.beat === b);
                    if (paintStroke === '__erase') {
                        if (idx >= 0) list.splice(idx, 1);
                    } else if (idx >= 0 && list[idx].stroke === paintStroke) {
                        list.splice(idx, 1);
                    } else {
                        if (idx >= 0) list.splice(idx, 1);
                        list.push({ beat: b, stroke: paintStroke });
                        list.sort((a, c) => a.beat - c.beat);
                    }
                    refresh();
                });
                cells.appendChild(btn);
            }
            bar.appendChild(cells);
            grid.appendChild(bar);
        }
        clipEl.appendChild(grid);
    }

    function buildDidacticMap() {
        if (!mapEl) return;
        renderDidacticMap(mapEl, state, { emptyHint: 'Agregá una parte del toque para ver el mapa.' });
    }

    if (staffToggle) {
        staffToggle.addEventListener('click', () => {
            showMusicStaff = !showMusicStaff;
            refresh();
        });
    }

    if (addSceneBtn) {
        addSceneBtn.addEventListener('click', () => {
            state.sections.push(emptySection('', 1));
            focus = { scene: state.sections.length - 1, track: focus?.track || CHILINGA_DRUMS[0].id };
            refresh();
        });
    }

    if (demoBtn) {
        demoBtn.addEventListener('click', () => {
            state = demoToqueChilinga();
            focus = { scene: 0, track: 'surdo_grave' };
            refresh();
        });
    }

    if (removeCheck) removeCheck.addEventListener('change', syncHidden);
    const form = root.closest('form');
    if (form) form.addEventListener('submit', syncHidden);

    refresh();
}
