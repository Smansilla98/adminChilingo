import { Stack, useLocalSearchParams } from 'expo-router';

import { FormBloque } from '@/features/agenda/FormBloque';

export default function NuevoBloque() {
  const { sede_id } = useLocalSearchParams<{ sede_id?: string }>();
  return (
    <>
      <Stack.Screen options={{ title: 'Nuevo bloque' }} />
      <FormBloque sedeInicial={sede_id ? Number(sede_id) : undefined} />
    </>
  );
}
