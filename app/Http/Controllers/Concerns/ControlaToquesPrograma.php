<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ProgramaRitmo;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de acceso a los toques del programa (admin o toque publicado), usadas por varios controladores.
 */
trait ControlaToquesPrograma
{
    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }
    }

    private function abortSiToqueNoPublico(ProgramaRitmo $programaRitmo): void
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }
    }
}
