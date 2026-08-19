<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Programa') — {{ config('app.name', 'La Chilinga') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}?v=13">
    <link rel="stylesheet" href="{{ asset('css/biblioteca.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/programa.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/programa-publico.css') }}?v=3">
    @include('layouts.partials.apariencia-head')
    @stack('head')
    @stack('vite')
    @stack('styles')
</head>
<body class="biblio-body @yield('body-class')">
<a class="ito-skip" href="#contenido-principal">Ir al contenido</a>
<header class="biblio-top">
    <a href="{{ route('programa.index') }}" class="biblio-brand">
        <x-brand-logo variant="sidebar" />
        <span>
            <strong>@yield('publico-brand', 'La Chilinga')</strong>
            <small>Programa · Partituras · Biblioteca</small>
        </span>
    </a>
    <nav class="biblio-nav">
        <a href="{{ route('programa.index') }}" class="{{ request()->routeIs('programa.index') || request()->routeIs('programa.toque.show') ? 'is-active' : '' }}">Programa</a>
        <a href="{{ route('programa.partituras.index') }}" class="{{ request()->routeIs('programa.partituras.*') || request()->routeIs('programa.toque.parte') || request()->routeIs('programa.toque.editor') || request()->routeIs('programa.toque.partitura.*') ? 'is-active' : '' }}">Partituras</a>
        <a href="{{ route('biblioteca.index') }}" class="{{ request()->routeIs('biblioteca.index') || request()->routeIs('biblioteca.show') ? 'is-active' : '' }}">Biblioteca</a>
        <a href="{{ route('biblioteca.create') }}" class="{{ request()->routeIs('biblioteca.create') ? 'is-active' : '' }}">Subir</a>
        @auth
            <a href="{{ route('dashboard') }}">Panel</a>
        @else
            <a href="{{ route('login') }}">Entrar</a>
        @endauth
    </nav>
</header>

<main id="contenido-principal" class="biblio-main @yield('main-class')" tabindex="-1">
    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
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
    @yield('publico-foot', 'Espacio abierto · Programa, partituras y biblioteca sin cuenta')
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/ito-a11y.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
