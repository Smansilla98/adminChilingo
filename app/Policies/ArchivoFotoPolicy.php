<?php

namespace App\Policies;

use App\Models\ArchivoFoto;
use App\Models\User;

/**
 * Fotos del archivo histórico.
 *
 * - Lo publicado lo ve cualquiera, sin cuenta.
 * - Cualquier cuenta activa aporta; quien aportó corrige lo suyo mientras no esté
 *   publicado y nunca toca lo de otros.
 * - El equipo opera con `archivo.*` según su alcance (global o la sede de la foto).
 */
class ArchivoFotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puedeAlguno(['archivo.view', 'archivo.manage', 'archivo.moderate']);
    }

    public function view(?User $user, ArchivoFoto $foto): bool
    {
        if ($foto->esPublica()) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $this->esAportante($user, $foto)
            || $this->alcanza($user, 'archivo.view', $foto)
            || $this->alcanza($user, 'archivo.manage', $foto)
            || $this->alcanza($user, 'archivo.moderate', $foto);
    }

    /** Aportar material: cualquier cuenta activa. */
    public function aportar(User $user): bool
    {
        return (bool) ($user->activo ?? true);
    }

    /** Carga del equipo (queda como material propio del archivo). */
    public function create(User $user): bool
    {
        return $user->acceso()->puede('archivo.manage');
    }

    public function update(User $user, ArchivoFoto $foto): bool
    {
        return $this->editarComoEquipo($user, $foto)
            || ($this->esAportante($user, $foto) && in_array($foto->estado, ArchivoFoto::EDITABLES_POR_APORTANTE, true));
    }

    public function editarComoEquipo(User $user, ArchivoFoto $foto): bool
    {
        return $this->alcanza($user, 'archivo.manage', $foto);
    }

    public function delete(User $user, ArchivoFoto $foto): bool
    {
        return $this->alcanza($user, 'archivo.delete', $foto)
            || ($this->esAportante($user, $foto) && in_array($foto->estado, ['borrador', 'pendiente', 'cambios', 'rechazada'], true));
    }

    public function enviar(User $user, ArchivoFoto $foto): bool
    {
        return $this->esAportante($user, $foto) && in_array($foto->estado, ['borrador', 'cambios'], true);
    }

    public function moderate(User $user, ArchivoFoto $foto): bool
    {
        return $this->alcanza($user, 'archivo.moderate', $foto);
    }

    public function publish(User $user, ArchivoFoto $foto): bool
    {
        return $this->alcanza($user, 'archivo.publish', $foto);
    }

    public function descargarOriginal(User $user, ArchivoFoto $foto): bool
    {
        return $this->esAportante($user, $foto)
            || $this->alcanza($user, 'archivo.view', $foto)
            || $this->alcanza($user, 'archivo.manage', $foto);
    }

    private function esAportante(User $user, ArchivoFoto $foto): bool
    {
        return $foto->aportada_por !== null && (int) $foto->aportada_por === (int) $user->id;
    }

    private function alcanza(User $user, string $permiso, ArchivoFoto $foto): bool
    {
        $alcance = $user->acceso()->alcance($permiso);
        if ($alcance->esGlobal()) {
            return true;
        }
        if ($foto->sede_id) {
            return $alcance->incluyeSede((int) $foto->sede_id);
        }

        // Sin sede: el equipo con alcance de sede opera sobre lo que cargó.
        return ! $alcance->estaVacio() && $this->esAportante($user, $foto);
    }
}
