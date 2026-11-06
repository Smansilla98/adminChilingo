import { Stack } from 'expo-router';

import { CambiarClave } from '@/features/cuenta/CambiarClave';
import { useMe } from '@/lib/queries';
import { C } from '@/lib/theme';

/**
 * Pila de pantallas autenticadas. Cada pantalla define su título con
 * <Stack.Screen options={{ title }} /> dentro del propio archivo.
 * Quien entró con una contraseña provisoria primero elige la suya.
 */
export default function AppLayout() {
  const me = useMe();
  if (me.data?.debe_cambiar_clave) {
    return <CambiarClave obligatoria usuario={me.data.usuario.username} alTerminar={() => void me.refetch()} />;
  }

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
