export interface ConfigGira {
  fecha_inicio: string | null;
  fecha_fin: string | null;
  cupo_maximo: number;
  aporte_esperado: number;
  notas: string | null;
  dias: number;
  valor_por_dia: number;
}

export interface PlanGira {
  dias: number;
  cupo: number;
  plazas_ocupadas: number;
  lista_espera: number;
  valor_por_dia: number;
  ingresos_pagados: number;
  ingresos_esperados: number;
  ingresos_si_cupo_lleno: number;
  gastos_por_tipo: Record<string, number>;
  gastos_totales: number;
  insumos_totales: number;
  balance_pagado: number;
  balance_esperado: number;
  balance_cupo_lleno: number;
}

export interface Inscripto {
  id: number;
  alumno: { id: number; nombre: string; sede: string | null; persona_id: number | null } | null;
  estado_pago: string;
  estado_pago_nombre: string;
  monto_esperado: number;
  monto_pagado: number;
  saldo: number;
  plaza: number | null;
  lista_espera: boolean;
  fecha_desde: string | null;
  fecha_hasta: string | null;
  dias: number;
  talle_remera: string | null;
  tambor_principal: string | null;
  tambor_secundario: string | null;
  tambor_terciario: string | null;
  tambor_principal_origen: string | null;
  tambor_secundario_origen: string | null;
  tambor_terciario_origen: string | null;
  notas: string | null;
}

export interface CatalogoGira {
  estados_pago: Record<string, string>;
  talles: string[];
  tambores: string[];
  origenes_tambor: Record<string, string>;
  categorias_insumo: Record<string, string>;
  tipos_gasto: Record<string, string>;
  modos_gasto: Record<string, string>;
  sedes: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede: string | null; profesor: string | null }[];
  profesores: { id: number; nombre: string }[];
}

export interface Tocada { id: number; dia_id: number; orden: number; hora: string | null; que: string; donde: string | null; notas: string | null }
export interface DiaGira { id: number; fecha: string; notas: string | null; tocadas: Tocada[] }
export interface GastoGira { id: number; tipo: string; tipo_nombre: string; concepto: string; monto: number; modo: string; fecha: string | null; notas: string | null; proyectado: number }
export interface InsumoGira { id: number; nombre: string; categoria: string; categoria_nombre: string; cantidad: number; unidad: string | null; costo_unitario: number; costo_total: number; notas: string | null }

export const COLOR_PAGO: Record<string, string> = { pendiente: '#e6a817', sena: '#4fb3d9', pago: '#3daf3a', beca: '#a3a3a3' };
