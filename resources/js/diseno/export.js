/**
 * Exportación multi-formato — PNG / JPG / PDF / SVG + perfiles DTF y sablón.
 * Extensible: agregá un handler en EXPORTERS sin tocar el diálogo.
 */
import { jsPDF } from 'jspdf';
import 'svg2pdf.js';
import { pixelesParaDpi, presetPorFormato } from './presets.js';

/** @typedef {'png'|'jpg'|'pdf'|'svg'} ExportFormat */
/** @typedef {'screen'|'dtf'|'sablon'} ExportProfile */

/**
 * Multiplicador de píxeles respecto al zoom de pantalla.
 * @param {import('fabric').Canvas} canvas
 * @param {number} targetW
 */
export function multiplierParaAncho(canvas, targetW) {
    const cw = canvas.getWidth() || 1;
    const m = targetW / cw;
    return m > 0 ? m : 1;
}

/**
 * Objetos visibles para export (omite mockups / excludeFromExport).
 * @param {import('fabric').Canvas} canvas
 */
function withExportVisibility(canvas, fn) {
    const hidden = [];
    canvas.getObjects().forEach((o) => {
        if (o.excludeFromExport || o._mockupOverlay) {
            hidden.push({ o, visible: o.visible });
            o.visible = false;
        }
    });
    canvas.requestRenderAll();
    try {
        return fn();
    } finally {
        hidden.forEach(({ o, visible }) => { o.visible = visible; });
        canvas.requestRenderAll();
    }
}

/**
 * Raster genérico.
 * @param {import('fabric').Canvas} canvas
 * @param {{ format: 'png'|'jpeg'|'webp', quality?: number, multiplier: number }} opts
 */
export function toRasterDataUrl(canvas, opts) {
    return withExportVisibility(canvas, () => canvas.toDataURL({
        format: opts.format,
        quality: opts.quality ?? 0.92,
        multiplier: opts.multiplier,
        enableRetinaScaling: false,
    }));
}

export function toSvgString(canvas) {
    return withExportVisibility(canvas, () => canvas.toSVG());
}

/**
 * @param {string} svg
 * @returns {SVGSVGElement|null}
 */
function parseSvgElement(svg) {
    const doc = new DOMParser().parseFromString(svg, 'image/svg+xml');
    const el = doc.documentElement;
    if (!el || el.tagName.toLowerCase() !== 'svg' || doc.querySelector('parsererror')) {
        return null;
    }
    return /** @type {SVGSVGElement} */ (el);
}

/**
 * PDF vectorial vía SVG (texto/formas). Si falla, cae a raster.
 */
export async function toVectorPdf(canvas, { target, title, base, dpi }) {
    const svg = toSvgString(canvas);
    const orient = target.w >= target.h ? 'landscape' : 'portrait';
    const pdf = new jsPDF({
        orientation: orient,
        unit: 'pt',
        format: [target.w, target.h],
        hotfixes: ['px_scaling'],
    });
    pdf.setProperties({ title: title || base });

    const svgEl = parseSvgElement(svg);
    if (svgEl && typeof pdf.svg === 'function') {
        try {
            if (!svgEl.getAttribute('width')) svgEl.setAttribute('width', String(target.w));
            if (!svgEl.getAttribute('height')) svgEl.setAttribute('height', String(target.h));
            await pdf.svg(svgEl, { x: 0, y: 0, width: target.w, height: target.h });
            pdf.save(`${base}-vector.pdf`);
            return { format: 'pdf', profile: 'vector', w: target.w, h: target.h, dpi };
        } catch (err) {
            console.warn('ITO Diseño: PDF vector falló, usando raster', err);
        }
    }

    const url = toRasterDataUrl(canvas, {
        format: 'png',
        quality: 1,
        multiplier: multiplierParaAncho(canvas, target.w),
    });
    pdf.addImage(url, 'PNG', 0, 0, target.w, target.h);
    pdf.save(`${base}-${dpi}dpi.pdf`);
    return { format: 'pdf', profile: 'raster-fallback', w: target.w, h: target.h, dpi };
}

/**
 * PNG 1-bit / alto contraste para serigrafía a un sablón.
 */
export async function toSablonDataUrl(canvas, multiplier, threshold = 140) {
    const src = toRasterDataUrl(canvas, { format: 'png', quality: 1, multiplier });
    const img = await loadImage(src);
    const c = document.createElement('canvas');
    c.width = img.width;
    c.height = img.height;
    const ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0);
    const data = ctx.getImageData(0, 0, c.width, c.height);
    const d = data.data;
    for (let i = 0; i < d.length; i += 4) {
        const y = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
        const v = y < threshold ? 0 : 255;
        d[i] = d[i + 1] = d[i + 2] = v;
        d[i + 3] = 255;
    }
    ctx.putImageData(data, 0, 0);
    return c.toDataURL('image/png');
}

