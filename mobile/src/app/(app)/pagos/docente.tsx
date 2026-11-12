import { useInfiniteQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, View } from 'react-native';

import { SelectorLista } from '@/components/form';
import { Esqueleto, ItemLista } from '@/components/lista';
import { Boton, Chip, ErrorVista, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { C, E, moneda } from '@/lib/theme';

interface Respuesta {
  data: { id: number; pago_id: number; fecha: string; alumno: string; cuota: string; monto: number; abono_profesor: number | null; anulado: boolean }[];
  meta: { current_page: number; last_page: number; total: number };
  total_abono: number;
  alumnos: { id: number; nombre: string }[];
}

/** Docente: pagos de los alumnos de sus bloques y el abono que le corresponde. */
export default function PagosDocente() {
  const [alumno, setAlumno] = useState<number | null>(null);
  const q = useInfiniteQuery({
    queryKey: ['mi', 'pagos-docente', alumno],
    queryFn: ({ pageParam }) => api<Respuesta>(`mi/pagos-docente${qs({ alumno_id: alumno, page: pageParam })}`),
    initialPageParam: 1,
    getNextPageParam: (u) => (u.meta.current_page < u.meta.last_page ? u.meta.current_page + 1 : undefined),
  });
  const primera = q.data?.pages[0];
  const items = q.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <>
      <Stack.Screen options={{ title: 'Pagos de mis alumnos' }} />
      <FlatList
        style={{ flex: 1, backgroundColor: C.fondo }}
        data={items}
        keyExtractor={(d) => String(d.id)}
        contentContainerStyle={{ padding: E.l, gap: E.s, paddingBottom: E.xxl * 2 }}
        keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={q.isRefetching && !q.isFetchingNextPage} onRefresh={() => void q.refetch()} tintColor={C.acento} />}
        ListHeaderComponent={
          <View style={{ gap: E.m, marginBottom: E.s }}>
            {primera && (
              <Tarjeta acento={C.exito}>
                <Tenue>Total de abonos docentes (pagos vigentes)</Tenue>
                <Texto style={{ fontSize: 26, fontWeight: '800' }}>{moneda(primera.total_abono)}</Texto>
              </Tarjeta>
            )}
            <SelectorLista<number> etiqueta="Alumno" valor={alumno} onChange={setAlumno} permitirVacio placeholder="Todos mis alumnos"
              opciones={(primera?.alumnos ?? []).map((a) => ({ valor: a.id, etiqueta: a.nombre }))} />
            {q.isError && items.length > 0 && <Tenue>No se pudo actualizar la lista. Mostramos lo último guardado.</Tenue>}
          </View>
        }
        ListEmptyComponent={
          q.isPending ? <Esqueleto /> : q.isError ? <ErrorVista error={q.error} onReintentar={() => q.refetch()} /> : <Vacio icono="payments" texto="No hay pagos registrados de tus alumnos." />
        }
        renderItem={({ item: d }) => (
          <ItemLista icono="payments" colorIcono={d.anulado ? C.peligro : C.exito} titulo={`${d.alumno} · ${moneda(d.monto)}`}
            subtitulo={`${d.fecha} · ${d.cuota}`} detalle={d.abono_profesor != null ? `Tu abono: ${moneda(d.abono_profesor)}` : null}
            derecha={d.anulado ? <Chip texto="Anulado" color={C.peligro} /> : undefined} />
        )}
        ListFooterComponent={
          <View style={{ gap: E.s }}>
            {q.isFetchingNextPage && <ActivityIndicator color={C.acento} />}
            {q.hasNextPage && !q.isFetchingNextPage && <Boton titulo="Ver más" variante="secundario" onPress={() => void q.fetchNextPage()} />}
          </View>
        }
      />
    </>
  );
}
