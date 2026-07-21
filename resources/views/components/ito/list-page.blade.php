@props([
    'title' => null,
    'subtitle' => null,
])
<div {{ $attributes->merge(['class' => 'ito-page']) }}>
    @if($title || isset($actions))
        <div class="ito-page-head">
            <div>
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

    <div class="ito-card">
        @isset($toolbar)
            <div class="ito-toolbar">
                {{ $toolbar }}
            </div>
        @endisset

        <p class="ito-table-hint" role="note">
            <i class="bi bi-arrows-expand" aria-hidden="true"></i>
            En pantallas chicas podés deslizar la tabla horizontalmente. La primera columna queda fija.
        </p>

        <div class="ito-table-wrap" tabindex="0" role="region" aria-label="{{ $title ? 'Tabla: '.$title : 'Tabla de datos' }}">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="ito-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
