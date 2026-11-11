import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import * as Crypto from 'expo-crypto';
import { useEffect, useState } from 'react';

import { api, ApiError } from './api';

/**
 * Cola local SOLO para asistencia (la operación que se hace en el aula, a veces sin señal).
 * Cada envío lleva un client_uuid: el servidor lo registra y un reintento no duplica.
 * `capturado_en` permite al servidor no pisar correcciones hechas después por otra persona.
 *
 * La cola es de cada cuenta (clave con el id de usuario): si en el teléfono entra otra
 * persona, no se envían con su sesión las planillas de la anterior.
 */

const PREFIJO = 'ito.cola_asistencia.v2';
/** Cola de la versión anterior (una sola para todo el teléfono): se pasa a la primera cuenta que entra. */
const CLAVE_VIEJA = 'ito.cola_asistencia.v1';
/** Errores de servidor (5xx) seguidos antes de dejar de reintentar solo. */
export const MAX_INTENTOS = 5;

export interface EnvioAsistencia {
  uuid: string;
  bloqueId: number;
  /** Nombre del bloque para mostrarlo en la bandeja (opcional en envíos viejos). */
  bloque?: string;
  fecha: string;
  registros: { alumno_id: number; tipo: string }[];
  capturadoEn: string;
  /** Último error al enviar (se muestra en la bandeja). */
  error?: string;
  /** Intentos fallidos por error del servidor. */
  intentos?: number;
  /** No se pudo enviar (dato rechazado o demasiados intentos): no se reintenta sola. */
  fallida?: boolean;
}

type Oyente = (cola: EnvioAsistencia[]) => void;
const oyentes = new Set<Oyente>();
let enviando = false;
/** Cuenta activa: null sin sesión (o mientras no se sabe quién es). */
let usuario: number | null = null;

const clave = (u: number | null) => `${PREFIJO}.${u ?? 'sin-usuario'}`;

async function leerClave(k: string): Promise<EnvioAsistencia[]> {
  try {
    const v = JSON.parse((await AsyncStorage.getItem(k)) ?? '[]');
    return Array.isArray(v) ? v : [];
  } catch {
    return [];
  }
}

async function leer(): Promise<EnvioAsistencia[]> {
  return leerClave(clave(usuario));
}

async function escribir(cola: EnvioAsistencia[]) {
  if (cola.length) await AsyncStorage.setItem(clave(usuario), JSON.stringify(cola));
  else await AsyncStorage.removeItem(clave(usuario));
  oyentes.forEach((o) => o(cola));
}

/**
 * Indica de quién es la sesión. Lo que se encoló antes de saberlo (o con la versión
 * vieja de la app) pasa a la cola de esa cuenta.
 */
export async function establecerUsuarioCola(id: number | null) {
  if (id !== null) {
    const huerfanas = [...(await leerClave(CLAVE_VIEJA)), ...(await leerClave(clave(null)))];
    if (huerfanas.length) {
      const propia = await leerClave(clave(id));
      const uuids = new Set(propia.map((e) => e.uuid));
      await AsyncStorage.setItem(clave(id), JSON.stringify([...propia, ...huerfanas.filter((e) => !uuids.has(e.uuid))]));
      await AsyncStorage.multiRemove([CLAVE_VIEJA, clave(null)]);
    }
  }
  usuario = id;
  const cola = await leer();
  oyentes.forEach((o) => o(cola));
  if (id !== null) void sincronizar();
}

/**
 * Al cerrar sesión: la cola de la cuenta queda guardada para cuando vuelva a entrar.
 * Si nunca se supo de quién era, se descarta (no se puede asegurar que sea de la próxima).
 */
export async function cerrarCola() {
  if (usuario === null) await AsyncStorage.multiRemove([clave(null), CLAVE_VIEJA]);
  await establecerUsuarioCola(null);
}

export const usuarioCola = () => usuario;

export type ResultadoAsistencia = { estado: 'enviada'; conflictos: number[] } | { estado: 'pendiente' };

