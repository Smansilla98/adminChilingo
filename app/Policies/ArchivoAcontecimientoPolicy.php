<?php

namespace App\Policies;

use App\Models\ArchivoAcontecimiento;
use App\Models\User;

/** Acontecimientos del archivo: `archivo.manage` / `archivo.delete` con alcance de sede. */
class ArchivoAcontecimientoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puedeAlguno(['archivo.view', 'archivo.manage', 'archivo.moderate']);
    }

    public function view(?User $user, ArchivoAcontecimiento $a): bool
    {
        return $a->publicado || ($user && $this->viewAny($user));
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('archivo.manage');
    }

    /** Antes de guardar: con alcance de sede, el acontecimiento tiene que ser de una sede propia. */
    public function createEnSede(User $user, ?int $sedeId): bool
    {
        $alcance = $user->acceso()->alcance('archivo.manage');

        return $alcance->esGlobal() || ($sedeId && $alcance->incluyeSede($sedeId));
    }

    public function update(User $user, ArchivoAcontecimiento $a): bool
    {
        return $this->alcanza($user, 'archivo.manage', $a);
    }

    public function delete(User $user, ArchivoAcontecimiento $a): bool
    {
        return $this->alcanza($user, 'archivo.delete', $a);
    }

    private function alcanza(User $user, string $permiso, ArchivoAcontecimiento $a): bool
    {
        $alcance = $user->acceso()->alcance($permiso);

        return $alcance->esGlobal() || ($a->sede_id && $alcance->incluyeSede((int) $a->sede_id));
    }
}
