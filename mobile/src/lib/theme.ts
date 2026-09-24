/** Paleta del panel web (public/css/chilinga-admin.css): fondo negro, acento naranja. */
export const C = {
  fondo: '#000000',
  superficie: '#111111',
  superficie2: '#1c1c1c',
  borde: '#2a2a2a',
  texto: '#ffffff',
  tenue: '#a3a3a3',
  acento: '#f26422',
  acentoSuave: 'rgba(242,100,34,0.18)',
  exito: '#3daf3a',
  exitoSuave: 'rgba(61,175,58,0.18)',
  alerta: '#e6a817',
  alertaSuave: 'rgba(230,168,23,0.18)',
  peligro: '#e31b23',
  peligroSuave: 'rgba(227,27,35,0.18)',
  info: '#4fb3d9',
} as const;

export const E = { xs: 4, s: 8, m: 12, l: 16, xl: 24, xxl: 32 } as const;

/** Mínimo recomendado para objetivos táctiles. */
export const TOQUE = 48;

export const moneda = (n: number) => '$ ' + Math.round(n).toLocaleString('es-AR');
