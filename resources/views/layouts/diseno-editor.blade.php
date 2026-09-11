<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['skipBootstrap' => true])
    @stack('vite')
    @stack('styles')
</head>
<body class="diseno-studio-body">
@yield('content')
</body>
</html>
