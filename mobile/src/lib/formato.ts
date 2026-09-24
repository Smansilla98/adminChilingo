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
