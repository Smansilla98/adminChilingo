<?php

namespace App\Domain\Academico;

use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\CicloLectivo;
use App\Models\Cuota;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Genera las cuotas mensuales de un ciclo (marzo N a febrero N+1) para cada bloque activo.
 *
 * Solo crea las que faltan: una cuota que ya existe para ese bloque y mes no se toca (ni su monto,
 * ni su estado, ni sus pagos). Por eso se puede correr de nuevo sin duplicar nada.
 */
final class CuotasDelCicloService
{
    /**
     * @param  array<int, float|int|string>  $montoPorBloque  bloque_id => monto (si no está, usa el base)
     * @return array{creadas: int, existentes: int}
     */
    public function generar(CicloLectivo $ciclo, float $montoBase, array $montoPorBloque, User $por): array
    {
        if (! $por->acceso()->puedeGlobal('cuotas.create')) {
            throw new AuthorizationException('Para generar las cuotas de todo el ciclo hace falta permiso de cuotas en toda la escuela.');
        }

        return DB::transaction(function () use ($ciclo, $montoBase, $montoPorBloque, $por) {
            $creadas = 0;
            $existentes = 0;

            Bloque::query()->where('activo', true)->orderBy('id')->each(function (Bloque $bloque) use ($ciclo, $montoBase, $montoPorBloque, &$creadas, &$existentes) {
                foreach (CicloLectivo::periodoCuotas((int) $ciclo->anio) as ['anio' => $anio, 'mes' => $mes]) {
                    $existe = Cuota::query()
                        ->where('alcance', Cuota::ALCANCE_BLOQUE)
                        ->where('bloque_id', $bloque->id)
                        ->where('año', $anio)
                        ->where('mes', $mes)
                        ->exists();
                    if ($existe) {
                        $existentes++;

                        continue;
                    }
                    Cuota::query()->create([
                        'alcance' => Cuota::ALCANCE_BLOQUE,
                        'bloque_id' => $bloque->id,
                        'sede_id' => null,
                        'nombre' => 'Cuota '.sprintf('%02d/%d', $mes, $anio),
                        'año' => $anio,
                        'mes' => $mes,
                        'monto' => (float) ($montoPorBloque[$bloque->id] ?? $montoBase),
                        'activo' => true,
                    ]);
                    $creadas++;
                }
            });

            Auditoria::registrar('ciclo.cuotas', $ciclo, null, ['creadas' => $creadas, 'existentes' => $existentes, 'por' => $por->id]);

            return ['creadas' => $creadas, 'existentes' => $existentes];
        });
    }
}
