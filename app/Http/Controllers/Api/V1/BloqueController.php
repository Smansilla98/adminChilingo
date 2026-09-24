<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Asistencias\AsistenciaService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AlumnoResource;
use App\Http\Resources\V1\BloqueResource;
use App\Models\Bloque;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BloqueController extends Controller
{
    /**
     * Bloques visibles. `?para=asistencia` devuelve solo donde puede tomar asistencia.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $acceso = $request->user()->acceso();
        $permiso = $request->input('para') === 'asistencia' ? 'asistencias.create' : 'bloques.view';
        abort_unless($acceso->puede($permiso), 403);

        $query = Bloque::query()->where('activo', true)->with(['sede:id,nombre', 'horarios'])->withCount('alumnos')
            ->orderBy('sede_id')->orderBy('año')->orderBy('nombre');
        $acceso->alcance($permiso)->aplicarBloques($query);
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->integer('sede_id'));
        }

        return BloqueResource::collection($query->get());
    }

    public function show(Request $request, Bloque $bloque): BloqueResource
    {
        $this->authorize('view', $bloque);

        return new BloqueResource($bloque->load(['sede:id,nombre', 'horarios'])->loadCount('alumnos'));
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
