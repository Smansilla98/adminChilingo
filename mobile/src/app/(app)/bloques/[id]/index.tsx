import { useQuery } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { CampoHora, ErrorFormulario, Selector, useFormulario } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Encabezado, ErrorVista, Fila, Icon, Pantalla, Segmentos, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import type { BloqueFicha } from '@/features/agenda/FormBloque';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

const DIAS = [1, 2, 3, 4, 5, 6, 7].map((d) => ({ valor: d, etiqueta: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][d - 1] }));

export default function FichaBloque() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<BloqueFicha>('bloques', id);
  const [pestana, setPestana] = useState<'info' | 'alumnos'>('info');
  const [agregando, setAgregando] = useState(false);
  const eliminar = useOperacion(() => api(`bloques/${id}`, { method: 'DELETE' }), { exito: 'Bloque eliminado', invalidar: ['bloques', 'sedes'], alTerminar: () => router.back() });
  const quitarHorario = useOperacion((hid: number) => api(`bloque-horarios/${hid}`, { method: 'DELETE' }), { exito: 'Horario eliminado', invalidar: ['bloques', 'calendario'] });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const b = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Bloque' }} />
      <Encabezado icono="groups" titulo={b.nombre} subtitulo={`${b.anio}° año${b.sede ? ` · ${b.sede.nombre}` : ''}`} chips={<>
        <Chip texto={b.activo ? 'Activo' : 'Inactivo'} color={b.activo ? C.exito : C.tenue} />
        <Chip texto={`${b.cantidad_alumnos}/${b.cantidad_max_alumnos} alumnos`} color={b.cantidad_alumnos >= b.cantidad_max_alumnos ? C.alerta : C.info} />
      </>} />
      <Acciones>
        {b.acciones.tomar_asistencia && <Boton titulo="Tomar asistencia" icono="fact-check" onPress={() => router.push({ pathname: '/asistencia/[bloque]', params: { bloque: String(b.id), nombre: b.nombre } } as never)} />}
        {b.acciones.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/bloques/[id]/editar', params: { id: String(b.id) } } as never)} />}
        {b.acciones.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar ${b.nombre}?`, mensaje: 'Solo se puede si no tiene alumnos ni historial (asistencias, cuotas). Si no, desactivalo.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>

      <Segmentos opciones={[{ valor: 'info', etiqueta: 'Información' }, ...(b.acciones.ver_alumnos ? [{ valor: 'alumnos' as const, etiqueta: 'Alumnos', cantidad: b.cantidad_alumnos }] : [])]} valor={pestana} onChange={setPestana} />

      {pestana === 'info' && (
        <>
          <Subtitulo>Horarios</Subtitulo>
          <Tarjeta>
            {b.horarios_detalle.length === 0 && <Tenue>Sin horarios cargados.</Tenue>}
            {b.horarios_detalle.map((h) => (
              <Fila key={h.id} style={{ justifyContent: 'space-between', minHeight: 44 }}>
                <Texto>{h.dia_nombre} · {h.inicio}–{h.fin}</Texto>
                {b.acciones.editar && (
                  <Pressable accessibilityRole="button" accessibilityLabel={`Quitar horario ${h.dia_nombre} ${h.inicio}`} hitSlop={10}
                    onPress={async () => { if (await confirmar({ titulo: `¿Quitar el horario del ${h.dia_nombre}?`, accion: 'Quitar' })) quitarHorario.mutate(h.id); }}>
                    <Icon name="delete-outline" size={24} color={C.peligro} />
                  </Pressable>
                )}
              </Fila>
            ))}
            {b.acciones.editar && <Boton titulo="Agregar horario" icono="add-alarm" variante="secundario" onPress={() => setAgregando(true)} />}
          </Tarjeta>

          <Subtitulo>Docentes</Subtitulo>
          {b.profesores.length === 0 && <Tenue>Sin docentes asignados.</Tenue>}
          {b.profesores.map((p) => (
            <ItemLista key={p.id} icono="co-present" titulo={p.nombre} subtitulo={p.rol} onPress={() => router.push({ pathname: '/profesores/[id]', params: { id: String(p.id) } } as never)} />
          ))}
          {b.tambores.length > 0 && (
            <>
              <Subtitulo>Tambores</Subtitulo>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.s }}>{b.tambores.map((t) => <Chip key={t} texto={t} />)}</View>
            </>
          )}
          {b.eventos.length > 0 && (
            <>
              <Subtitulo>Próximos eventos</Subtitulo>
              {b.eventos.map((ev) => <ItemLista key={ev.id} icono="celebration" titulo={ev.titulo} subtitulo={formatearFecha(ev.fecha)} onPress={() => router.push({ pathname: '/eventos/[id]', params: { id: String(ev.id) } } as never)} />)}
            </>
          )}
        </>
      )}
      {pestana === 'alumnos' && <Alumnos bloqueId={b.id} />}
      <NuevoHorario bloqueId={b.id} visible={agregando} onCerrar={() => setAgregando(false)} />
    </Pantalla>
  );
}

function Alumnos({ bloqueId }: { bloqueId: number }) {
  const q = useQuery({ queryKey: ['bloques', 'alumnos', bloqueId], queryFn: () => api<{ data: { id: number; nombre: string; activo: boolean; instrumento: string | null }[] }>(`bloques/${bloqueId}/alumnos`).then((r) => r.data) });
  if (q.isPending) return <Tenue>Cargando alumnos…</Tenue>;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  if (q.data.length === 0) return <Tenue>Sin alumnos inscriptos.</Tenue>;
  return (
    <>
      {q.data.map((a) => (
        <ItemLista key={a.id} icono="school" titulo={a.nombre} subtitulo={a.instrumento} derecha={!a.activo ? <Chip texto="Inactivo" /> : undefined}
          onPress={() => router.push({ pathname: '/alumnos/[id]', params: { id: String(a.id) } } as never)} />
      ))}
    </>
  );
}

function NuevoHorario({ bloqueId, visible, onCerrar }: { bloqueId: number; visible: boolean; onCerrar: () => void }) {
  return (
    <Hoja visible={visible} titulo="Agregar horario" onCerrar={onCerrar}>
      {visible && <FormHorario bloqueId={bloqueId} onCerrar={onCerrar} />}
    </Hoja>
  );
}

function FormHorario({ bloqueId, onCerrar }: { bloqueId: number; onCerrar: () => void }) {
  const f = useFormulario({ dia_semana: 1, hora_inicio: '18:00', hora_fin: '19:30' });
  const op = useOperacion((d: typeof f.valores) => api(`bloques/${bloqueId}/horarios`, { method: 'POST', body: d }), { exito: 'Horario agregado', invalidar: ['bloques', 'calendario', 'inicio'], alTerminar: onCerrar });
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Selector etiqueta="Día" opciones={DIAS} valor={f.valores.dia_semana} onChange={(x) => f.set('dia_semana', x ?? 1)} error={f.errores.dia_semana} />
      <CampoHora etiqueta="Desde" valor={f.valores.hora_inicio} onChange={(x) => f.set('hora_inicio', x)} error={f.errores.hora_inicio} requerido />
      <CampoHora etiqueta="Hasta" valor={f.valores.hora_fin} onChange={(x) => f.set('hora_fin', x)} error={f.errores.hora_fin} requerido />
      <Boton titulo="Agregar" icono="add" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d), (d): Record<string, string> => (d.hora_fin <= d.hora_inicio ? { hora_fin: 'Tiene que ser posterior al inicio.' } : {}))} />
    </>
  );
}
