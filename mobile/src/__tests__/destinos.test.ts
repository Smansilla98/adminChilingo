import { destinoDe } from '@/lib/destinos';
import { enlaceWhatsApp } from '@/lib/formato';

it.each([
  ['app://mi-cuenta', '/mi-cuenta'],
  ['app://cuotas', '/mi-cuenta'],
  ['app://calendario', '/agenda'],
  ['app://asistencia/12', '/asistencia/12'],
  ['https://admin.ejemplo/archivo/mis-aportes/40', '/archivo/aportes/40'],
  // Aviso de ausencias: la web manda la ruta del panel docente.
  ['https://admin.ejemplo/profesor/alumnos/77', '/alumnos/77'],
  ['https://admin.ejemplo/profesor/alumnos/77?pestana=asistencia', '/alumnos/77'],
  ['/profesor/alumnos/5', '/alumnos/5'],
])('%s → %s', (enlace, destino) => {
  expect(destinoDe(enlace)).toBe(destino);
});

it.each([null, undefined, '', 42, 'app://desconocido', 'https://admin.ejemplo/profesor/alumnos/', 'https://admin.ejemplo/profesor/alumnos/7x'])(
  'sin destino conocido: %p',
  (enlace) => {
    expect(destinoDe(enlace)).toBeUndefined();
  },
);

it.each([
  ['11 1234-5678', 'https://wa.me/5491112345678'],
  ['011 15 1234-5678', 'https://wa.me/5491112345678'],
  ['+54 9 223 456-7890', 'https://wa.me/5492234567890'],
  ['2234 15 567890', 'https://wa.me/5492234567890'],
])('WhatsApp de %s', (tel, url) => {
  expect(enlaceWhatsApp(tel)).toBe(url);
});

it('sin teléfono no hay WhatsApp', () => {
  expect(enlaceWhatsApp(null)).toBeNull();
  expect(enlaceWhatsApp('123')).toBeNull();
});
