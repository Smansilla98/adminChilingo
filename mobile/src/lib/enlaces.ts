import { API_URL } from './config';

/** Un enlace es externo si no apunta al mismo host que la API (el panel web). */
export function esEnlaceExterno(url: string): boolean {
  try {
    const host = new URL(url).host;
    const api = new URL(API_URL).host;
    return host !== '' && host !== api;
  } catch {
    return false;
  }
}
