import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import { FormPago } from '@/features/finanzas/FormPago';
import { ApiError } from '@/lib/api';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
let mockN = 0;
jest.mock('expo-crypto', () => ({ randomUUID: () => `uuid-${++mockN}` }));

const cuotas = { data: [{ id: 3, monto: 24000, label: 'Marzo 2026 — Cuota — $ 24.000', nombre: 'Cuota', anio: 2026, mes: 3, alcance: 'general', bloque: null, sede_nombre: 'Banfield', activo: true, abono_docente_ref: 9600, liquidacion_resumen: null }] };
const cuenta = {
  alumno_id: 7, anio: 2026, becas: [], totales: { bruto: 0, descuento: 0, neto: 0, pagado: 0, saldo: 24000, vencido: 0 },
  items: [{ cuota_id: 3, nombre: 'Marzo', periodo: '03/2026', vencimiento: null, bruto: 24000, beca: null, descuento: 0, neto: 24000, pagado: 0, saldo: 24000, estado: 'pendiente' }],
};

beforeEach(() => jest.clearAllMocks());

it('desde el alumno: suma su cuota pendiente y registra el pago con liquidación de la sede', async () => {
  const llamadas = simularApi({
    'GET pagos/cuotas-para-cobrar': cuotas,
    'GET alumnos/7/estado-cuenta': cuenta,
    'GET alumnos/7': { data: { nombre: 'Ana Paz' } },
    'POST pagos': { data: { id: 90 } },
  });
  await renderizar(<FormPago alumnoId={7} />);

  await fireEvent.press(await screen.findByLabelText('Agregar Marzo por $ 24.000'));
  expect(screen.getByText('Según la regla de cada sede: $ 9.600')).toBeTruthy();
  await fireEvent.press(screen.getByLabelText('Registrar pago'));

  await waitFor(() => expect(router.replace).toHaveBeenCalledWith({ pathname: '/pagos/[id]', params: { id: '90' } }));
  const post = llamadas.find((l) => l.metodo === 'POST');
  expect(post?.body).toMatchObject({
    monto_total: 24000,
    liquidar_profesor: '1',
    monto_abono_profesor: null,
    lineas: [{ alumno_id: 7, cuota_id: 3, monto: 24000 }],
  });
  expect((post?.body as { client_uuid: string }).client_uuid).toMatch(/^uuid-/);
});

it('muestra junto a la línea el error del servidor y reintenta con el mismo client_uuid', async () => {
  let intento = 0;
  const llamadas = simularApi({
    'GET pagos/cuotas-para-cobrar': cuotas,
    'GET alumnos/7/estado-cuenta': cuenta,
    'GET alumnos/7': { data: { nombre: 'Ana Paz' } },
    'POST pagos': () => {
      intento++;
      if (intento === 1) throw new ApiError(422, 'Este alumno ya tiene pago registrado para la cuota elegida en esa línea (en otro pago).', { 'lineas.0.alumno_id': ['Este alumno ya tiene pago registrado para la cuota elegida en esa línea (en otro pago).'] });
      throw new ApiError(0, 'Sin conexión. Revisá internet e intentá de nuevo.');
    },
  });
  await renderizar(<FormPago alumnoId={7} />);
  await fireEvent.press(await screen.findByLabelText('Agregar Marzo por $ 24.000'));

  await fireEvent.press(screen.getByLabelText('Registrar pago'));
  expect((await screen.findAllByText('Este alumno ya tiene pago registrado para la cuota elegida en esa línea (en otro pago).')).length).toBeGreaterThan(0);

  await fireEvent.press(screen.getByLabelText('Registrar pago'));
  await waitFor(() => expect(llamadas.filter((l) => l.metodo === 'POST')).toHaveLength(2));
  const [a, b] = llamadas.filter((l) => l.metodo === 'POST').map((l) => (l.body as { client_uuid: string }).client_uuid);
  expect(a).toBe(b);
  expect(router.replace).not.toHaveBeenCalled();
});

it('no envía líneas incompletas', async () => {
  const llamadas = simularApi({ 'GET pagos/cuotas-para-cobrar': cuotas });
  await renderizar(<FormPago />);
  await fireEvent.press(await screen.findByLabelText('Registrar pago'));
  expect(await screen.findByText('Elegí la cuota.')).toBeTruthy();
  expect(screen.getByText('Elegí el alumno.')).toBeTruthy();
  expect(llamadas.some((l) => l.metodo === 'POST')).toBe(false);
});
