import { Stack, useLocalSearchParams } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Aviso, Boton, Cargando, ErrorVista, Fila, Tenue } from '@/components/ui';
import { useConexion } from '@/lib/offline';
import { useGuardarAsistencia, usePlanilla } from '@/lib/queries';
import { C, E } from '@/lib/theme';
import type { TipoAsistencia } from '@/lib/types';

const hoyIso = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// Opciones rápidas: un toque alterna presente/ausente; el resto con toque largo.
const CICLO: TipoAsistencia[] = ['presente', 'ausencia_injustificada'];
const ESTILO: Record<TipoAsistencia, { letra: string; color: string; fondo: string; texto: string }> = {
  presente: { letra: 'P', color: C.exito, fondo: C.exitoSuave, texto: 'Presente' },
  tarde: { letra: 'T', color: C.alerta, fondo: C.alertaSuave, texto: 'Tarde' },
  ausencia_justificada: { letra: 'J', color: C.info, fondo: 'rgba(79,179,217,0.18)', texto: 'Ausente justificado' },
  ausencia_injustificada: { letra: 'A', color: C.peligro, fondo: C.peligroSuave, texto: 'Ausente' },
  feriado: { letra: 'F', color: C.tenue, fondo: C.superficie2, texto: 'Feriado' },
  sin_clases: { letra: 'S', color: C.tenue, fondo: C.superficie2, texto: 'Sin clases' },
};

