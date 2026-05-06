<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'La Chilinga - Admin')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}">

    @stack('styles')
</head>
<body>
@auth
<div class="shell">
    <aside class="sidebar">
        <a class="side-logo" href="{{ route('dashboard') }}" aria-label="Dashboard">
            <span class="lc-badge">LC</span>
        </a>

        <nav class="side-nav" aria-label="Navegación">
            <a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard" aria-label="Dashboard">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" fill="currentColor"/>
                </svg>
            </a>
            <a class="side-link {{ request()->routeIs('alumnos.*') ? 'active' : '' }}" href="{{ route('alumnos.index') }}" title="Alumnos" aria-label="Alumnos">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h7v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/>
                </svg>
            </a>
            <a class="side-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" href="{{ route('calendario.index') }}" title="Calendario" aria-label="Calendario">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z" fill="currentColor"/>
                </svg>
            </a>
            <a class="side-link {{ request()->routeIs('cuotas.*') ? 'active' : '' }}" href="{{ route('cuotas.index') }}" title="Cuotas" aria-label="Cuotas">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 1C5.93 1 1 5.93 1 12s4.93 11 11 11 11-4.93 11-11S18.07 1 12 1zm1 17.93c-2.83.48-5.62-.9-6.78-3.45l1.74-.99A4.99 4.99 0 0 0 13 16.9V13h-2v-2h2V8.82c-1.16.41-2 1.51-2 2.82H9c0-2.76 2.24-5 5-5v2c-1.66 0-3 1.34-3 3v2h4v2h-2v3.93z" fill="currentColor"/>
                </svg>
            </a>
            <a class="side-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" href="{{ route('reportes.index') }}" title="Reportes" aria-label="Reportes">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 17h3v-7H3v7zm5 0h3V7H8v10zm5 0h3v-4h-3v4zm5 0h3V3h-3v14z" fill="currentColor"/>
                </svg>
            </a>
        </nav>

        <div class="side-user" title="{{ auth()->user()->name ?: auth()->user()->username }}">
            @php
                $n = auth()->user()->name ?: auth()->user()->username ?: 'Usuario';
                $initials = collect(preg_split('/\s+/', trim($n)))->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
            @endphp
            <div class="side-avatar">{{ $initials ?: 'U' }}</div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <div class="top-kicker">BIENVENIDO</div>
                <div class="top-title">@yield('page-title', 'Panel Administrador')</div>
                <div class="top-sub">
                    <span class="muted">La Chilinga</span>
                    <span class="dot">•</span>
                    <span class="muted">{{ now()->locale('es')->translatedFormat('F Y') }}</span>
                </div>
            </div>
            <div class="topbar-actions">
                <a href="{{ route('alumnos.create') }}" class="btn btn-pill">+ Alumno</a>
                <a href="{{ route('bloques.create') }}" class="btn btn-pill">+<br>Bloque</a>
                <a href="{{ route('pagos.create') }}" class="btn btn-pill btn-pill-wide">Registrar pago</a>
            </div>
        </header>

        <section class="content">
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
@stack('scripts')
</body>
</html>

