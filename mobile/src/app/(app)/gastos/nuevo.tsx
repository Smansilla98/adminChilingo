import { Stack } from 'expo-router';

import { FormGasto } from '@/features/finanzas/FormGasto';

export default function NuevoGasto() {
  return (
    <>
      <Stack.Screen options={{ title: 'Registrar gasto' }} />
      <FormGasto />
    </>
  );
}
