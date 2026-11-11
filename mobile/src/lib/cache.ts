import AsyncStorage from '@react-native-async-storage/async-storage';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { defaultShouldDehydrateQuery, type Query, QueryClient, type QueryKey } from '@tanstack/react-query';

import { ApiError } from './api';

export const DIA = 24 * 60 * 60_000;

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      gcTime: DIA,
      // Con red lenta: mostrar lo cacheado y reintentar solo errores de red/servidor.
      networkMode: 'offlineFirst',
      retry: (intentos, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && intentos < 2,
    },
  },
});

/** Caché persistente: la última información consultada se ve aunque no haya señal. */
export const CLAVE_CACHE = 'ito.cache.v2';
export const persister = createAsyncStoragePersister({ storage: AsyncStorage, key: CLAVE_CACHE });

/**
 * Datos personales o financieros (DNI, teléfonos, saldos, pagos): quedan solo en memoria.
 * AsyncStorage no está cifrado, así que no se guardan en el teléfono.
 */
const SENSIBLES = new Set(['personas', 'usuarios', 'mi', 'pagos', 'comprobantes', 'becas', 'auditoria', 'recordatorios', 'profesores', 'villa-gesell']);
const ALUMNO_SENSIBLE = new Set(['detalle', 'cuenta', 'cuenta-para-pago', 'seguimiento', 'nombre']);

export function esSensible(clave: QueryKey): boolean {
  const [a, b] = clave;
  if (typeof a === 'string' && SENSIBLES.has(a)) return true;
  // Ficha, cuenta y seguimiento de un alumno (useDetalle: ['alumnos', 'detalle', id]).
  return a === 'alumnos' && typeof b === 'string' && ALUMNO_SENSIBLE.has(b);
}

export const debePersistir = (q: Query) => defaultShouldDehydrateQuery(q) && !esSensible(q.queryKey);

/** El buster separa la caché de cada cuenta: si en el teléfono entra otra persona, no ve lo de la anterior. */
export const busterPara = (usuarioId: number | null) => `v2-${usuarioId ?? 'sin-sesion'}`;

/** Borra la caché en memoria y la guardada en el teléfono (al cerrar sesión). */
export async function vaciarCache(cliente: QueryClient = queryClient) {
  cliente.clear();
  try {
    await persister.removeClient();
  } catch {
    await AsyncStorage.removeItem(CLAVE_CACHE).catch(() => undefined);
  }
  // Caché de la versión anterior (antes de separar por cuenta).
  await AsyncStorage.removeItem('ito.cache.v1').catch(() => undefined);
}
