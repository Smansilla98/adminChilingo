import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import { PanelTranscripcion } from '@/features/partituras/PanelTranscripcion';
import { subirArchivo } from '@/lib/archivos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('@/lib/archivos', () => ({
  elegirDocumento: jest.fn(async () => ({ uri: 'file:///ensayo.mp3', nombre: 'ensayo.mp3', mime: 'audio/mpeg' })),
  subirArchivo: jest.fn(),
}));

const pendiente = { id: 7, estado: 'pendiente', progreso: 0, error: null, resumen: null, url: 'partituras/zz/transcripciones/7' };

beforeEach(() => {
  jest.clearAllMocks();
});

it('sin servicio configurado muestra el mensaje y no ofrece los botones', async () => {
  simularApi({
    'GET partituras-transcriptor': { transcripcion: { habilitada: false, mensaje: 'La transcripción automática todavía no está activada.' } },
  });
  await renderizar(<PanelTranscripcion slug="zz" tieneAudio />);

  expect(await screen.findByText(/todavía no está activada/)).toBeTruthy();
  expect(screen.queryByText('Subir grabación y transcribir')).toBeNull();
  expect(screen.queryByText('Transcribir la grabación del toque')).toBeNull();
});

it('con servicio, sube la grabación y muestra el estado del pedido', async () => {
  simularApi({ 'GET partituras-transcriptor': { transcripcion: { habilitada: true, mensaje: null } } });
  (subirArchivo as jest.Mock).mockResolvedValue({ ok: true, audio_ref: { path: 'partituras/audio/1-abcdefghijklmnop.mp3' }, transcripcion: pendiente });
  await renderizar(<PanelTranscripcion slug="zz" tieneAudio={false} />);

  fireEvent.press(await screen.findByText('Subir grabación y transcribir'));

  await waitFor(() => expect(subirArchivo).toHaveBeenCalledTimes(1));
  const [ruta, archivo, opciones] = (subirArchivo as jest.Mock).mock.calls[0];
  expect(ruta).toBe('partituras/zz/audio');
  expect(archivo).toMatchObject({ nombre: 'ensayo.mp3' });
  expect(opciones).toMatchObject({ campo: 'audio', datos: { transcribir: true } });
  expect(await screen.findByText(/En espera/)).toBeTruthy();
});

it('consulta el pedido hasta que queda listo y avisa que el borrador no se publicó solo', async () => {
  simularApi({
    'GET partituras-transcriptor': { transcripcion: { habilitada: true, mensaje: null } },
    'GET partituras/zz/transcripciones/7': {
      data: { ...pendiente, estado: 'listo', progreso: 100, resumen: { golpes: 12, compases: 4, dudosos: 1, truncado: false } },
    },
  });
  (subirArchivo as jest.Mock).mockResolvedValue({ ok: true, audio_ref: null, transcripcion: pendiente });
  await renderizar(<PanelTranscripcion slug="zz" tieneAudio={false} />);

  fireEvent.press(await screen.findByText('Subir grabación y transcribir'));

  await waitFor(() => expect(screen.getAllByText(/Revisalo en el editor web/).length).toBeGreaterThan(0), { timeout: 8000 });
  expect(screen.getByText(/4 compases, 12 golpes, 1 para revisar/)).toBeTruthy();
}, 15000);

it('con grabación ya subida ofrece transcribir la actual', async () => {
  simularApi({ 'GET partituras-transcriptor': { transcripcion: { habilitada: true, mensaje: null } } });
  await renderizar(<PanelTranscripcion slug="zz" tieneAudio />);

  expect(await screen.findByText('Transcribir la grabación del toque')).toBeTruthy();
});
