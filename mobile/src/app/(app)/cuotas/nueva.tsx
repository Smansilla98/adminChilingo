import { Stack } from 'expo-router';

import { FormCuota } from '@/features/finanzas/FormCuota';

export default function NuevaCuota() {
  return (
    <>
      <Stack.Screen options={{ title: 'Nueva cuota' }} />
      <FormCuota />
    </>
  );
}