export default function TomarAsistencia() {
  const { bloque, nombre } = useLocalSearchParams<{ bloque: string; nombre?: string }>();
  const bloqueId = Number(bloque);
  const [fecha] = useState(hoyIso);
  const planilla = usePlanilla(bloqueId, fecha);
  const guardar = useGuardarAsistencia(bloqueId, fecha);
  const online = useConexion();
  // Lo ya registrado en el servidor + lo que se marcó en esta pantalla (corrección posterior).
  const [cambios, setCambios] = useState<Record<number, TipoAsistencia>>({});
  const [resultado, setResultado] = useState<string | null>(null);

  const alumnos = useMemo(() => planilla.data?.alumnos ?? [], [planilla.data]);
  const marcas = useMemo(() => {
    const base: Record<number, TipoAsistencia> = {};
    alumnos.forEach((a) => {
      if (a.tipo) base[a.alumno_id] = a.tipo;
    });
    return { ...base, ...cambios };
  }, [alumnos, cambios]);
  const setMarcas = (f: (m: Record<number, TipoAsistencia>) => Record<number, TipoAsistencia>) => setCambios(f(marcas));
  const puedeEditar = planilla.data?.puede_editar ?? false;
  const cuenta = useMemo(() => {
    const presentes = alumnos.filter((a) => marcas[a.alumno_id] === 'presente' || marcas[a.alumno_id] === 'tarde').length;
    const marcados = alumnos.filter((a) => marcas[a.alumno_id]).length;
    return { presentes, marcados };
  }, [alumnos, marcas]);

  if (planilla.isPending && !planilla.data) return <Cargando />;
  if (planilla.isError && !planilla.data) return <ErrorVista error={planilla.error} onReintentar={() => planilla.refetch()} />;

  const alternar = (id: number) => {
    if (!puedeEditar) return;
    setMarcas((m) => {
      const actual = m[id];
      const i = actual ? CICLO.indexOf(actual) : -1;
      return { ...m, [id]: CICLO[(i + 1) % CICLO.length] };
    });
  };

  const elegirOtro = (id: number, alumno: string) => {
    if (!puedeEditar) return;
    Alert.alert(alumno, 'Marcar como', [
      ...(['tarde', 'ausencia_justificada', 'feriado', 'sin_clases'] as TipoAsistencia[]).map((t) => ({
        text: ESTILO[t].texto,
        onPress: () => setMarcas((m) => ({ ...m, [id]: t })),
      })),
      { text: 'Cancelar', style: 'cancel' as const },
    ]);
  };

  const todosPresentes = () => setCambios(Object.fromEntries(alumnos.map((a) => [a.alumno_id, 'presente' as TipoAsistencia])));

  const enviar = async () => {
    setResultado(null);
    const marcadas = Object.fromEntries(Object.entries(marcas).filter(([, t]) => !!t)) as Record<number, TipoAsistencia>;
    if (Object.keys(marcadas).length === 0) {
      Alert.alert('Nada para guardar', 'Marcá al menos un alumno.');
      return;
    }
    try {
      const r = await guardar.mutateAsync(marcadas);
      if (r.estado === 'pendiente') {
        setResultado('Guardada en el teléfono. Se envía sola cuando vuelva la conexión.');
      } else if (r.conflictos.length > 0) {
        setResultado(`Guardada. ${r.conflictos.length} alumno(s) ya habían sido corregidos por otra persona y se conservó ese dato.`);
      } else {
        setResultado('✓ Asistencia guardada.');
      }
    } catch (e) {
      Alert.alert('No se pudo guardar', e instanceof Error ? e.message : 'Error');
    }
  };

  return (
    <SafeAreaView style={s.pantalla} edges={['bottom', 'left', 'right']}>
      <Stack.Screen options={{ title: nombre ?? planilla.data?.bloque.nombre ?? 'Asistencia' }} />
      <View style={s.cabecera}>
        <Tenue>{new Date(fecha + 'T12:00:00').toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' })}</Tenue>
        <Text style={s.contador} accessibilityLiveRegion="polite">
          {cuenta.presentes} presentes · {cuenta.marcados}/{alumnos.length} marcados
        </Text>
        {!online && <Aviso tono="alerta" texto="Sin conexión: la planilla se guarda en el teléfono y se envía después." />}
        {planilla.data?.tomada && <Tenue>Ya había asistencia cargada: podés corregirla.</Tenue>}
        {!puedeEditar && <Aviso tono="info" texto="Solo lectura: no podés modificar la asistencia de este bloque." />}
        {puedeEditar && <Boton titulo="Todos presentes" icono="done-all" variante="secundario" onPress={todosPresentes} />}
        <Tenue>Tocá para alternar presente / ausente. Mantené apretado para tarde o justificado.</Tenue>
      </View>

      <FlatList
        data={alumnos}
        keyExtractor={(a) => String(a.alumno_id)}
        contentContainerStyle={{ padding: E.l, gap: E.s, paddingBottom: 120 }}
        renderItem={({ item }) => {
          const tipo = marcas[item.alumno_id];
          const est = tipo ? ESTILO[tipo] : null;
          return (
            <Pressable
              onPress={() => alternar(item.alumno_id)}
              onLongPress={() => elegirOtro(item.alumno_id, item.nombre)}
              accessibilityRole="button"
              accessibilityLabel={`${item.nombre}: ${est ? est.texto : 'sin marcar'}`}
              accessibilityHint="Tocá para cambiar entre presente y ausente"
              style={({ pressed }) => [s.alumno, est && { backgroundColor: est.fondo, borderColor: est.color }, pressed && { opacity: 0.8 }]}>
              <Text style={s.nombre} numberOfLines={2}>{item.nombre}</Text>
              <View style={[s.letra, { borderColor: est?.color ?? C.borde }]}>
                <Text style={[s.letraTexto, { color: est?.color ?? C.tenue }]}>{est?.letra ?? '–'}</Text>
              </View>
            </Pressable>
          );
        }}
        ListEmptyComponent={<Tenue>No hay alumnos activos en este bloque.</Tenue>}
      />

      {puedeEditar && (
        <View style={s.pie}>
          {resultado && <Aviso texto={resultado} tono={resultado.startsWith('✓') ? 'exito' : 'info'} />}
          <Fila>
            <View style={{ flex: 1 }}>
              <Boton titulo="Guardar asistencia" icono="save" onPress={enviar} cargando={guardar.isPending} grande />
            </View>
          </Fila>
        </View>
      )}
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.fondo },
  cabecera: { padding: E.l, paddingBottom: 0, gap: E.s },
  contador: { color: C.texto, fontSize: 20, fontWeight: '800' },
  alumno: { flexDirection: 'row', alignItems: 'center', gap: E.m, backgroundColor: C.superficie, borderRadius: 14, borderWidth: 2, borderColor: C.borde, paddingHorizontal: E.l, minHeight: 64 },
  nombre: { flex: 1, color: C.texto, fontSize: 17, fontWeight: '600' },
  letra: { width: 44, height: 44, borderRadius: 22, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  letraTexto: { fontSize: 20, fontWeight: '900' },
  pie: { position: 'absolute', left: 0, right: 0, bottom: 0, padding: E.l, gap: E.s, backgroundColor: C.fondo, borderTopWidth: 1, borderTopColor: C.borde },
});
