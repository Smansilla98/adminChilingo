@props([
    'title' => null,
    'help' => null,
    'icon' => null,
    'flush' => false,
])
{{-- Sección de una ficha (show): tarjeta con título y acciones propias. --}}
<section {{ $attributes->merge(['class' => 'ito-detail-section'.($flush ? ' ito-detail-section--flush' : '')]) }}>
    @if($title || isset($actions))
        <header class="ito-detail-section-head">
            <div class="min-w-0">
                @if($title)
                    <h2 class="ito-detail-section-title">
                        @if($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
                        {{ $title }}
                    </h2>
                @endif
                @if($help)
                    <p class="ito-detail-section-help">{{ $help }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="ito-detail-section-actions">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="ito-detail-section-body">
        {{ $slot }}
    </div>
</section>
