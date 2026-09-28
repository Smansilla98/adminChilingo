import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { Campo, CampoArchivo, ErrorFormulario, Progreso, requeridos, Seccion, SelectorLista, useFormulario } from '@/components/form';
import { Boton, Pantalla } from '@/components/ui';
import type { ArchivoLocal } from '@/lib/archivos';
import { subirArchivo } from '@/lib/archivos';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import type { CatalogoBiblioteca } from './index';

/** Publicar un material: archivo (foto, video, audio, PDF) o enlace, con hashtags. */
export default function SubirMaterial() {
  const cat = useCatalogo<CatalogoBiblioteca>('biblioteca/catalogo', 1);
  const [progreso, setProgreso] = useState<number | null>(null);
  const [controlador, setControlador] = useState<AbortController | null>(null);
  const f = useFormulario({ titulo: '', descripcion: '', hashtags: '', url: '', toque: null as string | null, instrumento: null as string | null, archivo: null as ArchivoLocal | null });
  const { valores: v, set, errores: e } = f;
  const publicar = useOperacion(
    async (d: typeof v) => {
      const ctrl = new AbortController();
      setControlador(ctrl);
      setProgreso(0);
      try {
        return await subirArchivo<{ data: { id: number } }>('biblioteca', d.archivo, {
          campo: 'archivo',
          datos: { titulo: d.titulo, descripcion: d.descripcion || null, hashtags: d.hashtags || null, url: d.url || null, toque: d.toque, instrumento: d.toque ? d.instrumento : null },
          onProgreso: setProgreso,
          signal: ctrl.signal,
        });
      } finally {
        setProgreso(null);
        setControlador(null);
      }
    },
    { exito: '¡Listo! Tu material ya está en la biblioteca', invalidar: ['biblioteca'], alTerminar: (r) => router.replace({ pathname: '/biblioteca/[id]', params: { id: String(r.data.id) } } as never) },
  );

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Subir material' }} />
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Material">
        <Campo etiqueta="Título" requerido valor={v.titulo} onChange={(x) => set('titulo', x)} error={e.titulo} />
        <CampoArchivo etiqueta="Archivo" archivo={v.archivo} onChange={(a) => set('archivo', a)} error={e.archivo} maxMb={cat.data?.max_mb ?? 100}
          tipos={['image/*', 'video/*', 'audio/*', 'application/pdf']} ayuda="Foto, video, audio o PDF. O pegá un enlace abajo." />
        {progreso !== null && <Progreso fraccion={progreso} texto="Subiendo…" />}
        {controlador && <Boton titulo="Cancelar subida" icono="close" variante="secundario" onPress={() => controlador.abort()} />}
        <Campo etiqueta="Enlace (opcional)" valor={v.url} onChange={(x) => set('url', x.trim())} error={e.url} teclado="url" autoCapitalize="none" placeholder="https://" />
        <Campo etiqueta="Descripción" valor={v.descripcion} onChange={(x) => set('descripcion', x)} error={e.descripcion} multilinea />
        <Campo etiqueta="Hashtags" valor={v.hashtags} onChange={(x) => set('hashtags', x)} error={e.hashtags} placeholder="#corso #muestra" autoCapitalize="none" />
      </Seccion>
      <Seccion titulo="Programa (opcional)">
        <SelectorLista etiqueta="Toque" valor={v.toque} onChange={(x) => set('toque', x)} error={e.toque} permitirVacio placeholder="Sin toque"
          opciones={(cat.data?.toques ?? []).map((t) => ({ valor: t.slug, etiqueta: t.nombre }))} />
        {!!v.toque && <SelectorLista etiqueta="Instrumento" valor={v.instrumento} onChange={(x) => set('instrumento', x)} error={e.instrumento} permitirVacio placeholder="Sin instrumento"
          opciones={Object.entries(cat.data?.instrumentos ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />}
      </Seccion>
      <Boton titulo="Publicar" icono="cloud-upload" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => publicar.mutateAsync(d), (d) => ({ ...requeridos(d, { titulo: 'El título' }), ...(!d.archivo && !d.url ? { archivo: 'Subí un archivo o pegá un enlace.' } : {}) }))} />
    </Pantalla>
  );
}
