<?php

namespace App\Policies;

use App\Models\Cuota;
use App\Models\User;

class CuotaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('cuotas.view');
    }

    public function view(User $user, Cuota $cuota): bool
    {
        // Las cuotas generales se consultan desde cualquier alcance (son el valor de referencia).
        if ($cuota->alcanceNormalizado() === Cuota::ALCANCE_GENERAL) {
            return $user->acceso()->puede('cuotas.view');
        }

        return $this->alcanza($user, 'cuotas.view', $cuota);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('cuotas.create');
    }

    public function update(User $user, Cuota $cuota): bool
    {
        return $this->alcanza($user, 'cuotas.update', $cuota);
    }

    public function delete(User $user, Cuota $cuota): bool
    {
        return $this->alcanza($user, 'cuotas.delete', $cuota);
    }

    /** ¿Puede crear/editar una cuota con este alcance? */
    public static function puedeDefinir(User $user, string $permiso, string $alcance, ?int $sedeId, ?int $bloqueId): bool
    {
        $acceso = $user->acceso();

        return match ($alcance) {
            Cuota::ALCANCE_BLOQUE => $bloqueId && $acceso->puedeEnBloque($permiso, $bloqueId),
            Cuota::ALCANCE_SEDE => $sedeId && $acceso->puedeEnSede($permiso, $sedeId),
            default => $acceso->puedeGlobal($permiso),
        };
    }

    private function alcanza(User $user, string $permiso, Cuota $cuota): bool
    {
        return self::puedeDefinir(
            $user,
            $permiso,
            $cuota->alcanceNormalizado(),
            $cuota->sede_id ? (int) $cuota->sede_id : null,
            $cuota->bloque_id ? (int) $cuota->bloque_id : null,
        );
    }
}
