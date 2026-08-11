<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * @param  string  ...$roles  Uno o más roles (también admite "admin,direccion").
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $needed = collect($roles)
            ->flatMap(fn (string $r) => explode(',', $r))
            ->map(fn (string $r) => trim($r))
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($needed as $role) {
            if ($this->matches($user, $role)) {
                return $next($request);
            }
        }

        abort(403, 'No tenés permisos para acceder a esta sección.');
    }

    private function matches(\App\Models\User $user, string $role): bool
    {
        // admin y dirección son equivalentes para el panel
        if ($role === 'admin' || $role === 'direccion') {
            return $user->isAdmin();
        }

        if ($role === 'profesor') {
            return $user->isProfesor()
                || $user->isCoordinadorSede()
                || $user->isCoordinadorArea();
        }

        return $user->role === $role || $user->hasRole($role);
    }
}
