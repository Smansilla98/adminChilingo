import { useMemo, useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { useCalendario } from '@/lib/queries';
import { C, E } from '@/lib/theme';
import type { ItemAgenda } from '@/lib/types';

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const sumarDias = (d: Date, n: number) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

const COLOR: Record<string, string> = { clase: C.info, show: C.acento, convocatoria: C.acento, taller: C.exito, muestra: C.alerta, reunion: C.tenue };
const NOMBRE: Record<string, string> = { clase: 'Clase', show: 'Show', convocatoria: 'Convocatoria', taller: 'Taller', muestra: 'Muestra', gira: 'Gira', otro: 'Evento' };

export default function Agenda() {
  const [inicio, setInicio] = useState(() => new Date());
  const desde = iso(inicio);
  const hasta = iso(sumarDias(inicio, 13));
  const q = useCalendario(desde, hasta);

  const porDia = useMemo(() => {
    const grupos = new Map<string, ItemAgenda[]>();
    (q.data ?? []).forEach((i) => grupos.set(i.fecha, [...(grupos.get(i.fecha) ?? []), i]));
    return [...grupos.entries()];
  }, [q.data]);

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Fila>
        <View style={{ flex: 1 }}><Boton titulo="Anterior" icono="chevron-left" variante="secundario" onPress={() => setInicio(sumarDias(inicio, -14))} /></View>
        <View style={{ flex: 1 }}><Boton titulo="Hoy" variante="secundario" onPress={() => setInicio(new Date())} /></View>
        <View style={{ flex: 1 }}><Boton titulo="Siguiente" icono="chevron-right" variante="secundario" onPress={() => setInicio(sumarDias(inicio, 14))} /></View>
      </Fila>
      {q.isPending && !q.data && <Cargando />}
      {q.isError && !q.data && <ErrorVista error={q.error} onReintentar={() => q.refetch()} />}
      {q.data && porDia.length === 0 && <Vacio icono="event-busy" texto="Nada agendado en estas dos semanas." />}
      {porDia.map(([fecha, items]) => (
        <View key={fecha} style={{ gap: E.s }}>
          <Subtitulo>{new Date(fecha + 'T12:00:00').toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' })}</Subtitulo>
          {items.map((i) => (
            <Tarjeta key={i.id} acento={COLOR[i.tipo] ?? C.tenue} style={s.item}>
              <Fila>
                <Texto style={{ fontWeight: '800', flex: 1 }}>{i.inicio ? `${i.inicio}${i.fin ? '–' + i.fin : ''} · ` : ''}{i.titulo}</Texto>
                <Chip texto={NOMBRE[i.tipo] ?? i.tipo} />
              </Fila>
              {i.sede && <Tenue>{i.sede}</Tenue>}
            </Tarjeta>
          ))}
        </View>
      ))}
    </Pantalla>
  );
}

const s = StyleSheet.create({ item: { paddingVertical: E.m } });
