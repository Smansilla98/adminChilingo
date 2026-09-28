import { useQuery } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { router, Stack } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { BotonFlotante } from '@/components/lista';
import { Cargando, ErrorVista, Icon, Pantalla, Tenue, Vacio } from '@/components/ui';
import type { Diseno } from '@/features/disenos/tipos';
import { api } from '@/lib/api';
import { C, E } from '@/lib/theme';

/** Mis diseños (piezas gráficas). */
export default function Disenos() {
  const q = useQuery({ queryKey: ['disenos', 'lista'], queryFn: () => api<Diseno[]>('disenos') });

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: 'Diseño' }} />
      {q.isPending ? <Cargando /> : q.isError ? <ErrorVista error={q.error} onReintentar={() => q.refetch()} /> : (
        <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
          {q.data.length === 0 && <Vacio icono="palette" texto="Todavía no tenés diseños. Creá uno desde una plantilla." />}
          <View style={s.grilla}>
            {q.data.map((d) => (
              <Pressable key={d.id} style={({ pressed }) => [s.tarjeta, pressed && { opacity: 0.7 }]} accessibilityRole="button" accessibilityLabel={`Abrir ${d.name}`}
                onPress={() => router.push({ pathname: '/disenos/[id]', params: { id: d.id } } as never)}>
                <View style={[s.miniatura, { aspectRatio: d.width / d.height }]}>
                  {d.thumbnail_url ? <Image source={{ uri: d.thumbnail_url }} style={{ flex: 1 }} contentFit="cover" /> : <Icon name="palette" size={36} color={C.tenue} />}
                </View>
                <Text style={s.nombre} numberOfLines={2}>{d.name}</Text>
                <Tenue style={{ fontSize: 12 }}>{d.width}×{d.height}{d.updated_at ? ` · ${new Date(d.updated_at).toLocaleDateString('es-AR')}` : ''}</Tenue>
              </Pressable>
            ))}
          </View>
        </Pantalla>
      )}
      <BotonFlotante icono="add" texto="Nuevo diseño" onPress={() => router.push('/disenos/nuevo' as never)} />
    </View>
  );
}

const s = StyleSheet.create({
  grilla: { flexDirection: 'row', flexWrap: 'wrap', gap: E.m },
  tarjeta: { width: '47%', gap: E.xs },
  miniatura: { width: '100%', maxHeight: 260, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' },
  nombre: { color: C.texto, fontWeight: '700' },
});
