@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Tablero')

@section('content')
@php
    $nombreSaludo = trim($adminNombre ?? '') ?: 'Administrador';
    $primerNombre = explode(' ', $nombreSaludo)[0] ?: 'Administrador';
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $user = auth()->user();

    $clasesPendientesSemana = ($bloquesSemanales ?? collect())
        ->filter(fn ($r) => in_array($r['estado'] ?? '', ['Pendiente', 'Incompleta'], true))
        ->count();
    $clasesTomadasSemana = ($bloquesSemanales ?? collect())
        ->filter(fn ($r) => ($r['estado'] ?? '') === 'Tomada')
        ->count();

    $atencionExtra = collect($atencionHoy ?? []);
    if ($clasesPendientesSemana > 0) {
        $atencionExtra = $atencionExtra->prepend([
            'href' => route('asistencias.index'),
            'title' => $clasesPendientesSemana.' clase'.($clasesPendientesSemana === 1 ? '' : 's').' de la semana sin lista completa',
            'hint' => 'Asistencia',
        ]);
    }

    $accionesRapidas = array_values(array_filter([
        $user->tieneAccesoModulo('admin.asistencias') ? ['href' => route('asistencias.index'), 'icon' => 'bi-check2-square', 'title' => 'Asistencias', 'desc' => 'Pasar lista'] : null,
        $user->tieneAccesoModulo('admin.alumnos') ? ['href' => route('alumnos.index'), 'icon' => 'bi-people', 'title' => 'Alumnos', 'desc' => 'Buscar ficha'] : null,
        $user->tieneAccesoModulo('comprobantes') ? ['href' => route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']), 'icon' => 'bi-inbox', 'title' => 'Comprobantes', 'desc' => (($comprobantesPendientesCount ?? 0) > 0 ? $comprobantesPendientesCount.' sin revisar' : 'Revisar envíos')] : null,
        $user->tieneAccesoModulo('admin.pagos') ? ['href' => route('pagos.index'), 'icon' => 'bi-receipt', 'title' => 'Registrar pago', 'desc' => 'Cobro al día'] : null,
        $user->tieneAccesoModulo('admin.eventos') ? ['href' => route('eventos.index'), 'icon' => 'bi-calendar-event', 'title' => 'Eventos', 'desc' => 'Agenda cercana'] : null,
        $user->tieneAccesoModulo('admin.villa_gesell') ? ['href' => route('villa-gesell.index'), 'icon' => 'bi-sun', 'title' => 'Villa Gesell', 'desc' => 'Gira 2027'] : null,
    ]));
@endphp

<div class="hub hub--command">
    @include('partials.hub-hint', [
        'title' => 'Centro de control',
        'body' => 'Primero lo urgente (Hoy). Después acciones rápidas. Ctrl+K busca alumno, bloque o módulo.',
        'helpLabel' => 'Cómo pagar y cobrar',
    ])

    <div class="hub-hero">
        <div class="hub-hero-main">
            <p class="hub-eyebrow">La Chilinga · centro de control</p>
            <h1 class="hub-greeting">
                {{ $saludo }}, <em>{{ $primerNombre }}</em>.
            </h1>
            <p class="hub-lead">
                {{ $sedesActivasEnBloques ?? 0 }} sedes activas ·
                {{ number_format($asistenciasMes ?? 0, 0, ',', '.') }} asistencias este mes ·
                {{ $dashboardAmbito ?? 'Vista general de la escuela' }}.
            </p>
        </div>
        <dl class="hub-meta">
            <div>
                <dt>Fecha</dt>
                <dd>{{ now()->locale('es')->translatedFormat('d/m/Y') }}</dd>
            </div>
            <div>
                <dt>Rol</dt>
                <dd>{{ $user->etiquetaRol() }}</dd>
            </div>
            <div>
                <dt>Semana</dt>
                <dd>
                    @if($clasesPendientesSemana > 0)
                        <span class="hub-status-dot hub-status-dot--alert" aria-hidden="true"></span>
                        {{ $clasesPendientesSemana }} pendientes
                    @else
                        <span class="hub-status-dot hub-status-dot--ok" aria-hidden="true"></span>
                        Lista al día
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    @if($atencionExtra->isNotEmpty())
    <section class="hub-section hub-section--priority" aria-labelledby="hub-hoy">
        <header class="hub-section-head">
            <h2 id="hub-hoy" class="hub-section-title"><span class="hub-section-code">Hoy</span> Necesita atención</h2>
        </header>
        <div class="hub-modules">
            @foreach($atencionExtra as $item)
            <a class="hub-module hub-module--alert" href="{{ $item['href'] }}">
                <span class="hub-module-icon" aria-hidden="true"><i class="bi bi-lightning"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">{{ $item['title'] }}</span>
                    <span class="hub-module-desc">{{ $item['hint'] }}</span>
                </span>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    @if(count($accionesRapidas) > 0)
    <section class="hub-section" aria-labelledby="hub-acciones">
        <header class="hub-section-head">
            <h2 id="hub-acciones" class="hub-section-title"><span class="hub-section-code">Hacer</span> Acciones rápidas</h2>
            <span class="hub-section-count">Uso diario</span>
        </header>
        <div class="hub-modules hub-modules--compact">
            @foreach($accionesRapidas as $item)
            <a class="hub-module" href="{{ $item['href'] }}">
                <span class="hub-module-icon" aria-hidden="true"><i class="bi {{ $item['icon'] }}"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">{{ $item['title'] }}</span>
                    <span class="hub-module-desc">{{ $item['desc'] }}</span>
                </span>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    <div class="hub-kpis" role="list">
        <a class="hub-kpi" href="{{ route('alumnos.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Alumnos activos</span>
                <span class="hub-kpi-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $alumnosActivos ?? 0 }}</div>
            @if(($alumnosNuevosMes ?? 0) > 0)
                <span class="hub-kpi-badge is-ok">+{{ $alumnosNuevosMes }} este mes</span>
            @else
                <span class="hub-kpi-badge">Plantel vigente</span>
            @endif
        </a>
        <a class="hub-kpi" href="{{ route('bloques.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Bloques activos</span>
                <span class="hub-kpi-icon"><i class="bi bi-collection" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $bloquesActivos ?? 0 }}</div>
            <span class="hub-kpi-badge">{{ $sedesActivasEnBloques ?? 0 }} sedes</span>
        </a>
        <a class="hub-kpi" href="{{ route('pagos.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Cobrado del mes</span>
                <span class="hub-kpi-icon"><i class="bi bi-currency-dollar" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value hub-kpi-value--sm">${{ number_format($cobradoMes ?? 0, 0, ',', '.') }}</div>
            <span class="hub-kpi-badge is-ok">{{ $pctAbonadas ?? 0 }}% cuotas</span>
        </a>
        <a class="hub-kpi" href="{{ route('cuotas.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Cuotas pendientes</span>
                <span class="hub-kpi-icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $cuotasPendientes ?? 0 }}</div>
            @if(($cuotasPendientes ?? 0) > 0)
                <span class="hub-kpi-badge is-alert">{{ $pctPendientes ?? 0 }}% del mes</span>
            @else
                <span class="hub-kpi-badge is-ok">Al día</span>
            @endif
        </a>
        <a class="hub-kpi" href="{{ route('eventos.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Próximos eventos</span>
                <span class="hub-kpi-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $proximosEventosCount ?? 0 }}</div>
            <span class="hub-kpi-badge">Agenda cercana</span>
        </a>
        <a class="hub-kpi" href="{{ route('comprobantes-cuota-alumnos.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Comprobantes</span>
                <span class="hub-kpi-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $comprobantesPendientesCount ?? 0 }}</div>
            @if(($comprobantesPendientesCount ?? 0) > 0)
                <span class="hub-kpi-badge is-alert">Sin revisar</span>
            @else
                <span class="hub-kpi-badge is-ok">Sin pendientes</span>
            @endif
        </a>
    </div>

    <section class="hub-section" aria-labelledby="hub-semana">
        <header class="hub-section-head">
            <h2 id="hub-semana" class="hub-section-title">
                <span class="hub-section-code">Semana</span>
                Clases de esta semana
            </h2>
            <span class="hub-section-count">{{ $clasesTomadasSemana }} tomadas · {{ $clasesPendientesSemana }} por completar</span>
        </header>
        <div class="hub-panel mb-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Día / hora</th>
                            <th>Bloque</th>
                            <th>Sede</th>
                            <th>Profesor</th>
                            <th>Estado</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($bloquesSemanales ?? collect()) as $row)
                            @php
                                $h = $row['horario'];
                                $fecha = $row['fecha_clase'] ?? null;
                                $diaLabel = $fecha ? $fecha->locale('es')->translatedFormat('D d/m') : '—';
                                $horaLabel = $h->hora_inicio ? substr((string) $h->hora_inicio, 0, 5) : '';
                                $bloqueId = $row['bloque']->id ?? null;
                                $fechaStr = $fecha ? $fecha->toDateString() : now()->toDateString();
                            @endphp
                            <tr>
                                <td class="text-nowrap">
                                    <span class="fw-semibold">{{ $diaLabel }}</span>
                                    @if($horaLabel)<span class="text-muted"> · {{ $horaLabel }}</span>@endif
                                </td>
                                <td class="fw-semibold">{{ $row['bloque']->nombre ?? '—' }}</td>
                                <td class="text-muted">{{ $row['sede']->nombre ?? '—' }}</td>
                                <td class="text-muted">{{ $row['profesor']->nombre ?? '—' }}</td>
                                <td>
                                    <span class="{{ $row['badge_class'] ?? 'badge-pend' }}">{{ $row['estado'] }}</span>
                                    @if(($row['total_alumnos'] ?? 0) > 0 && ($row['estado'] ?? '') === 'Tomada')
                                        <span class="small text-muted ms-1">{{ $row['presentes'] }}/{{ $row['total_alumnos'] }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($bloqueId && in_array($row['estado'] ?? '', ['Pendiente', 'Incompleta'], true))
                                        <a class="btn btn-sm btn-primary" href="{{ route('asistencias.create', ['bloque_id' => $bloqueId, 'fecha' => $fechaStr]) }}">Pasar lista</a>
                                    @elseif($bloqueId)
                                        <a class="hub-panel-link" href="{{ route('asistencias.index', ['bloque_id' => $bloqueId]) }}">Ver →</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted py-3">No hay clases programadas esta semana.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="hub-panels">
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div>
                    <div class="hub-panel-title">Cobros pendientes</div>
                    <div class="hub-panel-sub">Cuotas del mes aún abiertas</div>
                </div>
                <a href="{{ route('cuotas.index') }}" class="hub-panel-link">Ver todas →</a>
            </div>
            @forelse(($cuotasPendientesList ?? collect()) as $fila)
                <div class="hub-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $fila['alumno'] ?? 'Alumno' }}</div>
                        <div class="small text-muted">
                            {{ $fila['sede'] ?? '—' }}
                            @if(!empty($fila['cuota_nombre'])) · {{ $fila['cuota_nombre'] }}@endif
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-semibold font-monospace">${{ number_format($fila['monto'] ?? 0, 0, ',', '.') }}</div>
                        @if(!empty($fila['dot_class']))
                            <span class="hub-kpi-badge is-alert">Vencida</span>
                        @else
                            <span class="hub-kpi-badge">Pendiente</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0 py-2">No hay cobros pendientes listados.</p>
            @endforelse
            @if(($cuotasPendientes ?? 0) > 0)
                <div class="mt-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('pagos.create') }}">Registrar pago</a>
                </div>
            @endif
        </div>
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div class="hub-panel-title">Comprobantes sin revisar</div>
                <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="hub-panel-link">Ir al listado →</a>
            </div>
            @forelse(($comprobantesPendientesList ?? collect()) as $comp)
                <div class="hub-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $comp->alumno?->nombre_apellido ?? 'Alumno' }}</div>
                        <div class="small text-muted">{{ $comp->created_at?->diffForHumans() ?? '' }}</div>
                    </div>
                    <span class="hub-kpi-badge is-alert">Pendiente</span>
                </div>
            @empty
                <p class="text-muted small mb-0 py-2">No hay comprobantes pendientes.</p>
            @endforelse
        </div>
    </div>

    <section class="hub-section" aria-labelledby="hub-sec-ops">
        <header class="hub-section-head">
            <h2 id="hub-sec-ops" class="hub-section-title">
                <span class="hub-section-code">Contexto</span>
                Operación del mes
            </h2>
        </header>

        <div class="hub-panels">
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div>
                        <div class="hub-panel-title">Ingresos y gastos</div>
                        <div class="hub-panel-sub">Últimos 6 meses</div>
                    </div>
                </div>
                <div class="hub-chart"><canvas id="dashChartFinanzas" aria-label="Gráfico de ingresos y gastos"></canvas></div>
            </div>
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div>
                        <div class="hub-panel-title">Alumnos por sede</div>
                        <div class="hub-panel-sub">Activos hoy</div>
                    </div>
                </div>
                <div class="hub-chart"><canvas id="dashChartSedes" aria-label="Gráfico de alumnos por sede"></canvas></div>
            </div>
        </div>

        <div class="hub-panels">
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div class="hub-panel-title">Próximos eventos</div>
                    <a href="{{ route('eventos.index') }}" class="hub-panel-link">Ver todos →</a>
                </div>
                @forelse(($proximosEventos ?? collect()) as $ev)
                    <div class="hub-list-item">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $ev->titulo ?? $ev->nombre ?? 'Evento' }}</div>
                            <div class="small text-muted">
                                {{ $ev->fecha?->locale('es')->translatedFormat('d M Y') ?? '—' }}
                                @if(!empty($ev->lugar)) · {{ $ev->lugar }} @endif
                            </div>
                        </div>
                        <span class="hub-kpi-badge">Próximo</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0 py-2">No hay eventos próximos.</p>
                @endforelse
            </div>
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div>
                        <div class="hub-panel-title">Bloques — cupo</div>
                        <div class="hub-panel-sub">Ocupación</div>
                    </div>
                    <a href="{{ route('bloques.index') }}" class="hub-panel-link">Ver →</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Bloque</th>
                                <th>Sede</th>
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
                                    <td>
                                        <span class="cupo-bar {{ $barClass }}" aria-hidden="true"><i style="width:{{ $pct }}%"></i></span>
                                        <span class="small font-monospace">{{ $pct }}%</span>
                                    </td>
                                    <td class="text-end font-monospace">{{ $activos }}/{{ $cupo }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Sin bloques activos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const css = getComputedStyle(document.documentElement);
    const muted = css.getPropertyValue('--muted-2').trim() || 'rgba(255,255,255,.45)';
    const line = 'rgba(233,237,245,0.08)';
    const brick = css.getPropertyValue('--brick').trim() || '#3e7bfa';
    const verdigris = css.getPropertyValue('--verdigris').trim() || '#22c55e';
    const brass = css.getPropertyValue('--brass').trim() || '#3e7bfa';
    const s3 = css.getPropertyValue('--surface-3').trim() || '#1e2a45';

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
    if (fin && window.Chart) {
        new Chart(fin.getContext('2d'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels ?? []) !!},
                datasets: [
                    { label: 'Ingresos', data: {!! json_encode($chartIngresos ?? []) !!}, backgroundColor: verdigris, borderRadius: 6 },
                    { label: 'Gastos', data: {!! json_encode($chartGastos ?? []) !!}, backgroundColor: brick, borderRadius: 6 },
                ],
            },
            options: chartDefaults,
        });
    }

    const sedes = document.getElementById('dashChartSedes');
    if (sedes && window.Chart) {
        const sedeData = {!! json_encode(($alumnosPorSedeChart ?? collect())->values()) !!};
        new Chart(sedes.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: sedeData.map((r) => r.nombre),
                datasets: [{
                    data: sedeData.map((r) => r.total),
                    backgroundColor: [brass, verdigris, brick, '#8b5cf6', '#5b9ef0', s3],
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
})();
</script>
@endpush
