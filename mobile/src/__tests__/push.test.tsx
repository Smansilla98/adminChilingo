import { act, fireEvent, renderHook, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import type { ReactNode } from 'react';
import { Linking } from 'react-native';

import { ActivarPush } from '@/features/avisos/ActivarPush';
import { destinoDe } from '@/lib/destinos';
import { useNotificacionesPush } from '@/lib/push';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-device', () => ({ isDevice: true, modelName: 'Pixel' }));
jest.mock('expo-constants', () => ({ expoConfig: { extra: { eas: { projectId: 'p1' } } } }));

let alTocar: ((r: unknown) => void) | undefined;
jest.mock('expo-notifications', () => ({
  DEFAULT_ACTION_IDENTIFIER: 'expo.modules.notifications.actions.DEFAULT',
  AndroidImportance: { HIGH: 4 },
  setNotificationHandler: jest.fn(),
  setNotificationChannelAsync: jest.fn(),
  getPermissionsAsync: jest.fn(),
  requestPermissionsAsync: jest.fn(),
  getExpoPushTokenAsync: jest.fn(async () => ({ data: 'ExponentPushToken[abc]' })),
  getLastNotificationResponse: jest.fn(() => null),
  clearLastNotificationResponse: jest.fn(),
  addNotificationResponseReceivedListener: jest.fn((fn: (r: unknown) => void) => {
    alTocar = fn;
    return { remove: jest.fn() };
  }),
  addNotificationReceivedListener: jest.fn(() => ({ remove: jest.fn() })),
}));

const toque = (enlace: string | null) => ({
  actionIdentifier: 'expo.modules.notifications.actions.DEFAULT',
  notification: { request: { content: { data: { enlace } } } },
});

const cliente = new QueryClient();
const envolver = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cliente}>{children}</QueryClientProvider>;

beforeEach(() => {
  jest.clearAllMocks();
  alTocar = undefined;
  (Notifications.getLastNotificationResponse as jest.Mock).mockReturnValue(null);
});

it('traduce los enlaces de los avisos a pantallas de la app', () => {
  expect(destinoDe('app://mi-cuenta')).toBe('/mi-cuenta');
  expect(destinoDe('app://cuotas')).toBe('/mi-cuenta');
  expect(destinoDe('https://x/archivo/mis-aportes/12')).toBe('/archivo/aportes/12');
  expect(destinoDe(null)).toBeUndefined();
  expect(destinoDe(42)).toBeUndefined();
});

it('al tocar la notificación de una cuota abre Mi cuenta', async () => {
  await renderHook(() => useNotificacionesPush(true), { wrapper: envolver });
  await act(async () => alTocar?.(toque('app://mi-cuenta')));
  expect(router.push).toHaveBeenCalledWith('/mi-cuenta');
});

it('si la app se abrió desde una notificación sin destino, va a la bandeja de avisos', async () => {
  (Notifications.getLastNotificationResponse as jest.Mock).mockReturnValue(toque(null));
  await renderHook(() => useNotificacionesPush(true), { wrapper: envolver });
  expect(router.push).toHaveBeenCalledWith('/avisos');
  expect(Notifications.clearLastNotificationResponse).toHaveBeenCalled();
});

it('sin sesión no escucha notificaciones', async () => {
  await renderHook(() => useNotificacionesPush(false), { wrapper: envolver });
  expect(Notifications.addNotificationResponseReceivedListener).not.toHaveBeenCalled();
});

it('sin permiso ofrece activarlas y registra el celular', async () => {
  const llamadas = simularApi({ 'POST dispositivos': { ok: true } });
  (Notifications.getPermissionsAsync as jest.Mock)
    .mockResolvedValueOnce({ status: 'undetermined', canAskAgain: true }) // tarjeta
    .mockResolvedValueOnce({ status: 'undetermined', canAskAgain: true }) // registrarPush
    .mockResolvedValue({ status: 'granted', canAskAgain: true });
  (Notifications.requestPermissionsAsync as jest.Mock).mockResolvedValue({ status: 'granted' });
  await renderizar(<ActivarPush />);

  await fireEvent.press(await screen.findByText('Activar'));
  await waitFor(() => expect(llamadas.find((l) => l.ruta === 'dispositivos')?.body).toMatchObject({ token: 'ExponentPushToken[abc]' }));
  await waitFor(() => expect(screen.queryByText('Activá las notificaciones')).toBeNull());
});

it('si las bloqueó, lleva a los ajustes del celular', async () => {
  (Notifications.getPermissionsAsync as jest.Mock).mockResolvedValue({ status: 'denied', canAskAgain: false });
  const ajustes = jest.spyOn(Linking, 'openSettings').mockResolvedValue();
  await renderizar(<ActivarPush />);

  await fireEvent.press(await screen.findByText('Abrir ajustes del celular'));
  expect(ajustes).toHaveBeenCalled();
});
