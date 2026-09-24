<?php

namespace App\Policies;

use App\Models\Bloque;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BloquePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('bloques.view');
    }

    public function view(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('bloques.view', $bloque->id, $bloque->sede_id)
            || $this->esAlumnoDelBloque($user, $bloque);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('bloques.manage');
    }

    public function update(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('bloques.manage', $bloque->id, $bloque->sede_id);
    }

    public function delete(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('bloques.delete', $bloque->id, $bloque->sede_id);
    }

    public function tomarAsistencia(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('asistencias.create', $bloque->id, $bloque->sede_id);
    }

    public function verAsistencias(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('asistencias.view', $bloque->id, $bloque->sede_id);
    }

    private function esAlumnoDelBloque(User $user, Bloque $bloque): bool
    {
        if (! $user->persona_id) {
            return false;
        }

        return DB::table('alumno_bloque')
            ->join('alumnos', 'alumnos.id', '=', 'alumno_bloque.alumno_id')
            ->where('alumno_bloque.bloque_id', $bloque->id)
            ->where('alumnos.persona_id', $user->persona_id)
            ->exists();
    }
}
