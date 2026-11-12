<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administración › Usuarios › Sesiones en la app: cerrar la sesión de un celular perdido
 * o todas las de la cuenta (también deja de recibir los avisos push). Mismo permiso que
 * editar la cuenta (UserPolicy::update). La sesión propia se cierra desde «Cerrar sesión».
 */
class SesionesUsuarioController extends Controller
{
    /** Cierra una sesión (un token de la app). */
    public function cerrar(Request $request, User $usuario, int $sesion): JsonResponse
    {
        $this->autorizar($request, $usuario);
        $token = $usuario->tokens()->whereKey($sesion)->firstOrFail();
        $token->delete();
        Auditoria::registrar('usuario.sesion_cerrada', $usuario, null, ['sesion' => $token->name, 'por' => $request->user()->id]);

        return response()->json(['ok' => true, 'cerradas' => 1]);
    }

    /** Cierra todas las sesiones de la app y desvincula sus celulares de los avisos push. */
    public function cerrarTodas(Request $request, User $usuario): JsonResponse
    {
        $this->autorizar($request, $usuario);
        $cerradas = $usuario->tokens()->delete();
        $usuario->dispositivos()->delete();
        Auditoria::registrar('usuario.sesiones_cerradas', $usuario, null, ['cerradas' => $cerradas, 'por' => $request->user()->id]);

        return response()->json(['ok' => true, 'cerradas' => $cerradas]);
    }

    private function autorizar(Request $request, User $usuario): void
    {
        $this->authorize('update', $usuario);
        abort_if($request->user()->is($usuario), 422, 'Para cerrar tu propia sesión usá «Cerrar sesión» en la app.');
    }
}
