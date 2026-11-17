import AsyncStorage from '@react-native-async-storage/async-storage';
import type { Query } from '@tanstack/react-query';

import { debePersistir, limpiarCacheAnterior } from '@/lib/cache';

const consulta = (queryKey: unknown[]) => ({ queryKey, state: { status: 'success', data: {} } }) as unknown as Query;

beforeEach(async () => {
  await AsyncStorage.clear();
});

it.each([
  [['catalogo', 'alumnos/catalogo']],
  [['calendario', '2026-11-01', '2026-11-14']],
  [['partituras', false]],
  [['archivo', 'resumen']],
  [['ciclo', 'lista']],
  [['inventario', '']],
])('se guarda en el teléfono solo lo no personal: %j', (clave) => {
  expect(debePersistir(consulta(clave))).toBe(true);
});

it.each([
  [['alumnos', 'lista', {}]],
  [['alumnos', 'detalle', '4']],
  [['alumnos', 'cuenta', '4']],
  [['personas', 'lista']],
  [['me']],
  [['mi', 'estado-cuenta']],
  [['pagos', 'lista']],
  [['planilla', 3, '2026-11-09']],
  [['bloques', 'alumnos', 3]],
  [['avisos']],
  [['inicio']],
  [['usuarios', 'detalle', '9']],
])('no se guarda en el teléfono: %j', (clave) => {
  expect(debePersistir(consulta(clave))).toBe(false);
});

it('al arrancar borra las cachés de versiones anteriores', async () => {
  await AsyncStorage.setItem('ito.cache.v2', '{"alumnos":"Ana"}');
  await AsyncStorage.setItem('ito.cache.v1', '{}');
  await limpiarCacheAnterior();
  expect(await AsyncStorage.getItem('ito.cache.v2')).toBeNull();
  expect(await AsyncStorage.getItem('ito.cache.v1')).toBeNull();
});
