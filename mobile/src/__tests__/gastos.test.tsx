import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { useLocalSearchParams } from 'expo-router';
import { Alert } from 'react-native';

import FichaGasto from '@/app/(app)/gastos/[id]/index';
import type { Gasto } from '@/features/finanzas/FormGasto';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const gasto: Gasto = {
  id: 4, fecha: '2026-09-01', tipo: 'servicio', tipo_nombre: 'Servicios', subtipo: 'luz', subtipo_nombre: 'Luz', descripcion: 'Factura', monto: 15000,
  proveedor: null, notas: null, estado: 'pendiente', estado_nombre: 'Pendiente de aprobación', sede: { id: 1, nombre: 'Palomar' }, bloque: null,
  creado_por: 'Resp', aprobado_at: null, aprobado_por: null, acciones: { editar: true, eliminar: false, aprobar: false, rechazar: false },
};

beforeEach(() => {
  jest.clearAllMocks();
  (useLocalSearchParams as jest.Mock).mockReturnValue({ id: '4' });
});

it('sin permiso de aprobación no muestra aprobar ni rechazar', async () => {
  simularApi({ 'GET gastos/4': { data: gasto }, 'GET me': { permisos: [], superadmin: false } });
  await renderizar(<FichaGasto />);
  expect(await screen.findByText('Pendiente de aprobación')).toBeTruthy();
  expect(screen.queryByLabelText('Aprobar')).toBeNull();
  expect(screen.queryByLabelText('Rechazar')).toBeNull();
});

it('aprueba después de confirmar y refresca el gasto', async () => {
  let aprobado = false;
  const llamadas = simularApi({
    'GET gastos/4': () => ({ data: aprobado ? { ...gasto, estado: 'aprobado', estado_nombre: 'Aprobado', acciones: { ...gasto.acciones, aprobar: false, rechazar: true } } : { ...gasto, acciones: { ...gasto.acciones, aprobar: true, rechazar: true } } }),
    'POST gastos/4/decision': () => { aprobado = true; return { data: { ...gasto, estado: 'aprobado' } }; },
    'GET me': { permisos: [], superadmin: false },
  });
  jest.spyOn(Alert, 'alert').mockImplementation((_t, _m, botones) => botones?.[1]?.onPress?.());

  await renderizar(<FichaGasto />);
  await fireEvent.press(await screen.findByLabelText('Aprobar'));

  await waitFor(() => expect(llamadas.find((l) => l.metodo === 'POST')?.body).toEqual({ decision: 'aprobado' }));
  expect(await screen.findByText('Aprobado')).toBeTruthy();
  expect(await screen.findByText('Gasto aprobado')).toBeTruthy();
});
