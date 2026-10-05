import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable } from 'react-native';

import { FiltrosChips, ListaPaginada } from '@/components/lista';
import { Tarjeta } from '@/components/ui';
import { useCatalogo } from '@/lib/recursos';
import { FilaFoto } from '@/features/archivo/Miniatura';
import type { CatalogoArchivo, FotoResumen } from '@/features/archivo/tipos';

/** Todas las fotos que la cuenta puede gestionar, con filtros por estado y faltantes. */
export default function FotosDelArchivo() {
  const cat = useCatalogo<CatalogoArchivo>('archivo/catalogo', 1);
  const [estado, setEstado] = useState<string | null>(null);
  const [sin, setSin] = useState<string | null>(null);

  return (
    <>
      <Stack.Screen options={{ title: 'Fotos del archivo' }} />
      <ListaPaginada<FotoResumen>
        ruta="archivo/gestion/fotos"
        filtros={{ estado, sin }}
        buscar
        placeholderBusqueda="Título, lugar, persona o etiqueta"
        vacio="No hay fotos con esos filtros."
        iconoVacio="collections"
        cabecera={
          <>
            <FiltrosChips opciones={Object.entries(cat.data?.estados ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={estado} onChange={setEstado} todos="Todo estado" />
            <FiltrosChips opciones={[{ valor: 'fecha', etiqueta: 'Sin fecha' }, { valor: 'credito', etiqueta: 'Sin crédito' }, { valor: 'descripcion', etiqueta: 'Sin descripción' }, { valor: 'personas', etiqueta: 'Sin personas' }]} valor={sin} onChange={setSin} todos="Con o sin datos" />
          </>
        }
        render={(f) => (
          <Pressable onPress={() => router.push({ pathname: '/archivo/fotos/[id]', params: { id: String(f.id) } } as never)} accessibilityRole="button" accessibilityLabel={f.titulo}>
            <Tarjeta>
              <FilaFoto foto={f} detalle={[f.acontecimiento?.titulo, f.sede].filter(Boolean).join(' · ') || null} />
            </Tarjeta>
          </Pressable>
        )}
      />
    </>
  );
}
