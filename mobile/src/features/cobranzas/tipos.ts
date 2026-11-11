/** Cobranzas (morosidad y cuotas por lote): formas de la API /morosos y /cuotas/lote. */

export type Tramo = '0-30' | '31-60' | '61-90' | '+90';

export interface Moroso {
  alumno_id: number;
  alumno: string;
  sede: string | null;
  bloques: string[];
  cuotas_vencidas: number;
  saldo_vencido: number;
  vencida_desde: string;
  dias_mora: number;
  tramo: Tramo;
  tiene_cuenta: boolean;
}

export interface RespuestaMorosos {
  data: Moroso[];
  total_saldo: number;
  tramos: { id: Tramo; nombre: string }[];
}

export type EstadoRecordatorio = 'enviado' | 'duplicado' | 'sin_envio' | 'al_dia' | 'sin_cuenta';

export interface ResultadoRecordatorio {
  estado: EstadoRecordatorio;
  canales: Record<string, string>;
}

export interface ResultadoMasivo {
  enviado: number;
  duplicado: number;
  sin_envio: number;
  al_dia: number;
  sin_cuenta: number;
}

export interface ItemLote {
  nombre: string;
  periodo: string;
  mes: number;
  destino: string;
  bloque_id: number | null;
  sede_id: number | null;
  vencimiento: string | null;
  monto: number;
  estado: 'crear' | 'existe' | 'sin_permiso';
}

export interface VistaPreviaLote {
  total: number;
  crear: number;
  existen: number;
  sin_permiso: number;
  items: ItemLote[];
}

export interface ResultadoLote {
  creadas: number;
  omitidas: number;
}

export interface DatosLote {
  nombre: string;
  año: number;
  mes_desde: number;
  mes_hasta: number;
  monto: number;
  alcance: 'bloque' | 'general';
  bloque_ids: number[];
  dia_vencimiento: number | null;
  descripcion: string | null;
}

export const TRAMO_NOMBRE: Record<Tramo, string> = {
  '0-30': '0 a 30 días',
  '31-60': '31 a 60 días',
  '61-90': '61 a 90 días',
  '+90': 'Más de 90 días',
};
