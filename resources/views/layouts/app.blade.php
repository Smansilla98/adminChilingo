<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ITO - Sistema de gestión')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}">

    @stack('vite')
    @stack('styles')
    @auth
    <link rel="stylesheet" href="{{ asset('css/recordatorio-chatbot.css') }}?v=2">
    @endauth
</head>
<body>
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
            <a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Inicio">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" fill="currentColor"/></svg>
                <span class="side-link-text">Inicio</span>
            </a>

            @if(auth()->user()->tieneAccesoModulo('programa'))
            <a class="side-link {{ request()->routeIs('programa.*') ? 'active' : '' }}" href="{{ route('programa.index') }}" title="Programa">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z" fill="currentColor"/></svg>
                <span class="side-link-text">Programa</span>
            </a>
            @endif

            @if(auth()->user()->tieneAccesoModulo('calendario'))
            <a class="side-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" href="{{ route('calendario.index') }}" title="Calendario">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z" fill="currentColor"/></svg>
                <span class="side-link-text">Calendario</span>
            </a>
            @endif

            @if(auth()->user()->tieneAccesoModulo('ayuda'))
            <a class="side-link {{ request()->routeIs('ayuda') ? 'active' : '' }}" href="{{ route('ayuda') }}" title="Guía de uso">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z" fill="currentColor"/></svg>
                <span class="side-link-text">Guía de uso</span>
            </a>
            @endif

            @if(auth()->user()->isAdmin())
                @if(auth()->user()->tieneAccesoModulo('admin.alumnos'))
                <a class="side-link {{ request()->routeIs('alumnos.*') ? 'active' : '' }}" href="{{ route('alumnos.index') }}" title="Alumnos">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h7v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/></svg>
                    <span class="side-link-text">Alumnos</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('admin.importar'))
                <a class="side-link {{ request()->routeIs('alumnos.import.*') ? 'active' : '' }}" href="{{ route('alumnos.import.form') }}" title="Importar alumnos">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20h14v-2H5v2zM12 2 6.5 7.5l1.42 1.42L11 5.84V16h2V5.84l3.08 3.08 1.42-1.42L12 2z" fill="currentColor"/></svg>
                    <span class="side-link-text">Importar</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('admin.cuotas'))
                <a class="side-link {{ request()->routeIs('cuotas.*') ? 'active' : '' }}" href="{{ route('cuotas.index') }}" title="Cuotas">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1C5.93 1 1 5.93 1 12s4.93 11 11 11 11-4.93 11-11S18.07 1 12 1zm1 17.93c-2.83.48-5.62-.9-6.78-3.45l1.74-.99A4.99 4.99 0 0 0 13 16.9V13h-2v-2h2V8.82c-1.16.41-2 1.51-2 2.82H9c0-2.76 2.24-5 5-5v2c-1.66 0-3 1.34-3 3v2h4v2h-2v3.93z" fill="currentColor"/></svg>
                    <span class="side-link-text">Cuotas</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('comprobantes'))
                <a class="side-link {{ request()->routeIs('comprobantes-cuota-alumnos.*') ? 'active' : '' }}" href="{{ route('comprobantes-cuota-alumnos.index') }}" title="Comprobantes">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z" fill="currentColor"/></svg>
                    <span class="side-link-text">Comprobantes</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('admin.reportes'))
                <a class="side-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" href="{{ route('reportes.index') }}" title="Reportes">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17h3v-7H3v7zm5 0h3V7H8v10zm5 0h3v-4h-3v4zm5 0h3V3h-3v14z" fill="currentColor"/></svg>
                    <span class="side-link-text">Reportes</span>
                </a>
                @endif
            @else
                @if(auth()->user()->tieneAccesoModulo('profesor.asistencia'))
                <a class="side-link {{ request()->routeIs('profesor.asistencias.*') ? 'active' : '' }}" href="{{ route('profesor.asistencias.create') }}" title="Asistencia">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-8 14-4-4 1.41-1.41L11 14.17l5.59-5.59L18 10l-7 7z" fill="currentColor"/></svg>
                    <span class="side-link-text">Asistencia</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('profesor.mis_alumnos'))
                <a class="side-link {{ request()->routeIs('profesor.alumnos*') ? 'active' : '' }}" href="{{ route('profesor.alumnos') }}" title="Mis alumnos">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13z" fill="currentColor"/></svg>
                    <span class="side-link-text">Mis alumnos</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('profesor.pagos_cuotas'))
                <a class="side-link {{ request()->routeIs('profesor.pagos-cuotas.*') ? 'active' : '' }}" href="{{ route('profesor.pagos-cuotas.index') }}" title="Pagos de cuotas">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.09.16-2.02.71-2.61 1.56-.59.84-.88 1.81-.88 2.89 0 2.35 1.51 3.77 4.6 4.48 2.35.55 3.17 1.07 3.17 2.41 0 .95-.74 1.86-2.7 1.86-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="currentColor"/></svg>
                    <span class="side-link-text">Pagos cuotas</span>
                </a>
                @endif
                @if(auth()->user()->tieneAccesoModulo('comprobantes'))
                <a class="side-link {{ request()->routeIs('comprobantes-cuota-alumnos.*') ? 'active' : '' }}" href="{{ route('comprobantes-cuota-alumnos.index') }}" title="Comprobantes">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z" fill="currentColor"/></svg>
                    <span class="side-link-text">Comprobantes</span>
                </a>
                @endif
            @endif
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
                    <div class="top-title">@yield('page-title', 'Panel')</div>
                    <div class="top-sub">
                        <span class="muted">{{ config('app.name', 'ITO') }}</span>
                        <span class="dot">•</span>
                        <span class="muted">{{ now()->locale('es')->translatedFormat('F Y') }}</span>
                    </div>
                </div>
            </div>

            @if(auth()->user()->isAdmin())
                @include('layouts.partials.admin-topbar-actions')
            @endif
        </header>

        <section class="content content--maxton">
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
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNav();
    });
})();
</script>
@auth
<script src="{{ asset('js/recordatorio-chatbot.js') }}?v=2"></script>
@endauth
@stack('scripts')
</body>
</html>
