/**
 * Recorrido tipo Flat.io: un tooltip grande que señala cada zona
 * y dice qué hacer, en el orden real de escribir un toque.
 */

const STORAGE_KEY = 'chilinga-pt-tour-v1';

export const PASOS_TOUR = [
    {
        id: 'inicio',
        target: null,
        titulo: 'Escribir un toque, como en Flat.io',
        html: `
            <p>La regla es una sola: <strong>primero seleccionás una nota, después le cambias algo</strong>.</p>
            <ol>
                <li>Clic en una cabeza del pentagrama (queda marcada).</li>
                <li>Elegís figura, golpe, dinámica o mano.</li>
                <li><kbd>Enter</kbd> mete la nota siguiente. Flechas te mueven.</li>
            </ol>
            <p class="pt-tour-note">Si el lienzo está vacío, no hay nada que editar: recargá o avisá. Este recorrido se puede reabrir con <strong>?</strong>.</p>
        `,
    },
    {
        id: 'lienzo',
        target: 'lienzo',
        titulo: 'El papel: elegir qué nota tocar',
        html: `
            <p>Acá está la partitura. No se “escribe en el aire”: siempre hay un golpe seleccionado.</p>
            <ol>
                <li>Clic en una nota o silencio de la línea que quieras (Surdo, Redo, Repi…).</li>
                <li>El recuadro naranja es “estoy editando esto”.</li>
                <li><kbd>←</kbd> <kbd>→</kbd> van de nota en nota. <kbd>↑</kbd> <kbd>↓</kbd> cambian de instrumento.</li>
                <li><kbd>Supr</kbd> borra esa figura (el compás se rellena con silencios).</li>
            </ol>
            <p class="pt-tour-note">Si hacés clic y no pasa nada, el pentagrama no está dibujado. No uses la paleta hasta ver notas.</p>
        `,
    },
    {
        id: 'figuras',
        target: 'figuras',
        titulo: 'Figuras, grupos y silencios',
        html: `
            <p>La barra de abajo es la de escribir. Elegís la herramienta y se aplica a la nota naranja.</p>
            <ol>
                <li><strong>Figuras</strong>: redonda, blanca, negra, corchea, semi, fusa. Las que tienen puntillo duran la mitad más.</li>
                <li>Debajo de cada botón está cuánto ocupa en <strong>tiempos</strong> (negra = 1 t).</li>
                <li><strong>Grupos</strong>: 2 corcheas, 4 semis, tresillo, sextillo — llenan el tiempo de una vez.</li>
                <li><strong>Silencios</strong>: el mismo valor, pero sin golpe.</li>
                <li>Teclado: <kbd>1</kbd>–<kbd>6</kbd> figuras · <kbd>.</kbd> puntillo · <kbd>R</kbd> silencio · <kbd>Ctrl</kbd>+<kbd>3</kbd> tresillo.</li>
            </ol>
            <p class="pt-tour-note">El inspector a la derecha te dice si el compás está completo (192/192 en 4/4). Si queda corto o largo, cambiá figuras hasta que cierre.</p>
        `,
    },
    {
        id: 'golpes',
        target: 'golpes',
        titulo: 'Golpes: qué sonido es',
        html: `
            <p>La paleta de la izquierda cambia según el instrumento seleccionado (surdo ≠ redoblante ≠ timbal).</p>
            <ol>
                <li>Seleccioná una nota en esa línea.</li>
                <li>Clic en el golpe: pleno, chapa, tapado, acentuado, agudo…</li>
                <li>O usá <kbd>Q</kbd> <kbd>W</kbd> <kbd>E</kbd> <kbd>R</kbd> <kbd>T</kbd> <kbd>Y</kbd> en ese orden.</li>
            </ol>
            <p class="pt-tour-note">Al aplicar un golpe se escucha el sample. Si no suena, hacé un clic en la página para activar el audio.</p>
        `,
    },
    {
        id: 'expresion',
        target: 'expresion',
        titulo: 'Dinámicas y manos',
        html: `
            <ol>
                <li><strong>Dinámicas</strong> (<i>p</i>, <i>f</i>, <i>ff</i>…): volumen de esa nota.</li>
                <li><strong>Manos</strong>: <kbd>D</kbd> derecha, <kbd>I</kbd> izquierda, debajo del pentagrama. ✕ las saca.</li>
            </ol>
            <p class="pt-tour-note">Los tresillos y sextillos están en la barra de figuras, no acá. Siempre sobre la nota naranja.</p>
        `,
    },
    {
        id: 'compases',
        target: 'compases',
        titulo: 'Compases, repeticiones y marcas',
        html: `
            <p>Esto no cambia una nota: cambia el <strong>compás entero</strong> donde está la selección.</p>
            <ol>
                <li><strong>+ Compás</strong> / <strong>− Compás</strong>: agrega o saca un compás en esa parte.</li>
                <li><strong>Limpiar voz</strong>: esa línea de ese compás queda en silencios.</li>
                <li><strong>Copiar voz</strong>: pega esa línea en otros instrumentos.</li>
                <li><strong>Partitura en blanco</strong>: borra todo y deja un compás vacío (se deshace con Ctrl+Z).</li>
                <li>A la izquierda: 𝄆 𝄇 casillas 1. y 2., y marcas (D.C. al Fine…).</li>
            </ol>
        `,
    },
    {
        id: 'transporte',
        target: 'transporte',
        titulo: 'Escuchar lo que escribiste',
        html: `
            <ol>
                <li><strong>▶</strong> o <kbd>Espacio</kbd>: reproduce desde el arranque (con conteo 1·2·3·4 si está encendido).</li>
                <li>❚❚ pausa · ■ para · ↻ loop de la partitura.</li>
                <li><strong>Metrónomo</strong>: click en cada tiempo, aparte de los golpes.</li>
                <li><strong>Tempo</strong> 60–100: el gesto del bloque. Arrastrá la barra o escribí el número.</li>
                <li><strong>Compás</strong> 4/4, 2/4, 6/8…: cambia el metro de todo el toque.</li>
            </ol>
            <p class="pt-tour-note">En cada parte, ▶ en la lista de la derecha escucha solo esa sección.</p>
        `,
    },
    {
        id: 'inspector',
        target: 'inspector',
        titulo: 'Inspector: confirmá qué estás editando',
        html: `
            <p>Si el inspector dice “Hacé clic en una nota”, todavía no hay selección.</p>
            <ol>
                <li>Parte, número de compás, instrumento y figura de la nota naranja.</li>
                <li>La barra verde/roja: el compás cierra o le faltan/sobran ticks.</li>
                <li><strong>Texto del compás</strong>: D.C. al Fine u otra marca a mano.</li>
            </ol>
            <p class="pt-tour-note">Abajo, el <strong>mixer</strong>: volumen, mute, solo y el ojito para ocultar una línea. “Agregar / quitar instrumentos” suma o saca voces de la partitura.</p>
        `,
    },
    {
        id: 'partes',
        target: 'partes',
        titulo: 'Partes: llamada, toque, final…',
        html: `
            <p>Un toque no es un solo pentagrama infinito: son bloques (LLAMADA, TOQUE, FINAL).</p>
            <ol>
                <li>Cambiá el nombre del bloque.</li>
                <li><strong>×</strong> cuántas veces se toca esa parte al reproducir.</li>
                <li><strong>+ Parte</strong> agrega un bloque nuevo al final.</li>
                <li>✕ borra la parte (con cuidado).</li>
            </ol>
            <p class="pt-tour-note">El compás que edités es el de la parte seleccionada (la fila activa). Si agregás un compás, entra en esa parte, no en otra.</p>
        `,
    },
    {
        id: 'archivo',
        target: 'archivo',
        titulo: 'Guardar, original y exportar',
        html: `
            <ol>
                <li><strong>Guardar</strong> o <kbd>Ctrl</kbd>+<kbd>S</kbd>: deja el toque en el servidor. El pie dice “Cambios sin guardar” si falta.</li>
                <li><strong>Importar → PDF / imagen</strong>: el original al lado, para transcribir. Botón <strong>Original</strong> lo muestra u oculta.</li>
                <li>MusicXML / JSON: traer un archivo de MuseScore o un backup del editor.</li>
                <li><strong>Exportar</strong>: PDF, PNG, MusicXML o MIDI para afuera.</li>
                <li><kbd>Ctrl</kbd>+<kbd>Z</kbd> deshace · <kbd>Ctrl</kbd>+<kbd>Y</kbd> rehace.</li>
            </ol>
            <p class="pt-tour-note">Reabrí esta guía cuando quieras con el botón <strong>?</strong> de la barra.</p>
        `,
    },
];

