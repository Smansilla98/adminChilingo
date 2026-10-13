/**
 * Render VexFlow 4 — pentagramas de 5 líneas, clave de percusión, barras de compás.
 * Redoblante y Repique comparten un único sistema. Las voces de un mismo
 * compás se formatean juntas (Formatter.joinVoices) para alinear la coordenada X.
 *
 * Grabado según el cuadernillo: cabeza en el 2º espacio, plica arriba, barras
 * horizontales arriba (2 corcheas / 4 semis / 8 fusas por tiempo), acentos y
 * guiones debajo del pentagrama. También en Redo+Repi / Agudos.
 *
 * Orden VexFlow: notas → Voice → format → plica arriba → beams/tuplets →
 * stave.draw → voice.draw → beam.draw. Los beams van DESPUÉS del formateo
 * porque StaveNote.format llama setStemDirection y eso borra note.beam.
 */
import {
    Renderer, Stave, StaveNote, GhostNote, Beam, Tuplet, Formatter, Articulation,
    Barline, Volta, Annotation, Fraction, Voice, GraceNote, GraceNoteGroup, Parenthesis,
} from 'vexflow';
import { instrumentoPorId, cabezaVexflow, glifoDeCabeza, GOLPES, sistemasVisuales } from './instruments.js';
import { TPQ, ticksDeNota, ticksDeCompas } from './model.js';

const LABEL_W = 188;
const STAVE_H = 94;
const LINE_PAD_TOP = 28;
const LINE_PAD_BOTTOM = 22;
const MIN_MEASURE_W = 180;
const PX_POR_GOLPE = 27;
const PX_PRIMER_COMPAS = 64;
const LINE_SPACING = 10;
const FONT = 'Inter, system-ui, sans-serif';

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
        const instrumentos = opts.todasLasVoces
            ? todosLosInst
            : instrumentosDeSeccion(sec, todosLosInst);
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

        // Un compás de 16 semicorcheas no entra en el ancho mínimo: la cantidad de
        // compases por línea sale del compás más denso de la parte.
        const necesario = Math.max(MIN_MEASURE_W, ...sec.measures.map((m) => anchoDeCompas(m, instrumentos)));
        const porLinea = Math.max(1, Math.floor((anchoPagina - LABEL_W - 30 - PX_PRIMER_COMPAS) / necesario));
        for (let start = 0; start < sec.measures.length; start += porLinea) {
            const idxs = [];
            for (let k = start; k < Math.min(start + porLinea, sec.measures.length); k++) idxs.push(k);
            try {
                secEl.appendChild(renderLinea(score, sec, si, idxs, instrumentos, anchoPagina, hits, measureBoxes, necesario));
            } catch (e) {
                console.warn('Partitura: no se pudo dibujar una línea', e);
            }
        }

        host.appendChild(secEl);
    });

    return { hits, measureBoxes };
}

function instrumentosDeSeccion(sec, todos) {
    const conGolpes = todos.filter(({ def }) =>
        sec.measures.some((m) => (m.voces[def.id] || []).some((n) => !n.rest))
    );
    // Solo silencios (partitura en blanco): hay que dibujar el pentagrama igual.
    return conGolpes.length ? conGolpes : todos;
}

