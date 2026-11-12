<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Personas\PreinscripcionService;
use App\Http\Controllers\Controller;
use App\Models\Preinscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bandeja de preinscripciones (app y clientes API). */
class PreinscripcionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $estado = $request->query('estado', Preinscripcion::PENDIENTE);
        $lista = Preinscripcion::query()->with(['sede:id,nombre', 'bloque:id,nombre'])
            ->when(in_array($estado, [Preinscripcion::PENDIENTE, Preinscripcion::APROBADA, Preinscripcion::RECHAZADA], true),
                fn ($q) => $q->where('estado', $estado))
            ->latest('id')->limit(100)->get();

        return response()->json(['data' => $lista->map(fn (Preinscripcion $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'apellido' => $p->apellido,
            'fecha_nacimiento' => $p->fecha_nacimiento->toDateString(),
            'menor' => $p->esMenor(),
            'telefono' => $p->telefono,
            'email' => $p->email,
            'sede' => $p->sede?->nombre,
            'bloque' => $p->bloque?->nombre,
            'tutor' => $p->tutor_nombre ? ['nombre' => $p->tutor_nombre, 'vinculo' => $p->tutor_vinculo, 'telefono' => $p->tutor_telefono] : null,
            'estado' => $p->estado,
            'motivo_rechazo' => $p->motivo_rechazo,
            'alumno_id' => $p->alumno_id,
            'fecha' => $p->created_at?->toIso8601String(),
        ])->values()]);
    }

    public function aprobar(Request $request, Preinscripcion $preinscripcion, PreinscripcionService $servicio): JsonResponse
    {
        $datos = $request->validate(['bloque_id' => ['nullable', 'integer', 'exists:bloques,id']]);
        $alumno = $servicio->aprobar($preinscripcion, $request->user(), $datos['bloque_id'] ?? null);

        return response()->json(['alumno_id' => $alumno->id, 'estado' => Preinscripcion::APROBADA]);
    }

    public function rechazar(Request $request, Preinscripcion $preinscripcion, PreinscripcionService $servicio): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $servicio->rechazar($preinscripcion, $request->user(), trim((string) ($datos['motivo'] ?? '')));

        return response()->json(['estado' => Preinscripcion::RECHAZADA]);
    }
}
