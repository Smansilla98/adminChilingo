@props([
    'name' => '',
    'sub' => null,
])
@php
    $initials = collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->join('') ?: '?';
@endphp
<div {{ $attributes->merge(['class' => 'ito-person']) }}>
    <span class="ito-avatar" aria-hidden="true">{{ $initials }}</span>
    <div class="ito-person-meta">
        <div class="ito-person-name">{{ $name }}</div>
        @if($sub)
            <div class="ito-person-sub">{{ $sub }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
