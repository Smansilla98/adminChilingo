import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

import { SesionesApp } from '@/features/usuarios/SesionesApp';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const usuario = {
  id: 9,
  nombre: 'Ana Pérez',
  dispositivos: [
    { id: 41, nombre: 'Pixel 7', ultimo_uso: '2026-11-01T10:00:00Z', desde: '2026-10-01T10:00:00Z', vence: null },
    { id: 42, nombre: 'iPhone', ultimo_uso: null, desde: '2026-10-02T10:00:00Z', vence: null },
  ],
};

beforeEach(() => jest.clearAllMocks());

const confirmarEn = () => jest.spyOn(Alert, 'alert').mockImplementationOnce((_t, _m, botones) => botones?.find((b) => b.text !== 'Cancelar')?.onPress?.());

it('cierra una sesión puntual con DELETE usuarios/{id}/sesiones/{sesion}', async () => {
  const llamadas = simularApi({ 'DELETE usuarios/9/sesiones/41': { ok: true, cerradas: 1 } });
  confirmarEn();
  await renderizar(<SesionesApp usuario={usuario} puedeCerrar />);

  await fireEvent.press(screen.getByLabelText('Cerrar la sesión Pixel 7'));
  await waitFor(() => expect(llamadas.some((l) => l.metodo === 'DELETE' && l.ruta === 'usuarios/9/sesiones/41')).toBe(true));
});

it('cierra todas las sesiones con DELETE usuarios/{id}/sesiones', async () => {
  const llamadas = simularApi({ 'DELETE usuarios/9/sesiones': { ok: true, cerradas: 2 } });
  confirmarEn();
  await renderizar(<SesionesApp usuario={usuario} puedeCerrar />);

  await fireEvent.press(screen.getByText('Cerrar todas las sesiones de la app'));
  await waitFor(() => expect(llamadas.some((l) => l.metodo === 'DELETE' && l.ruta === 'usuarios/9/sesiones')).toBe(true));
});

it('sin permiso solo muestra las sesiones, sin acciones para cerrarlas', async () => {
  await renderizar(<SesionesApp usuario={usuario} puedeCerrar={false} />);
  expect(screen.getByText('Pixel 7')).toBeTruthy();
  expect(screen.queryByLabelText('Cerrar la sesión Pixel 7')).toBeNull();
  expect(screen.queryByText('Cerrar todas las sesiones de la app')).toBeNull();
});

it('sin sesiones abiertas lo dice', async () => {
  await renderizar(<SesionesApp usuario={{ ...usuario, dispositivos: [] }} puedeCerrar />);
  expect(screen.getByText('No tiene sesiones abiertas en la app.')).toBeTruthy();
});
