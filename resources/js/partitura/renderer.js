/**
 * Render de partitura en vista página (VexFlow 4).
 * Devuelve además un mapa de posiciones (hitmap) para selección/edición y playhead.
 */
import {
    Renderer, Stave, StaveNote, GhostNote, Beam, Tuplet, Formatter, Articulation,
    Barline, Volta, StaveConnector, Annotation, Dot, Fraction,
} from 'vexflow';
import { instrumentoPorId, cabezaVexflow, GOLPES } from './instruments.js';
import { TPQ, ticksDeNota } from './model.js';

const LABEL_W = 92;
const STAVE_H = 78;
const LINE_PAD_TOP = 44;
const LINE_PAD_BOTTOM = 34;
const MIN_MEASURE_W = 150;
// Cada instrumento tiene su propio pentagrama: notas y silencios sobre la 3ª línea.
const PITCH_LINEA = 'b/4';

/**
 * @param {HTMLElement} host
 * @param {object} score
 * @param {{ instrumentos?: string[], anchoPagina?: number, mostrarNombres?: boolean }} [opts]
 */
export function renderScore(host, score, opts = {}) {
    const instrumentos = (opts.instrumentos || score.instruments.filter((i) => i.visible !== false).map((i) => i.id))
        .map((id) => ({ cfg: score.instruments.find((c) => c.id === id), def: instrumentoPorId(id) }))
        .filter((x) => x.def);

    host.innerHTML = '';
    host.classList.add('pt-score');

    const hits = [];
    const measureBoxes = [];
    const anchoPagina = Math.max(560, opts.anchoPagina || host.clientWidth || 900);

    if (!instrumentos.length) {
        host.innerHTML = '<p class="pt-empty">No hay instrumentos visibles.</p>';
        return { hits, measureBoxes };
    }

    score.sections.forEach((sec, si) => {
        const secEl = document.createElement('section');
        secEl.className = 'pt-section';
        secEl.dataset.section = String(si);

        const head = document.createElement('header');
        head.className = 'pt-section-head';
        head.innerHTML = `<span class="pt-section-name">${escapeHtml(sec.name)}</span>${
            sec.repeatX > 1 ? `<span class="pt-section-rep">×${sec.repeatX}</span>` : ''
        }<span class="pt-section-meta">${sec.measures.length} compás${sec.measures.length === 1 ? '' : 'es'}</span>`;
        secEl.appendChild(head);

        // Reparto de compases por línea
        const porLinea = Math.max(1, Math.floor((anchoPagina - LABEL_W - 30) / MIN_MEASURE_W));
        for (let start = 0; start < sec.measures.length; start += porLinea) {
            const idxs = [];
            for (let k = start; k < Math.min(start + porLinea, sec.measures.length); k++) idxs.push(k);
            const lineEl = renderLinea(score, sec, si, idxs, instrumentos, anchoPagina, hits, measureBoxes);
            secEl.appendChild(lineEl);
        }

        host.appendChild(secEl);
    });

    return { hits, measureBoxes };
}

