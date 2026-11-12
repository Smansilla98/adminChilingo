<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Personas\CicloLectivoService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\CicloLectivo;
use App\Models\Reinscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ciclo lectivo y reinscripción (app y API). */
class CicloLectivoController extends Controller
{
    /** El alumno ve el ciclo vigente y el estado de sus alumnos a cargo. */
    public function miReinscripcion(Request $request): JsonResponse
    {
        $ciclo = CicloLectivo::vigente();
        $alumnos = Alumno::query()->where('user_id', $request->user()->id)->where('activo', true)->get(['id', 'nombre_apellido']);
        $estados = $ciclo ? Reinscripcion::query()->where('ciclo_lectivo_id', $ciclo->id)
            ->whereIn('alumno_id', $alumnos->pluck('id'))->pluck('estado', 'alumno_id') : collect();

        return response()->json([
            'ciclo' => $ciclo ? ['anio' => $ciclo->anio, 'nombre' => $ciclo->nombre, 'inicio' => $ciclo->inicio->toDateString(), 'fin' => $ciclo->fin->toDateString()] : null,
            'alumnos' => $alumnos->map(fn (Alumno $a) => [
                'id' => $a->id,
                'nombre' => $a->nombre_apellido,
                'estado' => $estados[$a->id] ?? null,
            ])->values(),
        ]);
    }

    public function confirmar(Request $request, CicloLectivoService $servicio): JsonResponse
    {
        $n = $servicio->confirmar($request->user());

        return response()->json(['ok' => true, 'confirmadas' => $n]);
    }

    /** Administración: lista los ciclos. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => CicloLectivo::query()->orderByDesc('anio')->get()->map(fn (CicloLectivo $c) => [
            'id' => $c->id, 'anio' => $c->anio, 'nombre' => $c->nombre,
            'inicio' => $c->inicio->toDateString(), 'fin' => $c->fin->toDateString(), 'cerrado' => $c->cerrado_at !== null,
        ])->values()]);
    }

    public function store(Request $request, CicloLectivoService $servicio): JsonResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100', 'unique:ciclos_lectivos,anio'],
            'nombre' => ['nullable', 'string', 'max:80'],
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date'],
        ]);
        $ciclo = $servicio->abrir($datos, $request->user());
        $avisos = $servicio->avisar($ciclo);

        return response()->json(['id' => $ciclo->id, 'anio' => $ciclo->anio, 'avisos' => $avisos, 'resumen' => $servicio->resumen($ciclo)], 201);
    }

    /** Reinscripciones de un ciclo, con filtro de estado opcional. */
    public function reinscripciones(Request $request, CicloLectivo $ciclo, CicloLectivoService $servicio): JsonResponse
    {
        $estado = $request->query('estado');
        $lista = Reinscripcion::query()->with('alumno:id,nombre_apellido')
            ->where('ciclo_lectivo_id', $ciclo->id)
            ->when(in_array($estado, [Reinscripcion::PENDIENTE, Reinscripcion::CONFIRMADA, Reinscripcion::BAJA], true), fn ($q) => $q->where('estado', $estado))
            ->get();

        return response()->json([
            'resumen' => $servicio->resumen($ciclo),
            'data' => $lista->map(fn (Reinscripcion $r) => [
                'id' => $r->id,
                'alumno_id' => $r->alumno_id,
                'alumno' => $r->alumno?->nombre_apellido,
                'estado' => $r->estado,
                'confirmada_at' => $r->confirmada_at?->toIso8601String(),
            ])->sortBy('alumno')->values(),
        ]);
    }

    public function baja(Request $request, Reinscripcion $reinscripcion, CicloLectivoService $servicio): JsonResponse
    {
        $servicio->darDeBaja($reinscripcion, $request->user());

        return response()->json(['estado' => Reinscripcion::BAJA]);
    }
}
