<?php

namespace App\Domain\Reportes;

use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Gasto;
use App\Models\PagoDetalle;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cortes en el tiempo y deuda, para la pestaña «Tendencias y deuda» de Reportes.
 *
 * - Cobrado: por fecha de pago (lo que efectivamente entró ese mes), sin pagos anulados.
 * - Emitido: cuotas del mes × alumnos a los que aplican (aproximado, sin becas).
 * - Gastos: por fecha del gasto.
 * - Deuda: saldo real por alumno (con becas y pagos parciales) de cuotas ya vencidas.
 * Todo respeta el alcance por sede de quien mira (`null` = todas).
 */
class TendenciasService
{
    /**
     * Un punto por mes, del más viejo al más nuevo, terminando en $mes/$año.
     *
     * @param  list<int>|null  $sedes
     * @return list<array{mes: int, año: int, etiqueta: string, emitido: float, cobrado: float, gastos: float, resultado: float, cobranza: ?float}>
     */
    public function serie(int $mes, int $año, int $meses, ?array $sedes): array
    {
        $fin = Carbon::create($año, $mes, 1)->endOfMonth();
        $inicio = $fin->copy()->startOfMonth()->subMonths(max(1, $meses) - 1);

        $cobrado = PagoDetalle::query()
            ->join('pagos', 'pago_detalles.pago_id', '=', 'pagos.id')
            ->join('alumnos', 'pago_detalles.alumno_id', '=', 'alumnos.id')
            ->whereNull('pagos.anulado_at')
            ->whereBetween('pagos.fecha_pago', [$inicio->toDateString(), $fin->toDateString()])
            ->when($sedes !== null, fn ($q) => $q->whereIn('alumnos.sede_id', $sedes ?: [0]))
            ->toBase()
            ->get(['pagos.fecha_pago', 'pago_detalles.monto'])
            ->groupBy(fn ($r) => Carbon::parse($r->fecha_pago)->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('monto'));

        $gastos = Gasto::query()
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($sedes !== null, fn ($q) => $q->whereIn('sede_id', $sedes ?: [0]))
            ->get(['fecha', 'monto'])
            ->groupBy(fn ($g) => $g->fecha->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('monto'));

        $emitido = $this->emitidoPorMes($inicio, $fin, $sedes);

        $serie = [];
        for ($d = $inicio->copy(); $d->lte($fin); $d->addMonth()) {
            $clave = $d->format('Y-m');
            $e = round($emitido[$clave] ?? 0.0, 2);
            $c = round($cobrado[$clave] ?? 0.0, 2);
            $g = round($gastos[$clave] ?? 0.0, 2);
            $serie[] = [
                'mes' => (int) $d->month,
                'año' => (int) $d->year,
                'etiqueta' => ucfirst($d->locale('es')->translatedFormat('M y')),
                'emitido' => $e,
                'cobrado' => $c,
                'gastos' => $g,
                'resultado' => round($c - $g, 2),
                'cobranza' => $e > 0 ? round($c / $e * 100, 1) : null,
            ];
        }

        return $serie;
    }

    /**
     * El mes elegido contra el mes anterior y contra el mismo mes del año pasado.
     *
     * @param  list<int>|null  $sedes
     * @return array{actual: array, anterior: array, año_pasado: array}
     */
    public function comparativo(int $mes, int $año, ?array $sedes): array
    {
        $actual = $this->serie($mes, $año, 2, $sedes);
        $pasado = $this->serie($mes, $año - 1, 1, $sedes);

        return ['actual' => $actual[1], 'anterior' => $actual[0], 'año_pasado' => $pasado[0]];
    }

    /**
     * Deuda de cuotas vencidas por sede y por bloque, y quiénes deben más.
     *
     * @param  list<int>|null  $sedes
     * @return array{total: float, alumnos: int, por_sede: Collection, por_bloque: Collection, mayores: Collection}
     */
    public function morosidad(?array $sedes): array
    {
        $clave = 'reportes:morosidad:'.md5(json_encode($sedes)).':'.now()->format('Y-m-d-H');

        return Cache::remember($clave, 600, fn () => $this->calcularMorosidad($sedes));
    }

