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

  /** Errores de validación del servidor (422): se muestran junto a cada campo. */
  get esValidacion(): boolean {
    return this.status === 422;
  }

  get sinPermiso(): boolean {
    return this.status === 403;
  }

  /** Error del servidor o de red: tiene sentido reintentar. */
  get reintentable(): boolean {
    return this.status === 0 || this.status === 429 || this.status >= 500;
  }

  /** Primer error de un campo (para mostrarlo debajo del input). */
  campo(nombre: string): string | undefined {
    return this.errores[nombre]?.[0];
  }
}

type Sesion = { token: string | null; contexto: string | null; onNoAutorizado?: () => void };
const sesion: Sesion = { token: null, contexto: null };

export function configurarSesion(parcial: Partial<Sesion>) {
  Object.assign(sesion, parcial);
}

export type Metodo = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface Opciones {
  method?: Metodo;
  body?: unknown;
  signal?: AbortSignal;
}

/** Headers comunes (token Bearer y contexto de trabajo). */
export function headersSesion(): Record<string, string> {
  const h: Record<string, string> = { Accept: 'application/json' };
  if (sesion.token) h.Authorization = `Bearer ${sesion.token}`;
  if (sesion.contexto) h['X-Contexto'] = sesion.contexto;
  return h;
}

export const urlApi = (ruta: string) => `${API_URL}/${ruta.replace(/^\/+/, '')}`;

/** Arma un query string ignorando vacíos: qs({ q: 'ana', sede_id: undefined }) → '?q=ana'. */
export function qs(params: Record<string, string | number | boolean | null | undefined>): string {
  const partes = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(typeof v === 'boolean' ? (v ? '1' : '0') : String(v))}`);
  return partes.length ? `?${partes.join('&')}` : '';
}

/**
 * Llamada a /api/v1 con token Bearer y contexto de trabajo. Lanza ApiError con el
 * mensaje del servidor (en español) o status 0 si no hay red.
 */
export async function api<T>(ruta: string, { method = 'GET', body, signal }: Opciones = {}): Promise<T> {
  const headers = headersSesion();
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let resp: Response;
  try {
    resp = await fetch(urlApi(ruta), {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal,
    });
  } catch (e) {
    if (e instanceof Error && e.name === 'AbortError') throw e;
    throw new ApiError(0, 'Sin conexión. Revisá internet e intentá de nuevo.');
  }

  return procesarRespuesta<T>(resp.status, await resp.text());
}

/** Interpreta status + cuerpo (compartido por fetch y por las subidas de archivos). */
export function procesarRespuesta<T>(status: number, texto: string): T {
  const json = texto ? safeJson(texto) : null;
  if (status >= 200 && status < 300) return json as T;

  if (status === 401) sesion.onNoAutorizado?.();
  const errores: Record<string, string[]> = json?.errors ?? {};
  const primero = Object.values(errores)[0]?.[0];
  throw new ApiError(status, mensajeDeError(status, primero ?? json?.message), errores);
}

function safeJson(texto: string): any {
  try {
    return JSON.parse(texto);
  } catch {
    return null;
  }
}

/**
 * Mensaje para la persona usuaria. Los 5xx nunca muestran el texto técnico del servidor
 * ("Server Error", trazas): se reemplaza por uno entendible con opción de reintentar.
 */
function mensajeDeError(status: number, delServidor?: string): string {
  if (status >= 500) return 'El servidor tuvo un problema. Probá de nuevo en un rato.';
  if (status === 401) return 'Tu sesión venció. Ingresá de nuevo.';
  if (status === 429) return 'Demasiados intentos. Esperá un minuto.';
  if (delServidor && !/^(Unauthenticated|This action is unauthorized|Not Found|Server Error)\.?$/i.test(delServidor)) return delServidor;
  if (status === 403) return 'No tenés permiso para hacer esto.';
  if (status === 404) return 'No encontramos lo que buscabas. Puede que se haya eliminado.';
  if (status === 409) return 'Alguien modificó este dato recién. Actualizá e intentá de nuevo.';
  if (status === 422) return 'Revisá los datos marcados.';
  if (status === 400) return 'La solicitud no es válida.';
  return 'No se pudo completar la operación.';
}
