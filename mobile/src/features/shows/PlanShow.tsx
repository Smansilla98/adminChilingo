import { router } from 'expo-router';
import { View } from 'react-native';

import { ItemLista } from '@/components/lista';
import { Boton, Dato, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import { formatearFecha } from '@/lib/formato';
import { E } from '@/lib/theme';

import type { PlanShow as Plan, ResumenConfirmaciones } from './tipos';

/** Lista de temas, ensayos, logística e instrumentos (solo lectura). */
export function DetallePlan({ plan, onEnsayo }: { plan: Plan; onEnsayo?: (id: number) => void }) {
  const l = plan.logistica;
  const hayLogistica = !!(l.punto_encuentro || l.hora_encuentro || l.transporte || l.notas);
  return (
    <View style={{ gap: E.s }}>
      <Subtitulo>Lista de temas ({plan.temas.length})</Subtitulo>
      {plan.temas.length === 0 ? <Tenue>Todavía no hay temas.</Tenue> : (
        <Tarjeta>
          {plan.temas.map((t, i) => <Texto key={t.id}>{i + 1}. {t.nombre}{t.notas ? ` · ${t.notas}` : ''}</Texto>)}
        </Tarjeta>
      )}
      <Subtitulo>Ensayos ({plan.ensayos.length})</Subtitulo>
      {plan.ensayos.length === 0 && <Tenue>Sin ensayos cargados.</Tenue>}
      {plan.ensayos.map((e) => (
        <ItemLista key={e.id} icono="event" titulo={`${formatearFecha(e.fecha)}${e.hora_inicio ? ` · ${e.hora_inicio}` : ''}`}
          subtitulo={e.lugar} detalle={onEnsayo ? `${e.presentes} presentes` : e.notas} onPress={onEnsayo ? () => onEnsayo(e.id) : undefined} />
      ))}
      <Subtitulo>Logística</Subtitulo>
      {hayLogistica ? (
        <Tarjeta>
          {l.punto_encuentro && <Dato etiqueta="Punto de encuentro" valor={l.punto_encuentro} />}
          {l.hora_encuentro && <Dato etiqueta="Hora de encuentro" valor={l.hora_encuentro} />}
          {l.transporte && <Dato etiqueta="Transporte" valor={l.transporte} />}
          {l.notas && <Texto>{l.notas}</Texto>}
        </Tarjeta>
      ) : <Tenue>Sin datos de traslado todavía.</Tenue>}
      {plan.instrumentos.length > 0 && (
        <>
          <Subtitulo>Instrumentos que van</Subtitulo>
          <Tarjeta>{plan.instrumentos.map((i) => <Texto key={i.id}>{i.cantidad} × {i.nombre}</Texto>)}</Tarjeta>
        </>
      )}
    </View>
  );
}

/** Bloque para la ficha del show (organizador / docente): confirmaciones y accesos. */
export function SeccionParticipacion({ showId, resumen, plan, puedeEditar }: { showId: number; resumen: ResumenConfirmaciones | null; plan: Plan; puedeEditar: boolean }) {
  return (
    <View style={{ gap: E.s }}>
      {resumen && (
        <>
          <Subtitulo>Confirmaciones</Subtitulo>
          <Tarjeta>
            <Texto style={{ fontWeight: '700' }}>{resumen.voy} van · {resumen.tal_vez} tal vez · {resumen.no_voy} no van</Texto>
            <Tenue>{resumen.sin_respuesta} sin responder de {resumen.convocados} convocados{resumen.asistieron ? ` · ${resumen.asistieron} fueron` : ''}</Tenue>
            <Boton titulo="Ver convocados y asistencia" icono="how-to-reg" variante="secundario"
              onPress={() => router.push({ pathname: '/shows/[id]/convocados', params: { id: String(showId) } } as never)} />
          </Tarjeta>
        </>
      )}
      <DetallePlan plan={plan} onEnsayo={(e) => router.push({ pathname: '/shows/[id]/ensayo/[ensayoId]', params: { id: String(showId), ensayoId: String(e) } } as never)} />
      {puedeEditar && (
        <View style={{ gap: E.s }}>
          <Boton titulo="Editar temas, logística e instrumentos" icono="queue-music" variante="secundario"
            onPress={() => router.push({ pathname: '/shows/[id]/plan', params: { id: String(showId) } } as never)} />
          <Boton titulo="Agregar ensayo" icono="event" variante="secundario"
            onPress={() => router.push({ pathname: '/shows/[id]/ensayo/nuevo', params: { id: String(showId) } } as never)} />
        </View>
      )}
    </View>
  );
}