export class TourPartitura {
    /**
     * @param {HTMLElement} root
     */
    constructor(root) {
        this.root = root;
        this.idx = 0;
        this.abierto = false;
        this._onKey = (e) => this.onKey(e);
        this._onFit = () => this.colocar();
        this.construir();
    }

    construir() {
        this.el = document.createElement('div');
        this.el.className = 'pt-tour';
        this.el.hidden = true;
        this.el.innerHTML = `
            <div class="pt-tour-spot" data-tour-spot></div>
            <div class="pt-tour-card" role="dialog" aria-modal="true" aria-labelledby="pt-tour-title">
                <div class="pt-tour-kicker"><span data-tour-step></span></div>
                <h2 id="pt-tour-title" data-tour-title></h2>
                <div class="pt-tour-body" data-tour-body></div>
                <div class="pt-tour-nav">
                    <button type="button" class="pt-btn" data-tour-a="skip">Saltar</button>
                    <span class="pt-tour-nav-spacer"></span>
                    <button type="button" class="pt-btn" data-tour-a="prev">Atrás</button>
                    <button type="button" class="pt-btn pt-btn-primary" data-tour-a="next">Siguiente</button>
                </div>
            </div>
        `;
        this.root.appendChild(this.el);
        this.spot = this.el.querySelector('[data-tour-spot]');
        this.card = this.el.querySelector('.pt-tour-card');
        this.el.addEventListener('click', (e) => {
            const a = e.target.closest('[data-tour-a]')?.dataset.tourA;
            if (!a) return;
            e.preventDefault();
            e.stopPropagation();
            if (a === 'next') this.siguiente();
            else if (a === 'prev') this.anterior();
            else if (a === 'skip') this.cerrar();
        });
    }

