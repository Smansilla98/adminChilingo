@props([
    'cancel' => null,
    'submit' => 'Guardar',
    'icon' => 'bi-check-lg',
])
{{-- Pie de formulario: acciones secundarias a la izquierda, Cancelar + principal a la derecha. --}}
<div {{ $attributes->merge(['class' => 'ito-form-actions']) }}>
    @isset($extra)
        <div class="ito-form-actions-extra">{{ $extra }}</div>
    @endisset
    @if($cancel)
        <a href="{{ $cancel }}" class="btn btn-outline-secondary">Cancelar</a>
    @endif
    @if($submit)
        <button type="submit" class="btn btn-primary">
            @if($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
            {{ $submit }}
        </button>
    @endif
    {{ $slot }}
</div>
