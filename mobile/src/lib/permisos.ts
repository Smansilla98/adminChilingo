import { useMe } from './queries';

/**
 * Permisos del usuario (de /me) para mostrar u ocultar acciones. Es solo UX: el
 * backend vuelve a verificar permiso y alcance en cada operación.
 *
 * Para acciones sobre un registro concreto (editar, anular…) usar el campo `acciones`
 * que devuelve la API junto al registro: ese sí considera el alcance (sede/bloque).
 */
export function usePermisos() {
  const me = useMe();
  const lista = me.data?.permisos ?? [];
  const superadmin = !!me.data?.superadmin;
  const alcances = me.data?.alcances ?? {};
  const puede = (...permisos: string[]) => superadmin || permisos.some((p) => lista.includes(p));
  /** El permiso vale para toda la escuela (p. ej. crear sedes, gastos sin sede). */
  const puedeGlobal = (permiso: string) => superadmin || !!alcances[permiso]?.global;
  const puedeEnSede = (permiso: string, sedeId: number | null | undefined) =>
    puedeGlobal(permiso) || (!!sedeId && !!alcances[permiso]?.sedes.includes(sedeId));
  return { puede, puedeGlobal, puedeEnSede, cargado: !!me.data };
}

export function usePuede(...permisos: string[]): boolean {
  return usePermisos().puede(...permisos);
}
