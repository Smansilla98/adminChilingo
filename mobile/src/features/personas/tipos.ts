import type { EstadoCuenta, Funcion } from '@/lib/types';

/** Permisos efectivos agrupados por módulo (qué puede hacer, dónde y por qué rol). */
export type PermisosAgrupados = Record<string, { permiso: string; etiqueta: string; alcance: string; via: string[] }[]>;

export interface PersonaResumen {
  id: number;
  nombre: string;
  apellido: string | null;
  nombre_completo: string;
  dni: string | null;
  fecha_nacimiento: string | null;
  telefono: string | null;
  email: string | null;
  estado: 'activo' | 'inactivo' | 'baja';
  tiene_cuenta?: boolean;
  es_alumno?: boolean;
  es_docente?: boolean;
}

export interface Persona extends PersonaResumen {
  direccion: string | null;
  contacto_emergencia_nombre: string | null;
  contacto_emergencia_telefono: string | null;
  observaciones: string | null;
  edad: number | null;
  fusionada_en: { id: number; nombre: string } | null;
  funciones: Funcion[];
  permisos: PermisosAgrupados | null;
  cuenta: { id: number; username: string; activo: boolean; ultimo_acceso: string | null } | null;
  alumnos: {
    id: number;
    activo: boolean;
    instrumento: string | null;
    sede: { id: number; nombre: string } | null;
    bloques: { id: number; nombre: string; sede: string | null }[];
    estado_cuenta: EstadoCuenta | null;
    puede_ver: boolean;
    puede_gestionar_becas: boolean;
  }[];
  profesor: {
    id: number;
    activo: boolean;
    bloques: { id: number; nombre: string; sede: string | null; rol: string }[];
    sedes: { id: number; nombre: string; rol: string }[];
  } | null;
  asistencias: { id: number; fecha: string; tipo: string; tipo_nombre: string; bloque: string | null }[];
  eventos: { id: number; titulo: string; fecha: string; hora_inicio: string | null; sede: string | null }[];
  inventario: { id: number; codigo: string | null; nombre: string; estado: string; sede: string | null }[];
  acciones: {
    editar: boolean;
    fusionar: boolean;
    inscribir_alumno: boolean;
    sumar_docente: boolean;
    crear_cuenta: boolean;
    ver_cuenta: boolean;
    gestionar_becas: boolean;
    ver_auditoria: boolean;
  };
  tipos_beca: Record<string, string>;
}

export const ESTADOS_PERSONA = [
  { valor: 'activo', etiqueta: 'Activo' },
  { valor: 'inactivo', etiqueta: 'Inactivo' },
  { valor: 'baja', etiqueta: 'Baja' },
] as const;

export interface Beca {
  id: number;
  etiqueta: string;
  tipo: string;
  tipo_nombre: string;
  porcentaje: number | null;
  monto: number | null;
  estado: 'activa' | 'suspendida' | 'finalizada';
  estado_nombre: string;
  desde: string;
  hasta: string | null;
  motivo: string | null;
  observaciones: string | null;
  alumno: { id: number; nombre: string; persona_id: number | null } | null;
  bloque: { id: number; nombre: string } | null;
  sede: { id: number; nombre: string } | null;
  otorgada_por: string | null;
  puede_gestionar: boolean;
}

export const ESTADOS_BECA = [
  { valor: 'activa', etiqueta: 'Activa' },
  { valor: 'suspendida', etiqueta: 'Suspendida' },
  { valor: 'finalizada', etiqueta: 'Finalizada' },
] as const;
