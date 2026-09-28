import { router, Stack, useLocalSearchParams } from 'expo-router';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue } from '@/components/ui';
import type { Sede } from '@/features/agenda/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { usePuede } from '@/lib/permisos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function FichaSede() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Sede>('sedes', id);
  const puedeEvento = usePuede('eventos.create');
  const eliminar = useOperacion(() => api(`sedes/${id}`, { method: 'DELETE' }), { exito: 'Sede eliminada', invalidar: ['sedes', ['catalogo', 'sedes']], alTerminar: () => router.back() });
  const alternar = useOperacion((activo: boolean) => api(`sedes/${id}`, { method: 'PUT', body: { nombre: q.data?.nombre, activo } }), {
    exito: (r) => ((r as { data: Sede }).data.activo ? 'Sede activada' : 'Sede desactivada'),
    invalidar: ['sedes', ['catalogo', 'sedes']],
  });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const s = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Sede' }} />
      <Encabezado icono="location-on" titulo={s.nombre} subtitulo={s.direccion} chips={<>
        <Chip texto={s.activo ? 'Activa' : 'Inactiva'} color={s.activo ? C.exito : C.tenue} />
        {s.tipo_propiedad_nombre && <Chip texto={s.tipo_propiedad_nombre} />}
      </>} />
      <Acciones>
        {s.acciones.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/sedes/[id]/editar', params: { id: String(s.id) } } as never)} />}
        {s.acciones.editar && (
          <Boton
            titulo={s.activo ? 'Desactivar' : 'Activar'}
            icono={s.activo ? 'toggle-off' : 'toggle-on'}
            variante="secundario"
            cargando={alternar.isPending}
            onPress={async () => {
              if (!s.activo || await confirmar({ titulo: `¿Desactivar ${s.nombre}?`, mensaje: 'Deja de aparecer para inscribir y cargar. No se borra nada.', accion: 'Desactivar' })) alternar.mutate(!s.activo);
            }}
          />
        )}
        {puedeEvento && <Boton titulo="Nuevo evento" icono="event" variante="secundario" onPress={() => router.push({ pathname: '/eventos/nuevo', params: { sede_id: String(s.id) } } as never)} />}
        {s.acciones.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar la sede ${s.nombre}?`, mensaje: 'Solo se puede si no tiene bloques, alumnos ni historial. Si tiene, desactivala.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>

      <Tarjeta>
        <Dato etiqueta="Alumnos activos" valor={String(s.cantidad_alumnos)} />
        <Dato etiqueta="Alquiler mensual" valor={s.costo_alquiler_mensual != null ? moneda(s.costo_alquiler_mensual) : null} />
        <Dato etiqueta="Liquidación docente" valor={s.liquidacion_porc_docente != null ? `${s.liquidacion_porc_docente}% sobre cuota − ${moneda(s.liquidacion_retencion_escuela ?? 0)}` : null} />
      </Tarjeta>

      <Subtitulo>Bloques ({s.bloques.length})</Subtitulo>
      {s.bloques.length === 0 && <Tenue>Sin bloques.</Tenue>}
      {s.bloques.map((b) => (
        <ItemLista key={b.id} icono="groups" colorIcono={b.activo ? C.acento : C.tenue} titulo={b.nombre} subtitulo={`${b.anio}° año${b.profesor ? ` · ${b.profesor}` : ''}`} detalle={`${b.cantidad_alumnos} alumnos${b.activo ? '' : ' · inactivo'}`}
          onPress={() => router.push({ pathname: '/bloques/[id]', params: { id: String(b.id) } } as never)} />
      ))}

      <Subtitulo>Próximos eventos</Subtitulo>
      {s.eventos.length === 0 && <Tenue>No hay eventos próximos.</Tenue>}
      {s.eventos.map((e) => (
        <ItemLista key={e.id} icono="celebration" titulo={e.titulo} subtitulo={`${formatearFecha(e.fecha)}${e.hora_inicio ? ` · ${e.hora_inicio}` : ''}`} onPress={() => router.push({ pathname: '/eventos/[id]', params: { id: String(e.id) } } as never)} />
      ))}
    </Pantalla>
  );
}
