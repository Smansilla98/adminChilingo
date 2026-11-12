@extends('layouts.app')

@section('title', 'Tablero de dirección')
@section('page-title', 'Tablero de dirección')

@php
    $plata = fn ($n) => '$ '.number_format((float) $n, 0, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').' %';
    $a = $d['actual'];
@endphp

@push('styles')
<style>
    .dt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; }
    .dt-kpi { padding: 1rem; }
    .dt-kpi .dt-l { font-size: .78rem; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
    .dt-kpi .dt-v { font-size: 1.5rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .dt-kpi .dt-s { font-size: .82rem; color: var(--bs-secondary-color); }
    .dt-graf { position: relative; height: 280px; }
    .dt-num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')
<x-ito.shell-page title="Tablero de dirección" eyebrow="Reportes" subtitle="Los últimos 12 meses: plata, alumnos, ocupación y asistencia. {{ $d['alcance'] === 'global' ? 'Toda la escuela.' : 'Solo las sedes elegidas.' }}">
    <x-slot:actions>
        <a href="{{ route('direccion.excel', array_filter(['sede_id' => $sedeId])) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="{{ route('direccion.imprimible', array_filter(['sede_id' => $sedeId])) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-printer"></i> PDF</a>
    </x-slot:actions>

    @if($sedes->count() > 1)
        <form method="GET" class="d-flex gap-2 align-items-end mb-3" aria-label="Sede">
            <div>
                <label class="form-label small mb-1" for="dt-sede">Sede</label>
                <select id="dt-sede" name="sede_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas mis sedes</option>
                    @foreach($sedes as $s)<option value="{{ $s->id }}" @selected($sedeId === $s->id)>{{ $s->nombre }}</option>@endforeach
                </select>
            </div>
            <noscript><button class="btn btn-sm btn-primary">Ver</button></noscript>
        </form>
    @endif

    <div class="dt-kpis mb-4">
        <div class="ito-card dt-kpi"><div class="dt-l">Resultado del mes</div><div class="dt-v {{ $a['resultado'] < 0 ? 'text-danger' : '' }}">{{ $plata($a['resultado']) }}</div><div class="dt-s">Cobrado {{ $plata($a['cobrado']) }} · gastos {{ $plata($a['gastos']) }}</div></div>
        <div class="ito-card dt-kpi"><div class="dt-l">Cobranza del mes</div><div class="dt-v">{{ $pct($a['cobranza']) }}</div><div class="dt-s">Cobrado sobre lo emitido ({{ $plata($a['emitido']) }})</div></div>
        <div class="ito-card dt-kpi"><div class="dt-l">Deuda vencida</div><div class="dt-v">{{ $plata($d['morosidad']['total']) }}</div><div class="dt-s">{{ $d['morosidad']['alumnos'] }} alumnos ({{ $pct($d['morosidad']['porcentaje_alumnos']) }} de los activos)</div></div>
        <div class="ito-card dt-kpi"><div class="dt-l">Alumnos activos</div><div class="dt-v">{{ $d['alumnos']['activos'] }}</div><div class="dt-s">12 meses: {{ $d['alumnos']['altas_12m'] }} altas · {{ $d['alumnos']['bajas_12m'] }} bajas · retención {{ $pct($d['alumnos']['retencion']) }}</div></div>
        <div class="ito-card dt-kpi"><div class="dt-l">Ocupación de bloques</div><div class="dt-v">{{ $pct($d['ocupacion']['porcentaje']) }}</div><div class="dt-s">{{ $d['ocupacion']['inscriptos'] }} inscriptos · cupo {{ $d['ocupacion']['cupo'] }}</div></div>
        <div class="ito-card dt-kpi"><div class="dt-l">Asistencia promedio</div><div class="dt-v">{{ $pct($d['asistencia_promedio']) }}</div><div class="dt-s">Presentes sobre clases tomadas</div></div>
        <div class="ito-card dt-kpi"><div class="dt-l">Proyección {{ $d['proyeccion']['etiqueta'] }}</div><div class="dt-v {{ $d['proyeccion']['resultado'] < 0 ? 'text-danger' : '' }}">{{ $plata($d['proyeccion']['resultado']) }}</div><div class="dt-s" title="{{ $d['proyeccion']['metodo'] }}">Ingresos {{ $plata($d['proyeccion']['ingresos']) }} · gastos {{ $plata($d['proyeccion']['gastos']) }} (estimado)</div></div>
    </div>

    <div class="row g-3 mb-3">
        <section class="col-lg-7"><div class="ito-card p-3 h-100">
            <h2 class="h6">Ingresos, gastos y margen</h2>
            <div class="dt-graf"><canvas id="dtFinanzas" role="img" aria-label="Ingresos y gastos de los últimos 12 meses (detalle en la tabla)"></canvas></div>
        </div></section>
        <section class="col-lg-5"><div class="ito-card p-3 h-100">
            <h2 class="h6">Altas, bajas y asistencia</h2>
            <div class="dt-graf"><canvas id="dtAlumnos" role="img" aria-label="Altas, bajas y asistencia por mes (detalle en la tabla)"></canvas></div>
        </div></section>
    </div>

    <section class="ito-card p-3 mb-3">
        <h2 class="h6">Mes a mes</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Mes</th><th class="dt-num">Emitido</th><th class="dt-num">Cobrado</th><th class="dt-num">Cobranza</th><th class="dt-num">Gastos</th><th class="dt-num">Resultado</th><th class="dt-num">Margen</th><th class="dt-num">Altas</th><th class="dt-num">Bajas</th><th class="dt-num">Asistencia</th></tr></thead>
                <tbody>
                    @foreach(array_reverse($d['serie']) as $p)
                        <tr>
                            <td>{{ $p['etiqueta'] }}</td><td class="dt-num">{{ $plata($p['emitido']) }}</td><td class="dt-num">{{ $plata($p['cobrado']) }}</td><td class="dt-num">{{ $pct($p['cobranza']) }}</td>
                            <td class="dt-num">{{ $plata($p['gastos']) }}</td><td class="dt-num {{ $p['resultado'] < 0 ? 'text-danger' : '' }}">{{ $plata($p['resultado']) }}</td><td class="dt-num">{{ $pct($p['margen']) }}</td>
                            <td class="dt-num">{{ $p['altas'] }}</td><td class="dt-num">{{ $p['bajas'] }}</td><td class="dt-num">{{ $pct($p['asistencia']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-2 mb-0">Emitido: cuotas del mes por alumnos a los que aplican (aproximado, sin becas). Bajas: alumnos que pasaron a inactivos. Proyección: {{ $d['proyeccion']['metodo'] }}.</p>
    </section>

    <div class="row g-3">
        <section class="col-lg-6"><div class="ito-card p-3 h-100">
            <h2 class="h6">Deuda vencida por sede</h2>
            <table class="table table-sm mb-0"><thead><tr><th>Sede</th><th class="dt-num">Deuda</th><th class="dt-num">Alumnos</th></tr></thead><tbody>
                @forelse($d['morosidad']['por_sede'] as $m)<tr><td>{{ $m['sede'] }}</td><td class="dt-num">{{ $plata($m['total']) }}</td><td class="dt-num">{{ $m['alumnos'] }}</td></tr>@empty<tr><td colspan="3" class="text-muted">Sin deuda vencida.</td></tr>@endforelse
            </tbody></table>
        </div></section>
        <section class="col-lg-6"><div class="ito-card p-3 h-100">
            <h2 class="h6">Ocupación por bloque</h2>
            <table class="table table-sm mb-0"><thead><tr><th>Bloque</th><th class="dt-num">Inscriptos</th><th class="dt-num">Cupo</th><th class="dt-num">Ocupación</th></tr></thead><tbody>
                @forelse(array_slice($d['ocupacion']['bloques'], 0, 15) as $b)<tr><td>{{ $b['nombre'] }} <span class="text-muted small">{{ $b['sede'] }}</span></td><td class="dt-num">{{ $b['inscriptos'] }}</td><td class="dt-num">{{ $b['cupo'] ?: '—' }}</td><td class="dt-num">{{ $pct($b['porcentaje']) }}</td></tr>@empty<tr><td colspan="4" class="text-muted">Sin bloques activos.</td></tr>@endforelse
            </tbody></table>
        </div></section>
    </div>
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function () {
    if (!window.Chart) return;
    const css = getComputedStyle(document.documentElement);
    const v = (n, f) => css.getPropertyValue(n).trim() || f;
    const grid = v('--border', '#e2e5ea');
    const success = v('--success', '#1e7b34');
    const accent = v('--accent', '#f26422');
    const info = v('--info', '#0b7593');
    const texto = v('--text', '#1d232c');
    Chart.defaults.color = v('--muted', '#5b6472');
    const peso = (n) => '$' + Number(n || 0).toLocaleString('es-AR');
    const serie = @json($d['serie']);
    const labels = serie.map((p) => p.etiqueta);

    new Chart(document.getElementById('dtFinanzas'), {
        data: {
            labels,
            datasets: [
                { type: 'bar', label: 'Ingresos (cobrado)', data: serie.map((p) => p.cobrado), backgroundColor: success, borderRadius: 4, maxBarThickness: 22, yAxisID: 'y' },
                { type: 'bar', label: 'Gastos', data: serie.map((p) => p.gastos), backgroundColor: accent, borderRadius: 4, maxBarThickness: 22, yAxisID: 'y' },
                { type: 'line', label: 'Margen %', data: serie.map((p) => p.margen), borderColor: texto, backgroundColor: texto, pointRadius: 3, tension: .25, yAxisID: 'y1', spanGaps: true },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } }, tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1' ? c.dataset.label + ': ' + (c.raw ?? '—') + ' %' : c.dataset.label + ': ' + peso(c.raw) } } },
            scales: {
                x: { grid: { display: false }, border: { color: grid } },
                y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { callback: (val) => peso(val) } },
                y1: { position: 'right', grid: { display: false }, border: { display: false }, ticks: { callback: (val) => val + ' %' } },
            },
        },
    });

    new Chart(document.getElementById('dtAlumnos'), {
        data: {
            labels,
            datasets: [
                { type: 'bar', label: 'Altas', data: serie.map((p) => p.altas), backgroundColor: info, borderRadius: 4, maxBarThickness: 16, yAxisID: 'y' },
                { type: 'bar', label: 'Bajas', data: serie.map((p) => p.bajas), backgroundColor: accent, borderRadius: 4, maxBarThickness: 16, yAxisID: 'y' },
                { type: 'line', label: 'Asistencia %', data: serie.map((p) => p.asistencia), borderColor: success, backgroundColor: success, pointRadius: 3, tension: .25, yAxisID: 'y1', spanGaps: true },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } },
            scales: {
                x: { grid: { display: false }, border: { color: grid } },
                y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0 } },
                y1: { position: 'right', min: 0, max: 100, grid: { display: false }, border: { display: false }, ticks: { callback: (val) => val + ' %' } },
            },
        },
    });
})();
</script>
@endpush
