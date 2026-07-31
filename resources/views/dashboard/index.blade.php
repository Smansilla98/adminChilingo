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

    $hubModules = array_values(array_filter([
        [
            'code' => '01',
            'label' => 'Académico',
            'items' => array_values(array_filter([
                $user->tieneAccesoModulo('admin.alumnos') ? ['href' => route('alumnos.index'), 'icon' => 'bi-people', 'title' => 'Alumnos', 'desc' => 'Altas, bajas y ficha del alumnado'] : null,
                $user->tieneAccesoModulo('admin.profesores') ? ['href' => route('profesores.index'), 'icon' => 'bi-person-badge', 'title' => 'Profesores', 'desc' => 'Plantel docente y asignación'] : null,
                $user->tieneAccesoModulo('admin.bloques') ? ['href' => route('bloques.index'), 'icon' => 'bi-collection', 'title' => 'Bloques', 'desc' => 'Grupos, cupos y horarios'] : null,
                $user->tieneAccesoModulo('admin.sedes') ? ['href' => route('sedes.index'), 'icon' => 'bi-geo-alt', 'title' => 'Sedes', 'desc' => 'Espacios físicos de la escuela'] : null,
                $user->tieneAccesoModulo('admin.asistencias') ? ['href' => route('asistencias.index'), 'icon' => 'bi-check2-square', 'title' => 'Asistencias', 'desc' => 'Planilla mensual por bloque'] : null,
                $user->tieneAccesoModulo('calendario') ? ['href' => route('calendario.index'), 'icon' => 'bi-calendar3', 'title' => 'Calendario', 'desc' => 'Agenda de clases y fechas'] : null,
            ])),
        ],
        [
            'code' => '02',
            'label' => 'Eventos y shows',
            'items' => array_values(array_filter([
                $user->tieneAccesoModulo('admin.eventos') ? ['href' => route('eventos.index'), 'icon' => 'bi-calendar-event', 'title' => 'Eventos', 'desc' => 'Ensayos, muestras y fechas'] : null,
                $user->tieneAccesoModulo('admin.shows') ? ['href' => route('shows.index'), 'icon' => 'bi-mic', 'title' => 'Shows', 'desc' => 'Presentaciones y logística'] : null,
            ])),
        ],
        [
            'code' => '03',
            'label' => 'Económico',
            'items' => array_values(array_filter([
                $user->tieneAccesoModulo('admin.cuotas') ? ['href' => route('cuotas.index'), 'icon' => 'bi-cash-stack', 'title' => 'Cuotas', 'desc' => 'Emisión y montos del mes'] : null,
                $user->tieneAccesoModulo('admin.pagos') ? ['href' => route('pagos.index'), 'icon' => 'bi-receipt', 'title' => 'Pagos', 'desc' => 'Cobros y detalle por alumno'] : null,
                $user->tieneAccesoModulo('admin.facturacion_mensual') ? ['href' => route('facturacion-mensual.index'), 'icon' => 'bi-file-earmark-text', 'title' => 'Facturación', 'desc' => 'Resumen mensual de facturación'] : null,
                $user->tieneAccesoModulo('comprobantes') ? ['href' => route('comprobantes-cuota-alumnos.index'), 'icon' => 'bi-upload', 'title' => 'Comprobantes', 'desc' => 'Revisión de envíos de alumnos', 'badge' => ($comprobantesPendientesCount ?? 0) > 0 ? ($comprobantesPendientesCount.' pend.') : null, 'tone' => 'danger'] : null,
                $user->tieneAccesoModulo('admin.gastos') ? ['href' => route('gastos.index'), 'icon' => 'bi-wallet2', 'title' => 'Gastos', 'desc' => 'Egresos operativos'] : null,
                $user->tieneAccesoModulo('admin.reportes') ? ['href' => route('reportes.index'), 'icon' => 'bi-graph-up', 'title' => 'Reportes', 'desc' => 'Indicadores y exportaciones'] : null,
            ])),
        ],
        [
            'code' => '04',
            'label' => 'Inventario y compras',
            'items' => array_values(array_filter([
                $user->tieneAccesoModulo('admin.inventarios') ? ['href' => route('inventarios.index'), 'icon' => 'bi-box-seam', 'title' => 'Inventarios', 'desc' => 'Instrumentos y stock'] : null,
                $user->tieneAccesoModulo('admin.plan_compras') ? ['href' => route('plan-compras.index'), 'icon' => 'bi-clipboard-check', 'title' => 'Plan de compras', 'desc' => 'Necesidades y prioridades'] : null,
                $user->tieneAccesoModulo('admin.ordenes_compra') ? ['href' => route('ordenes-compra.index'), 'icon' => 'bi-cart', 'title' => 'Órdenes de compra', 'desc' => 'Pedidos a proveedores'] : null,
            ])),
        ],
        [
            'code' => '05',
            'label' => 'Contenido',
            'items' => array_values(array_filter([
                $user->tieneAccesoModulo('programa') ? ['href' => route('programa.index'), 'icon' => 'bi-journal-text', 'title' => 'Programa', 'desc' => 'Currículum por año'] : null,
                $user->tieneAccesoModulo('programa') ? ['href' => route('programa.partituras.index'), 'icon' => 'bi-file-earmark-music', 'title' => 'Partituras', 'desc' => 'PDFs del cuadernillo por toque'] : null,
                $user->isAdmin() && $user->tieneAccesoModulo('admin.disenos') ? ['href' => route('disenos.index'), 'icon' => 'bi-palette', 'title' => 'Diseño', 'desc' => 'Piezas gráficas y editor'] : null,
                $user->isAdmin() ? ['href' => route('biblioteca.admin.index'), 'icon' => 'bi-images', 'title' => 'Biblioteca', 'desc' => 'Moderar material público compartido'] : null,
            ])),
        ],
        [
            'code' => '06',
            'label' => 'Configuración',
            'items' => array_values(array_filter([
                $user->isAdmin() ? ['href' => route('accesos.index'), 'icon' => 'bi-shield-lock', 'title' => 'Accesos', 'desc' => 'Permisos por usuario'] : null,
                $user->tieneAccesoModulo('ayuda') ? ['href' => route('ayuda'), 'icon' => 'bi-question-circle', 'title' => 'Ayuda', 'desc' => 'Guías rápidas del sistema'] : null,
            ])),
        ],
    ]));
    $hubModules = array_values(array_filter($hubModules, fn ($g) => count($g['items']) > 0));
