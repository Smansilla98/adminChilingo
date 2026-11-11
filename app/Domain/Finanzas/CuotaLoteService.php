<?php

namespace App\Domain\Finanzas;

use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Sede;
use App\Models\User;
use App\Policies\CuotaPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generación de cuotas por lote: un rango de meses de un año, para varios bloques, sedes o
 * toda la escuela. Primero una vista previa (qué se crea y qué ya existe); después se genera
 * solo lo que se puede crear. Nunca duplica: un período ya cargado se omite.
 */
class CuotaLoteService
{
    public const MAX_CUOTAS = 500;

    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'mes_desde' => 'required|integer|min:1|max:12',
            'mes_hasta' => 'required|integer|min:1|max:12|gte:mes_desde',
            'monto' => 'required|numeric|min:0|max:100000000',
            'alcance' => 'required|in:bloque,sede,general',
            'bloque_ids' => 'nullable|array|required_if:alcance,bloque',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'sede_ids' => 'nullable|array|required_if:alcance,sede',
            'sede_ids.*' => 'integer|exists:sedes,id',
            'dia_vencimiento' => 'nullable|integer|min:1|max:31',
            'descripcion' => 'nullable|string|max:500',
        ];
    }

    /**
     * Vista previa: qué cuotas se crearían y cuáles ya existen o no se pueden definir.
     *
     * @param  array<string, mixed>  $datos  validados con reglas()
     * @return array{total: int, crear: int, existen: int, sin_permiso: int, items: list<array<string, mixed>>}
     */
    public function vistaPrevia(array $datos, User $por): array
    {
        $items = [];
        foreach ($this->destinos($datos) as $destino) {
            foreach ($this->meses($datos) as $mes) {
                $estado = 'crear';
                if (! CuotaPolicy::puedeDefinir($por, 'cuotas.create', $datos['alcance'], $destino['sede_id'], $destino['bloque_id'])) {
                    $estado = 'sin_permiso';
                } elseif ($this->existe($datos['año'], $mes, $datos['alcance'], $destino['sede_id'], $destino['bloque_id'])) {
                    $estado = 'existe';
                }
                $items[] = [
                    'nombre' => $datos['nombre'],
                    'periodo' => sprintf('%02d/%d', $mes, $datos['año']),
                    'mes' => $mes,
                    'destino' => $destino['etiqueta'],
                    'bloque_id' => $destino['bloque_id'],
                    'sede_id' => $destino['sede_id'],
                    'vencimiento' => $this->vencimiento($datos, $mes),
                    'monto' => (float) $datos['monto'],
                    'estado' => $estado,
                ];
            }
        }
        if (count($items) > self::MAX_CUOTAS) {
            throw ValidationException::withMessages(['mes_hasta' => 'Son demasiadas cuotas de una vez ('.count($items).'). El máximo es '.self::MAX_CUOTAS.'; achicá el rango o los destinos.']);
        }

        return [
            'total' => count($items),
            'crear' => collect($items)->where('estado', 'crear')->count(),
            'existen' => collect($items)->where('estado', 'existe')->count(),
            'sin_permiso' => collect($items)->where('estado', 'sin_permiso')->count(),
            'items' => $items,
        ];
    }

    /**
     * Crea las cuotas de la vista previa que están en estado "crear". Todo en una transacción:
     * si algo falla, no queda un lote a medias.
     *
     * @param  array<string, mixed>  $datos
     * @return array{creadas: int, omitidas: int}
     */
    public function generar(array $datos, User $por, CuotaService $cuotas): array
    {
        $previa = $this->vistaPrevia($datos, $por);
        $creadas = 0;
        DB::transaction(function () use ($previa, $datos, $por, $cuotas, &$creadas) {
            foreach ($previa['items'] as $item) {
                if ($item['estado'] !== 'crear') {
                    continue;
                }
                $cuotas->guardar(null, [
                    'nombre' => $item['nombre'],
                    'año' => (int) $datos['año'],
                    'mes' => $item['mes'],
                    'monto' => $item['monto'],
                    'fecha_vencimiento' => $item['vencimiento'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'alcance' => $datos['alcance'],
                    'bloque_id' => $item['bloque_id'],
                    'sede_id' => $item['sede_id'],
                    'activo' => true,
                    'alumno_ids' => [],
                ], $por);
                $creadas++;
            }
        });

        return ['creadas' => $creadas, 'omitidas' => $previa['total'] - $creadas];
    }

    /** @return list<int> */
    private function meses(array $datos): array
    {
        return range((int) $datos['mes_desde'], (int) $datos['mes_hasta']);
    }

    /**
     * Destinos del lote: cada bloque, cada sede o la escuela entera.
     *
     * @return list<array{bloque_id: ?int, sede_id: ?int, etiqueta: string}>
     */
    private function destinos(array $datos): array
    {
        return match ($datos['alcance']) {
            Cuota::ALCANCE_BLOQUE => Bloque::query()->whereIn('id', $datos['bloque_ids'] ?? [])->with('sede')->orderBy('nombre')->get()
                ->map(fn (Bloque $b) => ['bloque_id' => (int) $b->id, 'sede_id' => null, 'etiqueta' => $b->nombre.' ('.($b->sede->nombre ?? '—').')'])->values()->all(),
            Cuota::ALCANCE_SEDE => Sede::query()->whereIn('id', $datos['sede_ids'] ?? [])->orderBy('nombre')->get()
                ->map(fn (Sede $s) => ['bloque_id' => null, 'sede_id' => (int) $s->id, 'etiqueta' => 'Sede '.$s->nombre])->values()->all(),
            default => [['bloque_id' => null, 'sede_id' => null, 'etiqueta' => 'Toda la escuela']],
        };
    }

    private function existe(int $anio, int $mes, string $alcance, ?int $sedeId, ?int $bloqueId): bool
    {
        $q = Cuota::query()->where('año', $anio)->where('mes', $mes)->where('alcance', $alcance);
        if ($alcance === Cuota::ALCANCE_BLOQUE) {
            $q->where('bloque_id', $bloqueId);
        } elseif ($alcance === Cuota::ALCANCE_SEDE) {
            $q->where('sede_id', $sedeId);
        } else {
            $q->whereNull('bloque_id')->whereNull('sede_id');
        }

        return $q->exists();
    }

    private function vencimiento(array $datos, int $mes): ?string
    {
        if (empty($datos['dia_vencimiento'])) {
            return null;
        }
        $fecha = Carbon::create((int) $datos['año'], $mes, 1);

        return $fecha->copy()->day(min((int) $datos['dia_vencimiento'], $fecha->daysInMonth))->toDateString();
    }
}
