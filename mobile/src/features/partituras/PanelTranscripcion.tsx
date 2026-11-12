import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { View } from 'react-native';

import { Aviso, Boton, Subtitulo, Tenue } from '@/components/ui';
import { api } from '@/lib/api';
import { elegirDocumento } from '@/lib/archivos';

import { consultarTranscripcion, estadoTranscriptor, pedirTranscripcion, subirGrabacion, TEXTO_ESTADO, type Transcripcion } from './transcripcion';

const ESPERA_MS = 3000;

/**
 * Transcripción automática en la ficha del toque (solo administración). Si el servicio no está
 * configurado, lo dice y no muestra los botones.
 */
export function PanelTranscripcion({ slug, tieneAudio, onBorrador }: { slug: string; tieneAudio: boolean; onBorrador?: () => void }) {
  const qc = useQueryClient();
  const estado = useQuery({ queryKey: ['partituras-transcriptor'], queryFn: estadoTranscriptor, staleTime: 10 * 60_000 });
  const [tr, setTr] = useState<Transcripcion | null>(null);
  const [subiendo, setSubiendo] = useState<number | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);

  // Mientras el servicio trabaja, se consulta cada pocos segundos hasta que termine.
  useEffect(() => {
    if (!tr || tr.estado === 'listo' || tr.estado === 'error') return;
    const t = setTimeout(() => {
      consultarTranscripcion(slug, tr.id)
        .then((nuevo) => {
          setTr(nuevo);
          if (nuevo.estado === 'listo') {
            setAviso(TEXTO_ESTADO.listo);
            void qc.invalidateQueries({ queryKey: ['partitura', slug] });
            onBorrador?.();
          }
        })
        .catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo consultar el estado.'));
    }, ESPERA_MS);
    return () => clearTimeout(t);
  }, [tr, slug, qc, onBorrador]);

  if (estado.isPending) return null;
  const habilitada = estado.data?.transcripcion.habilitada ?? false;
  if (!habilitada) {
    return (
      <View style={{ gap: 4 }}>
        <Subtitulo>Transcripción automática</Subtitulo>
        <Tenue>{estado.data?.transcripcion.mensaje ?? 'La transcripción automática no está activada en este sistema.'}</Tenue>
      </View>
    );
  }

  const subir = async (transcribir: boolean) => {
    setAviso(null);
    const archivo = await elegirDocumento(['audio/*']);
    if (!archivo) return;
    setSubiendo(transcribir ? 2 : 1);
    try {
      const r = await subirGrabacion(slug, archivo, transcribir, undefined);
      void qc.invalidateQueries({ queryKey: ['partitura', slug] });
      if (r.transcripcion) setTr(r.transcripcion);
      else setAviso(r.aviso ?? 'La grabación quedó subida.');
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo subir la grabación.');
    } finally {
      setSubiendo(null);
    }
  };

  const transcribirActual = async () => {
    setAviso(null);
    setSubiendo(2);
    try {
      const partitura = await qc.fetchQuery({ queryKey: ['partitura', slug], queryFn: () => api<{ score: { audio_ref?: { path?: string | null } | null } | null }>(`partituras/${slug}`) });
      const path = partitura.score?.audio_ref?.path;
      if (!path) return setAviso('Primero subí la grabación del toque.');
      setTr(await pedirTranscripcion(slug, path));
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo pedir la transcripción.');
    } finally {
      setSubiendo(null);
    }
  };

  const ocupado = tr !== null && (tr.estado === 'pendiente' || tr.estado === 'procesando');

  return (
    <View style={{ gap: 6 }}>
      <Subtitulo>Transcripción automática</Subtitulo>
      <Tenue>Escucha la grabación y escribe un borrador. No se publica solo: lo revisás en el editor web.</Tenue>
      <Boton titulo="Subir grabación y transcribir" icono="graphic-eq" cargando={subiendo === 2} deshabilitado={subiendo !== null || ocupado} onPress={() => void subir(true)} />
      {tieneAudio && (
        <Boton titulo="Transcribir la grabación del toque" icono="graphic-eq" variante="secundario" deshabilitado={subiendo !== null || ocupado} onPress={() => void transcribirActual()} />
      )}
      {subiendo === 1 && <Tenue>Subiendo grabación…</Tenue>}
      {tr && (
        <Tenue>
          {TEXTO_ESTADO[tr.estado]}
          {ocupado ? ` ${tr.progreso}%` : ''}
          {tr.estado === 'listo' && tr.resumen ? ` ${tr.resumen.compases} compases, ${tr.resumen.golpes} golpes${tr.resumen.dudosos ? `, ${tr.resumen.dudosos} para revisar` : ''}.` : ''}
          {tr.estado === 'error' && tr.error ? ` ${tr.error}` : ''}
        </Tenue>
      )}
      {aviso && <Aviso texto={aviso} tono={tr?.estado === 'error' ? 'peligro' : 'info'} />}
    </View>
  );
}
