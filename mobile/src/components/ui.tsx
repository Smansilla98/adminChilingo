import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import type { ComponentProps, ReactNode } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View, type ViewStyle } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { C, E, TOQUE } from '@/lib/theme';

export type Icono = ComponentProps<typeof MaterialIcons>['name'];

export function Pantalla({ children, refrescando, onRefrescar, scroll = true }: {
  children: ReactNode;
  refrescando?: boolean;
  onRefrescar?: () => void;
  scroll?: boolean;
}) {
  if (!scroll) return <SafeAreaView style={s.pantalla} edges={['bottom', 'left', 'right']}>{children}</SafeAreaView>;
  return (
    <SafeAreaView style={s.pantalla} edges={['bottom', 'left', 'right']}>
      <ScrollView
        contentContainerStyle={s.contenido}
        refreshControl={onRefrescar ? <RefreshControl refreshing={!!refrescando} onRefresh={onRefrescar} tintColor={C.acento} /> : undefined}
        keyboardShouldPersistTaps="handled">
        {children}
      </ScrollView>
    </SafeAreaView>
  );
}

export function Titulo({ children }: { children: ReactNode }) {
  return <Text style={s.titulo} accessibilityRole="header">{children}</Text>;
}

export function Subtitulo({ children }: { children: ReactNode }) {
  return <Text style={s.subtitulo} accessibilityRole="header">{children}</Text>;
}

export function Tenue({ children, style }: { children: ReactNode; style?: object }) {
  return <Text style={[s.tenue, style]}>{children}</Text>;
}

export function Texto({ children, style }: { children: ReactNode; style?: object }) {
  return <Text style={[s.texto, style]}>{children}</Text>;
}

export function Tarjeta({ children, onPress, style, acento }: { children: ReactNode; onPress?: () => void; style?: ViewStyle; acento?: string }) {
  const contenido = <View style={[s.tarjeta, acento ? { borderLeftColor: acento, borderLeftWidth: 4 } : null, style]}>{children}</View>;
  if (!onPress) return contenido;
  return (
    <Pressable onPress={onPress} accessibilityRole="button" style={({ pressed }) => (pressed ? { opacity: 0.7 } : null)}>
      {contenido}
    </Pressable>
  );
}

export function Boton({ titulo, onPress, icono, variante = 'primario', cargando, deshabilitado, grande }: {
  titulo: string;
  onPress: () => void;
  icono?: Icono;
  variante?: 'primario' | 'secundario' | 'peligro';
  cargando?: boolean;
  deshabilitado?: boolean;
  grande?: boolean;
}) {
  const fondo = variante === 'primario' ? C.acento : variante === 'peligro' ? C.peligro : C.superficie2;
  return (
    <Pressable
      onPress={onPress}
      disabled={deshabilitado || cargando}
      accessibilityRole="button"
      accessibilityLabel={titulo}
      accessibilityState={{ disabled: !!(deshabilitado || cargando), busy: !!cargando }}
      style={({ pressed }) => [s.boton, { backgroundColor: fondo, minHeight: grande ? 60 : TOQUE, opacity: deshabilitado ? 0.4 : pressed ? 0.8 : 1 }]}>
      {cargando ? <ActivityIndicator color={C.texto} /> : (
        <>
          {icono && <MaterialIcons name={icono} size={grande ? 26 : 20} color={C.texto} />}
          <Text style={[s.botonTexto, grande && { fontSize: 18 }]}>{titulo}</Text>
        </>
      )}
    </Pressable>
  );
}

export function Fila({ children, style }: { children: ReactNode; style?: ViewStyle }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center', gap: E.s }, style]}>{children}</View>;
}

export function Chip({ texto, color = C.tenue, fondo = C.superficie2 }: { texto: string; color?: string; fondo?: string }) {
  return (
    <View style={[s.chip, { backgroundColor: fondo }]}>
      <Text style={[s.chipTexto, { color }]}>{texto}</Text>
    </View>
  );
}

export function Cargando({ texto = 'Cargando…' }: { texto?: string }) {
  return (
    <View style={s.centro} accessibilityLiveRegion="polite">
      <ActivityIndicator color={C.acento} size="large" />
      <Tenue>{texto}</Tenue>
    </View>
  );
}

export function Vacio({ icono = 'inbox', texto }: { icono?: Icono; texto: string }) {
  return (
    <View style={s.centro}>
      <MaterialIcons name={icono} size={40} color={C.tenue} />
      <Tenue style={{ textAlign: 'center' }}>{texto}</Tenue>
    </View>
  );
}

export function ErrorVista({ error, onReintentar }: { error: unknown; onReintentar?: () => void }) {
  const msg = error instanceof Error ? error.message : 'Algo salió mal.';
  return (
    <View style={s.centro} accessibilityLiveRegion="assertive">
      <MaterialIcons name="error-outline" size={40} color={C.peligro} />
      <Texto style={{ textAlign: 'center' }}>{msg}</Texto>
      {onReintentar && <Boton titulo="Reintentar" variante="secundario" onPress={onReintentar} />}
    </View>
  );
}

export function Aviso({ texto, tono = 'info' }: { texto: string; tono?: 'info' | 'alerta' | 'exito' | 'peligro' }) {
  const color = { info: C.info, alerta: C.alerta, exito: C.exito, peligro: C.peligro }[tono];
  return (
    <View style={[s.aviso, { borderColor: color }]} accessibilityLiveRegion="polite">
      <Text style={[s.texto, { color }]}>{texto}</Text>
    </View>
  );
}

export function Icon(props: ComponentProps<typeof MaterialIcons>) {
  return <MaterialIcons {...props} />;
}

const s = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.fondo },
  contenido: { padding: E.l, gap: E.m, paddingBottom: E.xxl * 2 },
  titulo: { color: C.texto, fontSize: 26, fontWeight: '700' },
  subtitulo: { color: C.texto, fontSize: 18, fontWeight: '700', marginTop: E.s },
  tenue: { color: C.tenue, fontSize: 14 },
  texto: { color: C.texto, fontSize: 16 },
  tarjeta: { backgroundColor: C.superficie, borderRadius: 14, padding: E.l, gap: E.s, borderWidth: 1, borderColor: C.borde },
  boton: { borderRadius: 12, paddingHorizontal: E.l, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: E.s },
  botonTexto: { color: C.texto, fontSize: 16, fontWeight: '700' },
  chip: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4, alignSelf: 'flex-start' },
  chipTexto: { fontSize: 12, fontWeight: '700' },
  centro: { alignItems: 'center', justifyContent: 'center', padding: E.xxl, gap: E.m, flexGrow: 1 },
  aviso: { borderWidth: 1, borderRadius: 12, padding: E.m },
});
