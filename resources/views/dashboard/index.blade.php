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
    $fallasConfig = $user->isAdmin() ? \App\Support\EstadoSistema::errores(request()) : 0;
    if ($fallasConfig > 0) {
        $atencionExtra = $atencionExtra->prepend([
            'href' => route('estado-sistema'),
            'title' => $fallasConfig.' '.($fallasConfig === 1 ? 'falla' : 'fallas').' de configuración del sistema',
            'hint' => 'Estado del sistema',
        ]);
    }
    if ($clasesPendientesSemana > 0) {
        $atencionExtra = $atencionExtra->prepend([
            'href' => route('asistencias.index'),
            'title' => $clasesPendientesSemana.' clase'.($clasesPendientesSemana === 1 ? '' : 's').' de la semana sin lista completa',
            'hint' => 'Asistencia',
        ]);
    }

    $modulos = \App\Support\ModulosInicio::para($user, request());
    $estadoModulo = [
        'asistencias' => $clasesPendientesSemana > 0
            ? ['texto' => $clasesPendientesSemana.' '.($clasesPendientesSemana === 1 ? 'clase sin lista' : 'clases sin lista'), 'tono' => 'alert']
            : ['texto' => $clasesTomadasSemana.' listas tomadas esta semana', 'tono' => 'ok'],
        'personas' => ['texto' => ($alumnosActivos ?? 0).' alumnos activos', 'tono' => 'ok'],
        'pagos' => ($cuotasPendientes ?? 0) > 0
            ? ['texto' => ($cuotasPendientes ?? 0).' cuotas pendientes', 'tono' => 'alert']
            : ['texto' => 'Cuotas del mes al día', 'tono' => 'ok'],
        'escuela' => ['texto' => ($sedesActivasEnBloques ?? 0).' sedes con bloques', 'tono' => 'neutral'],
        'actividades' => ['texto' => ($proximosEventosCount ?? 0).' eventos en la agenda', 'tono' => 'neutral'],
        'recursos' => ['texto' => 'Programa y archivo', 'tono' => 'neutral'],
        'inventario' => ['texto' => 'Stock y compras', 'tono' => 'neutral'],
        'config' => $fallasConfig > 0
            ? ['texto' => $fallasConfig.' '.($fallasConfig === 1 ? 'falla' : 'fallas').' de configuración', 'tono' => 'alert']
            : ['texto' => 'Sistema en orden', 'tono' => 'ok'],
    ];
@endphp

