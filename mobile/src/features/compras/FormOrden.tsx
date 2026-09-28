import * as Crypto from 'expo-crypto';
import { router } from 'expo-router';
import { Pressable, View } from 'react-native';

import { Campo, CampoFecha, CampoMonto, ErrorFormulario, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Fila, Icon, Pantalla, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { C, E, moneda } from '@/lib/theme';

export const COLOR_ESTADO_ORDEN: Record<string, string> = { borrador: C.tenue, enviada: C.info, aprobada: C.exito, recibida: C.exito, cancelada: C.peligro };

export interface ItemOrden { id?: number; tipo: string | null; familia: string | null; descripcion: string; marca: string | null; modelo: string | null; medida: string | null; cantidad: number; unidad: string; precio_estimado: number | null; subtotal_estimado?: number }

export interface Orden {
  id: number;
  sede: { id: number; nombre: string } | null;
  motivo: string;
  motivo_nombre: string;
  estado: string;
  estado_nombre: string;
  fecha_objetivo: string | null;
  justificacion: string | null;
  total_estimado: number;
  cantidad_items: number | null;
  creado_por: string | null;
  creado_at: string | null;
  items: ItemOrden[] | null;
  acciones: { editar: boolean; eliminar: boolean; estados_posibles: string[] } | null;
}

export interface CatalogoCompras { estados: Record<string, string>; motivos: Record<string, string>; sedes: { id: number; nombre: string }[] }

interface FilaItem { clave: string; descripcion: string; cantidad: string; unidad: string; precio: string; marca: string; medida: string }
const fila = (i?: ItemOrden): FilaItem => ({ clave: Crypto.randomUUID(), descripcion: i?.descripcion ?? '', cantidad: i ? String(i.cantidad) : '1', unidad: i?.unidad ?? 'u', precio: i?.precio_estimado != null ? String(i.precio_estimado) : '', marca: i?.marca ?? '', medida: i?.medida ?? '' });

export function FormOrden({ orden }: { orden?: Orden }) {
  const cat = useCatalogo<CatalogoCompras>('compras/catalogo', 1);
  const f = useFormulario({
    sede_id: (orden?.sede?.id ?? null) as number | null,
    motivo: orden?.motivo ?? 'reposicion',
    estado: orden?.estado ?? 'borrador',
    fecha_objetivo: orden?.fecha_objetivo ?? '',
    justificacion: orden?.justificacion ?? '',
    items: orden?.items?.length ? orden.items.map(fila) : [fila()],
  });
  const { valores: v, set, errores: e } = f;
  const total = v.items.reduce((s, i) => s + (Number(i.cantidad) || 0) * (Number(i.precio) || 0), 0);
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: Orden }>(orden ? `compras/${orden.id}` : 'compras', {
      method: orden ? 'PUT' : 'POST',
      body: {
        sede_id: d.sede_id, motivo: d.motivo, estado: d.estado, fecha_objetivo: d.fecha_objetivo || null, justificacion: d.justificacion || null,
        items: d.items.filter((i) => i.descripcion.trim()).map((i) => ({ descripcion: i.descripcion, cantidad: Number(i.cantidad) || 1, unidad: i.unidad || 'u', precio_estimado: i.precio === '' ? null : Number(i.precio), marca: i.marca || null, medida: i.medida || null })),
      },
    }),
    {
      exito: orden ? 'Orden de compra actualizada' : 'Orden de compra creada',
      invalidar: ['compras'],
      alTerminar: (r) => (orden ? router.back() : router.replace({ pathname: '/compras/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );
  const cambiar = (clave: string, p: Partial<FilaItem>) => set('items', v.items.map((i) => (i.clave === clave ? { ...i, ...p } : i)));

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  // Aprobar/recibir se hace desde la ficha (requiere permiso de aprobación).
  const estados = Object.entries(c.estados).filter(([k]) => !['aprobada', 'recibida'].includes(k) || k === orden?.estado).map(([valor, etiqueta]) => ({ valor, etiqueta }));

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Orden">
        <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} valorEtiqueta={orden?.sede?.nombre}
          opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
        <Selector etiqueta="Motivo" requerido opciones={Object.entries(c.motivos).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.motivo} onChange={(x) => set('motivo', x ?? 'otro')} error={e.motivo} />
        <Selector etiqueta="Estado" opciones={estados} valor={v.estado} onChange={(x) => set('estado', x ?? 'borrador')} error={e.estado} />
        <CampoFecha etiqueta="Fecha objetivo" valor={v.fecha_objetivo} onChange={(x) => set('fecha_objetivo', x)} error={e.fecha_objetivo} opcional />
        <Campo etiqueta="Justificación" valor={v.justificacion} onChange={(x) => set('justificacion', x)} error={e.justificacion} multilinea />
      </Seccion>
      <Seccion titulo="Ítems">
        {e.items && <Aviso tono="peligro" texto={e.items} />}
        {v.items.map((i, n) => (
          <View key={i.clave} style={{ gap: E.s, borderTopWidth: n ? 1 : 0, borderTopColor: C.borde, paddingTop: n ? E.m : 0 }}>
            <Fila style={{ justifyContent: 'space-between' }}>
              <Texto style={{ fontWeight: '800' }}>Ítem {n + 1}</Texto>
              {v.items.length > 1 && <Pressable onPress={() => set('items', v.items.filter((x) => x.clave !== i.clave))} accessibilityRole="button" accessibilityLabel={`Quitar ítem ${n + 1}`} hitSlop={10}><Icon name="delete-outline" size={24} color={C.peligro} /></Pressable>}
            </Fila>
            <Campo etiqueta={`Descripción (ítem ${n + 1})`} requerido valor={i.descripcion} onChange={(x) => cambiar(i.clave, { descripcion: x })} error={e[`items.${n}.descripcion`]} />
            <Fila style={{ alignItems: 'flex-start' }}>
              <View style={{ flex: 1 }}><Campo etiqueta={`Cantidad (ítem ${n + 1})`} valor={i.cantidad} onChange={(x) => cambiar(i.clave, { cantidad: x.replace(/[^0-9.]/g, '') })} teclado="decimal-pad" error={e[`items.${n}.cantidad`]} /></View>
              <View style={{ flex: 1 }}><Campo etiqueta={`Unidad (ítem ${n + 1})`} valor={i.unidad} onChange={(x) => cambiar(i.clave, { unidad: x })} /></View>
            </Fila>
            <CampoMonto etiqueta={`Precio estimado unitario (ítem ${n + 1})`} valor={i.precio} onChange={(x) => cambiar(i.clave, { precio: x })} error={e[`items.${n}.precio_estimado`]} />
            <Fila style={{ alignItems: 'flex-start' }}>
              <View style={{ flex: 1 }}><Campo etiqueta={`Marca (ítem ${n + 1})`} valor={i.marca} onChange={(x) => cambiar(i.clave, { marca: x })} /></View>
              <View style={{ flex: 1 }}><Campo etiqueta={`Medida (ítem ${n + 1})`} valor={i.medida} onChange={(x) => cambiar(i.clave, { medida: x })} /></View>
            </Fila>
          </View>
        ))}
        <Boton titulo="Agregar ítem" icono="add" variante="secundario" onPress={() => set('items', [...v.items, fila()])} />
        <Fila style={{ justifyContent: 'space-between' }}>
          <Texto style={{ fontWeight: '800' }}>Total estimado</Texto>
          <Texto style={{ fontWeight: '800', fontSize: 20 }}>{moneda(total)}</Texto>
        </Fila>
      </Seccion>
      <Boton titulo={orden ? 'Guardar cambios' : 'Crear orden'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { sede_id: 'La sede', motivo: 'El motivo' }),
          ...(!d.items.some((i) => i.descripcion.trim()) ? { items: 'Agregá al menos un ítem a la orden de compra.' } : {}),
        }))} />
    </Pantalla>
  );
}
