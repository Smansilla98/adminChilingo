<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra altas, cambios y bajas del modelo en la tabla `auditoria`.
 * Los atributos de `$hidden` (contraseñas, tokens) nunca se guardan.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            Auditoria::registrar('created', $model, null, self::datosAuditables($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $cambios = self::datosAuditables($model, $model->getChanges());
            if ($cambios === []) {
                return;
            }
            $antes = array_intersect_key(self::datosAuditables($model, $model->getOriginal()), $cambios);
            Auditoria::registrar('updated', $model, $antes, $cambios);
        });

        static::deleted(function (Model $model) {
            Auditoria::registrar('deleted', $model, self::datosAuditables($model, $model->getAttributes()), null);
        });
    }

    /**
     * Quita de los datos los atributos ocultos (contraseñas, tokens) y las marcas de tiempo.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function datosAuditables(Model $model, array $datos): array
    {
        $excluir = array_merge($model->getHidden(), ['created_at', 'updated_at', 'remember_token', 'password']);

        return array_diff_key($datos, array_flip($excluir));
    }
}
