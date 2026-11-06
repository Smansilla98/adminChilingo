<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien entró con una contraseña provisoria tiene que elegir la suya antes de usar el sistema.
 * Web: lo manda a «Mi contraseña». API: responde 403 DEBE_CAMBIAR_CLAVE salvo en lo mínimo
 * para cambiarla (la app lo sabe por /me y muestra la pantalla).
 */
class EnsureClaveCambiada
{
    private const WEB_PERMITIDAS = ['mi-clave.edit', 'mi-clave.update', 'logout'];

    private const API_PERMITIDAS = ['api.v1.me', 'api.v1.auth.clave', 'api.v1.auth.logout', 'api.v1.auth.refresh', 'api.v1.dispositivos.store', 'api.v1.dispositivos.destroy'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->debe_cambiar_clave) {
            return $next($request);
        }
        $ruta = (string) $request->route()?->getName();

        if ($request->is('api/*')) {
            return in_array($ruta, self::API_PERMITIDAS, true)
                ? $next($request)
                : response()->json(['message' => 'Antes de seguir, elegí tu contraseña.', 'codigo' => 'DEBE_CAMBIAR_CLAVE'], 403);
        }

        return in_array($ruta, self::WEB_PERMITIDAS, true)
            ? $next($request)
            : redirect()->route('mi-clave.edit');
    }
}
