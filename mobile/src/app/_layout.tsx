import AsyncStorage from '@react-native-async-storage/async-storage';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { QueryClient } from '@tanstack/react-query';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { DarkTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';

import { ApiError } from '@/lib/api';
import { AuthProvider, useAuth } from '@/lib/auth';
import { useSincronizacionAutomatica } from '@/lib/offline';
import { registrarPush } from '@/lib/push';
import { C } from '@/lib/theme';

SplashScreen.preventAutoHideAsync();

const DIA = 24 * 60 * 60_000;

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      gcTime: DIA,
      // Con red lenta: mostrar lo cacheado y reintentar solo errores de red/servidor.
      networkMode: 'offlineFirst',
      retry: (intentos, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && intentos < 2,
    },
  },
});

// Caché persistente: la última información consultada se ve aunque no haya señal.
const persister = createAsyncStoragePersister({ storage: AsyncStorage, key: 'ito.cache.v1' });

const tema = { ...DarkTheme, colors: { ...DarkTheme.colors, background: C.fondo, card: C.fondo, primary: C.acento, text: C.texto, border: C.borde } };

export default function RootLayout() {
  return (
    <PersistQueryClientProvider client={queryClient} persistOptions={{ persister, maxAge: DIA, buster: 'v1' }}>
      <AuthProvider>
        <ThemeProvider value={tema}>
          <StatusBar style="light" />
          <Navegacion />
        </ThemeProvider>
      </AuthProvider>
    </PersistQueryClientProvider>
  );
}

function Navegacion() {
  const { listo, autenticado } = useAuth();
  useSincronizacionAutomatica();

  useEffect(() => {
    if (listo) void SplashScreen.hideAsync();
  }, [listo]);

  useEffect(() => {
    if (autenticado) void registrarPush();
  }, [autenticado]);

  if (!listo) return null;

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: C.fondo },
        headerTintColor: C.texto,
        headerTitleStyle: { fontWeight: '700' },
        contentStyle: { backgroundColor: C.fondo },
        headerBackTitle: 'Volver',
      }}>
      <Stack.Protected guard={!autenticado}>
        <Stack.Screen name="login" options={{ headerShown: false }} />
      </Stack.Protected>
      <Stack.Protected guard={autenticado}>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="asistencia/index" options={{ title: 'Asistencia' }} />
        <Stack.Screen name="asistencia/[bloque]" options={{ title: 'Tomar asistencia' }} />
        <Stack.Screen name="alumnos/index" options={{ title: 'Alumnos' }} />
        <Stack.Screen name="alumnos/[id]" options={{ title: 'Alumno' }} />
        <Stack.Screen name="cuotas" options={{ title: 'Mis cuotas' }} />
        <Stack.Screen name="finanzas" options={{ title: 'Finanzas' }} />
        <Stack.Screen name="inventario/index" options={{ title: 'Inventario' }} />
        <Stack.Screen name="inventario/[id]" options={{ title: 'Instrumento' }} />
        <Stack.Screen name="inventario/escanear" options={{ title: 'Escanear QR', presentation: 'modal' }} />
        <Stack.Screen name="inventario/nuevo" options={{ title: 'Cargar ítem' }} />
        <Stack.Screen name="partituras/index" options={{ title: 'Partituras' }} />
        <Stack.Screen name="partituras/[slug]" options={{ title: 'Partitura' }} />
      </Stack.Protected>
    </Stack>
  );
}
