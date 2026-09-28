import { useInfiniteQuery } from '@tanstack/react-query';
import { type ReactElement, type ReactNode, useDeferredValue, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, qs } from '@/lib/api';
import { C, E, TOQUE } from '@/lib/theme';
import type { Paginado } from '@/lib/types';

import { Chip } from './form';
import { ErrorVista, Icon, type Icono, Tenue, Vacio } from './ui';

export type Filtros = Record<string, string | number | boolean | null | undefined>;

/**
 * Listado paginado del servidor con scroll infinito (Laravel: data + meta). La búsqueda
 * y los filtros viajan como query string; cada combinación se cachea por separado.
 */
export function useListado<T>(ruta: string, filtros: Filtros = {}, habilitado = true) {
  return useInfiniteQuery({
    queryKey: [ruta, 'lista', filtros],
    queryFn: ({ pageParam, signal }) => api<Paginado<T>>(`${ruta}${qs({ ...filtros, page: pageParam })}`, { signal }),
    initialPageParam: 1,
    getNextPageParam: (ultima) => (ultima.meta && ultima.meta.current_page < ultima.meta.last_page ? ultima.meta.current_page + 1 : undefined),
    enabled: habilitado,
  });
}

export function ListaPaginada<T>({ ruta, filtros, render, clave, vacio = 'No hay registros.', iconoVacio = 'inbox', cabecera, buscar, placeholderBusqueda = 'Buscar', onCrear, textoCrear = 'Nuevo' }: {
  ruta: string;
  filtros?: Filtros;
  render: (item: T) => ReactElement;
  clave?: (item: T) => string | number;
  vacio?: string;
  iconoVacio?: Icono;
  cabecera?: ReactNode;
  /** Habilita el buscador (envía `q`). */
  buscar?: boolean;
  placeholderBusqueda?: string;
  onCrear?: () => void;
  textoCrear?: string;
}) {
  const [texto, setTexto] = useState('');
  const q = useDeferredValue(texto.trim());
  const insets = useSafeAreaInsets();
  const lista = useListado<T>(ruta, { ...filtros, q: q.length >= 2 ? q : undefined });
  const items = lista.data?.pages.flatMap((p) => p.data) ?? [];
  const total = lista.data?.pages[0]?.meta?.total;

  return (
    <View style={{ flex: 1, backgroundColor: C.fondo }}>
      <FlatList
        data={items}
        keyExtractor={(it, i) => String(clave ? clave(it) : ((it as { id?: number }).id ?? i))}
        renderItem={({ item }) => render(item)}
        contentContainerStyle={{ padding: E.l, gap: E.s, paddingBottom: insets.bottom + 110 }}
        keyboardShouldPersistTaps="handled"
        onEndReachedThreshold={0.4}
        onEndReached={() => lista.hasNextPage && !lista.isFetchingNextPage && void lista.fetchNextPage()}
        refreshControl={<RefreshControl refreshing={lista.isRefetching && !lista.isFetchingNextPage} onRefresh={() => void lista.refetch()} tintColor={C.acento} />}
        ListHeaderComponent={
          <View style={{ gap: E.m, marginBottom: E.s }}>
            {buscar && <Buscador valor={texto} onChange={setTexto} placeholder={placeholderBusqueda} />}
            {cabecera}
            {total !== undefined && items.length > 0 && <Tenue style={{ fontSize: 13 }}>{total} resultado{total === 1 ? '' : 's'}</Tenue>}
          </View>
        }
        ListEmptyComponent={
          lista.isPending ? <Esqueleto /> : lista.isError ? <ErrorVista error={lista.error} onReintentar={() => void lista.refetch()} /> : <Vacio icono={iconoVacio} texto={q ? `Sin resultados para “${q}”.` : vacio} />
        }
        ListFooterComponent={lista.isFetchingNextPage ? <ActivityIndicator color={C.acento} style={{ margin: E.l }} /> : null}
      />
      {onCrear && <BotonFlotante icono="add" texto={textoCrear} onPress={onCrear} />}
    </View>
  );
}

