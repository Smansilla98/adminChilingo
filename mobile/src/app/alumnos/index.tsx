import { router } from 'expo-router';
import { useDeferredValue, useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';

import { Cargando, ErrorVista, Icon, Pantalla, Tenue, Texto, Vacio } from '@/components/ui';
import { useAlumnos } from '@/lib/queries';
import { C, E, TOQUE } from '@/lib/theme';

export default function Alumnos() {
  const [texto, setTexto] = useState('');
  const busqueda = useDeferredValue(texto.trim());
  const q = useAlumnos(busqueda.length >= 2 ? busqueda : '');

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <TextInput
        style={s.buscar}
        placeholder="Buscar por nombre o DNI"
        placeholderTextColor={C.tenue}
        value={texto}
        onChangeText={setTexto}
        autoCorrect={false}
        accessibilityLabel="Buscar alumno"
        clearButtonMode="while-editing"
      />
      {q.isPending && !q.data && <Cargando />}
      {q.isError && !q.data && <ErrorVista error={q.error} onReintentar={() => q.refetch()} />}
      {q.data?.data.length === 0 && <Vacio icono="person-search" texto="Sin resultados en tus bloques y sedes." />}
      {q.data?.data.map((a) => (
        <Pressable key={a.id} style={({ pressed }) => [s.fila, pressed && { opacity: 0.7 }]} accessibilityRole="button" onPress={() => router.push({ pathname: '/alumnos/[id]', params: { id: String(a.id) } } as never)}>
          <View style={{ flex: 1 }}>
            <Texto style={{ fontWeight: '700' }}>{a.nombre}</Texto>
            <Tenue>{[a.sede?.nombre, a.bloques?.map((b) => b.nombre).join(', ')].filter(Boolean).join(' · ')}</Tenue>
          </View>
          <Icon name="chevron-right" size={24} color={C.tenue} />
        </Pressable>
      ))}
      {!!q.data?.meta && q.data.meta.total > q.data.data.length && <Tenue>Mostrando {q.data.data.length} de {q.data.meta.total}. Afiná la búsqueda.</Tenue>}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  buscar: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 17, paddingHorizontal: E.l, minHeight: TOQUE + 4 },
  fila: { flexDirection: 'row', alignItems: 'center', gap: E.m, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, padding: E.m, minHeight: 60 },
});