<div class="hub hub--command">
    <div class="hub-hero">
        <div class="hub-hero-main">
            <h1 class="hub-greeting">{{ $saludo }}, {{ $primerNombre }}</h1>
            <p class="hub-lead">
                {{ ucfirst(now()->locale('es')->translatedFormat('l j \d\e F')) }}
                · {{ number_format($asistenciasMes ?? 0, 0, ',', '.') }} asistencias este mes
                @if(!empty($dashboardAmbito)) · {{ $dashboardAmbito }}@endif
            </p>
        </div>
        <div class="ito-page-actions">
            @if($user->tieneAccesoModulo('admin.alumnos'))
                <a href="{{ route('alumnos.create') }}" class="btn btn-lg btn-outline-secondary"><i class="bi bi-person-plus" aria-hidden="true"></i> Nuevo alumno</a>
            @endif
            @if($user->tieneAccesoModulo('admin.pagos'))
                <a href="{{ route('pagos.create') }}" class="btn btn-lg btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Registrar pago</a>
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
                <i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    @if($modulos !== [])
    <section class="hub-section" aria-labelledby="hub-modulos">
        <header class="hub-section-head">
            <h2 id="hub-modulos" class="hub-section-title">Módulos</h2>
            <span class="hub-section-count">Entrá a uno para ver sus pantallas</span>
        </header>
        <div class="modulos-grid">
            @foreach($modulos as $mod)
                @php
                    $estado = $estadoModulo[$mod['clave']] ?? ['texto' => count($mod['links']).' pantallas', 'tono' => 'neutral'];
                @endphp
                <article class="modulo-card">
                    <a class="modulo-card-abrir" href="{{ route('modulos.show', $mod['clave']) }}">
                        <span class="modulo-card-top">
                            <span class="modulo-card-icon" aria-hidden="true"><i class="bi {{ $mod['icono'] }}"></i></span>
                            <span class="modulo-estado is-{{ $estado['tono'] }}">{{ $estado['texto'] }}</span>
                        </span>
                        <span class="modulo-card-titulo">{{ $mod['titulo'] }}</span>
                        <span class="modulo-card-resumen">{{ $mod['resumen'] }}</span>
                        <span class="modulo-card-entrar">Ver módulo <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                    </a>
                    @if($mod['accion'])
                        <a class="btn btn-primary modulo-card-accion" href="{{ $mod['accion']['href'] }}">{{ $mod['accion']['label'] }}</a>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @endif

    @if(collect($modulos)->contains('clave', 'pagos') || collect($modulos)->contains('clave', 'escuela'))
    <section class="hub-section" aria-labelledby="hub-graficos">
        <h2 id="hub-graficos" class="hub-section-title"><i class="bi bi-bar-chart" aria-hidden="true"></i> Números</h2>
        <div class="modulos-grid">
            @if(collect($modulos)->contains('clave', 'pagos'))
                <article class="modulo-card">
                    <h3 class="modulo-card-titulo">Ingresos y gastos</h3>
                    <p class="modulo-card-resumen">Últimos 6 meses</p>
                    <div class="hub-chart"><canvas id="dashChartFinanzas" role="img" aria-label="Gráfico de barras: ingresos y gastos de los últimos 6 meses"></canvas></div>
                </article>
            @endif
            @if(collect($modulos)->contains('clave', 'escuela'))
                <article class="modulo-card">
                    <h3 class="modulo-card-titulo">Alumnos activos por sede</h3>
                    <div class="hub-chart"><canvas id="dashChartSedes" role="img" aria-label="Gráfico de barras: alumnos activos por sede"></canvas></div>
                </article>
            @endif
        </div>
    </section>
    @endif

    @if(Route::has('ayuda.direccion') && ($user->isAdmin() || $user->puedeGestionarOperativo()))
        <a class="hub-guia" href="{{ route('ayuda.direccion') }}">
            <span class="hub-guia-icon" aria-hidden="true"><i class="bi bi-signpost-split"></i></span>
            <span class="hub-guia-texto">
                <strong>Guía paso a paso</strong>
                <span>Para quien está aprendiendo el sistema. La abrís cuando la necesitás, sin mezclarla con el trabajo del día.</span>
            </span>
            <span class="hub-guia-cta">Abrir guía</span>
        </a>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const css = getComputedStyle(document.documentElement);
    const v = (n, f) => css.getPropertyValue(n).trim() || f;
    const muted = v('--text-2', '#3a404a');
    const grid = v('--border', '#e2e5ea');
    const success = v('--success', '#1e7b34');
    const accent = v('--accent', '#f26422');
    const info = v('--info', '#0b7593');
    if (!window.Chart) return;
    Chart.defaults.color = muted;
    Chart.defaults.font.family = "'Manrope', system-ui, sans-serif";
    const peso = (n) => '$' + Number(n || 0).toLocaleString('es-AR');
    let finanzas = null;
    let sedes = null;

    function graficoFinanzas() {
        const fin = document.getElementById('dashChartFinanzas');
        if (!fin || finanzas) return;
        finanzas = new Chart(fin.getContext('2d'), {
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
                    legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, font: { size: 14 } } },
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + peso(c.raw) } },
                },
                scales: {
                    x: { grid: { display: false }, border: { color: grid }, ticks: { font: { size: 13 } } },
                    y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { font: { size: 13 }, callback: (val) => peso(val) } },
                },
            },
        });
    }

    function graficoSedes() {
        const nodo = document.getElementById('dashChartSedes');
        if (!nodo || sedes) return;
        const sedeData = {!! json_encode(($alumnosPorSedeChart ?? collect())->values()) !!};
        sedes = new Chart(nodo.getContext('2d'), {
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
                    x: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, font: { size: 13 } } },
                    y: { grid: { display: false }, border: { color: grid }, ticks: { font: { size: 13 } } },
                },
            },
        });
    }

    // Los gráficos están en el inicio (ya no hay paneles que abrir): se dibujan al cargar.
    graficoFinanzas();
    graficoSedes();
})();
</script>
@endpush
