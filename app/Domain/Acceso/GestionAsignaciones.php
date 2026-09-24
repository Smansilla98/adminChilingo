<?php

namespace App\Domain\Acceso;

use App\Models\Asignacion;
use App\Models\Bloque;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Alta y baja de asignaciones de rol/permiso con las reglas de negocio:
 * ámbitos válidos por rol, roles derivados no asignables, protección de administración
 * y de que siempre quede al menos un superadministrador.
 */
class GestionAsignaciones
{
    /**
     * @param  array{tipo: string, nombre: string, ambito: string, sede_id?: ?int, bloque_id?: ?int, desde?: ?string, hasta?: ?string, notas?: ?string}  $datos
     */
    public function asignar(Persona $persona, array $datos, User $por): Asignacion
    {
        $tipo = $datos['tipo'];
        $ambito = $datos['ambito'];
        $sedeId = $ambito === 'sede' ? ($datos['sede_id'] ?? null) : null;
        $bloqueId = $ambito === 'bloque' ? ($datos['bloque_id'] ?? null) : null;

        if ($ambito === 'sede' && ! $sedeId) {
            throw ValidationException::withMessages(['sede_id' => 'Elegí la sede.']);
        }
        if ($ambito === 'bloque' && ! $bloqueId) {
            throw ValidationException::withMessages(['bloque_id' => 'Elegí el bloque.']);
        }
        if ($bloqueId && ! Bloque::query()->whereKey($bloqueId)->exists()) {
            throw ValidationException::withMessages(['bloque_id' => 'El bloque no existe.']);
        }

        $roleId = null;
        $permissionId = null;
        if ($tipo === 'rol') {
            $rol = $datos['nombre'];
            if (! CatalogoPermisos::existeRol($rol)) {
                throw ValidationException::withMessages(['nombre' => 'Rol desconocido.']);
            }
            if (CatalogoPermisos::esDerivado($rol)) {
                throw ValidationException::withMessages([
                    'nombre' => 'El rol «'.CatalogoPermisos::nombreRol($rol).'» surge de los datos (inscripción o beca) y no se asigna a mano.',
                ]);
            }
            if (! in_array($ambito, CatalogoPermisos::ambitosDeRol($rol), true)) {
                throw ValidationException::withMessages([
                    'ambito' => 'El rol «'.CatalogoPermisos::nombreRol($rol).'» se asigna con alcance: '.implode(', ', CatalogoPermisos::ambitosDeRol($rol)).'.',
                ]);
            }
            if (CatalogoPermisos::esRolDeAdministracion($rol) && ! $por->acceso()->puedeGlobal('usuarios.assign_admin')) {
                throw ValidationException::withMessages(['nombre' => 'No tenés permiso para asignar roles de administración.']);
            }
            $roleId = Role::findOrCreate($rol, 'web')->id;
        } else {
            $permiso = $datos['nombre'];
            if (! CatalogoPermisos::existe($permiso)) {
                throw ValidationException::withMessages(['nombre' => 'Permiso desconocido.']);
            }
            if ($permiso === 'usuarios.assign_admin' && ! $por->acceso()->puedeGlobal('usuarios.assign_admin')) {
                throw ValidationException::withMessages(['nombre' => 'No tenés permiso para delegar la administración.']);
            }
            $permissionId = Permission::findOrCreate($permiso, 'web')->id;
        }

        $duplicada = Asignacion::query()->vigentes()
            ->where('persona_id', $persona->id)
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->where('ambito_tipo', $ambito)
            ->where('sede_id', $sedeId)
            ->where('bloque_id', $bloqueId)
            ->exists();
        if ($duplicada) {
            throw ValidationException::withMessages(['asignacion' => 'Esa asignación ya existe.']);
        }

        return Asignacion::query()->create([
            'persona_id' => $persona->id,
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'ambito_tipo' => $ambito,
            'sede_id' => $sedeId,
            'bloque_id' => $bloqueId,
            'desde' => $datos['desde'] ?? null,
            'hasta' => $datos['hasta'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'activo' => true,
            'creado_por' => $por->id,
        ]);
    }

    public function quitar(Asignacion $asignacion, User $por): void
    {
        $rol = $asignacion->role?->name;
        if ($rol && CatalogoPermisos::esRolDeAdministracion($rol) && ! $por->acceso()->puedeGlobal('usuarios.assign_admin')) {
            throw ValidationException::withMessages(['asignacion' => 'No tenés permiso para quitar roles de administración.']);
        }
        if ($rol === 'superadministrador') {
            $restantes = Asignacion::query()->vigentes()
                ->where('role_id', $asignacion->role_id)
                ->where('ambito_tipo', 'global')
                ->where('id', '!=', $asignacion->id)
                ->count();
            if ($restantes === 0) {
                throw ValidationException::withMessages(['asignacion' => 'Tiene que quedar al menos un superadministrador.']);
            }
        }

        $asignacion->delete();
    }
}
