import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormShow } from '@/features/agenda/FormShow';
import type { Show } from '@/features/agenda/tipos';
import { useDetalle } from '@/lib/recursos';

export default function EditarShow() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Show>('shows', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar show' }} />
      <FormShow show={q.data} />
    </>
  );
}
