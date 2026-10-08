<?php

namespace App\Support;

use App\Models\Alumno;
use App\Models\ProgramaRitmo;
use App\Models\User;

/**
 * Quién ve el material de los toques (partitura, PDF, videos, editor).
 *
 * - Sin cuenta: nada; el programa (lista de toques y textos de la escuela) sigue público.
 * - Alumno: los toques hasta el año más alto de los bloques donde cursa. Los años son
 *   saber acumulado: un toque de 3° da por sabido lo de 1° y 2°.
 * - Profesores, coordinación, administración y demás personal: todo.
 */
class AccesoMaterial
{
    /** Sin límite (personal de la escuela). */
    public const SIN_LIMITE = null;

    /**
     * Año máximo que puede ver: null = todo, 0 = nada (sin cuenta).
     */
    public static function anioMaximo(?User $user): ?int
    {
        if (! $user) {
            return 0;
        }

        // Una vez por request (el menú, el listado y cada toque preguntan lo mismo).
        $clave = 'acceso_material.'.$user->id;
        $attrs = request()->attributes;
        if (! $attrs->has($clave)) {
            $attrs->set($clave, self::calcular($user));
        }

        return $attrs->get($clave);
    }

    public static function puedeVer(?User $user, ProgramaRitmo $toque): bool
    {
        $max = self::anioMaximo($user);

        return $max === self::SIN_LIMITE || ($max > 0 && (int) $toque->año <= $max);
    }

    public static function esLimitado(?User $user): bool
    {
        return self::anioMaximo($user) !== self::SIN_LIMITE;
    }

    private static function calcular(User $user): ?int
    {
        $esPersonal = $user->isAdmin() || $user->isProfesor() || $user->puedeGestionarOperativo()
            || $user->acceso()->puede('partituras.admin');
        if ($esPersonal || ! $user->isAlumno()) {
            return self::SIN_LIMITE;
        }

        $anio = Alumno::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->when($user->persona_id, fn ($q2) => $q2->orWhere('persona_id', $user->persona_id)))
            ->with(['bloques' => fn ($q) => $q->where('bloques.activo', true), 'bloque'])
            ->get()
            ->flatMap(fn (Alumno $a) => $a->bloques->push($a->bloque)->filter())
            ->filter(fn ($b) => $b->activo)
            ->max('año');

        // Alumno sin bloque activo con año: ve el primer año (es por donde se empieza).
        return max(1, (int) $anio);
    }
}
