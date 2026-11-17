/**
 * Entrenamiento de velocidad (presets y subida por repeticiones).
 * Ejecutar: node --test tests/js/partitura-entrenamiento.test.js
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
    PRESETS_DEFAULT, normalizarPreset, tempoDeVuelta, cargarPresets, guardarPresets,
} from '../../resources/js/partitura/entrenamiento.js';

describe('tempoDeVuelta', () => {
    const medio = PRESETS_DEFAULT.medio; // inicio 70 %, sube 5 % cada 2 vueltas, tope 100 %

    it('arranca al porcentaje de inicio del tempo del toque', () => {
        assert.equal(tempoDeVuelta(100, 0, medio), 70);
    });

    it('sube un escalón cada N repeticiones', () => {
        assert.equal(tempoDeVuelta(100, 1, medio), 70);
        assert.equal(tempoDeVuelta(100, 2, medio), 75);
        assert.equal(tempoDeVuelta(100, 4, medio), 80);
    });

    it('no pasa del tope', () => {
        assert.equal(tempoDeVuelta(100, 100, medio), 100);
        const alto = normalizarPreset({ inicio: 90, subida: 20, cada: 1, tope: 110 });
        assert.equal(tempoDeVuelta(100, 9, alto), 110);
    });

    it('respeta los límites de tempo de la partitura (30–260)', () => {
        assert.equal(tempoDeVuelta(20, 0, medio), 30);
        assert.equal(tempoDeVuelta(250, 50, normalizarPreset({ inicio: 100, subida: 20, cada: 1, tope: 150 })), 260);
    });

    it('con preset fuerte sube cada vuelta', () => {
        const fuerte = PRESETS_DEFAULT.fuerte;
        assert.equal(tempoDeVuelta(100, 0, fuerte), 60);
        assert.equal(tempoDeVuelta(100, 1, fuerte), 70);
        assert.equal(tempoDeVuelta(100, 2, fuerte), 80);
    });
});

describe('presets del usuario', () => {
    it('valores inválidos vuelven al de fábrica y los fuera de rango se acotan', () => {
        const p = normalizarPreset({ inicio: 'x', subida: 99, cada: 0, tope: 400 }, 'leve');
        assert.deepEqual(p, { inicio: PRESETS_DEFAULT.leve.inicio, subida: 20, cada: 1, tope: 150 });
    });

    it('se guardan y se leen del almacenamiento', () => {
        const datos = {};
        const storage = {
            getItem: (k) => datos[k] ?? null,
            setItem: (k, v) => { datos[k] = v; },
        };
        guardarPresets(storage, { ...PRESETS_DEFAULT, fuerte: { inicio: 50, subida: 3, cada: 4, tope: 120 } });
        const leidos = cargarPresets(storage);
        assert.deepEqual(leidos.fuerte, { inicio: 50, subida: 3, cada: 4, tope: 120 });
        assert.deepEqual(leidos.leve, PRESETS_DEFAULT.leve);
    });

    it('un JSON roto no rompe la carga', () => {
        const storage = { getItem: () => '{roto' };
        assert.deepEqual(cargarPresets(storage), PRESETS_DEFAULT);
    });
});
