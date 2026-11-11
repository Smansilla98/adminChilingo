import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * Último token de push que este celular registró en el servidor. Se manda al cerrar
 * sesión para que el teléfono deje de recibir los avisos de esa cuenta.
 * (Módulo aparte de push.ts para no cargar expo-notifications donde no hace falta.)
 */
const CLAVE = 'ito.push_token';

export const guardarTokenPush = (token: string) => AsyncStorage.setItem(CLAVE, token).catch(() => undefined);
export const tokenPushGuardado = () => AsyncStorage.getItem(CLAVE).catch(() => null);
export const olvidarTokenPush = () => AsyncStorage.removeItem(CLAVE).catch(() => undefined);
