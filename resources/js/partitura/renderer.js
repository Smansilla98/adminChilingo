/**
 * Render estilo Cuadernillo de Toques (VexFlow 4).
 * Igual al PDF: 1 línea, clave de percusión, C en 4/4, plicas arriba,
 * barras planas por tiempo (Equivalencias: 2 corcheas / 4 semis / 8 fusas),
 * acentos abajo. Solo voces que tocan en la sección.
 */
import {
    Renderer, Stave, StaveNote, GhostNote, Beam, Tuplet, Formatter, Articulation,
    Barline, Volta, Annotation, Dot, Fraction,
} from 'vexflow';
import { instrumentoPorId, cabezaVexflow, GOLPES, esUnisono } from './instruments.js';
import { TPQ, ticksDeNota } from './model.js';

const LABEL_W = 118;
const STAVE_H = 56;
const LINE_PAD_TOP = 36;
const LINE_PAD_BOTTOM = 24;
const MIN_MEASURE_W = 170;
/** Todas las notas sobre la línea central del pentagrama de 1 línea. */
const PITCH = 'b/4';

/**
 * @param {HTMLElement} host
 * @param {object} score
 * @param {{ instrumentos?: string[], anchoPagina?: number }} [opts]
 */
export function renderScore(host, score, opts = {}) {
    host.innerHTML = '';
    host.classList.add('pt-score');

    const hits = [];
    const measureBoxes = [];
    const anchoPagina = Math.max(560, opts.anchoPagina || host.clientWidth || 900);

    const todosLosInst = (opts.instrumentos || score.instruments.filter((i) => i.visible !== false).map((i) => i.id))
        .map((id) => ({ cfg: score.instruments.find((c) => c.id === id), def: instrumentoPorId(id) }))
        .filter((x) => x.def);

    if (!todosLosInst.length) {
        host.innerHTML = '<p class="pt-empty">No hay instrumentos visibles.</p>';
        return { hits, measureBoxes };
    }

    score.sections.forEach((sec, si) => {
        const instrumentos = instrumentosDeSeccion(sec, todosLosInst);
        if (!instrumentos.length) return;

        const secEl = document.createElement('section');
        secEl.className = 'pt-section';
        secEl.dataset.section = String(si);

        const head = document.createElement('header');
        head.className = 'pt-section-head';
        head.innerHTML = `<span class="pt-section-name">${escapeHtml(sec.name)}</span>${
            sec.repeatX > 1 ? `<span class="pt-section-rep">×${sec.repeatX}</span>` : ''
        }`;
        secEl.appendChild(head);

        const porLinea = Math.max(1, Math.floor((anchoPagina - LABEL_W - 30) / MIN_MEASURE_W));
        for (let start = 0; start < sec.measures.length; start += porLinea) {
            const idxs = [];
            for (let k = start; k < Math.min(start + porLinea, sec.measures.length); k++) idxs.push(k);
            secEl.appendChild(renderLinea(score, sec, si, idxs, instrumentos, anchoPagina, hits, measureBoxes));
        }

        host.appendChild(secEl);
    });

    return { hits, measureBoxes };
}

function instrumentosDeSeccion(sec, todos) {
    return todos.filter(({ def }) =>
        sec.measures.some((m) => (m.voces[def.id] || []).some((n) => !n.rest))
    );
}

