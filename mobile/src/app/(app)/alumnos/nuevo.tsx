import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormAlumno } from '@/features/alumnos/FormAlumno';
import type { Persona } from '@/features/personas/tipos';
import { useDetalle } from '@/lib/recursos';

/** Inscribir alumno. Con ?persona_id= inscribe a una persona existente. */
export default function NuevoAlumno() {
  const { persona_id } = useLocalSearchParams<{ persona_id?: string }>();
  const persona = useDetalle<Persona>('personas', persona_id, { habilitado: !!persona_id });
  if (persona_id && persona.isPending) return <Cargando />;
  if (persona_id && persona.isError) return <ErrorVista error={persona.error} onReintentar={() => persona.refetch()} />;
  const p = persona.data;
  return (
    <>
      <Stack.Screen options={{ title: p ? 'Inscribir como alumno' : 'Nuevo alumno' }} />
      <FormAlumno persona={p ? { id: p.id, nombre: p.nombre_completo, dni: p.dni, telefono: p.telefono, fecha_nacimiento: p.fecha_nacimiento, es_docente: !!p.profesor } : undefined} />
    </>
  );
}
