import { router, Stack } from 'expo-router';
import { View } from 'react-native';

import { ItemLista } from '@/components/lista';
import { Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { type EstadoToque, type ProgresoAlumno, useMiProgreso } from '@/features/progreso/tipos';
import { formatearFecha } from '@/lib/formato';
import { C, E } from '@/lib/theme';

const COLOR: Record<EstadoToque, string> = { no_empezado: C.tenue, en_proceso: C.alerta, aprobado: C.exito };
const ICONO = { no_empezado: 'radio-button-unchecked', en_proceso: 'timelapse', aprobado: 'check-circle' } as const;

/** «Mi progreso»: los toques de cada año y en qué anda el alumno con cada uno. No es una nota. */
export default function MiProgreso() {
  const q = useMiProgreso();
  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const perfiles = q.data?.data ?? [];

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Mi progreso' }} />
      {perfiles.length === 0 && <Vacio icono="music-note" texto="Todavía no estás inscripto en un bloque." />}
      {perfiles.map((p) => <Perfil key={p.alumno.id} p={p} varios={perfiles.length > 1} />)}
    </Pantalla>
  );
}

function Perfil({ p, varios }: { p: ProgresoAlumno; varios: boolean }) {
  const actual = p.anios.find((a) => a.anio === p.anio_actual);
  return (
    <View style={{ gap: E.s }}>
      <Tarjeta acento={C.info}>
        {varios && <Texto style={{ fontWeight: '800' }}>{p.alumno.nombre}</Texto>}
        <Tenue>Estás cursando</Tenue>
        <Texto style={{ fontSize: 26, fontWeight: '800' }}>{p.anio_actual}° año</Texto>
        {actual && <Tenue>Ya tocás con el bloque {actual.tocados} de {actual.total} toques de este año{actual.en_proceso > 0 ? ` · ${actual.en_proceso} en camino` : ''}.</Tenue>}
        {p.propuesta && <Chip texto={`Tu docente puede confirmar el paso a ${p.propuesta.anio_hasta}° año`} color={C.exito} />}
      </Tarjeta>

      {p.anios.map((a) => (
        <View key={a.anio} style={{ gap: E.s }}>
          <Subtitulo>Toques de {a.anio}° año</Subtitulo>
          {a.toques.length === 0 && <Tenue>Todavía no hay toques cargados para este año.</Tenue>}
          {a.toques.map((t) => (
            <ItemLista
              key={t.id}
              icono={ICONO[t.estado]}
              colorIcono={COLOR[t.estado]}
              titulo={t.nombre + (t.opcional ? ' (opcional)' : '')}
              subtitulo={t.estado_nombre}
              detalle={t.fecha ? `Desde el ${formatearFecha(t.fecha)}` : null}
              onPress={() => router.push({ pathname: '/partituras/[slug]', params: { slug: t.slug } } as never)}
            />
          ))}
        </View>
      ))}

      <Subtitulo>Devoluciones de tus docentes</Subtitulo>
      {p.evaluaciones.length === 0 && <Tenue>Todavía no hay devoluciones.</Tenue>}
      {p.evaluaciones.map((e) => (
        <Tarjeta key={e.id}>
          <Texto style={{ fontWeight: '800' }}>{e.resultado_nombre}</Texto>
          {e.devolucion && <Texto>{e.devolucion}</Texto>}
          <Tenue style={{ fontSize: 13 }}>{[formatearFecha(e.fecha), e.toques.join(', '), e.docente].filter(Boolean).join(' · ')}</Tenue>
        </Tarjeta>
      ))}

      {p.historial.length > 0 && (
        <>
          <Subtitulo>Años que cursaste</Subtitulo>
          {p.historial.map((h, i) => (
            <Tenue key={i}>{h.anio}° año · desde {formatearFecha(h.desde)}{h.hasta ? ` hasta ${formatearFecha(h.hasta)}` : ' (ahora)'}{h.bloque ? ` · ${h.bloque}` : ''}</Tenue>
          ))}
        </>
      )}
    </View>
  );
}
