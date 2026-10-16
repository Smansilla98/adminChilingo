<?php

namespace App\Support;

use App\Models\Alumno;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Collection;

/** Fichas de alumno de una cuenta: por el usuario o por la persona vinculada. */
final class FichasAlumno
{
    /** @return Collection<int, Alumno> */
    public static function delUsuario(User $user): Collection
    {
        return Alumno::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->when($user->persona_id, fn ($q2) => $q2->orWhere('persona_id', $user->persona_id)))
            ->with(['bloques', 'bloque', 'sede'])
            ->get();
    }

    /**
     * Fichas activas que parecen ser de esta persona pero están cargadas aparte
     * (mismo DNI o mismo nombre). Sirven para que administración las una.
     *
     * @return Collection<int, Alumno>
     */
    public static function candidatas(User $user): Collection
    {
        $persona = $user->persona;
        $esPersona = $persona instanceof Persona;
        $nombre = trim($esPersona && $persona->nombre_completo !== '' ? $persona->nombre_completo : (string) $user->name);
        $dni = $esPersona ? (string) preg_replace('/\D/', '', (string) $persona->dni) : '';
        if ($nombre === '' && $dni === '') {
            return collect();
        }

        return Alumno::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('persona_id')->orWhere('persona_id', '!=', (int) $user->persona_id))
            ->where(function ($q) use ($nombre, $dni) {
                if ($dni !== '') {
                    $q->orWhere('dni', $dni);
                }
                if ($nombre !== '') {
                    $q->orWhere('nombre_apellido', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $nombre).'%');
                }
            })
            ->with(['bloques.sede', 'persona'])
            ->limit(5)
            ->get();
    }
}
