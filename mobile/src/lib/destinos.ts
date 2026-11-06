// Los avisos de cuotas son para el alumno: van a su cuenta (los viejos usaban app://cuotas).
const DESTINOS: Record<string, string> = { 'app://cuotas': '/mi-cuenta', 'app://mi-cuenta': '/mi-cuenta', 'app://calendario': '/agenda' };

/** Enlace de un aviso (bandeja o push) → pantalla de la app. Los del archivo apuntan a la web: se traducen. */
export function destinoDe(enlace: unknown): string | undefined {
  if (typeof enlace !== 'string' || !enlace) return undefined;
  const aporte = enlace.match(/\/archivo\/mis-aportes\/(\d+)/);
  if (aporte) return `/archivo/aportes/${aporte[1]}`;
  // Recordatorio de pasar lista: abre la planilla de ese bloque.
  const lista = enlace.match(/^app:\/\/asistencia\/(\d+)$/);
  if (lista) return `/asistencia/${lista[1]}`;
  return DESTINOS[enlace];
}
