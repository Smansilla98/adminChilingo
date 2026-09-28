import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormEvento } from '@/features/agenda/FormEvento';
import type { Evento } from '@/features/agenda/tipos';
import { useDetalle } from '@/lib/recursos';

export default function EditarEvento() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Evento>('eventos', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar evento' }} />
      <FormEvento evento={q.data} />
    </>
  );
}
