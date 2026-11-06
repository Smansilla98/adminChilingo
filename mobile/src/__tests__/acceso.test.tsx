import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

import Registro from '@/app/registro';
import { CambiarClave } from '@/features/cuenta/CambiarClave';
import { DarAccesoApp } from '@/features/personas/DarAccesoApp';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-clipboard', () => ({ setStringAsync: jest.fn(async () => true) }));
const mockRegistrarse = jest.fn(async () => undefined);
const mockSalir = jest.fn(async () => undefined);
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ registrarse: mockRegistrarse, salir: mockSalir }) }));
jest.mock('expo-router', () => ({ router: { back: jest.fn(), push: jest.fn() } }));

beforeEach(() => jest.clearAllMocks());

it('el registro pide la fecha de nacimiento y no deja usar el DNI como contraseña', async () => {
  await renderizar(<Registro />);
  await fireEvent.changeText(screen.getByLabelText('Tu DNI'), '30123456');
  await fireEvent.changeText(screen.getByLabelText('Elegí una contraseña'), '30123456');
  await fireEvent.changeText(screen.getByLabelText('Repetila'), '30123456');
  await fireEvent.press(screen.getAllByText('Crear mi cuenta').at(-1)!);

  expect(await screen.findByText('Elegí tu fecha de nacimiento.')).toBeTruthy();
  expect(screen.getByText('No uses tu DNI como contraseña.')).toBeTruthy();
  expect(mockRegistrarse).not.toHaveBeenCalled();
});

it('con la clave provisoria obliga a elegir una propia', async () => {
  const llamadas = simularApi({ 'POST auth/clave': { ok: true } });
  const listo = jest.fn();
  await renderizar(<CambiarClave obligatoria usuario="30123456" alTerminar={listo} />);

  expect(screen.getByText(/Tu usuario es 30123456/)).toBeTruthy();
  await fireEvent.changeText(screen.getByLabelText('Contraseña provisoria'), '482913');
  await fireEvent.changeText(screen.getByLabelText('Contraseña nueva'), 'redoblante');
  await fireEvent.changeText(screen.getByLabelText('Repetila'), 'redoblante');
  await fireEvent.press(screen.getByText('Guardar contraseña'));

  await waitFor(() => expect(listo).toHaveBeenCalled());
  expect(llamadas[0].body).toEqual({ actual: '482913', password: 'redoblante', password_confirmation: 'redoblante' });
});

it('administración da acceso a la app y ve la clave provisoria para compartir', async () => {
  jest.spyOn(Alert, 'alert').mockImplementation((_t, _m, botones) => botones?.[1]?.onPress?.());
  simularApi({ 'POST personas/7/acceso-app': { usuario: '30123456', clave: '482913', nueva: true } });
  await renderizar(<DarAccesoApp personaId={7} tieneCuenta={false} />);

  await fireEvent.press(screen.getByText('Dar acceso a la app'));

  expect(await screen.findByText('Cuenta creada')).toBeTruthy();
  expect(screen.getByText('482913')).toBeTruthy();
  expect(screen.getByText('Compartir')).toBeTruthy();
});
