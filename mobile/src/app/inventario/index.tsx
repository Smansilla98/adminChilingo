import { router } from 'expo-router';
import { useDeferredValue, useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Tenue, Texto, Vacio } from '@/components/ui';
import { COLOR_ESTADO_ITEM } from '@/lib/formato';
import { useInventario, useMe } from '@/lib/queries';
import { C, E, TOQUE } from '@/lib/theme';

export default function Inventario() {
  const [texto, setTexto] = useState('');
  const busqueda = useDeferredValue(texto.trim());
  const q = useInventario(busqueda);
  const me = useMe();
  const puedeCargar = me.data?.permisos.includes('inventario.create') ?? false;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Fila>
        <View style={{ flex: 1 }}><Boton titulo="Escanear QR" icono="qr-code-scanner" onPress={() => router.push('/inventario/escanear' as never)} grande /></View>
        {puedeCargar && <View style={{ flex: 1 }}><Boton titulo="Cargar" icono="add" variante="secundario" onPress={() => router.push('/inventario/nuevo' as never)} grande /></View>}
      </Fila>
      <TextInput style={s.buscar} placeholder="Buscar por nombre, código o marca" placeholderTextColor={C.tenue} value={texto} onChangeText={setTexto} accessibilityLabel="Buscar en inventario" />
      {q.isPending && !q.data && <Cargando />}
      {q.isError && !q.data && <ErrorVista error={q.error} onReintentar={() => q.refetch()} />}
      {q.data?.data.length === 0 && <Vacio icono="inventory-2" texto="No hay ítems en tu alcance." />}
      {q.data?.data.map((i) => (
        <Pressable key={i.id} style={({ pressed }) => [s.fila, pressed && { opacity: 0.7 }]} accessibilityRole="button" onPress={() => router.push({ pathname: '/inventario/[id]', params: { id: String(i.id) } } as never)}>
          <View style={{ flex: 1 }}>
            <Texto style={{ fontWeight: '700' }}>{i.nombre}</Texto>
            <Tenue>{[i.codigo, i.tipo_nombre, i.sede?.nombre].filter(Boolean).join(' · ')}</Tenue>
          </View>
          <Chip texto={i.estado_nombre} color={COLOR_ESTADO_ITEM[i.estado] ?? C.tenue} />
        </Pressable>
      ))}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  buscar: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 17, paddingHorizontal: E.l, minHeight: TOQUE + 4 },
  fila: { flexDirection: 'row', alignItems: 'center', gap: E.m, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, padding: E.m, minHeight: 60 },
});
