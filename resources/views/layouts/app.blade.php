<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ITO - Sistema de gestión')</title>

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}?v=10">
    @include('layouts.partials.apariencia-head')

    @stack('vite')
    @stack('styles')
    @auth
    <link rel="stylesheet" href="{{ asset('css/recordatorio-chatbot.css') }}?v=3">
    @endauth
</head>
<body>
<a class="ito-skip" href="#contenido-principal">Ir al contenido</a>
<div id="itoA11yLive" class="ito-sr-only" aria-live="polite" aria-atomic="true"></div>
@auth
@php
    $sideUserName = auth()->user()->name ?: auth()->user()->username ?: 'Usuario';
    $sideUserInitials = collect(preg_split('/\s+/', trim($sideUserName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: 'U';
    $sideUserRole = auth()->user()->isAdmin() ? 'Administrador' : 'Profesor';
@endphp
<div class="shell shell--maxton" id="appShell">
    <button type="button" class="nav-backdrop" id="navBackdrop" aria-label="Cerrar menú"></button>

    <aside class="sidebar sidebar--maxton" id="sidebarNav">
        <div class="sidebar-head">
            <a class="sidebar-brand" href="{{ route('dashboard') }}" aria-label="Inicio">
                <x-brand-logo variant="sidebar" />
                <span class="sidebar-brand-text">
                    <span class="sidebar-brand-title">ITO</span>
                    <span class="sidebar-brand-sub">{{ Str::limit(config('app.name', 'Panel'), 28) }}</span>
                </span>
            </a>
        </div>

        <nav class="side-nav" aria-label="Navegación principal">
            @include('layouts.partials.sidebar-nav')
        </nav>

        <div class="sidebar-foot">
            <div class="dropdown dropup w-100">
                <button type="button" class="side-user-btn" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-haspopup="true" id="sideUserMenuBtn">
                    <span class="side-avatar" aria-hidden="true">{{ $sideUserInitials }}</span>
                    <span class="side-user-meta">
                        <span class="side-user-name text-truncate">{{ $sideUserName }}</span>
                        <span class="side-user-role">{{ $sideUserRole }}</span>
                    </span>
                    <i class="bi bi-chevron-expand side-user-chevron" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark side-user-menu shadow-lg" aria-labelledby="sideUserMenuBtn">
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2 ito-pref-btn"
                                data-ito-pref="ito-a11y-lg"
                                data-on-msg="Texto grande activado"
                                data-off-msg="Texto grande desactivado"
                                aria-pressed="false">
                            <i class="bi bi-fonts" aria-hidden="true"></i>
                            Texto grande
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2 ito-pref-btn"
                                data-ito-pref="ito-a11y-hc"
                                data-on-msg="Alto contraste activado"
                                data-off-msg="Alto contraste desactivado"
                                aria-pressed="false">
                            <i class="bi bi-circle-half" aria-hidden="true"></i>
                            Alto contraste
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                Cerrar sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </aside>

    <main class="main main--maxton">
        @include('layouts.partials.topbar-hub')

        <section class="content content--maxton" id="contenido-principal" tabindex="-1">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('import_errors') && is_array(session('import_errors')) && count(session('import_errors')) > 0)
                <div class="alert alert-warning">
                    <div class="fw-semibold">Importación: advertencias</div>
                    <ul class="mb-0">
                        @foreach(session('import_errors') as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </section>
    </main>

    @include('layouts.partials.recordatorio-chatbot')
</div>
@endauth

<div id="itoConfirmModal" class="ito-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="itoConfirmTitle" aria-describedby="itoConfirmMessage">
    <div class="ito-confirm-backdrop" tabindex="-1"></div>
    <div class="ito-confirm-dialog">
        <button type="button" class="ito-confirm-close" id="itoConfirmClose" aria-label="Cerrar">&times;</button>
        <h2 id="itoConfirmTitle">Confirmar acción</h2>
        <p id="itoConfirmMessage">¿Estás seguro?</p>
        <div class="ito-confirm-actions">
            <button type="button" class="btn btn-secondary" id="itoConfirmCancel">Cancelar</button>
            <button type="button" class="btn btn-danger" id="itoConfirmOk">Sí, continuar</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const shell = document.getElementById('appShell');
    const backdrop = document.getElementById('navBackdrop');
    const sidebar = document.getElementById('sidebarNav');
    if (!shell || !backdrop) return;
    const closeNav = function () {
        shell.classList.remove('shell--nav-open');
        document.body.classList.remove('shell-nav-open');
    };
    const openNav = function () {
        shell.classList.add('shell--nav-open');
        document.body.classList.add('shell-nav-open');
    };
    document.querySelectorAll('[data-open-nav]').forEach(function (el) {
        el.addEventListener('click', openNav);
    });
    backdrop.addEventListener('click', closeNav);
    sidebar?.querySelectorAll('a.side-link').forEach(function (a) {
        a.addEventListener('click', closeNav);
    });
    document.querySelectorAll('.nav-group-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = btn.closest('.nav-group');
            if (!group) return;
            const wasOpen = group.classList.contains('open');
            document.querySelectorAll('.nav-group.open').forEach(function (g) {
                g.classList.remove('open');
                g.querySelector('.nav-group-btn')?.setAttribute('aria-expanded', 'false');
            });
            if (!wasOpen) {
                group.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNav();
    });
})();
</script>
<script>
(function () {
    const wrap = document.querySelector('[data-hub-search]');
    if (!wrap) return;
    const input = wrap.querySelector('[data-hub-search-input]');
    const box = wrap.querySelector('[data-hub-search-results]');
    const dataEl = wrap.querySelector('[data-hub-search-data]');
    if (!input || !box || !dataEl) return;
    let items = [];
    try { items = JSON.parse(dataEl.textContent || '[]'); } catch (e) { items = []; }

    function render(q) {
        const query = (q || '').trim().toLowerCase();
        const hits = !query ? items.slice(0, 8) : items.filter(function (it) {
            return (it.label || '').toLowerCase().indexOf(query) !== -1;
        }).slice(0, 10);
        if (!hits.length) {
            box.innerHTML = '<div class="topbar-search-empty">Sin resultados</div>';
            box.hidden = false;
            return;
        }
        box.innerHTML = hits.map(function (it) {
            return '<a class="topbar-search-item" role="option" href="' + it.href + '">' +
                '<i class="bi ' + (it.icon || 'bi-box') + '" aria-hidden="true"></i>' +
                '<span>' + it.label + '</span></a>';
        }).join('');
        box.hidden = false;
    }

    input.addEventListener('focus', function () { render(input.value); });
    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            box.hidden = true;
            input.blur();
        } else if (e.key === 'Enter') {
            const first = box.querySelector('a.topbar-search-item');
            if (first) {
                e.preventDefault();
                window.location.href = first.getAttribute('href');
            }
        }
    });
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) box.hidden = true;
    });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            input.focus();
            render(input.value);
        }
    });
})();
</script>
@auth
<script src="{{ asset('js/recordatorio-chatbot.js') }}?v=3"></script>
@endauth
<script src="{{ asset('js/ito-a11y.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
