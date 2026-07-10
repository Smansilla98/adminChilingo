import '../css/diseno-canvas.css';
import { Canvas, Rect, Circle, Textbox, Image as FabricImage } from 'fabric';

const BRAND_COLORS = ['#c1432b', '#d1a054', '#4a9a86', '#f3e9d8', '#1d160f', '#9c8ad1'];

function initDisenoEditor() {
    const root = document.getElementById('disenoApp');
    const form = document.getElementById('disenoForm');
    const canvasEl = document.getElementById('designCanvas');
    if (!root || !form || !canvasEl) return;

    let activeColor = BRAND_COLORS[0];
    let template = {
        w: parseInt(root.dataset.ancho, 10) || 1080,
        h: parseInt(root.dataset.alto, 10) || 1350,
        formato: root.dataset.formato || 'flyer_feed',
    };

    const canvas = new Canvas(canvasEl, { backgroundColor: '#f3e9d8', preserveObjectStacking: true });

    function fitCanvas() {
        const maxW = Math.min(560, window.innerWidth - 560);
        const scale = Math.min(maxW / template.w, 620 / template.h, 1);
        canvas.setDimensions({ width: template.w * scale, height: template.h * scale });
        canvas.setZoom(scale);
    }

    function syncHidden() {
        document.getElementById('disenoFormato').value = template.formato;
        document.getElementById('disenoAncho').value = String(template.w);
        document.getElementById('disenoAlto').value = String(template.h);
        document.getElementById('disenoCanvasJson').value = JSON.stringify(canvas.toJSON());
        const mult = template.w / canvas.getWidth();
        document.getElementById('disenoPreviewBase64').value = canvas.toDataURL({
            format: 'png',
            quality: 1,
            multiplier: mult,
        });
    }

    function refreshLayers() {
        const list = document.getElementById('disenoLayerList');
        const objs = canvas.getObjects().slice().reverse();
        const active = canvas.getActiveObject();
        if (!objs.length) {
            list.innerHTML = '<p class="diseno-hint">Todavía no agregaste elementos.</p>';
            return;
        }
        list.innerHTML = objs.map((o) => {
            const idx = canvas.getObjects().indexOf(o);
            const sel = o === active ? 'selected' : '';
            const label = o.type === 'textbox' ? (o.text || 'Texto').slice(0, 22) : (o.type === 'circle' ? 'Círculo' : (o.type === 'image' ? 'Imagen' : 'Rectángulo'));
            return `<button type="button" class="diseno-layer-item ${sel}" data-idx="${idx}">${label}</button>`;
        }).join('');
        list.querySelectorAll('.diseno-layer-item').forEach((el) => {
            el.addEventListener('click', () => {
                const obj = canvas.getObjects()[parseInt(el.dataset.idx, 10)];
                canvas.setActiveObject(obj);
                canvas.requestRenderAll();
            });
        });
    }

    function refreshProps() {
        refreshLayers();
        const panel = document.getElementById('disenoPropsPanel');
        const obj = canvas.getActiveObject();
        if (!obj) {
            panel.innerHTML = '<h4>Propiedades</h4><p class="diseno-hint">Seleccioná un elemento para editarlo.</p>';
            return;
        }
        const isText = obj.type === 'textbox';
        panel.innerHTML = `
            <h4>Propiedades</h4>
            ${isText ? `<label class="diseno-field"><span>Texto</span><input type="text" id="propText" value="${(obj.text || '').replace(/"/g, '&quot;')}"></label>` : ''}
            <div class="diseno-field-row">
                <label class="diseno-field"><span>X</span><input type="number" id="propX" value="${Math.round(obj.left)}"></label>
                <label class="diseno-field"><span>Y</span><input type="number" id="propY" value="${Math.round(obj.top)}"></label>
            </div>
            ${isText ? `<label class="diseno-field"><span>Tamaño</span><input type="number" id="propSize" value="${Math.round(obj.fontSize)}"></label>` : ''}
        `;
        document.getElementById('propX')?.addEventListener('input', (e) => { obj.set('left', parseFloat(e.target.value) || 0); canvas.requestRenderAll(); });
        document.getElementById('propY')?.addEventListener('input', (e) => { obj.set('top', parseFloat(e.target.value) || 0); canvas.requestRenderAll(); });
        document.getElementById('propText')?.addEventListener('input', (e) => { obj.set('text', e.target.value); canvas.requestRenderAll(); refreshLayers(); });
        document.getElementById('propSize')?.addEventListener('input', (e) => { obj.set('fontSize', parseFloat(e.target.value) || 10); canvas.requestRenderAll(); });
    }

    function loadInitial() {
        const raw = root.dataset.canvasJson;
        if (raw && raw !== 'null') {
            try {
                const data = JSON.parse(raw);
                canvas.loadFromJSON(data, () => { canvas.requestRenderAll(); fitCanvas(); refreshLayers(); });
                return;
            } catch (e) { /* seed */ }
        }
        fitCanvas();
        refreshLayers();
    }

    const palette = document.getElementById('disenoPalette');
    palette.innerHTML = BRAND_COLORS.map((c, i) => `<button type="button" class="diseno-swatch ${i === 0 ? 'active' : ''}" style="background:${c}" data-color="${c}" aria-label="Color ${c}"></button>`).join('');
    palette.querySelectorAll('.diseno-swatch').forEach((sw) => {
        sw.addEventListener('click', () => {
            palette.querySelectorAll('.diseno-swatch').forEach((s) => s.classList.remove('active'));
            sw.classList.add('active');
            activeColor = sw.dataset.color;
            const obj = canvas.getActiveObject();
            if (obj) { obj.set('fill', activeColor); canvas.requestRenderAll(); refreshProps(); }
        });
    });

    document.querySelectorAll('.diseno-template-item').forEach((btn) => {
        if (btn.dataset.formato === template.formato) btn.classList.add('active');
        btn.addEventListener('click', () => {
            document.querySelectorAll('.diseno-template-item').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            template = { w: parseInt(btn.dataset.w, 10), h: parseInt(btn.dataset.h, 10), formato: btn.dataset.formato };
            canvas.clear();
            canvas.backgroundColor = '#f3e9d8';
            fitCanvas();
            refreshLayers();
        });
    });

    document.querySelector('[data-action="text"]')?.addEventListener('click', () => {
        const t = new Textbox('Nuevo texto', { left: template.w / 2 - 150, top: template.h / 2, width: 300, fontFamily: 'Inter, sans-serif', fontSize: 40, fill: activeColor });
        canvas.add(t); canvas.setActiveObject(t); canvas.requestRenderAll();
    });
    document.querySelector('[data-action="rect"]')?.addEventListener('click', () => {
        const r = new Rect({ left: template.w / 2 - 100, top: template.h / 2 - 70, width: 200, height: 140, fill: activeColor, rx: 10, ry: 10 });
        canvas.add(r); canvas.setActiveObject(r); canvas.requestRenderAll();
    });
    document.querySelector('[data-action="circle"]')?.addEventListener('click', () => {
        const c = new Circle({ left: template.w / 2 - 80, top: template.h / 2 - 80, radius: 80, fill: activeColor });
        canvas.add(c); canvas.setActiveObject(c); canvas.requestRenderAll();
    });
    document.querySelector('[data-action="delete"]')?.addEventListener('click', () => {
        const obj = canvas.getActiveObject();
        if (obj) { canvas.remove(obj); canvas.discardActiveObject(); canvas.requestRenderAll(); }
    });
    document.getElementById('disenoUndoBtn')?.addEventListener('click', () => {
        const objs = canvas.getObjects();
        if (objs.length) { canvas.remove(objs[objs.length - 1]); canvas.requestRenderAll(); }
    });
    document.getElementById('disenoExportBtn')?.addEventListener('click', () => {
        const mult = template.w / canvas.getWidth();
        const a = document.createElement('a');
        a.href = canvas.toDataURL({ format: 'png', quality: 1, multiplier: mult });
        a.download = 'diseno-ito.png';
        a.click();
    });
    document.getElementById('disenoImgInput')?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (f) => {
            FabricImage.fromURL(f.target.result).then((img) => {
                img.scaleToWidth(300);
                img.set({ left: template.w / 2 - 150, top: template.h / 2 - 150 });
                canvas.add(img); canvas.setActiveObject(img); canvas.requestRenderAll();
            });
        };
        reader.readAsDataURL(file);
    });
    document.querySelector('[data-action="image"]')?.addEventListener('click', () => document.getElementById('disenoImgInput')?.click());

    canvas.on('selection:created', refreshProps);
    canvas.on('selection:updated', refreshProps);
    canvas.on('selection:cleared', refreshProps);
    canvas.on('object:modified', refreshLayers);
    canvas.on('object:added', refreshLayers);
    canvas.on('object:removed', refreshLayers);

    form.addEventListener('submit', () => syncHidden());
    window.addEventListener('resize', fitCanvas);
    loadInitial();
}

document.addEventListener('DOMContentLoaded', initDisenoEditor);
