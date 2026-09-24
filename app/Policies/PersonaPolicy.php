<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;

/**
 * Una persona se ve si es uno mismo, si el permiso es global, o si alguno de sus
 * perfiles de alumno cae dentro del alcance.
 */
class PersonaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('personas.view');
    }

    public function view(User $user, Persona $persona): bool
    {
        return $this->esUnoMismo($user, $persona) || $this->alcanza($user, 'personas.view', $persona);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('personas.create');
    }

    public function update(User $user, Persona $persona): bool
    {
        return $this->alcanza($user, 'personas.update', $persona);
    }

    public function delete(User $user, Persona $persona): bool
    {
        return $user->acceso()->puedeGlobal('personas.delete');
    }

    public function merge(User $user): bool
    {
        return $user->acceso()->puedeGlobal('personas.merge');
    }

    public function esUnoMismo(User $user, Persona $persona): bool
    {
        return $user->persona_id && (int) $user->persona_id === (int) $persona->id;
    }

    private function alcanza(User $user, string $permiso, Persona $persona): bool
    {
        $acceso = $user->acceso();
        if ($acceso->puedeGlobal($permiso)) {
            return true;
        }
        if (! $acceso->puede($permiso)) {
            return false;
        }
        foreach ($persona->alumnos()->get() as $alumno) {
            if ($acceso->puedeSobreAlumno($permiso, $alumno)) {
                return true;
            }
        }
        $profesor = $persona->profesor;
        if ($profesor) {
            foreach ($profesor->bloqueIdsDondeParticipa() as $bloqueId) {
                if ($acceso->puedeEnBloque($permiso, (int) $bloqueId)) {
                    return true;
                }
            }
        }

        return false;
    }
}
