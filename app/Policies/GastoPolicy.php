<?php

namespace App\Policies;

use App\Models\Gasto;
use App\Models\User;

class GastoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('gastos.view');
    }

    public function view(User $user, Gasto $gasto): bool
    {
        return $this->alcanza($user, 'gastos.view', $gasto);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('gastos.create');
    }

    public function update(User $user, Gasto $gasto): bool
    {
        return $this->alcanza($user, 'gastos.update', $gasto);
    }

    public function delete(User $user, Gasto $gasto): bool
    {
        return $this->alcanza($user, 'gastos.delete', $gasto);
    }

    public function approve(User $user, Gasto $gasto): bool
    {
        return $this->alcanza($user, 'gastos.approve', $gasto);
    }

    /** Gastos sin sede son de la escuela: requieren alcance global. */
    private function alcanza(User $user, string $permiso, Gasto $gasto): bool
    {
        return $gasto->sede_id
            ? $user->acceso()->puedeEnSede($permiso, (int) $gasto->sede_id)
            : $user->acceso()->puedeGlobal($permiso);
    }
}
