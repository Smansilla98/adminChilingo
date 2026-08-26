@props([
    'index' => 0,
    'title' => null,
    'help' => null,
])
<section
    {{ $attributes->merge(['class' => 'ito-step-panel'.($index === 0 ? ' is-active' : '')]) }}
    data-ito-step="{{ (int) $index }}"
    @if($index !== 0) hidden @endif
>
    @if($title || $help)
        <header class="ito-step-panel-head">
            @if($title)
                <h2 class="ito-step-panel-title">{{ $title }}</h2>
            @endif
            @if($help)
                <p class="ito-step-panel-help">{{ $help }}</p>
            @endif
        </header>
    @endif
    {{ $slot }}
</section>
