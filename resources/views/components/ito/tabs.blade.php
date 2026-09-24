@props([
    /** @var array<string, string> clave => etiqueta */
    'tabs' => [],
    'id' => 'itoTabs',
    'active' => null,
])
{{-- Pestañas de una ficha. Cada panel: <div class="tab-pane" id="{id}-{clave}" ...> dentro del slot. --}}
@php
    $activa = $active ?? array_key_first($tabs);
@endphp
<div {{ $attributes->merge(['class' => 'ito-tabs']) }} data-ito-tabs="{{ $id }}">
    <ul class="nav nav-tabs" role="tablist">
        @foreach($tabs as $clave => $etiqueta)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $clave === $activa ? 'active' : '' }}" id="{{ $id }}-{{ $clave }}-tab"
                        data-bs-toggle="tab" data-bs-target="#{{ $id }}-{{ $clave }}" type="button" role="tab"
                        aria-controls="{{ $id }}-{{ $clave }}" aria-selected="{{ $clave === $activa ? 'true' : 'false' }}">
                    {!! $etiqueta !!}
                </button>
            </li>
        @endforeach
    </ul>
    <div class="tab-content">
        {{ $slot }}
    </div>
</div>
