import type { Show } from '@/features/agenda/tipos';

export type Respuesta = 'voy' | 'tal_vez' | 'no_voy';

export const RESPUESTAS: { valor: Respuesta; etiqueta: string; color: string }[] = [
  { valor: 'voy', etiqueta: 'Voy', color: '#3daf3a' },
  { valor: 'tal_vez', etiqueta: 'Tal vez', color: '#e6a817' },
  { valor: 'no_voy', etiqueta: 'No voy', color: '#e31b23' },
];

export const colorRespuesta = (r: Respuesta | null) => RESPUESTAS.find((x) => x.valor === r)?.color ?? '#a3a3a3';

export interface Logistica {
  punto_encuentro: string | null;
  hora_encuentro: string | null;
  transporte: string | null;
  notas: string | null;
}

export interface Tema {
  id: number;
  orden: number;
  nombre: string;
  titulo: string | null;
  programa_ritmo_id: number | null;
  slug: string | null;
  notas: string | null;
}

export interface EnsayoResumen {
  id: number;
  fecha: string;
  hora_inicio: string | null;
  hora_fin: string | null;
  lugar: string | null;
  notas: string | null;
  presentes: number;
}

export interface InstrumentoShow {
  id: number;
  nombre: string;
  inventario_item_id: number | null;
  descripcion: string | null;
  cantidad: number;
}

export interface PlanShow {
  logistica: Logistica;
  temas: Tema[];
  ensayos: EnsayoResumen[];
  instrumentos: InstrumentoShow[];
}

export interface ResumenConfirmaciones {
  convocados: number;
  voy: number;
  tal_vez: number;
  no_voy: number;
  sin_respuesta: number;
  asistieron: number;
}

/** Detalle de show para quien organiza (GET shows/{id}). */
export type ShowDetalle = Omit<Show, 'acciones'> & PlanShow & {
  acciones: { editar: boolean; eliminar: boolean; asistencia: boolean } | null;
  confirmaciones: ResumenConfirmaciones | null;
};

export interface Convocado {
  alumno_id: number;
  nombre: string;
  bloque: string | null;
  respuesta: Respuesta | null;
  respuesta_texto: string;
  comentario: string | null;
  respondido_at: string | null;
  asistio: boolean | null;
}

export interface Ensayo {
  id: number;
  show_id: number;
  show: string;
  fecha: string;
  hora_inicio: string | null;
  hora_fin: string | null;
  lugar: string | null;
  notas: string | null;
  asistencia: { alumno_id: number; nombre: string; presente: boolean | null }[];
  acciones: { editar: boolean; asistencia: boolean };
}

export interface CatalogoPlan {
  toques: { id: number; nombre: string; anio: number | null }[];
  instrumentos: { id: number; nombre: string; codigo: string | null; sede: string | null }[];
}

/** Show visto por el alumno convocado (GET mi/shows). */
export interface MiShow {
  id: number;
  titulo: string;
  fecha: string;
  hora_inicio: string | null;
  hora_fin: string | null;
  lugar: string | null;
  descripcion: string | null;
  convocatoria_abierta: boolean;
  alumno_id: number;
  mi_respuesta: Respuesta | null;
  mi_comentario: string | null;
  asisti: boolean | null;
}

export type MiShowDetalle = MiShow & PlanShow;
