/**
 * Reproductor de videos multipista: grilla de 1 a 6 videos que suenan y avanzan juntos.
 * El reloj es un video (el elegido); los demás se resincronizan si se desvían más de 0,1 s.
 * Play, pausa y saltar afectan a todos. Solo un video tiene audio a la vez.
 * La lógica de sincronía está en sincronizador-videos.js (con tests).
 */
import {
    ajustarOffset, debeResincronizar, desfase, disposicionGrilla, relojMaestro,
    tiempoEsperado, tiempoGrupo, videoConAudio, videosActivos,
} from './sincronizador-videos.js';

export class ReproductorVideos {
    /**
     * @param {HTMLElement} root
     * @param {{ videos: {id:string, titulo:string, instrumento:string, url:string, offset_seg:number, orden:number}[] }} opts
     */
    constructor(root, { videos }) {
        this.root = root;
        this.videos = [...videos].sort((a, b) => a.orden - b.orden);
        this.maestroId = this.videos[0]?.id ?? null;
        this.audioId = this.maestroId;
        this.solos = [];
        this.mudos = [];
        this.modo = 'todos';
        this.separadoId = this.videos[0]?.id ?? null;
        this.el = new Map();
        this.tiempo = 0;
        this.sonando = false;
        this._raf = 0;
        this.construir();
        this.aplicarAudio();
    }

    get maestro() {
        return relojMaestro(this.videos, this.maestroId);
    }

    construir() {
        const { columnas, filas } = disposicionGrilla(this.videos.length);
        this.root.classList.add('pt-videos');
        this.root.innerHTML = `
            <div class="pt-videos-bar">
                <button type="button" class="pt-btn pt-btn-play" data-v="play" aria-label="Reproducir todos">▶</button>
                <button type="button" class="pt-btn" data-v="pausa" aria-label="Pausar todos">❚❚</button>
                <button type="button" class="pt-btn" data-v="atras" title="Atrás 5 segundos">−5 s</button>
                <button type="button" class="pt-btn" data-v="adelante" title="Adelante 5 segundos">+5 s</button>
                <button type="button" class="pt-btn" data-v="modo-todos" aria-pressed="true" title="Todos los videos juntos y sincronizados">Ejecutar todos</button>
                <button type="button" class="pt-btn" data-v="modo-separado" aria-pressed="false" title="Un solo video a la vez">Ejecutar por separado</button>
                <label class="pt-field" data-v="elegir-wrap" hidden><span>Video</span>
                    <select class="pt-input pt-select" data-v="elegir" aria-label="Video a ejecutar">
                        ${this.videos.map((v) => `<option value="${esc(v.id)}">${esc(v.titulo)}</option>`).join('')}
                    </select>
                </label>
                <input type="range" class="pt-videos-barra" data-v="barra" min="0" max="1000" value="0" aria-label="Posición del grupo">
                <span class="pt-videos-tiempo" data-v="tiempo">0:00</span>
                <label class="pt-field"><span>Reloj</span>
                    <select class="pt-input pt-select" data-v="reloj" aria-label="Video que marca el tiempo">
                        ${this.videos.map((v) => `<option value="${esc(v.id)}">${esc(v.titulo)}</option>`).join('')}
                    </select>
                </label>
            </div>
            <div class="pt-videos-grid" data-columnas="${columnas}" style="grid-template-columns: repeat(${columnas}, minmax(0, 1fr)); --pt-filas: ${filas}">
                ${this.videos.map((v) => `
                    <figure class="pt-videos-tile" data-id="${esc(v.id)}">
                        <video src="${esc(v.url)}" preload="metadata" playsinline></video>
                        <figcaption>
                            <strong>${esc(v.titulo)}</strong>
                            <span class="pt-videos-instr">${esc(v.instrumento === 'voz' ? 'Voz' : v.instrumento)}</span>
                            <span class="pt-videos-acciones">
                                <button type="button" class="pt-btn pt-btn-ghost" data-v="solo" aria-pressed="false" title="Solo este video">Solo</button>
                                <button type="button" class="pt-btn pt-btn-ghost" data-v="mudo" aria-pressed="false" title="Silenciar este video">Silencio</button>
                                <button type="button" class="pt-btn pt-btn-ghost" data-v="audio" aria-pressed="false" title="Que este video tenga el audio">Audio</button>
                            </span>
                            <span class="pt-videos-offset" title="Offset: cuánto después del grupo arranca este video">
                                <button type="button" class="pt-btn pt-btn-ghost" data-v="off-menos" aria-label="Atrasar 0,05 segundos">−0,05</button>
                                <span data-v="off">${Number(v.offset_seg || 0).toFixed(2)} s</span>
                                <button type="button" class="pt-btn pt-btn-ghost" data-v="off-mas" aria-label="Adelantar 0,05 segundos">+0,05</button>
                            </span>
                        </figcaption>
                    </figure>`).join('')}
            </div>`;
        this.root.querySelectorAll('.pt-videos-tile').forEach((fig) => {
            const v = this.videos.find((x) => x.id === fig.dataset.id);
            this.el.set(v.id, { video: fig.querySelector('video'), fig, data: v, off: fig.querySelector('[data-v="off"]') });
        });
        this.root.addEventListener('click', (e) => this.onClick(e));
        this.root.querySelector('[data-v="reloj"]').addEventListener('change', (e) => {
            this.maestroId = e.target.value;
            this.seek(this.tiempo);
        });
        this.root.querySelector('[data-v="barra"]').addEventListener('input', (e) => {
            const dur = this.duracionGrupo();
            if (dur > 0) this.seek((Number(e.target.value) / 1000) * dur);
        });
        this.root.querySelector('[data-v="reloj"]').value = this.maestroId;
        this.root.querySelector('[data-v="elegir"]').addEventListener('change', (e) => {
            this.separadoId = e.target.value;
            this.aplicarModo();
        });
    }

