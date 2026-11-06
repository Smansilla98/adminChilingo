import { useQueryClient } from '@tanstack/react-query';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { api } from './api';
import { destinoDe } from './destinos';

/** Canal de Android de los avisos: importancia alta para que aparezca arriba con sonido. El backend manda channelId 'avisos'. */
export const CANAL_ANDROID = 'avisos';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

export const pushDisponible = () => Platform.OS !== 'web' && Device.isDevice;

/** Por qué falló el último intento de registrar el celular (se muestra en Avisos para poder diagnosticarlo). */
let ultimoError: string | null = null;
export const errorRegistroPush = () => ultimoError;

/** 'granted' | 'denied' | 'undetermined', y si todavía se puede preguntar (si no, hay que ir a Ajustes). */
export async function permisoPush(): Promise<{ estado: Notifications.PermissionStatus; sePuedePreguntar: boolean }> {
  const p = await Notifications.getPermissionsAsync();
  return { estado: p.status, sePuedePreguntar: p.canAskAgain };
}

/**
 * Pide permiso y registra el token de push en el backend. Requiere development build o
 * build de tienda (Expo Go no recibe push remotos), el projectId de EAS y, en Android, Firebase (FCM).
 * Devuelve null sin romper nada si no se puede.
 */
export async function registrarPush(): Promise<string | null> {
  if (!pushDisponible()) return null;
  ultimoError = null;
  let paso = 'crear el canal de avisos';
  try {
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync(CANAL_ANDROID, {
        name: 'Avisos de la escuela',
        importance: Notifications.AndroidImportance.HIGH,
        vibrationPattern: [0, 250, 250, 250],
        lightColor: '#f26422',
      });
    }
    paso = 'pedir el permiso';
    let { status } = await Notifications.getPermissionsAsync();
    if (status !== 'granted') status = (await Notifications.requestPermissionsAsync()).status;
    if (status !== 'granted') {
      ultimoError = 'El celular no dio permiso para mostrar notificaciones.';
      return null;
    }

    const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
    if (!projectId) {
      ultimoError = 'La app no tiene el projectId de EAS.';
      return null;
    }
    paso = 'obtener el token de Expo/Firebase';
    const token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
    paso = 'guardar el celular en el servidor';
    await api('dispositivos', { method: 'POST', body: { token, plataforma: Platform.OS, nombre: Device.modelName ?? undefined } });
    return token;
  } catch (e) {
    // Sin FCM configurado en Android, getExpoPushTokenAsync falla.
    ultimoError = `Falló al ${paso}: ${e instanceof Error ? e.message : String(e)}`;
    if (__DEV__) console.warn('No se pudo registrar el push', e);
    return null;
  }
}

/**
 * Con la sesión iniciada: al tocar una notificación abre la pantalla del aviso (también si la app
 * estaba cerrada) y, cuando llega una con la app abierta, refresca la bandeja y el estado de cuenta.
 */
export function useNotificacionesPush(activo: boolean) {
  const qc = useQueryClient();
  useEffect(() => {
    if (!activo || !pushDisponible()) return;

    const abrir = (r: Notifications.NotificationResponse | null) => {
      if (!r || r.actionIdentifier !== Notifications.DEFAULT_ACTION_IDENTIFIER) return;
      void qc.invalidateQueries({ queryKey: ['avisos'] });
      const destino = destinoDe(r.notification.request.content.data?.enlace);
      router.push((destino ?? '/avisos') as never);
      Notifications.clearLastNotificationResponse();
    };
    // La app se abrió tocando una notificación.
    abrir(Notifications.getLastNotificationResponse());

    const toques = Notifications.addNotificationResponseReceivedListener(abrir);
    const recibidas = Notifications.addNotificationReceivedListener(() => {
      void qc.invalidateQueries({ queryKey: ['avisos'] });
      void qc.invalidateQueries({ queryKey: ['mi', 'estado-cuenta'] });
    });
    return () => {
      toques.remove();
      recibidas.remove();
    };
  }, [activo, qc]);
}
