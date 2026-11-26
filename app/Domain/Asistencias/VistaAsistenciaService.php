<?php

namespace App\Domain\Asistencias;

use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de las pantallas web de asistencias (listado, matriz mensual y toma del día).
 * El controlador valida, autoriza y responde; las consultas y la escritura de la matriz viven acá.
 */
class VistaAsistenciaService
{
    /**
     * Bloques activos que el usuario puede ver en asistencias.
     *
     * @return Collection<int, Bloque>
     */
    public function bloquesVisibles(?User $user, bool $ordenarPorNombre = true): Collection
    {
        $query = Bloque::where('activo', true)->with('sede');
        if ($ordenarPorNombre) {
            $query->orderBy('nombre');
        }
        if ($user && ! $user->veTodosLosBloques()) {
            $ids = $user->bloqueIdsPermitidos();
            $query->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        return $query->get();
    }

    /** Listado paginado de asistencias, con filtros opcionales de bloque (ya autorizado) y fecha. */
    public function listado(?User $user, ?int $bloqueId, ?string $fecha): LengthAwarePaginator
    {
        $query = Asistencia::with(['alumno', 'bloque']);

        if ($user && ! $user->veTodosLosBloques()) {
            $ids = $user->bloqueIdsPermitidos();
            $query->whereIn('bloque_id', $ids !== [] ? $ids : [0]);
        }
        if ($bloqueId !== null) {
            $query->where('bloque_id', $bloqueId);
        }
        if ($fecha !== null) {
            $query->where('fecha', $fecha);
        }

        return $query->orderBy('fecha', 'desc')->paginate(50);
    }

    /**
     * Datos de la matriz mensual de un bloque.
     *
     * @return array{fechas: Collection<int, Carbon>, alumnos: Collection, asistenciasMap: Collection, marcadoresEspeciales: int}
     */
    public function matriz(Bloque $bloque, int $año, int $mes): array
    {
        $fechas = app(AsistenciaService::class)->fechasClaseDelMes($bloque, $año, $mes)
            ->map(fn (string $f) => Carbon::parse($f));
        $alumnos = $bloque->alumnos()->where('alumnos.activo', true)->orderBy('alumnos.nombre_apellido')->get();

        $asistenciasMap = collect();
        $marcadoresEspeciales = 0;
        if (Schema::hasTable('asistencias')) {
            $asistenciasMap = Asistencia::query()
                ->where('bloque_id', $bloque->id)
                ->whereYear('fecha', $año)
                ->whereMonth('fecha', $mes)
                ->get()
                ->keyBy(fn (Asistencia $a) => $a->alumno_id.'|'.$a->fecha->format('Y-m-d'));
            $marcadoresEspeciales = $asistenciasMap->filter(fn (Asistencia $a) => in_array($a->tipo_asistencia, ['feriado', 'sin_clases'], true))->count();
        }

        return compact('fechas', 'alumnos', 'asistenciasMap', 'marcadoresEspeciales');
    }

    /** Asistencias ya registradas del bloque en una fecha, indexadas por alumno. */
    public function asistenciasDelDia(Bloque $bloque, string $fecha): Collection
    {
        return Asistencia::query()
            ->where('bloque_id', $bloque->id)
            ->whereDate('fecha', $fecha)
            ->get()
            ->keyBy('alumno_id');
    }

    /**
     * Guarda las celdas de la matriz. Sólo alumnos activos del bloque y fechas de clase del mes;
     * una celda vacía borra el registro. Se guarda todo o nada.
     *
     * @param  array<string, array<string, string|null>>  $cells
     */
    public function guardarPlanilla(Bloque $bloque, int $año, int $mes, array $cells): void
    {
        $fechasPermitidas = app(AsistenciaService::class)->fechasClaseDelMes($bloque, $año, $mes)->flip();
        $alumnoIdsPermitidos = $bloque->alumnos()->where('alumnos.activo', true)->pluck('alumnos.id')->flip();

        DB::transaction(function () use ($bloque, $cells, $fechasPermitidas, $alumnoIdsPermitidos) {
            foreach ($cells as $alumnoIdStr => $porFecha) {
                $alumnoId = (int) $alumnoIdStr;
                if (! $alumnoIdsPermitidos->has($alumnoId)) {
                    continue;
                }
                foreach ($porFecha as $fechaStr => $tipo) {
                    if (! $fechasPermitidas->has($fechaStr)) {
                        continue;
                    }
                    if ($tipo === null || $tipo === '') {
                        Asistencia::query()
                            ->where('bloque_id', $bloque->id)
                            ->where('alumno_id', $alumnoId)
                            ->whereDate('fecha', $fechaStr)
                            ->delete();

                        continue;
                    }

                    Asistencia::updateOrCreate(
                        ['alumno_id' => $alumnoId, 'bloque_id' => $bloque->id, 'fecha' => $fechaStr],
                        ['tipo_asistencia' => $tipo, 'presente' => Asistencia::esPresente($tipo)]
                    );
                }
            }
        });
    }
}
