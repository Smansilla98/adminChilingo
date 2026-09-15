import '../css/diseno-canvas.css';
import {
    Canvas,
    Rect,
    Circle,
    Ellipse,
    Textbox,
    Line,
    Path,
    Group,
    ActiveSelection,
    Image as FabricImage,
    filters,
} from 'fabric';
import {
    BRAND_COLORS,
    CANVAS_PRESETS,
    CONTENT_TEMPLATES,
    FONT_FAMILIES,
    contenidoPlantilla,
    resolveBrandAssets,
    ensureEditorFonts,
    weightsForFont,
    MOCKUP_HOODIE_URL,
} from './diseno/presets.js';
import { exportDesign, openExportDialog } from './diseno/export.js';
import { snapObject, clearGuides } from './diseno/snap.js';
import { initBibliotecaPanel, bindBibliotecaDrop } from './diseno/biblioteca.js';

const BG = '#ffffff';
const MAX_HISTORY = 60;
const HISTORY_DEBOUNCE_MS = 300;
const BLEND_MODES = [
    { id: 'source-over', label: 'Normal' },
    { id: 'multiply', label: 'Multiply' },
    { id: 'screen', label: 'Screen' },
    { id: 'overlay', label: 'Overlay' },
    { id: 'darken', label: 'Darken' },
    { id: 'lighten', label: 'Lighten' },
];
const JSON_PROPS = ['name', 'excludeFromExport', '_mockupOverlay', '_layerFolder', 'globalCompositeOperation', 'selectable', 'evented', 'lockMovementX', 'lockMovementY', 'lockRotation', 'lockScalingX', 'lockScalingY', 'src'];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function readServerJson(id, fallback = null) {
    const el = document.getElementById(id);
    if (!el?.textContent?.trim()) return fallback;
    try { return JSON.parse(el.textContent); } catch { return fallback; }
}

