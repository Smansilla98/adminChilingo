import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams, router } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, AppState, Linking, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';

import { Aviso, Boton, Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tenue, Texto, Titulo } from '@/components/ui';
import { api } from '@/lib/api';
import { descargarYAbrir, elegirDocumento, subirArchivo } from '@/lib/archivos';
import { esEnlaceExterno } from '@/lib/enlaces';
import { MotorAudio } from '@/lib/partitura/audio';
import { GrabacionRef, VELOCIDADES } from '@/lib/partitura/grabacion';
import { cicloGolpe, ETIQUETA_GOLPE, golpesDe, INSTRUMENTOS, nombreDe, SIMBOLOS } from '@/lib/partitura/catalogo';
import {
  agregarCompas, agregarSeccion, conInstrumento, cuantizarVoz, escribirCelda, grillaDeVoz,
  expandirTimeline, paraGuardar, partituraVacia, renombrarSeccion, repeticion, segundosDeTicks, TEMPO_MAX, TEMPO_MIN, tempoDe, ticksDeCompas,
  type Score,
} from '@/lib/partitura/modelo';
import { ajustarInicio, posicionEnGrabacion, pulsoDeTiempo, tempoDeGrabacion, ticksDePulso } from '@/lib/partitura/sincro';
import { PanelTranscripcion } from '@/features/partituras/PanelTranscripcion';
import { subirGrabacion } from '@/features/partituras/transcripcion';
import { usePuede } from '@/lib/permisos';
import { C, E } from '@/lib/theme';

type ModoGrab = 'grabacion' | 'sintetizado' | 'ambos';

const MODOS_GRAB: { id: ModoGrab; titulo: string }[] = [
  { id: 'grabacion', titulo: 'Grabación' },
  { id: 'sintetizado', titulo: 'Sintetizado' },
  { id: 'ambos', titulo: 'Ambos' },
];

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
  score: Score | null;
  videos: { clave: string; url: string }[];
}

