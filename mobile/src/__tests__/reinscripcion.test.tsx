import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

import { MiReinscripcion } from '@/features/reinscripcion/MiReinscripcion';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const ciclo = { anio: 2027, nombre: 'Ciclo 2027', inicio: '2027-02-01', fin: '2027-11-30' };

beforeEach(() => jest.clearAllMocks());

it('muestra el estado de cada alumno y confirma las pendientes', async () => {
  const llamadas = simularApi({
    'GET mi/reinscripcion': { ciclo, alumnos: [{ id: 1, nombre: 'Ana Pérez', estado: 'pendiente' }, { id: 2, nombre: 'Beto Gómez', estado: 'confirmada' }] },
    'POST mi/reinscripcion': { ok: true, confirmadas: 1 },
  });
  jest.spyOn(Alert, 'alert').mockImplementationOnce((_t, _m, botones) => botones?.find((b) => b.text === 'Confirmar')?.onPress?.());
  await renderizar(<MiReinscripcion />);

  expect(await screen.findByText('Ciclo 2027')).toBeTruthy();
  expect(screen.getByText('Pendiente')).toBeTruthy();
  expect(screen.getByText('Confirmada')).toBeTruthy();

  await fireEvent.press(screen.getByText('Confirmar mi reinscripción'));
  await waitFor(() => expect(llamadas.some((l) => l.metodo === 'POST' && l.ruta === 'mi/reinscripcion')).toBe(true));
});

it('sin ciclo abierto lo dice y no ofrece confirmar', async () => {
  simularApi({ 'GET mi/reinscripcion': { ciclo: null, alumnos: [] } });
  await renderizar(<MiReinscripcion />);
  expect(await screen.findByText('Todavía no hay un ciclo lectivo abierto para reinscribirse.')).toBeTruthy();
  expect(screen.queryByText(/Confirmar/)).toBeNull();
});

it('si ya están todos confirmados no muestra el botón', async () => {
  simularApi({ 'GET mi/reinscripcion': { ciclo, alumnos: [{ id: 1, nombre: 'Ana Pérez', estado: 'confirmada' }] } });
  await renderizar(<MiReinscripcion />);
  expect(await screen.findByText('Todos tus alumnos están reinscriptos para este ciclo.')).toBeTruthy();
  expect(screen.queryByText(/Confirmar/)).toBeNull();
});
