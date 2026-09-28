import { CampoFecha, Campo, ErrorFormulario, Selector, useFormulario } from '@/components/form';
import { Hoja } from '@/components/feedback';
import { Boton, Tenue } from '@/components/ui';
import { api } from '@/lib/api';
import { useOperacion } from '@/lib/recursos';

import { type Beca, ESTADOS_BECA } from './tipos';

/** Cambiar estado, fin de vigencia u observaciones de una beca (hoja inferior). */
export function EditarBeca({ beca, onCerrar }: { beca: Beca | null; onCerrar: () => void }) {
  return (
    <Hoja visible={!!beca} titulo={beca ? `${beca.etiqueta} · ${beca.alumno?.nombre ?? ''}` : ''} onCerrar={onCerrar}>
      {beca && <Contenido key={beca.id} beca={beca} onCerrar={onCerrar} />}
    </Hoja>
  );
}

function Contenido({ beca, onCerrar }: { beca: Beca; onCerrar: () => void }) {
  const f = useFormulario({ estado: beca.estado as string, fecha_fin: beca.hasta ?? '', observaciones: beca.observaciones ?? '' });
  const guardar = useOperacion(
    (v: typeof f.valores) => api(`becas/${beca.id}`, { method: 'PUT', body: { ...v, fecha_fin: v.fecha_fin || null, observaciones: v.observaciones || null } }),
    { exito: 'Beca actualizada', invalidar: ['becas', 'personas', 'alumnos'], alTerminar: onCerrar },
  );
  return (
    <>
      <Tenue>Vigente desde {beca.desde}. El cambio se refleja en el estado de cuenta.</Tenue>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Selector etiqueta="Estado" opciones={[...ESTADOS_BECA]} valor={f.valores.estado} onChange={(v) => f.set('estado', v ?? 'activa')} error={f.errores.estado} />
      <CampoFecha etiqueta="Fin de vigencia" valor={f.valores.fecha_fin} onChange={(v) => f.set('fecha_fin', v)} error={f.errores.fecha_fin} opcional minimo={beca.desde} />
      <Campo etiqueta="Observaciones" valor={f.valores.observaciones} onChange={(v) => f.set('observaciones', v)} error={f.errores.observaciones} multilinea />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((v) => guardar.mutateAsync(v))} />
    </>
  );
}
