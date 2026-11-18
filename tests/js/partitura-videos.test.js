/**
 * Sincronizador de videos multipista (lógica pura). Ejecutar: node --test tests/js/partitura-videos.test.js
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
    relojMaestro, tiempoGrupo, tiempoEsperado, desfase, debeResincronizar,
    videoConAudio, disposicionGrilla, ajustarOffset, TOLERANCIA_S,
} from '../../resources/js/partitura/sincronizador-videos.js';

const videos = [
    { id: 'a', offset_seg: 0 },
    { id: 'b', offset_seg: 1.5 },
    { id: 'c', offset_seg: -0.5 },
];

describe('reloj maestro', () => {
    it('es el elegido o el primero', () => {
        assert.equal(relojMaestro(videos).id, 'a');
        assert.equal(relojMaestro(videos, 'c').id, 'c');
        assert.equal(relojMaestro(videos, 'nope').id, 'a');
        assert.equal(relojMaestro([]), null);
    });

    it('el tiempo del grupo suma el offset del maestro', () => {
        assert.equal(tiempoGrupo(videos[1], 10), 11.5);
    });
});

describe('sincronía', () => {
    it('cada video va en tiempo del grupo menos su offset', () => {
        assert.equal(tiempoEsperado(videos[1], 10), 8.5);
        assert.equal(tiempoEsperado(videos[2], 10), 10.5);
    });

    it('antes de que empiece el video queda en 0, y no pasa de su duración', () => {
        assert.equal(tiempoEsperado(videos[1], 1), 0);
        assert.equal(tiempoEsperado(videos[0], 30, 20), 20);
    });

    it('el desfase tiene signo y se resincroniza por encima de 0,1 s', () => {
        assert.equal(TOLERANCIA_S, 0.1);
        assert.equal(desfase(8.6, 8.5), 0.1);
        assert.equal(debeResincronizar(0.1), false, 'justo en el límite no se corrige');
        assert.equal(debeResincronizar(0.12), true);
        assert.equal(debeResincronizar(-0.2), true);
    });
});

describe('audio de un solo video', () => {
    it('suena el elegido como audio', () => {
        assert.equal(videoConAudio(videos, { audioId: 'b' }), 'b');
    });

    it('un «solo» gana sobre el audio elegido', () => {
        assert.equal(videoConAudio(videos, { audioId: 'a', solos: ['c'] }), 'c');
    });

    it('un video silenciado nunca suena; sin audio válido, nadie', () => {
        assert.equal(videoConAudio(videos, { audioId: 'a', mudos: ['a'] }), null);
        assert.equal(videoConAudio(videos, { audioId: 'a', solos: ['b'], mudos: ['b'] }), 'a');
        assert.equal(videoConAudio(videos, { audioId: 'zzz' }), null);
    });
});

describe('grilla', () => {
    it('se acomoda sola de 1 a 6 tiles', () => {
        assert.deepEqual(disposicionGrilla(1), { columnas: 1, filas: 1 });
        assert.deepEqual(disposicionGrilla(2), { columnas: 2, filas: 1 });
        assert.deepEqual(disposicionGrilla(3), { columnas: 2, filas: 2 });
        assert.deepEqual(disposicionGrilla(4), { columnas: 2, filas: 2 });
        assert.deepEqual(disposicionGrilla(6), { columnas: 3, filas: 2 });
        assert.deepEqual(disposicionGrilla(9), { columnas: 3, filas: 2 }, 'nunca más de 6');
        assert.deepEqual(disposicionGrilla(0), { columnas: 1, filas: 1 });
    });
});

describe('offset por video', () => {
    it('cada toque mueve 0,05 s y no pasa de ±10 s', () => {
        assert.equal(ajustarOffset(0, 1), 0.05);
        assert.equal(ajustarOffset(0.05, -1), 0);
        assert.equal(ajustarOffset(9.98, 5), 10);
        assert.equal(ajustarOffset(-10, -1), -10);
    });

    it('sin decimales basura al sumar', () => {
        let o = 0;
        for (let i = 0; i < 7; i += 1) o = ajustarOffset(o, 1);
        assert.equal(o, 0.35);
    });
});