function initDisenoEditor() {
    const root = document.getElementById('disenoApp');
    const form = document.getElementById('disenoForm');
    const canvasEl = document.getElementById('designCanvas');
    if (!root || !form || !canvasEl) return;

    let activeColor = BRAND_COLORS[0].hex;
    let userZoom = 100;
    let fitScale = 1;
    let template = {
        w: parseInt(root.dataset.ancho, 10) || 1080,
        h: parseInt(root.dataset.alto, 10) || 1350,
        formato: root.dataset.formato || 'flyer_feed',
    };
    const uploadUrl = root.dataset.uploadUrl || '';
    const bibliotecaApiUrl = root.dataset.bibliotecaApi || '';
    const kitUploadUrl = root.dataset.kitUploadUrl || '';
    const kitDestroyBase = (root.dataset.kitDestroyUrl || '/disenos/kit').replace(/\/$/, '');
    const canManageKit = root.dataset.canManageKit === '1';
    const brandAssets = resolveBrandAssets(readServerJson('disenoBrandAssets', null));
    const mockupUrl = brandAssets.find((a) => a.kind === 'mockup')?.url || MOCKUP_HOODIE_URL;

    const history = [];
    const historyLabels = [];
    let historyStep = -1;
    let historyLock = false;
    let historyTimer = null;

    const canvas = new Canvas(canvasEl, {
        width: template.w,
        height: template.h,
        backgroundColor: BG,
        preserveObjectStacking: true,
        selection: true,
    });

    function effectiveZoom() {
        return fitScale * (userZoom / 100);
    }

    function applyZoom() {
        const z = Math.max(0.05, effectiveZoom());
        canvas.setDimensions({ width: template.w * z, height: template.h * z });
        canvas.setZoom(z);
        canvas.requestRenderAll();
        const label = document.getElementById('disenoZoomLabel');
        if (label) label.textContent = `${userZoom}%`;
        const range = document.getElementById('disenoZoomRange');
        if (range) range.value = String(userZoom);
    }

    function computeFitScale() {
        const stage = document.getElementById('disenoStageInner');
        if (!stage) return 1;
        const pad = 64;
        const maxW = stage.clientWidth - pad;
        const maxH = stage.clientHeight - pad;
        if (maxW < 40 || maxH < 40) return fitScale > 0 ? fitScale : 0.25;
        return Math.min(maxW / template.w, maxH / template.h, 1);
    }

    function fitCanvas() {
        fitScale = computeFitScale();
        applyZoom();
    }

    function updateDocSize() {
        const el = document.getElementById('disenoDocSize');
        if (el) el.textContent = `${template.w}×${template.h}`;
    }

    function canvasJson() {
        return canvas.toJSON(JSON_PROPS);
    }

    function syncHidden() {
        document.getElementById('disenoFormato').value = template.formato;
        document.getElementById('disenoAncho').value = String(template.w);
        document.getElementById('disenoAlto').value = String(template.h);
        document.getElementById('disenoCanvasJson').value = JSON.stringify(canvasJson());
        // Preview liviano (no full-res) para no inflar el POST
        const cw = canvas.getWidth() || 1;
        const previewW = Math.min(template.w, 720);
        const mult = previewW / cw;
        document.getElementById('disenoPreviewBase64').value = canvas.toDataURL({
            format: 'jpeg',
            quality: 0.72,
            multiplier: mult > 0 ? mult : 1,
            enableRetinaScaling: false,
        });
    }

    function pushHistory(label = 'Edición') {
        if (historyLock) return;
        const snap = JSON.stringify(canvasJson());
        if (history[historyStep] === snap) return;
        historyStep++;
        history.splice(historyStep);
        historyLabels.splice(historyStep);
        history.push(snap);
        historyLabels.push(label);
        while (history.length > MAX_HISTORY) {
            history.shift();
            historyLabels.shift();
            historyStep--;
        }
        refreshHistoryPanel();
    }

    function scheduleHistory(label = 'Edición') {
        clearTimeout(historyTimer);
        historyTimer = setTimeout(() => pushHistory(label), HISTORY_DEBOUNCE_MS);
    }

    function refreshHistoryPanel() {
        const list = document.getElementById('disenoHistoryList');
        if (!list) return;
        if (!history.length) {
            list.innerHTML = '<p class="diseno-hint">Sin pasos aún.</p>';
            return;
        }
        list.innerHTML = history.map((_, i) => {
            const cur = i === historyStep ? 'current' : '';
            const label = escapeHtml(historyLabels[i] || `Paso ${i + 1}`);
            return `<button type="button" class="diseno-history-item ${cur}" data-step="${i}">
                <span class="diseno-history-n">${i + 1}</span>
                <span>${label}</span>
            </button>`;
        }).reverse().join('');
        list.querySelectorAll('[data-step]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const step = parseInt(btn.dataset.step, 10);
                if (Number.isNaN(step) || step === historyStep) return;
                historyStep = step;
                loadHistory(step);
                refreshHistoryPanel();
            });
        });
    }

    function loadHistory(step) {
        if (step < 0 || step >= history.length) return;
        historyLock = true;
        canvas.loadFromJSON(history[step])
            .then(() => {
                canvas.requestRenderAll();
                refreshProps();
                syncHidden();
                refreshHistoryPanel();
            })
            .catch((err) => console.error('ITO Diseño: no se pudo restaurar historial', err))
            .finally(() => { historyLock = false; });
    }

    function undo() {
        if (historyStep <= 0) return;
        historyStep--;
        loadHistory(historyStep);
    }

    function redo() {
        if (historyStep >= history.length - 1) return;
        historyStep++;
        loadHistory(historyStep);
    }

    function layerIcon(obj) {
        if (isGroupObj(obj)) return 'bi-folder2';
        if (obj.type === 'textbox' || obj.type === 'text') return 'bi-type';
        if (obj.type === 'image') return 'bi-image';
        if (obj.type === 'circle' || obj.type === 'ellipse') return 'bi-circle';
        if (obj.type === 'line') return 'bi-dash-lg';
        if (obj.type === 'path') return 'bi-hexagon';
        return 'bi-square';
    }

    function layerLabel(obj) {
        if (obj.name) return String(obj.name).slice(0, 28);
        if (isGroupObj(obj)) return 'Grupo';
        if (obj.type === 'textbox' || obj.type === 'text') return (obj.text || 'Texto').slice(0, 24);
        if (obj.type === 'image') return 'Imagen';
        if (obj.type === 'circle') return 'Círculo';
        if (obj.type === 'ellipse') return 'Elipse';
        if (obj.type === 'line') return 'Línea';
        return 'Rectángulo';
    }

    function isLocked(obj) {
        return !!(obj.lockMovementX && obj.lockMovementY);
    }

    function setLocked(obj, locked) {
        obj.set({
            lockMovementX: locked,
            lockMovementY: locked,
            lockRotation: locked,
            lockScalingX: locked,
            lockScalingY: locked,
            selectable: true,
            evented: true,
            hasControls: !locked,
        });
    }

    function renameLayer(obj) {
        const next = window.prompt('Nombre de capa', layerLabel(obj));
        if (next == null) return;
        const name = next.trim().slice(0, 60);
        if (!name) return;
        obj.set('name', name);
        pushHistory('Renombrar capa');
        refreshLayers();
        syncHidden();
    }

    function reorderLayer(fromIdx, toIdx) {
        const objs = canvas.getObjects();
        if (fromIdx < 0 || toIdx < 0 || fromIdx >= objs.length || toIdx >= objs.length) return;
        const obj = objs[fromIdx];
        if (typeof canvas.moveObjectTo === 'function') {
            canvas.moveObjectTo(obj, toIdx);
        } else {
            canvas.remove(obj);
            canvas.insertAt(toIdx, obj);
        }
        canvas.requestRenderAll();
        pushHistory('Reordenar capas');
        refreshLayers();
        syncHidden();
    }

    function isMultiSelect(obj) {
        if (!obj) return false;
        const t = String(obj.type || '').toLowerCase();
        return t === 'activeselection' || typeof obj.multiSelectAdd === 'function';
    }

    function isGroupObj(obj) {
        if (!obj) return false;
        const t = String(obj.type || '').toLowerCase();
        return t === 'group';
    }

    function groupSelection() {
        const active = canvas.getActiveObject();
        if (!active || !isMultiSelect(active)) {
            alert('Seleccioná varios elementos (Shift+clic) para agrupar.');
            return;
        }
        const objects = (active.getObjects?.() || []).slice();
        if (objects.length < 2) {
            alert('Seleccioná al menos 2 elementos para agrupar.');
            return;
        }
        // Fabric 6: ActiveSelection.removeAll() + new Group (ya no existe toGroup)
        if (typeof active.removeAll === 'function') {
            active.removeAll();
        }
        canvas.discardActiveObject();
        objects.forEach((o) => canvas.remove(o));
        const group = new Group(objects);
        group.set({ name: 'Grupo', _layerFolder: true, subTargetCheck: true });
        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
        pushHistory('Agrupar');
        refreshProps();
    }

    function ungroupSelection() {
        const active = canvas.getActiveObject();
        if (!active || !isGroupObj(active)) return;
        const objects = typeof active.removeAll === 'function'
            ? active.removeAll()
            : (active.getObjects?.() || []).slice();
        canvas.remove(active);
        objects.forEach((o) => canvas.add(o));
        if (objects.length > 1) {
            const sel = new ActiveSelection(objects, { canvas });
            canvas.setActiveObject(sel);
        } else if (objects[0]) {
            canvas.setActiveObject(objects[0]);
        }
        canvas.requestRenderAll();
        pushHistory('Desagrupar');
        refreshProps();
    }

    function refreshLayers() {
        const list = document.getElementById('disenoLayerList');
        if (!list) return;
        const objs = canvas.getObjects().slice().reverse();
        const active = canvas.getActiveObject();
        if (!objs.length) {
            list.innerHTML = '<p class="diseno-hint">Sin capas todavía.</p>';
            return;
        }
        list.innerHTML = objs.map((o) => {
            const idx = canvas.getObjects().indexOf(o);
            const sel = o === active || (isMultiSelect(active) && active.getObjects?.().includes(o)) ? 'selected' : '';
            const mock = (o._mockupOverlay || o.excludeFromExport)
                ? '<span class="diseno-layer-badge">mockup</span>'
                : '';
            const folder = (isGroupObj(o) || o._layerFolder)
                ? '<span class="diseno-layer-badge">grupo</span>'
                : '';
            const eye = o.visible === false ? 'bi-eye-slash' : 'bi-eye';
            const lock = isLocked(o) ? 'bi-lock-fill' : 'bi-unlock';
            return `<div class="diseno-layer-item ${sel}" data-idx="${idx}" draggable="true">
                <button type="button" class="diseno-layer-vis" data-idx="${idx}" title="Visibilidad" aria-label="Visibilidad">
                    <i class="bi ${eye}"></i>
                </button>
                <button type="button" class="diseno-layer-lock" data-idx="${idx}" title="Bloquear" aria-label="Bloquear">
                    <i class="bi ${lock}"></i>
                </button>
                <button type="button" class="diseno-layer-select" data-idx="${idx}">
                    <i class="bi ${layerIcon(o)}"></i>
                    <span class="diseno-layer-name">${escapeHtml(layerLabel(o))}</span>
                    ${mock}${folder}
                </button>
                <button type="button" class="diseno-layer-up" data-idx="${idx}" title="Adelante" aria-label="Adelante">▲</button>
                <button type="button" class="diseno-layer-down" data-idx="${idx}" title="Atrás" aria-label="Atrás">▼</button>
            </div>`;
        }).join('');

        list.querySelectorAll('.diseno-layer-select').forEach((el) => {
            el.addEventListener('click', () => {
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (!obj) return;
                canvas.setActiveObject(obj);
                canvas.requestRenderAll();
                refreshProps();
            });
            el.addEventListener('dblclick', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (obj) renameLayer(obj);
            });
        });
        list.querySelectorAll('.diseno-layer-vis').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (!obj) return;
                obj.visible = obj.visible === false;
                canvas.requestRenderAll();
                pushHistory('Visibilidad');
                refreshLayers();
                syncHidden();
            });
        });
        list.querySelectorAll('.diseno-layer-lock').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (!obj) return;
                setLocked(obj, !isLocked(obj));
                canvas.requestRenderAll();
                pushHistory('Bloqueo capa');
                refreshLayers();
            });
        });
        list.querySelectorAll('.diseno-layer-up').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (!obj) return;
                canvas.bringObjectForward(obj);
                canvas.requestRenderAll();
                pushHistory('Capa adelante');
                refreshLayers();
            });
        });
        list.querySelectorAll('.diseno-layer-down').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (!obj) return;
                canvas.sendObjectBackwards(obj);
                canvas.requestRenderAll();
                pushHistory('Capa atrás');
                refreshLayers();
            });
        });

        let dragFrom = null;
        list.querySelectorAll('.diseno-layer-item').forEach((row) => {
            row.addEventListener('dragstart', (e) => {
                dragFrom = parseInt(row.dataset.idx, 10);
                e.dataTransfer.effectAllowed = 'move';
                row.classList.add('dragging');
            });
            row.addEventListener('dragend', () => row.classList.remove('dragging'));
            row.addEventListener('dragover', (e) => {
                e.preventDefault();
                row.classList.add('drag-over');
            });
            row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
            row.addEventListener('drop', (e) => {
                e.preventDefault();
                row.classList.remove('drag-over');
                const toIdx = parseInt(row.dataset.idx, 10);
                if (dragFrom == null || Number.isNaN(toIdx) || dragFrom === toIdx) return;
                reorderLayer(dragFrom, toIdx);
                dragFrom = null;
            });
        });
    }

    function getFilterValue(obj, type, key, fallback = 0) {
        const f = (obj.filters || []).find((x) => x && x.type === type);
        return f ? (f[key] ?? fallback) : fallback;
    }

    function setImageFilters(obj, values) {
        if (obj.type !== 'image') return;
        const list = [
            new filters.Brightness({ brightness: values.brightness }),
            new filters.Contrast({ contrast: values.contrast }),
            new filters.Saturation({ saturation: values.saturation }),
            new filters.Blur({ blur: values.blur }),
        ];
        if (values.hue != null && Math.abs(values.hue) > 0.001 && filters.HueRotation) {
            list.push(new filters.HueRotation({ rotation: values.hue }));
        }
        if (values.noise != null && values.noise > 0.001 && filters.Noise) {
            list.push(new filters.Noise({ noise: values.noise }));
        }
        obj.filters = list;
        obj.applyFilters();
    }

    function applyClip(obj, kind) {
        if (kind === 'none') {
            obj.clipPath = undefined;
            obj.setCoords();
            canvas.requestRenderAll();
            scheduleHistory('Quitar máscara');
            return;
        }
        const w = obj.getScaledWidth?.() || obj.width || 100;
        const h = obj.getScaledHeight?.() || obj.height || 100;
        if (kind === 'circle') {
            const r = Math.min(w, h) / 2;
            obj.clipPath = new Circle({
                radius: r,
                originX: 'center',
                originY: 'center',
            });
        } else if (kind === 'ellipse') {
            obj.clipPath = new Ellipse({
                rx: w / 2,
                ry: h / 2,
                originX: 'center',
                originY: 'center',
            });
        } else if (kind === 'rounded') {
            obj.clipPath = new Rect({
                width: w,
                height: h,
                rx: Math.min(w, h) * 0.12,
                ry: Math.min(w, h) * 0.12,
                originX: 'center',
                originY: 'center',
            });
        } else if (kind === 'diamond') {
            const hw = w / 2;
            const hh = h / 2;
            obj.clipPath = new Path(`M 0 ${-hh} L ${hw} 0 L 0 ${hh} L ${-hw} 0 Z`, {
                originX: 'center',
                originY: 'center',
            });
        } else {
            obj.clipPath = new Rect({
                width: w,
                height: h,
                originX: 'center',
                originY: 'center',
            });
        }
        obj.setCoords();
        canvas.requestRenderAll();
        scheduleHistory('Máscara');
    }

    function refreshProps() {
        refreshLayers();
        const panel = document.getElementById('disenoPropsPanel');
        if (!panel) return;
        const obj = canvas.getActiveObject();
        if (!obj) {
            panel.innerHTML = '<h3 class="diseno-drawer-title">Propiedades</h3><p class="diseno-hint">Seleccioná un elemento del lienzo.</p>';
            return;
        }

        const isText = obj.type === 'textbox' || obj.type === 'text';
        const isImage = obj.type === 'image';
        const fill = typeof obj.fill === 'string' ? obj.fill : activeColor;
        const opacity = Math.round((obj.opacity ?? 1) * 100);
        const angle = Math.round(obj.angle ?? 0);
        const blend = obj.globalCompositeOperation || 'source-over';
        const fontOpts = FONT_FAMILIES.map((f) =>
            `<option value="${escapeAttr(f.id)}" ${(obj.fontFamily || '') === f.id ? 'selected' : ''}>${escapeHtml(f.label)}</option>`
        ).join('');
        const weightOpts = weightsForFont(obj.fontFamily || FONT_FAMILIES[0].id).map((w) => {
            const labels = { 400: 'Regular', 500: 'Medium', 600: 'Semibold', 700: 'Bold', 800: 'Extra bold' };
            const sel = String(obj.fontWeight) === w || (w === '400' && obj.fontWeight === 'normal') || (w === '700' && obj.fontWeight === 'bold');
            return `<option value="${w}" ${sel ? 'selected' : ''}>${labels[w] || w}</option>`;
        }).join('');
        const blendOpts = BLEND_MODES.map((m) =>
            `<option value="${m.id}" ${blend === m.id ? 'selected' : ''}>${m.label}</option>`
        ).join('');

        const bright = getFilterValue(obj, 'Brightness', 'brightness', 0);
        const contrast = getFilterValue(obj, 'Contrast', 'contrast', 0);
        const sat = getFilterValue(obj, 'Saturation', 'saturation', 0);
        const blur = getFilterValue(obj, 'Blur', 'blur', 0);
        const hue = getFilterValue(obj, 'HueRotation', 'rotation', 0);
        const noise = getFilterValue(obj, 'Noise', 'noise', 0);

        panel.innerHTML = `
            <h3 class="diseno-drawer-title">Propiedades</h3>
            ${isText ? `
                <label class="diseno-field"><span>Texto</span><input type="text" id="propText" value="${escapeAttr(obj.text || '')}"></label>
                <label class="diseno-field"><span>Fuente</span>
                    <select id="propFontFamily">${fontOpts}</select>
                </label>
                <label class="diseno-field"><span>Peso</span>
                    <select id="propFontWeight">${weightOpts}</select>
                </label>
                <label class="diseno-field"><span>Alineación</span>
                    <select id="propTextAlign">
                        <option value="left" ${obj.textAlign === 'left' ? 'selected' : ''}>Izquierda</option>
                        <option value="center" ${obj.textAlign === 'center' ? 'selected' : ''}>Centro</option>
                        <option value="right" ${obj.textAlign === 'right' ? 'selected' : ''}>Derecha</option>
                        <option value="justify" ${obj.textAlign === 'justify' ? 'selected' : ''}>Justificado</option>
                    </select>
                </label>
                <label class="diseno-field"><span>Tamaño fuente</span><input type="number" id="propSize" value="${Math.round(obj.fontSize || 24)}"></label>
            ` : ''}
            <div class="diseno-field-row">
                <label class="diseno-field"><span>X</span><input type="number" id="propX" value="${Math.round(obj.left || 0)}"></label>
                <label class="diseno-field"><span>Y</span><input type="number" id="propY" value="${Math.round(obj.top || 0)}"></label>
            </div>
            <div class="diseno-field-row">
                <label class="diseno-field"><span>Ancho</span><input type="number" id="propW" value="${Math.round(obj.getScaledWidth?.() || obj.width || 0)}"></label>
                <label class="diseno-field"><span>Alto</span><input type="number" id="propH" value="${Math.round(obj.getScaledHeight?.() || obj.height || (obj.radius ? obj.radius * 2 : 0) || 0)}"></label>
            </div>
            <label class="diseno-field"><span>Color relleno</span><input type="color" id="propFill" value="${toHex(fill)}"></label>
            <label class="diseno-field"><span>Opacidad (${opacity}%)</span><input type="range" id="propOpacity" min="0" max="100" value="${opacity}"></label>
            <label class="diseno-field"><span>Rotación (${angle}°)</span><input type="range" id="propAngle" min="0" max="360" value="${angle}"></label>
            <label class="diseno-field"><span>Modo de fusión</span>
                <select id="propBlend">${blendOpts}</select>
            </label>
            ${isImage ? `
                <h4 class="diseno-drawer-subtitle">Ajustes no destructivos</h4>
                <label class="diseno-field"><span>Brillo</span><input type="range" id="propBright" min="-100" max="100" value="${Math.round(bright * 100)}"></label>
                <label class="diseno-field"><span>Contraste</span><input type="range" id="propContrast" min="-100" max="100" value="${Math.round(contrast * 100)}"></label>
                <label class="diseno-field"><span>Saturación</span><input type="range" id="propSat" min="-100" max="100" value="${Math.round(sat * 100)}"></label>
                <label class="diseno-field"><span>Matiz</span><input type="range" id="propHue" min="-100" max="100" value="${Math.round(hue * 100)}"></label>
                <label class="diseno-field"><span>Ruido</span><input type="range" id="propNoise" min="0" max="100" value="${Math.round(noise * 100)}"></label>
                <label class="diseno-field"><span>Desenfoque</span><input type="range" id="propBlur" min="0" max="100" value="${Math.round(blur * 100)}"></label>
            ` : ''}
            <div class="diseno-clip-actions">
                <button type="button" class="diseno-chip" id="propClipCircle">Círculo</button>
                <button type="button" class="diseno-chip" id="propClipEllipse">Elipse</button>
                <button type="button" class="diseno-chip" id="propClipRounded">Redondeado</button>
                <button type="button" class="diseno-chip" id="propClipDiamond">Rombo</button>
                <button type="button" class="diseno-chip" id="propClipRect">Rect</button>
                <button type="button" class="diseno-chip" id="propClipNone">Quitar máscara</button>
            </div>
        `;

        const afterChange = () => {
            obj.setCoords();
            canvas.requestRenderAll();
            scheduleHistory();
            syncHidden();
        };

        bindProp('propX', (v) => { obj.set('left', v); afterChange(); });
        bindProp('propY', (v) => { obj.set('top', v); afterChange(); });
        bindProp('propText', (v) => { obj.set('text', v); refreshLayers(); afterChange(); }, true);
        bindProp('propSize', (v) => { obj.set('fontSize', v); afterChange(); });
        bindProp('propFill', (v) => { obj.set('fill', v); afterChange(); }, true);

        document.getElementById('propFontFamily')?.addEventListener('change', (e) => {
            obj.set('fontFamily', e.target.value);
            const allowed = weightsForFont(e.target.value);
            if (!allowed.includes(String(obj.fontWeight))) {
                obj.set('fontWeight', allowed[allowed.length - 1] || '400');
            }
            afterChange();
            refreshProps();
        });
        document.getElementById('propFontWeight')?.addEventListener('change', (e) => {
            obj.set('fontWeight', e.target.value);
            afterChange();
        });
        document.getElementById('propTextAlign')?.addEventListener('change', (e) => {
            obj.set('textAlign', e.target.value);
            afterChange();
        });
        document.getElementById('propBlend')?.addEventListener('change', (e) => {
            obj.globalCompositeOperation = e.target.value;
            afterChange();
        });
        document.getElementById('propOpacity')?.addEventListener('input', (e) => {
            obj.set('opacity', parseInt(e.target.value, 10) / 100);
            afterChange();
        });
        document.getElementById('propAngle')?.addEventListener('input', (e) => {
            obj.set('angle', parseInt(e.target.value, 10));
            afterChange();
        });
        document.getElementById('propW')?.addEventListener('change', (e) => {
            const w = parseFloat(e.target.value) || 1;
            if (obj.type === 'circle') {
                obj.set({ radius: w / 2, scaleX: 1, scaleY: 1 });
            } else {
                obj.set({ scaleX: w / (obj.width || 1) });
            }
            afterChange();
        });
        document.getElementById('propH')?.addEventListener('change', (e) => {
            const h = parseFloat(e.target.value) || 1;
            if (obj.type !== 'circle') {
                obj.set({ scaleY: h / (obj.height || 1) });
            }
            afterChange();
        });

        const readFilters = () => ({
            brightness: (parseInt(document.getElementById('propBright')?.value || '0', 10) || 0) / 100,
            contrast: (parseInt(document.getElementById('propContrast')?.value || '0', 10) || 0) / 100,
            saturation: (parseInt(document.getElementById('propSat')?.value || '0', 10) || 0) / 100,
            blur: (parseInt(document.getElementById('propBlur')?.value || '0', 10) || 0) / 100,
            hue: (parseInt(document.getElementById('propHue')?.value || '0', 10) || 0) / 100,
            noise: (parseInt(document.getElementById('propNoise')?.value || '0', 10) || 0) / 100,
        });
        ['propBright', 'propContrast', 'propSat', 'propBlur', 'propHue', 'propNoise'].forEach((id) => {
            document.getElementById(id)?.addEventListener('input', () => {
                setImageFilters(obj, readFilters());
                afterChange();
            });
        });

        document.getElementById('propClipCircle')?.addEventListener('click', () => applyClip(obj, 'circle'));
        document.getElementById('propClipEllipse')?.addEventListener('click', () => applyClip(obj, 'ellipse'));
        document.getElementById('propClipRounded')?.addEventListener('click', () => applyClip(obj, 'rounded'));
        document.getElementById('propClipDiamond')?.addEventListener('click', () => applyClip(obj, 'diamond'));
        document.getElementById('propClipRect')?.addEventListener('click', () => applyClip(obj, 'rect'));
        document.getElementById('propClipNone')?.addEventListener('click', () => applyClip(obj, 'none'));
    }

    function bindProp(id, fn, asString = false) {
        document.getElementById(id)?.addEventListener('input', (e) => {
            fn(asString || id === 'propFill' || id === 'propText' ? e.target.value : parseFloat(e.target.value) || 0);
        });
    }

    function escapeAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function toHex(color) {
        if (typeof color === 'string' && color.startsWith('#')) {
            if (color.length === 7) return color;
            if (color.length === 4) {
                return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`;
            }
        }
        return BRAND_COLORS[0].hex;
    }

    function addText(text, fontSize, fontWeight) {
        const t = new Textbox(text, {
            left: template.w / 2 - 180,
            top: template.h / 2 - 30,
            width: 360,
            fontFamily: 'Manrope, sans-serif',
            fontSize,
            fontWeight: fontWeight || '600',
            fill: activeColor,
            name: text.slice(0, 24),
        });
        canvas.add(t);
        canvas.setActiveObject(t);
        canvas.requestRenderAll();
        pushHistory();
        refreshProps();
    }

    function addImageFromUrl(url, name = 'Imagen') {
        const sameOrigin = (() => {
            try {
                const u = new URL(url, window.location.origin);
                return u.origin === window.location.origin;
            } catch {
                return true;
            }
        })();
        const opts = sameOrigin ? {} : { crossOrigin: 'anonymous' };
        return FabricImage.fromURL(url, opts).then((img) => {
            const maxSide = Math.min(template.w, template.h) * 0.55;
            const scale = Math.min(maxSide / (img.width || 1), maxSide / (img.height || 1), 1);
            img.set({
                left: template.w / 2 - (img.width * scale) / 2,
                top: template.h / 2 - (img.height * scale) / 2,
                scaleX: scale,
                scaleY: scale,
                name,
            });
            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
            syncHidden();
            return img;
        }).catch((err) => {
            console.error('ITO Diseño: no se pudo cargar la imagen', err);
            throw err;
        });
    }

    async function uploadImageFile(file) {
        if (!uploadUrl) {
            // Fallback local (dev sin ruta): data URL
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (ev) => resolve({ url: ev.target.result, name: file.name });
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }
        const body = new FormData();
        body.append('archivo', file);
        const res = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
            credentials: 'same-origin',
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || `Error al subir (${res.status})`);
        }
        return res.json();
    }

    function handleFiles(files) {
        Array.from(files || []).forEach(async (file) => {
            if (!file.type.startsWith('image/')) return;
            try {
                const uploaded = await uploadImageFile(file);
                await addImageFromUrl(uploaded.url, uploaded.name || file.name.replace(/\.[^.]+$/, '') || 'Imagen');
            } catch (err) {
                console.error(err);
                alert(err.message || 'No se pudo subir la imagen.');
            }
        });
    }

    function setPanel(name) {
        document.querySelectorAll('.diseno-rail-btn').forEach((b) => {
            b.classList.toggle('active', b.dataset.panel === name);
        });
        document.querySelectorAll('.diseno-drawer-panel').forEach((p) => {
            p.classList.toggle('d-none', p.dataset.drawer !== name);
        });
        const drawer = document.getElementById('disenoDrawer');
        if (drawer && window.innerWidth <= 1100) {
            drawer.classList.add('open');
        }
    }

    function ratioClass(w, h) {
        const r = w / h;
        if (Math.abs(r - 1) < 0.05) return 'ratio-sq';
        if (r < 0.7) return 'ratio-916';
        if (r < 0.9) return 'ratio-45';
        if (r > 1.6) return 'ratio-banner';
        return 'ratio-a4';
    }

    function renderTemplateGrid() {
        const grid = document.getElementById('disenoTemplateGrid');
        if (!grid) return;
        const cards = CANVAS_PRESETS.filter((p) => p.formato !== 'custom').map((p) => `
            <button type="button" class="diseno-template-card ${p.formato === template.formato ? 'active' : ''}"
                data-formato="${p.formato}" data-w="${p.w}" data-h="${p.h}">
                <span class="diseno-template-ratio ${ratioClass(p.w, p.h)}"></span>
                <strong>${escapeHtml(p.label)}</strong>
                <small>${p.w} × ${p.h}</small>
            </button>
        `).join('');
        grid.innerHTML = `${cards}
            <div class="diseno-template-custom">
                <strong>Personalizado</strong>
                <div class="diseno-field-row">
                    <label class="diseno-field"><span>Ancho</span><input type="number" id="disenoCustomW" min="64" max="8000" value="${template.formato === 'custom' ? template.w : 1080}"></label>
                    <label class="diseno-field"><span>Alto</span><input type="number" id="disenoCustomH" min="64" max="8000" value="${template.formato === 'custom' ? template.h : 1080}"></label>
                </div>
                <button type="button" class="diseno-btn diseno-btn-ghost" id="disenoCustomApply">Aplicar tamaño</button>
            </div>
            <div class="diseno-content-templates">
                <h4 class="diseno-drawer-subtitle">Plantillas con contenido</h4>
                ${CONTENT_TEMPLATES.map((t) =>
                    `<button type="button" class="diseno-add-btn" data-content-template="${t.id}">${escapeHtml(t.label)}</button>`
                ).join('')}
            </div>
        `;
        grid.querySelectorAll('.diseno-template-card').forEach((btn) => {
            btn.addEventListener('click', () => {
                applyTemplate(btn.dataset.formato, parseInt(btn.dataset.w, 10), parseInt(btn.dataset.h, 10));
            });
        });
        document.getElementById('disenoCustomApply')?.addEventListener('click', () => {
            const w = Math.max(64, parseInt(document.getElementById('disenoCustomW')?.value, 10) || 1080);
            const h = Math.max(64, parseInt(document.getElementById('disenoCustomH')?.value, 10) || 1080);
            applyTemplate('custom', w, h);
        });
        bindContentTemplates(grid);
    }

    function markTemplateActive() {
        document.querySelectorAll('.diseno-template-card').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.formato === template.formato);
        });
    }

    function applyTemplate(fmt, w, h, clear = true) {
        if (clear && canvas.getObjects().length > 0) {
            if (!confirm('Cambiar plantilla vacía el diseño actual. ¿Continuar?')) return;
        }
        template = { w, h, formato: fmt };
        if (clear) {
            canvas.clear();
            canvas.backgroundColor = BG;
            canvas.requestRenderAll();
        }
        markTemplateActive();
        updateDocSize();
        fitCanvas();
        pushHistory();
        refreshLayers();
        syncHidden();
    }

    function loadContentTemplate(id) {
        if (canvas.getObjects().length > 0) {
            if (!confirm('Esto reemplaza el contenido actual del lienzo. ¿Continuar?')) return;
        }
        const json = contenidoPlantilla(id, template.w, template.h);
        historyLock = true;
        canvas.loadFromJSON(json)
            .then(() => {
                if (!canvas.backgroundColor) canvas.backgroundColor = BG;
                canvas.requestRenderAll();
                pushHistory();
                refreshProps();
                syncHidden();
            })
            .catch((err) => console.error('ITO Diseño: no se pudo cargar plantilla', err))
            .finally(() => { historyLock = false; });
    }

    function bindContentTemplates(scope = document) {
        scope.querySelectorAll('[data-content-template]').forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => loadContentTemplate(btn.dataset.contentTemplate));
        });
    }

    function addBrandAsset(assetId) {
        const asset = brandAssets.find((a) => a.id === assetId);
        if (!asset) return;
        if (asset.kind === 'image' && asset.url) {
            addImageFromUrl(asset.url, asset.label).catch(() => {});
            return;
        }
        if (asset.kind === 'mockup') {
            addMockupGarment(asset.url || mockupUrl, asset.label || 'Mockup prenda');
            return;
        }
        let obj = null;
        if (asset.kind === 'text-badge') {
            obj = new Textbox(asset.text, {
                left: template.w / 2 - 160,
                top: template.h / 2 - 30,
                width: 320,
                fontFamily: 'Manrope, sans-serif',
                fontSize: asset.fontSize || 48,
                fontWeight: asset.fontWeight || '800',
                fill: activeColor,
                textAlign: 'center',
                name: asset.label,
            });
        } else if (asset.kind === 'shape-circle') {
            obj = new Circle({
                left: template.w / 2 - 60,
                top: template.h / 2 - 60,
                radius: 60,
                fill: asset.fill || activeColor,
                name: asset.label,
            });
        } else if (asset.kind === 'shape-rect') {
            obj = new Rect({
                left: template.w / 2 - 100,
                top: template.h / 2 - 50,
                width: 200,
                height: 100,
                fill: asset.fill || activeColor,
                rx: 8,
                ry: 8,
                name: asset.label,
            });
        } else if (asset.kind === 'shape-line') {
            obj = new Line(
                [template.w / 2 - 140, template.h / 2, template.w / 2 + 140, template.h / 2],
                {
                    stroke: asset.stroke || activeColor,
                    strokeWidth: 6,
                    strokeLineCap: 'round',
                    name: asset.label,
                },
            );
        }
        if (!obj) return;
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        pushHistory();
        refreshProps();
    }

    function renderBrandAssets() {
        const panel = document.querySelector('[data-drawer="marca"]');
        if (!panel || panel.querySelector('[data-asset], [data-mockup-url]')) return;
        const wrap = document.createElement('div');
        wrap.className = 'diseno-brand-assets';
        const kitItems = brandAssets.filter((a) => a.kit_id);
        const items = brandAssets.filter((a) => a.kind !== 'mockup' && !a.kit_id);
        const mockups = brandAssets.filter((a) => a.kind === 'mockup');
        wrap.innerHTML = `
            <h4 class="diseno-drawer-subtitle">Assets de marca</h4>
            <div class="diseno-brand-grid" id="disenoBrandGrid">
                ${items.map((a) => brandThumbHtml(a)).join('')}
            </div>
            <h4 class="diseno-drawer-subtitle">Kit del estudio</h4>
            ${canManageKit ? `
                <p class="diseno-hint">Logos/kits compartidos. Solo admin/dirección puede subir o borrar.</p>
                <label class="diseno-dropzone diseno-dropzone-sm" id="disenoKitDropzone">
                    <input type="file" id="disenoKitInput" accept="image/*,.svg" class="d-none">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Subir al kit de marca</span>
                </label>
            ` : `<p class="diseno-hint">Assets compartidos del estudio.</p>`}
            <div class="diseno-brand-grid" id="disenoKitGrid">
                ${kitItems.length
                    ? kitItems.map((a) => brandThumbHtml(a, canManageKit)).join('')
                    : '<p class="diseno-hint" id="disenoKitEmpty">Todavía no hay assets en el kit.</p>'}
            </div>
            <h4 class="diseno-drawer-subtitle">Mockups de prenda</h4>
            <p class="diseno-hint">Solo guía visual: no se incluyen en la exportación DTF/sablón.</p>
            <div class="diseno-mockup-grid">
                ${mockups.map((m) => `
                    <button type="button" class="diseno-mockup-btn" data-mockup-url="${escapeAttr(m.url)}" data-mockup-label="${escapeAttr(m.label)}">
                        <span class="diseno-mockup-thumb" style="background-image:url('${escapeAttr(m.url)}')"></span>
                        <span>${escapeHtml(m.label.replace(/^Mockup\s+/i, ''))}</span>
                    </button>
                `).join('') || `
                    <button type="button" class="diseno-add-btn" data-action="mockup-hoodie">
                        <i class="bi bi-person-bounding-box"></i> Hoodie
                    </button>
                `}
            </div>
            <label class="diseno-check">
                <input type="checkbox" id="disenoMockupToggle"> Mostrar mockup
            </label>
        `;
        panel.appendChild(wrap);
        bindBrandAssets(wrap);
        bindKitDeletes(wrap);
        wrap.querySelectorAll('[data-mockup-url]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                addMockupGarment(btn.dataset.mockupUrl, btn.dataset.mockupLabel || 'Mockup prenda');
            });
        });
        wrap.querySelector('[data-action="mockup-hoodie"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            addMockupGarment(mockupUrl, 'Mockup hoodie');
        });
        document.getElementById('disenoMockupToggle')?.addEventListener('change', (e) => {
            setMockupVisible(e.target.checked);
        });
        if (canManageKit) {
            const kitInput = document.getElementById('disenoKitInput');
            const kitZone = document.getElementById('disenoKitDropzone');
            kitInput?.addEventListener('change', () => {
                const file = kitInput.files?.[0];
                if (file) uploadKitAsset(file).finally(() => { kitInput.value = ''; });
            });
            kitZone?.addEventListener('dragover', (e) => { e.preventDefault(); kitZone.classList.add('drag'); });
            kitZone?.addEventListener('dragleave', () => kitZone.classList.remove('drag'));
            kitZone?.addEventListener('drop', (e) => {
                e.preventDefault();
                kitZone.classList.remove('drag');
                const file = e.dataTransfer.files?.[0];
                if (file) uploadKitAsset(file);
            });
        }
    }

    function brandThumbHtml(a, withDelete = false) {
        if (a.kind === 'image' && (a.thumb || a.url)) {
            const del = withDelete && a.kit_id
                ? `<button type="button" class="diseno-kit-del" data-kit-id="${a.kit_id}" title="Quitar del kit" aria-label="Quitar">×</button>`
                : '';
            return `<div class="diseno-brand-thumb-wrap">
                <button type="button" class="diseno-brand-thumb" data-asset="${a.id}" title="${escapeAttr(a.label)}">
                    <img src="${escapeAttr(a.thumb || a.url)}" alt="">
                    <span>${escapeHtml(a.label)}</span>
                </button>
                ${del}
            </div>`;
        }
        return `<button type="button" class="diseno-add-btn" data-asset="${a.id}">${escapeHtml(a.label)}</button>`;
    }

    function bindKitDeletes(scope = document) {
        scope.querySelectorAll('[data-kit-id]').forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const id = btn.dataset.kitId;
                if (!id || !confirm('¿Quitar este asset del kit compartido?')) return;
                try {
                    const res = await fetch(`${kitDestroyBase}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const idx = brandAssets.findIndex((a) => String(a.kit_id) === String(id));
                    if (idx >= 0) brandAssets.splice(idx, 1);
                    btn.closest('.diseno-brand-thumb-wrap')?.remove();
                    const grid = document.getElementById('disenoKitGrid');
                    if (grid && !grid.querySelector('[data-asset]')) {
                        grid.innerHTML = '<p class="diseno-hint" id="disenoKitEmpty">Todavía no hay assets en el kit.</p>';
                    }
                } catch (err) {
                    console.error('ITO Diseño: borrar kit', err);
                    alert('No se pudo borrar el asset del kit.');
                }
            });
        });
    }

    async function uploadKitAsset(file) {
        if (!kitUploadUrl) return;
        const body = new FormData();
        body.append('archivo', file);
        body.append('titulo', file.name.replace(/\.[^.]+$/, '') || 'Asset');
        try {
            const res = await fetch(kitUploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
                credentials: 'same-origin',
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || `Error ${res.status}`);
            if (json.item) {
                brandAssets.push(json.item);
                const grid = document.getElementById('disenoKitGrid');
                document.getElementById('disenoKitEmpty')?.remove();
                if (grid && json.item.url) {
                    grid.insertAdjacentHTML('beforeend', brandThumbHtml(json.item, true));
                    bindBrandAssets(grid);
                    bindKitDeletes(grid);
                }
                addImageFromUrl(json.item.url, json.item.label).catch(() => {});
            }
        } catch (err) {
            console.error('ITO Diseño: kit', err);
            alert(err.message || 'No se pudo subir al kit.');
        }
    }

    function bindBrandAssets(scope = document) {
        scope.querySelectorAll('[data-asset]').forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => addBrandAsset(btn.dataset.asset));
        });
    }

    function findMockups() {
        return canvas.getObjects().filter((o) => o._mockupOverlay || o.name === 'Mockup prenda');
    }

    function setMockupVisible(visible) {
        const mocks = findMockups();
        if (!mocks.length && visible) {
            addMockupGarment(mockupUrl, 'Mockup prenda');
            return;
        }
        mocks.forEach((o) => { o.visible = visible; });
        canvas.requestRenderAll();
        refreshLayers();
        const toggle = document.getElementById('disenoMockupToggle');
        if (toggle) toggle.checked = visible && mocks.some((o) => o.visible !== false);
        scheduleHistory('Mockup');
    }

    function toggleMockup() {
        const mocks = findMockups();
        if (!mocks.length) {
            addMockupGarment(mockupUrl, 'Mockup prenda');
            return;
        }
        const anyVisible = mocks.some((o) => o.visible !== false);
        setMockupVisible(!anyVisible);
    }

    function addMockupGarment(url = mockupUrl, label = 'Mockup prenda') {
        const existing = findMockups();
        if (existing.length) {
            // Reemplazar mockup actual por el elegido
            existing.forEach((o) => canvas.remove(o));
        }
        FabricImage.fromURL(url, { crossOrigin: 'anonymous' }).then((img) => {
            const maxW = template.w * 0.72;
            const maxH = template.h * 0.85;
            const scale = Math.min(maxW / (img.width || 1), maxH / (img.height || 1), 1);
            img.set({
                left: (template.w - img.width * scale) / 2,
                top: (template.h - img.height * scale) / 2,
                scaleX: scale,
                scaleY: scale,
                name: label || 'Mockup prenda',
                selectable: true,
                evented: true,
                excludeFromExport: true,
                _mockupOverlay: true,
                opacity: 1,
            });
            canvas.add(img);
            canvas.bringObjectToFront(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
            const toggle = document.getElementById('disenoMockupToggle');
            if (toggle) toggle.checked = true;
            pushHistory(label || 'Mockup');
            refreshProps();
        }).catch((err) => {
            console.error('ITO Diseño: no se pudo cargar el mockup', err);
            const padX = template.w * 0.15;
            const padY = template.h * 0.12;
            const mock = new Rect({
                left: padX,
                top: padY,
                width: template.w - padX * 2,
                height: template.h - padY * 2,
                fill: '#222222',
                opacity: 0.28,
                rx: 24,
                ry: 24,
                name: label || 'Mockup prenda',
                excludeFromExport: true,
                _mockupOverlay: true,
            });
            canvas.add(mock);
            canvas.requestRenderAll();
            pushHistory(label || 'Mockup');
            refreshProps();
        });
    }

    function addMockupHoodie(url = mockupUrl) {
        addMockupGarment(url, 'Mockup hoodie');
    }

    async function runExport() {
        const opts = await openExportDialog();
        if (!opts) return;
        const title = document.querySelector('.diseno-doc-title')?.value || 'diseno-ito';
        try {
            await exportDesign(canvas, { ...opts, template, title });
        } catch (err) {
            console.error('ITO Diseño: error al exportar', err);
            alert('No se pudo exportar el diseño.');
        }
    }

    function readInitialJson() {
        const script = document.getElementById('disenoInitialJson');
        if (script?.textContent?.trim()) {
            try { return JSON.parse(script.textContent); } catch (e) { /* ignore */ }
        }
        const raw = root.dataset.canvasJson;
        if (raw && raw !== 'null' && raw !== '') {
            try { return JSON.parse(raw); } catch (e) { /* ignore */ }
        }
        return null;
    }

    function loadInitial() {
        const data = readInitialJson();
        markTemplateActive();
        updateDocSize();
        if (data && (data.objects?.length || data.background || data.backgroundColor)) {
            canvas.loadFromJSON(data)
                .then(() => {
                    if (!canvas.backgroundColor) canvas.backgroundColor = BG;
                    canvas.requestRenderAll();
                    fitCanvas();
                    pushHistory();
                    refreshLayers();
                    syncHidden();
                    const toggle = document.getElementById('disenoMockupToggle');
                    if (toggle) {
                        const mocks = findMockups();
                        toggle.checked = mocks.some((o) => o.visible !== false);
                    }
                })
                .catch((err) => {
                    console.error('ITO Diseño: no se pudo cargar el lienzo', err);
                    fitCanvas();
                    pushHistory();
                    refreshLayers();
                });
            return;
        }
        fitCanvas();
        pushHistory();
        refreshLayers();
    }

    const palette = document.getElementById('disenoPalette');
    if (palette) {
        palette.innerHTML = BRAND_COLORS.map((c, i) =>
            `<button type="button" class="diseno-swatch ${i === 0 ? 'active' : ''}" style="background:${c.hex}" data-color="${c.hex}" title="${c.name}"></button>`
        ).join('');
        palette.querySelectorAll('.diseno-swatch').forEach((sw) => {
            sw.addEventListener('click', () => {
                palette.querySelectorAll('.diseno-swatch').forEach((s) => s.classList.remove('active'));
                sw.classList.add('active');
                activeColor = sw.dataset.color;
                const obj = canvas.getActiveObject();
                if (obj && (typeof obj.fill === 'string' || obj.type === 'textbox' || obj.type === 'rect' || obj.type === 'circle')) {
                    obj.set('fill', activeColor);
                    canvas.requestRenderAll();
                    refreshProps();
                    pushHistory();
                } else if (obj && obj.type === 'line') {
                    obj.set('stroke', activeColor);
                    canvas.requestRenderAll();
                    refreshProps();
                    pushHistory();
                }
            });
        });
    }

    renderTemplateGrid();
    renderBrandAssets();
    bindContentTemplates();
    bindBrandAssets();

    document.getElementById('disenoLayerGroup')?.addEventListener('click', groupSelection);
    document.getElementById('disenoLayerUngroup')?.addEventListener('click', ungroupSelection);

    document.querySelectorAll('.diseno-rail-btn').forEach((btn) => {
        btn.addEventListener('click', () => setPanel(btn.dataset.panel));
    });

    document.querySelectorAll('.diseno-template-card').forEach((btn) => {
        if (btn.closest('#disenoTemplateGrid')) return;
        btn.addEventListener('click', () => {
            applyTemplate(btn.dataset.formato, parseInt(btn.dataset.w, 10), parseInt(btn.dataset.h, 10));
        });
    });

    document.getElementById('disenoCustomApply')?.addEventListener('click', () => {
        const w = Math.max(64, parseInt(document.getElementById('disenoCustomW')?.value, 10) || 1080);
        const h = Math.max(64, parseInt(document.getElementById('disenoCustomH')?.value, 10) || 1080);
        applyTemplate('custom', w, h);
    });

    const actions = {
        'text-heading': () => addText('Título', 64, '700'),
        'text-sub': () => addText('Subtítulo', 36, '600'),
        'text-body': () => addText('Escribí tu texto aquí', 22, '400'),
        text: () => addText('Nuevo texto', 40, '600'),
        rect: () => {
            const r = new Rect({
                left: template.w / 2 - 100,
                top: template.h / 2 - 70,
                width: 200,
                height: 140,
                fill: activeColor,
                rx: 10,
                ry: 10,
                name: 'Rectángulo',
            });
            canvas.add(r);
            canvas.setActiveObject(r);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
        },
        circle: () => {
            const c = new Circle({
                left: template.w / 2 - 80,
                top: template.h / 2 - 80,
                radius: 80,
                fill: activeColor,
                name: 'Círculo',
            });
            canvas.add(c);
            canvas.setActiveObject(c);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
        },
        line: () => {
            const ln = new Line([template.w / 2 - 120, template.h / 2, template.w / 2 + 120, template.h / 2], {
                stroke: activeColor,
                strokeWidth: 6,
                strokeLineCap: 'round',
                name: 'Línea',
            });
            canvas.add(ln);
            canvas.setActiveObject(ln);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
        },
        delete: () => {
            const obj = canvas.getActiveObject();
            if (obj) {
                canvas.remove(obj);
                canvas.discardActiveObject();
                canvas.requestRenderAll();
                pushHistory();
                refreshProps();
            }
        },
        duplicate: () => {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            obj.clone().then((cloned) => {
                cloned.set({ left: (obj.left || 0) + 24, top: (obj.top || 0) + 24 });
                if (obj._mockupOverlay) {
                    cloned._mockupOverlay = true;
                    cloned.excludeFromExport = true;
                }
                canvas.add(cloned);
                canvas.setActiveObject(cloned);
                canvas.requestRenderAll();
                pushHistory();
                refreshProps();
            });
        },
        front: () => {
            const obj = canvas.getActiveObject();
            if (obj) {
                canvas.bringObjectToFront(obj);
                canvas.requestRenderAll();
                pushHistory();
                refreshLayers();
            }
        },
        back: () => {
            const obj = canvas.getActiveObject();
            if (obj) {
                canvas.sendObjectToBack(obj);
                canvas.requestRenderAll();
                pushHistory();
                refreshLayers();
            }
        },
        'mockup-hoodie': () => addMockupHoodie(),
        'mockup-toggle': () => toggleMockup(),
        'align-left': () => {
            const obj = canvas.getActiveObject();
            if (obj?.type === 'textbox') {
                obj.set('textAlign', 'left');
                canvas.requestRenderAll();
                scheduleHistory();
            }
        },
        'align-center': () => {
            const obj = canvas.getActiveObject();
            if (obj?.type === 'textbox') {
                obj.set('textAlign', 'center');
                canvas.requestRenderAll();
                scheduleHistory();
            }
        },
        'align-right': () => {
            const obj = canvas.getActiveObject();
            if (obj?.type === 'textbox') {
                obj.set('textAlign', 'right');
                canvas.requestRenderAll();
                scheduleHistory();
            }
        },
        'bg-white': () => { canvas.backgroundColor = '#ffffff'; canvas.requestRenderAll(); pushHistory(); syncHidden(); },
        'bg-black': () => { canvas.backgroundColor = '#000000'; canvas.requestRenderAll(); pushHistory(); syncHidden(); },
        'bg-accent': () => { canvas.backgroundColor = '#f26422'; canvas.requestRenderAll(); pushHistory(); syncHidden(); },
        'bg-cream': () => { canvas.backgroundColor = '#f5f0e8'; canvas.requestRenderAll(); pushHistory(); syncHidden(); },
        'flyer-banner': () => {
            const bar = new Rect({
                left: 0, top: 0, width: template.w, height: Math.round(template.h * 0.22),
                fill: activeColor, name: 'Franja',
            });
            const title = new Textbox('Título del evento', {
                left: template.w * 0.08,
                top: template.h * 0.06,
                width: template.w * 0.84,
                fontFamily: 'Manrope, sans-serif',
                fontSize: Math.round(template.w * 0.07),
                fontWeight: '800',
                fill: '#ffffff',
                name: 'Título',
            });
            canvas.add(bar, title);
            canvas.setActiveObject(title);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
        },
        'flyer-cta': () => {
            const bw = template.w * 0.42;
            const bh = template.h * 0.08;
            const left = (template.w - bw) / 2;
            const top = template.h * 0.82;
            const bg = new Rect({
                left, top, width: bw, height: bh, fill: activeColor, rx: 10, ry: 10, name: 'CTA fondo',
            });
            const label = new Textbox('Inscribite', {
                left, top: top + bh * 0.18, width: bw,
                fontFamily: 'Manrope, sans-serif',
                fontSize: Math.round(template.w * 0.035),
                fontWeight: '700',
                fill: '#ffffff',
                textAlign: 'center',
                name: 'CTA',
            });
            canvas.add(bg, label);
            canvas.setActiveObject(label);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
        },
    };

    document.querySelectorAll('[data-action]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const fn = actions[el.dataset.action];
            if (fn) fn();
        });
    });

    document.getElementById('disenoMockupToggle')?.addEventListener('change', (e) => {
        setMockupVisible(e.target.checked);
    });

    const imgInput = document.getElementById('disenoImgInput');
    const dropzone = document.getElementById('disenoDropzone');
    imgInput?.addEventListener('change', (e) => handleFiles(e.target.files));
    dropzone?.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone?.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        handleFiles(e.dataTransfer?.files);
    });

    document.getElementById('disenoZoomRange')?.addEventListener('input', (e) => {
        userZoom = parseInt(e.target.value, 10);
        applyZoom();
    });
    document.getElementById('disenoZoomFit')?.addEventListener('click', () => {
        userZoom = 100;
        fitCanvas();
    });

    document.getElementById('disenoUndoBtn')?.addEventListener('click', undo);
    document.getElementById('disenoRedoBtn')?.addEventListener('click', redo);
    document.getElementById('disenoExportBtn')?.addEventListener('click', runExport);

    function closeMenus() {
        document.querySelectorAll('[data-menu-panel]').forEach((p) => { p.hidden = true; });
        document.querySelectorAll('[data-menu-toggle]').forEach((b) => {
            b.classList.remove('is-open');
            b.setAttribute('aria-expanded', 'false');
        });
    }

    document.querySelectorAll('[data-menu-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const name = btn.dataset.menuToggle;
            const panel = document.querySelector(`[data-menu-panel="${name}"]`);
            const wasOpen = panel && !panel.hidden;
            closeMenus();
            if (panel && !wasOpen) {
                panel.hidden = false;
                btn.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.querySelectorAll('[data-menu-action]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const act = el.dataset.menuAction;
            closeMenus();
            if (act === 'save') {
                syncHidden();
                form.requestSubmit();
                return;
            }
            if (act === 'export') { runExport(); return; }
            if (act === 'undo') { undo(); return; }
            if (act === 'redo') { redo(); return; }
            if (act === 'duplicate') { actions.duplicate(); return; }
            if (act === 'delete') { actions.delete(); return; }
            if (act === 'front') { actions.front(); return; }
            if (act === 'back') { actions.back(); return; }
            if (act === 'zoom-in') {
                userZoom = Math.min(200, userZoom + 15);
                applyZoom();
                return;
            }
            if (act === 'zoom-out') {
                userZoom = Math.max(15, userZoom - 15);
                applyZoom();
                return;
            }
            if (act === 'zoom-fit') {
                userZoom = 100;
                fitCanvas();
                return;
            }
            if (act === 'zoom-100') {
                fitScale = 1;
                userZoom = 100;
                applyZoom();
                return;
            }
            if (act?.startsWith('panel-')) {
                setPanel(act.replace('panel-', ''));
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#disenoMenuBar')) closeMenus();
    });

    canvas.on('selection:created', () => { clearGuides(canvas); refreshProps(); });
    canvas.on('selection:updated', () => { clearGuides(canvas); refreshProps(); });
    canvas.on('selection:cleared', () => { clearGuides(canvas); refreshProps(); });
    canvas.on('object:moving', (e) => {
        snapObject(canvas, e.target, template);
    });
    canvas.on('object:modified', () => {
        clearGuides(canvas);
        pushHistory();
        syncHidden();
        refreshProps();
    });
    canvas.on('object:added', () => { refreshLayers(); syncHidden(); });
    canvas.on('object:removed', () => { refreshLayers(); syncHidden(); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMenus();
        if (e.target.matches('input, textarea, select')) return;

        if (e.key === 'Delete' || e.key === 'Backspace') {
            e.preventDefault();
            actions.delete();
            return;
        }
        if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); return; }
        if (e.ctrlKey && e.key === 'y') { e.preventDefault(); redo(); return; }
        if (e.ctrlKey && e.key === 'd') { e.preventDefault(); actions.duplicate(); return; }
        if (e.ctrlKey && e.key === 'g' && !e.shiftKey) { e.preventDefault(); groupSelection(); return; }
        if (e.ctrlKey && e.shiftKey && (e.key === 'G' || e.key === 'g')) { e.preventDefault(); ungroupSelection(); return; }

        const obj = canvas.getActiveObject();
        if (!obj || isLocked(obj)) return;
        const step = e.shiftKey ? 10 : 1;
        let moved = false;
        if (e.key === 'ArrowLeft') { obj.set('left', (obj.left || 0) - step); moved = true; }
        if (e.key === 'ArrowRight') { obj.set('left', (obj.left || 0) + step); moved = true; }
        if (e.key === 'ArrowUp') { obj.set('top', (obj.top || 0) - step); moved = true; }
        if (e.key === 'ArrowDown') { obj.set('top', (obj.top || 0) + step); moved = true; }
        if (moved) {
            e.preventDefault();
            obj.setCoords();
            canvas.requestRenderAll();
            scheduleHistory();
            refreshProps();
            syncHidden();
        }
    });

    form.addEventListener('submit', (e) => {
        if (form.dataset.readonly === '1') {
            e.preventDefault();
            alert('Este diseño es de solo lectura. Creá un proyecto nuevo o duplicá el contenido en uno propio.');
            return;
        }
        syncHidden();
    });
    window.addEventListener('resize', fitCanvas);

    setPanel('select');
    if (bibliotecaApiUrl) {
        initBibliotecaPanel({
            apiUrl: bibliotecaApiUrl,
            onInsert: (item) => {
                addImageFromUrl(item.archivo_url, item.titulo || 'Biblioteca').catch(() => {});
            },
        });
        bindBibliotecaDrop(document.getElementById('disenoStageInner'), (item) => {
            addImageFromUrl(item.archivo_url, item.titulo || 'Biblioteca').catch(() => {});
        });
    }
    ensureEditorFonts().finally(() => {
        loadInitial();
        requestAnimationFrame(() => {
            fitCanvas();
            setTimeout(fitCanvas, 120);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        initDisenoEditor();
    } catch (err) {
        console.error('ITO Diseño: error al iniciar el editor', err);
    }
});
