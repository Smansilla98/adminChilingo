import { router, Stack } from 'expo-router';

import { ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { COLOR_ESTADO, type Difusion } from '@/features/difusion/tipos';
import { formatearFecha } from '@/lib/formato';

/** Historial de mensajes a grupos, con cuántos lo recibieron y leyeron. */
export default function Difusiones() {
  return (
    <>
      <Stack.Screen options={{ title: 'Mensajes a grupos' }} />
      <ListaPaginada<Difusion>
        ruta="difusiones"
        vacio="Todavía no mandaste mensajes a grupos."
        iconoVacio="campaign"
        onCrear={() => router.push('/difusion/nuevo' as never)}
        textoCrear="Nuevo mensaje"
        render={(d) => {
          const fecha = d.enviada_at ?? d.programada_para ?? d.creada_at;
          return (
            <ItemLista
              icono="campaign"
              titulo={d.titulo}
              subtitulo={`${fecha ? formatearFecha(fecha.slice(0, 10)) : ''} · ${d.destino}`}
              detalle={d.lecturas ? `${d.destinatarios} lo recibieron · ${d.lecturas.leyeron} lo leyeron` : d.quienes}
              derecha={<Chip texto={d.estado_texto} color={COLOR_ESTADO[d.estado]} />}
              onPress={() => router.push({ pathname: '/difusion/[id]', params: { id: String(d.id) } } as never)}
            />
          );
        }}
      />
    </>
  );
}
