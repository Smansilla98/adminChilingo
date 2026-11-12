import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

import { CuotasLote } from '@/features/cobranzas/CuotasLote';
import { Morosos } from '@/features/cobranzas/Morosos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const moroso = (alumno_id: number, alumno: string, tiene_cuenta = true) => ({
  alumno_id,
  alumno,
  sede: 'Palomar',
  bloques: ['Martes'],
  cuotas_vencidas: 1,
  saldo_vencido: 25000,
  vencida_desde: '2027-01-10',
  dias_mora: 45,
  tramo: '31-60',
  tiene_cuenta,
});

const listaMorosos = {
  data: [moroso(1, 'Ana Pérez'), moroso(2, 'Beto Gómez', false)],
  total_saldo: 50000,
  tramos: [{ id: '0-30', nombre: '0 a 30 días' }, { id: '31-60', nombre: '31 a 60 días' }],
};

beforeEach(() => jest.restoreAllMocks());

it('lista morosos con antigüedad y manda un recordatorio individual', async () => {
  const llamadas = simularApi({
    'GET morosos': listaMorosos,
    'POST morosos/1/recordatorio': { data: { estado: 'enviado', canales: { interna: 'enviado' } } },
  });
  await renderizar(<Morosos />);

  expect(await screen.findByText('Ana Pérez')).toBeTruthy();
  expect(screen.getAllByText('31 a 60 días (45 días)')).toHaveLength(2);
  expect(screen.getByText('Sin cuenta para recibir avisos.')).toBeTruthy();

  await fireEvent.press(screen.getByText('Recordar'));
  expect(await screen.findByText('Recordatorio enviado a Ana Pérez.')).toBeTruthy();
  expect(llamadas.find((l) => l.metodo === 'POST')?.body).toEqual({ canales: ['interna', 'push'] });
});

it('recordar a todos pide confirmación y manda solo a quienes tienen cuenta', async () => {
  const llamadas = simularApi({
    'GET morosos': listaMorosos,
    'POST morosos/recordatorios': { data: { enviado: 1, duplicado: 0, sin_envio: 0, al_dia: 0, sin_cuenta: 0 } },
  });
  const alerta = jest.spyOn(Alert, 'alert');
  await renderizar(<Morosos />);

  await fireEvent.press(await screen.findByText('Recordar a todos (1)'));
  const [, , botones] = alerta.mock.calls[0];
  (botones as { text: string; onPress?: () => void }[]).find((b) => b.text === 'Enviar')?.onPress?.();

  expect(await screen.findByText('Enviados: 1. Ya enviados este mes: 0. Sin canal disponible: 0. Sin cuenta: 0.')).toBeTruthy();
  expect(llamadas.find((l) => l.metodo === 'POST')?.body).toEqual({ alumno_ids: [1], canales: ['interna', 'push'] });
});

it('cuotas por lote: vista previa antes de generar y sin duplicar', async () => {
  const preview = {
    data: {
      total: 2,
      crear: 1,
      existen: 1,
      sin_permiso: 0,
      items: [
        { nombre: 'Cuota mensual', periodo: '01/2027', mes: 1, destino: 'Martes (Palomar)', bloque_id: 5, sede_id: null, vencimiento: '2027-01-10', monto: 25000, estado: 'crear' },
        { nombre: 'Cuota mensual', periodo: '02/2027', mes: 2, destino: 'Martes (Palomar)', bloque_id: 5, sede_id: null, vencimiento: '2027-02-10', monto: 25000, estado: 'existe' },
      ],
    },
  };
  const llamadas = simularApi({
    'GET bloques': { data: [{ id: 5, nombre: 'Martes', anio: 2027 }] },
    'POST cuotas/lote/vista-previa': preview,
    'POST cuotas/lote': { data: { creadas: 1, omitidas: 1 } },
  });
  const alerta = jest.spyOn(Alert, 'alert');
  await renderizar(<CuotasLote />);

  await fireEvent.changeText(await screen.findByLabelText('Monto ($)'), '25000');
  await fireEvent.press(screen.getByText('Martes'));
  await fireEvent.press(screen.getByText('Ver vista previa'));

  expect(await screen.findByText('2 cuota(s) en el lote')).toBeTruthy();
  expect(screen.getByText('Se crean 1 · ya existen 1 · sin permiso 0')).toBeTruthy();
  expect(screen.getByText('Ya existe')).toBeTruthy();

  await fireEvent.press(screen.getByText('Generar 1 cuota(s)'));
  const [, , botones] = alerta.mock.calls[0];
  (botones as { text: string; onPress?: () => void }[]).find((b) => b.text === 'Generar')?.onPress?.();

  await waitFor(() => expect(screen.getByText('Se generaron 1 cuota(s). Omitidas: 1.')).toBeTruthy());
  const previaBody = llamadas.find((l) => l.ruta === 'cuotas/lote/vista-previa')?.body as Record<string, unknown>;
  expect(previaBody).toMatchObject({ alcance: 'bloque', bloque_ids: [5], monto: 25000, mes_desde: 1, mes_hasta: 12 });
});
