<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\BloqueService;
use App\Domain\Asistencias\AsistenciaService;
use App\Domain\Datos\EliminacionSegura;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AlumnoResource;
use App\Http\Resources\V1\BloqueResource;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BloqueController extends Controller
{
    /**
     * Bloques visibles. `?para=asistencia` devuelve solo donde puede tomar asistencia.
     * `?incluir_inactivos=1` suma los inactivos (gestión). `q` filtra por nombre.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $acceso = $request->user()->acceso();
        $permiso = $request->input('para') === 'asistencia' ? 'asistencias.create' : 'bloques.view';
        abort_unless($acceso->puede($permiso), 403);

        $query = Bloque::query()->with(['sede:id,nombre', 'horarios', 'profesor:id,nombre'])->withCount('alumnos')
            ->orderBy('sede_id')->orderBy('año')->orderBy('nombre');
        if (! $request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }
        $acceso->alcance($permiso)->aplicarBloques($query);
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->integer('sede_id'));
        }
        if ($request->filled('anio')) {
            $query->where('año', $request->integer('anio'));
        }
        if ($request->filled('q')) {
            $query->where('nombre', 'like', '%'.trim((string) $request->input('q')).'%');
        }

        return BloqueResource::collection($query->get());
    }

    public function show(Request $request, Bloque $bloque): JsonResponse
    {
        $this->authorize('view', $bloque);
        $bloque->load(['sede:id,nombre', 'horarios', 'profesor:id,nombre', 'profesores:id,nombre', 'eventos' => fn ($q) => $q->where('fecha', '>=', now()->toDateString())->orderBy('fecha')->limit(10)])
            ->loadCount('alumnos');
        $user = $request->user();

        return response()->json(['data' => (new BloqueResource($bloque))->toArray($request) + [
            'corresponde_a' => $bloque->corresponde_a,
            'cantidad_max_alumnos' => (int) $bloque->cantidad_max_alumnos,
            'tambores' => $bloque->tambores ?? [],
            'profesor' => $bloque->profesor ? ['id' => $bloque->profesor->id, 'nombre' => $bloque->profesor->nombre] : null,
            'profesores' => $bloque->profesores->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'rol' => $p->pivot->rol])->values(),
            'horarios_detalle' => $bloque->horarios->map(fn (BloqueHorario $h) => [
                'id' => $h->id,
                'dia' => (int) $h->dia_semana,
                'dia_nombre' => BloqueResource::DIAS[(int) $h->dia_semana] ?? null,
                'inicio' => $h->hora_inicio?->format('H:i'),
                'fin' => $h->hora_fin?->format('H:i'),
            ])->values(),
            'eventos' => $bloque->eventos->map(fn ($e) => ['id' => $e->id, 'titulo' => $e->titulo, 'fecha' => $e->fecha?->toDateString()])->values(),
            'acciones' => [
                'editar' => $user->can('update', $bloque),
                'eliminar' => $user->can('delete', $bloque),
                'tomar_asistencia' => $user->can('tomarAsistencia', $bloque),
                'ver_alumnos' => $user->can('verAsistencias', $bloque) || $user->acceso()->puedeEnBloque('alumnos.view', $bloque->id, $bloque->sede_id),
            ],
        ]]);
    }

    public function store(Request $request, BloqueService $bloques): JsonResponse
    {
        $this->authorize('create', Bloque::class);
        $datos = $request->validate($bloques->reglasApi());
        $datos = $bloques->desdeApi($datos) + ['activo' => $request->boolean('activo', true)];
        $bloque = $bloques->crear($datos, $request->user());

        return $this->show($request, $bloque)->setStatusCode(201);
    }

    public function update(Request $request, Bloque $bloque, BloqueService $bloques): JsonResponse
    {
        $this->authorize('update', $bloque);
        $datos = $bloques->desdeApi($request->validate($bloques->reglasApi()));
        $datos['activo'] = $request->boolean('activo', (bool) $bloque->activo);
        $bloques->actualizar($bloque, $datos, $request->user());

        return $this->show($request, $bloque->fresh());
    }

    public function destroy(Bloque $bloque, EliminacionSegura $eliminacion): JsonResponse
    {
        $this->authorize('delete', $bloque);
        $eliminacion->verificar($bloque);
        $bloque->delete();

        return response()->json(['ok' => true]);
    }

    public function agregarHorario(Request $request, Bloque $bloque, BloqueService $bloques): JsonResponse
    {
        $this->authorize('update', $bloque);
        $h = $bloques->agregarHorario($bloque, $request->validate($bloques->reglasHorario()));

        return response()->json(['data' => ['id' => $h->id]], 201);
    }

    public function quitarHorario(Request $request, BloqueHorario $horario): JsonResponse
    {
        $this->authorize('update', $horario->bloque);
        $horario->delete();

        return response()->json(['ok' => true]);
    }

    /** Opciones para el formulario: sedes donde puede gestionar bloques, docentes, tambores. */
    public function catalogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('bloques.manage'), 403);
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre');
        $request->user()->acceso()->alcance('bloques.manage')->aplicarPorSede($sedes, 'id');

        return response()->json([
            'sedes' => $sedes->get(['id', 'nombre']),
            'profesores' => Profesor::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tambores' => Bloque::TAMBORES_DISPONIBLES,
            'dias' => BloqueResource::DIAS,
        ]);
    }

    /** Alumnos del bloque (para tomar asistencia o consultar). */
    public function alumnos(Request $request, Bloque $bloque, AsistenciaService $asistencias): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can('verAsistencias', $bloque) || $request->user()->acceso()->puedeEnBloque('alumnos.view', $bloque->id, $bloque->sede_id),
            403
        );

        return AlumnoResource::collection($asistencias->alumnosDelBloque($bloque));
    }
}
