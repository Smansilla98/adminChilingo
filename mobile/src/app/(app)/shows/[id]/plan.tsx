import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { Campo, CampoHora, Seccion, SelectorLista } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Boton, Cargando, ErrorVista, Pantalla, Tenue } from '@/components/ui';
import type { CatalogoPlan, Logistica, ShowDetalle } from '@/features/shows/tipos';
import { api } from '@/lib/api';
import { useCatalogo, useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

type TemaEditable = { programa_ritmo_id: number | null; titulo: string; nombre: string };
type InstrumentoEditable = { inventario_item_id: number | null; descripcion: string; cantidad: number; nombre: string };

/** Temas (toques del programa o libres), logística del traslado e instrumentos que van. */
export default function PlanDelShow() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<ShowDetalle>('shows', id);
  const cat = useCatalogo<CatalogoPlan>('shows/plan-catalogo', 1);
  if (q.isPending || cat.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  return <EditorPlan id={id} show={q.data} cat={cat.data} />;
}

function EditorPlan({ id, show, cat: catalogo }: { id: string; show: ShowDetalle; cat: CatalogoPlan }) {
  const cat = { data: catalogo };
  const [temas, setTemas] = useState<TemaEditable[]>(() => show.temas.map((t) => ({ programa_ritmo_id: t.programa_ritmo_id, titulo: t.titulo ?? '', nombre: t.nombre })));
  const [instrumentos, setInstrumentos] = useState<InstrumentoEditable[]>(() => show.instrumentos.map((i) => ({ inventario_item_id: i.inventario_item_id, descripcion: i.descripcion ?? '', cantidad: i.cantidad, nombre: i.nombre })));
  const [logistica, setLogistica] = useState<Logistica>(() => ({ ...show.logistica }));
  const [temaLibre, setTemaLibre] = useState('');
  const [instLibre, setInstLibre] = useState('');

  const opts = { invalidar: ['shows'] };
  const guardarTemas = useOperacion(() => api(`shows/${id}/temas`, { method: 'PUT', body: { temas: temas.map(({ nombre: _n, ...t }) => ({ ...t, titulo: t.titulo || null })) } }), { ...opts, exito: 'Temas guardados' });
  const guardarInst = useOperacion(() => api(`shows/${id}/instrumentos`, { method: 'PUT', body: { instrumentos: instrumentos.map(({ nombre: _n, ...i }) => ({ ...i, descripcion: i.descripcion || null })) } }), { ...opts, exito: 'Instrumentos guardados' });
  const guardarLog = useOperacion(() => api(`shows/${id}/logistica`, {
    method: 'PUT',
    body: { punto_encuentro: logistica.punto_encuentro || null, hora_encuentro: logistica.hora_encuentro || null, transporte: logistica.transporte || null, logistica_notas: logistica.notas || null },
  }), { ...opts, exito: 'Logística guardada' });

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Preparar el show' }} />
      <Seccion titulo="Lista de temas" ayuda="En el orden en que se tocan.">
        {temas.length === 0 && <Tenue>Todavía no hay temas.</Tenue>}
        {temas.map((t, i) => (
          <ItemLista key={`${t.nombre}-${i}`} icono="music-note" titulo={`${i + 1}. ${t.nombre}`} detalle="Tocá para quitar" colorIcono={C.acento}
            onPress={() => setTemas((x) => x.filter((_, j) => j !== i))} />
        ))}
        <SelectorLista etiqueta="Agregar toque del programa" valor={null} valorEtiqueta=""
          opciones={cat.data.toques.map((t) => ({ valor: t.id, etiqueta: t.nombre, detalle: t.anio ? `${t.anio}° año` : undefined }))}
          onChange={(v) => {
            const toque = cat.data.toques.find((t) => t.id === v);
            if (toque) setTemas((x) => [...x, { programa_ritmo_id: toque.id, titulo: '', nombre: toque.nombre }]);
          }} />
        <Campo etiqueta="U otro tema" valor={temaLibre} onChange={setTemaLibre} />
        {temaLibre.trim() !== '' && (
          <Boton titulo="Agregar tema" icono="add" variante="secundario" onPress={() => { setTemas((x) => [...x, { programa_ritmo_id: null, titulo: temaLibre.trim(), nombre: temaLibre.trim() }]); setTemaLibre(''); }} />
        )}
        <Boton titulo="Guardar temas" icono="save" cargando={guardarTemas.isPending} onPress={() => guardarTemas.mutate()} />
      </Seccion>

      <Seccion titulo="Logística">
        <Campo etiqueta="Punto de encuentro" valor={logistica.punto_encuentro ?? ''} onChange={(x) => setLogistica((l) => ({ ...l, punto_encuentro: x }))} />
        <CampoHora etiqueta="Hora de encuentro" opcional valor={logistica.hora_encuentro ?? ''} onChange={(x) => setLogistica((l) => ({ ...l, hora_encuentro: x }))} />
        <Campo etiqueta="Transporte" placeholder="Micro, combis, cada uno por su cuenta…" valor={logistica.transporte ?? ''} onChange={(x) => setLogistica((l) => ({ ...l, transporte: x }))} />
        <Campo etiqueta="Notas" multilinea valor={logistica.notas ?? ''} onChange={(x) => setLogistica((l) => ({ ...l, notas: x }))} />
        <Boton titulo="Guardar logística" icono="save" cargando={guardarLog.isPending} onPress={() => guardarLog.mutate()} />
      </Seccion>

      <Seccion titulo="Instrumentos que van" ayuda="Del inventario solo aparecen los que no están prestados.">
        <View style={{ gap: E.s }}>
          {instrumentos.map((it, i) => (
            <ItemLista key={`${it.nombre}-${i}`} icono="inventory-2" titulo={`${it.cantidad} × ${it.nombre}`} detalle="Tocá para quitar"
              onPress={() => setInstrumentos((x) => x.filter((_, j) => j !== i))} />
          ))}
        </View>
        <SelectorLista etiqueta="Agregar del inventario" valor={null} valorEtiqueta=""
          opciones={cat.data.instrumentos.map((t) => ({ valor: t.id, etiqueta: t.nombre, detalle: [t.codigo, t.sede].filter(Boolean).join(' · ') || undefined }))}
          onChange={(v) => {
            const item = cat.data.instrumentos.find((t) => t.id === v);
            if (item) setInstrumentos((x) => [...x, { inventario_item_id: item.id, descripcion: '', cantidad: 1, nombre: item.nombre }]);
          }} />
        <Campo etiqueta="U otro (ej. 4 repiques)" valor={instLibre} onChange={setInstLibre} />
        {instLibre.trim() !== '' && (
          <Boton titulo="Agregar instrumento" icono="add" variante="secundario" onPress={() => { setInstrumentos((x) => [...x, { inventario_item_id: null, descripcion: instLibre.trim(), cantidad: 1, nombre: instLibre.trim() }]); setInstLibre(''); }} />
        )}
        <Boton titulo="Guardar instrumentos" icono="save" cargando={guardarInst.isPending} onPress={() => guardarInst.mutate()} />
      </Seccion>
    </Pantalla>
  );
}
