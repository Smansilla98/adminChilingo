<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito')
    @unless($__env->hasSection('og-propio'))
        @include('layouts.partials.og-default')
    @endunless
    <meta name="chilinga-samples" content="{{ parse_url(asset('sounds/perc'), PHP_URL_PATH) ?: '/sounds/perc' }}">
    @vite('resources/css/biblioteca.css')
    @vite('resources/css/programa.css')
    @vite('resources/css/programa-publico.css')
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
            <small>Programa · Partituras · Biblioteca · Agenda</small>
        </span>
    </a>
    <nav class="biblio-nav">
        <a href="{{ route('programa.index') }}" class="{{ request()->routeIs('programa.index') || request()->routeIs('programa.toque.show') ? 'is-active' : '' }}">Programa</a>
        <a href="{{ route('programa.partituras.index') }}" class="{{ request()->routeIs('programa.partituras.*') || request()->routeIs('programa.toque.parte') || request()->routeIs('programa.toque.editor') || request()->routeIs('programa.toque.partitura.*') ? 'is-active' : '' }}">Partituras</a>
        <a href="{{ route('comunidad.agenda') }}" class="{{ request()->routeIs('comunidad.agenda') ? 'is-active' : '' }}">Agenda</a>
        <a href="{{ route('biblioteca.index') }}" class="{{ request()->routeIs('biblioteca.index') || request()->routeIs('biblioteca.show') ? 'is-active' : '' }}">Biblioteca</a>
        <a href="{{ route('biblioteca.create') }}" class="{{ request()->routeIs('biblioteca.create') ? 'is-active' : '' }}">Subir</a>
        <a href="{{ route('archivo.index') }}">Archivo histórico</a>
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
    @yield('publico-foot', 'El programa y la biblioteca son abiertos · El material de cada toque es para alumnos y profes con cuenta')
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite('resources/js/legacy/ito-a11y.js')
@stack('scripts')
</body>
</html>
