import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { Campo, CampoHora, ErrorFormulario, requeridos, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Fila, Icon, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import type { DiaGira, Tocada } from '@/features/villa-gesell/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

/** Calendario de la gira: días con sus tocadas (qué, dónde, a qué hora). */
export default function CalendarioGira() {
  const q = useQuery({ queryKey: ['villa-gesell', 'calendario'], queryFn: () => api<{ data: DiaGira[] }>('villa-gesell/calendario').then((r) => r.data) });
  const [editando, setEditando] = useState<{ dia: DiaGira; tocada?: Tocada } | null>(null);
  const [notasDe, setNotasDe] = useState<DiaGira | null>(null);
  const invalidar = [['villa-gesell', 'calendario']];
  const slots = useOperacion((dia: number) => api(`villa-gesell/dias/${dia}/slots`, { method: 'POST', body: { cantidad: 3 } }), { exito: 'Se agregaron 3 fechas por definir', invalidar });
  const borrar = useOperacion((t: number) => api(`villa-gesell/tocadas/${t}`, { method: 'DELETE' }), { exito: 'Fecha eliminada', invalidar });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Calendario de la gira' }} />
      {q.data.length === 0 && <Vacio icono="event" texto="Definí las fechas de la gira para armar el calendario." />}
      {q.data.map((d) => (
        <Tarjeta key={d.id}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '800', fontSize: 17, flex: 1 }}>{formatearFecha(d.fecha)}</Texto>
            <Pressable onPress={() => setNotasDe(d)} accessibilityRole="button" accessibilityLabel={`Notas del ${formatearFecha(d.fecha)}`} hitSlop={10}><Icon name="sticky-note-2" size={22} color={C.tenue} /></Pressable>
          </Fila>
          {d.notas && <Tenue>{d.notas}</Tenue>}
          {d.tocadas.length === 0 && <Tenue>Sin tocadas.</Tenue>}
          {d.tocadas.map((t) => (
            <Pressable key={t.id} onPress={() => setEditando({ dia: d, tocada: t })} accessibilityRole="button" accessibilityLabel={`Editar ${t.que}`}
              style={({ pressed }) => [{ flexDirection: 'row', gap: E.s, alignItems: 'center', minHeight: 44, borderTopWidth: 1, borderTopColor: C.borde, paddingTop: E.s }, pressed && { opacity: 0.7 }]}>
              <Texto style={{ width: 52, fontWeight: '700', color: C.acento }}>{t.hora ?? '--:--'}</Texto>
              <View style={{ flex: 1 }}>
                <Texto style={{ fontWeight: '700' }}>{t.que}</Texto>
                {t.donde && <Tenue>{t.donde}</Tenue>}
              </View>
              <Pressable onPress={async () => { if (await confirmar({ titulo: `¿Eliminar "${t.que}"?`, accion: 'Eliminar' })) borrar.mutate(t.id); }} accessibilityRole="button" accessibilityLabel={`Eliminar ${t.que}`} hitSlop={10}>
                <Icon name="delete-outline" size={22} color={C.peligro} />
              </Pressable>
            </Pressable>
          ))}
          <Fila>
            <View style={{ flex: 1 }}><Boton titulo="Agregar" icono="add" variante="secundario" onPress={() => setEditando({ dia: d })} /></View>
            <View style={{ flex: 1 }}><Boton titulo="3 por definir" icono="playlist-add" variante="secundario" cargando={slots.isPending} onPress={() => slots.mutate(d.id)} /></View>
          </Fila>
        </Tarjeta>
      ))}
      <Hoja visible={!!editando} titulo={editando?.tocada ? 'Editar tocada' : 'Nueva tocada'} onCerrar={() => setEditando(null)}>
        {editando && <FormTocada dia={editando.dia} tocada={editando.tocada} onCerrar={() => setEditando(null)} />}
      </Hoja>
      <Hoja visible={!!notasDe} titulo="Notas del día" onCerrar={() => setNotasDe(null)}>
        {notasDe && <NotasDia dia={notasDe} onCerrar={() => setNotasDe(null)} />}
      </Hoja>
    </Pantalla>
  );
}

function FormTocada({ dia, tocada, onCerrar }: { dia: DiaGira; tocada?: Tocada; onCerrar: () => void }) {
  const f = useFormulario({ hora: tocada?.hora ?? '', que: tocada?.que ?? '', donde: tocada?.donde ?? '', notas: tocada?.notas ?? '', orden: tocada ? String(tocada.orden) : '' });
  const op = useOperacion(
    (d: typeof f.valores) => api(tocada ? `villa-gesell/tocadas/${tocada.id}` : `villa-gesell/dias/${dia.id}/tocadas`, {
      method: tocada ? 'PUT' : 'POST',
      body: { que: d.que, hora: d.hora || null, donde: d.donde || null, notas: d.notas || null, orden: d.orden ? Number(d.orden) : null },
    }),
    { exito: tocada ? 'Fecha actualizada' : 'Fecha agregada', invalidar: [['villa-gesell', 'calendario']], alTerminar: onCerrar },
  );
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Campo etiqueta="Qué" requerido valor={f.valores.que} onChange={(x) => f.set('que', x)} error={f.errores.que} placeholder="Ej.: Corso, show en la peatonal" />
      <CampoHora etiqueta="Hora" valor={f.valores.hora} onChange={(x) => f.set('hora', x)} error={f.errores.hora} opcional />
      <Campo etiqueta="Dónde" valor={f.valores.donde} onChange={(x) => f.set('donde', x)} error={f.errores.donde} />
      <Campo etiqueta="Orden" valor={f.valores.orden} onChange={(x) => f.set('orden', x.replace(/\D/g, ''))} teclado="number-pad" error={f.errores.orden} />
      <Campo etiqueta="Notas" valor={f.valores.notas} onChange={(x) => f.set('notas', x)} error={f.errores.notas} multilinea />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d), (d) => requeridos(d, { que: 'Qué' }))} />
    </>
  );
}

function NotasDia({ dia, onCerrar }: { dia: DiaGira; onCerrar: () => void }) {
  const f = useFormulario({ notas: dia.notas ?? '' });
  const op = useOperacion((d: typeof f.valores) => api(`villa-gesell/dias/${dia.id}`, { method: 'PUT', body: { notas: d.notas || null } }), { exito: 'Notas del día guardadas', invalidar: [['villa-gesell', 'calendario']], alTerminar: onCerrar });
  return (
    <>
      <Campo etiqueta="Notas" valor={f.valores.notas} onChange={(x) => f.set('notas', x)} error={f.errores.notas} multilinea maxLength={400} />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d))} />
    </>
  );
}
