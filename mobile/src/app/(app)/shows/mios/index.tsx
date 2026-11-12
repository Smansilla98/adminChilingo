import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';

import { ItemLista } from '@/components/lista';
import { Cargando, Chip, ErrorVista, Pantalla, Tenue, Vacio } from '@/components/ui';
import { colorRespuesta, type MiShow, RESPUESTAS } from '@/features/shows/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';

/** Alumno: shows a los que lo convocaron, con su respuesta. */
export default function MisShows() {
  const q = useQuery({ queryKey: ['mi-shows'], queryFn: ({ signal }) => api<{ data: MiShow[] }>('mi/shows', { signal }).then((r) => r.data) });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Mis shows' }} />
      <Tenue>Los shows a los que te convocaron. Tocá uno para confirmar si vas.</Tenue>
      {q.data.length === 0 && <Vacio icono="theater-comedy" texto="No tenés shows por delante." />}
      {q.data.map((s) => (
        <ItemLista
          key={s.id}
          icono="theater-comedy"
          colorIcono={colorRespuesta(s.mi_respuesta)}
          titulo={s.titulo}
          subtitulo={`${formatearFecha(s.fecha)}${s.hora_inicio ? ` · ${s.hora_inicio}` : ''}${s.lugar ? ` · ${s.lugar}` : ''}`}
          derecha={<Chip texto={RESPUESTAS.find((r) => r.valor === s.mi_respuesta)?.etiqueta ?? 'Sin responder'} color={colorRespuesta(s.mi_respuesta)} />}
          onPress={() => router.push({ pathname: '/shows/mios/[id]', params: { id: String(s.id) } } as never)}
        />
      ))}
    </Pantalla>
  );
}
