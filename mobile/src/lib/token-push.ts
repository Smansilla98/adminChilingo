import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * Último token de push que este celular registró en el servidor. Se manda al cerrar
 * sesión para que el teléfono deje de recibir los avisos de esa cuenta. Va en el
 * almacén seguro (SecureStore), igual que el token de sesión.
 * (Módulo aparte de push.ts para no cargar expo-notifications donde no hace falta.)
 */
const CLAVE = 'ito.push_token';

// SecureStore no existe en web: ahí no hay push, así que no se guarda nada.
export async function guardarTokenPush(token: string): Promise<void> {
  if (Platform.OS === 'web') return;
  try {
    await SecureStore.setItemAsync(CLAVE, token);
  } catch {
    // Sin el token guardado, al salir solo se quita el dispositivo por el servidor.
  }
}

export async function tokenPushGuardado(): Promise<string | null> {
  if (Platform.OS === 'web') return null;
  try {
    return (await SecureStore.getItemAsync(CLAVE)) ?? null;
  } catch {
    return null;
  }
}

export async function olvidarTokenPush(): Promise<void> {
  if (Platform.OS === 'web') return;
  try {
    await SecureStore.deleteItemAsync(CLAVE);
  } catch {
    // Nada que borrar.
  }
}
