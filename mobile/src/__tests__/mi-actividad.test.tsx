import { fireEvent, screen } from '@testing-library/react-native';
import { router } from 'expo-router';

import MiActividad from '@/app/(app)/mi-actividad';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ router: { push: jest.fn() }, Stack: { Screen: () => null } }));

const item = (cuota_id: number, nombre: string, estado: string, neto: number, pagado: number, extra: object = {}) => ({
  cuota_id, nombre, periodo: '', vencimiento: null, bruto: neto, beca: null, descuento: 0, neto, pagado, saldo: Math.max(0, neto - pagado), estado, ...extra,
});

beforeEach(() => {
  jest.clearAllMocks();
  simularApi({
    'GET mi/estado-cuenta': {
      cuentas: [{
        alumno_id: 7, anio: 2026, becas: [], totales: { bruto: 0, descuento: 0, neto: 0, pagado: 0, saldo: 25000, vencido: 0 },
        items: [
          item(1, 'Octubre', 'pendiente', 25000, 0, { vencimiento: '2026-11-10' }),
          item(2, 'Septiembre', 'pagada', 20000, 20000, { pagado_el: '2026-09-08' }),
          item(3, 'Agosto', 'pagada', 20000, 20000, { pagado_el: '2026-08-05' }),
        ],
      }],
    },
    'GET partituras': { data: [
      { slug: 'marcha', nombre: 'Marcha camión', anio: 1, autor: 'La Chilinga', tiene_partitura: true },
      { slug: 'candombe', nombre: 'Candombe', anio: 2, autor: null, tiene_partitura: false },
    ] },
  });
});

it('muestra qué debe, lo que ya pagó y el material de estudio del alumno', async () => {
  await renderizar(<MiActividad />);

  expect(await screen.findByText('Tenés para pagar')).toBeTruthy();
  expect(screen.getByText('1 cuota pendiente · 2 pagadas')).toBeTruthy();
  expect(screen.getByText('Octubre · $ 25.000')).toBeTruthy();

  await fireEvent.press(screen.getByText('Pagar o enviar comprobante'));
  expect(router.push).toHaveBeenCalledWith('/mi-cuenta');

  await fireEvent.press(screen.getByText('Pagadas (2)'));
  expect(screen.getByText('Septiembre · $ 20.000')).toBeTruthy();
  expect(screen.getAllByText(/^Pagada el /)).toHaveLength(2);
  expect(screen.queryByText('Octubre · $ 25.000')).toBeNull();

  await fireEvent.press(screen.getByText('Material de estudio'));
  expect(screen.getByText('Toques de 2° año')).toBeTruthy();
  await fireEvent.press(screen.getByText('Marcha camión'));
  expect(router.push).toHaveBeenCalledWith({ pathname: '/partituras/[slug]', params: { slug: 'marcha' } });
});
