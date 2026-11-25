<?php

namespace App\Domain\Acceso;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Límites de uso de los flujos públicos (sin cuenta): búsqueda por DNI del formulario
 * de comprobantes, recuperar la clave y preinscripción. Cada uno con su propia clave,
 * para no sumar con el límite general del grupo de rutas.
 */
final class LimitesAcceso
{
    public static function registrar(): void
    {
        RateLimiter::for('comprobante-publico-dni', function (Request $request) {
            // Por IP (evita barridos) y por DNI consultado (una IP distinta no sirve para seguir preguntando por la misma persona).
            $dni = preg_replace('/\D+/', '', (string) $request->input('dni'));

            return [
                Limit::perMinute(5)->by('cp-dni:'.$request->ip()),
                Limit::perDay(40)->by('cp-dni-dia:'.$request->ip()),
                Limit::perHour(10)->by('cp-dni-persona:'.$dni),
            ];
        });

        RateLimiter::for('recuperar-clave', function (Request $request) {
            $quien = mb_strtolower(trim((string) $request->input('usuario')));

            return [
                Limit::perMinute(5)->by('rec-ip:'.$request->ip()),
                Limit::perHour(10)->by('rec-cuenta:'.$quien),
            ];
        });

        RateLimiter::for('preinscripcion', fn (Request $request) => [
            Limit::perMinute(12)->by('pre:'.$request->ip()),
            Limit::perDay(60)->by('pre-dia:'.$request->ip()),
        ]);
    }
}
