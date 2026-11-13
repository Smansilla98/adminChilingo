<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\PagosDocenteService;
use App\Domain\Finanzas\PagoService;
use App\Domain\Finanzas\ReciboPago;
use App\Domain\Finanzas\RegistrarPago;
use App\Domain\Finanzas\RegistroPagoService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PagoResource;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Services\AmbitoSedeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagos de cuotas: consulta, registro (con liquidación docente), edición, anulación y
 * comprobante. Mismas reglas que el panel web (RegistroPagoService, PagoService y
 * PagoPolicy). El registro acepta `client_uuid`: un reintento de la app no duplica.
 */
class PagoController extends Controller
{
    private const RELACIONES = ['detalles.alumno:id,nombre_apellido,persona_id', 'detalles.cuota:id,nombre,monto,mes,año', 'registradoPor:id,name', 'anuladoPor:id,name'];

    public function index(Request $request, AmbitoSedeService $ambito): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pago::class);
        $query = Pago::query()->with(self::RELACIONES)->orderByDesc('fecha_pago')->orderByDesc('id');
        $filtro = $ambito->idsPara($request->user(), 'pagos.view');
        if ($filtro !== null) {
            $ambito->aplicarPagos($query, $filtro);
        }
        if ($request->filled('alumno_id')) {
            $query->whereHas('detalles', fn ($q) => $q->where('alumno_id', $request->integer('alumno_id')));
        }
        if ($request->filled('cuota_id')) {
            $query->whereHas('detalles', fn ($q) => $q->where('cuota_id', $request->integer('cuota_id')));
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->whereHas('detalles.alumno', fn ($q) => $q->where('nombre_apellido', 'like', $t)->orWhere('dni', 'like', $t)->orWhere('telefono', 'like', $t));
        }
        if ($request->filled('desde')) {
            $query->where('fecha_pago', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->where('fecha_pago', '<=', $request->date('hasta'));
        }
        match ($request->input('estado')) {
            'vigentes' => $query->whereNull('anulado_at'),
            'anulados' => $query->whereNotNull('anulado_at'),
            default => null,
        };

        return PagoResource::collection($query->paginate(30));
    }

    public function show(Request $request, Pago $pago): JsonResponse
    {
        $this->authorize('view', $pago);

        return response()->json(['data' => $this->ficha($request, $pago)]);
    }

    public function store(Request $request, RegistrarPago $registrar): JsonResponse
    {
        $this->authorize('create', Pago::class);
        ['pago' => $pago, 'duplicado' => $duplicado] = $registrar->ejecutar(
            $request->except('comprobante'),
            $request->user(),
            $request->file('comprobante'),
            $request->header('Idempotency-Key'),
        );

        return response()->json(['data' => $this->ficha($request, $pago), 'duplicado' => $duplicado], $duplicado ? 200 : 201);
    }

    public function update(Request $request, Pago $pago, RegistroPagoService $servicio): JsonResponse
    {
        $this->authorize('update', $pago);
        $datos = $request->validate($servicio->reglas(true));
        $pago = $servicio->actualizar($pago, $datos, $request->user(), $request->file('comprobante'), $request->boolean('quitar_comprobante'));

        return response()->json(['data' => $this->ficha($request, $pago)]);
    }

    public function anular(Request $request, Pago $pago, PagoService $pagos): JsonResponse
    {
        $this->authorize('reverse', $pago);
        $data = $request->validate(['motivo' => 'required|string|min:5|max:500']);
        $pagos->anular($pago, $request->user(), $data['motivo']);

        return response()->json(['data' => $this->ficha($request, $pago->fresh())]);
    }

    public function comprobante(Pago $pago): StreamedResponse
    {
        $this->authorize('view', $pago);
        abort_unless($pago->comprobante_path, 404, 'El pago no tiene comprobante.');
        $ext = strtolower((string) pathinfo($pago->comprobante_path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('comprobantes');
        abort_unless($disk->exists($pago->comprobante_path), 404, 'El archivo ya no está disponible.');

        return $disk->response($pago->comprobante_path, 'comprobante-pago-'.$pago->id.'.'.$ext);
    }

    /** Recibo del pago en PDF. */
    public function recibo(Pago $pago): Response
    {
        $this->authorize('view', $pago);

        return response(ReciboPago::pdf($pago), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.ReciboPago::nombreArchivo($pago).'"',
        ]);
    }

    /** Cuotas que puede cobrar, con el abono docente de referencia. */
    public function cuotasParaCobrar(Request $request, RegistroPagoService $servicio): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['pagos.create', 'pagos.update']), 403);
        $cuotas = $servicio->cuotasParaCobrar($request->user());
        if ($request->filled('anio')) {
            $cuotas = $cuotas->where('anio', $request->integer('anio'))->values();
        }
        if ($request->boolean('solo_activas')) {
            $cuotas = $cuotas->where('activo', true)->values();
        }

        return response()->json(['data' => $cuotas]);
    }

    /** Alumnos a los que aplica la cuota y todavía no la pagaron. */
    public function alumnosParaCuota(Request $request, Cuota $cuota, RegistroPagoService $servicio): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['pagos.create', 'pagos.update']), 403);
        $alumnos = collect($servicio->alumnosParaCuota($cuota, $request->filled('pago_id') ? $request->integer('pago_id') : null));
        if ($request->filled('q')) {
            $t = mb_strtolower(trim((string) $request->input('q')));
            $alumnos = $alumnos->filter(fn ($a) => str_contains(mb_strtolower($a['nombre_apellido']), $t))->values();
        }

        return response()->json(['data' => $alumnos->take(200)->values()]);
    }

    /** Pagos de alumnos de sus bloques, con el abono que le corresponde al docente. */
    public function misPagosDocente(Request $request, PagosDocenteService $servicio): JsonResponse
    {
        $profesor = $request->user()->profesor;
        abort_unless($profesor && $request->user()->acceso()->puede('pagos.view'), 403, 'Solo para docentes.');
        $q = $servicio->lineas($profesor, $request->only(['alumno_id', 'desde', 'hasta']));
        if (! $q) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0], 'total_abono' => 0]);
        }
        $totalAbono = (float) (clone $q)->whereNull('pagos.anulado_at')->sum('pago_detalles.abono_profesor');
        $pagina = $q->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (PagoDetalle $d) => [
                'id' => $d->id,
                'pago_id' => $d->pago_id,
                'fecha' => $d->pago?->fecha_pago?->toDateString(),
                'alumno' => $d->alumno?->nombre_apellido,
                'cuota' => $d->cuota?->nombre,
                'monto' => (float) $d->monto,
                'abono_profesor' => $d->abono_profesor !== null ? (float) $d->abono_profesor : null,
                'anulado' => $d->pago?->anulado_at !== null,
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
            'total_abono' => round($totalAbono, 2),
            'alumnos' => $servicio->alumnos($profesor)->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombre_apellido])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, Pago $pago): array
    {
        $pago->loadMissing(self::RELACIONES);
        $user = $request->user();
        $base = (new PagoResource($pago))->toArray($request);
        $base['detalles'] = $pago->detalles->map(fn (PagoDetalle $d) => [
            'id' => $d->id,
            'alumno_id' => $d->alumno_id,
            'alumno' => $d->alumno?->nombre_apellido,
            'persona_id' => $d->alumno?->persona_id,
            'cuota_id' => $d->cuota_id,
            'cuota' => $d->cuota?->nombre,
            'cuota_monto' => $d->cuota ? (float) $d->cuota->monto : null,
            'monto' => (float) $d->monto,
            'abono_profesor' => $d->abono_profesor !== null ? (float) $d->abono_profesor : null,
            'abono_nota' => $d->abono_nota,
        ])->values();

        return $base + [
            'tiene_comprobante' => (bool) $pago->comprobante_path,
            'anulado_at' => $pago->anulado_at?->toIso8601String(),
            'anulado_por' => $pago->anuladoPor?->name,
            'total_abono_profesor' => round((float) $pago->detalles->sum('abono_profesor'), 2),
            'acciones' => [
                'editar' => $user->can('update', $pago),
                'anular' => $user->can('reverse', $pago),
            ],
        ];
    }
}
