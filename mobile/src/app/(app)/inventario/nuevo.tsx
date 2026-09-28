import { useQuery } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';

import { Campo, ErrorFormulario, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import type { InventarioItem } from '@/lib/types';

interface Catalogos { tipos: Record<string, string>; estados: Record<string, string>; propietarios: Record<string, string> }

/** Alta de ítem o edición (?id=). */
export default function FormItem() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const item = useQuery({ queryKey: ['inventario', 'item', Number(id)], queryFn: () => api<{ data: InventarioItem }>(`inventario/${id}`).then((r) => r.data), enabled: !!id });
  if (id && item.isPending) return <Cargando />;
  if (id && item.isError) return <ErrorVista error={item.error} onReintentar={() => item.refetch()} />;
  return <Formulario item={item.data} />;
}

function Formulario({ item }: { item?: InventarioItem }) {
  const sedes = useCatalogo<{ data: { id: number; nombre: string }[] }>('sedes');
  const cat = useCatalogo<Catalogos>('inventario/catalogos', 24);
  const f = useFormulario({
    nombre: item?.nombre ?? '',
    sede_id: (item?.sede?.id ?? null) as number | null,
    tipo: item?.tipo ?? 'instrumento',
    estado: item?.estado ?? 'bueno',
    propietario_tipo: item?.propietario ?? 'escuela',
    cantidad: String(item?.cantidad ?? 1),
    marca: item?.marca ?? '',
    modelo: item?.modelo ?? '',
    medida: item?.medida ?? '',
    codigo: item?.codigo ?? '',
    notas: item?.notas ?? '',
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: InventarioItem }>(item ? `inventario/${item.id}` : 'inventario', {
      method: item ? 'PUT' : 'POST',
      body: { ...d, cantidad: Number(d.cantidad) || 0, marca: d.marca || null, modelo: d.modelo || null, medida: d.medida || null, codigo: d.codigo || null, notas: d.notas || null },
    }),
    {
      exito: item ? 'Ítem actualizado' : 'Ítem cargado correctamente',
      invalidar: ['inventario'],
      alTerminar: (r) => (item ? router.back() : router.replace({ pathname: '/inventario/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );
  if (cat.isPending) return <Cargando />;
  const opciones = (o?: Record<string, string>) => Object.entries(o ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }));

  return (
    <Pantalla>
      <Stack.Screen options={{ title: item ? 'Editar ítem' : 'Cargar ítem' }} />
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Ítem">
        <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} placeholder='Ej.: Surdo 22"' />
        <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} valorEtiqueta={item?.sede?.nombre}
          opciones={(sedes.data?.data ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
        <Selector etiqueta="Tipo" opciones={opciones(cat.data?.tipos)} valor={v.tipo} onChange={(x) => set('tipo', x ?? 'otro')} error={e.tipo} />
        {!item && <Selector etiqueta="Estado" opciones={opciones(cat.data?.estados)} valor={v.estado} onChange={(x) => set('estado', x ?? 'bueno')} error={e.estado} />}
        <Selector etiqueta="Propietario" opciones={opciones(cat.data?.propietarios)} valor={v.propietario_tipo} onChange={(x) => set('propietario_tipo', x ?? 'escuela')} error={e.propietario_tipo} />
        <Campo etiqueta="Cantidad" requerido valor={v.cantidad} onChange={(x) => set('cantidad', x.replace(/[^0-9.]/g, ''))} teclado="decimal-pad" error={e.cantidad} />
      </Seccion>
      <Seccion titulo="Detalle">
        <Campo etiqueta="Marca" valor={v.marca} onChange={(x) => set('marca', x)} error={e.marca} />
        <Campo etiqueta="Modelo" valor={v.modelo} onChange={(x) => set('modelo', x)} error={e.modelo} />
        <Campo etiqueta="Medida" valor={v.medida} onChange={(x) => set('medida', x)} error={e.medida} placeholder='Ej.: 22"' />
        <Campo etiqueta="Código de etiqueta" valor={v.codigo} onChange={(x) => set('codigo', x)} error={e.codigo} placeholder="Se genera solo si lo dejás vacío" autoCapitalize="characters" />
        <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea />
      </Seccion>
      <Boton titulo={item ? 'Guardar cambios' : 'Guardar'} icono="save" grande cargando={f.enviando} onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { nombre: 'El nombre', sede_id: 'La sede' }))} />
    </Pantalla>
  );
}
