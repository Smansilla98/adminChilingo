<?php

namespace App\Policies;

use App\Models\Diseno;
use App\Models\User;

/**
 * Ownership de diseños ITO.
 *
 * - Admin / dirección: acceso total (lectura, edición, kit).
 * - Resto con módulo admin.disenos:
 *   - Lectura: propios + plantillas de estudio (user_id null).
 *   - Escritura/borrado: solo los propios.
 * - Kit de marca compartido: solo admin/dirección.
 */
class DisenoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasModulo($user);
    }

    public function view(User $user, Diseno $diseno): bool
    {
        if (! $this->hasModulo($user)) {
            return false;
        }
        if ($user->isAdmin() || $user->isDireccion()) {
            return true;
        }
        // Plantillas / diseños de estudio: lectura para quien tenga el módulo.
        if ($diseno->user_id === null) {
            return true;
        }

        return (int) $diseno->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->hasModulo($user);
    }

    public function update(User $user, Diseno $diseno): bool
    {
        return $this->ownsOrElevated($user, $diseno);
    }

    public function delete(User $user, Diseno $diseno): bool
    {
        return $this->ownsOrElevated($user, $diseno);
    }

    /** Medios personales del canvas (Subidos). */
    public function uploadAsset(User $user): bool
    {
        return $this->hasModulo($user);
    }

    /** Logos/kits compartidos del estudio. */
    public function manageKit(User $user): bool
    {
        return $this->hasModulo($user) && ($user->isAdmin() || $user->isDireccion());
    }

    private function hasModulo(User $user): bool
    {
        return $user->tieneAccesoModulo('admin.disenos');
    }

    /** Escritura: owner o admin/dirección. Huérfanos solo staff elevado. */
    private function ownsOrElevated(User $user, Diseno $diseno): bool
    {
        if (! $this->hasModulo($user)) {
            return false;
        }
        if ($user->isAdmin() || $user->isDireccion()) {
            return true;
        }
        if ($diseno->user_id === null) {
            return false;
        }

        return (int) $diseno->user_id === (int) $user->id;
    }
}
