<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito')
    @stack('styles')
</head>
<body class="auth-page-ito">
<a class="ito-skip" href="#contenido-principal">Ir al contenido</a>
<div class="auth-shell py-4">
    <div class="auth-card auth-card--ito auth-card--ito-wide">
        <div class="auth-card-ito__logo-ring" aria-hidden="true">
            <img src="{{ asset('images/brand/logo.png') }}" alt="La Chilinga">
        </div>
        <div class="auth-card-ito__head">
            <h1 class="auth-card-ito__title">@yield('guest-title', 'La Chilinga')</h1>
            <p class="auth-card-ito__sub">@yield('guest-subtitle', '')</p>
        </div>
        <div class="auth-card-ito__body" id="contenido-principal" tabindex="-1">
            @if(session('success'))
                <div class="auth-alerts"><div class="alert alert-success mb-0 py-2">{{ session('success') }}</div></div>
            @endif
            @if($errors->any())
                <div class="auth-alerts">
                    <div class="alert alert-danger mb-0 py-2 px-3">
                        <ul class="mb-0 ps-3 small">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @yield('content')
        </div>
        <div class="auth-card-ito__foot">
            <a href="{{ route('login') }}">Acceso administración</a>
            <span class="mx-1">·</span>
            <a href="{{ route('programa.index') }}">Programa</a>
            <span class="mx-1">·</span>
            <a href="{{ route('biblioteca.index') }}">Biblioteca</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
