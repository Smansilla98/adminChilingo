import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import LiquidacionesGestion from '@/app/(app)/liquidaciones/index';
import { api } from '@/lib/api';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('@/lib/archivos', () => ({ pdfDesdeServidor: jest.fn(async () => undefined) }));
jest.mock('expo-router', () => ({ Stack: { Screen: () => null } }));

const fila = (estado: string | null) => ({
  profesor: { id: 4, nombre: 'Docente Uno' },
  modalidad: 'hora',
  liquidacion: estado ? { id: 9, numero: 'LD-1', periodo: 'Octubre 2026', estado, estado_nombre: estado === 'borrador' ? 'En preparación' : 'Aprobada', modalidad: 'hora', horas_total: 6, monto_total: 9000, monto_horas: 9000, monto_porcentaje: 0 } : null,
});

beforeEach(() => jest.clearAllMocks());

it('lista las liquidaciones del mes y aprueba un borrador', async () => {
  simularApi({
    'GET gestion/liquidaciones': { data: [fila('borrador')], anio: 2026, mes: 10, puede_gestionar: true },
    'POST gestion/liquidaciones/9/aprobar': { data: { id: 9, estado: 'aprobada' } },
  });
  await renderizar(<LiquidacionesGestion />);
  expect(await screen.findByText('Docente Uno')).toBeTruthy();
  expect(screen.getByText('En preparación')).toBeTruthy();
  fireEvent.press(screen.getByText('Aprobar'));
  await waitFor(() => expect(api).toHaveBeenCalledWith('gestion/liquidaciones/9/aprobar', { method: 'POST' }));
});

it('quien solo ve no tiene botones de gestión', async () => {
  simularApi({
    'GET gestion/liquidaciones': { data: [fila('aprobada')], anio: 2026, mes: 10, puede_gestionar: false },
  });
  await renderizar(<LiquidacionesGestion />);
  expect(await screen.findByText('Docente Uno')).toBeTruthy();
  expect(screen.queryByText('Marcar pagada')).toBeNull();
  expect(screen.getByText('Podés ver las liquidaciones; aprobar y pagar lo hace administración.')).toBeTruthy();
});
