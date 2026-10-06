/**
 * Registro de atajos del editor de ritmos.
 *
 * Cada acción se registra una vez (id, etiqueta, grupo, contextos, teclas, run). El
 * registro resuelve la tecla según el contexto activo, detecta conflictos y genera la
 * ayuda (`?`): la tabla de atajos no se escribe a mano en ningún otro lado.
 *
 * Contextos: `global` (siempre), `partitura` (foco en el pentagrama), `grilla` (foco en
 * la grilla) y `tocar` (el teclado es un instrumento). Uno específico gana sobre global.
 */

const NOMBRES = {
    ' ': 'Space', Spacebar: 'Space', Esc: 'Escape', Del: 'Delete',
    Left: 'ArrowLeft', Right: 'ArrowRight', Up: 'ArrowUp', Down: 'ArrowDown',
};

const LEGIBLE = {
    Space: 'Espacio', ArrowLeft: '←', ArrowRight: '→', ArrowUp: '↑', ArrowDown: '↓',
    Delete: 'Supr', Backspace: '⌫', Enter: 'Enter', Escape: 'Esc', Ctrl: 'Ctrl', Alt: 'Alt', Shift: 'Shift',
};

/**
 * Forma canónica: `Ctrl+Shift+Z`, `Space`, `A`, `?`, `Alt+1`.
 * Ctrl y Cmd son lo mismo. En teclas imprimibles que no son letras, Shift ya está en el
 * carácter (`?`, `>`, `+`) y no se agrega, así funciona en teclados con otra distribución.
 *
 * @param {KeyboardEvent|string} e
 */
export function normalizarTecla(e) {
    if (typeof e === 'string') {
        const partes = e.split('+').map((p) => p.trim()).filter(Boolean);
        if (e.endsWith('++')) partes.push('+');
        const mods = new Set(partes.slice(0, -1).map((m) => (/^(cmd|meta|ctrl|control)$/i.test(m) ? 'Ctrl' : m[0].toUpperCase() + m.slice(1).toLowerCase())));
        return armar(mods.has('Ctrl'), mods.has('Alt'), mods.has('Shift'), partes[partes.length - 1] || '');
    }
    let key = NOMBRES[e.key] || e.key || '';
    // Alt cambia el carácter en macOS (Alt+1 = ¡): se usa el código físico para dígitos y letras.
    if (e.altKey && /^Digit\d$/.test(e.code || '')) key = e.code.slice(5);
    if (e.altKey && /^Key[A-Z]$/.test(e.code || '')) key = e.code.slice(3);
    return armar(e.ctrlKey || e.metaKey, e.altKey, e.shiftKey, key);
}

function armar(ctrl, alt, shift, key) {
    let k = NOMBRES[key] || key;
    if (k.length === 1) k = k.toUpperCase();
    const esLetra = /^[A-Z]$/.test(k);
    const imprimible = k.length === 1;
    const out = [];
    if (ctrl) out.push('Ctrl');
    if (alt) out.push('Alt');
    if (shift && (esLetra || !imprimible || ctrl || alt)) out.push('Shift');
    out.push(k);
    return out.join('+');
}

/** Texto para mostrar una tecla canónica. */
export function teclaLegible(tecla) {
    return String(tecla).split('+').map((p) => LEGIBLE[p] || p).join(' + ').replace(' + + ', ' + +');
}

export class RegistroAtajos {
    constructor() {
        /** @type {Map<string, {id:string, etiqueta:string, grupo:string, contextos:string[], teclas:string[], porDefecto:string[], run:Function, personalizable:boolean, oculto:boolean}>} */
        this.acciones = new Map();
    }

