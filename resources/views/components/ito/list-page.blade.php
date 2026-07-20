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

        <div class="ito-table-wrap">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="ito-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
