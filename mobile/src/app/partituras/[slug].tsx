import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Linking } from 'react-native';

import { Boton, Cargando, ErrorVista, Pantalla, Subtitulo, Tenue, Titulo } from '@/components/ui';
import { api } from '@/lib/api';

interface Toque {
  nombre: string;
  anio: number;
  autor: string | null;
  resumen: string | null;
  visor_url: string;
  pdf_url: string | null;
  partes: { instrumento: string; nombre: string; url: string }[];
  videos: { clave: string; url: string }[];
}

/**
 * El visor interactivo (VexFlow, audio, zoom) es la vista web existente: se abre a
 * pantalla completa en el navegador del sistema, que permite girar a horizontal y hacer zoom.
 */
export default function Partitura() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const q = useQuery({ queryKey: ['partitura', slug], queryFn: () => api<Toque>(`partituras/${slug}`), staleTime: 60 * 60_000 });

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const t = q.data!;
  const abrir = (url: string) => WebBrowser.openBrowserAsync(url, { presentationStyle: WebBrowser.WebBrowserPresentationStyle.FULL_SCREEN });

  return (
    <Pantalla>
      <Stack.Screen options={{ title: t.nombre }} />
      <Titulo>{t.nombre}</Titulo>
      <Tenue>{t.anio}° año{t.autor ? ` · ${t.autor}` : ''}</Tenue>
      {t.resumen && <Tenue>{t.resumen}</Tenue>}
      <Boton titulo="Abrir partitura y audio" icono="music-note" onPress={() => abrir(t.visor_url)} grande />
      <Tenue>Tip: girá el teléfono para ver más compases.</Tenue>
      {t.pdf_url && <Boton titulo="Partitura original (PDF)" icono="picture-as-pdf" variante="secundario" onPress={() => abrir(t.pdf_url!)} />}
      {t.partes.length > 0 && (
        <>
          <Subtitulo>Por instrumento</Subtitulo>
          {t.partes.map((p) => <Boton key={p.instrumento} titulo={p.nombre} variante="secundario" onPress={() => abrir(p.url)} />)}
        </>
      )}
      {t.videos.length > 0 && (
        <>
          <Subtitulo>Videos</Subtitulo>
          {t.videos.map((v) => <Boton key={v.clave} titulo={`Video ${v.clave.replace(/_/g, ' ')}`} icono="play-circle" variante="secundario" onPress={() => Linking.openURL(v.url)} />)}
        </>
      )}
    </Pantalla>
  );
}
