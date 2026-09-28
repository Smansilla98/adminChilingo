import { Stack } from 'expo-router';

import { FormPersona } from '@/features/personas/FormPersona';

export default function NuevaPersona() {
  return (
    <>
      <Stack.Screen options={{ title: 'Nueva persona' }} />
      <FormPersona />
    </>
  );
}
