<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\User;

class AlumnoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('alumnos.view');
    }

    public function view(User $user, Alumno $alumno): bool
    {
        return $this->esPropio($user, $alumno) || $user->acceso()->puedeSobreAlumno('alumnos.view', $alumno);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('alumnos.create');
    }

    public function update(User $user, Alumno $alumno): bool
    {
        return $user->acceso()->puedeSobreAlumno('alumnos.update', $alumno);
    }

    public function delete(User $user, Alumno $alumno): bool
    {
        return $user->acceso()->puedeSobreAlumno('alumnos.delete', $alumno);
    }

    /** Cuotas, becas y pagos del alumno. */
    public function verFinanzas(User $user, Alumno $alumno): bool
    {
        $acceso = $user->acceso();

        return $this->esPropio($user, $alumno)
            || $acceso->puedeSobreAlumno('cuotas.view', $alumno)
            || $acceso->puedeSobreAlumno('pagos.view', $alumno);
    }

    public function gestionarBecas(User $user, Alumno $alumno): bool
    {
        return $user->acceso()->puedeSobreAlumno('becas.manage', $alumno);
    }

    public function esPropio(User $user, Alumno $alumno): bool
    {
        return ($user->persona_id && (int) $alumno->persona_id === (int) $user->persona_id)
            || ($alumno->user_id && (int) $alumno->user_id === (int) $user->id);
    }
}
