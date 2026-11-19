import { screen } from '@testing-library/react-native';

import Recuperar from '@/app/recuperar';

import { renderizar } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ router: { back: jest.fn(), push: jest.fn(), replace: jest.fn() } }));

beforeEach(() => jest.clearAllMocks());

it('no manda códigos: pide la clave provisoria a administración', async () => {
  await renderizar(<Recuperar />);

  expect(screen.getByText(/pedile a administración o a tu profe una clave provisoria/)).toBeTruthy();
  expect(screen.queryByLabelText('Tu DNI, usuario o correo')).toBeNull();
  expect(screen.queryByText('Mandarme el código')).toBeNull();
});
