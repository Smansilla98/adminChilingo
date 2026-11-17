import { Stack } from 'expo-router';

import { CalendarioCiclo } from '@/features/ciclo/CalendarioCiclo';

export default function CalendarioAcademico() {
  return (
    <>
      <Stack.Screen options={{ title: 'Calendario académico' }} />
      <CalendarioCiclo />
    </>
  );
}
