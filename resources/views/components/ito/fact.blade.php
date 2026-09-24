@props([
    'label' => '',
    'value' => null,
    'mono' => false,
])
<div class="ito-fact">
    <dt>{{ $label }}</dt>
    <dd @class(['ito-mono' => $mono])>
        @if($slot->isNotEmpty())
            {{ $slot }}
        @else
            {{ ($value === null || $value === '') ? '—' : $value }}
        @endif
    </dd>
</div>
