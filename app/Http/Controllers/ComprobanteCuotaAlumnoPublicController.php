<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Sede;
use App\Services\ComprobanteCuotaRegistroService;
use App\Support\PagoCuotaToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ComprobanteCuotaAlumnoPublicController extends Controller
{
    public function create()
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            abort(503, 'Función no disponible: ejecutá migraciones.');
        }

        $sedes = Sede::query()
            ->where('activo', true)
            ->whereHas('bloques', function ($q) {
                $q->where('bloques.activo', true);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('comprobante_cuota_public.create', compact('sedes'));
    }

    public function apiPeriodos(Request $request): JsonResponse
    {
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

    public function apiAlumnos(Request $request): JsonResponse
    {
        $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'bloque_ids' => 'required|array|min:1',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'dni' => 'required|string|min:6|max:20',
        ]);

        $bloqueIds = array_map('intval', $request->input('bloque_ids', []));
        $año = $request->integer('año');
        $mes = $request->integer('mes');
        $sedeId = $request->integer('sede_id');

        $bloques = Bloque::query()
            ->whereIn('id', $bloqueIds)
            ->where('sede_id', $sedeId)
            ->where('activo', true)
            ->get();
        if ($bloques->count() !== count(array_unique($bloqueIds))) {
            return response()->json(['error' => 'Bloques inválidos para la sede.'], 422);
        }

        foreach ($bloqueIds as $bid) {
            if (! $this->cuotaParaBloqueMes($bid, $año, $mes)) {
                return response()->json(['error' => 'No hay cuota cargada para uno de los bloques en ese mes.'], 422);
            }
        }

        $alumnosPorBloque = [];
        foreach ($bloqueIds as $bid) {
            $ids = Alumno::query()
                ->where('activo', true)
                ->where(function ($q) use ($bid) {
                    $q->whereHas('bloques', fn ($sub) => $sub->where('bloques.id', $bid))
                        ->orWhere('bloque_id', $bid);
                })
                ->pluck('id')
                ->all();
            $alumnosPorBloque[$bid] = $ids;
        }

        if ($alumnosPorBloque === []) {
            return response()->json(['alumnos' => []]);
        }

        $interseccion = null;
        foreach ($alumnosPorBloque as $ids) {
            $interseccion = $interseccion === null ? collect($ids) : $interseccion->intersect($ids);
        }
        $interseccion = $interseccion ?? collect();

        $cuotasPorBloque = [];
        foreach ($bloqueIds as $bid) {
            $cuotasPorBloque[$bid] = $this->cuotaParaBloqueMes((int) $bid, $año, $mes);
        }

        $dni = preg_replace('/\D+/', '', (string) $request->input('dni'));
        if (strlen($dni) < 6) {
            return response()->json(['error' => 'Ingresá el DNI completo (solo números).'], 422);
        }

        $alumnos = Alumno::query()
            ->whereIn('id', $interseccion->all())
            ->where('activo', true)
            ->whereRaw("REPLACE(REPLACE(REPLACE(dni, '.', ''), '-', ''), ' ', '') = ?", [$dni])
            ->orderBy('nombre_apellido')
            ->get(['id', 'nombre_apellido']);

        $data = $alumnos->filter(function (Alumno $a) use ($cuotasPorBloque) {
            foreach ($cuotasPorBloque as $cuota) {
                if ($cuota && ! $cuota->aplicaAAlumno($a)) {
                    return false;
                }
                if ($cuota && $this->alumnoYaPagoCuota((int) $a->id, (int) $cuota->id)) {
                    return false;
                }
            }

            return true;
        })->values()->map(fn (Alumno $a) => [
            'id' => $a->id,
            'nombre_apellido' => $a->nombre_apellido,
            'lookup_token' => PagoCuotaToken::emitir((int) $a->id, $sedeId),
        ]);

        return response()->json([
            'alumnos' => $data,
            'puede_multibloque' => count($bloqueIds) > 1,
            'nota_multibloque' => $data->isEmpty()
                ? 'No encontramos un alumno activo con ese DNI en los bloques elegidos (o la cuota ya figura paga).'
                : null,
        ]);
    }

    public function apiOtrosBloquesAlumno(Request $request): JsonResponse
    {
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
        $año = $request->integer('año');
        $mes = $request->integer('mes');
        $sedeId = $request->integer('sede_id');

        $bloqueIds = $alumno->bloques()
            ->where('bloques.sede_id', $sedeId)
            ->where('bloques.activo', true)
            ->pluck('bloques.id');
        if ($alumno->bloque_id) {
            $bid = (int) $alumno->bloque_id;
            $b = Bloque::query()->whereKey($bid)->where('sede_id', $sedeId)->where('activo', true)->first();
            if ($b) {
                $bloqueIds = $bloqueIds->push($bid);
            }
        }
        $bloqueIds = $bloqueIds->unique()->values();

        $extra = [];
        foreach ($bloqueIds as $bid) {
            $cuota = $this->cuotaParaBloqueMes((int) $bid, $año, $mes);
            if (! $cuota || ! $cuota->aplicaAAlumno($alumno)) {
                continue;
            }
            $bloque = $cuota->bloque;
            if (! $bloque || (int) $bloque->sede_id !== $sedeId) {
                continue;
            }
            if ($this->alumnoYaPagoCuota((int) $alumno->id, (int) $cuota->id)) {
                continue;
            }
            $extra[] = [
                'bloque_id' => (int) $bid,
                'bloque_nombre' => $bloque->nombre,
                'cuota_id' => $cuota->id,
                'monto' => (float) $cuota->monto,
                'cuota_nombre' => $cuota->nombre,
            ];
        }

        return response()->json(['bloques_cuotas' => $extra]);
    }

    public function store(Request $request, ComprobanteCuotaRegistroService $registro)
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            abort(503, 'Función no disponible: ejecutá migraciones.');
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

    private function cuotaParaBloqueMes(int $bloqueId, int $año, int $mes): ?Cuota
    {
        return Cuota::resolveForBloque($bloqueId, $año, $mes);
    }

    private function alumnoYaPagoCuota(int $alumnoId, int $cuotaId): bool
    {
        if (! Schema::hasTable('pago_detalles')) {
            return false;
        }

        return PagoDetalle::query()
            ->where('alumno_id', $alumnoId)
            ->where('cuota_id', $cuotaId)
            ->exists();
    }
}
