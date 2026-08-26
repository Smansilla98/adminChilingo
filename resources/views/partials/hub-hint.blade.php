@props([
    'storageKey' => 'ito-hub-hint-v1',
    'title' => 'Empezá por acá',
    'body' => 'Usá Ctrl+K para buscar alumno o módulo. Revisá «Necesita atención» y las acciones rápidas.',
    'helpHref' => null,
    'helpLabel' => 'Ver guía',
])
@php
    $helpHref = $helpHref ?: (Route::has('ayuda') ? route('ayuda') : null);
@endphp
<aside
    class="ito-hub-hint"
    data-ito-hub-hint
    data-storage-key="{{ $storageKey }}"
    role="note"
    hidden
>
    <div class="ito-hub-hint-body">
        <strong class="ito-hub-hint-title">{{ $title }}</strong>
        <p class="ito-hub-hint-text mb-0">{{ $body }}</p>
        @if($helpHref)
            <a href="{{ $helpHref }}#c-pagos" class="ito-hub-hint-link">{{ $helpLabel }}</a>
        @endif
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary ito-hub-hint-dismiss" data-ito-hub-hint-dismiss>
        Entendido
    </button>
</aside>
<script>
(function () {
    var root = document.currentScript && document.currentScript.previousElementSibling;
    if (!root || !root.hasAttribute('data-ito-hub-hint')) {
        root = document.querySelector('[data-ito-hub-hint]');
    }
    if (!root) return;
    var key = root.getAttribute('data-storage-key') || 'ito-hub-hint-v1';
    try {
        if (localStorage.getItem(key) === '1') return;
    } catch (e) {}
    root.hidden = false;
    root.querySelector('[data-ito-hub-hint-dismiss]')?.addEventListener('click', function () {
        try { localStorage.setItem(key, '1'); } catch (e) {}
        root.hidden = true;
    });
})();
</script>
