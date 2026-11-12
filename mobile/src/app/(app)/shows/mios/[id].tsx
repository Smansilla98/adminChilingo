import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { Campo, Selector } from '@/components/form';
import { Boton, Cargando, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import { DetallePlan } from '@/features/shows/PlanShow';
import { type MiShowDetalle, type Respuesta, RESPUESTAS } from '@/features/shows/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useOperacion } from '@/lib/recursos';

/** Alumno: un show al que lo convocaron. Confirma si va y ve temas, ensayos y cómo llegar. */
export default function MiShowDetalleScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useQuery({ queryKey: ['mi-shows', id], queryFn: ({ signal }) => api<{ data: MiShowDetalle }>(`mi/shows/${id}`, { signal }).then((r) => r.data) });
  const [respuesta, setRespuesta] = useState<Respuesta | null>(null);
  const [comentario, setComentario] = useState<string | null>(null);
  const enviar = useOperacion(
    () => api(`mi/shows/${id}/confirmacion`, { method: 'POST', body: { respuesta: respuesta ?? q.data?.mi_respuesta, comentario: (comentario ?? q.data?.mi_comentario) || null } }),
    { exito: '¡Gracias! Guardamos tu respuesta', invalidar: ['mi-shows'], alTerminar: () => { setRespuesta(null); setComentario(null); } },
  );

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const s = q.data;
  const elegida = respuesta ?? s.mi_respuesta;
  const yaPaso = s.fecha < new Date().toISOString().slice(0, 10);

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Show' }} />
      <Encabezado icono="theater-comedy" titulo={s.titulo}
        subtitulo={`${formatearFecha(s.fecha)}${s.hora_inicio ? ` · ${s.hora_inicio}${s.hora_fin ? `–${s.hora_fin}` : ''}` : ''}${s.lugar ? ` · ${s.lugar}` : ''}`} />
      {s.descripcion && <Tarjeta><Texto>{s.descripcion}</Texto></Tarjeta>}
      <Subtitulo>¿Vas?</Subtitulo>
      {yaPaso ? (
        <Tenue>{s.asisti === true ? 'Fuiste a este show. ¡Gracias!' : 'El show ya pasó.'}</Tenue>
      ) : (
        <Tarjeta>
          <Selector opciones={RESPUESTAS.map((r) => ({ valor: r.valor, etiqueta: r.etiqueta }))} valor={elegida} onChange={(x) => setRespuesta(x)} />
          <Campo etiqueta="Comentario (opcional)" valor={comentario ?? s.mi_comentario ?? ''} onChange={setComentario} maxLength={255} />
          <Boton titulo={s.mi_respuesta ? 'Cambiar mi respuesta' : 'Enviar respuesta'} icono="send" deshabilitado={!elegida} cargando={enviar.isPending} onPress={() => enviar.mutate()} />
        </Tarjeta>
      )}
      <DetallePlan plan={s} />
    </Pantalla>
  );
}
