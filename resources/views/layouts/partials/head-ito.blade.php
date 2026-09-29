{{-- Head compartido: misma marca, fuentes y CSS en admin, público, auth y diseño. --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $headTitle ?? $__env->yieldContent('title', 'La Chilinga') }}</title>
<link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}?v=1" type="image/png" sizes="32x32">
<link rel="icon" href="{{ asset('favicon.ico') }}?v=1" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
@if(empty($skipBootstrap))
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}?v=30">
@include('layouts.partials.apariencia-head')
