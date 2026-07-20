import '../css/diseno-canvas.css';
import { Canvas, Rect, Circle, Textbox, Line, Image as FabricImage } from 'fabric';

const BRAND_COLORS = [
    { hex: '#c1432b', name: 'Ladrillo' },
    { hex: '#d1a054', name: 'Latón' },
    { hex: '#4a9a86', name: 'Verdigris' },
    { hex: '#f3e9d8', name: 'Crema' },
    { hex: '#1d160f', name: 'Madera' },
    { hex: '#9c8ad1', name: 'Violeta' },
];

const TEMPLATES = [
    { formato: 'flyer_feed', w: 1080, h: 1350, label: 'Flyer feed' },
    { formato: 'historia', w: 1080, h: 1920, label: 'Historia' },
    { formato: 'afiche_a4', w: 1240, h: 1748, label: 'Afiche A4' },
    { formato: 'banner_web', w: 1200, h: 628, label: 'Banner web' },
];

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

    const history = [];
    let historyStep = -1;
    let historyLock = false;

    const canvas = new Canvas(canvasEl, {
        width: template.w,
        height: template.h,
        backgroundColor: '#f3e9d8',
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

    function syncHidden() {
        document.getElementById('disenoFormato').value = template.formato;
        document.getElementById('disenoAncho').value = String(template.w);
        document.getElementById('disenoAlto').value = String(template.h);
        document.getElementById('disenoCanvasJson').value = JSON.stringify(canvas.toJSON());
        const cw = canvas.getWidth() || 1;
        const mult = template.w / cw;
        document.getElementById('disenoPreviewBase64').value = canvas.toDataURL({
            format: 'png',
            quality: 1,
            multiplier: mult > 0 ? mult : 1,
        });
    }

    function pushHistory() {
        if (historyLock) return;
        const snap = JSON.stringify(canvas.toJSON());
        if (history[historyStep] === snap) return;
        historyStep++;
        history.splice(historyStep);
        history.push(snap);
        if (history.length > 40) {
            history.shift();
            historyStep--;
        }
    }

    function loadHistory(step) {
        if (step < 0 || step >= history.length) return;
        historyLock = true;
        canvas.loadFromJSON(history[step])
            .then(() => {
                canvas.requestRenderAll();
                refreshProps();
                syncHidden();
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
        if (obj.type === 'textbox') return 'bi-type';
        if (obj.type === 'image') return 'bi-image';
        if (obj.type === 'circle') return 'bi-circle';
        if (obj.type === 'line') return 'bi-dash-lg';
        return 'bi-square';
    }

    function layerLabel(obj) {
        if (obj.type === 'textbox') return (obj.text || 'Texto').slice(0, 24);
        if (obj.type === 'image') return 'Imagen';
        if (obj.type === 'circle') return 'Círculo';
        if (obj.type === 'line') return 'Línea';
        return 'Rectángulo';
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
            const sel = o === active ? 'selected' : '';
            return `<button type="button" class="diseno-layer-item ${sel}" data-idx="${idx}">
                <i class="bi ${layerIcon(o)}"></i>
                <span>${layerLabel(o)}</span>
            </button>`;
        }).join('');
        list.querySelectorAll('.diseno-layer-item').forEach((el) => {
            el.addEventListener('click', () => {
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                if (!obj) return;
                canvas.setActiveObject(obj);
                canvas.requestRenderAll();
                refreshProps();
            });
        });
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
        const isText = obj.type === 'textbox';
        const fill = typeof obj.fill === 'string' ? obj.fill : activeColor;
        const opacity = Math.round((obj.opacity ?? 1) * 100);
        const angle = Math.round(obj.angle ?? 0);
        panel.innerHTML = `
            <h3 class="diseno-drawer-title">Propiedades</h3>
            ${isText ? `<label class="diseno-field"><span>Texto</span><input type="text" id="propText" value="${escapeAttr(obj.text || '')}"></label>` : ''}
            <div class="diseno-field-row">
                <label class="diseno-field"><span>X</span><input type="number" id="propX" value="${Math.round(obj.left)}"></label>
                <label class="diseno-field"><span>Y</span><input type="number" id="propY" value="${Math.round(obj.top)}"></label>
            </div>
            <div class="diseno-field-row">
                <label class="diseno-field"><span>Ancho</span><input type="number" id="propW" value="${Math.round(obj.getScaledWidth?.() || obj.width || 0)}"></label>
                <label class="diseno-field"><span>Alto</span><input type="number" id="propH" value="${Math.round(obj.getScaledHeight?.() || obj.height || obj.radius * 2 || 0)}"></label>
            </div>
            ${isText ? `<label class="diseno-field"><span>Tamaño fuente</span><input type="number" id="propSize" value="${Math.round(obj.fontSize)}"></label>` : ''}
            <label class="diseno-field"><span>Color relleno</span><input type="color" id="propFill" value="${toHex(fill)}"></label>
            <label class="diseno-field"><span>Opacidad (${opacity}%)</span><input type="range" id="propOpacity" min="0" max="100" value="${opacity}"></label>
            <label class="diseno-field"><span>Rotación (${angle}°)</span><input type="range" id="propAngle" min="0" max="360" value="${angle}"></label>
        `;
        bindProp('propX', (v) => { obj.set('left', v); });
        bindProp('propY', (v) => { obj.set('top', v); });
        bindProp('propText', (v) => { obj.set('text', v); refreshLayers(); });
        bindProp('propSize', (v) => { obj.set('fontSize', v); });
        bindProp('propFill', (v) => { obj.set('fill', v); });
        document.getElementById('propOpacity')?.addEventListener('input', (e) => {
            obj.set('opacity', parseInt(e.target.value, 10) / 100);
            canvas.requestRenderAll();
        });
        document.getElementById('propAngle')?.addEventListener('input', (e) => {
            obj.set('angle', parseInt(e.target.value, 10));
            canvas.requestRenderAll();
        });
        document.getElementById('propW')?.addEventListener('change', (e) => {
            const w = parseFloat(e.target.value) || 1;
            if (obj.type === 'circle') {
                obj.set({ radius: w / 2, scaleX: 1, scaleY: 1 });
            } else {
                obj.set({ scaleX: w / (obj.width || 1) });
            }
            canvas.requestRenderAll();
        });
        document.getElementById('propH')?.addEventListener('change', (e) => {
            const h = parseFloat(e.target.value) || 1;
            if (obj.type !== 'circle') {
                obj.set({ scaleY: h / (obj.height || 1) });
            }
            canvas.requestRenderAll();
        });
    }

    function bindProp(id, fn) {
        document.getElementById(id)?.addEventListener('input', (e) => {
            fn(id === 'propText' ? e.target.value : parseFloat(e.target.value) || 0);
            canvas.requestRenderAll();
        });
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function toHex(color) {
        if (typeof color === 'string' && color.startsWith('#')) {
            return color.length === 7 ? color : '#c1432b';
        }
        return '#c1432b';
    }

    function addText(text, fontSize, fontWeight) {
        const t = new Textbox(text, {
            left: template.w / 2 - 180,
            top: template.h / 2 - 30,
            width: 360,
            fontFamily: 'Inter, sans-serif',
            fontSize,
            fontWeight: fontWeight || '600',
            fill: activeColor,
        });
        canvas.add(t);
        canvas.setActiveObject(t);
        canvas.requestRenderAll();
        pushHistory();
        refreshProps();
    }

    function addImageFromDataUrl(dataUrl) {
        FabricImage.fromURL(dataUrl).then((img) => {
            const maxSide = Math.min(template.w, template.h) * 0.55;
            const scale = Math.min(maxSide / (img.width || 1), maxSide / (img.height || 1), 1);
            img.set({
                left: template.w / 2 - (img.width * scale) / 2,
                top: template.h / 2 - (img.height * scale) / 2,
                scaleX: scale,
                scaleY: scale,
            });
            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
            pushHistory();
            refreshProps();
        }).catch((err) => console.error('ITO Diseño: no se pudo cargar la imagen', err));
    }

    function handleFiles(files) {
        Array.from(files || []).forEach((file) => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = (ev) => addImageFromDataUrl(ev.target.result);
            reader.readAsDataURL(file);
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
            canvas.backgroundColor = '#f3e9d8';
            canvas.requestRenderAll();
        }
        markTemplateActive();
        updateDocSize();
        fitCanvas();
        pushHistory();
        refreshLayers();
        syncHidden();
    }

    function readInitialJson() {
        const script = document.getElementById('disenoInitialJson');
        if (script?.textContent?.trim()) {
            try { return JSON.parse(script.textContent); } catch (e) { /* */ }
        }
        const raw = root.dataset.canvasJson;
        if (raw && raw !== 'null' && raw !== '') {
            try { return JSON.parse(raw); } catch (e) { /* */ }
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
                    if (!canvas.backgroundColor) canvas.backgroundColor = '#f3e9d8';
                    canvas.requestRenderAll();
                    fitCanvas();
                    pushHistory();
                    refreshLayers();
                    syncHidden();
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
                if (obj) {
                    obj.set('fill', activeColor);
                    canvas.requestRenderAll();
                    refreshProps();
                    pushHistory();
                }
            });
        });
    }

    document.querySelectorAll('.diseno-rail-btn').forEach((btn) => {
        btn.addEventListener('click', () => setPanel(btn.dataset.panel));
    });

    document.querySelectorAll('.diseno-template-card').forEach((btn) => {
        btn.addEventListener('click', () => {
            applyTemplate(btn.dataset.formato, parseInt(btn.dataset.w, 10), parseInt(btn.dataset.h, 10));
        });
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
                canvas.add(cloned);
                canvas.setActiveObject(cloned);
                canvas.requestRenderAll();
                pushHistory();
                refreshProps();
            });
        },
        front: () => {
            const obj = canvas.getActiveObject();
            if (obj) { canvas.bringObjectToFront(obj); canvas.requestRenderAll(); pushHistory(); refreshLayers(); }
        },
        back: () => {
            const obj = canvas.getActiveObject();
            if (obj) { canvas.sendObjectToBack(obj); canvas.requestRenderAll(); pushHistory(); refreshLayers(); }
        },
    };

    document.querySelectorAll('[data-action]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const fn = actions[el.dataset.action];
            if (fn) fn();
        });
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

    function exportPng() {
        const cw = canvas.getWidth() || 1;
        const mult = template.w / cw;
        const a = document.createElement('a');
        a.href = canvas.toDataURL({ format: 'png', quality: 1, multiplier: mult > 0 ? mult : 1 });
        a.download = (document.querySelector('.diseno-doc-title')?.value || 'diseno-ito').replace(/\s+/g, '-') + '.png';
        a.click();
    }

    document.getElementById('disenoExportBtn')?.addEventListener('click', exportPng);

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
            if (act === 'export') { exportPng(); return; }
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

    canvas.on('selection:created', refreshProps);
    canvas.on('selection:updated', refreshProps);
    canvas.on('selection:cleared', refreshProps);
    canvas.on('object:modified', () => { pushHistory(); syncHidden(); });
    canvas.on('object:added', () => { refreshLayers(); syncHidden(); });
    canvas.on('object:removed', () => { refreshLayers(); syncHidden(); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMenus();
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'Delete' || e.key === 'Backspace') {
            e.preventDefault();
            actions.delete();
        }
        if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); }
        if (e.ctrlKey && e.key === 'y') { e.preventDefault(); redo(); }
        if (e.ctrlKey && e.key === 'd') { e.preventDefault(); actions.duplicate(); }
    });

    form.addEventListener('submit', () => syncHidden());
    window.addEventListener('resize', fitCanvas);

    setPanel('select');
    loadInitial();
    requestAnimationFrame(() => {
        fitCanvas();
        setTimeout(fitCanvas, 120);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        initDisenoEditor();
    } catch (err) {
        console.error('ITO Diseño: error al iniciar el editor', err);
    }
});
