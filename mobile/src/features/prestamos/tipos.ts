export type EstadoPrestamo = 'prestado' | 'vencido' | 'devuelto';

/** Préstamo de un instrumento (GET mi/prestamos). */
export interface Prestamo {
  id: number;
  item: { id: number; nombre: string; codigo: string | null; sede: string | null };
  responsable: string;
  alumno_id: number | null;
  persona_id: number | null;
  entregado_el: string;
  devolucion_prevista: string;
  devuelto_el: string | null;
  estado: EstadoPrestamo;
  estado_nombre: string;
  deposito: number | null;
  deposito_devuelto: boolean;
  condicion_entrega: string | null;
  condicion_devolucion: string | null;
  notas: string | null;
}

export const COLOR_PRESTAMO: Record<EstadoPrestamo, string> = {
  prestado: '#4fb3d9',
  vencido: '#e31b23',
  devuelto: '#3daf3a',
};
