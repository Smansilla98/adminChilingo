import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { Cargando, Chip, ErrorVista, Icon, Pantalla, Tenue, Texto, Vacio } from '@/components/ui';
import { useBloquesAsistencia } from '@/lib/queries';
import { C, E } from '@/lib/theme';

export default function Bloques() {
  const q = useBloquesAsistencia();
  const hoy = new Date().getDay() || 7; // 1 = lunes … 7 = domingo (ISO)

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  const bloques = [...(q.data ?? [])].sort((a, b) => Number(tieneHoy(b, hoy)) - Number(tieneHoy(a, hoy)));

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      {bloques.length === 0 && <Vacio icono="groups" texto="No tenés bloques donde tomar asistencia." />}
      {bloques.map((b) => (
        <Pressable
          key={b.id}
          accessibilityRole="button"
          accessibilityLabel={`Tomar asistencia de ${b.nombre}`}
          style={({ pressed }) => [s.fila, pressed && { opacity: 0.7 }]}
          onPress={() => router.push({ pathname: '/asistencia/[bloque]', params: { bloque: String(b.id), nombre: b.nombre } } as never)}>
          <View style={{ flex: 1, gap: 2 }}>
            <Texto style={{ fontWeight: '800' }}>{b.nombre}</Texto>
            <Tenue>{[b.sede?.nombre, `${b.anio}° año`, b.cantidad_alumnos != null ? `${b.cantidad_alumnos} alumnos` : null].filter(Boolean).join(' · ')}</Tenue>
            {!!b.horarios?.length && <Tenue>{b.horarios.map((h) => `${h.dia_nombre.slice(0, 3)} ${h.inicio}`).join(' · ')}</Tenue>}
          </View>
          {tieneHoy(b, hoy) && <Chip texto="Hoy" color={C.acento} fondo={C.acentoSuave} />}
          <Icon name="chevron-right" size={26} color={C.tenue} />
        </Pressable>
      ))}
    </Pantalla>
  );
}

const tieneHoy = (b: { horarios?: { dia: number }[] }, hoy: number) => !!b.horarios?.some((h) => h.dia === hoy);

const s = StyleSheet.create({
  fila: { flexDirection: 'row', alignItems: 'center', gap: E.m, backgroundColor: C.superficie, borderRadius: 14, borderWidth: 1, borderColor: C.borde, padding: E.l, minHeight: 72 },
});
