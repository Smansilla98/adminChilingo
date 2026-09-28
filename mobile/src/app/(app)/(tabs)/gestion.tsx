import { router } from 'expo-router';
import { useDeferredValue, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Buscador } from '@/components/lista';
import { Cargando, ErrorVista, Icon, Pantalla, Subtitulo, Vacio } from '@/components/ui';
import { ORDEN_GRUPOS, RUTAS } from '@/lib/modulos';
import { useMe } from '@/lib/queries';
import { C, E } from '@/lib/theme';

/** Todos los módulos habilitados para la persona, agrupados. */
export default function Gestion() {
  const me = useMe();
  const [texto, setTexto] = useState('');
  const q = useDeferredValue(texto.trim().toLowerCase());

  if (me.isPending && !me.data) return <Cargando />;
  if (me.isError && !me.data) return <ErrorVista error={me.error} onReintentar={() => me.refetch()} />;

  const modulos = (me.data?.modulos ?? []).filter((m) => RUTAS[m.clave] && (!q || m.etiqueta.toLowerCase().includes(q)));

  return (
    <Pantalla refrescando={me.isRefetching} onRefrescar={() => me.refetch()}>
      <Buscador valor={texto} onChange={setTexto} placeholder="Buscar módulo" />
      {modulos.length === 0 && <Vacio icono="apps" texto={q ? 'Ningún módulo coincide.' : 'Tu cuenta no tiene módulos de gestión habilitados.'} />}
      {ORDEN_GRUPOS.map((grupo) => {
        const delGrupo = modulos.filter((m) => RUTAS[m.clave].grupo === grupo);
        if (!delGrupo.length) return null;
        return (
          <View key={grupo} style={{ gap: E.s }}>
            <Subtitulo>{grupo}</Subtitulo>
            <View style={s.grilla}>
              {delGrupo.map((m) => (
                <Pressable
                  key={m.clave}
                  onPress={() => router.push(RUTAS[m.clave].ruta as never)}
                  accessibilityRole="button"
                  accessibilityLabel={m.etiqueta}
                  style={({ pressed }) => [s.modulo, pressed && { opacity: 0.7 }]}>
                  <Icon name={RUTAS[m.clave].icono} size={28} color={C.acento} />
                  <Text style={s.texto} numberOfLines={2}>{m.etiqueta}</Text>
                </Pressable>
              ))}
            </View>
          </View>
        );
      })}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  grilla: { flexDirection: 'row', flexWrap: 'wrap', gap: E.m },
  modulo: { width: '47%', minHeight: 88, backgroundColor: C.superficie, borderRadius: 14, borderWidth: 1, borderColor: C.borde, padding: E.m, justifyContent: 'center', gap: E.xs },
  texto: { color: C.texto, fontWeight: '700', fontSize: 15 },
});
