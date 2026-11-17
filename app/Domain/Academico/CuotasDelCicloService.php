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

    /**
     * Vista previa del cambio de monto: por cada cuota ya existente del ciclo (bloque y mes), el monto
     * actual, el nuevo y cuántos pagos tiene. No escribe nada. Las cuotas que no existen no se crean acá
     * (eso es generar()).
     *
     * @param  array<int, float|int|string>  $montoPorBloque  bloque_id => monto (si no está, usa el base)
     * @return array{total: int, cambian: int, iguales: int, con_pagos: int, items: list<array<string, mixed>>}
     */
    public function vistaPreviaMonto(CicloLectivo $ciclo, float $montoBase, array $montoPorBloque): array
    {
        $items = [];
        foreach ($this->existentes($ciclo) as $cuota) {
            $nuevo = (float) ($montoPorBloque[$cuota->bloque_id] ?? $montoBase);
            $pagos = (int) DB::table('pago_detalles')->where('cuota_id', $cuota->id)->count();
            $items[] = [
                'cuota_id' => (int) $cuota->id,
                'bloque_id' => (int) $cuota->bloque_id,
                'bloque' => (string) $cuota->bloque->nombre,
                'periodo' => sprintf('%02d/%d', $cuota->mes, $cuota->año),
                'monto_actual' => (float) $cuota->monto,
                'monto_nuevo' => round($nuevo, 2),
                'cambia' => abs((float) $cuota->monto - $nuevo) > 0.005,
                'pagos' => $pagos,
            ];
        }

        return [
            'total' => count($items),
            'cambian' => collect($items)->where('cambia', true)->count(),
            'iguales' => collect($items)->where('cambia', false)->count(),
            'con_pagos' => collect($items)->where('pagos', '>', 0)->count(),
            'items' => $items,
        ];
    }

    /**
     * Aplica el monto nuevo a las cuotas del ciclo que cambian. Solo toca el monto de la cuota: los
     * pagos ya registrados (sus líneas y montos) no se modifican. Cada cambio queda auditado.
     *
     * @param  array<int, float|int|string>  $montoPorBloque
     * @return array{actualizadas: int, sin_cambio: int}
     */
    public function actualizarMonto(CicloLectivo $ciclo, float $montoBase, array $montoPorBloque, User $por): array
    {
        if (! $por->acceso()->puedeGlobal('cuotas.update')) {
            throw new AuthorizationException('Para cambiar el valor de las cuotas hace falta permiso de cuotas en toda la escuela.');
        }
        $previa = $this->vistaPreviaMonto($ciclo, $montoBase, $montoPorBloque);

        return DB::transaction(function () use ($ciclo, $previa, $por) {
            $actualizadas = 0;
            foreach ($previa['items'] as $item) {
                if (! $item['cambia']) {
                    continue;
                }
                // Cuota::update() deja la auditoría (antes y después del monto).
                Cuota::query()->whereKey($item['cuota_id'])->first()?->update(['monto' => $item['monto_nuevo']]);
                $actualizadas++;
            }
            Auditoria::registrar('ciclo.cuotas_monto', $ciclo, null, ['actualizadas' => $actualizadas, 'sin_cambio' => $previa['total'] - $actualizadas, 'por' => $por->id]);

            return ['actualizadas' => $actualizadas, 'sin_cambio' => $previa['total'] - $actualizadas];
        });
    }

    /** Cuotas de bloque del ciclo (marzo a febrero) que ya existen. */
    private function existentes(CicloLectivo $ciclo): \Illuminate\Support\Collection
    {
        $periodos = CicloLectivo::periodoCuotas((int) $ciclo->anio);

        return Cuota::query()->with('bloque:id,nombre')
            ->where('alcance', Cuota::ALCANCE_BLOQUE)
            ->whereIn('bloque_id', Bloque::query()->where('activo', true)->pluck('id'))
            ->where(function ($q) use ($periodos) {
                foreach ($periodos as ['anio' => $anio, 'mes' => $mes]) {
                    $q->orWhere(fn ($w) => $w->where('año', $anio)->where('mes', $mes));
                }
            })
            ->orderBy('bloque_id')->orderBy('año')->orderBy('mes')
            ->get();
    }
}
