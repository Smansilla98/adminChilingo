/**
 * Tests del modelo musical, mapeo de samples y sistemas de pentagrama.
 * No requiere DOM. Ejecutar: node --test tests/js/partitura-modelo.test.js
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
    TPQ, ticksDeCompas, tickAPosicion, eventosMusicales, duracionNegra,
    segundosDeTicks, crearPartitura, crearNota, ajustarVoz, ops, herramientaPorId,
    ticksDeVoz, ticksDeNota,
} from '../../resources/js/partitura/model.js';
import {
    MAPA_SAMPLES, nombreArchivoSample, sistemasVisuales, instrumentoPorId,
    GOLPES_POR_INSTRUMENTO, ARTICULACION_SAMPLE, vocesDeUnisono, midiDeGolpe,
} from '../../resources/js/partitura/instruments.js';
import { BancoSamples, resolverGolpe, golpesPaletaAudibles } from '../../resources/js/partitura/samples.js';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

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
        redoblante: ['nota', 'acentuado', 'chapa', 'agudo'],
        timbal: ['abierto', 'slap', 'palma', 'presionado', 'dedo'],
        repique: ['nota', 'acentuado', 'chapa', 'agudo'],
        agogo: ['nota', 'acentuado', 'tapado'],
        palmas: ['nota', 'acentuado'],
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
    it('obtener() no inventa buffers: sin precarga sigue vacío', () => {
        const b = new BancoSamples();
        assert.equal(b.obtener('surdo_medio', 'chapa'), null);
        assert.equal(b.obtener('surdo_grave', 'nota'), null);
    });
    it('cada golpe de paleta resuelve a un sample del catálogo', () => {
        golpesPaletaAudibles().forEach(({ instId, stroke, ok }) => {
            assert.equal(ok, true, `${instId} ${stroke} mudo`);
        });
        const surdoAcc = resolverGolpe('surdo_grave', 'acentuado');
        assert.equal(surdoAcc.strokeId, 'nota');
        assert.ok(surdoAcc.velMul > 1);
        const flam = resolverGolpe('redoblante', 'flam');
        assert.equal(flam.flam, true);
        assert.equal(flam.strokeId, 'nota');
        const tap = resolverGolpe('redoblante', 'tapado');
        assert.equal(tap.choke, true);
    });
    it('los 27 WAV del catálogo existen en public/sounds/perc', () => {
        const root = join(dirname(fileURLToPath(import.meta.url)), '../../public/sounds/perc');
        const wavs = existsSync(root) ? readdirSync(root).filter((f) => f.endsWith('.wav')) : [];
        Object.entries(MAPA_SAMPLES).forEach(([inst, strokes]) => {
            strokes.forEach((s) => {
                const file = `${nombreArchivoSample(inst, s)}.wav`;
                assert.ok(wavs.includes(file), `falta ${file}`);
            });
        });
        assert.equal(wavs.length, 27);
        assert.ok(wavs.includes('redoblante_agudo.wav'));
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
    it('redoblante incluye agudo (mismo triángulo que repique)', () => {
        assert.ok(GOLPES_POR_INSTRUMENTO.redoblante.includes('agudo'));
        assert.ok(MAPA_SAMPLES.redoblante.includes('agudo'));
    });
});

describe('Equivalencias (grabado)', () => {
    it('cabeza en la línea del medio y plica abajo', () => {
        ['todos', 'surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal'].forEach((id) => {
            const def = instrumentoPorId(id);
            assert.equal(def.pitch, 'b/4', id);
            assert.equal(def.stem, -1, id);
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
        assert.equal(redo.label, 'Redoblante y Repique');
        assert.equal(redo.compartido, true);
        assert.equal(redo.members.length, 2);
        assert.equal(sis.filter((s) => s.id === 'redoblante' || s.id === 'repique').length, 0);
        assert.ok(sis.find((s) => s.id === 'timbal'));
        assert.ok(sis.find((s) => s.id === 'surdo_grave'));
    });
    it('agrupar agudos-graves etiqueta llaman / responden', () => {
        const insts = ['redoblante', 'repique', 'surdo_grave', 'surdo_agudo', 'surdo_medio', 'timbal'].map((id) => ({
            def: instrumentoPorId(id),
            cfg: { id, visible: true },
        }));
        const sis = sistemasVisuales(insts, 'agudos-graves');
        const agudos = sis.find((s) => s.label === 'Agudos (llaman)');
        const graves = sis.find((s) => s.label === 'Graves (responden)');
        assert.ok(agudos);
        assert.equal(agudos.members.length, 2);
        assert.ok(graves);
        assert.equal(graves.members.map((m) => m.def.id).join(), 'surdo_grave');
        assert.equal(sis.filter((s) => s.id.startsWith('surdo_agudo') || s.id === 'surdo_medio').length, 0);
    });
});

describe('Todos (unísono)', () => {
    it('vocesDeUnisono expande Todos a los tambores reales', () => {
        const score = crearPartitura({
            instrumentos: ['todos', 'surdo_grave', 'redoblante', 'timbal'],
        });
        const dest = vocesDeUnisono(score);
        assert.deepEqual(dest, ['surdo_grave', 'redoblante', 'timbal']);
        assert.ok(!dest.includes('todos'));
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

describe('Toque de Chilinga — llamada agudos / graves', () => {
    it('tiene 3 compases y agrupa agudos que llaman / graves que responden', () => {
        const p = join(dirname(fileURLToPath(import.meta.url)), '../../database/data/partituras-v4/01-toque-de-chilinga.json');
        const score = JSON.parse(readFileSync(p, 'utf8'));
        const llamada = score.sections.find((s) => String(s.name).includes('LLAMADA INICIAL'));
        assert.ok(llamada);
        assert.equal(llamada.measures.length, 3);
        assert.equal(llamada.agrupar, 'agudos-graves');
        const m = llamada.measures[0];
        const redo = (m.voces.redoblante || []).filter((n) => !n.rest);
        const grave = (m.voces.surdo_grave || []).filter((n) => !n.rest);
        assert.equal(redo.length, 3, 'ta-ca-tá');
        assert.equal(redo[2].stroke, 'acentuado', 'tá acentuada');
        assert.equal(grave.length, 2, 'pum pum');
    });
});

describe('PDF Toques — Oxosi agudo y source', () => {
    it('el Final conserva golpe agudo en redoblante (no se degrada a nota)', () => {
        const p = join(dirname(fileURLToPath(import.meta.url)), '../../database/data/partituras-v4/26-toque-a-oxosi.json');
        const score = JSON.parse(readFileSync(p, 'utf8'));
        const final = score.sections.find((s) => String(s.name).toUpperCase().includes('FINAL'));
        assert.ok(final, 'sección Final');
        const redo = (final.measures[0].voces.redoblante || []).filter((n) => !n.rest);
        assert.ok(redo.some((n) => n.stroke === 'agudo'), 'redoblante debe tener agudo');
        const repi = (final.measures[0].voces.repique || []).filter((n) => !n.rest);
        assert.ok(repi.some((n) => n.stroke === 'agudo'), 'repique debe tener agudo');
    });
    it('source apunta al PDF de Toques', () => {
        const p = join(dirname(fileURLToPath(import.meta.url)), '../../database/data/partituras-v4/26-toque-a-oxosi.json');
        const score = JSON.parse(readFileSync(p, 'utf8'));
        assert.equal(score.source?.type, 'pdf');
        assert.equal(score.source?.file, 'Toques_chilinga_compressed.pdf');
        assert.deepEqual(score.source?.pages, [58, 59, 60]);
    });
    it('resolverGolpe no sustituye redoblante+agudo por nota', () => {
        const r = resolverGolpe('redoblante', 'agudo');
        assert.equal(r.strokeId, 'agudo');
        assert.equal(r.instId, 'redoblante');
        assert.equal(nombreArchivoSample('redoblante', 'agudo'), 'redoblante_agudo');
        const root = join(dirname(fileURLToPath(import.meta.url)), '../../public/sounds/perc');
        const wavs = existsSync(root) ? readdirSync(root).filter((f) => f.endsWith('.wav')) : [];
        assert.equal(wavs.includes('redoblante_agudo.wav'), true, 'Oxosi necesita el WAV de agudo en redoblante');
    });
    it('midiDeGolpe distingue agudo de nota en redoblante', () => {
        assert.notEqual(midiDeGolpe('redoblante', 'agudo'), midiDeGolpe('redoblante', 'nota'));
        assert.equal(midiDeGolpe('redoblante', 'agudo'), midiDeGolpe('repique', 'agudo'));
    });
});

describe('herramientas de figura', () => {
    function selDe(score) {
        return { sectionIdx: 0, measureIdx: 0, instId: score.instruments[0].id, noteIdx: 0 };
    }

    it('vaciarPartitura deja una parte y un compás de silencios', () => {
        const score = crearPartitura({ title: 'X', instrumentos: ['surdo_grave', 'redoblante'] });
        score.sections[0].measures[0].voces.surdo_grave = [crearNota({ dur: 'q' })];
        ops.vaciarPartitura(score);
        assert.equal(score.title, 'X');
        assert.equal(score.sections.length, 1);
        assert.equal(score.sections[0].measures.length, 1);
        assert.equal(score.instruments.length, 2);
        const voz = score.sections[0].measures[0].voces.surdo_grave;
        assert.ok(voz.every((n) => n.rest));
        assert.equal(ticksDeVoz(voz), ticksDeCompas(score.timeSignature));
    });

    it('4 semicorcheas llenan 1 tiempo', () => {
        const score = crearPartitura({ instrumentos: ['surdo_grave'] });
        const sel = selDe(score);
        const n = ops.aplicarHerramienta(score, sel, herramientaPorId('16x4'));
        assert.equal(n, 4);
        const voz = score.sections[0].measures[0].voces.surdo_grave;
        const primeras = voz.filter((x) => !x.rest);
        assert.equal(primeras.length, 4);
        primeras.forEach((x) => assert.equal(x.dur, '16'));
        assert.equal(primeras.reduce((s, x) => s + ticksDeNota(x), 0), 48);
    });

    it('tresillo de corcheas ocupa 1 tiempo', () => {
        const score = crearPartitura({ instrumentos: ['surdo_grave'] });
        const sel = selDe(score);
        ops.aplicarHerramienta(score, sel, herramientaPorId('3:2-8'));
        const tres = score.sections[0].measures[0].voces.surdo_grave.filter((x) => x.tuplet);
        assert.equal(tres.length, 3);
        assert.equal(tres.reduce((s, x) => s + ticksDeNota(x), 0), 48);
    });
});

describe('guía del editor (tooltip)', () => {
    it('recorre cada zona con un paso y un título', async () => {
        const { PASOS_TOUR } = await import('../../resources/js/partitura/tour.js');
        assert.equal(PASOS_TOUR.length, 10);
        const ids = PASOS_TOUR.map((p) => p.id);
        assert.equal(new Set(ids).size, ids.length);
        PASOS_TOUR.forEach((p) => {
            assert.ok(p.titulo.length > 8, p.id);
            assert.match(p.html, /<ol>/);
        });
    });
});
