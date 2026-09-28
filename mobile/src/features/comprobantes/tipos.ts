export interface Comprobante {
  id: number;
  estado: 'pendiente' | 'visto' | 'pagado';
  estado_nombre: string;
  fecha_pago: string | null;
  monto_total: number;
  notas: string | null;
  enviado_at: string | null;
  alumno: { id: number; nombre: string; persona_id: number | null } | null;
  sede: string | null;
  items: { bloque: string | null; cuota: string | null; cuota_id: number; monto: number }[];
  pago_id: number | null;
  tiene_archivo: boolean;
  acciones: { marcar_visto: boolean; aprobar: boolean } | null;
}

export const COLOR_COMPROBANTE = { pendiente: '#e6a817', visto: '#4fb3d9', pagado: '#3daf3a' } as const;
