/**
 * Editor de ritmos (docs/EDITOR_RITMOS.md): modelo v5, grilla, atajos, plan de audio,
 * práctica, MIDI y autoguardado. Sin DOM. Ejecutar: npm run test:partitura
 */
import { describe, it, mock } from 'node:test';
import assert from 'node:assert/strict';
import {
    crearPartitura, crearNota, ops, normalizarPartitura, ticksDeVoz, ticksDeCompas, velocidadDeNota, esAudible, eventosMusicales, TPQ,
} from '../../resources/js/partitura/model.js';
import {
    celdasDeVoz, ponerGolpe, quitarGolpe, alternarGolpe, etiquetasDePasos, pasosPorCompas, piezas, notaDePaso,
} from '../../resources/js/partitura/grilla.js';
import { RegistroAtajos, normalizarTecla } from '../../resources/js/partitura/atajos.js';
import { MotorAudio } from '../../resources/js/partitura/audio.js';
import { bpmDeToques, cuantizar, registrarToque } from '../../resources/js/partitura/practica.js';
import { leerMIDI, scoreDesdeMIDI, instrumentoDeMidi } from '../../resources/js/partitura/midi.js';
import { generarMIDI } from '../../resources/js/partitura/exporters.js';
import { EditorPartitura } from '../../resources/js/partitura/editor.js';

const SEMI = TPQ / 4;
const vacio = () => normalizarPartitura({
    tempo: 100,
    timeSignature: { num: 4, den: 4 },
    instruments: ['surdo_grave', 'repique'],
    sections: [{ name: 'Toque', measures: [{}, {}] }],
});
const golpes = (voz) => voz.filter((n) => !n.rest);

describe('modelo v5', () => {
    it('lee v4 y conserva los campos nuevos (fantasma, vel, pan, pitch, seña)', () => {
        const s = normalizarPartitura({
            version: 4,
            instruments: [{ id: 'repique', pan: 0.5, pitch: 3 }],
            sections: [{ measures: [{ sena: { texto: 'Entrada', tipo: 'entrada' }, voces: { repique: [{ dur: 'q', stroke: 'fantasma', vel: 40 }] } }] }],
        });
        assert.equal(s.version, 5);
        assert.equal(s.instruments[0].pan, 0.5);
        assert.equal(s.instruments[0].pitch, 3);
        const n = s.sections[0].measures[0].voces.repique[0];
        assert.equal(n.stroke, 'fantasma');
        assert.equal(n.vel, 40);
        assert.deepEqual(s.sections[0].measures[0].sena, { texto: 'Entrada', tipo: 'entrada', instrumento: null });
    });
    it('vel manda sobre golpe y dinámica; el fantasma suena más suave', () => {
        assert.equal(velocidadDeNota(crearNota({ stroke: 'nota', vel: 50 })), 0.5);
        assert.ok(velocidadDeNota(crearNota({ stroke: 'fantasma' })) < velocidadDeNota(crearNota({ stroke: 'nota' })));
    });
    it('insertar antes/después, repetir y pegar compases', () => {
        const s = vacio();
        s.sections[0].measures[0].voces.surdo_grave = ponerGolpe(s.sections[0].measures[0].voces.surdo_grave, 0, SEMI, 'surdo_grave');
        ops.insertarCompas(s, 0, 0, { antes: true });
        assert.equal(s.sections[0].measures.length, 3);
        assert.equal(golpes(s.sections[0].measures[1].voces.surdo_grave).length, 1, 'el patrón quedó en el compás 2');
        ops.repetirCompases(s, 0, 1, 1, 2);
        assert.equal(s.sections[0].measures.length, 5);
        assert.equal(golpes(s.sections[0].measures[3].voces.surdo_grave).length, 1);
        const clip = ops.copiarCompases(s, 0, 1, 1);
        ops.pegarCompases(s, 0, 4, clip);
        assert.equal(golpes(s.sections[0].measures[4].voces.surdo_grave).length, 1);
        assert.notEqual(s.sections[0].measures[4].id, s.sections[0].measures[1].id, 'ids nuevos al pegar');
    });
    it('pasoDinamica sube y baja por la escala', () => {
        const s = vacio();
        const m = s.sections[0].measures[0];
        m.voces.repique = ponerGolpe(m.voces.repique, 0, SEMI, 'repique');
        const sel = { sectionIdx: 0, measureIdx: 0, instId: 'repique', noteIdx: 0 };
        ops.pasoDinamica(s, sel, 1);
        assert.equal(m.voces.repique[0].dyn, 'f');
        ops.pasoDinamica(s, sel, -2);
        assert.equal(m.voces.repique[0].dyn, 'mp');
    });
});

