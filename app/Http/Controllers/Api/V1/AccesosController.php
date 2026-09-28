<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\GestionAsignaciones;
use App\Domain\Personas\PersonaService;
use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de roles/permisos y administración de accesos (usuarios.*).
 */
class AccesosController extends Controller
{
    public function catalogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['usuarios.view', 'usuarios.permissions']), 403);

        return response()->json([
            'permisos' => CatalogoPermisos::grupos(),
            'roles' => collect(CatalogoPermisos::roles())->map(fn ($r, $k) => [
                'clave' => $k,
                'nombre' => $r['nombre'],
                'descripcion' => $r['descripcion'] ?? null,
                'ambitos' => $r['ambitos'],
                'derivado' => (bool) ($r['derivado'] ?? false),
                'permisos' => CatalogoPermisos::permisosDeRol($k),
            ])->values(),
        ]);
    }

    public function asignar(Request $request, User $usuario, GestionAsignaciones $asignaciones, PersonaService $personas): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['rol', 'permiso'])],
            'nombre' => ['required', 'string', 'max:80'],
            'ambito' => ['required', Rule::in(['global', 'sede', 'bloque'])],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'exists:bloques,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);
        $persona = $usuario->persona ?? $personas->asegurarParaUsuario($usuario);
        $a = $asignaciones->asignar($persona, $data, $request->user());

        return response()->json(['id' => $a->id], 201);
    }

    public function quitar(Request $request, User $usuario, Asignacion $asignacion, GestionAsignaciones $asignaciones): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);
        abort_unless((int) $asignacion->persona_id === (int) $usuario->persona_id, 404);
        $asignaciones->quitar($asignacion, $request->user());

        return response()->json(['ok' => true]);
    }
}
