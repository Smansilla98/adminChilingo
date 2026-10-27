@extends('layouts.app')

@section('title', 'Tendencias y deuda')
@section('page-title', 'Reportes — Tendencias y deuda')

@php
    $plata = fn ($n) => '$ '.number_format((float) $n, 0, ',', '.');
    $nombresMes = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $variacion = function ($actual, $base) {
        if (! $base) {
            return null;
        }

        return round(($actual - $base) / abs($base) * 100);
    };
    $maximo = max(1, collect($serie)->max(fn ($p) => max($p['emitido'], $p['cobrado'], $p['gastos'])));
    $c = $comparativo;
@endphp

@push('styles')
<style>
    .td-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .75rem; }
    .td-kpi { padding: 1rem; }
    .td-kpi .td-label { font-size: .8rem; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
    .td-kpi .td-valor { font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .td-delta { font-size: .85rem; font-weight: 600; display: block; }
    .td-sube { color: var(--bs-success); } .td-baja { color: var(--bs-danger); }
    .td-barras { display: grid; gap: .45rem; }
    .td-fila { display: grid; grid-template-columns: 4.5rem 1fr 7.5rem; gap: .6rem; align-items: center; font-size: .85rem; }
    .td-pistas { display: grid; gap: 3px; }
    .td-pista { height: 9px; border-radius: 5px; min-width: 2px; }
    .td-pista.e { background: var(--bs-secondary-bg-subtle, #e9ecef); outline: 1px solid var(--bs-border-color); }
    .td-pista.c { background: var(--bs-success); }
    .td-pista.g { background: var(--bs-danger); opacity: .75; }
    .td-leyenda { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .8rem; color: var(--bs-secondary-color); }
    .td-leyenda i { display: inline-block; width: 12px; height: 9px; border-radius: 3px; margin-right: .3rem; vertical-align: middle; }
    .td-num { font-variant-numeric: tabular-nums; text-align: right; }
</style>
@endpush

@section('content')
<x-ito.shell-page title="Tendencias y deuda" eyebrow="Reportes" subtitle="Cómo viene cada mes comparado con el anterior y con el año pasado, y dónde se acumula la deuda.">
    <x-slot:actions>
        <a href="{{ route('reportes.index', ['mes' => $mes, 'año' => $año]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Reportes del mes</a>
    </x-slot:actions>

    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" aria-label="Período">
        <div>
            <label class="form-label small mb-1" for="td-mes">Mes</label>
            <select id="td-mes" name="mes" class="form-select form-select-sm">
                @foreach($nombresMes as $n => $nombre)<option value="{{ $n }}" @selected($n === $mes)>{{ $nombre }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1" for="td-anio">Año</label>
            <select id="td-anio" name="año" class="form-select form-select-sm">
                @for($y = now()->year; $y >= now()->year - 5; $y--)<option value="{{ $y }}" @selected($y === $año)>{{ $y }}</option>@endfor
            </select>
        </div>
        <div>
            <label class="form-label small mb-1" for="td-meses">Ver</label>
            <select id="td-meses" name="meses" class="form-select form-select-sm">
                @foreach([6 => 'Últimos 6 meses', 12 => 'Últimos 12 meses', 24 => 'Últimos 24 meses'] as $n => $t)<option value="{{ $n }}" @selected($n === $meses)>{{ $t }}</option>@endforeach
            </select>
        </div>
        <button class="btn btn-sm btn-primary">Ver</button>
        @if(!empty($sedeScope))<span class="badge bg-info text-dark align-self-center">Solo tus sedes</span>@endif
    </form>

    <h2 class="h6 mb-2">{{ $nombresMes[$mes] }} {{ $año }} comparado</h2>
    <div class="td-kpis mb-4">
        @foreach([['cobrado', 'Cobrado', true], ['gastos', 'Gastos', false], ['resultado', 'Resultado', true]] as [$k, $titulo, $masEsMejor])
        @php $vsMes = $variacion($c['actual'][$k], $c['anterior'][$k]); $vsAño = $variacion($c['actual'][$k], $c['año_pasado'][$k]); @endphp
        <div class="ito-card td-kpi">
            <div class="td-label">{{ $titulo }}</div>
            <div class="td-valor">{{ $plata($c['actual'][$k]) }}</div>
            @foreach([[$vsMes, 'vs. mes anterior', $c['anterior'][$k]], [$vsAño, 'vs. '.$nombresMes[$mes].' '.($año - 1), $c['año_pasado'][$k]]] as [$v, $contra, $base])
                @if($v === null)
                    <span class="td-delta text-muted">— {{ $contra }} (sin datos)</span>
                @else
                    <span class="td-delta {{ ($v >= 0) === $masEsMejor ? 'td-sube' : 'td-baja' }}" title="{{ $plata($base) }}">{{ $v >= 0 ? '▲' : '▼' }} {{ abs($v) }} % {{ $contra }}</span>
                @endif
            @endforeach
        </div>
        @endforeach
        <div class="ito-card td-kpi">
            <div class="td-label">Cobranza</div>
            <div class="td-valor">{{ $c['actual']['cobranza'] !== null ? $c['actual']['cobranza'].' %' : '—' }}</div>
            <span class="td-delta text-muted">Cobrado sobre lo emitido del mes{{ $c['año_pasado']['cobranza'] !== null ? ' · el año pasado: '.$c['año_pasado']['cobranza'].' %' : '' }}</span>
        </div>
    </div>

    <section class="ito-card p-3 mb-4" aria-labelledby="td-serie">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h6 mb-0" id="td-serie">Mes a mes</h2>
            <div class="td-leyenda" aria-hidden="true"><span><i style="outline:1px solid var(--bs-border-color)"></i>Emitido</span><span><i style="background:var(--bs-success)"></i>Cobrado</span><span><i style="background:var(--bs-danger);opacity:.75"></i>Gastos</span></div>
        </div>
        <div class="td-barras" role="img" aria-label="Emitido, cobrado y gastos de los últimos {{ $meses }} meses (detalle en la tabla de abajo)">
            @foreach($serie as $p)
            <div class="td-fila">
                <span>{{ $p['etiqueta'] }}</span>
                <span class="td-pistas">
                    <span class="td-pista e" style="width: {{ $p['emitido'] / $maximo * 100 }}%"></span>
                    <span class="td-pista c" style="width: {{ $p['cobrado'] / $maximo * 100 }}%"></span>
                    <span class="td-pista g" style="width: {{ $p['gastos'] / $maximo * 100 }}%"></span>
                </span>
                <span class="td-num {{ $p['resultado'] < 0 ? 'text-danger' : '' }}">{{ $plata($p['resultado']) }}</span>
            </div>
            @endforeach
        </div>
        <details class="mt-3">
            <summary class="small">Ver la tabla</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Mes</th><th class="td-num">Emitido (aprox.)</th><th class="td-num">Cobrado</th><th class="td-num">Cobranza</th><th class="td-num">Gastos</th><th class="td-num">Resultado</th></tr></thead>
                    <tbody>
                        @foreach(array_reverse($serie) as $p)
                        <tr>
                            <td>{{ $p['etiqueta'] }}</td>
                            <td class="td-num">{{ $plata($p['emitido']) }}</td>
                            <td class="td-num">{{ $plata($p['cobrado']) }}</td>
                            <td class="td-num">{{ $p['cobranza'] !== null ? $p['cobranza'].' %' : '—' }}</td>
                            <td class="td-num">{{ $plata($p['gastos']) }}</td>
                            <td class="td-num {{ $p['resultado'] < 0 ? 'text-danger' : '' }}">{{ $plata($p['resultado']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
        <p class="small text-muted mb-0 mt-2">Cobrado: pagos registrados ese mes (sin anulados). Emitido: cuotas del mes por la cantidad de alumnos a los que aplican, sin descontar becas.</p>
    </section>

    <section aria-labelledby="td-deuda">
        <h2 class="h6 mb-2" id="td-deuda">Deuda de cuotas vencidas</h2>
        @if($morosidad['total'] <= 0)
            <div class="ito-card p-3 text-success"><i class="bi bi-check2-circle"></i> No hay cuotas vencidas sin pagar.</div>
        @else
            <div class="td-kpis mb-3">
                <div class="ito-card td-kpi"><div class="td-label">Total adeudado</div><div class="td-valor text-danger">{{ $plata($morosidad['total']) }}</div><span class="td-delta text-muted">Con becas y pagos parciales descontados</span></div>
                <div class="ito-card td-kpi"><div class="td-label">Alumnos con deuda</div><div class="td-valor">{{ $morosidad['alumnos'] }}</div></div>
            </div>
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="ito-card p-0 h-100">
                        <div class="card-header py-2 px-3">Por sede</div>
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th class="ps-3">Sede</th><th class="td-num">Alumnos</th><th class="td-num pe-3">Deuda</th></tr></thead>
                            <tbody>
                                @foreach($morosidad['por_sede'] as $f)
                                <tr><td class="ps-3">{{ $f['sede'] }}</td><td class="td-num">{{ $f['alumnos'] }}</td><td class="td-num pe-3">{{ $plata($f['total']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="ito-card p-0 h-100">
                        <div class="card-header py-2 px-3">Por bloque</div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead><tr><th class="ps-3">Bloque</th><th>Sede</th><th class="td-num">Alumnos</th><th class="td-num">Cuotas</th><th class="td-num pe-3">Deuda</th></tr></thead>
                                <tbody>
                                    @forelse($morosidad['por_bloque'] as $f)
                                    <tr><td class="ps-3">{{ $f['bloque'] }}</td><td>{{ $f['sede'] ?? '—' }}</td><td class="td-num">{{ $f['alumnos'] }}</td><td class="td-num">{{ $f['cuotas'] }}</td><td class="td-num pe-3">{{ $plata($f['total']) }}</td></tr>
                                    @empty
                                    <tr><td colspan="5" class="text-muted ps-3">La deuda es de cuotas por sede o generales.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ito-card p-0 mt-3">
                <div class="card-header py-2 px-3">Quiénes deben más</div>
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th class="ps-3">Alumno</th><th class="td-num">Cuotas</th><th class="td-num pe-3">Deuda</th></tr></thead>
                    <tbody>
                        @foreach($morosidad['mayores'] as $f)
                        <tr>
                            <td class="ps-3"><a href="{{ route('alumnos.show', $f['alumno']) }}">{{ $f['alumno']->nombre_apellido }}</a></td>
                            <td class="td-num">{{ $f['cuotas'] }}</td>
                            <td class="td-num pe-3">{{ $plata($f['total']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2">Se actualiza cada hora (o a los 10 minutos). Cuenta cuotas de este año y el anterior.</p>
        @endif
    </section>
</x-ito.shell-page>
@endsection
