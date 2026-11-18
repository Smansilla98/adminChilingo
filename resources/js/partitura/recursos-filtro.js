/**
 * Filtro de «Toques y recursos»: por año y por tipo de recurso (videos, partitura o grabación).
 * Lógica pura; el mismo criterio que la lista del servidor. Espejo en tests/js.
 */
export const TIPOS = ['videos', 'partitura', 'audio'];

/** ¿El toque tiene el recurso pedido? Sin tipo, cualquier toque de la lista lo cumple. */
export function tieneRecurso(item, tipo) {
    if (tipo === 'videos') return Number(item.videos) > 0;
    if (tipo === 'partitura') return Boolean(Number(item.partitura));
    if (tipo === 'audio') return Boolean(Number(item.audio));
    return true;
}

/**
 * @param {{anio: number|string, videos: number|string, partitura: number|string, audio: number|string}[]} items
 * @param {{anio?: string|number, tipo?: string}} filtro  año vacío = todos; tipo vacío = todos
 */
export function filtrarRecursos(items, { anio = '', tipo = '' } = {}) {
    return items.filter((it) => (!anio || String(it.anio) === String(anio)) && tieneRecurso(it, tipo));
}
