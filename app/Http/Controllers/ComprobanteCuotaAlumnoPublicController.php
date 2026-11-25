<?php

namespace App\Http\Controllers;

use App\Domain\Pagos\BuscadorCuotaPublica;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Sede;
use App\Services\ComprobanteCuotaRegistroService;
use App\Support\PagoCuotaToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Formulario público (sin cuenta) para mandar el comprobante de una cuota. Se puede
 * apagar con ACTIVAR_FORMULARIO_PUBLICO_COMPROBANTES=false: la app y la web con
 * cuenta ya cubren el mismo flujo. La búsqueda por DNI nunca muestra el nombre
 * completo (solo iniciales enmascaradas) y tiene trampa para robots y límite de uso.
 */
class ComprobanteCuotaAlumnoPublicController extends Controller
{
    /** Campo oculto que una persona deja vacío; los robots lo completan. */
    public const HONEYPOT = 'sitio_web';

    public static function activo(): bool
    {
        return (bool) config('chilinga.formulario_publico_comprobantes', true);
    }

    /** "Juan Pérez" → "J*** P***". */
    public static function enmascarar(string $nombre): string
    {
        return BuscadorCuotaPublica::enmascarar($nombre);
    }

    private function asegurarActivo(): void
    {
        if (! self::activo()) {
            abort(404);
        }
    }

    private function esRobot(Request $request): bool
    {
        return filled($request->input(self::HONEYPOT));
    }

