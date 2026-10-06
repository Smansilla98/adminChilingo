import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import type { ComponentProps, ReactNode } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View, type TextProps, type ViewStyle } from 'react-native';
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

export function Texto({ children, style, ...props }: TextProps & { children: ReactNode }) {
  return <Text {...props} style={[s.texto, style]}>{children}</Text>;
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

/** Par etiqueta / valor dentro de una ficha. No se muestra si no hay valor. */
export function Dato({ etiqueta, valor, onPress, icono }: { etiqueta: string; valor: ReactNode; onPress?: () => void; icono?: Icono }) {
  if (valor === null || valor === undefined || valor === '') return null;
  const cuerpo = (
    <View style={s.dato}>
      <Text style={s.datoEtiqueta}>{etiqueta}</Text>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: E.xs }}>
        {typeof valor === 'string' || typeof valor === 'number' ? <Text style={[s.texto, { flex: 1 }, onPress && { color: C.acento }]} selectable>{valor}</Text> : <View style={{ flex: 1 }}>{valor}</View>}
        {icono && <MaterialIcons name={icono} size={20} color={onPress ? C.acento : C.tenue} />}
      </View>
    </View>
  );
  if (!onPress) return cuerpo;
  return <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={`${etiqueta}: ${String(valor)}`}>{cuerpo}</Pressable>;
}

/** Pestañas segmentadas para organizar una ficha (Información, Cuotas, Pagos…). */
export function Segmentos<V extends string>({ opciones, valor, onChange }: { opciones: { valor: V; etiqueta: string; cantidad?: number }[]; valor: V; onChange: (v: V) => void }) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }} accessibilityRole="tablist">
      {opciones.map((o) => {
        const activo = o.valor === valor;
        return (
          <Pressable
            key={o.valor}
            onPress={() => onChange(o.valor)}
            accessibilityRole="tab"
            accessibilityState={{ selected: activo }}
            style={[s.segmento, activo && { backgroundColor: C.acento, borderColor: C.acento }]}>
            <Text style={[s.segmentoTexto, activo && { color: C.texto }]}>{o.etiqueta}{o.cantidad !== undefined ? ` · ${o.cantidad}` : ''}</Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

/** Cabecera de una ficha: iniciales, nombre, estado y datos rápidos. */
export function Encabezado({ titulo, subtitulo, chips, icono }: { titulo: string; subtitulo?: string | null; chips?: ReactNode; icono?: Icono }) {
  const iniciales = titulo.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('');
  return (
    <View style={{ flexDirection: 'row', gap: E.l, alignItems: 'center' }}>
      <View style={s.avatar} accessibilityElementsHidden importantForAccessibility="no">
        {icono ? <MaterialIcons name={icono} size={30} color={C.acento} /> : <Text style={s.avatarTexto}>{iniciales || '?'}</Text>}
      </View>
      <View style={{ flex: 1, gap: E.xs }}>
        <Text style={s.titulo} accessibilityRole="header" numberOfLines={3}>{titulo}</Text>
        {!!subtitulo && <Text style={s.tenue}>{subtitulo}</Text>}
        {chips && <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.xs }}>{chips}</View>}
      </View>
    </View>
  );
}

/** Fila de acciones de una ficha (se reparten el ancho y bajan de línea si no entran). */
export function Acciones({ children }: { children: ReactNode }) {
  return <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.s }}>{Array.isArray(children) ? children.filter(Boolean).map((c, i) => <View key={i} style={{ flexGrow: 1, minWidth: '45%' }}>{c}</View>) : <View style={{ flexGrow: 1 }}>{children}</View>}</View>;
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
  dato: { gap: 2, paddingVertical: E.xs, minHeight: 40, justifyContent: 'center' },
  datoEtiqueta: { color: C.tenue, fontSize: 13, fontWeight: '600' },
  segmento: { borderRadius: 999, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, paddingHorizontal: E.l, minHeight: 40, justifyContent: 'center' },
  segmentoTexto: { color: C.tenue, fontWeight: '700' },
  avatar: { width: 64, height: 64, borderRadius: 32, backgroundColor: C.acentoSuave, alignItems: 'center', justifyContent: 'center' },
  avatarTexto: { color: C.acento, fontSize: 24, fontWeight: '800' },
});
