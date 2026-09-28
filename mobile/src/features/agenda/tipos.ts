export interface Sede {
  id: number;
  nombre: string;
  direccion: string | null;
  activo: boolean;
  tipo_propiedad: string | null;
  tipo_propiedad_nombre?: string | null;
  costo_alquiler_mensual: number | null;
  liquidacion_retencion_escuela: number | null;
  liquidacion_porc_docente: number | null;
  cantidad_alumnos: number;
  bloques: { id: number; nombre: string; anio: number; activo: boolean; profesor: string | null; cantidad_alumnos: number }[];
  eventos: { id: number; titulo: string; fecha: string; hora_inicio: string | null }[];
  acciones: { editar: boolean; eliminar: boolean };
}

export interface Evento {
  id: number;
  titulo: string;
  descripcion: string | null;
  tipo: string;
  tipo_nombre: string;
  fecha: string;
  hora_inicio: string | null;
  hora_fin: string | null;
  sede: { id: number; nombre: string } | null;
  bloque: { id: number; nombre: string } | null;
  profesor: { id: number; nombre: string } | null;
  cantidad_personas: number | null;
  ambito: 'bloque' | 'sede' | 'escuela';
  creado_por: string | null;
  acciones: { editar: boolean; eliminar: boolean } | null;
}

export interface Show {
  id: number;
  titulo: string;
  fecha: string;
  hora_inicio: string | null;
  hora_fin: string | null;
  lugar: string | null;
  descripcion: string | null;
  convocatoria_abierta: boolean;
  bloques: { id: number; nombre: string; sede: string | null; profesor: string | null }[];
  acciones: { editar: boolean; eliminar: boolean } | null;
}

export interface CatalogoEventos {
  tipos: Record<string, string>;
  sedes: { id: number; nombre: string }[];
  profesores: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede_id: number | null; sede: string | null }[];
}
