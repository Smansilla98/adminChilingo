import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import Recuperar from '@/app/recuperar';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ router: { back: jest.fn(), push: jest.fn(), replace: jest.fn() } }));

beforeEach(() => jest.clearAllMocks());

it('pide el código con el DNI y pasa al paso de elegir la contraseña nueva', async () => {
  const llamadas = simularApi({
    'POST auth/recuperar': { ok: true, mensaje: 'Si los datos corresponden a una cuenta, te mandamos un código.' },
  });
  await renderizar(<Recuperar />);

  await fireEvent.changeText(screen.getByLabelText('Tu DNI, usuario o correo'), ' 30123456 ');
  await fireEvent.press(screen.getByText('Mandarme el código'));

  expect(await screen.findByLabelText('Código')).toBeTruthy();
  expect(screen.getByText(/Si los datos corresponden a una cuenta/)).toBeTruthy();
  expect(llamadas[0].body).toEqual({ usuario: '30123456' });
});

it('no deja confirmar sin código ni con una contraseña corta', async () => {
  simularApi({ 'POST auth/recuperar': { ok: true, mensaje: 'ok' } });
  await renderizar(<Recuperar />);
  await fireEvent.changeText(screen.getByLabelText('Tu DNI, usuario o correo'), '30123456');
  await fireEvent.press(screen.getByText('Mandarme el código'));
  await screen.findByLabelText('Código');

  await fireEvent.changeText(screen.getByLabelText('Contraseña nueva'), 'corta');
  await fireEvent.changeText(screen.getByLabelText('Repetila'), 'corta');
  await fireEvent.press(screen.getByText('Guardar contraseña'));

  expect(await screen.findByText('Escribí el código que te mandamos.')).toBeTruthy();
  expect(screen.getByText('Al menos 8 letras o números.')).toBeTruthy();
});

it('confirma con el código y la contraseña nueva en mayúsculas y sin espacios', async () => {
  const llamadas = simularApi({
    'POST auth/recuperar': { ok: true, mensaje: 'ok' },
    'POST auth/recuperar/confirmar': { ok: true },
  });
  await renderizar(<Recuperar />);
  await fireEvent.changeText(screen.getByLabelText('Tu DNI, usuario o correo'), '30123456');
  await fireEvent.press(screen.getByText('Mandarme el código'));
  await screen.findByLabelText('Código');

  await fireEvent.changeText(screen.getByLabelText('Código'), ' ab3k9q ');
  await fireEvent.changeText(screen.getByLabelText('Contraseña nueva'), 'redoblante2');
  await fireEvent.changeText(screen.getByLabelText('Repetila'), 'redoblante2');
  await fireEvent.press(screen.getByText('Guardar contraseña'));

  await waitFor(() => expect(llamadas.at(-1)?.ruta).toBe('auth/recuperar/confirmar'));
  expect(llamadas.at(-1)?.body).toEqual({
    usuario: '30123456',
    codigo: 'AB3K9Q',
    password: 'redoblante2',
    password_confirmation: 'redoblante2',
  });
});
