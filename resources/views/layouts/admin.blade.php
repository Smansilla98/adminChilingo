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
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}?v=7">

    @stack('vite')
    @stack('styles')
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
                <button type="button" class="side-user-btn" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-haspopup="true" id="sideUserMenuBtnAdmin">
                    <span class="side-avatar" aria-hidden="true">{{ $sideUserInitials }}</span>
                    <span class="side-user-meta">
                        <span class="side-user-name text-truncate">{{ $sideUserName }}</span>
                        <span class="side-user-role">{{ $sideUserRole }}</span>
                    </span>
                    <i class="bi bi-chevron-expand side-user-chevron" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark side-user-menu shadow-lg" aria-labelledby="sideUserMenuBtnAdmin">
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
        <header class="topbar topbar--maxton">
            <div class="topbar-left">
                <button type="button" class="btn nav-open-btn d-lg-none" data-open-nav aria-label="Abrir menú lateral">
                    <i class="bi bi-list fs-4" aria-hidden="true"></i>
                </button>
                <div class="topbar-titles">
                    <div class="top-kicker">BIENVENIDO</div>
                    <div class="top-title">@yield('page-title', 'Panel Administrador')</div>
                    <div class="top-sub">
                        <span class="muted">{{ config('app.name', 'ITO') }}</span>
                        <span class="dot">•</span>
                        <span class="muted">{{ now()->locale('es')->translatedFormat('F Y') }}</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="content content--maxton" id="contenido-principal" tabindex="-1">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
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
    document.querySelectorAll('[data-open-nav]').forEach(function (el) { el.addEventListener('click', openNav); });
    backdrop.addEventListener('click', closeNav);
    sidebar?.querySelectorAll('a.side-link').forEach(function (a) { a.addEventListener('click', closeNav); });
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
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeNav(); });
})();
</script>
<script src="{{ asset('js/ito-a11y.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
