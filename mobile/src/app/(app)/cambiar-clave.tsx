import { router, Stack } from 'expo-router';

import { CambiarClave } from '@/features/cuenta/CambiarClave';

export default function CambiarClavePantalla() {
  return (
    <>
      <Stack.Screen options={{ title: 'Cambiar mi contraseña' }} />
      <CambiarClave alTerminar={() => router.back()} />
    </>
  );
}
