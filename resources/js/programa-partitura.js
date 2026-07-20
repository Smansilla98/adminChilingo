import '../css/programa-partitura.css';
import { initPartituraEditor, initPartituraViewers } from './programa/partitura-vexflow.js';
import { initDawEditor, initDidacticViewers } from './programa/partitura-daw.js';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-partitura-editor]').forEach((root) => {
        if (root.getAttribute('data-daw') === '1') {
            initDawEditor(root);
        } else {
            initPartituraEditor(root);
        }
    });
    initDidacticViewers();
    initPartituraViewers();
});
