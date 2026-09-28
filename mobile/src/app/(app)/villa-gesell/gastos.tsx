import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { Campo, CampoFecha, CampoMonto, ErrorFormulario, requeridos, Selector, useFormulario } from '@/components/form';
import { BotonFlotante, ItemLista } from '@/components/lista';
import { Boton, Cargando, ErrorVista, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import type { CatalogoGira, GastoGira, PlanGira } from '@/features/villa-gesell/tipos';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function GastosGira() {
  const q = useQuery({ queryKey: ['villa-gesell', 'gastos'], queryFn: () => api<{ data: GastoGira[]; plan: PlanGira }>('villa-gesell/gastos') });
  const cat = useCatalogo<CatalogoGira>('villa-gesell/catalogo', 1);
  const [editando, setEditando] = useState<GastoGira | 'nuevo' | null>(null);

  if (q.isPending || cat.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: 'Gastos de la gira' }} />
      <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
        <Tarjeta acento={C.acento}>
          <Tenue>Gastos proyectados para {q.data.plan.dias} días</Tenue>
          <Texto style={{ fontSize: 24, fontWeight: '800' }}>{moneda(q.data.plan.gastos_totales)}</Texto>
          {Object.entries(q.data.plan.gastos_por_tipo).filter(([, v]) => v > 0).map(([k, v]) => <Tenue key={k}>{cat.data?.tipos_gasto[k] ?? k}: {moneda(v)}</Tenue>)}
        </Tarjeta>
        {q.data.data.length === 0 && <Vacio icono="account-balance-wallet" texto="Sin gastos cargados." />}
        {q.data.data.map((g) => (
          <ItemLista key={g.id} icono="account-balance-wallet" titulo={`${g.concepto} · ${moneda(g.monto)}`} subtitulo={`${g.tipo_nombre}${g.modo === 'por_dia' ? ' · por día' : ''}`} detalle={`Proyectado ${moneda(g.proyectado)}`} onPress={() => setEditando(g)} />
        ))}
      </Pantalla>
      <BotonFlotante icono="add" texto="Nuevo gasto" onPress={() => setEditando('nuevo')} />
      <Hoja visible={!!editando} titulo={editando === 'nuevo' ? 'Nuevo gasto' : 'Editar gasto'} onCerrar={() => setEditando(null)}>
        {editando && cat.data && <FormGasto gasto={editando === 'nuevo' ? undefined : editando} catalogo={cat.data} onCerrar={() => setEditando(null)} />}
      </Hoja>
    </View>
  );
}

function FormGasto({ gasto, catalogo, onCerrar }: { gasto?: GastoGira; catalogo: CatalogoGira; onCerrar: () => void }) {
  const f = useFormulario({ tipo: gasto?.tipo ?? 'diario', concepto: gasto?.concepto ?? '', monto: gasto ? String(gasto.monto) : '', modo: gasto?.modo ?? 'por_dia', fecha: gasto?.fecha ?? '', notas: gasto?.notas ?? '' });
  const inv = [['villa-gesell']];
  const guardar = useOperacion((d: typeof f.valores) => api(gasto ? `villa-gesell/gastos/${gasto.id}` : 'villa-gesell/gastos', { method: gasto ? 'PUT' : 'POST', body: { ...d, monto: Number(d.monto), fecha: d.fecha || null, notas: d.notas || null } }), { exito: gasto ? 'Gasto actualizado' : 'Gasto de la gira registrado', invalidar: inv, alTerminar: onCerrar });
  const borrar = useOperacion(() => api(`villa-gesell/gastos/${gasto?.id}`, { method: 'DELETE' }), { exito: 'Gasto eliminado', invalidar: inv, alTerminar: onCerrar });
  const { valores: v, set, errores: e } = f;
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Selector etiqueta="Tipo" opciones={Object.entries(catalogo.tipos_gasto).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.tipo} onChange={(x) => { set('tipo', x ?? 'otro'); if (x === 'diario') set('modo', 'por_dia'); }} error={e.tipo} />
      <Campo etiqueta="Concepto" requerido valor={v.concepto} onChange={(x) => set('concepto', x)} error={e.concepto} />
      <CampoMonto etiqueta="Monto ($)" requerido valor={v.monto} onChange={(x) => set('monto', x)} error={e.monto} />
      {v.tipo !== 'diario' && <Selector etiqueta="Cómo se calcula" opciones={Object.entries(catalogo.modos_gasto).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.modo} onChange={(x) => set('modo', x ?? 'total')} error={e.modo} />}
      <CampoFecha etiqueta="Fecha" valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.fecha} opcional />
      <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { concepto: 'El concepto', monto: 'El monto' }))} />
      {gasto && <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={borrar.isPending} onPress={async () => { if (await confirmar({ titulo: `¿Eliminar "${gasto.concepto}"?`, accion: 'Eliminar' })) borrar.mutate(); }} />}
    </>
  );
}
