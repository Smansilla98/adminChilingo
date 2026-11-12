/**
 * Sincronía grabación ↔ partitura (audio_ref). Ejecutar: node --test tests/js/partitura-sincro.test.js
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { crearPartitura, normalizarPartitura } from '../../resources/js/partitura/model.js';
import {
    ajustarInicio, posicionEnGrabacion, pulsoDeTiempo, tiempoDeCompas, tiempoDePulso, tempoDeGrabacion, ticksDePulso,
} from '../../resources/js/partitura/sincro.js';

function conRef(ref, extra = {}) {
    const s = crearPartitura();
    return normalizarPartitura({ ...s, ...extra, audio_ref: { path: 'partituras/audio/1-abcdefghijklmnop.mp3', ...ref } });
}

describe('audio_ref en el modelo', () => {
    it('se conserva al normalizar y rechaza rutas ajenas', () => {
        assert.equal(conRef({ offset_seg: 1.5 }).audio_ref.offset_seg, 1.5);
        assert.equal(normalizarPartitura({ ...crearPartitura(), audio_ref: { path: 'comprobantes/x.pdf' } }).audio_ref, undefined);
    });
});

describe('sin pulsos: inicio + tempo', () => {
    const s = conRef({ offset_seg: 2, tempo_origen: 120 });
    it('el primer tiempo cae en el inicio y un compás de 4/4 a 120 dura 2 s', () => {
        assert.equal(pulsoDeTiempo(s, 2), 0);
        assert.equal(tiempoDeCompas(s, 1), 4);
        const p = posicionEnGrabacion(s, 5);
        assert.equal(p.orden, 1);
        assert.equal(p.frac, 0.5);
        assert.equal(p.sectionIdx, 1);
    });
    it('antes del inicio la posición queda «antes»', () => {
        assert.equal(posicionEnGrabacion(s, 0.5).antes, true);
    });
});

describe('con pulsos: sigue la aceleración', () => {
    const beats = [1, 1.6, 2.15, 2.65, 3.1, 3.5];
    const s = conRef({ beats });
    it('interpola entre pulsos y es inversa', () => {
        assert.equal(pulsoDeTiempo(s, 1.6), 1);
        assert.ok(Math.abs(pulsoDeTiempo(s, 1.875) - 1.5) < 1e-9);
        for (const p of [0, 0.25, 2.5, 4.9, 7]) assert.ok(Math.abs(pulsoDeTiempo(s, tiempoDePulso(s, p)) - p) < 1e-9);
        assert.equal(tiempoDeCompas(s, 1), 3.1);
    });
    it('tempo medio de la grabación', () => {
        assert.equal(tempoDeGrabacion(s), 120);
    });
    it('ajustar inicio descarta la anacrusa', () => {
        const r = ajustarInicio(s.audio_ref, 2.1);
        assert.deepEqual(r.beats, [2.1, 2.65, 3.1, 3.5]);
        assert.equal(r.offset_seg, 2.1);
    });
});

describe('pulsos compuestos', () => {
    it('6/8 se cuenta en negras con puntillo', () => {
        assert.equal(ticksDePulso({ num: 6, den: 8 }), 72);
        assert.equal(ticksDePulso({ num: 4, den: 4 }), 48);
    });
});
