import { router, Stack, useLocalSearchParams } from 'expo-router';

import { Campo, CampoFecha, CampoHora, ErrorFormulario, hoyIso, requeridos, Seccion, useFormulario } from '@/components/form';
import { Boton, Pantalla } from '@/components/ui';
import type { Ensayo } from '@/features/shows/tipos';
import { api } from '@/lib/api';
import { useOperacion } from '@/lib/recursos';

export default function NuevoEnsayo() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const f = useFormulario({ fecha: hoyIso(), hora_inicio: '', hora_fin: '', lugar: '', notas: '' });
  const { valores: v, set, errores: e } = f;
  const crear = useOperacion(
    (d: typeof v) => api<{ data: Ensayo }>(`shows/${id}/ensayos`, {
      method: 'POST',
      body: { ...d, hora_inicio: d.hora_inicio || null, hora_fin: d.hora_fin || null, lugar: d.lugar || null, notas: d.notas || null },
    }),
    { exito: 'Ensayo agregado', invalidar: ['shows'], alTerminar: () => router.back() },
  );

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Nuevo ensayo' }} />
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Ensayo">
        <CampoFecha etiqueta="Fecha" requerido valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.fecha} />
        <CampoHora etiqueta="Desde" opcional valor={v.hora_inicio} onChange={(x) => set('hora_inicio', x)} error={e.hora_inicio} />
        <CampoHora etiqueta="Hasta" opcional valor={v.hora_fin} onChange={(x) => set('hora_fin', x)} error={e.hora_fin} />
        <Campo etiqueta="Lugar" valor={v.lugar} onChange={(x) => set('lugar', x)} error={e.lugar} />
        <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} />
      </Seccion>
      <Boton titulo="Agregar ensayo" icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => crear.mutateAsync(d), (d) => requeridos(d, { fecha: 'La fecha' }))} />
    </Pantalla>
  );
}
