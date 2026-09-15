/**
 * Guías de alineación / snapping estilo Canva.
 */
const THRESHOLD = 6;

/**
 * @param {import('fabric').Canvas} canvas
 * @param {import('fabric').FabricObject} obj
 * @param {{ w: number, h: number }} template
 */
export function snapObject(canvas, obj, template) {
    if (!obj || obj._mockupOverlay) return clearGuides(canvas);

    const bound = obj.getBoundingRect();
    const cx = bound.left + bound.width / 2;
    const cy = bound.top + bound.height / 2;
    const guides = [];
    let dx = 0;
    let dy = 0;

    const canvasGuides = [
        { type: 'v', pos: template.w / 2, kind: 'center' },
        { type: 'h', pos: template.h / 2, kind: 'center' },
        { type: 'v', pos: 0, kind: 'edge' },
        { type: 'v', pos: template.w, kind: 'edge' },
        { type: 'h', pos: 0, kind: 'edge' },
        { type: 'h', pos: template.h, kind: 'edge' },
    ];

    canvas.getObjects().forEach((other) => {
        if (other === obj || other._guide || other._mockupOverlay) return;
        const b = other.getBoundingRect();
        canvasGuides.push(
            { type: 'v', pos: b.left, kind: 'obj' },
            { type: 'v', pos: b.left + b.width / 2, kind: 'obj' },
            { type: 'v', pos: b.left + b.width, kind: 'obj' },
            { type: 'h', pos: b.top, kind: 'obj' },
            { type: 'h', pos: b.top + b.height / 2, kind: 'obj' },
            { type: 'h', pos: b.top + b.height, kind: 'obj' },
        );
    });

    const left = bound.left;
    const right = bound.left + bound.width;
    const top = bound.top;
    const bottom = bound.top + bound.height;

    canvasGuides.forEach((g) => {
        if (g.type === 'v') {
            const candidates = [
                { delta: g.pos - left, apply: g.pos - left },
                { delta: g.pos - cx, apply: g.pos - cx },
                { delta: g.pos - right, apply: g.pos - right },
            ];
            candidates.forEach((c) => {
                if (Math.abs(c.delta) <= THRESHOLD && (dx === 0 || Math.abs(c.delta) < Math.abs(dx))) {
                    dx = c.apply;
                    guides.push({ type: 'v', pos: g.pos });
                }
            });
        } else {
            const candidates = [
                { delta: g.pos - top, apply: g.pos - top },
                { delta: g.pos - cy, apply: g.pos - cy },
                { delta: g.pos - bottom, apply: g.pos - bottom },
            ];
            candidates.forEach((c) => {
                if (Math.abs(c.delta) <= THRESHOLD && (dy === 0 || Math.abs(c.delta) < Math.abs(dy))) {
                    dy = c.apply;
                    guides.push({ type: 'h', pos: g.pos });
                }
            });
        }
    });

    if (dx !== 0 || dy !== 0) {
        obj.set({
            left: (obj.left || 0) + dx,
            top: (obj.top || 0) + dy,
        });
        obj.setCoords();
    }

    drawGuides(canvas, guides, template);
}

export function clearGuides(canvas) {
    const stage = document.getElementById('disenoGuideOverlay');
    if (stage) stage.innerHTML = '';
    return null;
}

function drawGuides(canvas, guides, template) {
    let overlay = document.getElementById('disenoGuideOverlay');
    const frame = document.getElementById('disenoCanvasFrame');
    if (!frame) return;
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'disenoGuideOverlay';
        overlay.className = 'diseno-guide-overlay';
        frame.appendChild(overlay);
    }
    const zoom = canvas.getZoom() || 1;
    overlay.style.width = `${template.w * zoom}px`;
    overlay.style.height = `${template.h * zoom}px`;
    const uniq = [];
    guides.forEach((g) => {
        if (!uniq.some((u) => u.type === g.type && Math.abs(u.pos - g.pos) < 0.5)) uniq.push(g);
    });
    overlay.innerHTML = uniq.map((g) => {
        if (g.type === 'v') {
            return `<div class="diseno-guide diseno-guide-v" style="left:${g.pos * zoom}px"></div>`;
        }
        return `<div class="diseno-guide diseno-guide-h" style="top:${g.pos * zoom}px"></div>`;
    }).join('');
}
