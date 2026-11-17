import * as Sentry from '@sentry/react-native';
import type { ReactElement } from 'react';

import { VARIANTE } from './config';

/**
 * Reporte de errores (Sentry). Solo se activa si EXPO_PUBLIC_SENTRY_DSN existe en el build:
 * sin DSN no se inicia nada ni sale ningún dato del teléfono.
 *
 * Sin datos personales: no se envía PII por defecto, se quitan usuario, cabeceras y cuerpos de
 * pedidos, las URLs pierden su query string (búsquedas por nombre o DNI) y se borran los DNI,
 * emails y teléfonos que aparezcan en mensajes.
 */

const DSN = process.env.EXPO_PUBLIC_SENTRY_DSN ?? '';

export const sentryActivo = () => DSN.length > 0;

/** Quita DNI (7-8 dígitos), emails y teléfonos de un texto. */
export function redactar(texto: string): string {
  return texto
    .replace(/[\w.+-]+@[\w-]+\.[\w.-]+/g, '[email]')
    .replace(/\+?\d[\d\s().-]{7,}\d/g, '[número]')
    .replace(/\b\d{7,8}\b/g, '[dni]');
}

const sinQuery = (url: string) => url.split('?')[0].split('#')[0];

/** Antes de enviar: deja solo lo técnico. Devuelve null si el evento no debe salir. */
export function limpiarEvento<T extends Sentry.ErrorEvent | Sentry.Event>(evento: T): T | null {
  const e = { ...evento, user: undefined, request: undefined, contexts: { ...evento.contexts, device: evento.contexts?.device, app: evento.contexts?.app } } as T;
  if (e.message) e.message = redactar(e.message);
  e.exception?.values?.forEach((v) => {
    if (v.value) v.value = redactar(v.value);
  });
  return e;
}

/** Antes de guardar un breadcrumb: sin consola ni cuerpos, y las URLs sin query string. */
export function limpiarBreadcrumb(b: Sentry.Breadcrumb): Sentry.Breadcrumb | null {
  if (b.category === 'console' || b.category === 'ui.input') return null;
  const data = b.data ? { ...b.data } : undefined;
  if (data) {
    delete data.body;
    delete data.request_body;
    delete data.response_body;
    if (typeof data.url === 'string') data.url = sinQuery(data.url);
  }
  return { ...b, message: b.message ? redactar(b.message) : b.message, data };
}

let iniciado = false;

/** Llamar una vez al arrancar la app (antes de renderizar). */
export function iniciarSentry() {
  if (!sentryActivo() || iniciado) return;
  iniciado = true;
  Sentry.init({
    dsn: DSN,
    environment: VARIANTE,
    sendDefaultPii: false,
    enableAutoSessionTracking: false,
    tracesSampleRate: 0,
    beforeSend: (evento) => limpiarEvento(evento),
    beforeBreadcrumb: (b) => limpiarBreadcrumb(b),
  });
}

/** Raíz de la app: con Sentry activo, captura también los errores de render. */
export const envolverRaiz = (componente: () => ReactElement | null) => (sentryActivo() ? Sentry.wrap(componente) : componente);