/** Guarda en la cola y trata de enviar enseguida. */
export async function encolarAsistencia(datos: Omit<EnvioAsistencia, 'uuid' | 'capturadoEn'>): Promise<ResultadoAsistencia> {
  const envio: EnvioAsistencia = { ...datos, uuid: Crypto.randomUUID(), capturadoEn: new Date().toISOString() };
  // Una planilla nueva del mismo bloque y fecha reemplaza a la pendiente anterior.
  const cola = (await leer()).filter((e) => !(e.bloqueId === envio.bloqueId && e.fecha === envio.fecha));
  await escribir([...cola, envio]);

  try {
    const r = await enviar(envio);
    await quitar(envio.uuid);
    return { estado: 'enviada', conflictos: r.conflictos ?? [] };
  } catch (e) {
    if (e instanceof ApiError && !e.reintentable && e.status !== 401) {
      // Error definitivo (permiso, validación): se muestra en pantalla, no se reintenta.
      // (Con 401 la planilla queda guardada para cuando vuelva a ingresar.)
      await quitar(envio.uuid);
      throw e;
    }
    return { estado: 'pendiente' };
  }
}

function enviar(e: EnvioAsistencia) {
  return api<{ guardadas: number; conflictos?: number[] }>(`bloques/${e.bloqueId}/asistencia`, {
    method: 'POST',
    body: { fecha: e.fecha, client_uuid: e.uuid, capturado_en: e.capturadoEn, registros: e.registros },
  });
}

async function quitar(uuid: string) {
  await escribir((await leer()).filter((x) => x.uuid !== uuid));
}

async function actualizar(uuid: string, cambios: Partial<EnvioAsistencia>) {
  await escribir((await leer()).map((x) => (x.uuid === uuid ? { ...x, ...cambios } : x)));
}

/**
 * Envía lo pendiente. Se llama al volver la conexión, al abrir la app y desde la bandeja.
 * Los errores definitivos (4xx) y los que superan MAX_INTENTOS quedan como «no se pudo
 * enviar» con el detalle; solo se reintentan si se pide a mano (`forzar`).
 */
export async function sincronizar({ forzar = false }: { forzar?: boolean } = {}): Promise<void> {
  if (enviando || usuario === null) return;
  enviando = true;
  const de = usuario;
  try {
    for (const envio of await leer()) {
      if (usuario !== de) break; // cerró sesión en el medio
      if (envio.fallida && !forzar) continue;
      try {
        await enviar(envio);
        await quitar(envio.uuid);
      } catch (e) {
        if (e instanceof ApiError && (e.esDeRed || e.status === 401)) break; // sin red o sin sesión: se reintenta después
        const msg = e instanceof Error ? e.message : 'Error';
        const intentos = (envio.intentos ?? 0) + 1;
        const definitivo = !(e instanceof ApiError && e.reintentable);
        await actualizar(envio.uuid, { error: msg, intentos, fallida: definitivo || intentos >= MAX_INTENTOS });
      }
    }
  } finally {
    enviando = false;
  }
}

export function descartar(uuid: string) {
  return quitar(uuid);
}

/** Escucha la conexión y sincroniza sola. Montar una vez en el layout raíz. */
export function useSincronizacionAutomatica() {
  useEffect(() => {
    void sincronizar();
    return NetInfo.addEventListener((s) => {
      if (s.isConnected && s.isInternetReachable !== false) void sincronizar();
    });
  }, []);
}

export function useColaAsistencia(): EnvioAsistencia[] {
  const [cola, setCola] = useState<EnvioAsistencia[]>([]);
  useEffect(() => {
    void leer().then(setCola);
    oyentes.add(setCola);
    return () => {
      oyentes.delete(setCola);
    };
  }, []);
  return cola;
}

export function useConexion(): boolean {
  const [online, setOnline] = useState(true);
  useEffect(() => NetInfo.addEventListener((s) => setOnline(!!s.isConnected && s.isInternetReachable !== false)), []);
  return online;
}
