@props([
    'text' => 'Completá lo indispensable y tocá Guardar. Si falta algo, podés volver después con Editar.',
    'helpHref' => null,
    'helpLabel' => 'Ver en la guía',
])
<p class="text-muted small mb-3">
    {{ $text }}
    @if($helpHref)
        <a href="{{ $helpHref }}" class="ms-1">{{ $helpLabel }}</a>
    @endif
</p>
