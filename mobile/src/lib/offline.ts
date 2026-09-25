import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import * as Crypto from 'expo-crypto';
import { useEffect, useState } from 'react';

import { api, ApiError } from './api';

/**
 * Cola local SOLO para asistencia (la operación que se hace en el aula, a veces sin señal).
 * Cada envío lleva un client_uuid: el servidor lo registra y un reintento no duplica.
 * `capturado_en` permite al servidor no pisar correcciones hechas después por otra persona.
 */

const CLAVE = 'ito.cola_asistencia.v1';

export interface EnvioAsistencia {
  uuid: string;
  bloqueId: number;
  fecha: string;
  registros: { alumno_id: number; tipo: string }[];
  capturadoEn: string;
  error?: string;
}

type Oyente = (cola: EnvioAsistencia[]) => void;
const oyentes = new Set<Oyente>();
let enviando = false;

async function leer(): Promise<EnvioAsistencia[]> {
  try {
    return JSON.parse((await AsyncStorage.getItem(CLAVE)) ?? '[]');
  } catch {
    return [];
  }
}

async function escribir(cola: EnvioAsistencia[]) {
  await AsyncStorage.setItem(CLAVE, JSON.stringify(cola));
  oyentes.forEach((o) => o(cola));
}

export type ResultadoAsistencia = { estado: 'enviada'; conflictos: number[] } | { estado: 'pendiente' };

/** Guarda en la cola y trata de enviar enseguida. */
export async function encolarAsistencia(datos: Omit<EnvioAsistencia, 'uuid' | 'capturadoEn'>): Promise<ResultadoAsistencia> {
  const envio: EnvioAsistencia = { ...datos, uuid: Crypto.randomUUID(), capturadoEn: new Date().toISOString() };
  // Una planilla nueva del mismo bloque y fecha reemplaza a la pendiente anterior.
  const cola = (await leer()).filter((e) => !(e.bloqueId === envio.bloqueId && e.fecha === envio.fecha));
  await escribir([...cola, envio]);

  try {
    const r = await enviar(envio);
    await escribir((await leer()).filter((e) => e.uuid !== envio.uuid));
    return { estado: 'enviada', conflictos: r.conflictos ?? [] };
  } catch (e) {
    if (e instanceof ApiError && !e.esDeRed) {
      // Error definitivo (permiso, validación): no tiene sentido reintentar.
      await escribir((await leer()).filter((x) => x.uuid !== envio.uuid));
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

/** Envía lo pendiente. Se llama al volver la conexión y al abrir la app. */
export async function sincronizar(): Promise<void> {
  if (enviando) return;
  enviando = true;
  try {
    for (const envio of await leer()) {
      try {
        await enviar(envio);
        await escribir((await leer()).filter((x) => x.uuid !== envio.uuid));
      } catch (e) {
        if (e instanceof ApiError && e.esDeRed) break; // sigue sin red
        const msg = e instanceof Error ? e.message : 'Error';
        await escribir((await leer()).map((x) => (x.uuid === envio.uuid ? { ...x, error: msg } : x)));
      }
    }
  } finally {
    enviando = false;
  }
}

export function descartar(uuid: string) {
  return leer().then((c) => escribir(c.filter((x) => x.uuid !== uuid)));
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
