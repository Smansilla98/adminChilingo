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

    $u = auth()->user();
    $topNav = \App\Support\Navegacion::para($u, request());
    $topMigas = $topNav['migas'];
    $topTitulo = trim($__env->yieldContent('page-title', ''));
    if ($topTitulo === '') {
        $topTitulo = $topMigas ? end($topMigas)['label'] : 'Panel';
    }
    if ($topMigas && end($topMigas)['label'] === $topTitulo) {
        array_pop($topMigas);
    }

    $topNombre = $u->name ?: $u->username ?: 'Usuario';
    $topIniciales = collect(preg_split('/\s+/', trim($topNombre)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: 'U';
    $topRol = $u->etiquetaRol();
    $topContextos = app(\App\Domain\Acceso\PresentadorAcceso::class)->contextos($u->acceso());
    $topContextoActual = collect($topContextos)->firstWhere('clave', session('contexto'));
    if ($topContextoActual) {
        $topRol = $topContextoActual['etiqueta'];
    }
    $topTema = \App\Support\AparienciaTema::temaDe($u);

    $avisosNoLeidos = 0;
    $avisos = collect();
    try {
        $avisosNoLeidos = $u->unreadNotifications()->count();
        $avisos = $u->notifications()->latest()->limit(6)->get();
    } catch (\Throwable $e) {
        // tabla de notificaciones no migrada: la campana queda vacía
    }
@endphp
<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="icon-btn nav-open-btn d-lg-none" data-open-nav aria-controls="sidebarNav" aria-expanded="false" aria-label="Abrir menú">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="topbar-titles">
            @if(count($topMigas))
                <ol class="topbar-crumbs" aria-label="Estás en">
                    @foreach($topMigas as $miga)
                        <li>
                            @if($miga['href'])
                                <a href="{{ $miga['href'] }}">{{ $miga['label'] }}</a>
                            @else
                                <span>{{ $miga['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
            <p class="top-title">{{ $topTitulo }}</p>
        </div>
    </div>

    <div class="topbar-search" data-hub-search @auth data-search-url="{{ route('hub.search') }}" @endauth>
        <label class="visually-hidden" for="hubModuleSearch">Buscar en el sistema</label>
        <i class="bi bi-search topbar-search-icon" aria-hidden="true"></i>
        <input type="search" id="hubModuleSearch" class="topbar-search-input" placeholder="Buscar alumno, bloque o sección…" autocomplete="off" data-hub-search-input aria-haspopup="listbox">
        <kbd class="topbar-search-kbd d-none d-lg-inline" aria-hidden="true">Ctrl K</kbd>
        <div class="topbar-search-results" id="hubSearchResults" data-hub-search-results hidden role="listbox" aria-label="Resultados de búsqueda"></div>
        <script type="application/json" data-hub-search-data>@json($hubSearchLinks)</script>
    </div>

    <div class="topbar-right">
        <button type="button" class="icon-btn topbar-search-toggle" data-search-toggle aria-label="Buscar">
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>

        <div class="dropdown">
            <button type="button" class="icon-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                    aria-label="Avisos{{ $avisosNoLeidos ? ': '.$avisosNoLeidos.' sin leer' : '' }}" title="Avisos">
                <i class="bi bi-bell" aria-hidden="true"></i>
                @if($avisosNoLeidos)
                    <span class="icon-btn-dot" aria-hidden="true">{{ $avisosNoLeidos > 9 ? '9+' : $avisosNoLeidos }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-menu">
                <div class="notif-head">
                    <strong>Avisos</strong>
                    @if($avisosNoLeidos)
                        <form method="POST" action="{{ route('notificaciones.leer-todas') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0">Marcar todo como leído</button>
                        </form>
                    @endif
                </div>
                @if($avisos->isEmpty())
                    <div class="notif-empty">
                        <i class="bi bi-bell-slash d-block fs-4 mb-2" aria-hidden="true"></i>
                        No tenés avisos por ahora.
                    </div>
                @else
                    <ul class="notif-list">
                        @foreach($avisos as $aviso)
                            @php
                                $tipoAviso = $aviso->data['tipo'] ?? '';
                                $iconoAviso = match (true) {
                                    str_contains($tipoAviso, 'pago') => 'bi-receipt',
                                    str_contains($tipoAviso, 'cuota') => 'bi-cash-stack',
                                    str_contains($tipoAviso, 'evento') => 'bi-calendar-event',
                                    str_contains($tipoAviso, 'asistencia') => 'bi-check2-square',
                                    default => 'bi-bell',
                                };
                            @endphp
                            <li class="notif-item {{ $aviso->read_at ? '' : 'is-unread' }}">
                                <span class="notif-item-icon" aria-hidden="true"><i class="bi {{ $iconoAviso }}"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <form method="POST" action="{{ route('notificaciones.leer', $aviso->id) }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-link p-0 text-start notif-item-title text-decoration-none">
                                            {{ $aviso->data['titulo'] ?? 'Aviso' }}
                                            @unless($aviso->read_at)<span class="visually-hidden">(sin leer)</span>@endunless
                                        </button>
                                    </form>
                                    @if(!empty($aviso->data['mensaje']))
                                        <div class="notif-item-text">{{ \Illuminate\Support\Str::limit($aviso->data['mensaje'], 110) }}</div>
                                    @endif
                                    <div class="notif-item-time">{{ $aviso->created_at?->locale('es')->diffForHumans() }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="dropdown">
            <button type="button" class="topbar-user-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de {{ $topNombre }}">
                <span class="topbar-avatar" aria-hidden="true">{{ $topIniciales }}</span>
                <span class="topbar-user-meta">
                    <span class="topbar-user-name">{{ $topNombre }}</span>
                    <span class="topbar-user-role">{{ $topRol }}</span>
                </span>
                <i class="bi bi-chevron-down small text-muted d-none d-lg-inline" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end topbar-menu">
                <li class="topbar-menu-head">
                    <strong>{{ $topNombre }}</strong>
                    <span>{{ $topRol }}</span>
                </li>
                <li><hr class="dropdown-divider"></li>
                @if(count($topContextos) > 1)
                    <li><h6 class="dropdown-header">Estoy trabajando como</h6></li>
                    @foreach($topContextos as $ctx)
                        <li>
                            <form method="POST" action="{{ route('contexto.cambiar') }}" class="m-0">
                                @csrf
                                <input type="hidden" name="contexto" value="{{ $ctx['clave'] }}">
                                <button type="submit" class="dropdown-item" @if(session('contexto') === $ctx['clave']) aria-current="true" @endif>
                                    <i class="bi {{ session('contexto') === $ctx['clave'] ? 'bi-check-circle-fill' : 'bi-circle' }}" aria-hidden="true"></i>
                                    {{ $ctx['etiqueta'] }}
                                </button>
                            </form>
                        </li>
                    @endforeach
                    @if(session('contexto'))
                        <li>
                            <form method="POST" action="{{ route('contexto.cambiar') }}" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="bi bi-grid" aria-hidden="true"></i> Ver todo</button>
                            </form>
                        </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                @endif
                @if($u->persona_id)
                    <li><a class="dropdown-item" href="{{ route('personas.show', $u->persona_id) }}"><i class="bi bi-person-vcard" aria-hidden="true"></i> Mi ficha</a></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('apariencia.edit') }}"><i class="bi bi-palette2" aria-hidden="true"></i> Apariencia</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Tema</h6></li>
                @foreach(\App\Support\AparienciaTema::TEMAS as $claveTema => $temaInfo)
                    <li>
                        <form method="POST" action="{{ route('apariencia.tema') }}" class="m-0" data-no-loading>
                            @csrf
                            <input type="hidden" name="tema" value="{{ $claveTema }}">
                            <button type="submit" class="dropdown-item" @if($topTema === $claveTema) aria-current="true" @endif>
                                <i class="bi {{ $temaInfo['icon'] }}" aria-hidden="true"></i>
                                {{ $temaInfo['label'] }}
                                @if($topTema === $claveTema)<i class="bi bi-check2 ms-auto" aria-hidden="true"></i>@endif
                            </button>
                        </form>
                    </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item ito-pref-btn" data-ito-pref="ito-a11y-lg" data-on-msg="Texto grande activado" data-off-msg="Texto grande desactivado" aria-pressed="false">
                        <i class="bi bi-fonts" aria-hidden="true"></i> Texto grande <i class="bi bi-check2" aria-hidden="true"></i>
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item ito-pref-btn" data-ito-pref="ito-a11y-hc" data-on-msg="Alto contraste activado" data-off-msg="Alto contraste desactivado" aria-pressed="false">
                        <i class="bi bi-circle-half" aria-hidden="true"></i> Alto contraste <i class="bi bi-check2" aria-hidden="true"></i>
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
