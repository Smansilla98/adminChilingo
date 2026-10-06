import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';

import { useToast } from '@/components/feedback';
import { Cargando, ErrorVista, Pantalla, Tenue, Texto } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { C, E } from '@/lib/theme';

interface Celda { id: number; tipo: string }
interface Matriz {
  mes: number;
  anio: number;
  fechas: string[];
  tipos: Record<string, string>;
  puede_editar: boolean;
  alumnos: { alumno_id: number; nombre: string; celdas: Record<string, Celda | null> }[];
}

const CORTO: Record<string, string> = {
  presente: 'P',
  tarde: 'T',
  ausencia_justificada: 'J',
  ausencia_injustificada: 'A',
  feriado: 'F',
  sin_clases: 'S',
};

/** Matriz del mes. Tocar una celda rota el tipo; mantenerla apretada la borra. */
export default function MatrizAsistencia() {
  const { bloque, nombre } = useLocalSearchParams<{ bloque: string; nombre?: string }>();
  const hoy = new Date();
  const [mes, setMes] = useState(hoy.getMonth() + 1);
  const [anio, setAnio] = useState(hoy.getFullYear());
  const qc = useQueryClient();
  const avisar = useToast();
  const q = useQuery({
    queryKey: ['matriz', bloque, mes, anio],
    queryFn: () => api<Matriz>(`bloques/${bloque}/asistencia/matriz${qs({ mes, anio })}`),
  });

  const guardar = async (alumnoId: number, fecha: string, tipo: string | null) => {
    try {
      await api(`bloques/${bloque}/asistencia/matriz`, { method: 'PUT', body: { mes, anio, celdas: { [alumnoId]: { [fecha]: tipo } } } });
      await qc.invalidateQueries({ queryKey: ['matriz', bloque, mes, anio] });
    } catch (e) {
      avisar(e instanceof Error ? e.message : 'No se pudo guardar.', 'error');
    }
  };

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const m = q.data!;
  const tipos = Object.keys(m.tipos);

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: nombre || 'Matriz' }} />
      <View style={{ flexDirection: 'row', gap: E.s }}>
        <Texto accessibilityRole="button" accessibilityLabel="Mes anterior" onPress={() => setMes((x) => x === 1 ? 12 : x - 1)} style={{ color: C.acento, fontWeight: '800' }}>◀</Texto>
        <Texto style={{ fontWeight: '800' }}>{m.mes}/{m.anio}</Texto>
        <Texto accessibilityRole="button" accessibilityLabel="Mes siguiente" onPress={() => setMes((x) => x === 12 ? 1 : x + 1)} style={{ color: C.acento, fontWeight: '800' }}>▶</Texto>
        <Texto accessibilityRole="button" accessibilityLabel={`Ir a ${anio - 1}`} onPress={() => setAnio((x) => x - 1)} style={{ color: C.tenue }}> {anio - 1}</Texto>
      </View>
      <Tenue>P presente · T tarde · J justificada · A ausencia. Mantener apretado borra.</Tenue>
      <ScrollView horizontal>
        <View>
          <View style={{ flexDirection: 'row' }}>
            <View style={{ width: 140 }} />
            {m.fechas.map((f) => <Texto key={f} style={{ width: 36, textAlign: 'center', fontSize: 11 }}>{f.slice(8)}</Texto>)}
          </View>
          {m.alumnos.map((a) => (
            <View key={a.alumno_id} style={{ flexDirection: 'row', alignItems: 'center', minHeight: 40 }}>
              <Texto numberOfLines={1} style={{ width: 140 }}>{a.nombre}</Texto>
              {m.fechas.map((f) => {
                const celda = a.celdas[f];
                const siguiente = () => {
                  if (!m.puede_editar) return;
                  const i = celda ? tipos.indexOf(celda.tipo) : -1;
                  void guardar(a.alumno_id, f, tipos[(i + 1) % tipos.length] ?? 'presente');
                };
                return (
                  <Pressable key={f} onPress={siguiente} onLongPress={() => m.puede_editar && void guardar(a.alumno_id, f, null)} style={{ width: 36, height: 36, alignItems: 'center', justifyContent: 'center' }}>
                    <Texto style={{ fontWeight: '800', color: celda ? C.acento : C.tenue }}>{celda ? (CORTO[celda.tipo] ?? '·') : '·'}</Texto>
                  </Pressable>
                );
              })}
            </View>
          ))}
        </View>
      </ScrollView>
    </Pantalla>
  );
}
