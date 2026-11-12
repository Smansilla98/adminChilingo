import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams, useNavigation } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { type AccessibilityActionEvent, Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { confirmar, useToast } from '@/components/feedback';
import { CampoFecha, hoyIso } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Icon, Tenue, Vacio } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { useColaAsistencia, useConexion } from '@/lib/offline';
import { useGuardarAsistencia, usePlanilla } from '@/lib/queries';
import { C, E } from '@/lib/theme';
import type { Planilla, TipoAsistencia } from '@/lib/types';

// Opciones rápidas: un toque alterna presente/ausente; el resto desde el menú (⋯, toque largo o lector de pantalla).
const CICLO: TipoAsistencia[] = ['presente', 'ausencia_injustificada'];
const OTRAS: TipoAsistencia[] = ['tarde', 'ausencia_justificada', 'feriado', 'sin_clases'];
const ESTILO: Record<TipoAsistencia, { letra: string; color: string; fondo: string; texto: string }> = {
  presente: { letra: 'P', color: C.exito, fondo: C.exitoSuave, texto: 'Presente' },
  tarde: { letra: 'T', color: C.alerta, fondo: C.alertaSuave, texto: 'Tarde' },
  ausencia_justificada: { letra: 'J', color: C.info, fondo: 'rgba(79,179,217,0.18)', texto: 'Ausente justificado' },
  ausencia_injustificada: { letra: 'A', color: C.peligro, fondo: C.peligroSuave, texto: 'Ausente' },
  feriado: { letra: 'F', color: C.tenue, fondo: C.superficie2, texto: 'Feriado' },
  sin_clases: { letra: 'S', color: C.tenue, fondo: C.superficie2, texto: 'Sin clases' },
};

type Alumno = Planilla['alumnos'][number];