/** Leer, escribir y escuchar el toque. El audio y el PDF salen de la API. */
export default function Partitura() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const q = useQuery({ queryKey: ['partitura', slug], queryFn: () => api<Toque>(`partituras/${slug}`), staleTime: 60 * 60_000 });
  const admin = usePuede('partituras.admin');
  const qc = useQueryClient();
  const motor = useRef(new MotorAudio());
  const grabacion = useRef(new GrabacionRef());
  const marcaPrevia = useRef('');

  const [instrumento, setInstrumento] = useState<string | null>(null);
  const [editando, setEditando] = useState(false);
  const [score, setScore] = useState<Score | null>(null);
  const [sucio, setSucio] = useState(false);
  const [tempo, setTempo] = useState(88);
  const [metronomo, setMetronomo] = useState(false);
  const [conteo, setConteo] = useState(true);
  const [solo, setSolo] = useState<string | null>(null);
  const [mudas, setMudas] = useState<string[]>([]);
  const [sonando, setSonando] = useState(false);
  const [enConteo, setEnConteo] = useState(false);
  const [marca, setMarca] = useState<{ s: number; m: number } | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [guardando, setGuardando] = useState(false);
  const [rango, setRango] = useState<{ s: number; desde: number; hasta: number | null } | null>(null);
  const [progreso, setProgreso] = useState<string | null>(null);
  const [grabando, setGrabando] = useState(false);
  const [velocidadGrab, setVelocidadGrab] = useState(100);
  const [marcaGrab, setMarcaGrab] = useState<{ s: number; m: number } | null>(null);
  const [modoGrab, setModoGrab] = useState<ModoGrab>('grabacion');
  const modoGrabRef = useRef<ModoGrab>('grabacion');
  const syncRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    const m = motor.current;
    const g = grabacion.current;
    return () => { m.soltar(); g.soltar(); if (syncRef.current) clearInterval(syncRef.current); };
  }, []);

  // Al pasar a segundo plano se corta todo: el audio no puede seguir sonando sin la pantalla.
  useEffect(() => {
    const sub = AppState.addEventListener('change', (estado) => {
      if (estado === 'active') return;
      motor.current.parar(false);
      grabacion.current.pausar();
      if (syncRef.current) clearInterval(syncRef.current);
      syncRef.current = null;
      setSonando(false);
      setEnConteo(false);
      setMarca(null);
      setGrabando(false);
      marcaPrevia.current = '';
    });
    return () => sub.remove();
  }, []);

  // Toma la partitura del servidor cada vez que llega una nueva, salvo que haya
  // cambios sin guardar (ajuste de estado durante el render, sin efecto en cascada).
  const remoto = q.data?.score ?? null;
  const [origen, setOrigen] = useState<Score | null>(null);
  if (remoto && remoto !== origen && !sucio) {
    setOrigen(remoto);
    setScore(remoto);
    setTempo(Math.min(TEMPO_MAX, Math.max(TEMPO_MIN, remoto.tempo || 88)));
  }

  useEffect(() => {
    motor.current.mezcla = { solo, mudas };
  }, [solo, mudas]);

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const t = q.data!;
  const partitura = score ?? (t.score ? t.score : null);
  const lectura = t.lectura;
  const elegido = instrumento && (partitura?.instruments.some((i) => i.id === instrumento) || lectura?.instrumentos.some((i) => i.id === instrumento))
    ? instrumento
    : partitura?.instruments[0]?.id ?? lectura?.instrumentos[0]?.id ?? null;
  const videos = t.videos.filter((v) => esEnlaceExterno(v.url));

  const cambiar = (next: Score | null) => {
    if (!next) return;
    setScore(next);
    setSucio(true);
  };

  const tocar = async (seccion: number | null, tramo: { sectionIdx: number; desde: number; hasta: number } | null = null) => {
    const actual = partitura ?? partituraVacia(t.nombre, t.autor ?? '');
    setAviso(null);
    try {
      await motor.current.preparar((listos, total) => setProgreso(listos < total ? `Cargando sonidos ${listos} de ${total}…` : null));
      setProgreso(null);
      if (motor.current.faltan.length) setAviso(`Faltan ${motor.current.faltan.length} sonidos. Lo demás suena igual.`);
      motor.current.onFin = () => { setSonando(false); setEnConteo(false); setMarca(null); };
      motor.current.tocar(actual, { bpm: tempo, soloSeccion: tramo ? null : seccion, rango: tramo, conteo, metronomo, loop: seccion !== null || tramo !== null }, (p) => {
        const clave = `${p.conteo}:${p.sectionIdx}:${p.measureIdx}`;
        if (clave === marcaPrevia.current) return;
        marcaPrevia.current = clave;
        setSonando(true);
        setEnConteo(p.conteo);
        setMarca(p.sectionIdx === null || p.measureIdx === null ? null : { s: p.sectionIdx, m: p.measureIdx });
      });
      setSonando(true);
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo cargar el audio.');
    }
  };

  const refActual = () => partitura?.audio_ref ?? t.score?.audio_ref ?? null;
  const scoreActual = () => partitura ?? partituraVacia(t.nombre, t.autor ?? '');

  const detenerSincro = () => {
    if (syncRef.current) clearInterval(syncRef.current);
    syncRef.current = null;
  };

  /** Sonido sintetizado desde el mismo pulso que la grabación, al tempo de la grabación × velocidad. */
  const arrancarSintetico = (desdeGrabSeg: number, velocidad: number) => {
    const sc = scoreActual();
    const bpmBase = tempoDeGrabacion(sc) || sc.tempo || 100;
    const bpm = Math.min(TEMPO_MAX, Math.max(TEMPO_MIN, Math.round((bpmBase * velocidad) / 100)));
    const pulso = Math.max(0, pulsoDeTiempo(sc, desdeGrabSeg));
    const desdeSeg = segundosDeTicks(pulso * ticksDePulso(sc.timeSignature), bpm);
    motor.current.onFin = () => { setSonando(false); setGrabando(false); setMarcaGrab(null); };
    motor.current.tocar(sc, { bpm, soloSeccion: null, rango: null, conteo: false, metronomo: false, loop: false, desdeSeg }, (p) => {
      if (modoGrabRef.current !== 'sintetizado' || p.sectionIdx === null || p.measureIdx === null) return;
      setMarcaGrab({ s: p.sectionIdx, m: p.measureIdx });
    });
  };

  /** Escucha la grabación con el cursor sincronizado (solo grabación, sintetizado o ambos). */
  const escucharGrabacion = async () => {
    const ref = refActual();
    if (!ref) return;
    setAviso(null);
    const modo = modoGrab;
    const g = grabacion.current;
    try {
      const ok = await g.cargar(slug, ref);
      if (!ok && modo !== 'sintetizado') return setAviso('La grabación de este toque no está disponible.');
      g.setVelocidad(velocidadGrab);
      const desde = g.tiempo;
      if (modo !== 'sintetizado' && ok) {
        g.setSilenciada(false);
        g.play();
      }
      if (modo !== 'grabacion') {
        await motor.current.preparar((listos, total) => setProgreso(listos < total ? `Cargando sonidos ${listos} de ${total}…` : null));
        setProgreso(null);
        arrancarSintetico(desde, velocidadGrab);
      }
      setGrabando(true);
      setSonando(true);
      if (modo !== 'sintetizado' && ok) {
        // La grabación manda el cursor. En «ambos» el sintetizado se vuelve a alinear cada 8 s.
        let ticks = 0;
        const cant = expandirTimeline(scoreActual()).length;
        detenerSincro();
        syncRef.current = setInterval(() => {
          if (!g.sonando) return;
          if (g.duracion > 0 && g.tiempo >= g.duracion - 0.05) {
            detenerSincro();
            pararGrabacion();
            return;
          }
          const sc = scoreActual();
          const pos = posicionEnGrabacion(sc, g.tiempo, cant);
          if (pos.antes || pos.despues) setMarcaGrab(null);
          else {
            const tl = expandirTimeline(sc)[pos.orden];
            if (tl) setMarcaGrab({ s: tl.sectionIdx, m: tl.measureIdx });
          }
          ticks += 1;
          // 67 ticks de 120 ms ≈ 8 s.
          if (modo === 'ambos' && ticks >= 67) {
            ticks = 0;
            motor.current.parar(false);
            arrancarSintetico(g.tiempo, velocidadGrab);
          }
        }, 120);
      }
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo cargar la grabación.');
    }
  };

  const pararGrabacion = () => {
    detenerSincro();
    grabacion.current.pausar();
    motor.current.parar(false);
    setGrabando(false);
    setSonando(false);
    setMarcaGrab(null);
  };

  const cambiarVelocidad = () => {
    const i = VELOCIDADES.indexOf(velocidadGrab as (typeof VELOCIDADES)[number]);
    const siguiente = VELOCIDADES[(i + 1) % VELOCIDADES.length];
    setVelocidadGrab(siguiente);
    grabacion.current.setVelocidad(siguiente);
    if (grabando && modoGrab !== 'grabacion') {
      motor.current.parar(false);
      arrancarSintetico(grabacion.current.tiempo, siguiente);
    }
  };

  const cambiarModo = () => {
    const i = MODOS_GRAB.findIndex((m) => m.id === modoGrab);
    const siguiente = MODOS_GRAB[(i + 1) % MODOS_GRAB.length].id;
    setModoGrab(siguiente);
    modoGrabRef.current = siguiente;
    if (grabando) pararGrabacion();
  };

  /** «Ajustar inicio»: el primer tiempo queda en el punto donde está la grabación ahora. */
  const ajustarInicioAqui = async () => {
    const ref = refActual();
    if (!ref) return;
    const nuevo = ajustarInicio(ref, grabacion.current.tiempo);
    setAviso(null);
    try {
      await api(`partituras/${slug}/audio/inicio`, { method: 'PUT', body: { offset_seg: nuevo.offset_seg, beats: nuevo.beats } });
      setScore((sc) => (sc ? { ...sc, audio_ref: nuevo } : sc));
      await qc.invalidateQueries({ queryKey: ['partitura', slug] });
      setAviso(`El primer tiempo queda en ${nuevo.offset_seg.toFixed(2)} s.`);
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo ajustar el inicio.');
    }
  };

  /** Marcar el loop: primer toque = A, segundo = B (en la misma parte). */
  const marcarCompas = (s: number, m: number) => {
    setRango((r) => {
      if (!r || r.s !== s || r.hasta !== null) return { s, desde: m, hasta: null };
      return { s, desde: Math.min(r.desde, m), hasta: Math.max(r.desde, m) };
    });
  };

  const parar = () => {
    motor.current.parar(false);
    setSonando(false);
    setEnConteo(false);
    setMarca(null);
    marcaPrevia.current = '';
  };

  const guardar = async () => {
    if (!partitura) return;
    setGuardando(true);
    setAviso(null);
    try {
      await api(`partituras/${slug}/score`, { method: 'PUT', body: { score: tempoDe(paraGuardar(partitura), tempo) } });
      setSucio(false);
      await qc.invalidateQueries({ queryKey: ['partitura', slug] });
      await qc.invalidateQueries({ queryKey: ['partituras'] });
      setAviso('Partitura guardada.');
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo guardar.');
    } finally {
      setGuardando(false);
    }
  };

  const celda = (si: number, mi: number, idx: number) => {
    if (!partitura || !elegido) return;
    const cap = ticksDeCompas(partitura.timeSignature);
    const grilla = grillaDeVoz(partitura.sections[si]?.measures[mi]?.voces[elegido], cap);
    if (!grilla) {
      Alert.alert(
        'Figuras que no entran en la grilla',
        'Este compás tiene tresillos u otra figura. Pasarlo a semicorcheas mantiene los golpes, redondeados al tiempo más cercano.',
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Pasar a semicorcheas', onPress: () => cambiar(cuantizarVoz(partitura, si, mi, elegido)) },
        ],
      );
      return;
    }
    const actual = grilla[idx]?.tipo === 'silencio' ? null : grilla[idx]?.stroke ?? null;
    const sig = cicloGolpe(elegido, actual);
    cambiar(escribirCelda(partitura, si, mi, elegido, idx, sig));
    if (sig) void motor.current.preescuchar(elegido, sig);
  };

  const borrar = () => {
    Alert.alert('Eliminar toque', `Se borra «${t.nombre}» del programa.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar', style: 'destructive', onPress: () => {
          void api(`partituras/${slug}`, { method: 'DELETE' })
            .then(() => qc.invalidateQueries({ queryKey: ['partituras'] }))
            .then(() => router.back())
            .catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo eliminar.'));
        },
      },
    ]);
  };

  return (
    <Pantalla>
      <Stack.Screen options={{ title: t.nombre }} />
      <Titulo>{t.nombre}</Titulo>
      <Tenue>{t.anio}° año{t.autor ? ` · ${t.autor}` : ''}{lectura ? ` · ${lectura.compas}` : ''}{t.publicado === false ? ' · oculto' : ''}</Tenue>
      {t.resumen && <Tenue>{t.resumen}</Tenue>}
      {enConteo && <Aviso texto="Conteo…" />}
      {aviso && <Tenue>{aviso}</Tenue>}

      <View style={s.transporte}>
        <Boton titulo={sonando ? 'Parar' : 'Escuchar'} icono={sonando ? 'stop' : 'play-arrow'} onPress={() => (sonando ? parar() : void tocar(null))} />
        <Boton titulo="−5" variante="secundario" onPress={() => setTempo((n) => Math.max(TEMPO_MIN, n - 5))} />
        <Boton titulo={`${tempo} bpm`} variante="secundario" onPress={() => setTempo((n) => Math.min(TEMPO_MAX, n + 1))} />
        <Boton titulo="+5" variante="secundario" onPress={() => setTempo((n) => Math.min(TEMPO_MAX, n + 5))} />
        <Boton titulo="Metrónomo" variante={metronomo ? 'primario' : 'secundario'} onPress={() => setMetronomo((v) => !v)} />
        <Boton titulo="Conteo" variante={conteo ? 'primario' : 'secundario'} onPress={() => setConteo((v) => !v)} />
        <Boton titulo={solo ? 'Todas las cuerdas' : 'Solo esta cuerda'} variante="secundario" onPress={() => setSolo((v) => (v ? null : elegido))} />
        <Boton
          titulo={elegido && mudas.includes(elegido) ? 'Activar cuerda' : 'Silenciar cuerda'}
          variante="secundario"
          onPress={() => elegido && setMudas((xs) => (xs.includes(elegido) ? xs.filter((x) => x !== elegido) : [...xs, elegido]))}
        />
      </View>
      <Tenue>El tempo va de {TEMPO_MIN} a {TEMPO_MAX} bpm. Tocá el número para subirlo de a 1. Solo escucha la cuerda elegida. Silenciar la saca del conjunto.</Tenue>
      {progreso && <Tenue>{progreso}</Tenue>}
      {(partitura?.audio_ref ?? t.score?.audio_ref) && (
        <View style={s.transporte}>
          <Boton titulo={grabando ? 'Pausar grabación' : 'Escuchar grabación'} icono={grabando ? 'pause' : 'graphic-eq'} onPress={() => (grabando ? pararGrabacion() : void escucharGrabacion())} />
          <Boton titulo={`${velocidadGrab}%`} variante="secundario" onPress={cambiarVelocidad} />
          <Boton titulo={MODOS_GRAB.find((m) => m.id === modoGrab)?.titulo ?? 'Grabación'} variante="secundario" onPress={cambiarModo} />
        </View>
      )}
      <View style={s.transporte}>
        <Boton titulo="Descargar MIDI" icono="save" variante="secundario" onPress={() => void descargarYAbrir(`partituras/${slug}/exportar/midi`, { nombre: `${t.nombre}.mid` }).catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo exportar.'))} />
        <Boton titulo="Descargar MusicXML" icono="save" variante="secundario" onPress={() => void descargarYAbrir(`partituras/${slug}/exportar/musicxml`, { nombre: `${t.nombre}.musicxml` }).catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo exportar.'))} />
        <Boton titulo="Versiones" icono="history" variante="secundario" onPress={() => router.push(`/partituras/versiones/${slug}`)} />
      </View>
      {rango && (
        <View style={s.transporte}>
          <Tenue>{`Loop: compases ${rango.desde + 1}${rango.hasta !== null ? `–${rango.hasta + 1}` : ' (tocá otro para cerrar)'} de la parte`}</Tenue>
          {rango.hasta !== null && <Boton titulo="Repetir este tramo" icono="loop" onPress={() => void tocar(null, { sectionIdx: rango.s, desde: rango.desde, hasta: rango.hasta ?? rango.desde })} />}
          <Boton titulo="Quitar loop" variante="secundario" onPress={() => setRango(null)} />
        </View>
      )}

      {(partitura?.instruments.length || lectura?.instrumentos.length) ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
          {(partitura?.instruments ?? lectura?.instrumentos.map((i) => ({ id: i.id })) ?? []).map((i) => (
            <Pressable key={i.id} accessibilityRole="button" accessibilityState={{ selected: i.id === elegido }} onPress={() => setInstrumento(i.id)}>
              <Chip texto={nombreDe(i.id)} color={i.id === elegido ? C.fondo : C.texto} fondo={i.id === elegido ? C.acento : (solo === i.id ? C.superficie2 : C.superficie)} />
            </Pressable>
          ))}
        </ScrollView>
      ) : null}

      {editando && partitura && elegido && partitura.sections.map((sec, si) => (
        <View key={sec.id ?? sec.name} style={{ gap: E.s }}>
          <TextInput
            value={sec.name}
            onChangeText={(nombre) => cambiar(renombrarSeccion(partitura, si, nombre))}
            style={s.nombre}
            accessibilityLabel="Nombre de la parte"
          />
          <View style={s.transporte}>
            <Boton titulo={`×${sec.repeatX}`} variante="secundario" onPress={() => cambiar(repeticion(partitura, si, sec.repeatX >= 8 ? 1 : sec.repeatX + 1))} />
            <Boton titulo="Compás" icono="add" variante="secundario" onPress={() => cambiar(agregarCompas(partitura, si))} />
            <Boton titulo="Esta parte" icono="loop" variante="secundario" onPress={() => void tocar(si)} />
          </View>
          {sec.measures.map((m, mi) => {
            const grilla = grillaDeVoz(m.voces[elegido], ticksDeCompas(partitura.timeSignature));
            const activo = marca?.s === si && marca.m === mi;
            return (
              <View key={m.id ?? mi} style={{ gap: 4 }}>
                <Tenue>Compás {mi + 1}{activo ? ' · sonando' : ''}</Tenue>
                {grilla ? (
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 4 }}>
                    {grilla.map((c, idx) => (
                      <Pressable key={idx} accessibilityRole="button" accessibilityLabel={`Celda ${idx + 1}`} onPress={() => celda(si, mi, idx)} style={[s.celda, activo && s.activa, c.tipo === 'golpe' && s.golpe]}>
                        <Texto>{c.tipo === 'silencio' ? '·' : SIMBOLOS[c.stroke ?? 'nota'] ?? '●'}</Texto>
                      </Pressable>
                    ))}
                  </ScrollView>
                ) : (
                  <Boton titulo="Pasar este compás a la grilla" variante="secundario" onPress={() => celda(si, mi, 0)} />
                )}
              </View>
            );
          })}
        </View>
      ))}

      {editando && partitura && (
        <>
          <Tenue>Tocá una celda para pasar de silencio al próximo golpe ({elegido ? golpesDe(elegido).map((g) => `${SIMBOLOS[g] ?? g} ${ETIQUETA_GOLPE[g] ?? g}`).join(' · ') : ''}). Una negra son cuatro celdas: el golpe y el tiempo que dura.</Tenue>
          <Boton titulo="Nueva parte" icono="add" variante="secundario" onPress={() => cambiar(agregarSeccion(partitura))} />
          <Subtitulo>Cuerdas</Subtitulo>
          <View style={s.transporte}>
            {INSTRUMENTOS.map((i) => {
              const puesta = partitura.instruments.some((x) => x.id === i.id);
              return <Boton key={i.id} titulo={i.corto} variante={puesta ? 'primario' : 'secundario'} onPress={() => cambiar(conInstrumento(partitura, i.id, !puesta))} />;
            })}
          </View>
          <Boton titulo="Guardar partitura" icono="save" cargando={guardando} deshabilitado={!sucio && partitura.tempo === tempo} onPress={() => void guardar()} />
        </>
      )}

      {!editando && lectura && lectura.instrumentos.length > 0 && lectura.secciones.map((sec, si) => (
        <View key={sec.nombre} style={{ gap: E.s }}>
          <Subtitulo>{sec.nombre}{sec.repetir > 1 ? ` ×${sec.repetir}` : ''}</Subtitulo>
          <Boton titulo="Escuchar esta parte" icono="loop" variante="secundario" onPress={() => void tocar(si)} />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
            {sec.compases.map((compas, n) => (
              <Pressable
                key={`${sec.nombre}-${n}`}
                accessibilityRole="button"
                accessibilityLabel={`Compás ${n + 1}, marcar para repetir`}
                onPress={() => marcarCompas(si, n)}
                style={[s.compas, (marca?.s === si && marca.m === n) || (marcaGrab?.s === si && marcaGrab.m === n) ? s.activa : null, rango?.s === si && rango.desde <= n && n <= (rango.hasta ?? rango.desde) && s.enLoop]}
              >
                <Tenue>{n + 1}</Tenue>
                <Texto style={s.simbolos}>{elegido ? (compas.voces[elegido] || '·') : '·'}</Texto>
              </Pressable>
            ))}
          </ScrollView>
        </View>
      ))}
      {!editando && lectura && <Tenue>● golpe · · silencio · &gt; acentuado · ✕ chapa · — tapado</Tenue>}

      {admin && (
        <>
          <PanelTranscripcion slug={slug} tieneAudio={Boolean(partitura?.audio_ref?.path)} />
          <Boton titulo="Subir grabación" icono="graphic-eq" variante="secundario" onPress={() => void elegirDocumento(['audio/*']).then((archivo) => {
            if (!archivo) return;
            return subirGrabacion(slug, archivo, false)
              .then(() => qc.invalidateQueries({ queryKey: ['partitura', slug] }))
              .catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo subir la grabación.'));
          })} />
          {partitura?.audio_ref?.path && (
            <Boton titulo="Ajustar inicio aquí" icono="flag" variante="secundario" onPress={() => void ajustarInicioAqui()} />
          )}
          <Boton titulo={editando ? 'Ver lectura' : 'Escribir'} icono="edit" variante="secundario" onPress={() => setEditando((v) => !v)} />
          <Boton
            titulo={t.publicado === false ? 'Publicar toque' : 'Ocultar toque'}
            icono="visibility"
            variante="secundario"
            onPress={() => void api(`partituras/${slug}`, { method: 'PUT', body: { publicado: t.publicado === false } }).then(() => qc.invalidateQueries({ queryKey: ['partitura', slug] }))}
          />
          <Boton
            titulo="Subir PDF original"
            icono="picture-as-pdf"
            variante="secundario"
            onPress={() => void elegirDocumento().then((archivo) => {
              if (!archivo) return;
              return subirArchivo(`partituras/${slug}/archivo`, archivo, { campo: 'partitura_archivo' })
                .then(() => qc.invalidateQueries({ queryKey: ['partitura', slug] }))
                .catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo subir el PDF.'));
            })}
          />
          {t.tiene_pdf && <Boton titulo="Abrir PDF" icono="picture-as-pdf" variante="secundario" onPress={() => void descargarYAbrir(`partituras/${slug}/archivo`, { nombre: `${t.nombre}.pdf` }).catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo abrir el PDF.'))} />}
          <Boton titulo="Eliminar toque" icono="delete" variante="peligro" onPress={borrar} />
        </>
      )}
      {!admin && t.tiene_pdf && (
        <Boton titulo="Partitura original (PDF)" icono="picture-as-pdf" onPress={() => void descargarYAbrir(`partituras/${slug}/archivo`, { nombre: `${t.nombre}.pdf` }).catch((e) => setAviso(e instanceof Error ? e.message : 'No se pudo abrir el PDF.'))} />
      )}
      {editando && !partitura && <Boton titulo="Empezar partitura" icono="add" onPress={() => cambiar(partituraVacia(t.nombre, t.autor ?? ''))} />}
      {!lectura && !t.tiene_pdf && !partitura && <Tenue>Este toque todavía no tiene partitura cargada.</Tenue>}
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
  transporte: { flexDirection: 'row', flexWrap: 'wrap', gap: E.s },
  compas: { minWidth: 88, padding: E.s, borderRadius: 10, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, gap: 4 },
  simbolos: { letterSpacing: 1 },
  celda: { width: 44, height: 44, borderRadius: 8, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, alignItems: 'center', justifyContent: 'center' },
  golpe: { backgroundColor: C.superficie2 },
  activa: { borderColor: C.acento, borderWidth: 2 },
  enLoop: { backgroundColor: C.superficie2 },
  nombre: { color: C.texto, fontSize: 18, fontWeight: '700', borderBottomWidth: 1, borderColor: C.borde, paddingVertical: 4 },
});
