import { C } from './theme';
import type { ItemCuenta } from './types';

export const ESTADO_CUOTA: Record<ItemCuenta['estado'], { texto: string; color: string }> = {
  pagada: { texto: 'Pagada', color: C.exito },
  becada: { texto: 'Becada', color: C.info },
  parcial: { texto: 'Pago parcial', color: C.alerta },
  vencida: { texto: 'Vencida', color: C.peligro },
  pendiente: { texto: 'Pendiente', color: C.tenue },
};

export function formatearFecha(iso: string): string {
  const [a, m, d] = iso.split('-').map(Number);
  return new Date(a, m - 1, d).toLocaleDateString('es-AR', { weekday: 'short', day: 'numeric', month: 'short' });
}

export const COLOR_ESTADO_ITEM: Record<string, string> = { nuevo: C.exito, bueno: C.exito, regular: C.alerta, reparacion: C.peligro, baja: C.tenue };

/**
 * Enlace de WhatsApp para un teléfono cargado a mano (con o sin 0, 15, +54…).
 * Números argentinos: 54 9 + área + número. Devuelve null si no parece un teléfono.
 */
export function enlaceWhatsApp(telefono: string | null | undefined): string | null {
  let n = (telefono ?? '').replace(/\D/g, '');
  if (n.length < 8) return null;
  if (n.startsWith('54')) n = n.slice(2);
  if (n.startsWith('9')) n = n.slice(1);
  if (n.startsWith('0')) n = n.slice(1);
  // 11 15 1234 5678 → 11 1234 5678 (el 15 de celular no va en el formato internacional)
  const conQuince = n.match(/^(\d{2,4})15(\d{6,8})$/);
  if (conQuince && conQuince[1].length + conQuince[2].length === 10) n = conQuince[1] + conQuince[2];
  return `https://wa.me/549${n}`;
}
