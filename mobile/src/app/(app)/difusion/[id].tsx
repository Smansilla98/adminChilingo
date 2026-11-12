import { Stack, useLocalSearchParams } from 'expo-router';

import { confirmar } from '@/components/feedback';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta, Texto } from '@/components/ui';
import { COLOR_ESTADO, type Difusion } from '@/features/difusion/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useDetalle, useOperacion } from '@/lib/recursos';

const fechaHora = (iso: string | null) => (iso ? `${formatearFecha(iso.slice(0, 10))} ${iso.slice(11, 16)}` : '—');

export default function DetalleDifusion() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Difusion>('difusiones', id);
  const cancelar = useOperacion(() => api(`difusiones/${id}/cancelar`, { method: 'POST' }), { exito: 'Envío cancelado', invalidar: ['difusiones'] });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const d = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Mensaje' }} />
      <Encabezado icono="campaign" titulo={d.titulo} subtitulo={`${d.destino} · ${d.quienes}`} chips={<Chip texto={d.estado_texto} color={COLOR_ESTADO[d.estado]} />} />
      {d.estado === 'programada' && (
        <Acciones>
          <Boton titulo="Cancelar envío" icono="cancel" variante="peligro" cargando={cancelar.isPending} onPress={async () => {
            if (await confirmar({ titulo: '¿Cancelar el envío?', mensaje: 'El mensaje no se va a mandar.', accion: 'Cancelar envío' })) cancelar.mutate();
          }} />
        </Acciones>
      )}
      <Tarjeta><Texto>{d.mensaje}</Texto></Tarjeta>
      <Subtitulo>Cómo salió</Subtitulo>
      <Tarjeta>
        <Dato etiqueta="Canales" valor={d.canales_texto} />
        {d.programada_para && <Dato etiqueta="Programado para" valor={fechaHora(d.programada_para)} />}
        <Dato etiqueta="Enviado" valor={fechaHora(d.enviada_at)} />
        <Dato etiqueta="Destinatarios" valor={String(d.destinatarios)} />
        {d.lecturas && <Dato etiqueta="Lo recibieron en la bandeja" valor={String(d.lecturas.recibieron)} />}
        {d.lecturas && <Dato etiqueta="Lo leyeron" valor={String(d.lecturas.leyeron)} />}
        {d.enlace && <Dato etiqueta="Enlace" valor={d.enlace} />}
        {d.autor && <Dato etiqueta="Escrito por" valor={d.autor} />}
      </Tarjeta>
    </Pantalla>
  );
}
