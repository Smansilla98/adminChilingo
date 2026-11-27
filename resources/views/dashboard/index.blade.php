@extends('layouts.app')

@section('title', 'Inicio · La Chilinga')
@section('page-title', 'Inicio')

@section('content')
@php
    use App\Support\MarcaChilinga;

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
    $clasesSemanaTotal = $clasesTomadasSemana + $clasesPendientesSemana;

    // Pizarra: clases de hasta hoy sin lista completa (una fila por clase, con el color de su sede).
    $clasesSinLista = ($bloquesSemanales ?? collect())
        ->filter(fn ($r) => in_array($r['estado'] ?? '', ['Pendiente', 'Incompleta'], true)
            && $r['fecha_clase']->lte(now()->endOfDay()))
        ->values();

    $pizarra = $clasesSinLista->take(6)->map(function ($r) {
        $sede = $r['sede']?->nombre;
        return [
            'href' => route('asistencias.index'),
            'titulo' => $r['estado'] === 'Incompleta' ? 'Completar lista' : 'Pasar lista',
            'detalle' => ucfirst($r['fecha_clase']->locale('es')->translatedFormat('l')).' '.substr((string) $r['horario']->hora_inicio, 0, 5)
                .' · '.($r['presentes'] ?? 0).'/'.($r['total_alumnos'] ?? 0).' presentes',
            'sede' => $sede ?: 'Sin sede',
            'color' => MarcaChilinga::colorSede($sede),
            'accion' => 'Pasar lista',
        ];
    });

    // Otros pendientes (cobranza, agenda, sistema).
    $otros = collect($atencionHoy ?? [])->map(fn ($item) => [
        'href' => $item['href'],
        'titulo' => $item['title'],
        'detalle' => $item['hint'],
        'sede' => null,
        'color' => null,
        'accion' => 'Ver',
    ]);
    if ($clasesSinLista->count() > 6) {
        $otros = $otros->prepend([
            'href' => route('asistencias.index'),
            'titulo' => '+'.($clasesSinLista->count() - 6).' clases más sin lista',
            'detalle' => 'Asistencia',
            'sede' => null,
            'color' => null,
            'accion' => 'Ver todas',
        ]);
    }
    $fallasConfig = $user->isAdmin() ? \App\Support\EstadoSistema::errores(request()) : 0;
    if ($fallasConfig > 0) {
        $otros = $otros->prepend([
            'href' => route('estado-sistema'),
            'titulo' => $fallasConfig.' '.($fallasConfig === 1 ? 'falla' : 'fallas').' de configuración del sistema',
            'detalle' => 'Estado del sistema',
            'sede' => null,
            'color' => null,
            'accion' => 'Revisar',
        ]);
    }
    $pizarra = $pizarra->concat($otros)->values();

    $modulos = \App\Support\ModulosInicio::para($user, request());
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
        <a class="hub-kpi" href="{{ route('asistencias.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Clases de la semana</span>
                <span class="hub-kpi-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $clasesTomadasSemana }}<small class="hub-kpi-de"> de {{ $clasesSemanaTotal }}</small></div>
            @if($clasesPendientesSemana > 0)
                <span class="hub-kpi-badge is-alert">{{ $clasesPendientesSemana }} sin lista</span>
            @else
                <span class="hub-kpi-badge is-ok">Listas al día</span>
            @endif
        </a>
        <a class="hub-kpi" href="{{ route('pagos.index') }}" role="listitem">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Cobrado del mes</span>
                <span class="hub-kpi-icon"><i class="bi bi-currency-dollar" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value hub-kpi-value--sm">${{ number_format($cobradoMes ?? 0, 0, ',', '.') }}</div>
            <span class="hub-kpi-badge">{{ $pctAbonadas ?? 0 }}% de las cuotas</span>
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
    </div>

    <section class="hub-section" aria-labelledby="hub-pizarra">
        <header class="hub-section-head">
            <h2 id="hub-pizarra" class="hub-section-title">Pizarra del día</h2>
            @if($pizarra->isNotEmpty())
                <span class="hub-section-count">{{ $pizarra->count() }} {{ $pizarra->count() === 1 ? 'pendiente' : 'pendientes' }}</span>
            @endif
        </header>

        @forelse($pizarra as $item)
            <a class="hub-task" href="{{ $item['href'] }}">
                @if($item['color'])
                    <span class="hub-tag" style="--sede: {{ $item['color'] }}">{{ $item['sede'] }}</span>
                @else
                    <span class="hub-tag hub-tag--general">{{ $item['sede'] ?? 'General' }}</span>
                @endif
                <span class="hub-task-texto">
                    <strong>{{ $item['titulo'] }}</strong>
                    <span>{{ $item['detalle'] }}</span>
                </span>
                <span class="hub-task-cta">{{ $item['accion'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
        @empty
            <p class="hub-vacio"><i class="bi bi-check2-circle" aria-hidden="true"></i> Todo al día. No hay nada pendiente para hoy.</p>
        @endforelse
    </section>

    @if($modulos !== [])
    <section class="hub-section" aria-labelledby="hub-modulos">
        <header class="hub-section-head">
            <h2 id="hub-modulos" class="hub-section-title">Módulos</h2>
        </header>
        <div class="modulos-grid hub-modulos">
            @foreach($modulos as $mod)
                <a class="modulo-card" href="{{ route('modulos.show', $mod['clave']) }}">
                    <span class="modulo-card-icon" aria-hidden="true"><i class="bi {{ $mod['icono'] }}"></i></span>
                    <span class="modulo-card-titulo">{{ $mod['titulo'] }}</span>
                    <span class="modulo-card-resumen">{{ $mod['resumen'] }}</span>
                    <span class="modulo-card-entrar">Ver módulo <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    @if(collect($modulos)->contains('clave', 'pagos') || collect($modulos)->contains('clave', 'escuela'))
    <section class="hub-section" aria-labelledby="hub-graficos">
        <header class="hub-section-head">
            <h2 id="hub-graficos" class="hub-section-title">Números</h2>
        </header>
        <div class="hub-graficos">
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
                    <p class="modulo-card-resumen">Plantel actual</p>
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
    const accent = v('--accent', '#465fff');
    const info = v('--info', '#0b7593');
    if (!window.Chart) return;
    Chart.defaults.color = muted;
    Chart.defaults.font.family = v('--font-body', "'Lato', system-ui, sans-serif");
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
