import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Boton, Chip } from '@/components/ui';
import { type CatalogoCompras, COLOR_ESTADO_ORDEN, type Orden } from '@/features/compras/FormOrden';
import { usePuede } from '@/lib/permisos';
import { useCatalogo } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function Compras() {
  const [estado, setEstado] = useState<string | null>(null);
  const [sede, setSede] = useState<number | null>(null);
  const cat = useCatalogo<CatalogoCompras>('compras/catalogo', 1);
  const puedeCrear = usePuede('compras.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Compras' }} />
      <ListaPaginada<Orden>
        ruta="compras"
        filtros={{ estado, sede_id: sede }}
        buscar
        placeholderBusqueda="Ítem o justificación"
        vacio="No hay órdenes de compra."
        iconoVacio="shopping-cart"
        cabecera={
          <>
            <Boton titulo="Plan de compras" icono="insights" variante="secundario" onPress={() => router.push('/compras/plan' as never)} />
            <FiltrosChips opciones={Object.entries(cat.data?.estados ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={estado} onChange={setEstado} />
            <FiltrosChips opciones={(cat.data?.sedes ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={setSede} todos="Todas las sedes" />
          </>
        }
        onCrear={puedeCrear ? () => router.push('/compras/nueva' as never) : undefined}
        textoCrear="Nueva orden"
        render={(o) => (
          <ItemLista
            icono="shopping-cart"
            colorIcono={COLOR_ESTADO_ORDEN[o.estado] ?? C.acento}
            titulo={`#${o.id} · ${moneda(o.total_estimado)}`}
            subtitulo={`${o.sede?.nombre ?? ''} · ${o.motivo_nombre}`}
            detalle={`${o.cantidad_items ?? 0} ítems${o.fecha_objetivo ? ` · para ${o.fecha_objetivo}` : ''}`}
            derecha={<Chip texto={o.estado_nombre} color={COLOR_ESTADO_ORDEN[o.estado] ?? C.tenue} />}
            onPress={() => router.push({ pathname: '/compras/[id]', params: { id: String(o.id) } } as never)}
          />
        )}
      />
    </>
  );
}
