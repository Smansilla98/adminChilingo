/**
 * Tests de render VexFlow: 5 líneas, barras de pentagrama, redo+repi juntos, alineación X.
 */
import { describe, it, before } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';
import { crearPartitura, crearNota, crearCompas, ajustarVoz, ticksDeCompas, ops, herramientaPorId } from '../../resources/js/partitura/model.js';
import { renderScore } from '../../resources/js/partitura/renderer.js';

describe('render VexFlow', () => {
    before(() => {
        const dom = new JSDOM('<!DOCTYPE html><div id="h"></div>', { pretendToBeVisual: true });
        global.window = dom.window;
        global.document = dom.window.document;
        global.HTMLElement = dom.window.HTMLElement;
        global.SVGElement = dom.window.SVGElement;
        if (!global.window.devicePixelRatio) global.window.devicePixelRatio = 1;
    });

    function scorePrueba() {
        const score = crearPartitura({ title: 'Prueba golpes', instrumentos: [
            'surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal',
        ] });
        score.tempo = 88;
        const cap = ticksDeCompas(score.timeSignature);
        const m = score.sections[0].measures[0];
        const q = (stroke) => crearNota({ dur: 'q', stroke });
        const rest = () => crearNota({ dur: 'q', rest: true });
        m.voces.surdo_grave = ajustarVoz([q('nota'), rest(), q('chapa'), q('tapado')], cap);
        m.voces.surdo_agudo = ajustarVoz([rest(), q('nota'), rest(), q('chapa')], cap);
        m.voces.surdo_medio = ajustarVoz([q('nota'), q('nota'), rest(), q('tapado')], cap);
        m.voces.redoblante = ajustarVoz([q('nota'), q('acentuado'), q('chapa'), rest()], cap);
        m.voces.repique = ajustarVoz([q('nota'), q('acentuado'), rest(), q('agudo')], cap);
        m.voces.timbal = ajustarVoz([
            crearNota({ dur: '8', stroke: 'abierto' }),
            crearNota({ dur: '8', stroke: 'slap' }),
            crearNota({ dur: '8', stroke: 'palma' }),
            crearNota({ dur: '8', stroke: 'presionado' }),
            crearNota({ dur: 'q', stroke: 'dedo' }),
            rest(),
        ], cap);
        return score;
    }

    it('dibuja pentagramas de 5 líneas y clave de percusión', () => {
        const host = document.getElementById('h');
        renderScore(host, scorePrueba(), { anchoPagina: 900 });
        const svg = host.querySelector('svg');
        assert.ok(svg, 'hay SVG');
        const staves = svg.querySelectorAll('g.vf-stave');
        assert.ok(staves.length >= 5, `sistemas: ${staves.length}`);
        staves.forEach((g) => {
            const paths = [...g.querySelectorAll('path')].filter((p) => {
                const d = p.getAttribute('d') || '';
                return /^M[\d.]+ [\d.]+L[\d.]+ \1$/.test(d.replace(/L([\d.]+) ([\d.]+)/, (m, x, y) => {
                    const y1 = d.split(' ')[1];
                    return `L${x} ${y}`;
                })) || /M[\d.]+ ([\d.]+)L[\d.]+ \1/.test(d);
            });
            const horiz = [...g.querySelectorAll('path')].filter((p) => {
                const d = p.getAttribute('d') || '';
                const m = d.match(/^M([\d.]+) ([\d.]+)L([\d.]+) ([\d.]+)$/);
                return m && m[2] === m[4];
            });
            assert.equal(horiz.length, 5, `5 líneas, obtuvo ${horiz.length} d=${[...g.querySelectorAll('path')].map((p) => p.getAttribute('d')).join('|')}`);
        });
        assert.ok(svg.querySelector('g.vf-clef'), 'clave de percusión');
        assert.match(host.textContent, /Redoblante y Repique/);
        assert.doesNotMatch(host.textContent, /Redoblante\n/);
    });

    it('Redoblante y Repique no generan dos pentagramas separados', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const score = scorePrueba();
        renderScore(host, score, { anchoPagina: 900 });
        const labels = [...host.querySelectorAll('svg')].map((s) => s.parentElement);
        const text = host.innerHTML;
        const redoSolo = (text.match(/>Redoblante</g) || []).length;
        const compartido = (text.match(/Redoblante y Repique/g) || []).length;
        assert.ok(compartido >= 1, 'etiqueta compartida');
        assert.equal(redoSolo, 0, 'no debe haber pentagrama solo de Redoblante');
    });

    it('alineación X: beat 1 de surdo y redoblante coinciden', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const score = scorePrueba();
        const { hits } = renderScore(host, score, { anchoPagina: 900 });
        const s1 = hits.find((h) => h.instId === 'surdo_grave' && h.noteIdx === 0 && !h.rest);
        const r1 = hits.find((h) => h.instId === 'redoblante' && h.noteIdx === 0 && !h.rest);
        assert.ok(s1 && r1, 'hay golpes en beat 1');
        assert.ok(Math.abs(s1.x - r1.x) < 8, `X surdo=${s1.x} redo=${r1.x}`);
    });

    it('barras de compás no superan la altura de 5 líneas (~40px + grosor)', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        renderScore(host, scorePrueba(), { anchoPagina: 900 });
        const bars = host.querySelectorAll('g.vf-stavebarline rect');
        assert.ok(bars.length > 0, 'hay barras');
        let maxH = 0;
        bars.forEach((r) => {
            const h = parseFloat(r.getAttribute('height') || '0');
            if (h > maxH) maxH = h;
        });
        // 5 líneas × 10px spacing = 40, más grosor de línea
        assert.ok(maxH <= 48, `barra más alta ${maxH}px (debe ≤ 48)`);
        assert.ok(maxH >= 35, `barra demasiado baja ${maxH}`);
    });

    it('genera beams de corchea (barras de agrupación)', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const { hits } = renderScore(host, scorePrueba(), { anchoPagina: 900 });
        const beams = host.querySelectorAll('g.vf-beam');
        assert.ok(beams.length >= 1, `beams: ${beams.length}`);
        hits.filter((h) => !h.rest && h.dur !== 'w').forEach((h) => {
            assert.equal(h.stem, -1, `${h.instId} plica abajo (Equivalencias)`);
        });
    });

    it('Equivalencias: plica abajo y barras de a 2 / 4 / 8 por tiempo', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const score = crearPartitura({ title: 'Equivalencias', instrumentos: ['surdo_grave'] });
        const cap = ticksDeCompas(score.timeSignature);
        const n = (dur) => crearNota({ dur, stroke: 'nota' });
        const sec = score.sections[0];
        sec.measures = [
            crearCompas(['surdo_grave'], score.timeSignature),
            crearCompas(['surdo_grave'], score.timeSignature),
            crearCompas(['surdo_grave'], score.timeSignature),
        ];
        sec.measures[0].voces.surdo_grave = ajustarVoz(Array.from({ length: 8 }, () => n('8')), cap);
        sec.measures[1].voces.surdo_grave = ajustarVoz(Array.from({ length: 16 }, () => n('16')), cap);
        sec.measures[2].voces.surdo_grave = ajustarVoz(Array.from({ length: 32 }, () => n('32')), cap);
        score.sections = [sec];

        const { hits } = renderScore(host, score, { anchoPagina: 1100 });
        const conPlica = hits.filter((h) => !h.rest && h.dur !== 'w');
        assert.ok(conPlica.length >= 8 + 16 + 32, `notas: ${conPlica.length}`);
        conPlica.forEach((h) => {
            assert.equal(h.stem, -1, `plica abajo dur=${h.dur}`);
        });
        const beams = host.querySelectorAll('g.vf-beam');
        assert.equal(beams.length, 12, `2 corcheas / 4 semis / 8 fusas × 4 tiempos = 12 barras, obtuvo ${beams.length}`);
    });

    it('Toque de Chilinga se dibuja y sigue dibujándose al agregar un compás', () => {
        const p = join(dirname(fileURLToPath(import.meta.url)), '../../database/data/partituras-v4/01-toque-de-chilinga.json');
        const score = JSON.parse(readFileSync(p, 'utf8'));
        const host = document.createElement('div');
        document.body.appendChild(host);

        for (const ancho of [560, 900, 1400]) {
            const { hits } = renderScore(host, score, { anchoPagina: ancho });
            assert.ok(host.querySelector('svg'), `SVG a ${ancho}px`);
            assert.ok(hits.length > 0, `hits a ${ancho}px`);
        }

        ops.agregarCompas(score, 0, 0);
        const { hits } = renderScore(host, score, { anchoPagina: 900 });
        assert.ok(host.querySelectorAll('svg').length >= 1, 'SVG después de + compás');
        assert.ok(hits.length > 0, 'hits después de + compás');
        assert.ok(host.querySelectorAll('g.vf-stave').length > 0, 'hay pentagramas');
    });

    it('partitura en blanco dibuja pentagramas (silencios clicables)', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const score = crearPartitura({ title: 'Vacía', instrumentos: ['surdo_grave', 'redoblante', 'repique'] });
        ops.vaciarPartitura(score);
        const { hits } = renderScore(host, score, { anchoPagina: 900, todasLasVoces: true });
        assert.ok(host.querySelector('svg'), 'hay SVG');
        assert.ok(host.querySelectorAll('g.vf-stave').length >= 2, 'hay pentagramas vacíos');
        assert.ok(hits.length > 0, 'los silencios se pueden seleccionar');
        assert.ok(hits.every((h) => h.rest), 'todo es silencio');
    });

    it('escribir figuras sobre partitura en blanco dibuja las notas', () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const score = crearPartitura({
            title: 'Vacía',
            instrumentos: ['surdo_grave', 'surdo_agudo', 'redoblante', 'repique', 'timbal'],
        });
        ops.vaciarPartitura(score);
        const sel = { sectionIdx: 0, measureIdx: 0, instId: 'surdo_grave', noteIdx: 0 };
        ops.aplicarHerramienta(score, sel, herramientaPorId('q'));
        const warns = [];
        const prev = console.warn;
        console.warn = (...a) => warns.push(a.map(String).join(' '));
        const { hits } = renderScore(host, score, { anchoPagina: 900, todasLasVoces: true });
        console.warn = prev;
        const golpes = hits.filter((h) => !h.rest);
        assert.ok(golpes.length >= 1, `hay golpes dibujados, obtuvo ${golpes.length}`);
        assert.equal(golpes[0].dur, 'q');
        assert.ok(host.querySelectorAll('g.vf-stavenote').length > 0, 'hay notas VexFlow');
        assert.equal(warns.filter((w) => /IncompleteVoice|UnformattedNote|NoTickContext/.test(w)).length, 0, warns.join('\n'));

        ops.aplicarHerramienta(score, sel, herramientaPorId('8x2'));
        const r2 = renderScore(host, score, { anchoPagina: 900, todasLasVoces: true });
        assert.equal(r2.hits.filter((h) => !h.rest && h.dur === '8').length, 2, '2 corcheas visibles');
        assert.ok(host.querySelectorAll('g.vf-beam').length >= 1, 'barra de 2 corcheas');
    });
});
