import { Stack } from 'expo-router';

import { C } from '@/lib/theme';

/**
 * Pila de pantallas autenticadas. Cada pantalla define su título con
 * <Stack.Screen options={{ title }} /> dentro del propio archivo.
 */
export default function AppLayout() {
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: C.fondo },
        headerTintColor: C.texto,
        headerTitleStyle: { fontWeight: '700' },
        contentStyle: { backgroundColor: C.fondo },
        headerBackTitle: 'Volver',
      }}>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="inventario/escanear" options={{ title: 'Escanear QR', presentation: 'modal' }} />
    </Stack>
  );
}
