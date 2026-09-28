import { router, Stack, useLocalSearchParams } from 'expo-router';
import { Linking } from 'react-native';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue } from '@/components/ui';
import type { Profesor } from '@/features/profesores/FormProfesor';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';

export default function FichaProfesor() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Profesor>('profesores', id);
  const eliminar = useOperacion(() => api(`profesores/${id}`, { method: 'DELETE' }), {
    exito: 'Docente eliminado',
    invalidar: ['profesores', 'personas'],
    alTerminar: () => router.back(),
  });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const p = q.data;

  const borrar = async () => {
    if (await confirmar({ titulo: `¿Eliminar a ${p.nombre} del plantel?`, mensaje: 'Solo se puede si no tiene bloques asignados. Su ficha de persona se conserva. Queda registrado en auditoría.', accion: 'Eliminar' })) {
      eliminar.mutate();
    }
  };

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Docente' }} />
      <Encabezado
        titulo={p.nombre}
        subtitulo={p.cuenta ? `@${p.cuenta.username}` : 'Sin cuenta de acceso'}
        chips={<>
          <Chip texto={p.activo ? 'Activo' : 'Inactivo'} color={p.activo ? C.exito : C.tenue} />
          {p.alumno_id && <Chip texto="También alumno/a" color={C.info} />}
          {p.areas.map((a) => <Chip key={a} texto={`Coord. ${a}`} color={C.alerta} />)}
        </>}
      />
      <Acciones>
        {p.acciones.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/profesores/[id]/editar', params: { id: String(p.id) } } as never)} />}
        {p.persona_id && <Boton titulo="Ficha de persona" icono="badge" variante="secundario" onPress={() => router.push({ pathname: '/personas/[id]', params: { id: String(p.persona_id) } } as never)} />}
        {!!p.telefono && <Boton titulo="Llamar" icono="call" variante="secundario" onPress={() => Linking.openURL(`tel:${p.telefono}`)} />}
        {p.acciones.eliminar && <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={() => void borrar()} />}
      </Acciones>

      <Tarjeta>
        <Dato etiqueta="Teléfono" valor={p.telefono} />
        <Dato etiqueta="Email" valor={p.email} onPress={p.email ? () => Linking.openURL(`mailto:${p.email}`) : undefined} icono={p.email ? 'email' : undefined} />
      </Tarjeta>

      <Subtitulo>Bloques ({p.bloques.length})</Subtitulo>
      {p.bloques.length === 0 && <Tenue>Sin bloques asignados.</Tenue>}
      {p.bloques.map((b) => (
        <ItemLista
          key={b.id}
          icono="groups"
          titulo={b.nombre}
          subtitulo={`${b.rol}${b.sede ? ` · ${b.sede}` : ''}`}
          detalle={`${b.cantidad_alumnos} alumnos`}
          onPress={() => router.push({ pathname: '/bloques/[id]', params: { id: String(b.id) } } as never)}
        />
      ))}

      {p.sedes.length > 0 && (
        <>
          <Subtitulo>Roles por sede</Subtitulo>
          {p.sedes.map((s) => <ItemLista key={`${s.id}-${s.rol}`} icono="location-on" titulo={s.nombre} subtitulo={s.rol_nombre} />)}
        </>
      )}

      {p.eventos.length > 0 && (
        <>
          <Subtitulo>Próximos eventos a cargo</Subtitulo>
          {p.eventos.map((e) => <ItemLista key={e.id} icono="celebration" titulo={e.titulo} subtitulo={formatearFecha(e.fecha)} onPress={() => router.push({ pathname: '/eventos/[id]', params: { id: String(e.id) } } as never)} />)}
        </>
      )}
    </Pantalla>
  );
}
