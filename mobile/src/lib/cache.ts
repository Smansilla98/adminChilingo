import AsyncStorage from '@react-native-async-storage/async-storage';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { defaultShouldDehydrateQuery, type Query, QueryClient } from '@tanstack/react-query';

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
export const CLAVE_CACHE = 'ito.cache.v3';
/** Versiones anteriores: se borran al arrancar (guardaban datos personales sin criterio). */
const CLAVES_VIEJAS = ['ito.cache.v1', 'ito.cache.v2'];
export const persister = createAsyncStoragePersister({ storage: AsyncStorage, key: CLAVE_CACHE });

/**
 * Solo se guardan en el teléfono consultas de datos NO personales (catálogos, calendario,
 * partituras, archivo público, ciclo, inventario). AsyncStorage no está cifrado: alumnos,
 * personas, cuentas, pagos, planillas, avisos o cualquier otra cosa con nombres, DNI o saldos
 * queda solo en memoria y se pierde al cerrar la app.
 */
const PERSISTIBLES = new Set(['catalogo', 'calendario', 'partituras', 'archivo', 'ciclo', 'inventario']);

export const debePersistir = (q: Query) => defaultShouldDehydrateQuery(q) && typeof q.queryKey[0] === 'string' && PERSISTIBLES.has(q.queryKey[0]);

/** Borra del teléfono las cachés de versiones anteriores. Llamar al arrancar. */
export async function limpiarCacheAnterior() {
  await Promise.all(CLAVES_VIEJAS.map((k) => AsyncStorage.removeItem(k).catch(() => undefined)));
}

/** El buster separa la caché de cada cuenta: si en el teléfono entra otra persona, no ve lo de la anterior. */
export const busterPara = (usuarioId: number | null) => `v3-${usuarioId ?? 'sin-sesion'}`;

/** Borra la caché en memoria y la guardada en el teléfono (al cerrar sesión). */
export async function vaciarCache(cliente: QueryClient = queryClient) {
  cliente.clear();
  try {
    await persister.removeClient();
  } catch {
    await AsyncStorage.removeItem(CLAVE_CACHE).catch(() => undefined);
  }
  await limpiarCacheAnterior();
}
