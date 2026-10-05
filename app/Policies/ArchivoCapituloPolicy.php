<?php

namespace App\Policies;

use App\Models\ArchivoCapitulo;
use App\Models\User;

/** Los capítulos ordenan la historia de toda la escuela: solo alcance global. */
class ArchivoCapituloPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puedeAlguno(['archivo.view', 'archivo.manage', 'archivo.moderate']);
    }

    public function view(?User $user, ArchivoCapitulo $capitulo): bool
    {
        return $capitulo->publicado || ($user && $this->viewAny($user));
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puedeGlobal('archivo.manage');
    }

    public function update(User $user, ArchivoCapitulo $capitulo): bool
    {
        return $user->acceso()->puedeGlobal('archivo.manage');
    }

    public function delete(User $user, ArchivoCapitulo $capitulo): bool
    {
        return $user->acceso()->puedeGlobal('archivo.delete');
    }
}
