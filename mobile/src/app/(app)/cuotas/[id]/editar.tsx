import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormCuota } from '@/features/finanzas/FormCuota';
import type { CuotaFicha } from '@/features/finanzas/tipos';
import { useDetalle } from '@/lib/recursos';

export default function EditarCuota() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<CuotaFicha>('cuotas', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar cuota' }} />
      <FormCuota cuota={q.data} />
    </>
  );
}
