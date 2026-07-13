@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Panel Administrador')

@section('content')
@php
    $nombreSaludo = trim($adminNombre ?? '') ?: 'equipo';
    $primerNombre = explode(' ', $nombreSaludo)[0];
@endphp

<div class="dash-hero">
    <div>
        <div class="dash-hero-eyebrow">Pulso del mes</div>
        <div class="dash-hero-title">
            {{ $sedesActivasEnBloques ?? 0 }} sedes activas,
            <em>{{ number_format($asistenciasMes ?? 0, 0, ',', '.') }}</em> asistencias registradas este mes.
        </div>
    </div>
    <div class="text-end">
        <div class="small text-uppercase text-muted mb-2" style="letter-spacing:.08em;">Actividad — últimos 16 días</div>
        <div class="pulse-strip" aria-hidden="true">
            @for($i = 0; $i < 16; $i++)
                <i class="{{ in_array($i % 4, [0, 2]) ? 'on' : '' }}{{ $i % 7 === 3 ? ' accent' : '' }}"></i>
            @endfor
        </div>
    </div>
</div>

<div class="dash-metrics">
    <div class="dash-metric" style="--metric-accent:var(--brass);--metric-soft:var(--brass-soft)">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="dash-metric-icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            @if(($alumnosNuevosMes ?? 0) > 0)
                <span class="small text-success font-monospace">+{{ $alumnosNuevosMes }}</span>
            @endif
        </div>
        <div class="dash-metric-value">{{ $alumnosActivos ?? 0 }}</div>
        <div class="dash-metric-label">Alumnos activos</div>
    </div>
    <div class="dash-metric" style="--metric-accent:var(--verdigris);--metric-soft:var(--verdigris-soft)">
        <div class="dash-metric-icon mb-2"><i class="bi bi-currency-dollar" aria-hidden="true"></i></div>
        <div class="dash-metric-value">${{ number_format($cobradoMes ?? 0, 0, ',', '.') }}</div>
        <div class="dash-metric-label">Cobrado este mes</div>
    </div>
    <div class="dash-metric" style="--metric-accent:var(--brick);--metric-soft:var(--brick-soft)">
        <div class="dash-metric-icon mb-2"><i class="bi bi-calendar-event" aria-hidden="true"></i></div>
        <div class="dash-metric-value">{{ $proximosEventosCount ?? 0 }}</div>
        <div class="dash-metric-label">Próximos eventos</div>
    </div>
    <div class="dash-metric" style="--metric-accent:var(--purple);--metric-soft:rgba(156,138,209,.16)">
        <div class="dash-metric-icon mb-2"><i class="bi bi-inbox" aria-hidden="true"></i></div>
        <div class="dash-metric-value">{{ $comprobantesPendientesCount ?? 0 }}</div>
        <div class="dash-metric-label">Comprobantes sin revisar</div>
    </div>
</div>

<div class="dash-widgets">
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="dash-card-title">Ingresos y gastos</div>
                <div class="small text-muted">Últimos 6 meses</div>
            </div>
        </div>
        <div style="height:220px;">
            <canvas id="dashChartFinanzas" aria-label="Gráfico de ingresos y gastos"></canvas>
        </div>
    </div>
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="dash-card-title">Alumnos por sede</div>
                <div class="small text-muted">Activos hoy</div>
            </div>
        </div>
        <div style="height:220px;">
            <canvas id="dashChartSedes" aria-label="Gráfico de alumnos por sede"></canvas>
        </div>
    </div>
</div>

