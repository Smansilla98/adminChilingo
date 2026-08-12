/**
 * Vista de lectura de partituras v4: render, play/stop, zoom y exportación.
 * Se usa en la ficha pública del toque y en las partes por instrumento.
 */
import { normalizarPartitura, resumen } from './model.js';
import { instrumentoPorId } from './instruments.js';
import { renderScore } from './renderer.js';
import { MotorAudio } from './audio.js';
import { exportarPDF, exportarPNG } from './exporters.js';

export class VisorPartitura {
    /**
     * @param {HTMLElement} root
     * @param {{ score?: object, instrumento?: string|null, controles?: boolean, zoom?: number }} [opts]
     */
    constructor(root, opts = {}) {
        this.root = root;
        this.score = normalizarPartitura(opts.score);
        this.instrumento = opts.instrumento || null;
        this.controles = opts.controles !== false;
        this.zoom = opts.zoom || 1;
        this.audio = new MotorAudio();
        this.audio.onMeasure = (pos) => this.playhead(pos);
        this.audio.onStop = () => this.finTransporte();
        this.construir();
        this.render();
    }

    construir() {
        this.root.classList.add('pt-viewer');
        const r = resumen(this.score);
        const inst = this.instrumento ? instrumentoPorId(this.instrumento) : null;
        this.root.innerHTML = `
            <div class="pt-viewer-head">
                <div>
                    <h2 class="pt-viewer-title">${esc(this.score.title)}${inst ? ` — ${esc(inst.label)}` : ''}</h2>
                    <p class="pt-viewer-meta">${this.score.autor ? `${esc(this.score.autor)} · ` : ''}${this.score.tempo} BPM · ${this.score.timeSignature.num}/${this.score.timeSignature.den} · ${r.compases} compases</p>
                </div>
                ${this.controles
                    ? `<div class="pt-viewer-actions">
                        <button class="pt-btn pt-btn-play" data-a="play" title="Reproducir">▶</button>
                        <button class="pt-btn" data-a="stop" title="Detener">■</button>
                        <button class="pt-btn pt-toggle" data-a="metro" title="Metrónomo">𝅘𝅥</button>
                        <button class="pt-btn" data-a="zoom-out">−</button>
                        <button class="pt-btn" data-a="zoom-in">+</button>
                        <button class="pt-btn" data-a="pdf">PDF</button>
                        <button class="pt-btn" data-a="png">PNG</button>
                        <button class="pt-btn" data-a="print" title="Imprimir">⎙</button>
                    </div>`
                    : ''}
            </div>
            <div class="pt-page" data-zone="page"><div class="pt-canvas" data-zone="canvas"></div></div>
        `;
        this.canvas = this.root.querySelector('[data-zone="canvas"]');
        this.page = this.root.querySelector('[data-zone="page"]');
        this.root.addEventListener('click', (e) => this.onClick(e));
        window.addEventListener('resize', debounce(() => this.render(), 250));
    }

    render() {
        const instrumentos = this.instrumento
            ? [this.instrumento]
            : this.score.instruments.filter((i) => i.visible !== false).map((i) => i.id);
        const ancho = Math.max(560, Math.floor((this.root.clientWidth - 24) / this.zoom));
        const r = renderScore(this.canvas, this.score, { instrumentos, anchoPagina: ancho });
        this.measureBoxes = r.measureBoxes;
        this.page.style.transform = `scale(${this.zoom})`;
    }

    onClick(e) {
        const btn = e.target.closest('[data-a]');
        if (!btn) return;
        switch (btn.dataset.a) {
            case 'play':
                btn.classList.add('on');
                this.audio.play(this.score, {}).catch(() => this.finTransporte());
                return;
            case 'stop': return this.audio.stop();
            case 'metro': btn.classList.toggle('on'); this.audio.metronomo = btn.classList.contains('on'); return;
            case 'zoom-in': this.zoom = Math.min(2, this.zoom + 0.15); return this.render();
            case 'zoom-out': this.zoom = Math.max(0.5, this.zoom - 0.15); return this.render();
            case 'pdf': return exportarPDF(this.canvas, this.score).catch(() => {});
            case 'png': return exportarPNG(this.canvas, this.score).catch(() => {});
            case 'print': return window.print();
            default: return;
        }
    }

    playhead(pos) {
        this.root.querySelectorAll('.pt-play-box').forEach((n) => n.remove());
        const box = (this.measureBoxes || []).find((b) => b.sectionIdx === pos.sectionIdx && b.measureIdx === pos.measureIdx);
        if (!box) return;
        const el = document.createElement('div');
        el.className = 'pt-play-box';
        el.style.left = `${box.x}px`;
        el.style.top = `${box.y}px`;
        el.style.width = `${box.w}px`;
        el.style.height = `${box.h}px`;
        box.lineEl.appendChild(el);
    }

    finTransporte() {
        this.root.querySelector('.pt-btn-play')?.classList.remove('on');
        this.root.querySelectorAll('.pt-play-box').forEach((n) => n.remove());
    }
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function debounce(fn, ms) {
    let t;
    return (...a) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...a), ms);
    };
}

export default VisorPartitura;
