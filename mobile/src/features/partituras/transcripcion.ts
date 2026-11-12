/**
 * Transcripción automática desde la app: subir la grabación del toque y pedir un borrador.
 * El borrador nunca se publica solo: queda en el editor web para revisarlo y publicarlo ahí.
 */
import { api } from '@/lib/api';
import { subirArchivo, type ArchivoLocal } from '@/lib/archivos';

export interface EstadoTranscriptor {
  transcripcion: { habilitada: boolean; mensaje: string | null };
}

export type EstadoPedido = 'pendiente' | 'procesando' | 'listo' | 'error';

export interface Transcripcion {
  id: number;
  estado: EstadoPedido;
  progreso: number;
  error: string | null;
  resumen: { golpes: number; compases: number; dudosos: number; truncado: boolean } | null;
  url: string;
}

interface RespuestaSubida {
  ok: boolean;
  audio_ref: { path?: string | null } | null;
  transcripcion: Transcripcion | null;
  aviso?: string | null;
}

export const TEXTO_ESTADO: Record<EstadoPedido, string> = {
  pendiente: 'En espera…',
  procesando: 'Escuchando la grabación…',
  listo: 'Listo: quedó un borrador. Revisalo en el editor web antes de publicarlo.',
  error: 'No se pudo transcribir.',
};

export function estadoTranscriptor() {
  return api<EstadoTranscriptor>('partituras-transcriptor');
}

/** Sube la grabación y, si se pide, pide la transcripción en el mismo paso. */
export function subirGrabacion(slug: string, archivo: ArchivoLocal, transcribir: boolean, onProgreso?: (f: number) => void) {
  return subirArchivo<RespuestaSubida>(`partituras/${slug}/audio`, archivo, {
    campo: 'audio',
    datos: { transcribir },
    onProgreso,
  });
}

/** Pide un borrador a partir de una grabación que ya está en el servidor. */
export function pedirTranscripcion(slug: string, path: string) {
  return api<{ data: Transcripcion }>(`partituras/${slug}/transcripciones`, { method: 'POST', body: { path } }).then((r) => r.data);
}

export function consultarTranscripcion(slug: string, id: number) {
  return api<{ data: Transcripcion }>(`partituras/${slug}/transcripciones/${id}`).then((r) => r.data);
}
