import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { useCatalogo } from '@/lib/recursos';
import { C } from '@/lib/theme';

export interface MaterialBiblioteca {
  id: number; titulo: string; descripcion: string | null; tipo: string; tipo_nombre: string; autor: string | null; estado: string;
  tiene_archivo: boolean; url: string | null; mime: string | null; bytes: number | null; nombre_original: string | null;
  toque: { nombre: string; slug: string } | null; instrumento: string | null; tags: { nombre: string; slug: string }[]; fecha: string | null;
  acciones: { moderar: boolean };
}
export interface CatalogoBiblioteca { tipos: Record<string, string>; instrumentos: Record<string, string>; toques: { id: number; nombre: string; slug: string }[]; tags: { nombre: string; slug: string; usos: number }[]; modera: boolean; max_mb: number }

const ICONO: Record<string, 'image' | 'movie' | 'audiotrack' | 'picture-as-pdf' | 'link' | 'insert-drive-file'> = { imagen: 'image', video: 'movie', audio: 'audiotrack', pdf: 'picture-as-pdf', enlace: 'link', otro: 'insert-drive-file' };

export default function Biblioteca() {
  const cat = useCatalogo<CatalogoBiblioteca>('biblioteca/catalogo', 1);
  const [tipo, setTipo] = useState<string | null>(null);
  const [tag, setTag] = useState<string | null>(null);
  const [toque, setToque] = useState<string | null>(null);
  const [estado, setEstado] = useState<string | null>(null);

  return (
    <>
      <Stack.Screen options={{ title: 'Biblioteca' }} />
      <ListaPaginada<MaterialBiblioteca>
        ruta="biblioteca"
        filtros={{ tipo, tag, toque, estado: estado ?? undefined }}
        buscar
        placeholderBusqueda="Título, autor, hashtag o toque"
        vacio="No hay materiales con esos filtros."
        iconoVacio="local-library"
        cabecera={
          <>
            <FiltrosChips opciones={Object.entries(cat.data?.tipos ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={tipo} onChange={setTipo} todos="Todo tipo" />
            <FiltrosChips opciones={(cat.data?.tags ?? []).map((t) => ({ valor: t.slug, etiqueta: `#${t.nombre}` }))} valor={tag} onChange={setTag} todos="Todos los tags" />
            <FiltrosChips opciones={(cat.data?.toques ?? []).map((t) => ({ valor: t.slug, etiqueta: t.nombre }))} valor={toque} onChange={setToque} todos="Todos los toques" />
            {cat.data?.modera && <FiltrosChips opciones={[{ valor: 'oculto', etiqueta: 'Ocultos' }, { valor: 'todos', etiqueta: 'Todos' }]} valor={estado} onChange={setEstado} todos="Publicados" />}
          </>
        }
        onCrear={() => router.push('/biblioteca/subir' as never)}
        textoCrear="Subir material"
        render={(m) => (
          <ItemLista
            icono={ICONO[m.tipo] ?? 'insert-drive-file'}
            colorIcono={m.estado === 'oculto' ? C.tenue : C.acento}
            titulo={m.titulo}
            subtitulo={[m.tipo_nombre, m.toque?.nombre, m.autor].filter(Boolean).join(' · ')}
            detalle={m.tags.map((t) => `#${t.nombre}`).join(' ') || null}
            derecha={m.estado === 'oculto' ? <Chip texto="Oculto" /> : undefined}
            onPress={() => router.push({ pathname: '/biblioteca/[id]', params: { id: String(m.id) } } as never)}
          />
        )}
      />
    </>
  );
}
