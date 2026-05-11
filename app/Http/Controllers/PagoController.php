<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Cuota;
use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Throwable;

class PagoController extends Controller
{
    public function index(Request $request)
    {
        $pagos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        $alumnos = collect();
        $cuotas = collect();

        if (Schema::hasTable('pagos')) {
            try {
                $query = Pago::with(['detalles.alumno', 'detalles.cuota', 'registradoPor']);
                if ($request->filled('alumno_id')) {
                    $query->whereHas('detalles', fn($q) => $q->where('alumno_id', $request->alumno_id));
                }
                if ($request->filled('cuota_id')) {
                    $query->whereHas('detalles', fn($q) => $q->where('cuota_id', $request->cuota_id));
                }
                if ($request->filled('desde')) {
                    $query->where('fecha_pago', '>=', $request->desde);
                }
                if ($request->filled('hasta')) {
                    $query->where('fecha_pago', '<=', $request->hasta);
                }
                $pagos = $query->orderBy('fecha_pago', 'desc')->paginate(20);
            } catch (QueryException $e) {
                // mantener paginador vacío
            }
        }

        if (Schema::hasTable('alumnos')) {
            try {
                $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }
        if (Schema::hasTable('cuotas')) {
            try {
                $qCuotas = Cuota::query();
                if (Schema::hasColumn('cuotas', 'activo')) {
                    $qCuotas->orderBy('activo', 'desc');
                }
                $cuotas = $qCuotas->orderBy('año', 'desc')->orderBy('mes', 'desc')->orderBy('id', 'desc')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('pagos.index', compact('pagos', 'alumnos', 'cuotas'));
    }

    public function create()
    {
        $cuotas = collect();
        if (Schema::hasTable('cuotas')) {
            try {
                $q = Cuota::query();
                if (Schema::hasColumn('cuotas', 'activo')) {
                    // Incluye retroactivos aunque estén inactivos, para poder registrarlos.
                    $q->orderBy('activo', 'desc');
                }
                $cuotas = $q->with(['bloque.sede'])->orderBy('año', 'desc')->orderBy('mes', 'desc')->orderBy('id', 'desc')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }
        $bloquesFiltro = $cuotas
            ->pluck('bloque')
            ->filter()
            ->unique('id')
            ->sortBy(fn ($b) => $b->nombre)
            ->values();

        return view('pagos.create', compact('cuotas', 'bloquesFiltro'));
    }

    /**
     * Alumnos que pueden sumarse a un pago para esta cuota: en el bloque (si aplica), no figuran en cuota_alumno excluidos,
     * y aún no tienen línea en pago_detalles para la misma cuota.
     */
    public function alumnosParaCuota(Request $request): JsonResponse
    {
        $request->validate([
            'cuota_id' => 'required|exists:cuotas,id',
        ]);

        if (! Schema::hasTable('pago_detalles') || ! Schema::hasTable('alumnos')) {
            return response()->json(['alumnos' => []]);
        }

        try {
            $cuota = Cuota::query()->with('bloque')->find($request->integer('cuota_id'));
            if (! $cuota) {
                return response()->json(['alumnos' => []]);
            }

            if (! $cuota->bloque_id) {
                return response()->json(['alumnos' => []]);
            }

            $query = Alumno::query()->where('activo', true)->orderBy('nombre_apellido')->with('sede');

            $bid = (int) $cuota->bloque_id;
            if (Schema::hasTable('alumno_bloque')) {
                $query->where(function ($q) use ($bid) {
                    $q->whereHas('bloques', fn ($sub) => $sub->where('bloques.id', $bid))
                        ->orWhere('bloque_id', $bid);
                });
            } else {
                $query->where('bloque_id', $bid);
            }

            if (Schema::hasTable('cuota_alumno')) {
                $idsSoloCuota = $cuota->alumnos()->pluck('alumnos.id');
                if ($idsSoloCuota->isNotEmpty()) {
                    $query->whereIn('alumnos.id', $idsSoloCuota->all());
                }
            }

            $yaPagaron = PagoDetalle::query()
                ->where('cuota_id', $cuota->id)
                ->pluck('alumno_id')
                ->unique()
                ->filter()
                ->values();
            if ($yaPagaron->isNotEmpty()) {
                $query->whereNotIn('alumnos.id', $yaPagaron->all());
            }

            $nombreBloque = $cuota->bloque?->nombre;
            $alumnos = $query->get(['alumnos.id', 'alumnos.nombre_apellido', 'alumnos.sede_id'])->map(fn (Alumno $a) => [
                'id' => $a->id,
                'nombre_apellido' => $a->nombre_apellido,
                'sede_nombre' => $a->sede?->nombre,
                'bloque_nombre' => $nombreBloque,
            ]);

            return response()->json(['alumnos' => $alumnos]);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['alumnos' => []]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['alumnos' => []]);
        }
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('pagos') || !Schema::hasTable('pago_detalles') || !Schema::hasTable('alumnos') || !Schema::hasTable('cuotas')) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para registrar pagos. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $validated = $request->validate([
            'fecha_pago' => 'required|date',
            'cuota_id' => 'required|exists:cuotas,id',
            'alumno_ids' => 'required|array|min:1',
            'alumno_ids.*' => 'exists:alumnos,id',
            'monto_total' => 'required|numeric|min:0',
            'comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notas' => 'nullable|string|max:1000',
        ]);

        $alumnoIds = array_values(array_filter($validated['alumno_ids']));
        if (empty($alumnoIds)) {
            return back()->withErrors(['alumno_ids' => 'Seleccione al menos un alumno.'])->withInput();
        }

        $cuota = Cuota::query()->findOrFail($validated['cuota_id']);
        foreach ($alumnoIds as $alumnoId) {
            if (PagoDetalle::query()->where('cuota_id', $cuota->id)->where('alumno_id', $alumnoId)->exists()) {
                return back()->withErrors([
                    'alumno_ids' => 'Uno de los alumnos ya tiene pago registrado para esta cuota. Elegí otra cuota o actualizá la lista de alumnos.',
                ])->withInput();
            }
            $alumno = Alumno::query()->find($alumnoId);
            if (! $alumno || ! $cuota->aplicaAAlumno($alumno)) {
                return back()->withErrors([
                    'alumno_ids' => 'Uno de los alumnos no corresponde a esta cuota o al bloque.',
                ])->withInput();
            }
        }

        $path = null;
        if ($request->hasFile('comprobante')) {
            $upload = $request->file('comprobante');
            $ext = strtolower((string) $upload->getClientOriginalExtension());
            if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $ext = strtolower((string) ($upload->guessExtension() ?: 'pdf'));
            }
            if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $ext = 'pdf';
            }
            $path = $upload->storeAs('pagos', (string) Str::uuid() . '.' . $ext, 'comprobantes');
        }

        $pago = Pago::create([
            'fecha_pago' => $validated['fecha_pago'],
            'monto_total' => $validated['monto_total'],
            'comprobante_path' => $path,
            'notas' => $validated['notas'] ?? null,
            'registrado_por' => auth()->id(),
        ]);

        $montoPorAlumno = round((float) $validated['monto_total'] / count($alumnoIds), 2);
        foreach ($alumnoIds as $alumnoId) {
            PagoDetalle::create([
                'pago_id' => $pago->id,
                'alumno_id' => $alumnoId,
                'cuota_id' => $validated['cuota_id'],
                'monto' => $montoPorAlumno,
            ]);
        }

        return redirect()->route('pagos.index')->with('success', 'Pago registrado correctamente.');
    }

    public function show(Pago $pago)
    {
        $pago->load(['detalles.alumno', 'detalles.cuota', 'registradoPor']);
        return view('pagos.show', compact('pago'));
    }

    public function downloadComprobante(Pago $pago)
    {
        if (!$pago->comprobante_path) {
            abort(404);
        }
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('comprobantes');
        $ext = strtolower((string) pathinfo($pago->comprobante_path, PATHINFO_EXTENSION));
        if ($ext === '' || ! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }
        $name = 'comprobante-pago-' . $pago->id . '.' . $ext;

        return $disk->response($pago->comprobante_path, $name);
    }
}
