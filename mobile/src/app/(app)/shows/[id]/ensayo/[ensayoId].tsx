import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Encabezado, ErrorVista, Pantalla, Subtitulo, Tenue } from '@/components/ui';
import type { Ensayo } from '@/features/shows/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';

/** Ensayo de un show: datos y asistencia propia (presente / ausente por alumno convocado). */
export default function DetalleEnsayo() {
  const { ensayoId } = useLocalSearchParams<{ id: string; ensayoId: string }>();
  const q = useDetalle<Ensayo>('show-ensayos', ensayoId);
  const [marcas, setMarcas] = useState<Record<number, boolean>>({});
  const guardar = useOperacion(() => api(`show-ensayos/${ensayoId}/asistencia`, { method: 'PUT', body: { marcas } }), {
    exito: 'Asistencia guardada', invalidar: ['show-ensayos', 'shows'], alTerminar: () => setMarcas({}),
  });
  const eliminar = useOperacion(() => api(`show-ensayos/${ensayoId}`, { method: 'DELETE' }), { exito: 'Ensayo eliminado', invalidar: ['shows'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const e = q.data;
  const presente = (a: Ensayo['asistencia'][number]) => (a.alumno_id in marcas ? marcas[a.alumno_id] : a.presente);
  const cambios = Object.keys(marcas).length;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Ensayo' }} />
      <Encabezado icono="event" titulo={`Ensayo · ${e.show}`}
        subtitulo={`${formatearFecha(e.fecha)}${e.hora_inicio ? ` · ${e.hora_inicio}${e.hora_fin ? `–${e.hora_fin}` : ''}` : ''}${e.lugar ? ` · ${e.lugar}` : ''}`} />
      {e.acciones.editar && (
        <Acciones>
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: '¿Eliminar este ensayo?', mensaje: 'Se pierde su asistencia.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        </Acciones>
      )}
      {e.notas && <Tenue>{e.notas}</Tenue>}
      <Subtitulo>Asistencia ({e.asistencia.filter((a) => presente(a)).length} de {e.asistencia.length})</Subtitulo>
      {e.asistencia.length === 0 && <Tenue>No hay alumnos convocados.</Tenue>}
      {e.asistencia.map((a) => {
        const p = presente(a);
        return (
          <ItemLista key={a.alumno_id} icono={p ? 'check-circle' : 'radio-button-unchecked'} colorIcono={p ? C.exito : C.tenue}
            titulo={a.nombre} detalle={p === null ? 'Sin marcar' : p ? 'Presente' : 'Ausente'}
            onPress={e.acciones.asistencia ? () => setMarcas((m) => ({ ...m, [a.alumno_id]: !p })) : undefined} />
        );
      })}
      {cambios > 0 && <Boton titulo={`Guardar asistencia (${cambios})`} icono="save" grande cargando={guardar.isPending} onPress={() => guardar.mutate()} />}
    </Pantalla>
  );
}
