import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import MisLiquidaciones from '@/app/(app)/mis-liquidaciones';
import { pdfDesdeServidor } from '@/lib/archivos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('@/lib/archivos', () => ({ pdfDesdeServidor: jest.fn(async () => undefined) }));
jest.mock('expo-router', () => ({ router: { push: jest.fn() }, Stack: { Screen: () => null } }));

it('muestra las liquidaciones del docente con ambas modalidades y baja el recibo', async () => {
  simularApi({
    'GET mi/liquidaciones': { data: [{ id: 3, numero: 'LD-202610-00003', periodo: 'Octubre 2026', estado: 'pagada', estado_nombre: 'Pagada', modalidad: 'hora', horas_total: 6, monto_horas: 9000, monto_porcentaje: 4000, monto_total: 9000 }] },
  });
  await renderizar(<MisLiquidaciones />);
  expect(await screen.findByText('Octubre 2026')).toBeTruthy();
  expect(screen.getByText('Por horas: 6 h dictadas')).toBeTruthy();
  await fireEvent.press(screen.getByText('Recibo en PDF'));
  await waitFor(() => expect(pdfDesdeServidor).toHaveBeenCalledWith('liquidaciones/3/recibo'));
});
