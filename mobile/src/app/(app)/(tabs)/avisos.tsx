import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ActivityIndicator, FlatList, RefreshControl, View } from 'react-native';

import { useToast } from '@/components/feedback';
import { Esqueleto } from '@/components/lista';
import { Aviso as AvisoTexto, Boton, ErrorVista, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { ActivarPush } from '@/features/avisos/ActivarPush';
import { api } from '@/lib/api';
import { destinoDe } from '@/lib/destinos';
import { C, E } from '@/lib/theme';
import type { Aviso } from '@/lib/types';

interface Pagina {
  no_leidas: number;
  data: Aviso[];
  meta?: { current_page: number; last_page: number; total: number };
}

/** Bandeja de avisos paginada (el servidor devuelve de a 30). La clave empieza con 'avisos' para que la invaliden las push. */
export default function Avisos() {
  const qc = useQueryClient();
  const avisar = useToast();
  const q = useInfiniteQuery({
    queryKey: ['avisos', 'lista'],
    queryFn: ({ pageParam, signal }) => api<Pagina>(`notificaciones?page=${pageParam}`, { signal }),
    initialPageParam: 1,
    getNextPageParam: (u) => (u.meta && u.meta.current_page < u.meta.last_page ? u.meta.current_page + 1 : undefined),
  });
  const leer = useMutation({
    mutationFn: (id: string | null) => api(id ? `notificaciones/${id}/leer` : 'notificaciones/leer-todas', { method: 'POST' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['avisos'] }),
    onError: (e) => avisar(e instanceof Error ? `No se pudo marcar como leído. ${e.message}` : 'No se pudo marcar como leído.', 'error'),
  });

  const items = q.data?.pages.flatMap((p) => p.data) ?? [];
  const noLeidas = q.data?.pages[0]?.no_leidas ?? 0;

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: C.fondo }}
      data={items}
      keyExtractor={(a) => a.id}
      contentContainerStyle={{ padding: E.l, gap: E.m, paddingBottom: E.xxl }}
      onEndReachedThreshold={0.4}
      onEndReached={() => q.hasNextPage && !q.isFetchingNextPage && void q.fetchNextPage()}
      refreshControl={<RefreshControl refreshing={q.isRefetching && !q.isFetchingNextPage} onRefresh={() => void q.refetch()} tintColor={C.acento} />}
      ListHeaderComponent={
        <View style={{ gap: E.m }}>
          <ActivarPush />
          {q.isRefetchError && items.length > 0 && <AvisoTexto tono="alerta" texto="No se pudieron actualizar los avisos. Mostramos los últimos guardados." />}
          {noLeidas > 0 && <Boton titulo="Marcar todo como leído" variante="secundario" icono="done-all" cargando={leer.isPending && leer.variables === null} onPress={() => leer.mutate(null)} />}
        </View>
      }
      renderItem={({ item: a }) => (
        <Tarjeta
          acento={a.leida ? undefined : C.acento}
          onPress={() => {
            if (!a.leida) leer.mutate(a.id);
            const destino = destinoDe(a.enlace);
            if (destino) router.push(destino as never);
          }}>
          <Texto style={{ fontWeight: a.leida ? '400' : '800' }}>{a.titulo}</Texto>
          <Tenue>{a.mensaje}</Tenue>
          <Tenue style={{ fontSize: 12 }}>{new Date(a.fecha).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' })}</Tenue>
        </Tarjeta>
      )}
      ListEmptyComponent={
        q.isPending ? <Esqueleto /> : q.isError ? <ErrorVista error={q.error} onReintentar={() => void q.refetch()} /> : <Vacio icono="notifications-none" texto="No tenés avisos." />
      }
      ListFooterComponent={q.isFetchingNextPage ? <ActivityIndicator color={C.acento} style={{ margin: E.l }} /> : null}
    />
  );
}
