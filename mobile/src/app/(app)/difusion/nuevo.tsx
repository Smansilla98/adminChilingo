import { Stack } from 'expo-router';

import { FormDifusion } from '@/features/difusion/FormDifusion';

export default function NuevaDifusion() {
  return (
    <>
      <Stack.Screen options={{ title: 'Nuevo mensaje' }} />
      <FormDifusion />
    </>
  );
}
