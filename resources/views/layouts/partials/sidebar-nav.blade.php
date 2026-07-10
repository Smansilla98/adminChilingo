@php
    $navGroups = [
        'academico' => [
            'label' => 'Académico',
            'accent' => 'academico',
            'patterns' => ['alumnos.*', 'profesores.*', 'bloques.*', 'sedes.*', 'asistencias.*', 'calendario.*'],
            'links' => array_filter([
                auth()->user()->tieneAccesoModulo('admin.alumnos') ? ['route' => 'alumnos.index', 'label' => 'Alumnos', 'pattern' => 'alumnos.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.profesores') ? ['route' => 'profesores.index', 'label' => 'Profesores', 'pattern' => 'profesores.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.bloques') ? ['route' => 'bloques.index', 'label' => 'Bloques', 'pattern' => 'bloques.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.sedes') ? ['route' => 'sedes.index', 'label' => 'Sedes', 'pattern' => 'sedes.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.asistencias') ? ['route' => 'asistencias.index', 'label' => 'Asistencias', 'pattern' => 'asistencias.*'] : null,
                auth()->user()->tieneAccesoModulo('calendario') ? ['route' => 'calendario.index', 'label' => 'Calendario', 'pattern' => 'calendario.*'] : null,
            ]),
        ],
        'eventos' => [
            'label' => 'Eventos y shows',
            'accent' => 'eventos',
            'patterns' => ['eventos.*', 'shows.*'],
            'links' => array_filter([
                auth()->user()->tieneAccesoModulo('admin.eventos') ? ['route' => 'eventos.index', 'label' => 'Eventos', 'pattern' => 'eventos.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.shows') ? ['route' => 'shows.index', 'label' => 'Shows', 'pattern' => 'shows.*'] : null,
            ]),
        ],
        'economico' => [
            'label' => 'Económico',
            'accent' => 'economico',
            'patterns' => ['cuotas.*', 'pagos.*', 'facturacion-mensual.*', 'comprobantes-cuota-alumnos.*', 'gastos.*', 'reportes.*'],
            'links' => array_filter([
                auth()->user()->tieneAccesoModulo('admin.cuotas') ? ['route' => 'cuotas.index', 'label' => 'Cuotas', 'pattern' => 'cuotas.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.pagos') ? ['route' => 'pagos.index', 'label' => 'Pagos', 'pattern' => 'pagos.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.facturacion_mensual') ? ['route' => 'facturacion-mensual.index', 'label' => 'Facturación mensual', 'pattern' => 'facturacion-mensual.*'] : null,
                auth()->user()->tieneAccesoModulo('comprobantes') ? ['route' => 'comprobantes-cuota-alumnos.index', 'label' => 'Comprobantes de alumnos', 'pattern' => 'comprobantes-cuota-alumnos.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.gastos') ? ['route' => 'gastos.index', 'label' => 'Gastos', 'pattern' => 'gastos.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.reportes') ? ['route' => 'reportes.index', 'label' => 'Reportes', 'pattern' => 'reportes.*'] : null,
            ]),
        ],
        'inventario' => [
            'label' => 'Inventario y compras',
            'accent' => 'inventario',
            'patterns' => ['inventarios.*', 'plan-compras.*', 'ordenes-compra.*'],
            'links' => array_filter([
                auth()->user()->tieneAccesoModulo('admin.inventarios') ? ['route' => 'inventarios.index', 'label' => 'Inventarios', 'pattern' => 'inventarios.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.plan_compras') ? ['route' => 'plan-compras.index', 'label' => 'Plan de compras', 'pattern' => 'plan-compras.*'] : null,
                auth()->user()->tieneAccesoModulo('admin.ordenes_compra') ? ['route' => 'ordenes-compra.index', 'label' => 'Órdenes de compra', 'pattern' => 'ordenes-compra.*'] : null,
            ]),
        ],
        'contenido' => [
            'label' => 'Contenido',
            'accent' => 'contenido',
            'patterns' => ['programa.*', 'disenos.*'],
            'links' => array_filter([
                auth()->user()->tieneAccesoModulo('programa') ? ['route' => 'programa.index', 'label' => 'Programa', 'pattern' => 'programa.*'] : null,
                auth()->user()->tieneAccesoModulo('programa') ? ['route' => 'programa.index', 'label' => 'Partituras', 'pattern' => 'programa.*', 'fragment' => '#partituras'] : null,
                auth()->user()->isAdmin() && auth()->user()->tieneAccesoModulo('admin.disenos') ? ['route' => 'disenos.index', 'label' => 'Diseño', 'pattern' => 'disenos.*', 'badge' => 'nuevo'] : null,
            ]),
        ],
        'config' => [
            'label' => 'Configuración',
            'accent' => 'config',
            'patterns' => ['accesos.*', 'ayuda'],
            'links' => array_filter([
                auth()->user()->isAdmin() ? ['route' => 'accesos.index', 'label' => 'Accesos', 'pattern' => 'accesos.*'] : null,
                auth()->user()->tieneAccesoModulo('ayuda') ? ['route' => 'ayuda', 'label' => 'Ayuda', 'pattern' => 'ayuda'] : null,
            ]),
        ],
    ];

    $activeGroup = null;
    foreach ($navGroups as $key => $group) {
        foreach ($group['patterns'] as $pattern) {
            if (request()->routeIs($pattern)) {
                $activeGroup = $key;
                break 2;
            }
        }
    }

    $profesorLinks = array_filter([
        auth()->user()->tieneAccesoModulo('profesor.mis_bloques') ? ['route' => 'profesor.bloques', 'label' => 'Mis bloques', 'pattern' => 'profesor.bloques*'] : null,
        auth()->user()->tieneAccesoModulo('profesor.asistencia') ? ['route' => 'profesor.asistencias.create', 'label' => 'Asistencia', 'pattern' => 'profesor.asistencias.*'] : null,
        auth()->user()->tieneAccesoModulo('profesor.mis_alumnos') ? ['route' => 'profesor.alumnos', 'label' => 'Mis alumnos', 'pattern' => 'profesor.alumnos*'] : null,
        auth()->user()->tieneAccesoModulo('profesor.pagos_cuotas') ? ['route' => 'profesor.pagos-cuotas.index', 'label' => 'Pagos de cuotas', 'pattern' => 'profesor.pagos-cuotas.*'] : null,
        auth()->user()->tieneAccesoModulo('comprobantes') ? ['route' => 'comprobantes-cuota-alumnos.index', 'label' => 'Comprobantes', 'pattern' => 'comprobantes-cuota-alumnos.*'] : null,
        auth()->user()->tieneAccesoModulo('programa') ? ['route' => 'programa.index', 'label' => 'Programa', 'pattern' => 'programa.*'] : null,
        auth()->user()->tieneAccesoModulo('calendario') ? ['route' => 'calendario.index', 'label' => 'Calendario', 'pattern' => 'calendario.*'] : null,
        auth()->user()->tieneAccesoModulo('ayuda') ? ['route' => 'ayuda', 'label' => 'Ayuda', 'pattern' => 'ayuda'] : null,
    ]);
