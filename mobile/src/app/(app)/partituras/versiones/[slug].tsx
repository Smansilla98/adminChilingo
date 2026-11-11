import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams, router } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, View } from 'react-native';

import { Aviso, Boton, Cargando, ErrorVista, Pantalla, Tenue, Texto, Titulo } from '@/components/ui';
import { api } from '@/lib/api';
import type { Score } from '@/lib/partitura/modelo';
import { usePuede } from '@/lib/permisos';
import { C, E } from '@/lib/theme';

interface Resumen {
  partes: number;
  compases: number;
  golpes: number;
  instrumentos: number;
}

interface VersionLista {
  numero: number;
  autor: string | null;
  nota: string | null;
  fecha: string | null;
  resumen: Resumen;
}

interface VersionDetalle extends VersionLista {
  score: Score | null;
}

/** Versiones publicadas de un toque: ver cuándo se cambió y quién, y restaurar una (solo admin). */
export default function Versiones() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const admin = usePuede('partituras.admin');
  const qc = useQueryClient();
  const [aviso, setAviso] = useState<string | null>(null);
  const [ocupada, setOcupada] = useState<number | null>(null);

  const q = useQuery({
    queryKey: ['partitura-versiones', slug],
    queryFn: () => api<{ data: VersionLista[] }>(`partituras/${slug}/versiones`),
  });

  const restaurar = (numero: number) => {
    Alert.alert('Restaurar versión', `Se reemplaza la partitura publicada por la versión ${numero}. La versión actual queda guardada en el historial.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Restaurar',
        onPress: () => {
          setOcupada(numero);
          setAviso(null);
          void api<{ data: VersionDetalle }>(`partituras/${slug}/versiones/${numero}`)
            .then((v) => {
              if (!v.data.score) throw new Error('La versión no tiene partitura.');
              return api(`partituras/${slug}/score`, { method: 'PUT', body: { score: v.data.score, nota: `Restaurada desde la versión ${numero}` } });
            })
            .then(() => {
              setAviso(`Versión ${numero} restaurada.`);
              return Promise.all([
                qc.invalidateQueries({ queryKey: ['partitura-versiones', slug] }),
                qc.invalidateQueries({ queryKey: ['partitura', slug] }),
              ]);
            })
            .catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo restaurar.'))
            .finally(() => setOcupada(null));
        },
      },
    ]);
  };

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const versiones = q.data?.data ?? [];

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Versiones' }} />
      <Titulo>Versiones</Titulo>
      <Tenue>Cada cambio publicado queda guardado. Podés volver a una versión anterior.</Tenue>
      {aviso && <Aviso texto={aviso} />}
      {versiones.length === 0 && <Tenue>Todavía no hay versiones publicadas.</Tenue>}
      {versiones.map((v) => (
        <View key={v.numero} style={s.tarjeta}>
          <Texto>Versión {v.numero}{v.autor ? ` · ${v.autor}` : ''}</Texto>
          <Tenue>
            {v.fecha ? new Date(v.fecha).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : 'Sin fecha'}
            {` · ${v.resumen.partes} partes · ${v.resumen.compases} compases · ${v.resumen.golpes} golpes`}
          </Tenue>
          {v.nota ? <Tenue>{v.nota}</Tenue> : null}
          {admin && (
            <Boton titulo={ocupada === v.numero ? 'Restaurando…' : 'Restaurar esta versión'} icono="history" variante="secundario" cargando={ocupada === v.numero} deshabilitado={ocupada !== null} onPress={() => restaurar(v.numero)} />
          )}
        </View>
      ))}
      <Boton titulo="Volver al toque" variante="secundario" onPress={() => router.back()} />
    </Pantalla>
  );
}

const s = StyleSheet.create({
  tarjeta: { gap: 4, padding: E.s, borderRadius: 10, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie },
});