/**
 * DTF: PNG con transparencia, resolución de impresión, espejo opcional.
 */
export async function toDtfDataUrl(canvas, multiplier, { mirror = false, transparent = true } = {}) {
    const bg = canvas.backgroundColor;
    if (transparent) {
        canvas.backgroundColor = null;
        canvas.requestRenderAll();
    }
    try {
        let url = toRasterDataUrl(canvas, { format: 'png', quality: 1, multiplier });
        if (mirror) {
            url = await mirrorDataUrl(url);
        }
        return url;
    } finally {
        if (transparent) {
            canvas.backgroundColor = bg;
            canvas.requestRenderAll();
        }
    }
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}

async function mirrorDataUrl(src) {
    const img = await loadImage(src);
    const c = document.createElement('canvas');
    c.width = img.width;
    c.height = img.height;
    const ctx = c.getContext('2d');
    ctx.translate(c.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(img, 0, 0);
    return c.toDataURL('image/png');
}

function downloadDataUrl(dataUrl, filename) {
    const a = document.createElement('a');
    a.href = dataUrl;
    a.download = filename;
    a.click();
}

function downloadBlob(blob, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

function safeName(title) {
    return (title || 'diseno-ito').replace(/[^\w\-áéíóúñÁÉÍÓÚÑ]+/gi, '-').replace(/-+/g, '-');
}

/**
 * API principal de export.
 */
export async function exportDesign(canvas, opts) {
    const {
        format,
        profile = 'screen',
        dpi = 150,
        quality = 0.92,
        transparent = false,
        mirror = false,
        threshold = 140,
        template,
        title = 'diseno-ito',
    } = opts;

    const preset = presetPorFormato(template.formato);
    const target = pixelesParaDpi(preset.mm, dpi, { w: template.w, h: template.h, dpiHint: preset.dpiHint || 72 });
    const mult = multiplierParaAncho(canvas, target.w);
    const base = safeName(title);

    if (profile === 'sablon' || (format === 'png' && profile === 'sablon')) {
        const url = await toSablonDataUrl(canvas, mult, threshold);
        downloadDataUrl(url, `${base}-sablon-${dpi}dpi.png`);
        return { format: 'png', profile: 'sablon', w: target.w, h: target.h, dpi };
    }

    if (profile === 'dtf') {
        const url = await toDtfDataUrl(canvas, mult, { mirror, transparent: true });
        downloadDataUrl(url, `${base}-dtf-${dpi}dpi${mirror ? '-mirror' : ''}.png`);
        return { format: 'png', profile: 'dtf', w: target.w, h: target.h, dpi };
    }

    const handler = EXPORTERS[format];
    if (!handler) throw new Error(`Formato no soportado: ${format}`);
    return handler({ canvas, mult, target, quality, transparent, base, dpi, title });
}

/** Registro extensible de formatos. */
export const EXPORTERS = {
    async png({ canvas, mult, target, transparent, base, dpi }) {
        const bg = canvas.backgroundColor;
        if (transparent) {
            canvas.backgroundColor = null;
            canvas.requestRenderAll();
        }
        try {
            const url = toRasterDataUrl(canvas, { format: 'png', quality: 1, multiplier: mult });
            downloadDataUrl(url, `${base}-${dpi}dpi.png`);
            return { format: 'png', profile: 'screen', w: target.w, h: target.h, dpi };
        } finally {
            if (transparent) {
                canvas.backgroundColor = bg;
                canvas.requestRenderAll();
            }
        }
    },
    async jpg({ canvas, mult, target, quality, base, dpi }) {
        const url = toRasterDataUrl(canvas, { format: 'jpeg', quality, multiplier: mult });
        downloadDataUrl(url, `${base}-${dpi}dpi.jpg`);
        return { format: 'jpg', profile: 'screen', w: target.w, h: target.h, dpi };
    },
    async webp({ canvas, mult, target, quality, base, dpi }) {
        const url = toRasterDataUrl(canvas, { format: 'webp', quality, multiplier: mult });
        const isWebp = url.startsWith('data:image/webp');
        downloadDataUrl(url, `${base}-${dpi}dpi.${isWebp ? 'webp' : 'png'}`);
        return { format: isWebp ? 'webp' : 'png', profile: 'screen', w: target.w, h: target.h, dpi };
    },
    async svg({ canvas, base, target, dpi }) {
        const svg = toSvgString(canvas);
        const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
        downloadBlob(blob, `${base}.svg`);
        return { format: 'svg', profile: 'screen', w: target.w, h: target.h, dpi };
    },
    async pdf({ canvas, target, base, dpi, title }) {
        return toVectorPdf(canvas, { target, title, base, dpi });
    },
};

export function ensureExportDialog() {
    let el = document.getElementById('disenoExportDialog');
    if (el) return el;
    el = document.createElement('dialog');
    el.id = 'disenoExportDialog';
    el.className = 'diseno-export-dialog';
    el.innerHTML = `
        <form method="dialog" class="diseno-export-form" id="disenoExportForm">
            <header class="diseno-export-head">
                <h2>Exportar diseño</h2>
                <button type="submit" value="cancel" class="diseno-btn diseno-btn-ghost" aria-label="Cerrar">✕</button>
            </header>
            <div class="diseno-export-body">
                <label class="diseno-field"><span>Formato</span>
                    <select name="format" id="exportFormat">
                        <option value="png">PNG</option>
                        <option value="jpg">JPG</option>
                        <option value="webp">WebP</option>
                        <option value="pdf">PDF (vector preferido)</option>
                        <option value="svg">SVG (vector)</option>
                    </select>
                </label>
                <label class="diseno-field"><span>Resolución (DPI)</span>
                    <select name="dpi" id="exportDpi">
                        <option value="72">72 — pantalla</option>
                        <option value="150" selected>150 — print liviano</option>
                        <option value="300">300 — impresión / DTF</option>
                    </select>
                </label>
                <label class="diseno-field" id="exportQualityWrap"><span>Calidad JPG / WebP</span>
                    <input type="range" name="quality" id="exportQuality" min="50" max="100" value="92">
                </label>
                <label class="diseno-check"><input type="checkbox" name="transparent" id="exportTransparent"> Fondo transparente (PNG)</label>
                <fieldset class="diseno-export-profile">
                    <legend>Perfil de producción</legend>
                    <label class="diseno-check"><input type="radio" name="profile" value="screen" checked> Pantalla / redes</label>
                    <label class="diseno-check"><input type="radio" name="profile" value="dtf"> DTF (PNG 300 dpi, alfa, espejo opcional)</label>
                    <label class="diseno-check"><input type="radio" name="profile" value="sablon"> Serigrafía 1 sablón (B/N umbral)</label>
                </fieldset>
                <label class="diseno-check" id="exportMirrorWrap" hidden><input type="checkbox" name="mirror" id="exportMirror"> Espejo horizontal (DTF)</label>
                <p class="diseno-hint" id="exportHint">PDF intenta vector (texto/formas); si el SVG no se puede, usa raster.</p>
            </div>
            <footer class="diseno-export-foot">
                <button type="submit" value="cancel" class="diseno-btn diseno-btn-ghost">Cancelar</button>
                <button type="submit" value="ok" class="diseno-btn diseno-btn-primary">Exportar</button>
            </footer>
        </form>
    `;
    document.body.appendChild(el);

    const syncUi = () => {
        const fmt = el.querySelector('#exportFormat')?.value;
        const profile = el.querySelector('input[name="profile"]:checked')?.value;
        el.querySelector('#exportQualityWrap').hidden = fmt !== 'jpg' && fmt !== 'webp';
        el.querySelector('#exportMirrorWrap').hidden = profile !== 'dtf';
        const dpiSel = el.querySelector('#exportDpi');
        if (profile === 'dtf' || profile === 'sablon') dpiSel.value = '300';
        if (profile === 'dtf' || profile === 'sablon') {
            el.querySelector('#exportFormat').value = 'png';
        }
    };
    el.addEventListener('change', syncUi);
    return el;
}

export function openExportDialog() {
    const dialog = ensureExportDialog();
    return new Promise((resolve) => {
        const onClose = () => {
            dialog.removeEventListener('close', onClose);
            if (dialog.returnValue !== 'ok') {
                resolve(null);
                return;
            }
            const fd = new FormData(dialog.querySelector('#disenoExportForm'));
            resolve({
                format: String(fd.get('format') || 'png'),
                dpi: parseInt(String(fd.get('dpi') || '150'), 10),
                quality: (parseInt(String(fd.get('quality') || '92'), 10) || 92) / 100,
                transparent: fd.get('transparent') === 'on',
                mirror: fd.get('mirror') === 'on',
                profile: String(fd.get('profile') || 'screen'),
            });
        };
        dialog.addEventListener('close', onClose);
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else {
            dialog.setAttribute('open', '');
            onClose();
            resolve({
                format: 'png', dpi: 150, quality: 0.92, transparent: false, mirror: false, profile: 'screen',
            });
        }
    });
}
