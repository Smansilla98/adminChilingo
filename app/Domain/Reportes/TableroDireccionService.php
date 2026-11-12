<?php

namespace App\Domain\Reportes;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\Sede;
use App\Models\User;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de dirección: evolución de los últimos 12 meses, por sede o de toda la escuela.
 *
 * - Ingresos, gastos, margen, emitido y cobranza: TendenciasService::serie (cobrado por fecha de pago).
 * - Morosidad: TendenciasService::morosidad (deuda vencida real, ya cacheada).
 * - Altas: alumnos creados en el mes. Bajas: alumnos que pasaron a inactivos según la auditoría.
 * - Ocupación: inscriptos activos sobre el cupo (cantidad_max_alumnos) de los bloques activos.
 * - Asistencia: presentes sobre registros tomados (sin feriados ni días sin clase).
 * - Proyección: cobranza promedio de los últimos 3 meses × emitido del mes próximo
 *   (o cobrado promedio si todavía no hay cuotas) y gastos promedio. Es una estimación simple.
 * Todo con consultas agregadas y caché corta (5 minutos).
 */
class TableroDireccionService
{
    public const PERMISO = 'reportes.direccion';

    public function __construct(private TendenciasService $tendencias, private AmbitoSedeService $ambito) {}

    /** @return list<int>|null  null = toda la escuela */
    public function sedesPara(User $user, ?int $sedeId): ?array
    {
        $alcance = $this->ambito->idsPara($user, self::PERMISO);
        if ($sedeId) {
            abort_unless($alcance === null || in_array($sedeId, $alcance, true), 403, 'No tenés acceso a esa sede.');

            return [$sedeId];
        }

        return $alcance;
    }

