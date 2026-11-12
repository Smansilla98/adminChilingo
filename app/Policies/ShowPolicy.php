<?php

namespace App\Policies;

use App\Models\Show;
use App\Models\User;

class ShowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('shows.view');
    }

    public function view(User $user, Show $show): bool
    {
        return $user->acceso()->puede('shows.view');
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('shows.manage');
    }

    /** Solo si todos los bloques convocados están en su alcance (o tiene alcance global). */
    public function update(User $user, Show $show): bool
    {
        $alcance = $user->acceso()->alcance('shows.manage');
        if ($alcance->esGlobal()) {
            return true;
        }
        $bloques = $show->bloques()->get(['bloques.id', 'bloques.sede_id']);
        if ($bloques->isEmpty()) {
            return false;
        }

        return $bloques->every(fn ($b) => $alcance->incluyeBloque((int) $b->id, $b->sede_id ? (int) $b->sede_id : null));
    }

    public function delete(User $user, Show $show): bool
    {
        return $this->update($user, $show);
    }

    /**
     * Tomar asistencia al show y a sus ensayos: quien lo gestiona o un docente
     * (asistencias.create) de alguno de los bloques convocados.
     */
    public function asistencia(User $user, Show $show): bool
    {
        if ($this->update($user, $show)) {
            return true;
        }
        $acceso = $user->acceso();
        foreach ($show->bloques()->get(['bloques.id', 'bloques.sede_id']) as $b) {
            if ($acceso->puedeEnBloque('asistencias.create', (int) $b->id, $b->sede_id ? (int) $b->sede_id : null)) {
                return true;
            }
        }

        return false;
    }
}
