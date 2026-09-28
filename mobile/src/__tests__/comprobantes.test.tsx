import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import { FormComprobante } from '@/features/comprobantes/FormComprobante';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('@/lib/archivos', () => ({
  ...jest.requireActual('@/lib/archivos'),
  subirArchivo: jest.fn(async () => ({ data: { id: 5 } })),
  elegirImagen: jest.fn(async () => ({ uri: 'file:///foto.jpg', nombre: 'foto.jpg', mime: 'image/jpeg', tamano: 1000 })),
}));

const opciones = {
  alumno: { id: 7, nombre: 'Ana Paz' },
  bloques: [
    { id: 1, nombre: 'Bloque A', cuota_id: 3, cuota: 'Agosto', monto: 20000, ya_pagada: false },
    { id: 2, nombre: 'Bloque B', cuota_id: 4, cuota: 'Agosto', monto: 15000, ya_pagada: true },
  ],
};

beforeEach(() => jest.clearAllMocks());

it('el alumno elige la cuota, adjunta la foto y envía a mi/comprobantes', async () => {
  simularApi({ 'GET comprobantes/opciones': opciones });
  const { subirArchivo } = jest.requireMock('@/lib/archivos') as { subirArchivo: jest.Mock };
  await renderizar(<FormComprobante propio alumnoInicial={{ id: 7, nombre: 'Ana Paz' }} />);

  await fireEvent.press(screen.getByLabelText('Enviar comprobante'));
  expect(await screen.findByText('Adjuntá la foto o el PDF del comprobante.')).toBeTruthy();
  expect(subirArchivo).not.toHaveBeenCalled();

  expect(await screen.findByText('Ya pagadas: Bloque B')).toBeTruthy();
  await fireEvent.press(screen.getByText('Bloque A · $ 20.000'));
  await fireEvent.press(screen.getByLabelText('Galería'));
  expect(await screen.findByText('foto.jpg')).toBeTruthy();
  await fireEvent.press(screen.getByLabelText('Enviar comprobante'));

  await waitFor(() => expect(subirArchivo).toHaveBeenCalled());
  const [ruta, archivo, opciones_] = subirArchivo.mock.calls[0];
  expect(ruta).toBe('mi/comprobantes');
  expect(archivo).toMatchObject({ nombre: 'foto.jpg' });
  expect(opciones_.campo).toBe('comprobante');
  expect(opciones_.datos).toMatchObject({ alumno_id: 7, 'bloque_ids[0]': 1 });
  await waitFor(() => expect(router.back).toHaveBeenCalled());
});
