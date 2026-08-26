@props([
    'rows' => 6,
    'cols' => 5,
])
<div {{ $attributes->merge(['class' => 'ito-skeleton-table', 'role' => 'status', 'aria-live' => 'polite', 'aria-label' => 'Cargando listado']) }}>
    <span class="visually-hidden">Cargando…</span>
    <div class="ito-skeleton-head" aria-hidden="true">
        @for($c = 0; $c < (int) $cols; $c++)
            <span class="ito-skeleton-bar ito-skeleton-bar--sm"></span>
        @endfor
    </div>
    @for($r = 0; $r < (int) $rows; $r++)
        <div class="ito-skeleton-row" aria-hidden="true">
            @for($c = 0; $c < (int) $cols; $c++)
                <span class="ito-skeleton-bar {{ $c === 0 ? 'ito-skeleton-bar--lg' : '' }}"></span>
            @endfor
        </div>
    @endfor
</div>
