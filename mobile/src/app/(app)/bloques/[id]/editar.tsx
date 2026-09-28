import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista } from '@/components/ui';
import { type BloqueFicha, FormBloque } from '@/features/agenda/FormBloque';
import { useDetalle } from '@/lib/recursos';

export default function EditarBloque() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<BloqueFicha>('bloques', id);
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <>
      <Stack.Screen options={{ title: 'Editar bloque' }} />
      <FormBloque bloque={q.data} />
    </>
  );
}
