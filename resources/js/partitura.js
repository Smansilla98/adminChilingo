/**
 * Entry del módulo de partituras (editor + visor).
 * Monta automáticamente cualquier [data-partitura-editor] o [data-partitura-viewer] del DOM.
 */
import '../css/partitura.css';
import { EditorPartitura } from './partitura/editor.js';
import { VisorPartitura } from './partitura/viewer.js';

function leerJson(el, attr) {
    const raw = el.getAttribute(attr);
    if (!raw) return null;
    try {
        return JSON.parse(raw);
    } catch (e) {
        console.error('Partitura: JSON inválido en', attr, e);
        return null;
    }
}

function montar() {
    document.querySelectorAll('[data-partitura-editor]').forEach((el) => {
        if (el.dataset.ptMounted) return;
        el.dataset.ptMounted = '1';
        const editor = new EditorPartitura(el, {
            score: leerJson(el, 'data-score'),
            saveUrl: el.dataset.saveUrl || null,
            backUrl: el.dataset.backUrl || null,
            parteUrl: el.dataset.parteUrl || null,
            readonly: el.dataset.readonly === '1',
        });
        window.partituraEditor = editor;
    });

    document.querySelectorAll('[data-partitura-viewer]').forEach((el) => {
        if (el.dataset.ptMounted) return;
        el.dataset.ptMounted = '1';
        new VisorPartitura(el, {
            score: leerJson(el, 'data-score'),
            instrumento: el.dataset.instrumento || null,
            controles: el.dataset.controles !== '0',
        });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', montar);
else montar();

export { EditorPartitura, VisorPartitura };
