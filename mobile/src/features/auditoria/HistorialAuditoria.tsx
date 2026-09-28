import { router } from 'expo-router';
import { View } from 'react-native';

import { ItemLista, useListado } from '@/components/lista';
import { Boton, ErrorVista, Tenue } from '@/components/ui';
import { C, E } from '@/lib/theme';

export interface RegistroAuditoria {
  id: number;
  accion: string;
  accion_nombre: string;
  entidad: string;
  entidad_id: number | null;
  usuario: { id: number; nombre: string; username: string } | null;
  origen: 'web' | 'api' | 'cli' | null;
  fecha: string;
  campos: string[];
  antes: Record<string, unknown> | null;
  despues: Record<string, unknown> | null;
  ip: string | null;
}

export const COLOR_ACCION: Record<string, string> = { created: C.exito, updated: C.info, deleted: C.peligro, fusionada: C.alerta };
export const ORIGEN: Record<string, string> = { web: 'Panel web', api: 'App', cli: 'Sistema' };

export const fechaHora = (iso: string) =>
  new Date(iso).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });

export function FilaAuditoria({ r, conEntidad }: { r: RegistroAuditoria; conEntidad?: boolean }) {
  return (
    <ItemLista
      titulo={`${r.accion_nombre}${conEntidad ? ` · ${r.entidad}${r.entidad_id ? ` #${r.entidad_id}` : ''}` : ''}`}
      subtitulo={`${fechaHora(r.fecha)} · ${r.usuario?.nombre ?? 'Sistema'}${r.origen ? ` · ${ORIGEN[r.origen] ?? r.origen}` : ''}`}
      detalle={r.campos.length ? `Campos: ${r.campos.slice(0, 5).join(', ')}${r.campos.length > 5 ? '…' : ''}` : null}
      acento={COLOR_ACCION[r.accion] ?? C.tenue}
      onPress={() => router.push({ pathname: '/auditoria/[id]', params: { id: String(r.id) } } as never)}
    />
  );
}

/** Últimos cambios registrados de una entidad (para las fichas). */
export function HistorialAuditoria({ entidad, id }: { entidad: string; id: number }) {
  const q = useListado<RegistroAuditoria>('auditoria', { entidad, entidad_id: id, por_pagina: 20 });
  const items = q.data?.pages.flatMap((p) => p.data) ?? [];
  if (q.isPending) return <Tenue>Cargando historial…</Tenue>;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <View style={{ gap: E.s }}>
      {items.length === 0 && <Tenue>Sin cambios registrados.</Tenue>}
      {items.map((r) => <FilaAuditoria key={r.id} r={r} />)}
      {q.hasNextPage && <Boton titulo="Ver más" variante="secundario" cargando={q.isFetchingNextPage} onPress={() => void q.fetchNextPage()} />}
    </View>
  );
}
