@props([
    'id' => null,
    'label' => 'Acciones',
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
        title="{{ $label }}"
    >
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
        <span class="visually-hidden">{{ $label }}</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="{{ $menuId }}">
        {{ $slot }}
    </ul>
</div>
