import Constants from 'expo-constants';

/** URL base de la API v1 (sin barra final). Se define por variante en eas.json / .env.local. */
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'http://10.0.2.2:8000/api/v1').replace(/\/+$/, '');

/** Panel web (para módulos que no tienen pantalla nativa). */
export const WEB_URL = API_URL.replace(/\/api\/v1$/, '');

export const VARIANTE: string = (Constants.expoConfig?.extra?.variante as string | undefined) ?? 'production';
