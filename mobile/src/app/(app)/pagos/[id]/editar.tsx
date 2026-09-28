import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { FormPago } from '@/features/finanzas/FormPago';
import type { Pago } from '@/features/finanzas/tipos';
import { useDetalle } from '@/lib/recursos';

export default function EditarPago() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Pago>('pagos', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: `Editar pago #${q.data.id}` }} />
      <FormPago pago={q.data} />
    </>
  );
}
