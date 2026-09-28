import { router, Stack, useLocalSearchParams } from 'expo-router';

import { Campo, CampoFecha, CampoMonto, ErrorFormulario, hoyIso, requeridos, Seccion, Selector, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla, Tenue } from '@/components/ui';
import type { Persona } from '@/features/personas/tipos';
import { api } from '@/lib/api';
import { useDetalle, useOperacion } from '@/lib/recursos';

/** Otorgar una beca a una de las fichas de alumno de la persona. */
export default function OtorgarBeca() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Persona>('personas', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return <Formulario persona={q.data} />;
}

function Formulario({ persona }: { persona: Persona }) {
  const alumnos = persona.alumnos.filter((a) => a.puede_gestionar_becas);
  const f = useFormulario({
    alumno_id: (alumnos[0]?.id ?? null) as number | null,
    tipo: 'porcentaje' as string,
    porcentaje: '',
    monto: '',
    bloque_id: null as number | null,
    motivo: '',
    fecha_inicio: hoyIso(),
    fecha_fin: '',
    observaciones: '',
  });
  const { valores: v, set, errores: e } = f;
  const alumno = alumnos.find((a) => a.id === v.alumno_id);

  const otorgar = useOperacion(
    (d: typeof v) => api(`personas/${persona.id}/becas`, {
      method: 'POST',
      body: {
        ...d,
        porcentaje: d.tipo === 'porcentaje' ? Number(d.porcentaje) || null : null,
        monto: d.tipo === 'monto_fijo' ? Number(d.monto) || null : null,
        fecha_fin: d.fecha_fin || null,
        motivo: d.motivo || null,
        observaciones: d.observaciones || null,
      },
    }),
    { exito: 'Beca otorgada. Se aplica al estado de cuenta desde la fecha de inicio', invalidar: ['personas', 'becas', 'alumnos'], alTerminar: () => router.back() },
  );

  if (alumnos.length === 0) return <Pantalla><Tenue>No tenés permiso para otorgar becas a esta persona.</Tenue></Pantalla>;

  return (
    <Pantalla>
      <Stack.Screen options={{ title: `Beca · ${persona.nombre_completo}` }} />
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Beca">
        {alumnos.length > 1 && (
          <Selector etiqueta="Ficha de alumno" opciones={alumnos.map((a) => ({ valor: a.id, etiqueta: a.bloques.map((b) => b.nombre).join(', ') || `#${a.id}` }))} valor={v.alumno_id} onChange={(x) => set('alumno_id', x)} error={e.alumno_id} requerido />
        )}
        <Selector etiqueta="Tipo" opciones={Object.entries(persona.tipos_beca).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.tipo} onChange={(x) => set('tipo', x ?? 'porcentaje')} error={e.tipo} requerido />
        {v.tipo === 'porcentaje' && <Campo etiqueta="Porcentaje" requerido valor={v.porcentaje} onChange={(x) => set('porcentaje', x.replace(/[^0-9.]/g, ''))} teclado="decimal-pad" error={e.porcentaje} placeholder="Ej.: 50" />}
        {v.tipo === 'monto_fijo' && <CampoMonto etiqueta="Descuento por cuota ($)" requerido valor={v.monto} onChange={(x) => set('monto', x)} error={e.monto} />}
        {alumno && alumno.bloques.length > 0 && (
          <Selector etiqueta="Aplica a" permitirVacio="Todas sus cuotas" opciones={alumno.bloques.map((b) => ({ valor: b.id, etiqueta: b.nombre }))} valor={v.bloque_id} onChange={(x) => set('bloque_id', x)} error={e.bloque_id} />
        )}
        <Campo etiqueta="Motivo" valor={v.motivo} onChange={(x) => set('motivo', x)} error={e.motivo} />
      </Seccion>
      <Seccion titulo="Vigencia">
        <CampoFecha etiqueta="Desde" requerido valor={v.fecha_inicio} onChange={(x) => set('fecha_inicio', x)} error={e.fecha_inicio} />
        <CampoFecha etiqueta="Hasta" valor={v.fecha_fin} onChange={(x) => set('fecha_fin', x)} error={e.fecha_fin} opcional minimo={v.fecha_inicio} />
        <Campo etiqueta="Observaciones" valor={v.observaciones} onChange={(x) => set('observaciones', x)} error={e.observaciones} multilinea />
      </Seccion>
      <Boton
        titulo="Otorgar beca"
        icono="volunteer-activism"
        grande
        cargando={f.enviando}
        onPress={() => void f.enviar((d) => otorgar.mutateAsync(d), (d) => ({
          ...requeridos(d, { alumno_id: 'La ficha de alumno', tipo: 'El tipo', fecha_inicio: 'La fecha de inicio' }),
          ...(d.tipo === 'porcentaje' && !(Number(d.porcentaje) >= 1 && Number(d.porcentaje) <= 100) ? { porcentaje: 'Ingresá un porcentaje entre 1 y 100.' } : {}),
          ...(d.tipo === 'monto_fijo' && !(Number(d.monto) >= 1) ? { monto: 'Ingresá el monto del descuento.' } : {}),
        }))}
      />
    </Pantalla>
  );
}