    /** @return \Illuminate\Support\Collection<int, Sede> */
    public function sedesDisponibles(User $user)
    {
        $alcance = $this->ambito->idsPara($user, self::PERMISO);

        return Sede::query()->when($alcance !== null, fn ($q) => $q->whereIn('id', $alcance ?: [0]))->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** @return array<string, mixed> */
    public function datos(User $user, ?int $sedeId = null): array
    {
        $sedes = $this->sedesPara($user, $sedeId);
        $hoy = Carbon::today();
        $clave = 'direccion:tablero:'.md5((string) json_encode($sedes)).':'.$hoy->toDateString().':'.intdiv((int) now()->format('Gi'), 5);

        return Cache::remember($clave, 300, fn () => $this->calcular($sedes, $hoy));
    }

    /**
     * @param  list<int>|null  $sedes
     * @return array<string, mixed>
     */
    private function calcular(?array $sedes, Carbon $hoy): array
    {
        $serie = $this->tendencias->serie((int) $hoy->month, (int) $hoy->year, 12, $sedes);
        $inicio = Carbon::create((int) $serie[0]['año'], (int) $serie[0]['mes'], 1)->startOfMonth();
        $fin = $hoy->copy()->endOfMonth();

        $altas = $this->porMes($this->alumnos($sedes)->whereBetween('created_at', [$inicio, $fin])->pluck('created_at'));
        $bajas = $this->porMes($this->bajas($sedes, $inicio, $fin));
        $asistencia = $this->asistenciaPorMes($sedes, $inicio, $fin);

        foreach ($serie as &$p) {
            $k = sprintf('%04d-%02d', $p['año'], $p['mes']);
            $p['margen'] = $p['cobrado'] > 0 ? round($p['resultado'] / $p['cobrado'] * 100, 1) : null;
            $p['altas'] = $altas[$k] ?? 0;
            $p['bajas'] = $bajas[$k] ?? 0;
            $p['asistencia'] = $asistencia[$k] ?? null;
        }
        unset($p);

        $activos = $this->alumnos($sedes)->where('activo', true)->count();
        $bajas12 = array_sum($bajas);
        $altas12 = array_sum($altas);
        $morosidad = $this->tendencias->morosidad($sedes);
        $actual = $serie[count($serie) - 1];

        return [
            'alcance' => $sedes === null ? 'global' : 'sedes',
            'sedes' => $sedes,
            'generado' => now()->toIso8601String(),
            'serie' => $serie,
            'actual' => $actual,
            'totales_12m' => [
                'cobrado' => round(array_sum(array_column($serie, 'cobrado')), 2),
                'emitido' => round(array_sum(array_column($serie, 'emitido')), 2),
                'gastos' => round(array_sum(array_column($serie, 'gastos')), 2),
                'resultado' => round(array_sum(array_column($serie, 'resultado')), 2),
            ],
            'alumnos' => [
                'activos' => $activos,
                'altas_12m' => $altas12,
                'bajas_12m' => $bajas12,
                'retencion' => ($activos + $bajas12) > 0 ? round($activos / ($activos + $bajas12) * 100, 1) : null,
                'abandono' => ($activos + $bajas12) > 0 ? round($bajas12 / ($activos + $bajas12) * 100, 1) : null,
            ],
            'morosidad' => [
                'total' => $morosidad['total'],
                'alumnos' => $morosidad['alumnos'],
                'porcentaje_alumnos' => $activos > 0 ? round($morosidad['alumnos'] / $activos * 100, 1) : null,
                'por_sede' => collect($morosidad['por_sede'])->values()->all(),
            ],
            'ocupacion' => $this->ocupacion($sedes),
            'asistencia_promedio' => $this->promedio(array_filter(array_column($serie, 'asistencia'), fn ($v) => $v !== null)),
            'proyeccion' => $this->proyeccion($serie, $sedes, $hoy),
        ];
    }

    /**
     * @param  list<int>|null  $sedes
     * @return \Illuminate\Database\Eloquent\Builder<Alumno>
     */
    private function alumnos(?array $sedes)
    {
        return Alumno::query()->when($sedes !== null, fn ($q) => $q->whereIn('sede_id', $sedes ?: [0]));
    }

    /**
     * Fechas en que un alumno pasó a inactivo (auditoría de cambios del modelo Alumno).
     *
     * @param  list<int>|null  $sedes
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function bajas(?array $sedes, Carbon $inicio, Carbon $fin)
    {
        return Auditoria::query()
            ->where('entidad_tipo', 'Alumno')->where('accion', 'updated')
            ->whereBetween('created_at', [$inicio, $fin])
            ->where(fn ($q) => $q->where('datos_nuevos', 'like', '%"activo":false%')->orWhere('datos_nuevos', 'like', '%"activo":0%'))
            ->when($sedes !== null, fn ($q) => $q->whereIn('entidad_id', Alumno::query()->select('id')->whereIn('sede_id', $sedes ?: [0])))
            ->pluck('created_at');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $fechas
     * @return array<string, int>
     */
    private function porMes($fechas): array
    {
        return $fechas->filter()->countBy(fn ($f) => Carbon::parse($f)->format('Y-m'))->all();
    }

    /**
     * @param  list<int>|null  $sedes
     * @return array<string, float>
     */
    private function asistenciaPorMes(?array $sedes, Carbon $inicio, Carbon $fin): array
    {
        $filas = Asistencia::query()
            ->join('bloques', 'bloques.id', '=', 'asistencias.bloque_id')
            ->whereBetween('asistencias.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->where(fn ($q) => $q->whereNull('asistencias.tipo_asistencia')->orWhereNotIn('asistencias.tipo_asistencia', ['feriado', 'sin_clases']))
            ->when($sedes !== null, fn ($q) => $q->whereIn('bloques.sede_id', $sedes ?: [0]))
            ->groupBy('asistencias.fecha')
            ->toBase()
            ->get(['asistencias.fecha', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN asistencias.presente THEN 1 ELSE 0 END) as presentes')]);

        $out = [];
        foreach ($filas->groupBy(fn ($f) => Carbon::parse($f->fecha)->format('Y-m')) as $mes => $g) {
            $total = (int) $g->sum('total');
            $out[$mes] = $total > 0 ? round((int) $g->sum('presentes') / $total * 100, 1) : null;
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    /**
     * @param  list<int>|null  $sedes
     * @return array{inscriptos: int, cupo: int, porcentaje: ?float, bloques: list<array{id: int, nombre: string, sede: ?string, inscriptos: int, cupo: int, porcentaje: ?float}>}
     */
    private function ocupacion(?array $sedes): array
    {
        $bloques = Bloque::query()->where('activo', true)->when($sedes !== null, fn ($q) => $q->whereIn('sede_id', $sedes ?: [0]))
            ->with('sede:id,nombre')->get(['id', 'nombre', 'sede_id', 'cantidad_max_alumnos']);
        $ids = $bloques->pluck('id')->all() ?: [0];
        $pivote = DB::table('alumno_bloque')->join('alumnos', 'alumnos.id', '=', 'alumno_bloque.alumno_id')->where('alumnos.activo', true)
            ->whereIn('alumno_bloque.bloque_id', $ids)->get(['alumno_bloque.bloque_id', 'alumno_bloque.alumno_id']);
        $legado = DB::table('alumnos')->where('activo', true)->whereIn('bloque_id', $ids)->get(['bloque_id', 'id as alumno_id']);
        $conteo = $pivote->concat($legado)->groupBy('bloque_id')->map(fn ($g) => $g->pluck('alumno_id')->unique()->count());

        $filas = $bloques->map(function (Bloque $b) use ($conteo) {
            $inscriptos = (int) ($conteo[$b->id] ?? 0);
            $cupo = (int) $b->cantidad_max_alumnos;

            return ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre, 'inscriptos' => $inscriptos, 'cupo' => $cupo, 'porcentaje' => $cupo > 0 ? round($inscriptos / $cupo * 100, 1) : null];
        })->sortByDesc(fn ($f) => $f['porcentaje'] ?? -1)->values();

        $conCupo = $filas->filter(fn ($f) => $f['cupo'] > 0);
        $cupo = (int) $conCupo->sum('cupo');
        $insc = (int) $conCupo->sum('inscriptos');

        return ['inscriptos' => (int) $filas->sum('inscriptos'), 'cupo' => $cupo, 'porcentaje' => $cupo > 0 ? round($insc / $cupo * 100, 1) : null, 'bloques' => $filas->all()];
    }

    /**
     * @param  list<array<string, mixed>>  $serie
     * @param  list<int>|null  $sedes
     * @return array{mes: int, año: int, etiqueta: string, ingresos: float, gastos: float, resultado: float, emitido: float, metodo: string}
     */
    private function proyeccion(array $serie, ?array $sedes, Carbon $hoy): array
    {
        // Los 3 meses cerrados anteriores al actual (el mes en curso todavía no terminó).
        $cerrados = array_slice($serie, -4, 3);
        $proximo = $hoy->copy()->startOfMonth()->addMonth();
        $emitido = (float) ($this->tendencias->serie((int) $proximo->month, (int) $proximo->year, 1, $sedes)[0]['emitido'] ?? 0);
        $cobranza = $this->promedio(array_filter(array_column($cerrados, 'cobranza'), fn ($v) => $v !== null));
        $gastos = $this->promedio(array_column($cerrados, 'gastos')) ?? 0.0;
        if ($emitido > 0 && $cobranza !== null) {
            $ingresos = round($emitido * min(100, $cobranza) / 100, 2);
            $metodo = 'Emitido del mes × cobranza promedio de los últimos 3 meses ('.$cobranza.' %)';
        } else {
            $ingresos = $this->promedio(array_column($cerrados, 'cobrado')) ?? 0.0;
            $metodo = 'Promedio de lo cobrado en los últimos 3 meses (todavía no hay cuotas del mes próximo)';
        }

        return [
            'mes' => (int) $proximo->month,
            'año' => (int) $proximo->year,
            'etiqueta' => ucfirst($proximo->locale('es')->translatedFormat('F Y')),
            'ingresos' => round($ingresos, 2),
            'gastos' => round($gastos, 2),
            'resultado' => round($ingresos - $gastos, 2),
            'emitido' => round($emitido, 2),
            'metodo' => $metodo,
        ];
    }

    /** @param  array<int|string, mixed>  $valores */
    private function promedio(array $valores): ?float
    {
        $valores = array_values($valores);

        return $valores === [] ? null : round(array_sum($valores) / count($valores), 1);
    }

    /**
     * Filas para el Excel.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, list<list<mixed>>>
     */
    public function hojasExcel(array $d): array
    {
        return [
            'Mes a mes' => array_merge(
                [['Mes', 'Emitido', 'Cobrado', 'Cobranza %', 'Gastos', 'Resultado', 'Margen %', 'Altas', 'Bajas', 'Asistencia %']],
                array_map(fn ($p) => [$p['etiqueta'], $p['emitido'], $p['cobrado'], $p['cobranza'], $p['gastos'], $p['resultado'], $p['margen'], $p['altas'], $p['bajas'], $p['asistencia']], $d['serie'])
            ),
            'Resumen' => [
                ['Concepto', 'Valor'],
                ['Alumnos activos', $d['alumnos']['activos']],
                ['Altas (12 meses)', $d['alumnos']['altas_12m']],
                ['Bajas (12 meses)', $d['alumnos']['bajas_12m']],
                ['Retención %', $d['alumnos']['retencion']],
                ['Deuda vencida', $d['morosidad']['total']],
                ['Alumnos con deuda', $d['morosidad']['alumnos']],
                ['Ocupación de bloques %', $d['ocupacion']['porcentaje']],
                ['Asistencia promedio %', $d['asistencia_promedio']],
                ['Proyección '.$d['proyeccion']['etiqueta'].' · ingresos', $d['proyeccion']['ingresos']],
                ['Proyección '.$d['proyeccion']['etiqueta'].' · gastos', $d['proyeccion']['gastos']],
                ['Proyección '.$d['proyeccion']['etiqueta'].' · resultado', $d['proyeccion']['resultado']],
            ],
            'Ocupación' => array_merge(
                [['Sede', 'Bloque', 'Inscriptos', 'Cupo', 'Ocupación %']],
                array_map(fn ($b) => [$b['sede'], $b['nombre'], $b['inscriptos'], $b['cupo'], $b['porcentaje']], $d['ocupacion']['bloques'])
            ),
        ];
    }
}
