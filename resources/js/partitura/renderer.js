/**
 * Render VexFlow 4 — pentagramas de 5 líneas, clave de percusión, barras de compás.
 * Redoblante y Repique comparten un único sistema. Las voces de un mismo
 * compás se formatean juntas (Formatter.joinVoices) para alinear la coordenada X.
 */
import {
    Renderer, Stave, StaveNote, GhostNote, Beam, Tuplet, Formatter, Articulation,
    Barline, Volta, Annotation, Dot, Fraction, Voice,
} from 'vexflow';
import { instrumentoPorId, cabezaVexflow, GOLPES, esUnisono, sistemasVisuales } from './instruments.js';
import { TPQ, ticksDeNota } from './model.js';

const LABEL_W = 132;
const STAVE_H = 94;
const LINE_PAD_TOP = 28;
const LINE_PAD_BOTTOM = 22;
const MIN_MEASURE_W = 180;
const LINE_SPACING = 10;

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

function renderLinea(score, sec, si, idxs, instrumentos, anchoPagina, hits, measureBoxes) {
    const wrap = document.createElement('div');
    wrap.className = 'pt-line';
    wrap.dataset.section = String(si);

    const sistemas = sistemasVisuales(instrumentos);
    const width = anchoPagina - 12;
    const usable = width - LABEL_W - 24;
    const measureW = Math.max(MIN_MEASURE_W, Math.floor(usable / idxs.length));
    const height = LINE_PAD_TOP + sistemas.length * STAVE_H + LINE_PAD_BOTTOM;

    const renderer = new Renderer(wrap, Renderer.Backends.SVG);
    renderer.resize(width, height);
    const ctx = renderer.getContext();
    ctx.setFont('Times New Roman', 11, '');
    ctx.setFillStyle('#111');
    ctx.setStrokeStyle('#111');

    const ts = score.timeSignature || { num: 4, den: 4 };
    const voiceTime = { num_beats: ts.num, beat_value: ts.den };

    idxs.forEach((mi, k) => {
        const m = sec.measures[mi];
        const x = LABEL_W + k * measureW;
        const vocesFmt = [];
        const sistemasStave = [];

        sistemas.forEach((sis, di) => {
            const y = LINE_PAD_TOP + di * STAVE_H;
            if (k === 0) {
                ctx.save();
                ctx.setFont('Times New Roman', 12, 'italic');
                ctx.fillText(sis.label, 4, y + 48);
                ctx.restore();
            }

            const stave = new Stave(x, y, measureW, {
                num_lines: 5,
                spacing_between_lines_px: LINE_SPACING,
                space_above_staff_ln: 1.6,
                space_below_staff_ln: 1.4,
                fill_style: '#111',
            });
            stave.setStyle({ fillStyle: '#111', strokeStyle: '#111' });

            if (k === 0) {
                stave.addClef('percussion');
                if (ts.num === 4 && ts.den === 4) stave.addTimeSignature('C');
                else stave.addTimeSignature(`${ts.num}/${ts.den}`);
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
            sistemasStave.push({ sis, stave, di, y });

            sis.members.forEach((inst, vi) => {
                const vozData = m.voces[inst.def.id] || [];
                const stem = sis.compartido ? (vi === 0 ? 1 : -1) : 1;
                const pitch = inst.def.pitch || 'b/4';
                const built = [];
                const tuplets = [];
                let grupoActual = null;
                let grupoNotas = [];

                const tickables = (vozData.length ? vozData : [{ dur: 'w', rest: true, dots: 0, stroke: 'nota' }]).map((n, ni) => {
                    const vf = construirNota(n, pitch, stem);
                    built.push({ vf, data: n, idx: ni, instId: inst.def.id });
                    const gid = n.tuplet?.id || null;
                    if (gid !== grupoActual) {
                        if (grupoActual && grupoNotas.length > 1) tuplets.push(nuevoTuplet(grupoNotas));
                        grupoActual = gid;
                        grupoNotas = [];
                    }
                    if (gid) grupoNotas.push({ vf, data: n });
                    return vf;
                });
                if (grupoActual && grupoNotas.length > 1) tuplets.push(nuevoTuplet(grupoNotas));

                const voice = new Voice(voiceTime).setMode(Voice.Mode.SOFT);
                try {
                    voice.addTickables(tickables);
                } catch (e) {
                    console.warn('Partitura: voz no encaja en el compás', e);
                }
                voice.setStave(stave);
                vocesFmt.push({ voice, stave, built, tuplets, instId: inst.def.id });
            });
        });

        const voices = vocesFmt.map((v) => v.voice);
        if (voices.length) {
            try {
                const fmt = new Formatter();
                fmt.joinVoices(voices);
                const first = sistemasStave[0]?.stave;
                if (first) fmt.formatToStave(voices, first);
                else fmt.format(voices, measureW - (k === 0 ? 56 : 16));
            } catch (e) {
                console.warn('Partitura: formato conjunto falló', e);
            }
        }

        vocesFmt.forEach(({ voice, stave, built, tuplets, instId }) => {
            try {
                voice.draw(ctx, stave);
            } catch (e) {
                console.warn('Partitura: no se pudo dibujar la voz', e);
            }
            const vfNotas = built.map((b) => b.vf);
            const beams = Beam.generateBeams(vfNotas, {
                beam_rests: false,
                maintain_stem_directions: true,
                groups: gruposDeBeam(ts),
            });
            beams.forEach((b) => b.setContext(ctx).draw());
            tuplets.forEach((t) => t.setContext(ctx).draw());

            built.forEach(({ vf, data, idx }) => {
                const box = cajaDeNota(vf, stave);
                hits.push({
                    sectionIdx: si,
                    measureIdx: mi,
                    instId,
                    noteIdx: idx,
                    noteId: data.id,
                    rest: data.rest,
                    lineEl: wrap,
                    ...box,
                });
            });
        });

        const top = sistemasStave[0];
        if (top) {
            measureBoxes.push({
                sectionIdx: si,
                measureIdx: mi,
                lineEl: wrap,
                x: top.stave.getX(),
                y: LINE_PAD_TOP - 8,
                w: measureW,
                h: sistemas.length * STAVE_H + 8,
                sistemas: sistemas.map((s) => s.id),
            });
        }
    });

    return wrap;
}

function construirNota(n, pitch, stem) {
    const dur = n.dur + (n.rest ? 'r' : '');

    if (n.rest) {
        const rest = new StaveNote({ keys: [pitch], duration: dur, align_center: true, clef: 'percussion' });
        aplicarPuntillos(rest, n.dots);
        return rest;
    }

    const note = new StaveNote({
        keys: [cabezaVexflow(pitch, n.stroke)],
        duration: dur,
        stem_direction: stem || 1,
        clef: 'percussion',
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

function gruposDeBeam(ts) {
    if (ts.den === 8 && ts.num % 3 === 0) return [new Fraction(3, 8)];
    return [new Fraction(1, 4)];
}

function cajaDeNota(vf, stave) {
    try {
        const bb = vf.getBoundingBox();
        if (bb) return { x: bb.getX(), y: bb.getY(), w: Math.max(12, bb.getW()), h: Math.max(24, bb.getH()) };
    } catch { /* fallback */ }
    const x = typeof vf.getAbsoluteX === 'function' ? vf.getAbsoluteX() : stave.getX();
    return { x: x - 8, y: stave.getYForTopText(1), w: 16, h: 36 };
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export { LABEL_W, STAVE_H, TPQ, ticksDeNota, GhostNote, sistemasVisuales };