describe('grilla', () => {
    it('cuenta 1 e & a en semicorcheas y 1 & en corcheas', () => {
        assert.deepEqual(etiquetasDePasos({ num: 4, den: 4 }, SEMI).slice(0, 5), ['1', 'e', '&', 'a', '2']);
        assert.deepEqual(etiquetasDePasos({ num: 4, den: 4 }, TPQ / 2).slice(0, 3), ['1', '&', '2']);
        assert.equal(pasosPorCompas({ num: 4, den: 4 }, SEMI), 16);
        assert.equal(pasosPorCompas({ num: 6, den: 8 }, SEMI), 12);
    });
    it('poner golpes A A S A: cada uno en su paso, el compás siempre completo', () => {
        const s = vacio();
        const m = s.sections[0].measures[0];
        [0, 1, 3].forEach((k) => { m.voces.surdo_grave = ponerGolpe(m.voces.surdo_grave, k, SEMI, 'surdo_grave'); });
        m.voces.repique = ponerGolpe(m.voces.repique, 2, SEMI, 'repique', { stroke: 'acentuado' });
        const cap = ticksDeCompas(s.timeSignature);
        const c = celdasDeVoz(m.voces.surdo_grave, cap, SEMI);
        assert.deepEqual(c.slice(0, 5).map((x) => x.tipo), ['golpe', 'golpe', 'vacio', 'golpe', 'vacio'], 'cada golpe dura un paso');
        assert.equal(celdasDeVoz(m.voces.repique, cap, SEMI)[2].nota.stroke, 'acentuado');
        assert.equal(ticksDeVoz(m.voces.surdo_grave), cap);
        assert.equal(ticksDeVoz(m.voces.repique), cap);
    });
    it('poner un golpe dentro de una nota larga la parte y conserva su golpe y dinámica', () => {
        const voz = [crearNota({ dur: 'h', stroke: 'acentuado', dyn: 'f' }), crearNota({ dur: 'h', rest: true })];
        const nueva = ponerGolpe(voz, 4, SEMI, 'repique');
        assert.equal(nueva[0].stroke, 'acentuado');
        assert.equal(nueva[0].dyn, 'f');
        assert.equal(ticksDeVoz(nueva), TPQ * 4);
        assert.equal(golpes(nueva).length, 2);
    });
    it('quitar deja silencio del mismo largo; alternar pone y saca', () => {
        let voz = ponerGolpe([crearNota({ dur: 'w', rest: true })], 8, SEMI, 'repique');
        voz = alternarGolpe(voz, 8, SEMI, 'repique');
        assert.equal(golpes(voz).length, 0);
        voz = alternarGolpe(voz, 8, SEMI, 'repique');
        assert.equal(golpes(voz).length, 1);
        assert.equal(quitarGolpe(voz, 3, SEMI), null, 'no hay golpe que sacar en el paso 3');
    });
    it('los tresillos quedan fuera de la grilla y no se tocan', () => {
        const s = vacio();
        const sel = { sectionIdx: 0, measureIdx: 0, instId: 'repique', noteIdx: 0 };
        ops.aplicarHerramienta(s, sel, { kind: 'tuplet', dur: '8', num: 3, den: 2 });
        const voz = s.sections[0].measures[0].voces.repique;
        const c = celdasDeVoz(voz, ticksDeCompas(s.timeSignature), SEMI);
        assert.equal(c[0].tipo, 'fuera');
        assert.equal(ponerGolpe(voz, 1, SEMI, 'repique'), null);
        assert.equal(notaDePaso(voz, 0, SEMI), 0);
    });
    it('piezas parte cualquier largo en figuras estándar', () => {
        assert.deepEqual(piezas(TPQ * 3 / 4).map((p) => `${p.dur}${p.dots ? '.' : ''}`), ['8.']);
        assert.equal(piezas(TPQ * 4 - SEMI).reduce((a, p) => a + p.t, 0), TPQ * 4 - SEMI);
    });
});

