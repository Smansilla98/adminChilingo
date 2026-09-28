import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { type Facturacion, FormFacturacion } from '@/features/finanzas/FormFacturacion';
import { useDetalle } from '@/lib/recursos';

export default function EditarFacturacion() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Facturacion>('facturacion', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: `${q.data.mes_nombre} ${q.data.anio} · ${q.data.sede?.nombre ?? 'Escuela'}` }} />
      <FormFacturacion item={q.data} />
    </>
  );
}
