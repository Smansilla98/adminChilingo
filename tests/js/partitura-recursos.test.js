/**
 * Filtro de «Toques y recursos». Ejecutar: node --test tests/js/partitura-recursos.test.js
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { filtrarRecursos, tieneRecurso } from '../../resources/js/partitura/recursos-filtro.js';

const items = [
    { slug: 'a', anio: 1, videos: 3, partitura: 1, audio: 0 },
    { slug: 'b', anio: 2, videos: 0, partitura: 1, audio: 1 },
    { slug: 'c', anio: 2, videos: 2, partitura: 0, audio: 0 },
    { slug: 'd', anio: 3, videos: 0, partitura: 0, audio: 1 },
];

describe('tieneRecurso', () => {
    it('cada tipo mira su recurso', () => {
        assert.equal(tieneRecurso(items[0], 'videos'), true);
        assert.equal(tieneRecurso(items[1], 'videos'), false);
        assert.equal(tieneRecurso(items[2], 'partitura'), false);
        assert.equal(tieneRecurso(items[3], 'audio'), true);
    });

    it('sin tipo, todo cuenta', () => {
        assert.equal(tieneRecurso(items[3], ''), true);
    });

    it('acepta los valores como texto (así llegan del DOM)', () => {
        assert.equal(tieneRecurso({ videos: '2', partitura: '0', audio: '0' }, 'videos'), true);
        assert.equal(tieneRecurso({ videos: '0', partitura: '1', audio: '0' }, 'partitura'), true);
    });
});

describe('filtrarRecursos', () => {
    it('sin filtros devuelve todos', () => {
        assert.deepEqual(filtrarRecursos(items).map((i) => i.slug), ['a', 'b', 'c', 'd']);
    });

    it('por año', () => {
        assert.deepEqual(filtrarRecursos(items, { anio: '2' }).map((i) => i.slug), ['b', 'c']);
        assert.deepEqual(filtrarRecursos(items, { anio: 3 }).map((i) => i.slug), ['d']);
        assert.deepEqual(filtrarRecursos(items, { anio: '' }).length, 4);
    });

    it('por tipo de recurso', () => {
        assert.deepEqual(filtrarRecursos(items, { tipo: 'videos' }).map((i) => i.slug), ['a', 'c']);
        assert.deepEqual(filtrarRecursos(items, { tipo: 'audio' }).map((i) => i.slug), ['b', 'd']);
    });

    it('año y tipo juntos', () => {
        assert.deepEqual(filtrarRecursos(items, { anio: '2', tipo: 'partitura' }).map((i) => i.slug), ['b']);
        assert.deepEqual(filtrarRecursos(items, { anio: '1', tipo: 'audio' }), []);
    });

    it('no modifica la lista original', () => {
        const copia = JSON.stringify(items);
        filtrarRecursos(items, { anio: '2', tipo: 'videos' });
        assert.equal(JSON.stringify(items), copia);
    });
});
