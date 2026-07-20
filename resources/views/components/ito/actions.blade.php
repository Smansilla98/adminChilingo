@props([
    'id' => null,
])
@php
    $menuId = $id ?: 'itoActions'.uniqid();
@endphp
<div {{ $attributes->merge(['class' => 'dropdown ito-actions']) }}>
    <button
        type="button"
        class="btn btn-sm dropdown-toggle"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        id="{{ $menuId }}"
    >
        Acciones
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="{{ $menuId }}">
        {{ $slot }}
    </ul>
</div>
