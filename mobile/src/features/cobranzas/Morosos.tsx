import { Alert, View } from 'react-native';
import { useState } from 'react';

import { Aviso, Boton, Chip, Encabezado, Fila, Pantalla, Tarjeta, Tenue, Texto, Vacio, ErrorVista, Cargando } from '@/components/ui';
import { FiltrosChips } from '@/components/lista';
import { E } from '@/lib/theme';
import { ApiError } from '@/lib/api';

import { useMorosos, useRecordarMoroso, useRecordarMorosos } from './hooks';
import { type Moroso, type Tramo, TRAMO_NOMBRE } from './tipos';

/** Morosidad: alumnos con cuotas vencidas, por antigüedad, con recordatorio individual y masivo. */
export function Morosos() {
  const [tramo, setTramo] = useState<Tramo | null>(null);
  const [mensaje, setMensaje] = useState<{ texto: string; tono: 'exito' | 'alerta' | 'peligro' } | null>(null);
  const consulta = useMorosos(tramo);
  const individual = useRecordarMoroso();
  const masivo = useRecordarMorosos();

  if (consulta.isPending) return <Cargando />;
  if (consulta.isError) return <ErrorVista error={consulta.error} onReintentar={() => void consulta.refetch()} />;

  const { data, total_saldo, tramos } = consulta.data;
  const conCuenta = data.filter((m) => m.tiene_cuenta);

  const recordarUno = async (m: Moroso) => {
    setMensaje(null);
    try {
      const r = await individual.mutateAsync({ alumnoId: m.alumno_id });
      setMensaje(textoEstado(r.estado, m.alumno));
    } catch (e) {
      setMensaje({ texto: e instanceof ApiError ? e.message : 'No se pudo enviar el recordatorio.', tono: 'peligro' });
    }
  };

  const recordarTodos = () => {
    if (conCuenta.length === 0) return;
    Alert.alert('Recordar a todos', `¿Enviar recordatorio a ${conCuenta.length} alumno(s)? Quien ya lo recibió este mes no lo recibe otra vez.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Enviar',
        onPress: () => {
          setMensaje(null);
          masivo.mutate(
            { alumnoIds: conCuenta.map((m) => m.alumno_id) },
            {
              onSuccess: (r) =>
                setMensaje({ texto: `Enviados: ${r.enviado}. Ya enviados este mes: ${r.duplicado}. Sin canal disponible: ${r.sin_envio}. Sin cuenta: ${r.sin_cuenta}.`, tono: 'exito' }),
              onError: (e) => setMensaje({ texto: e instanceof ApiError ? e.message : 'No se pudieron enviar los recordatorios.', tono: 'peligro' }),
            },
          );
        },
      },
    ]);
  };

  return (
    <Pantalla refrescando={consulta.isRefetching} onRefrescar={() => void consulta.refetch()}>
      <Encabezado
        titulo="Morosos"
        subtitulo={`${data.length} alumno(s) con cuotas vencidas · total $ ${Math.round(total_saldo).toLocaleString('es-AR')}`}
        icono="warning"
      />
      <FiltrosChips
        opciones={tramos.map((t) => ({ valor: t.id, etiqueta: TRAMO_NOMBRE[t.id] ?? t.nombre }))}
        valor={tramo}
        onChange={setTramo}
        todos="Todas"
      />
      {conCuenta.length > 0 && (
        <Boton titulo={`Recordar a todos (${conCuenta.length})`} icono="notifications" onPress={recordarTodos} cargando={masivo.isPending} />
      )}
      {mensaje && <Aviso texto={mensaje.texto} tono={mensaje.tono} />}

      {data.length === 0 ? (
        <Vacio icono="check-circle" texto="No hay alumnos con cuotas vencidas con este filtro." />
      ) : (
        <View style={{ gap: E.s }}>
          {data.map((m) => (
            <Tarjeta key={m.alumno_id}>
              <Texto style={{ fontWeight: '700' }}>{m.alumno}</Texto>
              <Tenue>
                {[m.sede, m.bloques.join(', ')].filter(Boolean).join(' · ') || 'Sin sede'} · {m.cuotas_vencidas} cuota(s) desde {m.vencida_desde}
              </Tenue>
              <Fila style={{ gap: E.s, flexWrap: 'wrap', alignItems: 'center' }}>
                <Chip texto={`${TRAMO_NOMBRE[m.tramo]} (${m.dias_mora} días)`} color={m.tramo === '+90' ? '#b91c1c' : undefined} />
                <Texto style={{ fontWeight: '700' }}>$ {Math.round(m.saldo_vencido).toLocaleString('es-AR')}</Texto>
              </Fila>
              {m.tiene_cuenta ? (
                <Boton titulo="Recordar" icono="notifications-none" variante="secundario" onPress={() => void recordarUno(m)} cargando={individual.isPending} />
              ) : (
                <Tenue>Sin cuenta para recibir avisos.</Tenue>
              )}
            </Tarjeta>
          ))}
        </View>
      )}
    </Pantalla>
  );
}

function textoEstado(estado: string, alumno: string): { texto: string; tono: 'exito' | 'alerta' } {
  switch (estado) {
    case 'enviado':
      return { texto: `Recordatorio enviado a ${alumno}.`, tono: 'exito' };
    case 'duplicado':
      return { texto: `Ya se le envió un recordatorio a ${alumno} este mes.`, tono: 'alerta' };
    case 'al_dia':
      return { texto: `${alumno} ya no tiene cuotas vencidas.`, tono: 'alerta' };
    case 'sin_cuenta':
      return { texto: `${alumno} no tiene cuenta para recibir avisos.`, tono: 'alerta' };
    default:
      return { texto: 'No se pudo enviar por ningún canal disponible.', tono: 'alerta' };
  }
}
