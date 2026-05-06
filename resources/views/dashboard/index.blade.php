@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Panel Administrador')

@section('content')
<div class="stat-strip">
    <div class="stat-card c-orange">
        <div class="stat-kicker">Alumnos activos</div>
        <div class="stat-value">{{ $alumnosActivos ?? 0 }}</div>
        <div class="stat-sub">+{{ $alumnosNuevosMes ?? 0 }} este mes</div>
        <div class="stat-line"></div>
    </div>
    <div class="stat-card c-blue">
        <div class="stat-kicker">Bloques activos</div>
        <div class="stat-value">{{ $bloquesActivos ?? 0 }}</div>
        <div class="stat-sub">{{ $sedesActivasEnBloques ?? 0 }} sedes</div>
        <div class="stat-line"></div>
    </div>
    <div class="stat-card c-green">
        <div class="stat-kicker">Cuotas abonadas</div>
        <div class="stat-value">{{ $cuotasAbonadas ?? 0 }}</div>
        <div class="stat-sub">{{ ($pctAbonadas ?? 0) }}% del total</div>
        <div class="stat-line"></div>
    </div>
    <div class="stat-card c-amber">
        <div class="stat-kicker">Cuotas pendientes</div>
        <div class="stat-value">{{ $cuotasPendientes ?? 0 }}</div>
        <div class="stat-sub">{{ ($pctPendientes ?? 0) }}% del total</div>
        <div class="stat-line"></div>
    </div>
</div>

