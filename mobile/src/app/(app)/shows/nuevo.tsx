import { Stack } from 'expo-router';

import { FormShow } from '@/features/agenda/FormShow';

export default function NuevoShow() {
  return (
    <>
      <Stack.Screen options={{ title: 'Nuevo show' }} />
      <FormShow />
    </>
  );
}
