/**
 * MusicXML 4.0, conteo previo y round-trip de importación.
 */
import { describe, it, before } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { crearPartitura, crearNota, ajustarVoz, ticksDeCompas } from '../../resources/js/partitura/model.js';
import { generarMusicXML, calcularBeams } from '../../resources/js/partitura/exporters.js';
import { importarMusicXML } from '../../resources/js/partitura/importers.js';
import { MotorAudio } from '../../resources/js/partitura/audio.js';

describe('interop partituras', () => {
    before(() => {
        const dom = new JSDOM('<!DOCTYPE html>', { url: 'http://localhost' });
        global.window = dom.window;
        global.document = dom.window.document;
        global.DOMParser = dom.window.DOMParser;
        global.XMLSerializer = dom.window.XMLSerializer;
    });

    function scorePrueba() {
        const score = crearPartitura({
            title: 'Toque de Chilinga',
            instrumentos: ['todos', 'surdo_grave', 'redoblante', 'repique', 'timbal'],
        });
        score.tempo = 88;
        score.autor = 'La Chilinga';
        score.sections[0].name = 'LLAMADA INICIAL Y FINAL';
        score.sections[0].repeatX = 1;
        const cap = ticksDeCompas(score.timeSignature);
        const m = score.sections[0].measures[0];
        m.voces.redoblante = ajustarVoz([
            crearNota({ dur: '16', stroke: 'acentuado' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: 'q', rest: true }),
            crearNota({ dur: 'q', rest: true }),
            crearNota({ dur: 'q', rest: true }),
        ], cap);
        m.voces.repique = ajustarVoz([
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'agudo' }),
            crearNota({ dur: 'h', rest: true }),
        ], cap);
        m.voces.timbal = ajustarVoz([
            crearNota({ dur: '8', stroke: 'slap' }),
            crearNota({ dur: '8', stroke: 'palma' }),
            crearNota({ dur: 'h', rest: true }),
        ], cap);
        return score;
    }

    it('MusicXML 4.0: unpitched, clave de percusión, sistema Redo+Repi, beams y acento debajo', () => {
        const xml = generarMusicXML(scorePrueba());
        assert.match(xml, /score-partwise version="4.0"/);
        assert.match(xml, /<unpitched>/);
        assert.match(xml, /<clef><sign>percussion<\/sign>/);
        assert.match(xml, /Redoblante y Repique/);
        assert.match(xml, /<beam number="1">begin<\/beam>/);
        assert.match(xml, /<beam number="2">begin<\/beam>/);
        assert.match(xml, /accent placement="below"/);
        assert.match(xml, /<rehearsal>LLAMADA INICIAL Y FINAL<\/rehearsal>/);
        assert.match(xml, /midi-channel>10/);
        assert.doesNotMatch(xml, /version="3\.1"/);
    });

    it('calcularBeams agrupa cuatro semicorcheas en un tiempo', () => {
        const notas = [
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
            crearNota({ dur: '16', stroke: 'nota' }),
        ];
        const beams = calcularBeams(notas, { num: 4, den: 4 });
        assert.equal(beams[0][0].tipo, 'begin');
        assert.equal(beams[3][0].tipo, 'end');
        assert.equal(beams[1][1].number, 2);
    });

    it('importar MusicXML propio conserva instrumentos de la escuela y el tempo 80-90', () => {
        const orig = scorePrueba();
        const xml = generarMusicXML(orig);
        const back = importarMusicXML(xml);
        const ids = back.instruments.map((i) => i.id);
        assert.ok(ids.includes('redoblante'));
        assert.ok(ids.includes('repique'));
        assert.ok(ids.includes('timbal'));
        assert.equal(back.tempo, 88);
        assert.ok(back.sections.some((s) => /LLAMADA/i.test(s.name)));
        const redo = back.sections[0].measures[0].voces.redoblante || [];
        assert.ok(redo.some((n) => !n.rest && n.stroke === 'acentuado'));
        assert.ok(ids.every((id) => [
            'todos', 'surdo_grave', 'surdo_medio', 'surdo_agudo',
            'redoblante', 'repique', 'timbal', 'agogo', 'palmas',
        ].includes(id)));
    });

    it('conteo previo de un compás (4 clicks) y se puede apagar', () => {
        const motor = new MotorAudio();
        const score = scorePrueba();
        score.tempo = 80;
        const con = motor._planificar(score, {});
        assert.equal(con.countInSec, 3);
        assert.equal(con.eventos.filter((e) => e.countIn).length, 4);
        const notas = con.eventos.filter((e) => e.tipo === 'nota');
        assert.ok(notas.length && notas.every((e) => e.musicalSec >= 3 - 0.0001));
        const sin = motor._planificar(score, { countIn: false });
        assert.equal(sin.countInSec, 0);
        assert.equal(sin.eventos.filter((e) => e.countIn).length, 0);
    });
});