<div class="dash-grid">
    <div class="col-main">
        <div class="panel">
            <div class="panel-h">
                <div class="panel-h-title">Alumnos y bloques por profesor</div>
                <a class="panel-h-link" href="{{ url('/reportes/profesores') }}">ver todo →</a>
            </div>
            <div class="panel-b">
                <div class="prof-list">
                    @forelse(($profBase ?? collect()) as $p)
                        @php
                            $bar = ($maxAlumnosProfesor ?? 1) > 0 ? round((($p->alumnos_count ?? 0) / ($maxAlumnosProfesor ?? 1)) * 100) : 0;
                            $barClass = match ($p->avatar_class ?? 'av-orange') {
                                'av-blue' => 'bar-blue',
                                'av-green' => 'bar-green',
                                'av-purple' => 'bar-purple',
                                'av-amber' => 'bar-amber',
                                default => 'bar-orange',
                            };
                        @endphp
                        <div class="prof-row">
                            <div class="prof-avatar {{ $p->avatar_class ?? 'av-orange' }}">{{ $p->initials ?: 'P' }}</div>
                            <div>
                                <div class="prof-name">{{ $p->nombre }}</div>
                                <div class="prof-meta">{{ $p->sedes_str ?: '—' }}</div>
                            </div>
                            <div class="prof-kpi">
                                <div class="n">{{ $p->alumnos_count ?? 0 }}</div>
                                <div class="l">alumnos</div>
                            </div>
                            <div class="prof-kpi">
                                <div class="n">{{ $p->bloques_count ?? 0 }}</div>
                                <div class="l">bloques</div>
                            </div>
                            <div class="prof-bar-wrap" aria-hidden="true">
                                <div class="prof-bar {{ $barClass }}" style="--w: {{ $bar }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="muted">Sin datos de profesores.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="panel" style="margin-top: 14px;">
            <div class="panel-h">
                <div class="panel-h-title">Asistencias — bloques de esta semana</div>
                <a class="panel-h-link" href="{{ route('asistencias.create') }}">registrar →</a>
            </div>
            <div class="panel-b">
                <div class="asist-grid">
                    @forelse(($bloquesSemanales ?? collect())->take(6) as $row)
                        @php
                            $b = $row['bloque'];
                            $h = $row['horario'];
                            $pct = (int) ($row['pct'] ?? 0);
                            $barColor = $row['estado'] === 'Tomada' ? 'var(--green)' : ($row['estado'] === 'Incompleta' ? 'var(--accent2)' : 'var(--accent)');
                        @endphp
                        <a class="asist-card" href="{{ url('/asistencias/bloque/' . ($b->id ?? 0)) }}">
                            <div class="asist-top">
                                <div class="asist-sede">{{ $row['sede']?->nombre ?? '—' }}</div>
                                <div class="{{ $row['badge_class'] }}">{{ $row['estado'] }}</div>
                            </div>
                            <div class="asist-title">{{ $row['profesor']?->nombre ?? '—' }}</div>
                            <div class="asist-sub">
                                {{ $h?->nombre_dia ?? '—' }} {{ $h?->hora_inicio ? \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') : '' }} hs ·
                                {{ $b?->nombre ?? 'Bloque' }}
                            </div>
                            <div class="asist-bar" aria-hidden="true">
                                <span style="--w: {{ $pct }}%; --bar: {{ $barColor }}"></span>
                            </div>
                            <div class="asist-foot">
                                <span>Presentes</span>
                                <span>{{ $row['presentes'] ?? 0 }}/{{ $row['total_alumnos'] ?? 0 }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="muted">No hay horarios/bloques para mostrar.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <aside class="col-side">
        <div class="panel">
            <div class="panel-h">
                <div class="panel-h-title">Cobros pendientes</div>
            </div>
            <div class="panel-b">
                <div class="cuota-list">
                    @forelse(($cuotasPendientesList ?? collect()) as $c)
                        <div class="cuota-row">
                            <div class="cuota-dot {{ $c['dot_class'] ?? '' }}"></div>
                            <div>
                                <div class="cuota-name">{{ $c['alumno'] }}</div>
                                <div class="cuota-meta">{{ $c['sede'] }} · {{ $c['mes_label'] }}</div>
                            </div>
                            <div class="cuota-monto">${{ number_format($c['monto'] ?? 0, 0, ',', '.') }}</div>
                        </div>
                    @empty
                        <div class="muted">No hay cuotas pendientes para mostrar.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-h">
                <div class="panel-h-title">Recaudación — últimas semanas</div>
            </div>
            <div class="chart-wrap">
                <canvas id="recaudacionChart" height="130"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="panel-h">
                <div class="panel-h-title">Acciones rápidas</div>
            </div>
            <div class="panel-b">
                <div class="qa-grid">
                    <a class="qa-btn" href="{{ route('alumnos.create') }}">
                        <span class="qa-ic"><i class="bi bi-person-plus"></i></span>
                        Nuevo alumno
                    </a>
                    <a class="qa-btn" href="{{ route('asistencias.create') }}">
                        <span class="qa-ic"><i class="bi bi-check2-square"></i></span>
                        Tomar asistencia
                    </a>
                    <a class="qa-btn" href="{{ route('pagos.create') }}">
                        <span class="qa-ic"><i class="bi bi-receipt"></i></span>
                        Registrar pago
                    </a>
                    <a class="qa-btn" href="{{ route('reportes.index') }}">
                        <span class="qa-ic"><i class="bi bi-bar-chart"></i></span>
                        Ver reportes
                    </a>
                </div>
            </div>
        </div>
    </aside>
</div>

@push('scripts')
<script>
    (function () {
        const el = document.getElementById('recaudacionChart');
        if (!el) return;
        const values = JSON.parse('{!! json_encode(array_values(($recaudacion ?? collect([0,0,0,0,0,0]))->toArray())) !!}');
        const css = getComputedStyle(document.documentElement);
        const s3 = css.getPropertyValue('--s3').trim() || '#2a2927';
        const accent = css.getPropertyValue('--accent').trim() || '#e85d2b';
        new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Sem 1','Sem 2','Sem 3','Sem 4','Sem 5','Sem 6'],
                datasets: [{
                    data: values,
                    backgroundColor: [s3, s3, s3, 'rgba(232,93,43,0.35)', 'rgba(232,93,43,0.55)', accent],
                    borderColor: 'rgba(255,255,255,0.10)',
                    borderWidth: 1,
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                scales: {
                    x: { ticks: { color: 'rgba(255,255,255,0.45)' }, grid: { color: 'rgba(255,255,255,0.06)' } },
                    y: { ticks: { color: 'rgba(255,255,255,0.45)' }, grid: { color: 'rgba(255,255,255,0.06)' } }
                }
            }
        });
    })();
</script>
@endpush
@endsection

