import { useQuery } from '@tanstack/react-query';

import { api, qs } from '@/lib/api';

/** Estados de un toque: no es una nota, dice en qué anda el alumno con ese toque. */
export type EstadoToque = 'no_empezado' | 'en_proceso' | 'aprobado';

export const ESTADOS_TOQUE: Record<EstadoToque, string> = {
  no_empezado: 'Todavía no',
  en_proceso: 'Lo está trabajando',
  aprobado: 'Lo toca con el bloque',
};

export const SIGUIENTE_ESTADO: Record<EstadoToque, EstadoToque> = {
  no_empezado: 'en_proceso',
  en_proceso: 'aprobado',
  aprobado: 'no_empezado',
};

export interface ToqueProgreso { id: number; slug: string; nombre: string; opcional: boolean; estado: EstadoToque; estado_nombre: string; fecha: string | null; docente: string | null }
export interface AnioProgreso { anio: number; toques: ToqueProgreso[]; tocados: number; en_proceso: number; total: number }
export interface Repaso { id: number; fecha: string; resultado: string; resultado_nombre: string; devolucion: string | null; toques: string[]; docente: string | null; bloque: string | null; visible_alumno: boolean }
export interface Propuesta { id: number; alumno_id?: number; alumno?: string | null; anio_desde: number; anio_hasta: number; estado?: string; desde?: string | null }

export interface ProgresoAlumno {
  alumno: { id: number; nombre: string };
  anio_actual: number;
  anios: AnioProgreso[];
  evaluaciones: Repaso[];
  historial: { anio: number; desde: string; hasta: string | null; bloque: string | null; confirmado_por: string | null }[];
  propuesta: Propuesta | null;
  puede_resolver?: boolean;
}

export interface BloqueProgreso { id: number; nombre: string; anio: number | null; sede: string | null; puede_editar: boolean }

export interface MatrizBloque {
  bloque: { id: number; nombre: string; anio: number | null; sede: string | null };
  anio: number;
  puede_editar: boolean;
  estados: Record<EstadoToque, string>;
  resultados: Record<string, string>;
  toques: { id: number; nombre: string; slug: string; opcional: boolean }[];
  alumnos: { id: number; nombre: string; anio_actual: number; obligatorios_tocados: number; obligatorios: number; estados: Record<string, EstadoToque> }[];
  propuestas: Propuesta[];
}

export const useMiProgreso = () => useQuery({ queryKey: ['mi', 'progreso'], queryFn: () => api<{ data: ProgresoAlumno[] }>('mi/progreso') });

export const useBloquesProgreso = () => useQuery({ queryKey: ['progreso', 'bloques'], queryFn: () => api<{ data: BloqueProgreso[] }>('progreso/bloques') });

export const useMatriz = (bloque: number, anio: number | null) =>
  useQuery({ queryKey: ['progreso', 'bloque', bloque, anio], queryFn: () => api<MatrizBloque>(`progreso/bloques/${bloque}${qs({ anio })}`), enabled: bloque > 0 });
