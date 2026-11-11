import { Stack } from 'expo-router';

import { CuotasLote } from '@/features/cobranzas/CuotasLote';

export default function PantallaCuotasLote() {
  return (
    <>
      <Stack.Screen options={{ title: 'Cuotas por lote' }} />
      <CuotasLote />
    </>
  );
}
