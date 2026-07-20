@props([
    'tone' => 'neutral', // success|warning|danger|info|neutral
    'label' => '',
])
@php
    $tone = in_array($tone, ['success', 'warning', 'danger', 'info', 'neutral'], true) ? $tone : 'neutral';
@endphp
<span {{ $attributes->merge(['class' => 'ito-status ito-status--'.$tone]) }}>
    <span class="ito-status-dot" aria-hidden="true"></span>
    <span>{{ $label !== '' ? $label : $slot }}</span>
</span>
