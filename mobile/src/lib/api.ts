import { API_URL } from './config';

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errores: Record<string, string[]> = {},
  ) {
    super(message);
  }

  /** Sin conexión o servidor inaccesible. */
  get esDeRed(): boolean {
    return this.status === 0;
  }
}

type Sesion = { token: string | null; contexto: string | null; onNoAutorizado?: () => void };
const sesion: Sesion = { token: null, contexto: null };

export function configurarSesion(parcial: Partial<Sesion>) {
  Object.assign(sesion, parcial);
}

interface Opciones {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  body?: unknown;
  signal?: AbortSignal;
}

/**
 * Llamada a /api/v1 con token Bearer y contexto de trabajo. Lanza ApiError con el
 * mensaje del servidor (en español) o status 0 si no hay red.
 */
export async function api<T>(ruta: string, { method = 'GET', body, signal }: Opciones = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (sesion.token) headers.Authorization = `Bearer ${sesion.token}`;
  if (sesion.contexto) headers['X-Contexto'] = sesion.contexto;

  let resp: Response;
  try {
    resp = await fetch(`${API_URL}/${ruta.replace(/^\/+/, '')}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal,
    });
  } catch {
    throw new ApiError(0, 'Sin conexión. Revisá internet e intentá de nuevo.');
  }

  const texto = await resp.text();
  const json = texto ? safeJson(texto) : null;

  if (!resp.ok) {
    if (resp.status === 401) sesion.onNoAutorizado?.();
    const errores: Record<string, string[]> = json?.errors ?? {};
    const primero = Object.values(errores)[0]?.[0];
    throw new ApiError(resp.status, primero ?? json?.message ?? mensajePorEstado(resp.status), errores);
  }

  return json as T;
}

function safeJson(texto: string): any {
  try {
    return JSON.parse(texto);
  } catch {
    return null;
  }
}

function mensajePorEstado(status: number): string {
  if (status === 403) return 'No tenés permiso para hacer esto.';
  if (status === 404) return 'No encontramos lo que buscabas.';
  if (status === 429) return 'Demasiados intentos. Esperá un minuto.';
  if (status >= 500) return 'El servidor tuvo un problema. Probá en un rato.';
  return 'No se pudo completar la operación.';
}
