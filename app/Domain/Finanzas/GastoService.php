<?php

namespace App\Domain\Finanzas;

use App\Models\Gasto;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Gastos (web y API). Quien no puede aprobar gastos en ese ámbito los deja
 * pendientes de aprobación. Gastos sin sede son de toda la escuela (alcance global).
 */
class GastoService
{
    public const ESTADOS = [
        'pendiente' => 'Pendiente de aprobación',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
    ];

    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'sede_id' => 'nullable|exists:sedes,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'fecha' => 'required|date',
            'tipo' => 'required|string|in:'.implode(',', array_keys(Gasto::TIPOS)),
            'subtipo' => 'nullable|string|max:40',
            'descripcion' => 'nullable|string|max:255',
            'monto' => 'required|numeric|min:0',
            'proveedor' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos, User $por): Gasto
    {
        $this->asegurarAlcance($por, 'gastos.create', $datos['sede_id'] ?? null);
        $puedeAprobar = $this->puedeEnAlcance($por, 'gastos.approve', $datos['sede_id'] ?? null);
        $gasto = new Gasto($datos + ['created_by' => $por->id]);
        $gasto->forceFill([
            'estado' => $puedeAprobar ? 'aprobado' : 'pendiente',
            'aprobado_por' => $puedeAprobar ? $por->id : null,
            'aprobado_at' => $puedeAprobar ? now() : null,
        ])->save();

        return $gasto;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Gasto $gasto, array $datos, User $por): Gasto
    {
        $this->asegurarAlcance($por, 'gastos.update', $datos['sede_id'] ?? null);
        $gasto->update($datos);

        return $gasto;
    }

    /** Aprobar o rechazar (queda quién y cuándo). */
    public function decidir(Gasto $gasto, string $decision, User $por): Gasto
    {
        if (! in_array($decision, ['aprobado', 'rechazado'], true)) {
            throw ValidationException::withMessages(['decision' => 'Decisión inválida.']);
        }
        $gasto->forceFill(['estado' => $decision, 'aprobado_por' => $por->id, 'aprobado_at' => now()])->save();

        return $gasto;
    }

    public function puedeEnAlcance(User $user, string $permiso, $sedeId): bool
    {
        $acceso = $user->acceso();

        return $sedeId ? $acceso->puedeEnSede($permiso, (int) $sedeId) : $acceso->puedeGlobal($permiso);
    }

    private function asegurarAlcance(User $user, string $permiso, $sedeId): void
    {
        if (! $this->puedeEnAlcance($user, $permiso, $sedeId)) {
            abort(403, 'No podés registrar gastos en esa sede.');
        }
    }
}