function configurarUnaLinea(stave) {
    // Línea central visible (como el cuadernillo: pauta de una línea)
    stave.setConfigForLines([
        { visible: false },
        { visible: false },
        { visible: true },
        { visible: false },
        { visible: false },
    ]);
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
    ctx.setFont('Times New Roman', 11, '');
    ctx.setFillStyle('#111');
    ctx.setStrokeStyle('#111');

    const staves = [];

    instrumentos.forEach((inst, di) => {
        let x = LABEL_W;
        const y = LINE_PAD_TOP + di * STAVE_H - 10;

        ctx.save();
        ctx.setFont('Times New Roman', 11, 'italic');
        ctx.fillText(etiquetaInstrumento(inst.def), 4, y + 26);
        ctx.restore();

        idxs.forEach((mi, k) => {
            const m = sec.measures[mi];
            const stave = new Stave(x, y, measureW, {
                spacing_between_lines_px: 10,
                space_above_staff_ln: 1.2,
                space_below_staff_ln: 1.2,
            });
            configurarUnaLinea(stave);

            if (k === 0) {
                stave.addClef('percussion');
                if (score.timeSignature.num === 4 && score.timeSignature.den === 4) {
                    stave.addTimeSignature('C');
                } else {
                    stave.addTimeSignature(`${score.timeSignature.num}/${score.timeSignature.den}`);
                }
            }
            if (m.repeatBegin) stave.setBegBarType(Barline.type.REPEAT_BEGIN);
            if (m.repeatEnd) stave.setEndBarType(Barline.type.REPEAT_END);
            else if (mi === sec.measures.length - 1) stave.setEndBarType(Barline.type.END);

            if (di === 0 && m.ending) {
                stave.setVoltaType(Volta.type.BEGIN_END, `${m.ending}.`, 0);
            }
            if (di === 0 && m.texto && !['Todos', 'Toque', 'Llamada intermedia'].includes(m.texto)) {
                stave.setText(m.texto, 3, { shift_y: -8, justification: 1 });
            }

            stave.setContext(ctx).draw();
            staves.push({ stave, di, mi });

            const voz = m.voces[inst.def.id] || [];
            const notas = [];
            const tuplets = [];
            let grupoActual = null;
            let grupoNotas = [];

            voz.forEach((n, ni) => {
                const vfNote = construirNota(n);
                notas.push({ vf: vfNote, data: n, idx: ni });

                const gid = n.tuplet?.id || null;
                if (gid !== grupoActual) {
                    if (grupoActual && grupoNotas.length > 1) tuplets.push(nuevoTuplet(grupoNotas));
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
                console.warn('Partitura: compás no formateable', e);
            }

            const beams = Beam.generateBeams(vfNotas, {
                beam_rests: false,
                maintain_stem_directions: true,
                flat_beams: true,
                flat_beam_offset: 12,
                groups: gruposDeBeam(score.timeSignature),
            });
            beams.forEach((b) => b.setContext(ctx).draw());
            tuplets.forEach((t) => t.setContext(ctx).draw());

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
                    y: LINE_PAD_TOP - 14,
                    w: measureW,
                    h: instrumentos.length * STAVE_H + 4,
                });
            }

            x += measureW;
        });
    });

    // Sin bracket ni barra izquierda: el cuadernillo no los usa.
    return wrap;
}

function etiquetaInstrumento(def) {
    if (esUnisono(def.id)) return 'Todos';
    return def.label;
}

function construirNota(n) {
    const dur = n.dur + (n.rest ? 'r' : '');

    if (n.rest) {
        const rest = new StaveNote({ keys: [PITCH], duration: dur, align_center: true });
        aplicarPuntillos(rest, n.dots);
        return rest;
    }

    const note = new StaveNote({
        keys: [cabezaVexflow(PITCH, n.stroke)],
        duration: dur,
        stem_direction: 1,
    });
    aplicarPuntillos(note, n.dots);

    const golpe = GOLPES[n.stroke];
    if (golpe?.articulacion) {
        const pos = (golpe.articulacion === 'a>' || golpe.articulacion === 'a-') ? 4 : (golpe.pos || 4);
        note.addModifier(new Articulation(golpe.articulacion).setPosition(pos));
    }
    if (n.stroke === 'flam') {
        note.addModifier(
            new Annotation('fl')
                .setFont('Times New Roman', 9, 'italic')
                .setVerticalJustification(Annotation.VerticalJustify.TOP)
        );
    }
    if (n.digitacion === 'D' || n.digitacion === 'I') {
        note.addModifier(
            new Annotation(n.digitacion)
                .setFont('Times New Roman', 11, 'bold')
                .setVerticalJustification(Annotation.VerticalJustify.BOTTOM)
        );
    }
    if (n.dyn) {
        note.addModifier(
            new Annotation(n.dyn)
                .setFont('Times New Roman', 11, 'bold italic')
                .setVerticalJustification(Annotation.VerticalJustify.BOTTOM)
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

/** Barras por 1 tiempo (= negra), como en la hoja Equivalencias. */
function gruposDeBeam(ts) {
    if (ts.den === 8 && ts.num % 3 === 0) return [new Fraction(3, 8)];
    return [new Fraction(1, 4)];
}

function cajaDeNota(vf, stave) {
    try {
        const bb = vf.getBoundingBox();
        if (bb) return { x: bb.getX(), y: bb.getY(), w: Math.max(12, bb.getW()), h: Math.max(24, bb.getH()) };
    } catch (e) { /* fallback */ }
    const x = typeof vf.getAbsoluteX === 'function' ? vf.getAbsoluteX() : stave.getX();
    return { x: x - 8, y: stave.getYForTopText(1), w: 16, h: 36 };
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export { LABEL_W, STAVE_H, TPQ, ticksDeNota, GhostNote };
