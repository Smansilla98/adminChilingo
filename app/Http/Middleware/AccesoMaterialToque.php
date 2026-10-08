<?php

namespace App\Http\Middleware;

use App\Models\ProgramaRitmo;
use App\Support\AccesoMaterial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El material de un toque pide cuenta; un alumno ve hasta el año que cursa.
 * Sin cuenta, lleva a entrar y después vuelve al toque.
 */
class AccesoMaterialToque
{
    public function handle(Request $request, Closure $next): Response
    {
        $toque = $request->route('programaRitmo');
        if (! $toque instanceof ProgramaRitmo) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Entrá con tu cuenta para ver el material de los toques.'], 401);
            }

            return redirect()->guest(route('login'))
                ->with('info', 'Para ver el material de «'.$toque->nombre.'» entrá con tu cuenta.');
        }

        if (! AccesoMaterial::puedeVer($user, $toque)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Este material es de '.(int) $toque->año.'° año.'], 403);
            }

            return response()->view('programa.material-no-disponible', [
                'toque' => $toque,
                'anioAlumno' => AccesoMaterial::anioMaximo($user),
            ], 403);
        }

        return $next($request);
    }
}