function renderLinea(score, sec, si, idxs, instrumentos, anchoPagina, hits, measureBoxes) {
    const wrap = document.createElement('div');
    wrap.className = 'pt-line';
    wrap.dataset.section = String(si);

    const width = anchoPagina - 12;
    const usable = width - LABEL_W - 24;
    const measureW = Math.max(MIN_MEASURE_W, Math.floor(usable / idxs.length));
    const height = LINE_PAD_TOP + instrumentos.length * STAVE_H + LINE_PAD_BOTTOM;

    const renderer = new Renderer(wrap, Renderer.Backends.SVG);
    renderer.resize(width, height);
    const ctx = renderer.getContext();
    ctx.setFont('Inter', 11, '600');

    const staves = [];

    instrumentos.forEach((inst, di) => {
        let x = LABEL_W;
        const y = LINE_PAD_TOP + di * STAVE_H - 14;

        ctx.save();
        ctx.setFont('Inter', 10, '600');
        ctx.setFillStyle('#6d5b45');
        ctx.fillText(inst.def.label, 6, y + 26);
        ctx.restore();

        idxs.forEach((mi, k) => {
            const m = sec.measures[mi];
            const stave = new Stave(x, y, measureW);
            if (k === 0) {
                stave.addClef('percussion');
                stave.addTimeSignature(`${score.timeSignature.num}/${score.timeSignature.den}`);
            }
            if (m.repeatBegin) stave.setBegBarType(Barline.type.REPEAT_BEGIN);
            if (m.repeatEnd) stave.setEndBarType(Barline.type.REPEAT_END);
            else if (mi === sec.measures.length - 1) stave.setEndBarType(Barline.type.END);

            if (di === 0 && m.ending) {
                stave.setVoltaType(Volta.type.BEGIN_END, `${m.ending}.`, 0);
            }
            if (di === 0 && m.texto) {
                stave.setText(m.texto, 3, { shift_y: -12, justification: 1 });
            }

            stave.setContext(ctx).draw();
            staves.push({ stave, di, mi });

            const voz = m.voces[inst.def.id] || [];
            const notas = [];
            const tuplets = [];
            let grupoActual = null;
            let grupoNotas = [];

            voz.forEach((n, ni) => {
                const vfNote = construirNota(n, inst.def);
                notas.push({ vf: vfNote, data: n, idx: ni });

                const gid = n.tuplet?.id || null;
                if (gid !== grupoActual) {
                    if (grupoActual && grupoNotas.length > 1) {
                        tuplets.push(nuevoTuplet(grupoNotas));
                    }
                    grupoActual = gid;
                    grupoNotas = [];
                }
                if (gid) grupoNotas.push({ vf: vfNote, data: n });
            });
            if (grupoActual && grupoNotas.length > 1) tuplets.push(nuevoTuplet(grupoNotas));

            const vfNotas = notas.map((n) => n.vf);
            try {
                Formatter.FormatAndDraw(ctx, stave, vfNotas, { auto_beam: false, align_rests: true });
            } catch (e) {
                // Compás inconsistente: no rompas todo el render
                console.warn('Partitura: compás no formateable', e);
            }

            const beams = Beam.generateBeams(vfNotas, {
                beam_rests: false,
                maintain_stem_directions: true,
                groups: gruposDeBeam(score.timeSignature),
            });
            beams.forEach((b) => b.setContext(ctx).draw());
            tuplets.forEach((t) => t.setContext(ctx).draw());

            // hitmap
            notas.forEach(({ vf, data, idx }) => {
                const box = cajaDeNota(vf, stave);
                hits.push({
                    sectionIdx: si,
                    measureIdx: mi,
                    instId: inst.def.id,
                    noteIdx: idx,
                    noteId: data.id,
                    rest: data.rest,
                    lineEl: wrap,
                    ...box,
                });
            });

            if (di === 0) {
                measureBoxes.push({
                    sectionIdx: si,
                    measureIdx: mi,
                    lineEl: wrap,
                    x: stave.getX(),
                    y: LINE_PAD_TOP - 20,
                    w: measureW,
                    h: instrumentos.length * STAVE_H + 8,
                });
            }

            x += measureW;
        });
    });

    // Corchete de sistema por línea
    const primeros = staves.filter((s) => s.mi === idxs[0]);
    if (primeros.length > 1) {
        const top = primeros[0].stave;
        const bottom = primeros[primeros.length - 1].stave;
        new StaveConnector(top, bottom).setType(StaveConnector.type.BRACKET).setContext(ctx).draw();
        new StaveConnector(top, bottom).setType(StaveConnector.type.SINGLE_LEFT).setContext(ctx).draw();
    }

    return wrap;
}

function construirNota(n, instDef) {
    const dur = n.dur + (n.rest ? 'r' : '');
    if (n.rest) {
        const rest = new StaveNote({ keys: [PITCH_LINEA], duration: dur, align_center: true });
        aplicarPuntillos(rest, n.dots);
        return rest;
    }

    const note = new StaveNote({
        keys: [cabezaVexflow(PITCH_LINEA, n.stroke)],
        duration: dur,
        stem_direction: 1,
    });
    aplicarPuntillos(note, n.dots);

    const golpe = GOLPES[n.stroke];
    if (golpe?.articulacion) {
        note.addModifier(new Articulation(golpe.articulacion).setPosition(golpe.pos));
    }
    if (n.stroke === 'flam') {
        note.addModifier(new Annotation('fl').setFont('Inter', 9, 'italic').setVerticalJustification(Annotation.VerticalJustify.TOP));
    }
    if (n.dyn) {
        note.addModifier(
            new Annotation(n.dyn).setFont('Times New Roman', 13, 'bold italic').setVerticalJustification(Annotation.VerticalJustify.BOTTOM)
        );
    }
    return note;
}

function aplicarPuntillos(note, dots) {
    for (let d = 0; d < (dots || 0); d++) {
        Dot.buildAndAttach([note], { all: true });
    }
}

function nuevoTuplet(grupo) {
    const first = grupo[0].data;
    return new Tuplet(
        grupo.map((g) => g.vf),
        { num_notes: first.tuplet.num, notes_occupied: first.tuplet.den, ratioed: false, bracketed: true }
    );
}

function gruposDeBeam(ts) {
    // Agrupar por negra (o por corchea con puntillo en compases compuestos)
    if (ts.den === 8 && ts.num % 3 === 0) return [new Fraction(3, 8)];
    return [new Fraction(1, 4)];
}

function cajaDeNota(vf, stave) {
    try {
        const bb = vf.getBoundingBox();
        if (bb) return { x: bb.getX(), y: bb.getY(), w: Math.max(12, bb.getW()), h: Math.max(24, bb.getH()) };
    } catch (e) { /* fallback abajo */ }
    const x = typeof vf.getAbsoluteX === 'function' ? vf.getAbsoluteX() : stave.getX();
    return { x: x - 8, y: stave.getYForTopText(1), w: 16, h: 44 };
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export { LABEL_W, STAVE_H, TPQ, ticksDeNota, GhostNote };
