import { type QueryKey, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useToast } from '@/components/feedback';

import { api, type Metodo } from './api';

/**
 * Detalle de un registro (`GET ruta/id`). La clave de caché empieza con la ruta del
 * módulo, así cualquier mutación del módulo la invalida con `invalidar: [ruta]`.
 */
export function useDetalle<T>(ruta: string, id: string | number | undefined, opciones: { habilitado?: boolean } = {}) {
  return useQuery({
    queryKey: [ruta, 'detalle', String(id)],
    queryFn: ({ signal }) => api<{ data: T }>(`${ruta}/${id}`, { signal }).then((r) => r.data),
    enabled: !!id && id !== 'nuevo' && (opciones.habilitado ?? true),
  });
}

/** Catálogos (tipos, estados, sedes…): cambian poco, se cachean más tiempo. */
export function useCatalogo<T>(ruta: string, horas = 12) {
  return useQuery({
    queryKey: ['catalogo', ruta],
    queryFn: ({ signal }) => api<T>(ruta, { signal }),
    staleTime: horas * 3600_000,
  });
}

/**
 * Mutación con feedback: muestra el mensaje de éxito, invalida las cachés del módulo
 * (listados y detalles) y deja el error para que el formulario lo muestre.
 */
export function useOperacion<V = void, R = unknown>(fn: (v: V) => Promise<R>, { exito, invalidar = [], alTerminar }: {
  exito?: string | ((r: R) => string);
  invalidar?: (string | QueryKey)[];
  alTerminar?: (r: R) => void;
} = {}) {
  const qc = useQueryClient();
  const avisar = useToast();
  return useMutation({
    mutationFn: fn,
    onSuccess: async (r) => {
      await Promise.all([
        ...invalidar.map((k) => qc.invalidateQueries({ queryKey: Array.isArray(k) ? k : [k] })),
        qc.invalidateQueries({ queryKey: ['inicio'] }),
      ]);
      if (exito) avisar(typeof exito === 'function' ? exito(r) : exito);
      alTerminar?.(r);
    },
    onError: (e) => avisar(e instanceof Error ? e.message : 'No se pudo completar la operación.', 'error'),
  });
}

/** Atajo para operaciones JSON: `enviarJson('POST', 'gastos', datos)`. */
export const enviarJson = <R = unknown>(method: Metodo, ruta: string, body?: unknown) => api<R>(ruta, { method, body });
