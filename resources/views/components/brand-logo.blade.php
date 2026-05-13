@props(['variant' => 'auth'])
@php
    $size = $variant === 'sidebar' ? 34 : 40;
    $class = 'brand-logo' . ($variant === 'sidebar' ? ' brand-logo--sidebar' : ' brand-logo--auth');
@endphp
<img src="{{ asset('images/brand/logo.png') }}" alt="ITO" class="{{ $class }}" width="{{ $size }}" height="{{ $size }}" loading="eager" decoding="async" {{ $attributes }}>
