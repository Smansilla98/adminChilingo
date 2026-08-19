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
     * @return array{sedes: \Illuminate\Support\Collection, bloques: \Illuminate\Support\Collection}
     */
    public function filtrosCatalogo(?User $user): array
    {
        if ($user && $user->acotaPorSede()) {
            $sedeIds = $user->sedeIdsOperativas();
            $ids = $sedeIds !== [] ? $sedeIds : [0];

            return [
                'sedes' => Sede::where('activo', true)->whereIn('id', $ids)->get(),
                'bloques' => Bloque::where('activo', true)->whereIn('sede_id', $ids)->with('sede')->get(),
            ];
        }

        return [
            'sedes' => Sede::where('activo', true)->get(),
            'bloques' => Bloque::where('activo', true)->with('sede')->get(),
        ];
    }

    private function aplicarAlcance(Builder $query, ?User $user): void
    {
        if ($user && $user->acotaPorSede()) {
            $sedeIds = $user->sedeIdsOperativas();
            $ids = $sedeIds !== [] ? $sedeIds : [0];
            $query->where(function ($q) use ($ids) {
                $q->whereIn('sede_id', $ids)
                    ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.sede_id', $ids))
                    ->orWhereHas('bloque', fn ($b) => $b->whereIn('sede_id', $ids));
            });

            return;
        }

        if ($user && $user->isProfesor() && ! $user->isAdmin() && ! $user->puedeGestionarOperativo()) {
            $prof = $user->profesor;
            if ($prof) {
                $query->where(function ($sub) use ($prof) {
                    $bloqueVisible = fn ($q) => $q->where('profesor_id', $prof->id)
                        ->orWhereHas('profesores', fn ($q2) => $q2->where('profesores.id', $prof->id));
                    $sub->whereHas('bloque', $bloqueVisible)
                        ->orWhereHas('bloques', $bloqueVisible);
                });
            } else {
                $query->whereRaw('1=0');
            }
        }
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
