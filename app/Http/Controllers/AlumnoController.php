<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AlumnosExport;
use Carbon\Carbon;

class AlumnoController extends Controller
{
    private const TIPOS_TAMBOR = [
        'Redoblante',
        'Repique',
        'Medio',
        'Fondo Agudo',
        'Fondo Grave',
        'Timbal',
        'Platillo',
        'Otro',
    ];

    private const TAMBOR_PROCEDENCIAS = [
        'Propio',
        'Sede',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Alumno::with(['bloque', 'bloques', 'sede']);

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
                $query->where(function($q) use ($search) {
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

            $alumnos = $query->orderBy('nombre_apellido')->paginate(20);
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
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
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;
        $tiposTambor = self::TIPOS_TAMBOR;
        $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;

        return view('alumnos.create', compact('sedes', 'bloques', 'instrumentos', 'tiposTambor', 'procedenciasTambor'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|unique:alumnos,dni|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'nullable|string|in:' . implode(',', self::TIPOS_TAMBOR),
            'tambor_procedencia' => 'nullable|string|in:' . implode(',', self::TAMBOR_PROCEDENCIAS),
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : true;

        $alumno = Alumno::create($validated);

        if (!empty($validated['bloque_id'])) {
            $alumno->bloques()->attach($validated['bloque_id'], ['es_principal' => true]);
        }

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Alumno $alumno)
    {
        $alumno->load(['bloque.profesor', 'bloques.profesor', 'sede', 'asistencias']);

        $bloquesIds = $alumno->bloques->pluck('id')->filter()->values();
        if ($bloquesIds->isEmpty() && $alumno->bloque_id) {
            $bloquesIds = collect([$alumno->bloque_id]);
        }

        $cuotas = $bloquesIds->isNotEmpty()
            ? Cuota::query()
                ->with(['alumnos:id'])
                ->whereIn('bloque_id', $bloquesIds->all())
                ->where('activo', true)
                ->orderByDesc('año')
                ->orderByDesc('mes')
                ->get()
            : collect();

        $cuotasAplicables = $cuotas->filter(function (Cuota $cuota) use ($alumno) {
            if ($cuota->alumnos->isEmpty()) {
                return true;
            }
            return $cuota->alumnos->contains('id', $alumno->id);
        })->values();

        $pagosPorCuota = $cuotasAplicables->isNotEmpty()
            ? PagoDetalle::query()
                ->with(['pago:id,fecha_pago'])
                ->where('alumno_id', $alumno->id)
                ->whereIn('cuota_id', $cuotasAplicables->pluck('id')->all())
                ->get()
                ->keyBy('cuota_id')
            : collect();

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
                'periodo' => ($cuota->mes ? str_pad((string) $cuota->mes, 2, '0', STR_PAD_LEFT) : '—') . '/' . ($cuota->año ?? '—'),
                'monto' => (float) $cuota->monto,
                'fecha_pago' => $fechaPago,
                'estado' => $estado,
                'estado_color' => $estadoColor,
            ];
        });

        return view('alumnos.show', compact('alumno', 'estadoCuenta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Alumno $alumno)
    {
        try {
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $bloques = collect();
        }
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;
        $tiposTambor = self::TIPOS_TAMBOR;
        $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;

        return view('alumnos.edit', compact('alumno', 'sedes', 'bloques', 'instrumentos', 'tiposTambor', 'procedenciasTambor'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Alumno $alumno)
    {
        $validated = $request->validate([
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|unique:alumnos,dni,' . $alumno->id . '|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'nullable|string|in:' . implode(',', self::TIPOS_TAMBOR),
            'tambor_procedencia' => 'nullable|string|in:' . implode(',', self::TAMBOR_PROCEDENCIAS),
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : ($request->has('activo') ? false : $alumno->activo);

        $alumno->update($validated);

        if (array_key_exists('bloque_id', $validated)) {
            $alumno->bloques()->sync(
                $validated['bloque_id']
                    ? [$validated['bloque_id'] => ['es_principal' => true]]
                    : []
            );
        }

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Alumno $alumno)
    {
        $alumno->delete();

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno eliminado exitosamente.');
    }

    /**
     * Exportar alumnos a Excel
     */
    public function export(Request $request)
    {
        return Excel::download(new AlumnosExport($request), 'alumnos_' . now()->format('Y-m-d') . '.xlsx');
    }
}