export default function TomarAsistencia() {
  const { bloque, nombre, fecha: fechaInicial } = useLocalSearchParams<{ bloque: string; nombre?: string; fecha?: string }>();
  const bloqueId = Number(bloque);
  const hoy = hoyIso();
  // Por defecto hoy; se puede elegir otro día para cargar o corregir una clase pasada.
  const [fecha, setFecha] = useState(fechaInicial ?? hoy);
  const planilla = usePlanilla(bloqueId, fecha);
  const nombreBloque = nombre ?? planilla.data?.bloque.nombre;
  const guardar = useGuardarAsistencia(bloqueId, fecha, nombreBloque);
  const online = useConexion();
  const cola = useColaAsistencia();
  const enCola = cola.find((e) => e.bloqueId === bloqueId && e.fecha === fecha);
  const qc = useQueryClient();
  const avisar = useToast();
  const navigation = useNavigation();
  // Lo ya registrado en el servidor + lo que se marcó en esta pantalla (corrección posterior).
  const [cambios, setCambios] = useState<Record<number, TipoAsistencia>>({});
  const [sinGuardar, setSinGuardar] = useState(false);
  const [resultado, setResultado] = useState<string | null>(null);

  const borrar = useMutation({
    mutationFn: (asistenciaId: number) => api(`asistencias/${asistenciaId}`, { method: 'DELETE' }),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ['planilla', bloqueId, fecha] });
      void qc.invalidateQueries({ queryKey: ['inicio'] });
      avisar('Marca borrada');
    },
    onError: (e) => avisar(e instanceof Error ? e.message : 'No se pudo borrar la marca.', 'error'),
  });

  // Salir con marcas sin guardar: se pregunta antes de perderlas.
  useEffect(
    () =>
      navigation.addListener('beforeRemove', (e) => {
        if (!sinGuardar) return;
        e.preventDefault();
        Alert.alert('¿Salir sin guardar?', 'Marcaste asistencia que todavía no guardaste. Si salís, esas marcas se pierden.', [
          { text: 'Seguir marcando', style: 'cancel' },
          { text: 'Salir sin guardar', style: 'destructive', onPress: () => navigation.dispatch(e.data.action) },
        ]);
      }),
    [navigation, sinGuardar],
  );

  const alumnos = useMemo(() => planilla.data?.alumnos ?? [], [planilla.data]);
  const marcas = useMemo(() => {
    const base: Record<number, TipoAsistencia> = {};
    alumnos.forEach((a) => {
      if (a.tipo) base[a.alumno_id] = a.tipo;
    });
    return { ...base, ...cambios };
  }, [alumnos, cambios]);
  const puedeEditar = planilla.data?.puede_editar ?? false;
  const cuenta = useMemo(() => {
    const presentes = alumnos.filter((a) => marcas[a.alumno_id] === 'presente' || marcas[a.alumno_id] === 'tarde').length;
    const marcados = alumnos.filter((a) => marcas[a.alumno_id]).length;
    return { presentes, marcados };
  }, [alumnos, marcas]);

  const marcar = (id: number, tipo: TipoAsistencia) => {
    setCambios((c) => ({ ...c, [id]: tipo }));
    setSinGuardar(true);
    setResultado(null);
  };

  const alternar = (id: number) => {
    if (!puedeEditar) return;
    const actual = marcas[id];
    const i = actual ? CICLO.indexOf(actual) : -1;
    marcar(id, CICLO[(i + 1) % CICLO.length]);
  };

  /** Deshace lo marcado en pantalla o, si ya estaba guardada, borra la marca del servidor. */
  const quitarMarca = async (a: Alumno) => {
    if (cambios[a.alumno_id]) {
      setCambios((c) => Object.fromEntries(Object.entries(c).filter(([id]) => Number(id) !== a.alumno_id)));
      return;
    }
    if (!a.asistencia_id) return;
    if (await confirmar({ titulo: `¿Borrar la marca de ${a.nombre}?`, mensaje: 'Queda sin asistencia cargada para este día.', accion: 'Borrar' })) {
      borrar.mutate(a.asistencia_id);
    }
  };

  const textoQuitar = (a: Alumno) => (cambios[a.alumno_id] ? 'Deshacer cambio' : a.asistencia_id ? 'Borrar marca guardada' : null);

  const elegirOtro = (a: Alumno) => {
    if (!puedeEditar) return;
    const quitar = textoQuitar(a);
    Alert.alert(a.nombre, 'Marcar como', [
      ...OTRAS.map((t) => ({ text: ESTILO[t].texto, onPress: () => marcar(a.alumno_id, t) })),
      ...(quitar ? [{ text: quitar, style: 'destructive' as const, onPress: () => void quitarMarca(a) }] : []),
      { text: 'Cancelar', style: 'cancel' as const },
    ]);
  };

  const accionAccesible = (a: Alumno, ev: AccessibilityActionEvent) => {
    const n = ev.nativeEvent.actionName;
    if (n === 'activate') alternar(a.alumno_id);
    else if (n === 'quitar') void quitarMarca(a);
    else if ((OTRAS as string[]).includes(n)) marcar(a.alumno_id, n as TipoAsistencia);
  };

  const accionesDe = (a: Alumno) =>
    puedeEditar
      ? [
          { name: 'activate', label: 'Alternar presente o ausente' },
          ...OTRAS.map((t) => ({ name: t, label: `Marcar ${ESTILO[t].texto.toLowerCase()}` })),
          ...(textoQuitar(a) ? [{ name: 'quitar', label: textoQuitar(a)! }] : []),
        ]
      : undefined;

  const todosPresentes = () => {
    setCambios(Object.fromEntries(alumnos.map((a) => [a.alumno_id, 'presente' as TipoAsistencia])));
    setSinGuardar(true);
  };

  const cambiarFecha = async (f: string) => {
    if (!f || f === fecha) return;
    if (sinGuardar && !(await confirmar({ titulo: '¿Cambiar de día?', mensaje: 'Las marcas sin guardar de este día se pierden.', accion: 'Cambiar' }))) return;
    setFecha(f);
    setCambios({});
    setSinGuardar(false);
    setResultado(null);
  };

  const enviar = async () => {
    setResultado(null);
    const marcadas = Object.fromEntries(Object.entries(marcas).filter(([, t]) => !!t)) as Record<number, TipoAsistencia>;
    if (Object.keys(marcadas).length === 0) {
      Alert.alert('Nada para guardar', 'Marcá al menos un alumno.');
      return;
    }
    try {
      const r = await guardar.mutateAsync(marcadas);
      setSinGuardar(false);
      if (r.estado === 'pendiente') {
        setResultado('Guardada en el teléfono. Se envía sola cuando vuelva la conexión.');
        return;
      }
      await planilla.refetch();
      setCambios({});
      setResultado(
        r.conflictos.length > 0
          ? `Guardada. ${r.conflictos.length} alumno(s) ya habían sido corregidos por otra persona y se conservó ese dato.`
          : '✓ Asistencia guardada.',
      );
    } catch (e) {
      Alert.alert('No se pudo guardar', e instanceof Error ? e.message : 'Error');
    }
  };

  // Sin datos de ese día: cargando, sin conexión (la consulta queda en pausa) o error.
  const sinDatos = !planilla.data;
  const sinConexionSinDatos = sinDatos && (planilla.fetchStatus === 'paused' || (planilla.isError && planilla.error instanceof ApiError && planilla.error.esDeRed));
  const volverAHoy = fecha !== hoy ? <Boton titulo="Volver a hoy" icono="today" variante="secundario" onPress={() => void cambiarFecha(hoy)} /> : null;

  let cuerpo;
  if (sinConexionSinDatos) {
    cuerpo = (
      <View style={s.estado}>
        <Vacio icono="cloud-off" texto="Sin conexión: no hay datos guardados de esa fecha en el teléfono. Probá de nuevo cuando vuelva la señal." />
        {volverAHoy}
        <Boton titulo="Reintentar" icono="refresh" variante="secundario" onPress={() => void planilla.refetch()} />
      </View>
    );
  } else if (sinDatos && planilla.isError) {
    cuerpo = (
      <View style={s.estado}>
        <ErrorVista error={planilla.error} onReintentar={() => void planilla.refetch()} />
        {volverAHoy}
      </View>
    );
  } else if (sinDatos) {
    cuerpo = <Cargando />;
  } else {
    cuerpo = (
      <FlatList
        style={{ flex: 1 }}
        data={alumnos}
        keyExtractor={(a) => String(a.alumno_id)}
        contentContainerStyle={{ padding: E.l, gap: E.s }}
        renderItem={({ item }) => {
          const tipo = marcas[item.alumno_id];
          const est = tipo ? ESTILO[tipo] : null;
          return (
            <View style={[s.alumno, est && { backgroundColor: est.fondo, borderColor: est.color }]}>
              <Pressable
                onPress={() => alternar(item.alumno_id)}
                onLongPress={() => elegirOtro(item)}
                disabled={!puedeEditar}
                accessibilityRole="button"
                accessibilityLabel={`${item.nombre}: ${est ? est.texto : 'sin marcar'}`}
                accessibilityHint={puedeEditar ? 'Tocá para cambiar entre presente y ausente. Hay más opciones en las acciones.' : undefined}
                accessibilityActions={accionesDe(item)}
                onAccessibilityAction={(ev) => accionAccesible(item, ev)}
                style={({ pressed }) => [s.alumnoToque, pressed && { opacity: 0.8 }]}>
                <Text style={s.nombre} numberOfLines={2}>{item.nombre}</Text>
                <View style={[s.letra, { borderColor: est?.color ?? C.borde }]}>
                  <Text style={[s.letraTexto, { color: est?.color ?? C.tenue }]}>{est?.letra ?? '–'}</Text>
                </View>
              </Pressable>
              {puedeEditar && (
                <Pressable
                  onPress={() => elegirOtro(item)}
                  accessibilityRole="button"
                  accessibilityLabel={`Más opciones para ${item.nombre}`}
                  hitSlop={6}
                  style={s.mas}>
                  <Icon name="more-vert" size={26} color={C.tenue} />
                </Pressable>
              )}
            </View>
          );
        }}
        ListEmptyComponent={<Tenue>No hay alumnos activos en este bloque.</Tenue>}
      />
    );
  }

  return (
    <SafeAreaView style={s.pantalla} edges={['bottom', 'left', 'right']}>
      <Stack.Screen options={{ title: nombreBloque ?? 'Asistencia' }} />
      <View style={s.cabecera}>
        <CampoFecha etiqueta="Día de la clase" valor={fecha} maximo={hoy} onChange={(f) => void cambiarFecha(f)} />
        {!sinDatos && (
          <Text style={s.contador} accessibilityLiveRegion="polite">
            {cuenta.presentes} presentes · {cuenta.marcados}/{alumnos.length} marcados
          </Text>
        )}
        {!online && !sinDatos && <Aviso tono="alerta" texto="Sin conexión: la planilla se guarda en el teléfono y se envía después." />}
        {enCola && (
          <Aviso
            tono={enCola.fallida ? 'peligro' : 'alerta'}
            texto={enCola.fallida ? `La planilla de este día no se pudo enviar: ${enCola.error ?? 'error'}. Revisala en «Yo».` : 'La planilla de este día está guardada en el teléfono, esperando conexión.'}
          />
        )}
        {planilla.data?.tomada && <Tenue>Ya había asistencia cargada: podés corregirla o borrar una marca desde ⋯.</Tenue>}
        {!sinDatos && !puedeEditar && <Aviso tono="info" texto="Solo lectura: no podés modificar la asistencia de este bloque." />}
        {!sinDatos && puedeEditar && <Boton titulo="Todos presentes" icono="done-all" variante="secundario" onPress={todosPresentes} />}
        {!sinDatos && puedeEditar && <Tenue>Tocá para alternar presente / ausente. Para tarde, justificado o feriado usá ⋯.</Tenue>}
      </View>

      {cuerpo}

      {!sinDatos && puedeEditar && (
        <View style={s.pie}>
          {resultado && <Aviso texto={resultado} tono={resultado.startsWith('✓') ? 'exito' : 'info'} />}
          <Boton titulo="Guardar asistencia" icono="save" onPress={enviar} cargando={guardar.isPending} grande />
        </View>
      )}
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.fondo },
  cabecera: { padding: E.l, paddingBottom: 0, gap: E.s },
  contador: { color: C.texto, fontSize: 20, fontWeight: '800' },
  estado: { flex: 1, padding: E.l, gap: E.m },
  alumno: { flexDirection: 'row', alignItems: 'center', backgroundColor: C.superficie, borderRadius: 14, borderWidth: 2, borderColor: C.borde, minHeight: 64 },
  alumnoToque: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: E.m, paddingLeft: E.l, paddingRight: E.s, minHeight: 64 },
  nombre: { flex: 1, color: C.texto, fontSize: 17, fontWeight: '600' },
  letra: { width: 44, height: 44, borderRadius: 22, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  letraTexto: { fontSize: 20, fontWeight: '900' },
  mas: { width: 48, minHeight: 64, alignItems: 'center', justifyContent: 'center' },
  pie: { padding: E.l, gap: E.s, backgroundColor: C.fondo, borderTopWidth: 1, borderTopColor: C.borde },
});
