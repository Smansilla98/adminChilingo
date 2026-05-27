<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuloAccess
{
    public function handle(Request $request, Closure $next, string $clave): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect()->route('login');
        }

        if (method_exists($u, 'tieneAccesoModulo') && ! $u->tieneAccesoModulo($clave)) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}

