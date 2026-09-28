<?php

namespace App\Policies;

use App\Models\Profesor;
use App\Models\User;

/**
 * Un docente cae en el alcance si da clase en un bloque del alcance o tiene un rol
 * en una sede del alcance. Sin bloques ni sedes, solo lo gestiona el alcance global.
 */
class ProfesorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('profesores.view');
    }

    public function view(User $user, Profesor $profesor): bool
    {
        return $this->esPropio($user, $profesor) || $this->alcanza($user, 'profesores.view', $profesor);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('profesores.create');
    }

    public function update(User $user, Profesor $profesor): bool
    {
        return $this->alcanza($user, 'profesores.update', $profesor);
    }

    public function delete(User $user, Profesor $profesor): bool
    {
        return $this->alcanza($user, 'profesores.delete', $profesor);
    }

    public function esPropio(User $user, Profesor $profesor): bool
    {
        return ($user->persona_id && (int) $profesor->persona_id === (int) $user->persona_id)
            || ($profesor->user_id && (int) $profesor->user_id === (int) $user->id);
    }

    private function alcanza(User $user, string $permiso, Profesor $profesor): bool
    {
        $acceso = $user->acceso();
        if ($acceso->puedeGlobal($permiso)) {
            return true;
        }
        if (! $acceso->puede($permiso)) {
            return false;
        }
        foreach ($profesor->bloqueIdsDondeParticipa() as $bloqueId) {
            if ($acceso->puedeEnBloque($permiso, (int) $bloqueId)) {
                return true;
            }
        }
        foreach ($profesor->sedesConRol()->pluck('sedes.id') as $sedeId) {
            if ($acceso->puedeEnSede($permiso, (int) $sedeId)) {
                return true;
            }
        }

        return false;
    }
}
