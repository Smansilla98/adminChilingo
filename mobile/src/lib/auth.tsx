import { useQueryClient } from '@tanstack/react-query';
import * as SecureStore from 'expo-secure-store';
import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';

import { api, configurarSesion } from './api';
import { vaciarCache } from './cache';
import { cerrarCola, establecerUsuarioCola } from './offline';
import { olvidarTokenPush, tokenPushGuardado } from './token-push';
import type { Me } from './types';

const CLAVE_TOKEN = 'ito.token';
const CLAVE_EXPIRA = 'ito.token_expira';
const CLAVE_CONTEXTO = 'ito.contexto';
const CLAVE_USUARIO = 'ito.usuario_id';
const RENOVAR_SI_QUEDAN_DIAS = 7;

// SecureStore no existe en web (solo para desarrollo): se usa memoria.
const memoria = new Map<string, string>();
const almacen = {
  get: (k: string) => (Platform.OS === 'web' ? Promise.resolve(memoria.get(k) ?? null) : SecureStore.getItemAsync(k)),
  set: (k: string, v: string) => (Platform.OS === 'web' ? Promise.resolve(void memoria.set(k, v)) : SecureStore.setItemAsync(k, v)),
  del: (k: string) => (Platform.OS === 'web' ? Promise.resolve(void memoria.delete(k)) : SecureStore.deleteItemAsync(k)),
};

/** Cuenta de la última sesión (para separar la caché y la cola offline por persona). */
export async function leerUsuarioGuardado(): Promise<number | null> {
  try {
    const v = Number(await almacen.get(CLAVE_USUARIO));
    return Number.isInteger(v) && v > 0 ? v : null;
  } catch {
    return null;
  }
}

interface Token {
  token: string;
  expira: string | null;
}

export interface DatosRegistro {
  dni: string;
  fecha_nacimiento: string;
  email?: string;
  password: string;
  password_confirmation: string;
}

interface AuthState {
  listo: boolean;
  autenticado: boolean;
  contexto: string | null;
  ingresar: (usuario: string, clave: string) => Promise<void>;
  /** Un alumno ya cargado crea su cuenta (DNI + fecha de nacimiento) y queda adentro. */
  registrarse: (datos: DatosRegistro) => Promise<void>;
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

  /** Pregunta quién es la cuenta y separa la cola offline por persona. Sin red no rompe nada. */
  const conocerUsuario = useCallback(async () => {
    try {
      const me = await api<Me>('me');
      queryClient.setQueryData(['me'], me);
      await almacen.set(CLAVE_USUARIO, String(me.usuario.id));
      await establecerUsuarioCola(me.usuario.id);
    } catch {
      // Se vuelve a intentar en la próxima apertura.
    }
  }, [queryClient]);

  const limpiar = useCallback(async () => {
    await Promise.all([almacen.del(CLAVE_TOKEN), almacen.del(CLAVE_EXPIRA), almacen.del(CLAVE_USUARIO)]);
    configurarSesion({ token: null });
    setToken(null);
    await cerrarCola();
    await vaciarCache(queryClient);
  }, [queryClient]);

  // Al abrir: recuperar token, renovarlo si está por vencer.
  useEffect(() => {
    (async () => {
      const [t, expira, ctx, usuarioId] = await Promise.all([almacen.get(CLAVE_TOKEN), almacen.get(CLAVE_EXPIRA), almacen.get(CLAVE_CONTEXTO), leerUsuarioGuardado()]);
      configurarSesion({ token: t, contexto: ctx, onNoAutorizado: () => void limpiar() });
      setContexto(ctx);
      if (t) {
        setToken(t);
        if (usuarioId) await establecerUsuarioCola(usuarioId);
        else void conocerUsuario();
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
  }, [guardar, limpiar, conocerUsuario]);

  const ingresar = useCallback(
    async (usuario: string, clave: string) => {
      const t = await api<Token>('auth/login', {
        method: 'POST',
        body: { username: usuario.trim(), password: clave, dispositivo: `${Platform.OS} app` },
      });
      await guardar(t);
      await conocerUsuario();
    },
    [guardar, conocerUsuario],
  );

  const registrarse = useCallback(
    async (datos: DatosRegistro) => {
      await guardar(await api<Token>('auth/registro', { method: 'POST', body: { ...datos, dni: datos.dni.trim(), dispositivo: `${Platform.OS} app` } }));
      await conocerUsuario();
    },
    [guardar, conocerUsuario],
  );

  const salir = useCallback(async () => {
    // Con el token de push el servidor desvincula este celular: deja de recibir los avisos de la cuenta.
    const pushToken = await tokenPushGuardado();
    try {
      await api('auth/logout', { method: 'POST', body: pushToken ? { push_token: pushToken } : undefined });
    } catch {
      // Igual se borra la sesión local; se intenta al menos quitar el dispositivo.
      if (pushToken) await api('dispositivos', { method: 'DELETE', body: { token: pushToken } }).catch(() => undefined);
    }
    await olvidarTokenPush();
    await limpiar();
  }, [limpiar]);

  const elegirContexto = useCallback(
    async (clave: string | null) => {
      if (clave) await almacen.set(CLAVE_CONTEXTO, clave);
      else await almacen.del(CLAVE_CONTEXTO);
      configurarSesion({ contexto: clave });
      setContexto(clave);
      // El contexto viaja en cada pedido: todo lo cacheado puede cambiar.
      await queryClient.invalidateQueries();
    },
    [queryClient],
  );

  const valor = useMemo<AuthState>(
    () => ({ listo, autenticado: !!token, contexto, ingresar, registrarse, salir, elegirContexto }),
    [listo, token, contexto, ingresar, registrarse, salir, elegirContexto],
  );

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth fuera de AuthProvider');
  return ctx;
}
