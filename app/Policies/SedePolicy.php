<?php

namespace App\Policies;

use App\Models\Sede;
use App\Models\User;

class SedePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('sedes.view');
    }

    public function view(User $user, Sede $sede): bool
    {
        return $user->acceso()->puedeEnSede('sedes.view', $sede->id);
    }

    /** Crear sedes es una decisión de toda la escuela. */
    public function create(User $user): bool
    {
        return $user->acceso()->puedeGlobal('sedes.manage');
    }

    public function update(User $user, Sede $sede): bool
    {
        return $user->acceso()->puedeEnSede('sedes.manage', $sede->id);
    }

    public function delete(User $user, Sede $sede): bool
    {
        return $user->acceso()->puedeGlobal('sedes.delete');
    }
}
