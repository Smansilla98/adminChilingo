import { screen } from '@testing-library/react-native';

import Direccion from '@/app/(app)/direccion';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ Stack: { Screen: () => null } }));

it('muestra el resumen del tablero de dirección', async () => {
  simularApi({
    'GET direccion': {
      alcance: 'global', sedes: [], sede_id: null,
      actual: { etiqueta: 'Noviembre 26', emitido: 100000, cobrado: 80000, gastos: 30000, resultado: 50000, cobranza: 80, altas: 2, bajas: 1, asistencia: 90 },
      totales_12m: { cobrado: 0, emitido: 0, gastos: 0, resultado: 0 },
      alumnos: { activos: 42, altas_12m: 10, bajas_12m: 3, retencion: 93.3 },
      morosidad: { total: 12000, alumnos: 4 },
      ocupacion: { inscriptos: 30, cupo: 40, porcentaje: 75 },
      asistencia_promedio: 88,
      proyeccion: { etiqueta: 'Diciembre 2026', ingresos: 70000, gastos: 30000, resultado: 40000 },
      serie: [{ etiqueta: 'Noviembre 26', emitido: 100000, cobrado: 80000, gastos: 30000, resultado: 50000, cobranza: 80, altas: 2, bajas: 1, asistencia: 90 }],
    },
  });
  await renderizar(<Direccion />);
  expect(await screen.findByText('Toda la escuela · últimos 12 meses')).toBeTruthy();
  expect(screen.getByText('42')).toBeTruthy();
  expect(screen.getByText('75 %')).toBeTruthy();
});
