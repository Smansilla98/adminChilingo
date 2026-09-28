import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { type AlumnoFicha, FormAlumno } from '@/features/alumnos/FormAlumno';
import { useDetalle } from '@/lib/recursos';

export default function EditarAlumno() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<AlumnoFicha>('alumnos', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar alumno' }} />
      <FormAlumno alumno={q.data} />
    </>
  );
}
