import { Stack } from 'expo-router';

import { MiReinscripcion } from '@/features/reinscripcion/MiReinscripcion';

export default function MiReinscripcionPantalla() {
  return (
    <>
      <Stack.Screen options={{ title: 'Reinscripción' }} />
      <MiReinscripcion />
    </>
  );
}