<div class="dash-widgets">
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dash-card-title">Próximos eventos</div>
            <a href="{{ route('eventos.index') }}" class="small text-muted">Ver todos →</a>
        </div>
        <div>
            @forelse(($proximosEventos ?? collect()) as $ev)
                <div class="dash-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $ev->titulo ?? $ev->nombre ?? 'Evento' }}</div>
                        <div class="small text-muted">
                            {{ $ev->fecha?->locale('es')->translatedFormat('d M Y') ?? '—' }}
                            @if(!empty($ev->lugar)) · {{ $ev->lugar }} @endif
                        </div>
                    </div>
                    <span class="dash-badge pend">Próximo</span>
                </div>
            @empty
                <p class="text-muted small mb-0 py-2">No hay eventos próximos.</p>
            @endforelse
        </div>
    </div>
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dash-card-title">Comprobantes sin revisar</div>
            <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="small text-muted">Ir al listado →</a>
        </div>
        <div>
            @forelse(($comprobantesPendientesList ?? collect()) as $comp)
                <div class="dash-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $comp->alumno?->nombre_apellido ?? 'Alumno' }}</div>
                        <div class="small text-muted">{{ $comp->created_at?->diffForHumans() ?? '' }}</div>
                    </div>
                    <span class="dash-badge alert">Pendiente</span>
                </div>
            @empty
                <p class="text-muted small mb-0 py-2">No hay comprobantes pendientes.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="dash-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <div class="dash-card-title">Bloques — cupo</div>
            <div class="small text-muted">Ocupación por bloque activo</div>
        </div>
        <a href="{{ route('bloques.index') }}" class="small text-muted">Ver bloques →</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Bloque</th>
                    <th>Sede</th>
                    <th>Profesor</th>
                    <th>Cupo</th>
                    <th class="text-end">Alumnos</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($bloquesCupo ?? collect()) as $bloque)
                    @php
                        $activos = (int) ($bloque->alumnos_activos_count ?? 0);
                        $cupo = max(1, (int) ($bloque->cupo_maximo ?? $bloque->cupo ?? 30));
                        $pct = min(100, round(($activos / $cupo) * 100));
                        $barClass = $pct >= 100 ? 'full' : ($pct >= 75 ? 'warn' : '');
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $bloque->nombre }}</td>
                        <td class="text-muted">{{ $bloque->sede?->nombre ?? '—' }}</td>
                        <td class="text-muted">{{ $bloque->profesor?->nombre ?? '—' }}</td>
                        <td>
                            <span class="cupo-bar {{ $barClass }}" aria-hidden="true"><i style="width:{{ $pct }}%"></i></span>
                            <span class="small font-monospace">{{ $pct }}%</span>
                        </td>
                        <td class="text-end font-monospace">{{ $activos }}/{{ $cupo }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Sin bloques activos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="dash-widgets">
    <div class="dash-card">
        <div class="dash-card-title mb-3">Acciones rápidas</div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('alumnos.create') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-plus"></i> Alumno</a>
            <a href="{{ route('bloques.create') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-collection"></i> Bloque</a>
            <a href="{{ route('pagos.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-receipt"></i> Registrar pago</a>
            <a href="{{ route('asistencias.create') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-square"></i> Asistencia</a>
            <a href="{{ route('programa.partituras.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-music-note-beamed"></i> Partituras</a>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-title mb-3">Recaudación semanal</div>
        <div style="height:160px;">
            <canvas id="recaudacionChart" aria-label="Recaudación últimas semanas"></canvas>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const css = getComputedStyle(document.documentElement);
    const skin = css.getPropertyValue('--skin').trim() || '#f3e9d8';
    const muted = css.getPropertyValue('--muted-2').trim() || 'rgba(255,255,255,.45)';
    const line = 'rgba(243,233,216,0.08)';
    const brick = css.getPropertyValue('--brick').trim() || '#c1432b';
    const verdigris = css.getPropertyValue('--verdigris').trim() || '#4a9a86';
    const brass = css.getPropertyValue('--brass').trim() || '#d1a054';
    const s3 = css.getPropertyValue('--surface-3').trim() || '#332619';

    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: muted } } },
        scales: {
            x: { ticks: { color: muted }, grid: { color: line } },
            y: { ticks: { color: muted }, grid: { color: line } },
        },
    };

    const fin = document.getElementById('dashChartFinanzas');
    if (fin) {
        new Chart(fin.getContext('2d'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels ?? []) !!},
                datasets: [
                    {
                        label: 'Ingresos',
                        data: {!! json_encode($chartIngresos ?? []) !!},
                        backgroundColor: verdigris,
                        borderRadius: 6,
                    },
                    {
                        label: 'Gastos',
                        data: {!! json_encode($chartGastos ?? []) !!},
                        backgroundColor: brick,
                        borderRadius: 6,
                    },
                ],
            },
            options: chartDefaults,
        });
    }

    const sedes = document.getElementById('dashChartSedes');
    if (sedes) {
        const sedeData = {!! json_encode(($alumnosPorSedeChart ?? collect())->values()) !!};
        new Chart(sedes.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: sedeData.map((r) => r.nombre),
                datasets: [{
                    data: sedeData.map((r) => r.total),
                    backgroundColor: [brass, verdigris, brick, '#9c8ad1', '#5b9ef0', s3],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: muted, boxWidth: 10 } } },
            },
        });
    }

    const rec = document.getElementById('recaudacionChart');
    if (rec) {
        const values = {!! json_encode(array_values(($recaudacion ?? collect([0,0,0,0,0,0]))->toArray())) !!};
        new Chart(rec.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Sem 1','Sem 2','Sem 3','Sem 4','Sem 5','Sem 6'],
                datasets: [{
                    data: values,
                    backgroundColor: [s3, s3, s3, 'rgba(193,67,43,.35)', 'rgba(193,67,43,.55)', brick],
                    borderRadius: 8,
                }],
            },
            options: { ...chartDefaults, plugins: { legend: { display: false } } },
        });
    }
})();
</script>
@endpush
