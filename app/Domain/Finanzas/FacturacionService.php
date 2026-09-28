<?php

namespace App\Domain\Finanzas;

use App\Models\FacturacionMensual;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Facturación mensual (web y API): una carga por sede (o de toda la escuela) y
 * período. Cargar o editar requiere `facturacion.manage` en esa sede (o global).
 */
class FacturacionService
{
    /** @return array<string, mixed> */
    public function reglas(bool $edicion = false): array
    {
        $reglas = [
            'cantidad_alumnos' => 'required|integer|min:0',
            'monto_facturado' => 'required|numeric|min:0',
            'monto_previsto' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string|max:500',
        ];
        if (! $edicion) {
            $reglas += [
                'sede_id' => 'nullable|exists:sedes,id',
                'año' => 'required|integer|min:2020|max:2030',
                'mes' => 'required|integer|min:1|max:12',
            ];
        }

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos, User $por): FacturacionMensual
    {
        $this->asegurarAlcance($por, $datos['sede_id'] ?? null);
        $existe = FacturacionMensual::query()->where('año', $datos['año'])->where('mes', $datos['mes'])
            ->when(! empty($datos['sede_id']), fn ($q) => $q->where('sede_id', $datos['sede_id']), fn ($q) => $q->whereNull('sede_id'))
            ->exists();
        if ($existe) {
            throw ValidationException::withMessages(['mes' => 'Ya existe facturación para esa sede, año y mes.']);
        }

        return FacturacionMensual::create($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(FacturacionMensual $f, array $datos, User $por): FacturacionMensual
    {
        $this->asegurarAlcance($por, $f->sede_id);
        $f->update($datos);

        return $f;
    }

    public function puedeGestionar(User $user, ?int $sedeId): bool
    {
        $acceso = $user->acceso();

        return $sedeId ? $acceso->puedeEnSede('facturacion.manage', $sedeId) : $acceso->puedeGlobal('facturacion.manage');
    }

    private function asegurarAlcance(User $user, $sedeId): void
    {
        if (! $this->puedeGestionar($user, $sedeId ? (int) $sedeId : null)) {
            abort(403, 'No podés cargar facturación de esa sede.');
        }
    }
}
