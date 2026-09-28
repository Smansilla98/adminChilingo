import { Image } from 'expo-image';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Linking, View } from 'react-native';

import { confirmar } from '@/components/feedback';
import { Progreso } from '@/components/form';
import { Acciones, Aviso, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Tarjeta, Texto } from '@/components/ui';
import { api, headersSesion, urlApi } from '@/lib/api';
import { descargarYAbrir } from '@/lib/archivos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

import type { MaterialBiblioteca } from './index';

export default function MaterialDetalle() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<MaterialBiblioteca>('biblioteca', id);
  const [descarga, setDescarga] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const visibilidad = useOperacion(() => api(`biblioteca/${id}/visibilidad`, { method: 'POST' }), { exito: 'Visibilidad actualizada', invalidar: ['biblioteca'] });
  const eliminar = useOperacion(() => api(`biblioteca/${id}`, { method: 'DELETE' }), { exito: 'Material eliminado', invalidar: ['biblioteca'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const m = q.data;

  const abrir = async () => {
    setError(null);
    setDescarga(0);
    try {
      await descargarYAbrir(`biblioteca/${m.id}/archivo`, { nombre: m.nombre_original ?? `material-${m.id}`, onProgreso: setDescarga });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo abrir el archivo.');
    } finally {
      setDescarga(null);
    }
  };

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Material' }} />
      <Encabezado icono="local-library" titulo={m.titulo} subtitulo={[m.tipo_nombre, m.autor].filter(Boolean).join(' · ')} chips={m.estado === 'oculto' ? <Chip texto="Oculto" /> : undefined} />
      {m.tipo === 'imagen' && m.tiene_archivo && (
        <View style={{ aspectRatio: 1, borderRadius: 12, overflow: 'hidden', backgroundColor: C.superficie }}>
          <Image source={{ uri: urlApi(`biblioteca/${m.id}/archivo`), headers: headersSesion() }} style={{ flex: 1 }} contentFit="contain" accessibilityLabel={m.titulo} />
        </View>
      )}
      <Acciones>
        {m.tiene_archivo && <Boton titulo="Ver / descargar" icono="download" cargando={descarga !== null} onPress={() => void abrir()} />}
        {!!m.url && <Boton titulo="Abrir enlace" icono="open-in-new" variante="secundario" onPress={() => Linking.openURL(m.url!)} />}
        {m.acciones.moderar && <Boton titulo={m.estado === 'oculto' ? 'Publicar' : 'Ocultar'} icono={m.estado === 'oculto' ? 'visibility' : 'visibility-off'} variante="secundario" cargando={visibilidad.isPending} onPress={() => visibilidad.mutate()} />}
        {m.acciones.moderar && <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => { if (await confirmar({ titulo: `¿Eliminar "${m.titulo}"?`, mensaje: 'Se borra el archivo de la biblioteca.', accion: 'Eliminar' })) eliminar.mutate(); }} />}
      </Acciones>
      {descarga !== null && <Progreso fraccion={descarga} texto="Descargando…" />}
      {error && <Aviso tono="peligro" texto={error} />}
      <Tarjeta>
        {m.descripcion && <Texto>{m.descripcion}</Texto>}
        <Dato etiqueta="Toque" valor={m.toque ? `${m.toque.nombre}${m.instrumento ? ` · ${m.instrumento}` : ''}` : null} />
        <Dato etiqueta="Archivo" valor={m.nombre_original ? `${m.nombre_original}${m.bytes ? ` · ${(m.bytes / 1048576).toFixed(1)} MB` : ''}` : null} />
        <Dato etiqueta="Publicado" valor={m.fecha ? new Date(m.fecha).toLocaleDateString('es-AR') : null} />
      </Tarjeta>
      {m.tags.length > 0 && <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.xs }}>{m.tags.map((t) => <Chip key={t.slug} texto={`#${t.nombre}`} />)}</View>}
    </Pantalla>
  );
}
