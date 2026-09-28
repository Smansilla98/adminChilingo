import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormProfesor, type Profesor } from '@/features/profesores/FormProfesor';
import { useDetalle } from '@/lib/recursos';

export default function EditarProfesor() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Profesor>('profesores', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar docente' }} />
      <FormProfesor profesor={q.data} />
    </>
  );
}
