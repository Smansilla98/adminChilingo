export interface CuotaResumen {
  id: number;
  nombre: string;
  anio: number;
  mes: number | null;
  periodo: string;
  monto: number;
  vencimiento: string | null;
  vencida: boolean;
  alcance: 'general' | 'sede' | 'bloque';
  bloque: string | null;
  bloque_id: number | null;
  sede: string | null;
  sede_id: number | null;
  pagos: number;
  activo: boolean;
}

/** Ficha de cuota: `pagos` es la cantidad y `cobros` el detalle. */
export interface CuotaFicha extends CuotaResumen {
  descripcion: string | null;
  alumnos: { id: number; nombre: string }[];
  cobros: { pago_id: number; alumno: { id: number; nombre: string; persona_id: number | null } | null; monto: number; fecha: string | null; anulado: boolean }[];
  total_cobrado: number;
  recordatorios: { alumno: string | null; estado: string | null; fecha: string | null }[];
  acciones: { editar: boolean; eliminar: boolean; registrar_pago: boolean };
}

export interface LineaPago {
  id?: number;
  alumno_id: number;
  alumno: string | null;
  persona_id?: number | null;
  cuota_id: number;
  cuota: string | null;
  cuota_monto?: number | null;
  monto: number;
  abono_profesor?: number | null;
  abono_nota?: string | null;
}

export interface Pago {
  id: number;
  fecha: string;
  monto_total: number;
  anulado: boolean;
  motivo_anulacion: string | null;
  notas: string | null;
  detalles: LineaPago[];
  registrado_por?: string | null;
  tiene_comprobante?: boolean;
  anulado_at?: string | null;
  anulado_por?: string | null;
  total_abono_profesor?: number;
  acciones?: { editar: boolean; anular: boolean };
}

export interface CuotaParaCobrar {
  id: number;
  monto: number;
  label: string;
  nombre: string;
  anio: number;
  mes: number | null;
  alcance: string;
  bloque: string | null;
  sede_nombre: string | null;
  activo: boolean;
  abono_docente_ref: number;
  liquidacion_resumen: string | null;
}

export const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

export const ALCANCE_CUOTA: Record<string, string> = { general: 'Toda la escuela', sede: 'Sede', bloque: 'Bloque' };
