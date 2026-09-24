import { router } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { Cargando, ErrorVista, Icon, Pantalla, Subtitulo, Tenue, Texto, Vacio } from '@/components/ui';
import { usePartituras } from '@/lib/queries';
import { C, E } from '@/lib/theme';

export default function Partituras() {
  const q = usePartituras();
  const toques = q.data;
  const porAnio = useMemo(() => {
    const g = new Map<number, NonNullable<typeof toques>>();
    (toques ?? []).forEach((t) => g.set(t.anio, [...(g.get(t.anio) ?? []), t]));
    return [...g.entries()].sort(([a], [b]) => a - b);
  }, [toques]);

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      {porAnio.length === 0 && <Vacio icono="music-off" texto="No hay toques publicados." />}
      {porAnio.map(([anio, toques]) => (
        <View key={anio} style={{ gap: E.s }}>
          <Subtitulo>{anio}° año</Subtitulo>
          {toques.map((t) => (
            <Pressable key={t.slug} style={({ pressed }) => [s.fila, pressed && { opacity: 0.7 }]} accessibilityRole="button" onPress={() => router.push({ pathname: '/partituras/[slug]', params: { slug: t.slug } } as never)}>
              <Icon name={t.tiene_partitura ? 'music-note' : 'description'} size={24} color={t.tiene_partitura ? C.acento : C.tenue} />
              <View style={{ flex: 1 }}>
                <Texto style={{ fontWeight: '700' }}>{t.nombre}</Texto>
                {t.autor && <Tenue>{t.autor}</Tenue>}
              </View>
              <Icon name="chevron-right" size={24} color={C.tenue} />
            </Pressable>
          ))}
        </View>
      ))}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  fila: { flexDirection: 'row', alignItems: 'center', gap: E.m, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, padding: E.m, minHeight: 60 },
});
