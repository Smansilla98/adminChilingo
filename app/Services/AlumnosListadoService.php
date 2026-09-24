<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AlumnosListadoService
{
    public function queryIndex(Request $request, ?User $user): Builder
    {
        $query = Alumno::with(['bloque.sede', 'bloques.sede', 'sede']);
        $this->aplicarAlcance($query, $user);
        $this->aplicarFiltros($query, $request);

        return $query;
    }

    /**
     * Sedes y bloques para los filtros, dentro del alcance de alumnos.view.
     *
     * @return array{sedes: \Illuminate\Support\Collection, bloques: \Illuminate\Support\Collection}
     */
    public function filtrosCatalogo(?User $user): array
    {
        $sedes = Sede::where('activo', true)->orderBy('nombre');
        $bloques = Bloque::where('activo', true)->with('sede');
        if ($user) {
            $alcance = $user->acceso()->alcance('alumnos.view');
            $alcance->aplicarPorSede($sedes, 'id', true);
            $alcance->aplicarBloques($bloques);
        }

        return ['sedes' => $sedes->get(), 'bloques' => $bloques->get()];
    }

    /**
     * Alumnos que caen dentro del alcance (sede o bloque) de alumnos.view.
     */
    private function aplicarAlcance(Builder $query, ?User $user): void
    {
        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }
        $user->acceso()->alcance('alumnos.view')->aplicarAlumnos($query);
    }

    private function aplicarFiltros(Builder $query, Request $request): void
    {
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }
        if ($request->filled('bloque_id')) {
            $query->whereHas('bloques', function ($q) use ($request) {
                $q->where('bloques.id', $request->bloque_id);
            });
        }
        if ($request->filled('activo')) {
            $query->where('activo', $request->activo === '1');
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_apellido', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%");
            });
        }
        if ($request->filled('tipo_tambor')) {
            $query->where('tipo_tambor', $request->tipo_tambor);
        }
        if ($request->filled('tambor_procedencia')) {
            $query->where('tambor_procedencia', $request->tambor_procedencia);
        }
    }
}