function renderLinea(score, sec, si, idxs, instrumentos, anchoPagina, hits, measureBoxes, necesario = MIN_MEASURE_W) {
    const wrap = document.createElement('div');
    wrap.className = 'pt-line';
    wrap.dataset.section = String(si);

    const sistemas = sistemasVisuales(instrumentos, sec.agrupar || null, { separarAgudos: !agudosIguales(sec) });
    // Pantalla angosta: si ni un compás entra, la línea se ensancha y se desplaza de costado.
    const width = Math.max(anchoPagina - 12, LABEL_W + 24 + PX_PRIMER_COMPAS + necesario * idxs.length);
    const usable = width - LABEL_W - 24;
    // El primer compás de la línea lleva clave y cifra de compás: se le da ese lugar de más.
    const anchoBase = Math.max(MIN_MEASURE_W, Math.floor((usable - PX_PRIMER_COMPAS) / idxs.length));
    const height = LINE_PAD_TOP + sistemas.length * STAVE_H + LINE_PAD_BOTTOM;

    const renderer = new Renderer(wrap, Renderer.Backends.SVG);
    renderer.resize(width, height);
    const ctx = renderer.getContext();
    ctx.setFont(FONT, 11, '');
    ctx.setFillStyle('#111');
    ctx.setStrokeStyle('#111');

    const ts = score.timeSignature || { num: 4, den: 4 };
    const voiceTime = { num_beats: ts.num, beat_value: ts.den };

    idxs.forEach((mi, k) => {
        try {
        const m = sec.measures[mi];
        if (!m) return;
        const measureW = anchoBase + (k === 0 ? PX_PRIMER_COMPAS : 0);
        const x = LABEL_W + k * anchoBase + (k === 0 ? 0 : PX_PRIMER_COMPAS);
        const vocesFmt = [];
        const sistemasStave = [];

        sistemas.forEach((sis, di) => {
            const y = LINE_PAD_TOP + di * STAVE_H;
            if (k === 0) {
                ctx.save();
                ctx.setFont(FONT, 12, '');
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
            else if (mi === sec.measures.length - 1) {
                // Cuadernillo: doble barra fina al cerrar cada parte; la gruesa sólo al final del toque.
                stave.setEndBarType(si === score.sections.length - 1 ? Barline.type.END : Barline.type.DOUBLE);
            }

            if (di === 0 && m.ending) {
                stave.setVoltaType(Volta.type.BEGIN_END, `${m.ending}.`, 0);
            }
            if (di === 0 && m.sena?.texto) {
                stave.setText(`✋ ${m.sena.texto}`, 3, { shift_y: m.texto ? -22 : -8, justification: 1 });
            }
            if (di === 0 && m.texto && !['Todos', 'Toque', 'Llamada intermedia'].includes(m.texto)) {
                stave.setText(m.texto, 3, { shift_y: -8, justification: 1 });
            }

            sistemasStave.push({ sis, stave, di, y });

            sis.members.forEach((inst, vi) => {
                const vozData = m.voces[inst.def.id] || [];
                const pitch = inst.def.pitch || 'a/4';
                const plica = inst.def.stem === -1 ? -1 : 1;
                const built = [];
                const tuplets = [];
                let grupoActual = null;
                let grupoNotas = [];

                const tickables = (vozData.length ? vozData : [{ dur: 'w', rest: true, dots: 0, stroke: 'nota' }]).map((n, ni) => {
                    let vf;
                    try {
                        vf = construirNota(n, pitch, ts, plica);
                    } catch (e) {
                        console.warn('Partitura: nota inválida, se dibuja como silencio', e);
                        vf = new StaveNote({ keys: [pitch], duration: '4r', clef: 'percussion' });
                    }
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

                const voice = vozConTickables(tickables, voiceTime);
                voice.setStave(stave);

                vocesFmt.push({ voice, stave, built, tuplets, beams: [], instId: inst.def.id, di, plica });
            });
        });

        const voices = vocesFmt.map((v) => v.voice);
        if (voices.length) {
            try {
                const fmt = new Formatter();
                // Cada pentagrama es un joinVoices aparte. Juntar Surdo + Redo + Timbal
                // como una sola voz múltiple explota al escribir (IncompleteVoice) y
                // el compás queda en blanco: el caso de «Partitura en blanco» + negra.
                sistemasStave.forEach((_, di) => {
                    const vs = vocesFmt.filter((v) => v.di === di).map((v) => v.voice);
                    if (vs.length) fmt.joinVoices(vs);
                });
                const first = sistemasStave[0]?.stave;
                const indent = first
                    ? Math.max(8, first.getNoteStartX() - first.getX())
                    : (k === 0 ? 56 : 12);
                const anchoUtil = Math.max(48, measureW - indent - 10);
                fmt.format(voices, anchoUtil);
            } catch (e) {
                console.warn('Partitura: formato conjunto falló', e);
                formatearVocesUnaAUna(vocesFmt, measureW, k === 0);
            }
        }

        // Format puede invertir plicas (voces juntas) y borrar beams con setStemDirection.
        // Cuadernillo: plica arriba en todas, barras después del formato.
        vocesFmt.forEach(({ built, plica }) => {
            built.forEach(({ vf, data }) => forzarPlica(vf, data, plica));
        });
        vocesFmt.forEach((entry) => {
            try {
                entry.beams = Beam.generateBeams(entry.built.map((b) => b.vf), {
                    beam_rests: false,
                    groups: gruposDeBeam(ts),
                    stem_direction: entry.plica,
                    flat_beams: true,
                });
            } catch (e) {
                console.warn('Partitura: no se pudieron agrupar las barras', e);
                entry.beams = [];
            }
        });

        sistemasStave.forEach(({ stave }) => {
            try {
                stave.setContext(ctx).draw();
            } catch (e) {
                console.warn('Partitura: no se pudo dibujar el pentagrama', e);
            }
        });

        vocesFmt.forEach(({ voice, stave, built, tuplets, beams, instId }) => {
            // Recién acá: los beams llaman reset() y rearman las cabezas con el glifo de la figura.
            built.forEach(({ vf, data }) => {
                const glifo = data.rest || data.dur === 'w' ? null : glifoDeCabeza(data.stroke);
                if (glifo) (vf.noteHeads || []).forEach((h) => { h.glyph_code = glifo; });
            });
            try {
                voice.draw(ctx, stave);
            } catch (e) {
                console.warn('Partitura: no se pudo dibujar la voz', e);
            }
            beams.forEach((b) => {
                try {
                    b.setContext(ctx).draw();
                } catch (e) {
                    console.warn('Partitura: no se pudo dibujar una barra', e);
                }
            });
            tuplets.forEach((t) => {
                try {
                    t.setContext(ctx).draw();
                } catch (e) {
                    console.warn('Partitura: no se pudo dibujar un tresillo', e);
                }
            });

            built.forEach(({ vf, data, idx }) => {
                let box;
                try {
                    box = cajaDeNota(vf, stave);
                } catch {
                    box = { x: stave.getX() + 8, y: stave.getYForTopText(1), w: 16, h: 36 };
                }
                hits.push({
                    sectionIdx: si,
                    measureIdx: mi,
                    instId,
                    noteIdx: idx,
                    noteId: data.id,
                    rest: data.rest,
                    dur: data.dur,
                    stem: direccionPlica(vf, data),
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
        } catch (e) {
            console.warn('Partitura: no se pudo dibujar el compás', mi, e);
        }
    });

    return wrap;
}

/** Ancho que pide un compás: un lugar por cada ataque distinto entre todas sus voces. */
function anchoDeCompas(m, instrumentos) {
    const ataques = new Set();
    instrumentos.forEach(({ def }) => {
        let t = 0;
        (m.voces[def.id] || []).forEach((n) => {
            ataques.add(Math.round(t));
            t += ticksDeNota(n);
        });
    });
    return ataques.size * PX_POR_GOLPE + 24;
}

/** Redoblante y repique tocan lo mismo en toda la parte (misma figura y mismo golpe). */
function agudosIguales(sec) {
    const firma = (voz) => (voz || []).map((n) => `${n.dur}.${n.dots || 0}.${n.rest ? 'r' : n.stroke}.${n.tuplet?.num || ''}`).join(' ');
    return sec.measures.every((m) => {
        const a = firma(m.voces.redoblante);
        const b = firma(m.voces.repique);
        return !a || !b || a === b;
    });
}

function vozConTickables(tickables, voiceTime) {
    const intentar = (mode) => {
        const voice = new Voice(voiceTime).setMode(mode);
        voice.addTickables(tickables);
        return voice;
    };
    try {
        return intentar(Voice.Mode.STRICT);
    } catch (e) {
        console.warn('Partitura: voz no encaja en el compás, se formatea en SOFT', e);
        try {
            return intentar(Voice.Mode.SOFT);
        } catch {
            return new Voice(voiceTime).setMode(Voice.Mode.SOFT);
        }
    }
}

function formatearVocesUnaAUna(vocesFmt, measureW, primerCompas) {
    vocesFmt.forEach(({ voice, stave }) => {
        try {
            const indent = stave
                ? Math.max(8, stave.getNoteStartX() - stave.getX())
                : (primerCompas ? 56 : 12);
            const anchoUtil = Math.max(48, measureW - indent - 10);
            new Formatter().joinVoices([voice]).format([voice], anchoUtil);
        } catch (e) {
            console.warn('Partitura: formato individual falló', e);
        }
    });
}

function construirNota(n, pitch, ts, plica = 1) {
    const dots = Math.min(2, Math.max(0, n.dots || 0));
    // VexFlow cuenta el puntillo en la duración (`qd`, `hdr`), no en el modificador Dot.
    const dur = n.dur + (dots === 2 ? 'dd' : dots === 1 ? 'd' : '') + (n.rest ? 'r' : '');

    if (n.rest) {
        const cap = ticksDeCompas(ts || { num: 4, den: 4 });
        const entero = ticksDeNota(n) >= cap;
        return new StaveNote({
            keys: [pitch],
            duration: dur,
            clef: 'percussion',
            ...(entero ? { align_center: true } : {}),
        });
    }

    const note = new StaveNote({
        keys: [cabezaVexflow(pitch, n.stroke)],
        duration: dur,
        stem_direction: plica,
        clef: 'percussion',
    });
    const golpe = GOLPES[n.stroke];
    if (golpe?.articulacion) {
        const pos = (golpe.articulacion === 'a>' || golpe.articulacion === 'a-') ? 4 : (golpe.pos || 4);
        const art = new Articulation(golpe.articulacion).setPosition(pos);
        // Cuadernillo: acentos y guiones siempre fuera del pentagrama, en una misma línea.
        if (art.articulation) art.articulation = { ...art.articulation, between_lines: false };
        note.addModifier(art);
    }
    if (n.stroke === 'flam') {
        try {
            const grace = new GraceNote({
                keys: [cabezaVexflow(pitch, 'nota')],
                duration: '16',
                slash: true,
                clef: 'percussion',
                stem_direction: plica,
            });
            note.addModifier(new GraceNoteGroup([grace], true));
        } catch {
            note.addModifier(
                new Annotation('fl')
                    .setFont(FONT, 9, 'italic')
                    .setVerticalJustification(Annotation.VerticalJustify.TOP)
            );
        }
    }
    if (golpe?.parentesis) {
        try { Parenthesis.buildAndAttach([note]); } catch { /* versión de VexFlow sin paréntesis */ }
    }
    if (n.digitacion === 'D' || n.digitacion === 'I') {
        note.addModifier(
            new Annotation(n.digitacion)
                .setFont(FONT, 11, 'bold')
                .setVerticalJustification(Annotation.VerticalJustify.BOTTOM)
        );
    }
    if (n.dyn) {
        note.addModifier(
            new Annotation(n.dyn)
                .setFont(FONT, 11, 'bold italic')
                .setVerticalJustification(Annotation.VerticalJustify.BOTTOM)
        );
    }
    return note;
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

/** Cuadernillo: plica arriba. No usar setStemDirection: borra el beam. */
function forzarPlica(vf, data, plica) {
    if (!vf || data.rest || data.dur === 'w') return;
    vf.stem_direction = plica;
    if (vf.stem && typeof vf.stem.setDirection === 'function') {
        vf.stem.setDirection(plica);
    }
}

function direccionPlica(vf, data) {
    if (!vf || data.rest || data.dur === 'w') return 0;
    try {
        if (typeof vf.getStemDirection === 'function') return vf.getStemDirection();
    } catch { /* sin plica */ }
    return typeof vf.stem_direction === 'number' ? vf.stem_direction : 1;
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
