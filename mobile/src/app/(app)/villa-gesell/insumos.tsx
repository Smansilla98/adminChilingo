import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { Campo, CampoMonto, ErrorFormulario, requeridos, SelectorLista, useFormulario } from '@/components/form';
import { BotonFlotante, ItemLista } from '@/components/lista';
import { Boton, Cargando, ErrorVista, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import type { CatalogoGira, InsumoGira } from '@/features/villa-gesell/tipos';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function InsumosGira() {
  const q = useQuery({ queryKey: ['villa-gesell', 'insumos'], queryFn: () => api<{ data: InsumoGira[]; total: number }>('villa-gesell/insumos') });
  const cat = useCatalogo<CatalogoGira>('villa-gesell/catalogo', 1);
  const [editando, setEditando] = useState<InsumoGira | 'nuevo' | null>(null);

  if (q.isPending || cat.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: 'Insumos de la gira' }} />
      <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
        <Tarjeta acento={C.acento}>
          <Tenue>Total de insumos</Tenue>
          <Texto style={{ fontSize: 24, fontWeight: '800' }}>{moneda(q.data.total)}</Texto>
        </Tarjeta>
        {q.data.data.length === 0 && <Vacio icono="inventory-2" texto="Sin insumos cargados." />}
        {q.data.data.map((i) => (
          <ItemLista key={i.id} icono="inventory-2" titulo={`${i.nombre} · ${moneda(i.costo_total)}`} subtitulo={`${i.categoria_nombre} · ${i.cantidad}${i.unidad ? ` ${i.unidad}` : ''} × ${moneda(i.costo_unitario)}`} onPress={() => setEditando(i)} />
        ))}
      </Pantalla>
      <BotonFlotante icono="add" texto="Nuevo insumo" onPress={() => setEditando('nuevo')} />
      <Hoja visible={!!editando} titulo={editando === 'nuevo' ? 'Nuevo insumo' : 'Editar insumo'} onCerrar={() => setEditando(null)}>
        {editando && cat.data && <FormInsumo insumo={editando === 'nuevo' ? undefined : editando} catalogo={cat.data} onCerrar={() => setEditando(null)} />}
      </Hoja>
    </View>
  );
}

function FormInsumo({ insumo, catalogo, onCerrar }: { insumo?: InsumoGira; catalogo: CatalogoGira; onCerrar: () => void }) {
  const f = useFormulario({ nombre: insumo?.nombre ?? '', categoria: insumo?.categoria ?? 'percusion', cantidad: String(insumo?.cantidad ?? 1), unidad: insumo?.unidad ?? '', costo_unitario: insumo ? String(insumo.costo_unitario) : '', notas: insumo?.notas ?? '' });
  const inv = [['villa-gesell']];
  const guardar = useOperacion((d: typeof f.valores) => api(insumo ? `villa-gesell/insumos/${insumo.id}` : 'villa-gesell/insumos', { method: insumo ? 'PUT' : 'POST', body: { ...d, cantidad: Number(d.cantidad), costo_unitario: Number(d.costo_unitario), unidad: d.unidad || null, notas: d.notas || null } }), { exito: insumo ? 'Insumo actualizado' : 'Insumo registrado', invalidar: inv, alTerminar: onCerrar });
  const borrar = useOperacion(() => api(`villa-gesell/insumos/${insumo?.id}`, { method: 'DELETE' }), { exito: 'Insumo eliminado', invalidar: inv, alTerminar: onCerrar });
  const { valores: v, set, errores: e } = f;
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} />
      <SelectorLista etiqueta="Categoría" requerido valor={v.categoria} onChange={(x) => set('categoria', x ?? 'otro')} error={e.categoria} opciones={Object.entries(catalogo.categorias_insumo).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
      <Campo etiqueta="Cantidad" requerido valor={v.cantidad} onChange={(x) => set('cantidad', x.replace(/[^0-9.]/g, ''))} teclado="decimal-pad" error={e.cantidad} />
      <Campo etiqueta="Unidad" valor={v.unidad} onChange={(x) => set('unidad', x)} error={e.unidad} />
      <CampoMonto etiqueta="Costo unitario ($)" requerido valor={v.costo_unitario} onChange={(x) => set('costo_unitario', x)} error={e.costo_unitario} />
      <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { nombre: 'El nombre', costo_unitario: 'El costo' }))} />
      {insumo && <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={borrar.isPending} onPress={async () => { if (await confirmar({ titulo: `¿Eliminar "${insumo.nombre}"?`, accion: 'Eliminar' })) borrar.mutate(); }} />}
    </>
  );
}
