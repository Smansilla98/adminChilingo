export type Ambito = 'global' | 'sede' | 'bloque';

export interface Funcion {
  clave: string;
  rol: string;
  rol_nombre: string;
  ambito: Ambito;
  ambito_nombre: string;
  sede_id: number | null;
  bloque_id: number | null;
  origen: string;
  origen_etiqueta: string;
}

export interface Contexto {
  clave: string;
  rol: string;
  etiqueta: string;
  sede_id: number | null;
  sede: string | null;
  bloques: { id: number; nombre: string }[];
}

export interface Modulo {
  clave: string;
  etiqueta: string;
  icono: string | null;
}

export interface Me {
  usuario: { id: number; username: string; email: string; nombre: string };
  persona: { id: number; nombre: string; apellido: string | null; telefono: string | null } | null;
  funciones: Funcion[];
  contextos: Contexto[];
  contexto_actual: string | null;
  permisos: string[];
  modulos: Modulo[];
  superadmin: boolean;
}

export interface Tarjeta {
  tipo: 'mi_espacio' | 'clases_hoy' | 'finanzas' | 'inventario' | 'eventos';
  rol: string | null;
  datos: any;
}

export interface Inicio {
  saludo: string;
  avisos_no_leidos: number;
  modulos: string[];
  tarjetas: Tarjeta[];
}

export interface Bloque {
  id: number;
  nombre: string;
  anio: number;
  sede: { id: number; nombre: string } | null;
  cantidad_alumnos?: number;
  horarios?: { dia: number; dia_nombre: string; inicio: string; fin: string }[];
}

export type TipoAsistencia =
  | 'presente'
  | 'tarde'
  | 'ausencia_justificada'
  | 'ausencia_injustificada'
  | 'feriado'
  | 'sin_clases';

export interface Planilla {
  bloque: { id: number; nombre: string };
  fecha: string;
  tomada: boolean;
  puede_editar: boolean;
  tipos: Record<TipoAsistencia, string>;
  alumnos: { alumno_id: number; nombre: string; tipo: TipoAsistencia | null }[];
}

export interface ItemCuenta {
  cuota_id: number;
  nombre: string;
  periodo: string;
  vencimiento: string | null;
  bruto: number;
  beca: { id: number; etiqueta: string } | null;
  descuento: number;
  neto: number;
  pagado: number;
  saldo: number;
  estado: 'pagada' | 'becada' | 'parcial' | 'vencida' | 'pendiente';
}

export interface EstadoCuenta {
  alumno_id: number;
  anio: number;
  items: ItemCuenta[];
  totales: { bruto: number; descuento: number; neto: number; pagado: number; saldo: number; vencido: number };
  becas: { id: number; etiqueta: string; estado: string; desde: string; hasta: string | null; motivo: string | null }[];
}

export interface ItemAgenda {
  tipo: string;
  id: string;
  titulo: string;
  fecha: string;
  inicio: string | null;
  fin: string | null;
  sede: string | null;
  bloque_id: number | null;
}

export interface InventarioItem {
  id: number;
  codigo: string | null;
  nombre: string;
  tipo: string;
  tipo_nombre: string;
  estado: string;
  estado_nombre: string;
  marca: string | null;
  modelo: string | null;
  medida: string | null;
  cantidad: number;
  propietario: string;
  sede: { id: number; nombre: string } | null;
  notas: string | null;
  puede_editar: boolean;
  movimientos?: { id: number; tipo: string; tipo_nombre: string; nota: string | null; sede: string | null; autor: string | null; fecha: string }[];
}

export interface Aviso {
  id: string;
  tipo: string;
  titulo: string;
  mensaje: string;
  enlace: string | null;
  leida: boolean;
  fecha: string;
}

export interface Paginado<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
}
