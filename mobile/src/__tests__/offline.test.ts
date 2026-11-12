import AsyncStorage from '@react-native-async-storage/async-storage';

import { ApiError } from '@/lib/api';
import { cerrarCola, descartar, encolarAsistencia, establecerUsuarioCola, MAX_INTENTOS, sincronizar, usuarioCola } from '@/lib/offline';

import { simularApi } from './utils';

// Mock en línea: utils importa la cola (vía feedback) y con require('./utils') quedaría con otra instancia del mock.
jest.mock('@/lib/api', () => ({ ...jest.requireActual('@/lib/api'), api: jest.fn() }));

const leerCola = async (u: number | string) => JSON.parse((await AsyncStorage.getItem(`ito.cola_asistencia.v2.${u}`)) ?? '[]');
const planilla = { bloqueId: 3, fecha: '2026-11-09', registros: [{ alumno_id: 1, tipo: 'presente' }] };
const sinRed = () => {
  throw new ApiError(0, 'Sin conexión');
};

beforeEach(async () => {
  jest.clearAllMocks();
  await AsyncStorage.clear();
  await establecerUsuarioCola(null);
});

it('sin red la planilla queda en la cola de la cuenta y se envía al volver la conexión', async () => {
  simularApi({ 'POST bloques/3/asistencia': sinRed });
  await establecerUsuarioCola(5);
  expect(await encolarAsistencia(planilla)).toEqual({ estado: 'pendiente' });
  expect(await leerCola(5)).toHaveLength(1);

  const llamadas = simularApi({ 'POST bloques/3/asistencia': { guardadas: 1 } });
  await sincronizar();
  expect(llamadas[0].body).toMatchObject({ fecha: '2026-11-09', client_uuid: 'uuid-test' });
  expect(await leerCola(5)).toHaveLength(0);
});

it('al cerrar sesión la cola no se envía con la cuenta siguiente', async () => {
  simularApi({ 'POST bloques/3/asistencia': sinRed });
  await establecerUsuarioCola(5);
  await encolarAsistencia(planilla);
  await cerrarCola();
  expect(usuarioCola()).toBeNull();

  const llamadas = simularApi({ 'POST bloques/3/asistencia': { guardadas: 1 } });
  await establecerUsuarioCola(9); // entra otra persona
  await sincronizar();
  expect(llamadas).toHaveLength(0);
  expect(await leerCola(5)).toHaveLength(1); // sigue guardada para la primera cuenta
});

it('la cola de la versión anterior pasa a la cuenta que entra', async () => {
  await AsyncStorage.setItem('ito.cola_asistencia.v1', JSON.stringify([{ ...planilla, uuid: 'viejo', capturadoEn: 'x' }]));
  simularApi({ 'POST bloques/3/asistencia': sinRed });
  await establecerUsuarioCola(7);
  expect(await leerCola(7)).toHaveLength(1);
  expect(await AsyncStorage.getItem('ito.cola_asistencia.v1')).toBeNull();
});

it('un error definitivo (4xx) no se reintenta solo y queda con el detalle', async () => {
  simularApi({ 'POST bloques/3/asistencia': sinRed });
  await establecerUsuarioCola(5);
  await encolarAsistencia(planilla);

  const llamadas = simularApi({
    'POST bloques/3/asistencia': () => {
      throw new ApiError(403, 'No tenés permiso para tomar asistencia en este bloque.');
    },
  });
  await sincronizar();
  const [envio] = await leerCola(5);
  expect(envio).toMatchObject({ fallida: true, error: 'No tenés permiso para tomar asistencia en este bloque.', intentos: 1 });

  await sincronizar(); // no se reintenta sola
  expect(llamadas).toHaveLength(1);
  await sincronizar({ forzar: true }); // a mano sí
  expect(llamadas).toHaveLength(2);

  await descartar(envio.uuid);
  expect(await leerCola(5)).toHaveLength(0);
});

it('los errores del servidor se reintentan hasta un límite', async () => {
  simularApi({ 'POST bloques/3/asistencia': sinRed });
  await establecerUsuarioCola(5);
  await encolarAsistencia(planilla);
  const llamadas = simularApi({
    'POST bloques/3/asistencia': () => {
      throw new ApiError(500, 'El servidor tuvo un problema.');
    },
  });
  for (let i = 0; i < MAX_INTENTOS + 2; i++) await sincronizar();
  expect(llamadas).toHaveLength(MAX_INTENTOS);
  expect((await leerCola(5))[0]).toMatchObject({ fallida: true, intentos: MAX_INTENTOS });
});

it('si el servidor rechaza la planilla al guardarla, avisa y no la deja en la cola', async () => {
  simularApi({
    'POST bloques/3/asistencia': () => {
      throw new ApiError(422, 'La fecha no puede ser futura.');
    },
  });
  await establecerUsuarioCola(5);
  await expect(encolarAsistencia(planilla)).rejects.toThrow('La fecha no puede ser futura.');
  expect(await leerCola(5)).toHaveLength(0);
});
