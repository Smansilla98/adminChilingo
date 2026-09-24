@props([
    'name' => '',
    'tabs' => 'itoTabs',
    'active' => false,
])
<div {{ $attributes->merge(['class' => 'tab-pane fade'.($active ? ' show active' : '')]) }} id="{{ $tabs }}-{{ $name }}" role="tabpanel" aria-labelledby="{{ $tabs }}-{{ $name }}-tab" tabindex="0">
    {{ $slot }}
</div>
