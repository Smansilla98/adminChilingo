import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormGasto, type Gasto } from '@/features/finanzas/FormGasto';
import { useDetalle } from '@/lib/recursos';

export default function EditarGasto() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Gasto>('gastos', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar gasto' }} />
      <FormGasto gasto={q.data} />
    </>
  );
}
