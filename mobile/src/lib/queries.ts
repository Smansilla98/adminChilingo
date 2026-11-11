import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from './api';
import { encolarAsistencia } from './offline';
import type {
  Aviso,
  Bloque,
  CobroDisponible,
  EstadoCuenta,
  Inicio,
  InventarioItem,
  ItemAgenda,
  Me,
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
export function useGuardarAsistencia(bloqueId: number, fecha: string, bloque?: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (registros: Record<number, TipoAsistencia>) =>
      encolarAsistencia({ bloqueId, bloque, fecha, registros: Object.entries(registros).map(([id, tipo]) => ({ alumno_id: Number(id), tipo })) }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['planilla', bloqueId, fecha] });
      void qc.invalidateQueries({ queryKey: ['inicio'] });
    },
  });
}

/** Una sola consulta a mi/estado-cuenta; cada hook toma su parte. */
const miEstadoCuenta = () => api<{ cuentas: EstadoCuenta[]; cobro?: CobroDisponible }>('mi/estado-cuenta');

export const useMiEstadoCuenta = () =>
  useQuery({ queryKey: ['mi', 'estado-cuenta'], queryFn: miEstadoCuenta, select: (r) => r.cuentas });

/** Datos para pagar (alias, CBU…) y si hay Mercado Pago: vienen con el estado de cuenta. */
export const useMiCobro = () =>
  useQuery({ queryKey: ['mi', 'estado-cuenta'], queryFn: miEstadoCuenta, select: (r) => r.cobro ?? { transferencia: null, mercado_pago: false } });

export const useCalendario = (desde: string, hasta: string) =>
  useQuery({
    queryKey: ['calendario', desde, hasta],
    queryFn: () => api<{ items: ItemAgenda[] }>(`calendario?desde=${desde}&hasta=${hasta}`).then((r) => r.items),
  });

export const useInventarioItem = (id: number) =>
  useQuery({ queryKey: ['inventario', 'item', id], queryFn: () => api<{ data: InventarioItem }>(`inventario/${id}`).then((r) => r.data), enabled: id > 0 });

export const useAvisos = () =>
  useQuery({ queryKey: ['avisos'], queryFn: () => api<{ no_leidas: number; data: Aviso[] }>('notificaciones') });

export const usePartituras = (todas = false) =>
  useQuery({
    queryKey: ['partituras', todas],
    queryFn: () => api<{ data: { slug: string; nombre: string; anio: number; autor: string | null; tiene_partitura: boolean; publicado?: boolean }[] }>(`partituras${todas ? '?todas=1' : ''}`).then((r) => r.data),
    staleTime: 60 * 60_000,
  });
