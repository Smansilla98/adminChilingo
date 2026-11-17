<?php

namespace App\Services\Dashboard;

use App\Models\Profesor;
use App\Services\AmbitoSedeService;

/**
 * Profesores con más alumnos activos en sus bloques (top 8) para el panel de administración.
 */
class ProfesoresTopService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(?array $filtroSedes, AmbitoSedeService $ambito): array
    {
        // Profesores (top 8) por cantidad de alumnos activos en sus bloques
        $profBaseQ = Profesor::query()
            ->where('activo', true)
            ->with(['bloques' => function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true)->with('sede');
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($q, $filtroSedes);
                }
            }])
            ->withCount(['bloques as bloques_count' => function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true);
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($q, $filtroSedes);
                }
            }]);
        if ($filtroSedes !== null) {
            $profBaseQ->whereHas('bloques', function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true);
                $ambito->aplicarBloques($q, $filtroSedes);
            });
        }
        $profBase = $profBaseQ
            ->get()
            ->map(function (Profesor $p) {
                $p->alumnos_count = (int) ($p->bloques_count ?? $p->bloques->count());
                $p->sedes_str = $p->bloques->pluck('sede.nombre')->unique()->filter()->implode(' · ');
                $p->initials = collect(preg_split('/\s+/', trim($p->nombre ?? '')))
                    ->filter()
                    ->take(2)
                    ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                    ->join('');
                $colors = ['av-orange', 'av-blue', 'av-green', 'av-purple', 'av-amber'];
                $p->avatar_class = $colors[abs(crc32((string) $p->id)) % count($colors)];

                return $p;
            })
            ->sortByDesc('alumnos_count')
            ->values()
            ->take(8);

        $maxAlumnosProfesor = (int) ($profBase->max('alumnos_count') ?: 1);

        return compact(
            'profBase',
            'maxAlumnosProfesor'
        );
    }
}
