import { C } from '@/lib/theme';

/** Contraste WCAG (1.4.3): texto normal 4.5:1 como mínimo. */
function luminancia(hex: string): number {
  const canales = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * canales[0] + 0.7152 * canales[1] + 0.0722 * canales[2];
}
const contraste = (a: string, b: string) => {
  const [claro, oscuro] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
  return (claro + 0.05) / (oscuro + 0.05);
};

it('el texto blanco sobre el fondo de botones cumple WCAG AA', () => {
  expect(contraste(C.texto, C.acentoBoton)).toBeGreaterThanOrEqual(4.5);
});

it('el texto tenue sobre el fondo negro y las superficies cumple WCAG AA', () => {
  expect(contraste(C.tenue, C.fondo)).toBeGreaterThanOrEqual(4.5);
  expect(contraste(C.tenue, C.superficie)).toBeGreaterThanOrEqual(4.5);
});
