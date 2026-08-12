@php
    $variant = $variant ?? 'icon';
    $titulo = $item->titulo ?: 'Material de la biblioteca';
    $texto = $item->textoCompartir();
    $url = $item->permalinkUrl();
@endphp
<button
    type="button"
    class="biblio-share-btn {{ $variant === 'primary' ? 'biblio-share-btn--primary btn btn-primary btn-sm' : '' }}"
    data-biblio-ignore
    data-biblio-share
    data-share-url="{{ $url }}"
    data-share-title="{{ e($titulo) }}"
    data-share-text="{{ e($texto) }}"
    aria-label="Compartir {{ $titulo }}"
    title="Compartir enlace"
>
    <i class="bi bi-share" aria-hidden="true"></i>
    @if($variant === 'primary')
        <span>Compartir</span>
    @elseif($variant === 'text')
        <span>Compartir</span>
    @endif
</button>
