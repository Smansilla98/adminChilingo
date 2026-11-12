import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useDeferredValue, useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';

import { BotonFlotante, Buscador, Esqueleto, FiltrosChips, ItemLista } from '@/components/lista';
import { Chip, ErrorVista, Pantalla, Tenue, Vacio } from '@/components/ui';
import { type CatalogoGira, COLOR_PAGO, type Inscripto } from '@/features/villa-gesell/tipos';
import { api, qs } from '@/lib/api';
import { useCatalogo } from '@/lib/recursos';
import { C, E, moneda } from '@/lib/theme';

export default function InscriptosGira() {
  const [estado, setEstado] = useState<string | null>(null);
  const [lista, setLista] = useState<string | null>(null);
  const [texto, setTexto] = useState('');
  const buscar = useDeferredValue(texto.trim());
  const cat = useCatalogo<CatalogoGira>('villa-gesell/catalogo', 1);
  const q = useQuery({
    queryKey: ['villa-gesell', 'inscriptos', estado, lista, buscar],
    queryFn: () => api<{ data: Inscripto[] }>(`villa-gesell/inscriptos${qs({ estado, lista, q: buscar.length >= 2 ? buscar : undefined })}`).then((r) => r.data),
  });
  const datos = q.data ?? [];
  const total = datos.reduce((s, i) => s + i.saldo, 0);

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: 'Inscriptos a la gira' }} />
      <Pantalla scroll={false}>
        <FlatList
          style={{ flex: 1 }}
          data={datos}
          keyExtractor={(i) => String(i.id)}
          contentContainerStyle={{ padding: E.l, gap: E.s, paddingBottom: E.xxl * 3 }}
          keyboardShouldPersistTaps="handled"
          refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} tintColor={C.acento} />}
          ListHeaderComponent={
            <View style={{ gap: E.m, marginBottom: E.s }}>
              <Buscador valor={texto} onChange={setTexto} placeholder="Buscar alumno" />
              <FiltrosChips opciones={Object.entries(cat.data?.estados_pago ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={estado} onChange={setEstado} todos="Todo pago" />
              <FiltrosChips opciones={[{ valor: 'plaza', etiqueta: 'Con plaza' }, { valor: 'espera', etiqueta: 'Lista de espera' }]} valor={lista} onChange={setLista} />
              {!!datos.length && <Tenue>{datos.length} inscriptos · saldo pendiente {moneda(total)}</Tenue>}
              {q.isError && datos.length > 0 && <Tenue>No se pudo actualizar la lista. Mostramos lo último guardado.</Tenue>}
            </View>
          }
          ListEmptyComponent={
            q.isPending ? <Esqueleto /> : q.isError ? <ErrorVista error={q.error} onReintentar={() => q.refetch()} /> : <Vacio icono="beach-access" texto="Sin inscriptos con esos filtros." />
          }
          renderItem={({ item: i }) => (
            <ItemLista
              icono={i.lista_espera ? 'hourglass-empty' : 'event-seat'}
              colorIcono={i.lista_espera ? C.alerta : C.acento}
              titulo={`${i.plaza ? `#${i.plaza} · ` : ''}${i.alumno?.nombre ?? 'Alumno'}`}
              subtitulo={`${moneda(i.monto_pagado)} de ${moneda(i.monto_esperado)}${i.saldo > 0 ? ` · debe ${moneda(i.saldo)}` : ''}`}
              detalle={[i.alumno?.sede, i.talle_remera ? `remera ${i.talle_remera}` : null, i.tambor_principal].filter(Boolean).join(' · ') || null}
              derecha={<Chip texto={i.lista_espera ? 'En espera' : i.estado_pago_nombre} color={i.lista_espera ? C.alerta : COLOR_PAGO[i.estado_pago] ?? C.tenue} />}
              onPress={() => router.push({ pathname: '/villa-gesell/inscripcion', params: { id: String(i.id) } } as never)}
            />
          )}
        />
      </Pantalla>
      <BotonFlotante icono="person-add" texto="Inscribir" onPress={() => router.push('/villa-gesell/inscripcion' as never)} />
    </View>
  );
}