    /**
     * @param {{ id: string, etiqueta: string, grupo?: string, contextos?: string[], teclas: string|string[], run: Function, personalizable?: boolean, oculto?: boolean, repetible?: boolean }} def
     */
    registrar(def) {
        const teclas = (Array.isArray(def.teclas) ? def.teclas : [def.teclas]).filter(Boolean).map(normalizarTecla);
        this.acciones.set(def.id, {
            id: def.id,
            etiqueta: def.etiqueta,
            grupo: def.grupo || 'General',
            contextos: def.contextos?.length ? def.contextos : ['global'],
            teclas,
            porDefecto: teclas.slice(),
            run: def.run,
            personalizable: !!def.personalizable,
            oculto: !!def.oculto,
            repetible: !!def.repetible,
        });
        return this;
    }

    quitar(id) {
        this.acciones.delete(id);
        return this;
    }

    /**
     * Acción para una tecla en los contextos activos (el primero tiene prioridad).
     * @param {KeyboardEvent|string} e
     * @param {string[]} contextos  p. ej. ['grilla', 'global']
     */
    resolver(e, contextos = ['global']) {
        const tecla = normalizarTecla(e);
        for (const ctx of contextos) {
            for (const a of this.acciones.values()) {
                if (a.contextos.includes(ctx) && a.teclas.includes(tecla)) return a;
            }
        }
        return null;
    }

    /** Dos acciones que comparten tecla y algún contexto (o una de ellas es global). */
    conflictosDe(tecla, contextos, excluirId = null) {
        const t = normalizarTecla(tecla);
        const ctxs = new Set(contextos);
        return [...this.acciones.values()].filter((a) => a.id !== excluirId
            && a.teclas.includes(t)
            && (a.contextos.some((c) => ctxs.has(c)) || a.contextos.includes('global') || ctxs.has('global')));
    }

    /** Todos los conflictos del registro: [{ tecla, acciones: [ids] }]. */
    conflictos() {
        const out = [];
        const vistos = new Set();
        for (const a of this.acciones.values()) {
            for (const t of a.teclas) {
                const otros = this.conflictosDe(t, a.contextos, a.id);
                if (!otros.length) continue;
                const clave = [t, a.id, ...otros.map((o) => o.id)].sort().join('|');
                if (vistos.has(clave)) continue;
                vistos.add(clave);
                out.push({ tecla: t, acciones: [a.id, ...otros.map((o) => o.id)] });
            }
        }
        return out;
    }

    /**
     * Cambia la tecla de una acción personalizable. No pisa: si hay conflicto lo devuelve.
     * @returns {{ ok: boolean, conflicto?: string }}
     */
    asignar(id, tecla) {
        const a = this.acciones.get(id);
        if (!a || !a.personalizable) return { ok: false, conflicto: 'Esta acción no se puede cambiar.' };
        const t = normalizarTecla(tecla);
        const otros = this.conflictosDe(t, a.contextos, id);
        if (otros.length) return { ok: false, conflicto: `Esta tecla ya está asignada a “${otros[0].etiqueta}”.` };
        a.teclas = [t];
        return { ok: true };
    }

    restaurar() {
        this.acciones.forEach((a) => { a.teclas = a.porDefecto.slice(); });
    }

    /** Personalizaciones para guardar: { id: tecla } (solo las distintas al defecto). */
    exportar() {
        const out = {};
        this.acciones.forEach((a) => {
            if (a.personalizable && a.teclas.join() !== a.porDefecto.join()) out[a.id] = a.teclas[0];
        });
        return out;
    }

    /** Aplica personalizaciones guardadas; ignora las que generan conflicto. */
    importar(mapa = {}) {
        Object.entries(mapa || {}).forEach(([id, tecla]) => {
            if (typeof tecla === 'string') this.asignar(id, tecla);
        });
    }

    /** Ayuda agrupada, generada desde el registro. */
    ayuda() {
        const grupos = new Map();
        this.acciones.forEach((a) => {
            if (a.oculto) return;
            if (!grupos.has(a.grupo)) grupos.set(a.grupo, []);
            grupos.get(a.grupo).push({ id: a.id, etiqueta: a.etiqueta, teclas: a.teclas.map(teclaLegible), contextos: a.contextos });
        });
        return [...grupos.entries()].map(([grupo, items]) => ({ grupo, items }));
    }
}
