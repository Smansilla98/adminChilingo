import { useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { FiltrosChips } from '@/components/lista';
import { Aviso, Boton, Cargando, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { type EstadoToque, type MatrizBloque, SIGUIENTE_ESTADO, useMatriz } from '@/features/progreso/tipos';
import { api } from '@/lib/api';
import { C, E } from '@/lib/theme';

const COLOR: Record<EstadoToque, string> = { no_empezado: C.tenue, en_proceso: C.alerta, aprobado: C.exito };
const MARCA: Record<EstadoToque, string> = { no_empezado: '○', en_proceso: '◐', aprobado: '●' };

/**
 * Grilla del bloque en el celular: una tarjeta por alumno con sus toques del año.
 * Tocar un toque pasa al estado siguiente (Todavía no → Lo está trabajando → Lo toca con el bloque).
 */
export default function ProgresoBloque() {
  const { bloque, nombre } = useLocalSearchParams<{ bloque: string; nombre?: string }>();
  const id = Number(bloque);
  const [anio, setAnio] = useState<number | null>(null);
  const q = useMatriz(id, anio);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [guardando, setGuardando] = useState<string | null>(null);

  const clave = ['progreso', 'bloque', id, anio];
  const cambiar = async (alumnoId: number, toqueId: number, actual: EstadoToque) => {
    const estado = SIGUIENTE_ESTADO[actual];
    setError(null);
    setGuardando(`${alumnoId}-${toqueId}`);
    // Cambio optimista: la celda cambia al instante y se corrige si falla.
    qc.setQueryData<MatrizBloque>(clave, (d) => d && { ...d, alumnos: d.alumnos.map((a) => (a.id === alumnoId ? { ...a, estados: { ...a.estados, [toqueId]: estado } } : a)) });
    try {
      await api(`progreso/bloques/${id}/estado`, { method: 'POST', body: { alumno_id: alumnoId, programa_ritmo_id: toqueId, estado } });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo guardar.');
    } finally {
      setGuardando(null);
      void qc.invalidateQueries({ queryKey: clave });
    }
  };

  const resolver = async (propuesta: number, accion: 'confirmar' | 'descartar') => {
    setError(null);
    try {
      await api(`progreso/promociones/${propuesta}/${accion}`, { method: 'POST' });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo guardar.');
    }
    void qc.invalidateQueries({ queryKey: ['progreso'] });
  };

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const m = q.data!;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: nombre ?? m.bloque.nombre }} />
      <FiltrosChips todos={false} opciones={[1, 2, 3, 4, 5, 6, 7].map((n) => ({ valor: n, etiqueta: `${n}°` }))} valor={m.anio} onChange={(v) => setAnio(v ?? null)} />
      <Tenue>{m.puede_editar ? 'Tocá un toque para cambiarlo: ○ Todavía no · ◐ Lo está trabajando · ● Lo toca con el bloque.' : '○ Todavía no · ◐ Lo está trabajando · ● Lo toca con el bloque.'}</Tenue>
      {error && <Aviso tono="peligro" texto={error} />}

      {m.propuestas.length > 0 && (
        <Tarjeta acento={C.exito}>
          <Subtitulo>Paso de año para confirmar</Subtitulo>
          {m.propuestas.map((p) => (
            <View key={p.id} style={{ gap: E.xs }}>
              <Texto>{p.alumno}: de {p.anio_desde}° a {p.anio_hasta}° año</Texto>
              {m.puede_editar && (
                <Fila>
                  <View style={{ flex: 1 }}><Boton titulo="Confirmar" icono="check" onPress={() => void resolver(p.id, 'confirmar')} /></View>
                  <View style={{ flex: 1 }}><Boton titulo="Todavía no" variante="secundario" onPress={() => void resolver(p.id, 'descartar')} /></View>
                </Fila>
              )}
            </View>
          ))}
        </Tarjeta>
      )}

      {m.toques.length === 0 && <Vacio icono="music-note" texto={`No hay toques cargados para ${m.anio}° año.`} />}
      {m.toques.length > 0 && m.alumnos.length === 0 && <Vacio icono="groups" texto="El bloque no tiene alumnos activos." />}
      {m.toques.length > 0 && m.alumnos.map((a) => (
        <Tarjeta key={a.id}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '800', flex: 1 }}>{a.nombre}</Texto>
            <Tenue>{a.obligatorios_tocados}/{a.obligatorios}</Tenue>
          </Fila>
          <View style={s.grilla}>
            {m.toques.map((t) => {
              const estado = a.estados[t.id] ?? 'no_empezado';
              return (
                <Pressable
                  key={t.id}
                  disabled={!m.puede_editar || guardando === `${a.id}-${t.id}`}
                  accessibilityRole="button"
                  accessibilityLabel={`${a.nombre}, ${t.nombre}: ${m.estados[estado]}`}
                  onPress={() => void cambiar(a.id, t.id, estado)}
                  style={({ pressed }) => [s.celda, { borderColor: COLOR[estado] }, pressed && { opacity: 0.6 }]}>
                  <Text style={[s.marca, { color: COLOR[estado] }]}>{MARCA[estado]}</Text>
                  <Text style={s.toque} numberOfLines={2}>{t.nombre}{t.opcional ? ' (op.)' : ''}</Text>
                </Pressable>
              );
            })}
          </View>
        </Tarjeta>
      ))}
    </Pantalla>
  );
}

const s = StyleSheet.create({
  grilla: { flexDirection: 'row', flexWrap: 'wrap', gap: E.s },
  celda: { flexDirection: 'row', alignItems: 'center', gap: E.xs, borderWidth: 2, borderRadius: 10, paddingHorizontal: E.s, minHeight: 44, maxWidth: '100%' },
  marca: { fontSize: 18, fontWeight: '800' },
  toque: { color: C.texto, fontWeight: '600' },
});
