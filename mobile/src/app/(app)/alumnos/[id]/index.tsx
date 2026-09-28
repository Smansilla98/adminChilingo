import { useQuery } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { Linking } from 'react-native';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue } from '@/components/ui';
import type { AlumnoFicha } from '@/features/alumnos/FormAlumno';
import { EstadoCuentaVista } from '@/features/finanzas/EstadoCuentaVista';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { usePuede } from '@/lib/permisos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';
import type { EstadoCuenta } from '@/lib/types';

export default function FichaAlumno() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<AlumnoFicha>('alumnos', id);
  const puedePagar = usePuede('pagos.create');
  const cuenta = useQuery({
    queryKey: ['alumnos', 'cuenta', id],
    queryFn: () => api<EstadoCuenta>(`alumnos/${id}/estado-cuenta`),
    enabled: !!q.data?.acciones.ver_finanzas,
  });
  const eliminar = useOperacion(() => api(`alumnos/${id}`, { method: 'DELETE' }), { exito: 'Alumno eliminado', invalidar: ['alumnos', 'personas', 'bloques'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const a = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => { void q.refetch(); void cuenta.refetch(); }}>
      <Stack.Screen options={{ title: 'Alumno' }} />
      <Encabezado titulo={a.nombre} subtitulo={[a.sede?.nombre, a.instrumento, a.dni ? `DNI ${a.dni}` : null].filter(Boolean).join(' · ')} chips={<>
        <Chip texto={a.activo ? 'Activo' : 'Inactivo'} color={a.activo ? C.exito : C.tenue} />
        {a.es_docente && <Chip texto="También docente" color={C.acento} />}
      </>} />
      <Acciones>
        {a.acciones.ver_persona && a.persona_id && <Boton titulo="Ficha de persona" icono="badge" variante="secundario" onPress={() => router.push({ pathname: '/personas/[id]', params: { id: String(a.persona_id) } } as never)} />}
        {a.acciones.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/alumnos/[id]/editar', params: { id: String(a.id) } } as never)} />}
        {!!a.telefono && <Boton titulo="Llamar" icono="call" variante="secundario" onPress={() => Linking.openURL(`tel:${a.telefono}`)} />}
        {puedePagar && a.acciones.ver_finanzas && <Boton titulo="Registrar pago" icono="add-card" onPress={() => router.push({ pathname: '/pagos/nuevo', params: { alumno_id: String(a.id) } } as never)} />}
        {a.acciones.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar a ${a.nombre}?`, mensaje: 'Solo se puede si no tiene historial (asistencias, pagos, comprobantes). Si lo tiene, marcalo inactivo.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>
      <Tarjeta>
        <Dato etiqueta="Nacimiento" valor={a.fecha_nacimiento ? formatearFecha(a.fecha_nacimiento) : null} />
        <Dato etiqueta="Instrumento secundario" valor={a.instrumento_secundario} />
        <Dato etiqueta="Tambor" valor={[a.tipo_tambor, a.tambor_procedencia].filter(Boolean).join(' · ')} />
      </Tarjeta>
      <Subtitulo>Bloques</Subtitulo>
      {a.bloques_detalle.length === 0 && <Tenue>Sin bloques.</Tenue>}
      {a.bloques_detalle.map((b) => (
        <ItemLista key={b.id} icono="groups" titulo={b.nombre} subtitulo={b.sede} derecha={b.principal ? <Chip texto="Principal" color={C.acento} /> : undefined}
          onPress={() => router.push({ pathname: '/bloques/[id]', params: { id: String(b.id) } } as never)} />
      ))}
      {a.acciones.ver_finanzas && (
        <>
          <Subtitulo>Cuotas</Subtitulo>
          {cuenta.isPending && <Tenue>Cargando estado de cuenta…</Tenue>}
          {cuenta.isError && <Tenue>{(cuenta.error as Error).message}</Tenue>}
          {cuenta.data && <EstadoCuentaVista cuenta={cuenta.data} onCuota={(cuotaId) => router.push({ pathname: '/cuotas/[id]', params: { id: String(cuotaId) } } as never)} />}
        </>
      )}
      {!a.acciones.ver_finanzas && <Tenue>No tenés acceso a la información financiera de este alumno.</Tenue>}
    </Pantalla>
  );
}