    onClick(e) {
        const btn = e.target.closest('[data-v]');
        const fig = e.target.closest('.pt-videos-tile');
        const id = fig?.dataset.id;
        switch (btn?.dataset.v) {
            case 'play': return this.play();
            case 'modo-todos': return this.setModo('todos');
            case 'modo-separado': return this.setModo('separado');
            case 'pausa': return this.pausar();
            case 'atras': return this.seek(this.tiempo - 5);
            case 'adelante': return this.seek(this.tiempo + 5);
            case 'solo': return this.alternar(this.solos, id, btn);
            case 'mudo': return this.alternar(this.mudos, id, btn);
            case 'audio': this.audioId = id; return this.aplicarAudio();
            case 'off-menos': return this.cambiarOffset(id, -1);
            case 'off-mas': return this.cambiarOffset(id, 1);
            default: return undefined;
        }
    }

    /** «todos»: la grilla completa. «separado»: un solo video, el elegido (play, audio y reloj). */
    setModo(modo) {
        this.pausar();
        this.modo = modo === 'separado' ? 'separado' : 'todos';
        this.aplicarModo();
        this.seek(this.tiempo);
    }

    aplicarModo() {
        const separado = this.modo === 'separado';
        if (separado) {
            this.maestroId = this.separadoId;
            this.audioId = this.separadoId;
            this.root.querySelector('[data-v="reloj"]').value = this.maestroId;
        }
        this.el.forEach((item, id) => {
            item.fig.hidden = separado && id !== this.separadoId;
        });
        const grid = this.root.querySelector('.pt-videos-grid');
        if (grid) grid.style.gridTemplateColumns = separado ? 'minmax(0, 1fr)' : `repeat(${grid.dataset.columnas}, minmax(0, 1fr))`;
        this.root.querySelector('[data-v="modo-todos"]')?.setAttribute('aria-pressed', separado ? 'false' : 'true');
        this.root.querySelector('[data-v="modo-separado"]')?.setAttribute('aria-pressed', separado ? 'true' : 'false');
        const wrap = this.root.querySelector('[data-v="elegir-wrap"]');
        if (wrap) wrap.hidden = !separado;
        this.aplicarAudio();
    }

