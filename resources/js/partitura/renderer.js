/**
 * Render de partitura estilo Cuadernillo de Toques (VexFlow 4).
 * - Clave de percusión + C en 4/4
 * - Plicas arriba, barras planas por negra (4 semis / 2 corcheas)
 * - Altura de nota según instrumento (como en el PDF)
 * - Solo pentagramas que tocan en la sección
 * - Acentos debajo; digitación D/I debajo
 */
import {
    Renderer, Stave, StaveNote, GhostNote, Beam, Tuplet, Formatter, Articulation,
    Barline, Volta, StaveConnector, Annotation, Dot, Fraction,
} from 'vexflow';
import { instrumentoPorId, cabezaVexflow, GOLPES, esUnisono } from './instruments.js';
import { TPQ, ticksDeNota } from './model.js';

const LABEL_W = 108;
const STAVE_H = 72;
const LINE_PAD_TOP = 40;
const LINE_PAD_BOTTOM = 28;
const MIN_MEASURE_W = 160;

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

/** Solo instrumentos que tienen al menos un golpe (no silencio) en la sección. */
function instrumentosDeSeccion(sec, todos) {
    return todos.filter(({ def }) => {
        if (esUnisono(def.id)) {
            return sec.measures.some((m) => (m.voces[def.id] || []).some((n) => !n.rest));
        }
        return sec.measures.some((m) => (m.voces[def.id] || []).some((n) => !n.rest));
    });
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
    ctx.setFillStyle('#1a1410');
    ctx.setStrokeStyle('#1a1410');

    const staves = [];

    instrumentos.forEach((inst, di) => {
        let x = LABEL_W;
        const y = LINE_PAD_TOP + di * STAVE_H - 14;

        ctx.save();
        ctx.setFont('Times New Roman', 11, 'italic');
        ctx.setFillStyle('#1a1410');
        ctx.fillText(etiquetaInstrumento(inst.def), 4, y + 28);
        ctx.restore();

        idxs.forEach((mi, k) => {
            const m = sec.measures[mi];
            const stave = new Stave(x, y, measureW, {
                spacing_between_lines_px: 10,
                space_above_staff_ln: 1,
                space_below_staff_ln: 1,
            });

            if (k === 0) {
                stave.addClef('percussion');
                // Cuadernillo: C en 4/4
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
            if (di === 0 && m.texto && m.texto !== 'Todos' && m.texto !== 'Toque') {
                stave.setText(m.texto, 3, { shift_y: -10, justification: 1 });
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

            // Barras como el cuadernillo: por negra, sin atravesar silencios
            const beams = Beam.generateBeams(vfNotas, {
                beam_rests: false,
                maintain_stem_directions: true,
                flat_beams: true,
                flat_beam_offset: 10,
                groups: gruposDeBeam(score.timeSignature),
            });
            beams.forEach((b) => {
                b.setContext(ctx).draw();
            });
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
                    y: LINE_PAD_TOP - 18,
                    w: measureW,
                    h: instrumentos.length * STAVE_H + 6,
                });
            }

            x += measureW;
        });
    });

    const primeros = staves.filter((s) => s.mi === idxs[0]);
    if (primeros.length > 1) {
        const top = primeros[0].stave;
        const bottom = primeros[primeros.length - 1].stave;
        new StaveConnector(top, bottom).setType(StaveConnector.type.BRACKET).setContext(ctx).draw();
        new StaveConnector(top, bottom).setType(StaveConnector.type.SINGLE_LEFT).setContext(ctx).draw();
    }

    return wrap;
}

function etiquetaInstrumento(def) {
    if (esUnisono(def.id)) return 'Todos';
    return def.label;
}

/** Altura en el pentagrama: igual que el cuadernillo (grave abajo, agudo más arriba). */
function pitchDe(instDef) {
    return instDef?.pitch || 'b/4';
}

function construirNota(n, instDef) {
    const pitch = pitchDe(instDef);
    const dur = n.dur + (n.rest ? 'r' : '');

    if (n.rest) {
        const rest = new StaveNote({
            keys: [pitch],
            duration: dur,
            align_center: true,
        });
        aplicarPuntillos(rest, n.dots);
        return rest;
    }

    const note = new StaveNote({
        keys: [cabezaVexflow(pitch, n.stroke)],
        duration: dur,
        stem_direction: 1, // siempre arriba, como el cuadernillo
    });
    aplicarPuntillos(note, n.dots);

    const golpe = GOLPES[n.stroke];
    if (golpe?.articulacion) {
        // Acentos y tenutos debajo (como Redoblante del PDF)
        const pos = golpe.articulacion === 'a>' || golpe.articulacion === 'a-' ? 4 : (golpe.pos || 4);
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
                .setFont('Times New Roman', 12, 'bold')
                .setVerticalJustification(Annotation.VerticalJustify.BOTTOM)
        );
    }
    if (n.dyn) {
        note.addModifier(
            new Annotation(n.dyn)
                .setFont('Times New Roman', 12, 'bold italic')
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

function gruposDeBeam(ts) {
    // Una barra por negra → 4 semis o 2 corcheas, como el cuadernillo
    if (ts.den === 8 && ts.num % 3 === 0) return [new Fraction(3, 8)];
    return [new Fraction(1, 4)];
}

function cajaDeNota(vf, stave) {
    try {
        const bb = vf.getBoundingBox();
        if (bb) return { x: bb.getX(), y: bb.getY(), w: Math.max(12, bb.getW()), h: Math.max(24, bb.getH()) };
    } catch (e) { /* fallback */ }
    const x = typeof vf.getAbsoluteX === 'function' ? vf.getAbsoluteX() : stave.getX();
    return { x: x - 8, y: stave.getYForTopText(1), w: 16, h: 44 };
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export { LABEL_W, STAVE_H, TPQ, ticksDeNota, GhostNote };
