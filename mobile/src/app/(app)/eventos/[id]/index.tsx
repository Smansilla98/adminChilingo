import { router, Stack, useLocalSearchParams } from 'expo-router';

import { confirmar } from '@/components/feedback';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Tarjeta, Texto } from '@/components/ui';
import type { Evento } from '@/features/agenda/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';

const AMBITO = { bloque: 'Bloque', sede: 'Sede', escuela: 'Toda la escuela' } as const;

export default function DetalleEvento() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Evento>('eventos', id);
  const eliminar = useOperacion(() => api(`eventos/${id}`, { method: 'DELETE' }), { exito: 'Evento eliminado', invalidar: ['eventos', 'calendario'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const e = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Evento' }} />
      <Encabezado icono="celebration" titulo={e.titulo} subtitulo={`${formatearFecha(e.fecha)}${e.hora_inicio ? ` · ${e.hora_inicio}${e.hora_fin ? `–${e.hora_fin}` : ''}` : ''}`}
        chips={<><Chip texto={e.tipo_nombre} color={C.acento} /><Chip texto={AMBITO[e.ambito]} /></>} />
      <Acciones>
        {e.acciones?.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/eventos/[id]/editar', params: { id: String(e.id) } } as never)} />}
        {e.acciones?.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar "${e.titulo}"?`, mensaje: 'Deja de verse en la agenda de todos. Queda registrado en auditoría.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>
      <Tarjeta>
        <Dato etiqueta="Sede" valor={e.sede?.nombre ?? 'Toda la escuela'} onPress={e.sede ? () => router.push({ pathname: '/sedes/[id]', params: { id: String(e.sede!.id) } } as never) : undefined} icono={e.sede ? 'chevron-right' : undefined} />
        <Dato etiqueta="Bloque" valor={e.bloque?.nombre} />
        <Dato etiqueta="Docente a cargo" valor={e.profesor?.nombre} />
        <Dato etiqueta="Personas" valor={e.cantidad_personas != null ? String(e.cantidad_personas) : null} />
        <Dato etiqueta="Creado por" valor={e.creado_por} />
      </Tarjeta>
      {e.descripcion && <Tarjeta><Texto>{e.descripcion}</Texto></Tarjeta>}
    </Pantalla>
  );
}