    /** Videos que suenan en el modo actual. */
    activos() {
        return videosActivos(this.videos, { modo: this.modo, elegido: this.separadoId })
            .map((v) => this.el.get(v.id))
            .filter(Boolean);
    }

    alternar(lista, id, btn) {
        const i = lista.indexOf(id);
        if (i >= 0) lista.splice(i, 1);
        else lista.push(id);
        btn.setAttribute('aria-pressed', i >= 0 ? 'false' : 'true');
        this.aplicarAudio();
    }

    aplicarAudio() {
        const sonante = videoConAudio(this.videos, { audioId: this.audioId, solos: this.solos, mudos: this.mudos });
        this.el.forEach((item, id) => {
            item.video.muted = id !== sonante;
            item.fig.classList.toggle('es-maestro', id === this.maestroId);
            item.fig.classList.toggle('silenciado', this.mudos.includes(id));
            item.fig.querySelector('[data-v="audio"]')?.setAttribute('aria-pressed', id === sonante ? 'true' : 'false');
        });
    }

    /** Duración del grupo: la del reloj maestro (o la más corta, si los videos no coinciden). */
    duracionGrupo() {
        const m = this.el.get(this.maestro?.id)?.video;
        return m && Number.isFinite(m.duration) ? m.duration + (this.maestro.offset_seg || 0) : 0;
    }

    /** Salta a un instante del grupo: cada video a su tiempo (acotado a su duración). */
    seek(tiempoGrupoNuevo) {
        this.tiempo = Math.max(0, tiempoGrupoNuevo);
        this.el.forEach((item) => {
            const dur = Number.isFinite(item.video.duration) ? item.video.duration : Infinity;
            item.video.currentTime = tiempoEsperado(item.data, this.tiempo, dur);
        });
        this.pintarPosicion();
    }

    async play() {
        this.sonando = true;
        await Promise.allSettled(this.activos().map((i) => i.video.play()));
        this.bucle();
    }

    pausar() {
        this.sonando = false;
        this.el.forEach((i) => i.video.pause());
        cancelAnimationFrame(this._raf);
    }

    /** Reloj: lee el maestro y corrige a los demás cuando se desfasan. */
    bucle() {
        cancelAnimationFrame(this._raf);
        const paso = () => {
            if (!this.sonando) return;
            const m = this.el.get(this.maestro.id);
            this.tiempo = tiempoGrupo(this.maestro, m.video.currentTime);
            this.activos().forEach((item) => {
                if (item.data.id === this.maestro.id) return;
                const dur = Number.isFinite(item.video.duration) ? item.video.duration : Infinity;
                const esperado = tiempoEsperado(item.data, this.tiempo, dur);
                const d = desfase(item.video.currentTime, esperado);
                if (debeResincronizar(d) && item.video.readyState >= 2) item.video.currentTime = esperado;
            });
            this.pintarPosicion();
            this._raf = requestAnimationFrame(paso);
        };
        this._raf = requestAnimationFrame(paso);
    }

    cambiarOffset(id, pasos) {
        const item = this.el.get(id);
        if (!item) return;
        item.data.offset_seg = ajustarOffset(item.data.offset_seg, pasos);
        item.off.textContent = `${item.data.offset_seg.toFixed(2)} s`;
        this.seek(this.tiempo);
    }

    pintarPosicion() {
        const dur = this.duracionGrupo();
        const barra = this.root.querySelector('[data-v="barra"]');
        if (dur > 0 && document.activeElement !== barra) barra.value = String(Math.round((this.tiempo / dur) * 1000));
        const t = this.root.querySelector('[data-v="tiempo"]');
        if (t) t.textContent = formatea(this.tiempo);
    }
}

function formatea(s) {
    const t = Math.max(0, s || 0);
    return `${Math.floor(t / 60)}:${String(Math.floor(t % 60)).padStart(2, '0')}`;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export default ReproductorVideos;
