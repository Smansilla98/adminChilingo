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
<div class="auth-shell py-4">
    <div class="auth-card" style="max-width: 52rem;">
        <div class="auth-head mb-3">
            <div class="auth-brand"><x-brand-logo /></div>
            <div>
                <div class="auth-title">@yield('guest-title', 'ITO - Sistema de gestión')</div>
                <div class="auth-sub">@yield('guest-subtitle', '')</div>
            </div>
        </div>
        <div class="auth-body">
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
        </div>
    </div>
    <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}" class="link-light">Acceso administración</a></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
