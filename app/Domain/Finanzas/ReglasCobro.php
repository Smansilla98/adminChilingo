<?php

namespace App\Domain\Finanzas;

use App\Models\Ajuste;

/**
 * Reglas de cobro configurables en Ajustes (Cuotas → Reglas de cobro):
 * - recargo por mora: porcentaje o monto fijo a partir de X días de vencida la cuota;
 * - descuento por grupo familiar: porcentaje a partir del segundo integrante activo;
 * - descuento por pago adelantado: porcentaje si se paga hasta X días antes del vencimiento.
 * Todo arranca apagado.
 *
 * @phpstan-type Reglas array{
 *   recargo: array{activo: bool, tipo: string, valor: float, dias: int},
 *   familiar: array{activo: bool, porcentaje: float},
 *   adelantado: array{activo: bool, porcentaje: float, dias: int}
 * }
 */
class ReglasCobro
{
    public const CLAVE = 'reglas_cobro';

    public const TIPOS_RECARGO = ['porcentaje' => 'Porcentaje de la cuota', 'fijo' => 'Monto fijo'];

    /** @return Reglas */
    public static function actuales(): array
    {
        return self::normalizar(Ajuste::leer(self::CLAVE));
    }

    /** @return array<string, mixed> */
    public static function reglasValidacion(): array
    {
        return [
            'recargo.activo' => 'boolean',
            'recargo.tipo' => 'required_if:recargo.activo,1,true|in:porcentaje,fijo',
            'recargo.valor' => 'required_if:recargo.activo,1,true|numeric|min:0|max:10000000',
            'recargo.dias' => 'nullable|integer|min:0|max:365',
            'familiar.activo' => 'boolean',
            'familiar.porcentaje' => 'required_if:familiar.activo,1,true|numeric|min:0|max:100',
            'adelantado.activo' => 'boolean',
            'adelantado.porcentaje' => 'required_if:adelantado.activo,1,true|numeric|min:0|max:100',
            'adelantado.dias' => 'nullable|integer|min:0|max:120',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return Reglas
     */
    public static function guardar(array $datos, ?int $por): array
    {
        $reglas = self::normalizar($datos);
        Ajuste::guardar(self::CLAVE, $reglas, $por);

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return Reglas
     */
    public static function normalizar(array $d): array
    {
        $r = (array) ($d['recargo'] ?? []);
        $f = (array) ($d['familiar'] ?? []);
        $a = (array) ($d['adelantado'] ?? []);

        return [
            'recargo' => [
                'activo' => filter_var($r['activo'] ?? false, FILTER_VALIDATE_BOOL),
                'tipo' => ($r['tipo'] ?? 'porcentaje') === 'fijo' ? 'fijo' : 'porcentaje',
                'valor' => max(0.0, round((float) ($r['valor'] ?? 0), 2)),
                'dias' => max(0, (int) ($r['dias'] ?? 0)),
            ],
            'familiar' => [
                'activo' => filter_var($f['activo'] ?? false, FILTER_VALIDATE_BOOL),
                'porcentaje' => min(100.0, max(0.0, round((float) ($f['porcentaje'] ?? 0), 2))),
            ],
            'adelantado' => [
                'activo' => filter_var($a['activo'] ?? false, FILTER_VALIDATE_BOOL),
                'porcentaje' => min(100.0, max(0.0, round((float) ($a['porcentaje'] ?? 0), 2))),
                'dias' => max(0, (int) ($a['dias'] ?? 0)),
            ],
        ];
    }
}
