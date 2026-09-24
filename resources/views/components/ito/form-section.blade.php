@props([
    'title' => null,
    'help' => null,
    'icon' => null,
])
{{-- Bloque de un formulario: título corto, ayuda opcional y los campos. --}}
<section {{ $attributes->merge(['class' => 'ito-form-section']) }}>
    @if($title || $help)
        <header class="ito-form-section-head">
            @if($title)
                <h2 class="ito-form-section-title">
                    @if($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
                    {{ $title }}
                </h2>
            @endif
            @if($help)
                <p class="ito-form-section-help">{{ $help }}</p>
            @endif
        </header>
    @endif
    {{ $slot }}
</section>
