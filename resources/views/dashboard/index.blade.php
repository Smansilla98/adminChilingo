@extends('layouts.app')

@section('title', 'Inicio · La Chilinga')
@section('page-title', 'Inicio')

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
        $user->tieneAccesoModulo('admin.pagos') ? ['href' => route('pagos.index'), 'icon' => 'bi-receipt', 'title' => 'Pagos', 'desc' => 'Cobros registrados'] : null,
        $user->tieneAccesoModulo('admin.eventos') ? ['href' => route('eventos.index'), 'icon' => 'bi-calendar-event', 'title' => 'Eventos', 'desc' => 'Agenda cercana'] : null,
        $user->tieneAccesoModulo('admin.villa_gesell') ? ['href' => route('villa-gesell.index'), 'icon' => 'bi-sun', 'title' => 'Villa Gesell', 'desc' => 'Gira 2027'] : null,
    ]));
@endphp

<div class="hub hub--command">
    @include('partials.hub-hint', [
        'title' => 'Tu tablero',
        'body' => 'Arriba ves los números del mes y lo que necesita atención hoy. Con Ctrl+K buscás cualquier alumno, bloque o sección.',
        'helpLabel' => 'Cómo pagar y cobrar',
    ])

    <div class="hub-hero">
        <div class="hub-hero-main">
            <h1 class="hub-greeting">{{ $saludo }}, {{ $primerNombre }}</h1>
            <p class="hub-lead">
                {{ ucfirst(now()->locale('es')->translatedFormat('l j \d\e F')) }} ·
                {{ $sedesActivasEnBloques ?? 0 }} sedes activas ·
                {{ number_format($asistenciasMes ?? 0, 0, ',', '.') }} asistencias este mes
                @if(!empty($dashboardAmbito)) · {{ $dashboardAmbito }}@endif
            </p>
        </div>
        <div class="ito-page-actions">
            @if($user->tieneAccesoModulo('admin.alumnos'))
                <a href="{{ route('alumnos.create') }}" class="btn btn-outline-secondary"><i class="bi bi-person-plus" aria-hidden="true"></i> Nuevo alumno</a>
            @endif
            @if($user->tieneAccesoModulo('admin.pagos'))
                <a href="{{ route('pagos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Registrar pago</a>
            @endif
        </div>
    </div>

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
            <span class="hub-kpi-badge is-ok">{{ $pctAbonadas ?? 0 }}% de las cuotas</span>
        </a>
        <a class="hub-kpi" href="{{ route('cuotas.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Cuotas pendientes</span>
                <span class="hub-kpi-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
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

    @if($atencionExtra->isNotEmpty())
    <section class="hub-section hub-section--priority" aria-labelledby="hub-hoy">
        <header class="hub-section-head">
            <h2 id="hub-hoy" class="hub-section-title">Necesita atención</h2>
            <span class="hub-section-count">{{ $atencionExtra->count() }} {{ $atencionExtra->count() === 1 ? 'tema' : 'temas' }}</span>
        </header>
        <div class="hub-modules">
            @foreach($atencionExtra as $item)
            <a class="hub-module hub-module--alert" href="{{ $item['href'] }}">
                <span class="hub-module-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">{{ $item['title'] }}</span>
                    <span class="hub-module-desc">{{ $item['hint'] }}</span>
                </span>
                <i class="bi bi-chevron-right ms-auto text-muted" aria-hidden="true"></i>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    <div class="hub-grid-main">
        <section class="hub-section" aria-labelledby="hub-semana">
            <header class="hub-section-head">
                <h2 id="hub-semana" class="hub-section-title">Clases de esta semana</h2>
                <span class="hub-section-count">{{ $clasesTomadasSemana }} tomadas · {{ $clasesPendientesSemana }} por completar</span>
            </header>
            <div class="hub-panel hub-panel--flush">
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-ito-no-cards>
                        <thead>
                            <tr>
                                <th>Día y hora</th>
                                <th>Bloque</th>
                                <th class="d-none d-md-table-cell">Profesor</th>
                                <th>Estado</th>
                                <th class="text-end"><span class="visually-hidden">Acción</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($bloquesSemanales ?? collect()) as $row)
                                @php
                                    $h = $row['horario'];
                                    $fecha = $row['fecha_clase'] ?? null;
                                    $diaLabel = $fecha ? ucfirst($fecha->locale('es')->translatedFormat('D d/m')) : '—';
                                    $horaLabel = $h->hora_inicio ? \Illuminate\Support\Carbon::parse($h->hora_inicio)->format('H:i') : '';
                                    $bloqueId = $row['bloque']->id ?? null;
                                    $fechaStr = $fecha ? $fecha->toDateString() : now()->toDateString();
                                    $tono = match ($row['estado'] ?? '') {
                                        'Tomada' => 'success',
                                        'Incompleta' => 'warning',
                                        'Pendiente' => 'info',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-nowrap">
                                        <span class="fw-semibold">{{ $diaLabel }}</span>
                                        @if($horaLabel)<span class="text-muted"> · {{ $horaLabel }}</span>@endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $row['bloque']->nombre ?? '—' }}</span>
                                        <span class="d-block small text-muted">{{ $row['sede']->nombre ?? '—' }}</span>
                                    </td>
                                    <td class="text-muted d-none d-md-table-cell">{{ $row['profesor']->nombre ?? '—' }}</td>
                                    <td>
                                        <x-ito.status :tone="$tono" :label="$row['estado'] ?? '—'" />
                                        @if(($row['total_alumnos'] ?? 0) > 0 && ($row['estado'] ?? '') === 'Tomada')
                                            <span class="small text-muted ms-1">{{ $row['presentes'] }}/{{ $row['total_alumnos'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if($bloqueId && in_array($row['estado'] ?? '', ['Pendiente', 'Incompleta'], true))
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('asistencias.create', ['bloque_id' => $bloqueId, 'fecha' => $fechaStr]) }}">Pasar lista</a>
                                        @elseif($bloqueId)
                                            <a class="btn btn-sm btn-ghost" href="{{ route('asistencias.index', ['bloque_id' => $bloqueId]) }}">Ver planilla</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ito-empty">No hay clases programadas esta semana.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="hub-section" aria-labelledby="hub-agenda">
            <header class="hub-section-head">
                <h2 id="hub-agenda" class="hub-section-title">Próximos eventos</h2>
                <a href="{{ route('eventos.index') }}" class="hub-panel-link">Ver agenda</a>
            </header>
            <div class="hub-panel">
                @forelse(($proximosEventos ?? collect()) as $ev)
                    <div class="hub-list-item">
                        <span class="agenda-date" aria-hidden="true">
                            <span class="agenda-date-d">{{ $ev->fecha?->format('d') ?? '—' }}</span>
                            <span class="agenda-date-m">{{ $ev->fecha?->locale('es')->translatedFormat('M') ?? '' }}</span>
                        </span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-semibold text-truncate">{{ $ev->titulo ?? $ev->nombre ?? 'Evento' }}</span>
                            <span class="d-block small text-muted text-truncate">
                                {{ $ev->fecha ? ucfirst($ev->fecha->locale('es')->translatedFormat('l j \d\e F')) : '—' }}
                                @if(!empty($ev->lugar)) · {{ $ev->lugar }}@endif
                            </span>
                        </span>
                    </div>
                @empty
                    <x-ito.empty icon="bi-calendar-event" title="Sin eventos próximos" description="Cuando cargues un evento o un show va a aparecer acá."
                        :action-href="$user->tieneAccesoModulo('admin.eventos') ? route('eventos.create') : null" action-label="Nuevo evento" />
                @endforelse
            </div>
        </section>
    </div>

    <div class="hub-panels">
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div>
                    <div class="hub-panel-title">Cobros pendientes</div>
                    <div class="hub-panel-sub">Cuotas del mes aún abiertas</div>
                </div>
                <a href="{{ route('cuotas.index') }}" class="hub-panel-link">Ver cuotas</a>
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
                            <x-ito.status tone="danger" label="Vencida" />
                        @else
                            <x-ito.status tone="warning" label="Pendiente" />
                        @endif
                    </div>
                </div>
            @empty
                <x-ito.empty icon="bi-check2-circle" title="Sin cobros pendientes" description="Todas las cuotas del mes están al día." />
            @endforelse
            @if(($cuotasPendientes ?? 0) > 0 && $user->tieneAccesoModulo('admin.pagos'))
                <div class="mt-3">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('pagos.create') }}">Registrar pago</a>
                </div>
            @endif
        </div>
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div>
                    <div class="hub-panel-title">Comprobantes sin revisar</div>
                    <div class="hub-panel-sub">Enviados por alumnos y familias</div>
                </div>
                <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="hub-panel-link">Ir al listado</a>
            </div>
            @forelse(($comprobantesPendientesList ?? collect()) as $comp)
                <div class="hub-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $comp->alumno?->nombre_apellido ?? 'Alumno' }}</div>
                        <div class="small text-muted">{{ $comp->created_at?->locale('es')->diffForHumans() ?? '' }}</div>
                    </div>
                    <x-ito.status tone="warning" label="Pendiente" />
                </div>
            @empty
                <x-ito.empty icon="bi-inbox" title="Nada para revisar" description="No hay comprobantes esperando revisión." />
            @endforelse
        </div>
    </div>

    <section class="hub-section" aria-labelledby="hub-sec-ops">
        <header class="hub-section-head">
            <h2 id="hub-sec-ops" class="hub-section-title">Operación del mes</h2>
        </header>

        <div class="hub-panels">
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div>
                        <div class="hub-panel-title">Ingresos y gastos</div>
                        <div class="hub-panel-sub">Últimos 6 meses</div>
                    </div>
                </div>
                <div class="hub-chart"><canvas id="dashChartFinanzas" role="img" aria-label="Gráfico de barras: ingresos y gastos de los últimos 6 meses"></canvas></div>
            </div>
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div>
                        <div class="hub-panel-title">Alumnos activos por sede</div>
                        <div class="hub-panel-sub">Hoy</div>
                    </div>
                </div>
                <div class="hub-chart"><canvas id="dashChartSedes" role="img" aria-label="Gráfico de barras: alumnos activos por sede"></canvas></div>
            </div>
        </div>

        <div class="hub-panels">
            <div class="hub-panel hub-panel--flush">
                <div class="hub-panel-head">
                    <div>
                        <div class="hub-panel-title">Ocupación de bloques</div>
                        <div class="hub-panel-sub">Alumnos activos sobre cupo</div>
                    </div>
                    <a href="{{ route('bloques.index') }}" class="hub-panel-link">Ver bloques</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-ito-no-cards>
                        <thead>
                            <tr>
                                <th>Bloque</th>
                                <th>Ocupación</th>
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
                                    <td>
                                        <span class="fw-semibold">{{ $bloque->nombre }}</span>
                                        <span class="d-block small text-muted">{{ $bloque->sede?->nombre ?? '—' }}</span>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="cupo-bar {{ $barClass }}" aria-hidden="true"><i style="width:{{ $pct }}%"></i></span>
                                        <span class="small font-monospace">{{ $pct }}%</span>
                                    </td>
                                    <td class="text-end font-monospace">{{ $activos }}/{{ $cupo }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="ito-empty">Sin bloques activos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($accionesRapidas) > 0)
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <div class="hub-panel-title">Accesos rápidos</div>
                </div>
                <div class="hub-shortcuts">
                    @foreach($accionesRapidas as $item)
                    <a class="hub-shortcut" href="{{ $item['href'] }}">
                        <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <span class="d-block fw-semibold">{{ $item['title'] }}</span>
                            <span class="d-block small text-muted">{{ $item['desc'] }}</span>
                        </span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const css = getComputedStyle(document.documentElement);
    const v = (n, f) => css.getPropertyValue(n).trim() || f;
    const muted = v('--muted', '#5b6472');
    const grid = v('--border', '#e2e5ea');
    const success = v('--success', '#1e7b34');
    const accent = v('--accent', '#f26422');
    const info = v('--info', '#0b7593');
    if (!window.Chart) return;
    Chart.defaults.color = muted;
    Chart.defaults.font.family = "'Manrope', system-ui, sans-serif";
    const peso = (n) => '$' + Number(n || 0).toLocaleString('es-AR');

    const fin = document.getElementById('dashChartFinanzas');
    if (fin) {
        new Chart(fin.getContext('2d'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels ?? []) !!},
                datasets: [
                    { label: 'Ingresos', data: {!! json_encode($chartIngresos ?? []) !!}, backgroundColor: success, borderRadius: 4, maxBarThickness: 28 },
                    { label: 'Gastos', data: {!! json_encode($chartGastos ?? []) !!}, backgroundColor: accent, borderRadius: 4, maxBarThickness: 28 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + peso(c.raw) } },
                },
                scales: {
                    x: { grid: { display: false }, border: { color: grid } },
                    y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { callback: (val) => peso(val) } },
                },
            },
        });
    }

    const sedes = document.getElementById('dashChartSedes');
    if (sedes) {
        const sedeData = {!! json_encode(($alumnosPorSedeChart ?? collect())->values()) !!};
        new Chart(sedes.getContext('2d'), {
            type: 'bar',
            data: {
                labels: sedeData.map((r) => r.nombre),
                datasets: [{ label: 'Alumnos', data: sedeData.map((r) => r.total), backgroundColor: info, borderRadius: 4, maxBarThickness: 22 }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0 } },
                    y: { grid: { display: false }, border: { color: grid } },
                },
            },
        });
    }
})();
</script>
@endpush
