<?php

namespace App\Http\Controllers;

use App\Domain\Datos\EliminacionSegura;
use App\Domain\Personas\AlumnoImportService;
use App\Domain\Personas\AlumnoService;
use App\Exports\AlumnosExport;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\Sede;
use App\Services\AlumnosListadoService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AlumnoController extends Controller
{
    private const TIPOS_TAMBOR = AlumnoService::TIPOS_TAMBOR;

    private const TAMBOR_PROCEDENCIAS = AlumnoService::TAMBOR_PROCEDENCIAS;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AlumnosListadoService $listado)
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();
            $query = $listado->queryIndex($request, $user);
            $catalogo = $listado->filtrosCatalogo($user);
            $sedes = $catalogo['sedes'];
            $bloques = $catalogo['bloques'];

            $alumnos = $query->orderBy('nombre_apellido')->paginate(20);
            $tiposTambor = self::TIPOS_TAMBOR;
            $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;
        } catch (QueryException $e) {
            $alumnos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $sedes = collect();
            $bloques = collect();
            $tiposTambor = self::TIPOS_TAMBOR;
            $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;
        }

        return view('alumnos.index', compact('alumnos', 'sedes', 'bloques', 'tiposTambor', 'procedenciasTambor'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $bloques = collect();
        }
        [$sedes, $bloques] = $this->acotarSedesYBloques($sedes, $bloques);
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;
        $tiposTambor = self::TIPOS_TAMBOR;
        $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;

        $profesoresSinVinculo = $this->profesoresDisponiblesParaVinculo();
        $persona = request()->integer('persona_id') ? Persona::query()->find(request()->integer('persona_id')) : null;
        if ($persona) {
            $this->authorize('view', $persona);
        }

        return view('alumnos.create', compact('sedes', 'bloques', 'instrumentos', 'tiposTambor', 'procedenciasTambor', 'profesoresSinVinculo', 'persona'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, AlumnoService $alumnos)
    {
        $validated = $request->validate($alumnos->reglas());
        $validated['activo'] = $request->boolean('activo');
        $alumnos->guardar(null, $validated, $request->user());

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Alumno $alumno)
    {
        $this->authorize('view', $alumno);

        $alumno->load(['bloque.profesor', 'bloques.sede', 'bloques.profesor', 'sede', 'asistencias', 'user']);
        if (\Illuminate\Support\Facades\Schema::hasTable('observaciones_pedagogicas')) {
            $alumno->load(['observacionesPedagogicas.autor', 'observacionesPedagogicas.bloque']);
        }
        $profesorPerfil = $alumno->profesorPerfil();

        $bloquesIds = $alumno->bloques->pluck('id')->filter()->values();
        if ($bloquesIds->isEmpty() && $alumno->bloque_id) {
            $bloquesIds = collect([$alumno->bloque_id]);
        }

        $cuotas = collect();
        if ($bloquesIds->isNotEmpty()) {
            $q = Cuota::query()
                ->with(['alumnos:id'])
                ->where('activo', true);

            if (\Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance')) {
                $sidList = $alumno->bloques->pluck('sede_id')->filter()->unique()->values()->all();
                if ($alumno->sede_id) {
                    $sidList = array_values(array_unique(array_merge($sidList, [(int) $alumno->sede_id])));
                }
                $q->where(function ($outer) use ($bloquesIds, $sidList) {
                    $outer->whereIn('bloque_id', $bloquesIds->all())
                        ->orWhere(function ($g) {
                            $g->where('alcance', Cuota::ALCANCE_GENERAL)->whereNull('bloque_id');
                        });
                    if ($sidList !== []) {
                        $outer->orWhere(function ($s) use ($sidList) {
                            $s->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $sidList);
                        });
                    }
                });
            } else {
                $q->whereIn('bloque_id', $bloquesIds->all());
            }

            $cuotas = $q->orderByDesc('año')->orderByDesc('mes')->get();
        }

        $cuotasAplicables = $cuotas->filter(function (Cuota $cuota) use ($alumno) {
            if ($cuota->alumnos->isEmpty()) {
                return true;
            }

            return $cuota->alumnos->contains('id', $alumno->id);
        })->values();

        try {
            $historialPagos = PagoDetalle::query()
                ->with(['pago', 'cuota'])
                ->where('alumno_id', $alumno->id)
                ->whereHas('pago')
                ->get()
                ->sortByDesc(fn ($d) => $d->pago?->fecha_pago)
                ->values();
        } catch (QueryException $e) {
            $historialPagos = collect();
        }

        $pagosPorCuota = $historialPagos->keyBy('cuota_id');

        $hoy = Carbon::today();
        $estadoCuenta = $cuotasAplicables->map(function (Cuota $cuota) use ($pagosPorCuota, $hoy) {
            $pagoDetalle = $pagosPorCuota->get($cuota->id);

            $fechaPago = $pagoDetalle?->pago?->fecha_pago;
            $fechaVencimiento = $cuota->fecha_vencimiento;

            if ($fechaPago) {
                $estado = 'Pagada';
                $estadoColor = 'success';
            } elseif ($fechaVencimiento && $fechaVencimiento->lt($hoy)) {
                $estado = 'Vencida';
                $estadoColor = 'danger';
            } else {
                $estado = 'Pendiente';
                $estadoColor = 'warning';
            }

            return [
                'cuota' => $cuota,
                'periodo' => ($cuota->mes ? str_pad((string) $cuota->mes, 2, '0', STR_PAD_LEFT) : '—').'/'.($cuota->año ?? '—'),
                'monto' => (float) $cuota->monto,
                'fecha_pago' => $fechaPago,
                'estado' => $estado,
                'estado_color' => $estadoColor,
            ];
        });

        return view('alumnos.show', compact('alumno', 'estadoCuenta', 'historialPagos', 'profesorPerfil'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Alumno $alumno)
    {
        $this->authorize('update', $alumno);
        try {
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $bloques = collect();
        }
        [$sedes, $bloques] = $this->acotarSedesYBloques($sedes, $bloques);
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;
        $tiposTambor = self::TIPOS_TAMBOR;
        $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;

        $alumno->load('bloques');
        $profesoresSinVinculo = $this->profesoresDisponiblesParaVinculo($alumno);

        return view('alumnos.edit', compact('alumno', 'sedes', 'bloques', 'instrumentos', 'tiposTambor', 'procedenciasTambor', 'profesoresSinVinculo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Alumno $alumno, AlumnoService $alumnos)
    {
        $this->authorize('update', $alumno);
        $validated = $request->validate($alumnos->reglas($alumno));
        $validated['activo'] = $request->boolean('activo');
        $alumnos->guardar($alumno, $validated, $request->user());

        return redirect()->route('alumnos.show', $alumno)
            ->with('success', 'Alumno actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Alumno $alumno, EliminacionSegura $eliminacion)
    {
        $this->authorize('delete', $alumno);
        $eliminacion->verificar($alumno);
        $alumno->delete();

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno eliminado exitosamente.');
    }

    /**
     * Exportar alumnos a Excel
     */
    public function export(Request $request)
    {
        return Excel::download(new AlumnosExport($request, auth()->user()), 'alumnos_'.now()->format('Y-m-d').'.xlsx');
    }

    public function importForm()
    {
        $sedes = Sede::orderBy('nombre')->get();
        $bloques = Bloque::orderBy('nombre')->get();

        return view('alumnos.import', compact('sedes', 'bloques'));
    }

    public function importStore(Request $request, AlumnoImportService $importador)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'exists:bloques,id'],
        ], [
            'archivo.required' => 'Tenés que subir un archivo.',
            'archivo.mimes' => 'Formato inválido. Usá CSV o Excel.',
            'sede_id.required' => 'Seleccioná una sede.',
        ]);

        $file = $request->file('archivo');
        if (! $file) {
            throw ValidationException::withMessages(['archivo' => 'Archivo inválido.']);
        }

        $resultado = $importador->importar(
            $file,
            (int) $data['sede_id'],
            ! empty($data['bloque_id']) ? (int) $data['bloque_id'] : null
        );

        return redirect()
            ->route('alumnos.index')
            ->with('success', "Importación finalizada. Importados: {$resultado['importados']}. Omitidos: {$resultado['omitidos']}.")
            ->with('import_errors', $resultado['errores']);
    }

    private function alumnoPerteneceABloquesDelProfesor(Alumno $alumno, Profesor $profesor): bool
    {
        $ids = $profesor->bloqueIdsDondeParticipa()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return false;
        }

        return $alumno->bloqueIds()->intersect($ids)->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Profesor>
     */
    private function profesoresDisponiblesParaVinculo(?Alumno $exceptoAlumno = null): \Illuminate\Support\Collection
    {
        try {
            $q = Profesor::query()->where('activo', true)->orderBy('nombre');
            if ($exceptoAlumno?->profesorPerfil()) {
                $q->where('id', '!=', $exceptoAlumno->profesorPerfil()->id);
            }

            return $q->get();
        } catch (QueryException $e) {
            return collect();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Sede>  $sedes
     * @param  \Illuminate\Support\Collection<int, Bloque>  $bloques
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function acotarSedesYBloques($sedes, $bloques): array
    {
        $alcance = auth()->user()->acceso()->alcance('alumnos.create')->unir(auth()->user()->acceso()->alcance('alumnos.update'));
        if ($alcance->esGlobal()) {
            return [$sedes, $bloques];
        }
        $sedesIds = $alcance->sedesTocadas();
        $bloques = $bloques->filter(fn ($b) => $alcance->incluyeBloque((int) $b->id, (int) $b->sede_id))->values();

        return [$sedes->whereIn('id', $sedesIds)->values(), $bloques];
    }
}
