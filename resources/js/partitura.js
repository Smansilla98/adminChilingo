/**
 * Entry del módulo de partituras (editor + visor).
 * Monta automáticamente cualquier [data-partitura-editor] o [data-partitura-viewer] del DOM.
 */
import '../css/partitura.css';
import { EditorPartitura } from './partitura/editor.js';
import { VisorPartitura } from './partitura/viewer.js';
import { ReproductorVideos } from './partitura/videos-multipista.js';
import { filtrarRecursos } from './partitura/recursos-filtro.js';

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

/** «Toques y recursos»: oculta las tarjetas que no cumplen el año y el tipo elegidos. */
function montarFiltroRecursos(el) {
    const tarjetas = [...el.querySelectorAll('[data-recurso]')];
    const items = tarjetas.map((t) => ({ ...t.dataset }));
    const aplicar = () => {
        const anio = el.querySelector('[data-filtro="anio"]')?.value || '';
        const tipo = el.querySelector('[data-filtro="tipo"]')?.value || '';
        const visibles = new Set(filtrarRecursos(items, { anio, tipo }));
        tarjetas.forEach((t, k) => { t.hidden = !visibles.has(items[k]); });
        const cuenta = el.querySelector('[data-filtro-cuenta]');
        if (cuenta) cuenta.textContent = `${visibles.size} ${visibles.size === 1 ? 'toque' : 'toques'}`;
        const vacio = el.querySelector('[data-filtro-vacio]');
        if (vacio) vacio.hidden = visibles.size > 0;
    };
    el.querySelectorAll('[data-filtro]').forEach((sel) => sel.addEventListener('change', aplicar));
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
            editorNombre: el.dataset.editorNombre || '',
            uploadRefUrl: el.dataset.uploadRefUrl || null,
            refUrl: el.dataset.refUrl || null,
            refTipo: el.dataset.refTipo || 'imagen',
            refNombre: el.dataset.refNombre || '',
            slug: el.dataset.slug || '',
            borradorUrl: el.dataset.borradorUrl || null,
            versionesUrl: el.dataset.versionesUrl || null,
            borrador: leerJson(el, 'data-borrador'),
            audioBase: el.dataset.audioBase || null,
            audioSubirUrl: el.dataset.audioSubirUrl || null,
            transcribir: el.dataset.transcribirUrl
                ? { url: el.dataset.transcribirUrl, habilitado: el.dataset.transcribirHabilitado === '1', mensaje: el.dataset.transcribirMensaje || '' }
                : null,
        });
        window.partituraEditor = editor;
    });

    document.querySelectorAll('[data-recursos-filtro]').forEach((el) => {
        if (el.dataset.ptMounted) return;
        el.dataset.ptMounted = '1';
        montarFiltroRecursos(el);
    });

    document.querySelectorAll('[data-videos-multipista]').forEach((el) => {
        if (el.dataset.ptMounted) return;
        el.dataset.ptMounted = '1';
        const videos = leerJson(el, 'data-videos') || [];
        if (videos.length) new ReproductorVideos(el, { videos });
    });

    document.querySelectorAll('[data-partitura-viewer]').forEach((el) => {
        if (el.dataset.ptMounted) return;
        el.dataset.ptMounted = '1';
        new VisorPartitura(el, {
            score: leerJson(el, 'data-score'),
            instrumento: el.dataset.instrumento || null,
            controles: el.dataset.controles !== '0',
            audioBase: el.dataset.audioBase || null,
            exportarMidi: el.dataset.exportarMidi || null,
            exportarXml: el.dataset.exportarXml || null,
        });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', montar);
else montar();

export { EditorPartitura, VisorPartitura };
