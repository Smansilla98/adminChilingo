import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import { Campo } from '@/components/form';
import { FiltrosChips } from '@/components/lista';
import { Cargando, ErrorVista, Pantalla, Subtitulo, Tenue } from '@/components/ui';
import { Lienzo, leerCanvas } from '@/features/disenos/Lienzo';
import { type Diseno, FORMATOS, type Plantilla } from '@/features/disenos/tipos';
import { api } from '@/lib/api';
import { useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

/** Crear un diseño desde una plantilla (de La Chilinga, generales o de estudio) o en blanco. */
export default function NuevoDiseno() {
  const q = useQuery({ queryKey: ['disenos', 'plantillas'], queryFn: () => api<Plantilla[]>('disenos/plantillas'), staleTime: 3600_000 });
  const [categoria, setCategoria] = useState<string | null>(null);
  const [nombre, setNombre] = useState('');
  const { width } = useWindowDimensions();
  const crear = useOperacion(
    (p: { canvas_json: string; width: number; height: number; name: string }) => api<Diseno>('disenos', { method: 'POST', body: p }),
    { exito: 'Diseño creado', invalidar: ['disenos'], alTerminar: (d) => router.replace({ pathname: '/disenos/[id]', params: { id: d.id } } as never) },
  );
  const anchoTarjeta = (Math.min(width, 700) - E.l * 2 - E.m) / 2;

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const categorias = [...new Set(q.data.map((p) => p.category))];
  const plantillas = q.data.filter((p) => !categoria || p.category === categoria);

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Nuevo diseño' }} />
      <Campo etiqueta="Nombre" valor={nombre} onChange={setNombre} placeholder="Diseño sin título" />
      <Subtitulo>En blanco</Subtitulo>
      <View style={s.grilla}>
        {FORMATOS.map((f) => (
          <Pressable key={f.nombre} style={({ pressed }) => [s.formato, pressed && { opacity: 0.7 }]} accessibilityRole="button" accessibilityLabel={`${f.nombre} en blanco`}
            disabled={crear.isPending} onPress={() => crear.mutate({ canvas_json: '{}', width: f.ancho, height: f.alto, name: nombre || f.nombre })}>
            <Text style={s.texto}>{f.nombre}</Text>
            <Tenue style={{ fontSize: 12 }}>{f.ancho}×{f.alto}</Tenue>
          </Pressable>
        ))}
      </View>
      <Subtitulo>Plantillas</Subtitulo>
      <FiltrosChips opciones={categorias.map((c) => ({ valor: c, etiqueta: c }))} valor={categoria} onChange={setCategoria} todos="Todas" />
      <View style={s.grilla}>
        {plantillas.map((p) => (
          <Pressable key={p.id} style={({ pressed }) => [{ width: anchoTarjeta, gap: E.xs }, pressed && { opacity: 0.7 }]} accessibilityRole="button" accessibilityLabel={`Usar plantilla ${p.name}`}
            disabled={crear.isPending} onPress={() => crear.mutate({ canvas_json: p.canvas_json, width: p.width, height: p.height, name: nombre || p.name })}>
            <Lienzo canvas={leerCanvas(p.canvas_json)} ancho={p.width} alto={p.height} anchoVista={anchoTarjeta} />
            <Text style={s.texto} numberOfLines={2}>{p.name}</Text>
          </Pressable>
        ))}
      </View>
      {crear.isPending && <Tenue>Creando…</Tenue>}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  grilla: { flexDirection: 'row', flexWrap: 'wrap', gap: E.m },
  formato: { width: '47%', minHeight: 72, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, padding: E.m, justifyContent: 'center' },
  texto: { color: C.texto, fontWeight: '700' },
});
