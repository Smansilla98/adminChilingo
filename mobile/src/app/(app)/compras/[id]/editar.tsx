import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormOrden, type Orden } from '@/features/compras/FormOrden';
import { useDetalle } from '@/lib/recursos';

export default function EditarOrden() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Orden>('compras', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: `Editar orden #${q.data.id}` }} />
      <FormOrden orden={q.data} />
    </>
  );
}
