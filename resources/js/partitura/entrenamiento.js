/**
 * Entrenamiento de velocidad: el loop arranca a un porcentaje del tempo del toque y sube solo
 * cada N repeticiones, hasta un tope. Tres presets (leve, medio, fuerte) que cada usuario puede
 * ajustar; se guardan en su navegador. Funciones puras (sin DOM ni audio).
 * Espejo en la app: mobile/src/lib/partitura/entrenamiento.ts.
 */
export const TEMPO_MIN = 30;
export const TEMPO_MAX = 260;

export const PRESETS = ['leve', 'medio', 'fuerte'];

/** Valores de fábrica. El usuario los cambia; se guardan aparte. */
export const PRESETS_DEFAULT = {
    leve: { inicio: 80, subida: 2, cada: 2, tope: 100 },
    medio: { inicio: 70, subida: 5, cada: 2, tope: 100 },
    fuerte: { inicio: 60, subida: 10, cada: 1, tope: 100 },
};

/** Rangos válidos de cada parámetro (en %, salvo «cada», que son repeticiones). */
export const LIMITES = {
    inicio: [30, 100],
    subida: [1, 20],
    cada: [1, 16],
    tope: [50, 150],
};

const clamp = (n, [min, max]) => Math.min(max, Math.max(min, n));

/** Un preset con todos sus campos válidos. Lo que falta o es inválido vuelve al valor de fábrica. */
export function normalizarPreset(raw, nombre = 'medio') {
    const base = PRESETS_DEFAULT[nombre] || PRESETS_DEFAULT.medio;
    const dato = (campo) => {
        const n = Math.round(Number(raw?.[campo]));
        return Number.isFinite(n) ? clamp(n, LIMITES[campo]) : base[campo];
    };
    return { inicio: dato('inicio'), subida: dato('subida'), cada: dato('cada'), tope: dato('tope') };
}

/**
 * Tempo de la repetición `vuelta` (0 = la primera). Arranca en `inicio`% del tempo del toque,
 * sube `subida`% del tempo cada `cada` repeticiones y se detiene en `tope`%.
 */
export function tempoDeVuelta(base, vuelta, preset) {
    const cfg = normalizarPreset(preset);
    const b = Math.max(TEMPO_MIN, Number(base) || 100);
    const tope = Math.min(TEMPO_MAX, Math.round((b * cfg.tope) / 100));
    const inicio = Math.round((b * cfg.inicio) / 100);
    const escalones = Math.floor(Math.max(0, vuelta) / cfg.cada);
    const tempo = inicio + escalones * ((b * cfg.subida) / 100);
    return Math.max(TEMPO_MIN, Math.min(tope, Math.round(tempo)));
}

/** Lee los presets guardados (con lo que falte, de fábrica). `storage` es localStorage o similar. */
export function cargarPresets(storage) {
    let guardados = {};
    try {
        guardados = JSON.parse(storage?.getItem('pt-entrenamiento') || '{}') || {};
    } catch {
        guardados = {};
    }
    return Object.fromEntries(PRESETS.map((p) => [p, normalizarPreset(guardados[p], p)]));
}

export function guardarPresets(storage, presets) {
    const limpios = Object.fromEntries(PRESETS.map((p) => [p, normalizarPreset(presets[p], p)]));
    storage?.setItem('pt-entrenamiento', JSON.stringify(limpios));
    return limpios;
}
