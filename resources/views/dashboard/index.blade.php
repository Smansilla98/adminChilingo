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

<div class="panel mod-panel" id="listados-modulos">
    <div class="panel-h">
        <div class="panel-h-title">Accesos a cada sección</div>
        <span class="muted small">Acceso directo al índice (listado) de cada sección.</span>
    </div>
    <div class="panel-b">
        <div class="index-mod-grid d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('programa.index') }}">Programa</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('programa.partituras.index') }}">Partituras y recursos</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendario.index') }}">Calendario</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('comprobantes-cuota-alumnos.index') }}">Comprobantes cuota</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('alumnos.index') }}">Alumnos</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('profesores.index') }}">Profesores</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sedes.index') }}">Sedes</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('bloques.index') }}">Bloques</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('eventos.index') }}">Eventos</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('asistencias.index') }}">Asistencias</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('shows.index') }}">Shows</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('cuotas.index') }}">Cuotas</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('pagos.index') }}">Pagos</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('facturacion-mensual.index') }}">Facturación mensual</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('inventarios.index') }}">Inventarios</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('plan-compras.index') }}">Plan de compras</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('ordenes-compra.index') }}">Órdenes de compra</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('gastos.index') }}">Gastos</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('reportes.index') }}">Reportes</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('reportes.profesores') }}">Listado por profesor</a>
        </div>
    </div>
</div>

<div class="panel mod-panel" id="modulos">
    <div class="panel-h">
        <div class="panel-h-title">Quién puede ver qué</div>
    </div>
    <div class="panel-b mod-nav-wrap">
        <div class="mod-nav">
            <div class="mod-col">
                <div class="mod-col-title">General</div>
                <div class="mod-links">
                    <a href="{{ route('programa.index') }}">Programa oficial</a>
                    <a href="{{ route('programa.partituras.index') }}">Partituras y recursos</a>
                    <a href="{{ route('calendario.index') }}">Calendario</a>
                    <a href="{{ route('comprobantes-cuota-alumnos.index') }}">Comprobantes de cuota (alumnos)</a>
                    <a href="{{ route('comprobante-cuota-public.create') }}" target="_blank" rel="noopener">Formulario público de comprobante</a>
                </div>
            </div>
            <div class="mod-col">
                <div class="mod-col-title">Personas y sedes</div>
                <div class="mod-links">
                    <a href="{{ route('alumnos.index') }}">Alumnos</a>
                    <a href="{{ route('alumnos.create') }}">Nuevo alumno</a>
                    <a href="{{ route('alumnos.import.form') }}">Importar alumnos</a>
                    <a href="{{ route('alumnos.export') }}">Exportar alumnos (Excel)</a>
                    <a href="{{ route('profesores.index') }}">Profesores</a>
                    <a href="{{ route('sedes.index') }}">Sedes</a>
                </div>
            </div>
            <div class="mod-col">
                <div class="mod-col-title">Bloques y clases</div>
                <div class="mod-links">
                    <a href="{{ route('bloques.index') }}">Bloques</a>
                    <a href="{{ route('bloques.create') }}">Nuevo bloque</a>
                    <a href="{{ route('eventos.index') }}">Eventos</a>
                    <a href="{{ route('asistencias.index') }}">Asistencias</a>
                    <a href="{{ route('asistencias.create') }}">Registrar asistencia</a>
                    <a href="{{ route('shows.index') }}">Shows</a>
                </div>
            </div>
            <div class="mod-col">
                <div class="mod-col-title">Cuotas y pagos</div>
                <div class="mod-links">
                    <a href="{{ route('cuotas.index') }}">Cuotas</a>
                    <a href="{{ route('pagos.index') }}">Pagos</a>
                    <a href="{{ route('pagos.create') }}">Registrar pago</a>
                    <a href="{{ route('facturacion-mensual.index') }}">Facturación mensual</a>
                </div>
            </div>
            <div class="mod-col">
                <div class="mod-col-title">Inventario y compras</div>
                <div class="mod-links">
                    <a href="{{ route('inventarios.index') }}">Inventarios</a>
                    <a href="{{ route('plan-compras.index') }}">Plan de compras</a>
                    <a href="{{ route('ordenes-compra.index') }}">Órdenes de compra</a>
                    <a href="{{ route('gastos.index') }}">Gastos</a>
                </div>
            </div>
            <div class="mod-col">
                <div class="mod-col-title">Reportes</div>
                <div class="mod-links">
                    <a href="{{ route('reportes.index') }}">Reportes</a>
                    <a href="{{ route('reportes.profesores') }}">Alumnos por profesor</a>
                </div>
            </div>
        </div>
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
                            $barColor = match ($row['estado'] ?? '') {
                                'Tomada' => 'var(--green)',
                                'Incompleta' => 'var(--accent2)',
                                'Próxima' => 'var(--s3)',
                                default => 'var(--accent)',
                            };
                            $fechaClaseCard = $row['fecha_clase'] ?? null;
                            $urlAsist = route('asistencias.create', array_filter([
                                'bloque_id' => $b->id ?? null,
                                'fecha' => $fechaClaseCard ? $fechaClaseCard->format('Y-m-d') : null,
                            ]));
                        @endphp
                        <a class="asist-card" href="{{ $urlAsist }}">
                            <div class="asist-top">
                                <div class="asist-sede">{{ $row['sede']?->nombre ?? '—' }}</div>
                                <div class="{{ $row['badge_class'] }}">{{ $row['estado'] }}</div>
                            </div>
                            <div class="asist-title">{{ $row['profesor']?->nombre ?? '—' }}</div>
                            <div class="asist-sub">
                                {{ $h?->nombre_dia ?? '—' }} {{ $h?->hora_inicio ? \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') : '' }} hs ·
                                {{ $b?->nombre ?? 'Bloque' }}
                                @if($fechaClaseCard)
                                · clase {{ $fechaClaseCard->format('d/m') }}
                                @endif
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
                                <div class="cuota-meta">{{ $c['sede'] }} · {{ $c['cuota_nombre'] ?? $c['mes_label'] }}</div>
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

