@php
    $hubSearchLinks = [];
    if (auth()->check()) {
        $u = auth()->user();
        $candidates = [
            ['Dashboard', 'dashboard', 'bi-house'],
            ['Pendientes', 'operativo.pendientes', 'bi-check2-square'],
            ['Alumnos', 'alumnos.index', 'bi-people', 'admin.alumnos'],
            ['Profesores', 'profesores.index', 'bi-person-badge', 'admin.profesores'],
            ['Bloques', 'bloques.index', 'bi-collection', 'admin.bloques'],
            ['Sedes', 'sedes.index', 'bi-geo-alt', 'admin.sedes'],
            ['Asistencias', 'asistencias.index', 'bi-check2-square', 'admin.asistencias'],
            ['Calendario', 'calendario.index', 'bi-calendar3', 'calendario'],
            ['Eventos', 'eventos.index', 'bi-calendar-event', 'admin.eventos'],
            ['Shows', 'shows.index', 'bi-mic', 'admin.shows'],
            ['Villa Gesell', 'villa-gesell.index', 'bi-sun', 'admin.villa_gesell'],
            ['Seña Villa Gesell', 'villa-gesell.inscriptos.index', 'bi-cash-coin', 'admin.villa_gesell'],
            ['Cuotas', 'cuotas.index', 'bi-cash-stack', 'admin.cuotas'],
            ['Pagos', 'pagos.index', 'bi-receipt', 'admin.pagos'],
            ['Facturación mensual', 'facturacion-mensual.index', 'bi-file-earmark-text', 'admin.facturacion_mensual'],
            ['Comprobantes', 'comprobantes-cuota-alumnos.index', 'bi-upload', 'comprobantes'],
            ['Gastos', 'gastos.index', 'bi-wallet2', 'admin.gastos'],
            ['Reportes', 'reportes.index', 'bi-graph-up', 'admin.reportes'],
            ['Inventarios', 'inventarios.index', 'bi-box-seam', 'admin.inventarios'],
            ['Plan de compras', 'plan-compras.index', 'bi-clipboard-check', 'admin.plan_compras'],
            ['Órdenes de compra', 'ordenes-compra.index', 'bi-cart', 'admin.ordenes_compra'],
            ['Programa', 'programa.index', 'bi-journal-text', 'programa'],
            ['Partituras', 'programa.partituras.index', 'bi-file-earmark-music', 'programa'],
            ['Biblioteca', 'biblioteca.index', 'bi-images', null],
            ['Moderar biblioteca', 'biblioteca.admin.index', 'bi-shield-check', null],
            ['Diseño', 'disenos.index', 'bi-palette', 'admin.disenos'],
            ['Accesos', 'accesos.index', 'bi-shield-lock', null],
            ['Apariencia', 'apariencia.edit', 'bi-palette2', null],
            ['Ayuda', 'ayuda', 'bi-question-circle', 'ayuda'],
            ['Mis bloques', 'profesor.bloques', 'bi-collection', 'profesor.mis_bloques'],
            ['Mis alumnos', 'profesor.alumnos', 'bi-people', 'profesor.mis_alumnos'],
            ['Tomar asistencia', 'profesor.asistencias.create', 'bi-check2-square', 'profesor.asistencia'],
        ];
        foreach ($candidates as $c) {
            $label = $c[0];
            $route = $c[1];
            $icon = $c[2];
            $mod = $c[3] ?? null;
            if (in_array($route, ['accesos.index', 'biblioteca.admin.index'], true) && ! $u->isAdmin()) {
                continue;
            }
            if ($mod && ! $u->tieneAccesoModulo($mod)) {
                continue;
            }
            try {
                $hubSearchLinks[] = [
                    'label' => $label,
                    'href' => route($route),
                    'icon' => $icon,
                ];
            } catch (\Throwable $e) {
                // ruta no registrada
            }
        }
    }
@endphp
<header class="topbar topbar--maxton topbar--hub">
    <div class="topbar-left">
        <button type="button" class="btn nav-open-btn d-lg-none" data-open-nav aria-label="Abrir menú lateral">
            <i class="bi bi-list fs-4" aria-hidden="true"></i>
        </button>
        <div class="topbar-titles">
            <div class="top-kicker">ITO · {{ config('app.name', 'La Chilinga') }}</div>
            <div class="top-title">@yield('page-title', 'Panel')</div>
        </div>
    </div>

    <div class="topbar-search" data-hub-search>
        <label class="visually-hidden" for="hubModuleSearch">Buscar módulo</label>
        <i class="bi bi-search topbar-search-icon" aria-hidden="true"></i>
        <input
            type="search"
            id="hubModuleSearch"
            class="topbar-search-input"
            placeholder="Buscar módulo, alumno, bloque…"
            autocomplete="off"
            data-hub-search-input
        >
        <kbd class="topbar-search-kbd" aria-hidden="true">Ctrl K</kbd>
        <div class="topbar-search-results" data-hub-search-results hidden role="listbox" aria-label="Resultados"></div>
        <script type="application/json" data-hub-search-data>@json($hubSearchLinks)</script>
    </div>

    <div class="topbar-right">
        <span class="topbar-date muted small d-none d-md-inline">{{ now()->locale('es')->translatedFormat('d M Y') }}</span>
    </div>
</header>
