import '../css/programa-partitura.css';
import { initPartituraEditor, initPartituraViewers } from './programa/partitura-vexflow.js';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-partitura-editor]').forEach((root) => initPartituraEditor(root));
    initPartituraViewers();
});
