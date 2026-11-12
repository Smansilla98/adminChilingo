import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, View } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Fila, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { useCalendario } from '@/lib/queries';
import { C, E } from '@/lib/theme';
import type { ItemAgenda } from '@/lib/types';

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const sumarDias = (d: Date, n: number) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

const COLOR: Record<string, string> = { clase: C.info, show: C.acento, convocatoria: C.acento, taller: C.exito, muestra: C.alerta, reunion: C.tenue };
const NOMBRE: Record<string, string> = { clase: 'Clase', show: 'Show', convocatoria: 'Convocatoria', taller: 'Taller', muestra: 'Muestra', gira: 'Gira', otro: 'Evento' };

/** Abre el detalle según el origen del ítem: evento-12, show-3, clase-{bloque}-{fecha}. */
function abrir(i: ItemAgenda) {
  const [origen, id] = i.id.split('-');
  if (origen === 'evento') router.push({ pathname: '/eventos/[id]', params: { id } } as never);
  else if (origen === 'show') router.push({ pathname: '/shows/[id]', params: { id } } as never);
  else if (origen === 'clase' && i.bloque_id) router.push({ pathname: '/bloques/[id]', params: { id: String(i.bloque_id) } } as never);
}

/** Fila de la lista: el encabezado de un día o un evento. */
type FilaAgenda = { clave: string; dia: string } | { clave: string; item: ItemAgenda };

export default function Agenda() {
  const [inicio, setInicio] = useState(() => new Date());
  const desde = iso(inicio);
  const hasta = iso(sumarDias(inicio, 13));
  const q = useCalendario(desde, hasta);

  // Los eventos se agrupan por día; la lista plana es lo que recorre la FlatList.
  const filas = useMemo<FilaAgenda[]>(() => {
    const grupos = new Map<string, ItemAgenda[]>();
    (q.data ?? []).forEach((i) => grupos.set(i.fecha, [...(grupos.get(i.fecha) ?? []), i]));
    return [...grupos.entries()].flatMap(([fecha, items]) => [
      { clave: `dia-${fecha}`, dia: fecha },
      ...items.map((item) => ({ clave: `item-${item.id}-${fecha}`, item })),
    ]);
  }, [q.data]);

  const cabecera = (
    <Fila style={{ marginBottom: E.s }}>
      <View style={{ flex: 1 }}><Boton titulo="Anterior" icono="chevron-left" variante="secundario" onPress={() => setInicio(sumarDias(inicio, -14))} /></View>
      <View style={{ flex: 1 }}><Boton titulo="Hoy" variante="secundario" onPress={() => setInicio(new Date())} /></View>
      <View style={{ flex: 1 }}><Boton titulo="Siguiente" icono="chevron-right" variante="secundario" onPress={() => setInicio(sumarDias(inicio, 14))} /></View>
    </Fila>
  );

  if (q.isPending && !q.data) {
    return <View style={{ flex: 1, backgroundColor: C.fondo, padding: E.l }}>{cabecera}<Cargando /></View>;
  }
  if (q.isError && !q.data) {
    return <View style={{ flex: 1, backgroundColor: C.fondo, padding: E.l }}>{cabecera}<ErrorVista error={q.error} onReintentar={() => q.refetch()} /></View>;
  }

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: C.fondo }}
      data={filas}
      keyExtractor={(f) => f.clave}
      contentContainerStyle={{ padding: E.l, gap: E.s, paddingBottom: E.xxl * 2 }}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} tintColor={C.acento} />}
      ListHeaderComponent={cabecera}
      ListEmptyComponent={<Vacio icono="event-busy" texto="Nada agendado en estas dos semanas." />}
      renderItem={({ item: f }: { item: FilaAgenda }) => {
        if ('dia' in f) {
          return (
            <View style={{ marginTop: E.s }}>
              <Subtitulo>{new Date(f.dia + 'T12:00:00').toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' })}</Subtitulo>
            </View>
          );
        }
        const i = f.item;
        return (
          <Tarjeta acento={COLOR[i.tipo] ?? C.tenue} style={s.item} onPress={() => abrir(i)}>
            <Fila>
              <Texto style={{ fontWeight: '800', flex: 1 }}>{i.inicio ? `${i.inicio}${i.fin ? '–' + i.fin : ''} · ` : ''}{i.titulo}</Texto>
              <Chip texto={NOMBRE[i.tipo] ?? i.tipo} />
            </Fila>
            {i.sede && <Tenue>{i.sede}</Tenue>}
          </Tarjeta>
        );
      }}
    />
  );
}

const s = StyleSheet.create({ item: { paddingVertical: E.m } });
