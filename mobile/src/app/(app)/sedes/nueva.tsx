import { Stack } from 'expo-router';

import { FormSede } from '@/features/agenda/FormSede';

export default function NuevaSede() {
  return (
    <>
      <Stack.Screen options={{ title: 'Nueva sede' }} />
      <FormSede />
    </>
  );
}
