import { useQueryClient } from '@tanstack/react-query';
import * as SecureStore from 'expo-secure-store';
import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';

import { api, configurarSesion } from './api';

const CLAVE_TOKEN = 'ito.token';
const CLAVE_EXPIRA = 'ito.token_expira';
const CLAVE_CONTEXTO = 'ito.contexto';
const RENOVAR_SI_QUEDAN_DIAS = 7;

// SecureStore no existe en web (solo para desarrollo): se usa memoria.
const memoria = new Map<string, string>();
const almacen = {
  get: (k: string) => (Platform.OS === 'web' ? Promise.resolve(memoria.get(k) ?? null) : SecureStore.getItemAsync(k)),
  set: (k: string, v: string) => (Platform.OS === 'web' ? Promise.resolve(void memoria.set(k, v)) : SecureStore.setItemAsync(k, v)),
  del: (k: string) => (Platform.OS === 'web' ? Promise.resolve(void memoria.delete(k)) : SecureStore.deleteItemAsync(k)),
};

interface Token {
  token: string;
  expira: string | null;
}

interface AuthState {
  listo: boolean;
  autenticado: boolean;
  contexto: string | null;
  ingresar: (usuario: string, clave: string) => Promise<void>;
  salir: () => Promise<void>;
  elegirContexto: (clave: string | null) => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [listo, setListo] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [contexto, setContexto] = useState<string | null>(null);

  const guardar = useCallback(async (t: Token) => {
    await almacen.set(CLAVE_TOKEN, t.token);
    if (t.expira) await almacen.set(CLAVE_EXPIRA, t.expira);
    configurarSesion({ token: t.token });
    setToken(t.token);
  }, []);

  const limpiar = useCallback(async () => {
    await Promise.all([almacen.del(CLAVE_TOKEN), almacen.del(CLAVE_EXPIRA)]);
    configurarSesion({ token: null });
    setToken(null);
    queryClient.clear();
  }, [queryClient]);

  // Al abrir: recuperar token, renovarlo si está por vencer.
  useEffect(() => {
    (async () => {
      const [t, expira, ctx] = await Promise.all([almacen.get(CLAVE_TOKEN), almacen.get(CLAVE_EXPIRA), almacen.get(CLAVE_CONTEXTO)]);
      configurarSesion({ token: t, contexto: ctx, onNoAutorizado: () => void limpiar() });
      setContexto(ctx);
      if (t) {
        setToken(t);
        const dias = expira ? (new Date(expira).getTime() - Date.now()) / 86_400_000 : Infinity;
        if (dias < RENOVAR_SI_QUEDAN_DIAS) {
          try {
            await guardar(await api<Token>('auth/refresh', { method: 'POST' }));
          } catch {
            // Sin red: se reintenta en la próxima apertura. Si el token venció, el 401 cierra la sesión.
          }
        }
      }
      setListo(true);
    })();
  }, [guardar, limpiar]);

  const ingresar = useCallback(
    async (usuario: string, clave: string) => {
      const t = await api<Token>('auth/login', {
        method: 'POST',
        body: { username: usuario.trim(), password: clave, dispositivo: `${Platform.OS} app` },
      });
      await guardar(t);
    },
    [guardar],
  );

  const salir = useCallback(async () => {
    try {
      await api('auth/logout', { method: 'POST' });
    } catch {
      // Igual se borra la sesión local.
    }
    await limpiar();
  }, [limpiar]);

  const elegirContexto = useCallback(
    async (clave: string | null) => {
      if (clave) await almacen.set(CLAVE_CONTEXTO, clave);
      else await almacen.del(CLAVE_CONTEXTO);
      configurarSesion({ contexto: clave });
      setContexto(clave);
      await queryClient.invalidateQueries({ queryKey: ['inicio'] });
    },
    [queryClient],
  );

  const valor = useMemo<AuthState>(
    () => ({ listo, autenticado: !!token, contexto, ingresar, salir, elegirContexto }),
    [listo, token, contexto, ingresar, salir, elegirContexto],
  );

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth fuera de AuthProvider');
  return ctx;
}
