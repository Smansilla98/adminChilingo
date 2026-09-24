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
            Auditoria::registrar('created', $model, null, $model->datosAuditables($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $cambios = $model->datosAuditables($model->getChanges());
            if ($cambios === []) {
                return;
            }
            $antes = array_intersect_key($model->datosAuditables($model->getOriginal()), $cambios);
            Auditoria::registrar('updated', $model, $antes, $cambios);
        });

        static::deleted(function (Model $model) {
            Auditoria::registrar('deleted', $model, $model->datosAuditables($model->getAttributes()), null);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function datosAuditables(array $datos): array
    {
        $excluir = array_merge($this->getHidden(), ['created_at', 'updated_at', 'remember_token', 'password']);

        return array_diff_key($datos, array_flip($excluir));
    }
}
