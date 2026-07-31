<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Biblioteca') — {{ config('app.name', 'ITO') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('css/biblioteca.css') }}?v=2">
    @stack('styles')
</head>
<body class="biblio-body">
<header class="biblio-top">
    <a href="{{ route('biblioteca.index') }}" class="biblio-brand">
        <x-brand-logo variant="sidebar" />
        <span>
            <strong>Biblioteca</strong>
            <small>{{ config('app.name', 'La Chilinga') }}</small>
        </span>
    </a>
    <nav class="biblio-nav">
        <a href="{{ route('biblioteca.index') }}" class="{{ request()->routeIs('biblioteca.index') ? 'is-active' : '' }}">Explorar</a>
        <a href="{{ route('biblioteca.create') }}" class="{{ request()->routeIs('biblioteca.create') ? 'is-active' : '' }}">Subir</a>
        @auth
            <a href="{{ route('dashboard') }}">Panel</a>
        @else
            <a href="{{ route('login') }}">Admin</a>
        @endauth
    </nav>
</header>

<main class="biblio-main">
    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>

<footer class="biblio-foot">
    Espacio abierto para compartir material · Sin cuenta requerida para subir
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
