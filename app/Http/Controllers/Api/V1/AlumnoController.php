<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Datos\EliminacionSegura;
use App\Domain\Finanzas\EstadoCuentaService;
use App\Domain\Personas\AlumnoService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AlumnoResource;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
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

    public function show(Request $request, Alumno $alumno): JsonResponse
    {
        $this->authorize('view', $alumno);

        return response()->json(['data' => $this->ficha($request, $alumno)]);
    }

    /** Inscripción (también de una persona existente con `persona_id`). */
    public function store(Request $request, AlumnoService $alumnos): JsonResponse
    {
        $this->authorize('create', Alumno::class);
        $datos = $request->validate($alumnos->reglas());
        $datos['activo'] = $request->boolean('activo', true);
        $alumno = $alumnos->guardar(null, $datos, $request->user());

        return response()->json(['data' => $this->ficha($request, $alumno->fresh())], 201);
    }

    public function update(Request $request, Alumno $alumno, AlumnoService $alumnos): JsonResponse
    {
        $this->authorize('update', $alumno);
        $datos = $request->validate($alumnos->reglas($alumno));
        $datos['activo'] = $request->boolean('activo', (bool) $alumno->activo);
        if (! $request->exists('bloque_ids')) {
            $datos['bloque_ids'] = $alumno->bloques()->pluck('bloques.id')->all();
            $datos['bloque_principal_id'] ??= $alumno->bloque_id;
        }
        $alumnos->guardar($alumno, $datos, $request->user());

        return response()->json(['data' => $this->ficha($request, $alumno->fresh())]);
    }

    public function destroy(Alumno $alumno, EliminacionSegura $eliminacion): JsonResponse
    {
        $this->authorize('delete', $alumno);
        $eliminacion->verificar($alumno);
        $alumno->delete();

        return response()->json(['ok' => true]);
    }

    /** Opciones del formulario: sedes y bloques donde puede inscribir, instrumentos. */
    public function catalogo(Request $request): JsonResponse
    {
        $acceso = $request->user()->acceso();
        abort_unless($acceso->puedeAlguno(['alumnos.create', 'alumnos.update']), 403);
        $alcance = $acceso->alcance('alumnos.create')->unir($acceso->alcance('alumnos.update'));
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $bloques = Bloque::query()->where('activo', true)->with('sede:id,nombre')->orderBy('nombre')->get();
        if (! $alcance->esGlobal()) {
            $sedes = $sedes->whereIn('id', $alcance->sedesTocadas())->values();
            $bloques = $bloques->filter(fn ($b) => $alcance->incluyeBloque((int) $b->id, (int) $b->sede_id))->values();
        }

        return response()->json([
            'sedes' => $sedes,
            'bloques' => $bloques->map(fn (Bloque $b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede_id' => $b->sede_id, 'sede' => $b->sede?->nombre])->values(),
            'instrumentos' => Bloque::TAMBORES_DISPONIBLES,
            'tipos_tambor' => AlumnoService::TIPOS_TAMBOR,
            'procedencias_tambor' => AlumnoService::TAMBOR_PROCEDENCIAS,
            'profesores' => Profesor::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, Alumno $alumno): array
    {
        $alumno->load(['sede:id,nombre', 'bloques.sede:id,nombre']);
        $resource = new AlumnoResource($alumno);
        $resource->detalle = true;
        $user = $request->user();
        $puedeEditar = $user->can('update', $alumno);

        return $resource->toArray($request) + [
            'instrumento_secundario' => $alumno->instrumento_secundario,
            'tipo_tambor' => $alumno->tipo_tambor,
            'tambor_procedencia' => $alumno->tambor_procedencia,
            'bloque_principal_id' => $alumno->bloque_id,
            'bloques_detalle' => $alumno->bloques->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre, 'principal' => (bool) $b->pivot->es_principal])->values(),
            'es_docente' => $alumno->profesorPerfil() !== null,
            'acciones' => [
                'editar' => $puedeEditar,
                'eliminar' => $user->can('delete', $alumno),
                'ver_finanzas' => $user->can('verFinanzas', $alumno),
                'ver_persona' => $alumno->persona_id !== null && $alumno->persona !== null && $user->can('view', $alumno->persona),
            ],
        ];
    }

    /** Excel de alumnos del alcance (mismo archivo y filtros que el panel web). */
    public function exportar(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AlumnosExport($request, $request->user()), 'alumnos_'.now()->format('Y-m-d').'.xlsx');
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
