@php
    $hubSearchLinks = [];
    if (auth()->check()) {
        $u = auth()->user();
        $candidates = [
            ['Ir al inicio', 'dashboard', 'bi-house', null, 'Acciones'],
            ['Pendientes de hoy', 'operativo.pendientes', 'bi-lightning', null, 'Acciones'],
            ['Tomar asistencia', 'profesor.asistencias.create', 'bi-check2-square', 'profesor.asistencia', 'Acciones'],
            ['Registrar pago', 'pagos.create', 'bi-plus-circle', 'admin.pagos', 'Acciones'],
            ['Nuevo alumno', 'alumnos.create', 'bi-person-plus', 'admin.alumnos', 'Acciones'],
            ['Alumnos', 'alumnos.index', 'bi-people', 'admin.alumnos', 'Módulos'],
            ['Profesores', 'profesores.index', 'bi-person-badge', 'admin.profesores', 'Módulos'],
            ['Bloques', 'bloques.index', 'bi-collection', 'admin.bloques', 'Módulos'],
            ['Sedes', 'sedes.index', 'bi-geo-alt', 'admin.sedes', 'Módulos'],
            ['Asistencias', 'asistencias.index', 'bi-check2-square', 'admin.asistencias', 'Módulos'],
            ['Calendario', 'calendario.index', 'bi-calendar3', 'calendario', 'Módulos'],
            ['Eventos', 'eventos.index', 'bi-calendar-event', 'admin.eventos', 'Módulos'],
            ['Shows', 'shows.index', 'bi-mic', 'admin.shows', 'Módulos'],
            ['Villa Gesell', 'villa-gesell.index', 'bi-sun', 'admin.villa_gesell', 'Módulos'],
            ['Seña Villa Gesell', 'villa-gesell.inscriptos.index', 'bi-cash-coin', 'admin.villa_gesell', 'Módulos'],
            ['Cuotas', 'cuotas.index', 'bi-cash-stack', 'admin.cuotas', 'Módulos'],
            ['Pagos', 'pagos.index', 'bi-receipt', 'admin.pagos', 'Módulos'],
            ['Facturación mensual', 'facturacion-mensual.index', 'bi-file-earmark-text', 'admin.facturacion_mensual', 'Módulos'],
            ['Comprobantes', 'comprobantes-cuota-alumnos.index', 'bi-upload', 'comprobantes', 'Módulos'],
            ['Gastos', 'gastos.index', 'bi-wallet2', 'admin.gastos', 'Módulos'],
            ['Reportes', 'reportes.index', 'bi-graph-up', 'admin.reportes', 'Módulos'],
            ['Inventarios', 'inventarios.index', 'bi-box-seam', 'admin.inventarios', 'Módulos'],
            ['Plan de compras', 'plan-compras.index', 'bi-clipboard-check', 'admin.plan_compras', 'Módulos'],
            ['Órdenes de compra', 'ordenes-compra.index', 'bi-cart', 'admin.ordenes_compra', 'Módulos'],
            ['Programa', 'programa.index', 'bi-journal-text', 'programa', 'Módulos'],
            ['Partituras', 'programa.partituras.index', 'bi-file-earmark-music', 'programa', 'Módulos'],
            ['Biblioteca', 'biblioteca.index', 'bi-images', null, 'Módulos'],
            ['Moderar biblioteca', 'biblioteca.admin.index', 'bi-shield-check', null, 'Módulos'],
            ['Diseño', 'disenos.index', 'bi-palette', 'admin.disenos', 'Módulos'],
            ['Accesos', 'accesos.index', 'bi-shield-lock', null, 'Módulos'],
            ['Apariencia', 'apariencia.edit', 'bi-palette2', null, 'Módulos'],
            ['Ayuda', 'ayuda', 'bi-question-circle', 'ayuda', 'Módulos'],
            ['Mis bloques', 'profesor.bloques', 'bi-collection', 'profesor.mis_bloques', 'Módulos'],
            ['Mis alumnos', 'profesor.alumnos', 'bi-people', 'profesor.mis_alumnos', 'Módulos'],
        ];
        foreach ($candidates as $c) {
            $label = $c[0];
            $route = $c[1];
            $icon = $c[2];
            $mod = $c[3] ?? null;
            $group = $c[4] ?? 'Módulos';
            if (in_array($route, ['accesos.index', 'biblioteca.admin.index'], true) && ! $u->isAdmin()) {
                continue;
            }
            if ($route === 'operativo.pendientes' && ! ($u->puedeGestionarOperativo() || $u->isProfesor())) {
                continue;
            }
            if ($mod && ! $u->tieneAccesoModulo($mod)) {
                continue;
            }
            try {
                $params = $route === 'villa-gesell.inscriptos.index' ? ['estado' => 'sena'] : [];
                $hubSearchLinks[] = [
                    'label' => $label,
                    'href' => $params ? route($route, $params) : route($route),
                    'icon' => $icon,
                    'group' => $group,
                    'meta' => $group === 'Acciones' ? 'Acción rápida' : null,
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

    <div
        class="topbar-search"
        data-hub-search
        @auth data-search-url="{{ route('hub.search') }}" @endauth
    >
        <label class="visually-hidden" for="hubModuleSearch">Buscar en el sistema</label>
        <i class="bi bi-search topbar-search-icon" aria-hidden="true"></i>
        <input
            type="search"
            id="hubModuleSearch"
            class="topbar-search-input"
            placeholder="Buscar módulo, alumno, bloque…"
            autocomplete="off"
            data-hub-search-input
            aria-haspopup="listbox"
        >
        <kbd class="topbar-search-kbd" aria-hidden="true">Ctrl K</kbd>
        <div class="topbar-search-results" id="hubSearchResults" data-hub-search-results hidden role="listbox" aria-label="Resultados de búsqueda"></div>
        <script type="application/json" data-hub-search-data>@json($hubSearchLinks)</script>
    </div>

    <div class="topbar-right">
        <span class="topbar-date muted small d-none d-md-inline">{{ now()->locale('es')->translatedFormat('d M Y') }}</span>
    </div>
</header>
