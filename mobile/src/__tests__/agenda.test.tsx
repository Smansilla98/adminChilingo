import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import { FormEvento } from '@/features/agenda/FormEvento';
import type { Evento } from '@/features/agenda/tipos';
import { ApiError } from '@/lib/api';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const catalogo = { tipos: { show: 'Show', muestra: 'Muestra' }, sedes: [{ id: 1, nombre: 'Palomar' }], profesores: [], bloques: [] };

beforeEach(() => jest.clearAllMocks());

it('crea el evento en la sede elegida y abre su detalle', async () => {
  const llamadas = simularApi({ 'GET eventos/catalogo': catalogo, 'POST eventos': { data: { id: 44 } } });
  await renderizar(<FormEvento sedeInicial={1} />);

  await fireEvent.changeText(await screen.findByLabelText('Título'), 'Muestra de fin de año');
  await fireEvent.press(screen.getByLabelText('Crear evento'));

  await waitFor(() => expect(router.replace).toHaveBeenCalledWith({ pathname: '/eventos/[id]', params: { id: '44' } }));
  expect(llamadas.find((l) => l.metodo === 'POST')?.body).toMatchObject({ titulo: 'Muestra de fin de año', sede_id: 1, tipo_evento: 'show', hora_inicio: null });
});

it('muestra el 403 del backend cuando el ámbito está fuera del alcance', async () => {
  simularApi({
    'GET eventos/catalogo': catalogo,
    'PUT eventos/5': () => { throw new ApiError(403, 'No podés crear o mover eventos a ese ámbito.'); },
  });
  const evento = { id: 5, titulo: 'X', descripcion: null, tipo: 'show', tipo_nombre: 'Show', fecha: '2026-10-10', hora_inicio: null, hora_fin: null, sede: null, bloque: null, profesor: null, cantidad_personas: null, ambito: 'escuela', creado_por: null, acciones: null } as Evento;
  await renderizar(<FormEvento evento={evento} />);

  await fireEvent.press(await screen.findByLabelText('Guardar cambios'));
  expect((await screen.findAllByText('No podés crear o mover eventos a ese ámbito.')).length).toBeGreaterThan(0);
  expect(router.back).not.toHaveBeenCalled();
});
