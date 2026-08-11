<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfesorOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            abort(403);
        }

        if ($u->isAdmin() || $u->isProfesor() || $u->isCoordinadorSede() || $u->isCoordinadorArea()) {
            return $next($request);
        }

        abort(403);
    }
}
