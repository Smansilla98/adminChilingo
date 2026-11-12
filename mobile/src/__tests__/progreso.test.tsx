import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import MiProgreso from '@/app/(app)/mi-progreso';
import ProgresoBloque from '@/app/(app)/progreso/[bloque]';
import { api } from '@/lib/api';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-router', () => ({ router: { push: jest.fn() }, Stack: { Screen: () => null }, useLocalSearchParams: () => ({ bloque: '5', nombre: 'Bloque 1' }) }));

beforeEach(() => jest.clearAllMocks());

it('el alumno ve su año, sus toques y las devoluciones, sin notas', async () => {
  simularApi({
    'GET mi/progreso': { data: [{
      alumno: { id: 7, nombre: 'Ana' },
      anio_actual: 1,
      anios: [{ anio: 1, tocados: 1, en_proceso: 1, total: 2, toques: [
        { id: 1, slug: 'chilinga-1', nombre: 'Chilinga 1', opcional: false, estado: 'aprobado', estado_nombre: 'Lo toca con el bloque', fecha: '2026-11-01', docente: 'Profe' },
        { id: 2, slug: 'candombe', nombre: 'Candombe', opcional: false, estado: 'en_proceso', estado_nombre: 'Lo está trabajando', fecha: null, docente: null },
      ] }],
      evaluaciones: [{ id: 1, fecha: '2026-11-02', resultado: 'lo_toca', resultado_nombre: 'Lo toca con el bloque', devolucion: 'Muy bien.', toques: ['Chilinga 1'], docente: 'Profe', bloque: 'B1', visible_alumno: true }],
      historial: [],
      propuesta: null,
    }] },
  });
  await renderizar(<MiProgreso />);
  expect(await screen.findByText('1° año')).toBeTruthy();
  expect(screen.getByText('Ya tocás con el bloque 1 de 2 toques de este año · 1 en camino.')).toBeTruthy();
  expect(screen.getByText('Muy bien.')).toBeTruthy();
  expect(screen.getByText('Lo está trabajando')).toBeTruthy();
});

it('el docente cambia un toque con un toque en la grilla', async () => {
  simularApi({
    'GET progreso/bloques/5': {
      bloque: { id: 5, nombre: 'Bloque 1', anio: 1, sede: 'Palomar' }, anio: 1, puede_editar: true,
      estados: { no_empezado: 'Todavía no', en_proceso: 'Lo está trabajando', aprobado: 'Lo toca con el bloque' }, resultados: {},
      toques: [{ id: 1, nombre: 'Chilinga 1', slug: 'chilinga-1', opcional: false }],
      alumnos: [{ id: 7, nombre: 'Ana', anio_actual: 1, obligatorios_tocados: 0, obligatorios: 1, estados: {} }],
      propuestas: [],
    },
    'POST progreso/bloques/5/estado': { data: {}, propuesta: null },
  });
  await renderizar(<ProgresoBloque />);
  await fireEvent.press(await screen.findByLabelText('Ana, Chilinga 1: Todavía no'));
  await waitFor(() => expect(api).toHaveBeenCalledWith('progreso/bloques/5/estado', { method: 'POST', body: { alumno_id: 7, programa_ritmo_id: 1, estado: 'en_proceso' } }));
});
