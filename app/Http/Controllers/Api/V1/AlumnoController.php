<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\EstadoCuentaService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AlumnoResource;
use App\Models\Alumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlumnoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Alumno::class);
        $query = Alumno::query()->with(['sede:id,nombre', 'bloques:id,nombre'])->orderBy('nombre_apellido');
        $request->user()->acceso()->alcance('alumnos.view')->aplicarAlumnos($query);

        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('nombre_apellido', 'like', $t)->orWhere('dni', 'like', $t));
        }
        if ($request->filled('bloque_id')) {
            $query->whereHas('bloques', fn ($b) => $b->where('bloques.id', $request->integer('bloque_id')));
        }
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->integer('sede_id'));
        }
        $query->where('activo', $request->input('activo', '1') === '1');

        return AlumnoResource::collection($query->paginate(min(100, max(10, $request->integer('por_pagina', 30)))));
    }

    public function show(Request $request, Alumno $alumno): AlumnoResource
    {
        $this->authorize('view', $alumno);
        $resource = new AlumnoResource($alumno->load(['sede:id,nombre', 'bloques:id,nombre']));
        $resource->detalle = true;

        return $resource;
    }

    public function estadoCuenta(Request $request, Alumno $alumno, EstadoCuentaService $cuentas): JsonResponse
    {
        $this->authorize('verFinanzas', $alumno);

        return response()->json($cuentas->paraAlumno($alumno, $request->integer('anio') ?: null));
    }

    /** Estado de cuenta de la persona logueada (todas sus fichas de alumno). */
    public function miEstadoCuenta(Request $request, EstadoCuentaService $cuentas): JsonResponse
    {
        $user = $request->user();
        $alumnos = Alumno::query()
            ->where(fn ($q) => $q->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id))
            ->get();

        return response()->json([
            'cuentas' => $alumnos->map(fn (Alumno $a) => $cuentas->paraAlumno($a, $request->integer('anio') ?: null))->values(),
        ]);
    }
}
