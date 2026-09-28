import type { PermisosAgrupados } from '@/features/personas/tipos';
import type { Funcion } from '@/lib/types';

export interface UsuarioResumen {
  id: number;
  username: string;
  email: string;
  nombre: string;
  activo: boolean;
  ultimo_acceso: string | null;
  roles: string[];
}

export interface Usuario {
  id: number;
  username: string;
  email: string;
  telefono: string | null;
  nombre: string;
  activo: boolean;
  ultimo_acceso: string | null;
  persona: { id: number; nombre: string } | null;
  superadmin: boolean;
  funciones: Funcion[];
  permisos: PermisosAgrupados;
  dispositivos: { id: number; nombre: string; ultimo_uso: string | null; vence: string | null; desde: string | null }[];
  acciones: { editar: boolean; activar: boolean; gestionar_permisos: boolean };
}

export interface CatalogoUsuarios {
  roles: Record<string, string>;
  roles_ambitos: Record<string, string[]>;
  permisos: Record<string, Record<string, string>>;
  sedes: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede: string | null }[];
}
