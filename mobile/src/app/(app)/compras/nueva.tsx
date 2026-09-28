import { Stack } from 'expo-router';

import { FormOrden } from '@/features/compras/FormOrden';

export default function NuevaOrden() {
  return (
    <>
      <Stack.Screen options={{ title: 'Nueva orden de compra' }} />
      <FormOrden />
    </>
  );
}
