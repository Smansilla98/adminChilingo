import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import * as Clipboard from 'expo-clipboard';
import { Linking } from 'react-native';

import { ComoPagar } from '@/features/finanzas/ComoPagar';
import { ResumenDeuda } from '@/features/finanzas/ResumenDeuda';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-clipboard', () => ({ setStringAsync: jest.fn(async () => true) }));

const item = (cuota_id: number, nombre: string, estado: string, saldo: number, vencimiento: string | null = null) => ({
  cuota_id, nombre, periodo: '', vencimiento, bruto: saldo, beca: null, descuento: 0, neto: saldo, pagado: 0, saldo, estado,
});

beforeEach(() => jest.clearAllMocks());

it('dice cuántas cuotas debe, cuáles y el total, con las vencidas primero', async () => {
  const cuenta = {
    alumno_id: 7, anio: 2026, becas: [], totales: { bruto: 0, descuento: 0, neto: 0, pagado: 0, saldo: 45000, vencido: 20000 },
    items: [item(1, 'Octubre', 'pendiente', 25000, '10/11'), item(2, 'Septiembre', 'vencida', 20000, '10/10'), item(3, 'Agosto', 'pagada', 0)],
  };
  await renderizar(<ResumenDeuda cuenta={cuenta as never} />);

  expect(screen.getByText('Debés 2 cuotas · $ 45.000')).toBeTruthy();
  expect(screen.getByText('1 está vencida')).toBeTruthy();
  expect(screen.getByText('Septiembre')).toBeTruthy();
  expect(screen.queryByText('Agosto')).toBeNull();
});

it('copia el alias y abre Mercado Pago con las cuotas pendientes', async () => {
  const llamadas = simularApi({ 'POST mi/cobro/mercadopago': { link: 'https://mp.example/checkout/1' } });
  const abrir = jest.spyOn(Linking, 'openURL').mockResolvedValue(true);
  await renderizar(
    <ComoPagar cobro={{ transferencia: { alias: 'chilinga.cuotas', titular: 'Asociación Civil' }, mercado_pago: true }} alumnoId={7} saldo={45000} />,
  );

  await fireEvent.press(screen.getByLabelText('Copiar alias'));
  expect(Clipboard.setStringAsync).toHaveBeenCalledWith('chilinga.cuotas');
  expect(await screen.findByText('Alias copiado. Pegalo en la app de tu banco o billetera.')).toBeTruthy();

  await fireEvent.press(screen.getByLabelText('Pagar con Mercado Pago'));
  await waitFor(() => expect(abrir).toHaveBeenCalledWith('https://mp.example/checkout/1'));
  expect(llamadas.find((l) => l.metodo === 'POST')?.body).toEqual({ alumno_id: 7 });
});
