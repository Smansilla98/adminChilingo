{{-- Archivo histórico: layout público propio (oscuro, editorial). Sin Bootstrap. --}}
@php
    $seo = $seo ?? [];
    $titulo = $seo['titulo'] ?? 'Archivo histórico de La Chilinga';
    $descripcion = $seo['descripcion'] ?? 'La memoria fotográfica de La Chilinga.';
    $imagen = $seo['imagen'] ?? asset('images/brand/apple-touch-icon.png');
@endphp
<!DOCTYPE html>
<html lang="es" class="archivo-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e0d0c">
    <meta name="color-scheme" content="dark">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descripcion }}">
    @if(! empty($seo['url']))
        <link rel="canonical" href="{{ $seo['url'] }}">
    @endif
    @if(! empty($seo['noindex']))
        <meta name="robots" content="noindex, follow">
    @endif
    <meta property="og:site_name" content="La Chilinga — Archivo histórico">
    <meta property="og:locale" content="es_AR">
    <meta property="og:type" content="{{ $seo['tipo'] ?? 'website' }}">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descripcion }}">
    @if(! empty($seo['url']))<meta property="og:url" content="{{ $seo['url'] }}">@endif
    <meta property="og:image" content="{{ $imagen }}">
    @if(! empty($seo['imagen_alt']))<meta property="og:image:alt" content="{{ $seo['imagen_alt'] }}">@endif
    <meta name="twitter:card" content="{{ ! empty($seo['imagen']) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $titulo }}">
    <meta name="twitter:description" content="{{ $descripcion }}">
    <meta name="twitter:image" content="{{ $imagen }}">
    @if(! empty($seo['jsonld']))
        <script type="application/ld+json">{!! json_encode($seo['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
    <link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}?v=1" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/archivo.css') }}?v=1">
    <script>
        // Con JS, las fotos aparecen al cargar; este listener temprano no se pierde ninguna carga.
        document.documentElement.classList.add('ar-js');
        document.addEventListener('load', function (e) {
            if (e.target.classList && e.target.classList.contains('ar-fig__img')) e.target.classList.add('is-cargada');
        }, true);
    </script>
    @stack('head')
</head>
<body class="archivo @yield('body-class')">
<a class="ar-skip" href="#contenido">Ir al contenido</a>
<div class="ar-progreso" aria-hidden="true"><span data-progreso></span></div>

<header class="ar-top" data-top>
    <a class="ar-marca" href="{{ route('archivo.index') }}" aria-label="Archivo histórico de La Chilinga — inicio">
        <span class="ar-marca__nombre">La Chilinga</span>
        <span class="ar-marca__sep" aria-hidden="true">/</span>
        <span class="ar-marca__seccion">Archivo</span>
    </a>
    <nav class="ar-nav" aria-label="Archivo">
        <a href="{{ route('archivo.historia') }}" @class(['is-activo' => request()->routeIs('archivo.historia')])>Historia</a>
        <a href="{{ route('archivo.buscar') }}" @class(['is-activo' => request()->routeIs('archivo.buscar')])>
            <span class="ar-solo-escritorio">Buscar</span>
            <svg class="ar-solo-movil" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span class="ar-solo-movil ar-sr">Buscar</span>
        </a>
        <a href="{{ route('archivo.aportar') }}" class="ar-nav__aportar">Aportar</a>
        @auth
            @if(auth()->user()->acceso()->puedeAlguno(['archivo.view', 'archivo.manage', 'archivo.moderate']))
                <a href="{{ route('archivo.gestion.tablero') }}" class="ar-solo-escritorio">Gestión</a>
            @else
                <a href="{{ route('archivo.aportes.index') }}" class="ar-solo-escritorio">Mis aportes</a>
            @endif
        @endauth
    </nav>
</header>

<main id="contenido" tabindex="-1">
    @if(session('success') || session('error') || $errors->any())
        <div class="ar-avisos" role="status" aria-live="polite">
            @if(session('success'))<p class="ar-aviso ar-aviso--ok">{{ session('success') }}</p>@endif
            @if(session('error'))<p class="ar-aviso ar-aviso--error" role="alert">{{ session('error') }}</p>@endif
            @if($errors->any())
                <div class="ar-aviso ar-aviso--error" role="alert">
                    <strong>Revisá estos datos:</strong>
                    <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
        </div>
    @endif
    @yield('content')
</main>

<footer class="ar-pie">
    <div class="ar-pie__inner">
        <p class="ar-pie__lema">La memoria de La Chilinga se construye entre todos.</p>
        <p><a href="{{ route('archivo.aportar') }}">Compartí una foto histórica →</a></p>
        <p class="ar-pie__legal">
            <a href="{{ route('programa.index') }}">Programa</a> ·
            <a href="{{ route('biblioteca.index') }}">Biblioteca</a> ·
            <a href="{{ route('privacy') }}">Privacidad</a>
        </p>
    </div>
</footer>

@include('archivo.partials.visor')
@if(isset($visor))
    <script type="application/json" id="archivo-datos">{!! json_encode($visor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
<script src="{{ asset('js/archivo.js') }}?v=1" defer></script>
@stack('scripts')
</body>
</html>
