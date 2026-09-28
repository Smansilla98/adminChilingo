import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { api } from '@/lib/api';
import { COLOR_ESTADO_ITEM } from '@/lib/formato';
import { useInventarioItem } from '@/lib/queries';
import { C, E, TOQUE } from '@/lib/theme';

interface Catalogos {
  estados: Record<string, string>;
  movimientos: Record<string, string>;
}

export default function ItemDetalle() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const item = useInventarioItem(Number(id));
  const catalogos = useQuery({ queryKey: ['inventario', 'catalogos'], queryFn: () => api<Catalogos>('inventario/catalogos'), staleTime: 24 * 3600_000 });
  const qc = useQueryClient();
  const [tipo, setTipo] = useState('reparacion');
  const [estado, setEstado] = useState<string | null>(null);
  const [nota, setNota] = useState('');
  const [ok, setOk] = useState<string | null>(null);

  const mover = useMutation({
    mutationFn: () => api(`inventario/${id}/movimientos`, { method: 'POST', body: { tipo, estado: estado ?? undefined, nota: nota || undefined } }),
    onSuccess: () => {
      setOk('Movimiento registrado.');
      setNota('');
      setEstado(null);
      void qc.invalidateQueries({ queryKey: ['inventario'] });
    },
  });

  if (item.isPending && !item.data) return <Cargando />;
  if (item.isError && !item.data) return <ErrorVista error={item.error} onReintentar={() => item.refetch()} />;
  const i = item.data!;

  return (
    <Pantalla refrescando={item.isRefetching} onRefrescar={() => item.refetch()}>
      <Stack.Screen options={{ title: i.codigo ?? 'Instrumento' }} />
      <Titulo>{i.nombre}</Titulo>
      <Fila style={{ flexWrap: 'wrap' }}>
        <Chip texto={i.estado_nombre} color={COLOR_ESTADO_ITEM[i.estado] ?? C.tenue} />
        {i.codigo && <Chip texto={i.codigo} />}
        <Chip texto={i.tipo_nombre} />
      </Fila>
      <Tarjeta>
        <Texto>Sede: {i.sede?.nombre ?? '—'}</Texto>
        <Texto>Propietario: {i.propietario === 'alumno' ? 'Alumno' : 'Escuela'}</Texto>
        {(i.marca || i.modelo || i.medida) && <Texto>{[i.marca, i.modelo, i.medida].filter(Boolean).join(' · ')}</Texto>}
        {i.notas && <Tenue>{i.notas}</Tenue>}
      </Tarjeta>

      {i.puede_editar && catalogos.data && (
        <>
          <Subtitulo>Registrar movimiento</Subtitulo>
          <View style={s.opciones}>
            {Object.entries(catalogos.data.movimientos).map(([clave, nombre]) => (
              <Opcion key={clave} texto={nombre} activa={tipo === clave} onPress={() => setTipo(clave)} />
            ))}
          </View>
          <Tenue>Estado (opcional)</Tenue>
          <View style={s.opciones}>
            {Object.entries(catalogos.data.estados).map(([clave, nombre]) => (
              <Opcion key={clave} texto={nombre} activa={estado === clave} onPress={() => setEstado(estado === clave ? null : clave)} />
            ))}
          </View>
          <TextInput style={s.input} placeholder="Nota (ej.: parche roto, sale a show)" placeholderTextColor={C.tenue} value={nota} onChangeText={setNota} maxLength={400} multiline accessibilityLabel="Nota del movimiento" />
          {mover.isError && <Aviso tono="peligro" texto={(mover.error as Error).message} />}
          {ok && <Aviso tono="exito" texto={ok} />}
          <Boton titulo="Guardar movimiento" icono="save" onPress={() => mover.mutate()} cargando={mover.isPending} grande />
        </>
      )}

      {!!i.movimientos?.length && (
        <>
          <Subtitulo>Historial</Subtitulo>
          {i.movimientos.map((m) => (
            <Tarjeta key={m.id}>
              <Texto style={{ fontWeight: '700' }}>{m.tipo_nombre}</Texto>
              <Tenue>{new Date(m.fecha).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' })}{m.autor ? ` · ${m.autor}` : ''}{m.sede ? ` · ${m.sede}` : ''}</Tenue>
              {m.nota && <Texto>{m.nota}</Texto>}
            </Tarjeta>
          ))}
        </>
      )}
    </Pantalla>
  );
}

function Opcion({ texto, activa, onPress }: { texto: string; activa: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityState={{ selected: activa }} style={[s.opcion, activa && { backgroundColor: C.acento, borderColor: C.acento }]}>
      <Text style={{ color: C.texto, fontWeight: '600' }}>{texto}</Text>
    </Pressable>
  );
}

const s = StyleSheet.create({
  opciones: { flexDirection: 'row', flexWrap: 'wrap', gap: E.s },
  opcion: { borderRadius: 999, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, paddingHorizontal: E.l, minHeight: 44, justifyContent: 'center' },
  input: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 16, padding: E.m, minHeight: TOQUE * 1.5 },
});