export function Buscador({ valor, onChange, placeholder = 'Buscar' }: { valor: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <View style={s.buscador}>
      <Icon name="search" size={22} color={C.tenue} />
      <TextInput
        style={s.buscadorInput}
        value={valor}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={C.tenue}
        autoCorrect={false}
        returnKeyType="search"
        accessibilityLabel={placeholder}
      />
      {!!valor && <Pressable onPress={() => onChange('')} accessibilityRole="button" accessibilityLabel="Borrar búsqueda" hitSlop={10}><Icon name="close" size={20} color={C.tenue} /></Pressable>}
    </View>
  );
}

/** Fila de filtros rápidos con chips (desplazable en horizontal). */
export function FiltrosChips<V extends string | number>({ opciones, valor, onChange, todos = 'Todos' }: {
  opciones: { valor: V; etiqueta: string }[];
  valor: V | null;
  onChange: (v: V | null) => void;
  todos?: string | false;
}) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
      {todos !== false && <Chip etiqueta={todos} activo={valor === null} onPress={() => onChange(null)} />}
      {opciones.map((o) => <Chip key={String(o.valor)} etiqueta={o.etiqueta} activo={valor === o.valor} onPress={() => onChange(valor === o.valor && todos !== false ? null : o.valor)} />)}
    </ScrollView>
  );
}

/** Fila tocable de un listado. */
export function ItemLista({ titulo, subtitulo, detalle, derecha, icono, colorIcono = C.acento, acento, onPress }: {
  titulo: string;
  subtitulo?: string | null;
  detalle?: string | null;
  derecha?: ReactNode;
  icono?: Icono;
  colorIcono?: string;
  acento?: string;
  onPress?: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      accessibilityRole={onPress ? 'button' : undefined}
      accessibilityLabel={[titulo, subtitulo, detalle].filter(Boolean).join(', ')}
      style={({ pressed }) => [s.item, acento ? { borderLeftColor: acento, borderLeftWidth: 4 } : null, pressed && { opacity: 0.7 }]}>
      {icono && <Icon name={icono} size={24} color={colorIcono} />}
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={s.itemTitulo} numberOfLines={2}>{titulo}</Text>
        {!!subtitulo && <Tenue style={{ fontSize: 14 }}>{subtitulo}</Tenue>}
        {!!detalle && <Tenue style={{ fontSize: 13 }}>{detalle}</Tenue>}
      </View>
      {derecha}
      {onPress && !derecha && <Icon name="chevron-right" size={24} color={C.tenue} />}
    </Pressable>
  );
}

export function BotonFlotante({ icono, texto, onPress }: { icono: Icono; texto: string; onPress: () => void }) {
  const insets = useSafeAreaInsets();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={texto}
      style={({ pressed }) => [s.fab, { bottom: insets.bottom + E.l }, pressed && { opacity: 0.85 }]}>
      <Icon name={icono} size={26} color={C.texto} />
      <Text style={s.fabTexto}>{texto}</Text>
    </Pressable>
  );
}

/** Placeholder mientras carga la primera página. */
export function Esqueleto({ filas = 6 }: { filas?: number }) {
  return (
    <View style={{ gap: E.s }} accessibilityLabel="Cargando" accessibilityRole="progressbar">
      {Array.from({ length: filas }, (_, i) => (
        <View key={i} style={[s.item, { opacity: 1 - i * 0.12 }]}>
          <View style={{ flex: 1, gap: E.s }}>
            <View style={[s.hueso, { width: '60%' }]} />
            <View style={[s.hueso, { width: '35%', height: 10 }]} />
          </View>
        </View>
      ))}
    </View>
  );
}

const s = StyleSheet.create({
  buscador: { flexDirection: 'row', alignItems: 'center', gap: E.s, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, paddingHorizontal: E.m, minHeight: TOQUE + 4 },
  buscadorInput: { flex: 1, color: C.texto, fontSize: 17, minHeight: TOQUE },
  item: { flexDirection: 'row', alignItems: 'center', gap: E.m, backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, padding: E.m, minHeight: 64 },
  itemTitulo: { color: C.texto, fontSize: 16, fontWeight: '700' },
  fab: { position: 'absolute', right: E.l, flexDirection: 'row', alignItems: 'center', gap: E.s, backgroundColor: C.acento, borderRadius: 999, paddingHorizontal: E.xl, minHeight: 56, elevation: 6 },
  fabTexto: { color: C.texto, fontSize: 16, fontWeight: '800' },
  hueso: { height: 14, borderRadius: 7, backgroundColor: C.superficie2 },
});
