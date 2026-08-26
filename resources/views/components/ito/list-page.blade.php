@props([
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'showTableHint' => true,
])
<div {{ $attributes->merge(['class' => 'ito-page']) }}>
    @if($title || isset($actions))
        <div class="ito-page-head">
            <div>
                @if($eyebrow || $subtitle)
                    <p class="ito-eyebrow">{{ $eyebrow ?: 'ITO · Gestión' }}</p>
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

    <div class="ito-card">
        @isset($toolbar)
            <div class="ito-toolbar">
                {{ $toolbar }}
            </div>
        @endisset

        @isset($filters)
            {{ $filters }}
        @endisset

        @if($showTableHint)
        <p class="ito-table-hint" role="note">
            <i class="bi bi-arrows-expand" aria-hidden="true"></i>
            En celular y tablet cada fila se ve como ficha, con el nombre del campo arriba del dato.
        </p>
        @endif

        <div class="ito-table-wrap" data-ito-table-wrap tabindex="0" role="region" aria-label="{{ $title ? 'Tabla: '.$title : 'Tabla de datos' }}">
            {{ $slot }}
            <div class="ito-skeleton-overlay d-none" data-ito-table-skeleton aria-hidden="true">
                <x-ito.skeleton :rows="6" :cols="5" />
            </div>
        </div>

        @isset($footer)
            <div class="ito-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
