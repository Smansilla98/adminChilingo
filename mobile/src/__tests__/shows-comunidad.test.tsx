import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { useLocalSearchParams } from 'expo-router';

import MiShowDetalleScreen from '@/app/(app)/shows/mios/[id]';
import { destinoDe } from '@/lib/destinos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const plan = { logistica: { punto_encuentro: 'Estación Palomar', hora_encuentro: '18:30', transporte: 'Micro', notas: null }, temas: [{ id: 1, orden: 1, nombre: 'Candombe', titulo: null, programa_ritmo_id: 3, slug: 'candombe', notas: null }], ensayos: [], instrumentos: [] };
const show = { id: 5, titulo: 'Carnaval', fecha: '2099-02-10', hora_inicio: '20:00', hora_fin: null, lugar: 'Plaza', descripcion: null, convocatoria_abierta: false, alumno_id: 9, mi_respuesta: null, mi_comentario: null, asisti: null, ...plan };

beforeEach(() => {
  jest.clearAllMocks();
  (useLocalSearchParams as jest.Mock).mockReturnValue({ id: '5' });
});

it('el alumno confirma que va y ve la logística y los temas', async () => {
  const llamadas = simularApi({ 'GET mi/shows/5': { data: show }, 'POST mi/shows/5/confirmacion': { data: { ...show, mi_respuesta: 'voy' } } });
  await renderizar(<MiShowDetalleScreen />);

  expect(await screen.findByText('Estación Palomar')).toBeTruthy();
  expect(screen.getByText('1. Candombe')).toBeTruthy();
  await fireEvent.press(screen.getByText('Voy'));
  await fireEvent.press(screen.getByLabelText('Enviar respuesta'));
  await waitFor(() => expect(llamadas.find((l) => l.metodo === 'POST')?.body).toMatchObject({ respuesta: 'voy', comentario: null }));
});

it('los avisos de shows y préstamos abren su pantalla', () => {
  expect(destinoDe('app://mis-shows/12')).toBe('/shows/mios/12');
  expect(destinoDe('app://mis-shows')).toBe('/shows/mios');
  expect(destinoDe('app://mis-prestamos')).toBe('/mis-prestamos');
});
