import { router, Stack, useLocalSearchParams } from 'expo-router';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import type { Show } from '@/features/agenda/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';

export default function DetalleShow() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Show>('shows', id);
  const eliminar = useOperacion(() => api(`shows/${id}`, { method: 'DELETE' }), { exito: 'Show eliminado', invalidar: ['shows', 'calendario'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const s = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Show' }} />
      <Encabezado icono="theater-comedy" titulo={s.titulo}
        subtitulo={`${formatearFecha(s.fecha)}${s.hora_inicio ? ` · ${s.hora_inicio}${s.hora_fin ? `–${s.hora_fin}` : ''}` : ''}${s.lugar ? ` · ${s.lugar}` : ''}`}
        chips={s.convocatoria_abierta ? <Chip texto="Convocatoria abierta" color={C.exito} /> : undefined} />
      <Acciones>
        {s.acciones?.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/shows/[id]/editar', params: { id: String(s.id) } } as never)} />}
        {s.acciones?.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar "${s.titulo}"?`, mensaje: 'Se quita la convocatoria de los bloques. Queda registrado en auditoría.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>
      {s.descripcion && <Tarjeta><Texto>{s.descripcion}</Texto></Tarjeta>}
      <Subtitulo>Bloques convocados ({s.bloques.length})</Subtitulo>
      {s.bloques.length === 0 && <Tenue>Sin bloques convocados.</Tenue>}
      {s.bloques.map((b) => (
        <ItemLista key={b.id} icono="groups" titulo={b.nombre} subtitulo={[b.sede, b.profesor].filter(Boolean).join(' · ') || null}
          onPress={() => router.push({ pathname: '/bloques/[id]', params: { id: String(b.id) } } as never)} />
      ))}
    </Pantalla>
  );
}
