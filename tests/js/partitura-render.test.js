/**
 * Tests de render VexFlow: 5 líneas, barras de pentagrama, redo+repi juntos, alineación X.
 */
import { describe, it, before } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { crearPartitura, crearNota, ajustarVoz, ticksDeCompas } from '../../resources/js/partitura/model.js';
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
        assert.match(host.textContent, /Redoblante \/ Repique/);
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
        const compartido = (text.match(/Redoblante \/ Repique/g) || []).length;
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
});
