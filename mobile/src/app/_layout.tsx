import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { DarkTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';

import { BannerSinConexion, ToastProvider } from '@/components/feedback';
import { AuthProvider, leerUsuarioGuardado, useAuth } from '@/lib/auth';
import { busterPara, debePersistir, DIA, persister, queryClient } from '@/lib/cache';
import { useSincronizacionAutomatica } from '@/lib/offline';
import { registrarPush, useNotificacionesPush } from '@/lib/push';
import { C } from '@/lib/theme';

SplashScreen.preventAutoHideAsync();

const tema = { ...DarkTheme, colors: { ...DarkTheme.colors, background: C.fondo, card: C.fondo, primary: C.acento, text: C.texto, border: C.borde } };

export default function RootLayout() {
  // La caché guardada es de una cuenta: se restaura solo si es de la misma que tiene la sesión.
  const [buster, setBuster] = useState<string | null>(null);
  useEffect(() => {
    void leerUsuarioGuardado().then((id) => setBuster(busterPara(id)));
  }, []);
  if (!buster) return null; // el splash sigue visible

  return (
    <PersistQueryClientProvider
      client={queryClient}
      persistOptions={{ persister, maxAge: DIA, buster, dehydrateOptions: { shouldDehydrateQuery: debePersistir } }}>
      <AuthProvider>
        <ThemeProvider value={tema}>
          <ToastProvider>
            <StatusBar style="light" />
            <BannerSinConexion>
              <Navegacion />
            </BannerSinConexion>
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
  useNotificacionesPush(listo && autenticado);

  if (!listo) return null;

  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: C.fondo } }}>
      <Stack.Protected guard={!autenticado}>
        <Stack.Screen name="login" />
        <Stack.Screen name="registro" />
        <Stack.Screen name="recuperar" />
      </Stack.Protected>
      {/* Todo lo que está en (app) requiere sesión: las pantallas nuevas quedan protegidas solas. */}
      <Stack.Protected guard={autenticado}>
        <Stack.Screen name="(app)" />
      </Stack.Protected>
    </Stack>
  );
}