describe('atajos', () => {
    const fake = () => {
        const llamadas = [];
        const ed = new Proxy({ atajos: new RegistroAtajos(), entrada: false, score: { tempo: 88 }, audio: { stop() { llamadas.push('stop'); } } }, {
            get(t, k) {
                if (k in t) return t[k];
                return (...args) => llamadas.push([k, ...args]);
            },
        });
        EditorPartitura.prototype.registrarAtajos.call(ed);
        return { ed, llamadas };
    };
    it('normaliza teclas (Cmd = Ctrl, símbolos sin Shift)', () => {
        assert.equal(normalizarTecla({ key: 'z', ctrlKey: false, metaKey: true, shiftKey: true }), 'Ctrl+Shift+Z');
        assert.equal(normalizarTecla({ key: '?', shiftKey: true }), '?');
        assert.equal(normalizarTecla({ key: ' ' }), 'Space');
        assert.equal(normalizarTecla({ key: '¡', altKey: true, code: 'Digit1' }), 'Alt+1');
    });
    it('no hay conflictos en los atajos del editor', () => {
        assert.deepEqual(fake().ed.atajos.conflictos(), []);
    });
    it('la misma letra hace cosas distintas según el foco', () => {
        const { ed } = fake();
        const r = ed.atajos;
        assert.equal(r.resolver({ key: 'd' }, ['partitura', 'global']).id, 'dig-d');
        assert.equal(r.resolver({ key: 'd' }, ['grilla', 'global']).id, 'inst-3');
        assert.equal(r.resolver({ key: 'a' }, ['tocar', 'global']).id, 'inst-1');
        assert.equal(r.resolver({ key: 'a' }, ['partitura', 'global']), null);
        assert.equal(r.resolver({ key: '0' }, ['partitura', 'global']).id, 'silencio');
        assert.equal(r.resolver({ key: 'r' }, ['partitura', 'global']).id, 'repetir');
    });
    it('Space, N, L, M, Ctrl+Z y Ctrl+S ejecutan su acción', () => {
        const { ed, llamadas } = fake();
        const run = (ev, ctx = ['partitura', 'global']) => ed.atajos.resolver(ev, ctx).run(ev);
        run({ key: ' ' });
        run({ key: 'l' });
        run({ key: 'm' });
        run({ key: 'z', ctrlKey: true });
        run({ key: 's', ctrlKey: true });
        run({ key: 'n' });
        const nombres = llamadas.map((l) => (Array.isArray(l) ? l[0] : l));
        ['togglePlay', 'toggleLoop', 'toggleMetronomo', 'undo', 'guardar', 'aviso'].forEach((n) => assert.ok(nombres.includes(n), n));
        assert.equal(ed.entrada, true, 'N alterna la entrada continua');
    });
    it('personalizar avisa conflictos y no pisa', () => {
        const { ed } = fake();
        const r = ed.atajos;
        assert.equal(r.asignar('inst-1', 'S').ok, false, 'S ya es instrumento 2');
        assert.match(r.asignar('inst-1', 'Space').conflicto, /Reproducir/);
        assert.equal(r.asignar('inst-1', 'Z').ok, true);
        assert.deepEqual(r.exportar(), { 'inst-1': 'Z' });
        assert.equal(r.resolver({ key: 'z' }, ['tocar', 'global']).id, 'inst-1');
        const otra = fake().ed.atajos;
        otra.importar({ 'inst-1': 'Z' });
        assert.equal(otra.resolver({ key: 'z' }, ['grilla']).id, 'inst-1');
    });
    it('la ayuda se genera desde el registro', () => {
        const grupos = fake().ed.atajos.ayuda().map((g) => g.grupo);
        ['Escuchar', 'Editar', 'Partitura', 'Grilla', 'Tocar'].forEach((g) => assert.ok(grupos.includes(g), g));
    });
});

describe('plan de audio', () => {
    const conGolpes = () => {
        const s = vacio();
        const m = s.sections[0].measures;
        m[0].voces.surdo_grave = ponerGolpe(m[0].voces.surdo_grave, 4, SEMI, 'surdo_grave');
        m[1].voces.repique = ponerGolpe(m[1].voces.repique, 0, SEMI, 'repique');
        return s;
    };
    it('cada golpe genera un evento y el BPM cambia los tiempos', () => {
        const a = new MotorAudio();
        const s = conGolpes();
        const p100 = a._planificar(s, { countIn: false }).eventos.filter((e) => e.tipo === 'nota');
        assert.equal(p100.length, 2);
        assert.equal(p100[0].musicalSec, 0.6, 'tiempo 2 a 100 BPM');
        s.tempo = 50;
        assert.equal(a._planificar(s, { countIn: false }).eventos.find((e) => e.tipo === 'nota').musicalSec, 1.2);
    });
    it('el loop de un tramo solo programa esos compases', () => {
        const a = new MotorAudio();
        const p = a._planificar(conGolpes(), { countIn: false, rango: { sectionIdx: 0, desde: 1, hasta: 1 } });
        const notas = p.eventos.filter((e) => e.tipo === 'nota');
        assert.equal(notas.length, 1);
        assert.equal(notas[0].instrument, 'repique');
        assert.equal(notas[0].musicalSec, 0);
        assert.equal(p.measureStarts.length, 1);
    });
    it('mute y solo sacan eventos; cuenta previa de 2 compases', () => {
        const a = new MotorAudio();
        const s = conGolpes();
        s.instruments.find((i) => i.id === 'repique').solo = true;
        assert.ok(!esAudible(s, 'surdo_grave'));
        let p = a._planificar(s, { countIn: false });
        assert.deepEqual(p.eventos.filter((e) => e.tipo === 'nota').map((e) => e.instrument), ['repique']);
        p = a._planificar(conGolpes(), { countInCompases: 2 });
        assert.equal(p.countInSec, 4.8, '2 compases de 4/4 a 100 BPM');
        assert.equal(p.eventos.filter((e) => e.countIn).length, 8);
    });
    it('metrónomo con subdivisión en corcheas y acento opcional', () => {
        const a = new MotorAudio();
        a.metronomo = true;
        a.metroSub = 2;
        a.metroAcento = false;
        const clicks = a._planificar(conGolpes(), { countIn: false }).eventos.filter((e) => e.tipo === 'click');
        assert.equal(clicks.length, 16, '2 compases × 8 corcheas');
        assert.equal(clicks.filter((c) => c.fuerte).length, 0);
        assert.equal(clicks.filter((c) => c.sub).length, 8);
    });
});

