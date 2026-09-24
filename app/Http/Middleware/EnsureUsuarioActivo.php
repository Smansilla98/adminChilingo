<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta la sesión (o el token) de cuentas desactivadas y registra la última actividad.
 */
class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (isset($user->activo) && ! $user->activo) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => 'Tu cuenta está desactivada. Consultá con administración.'], 403);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('error', 'Tu cuenta está desactivada. Consultá con administración.');
        }

        // Última actividad, como mucho una escritura cada 5 minutos.
        if (array_key_exists('ultimo_acceso_at', $user->getAttributes())
            && (! $user->ultimo_acceso_at || $user->ultimo_acceso_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
