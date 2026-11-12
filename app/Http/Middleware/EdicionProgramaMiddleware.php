<?php

namespace App\Http\Middleware;

use App\Domain\Acceso\EdicionPrograma;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas que modifican el programa o las partituras. Pide cuenta con permiso de edición
 * (ver EdicionPrograma) y deja como autor a la persona que inició sesión: el campo
 * editor_nombre que llega del formulario se reemplaza por el nombre de la cuenta.
 */
class EdicionProgramaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Entrá con tu cuenta para editar el programa.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if (! EdicionPrograma::puedeEditar($user)) {
            abort(403, 'Para editar el programa hace falta permiso de edición de partituras.');
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('DELETE')) {
            $request->merge(['editor_nombre' => EdicionPrograma::autor($user)]);
        }

        return $next($request);
    }
}
