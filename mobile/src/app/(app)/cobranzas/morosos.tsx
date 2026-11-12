import { Stack } from 'expo-router';

import { Morosos } from '@/features/cobranzas/Morosos';

export default function PantallaMorosos() {
  return (
    <>
      <Stack.Screen options={{ title: 'Morosos' }} />
      <Morosos />
    </>
  );
}
