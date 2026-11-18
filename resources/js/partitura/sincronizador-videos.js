/**
 * Sincronizador de videos multipista: lógica pura (sin DOM). Un video es el reloj maestro; el
 * tiempo del grupo es el de ese video más su offset. Cada video debe estar en
 * `tiempoGrupo - offset`; si se desvía más de TOLERANCIA_S, se resincroniza.
 */
export const TOLERANCIA_S = 0.1;
export const OFFSET_PASO_S = 0.05;
export const OFFSET_LIMITE_S = 10;
export const MAX_VIDEOS = 6;

const redondear3 = (n) => Math.round(n * 1000) / 1000;

/** Video que marca el tiempo: el elegido, o el primero de la lista. */
export function relojMaestro(videos, elegidoId = null) {
    if (!videos.length) return null;
    return videos.find((v) => v.id === elegidoId) || videos[0];
}

/** Tiempo del grupo a partir del tiempo del reloj maestro. */
export function tiempoGrupo(maestro, tiempoMaestro) {
    return redondear3(tiempoMaestro + (maestro?.offset_seg || 0));
}

/** Tiempo que debe mostrar un video en el instante `tiempoGrupoActual` (acotado a su duración). */
export function tiempoEsperado(video, tiempoGrupoActual, duracion = Infinity) {
    const t = tiempoGrupoActual - (video.offset_seg || 0);
    return Math.min(Math.max(0, t), Number.isFinite(duracion) ? duracion : Infinity);
}

/** Cuánto se desvió un video (positivo = va adelantado). */
export function desfase(tiempoReal, tiempoEsperadoEnVideo) {
    return redondear3(tiempoReal - tiempoEsperadoEnVideo);
}

export function debeResincronizar(desfaseS, tolerancia = TOLERANCIA_S) {
    return Math.abs(desfaseS) > tolerancia;
}

/**
 * Qué video suena. Solo uno a la vez: si hay algún «solo», gana el primero marcado; si no, el
 * elegido como audio (por defecto el reloj). Un video silenciado nunca suena.
 * @param {{id: string}[]} videos
 * @param {{audioId?: string|null, solos?: string[], mudos?: string[]}} estado
 */
export function videoConAudio(videos, { audioId = null, solos = [], mudos = [] } = {}) {
    const ids = new Set(videos.map((v) => v.id));
    const solo = solos.find((id) => ids.has(id) && !mudos.includes(id));
    if (solo) return solo;
    if (audioId && ids.has(audioId) && !mudos.includes(audioId)) return audioId;
    return null;
}

/** Disposición de la grilla según la cantidad de tiles (1 a 6). */
export function disposicionGrilla(n) {
    const cantidad = Math.max(1, Math.min(MAX_VIDEOS, Math.floor(n) || 1));
    if (cantidad === 1) return { columnas: 1, filas: 1 };
    if (cantidad === 2) return { columnas: 2, filas: 1 };
    if (cantidad <= 4) return { columnas: 2, filas: 2 };
    return { columnas: 3, filas: 2 };
}

/** Nuevo offset tras `pasos` toques de ±0,05 s (+1 / −1), acotado a ±10 s. */
export function ajustarOffset(offset, pasos) {
    const n = redondear3((Number(offset) || 0) + Math.round(Number(pasos) || 0) * OFFSET_PASO_S);
    return Math.max(-OFFSET_LIMITE_S, Math.min(OFFSET_LIMITE_S, n));
}

/** Modos de ejecución: todos juntos y sincronizados, o un solo video a la vez. */
export const MODOS = ['todos', 'separado'];

/** Qué videos se reproducen en el modo elegido. «separado» usa solo el video indicado. */
export function videosActivos(videos, { modo = 'todos', elegido = null } = {}) {
    if (modo === 'separado') {
        const v = videos.find((x) => x.id === elegido) || videos[0];
        return v ? [v] : [];
    }
    return [...videos];
}
