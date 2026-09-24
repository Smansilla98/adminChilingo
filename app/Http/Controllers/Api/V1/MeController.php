<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\PresentadorAcceso;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identidad, funciones y capacidades del usuario. La app arma su navegación con esto.
 */
class MeController extends Controller
{
    public function __invoke(Request $request, PresentadorAcceso $presentador): JsonResponse
    {
        $user = $request->user()->loadMissing('persona');
        $acceso = $user->acceso();
        $contextos = $presentador->contextos($acceso);
        $contextoPedido = (string) $request->header('X-Contexto', '');

        $modulos = [];
        foreach ($acceso->modulos() as $clave) {
            $def = config('permisos.modulos.'.$clave, []);
            $modulos[] = ['clave' => $clave, 'etiqueta' => $def['etiqueta'] ?? $clave, 'icono' => $def['icono'] ?? null];
        }

        return response()->json([
            'usuario' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'nombre' => $user->persona?->nombre_completo ?? $user->name,
            ],
            'persona' => $user->persona ? [
                'id' => $user->persona->id,
                'nombre' => $user->persona->nombre,
                'apellido' => $user->persona->apellido,
                'telefono' => $user->persona->telefono,
            ] : null,
            'funciones' => $presentador->funciones($acceso),
            'contextos' => $contextos,
            'contexto_actual' => in_array($contextoPedido, array_column($contextos, 'clave'), true) ? $contextoPedido : null,
            'permisos' => $acceso->permisos(),
            'alcances' => $acceso->toArray()['permisos'],
            'modulos' => $modulos,
            'superadmin' => $acceso->esSuperadmin(),
            'catalogo_version' => md5(json_encode(CatalogoPermisos::todos())),
        ]);
    }
}
