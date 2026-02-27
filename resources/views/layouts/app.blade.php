<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'La Chilinga - Sistema de Gestión')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 14rem;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #3d566e;
            --primary: #4e73df;
            --primary-dark: #2e59d9;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8f9fc;
            min-height: 100vh;
            color: #5a5c69;
        }
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding-top: 1rem;
            transition: transform 0.2s ease, width 0.2s ease;
            box-shadow: 4px 0 10px rgba(0,0,0,.08);
        }
        .sidebar .sidebar-brand {
            padding: 0 1.5rem 1.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sidebar .sidebar-brand:hover { color: #fff !important; opacity: .9; }
        .sidebar .nav {
            padding: 0 .75rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: .75rem 1rem;
            border-radius: .35rem;
            margin-bottom: .125rem;
            font-weight: 600;
            font-size: .85rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background: var(--sidebar-hover);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background: var(--primary);
        }
        .sidebar .nav-link i { font-size: 1.1rem; opacity: .9; }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: #fff;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            height: 4.375rem;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #5a5c69;
        }
        .content-wrapper {
            flex: 1;
            padding: 1.5rem;
        }
        .card {
            border: none;
            border-radius: .35rem;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: .75rem 1.25rem;
            font-weight: 700;
            color: #5a5c69;
            font-size: 1rem;
            border-radius: .35rem .35rem 0 0;
        }
        .card-header.py-3 { padding: 1rem 1.25rem; }
        .card-body { padding: 1.25rem; }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            font-weight: 600;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-sm { font-size: .8rem; }
        .table { color: #5a5c69; }
        .table thead th {
            font-weight: 700;
            font-size: .75rem;
            text-transform: uppercase;
            color: #5a5c69;
            border-bottom-width: 1px;
        }
        .alert { border: none; border-radius: .35rem; }
        .navbar-brand { font-weight: 700; }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @auth
    <nav class="sidebar">
        <a class="sidebar-brand" href="{{ route('dashboard') }}">
            <i class="bi bi-music-note-beamed"></i> La Chilinga
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('programa.*') ? 'active' : '' }}" href="{{ route('programa.index') }}">
                    <i class="bi bi-music-note-list"></i> Programa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" href="{{ route('calendario.index') }}">
                    <i class="bi bi-calendar3"></i> Calendario
                </a>
            </li>
            @if(auth()->user()->isAdmin())
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" href="{{ route('reportes.index') }}">
                    <i class="bi bi-bar-chart"></i> Reportes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('alumnos.*') ? 'active' : '' }}" href="{{ route('alumnos.index') }}">
                    <i class="bi bi-people"></i> Alumnos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('profesores.*') ? 'active' : '' }}" href="{{ route('profesores.index') }}">
                    <i class="bi bi-person-badge"></i> Profesores
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('bloques.*') ? 'active' : '' }}" href="{{ route('bloques.index') }}">
                    <i class="bi bi-collection"></i> Bloques
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('sedes.*') ? 'active' : '' }}" href="{{ route('sedes.index') }}">
                    <i class="bi bi-building"></i> Sedes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('eventos.*') ? 'active' : '' }}" href="{{ route('eventos.index') }}">
                    <i class="bi bi-calendar-event"></i> Eventos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('shows.*') ? 'active' : '' }}" href="{{ route('shows.index') }}">
                    <i class="bi bi-mic"></i> Shows
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventarios.*') ? 'active' : '' }}" href="{{ route('inventarios.index') }}">
                    <i class="bi bi-box-seam"></i> Inventarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('plan-compras.*') ? 'active' : '' }}" href="{{ route('plan-compras.index') }}">
                    <i class="bi bi-clipboard-data"></i> Plan de compras
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('ordenes-compra.*') ? 'active' : '' }}" href="{{ route('ordenes-compra.index') }}">
                    <i class="bi bi-file-earmark-text"></i> Órdenes de compra
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('asistencias.*') ? 'active' : '' }}" href="{{ route('asistencias.index') }}">
                    <i class="bi bi-check-circle"></i> Asistencias
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cuotas.*') ? 'active' : '' }}" href="{{ route('cuotas.index') }}">
                    <i class="bi bi-cash-coin"></i> Cuotas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('pagos.*') ? 'active' : '' }}" href="{{ route('pagos.index') }}">
                    <i class="bi bi-receipt"></i> Pagos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('facturacion-mensual.*') ? 'active' : '' }}" href="{{ route('facturacion-mensual.index') }}">
                    <i class="bi bi-graph-up"></i> Facturación
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('gastos.*') ? 'active' : '' }}" href="{{ route('gastos.index') }}">
                    <i class="bi bi-wallet2"></i> Gastos
                </a>
            </li>
            @endif
        </ul>
    </nav>
    @endauth

    <div class="main-content">
        @auth
        <header class="topbar">
            <span class="page-title">@yield('page-title', 'Dashboard')</span>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">{{ auth()->user()->name ?: auth()->user()->username }}</span>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </button>
                </form>
            </div>
        </header>
        @endauth

        <div class="content-wrapper">
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @stack('scripts')
</body>
</html>
