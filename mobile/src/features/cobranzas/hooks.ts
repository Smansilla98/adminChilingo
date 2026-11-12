import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api, qs } from '@/lib/api';

import type { DatosLote, RespuestaMorosos, ResultadoLote, ResultadoMasivo, ResultadoRecordatorio, Tramo, VistaPreviaLote } from './tipos';

export interface FiltrosMorosos {
  tramo: Tramo | null;
  sedeId: number | null;
  bloqueId: number | null;
}

export const useMorosos = (f: FiltrosMorosos) =>
  useQuery({
    queryKey: ['morosos', f.tramo, f.sedeId, f.bloqueId],
    queryFn: () => api<RespuestaMorosos>(`morosos${qs({ tramo: f.tramo, sede_id: f.sedeId, bloque_id: f.bloqueId })}`),
  });

export function useRecordarMoroso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (p: { alumnoId: number; canales?: string[] }) =>
      api<{ data: ResultadoRecordatorio }>(`morosos/${p.alumnoId}/recordatorio`, { method: 'POST', body: { canales: p.canales ?? ['interna', 'push'] } }).then((r) => r.data),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['morosos'] }),
  });
}

export function useRecordarMorosos() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (p: { alumnoIds: number[]; canales?: string[] }) =>
      api<{ data: ResultadoMasivo }>('morosos/recordatorios', { method: 'POST', body: { alumno_ids: p.alumnoIds, canales: p.canales ?? ['interna', 'push'] } }).then((r) => r.data),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['morosos'] }),
  });
}

export function useVistaPreviaLote() {
  return useMutation({
    mutationFn: (datos: DatosLote) => api<{ data: VistaPreviaLote }>('cuotas/lote/vista-previa', { method: 'POST', body: datos }).then((r) => r.data),
  });
}

export function useGenerarLote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (datos: DatosLote) => api<{ data: ResultadoLote }>('cuotas/lote', { method: 'POST', body: datos }).then((r) => r.data),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['cuotas'] }),
  });
}
