<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    {{-- Independiente de la base de datos: se muestra aunque la app esté caída. --}}
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') · @yield('title') · La Chilinga</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}?v=30">
    <script>
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>
</head>
<body>
<main class="error-page">
    <div class="error-card">
        <a class="error-brand" href="{{ url('/') }}">
            <img src="{{ asset('images/brand/logo.png') }}" alt="" width="36" height="36">
            La Chilinga
        </a>
        <p class="error-code">@yield('code')</p>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-text">@yield('message')</p>
        <div class="error-actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a href="{{ url('/dashboard') }}" class="btn btn-primary"><i class="bi bi-house" aria-hidden="true"></i> Ir al inicio</a>
                <button type="button" class="btn btn-outline-secondary" onclick="history.length > 1 ? history.back() : location.assign('/')">Volver</button>
            @endif
        </div>
    </div>
</main>
</body>
</html>
