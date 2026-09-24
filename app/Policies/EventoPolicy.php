<?php

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;

/**
 * Un evento pertenece a un bloque, a una sede o a toda la escuela (sin sede ni bloque).
 */
class EventoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('eventos.view');
    }

    public function view(User $user, Evento $evento): bool
    {
        if (! $evento->sede_id && ! $evento->bloque_id) {
            return $user->acceso()->puede('eventos.view');
        }

        return $this->alcanza($user, 'eventos.view', $evento, true);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('eventos.create');
    }

    public function update(User $user, Evento $evento): bool
    {
        return $this->alcanza($user, 'eventos.update', $evento);
    }

    public function delete(User $user, Evento $evento): bool
    {
        return $this->alcanza($user, 'eventos.delete', $evento);
    }

    private function alcanza(User $user, string $permiso, Evento $evento, bool $lectura = false): bool
    {
        $alcance = $user->acceso()->alcance($permiso);
        if ($alcance->esGlobal()) {
            return true;
        }
        if ($evento->bloque_id) {
            return $user->acceso()->puedeEnBloque($permiso, (int) $evento->bloque_id);
        }
        if ($evento->sede_id) {
            // Para ver, alcanza con dar clase en un bloque de la sede.
            return $lectura
                ? in_array((int) $evento->sede_id, $alcance->sedesTocadas(), true)
                : $alcance->incluyeSede((int) $evento->sede_id);
        }

        return false; // evento de toda la escuela: solo alcance global puede editarlo
    }
}
