import { screen } from '@testing-library/react-native';

import AgendaTab from '@/app/(app)/(tabs)/agenda';
import PagosDocente from '@/app/(app)/pagos/docente';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn() }, Stack: { Screen: () => null } }));

beforeEach(() => jest.clearAllMocks());

it('la agenda muestra los días y sus eventos en la lista', async () => {
  const hoy = new Date();
  const fecha = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
  simularApi({
    'GET calendario': {
      items: [
        { id: 'evento-4', fecha, inicio: '18:00', fin: null, titulo: 'Ensayo general', tipo: 'show', sede: 'Quilmes', bloque_id: null },
        { id: 'clase-3-' + fecha, fecha, inicio: '19:00', fin: '21:00', titulo: 'Martes noche', tipo: 'clase', sede: null, bloque_id: 3 },
      ],
    },
  });
  await renderizar(<AgendaTab />);
  expect(await screen.findByText(/18:00 · Ensayo general/)).toBeTruthy();
  expect(screen.getByText(/19:00–21:00 · Martes noche/)).toBeTruthy();
  expect(screen.getByText('Quilmes')).toBeTruthy();
});

it('la agenda vacía lo dice', async () => {
  simularApi({ 'GET calendario': { items: [] } });
  await renderizar(<AgendaTab />);
  expect(await screen.findByText('Nada agendado en estas dos semanas.')).toBeTruthy();
});

it('pagos del docente lista los pagos y el total de abonos', async () => {
  simularApi({
    'GET mi/pagos-docente': {
      data: [{ id: 1, pago_id: 1, fecha: '2026-11-03', alumno: 'Ana Pérez', cuota: 'Octubre', monto: 25000, abono_profesor: 5000, anulado: false }],
      meta: { current_page: 1, last_page: 1, total: 1 },
      total_abono: 5000,
      alumnos: [{ id: 7, nombre: 'Ana Pérez' }],
    },
  });
  await renderizar(<PagosDocente />);
  expect(await screen.findByText('Ana Pérez · $ 25.000')).toBeTruthy();
  expect(screen.getByText('Tu abono: $ 5.000')).toBeTruthy();
  expect(screen.getByText('$ 5.000')).toBeTruthy();
});

it('pagos del docente sin pagos lo dice', async () => {
  simularApi({ 'GET mi/pagos-docente': { data: [], meta: { current_page: 1, last_page: 1, total: 0 }, total_abono: 0, alumnos: [] } });
  await renderizar(<PagosDocente />);
  expect(await screen.findByText('No hay pagos registrados de tus alumnos.')).toBeTruthy();
});
