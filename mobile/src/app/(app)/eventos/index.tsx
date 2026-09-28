import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import type { CatalogoEventos, Evento } from '@/features/agenda/tipos';
import { formatearFecha } from '@/lib/formato';
import { usePuede } from '@/lib/permisos';
import { useCatalogo } from '@/lib/recursos';
import { C } from '@/lib/theme';

export default function Eventos() {
  const [cuando, setCuando] = useState<'proximos' | 'historial'>('proximos');
  const [tipo, setTipo] = useState<string | null>(null);
  const [sede, setSede] = useState<number | null>(null);
  const cat = useCatalogo<CatalogoEventos>('eventos/catalogo', 1);
  const puedeCrear = usePuede('eventos.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Eventos' }} />
      <ListaPaginada<Evento>
        ruta="eventos"
        filtros={{ historial: cuando === 'historial' ? 1 : undefined, tipo_evento: tipo, sede_id: sede }}
        buscar
        placeholderBusqueda="Título o descripción"
        vacio={cuando === 'proximos' ? 'No hay eventos próximos.' : 'No hay eventos pasados.'}
        iconoVacio="celebration"
        cabecera={
          <>
            <FiltrosChips opciones={[{ valor: 'proximos', etiqueta: 'Próximos' }, { valor: 'historial', etiqueta: 'Pasados' }]} valor={cuando} onChange={(v) => setCuando(v ?? 'proximos')} todos={false} />
            <FiltrosChips opciones={Object.entries(cat.data?.tipos ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={tipo} onChange={setTipo} todos="Todo tipo" />
            <FiltrosChips opciones={(cat.data?.sedes ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={setSede} todos="Todas las sedes" />
          </>
        }
        onCrear={puedeCrear ? () => router.push('/eventos/nuevo' as never) : undefined}
        textoCrear="Nuevo evento"
        render={(e) => (
          <ItemLista
            icono="celebration"
            titulo={e.titulo}
            subtitulo={`${formatearFecha(e.fecha)}${e.hora_inicio ? ` · ${e.hora_inicio}` : ''}`}
            detalle={[e.bloque?.nombre ?? e.sede?.nombre ?? 'Toda la escuela', e.profesor?.nombre].filter(Boolean).join(' · ')}
            derecha={<Chip texto={e.tipo_nombre} color={C.acento} />}
            onPress={() => router.push({ pathname: '/eventos/[id]', params: { id: String(e.id) } } as never)}
          />
        )}
      />
    </>
  );
}
