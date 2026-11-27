@props(['variant' => 'auth'])
@php
    // Marca de ITO recortada (sin el lema): proporción 2:1, se fija la altura.
    $alto = $variant === 'sidebar' ? 26 : 36;
    $class = 'brand-logo' . ($variant === 'sidebar' ? ' brand-logo--sidebar' : ' brand-logo--auth');
@endphp
<img src="{{ asset('images/brand/ito-marca.png') }}" alt="ITO" class="{{ $class }}" height="{{ $alto }}" loading="eager" decoding="async" {{ $attributes }}>
