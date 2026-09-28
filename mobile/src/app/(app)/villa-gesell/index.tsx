import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { Hoja } from '@/components/feedback';
import { Campo, CampoFecha, CampoMonto, ErrorFormulario, useFormulario } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Dato, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import type { ConfigGira, PlanGira } from '@/features/villa-gesell/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

/** Gira a Villa Gesell: datos, cupo y plan económico, con acceso a cada sección. */
export default function VillaGesell() {
  const q = useQuery({ queryKey: ['villa-gesell', 'resumen'], queryFn: () => api<{ config: ConfigGira; plan: PlanGira }>('villa-gesell') });
  const [editando, setEditando] = useState(false);

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const { config: c, plan: p } = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Villa Gesell' }} />
      <Tarjeta acento={C.acento}>
        <Texto style={{ fontWeight: '800', fontSize: 18 }}>{c.fecha_inicio ? `${formatearFecha(c.fecha_inicio)} al ${c.fecha_fin ? formatearFecha(c.fecha_fin) : '—'}` : 'Fechas sin definir'}</Texto>
        <Tenue>{c.dias} días · aporte {moneda(c.valor_por_dia)} por día</Tenue>
        <Fila style={{ justifyContent: 'space-between' }}>
          <Tenue>Plazas</Tenue>
          <Texto style={{ fontWeight: '800' }}>{p.plazas_ocupadas} / {p.cupo}{p.lista_espera ? ` · ${p.lista_espera} en espera` : ''}</Texto>
        </Fila>
        {c.notas && <Tenue>{c.notas}</Tenue>}
      </Tarjeta>
      <Acciones>
        <Boton titulo="Inscriptos" icono="groups" onPress={() => router.push('/villa-gesell/inscriptos' as never)} />
        <Boton titulo="Calendario" icono="event" variante="secundario" onPress={() => router.push('/villa-gesell/calendario' as never)} />
        <Boton titulo="Gastos" icono="account-balance-wallet" variante="secundario" onPress={() => router.push('/villa-gesell/gastos' as never)} />
        <Boton titulo="Insumos" icono="inventory-2" variante="secundario" onPress={() => router.push('/villa-gesell/insumos' as never)} />
        <Boton titulo="Datos de la gira" icono="edit" variante="secundario" onPress={() => setEditando(true)} />
      </Acciones>

      <Subtitulo>Plan económico</Subtitulo>
      <Tarjeta>
        <Dato etiqueta="Ingresos cobrados" valor={moneda(p.ingresos_pagados)} />
        <Dato etiqueta="Ingresos esperados" valor={moneda(p.ingresos_esperados)} />
        <Dato etiqueta="Si se llena el cupo" valor={moneda(p.ingresos_si_cupo_lleno)} />
        <Dato etiqueta="Gastos proyectados" valor={moneda(p.gastos_totales)} />
        <Dato etiqueta="Insumos" valor={moneda(p.insumos_totales)} />
      </Tarjeta>
      {[
        ['Balance con lo cobrado', p.balance_pagado],
        ['Balance con lo esperado', p.balance_esperado],
        ['Balance con cupo lleno', p.balance_cupo_lleno],
      ].map(([t, v]) => (
        <ItemLista key={String(t)} icono={Number(v) >= 0 ? 'trending-up' : 'trending-down'} colorIcono={Number(v) >= 0 ? C.exito : C.peligro} titulo={moneda(Number(v))} subtitulo={String(t)} />
      ))}
      <Hoja visible={editando} titulo="Datos de la gira" onCerrar={() => setEditando(false)}>
        {editando && <EditarConfig config={c} onCerrar={() => setEditando(false)} />}
      </Hoja>
    </Pantalla>
  );
}

function EditarConfig({ config, onCerrar }: { config: ConfigGira; onCerrar: () => void }) {
  const f = useFormulario({
    fecha_inicio: config.fecha_inicio ?? '',
    fecha_fin: config.fecha_fin ?? '',
    cupo_maximo: String(config.cupo_maximo),
    aporte_esperado: String(config.aporte_esperado),
    notas: config.notas ?? '',
  });
  const op = useOperacion(
    (d: typeof f.valores) => api('villa-gesell/config', { method: 'PUT', body: { ...d, cupo_maximo: Number(d.cupo_maximo), aporte_esperado: Number(d.aporte_esperado), notas: d.notas || null } }),
    { exito: 'Datos de la gira actualizados', invalidar: ['villa-gesell'], alTerminar: onCerrar },
  );
  const { valores: v, set, errores: e } = f;
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <CampoFecha etiqueta="Desde" requerido valor={v.fecha_inicio} onChange={(x) => set('fecha_inicio', x)} error={e.fecha_inicio} />
      <CampoFecha etiqueta="Hasta" requerido valor={v.fecha_fin} onChange={(x) => set('fecha_fin', x)} error={e.fecha_fin} minimo={v.fecha_inicio} />
      <Campo etiqueta="Cupo máximo" requerido valor={v.cupo_maximo} onChange={(x) => set('cupo_maximo', x.replace(/\D/g, ''))} teclado="number-pad" error={e.cupo_maximo} />
      <CampoMonto etiqueta="Aporte por día ($)" requerido valor={v.aporte_esperado} onChange={(x) => set('aporte_esperado', x)} error={e.aporte_esperado} />
      <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d))} />
    </>
  );
}