    /** @param  list<int>|null  $sedes */
    private function calcularMorosidad(?array $sedes): array
    {
        $hoy = Carbon::today();
        $estado = app(EstadoCuentaService::class);
        $alumnos = Alumno::query()->where('activo', true)
            ->when($sedes !== null, fn ($q) => $q->whereIn('sede_id', $sedes ?: [0]))
            ->get();

        $lineas = collect();
        foreach ($alumnos as $alumno) {
            foreach ([$hoy->year - 1, $hoy->year] as $anio) {
                foreach ($estado->paraAlumno($alumno, $anio)['items'] as $item) {
                    $vencida = $item['vencimiento']
                        ? Carbon::parse($item['vencimiento'])->lt($hoy)
                        : Carbon::create($item['anio'], $item['mes'] ?? 12, 1)->endOfMonth()->lt($hoy);
                    if ($item['saldo'] > 0 && $vencida) {
                        $lineas->push(['alumno' => $alumno, 'cuota_id' => $item['cuota_id'], 'saldo' => (float) $item['saldo']]);
                    }
                }
            }
        }

        $cuotas = Cuota::query()->whereIn('id', $lineas->pluck('cuota_id')->unique()->all() ?: [0])->get(['id', 'bloque_id', 'sede_id'])->keyBy('id');
        $bloques = Bloque::query()->whereIn('id', $cuotas->pluck('bloque_id')->filter()->unique()->all() ?: [0])->with('sede:id,nombre')->get()->keyBy('id');
        $nombresSede = Sede::query()->pluck('nombre', 'id');

        $lineas = $lineas->map(function ($l) use ($cuotas, $bloques) {
            $cuota = $cuotas->get($l['cuota_id']);
            $bloque = $cuota?->bloque_id ? $bloques->get($cuota->bloque_id) : null;
            $l['bloque'] = $bloque;
            $l['sede_id'] = $bloque->sede_id ?? $cuota->sede_id ?? $l['alumno']->sede_id;

            return $l;
        });

        $agrupar = fn (Collection $grupo) => [
            'total' => round((float) $grupo->sum('saldo'), 2),
            'alumnos' => $grupo->pluck('alumno.id')->unique()->count(),
            'cuotas' => $grupo->count(),
        ];

        return [
            'total' => round((float) $lineas->sum('saldo'), 2),
            'alumnos' => $lineas->pluck('alumno.id')->unique()->count(),
            'por_sede' => $lineas->groupBy('sede_id')
                ->map(fn ($g, $sid) => ['sede' => $nombresSede[$sid] ?? 'Sin sede'] + $agrupar($g))
                ->sortByDesc('total')->values(),
            'por_bloque' => $lineas->filter(fn ($l) => $l['bloque'] !== null)->groupBy(fn ($l) => $l['bloque']->id)
                ->map(fn ($g) => ['bloque' => $g->first()['bloque']->nombre, 'sede' => $g->first()['bloque']->sede?->nombre] + $agrupar($g))
                ->sortByDesc('total')->values(),
            'mayores' => $lineas->groupBy(fn ($l) => $l['alumno']->id)
                ->map(fn ($g) => ['alumno' => $g->first()['alumno'], 'total' => round((float) $g->sum('saldo'), 2), 'cuotas' => $g->count()])
                ->sortByDesc('total')->take(10)->values(),
        ];
    }

    /**
     * @param  list<int>|null  $sedes
     * @return array<string, float> 'YYYY-mm' => monto
     */
    private function emitidoPorMes(Carbon $inicio, Carbon $fin, ?array $sedes): array
    {
        $cuotas = Cuota::query()
            ->with(['alumnos:id', 'bloque:id,sede_id', 'bloque.alumnos' => fn ($q) => $q->where('alumnos.activo', true)->select('alumnos.id')])
            ->whereNotNull('mes')
            ->where(fn ($q) => $q->where('año', '>', $inicio->year)->orWhere(fn ($q) => $q->where('año', $inicio->year)->where('mes', '>=', $inicio->month)))
            ->where(fn ($q) => $q->where('año', '<', $fin->year)->orWhere(fn ($q) => $q->where('año', $fin->year)->where('mes', '<=', $fin->month)))
            ->get();

        $activosPorSede = Alumno::query()->where('activo', true)->selectRaw('sede_id, COUNT(*) as n')->groupBy('sede_id')->pluck('n', 'sede_id');

        $out = [];
        foreach ($cuotas as $cuota) {
            $alcance = $cuota->alcanceNormalizado();
            $bloque = $cuota->bloque;
            $sede = $bloque instanceof Bloque ? $bloque->sede_id : $cuota->sede_id;
            if ($sedes !== null && $alcance !== Cuota::ALCANCE_GENERAL && ! in_array($sede, $sedes, true)) {
                continue;
            }
            $objetivo = match (true) {
                $cuota->alumnos->isNotEmpty() => $cuota->alumnos->unique('id')->count(),
                $alcance === Cuota::ALCANCE_SEDE => (int) ($activosPorSede[$cuota->sede_id] ?? 0),
                $alcance === Cuota::ALCANCE_GENERAL => (int) ($sedes === null ? $activosPorSede->sum() : $activosPorSede->only($sedes)->sum()),
                default => $bloque instanceof Bloque ? $bloque->alumnos->unique('id')->count() : 0,
            };
            $clave = sprintf('%04d-%02d', $cuota->año, $cuota->mes);
            $out[$clave] = ($out[$clave] ?? 0.0) + (float) $cuota->monto * $objetivo;
        }

        return $out;
    }
}
