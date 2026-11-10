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
  asignacion_id?: number | null;
  editable?: boolean;
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
  /** Dónde vale cada permiso (global, sedes, bloques). */
  alcances?: Record<string, { global: boolean; sedes: number[]; bloques: number[] }>;
  modulos: Modulo[];
  superadmin: boolean;
  /** Entró con una contraseña provisoria: tiene que elegir la suya antes de seguir. */
  debe_cambiar_clave?: boolean;
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
  /** Año en que arrancó el bloque; el año del programa queda como referencia. */
  funciona_desde?: number | null;
  anios_funcionando?: number | null;
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
  alumnos: { alumno_id: number; nombre: string; tipo: TipoAsistencia | null; asistencia_id?: number | null }[];
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
  /** Fecha (YYYY-MM-DD) del último pago de la cuota. */
  pagado_el?: string | null;
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
  puede_eliminar?: boolean;
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

/** Datos para pagar (Configuración → Avisos y cobro; lo vacío viene de COBRO_* del servidor). */
export interface DatosTransferencia {
  alias?: string;
  cbu?: string;
  titular?: string;
  cuit?: string;
  banco?: string;
}

export interface CobroDisponible {
  transferencia: DatosTransferencia | null;
  mercado_pago: boolean;
}

/** Configuración de un aviso automático (GET/PUT config/avisos). */
export interface AvisoConfig {
  nombre: string;
  descripcion: string;
  para: string;
  activo: boolean;
  canales: string[];
  titulo: string;
  mensaje: string;
  dias?: number;
  /** Recordatorio de pasar lista: minutos después de empezar la clase. */
  minutos?: number;
  /** Cómo se llama el plazo (días o minutos) de este aviso. */
  plazo_etiqueta?: string;
  /** Variables que sirven en este aviso. */
  variables?: string[];
}

export interface ConfigAvisos {
  avisos: Record<string, AvisoConfig>;
  canales: Record<string, string>;
  variables: Record<string, string>;
  cobro: DatosTransferencia;
  cobro_efectivo: DatosTransferencia;
  mercado_pago: boolean;
  /** Notificaciones en el celular: si el servidor las manda y cuántos alumnos tienen la app con push. */
  push?: { habilitado: boolean; alumnos_con_app: number; celulares: number };
}
