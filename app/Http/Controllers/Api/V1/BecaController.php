<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\BecaService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BecaRequest;
use App\Http\Requests\BecaUpdateRequest;
use App\Models\Alumno;
use App\Models\Beca;
use App\Models\Persona;
use App\Services\AmbitoSedeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Becas: listado del alcance, otorgamiento desde la ficha de la persona y cambios de
 * estado/vigencia. Mismas reglas que el panel web (BecaRequest, BecaService,
 * AlumnoPolicy::gestionarBecas).
 */
class BecaController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito): JsonResponse
    {
        $acceso = $request->user()->acceso();
        abort_unless($acceso->puedeAlguno(['becas.view', 'becas.manage']), 403, 'No tenés permiso para ver becas.');

        $query = Beca::query()->with(['alumno:id,nombre_apellido,persona_id,sede_id', 'bloque:id,nombre', 'sede:id,nombre', 'otorgadaPor:id,name'])
            ->orderByDesc('fecha_inicio')->orderByDesc('id');
        $alcance = $acceso->alcance('becas.view');
        if (! $alcance->esGlobal()) {
            $query->whereHas('alumno', fn ($a) => $alcance->aplicarAlumnos($a));
        }
        if ($request->filled('estado') && array_key_exists($request->input('estado'), Beca::ESTADOS)) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('tipo') && array_key_exists($request->input('tipo'), Beca::TIPOS)) {
            $query->where('tipo', $request->input('tipo'));
        }
        if ($request->filled('persona_id')) {
            $query->whereHas('alumno', fn ($a) => $a->where('persona_id', $request->integer('persona_id')));
        }
        if ($request->filled('alumno_id')) {
            $query->where('alumno_id', $request->integer('alumno_id'));
        }
        if ($request->filled('vigentes')) {
            $query->vigentesEn(now());
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->whereHas('alumno', fn ($a) => $a->where('nombre_apellido', 'like', $t))->orWhere('motivo', 'like', $t));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Beca $b) => $this->beca($b, $request))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, Beca $beca): JsonResponse
    {
        abort_unless($beca->alumno && $request->user()->can('verFinanzas', $beca->alumno), 403, 'No tenés acceso a esta beca.');

        return response()->json(['data' => $this->beca($beca->load(['alumno', 'bloque', 'sede', 'otorgadaPor']), $request)]);
    }

    public function store(BecaRequest $request, Persona $persona, BecaService $becas): JsonResponse
    {
        $data = $request->validated();
        $alumno = Alumno::query()->where('persona_id', $persona->id)->findOrFail($data['alumno_id']);
        $this->authorize('gestionarBecas', $alumno);
        $beca = $becas->otorgar($alumno, $data, $request->user());

        return response()->json(['data' => $this->beca($beca->load(['alumno', 'bloque', 'sede', 'otorgadaPor']), $request)], 201);
    }

    public function update(BecaUpdateRequest $request, Beca $beca, BecaService $becas): JsonResponse
    {
        $becas->actualizar($beca, $request->validated());

        return response()->json(['data' => $this->beca($beca->fresh(['alumno', 'bloque', 'sede', 'otorgadaPor']), $request)]);
    }

    /** @return array<string, mixed> */
    private function beca(Beca $b, Request $request): array
    {
        return [
            'id' => $b->id,
            'etiqueta' => $b->etiqueta(),
            'tipo' => $b->tipo,
            'tipo_nombre' => Beca::TIPOS[$b->tipo] ?? $b->tipo,
            'porcentaje' => $b->porcentaje !== null ? (float) $b->porcentaje : null,
            'monto' => $b->monto !== null ? (float) $b->monto : null,
            'estado' => $b->estado,
            'estado_nombre' => Beca::ESTADOS[$b->estado] ?? $b->estado,
            'desde' => $b->fecha_inicio?->toDateString(),
            'hasta' => $b->fecha_fin?->toDateString(),
            'motivo' => $b->motivo,
            'observaciones' => $b->observaciones,
            'alumno' => $b->alumno ? ['id' => $b->alumno->id, 'nombre' => $b->alumno->nombre_apellido, 'persona_id' => $b->alumno->persona_id] : null,
            'bloque' => $b->bloque ? ['id' => $b->bloque->id, 'nombre' => $b->bloque->nombre] : null,
            'sede' => $b->sede ? ['id' => $b->sede->id, 'nombre' => $b->sede->nombre] : null,
            'otorgada_por' => $b->otorgadaPor?->name,
            'puede_gestionar' => $b->alumno ? $request->user()->can('gestionarBecas', $b->alumno) : false,
        ];
    }
}
