import AsyncStorage from '@react-native-async-storage/async-storage';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { QueryClient } from '@tanstack/react-query';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { DarkTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';

import { BannerSinConexion, ToastProvider } from '@/components/feedback';
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
          <ToastProvider>
            <StatusBar style="light" />
            <BannerSinConexion />
            <Navegacion />
          </ToastProvider>
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
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: C.fondo } }}>
      <Stack.Protected guard={!autenticado}>
        <Stack.Screen name="login" />
      </Stack.Protected>
      {/* Todo lo que está en (app) requiere sesión: las pantallas nuevas quedan protegidas solas. */}
      <Stack.Protected guard={autenticado}>
        <Stack.Screen name="(app)" />
      </Stack.Protected>
    </Stack>
  );
}
