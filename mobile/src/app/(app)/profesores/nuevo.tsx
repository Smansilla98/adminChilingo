import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import type { Persona } from '@/features/personas/tipos';
import { FormProfesor } from '@/features/profesores/FormProfesor';
import { useDetalle } from '@/lib/recursos';

/** Alta de docente. Con ?persona_id= suma al plantel a una persona existente. */
export default function NuevoProfesor() {
  const { persona_id } = useLocalSearchParams<{ persona_id?: string }>();
  const persona = useDetalle<Persona>('personas', persona_id, { habilitado: !!persona_id });

  if (persona_id && persona.isPending) return <Cargando />;
  if (persona_id && persona.isError) return <ErrorVista error={persona.error} onReintentar={() => persona.refetch()} />;
  const p = persona.data;
  return (
    <>
      <Stack.Screen options={{ title: p ? 'Sumar al plantel' : 'Nuevo docente' }} />
      <FormProfesor persona={p ? { id: p.id, nombre: p.nombre_completo, telefono: p.telefono, email: p.email, tiene_cuenta: !!p.cuenta } : undefined} />
    </>
  );
}