@endphp

<div class="hub">
    <div class="hub-hero">
        <div class="hub-hero-main">
            <p class="hub-eyebrow">Tablero de operación · ITO</p>
            <h1 class="hub-greeting">
                {{ $saludo }}, <em>{{ $primerNombre }}</em>.
            </h1>
            <p class="hub-lead">
                {{ $sedesActivasEnBloques ?? 0 }} sedes activas ·
                {{ number_format($asistenciasMes ?? 0, 0, ',', '.') }} asistencias este mes ·
                vista general de la escuela.
            </p>
        </div>
        <dl class="hub-meta">
            <div>
                <dt>Fecha</dt>
                <dd>{{ now()->locale('es')->translatedFormat('d/m/Y') }}</dd>
            </div>
            <div>
                <dt>Rol</dt>
                <dd>Admin</dd>
            </div>
            <div>
                <dt>Estado</dt>
                <dd><span class="hub-status-dot" aria-hidden="true"></span> Operativo</dd>
            </div>
        </dl>
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

    @foreach($hubModules as $group)
        <section class="hub-section" aria-labelledby="hub-sec-{{ $group['code'] }}">
            <header class="hub-section-head">
                <h2 id="hub-sec-{{ $group['code'] }}" class="hub-section-title">
                    <span class="hub-section-code">{{ $group['code'] }}</span>
                    {{ $group['label'] }}
                </h2>
                <span class="hub-section-count">{{ count($group['items']) }} módulos</span>
            </header>
            <div class="hub-modules">
                @foreach($group['items'] as $item)
                    <a class="hub-module" href="{{ $item['href'] }}">
                        <span class="hub-module-icon" aria-hidden="true"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span class="hub-module-body">
                            <span class="hub-module-title">{{ $item['title'] }}</span>
                            <span class="hub-module-desc">{{ $item['desc'] }}</span>
                            @if(!empty($item['badge']))
                                <span class="hub-kpi-badge {{ ($item['tone'] ?? '') === 'danger' ? 'is-alert' : '' }}">{{ $item['badge'] }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="hub-section" aria-labelledby="hub-sec-ops">
        <header class="hub-section-head">
            <h2 id="hub-sec-ops" class="hub-section-title">
                <span class="hub-section-code">07</span>
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

        <div class="hub-panel mb-0">
            <div class="hub-panel-head">
                <div>
                    <div class="hub-panel-title">Bloques — cupo</div>
                    <div class="hub-panel-sub">Ocupación por bloque activo</div>
                </div>
                <a href="{{ route('bloques.index') }}" class="hub-panel-link">Ver bloques →</a>
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
