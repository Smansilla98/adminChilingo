<?php

namespace App\Domain\Acceso;

use App\Models\User;

/**
 * Quién puede editar el programa y las partituras (texto del toque, editor de ritmos,
 * referencias). Por defecto hace falta el permiso partituras.edit (profesores,
 * coordinación y dirección) o partituras.admin. Con PROGRAMA_EDICION_PUBLICA=true
 * vuelve el comportamiento histórico: cualquier cuenta edita.
 */
final class EdicionPrograma
{
    public static function abierta(): bool
    {
        return (bool) config('chilinga.edicion_publica_programa');
    }

    public static function puedeEditar(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if (self::abierta()) {
            return true;
        }

        return $user->acceso()->puedeAlguno(['partituras.edit', 'partituras.admin']);
    }

    /** Nombre que queda como autor de la edición: el de la cuenta, no uno escrito a mano. */
    public static function autor(User $user): string
    {
        $nombre = trim((string) ($user->persona->nombre_completo ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($user->name ?: $user->username));
        }

        return mb_substr($nombre, 0, 80);
    }
}
