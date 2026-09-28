import { act, fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';

import FichaPersona from '@/app/(app)/personas/[id]/index';
import { FormPersona } from '@/features/personas/FormPersona';
import type { Persona } from '@/features/personas/tipos';
import { ApiError } from '@/lib/api';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const base: Persona = {
  id: 7, nombre: 'Ana', apellido: 'Paz', nombre_completo: 'Ana Paz', dni: '30123456', fecha_nacimiento: null, telefono: '1122334455',
  email: null, estado: 'activo', direccion: null, contacto_emergencia_nombre: null, contacto_emergencia_telefono: null, observaciones: null,
  edad: null, fusionada_en: null, permisos: null, cuenta: null, asistencias: [], eventos: [], inventario: [], tipos_beca: { total: 'Beca total' },
  funciones: [
    { clave: 'alumno:b1', rol: 'alumno', rol_nombre: 'Alumno', ambito: 'bloque', ambito_nombre: 'Bloque A — Palomar', sede_id: 1, bloque_id: 1, origen: 'inscripcion', origen_etiqueta: 'Inscripción' },
    { clave: 'profesor:b2', rol: 'profesor', rol_nombre: 'Profesor', ambito: 'bloque', ambito_nombre: 'Bloque B — Palomar', sede_id: 1, bloque_id: 2, origen: 'plantel', origen_etiqueta: 'Plantel docente' },
  ],
  alumnos: [{ id: 3, activo: true, instrumento: 'Surdo', sede: { id: 1, nombre: 'Palomar' }, bloques: [{ id: 1, nombre: 'Bloque A', sede: 'Palomar' }], estado_cuenta: null, puede_ver: true, puede_gestionar_becas: false }],
  profesor: { id: 9, activo: true, bloques: [{ id: 2, nombre: 'Bloque B', sede: 'Palomar', rol: 'titular' }], sedes: [] },
  acciones: { editar: false, fusionar: false, inscribir_alumno: false, sumar_docente: false, crear_cuenta: false, ver_cuenta: false, gestionar_becas: false, ver_auditoria: false },
};

beforeEach(() => {
  jest.clearAllMocks();
  (useLocalSearchParams as jest.Mock).mockReturnValue({ id: '7' });
  simularApi({ 'GET me': { permisos: [], modulos: [], superadmin: false } });
});

describe('Ficha de persona', () => {
  it('muestra múltiples roles y oculta las acciones que el backend no permite', async () => {
    simularApi({ 'GET personas/7': { data: base }, 'GET me': { permisos: [], superadmin: false } });
    await renderizar(<FichaPersona />);

    expect(await screen.findByText('Ana Paz')).toBeTruthy();
    expect(screen.getByText('Alumno/a')).toBeTruthy();
    expect(screen.getByText('Docente')).toBeTruthy();
    expect(screen.queryByLabelText('Editar')).toBeNull();
    expect(screen.queryByLabelText('Fusionar duplicada')).toBeNull();

    await fireEvent.press(screen.getByText('Roles · 2'));
    expect(screen.getByText('Bloque B — Palomar')).toBeTruthy();
  });

  it('con permiso muestra Editar, inscribir y crear cuenta y navega a esas pantallas', async () => {
    simularApi({
      'GET personas/7': { data: { ...base, alumnos: [], profesor: null, acciones: { ...base.acciones, editar: true, sumar_docente: true, inscribir_alumno: true, crear_cuenta: true } } },
      'GET me': { permisos: [], superadmin: false },
    });
    await renderizar(<FichaPersona />);

    await fireEvent.press(await screen.findByLabelText('Inscribir como alumno'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/alumnos/nuevo', params: { persona_id: '7' } });
    await fireEvent.press(screen.getByLabelText('Crear cuenta'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/usuarios/nuevo', params: { persona_id: '7' } });
    await fireEvent.press(screen.getByLabelText('Editar'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/personas/[id]/editar', params: { id: '7' } });
  });

  it('muestra un error entendible y permite reintentar', async () => {
    let intentos = 0;
    simularApi({
      'GET personas/7': () => {
        intentos++;
        if (intentos === 1) throw new ApiError(0, 'Sin conexión. Revisá internet e intentá de nuevo.');
        return { data: base };
      },
    });
    await renderizar(<FichaPersona />);
    expect(await screen.findByText('Sin conexión. Revisá internet e intentá de nuevo.')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Reintentar'));
    expect(await screen.findByText('Ana Paz')).toBeTruthy();
  });
});

describe('Formulario de persona', () => {
  it('valida localmente, muestra errores 422 junto al campo y conserva lo cargado', async () => {
    const llamadas = simularApi({
      'POST personas': () => {
        throw new ApiError(422, 'Ya hay otra persona con ese DNI.', { dni: ['Ya hay otra persona con ese DNI.'] });
      },
    });
    await renderizar(<FormPersona />);

    await fireEvent.press(screen.getByLabelText('Crear persona'));
    expect(await screen.findByText('El nombre es obligatorio.')).toBeTruthy();
    expect(llamadas.filter((l) => l.metodo === 'POST')).toHaveLength(0);

    await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Lucía');
    await fireEvent.changeText(screen.getByLabelText('DNI'), '30123456');
    await fireEvent.press(screen.getByLabelText('Crear persona'));

    await waitFor(() => expect(screen.getAllByText('Ya hay otra persona con ese DNI.').length).toBeGreaterThan(0));
    expect(screen.getByLabelText('Nombre').props.value).toBe('Lucía');
    const post = llamadas.find((l) => l.metodo === 'POST');
    expect(post?.body).toMatchObject({ nombre: 'Lucía', dni: '30123456', estado: 'activo', email: null });
  });

  it('no envía dos veces si se toca rápido', async () => {
    let resolver: (v: unknown) => void = () => undefined;
    const llamadas = simularApi({ 'POST personas': () => new Promise((r) => { resolver = r; }) });
    await renderizar(<FormPersona />);
    await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Lucía');
    const boton = screen.getByLabelText('Crear persona');
    await fireEvent.press(boton);
    await fireEvent.press(boton);
    await act(async () => {
      resolver({ data: { id: 12 } });
    });
    expect(llamadas.filter((l) => l.metodo === 'POST')).toHaveLength(1);
    await waitFor(() => expect(router.replace).toHaveBeenCalledWith({ pathname: '/personas/[id]', params: { id: '12' } }));
  });
});
