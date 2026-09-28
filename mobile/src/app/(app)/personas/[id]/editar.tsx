import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormPersona } from '@/features/personas/FormPersona';
import type { Persona } from '@/features/personas/tipos';
import { useDetalle } from '@/lib/recursos';

export default function EditarPersona() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Persona>('personas', id);

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar datos' }} />
      <FormPersona persona={q.data} />
    </>
  );
}
