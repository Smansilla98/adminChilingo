@props([
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'flush' => false,
    'plain' => false,
])
{{-- Shell de página unificado (create / edit / show). Misma cabeza que list-page. --}}
<div {{ $attributes->merge(['class' => 'ito-page']) }}>
    @if($title || isset($actions))
        <div class="ito-page-head">
            <div>
                @if($eyebrow)
                    <p class="ito-eyebrow">{{ $eyebrow }}</p>
                @endif
                @if($title)
                    <h1 class="ito-page-title">{{ $title }}</h1>
                @endif
                @if($subtitle)
                    <p class="ito-page-sub">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="ito-page-actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    @isset($intro)
        <div class="ito-page-intro">
            {{ $intro }}
        </div>
    @endisset

    @if($plain)
        {{-- Sin tarjeta envolvente: las secciones del contenido son las tarjetas. --}}
        <div class="ito-page-body">
            {{ $slot }}
        </div>
    @else
        <div @class(['ito-card', 'ito-card--flush' => $flush, 'ito-card--body' => ! $flush])>
            {{ $slot }}
        </div>
    @endif

    @isset($aside)
        <div class="ito-page-aside">
            {{ $aside }}
        </div>
    @endisset
</div>
