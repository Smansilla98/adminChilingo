import { fireEvent, screen } from '@testing-library/react-native';
import { router } from 'expo-router';

import AlumnosLista from '@/app/(app)/alumnos/index';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ router: { push: jest.fn() }, Stack: { Screen: () => null } }));
jest.mock('@/lib/permisos', () => ({
  usePuede: () => false,
  usePermisos: () => ({ puede: () => false }),
}));

beforeEach(() => jest.clearAllMocks());

it('la lista de alumnos muestra la primera página y abre la ficha al tocar', async () => {
  const llamadas = simularApi({
    'GET alumnos': {
      data: [
        { id: 1, nombre: 'Ana Pérez', sede: { nombre: 'Quilmes' }, bloques: [{ id: 3, nombre: 'Martes' }] },
        { id: 2, nombre: 'Beto Gómez', sede: null, bloques: [] },
      ],
      meta: { current_page: 1, last_page: 2, total: 40 },
    },
  });
  await renderizar(<AlumnosLista />);

  expect(await screen.findByText('Ana Pérez')).toBeTruthy();
  expect(screen.getByText('Beto Gómez')).toBeTruthy();
  expect(llamadas.some((l) => l.ruta.startsWith('alumnos'))).toBe(true);

  await fireEvent.press(screen.getByLabelText('Ana Pérez, Quilmes · Martes'));
  expect(router.push).toHaveBeenCalledWith({ pathname: '/alumnos/[id]', params: { id: '1' } });
});
