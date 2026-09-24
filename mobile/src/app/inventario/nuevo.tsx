import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { Aviso, Boton, Pantalla, Tenue } from '@/components/ui';
import { api } from '@/lib/api';
import { C, E, TOQUE } from '@/lib/theme';
import type { InventarioItem } from '@/lib/types';

interface Catalogos { tipos: Record<string, string>; estados: Record<string, string> }

export default function NuevoItem() {
  const sedes = useQuery({ queryKey: ['sedes'], queryFn: () => api<{ data: { id: number; nombre: string }[] }>('sedes').then((r) => r.data) });
  const catalogos = useQuery({ queryKey: ['inventario', 'catalogos'], queryFn: () => api<Catalogos>('inventario/catalogos'), staleTime: 24 * 3600_000 });
  const qc = useQueryClient();
  const [f, setF] = useState({ nombre: '', sede_id: 0, tipo: 'instrumento', estado: 'bueno', marca: '', medida: '', codigo: '' });
  const set = (k: keyof typeof f, v: string | number) => setF((x) => ({ ...x, [k]: v }));

  const crear = useMutation({
    mutationFn: () => api<{ data: InventarioItem }>('inventario', {
      method: 'POST',
      body: { ...f, cantidad: 1, propietario_tipo: 'escuela', codigo: f.codigo || undefined, marca: f.marca || undefined, medida: f.medida || undefined },
    }),
    onSuccess: (r) => {
      void qc.invalidateQueries({ queryKey: ['inventario'] });
      router.replace({ pathname: '/inventario/[id]', params: { id: String(r.data.id) } } as never);
    },
  });

  return (
    <Pantalla>
      <Campo etiqueta="Nombre *" valor={f.nombre} onChange={(v) => set('nombre', v)} placeholder='Ej.: Surdo 22"' />
      <Tenue>Sede *</Tenue>
      <Opciones opciones={(sedes.data ?? []).map((s) => [String(s.id), s.nombre])} valor={String(f.sede_id)} onChange={(v) => set('sede_id', Number(v))} />
      <Tenue>Tipo</Tenue>
      <Opciones opciones={Object.entries(catalogos.data?.tipos ?? {})} valor={f.tipo} onChange={(v) => set('tipo', v)} />
      <Tenue>Estado</Tenue>
      <Opciones opciones={Object.entries(catalogos.data?.estados ?? {})} valor={f.estado} onChange={(v) => set('estado', v)} />
      <Campo etiqueta="Marca" valor={f.marca} onChange={(v) => set('marca', v)} />
      <Campo etiqueta="Medida" valor={f.medida} onChange={(v) => set('medida', v)} placeholder='Ej.: 22"' />
      <Campo etiqueta="Código de etiqueta (opcional)" valor={f.codigo} onChange={(v) => set('codigo', v)} placeholder="Se genera solo si lo dejás vacío" />
      {crear.isError && <Aviso tono="peligro" texto={(crear.error as Error).message} />}
      <Boton titulo="Guardar" icono="save" onPress={() => crear.mutate()} cargando={crear.isPending} deshabilitado={!f.nombre || !f.sede_id} grande />
    </Pantalla>
  );
}

function Campo({ etiqueta, valor, onChange, placeholder }: { etiqueta: string; valor: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <View style={{ gap: E.xs }}>
      <Tenue>{etiqueta}</Tenue>
      <TextInput style={s.input} value={valor} onChangeText={onChange} placeholder={placeholder} placeholderTextColor={C.tenue} accessibilityLabel={etiqueta} />
    </View>
  );
}

function Opciones({ opciones, valor, onChange }: { opciones: [string, string][]; valor: string; onChange: (v: string) => void }) {
  return (
    <View style={s.opciones}>
      {opciones.map(([clave, nombre]) => (
        <Pressable key={clave} onPress={() => onChange(clave)} accessibilityRole="button" accessibilityState={{ selected: valor === clave }} style={[s.opcion, valor === clave && { backgroundColor: C.acento, borderColor: C.acento }]}>
          <Text style={{ color: C.texto, fontWeight: '600' }}>{nombre}</Text>
        </Pressable>
      ))}
    </View>
  );
}

const s = StyleSheet.create({
  input: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 17, paddingHorizontal: E.l, minHeight: TOQUE + 4 },
  opciones: { flexDirection: 'row', flexWrap: 'wrap', gap: E.s },
  opcion: { borderRadius: 999, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, paddingHorizontal: E.l, minHeight: 44, justifyContent: 'center' },
});
