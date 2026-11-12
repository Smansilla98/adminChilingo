import { screen } from '@testing-library/react-native';

import MisPrestamos from '@/app/(app)/mis-prestamos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const prestamo = {
  id: 1,
  item: { id: 3, nombre: 'Surdo 22', codigo: 'CHL-0003', sede: 'Palomar' },
  responsable: 'Ana',
  alumno_id: 9,
  persona_id: null,
  entregado_el: '2026-11-01',
  devolucion_prevista: '2026-11-20',
  devuelto_el: null,
  estado: 'prestado',
  estado_nombre: 'Prestado',
  deposito: null,
  deposito_devuelto: false,
  condicion_entrega: 'bueno',
  condicion_devolucion: null,
  notas: null,
};

it('muestra los instrumentos que el alumno tiene prestados', async () => {
  simularApi({ 'GET mi/prestamos': { data: [prestamo] } });
  await renderizar(<MisPrestamos />);

  expect(await screen.findByText('Surdo 22')).toBeTruthy();
  expect(screen.getByText('Prestados (1)')).toBeTruthy();
});
