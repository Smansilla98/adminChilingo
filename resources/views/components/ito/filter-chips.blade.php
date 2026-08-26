@props([
    /** @var array<string, string> request key => etiqueta visible */
    'filters' => [],
    'clearUrl' => null,
])
@php
    $chips = [];
    foreach ($filters as $key => $label) {
        if (! request()->filled($key)) {
            continue;
        }
        $raw = request($key);
        $value = is_scalar($raw) ? (string) $raw : '';
        if ($value === '') {
            continue;
        }
        $chips[] = [
            'key' => $key,
            'label' => $label,
            'value' => \Illuminate\Support\Str::limit($value, 28),
        ];
    }
    $clear = $clearUrl ?: url()->current();
@endphp
@if(count($chips) > 0)
<div {{ $attributes->merge(['class' => 'ito-filter-chips']) }} role="status" aria-label="Filtros activos">
    <span class="ito-filter-chips-label">Filtros activos</span>
    <div class="ito-filter-chips-list">
        @foreach($chips as $chip)
            <span class="ito-filter-chip">
                <span class="ito-filter-chip-k">{{ $chip['label'] }}</span>
                <span class="ito-filter-chip-v">{{ $chip['value'] }}</span>
            </span>
        @endforeach
    </div>
    <a href="{{ $clear }}" class="ito-filter-chips-clear">Limpiar</a>
</div>
@endif
