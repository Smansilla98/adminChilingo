<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: `permiso:alumnos.view` o `permiso:pagos.view|cuotas.view` (alcanza con uno).
 * Verifica que el permiso exista en algún ámbito; el alcance de cada registro lo
 * controlan las Policies.
 */
class CheckPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson() ? abort(401) : redirect()->guest(route('login'));
        }

        $lista = collect($permisos)->flatMap(fn (string $p) => explode('|', $p))->map('trim')->filter()->values()->all();
        if (! $user->acceso()->puedeAlguno($lista)) {
            abort(403, 'No tenés permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
