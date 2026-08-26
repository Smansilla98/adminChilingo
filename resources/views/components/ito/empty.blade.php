@props([
    'title' => 'No hay datos',
    'description' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'icon' => 'bi-inbox',
])
<div {{ $attributes->merge(['class' => 'ito-empty-state']) }} role="status">
    <div class="ito-empty-state-icon" aria-hidden="true">
        <i class="bi {{ $icon }}"></i>
    </div>
    <p class="ito-empty-state-title">{{ $title }}</p>
    @if($description)
        <p class="ito-empty-state-desc">{{ $description }}</p>
    @endif
    @if($actionHref && $actionLabel)
        <a href="{{ $actionHref }}" class="btn btn-primary btn-sm mt-2">{{ $actionLabel }}</a>
    @elseif(isset($action))
        <div class="mt-2">{{ $action }}</div>
    @endif
</div>
