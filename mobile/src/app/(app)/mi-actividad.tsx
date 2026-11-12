import { router, Stack } from 'expo-router';
import { useMemo, useState } from 'react';
import { View } from 'react-native';

import { FiltrosChips, ItemLista } from '@/components/lista';
import { Boton, Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { MiAsistenciaVista, MiHorario } from '@/features/asistencia/MiAsistencia';
import { ESTADO_CUOTA, formatearFecha } from '@/lib/formato';
import { useMiEstadoCuenta, usePartituras } from '@/lib/queries';
import { C, E, moneda } from '@/lib/theme';
import type { ItemCuenta } from '@/lib/types';

type Seccion = 'pendientes' | 'pagadas' | 'asistencia' | 'material';

const pendiente = (i: ItemCuenta) => i.saldo > 0;

/** Espacio del alumno: qué cuotas debe, cuáles ya pagó y el material de estudio de su año. */
export default function MiActividad() {
  const cuentas = useMiEstadoCuenta();
  const material = usePartituras();
  const items = useMemo(() => (cuentas.data ?? []).flatMap((c) => c.items), [cuentas.data]);
  const pendientes = items.filter(pendiente);
  const pagadas = items.filter((i) => !pendiente(i)).sort((a, b) => (b.pagado_el ?? b.periodo).localeCompare(a.pagado_el ?? a.periodo));
  const [seccion, setSeccion] = useState<Seccion>('pendientes');

  if (cuentas.isPending && !cuentas.data) return <Cargando />;
  if (cuentas.isError && !cuentas.data) return <ErrorVista error={cuentas.error} onReintentar={() => cuentas.refetch()} />;

  const deuda = pendientes.reduce((t, i) => t + i.saldo, 0);
  const porAnio = new Map<number, NonNullable<typeof material.data>>();
  (material.data ?? []).forEach((t) => porAnio.set(t.anio, [...(porAnio.get(t.anio) ?? []), t]));

  return (
    <Pantalla refrescando={cuentas.isRefetching || material.isRefetching} onRefrescar={() => { void cuentas.refetch(); void material.refetch(); }}>
      <Stack.Screen options={{ title: 'Mi actividad' }} />

      <Tarjeta acento={deuda > 0 ? C.alerta : C.exito}>
        <Tenue>{deuda > 0 ? 'Tenés para pagar' : 'Estás al día'}</Tenue>
        <Texto style={{ fontSize: 28, fontWeight: '800' }}>{deuda > 0 ? moneda(deuda) : '¡Gracias!'}</Texto>
        <Tenue>{pendientes.length} {pendientes.length === 1 ? 'cuota pendiente' : 'cuotas pendientes'} · {pagadas.length} {pagadas.length === 1 ? 'pagada' : 'pagadas'}</Tenue>
      </Tarjeta>

      <Boton titulo="Mi progreso en los toques" icono="trending-up" variante="secundario" onPress={() => router.push('/mi-progreso' as never)} />
      <Boton titulo="Mis préstamos de instrumentos" icono="inventory-2" variante="secundario" onPress={() => router.push('/mis-prestamos' as never)} />

      <FiltrosChips<Seccion>
        todos={false}
        valor={seccion}
        onChange={(v) => v && setSeccion(v)}
        opciones={[
          { valor: 'pendientes', etiqueta: `Pendientes (${pendientes.length})` },
          { valor: 'pagadas', etiqueta: `Pagadas (${pagadas.length})` },
          { valor: 'asistencia', etiqueta: 'Asistencia' },
          { valor: 'material', etiqueta: 'Material de estudio' },
        ]}
      />

      {seccion === 'pendientes' && (
        <View style={{ gap: E.s }}>
          {pendientes.length === 0 && <Vacio icono="check-circle" texto="No tenés cuotas pendientes." />}
          {pendientes.map((i) => (
            <ItemLista
              key={i.cuota_id}
              icono="receipt-long"
              colorIcono={ESTADO_CUOTA[i.estado].color}
              titulo={`${i.nombre} · ${moneda(i.saldo)}`}
              subtitulo={i.vencimiento ? `Vence el ${formatearFecha(i.vencimiento)}` : null}
              detalle={i.pagado > 0 ? `Pagaste ${moneda(i.pagado)} de ${moneda(i.neto)}` : null}
              derecha={<Chip texto={ESTADO_CUOTA[i.estado].texto} color={ESTADO_CUOTA[i.estado].color} />}
            />
          ))}
          {pendientes.length > 0 && <Boton titulo="Pagar o enviar comprobante" icono="payments" grande onPress={() => router.push('/mi-cuenta' as never)} />}
        </View>
      )}

      {seccion === 'pagadas' && (
        <View style={{ gap: E.s }}>
          {pagadas.length === 0 && <Vacio icono="receipt" texto="Todavía no hay cuotas pagadas este año." />}
          {pagadas.map((i) => (
            <ItemLista
              key={i.cuota_id}
              icono="check-circle"
              colorIcono={ESTADO_CUOTA[i.estado].color}
              titulo={`${i.nombre} · ${moneda(i.neto)}`}
              subtitulo={i.estado === 'becada' ? i.beca?.etiqueta ?? 'Becada' : i.pagado_el ? `Pagada el ${formatearFecha(i.pagado_el)}` : 'Pagada'}
              derecha={<Chip texto={ESTADO_CUOTA[i.estado].texto} color={ESTADO_CUOTA[i.estado].color} />}
            />
          ))}
        </View>
      )}

      {seccion === 'asistencia' && (
        <View style={{ gap: E.m }}>
          <MiHorario />
          <MiAsistenciaVista />
        </View>
      )}

      {seccion === 'material' && (
        <View style={{ gap: E.s }}>
          {material.isPending && <Tenue>Cargando…</Tenue>}
          {material.isError && <Tenue>No se pudo cargar el material.</Tenue>}
          {material.data?.length === 0 && <Vacio icono="library-music" texto="Todavía no hay material publicado para tu año." />}
          {[...porAnio.entries()].sort(([a], [b]) => b - a).map(([anio, toques]) => (
            <View key={anio} style={{ gap: E.s }}>
              <Subtitulo>Toques de {anio}° año</Subtitulo>
              {toques.map((t) => (
                <ItemLista
                  key={t.slug}
                  icono={t.tiene_partitura ? 'music-note' : 'description'}
                  colorIcono={t.tiene_partitura ? C.acento : C.tenue}
                  titulo={t.nombre}
                  subtitulo={t.autor}
                  detalle={t.tiene_partitura ? 'Partitura para estudiar' : null}
                  onPress={() => router.push({ pathname: '/partituras/[slug]', params: { slug: t.slug } } as never)}
                />
              ))}
            </View>
          ))}
        </View>
      )}
    </Pantalla>
  );
}
