<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Diseño · {{ config('app.name', 'La Chilinga') }}</title>
    <link rel="icon" href="{{ asset('images/brand/logo.png') }}">
    {{-- Editor OpenDesign (MIT) — ver resources/opendesign/NOTICE.md --}}
    <script>
        window.__OPENDESIGN__ = @json($config);
    </script>
    @vite('src/main.tsx', 'opendesign')
</head>
<body>
    <noscript>El editor de Diseño necesita JavaScript.</noscript>
    <div id="app"></div>
</body>
</html>
