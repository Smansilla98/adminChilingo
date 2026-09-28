import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import { FormProfesor, type Profesor } from '@/features/profesores/FormProfesor';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const catalogo = {
  roles_bloque: { titular: 'Titular', ayudante: 'Ayudante' },
  roles_sede: { profesor: 'Profesor', coordinador: 'Coordinador de sede' },
  bloques: [{ id: 1, nombre: 'Bloque A', sede: 'Palomar' }, { id: 2, nombre: 'Bloque B', sede: 'Palomar' }],
  sedes: [{ id: 5, nombre: 'Palomar' }],
  usa_username: true,
};

const profesor: Profesor = {
  id: 9, nombre: 'Juana', telefono: null, email: null, activo: true, persona_id: 3, cuenta: null,
  bloques: [{ id: 1, nombre: 'Bloque A', sede: 'Palomar', rol: 'titular', cantidad_alumnos: 10 }],
  sedes: [], areas: [], eventos: [], alumno_id: null, acciones: { editar: true, eliminar: true, ver_persona: true },
};

beforeEach(() => jest.clearAllMocks());

it('edita roles: cambia el rol del bloque y suma coordinación de sede', async () => {
  const llamadas = simularApi({ 'GET profesores/catalogo': catalogo, 'PUT profesores/9': { data: profesor } });
  await renderizar(<FormProfesor profesor={profesor} />);

  await fireEvent.press(await screen.findByText('Ayudante'));
  await fireEvent.press(screen.getByLabelText('Coordinador de sede en Palomar'));
  await fireEvent.press(screen.getByLabelText('Guardar cambios'));

  await waitFor(() => expect(router.back).toHaveBeenCalled());
  const put = llamadas.find((l) => l.metodo === 'PUT');
  expect(put?.body).toMatchObject({
    nombre: 'Juana',
    cuenta_modo: 'ninguna',
    bloques: [{ bloque_id: 1, rol: 'ayudante' }],
    sedes: [{ sede_id: 5, rol: 'coordinador' }],
  });
});

it('al crear cuenta nueva exige que las contraseñas coincidan antes de enviar', async () => {
  const llamadas = simularApi({ 'GET profesores/catalogo': catalogo });
  await renderizar(<FormProfesor />);

  await fireEvent.changeText(await screen.findByLabelText('Nombre'), 'Pedro');
  await fireEvent.press(screen.getByText('Crear cuenta'));
  await fireEvent.changeText(screen.getByLabelText('Contraseña'), 'clave-segura-1');
  await fireEvent.changeText(screen.getByLabelText('Repetir contraseña'), 'otra-clave-22');
  await fireEvent.press(screen.getByLabelText('Crear docente'));

  expect(await screen.findByText('Las contraseñas no coinciden.')).toBeTruthy();
  expect(llamadas.some((l) => l.metodo === 'POST')).toBe(false);
});
