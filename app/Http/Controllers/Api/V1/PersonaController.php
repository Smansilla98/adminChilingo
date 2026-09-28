<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Personas\FichaPersona;
use App\Domain\Personas\PersonaService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PersonaRequest;
use App\Http\Resources\V1\PersonaResource;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Beca;
use App\Models\Evento;
use App\Models\InventarioItem;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Ficha central de personas. Mismas reglas que el panel web (PersonaRequest,
 * PersonaService, FichaPersona y PersonaPolicy).
 */
class PersonaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Persona::class);
        $alcance = $request->user()->acceso()->alcance('personas.view');
        $query = Persona::query()->whereNull('fusionada_en_id')->with(['user:id,persona_id', 'alumnos:id,persona_id', 'profesor:id,persona_id'])
            ->buscar($request->input('q'))->orderBy('nombre')->orderBy('apellido');
        if (! $alcance->esGlobal()) {
            $query->where(fn (Builder $q) => $q->whereHas('alumnos', fn (Builder $a) => $alcance->aplicarAlumnos($a))
                ->orWhereHas('profesores', fn (Builder $p) => $p->whereHas('bloques', fn (Builder $b) => $alcance->aplicarBloques($b))));
        }

        match ($request->input('funcion')) {
            'alumno' => $query->whereHas('alumnos'),
            'profesor' => $query->whereHas('profesores'),
            'con_cuenta' => $query->whereHas('user'),
            'sin_cuenta' => $query->whereDoesntHave('user'),
            default => null,
        };
        if ($request->filled('estado') && array_key_exists($request->input('estado'), Persona::ESTADOS)) {
            $query->where('estado', $request->input('estado'));
        }

        return PersonaResource::collection($query->paginate(min(max($request->integer('por_pagina', 30), 5), 100)));
    }

    public function store(PersonaRequest $request, PersonaService $personas): JsonResponse
    {
        $persona = $personas->crear($request->validated());

        return response()->json(['data' => $this->ficha($request, $persona)], 201);
    }

    public function show(Request $request, Persona $persona): JsonResponse
    {
        $this->authorize('view', $persona);

        return response()->json(['data' => $this->ficha($request, $persona)]);
    }

    public function update(PersonaRequest $request, Persona $persona, PersonaService $personas): JsonResponse
    {
        $personas->actualizar($persona, $request->validated());

        return response()->json(['data' => $this->ficha($request, $persona->fresh())]);
    }

    public function fusionar(Request $request, Persona $persona, PersonaService $personas): JsonResponse
    {
        $this->authorize('merge', Persona::class);
        $data = $request->validate(['duplicada_id' => 'required|integer|exists:personas,id']);
        abort_if((int) $data['duplicada_id'] === (int) $persona->id, 422, 'No se puede fusionar una persona consigo misma.');
        $resultado = $personas->fusionar($persona, Persona::query()->findOrFail($data['duplicada_id']));

        return response()->json(['data' => $this->ficha($request, $resultado)]);
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, Persona $persona): array
    {
        /** @var User $quien */
        $quien = $request->user();
        $datos = app(FichaPersona::class)->datos($persona, $quien, $request->integer('anio') ?: null);
        $acceso = $quien->acceso();
        $alumnos = $persona->alumnos;
        $profesor = $persona->profesor;

        return (new PersonaResource($persona))->toArray($request) + [
            'direccion' => $persona->direccion,
            'contacto_emergencia_nombre' => $persona->contacto_emergencia_nombre,
            'contacto_emergencia_telefono' => $persona->contacto_emergencia_telefono,
            'observaciones' => $persona->observaciones,
            'edad' => $persona->edad,
            'fusionada_en' => $persona->fusionadaEn ? ['id' => $persona->fusionadaEn->id, 'nombre' => $persona->fusionadaEn->nombre_completo] : null,
            'funciones' => $datos['funciones'],
            'permisos' => $datos['permisos'],
            'cuenta' => $persona->user && $quien->can('view', $persona->user) ? [
                'id' => $persona->user->id,
                'username' => $persona->user->username,
                'activo' => (bool) $persona->user->activo,
                'ultimo_acceso' => $persona->user->ultimo_acceso_at?->toIso8601String(),
            ] : null,
            'alumnos' => $alumnos->map(fn (Alumno $a) => [
                'id' => $a->id,
                'activo' => (bool) $a->activo,
                'instrumento' => $a->instrumento_principal,
                'sede' => $a->sede ? ['id' => $a->sede->id, 'nombre' => $a->sede->nombre] : null,
                'bloques' => $a->bloques->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre])->values(),
                'estado_cuenta' => $datos['cuentas'][$a->id] ?? null,
                'puede_ver' => $quien->can('view', $a),
                'puede_gestionar_becas' => $quien->can('gestionarBecas', $a),
            ])->values(),
            'profesor' => $profesor ? [
                'id' => $profesor->id,
                'activo' => (bool) $profesor->activo,
                'bloques' => $profesor->bloques->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre, 'rol' => $b->pivot->rol])->values(),
                'sedes' => $profesor->sedesConRol->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'rol' => $s->pivot->rol])->values(),
            ] : null,
            'asistencias' => $datos['asistencias']->map(fn (Asistencia $x) => [
                'id' => $x->id,
                'fecha' => $x->fecha?->toDateString(),
                'tipo' => $x->tipo_asistencia,
                'tipo_nombre' => Asistencia::TIPOS_ASISTENCIA[$x->tipo_asistencia] ?? $x->tipo_asistencia,
                'bloque' => $x->bloque?->nombre,
            ])->values(),
            'eventos' => $datos['eventos']->map(fn (Evento $e) => [
                'id' => $e->id,
                'titulo' => $e->titulo,
                'fecha' => $e->fecha?->toDateString(),
                'hora_inicio' => $e->hora_inicio ? substr((string) $e->hora_inicio, 0, 5) : null,
                'sede' => $e->sede?->nombre,
            ])->values(),
            'inventario' => $datos['inventario']->map(fn (InventarioItem $i) => [
                'id' => $i->id,
                'codigo' => $i->codigo,
                'nombre' => $i->nombre,
                'estado' => $i->estado,
                'sede' => $i->sede?->nombre,
            ])->values(),
            'acciones' => [
                'editar' => $quien->can('update', $persona),
                'fusionar' => $quien->can('merge', Persona::class),
                'inscribir_alumno' => $alumnos->isEmpty() && $acceso->puede('alumnos.create'),
                'sumar_docente' => ! $profesor && $acceso->puede('profesores.create'),
                'crear_cuenta' => ! $persona->user && $acceso->puede('usuarios.create'),
                'ver_cuenta' => $persona->user !== null && $quien->can('view', $persona->user),
                'gestionar_becas' => $alumnos->contains(fn (Alumno $a) => $quien->can('gestionarBecas', $a)),
                'ver_auditoria' => $acceso->puede('auditoria.view'),
            ],
            'tipos_beca' => Beca::TIPOS,
        ];
    }
}