    public function create(Request $request)
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            abort(503, 'Función no disponible: ejecutá migraciones.');
        }
        if (! self::activo()) {
            return response()->view('comprobante_cuota_public.cerrado', [], 404);
        }

        $sedes = Sede::query()
            ->where('activo', true)
            ->whereHas('bloques', function ($q) {
                $q->where('bloques.activo', true);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $prefill = [
            'sede_id' => $request->query('sede_id', old('sede_id')),
            'dni' => $request->query('dni', old('dni')),
        ];

        // Si hay sesión de alumno, sugerir DNI/sede sin exponer padrón.
        if (auth()->check() && auth()->user()->isAlumno() && auth()->user()->alumno) {
            $alu = auth()->user()->alumno;
            $prefill['dni'] = $prefill['dni'] ?: $alu->dni;
            $prefill['sede_id'] = $prefill['sede_id'] ?: $alu->sede_id;
        }

        return view('comprobante_cuota_public.create', compact('sedes', 'prefill'));
    }

    public function apiPeriodos(Request $request): JsonResponse
    {
        $this->asegurarActivo();
        $request->validate(['sede_id' => 'required|exists:sedes,id']);

        if (\Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance')) {
            $sid = $request->integer('sede_id');
            $rows = Cuota::query()
                ->select(['cuotas.año', 'cuotas.mes'])
                ->whereNotNull('cuotas.mes')
                ->where(function ($q) use ($sid) {
                    $q->where(function ($q2) use ($sid) {
                        $q2->where('cuotas.alcance', Cuota::ALCANCE_BLOQUE)
                            ->whereHas('bloque', fn ($b) => $b->where('bloques.sede_id', $sid)->where('bloques.activo', true));
                    })
                        ->orWhere(function ($q3) use ($sid) {
                            $q3->where('cuotas.alcance', Cuota::ALCANCE_SEDE)
                                ->where('cuotas.sede_id', $sid);
                        })
                        ->orWhere('cuotas.alcance', Cuota::ALCANCE_GENERAL);
                })
                ->groupBy('cuotas.año', 'cuotas.mes')
                ->orderByDesc('cuotas.año')
                ->orderByDesc('cuotas.mes')
                ->get();
        } else {
            $rows = Cuota::query()
                ->select(['cuotas.año', 'cuotas.mes'])
                ->join('bloques', 'cuotas.bloque_id', '=', 'bloques.id')
                ->where('bloques.sede_id', $request->integer('sede_id'))
                ->where('bloques.activo', true)
                ->whereNotNull('cuotas.mes')
                ->groupBy('cuotas.año', 'cuotas.mes')
                ->orderByDesc('cuotas.año')
                ->orderByDesc('cuotas.mes')
                ->get();
        }

        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $data = $rows->map(function ($r) use ($meses) {
            $m = (int) $r->mes;
            $label = ($meses[$m] ?? "Mes {$m}").' '.$r->año;

            return [
                'año' => (int) $r->año,
                'mes' => $m,
                'label' => $label,
            ];
        })->unique(fn ($x) => $x['año'].'-'.$x['mes'])->values();

        return response()->json(['periodos' => $data]);
    }

    public function apiBloques(Request $request): JsonResponse
    {
        $this->asegurarActivo();
        $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
        ]);

        $año = $request->integer('año');
        $mes = $request->integer('mes');

        $bloques = Bloque::query()
            ->where('sede_id', $request->integer('sede_id'))
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sede_id']);

        $data = $bloques->map(function (Bloque $b) use ($año, $mes) {
            $cuota = Cuota::resolveForBloque((int) $b->id, $año, $mes);

            return [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'cuota_id' => $cuota?->id,
                'monto' => $cuota ? (float) $cuota->monto : null,
                'cuota_nombre' => $cuota?->nombre,
            ];
        })->filter(fn ($row) => $row['cuota_id'] !== null)->values();

        return response()->json(['bloques' => $data]);
    }

    public function apiAlumnos(Request $request, BuscadorCuotaPublica $buscador): JsonResponse
    {
        $this->asegurarActivo();
        $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'bloque_ids' => 'required|array|min:1',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'dni' => 'required|string|min:6|max:20',
        ]);

        $bloqueIds = array_map('intval', $request->input('bloque_ids', []));

        // Robot: misma respuesta que "no encontrado", sin consultar el padrón.
        if ($this->esRobot($request)) {
            return response()->json([
                'alumnos' => [],
                'puede_multibloque' => count($bloqueIds) > 1,
                'nota_multibloque' => 'No encontramos un alumno activo con ese DNI en los bloques elegidos (o la cuota ya figura paga).',
            ]);
        }

        $resultado = $buscador->alumnosConDeuda(
            $request->integer('sede_id'),
            $request->integer('año'),
            $request->integer('mes'),
            $bloqueIds,
            (string) $request->input('dni'),
        );

        return response()->json($resultado['body'], $resultado['status']);
    }

    public function apiOtrosBloquesAlumno(Request $request, BuscadorCuotaPublica $buscador): JsonResponse
    {
        $this->asegurarActivo();
        $request->validate([
            'alumno_id' => 'required|exists:alumnos,id',
            'lookup_token' => 'required|string',
            'sede_id' => 'required|exists:sedes,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
        ]);
        $payload = PagoCuotaToken::leer($request->input('lookup_token'));
        if (! $payload || $payload['alumno_id'] !== $request->integer('alumno_id') || $payload['sede_id'] !== $request->integer('sede_id')) {
            return response()->json(['error' => 'La búsqueda venció. Volvé a ingresar el DNI.'], 422);
        }

        $alumno = Alumno::query()->findOrFail($request->integer('alumno_id'));

        return response()->json($buscador->otrosBloques(
            $alumno,
            $request->integer('sede_id'),
            $request->integer('año'),
            $request->integer('mes'),
        ));
    }

    public function store(Request $request, ComprobanteCuotaRegistroService $registro)
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            abort(503, 'Función no disponible: ejecutá migraciones.');
        }
        $this->asegurarActivo();
        if ($this->esRobot($request)) {
            throw ValidationException::withMessages(['comprobante' => 'No pudimos recibir el comprobante. Probá de nuevo.']);
        }

        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'fecha_pago' => 'required|date',
            'alumno_id' => 'required|exists:alumnos,id',
            'dni' => 'required|string|min:6|max:20',
            'lookup_token' => 'required|string',
            'bloque_ids' => 'required|array|min:1',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notas' => 'nullable|string|max:1000',
        ]);

        $alumno = Alumno::query()->findOrFail($validated['alumno_id']);
        $token = PagoCuotaToken::leer($validated['lookup_token']);
        if (! $token || $token['alumno_id'] !== (int) $alumno->id || $token['sede_id'] !== (int) $validated['sede_id']) {
            throw ValidationException::withMessages(['dni' => 'La búsqueda venció. Volvé a buscar el DNI.']);
        }
        $dniIngresado = preg_replace('/\D+/', '', (string) $validated['dni']);
        $dniAlumno = preg_replace('/\D+/', '', (string) $alumno->dni);
        if ($dniAlumno === '' || $dniIngresado !== $dniAlumno) {
            throw ValidationException::withMessages(['dni' => 'El DNI no coincide con el alumno.']);
        }

        $registro->registrar(
            $alumno,
            (int) $validated['sede_id'],
            (int) $validated['año'],
            (int) $validated['mes'],
            $validated['fecha_pago'],
            $validated['bloque_ids'],
            $request->file('comprobante'),
            $validated['notas'] ?? null,
            null,
        );

        return redirect()->route('comprobante-cuota-public.create')
            ->with('success', 'Recibimos tu comprobante. Tu profesor o la administración lo verá en el panel.');
    }
}
