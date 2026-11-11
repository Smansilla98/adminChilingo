import AsyncStorage from '@react-native-async-storage/async-storage';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react-native';
import * as SecureStore from 'expo-secure-store';
import { Text } from 'react-native';

import { ApiError, procesarRespuesta } from '@/lib/api';
import { AuthProvider, useAuth } from '@/lib/auth';
import { usuarioCola } from '@/lib/offline';
import { guardarTokenPush } from '@/lib/token-push';

import { simularApi } from './utils';

jest.mock('@/lib/api', () => ({ ...jest.requireActual('@/lib/api'), api: jest.fn() }));

// SecureStore en memoria.
const mockGuardado = new Map<string, string>();
beforeEach(async () => {
  jest.clearAllMocks();
  mockGuardado.clear();
  await AsyncStorage.clear();
  (SecureStore.getItemAsync as jest.Mock).mockImplementation(async (k: string) => mockGuardado.get(k) ?? null);
  (SecureStore.setItemAsync as jest.Mock).mockImplementation(async (k: string, v: string) => void mockGuardado.set(k, v));
  (SecureStore.deleteItemAsync as jest.Mock).mockImplementation(async (k: string) => void mockGuardado.delete(k));
});

const me = { usuario: { id: 5, username: 'ana', email: 'a@x', nombre: 'Ana' }, funciones: [], contextos: [], modulos: [], permisos: [] };
let auth: ReturnType<typeof useAuth>;
const mockExponer = (a: ReturnType<typeof useAuth>) => {
  auth = a;
};
function Sonda({ exponer }: { exponer: typeof mockExponer }) {
  const a = useAuth();
  exponer(a);
  return <Text>{a.listo ? (a.autenticado ? 'adentro' : 'afuera') : 'cargando'}</Text>;
}

async function montar() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: Infinity } } });
  await render(
    <QueryClientProvider client={qc}>
      <AuthProvider>
        <Sonda exponer={mockExponer} />
      </AuthProvider>
    </QueryClientProvider>,
  );
  return qc;
}

it('al ingresar guarda el token y separa la cola offline por cuenta', async () => {
  const llamadas = simularApi({ 'POST auth/login': { token: 't1', expira: null }, 'GET me': me });
  await montar();
  expect(await screen.findByText('afuera')).toBeTruthy();

  await act(() => auth.ingresar(' ana ', 'clave'));
  expect(screen.getByText('adentro')).toBeTruthy();
  expect(llamadas[0].body).toMatchObject({ username: 'ana', password: 'clave' });
  expect(mockGuardado.get('ito.token')).toBe('t1');
  expect(mockGuardado.get('ito.usuario_id')).toBe('5');
  expect(usuarioCola()).toBe(5);
});

it('al salir manda el token de push, borra la sesión y vacía la caché', async () => {
  mockGuardado.set('ito.token', 't1').set('ito.usuario_id', '5');
  await guardarTokenPush('ExponentPushToken[abc]');
  await AsyncStorage.setItem('ito.cache.v2', '{"x":1}');
  const llamadas = simularApi({ 'POST auth/logout': { ok: true } });
  const qc = await montar();
  expect(await screen.findByText('adentro')).toBeTruthy();
  qc.setQueryData(['personas', 'detalle', '1'], { dni: '123' });

  await act(() => auth.salir());
  expect(screen.getByText('afuera')).toBeTruthy();
  expect(llamadas.find((l) => l.ruta === 'auth/logout')?.body).toEqual({ push_token: 'ExponentPushToken[abc]' });
  expect(mockGuardado.has('ito.token')).toBe(false);
  expect(mockGuardado.has('ito.usuario_id')).toBe(false);
  expect(usuarioCola()).toBeNull();
  expect(qc.getQueryData(['personas', 'detalle', '1'])).toBeUndefined();
  expect(await AsyncStorage.getItem('ito.cache.v2')).toBeNull();
  expect(await AsyncStorage.getItem('ito.push_token')).toBeNull();
});

it('si el logout falla, igual intenta quitar el dispositivo y cierra la sesión local', async () => {
  mockGuardado.set('ito.token', 't1').set('ito.usuario_id', '5');
  await guardarTokenPush('ExponentPushToken[abc]');
  const llamadas = simularApi({
    'POST auth/logout': () => {
      throw new ApiError(500, 'El servidor tuvo un problema.');
    },
    'DELETE dispositivos': { ok: true },
  });
  await montar();
  expect(await screen.findByText('adentro')).toBeTruthy();

  await act(() => auth.salir());
  expect(llamadas.find((l) => l.metodo === 'DELETE')?.body).toEqual({ token: 'ExponentPushToken[abc]' });
  expect(screen.getByText('afuera')).toBeTruthy();
});

it('un 401 del servidor cierra la sesión', async () => {
  mockGuardado.set('ito.token', 't1').set('ito.usuario_id', '5');
  await montar();
  expect(await screen.findByText('adentro')).toBeTruthy();

  await act(async () => {
    expect(() => procesarRespuesta(401, '{"message":"Unauthenticated."}')).toThrow('Tu sesión venció');
  });
  await waitFor(() => expect(screen.getByText('afuera')).toBeTruthy());
  expect(mockGuardado.has('ito.token')).toBe(false);
});

it('al abrir renueva el token si está por vencer', async () => {
  const pronto = new Date(Date.now() + 2 * 86_400_000).toISOString();
  mockGuardado.set('ito.token', 'viejo').set('ito.token_expira', pronto).set('ito.usuario_id', '5');
  const llamadas = simularApi({ 'POST auth/refresh': { token: 'nuevo', expira: '2027-01-01T00:00:00Z' } });
  await montar();
  expect(await screen.findByText('adentro')).toBeTruthy();
  expect(llamadas.map((l) => l.ruta)).toContain('auth/refresh');
  expect(mockGuardado.get('ito.token')).toBe('nuevo');
});

it('con el token lejos de vencer no lo renueva', async () => {
  const lejos = new Date(Date.now() + 30 * 86_400_000).toISOString();
  mockGuardado.set('ito.token', 't1').set('ito.token_expira', lejos).set('ito.usuario_id', '5');
  const llamadas = simularApi({});
  await montar();
  expect(await screen.findByText('adentro')).toBeTruthy();
  expect(llamadas).toHaveLength(0);
});
