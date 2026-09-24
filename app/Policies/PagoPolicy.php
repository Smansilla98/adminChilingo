<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;

/**
 * Un pago alcanza a los alumnos de sus detalles. Para operar sobre él, el permiso
 * debe cubrir a todos esos alumnos (o ser global).
 */
class PagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('pagos.view');
    }

    public function view(User $user, Pago $pago): bool
    {
        if ($this->esPagoPropio($user, $pago)) {
            return true;
        }

        return $this->alcanza($user, 'pagos.view', $pago, false);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('pagos.create');
    }

    public function update(User $user, Pago $pago): bool
    {
        return ! $pago->estaAnulado() && $this->alcanza($user, 'pagos.update', $pago, true);
    }

    public function reverse(User $user, Pago $pago): bool
    {
        return ! $pago->estaAnulado() && $this->alcanza($user, 'pagos.reverse', $pago, true);
    }

    private function alcanza(User $user, string $permiso, Pago $pago, bool $todos): bool
    {
        $acceso = $user->acceso();
        if ($acceso->puedeGlobal($permiso)) {
            return true;
        }
        if (! $acceso->puede($permiso)) {
            return false;
        }
        $alumnos = $pago->detalles()->with(['alumno.bloques:id,sede_id', 'alumno.bloque:id,sede_id'])->get()->pluck('alumno')->filter();
        if ($alumnos->isEmpty()) {
            return false;
        }
        $check = fn ($alumno) => $acceso->puedeSobreAlumno($permiso, $alumno);

        return $todos ? $alumnos->every($check) : $alumnos->contains($check);
    }

    private function esPagoPropio(User $user, Pago $pago): bool
    {
        if (! $user->persona_id) {
            return false;
        }

        return $pago->detalles()->whereHas('alumno', fn ($q) => $q->where('persona_id', $user->persona_id))->exists();
    }
}
