import { Stack, useLocalSearchParams } from 'expo-router';

import { FormEvento } from '@/features/agenda/FormEvento';

export default function NuevoEvento() {
  const { sede_id } = useLocalSearchParams<{ sede_id?: string }>();
  return (
    <>
      <Stack.Screen options={{ title: 'Nuevo evento' }} />
      <FormEvento sedeInicial={sede_id ? Number(sede_id) : undefined} />
    </>
  );
}
