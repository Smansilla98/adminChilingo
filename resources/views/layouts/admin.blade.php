<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ITO - Sistema de gestión')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}">

    @stack('styles')
</head>
<body>
@auth
@php
    $sideUserName = auth()->user()->name ?: auth()->user()->username ?: 'Usuario';
    $sideUserInitials = collect(preg_split('/\s+/', trim($sideUserName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: 'U';
    $sideUserRole = 'Administrador';
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
            <a class="side-link {{ request()->routeIs('alumnos.*') ? 'active' : '' }}" href="{{ route('alumnos.index') }}" title="Alumnos">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h7v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/></svg>
                <span class="side-link-text">Alumnos</span>
            </a>
            <a class="side-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" href="{{ route('calendario.index') }}" title="Calendario">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z" fill="currentColor"/></svg>
                <span class="side-link-text">Calendario</span>
            </a>
            <a class="side-link {{ request()->routeIs('cuotas.*') ? 'active' : '' }}" href="{{ route('cuotas.index') }}" title="Cuotas">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1C5.93 1 1 5.93 1 12s4.93 11 11 11 11-4.93 11-11S18.07 1 12 1zm1 17.93c-2.83.48-5.62-.9-6.78-3.45l1.74-.99A4.99 4.99 0 0 0 13 16.9V13h-2v-2h2V8.82c-1.16.41-2 1.51-2 2.82H9c0-2.76 2.24-5 5-5v2c-1.66 0-3 1.34-3 3v2h4v2h-2v3.93z" fill="currentColor"/></svg>
                <span class="side-link-text">Cuotas</span>
            </a>
            <a class="side-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" href="{{ route('reportes.index') }}" title="Reportes">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17h3v-7H3v7zm5 0h3V7H8v10zm5 0h3v-4h-3v4zm5 0h3V3h-3v14z" fill="currentColor"/></svg>
                <span class="side-link-text">Reportes</span>
            </a>
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
            <div class="topbar-actions">
                <a href="{{ route('alumnos.create') }}" class="btn btn-pill">+ Alumno</a>
                <a href="{{ route('bloques.create') }}" class="btn btn-pill">+ Bloque</a>
                <a href="{{ route('pagos.create') }}" class="btn btn-pill btn-pill-wide">Registrar pago</a>
            </div>
        </header>

        <section class="content content--maxton">
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
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeNav(); });
})();
</script>
@stack('scripts')
</body>
</html>
