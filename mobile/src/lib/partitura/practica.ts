/**
 * Tap tempo (espejo de resources/js/partitura/practica.js): BPM a partir de los últimos toques.
 * Ignora pausas largas (> 2 s) y usa la mediana para no saltar con un toque desparejo.
 */
export const TAP_MIN = 40;
export const TAP_MAX = 180;

export function bpmDeToques(tiempos: number[], { min = TAP_MIN, max = TAP_MAX } = {}): number | null {
  const recientes: number[] = [];
  for (let i = tiempos.length - 1; i > 0 && recientes.length < 8; i -= 1) {
    const dt = tiempos[i] - tiempos[i - 1];
    if (dt > 2000) break;
    if (dt > 0) recientes.push(dt);
  }
  if (recientes.length < 2) return null;
  const orden = [...recientes].sort((a, b) => a - b);
  const mediana = orden[Math.floor(orden.length / 2)];
  return Math.min(max, Math.max(min, Math.round(60000 / mediana)));
}

/** Agrega un toque; si pasaron más de 2 s desde el anterior, empieza de nuevo. */
export function registrarToque(tiempos: number[], ahora: number): number[] {
  const ultimo = tiempos[tiempos.length - 1];
  const base = ultimo !== undefined && ahora - ultimo > 2000 ? [] : tiempos.slice(-8);
  return [...base, ahora];
}