describe('práctica', () => {
    it('tap tempo: mediana de los intervalos y reinicio tras una pausa', () => {
        let t = [];
        [0, 600, 1200, 1800, 2410].forEach((ms) => { t = registrarToque(t, ms); });
        assert.equal(bpmDeToques(t), 100);
        t = registrarToque(t, 9000);
        assert.equal(t.length, 1, 'una pausa larga empieza de nuevo');
        assert.equal(bpmDeToques([0, 100, 200], { max: 180 }), 180);
    });
    it('cuantiza lo grabado a la grilla y descarta repetidos', () => {
        const compases = [{ sectionIdx: 0, measureIdx: 0 }, { sectionIdx: 0, measureIdx: 1 }];
        const r = cuantizar([
            { seg: 0.02, instId: 'repique' },
            { seg: 0.31, instId: 'repique' },
            { seg: 0.30, instId: 'repique' },
            { seg: 2.41, instId: 'surdo_grave' },
        ], { bpm: 100, timeSignature: { num: 4, den: 4 }, paso: SEMI, compases });
        assert.deepEqual(r.map((x) => [x.measureIdx, x.paso, x.instId]), [[0, 0, 'repique'], [0, 2, 'repique'], [1, 0, 'surdo_grave']]);
    });
});

describe('MIDI', () => {
    it('exportar e importar conserva instrumentos, golpes y posiciones', () => {
        const s = vacio();
        const m = s.sections[0].measures[0];
        m.voces.surdo_grave = ponerGolpe(m.voces.surdo_grave, 0, SEMI, 'surdo_grave');
        m.voces.repique = ponerGolpe(m.voces.repique, 6, SEMI, 'repique', { stroke: 'agudo' });
        const bytes = generarMIDI(s);
        const leido = leerMIDI(bytes);
        assert.equal(leido.bpm, 100);
        assert.equal(leido.notas.length, 2);
        const vuelta = scoreDesdeMIDI(bytes, { preferidos: ['surdo_grave', 'repique'] });
        const ev = eventosMusicales(vuelta).map((e) => [e.instrument, e.tickLocal, e.articulation]);
        assert.deepEqual(ev, [['surdo_grave', 0, 'nota'], ['repique', 6 * SEMI, 'agudo']]);
        assert.deepEqual(instrumentoDeMidi(87), { instId: 'surdo_grave', stroke: 'nota' });
    });
    it('rechaza archivos que no son MIDI', () => {
        assert.throws(() => leerMIDI(new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8])), /No es un archivo MIDI/);
    });
});

describe('autoguardado', () => {
    it('una sola petición por ráfaga de cambios (debounce) y estado visible', async () => {
        mock.timers.enable({ apis: ['setTimeout'] });
        const pedidos = [];
        globalThis.fetch = async (url, op) => { pedidos.push({ url, body: JSON.parse(op.body) }); return { ok: true, json: async () => ({}) }; };
        globalThis.document = { querySelector: () => null };
        const ed = {
            readonly: false, borradorUrl: '/borrador', editorNombre: 'Lu', root: { dataset: {} }, score: crearPartitura(),
            estados: [], pintarStatus() { this.estados.push(this.estadoGuardado); },
        };
        ['programarAutoguardado', 'autoguardar'].forEach((k) => { ed[k] = EditorPartitura.prototype[k].bind(ed); });
        for (let i = 0; i < 5; i++) ed.programarAutoguardado();
        mock.timers.tick(2999);
        assert.equal(pedidos.length, 0);
        mock.timers.tick(1);
        await new Promise((r) => setImmediate(r));
        assert.equal(pedidos.length, 1);
        assert.equal(pedidos[0].body.editor_nombre, 'Lu');
        assert.equal(ed.estadoGuardado, 'borrador');
        assert.ok(ed.estados.includes('guardando'));
        mock.timers.reset();
        delete globalThis.fetch;
        delete globalThis.document;
    });
});
