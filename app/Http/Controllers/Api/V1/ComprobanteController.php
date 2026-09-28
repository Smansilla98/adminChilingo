<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\ComprobanteService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ComprobanteCuotaAlumnoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprobantes de cuota: gestión (revisar, marcar visto, aprobar = registrar el pago,
 * cargar en nombre de un alumno) y envío por parte del propio alumno desde la app
 * (reemplaza al formulario público web). Mismas reglas que el panel web.
 */
class ComprobanteController extends Controller
{
    public function index(Request $request, ComprobanteService $servicio): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('comprobantes.view'), 403);
        $query = $servicio->consulta($request->user())->with(['alumno:id,nombre_apellido,persona_id', 'sede:id,nombre', 'items.bloque:id,nombre', 'items.cuota:id,nombre'])->orderByDesc('created_at');
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('alumno_id')) {
            $query->where('alumno_id', $request->integer('alumno_id'));
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->whereHas('alumno', fn ($a) => $a->where('nombre_apellido', 'like', $t)->orWhere('dni', 'like', $t));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (ComprobanteCuotaAlumno $c) => $this->comprobante($request, $c, false))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
            'pendientes' => (clone $servicio->consulta($request->user()))->where('estado', 'pendiente')->count(),
        ]);
    }

    public function show(Request $request, ComprobanteCuotaAlumno $comprobante, ComprobanteService $servicio): JsonResponse
    {
        abort_unless($this->puedeVerOPropio($request, $comprobante, $servicio), 403);

        return response()->json(['data' => $this->comprobante($request, $comprobante->load(['alumno', 'sede', 'items.bloque', 'items.cuota', 'pago']), true)]);
    }

    public function archivo(Request $request, ComprobanteCuotaAlumno $comprobante, ComprobanteService $servicio): StreamedResponse
    {
        abort_unless($this->puedeVerOPropio($request, $comprobante, $servicio), 403);
        abort_unless($comprobante->comprobante_path, 404, 'El comprobante no tiene archivo.');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('comprobantes');
        abort_unless($disk->exists($comprobante->comprobante_path), 404, 'El archivo ya no está disponible.');
        $ext = strtolower((string) pathinfo($comprobante->comprobante_path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }

        return $disk->response($comprobante->comprobante_path, 'comprobante-alumno-'.$comprobante->id.'.'.$ext);
    }

    /** Carga desde la escuela en nombre de un alumno. */
    public function store(Request $request, ComprobanteService $servicio): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $alumno = Alumno::query()->findOrFail($datos['alumno_id']);
        $c = $servicio->cargarPorGestion($request->user(), $alumno, (int) $datos['anio'], (int) $datos['mes'], $datos['fecha_pago'], array_map('intval', $datos['bloque_ids']), $request->file('comprobante'), $datos['notas'] ?? null);

        return response()->json(['data' => $this->comprobante($request, $c->load(['alumno', 'sede', 'items.bloque', 'items.cuota']), true)], 201);
    }

    public function visto(Request $request, ComprobanteCuotaAlumno $comprobante, ComprobanteService $servicio): JsonResponse
    {
        abort_unless($servicio->puedeVer($request->user(), $comprobante), 403);
        $servicio->marcarVisto($comprobante);

        return response()->json(['data' => $this->comprobante($request, $comprobante->fresh(['alumno', 'sede', 'items.bloque', 'items.cuota', 'pago']), true)]);
    }

    /** Aprobar = registrar el pago con las cuotas del comprobante. */
    public function aprobar(Request $request, ComprobanteCuotaAlumno $comprobante, ComprobanteService $servicio): JsonResponse
    {
        abort_unless($servicio->puedeAprobar($request->user(), $comprobante), 403, 'No podés registrar el pago de este comprobante.');
        $data = $request->validate(['liquidar_profesor' => 'nullable|boolean']);
        try {
            $r = $servicio->aprobar($comprobante, $request->user(), (bool) ($data['liquidar_profesor'] ?? true));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['mensaje' => $r['mensaje'], 'pago_id' => $r['pago']->id, 'data' => $this->comprobante($request, $comprobante->fresh(['alumno', 'sede', 'items.bloque', 'items.cuota', 'pago']), true)]);
    }

    /** Bloques del alumno con su cuota del período (para el formulario). */
    public function opciones(Request $request, ComprobanteService $servicio): JsonResponse
    {
        $data = $request->validate(['alumno_id' => 'required|integer|exists:alumnos,id', 'anio' => 'required|integer|min:2000|max:2100', 'mes' => 'required|integer|min:1|max:12']);
        $alumno = Alumno::query()->findOrFail($data['alumno_id']);
        $user = $request->user();
        $propio = ($user->persona_id && (int) $alumno->persona_id === (int) $user->persona_id) || (int) $alumno->user_id === (int) $user->id;
        abort_unless($propio || $user->acceso()->puedeSobreAlumno('comprobantes.create', $alumno), 403);

        return response()->json(['alumno' => ['id' => $alumno->id, 'nombre' => $alumno->nombre_apellido], 'bloques' => $servicio->opciones($alumno, (int) $data['anio'], (int) $data['mes'])]);
    }

    /** El alumno envía su comprobante desde la app. */
    public function enviarPropio(Request $request, ComprobanteService $servicio): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $alumno = Alumno::query()->findOrFail($datos['alumno_id']);
        $c = $servicio->enviarPropio($request->user(), $alumno, (int) $datos['anio'], (int) $datos['mes'], $datos['fecha_pago'], array_map('intval', $datos['bloque_ids']), $request->file('comprobante'), $datos['notas'] ?? null);

        return response()->json(['data' => $this->comprobante($request, $c->load(['alumno', 'sede', 'items.bloque', 'items.cuota']), true)], 201);
    }

    /** Comprobantes enviados por la persona logueada (sus fichas de alumno). */
    public function mios(Request $request): JsonResponse
    {
        $user = $request->user();
        $comprobantes = ComprobanteCuotaAlumno::query()
            ->whereHas('alumno', fn ($a) => $a->where(fn ($w) => $w->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id)))
            ->with(['alumno:id,nombre_apellido', 'sede:id,nombre', 'items.bloque:id,nombre', 'items.cuota:id,nombre'])
            ->orderByDesc('created_at')->limit(50)->get();

        return response()->json(['data' => $comprobantes->map(fn ($c) => $this->comprobante($request, $c, false))->values()]);
    }

    /** @return array<string, mixed> */
    private function reglas(): array
    {
        return [
            'alumno_id' => 'required|integer|exists:alumnos,id',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'fecha_pago' => 'required|date',
            'bloque_ids' => 'required|array|min:1',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notas' => 'nullable|string|max:1000',
        ];
    }

    private function puedeVerOPropio(Request $request, ComprobanteCuotaAlumno $c, ComprobanteService $servicio): bool
    {
        $user = $request->user();
        $c->loadMissing('alumno');
        $propio = $c->alumno && (($user->persona_id && (int) $c->alumno->persona_id === (int) $user->persona_id) || (int) $c->alumno->user_id === (int) $user->id);

        return $propio || ($user->acceso()->puede('comprobantes.view') && $servicio->puedeVer($user, $c));
    }

    /** @return array<string, mixed> */
    private function comprobante(Request $request, ComprobanteCuotaAlumno $c, bool $detalle): array
    {
        $servicio = app(ComprobanteService::class);
        $user = $request->user();
        $gestiona = $user->acceso()->puede('comprobantes.view') && $servicio->puedeVer($user, $c);

        return [
            'id' => $c->id,
            'estado' => $c->estado,
            'estado_nombre' => $c->etiquetaEstado(),
            'fecha_pago' => $c->fecha_pago?->toDateString(),
            'monto_total' => (float) $c->monto_total,
            'notas' => $c->notas,
            'enviado_at' => $c->created_at?->toIso8601String(),
            'alumno' => $c->alumno ? ['id' => $c->alumno->id, 'nombre' => $c->alumno->nombre_apellido, 'persona_id' => $c->alumno->persona_id] : null,
            'sede' => $c->sede?->nombre,
            'items' => $c->items->map(fn (ComprobanteCuotaAlumnoItem $i) => ['bloque' => $i->bloque?->nombre, 'cuota' => $i->cuota?->nombre, 'cuota_id' => $i->cuota_id, 'monto' => (float) $i->monto])->values(),
            'pago_id' => $c->pago_id,
            'tiene_archivo' => (bool) $c->comprobante_path,
            'acciones' => $detalle ? [
                'marcar_visto' => $gestiona && $c->estaPendiente(),
                'aprobar' => ! $c->estaPagado() && $servicio->puedeAprobar($user, $c),
            ] : null,
        ];
    }
}
