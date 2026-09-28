<?php

namespace App\Domain\Finanzas;

use App\Models\Cuota;
use App\Models\User;
use App\Policies\CuotaPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Definición de cuotas (web y API). Una cuota aplica a un bloque, a una sede o a toda
 * la escuela, y opcionalmente a una lista de alumnos. No puede haber dos cuotas del
 * mismo alcance para el mismo mes.
 */
class CuotaService
{
    public const ALCANCES = [
        Cuota::ALCANCE_GENERAL => 'Toda la escuela',
        Cuota::ALCANCE_SEDE => 'Una sede',
        Cuota::ALCANCE_BLOQUE => 'Un bloque',
    ];

    public function tieneAlcance(): bool
    {
        return Schema::hasColumn('cuotas', 'alcance');
    }

    /** @return array<string, mixed> */
    public function reglas(?string $alcance): array
    {
        $rules = [
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'fecha_vencimiento' => 'nullable|date',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'alumno_ids' => 'nullable|array',
            'alumno_ids.*' => 'exists:alumnos,id',
        ];
        if ($this->tieneAlcance()) {
            $rules['mes'] = 'required|integer|min:1|max:12';
            $rules['alcance'] = 'required|in:bloque,sede,general';
            $rules['bloque_id'] = [Rule::requiredIf(fn () => $alcance === Cuota::ALCANCE_BLOQUE), 'nullable', 'exists:bloques,id'];
            $rules['sede_id'] = [Rule::requiredIf(fn () => $alcance === Cuota::ALCANCE_SEDE), 'nullable', 'exists:sedes,id'];
        } else {
            $rules['bloque_id'] = 'required|exists:bloques,id';
            $rules['mes'] = 'nullable|integer|min:1|max:12';
        }

        return $rules;
    }

    /**
     * Crea o actualiza la cuota y su lista de alumnos.
     *
     * @param  array<string, mixed>  $datos  validados con reglas(), con `activo` resuelto
     */
    public function guardar(?Cuota $cuota, array $datos, User $por): Cuota
    {
        $alumnoIds = array_values(array_filter((array) ($datos['alumno_ids'] ?? [])));
        unset($datos['alumno_ids']);

        if ($this->tieneAlcance()) {
            $alcance = $datos['alcance'];
            if ($alcance === Cuota::ALCANCE_GENERAL) {
                $datos['bloque_id'] = null;
                $datos['sede_id'] = null;
            } elseif ($alcance === Cuota::ALCANCE_SEDE) {
                $datos['bloque_id'] = null;
            } else {
                $datos['sede_id'] = null;
            }

            $permiso = $cuota ? 'cuotas.update' : 'cuotas.create';
            if (! CuotaPolicy::puedeDefinir($por, $permiso, $alcance, isset($datos['sede_id']) ? (int) $datos['sede_id'] : null, isset($datos['bloque_id']) ? (int) $datos['bloque_id'] : null)) {
                abort(403, 'No podés definir cuotas con ese alcance.');
            }
            $this->asegurarUnicaEnPeriodo((int) $datos['año'], (int) ($datos['mes'] ?? 0), $alcance, $datos['bloque_id'] ?? null, $datos['sede_id'] ?? null, $cuota?->id);
        }

        return DB::transaction(function () use ($cuota, $datos, $alumnoIds) {
            if ($cuota) {
                $cuota->update($datos);
            } else {
                $cuota = Cuota::create($datos);
            }
            $cuota->alumnos()->sync($alumnoIds);

            return $cuota;
        });
    }

    private function asegurarUnicaEnPeriodo(int $año, int $mes, string $alcance, ?int $bloqueId, ?int $sedeId, ?int $exceptoId): void
    {
        if ($mes < 1) {
            return;
        }
        $q = Cuota::query()->where('año', $año)->where('mes', $mes)->where('alcance', $alcance);
        if ($exceptoId) {
            $q->where('id', '!=', $exceptoId);
        }
        if ($alcance === Cuota::ALCANCE_BLOQUE) {
            $q->where('bloque_id', $bloqueId);
        } elseif ($alcance === Cuota::ALCANCE_SEDE) {
            $q->where('sede_id', $sedeId);
        } else {
            $q->whereNull('bloque_id')->whereNull('sede_id');
        }
        if ($q->exists()) {
            throw ValidationException::withMessages(['mes' => 'Ya existe una cuota de este tipo para ese mes y año.']);
        }
    }
}
