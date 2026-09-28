<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Asistencias\SeguimientoService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\ObservacionPedagogica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bitácora pedagógica del alumno. El equipo docente ve todas las notas; el propio
 * alumno solo las marcadas como visibles para él.
 */
class SeguimientoController extends Controller
{
    public function index(Request $request, Alumno $alumno, SeguimientoService $seguimiento): JsonResponse
    {
        $user = $request->user();
        $propio = $user->can('esPropio', $alumno);
        $equipo = $user->acceso()->puedeSobreAlumno('seguimiento.view', $alumno);
        abort_unless($propio || $equipo, 403);
        $notas = ObservacionPedagogica::query()->where('alumno_id', $alumno->id)->with(['autor:id,name', 'bloque:id,nombre'])
            ->when(! $equipo, fn ($q) => $q->where('visible_alumno', true))
            ->orderByDesc('fecha')->orderByDesc('id')->limit(100)->get();

        return response()->json([
            'data' => $notas->map(fn (ObservacionPedagogica $o) => [
                'id' => $o->id,
                'fecha' => $o->fecha?->toDateString(),
                'tipo' => $o->tipo,
                'tipo_nombre' => ObservacionPedagogica::TIPOS[$o->tipo] ?? $o->tipo,
                'eje' => $o->eje,
                'eje_nombre' => $o->eje ? (ObservacionPedagogica::EJES[$o->eje] ?? $o->eje) : null,
                'toque' => $o->toque,
                'cuerpo' => $o->cuerpo,
                'proximo_paso' => $o->proximo_paso,
                'visible_alumno' => (bool) $o->visible_alumno,
                'autor' => $o->autor?->name,
                'bloque' => $o->bloque?->nombre,
                'puede_eliminar' => $seguimiento->puedeEliminar($user, $o),
            ])->values(),
            'puede_escribir' => $user->acceso()->puedeSobreAlumno('seguimiento.create', $alumno) && $user->puedeGestionarAlumno($alumno->loadMissing(['bloques', 'bloque'])),
            'tipos' => ObservacionPedagogica::TIPOS,
            'ejes' => ObservacionPedagogica::EJES,
        ]);
    }

    public function store(Request $request, SeguimientoService $seguimiento): JsonResponse
    {
        $o = $seguimiento->registrar($request->user(), $request->validate($seguimiento->reglas()), $request->boolean('visible_alumno'));

        return response()->json(['data' => ['id' => $o->id]], 201);
    }

    public function destroy(Request $request, ObservacionPedagogica $observacion, SeguimientoService $seguimiento): JsonResponse
    {
        $seguimiento->eliminar($request->user(), $observacion);

        return response()->json(['ok' => true]);
    }
}
