/**
 * Tests del modelo musical, mapeo de samples y sistemas de pentagrama.
 * No requiere DOM. Ejecutar: node --test tests/js/partitura-modelo.test.js
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
    TPQ, ticksDeCompas, tickAPosicion, eventosMusicales, duracionNegra,
    segundosDeTicks, crearPartitura, crearNota, ajustarVoz,
} from '../../resources/js/partitura/model.js';
import {
    MAPA_SAMPLES, nombreArchivoSample, sistemasVisuales, instrumentoPorId,
    GOLPES_POR_INSTRUMENTO, ARTICULACION_SAMPLE,
} from '../../resources/js/partitura/instruments.js';
import { BancoSamples } from '../../resources/js/partitura/samples.js';

describe('compás 4/4', () => {
    it('tiene 192 ticks (4 negras × TPQ 48)', () => {
        assert.equal(ticksDeCompas({ num: 4, den: 4 }), 192);
        assert.equal(TPQ, 48);
    });
    it('beat 1 subdivision 0 en tick 0; beat 3 en tick 96', () => {
        assert.deepEqual(tickAPosicion(0, { num: 4, den: 4 }), { beat: 1, subdivision: 0, ticksPorBeat: 48 });
        assert.equal(tickAPosicion(96, { num: 4, den: 4 }).beat, 3);
        assert.equal(tickAPosicion(48 + 12, { num: 4, den: 4 }).subdivision, 12);
    });
    it('duración de negra respeta el BPM', () => {
        assert.equal(duracionNegra(120), 0.5);
        assert.equal(segundosDeTicks(48, 120), 0.5);
        assert.equal(segundosDeTicks(192, 60), 4);
    });
});

describe('mapeo de samples 1:1', () => {
    const esperado = {
        surdo_grave: ['nota', 'chapa', 'tapado'],
        surdo_medio: ['nota', 'chapa', 'tapado'],
        surdo_agudo: ['nota', 'chapa', 'tapado'],
        redoblante: ['nota', 'acentuado', 'chapa'],
        timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo'],
        repique: ['nota', 'acentuado', 'chapa', 'agudo'],
    };
    Object.entries(esperado).forEach(([inst, strokes]) => {
        it(`${inst} tiene ${strokes.join(', ')}`, () => {
            assert.deepEqual(MAPA_SAMPLES[inst], strokes);
            strokes.forEach((s) => {
                const art = ARTICULACION_SAMPLE[s];
                const file = nombreArchivoSample(inst, s);
                assert.ok(art, `articulación de ${s}`);
                assert.equal(file, `${inst}_${art}`);
                assert.ok(!file.includes('midi'));
            });
        });
    });
    it('nota se publica como normal, no como MIDI', () => {
        assert.equal(nombreArchivoSample('redoblante', 'nota'), 'redoblante_normal');
        assert.equal(nombreArchivoSample('timbal', 'abierto'), 'timbal_abierto');
    });
    it('no hay fallback a otra articulación en el banco', () => {
        const b = new BancoSamples();
        assert.equal(b.obtener('surdo_medio', 'chapa'), null);
        assert.equal(b.obtener('surdo_grave', 'nota'), null);
    });
});

describe('golpes por instrumento (editor)', () => {
    it('surdos incluyen normal/chapa/tapado', () => {
        ['surdo_grave', 'surdo_medio', 'surdo_agudo'].forEach((id) => {
            assert.ok(GOLPES_POR_INSTRUMENTO[id].includes('nota'));
            assert.ok(GOLPES_POR_INSTRUMENTO[id].includes('chapa'));
            assert.ok(GOLPES_POR_INSTRUMENTO[id].includes('tapado'));
        });
    });
    it('timbal incluye abierto slap palma presionado dedo', () => {
        ['abierto', 'slap', 'palma', 'presionado', 'dedo'].forEach((g) => {
            assert.ok(GOLPES_POR_INSTRUMENTO.timbal.includes(g));
        });
    });
});

describe('Redoblante + Repique comparten pentagrama', () => {
    it('sistemasVisuales agrupa redo y repi', () => {
        const insts = ['redoblante', 'repique', 'timbal', 'surdo_grave'].map((id) => ({
            def: instrumentoPorId(id),
            cfg: { id, visible: true },
        }));
        const sis = sistemasVisuales(insts);
        const redo = sis.find((s) => s.id === 'redoblante+repique');
        assert.ok(redo, 'debe existir el sistema compartido');
        assert.equal(redo.label, 'Redoblante / Repique');
        assert.equal(redo.compartido, true);
        assert.equal(redo.members.length, 2);
        assert.equal(sis.filter((s) => s.id === 'redoblante' || s.id === 'repique').length, 0);
        assert.ok(sis.find((s) => s.id === 'timbal'));
        assert.ok(sis.find((s) => s.id === 'surdo_grave'));
    });
});

describe('eventosMusicales', () => {
    it('expone instrumento, articulación, compás, beat, subdivision, velocity', () => {
        const score = crearPartitura({ instrumentos: ['surdo_grave', 'redoblante'] });
        const cap = ticksDeCompas(score.timeSignature);
        score.sections[0].measures[0].voces.surdo_grave = ajustarVoz([
            crearNota({ dur: 'q', stroke: 'nota' }),
            crearNota({ dur: 'q', rest: true }),
            crearNota({ dur: 'q', stroke: 'chapa' }),
            crearNota({ dur: 'q', rest: true }),
        ], cap);
        const evs = eventosMusicales(score).filter((e) => e.instrument === 'surdo_grave');
        assert.ok(evs.length >= 2);
        const g = evs[0];
        assert.equal(g.instrument, 'surdo_grave');
        assert.equal(g.articulation, 'nota');
        assert.equal(g.measure, 1);
        assert.equal(g.beat, 1);
        assert.equal(g.subdivision, 0);
        assert.ok(g.velocity > 0);
        const chapa = evs.find((e) => e.articulation === 'chapa');
        assert.equal(chapa.beat, 3);
    });
});