@endphp

<a class="side-link side-link--top {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Inicio">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12l9-8 9 8M5 10v10h14V10" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
    <span class="side-link-text">Inicio</span>
</a>

@if(auth()->user()->isAdmin())
    @foreach($navGroups as $key => $group)
        @if(count($group['links']) > 0)
            <div class="nav-group {{ $activeGroup === $key ? 'open' : '' }}" data-accent="{{ $group['accent'] }}">
                <button type="button" class="nav-group-btn" aria-expanded="{{ $activeGroup === $key ? 'true' : 'false' }}">
                    <span class="nav-group-dot" aria-hidden="true"></span>
                    <span class="nav-group-label">{{ $group['label'] }}</span>
                    <svg class="nav-group-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
                </button>
                <div class="nav-group-links">
                    @foreach($group['links'] as $link)
                        @php
                            $href = route($link['route']).($link['fragment'] ?? '');
                            $isActive = request()->routeIs($link['pattern']);
                        @endphp
                        <a class="side-link side-link--nested {{ $isActive ? 'active' : '' }}" href="{{ $href }}" title="{{ $link['label'] }}">
                            <span class="side-link-text">{{ $link['label'] }}</span>
                            @if(!empty($link['badge']))
                                <span class="side-link-badge">{{ $link['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
@else
    <div class="nav-group open" data-accent="profesor">
        <button type="button" class="nav-group-btn" aria-expanded="true">
            <span class="nav-group-dot" aria-hidden="true"></span>
            <span class="nav-group-label">Mi espacio</span>
            <svg class="nav-group-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
        </button>
        <div class="nav-group-links">
            @foreach($profesorLinks as $link)
                <a class="side-link side-link--nested {{ request()->routeIs($link['pattern']) ? 'active' : '' }}" href="{{ route($link['route']) }}" title="{{ $link['label'] }}">
                    <span class="side-link-text">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
