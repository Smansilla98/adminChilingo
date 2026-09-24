<?php

namespace App\Policies;

use App\Models\User;

/**
 * La administración de cuentas es global: nadie gestiona usuarios "de su sede".
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puedeGlobal('usuarios.view');
    }

    public function view(User $user, User $objetivo): bool
    {
        return $user->is($objetivo) || $user->acceso()->puedeGlobal('usuarios.view');
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puedeGlobal('usuarios.create');
    }

    public function update(User $user, User $objetivo): bool
    {
        if (! $user->acceso()->puedeGlobal('usuarios.update')) {
            return false;
        }

        // Solo quien puede asignar administración puede tocar cuentas de administración.
        return ! $objetivo->acceso()->esAdministrador() || $user->acceso()->puedeGlobal('usuarios.assign_admin') || $user->is($objetivo);
    }

    public function managePermissions(User $user, ?User $objetivo = null): bool
    {
        if (! $user->acceso()->puedeGlobal('usuarios.permissions')) {
            return false;
        }

        return ! $objetivo || ! $objetivo->acceso()->esAdministrador() || $user->acceso()->puedeGlobal('usuarios.assign_admin');
    }
}
