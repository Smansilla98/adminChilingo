import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormSede } from '@/features/agenda/FormSede';
import type { Sede } from '@/features/agenda/tipos';
import { useDetalle } from '@/lib/recursos';

export default function EditarSede() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Sede>('sedes', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar sede' }} />
      <FormSede sede={q.data} />
    </>
  );
}
