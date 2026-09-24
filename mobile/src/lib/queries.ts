import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from './api';
import { encolarAsistencia } from './offline';
import type {
  Aviso,
  Bloque,
  EstadoCuenta,
  Inicio,
  InventarioItem,
  ItemAgenda,
  Me,
  Paginado,
  Planilla,
  TipoAsistencia,
} from './types';

export const useMe = () => useQuery({ queryKey: ['me'], queryFn: () => api<Me>('me'), staleTime: 5 * 60_000 });

export const useInicio = () => useQuery({ queryKey: ['inicio'], queryFn: () => api<Inicio>('inicio') });

export const useBloquesAsistencia = () =>
  useQuery({ queryKey: ['bloques', 'asistencia'], queryFn: () => api<{ data: Bloque[] }>('bloques?para=asistencia').then((r) => r.data) });

export const usePlanilla = (bloqueId: number, fecha: string) =>
  useQuery({
    queryKey: ['planilla', bloqueId, fecha],
    queryFn: () => api<Planilla>(`bloques/${bloqueId}/asistencia?fecha=${fecha}`),
    enabled: bloqueId > 0,
  });

/**
 * Guarda la asistencia. Si no hay red, queda en la cola local y se envía sola al volver
 * la conexión (idempotente por client_uuid).
 */
export function useGuardarAsistencia(bloqueId: number, fecha: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (registros: Record<number, TipoAsistencia>) =>
      encolarAsistencia({ bloqueId, fecha, registros: Object.entries(registros).map(([id, tipo]) => ({ alumno_id: Number(id), tipo })) }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['planilla', bloqueId, fecha] });
      void qc.invalidateQueries({ queryKey: ['inicio'] });
    },
  });
}

export const useMiEstadoCuenta = () =>
  useQuery({ queryKey: ['mi', 'estado-cuenta'], queryFn: () => api<{ cuentas: EstadoCuenta[] }>('mi/estado-cuenta').then((r) => r.cuentas) });

export const useCalendario = (desde: string, hasta: string) =>
  useQuery({
    queryKey: ['calendario', desde, hasta],
    queryFn: () => api<{ items: ItemAgenda[] }>(`calendario?desde=${desde}&hasta=${hasta}`).then((r) => r.items),
  });

export const useAlumnos = (q: string) =>
  useQuery({
    queryKey: ['alumnos', q],
    queryFn: () => api<Paginado<{ id: number; nombre: string; bloques?: { id: number; nombre: string }[]; sede?: { nombre: string } | null }>>(`alumnos?q=${encodeURIComponent(q)}`),
  });

export const useInventario = (q: string) =>
  useQuery({ queryKey: ['inventario', q], queryFn: () => api<Paginado<InventarioItem>>(`inventario?q=${encodeURIComponent(q)}`) });

export const useInventarioItem = (id: number) =>
  useQuery({ queryKey: ['inventario', 'item', id], queryFn: () => api<{ data: InventarioItem }>(`inventario/${id}`).then((r) => r.data), enabled: id > 0 });

export const useAvisos = () =>
  useQuery({ queryKey: ['avisos'], queryFn: () => api<{ no_leidas: number; data: Aviso[] }>('notificaciones') });

export const usePartituras = () =>
  useQuery({
    queryKey: ['partituras'],
    queryFn: () => api<{ data: { slug: string; nombre: string; anio: number; autor: string | null; tiene_partitura: boolean }[] }>('partituras').then((r) => r.data),
    staleTime: 60 * 60_000,
  });
