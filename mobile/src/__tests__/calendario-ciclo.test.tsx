import { screen } from '@testing-library/react-native';

import { CalendarioCiclo, cicloVigente } from '@/features/ciclo/CalendarioCiclo';
import { ApiError } from '@/lib/api';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const ciclos = [
  { id: 1, anio: 2026, nombre: 'Ciclo 2026', inicio: '2026-03-01', fin: '2027-02-28', cerrado: true },
  { id: 2, anio: 2027, nombre: null, inicio: '2027-03-01', fin: '2028-02-29', cerrado: false },
];

const meses = [
  { anio: 2027, mes: 1, nombre: 'Enero 2027', actividades: [{ tipo: 'taller_verano', etiqueta: 'Taller de verano' }, { tipo: 'gira_gesell', etiqueta: 'Gira a Villa Gesell' }], cuotas: false },
  { anio: 2027, mes: 3, nombre: 'Marzo 2027', actividades: [{ tipo: 'clases', etiqueta: 'Clases' }], cuotas: true },
];

beforeEach(() => jest.clearAllMocks());

it('el ciclo vigente es el primero no cerrado; si todos cerraron, el más reciente', () => {
  expect(cicloVigente(ciclos)?.id).toBe(2);
  expect(cicloVigente(ciclos.map((c) => ({ ...c, cerrado: true })))?.id).toBe(1);
  expect(cicloVigente([])).toBeNull();
});

it('muestra cada mes con sus actividades (enero: taller y gira en paralelo)', async () => {
  const llamadas = simularApi({ 'GET ciclos-lectivos': { data: ciclos }, 'GET ciclos-lectivos/2/calendario': { data: meses } });
  await renderizar(<CalendarioCiclo />);

  expect(await screen.findByText('Enero 2027')).toBeTruthy();
  expect(screen.getByText('Marzo 2027')).toBeTruthy();
  expect(screen.getByLabelText('Enero 2027: Taller de verano, Gira a Villa Gesell')).toBeTruthy();
  expect(screen.getByLabelText('Marzo 2027: Clases')).toBeTruthy();
  expect(screen.getByText('Cuota mensual')).toBeTruthy();
  expect(screen.getByText('Actividades en paralelo: se pueden cursar a la vez.')).toBeTruthy();
  expect(llamadas.some((l) => l.ruta === 'ciclos-lectivos/2/calendario')).toBe(true);
});

it('sin permiso para el calendario lo dice y no rompe la pantalla', async () => {
  simularApi({
    'GET ciclos-lectivos': () => {
      throw new ApiError(403, 'No tenés permiso para hacer esto.');
    },
  });
  await renderizar(<CalendarioCiclo />);
  expect(await screen.findByText('No tenés acceso al calendario académico. Consultá en la escuela.')).toBeTruthy();
});

it('sin ciclos cargados lo dice', async () => {
  simularApi({ 'GET ciclos-lectivos': { data: [] } });
  await renderizar(<CalendarioCiclo />);
  expect(await screen.findByText('Todavía no hay un ciclo lectivo cargado.')).toBeTruthy();
});
