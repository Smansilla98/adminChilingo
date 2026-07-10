@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Panel Administrador')

@section('content')
@php
    $saludo = now()->hour < 12 ? 'Buen día' : (now()->hour < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp

<div class="dash-hero">
    <div>
        <div class="dash-hero-eyebrow">Pulso del mes</div>
        <h2 class="dash-hero-title">
            {{ $sedesActivasEnBloques ?? 0 }} sedes activas,
            <em>{{ number_format($asistenciasMes ?? 0, 0, ',', '.') }}</em> asistencias registradas este mes.
        </h2>
    </div>
    <div class="text-end">
        <div class="small text-muted text-uppercase mb-2" style="letter-spacing:.08em;">Actividad reciente</div>
        <div class="pulse-strip" aria-hidden="true">
            @for($i = 0; $i < 16; $i++)
                <i class="{{ in_array($i, [0,4,8,12]) ? 'on' : (in_array($i, [2,6,10,14]) ? 'accent' : '') }}"></i>
            @endfor
        </div>
    </div>
</div>

<div class="dash-metrics">
    <div class="dash-metric" style="--metric-accent:var(--brass);--metric-soft:var(--brass-soft)">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="dash-metric-icon"><i class="bi bi-people"></i></div>
            @if(($alumnosNuevosMes ?? 0) > 0)
                <span class="small text-success font-monospace">+{{ $alumnosNuevosMes }}</span>
            @endif
        </div>
        <div class="dash-metric-value">{{ number_format($alumnosActivos ?? 0, 0, ',', '.') }}</div>
        <div class="dash-metric-label">Alumnos activos</div>
    </div>
    <div class="dash-metric" style="--metric-accent:var(--verdigris);--metric-soft:var(--verdigris-soft)">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="dash-metric-icon"><i class="bi bi-cash-stack"></i></div>
        </div>
        <div class="dash-metric-value">${{ number_format($cobradoMes ?? 0, 0, ',', '.') }}</div>
        <div class="dash-metric-label">Cobrado del mes</div>
    </div>
    <div class="dash-metric" style="--metric-accent:var(--brick);--metric-soft:var(--brick-soft)">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="dash-metric-icon"><i class="bi bi-calendar-event"></i></div>
        </div>
        <div class="dash-metric-value">{{ $proximosEventosCount ?? 0 }}</div>
        <div class="dash-metric-label">Próximos eventos</div>
    </div>
    <div class="dash-metric" style="--metric-accent:var(--purple);--metric-soft:rgba(156,138,209,.16)">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="dash-metric-icon"><i class="bi bi-inbox"></i></div>
        </div>
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
        <canvas id="ingresosGastosChart" height="180" aria-label="Gráfico de ingresos y gastos"></canvas>
    </div>
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="dash-card-title">Alumnos por sede</div>
                <div class="small text-muted">Distribución actual</div>
            </div>
        </div>
        <canvas id="alumnosSedeChart" height="180" aria-label="Gráfico de alumnos por sede"></canvas>
    </div>
</div>

<div class="dash-widgets">
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dash-card-title">Próximos eventos</div>
            <a href="{{ route('eventos.index') }}" class="small">Ver todos →</a>
        </div>
        <div>
            @forelse($proximosEventos ?? [] as $evento)
                <div class="dash-list-item">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $evento->titulo }}</div>
                        <div class="small text-muted">{{ $evento->fecha?->format('d/m/Y') }} · {{ $evento->lugar ?? 'Sin lugar' }}</div>
                    </div>
                    <span class="dash-badge pend">Próximo</span>
                </div>
            @empty
                <p class="text-muted small mb-0">No hay eventos próximos.</p>
            @endforelse
        </div>
    </div>
    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dash-card-title">Comprobantes pendientes</div>
            <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="small">Revisar →</a>
        </div>
        <div>
            @forelse($comprobantesPendientesList ?? [] as $comp)
                <div class="dash-list-item">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $comp->alumno?->nombre_apellido ?? 'Alumno' }}</div>
                        <div class="small text-muted">{{ $comp->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <span class="dash-badge alert">Pendiente</span>
                </div>
            @empty
                <p class="text-muted small mb-0">No hay comprobantes sin revisar.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="dash-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="dash-card-title">Bloques — cupo</div>
            <div class="small text-muted">Ocupación por bloque activo</div>
        </div>
        <a href="{{ route('bloques.index') }}" class="small">Ver bloques →</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Bloque</th>
                    <th>Sede</th>
                    <th>Profesor</th>
                    <th>Cupo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($bloquesCupo ?? [] as $bloque)
                    @php
                        $ocupados = (int) ($bloque->alumnos_activos_count ?? 0);
                        $max = max(1, (int) ($bloque->cantidad_max_alumnos ?? 1));
                        $pct = min(100, round(($ocupados / $max) * 100));
                        $barClass = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '');
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $bloque->nombre }}</td>
                        <td>{{ $bloque->sede?->nombre ?? '—' }}</td>
                        <td>{{ $bloque->profesor?->nombre ?? '—' }}</td>
                        <td>
                            <span class="cupo-bar {{ $barClass }}"><i style="width:{{ $pct }}%"></i></span>
                            <span class="font-monospace small">{{ $ocupados }}/{{ $max }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('bloques.show', $bloque) }}" class="small">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Sin bloques activos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const brass = getComputedStyle(document.documentElement).getPropertyValue('--brass').trim() || '#d1a054';
    const verdigris = getComputedStyle(document.documentElement).getPropertyValue('--verdigris').trim() || '#4a9a86';
    const brick = getComputedStyle(document.documentElement).getPropertyValue('--brick').trim() || '#c1432b';
    const muted = 'rgba(243,233,216,0.45)';

    const ig = document.getElementById('ingresosGastosChart');
    if (ig) {
        new Chart(ig.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($chartLabels ?? []),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: @json($chartIngresos ?? []),
                        backgroundColor: verdigris,
                        borderRadius: 6,
                    },
                    {
                        label: 'Gastos',
                        data: @json($chartGastos ?? []),
                        backgroundColor: brick,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { labels: { color: muted } } },
                scales: {
                    x: { ticks: { color: muted }, grid: { color: 'rgba(243,233,216,0.06)' } },
                    y: { ticks: { color: muted }, grid: { color: 'rgba(243,233,216,0.06)' } }
                }
            }
        });
    }

    const as = document.getElementById('alumnosSedeChart');
    if (as) {
        const sedeData = @json($alumnosPorSedeChart ?? []);
        new Chart(as.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: sedeData.map(r => r.nombre),
                datasets: [{
                    data: sedeData.map(r => r.total),
                    backgroundColor: [brass, verdigris, brick, '#9c8ad1', '#5b9ef0', '#b6a488'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { color: muted, boxWidth: 12 } } }
            }
        });
    }
})();
</script>
@endpush
@endsection
