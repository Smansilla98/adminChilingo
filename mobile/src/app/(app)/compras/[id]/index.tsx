import { router, Stack, useLocalSearchParams } from 'expo-router';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta } from '@/components/ui';
import { COLOR_ESTADO_ORDEN, type Orden } from '@/features/compras/FormOrden';
import { api } from '@/lib/api';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

const TEXTO_ESTADO: Record<string, { boton: string; icono: 'send' | 'check-circle' | 'inventory' | 'cancel' | 'edit-note'; confirmacion?: string }> = {
  borrador: { boton: 'Volver a borrador', icono: 'edit-note' },
  enviada: { boton: 'Marcar enviada', icono: 'send' },
  aprobada: { boton: 'Aprobar', icono: 'check-circle', confirmacion: 'Aprobar la orden es una decisión de gasto. Queda registrado quién la aprobó.' },
  recibida: { boton: 'Marcar recibida', icono: 'inventory', confirmacion: 'Confirmá que la compra llegó completa.' },
  cancelada: { boton: 'Cancelar orden', icono: 'cancel', confirmacion: 'La orden queda cancelada. Podés volver a borrador después.' },
};

export default function FichaOrden() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Orden>('compras', id);
  const estado = useOperacion((nuevo: string) => api(`compras/${id}/estado`, { method: 'POST', body: { estado: nuevo } }), { exito: 'Estado de la orden actualizado', invalidar: ['compras'] });
  const eliminar = useOperacion(() => api(`compras/${id}`, { method: 'DELETE' }), { exito: 'Orden eliminada', invalidar: ['compras'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const o = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: `Orden #${o.id}` }} />
      <Encabezado icono="shopping-cart" titulo={moneda(o.total_estimado)} subtitulo={`${o.sede?.nombre ?? ''} · ${o.motivo_nombre}`} chips={<Chip texto={o.estado_nombre} color={COLOR_ESTADO_ORDEN[o.estado] ?? C.tenue} />} />
      <Acciones>
        {(o.acciones?.estados_posibles ?? []).map((e) => {
          const t = TEXTO_ESTADO[e];
          return (
            <Boton key={e} titulo={t?.boton ?? e} icono={t?.icono} variante={e === 'cancelada' ? 'peligro' : e === 'aprobada' ? 'primario' : 'secundario'} cargando={estado.isPending}
              onPress={async () => { if (!t?.confirmacion || await confirmar({ titulo: `${t.boton}?`, mensaje: t.confirmacion, accion: t.boton, destructiva: e === 'cancelada' })) estado.mutate(e); }} />
          );
        })}
        {o.acciones?.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/compras/[id]/editar', params: { id: String(o.id) } } as never)} />}
        {o.acciones?.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar la orden #${o.id}?`, mensaje: 'Se borran también sus ítems. Si solo querés frenarla, cancelala.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>
      <Tarjeta>
        <Dato etiqueta="Fecha objetivo" valor={o.fecha_objetivo} />
        <Dato etiqueta="Justificación" valor={o.justificacion} />
        <Dato etiqueta="Creada por" valor={o.creado_por ? `${o.creado_por}${o.creado_at ? ` · ${new Date(o.creado_at).toLocaleDateString('es-AR')}` : ''}` : null} />
      </Tarjeta>
      <Subtitulo>Ítems ({o.items?.length ?? 0})</Subtitulo>
      {o.items?.map((i) => (
        <ItemLista key={i.id} icono="inventory-2" titulo={i.descripcion} subtitulo={`${i.cantidad} ${i.unidad}${i.precio_estimado != null ? ` × ${moneda(i.precio_estimado)}` : ''}`}
          detalle={[i.marca, i.modelo, i.medida].filter(Boolean).join(' · ') || null}
          derecha={i.subtotal_estimado ? <Chip texto={moneda(i.subtotal_estimado)} /> : undefined} />
      ))}
    </Pantalla>
  );
}
