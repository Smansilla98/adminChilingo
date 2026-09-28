import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import type { Show } from '@/features/agenda/tipos';
import { formatearFecha } from '@/lib/formato';
import { usePuede } from '@/lib/permisos';
import { C } from '@/lib/theme';

export default function Shows() {
  const [cuando, setCuando] = useState<'proximos' | 'todos'>('proximos');
  const puedeCrear = usePuede('shows.manage');
  return (
    <>
      <Stack.Screen options={{ title: 'Shows' }} />
      <ListaPaginada<Show>
        ruta="shows"
        filtros={{ proximos: cuando === 'proximos' ? 1 : undefined }}
        buscar
        placeholderBusqueda="Título o lugar"
        vacio="No hay shows."
        iconoVacio="theater-comedy"
        cabecera={<FiltrosChips opciones={[{ valor: 'proximos', etiqueta: 'Próximos' }, { valor: 'todos', etiqueta: 'Todos' }]} valor={cuando} onChange={(v) => setCuando(v ?? 'proximos')} todos={false} />}
        onCrear={puedeCrear ? () => router.push('/shows/nuevo' as never) : undefined}
        textoCrear="Nuevo show"
        render={(s) => (
          <ItemLista
            icono="theater-comedy"
            titulo={s.titulo}
            subtitulo={`${formatearFecha(s.fecha)}${s.hora_inicio ? ` · ${s.hora_inicio}` : ''}${s.lugar ? ` · ${s.lugar}` : ''}`}
            detalle={s.bloques.map((b) => b.nombre).join(' · ') || null}
            derecha={s.convocatoria_abierta ? <Chip texto="Abierta" color={C.exito} /> : undefined}
            onPress={() => router.push({ pathname: '/shows/[id]', params: { id: String(s.id) } } as never)}
          />
        )}
      />
    </>
  );
}
