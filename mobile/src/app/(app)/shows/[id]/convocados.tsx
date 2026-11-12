import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { FiltrosChips, ItemLista } from '@/components/lista';
import { Boton, Cargando, Chip, ErrorVista, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { type Convocado, colorRespuesta, type Respuesta, type ResumenConfirmaciones, type ShowDetalle } from '@/features/shows/tipos';
import { api } from '@/lib/api';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

type Filtro = Respuesta | 'sin';

/** Convocados al show: qué respondió cada uno y, después del show, quién fue. */
export default function ConvocadosShow() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const show = useDetalle<ShowDetalle>('shows', id);
  const q = useQuery({
    queryKey: ['shows', 'convocados', id],
    queryFn: ({ signal }) => api<{ data: Convocado[]; resumen: ResumenConfirmaciones }>(`shows/${id}/convocados`, { signal }),
  });
  const [filtro, setFiltro] = useState<Filtro | null>(null);
  const [marcas, setMarcas] = useState<Record<number, boolean | null>>({});
  const guardar = useOperacion(
    () => api(`shows/${id}/asistencia`, { method: 'PUT', body: { marcas } }),
    { exito: 'Asistencia guardada', invalidar: ['shows'], alTerminar: () => setMarcas({}) },
  );

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const r = q.data.resumen;
  const puedeAsistencia = !!show.data?.acciones?.asistencia;
  const filas = q.data.data.filter((f) => !filtro || (filtro === 'sin' ? !f.respuesta : f.respuesta === filtro));
  const asistio = (f: Convocado) => (f.alumno_id in marcas ? marcas[f.alumno_id] : f.asistio);
  const cambios = Object.keys(marcas).length;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Convocados' }} />
      <Tarjeta>
        <Texto style={{ fontWeight: '700' }}>{r.voy} van · {r.tal_vez} tal vez · {r.no_voy} no van</Texto>
        <Tenue>{r.sin_respuesta} sin responder · {r.asistieron} fueron al show</Tenue>
      </Tarjeta>
      <FiltrosChips<Filtro>
        valor={filtro}
        onChange={setFiltro}
        opciones={[{ valor: 'voy', etiqueta: 'Van' }, { valor: 'tal_vez', etiqueta: 'Tal vez' }, { valor: 'no_voy', etiqueta: 'No van' }, { valor: 'sin', etiqueta: 'Sin responder' }]}
      />
      {filas.length === 0 && <Vacio icono="groups" texto="No hay convocados en esta lista." />}
      <View style={{ gap: E.s }}>
        {filas.map((f) => {
          const fue = asistio(f);
          return (
            <ItemLista
              key={f.alumno_id}
              icono="person"
              colorIcono={colorRespuesta(f.respuesta)}
              titulo={f.nombre}
              subtitulo={[f.bloque, f.comentario ? `«${f.comentario}»` : null].filter(Boolean).join(' · ') || null}
              detalle={puedeAsistencia ? (fue === null ? 'Tocá para marcar si fue' : fue ? 'Fue al show' : 'No fue') : null}
              derecha={<Chip texto={f.respuesta_texto} color={colorRespuesta(f.respuesta)} />}
              onPress={puedeAsistencia ? () => setMarcas((m) => ({ ...m, [f.alumno_id]: fue === true ? false : true })) : undefined}
            />
          );
        })}
      </View>
      {puedeAsistencia && cambios > 0 && (
        <Boton titulo={`Guardar asistencia (${cambios})`} icono="save" grande cargando={guardar.isPending} onPress={() => guardar.mutate()} />
      )}
      {!puedeAsistencia && <Tenue style={{ color: C.tenue }}>La asistencia la marca quien organiza o un docente del bloque.</Tenue>}
    </Pantalla>
  );
}