    visto() {
        try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch { return true; }
    }

    marcarVisto() {
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch { /* privado */ }
    }

    maybeStart() {
        if (this.visto()) return;
        requestAnimationFrame(() => this.start(0));
    }

    start(i = 0) {
        this.idx = Math.max(0, Math.min(i, PASOS_TOUR.length - 1));
        this.abierto = true;
        this.el.hidden = false;
        document.addEventListener('keydown', this._onKey, true);
        window.addEventListener('resize', this._onFit);
        this.root.querySelector('.pt-canvas-wrap')?.addEventListener('scroll', this._onFit);
        this.pintar();
    }

    cerrar() {
        this.abierto = false;
        this.el.hidden = true;
        this.marcarVisto();
        document.removeEventListener('keydown', this._onKey, true);
        window.removeEventListener('resize', this._onFit);
        this.root.querySelector('.pt-canvas-wrap')?.removeEventListener('scroll', this._onFit);
        this.root.querySelectorAll('.pt-tour-hl').forEach((n) => n.classList.remove('pt-tour-hl'));
    }

    siguiente() {
        if (this.idx >= PASOS_TOUR.length - 1) {
            this.cerrar();
            return;
        }
        this.idx += 1;
        this.pintar();
    }

    anterior() {
        if (this.idx <= 0) return;
        this.idx -= 1;
        this.pintar();
    }

    onKey(e) {
        if (!this.abierto) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            this.cerrar();
            return;
        }
        if (e.key === 'ArrowRight' || e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            this.siguiente();
            return;
        }
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            e.stopPropagation();
            this.anterior();
        }
    }

    pintar() {
        const paso = PASOS_TOUR[this.idx];
        const ultimo = this.idx === PASOS_TOUR.length - 1;
        this.el.querySelector('[data-tour-step]').textContent = `Paso ${this.idx + 1} de ${PASOS_TOUR.length}`;
        this.el.querySelector('[data-tour-title]').textContent = paso.titulo;
        this.el.querySelector('[data-tour-body]').innerHTML = paso.html;
        const next = this.el.querySelector('[data-tour-a="next"]');
        next.textContent = ultimo ? 'Listo, a escribir' : 'Siguiente';
        this.el.querySelector('[data-tour-a="prev"]').disabled = this.idx === 0;
        this.colocar();
    }

    colocar() {
        const paso = PASOS_TOUR[this.idx];
        this.root.querySelectorAll('.pt-tour-hl').forEach((n) => n.classList.remove('pt-tour-hl'));
        const target = paso.target ? this.root.querySelector(`[data-tour="${paso.target}"]`) : null;
        if (target) {
            target.classList.add('pt-tour-hl');
            if (typeof target.scrollIntoView === 'function') {
                target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
        }
        const hole = target ? target.getBoundingClientRect() : null;
        if (hole && hole.width > 8 && hole.height > 8) {
            this.spot.hidden = false;
            this.spot.style.top = `${Math.max(6, hole.top - 6)}px`;
            this.spot.style.left = `${Math.max(6, hole.left - 6)}px`;
            this.spot.style.width = `${hole.width + 12}px`;
            this.spot.style.height = `${hole.height + 12}px`;
        } else {
            this.spot.hidden = true;
        }
        this.colocarCard(hole);
    }

    colocarCard(hole) {
        const card = this.card;
        card.style.visibility = 'hidden';
        card.style.top = '0';
        card.style.left = '0';
        const w = card.offsetWidth || 400;
        const h = card.offsetHeight || 280;
        const pad = 12;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        let top;
        let left;
        if (!hole || this.spot.hidden) {
            left = Math.max(pad, (vw - w) / 2);
            top = Math.max(pad, (vh - h) / 2);
        } else {
            left = Math.min(Math.max(pad, hole.left), vw - w - pad);
            top = hole.bottom + 14;
            if (top + h > vh - pad) top = hole.top - h - 14;
            if (top < pad) {
                top = Math.max(pad, Math.min(hole.top, vh - h - pad));
                left = hole.right + 14;
                if (left + w > vw - pad) left = hole.left - w - 14;
            }
        }
        left = Math.min(Math.max(pad, left), Math.max(pad, vw - w - pad));
        top = Math.min(Math.max(pad, top), Math.max(pad, vh - h - pad));
        card.style.left = `${left}px`;
        card.style.top = `${top}px`;
        card.style.visibility = 'visible';
    }
}
