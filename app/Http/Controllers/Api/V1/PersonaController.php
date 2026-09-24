<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Acceso\ResolvedorAcceso;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PersonaResource;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Persona::class);
        $alcance = $request->user()->acceso()->alcance('personas.view');
        $query = Persona::query()->whereNull('fusionada_en_id')->with(['user:id,persona_id', 'alumnos:id,persona_id', 'profesor:id,persona_id'])
            ->buscar($request->input('q'))->orderBy('nombre');
        if (! $alcance->esGlobal()) {
            $query->where(fn (Builder $q) => $q->whereHas('alumnos', fn (Builder $a) => $alcance->aplicarAlumnos($a))
                ->orWhereHas('profesores', fn (Builder $p) => $p->whereHas('bloques', fn (Builder $b) => $alcance->aplicarBloques($b))));
        }

        return PersonaResource::collection($query->paginate(30));
    }

    public function show(Request $request, Persona $persona, PresentadorAcceso $presentador): JsonResponse
    {
        $this->authorize('view', $persona);
        $persona->load(['user:id,persona_id', 'alumnos:id,persona_id', 'profesor:id,persona_id']);

        return response()->json([
            'data' => (new PersonaResource($persona))->toArray($request),
            'funciones' => $presentador->funciones(app(ResolvedorAcceso::class)->paraPersona($persona)),
        ]);
    }
}
