@php
    $hrefEditor = $item->urlPasarAlEditor();
    $variant = $variant ?? 'sm';
@endphp
@if($hrefEditor)
    <a
        href="{{ $hrefEditor }}"
        class="{{ $variant === 'primary' ? 'btn btn-outline-primary' : 'btn btn-sm btn-outline-primary' }}"
        data-biblio-ignore
        title="Abrir el editor con este original al lado"
    >
        <i class="bi bi-music-note-list" aria-hidden="true"></i>
        Pasar al editor
    </a>
@endif
