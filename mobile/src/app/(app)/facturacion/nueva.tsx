import { Stack } from 'expo-router';

import { FormFacturacion } from '@/features/finanzas/FormFacturacion';

export default function NuevaFacturacion() {
  return (
    <>
      <Stack.Screen options={{ title: 'Cargar facturación' }} />
      <FormFacturacion />
    </>
  );
}
