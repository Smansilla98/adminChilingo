<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\RegistroPagoService;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Pago;
use App\Services\AmbitoSedeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PagoController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito)
    {
        $pagos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        $alumnos = collect();
        $cuotas = collect();
        $filtroSedes = $ambito->idsPara(auth()->user(), 'pagos.view');

        if (Schema::hasTable('pagos')) {
            try {
                $query = Pago::with(['detalles.alumno', 'detalles.cuota', 'registradoPor']);
                if ($filtroSedes !== null) {
                    $ambito->aplicarPagos($query, $filtroSedes);
                }
                if ($request->filled('alumno_id')) {
                    $query->whereHas('detalles', fn ($q) => $q->where('alumno_id', $request->alumno_id));
                }
                if ($request->filled('alumno')) {
                    $term = trim((string) $request->input('alumno'));
                    $query->whereHas('detalles.alumno', function ($q) use ($term) {
                        $q->where('nombre_apellido', 'like', '%'.$term.'%')
                            ->orWhere('dni', 'like', '%'.$term.'%')
                            ->orWhere('telefono', 'like', '%'.$term.'%');
                    });
                }
                if ($request->filled('cuota_id')) {
                    $query->whereHas('detalles', fn ($q) => $q->where('cuota_id', $request->cuota_id));
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

        if (Schema::hasTable('cuotas')) {
            try {
                $qCuotas = Cuota::query();
                if ($filtroSedes !== null) {
                    $ambito->aplicarCuotas($qCuotas, $filtroSedes);
                }
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
        return view('pagos.create', $this->pagoFormViewData());
    }

    public function edit(Pago $pago)
    {
        $this->authorize('update', $pago);
        $pago->load(['detalles.alumno', 'detalles.cuota']);

        return view('pagos.create', array_merge($this->pagoFormViewData(), [
            'pago' => $pago,
        ]));
    }

    /**
     * @return array{cuotas: \Illuminate\Support\Collection, bloquesFiltro: \Illuminate\Support\Collection, cuotasMeta: array<int, array<string, mixed>>}
     */
    private function pagoFormViewData(): array
    {
        $ambito = app(AmbitoSedeService::class);
        $filtroSedes = $ambito->idsPara(auth()->user(), 'pagos.view');

        $cuotas = collect();
        if (Schema::hasTable('cuotas')) {
            try {
                $q = Cuota::query();
                if ($filtroSedes !== null) {
                    $ambito->aplicarCuotas($q, $filtroSedes);
                }
                if (Schema::hasColumn('cuotas', 'activo')) {
                    $q->orderBy('activo', 'desc');
                }
                $cuotas = $q->with(['bloque.sede', 'bloque.profesor', 'sede'])->orderBy('año', 'desc')->orderBy('mes', 'desc')->orderBy('id', 'desc')->get();
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
        if ($bloquesFiltro->isEmpty() && Schema::hasColumn('cuotas', 'alcance')) {
            try {
                $bloquesQ = Bloque::query()
                    ->where('activo', true)
                    ->with('sede')
                    ->orderBy('nombre');
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($bloquesQ, $filtroSedes);
                }
                $bloquesFiltro = $bloquesQ->get();
            } catch (\Throwable $e) {
                $bloquesFiltro = collect();
            }
        }

        try {
            $cuotasMeta = app(RegistroPagoService::class)->cuotasParaCobrar(auth()->user())->all();
        } catch (QueryException $e) {
            $cuotasMeta = [];
        }

        return compact('cuotas', 'bloquesFiltro', 'cuotasMeta');
    }

    /**
     * Alumnos que pueden sumarse a un pago para esta cuota: en el bloque (si aplica), no figuran en cuota_alumno excluidos,
     * y aún no tienen línea en pago_detalles para la misma cuota.
     */
    public function alumnosParaCuota(Request $request, RegistroPagoService $servicio): JsonResponse
    {
        $request->validate([
            'cuota_id' => 'required|exists:cuotas,id',
            'pago_id' => 'nullable|integer|exists:pagos,id',
        ]);

        try {
            $cuota = Cuota::query()->find($request->integer('cuota_id'));
            $alumnos = $cuota ? $servicio->alumnosParaCuota($cuota, $request->filled('pago_id') ? $request->integer('pago_id') : null) : [];
        } catch (Throwable $e) {
            report($e);
            $alumnos = [];
        }

        return response()->json(['alumnos' => $alumnos]);
    }

    public function store(Request $request, RegistroPagoService $servicio)
    {
        if (! $servicio->tablasDisponibles()) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para registrar pagos. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $this->normalizarAbono($request);
        $validated = $request->validate($servicio->reglas(false));
        $pago = $servicio->registrar($validated, $request->user(), $request->file('comprobante'));

        return redirect()->route('pagos.show', $pago)->with('success', 'Pago registrado correctamente.');
    }

    public function update(Request $request, Pago $pago, RegistroPagoService $servicio)
    {
        $this->authorize('update', $pago);
        if (! $servicio->tablasDisponibles()) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para actualizar pagos. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $this->normalizarAbono($request);
        $validated = $request->validate($servicio->reglas(true));
        $servicio->actualizar($pago, $validated, $request->user(), $request->file('comprobante'), $request->boolean('quitar_comprobante'));

        return redirect()->route('pagos.show', $pago)->with('success', 'Pago actualizado correctamente.');
    }

    /** El formulario manda el abono vacío cuando se usa la regla de la sede. */
    private function normalizarAbono(Request $request): void
    {
        if ($request->input('monto_abono_profesor', null) === '') {
            $request->merge(['monto_abono_profesor' => null]);
        }
    }

    public function show(Pago $pago)
    {
        $this->authorize('view', $pago);
        $pago->load(['detalles.alumno', 'detalles.cuota', 'registradoPor', 'anuladoPor']);

        return view('pagos.show', compact('pago'));
    }

    public function anular(Request $request, Pago $pago, \App\Domain\Finanzas\PagoService $pagos)
    {
        $this->authorize('reverse', $pago);
        $data = $request->validate(['motivo' => 'required|string|min:5|max:500']);
        $pagos->anular($pago, $request->user(), $data['motivo']);

        return redirect()->route('pagos.show', $pago)->with('success', 'Pago anulado. Ya no cuenta para saldos ni reportes.');
    }

    public function downloadComprobante(Pago $pago)
    {
        $this->authorize('view', $pago);
        if (! $pago->comprobante_path) {
            abort(404);
        }
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('comprobantes');
        $ext = strtolower((string) pathinfo($pago->comprobante_path, PATHINFO_EXTENSION));
        if ($ext === '' || ! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }
        $name = 'comprobante-pago-'.$pago->id.'.'.$ext;

        return $disk->response($pago->comprobante_path, $name);
    }
}
