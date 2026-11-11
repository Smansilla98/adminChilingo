import { useQuery } from '@tanstack/react-query';
import { View } from 'react-native';

import { ItemLista } from '@/components/lista';
import { Chip, Fila, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { C, E } from '@/lib/theme';

export interface Resumen {
  clases: number;
  presentes: number;
  tardes: number;
  ausentes: number;
  justificadas: number;
  porcentaje: number | null;
}

export interface Horario {
  dia: number;
  dia_nombre: string;
  inicio: string;
  fin: string;
}

export interface MiAsistenciaRespuesta {
  anio: number;
  alumnos: {
    alumno_id: number;
    nombre: string;
    resumen: Resumen;
    bloques: { bloque_id: number; nombre: string; sede: string | null; actual: boolean; horarios: Horario[]; resumen: Resumen; meses: (Resumen & { mes: number })[] }[];
    ultimas: { fecha: string; bloque: string | null; tipo: string; tipo_etiqueta: string }[];
  }[];
}

const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const COLOR_TIPO: Record<string, string> = { presente: C.exito, tarde: C.alerta, ausencia_justificada: C.info, ausencia_injustificada: C.peligro };

/** Mi asistencia (endpoint mi/asistencia). Se comparte entre Mi actividad y el horario de Inicio. */
export const useMiAsistencia = (habilitado = true) =>
  useQuery({ queryKey: ['mi', 'asistencia'], queryFn: () => api<MiAsistenciaRespuesta>('mi/asistencia'), enabled: habilitado });

const colorPorcentaje = (p: number | null) => (p === null ? C.tenue : p >= 80 ? C.exito : p >= 60 ? C.alerta : C.peligro);

function Porcentaje({ resumen }: { resumen: Resumen }) {
  if (resumen.porcentaje === null) return <Chip texto="Sin clases" />;
  return <Chip texto={`${resumen.porcentaje}%`} color={colorPorcentaje(resumen.porcentaje)} />;
}

const detalle = (r: Resumen) =>
  `${r.presentes} presente${r.presentes === 1 ? '' : 's'}${r.tardes ? ` · ${r.tardes} tarde` : ''} · ${r.ausentes} ausente${r.ausentes === 1 ? '' : 's'}${r.justificadas ? ` · ${r.justificadas} justificada${r.justificadas === 1 ? '' : 's'}` : ''}`;

/** Sección «Asistencia» de Mi actividad: porcentaje del año, por bloque y por mes, y las últimas clases. */
export function MiAsistenciaVista() {
  const q = useMiAsistencia();
  if (q.isPending && !q.data) return <Tenue>Cargando tu asistencia…</Tenue>;
  if (q.isError && !q.data) return <Tenue>{q.error instanceof Error ? q.error.message : 'No se pudo cargar la asistencia.'}</Tenue>;
  const alumnos = q.data?.alumnos ?? [];
  if (alumnos.length === 0) return <Vacio icono="fact-check" texto="No encontramos tu ficha de alumno. Consultá en la escuela." />;

  return (
    <View style={{ gap: E.m }}>
      {alumnos.map((a) => (
        <View key={a.alumno_id} style={{ gap: E.s }}>
          <Tarjeta acento={colorPorcentaje(a.resumen.porcentaje)}>
            <Tenue>Tu asistencia en {q.data?.anio}{alumnos.length > 1 ? ` · ${a.nombre}` : ''}</Tenue>
            <Texto style={{ fontSize: 28, fontWeight: '800' }} accessibilityLabel={a.resumen.porcentaje === null ? 'Todavía no hay clases registradas' : `${a.resumen.porcentaje} por ciento de asistencia`}>
              {a.resumen.porcentaje === null ? 'Sin clases todavía' : `${a.resumen.porcentaje}%`}
            </Texto>
            {a.resumen.clases > 0 && <Tenue>{detalle(a.resumen)} · {a.resumen.clases} clases</Tenue>}
          </Tarjeta>

          {a.bloques.map((b) => (
            <View key={b.bloque_id} style={{ gap: E.s }}>
              <Fila style={{ justifyContent: 'space-between' }}>
                <View style={{ flex: 1 }}>
                  <Subtitulo>{b.nombre}</Subtitulo>
                  {!!b.sede && <Tenue>{b.sede}{b.actual ? '' : ' · ya no estás en este bloque'}</Tenue>}
                </View>
                <Porcentaje resumen={b.resumen} />
              </Fila>
              {b.meses.length === 0 && <Tenue>Todavía no hay asistencia cargada este año.</Tenue>}
              {b.meses.map((m) => (
                <ItemLista key={m.mes} icono="calendar-month" colorIcono={colorPorcentaje(m.porcentaje)} titulo={MESES[m.mes]} subtitulo={detalle(m)} derecha={<Porcentaje resumen={m} />} />
              ))}
            </View>
          ))}

          {a.ultimas.length > 0 && (
            <>
              <Subtitulo>Últimas clases</Subtitulo>
              {a.ultimas.map((u) => (
                <ItemLista
                  key={`${u.fecha}-${u.bloque}`}
                  icono="event"
                  colorIcono={COLOR_TIPO[u.tipo] ?? C.tenue}
                  titulo={formatearFecha(u.fecha)}
                  subtitulo={u.bloque}
                  derecha={<Chip texto={u.tipo_etiqueta} color={COLOR_TIPO[u.tipo] ?? C.tenue} />}
                />
              ))}
            </>
          )}
        </View>
      ))}
    </View>
  );
}

/** Horario semanal fijo de los bloques actuales del alumno (para Inicio y Mi actividad). */
export function MiHorario() {
  const q = useMiAsistencia();
  const bloques = (q.data?.alumnos ?? []).flatMap((a) => a.bloques.filter((b) => b.actual && b.horarios.length > 0));
  if (bloques.length === 0) return null;
  const clases = bloques
    .flatMap((b) => b.horarios.map((h) => ({ ...h, bloque: b.nombre, sede: b.sede })))
    .sort((x, y) => x.dia - y.dia || x.inicio.localeCompare(y.inicio));

  return (
    <Tarjeta>
      <Texto style={{ fontWeight: '800' }}>Mi horario</Texto>
      {clases.map((c) => (
        <Fila key={`${c.bloque}-${c.dia}-${c.inicio}`} style={{ justifyContent: 'space-between' }}>
          <Texto style={{ fontWeight: '700', minWidth: 90 }}>{c.dia_nombre}</Texto>
          <View style={{ flex: 1 }}>
            <Texto>{c.inicio} a {c.fin}</Texto>
            <Tenue>{[c.bloque, c.sede].filter(Boolean).join(' · ')}</Tenue>
          </View>
        </Fila>
      ))}
    </Tarjeta>
  );
}
