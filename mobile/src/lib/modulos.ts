import type { Icono } from '@/components/ui';
import { WEB_URL } from './config';

/**
 * Módulos que devuelve el backend (/me.modulos) → pantalla nativa o panel web.
 * Si el backend agrega un módulo sin pantalla, se abre en el panel web.
 */
export const RUTAS: Record<string, { ruta?: string; web?: string; icono: Icono }> = {
  mi_espacio: { ruta: '/cuotas', icono: 'person' },
  asistencia: { ruta: '/asistencia', icono: 'fact-check' },
  bloques: { ruta: '/asistencia', icono: 'groups' },
  alumnos: { ruta: '/alumnos', icono: 'school' },
  calendario: { ruta: '/agenda', icono: 'calendar-month' },
  eventos: { ruta: '/agenda', icono: 'celebration' },
  partituras: { ruta: '/partituras', icono: 'music-note' },
  cuotas: { ruta: '/finanzas', icono: 'receipt-long' },
  pagos: { ruta: '/finanzas', icono: 'payments' },
  inventario: { ruta: '/inventario', icono: 'inventory-2' },
  notificaciones: { ruta: '/avisos', icono: 'notifications' },
  personas: { web: '/personas', icono: 'badge' },
  profesores: { web: '/profesores', icono: 'co-present' },
  sedes: { web: '/sedes', icono: 'location-on' },
  facturacion: { web: '/facturacion-mensual', icono: 'request-quote' },
  gastos: { web: '/gastos', icono: 'account-balance-wallet' },
  reportes: { web: '/reportes', icono: 'bar-chart' },
  compras: { web: '/ordenes-compra', icono: 'shopping-cart' },
  usuarios: { web: '/usuarios', icono: 'admin-panel-settings' },
};

export const urlWeb = (ruta: string) => WEB_URL + ruta;
