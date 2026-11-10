import AsyncStorage from '@react-native-async-storage/async-storage';
import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

import TomarAsistencia from '@/app/(app)/asistencia/[bloque]';
import { ApiError } from '@/lib/api';
import { establecerUsuarioCola } from '@/lib/offline';

import { renderizar, simularApi } from './utils';

// Mock en línea (la cola offline tiene que ver la misma instancia que el test).
jest.mock('@/lib/api', () => ({ ...jest.requireActual('@/lib/api'), api: jest.fn() }));

let mockAntesDeSalir: ((e: unknown) => void) | undefined;
const mockDispatch = jest.fn();
jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  Stack: { Screen: () => null },
  useLocalSearchParams: () => ({ bloque: '3', nombre: 'Martes', fecha: '2026-11-09' }),
  useNavigation: () => ({
    addListener: (_: string, fn: (e: unknown) => void) => {
      mockAntesDeSalir = fn;
      return () => undefined;
    },
    dispatch: mockDispatch,
  }),
}));

const planilla = {
  bloque: { id: 3, nombre: 'Martes' },
  fecha: '2026-11-09',
  tomada: true,
  puede_editar: true,
  tipos: {},
  alumnos: [
    { alumno_id: 1, nombre: 'Ana Pérez', tipo: 'presente', asistencia_id: 9 },
    { alumno_id: 2, nombre: 'Beto Gómez', tipo: null, asistencia_id: null },
  ],
};

/** Toca el botón con ese texto en el próximo Alert. */
const responderAlerta = (boton: string) =>
  jest.spyOn(Alert, 'alert').mockImplementationOnce((_t, _m, botones) => botones?.find((b) => b.text === boton)?.onPress?.());

beforeEach(async () => {
  jest.clearAllMocks();
  mockAntesDeSalir = undefined;
  await AsyncStorage.clear();
  await establecerUsuarioCola(5);
});

it('tocar alterna presente/ausente y guarda la planilla', async () => {
  const llamadas = simularApi({ 'GET bloques/3/asistencia': planilla, 'POST bloques/3/asistencia': { guardadas: 2 } });
  await renderizar(<TomarAsistencia />);

  await fireEvent.press(await screen.findByLabelText('Beto Gómez: sin marcar'));
  expect(screen.getByLabelText('Beto Gómez: Presente')).toBeTruthy();
  await fireEvent.press(screen.getByLabelText('Beto Gómez: Presente'));
  expect(screen.getByLabelText('Beto Gómez: Ausente')).toBeTruthy();

  await fireEvent.press(screen.getByText('Guardar asistencia'));
  await waitFor(() => expect(screen.getByText('✓ Asistencia guardada.')).toBeTruthy());
  const envio = llamadas.find((l) => l.metodo === 'POST');
  expect(envio?.body).toMatchObject({
    fecha: '2026-11-09',
    registros: [
      { alumno_id: 1, tipo: 'presente' },
      { alumno_id: 2, tipo: 'ausencia_injustificada' },
    ],
  });
});

it('las opciones de tarde/justificado/feriado se pueden usar con el lector de pantalla', async () => {
  simularApi({ 'GET bloques/3/asistencia': planilla });
  await renderizar(<TomarAsistencia />);
  const fila = await screen.findByLabelText('Beto Gómez: sin marcar');
  expect(fila.props.accessibilityActions.map((a: { name: string }) => a.name)).toEqual(expect.arrayContaining(['activate', 'tarde', 'ausencia_justificada', 'feriado']));

  await fireEvent(fila, 'accessibilityAction', { nativeEvent: { actionName: 'tarde' } });
  expect(screen.getByLabelText('Beto Gómez: Tarde')).toBeTruthy();
});

it('borra una marca ya guardada', async () => {
  const llamadas = simularApi({ 'GET bloques/3/asistencia': planilla, 'DELETE asistencias/9': { ok: true } });
  await renderizar(<TomarAsistencia />);

  responderAlerta('Borrar');
  await fireEvent(await screen.findByLabelText('Ana Pérez: Presente'), 'accessibilityAction', { nativeEvent: { actionName: 'quitar' } });
  await waitFor(() => expect(llamadas.some((l) => l.metodo === 'DELETE' && l.ruta === 'asistencias/9')).toBe(true));
});

it('pregunta antes de salir con marcas sin guardar', async () => {
  simularApi({ 'GET bloques/3/asistencia': planilla });
  await renderizar(<TomarAsistencia />);
  await fireEvent.press(await screen.findByLabelText('Beto Gómez: sin marcar'));

  const evento = { preventDefault: jest.fn(), data: { action: { type: 'GO_BACK' } } };
  responderAlerta('Salir sin guardar');
  mockAntesDeSalir?.(evento);
  expect(evento.preventDefault).toHaveBeenCalled();
  expect(mockDispatch).toHaveBeenCalledWith({ type: 'GO_BACK' });
});

it('sin conexión y sin datos guardados de esa fecha lo explica y deja volver a hoy', async () => {
  simularApi({
    'GET bloques/3/asistencia': () => {
      throw new ApiError(0, 'Sin conexión');
    },
  });
  await renderizar(<TomarAsistencia />);
  expect(await screen.findByText(/no hay datos guardados de esa fecha/)).toBeTruthy();
  expect(screen.getByLabelText(/Día de la clase/)).toBeTruthy();
  expect(screen.getByText('Volver a hoy')).toBeTruthy();
});
