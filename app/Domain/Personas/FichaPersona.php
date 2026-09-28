<?php

namespace App\Domain\Personas;

use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Acceso\ResolvedorAcceso;
use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Asistencia;
use App\Models\Evento;
use App\Models\InventarioItem;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Datos de la ficha central de una persona, filtrados por lo que puede ver quien
 * consulta. Lo usan el panel web y la API para mostrar exactamente lo mismo.
 */
class FichaPersona
{
    public function __construct(
        private PresentadorAcceso $presentador,
        private EstadoCuentaService $estadoCuenta,
    ) {}

    /**
     * @return array{
     *   funciones: list<array<string, mixed>>,
     *   permisos: array<string, mixed>|null,
     *   cuentas: array<int, array<string, mixed>>,
     *   asistencias: Collection<int, Asistencia>,
     *   eventos: Collection<int, Evento>,
     *   inventario: Collection<int, InventarioItem>
     * }
     */
    public function datos(Persona $persona, User $quien, ?int $anio = null): array
    {
        $persona->loadMissing(['user', 'profesor.bloques.sede', 'profesor.sedesConRol', 'alumnos.bloques.sede', 'alumnos.sede', 'fusionadaEn']);

        $acceso = app(ResolvedorAcceso::class)->paraPersona($persona);
        $funciones = $this->presentador->funciones($acceso);
        $permisos = $persona->user && $quien->can('view', $persona->user) && $quien->acceso()->puede('usuarios.view')
            ? $this->presentador->permisosAgrupados($acceso)
            : null;

        $alumnos = $persona->alumnos;
        $cuentas = [];
        foreach ($alumnos as $alumno) {
            if ($quien->can('verFinanzas', $alumno)) {
                $cuentas[$alumno->id] = $this->estadoCuenta->paraAlumno($alumno, $anio);
            }
        }

        $alumnoIds = $alumnos->pluck('id')->all();
        $asistencias = $alumnoIds === [] ? collect() : Asistencia::query()
            ->whereIn('alumno_id', $alumnoIds)
            ->with('bloque:id,nombre')
            ->orderByDesc('fecha')
            ->limit(20)
            ->get();

        $sedesPersona = collect($funciones)->pluck('sede_id')->filter()->unique()->values()->all();
        $eventos = Evento::query()
            ->with('sede:id,nombre')
            ->where('fecha', '>=', now()->toDateString())
            ->where(fn ($q) => $q->whereIn('sede_id', $sedesPersona ?: [0])->orWhere(fn ($g) => $g->whereNull('sede_id')->whereNull('bloque_id')))
            ->orderBy('fecha')
            ->limit(8)
            ->get();

        $inventario = $alumnoIds === [] ? collect() : InventarioItem::query()->whereIn('alumno_id', $alumnoIds)->with('sede:id,nombre')->get();

        return compact('funciones', 'permisos', 'cuentas', 'asistencias', 'eventos', 'inventario');
    }
}
