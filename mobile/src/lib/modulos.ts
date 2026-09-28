import type { Icono } from '@/components/ui';

export type Grupo = 'Mi espacio' | 'Académico' | 'Finanzas' | 'Operación' | 'Comunidad' | 'Administración';

/**
 * Módulos que devuelve el backend (/me.modulos) → pantalla nativa. La app no decide
 * quién ve qué: muestra lo que el backend habilita. Todos los módulos tienen pantalla
 * propia; ninguno deriva al panel web.
 */
export const RUTAS: Record<string, { ruta: string; icono: Icono; grupo: Grupo }> = {
  mi_espacio: { ruta: '/mi-cuenta', icono: 'person', grupo: 'Mi espacio' },
  asistencia: { ruta: '/asistencia', icono: 'fact-check', grupo: 'Académico' },
  alumnos: { ruta: '/alumnos', icono: 'school', grupo: 'Académico' },
  bloques: { ruta: '/bloques', icono: 'groups', grupo: 'Académico' },
  profesores: { ruta: '/profesores', icono: 'co-present', grupo: 'Académico' },
  personas: { ruta: '/personas', icono: 'badge', grupo: 'Académico' },
  partituras: { ruta: '/partituras', icono: 'music-note', grupo: 'Académico' },
  cuotas: { ruta: '/cuotas', icono: 'receipt-long', grupo: 'Finanzas' },
  pagos: { ruta: '/pagos', icono: 'payments', grupo: 'Finanzas' },
  comprobantes: { ruta: '/comprobantes', icono: 'fact-check', grupo: 'Finanzas' },
  becas: { ruta: '/becas', icono: 'volunteer-activism', grupo: 'Finanzas' },
  facturacion: { ruta: '/facturacion', icono: 'request-quote', grupo: 'Finanzas' },
  gastos: { ruta: '/gastos', icono: 'account-balance-wallet', grupo: 'Finanzas' },
  reportes: { ruta: '/reportes', icono: 'bar-chart', grupo: 'Finanzas' },
  sedes: { ruta: '/sedes', icono: 'location-on', grupo: 'Operación' },
  inventario: { ruta: '/inventario', icono: 'inventory-2', grupo: 'Operación' },
  compras: { ruta: '/compras', icono: 'shopping-cart', grupo: 'Operación' },
  calendario: { ruta: '/agenda', icono: 'calendar-month', grupo: 'Comunidad' },
  eventos: { ruta: '/eventos', icono: 'celebration', grupo: 'Comunidad' },
  shows: { ruta: '/shows', icono: 'theater-comedy', grupo: 'Comunidad' },
  villa_gesell: { ruta: '/villa-gesell', icono: 'beach-access', grupo: 'Comunidad' },
  disenos: { ruta: '/disenos', icono: 'palette', grupo: 'Comunidad' },
  biblioteca: { ruta: '/biblioteca', icono: 'local-library', grupo: 'Comunidad' },
  usuarios: { ruta: '/usuarios', icono: 'admin-panel-settings', grupo: 'Administración' },
  auditoria: { ruta: '/auditoria', icono: 'history', grupo: 'Administración' },
};

export const ORDEN_GRUPOS: Grupo[] = ['Mi espacio', 'Académico', 'Finanzas', 'Operación', 'Comunidad', 'Administración'];
