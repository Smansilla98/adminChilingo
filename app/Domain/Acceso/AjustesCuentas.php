<?php

namespace App\Domain\Acceso;

use App\Models\Ajuste;

/**
 * Ajustes de cuentas de alumnos que la escuela cambia desde Configuración > Cuentas.
 * Lo que no está guardado toma el valor del .env (o el de acá).
 */
final class AjustesCuentas
{
    public const CLAVE = 'cuentas';

    /** Edad mínima para crear la cuenta solo; los más chicos los da de alta la escuela. */
    public const EDAD_MINIMA_REGISTRO = 13;

    /** Largo mínimo de contraseña elegida por la persona (registro, cambio, recuperación). */
    public const CLAVE_MINIMA = 8;

    /** @return array{registro_requiere_codigo: bool} */
    public static function todos(): array
    {
        $guardado = Ajuste::leer(self::CLAVE);

        return [
            'registro_requiere_codigo' => (bool) ($guardado['registro_requiere_codigo']
                ?? config('chilinga.registro_requiere_codigo', false)),
        ];
    }

    public static function registroRequiereCodigo(): bool
    {
        return self::todos()['registro_requiere_codigo'];
    }

    /** @param  array{registro_requiere_codigo: bool}  $valores */
    public static function guardar(array $valores, ?int $por): void
    {
        Ajuste::guardar(self::CLAVE, array_merge(Ajuste::leer(self::CLAVE), $valores), $por);
    }
}
