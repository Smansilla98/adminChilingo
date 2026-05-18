<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Cuota;
use App\Models\Alumno;
use App\Models\Bloque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return view('pagos.create', $this->pagoFormViewData());
    }

    public function edit(Pago $pago)
    {
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
        $cuotas = collect();
        if (Schema::hasTable('cuotas')) {
            try {
                $q = Cuota::query();
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
                $bloquesFiltro = Bloque::query()
                    ->where('activo', true)
                    ->with('sede')
                    ->orderBy('nombre')
                    ->get();
            } catch (\Throwable $e) {
                $bloquesFiltro = collect();
            }
        }

        $cuotasMeta = $cuotas->map(fn (Cuota $c) => [
            'id' => $c->id,
            'monto' => (float) $c->monto,
            'label' => ($c->nombre_mes ? $c->nombre_mes.' '.$c->año.' — ' : '').$c->nombre.' — $ '.number_format((float) $c->monto, 2, ',', '.'),
            'alcance' => Schema::hasColumn('cuotas', 'alcance') ? ($c->alcance ?? 'bloque') : 'bloque',
            'bloque_id' => $c->bloque_id,
            'sede_id' => $c->sede_id,
            'activo' => (bool) ($c->activo ?? true),
        ])->values()->all();

        return compact('cuotas', 'bloquesFiltro', 'cuotasMeta');
    }

    /**
     * Alumnos que pueden sumarse a un pago para esta cuota: en el bloque (si aplica), no figuran en cuota_alumno excluidos,
     * y aún no tienen línea en pago_detalles para la misma cuota.
     */
    public function alumnosParaCuota(Request $request): JsonResponse
    {
        $request->validate([
            'cuota_id' => 'required|exists:cuotas,id',
            'pago_id' => 'nullable|integer|exists:pagos,id',
        ]);

        $exceptPagoId = $request->filled('pago_id') ? $request->integer('pago_id') : null;

        if (! Schema::hasTable('pago_detalles') || ! Schema::hasTable('alumnos')) {
            return response()->json(['alumnos' => []]);
        }

        try {
            $cuota = Cuota::query()->with(['bloque.sede', 'sede'])->find($request->integer('cuota_id'));
            if (! $cuota) {
                return response()->json(['alumnos' => []]);
            }

            if (! Schema::hasColumn('cuotas', 'alcance')) {
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
                $this->aplicarFiltroCuotaAlumnoPivot($cuota, $query);
                $this->excluirAlumnosYaPagaron($cuota, $query, $exceptPagoId);
                $ctx = $cuota->bloque?->nombre ?? '';

                return response()->json(['alumnos' => $this->mapearAlumnosRespuesta($query, $ctx)]);
            }

            $alcance = $cuota->alcanceNormalizado();
            $query = Alumno::query()->where('activo', true)->orderBy('nombre_apellido')->with('sede');

            if ($alcance === Cuota::ALCANCE_BLOQUE) {
                if (! $cuota->bloque_id) {
                    return response()->json(['alumnos' => []]);
                }
                $bid = (int) $cuota->bloque_id;
                if (Schema::hasTable('alumno_bloque')) {
                    $query->where(function ($q) use ($bid) {
                        $q->whereHas('bloques', fn ($sub) => $sub->where('bloques.id', $bid))
                            ->orWhere('bloque_id', $bid);
                    });
                } else {
                    $query->where('bloque_id', $bid);
                }
                $ctx = $cuota->bloque?->nombre ?? '';
            } elseif ($alcance === Cuota::ALCANCE_GENERAL) {
                $query->where(function ($q) {
                    $q->whereHas('bloques')->orWhereNotNull('bloque_id');
                });
                $ctx = 'Cuota general';
            } elseif ($alcance === Cuota::ALCANCE_SEDE && $cuota->sede_id) {
                $sid = (int) $cuota->sede_id;
                $query->where(function ($q) use ($sid) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sid))
                        ->orWhere('sede_id', $sid);
                });
                $ctx = $cuota->sede?->nombre ?? 'Sede';
            } else {
                return response()->json(['alumnos' => []]);
            }

            $this->aplicarFiltroCuotaAlumnoPivot($cuota, $query);
            $this->excluirAlumnosYaPagaron($cuota, $query, $exceptPagoId);

            return response()->json(['alumnos' => $this->mapearAlumnosRespuesta($query, $ctx)]);
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
        if (! $this->tablasPagoDisponibles()) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para registrar pagos. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $payload = $this->validarFormularioPago($request);
        if ($payload instanceof \Illuminate\Http\RedirectResponse) {
            return $payload;
        }

        $path = $this->resolverComprobantePath($request, null);

        $pago = Pago::create([
            'fecha_pago' => $payload['validated']['fecha_pago'],
            'monto_total' => $payload['validated']['monto_total'],
            'comprobante_path' => $path,
            'notas' => $payload['validated']['notas'] ?? null,
            'registrado_por' => auth()->id(),
        ]);

        $this->sincronizarDetallesPago(
            $pago,
            $payload['lineas'],
            $payload['cuotasPorId'],
            $payload['liquidarProfesor'],
            $payload['totalAbonoDocente']
        );

        return redirect()->route('pagos.show', $pago)->with('success', 'Pago registrado correctamente.');
    }

    public function update(Request $request, Pago $pago)
    {
        if (! $this->tablasPagoDisponibles()) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para actualizar pagos. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $payload = $this->validarFormularioPago($request, $pago->id);
        if ($payload instanceof \Illuminate\Http\RedirectResponse) {
            return $payload;
        }

        $path = $this->resolverComprobantePath($request, $pago->comprobante_path);

        DB::transaction(function () use ($pago, $payload, $path) {
            $pago->update([
                'fecha_pago' => $payload['validated']['fecha_pago'],
                'monto_total' => $payload['validated']['monto_total'],
                'comprobante_path' => $path,
                'notas' => $payload['validated']['notas'] ?? null,
            ]);

            $pago->detalles()->delete();

            $this->sincronizarDetallesPago(
                $pago,
                $payload['lineas'],
                $payload['cuotasPorId'],
                $payload['liquidarProfesor'],
                $payload['totalAbonoDocente']
            );
        });

        return redirect()->route('pagos.show', $pago)->with('success', 'Pago actualizado correctamente.');
    }

    private function tablasPagoDisponibles(): bool
    {
        return Schema::hasTable('pagos')
            && Schema::hasTable('pago_detalles')
            && Schema::hasTable('alumnos')
            && Schema::hasTable('cuotas');
    }

    /**
     * @return array{
     *     validated: array<string, mixed>,
     *     lineas: array<int, array<string, mixed>>,
     *     cuotasPorId: \Illuminate\Support\Collection<int, Cuota>,
     *     liquidarProfesor: bool,
     *     totalAbonoDocente: float|null
     * }|\Illuminate\Http\RedirectResponse
     */
    private function validarFormularioPago(Request $request, ?int $exceptPagoId = null)
    {
        if ($request->input('monto_abono_profesor', null) === '') {
            $request->merge(['monto_abono_profesor' => null]);
        }

        $rules = [
            'fecha_pago' => 'required|date',
            'lineas' => 'required|array|min:1',
            'lineas.*.alumno_id' => 'required|exists:alumnos,id',
            'lineas.*.cuota_id' => 'required|exists:cuotas,id',
            'lineas.*.monto' => 'required|numeric|min:0.01',
            'monto_total' => 'required|numeric|min:0.01',
            'monto_abono_profesor' => 'nullable|numeric|min:0',
            'liquidar_profesor' => 'nullable|in:0,1',
            'comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notas' => 'nullable|string|max:1000',
        ];
        if ($exceptPagoId !== null) {
            $rules['quitar_comprobante'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        $liquidarProfesor = ($validated['liquidar_profesor'] ?? '1') === '1';
        $lineas = array_values($validated['lineas']);

        $paresVistos = [];
        foreach ($lineas as $linea) {
            $par = $linea['alumno_id'].'-'.$linea['cuota_id'];
            if (isset($paresVistos[$par])) {
                return back()->withErrors([
                    'lineas' => 'Hay líneas duplicadas (mismo alumno y misma cuota). Unificá el monto en una sola línea o separá en otro pago.',
                ])->withInput();
            }
            $paresVistos[$par] = true;
        }

        $sumaLineas = round(array_sum(array_map(fn ($l) => (float) $l['monto'], $lineas)), 2);
        $montoTotal = round((float) $validated['monto_total'], 2);
        if (abs($sumaLineas - $montoTotal) > 0.02) {
            return back()->withErrors([
                'monto_total' => 'El monto total ($'.number_format($montoTotal, 2, ',', '.').') debe coincidir con la suma de las líneas ($'.number_format($sumaLineas, 2, ',', '.').').',
            ])->withInput();
        }

        $cuotaIds = array_values(array_unique(array_map(fn ($l) => (int) $l['cuota_id'], $lineas)));
        $cuotasPorId = Cuota::query()
            ->with(['bloque.sede', 'sede'])
            ->whereIn('id', $cuotaIds)
            ->get()
            ->keyBy('id');

        foreach ($lineas as $idx => $linea) {
            $cuota = $cuotasPorId->get((int) $linea['cuota_id']);
            if (! $cuota) {
                return back()->withErrors(['lineas.'.$idx.'.cuota_id' => 'Cuota no válida.'])->withInput();
            }
            $alumnoId = (int) $linea['alumno_id'];
            $duplicado = PagoDetalle::query()
                ->where('cuota_id', $cuota->id)
                ->where('alumno_id', $alumnoId);
            if ($exceptPagoId !== null) {
                $duplicado->where('pago_id', '!=', $exceptPagoId);
            }
            if ($duplicado->exists()) {
                return back()->withErrors([
                    'lineas.'.$idx.'.alumno_id' => 'Este alumno ya tiene pago registrado para la cuota elegida en esa línea (en otro pago).',
                ])->withInput();
            }
            $alumno = Alumno::query()->find($alumnoId);
            if (! $alumno || ! $cuota->aplicaAAlumno($alumno)) {
                return back()->withErrors([
                    'lineas.'.$idx.'.alumno_id' => 'El alumno de la línea '.($idx + 1).' no corresponde a la cuota según alcance / bloques.',
                ])->withInput();
            }
        }

        $totalAbonoDocente = $liquidarProfesor
            ? max(0.0, round((float) ($validated['monto_abono_profesor'] ?? 0), 2))
            : null;

        return [
            'validated' => $validated,
            'lineas' => $lineas,
            'cuotasPorId' => $cuotasPorId,
            'liquidarProfesor' => $liquidarProfesor,
            'totalAbonoDocente' => $totalAbonoDocente,
        ];
    }

    private function resolverComprobantePath(Request $request, ?string $pathAnterior): ?string
    {
        if ($request->boolean('quitar_comprobante') && $pathAnterior) {
            Storage::disk('comprobantes')->delete($pathAnterior);
            $pathAnterior = null;
        }

        if ($request->hasFile('comprobante')) {
            if ($pathAnterior) {
                Storage::disk('comprobantes')->delete($pathAnterior);
            }

            return $this->guardarArchivoComprobante($request->file('comprobante'));
        }

        return $pathAnterior;
    }

    private function guardarArchivoComprobante(\Illuminate\Http\UploadedFile $upload): string
    {
        $ext = strtolower((string) $upload->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = strtolower((string) ($upload->guessExtension() ?: 'pdf'));
        }
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }

        return $upload->storeAs('pagos', (string) Str::uuid().'.'.$ext, 'comprobantes');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotasPorId
     */
    private function sincronizarDetallesPago(
        Pago $pago,
        array $lineas,
        $cuotasPorId,
        bool $liquidarProfesor,
        ?float $totalAbonoDocente
    ): void {
        $montosLinea = array_map(fn ($l) => (float) $l['monto'], $lineas);
        $n = count($lineas);
        $abonosPorIndice = [];
        if ($liquidarProfesor && $totalAbonoDocente !== null && Schema::hasColumn('pago_detalles', 'abono_profesor') && $n > 0) {
            $abonosPorIndice = $this->distribuirAbonoDocenteProporcionalMontos($montosLinea, $totalAbonoDocente);
        }

        foreach ($lineas as $idx => $linea) {
            $cuota = $cuotasPorId->get((int) $linea['cuota_id']);
            $alumnoId = (int) $linea['alumno_id'];
            $montoLinea = round((float) $linea['monto'], 2);
            $cuotaRef = (float) $cuota->monto;
            $alumno = Alumno::query()->with('sede')->find($alumnoId);
            $sedeNombre = $cuota->bloque?->sede?->nombre
                ?? $cuota->sede?->nombre
                ?? $alumno?->sede?->nombre
                ?? '—';

            $det = [
                'pago_id' => $pago->id,
                'alumno_id' => $alumnoId,
                'cuota_id' => (int) $linea['cuota_id'],
                'monto' => $montoLinea,
            ];
            if ($liquidarProfesor && Schema::hasColumn('pago_detalles', 'abono_profesor')) {
                $abonoAl = (float) ($abonosPorIndice[$idx] ?? 0.0);
                $pctEf = $cuotaRef > 0 ? round(100 * $abonoAl / $cuotaRef, 4) : null;
                $det['abono_profesor'] = $abonoAl;
                $det['abono_base'] = $cuotaRef;
                $det['abono_porcentaje'] = $pctEf;
                $det['abono_nota'] = sprintf(
                    'Línea pago: abono docente $%s (reparto prop. al total docente $%s). Cuota ref. $%s. %% efectivo sobre cuota ref.: %s. Sede ref.: %s.',
                    number_format($abonoAl, 2, ',', '.'),
                    number_format((float) ($totalAbonoDocente ?? 0), 2, ',', '.'),
                    number_format($cuotaRef, 2, ',', '.'),
                    $pctEf !== null ? number_format((float) $pctEf, 2, ',', '.') : '—',
                    $sedeNombre
                );
                if (strlen((string) $det['abono_nota']) > 500) {
                    $det['abono_nota'] = substr((string) $det['abono_nota'], 0, 497).'...';
                }
            }
            PagoDetalle::create($det);
        }
    }

    /**
     * Reparte el abono total al docente entre líneas en proporción al monto de cada línea (centavos exactos).
     *
     * @param  array<int, float>  $montosLinea
     * @return array<int, float> abono por índice de línea
     */
    private function distribuirAbonoDocenteProporcionalMontos(array $montosLinea, float $totalAbono): array
    {
        $n = count($montosLinea);
        if ($n === 0) {
            return [];
        }
        $centTot = (int) round($totalAbono * 100);
        if ($centTot <= 0) {
            return array_fill(0, $n, 0.0);
        }
        $sumM = array_sum($montosLinea);
        if ($sumM <= 0) {
            return array_fill(0, $n, 0.0);
        }
        $out = [];
        $asignados = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $centLinea = $centTot - $asignados;
            } else {
                $centLinea = (int) floor(($centTot * $montosLinea[$i] / $sumM) + 1e-9);
                $asignados += $centLinea;
            }
            $out[$i] = $centLinea / 100.0;
        }

        return $out;
    }

    private function aplicarFiltroCuotaAlumnoPivot(Cuota $cuota, $query): void
    {
        if (Schema::hasTable('cuota_alumno')) {
            $idsSoloCuota = $cuota->alumnos()->pluck('alumnos.id');
            if ($idsSoloCuota->isNotEmpty()) {
                $query->whereIn('alumnos.id', $idsSoloCuota->all());
            }
        }
    }

    private function excluirAlumnosYaPagaron(Cuota $cuota, $query, ?int $exceptPagoId = null): void
    {
        if (! Schema::hasTable('pago_detalles')) {
            return;
        }
        $yaPagaron = PagoDetalle::query()
            ->where('cuota_id', $cuota->id)
            ->when($exceptPagoId !== null, fn ($q) => $q->where('pago_id', '!=', $exceptPagoId))
            ->pluck('alumno_id')
            ->unique()
            ->filter()
            ->values();
        if ($yaPagaron->isNotEmpty()) {
            $query->whereNotIn('alumnos.id', $yaPagaron->all());
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function mapearAlumnosRespuesta($query, string $etiquetaBloque): array
    {
        return $query->get(['alumnos.id', 'alumnos.nombre_apellido', 'alumnos.sede_id'])->map(fn (Alumno $a) => [
            'id' => $a->id,
            'nombre_apellido' => $a->nombre_apellido,
            'sede_nombre' => $a->sede?->nombre,
            'bloque_nombre' => $etiquetaBloque,
        ])->values()->all();
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
