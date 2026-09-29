import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Linking, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tenue, Texto, Titulo } from '@/components/ui';
import { api } from '@/lib/api';
import { descargarYAbrir } from '@/lib/archivos';
import { esEnlaceExterno } from '@/lib/enlaces';
import { usePuede } from '@/lib/permisos';
import { C, E } from '@/lib/theme';

interface Lectura {
  tempo: number;
  compas: string;
  instrumentos: { id: string; nombre: string }[];
  secciones: { nombre: string; repetir: number; compases: { voces: Record<string, string> }[] }[];
}

interface Toque {
  nombre: string;
  anio: number;
  autor: string | null;
  resumen: string | null;
  tiene_pdf: boolean;
  publicado?: boolean;
  lectura: Lectura | null;
  videos: { clave: string; url: string }[];
}

/** La partitura se lee con los datos de la API. No abre el panel web. */
export default function Partitura() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const q = useQuery({ queryKey: ['partitura', slug], queryFn: () => api<Toque>(`partituras/${slug}`), staleTime: 60 * 60_000 });
  const [instrumento, setInstrumento] = useState<string | null>(null);
  const [descarga, setDescarga] = useState(false);
  const [errorPdf, setErrorPdf] = useState<string | null>(null);
  const admin = usePuede('partituras.admin');
  const qc = useQueryClient();

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const t = q.data!;
  const lectura = t.lectura;
  const elegido = instrumento && lectura?.instrumentos.some((i) => i.id === instrumento)
    ? instrumento
    : lectura?.instrumentos[0]?.id ?? null;
  const videos = t.videos.filter((v) => esEnlaceExterno(v.url));

  const bajarPdf = async () => {
    setErrorPdf(null);
    setDescarga(true);
    try {
      await descargarYAbrir(`partituras/${slug}/archivo`, { nombre: `${t.nombre}.pdf` });
    } catch (e) {
      setErrorPdf(e instanceof Error ? e.message : 'No se pudo abrir el PDF.');
    } finally {
      setDescarga(false);
    }
  };

  return (
    <Pantalla>
      <Stack.Screen options={{ title: t.nombre }} />
      <Titulo>{t.nombre}</Titulo>
      <Tenue>{t.anio}° año{t.autor ? ` · ${t.autor}` : ''}{lectura ? ` · ${lectura.compas}${lectura.tempo ? ` · ${lectura.tempo} bpm` : ''}` : ''}</Tenue>
      {t.resumen && <Tenue>{t.resumen}</Tenue>}
      {admin && (
        <Boton
          titulo={t.publicado === false ? 'Publicar toque' : 'Ocultar toque'}
          icono="visibility"
          variante="secundario"
          onPress={() => void api(`partituras/${slug}`, { method: 'PUT', body: { publicado: t.publicado === false } }).then(() => qc.invalidateQueries({ queryKey: ['partitura', slug] }))}
        />
      )}
      {t.tiene_pdf && <Boton titulo="Partitura original (PDF)" icono="picture-as-pdf" cargando={descarga} onPress={() => void bajarPdf()} />}
      {errorPdf && <Tenue>{errorPdf}</Tenue>}
      {lectura && lectura.instrumentos.length > 0 && (
        <>
          <Subtitulo>Parte</Subtitulo>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
            {lectura.instrumentos.map((i) => (
              <Pressable key={i.id} accessibilityRole="button" accessibilityState={{ selected: i.id === elegido }} onPress={() => setInstrumento(i.id)}>
                <Chip texto={i.nombre} color={i.id === elegido ? C.fondo : C.texto} fondo={i.id === elegido ? C.acento : C.superficie2} />
              </Pressable>
            ))}
          </ScrollView>
          {lectura.secciones.map((sec) => (
            <View key={sec.nombre} style={{ gap: E.s }}>
              <Subtitulo>{sec.nombre}{sec.repetir > 1 ? ` ×${sec.repetir}` : ''}</Subtitulo>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
                {sec.compases.map((compas, n) => (
                  <View key={`${sec.nombre}-${n}`} style={s.compas}>
                    <Tenue>{n + 1}</Tenue>
                    <Texto style={s.simbolos}>{elegido ? (compas.voces[elegido] || '·') : '·'}</Texto>
                  </View>
                ))}
              </ScrollView>
            </View>
          ))}
          <Tenue>● golpe · · silencio · &gt; acentuado · ✕ chapa · — tapado</Tenue>
        </>
      )}
      {!lectura && !t.tiene_pdf && <Tenue>Este toque todavía no tiene partitura cargada.</Tenue>}
      {videos.length > 0 && (
        <>
          <Subtitulo>Videos</Subtitulo>
          {videos.map((v) => <Boton key={v.clave} titulo={`Video ${v.clave.replace(/_/g, ' ')}`} icono="play-circle" variante="secundario" onPress={() => Linking.openURL(v.url)} />)}
        </>
      )}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  compas: { minWidth: 88, padding: E.s, borderRadius: 10, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, gap: 4 },
  simbolos: { letterSpacing: 1 },
});
